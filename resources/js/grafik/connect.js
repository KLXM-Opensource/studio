/*
 * Grafik-Editor – Diagramme: Verbinder, die an Formen haften (Seitenmitten, Mitte = automatisch, Knoten von Pfaden als
 * Magnetpunkte) und folgen, wenn Formen verschoben oder skaliert werden; gerade, rechtwinklig (mit verschiebbarem Knick)
 * oder gebogen; Pfeilspitzen je Ende (Pfeil, Dreieck, offenes Dreieck, Kreis, Raute, Strich) in wählbarer Größe;
 * Beschriftung in der Mitte; Text in Formen (zentriert, umbrochen).
 *
 * Gespeichert als gewöhnlicher <path> mit Markern – die Web-Fassung braucht nichts weiter. Die Beziehungen stehen nur in
 * der Arbeitsfassung (graphic_sources):
 *   <path data-ge-conn="a=kasten-1@r;b=kasten-2@l;r=ortho;m=0.5;hs=none;he=triangle;s=4" …>
 *     a/b  Ende: id@Seite (t, r, b, l, a = automatisch, n3 = Knoten 3) oder @x,y (frei, Koordinaten der Zeichenfläche)
 *     r    straight | ortho | curve      m  Lage des mittleren Abschnitts (0–1, rechtwinklig)
 *     hs/he  Spitze am Anfang/Ende      s  Größe der Spitzen (in Konturbreiten)
 *   <text data-ge-for="verbinder-1">Ja</text>       Beschriftung (bleibt in der Mitte)
 *   <text data-ge-in="kasten-1" data-ge-frame=…>    Text in einer Form (Breite und Lage folgen der Form)
 * Fehlt eine verbundene Form (gelöscht), wird das Ende frei an seiner letzten Stelle. MIT
 */
import { NS, num, docMatrix, parentMatrix, pt, docBounds, parsePath, toNodes, transformPath, pathToString } from './geom.js';
import { defs, uid, getProp, setProp } from './doc.js';
import { hex } from './color.js';
import { readText, layoutText } from './text.js';

/** Pfeilspitzen: Pfad im Raum 0 0 10 10 (Spitze rechts), ref = Bezugspunkt am Linienende, tip = Spitze */
export const HEADS = {
  arrow: { d: 'M1 1L9.5 5L1 9', stroke: true, ref: 9.5, tip: 9.5 },
  triangle: { d: 'M0 0.5L10 5L0 9.5z', ref: 0.5, tip: 10 },
  open: { d: 'M1 1L9.2 5L1 9z', stroke: true, ref: 1, tip: 9.6 },
  circle: { d: 'M1 5a4 4 0 1 0 8 0a4 4 0 1 0-8 0z', ref: 1, tip: 9 },
  diamond: { d: 'M0 5L5 1L10 5L5 9z', ref: 0, tip: 10 },
  bar: { d: 'M5 0.5V9.5', stroke: true, ref: 5, tip: 5 },
};
export const ROUTES = ['straight', 'ortho', 'curve'];
const DIR = { t: [0, -1], b: [0, 1], l: [-1, 0], r: [1, 0] };

export const isConn = el => el?.localName === 'path' && el.hasAttribute('data-ge-conn');

/** data-ge-conn lesen */
export function parseConn(el) {
  const o = { a: null, b: null, r: 'ortho', m: 0.5, hs: 'none', he: 'triangle', s: 4 };
  for (const part of String(el.getAttribute('data-ge-conn') || '').split(';')) {
    const i = part.indexOf('=');
    if (i < 1) continue;
    const k = part.slice(0, i), v = part.slice(i + 1);
    if (k === 'a' || k === 'b') {
      const [id, rest] = v.split('@');
      if (id) o[k] = { id, side: rest || 'a' };
      else { const [x, y] = (rest || '').split(',').map(Number); o[k] = { x: x || 0, y: y || 0 }; }
    } else if (k === 'm' || k === 's') o[k] = parseFloat(v) || o[k];
    else if (k === 'r' && ROUTES.includes(v)) o.r = v;
    else if ((k === 'hs' || k === 'he') && (v === 'none' || HEADS[v])) o[k] = v;
  }
  return o;
}
const endStr = e => e?.id ? `${e.id}@${e.side || 'a'}` : `@${num(e?.x || 0, 2)},${num(e?.y || 0, 2)}`;
export function writeConn(el, o) {
  el.setAttribute('data-ge-conn', `a=${endStr(o.a)};b=${endStr(o.b)};r=${o.r};m=${num(o.m, 3)};hs=${o.hs};he=${o.he};s=${num(o.s, 2)}`);
}

