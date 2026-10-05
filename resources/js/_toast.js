/*
 * Meldungen („Toast“) – eine Lösung für Verwaltung und Website (Teil von admin.js, global als CMSAdmin.toast / toastNext).
 *
 *   toast(msg, kind = 'ok' | 'error' | 'info', ms = 4000)   kurze Meldung unten mittig; Fehler als role="alert"
 *   toastNext(msg, kind)                                    Meldung für die NÄCHSTE Seite (nach Neuladen oder Weiterleitung) –
 *                                                           sessionStorage, zeigt flushToast() beim Start von admin.js
 * Ort: Verwaltung im Dokument (admin.css .cms-toast), Website in der Shadow-DOM-Ebene (editor.shadow.css – Kit-CSS wirkt nicht).
 * Es gibt immer nur eine Meldung; eine neue ersetzt die alte. Erweiterungen und Werkzeuge nutzen CMSAdmin.toast bzw. ctx.toast.
 */
import { layerBox } from './_shadow.js';

const KEY = 'cms-toast-next';
let el = null, timer = 0;

export function toast(msg, kind = 'ok', ms = 4000) {
  if (!msg) return;
  el ??= document.createElement('div');
  el.className = 'cms-toast cms-toast--' + (['ok', 'error', 'info'].includes(kind) ? kind : 'ok');
  el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
  el.textContent = String(msg);
  if (!el.isConnected) layerBox().append(el);
  clearTimeout(timer);
  timer = setTimeout(() => el.remove(), kind === 'error' ? Math.max(ms, 7000) : ms);
}

export function toastNext(msg, kind = 'ok') {
  try { sessionStorage.setItem(KEY, JSON.stringify({ msg: String(msg), kind, at: Date.now() })); } catch { /* privates Fenster */ }
}

export function flushToast() {
  let v = null;
  try { v = JSON.parse(sessionStorage.getItem(KEY) || 'null'); sessionStorage.removeItem(KEY); } catch { return; }
  if (v?.msg && Date.now() - (v.at || 0) < 60000) toast(v.msg, v.kind);
}
