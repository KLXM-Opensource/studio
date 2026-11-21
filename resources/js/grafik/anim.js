/*
 * Grafik-Editor – Bewegung (Persona Motion, Ausbaustufe 4): Modell, Zeitrechnung und Vorschau. Gleiche Rechnung wie die Website
 * (Core\Graphics\Motion erzeugt daraus CSS): Schlüsselbilder je Eigenschaft, Verlauf je Abschnitt (cubic-bezier), Bewegung
 * um den Drehpunkt im Raum des Elternelements:
 *   X = T(p) · translate(dx, dy) · rotate(r) · scale(s) · T(−p)   (auf der Website: CSS translate/rotate/scale, transform-origin p)
 * Modell am Element: data-ge-anim = Base64(JSON) {p, k: {pos, s, r, o, w, f, c}, n?, tr?, on?}; Szene am <svg>:
 * data-ge-motion="d=4;dl=0;it=1;dir=normal;tr=view". Links/Tooltips/Hotspots: data-ge-link, -linklabel, -tip, -tipbox, -hot.
 * Alles liegt im Dokument – Rückgängig/Wiederholen gelten damit genau wie für jede andere Änderung. MIT
 */
import { NS, num, localMatrix, docMatrix, parentMatrix, pt, fmtMatrix } from './geom.js';
import { getProp } from './doc.js';

export const PROPS = ['pos', 's', 'r', 'o', 'w', 'f', 'c'];
export const COLOR_PROPS = ['f', 'c'];
export const REST = { pos: [0, 0], s: 1, r: 0, o: 1, w: 1 };
/** Verläufe (Name → cubic-bezier); hold = Sprung am Ende des Abschnitts */
export const EASES = {
  smooth: [0.42, 0, 0.58, 1], out: [0, 0, 0.58, 1], in: [0.42, 0, 1, 1], linear: [0, 0, 1, 1], spring: [0.34, 1.56, 0.64, 1], hold: null,
};
export const ATTRS = ['data-ge-anim', 'data-ge-link', 'data-ge-linklabel', 'data-ge-tip', 'data-ge-tipbox', 'data-ge-hot'];
export const SCENE_DEFAULT = { d: 4, dl: 0, it: 1, dir: 'normal', tr: 'view' };

// ---------------------------------------------------------------- Lesen und Schreiben

const b64 = s => btoa(String.fromCharCode(...new TextEncoder().encode(s)));
const unb64 = s => new TextDecoder().decode(Uint8Array.from(atob(s), c => c.charCodeAt(0)));

export function getAnim(el) {
  const v = el?.getAttribute?.('data-ge-anim');
  if (!v) return null;
  try { const a = JSON.parse(unb64(v)); return a && typeof a === 'object' ? { k: {}, ...a } : null; } catch { return null; }
}

/** Bewegung setzen (null/leer = entfernen). Schlüsselbilder sortiert, Werte gerundet */
export function setAnim(el, a) {
  if (!a || (!Object.keys(a.k || {}).some(p => a.k[p]?.length) && !a.n)) {
    el.removeAttribute('data-ge-anim');
    return;
  }
  const k = {};
  for (const p of PROPS) {
    const list = a.k?.[p];
    if (!list?.length) continue;
    k[p] = list.map(x => x.map((v, i) => (typeof v === 'number' && i < (p === 'pos' ? 3 : 2) ? +num(v, i ? 4 : 3) : v))).sort((x, y) => x[0] - y[0]);
  }
  const out = { p: (a.p || [0, 0]).map(v => +num(v, 3)), k };
  if (a.n) out.n = a.n;
  if (a.tr) out.tr = a.tr;
  if (a.on) out.on = a.on;
  el.setAttribute('data-ge-anim', b64(JSON.stringify(out)));
}

export function getScene(root) {
  const s = { ...SCENE_DEFAULT };
  for (const part of (root.getAttribute('data-ge-motion') || '').split(';')) {
    const [k, v] = part.split('=');
    if (!k || v === undefined) continue;
    if (['d', 'dl', 'it'].includes(k)) s[k] = Math.max(0, +v || 0);
    else s[k] = v;
  }
  if (!(s.d > 0)) s.d = 4;
  return s;
}
export function setScene(root, s) {
  root.setAttribute('data-ge-motion', `d=${num(s.d, 3)};dl=${num(s.dl, 3)};it=${Math.round(s.it)};dir=${s.dir};tr=${s.tr}`);
}

