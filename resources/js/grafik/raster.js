/*
 * Grafik-Editor – Rastern für die einzigen erlaubten Pixel-Ausgaben (Persona Export): Favicon (ICO/PNG), App-Symbole, Social-Vorschau.
 * Der Editor bleibt Vektor; gerastert wird erst beim Herunterladen, im Browser (SVG → <img> → <canvas>).
 *   renderPng(svg, box, w, h, opts)   ein Bereich der Grafik als PNG/JPEG (Hintergrund, „contain“, Schutzzone)
 *   snapToPixels(root, box, size)      Kanten und Linienstärken am Pixelraster der Zielgröße ausrichten (kleine Symbole scharf)
 *   icoFile(pngs)                       ICO-Container mit PNG-Einträgen (wie Core\AppIcons)
 *   zipFile(files)                      ZIP ohne Kompression (PNG/PDF sind schon komprimiert) – kein Fremdcode nötig
 * MIT
 */
import { NS, parsePath, pathToString } from './geom.js';

// ---------------------------------------------------------------- Pixelraster

/**
 * Am Pixelraster ausrichten (wie „Pixelausrichtung erzwingen“ in Vektorprogrammen): Eckpunkte gerader Kanten auf ganze Pixel der
 * Zielgröße, Linienstärken auf ganze Pixel (mind. 1), ungerade Linienstärken auf halbe Pixel – damit 1-px-Linien nicht verschwimmen.
 * root muss im Dokument hängen (Matrizen). Nur Elemente ohne Drehung/Neigung; Kurven behalten ihre Form (Griffe ziehen mit).
 */
export function snapToPixels(root, box, size) {
  const k = size / box.w;
  const inv = root.getScreenCTM()?.inverse();
  if (!inv) return 0;
  let n = 0;
  for (const el of root.querySelectorAll('path,rect,line,polyline,polygon')) {
    if (el.closest('defs,clipPath,mask,marker,pattern,symbol')) continue;
    const sc = el.getScreenCTM();
    if (!sc) continue;
    const M = inv.multiply(sc);                       // Element → Dokument
    if (Math.abs(M.b) > 1e-6 || Math.abs(M.c) > 1e-6) continue;
    const Mi = M.inverse();
    const cs = getComputedStyle(el);
    const stroked = cs.stroke && cs.stroke !== 'none' && parseFloat(cs.strokeWidth) > 0;
    let off = 0;
    if (stroked) {
      const swPx = parseFloat(cs.strokeWidth) * Math.abs(M.a) * k;
      const r = Math.max(1, Math.round(swPx));
      el.setAttribute('stroke-width', String(r / (Math.abs(M.a) * k)));
      el.style?.removeProperty('stroke-width');
      if (r % 2 === 1) off = 0.5;
    }
    // Dokument → Pixel der Zielgröße und zurück
    const snap = (x, y) => {
      const p = new DOMPoint(x, y).matrixTransform(M);
      const px = Math.round((p.x - box.x) * k - off) + off, py = Math.round((p.y - box.y) * k - off) + off;
      const q = new DOMPoint(px / k + box.x, py / k + box.y).matrixTransform(Mi);
      return [q.x, q.y];
    };
    const tag = el.localName;
    if (tag === 'rect') {
      const x = +el.getAttribute('x') || 0, y = +el.getAttribute('y') || 0, w = +el.getAttribute('width') || 0, h = +el.getAttribute('height') || 0;
      const [x0, y0] = snap(x, y), [x1, y1] = snap(x + w, y + h);
      el.setAttribute('x', x0); el.setAttribute('y', y0); el.setAttribute('width', Math.max(0, x1 - x0)); el.setAttribute('height', Math.max(0, y1 - y0));
    } else if (tag === 'line') {
      const [a, b] = snap(+el.getAttribute('x1') || 0, +el.getAttribute('y1') || 0), [c, d] = snap(+el.getAttribute('x2') || 0, +el.getAttribute('y2') || 0);
      el.setAttribute('x1', a); el.setAttribute('y1', b); el.setAttribute('x2', c); el.setAttribute('y2', d);
    } else if (tag === 'polyline' || tag === 'polygon') {
      const v = (el.getAttribute('points') || '').trim().split(/[\s,]+/).map(Number);
      const o = [];
      for (let i = 0; i + 1 < v.length; i += 2) o.push(snap(v[i], v[i + 1]).join(','));
      el.setAttribute('points', o.join(' '));
    } else {
      const segs = parsePath(el.getAttribute('d'));
      let last = [0, 0], lastS = [0, 0];
      for (const s of segs) {
        const p = s.p;
        if (s.c === 'M' || s.c === 'L') { const q = snap(p[0], p[1]); last = [p[0], p[1]]; lastS = q; p[0] = q[0]; p[1] = q[1]; }
        else if (s.c === 'C') {
          const q = snap(p[4], p[5]);
          const d0 = [lastS[0] - last[0], lastS[1] - last[1]], d1 = [q[0] - p[4], q[1] - p[5]];
          p[0] += d0[0]; p[1] += d0[1]; p[2] += d1[0]; p[3] += d1[1];
          last = [p[4], p[5]]; lastS = q; p[4] = q[0]; p[5] = q[1];
        } else if (s.c === 'Q') {
          const q = snap(p[2], p[3]);
          last = [p[2], p[3]]; lastS = q; p[2] = q[0]; p[3] = q[1];
        } else if (s.c === 'A') {
          const q = snap(p[5], p[6]);
          last = [p[5], p[6]]; lastS = q; p[5] = q[0]; p[6] = q[1];
        }
      }
      el.setAttribute('d', pathToString(segs, 4));
    }
    n++;
  }
  return n;
}

