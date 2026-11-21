/*
 * Grafik-Editor – Foto-Ebenen und Pixel-Ausgabe (Persona „Bild“, Ausbaustufe 6).
 *
 * Arbeitsfassung (gespeichert, Protokoll): ein Foto ist ein Platzhalter-Rechteck <rect data-ge-photo="Base64(JSON)" x y width height>
 * – nie ein <image>, nie eingebettete Pixel. Daten: m = Mediathek-ID, z = Zoom (1 = Rahmen gefüllt), fx/fy = Ausschnitt wie
 * object-position in % (null = Fokuspunkt der Mediathek), b/c/s = Helligkeit/Kontrast/Sättigung in % (100 = unverändert),
 * bl = Weichzeichnen (Einheiten der Grafik). Begriffe wie Core\ImageFx (Helligkeit/Kontrast 50–150, Sättigung 0–200).
 * Im Editor wird daraus ein <image> mit einem im Browser zugeschnittenen und korrigierten Bild (Object-URL, Größe des Rahmens);
 * getSvg() macht wieder den Platzhalter daraus (toWork). Rahmen ändern = Ausschnitt folgt dem Fokuspunkt (füllen).
 *
 * renderImage(): Bereich einer Arbeitsfassung als Pixelbild (Canvas, Faktor 2): Fotos in voller Auflösung zugeschnitten, Texte mit
 * Webschriften in Kurven (sonst fehlte die Schrift im SVG-Bild), Hintergrund weiß (JPEG) oder transparent (PNG). MIT
 */
import { NS, docMatrix } from './geom.js';
import { outlineAll } from './outline.js';
import { getProp } from './doc.js';

export const ATTR = 'data-ge-photo';
const PLACEHOLDER = '#B8C0CC';

const b64 = s => btoa(String.fromCharCode(...new TextEncoder().encode(s)));
const unb64 = s => new TextDecoder().decode(Uint8Array.from(atob(s), c => c.charCodeAt(0)));

/** Daten einer Foto-Ebene (null = keine) */
export function getPhoto(el) {
  const v = el?.getAttribute?.(ATTR);
  if (!v) return null;
  try { const d = JSON.parse(unb64(v)); return d && +d.m > 0 ? { z: 1, fx: null, fy: null, b: 100, c: 100, s: 100, bl: 0, ...d, m: +d.m } : null; } catch { return null; }
}
export function setPhoto(el, d) {
  const out = { m: +d.m };
  if (+d.z && Math.abs(d.z - 1) > 1e-3) out.z = Math.round(d.z * 100) / 100;
  if (d.fx != null) out.fx = Math.round(d.fx * 10) / 10;
  if (d.fy != null) out.fy = Math.round(d.fy * 10) / 10;
  for (const k of ['b', 'c', 's']) if (d[k] != null && +d[k] !== 100) out[k] = Math.round(+d[k]);
  if (+d.bl > 0) out.bl = Math.round(d.bl * 10) / 10;
  el.setAttribute(ATTR, b64(JSON.stringify(out)));
}
export const isPhoto = el => !!el?.hasAttribute?.(ATTR) && (el.localName === 'image' || el.localName === 'rect');

/** Pixel-Ausgabe? Fotos enthalten oder ausdrücklich data-ge-out (jpeg | png) an der Wurzel */
export const isRasterDoc = root => !!root && (root.hasAttribute('data-ge-out') || !!root.querySelector(`[${ATTR}]`));
export const outType = root => (root?.getAttribute('data-ge-out') === 'png' ? 'png' : 'jpeg');

/** Live-Dokument → Arbeitsfassung: <image data-ge-photo> wird wieder Platzhalter-Rechteck (alle Attribute außer href) */
export function toWork(root) {
  for (const im of [...root.querySelectorAll(`image[${ATTR}]`)]) {
    const r = root.ownerDocument.createElementNS(NS, 'rect');
    for (const a of im.attributes) if (!['href', 'xlink:href', 'preserveAspectRatio'].includes(a.name)) r.setAttribute(a.name, a.value);
    r.setAttribute('fill', PLACEHOLDER);
    im.replaceWith(r);
  }
  return root;
}

/** Arbeitsfassung → Live-Dokument: Platzhalter werden <image> (Bild folgt asynchron) */
export function fromWork(root) {
  const out = [];
  for (const r of [...root.querySelectorAll(`rect[${ATTR}]`)]) {
    const im = document.createElementNS(NS, 'image');
    for (const a of r.attributes) if (a.name !== 'fill' && a.name !== 'stroke' && a.name !== 'rx' && a.name !== 'ry') im.setAttribute(a.name, a.value);
    im.setAttribute('preserveAspectRatio', 'xMidYMid slice');
    r.replaceWith(im);
    out.push(im);
  }
  return out;
}