/** Elemente mit Bewegung bzw. Interaktion (Dokumentreihenfolge) */
export const animated = root => [...root.querySelectorAll('[data-ge-anim]')];
export const interactive = root => [...root.querySelectorAll('[data-ge-link],[data-ge-hot],[data-ge-tip]')];
export const hasMotion = root => root.hasAttribute('data-ge-motion') || !!root.querySelector(ATTRS.map(a => `[${a}]`).join(','));

/** Text zählt hoch? (nicht in Kurven umwandeln) */
export const isCountText = el => el.localName === 'text' && !!getAnim(el)?.n;

// ---------------------------------------------------------------- Zeit und Verlauf

/** cubic-bezier wie CSS (Newton-Verfahren) */
export function bez(p, x) {
  if (!p) return x >= 1 ? 1 : 0;
  const cx = 3 * p[0], bx = 3 * (p[2] - p[0]) - cx, ax = 1 - cx - bx, cy = 3 * p[1], by = 3 * (p[3] - p[1]) - cy, ay = 1 - cy - by;
  let t = x;
  for (let i = 0; i < 10; i++) { const f = ((ax * t + bx) * t + cx) * t - x, d = (3 * ax * t + 2 * bx) * t + cx; if (Math.abs(f) < 1e-6 || !d) break; t -= f / d; }
  // Bisektion, falls Newton nicht trifft
  if (Math.abs(((ax * t + bx) * t + cx) * t - x) > 1e-4) { let lo = 0, hi = 1; t = x; for (let i = 0; i < 30; i++) { const f = ((ax * t + bx) * t + cx) * t; if (f < x) lo = t; else hi = t; t = (lo + hi) / 2; } }
  t = Math.max(0, Math.min(1, t));
  return ((ay * t + by) * t + cy) * t;
}
export const easePts = e => (Array.isArray(e) ? e : e in EASES ? EASES[e] : EASES.smooth);
export const easeOf = (prop, key) => (prop === 'pos' ? key[3] : key[2]) ?? 'smooth';

/** Wert einer Spur zur Zeit t (s) */
export function valueAt(prop, keys, t) {
  if (!keys?.length) return null;
  const val = k => (prop === 'pos' ? [k[1], k[2]] : k[1]);
  if (t <= keys[0][0]) return val(keys[0]);
  const last = keys[keys.length - 1];
  if (t >= last[0]) return val(last);
  for (let i = 0; i < keys.length - 1; i++) {
    const a = keys[i], b = keys[i + 1];
    if (t < a[0] || t > b[0]) continue;
    const u = b[0] === a[0] ? 1 : bez(easePts(easeOf(prop, a)), (t - a[0]) / (b[0] - a[0]));
    if (prop === 'pos') return [a[1] + (b[1] - a[1]) * u, a[2] + (b[2] - a[2]) * u];
    if (COLOR_PROPS.includes(prop)) return mix(a[1], b[1], u, a[3], b[3]);
    return a[1] + (b[1] - a[1]) * u;
  }
  return val(last);
}
/** Farben mischen (sRGB wie CSS); dunkle Werte daneben */
function mix(x, y, u, xd, yd) {
  const c = (h, i) => parseInt(h.slice(1 + i * 2, 3 + i * 2), 16);
  const one = (p, q) => '#' + [0, 1, 2].map(i => Math.round(c(p, i) + (c(q, i) - c(p, i)) * Math.max(0, Math.min(1, u))).toString(16).padStart(2, '0')).join('');
  const out = new String(one(x, y));
  out.dark = one(xd || x, yd || y);
  return out;
}

/** Zustand eines Elements zur Szenen-Zeit t: {dx, dy, s, r, o, w, f?, c?} */
export function stateAt(a, t) {
  const k = a?.k || {};
  const pos = valueAt('pos', k.pos, t) || [0, 0];
  const st = { dx: pos[0], dy: pos[1], s: valueAt('s', k.s, t) ?? 1, r: valueAt('r', k.r, t) ?? 0, o: valueAt('o', k.o, t) ?? 1, w: valueAt('w', k.w, t) ?? 1 };
  for (const p of COLOR_PROPS) if (k[p]?.length) st[p] = valueAt(p, k[p], t);
  return st;
}

