/*
 * Grafik-Editor – Wiederholen (Ausbaustufe 3b):
 *  - ⌘D „Duplizieren und Transformation wiederholen“ wie in Affinity/Illustrator: Kopie verschieben/drehen/skalieren, dann ⌘D –
 *    jede weitere Kopie bekommt denselben Versatz (Drehung und Skalierung, soweit sie als Transformation am Element stehen).
 *  - Objekt → Wiederholen … (⌥⌘D): Versatz (Anzahl, X/Y, Drehung, Skalierung je Kopie), Kreis (um einen Mittelpunkt, Winkel,
 *    Objekte mitdrehen), Raster (Spalten × Zeilen, Abstand). Vorschau auf der Fläche, ein Schritt im Protokoll. MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { NS, num, docBounds } from './geom.js';
import { applyTransform, remapIds } from './doc.js';
import { modal } from './ui.js';

const centre = b => ({ x: b.x + b.w / 2, y: b.y + b.h / 2 });
const about = (c, M) => new DOMMatrix().translate(c.x, c.y).multiply(M).translate(-c.x, -c.y);

/** Bounds mehrerer Elemente im Dokument */
function boundsOf(ed, list) {
  let u = null;
  for (const el of list) { const b = docBounds(el, ed.root); if (b) u = u ? { x: Math.min(u.x, b.x), y: Math.min(u.y, b.y), w: Math.max(u.x + u.w, b.x + b.w) - Math.min(u.x, b.x), h: Math.max(u.y + u.h, b.y + b.h) - Math.min(u.y, b.y) } : b; }
  return u;
}

/** Linearer Teil (Drehung/Skalierung/Neigung) der Transformation, die a in b überführt – nur was als transform am Element steht */
function linearDelta(ed, a, b) {
  const A = ed.root.getScreenCTM && a.getCTM ? DOMMatrix.fromMatrix(a.getCTM()) : new DOMMatrix();
  const B = b.getCTM ? DOMMatrix.fromMatrix(b.getCTM()) : new DOMMatrix();
  const L = B.multiply(A.inverse());
  L.e = 0; L.f = 0;
  return L;
}

/** Kopien der Elemente (hinter dem jeweiligen Original) mit Dokument-Transformation T */
export function cloneWith(ed, list, T, after = null) {
  const out = [];
  let anchor = after;
  for (const el of list) {
    const c = el.cloneNode(true);
    (anchor || el).after(c);
    anchor = c;
    remapIds(c, ed.root);
    applyTransform(c, T, ed.root);
    out.push(c);
  }
  return out;
}

/**
 * ⌘D: Duplizieren. Ist die Auswahl die letzte Kopie (unverändert oder verschoben/gedreht), wiederholt die neue Kopie denselben
 * Schritt. Rückgabe: Kopien.
 */
export function duplicateAgain(ed, off) {
  const L = ed.lastDup;
  const same = L && L.copies.length === ed.sel.length && L.copies.every((c, i) => c === ed.sel[i] && c.isConnected) && L.src.every(s => s.isConnected);
  let T;
  if (same) {
    if (L.T) T = L.T;
    else {
      const bs = boundsOf(ed, L.src), bc = boundsOf(ed, L.copies);
      const lin = linearDelta(ed, L.src[0], L.copies[0]);
      const cc = centre(bc), cs = centre(bs);
      // Versatz der Mitten, dazu Drehung/Skalierung um die Mitte der neuen Kopie
      T = new DOMMatrix().translate(cc.x - cs.x, cc.y - cs.y);
      if (!lin.isIdentity) T = about({ x: cc.x + (cc.x - cs.x), y: cc.y + (cc.y - cs.y) }, lin).multiply(T);
    }
  } else T = new DOMMatrix().translate(off, off);
  const src = [...ed.sel];
  const copies = cloneWith(ed, src, T, src[src.length - 1]);
  ed.lastDup = { src, copies, T: same ? T : null };
  return { copies, again: same };
}