export class Connectors {
  constructor(ed) { this.ed = ed; this.fitKeys = new WeakMap(); }

  byId(id) { try { return this.ed.root.querySelector('#' + CSS.escape(id)); } catch { return null; } }

  /** Ankerpunkte einer Form (Zeichenflächen-Koordinaten): Seitenmitten, Mitte (= automatisch), Knoten bei Pfaden */
  anchors(el) {
    const b = docBounds(el, this.ed.root);
    if (!b) return [];
    const out = [
      { side: 't', p: { x: b.x + b.w / 2, y: b.y } }, { side: 'r', p: { x: b.x + b.w, y: b.y + b.h / 2 } },
      { side: 'b', p: { x: b.x + b.w / 2, y: b.y + b.h } }, { side: 'l', p: { x: b.x, y: b.y + b.h / 2 } },
      { side: 'a', p: { x: b.x + b.w / 2, y: b.y + b.h / 2 } },
    ];
    if (['path', 'polygon', 'polyline'].includes(el.localName) && !isConn(el)) {
      for (const [i, q] of this.magnets(el).entries()) out.push({ side: 'n' + i, p: q, magnet: true });
    }
    return out;
  }

  /** Knoten eines Pfades als Magnetpunkte (höchstens 24) */
  magnets(el) {
    let d = el.getAttribute('d');
    if (!d && el.getAttribute('points')) d = 'M' + el.getAttribute('points').trim().replace(/[\s,]+/g, ' ');
    if (!d) return [];
    try {
      const M = docMatrix(el, this.ed.root);
      const subs = toNodes(parsePath(d));
      const pts = [];
      for (const s of subs) for (const n of s.nodes) { pts.push(pt(M, n.x, n.y)); if (pts.length >= 24) return pts; }
      return pts;
    } catch { return []; }
  }

  /** Lage eines Endes; other = Lage des anderen Endes (für automatische Seite) → { p, dir } */
  endPoint(end, other, conn) {
    if (!end) return null;
    if (!end.id) return { p: { x: end.x, y: end.y }, dir: null };
    const el = this.byId(end.id);
    if (!el || el === conn) return null;
    const b = docBounds(el, this.ed.root);
    if (!b) return null;
    if (/^n\d+$/.test(end.side)) {
      const m = this.magnets(el)[+end.side.slice(1)];
      if (m) return { p: m, dir: null };
    }
    let side = end.side;
    const cx = b.x + b.w / 2, cy = b.y + b.h / 2;
    if (!DIR[side]) {
      const o = other || { x: cx + 1, y: cy };
      const dx = (o.x - cx) / Math.max(1, b.w), dy = (o.y - cy) / Math.max(1, b.h);
      side = Math.abs(dx) >= Math.abs(dy) ? (dx >= 0 ? 'r' : 'l') : (dy >= 0 ? 'b' : 't');
    }
    const p = { t: { x: cx, y: b.y }, b: { x: cx, y: b.y + b.h }, l: { x: b.x, y: cy }, r: { x: b.x + b.w, y: cy } }[side];
    return { p, dir: DIR[side] };
  }

  /** Mitte einer verbundenen Form (für die automatische Seite des anderen Endes) */
  centerOf(end) {
    if (!end) return null;
    if (!end.id) return { x: end.x, y: end.y };
    const el = this.byId(end.id), b = el && docBounds(el, this.ed.root);
    return b ? { x: b.x + b.w / 2, y: b.y + b.h / 2 } : null;
  }