/** Szenen-Zeit aus der Laufzeit s (Verzögerung, Wiederholungen, Richtung) – wie CSS und js/graphic-motion.js */
export function localTime(sc, s) {
  const dur = sc.d, it = sc.it;
  s -= sc.dl;
  if (s <= 0) return sc.dir === 'reverse' || sc.dir === 'alternate-reverse' ? dur : 0;
  const total = it === 0 ? Infinity : it * dur;
  if (s >= total) s = total - 1e-9;
  const n = Math.floor(s / dur);
  const u = s - n * dur;
  const back = sc.dir === 'reverse' || (sc.dir === 'alternate' && n % 2) || (sc.dir === 'alternate-reverse' && !(n % 2));
  return back ? dur - u : u;
}
export const totalTime = sc => (sc.it === 0 ? Infinity : sc.dl + sc.d * Math.max(1, sc.it));

/** Matrix der Bewegung im Raum des Elternelements */
export function motionMatrix(p, st) {
  return new DOMMatrix().translate(p[0], p[1]).translate(st.dx, st.dy).rotate(st.r).scale(st.s).translate(-p[0], -p[1]);
}

/** Drehpunkt: Mitte des Rahmens im Raum des Elternelements (ohne Vorschau-Bewegung, aus getBBox und transform) */
export function pivotOf(el) {
  let b;
  try { b = el.getBBox(); } catch { return [0, 0]; }
  const M = localMatrix(el);
  const c = [[b.x, b.y], [b.x + b.width, b.y], [b.x + b.width, b.y + b.height], [b.x, b.y + b.height]].map(([x, y]) => pt(M, x, y));
  const xs = c.map(q => q.x), ys = c.map(q => q.y);
  return [(Math.min(...xs) + Math.max(...xs)) / 2, (Math.min(...ys) + Math.max(...ys)) / 2];
}

/** Rahmen im Raum des Elternelements (für fertige Bewegungen: Wege relativ zur Größe) */
export function boxOf(el) {
  let b;
  try { b = el.getBBox(); } catch { return { x: 0, y: 0, w: 1, h: 1 }; }
  const M = localMatrix(el);
  const c = [[b.x, b.y], [b.x + b.width, b.y], [b.x + b.width, b.y + b.height], [b.x, b.y + b.height]].map(([x, y]) => pt(M, x, y));
  const xs = c.map(q => q.x), ys = c.map(q => q.y);
  return { x: Math.min(...xs), y: Math.min(...ys), w: Math.max(...xs) - Math.min(...xs), h: Math.max(...ys) - Math.min(...ys) };
}

// ---------------------------------------------------------------- Fertige Bewegungen

/**
 * Fertige Bewegungen (aus der Erweiterung Motion übernommen): Schlüsselbilder über die Dauer verteilt. Wege in Anteilen der
 * Zeichenfläche (W = Breite, H = Höhe), damit sie in jeder Größe gleich wirken.
 */
export const PRESETS = {
  fade: { group: 'in', icon: 'circle-half', keys: { o: [0, 1] } },
  left: { group: 'in', icon: 'arrow-right', keys: { pos: [['-W', 0], [0, 0]], o: [0, 1] }, ease: 'out' },
  right: { group: 'in', icon: 'arrow-left', keys: { pos: [['W', 0], [0, 0]], o: [0, 1] }, ease: 'out' },
  up: { group: 'in', icon: 'arrow-up', keys: { pos: [[0, 'H'], [0, 0]], o: [0, 1] }, ease: 'out' },
  down: { group: 'in', icon: 'arrow-down', keys: { pos: [[0, '-H'], [0, 0]], o: [0, 1] }, ease: 'out' },
  zoom: { group: 'in', icon: 'magnifying-glass-plus', keys: { s: [0.3, 1], o: [0, 1] }, ease: 'out' },
  pop: { group: 'in', icon: 'sparkle', keys: { s: [0, 1] }, ease: 'spring' },
  spin: { group: 'loop', icon: 'arrows-clockwise', keys: { r: [0, 360] }, ease: 'linear' },
  pulse: { group: 'loop', icon: 'heartbeat', keys: { s: [1, 1.12, 1] } },
  shake: { group: 'loop', icon: 'waves', keys: { r: [0, -8, 8, -5, 0] } },
  float: { group: 'loop', icon: 'align-center-vertical', keys: { pos: [[0, 0], [0, '-h'], [0, 0]] } },
  draw: { group: 'loop', icon: 'pen-nib', keys: { w: [0, 1] }, stroke: true },
  out: { group: 'out', icon: 'eye-slash', keys: { o: [1, 0] } },
  count: { group: 'special', icon: 'hash', text: true },
  follow: { group: 'special', icon: 'path', path: true },
};

