/*
 * Grafik-Editor des Kerns (KLXM Studio) – Komponente. Zwei Hüllen nutzen sie: eigene Seite (/admin/grafik/editor) und Overlay
 * (CMSGraphicEditor.open, shell.js). Aufbau wie in Vektorprogrammen: Personas oben links (Vektor | Motion), Kontextleiste,
 * Werkzeugleiste links, Zeichenfläche, Bereiche mit Reitern rechts, Statuszeile unten.
 *
 * Modell: das SVG selbst (in einem Shadow Root – <style> der Grafik wirkt nur dort). Auswahl, Griffe, Hilfslinien liegen in
 * einer eigenen Ebene darüber (Bildschirmkoordinaten). Protokoll = Fassungen des SVG (Rückgängig/Wiederholen, Sprung).
 * Speichern geht immer über den Server (Core\Svg bereinigt); das SVG aus dem Browser gilt nie als vertrauenswürdig.
 *
 * Einbetten: const ed = mountEditor(el, boot, { onSave, onClose, shell }) → ed.getSvg(), ed.setSvg(svg), ed.on(name, fn),
 * ed.select(elements), ed.root (das SVG), ed.registerPersona({ id, label, icon, mount(ed, area), unmount() }). MIT
 */
import { t } from '../_i18n.js';
import { esc, csrf } from '../_html.js';
import { ico, spriteUrl } from '../_icons.js';
import { toast } from '../_toast.js';
import {
  NS, num, docMatrix, parentMatrix, pt, boundsOf, union, corners, docBounds, decompose,
  parsePath, pathToString, toNodes, fromNodes, shapeToPath, simplify, smoothNodes, polygonPoints,
} from './geom.js';
import {
  isDrawn, drawnChildren, nameOf, typeLabel, isLocked, isHidden, getProp, setProp, eachShape, snapshot, restore,
  applyTransform, defs, uid, getText, setText, remapIds, paintEl,
} from './doc.js';
import { parseColor, hex } from './color.js';
import { h, iconBtn, menu, closeMenus, modal, MOD, ALT, SHIFT, isMac } from './ui.js';
import { buildPanels } from './panels.js';

const TOOLS = [
  { id: 'select', icon: 'cursor', key: 'V', label: () => t('Verschieben') },
  { id: 'node', icon: 'vector-two', key: 'A', label: () => t('Knoten') },
  { id: 'pen', icon: 'pen-nib', key: 'P', label: () => t('Zeichenstift') },
  { id: 'pencil', icon: 'pencil-simple-line', key: 'N', label: () => t('Bleistift') },
  { id: 'rect', icon: 'rectangle', key: 'R', label: () => t('Rechteck') },
  { id: 'ellipse', icon: 'circle', key: 'E', label: () => t('Ellipse') },
  { id: 'polygon', icon: 'polygon', key: 'G', label: () => t('Vieleck und Stern') },
  { id: 'line', icon: 'line-segment', key: 'L', label: () => t('Linie und Pfeil') },
  { id: 'text', icon: 'text-t', key: 'T', label: () => t('Text') },
  { id: 'eyedropper', icon: 'eyedropper', key: 'I', label: () => t('Pipette') },
  { id: 'hand', icon: 'hand', key: 'H', label: () => t('Hand') },
  { id: 'zoom', icon: 'magnifying-glass-plus', key: 'Z', label: () => t('Zoom') },
];

const HINTS = {
  select: () => t('Klick = Objekt auswählen, Ziehen = verschieben bzw. Auswahlrahmen. ⇧ = hinzufügen, ⌥ beim Ziehen = Kopie. Doppelklick = in Gruppe bzw. Text bearbeiten.'),
  node: () => t('Klick auf eine Kurve zeigt ihre Knoten. Knoten oder Griffe ziehen, ⇧ = mehrere Knoten, ⌫ = Knoten löschen, Doppelklick auf Knoten = Ecke/glatt.'),
  pen: () => t('Klick = Eckpunkt, Ziehen = glatter Punkt mit Griffen. Klick auf den ersten Punkt schließt die Form, ↵ oder Esc beendet.'),
  pencil: () => t('Ziehen = frei zeichnen – die Linie wird automatisch geglättet (Stärke in der Kontextleiste).'),
  rect: () => t('Ziehen = Rechteck, ⇧ = Quadrat, ⌥ = von der Mitte aus. Eckenradius in der Kontextleiste.'),
  ellipse: () => t('Ziehen = Ellipse, ⇧ = Kreis, ⌥ = von der Mitte aus.'),
  polygon: () => t('Ziehen = Vieleck bzw. Stern (Ecken und Stern in der Kontextleiste), ⇧ = gleichmäßig.'),
  line: () => t('Ziehen = Linie, ⇧ = in 45°-Schritten. Pfeilspitzen in der Kontextleiste.'),
  text: () => t('Klick = Text an dieser Stelle. Esc oder Klick daneben beendet die Eingabe.'),
  eyedropper: () => t('Klick auf ein Objekt übernimmt seine Füllung und Kontur für die Auswahl.'),
  hand: () => t('Ziehen = Ansicht verschieben (auch mit gedrückter Leertaste in jedem Werkzeug).'),
  zoom: () => t('Klick = vergrößern, ⌥-Klick = verkleinern. ⌘0 = einpassen, ⌘1 = 100 %.'),
};

let seq = 0;

export class GraphicEditor {
  constructor(host, boot, opts = {}) {
    this.host = host;
    this.boot = boot;
    this.opts = opts;
    this.uid = 'ge' + (++seq);
    this.doc = { ...boot.doc };
    this.format = this.doc.format || 'info';
    this.tool = 'select';
    this.sel = [];
    this.scope = null;          // betretene Gruppe (Doppelklick), sonst Wurzel
    this.zoom = 1; this.panX = 0; this.panY = 0;
    this.history = []; this.hi = -1; this.savedAt = 0;
    this.listeners = {};
    this.personas = [{ id: 'vector', label: t('Vektor'), icon: 'bezier-curve' }, { id: 'motion', label: t('Motion'), icon: 'film-strip', soon: true }];
    this.persona = 'vector';
    this.style = this.format === 'icon'
      ? { fill: 'none', stroke: 'currentColor', strokeWidth: 2, target: 'stroke' }
      : { fill: '#5B73AD', stroke: 'none', strokeWidth: 2, target: 'fill' };
    this.opt = {
      radius: 0, sides: 6, star: false, inner: 0.5, arrowStart: false, arrowEnd: this.format !== 'icon', smooth: 0.6,
      fontSize: this.format === 'preview' ? 8 : this.format === 'icon' ? 6 : 32, fontFamily: (boot.fonts || [])[0] || 'sans-serif',
      fontWeight: '400', anchor: 'start', snap: true, grid: this.format === 'icon',
    };
    this.recent = [];
    this.nodeSel = new Set();   // ausgewählte Knoten (Direktauswahl): "s:i"
    this.build();
    this.load(this.doc.svg || '', t('Geöffnet'));
  }

  // ================================================================= Ereignisse für Einbettungen (Motion, Erweiterungen)
  on(name, fn) { (this.listeners[name] ||= []).push(fn); return () => { this.listeners[name] = this.listeners[name].filter(f => f !== fn); }; }
  emit(name, ...a) { for (const f of this.listeners[name] || []) { try { f(...a); } catch (e) { console.error('[CMSGraphicEditor] ' + name, e); } } }

  /** Weitere Persona (z. B. Motion): { id, label, icon, mount(editor, area), unmount() } */
  registerPersona(p) {
    const i = this.personas.findIndex(x => x.id === p.id);
    if (i >= 0) this.personas[i] = p; else this.personas.push(p);
    this.renderPersonas();
  }

  // ================================================================= Aufbau
  build() {
    const fmtLabel = (this.boot.formatLabels || {})[this.doc.kind === 'part' ? 'part' : this.format] || '';
    this.el = h(`<div class="ge" data-format="${esc(this.format)}" data-tool="select">
      <header class="ge-top">
        <div class="ge-personas" role="radiogroup" aria-label="${esc(t('Persona'))}"></div>
        <div class="ge-doctitle"><strong data-name></strong><span class="ge-fmt">${esc(fmtLabel)}</span><span class="ge-dirty" data-dirty hidden>${esc(t('Ungespeichert'))}</span></div>
        <div class="ge-top__grow"></div>
        <div class="ge-top__group">${iconBtn('arrow-u-up-left', t('Rückgängig'), 'data-act="undo"', MOD + 'Z')}${iconBtn('arrow-u-up-right', t('Wiederholen'), 'data-act="redo"', SHIFT + MOD + 'Z')}</div>
        <button type="button" class="ge-zoombtn" data-act="zoommenu" aria-haspopup="menu" aria-expanded="false" title="${esc(t('Zoom'))}"><span data-zoom>100 %</span>${ico('caret-down')}</button>
        <button type="button" class="adm-btn adm-btn--small" data-act="export" aria-haspopup="menu" aria-expanded="false">${ico('export')} ${esc(t('Exportieren'))}</button>
        <span data-shellbtns></span>
        <button type="button" class="adm-btn adm-btn--primary adm-btn--small" data-act="save" aria-keyshortcuts="${isMac ? 'Meta+S' : 'Control+S'}">${ico('floppy-disk')} ${esc(t('Speichern'))}</button>
      </header>
      <div class="ge-ctx" role="toolbar" aria-label="${esc(t('Kontextleiste'))}"></div>
      <div class="ge-tools" role="toolbar" aria-orientation="vertical" aria-label="${esc(t('Werkzeuge'))}"></div>
      <div class="ge-stage" tabindex="0" role="application" aria-roledescription="${esc(t('Zeichenfläche'))}" aria-label="${esc(t('Zeichenfläche'))}" aria-describedby="${this.uid}-hint">
        <div class="ge-paper"></div>
        <svg class="ge-ov" aria-hidden="true"></svg>
        <textarea class="ge-textedit is-bare" hidden aria-label="${esc(t('Text bearbeiten'))}" spellcheck="true"></textarea>
        <div class="ge-persona-area" hidden></div>
      </div>
      <aside class="ge-side" aria-label="${esc(t('Bereiche'))}"></aside>
      <footer class="ge-status"><span class="ge-status__hint" id="${this.uid}-hint" aria-live="polite"></span><span class="ge-status__pos" data-pos></span><span class="ge-status__size" data-size></span></footer>
    </div>`);
    this.host.append(this.el);
    this.$ = s => this.el.querySelector(s);
    this.stage = this.$('.ge-stage');
    this.paper = this.$('.ge-paper');
    this.ov = this.$('.ge-ov');
    this.shadow = this.paper.attachShadow({ mode: 'open' });
    this.shadow.innerHTML = '<style>:host{display:block}:host>svg{display:block;width:100%;height:100%;overflow:visible}</style>';
    this.renderPersonas();
    this.renderTools();
    this.panels = buildPanels(this);
    this.$('.ge-side').append(...this.panels.groups);
    this.bindTop();
    this.bindStage();
    this.bindKeys();
    this.hint();
    this.ro = new ResizeObserver(() => { if (!this.fitted) this.fit(); else this.layout(); });
    this.ro.observe(this.stage);
  }

  renderPersonas() {
    const box = this.$('.ge-personas');
    if (!box) return;
    box.innerHTML = this.personas.map(p => {
      const avail = !p.soon || !!p.mount;
      return `<button type="button" role="radio" aria-checked="${p.id === this.persona}" data-persona="${esc(p.id)}"${avail ? '' : ' aria-disabled="true"'} title="${esc(avail ? p.label : t('{name} – kommt mit der nächsten Ausbaustufe', { name: p.label }))}">${ico(p.icon || 'shapes')}<span>${esc(p.label)}</span></button>`;
    }).join('');
    box.querySelectorAll('[data-persona]').forEach(b => b.addEventListener('click', () => this.setPersona(b.dataset.persona)));
  }

  setPersona(id) {
    const p = this.personas.find(x => x.id === id);
    if (!p || id === this.persona) return;
    if (p.soon && !p.mount) { toast(t('Die Persona „{name}“ kommt mit der nächsten Ausbaustufe.', { name: p.label }), 'info'); return; }
    const old = this.personas.find(x => x.id === this.persona);
    old?.unmount?.(this);
    const area = this.$('.ge-persona-area');
    this.persona = id;
    this.el.dataset.persona = id;
    area.hidden = id === 'vector';
    area.innerHTML = '';
    if (id !== 'vector') p.mount?.(this, area);
    this.renderPersonas();
    this.emit('persona', id);
  }

