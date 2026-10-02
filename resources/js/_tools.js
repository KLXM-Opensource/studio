/*
 * Werkzeuge beim Bearbeiten auf der Website (Core\FrontendTools) – Teil von admin.js, global als CMSAdmin.tools.
 *
 *  - Konfiguration: <script type="application/json" id="cms-tools"> (nur angemeldet, nur im Bearbeiten-Modus, nur erlaubte
 *    Werkzeuge). Knöpfe [data-cms-tool] in der Werkzeugleiste (Schatten-Wurzel) bzw. im Menü „⋯“, Tastenkürzel je Werkzeug.
 *  - Lazy: Erst beim ersten Öffnen wird das ES-Modul geladen (import()). Es registriert sich mit export default
 *    { mount(ctx), unmount(ctx) } oder ruft selbst CMSAdmin.tools.register(id, { mount, unmount }) auf.
 *  - Oberfläche: Seitenleiste in der gemeinsamen Shadow-DOM-Ebene (_shadow.js layer(), editor.shadow.css .cms-tpanel) –
 *    Kit-CSS wirkt nicht hinein. role="dialog" (nicht modal): Esc schließt, Fokus kehrt zurück; das Tastenkürzel springt
 *    zwischen Text und Seitenleiste hin und her (die Schreibmarke im Text bleibt gemerkt).
 *  - ctx (für mount/unmount): id, tool (Angaben vom Server: data, texts, endpoints), page, entry, kind, lang, csrf,
 *    panel { el, body, setTitle(t), close(), focus() }, t(key, params), fetch(url, { method, json, query }),
 *    selection() → { editable, text, range, rich } | null, insertText(text), insertLink({ href, ref, label, title, newTab }),
 *    focusText(), toast(msg, kind), announce(msg), on(event, fn) (wird bei unmount abgemeldet), close().
 *  - Ereignisse am document (CustomEvent, detail siehe Technik → Erweiterungen): cms:editor-ready, cms:block-select,
 *    cms:before-save (abbrechbar, detail.waitUntil(promise) für asynchrone Prüfungen), cms:saved, cms:published,
 *    cms:status-changed; dazu cms:tool-open / cms:tool-close.
 */
import { layerBox, barRoot, deepActive, uiAll } from './_shadow.js';
import { Rich } from './_rte.js';
import { ico } from './_icons.js';

const d = document;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const fill = (s, p = {}) => Object.entries(p).reduce((x, [k, v]) => x.replaceAll('{' + k + '}', String(v)), String(s ?? ''));

let cfg = null;                 // Konfiguration vom Server
const defs = new Map();         // id → Angaben
const impls = new Map();        // id → { mount, unmount }
const state = new Map();        // id → { ctx, panel, offs: [] }
let current = null;             // id des offenen Werkzeugs
let lastEditable = null, lastRange = null, opener = null;

// ------------------------------------------------------------------ Ereignisse
/** Ereignis am document melden (für Werkzeuge, Erweiterungen, Kits) */
export function emit(name, detail = {}) {
  d.dispatchEvent(new CustomEvent(name, { detail }));
}
/**
 * cms:before-save auslösen. Abbruch: event.preventDefault() (optional detail.reason = 'Text') oder detail.waitUntil(promise)
 * mit Ergebnis false bzw. Fehler. Ergebnis: Promise<{ ok: boolean, reason: string }>.
 */
export async function beforeSave(info = {}) {
  const waits = [];
  const detail = { ...info, reason: '', waitUntil(p) { waits.push(Promise.resolve(p)); } };
  const ev = new CustomEvent('cms:before-save', { detail, cancelable: true });
  if (!d.dispatchEvent(ev)) return { ok: false, reason: detail.reason };
  for (const p of waits) {
    try { if ((await p) === false) return { ok: false, reason: detail.reason }; }
    catch (e) { return { ok: false, reason: detail.reason || e?.message || '' }; }
  }
  return { ok: true, reason: '' };
}

// ------------------------------------------------------------------ Schreibmarke im Text merken
/** Bearbeitbares Element der Seite (nicht die CMS-Oberfläche in Schatten-Wurzeln) */
const isEditable = n => n?.nodeType === 1 && n.isContentEditable && !n.closest('.cms-bar-host,#cms-layer-host,#cms-epanel-host');
const editableRoot = n => { let e = n?.nodeType === 1 ? n : n?.parentElement; let top = null; while (e && isEditable(e)) { top = e; e = e.parentElement; } return top; };
/** Formatierter Text (Links erlaubt)? Seiten-Editor: data-edit-mode rich|inline, Eintrag: data-entry-mode ≠ plain|lines */
const isRich = el => !!el && (['rich', 'inline'].includes(el.dataset.editMode) || (el.dataset.entryMode !== undefined && !['plain', 'lines'].includes(el.dataset.entryMode)));