  /** Punkte der Strecke (Zeichenflächen-Koordinaten) */
  points(o, A, B) {
    const a = A.p, b = B.p;
    const dA = A.dir || this.facing(a, b), dB = B.dir || this.facing(b, a);
    if (o.r === 'straight') return { pts: [a, b] };
    const dist = Math.hypot(b.x - a.x, b.y - a.y);
    if (o.r === 'curve') {
      const k = Math.max(24, dist * 0.4);
      return { curve: [a, { x: a.x + dA[0] * k, y: a.y + dA[1] * k }, { x: b.x + dB[0] * k, y: b.y + dB[1] * k }, b] };
    }
    // rechtwinklig: kurze Ansätze in Richtung der Seiten, dazwischen ein bzw. zwei Knicke
    const st = Math.min(20, Math.max(8, dist / 4));
    const A1 = { x: a.x + dA[0] * st, y: a.y + dA[1] * st }, B1 = { x: b.x + dB[0] * st, y: b.y + dB[1] * st };
    const hA = dA[0] !== 0, hB = dB[0] !== 0, m = Math.max(0, Math.min(1, o.m));
    let pts;
    if (hA && hB) {
      const mx = a.x + (b.x - a.x) * m;
      if ((mx - a.x) * dA[0] >= 0 && (mx - b.x) * dB[0] >= 0) pts = [a, { x: mx, y: a.y }, { x: mx, y: b.y }, b];
      else { const my = a.y + (b.y - a.y) * m; pts = [a, A1, { x: A1.x, y: my }, { x: B1.x, y: my }, B1, b]; }
    } else if (!hA && !hB) {
      const my = a.y + (b.y - a.y) * m;
      if ((my - a.y) * dA[1] >= 0 && (my - b.y) * dB[1] >= 0) pts = [a, { x: a.x, y: my }, { x: b.x, y: my }, b];
      else { const mx = a.x + (b.x - a.x) * m; pts = [a, A1, { x: mx, y: A1.y }, { x: mx, y: B1.y }, B1, b]; }
    } else if (hA) {
      // waagerecht → senkrecht: ein Knick, wenn beide Richtungen passen
      if ((b.x - a.x) * dA[0] > 0 && (a.y - b.y) * dB[1] > 0) pts = [a, { x: b.x, y: a.y }, b];
      else pts = [a, A1, { x: A1.x, y: B1.y }, B1, b];
    } else {
      if ((b.y - a.y) * dA[1] > 0 && (a.x - b.x) * dB[0] > 0) pts = [a, { x: a.x, y: b.y }, b];
      else pts = [a, A1, { x: B1.x, y: A1.y }, B1, b];
    }
    return { pts: clean(pts) };
  }

  facing(a, b) {
    const dx = b.x - a.x, dy = b.y - a.y;
    return Math.abs(dx) >= Math.abs(dy) ? [Math.sign(dx) || 1, 0] : [0, Math.sign(dy) || 1];
  }

  /** Verbinder neu berechnen; Rückgabe: geändert? */
  route(conn) {
    const o = parseConn(conn);
    const root = this.ed.root;
    // fehlende Form → Ende frei an der letzten Stelle (aus dem bisherigen Pfad)
    // ungekürzte Enden der letzten Berechnung (der Pfad selbst endet vor den Spitzen)
    const last = conn.__ge ? { a: conn.__ge.A, b: conn.__ge.B } : this.lastEnds(conn);
    let fixed = false;
    for (const k of ['a', 'b']) if (o[k]?.id && !this.byId(o[k].id)) { o[k] = last ? { ...last[k] } : { x: 0, y: 0 }; fixed = true; }
    // der Verbinder selbst wurde verschoben: freie Enden wandern mit, verbundene bleiben
    const T = conn.transform?.baseVal?.length ? DOMMatrix.fromMatrix(conn.transform.baseVal.consolidate().matrix) : null;
    if (T) {
      const P = parentMatrix(conn, root), Pi = P.inverse();
      for (const k of ['a', 'b']) if (o[k] && !o[k].id) { const loc = pt(Pi, o[k].x, o[k].y), moved = pt(P.multiply(T), loc.x, loc.y); o[k] = { x: moved.x, y: moved.y }; fixed = true; }
      conn.removeAttribute('transform');
    }
    if (fixed) writeConn(conn, o);
    const A = this.endPoint(o.a, this.centerOf(o.b), conn), B = this.endPoint(o.b, this.centerOf(o.a), conn);
    if (!A || !B) return false;
    const r = this.points(o, A, B);
    const sw = parseFloat(getProp(conn, 'stroke-width')) || 1;
    const trim = (h, list, atEnd) => {
      const H = HEADS[h];
      if (!H || list.length < 2) return;
      const len = (H.tip - H.ref) / 10 * o.s * sw;
      if (len <= 0) return;
      const i = atEnd ? list.length - 1 : 0, j = atEnd ? list.length - 2 : 1;
      const p = list[i], q = list[j], d = Math.hypot(p.x - q.x, p.y - q.y) || 1;
      list[i] = { x: p.x + (q.x - p.x) / d * Math.min(len, d * 0.9), y: p.y + (q.y - p.y) / d * Math.min(len, d * 0.9) };
    };
    const list = r.curve ? [...r.curve] : [...r.pts];
    trim(o.hs, list, false); trim(o.he, list, true);
    // in den Raum des Verbinders (Elternelement)
    const Pi = parentMatrix(conn, root).inverse();
    const L = list.map(p => pt(Pi, p.x, p.y));
    const d = r.curve ? `M${num(L[0].x)} ${num(L[0].y)}C${num(L[1].x)} ${num(L[1].y)} ${num(L[2].x)} ${num(L[2].y)} ${num(L[3].x)} ${num(L[3].y)}`
      : 'M' + L.map(p => `${num(p.x)} ${num(p.y)}`).join('L');
    const changed = conn.getAttribute('d') !== d;
    if (changed) conn.setAttribute('d', d);
    conn.__ge = { A: A.p, B: B.p, pts: r.pts || null, o };
    return changed;
  }

