/*
 * Grafik-Editor – Geometrie: Matrizen (DOMMatrix), Pfaddaten lesen/schreiben/umrechnen, Knotenmodell für das Direktauswahl-
 * Werkzeug, Glätten für den Bleistift. Ohne Abhängigkeiten. MIT
 */

export const NS = 'http://www.w3.org/2000/svg';

/** Zahl kompakt (höchstens 3 Nachkommastellen, ohne -0) */
export function num(n, d = 3) {
  const f = 10 ** d;
  let v = Math.round(n * f) / f;
  if (Object.is(v, -0) || Math.abs(v) < 1 / f) v = 0;
  return String(v);
}

export const ident = () => new DOMMatrix();

/** Eigene Transformation eines Elements (transform-Attribut), sonst Einheitsmatrix */
export function localMatrix(el) {
  const list = el.transform?.baseVal;
  if (!list || !list.numberOfItems) return new DOMMatrix();
  const m = list.consolidate()?.matrix;
  return m ? new DOMMatrix([m.a, m.b, m.c, m.d, m.e, m.f]) : new DOMMatrix();
}

/** Matrix vom Koordinatenraum des Elements in den des Dokuments (viewBox der Wurzel) */
export function docMatrix(el, root) {
  if (!el || el === root) return new DOMMatrix();
  const a = root.getScreenCTM(), b = el.getScreenCTM?.();
  if (a && b) return DOMMatrix.fromMatrix(a).inverse().multiply(DOMMatrix.fromMatrix(b));
  // nicht gezeichnet (display:none): Kette der transform-Attribute
  let m = new DOMMatrix();
  for (let n = el; n && n !== root; n = n.parentNode) m = localMatrix(n).multiply(m);
  return m;
}

/** Matrix des Elternraums (in dem das transform-Attribut des Elements gilt) */
export function parentMatrix(el, root) {
  return el.parentNode && el.parentNode !== root ? docMatrix(el.parentNode, root) : new DOMMatrix();
}

export const pt = (m, x, y) => { const p = new DOMPoint(x, y).matrixTransform(m); return { x: p.x, y: p.y }; };

export function fmtMatrix(m) {
  const near = (v, w) => Math.abs(v - w) < 1e-9;
  if (near(m.a, 1) && near(m.b, 0) && near(m.c, 0) && near(m.d, 1)) {
    return near(m.e, 0) && near(m.f, 0) ? '' : `translate(${num(m.e)} ${num(m.f)})`;
  }
  return `matrix(${[m.a, m.b, m.c, m.d].map(v => num(v, 6)).join(' ')} ${num(m.e)} ${num(m.f)})`;
}

/** Zerlegen: Verschiebung, Drehung (Grad), Skalierung, Scherung (Grad) */
export function decompose(m) {
  const sx = Math.hypot(m.a, m.b);
  const rot = Math.atan2(m.b, m.a);
  const shear = sx ? (m.a * m.c + m.b * m.d) / sx : 0;
  const sy = sx ? (m.a * m.d - m.b * m.c) / sx : Math.hypot(m.c, m.d);
  return { tx: m.e, ty: m.f, rot: rot * 180 / Math.PI, sx, sy, skew: Math.atan2(shear, sy || 1) * 180 / Math.PI };
}

/** Achsenparalleler Rahmen von Punkten */
export function boundsOf(points) {
  let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
  for (const p of points) { x0 = Math.min(x0, p.x); y0 = Math.min(y0, p.y); x1 = Math.max(x1, p.x); y1 = Math.max(y1, p.y); }
  return x0 === Infinity ? null : { x: x0, y: y0, w: x1 - x0, h: y1 - y0 };
}

export function union(a, b) {
  if (!a) return b; if (!b) return a;
  const x = Math.min(a.x, b.x), y = Math.min(a.y, b.y);
  return { x, y, w: Math.max(a.x + a.w, b.x + b.w) - x, h: Math.max(a.y + a.h, b.y + b.h) - y };
}