/** Dialog „Wiederholen …“ mit Vorschau */
export async function repeatDialog(ed) {
  const list = ed.ordered(ed.sel);
  if (!list.length) return;
  const b = boundsOf(ed, list);
  if (!b) return;
  const c0 = centre(b);
  let preview = [];
  const clear = () => { for (const p of preview) p.remove(); preview = []; };
  const build = v => {
    clear();
    const n = Math.max(1, Math.min(500, Math.round(+v.count || 1)));
    const host = list[list.length - 1];
    let anchor = host;
    const add = T => { const cs = cloneWith(ed, list, T, anchor); anchor = cs[cs.length - 1]; preview.push(...cs); };
    if (v.mode === 'offset') {
      let T = new DOMMatrix();
      for (let i = 1; i <= n; i++) {
        const dx = (+v.dx || 0) * i, dy = (+v.dy || 0) * i, r = (+v.rot || 0) * i, s = ((+v.scale || 100) / 100) ** i;
        T = new DOMMatrix().translate(dx, dy).multiply(about(c0, new DOMMatrix().rotate(r).scale(s)));
        add(T);
      }
    } else if (v.mode === 'radial') {
      const cx = +v.cx, cy = +v.cy, span = +v.span || 360, total = n + 1;
      const step = Math.abs(span) >= 360 ? span / total : span / Math.max(1, n);
      for (let i = 1; i <= n; i++) {
        const a = step * i;
        let T = about({ x: cx, y: cy }, new DOMMatrix().rotate(a));
        // ohne Mitdrehen: nur die Mitte wandert auf dem Kreis
        if (!v.spin) { const q = new DOMPoint(c0.x, c0.y).matrixTransform(T); T = new DOMMatrix().translate(q.x - c0.x, q.y - c0.y); }
        add(T);
      }
    } else {
      const cols = Math.max(1, Math.min(100, Math.round(+v.cols || 1))), rows = Math.max(1, Math.min(100, Math.round(+v.rows || 1)));
      const gx = b.w + (+v.gx || 0), gy = b.h + (+v.gy || 0);
      for (let r = 0; r < rows; r++) for (let c = 0; c < cols; c++) if (r || c) add(new DOMMatrix().translate(c * gx, r * gy));
    }
    ed.draw();
  };
  const fmt = x => num(x, 2);
  const r = await modal(ed.el, {
    title: t('Wiederholen'), ok: t('Wiederholen'),
    body: `<fieldset class="f"><legend>${esc(t('Art'))}</legend><div class="ge-row3">
        <label class="f-check"><input type="radio" name="mode" value="offset" checked> ${esc(t('Versatz'))}</label>
        <label class="f-check"><input type="radio" name="mode" value="radial"> ${esc(t('Kreis'))}</label>
        <label class="f-check"><input type="radio" name="mode" value="grid"> ${esc(t('Raster'))}</label></div></fieldset>
      <div data-m="offset"><div class="ge-row2"><label class="f">${esc(t('Anzahl Kopien'))}<input type="number" name="count" min="1" max="500" value="4"></label>
          <label class="f">${esc(t('Drehung je Kopie (°)'))}<input type="number" name="rot" step="1" value="0"></label></div>
        <div class="ge-row2"><label class="f">${esc(t('Versatz X'))}<input type="number" name="dx" step="1" value="${fmt(b.w + 10)}"></label>
          <label class="f">${esc(t('Versatz Y'))}<input type="number" name="dy" step="1" value="0"></label></div>
        <label class="f">${esc(t('Skalierung je Kopie (%)'))}<input type="number" name="scale" min="1" max="1000" step="1" value="100"></label></div>
      <div data-m="radial" hidden><div class="ge-row2"><label class="f">${esc(t('Mittelpunkt X'))}<input type="number" name="cx" step="1" value="${fmt(c0.x)}"></label>
          <label class="f">${esc(t('Mittelpunkt Y'))}<input type="number" name="cy" step="1" value="${fmt(c0.y + Math.max(b.h, b.w) * 1.5)}"></label></div>
        <label class="f">${esc(t('Winkel insgesamt (°)'))}<input type="number" name="span" step="1" value="360"></label>
        <label class="f-check"><input type="checkbox" name="spin" checked> ${esc(t('Objekte mitdrehen'))}</label></div>
      <div data-m="grid" hidden><div class="ge-row2"><label class="f">${esc(t('Spalten'))}<input type="number" name="cols" min="1" max="100" value="3"></label>
          <label class="f">${esc(t('Zeilen'))}<input type="number" name="rows" min="1" max="100" value="2"></label></div>
        <div class="ge-row2"><label class="f">${esc(t('Abstand X'))}<input type="number" name="gx" step="1" value="10"></label>
          <label class="f">${esc(t('Abstand Y'))}<input type="number" name="gy" step="1" value="10"></label></div></div>
      <label class="f-check"><input type="checkbox" name="group" checked> ${esc(t('Als Gruppe zusammenfassen'))}</label>
      <p class="f-help">${esc(t('Die Vorschau erscheint auf der Fläche. ⌘D wiederholt danach den letzten Schritt.'))}</p>`,
    onOpen: d => {
      const read = () => {
        const v = {};
        d.querySelectorAll('input').forEach(i => { if (i.type === 'radio') { if (i.checked) v[i.name] = i.value; } else v[i.name] = i.type === 'checkbox' ? i.checked : i.value; });
        // Anzahl gilt für alle Arten (Kreis: Kopien zusätzlich zum Original)
        if (v.mode === 'radial') v.count = d.querySelector('[data-m=radial] [name=rcount]')?.value || v.count;
        return v;
      };
      // Kreis: eigene Anzahl (Felder je Art)
      d.querySelector('[data-m=radial]').insertAdjacentHTML('afterbegin', `<label class="f">${esc(t('Anzahl Kopien'))}<input type="number" name="rcount" min="1" max="360" value="7"></label>`);
      const sync = () => { const v = read(); d.querySelectorAll('[data-m]').forEach(x => { x.hidden = x.dataset.m !== v.mode; }); build(v); };
      d.addEventListener('input', sync);
      d.addEventListener('change', sync);
      sync();
    },
  });
  if (!r) { clear(); ed.draw(); return; }
  if (!preview.length) return;
  let made = [...list, ...preview];
  if (r.group) {
    const g = document.createElementNS(NS, 'g');
    g.setAttribute('data-name', t('Wiederholung'));
    list[0].before(g);
    g.append(...ed.ordered(made));
    made = [g];
  }
  ed.sel = made;
  ed.lastDup = null;
  ed.commit(r.mode === 'radial' ? t('Wiederholen: Kreis') : r.mode === 'grid' ? t('Wiederholen: Raster') : t('Wiederholen: Versatz'));
}