  renderTools() {
    const nav = this.$('.ge-tools');
    nav.innerHTML = TOOLS.map(tl => `<button type="button" class="ge-tool" data-tool="${tl.id}" aria-pressed="${tl.id === this.tool}" aria-label="${esc(tl.label())}" title="${esc(tl.label())} (${tl.key})" aria-keyshortcuts="${tl.key}">${ico(tl.icon)}</button>`).join('')
      + `<div class="ge-tools__sep"></div>
      <div class="ge-fs" role="group" aria-label="${esc(t('Füllung und Kontur'))}">
        <button type="button" class="ge-fs__stroke" data-fs="stroke" aria-label="${esc(t('Kontur bearbeiten'))}" title="${esc(t('Kontur'))}"></button>
        <button type="button" class="ge-fs__fill" data-fs="fill" aria-label="${esc(t('Füllung bearbeiten'))}" title="${esc(t('Füllung'))}"></button>
        <button type="button" class="ge-fs__swap" data-act="swapfs" aria-label="${esc(t('Füllung und Kontur tauschen'))}" title="${esc(t('Füllung und Kontur tauschen'))} (${SHIFT}X)">${ico('swap')}</button>
      </div>`;
    nav.querySelectorAll('[data-tool]').forEach(b => b.addEventListener('click', () => this.setTool(b.dataset.tool)));
    nav.querySelectorAll('[data-fs]').forEach(b => b.addEventListener('click', () => { this.style.target = b.dataset.fs; this.paintFs(); this.panels.showColor(); }));
    nav.addEventListener('keydown', e => {
      const list = [...nav.querySelectorAll('button')], i = list.indexOf(document.activeElement);
      const d = e.key === 'ArrowDown' ? 1 : e.key === 'ArrowUp' ? -1 : 0;
      if (!d || i < 0) return;
      e.preventDefault(); e.stopPropagation(); list[(i + d + list.length) % list.length].focus();
    });
    this.paintFs();
  }

  /** Füll-/Konturfarbe in der Werkzeugleiste (der Auswahl bzw. der Vorgabe für neue Formen) */
  paintFs() {
    const f = this.$('.ge-fs__fill'), s = this.$('.ge-fs__stroke');
    if (!f) return;
    const el = paintEl(this.sel[0]);
    const fill = el ? getProp(el, 'fill') : this.style.fill, stroke = el ? getProp(el, 'stroke') : this.style.stroke;
    const paint = (b, v) => {
      b.classList.toggle('is-none', !v || v === 'none');
      b.classList.toggle('is-grad', /^url/.test(v || ''));
      b.style.setProperty('--c', /^url/.test(v || '') ? 'transparent' : (hex(v === 'currentColor' ? '#1F2430' : v) || 'transparent'));
    };
    paint(f, fill); paint(s, stroke);
    f.classList.toggle('is-active', this.style.target === 'fill');
    s.classList.toggle('is-active', this.style.target === 'stroke');
    f.setAttribute('aria-pressed', String(this.style.target === 'fill'));
    s.setAttribute('aria-pressed', String(this.style.target === 'stroke'));
  }

  setTool(id) {
    if (!TOOLS.some(x => x.id === id)) return;
    this.finishPen();
    this.endText();
    this.tool = id;
    this.el.dataset.tool = id;
    this.$('.ge-tools').querySelectorAll('[data-tool]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.tool === id)));
    if (id !== 'node') this.nodeSel.clear();
    this.hint();
    this.renderCtx();
    this.draw();
  }

  hint(text) { this.$('.ge-status__hint').textContent = text || HINTS[this.tool]?.() || ''; }

  // ================================================================= Dokument laden / ausgeben
  load(svg, label) {
    const parsed = new DOMParser().parseFromString(svg || '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500"/>', 'image/svg+xml');
    let root = parsed.documentElement;
    if (root.localName !== 'svg' || parsed.querySelector('parsererror')) {
      toast(t('Die Grafik konnte nicht gelesen werden.'), 'error');
      root = new DOMParser().parseFromString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500"/>', 'image/svg+xml').documentElement;
    }
    root = document.importNode(root, true);
    if (this.root) this.root.replaceWith(root); else this.shadow.append(root);
    this.root = root;
    this.scope = root;
    this.sel = [];
    if (label) { this.history = []; this.hi = -1; this.commit(label); this.savedAt = this.hi; }
    this.fitted = false;
    this.fit();
    this.changed(false);
  }

  get vb() {
    const v = this.root.viewBox?.baseVal;
    if (v && v.width) return { x: v.x, y: v.y, w: v.width, h: v.height };
    return { x: 0, y: 0, w: parseFloat(this.root.getAttribute('width')) || 800, h: parseFloat(this.root.getAttribute('height')) || 500 };
  }

  /** SVG als Text (wie gespeichert) – der Server bereinigt es noch einmal */
  getSvg() {
    const c = this.root.cloneNode(true);
    c.removeAttribute('style');
    if (!c.getAttribute('xmlns')) c.setAttribute('xmlns', NS);
    return new XMLSerializer().serializeToString(c);
  }

  setSvg(svg, label = t('Grafik ersetzt')) { this.load(svg); this.commit(label); }

  // ================================================================= Protokoll (Rückgängig / Wiederholen)
  commit(label) {
    const svg = this.getSvg();
    if (this.hi >= 0 && this.history[this.hi].svg === svg) return;
    this.history.length = this.hi + 1;
    this.history.push({ label, svg, sel: this.sel.map(el => this.pathOf(el)), at: Date.now() });
    // höchstens 200 Schritte bzw. ca. 40 MB
    let total = this.history.reduce((s, x) => s + x.svg.length, 0);
    while (this.history.length > 200 || (total > 40e6 && this.history.length > 2)) { total -= this.history.shift().svg.length; if (this.savedAt >= 0) this.savedAt--; }
    this.hi = this.history.length - 1;
    this.changed();
  }

  goto(i) {
    if (i < 0 || i >= this.history.length || i === this.hi) return;
    this.finishPen(); this.endText();
    this.hi = i;
    const st = this.history[i];
    const parsed = document.importNode(new DOMParser().parseFromString(st.svg, 'image/svg+xml').documentElement, true);
    this.root.replaceWith(parsed);
    this.root = parsed;
    this.scope = parsed;
    this.sel = st.sel.map(p => this.byPath(p)).filter(Boolean);
    this.layout();
    this.changed();
  }
  undo() { this.goto(this.hi - 1); }
  redo() { this.goto(this.hi + 1); }
  get dirty() { return this.hi !== this.savedAt; }

  pathOf(el) { const p = []; for (let n = el; n && n !== this.root; n = n.parentNode) p.unshift([...n.parentNode.children].indexOf(n)); return p; }
  byPath(p) { let n = this.root; for (const i of p) { n = n?.children[i]; } return n && n !== this.root ? n : null; }

  /** Nach jeder Änderung: Bereiche, Überlagerung, Titel */
  changed(emit = true) {
    this.sel = this.sel.filter(el => el.isConnected && this.root.contains(el));
    if (this.scope !== this.root && !this.scope.isConnected) this.scope = this.root;
    const nm = this.doc.name || this.doc.title || t('Neue Grafik');
    this.$('[data-name]').textContent = nm;
    this.$('[data-dirty]').hidden = !this.dirty;
    this.el.querySelector('[data-act=undo]').disabled = this.hi <= 0;
    this.el.querySelector('[data-act=redo]').disabled = this.hi >= this.history.length - 1;
    const vb = this.vb;
    this.$('[data-size]').textContent = `${num(vb.w, 2)} × ${num(vb.h, 2)}`;
    this.renderCtx();
    this.paintFs();
    this.panels?.refresh();
    this.draw();
    if (emit) this.emit('change', this);
  }

  // ================================================================= Ansicht: Zoom, Verschieben
  fit() {
    const r = this.stage.getBoundingClientRect();
    if (!r.width || !r.height) return;
    const vb = this.vb, pad = this.format === 'info' ? 60 : 80;
    this.zoom = Math.max(0.02, Math.min((r.width - pad * 2) / vb.w, (r.height - pad * 2) / vb.h, this.format === 'icon' ? 24 : 8));
    this.panX = (r.width - vb.w * this.zoom) / 2;
    this.panY = (r.height - vb.h * this.zoom) / 2;
    this.fitted = true;
    this.layout();
  }

  setZoom(z, cx, cy) {
    const r = this.stage.getBoundingClientRect();
    cx ??= r.width / 2; cy ??= r.height / 2;
    z = Math.max(0.02, Math.min(64, z));
    this.panX = cx - (cx - this.panX) * z / this.zoom;
    this.panY = cy - (cy - this.panY) * z / this.zoom;
    this.zoom = z;
    this.layout();
  }

  layout() {
    const vb = this.vb;
    Object.assign(this.paper.style, { left: this.panX + 'px', top: this.panY + 'px', width: vb.w * this.zoom + 'px', height: vb.h * this.zoom + 'px' });
    this.$('[data-zoom]').textContent = Math.round(this.zoom * 100) + ' %';
    this.draw();
  }

  /** Bildschirm (clientX/Y) → Dokument */
  toDoc(x, y) {
    const m = this.root.getScreenCTM();
    return m ? pt(DOMMatrix.fromMatrix(m).inverse(), x, y) : { x: 0, y: 0 };
  }
  /** Dokument → Überlagerung (Pixel in der Zeichenfläche) */
  toOv(p) {
    const m = this.root.getScreenCTM(), r = this.stage.getBoundingClientRect();
    const q = pt(DOMMatrix.fromMatrix(m), p.x, p.y);
    return { x: q.x - r.left, y: q.y - r.top };
  }

  // ================================================================= Auswahl
  select(list, add = false) {
    list = (Array.isArray(list) ? list : [list]).filter(Boolean);
    this.sel = add ? [...new Set([...this.sel, ...list])] : list;
    this.nodeSel.clear();
    if (this.sel[0] && this.scope !== this.root && !this.scope.contains(this.sel[0])) this.scope = this.root;
    this.changed(false);
    this.emit('selection', this.sel);
  }

  /** Auswählbares Element unter einem Treffer: Kind der betretenen Gruppe */
  pickable(target) {
    let n = target;
    if (!n || !this.root.contains(n) || n === this.root) return null;
    if (n.closest?.('defs,clipPath,mask,pattern,marker,symbol')) return null;
    const scope = this.scope.contains(n) ? this.scope : this.root;
    while (n && n.parentNode !== scope) n = n.parentNode;
    if (!n || !isDrawn(n) || isLocked(n) || isHidden(n)) return null;
    return n;
  }

  hitAt(e) {
    const list = this.shadow.elementsFromPoint ? this.shadow.elementsFromPoint(e.clientX, e.clientY) : [e.composedPath()[0]];
    for (const el of list) { const p = this.pickable(el); if (p) return { el: p, raw: el }; }
    return null;
  }

  /** Rahmen der Auswahl: Matrix F (Rahmen → Dokument) und Rechteck b im Rahmen. Ein Element: gedreht wie das Element. */
  frame(list = this.sel) {
    if (!list.length) return null;
    if (list.length === 1) {
      const el = list[0];
      let b; try { b = el.getBBox(); } catch { return null; }
      return { F: docMatrix(el, this.root), b: { x: b.x, y: b.y, w: b.width, h: b.height } };
    }
    let u = null;
    for (const el of list) u = union(u, docBounds(el, this.root));
    return u ? { F: new DOMMatrix(), b: u } : null;
  }

  selBounds(list = this.sel) {
    let u = null;
    for (const el of list) u = union(u, docBounds(el, this.root));
    return u;
  }

  // ================================================================= Zeichnen der Überlagerung
  draw() {
    if (!this.root || !this.ov) return;
    const ov = this.ov, out = [];
    const r = this.stage.getBoundingClientRect();
    ov.setAttribute('viewBox', `0 0 ${r.width} ${r.height}`);
    // Pixelraster (Symbole, ab 600 %)
    if (this.opt.grid && this.zoom >= 6) {
      const vb = this.vb, a = this.toOv({ x: vb.x, y: vb.y }), b = this.toOv({ x: vb.x + vb.w, y: vb.y + vb.h });
      let d = '';
      for (let x = Math.ceil(vb.x); x <= vb.x + vb.w; x++) { const p = this.toOv({ x, y: vb.y }); d += `M${p.x} ${a.y}V${b.y}`; }
      for (let y = Math.ceil(vb.y); y <= vb.y + vb.h; y++) { const p = this.toOv({ x: vb.x, y }); d += `M${a.x} ${p.y}H${b.x}`; }
      out.push(`<path class="ge-ov__grid" d="${d}"/>`);
    }
    // Rahmen der betretenen Gruppe
    if (this.scope !== this.root) {
      const c = corners(this.scope, this.root);
      if (c) out.push(`<polygon class="ge-ov__scope" points="${c.map(p => { const q = this.toOv(p); return q.x + ',' + q.y; }).join(' ')}"/>`);
    }
    if (this.hover && !this.sel.includes(this.hover) && this.hover.isConnected) {
      const c = corners(this.hover, this.root);
      if (c) out.push(`<polygon class="ge-ov__hover" points="${c.map(p => { const q = this.toOv(p); return q.x + ',' + q.y; }).join(' ')}"/>`);
    }
    if (this.tool === 'node') out.push(this.drawNodes());
    else if (this.sel.length && !this.textEl) out.push(this.drawSelection());
    if (this.guides?.length) for (const g of this.guides) {
      const a = this.toOv(g.a), b = this.toOv(g.b);
      out.push(`<line class="ge-ov__guide" x1="${a.x}" y1="${a.y}" x2="${b.x}" y2="${b.y}"/>`);
    }
    if (this.marquee) {
      const m = this.marquee;
      out.push(`<rect class="ge-ov__marquee" x="${Math.min(m.x0, m.x1)}" y="${Math.min(m.y0, m.y1)}" width="${Math.abs(m.x1 - m.x0)}" height="${Math.abs(m.y1 - m.y0)}"/>`);
    }
    if (this.pen) out.push(this.drawPen());
    ov.innerHTML = out.join('');
    for (const el of ov.querySelectorAll('[data-h]')) el.addEventListener('pointerdown', e => this.handleDown(e, el.dataset.h));
    for (const el of ov.querySelectorAll('[data-node]')) el.addEventListener('pointerdown', e => this.nodeDown(e, el.dataset.node));
    for (const el of ov.querySelectorAll('[data-node]')) el.addEventListener('dblclick', e => this.nodeToggle(e, el.dataset.node));
  }

  drawSelection() {
    const f = this.frame();
    if (!f) return '';
    const { F, b } = f;
    const P = (x, y) => this.toOv(pt(F, x, y));
    const c = [P(b.x, b.y), P(b.x + b.w, b.y), P(b.x + b.w, b.y + b.h), P(b.x, b.y + b.h)];
    let s = `<polygon class="ge-ov__sel" points="${c.map(p => p.x + ',' + p.y).join(' ')}"/>`;
    if (this.sel.length > 1) for (const el of this.sel) {
      const cc = corners(el, this.root);
      if (cc) s += `<polygon class="ge-ov__sub" points="${cc.map(p => { const q = this.toOv(p); return q.x + ',' + q.y; }).join(' ')}"/>`;
    }
    if (this.sel.some(isLocked)) return s;
    const H = { nw: [0, 0], n: [.5, 0], ne: [1, 0], e: [1, .5], se: [1, 1], s: [.5, 1], sw: [0, 1], w: [0, .5] };
    const small = Math.abs(c[1].x - c[0].x) + Math.abs(c[1].y - c[0].y) < 24;
    for (const [k, [u, v]] of Object.entries(H)) {
      if (small && k.length === 1) continue;
      const p = P(b.x + b.w * u, b.y + b.h * v);
      s += `<rect class="ge-ov__h" data-h="${k}" x="${p.x - 5}" y="${p.y - 5}" width="10" height="10" rx="2"/>`;
    }
    // Drehgriff oberhalb der Mitte
    const top = P(b.x + b.w / 2, b.y), ctr = P(b.x + b.w / 2, b.y + b.h / 2);
    const len = Math.hypot(top.x - ctr.x, top.y - ctr.y) || 1, rx = top.x + (top.x - ctr.x) / len * 26, ry = top.y + (top.y - ctr.y) / len * 26;
    s += `<line class="ge-ov__rotline" x1="${top.x}" y1="${top.y}" x2="${rx}" y2="${ry}"/><circle class="ge-ov__h ge-ov__rot" data-h="rot" cx="${rx}" cy="${ry}" r="6"/>`;
    return s;
  }

  // ================================================================= Zeichenfläche: Zeiger
  bindStage() {
    const st = this.stage;
    st.addEventListener('pointerdown', e => this.down(e));
    st.addEventListener('pointermove', e => {
      const p = this.toDoc(e.clientX, e.clientY);
      this.$('[data-pos]').textContent = `X ${num(p.x, 1)}  Y ${num(p.y, 1)}`;
      if (this.drag) return;
      if (this.tool === 'select' || this.tool === 'node') {
        const hit = this.hitAt(e)?.el || null;
        if (hit !== this.hover) { this.hover = hit; this.draw(); }
      }
      if (this.pen) { this.pen.cursor = this.snapPoint(p); this.draw(); }
    });
    st.addEventListener('pointerleave', () => { if (this.hover) { this.hover = null; this.draw(); } });
    st.addEventListener('dblclick', e => this.dbl(e));
    st.addEventListener('wheel', e => {
      e.preventDefault();
      const r = st.getBoundingClientRect();
      if (e.ctrlKey || e.metaKey) this.setZoom(this.zoom * Math.exp(-e.deltaY * 0.01), e.clientX - r.left, e.clientY - r.top);
      else { this.panX -= e.deltaX; this.panY -= e.deltaY; this.layout(); }
    }, { passive: false });
    // Bausteine aus der Bibliothek hereinziehen
    st.addEventListener('dragover', e => { if ([...e.dataTransfer.types].includes('application/x-ge-item')) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; } });
    st.addEventListener('drop', e => {
      const v = e.dataTransfer.getData('application/x-ge-item');
      if (!v) return;
      e.preventDefault();
      this.panels.insertItem(JSON.parse(v), this.toDoc(e.clientX, e.clientY));
    });
  }

