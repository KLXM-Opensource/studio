import { t } from './_i18n.js';
import { layerBox } from './_shadow.js';
import { ask } from './_bar.js';
/*
 * Bild bearbeiten (Core\ImageEdit) – zerstörungsfrei: Zuschneiden · Drehen · Spiegeln · Ausrichten · Entzerren.
 *  - Das Original bleibt unverändert; gespeichert werden nur Parameter (media.edit_json). Der Server erzeugt daraus die
 *    bearbeitete Fassung und neue Größen (neue Adressen, Seiten-Cache wird geleert).
 *  - Reihenfolge wie in PHP: Entzerren → Spiegeln → 90°-Drehung → freie Drehung (größtes einbeschriebenes Rechteck oder
 *    Füllfarbe) → Zuschneiden. Die Vorschau rechnet dasselbe auf einer verkleinerten Kopie im <canvas>.
 *  - Bedienung: Maus, Stift, Touch (Anfasser ≥ 32 px) und Tastatur (Pfeiltasten verschieben Ecken und Ausschnitt,
 *    drehen im Werkzeug Drehen/Ausrichten um 0,1°, mit ⇧ um 1°). Keine Inline-Skripte; Positionen über element.style (CSSOM).
 *  - Pool-Dateien: Bearbeitung wirkt auf allen Websites, die das Bild nutzen (Hinweis im Dialog).
 */
const d = document;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
export const EDIT_TOOLS = ['crop', 'rotate', 'flip', 'straighten', 'perspective'];
export const EDIT_RATIOS = ['free', '1:1', '4:3', '3:2', '16:9', '16:10', '4:5', '9:16'];
const MAX_ANGLE = 45;
const PREVIEW_MAX = 1600;   // längste Kante der Vorschau-Kopie
const PERSP_MAX = 1100;     // längste Kante der entzerrten Vorschau
const IDENT = [[0, 0], [1, 0], [1, 1], [0, 1]];
const round1 = v => Math.round(v * 10) / 10;
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

// ============================================================ Rechnung (gleich wie Core\ImageEdit)
/** Größtes achsparalleles Rechteck in einem um deg gedrehten Rechteck w×h */
export function inscribed(w, h, deg) {
  if (w <= 0 || h <= 0) return [0, 0];
  const a = Math.abs(deg) * Math.PI / 180, sin = Math.abs(Math.sin(a)), cos = Math.abs(Math.cos(a));
  if (sin < 1e-12) return [w, h];
  const long = Math.max(w, h), short = Math.min(w, h);
  if (short <= 2 * sin * cos * long || Math.abs(sin - cos) < 1e-10) {
    const x = 0.5 * short;
    return w >= h ? [x / sin, x / cos] : [x / cos, x / sin];
  }
  const cos2 = cos * cos - sin * sin;
  return [(w * cos - h * sin) / cos2, (h * cos - w * sin) / cos2];
}
export function rotatedBox(w, h, deg) {
  const a = deg * Math.PI / 180, s = Math.abs(Math.sin(a)), c = Math.abs(Math.cos(a));
  return [w * c + h * s, w * s + h * c];
}
/** Ausgabegröße beim Entzerren (Ecken in Pixeln: TL, TR, BR, BL) */
export function perspSize(q, fit = 'edges', ratio = 0) {
  const dist = (a, b) => Math.hypot(b[0] - a[0], b[1] - a[1]);
  const w = Math.max(dist(q[0], q[1]), dist(q[3], q[2]));
  let h = Math.max(dist(q[0], q[3]), dist(q[1], q[2]));
  if (fit === 'image' && ratio > 0) h = w / ratio;
  return [Math.max(1, Math.round(w)), Math.max(1, Math.round(h))];
}
/** Projektive Abbildung from → to (je 4 Punkte), 9 Werte oder null */
export function homography(from, to) {
  const A = [];
  for (let i = 0; i < 4; i++) {
    const [x, y] = from[i], [u, v] = to[i];
    A.push([x, y, 1, 0, 0, 0, -u * x, -u * y, u], [0, 0, 0, x, y, 1, -v * x, -v * y, v]);
  }
  for (let c = 0; c < 8; c++) {
    let p = c;
    for (let r = c + 1; r < 8; r++) if (Math.abs(A[r][c]) > Math.abs(A[p][c])) p = r;
    if (Math.abs(A[p][c]) < 1e-12) return null;
    [A[c], A[p]] = [A[p], A[c]];
    for (let r = 0; r < 8; r++) {
      if (r === c) continue;
      const f = A[r][c] / A[c][c];
      if (f) for (let k = c; k < 9; k++) A[r][k] -= f * A[c][k];
    }
  }
  return [...A.map((row, i) => row[8] / row[i]), 1];
}
function convex(p) {
  let sign = 0;
  for (let i = 0; i < 4; i++) {
    const [ax, ay] = p[i], [bx, by] = p[(i + 1) % 4], [cx, cy] = p[(i + 2) % 4];
    const z = (bx - ax) * (cy - by) - (by - ay) * (cx - bx);
    if (Math.abs(z) < 1e-9) return false;
    const s = z > 0 ? 1 : -1;
    if (sign && s !== sign) return false;
    sign = s;
  }
  return sign > 0;
}
const ratioOf = r => { const [a, b] = String(r).split(':').map(Number); return a && b ? a / b : 0; };
/** Zuschnitt in Pixeln (Seitenverhältnis exakt) */
function cropRect(w, h, c) {
  let pw = Math.max(1, Math.round(c.w * w)), ph = Math.max(1, Math.round(c.h * h));
  const ar = ratioOf(c.ratio);
  if (ar) {
    ph = Math.max(1, Math.round(pw / ar));
    if (ph > h) { ph = h; pw = Math.max(1, Math.round(ph * ar)); }
    if (pw > w) { pw = w; ph = Math.max(1, Math.round(pw / ar)); }
  }
  return { w: Math.min(pw, w), h: Math.min(ph, h) };
}
/** Maße des Ergebnisses für ein Original w×h */
export function outputSize(w, h, e) {
  e = e || {};
  if (e.quad) [w, h] = perspSize(e.quad.map(([x, y]) => [x * w, y * h]), e.quad_fit, w / h);
  if (e.rot === 90 || e.rot === 270) [w, h] = [h, w];
  if (e.angle) {
    const [fw, fh] = e.fill ? rotatedBox(w, h, e.angle) : inscribed(w, h, e.angle);
    [w, h] = e.fill ? [Math.round(fw), Math.round(fh)] : [Math.max(1, Math.floor(fw) - 2), Math.max(1, Math.floor(fh) - 2)];
  }
  if (e.crop) ({ w, h } = cropRect(w, h, e.crop));
  return [w, h];
}