/** Wert eines Weges: 'W' = 20 % der Breite, 'H' = 20 % der Höhe, 'h' = 3 % der Höhe */
const way = (v, vb) => typeof v !== 'string' ? v : (v[0] === '-' ? -1 : 1) * ({ W: vb.w * 0.2, H: vb.h * 0.2, h: vb.h * 0.03 }[v.replace('-', '')] || 0);

/** Schlüsselbilder einer fertigen Bewegung (ersetzen die betroffenen Spuren) */
export function presetKeys(key, start, len, vb) {
  const p = PRESETS[key];
  if (!p?.keys) return null;
  const out = {};
  for (const [prop, vals] of Object.entries(p.keys)) {
    const n = vals.length;
    out[prop] = vals.map((v, i) => {
      const t = start + (n > 1 ? len * i / (n - 1) : 0), e = p.ease || 'smooth';
      return prop === 'pos' ? [t, way(v[0], vb), way(v[1], vb), e] : [t, v, e];
    });
  }
  return out;
}

/** Zahl im Text (de/en): Wert, Nachkommastellen, Gruppen- und Dezimalzeichen */
export function parseNumber(text, lang = document.documentElement.lang || 'de') {
  const m = String(text).match(/[-+−]?\d[\d.,'   ]*\d|\d/);
  if (!m) return null;
  const s = m[0].replace('−', '-').replace(/[  ]/g, ' ');
  // Deutsch: Punkt = Tausender, Komma = Dezimal; Englisch umgekehrt. Eindeutig, wenn beide vorkommen oder ein Zeichen mehrfach.
  const de = !/^en/i.test(lang);
  let p = de ? ',' : '.', g = '';
  const lastC = s.lastIndexOf(','), lastD = s.lastIndexOf('.');
  const one = (c, last) => {
    if (s.indexOf(c) !== last) g = c;
    else if (new RegExp('\\' + c + '\\d{3}$').test(s) && ((c === '.') === de)) g = c;
    else p = c;
  };
  if (lastC > -1 && lastD > -1) { p = lastC > lastD ? ',' : '.'; g = p === ',' ? '.' : ','; }
  else if (lastC > -1) one(',', lastC);
  else if (lastD > -1) one('.', lastD);
  if (!g && /['  ]/.test(s)) g = s.match(/['  ]/)[0];
  const clean = s.split(g || '\u0000').join('').replace(p, '.').replace(/\s/g, '');
  const v = parseFloat(clean);
  if (!Number.isFinite(v)) return null;
  const d = p && clean.includes('.') ? clean.split('.')[1].length : 0;
  return { value: v, d, g, p, index: m.index, length: m[0].length };
}
/** Zahl wie auf der Website formatieren */
export function formatNumber(v, n) {
  const [i, f] = Math.abs(v).toFixed(n.d).split('.');
  return (v < 0 ? '−' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, n.g) + (f ? n.p + f : '');
}

/** Pfad folgen: Punkte gleichmäßig auf dem Pfad (Dokumentraum) mit Verlauf über die Zeit; Drehung entlang der Richtung */
export function followKeys(ed, el, path, start, len, ease = 'smooth', orient = false, steps = 36) {
  const L = path.getTotalLength();
  const toDoc = docMatrix(path, ed.root);
  const toParent = parentMatrix(el, ed.root).inverse();
  const p = pivotOf(el);
  const at = l => { const q = path.getPointAtLength(Math.max(0, Math.min(L, l))); const d = pt(toDoc, q.x, q.y); return pt(toParent, d.x, d.y); };
  const E = easePts(ease);
  const first = at(0);
  const pos = [], rot = [];
  let lastAng = null;
  for (let i = 0; i <= steps; i++) {
    const u = i / steps, l = L * bez(E, u), q = at(l);
    pos.push([start + len * u, q.x - p[0], q.y - p[1], 'linear']);
    if (orient) {
      const a = at(Math.max(0, l - L / 400)), b = at(Math.min(L, l + L / 400));
      let ang = Math.atan2(b.y - a.y, b.x - a.x) * 180 / Math.PI;
      if (lastAng !== null) while (ang - lastAng > 180) ang -= 360;
      if (lastAng !== null) while (ang - lastAng < -180) ang += 360;
      lastAng = ang;
      rot.push([start + len * u, ang, 'linear']);
    }
  }
  return orient ? { pos, r: rot } : { pos };
}

// ---------------------------------------------------------------- Vorschau im Editor

/** CSS-Selektor eines Elements im Shadow Root: über die id, sonst Pfad ab dem nächsten Vorfahren mit id bzw. der Wurzel */
export function selectorOf(el, root) {
  const chain = [];
  for (let n = el; n && n !== root; n = n.parentNode) {
    if (n.id) { chain.unshift('#' + CSS.escape(n.id)); return chain.join(' > '); }
    chain.unshift(`:nth-child(${[...n.parentNode.children].indexOf(n) + 1})`);
  }
  return ':host > svg:not(.ge-twin) > ' + chain.join(' > ');
}

/**
 * Regeln für die Vorschau zur Zeit t (Szenen-Zeit). dark: Farben der Dunkel-Fassung. Rückgabe: CSS-Text. Das Dokument bleibt
 * unverändert (die Regeln liegen in einem eigenen <style> im Shadow Root).
 */
export function previewCss(root, t, { dark = false, minOpacity = 0 } = {}) {
  let css = '';
  for (const el of animated(root)) {
    const a = getAnim(el);
    if (!a) continue;
    const st = stateAt(a, t), k = a.k || {};
    const sel = selectorOf(el, root);
    const decl = [];
    if (k.pos || k.s || k.r) {
      const M = motionMatrix(a.p || pivotOf(el), st).multiply(localMatrix(el));
      decl.push(`transform:matrix(${[M.a, M.b, M.c, M.d, M.e, M.f].map(v => num(v, 6)).join(',')})`, 'transform-origin:0 0', 'transform-box:view-box');
    }
    if (k.o) {
      const base = parseFloat(el.getAttribute('opacity') ?? '1');
      decl.push(`opacity:${num(Math.max(minOpacity, (Number.isFinite(base) ? base : 1) * st.o), 4)}`);
    }
    if (decl.length) css += `${sel}{${decl.map(d => d + ' !important').join(';')}}`;
    if (k.f) css += `${sel},${sel} *{fill:${dark ? st.f.dark : st.f} !important}`;
    if (k.c) css += `${sel},${sel} *{stroke:${dark ? st.c.dark : st.c} !important}`;
    if (k.w) {
      const shapes = ['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon'].includes(el.localName) ? [el] : [...el.querySelectorAll('path,rect,circle,ellipse,line,polyline,polygon')];
      for (const s of shapes) {
        let L = 0;
        try { L = s.getTotalLength(); } catch { /* nicht gezeichnet */ }
        if (!L) continue;
        const ss = s === el ? sel : selectorOf(s, root);
        css += `${ss}{stroke-dasharray:${num(L, 2)} ${num(L, 2)} !important;stroke-dashoffset:${num(L * (1 - st.w), 2)} !important}`;
      }
    }
  }
  return css;
}

/**
 * Standbild für Desktop/PDF: Zustand zur Zeit t fest ins SVG schreiben (transform, opacity, Farben, Strichlänge). host = gezeichnete
 * Kopie (für Pfadlängen), Rückgabe: SVG-Text.
 */
export function bakeAt(svgRoot, t, dark = false) {
  for (const el of animated(svgRoot)) {
    const a = getAnim(el);
    if (!a) continue;
    const st = stateAt(a, t), k = a.k || {};
    if (k.pos || k.s || k.r) {
      const M = motionMatrix(a.p || [0, 0], st).multiply(localMatrix(el));
      el.setAttribute('transform', fmtMatrix(M));
    }
    if (k.o) {
      const base = parseFloat(el.getAttribute('opacity') ?? '1');
      el.setAttribute('opacity', num((Number.isFinite(base) ? base : 1) * st.o, 4));
    }
    for (const p of COLOR_PROPS) if (k[p]) {
      const v = dark ? st[p].dark : String(st[p]);
      const list = [el, ...el.querySelectorAll('*')];
      for (const s of list) { if (s.localName === 'stop' || s.localName === 'title' || s.localName === 'desc') continue; s.setAttribute(p === 'f' ? 'fill' : 'stroke', v); s.style?.removeProperty(p === 'f' ? 'fill' : 'stroke'); }
    }
    if (k.w && st.w < 1) {
      const shapes = [el, ...el.querySelectorAll('*')].filter(s => ['path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon'].includes(s.localName));
      for (const s of shapes) {
        let L = 0;
        try { L = s.getTotalLength(); } catch { /* */ }
        if (!L) continue;
        s.setAttribute('stroke-dasharray', `${num(L, 2)} ${num(L, 2)}`);
        s.setAttribute('stroke-dashoffset', num(L * (1 - st.w), 2));
      }
    }
  }
}

export { NS, getProp };
