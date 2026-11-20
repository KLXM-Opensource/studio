/**
 * Vorschaugrafiken der Blockauswahl („+ Block einfügen“, Core\Blocks\Picker) – schematische Skizzen der Block-Layouts.
 *
 *   node tools/block-previews.mjs            schreibt alle Vorschauen neu
 *   node tools/block-previews.mjs hero faq   nur diese Namen
 *
 * Ziele: resources/img/previews/{typ}.svg (Kern, gemeinsame Blocknamen) und kits/{kit}/assets/img/previews/{typ}.svg
 * (kit-eigene Blöcke); der Build kopiert sie nach public/assets/… (img/ wird 1:1 übernommen).
 *
 * Stil (gilt auch für Vorschauen eigener Kits): viewBox 0 0 160 100, transparenter Hintergrund, nur zwei Farben –
 * neutrales Grau INK in Deckkräften (Text .3–.8, Flächen .12–.2, Linien .45) und ein Akzent ACC für Knöpfe und
 * Hervorhebungen. Beide Farben sind mittelhell und funktionieren auf hellem und dunklem Grund der Verwaltung.
 * Kein Text, keine Bilder, keine Schriften – nur Rechtecke, Kreise, Linien; Dateien unter 2 KB.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const INK = '#8A94A6', ACC = '#5B7FE1';
const r = n => Math.round(n * 10) / 10;

// ---------------------------------------------------------------- Bausteine
const rect = (x, y, w, h, { o = 1, fill = INK, rx = 1.5 } = {}) => `<rect x="${r(x)}" y="${r(y)}" width="${r(w)}" height="${r(h)}" rx="${rx}" fill="${fill}"${o < 1 ? ` opacity="${o}"` : ''}/>`;
/** Textzeile */
const line = (x, y, w, o = 0.4, h = 3) => rect(x, y, w, h, { o, rx: h / 2 });
/** Überschrift */
const head = (x, y, w, h = 6) => rect(x, y, w, h, { o: 0.75, rx: 2 });
/** Dachzeile (Akzent) */
const eyebrow = (x, y, w = 18) => rect(x, y, w, 2.5, { fill: ACC, o: 0.8, rx: 1.25 });
/** Absatz: n Zeilen, letzte kürzer */
const para = (x, y, w, n = 3, gap = 6, o = 0.35) => Array.from({ length: n }, (_, i) => line(x, y + i * gap, i === n - 1 && n > 1 ? w * 0.65 : w, o)).join('');
/** Bildplatzhalter mit Bergen und Sonne */
const img = (x, y, w, h, { o = 0.18, rx = 2 } = {}) => {
  const s = Math.min(w, h);
  const b = y + h, m1 = x + w * 0.32, m2 = x + w * 0.62;
  return rect(x, y, w, h, { o, rx })
    + `<path d="M${r(x + w * 0.08)} ${r(b - h * 0.12)}L${r(m1)} ${r(y + h * 0.48)}L${r(x + w * 0.48)} ${r(y + h * 0.68)}L${r(m2)} ${r(y + h * 0.4)}L${r(x + w * 0.92)} ${r(b - h * 0.12)}Z" fill="${INK}" opacity="0.32"/>`
    + `<circle cx="${r(x + w * 0.78)}" cy="${r(y + h * 0.28)}" r="${r(Math.max(1.5, s * 0.09))}" fill="${INK}" opacity="0.32"/>`;
};
/** Knopf (Akzent), outline = Zweitknopf */
const btn = (x, y, w = 22, h = 7, outline = false) => outline
  ? `<rect x="${r(x + 0.5)}" y="${r(y + 0.5)}" width="${r(w - 1)}" height="${r(h - 1)}" rx="2" stroke="${ACC}" opacity="0.8"/>`
  : rect(x, y, w, h, { fill: ACC, rx: 2 });
