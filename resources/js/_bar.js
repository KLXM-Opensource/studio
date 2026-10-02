/*
 * Redaktions-Werkzeugleiste der Website (Core\Toolbar, app/Views/toolbar.php) – Teil von admin.js.
 *
 *  - Menüs („⋯“, Veröffentlichen ▾, Vorschau mit …): Menü-Knopf mit Pfeiltasten, Pos1/Ende, Esc, Tab schließt.
 *  - Status-Chip mit Erklärung (Klick/Enter), EIN Zustand: Veröffentlicht | Entwurf | Geändert – nicht veröffentlicht |
 *    Ungespeichert | Live-Fassung. Speichern-Knopf zeigt „Gespeichert ✓“, Live-Region meldet den Stand.
 *  - Modus „Ansehen · Bearbeiten · Vorlage“ und „Abbrechen“: Wer bearbeitet (Seiten-Editor, Eintrag direkt im Text),
 *    meldet sich mit bar.use({ dirty, save, discard, exit, start }) an. Abbrechen ohne Änderungen verlässt den Modus sofort,
 *    sonst fragt ein Dialog in der Shadow-DOM-Ebene: Weiter bearbeiten | Speichern & beenden | Verwerfen.
 *    Esc = Abbrechen, solange kein Dialog, Menü, keine Seitenleiste und kein Eingabefeld den Fokus hat.
 */
import { barRoot, layerBox, openDialog, deepActive, uiAll } from './_shadow.js';
import { t } from './_i18n.js';

const d = document;
let R = null, bar = null, cfg = { texts: {} }, H = null, mode = 'view', openMenu = null;
const T = k => cfg.texts?.[k] ?? k;
const fill = (s, p = {}) => Object.entries(p).reduce((x, [k, v]) => x.replaceAll('{' + k + '}', String(v)), String(s ?? ''));
const visible = el => !!el && el.getClientRects().length > 0;

// ------------------------------------------------------------------ Menüs
function items(menu) { return [...menu.querySelectorAll('[role^=menuitem]')].filter(visible); }
function btnFor(menu) { return R.querySelector(`[aria-controls="${menu.id}"]`); }

function open(btn, focus = 'first') {
  const menu = R.getElementById(btn.getAttribute('aria-controls'));
  if (!menu) return;
  if (openMenu && openMenu !== menu) close(openMenu, false);
  closePop(false);
  menu.hidden = false;
  btn.setAttribute('aria-expanded', 'true');
  openMenu = menu;
  // Nach oben aufklappen, wenn unten kein Platz ist (Aktionsleiste unten auf Telefonen)
  const r = btn.getBoundingClientRect();
  menu.classList.toggle('is-up', r.top > innerHeight / 2);
  const list = items(menu);
  const cur = list.find(i => i.getAttribute('aria-checked') === 'true');
  (focus === 'last' ? list.at(-1) : (cur && focus === 'current' ? cur : list[0]))?.focus();
}
function close(menu = openMenu, refocus = true) {
  if (!menu) return;
  menu.hidden = true;
  const btn = btnFor(menu);
  btn?.setAttribute('aria-expanded', 'false');
  if (openMenu === menu) openMenu = null;
  if (refocus) btn?.focus();
}

