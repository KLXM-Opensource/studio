/*
 * Grafik-Editor – Verläufe auf der Fläche bearbeiten (Werkzeug „Verlauf“, F – wie das Verlaufswerkzeug in Affinity/
 * Illustrator): linear Anfang/Ende, radial Mitte/Radius, Farbstopps auf der Linie verschieben, Doppelklick auf die Linie
 * = Stopp hinzufügen, ⌫ = gewählten Stopp löschen. Ziehen auf einem Objekt ohne Verlauf legt einen linearen an.
 *
 * Gerechnet wird über die volle Abbildung Verlauf → Dokument: Element (docMatrix) · Rahmen (bei objectBoundingBox) ·
 * gradientTransform. Geteilte Verläufe werden vor dem Ändern für das Objekt kopiert (andere Objekte bleiben gleich). MIT
 */
import { NS, num, pt, docMatrix } from './geom.js';
import { getProp, setProp, refOf, useCount, uid, defs, gradientStops, writeStops } from './doc.js';
import { parseColor, toHex } from './color.js';
import { t } from '../_i18n.js';

const XLINK = 'http://www.w3.org/1999/xlink';

/** Attribut eines Verlaufs (auch über href geerbt) */
function gattr(root, g, name) {
  for (let cur = g, i = 0; cur && i < 8; i++) {
    if (cur.hasAttribute(name)) return cur.getAttribute(name);
    const href = cur.getAttribute('href') || cur.getAttributeNS(XLINK, 'href');
    cur = href?.startsWith('#') ? root.querySelector('#' + CSS.escape(href.slice(1))) : null;
  }
  return null;
}

export class GradientHandles {
  constructor(ed) { this.ed = ed; this.stop = 0; }

  get which() { return this.ed.style.target === 'stroke' ? 'stroke' : 'fill'; }

  /** Verlauf der (einzigen) Auswahl für Füllung bzw. Kontur */
  current() {
    const el = this.ed.sel.length === 1 ? this.ed.sel[0] : null;
    if (!el || ['g', 'switch'].includes(el.localName)) return null;
    const g = refOf(this.ed.root, getProp(el, this.which));
    return g && ['linearGradient', 'radialGradient'].includes(g.localName) ? { el, g } : null;
  }

  /** Abbildung Verlaufsraum → Dokument */
  matrix(el, g) {
    let M = docMatrix(el, this.ed.root);
    if ((gattr(this.ed.root, g, 'gradientUnits') || 'objectBoundingBox') !== 'userSpaceOnUse') {
      const b = el.getBBox();
      M = M.multiply(new DOMMatrix([b.width || 1, 0, 0, b.height || 1, b.x, b.y]));
    }
    const tl = g.gradientTransform?.baseVal;
    const m = tl && tl.numberOfItems ? tl.consolidate()?.matrix : null;
    return m ? M.multiply(new DOMMatrix([m.a, m.b, m.c, m.d, m.e, m.f])) : M;
  }

  /** Werte im Verlaufsraum (Prozent → Anteil bzw. Anteil der viewBox) */
  val(g, name, def) {
    const bbox = (gattr(this.ed.root, g, 'gradientUnits') || 'objectBoundingBox') !== 'userSpaceOnUse';
    const v = gattr(this.ed.root, g, name);
    if (v === null || v === '') return def;
    if (v.trim().endsWith('%')) { const f = parseFloat(v) / 100; if (bbox) return f; const vb = this.ed.vb; return f * (/x|cx|fx/.test(name) ? vb.w : /y/.test(name) ? vb.h : Math.hypot(vb.w, vb.h) / Math.SQRT2); }
    return parseFloat(v) || 0;
  }

  /** Punkte des Verlaufs im Verlaufsraum */
  geom(g) {
    if (g.localName === 'linearGradient') return { a: { x: this.val(g, 'x1', 0), y: this.val(g, 'y1', 0) }, b: { x: this.val(g, 'x2', 1), y: this.val(g, 'y2', 0) } };
    const c = { x: this.val(g, 'cx', 0.5), y: this.val(g, 'cy', 0.5) }, r = this.val(g, 'r', 0.5);
    return { a: c, b: { x: c.x + r, y: c.y }, r };
  }

  /** Verlauf nur für dieses Objekt (vor dem Ändern): Kopie mit allen Werten, wenn ihn andere mitbenutzen oder er erbt */
  own(el, g) {
    const root = this.ed.root;
    const inherits = g.hasAttribute('href') || g.hasAttributeNS(XLINK, 'href');
    if (useCount(root, g.id) <= 1 && !inherits) return g;
    const c = document.createElementNS(NS, g.localName);
    c.id = uid(root, 'verlauf-');
    for (const a of ['gradientUnits', 'gradientTransform', 'spreadMethod', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'fx', 'fy']) {
      const v = gattr(root, g, a);
      if (v !== null) c.setAttribute(a, v);
    }
    writeStops(c, gradientStops(root, g));
    g.after(c);
    setProp(el, this.which, `url(#${c.id})`);
    return c;
  }

