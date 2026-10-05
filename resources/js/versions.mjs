/*
 * Versionen – frühere Stände von Seiten und Einträgen ansehen und wiederherstellen (Admin\VersionsController).
 * Geladen erst beim Öffnen (admin.js: Knopf [data-versions]).
 *
 *   open({ endpoint, csrf })   endpoint = GET /admin/api/pages/{id}/versions bzw. /admin/api/data/{handle}/{id}/versions
 *
 * Aufbau: links die Zeitleiste – Karten senkrecht gestapelt, nach Tagen gruppiert (oben „Jetzt“, darunter älter), je Karte Uhrzeit,
 * Notiz, Person und bei Einträgen die geänderten Felder; rechts die Vorschau des gewählten Stands, die ganz normal scrollt.
 * Seiten: echte Seite im Iframe (…/versions/{rev}/vorschau), Umschalter Desktop/Mobil. Einträge: Felder wie auf der Website,
 * Änderungen gegenüber jetzt markiert, „Nur Änderungen“.
 * Tastatur: ↑/↓ in der Zeitleiste, Pos1/Ende, Esc schließt. „Wiederherstellen“ mit Rückfrage: Seiten als Entwurf (öffnet im Editor),
 * Einträge sofort (Status bleibt) – der bisherige Stand bleibt als Version; Meldung nach dem Neuladen (CMSAdmin.toastNext).
 * Eigene Shadow-DOM-Wurzel mit css/versions.css (hell/dunkel nach Systemeinstellung).
 */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const fill = (s, p = {}) => Object.entries(p).reduce((x, [k, v]) => x.replaceAll('{' + k + '}', String(v)), String(s ?? ''));
const SVG = {
  // Uhr im Kopf: Zifferblatt mit Strichen, Stunden- und Minutenzeiger (drehen per JS zur Zeit des gewählten Stands)
  clock: '<svg class="vs-clock" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="18" class="vs-clock__face"/>'
    + Array.from({ length: 12 }, (_, i) => `<line x1="20" y1="${i % 3 ? 4.5 : 3.5}" x2="20" y2="${i % 3 ? 6.5 : 7.5}" class="vs-clock__tick" transform="rotate(${i * 30} 20 20)"/>`).join('')
    + '<line x1="20" y1="20" x2="20" y2="11" class="vs-clock__h" data-hand="h"/><line x1="20" y1="21.5" x2="20" y2="6.5" class="vs-clock__m" data-hand="m"/><circle cx="20" cy="20" r="1.8" class="vs-clock__pin"/></svg>',
  down: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
  up: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>',
  restore: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>',
};

