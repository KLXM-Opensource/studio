/*
 * Grafik-Editor – Verzerren (Ausbaustufe 3b): Objekt → Perspektivisch verzerren (4 Eckgriffe, projektive Abbildung) bzw.
 * Hülle verzerren (4 Ecken + 4 Kantenmitten, Coons-Fläche mit gebogenen Kanten). Die Pfade werden im Dokumentraum abgebildet:
 * Strecken bleiben bei der Perspektive exakt Strecken (projektiv), Kurven werden vorher in Stücke geteilt (de Casteljau) – so
 * folgt die Form der Abbildung genau; bei der Hülle werden auch Strecken unterteilt. Texte vorher in Kurven (mit Rückfrage).
 * Griffe ziehen = Vorschau, ↵ oder Klick daneben = übernehmen (ein Schritt), Esc = abbrechen. MIT
 */
import { t } from '../_i18n.js';
import { NS, num, docMatrix, parentMatrix, parsePath, pathToString, transformPath, toNodes, shapeToPath } from './geom.js';
import { eachShape, isLocked } from './doc.js';
import { modal } from './ui.js';
import { toast } from '../_toast.js';

const lerp = (a, b, s) => ({ x: a.x + (b.x - a.x) * s, y: a.y + (b.y - a.y) * s });
/** Kubik in n gleiche Stücke teilen */
function split(c, n) {
  const out = [];
  let cur = c;
  for (let k = n; k > 1; k--) {
    const s = 1 / k, [p0, p1, p2, p3] = cur;
    const a = lerp(p0, p1, s), b = lerp(p1, p2, s), d = lerp(p2, p3, s), e = lerp(a, b, s), f = lerp(b, d, s), m = lerp(e, f, s);
    out.push([p0, a, e, m]);
    cur = [m, f, d, p3];
  }
  out.push(cur);
  return out;
}

/** Projektive Abbildung Einheitsquadrat → Viereck q[0..3] (nw, ne, se, sw) */
function homography(q) {
  const [p0, p1, p2, p3] = q;
  const dx1 = p1.x - p2.x, dx2 = p3.x - p2.x, dy1 = p1.y - p2.y, dy2 = p3.y - p2.y;
  const sx = p0.x - p1.x + p2.x - p3.x, sy = p0.y - p1.y + p2.y - p3.y;
  const den = dx1 * dy2 - dx2 * dy1 || 1e-12;
  const g = (sx * dy2 - dx2 * sy) / den, h = (dx1 * sy - sx * dy1) / den;
  const a = p1.x - p0.x + g * p1.x, b = p3.x - p0.x + h * p3.x, c = p0.x;
  const d = p1.y - p0.y + g * p1.y, e = p3.y - p0.y + h * p3.y, f = p0.y;
  return (u, v) => { const w = g * u + h * v + 1; return { x: (a * u + b * v + c) / w, y: (d * u + e * v + f) / w }; };
}

/** Coons-Fläche: Ecken nw, ne, se, sw und Kantenmitten n, e, s, w (Kanten als quadratische Kurven durch die Mitte) */
function coons(c, m) {
  const quad = (a, mid, b) => { const k = { x: 2 * mid.x - (a.x + b.x) / 2, y: 2 * mid.y - (a.y + b.y) / 2 }; return s => ({ x: (1 - s) ** 2 * a.x + 2 * s * (1 - s) * k.x + s * s * b.x, y: (1 - s) ** 2 * a.y + 2 * s * (1 - s) * k.y + s * s * b.y }); };
  const top = quad(c[0], m[0], c[1]), bot = quad(c[3], m[2], c[2]), left = quad(c[0], m[3], c[3]), right = quad(c[1], m[1], c[2]);
  return (u, v) => {
    const T = top(u), B = bot(u), L = left(v), R = right(v);
    const bil = { x: (1 - u) * (1 - v) * c[0].x + u * (1 - v) * c[1].x + u * v * c[2].x + (1 - u) * v * c[3].x, y: (1 - u) * (1 - v) * c[0].y + u * (1 - v) * c[1].y + u * v * c[2].y + (1 - u) * v * c[3].y };
    return { x: (1 - v) * T.x + v * B.x + (1 - u) * L.x + u * R.x - bil.x, y: (1 - v) * T.y + v * B.y + (1 - u) * L.y + u * R.y - bil.y };
  };
}

export class Warp {
  constructor(ed) { this.ed = ed; this.s = null; }
  get active() { return !!this.s; }

  /** Sitzung starten: mode 'persp' | 'env' */
  async start(mode) {
    const ed = this.ed;
    if (this.s) this.commit();
    if (!ed.sel.length) return;
    if (ed.sel.some(el => el.localName === 'text' || el.querySelector?.('text'))) {
      const r = await modal(ed.el, { title: t('Verzerren'), ok: t('In Kurven umwandeln und verzerren'), body: `<p>${t('Texte lassen sich nur als Kurven verzerren. Sie werden dafür in Kurven umgewandelt (mit ⌘Z rückgängig).')}</p>` });
      if (!r) return;
      await ed.toCurves();
    }
    const items = [];
    for (const top of ed.sel.filter(el => !isLocked(el))) eachShape(top, el => {
      if (el.closest('defs,clipPath,mask,marker,symbol,pattern')) return;
      const d = el.localName === 'path' ? el.getAttribute('d') : shapeToPath(el);
      if (d) items.push({ el, subs: toNodes(transformPath(parsePath(d), docMatrix(el, ed.root))) });
    });
    if (!items.length) { toast(t('Nichts zu verzerren – bitte Formen oder Kurven auswählen.'), 'info'); return; }
    let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
    for (const it of items) for (const s of it.subs) for (const n of s.nodes) for (const p of [n, n.i, n.o]) if (p) { x0 = Math.min(x0, p.x); y0 = Math.min(y0, p.y); x1 = Math.max(x1, p.x); y1 = Math.max(y1, p.y); }
    const b = { x: x0, y: y0, w: Math.max(1e-6, x1 - x0), h: Math.max(1e-6, y1 - y0) };
    const c = [{ x: x0, y: y0 }, { x: x1, y: y0 }, { x: x1, y: y1 }, { x: x0, y: y1 }];
    const m = [lerp(c[0], c[1], .5), lerp(c[1], c[2], .5), lerp(c[3], c[2], .5), lerp(c[0], c[3], .5)];
    this.s = { mode, items, b, c, m, snap: items.map(it => [...it.el.attributes].map(a => [a.name, a.value])), els: items.map(it => it.el) };
    ed.hint(mode === 'persp' ? t('Perspektive: Ecken ziehen. ↵ oder Klick daneben übernimmt, Esc bricht ab.') : t('Hülle: Ecken und Kantenmitten ziehen. ↵ oder Klick daneben übernimmt, Esc bricht ab.'));
    ed.draw();
  }