/** Rahmen (lokale Einheiten) */
const frameOf = el => ({ x: +el.getAttribute('x') || 0, y: +el.getAttribute('y') || 0, w: Math.max(1e-6, +el.getAttribute('width') || 0), h: Math.max(1e-6, +el.getAttribute('height') || 0) });

/**
 * Ausschnitt im Foto (Anteile 0..1): füllen wie object-fit: cover, Zoom z, Lage wie object-position fx/fy (Fokuspunkt).
 * info: {w, h, focus}. Rückgabe {x, y, w, h} als Anteile des Fotos.
 */
export function cropOf(d, frame, info) {
  const iw = info?.w || 1, ih = info?.h || 1;
  const S = Math.max(frame.w / iw, frame.h / ih) * Math.max(1, d.z || 1);
  const sw = Math.min(iw, frame.w / S), sh = Math.min(ih, frame.h / S);
  const fx = (d.fx ?? info?.focus?.x ?? 50) / 100, fy = (d.fy ?? info?.focus?.y ?? 50) / 100;
  const sx = (iw - sw) * fx, sy = (ih - sh) * fy;
  return { x: sx / iw, y: sy / ih, w: sw / iw, h: sh / ih };
}

// ---------------------------------------------------------------- Bilder laden, zuschneiden, korrigieren

const loaded = new Map();
export function loadImage(url) {
  if (!loaded.has(url)) {
    loaded.set(url, new Promise((res, rej) => {
      const im = new Image();
      im.decoding = 'async';
      im.onload = () => res(im);
      im.onerror = () => { loaded.delete(url); rej(new Error('Bild nicht geladen: ' + url)); };
      im.src = url;
    }));
  }
  return loaded.get(url);
}

/** Helligkeit, Kontrast, Sättigung wie die CSS-Filter (brightness → contrast → saturate), in Prozent */
function adjust(px, b, c, s) {
  if (b === 100 && c === 100 && s === 100) return;
  const B = b / 100, C = c / 100, S = s / 100;
  // Sättigung: Matrix wie feColorMatrix type=saturate
  const m = [0.213 + 0.787 * S, 0.715 - 0.715 * S, 0.072 - 0.072 * S, 0.213 - 0.213 * S, 0.715 + 0.285 * S, 0.072 - 0.072 * S, 0.213 - 0.213 * S, 0.715 - 0.715 * S, 0.072 + 0.928 * S];
  const lut = new Uint8ClampedArray(256);
  for (let i = 0; i < 256; i++) lut[i] = Math.round(((i / 255) * B - 0.5) * C * 255 + 127.5);
  for (let i = 0; i < px.length; i += 4) {
    const r = lut[px[i]], g = lut[px[i + 1]], bb = lut[px[i + 2]];
    if (S === 1) { px[i] = r; px[i + 1] = g; px[i + 2] = bb; continue; }
    px[i] = m[0] * r + m[1] * g + m[2] * bb; px[i + 1] = m[3] * r + m[4] * g + m[5] * bb; px[i + 2] = m[6] * r + m[7] * g + m[8] * bb;
  }
}

/** Weichzeichnen: dreimal Kastenfilter (nähert Gauß), Ränder werden fortgesetzt (kein durchscheinender Rand) */
function blur(img, radius) {
  const r = Math.round(radius);
  if (r < 1) return;
  const { width: w, height: h, data } = img;
  const tmp = new Float32Array(data.length);
  const pass = (src, dst, len, n, step, lineStep) => {
    for (let l = 0; l < n; l++) {
      const o = l * lineStep;
      for (let ch = 0; ch < 4; ch++) {
        let acc = 0;
        const at = i => src[o + Math.min(len - 1, Math.max(0, i)) * step + ch];
        for (let i = -r; i <= r; i++) acc += at(i);
        for (let i = 0; i < len; i++) {
          dst[o + i * step + ch] = acc / (2 * r + 1);
          acc += at(i + r + 1) - at(i - r);
        }
      }
    }
  };
  for (let k = 0; k < 3; k++) { pass(data, tmp, w, h, 4, w * 4); pass(tmp, data, h, w, w * 4, 4); }
}