  /** Enden des bisherigen Pfades (Zeichenflächen-Koordinaten) – für gelöste Enden */
  lastEnds(conn) {
    try {
      const segs = parsePath(conn.getAttribute('d') || '');
      const M = docMatrix(conn, this.ed.root);
      const ps = transformPath(segs, M).flatMap(s => { const out = []; for (let i = 0; i + 1 < s.p.length; i += 2) out.push({ x: s.p[i], y: s.p[i + 1] }); return out; });
      return ps.length ? { a: ps[0], b: ps[ps.length - 1] } : null;
    } catch { return null; }
  }

  /** Beschriftung in die Mitte des Verbinders */
  placeLabel(conn) {
    const lab = conn.id && this.ed.root.querySelector(`text[data-ge-for="${CSS.escape(conn.id)}"]`);
    if (!lab) return;
    let mid;
    try { const len = conn.getTotalLength(); mid = conn.getPointAtLength(len / 2); } catch { return; }
    const P = docMatrix(conn, this.ed.root), q = pt(P, mid.x, mid.y);
    const L = pt(docMatrix(lab.parentNode, this.ed.root).inverse(), q.x, q.y);
    lab.removeAttribute('transform');
    const fs = parseFloat(getProp(lab, 'font-size')) || 14;
    const x = num(L.x), y = num(L.y + fs * 0.35);
    if (lab.getAttribute('x') !== x || lab.getAttribute('y') !== y) {
      lab.setAttribute('x', x); lab.setAttribute('y', y);
      for (const ts of lab.querySelectorAll(':scope > tspan[x]')) ts.setAttribute('x', x);
    }
  }

  /** Text in einer Form: Rahmenbreite und Lage folgen der Form (waagerecht und senkrecht zentriert) */
  fitInner(text) {
    const shape = this.byId(text.getAttribute('data-ge-in'));
    if (!shape) { text.removeAttribute('data-ge-in'); return; }
    const b = docBounds(shape, this.ed.root);
    if (!b) return;
    const pad = Math.max(4, Math.min(16, b.w * 0.08));
    const Pi = docMatrix(text.parentNode, this.ed.root).inverse();
    const c = pt(Pi, b.x + b.w / 2, b.y + b.h / 2);
    const w = Math.max(10, b.w - pad * 2) * (Math.hypot(Pi.a, Pi.b) || 1);   // Breite im Raum des Textes
    const key = [num(c.x), num(c.y), num(w), text.textContent, getProp(text, 'font-size'), getProp(text, 'font-family'), text.getAttribute('data-ge-lh') || ''].join('|');
    if (this.fitKeys.get(text) === key && !text.hasAttribute('transform')) return;
    text.removeAttribute('transform');
    setProp(text, 'text-anchor', 'middle');
    text.setAttribute('data-ge-frame', num(w, 2));
    text.setAttribute('x', num(c.x));
    if (!text.getAttribute('y')) text.setAttribute('y', num(c.y));
    if (this.ed.textEl !== text) layoutText(text, readText(text));
    // senkrecht mittig: Mitte der Textbox auf die Mitte der Form
    try {
      const bb = text.getBBox();
      const y = parseFloat(text.getAttribute('y')) || 0;
      const ny = y + (c.y - (bb.y + bb.height / 2));
      if (Math.abs(ny - y) > 0.01) text.setAttribute('y', num(ny));
    } catch { /* nicht gezeichnet */ }
    this.fitKeys.set(text, key);
  }

