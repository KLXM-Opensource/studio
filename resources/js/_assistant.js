/*
 * Assistent-Chat der Redaktion (Core\AI\Assistant) – nur der Einstieg in admin.js: Knöpfe [data-assistant], Tastenkürzel
 * Alt+Umschalt+K (Mac: ⌥⇧K), Spotlight „Assistent fragen …“ (window.cmsAssistant). Das Chat-Fenster selbst
 * (assistant.mjs + assistant.css) lädt erst beim ersten Öffnen. Ohne <script id="cms-assistant"> passiert nichts.
 */
const d = document;
let cfg = null, mod = null;

const load = () => (mod ||= import(cfg.module).then(m => { m.init(cfg); return m; }));

export function initAssistant() {
  try { cfg = JSON.parse(d.getElementById('cms-assistant')?.textContent || 'null'); } catch { cfg = null; }
  if (!cfg) return;
  window.cmsAssistant = {
    open: (o = {}) => load().then(m => m.open(o)),
    toggle: (opener) => load().then(m => m.toggle(opener)),
  };
  d.addEventListener('click', e => {
    const b = e.target.closest?.('[data-assistant]');
    if (!b || e.metaKey || e.ctrlKey || e.shiftKey) return;
    e.preventDefault();
    window.cmsAssistant.toggle(b);
  });
  d.addEventListener('keydown', e => {
    if (e.altKey && e.shiftKey && !e.metaKey && !e.ctrlKey && e.code === 'KeyK') { e.preventDefault(); window.cmsAssistant.toggle(d.activeElement); }
  });
  // Seite im KI-Bereich (Chat im Hauptbereich) bzw. im selben Tab zuvor geöffnet → gleich wieder anzeigen
  let reopen = false;
  try { reopen = sessionStorage.getItem('kas-open:' + cfg.site + ':' + cfg.user) === '1'; } catch { /* aus */ }
  if (d.querySelector('[data-assistant-page]') || reopen) load().then(m => m.restore());
}