  /** Zieh-Vorgang: move(p, e) bei jeder Bewegung, up(p, e) am Ende */
  track(e, move, up) {
    const st = this.stage;
    st.setPointerCapture?.(e.pointerId);
    this.drag = true;
    const mv = ev => move(this.toDoc(ev.clientX, ev.clientY), ev);
    const fin = ev => {
      st.removeEventListener('pointermove', mv); st.removeEventListener('pointerup', fin); st.removeEventListener('pointercancel', fin);
      this.drag = false;
      up?.(this.toDoc(ev.clientX, ev.clientY), ev);
    };
    st.addEventListener('pointermove', mv); st.addEventListener('pointerup', fin); st.addEventListener('pointercancel', fin);
  }

  down(e) {
    if (e.button === 1 || this.space || this.tool === 'hand') return this.panDrag(e);
    if (e.button !== 0) return;
    if (e.target.closest?.('.ge-textedit')) return;
    if (this.textEl) { this.endText(); if (this.tool === 'text') return; }
    this.stage.focus({ preventScroll: true });
    const p = this.toDoc(e.clientX, e.clientY);
    switch (this.tool) {
      case 'select': return this.selectDown(e, p);
      case 'node': return this.nodeStageDown(e, p);
      case 'pen': return this.penDown(e, p);
      case 'pencil': return this.pencilDown(e, p);
      case 'rect': case 'ellipse': case 'polygon': case 'line': return this.shapeDown(e, p);
      case 'text': return this.textDown(e, p);
      case 'eyedropper': return this.eyedrop(e);
      case 'zoom': { const r = this.stage.getBoundingClientRect(); return this.setZoom(this.zoom * (e.altKey ? 0.5 : 2), e.clientX - r.left, e.clientY - r.top); }
    }
  }

  panDrag(e) {
    e.preventDefault();
    const x0 = e.clientX, y0 = e.clientY, px = this.panX, py = this.panY;
    this.el.classList.add('is-panning');
    this.stage.setPointerCapture?.(e.pointerId);
    const mv = ev => { this.panX = px + ev.clientX - x0; this.panY = py + ev.clientY - y0; this.layout(); };
    const up = () => { this.stage.removeEventListener('pointermove', mv); this.stage.removeEventListener('pointerup', up); this.el.classList.remove('is-panning'); };
    this.stage.addEventListener('pointermove', mv); this.stage.addEventListener('pointerup', up);
  }

  // ---------------------------------------------------------------- Verschieben-Werkzeug
  selectDown(e, p) {
    const hit = this.hitAt(e)?.el || null;
    if (!hit) {
      if (!e.shiftKey) this.select([]);
      if (this.scope !== this.root && !e.shiftKey) { this.scope = this.root; this.draw(); }
      const r = this.stage.getBoundingClientRect(), x0 = e.clientX - r.left, y0 = e.clientY - r.top, base = e.shiftKey ? [...this.sel] : [];
      this.marquee = { x0, y0, x1: x0, y1: y0 };
      this.track(e, (q, ev) => {
        this.marquee.x1 = ev.clientX - r.left; this.marquee.y1 = ev.clientY - r.top;
        const a = this.toDoc(Math.min(e.clientX, ev.clientX), Math.min(e.clientY, ev.clientY)), b = this.toDoc(Math.max(e.clientX, ev.clientX), Math.max(e.clientY, ev.clientY));
        const inside = drawnChildren(this.scope).filter(el => {
          if (isLocked(el) || isHidden(el)) return false;
          const bb = docBounds(el, this.root);
          return bb && bb.x < b.x && bb.x + bb.w > a.x && bb.y < b.y && bb.y + bb.h > a.y;
        });
        this.sel = [...new Set([...base, ...inside])];
        this.draw();
      }, () => { this.marquee = null; this.select(this.sel); });
      return;
    }
    if (e.shiftKey) {
      this.select(this.sel.includes(hit) ? this.sel.filter(x => x !== hit) : [...this.sel, hit]);
      return;
    }
    if (!this.sel.includes(hit)) this.select([hit]);
    this.moveDrag(e, p);
  }

  moveDrag(e, p0) {
    if (this.sel.some(isLocked)) return;
    let list = this.sel, copied = false;
    let snaps = list.map(snapshot);
    const b0 = this.selBounds(list);
    const cand = this.snapTargets(list);
    let moved = false;
    this.track(e, (p, ev) => {
      let dx = p.x - p0.x, dy = p.y - p0.y;
      if (!moved && Math.hypot(dx, dy) * this.zoom < 3) return;
      if (!moved && ev.altKey && !copied) {
        // ⌥ beim Ziehen: Kopie ziehen, Original bleibt
        const clones = list.map(el => { const c = el.cloneNode(true); el.after(c); this.freshIds(c); return c; });
        list.forEach((el, i) => restore(el, snaps[i]));
        list = clones; snaps = clones.map(snapshot); this.sel = clones; copied = true;
      }
      moved = true;
      if (ev.shiftKey) { if (Math.abs(dx) > Math.abs(dy)) dy = 0; else dx = 0; }
      if (b0 && this.opt.snap && !ev.metaKey && !ev.ctrlKey) [dx, dy] = this.snapMove(b0, dx, dy, cand);
      const T = new DOMMatrix().translate(dx, dy);
      list.forEach((el, i) => { restore(el, snaps[i]); applyTransform(el, T, this.root); });
      this.draw();
    }, () => {
      this.guides = null;
      if (moved) this.commit(copied ? t('Kopie verschoben') : t('Verschoben'));
      else this.draw();
    });
  }

  /** Griffe: Größe (8) und Drehen */
  handleDown(e, k) {
    e.stopPropagation(); e.preventDefault();
    const f = this.frame();
    if (!f) return;
    const { F, b } = f, Fi = F.inverse();
    const snaps = this.sel.map(snapshot);
    const ctr = pt(F, b.x + b.w / 2, b.y + b.h / 2);
    if (k === 'rot') {
      const a0 = Math.atan2(this.toDoc(e.clientX, e.clientY).y - ctr.y, this.toDoc(e.clientX, e.clientY).x - ctr.x);
      const r0 = decompose(F).rot;
      this.track(e, (p, ev) => {
        let a = (Math.atan2(p.y - ctr.y, p.x - ctr.x) - a0) * 180 / Math.PI;
        if (ev.shiftKey) a = Math.round((r0 + a) / 15) * 15 - r0;
        const T = new DOMMatrix().translate(ctr.x, ctr.y).rotate(a).translate(-ctr.x, -ctr.y);
        this.sel.forEach((el, i) => { restore(el, snaps[i]); applyTransform(el, T, this.root); });
        this.hint(t('Drehung: {a}°', { a: num(((r0 + a) % 360 + 360) % 360, 1) }));
        this.draw();
      }, () => { this.hint(); this.commit(t('Gedreht')); });
      return;
    }
    const u = k.includes('w') ? 0 : k.includes('e') ? 1 : .5, v = k.includes('n') ? 0 : k.includes('s') ? 1 : .5;
    const hx = b.x + b.w * u, hy = b.y + b.h * v;
    const lock = this.panels.aspectLocked();
    this.track(e, (p, ev) => {
      const q = pt(Fi, p.x, p.y);
      const ax = ev.altKey ? b.x + b.w / 2 : b.x + b.w * (1 - u), ay = ev.altKey ? b.y + b.h / 2 : b.y + b.h * (1 - v);
      let sx = u === .5 ? 1 : (hx - ax) ? (q.x - ax) / (hx - ax) : 1;
      let sy = v === .5 ? 1 : (hy - ay) ? (q.y - ay) / (hy - ay) : 1;
      if (ev.shiftKey || lock) {
        if (u === .5) sx = sy; else if (v === .5) sy = sx;
        else { const s = Math.max(Math.abs(sx), Math.abs(sy)); sx = Math.sign(sx || 1) * s; sy = Math.sign(sy || 1) * s; }
      }
      if (!Number.isFinite(sx) || Math.abs(sx) < 1e-4) sx = 1e-4;
      if (!Number.isFinite(sy) || Math.abs(sy) < 1e-4) sy = 1e-4;
      const T = F.multiply(new DOMMatrix().translate(ax, ay).scale(sx, sy).translate(-ax, -ay)).multiply(Fi);
      this.sel.forEach((el, i) => { restore(el, snaps[i]); applyTransform(el, T, this.root); });
      this.hint(`B ${num(Math.abs(b.w * sx), 1)}  H ${num(Math.abs(b.h * sy), 1)}`);
      this.draw();
    }, () => { this.hint(); this.commit(t('Größe geändert')); });
  }