/** Umrandete Fläche (Karte, Feld) */
const box = (x, y, w, h, { rx = 2.5, fill = 0.05, stroke = 0.4 } = {}) => `<rect x="${r(x + 0.5)}" y="${r(y + 0.5)}" width="${r(w - 1)}" height="${r(h - 1)}" rx="${rx}" fill="${INK}" fill-opacity="${fill}" stroke="${INK}" stroke-opacity="${stroke}"/>`;
/** Symbol (Akzentpunkt bzw. -quadrat) */
const dot = (cx, cy, rad = 3, o = 0.85) => `<circle cx="${r(cx)}" cy="${r(cy)}" r="${rad}" fill="${ACC}"${o < 1 ? ` opacity="${o}"` : ''}/>`;
const ico = (x, y, s = 7) => rect(x, y, s, s, { fill: ACC, o: 0.85, rx: 2 });
const circle = (cx, cy, rad, o = 0.2) => `<circle cx="${r(cx)}" cy="${r(cy)}" r="${rad}" fill="${INK}" opacity="${o}"/>`;
const stroke = (d, o = 0.45, w = 1.2, c = INK) => `<path d="${d}" stroke="${c}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round" opacity="${o}"/>`;
/** Pfeil nach rechts / links */
const chev = (x, y, dir = 1, o = 0.6) => stroke(`M${x} ${y - 3}l${3 * dir} 3l${-3 * dir} 3`, o, 1.4);
/** Plus/Minus (Aufklapp-Zeichen) */
const plus = (cx, cy, open = false) => stroke(`M${cx - 2.5} ${cy}h5` + (open ? '' : `M${cx} ${cy - 2.5}v5`), 0.7, 1.2);
/** Trennlinie */
const hr = (x, y, w, o = 0.3) => stroke(`M${x} ${y}h${w}`, o, 0.8);
/** Kopf eines Abschnitts: Dachzeile + Überschrift zentriert */
const secHead = (y = 10, w = 60) => eyebrow(80 - 9, y) + head(80 - w / 2, y + 5, w);
const grid = (n, x0, w, gap) => Array.from({ length: n }, (_, i) => x0 + i * (w + gap));

const svg = body => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 100" fill="none">${body}</svg>\n`;