/** Eckpunkte des Rahmens eines Elements im Dokument (gedreht wie das Element) */
export function corners(el, root) {
  let b;
  try { b = el.getBBox(); } catch { return null; }
  if (!b || (!b.width && !b.height && el.localName !== 'line' && el.localName !== 'path' && el.localName !== 'polyline')) {
    if (!b) return null;
  }
  const m = docMatrix(el, root);
  return [pt(m, b.x, b.y), pt(m, b.x + b.width, b.y), pt(m, b.x + b.width, b.y + b.height), pt(m, b.x, b.y + b.height)];
}

export function docBounds(el, root) {
  const c = corners(el, root);
  return c ? boundsOf(c) : null;
}

// ================================================================= Pfaddaten

const ARGS = { M: 2, L: 2, H: 1, V: 1, C: 6, S: 4, Q: 4, T: 2, A: 7, Z: 0 };

/** d → Liste absoluter Befehle [{c, p}] mit nur M L C Q A Z (H/V → L, S → C, T → Q) */
export function parsePath(d) {
  const out = [];
  const s = String(d || '');
  let pos = 0;
  const ws = () => { while (pos < s.length && /[\s,]/.test(s[pos])) pos++; };
  const numRe = /[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?/y;
  const readNum = () => { ws(); numRe.lastIndex = pos; const m = numRe.exec(s); if (!m) return null; pos = numRe.lastIndex; return +m[0]; };
  // Flags in Bögen stehen oft ohne Trenner („a1 1 0 011 1“) – je genau ein Zeichen 0/1
  const readFlag = () => { ws(); const c = s[pos]; if (c !== '0' && c !== '1') return null; pos++; return +c; };
  const peekCmd = () => { ws(); return pos < s.length && /[MmLlHhVvCcSsQqTtAaZz]/.test(s[pos]) ? s[pos] : null; };
  let cmd = '', x = 0, y = 0, sx = 0, sy = 0, lc = null, lq = null;
  while (true) {
    ws();
    if (pos >= s.length) break;
    const pc = peekCmd();
    if (pc) { cmd = pc; pos++; }
    else if (!cmd || cmd === 'Z' || cmd === 'z') break;
    const C = cmd.toUpperCase(), rel = cmd !== C, n = ARGS[C];
    if (C === 'Z') { out.push({ c: 'Z', p: [] }); x = sx; y = sy; lc = lq = null; continue; }
    let a = [];
    if (C === 'A') a = [readNum(), readNum(), readNum(), readFlag(), readFlag(), readNum(), readNum()];
    else for (let k = 0; k < n; k++) a.push(readNum());
    if (a.some(v => v === null || Number.isNaN(v))) break;
    const ax = v => rel ? v + x : v, ay = v => rel ? v + y : v;
    switch (C) {
      case 'M':
        x = ax(a[0]); y = ay(a[1]); sx = x; sy = y; out.push({ c: 'M', p: [x, y] }); lc = lq = null;
        cmd = rel ? 'l' : 'L'; break;
      case 'L': x = ax(a[0]); y = ay(a[1]); out.push({ c: 'L', p: [x, y] }); lc = lq = null; break;
      case 'H': x = rel ? x + a[0] : a[0]; out.push({ c: 'L', p: [x, y] }); lc = lq = null; break;
      case 'V': y = rel ? y + a[0] : a[0]; out.push({ c: 'L', p: [x, y] }); lc = lq = null; break;
      case 'C': { const p = [ax(a[0]), ay(a[1]), ax(a[2]), ay(a[3]), ax(a[4]), ay(a[5])]; out.push({ c: 'C', p }); lc = [p[2], p[3]]; lq = null; x = p[4]; y = p[5]; break; }
      case 'S': { const c1 = lc ? [2 * x - lc[0], 2 * y - lc[1]] : [x, y]; const p = [c1[0], c1[1], ax(a[0]), ay(a[1]), ax(a[2]), ay(a[3])]; out.push({ c: 'C', p }); lc = [p[2], p[3]]; lq = null; x = p[4]; y = p[5]; break; }
      case 'Q': { const p = [ax(a[0]), ay(a[1]), ax(a[2]), ay(a[3])]; out.push({ c: 'Q', p }); lq = [p[0], p[1]]; lc = null; x = p[2]; y = p[3]; break; }
      case 'T': { const q = lq ? [2 * x - lq[0], 2 * y - lq[1]] : [x, y]; const p = [q[0], q[1], ax(a[0]), ay(a[1])]; out.push({ c: 'Q', p }); lq = q; lc = null; x = p[2]; y = p[3]; break; }
      case 'A': { const p = [a[0], a[1], a[2], a[3] ? 1 : 0, a[4] ? 1 : 0, ax(a[5]), ay(a[6])]; out.push({ c: 'A', p }); lc = lq = null; x = p[5]; y = p[6]; break; }
    }
  }
  return out;
}

export function pathToString(segs, d = 3) {
  return segs.map(s => s.c + s.p.map((v, i) => s.c === 'A' && (i === 3 || i === 4) ? String(v) : num(v, d)).join(' ')).join('');
}

/** Bogen → kubische Bézierkurven (SVG-Implementierungshinweise F.6) */
export function arcToCubics(x1, y1, rx, ry, phi, fa, fs, x2, y2) {
  if (!rx || !ry) return [[x1, y1, x2, y2, x2, y2]];
  rx = Math.abs(rx); ry = Math.abs(ry);
  const rad = phi * Math.PI / 180, cos = Math.cos(rad), sin = Math.sin(rad);
  const dx = (x1 - x2) / 2, dy = (y1 - y2) / 2;
  const x1p = cos * dx + sin * dy, y1p = -sin * dx + cos * dy;
  let lam = (x1p * x1p) / (rx * rx) + (y1p * y1p) / (ry * ry);
  if (lam > 1) { rx *= Math.sqrt(lam); ry *= Math.sqrt(lam); }
  const sign = fa === fs ? -1 : 1;
  const num_ = rx * rx * ry * ry - rx * rx * y1p * y1p - ry * ry * x1p * x1p;
  const co = sign * Math.sqrt(Math.max(0, num_ / (rx * rx * y1p * y1p + ry * ry * x1p * x1p)));
  const cxp = co * rx * y1p / ry, cyp = -co * ry * x1p / rx;
  const cx = cos * cxp - sin * cyp + (x1 + x2) / 2, cy = sin * cxp + cos * cyp + (y1 + y2) / 2;
  const ang = (ux, uy, vx, vy) => { const a = Math.atan2(ux * vy - uy * vx, ux * vx + uy * vy); return a; };
  const t1 = ang(1, 0, (x1p - cxp) / rx, (y1p - cyp) / ry);
  let dt = ang((x1p - cxp) / rx, (y1p - cyp) / ry, (-x1p - cxp) / rx, (-y1p - cyp) / ry);
  if (!fs && dt > 0) dt -= 2 * Math.PI; else if (fs && dt < 0) dt += 2 * Math.PI;
  const n = Math.ceil(Math.abs(dt) / (Math.PI / 2) - 1e-9) || 1, step = dt / n;
  const k = 4 / 3 * Math.tan(step / 4);
  const P = (t) => [cx + rx * Math.cos(t) * cos - ry * Math.sin(t) * sin, cy + rx * Math.cos(t) * sin + ry * Math.sin(t) * cos];
  const D = (t) => [-rx * Math.sin(t) * cos - ry * Math.cos(t) * sin, -rx * Math.sin(t) * sin + ry * Math.cos(t) * cos];
  const out = [];
  for (let i = 0; i < n; i++) {
    const a = t1 + i * step, b = a + step, p0 = P(a), p1 = P(b), d0 = D(a), d1 = D(b);
    out.push([p0[0] + k * d0[0], p0[1] + k * d0[1], p1[0] - k * d1[0], p1[1] - k * d1[1], p1[0], p1[1]]);
  }
  return out;
}

/** Alle Punkte eines Pfads mit einer Matrix umrechnen. Bögen nur bei Ähnlichkeitsabbildung, sonst werden sie zu Kurven. */
export function transformPath(segs, m) {
  const P = (x, y) => { const p = pt(m, x, y); return [p.x, p.y]; };
  const sx = Math.hypot(m.a, m.b), sy = Math.hypot(m.c, m.d), similar = Math.abs(sx - sy) < 1e-9 && Math.abs(m.a * m.c + m.b * m.d) < 1e-9;
  const flip = m.a * m.d - m.b * m.c < 0, rot = Math.atan2(m.b, m.a) * 180 / Math.PI;
  const out = [];
  let x = 0, y = 0, x0 = 0, y0 = 0;
  for (const s of segs) {
    const p = s.p;
    if (s.c === 'A') {
      if (similar) out.push({ c: 'A', p: [p[0] * sx, p[1] * sx, p[2] + rot, p[3], flip ? 1 - p[4] : p[4], ...P(p[5], p[6])] });
      else for (const c of arcToCubics(x, y, ...p)) out.push({ c: 'C', p: [...P(c[0], c[1]), ...P(c[2], c[3]), ...P(c[4], c[5])] });
      x = p[5]; y = p[6]; continue;
    }
    if (s.c === 'Z') { out.push({ c: 'Z', p: [] }); x = x0; y = y0; continue; }
    const q = [];
    for (let i = 0; i < p.length; i += 2) q.push(...P(p[i], p[i + 1]));
    out.push({ c: s.c, p: q });
    x = p[p.length - 2]; y = p[p.length - 1];
    if (s.c === 'M') { x0 = x; y0 = y; }
  }
  return out;
}

// ================================================================= Knotenmodell (Direktauswahl, Zeichenstift)

/** Pfad → Teilpfade mit Knoten {x, y, i: {x,y}|null (Griff davor), o: {x,y}|null (Griff danach)} */
export function toNodes(segs) {
  const subs = [];
  let cur = null, x = 0, y = 0;
  const push = (nx, ny) => { const n = { x: nx, y: ny, i: null, o: null }; cur.nodes.push(n); return n; };
  for (const s of segs) {
    const p = s.p;
    if (s.c === 'M') { cur = { closed: false, nodes: [] }; subs.push(cur); push(p[0], p[1]); x = p[0]; y = p[1]; continue; }
    if (!cur) { cur = { closed: false, nodes: [] }; subs.push(cur); push(x, y); }
    if (s.c === 'Z') {
      cur.closed = true;
      const f = cur.nodes[0], l = cur.nodes[cur.nodes.length - 1];
      if (cur.nodes.length > 1 && Math.abs(f.x - l.x) < 1e-6 && Math.abs(f.y - l.y) < 1e-6) { f.i = l.i; cur.nodes.pop(); }
      x = f.x; y = f.y;
      cur = null;
      continue;
    }
    const last = cur.nodes[cur.nodes.length - 1];
    if (s.c === 'L') push(p[0], p[1]);
    else if (s.c === 'C') { last.o = { x: p[0], y: p[1] }; const n = push(p[4], p[5]); n.i = { x: p[2], y: p[3] }; }
    else if (s.c === 'Q') {
      last.o = { x: x + 2 / 3 * (p[0] - x), y: y + 2 / 3 * (p[1] - y) };
      const n = push(p[2], p[3]); n.i = { x: p[2] + 2 / 3 * (p[0] - p[2]), y: p[3] + 2 / 3 * (p[1] - p[3]) };
    } else if (s.c === 'A') {
      let px = x, py = y;
      for (const c of arcToCubics(px, py, ...p)) {
        cur.nodes[cur.nodes.length - 1].o = { x: c[0], y: c[1] };
        const n = push(c[4], c[5]); n.i = { x: c[2], y: c[3] };
        px = c[4]; py = c[5];
      }
    }
    const l2 = cur.nodes[cur.nodes.length - 1]; x = l2.x; y = l2.y;
  }
  return subs.filter(s => s.nodes.length);
}

/** Teilpfade → Befehle (gerade Strecken als L, sonst C) */
export function fromNodes(subs) {
  const out = [];
  // Griff auf dem eigenen Knoten = kein Griff (gerade Strecke bleibt L)
  const same = (h, n) => !h || (Math.abs(h.x - n.x) < 1e-9 && Math.abs(h.y - n.y) < 1e-9);
  const seg = (a, b) => !(same(a.o, a) && same(b.i, b))
    ? { c: 'C', p: [(a.o || a).x, (a.o || a).y, (b.i || b).x, (b.i || b).y, b.x, b.y] }
    : { c: 'L', p: [b.x, b.y] };
  for (const s of subs) {
    if (!s.nodes.length) continue;
    out.push({ c: 'M', p: [s.nodes[0].x, s.nodes[0].y] });
    for (let i = 1; i < s.nodes.length; i++) out.push(seg(s.nodes[i - 1], s.nodes[i]));
    if (s.closed) {
      const a = s.nodes[s.nodes.length - 1], b = s.nodes[0];
      if (s.nodes.length > 1 && (a.o || b.i)) out.push(seg(a, b));
      out.push({ c: 'Z', p: [] });
    }
  }
  return out;
}

/** Formen → Pfaddaten (für „In Kurven umwandeln“) */
export function shapeToPath(el) {
  const n = el.localName, g = a => parseFloat(el.getAttribute(a)) || 0;
  const k = 0.5522847498;
  if (n === 'rect') {
    const x = g('x'), y = g('y'), w = g('width'), h = g('height');
    let rx = el.hasAttribute('rx') ? g('rx') : g('ry'), ry = el.hasAttribute('ry') ? g('ry') : rx;
    rx = Math.min(rx, w / 2); ry = Math.min(ry, h / 2);
    if (!rx || !ry) return `M${num(x)} ${num(y)}H${num(x + w)}V${num(y + h)}H${num(x)}Z`;
    const c = (v) => num(v);
    return `M${c(x + rx)} ${c(y)}H${c(x + w - rx)}C${c(x + w - rx + rx * k)} ${c(y)} ${c(x + w)} ${c(y + ry - ry * k)} ${c(x + w)} ${c(y + ry)}`
      + `V${c(y + h - ry)}C${c(x + w)} ${c(y + h - ry + ry * k)} ${c(x + w - rx + rx * k)} ${c(y + h)} ${c(x + w - rx)} ${c(y + h)}`
      + `H${c(x + rx)}C${c(x + rx - rx * k)} ${c(y + h)} ${c(x)} ${c(y + h - ry + ry * k)} ${c(x)} ${c(y + h - ry)}`
      + `V${c(y + ry)}C${c(x)} ${c(y + ry - ry * k)} ${c(x + rx - rx * k)} ${c(y)} ${c(x + rx)} ${c(y)}Z`;
  }
  if (n === 'circle' || n === 'ellipse') {
    const cx = g('cx'), cy = g('cy'), rx = n === 'circle' ? g('r') : g('rx'), ry = n === 'circle' ? g('r') : g('ry');
    const c = (v) => num(v);
    return `M${c(cx + rx)} ${c(cy)}C${c(cx + rx)} ${c(cy + ry * k)} ${c(cx + rx * k)} ${c(cy + ry)} ${c(cx)} ${c(cy + ry)}`
      + `C${c(cx - rx * k)} ${c(cy + ry)} ${c(cx - rx)} ${c(cy + ry * k)} ${c(cx - rx)} ${c(cy)}`
      + `C${c(cx - rx)} ${c(cy - ry * k)} ${c(cx - rx * k)} ${c(cy - ry)} ${c(cx)} ${c(cy - ry)}`
      + `C${c(cx + rx * k)} ${c(cy - ry)} ${c(cx + rx)} ${c(cy - ry * k)} ${c(cx + rx)} ${c(cy)}Z`;
  }
  if (n === 'line') return `M${num(g('x1'))} ${num(g('y1'))}L${num(g('x2'))} ${num(g('y2'))}`;
  if (n === 'polyline' || n === 'polygon') {
    const v = (el.getAttribute('points') || '').trim().split(/[\s,]+/).map(Number).filter(x => !isNaN(x));
    let d = '';
    for (let i = 0; i + 1 < v.length; i += 2) d += (i ? 'L' : 'M') + num(v[i]) + ' ' + num(v[i + 1]);
    return d + (n === 'polygon' ? 'Z' : '');
  }
  return null;
}

// ================================================================= Bleistift: vereinfachen + glätten

/** Ramer-Douglas-Peucker */
export function simplify(points, eps) {
  if (points.length < 3) return points.slice();
  const d2 = (p, a, b) => {
    const dx = b.x - a.x, dy = b.y - a.y, l = dx * dx + dy * dy;
    let t = l ? ((p.x - a.x) * dx + (p.y - a.y) * dy) / l : 0;
    t = Math.max(0, Math.min(1, t));
    const x = a.x + t * dx - p.x, y = a.y + t * dy - p.y;
    return x * x + y * y;
  };
  const keep = new Uint8Array(points.length); keep[0] = keep[points.length - 1] = 1;
  const stack = [[0, points.length - 1]];
  while (stack.length) {
    const [a, b] = stack.pop();
    let max = 0, idx = -1;
    for (let i = a + 1; i < b; i++) { const d = d2(points[i], points[a], points[b]); if (d > max) { max = d; idx = i; } }
    if (idx > 0 && max > eps * eps) { keep[idx] = 1; stack.push([a, idx], [idx, b]); }
  }
  return points.filter((_, i) => keep[i]);
}

/** Punkte → glatte Knoten (Catmull-Rom als Bézier), tension 0 … 1 */
export function smoothNodes(points, tension = 0.5, closed = false) {
  const n = points.length, k = tension / 3 * 1;
  const nodes = points.map(p => ({ x: p.x, y: p.y, i: null, o: null }));
  for (let i = 0; i < n; i++) {
    const prev = points[closed ? (i - 1 + n) % n : Math.max(0, i - 1)], next = points[closed ? (i + 1) % n : Math.min(n - 1, i + 1)];
    const tx = (next.x - prev.x) * k, ty = (next.y - prev.y) * k;
    if (closed || i > 0) nodes[i].i = { x: points[i].x - tx, y: points[i].y - ty };
    if (closed || i < n - 1) nodes[i].o = { x: points[i].x + tx, y: points[i].y + ty };
  }
  return nodes;
}

/** Punkte eines regelmäßigen Vielecks bzw. Sterns in einem Rahmen */
export function polygonPoints(box, sides, star, inner) {
  const pts = [], n = Math.max(3, Math.min(64, sides | 0)), cx = box.x + box.w / 2, cy = box.y + box.h / 2;
  const count = star ? n * 2 : n;
  for (let i = 0; i < count; i++) {
    const a = -Math.PI / 2 + i * Math.PI * 2 / count, r = star && i % 2 ? inner : 1;
    pts.push(num(cx + Math.cos(a) * box.w / 2 * r) + ',' + num(cy + Math.sin(a) * box.h / 2 * r));
  }
  return pts.join(' ');
}