// ---------------------------------------------------------------- Rastern

/**
 * Bereich box {x, y, w, h} einer SVG (Text) als Bild w × h: opts = { bg: '#fff' | null, type: 'image/png' | 'image/jpeg', quality,
 * inset: 0..0.5 (Rand je Seite, z. B. Schutzzone), snap: true (Pixelraster), host: Element zum Einhängen (Matrizen) }.
 * Seitenverhältnis bleibt („contain“), der Rest ist Hintergrund.
 */
export async function renderPng(svg, box, w, h, opts = {}) {
  const doc = new DOMParser().parseFromString(svg, 'image/svg+xml').documentElement;
  const root = document.importNode(doc, true);
  root.setAttribute('viewBox', `${box.x} ${box.y} ${box.w} ${box.h}`);
  root.setAttribute('width', String(box.w)); root.setAttribute('height', String(box.h));
  root.setAttribute('preserveAspectRatio', 'xMidYMid meet');
  root.removeAttribute('style');
  if (opts.snap) {
    const host = document.createElement('div');
    host.style.cssText = 'position:fixed;left:-20000px;top:0;visibility:hidden';
    host.append(root);
    (opts.host || document.body).append(host);
    try { snapToPixels(root, box, Math.min(w, h)); } finally { host.remove(); }
  }
  const inset = Math.max(0, Math.min(0.45, opts.inset || 0));
  const iw = Math.round(w * (1 - 2 * inset)), ih = Math.round(h * (1 - 2 * inset));
  const s = Math.min(iw / box.w, ih / box.h);
  const dw = box.w * s, dh = box.h * s;
  root.setAttribute('width', String(dw)); root.setAttribute('height', String(dh));
  if (!root.getAttribute('xmlns')) root.setAttribute('xmlns', NS);
  const img = new Image();
  img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(root));
  await img.decode();
  const c = document.createElement('canvas');
  c.width = w; c.height = h;
  const g = c.getContext('2d');
  if (opts.bg) { g.fillStyle = opts.bg; g.fillRect(0, 0, w, h); }
  g.imageSmoothingQuality = 'high';
  g.drawImage(img, (w - dw) / 2, (h - dh) / 2, dw, dh);
  const type = opts.type || 'image/png';
  return new Promise((res, rej) => c.toBlob(b => (b ? res(b) : rej(new Error('toBlob'))), type, opts.quality ?? 0.9));
}