function initMenus() {
  R.querySelectorAll('[aria-haspopup=menu][aria-controls]').forEach(btn => {
    btn.addEventListener('click', () => (btn.getAttribute('aria-expanded') === 'true' ? close() : open(btn, 'current')));
    btn.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(btn, e.key === 'ArrowUp' ? 'last' : 'first'); }
    });
  });
  R.querySelectorAll('[role=menu]').forEach(menu => {
    let buf = '', bufT = 0;
    menu.addEventListener('keydown', e => {
      const list = items(menu), i = list.indexOf(R.activeElement);
      const go = n => { e.preventDefault(); list[(n + list.length) % list.length]?.focus(); };
      if (e.key === 'ArrowDown') go(i + 1);
      else if (e.key === 'ArrowUp') go(i - 1);
      else if (e.key === 'Home') go(0);
      else if (e.key === 'End') go(list.length - 1);
      else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(menu); }
      else if (e.key === 'Tab') close(menu, false);
      else if ((e.key === ' ' || e.key === 'Enter') && R.activeElement?.tagName === 'A') { if (e.key === ' ') { e.preventDefault(); R.activeElement.click(); } }
      else if (e.key.length === 1 && /\S/.test(e.key) && !e.metaKey && !e.ctrlKey) {
        // Tippen springt zum ersten passenden Eintrag
        clearTimeout(bufT); buf += e.key.toLowerCase(); bufT = setTimeout(() => { buf = ''; }, 600);
        const hit = list.find(x => x.textContent.trim().toLowerCase().startsWith(buf));
        if (hit) { e.preventDefault(); hit.focus(); }
      }
    });
    menu.addEventListener('click', e => {
      const it = e.target.closest('[role^=menuitem]');
      if (!it || it.getAttribute('role') === 'menuitemcheckbox') return;
      close(menu, false);
      // Fokus zurück zum Menü-Knopf, damit er nach Dialogen/Seitenleisten wieder dort landet
      if (it.tagName !== 'A') btnFor(menu)?.focus({ preventScroll: true });
    });
    menu.addEventListener('focusout', e => { if (openMenu === menu && !menu.contains(e.relatedTarget) && e.relatedTarget !== btnFor(menu)) setTimeout(() => { if (!menu.contains(R.activeElement)) close(menu, false); }); });
  });
  d.addEventListener('pointerdown', e => {
    const path = e.composedPath();
    if (openMenu && !path.includes(openMenu) && !path.includes(btnFor(openMenu))) close(openMenu, false);
    const pop = R.getElementById('cms-chip-pop'), chip = R.querySelector('[data-bar-chip]');
    if (pop && !pop.hidden && !path.includes(pop) && !path.includes(chip)) closePop(false);
  });
}

// ------------------------------------------------------------------ Status-Chip mit Erklärung
function closePop(refocus = true) {
  const pop = R.getElementById('cms-chip-pop'), chip = R.querySelector('[data-bar-chip]');
  if (!pop || pop.hidden) return;
  pop.hidden = true; chip.setAttribute('aria-expanded', 'false');
  if (refocus) chip.focus();
}
function initChip() {
  const chip = R.querySelector('[data-bar-chip]'), pop = R.getElementById('cms-chip-pop');
  if (!chip || !pop) return;
  chip.addEventListener('click', () => {
    if (!pop.hidden) { closePop(false); return; }
    if (openMenu) close(openMenu, false);
    pop.hidden = false; chip.setAttribute('aria-expanded', 'true');
  });
  d.addEventListener('keydown', e => { if (e.key === 'Escape' && !pop.hidden) { e.preventDefault(); closePop(R.activeElement === chip); } }, true);
}
function setChip(state) {
  const chip = R?.querySelector('[data-bar-chip]');
  const c = cfg.texts.chip?.[state];
  if (!chip || !c) return;
  chip.dataset.state = state;
  chip.querySelector('.cms-chip__t').textContent = c[0];
  chip.setAttribute('aria-label', fill(T('status'), { status: c[0] }));
  const pop = R.getElementById('cms-chip-pop');
  pop.querySelector('[data-bar-chip-title]').textContent = c[0];
  pop.querySelector('[data-bar-chip-text]').textContent = c[1];
  toggleButtons(state);
}

// ------------------------------------------------------------------ Online/Offline umschalten (Status-Chip, Core\Toolbar → config.toggle)
/**
 * „Offline nehmen“ bei online (auch mit offenem Entwurf), „Online stellen“ bei offline ohne offenen Entwurf.
 * Ungespeichert, Entwurf (nie veröffentlicht) und offline mit offenen Änderungen: kein Knopf – dort gilt „Veröffentlichen“.
 */
function toggleButtons(state) {
  const tg = cfg.toggle, pop = R?.getElementById('cms-chip-pop');
  if (!tg || !pop) return;
  const off = pop.querySelector('[data-bar-offline]'), on = pop.querySelector('[data-bar-online]'), hint = pop.querySelector('[data-bar-pending]');
  if (off) off.hidden = !(state === 'published' || state === 'changed');
  if (on) on.hidden = !(state === 'offline' && !tg.pending);
  if (hint) hint.hidden = !(state === 'offline' && tg.pending && cfg.kind === 'page');
}
/**
 * Eintrag online/offline ohne Neuladen: Knöpfe im Bearbeiten-Modus (Entwurf: Speichern + Veröffentlichen, online: Speichern
 * sofort sichtbar), „Als Entwurf speichern“ im Menü, gelber Hinweis „Entwurf …“ unter der Leiste; _entry_edit.js hört auf
 * cms:entry-status (Rückfrage „Als Entwurf?“ der Seitenleiste).
 */