  /** Überlagerung */
  overlay() {
    const cur = this.current();
    if (!cur) return '';
    const { el, g } = cur, M = this.matrix(el, g), S = p => this.ed.toOv(pt(M, p.x, p.y));
    const gm = this.geom(g), A = S(gm.a), B = S(gm.b);
    const stops = gradientStops(this.ed.root, g);
    let s = `<line class="ge-ov__gline" x1="${A.x}" y1="${A.y}" x2="${B.x}" y2="${B.y}"/><line class="ge-ov__ghitline" data-gh="line" x1="${A.x}" y1="${A.y}" x2="${B.x}" y2="${B.y}"/>`;
    if (g.localName === 'radialGradient') {
      // Kreis des Radius (im Verlaufsraum ein Kreis, auf der Fläche ggf. Ellipse)
      const pts = [];
      for (let i = 0; i <= 48; i++) { const a = i / 48 * Math.PI * 2; pts.push(S({ x: gm.a.x + Math.cos(a) * gm.r, y: gm.a.y + Math.sin(a) * gm.r })); }
      s += `<polyline class="ge-ov__gring" points="${pts.map(p => p.x + ',' + p.y).join(' ')}"/>`;
    }
    stops.forEach((st, i) => {
      const q = { x: A.x + (B.x - A.x) * st.offset, y: A.y + (B.y - A.y) * st.offset };
      s += `<rect class="ge-ov__gstop${i === this.stop ? ' is-on' : ''}" data-gh="stop:${i}" x="${q.x - 6}" y="${q.y - 6}" width="12" height="12" rx="2" style="fill:${toHex(parseColor(st.color)) || '#000'}"/>`;
    });
    s += `<circle class="ge-ov__gend" data-gh="a" cx="${A.x}" cy="${A.y}" r="6"/><circle class="ge-ov__gend ge-ov__gend--b" data-gh="b" cx="${B.x}" cy="${B.y}" r="6"/>`;
    return s;
  }

  /** Griff ziehen: a (Anfang/Mitte), b (Ende/Radius), stop:i, line (Doppelklick = Stopp) */
  down(e, key) {
    e.stopPropagation(); e.preventDefault();
    const ed = this.ed, cur = this.current();
    if (!cur) return;
    if (key === 'line') {
      // Doppelklick selbst erkennen (die Überlagerung wird zwischen den Klicks neu gezeichnet)
      const now = Date.now();
      if (e.detail !== 2 && now - (this.lastLine || 0) > 450) { this.lastLine = now; return; }
      this.lastLine = 0;
      const g = this.own(cur.el, cur.g), tt = this.project(cur.el, g, e);
      const st = gradientStops(ed.root, g), c = mix(st, tt);
      st.push({ offset: tt, color: c.color, opacity: c.opacity });
      st.sort((x, y) => x.offset - y.offset);
      this.stop = st.findIndex(x => x.offset === tt);
      writeStops(g, st);
      ed.commit(t('Farbstopp hinzugefügt'));
      return;
    }
    const g = this.own(cur.el, cur.g), M = this.matrix(cur.el, g), Mi = M.inverse();
    const bbox = (gattr(ed.root, g, 'gradientUnits') || 'objectBoundingBox') !== 'userSpaceOnUse', d = bbox ? 4 : 3;
    if (key.startsWith('stop:')) { this.stop = +key.slice(5); ed.panels.refresh(); }
    let moved = false;
    ed.track(e, p => {
      moved = true;
      const q = pt(Mi, p.x, p.y);
      if (key === 'a' || key === 'b') {
        if (g.localName === 'linearGradient') {
          g.setAttribute(key === 'a' ? 'x1' : 'x2', num(q.x, d)); g.setAttribute(key === 'a' ? 'y1' : 'y2', num(q.y, d));
          if (key === 'a') { for (const k of ['x2', 'y2']) if (!g.hasAttribute(k)) g.setAttribute(k, k === 'x2' ? '1' : '0'); }
        } else if (key === 'a') {
          g.setAttribute('cx', num(q.x, d)); g.setAttribute('cy', num(q.y, d)); g.removeAttribute('fx'); g.removeAttribute('fy');
        } else {
          const c = this.geom(g).a;
          g.setAttribute('r', num(Math.max(1e-3, Math.hypot(q.x - c.x, q.y - c.y)), d));
        }
      } else {
        const i = +key.slice(5), st = gradientStops(ed.root, g), gm = this.geom(g);
        const dx = gm.b.x - gm.a.x, dy = gm.b.y - gm.a.y, l2 = dx * dx + dy * dy || 1;
        st[i].offset = Math.max(0, Math.min(1, ((q.x - gm.a.x) * dx + (q.y - gm.a.y) * dy) / l2));
        writeStops(g, st);
      }
      ed.draw();
    }, () => { if (moved) ed.commit(t('Verlauf geändert')); else ed.draw(); });
  }

