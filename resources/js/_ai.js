/*
 * KI-Assistent der Redaktion – Einstieg in admin.js (Core\AI\Assist, Konfiguration <script type="application/json" id="cms-ai">).
 *
 * Hier liegt nur, was sofort und synchron gebraucht wird: Konfiguration, Knopf „✦ KI“ der Formatierungsleisten (Rich.barHtml)
 * und der Knopf je Bild beim Hochladen. Die Oberfläche (Dialoge, Übersetzen, SEO, Medien, Support, Generatoren – _ai_ui.js)
 * ist das eigene Bündel admin-ai.mjs: Es lädt nur, wenn die Website KI für die Person anbietet (#cms-ai vorhanden), und zwar
 * gleich beim Start – bis dahin warten Aufrufe (aiExec, initAi, window.CMSAi.*) auf das Modul. Ohne KI lädt nichts.
 */
import { t } from './_i18n.js';
import { esc } from './_html.js';
import { lazy } from './_lazy.js';

const d = document;
export const SPARK = '<span class="kia-spark" aria-hidden="true">✦</span>';

let CFG;
/** Konfiguration (null = kein Assistent) */
export function cfg() {
  if (CFG === undefined) { try { CFG = JSON.parse(d.querySelector('#cms-ai')?.textContent || 'null'); } catch { CFG = null; } }
  return CFG;
}
export const can = cap => !!cfg()?.[cap];

/** Knopf für Formatierungsleisten (Rich.barHtml) */
export function aiBarHtml() {
  if (!can('text')) return '';
  return `<span class="rte-group kia-rtegroup"><button type="button" data-cmd="ai" class="kia-rtebtn" aria-haspopup="dialog" aria-label="${esc(t('KI-Assistent'))}" title="${esc(t('KI-Assistent: verbessern, kürzen, vereinfachen, schreiben …'))}">${SPARK}<span>${esc(t('KI'))}</span></button></span>`;
}

/** Uploader (_media.js): Knopf je Bild in der Warteschlange */
export function uploadSlot(i) {
  if (!can('vision') || !i.isImage || i.error) return '';
  return `<span class="kia-mu">
    <button type="button" class="adm-btn adm-btn--small kia-btn" data-kia-alt${i.aiBusy ? ' disabled' : ''}>${SPARK} ${esc(i.aiBusy ? t('Die KI schaut sich das Bild an …') : t('Alt-Text vorschlagen'))}</button>
    ${i.aiPending ? `<span class="kia-badge">${esc(t('KI-Vorschlag – bitte prüfen'))}</span><button type="button" class="adm-btn adm-btn--small" data-kia-ok>${esc(t('Geprüft ✓'))}</button>` : ''}
    ${i.aiDeco ? `<span class="kia-mu__hint">${esc(t('Die KI hält das Bild für rein dekorativ – dann „dekorativ“ ankreuzen.'))}</span>` : ''}
    ${i.aiError ? `<span class="mu-err" role="alert">${esc(i.aiError)}</span>` : ''}</span>`;
}

// ------------------------------------------------------------------ Oberfläche (admin-ai.mjs) bei Bedarf
const ui = () => lazy('admin-ai');
/** Aufruf an die Oberfläche weiterreichen, sobald sie geladen ist */
const later = name => (...a) => { ui().then(m => m[name]?.(...a)); };

/** Aufruf aus Rich.exec(area, 'ai') – Rich-Text-Bereich (Seitenleiste, Formular oder direkt im Text) */
export const aiExec = later('aiExec');
/** Knöpfe an Textfeldern, Karten und Bereiche der KI in scope (ohne Konfiguration wirkungslos) */
export function initAi(scope = d) {
  if (cfg()) ui().then(m => m.initAi?.(scope));
}
export const translateReview = later('translateReview');

if (cfg()) {
  // Vorläufige Fassung für _media.js – admin-ai.mjs ersetzt sie beim Laden durch die eigenen Funktionen
  window.CMSAi = { cfg, aiExec, aiBarHtml, initAi, translateReview, uploadSlot,
    uploadBind: later('uploadBind'), mediaPanel: later('mediaPanel'), mediaSummary: later('mediaSummary') };
  ui();
}