// ---------------------------------------------------------------- ICO, PNG-Maße

/** ICO-Container: [[Größe, PNG-Bytes (Uint8Array)], …] */
export function icoFile(pngs) {
  const head = 6 + 16 * pngs.length;
  const total = head + pngs.reduce((s, [, b]) => s + b.length, 0);
  const out = new Uint8Array(total), v = new DataView(out.buffer);
  v.setUint16(0, 0, true); v.setUint16(2, 1, true); v.setUint16(4, pngs.length, true);
  let off = head;
  pngs.forEach(([size, b], i) => {
    const e = 6 + i * 16;
    out[e] = size >= 256 ? 0 : size; out[e + 1] = size >= 256 ? 0 : size; out[e + 2] = 0; out[e + 3] = 0;
    v.setUint16(e + 4, 1, true); v.setUint16(e + 6, 32, true); v.setUint32(e + 8, b.length, true); v.setUint32(e + 12, off, true);
    out.set(b, off); off += b.length;
  });
  return out;
}

/** Maße einer PNG-Datei (IHDR) */
export function pngSize(b) {
  const v = new DataView(b.buffer, b.byteOffset, b.byteLength);
  return b.length > 24 && v.getUint32(0) === 0x89504e47 ? { w: v.getUint32(16), h: v.getUint32(20) } : null;
}

// ---------------------------------------------------------------- ZIP (ohne Kompression)

let CRC = null;
function crc32(b) {
  if (!CRC) { CRC = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; CRC[n] = c >>> 0; } }
  let c = 0xffffffff;
  for (let i = 0; i < b.length; i++) c = CRC[(c ^ b[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}

/** ZIP aus [{name, data: Uint8Array}] – Namen UTF-8, Zeitstempel jetzt */
export function zipFile(files) {
  const enc = new TextEncoder();
  const d = new Date();
  const time = (d.getHours() << 11) | (d.getMinutes() << 5) | (d.getSeconds() >> 1), date = ((d.getFullYear() - 1980) << 9) | ((d.getMonth() + 1) << 5) | d.getDate();
  const parts = [], central = [];
  let off = 0;
  for (const f of files) {
    const name = enc.encode(f.name), data = f.data, crc = crc32(data);
    const h = new Uint8Array(30 + name.length), v = new DataView(h.buffer);
    v.setUint32(0, 0x04034b50, true); v.setUint16(4, 20, true); v.setUint16(6, 0x0800, true); v.setUint16(8, 0, true);
    v.setUint16(10, time, true); v.setUint16(12, date, true); v.setUint32(14, crc, true); v.setUint32(18, data.length, true); v.setUint32(22, data.length, true);
    v.setUint16(26, name.length, true); v.setUint16(28, 0, true); h.set(name, 30);
    const c = new Uint8Array(46 + name.length), w = new DataView(c.buffer);
    w.setUint32(0, 0x02014b50, true); w.setUint16(4, 20, true); w.setUint16(6, 20, true); w.setUint16(8, 0x0800, true); w.setUint16(10, 0, true);
    w.setUint16(12, time, true); w.setUint16(14, date, true); w.setUint32(16, crc, true); w.setUint32(20, data.length, true); w.setUint32(24, data.length, true);
    w.setUint16(28, name.length, true); w.setUint32(42, off, true); c.set(name, 46);
    parts.push(h, data); central.push(c);
    off += h.length + data.length;
  }
  const cs = central.reduce((s, c) => s + c.length, 0);
  const end = new Uint8Array(22), e = new DataView(end.buffer);
  e.setUint32(0, 0x06054b50, true); e.setUint16(8, files.length, true); e.setUint16(10, files.length, true); e.setUint32(12, cs, true); e.setUint32(16, off, true);
  return new Blob([...parts, ...central, end], { type: 'application/zip' });
}

export const bytes = async blob => new Uint8Array(await blob.arrayBuffer());
