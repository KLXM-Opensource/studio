/*
 * Grafik-Editor – Pfade bearbeiten auf dem Knotenmodell (geom.js → toNodes/fromNodes), ohne Abhängigkeiten:
 * Punkt auf der Kurve finden, Knoten einfügen (de Casteljau – die Form bleibt exakt), an einem Knoten auftrennen,
 * Endpunkte verbinden, schließen, umkehren, Knotenarten (Ecke, glatt, symmetrisch), Teilpfade trennen. MIT
 */
import { pt } from './geom.js';

const lerp = (a, b, t) => ({ x: a.x + (b.x - a.x) * t, y: a.y + (b.y - a.y) * t });
const near = (a, b, eps = 1e-6) => Math.abs(a.x - b.x) < eps && Math.abs(a.y - b.y) < eps;
const copy = n => ({ x: n.x, y: n.y, i: n.i && { ...n.i }, o: n.o && { ...n.o } });

/** Anzahl der Segmente eines Teilpfads */
export const segCount = sub => sub.nodes.length < 2 ? 0 : (sub.closed ? sub.nodes.length : sub.nodes.length - 1);

/** Kontrollpunkte von Segment k: [Start, Griff 1, Griff 2, Ende] */
export function segPoints(sub, k) {
  const a = sub.nodes[k], b = sub.nodes[(k + 1) % sub.nodes.length];
  return [a, a.o || a, b.i || b, b];
}

/** Punkt auf einer kubischen Kurve */
export function bez(p, t) {
  const u = 1 - t;
  return {
    x: u * u * u * p[0].x + 3 * u * u * t * p[1].x + 3 * u * t * t * p[2].x + t * t * t * p[3].x,
    y: u * u * u * p[0].y + 3 * u * u * t * p[1].y + 3 * u * t * t * p[2].y + t * t * t * p[3].y,
  };
}

/**
 * Nächster Punkt auf den Segmenten (M: lokaler Raum → Dokument, p im Dokument).
 * @return {{si:number, k:number, t:number, d:number, x:number, y:number}|null} d = Abstand im Dokument
 */
export function nearestSeg(subs, p, M) {
  let best = null;
  subs.forEach((sub, si) => {
    for (let k = 0; k < segCount(sub); k++) {
      const P = segPoints(sub, k).map(q => pt(M, q.x, q.y));
      const dist = t => { const q = bez(P, t); return Math.hypot(q.x - p.x, q.y - p.y); };
      let bt = 0, bd = Infinity;
      for (let i = 0; i <= 32; i++) { const t = i / 32, d = dist(t); if (d < bd) { bd = d; bt = t; } }
      // verfeinern (Intervallschachtelung um den besten Stützpunkt)
      let lo = Math.max(0, bt - 1 / 32), hi = Math.min(1, bt + 1 / 32);
      for (let i = 0; i < 30; i++) {
        const a = lo + (hi - lo) / 3, b = hi - (hi - lo) / 3;
        if (dist(a) < dist(b)) hi = b; else lo = a;
      }
      const t = (lo + hi) / 2, d = dist(t);
      if (!best || d < best.d) { const q = bez(P, t); best = { si, k, t, d, x: q.x, y: q.y }; }
    }
  });
  return best;
}

/** Knoten auf Segment k bei t einfügen – Form bleibt exakt gleich. Rückgabe: Index des neuen Knotens */
export function insertNode(sub, k, t) {
  const a = sub.nodes[k], bi = (k + 1) % sub.nodes.length, b = sub.nodes[bi];
  let n;
  if (!a.o && !b.i) {
    const q = lerp(a, b, t);
    n = { x: q.x, y: q.y, i: null, o: null };
  } else {
    const [P0, P1, P2, P3] = segPoints(sub, k);
    const A = lerp(P0, P1, t), B = lerp(P1, P2, t), C = lerp(P2, P3, t), D = lerp(A, B, t), E = lerp(B, C, t), F = lerp(D, E, t);
    a.o = A; b.i = C;
    n = { x: F.x, y: F.y, i: D, o: E };
  }
  sub.nodes.splice(k + 1, 0, n);
  return k + 1;
}

/**
 * An Knoten ni auftrennen. Geschlossen: wird offen (Anfang und Ende am Knoten). Offen: zwei Teilpfade.
 * Rückgabe: Liste der Teilpfade, die an die Stelle von sub treten.
 */
export function splitAt(sub, ni) {
  const n = sub.nodes.length;
  if (sub.closed) {
    const nodes = [];
    for (let i = 0; i < n; i++) nodes.push(copy(sub.nodes[(ni + i) % n]));
    const first = nodes[0], last = copy(first);
    first.i = null; last.o = null;
    nodes.push(last);
    return [{ closed: false, nodes }];
  }
  if (ni <= 0 || ni >= n - 1) return [sub];
  const a = sub.nodes.slice(0, ni + 1).map(copy), b = sub.nodes.slice(ni).map(copy);
  a[a.length - 1].o = null; b[0].i = null;
  return [{ closed: false, nodes: a }, { closed: false, nodes: b }];
}

/** Richtung umkehren */
export function reverseSub(sub) {
  sub.nodes = sub.nodes.reverse().map(n => ({ x: n.x, y: n.y, i: n.o, o: n.i }));
  if (sub.closed && sub.nodes.length > 1) sub.nodes.unshift(sub.nodes.pop());   // gleicher Startpunkt
  return sub;
}

