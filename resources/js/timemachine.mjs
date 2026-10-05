/*
 * Time Machine – Versionen von Seiten und Einträgen ansehen und wiederherstellen (Admin\VersionsController), wie bei Apple:
 * die Stände liegen als Fenster hintereinander im Raum (vorne der gewählte, dahinter die älteren), rechts die Zeitleiste
 * (unten „Jetzt“, nach oben älter). Geladen erst beim Öffnen (admin.js: [data-timemachine]).
 *
 *   open({ endpoint, csrf })   endpoint = GET /admin/api/pages/{id}/versions bzw. /admin/api/data/{handle}/{id}/versions
 *
 * Seiten: vorne die echte Seite in diesem Stand (Iframe, …/versions/{rev}/vorschau). Einträge: Titel, Status und Felder wie auf
 * der Website; geänderte Felder (gegenüber jetzt) sind markiert, „Nur Änderungen“ blendet den Rest aus.
 * Bedienung: ↑/↓ bzw. Bild↑/Bild↓ oder Mausrad (älter/neuer), Pos1 = Jetzt, Ende = ältester Stand, Klick auf ein Fenster
 * dahinter oder einen Strich der Zeitleiste, Esc schließt. „Wiederherstellen“ mit Rückfrage: Seiten als Entwurf (öffnet im
 * Editor), Einträge sofort (Status bleibt) – beides legt selbst wieder eine Version an.
 * Eigene Shadow-DOM-Wurzel mit css/timemachine.css; „Bewegung reduzieren“: nur Überblenden.
 */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const fill = (s, p = {}) => Object.entries(p).reduce((x, [k, v]) => x.replaceAll('{' + k + '}', String(v)), String(s ?? ''));
const SVG = {
  clock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v4h4"/><path d="M12 7v5l3 2"/></svg>',
  up: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>',
  down: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>',
};
const DEPTH = 5;   // so viele Fenster sind hinter dem gewählten zu sehen
const STEP = 22;   // px je Stufe nach oben (Platz dafür reserviert --tm-stack)

