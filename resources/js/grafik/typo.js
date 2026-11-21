/*
 * Grafik-Editor – Typografie: OpenType-Merkmale (font-feature-settings), Absatz-Einstellungen eines Textes (Abstände,
 * Einzüge, Blocksatz, Silbentrennung) und das Nachladen der Trennmuster.
 *
 * OpenType: alles in einer Eigenschaft font-feature-settings (style, je Text oder je Lauf) – z. B. "smcp", "onum", "liga" 0.
 * Standardmerkmale (liga, calt, kern) sind an; geschrieben wird nur die Abweichung. „In Kurven umwandeln“ übergibt dieselben
 * Merkmale an fontkit (outline.js), damit Ligaturen, Kapitälchen, Ziffern und Stilsätze in den Umrissen stimmen.
 *
 * Absatz: data-ge-para="al:justify;pb:6;pa:12;li:0;ri:0;fi:16;hy:de" am Text (Arbeitsfassung). Die Web-Fassung braucht die
 * Angaben nicht – Zeilenlage, Wortabstände (Blocksatz) und Trennstriche sind dort schon echte Positionen und Zeichen. MIT
 */
import { t } from '../_i18n.js';

/** Merkmale, die ohne Angabe an sind (Browser und fontkit) */
export const DEFAULT_ON = ['liga', 'calt', 'kern', 'clig'];

/** font-feature-settings → { tag: 0|1|n } */
export function parseFeatures(v) {
  const o = {};
  for (const m of String(v || '').matchAll(/["']([A-Za-z0-9]{4})["']\s*(on|off|\d+)?/g)) o[m[1]] = m[2] === 'off' ? 0 : m[2] === undefined || m[2] === 'on' ? 1 : parseInt(m[2], 10);
  return o;
}
/** { tag: wert } → font-feature-settings (Standardwerte weglassen) */
export function fmtFeatures(o) {
  return Object.entries(o || {}).filter(([k, v]) => !(DEFAULT_ON.includes(k) && v === 1) && !(!DEFAULT_ON.includes(k) && v === 0))
    .map(([k, v]) => v === 1 ? `"${k}"` : `"${k}" ${v}`).join(', ');
}
/** Für fontkit: { tag: true|false } aus font-feature-settings */
export function fontkitFeatures(v) {
  const o = {};
  for (const [k, n] of Object.entries(parseFeatures(v))) o[k] = !!n;
  return o;
}
/** Ist ein Merkmal an (mit Standardwerten)? */
export const featureOn = (o, tag) => tag in o ? !!o[tag] : DEFAULT_ON.includes(tag);

/** Bedienfeld OpenType: Gruppen mit Merkmalen; type check = an/aus, radio = eines aus einer Gruppe */
export const FEATURES = () => [
  { id: 'lig', label: t('Ligaturen'), items: [['liga', t('Standard-Ligaturen')], ['dlig', t('Bedingte Ligaturen')], ['calt', t('Kontextbedingte Alternativen')], ['swsh', t('Zierbuchstaben')]] },
  { id: 'caps', label: t('Kapitälchen'), radio: [['', t('Aus')], ['smcp', t('Kapitälchen')], ['c2sc', t('Alles in Kapitälchen')]] },
  { id: 'numform', label: t('Ziffernform'), radio: [['', t('Standard')], ['lnum', t('Versalziffern')], ['onum', t('Mediävalziffern')]] },
  { id: 'numwidth', label: t('Ziffernbreite'), radio: [['', t('Standard')], ['pnum', t('Proportional')], ['tnum', t('Tabellenziffern')]] },
  { id: 'num', label: t('Ziffern'), items: [['frac', t('Brüche')], ['zero', t('Null mit Schrägstrich')], ['ordn', t('Ordinalzeichen')]] },
  { id: 'pos', label: t('Position'), radio: [['', t('Normal')], ['sups', t('Hochgestellt')], ['subs', t('Tiefgestellt')]] },
];
/** Merkmale einer Gruppe radio, die sich gegenseitig ausschließen (c2sc schließt smcp ein) */
export const RADIO_TAGS = { caps: ['smcp', 'c2sc'], numform: ['lnum', 'onum'], numwidth: ['pnum', 'tnum'], pos: ['sups', 'subs'] };

// ================================================================= Absatz

const PARA_KEYS = { al: 'al', pb: 'pb', pa: 'pa', li: 'li', ri: 'ri', fi: 'fi', hy: 'hy' };
/** data-ge-para → { al, pb, pa, li, ri, fi, hy } (Zahlen in Einheiten der Zeichenfläche) */
export function paraOf(el) {
  const o = { al: '', pb: 0, pa: 0, li: 0, ri: 0, fi: 0, hy: '' };
  for (const part of String(el?.getAttribute?.('data-ge-para') || '').split(';')) {
    const [k, v] = part.split(':');
    if (!PARA_KEYS[k]) continue;
    o[k] = ['al', 'hy'].includes(k) ? String(v || '').replace(/[^a-z-]/g, '') : parseFloat(v) || 0;
  }
  return o;
}
export function setPara(el, patch) {
  const o = { ...paraOf(el), ...patch };
  const s = Object.entries(o).filter(([, v]) => v !== '' && v !== 0 && v !== null).map(([k, v]) => `${k}:${typeof v === 'number' ? Math.round(v * 100) / 100 : v}`).join(';');
  if (s) el.setAttribute('data-ge-para', s); else el.removeAttribute('data-ge-para');
}

// ================================================================= Silbentrennung (nachgeladen)

const HY = {};          // Sprache → Funktion (Wort → Wort mit U+00AD) bzw. Promise
let hyUrls = {};
/** Adressen der Trennmuster-Module (boot.modules.hyphen) */
export function setHyphenModules(urls) { hyUrls = urls || {}; }
/** Trennfunktion, wenn schon geladen – sonst null (und Laden starten; done() danach) */
export function hyphenator(lang, done) {
  const l = lang === 'en' ? 'en' : 'de';
  if (typeof HY[l] === 'function') return HY[l];
  if (!HY[l] && hyUrls[l]) {
    HY[l] = import(hyUrls[l]).then(m => { HY[l] = m.default; done?.(); }).catch(e => { console.error('[Grafik-Editor] Silbentrennung', e); HY[l] = null; });
  } else if (HY[l]?.then && done) HY[l].then(() => done());
  return null;
}
/** Trennmuster laden (Promise) */
export function loadHyphens(lang) {
  const l = lang === 'en' ? 'en' : 'de';
  if (typeof HY[l] === 'function') return Promise.resolve(HY[l]);
  return new Promise(res => { if (hyphenator(l, () => res(HY[l]))) res(HY[l]); else if (!HY[l]) res(null); });
}
/** Trennmuster für alle Texte einer Grafik mit Silbentrennung vorab laden (vor dem ersten Bearbeiten) */
export function preloadHyphens(root) {
  for (const el of root.querySelectorAll('text[data-ge-para*="hy:"]')) { const l = paraOf(el).hy; if (l) hyphenator(l); }
}

/** Trennstellen eines Wortes (Indizes, vor denen getrennt werden darf) – mindestens 2 Zeichen vorn, 3 hinten */
export function hyphenPoints(fn, word) {
  if (!fn || word.length < 5) return [];
  const out = [];
  let i = 0;
  for (const ch of fn(word)) { if (ch === '­') out.push(i); else i += ch.length; }
  return out.filter(p => p >= 2 && word.length - p >= 3);
}