  dbl(e) {
    if (this.tool !== 'select') return;
    const hit = this.hitAt(e);
    if (!hit) return;
    if (['g', 'switch'].includes(hit.el.localName)) {
      // Gruppe betreten (wie in Vektorprogrammen): danach wählt ein Klick die Teile
      this.scope = hit.el;
      const inner = this.pickable(hit.raw);
      this.select(inner ? [inner] : []);
      this.hint(t('In der Gruppe – Esc oder Klick daneben verlässt sie.'));
      return;
    }
    if (hit.el.localName === 'text') return this.beginText(hit.el);
    if (['path', 'rect', 'ellipse', 'circle', 'polygon', 'polyline', 'line'].includes(hit.el.localName)) { this.select([hit.el]); this.setTool('node'); }
  }

  // ---------------------------------------------------------------- Einrasten, Hilfslinien
  snapTargets(exclude) {
    const xs = [], ys = [];
    const vb = this.vb;
    xs.push(vb.x, vb.x + vb.w / 2, vb.x + vb.w); ys.push(vb.y, vb.y + vb.h / 2, vb.y + vb.h);
    const ex = new Set(exclude);
    const pool = drawnChildren(this.scope).filter(el => !ex.has(el) && !isHidden(el)).slice(0, 400);
    for (const el of pool) {
      const b = docBounds(el, this.root);
      if (!b) continue;
      xs.push(b.x, b.x + b.w / 2, b.x + b.w); ys.push(b.y, b.y + b.h / 2, b.y + b.h);
    }
    return { xs, ys };
  }

  snapMove(b0, dx, dy, cand) {
    const tol = 6 / this.zoom;
    const best = (vals, list) => {
      let r = null;
      for (const v of vals) for (const c of list) { const d = c - v; if (Math.abs(d) <= tol && (!r || Math.abs(d) < Math.abs(r.d))) r = { d, at: c }; }
      return r;
    };
    if (this.opt.grid) { dx = Math.round(b0.x + dx) - b0.x; dy = Math.round(b0.y + dy) - b0.y; }
    const x = b0.x + dx, y = b0.y + dy;
    const sx = best([x, x + b0.w / 2, x + b0.w], cand.xs), sy = best([y, y + b0.h / 2, y + b0.h], cand.ys);
    this.guides = [];
    const vb = this.vb;
    if (sx) { dx += sx.d; this.guides.push({ a: { x: sx.at, y: vb.y - 1e4 }, b: { x: sx.at, y: vb.y + 1e4 } }); }
    if (sy) { dy += sy.d; this.guides.push({ a: { x: vb.x - 1e4, y: sy.at }, b: { x: vb.x + 1e4, y: sy.at } }); }
    return [dx, dy];
  }

  snapPoint(p, from = null, angle = false) {
    let q = { ...p };
    if (angle && from) {
      const a = Math.round(Math.atan2(p.y - from.y, p.x - from.x) / (Math.PI / 4)) * Math.PI / 4, l = Math.hypot(p.x - from.x, p.y - from.y);
      q = { x: from.x + Math.cos(a) * l, y: from.y + Math.sin(a) * l };
    }
    if (this.opt.grid) { q.x = Math.round(q.x * 2) / 2; q.y = Math.round(q.y * 2) / 2; }
    else if (this.opt.snap) {
      const tol = 6 / this.zoom, c = this.snapCache ||= this.snapTargets([]);
      for (const x of c.xs) if (Math.abs(x - q.x) <= tol) { q.x = x; break; }
      for (const y of c.ys) if (Math.abs(y - q.y) <= tol) { q.y = y; break; }
    }
    return q;
  }

  // ---------------------------------------------------------------- Neue Elemente
  /** Ziel für neue Elemente: betretene Gruppe, sonst Wurzel – Punkte im Raum des Ziels */
  get target() { return this.scope && this.scope.isConnected ? this.scope : this.root; }
  local(p) { const m = docMatrix(this.target, this.root); return this.target === this.root ? p : pt(m.inverse(), p.x, p.y); }