// ---------------------------------------------------------------- Kern (resources/img/previews)
const CORE = {
  hero: () => eyebrow(10, 26) + head(10, 32, 62, 8) + head(10, 43, 46, 8) + para(10, 58, 56, 2) + btn(10, 72) + btn(36, 72, 22, 7, true) + img(84, 14, 66, 72, { rx: 3 }),
  richtext: () => head(24, 12, 70) + para(24, 25, 112, 4) + rect(24, 52, 50, 4.5, { o: 0.6, rx: 2 }) + para(24, 61, 112, 2) + [74, 82].map(y => dot(27, y + 1.5, 1.5) + line(33, y, 80)).join(''),
  text_image: () => eyebrow(10, 22) + head(10, 28, 58) + para(10, 41, 62, 4) + btn(10, 68) + img(84, 14, 66, 72),
  features: () => secHead(8) + grid(3, 10, 42, 7).map(x => box(x, 30, 42, 60) + ico(x + 6, 37) + line(x + 6, 50, 26, 0.7, 4) + para(x + 6, 59, 30, 3, 5.5, 0.3)).join(''),
  stats: () => secHead(10) + grid(4, 10, 32, 8).map((x, i) => rect(x + 4, 40, i % 2 ? 20 : 24, 12, { o: 0.75, rx: 2 }) + line(x + 4, 58, 24, 0.35) + line(x + 4, 64, 16, 0.25)).join('') + hr(10, 80, 140, 0.2),
  quote: () => stroke('M40 24c-5 2-7 6-7 11h6v7h-8v-8c0-6 3-10 9-12M54 24c-5 2-7 6-7 11h6v7h-8v-8c0-6 3-10 9-12', 0.9, 1.4, ACC) + line(34, 50, 92, 0.6, 4.5) + line(34, 58, 92, 0.6, 4.5) + line(34, 66, 60, 0.6, 4.5) + circle(40, 82, 5, 0.3) + line(50, 79, 30, 0.6) + line(50, 84, 22, 0.3),
  faq: () => head(10, 10, 56) + [26, 42, 58, 74].map((y, i) => box(10, y, 140, i === 0 ? 14 : 13, { fill: i === 0 ? 0.08 : 0.03 }) + line(16, y + 5.5, [70, 56, 80, 48][i], 0.6) + plus(142, y + 7, i === 0)).join(''),
  cta: () => rect(10, 22, 140, 56, { fill: ACC, o: 0.18, rx: 4 }) + head(28, 36, 104) + line(42, 48, 76, 0.4) + rect(56, 58, 24, 8, { fill: ACC, rx: 2 }) + btn(83, 58, 22, 8, true),
  pricing: () => secHead(6) + grid(3, 14, 40, 6).map((x, i) => box(x, i === 1 ? 24 : 28, 40, i === 1 ? 70 : 64, { stroke: i === 1 ? 0 : 0.4, fill: i === 1 ? 0.1 : 0.04 }) + (i === 1 ? `<rect x="${x + 0.6}" y="24.6" width="38.8" height="68.8" rx="2.5" stroke="${ACC}" stroke-width="1.2"/>` : '') + line(x + 6, (i === 1 ? 30 : 34), 18, 0.6) + rect(x + 6, (i === 1 ? 37 : 41), 20, 9, { o: 0.75, rx: 2 }) + [0, 1, 2].map(k => dot(x + 7.5, (i === 1 ? 55 : 58) + k * 7, 1.4) + line(x + 11, (i === 1 ? 53.5 : 56.5) + k * 7, 22, 0.35)).join('') + btn(x + 6, (i === 1 ? 81 : 82), 28, 6, i !== 1)).join(''),
  steps: () => secHead(8) + stroke('M26 46H134', 0.35, 1) + grid(4, 14, 24, 14).map((x, i) => `<circle cx="${x + 12}" cy="46" r="7" fill="${ACC}" opacity="${i === 0 ? 1 : 0.7}"/>` + line(x + 2, 60, 20, 0.65, 3.5) + para(x, 68, 24, 2, 5.5, 0.3)).join(''),
  tabs: () => head(10, 10, 52) + [10, 42, 74].map((x, i) => rect(x, 26, 30, 9, { o: i === 0 ? 0 : 0.1, rx: 2 }) + line(x + 5, 29, 20, i === 0 ? 0.8 : 0.4) + (i === 0 ? rect(x, 34, 30, 1.5, { fill: ACC, rx: 0.75 }) : '')).join('') + hr(10, 35.5, 140, 0.25) + para(10, 44, 70, 5) + img(94, 44, 56, 40),
  video: () => img(18, 10, 124, 70, { o: 0.2 }) + `<circle cx="80" cy="45" r="11" fill="${ACC}"/><path d="M76.5 39.5v11l9-5.5z" fill="#fff"/>` + line(18, 86, 60, 0.35),
  logos: () => secHead(14, 50) + grid(5, 10, 24, 5).map((x, i) => rect(x, 46, 24, 12, { o: 0.22, rx: 2 }) + (i % 2 ? circle(x + 7, 52, 3, 0.4) + line(x + 12, 50.5, 9, 0.45) : line(x + 4, 50.5, 16, 0.45))).join('') + hr(10, 72, 140, 0.2),
  contact: () => head(10, 10, 50) + [24, 38, 52].map(y => ico(10, y, 8) + line(22, y + 1, 40, 0.6) + line(22, y + 6, 28, 0.3)).join('') + line(10, 70, 30, 0.6) + para(10, 77, 50, 2, 6, 0.3) + img(78, 10, 72, 46, { o: 0.15 }) + dot(110, 32, 3.5) + box(78, 62, 72, 28) + line(84, 68, 40, 0.3) + line(84, 75, 52, 0.3) + btn(84, 81, 20, 6),
  downloads: () => head(10, 10, 56) + [24, 40, 56, 72].map((y, i) => box(10, y, 140, 13) + rect(15, y + 3, 6, 7, { fill: ACC, o: 0.75, rx: 1 }) + line(26, y + 3.5, [56, 44, 64, 50][i], 0.6) + line(26, y + 8, 30, 0.3) + stroke(`M140 ${y + 3.5}v5m-2.5 -2.5l2.5 2.5l2.5 -2.5`, 0.7, 1.2)).join(''),
  map: () => rect(10, 10, 140, 80, { o: 0.13, rx: 3 }) + stroke('M10 64C40 58 52 40 80 42s50 20 70 8M44 10c4 24 -6 50 6 80M112 10c-6 30 8 52 0 80', 0.35, 2) + `<path d="M80 30c-6 0-10 4.5-10 10c0 7 10 16 10 16s10-9 10-16c0-5.5-4-10-10-10z" fill="${ACC}"/>` + `<circle cx="80" cy="40" r="3.5" fill="#fff"/>` + box(16, 70, 46, 14, { fill: 0.25, stroke: 0 }) + line(21, 74, 30, 0.7) + line(21, 79, 20, 0.4),
  cards: () => secHead(6) + grid(3, 10, 42, 7).map(x => box(x, 26, 42, 66) + img(x + 1, 27, 40, 26, { rx: 1.5 }) + line(x + 5, 58, 28, 0.7, 4) + para(x + 5, 66, 31, 2, 5.5, 0.3) + line(x + 5, 82, 14, 0.8)).join(''),
  team: () => secHead(8) + grid(4, 12, 29, 8).map(x => circle(x + 14.5, 44, 11, 0.22) + circle(x + 14.5, 41, 4, 0.3) + line(x + 3, 62, 23, 0.7, 3.5) + line(x + 6, 69, 17, 0.35)).join(''),
  bento: () => img(10, 10, 66, 52) + box(80, 10, 70, 24, { fill: 0.08, stroke: 0 }) + ico(86, 15) + line(97, 16, 40, 0.6) + line(86, 26, 50, 0.3) + img(80, 38, 33, 24) + rect(117, 38, 33, 24, { fill: ACC, o: 0.25, rx: 2 }) + line(122, 46, 20, 0.6) + box(10, 66, 44, 24, { fill: 0.08, stroke: 0 }) + line(15, 72, 30, 0.6) + line(15, 79, 22, 0.3) + img(58, 66, 92, 24),
  scrolly: () => img(84, 10, 66, 80) + [12, 40, 68].map((y, i) => box(10, y, 64, 22, { fill: i === 1 ? 0.12 : 0.03, stroke: i === 1 ? 0.5 : 0.2 }) + line(16, y + 5, 36, i === 1 ? 0.75 : 0.4, 3.5) + line(16, y + 12, 50, 0.3) + line(16, y + 17, 34, 0.3)).join('') + stroke('M78 28v44', 0.4, 1) + stroke('M76 66l2 4l2 -4', 0.5, 1),
  specs: () => head(10, 10, 50) + [24, 34, 44, 54, 64, 74].map((y, i) => (i % 2 ? '' : rect(10, y, 86, 10, { o: 0.07, rx: 1 })) + line(14, y + 3.5, 26, 0.55) + line(52, y + 3.5, [30, 22, 36, 18, 28, 24][i], 0.35)).join('') + img(104, 24, 46, 60),
  principles: () => head(10, 8, 52) + [22, 46, 70].map((y, i) => rect(10, y, 14, 12, { fill: ACC, o: 0.75, rx: 2 }) + line(30, y + 1, 50, 0.7, 4) + para(30, y + 9, 110, 2, 5.5, 0.3) + (i < 2 ? hr(10, y + 21, 140, 0.2) : '')).join(''),
  // Kern-Blöcke (app/Blocks)
  data_list: () => head(10, 8, 46) + `<rect x="10.5" y="22.5" width="29" height="5" rx="2.5" stroke="${ACC}"/>` + line(16, 23.5, 18, 0.5) + rect(44, 22, 30, 6, { o: 0.1, rx: 3 }) + line(50, 23.5, 18, 0.5) + grid(3, 10, 42, 7).map(x => box(x, 34, 42, 56) + img(x + 1, 35, 40, 22, { rx: 1.5 }) + line(x + 5, 62, 26, 0.7, 3.5) + line(x + 5, 69, 31, 0.3) + line(x + 5, 75, 20, 0.3) + rect(x + 5, 81, 14, 4, { fill: ACC, o: 0.3, rx: 2 })).join(''),
  entry_nav: () => hr(10, 30, 140, 0.25) + box(10, 38, 66, 26) + chev(19, 51, -1) + line(28, 45, 22, 0.35) + line(28, 52, 40, 0.65, 4) + box(84, 38, 66, 26) + chev(141, 51, 1) + line(110, 45, 22, 0.35) + line(92, 52, 40, 0.65, 4) + hr(10, 72, 140, 0.25),
  data_fields: () => head(10, 10, 72, 7) + line(10, 22, 40, 0.35) + [32, 44, 56, 68, 80].map((y, i) => line(10, y, 30, 0.55) + line(48, y, [60, 44, 72, 38, 52][i], 0.3) + (i < 4 ? hr(10, y + 7, 102, 0.15) : '')).join('') + img(118, 30, 32, 40),
  entry_blocks: () => `<rect x="12.5" y="16.5" width="135" height="67" rx="4" stroke="${ACC}" stroke-dasharray="4 3" opacity="0.8"/>` + box(22, 26, 116, 14, { fill: 0.1, stroke: 0 }) + line(28, 31, 50, 0.5) + box(22, 44, 56, 30, { fill: 0.1, stroke: 0 }) + img(82, 44, 56, 30, { o: 0.14 }) + line(28, 50, 34, 0.5) + line(28, 57, 42, 0.3),
  data_form: () => head(10, 8, 56) + [22, 40].map(y => line(10, y, 30, 0.5) + box(10, y + 5, 66, 10, { fill: 0.03 })).join('') + [22, 40].map(y => line(84, y, 30, 0.5) + box(84, y + 5, 66, 10, { fill: 0.03 })).join('') + line(10, 58, 30, 0.5) + box(10, 63, 140, 18, { fill: 0.03 }) + btn(10, 86, 30, 8),
  job_facts: () => box(10, 10, 140, 80, { fill: 0.05 }) + [18, 32, 46, 60].map((y, i) => ico(18, y, 7) + line(30, y, 26, 0.35) + line(30, y + 5, [40, 30, 46, 34][i], 0.65) + ico(86, y, 7) + line(98, y, 26, 0.35) + line(98, y + 5, [30, 44, 26, 38][i], 0.65)).join('') + btn(18, 76, 40, 8),
  job_apply: () => line(10, 10, 40, 0.5) + head(10, 15, 80, 7) + [30, 46].map(y => line(10, y, 26, 0.5) + box(10, y + 4, 66, 10, { fill: 0.03 }) + line(84, y, 26, 0.5) + box(84, y + 4, 66, 10, { fill: 0.03 })).join('') + box(10, 64, 140, 14, { fill: 0.08, stroke: 0.3 }) + stroke('M76 67v6m-3 -3l3 -3l3 3', 0.6, 1.2) + btn(10, 84, 36, 8),
  calendar: () => head(10, 8, 40) + chev(132, 11, -1) + chev(144, 11, 1) + rect(10, 22, 140, 70, { o: 0.07, rx: 2 })
    + stroke([30, 50, 70, 90, 110, 130].map(x => `M${x} 22v70`).join('') + [36, 50, 64, 78].map(y => `M10 ${y}h140`).join(''), 0.25, 0.8)
    + [[50, 36], [110, 36], [130, 50], [70, 64]].map(([x, y]) => rect(x + 2, y + 7, 16, 3, { fill: ACC, o: 0.85, rx: 1 })).join(''),
  upcoming: () => head(10, 8, 50) + [24, 46, 68].map(y => rect(10, y, 18, 18, { fill: ACC, o: 0.2, rx: 2 }) + line(14, y + 3, 10, 0.8, 4) + line(15, y + 11, 8, 0.5) + line(34, y + 2, 70, 0.7, 4) + line(34, y + 10, 46, 0.3) + hr(34, y + 20, 116, 0.15)).join(''),
  push_subscribe: () => box(24, 16, 112, 68, { fill: 0.05 }) + `<path d="M80 27c-6 0-10 4.5-10 10v7l-3 4h26l-3-4v-7c0-5.5-4-10-10-10z" fill="${ACC}" opacity="0.85"/>` + circle(80, 51.5, 2.5, 0.6) + line(50, 60, 60, 0.6, 4) + line(56, 67, 48, 0.3) + btn(66, 73, 28, 7),
  not_found: () => rect(52, 14, 56, 18, { o: 0.75, rx: 3 }) + line(40, 38, 80, 0.4) + [48, 56, 64].map(y => dot(44, y + 1.5, 1.4) + line(49, y, 52, 0.5)).join('') + box(40, 72, 60, 11, { fill: 0.03 }) + btn(102, 72, 18, 11),
  chart: () => head(10, 8, 46) + stroke('M14 86H150M14 22V86', 0.35, 1) + [22, 40, 58, 76, 94, 112, 130].map((x, i) => rect(x, 86 - [30, 44, 26, 52, 38, 58, 46][i], 12, [30, 44, 26, 52, 38, 58, 46][i], { fill: i === 5 ? ACC : INK, o: i === 5 ? 0.9 : 0.3, rx: 1.5 })).join(''),
  gallery: () => [[10, 10, 50, 38], [64, 10, 40, 38], [108, 10, 42, 38], [10, 52, 34, 38], [48, 52, 56, 38], [108, 52, 42, 38]].map(([x, y, w, h]) => img(x, y, w, h)).join(''),
  live_gallery: () => [[10, 18, 44, 34], [58, 18, 44, 34], [106, 18, 44, 34], [10, 56, 44, 34], [58, 56, 44, 34]].map(([x, y, w, h]) => img(x, y, w, h)).join('') + `<rect x="106.5" y="56.5" width="43" height="33" rx="2" stroke="${ACC}" stroke-dasharray="3 2.5"/>` + stroke('M128 67v12m-6 -6h12', 0.9, 1.4, ACC) + dot(14, 10, 2.5) + line(19, 8.5, 20, 0.6),
  live_text: () => dot(14, 12, 2.5) + line(19, 10.5, 22, 0.6) + [22, 44, 66].map((y, i) => rect(10, y, 24, 4, { o: 0.5, rx: 2 }) + line(40, y, [94, 80, 100][i], i === 0 ? 0.75 : 0.45, 4) + line(40, y + 8, [70, 86, 60][i], 0.3) + (i < 2 ? hr(10, y + 17, 140, 0.15) : '')).join(''),
  slideshow: () => img(22, 10, 116, 68) + `<circle cx="14" cy="44" r="6" fill="${INK}" opacity="0.2"/>` + chev(13, 44, -1, 0.8) + `<circle cx="146" cy="44" r="6" fill="${INK}" opacity="0.2"/>` + chev(145, 44, 1, 0.8) + [70, 77, 84, 91].map((x, i) => `<circle cx="${x}" cy="88" r="2" fill="${i === 0 ? ACC : INK}" opacity="${i === 0 ? 1 : 0.4}"/>`).join(''),
  stack_cards: () => box(30, 14, 100, 60, { fill: 0.04, stroke: 0.25 }) + box(24, 22, 112, 62, { fill: 0.06, stroke: 0.3 }) + box(18, 30, 124, 62, { fill: 0.1, stroke: 0.45 }) + img(24, 36, 52, 50) + line(84, 42, 46, 0.7, 4) + para(84, 51, 50, 3, 6, 0.3) + btn(84, 74, 22, 6),
  people: () => head(10, 8, 46) + grid(3, 10, 42, 7).map(x => box(x, 22, 42, 68) + img(x + 1, 23, 40, 30, { rx: 1.5 }) + line(x + 5, 58, 28, 0.7, 4) + line(x + 5, 65, 22, 0.35) + ico(x + 5, 74, 5) + line(x + 13, 75, 22, 0.35) + ico(x + 5, 82, 5) + line(x + 13, 83, 18, 0.35)).join(''),
  partners: () => head(10, 8, 46) + [22, 56].map(y => grid(4, 10, 32, 4).map((x, i) => box(x, y, 32, 30, { fill: 0.04 }) + ((i + y) % 2 ? line(x + 7, y + 13.5, 18, 0.45, 4) : circle(x + 16, y + 15, 6, 0.35))).join('')).join(''),
  dials: () => head(10, 8, 46) + grid(3, 14, 36, 12).map((x, i) => { const cx = x + 18, cy = 50, R = 15; const a = [0.7, 0.45, 0.85][i] * Math.PI * 1.5 - Math.PI * 1.25, ex = cx + R * Math.cos(a), ey = cy + R * Math.sin(a); return stroke(`M${r(cx - R * 0.707)} ${r(cy + R * 0.707)}A${R} ${R} 0 1 1 ${r(cx + R * 0.707)} ${r(cy + R * 0.707)}`, 0.25, 3) + `<path d="M${r(cx - R * 0.707)} ${r(cy + R * 0.707)}A${R} ${R} 0 ${a > Math.PI * 0.25 ? 1 : 0} 1 ${r(ex)} ${r(ey)}" stroke="${ACC}" stroke-width="3" stroke-linecap="round"/>` + rect(cx - 7, cy - 4, 14, 7, { o: 0.75, rx: 1.5 }) + line(x + 6, 74, 24, 0.5) + line(x + 9, 80, 18, 0.3); }).join(''),
  glossary: () => head(10, 8, 40) + box(98, 7, 52, 10, { fill: 0.03 }) + Array.from({ length: 13 }, (_, i) => rect(10 + i * 10.8, 24, 8, 8, { fill: i === 2 ? ACC : INK, o: i === 2 ? 0.9 : 0.18, rx: 1.5 })).join('') + rect(10, 40, 10, 9, { o: 0.75, rx: 1.5 }) + [54, 68, 82].map(y => line(10, y, 34, 0.7, 3.5) + line(50, y, 100, 0.3)).join(''),
  layout: () => [[10, 44], [58, 44], [106, 44]].map(([x], i) => `<rect x="${x + 0.5}" y="14.5" width="${43}" height="71" rx="3" stroke="${ACC}" stroke-dasharray="4 3" opacity="0.7"/>` + (i === 0 ? box(x + 5, 20, 34, 18, { fill: 0.1, stroke: 0 }) + line(x + 9, 25, 22, 0.5) + line(x + 9, 31, 16, 0.3) : i === 1 ? img(x + 5, 20, 34, 26) : '') + `<circle cx="${x + 22}" cy="${i === 2 ? 50 : 72}" r="5" fill="${ACC}" opacity="0.85"/>` + stroke(`M${x + 22} ${(i === 2 ? 50 : 72) - 2.5}v5m-2.5 -2.5h5`, 1, 1.2, '#fff')).join(''),
};