function entryStatus(online) {
  R.querySelectorAll('[data-entry-when]').forEach(x => {
    const want = x.dataset.entryWhen === (online ? 'published' : 'draft');
    if (x.hasAttribute('data-bar-when')) { x.dataset.barWhen = want ? 'edit' : 'never'; x.hidden = !want || mode === 'view'; }
    else x.hidden = !want;
  });
  R.querySelectorAll('[data-entry-save]').forEach(b => {
    b.classList.toggle('cms-btn--primary', online);
    if (online && b.dataset.titleOnline) b.title = b.dataset.titleOnline; else b.removeAttribute('title');
  });
  const note = d.querySelector('[data-entry-note]');
  if (note) {
    const draft = note.querySelector('[data-entry-note-draft]');
    if (draft) draft.hidden = online;
    note.classList.toggle('cms-entry-note--draft', !online);
    note.hidden = online && !note.querySelector('[data-entry-note-other]');
  }
  d.dispatchEvent(new CustomEvent('cms:entry-status', { detail: { status: online ? 'published' : 'draft' } }));
}
function initToggle() {
  const tg = cfg.toggle, pop = R.getElementById('cms-chip-pop');
  if (!tg || !pop) return;
  const live = R.querySelector('[data-editor-status],[data-entry-status]'), err = pop.querySelector('[data-bar-toggle-err]');
  const say = m => { if (live) { live.textContent = ''; setTimeout(() => { live.textContent = m; }, 30); } };
  const send = async online => {
    const url = online ? tg.on : tg.off;
    const body = tg.id ? { action: online ? 'publish' : 'draft', ids: [tg.id] } : {};
    const r = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': tg.csrf }, body: JSON.stringify(body) });
    let res = {};
    try { res = await r.json(); } catch { res = { ok: false, error: T('error') }; }
    if (!r.ok || !res.ok) throw new Error(res.error || T('error'));
    return res;
  };
  const run = async (btn, online) => {
    if (btn.getAttribute('aria-busy') === 'true') return;
    if (!online && !(await ask({ title: T('offlineTitle'), body: T('offlineBody'), ok: T('offlineOk'), danger: true }))) return;
    if (err) err.hidden = true;
    btn.setAttribute('aria-busy', 'true');
    try {
      await send(online);
      cfg.status = online ? 'published' : 'offline';
      // Seite mit offenem Entwurf offline genommen: Chip „Offline“, „Online stellen“ erst nach dem Veröffentlichen
      setChip(H?.dirty?.() ? 'unsaved' : cfg.status);
      if (cfg.kind === 'entry') entryStatus(online);
      say(T(online ? 'onlineDone' : 'offlineDone'));
      // Ereignis für Werkzeuge/Erweiterungen (Technik → Erweiterungen): Online/Offline über den Status-Chip
      d.dispatchEvent(new CustomEvent('cms:status-changed', { detail: { kind: cfg.kind, status: online ? 'published' : 'offline', via: 'toggle', id: tg.id || null } }));
      const next = pop.querySelector(online ? '[data-bar-offline]' : '[data-bar-online]');
      (next && !next.hidden ? next : R.querySelector('[data-bar-chip]'))?.focus();
    } catch (e) {
      // z. B. Platzhalter-Sperre beim Veröffentlichen: Meldung im Erklärfeld (role=alert), Status bleibt
      if (err) { err.textContent = e.message; err.hidden = false; }
    } finally {
      btn.removeAttribute('aria-busy');
    }
  };
  pop.querySelector('[data-bar-offline]')?.addEventListener('click', e => run(e.currentTarget, false));
  pop.querySelector('[data-bar-online]')?.addEventListener('click', e => run(e.currentTarget, true));
  toggleButtons(cfg.status);
}

// ------------------------------------------------------------------ Zustand: dirty | saving | publishing | saved | published | error | clean
/**
 * Zustand melden (Seiten-Editor, Eintrag). msg = Text für die Live-Region (sonst Standardtext).
 * Ältere Theme-Leisten ohne Chip: nur die Statuszeile [data-editor-status]/[data-entry-status] wird beschrieben.
 */