/** Offenen Teilpfad schließen (fallen Anfang und Ende zusammen, werden sie ein Knoten) */
export function closeSub(sub) {
  if (sub.closed || sub.nodes.length < 2) return sub;
  const f = sub.nodes[0], l = sub.nodes[sub.nodes.length - 1];
  if (near(f, l, 1e-3)) { f.i = l.i; sub.nodes.pop(); }
  sub.closed = sub.nodes.length > 1;
  return sub;
}

/** Ist Knoten ni ein Endpunkt eines offenen Teilpfads? 'start' | 'end' | null */
export function endOf(sub, ni) {
  if (sub.closed) return null;
  if (ni === 0) return 'start';
  if (ni === sub.nodes.length - 1) return 'end';
  return null;
}

/**
 * Zwei Endpunkte verbinden: derselbe Teilpfad → schließen; zwei Teilpfade → einer (fallen die Punkte zusammen,
 * wird es ein Knoten, sonst eine gerade Strecke). subs wird geändert; Rückgabe: true, wenn verbunden.
 */
export function joinEnds(subs, [sa, na], [sb, nb]) {
  const A = subs[sa], B = subs[sb];
  if (!A || !B) return false;
  const ea = endOf(A, na), eb = endOf(B, nb);
  if (!ea || !eb) return false;
  if (A === B) { if (ea === eb) return false; closeSub(A); return true; }
  if (ea === 'start') reverseSub(A);
  if (eb === 'end') reverseSub(B);
  const last = A.nodes[A.nodes.length - 1], first = B.nodes[0];
  if (near(last, first, 1e-3)) { last.o = first.o; A.nodes.push(...B.nodes.slice(1)); }
  else A.nodes.push(...B.nodes);
  subs.splice(subs.indexOf(B), 1);
  return true;
}

/** Endpunkte zweier Teilpfadlisten, die am nächsten beieinander liegen → [[si, ni], [sj, nj]] */
export function nearestEnds(subs, skip = new Set()) {
  let best = null;
  const ends = [];
  subs.forEach((s, si) => { if (!s.closed && s.nodes.length && !skip.has(si)) { ends.push([si, 0]); ends.push([si, s.nodes.length - 1]); } });
  for (let i = 0; i < ends.length; i++) for (let j = i + 1; j < ends.length; j++) {
    if (ends[i][0] === ends[j][0]) continue;
    const a = subs[ends[i][0]].nodes[ends[i][1]], b = subs[ends[j][0]].nodes[ends[j][1]];
    const d = Math.hypot(a.x - b.x, a.y - b.y);
    if (!best || d < best.d) best = { d, a: ends[i], b: ends[j] };
  }
  return best;
}

/** Art eines Knotens aus seinen Griffen: corner (ohne bzw. mit freien Griffen), smooth (in Linie), symmetric */
export function nodeKind(n) {
  if (!n.i || !n.o) return n.i || n.o ? 'half' : 'corner';
  const a = Math.atan2(n.y - n.i.y, n.x - n.i.x), b = Math.atan2(n.o.y - n.y, n.o.x - n.x);
  let d = Math.abs(a - b) % (2 * Math.PI);
  if (d > Math.PI) d = 2 * Math.PI - d;
  if (d > 0.035) return 'cusp';
  const li = Math.hypot(n.x - n.i.x, n.y - n.i.y), lo = Math.hypot(n.o.x - n.x, n.o.y - n.y);
  return Math.abs(li - lo) <= Math.max(li, lo) * 0.01 + 1e-9 ? 'symmetric' : 'smooth';
}

/** Knotenart setzen: corner = Griffe weg, smooth = Griffe in eine Linie (Längen bleiben), symmetric = gleich lang */
export function setNodeKind(sub, ni, kind) {
  const n = sub.nodes[ni];
  if (!n) return;
  if (kind === 'corner') { n.i = n.o = null; return; }
  const cnt = sub.nodes.length;
  const prev = sub.closed || ni > 0 ? sub.nodes[(ni - 1 + cnt) % cnt] : null, next = sub.closed || ni < cnt - 1 ? sub.nodes[(ni + 1) % cnt] : null;
  // Richtung: aus vorhandenen Griffen, sonst aus den Nachbarn
  let dx, dy;
  if (n.i && n.o) { dx = n.o.x - n.i.x; dy = n.o.y - n.i.y; }
  else if (n.o) { dx = n.o.x - n.x; dy = n.o.y - n.y; }
  else if (n.i) { dx = n.x - n.i.x; dy = n.y - n.i.y; }
  else { const a = prev || n, b = next || n; dx = b.x - a.x; dy = b.y - a.y; }
  const l = Math.hypot(dx, dy) || 1; dx /= l; dy /= l;
  const dist = q => q ? Math.hypot(q.x - n.x, q.y - n.y) : 0;
  let li = n.i ? dist(n.i) : dist(prev) / 3, lo = n.o ? dist(n.o) : dist(next) / 3;
  if (kind === 'symmetric') li = lo = (li + lo) / 2 || Math.max(li, lo);
  n.i = prev || n.i ? { x: n.x - dx * li, y: n.y - dy * li } : null;
  n.o = next || n.o ? { x: n.x + dx * lo, y: n.y + dy * lo } : null;
}
