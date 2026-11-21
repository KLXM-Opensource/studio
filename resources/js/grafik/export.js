/*
 * Grafik-Editor – Ausgabe:
 *
 *  Web (was die Mediathek/Website ausliefert): verlustfrei optimiert, danach immer durch Core\Svg::clean auf dem Server.
 *    - Editor-Daten weg (data-name, data-locked, data-ge-*: Hilfslinien, Raster, Textrahmen), ausgeblendete Ebenen weg
 *    - Standardwerte weg (fill-opacity="1", stroke-miterlimit="4" …) – nur wenn kein Vorfahr einen anderen Wert setzt
 *      und kein <style> mitspielt
 *    - Zahlen auf N Nachkommastellen (Standard automatisch nach Größe der Zeichenfläche: Abweichung ≤ 1/20 000 der
 *      längeren Seite), Pfade kürzer (relativ/absolut je Befehl, H/V, minimale Trenner, ohne Fehler-Aufsummierung)
 *    - reine Verschiebungen (translate) in die Geometrie eingerechnet – das ist exakt; Drehen/Skalieren bleibt stehen
 *    - leere Gruppen/Definitionen, ungenutzte Definitionen, Leerraum; Farben kurz (#AABBCC → #abc)
 *    Sicherheitsnetz: vorher/nachher gerendert und Pixel verglichen (checkSame) – weicht es ab, gilt die sichere Stufe.
 *
 *  Desktop-Programme (Affinity, Illustrator, Inkscape, Figma): nicht verkleinert, eingerückt, Ebenen der obersten Stufe als
 *    Inkscape-Ebenen (inkscape:groupmode="layer", inkscape:label), Namen als id/data-name (Illustrator, Figma),
 *    gesperrte Objekte als sodipodi:insensitive, textPath zusätzlich mit xlink:href, Maße und viewBox bleiben.
 *
 *  PDF (Vektor): jsPDF + svg2pdf.js (MIT, nachgeladen). Texte werden vorher in Kurven umgewandelt, wenn die Schrift
 *    vorliegt; Filter (Schatten) und Mischmodi kennt svg2pdf nicht – Hinweis. MIT
 */
import { NS, num, parsePath, transformPath } from './geom.js';
import { t } from '../_i18n.js';
import { themeClasses } from './scheme.js';