/**
 * Zugeschnittenes, korrigiertes Foto als Canvas (pw × ph Pixel). img = geladenes Bild (beliebige Fassung – Anteile zählen),
 * k = Pixel je Einheit (für den Weichzeichner).
 */
export function drawCrop(img, d, frame, info, pw, ph, k) {
  const c = document.createElement('canvas');
  c.width = Math.max(1, Math.round(pw)); c.height = Math.max(1, Math.round(ph));
  const g = c.getContext('2d', { willReadFrequently: true });
  const cr = cropOf(d, frame, info);
  const nw = img.naturalWidth, nh = img.naturalHeight;
  g.imageSmoothingQuality = 'high';
  g.drawImage(img, cr.x * nw, cr.y * nh, cr.w * nw, cr.h * nh, 0, 0, c.width, c.height);
  const b = d.b ?? 100, cc = d.c ?? 100, s = d.s ?? 100, bl = +d.bl || 0;
  if (b !== 100 || cc !== 100 || s !== 100 || bl > 0) {
    const id = g.getImageData(0, 0, c.width, c.height);
    adjust(id.data, b, cc, s);
    if (bl > 0) blur(id, bl * k);
    g.putImageData(id, 0, 0);
  }
  return c;
}

const toBlob = (c, type = 'image/png', q = 0.92) => new Promise((res, rej) => c.toBlob(b => (b ? res(b) : rej(new Error('toBlob'))), type, q));

/**
 * Foto-Ebenen eines Dokuments im Editor (oder im Formular „Aus Vorlage erstellen“): ctx = { root (Getter), info (Mediathek-Angaben je ID),
 * host (media.info) }. Bilder in Rahmengröße (höchstens 1600 px, Bildschirmdichte bis 2), zwischengespeichert je Zustand.
 */
export class Photos {
  constructor(ctx) {
    this.ctx = ctx;
    this.info = ctx.info || {};
    this.cache = new Map();   // Schlüssel → Object-URL
    this.order = [];
  }

  /** Angaben zu Fotos nachladen, die noch fehlen */
  async need(ids) {
    const miss = ids.filter(id => !this.info[id]);
    if (!miss.length) return;
    try { Object.assign(this.info, await this.ctx.host.media.info(miss)); } catch (e) { console.error(e); }
  }

  /** Platzhalter im Dokument zu Bildern machen und zeichnen */
  adopt(root = this.ctx.root) {
    fromWork(root);
    this.refresh(root);
  }

  key(el, d) {
    const f = frameOf(el);
    return [d.m, Math.round(f.w), Math.round(f.h), d.z, d.fx, d.fy, d.b, d.c, d.s, d.bl].join('|');
  }

  /** Alle Fotos (neu) zeichnen, deren Rahmen oder Daten sich geändert haben */
  refresh(root = this.ctx.root) {
    if (!root) return;
    const list = [...root.querySelectorAll(`image[${ATTR}]`)];
    const ids = [...new Set(list.map(el => getPhoto(el)?.m).filter(Boolean))];
    const go = () => { for (const el of list) this.paint(el); };
    if (ids.some(id => !this.info[id])) this.need(ids).then(go); else go();
  }

  paint(el) {
    const d = getPhoto(el);
    if (!d) return;
    const key = this.key(el, d);
    if (el.__geKey === key && el.getAttribute('href')) return;
    el.__geKey = key;
    const hit = this.cache.get(key);
    if (hit) { el.setAttribute('href', hit); return; }
    const info = this.info[d.m];
    if (!info) return;
    loadImage(info.url).then(async img => {
      if (el.__geKey !== key) return;
      const f = frameOf(el);
      const dpr = Math.min(2, window.devicePixelRatio || 1);
      const k = Math.min(dpr, 1600 / Math.max(f.w, f.h));
      const c = drawCrop(img, d, f, info, f.w * k, f.h * k, k);
      const url = URL.createObjectURL(await toBlob(c));
      this.remember(key, url);
      if (el.__geKey === key) el.setAttribute('href', url);
      this.ctx.onPaint?.(el);
    }).catch(e => console.error(e));
  }

  remember(key, url) {
    this.cache.set(key, url);
    this.order.push(key);
    // höchstens 120 Fassungen; ältere freigeben (das Protokoll zeichnet sie bei Bedarf neu)
    while (this.order.length > 120) { const old = this.order.shift(); const u = this.cache.get(old); this.cache.delete(old); if (u) setTimeout(() => URL.revokeObjectURL(u), 30000); }
  }