  create(tag, attrs, paint = true) {
    const el = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs)) if (v !== null && v !== undefined) el.setAttribute(k, v);
    if (paint) {
      const s = this.style;
      if (s.fill !== undefined && tag !== 'line' && tag !== 'polyline') el.setAttribute('fill', s.fill);
      if (tag === 'line' || tag === 'polyline') el.setAttribute('fill', 'none');
      if (s.stroke && s.stroke !== 'none') { el.setAttribute('stroke', s.stroke); el.setAttribute('stroke-width', num(s.strokeWidth)); }
      else if (tag === 'line' || tag === 'path' && attrs['data-open']) { el.setAttribute('stroke', s.fill !== 'none' ? s.fill : '#1F2430'); el.setAttribute('stroke-width', num(s.strokeWidth)); }
      if (this.format === 'icon') { for (const a of ['fill', 'stroke', 'stroke-width']) if (el.getAttribute(a) === this.root.getAttribute(a)) el.removeAttribute(a); }
    }
    el.removeAttribute('data-open');
    this.target.append(el);
    return el;
  }

  shapeDown(e, p0) {
    p0 = this.snapPoint(p0);
    const tool = this.tool;
    let el = null;
    this.snapCache = null;
    this.track(e, (p, ev) => {
      p = this.snapPoint(p, p0, tool === 'line' && ev.shiftKey);
      let x0 = p0.x, y0 = p0.y, w = p.x - p0.x, hh = p.y - p0.y;
      if (tool !== 'line') {
        if (ev.shiftKey) { const s = Math.max(Math.abs(w), Math.abs(hh)); w = Math.sign(w || 1) * s; hh = Math.sign(hh || 1) * s; }
        if (ev.altKey) { x0 -= w; y0 -= hh; w *= 2; hh *= 2; }
      }
      const a = this.local({ x: Math.min(x0, x0 + w), y: Math.min(y0, y0 + hh) }), b = this.local({ x: Math.max(x0, x0 + w), y: Math.max(y0, y0 + hh) });
      const box = { x: a.x, y: a.y, w: b.x - a.x, h: b.y - a.y };
      if (!el) {
        if (Math.hypot(w, hh) * this.zoom < 3) return;
        el = tool === 'rect' ? this.create('rect', {}) : tool === 'ellipse' ? this.create('ellipse', {}) : tool === 'polygon' ? this.create('polygon', {}) : this.create('line', {});
      }
      if (tool === 'rect') {
        Object.entries({ x: box.x, y: box.y, width: box.w, height: box.h }).forEach(([k, v]) => el.setAttribute(k, num(v)));
        if (this.opt.radius > 0) el.setAttribute('rx', num(Math.min(this.opt.radius, box.w / 2, box.h / 2)));
      } else if (tool === 'ellipse') {
        Object.entries({ cx: box.x + box.w / 2, cy: box.y + box.h / 2, rx: box.w / 2, ry: box.h / 2 }).forEach(([k, v]) => el.setAttribute(k, num(v)));
      } else if (tool === 'polygon') {
        el.setAttribute('points', polygonPoints(box, this.opt.sides, this.opt.star, this.opt.inner));
      } else {
        const s = this.local({ x: x0, y: y0 }), q = this.local(p);
        Object.entries({ x1: s.x, y1: s.y, x2: q.x, y2: q.y }).forEach(([k, v]) => el.setAttribute(k, num(v)));
      }
      this.draw();
    }, () => {
      if (!el) return;
      if (tool === 'line') this.applyArrows(el);
      this.sel = [el];
      this.commit({ rect: t('Rechteck'), ellipse: t('Ellipse'), polygon: t('Vieleck'), line: t('Linie') }[tool]);
    });
  }

  /** Pfeilspitzen (marker) nach den Vorgaben der Kontextleiste – Farbe wie die Kontur */
  applyArrows(el, start = this.opt.arrowStart, end = this.opt.arrowEnd) {
    for (const [attr, on] of [['marker-start', start], ['marker-end', end]]) {
      if (!on) { setProp(el, attr, null); continue; }
      const color = hex(getProp(el, 'stroke')) || '#1F2430';
      const d = defs(this.root);
      // vorhandene Pfeilspitze in derselben Farbe wiederverwenden (id pfeil-…, Pfad mit dieser Füllung)
      let m = [...d.querySelectorAll('marker[id^="pfeil-"]')].find(x => (x.querySelector('path')?.getAttribute('fill') || '').toUpperCase() === color);
      if (!m) {
        m = document.createElementNS(NS, 'marker');
        const id = uid(this.root, 'pfeil-');
        Object.entries({ id, viewBox: '0 0 10 10', refX: '7', refY: '5', markerWidth: '5', markerHeight: '5', orient: 'auto-start-reverse' }).forEach(([k, v]) => m.setAttribute(k, v));
        const p = document.createElementNS(NS, 'path');
        p.setAttribute('d', 'M0 0L10 5L0 10z'); p.setAttribute('fill', color);
        m.append(p);
        d.append(m);
      }
      setProp(el, attr, `url(#${m.id})`);
    }
  }

  // ---------------------------------------------------------------- Zeichenstift
  penDown(e, p) {
    p = this.snapPoint(p);
    if (!this.pen) {
      this.snapCache = null;
      this.pen = { nodes: [], cursor: p };
    }
    const pen = this.pen, first = pen.nodes[0];
    if (first && pen.nodes.length > 1 && Math.hypot(first.x - p.x, first.y - p.y) * this.zoom < 8) {
      pen.closed = true;
      return this.finishPen();
    }
    const node = { x: p.x, y: p.y, i: null, o: null };
    pen.nodes.push(node);
    this.track(e, (q) => {
      if (Math.hypot(q.x - node.x, q.y - node.y) * this.zoom < 3) { node.i = node.o = null; }
      else { node.o = { x: q.x, y: q.y }; node.i = { x: 2 * node.x - q.x, y: 2 * node.y - q.y }; }
      pen.cursor = q; this.draw();
    }, () => this.draw());
    this.draw();
  }

  drawPen() {
    const pen = this.pen;
    if (!pen.nodes.length) return '';
    const S = p => this.toOv(p);
    const nodes = pen.nodes.map(n => ({ ...S(n), i: n.i && S(n.i), o: n.o && S(n.o) }));
    let d = pathToString(fromNodes([{ closed: false, nodes }]), 2);
    const last = pen.nodes[pen.nodes.length - 1];
    if (pen.cursor && !this.drag) {
      const c = S(pen.cursor), lo = last.o ? S(last.o) : S(last);
      d += `M${S(last).x} ${S(last).y}C${lo.x} ${lo.y} ${c.x} ${c.y} ${c.x} ${c.y}`;
    }
    let s = `<path class="ge-ov__pen" d="${d}"/>`;
    nodes.forEach((n, i) => {
      if (n.i) s += `<line class="ge-ov__hl" x1="${n.x}" y1="${n.y}" x2="${n.i.x}" y2="${n.i.y}"/><circle class="ge-ov__hp" cx="${n.i.x}" cy="${n.i.y}" r="3"/>`;
      if (n.o) s += `<line class="ge-ov__hl" x1="${n.x}" y1="${n.y}" x2="${n.o.x}" y2="${n.o.y}"/><circle class="ge-ov__hp" cx="${n.o.x}" cy="${n.o.y}" r="3"/>`;
      s += `<rect class="ge-ov__node${i === 0 ? ' is-first' : ''}" x="${n.x - 4}" y="${n.y - 4}" width="8" height="8"/>`;
    });
    return s;
  }

  finishPen() {
    const pen = this.pen;
    if (!pen) return;
    this.pen = null;
    if (pen.nodes.length < 2) { this.draw(); return; }
    const nodes = pen.nodes.map(n => { const q = this.local(n); return { x: q.x, y: q.y, i: n.i && this.local(n.i), o: n.o && this.local(n.o) }; });
    const d = pathToString(fromNodes([{ closed: !!pen.closed, nodes }]));
    const el = this.create('path', { d, 'data-open': pen.closed ? null : '1' });
    if (!pen.closed && this.format !== 'icon') { el.setAttribute('fill', 'none'); if (!el.getAttribute('stroke')) { el.setAttribute('stroke', this.style.fill !== 'none' ? this.style.fill : '#1F2430'); el.setAttribute('stroke-width', num(this.style.strokeWidth)); } }
    this.sel = [el];
    this.commit(t('Kurve gezeichnet'));
  }

  // ---------------------------------------------------------------- Bleistift (frei zeichnen, geglättet)
  pencilDown(e, p0) {
    const pts = [p0];
    this.pen = null;
    const el = this.create('path', { d: `M${num(p0.x)} ${num(p0.y)}`, 'data-open': '1' });
    el.setAttribute('fill', 'none');
    if (!el.getAttribute('stroke') && this.format !== 'icon') { el.setAttribute('stroke', this.style.fill !== 'none' ? this.style.fill : '#1F2430'); el.setAttribute('stroke-width', num(this.style.strokeWidth)); }
    el.setAttribute('stroke-linecap', 'round'); el.setAttribute('stroke-linejoin', 'round');
    this.track(e, (p) => {
      pts.push(p);
      el.setAttribute('d', 'M' + pts.map(q => { const l = this.local(q); return num(l.x, 2) + ' ' + num(l.y, 2); }).join('L'));
    }, () => {
      if (pts.length < 3) { el.remove(); this.draw(); return; }
      const loc = pts.map(q => this.local(q));
      const eps = (1.5 + this.opt.smooth * 4) / this.zoom;
      const simp = simplify(loc, eps);
      const closed = Math.hypot(loc[0].x - loc[loc.length - 1].x, loc[0].y - loc[loc.length - 1].y) * this.zoom < 10 && simp.length > 3;
      if (closed) simp.pop();
      el.setAttribute('d', pathToString(fromNodes([{ closed, nodes: smoothNodes(simp, this.opt.smooth, closed) }])));
      this.sel = [el];
      this.commit(t('Freihandlinie'));
    });
  }

  // ---------------------------------------------------------------- Text
  textDown(e, p) {
    e.preventDefault();   // kein mousedown-Fokus auf die Zeichenfläche – die Eingabe bekommt ihn
    const hit = this.hitAt(e)?.el;
    if (hit && hit.localName === 'text') return this.beginText(hit);
    const q = this.local(p);
    const el = this.create('text', { x: num(q.x), y: num(q.y + this.opt.fontSize * 0.8), 'font-size': num(this.opt.fontSize), 'font-family': this.opt.fontFamily, 'font-weight': this.opt.fontWeight !== '400' ? this.opt.fontWeight : null, 'text-anchor': this.opt.anchor !== 'start' ? this.opt.anchor : null }, false);
    el.setAttribute('fill', this.style.fill !== 'none' ? this.style.fill : (this.format === 'icon' ? 'currentColor' : '#1F2430'));
    el.textContent = '';
    this.beginText(el, true);
  }

  beginText(el, fresh = false) {
    this.textEl = el; this.textFresh = fresh; this.textBefore = getText(el);
    this.sel = [el];
    const ta = this.$('.ge-textedit');
    ta.hidden = false;
    ta.value = getText(el);
    const place = () => {
      const fs = parseFloat(getProp(el, 'font-size')) || 16, m = decompose(docMatrix(el, this.root));
      const bb = docBounds(el, this.root) || { x: 0, y: 0, w: 0, h: 0 };
      const empty = !el.textContent;
      const a = empty ? this.toOv(pt(docMatrix(el.parentNode, this.root), parseFloat(el.getAttribute('x')) || 0, (parseFloat(el.getAttribute('y')) || 0) - fs * 0.8)) : this.toOv({ x: bb.x, y: bb.y });
      const size = fs * this.zoom * m.sx;
      Object.assign(ta.style, { left: a.x - 4 + 'px', top: a.y - 4 + 'px', fontSize: size + 'px', fontFamily: getProp(el, 'font-family'), fontWeight: getProp(el, 'font-weight'),
        minWidth: Math.max(60, bb.w * this.zoom + 24) + 'px', height: Math.max(size * 1.4, bb.h * this.zoom) + 16 + 'px', lineHeight: '1.2' });
    };
    place();
    ta.oninput = () => { setText(el, ta.value); place(); this.draw(); };
    ta.onkeydown = ev => { ev.stopPropagation(); if (ev.key === 'Escape') { ev.preventDefault(); this.endText(); this.stage.focus(); } };
    ta.focus(); ta.select();
    requestAnimationFrame(() => { if (document.activeElement !== ta && this.textEl === el) { ta.focus(); ta.select(); } });
    this.draw();
  }

  endText() {
    const el = this.textEl;
    if (!el) return;
    this.textEl = null;
    const ta = this.$('.ge-textedit');
    ta.hidden = true; ta.oninput = ta.onkeydown = null;
    if (!el.textContent.trim()) { el.remove(); this.sel = []; this.changed(false); return; }
    if (this.textFresh || getText(el) !== this.textBefore) { this.sel = [el]; this.commit(this.textFresh ? t('Text') : t('Text geändert')); }
    else this.changed(false);
  }

  // ---------------------------------------------------------------- Pipette
  eyedrop(e) {
    const hit = this.hitAt(e);
    const src = hit?.raw && this.root.contains(hit.raw) ? hit.raw : null;
    if (!src) return;
    const fill = getProp(src, 'fill'), stroke = getProp(src, 'stroke'), sw = getProp(src, 'stroke-width');
    if (!this.sel.length) { this.style.fill = fill; this.style.stroke = stroke; this.paintFs(); this.panels.refresh(); return; }
    for (const el of this.sel) eachShape(el, s => { setProp(s, 'fill', fill); setProp(s, 'stroke', stroke); if (stroke && stroke !== 'none') setProp(s, 'stroke-width', sw); });
    this.commit(t('Farbe übernommen'));
  }

  // ================================================================= Direktauswahl (Knoten)
  nodeStageDown(e, p) {
    const hit = this.hitAt(e);
    const raw = hit?.raw;
    if (raw && ['path', 'rect', 'ellipse', 'circle', 'polygon', 'polyline', 'line'].includes(raw.localName) && !isLocked(hit.el)) {
      if (this.sel[0] !== raw) { this.sel = [raw]; this.nodeSel.clear(); this.changed(false); }
      return;
    }
    if (!e.shiftKey) { this.nodeSel.clear(); if (!raw) this.sel = []; this.changed(false); }
    void p;
  }

  /** Form → Pfad (beim ersten Knotenzug): Attribute bleiben, Geometrie wird zu d */
  toPath(el) {
    if (el.localName === 'path') return el;
    const d = shapeToPath(el);
    if (!d) return el;
    const p = document.createElementNS(NS, 'path');
    for (const a of el.attributes) if (!['x', 'y', 'width', 'height', 'rx', 'ry', 'cx', 'cy', 'r', 'x1', 'y1', 'x2', 'y2', 'points'].includes(a.name)) p.setAttribute(a.name, a.value);
    p.setAttribute('d', d);
    el.replaceWith(p);
    this.sel = this.sel.map(s => s === el ? p : s);
    return p;
  }

  nodeModel() {
    const el = this.sel[0];
    if (!el) return null;
    const d = el.localName === 'path' ? el.getAttribute('d') : shapeToPath(el);
    if (!d) return null;
    return { el, subs: toNodes(parsePath(d)), M: docMatrix(el, this.root) };
  }

  drawNodes() {
    const nm = this.nodeModel();
    if (!nm) return '';
    const S = p => this.toOv(pt(nm.M, p.x, p.y));
    let s = '';
    const c = corners(nm.el, this.root);
    if (c) s += `<polygon class="ge-ov__sub" points="${c.map(p => { const q = this.toOv(p); return q.x + ',' + q.y; }).join(' ')}"/>`;
    nm.subs.forEach((sub, si) => sub.nodes.forEach((n, ni) => {
      const key = si + ':' + ni, q = S(n), on = this.nodeSel.has(key);
      if (on || this.nodeSel.size === 0 && sub.nodes.length < 60) {
        for (const hk of ['i', 'o']) if (n[hk]) {
          const hq = S(n[hk]);
          s += `<line class="ge-ov__hl" x1="${q.x}" y1="${q.y}" x2="${hq.x}" y2="${hq.y}"/><circle class="ge-ov__hp" data-node="${key}:${hk}" cx="${hq.x}" cy="${hq.y}" r="4"/>`;
        }
      }
      s += `<rect class="ge-ov__node${on ? ' is-on' : ''}${n.i || n.o ? ' is-smooth' : ''}" data-node="${key}" x="${q.x - 4.5}" y="${q.y - 4.5}" width="9" height="9"${n.i || n.o ? ' rx="4.5"' : ''}/>`;
    }));
    return s;
  }

  nodeDown(e, key) {
    e.stopPropagation(); e.preventDefault();
    const [si, ni, hk] = key.split(':');
    const k = si + ':' + ni;
    if (!hk) {
      if (e.shiftKey) { this.nodeSel.has(k) ? this.nodeSel.delete(k) : this.nodeSel.add(k); }
      else if (!this.nodeSel.has(k)) { this.nodeSel.clear(); this.nodeSel.add(k); }
    }
    const el = this.toPath(this.sel[0]);
    const base = el.getAttribute('d');
    const Mi = docMatrix(el, this.root).inverse();
    const p0 = pt(Mi, this.toDoc(e.clientX, e.clientY).x, this.toDoc(e.clientX, e.clientY).y);
    let moved = false;
    this.track(e, (p, ev) => {
      const q = pt(Mi, p.x, p.y);
      let dx = q.x - p0.x, dy = q.y - p0.y;
      if (!moved && Math.hypot(dx, dy) * this.zoom < 2) return;
      moved = true;
      const subs = toNodes(parsePath(base));
      if (hk) {
        const n = subs[si].nodes[ni], hd = n[hk];
        if (!hd) return;
        hd.x += dx; hd.y += dy;
        // glatter Knoten: Gegengriff spiegeln (⌥ = Ecke, Griffe getrennt)
        const other = n[hk === 'i' ? 'o' : 'i'];
        if (other && !ev.altKey) {
          const l = Math.hypot(other.x - n.x, other.y - n.y), a = Math.atan2(n.y - hd.y, n.x - hd.x);
          other.x = n.x + Math.cos(a) * l; other.y = n.y + Math.sin(a) * l;
        }
      } else {
        for (const key2 of this.nodeSel) {
          const [a, b] = key2.split(':').map(Number), n = subs[a]?.nodes[b];
          if (!n) continue;
          n.x += dx; n.y += dy;
          if (n.i) { n.i.x += dx; n.i.y += dy; }
          if (n.o) { n.o.x += dx; n.o.y += dy; }
        }
      }
      el.setAttribute('d', pathToString(fromNodes(subs)));
      this.draw();
    }, () => { if (moved) this.commit(t('Knoten bearbeitet')); else this.draw(); });
    this.draw();
  }

  /** Doppelklick auf Knoten: Ecke ↔ glatt */
  nodeToggle(e, key) {
    e.stopPropagation();
    const [si, ni] = key.split(':').map(Number);
    const el = this.toPath(this.sel[0]);
    const subs = toNodes(parsePath(el.getAttribute('d')));
    const sub = subs[si], n = sub?.nodes[ni];
    if (!n) return;
    if (n.i || n.o) { n.i = n.o = null; }
    else {
      const prev = sub.nodes[(ni - 1 + sub.nodes.length) % sub.nodes.length], next = sub.nodes[(ni + 1) % sub.nodes.length];
      const tx = (next.x - prev.x) / 6, ty = (next.y - prev.y) / 6;
      n.i = { x: n.x - tx, y: n.y - ty }; n.o = { x: n.x + tx, y: n.y + ty };
    }
    el.setAttribute('d', pathToString(fromNodes(subs)));
    this.commit(t('Knotenart geändert'));
  }

  deleteNodes() {
    const el = this.toPath(this.sel[0]);
    const subs = toNodes(parsePath(el.getAttribute('d')));
    const keys = [...this.nodeSel].map(k => k.split(':').map(Number)).sort((a, b) => b[1] - a[1]);
    for (const [si, ni] of keys) subs[si]?.nodes.splice(ni, 1);
    const left = subs.filter(s => s.nodes.length > 1);
    this.nodeSel.clear();
    if (!left.length) { el.remove(); this.sel = []; }
    else el.setAttribute('d', pathToString(fromNodes(left)));
    this.commit(t('Knoten gelöscht'));
  }

  // ================================================================= Befehle
  /** ids einer Kopie eindeutig machen (Verweise innerhalb der Kopie folgen) */
  freshIds(el) { remapIds(el, this.root); }

  duplicate() {
    if (!this.sel.length) return;
    const off = this.format === 'icon' ? 1 : 10;
    const clones = this.sel.map(el => { const c = el.cloneNode(true); el.after(c); this.freshIds(c); applyTransform(c, new DOMMatrix().translate(off, off), this.root); return c; });
    this.sel = clones;
    this.commit(t('Dupliziert'));
  }

  remove() {
    if (this.tool === 'node' && this.nodeSel.size) return this.deleteNodes();
    const list = this.sel.filter(el => !isLocked(el));
    if (!list.length) return;
    list.forEach(el => el.remove());
    this.sel = [];
    this.commit(t('Gelöscht'));
  }

  group() {
    if (this.sel.length < 1) return;
    const list = this.ordered(this.sel);
    const parent = list[0].parentNode;
    if (list.some(el => el.parentNode !== parent)) { toast(t('Nur Objekte derselben Ebene lassen sich gruppieren.'), 'warn'); return; }
    const g = document.createElementNS(NS, 'g');
    list[list.length - 1].after(g);
    list.forEach(el => g.append(el));
    this.sel = [g];
    this.commit(t('Gruppiert'));
  }

  ungroup() {
    const out = [];
    for (const g of this.sel) {
      if (!['g', 'switch'].includes(g.localName)) { out.push(g); continue; }
      // Eigenschaften der Gruppe (transform, Darstellung) an die Kinder weitergeben
      const T = g.hasAttribute('transform') ? docMatrix(g, this.root).multiply(docMatrix(g.parentNode, this.root).inverse()) : null;
      const inherit = ['fill', 'stroke', 'stroke-width', 'opacity', 'font-family', 'font-size', 'font-weight', 'stroke-linecap', 'stroke-linejoin', 'color'];
      for (const c of [...g.children]) {
        g.before(c);
        if (!isDrawn(c)) continue;
        for (const a of inherit) {
          const v = getProp(g, a, false);
          if (!v || getProp(c, a, false)) continue;
          if (a === 'opacity') setProp(c, a, num((parseFloat(v) || 1) * (parseFloat(getProp(c, 'opacity', false)) || 1)));
          else c.setAttribute(a, v);
        }
        if (T) { const pm = parentMatrix(c, this.root); applyTransform(c, pm.multiply(localMatrixOf(g)).multiply(pm.inverse()), this.root); }
        out.push(c);
      }
      g.remove();
    }
    this.sel = out.filter(isDrawn);
    this.commit(t('Gruppierung aufgehoben'));
  }

  /** Auswahl in Dokumentreihenfolge */
  ordered(list) { return [...list].sort((a, b) => a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1); }

  arrange(how) {
    if (!this.sel.length) return;
    const list = this.ordered(this.sel);
    if (how === 'front') list.forEach(el => el.parentNode.append(el));
    else if (how === 'back') [...list].reverse().forEach(el => { const first = drawnChildren(el.parentNode)[0]; if (first !== el) el.parentNode.insertBefore(el, first); });
    else if (how === 'forward') [...list].reverse().forEach(el => { const n = nextDrawn(el); if (n) n.after(el); });
    else if (how === 'backward') list.forEach(el => { const p = prevDrawn(el); if (p) p.before(el); });
    this.commit({ front: t('Ganz nach vorn'), back: t('Ganz nach hinten'), forward: t('Eine Ebene nach vorn'), backward: t('Eine Ebene nach hinten') }[how]);
  }

  /** Ausrichten an der Auswahl (bei einem Objekt: an der Zeichenfläche) */
  align(how) {
    if (!this.sel.length) return;
    const vb = this.vb;
    const ref = this.sel.length === 1 ? { x: vb.x, y: vb.y, w: vb.w, h: vb.h } : this.selBounds();
    for (const el of this.sel) {
      const b = docBounds(el, this.root);
      if (!b) continue;
      let dx = 0, dy = 0;
      if (how === 'left') dx = ref.x - b.x;
      if (how === 'hcenter') dx = ref.x + ref.w / 2 - (b.x + b.w / 2);
      if (how === 'right') dx = ref.x + ref.w - (b.x + b.w);
      if (how === 'top') dy = ref.y - b.y;
      if (how === 'vcenter') dy = ref.y + ref.h / 2 - (b.y + b.h / 2);
      if (how === 'bottom') dy = ref.y + ref.h - (b.y + b.h);
      if (dx || dy) applyTransform(el, new DOMMatrix().translate(dx, dy), this.root);
    }
    this.commit(t('Ausgerichtet'));
  }

  distribute(axis) {
    if (this.sel.length < 3) return;
    const items = this.sel.map(el => ({ el, b: docBounds(el, this.root) })).filter(x => x.b);
    const k = axis === 'h' ? 'x' : 'y', s = axis === 'h' ? 'w' : 'h';
    items.sort((a, b) => a.b[k] - b.b[k]);
    const first = items[0].b, last = items[items.length - 1].b;
    const total = items.reduce((n, x) => n + x.b[s], 0), gap = (last[k] + last[s] - first[k] - total) / (items.length - 1);
    let pos = first[k] + first[s] + gap;
    for (const x of items.slice(1, -1)) {
      const d = pos - x.b[k];
      applyTransform(x.el, new DOMMatrix().translate(axis === 'h' ? d : 0, axis === 'h' ? 0 : d), this.root);
      pos += x.b[s] + gap;
    }
    this.commit(t('Verteilt'));
  }

  flip(axis) {
    const f = this.selBounds();
    if (!f) return;
    const cx = f.x + f.w / 2, cy = f.y + f.h / 2;
    const T = new DOMMatrix().translate(cx, cy).scale(axis === 'h' ? -1 : 1, axis === 'v' ? -1 : 1).translate(-cx, -cy);
    this.sel.forEach(el => applyTransform(el, T, this.root));
    this.commit(axis === 'h' ? t('Horizontal gespiegelt') : t('Vertikal gespiegelt'));
  }

  nudge(dx, dy) {
    const list = this.sel.filter(el => !isLocked(el));
    if (!list.length) return;
    list.forEach(el => applyTransform(el, new DOMMatrix().translate(dx, dy), this.root));
    clearTimeout(this.nudgeT);
    this.draw();
    this.panels.refresh();
    // mehrere Pfeiltasten hintereinander = ein Schritt im Protokoll
    this.nudgeT = setTimeout(() => this.commit(t('Verschoben')), 400);
  }

  toCurves() {
    const list = this.sel.filter(el => ['rect', 'ellipse', 'circle', 'polygon', 'polyline', 'line'].includes(el.localName));
    if (!list.length) return;
    list.forEach(el => this.toPath(el));
    this.commit(t('In Kurven umgewandelt'));
  }

  selectAll() { this.select(drawnChildren(this.scope).filter(el => !isLocked(el) && !isHidden(el))); }

  /** Tab in der Zeichenfläche: nächstes Objekt auswählen (Tastaturbedienung) */
  cycle(d) {
    const list = drawnChildren(this.scope).filter(el => !isHidden(el));
    if (!list.length) return;
    const i = list.indexOf(this.sel[0]);
    const n = list[(i + d + list.length) % list.length] || list[0];
    this.select([n]);
    this.hint(t('{name} ausgewählt – Pfeiltasten verschieben, ⌫ löscht.', { name: nameOf(n) }));
  }

  // ---------------------------------------------------------------- Zwischenablage (intern + Text)
  copy(cut = false) {
    if (!this.sel.length) return;
    this.clip = this.sel.map(el => {
      const c = el.cloneNode(true);
      const P = parentMatrix(el, this.root);
      if (!P.isIdentity) { const g = document.createElementNS(NS, 'g'); g.setAttribute('transform', `matrix(${[P.a, P.b, P.c, P.d, P.e, P.f].map(v => num(v, 6)).join(' ')})`); g.append(c); return new XMLSerializer().serializeToString(g); }
      return new XMLSerializer().serializeToString(c);
    });
    if (cut) { this.sel.forEach(el => el.remove()); this.sel = []; this.commit(t('Ausgeschnitten')); }
    toast(cut ? t('Ausgeschnitten') : t('Kopiert'));
  }

  paste() {
    if (!this.clip?.length) return;
    const out = [];
    for (const s of this.clip) {
      const el = document.importNode(new DOMParser().parseFromString(`<svg xmlns="${NS}">${s}</svg>`, 'image/svg+xml').documentElement.firstElementChild, true);
      if (!el) continue;
      this.freshIds(el);
      this.target.append(el);
      if (this.target !== this.root) applyTransform(el, new DOMMatrix(), this.root);
      out.push(el);
    }
    this.sel = out;
    this.commit(t('Eingefügt'));
  }

  // ================================================================= Tastatur
  bindKeys() {
    this.keyHandler = e => {
      if (!this.el.isConnected || this.el.closest('[inert]')) return;
      if (e.target.closest?.('dialog.ge-modal')) return;
      const inField = e.target.matches?.('input,textarea,select,[contenteditable]') && !e.target.closest('.ge-stage');
      const mod = e.metaKey || e.ctrlKey, k = e.key.toLowerCase();
      if (mod && k === 's') { e.preventDefault(); this.save(e.shiftKey); return; }
      if (inField) return;
      if (e.target.closest?.('.ge-textedit')) return;
      if (mod && k === 'z') { e.preventDefault(); e.shiftKey ? this.redo() : this.undo(); return; }
      if (mod && k === 'y') { e.preventDefault(); this.redo(); return; }
      if (mod && k === 'd') { e.preventDefault(); this.duplicate(); return; }
      if (mod && k === 'g') { e.preventDefault(); e.shiftKey ? this.ungroup() : this.group(); return; }
      if (mod && k === 'a') { e.preventDefault(); this.selectAll(); return; }
      if (mod && k === 'c') { e.preventDefault(); this.copy(); return; }
      if (mod && k === 'x') { e.preventDefault(); this.copy(true); return; }
      if (mod && k === 'v') { e.preventDefault(); this.paste(); return; }
      if (mod && (k === '0')) { e.preventDefault(); this.fit(); return; }
      if (mod && (k === '1')) { e.preventDefault(); this.setZoom(1); return; }
      if (mod && (k === '+' || k === '=')) { e.preventDefault(); this.setZoom(this.zoom * 1.25); return; }
      if (mod && (k === '-')) { e.preventDefault(); this.setZoom(this.zoom / 1.25); return; }
      if (mod && e.key === ']') { e.preventDefault(); this.arrange(e.shiftKey ? 'front' : 'forward'); return; }
      if (mod && e.key === '[') { e.preventDefault(); this.arrange(e.shiftKey ? 'back' : 'backward'); return; }
      if (mod && e.shiftKey && k === 'f') { e.preventDefault(); this.opts.shell?.toggleMax?.(); return; }
      if (mod) return;
      if (e.key === ' ' && !this.space) { if (e.target.closest?.('button,[role=tab],[role=slider]')) return; e.preventDefault(); this.space = true; this.el.classList.add('is-space'); return; }
      if (e.key === 'Escape') {
        if (this.pen) { e.preventDefault(); this.finishPen(); return; }
        if (this.tool === 'node' && this.nodeSel.size) { this.nodeSel.clear(); this.draw(); return; }
        if (this.scope !== this.root) { e.preventDefault(); const g = this.scope; this.scope = this.root; this.select([g]); return; }
        if (this.sel.length && e.target.closest?.('.ge-stage')) { e.preventDefault(); this.select([]); return; }
        return;
      }
      if (e.key === 'Enter' && this.pen) { e.preventDefault(); this.finishPen(); return; }
      if (e.key === 'Enter' && e.target.closest?.('.ge-stage') && this.sel.length === 1) {
        const el = this.sel[0];
        if (el.localName === 'text') { e.preventDefault(); this.beginText(el); return; }
        if (['g', 'switch'].includes(el.localName)) { e.preventDefault(); this.scope = el; this.select(drawnChildren(el).slice(0, 1)); return; }
      }
      if ((e.key === 'Backspace' || e.key === 'Delete') && (e.target.closest?.('.ge-stage') || e.target === document.body || e.target.closest?.('.ge-layers'))) {
        if (this.pen) { e.preventDefault(); this.pen.nodes.pop(); if (!this.pen.nodes.length) this.pen = null; this.draw(); return; }
        e.preventDefault(); this.remove(); return;
      }
      if (e.key.startsWith('Arrow') && e.target.closest?.('.ge-stage')) {
        e.preventDefault();
        const s = (e.shiftKey ? 10 : 1) * (this.format === 'info' || this.zoom < 2 ? 1 : 1);
        const d = { ArrowLeft: [-s, 0], ArrowRight: [s, 0], ArrowUp: [0, -s], ArrowDown: [0, s] }[e.key];
        if (this.tool === 'node' && this.nodeSel.size) return this.nudgeNodes(...d);
        this.nudge(...d); return;
      }
      if (e.key === 'Tab' && e.target.closest?.('.ge-stage') && !e.altKey) {
        if (drawnChildren(this.scope).length) { e.preventDefault(); this.cycle(e.shiftKey ? -1 : 1); }
        return;
      }
      if (e.shiftKey && k === 'x') { e.preventDefault(); this.swapFs(); return; }
      if (k === 'x' && !e.shiftKey) { this.style.target = this.style.target === 'fill' ? 'stroke' : 'fill'; this.paintFs(); this.panels.showColor(); return; }
      if (e.key === '?') { e.preventDefault(); this.shortcuts(); return; }
      const tl = TOOLS.find(x => x.key.toLowerCase() === k);
      if (tl && !e.altKey) { e.preventDefault(); this.setTool(tl.id); }
    };
    this.keyUp = e => { if (e.key === ' ') { this.space = false; this.el.classList.remove('is-space'); } };
    document.addEventListener('keydown', this.keyHandler);
    document.addEventListener('keyup', this.keyUp);
  }

  nudgeNodes(dx, dy) {
    const el = this.toPath(this.sel[0]);
    const Mi = docMatrix(el, this.root).inverse();
    const d0 = pt(Mi, 0, 0), d1 = pt(Mi, dx, dy), lx = d1.x - d0.x, ly = d1.y - d0.y;
    const subs = toNodes(parsePath(el.getAttribute('d')));
    for (const k of this.nodeSel) {
      const [a, b] = k.split(':').map(Number), n = subs[a]?.nodes[b];
      if (!n) continue;
      n.x += lx; n.y += ly;
      if (n.i) { n.i.x += lx; n.i.y += ly; }
      if (n.o) { n.o.x += lx; n.o.y += ly; }
    }
    el.setAttribute('d', pathToString(fromNodes(subs)));
    this.draw();
    clearTimeout(this.nudgeT);
    this.nudgeT = setTimeout(() => this.commit(t('Knoten bearbeitet')), 400);
  }

  swapFs() {
    if (!this.sel.length) { [this.style.fill, this.style.stroke] = [this.style.stroke, this.style.fill]; this.paintFs(); this.panels.refresh(); return; }
    for (const el of this.sel) eachShape(el, s => {
      const f = getProp(s, 'fill'), st = getProp(s, 'stroke');
      setProp(s, 'fill', st || 'none'); setProp(s, 'stroke', f || 'none');
      if (f && f !== 'none' && !getProp(s, 'stroke-width', false)) setProp(s, 'stroke-width', num(this.style.strokeWidth));
    });
    this.commit(t('Füllung und Kontur getauscht'));
  }

  // ================================================================= Kontextleiste
  renderCtx() {
    const ctx = this.$('.ge-ctx');
    if (!ctx || ctx.contains(document.activeElement) && document.activeElement.matches('input,select')) return;
    const n = this.sel.length;
    const status = n === 0 ? t('Keine Auswahl') : n === 1 ? nameOf(this.sel[0]) + (this.scope !== this.root ? ' · ' + t('in Gruppe') : '') : t('{n} Objekte', { n });
    let html = `<span class="ge-ctx__status">${esc(status)}</span><span class="ge-ctx__sep"></span>`;
    const o = this.opt;
    const numIn = (k, label, min, max, step) => `<label class="ge-ctx__f">${esc(label)} <input type="number" class="ge-in ge-in--s" data-opt="${k}" value="${esc(num(o[k], 2))}" min="${min}" max="${max}" step="${step}"></label>`;
    const chk = (k, label) => `<label class="ge-ctx__f ge-ctx__chk"><input type="checkbox" data-opt="${k}"${o[k] ? ' checked' : ''}> ${esc(label)}</label>`;
    if (this.tool === 'rect') html += numIn('radius', t('Eckenradius'), 0, 9999, 1);
    if (this.tool === 'polygon') html += numIn('sides', t('Ecken'), 3, 64, 1) + chk('star', t('Stern')) + numIn('inner', t('Innenradius'), 0.05, 0.95, 0.05);
    if (this.tool === 'line') html += chk('arrowStart', t('Pfeil am Anfang')) + chk('arrowEnd', t('Pfeil am Ende'));
    if (this.tool === 'pencil') html += `<label class="ge-ctx__f">${esc(t('Glättung'))} <input type="range" data-opt="smooth" min="0" max="1" step="0.05" value="${o.smooth}"></label>`;
    if (this.tool === 'text' || (n === 1 && this.sel[0].localName === 'text')) {
      const el = n === 1 && this.sel[0].localName === 'text' ? this.sel[0] : null;
      const ff = el ? getProp(el, 'font-family').replace(/["']/g, '') : o.fontFamily, fs = el ? parseFloat(getProp(el, 'font-size')) : o.fontSize;
      const fw = el ? getProp(el, 'font-weight') : o.fontWeight, an = el ? (getProp(el, 'text-anchor') || 'start') : o.anchor;
      const fonts = [...new Set([ff, ...(this.boot.fonts || [])])].filter(Boolean);
      html += `<label class="ge-ctx__f">${esc(t('Schrift'))} <select class="ge-in" data-text="font-family">${fonts.map(f => `<option${f === ff ? ' selected' : ''}>${esc(f)}</option>`).join('')}</select></label>`
        + `<label class="ge-ctx__f">${esc(t('Größe'))} <input type="number" class="ge-in ge-in--s" data-text="font-size" value="${esc(num(fs || 16, 2))}" min="1" max="2000" step="1"></label>`
        + `<label class="ge-ctx__f">${esc(t('Stärke'))} <select class="ge-in" data-text="font-weight">${[['300', t('Leicht')], ['400', t('Normal')], ['700', t('Fett')], ['900', t('Schwarz')]].map(([v, l]) => `<option value="${v}"${String(fw) === v || (fw === 'bold' && v === '700') || (fw === 'normal' && v === '400') ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></label>`
        + `<span class="ge-seg" role="group" aria-label="${esc(t('Ausrichtung'))}">${[['start', 'align-left', t('Links')], ['middle', 'align-center-horizontal', t('Mitte')], ['end', 'align-right', t('Rechts')]].map(([v, i, l]) => `<button type="button" class="ge-ib" data-text-anchor="${v}" aria-pressed="${an === v}" aria-label="${esc(l)}" title="${esc(l)}">${ico(i)}</button>`).join('')}</span>`;
    }
    if (n && this.tool === 'select') {
      html += `<span class="ge-ctx__sep"></span><span class="ge-seg" role="group" aria-label="${esc(t('Ausrichten'))}">`
        + [['left', 'align-left', t('Links ausrichten')], ['hcenter', 'align-center-horizontal', t('Horizontal zentrieren')], ['right', 'align-right', t('Rechts ausrichten')],
          ['top', 'align-top', t('Oben ausrichten')], ['vcenter', 'align-center-vertical', t('Vertikal zentrieren')], ['bottom', 'align-bottom', t('Unten ausrichten')]]
          .map(([v, i, l]) => `<button type="button" class="ge-ib" data-align="${v}" aria-label="${esc(l)}" title="${esc(l)}${n === 1 ? ' – ' + esc(t('an der Zeichenfläche')) : ''}">${ico(i)}</button>`).join('')
        + `</span>`;
      if (n > 2) html += `<span class="ge-seg" role="group" aria-label="${esc(t('Verteilen'))}">${iconBtn('arrows-left-right', t('Horizontal verteilen'), 'data-dist="h"')}${iconBtn('arrows-down-up', t('Vertikal verteilen'), 'data-dist="v"')}</span>`;
      html += `<span class="ge-seg" role="group" aria-label="${esc(t('Anordnen'))}">${iconBtn('arrow-line-up', t('Ganz nach vorn'), 'data-arr="front"', SHIFT + MOD + ']')}${iconBtn('arrow-up', t('Eine Ebene nach vorn'), 'data-arr="forward"', MOD + ']')}${iconBtn('arrow-down', t('Eine Ebene nach hinten'), 'data-arr="backward"', MOD + '[')}${iconBtn('arrow-line-down', t('Ganz nach hinten'), 'data-arr="back"', SHIFT + MOD + '[')}</span>`
        + `<span class="ge-seg" role="group" aria-label="${esc(t('Spiegeln'))}">${iconBtn('flip-horizontal', t('Horizontal spiegeln'), 'data-flip="h"')}${iconBtn('flip-vertical', t('Vertikal spiegeln'), 'data-flip="v"')}</span>`
        + `<span class="ge-seg" role="group" aria-label="${esc(t('Gruppe'))}">${iconBtn('selection-plus', t('Gruppieren'), 'data-cmd="group"', MOD + 'G')}${this.sel.some(el => el.localName === 'g') ? iconBtn('selection', t('Gruppierung aufheben'), 'data-cmd="ungroup"', SHIFT + MOD + 'G') : ''}${this.sel.some(el => ['rect', 'ellipse', 'circle', 'polygon', 'polyline', 'line'].includes(el.localName)) ? iconBtn('bezier-curve', t('In Kurven umwandeln'), 'data-cmd="curves"') : ''}</span>`;
      if (this.sel.some(el => ['line', 'path', 'polyline'].includes(el.localName))) {
        const el = this.sel[0];
        html += chk2('arrowStart', t('Pfeil am Anfang'), !!getProp(el, 'marker-start', false)) + chk2('arrowEnd', t('Pfeil am Ende'), !!getProp(el, 'marker-end', false));
      }
    }
    if (this.tool === 'node' && n === 1) {
      html += `<span class="ge-ctx__info">${esc(this.nodeSel.size ? t('{n} Knoten ausgewählt', { n: this.nodeSel.size }) : t('Knoten anklicken oder ziehen'))}</span>`;
      if (this.nodeSel.size) html += `<button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-cmd="delnodes">${ico('trash')} ${esc(t('Knoten löschen'))}</button>`;
    }
    html += `<span class="ge-ctx__grow"></span>${chk('snap', t('Einrasten'))}${this.format !== 'info' || o.grid ? chk('grid', t('Raster')) : ''}
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-act="docset">${ico('sliders-horizontal')} ${esc(t('Dokument …'))}</button>`;
    if (ctx._html === html) return;   // unverändert: nicht neu aufbauen (Symbole flackern sonst)
    ctx._html = html;
    ctx.innerHTML = html;
    function chk2(k, label, on) { return `<label class="ge-ctx__f ge-ctx__chk"><input type="checkbox" data-arrow="${k}"${on ? ' checked' : ''}> ${esc(label)}</label>`; }
    ctx.querySelectorAll('[data-opt]').forEach(i => i.addEventListener('change', () => {
      const k = i.dataset.opt;
      o[k] = i.type === 'checkbox' ? i.checked : +i.value;
      if (k === 'grid' || k === 'snap') this.draw();
    }));
    ctx.querySelectorAll('[data-text]').forEach(i => i.addEventListener('change', () => {
      const a = i.dataset.text, v = i.value;
      if (a === 'font-family') o.fontFamily = v; if (a === 'font-size') o.fontSize = +v; if (a === 'font-weight') o.fontWeight = v;
      const el = this.sel.length === 1 && this.sel[0].localName === 'text' ? this.sel[0] : null;
      if (el) { setProp(el, a, a === 'font-size' ? num(+v) : v); this.commit(t('Schrift geändert')); }
    }));
    ctx.querySelectorAll('[data-text-anchor]').forEach(b => b.addEventListener('click', () => {
      o.anchor = b.dataset.textAnchor;
      const el = this.sel.length === 1 && this.sel[0].localName === 'text' ? this.sel[0] : null;
      if (el) {
        const before = docBounds(el, this.root);
        setProp(el, 'text-anchor', o.anchor === 'start' ? null : o.anchor);
        const after = docBounds(el, this.root);
        if (before && after) applyTransform(el, new DOMMatrix().translate(before.x - after.x, 0), this.root);
        this.commit(t('Ausrichtung geändert'));
      } else this.renderCtx();
    }));
    ctx.querySelectorAll('[data-align]').forEach(b => b.addEventListener('click', () => this.align(b.dataset.align)));
    ctx.querySelectorAll('[data-dist]').forEach(b => b.addEventListener('click', () => this.distribute(b.dataset.dist)));
    ctx.querySelectorAll('[data-arr]').forEach(b => b.addEventListener('click', () => this.arrange(b.dataset.arr)));
    ctx.querySelectorAll('[data-flip]').forEach(b => b.addEventListener('click', () => this.flip(b.dataset.flip)));
    ctx.querySelectorAll('[data-arrow]').forEach(i => i.addEventListener('change', () => {
      for (const el of this.sel) if (['line', 'path', 'polyline'].includes(el.localName)) {
        this.applyArrows(el, i.dataset.arrow === 'arrowStart' ? i.checked : !!getProp(el, 'marker-start', false), i.dataset.arrow === 'arrowEnd' ? i.checked : !!getProp(el, 'marker-end', false));
      }
      this.commit(t('Pfeilspitzen'));
    }));
    ctx.querySelector('[data-cmd=group]')?.addEventListener('click', () => this.group());
    ctx.querySelector('[data-cmd=ungroup]')?.addEventListener('click', () => this.ungroup());
    ctx.querySelector('[data-cmd=curves]')?.addEventListener('click', () => this.toCurves());
    ctx.querySelector('[data-cmd=delnodes]')?.addEventListener('click', () => this.deleteNodes());
    ctx.querySelector('[data-act=docset]').addEventListener('click', () => this.docSettings());
  }

  // ================================================================= Kopfzeile: Speichern, Export, Zoom
  bindTop() {
    this.el.querySelector('[data-act=undo]').addEventListener('click', () => this.undo());
    this.el.querySelector('[data-act=redo]').addEventListener('click', () => this.redo());
    this.el.querySelector('[data-act=save]').addEventListener('click', () => this.save());
    this.el.querySelector('[data-act=swapfs]')?.addEventListener('click', () => this.swapFs());
    const zb = this.el.querySelector('[data-act=zoommenu]');
    zb.addEventListener('click', () => menu(zb, [
      { label: t('Einpassen'), key: MOD + '0', run: () => this.fit() },
      { label: '100 %', key: MOD + '1', run: () => this.setZoom(1) },
      { label: '200 %', run: () => this.setZoom(2) },
      { label: '400 %', run: () => this.setZoom(4) },
      { label: '800 %', run: () => this.setZoom(8) },
      { label: '50 %', run: () => this.setZoom(.5) },
      { sep: true },
      { label: t('Vergrößern'), key: MOD + '+', run: () => this.setZoom(this.zoom * 1.25) },
      { label: t('Verkleinern'), key: MOD + '−', run: () => this.setZoom(this.zoom / 1.25) },
    ]));
    const ex = this.el.querySelector('[data-act=export]');
    ex.addEventListener('click', () => menu(ex, [
      { label: t('Als SVG herunterladen'), run: () => this.download('svg') },
      { label: t('Als PNG herunterladen (2-fach)'), run: () => this.download('png') },
      { sep: true },
      { label: t('Speichern unter …'), key: SHIFT + MOD + 'S', run: () => this.save(true) },
      { label: t('Tastaturkürzel'), key: '?', run: () => this.shortcuts() },
    ]));
  }

  fileBase() { return (this.doc.name || this.doc.title || 'grafik').toLowerCase().replace(/[^a-z0-9äöüß_-]+/gi, '-').replace(/^-+|-+$/g, '') || 'grafik'; }

  async download(kind) {
    const svg = this.getSvg();
    let blob;
    if (kind === 'svg') blob = new Blob([svg], { type: 'image/svg+xml' });
    else {
      const vb = this.vb, s = Math.max(1, Math.min(8, 2 * (this.format === 'icon' ? 8 : 1)));
      const img = new Image();
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg.replace(/<svg\b/, `<svg width="${vb.w * s}" height="${vb.h * s}"`).replace(/(<svg[^>]*?)\swidth="[^"]*"([^>]*?)\swidth=/, '$1$2 width='));
      try { await img.decode(); } catch { toast(t('Das Bild konnte nicht erzeugt werden.'), 'error'); return; }
      const c = document.createElement('canvas'); c.width = Math.round(vb.w * s); c.height = Math.round(vb.h * s);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      blob = await new Promise(r => c.toBlob(r, 'image/png'));
    }
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = this.fileBase() + '.' + kind;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
  }

  /** Titel und Beschreibung als <title>/<desc> in der Grafik (Barrierefreiheit) */
  setA11y(title, desc) {
    for (const [tag, v] of [['desc', desc], ['title', title]]) {
      let n = [...this.root.children].find(c => c.localName === tag);
      if (!v) { n?.remove(); continue; }
      if (!n) { n = document.createElementNS(NS, tag); this.root.prepend(n); }
      n.textContent = v;
    }
    const tt = [...this.root.children].find(c => c.localName === 'title');
    if (tt) this.root.prepend(tt);
  }

  async docSettings() {
    const vb = this.vb, f = this.format;
    const title = [...this.root.children].find(c => c.localName === 'title')?.textContent || '';
    const desc = [...this.root.children].find(c => c.localName === 'desc')?.textContent || '';
    const vers = this.doc.versions || [];
    const r = await modal(this.el, {
      title: t('Dokument'), ok: t('Übernehmen'),
      body: `${f === 'info' ? `<div class="ge-row2"><label class="f">${esc(t('Breite'))}<input type="number" name="w" min="1" max="10000" step="1" value="${esc(num(vb.w, 2))}" required></label>
          <label class="f">${esc(t('Höhe'))}<input type="number" name="h" min="1" max="10000" step="1" value="${esc(num(vb.h, 2))}" required></label></div>
          <label class="f-check"><input type="checkbox" name="scale"> ${esc(t('Inhalt mit skalieren'))}</label>`
        : `<p class="f-help">${esc(t('Feste Größe: {w} × {h}', { w: num(vb.w), h: num(vb.h) }))}</p>`}
        <label class="f">${esc(t('Titel in der Grafik'))}<input name="title" maxlength="180" value="${esc(title)}"><span class="f-help">${esc(t('Wird als <title> gespeichert – Screenreader lesen ihn, wenn die Grafik direkt eingebunden ist.'))}</span></label>
        <label class="f">${esc(t('Beschreibung in der Grafik'))}<textarea name="desc" rows="3" maxlength="1000">${esc(desc)}</textarea><span class="f-help">${esc(t('Ausführlicher Text für Infografiken (<desc>). Texte in der Grafik bleiben Text.'))}</span></label>
        ${vers.length ? `<h3 class="ge-modal__h3">${esc(t('Frühere Fassungen'))}</h3><ul class="ge-versions">${vers.map(v => `<li><span>${esc(new Date(v.created_at.replace(' ', 'T')).toLocaleString())}</span><button type="button" class="adm-btn adm-btn--small" data-ver="${v.id}">${esc(t('Laden'))}</button></li>`).join('')}</ul>` : ''}`,
      onOpen: (d, finish) => d.querySelectorAll('[data-ver]').forEach(b => b.addEventListener('click', async () => {
        const res = await this.api('GET', this.boot.urls.version + '?ref=' + encodeURIComponent(this.doc.ref) + '&id=' + b.dataset.ver);
        if (!res?.ok) return;
        finish(null);
        this.load(res.svg); this.commit(t('Frühere Fassung geladen'));
      })),
    });
    if (!r) return;
    if (f === 'info') {
      const w = Math.max(1, Math.min(10000, +r.w)), hh = Math.max(1, Math.min(10000, +r.h));
      if (w !== vb.w || hh !== vb.h) {
        if (r.scale) for (const el of drawnChildren(this.root)) applyTransform(el, new DOMMatrix().translate(vb.x, vb.y).scale(w / vb.w, hh / vb.h).translate(-vb.x, -vb.y), this.root);
        this.root.setAttribute('viewBox', `${num(vb.x)} ${num(vb.y)} ${num(w)} ${num(hh)}`);
        this.root.setAttribute('width', num(w)); this.root.setAttribute('height', num(hh));
        this.fitted = false; this.fit();
      }
    }
    this.setA11y(r.title.trim(), r.desc.trim());
    this.commit(t('Dokument geändert'));
  }

  shortcuts() {
    const rows = [
      [t('Werkzeuge'), TOOLS.map(x => `${x.key} ${x.label()}`).join(' · ')],
      [MOD + 'Z / ' + SHIFT + MOD + 'Z', t('Rückgängig / Wiederholen')], [MOD + 'S', t('Speichern')], [MOD + 'D', t('Duplizieren')],
      [MOD + 'G / ' + SHIFT + MOD + 'G', t('Gruppieren / Gruppierung aufheben')], [MOD + 'C / X / V', t('Kopieren / Ausschneiden / Einfügen')],
      [MOD + '[ / ]', t('Eine Ebene nach hinten / vorn (mit ⇧ ganz)')], ['⌫', t('Löschen')], [t('Pfeiltasten'), t('Verschieben um 1 (mit ⇧ um 10)')],
      ['Tab', t('Nächstes Objekt auswählen')], ['↵ / Esc', t('Gruppe betreten bzw. Text bearbeiten / verlassen')], [t('Leertaste + Ziehen'), t('Ansicht verschieben')],
      [MOD + '0 / ' + MOD + '1 / ' + MOD + '+ / ' + MOD + '−', t('Einpassen / 100 % / Zoom')], ['X / ' + SHIFT + 'X', t('Füllung oder Kontur bearbeiten / tauschen')],
      [ALT + t('Ziehen'), t('Kopie ziehen')], [SHIFT + MOD + 'F', t('Vollbild ein/aus (Overlay)')],
    ];
    modal(this.el, { title: t('Tastaturkürzel'), ok: '', wide: true, body: `<dl class="ge-keys">${rows.map(([k, v]) => `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`).join('')}</dl>` });
  }

  // ================================================================= Speichern
  async api(method, url, body) {
    try {
      const r = await fetch(url, { method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() }, body: body ? JSON.stringify(body) : undefined, credentials: 'same-origin' });
      const j = await r.json().catch(() => ({ ok: false, error: t('Unerwartete Antwort vom Server.') }));
      if (!r.ok || j.ok === false) { toast(j.error || t('Speichern fehlgeschlagen.'), 'error'); return null; }
      return j;
    } catch {
      toast(t('Keine Verbindung zum Server.'), 'error');
      return null;
    }
  }

  async save(asNew = false) {
    this.finishPen(); this.endText();
    const d = this.doc;
    if (d.target === 'none') {
      // Einbettung ohne Ablage (z. B. Motion): bereinigtes SVG an den Aufrufer, der es selbst speichert
      const res = await this.api('POST', this.boot.urls.clean, { svg: this.getSvg() });
      if (!res) return null;
      this.savedAt = this.hi; this.changed(false);
      const result = { target: 'none', item: null, svg: res.svg, format: this.format };
      this.opts.onSave?.(result); this.emit('save', result);
      return result;
    }
    if (d.shared && !asNew) asNew = true;
    let meta = {};
    const isMedia = d.target !== 'graphics';
    const first = isMedia ? (!d.mediaId || asNew) : (!d.id || asNew);
    if (first) {
      const title = [...this.root.children].find(c => c.localName === 'title')?.textContent || d.title || '';
      const body = isMedia
        ? `<label class="f">${esc(t('Dateiname'))}<input name="name" maxlength="120" value="${esc(d.name || this.fileBase())}" required></label>
           <label class="f">${esc(t('Titel'))}<input name="title" maxlength="180" value="${esc(title)}"></label>
           <label class="f">${esc(t('Alt-Text (Bildbeschreibung)'))}<textarea name="alt" rows="2" maxlength="250" data-alt>${esc(d.alt || '')}</textarea><span class="f-help">${esc(t('Pflicht für Grafiken mit Inhalt: Was zeigt die Grafik? Bei Infografiken die Kernaussage.'))}</span></label>
           <label class="f-check"><input type="checkbox" name="decorative" data-deco> ${esc(t('Rein dekorativ (kein Alt-Text nötig)'))}</label>`
        : `<label class="f">${esc(t('Name'))}<input name="name" maxlength="120" value="${esc(d.name || '')}" required></label>
           ${d.kind === 'preview' || this.format === 'preview' ? `<label class="f">${esc(t('Block'))}<select name="block"><option value="">${esc(t('– keinem Block zuordnen –'))}</option>${Object.entries(this.boot.blocks || {}).map(([k, v]) => `<option value="${esc(k)}"${k === d.block ? ' selected' : ''}>${esc(v)} (${esc(k)})</option>`).join('')}</select></label>
             <label class="f-check"><input type="checkbox" name="assign" checked> ${esc(t('In der Blockauswahl dieser Website verwenden'))}</label>` : ''}`;
      const r = await modal(this.el, {
        title: asNew ? t('Speichern unter') : isMedia ? t('In der Mediathek speichern') : t('Grafik speichern'), ok: t('Speichern'), body,
        onOpen: dlg => { const deco = dlg.querySelector('[data-deco]'), alt = dlg.querySelector('[data-alt]'); if (deco) { const s = () => { alt.required = !deco.checked; alt.disabled = deco.checked; }; deco.addEventListener('change', s); s(); } },
      });
      if (!r) return null;
      meta = r;
      if (isMedia && r.title) this.setA11y(r.title, [...this.root.children].find(c => c.localName === 'desc')?.textContent || '');
    }
    const body = { svg: this.getSvg(), target: isMedia ? 'media' : 'graphics' };
    if (isMedia) Object.assign(body, first ? meta : {}, { mediaId: first ? null : d.mediaId });
    else Object.assign(body, { kind: d.kind || this.format, id: first ? null : d.id, name: meta.name ?? d.name, block: meta.block ?? d.block, assign: meta.assign ?? false });
    const btn = this.el.querySelector('[data-act=save]');
    btn.disabled = true;
    const res = await this.api('POST', this.boot.urls.save, body);
    btn.disabled = false;
    if (!res) return null;
    if (isMedia) { d.mediaId = res.item.id; d.ref = 'media:' + res.item.id; d.shared = false; if (first) { d.name = meta.name; d.title = meta.title; d.alt = meta.alt; } }
    else { d.id = res.item.id; d.ref = 'graphic:' + res.item.id; d.name = res.item.name; d.block = res.item.block; }
    d.versions = res.versions || [];
    this.savedAt = this.hi;
    this.changed(false);
    toast(isMedia ? t('In der Mediathek gespeichert') : t('Gespeichert'));
    const result = { target: body.target, item: res.item, svg: body.svg, format: this.format };
    this.opts.onSave?.(result);
    this.emit('save', result);
    return result;
  }

  destroy() {
    document.removeEventListener('keydown', this.keyHandler);
    document.removeEventListener('keyup', this.keyUp);
    this.ro?.disconnect();
    closeMenus();
    this.el.remove();
  }
}

function localMatrixOf(el) {
  const list = el.transform?.baseVal;
  const m = list && list.numberOfItems ? list.consolidate()?.matrix : null;
  return m ? new DOMMatrix([m.a, m.b, m.c, m.d, m.e, m.f]) : new DOMMatrix();
}
function nextDrawn(el) { let n = el.nextElementSibling; while (n && !isDrawn(n)) n = n.nextElementSibling; return n; }
function prevDrawn(el) { let n = el.previousElementSibling; while (n && !isDrawn(n)) n = n.previousElementSibling; return n; }

export { typeLabel, spriteUrl, boundsOf, parseColor };