export async function open({ endpoint, csrf = '' } = {}) {
  if (!endpoint || document.getElementById('cms-timemachine')) return;
  const opener = document.activeElement;
  const host = document.createElement('div');
  host.id = 'cms-timemachine';
  const root = host.attachShadow({ mode: 'open' });
  const css = document.createElement('link');
  css.rel = 'stylesheet';
  css.href = new URL('../css/timemachine.css', import.meta.url).href;
  root.append(css);
  const tm = document.createElement('div');
  tm.className = 'tm';
  tm.setAttribute('role', 'dialog');
  tm.setAttribute('aria-modal', 'true');
  tm.setAttribute('aria-labelledby', 'tm-t');
  tm.innerHTML = `<div class="tm-head"><span class="tm-head__ico">${SVG.clock}</span><div><p class="tm-head__t" id="tm-t">Time Machine</p><p class="tm-head__s" data-sub>…</p></div><button type="button" class="tm-x" data-close aria-label="Schließen">×</button></div>
    <div class="tm-stage" data-stage><p class="tm-load tm-load--dark">…</p></div>`;
  root.append(tm);
  document.body.append(host);
  const prevOverflow = document.documentElement.style.overflow;
  document.documentElement.style.overflow = 'hidden';
  const close = () => {
    document.removeEventListener('keydown', onKey, true);
    host.remove();
    document.documentElement.style.overflow = prevOverflow;
    opener?.focus?.({ preventScroll: true });
  };
  tm.querySelector('[data-close]').addEventListener('click', close);

  let data;
  try {
    const r = await fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    data = await r.json();
    if (!r.ok || !data.ok) throw new Error(data.error || r.statusText);
  } catch (e) {
    tm.querySelector('[data-stage]').innerHTML = `<p class="tm-load tm-load--dark">${esc(e.message || 'Fehler')}</p>`;
    tm.querySelector('[data-close]').focus();
    document.addEventListener('keydown', onKey, true);
    return;
  }
  const T = data.texts || {};
  const L = (k, p) => fill(T[k] ?? k, p);   // Texte vom Server (VersionsController::texts)
  const V = data.versions || [];
  const isEntry = data.kind === 'entry';
  let cur = 0, busy = false;

  tm.querySelector('[data-sub]').textContent = (data.table ? data.table + ' · ' : '') + data.title + ' · ' + (V.length - 1 === 1 ? L('count1') : L('count', { n: V.length - 1 }));
  tm.querySelector('[data-close]').setAttribute('aria-label', L('close'));
  tm.insertAdjacentHTML('beforeend', `
    <div class="tm-tl"><div class="tm-nav"><button type="button" class="tm-arrow" data-older aria-label="${esc(L('older'))}" title="${esc(L('older'))} (↑)">${SVG.up}</button><button type="button" class="tm-arrow" data-newer aria-label="${esc(L('newer'))}" title="${esc(L('newer'))} (↓)">${SVG.down}</button></div><ol class="tm-tl__list" role="listbox" aria-label="${esc(L('timeline'))}" data-tl>
      ${V.map((v, i) => `<li role="presentation"><button type="button" role="option" class="tm-tick${v.now ? ' is-now' : ''}" data-i="${i}" aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}" title="${esc(v.at)}"><span>${esc(v.now ? L('now') : v.label)}</span></button></li>`).join('')}
    </ol></div>
    <div class="tm-foot">
      ${isEntry ? `<label class="tm-only-l"><input type="checkbox" data-only> ${esc(L('onlyChanges'))}</label>` : ''}
      <div class="tm-when" aria-live="polite"><b data-when-at></b><small data-when-note></small></div>
      <div class="tm-confirm" data-ask-row>
        <button type="button" class="tm-btn" data-cancel>${esc(L('cancel'))}</button>
        <button type="button" class="tm-btn tm-btn--primary" data-restore>${esc(L('restore'))}</button>
      </div>
      <div class="tm-confirm" data-confirm hidden>
        <span>${esc(isEntry ? L('askEntry') : L('askPage'))}</span>
        <button type="button" class="tm-btn" data-no>${esc(L('back'))}</button>
        <button type="button" class="tm-btn tm-btn--primary" data-yes>${esc(L('yes'))}</button>
      </div>
      <p class="tm-msg" data-msg role="alert" hidden></p>
    </div>`);
  const stage = tm.querySelector('[data-stage]');
  stage.innerHTML = '';

  // Fenster je Stand; Inhalt erst, wenn es nach vorne kommt (Seiten: ein Iframe je besuchtem Stand)
  const wins = V.map((v, i) => {
    const w = document.createElement('section');
    w.className = 'tm-win';
    w.dataset.i = i;
    w.setAttribute('aria-label', (v.now ? L('now') : v.at) + (v.note ? ' – ' + v.note : ''));
    w.innerHTML = `<div class="tm-win__bar"><span class="tm-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="tm-win__t">${esc(v.now ? L('now') + ' · ' + v.at : v.at)}${v.note ? ' · ' + esc(v.note) : ''}</span><span class="tm-dots tm-dots--ghost" aria-hidden="true"><i></i><i></i><i></i></span></div><div class="tm-win__body"></div>`;
    w.addEventListener('click', () => { if (+w.dataset.i !== cur) go(+w.dataset.i); });
    stage.append(w);
    return w;
  });
  const fillWin = i => {
    const w = wins[i], v = V[i], body = w.querySelector('.tm-win__body');
    if (w.dataset.filled) return;
    w.dataset.filled = '1';
    if (!isEntry) {
      body.innerHTML = `<p class="tm-load">${esc(L('loading'))}</p>`;
      const f = document.createElement('iframe');
      f.title = (v.now ? L('now') : v.at) + ' – ' + data.title;
      f.src = v.preview;
      f.addEventListener('load', () => body.querySelector('.tm-load')?.remove());
      body.append(f);
      return;
    }
    const status = v.status === 'draft' ? `<span class="tm-chip tm-chip--draft">${esc(L('draft'))}</span>` : v.status ? `<span class="tm-chip">${esc(L('published'))}</span>` : '';
    body.innerHTML = `<div class="tm-entry"><h2 class="tm-entry__t">${esc(v.title || data.title)}</h2>
      <p class="tm-entry__m">${status}${esc(v.at)}${v.user ? ' · ' + esc(v.user) : ''}</p>
      <dl class="tm-fields">${(v.fields || []).map(f => `<div class="tm-f${f.changed ? ' is-changed' : ''}${f.empty ? ' is-empty' : ''}" data-changed="${esc(L('changed'))}"><dt>${esc(f.label)}</dt><dd>${f.empty ? esc(L('empty')) : f.html}</dd></div>`).join('') || `<p>${esc(L('noFields'))}</p>`}</dl></div>`;
  };

  const ticks = [...tm.querySelectorAll('.tm-tick')];
  const restoreBtn = tm.querySelector('[data-restore]');
  const layout = () => {
    wins.forEach((w, i) => {
      const k = i - cur;
      w.classList.toggle('is-front', k === 0);
      if (k < 0) {   // neuer als der gewählte Stand: nach vorne aus dem Bild
        w.style.transform = `translate3d(0, 8%, 260px) scale(1.04)`;
        w.style.opacity = '0';
        w.style.pointerEvents = 'none';
        w.style.zIndex = String(200 + k);
        w.inert = true;
        return;
      }
      w.inert = k !== 0;
      w.style.pointerEvents = k > DEPTH ? 'none' : '';
      w.style.zIndex = String(100 - k);
      w.style.opacity = k > DEPTH ? '0' : '1';   // deckend – Tiefe nur über Helligkeit, Unschärfe und Größe
      w.style.filter = k ? `brightness(${Math.max(0.35, 0.86 - k * 0.1)}) blur(${(k * 0.3).toFixed(2)}px)` : '';
      w.style.transform = `translate3d(0, ${-k * STEP}px, ${-k * 160}px)`;
    });
    // Platz oben für die Fenster dahinter (bei wenigen Ständen rückt das vordere Fenster nach oben)
    tm.style.setProperty('--tm-stack', Math.min(V.length - 1 - cur, DEPTH) * STEP + 'px');
    fillWin(cur);
    ticks.forEach((b, i) => { b.setAttribute('aria-selected', String(i === cur)); b.tabIndex = i === cur ? 0 : -1; });
    ticks[cur]?.scrollIntoView({ block: 'nearest' });
    const v = V[cur];
    tm.querySelector('[data-when-at]').textContent = v.now ? L('now') + ' – ' + v.at : v.at;
    tm.querySelector('[data-when-note]').textContent = [v.note, v.user].filter(Boolean).join(' · ');
    tm.querySelector('[data-older]').disabled = cur >= V.length - 1;
    tm.querySelector('[data-newer]').disabled = cur <= 0;
    restoreBtn.disabled = v.now || !data.can_restore;
    restoreBtn.title = v.now ? L('isNow') : '';
    ask(false);
  };
  const go = i => { i = Math.max(0, Math.min(V.length - 1, i)); if (i === cur) return; cur = i; layout(); };

  // Rückfrage und Wiederherstellen
  const ask = on => {
    tm.querySelector('[data-confirm]').hidden = !on;
    tm.querySelector('[data-ask-row]').hidden = on;
    if (on) tm.querySelector('[data-yes]').focus();
  };
  const msg = m => { const el = tm.querySelector('[data-msg]'); el.textContent = m || ''; el.hidden = !m; };
  restoreBtn.addEventListener('click', () => { if (!restoreBtn.disabled) ask(true); });
  tm.querySelector('[data-no]').addEventListener('click', () => { ask(false); restoreBtn.focus(); });
  tm.querySelector('[data-cancel]').addEventListener('click', close);
  tm.querySelector('[data-yes]').addEventListener('click', async () => {
    if (busy) return;
    busy = true; msg('');
    const yes = tm.querySelector('[data-yes]');
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
  tm.querySelector('[data-older]').addEventListener('click', () => go(cur + 1));
  tm.querySelector('[data-newer]').addEventListener('click', () => go(cur - 1));
  tm.querySelector('[data-tl]').addEventListener('click', e => { const b = e.target.closest('[data-i]'); if (b) go(+b.dataset.i); });
  tm.querySelector('[data-only]')?.addEventListener('change', e => tm.classList.toggle('tm-only', e.target.checked));

  // Mausrad: eine Stufe je Geste (gesammelt), nicht über dem Inhalt des vorderen Fensters (dort scrollt die Seite)
  let acc = 0, wheelT = 0;
  stage.addEventListener('wheel', e => {
    if (e.target.closest?.('.tm-win.is-front .tm-win__body')) return;
    e.preventDefault();
    acc += e.deltaY;
    clearTimeout(wheelT);
    wheelT = setTimeout(() => { acc = 0; }, 180);
    if (Math.abs(acc) > 60) { go(cur + (acc < 0 ? 1 : -1)); acc = 0; }
  }, { passive: false });

  function onKey(e) {
    if (!host.isConnected) return;
    const inFrame = e.target === host && root.activeElement?.tagName === 'IFRAME';
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); if (!tm.querySelector('[data-confirm]')?.hidden) ask(false); else close(); return; }
    if (inFrame) return;
    const map = { ArrowUp: 1, PageUp: 1, ArrowDown: -1, PageDown: -1 };
    if (e.key in map) { e.preventDefault(); e.stopPropagation(); go(cur + map[e.key]); ticks[cur]?.focus(); return; }
    if (e.key === 'Home') { e.preventDefault(); go(0); ticks[cur]?.focus(); return; }
    if (e.key === 'End') { e.preventDefault(); go(V.length - 1); ticks[cur]?.focus(); return; }
    if (e.key === 'Tab') {   // Fokus bleibt in der Time Machine
      const f = [...root.querySelectorAll('button:not([disabled]),input,iframe,[tabindex="0"]')].filter(x => x.getClientRects().length && !x.closest('[inert]'));
      if (!f.length) return;
      const a = root.activeElement;
      if (e.shiftKey && (a === f[0] || !a)) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && a === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
    }
  }
  document.addEventListener('keydown', onKey, true);

  layout();
  // Gleich den letzten gespeicherten Stand vorschlagen, wenn es einen gibt (sonst bliebe man bei „Jetzt“)
  if (V.length > 1) go(1);
  // Fokus auf die Zeitleiste (Pfeiltasten wählen den Stand) – ohne sichtbaren Ring beim Öffnen per Maus
  ticks[cur]?.focus({ preventScroll: true, focusVisible: false });
}

export default { open };