// ============================================================ Symbole (eigene Linien-Symbole, currentColor)
const SVG = {
  crop: '<path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M2 6h14a2 2 0 0 1 2 2v14"/>',
  rotate: '<path d="M20 12a8 8 0 1 1-2.6-5.9L20 8.5"/><path d="M20 3.5v5h-5"/>',
  rotl: '<path d="M4 12a8 8 0 1 0 2.6-5.9L4 8.5"/><path d="M4 3.5v5h5"/>',
  flip: '<path d="M12 2v20"/><path d="M9 6 3 18h6z"/><path d="M15 6l6 12h-6z"/>',
  flipv: '<path d="M2 12h20"/><path d="M6 9 18 3v6z"/><path d="M6 15l12 6v-6z"/>',
  straighten: '<path d="M3 18 21 6"/><path d="M3 12h18" stroke-dasharray="2 3"/><circle cx="12" cy="12" r="1.5"/>',
  perspective: '<path d="M7 4h10l4 16H3z"/><path d="M9 9h6v6H9z" stroke-dasharray="2 2"/>',
  horizon: '<path d="M3 16 21 8"/><circle cx="3" cy="16" r="2"/><circle cx="21" cy="8" r="2"/>',
};
const icon = k => `<svg class="ie-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${SVG[k]}</svg>`;
const toolLabels = () => ({ crop: t('Zuschneiden'), rotate: t('Drehen'), flip: t('Spiegeln'), straighten: t('Ausrichten'), perspective: t('Entzerren') });
/** Werkzeugleiste im Detail-Dialog der Mediathek (Buttons mit data-ie-tool) */
export function editToolbar(m, ro = false) {
  if (ro) return '';
  const L = toolLabels(), why = m.editable;
  return `<div class="ie-bar" role="toolbar" aria-label="${esc(t('Bild bearbeiten'))}">
      ${EDIT_TOOLS.map(k => `<button type="button" class="ie-bar__btn" data-ie-tool="${k}"${why ? ' disabled' : ''}>${icon(k)}<span>${esc(L[k])}</span></button>`).join('')}
    </div>${why ? `<p class="f-help ie-bar__why">${esc(why)}</p>` : m.edit ? `<p class="f-help ie-bar__state"><span class="ifx-badge">${esc(t('Bearbeitet'))}</span> ${esc(editLabel(m.edit))}</p>` : ''}`;
}
/** Kurzbeschreibung, z. B. „Gedreht 7,5° · Zugeschnitten 16:9“ */
export function editLabel(e) {
  if (!e) return '';
  const n = v => String(v).replace('.', ',');
  return [e.quad && t('Entzerrt'), (e.flip_h || e.flip_v) && t('Gespiegelt'), e.rot && t('Gedreht {deg}°', { deg: e.rot }),
    e.angle && t('Ausgerichtet {deg}°', { deg: n(e.angle) }), e.crop && t('Zugeschnitten {ratio}', { ratio: e.crop.ratio === 'free' ? t('frei') : e.crop.ratio })]
    .filter(Boolean).join(' · ');
}

// ============================================================ Zustand
const fromEdit = e => ({
  quad: e?.quad ? e.quad.map(p => [...p]) : null, quad_fit: e?.quad_fit || 'edges',
  flip_h: !!e?.flip_h, flip_v: !!e?.flip_v, rot: e?.rot || 0, angle: e?.angle || 0,
  fillOn: !!e?.fill, fill: e?.fill || '#ffffff',
  crop: e?.crop ? { x: e.crop.x, y: e.crop.y, w: e.crop.w, h: e.crop.h } : null, ratio: e?.crop?.ratio || 'free',
});
const isIdent = q => !q || q.every((p, i) => Math.abs(p[0] - IDENT[i][0]) < 1e-4 && Math.abs(p[1] - IDENT[i][1]) < 1e-4);
function toEdit(st) {
  const e = {};
  if (!isIdent(st.quad)) { e.quad = st.quad.map(([x, y]) => [+x.toFixed(5), +y.toFixed(5)]); if (st.quad_fit === 'image') e.quad_fit = 'image'; }
  if (st.flip_h) e.flip_h = true;
  if (st.flip_v) e.flip_v = true;
  if (st.rot) e.rot = st.rot;
  if (round1(st.angle)) e.angle = round1(st.angle);
  if (e.angle && st.fillOn) e.fill = st.fill;
  const c = st.crop;
  if (c && !(c.x < 5e-4 && c.y < 5e-4 && c.w > 0.9995 && c.h > 0.9995)) e.crop = { x: +c.x.toFixed(5), y: +c.y.toFixed(5), w: +c.w.toFixed(5), h: +c.h.toFixed(5), ratio: st.ratio };
  return Object.keys(e).length ? e : null;
}

// ============================================================ Dialog
/**
 * Bildeditor öffnen. m = Medium (JSON aus /admin/api/media/{id}), opts = { tool, save(edit) → Promise<{item}>, engine }
 * Ergebnis (Promise): gespeichertes Medium (item) oder undefined (abgebrochen).
 */