export function barState(s, msg = '') {
  const cls = { dirty: 'is-dirty', saving: 'is-busy', publishing: 'is-busy', saved: 'is-ok', published: 'is-ok', error: 'is-error' }[s] || '';
  const text = msg || { dirty: T('dirty'), saving: T('saving'), publishing: T('publishing'), error: T('error') }[s] || '';
  uiAll('[data-editor-status],[data-entry-status]').forEach(st => {
    st.textContent = text;
    st.className = (st.classList.contains('cms-sr') ? 'cms-sr ' : 'cms-bar__status ') + cls;
  });
  if (s === 'published') uiAll('[data-dirty]').forEach(b => { b.hidden = true; });   // ältere Leisten
  if (!bar) return;
  // EIN Chip
  if (s === 'dirty' || (s === 'error' && H?.dirty?.())) setChip('unsaved');
  else if (s === 'published') { cfg.status = 'published'; if (cfg.toggle) cfg.toggle.pending = false; setChip('published'); }
  else if (s === 'saved') {
    // Seite offline mit gespeichertem Entwurf: „Online stellen“ entfällt, „Veröffentlichen“ bringt sie mit den Änderungen zurück
    if (cfg.toggle && cfg.kind === 'page') cfg.toggle.pending = true;
    setChip(cfg.kind === 'entry' || cfg.status === 'draft' || cfg.status === 'offline' ? cfg.status : 'changed');
  }
  else if (s === 'clean') setChip(cfg.status);
  // Speichern-Knopf: „Speichern“ · „Speichere …“ · „Gespeichert ✓“ (aria-disabled, solange nichts zu speichern ist)
  const done = s === 'saved' || s === 'published', open_ = s === 'dirty' || s === 'error';
  R.querySelectorAll('[data-bar-save]').forEach(b => {
    b.textContent = s === 'saving' ? T('saving') : done ? T('savedOk') : T('save');
    b.classList.toggle('is-saved', done);
    b.setAttribute('aria-disabled', open_ ? 'false' : 'true');
    if (s === 'saving' || s === 'publishing') b.setAttribute('aria-busy', 'true'); else b.removeAttribute('aria-busy');
  });
}

// ------------------------------------------------------------------ Bestätigung „Änderungen verwerfen?“ (Ebene, fokusgefangen)
let dlg = null;
/**
 * Fragt nach, bevor ungespeicherte Änderungen verloren gehen. Ergebnis: 'keep' | 'save' | 'discard'.
 * o: { title, body, note, keep, discard, save (Beschriftung oder leer = ohne „Speichern & beenden“) }
 */
export function confirmDiscard(o = {}) {
  const t = { title: T('discardTitle'), body: T('discardBody'), note: T('discardNote'), keep: T('keep'), discard: T('discard'), save: T('saveExit') };
  for (const [k, v] of Object.entries(o)) if (v !== undefined && v !== null) t[k] = v;
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  if (!dlg) {
    dlg = d.createElement('dialog');
    dlg.className = 'adm-dialog adm-dialog--small cms-confirm';
    dlg.setAttribute('aria-labelledby', 'cms-confirm-t');
    dlg.setAttribute('aria-describedby', 'cms-confirm-b');
    layerBox().append(dlg);
  }
  dlg.innerHTML = `<h2 id="cms-confirm-t">${esc(t.title)}</h2>
    <div id="cms-confirm-b"><p>${esc(t.body)}</p>${t.note ? `<p class="cms-confirm__note">${esc(t.note)}</p>` : ''}</div>
    <div class="cms-confirm__foot">
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--danger-text" data-r="discard">${esc(t.discard)}</button>
      <span class="cms-confirm__right">
        ${t.save ? `<button type="button" class="adm-btn" data-r="save">${esc(t.save)}</button>` : ''}
        <button type="button" class="adm-btn adm-btn--primary" data-r="keep" autofocus>${esc(t.keep)}</button>
      </span>
    </div>`;
  const before = deepActive();
  return new Promise(res => {
    let result = 'keep';
    dlg.onclick = e => { const b = e.target.closest('[data-r]'); if (b) { result = b.dataset.r; dlg.close(); } };
    dlg.onclose = () => { res(result); if (result === 'keep' && before?.isConnected) before.focus?.({ preventScroll: true }); };
    dlg.showModal();
    dlg.querySelector('[data-r=keep]').focus();
  });
}

