/*
 * Grafik-Editor – Geometrie mit Paper.js (MIT, nachgeladen: public/assets/js/grafik-paper.mjs, ca. 210 KB, erst beim ersten
 * Aufruf): Vereinen, Abziehen, Schnittmenge, Ausschluss, Teilen, Vereinfachen.
 *
 * Paper.js rechnet die Schnittpunkte direkt auf den Bézierkurven (kein Umweg über Vielecke) – Kreise bleiben Kreisbögen,
 * Ergebnis sind wieder kubische Kurven. Gerechnet wird im Dokumentraum (alle Transformationen eingerechnet); das Ergebnis
 * liegt danach im Raum des Elternelements der Ausgangsform. Konturen zählen nicht zur Fläche (wie in Affinity/Illustrator),
 * offene Pfade gelten als geschlossen.
 *
 * Stil des Ergebnisses: Vereinen, Schnittmenge, Ausschluss → oberstes Objekt; Abziehen → unterstes (es bleibt übrig);
 * Teilen → jedes Stück behält den Stil seines Ursprungs. MIT
 */
import { NS, num, docMatrix, parentMatrix, parsePath, pathToString, transformPath, shapeToPath, toNodes, fromNodes } from './geom.js';
import { getProp } from './doc.js';
import { t } from '../_i18n.js';
import { outlineAll } from './outline.js';

let paperP = null, offsetFns = null;
/** Paper.js laden (einmal je Seite); offsetFns: Kontur in Fläche / Versetzen (paperjs-offset) */
export function loadPaper(url) {
  if (!url) return Promise.reject(new Error(t('Die Geometrie-Funktionen konnten nicht geladen werden.')));
  return (paperP ||= import(url).then(m => {
    const paper = m.default || m.paper || m;
    paper.setup(new paper.Size(1, 1));
    offsetFns = { offset: m.offset, offsetStroke: m.offsetStroke };
    return paper;
  }).catch(e => { paperP = null; throw e; }));
}