  /** Alle Verbinder, Beschriftungen und Texte in Formen nachführen (vor jedem Zeichnen und vor jedem Protokollschritt) */
  update() {
    const root = this.ed.root;
    if (!root) return;
    const conns = root.querySelectorAll('path[data-ge-conn]');
    const inner = root.querySelectorAll('text[data-ge-in]');
    if (!conns.length && !inner.length) return;
    for (const t of inner) this.fitInner(t);
    for (const c of conns) { this.route(c); this.placeLabel(c); }
  }

  // ------------------------------------------------------------------ Anlegen und Ändern

  /** Neuer Verbinder zwischen zwei Enden (Spezifikation wie parseConn) */
  create(a, b, opts = {}) {
    const ed = this.ed;
    const el = document.createElementNS(NS, 'path');
    el.id = uid(ed.root, 'verbinder-');
    el.setAttribute('fill', 'none');
    const stroke = ed.style.stroke && ed.style.stroke !== 'none' ? ed.style.stroke : '#1F2430';
    el.setAttribute('stroke', stroke);
    el.setAttribute('stroke-width', num(ed.format === 'icon' ? 1.5 : Math.max(1, ed.style.strokeWidth || 2)));
    el.setAttribute('stroke-linejoin', 'round');
    el.setAttribute('data-name', opts.name || 'Verbinder');
    const o = { a, b, r: opts.r || 'ortho', m: 0.5, hs: opts.hs || 'none', he: opts.he || 'triangle', s: opts.s || 4 };
    writeConn(el, o);
    // Verbinder liegen unter den Formen (wie in Diagramm-Programmen): vor dem ersten verbundenen Element einfügen
    const first = [a, b].map(e => e?.id && this.byId(e.id)).filter(Boolean).find(x => x.parentNode === ed.target);
    if (first) ed.target.insertBefore(el, first); else ed.target.append(el);
    this.applyHeads(el);
    this.route(el);
    return el;
  }

  /** Ende eines Verbinders an eine Form (Anker) bzw. einen Punkt hängen */
  setEnd(conn, which, end) {
    const o = parseConn(conn);
    o[which] = end;
    writeConn(conn, o);
    this.route(conn);
  }

  /** Einstellungen ändern (r, m, hs, he, s) */
  set(conn, patch) {
    const o = { ...parseConn(conn), ...patch };
    writeConn(conn, o);
    if ('hs' in patch || 'he' in patch || 's' in patch) this.applyHeads(conn);
    this.route(conn);
  }

  /** Pfeilspitzen als Marker in der Farbe der Kontur (gleiche Art/Farbe/Größe wird wiederverwendet) */
  applyHeads(conn) {
    const o = parseConn(conn);
    const color = hex(getProp(conn, 'stroke')) || '#1F2430';
    for (const [attr, type] of [['marker-start', o.hs], ['marker-end', o.he]]) {
      const H = HEADS[type];
      if (!H) { setProp(conn, attr, null); continue; }
      const key = `${type} ${num(o.s, 2)} ${color}`;
      const d = defs(this.ed.root);
      let m = [...d.querySelectorAll('marker[data-ge-head]')].find(x => x.getAttribute('data-ge-head') === key);
      if (!m) {
        m = document.createElementNS(NS, 'marker');
        Object.entries({ id: uid(this.ed.root, 'spitze-'), viewBox: '0 0 10 10', refX: num(H.ref, 2), refY: '5', markerWidth: num(o.s, 2), markerHeight: num(o.s, 2),
          orient: 'auto-start-reverse', overflow: 'visible', 'data-ge-head': key }).forEach(([k, v]) => m.setAttribute(k, v));
        const p = document.createElementNS(NS, 'path');
        p.setAttribute('d', H.d);
        if (H.stroke) { p.setAttribute('fill', 'none'); p.setAttribute('stroke', color); p.setAttribute('stroke-width', '1.5'); p.setAttribute('stroke-linejoin', 'round'); p.setAttribute('stroke-linecap', 'round'); }
        else p.setAttribute('fill', color);
        m.append(p);
        d.append(m);
      }
      setProp(conn, attr, `url(#${m.id})`);
    }
  }