function track() {
  d.addEventListener('focusin', e => { const r = editableRoot(e.target); if (r) lastEditable = r; });
  d.addEventListener('selectionchange', () => {
    const s = getSelection();
    if (!s.rangeCount) return;
    const r = editableRoot(s.anchorNode);
    if (r) { lastEditable = r; lastRange = s.getRangeAt(0).cloneRange(); }
  });
}

function selection() {
  if (!lastEditable?.isConnected || !lastEditable.isContentEditable) return null;
  const range = lastRange && lastEditable.contains(lastRange.commonAncestorContainer) ? lastRange.cloneRange() : null;
  return { editable: lastEditable, range, text: range ? range.toString() : '', rich: isRich(lastEditable) };
}

function restore(sel) {
  sel.editable.focus({ preventScroll: true });
  const s = getSelection();
  s.removeAllRanges();
  if (sel.range) s.addRange(sel.range);
  else { const r = d.createRange(); r.selectNodeContents(sel.editable); r.collapse(false); s.addRange(r); }
}

/** Text an der gemerkten Schreibmarke einfügen (ersetzt die Auswahl). false = kein Textfeld aktiv */
function insertText(text) {
  const sel = selection();
  if (!sel) return false;
  restore(sel);
  d.execCommand('insertText', false, String(text));
  sel.editable.dispatchEvent(new Event('input', { bubbles: true }));
  lastRange = getSelection().rangeCount ? getSelection().getRangeAt(0).cloneRange() : null;
  return true;
}

/**
 * Link an der gemerkten Schreibmarke bzw. um den markierten Text (Rich.insertLink, wie die Linkauswahl). In Feldern ohne Links
 * wird nur der Text eingefügt. Ergebnis: 'link' | 'text' | false (kein Textfeld aktiv)
 */
function insertLink(res) {
  const sel = selection();
  if (!sel) return false;
  if (!sel.rich) return insertText(sel.text || res.label || res.href) ? 'text' : false;
  restore(sel);
  Rich.insertLink(sel.editable, res, sel.range);
  sel.editable.dispatchEvent(new Event('input', { bubbles: true }));
  lastRange = getSelection().rangeCount ? getSelection().getRangeAt(0).cloneRange() : null;
  return 'link';
}

// ------------------------------------------------------------------ Meldungen
let toastEl = null, toastT = 0;
function toast(msg, kind = 'ok') {
  toastEl ??= Object.assign(d.createElement('div'), { className: 'cms-toast' });
  toastEl.className = 'cms-toast cms-toast--' + kind;
  toastEl.setAttribute('role', kind === 'error' ? 'alert' : 'status');
  toastEl.textContent = msg;
  if (!toastEl.isConnected) layerBox().append(toastEl);
  clearTimeout(toastT);
  toastT = setTimeout(() => toastEl.remove(), 4000);
}
function announce(msg) {
  const live = uiAll('[data-editor-status],[data-entry-status]')[0];
  if (!live) return;
  live.textContent = '';
  setTimeout(() => { live.textContent = msg; }, 30);
}

// ------------------------------------------------------------------ Seitenleiste in der Ebene
function panelFor(def) {
  const box = layerBox();
  const el = d.createElement('section');
  el.className = 'cms-tpanel cms-tpanel--' + (def.panel?.size || 'narrow');
  el.id = 'cms-tool-' + def.id;
  el.setAttribute('role', 'dialog');
  el.setAttribute('aria-modal', 'false');
  el.setAttribute('aria-labelledby', el.id + '-t');
  el.hidden = true;
  el.innerHTML = `<header class="cms-tpanel__head"><span class="cms-tpanel__ico" aria-hidden="true">${ico(def.icon)}</span>
      <h2 class="cms-tpanel__t" id="${el.id}-t" tabindex="-1">${esc(def.panel?.title || def.label)}</h2>
      ${def.shortcut ? `<kbd class="cms-tpanel__kbd" title="${esc(cfg.texts.backToText)}">${esc(def.shortcut.label)}</kbd>` : ''}
      <button type="button" class="cms-tpanel__x" data-tpanel-close aria-label="${esc(cfg.texts.close)}" title="${esc(cfg.texts.close)} (Esc)">${ico('x')}</button></header>
    <div class="cms-tpanel__body"></div>`;
  box.append(el);
  el.querySelector('[data-tpanel-close]').addEventListener('click', () => close(def.id));
  el.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !e.defaultPrevented) { e.preventDefault(); e.stopPropagation(); close(def.id); }
  });
  return {
    el, body: el.querySelector('.cms-tpanel__body'),
    setTitle: t => { el.querySelector('.cms-tpanel__t').textContent = t; },
    close: () => close(def.id),
    focus: () => (el.querySelector('[autofocus]') || el.querySelector('input,select,textarea,button:not([data-tpanel-close])') || el.querySelector('.cms-tpanel__t'))?.focus(),
  };
}