  /** Lage des Zeigers auf der Verlaufslinie (0 … 1) */
  project(el, g, e) {
    const q = pt(this.matrix(el, g).inverse(), this.ed.toDoc(e.clientX, e.clientY).x, this.ed.toDoc(e.clientX, e.clientY).y), gm = this.geom(g);
    const dx = gm.b.x - gm.a.x, dy = gm.b.y - gm.a.y, l2 = dx * dx + dy * dy || 1;
    return Math.max(0, Math.min(1, ((q.x - gm.a.x) * dx + (q.y - gm.a.y) * dy) / l2));
  }

  /** Gewählten Stopp löschen (mindestens zwei bleiben) */
  deleteStop() {
    const cur = this.current();
    if (!cur) return false;
    const g = this.own(cur.el, cur.g), st = gradientStops(this.ed.root, g);
    if (st.length <= 2) return false;
    st.splice(Math.min(this.stop, st.length - 1), 1);
    this.stop = 0;
    writeStops(g, st);
    this.ed.commit(t('Farbstopp entfernt'));
    return true;
  }

  /** Werkzeug: Klick wählt das Objekt; Ziehen auf einem Objekt ohne Verlauf legt einen linearen Verlauf an */
  stageDown(e, p0) {
    const ed = this.ed;
    const hit = ed.hitAt(e)?.el;
    if (hit && !ed.sel.includes(hit)) ed.select([hit]);
    if (!hit) { ed.select([]); return; }
    const el = hit;
    if (['g', 'switch', 'svg'].includes(el.localName)) return;
    ed.track(e, (p) => {
      if (Math.hypot(p.x - p0.x, p.y - p0.y) * ed.zoom < 6) return;
      let g = refOf(ed.root, getProp(el, this.which));
      if (!g || !['linearGradient', 'radialGradient'].includes(g.localName)) {
        const base = getProp(el, this.which);
        g = document.createElementNS(NS, 'linearGradient');
        g.id = uid(ed.root, 'verlauf-');
        const c = parseColor(base) ? toHex(parseColor(base)) : '#5B73AD';
        writeStops(g, [{ offset: 0, color: c, opacity: 1 }, { offset: 1, color: '#FFFFFF', opacity: 1 }]);
        defs(ed.root).append(g);
        setProp(el, this.which, `url(#${g.id})`);
      } else g = this.own(el, g);
      const Mi = this.matrix(el, g).inverse(), a = pt(Mi, p0.x, p0.y), b = pt(Mi, p.x, p.y);
      const d = (gattr(ed.root, g, 'gradientUnits') || 'objectBoundingBox') !== 'userSpaceOnUse' ? 4 : 3;
      if (g.localName === 'linearGradient') { g.setAttribute('x1', num(a.x, d)); g.setAttribute('y1', num(a.y, d)); g.setAttribute('x2', num(b.x, d)); g.setAttribute('y2', num(b.y, d)); }
      else { g.setAttribute('cx', num(a.x, d)); g.setAttribute('cy', num(a.y, d)); g.setAttribute('r', num(Math.hypot(b.x - a.x, b.y - a.y), d)); g.removeAttribute('fx'); g.removeAttribute('fy'); }
      ed.draw();
    }, (p) => { if (Math.hypot(p.x - p0.x, p.y - p0.y) * ed.zoom >= 6) ed.commit(t('Verlauf gezogen')); else ed.changed(false); });
  }
}

/** Farbe an Stelle tt zwischen den Stopps */
function mix(stops, tt) {
  const s = [...stops].sort((a, b) => a.offset - b.offset);
  let a = s[0], b = s[s.length - 1];
  for (let i = 0; i < s.length - 1; i++) if (tt >= s[i].offset && tt <= s[i + 1].offset) { a = s[i]; b = s[i + 1]; break; }
  const f = b.offset > a.offset ? (tt - a.offset) / (b.offset - a.offset) : 0;
  const ca = parseColor(a.color), cb = parseColor(b.color);
  const c = { r: ca.r + (cb.r - ca.r) * f, g: ca.g + (cb.g - ca.g) * f, b: ca.b + (cb.b - ca.b) * f, a: 1 };
  return { color: toHex(c), opacity: a.opacity + (b.opacity - a.opacity) * f };
}