export function editImage(m, opts = {}) {
  return new Promise(resolve => {
    const box = layerBox();
    box.querySelector('#ie-dlg')?.remove();   // je Aufruf ein neuer Dialog (Ereignisse hängen am Dialog)
    box.insertAdjacentHTML('beforeend', '<dialog id="ie-dlg" class="ie" aria-labelledby="ie-title"></dialog>');
    const dlg = box.querySelector('#ie-dlg');
    const L = toolLabels();
    const O = m.original_size || { w: m.width, h: m.height };
    let st = fromEdit(m.edit);
    const start = JSON.stringify(toEdit(st));
    let tool = EDIT_TOOLS.includes(opts.tool) ? opts.tool : 'crop';
    let before = false, horizon = null, drawing = false, gridUntil = 0, result, busy = false;
    const hadCrops = Object.keys(m.crops || {}).length;
    dlg.innerHTML = `
      <div class="ie-head">
        <h2 id="ie-title">${esc(t('Bild bearbeiten'))} <span>${esc(m.display || '')}</span></h2>
        <div class="ie-tabs" role="tablist" aria-label="${esc(t('Werkzeuge'))}">
          ${EDIT_TOOLS.map(k => `<button type="button" role="tab" id="ie-tab-${k}" aria-controls="ie-panel" data-tool="${k}">${icon(k)}<span>${esc(L[k])}</span></button>`).join('')}
        </div>
      </div>
      ${m.pool ? `<p class="ie-note ie-note--pool" role="note">${esc(t('Wirkt auf alle Websites, die dieses Bild nutzen'))}</p>` : ''}
      <div class="ie-main">
        <div class="ie-stage" data-stage tabindex="-1">
          <div class="ie-wrap" data-wrap>
            <canvas class="ie-view" data-view role="img" aria-label="${esc(t('Vorschau des bearbeiteten Bildes'))}"></canvas>
            <svg class="ie-ov" data-ov aria-hidden="true" focusable="false"></svg>
            <div class="ie-handles" data-handles></div>
          </div>
          <p class="ie-busy" data-busy role="status" hidden>${esc(t('Bild wird geladen …'))}</p>
        </div>
        <div class="ie-panel" id="ie-panel" role="tabpanel" tabindex="-1"></div>
      </div>
      <p class="ie-err f-error" role="alert" hidden></p>
      <div class="ie-foot">
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-before aria-pressed="false">${esc(t('Vorher/Nachher'))}</button>
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-resetall>${esc(t('Alles zurücksetzen'))}</button>
        <span class="ie-info" aria-live="polite"></span>
        <span class="ie-spacer"></span>
        <button type="button" class="adm-btn adm-btn--ghost" data-cancel>${esc(t('Abbrechen'))}</button>
        <button type="button" class="adm-btn adm-btn--primary" data-save>${esc(t('Speichern'))}</button>
      </div>`;
    const $ = s => dlg.querySelector(s), $$ = s => [...dlg.querySelectorAll(s)];
    const stage = $('[data-stage]'), wrap = $('[data-wrap]'), view = $('[data-view]'), ov = $('[data-ov]'), hs = $('[data-handles]'), panel = $('#ie-panel');
    const info = $('.ie-info'), err = $('.ie-err'), busyEl = $('[data-busy]');
    const ctx = view.getContext('2d');

    // ---------------------------------------------------------- Vorschau-Kopie des Originals
    const base = d.createElement('canvas');
    let baseData = null, pCache = { key: '', canvas: null }, loaded = false;
    const img = new Image();
    img.decoding = 'async';
    busyEl.hidden = false;
    img.onload = () => {
      const k = Math.min(1, PREVIEW_MAX / Math.max(img.naturalWidth, img.naturalHeight));
      base.width = Math.max(1, Math.round(img.naturalWidth * k));
      base.height = Math.max(1, Math.round(img.naturalHeight * k));
      base.getContext('2d').drawImage(img, 0, 0, base.width, base.height);
      loaded = true; baseData = null; pCache = { key: '', canvas: null };
      busyEl.hidden = true;
      render();
    };
    img.onerror = () => { busyEl.textContent = t('Bild konnte nicht geladen werden.'); };
    img.src = m.original || m.url;

    /** Entzerrte Vorschau (zwischengespeichert je Ecken und Größenart) */
    function persp() {
      if (isIdent(st.quad)) return base;
      const key = JSON.stringify([st.quad, st.quad_fit]);
      if (pCache.key === key) return pCache.canvas;
      const sw = base.width, sh = base.height;
      if (!baseData) baseData = base.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, sw, sh).data;
      const q = st.quad.map(([x, y]) => [x * sw, y * sh]);
      let [W, H] = perspSize(q, st.quad_fit, sw / sh);
      const k = Math.min(1, PERSP_MAX / Math.max(W, H));
      W = Math.max(1, Math.round(W * k)); H = Math.max(1, Math.round(H * k));
      const hm = homography([[0, 0], [W, 0], [W, H], [0, H]], q);
      const c = d.createElement('canvas');
      c.width = W; c.height = H;
      if (!hm) return base;
      const out = new ImageData(W, H), od = out.data, sd = baseData, mx = sw - 1, my = sh - 1;
      for (let y = 0, o = 0; y < H; y++) {
        const yy = y + 0.5;
        let nu = hm[0] * 0.5 + hm[1] * yy + hm[2], nv = hm[3] * 0.5 + hm[4] * yy + hm[5], dd = hm[6] * 0.5 + hm[7] * yy + hm[8];
        for (let x = 0; x < W; x++, o += 4) {
          const u = nu / dd - 0.5, v = nv / dd - 0.5;
          nu += hm[0]; nv += hm[3]; dd += hm[6];
          let x0 = Math.floor(u), y0 = Math.floor(v);
          const fx = u - x0, fy = v - y0;
          let x1 = x0 + 1, y1 = y0 + 1;
          x0 = clamp(x0, 0, mx); x1 = clamp(x1, 0, mx); y0 = clamp(y0, 0, my); y1 = clamp(y1, 0, my);
          const a = (y0 * sw + x0) * 4, b = (y0 * sw + x1) * 4, cc = (y1 * sw + x0) * 4, e = (y1 * sw + x1) * 4;
          const w00 = (1 - fx) * (1 - fy), w10 = fx * (1 - fy), w01 = (1 - fx) * fy, w11 = fx * fy;
          for (let ch = 0; ch < 4; ch++) od[o + ch] = sd[a + ch] * w00 + sd[b + ch] * w10 + sd[cc + ch] * w01 + sd[e + ch] * w11;
        }
      }
      c.getContext('2d').putImageData(out, 0, 0);
      pCache = { key, canvas: c };
      return c;
    }
    /** Maße nach Spiegeln/Drehen: full = ganzes gedrehtes Bild (Drehen/Ausrichten), sonst Ergebnis vor dem Zuschnitt */
    function geo(full) {
      const src = persp();
      let rw = src.width, rh = src.height;
      if (st.rot % 180) [rw, rh] = [rh, rw];
      const fill = st.fillOn && round1(st.angle) !== 0;
      let cw, ch;
      if (full || fill) [cw, ch] = rotatedBox(rw, rh, st.angle).map(v => Math.max(1, Math.round(v)));
      else [cw, ch] = inscribed(rw, rh, st.angle).map(v => Math.max(1, Math.floor(v)));
      return { src, rw, rh, cw, ch, fill };
    }
    function drawGeo(g) {
      view.width = g.cw; view.height = g.ch;
      ctx.clearRect(0, 0, g.cw, g.ch);
      if (g.fill) { ctx.fillStyle = st.fill; ctx.fillRect(0, 0, g.cw, g.ch); }
      ctx.save();
      ctx.translate(g.cw / 2, g.ch / 2);
      ctx.rotate(st.angle * Math.PI / 180);
      ctx.rotate(st.rot * Math.PI / 180);
      ctx.scale(st.flip_h ? -1 : 1, st.flip_v ? -1 : 1);
      ctx.imageSmoothingQuality = 'high';
      ctx.drawImage(g.src, -g.src.width / 2, -g.src.height / 2);
      ctx.restore();
    }
    /** Anzeigegröße: Bild einpassen, Platz für Anfasser lassen */
    function fit(w, h) {
      const pad = 28, aw = Math.max(80, stage.clientWidth - pad * 2), ah = Math.max(80, stage.clientHeight - pad * 2);
      const s = Math.min(aw / w, ah / h, 2);
      const cw = Math.max(1, Math.round(w * s)), ch = Math.max(1, Math.round(h * s));
      wrap.style.width = cw + 'px'; wrap.style.height = ch + 'px';
      view.style.width = cw + 'px'; view.style.height = ch + 'px';
      ov.setAttribute('viewBox', `0 0 ${cw} ${ch}`);
      ov.setAttribute('width', cw); ov.setAttribute('height', ch);
      return { cw, ch };
    }

    // ---------------------------------------------------------- Zeichnen
    let raf = 0;
    const schedule = () => { if (!raf) raf = requestAnimationFrame(() => { raf = 0; render(); }); };
    let V = { cw: 1, ch: 1 };   // aktuelle Anzeigegröße (CSS-Pixel)
    function render() {
      if (!loaded) return;   // <canvas> ist ohne Bild 300 × 150 groß – erst nach dem Laden zeichnen
      stage.dataset.tool = tool;
      stage.classList.toggle('is-before', before);
      stage.classList.toggle('is-horizon', !!horizon);
      let svg = '';
      hs.textContent = '';
      if (before) {
        view.width = base.width; view.height = base.height;
        ctx.drawImage(base, 0, 0);
        V = fit(base.width, base.height);
      } else if (tool === 'perspective') {
        view.width = base.width; view.height = base.height;
        ctx.drawImage(base, 0, 0);
        V = fit(base.width, base.height);
        const q = (st.quad || IDENT).map(([x, y]) => [x * V.cw, y * V.ch]);
        const pts = q.map(p => p.join(',')).join(' ');
        svg += `<path class="ie-dim" fill-rule="evenodd" d="M0 0H${V.cw}V${V.ch}H0Z M${q.map(p => p.join(' ')).join(' L')}Z"/>`;
        svg += `<polygon class="ie-quad" points="${pts}"/>`;
        // Hilfslinien im Viereck (Drittel), damit Kanten leichter anliegen
        for (const f of [1 / 3, 2 / 3]) {
          const l = (a, b) => [a[0] + (b[0] - a[0]) * f, a[1] + (b[1] - a[1]) * f];
          const [p1, p2, p3, p4] = [l(q[0], q[1]), l(q[3], q[2]), l(q[0], q[3]), l(q[1], q[2])];
          svg += `<line class="ie-grid" x1="${p1[0]}" y1="${p1[1]}" x2="${p2[0]}" y2="${p2[1]}"/><line class="ie-grid" x1="${p3[0]}" y1="${p3[1]}" x2="${p4[0]}" y2="${p4[1]}"/>`;
        }
        const names = [t('Ecke oben links'), t('Ecke oben rechts'), t('Ecke unten rechts'), t('Ecke unten links')];
        q.forEach(([x, y], i) => handle(x, y, names[i] + ' – ' + t('Pfeiltasten verschieben'), 'q' + i));
        drawMini();
      } else if (tool === 'crop') {
        const g = geo(false);
        drawGeo(g);
        V = fit(g.cw, g.ch);
        const c = st.crop || { x: 0, y: 0, w: 1, h: 1 };
        const x = c.x * V.cw, y = c.y * V.ch, w = c.w * V.cw, h = c.h * V.ch;
        svg += `<path class="ie-dim" fill-rule="evenodd" d="M0 0H${V.cw}V${V.ch}H0Z M${x} ${y}h${w}v${h}h${-w}Z"/><rect class="ie-rect" x="${x}" y="${y}" width="${w}" height="${h}"/>`;
        for (const f of [1 / 3, 2 / 3]) svg += `<line class="ie-grid" x1="${x + w * f}" y1="${y}" x2="${x + w * f}" y2="${y + h}"/><line class="ie-grid" x1="${x}" y1="${y + h * f}" x2="${x + w}" y2="${y + h * f}"/>`;
        const cbox = d.createElement('div');
        cbox.className = 'ie-box'; cbox.tabIndex = 0; cbox.dataset.h = 'box';
        cbox.setAttribute('role', 'group');
        cbox.setAttribute('aria-label', t('Ausschnitt – Pfeiltasten verschieben, ⇧ in großen Schritten'));
        Object.assign(cbox.style, { left: x + 'px', top: y + 'px', width: w + 'px', height: h + 'px' });
        hs.append(cbox);
        const names = [t('Ecke oben links'), t('Ecke oben rechts'), t('Ecke unten rechts'), t('Ecke unten links')];
        [[x, y], [x + w, y], [x + w, y + h], [x, y + h]].forEach(([hx, hy], i) => handle(hx, hy, names[i] + ' – ' + t('Pfeiltasten ändern die Größe'), 'c' + i));
      } else {
        const g = geo(true);
        drawGeo(g);
        V = fit(g.cw, g.ch);
        const s = V.cw / g.cw;
        if (round1(st.angle) && !g.fill) {
          const [iw, ih] = inscribed(g.rw, g.rh, st.angle).map(v => v * s);
          const x = (V.cw - iw) / 2, y = (V.ch - ih) / 2;
          svg += `<path class="ie-dim" fill-rule="evenodd" d="M0 0H${V.cw}V${V.ch}H0Z M${x} ${y}h${iw}v${ih}h${-iw}Z"/><rect class="ie-rect" x="${x}" y="${y}" width="${iw}" height="${ih}"/>`;
        }
        if (tool === 'straighten' || Date.now() < gridUntil) {
          const step = Math.max(24, Math.round(Math.min(V.cw, V.ch) / 8));
          for (let gx = step; gx < V.cw; gx += step) svg += `<line class="ie-grid ie-grid--fine" x1="${gx}" y1="0" x2="${gx}" y2="${V.ch}"/>`;
          for (let gy = step; gy < V.ch; gy += step) svg += `<line class="ie-grid ie-grid--fine" x1="0" y1="${gy}" x2="${V.cw}" y2="${gy}"/>`;
        }
        if (horizon?.b) svg += `<line class="ie-line" x1="${horizon.a[0]}" y1="${horizon.a[1]}" x2="${horizon.b[0]}" y2="${horizon.b[1]}"/>`;
      }
      ov.innerHTML = svg;
      const [ow, oh] = outputSize(O.w, O.h, toEdit(st));
      info.textContent = before ? t('Original: {w} × {h} px', { w: O.w, h: O.h }) : t('Ergebnis: {w} × {h} px', { w: ow, h: oh });
      $('[data-resetall]').disabled = !toEdit(st);
      if (focusKey) { hs.querySelector(`[data-h="${focusKey}"]`)?.focus({ preventScroll: true }); focusKey = ''; }
    }
    let focusKey = '';
    function handle(x, y, label, key) {
      const b = d.createElement('button');
      b.type = 'button'; b.className = 'ie-h'; b.dataset.h = key;
      b.setAttribute('aria-label', label);
      b.style.left = x + 'px'; b.style.top = y + 'px';
      hs.append(b);
    }
    function drawMini() {
      const mini = panel.querySelector('[data-mini]');
      if (!mini) return;
      if (!loaded) return;
      const p = persp(), k = Math.min(1, 260 / Math.max(p.width, p.height));
      mini.width = Math.max(1, Math.round(p.width * k)); mini.height = Math.max(1, Math.round(p.height * k));
      mini.getContext('2d').drawImage(p, 0, 0, mini.width, mini.height);
      const s = panel.querySelector('[data-mini-size]');
      if (s) { const [w, h] = outputSize(O.w, O.h, { quad: toEdit(st)?.quad, quad_fit: st.quad_fit }); s.textContent = `${w} × ${h} px`; }
    }

    // ---------------------------------------------------------- Werkzeuge (rechts)
    const reset = (label, what) => `<button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-reset="${what}">${esc(label)}</button>`;
    const angleCtl = () => `<div class="ie-range">
        <label for="ie-angle">${esc(t('Winkel'))}</label><output id="ie-angle-o" for="ie-angle"></output>
        <input id="ie-angle" type="range" min="-${MAX_ANGLE}" max="${MAX_ANGLE}" step="0.1" data-angle>
        <div class="ie-steps"><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-nudge="-0.1" aria-label="${esc(t('0,1° gegen den Uhrzeigersinn'))}">−0,1°</button>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-nudge="0" aria-label="${esc(t('Winkel auf 0°'))}">0°</button>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-nudge="0.1" aria-label="${esc(t('0,1° im Uhrzeigersinn'))}">+0,1°</button></div></div>`;
    const cornersCtl = () => `<fieldset class="ie-opts"><legend>${esc(t('Ecken nach dem Drehen'))}</legend>
        <label class="f-check"><input type="radio" name="ie-fill" value="0"${st.fillOn ? '' : ' checked'}> <span>${esc(t('Zuschneiden (größtes Rechteck, keine leeren Ecken)'))}</span></label>
        <label class="f-check"><input type="radio" name="ie-fill" value="1"${st.fillOn ? ' checked' : ''}> <span>${esc(t('Mit Farbe füllen'))}</span> <input type="color" data-fillcolor value="${esc(st.fill)}" aria-label="${esc(t('Füllfarbe'))}"></label></fieldset>`;
    const PANELS = {
      crop: () => `<h3>${esc(L.crop)}</h3>
        <p class="ie-help">${esc(t('Rahmen ziehen oder an den Ecken ändern. Wirkt auf das gedrehte und entzerrte Bild. Der Fokuspunkt bleibt erhalten.'))}</p>
        <fieldset class="ie-ratios"><legend>${esc(t('Seitenverhältnis'))}</legend>
          ${EDIT_RATIOS.map(r => `<button type="button" data-ratio="${r}" aria-pressed="${st.ratio === r}"><span class="ie-shape"${r === 'free' ? '' : ` data-ar="${r}"`}></span>${esc(r === 'free' ? t('Frei') : r)}</button>`).join('')}
        </fieldset>
        ${hadCrops ? `<p class="ie-note">${esc(t('Eigene Zuschnitte je Format (16:9, 4:3 …) werden beim Speichern zurückgesetzt, weil sich das Bild ändert.'))}</p>` : ''}
        <div class="ie-acts">${reset(t('Zuschnitt zurücksetzen'), 'crop')}</div>`,
      rotate: () => `<h3>${esc(L.rotate)}</h3>
        <div class="ie-acts"><button type="button" class="adm-btn adm-btn--small" data-rot="-90">${icon('rotl')} ${esc(t('90° nach links'))}</button>
          <button type="button" class="adm-btn adm-btn--small" data-rot="90">${icon('rotate')} ${esc(t('90° nach rechts'))}</button></div>
        <p class="ie-help">${esc(t('Freie Drehung von −45° bis +45° in 0,1°-Schritten. Pfeiltasten im Bild: 0,1°, mit ⇧ 1°.'))}</p>
        ${angleCtl()}${cornersCtl()}
        <div class="ie-acts">${reset(t('Drehung zurücksetzen'), 'rotate')}</div>`,
      flip: () => `<h3>${esc(L.flip)}</h3>
        <div class="ie-acts"><button type="button" class="adm-btn adm-btn--small" data-flip="h" aria-pressed="${st.flip_h}">${icon('flip')} ${esc(t('Horizontal spiegeln'))}</button>
          <button type="button" class="adm-btn adm-btn--small" data-flip="v" aria-pressed="${st.flip_v}">${icon('flipv')} ${esc(t('Vertikal spiegeln'))}</button></div>
        <p class="ie-help">${esc(t('Achtung bei Schrift und Logos: Gespiegelt stehen sie seitenverkehrt.'))}</p>
        <div class="ie-acts">${reset(t('Spiegeln zurücksetzen'), 'flip')}</div>`,
      straighten: () => `<h3>${esc(L.straighten)}</h3>
        <p class="ie-help">${esc(t('Das Raster hilft, Horizont und Kanten gerade zu richten. Oder mit „Horizont ziehen“ eine Linie entlang des Horizonts oder einer Kante ziehen – der Winkel wird daraus berechnet.'))}</p>
        <div class="ie-acts"><button type="button" class="adm-btn adm-btn--small" data-horizon aria-pressed="${!!horizon}">${icon('horizon')} ${esc(t('Horizont ziehen'))}</button></div>
        ${angleCtl()}${cornersCtl()}
        <div class="ie-acts">${reset(t('Ausrichten zurücksetzen'), 'straighten')}</div>`,
      perspective: () => `<h3>${esc(L.perspective)}</h3>
        <p class="ie-help">${esc(t('Die vier Ecken auf die Ecken einer schrägen Fläche ziehen (Gebäude, Schild, Bildschirm, Dokument) – sie wird gerade gerückt. Pfeiltasten verschieben die gewählte Ecke, mit ⇧ in großen Schritten.'))}</p>
        <fieldset class="ie-opts"><legend>${esc(t('Größe des Ergebnisses'))}</legend>
          <label class="f-check"><input type="radio" name="ie-fit" value="edges"${st.quad_fit !== 'image' ? ' checked' : ''}> <span>${esc(t('Aus den Kantenlängen'))}</span></label>
          <label class="f-check"><input type="radio" name="ie-fit" value="image"${st.quad_fit === 'image' ? ' checked' : ''}> <span>${esc(t('Seitenverhältnis des Bildes behalten'))}</span></label></fieldset>
        <figure class="ie-mini"><canvas data-mini role="img" aria-label="${esc(t('Vorschau des entzerrten Bildes'))}"></canvas><figcaption>${esc(t('Ergebnis'))} <span data-mini-size></span></figcaption></figure>
        ${opts.engine === 'gd' ? `<p class="ie-note">${esc(t('Auf diesem Server entzerrt GD (ohne Imagick) – das Ergebnis ist auf höchstens 2400 px begrenzt.'))}</p>` : ''}
        <div class="ie-acts">${reset(t('Entzerren zurücksetzen'), 'perspective')}</div>`,
    };
    function showTool(k, focusTab = false) {
      tool = k; horizon = null;
      $$('[role=tab]').forEach(b => { const on = b.dataset.tool === k; b.setAttribute('aria-selected', on); b.tabIndex = on ? 0 : -1; if (on && focusTab) b.focus(); });
      panel.setAttribute('aria-labelledby', 'ie-tab-' + k);
      panel.innerHTML = PANELS[k]();
      panel.querySelectorAll('[data-ar]').forEach(el => { el.style.aspectRatio = el.dataset.ar.replace(':', '/'); });
      syncPanel();
      render();
    }
    function syncPanel() {
      const a = panel.querySelector('[data-angle]');
      if (a) { a.value = st.angle; const txt = String(round1(st.angle)).replace('.', ',') + '°'; panel.querySelector('#ie-angle-o').textContent = txt; a.setAttribute('aria-valuetext', txt); }
      panel.querySelectorAll('[data-ratio]').forEach(b => b.setAttribute('aria-pressed', b.dataset.ratio === st.ratio));
      panel.querySelectorAll('[data-flip]').forEach(b => b.setAttribute('aria-pressed', b.dataset.flip === 'h' ? st.flip_h : st.flip_v));
      panel.querySelector('[data-horizon]')?.setAttribute('aria-pressed', !!horizon);
      panel.querySelectorAll('[name=ie-fill]').forEach(r => { r.checked = (r.value === '1') === st.fillOn; });
      const fc = panel.querySelector('[data-fillcolor]'); if (fc) fc.value = st.fill;
    }
    const setAngle = v => { st.angle = clamp(round1(v), -MAX_ANGLE, MAX_ANGLE); syncPanel(); schedule(); };

    // Spiegeln/Drehen so umrechnen, dass die gespeicherte Reihenfolge (Spiegeln → 90° → Winkel) gleich bleibt
    function flip(axis) {
      if (axis === 'h') st.flip_h = !st.flip_h; else st.flip_v = !st.flip_v;
      st.rot = (360 - st.rot) % 360;
      st.angle = -st.angle || 0;
      if (st.crop) { if (axis === 'h') st.crop.x = 1 - st.crop.x - st.crop.w; else st.crop.y = 1 - st.crop.y - st.crop.h; }
    }
    function rot90(dir) {
      st.rot = (st.rot + (dir > 0 ? 90 : 270)) % 360;
      const c = st.crop;
      if (c) st.crop = dir > 0 ? { x: 1 - c.y - c.h, y: c.x, w: c.h, h: c.w } : { x: c.y, y: 1 - c.x - c.w, w: c.h, h: c.w };
      if (st.ratio !== 'free') { const [a, b] = st.ratio.split(':'); st.ratio = EDIT_RATIOS.includes(`${b}:${a}`) ? `${b}:${a}` : 'free'; }
    }
    /** Größter Ausschnitt im Verhältnis r um die Mitte des aktuellen Ausschnitts */
    function applyRatio(r) {
      st.ratio = r;
      const ar = ratioOf(r);
      if (!ar) { schedule(); return; }
      const g = geo(false), c = st.crop || { x: 0, y: 0, w: 1, h: 1 };
      const cx = c.x + c.w / 2, cy = c.y + c.h / 2;
      let w = 1, h = (g.cw / ar) / g.ch;
      if (h > 1) { h = 1; w = (g.ch * ar) / g.cw; }
      st.crop = { w, h, x: clamp(cx - w / 2, 0, 1 - w), y: clamp(cy - h / 2, 0, 1 - h) };
      syncPanel(); schedule();
    }
    /** Ausschnitt an einer Ecke ändern (i = 0…3), Seitenverhältnis einhalten */
    function resizeCrop(i, px, py) {
      const c = st.crop || { x: 0, y: 0, w: 1, h: 1 };
      const ox = i === 0 || i === 3 ? c.x + c.w : c.x, oy = i < 2 ? c.y + c.h : c.y;   // gegenüberliegende Ecke
      const g = geo(false), ar = ratioOf(st.ratio), min = 0.04;
      let w = Math.abs(clamp(px, 0, 1) - ox), h = Math.abs(clamp(py, 0, 1) - oy);
      const maxW = i === 0 || i === 3 ? ox : 1 - ox, maxH = i < 2 ? oy : 1 - oy;
      w = clamp(w, min, maxW); h = clamp(h, min, maxH);
      if (ar) {
        h = w * g.cw / (ar * g.ch);
        if (h > maxH) { h = maxH; w = h * ar * g.ch / g.cw; }
      }
      st.crop = { w, h, x: i === 0 || i === 3 ? ox - w : ox, y: i < 2 ? oy - h : oy };
    }
    function moveQuad(i, x, y) {
      const q = (st.quad || IDENT).map(p => [...p]);
      q[i] = [clamp(x, 0, 1), clamp(y, 0, 1)];
      if (convex(q)) st.quad = q;
    }

    // ---------------------------------------------------------- Zeiger (Maus, Stift, Touch)
    const rel = e => { const r = wrap.getBoundingClientRect(); return [(e.clientX - r.left) / r.width, (e.clientY - r.top) / r.height]; };
    let drag = null;
    wrap.addEventListener('pointerdown', e => {
      if (before || e.button > 0) return;
      const h = e.target.closest('[data-h]');
      if (tool === 'straighten' && horizon) {
        const r = wrap.getBoundingClientRect();
        horizon = { a: [e.clientX - r.left, e.clientY - r.top], b: null };
        drag = { kind: 'line' };
      } else if (h) {
        const k = h.dataset.h;
        drag = { kind: k, start: rel(e), crop: st.crop ? { ...st.crop } : { x: 0, y: 0, w: 1, h: 1 } };
        h.focus({ preventScroll: true });
      } else return;
      e.preventDefault();
      wrap.setPointerCapture(e.pointerId);
      wrap.classList.add('is-drag');
    });
    wrap.addEventListener('pointermove', e => {
      if (!drag) return;
      const [x, y] = rel(e);
      if (drag.kind === 'line') { const r = wrap.getBoundingClientRect(); horizon.b = [e.clientX - r.left, e.clientY - r.top]; schedule(); return; }
      if (drag.kind[0] === 'q') moveQuad(+drag.kind[1], x, y);
      else if (drag.kind[0] === 'c') resizeCrop(+drag.kind[1], x, y);
      else if (drag.kind === 'box') {
        const c = drag.crop;
        st.crop = { ...c, x: clamp(c.x + x - drag.start[0], 0, 1 - c.w), y: clamp(c.y + y - drag.start[1], 0, 1 - c.h) };
      }
      focusKey = drag.kind;
      schedule();
    });
    const up = e => {
      if (!drag) return;
      if (drag.kind === 'line' && horizon?.b) {
        const dx = horizon.b[0] - horizon.a[0], dy = horizon.b[1] - horizon.a[1];
        if (Math.hypot(dx, dy) >= 20) {
          let deg = Math.atan2(dy, dx) * 180 / Math.PI;
          if (deg > 90) deg -= 180; else if (deg <= -90) deg += 180;
          if (Math.abs(deg) > 45) deg = deg > 0 ? deg - 90 : deg + 90;   // senkrechte Kante
          setAngle(st.angle - deg);
          info.textContent = t('Winkel aus der Linie: {deg}°', { deg: String(round1(st.angle)).replace('.', ',') });
        }
        horizon = null; syncPanel();
      }
      drag = null;
      wrap.classList.remove('is-drag');
      try { wrap.releasePointerCapture(e.pointerId); } catch {}
      schedule();
    };
    wrap.addEventListener('pointerup', up);
    wrap.addEventListener('pointercancel', up);

    // ---------------------------------------------------------- Tastatur
    dlg.addEventListener('keydown', e => {
      const map = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
      const tab = e.target.closest?.('[role=tab]');
      if (tab && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
        e.preventDefault();
        const i = EDIT_TOOLS.indexOf(tab.dataset.tool);
        showTool(EDIT_TOOLS[(i + (e.key === 'ArrowRight' ? 1 : EDIT_TOOLS.length - 1)) % EDIT_TOOLS.length], true);
        return;
      }
      const h = e.target.closest?.('[data-h]');
      if (h && map[e.key]) {
        e.preventDefault();
        const [dx, dy] = map[e.key], k = h.dataset.h;
        if (k[0] === 'q') {
          const s = e.shiftKey ? 0.02 : 0.002, p = (st.quad || IDENT)[+k[1]];
          moveQuad(+k[1], p[0] + dx * s, p[1] + dy * s);
        } else if (k === 'box') {
          const s = e.shiftKey ? 0.05 : 0.005, c = st.crop || { x: 0, y: 0, w: 1, h: 1 };
          st.crop = { ...c, x: clamp(c.x + dx * s, 0, 1 - c.w), y: clamp(c.y + dy * s, 0, 1 - c.h) };
        } else if (k[0] === 'c') {
          const s = e.shiftKey ? 0.05 : 0.005, c = st.crop || { x: 0, y: 0, w: 1, h: 1 }, i = +k[1];
          const cx = i === 0 || i === 3 ? c.x : c.x + c.w, cy = i < 2 ? c.y : c.y + c.h;
          resizeCrop(i, cx + dx * s, cy + dy * s);
        }
        focusKey = k;
        render();
        return;
      }
      // Drehen/Ausrichten: Pfeiltasten im Bild ändern den Winkel
      if ((e.target === stage || e.target === wrap) && (tool === 'rotate' || tool === 'straighten') && (e.key === 'ArrowLeft' || e.key === 'ArrowRight')) {
        e.preventDefault();
        gridUntil = Date.now() + 900;
        setAngle(st.angle + (e.key === 'ArrowRight' ? 1 : -1) * (e.shiftKey ? 1 : 0.1));
        setTimeout(schedule, 950);
      }
    });
    stage.tabIndex = 0;
    stage.setAttribute('aria-label', t('Bildfläche – im Werkzeug Drehen und Ausrichten ändern die Pfeiltasten den Winkel'));

    // ---------------------------------------------------------- Bedienelemente
    dlg.addEventListener('click', async e => {
      const b = e.target.closest('button');
      if (!b || b.disabled) return;
      if (b.dataset.tool) return showTool(b.dataset.tool);
      if (b.dataset.rot) { rot90(+b.dataset.rot); render(); return; }
      if (b.dataset.flip) { flip(b.dataset.flip); syncPanel(); render(); return; }
      if (b.dataset.ratio) { applyRatio(b.dataset.ratio); return; }
      if (b.dataset.nudge !== undefined) { gridUntil = Date.now() + 900; setAngle(+b.dataset.nudge === 0 ? 0 : st.angle + +b.dataset.nudge); setTimeout(schedule, 950); return; }
      if (b.hasAttribute('data-horizon')) { horizon = horizon ? null : { a: null, b: null }; syncPanel(); render(); if (horizon) info.textContent = t('Linie entlang des Horizonts oder einer Kante ziehen'); return; }
      if (b.dataset.reset) {
        const w = b.dataset.reset;
        if (w === 'crop') { st.crop = null; st.ratio = 'free'; }
        if (w === 'rotate') { st.rot = 0; st.angle = 0; st.fillOn = false; }
        if (w === 'straighten') st.angle = 0;
        if (w === 'flip') { st.flip_h = false; st.flip_v = false; }
        if (w === 'perspective') st.quad = null;
        syncPanel(); render(); return;
      }
      if (b.hasAttribute('data-resetall')) { st = fromEdit(null); showTool(tool); return; }
      if (b.hasAttribute('data-before')) { before = !before; b.setAttribute('aria-pressed', before); render(); return; }
      if (b.hasAttribute('data-cancel')) return close();
      if (b.hasAttribute('data-save')) return save(b);
    });
    panel.addEventListener('input', e => {
      if (e.target.matches('[data-angle]')) { gridUntil = Date.now() + 900; setAngle(+e.target.value); setTimeout(schedule, 950); }
      if (e.target.matches('[data-fillcolor]')) { st.fill = e.target.value; st.fillOn = true; syncPanel(); schedule(); }
    });
    panel.addEventListener('change', e => {
      if (e.target.name === 'ie-fill') { st.fillOn = e.target.value === '1'; schedule(); }
      if (e.target.name === 'ie-fit') { st.quad_fit = e.target.value; schedule(); }
    });

    async function save(btn) {
      if (busy) return;
      busy = true; btn.disabled = true; err.hidden = true;
      busyEl.textContent = t('Bild wird berechnet …'); busyEl.hidden = false;
      try {
        const res = await opts.save(toEdit(st));
        result = res.item;
        busy = false;
        dlg.close();
      } catch (ex) {
        err.textContent = ex.message; err.hidden = false;
      } finally { busy = false; btn.disabled = false; busyEl.hidden = true; }
    }
    async function close() {
      if (busy) return;
      if (JSON.stringify(toEdit(st)) !== start && !(await ask({ title: t('Ungespeicherte Änderungen verwerfen?'), ok: t('Verwerfen') }))) return;
      dlg.close();
    }
    dlg.oncancel = e => { e.preventDefault(); close(); };
    const ro = new ResizeObserver(() => schedule());
    dlg.onclose = () => { ro.disconnect(); cancelAnimationFrame(raf); dlg.remove(); resolve(result); };
    dlg.showModal();
    ro.observe(stage);
    showTool(tool, true);
  });
}