// ------------------------------------------------------------------ Öffnen / Schließen
async function load(def) {
  if (impls.has(def.id)) return impls.get(def.id);
  const mod = await import(def.module);
  if (!impls.has(def.id) && mod?.default && typeof mod.default.mount === 'function') impls.set(def.id, mod.default);
  if (!impls.has(def.id)) throw new Error('Werkzeug „' + def.id + '“: Modul ohne mount()');
  return impls.get(def.id);
}

function makeCtx(def, panel) {
  const offs = [];
  const ctx = {
    id: def.id, tool: def, page: cfg.page, entry: cfg.entry, kind: cfg.kind, mode: cfg.mode, lang: cfg.lang, csrf: cfg.csrf, panel,
    t: (k, p) => fill(def.texts?.[k] ?? cfg.texts?.[k] ?? k, p),
    async fetch(url, o = {}) {
      const u = new URL(url, location.href);
      for (const [k, v] of Object.entries(o.query || {})) if (v !== undefined && v !== null) u.searchParams.set(k, v);
      const method = o.method || (o.json ? 'POST' : 'GET');
      const r = await fetch(u, { method, credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-Token': cfg.csrf, ...(o.json ? { 'Content-Type': 'application/json' } : {}) },
        body: o.json ? JSON.stringify(o.json) : undefined });
      let res = {};
      try { res = await r.json(); } catch { res = {}; }
      if (!r.ok || res.ok === false) throw Object.assign(new Error(res.error || cfg.texts.error), { status: r.status, data: res });
      return res;
    },
    selection, insertText, insertLink, toast, announce,
    focusText() { const s = selection(); if (s) restore(s); return !!s; },
    on(name, fn) { d.addEventListener(name, fn); offs.push(() => d.removeEventListener(name, fn)); },
    close: () => close(def.id),
  };
  return { ctx, offs };
}

function buttons(id) {
  return [...(barRoot()?.querySelectorAll(`[data-cms-tool="${id}"]`) || [])];
}

/** Werkzeug öffnen (lädt das Modul beim ersten Mal) */
export async function open(id) {
  const def = defs.get(id);
  if (!def) return false;
  if (current && current !== id) close(current, false);
  // Auslöser merken: Fokus kehrt beim Schließen dorthin zurück (Text mit Schreibmarke, sonst der Knopf)
  const a = deepActive();
  opener = editableRoot(a) || a;
  let st = state.get(id);
  if (!st) {
    const panel = panelFor(def);
    const { ctx, offs } = makeCtx(def, panel);
    st = { ctx, panel, offs, mounted: false };
    state.set(id, st);
  }
  current = id;
  st.panel.el.hidden = false;
  d.documentElement.classList.add('cms-has-tpanel');
  buttons(id).forEach(b => b.setAttribute('aria-expanded', 'true'));
  if (!st.mounted) {
    st.panel.body.innerHTML = `<p class="cms-tpanel__wait" role="status">${esc(cfg.texts.loading)}</p>`;
    try {
      const impl = await load(def);
      st.panel.body.innerHTML = '';
      await impl.mount(st.ctx);
      st.mounted = true;
    } catch (e) {
      st.panel.body.innerHTML = `<p class="cms-tpanel__err" role="alert">${esc(cfg.texts.error)} ${esc(e?.message || '')}</p>`;
      console.error(e);
    }
  } else {
    impls.get(id)?.show?.(st.ctx);
  }
  st.panel.focus();
  emit('cms:tool-open', { id });
  return true;
}

