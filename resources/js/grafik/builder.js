/*
 * Grafik-Editor – Formgitter (Shape Builder, Ausbaustufe 3b) wie in Illustrator/Affinity: Formen auswählen, Werkzeug M.
 * Die Überlappungen der Auswahl werden in einzelne Bereiche zerlegt (Paper.js, auf den Bézierkurven). Zeiger darüber zeigt den
 * Bereich, Klick bzw. Ziehen über mehrere Bereiche vereint sie zu einer Form, ⌥ entfernt die überstrichenen Bereiche.
 * Ergebnis: alle Bereiche als Pfade an der Stelle der untersten Form, der vereinte mit dem Stil des ersten Bereichs –
 * ein Schritt im Protokoll. MIT
 */
import { t } from '../_i18n.js';
import { NS, parsePath, pathToString, transformPath } from './geom.js';
import { loadPaper, shapesOf, pathLike } from './bool.js';
import { getProp } from './doc.js';
import { toast } from '../_toast.js';

const CLOSED = ['path', 'rect', 'circle', 'ellipse', 'polygon', 'g'];

export class ShapeBuilder {
  constructor(ed) {
    this.ed = ed;
    this.pieces = null;   // [{ it, src, d }]
    this.key = '';
    this.hot = -1;
    this.mark = new Set();
    this.busy = false;
  }

  /** Bereiche der Auswahl (neu, wenn sich Auswahl oder Dokument geändert haben) */
  async prepare() {
    const ed = this.ed;
    const list = ed.ordered(ed.sel).filter(el => CLOSED.includes(el.localName));
    const key = ed.hi + ':' + list.map(el => ed.pathOf(el).join('.')).join('|');
    if (key === this.key && this.pieces) return this.pieces;
    this.key = key; this.pieces = null; this.hot = -1;
    if (list.length < 2) return null;
    const paper = await loadPaper(ed.boot.modules?.paper);
    let pieces = [], union = null;
    for (const el of list) {
      let it = null;
      for (const { d, src } of shapesOf(el, ed.root)) {
        const p = new paper.CompoundPath({ pathData: d, insert: false });
        if (/evenodd/i.test(getProp(src, 'fill-rule') || '')) p.fillRule = 'evenodd';
        it = it ? it.unite(p, { insert: false }) : p;
      }
      if (!it) continue;
      const next = [];
      for (const pc of pieces) {
        const a = pc.it.intersect(it, { insert: false }), b = pc.it.subtract(it, { insert: false });
        if (!empty(b)) next.push({ it: b, src: pc.src });
        if (!empty(a)) next.push({ it: a, src: el });
      }
      const own = union ? it.subtract(union, { insert: false }) : it;
      if (!empty(own)) next.push({ it: own, src: el });
      union = union ? union.unite(it, { insert: false }) : it;
      pieces = next;
    }
    // zerfallene Teilflächen (zwei getrennte Stücke derselben Überlappung) einzeln anbieten
    const flat = [];
    for (const pc of pieces) {
      const kids = pc.it.children && pc.it.children.length > 1 ? splitIslands(paper, pc.it) : [pc.it];
      for (const k of kids) if (!empty(k)) flat.push({ it: k, src: pc.src, d: k.pathData });
    }
    this.paper = paper;
    this.list = list;
    this.pieces = flat;
    return flat;
  }

  /** Index des Bereichs unter einem Dokumentpunkt */
  at(p) {
    if (!this.pieces) return -1;
    const pt = new this.paper.Point(p.x, p.y);
    return this.pieces.findIndex(pc => pc.it.contains(pt));
  }

  async hover(e) {
    if (this.busy || this.ed.drag) return;
    if (!(await this.prepare())) { if (this.hot !== -1) { this.hot = -1; this.ed.draw(); } return; }
    const i = this.at(this.ed.toDoc(e.clientX, e.clientY));
    if (i !== this.hot) { this.hot = i; this.ed.draw(); }
  }

