/*
 * Grafik-Editor – „Text in Kurven umwandeln“ mit fontkit (MIT, nachgeladen: public/assets/js/grafik-fonts.mjs).
 *
 * Schriftdateien: die selbst gehosteten WOFF2 des Schriften-Managers (boot.fontFiles: Familie → Dateien je Zeichensatz,
 * Schnitt, Stil; variable Schriften mit Achse wght) und Lato der Verwaltung. fontkit liest WOFF2 (Brotli) und Varianten
 * direkt – keine Umwandlung auf dem Server nötig. Systemschriften (sans-serif …) haben keine Datei → Meldung.
 *
 * Lage: die Positionen jedes Zeichens nimmt der Browser (getStartPositionOfChar, getRotationOfChar) – Laufweite,
 * Unterschneidung, Zeilen, Ausrichtung, Text auf Pfad stimmen so mit der Anzeige überein. Die Glyphen (auch Ligaturen)
 * kommen von fontkit (layout je Lauf), jede an der Position ihres ersten Zeichens. Ergebnis: ein Pfad je Lauf (eigene
 * Farbe/Kontur bleibt), bei mehreren Läufen in einer Gruppe mit den Eigenschaften des Textes. MIT
 */
import { NS, num, pt, docMatrix, parentMatrix, fmtMatrix, pathToString, parsePath } from './geom.js';
import { getProp } from './doc.js';
import { t } from '../_i18n.js';
import { firstFamily } from './fontpicker.js';

let fkP = null;
const fontCache = new Map();   // url → Promise<font>

function loadFontkit(url) {
  if (!url) return Promise.reject(new Error('fontkit'));
  return (fkP ||= import(url).catch(e => { fkP = null; throw e; }));
}

const bytesCache = new Map();   // url → Promise<Uint8Array>

/**
 * Schrift aus einer Datei, bei variablen Schriften als eigene Instanz je Gewicht. fontkit.getVariation() baut aus WOFF2
 * eine TTF-Instanz (liest dann die komprimierten Tabellen falsch) – daher eine frische WOFF2-Instanz mit gesetzten
 * Achsenwerten (variationCoords, wie getVariation sie berechnet).
 */
async function fontAt(ed, url, coords = null) {
  if (typeof coords === 'number') coords = { wght: coords };
  const key = url + '|' + (coords ? JSON.stringify(coords) : '');
  if (!fontCache.has(key)) {
    fontCache.set(key, (async () => {
      const fk = await loadFontkit(ed.boot.modules?.fonts);
      if (!bytesCache.has(url)) bytesCache.set(url, fetch(url, { credentials: 'same-origin' }).then(r => { if (!r.ok) throw new Error('font ' + r.status); return r.arrayBuffer(); }).then(b => new Uint8Array(b)));
      const font = fk.create(await bytesCache.get(url));
      if (coords && font.fvar) {
        // WOFF2: Glyf-Tabelle erst ohne Varianten entpacken (fontkit stolpert dort sonst über zusammengesetzte Glyphen),
        // danach die Achsenwerte setzen und den Varianten-Rechner neu erzeugen lassen
        Object.defineProperty(font, '_variationProcessor', { value: null, configurable: true, writable: true });
        font.getGlyph(0);
        font.variationCoords = font.fvar.axis.map(a => { const v = coords[a.axisTag.trim()]; return v != null ? Math.max(a.minValue, Math.min(a.maxValue, v)) : a.defaultValue; });
        delete font._variationProcessor;
        font._glyphs = {};
        patchWoff2Variations(font);
      }
      return font;
    })().catch(e => { fontCache.delete(key); bytesCache.delete(url); throw e; }));
  }
  return fontCache.get(key);
}

/**
 * fontkit wendet bei WOFF2 die Varianten (gvar) nicht auf einfache Glyphen an (WOFF2Glyph._decode liefert die
 * vorab entpackten Punkte unverändert). Nachgerüstet: Punkte kopieren, vier Phantom-Punkte anhängen (nur für die
 * Indizes der Deltas), transformPoints wie in TTFGlyph._decode. Zusammengesetzte Glyphen: Bauteile werden so variiert.
 */
