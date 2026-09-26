/*
 * Symbole aus dem Sprite (Phosphor duotone, Core\Icons) – JavaScript-Gegenstück zu icon() in PHP.
 *   ico('calendar')                    → <svg class="ico"><use href="…/time.svg?v=…#i-calendar"/></svg>
 *   ico('calendar', 'x', 'Termine')    → mit Klasse und zugänglichem Namen
 * Namen löst der Server auf (Menü-Schlüssel, alte Zeichen → Symbolname); hier nur fertige Symbolnamen.
 * Sprites: je Symbol das kleine Sprite seines Themas bzw. core.svg (Index icons-map.json, beim Build eingebunden) –
 * der Browser lädt nur die Themen, deren Symbole gerade zu sehen sind. Basisadresse (mit ?v=): data-icons an <html>
 * (Verwaltung) bzw. an der Werkzeugleiste (Website); data-icons-topics = aktivierte Symbolbereiche (leer = alle).
 */
import { ui } from './_shadow.js';
import MAP from '../../public/assets/icons/icons-map.json';

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
export const NAME = /^[a-z][a-z0-9-]{0,39}$/;

let sprite = null;
/** Adresse des Sprites für ein Symbol (ohne Namen: vollständiges icons.svg) */
export function spriteUrl(name = '') {
  if (!sprite) sprite = ui('[data-icons]')?.dataset.icons || '/assets/icons/icons.svg';
  const f = name && MAP[name];
  return f ? sprite.replace(/icons\.svg(?=\?|$)/, f + '.svg') : sprite;
}

/** Aktivierte Symbolbereiche (Grundeinstellungen → „Symbolbereiche“); null = alle */
export function enabledTopics() {
  const v = ui('[data-icons-topics]')?.dataset.iconsTopics;
  return v ? v.split(' ').filter(Boolean) : null;
}

export function ico(name, cls = '', label = '') {
  const a11y = label ? ` role="img" aria-label="${esc(label)}"` : ' aria-hidden="true"';
  return `<svg class="ico${cls ? ' ' + esc(cls) : ''}"${a11y} focusable="false" width="1em" height="1em" fill="currentColor">${label ? `<title>${esc(label)}</title>` : ''}<use href="${esc(spriteUrl(name))}#i-${esc(name)}"/></svg>`;
}

/** Symbolname → SVG, sonst (altes Zeichen) als Text */
export function icoOrGlyph(v, cls = '') {
  if (!v) return '';
  return NAME.test(v) ? ico(v, cls) : `<span class="ico ico--glyph${cls ? ' ' + esc(cls) : ''}" aria-hidden="true">${esc(v)}</span>`;
}

// Katalog (Themen, Bezeichnungen, Suchbegriffe) – einmal je Seite geladen (Symbolauswahl, Übersicht)
let catalog = null;
export function loadCatalog() {
  if (!catalog) {
    const url = ui('[data-icons-catalog]')?.dataset.iconsCatalog || spriteUrl().replace(/icons\.svg(\?.*)?$/, 'catalog.json$1');
    catalog = fetch(url, { credentials: 'same-origin' }).then(r => r.json()).catch(() => ({ topics: [], icons: {} }));
  }
  return catalog;
}

/** Platzhalter gleicher Größe; lazyIcons() setzt das Symbol ein, sobald es sichtbar wird (lädt erst dann das Themen-Sprite) */
export const icoLazy = (name, cls = '') => `<svg class="ico${cls ? ' ' + esc(cls) : ''}" aria-hidden="true" focusable="false" width="1em" height="1em" data-lz="${esc(name)}"></svg>`;

/** Platzhalter in scope füllen, sobald ihre Gruppe (groupSel) in root (Standard: Fenster) sichtbar wird */
export function lazyIcons(scope, groupSel, root = null) {
  const fill = g => g.querySelectorAll('svg[data-lz]').forEach(s => { s.insertAdjacentHTML('beforebegin', ico(s.dataset.lz, [...s.classList].filter(c => c !== 'ico').join(' '))); s.remove(); });
  const groups = [...scope.querySelectorAll(groupSel)].filter(g => g.querySelector('svg[data-lz]'));
  if (!('IntersectionObserver' in window)) { groups.forEach(fill); return null; }
  const io = new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { fill(e.target); io.unobserve(e.target); } }), { root, rootMargin: '300px 0px' });
  groups.forEach(g => io.observe(g));
  return io;
}