export async function open({ endpoint, csrf = '', css: cssUrl = '' } = {}) {
  if (!endpoint || document.getElementById('cms-versions')) return;
  const opener = document.activeElement;
  const host = document.createElement('div');
  host.id = 'cms-versions';
  const root = host.attachShadow({ mode: 'open' });
  const css = document.createElement('link');
  css.rel = 'stylesheet';
  // Versionierte Adresse vom Server (asset(), ?v=…) – sonst bliebe nach Updates das alte Stylesheet im Browser-Cache
  css.href = cssUrl || new URL('../css/versions.css' + new URL(import.meta.url).search, import.meta.url).href;
  root.append(css);
  const vs = document.createElement('div');
  vs.className = 'vs';
  vs.setAttribute('role', 'dialog');
  vs.setAttribute('aria-modal', 'true');
  vs.setAttribute('aria-labelledby', 'vs-t');
  vs.innerHTML = `<div class="vs__box"><div class="vs-head"><span class="vs-head__ico">${SVG.clock}</span><span class="vs-cal" aria-hidden="true"><span class="vs-cal__m" data-cal-m></span><span class="vs-cal__d" data-cal-d></span></span><div><p class="vs-head__t" id="vs-t">…</p><p class="vs-head__s" data-sub></p></div><button type="button" class="vs-x" data-close>×</button></div><div class="vs-load" data-wait>…</div></div>`;
  root.append(vs);
  document.body.append(host);
  const prevOverflow = document.documentElement.style.overflow;
  document.documentElement.style.overflow = 'hidden';
  function close() {
    document.removeEventListener('keydown', onKey, true);
    host.remove();
    document.documentElement.style.overflow = prevOverflow;
    opener?.focus?.({ preventScroll: true });
  }
  vs.querySelector('[data-close]').addEventListener('click', close);
  vs.addEventListener('click', e => { if (e.target === vs) close(); });   // Klick neben das Fenster
  document.addEventListener('keydown', onKey, true);

  let data;
  try {
    const r = await fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    data = await r.json();
    if (!r.ok || !data.ok) throw new Error(data.error || r.statusText);
  } catch (e) {
    vs.querySelector('[data-wait]').textContent = e.message || 'Fehler';
    vs.querySelector('[data-close]').focus();
    return;
  }
  const T = data.texts || {};
  const L = (k, p) => fill(T[k] ?? k, p);   // Texte vom Server (VersionsController::texts)
  const V = data.versions || [];
  const isEntry = data.kind === 'entry';
  let cur = V.length > 1 ? 1 : 0, busy = false, mobile = false;
  let mark = true;   // Änderungen hervorheben (gegenüber dem vorherigen Stand) – gemerkt je Browser
  let cmp = false;   // Gegenüberstellen: links live (Seite) bzw. jetzt (Eintrag), rechts der gewählte Stand
  try { mark = localStorage.getItem('cms-versions-mark') !== '0'; cmp = localStorage.getItem('cms-versions-cmp') === '1'; } catch { /* privates Fenster */ }

  vs.querySelector('#vs-t').textContent = L('title');
  vs.querySelector('[data-close]').setAttribute('aria-label', L('close'));
  vs.querySelector('[data-sub]').textContent = [data.table, data.title, V.length - 1 === 1 ? L('count1') : L('count', { n: V.length - 1 })].filter(Boolean).join(' · ');
  vs.querySelector('[data-wait]').remove();

  // Zeitleiste: Tage als Gruppen, Karten als Optionen einer Liste
  const days = [];
  V.forEach((v, i) => {
    const day = v.day === data.today ? L('today') : v.day;
    if (!days.length || days[days.length - 1].day !== day) days.push({ day, items: [] });
    days[days.length - 1].items.push(i);
  });
  const card = i => {
    const v = V[i];
    const d = v.diff || {};
    const list = isEntry ? (v.changes || []).map(c => [c, ''])
      : [...(d.changed ? [[L('blocksChanged', { n: d.changed }), 'chg']] : []), ...(d.new ? [[L('blocksNew', { n: d.new }), 'new']] : []),
        ...(d.removed?.length ? [[L('blocksRemoved', { list: d.removed.length }), 'del']] : [])];
    const chips = list.length
      ? `<span class="vs-chips">${list.slice(0, 4).map(([c, k]) => `<span class="vs-chip${k ? ' vs-chip--' + k : ''}">${esc(c)}</span>`).join('')}${list.length > 4 ? `<span class="vs-chip">+${list.length - 4}</span>` : ''}</span>`
      : '';
    return `<li class="vs-item${v.now ? ' is-now' : ''}" role="presentation"><button type="button" class="vs-card" role="option" data-i="${i}" aria-selected="false" tabindex="-1">
      <span class="vs-card__top"><span class="vs-card__time">${esc(v.time)}</span><span class="vs-card__ago">${esc(v.now ? '' : v.ago)}</span>${v.now ? `<span class="vs-badge">${esc(L('now'))}</span>` : ''}</span>
      <span class="vs-card__note">${esc(v.note)}</span>
      ${v.user ? `<span class="vs-card__user">${esc(v.user)}</span>` : ''}${chips}</button></li>`;
  };
  const box = vs.querySelector('.vs__box');
  box.insertAdjacentHTML('beforeend', `
    <div class="vs-tl" role="listbox" aria-label="${esc(L('timeline'))}" data-tl>
      ${days.map(g => `<p class="vs-day" aria-hidden="true">${esc(g.day)}</p><ul class="vs-list" role="group" aria-label="${esc(g.day)}">${g.items.map(card).join('')}</ul>`).join('')}
    </div>
    <section class="vs-pv" aria-label="${esc(L('preview'))}">
      <div class="vs-pv__bar">
        <div><p class="vs-pv__t" data-pt></p><span class="vs-pv__s" data-ps></span></div>
        <div class="vs-pv__tools">
          <label class="vs-only"><input type="checkbox" role="switch" data-cmp${cmp ? ' checked' : ''}> ${esc(L('compare'))}</label>
          <label class="vs-only vs-only--mark"><input type="checkbox" role="switch" data-mark${mark ? ' checked' : ''}> ${esc(L('highlight'))}</label>
          ${isEntry ? `<label class="vs-only"><input type="checkbox" data-only> ${esc(L('onlyChanges'))}</label>`
            : `<span class="vs-seg" role="group" aria-label="${esc(L('preview'))}"><button type="button" data-dev="desktop" aria-pressed="true">${esc(L('desktop'))}</button><button type="button" data-dev="mobile" aria-pressed="false">${esc(L('mobile'))}</button></span>`}
          <button type="button" class="vs-btn" data-older>${SVG.down}<span>${esc(L('older'))}</span></button>
          <button type="button" class="vs-btn" data-newer>${SVG.up}<span>${esc(L('newer'))}</span></button>
          <button type="button" class="vs-btn vs-btn--primary" data-restore>${SVG.restore}<span>${esc(L('restore'))}</span></button>
        </div>
      </div>
      <div class="vs-confirm" data-confirm hidden><p>${esc(isEntry ? L('askEntry') : L('askPage'))}</p>
        <button type="button" class="vs-btn" data-no>${esc(L('back'))}</button><button type="button" class="vs-btn vs-btn--primary" data-yes>${esc(L('yes'))}</button></div>
      <p class="vs-msg" data-msg role="alert" hidden></p>
      <p class="vs-diff" data-diff aria-live="polite" hidden></p>
      <div class="vs-frame" data-frame>
        <div class="vs-pane" data-pane-live hidden><p class="vs-pane__h" data-live-h></p><div class="vs-win" data-win-live></div></div>
        <div class="vs-pane"><p class="vs-pane__h" data-sel-h hidden></p><div class="vs-win" data-win></div></div>
        <p class="vs-load" data-load hidden>${esc(L('loading'))}</p></div>
    </section>`);

  const cards = [...vs.querySelectorAll('.vs-card')];
  const win = vs.querySelector('[data-win]'), frame = vs.querySelector('[data-frame]'), load = vs.querySelector('[data-load]');
  const restoreBtn = vs.querySelector('[data-restore]');
  let iframe = null;

  const renderEntry = (v, target = win, marked = mark) => {
    const status = v.status === 'draft' ? `<span class="vs-status vs-status--draft">${esc(L('draft'))}</span>` : v.status ? `<span class="vs-status">${esc(L('published'))}</span>` : '';
    target.classList.toggle('vs-mark', marked);
    target.innerHTML = `<div class="vs-scroll"><div class="vs-entry"><h2 class="vs-entry__t">${esc(v.title || data.title)}</h2>
      <p class="vs-entry__m">${status}${esc(v.at)}${v.user ? ' · ' + esc(v.user) : ''}</p>
      <dl class="vs-fields">${(v.fields || []).map(f => `<div class="vs-f${f.changed ? ' is-changed' : ''}${f.empty ? ' is-empty' : ''}"><dt data-changed="${esc(L('changed'))}">${esc(f.label)}</dt><dd>${f.empty ? esc(L('empty')) : f.html}</dd></div>`).join('') || `<p>${esc(L('noFields'))}</p>`}</dl></div></div>`;
  };
  const renderPage = v => {
    if (!iframe) {
      iframe = document.createElement('iframe');
      // Vorschau oben beginnen (Kits mit sanftem Scrollen/Ankern würden sonst mitten auf der Seite starten); markierte Blöcke hervorheben
      iframe.addEventListener('load', () => {
        load.hidden = true;
        try { iframe.contentWindow.scrollTo({ top: 0, behavior: 'instant' }); decorate(iframe.contentDocument); } catch { /* */ }
        if (liveFrame) { couple(iframe, liveFrame); couple(liveFrame, iframe); if (cmp && leader !== iframe) syncFrom(liveFrame, iframe); }
      });
      win.append(iframe);
    }
    iframe.title = (v.now ? L('now') : v.at) + ' – ' + data.title;
    load.hidden = false;
    iframe.src = mark && !v.diff?.first ? v.preview_mark : v.preview;
  };
  // Gegenüberstellen: links live (Seite: veröffentlichte Fassung) bzw. jetzt (Eintrag) – einmal geladen, Scrollen gekoppelt
  const paneLive = vs.querySelector('[data-pane-live]'), winLive = vs.querySelector('[data-win-live]');
  let liveFrame = null, leader = null;
  // Gekoppeltes Scrollen: nur die Seite, die gerade bedient wird, führt (sonst schaukeln sich beide auf). Abgleich am selben Block
  // (Abschnitte mit id, z. B. b-…/Anker): gleicher Block oben, gleiche Lage darin; ohne gemeinsamen Block nach Anteil der Höhe.
  const anchorAt = (doc, y) => {
    let best = null;
    for (const el of doc.querySelectorAll('body [id]')) {
      if (!/^(b-|sec-)/.test(el.id) && el.tagName !== 'SECTION') continue;
      const top = el.getBoundingClientRect().top + y;
      if (top <= y + 2 && (!best || top >= best.top)) best = { el, top };
    }
    return best;
  };
  const syncFrom = (from, to) => {
    const wf = from.contentWindow, wt = to.contentWindow;
    const df = from.contentDocument, dt = to.contentDocument;
    if (!df || !dt) return;
    const y = wf.scrollY, a = anchorAt(df, y);
    let ty = null;
    const twin = a && dt.getElementById(a.el.id);
    if (twin) {
      const r1 = a.el.getBoundingClientRect(), r2 = twin.getBoundingClientRect();
      const inside = r1.height ? (y - a.top) / r1.height : 0;
      ty = r2.top + wt.scrollY + inside * r2.height;
    } else {
      const sf = df.scrollingElement, st = dt.scrollingElement;
      ty = (y / Math.max(1, sf.scrollHeight - sf.clientHeight)) * (st.scrollHeight - st.clientHeight);
    }
    wt.scrollTo({ top: Math.max(0, ty), behavior: 'instant' });
  };
  const couple = (a, b) => {
    try {
      const w = a.contentWindow, d = a.contentDocument;
      if (!w || w._vsCoupled === b) return;
      w._vsCoupled = b;
      d.documentElement.style.scrollBehavior = 'auto';   // kein sanftes Scrollen in der Vorschau (sonst Nachlaufen beim Abgleich)
      const lead = () => { leader = a; };
      ['wheel', 'touchstart', 'pointerdown', 'keydown'].forEach(t => d.addEventListener(t, lead, { passive: true, capture: true }));
      a.addEventListener('pointerenter', lead);
      let raf = 0;
      w.addEventListener('scroll', () => {
        if (!cmp || leader !== a || raf) return;
        raf = requestAnimationFrame(() => { raf = 0; syncFrom(a, b); });
      }, { passive: true });
    } catch { /* */ }
  };
  function renderCompare() {
    frame.classList.toggle('is-cmp', cmp);
    paneLive.hidden = !cmp;
    vs.querySelector('[data-sel-h]').hidden = !cmp;
    if (!cmp) return;
    vs.querySelector('[data-live-h]').textContent = isEntry ? L('now') : (data.has_live ? L('live') : L('liveDraft'));
    vs.querySelector('[data-sel-h]').textContent = L('selected') + ' · ' + (V[cur].now ? L('now') : V[cur].at);
    if (isEntry) { renderEntry(V[0], winLive, false); return; }
    if (!liveFrame) {
      liveFrame = document.createElement('iframe');
      liveFrame.title = L('live') + ' – ' + data.title;
      liveFrame.addEventListener('load', () => {
        try { liveFrame.contentWindow.scrollTo({ top: 0, behavior: 'instant' }); } catch { /* */ }
        couple(liveFrame, iframe); if (iframe) couple(iframe, liveFrame);
      });
      liveFrame.src = data.live;
      winLive.append(liveFrame);
    }
  }

  // Blöcke mit data-vdiff (Admin\VersionsController, ?mark=1): farbiger Rahmen + Abzeichen „Neu“/„Geändert“ (CSSOM – CSP-tauglich)
  const COLORS = { new: '#1F8A4C', changed: '#C2410C' };
  function decorate(doc) {
    if (!doc || !mark) return;
    const els = [...doc.querySelectorAll('[data-vdiff]')];
    els.forEach(el => {
      const kind = el.dataset.vdiff === 'new' ? 'new' : 'changed', c = COLORS[kind];
      el.style.setProperty('outline', `3px solid ${c}`, 'important');
      el.style.setProperty('outline-offset', '-3px', 'important');
      if (doc.defaultView.getComputedStyle(el).position === 'static') el.style.position = 'relative';
      const b = doc.createElement('span');
      b.textContent = kind === 'new' ? L('markNew') : L('markChanged');
      b.setAttribute('aria-hidden', 'true');
      Object.assign(b.style, { position: 'absolute', top: '10px', left: '10px', zIndex: '60', padding: '3px 10px', borderRadius: '999px',
        background: c, color: '#fff', font: '700 12px/1.3 system-ui,-apple-system,sans-serif', boxShadow: '0 4px 12px rgba(0,0,0,.25)', pointerEvents: 'none' });
      el.prepend(b);
    });
    els[0]?.scrollIntoView({ block: 'start', behavior: 'instant' });
  }
  // Zeile über der Vorschau: was sich gegenüber dem vorherigen Stand geändert hat
  function diffLine(v) {
    const el = vs.querySelector('[data-diff]'), d = v.diff || {};
    if (!mark) { el.hidden = true; return; }
    let t;
    if (d.first) t = L('firstState');
    else if (isEntry) t = v.changes?.length ? L('diffHint') + ' ' + L('changes', { list: v.changes.join(', ') }) : L('noDiff');
    else {
      const parts = [d.changed ? L('blocksChanged', { n: d.changed }) : '', d.new ? L('blocksNew', { n: d.new }) : '', d.removed?.length ? L('blocksRemoved', { list: d.removed.join(', ') }) : ''].filter(Boolean);
      t = parts.length ? L('diffHint') + ' ' + parts.join(' · ') : L('noDiff');
    }
    el.textContent = t; el.hidden = false;
  }

  // Uhr: Winkel fortlaufend (nicht modulo), damit die Zeiger zurück- bzw. vorwärts laufen – älter = rückwärts, mit Extrarunden je Abstand
  const hands = { h: vs.querySelector('[data-hand="h"]'), m: vs.querySelector('[data-hand="m"]') };
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let clockTs = Math.floor(Date.now() / 1000), angH = 0, angM = 0;
  { const n = new Date(); angH = (n.getHours() % 12) * 30 + n.getMinutes() * 0.5; angM = n.getMinutes() * 6; }
  const setHands = () => {
    hands.h.style.transform = `rotate(${angH}deg)`;
    hands.m.style.transform = `rotate(${angM}deg)`;
  };
  setHands();
  const turnTo = v => {
    if (!v?.hm) return;
    const [hh, mm] = v.hm, dir = (v.ts ?? clockTs) < clockTs ? -1 : 1;
    const days = Math.abs((v.ts ?? clockTs) - clockTs) / 86400;
    const extra = reduce ? 0 : Math.min(3, Math.floor(days));   // Extrarunden des Minutenzeigers
    const step = (cur, target, full) => {
      let d = ((target - cur) % full + full) % full;   // 0 … full vorwärts
      if (dir < 0 && d) d -= full;                       // rückwärts
      return cur + d + dir * extra * full;
    };
    angM = step(angM, mm * 6, 360);
    angH = step(angH, (hh % 12) * 30 + mm * 0.5, 360) ;
    // Kalenderblatt: blättert um, wenn sich der Tag ändert (älter = nach unten weg, neuer = nach oben)
    const cal = vs.querySelector('.vs-cal'), key = v.dm ? v.dm.join(' ') : '';
    if (cal && v.dm && cal.dataset.key !== key) {
      const fill = () => { vs.querySelector('[data-cal-d]').textContent = v.dm[0]; vs.querySelector('[data-cal-m]').textContent = v.dm[1]; cal.dataset.key = key; };
      if (!cal.dataset.key || reduce) fill();
      else {
        cal.classList.remove('is-flip-back', 'is-flip-fwd'); void cal.offsetWidth;
        cal.classList.add(dir < 0 ? 'is-flip-back' : 'is-flip-fwd');
        setTimeout(fill, 180);
      }
    }
    clockTs = v.ts ?? clockTs;
    setHands();
  };

  const select = (i, focus = false) => {
    i = Math.max(0, Math.min(V.length - 1, i));
    cur = i;
    const v = V[i];
    cards.forEach((c, k) => { c.setAttribute('aria-selected', String(k === i)); c.tabIndex = k === i ? 0 : -1; });
    cards[i].scrollIntoView({ block: 'nearest', inline: 'nearest' });
    if (focus) cards[i].focus({ preventScroll: true });
    vs.querySelector('[data-pt]').textContent = v.now ? L('now') + ' · ' + v.at : v.at;
    vs.querySelector('[data-ps]').textContent = [v.note, v.user].filter(Boolean).join(' · ');
    vs.querySelector('[data-older]').disabled = i >= V.length - 1;
    vs.querySelector('[data-newer]').disabled = i <= 0;
    restoreBtn.disabled = !!v.now || !data.can_restore;
    restoreBtn.title = v.now ? L('isNow') : '';
    ask(false); msg('');
    if (isEntry) renderEntry(v); else renderPage(v);
    turnTo(v);
    diffLine(v);
    renderCompare();
  };

  // Rückfrage und Wiederherstellen
  function ask(on) {
    vs.querySelector('[data-confirm]').hidden = !on;
    if (on) vs.querySelector('[data-yes]').focus();
  }
  function msg(m) { const el = vs.querySelector('[data-msg]'); el.textContent = m || ''; el.hidden = !m; }
  restoreBtn.addEventListener('click', () => { if (!restoreBtn.disabled) ask(true); });
  vs.querySelector('[data-no]').addEventListener('click', () => { ask(false); restoreBtn.focus(); });
  vs.querySelector('[data-yes]').addEventListener('click', async () => {
    if (busy) return;
    busy = true; msg('');
    const yes = vs.querySelector('[data-yes]');
    yes.disabled = true; yes.textContent = L('restoring');
    try {
      const r = await fetch(data.restore.replace('{rev}', V[cur].id), { method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: '{}' });
      let res = {};
      try { res = await r.json(); } catch { res = {}; }
      if (!r.ok || res.ok === false) throw new Error(res.error || L('failed'));
      window.CMSAdmin?.toastNext?.(res.message || L('done'));
      const to = res.url ? new URL(res.url, location.href) : null;
      if (to && to.href !== location.href) location.href = to.href; else location.reload();
    } catch (e) {
      busy = false; yes.disabled = false; yes.textContent = L('yes');
      msg(e.message);
    }
  });
  vs.querySelector('[data-older]').addEventListener('click', () => select(cur + 1));
  vs.querySelector('[data-newer]').addEventListener('click', () => select(cur - 1));
  vs.querySelector('[data-tl]').addEventListener('click', e => { const c = e.target.closest('[data-i]'); if (c) select(+c.dataset.i, true); });
  vs.querySelector('[data-only]')?.addEventListener('change', e => win.classList.toggle('vs-onlychg', e.target.checked));
  vs.querySelector('[data-cmp]').addEventListener('change', e => {
    cmp = e.target.checked;
    try { localStorage.setItem('cms-versions-cmp', cmp ? '1' : '0'); } catch { /* */ }
    renderCompare();
  });
  vs.querySelector('[data-mark]').addEventListener('change', e => {
    mark = e.target.checked;
    try { localStorage.setItem('cms-versions-mark', mark ? '1' : '0'); } catch { /* */ }
    select(cur);   // neu zeichnen (Seiten: Vorschau mit bzw. ohne Markierung)
  });
  vs.querySelectorAll('[data-dev]').forEach(b => b.addEventListener('click', () => {
    mobile = b.dataset.dev === 'mobile';
    vs.querySelectorAll('[data-dev]').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    frame.classList.toggle('is-mobile', mobile);
  }));

  function onKey(e) {
    if (!host.isConnected) return;
    if (e.key === 'Escape') {
      e.preventDefault(); e.stopPropagation();
      if (vs.querySelector('[data-confirm]') && !vs.querySelector('[data-confirm]').hidden) ask(false); else close();
      return;
    }
    if (!cards.length) return;
    const inList = root.activeElement?.closest?.('[data-tl]');
    if (inList && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) { e.preventDefault(); select(cur + (e.key === 'ArrowDown' ? 1 : -1), true); return; }
    if (inList && e.key === 'Home') { e.preventDefault(); select(0, true); return; }
    if (inList && e.key === 'End') { e.preventDefault(); select(V.length - 1, true); return; }
    if (e.key === 'Tab') {   // Fokus bleibt im Dialog
      const f = [...root.querySelectorAll('button:not([disabled]):not([tabindex="-1"]),input,iframe')].filter(x => x.getClientRects().length);
      if (!f.length) return;
      const a = root.activeElement;
      if (e.shiftKey && (a === f[0] || !a)) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && a === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  }

  select(cur, true);
}

export default { open };