  /** Beschriftung anlegen (oder vorhandene liefern) */
  label(conn, text = 'Text') {
    if (!conn.id) conn.id = uid(this.ed.root, 'verbinder-');
    let lab = this.ed.root.querySelector(`text[data-ge-for="${CSS.escape(conn.id)}"]`);
    if (lab) return lab;
    lab = document.createElementNS(NS, 'text');
    lab.setAttribute('data-ge-for', conn.id);
    lab.setAttribute('text-anchor', 'middle');
    lab.setAttribute('font-size', num(this.ed.format === 'info' ? 14 : 6));
    lab.setAttribute('font-family', this.ed.opt.fontFamily || 'sans-serif');
    lab.setAttribute('fill', '#1F2430');
    // Hintergrund als Kontur unter der Schrift (paint-order) – lesbar auch auf der Linie
    lab.setAttribute('stroke', '#FFFFFF'); lab.setAttribute('stroke-width', '4'); lab.setAttribute('stroke-linejoin', 'round'); lab.setAttribute('paint-order', 'stroke');
    lab.setAttribute('data-name', 'Beschriftung');
    lab.textContent = text;
    conn.after(lab);
    this.placeLabel(conn);
    return lab;
  }

  /** Text in eine Form setzen (Rahmen in der Form, zentriert) – vorhandenen liefern */
  innerText(shape, text = '') {
    if (!shape.id) shape.id = uid(this.ed.root, 'form-');
    let t = this.ed.root.querySelector(`text[data-ge-in="${CSS.escape(shape.id)}"]`);
    if (t) return t;
    t = document.createElementNS(NS, 'text');
    t.setAttribute('data-ge-in', shape.id);
    const b = docBounds(shape, this.ed.root);
    const fs = this.ed.format === 'info' ? Math.max(10, Math.min(18, (b?.h || 60) / 4)) : 6;
    t.setAttribute('font-size', num(fs));
    t.setAttribute('font-family', this.ed.opt.fontFamily || 'sans-serif');
    t.setAttribute('fill', '#1F2430');
    t.setAttribute('y', '0');
    t.textContent = text;
    shape.after(t);
    this.fitInner(t);
    return t;
  }

  /** Lesereihenfolge des Diagramms (für die Beschreibung): „A → B (Beschriftung)“ je Verbinder, Fluss vom Start an */
  describe() {
    const root = this.ed.root;
    const name = el => {
      if (!el) return '?';
      const inner = el.id && root.querySelector(`text[data-ge-in="${CSS.escape(el.id)}"]`);
      return (inner?.textContent || el.querySelector?.('text')?.textContent || el.getAttribute('data-name') || el.id || '').trim().replace(/\s+/g, ' ');
    };
    const edges = [...root.querySelectorAll('path[data-ge-conn]')].map(c => {
      const o = parseConn(c);
      const lab = c.id && root.querySelector(`text[data-ge-for="${CSS.escape(c.id)}"]`);
      return { a: o.a?.id ? this.byId(o.a.id) : null, b: o.b?.id ? this.byId(o.b.id) : null, label: lab?.textContent.trim() || '' };
    }).filter(e => e.a && e.b);
    if (!edges.length) return '';
    // Reihenfolge: Breitensuche von Knoten ohne eingehende Verbinder
    const incoming = new Set(edges.map(e => e.b));
    const starts = [...new Set(edges.map(e => e.a))].filter(n => !incoming.has(n));
    const seen = new Set(), order = [];
    const queue = starts.length ? [...starts] : [edges[0].a];
    while (queue.length) {
      const n = queue.shift();
      if (seen.has(n)) continue;
      seen.add(n);
      for (const e of edges.filter(x => x.a === n)) { order.push(e); queue.push(e.b); }
    }
    for (const e of edges) if (!order.includes(e)) order.push(e);
    return order.map((e, i) => `${i + 1}. ${name(e.a)} → ${name(e.b)}${e.label ? ` (${e.label})` : ''}`).join('\n');
  }