  async down(e) {
    const ed = this.ed;
    if (!(await this.prepare())) {
      // ohne passende Auswahl: wie das Auswahl-Werkzeug wählen (⇧ = hinzufügen)
      const hit = ed.hitAt(e)?.el;
      if (hit) ed.select([hit], e.shiftKey); else if (!e.shiftKey) ed.select([]);
      if (ed.sel.length < 2) ed.hint(t('Formgitter: mindestens zwei sich überlappende Formen auswählen (⇧-Klick), dann Bereiche anklicken oder überstreichen.'));
      else { await this.prepare(); ed.hint(); ed.draw(); }
      return;
    }
    const remove = e.altKey;
    this.mark = new Set();
    const first = this.at(ed.toDoc(e.clientX, e.clientY));
    if (first < 0) { ed.select([]); this.pieces = null; ed.draw(); return; }
    this.mark.add(first);
    this.removing = remove;
    ed.draw();
    ed.track(e, p => { const i = this.at(p); if (i >= 0 && !this.mark.has(i)) { this.mark.add(i); ed.draw(); } }, () => this.apply(remove));
  }

  /** Vereinen bzw. Entfernen der markierten Bereiche – ein Schritt */
  apply(remove) {
    const ed = this.ed;
    const pieces = this.pieces, mark = [...this.mark].sort((a, b) => a - b);
    this.mark = new Set();
    if (!pieces || !mark.length) { ed.draw(); return; }
    if (!remove && mark.length === 1 && pieces.length === 1) { ed.draw(); return; }
    const bottom = this.list[0];
    let merged = null;
    for (const i of mark) merged = merged ? merged.unite(pieces[i].it, { insert: false }) : pieces[i].it;
    const out = [];
    pieces.forEach((pc, i) => {
      if (mark.includes(i)) {
        if (!remove && i === mark[0]) out.push(pathLike(pc.src, ed.root, merged.pathData, bottom));
      } else out.push(pathLike(pc.src, ed.root, pc.d, bottom));
    });
    // Ergebnis im Raum der untersten Form, gleiche Reihenfolge der Bereiche
    for (const p of out) bottom.before(p);
    for (const el of this.list) el.remove();
    const keep = out.filter(p => p.isConnected);
    ed.sel = keep;
    this.pieces = null; this.key = '';
    ed.commit(remove ? t('Formgitter: Bereiche entfernt') : t('Formgitter: Bereiche vereint'));
    if (!keep.length) toast(t('Alle Bereiche entfernt.'), 'info');
  }

  /** Überlagerung: Umrisse aller Bereiche, Bereich unter dem Zeiger, markierte Bereiche */
  overlay() {
    const ed = this.ed;
    if (!this.pieces || ed.tool !== 'builder') return '';
    const m = ed.root.getScreenCTM();
    if (!m) return '';
    const r = ed.stage.getBoundingClientRect();
    const M = new DOMMatrix().translate(-r.left, -r.top).multiply(DOMMatrix.fromMatrix(m));
    const dd = d => pathToString(transformPath(parsePath(d), M), 2);
    let s = '';
    this.pieces.forEach((pc, i) => {
      const on = this.mark.has(i), hot = i === this.hot;
      s += `<path class="ge-ov__piece${on ? (this.removing ? ' is-remove' : ' is-on') : hot ? ' is-hot' : ''}" d="${dd(pc.d)}"/>`;
    });
    return s;
  }

  reset() { this.pieces = null; this.key = ''; this.hot = -1; this.mark = new Set(); }
}

const empty = it => !it || !it.pathData || Math.abs(it.area || 0) < 1e-6;

/** Getrennte Inseln einer zusammengesetzten Fläche (Löcher bleiben bei ihrer Insel) */
function splitIslands(paper, cp) {
  const outer = [], holes = [];
  for (const c of cp.children) (c.clockwise === cp.children[0].clockwise ? outer : holes).push(c);
  if (outer.length < 2) return [cp];
  return outer.map(o => {
    const parts = [o.clone({ insert: false })];
    for (const h of holes) if (o.contains(h.interiorPoint || h.bounds.center)) parts.push(h.clone({ insert: false }));
    return new paper.CompoundPath({ children: parts, insert: false, fillRule: cp.fillRule });
  });
}

export { NS };
