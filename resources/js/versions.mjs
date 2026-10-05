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
  hist: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v4h4"/><path d="M12 7v5l3 2"/></svg>',
  down: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
  up: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>',
  restore: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>',
};

export async function open({ endpoint, csrf = '' } = {}) {
  if (!endpoint || document.getElementById('cms-versions')) return;
  const opener = document.activeElement;
  const host = document.createElement('div');
  host.id = 'cms-versions';
  const root = host.attachShadow({ mode: 'open' });
  const css = document.createElement('link');
  css.rel = 'stylesheet';
  css.href = new URL('../css/versions.css', import.meta.url).href;
  root.append(css);
  const vs = document.createElement('div');
  vs.className = 'vs';
  vs.setAttribute('role', 'dialog');
  vs.setAttribute('aria-modal', 'true');
  vs.setAttribute('aria-labelledby', 'vs-t');
  vs.innerHTML = `<div class="vs__box"><div class="vs-head"><span class="vs-head__ico">${SVG.hist}</span><div><p class="vs-head__t" id="vs-t">…</p><p class="vs-head__s" data-sub></p></div><button type="button" class="vs-x" data-close>×</button></div><div class="vs-load" data-wait>…</div></div>`;
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
    const chips = isEntry && v.changes?.length
      ? `<span class="vs-chips" aria-label="${esc(L('changes', { list: v.changes.join(', ') }))}">${v.changes.slice(0, 4).map(c => `<span class="vs-chip">${esc(c)}</span>`).join('')}${v.changes.length > 4 ? `<span class="vs-chip">+${v.changes.length - 4}</span>` : ''}</span>`
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
      <div class="vs-frame" data-frame><div class="vs-win" data-win></div><p class="vs-load" data-load hidden>${esc(L('loading'))}</p></div>
    </section>`);

  const cards = [...vs.querySelectorAll('.vs-card')];
  const win = vs.querySelector('[data-win]'), frame = vs.querySelector('[data-frame]'), load = vs.querySelector('[data-load]');
  const restoreBtn = vs.querySelector('[data-restore]');
  let iframe = null;

  const renderEntry = v => {
    const status = v.status === 'draft' ? `<span class="vs-status vs-status--draft">${esc(L('draft'))}</span>` : v.status ? `<span class="vs-status">${esc(L('published'))}</span>` : '';
    win.innerHTML = `<div class="vs-scroll"><div class="vs-entry"><h2 class="vs-entry__t">${esc(v.title || data.title)}</h2>
      <p class="vs-entry__m">${status}${esc(v.at)}${v.user ? ' · ' + esc(v.user) : ''}</p>
      <dl class="vs-fields">${(v.fields || []).map(f => `<div class="vs-f${f.changed ? ' is-changed' : ''}${f.empty ? ' is-empty' : ''}"><dt data-changed="${esc(L('changed'))}">${esc(f.label)}</dt><dd>${f.empty ? esc(L('empty')) : f.html}</dd></div>`).join('') || `<p>${esc(L('noFields'))}</p>`}</dl></div></div>`;
  };
  const renderPage = v => {
    if (!iframe) {
      iframe = document.createElement('iframe');
      // Vorschau immer oben beginnen (Kits mit sanftem Scrollen/Ankern würden sonst mitten auf der Seite starten)
      iframe.addEventListener('load', () => { load.hidden = true; try { iframe.contentWindow.scrollTo({ top: 0, behavior: 'instant' }); } catch { /* */ } });
      win.append(iframe);
    }
    iframe.title = (v.now ? L('now') : v.at) + ' – ' + data.title;
    load.hidden = false;
    iframe.src = v.preview;
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
