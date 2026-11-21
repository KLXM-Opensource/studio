/*
 * Grafik-Editor – Formate aus einem Entwurf (Persona „Bild“, Ausbaustufe 6): die üblichen Social-Media-Größen als weitere
 * Zeichenflächen im selben Dokument. Jede Ebene hat eine einfache Anker-Regel (data-ge-anchor = „h v größe“):
 *   h: l (links) | c (Mitte) | r (rechts) | s (Breite wächst mit)     v: t (oben) | m (Mitte) | b (unten) | s (Höhe wächst mit)
 *   größe: k (skaliert mit dem Format) | f (feste Größe)
 * Ohne Regel gilt ein Vorschlag aus der Lage (Hintergrund = wächst mit, sonst nächster Rand bzw. Mitte). Fotos füllen ihren neuen
 * Rahmen nach dem Fokuspunkt (photo.js), Texte und Gruppen werden nie verzerrt.
 * Eine Zeichenfläche = Gruppe <g data-ge-board="{id}"> rechts neben dem Hauptformat + Bereich in data-ge-slices (k: board,
 * fmt) – danach einzeln nachbesserbar; „Neu aus Hauptformat“ erzeugt sie wieder aus den Regeln. Hauptformat: data-ge-base. MIT
 */
import { NS, num, docBounds } from './geom.js';
import { applyTransform, isDrawn, remapIds } from './doc.js';
import { getSlices, setSlices } from './slices.js';
import { t } from '../_i18n.js';

export const FORMATS = [
  { id: 'og', w: 1200, h: 630, label: () => t('Link-Vorschau 1200 × 630') },
  { id: 'sq', w: 1080, h: 1080, label: () => t('Beitrag quadratisch 1080 × 1080') },
  { id: 'pt', w: 1080, h: 1350, label: () => t('Beitrag hoch 1080 × 1350 (4:5)') },
  { id: 'st', w: 1080, h: 1920, label: () => t('Story 1080 × 1920') },
  { id: 'hd', w: 1920, h: 1080, label: () => t('Kopfbild 1920 × 1080 (16:9)') },
];
export const fmtLabel = f => (FORMATS.find(x => x.id === f.id && x.w === f.w && x.h === f.h)?.label() || t('Frei {w} × {h}', { w: f.w, h: f.h }));

/** Hauptformat (Zeichenfläche „main“): data-ge-base „x y w h“, sonst die ganze Fläche */
export function baseBox(root) {
  const v = (root.getAttribute('data-ge-base') || '').trim().split(/[\s,]+/).map(Number);
  if (v.length === 4 && v.every(Number.isFinite) && v[2] > 0 && v[3] > 0) return { x: v[0], y: v[1], w: v[2], h: v[3] };
  const vb = root.viewBox?.baseVal;
  return vb && vb.width ? { x: vb.x, y: vb.y, w: vb.width, h: vb.height } : { x: 0, y: 0, w: +root.getAttribute('width') || 800, h: +root.getAttribute('height') || 500 };
}

/** Zeichenflächen der Formate: [{id, n, x, y, w, h, fmt}] – zuerst das Hauptformat */
export function boards(root) {
  const b = baseBox(root);
  return [{ id: 'main', n: t('Hauptformat'), ...b, fmt: 'main' }, ...getSlices(root).filter(s => s.k === 'board' && s.fmt)];
}

export function getAnchor(el) {
  const v = (el.getAttribute('data-ge-anchor') || '').trim().split(/\s+/);
  return v.length === 3 && 'lcrs'.includes(v[0]) && 'tmbs'.includes(v[1]) && 'kf'.includes(v[2]) ? { h: v[0], v: v[1], k: v[2], own: true } : null;
}
export function setAnchor(el, a) {
  if (!a) el.removeAttribute('data-ge-anchor'); else el.setAttribute('data-ge-anchor', `${a.h} ${a.v} ${a.k}`);
}

/** Regel eines Elements (eigene oder Vorschlag aus der Lage im Hauptformat) */
export function anchorFor(el, root, base = baseBox(root)) {
  const own = getAnchor(el);
  if (own) return own;
  const b = docBounds(el, root);
  if (!b) return { h: 'c', v: 'm', k: 'k' };
  if (b.w >= base.w * 0.9 && b.h >= base.h * 0.9) return { h: 's', v: 's', k: 'k' };
  const cx = (b.x + b.w / 2 - base.x) / base.w, cy = (b.y + b.h / 2 - base.y) / base.h;
  return { h: cx < 0.34 ? 'l' : cx > 0.66 ? 'r' : 'c', v: cy < 0.34 ? 't' : cy > 0.66 ? 'b' : 'm', k: 'k' };
}

/** Verzerren erlaubt (Breite/Höhe getrennt): Rechtecke, Fotos, Ellipsen, Linien, Pfade; Texte, Gruppen, Verweise nie */
const STRETCH = ['rect', 'image', 'ellipse', 'circle', 'line', 'path', 'polygon', 'polyline'];

/**
 * Elemente (hängen schon im Dokument, Lage = wie im Hauptformat) vom Rahmen base in den Rahmen to legen – nach ihren Regeln.
 * rules: Map Element → Regel (aus dem Original, falls die Elemente Kopien sind).
 */