// ------------------------------------------------------------------ Einfache Rückfrage statt window.confirm()
/**
 * Gestaltete Rückfrage (gleiche Ebene/Optik wie „Änderungen verwerfen?“) – Website (Shadow-DOM-Ebene) und Verwaltung.
 * ask('Titel?\nErklärung') oder ask({ title, body, ok, cancel, danger }). Ergebnis: Promise<boolean>.
 * Fokus liegt auf der sicheren Aktion (Abbrechen), Esc = Abbrechen, Fokus kehrt danach zum Auslöser zurück.
 */
export function ask(o = {}) {
  if (typeof o === 'string') o = { title: o };
  let title = String(o.title ?? ''), body = o.body ?? '';
  // Mehrzeilige Meldungen: erste Zeile = Frage, Rest = Erklärung
  if (!o.body && title.includes('\n')) { const i = title.indexOf('\n'); body = title.slice(i + 1).trim(); title = title.slice(0, i); }
  // Lange Ein-Satz-Meldungen: Frage bis zum ersten „?“ als Titel, Rest als Erklärung
  else if (!o.body && /\?\s+\S/.test(title)) { const i = title.indexOf('?') + 1; body = title.slice(i).trim(); title = title.slice(0, i); }
  const lab = k => cfg.texts?.[k] || t(k === 'cancel' ? 'Abbrechen' : 'Bestätigen');
  const danger = o.danger ?? /lösch|verwerf|widerruf|sperr|entfern|zurücksetz|delete|discard|revoke|remove/i.test(String(o.ok || '') + ' ' + title);
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  if (!dlg) {
    dlg = d.createElement('dialog');
    dlg.className = 'adm-dialog adm-dialog--small cms-confirm';
    dlg.setAttribute('aria-labelledby', 'cms-confirm-t');
    dlg.setAttribute('aria-describedby', 'cms-confirm-b');
    layerBox().append(dlg);
  }
  dlg.innerHTML = `<h2 id="cms-confirm-t">${esc(title)}</h2>
    <div id="cms-confirm-b">${String(body || '').split(/\n+/).filter(Boolean).map(p => `<p>${esc(p)}</p>`).join('')}</div>
    <div class="cms-confirm__foot">
      <span class="cms-confirm__right">
        <button type="button" class="adm-btn" data-r="cancel" autofocus>${esc(o.cancel || lab('cancel'))}</button>
        <button type="button" class="adm-btn ${danger ? 'adm-btn--danger' : 'adm-btn--primary'}" data-r="ok">${esc(o.ok || lab('ok'))}</button>
      </span>
    </div>`;
  const before = deepActive();
  return new Promise(res => {
    let result = false;
    dlg.onclick = e => { const b = e.target.closest('[data-r]'); if (b) { result = b.dataset.r === 'ok'; dlg.close(); } };
    dlg.onclose = () => { res(result); if (before?.isConnected) before.focus?.({ preventScroll: true }); };
    dlg.showModal();
    dlg.querySelector('[data-r=cancel]').focus();
  });
}

// ------------------------------------------------------------------ Modus und Abbrechen
function isEditMode() { return mode !== 'view'; }

/** Modus ohne Neuladen setzen (Eintrag: Ansehen ↔ Bearbeiten) */
function setMode(m) {
  mode = m;
  if (!bar) return;
  bar.dataset.mode = m;
  bar.classList.toggle('is-edit', m !== 'view');
  R.querySelectorAll('[data-bar-group]').forEach(g => { g.hidden = g.dataset.barGroup !== (m === 'view' ? 'view' : 'edit'); });
  R.querySelectorAll('.cms-seg [data-bar-mode]').forEach(b => { if (b.dataset.barMode === m) b.setAttribute('aria-current', 'true'); else b.removeAttribute('aria-current'); });
  R.querySelectorAll('[role=menuitemradio][data-bar-mode]').forEach(b => b.setAttribute('aria-checked', b.dataset.barMode === m ? 'true' : 'false'));
  R.querySelectorAll('[data-bar-when]').forEach(x => { x.hidden = x.dataset.barWhen !== (m === 'view' ? 'view' : 'edit'); });
  // Hook für Themes (editor.css, Hilfe „Technik › Seiten-Editor“): html.cms-editing = ein Bearbeiten-Modus ist aktiv,
  // html.cms-has-actionbar = zusätzlich Aktionsleiste unten (< 768 px) → [data-cms-hide-editing] wird ausgeblendet,
  // --cms-bottom-bar-h = ihre Höhe (0px ohne Leiste) für Themes, die eigene Leisten lieber verschieben
  d.documentElement.classList.toggle('cms-editing', m !== 'view');
  d.documentElement.classList.toggle('cms-has-actionbar', m !== 'view' && !!R.querySelector('.cms-bar__edit'));
  bottomBar();
}
let bbObs = null;
function bottomBar() {
  const eb = R?.querySelector('.cms-bar__edit');
  const set = () => {
    const fixed = eb && !eb.hidden && mode !== 'view' && getComputedStyle(eb).position === 'fixed';
    d.documentElement.style.setProperty('--cms-bottom-bar-h', fixed ? eb.offsetHeight + 'px' : '0px');
  };
  set();
  if (eb && !bbObs && 'ResizeObserver' in window) { bbObs = new ResizeObserver(set); bbObs.observe(eb); addEventListener('resize', set, { passive: true }); }
}