/** Geometrie-Attribute, die das Ergebnis nicht erbt (es ist ein Pfad im Raum des Elternelements) */
const GEOM = ['x', 'y', 'width', 'height', 'rx', 'ry', 'cx', 'cy', 'r', 'x1', 'y1', 'x2', 'y2', 'points', 'd', 'transform', 'pathLength'];
const TEXTATTR = ['font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'letter-spacing', 'word-spacing', 'dominant-baseline', 'lengthAdjust', 'textLength', 'dx', 'dy', 'rotate'];

/** Teilpfade schließen (offene Pfade zählen als Fläche) */
function closed(d) {
  const subs = toNodes(parsePath(d));
  for (const s of subs) s.closed = true;
  return pathToString(fromNodes(subs), 4);
}

/** Formen eines Elements im Dokumentraum (Gruppen: alle sichtbaren Formen darin) → [{d, src}] */
export function shapesOf(el, root, out = []) {
  if (el.closest?.('defs,clipPath,mask,marker,pattern,symbol') || getProp(el, 'display', false) === 'none') return out;
  if (['g', 'switch', 'svg'].includes(el.localName)) { for (const c of el.children) shapesOf(c, root, out); return out; }
  const d = el.localName === 'path' ? el.getAttribute('d') : shapeToPath(el);
  if (d) out.push({ d: pathToString(transformPath(parsePath(closed(d)), docMatrix(el, root)), 4), src: el });
  return out;
}

/** Element → Paper-Fläche (Formen einer Gruppe werden zuerst vereint) */
function toItem(paper, el, root) {
  let acc = null;
  for (const { d, src } of shapesOf(el, root)) {
    const it = new paper.CompoundPath({ pathData: d, insert: false });
    if (/evenodd/i.test(getProp(src, 'fill-rule') || '')) it.fillRule = 'evenodd';
    acc = acc ? acc.unite(it, { insert: false }) : it;
  }
  return acc;
}

const empty = it => !it || !it.pathData || Math.abs(it.area || 0) < 1e-9;

/** Pfad-Element mit dem Stil von src, Geometrie im Raum des Elternelements von place */
function makePath(src, place, root, pathData) {
  const p = document.createElementNS(NS, 'path');
  for (const a of src.attributes) if (!GEOM.includes(a.name) && !TEXTATTR.includes(a.name) && a.name !== 'id') p.setAttribute(a.name, a.value);
  if (['g', 'switch'].includes(src.localName)) {
    // Gruppe als Stilquelle: Füllung/Kontur der ersten Form darin
    const s = src.querySelector('path,rect,circle,ellipse,polygon,polyline,line,text');
    if (s) for (const k of ['fill', 'stroke', 'stroke-width', 'fill-rule', 'stroke-linejoin', 'stroke-linecap']) {
      const v = getProp(s, k, false);
      if (v && !p.hasAttribute(k)) p.setAttribute(k, v);
    }
  }
  const Pi = parentMatrix(place, root).inverse();
  p.setAttribute('d', pathToString(transformPath(parsePath(pathData), Pi)));
  return p;
}

/**
 * Boolesche Operation auf der Auswahl (unterstes Objekt zuerst). op: unite | subtract | intersect | exclude | divide
 * Rückgabe: neue Elemente (eingefügt an der Stelle des untersten Objekts) oder null mit Meldung.
 */
export async function booleanOp(ed, op, list) {
  const paper = await loadPaper(ed.boot.modules?.paper);
  const root = ed.root;
  const items = [];
  for (const el of list) {
    const it = toItem(paper, el, root);
    if (it) items.push({ el, it });
  }
  if (items.length < 2) return { error: t('Für diese Funktion bitte mindestens zwei Formen auswählen (Rechtecke, Ellipsen, Kurven oder Gruppen davon).') };
  const bottom = items[0].el, top = items[items.length - 1].el;
  let out = [];
  if (op === 'divide') {
    let pieces = [], union = null;
    for (const { el, it } of items) {
      const next = [];
      for (const pc of pieces) {
        const a = pc.it.intersect(it, { insert: false }), b = pc.it.subtract(it, { insert: false });
        if (!empty(b)) next.push({ it: b, el: pc.el });
        if (!empty(a)) next.push({ it: a, el });
      }
      const own = union ? it.subtract(union, { insert: false }) : it;
      if (!empty(own)) next.push({ it: own, el });
      union = union ? union.unite(it, { insert: false }) : it;
      pieces = next;
    }
    out = pieces.map(pc => makePath(pc.el, bottom, root, pc.it.pathData));
  } else {
    let acc = items[0].it;
    for (const { it } of items.slice(1)) acc = acc[op](it, { insert: false });
    if (empty(acc)) return { error: op === 'intersect' ? t('Die Formen überschneiden sich nicht – die Schnittmenge ist leer.') : t('Das Ergebnis ist leer.') };
    const src = op === 'subtract' ? bottom : top;
    const p = makePath(src, bottom, root, acc.pathData);
    // Name und id des untersten Objekts bleiben (Ebenen, Animationen)
    if (bottom.getAttribute('data-name')) p.setAttribute('data-name', bottom.getAttribute('data-name'));
    if (bottom.id) p.id = bottom.id;
    out = [p];
  }
  if (!out.length) return { error: t('Das Ergebnis ist leer.') };
  let node = out[0];
  if (out.length > 1) {
    node = document.createElementNS(NS, 'g');
    node.setAttribute('data-name', t('Geteilt'));
    node.append(...out);
  }
  bottom.before(node);
  for (const { el } of items) el.remove();
  for (const el of list) if (el.isConnected) el.remove();
  return { els: [node] };
}

/**
 * Vereinfachen: Pfad fein abtasten und mit weniger Knoten neu anpassen (Paper.js simplify, Schneider-Verfahren).
 * tol in Einheiten des Elements. Rückgabe: neue Pfaddaten und Knotenzahl.
 */
export async function simplifyD(ed, d, tol) {
  const paper = await loadPaper(ed.boot.modules?.paper);
  const cp = new paper.CompoundPath({ pathData: d, insert: false });
  for (const ch of [...cp.children]) {
    const isClosed = ch.closed;
    ch.flatten(Math.max(0.01, tol / 4));
    ch.simplify(tol);
    ch.closed = isClosed;
  }
  const nodes = cp.children.reduce((n, ch) => n + ch.segments.length, 0);
  return { d: pathToString(parsePath(cp.pathData)), nodes };
}

/** Pfaddaten eines Elements im Dokumentraum ohne Schließen (Konturen offener Pfade bleiben offen) */
function openDocPath(el, root) {
  const d = el.localName === 'path' ? el.getAttribute('d') : shapeToPath(el);
  return d ? pathToString(transformPath(parsePath(d), docMatrix(el, root)), 4) : null;
}

/** Maßstab eines Elements im Dokument (für Konturbreite und Versatz) */
const scaleOf = (el, root) => { const m = docMatrix(el, root); return Math.sqrt(Math.abs(m.a * m.d - m.b * m.c)) || 1; };

/**
 * Kontur in Fläche umwandeln (wie „Kontur umwandeln“ in Illustrator bzw. „Kontur erweitern“ in Affinity): die Kontur wird
 * eine gefüllte Fläche in der Konturfarbe (Ecken, Enden, Gehrungsgrenze wie eingestellt). Hat das Objekt eine Füllung,
 * entsteht eine Gruppe aus Füllung und Konturfläche. Strichmuster und Pfeilspitzen werden nicht mit umgewandelt.
 */
export async function outlineStroke(ed, el) {
  const paper = await loadPaper(ed.boot.modules?.paper);
  const root = ed.root;
  const stroke = getProp(el, 'stroke');
  if (!stroke || stroke === 'none') return { error: t('Das Objekt hat keine Kontur.') };
  const d = openDocPath(el, root);
  if (!d) return { error: t('Diese Form lässt sich nicht umwandeln.') };
  const w = (parseFloat(getProp(el, 'stroke-width')) || 1) * (getProp(el, 'vector-effect') === 'non-scaling-stroke' ? 1 / ed.zoom : scaleOf(el, root));
  const join = getProp(el, 'stroke-linejoin') || 'miter', cap = getProp(el, 'stroke-linecap') || 'butt', limit = parseFloat(getProp(el, 'stroke-miterlimit')) || 4;
  const cp = new paper.CompoundPath({ pathData: d, insert: false });
  let area = null;
  for (const ch of cp.children.length ? cp.children : [cp]) {
    const o = offsetFns.offsetStroke(ch, w / 2, { join, cap: cap === 'square' ? 'square' : cap === 'round' ? 'round' : 'butt', limit, insert: false });
    if (o) area = area ? area.unite(o, { insert: false }) : o;
  }
  if (empty(area)) return { error: t('Das Ergebnis ist leer.') };
  const sp = makePath(el, el, root, area.pathData);
  for (const a of ['stroke', 'stroke-width', 'stroke-linejoin', 'stroke-linecap', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'marker-start', 'marker-end', 'marker-mid', 'vector-effect', 'fill-opacity', 'fill-rule']) { sp.removeAttribute(a); sp.style?.removeProperty(a); }
  sp.setAttribute('fill', stroke);
  const so = getProp(el, 'stroke-opacity', false);
  if (so && so !== '1') sp.setAttribute('fill-opacity', so);
  sp.removeAttribute('id');
  const fill = getProp(el, 'fill');
  let out = sp;
  if (fill && fill !== 'none' && ['path', 'rect', 'circle', 'ellipse', 'polygon'].includes(el.localName)) {
    const fp = makePath(el, el, root, pathToString(transformPath(parsePath(closed(el.localName === 'path' ? el.getAttribute('d') : shapeToPath(el))), docMatrix(el, root)), 4));
    for (const a of ['stroke', 'stroke-width', 'stroke-linejoin', 'stroke-linecap', 'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'marker-start', 'marker-end', 'marker-mid', 'vector-effect']) { fp.removeAttribute(a); fp.style?.removeProperty(a); }
    fp.removeAttribute('id');
    out = document.createElementNS(NS, 'g');
    if (el.getAttribute('data-name')) out.setAttribute('data-name', el.getAttribute('data-name'));
    fp.setAttribute('data-name', t('Füllung')); sp.setAttribute('data-name', t('Kontur'));
    for (const a of ['opacity', 'filter', 'clip-path', 'mask', 'style']) { if (fp.hasAttribute(a)) { out.setAttribute(a, fp.getAttribute(a)); fp.removeAttribute(a); sp.removeAttribute(a); } }
    out.append(fp, sp);
  }
  if (el.id) out.id = el.id;
  el.replaceWith(out);
  return { el: out, dashed: !!(getProp(el, 'stroke-dasharray', false) && getProp(el, 'stroke-dasharray', false) !== 'none') };
}

/**
 * Pfad versetzen (Offset): Fläche um dist nach außen (positiv) bzw. innen (negativ), Ecken rund/spitz/abgeschrägt.
 * Rückgabe: Pfaddaten im Dokumentraum (für Vorschau und Übernahme).
 */
export async function offsetArea(ed, el, dist, join = 'round') {
  const paper = await loadPaper(ed.boot.modules?.paper);
  let acc = null;
  for (const { d } of shapesOf(el, ed.root)) {
    const cp = new paper.CompoundPath({ pathData: d, insert: false });
    const o = offsetFns.offset(cp, dist, { join, limit: 4, insert: false });
    if (o) acc = acc ? acc.unite(o, { insert: false }) : o;
  }
  return empty(acc) ? null : acc.pathData;
}

/** Pfad im Dokumentraum als Element mit dem Stil von src (im Raum des Elternelements von src) */
export function pathLike(src, root, pathData, place = src) { return makePath(src, place, root, pathData); }

// ================================================================= Zusammengesetzte Form (zerstörungsfrei)
/*
 * <g data-ge-bool="subtract" data-name="…">
 *   <path data-ge-result="1" …/>                    berechnetes Ergebnis (sichtbar, wird ausgeliefert)
 *   <g data-ge-src="1" display="none"> Originale </g> Ausgangsformen (verborgen; „Originale bearbeiten“ blendet sie ein)
 * </g>
 * Die Web-Ausgabe entfernt die verborgenen Originale (ausgeblendete Ebenen) – übrig bleibt der fertige Pfad.
 */
export const isCompound = el => el?.localName === 'g' && el.hasAttribute('data-ge-bool');

export async function makeCompound(ed, op, list) {
  const parent = list[0].parentNode;
  if (list.some(el => el.parentNode !== parent)) return { error: t('Nur Objekte derselben Ebene lassen sich zusammensetzen.') };
  const g = document.createElementNS(NS, 'g');
  g.setAttribute('data-ge-bool', op);
  g.setAttribute('data-name', t('Zusammengesetzte Form'));
  list[list.length - 1].after(g);
  const src = document.createElementNS(NS, 'g');
  src.setAttribute('data-ge-src', '1');
  src.setAttribute('data-name', t('Originale'));
  src.append(...list);
  g.append(src);
  const r = await recomputeCompound(ed, g);
  if (r.error) { g.before(...list); g.remove(); return r; }
  return { el: g };
}

/** Ergebnis einer zusammengesetzten Form neu berechnen (nach dem Bearbeiten der Originale oder Wechsel der Operation) */
export async function recomputeCompound(ed, g) {
  const src = [...g.children].find(c => c.getAttribute('data-ge-src') === '1');
  if (!src) return { error: 'src' };
  src.removeAttribute('display');   // zum Messen sichtbar (getBBox/getScreenCTM)
  const list = [...src.children].filter(c => DRAWN_TAGS.includes(c.localName));
  const tmp = list.map(el => el.cloneNode(true));
  // Rechnen auf Kopien: booleanOp entfernt seine Eingaben
  const holder = document.createElementNS(NS, 'g');
  g.append(holder);
  holder.append(...tmp);
  let r, work = tmp;
  try {
    // Texte als Stanze: Kopien in Kurven (die Originale bleiben Text und bearbeitbar)
    if (work.some(el => el.localName === 'text' || el.querySelector?.('text'))) work = (await outlineAll(ed, work)).els;
    r = list.length >= 2 ? await booleanOp(ed, g.getAttribute('data-ge-bool'), work) : { error: t('Für diese Funktion bitte mindestens zwei Formen auswählen (Rechtecke, Ellipsen, Kurven oder Gruppen davon).') };
  } finally { if (ed.compoundEdit !== g) src.setAttribute('display', 'none'); }
  const res = r.els?.[0];
  holder.remove();
  if (!res) return r;
  res.setAttribute('data-ge-result', '1');
  res.removeAttribute('id');
  [...g.children].filter(c => c.getAttribute('data-ge-result') === '1').forEach(c => c.remove());
  g.prepend(res);
  return { el: g };
}

const DRAWN_TAGS = ['g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'text', 'use'];

export { num };