function patchWoff2Variations(font) {
  if (font.type !== 'WOFF2') return;
  const proto = Object.getPrototypeOf(font.getGlyph(0));
  if (proto.__geVar) return;
  const orig = proto._decode;
  proto._decode = function () {
    const g = orig.call(this);
    const vp = this._font.variationCoords && this._font._variationProcessor;
    if (!g || !vp || !g.points || g.numberOfContours <= 0) return g;
    const P = Object.getPrototypeOf(g.points[0]);
    const pts = g.points.map(p => Object.assign(Object.create(P), p));
    for (let i = 0; i < 4; i++) pts.push(Object.assign(Object.create(P), { onCurve: false, endContour: true, x: 0, y: 0 }));
    vp.transformPoints(this.id, pts);
    pts.length -= 4;
    return { ...g, points: pts };
  };
  proto.__geVar = true;
}

/** font-variation-settings ('"wdth" 87, "opsz" 24') ↔ { wdth: 87, opsz: 24 } */
export function parseVariations(v) {
  const o = {};
  for (const m of String(v || '').matchAll(/["']([A-Za-z0-9 ]{4})["']\s+(-?[\d.]+)/g)) o[m[1]] = parseFloat(m[2]);
  return o;
}
export const fmtVariations = o => Object.entries(o || {}).map(([k, v]) => `'${k}' ${num(v, 2)}`).join(', ');

/**
 * Angaben zu einer Schrift aus ihrer Datei (latin, Normalschnitt): Achsen variabler Schriften und OpenType-Merkmale
 * (GSUB/GPOS) mit den Namen aus der Schrift (Stilsätze ss01–ss20, Zeichenvarianten cv01–cv99). null ohne Datei.
 * @return {Promise<{axes: Object, features: Map<string, string|null>, font}|null>}
 */
const infoCache = new Map();
export function fontInfo(ed, family) {
  const files = filesFor(ed, family, 400, 'normal');
  if (!files?.length) return Promise.resolve(null);
  const url = files[0].url;
  if (!infoCache.has(url)) infoCache.set(url, fontAt(ed, url).then(font => {
    const axes = {};
    for (const [tag, a] of Object.entries(font.variationAxes || {})) axes[tag.trim()] = { min: a.min, max: a.max, default: a.default, name: a.name };
    const features = new Map();
    for (const tb of [font.GSUB, font.GPOS]) for (const f of tb?.featureList || []) {
      if (features.has(f.tag) && features.get(f.tag)) continue;
      let name = null;
      try {
        const id = f.feature?.featureParams?.nameID;
        if (id) { const r = font.name?.records?.fontFeatures?.[id]; name = r ? (r.de || r.en || Object.values(r)[0]) : null; }
      } catch { /* ohne Namen */ }
      features.set(f.tag, name);
    }
    return { axes, features, font };
  }).catch(e => { infoCache.delete(url); throw e; }));
  return infoCache.get(url);
}

const normW = w => w === 'bold' ? 700 : w === 'bolder' ? 700 : w === 'lighter' ? 300 : w === 'normal' || !w ? 400 : parseInt(w, 10) || 400;

/** Dateien einer Familie für Schnitt und Stil, nach Zeichensatz (latin zuerst) */
function filesFor(ed, family, weight, style) {
  const f = (ed.boot.fontFiles || {})[family] || Object.entries(ed.boot.fontFiles || {}).find(([k]) => k.toLowerCase() === family.toLowerCase())?.[1];
  if (!f) return null;
  let cand = f.files.filter(x => x.style === style);
  if (!cand.length) cand = f.files.filter(x => x.style === 'normal');
  const vari = cand.filter(x => x.weight === null);
  let pick;
  if (vari.length) pick = vari.map(x => ({ ...x, coords: { wght: weight } }));
  else {
    const ws = [...new Set(cand.map(x => x.weight))].sort((a, b) => Math.abs(a - weight) - Math.abs(b - weight) || b - a);
    pick = cand.filter(x => x.weight === ws[0]);
  }
  return pick.sort((a, b) => (a.subset === 'latin' ? -1 : 0) - (b.subset === 'latin' ? -1 : 0));
}

/** Schrift (je Zeichensatz) für ein Element: [{font, subset}] */
async function fontsFor(ed, el) {
  const stack = getProp(el, 'font-family') || 'sans-serif';
  const fams = stack.split(',').map(s => s.trim().replace(/^["']|["']$/g, '')).filter(Boolean);
  const weight = normW(getProp(el, 'font-weight')), style = /italic|oblique/.test(getProp(el, 'font-style') || '') ? 'italic' : 'normal';
  for (const fam of fams) {
    const files = filesFor(ed, fam, weight, style);
    if (!files) continue;
    const out = [];
    // weitere Achsen (Breite, optische Größe …) aus font-variation-settings
    const extra = parseVariations(getProp(el, 'font-variation-settings'));
    for (const f of files) out.push(await fontAt(ed, f.url, f.coords || Object.keys(extra).length ? { ...(f.coords || {}), ...extra } : null));
    return { fonts: out, family: fam };
  }
  return { fonts: null, family: firstFamily(stack) };
}

/**
 * Leaf-Knoten des Textes in Zeichenreihenfolge mit ihrem Element (für Stil je Zeichen). Leerraum wird wie im Browser
 * zusammengefasst (xml:space default) – die Anzahl wird mit getNumberOfChars geprüft.
 */
function charsOf(text) {
  const pre = text.getAttribute('xml:space') === 'preserve' || text.getAttributeNS('http://www.w3.org/XML/1998/namespace', 'space') === 'preserve';
  const out = [];
  const walk = n => {
    for (const c of n.childNodes) {
      if (c.nodeType === 3) {
        let s = c.data;
        if (!pre) s = s.replace(/[\r\n]+/g, '').replace(/\t/g, ' ');
        for (const ch of s) out.push({ ch, el: c.parentNode });
      } else if (c.nodeType === 1 && ['tspan', 'textPath', 'a'].includes(c.localName)) walk(c);
    }
  };
  walk(text);
  if (pre) return out;
  // Leerraum: Anfang/Ende weg, mehrere zu einem
  const res = [];
  for (const c of out) { if (c.ch === ' ' && (!res.length || res[res.length - 1].ch === ' ')) continue; res.push(c); }
  while (res.length && res[res.length - 1].ch === ' ') res.pop();
  return res;
}

/** Ein Text-Element → Pfad (bzw. Gruppe aus Pfaden je Lauf). Rückgabe: { el } oder { error } */
export async function outlineText(ed, text) {
  const chars = charsOf(text);
  const n = text.getNumberOfChars();
  if (chars.reduce((k, c) => k + c.ch.length, 0) !== n && chars.length !== n) {
    // Zählung weicht ab (ungewöhnlicher Leerraum): Zeichen ohne Leerraum-Regel verwenden
    if (chars.length > n) chars.length = n;
  }
  // Läufe: gleiche Stil-Quelle (Element) und aufeinanderfolgend
  const runs = [];
  let j = 0;   // SVG-Zeichennummer (UTF-16-Einheiten)
  for (const c of chars) {
    const last = runs[runs.length - 1];
    if (last && last.el === c.el) { last.chars.push({ ch: c.ch, j }); } else runs.push({ el: c.el, chars: [{ ch: c.ch, j }] });
    j += c.ch.length;
  }
  const parts = [];
  for (const run of runs) {
    const { fonts, family } = await fontsFor(ed, run.el);
    if (!fonts) return { error: t('Für die Schrift „{font}“ liegt keine Schriftdatei vor – bitte eine installierte Schrift wählen (Bereich Zeichen) und erneut umwandeln.', { font: family }) };
    const fs = parseFloat(getProp(run.el, 'font-size')) || 16;
    let d = '';
    // Glyphen je Zeichensatz-Datei: erste Datei, die das Zeichen kennt (latin, latin-ext …)
    const str = run.chars.map(c => c.ch).join('');
    const main = fonts[0];
    const layout = main.layout(str);
    let ci = 0;
    for (const g of layout.glyphs) {
      const cps = g.codePoints?.length || 1;
      const first = run.chars[ci];
      ci += cps;
      if (!first || /^\s$/.test(first.ch)) continue;
      let glyph = g;
      if (!g.id || g.name === '.notdef') {
        for (const f of fonts.slice(1)) { const alt = f.glyphForCodePoint(first.ch.codePointAt(0)); if (alt && alt.id) { glyph = alt; break; } }
      }
      const font = glyph === g ? main : fonts.find(f => f.glyphForCodePoint(first.ch.codePointAt(0))?.id === glyph.id) || main;
      let p, rot = 0;
      try { p = text.getStartPositionOfChar(first.j); rot = text.getRotationOfChar(first.j) || 0; } catch { continue; }
      const s = fs / font.unitsPerEm;
      const m = new DOMMatrix().translate(p.x, p.y).rotate(rot).scale(s, -s);
      const gd = glyph.path?.toSVG?.() || '';
      if (!gd) continue;
      d += pathToString(parsePath(gd).map(seg => {
        if (seg.c === 'Z') return seg;
        const q = [];
        for (let i = 0; i < seg.p.length; i += 2) { const r = pt(m, seg.p[i], seg.p[i + 1]); q.push(r.x, r.y); }
        return { c: seg.c, p: q };
      }), 3);
    }
    // Unterstrichen/Durchgestrichen: Linie aus den Maßen der Schrift über die Breite des Laufs
    let deco = '';
    for (let n = run.el; n && n !== text.parentNode; n = n.parentNode) { const v = n.getAttribute?.('text-decoration') || n.style?.getPropertyValue('text-decoration'); if (v && v !== 'none') { deco = v; break; } }
    if (deco && run.chars.length && !text.querySelector('textPath')) {
      try {
        const a = text.getStartPositionOfChar(run.chars[0].j), b = text.getEndPositionOfChar(run.chars[run.chars.length - 1].j);
        const s = fs / main.unitsPerEm, th = Math.max(0.5, (main.underlineThickness || main.unitsPerEm / 14) * s);
        const lines = [];
        if (/underline/.test(deco)) lines.push(a.y - (main.underlinePosition || -main.unitsPerEm / 10) * s);
        if (/line-through/.test(deco)) lines.push(a.y - (main['OS/2']?.yStrikeoutPosition || main.xHeight / 2 || main.unitsPerEm * 0.25) * s);
        for (const y of lines) d += `M${num(a.x)} ${num(y - th / 2)}H${num(b.x)}V${num(y + th / 2)}H${num(a.x)}Z`;
      } catch { /* ohne Linie */ }
    }
    if (d) parts.push({ el: run.el, d });
  }
  if (!parts.length) return { error: t('Der Text enthält keine Zeichen.') };
  // Ergebnis im Raum des Textes (dessen transform bleibt)
  const KEEP_OUT = ['x', 'y', 'dx', 'dy', 'rotate', 'textLength', 'lengthAdjust', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor',
    'letter-spacing', 'word-spacing', 'text-decoration', 'dominant-baseline', 'data-ge-frame', 'data-ge-lh', 'font-kerning', 'xml:space'];
  const copyAttrs = (from, to) => { for (const a of from.attributes) if (!KEEP_OUT.includes(a.name) && a.name !== 'href') to.setAttribute(a.name, a.value); to.style?.removeProperty('font-kerning'); to.style?.removeProperty('font-family'); };
  let out;
  if (parts.length === 1 && parts[0].el === text) {
    out = document.createElementNS(NS, 'path');
    copyAttrs(text, out);
    out.setAttribute('d', parts[0].d);
  } else {
    out = document.createElementNS(NS, 'g');
    copyAttrs(text, out);
    for (const pc of parts) {
      const p = document.createElementNS(NS, 'path');
      if (pc.el !== text) for (const a of pc.el.attributes) if (['fill', 'stroke', 'stroke-width', 'opacity', 'fill-opacity'].includes(a.name)) p.setAttribute(a.name, a.value);
      p.setAttribute('d', pc.d);
      out.append(p);
    }
  }
  if (!out.getAttribute('data-name')) out.setAttribute('data-name', text.textContent.trim().replace(/\s+/g, ' ').slice(0, 40));
  out.removeAttribute('id');
  if (text.id) out.id = text.id;
  text.replaceWith(out);
  return { el: out };
}

/** Alle Texte unter den Elementen umwandeln (Gruppen eingeschlossen). Rückgabe: { els, errors } */
export async function outlineAll(ed, list) {
  const errors = [], els = [];
  for (const el of list) {
    const texts = el.localName === 'text' ? [el] : [...(el.querySelectorAll?.('text') || [])];
    let cur = el;
    for (const tx of texts) {
      try { await document.fonts?.ready; } catch { /* egal */ }
      const r = await outlineText(ed, tx);
      if (r.error) errors.push(r.error); else if (tx === el) cur = r.el;
    }
    els.push(cur);
  }
  return { els, errors: [...new Set(errors)] };
}

export { num, docMatrix, parentMatrix, fmtMatrix };