/** Werkzeug schließen; refocus = Fokus zurück zum Auslöser (Text bzw. Knopf) */
export function close(id = current, refocus = true) {
  const st = state.get(id);
  if (!st || st.panel.el.hidden) return;
  st.panel.el.hidden = true;
  if (current === id) current = null;
  if (!current) d.documentElement.classList.remove('cms-has-tpanel');
  buttons(id).forEach(b => b.setAttribute('aria-expanded', 'false'));
  impls.get(id)?.hide?.(st.ctx);
  emit('cms:tool-close', { id });
  if (refocus) {
    const s = selection();
    if (opener && opener === s?.editable) restore(s);
    else (opener?.isConnected && opener !== d.body ? opener : buttons(id).find(b => b.getClientRects().length))?.focus?.({ preventScroll: true });
  }
}

export function toggle(id) {
  if (current === id) return close(id);
  return open(id);
}

/** Werkzeug abmelden (z. B. beim Verlassen des Bearbeitens) – ruft unmount(ctx) */
export function unmount(id) {
  const st = state.get(id);
  if (!st) return;
  close(id, false);
  try { impls.get(id)?.unmount?.(st.ctx); } catch (e) { console.error(e); }
  st.offs.forEach(f => f());
  st.panel.el.remove();
  state.delete(id);
}

/** Werkzeug-Umsetzung anmelden (aus dem Modul heraus oder für Werkzeuge ohne Modul) */
export function register(id, impl) {
  if (impl && typeof impl.mount === 'function') impls.set(id, impl);
}

// ------------------------------------------------------------------ Start
function shortcutMatch(e, sc) {
  return !!sc && e.code === sc.code && !!e.altKey === !!sc.alt && !!e.shiftKey === !!sc.shift && !!e.ctrlKey === !!sc.ctrl && !!e.metaKey === !!sc.meta;
}

export function initTools() {
  const el = d.getElementById('cms-tools');
  if (!el) return;
  try { cfg = JSON.parse(el.textContent); } catch { cfg = null; }
  if (!cfg?.tools?.length) return;
  cfg.texts ||= {};
  cfg.tools.forEach(t => defs.set(t.id, t));
  track();
  // Knöpfe in der Werkzeugleiste (Schatten-Wurzel): Klick öffnet/schließt
  barRoot()?.addEventListener('click', e => {
    const b = e.target.closest('[data-cms-tool]');
    if (!b) return;
    e.preventDefault();
    toggle(b.dataset.cmsTool);
  });
  // Tastenkürzel: öffnen; ist das Werkzeug offen, springt es zwischen Seitenleiste und Text
  d.addEventListener('keydown', e => { if (!e.defaultPrevented && !e.isComposing && shortcut(e)) e.preventDefault(); }, true);
  // Tasten in der Seitenleiste bleiben dort: Editor.js und Kürzel der Seite hören am document (Enter würde dort z. B. einen
  // Block anlegen, Rücktaste markierte Blöcke löschen). Darum endet das echte Ereignis schon am window, und eine nicht
  // „composed“ Kopie läuft nur innerhalb der Schatten-Wurzel zum Ziel – Standardaktionen (Tippen, Tab, Enter auf Knöpfen)
  // bleiben erhalten, außer ein Handler der Seitenleiste verhindert sie (preventDefault wird übertragen).
  window.addEventListener('keydown', e => {
    if (!e.isTrusted || !current) return;
    const panel = state.get(current)?.panel.el;
    if (!panel || panel.hidden || !e.composedPath().includes(panel)) return;
    e.stopPropagation();
    if (e.isComposing) return;
    if (shortcut(e)) { e.preventDefault(); return; }
    const copy = new KeyboardEvent('keydown', { key: e.key, code: e.code, location: e.location, repeat: e.repeat, altKey: e.altKey, ctrlKey: e.ctrlKey,
      metaKey: e.metaKey, shiftKey: e.shiftKey, bubbles: true, cancelable: true, composed: false });
    if (!e.composedPath()[0].dispatchEvent(copy)) e.preventDefault();
  }, true);
}

/** Tastenkürzel eines Werkzeugs? Dann öffnen bzw. zwischen Text und Seitenleiste springen → true */
function shortcut(e) {
  for (const def of defs.values()) {
    if (!shortcutMatch(e, def.shortcut)) continue;
    // Eintrag: nur im Modus „Bearbeiten“ (Knöpfe sind sonst verborgen)
    if (buttons(def.id).length && buttons(def.id).every(b => b.hidden)) return false;
    const st = state.get(def.id);
    if (current === def.id && st) {
      if (st.panel.el.contains(deepActive())) { if (!st.ctx.focusText()) close(def.id); }
      else st.panel.focus();
    } else open(def.id);
    return true;
  }
  return false;
}

export const tools_ = { register, open, close, toggle, unmount, emit, beforeSave, get current() { return current; }, get list() { return [...defs.values()]; } };