const XLINK = 'http://www.w3.org/1999/xlink';
const INK = 'http://www.inkscape.org/namespaces/inkscape';
const SODI = 'http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd';
const DRAWN = ['g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'use', 'switch'];

/** Nachkommastellen „automatisch“: Fehler ≤ längere Seite / 20 000 */
export function autoDecimals(vb) {
  const tol = Math.max(vb.w, vb.h) / 20000;
  return Math.max(1, Math.min(4, Math.ceil(-Math.log10(tol * 2))));
}

const parse = svg => {
  const d = new DOMParser().parseFromString(svg, 'image/svg+xml');
  if (d.querySelector('parsererror')) throw new Error('parse');
  return d.documentElement;
};
const ser = el => new XMLSerializer().serializeToString(el);
const all = root => [root, ...root.querySelectorAll('*')];

/** ids, auf die verwiesen wird (url(#…), href) */
function references(root) {
  const refs = new Set();
  const re = /url\(\s*['"]?#([^'")\s]+)/g;
  for (const el of all(root)) {
    for (const a of el.attributes) {
      if (a.localName === 'href' && a.value.startsWith('#')) refs.add(a.value.slice(1));
      let m; re.lastIndex = 0;
      while ((m = re.exec(a.value))) refs.add(m[1]);
    }
    if (el.localName === 'style') { let m; re.lastIndex = 0; while ((m = re.exec(el.textContent))) refs.add(m[1]); }
  }
  return refs;
}

// ---------------------------------------------------------------- Zahlen und Pfade

function fmt(v, d) {
  let s = num(v, d);
  if (s.startsWith('0.')) s = s.slice(1); else if (s.startsWith('-0.')) s = '-' + s.slice(2);
  return s;
}

/** Pfad kürzen: je Befehl absolut oder relativ (kürzer gewinnt), H/V, minimale Trenner; gegen gerundete Lage gerechnet */
export function minifyPath(d, dec) {
  const segs = parsePath(d);
  if (!segs.length) return d;
  const R = v => Math.round(v * 10 ** dec) / 10 ** dec;
  const lastNum = s => (s.match(/[-+]?(?:\d*\.\d+|\d+\.?)(?:e[-+]?\d+)?$/i) || [''])[0];
  // Zahlen anhängen: Trenner nur, wo nötig („-“ trennt selbst, „.5.5“ ist gültig, wenn die vorige Zahl schon einen Punkt hat)
  const add = (str, n) => {
    if (/[\d.]$/.test(str) && !n.startsWith('-') && !(n.startsWith('.') && lastNum(str).includes('.'))) str += ' ';
    return str + n;
  };
  let out = '', last = '';
  let cx = 0, cy = 0, sx = 0, sy = 0;   // gerundete aktuelle Lage und Start des Teilpfads
  const emit = (cmd, vals) => {
    let s = cmd === last ? out : out + cmd;
    for (const v of vals) s = add(s, typeof v === 'string' ? v : fmt(v, dec));
    return s.slice(out.length);
  };
  for (const g of segs) {
    if (g.c === 'Z') { out += 'z'; last = 'z'; cx = sx; cy = sy; continue; }
    const p = g.p;
    let abs, rel, nx, ny;
    if (g.c === 'A') {
      nx = R(p[5]); ny = R(p[6]);
      const head = [R(p[0]), R(p[1]), R(p[2]), String(p[3]), String(p[4])];
      abs = ['A', [...head, nx, ny]]; rel = ['a', [...head, R(nx - cx), R(ny - cy)]];
    } else {
      const pts = [];
      for (let i = 0; i < p.length; i += 2) pts.push([R(p[i]), R(p[i + 1])]);
      [nx, ny] = pts[pts.length - 1];
      const C = g.c;
      if (C === 'L' && nx === cx) { abs = ['V', [ny]]; rel = ['v', [R(ny - cy)]]; }
      else if (C === 'L' && ny === cy) { abs = ['H', [nx]]; rel = ['h', [R(nx - cx)]]; }
      else { abs = [C, pts.flat()]; rel = [C.toLowerCase(), pts.flatMap(([x, y]) => [R(x - cx), R(y - cy)])]; }
    }
    // Bei M kein Weglassen des Buchstabens (sonst wäre es ein L); erstes M immer absolut
    const sa = emit(abs[0], abs[1]), sr = !out ? null : emit(rel[0], rel[1]);
    const useRel = sr !== null && sr.length < sa.length;
    out += useRel ? sr : sa;
    const cmd = useRel ? rel[0] : abs[0];
    last = cmd === 'M' ? 'L' : cmd === 'm' ? 'l' : cmd;
    cx = nx; cy = ny;
    if (g.c === 'M') { sx = nx; sy = ny; }
  }
  return out;
}

const NUM_ATTRS = ['x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'width', 'height', 'stroke-width', 'font-size', 'letter-spacing', 'word-spacing', 'stroke-dashoffset', 'stdDeviation', 'dx', 'dy', 'refX', 'refY', 'markerWidth', 'markerHeight'];
const GRAD_ATTRS = ['x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'fx', 'fy', 'fr'];

function roundNumbers(el, dec) {
  const isGrad = el.localName === 'linearGradient' || el.localName === 'radialGradient';
  const bbox = isGrad && el.getAttribute('gradientUnits') !== 'userSpaceOnUse';
  for (const a of NUM_ATTRS) {
    if (!el.hasAttribute(a) || (el.localName === 'svg' && (a === 'width' || a === 'height'))) continue;
    const v = el.getAttribute(a);
    const d = bbox && GRAD_ATTRS.includes(a) ? Math.max(4, dec) : dec;
    if (/^\s*-?[\d.]+(e-?\d+)?\s*(px)?\s*$/i.test(v)) el.setAttribute(a, fmt(parseFloat(v), d));
    else if (/^[-\d.\s,e]+$/i.test(v) && /[\s,]/.test(v.trim())) el.setAttribute(a, v.trim().split(/[\s,]+/).map(n => fmt(parseFloat(n), d)).join(' '));
  }
  if (isGrad) for (const a of GRAD_ATTRS) {
    const v = el.getAttribute(a);
    if (v && /^\s*-?[\d.]+\s*$/.test(v)) el.setAttribute(a, fmt(parseFloat(v), bbox ? Math.max(4, dec) : dec));
  }
  if (el.localName === 'stop' && el.hasAttribute('offset') && !/%/.test(el.getAttribute('offset'))) el.setAttribute('offset', fmt(parseFloat(el.getAttribute('offset')) || 0, 4));
  if (el.hasAttribute('points')) {
    const v = el.getAttribute('points').trim().split(/[\s,]+/).map(Number);
    if (v.every(Number.isFinite) && v.length % 2 === 0) { const o = []; for (let i = 0; i < v.length; i += 2) o.push(fmt(v[i], dec) + ',' + fmt(v[i + 1], dec)); el.setAttribute('points', o.join(' ')); }
  }
  for (const a of ['transform', 'gradientTransform', 'patternTransform']) {
    const v = el.getAttribute(a);
    if (!v) continue;
    el.setAttribute(a, v.replace(/(matrix|translate|scale|rotate|skewX|skewY)\(([^)]*)\)/g, (m, fn, args) => {
      const n = args.trim().split(/[\s,]+/).map(Number);
      const r = fn === 'matrix' ? n.map((x, i) => fmt(x, i < 4 ? 6 : dec)) : fn === 'translate' ? n.map(x => fmt(x, dec)) : fn === 'rotate' ? n.map((x, i) => fmt(x, i ? dec : 4)) : n.map(x => fmt(x, 6));
      return `${fn}(${r.join(' ')})`;
    }).replace(/\)\s+/g, ')'));
  }
  if (el.hasAttribute('d') && el.localName === 'path') el.setAttribute('d', minifyPath(el.getAttribute('d'), dec));
}

// ---------------------------------------------------------------- Standardwerte

const DEFAULTS = {
  'fill-opacity': '1', 'stroke-opacity': '1', opacity: '1', 'stroke-width': '1', 'stroke-miterlimit': '4', 'stroke-dasharray': 'none',
  'stroke-dashoffset': '0', 'fill-rule': 'nonzero', 'clip-rule': 'nonzero', 'stroke-linecap': 'butt', 'stroke-linejoin': 'miter',
  'font-style': 'normal', visibility: 'visible', display: 'inline', 'text-anchor': 'start', 'stop-opacity': '1', 'letter-spacing': '0',
  'mix-blend-mode': 'normal', stroke: 'none', 'font-weight': '400',
};
const INHERITED = new Set(['fill-opacity', 'stroke-opacity', 'stroke-width', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'fill-rule', 'clip-rule',
  'stroke-linecap', 'stroke-linejoin', 'font-style', 'visibility', 'text-anchor', 'letter-spacing', 'stroke', 'font-weight']);
const normVal = (k, v) => { v = String(v).trim().toLowerCase(); if (k === 'font-weight' && v === 'normal') return '400'; if (k === 'letter-spacing' && v === 'normal') return '0'; return /^-?[\d.]+(px)?$/.test(v) ? String(parseFloat(v)) : v; };

function inheritedValue(el, k) {
  for (let n = el.parentNode; n && n.nodeType === 1; n = n.parentNode) {
    const s = n.style?.getPropertyValue(k);
    if (s) return s;
    if (n.hasAttribute(k)) return n.getAttribute(k);
  }
  return null;
}

function dropDefaults(el, hasStyleSheet) {
  if (hasStyleSheet) return;   // Klassenregeln könnten den Wert sonst übersteuern
  for (const [k, def] of Object.entries(DEFAULTS)) {
    const v = el.getAttribute(k);
    if (v === null || normVal(k, v) !== normVal(k, def)) continue;
    if (k === 'display' || k === 'opacity' || k === 'mix-blend-mode' || k === 'stop-opacity' || !INHERITED.has(k) || inheritedValue(el, k) === null) el.removeAttribute(k);
  }
}

// ---------------------------------------------------------------- Verschiebung einrechnen

function foldTranslate(el) {
  const m = /^\s*translate\(\s*(-?[\d.e-]+)(?:[\s,]+(-?[\d.e-]+))?\s*\)\s*$/i.exec(el.getAttribute('transform') || '');
  if (!m) return;
  for (const p of ['clip-path', 'mask', 'filter', 'fill', 'stroke']) {
    const v = el.getAttribute(p) || el.style?.getPropertyValue(p) || '';
    if (/url\(/.test(v)) return;   // Verläufe/Muster/Filter im Benutzerraum würden sonst verrutschen – im Zweifel stehen lassen
  }
  if (el.getAttribute('style')) return;
  const tx = parseFloat(m[1]) || 0, ty = parseFloat(m[2] || '0') || 0;
  const n = el.localName, g = a => parseFloat(el.getAttribute(a)) || 0, set = (a, v) => el.setAttribute(a, String(v));
  if (n === 'rect' || (n === 'use' && !el.hasAttribute('transform-origin'))) { set('x', g('x') + tx); set('y', g('y') + ty); }
  else if (n === 'circle' || n === 'ellipse') { set('cx', g('cx') + tx); set('cy', g('cy') + ty); }
  else if (n === 'line') { set('x1', g('x1') + tx); set('y1', g('y1') + ty); set('x2', g('x2') + tx); set('y2', g('y2') + ty); }
  else if (n === 'polyline' || n === 'polygon') { const v = el.getAttribute('points').trim().split(/[\s,]+/).map(Number); el.setAttribute('points', v.map((x, i) => x + (i % 2 ? ty : tx)).join(' ')); }
  else if (n === 'path') el.setAttribute('d', transformPath(parsePath(el.getAttribute('d')), new DOMMatrix().translate(tx, ty)).map(s => s.c + s.p.join(' ')).join(''));
  else return;
  el.removeAttribute('transform');
}

// ---------------------------------------------------------------- Farben

const shortHex = v => v.replace(/#([0-9a-fA-F]{6})\b/g, (m, h) => { h = h.toLowerCase(); return h[0] === h[1] && h[2] === h[3] && h[4] === h[5] ? '#' + h[0] + h[2] + h[4] : '#' + h; });

/**
 * Für das Web optimieren. opts: { decimals: 'auto'|1..4, hidden: true (ausgeblendete Ebenen entfernen), safe: false
 * (nur Editor-Daten entfernen) }. Rückgabe: Text der SVG.
 */
export function optimizeSvg(svg, opts = {}) {
  const root = parse(svg);
  const vbA = (root.getAttribute('viewBox') || '').split(/[\s,]+/).map(Number);
  const vb = vbA.length === 4 && vbA[2] ? { w: vbA[2], h: vbA[3] } : { w: parseFloat(root.getAttribute('width')) || 800, h: parseFloat(root.getAttribute('height')) || 500 };
  const dec = opts.decimals && opts.decimals !== 'auto' ? Math.max(0, Math.min(6, +opts.decimals)) : autoDecimals(vb);
  // Design-Farben und Dunkel-Angaben als Klassen (Core\Graphics\Themed erzeugt daraus die Fassungen je Website), danach Editor-Daten weg
  for (const el of all(root)) {
    const cls = themeClasses(el);
    if (cls.length) el.setAttribute('class', [...(el.getAttribute('class') || '').split(/\s+/).filter(c => c && !/^ge-[td][fsc]-/.test(c)), ...cls].join(' '));
  }
  for (const el of all(root)) for (const a of [...el.attributes]) if (a.name === 'data-name' || a.name === 'data-locked' || a.name.startsWith('data-ge-')) el.removeAttribute(a.name);
  if (opts.safe) return ser(root);
  const refs = references(root);
  const sheet = !!root.querySelector('style');
  // ausgeblendete Ebenen (nicht referenziert)
  if (opts.hidden !== false) for (const el of [...root.querySelectorAll(DRAWN.join(','))]) {
    if (!el.isConnected || el.closest('defs,clipPath,mask,marker,pattern,symbol')) continue;
    const hid = el.getAttribute('display') === 'none' || /display\s*:\s*none/.test(el.getAttribute('style') || '');
    if (hid && !(el.id && refs.has(el.id)) && !el.querySelector('[id]')) el.remove();
  }
  // ungenutzte Definitionen (mehrere Durchgänge: Verläufe erben voneinander)
  for (let pass = 0; pass < 6; pass++) {
    const r2 = references(root);
    let gone = 0;
    for (const el of [...root.querySelectorAll('linearGradient,radialGradient,pattern,clipPath,mask,filter,marker,symbol')]) if (!r2.has(el.id)) { el.remove(); gone++; }
    if (!gone) break;
  }
  for (const el of all(root)) {
    if (el.localName !== 'svg') foldTranslate(el);
    dropDefaults(el, sheet);
    roundNumbers(el, dec);
    for (const a of ['fill', 'stroke', 'stop-color', 'flood-color', 'color', 'style']) if (el.hasAttribute(a)) el.setAttribute(a, shortHex(el.getAttribute(a)));
  }
  // leere Gruppen/defs weg, Gruppen ohne Attribute auflösen (von innen nach außen)
  for (const el of [...root.querySelectorAll('g,defs')].reverse()) {
    if (!el.isConnected) continue;
    if (!el.children.length && !(el.id && refs.has(el.id))) el.remove();
    else if (el.localName === 'g' && !el.attributes.length && el.parentNode.localName !== 'switch') { el.replaceWith(...el.childNodes); }
  }
  // Leerraum zwischen Elementen (nicht in Texten)
  const walk = n => { for (const c of [...n.childNodes]) { if (c.nodeType === 3 && !['text', 'tspan', 'textPath', 'title', 'desc', 'style'].includes(n.localName) && !c.data.trim()) c.remove(); else if (c.nodeType === 8) c.remove(); else if (c.nodeType === 1) walk(c); } };
  walk(root);
  if (!root.querySelector('[*|href]') || ![...all(root)].some(el => el.hasAttributeNS(XLINK, 'href'))) root.removeAttributeNS('http://www.w3.org/2000/xmlns/', 'xlink');
  root.removeAttribute('version');
  return ser(root);
}

/** Zwei SVG gleich gerendert? (Bilder vergleichen; Anteil abweichender Pixel ≤ tol) */
export async function checkSame(a, b, tol = 0.0015) {
  const render = async svg => {
    const root = parse(svg);
    const vb = (root.getAttribute('viewBox') || '0 0 300 150').split(/[\s,]+/).map(Number);
    const w = Math.min(1200, Math.max(200, vb[2] * (vb[2] < 100 ? 16 : 1))), h = Math.max(1, Math.round(w * vb[3] / vb[2]));
    root.setAttribute('width', w); root.setAttribute('height', h);
    const img = new Image();
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(ser(root));
    await img.decode();
    const c = document.createElement('canvas'); c.width = w; c.height = h;
    const x = c.getContext('2d', { willReadFrequently: true }); x.fillStyle = '#fff'; x.fillRect(0, 0, w, h); x.drawImage(img, 0, 0);
    return x.getImageData(0, 0, w, h).data;
  };
  try {
    const [A, B] = await Promise.all([render(a), render(b)]);
    if (A.length !== B.length) return false;
    let n = 0;
    for (let i = 0; i < A.length; i += 4) if (Math.abs(A[i] - B[i]) + Math.abs(A[i + 1] - B[i + 1]) + Math.abs(A[i + 2] - B[i + 2]) + Math.abs(A[i + 3] - B[i + 3]) > 48) n++;
    return n / (A.length / 4) <= tol;
  } catch { return false; }
}

// ---------------------------------------------------------------- Desktop-Programme

const slug = s => String(s).normalize('NFKD').replace(/ß/g, 'ss').replace(/[̀-ͯ]/g, '').replace(/[^A-Za-z0-9_-]+/g, '_').replace(/^([^A-Za-z_])/, '_$1').replace(/_+$/, '') || 'ebene';

/** SVG für Affinity, Illustrator, Inkscape, Figma: Ebenen, Namen, eingerückt */
export function desktopSvg(svg) {
  const root = parse(svg);
  root.setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:inkscape', INK);
  root.setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sodipodi', SODI);
  root.setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xlink', XLINK);
  const used = new Set([...root.querySelectorAll('[id]')].map(e => e.id));
  const uniq = base => { let id = base, i = 2; while (used.has(id)) id = base + '_' + i++; used.add(id); return id; };
  for (const el of all(root)) {
    const name = el.getAttribute('data-name');
    if (name) {
      el.setAttributeNS(INK, 'inkscape:label', name);
      if (!el.id) el.id = uniq(slug(name));
    }
    if (el.getAttribute('data-locked') === '1') { el.setAttributeNS(SODI, 'sodipodi:insensitive', 'true'); el.removeAttribute('data-locked'); }
    for (const a of [...el.attributes]) if (a.name.startsWith('data-ge-')) el.removeAttribute(a.name);
    if (el.localName === 'textPath' && el.getAttribute('href') && !el.hasAttributeNS(XLINK, 'href')) el.setAttributeNS(XLINK, 'xlink:href', el.getAttribute('href'));
  }
  let n = 0;
  for (const g of [...root.children].filter(c => c.localName === 'g')) {
    n++;
    g.setAttributeNS(INK, 'inkscape:groupmode', 'layer');
    if (!g.getAttributeNS(INK, 'label')) g.setAttributeNS(INK, 'inkscape:label', g.getAttribute('data-name') || t('Ebene {n}', { n }));
    if (!g.id) g.id = uniq(slug(g.getAttributeNS(INK, 'label')));
  }
  if (!root.getAttribute('width') || !root.getAttribute('height')) {
    const vb = (root.getAttribute('viewBox') || '0 0 800 500').split(/[\s,]+/);
    root.setAttribute('width', vb[2]); root.setAttribute('height', vb[3]);
  }
  return '<?xml version="1.0" encoding="UTF-8"?>\n' + pretty(root);
}

/** Eingerückt ausgeben (Texte bleiben unberührt) */
function pretty(root) {
  const strip = n => { for (const c of [...n.childNodes]) { if (c.nodeType === 3 && !['text', 'tspan', 'textPath', 'title', 'desc', 'style'].includes(n.localName) && !c.data.trim()) c.remove(); else if (c.nodeType === 1 && !['text', 'title', 'desc', 'style'].includes(c.localName)) strip(c); } };
  strip(root);
  const doc = root.ownerDocument;
  const indent = (n, depth) => {
    if (['text', 'title', 'desc', 'style'].includes(n.localName) || !n.children.length) return;
    for (const c of [...n.children]) { n.insertBefore(doc.createTextNode('\n' + '  '.repeat(depth + 1)), c); indent(c, depth + 1); }
    n.append(doc.createTextNode('\n' + '  '.repeat(depth)));
  };
  indent(root, 0);
  return ser(root) + '\n';
}

// ---------------------------------------------------------------- PDF

let pdfP = null;
/** PDF (Vektor) aus SVG-Text – Rückgabe: Blob. warn(text) für Hinweise (Filter, Mischmodi) */
export async function pdfBlob(url, svg, warn) {
  const mod = await (pdfP ||= import(url).catch(e => { pdfP = null; throw e; }));
  const root = parse(svg);
  const vb = (root.getAttribute('viewBox') || '0 0 800 500').split(/[\s,]+/).map(Number);
  if (root.querySelector('filter') || /mix-blend-mode/.test(svg)) warn?.(t('Schatten, Weichzeichner und Mischmodi kennt der PDF-Export nicht – sie fehlen im PDF.'));
  if (root.querySelector('text')) warn?.(t('Texte ohne verfügbare Schriftdatei erscheinen im PDF in einer Standardschrift.'));
  const host = document.createElement('div');
  host.style.cssText = 'position:fixed;left:-10000px;top:0;width:10px;height:10px;overflow:hidden';
  const el = document.importNode(root, true);
  el.setAttribute('width', vb[2]); el.setAttribute('height', vb[3]);
  host.append(el);
  document.body.append(host);
  try {
    const w = vb[2], h = vb[3];
    const doc = new mod.jsPDF({ unit: 'pt', format: [w, h], orientation: w > h ? 'landscape' : 'portrait', compress: true });
    await mod.svg2pdf(el, doc, { x: 0, y: 0, width: w, height: h });
    return doc.output('blob');
  } finally { host.remove(); }
}

export { NS };