// ---------------------------------------------------------------- Kit-eigene Blöcke (kits/{kit}/assets/img/previews)
const KITS = {
  editorial: {
    figure: () => img(10, 10, 140, 66) + line(10, 82, 90, 0.5) + line(10, 88, 40, 0.3),
    teasers: () => head(10, 8, 46) + box(10, 22, 70, 68) + img(11, 23, 68, 36, { rx: 1.5 }) + eyebrow(15, 64) + line(15, 70, 56, 0.75, 4.5) + para(15, 79, 58, 2, 5.5, 0.3) + [22, 46, 70].map(y => img(86, y, 22, 18) + eyebrow(112, y + 1, 12) + line(112, y + 6, 38, 0.65) + line(112, y + 12, 28, 0.3)).join(''),
    article: () => eyebrow(30, 8) + head(30, 13, 100, 8) + line(30, 26, 90, 0.45, 3.5) + circle(34, 36, 3.5, 0.3) + line(41, 34.5, 34, 0.4) + img(30, 44, 100, 30) + para(30, 80, 100, 2, 6, 0.35) + box(136, 44, 18, 30, { fill: 0.05, stroke: 0.25 }) + [50, 56, 62].map(y => line(139, y, 12, 0.35, 2)).join(''),
  },
  foto: {
    photo_hero: () => img(0, 0, 160, 100, { o: 0.22, rx: 4 }) + rect(14, 62, 84, 8, { o: 0.85, rx: 2 }) + line(14, 74, 56, 0.6) + [70, 77, 84].map((x, i) => `<circle cx="${x}" cy="90" r="1.8" fill="${i === 0 ? ACC : INK}" opacity="${i === 0 ? 1 : 0.5}"/>`).join(''),
    photo_grid: () => [[10, 10, 46, 30], [60, 10, 30, 44], [94, 10, 56, 30], [10, 44, 46, 46], [60, 58, 30, 32], [94, 44, 56, 46]].map(([x, y, w, h]) => img(x, y, w, h)).join(''),
    moments: () => img(10, 10, 74, 52) + img(88, 10, 62, 24) + img(88, 38, 29, 24) + img(121, 38, 29, 24) + img(10, 66, 40, 24) + rect(54, 66, 30, 24, { o: 0.1, rx: 2 }) + line(58, 74, 20, 0.6) + line(58, 80, 14, 0.3) + img(88, 66, 62, 24),
    series_index: () => grid(3, 10, 42, 7).map(x => img(x, 10, 42, 30) + line(x, 44, 28, 0.7, 3.5) + line(x, 50, 16, 0.35)).join('') + grid(3, 10, 42, 7).map(x => img(x, 58, 42, 30)).join(''),
    series_head: () => img(10, 8, 140, 48) + head(10, 64, 90, 8) + [10, 44, 78].map(x => line(x, 78, 26, 0.35) + line(x, 84, 20, 0.6)).join('') + para(112, 64, 38, 4, 6, 0.3),
  },
  galerie: {
    exhibition_feature: () => img(0, 0, 160, 100, { o: 0.22, rx: 4 }) + rect(14, 52, 26, 6, { fill: ACC, rx: 3 }) + head(14, 62, 96, 9) + line(14, 76, 60, 0.6) + line(14, 83, 42, 0.4),
    exhibitions_list: () => head(10, 8, 46) + [22, 39, 56, 73].map((y, i) => line(10, y + 4, 22, 0.4) + line(40, y + 2, [70, 56, 80, 62][i], 0.75, 4.5) + line(40, y + 10, 40, 0.3) + (i === 0 ? rect(128, y + 3, 22, 6, { fill: ACC, o: 0.85, rx: 3 }) : '') + hr(10, y + 16, 140, 0.18)).join(''),
    exhibition_detail: () => img(10, 8, 140, 40) + rect(10, 54, 22, 5, { fill: ACC, o: 0.85, rx: 2.5 }) + head(10, 63, 80, 8) + para(10, 76, 80, 2, 6, 0.3) + [54, 64, 74, 84].map(y => line(104, y, 18, 0.35) + line(126, y, 24, 0.6)).join(''),
    artists_index: () => [12, 28, 44, 60, 76].map((y, i) => line(10, y, [74, 58, 86, 64, 70][i], i === 1 ? 0.85 : 0.6, 7)).join('') + img(100, 20, 50, 62) + stroke('M68 31.5h28', 0.4, 1),
    artist_profile: () => img(10, 10, 56, 72) + head(76, 12, 74, 9) + line(76, 27, 46, 0.4) + para(76, 38, 74, 5, 6.5, 0.3) + btn(76, 74, 30, 8, true),
    artworks: () => [[10, 10, 32, 42], [48, 10, 46, 30], [100, 10, 50, 42], [10, 62, 46, 22], [62, 46, 32, 38], [100, 58, 50, 26]].map(([x, y, w, h]) => img(x, y, w, h) + `<circle cx="${x + 2.5}" cy="${y + h + 4}" r="1.6" fill="${ACC}"/>` + line(x + 6, y + h + 2.8, w * 0.55, 0.45, 2.5)).join(''),
    artwork_detail: () => rect(10, 8, 82, 84, { o: 0.07, rx: 3 }) + img(22, 16, 58, 68) + head(100, 14, 50, 7) + line(100, 26, 34, 0.4) + [38, 45, 52, 59].map(y => line(100, y, 44, 0.3)).join('') + `<circle cx="102.5" cy="70" r="2" fill="${ACC}"/>` + line(107, 68.5, 24, 0.5) + btn(100, 78, 34, 8),
    visit: () => head(10, 10, 50) + grid(3, 10, 42, 7).map(x => box(x, 26, 42, 64, { fill: 0.04 }) + line(x + 5, 32, 26, 0.7, 4) + [42, 48, 54, 60].map(y => line(x + 5, y, 14, 0.35) + line(x + 22, y, 14, 0.5)).join('') + dot(x + 7, 76, 2) + line(x + 12, 74.5, 22, 0.4)).join(''),
  },
  praxis: {
    quick_contact: () => eyebrow(10, 28) + head(10, 34, 56) + para(10, 46, 58, 3) + box(84, 12, 66, 76, { fill: 0.08 }) + [20, 40, 60].map(y => ico(92, y, 9) + line(106, y + 1, 32, 0.65, 3.5) + line(106, y + 7, 24, 0.3)).join(''),
    teaser_tiles: () => line(10, 12, 90, 0.4) + grid(4, 10, 32, 4).map((x, i) => rect(x, 24, 32, 58, { fill: i === 0 ? ACC : INK, o: i === 0 ? 0.3 : 0.12, rx: 3 }) + line(x + 5, 62, 20, 0.8, 4) + line(x + 5, 70, 16, 0.4) + chev(x + 25, 76, 1, 0.7)).join(''),
    text_video: () => img(10, 14, 72, 52, { o: 0.2 }) + `<circle cx="46" cy="40" r="8" fill="${ACC}"/><path d="M43.5 36v8l6.5-4z" fill="#fff"/>` + eyebrow(92, 20) + head(92, 26, 54) + para(92, 39, 58, 4),
    services: () => head(10, 8, 50) + [22, 46, 70].map((y, i) => [10, 84].map((x, k) => rect(x, y, 12, 12, { fill: ACC, o: 0.8, rx: 6 }) + line(x + 18, y + 1, 40, 0.7, 4) + line(x + 18, y + 9, 46, 0.3) + line(x + 18, y + 15, 30, 0.3)).join('')).join(''),
    doctors: () => head(10, 8, 56) + [10, 84].map(x => box(x, 22, 66, 68, { fill: 0.04 }) + img(x + 5, 27, 24, 24) + line(x + 34, 28, 26, 0.4) + line(x + 34, 35, 28, 0.75, 4) + line(x + 34, 43, 18, 0.3) + para(x + 5, 58, 56, 3, 6, 0.3) + rect(x + 5, 78, 30, 6, { fill: ACC, o: 0.25, rx: 3 })).join(''),
    image_wide: () => img(0, 18, 160, 56, { rx: 2 }) + line(10, 80, 50, 0.65, 3.5) + line(64, 80, 40, 0.3, 3.5),
    notice: () => [[16, ACC], [52, INK]].map(([y, c]) => `<rect x="10" y="${y}" width="140" height="30" rx="3" fill="${c}" opacity="0.14"/>` + `<rect x="10" y="${y}" width="3" height="30" rx="1.5" fill="${c}" opacity="0.9"/>` + `<circle cx="24" cy="${y + 10}" r="4" fill="${c}" opacity="0.85"/>` + line(34, y + 7, 50, 0.75, 4) + line(34, y + 16, 100, 0.35) + line(34, y + 22, 70, 0.35)).join(''),
    job: () => box(20, 14, 120, 72, { fill: 0.05 }) + rect(28, 22, 22, 6, { fill: ACC, o: 0.85, rx: 3 }) + rect(54, 22, 18, 6, { o: 0.25, rx: 3 }) + head(28, 34, 70, 7) + para(28, 47, 100, 3, 6, 0.3) + btn(28, 70, 32, 8),
    text_columns: () => head(10, 12, 44, 7) + head(10, 22, 30, 7) + para(66, 12, 84, 4, 6) + para(66, 42, 84, 3, 6) + hr(10, 70, 140, 0.2) + line(10, 78, 30, 0.6) + para(66, 78, 84, 2, 6),
  },
};

// ---------------------------------------------------------------- Schreiben
const only = process.argv.slice(2);
let n = 0;
const write = (file, fn, name) => {
  if (only.length && !only.includes(name)) return;
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, svg(fn()));
  n++;
};
for (const [name, fn] of Object.entries(CORE)) write(path.join(ROOT, 'resources/img/previews', name + '.svg'), fn, name);
for (const [kit, set] of Object.entries(KITS)) {
  if (!fs.existsSync(path.join(ROOT, 'kits', kit))) continue;
  for (const [name, fn] of Object.entries(set)) write(path.join(ROOT, 'kits', kit, 'assets/img/previews', name + '.svg'), fn, name);
}
console.log(`${n} Vorschauen geschrieben`);