/** Bearbeiten verlassen (zurück zu „Ansehen“ oder zu href) – mit Rückfrage bei ungespeicherten Änderungen */
export async function cancel(href = null) {
  if (!H) { if (href) location.href = href; return; }
  if (!H.dirty()) { H.exit(href); return; }
  const r = await confirmDiscard();
  if (r === 'save') { if (await H.save()) H.exit(href); }
  else if (r === 'discard') { H.discard(); H.exit(href); }
}

function initModes() {
  R.addEventListener('click', e => {
    const m = e.target.closest('[data-bar-mode]');
    if (!m || e.metaKey || e.ctrlKey || e.shiftKey) return;
    const key = m.dataset.barMode;
    if (key === mode) { e.preventDefault(); return; }
    // Eintrag: Ansehen ↔ Bearbeiten ohne Neuladen
    if (cfg.kind === 'entry' && !m.getAttribute('href')) {
      e.preventDefault();
      if (key === 'edit') { H?.start?.(); }
      else if (key === 'view') cancel();
      return;
    }
    // Aus einem Bearbeiten-Modus heraus: erst fragen
    if (isEditMode() && H) { e.preventDefault(); cancel(m.getAttribute('href')); }
  });
  R.querySelectorAll('[data-bar-cancel]').forEach(b => b.addEventListener('click', () => cancel()));
  // „aria-disabled“ statt disabled (bleibt fokussierbar): Klick nicht an die Handler weiterreichen
  R.addEventListener('click', e => { const b = e.target.closest('[aria-disabled=true]'); if (b) { e.preventDefault(); e.stopPropagation(); } }, true);
  // Esc = Abbrechen
  d.addEventListener('keydown', e => {
    if (e.key !== 'Escape' || e.defaultPrevented || !H || !isEditMode() || e.isComposing) return;
    const pop = R.getElementById('cms-chip-pop');
    if (openMenu || openDialog() || (pop && !pop.hidden)) return;
    const root = d.documentElement, body = d.body;
    if (body.classList.contains('has-drawer') || body.classList.contains('has-epanel') || root.classList.contains('has-drawer')) return;
    if (d.querySelector('.ce-popover--opened,.ce-toolbox--opened,.ce-settings--opened,.ce-inline-toolbar--showed')) return;
    const a = deepActive();
    if (a && (a.isContentEditable || a.matches?.('input,textarea,select'))) return;
    e.preventDefault();
    cancel();
  });
}

/** Wer bearbeitet: { dirty(): bool, save(): Promise<bool>, discard(), exit(href), start?() } */
export function use(handlers) {
  H = handlers;
}

export const bar_ = {
  get mode() { return mode; },
  setMode, use, state: barState, cancel, confirm: confirmDiscard, ask,
  get texts() { return cfg.texts || {}; },
  get viewUrl() { return cfg.viewUrl || ''; },
};

export function initToolbar() {
  R = barRoot();
  bar = R?.querySelector('.cms-bar[data-bar]') || null;
  if (!bar) return;
  try { cfg = JSON.parse(bar.dataset.bar); } catch { cfg = { texts: {} }; }
  mode = cfg.mode || 'view';
  initMenus();
  initChip();
  initToggle();
  initModes();
  setMode(mode);
}