  /** Abbildung auf alle Formen anwenden (Vorschau) */
  apply() {
    const { mode, items, b, c, m } = this.s, ed = this.ed;
    const F = mode === 'persp' ? homography(c) : coons(c, m);
    const map = p => F((p.x - b.x) / b.w, (p.y - b.y) / b.h);
    const n = mode === 'persp' ? 4 : 6;
    for (const it of items) {
      const segs = [];
      for (const s of it.subs) {
        const N = s.nodes;
        if (!N.length) continue;
        segs.push({ c: 'M', p: [map(N[0]).x, map(N[0]).y] });
        const cnt = s.closed ? N.length : N.length - 1;
        for (let i = 0; i < cnt; i++) {
          const a = N[i], z = N[(i + 1) % N.length];
          const line = !a.o && !z.i;
          if (line && mode === 'persp') { const q = map(z); segs.push({ c: 'L', p: [q.x, q.y] }); continue; }
          for (const piece of split([a, a.o || a, z.i || z, z], n)) {
            const [, p1, p2, p3] = piece.map(map);
            segs.push({ c: 'C', p: [p1.x, p1.y, p2.x, p2.y, p3.x, p3.y] });
          }
        }
        if (s.closed) segs.push({ c: 'Z', p: [] });
      }
      let el = it.el;
      if (el.localName !== 'path') {
        const p = document.createElementNS(NS, 'path');
        for (const a of el.attributes) if (!['x', 'y', 'width', 'height', 'rx', 'ry', 'cx', 'cy', 'r', 'x1', 'y1', 'x2', 'y2', 'points'].includes(a.name)) p.setAttribute(a.name, a.value);
        el.replaceWith(p);
        ed.sel = ed.sel.map(x => x === el ? p : x);
        it.el = el = p;
      }
      el.removeAttribute('transform');
      el.setAttribute('d', pathToString(transformPath(segs, parentMatrix(el, ed.root).inverse()), 3));
    }
    ed.draw();
  }

  /** Griffe in der Überlagerung */
  overlay() {
    if (!this.s) return '';
    const ed = this.ed, { c, m, mode } = this.s;
    const P = p => ed.toOv(p);
    const q = c.map(P);
    let s = `<polygon class="ge-ov__warp" points="${q.map(p => p.x + ',' + p.y).join(' ')}"/>`;
    q.forEach((p, i) => { s += `<rect class="ge-ov__h ge-ov__wh" data-wh="c${i}" x="${p.x - 6}" y="${p.y - 6}" width="12" height="12"/>`; });
    if (mode === 'env') m.map(P).forEach((p, i) => { s += `<circle class="ge-ov__h ge-ov__wh" data-wh="m${i}" cx="${p.x}" cy="${p.y}" r="6"/>`; });
    return s;
  }

  down(e, key) {
    e.stopPropagation(); e.preventDefault();
    const ed = this.ed, s = this.s;
    if (!s) return;
    const arr = key[0] === 'c' ? s.c : s.m, i = +key.slice(1);
    const start = { ...arr[i] }, p0 = ed.toDoc(e.clientX, e.clientY);
    ed.track(e, p => {
      arr[i] = { x: start.x + p.x - p0.x, y: start.y + p.y - p0.y };
      // Perspektive: Kantenmitten folgen den Ecken (für einen späteren Wechsel zur Hülle)
      if (s.mode === 'persp') s.m = [lerp(s.c[0], s.c[1], .5), lerp(s.c[1], s.c[2], .5), lerp(s.c[3], s.c[2], .5), lerp(s.c[0], s.c[3], .5)];
      this.apply();
    });
  }

  commit() {
    if (!this.s) return;
    const changed = this.s.items.some((it, k) => it.el !== this.s.els[k] || it.el.getAttribute('d') !== (this.s.snap[k].find(a => a[0] === 'd') || [])[1]);
    const mode = this.s.mode;
    this.s = null;
    this.ed.hint();
    if (changed) this.ed.commit(mode === 'persp' ? t('Perspektivisch verzerrt') : t('Hülle verzerrt'));
    else this.ed.draw();
  }

  cancel() {
    if (!this.s) return;
    const s = this.s;
    this.s = null;
    s.items.forEach((it, k) => {
      let el = it.el;
      const orig = s.els[k];
      if (el !== orig) { el.replaceWith(orig); this.ed.sel = this.ed.sel.map(x => x === el ? orig : x); el = orig; }
      for (const a of [...el.attributes]) el.removeAttribute(a.name);
      for (const [n, v] of s.snap[k]) el.setAttribute(n, v);
    });
    this.ed.hint();
    this.ed.draw();
  }
}

export { num };