export function layout(root, els, base, to, rules = new Map()) {
  const u = Math.min(to.w / base.w, to.h / base.h);
  for (const el of els) {
    const b = docBounds(el, root);
    if (!b || !b.w || !b.h) continue;
    const a = rules.get(el) || anchorFor(el, root, base);
    const s = a.k === 'f' ? 1 : u;
    const lm = b.x - base.x, rm = base.x + base.w - b.x - b.w, tm = b.y - base.y, bm = base.y + base.h - b.y - b.h;
    let nw = a.h === 's' ? Math.max(1, to.w - (lm + rm) * u) : b.w * s;
    let nh = a.v === 's' ? Math.max(1, to.h - (tm + bm) * u) : b.h * s;
    if (!STRETCH.includes(el.localName)) {
      // Text, Gruppen: gleichmäßig (passend in den mitwachsenden Rahmen)
      const q = Math.min(nw / b.w, nh / b.h);
      nw = b.w * q; nh = b.h * q;
    }
    const pos = (mode, start, size, nsize, near, far, base0, baseSize, toStart, toSize) => {
      if (mode === 'l' || mode === 't' || mode === 's') return toStart + near * u + (mode === 's' ? (toSize - (near + far) * u - nsize) / 2 : 0);
      if (mode === 'r' || mode === 'b') return toStart + toSize - far * u - nsize;
      return toStart + toSize / 2 + (start + size / 2 - base0 - baseSize / 2) * u - nsize / 2;
    };
    const nx = pos(a.h, b.x, b.w, nw, lm, rm, base.x, base.w, to.x, to.w);
    const ny = pos(a.v, b.y, b.h, nh, tm, bm, base.y, base.h, to.y, to.h);
    const T = new DOMMatrix().translate(nx, ny).scale(nw / b.w, nh / b.h).translate(-b.x, -b.y);
    applyTransform(el, T, root);
  }
}

/** Ebenen des Hauptformats (oberste Ebene, nicht Zeichenflächen-Gruppen), die das Hauptformat berühren */
export function baseLayers(root, base = baseBox(root)) {
  return [...root.children].filter(el => isDrawn(el) && !el.hasAttribute('data-ge-board')).filter(el => {
    const b = docBounds(el, root);
    return b && b.x < base.x + base.w && b.x + b.w > base.x && b.y < base.y + base.h && b.y + b.h > base.y;
  });
}

/**
 * Zeichenfläche für Format f ({id, w, h}) anlegen bzw. (id vorhanden) neu aus dem Hauptformat erzeugen. Rückgabe: id des Bereichs.
 * Die Fläche wächst nach rechts (wie Zeichenflächen in Export).
 */
export function addFormat(root, f, sid = null, newId = null) {
  const base = baseBox(root);
  if (!root.hasAttribute('data-ge-base')) root.setAttribute('data-ge-base', `${num(base.x)} ${num(base.y)} ${num(base.w)} ${num(base.h)}`);
  const list = getSlices(root);
  let s = sid ? list.find(x => x.id === sid) : null;
  const vb = root.viewBox.baseVal;
  if (!s) {
    const gap = Math.round(Math.max(40, Math.min(base.w, base.h) * 0.08));
    const right = Math.max(base.x + base.w, ...list.map(x => x.x + x.w));
    const id = newId || 's' + (Math.max(0, ...list.map(x => +String(x.id).slice(1) || 0)) + 1);
    s = { id, k: 'board', fmt: f.id || 'free', n: fmtLabel(f), x: Math.round(right + gap), y: Math.round(base.y), w: f.w, h: f.h, f: [], bg: '' };
    list.push(s);
  }
  const nw = Math.max(vb.x + vb.width, s.x + s.w) - vb.x, nh = Math.max(vb.y + vb.height, s.y + s.h) - vb.y;
  root.setAttribute('viewBox', `${num(vb.x)} ${num(vb.y)} ${num(nw)} ${num(nh)}`);
  if (root.hasAttribute('width')) root.setAttribute('width', num(nw));
  if (root.hasAttribute('height')) root.setAttribute('height', num(nh));
  setSlices(root, list);
  // Inhalt: Kopie der Ebenen des Hauptformats, nach Regeln gelegt
  root.querySelector(`g[data-ge-board="${CSS.escape(s.id)}"]`)?.remove();
  const g = document.createElementNS(NS, 'g');
  g.setAttribute('data-ge-board', s.id);
  g.setAttribute('data-name', s.n);
  const src = baseLayers(root, base);
  const rules = new Map();
  const copies = src.map(el => { const c = el.cloneNode(true); rules.set(c, anchorFor(el, root, base)); return c; });
  g.append(...copies);
  root.append(g);
  remapIds(g, root);
  layout(root, copies, base, s, rules);
  return s.id;
}

/** Zeichenfläche entfernen (Bereich und Gruppe), die Fläche schrumpft wieder */
export function removeFormat(root, sid) {
  const list = getSlices(root).filter(x => x.id !== sid);
  setSlices(root, list);
  root.querySelector(`g[data-ge-board="${CSS.escape(sid)}"]`)?.remove();
  const base = baseBox(root);
  const right = Math.max(base.x + base.w, ...list.map(x => x.x + x.w)), bottom = Math.max(base.y + base.h, ...list.map(x => x.y + x.h));
  root.setAttribute('viewBox', `${num(base.x)} ${num(base.y)} ${num(right - base.x)} ${num(bottom - base.y)}`);
  if (root.hasAttribute('width')) root.setAttribute('width', num(right - base.x));
  if (root.hasAttribute('height')) root.setAttribute('height', num(bottom - base.y));
  if (!list.some(x => x.k === 'board')) root.removeAttribute('data-ge-base');
}
