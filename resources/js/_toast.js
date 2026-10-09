/*
 * Meldungen („Toast“) – eine Lösung für Verwaltung und Website (Teil von admin.js, global als CMSAdmin.toast / toastNext).
 *
 *   toast(msg, kind = 'ok' | 'error' | 'info', ms = 4000, opts = {})
 *     ok/info: unten mittig, verschwindet nach ms. error: auffällige Karte oben mittig (role="alert"), mindestens 12 s,
 *     mit opts.sticky bis zum Schließen. opts.title = fette erste Zeile, opts.action = { label, fn } = Knopf (z. B. „Zum Feld“).
 *   toastNext(msg, kind)   Meldung für die NÄCHSTE Seite (nach Neuladen oder Weiterleitung) – sessionStorage, zeigt flushToast()
 *                          beim Start von admin.js
 * Ort: Verwaltung im Dokument (admin.css .cms-toast), Website in der Shadow-DOM-Ebene (editor.shadow.css – Kit-CSS wirkt nicht).
 * Es gibt immer nur eine Meldung; eine neue ersetzt die alte (ein offener Fehler wird nur durch einen neuen Fehler ersetzt).
 */
import { layerBox } from './_shadow.js';
import { t } from './_i18n.js';

const KEY = 'cms-toast-next';
let el = null, timer = 0;
const ICON = { ok: '✓', error: '!', info: 'i' };

export function toast(msg, kind = 'ok', ms = 4000, opts = {}) {
  if (!msg) return;
  kind = ['ok', 'error', 'info'].includes(kind) ? kind : 'ok';
  // Ein stehender Fehler wird nicht von einer Erfolgs-/Hinweismeldung verdrängt
  if (el?.isConnected && el.dataset.sticky && kind !== 'error') return;
  el ??= document.createElement('div');
  el.className = 'cms-toast cms-toast--' + kind;
  el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
  if (opts.sticky) el.dataset.sticky = '1'; else delete el.dataset.sticky;
  el.replaceChildren();
  const ico = document.createElement('span');
  ico.className = 'cms-toast__ico'; ico.setAttribute('aria-hidden', 'true'); ico.textContent = ICON[kind];
  const body = document.createElement('span');
  body.className = 'cms-toast__body';
  if (opts.title) { const b = document.createElement('strong'); b.textContent = String(opts.title); body.append(b); }
  const m = document.createElement('span'); m.textContent = String(msg); body.append(m);
  el.append(ico, body);
  if (opts.action?.label && typeof opts.action.fn === 'function') {
    const a = document.createElement('button');
    a.type = 'button'; a.className = 'cms-toast__act'; a.textContent = opts.action.label;
    a.addEventListener('click', () => opts.action.fn());
    el.append(a);
  }
  if (kind === 'error' || opts.sticky) {
    const x = document.createElement('button');
    x.type = 'button'; x.className = 'cms-toast__x'; x.setAttribute('aria-label', t('Meldung schließen')); x.textContent = '×';
    x.addEventListener('click', hide);
    el.append(x);
  }
  if (!el.isConnected) layerBox().append(el);
  clearTimeout(timer);
  if (!opts.sticky) timer = setTimeout(hide, kind === 'error' ? Math.max(ms, 12000) : ms);
}

export function hide() {
  clearTimeout(timer);
  if (el) { el.remove(); delete el.dataset.sticky; }
}

export function toastNext(msg, kind = 'ok') {
  try { sessionStorage.setItem(KEY, JSON.stringify({ msg: String(msg), kind, at: Date.now() })); } catch { /* privates Fenster */ }
}

export function flushToast() {
  let v = null;
  try { v = JSON.parse(sessionStorage.getItem(KEY) || 'null'); sessionStorage.removeItem(KEY); } catch { return; }
  if (v?.msg && Date.now() - (v.at || 0) < 60000) toast(v.msg, v.kind);
}