  /**
   * Baum anordnen (Organigramm): Wurzel = ausgewähltes Element ohne eingehenden Verbinder; Kinder über Verbinder a → b.
   * dir 'down' (oben nach unten) oder 'right'; gap = Abstand zwischen Geschwistern bzw. Ebenen.
   */
  layoutTree(rootEl, dir = 'down', gap = 40) {
    const root = this.ed.root;
    const edges = [...root.querySelectorAll('path[data-ge-conn]')].map(c => parseConn(c)).filter(o => o.a?.id && o.b?.id);
    const kids = n => edges.filter(o => o.a.id === n.id).map(o => this.byId(o.b.id)).filter(Boolean);
    const size = n => { const b = docBounds(n, root); return b ? (dir === 'down' ? { main: b.w, cross: b.h } : { main: b.h, cross: b.w }) : { main: 0, cross: 0 }; };
    const seen = new Set();
    // Breite eines Teilbaums
    const span = n => {
      if (seen.has(n)) return 0;
      seen.add(n);
      const c = kids(n);
      const own = size(n).main;
      const sum = c.reduce((s, k) => s + span(k), 0) + Math.max(0, c.length - 1) * gap;
      n.__span = Math.max(own, sum);
      return n.__span;
    };
    span(rootEl);
    seen.clear();
    // Ebenenhöhen
    const levels = [];
    const depth = (n, d) => { if (seen.has(n)) return; seen.add(n); levels[d] = Math.max(levels[d] || 0, size(n).cross); for (const k of kids(n)) depth(k, d + 1); };
    depth(rootEl, 0);
    seen.clear();
    const b0 = docBounds(rootEl, root);
    const startMain = (dir === 'down' ? b0.x + b0.w / 2 : b0.y + b0.h / 2) - rootEl.__span / 2;
    const startCross = dir === 'down' ? b0.y : b0.x;
    const moved = [];
    const place = (n, d, from) => {
      if (seen.has(n)) return;
      seen.add(n);
      const b = docBounds(n, root);
      const cross = startCross + levels.slice(0, d).reduce((s, v) => s + v + gap * 1.5, 0);
      const mainCenter = from + n.__span / 2;
      const tx = dir === 'down' ? mainCenter - (b.x + b.w / 2) : cross - b.x;
      const ty = dir === 'down' ? cross - b.y : mainCenter - (b.y + b.h / 2);
      if (Math.abs(tx) > 0.01 || Math.abs(ty) > 0.01) { this.ed.moveBy(n, tx, ty); moved.push(n); }
      let cur = from + (n.__span - (kids(n).reduce((s, k) => s + k.__span, 0) + Math.max(0, kids(n).length - 1) * gap)) / 2;
      for (const k of kids(n)) { place(k, d + 1, cur); cur += k.__span + gap; }
    };
    place(rootEl, 0, startMain);
    // Baum ragt links bzw. oben über die Zeichenfläche: als Ganzes hereinschieben (der Abstand zum Rand = halber Abstand)
    const vb = this.ed.vb;
    const all = [...seen];
    const bs = all.map(n => docBounds(n, root)).filter(Boolean);
    const minX = Math.min(...bs.map(b => b.x)), minY = Math.min(...bs.map(b => b.y));
    const dx = minX < vb.x ? vb.x + gap / 2 - minX : 0, dy = minY < vb.y ? vb.y + gap / 2 - minY : 0;
    if (dx || dy) for (const n of all) { this.ed.moveBy(n, dx, dy); if (!moved.includes(n)) moved.push(n); }
    // Verbinder im Baum: Seiten passend zur Richtung
    for (const c of root.querySelectorAll('path[data-ge-conn]')) {
      const o = parseConn(c);
      if (!o.a?.id || !o.b?.id || !seen.has(this.byId(o.a.id)) || !seen.has(this.byId(o.b.id))) continue;
      this.set(c, { a: { id: o.a.id, side: dir === 'down' ? 'b' : 'r' }, b: { id: o.b.id, side: dir === 'down' ? 't' : 'l' }, r: 'ortho', m: 0.5 });
    }
    return moved;
  }
}

/** doppelte und auf einer Linie liegende Punkte entfernen */
function clean(pts) {
  const out = [];
  for (const p of pts) {
    const l = out[out.length - 1];
    if (l && Math.abs(l.x - p.x) < 0.01 && Math.abs(l.y - p.y) < 0.01) continue;
    out.push(p);
  }
  for (let i = out.length - 2; i > 0; i--) {
    const a = out[i - 1], b = out[i], c = out[i + 1];
    if ((Math.abs(a.x - b.x) < 0.01 && Math.abs(b.x - c.x) < 0.01) || (Math.abs(a.y - b.y) < 0.01 && Math.abs(b.y - c.y) < 0.01)) out.splice(i, 1);
  }
  return out;
}

export { pathToString };