  /** Live-Bild sofort (ohne Protokoll) aktualisieren – z. B. beim Ziehen eines Reglers */
  live(el, patch) {
    const d = { ...getPhoto(el), ...patch };
    setPhoto(el, d);
    this.paint(el);
  }
}

// ---------------------------------------------------------------- Pixel-Ausgabe

/** Mutterschrift eines Textes (erste Familie) */
const familyOf = tx => (getProp(tx, 'font-family') || '').split(',')[0].trim().replace(/^["']|["']$/g, '');

/**
 * Bereich box {x, y, w, h} der Arbeitsfassung svg (Text) als Pixelbild: scale = Pixel je Einheit (Faktor 2), type 'jpeg' | 'png',
 * bg = Hintergrund für JPEG (Standard weiß). env = { info: Fotos (Mediathek-Angaben), boot: {fontFiles, modules} für Text in Kurven,
 * host: Element, in dem die Kopie zum Messen hängt }. Rückgabe Blob.
 */
export async function renderImage(svg, box, scale, env, opts = {}) {
  const type = opts.type === 'png' ? 'png' : 'jpeg';
  const W = Math.max(1, Math.round(box.w * scale)), H = Math.max(1, Math.round(box.h * scale));
  const root = document.importNode(new DOMParser().parseFromString(svg, 'image/svg+xml').documentElement, true);
  root.setAttribute('viewBox', `${box.x} ${box.y} ${box.w} ${box.h}`);
  root.setAttribute('width', String(W)); root.setAttribute('height', String(H));
  root.setAttribute('preserveAspectRatio', 'none');
  root.removeAttribute('style');
  const holder = document.createElement('div');
  holder.style.cssText = `position:fixed;left:-${W + 100}px;top:0;width:${W}px;height:${H}px;overflow:hidden;visibility:hidden;pointer-events:none`;
  holder.append(root);
  (env.host || document.body).append(holder);
  try {
    // Texte mit Webschrift (selbst gehostet) in Kurven: im SVG-Bild käme sonst eine Ersatzschrift
    const files = env.boot?.fontFiles || {};
    const texts = [...root.querySelectorAll('text')].filter(tx => tx.getAttribute('display') !== 'none' && Object.keys(files).some(k => k.toLowerCase() === familyOf(tx).toLowerCase()));
    if (texts.length) { try { await document.fonts.ready; } catch { /* egal */ } await outlineAll({ boot: env.boot }, texts); }
    // Fotos in Ausgabegröße zuschneiden und einsetzen
    for (const r of [...root.querySelectorAll(`rect[${ATTR}]`)]) {
      const d = getPhoto(r), info = d && env.info[d.m];
      if (!d || !info) { r.remove(); continue; }
      const f = frameOf(r);
      const M = docMatrix(r, root);
      const k = Math.hypot(M.a, M.b) * scale;
      const img = await loadImage(info.full || info.url);
      const pw = Math.min(6000, f.w * k), ph = Math.min(6000, f.h * k);
      const c = drawCrop(img, d, f, info, pw, ph, pw / f.w);
      const im = document.createElementNS(NS, 'image');
      for (const a of r.attributes) if (!['fill', 'stroke', 'rx', 'ry', ATTR].includes(a.name)) im.setAttribute(a.name, a.value);
      im.setAttribute('preserveAspectRatio', 'none');
      im.setAttribute('href', c.toDataURL('image/png'));
      r.replaceWith(im);
    }
    for (const el of root.querySelectorAll('[data-ge-photo],[data-ge-field]')) { el.removeAttribute('data-ge-photo'); el.removeAttribute('data-ge-field'); }
    if (!root.getAttribute('xmlns')) root.setAttribute('xmlns', NS);
    const img = new Image();
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(root));
    await img.decode();
    const c = document.createElement('canvas');
    c.width = W; c.height = H;
    const g = c.getContext('2d');
    if (type === 'jpeg') { g.fillStyle = opts.bg || '#ffffff'; g.fillRect(0, 0, W, H); }
    g.imageSmoothingQuality = 'high';
    g.drawImage(img, 0, 0, W, H);
    return await toBlob(c, type === 'png' ? 'image/png' : 'image/jpeg', 0.9);
  } finally { holder.remove(); }
}

/** Faktor der Ausgabe: 2 (scharf auf hochauflösenden Bildschirmen), begrenzt auf maxPx je Seite */
export const outScale = (box, maxPx = 4000) => Math.max(0.1, Math.min(2, maxPx / Math.max(box.w, box.h)));
