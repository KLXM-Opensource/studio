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
  NS, num, docMatrix, parentMatrix, pt, fmtMatrix, boundsOf, union, corners, docBounds, decompose,
  parsePath, pathToString, toNodes, fromNodes, shapeToPath, simplify, smoothNodes, polygonPoints, transformPath,
} from './geom.js';
import { nearestSeg, insertNode, splitAt, joinEnds, nearestEnds, closeSub, reverseSub, nodeKind, setNodeKind } from './pathops.js';
import {
  isDrawn, drawnChildren, nameOf, typeLabel, isLocked, isHidden, getProp, setProp, eachShape, snapshot, restore,
  applyTransform, defs, uid, getText, setText, remapIds, remapClasses, paintEl,
} from './doc.js';
import { parseColor, hex } from './color.js';
import { h, iconBtn, menu, closeMenus, modal, MOD, ALT, SHIFT, isMac } from './ui.js';
import { buildPanels } from './panels.js';
import { TextEditor } from './textedit.js';
import { readText, layoutText, transformCase, styleRange, mapRange } from './text.js';
import { parseFeatures, fmtFeatures, paraOf, setPara, loadHyphens, preloadHyphens, setHyphenModules } from './typo.js';
import { fontPicker, fontItems, itemFor, WEIGHTS } from './fontpicker.js';
import { Connectors, isConn, parseConn, HEADS } from './connect.js';
import { booleanOp, simplifyD, outlineStroke, offsetArea, pathLike, makeCompound, recomputeCompound, isCompound } from './bool.js';
import { makeClip, releaseClip, clipOf } from './clip.js';
import { outlineAll } from './outline.js';
import { Guides, readGrid } from './guides.js';
import { GradientHandles } from './gradhandles.js';
import { optimizeSvg, checkSame, desktopSvg, pdfBlob } from './export.js';
import { SchemeView, suggestDark, paintedElements, bakeScheme } from './scheme.js';
import { checkOverlay, suggestAlt } from './check.js';

/** Größe lesbar (Byte → KB) */
const kb = n => n < 1024 ? n + ' B' : (n / 1024).toLocaleString(undefined, { maximumFractionDigits: n < 10240 ? 1 : 0 }) + ' KB';

/** Vererbte Darstellungseigenschaften (Gruppe aufheben, Kopieren aus einer Gruppe) – opacity wird multipliziert, nicht geerbt */
const INHERITED = ['fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin',
  'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor',
  'letter-spacing', 'color', 'visibility', 'clip-rule'];

/** Formen mit Knoten (Knoten-Werkzeug, Pfad-Befehle) */
const NODE_SHAPES = ['path', 'rect', 'ellipse', 'circle', 'polygon', 'polyline', 'line'];

const TOOLS = [
  { id: 'select', icon: 'cursor', key: 'V', label: () => t('Verschieben') },
  { id: 'node', icon: 'vector-two', key: 'A', label: () => t('Knoten') },
  { id: 'pen', icon: 'pen-nib', key: 'P', label: () => t('Zeichenstift') },
  { id: 'pencil', icon: 'pencil-simple-line', key: 'N', label: () => t('Bleistift') },
  { id: 'rect', icon: 'rectangle', key: 'R', label: () => t('Rechteck') },
  { id: 'ellipse', icon: 'circle', key: 'E', label: () => t('Ellipse') },
  { id: 'polygon', icon: 'polygon', key: 'G', label: () => t('Vieleck und Stern') },
  { id: 'line', icon: 'line-segment', key: 'L', label: () => t('Linie und Pfeil') },
  { id: 'connector', icon: 'flow-arrow', key: 'C', label: () => t('Verbinder') },
  { id: 'text', icon: 'text-t', key: 'T', label: () => t('Text') },
  { id: 'gradient', icon: 'gradient', key: 'F', label: () => t('Verlauf') },
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
  text: () => t('Klick = Text an dieser Stelle, Klick in eine Form = Text in der Form (zentriert, umbrochen). Esc oder Klick daneben beendet die Eingabe.'),
  connector: () => t('Von einer Form zur nächsten ziehen – die Enden haften an den Ankerpunkten (Seiten, Mitte = automatisch, Knoten). Verbinder folgen, wenn Formen sich bewegen. Doppelklick auf einen Verbinder = Beschriftung.'),
  eyedropper: () => t('Klick auf ein Objekt übernimmt seine Füllung und Kontur für die Auswahl.'),
  gradient: () => t('Ziehen auf einem Objekt = Verlauf anlegen bzw. neu ausrichten. Griffe ziehen, Doppelklick auf die Linie = Farbstopp, ⌫ = Stopp löschen. X = Füllung/Kontur.'),
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
    // persönliche Einstellungen (Core\UiPrefs „grafik“): Schrift-Favoriten, zuletzt verwendete Schriften, Farbmodus
    this.prefs = { fontFav: [], fontRecent: [], colorMode: 'hex', ...(boot.prefs || {}) };
    setHyphenModules(boot.modules?.hyphen);
    this.loadFonts();
    this.nodeSel = new Set();   // ausgewählte Knoten (Direktauswahl): "s:i"
    this.conn = new Connectors(this);   // Verbinder, Beschriftungen, Text in Formen (connect.js)
    this.opt.connRoute = 'ortho'; this.opt.connStart = 'none'; this.opt.connEnd = 'triangle'; this.opt.connSize = 4;
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
        <div class="ge-scheme" role="radiogroup" aria-label="${esc(t('Hell oder Dunkel'))}"><button type="button" role="radio" data-scheme="light" aria-checked="true" tabindex="0">${ico('sun')}<span>${esc(t('Hell'))}</span></button><button type="button" role="radio" data-scheme="dark" aria-checked="false" tabindex="-1">${ico('moon')}<span>${esc(t('Dunkel'))}</span></button><button type="button" role="radio" data-scheme="side" aria-checked="false" tabindex="-1">${ico('circle-half')}<span>${esc(t('Nebeneinander'))}</span></button></div>
        <button type="button" class="ge-zoombtn ge-bgbtn" data-act="schememenu" aria-haspopup="menu" aria-expanded="false" title="${esc(t('Hintergrund der Vorschau und Hilfen für Dunkel'))}"><span class="ge-bgbtn__sw" data-bgsw></span><span data-bglbl></span>${ico('caret-down')}</button>
        <div class="ge-doctitle"><strong data-name></strong><span class="ge-fmt">${esc(fmtLabel)}</span><span class="ge-dirty" data-dirty hidden>${esc(t('Ungespeichert'))}</span></div>
        <div class="ge-top__grow"></div>
        <div class="ge-top__group">${iconBtn('arrow-u-up-left', t('Rückgängig'), 'data-act="undo"', MOD + 'Z')}${iconBtn('arrow-u-up-right', t('Wiederholen'), 'data-act="redo"', SHIFT + MOD + 'Z')}</div>
        <button type="button" class="ge-zoombtn" data-act="objmenu" aria-haspopup="menu" aria-expanded="false">${esc(t('Objekt'))}${ico('caret-down')}</button>
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
    // Die Zeichenfläche erbt nichts von der Verwaltung (Schrift, Größe, Glättung, dunkle Textfarbe): Texte ohne eigene Schrift
    // sehen aus wie in der Mediathek bzw. auf der Website; currentColor = Tinte (wie das Farbfeld „Schriftfarbe“).
    // Eigene Eigenschaften (var(--token) der Design-Farben) erben weiter – all betrifft sie nicht.
    this.shadow.innerHTML = '<style>:host{all:initial;display:block;color:#1F2430}:host>svg{display:block;width:100%;height:100%;overflow:visible;-webkit-user-select:none;user-select:none}</style>';
    // Hell | Dunkel | Nebeneinander (scheme.js): eine Ansicht, keine Persona – das Dokument bleibt die helle Fassung mit Dunkel-Angaben
    // Hintergründe der Vorschau: zuerst neutral weiß (wie bisher), dann die Abschnitts-Hintergründe des Kits
    const secs = this.boot.sections || [];
    if (!secs.some(s => s.id === 'paper')) this.boot.sections = [{ id: 'paper', label: t('Weiß (neutral)'), light: '#FFFFFF', dark: secs[0]?.dark || '#111318' }, ...secs];
    this.sectionId = 'paper';
    this.sv = new SchemeView(this);
    this.renderPersonas();
    this.renderTools();
    this.rulers = new Guides(this);
    this.grad = new GradientHandles(this);
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
    this.selMark = [];
    if (label) { this.history = []; this.hi = -1; this.commit(label); this.savedAt = this.hi; }
    this.fitted = false;
    this.fit();
    preloadHyphens(root);
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
  /** Pfeiltasten-Schritt, der noch auf weitere Tasten wartet, sofort ins Protokoll (vor ⌘Z oder einem anderen Schritt) */
  flushNudge() {
    if (!this.nudgeT) return;
    clearTimeout(this.nudgeT);
    this.nudgeT = null;
    this.commit(this.nudgeLabel);
  }

  commit(label) {
    this.flushNudge();
    this.lastDown = null;
    this.conn?.update();
    const svg = this.getSvg();
    if (this.hi >= 0 && this.history[this.hi].svg === svg) return;
    this.history.length = this.hi + 1;
    // selBefore: Auswahl vor dem Schritt (Pfade im vorigen Stand) – Rückgängig wählt wieder, was vorher gewählt war
    this.history.push({ label, svg, sel: this.sel.map(el => this.pathOf(el)), selBefore: this.selMark || [], at: Date.now() });
    this.selMark = this.sel.map(el => this.pathOf(el));
    // höchstens 200 Schritte bzw. ca. 40 MB
    let total = this.history.reduce((s, x) => s + x.svg.length, 0);
    while (this.history.length > 200 || (total > 40e6 && this.history.length > 2)) { total -= this.history.shift().svg.length; if (this.savedAt >= 0) this.savedAt--; }
    this.hi = this.history.length - 1;
    this.changed();
  }

  goto(i) {
    this.flushNudge();
    if (i < 0 || i >= this.history.length || i === this.hi) return;
    this.finishPen(); this.endText();
    this.lastDown = null;
    const back = i < this.hi;
    const scopePath = this.scope && this.scope !== this.root ? this.pathOf(this.scope) : null;
    this.hi = i;
    const st = this.history[i];
    const parsed = document.importNode(new DOMParser().parseFromString(st.svg, 'image/svg+xml').documentElement, true);
    this.root.replaceWith(parsed);
    this.root = parsed;
    // betretene Gruppe bleibt betreten, solange es sie noch gibt
    const sc = scopePath && this.byPath(scopePath);
    this.scope = sc && ['g', 'switch'].includes(sc.localName) ? sc : parsed;
    this.sel = ((back && this.history[i + 1]?.selBefore) || st.sel).map(p => this.byPath(p)).filter(Boolean);
    if (this.sel[0] && !this.scope.contains(this.sel[0])) this.scope = parsed;
    this.selMark = this.sel.map(el => this.pathOf(el));
    this.layout();
    this.changed();
  }
  undo() { this.flushNudge(); this.goto(this.hi - 1); }
  redo() { this.flushNudge(); this.goto(this.hi + 1); }
  get dirty() { return this.hi !== this.savedAt; }

  pathOf(el) { const p = []; for (let n = el; n && n !== this.root && n.parentNode; n = n.parentNode) p.unshift([...n.parentNode.children].indexOf(n)); return p; }
  byPath(p) { let n = this.root; for (const i of p) { n = n?.children[i]; } return n && n !== this.root ? n : null; }

  /** Nach jeder Änderung: Bereiche, Überlagerung, Titel */
  changed(emit = true) {
    this.sel = this.sel.filter(el => el.isConnected && this.root.contains(el));
    // Originale einer zusammengesetzten Form verlassen (Esc, Klick daneben): neu berechnen
    if (this.compoundEdit && !this.compoundBusy && !this.compoundEdit.contains(this.scope)) queueMicrotask(() => this.endCompound());
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
    if (this.sv?.mode !== 'light') this.sv?.refresh();
    // Prüfung nachziehen, solange der Bereich offen ist oder Hinweise markiert sind
    if (this.panels && (this.panels.checkOpen() || this.checkIssues?.length)) this.panels.check.soon();
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
    this.sv?.fitTwin();
    this.draw();
  }

  /** Zweiter Druck auf dasselbe Element der Überlagerung kurz nacheinander (Doppelklick, auch wenn neu gezeichnet wurde) */
  second(key, e) {
    const now = Date.now(), last = this.lastDown;
    const dbl = !!last && last.key === key && now - last.at < 450 && Math.hypot(e.clientX - last.x, e.clientY - last.y) < 5;
    this.lastDown = dbl ? null : { key, at: now, x: e.clientX, y: e.clientY };
    return dbl;
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
    this.selMark = this.sel.map(el => this.pathOf(el));
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
    this.conn?.update();
    // Dunkel-Ansicht: neue Dunkel-Farben beim Ziehen eines Reglers sofort zeigen; Nebeneinander: Kopie rechts nachziehen
    if (this.sv?.mode === 'dark') this.sv.live();
    else if (this.sv?.mode === 'side') this.sv.updateTwin();
    const ov = this.ov, out = [];
    const r = this.stage.getBoundingClientRect();
    ov.setAttribute('viewBox', `0 0 ${r.width} ${r.height}`);
    // Raster und Hilfslinien (guides.js), Lineale
    out.push(this.rulers.overlay());
    this.rulers.draw();
    // Rahmen der betretenen Gruppe
    if (this.scope !== this.root) {
      const c = corners(this.scope, this.root);
      if (c) out.push(`<polygon class="ge-ov__scope" points="${c.map(p => { const q = this.toOv(p); return q.x + ',' + q.y; }).join(' ')}"/>`);
    }
    if (this.hover && !this.sel.includes(this.hover) && this.hover.isConnected) {
      const c = corners(this.hover, this.root);
      if (c) out.push(`<polygon class="ge-ov__hover" points="${c.map(p => { const q = this.toOv(p); return q.x + ',' + q.y; }).join(' ')}"/>`);
    }
    // Hinweise der Prüfung (check.js) für die gezeigte Fassung
    out.push(checkOverlay(this));
    if (this.tool === 'gradient') out.push(this.grad.overlay());
    else if (this.tool === 'node') out.push(this.drawNodes());
    else if (this.sel.length && !this.textEl && this.sel.every(isConn)) out.push(this.drawConnSel(this.tool === 'select'));
    else if (this.sel.length && !this.textEl) out.push(this.drawSelection());
    if (this.tool === 'connector' || this.connDrag) out.push(this.drawAnchors());
    if (this.guides?.length) for (const g of this.guides) {
      const a = this.toOv(g.a), b = this.toOv(g.b);
      out.push(`<line class="ge-ov__guide" x1="${a.x}" y1="${a.y}" x2="${b.x}" y2="${b.y}"/>`);
    }
    if (this.marquee) {
      const m = this.marquee;
      out.push(`<rect class="ge-ov__marquee" x="${Math.min(m.x0, m.x1)}" y="${Math.min(m.y0, m.y1)}" width="${Math.abs(m.x1 - m.x0)}" height="${Math.abs(m.y1 - m.y0)}"/>`);
    }
    if (this.pen) out.push(this.drawPen());
    if (this.textEd) out.push(this.textEd.draw());
    // Textrahmen: Breite gestrichelt, Griff rechts zum Ändern der Breite
    const tf = this.textEl || (this.sel.length === 1 && this.sel[0].localName === 'text' && this.tool === 'select' ? this.sel[0] : null);
    if (tf && tf.hasAttribute('data-ge-frame')) out.push(this.drawTextFrame(tf));
    ov.innerHTML = out.join('');
    for (const el of ov.querySelectorAll('[data-h]')) el.addEventListener('pointerdown', e => this.handleDown(e, el.dataset.h));
    // Doppelklick auf Überlagerungs-Elemente: der erste Klick zeichnet die Überlagerung neu, ein dblclick-Ereignis käme dann
    // nicht mehr an – deshalb zweiten Druck auf dasselbe Element erkennen
    for (const el of ov.querySelectorAll('[data-node]')) el.addEventListener('pointerdown', e => this.second('n' + el.dataset.node, e) ? this.nodeToggle(e, el.dataset.node) : this.nodeDown(e, el.dataset.node));
    for (const el of ov.querySelectorAll('[data-gh]')) el.addEventListener('pointerdown', e => this.grad.down(e, el.dataset.gh));
    for (const el of ov.querySelectorAll('[data-ch]')) el.addEventListener('pointerdown', e => this.connHandleDown(e, el.dataset.ch));
    for (const el of ov.querySelectorAll('[data-guide]')) {
      el.addEventListener('pointerdown', e => { if (e.button !== 0 || this.tool === 'hand' || this.space) return; e.stopPropagation(); e.preventDefault(); this.rulers.dragGuide(e, +el.dataset.guide); });
      el.addEventListener('dblclick', e => { e.stopPropagation(); this.rulers.dialog(+el.dataset.guide); });
    }
  }

  /** Rahmen eines Textrahmens (Breite data-ge-frame) mit Griff rechts */
  drawTextFrame(el) {
    let b; try { b = el.getBBox(); } catch { return ''; }
    const w = parseFloat(el.getAttribute('data-ge-frame')) || 0, x = parseFloat((el.getAttribute('x') || '0').split(/[\s,]+/)[0]) || 0;
    const M = docMatrix(el, this.root), S = (px, py) => this.toOv(pt(M, px, py));
    const y0 = b.y, y1 = b.y + Math.max(b.height, 1);
    const c = [S(x, y0), S(x + w, y0), S(x + w, y1), S(x, y1)];
    const hp = S(x + w, (y0 + y1) / 2);
    return `<polygon class="ge-ov__tframe" points="${c.map(p => p.x + ',' + p.y).join(' ')}"/><rect class="ge-ov__h ge-ov__tfh" data-h="tfw" x="${hp.x - 4}" y="${hp.y - 8}" width="8" height="16" rx="2"/>`;
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
      if (this.tool === 'select' || this.tool === 'node' || this.tool === 'connector') {
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
    st.addEventListener('dragover', e => { if ([...e.dataTransfer.types].includes('Files')) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; } });
    st.addEventListener('drop', e => {
      // Dateien: SVG wird importiert, Pixelbilder abgelehnt (nur Vektoren)
      const f = e.dataTransfer.files?.[0];
      if (f) {
        e.preventDefault();
        if (/svg/i.test(f.type) || /[.]svg$/i.test(f.name)) { const at = this.toDoc(e.clientX, e.clientY); f.text().then(tx => this.importSvg(tx, f.name.replace(/[.]svg$/i, ''), { at })); }
        else toast(t('Der Grafik-Editor arbeitet nur mit Vektoren – Pixelbilder (PNG, JPG …) lassen sich nicht einfügen.'), 'warn');
        return;
      }
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
    if (this.textEd) {
      // Klick in den bearbeiteten Text: Schreibmarke/Auswahl; daneben: Bearbeitung beenden
      if (this.textEd.contains(e.clientX, e.clientY)) { this.textEd.pointerDown(e); return; }
      this.endText();
      if (this.tool === 'text') return;
    }
    this.stage.focus({ preventScroll: true });
    const p = this.toDoc(e.clientX, e.clientY);
    switch (this.tool) {
      case 'select': return this.selectDown(e, p);
      case 'node': return this.nodeStageDown(e, p);
      case 'pen': return this.penDown(e, p);
      case 'pencil': return this.pencilDown(e, p);
      case 'rect': case 'ellipse': case 'polygon': case 'line': return this.shapeDown(e, p);
      case 'text': return this.textDown(e, p);
      case 'connector': return this.connDown(e, p);
      case 'eyedropper': return this.eyedrop(e);
      case 'gradient': return this.grad.stageDown(e, p);
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
    // ⇧: Klick nimmt hinzu bzw. heraus, ⇧+Ziehen verschiebt waagerecht/senkrecht (wie Illustrator/Affinity)
    if (e.shiftKey) {
      if (!this.sel.includes(hit)) { this.select([...this.sel, hit]); this.moveDrag(e, p); }
      else this.moveDrag(e, p, () => this.select(this.sel.filter(x => x !== hit)));
      return;
    }
    // Klick ohne Ziehen auf ein Objekt einer Mehrfachauswahl wählt nur dieses; Ziehen verschiebt alle
    const wasIn = this.sel.includes(hit), many = this.sel.length > 1;
    if (!wasIn) this.select([hit]);
    this.moveDrag(e, p, wasIn && many ? () => this.select([hit]) : null);
  }

  moveDrag(e, p0, onClick = null) {
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
      else if (onClick) onClick();
      else this.draw();
    });
  }

  /** Griffe: Größe (8) und Drehen */
  handleDown(e, k) {
    e.stopPropagation(); e.preventDefault();
    if (k === 'tfw') return this.frameWidthDrag(e);
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

  /** Breite eines Textrahmens ziehen – die Zeilen brechen sofort neu um */
  frameWidthDrag(e) {
    const el = this.textEl || this.sel[0];
    if (!el || el.localName !== 'text') return;
    const Mi = docMatrix(el, this.root).inverse();
    const x = parseFloat((el.getAttribute('x') || '0').split(/[\s,]+/)[0]) || 0;
    const fs = parseFloat(getProp(el, 'font-size')) || 16;
    this.track(e, p => {
      const q = pt(Mi, p.x, p.y);
      this.setTextFrame(el, Math.max(fs, q.x - x), false);
      this.draw();
    }, () => this.commit(t('Textrahmen geändert')));
  }

  /** Textrahmen setzen (Breite in Einheiten des Textes; 0 = Punkttext ohne Umbruch) und neu setzen */
  setTextFrame(el, w, commit = true) {
    if (w > 0) el.setAttribute('data-ge-frame', num(w, 2)); else el.removeAttribute('data-ge-frame');
    this.retext(el);
    if (commit) this.commit(w > 0 ? t('Textrahmen geändert') : t('Textrahmen entfernt'));
  }

  /** Text nach Änderung von Schrift, Größe, Rahmen neu setzen (Umbruch, Zeilenabstand) */
  retext(el) {
    if (this.textEd && this.textEd.el === el) { this.textEd.dirty = true; this.textEd.before = null; this.textEd.relayout(); this.textEd.place(); return; }
    layoutText(el, readText(el));
  }

  dbl(e) {
    if (this.tool !== 'select') return;
    const hit = this.hitAt(e);
    if (!hit) return;
    if (isCompound(hit.el)) { this.editCompound(hit.el); return; }
    // Baustein mit Text in der Form (Flussdiagramm, Organigramm): Doppelklick bearbeitet gleich den Text
    if (hit.el.localName === 'g') {
      const inner = [...hit.el.querySelectorAll('text[data-ge-in]')];
      const t1 = inner.find(x => x.contains(hit.raw)) || (inner.length === 1 ? inner[0] : null);
      if (t1) { this.scope = hit.el; return this.beginText(t1, false, { clientX: e.clientX, clientY: e.clientY }); }
    }
    if (['g', 'switch'].includes(hit.el.localName)) {
      // Gruppe betreten (wie in Vektorprogrammen): danach wählt ein Klick die Teile
      this.scope = hit.el;
      const inner = this.pickable(hit.raw);
      this.select(inner ? [inner] : []);
      this.hint(t('In der Gruppe – Esc oder Klick daneben verlässt sie.'));
      return;
    }
    if (hit.el.localName === 'text') return this.beginText(hit.el, false, { clientX: e.clientX, clientY: e.clientY });
    if (isConn(hit.el)) return this.connLabel(hit.el);
    if (['path', 'rect', 'ellipse', 'circle', 'polygon', 'polyline', 'line'].includes(hit.el.localName)) { this.select([hit.el]); this.setTool('node'); }
  }

  // ---------------------------------------------------------------- Einrasten, Hilfslinien
  snapTargets(exclude) {
    const xs = [], ys = [];
    const vb = this.vb;
    xs.push(vb.x, vb.x + vb.w / 2, vb.x + vb.w); ys.push(vb.y, vb.y + vb.h / 2, vb.y + vb.h);
    const g = this.rulers.targets();
    xs.unshift(...g.xs); ys.unshift(...g.ys);
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
    if (this.opt.grid) { dx = this.rulers.snapGrid(b0.x + dx) - b0.x; dy = this.rulers.snapGrid(b0.y + dy) - b0.y; }
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
    if (this.opt.grid) { q.x = this.rulers.snapGrid(q.x); q.y = this.rulers.snapGrid(q.y); }
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
    if (isConn(el)) return this.conn.applyHeads(el);   // Farbe geändert: Spitzen der Verbinder nachziehen
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
  /** Text-Werkzeug: Klick = Punkttext, Ziehen = Textrahmen (Zeilen brechen auf die Breite um), Klick in Text = bearbeiten */
  textDown(e, p0) {
    e.preventDefault();   // kein mousedown-Fokus auf die Zeichenfläche – die Eingabe bekommt ihn
    const hit = this.hitAt(e)?.el;
    if (hit && hit.localName === 'text') return this.beginText(hit, false, { clientX: e.clientX, clientY: e.clientY });
    // Klick in eine geschlossene Form: Text in der Form (zentriert, umbricht auf die Breite der Form)
    if (hit && ['rect', 'ellipse', 'circle', 'polygon', 'path'].includes(hit.localName) && !isConn(hit) && (getProp(hit, 'fill') || 'none') !== 'none') {
      const inner = this.conn.innerText(hit, '');
      return this.beginText(inner, true);
    }
    const r = this.stage.getBoundingClientRect();
    let box = null;
    this.track(e, (p, ev) => {
      if (Math.hypot(ev.clientX - e.clientX, ev.clientY - e.clientY) < 6) { this.marquee = null; box = null; this.draw(); return; }
      this.marquee = { x0: e.clientX - r.left, y0: e.clientY - r.top, x1: ev.clientX - r.left, y1: ev.clientY - r.top };
      box = { x: Math.min(p0.x, p.x), y: Math.min(p0.y, p.y), w: Math.abs(p.x - p0.x) };
      this.draw();
    }, () => {
      this.marquee = null;
      const at = box ? { x: box.x, y: box.y } : p0;
      const q = this.local(at), fs = this.opt.fontSize;
      const el = this.create('text', { x: num(q.x), y: num(q.y + fs * 0.8), 'font-size': num(fs), 'font-family': this.opt.fontFamily,
        'font-weight': this.opt.fontWeight !== '400' ? this.opt.fontWeight : null, 'text-anchor': this.opt.anchor !== 'start' && !box ? this.opt.anchor : null }, false);
      if (box) el.setAttribute('data-ge-frame', num(Math.max(fs, box.w / (decompose(docMatrix(this.target, this.root)).sx || 1)), 2));
      el.setAttribute('fill', this.style.fill !== 'none' ? this.style.fill : (this.format === 'icon' ? 'currentColor' : '#1F2430'));
      el.textContent = '';
      this.beginText(el, true);
    });
  }

  /** Text bearbeiten (textedit.js): at = Bildschirmpunkt für die Schreibmarke, sonst alles auswählen */
  beginText(el, fresh = false, at = null) {
    if (this.textEd) this.endText();
    this.sel = [el];
    this.textEl = el;
    this.textEd = new TextEditor(this, el, { at, fresh });
    this.hint(t('Text bearbeiten: Tippen, Ziehen = auswählen, {mod}B/I/U = fett/kursiv/unterstrichen, Farbe wirkt auf die Auswahl. Esc beendet.', { mod: MOD }));
    this.changed(false);
  }

  endText() {
    const te = this.textEd, el = this.textEl;
    if (!te || !el) { this.textEl = null; return; }
    this.textEd = null; this.textEl = null;
    const changed = te.end();
    this.hint();
    if (!el.textContent.trim()) { el.remove(); this.sel = []; this.changed(false); return; }
    if (te.fresh || changed) { this.sel = [el]; this.commit(te.fresh ? t('Text') : t('Text geändert')); }
    else this.changed(false);
  }

  // ---------------------------------------------------------------- Verbinder (connect.js)
  /** Ziel unter dem Zeiger: Form und nächster Ankerpunkt (Bildschirmabstand ≤ 14 px), sonst Form mit „automatisch“ */
  connTarget(e, exclude = null) {
    const list = this.shadow.elementsFromPoint ? this.shadow.elementsFromPoint(e.clientX, e.clientY) : [];
    let el = null;
    for (const raw of list) { const p = this.pickable(raw); if (p && p !== exclude && !isConn(p) && !p.hasAttribute?.('data-ge-for')) { el = p; break; } }
    // Ankerpunkte der zuletzt gezeigten Form gelten auch knapp außerhalb (Seitenmitten liegen auf dem Rand)
    const cand = el ? [el] : (this.hover && this.hover !== exclude && !isConn(this.hover) ? [this.hover] : []);
    const r = this.stage.getBoundingClientRect(), mx = e.clientX - r.left, my = e.clientY - r.top;
    for (const c of cand) {
      let best = null;
      for (const a of this.conn.anchors(c)) {
        const q = this.toOv(a.p), d = Math.hypot(q.x - mx, q.y - my);
        if (d <= 14 && (!best || d < best.d)) best = { ...a, d };
      }
      if (best) return { el: c, side: best.side };
      if (c === el) return { el: c, side: 'a' };
    }
    return null;
  }

  /** Verbinder-Werkzeug: von einer Form (bzw. einem Punkt) zur nächsten ziehen */
  connDown(e, p0) {
    const start = this.connTarget(e);
    const endOf = (tg, p) => tg ? { id: this.ensureId(tg.el), side: tg.side } : { x: this.snapPoint(p).x, y: this.snapPoint(p).y };
    const a = endOf(start, p0);
    let el = null;
    this.connDrag = { from: start?.el || null };
    this.track(e, (p, ev) => {
      if (!el) {
        if (Math.hypot(ev.clientX - e.clientX, ev.clientY - e.clientY) < 5) return;
        el = this.conn.create(a, { x: p.x, y: p.y }, { r: this.opt.connRoute, hs: this.opt.connStart, he: this.opt.connEnd, s: this.opt.connSize });
      }
      const tg = this.connTarget(ev, start?.el);
      this.connDrag.to = tg;
      this.hover = tg?.el || null;
      this.conn.setEnd(el, 'b', endOf(tg, p));
      this.draw();
    }, () => {
      this.connDrag = null;
      if (!el) return;
      this.sel = [el];
      this.commit(t('Verbinder'));
    });
  }

  /** id für eine Form (Verbinder verweisen per id) */
  ensureId(el) { if (!el.id) el.id = uid(this.root, el.localName === 'g' ? 'gruppe-' : 'form-'); return el.id; }

  /** Ankerpunkte der Form unter dem Zeiger (bzw. Ziel beim Ziehen) */
  drawAnchors() {
    const els = new Set();
    if (this.hover && !isConn(this.hover) && this.root.contains(this.hover)) els.add(this.hover);
    if (this.connDrag?.from) els.add(this.connDrag.from);
    let s = '';
    for (const el of els) {
      const c = corners(el, this.root);
      if (c) s += `<polygon class="ge-ov__hover" points="${c.map(p => { const q = this.toOv(p); return q.x + ',' + q.y; }).join(' ')}"/>`;
      for (const a of this.conn.anchors(el)) {
        const q = this.toOv(a.p), on = this.connDrag?.to?.el === el && this.connDrag.to.side === a.side;
        s += a.side === 'a' ? `<circle class="ge-ov__anc ge-ov__anc--auto${on ? ' is-on' : ''}" cx="${q.x}" cy="${q.y}" r="${on ? 7 : 5}"/>`
          : `<rect class="ge-ov__anc${a.magnet ? ' ge-ov__anc--mag' : ''}${on ? ' is-on' : ''}" x="${q.x - (on ? 5 : 4)}" y="${q.y - (on ? 5 : 4)}" width="${on ? 10 : 8}" height="${on ? 10 : 8}" transform="rotate(45 ${q.x} ${q.y})"/>`;
      }
    }
    return s;
  }

  /** Auswahl aus Verbindern: Linie hervorgehoben, Griffe an den Enden, Griff für den Knick (rechtwinklig) */
  drawConnSel(handles = true) {
    let s = '';
    for (const c of this.sel) {
      const g = c.__ge;
      if (!g) continue;
      const A = this.toOv(g.A), B = this.toOv(g.B);
      try {
        const M = docMatrix(c, this.root), len = c.getTotalLength(), pts = [];
        for (let i = 0; i <= 40; i++) { const q = c.getPointAtLength(len * i / 40); pts.push(this.toOv(pt(M, q.x, q.y))); }
        s += `<polyline class="ge-ov__connsel" points="${pts.map(p => p.x + ',' + p.y).join(' ')}"/>`;
      } catch { /* nicht gezeichnet */ }
      // Griffe nur mit dem Auswahlwerkzeug (mit dem Verbinder-Werkzeug beginnt dort ein neuer Verbinder)
      if (!handles || this.sel.length > 1 || isLocked(c)) continue;
      const o = parseConn(c);
      s += `<circle class="ge-ov__h ge-ov__cend${o.a?.id ? ' is-attached' : ''}" data-ch="a" cx="${A.x}" cy="${A.y}" r="6"/><circle class="ge-ov__h ge-ov__cend${o.b?.id ? ' is-attached' : ''}" data-ch="b" cx="${B.x}" cy="${B.y}" r="6"/>`;
      if (o.r === 'ortho' && g.pts && g.pts.length >= 4) {
        // mittlerer Abschnitt: Griff in seiner Mitte
        const i = Math.floor((g.pts.length - 1) / 2), p1 = g.pts[i], p2 = g.pts[i + 1];
        const q = this.toOv({ x: (p1.x + p2.x) / 2, y: (p1.y + p2.y) / 2 }), vert = Math.abs(p1.x - p2.x) < 0.01;
        s += `<rect class="ge-ov__h ge-ov__cmid ${vert ? 'is-v' : 'is-h'}" data-ch="m" x="${q.x - (vert ? 4 : 8)}" y="${q.y - (vert ? 8 : 4)}" width="${vert ? 8 : 16}" height="${vert ? 16 : 8}" rx="3"/>`;
      }
    }
    return s;
  }

  /** Griff eines Verbinders ziehen: Ende neu anhängen bzw. Knick verschieben */
  connHandleDown(e, k) {
    e.stopPropagation(); e.preventDefault();
    const c = this.sel[0];
    if (!c || !isConn(c)) return;
    if (k === 'm') {
      const g = c.__ge, i = Math.floor((g.pts.length - 1) / 2), p1 = g.pts[i], p2 = g.pts[i + 1];
      const vert = Math.abs(p1.x - p2.x) < 0.01;
      const A = g.A, B = g.B;
      this.track(e, p => {
        const m = vert ? (p.x - A.x) / ((B.x - A.x) || 1) : (p.y - A.y) / ((B.y - A.y) || 1);
        this.conn.set(c, { m: Math.max(0, Math.min(1, m)) });
        this.draw();
      }, () => this.commit(t('Knick verschoben')));
      return;
    }
    this.connDrag = { from: null };
    this.track(e, (p, ev) => {
      const other = parseConn(c)[k === 'a' ? 'b' : 'a'];
      const tg = this.connTarget(ev, other?.id ? this.conn.byId(other.id) : null);
      this.connDrag.to = tg;
      this.hover = tg?.el || null;
      const sp = this.snapPoint(p);
      this.conn.setEnd(c, k, tg ? { id: this.ensureId(tg.el), side: tg.side } : { x: sp.x, y: sp.y });
      this.draw();
    }, () => { this.connDrag = null; this.commit(t('Verbinder umgehängt')); });
  }

  /** Beschriftung eines Verbinders anlegen bzw. bearbeiten */
  connLabel(c) {
    const isNew = !this.root.querySelector(`text[data-ge-for="${CSS.escape(c.id || '-')}"]`);
    const lab = this.conn.label(c, isNew ? '' : undefined);
    this.beginText(lab, isNew);
  }

  /** Einstellungen der ausgewählten Verbinder (Kontextleiste) bzw. Vorgabe für neue */
  connSet(patch) {
    const list = this.sel.filter(isConn);
    for (const [k, v] of Object.entries(patch)) this.opt[{ r: 'connRoute', hs: 'connStart', he: 'connEnd', s: 'connSize' }[k]] = v;
    if (!list.length) { this.renderCtx(true); return; }
    for (const c of list) this.conn.set(c, patch);
    this.commit(t('Verbinder geändert'));
  }

  /** Verschieben um tx/ty (Zeichenflächen-Koordinaten) – z. B. Baum anordnen */
  moveBy(el, tx, ty) { applyTransform(el, new DOMMatrix().translate(tx, ty), this.root); }

  /** Organigramm/Baum anordnen: ausgewählte Wurzel und alles, was über Verbinder daran hängt */
  arrangeTree(dir = 'down') {
    const rootEl = this.sel.find(el => !isConn(el));
    if (!rootEl || !rootEl.id) { toast(t('Bitte die oberste Form des Baums auswählen (mit Verbindern zu den übrigen).'), 'info'); return; }
    const moved = this.conn.layoutTree(rootEl, dir, this.format === 'info' ? 40 : 6);
    if (!moved.length) { toast(t('Nichts anzuordnen – an der Form hängen keine Verbinder.'), 'info'); return; }
    this.commit(dir === 'down' ? t('Baum angeordnet (von oben)') : t('Baum angeordnet (von links)'));
  }

  // ---------------------------------------------------------------- Pipette
  eyedrop(e) {
    const hit = this.hitAt(e);
    const src = hit?.raw && this.root.contains(hit.raw) ? hit.raw : null;
    if (!src) return;
    const fill = getProp(src, 'fill'), stroke = getProp(src, 'stroke'), sw = getProp(src, 'stroke-width');
    if (!this.sel.length) { this.style.fill = fill; this.style.stroke = stroke; this.paintFs(); this.panels.refresh(); return; }
    for (const el of this.sel) eachShape(el, s => {
      setProp(s, 'fill', fill); setProp(s, 'stroke', stroke); if (stroke && stroke !== 'none') setProp(s, 'stroke-width', sw);
      // Deckkraft von Füllung und Kontur gehört zur Farbe (sonst wird aus 50 % Rot volles Rot)
      for (const a of ['fill-opacity', 'stroke-opacity']) setProp(s, a, getProp(src, a, false) || null);
      // Farbe des Designs (Token) wandert mit
      for (const a of ['data-ge-ftoken', 'data-ge-stoken']) { const v = src.getAttribute(a); if (v) s.setAttribute(a, v); else s.removeAttribute(a); }
    });
    this.commit(t('Farbe übernommen'));
  }

  // ================================================================= Direktauswahl (Knoten)
  nodeStageDown(e, p) {
    // Klick auf ein Segment der gewählten Kurve: Knoten einfügen (Form bleibt exakt) und gleich ziehbar
    const cur = this.sel[0];
    if (cur && !isLocked(cur) && NODE_SHAPES.includes(cur.localName) && !e.shiftKey) {
      const nm = this.nodeModel();
      const near = nm && nearestSeg(nm.subs, p, nm.M);
      if (near && near.d * this.zoom <= 5) {
        const el = this.toPath(cur);
        const subs = toNodes(parsePath(el.getAttribute('d')));
        const ni = insertNode(subs[near.si], near.k, near.t);
        el.setAttribute('d', pathToString(fromNodes(subs)));
        this.nodeSel = new Set([near.si + ':' + ni]);
        return this.nodeDown(e, near.si + ':' + ni, t('Knoten eingefügt'));
      }
    }
    const hit = this.hitAt(e);
    const raw = hit?.raw;
    if (raw && NODE_SHAPES.includes(raw.localName) && !isLocked(hit.el)) {
      if (this.sel[0] !== raw) { this.sel = [raw]; this.selMark = [this.pathOf(raw)]; this.nodeSel.clear(); this.changed(false); }
      return;
    }
    if (!e.shiftKey) { this.nodeSel.clear(); if (!raw) { this.sel = []; this.selMark = []; } this.changed(false); }
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

  /** Knoten bzw. Griff ziehen. label: Schritt im Protokoll auch ohne Bewegung (z. B. „Knoten eingefügt“) */
  nodeDown(e, key, label = '') {
    e.stopPropagation(); e.preventDefault();
    const [si, ni, hk] = key.split(':');
    const k = si + ':' + ni;
    if (!hk) {
      if (e.shiftKey) { this.nodeSel.has(k) ? this.nodeSel.delete(k) : this.nodeSel.add(k); }
      else if (!this.nodeSel.has(k)) { this.nodeSel.clear(); this.nodeSel.add(k); }
      this.renderCtx();
    }
    const el = this.toPath(this.sel[0]);
    const base = el.getAttribute('d');
    const Mi = docMatrix(el, this.root).inverse();
    const p0 = pt(Mi, this.toDoc(e.clientX, e.clientY).x, this.toDoc(e.clientX, e.clientY).y);
    let moved = false;
    // Art des Knotens beim Start: glatt/symmetrisch → Gegengriff folgt; Ecke mit Griffen → Griffe bleiben getrennt
    const kind0 = hk ? nodeKind(toNodes(parsePath(base))[si]?.nodes[ni] || {}) : '';
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
        // glatter Knoten: Gegengriff in einer Linie (symmetrisch: gleich lang); ⌥ = Griffe trennen (Ecke)
        const other = n[hk === 'i' ? 'o' : 'i'];
        if (other && !ev.altKey && (kind0 === 'smooth' || kind0 === 'symmetric')) {
          const l = kind0 === 'symmetric' ? Math.hypot(hd.x - n.x, hd.y - n.y) : Math.hypot(other.x - n.x, other.y - n.y), a = Math.atan2(n.y - hd.y, n.x - hd.x);
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
    }, () => { if (moved || label) this.commit(moved ? t('Knoten bearbeitet') : label); else this.draw(); });
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

  /**
   * Pfad-Befehle (Kontextleiste des Knoten-Werkzeugs, Menü „Objekt“) auf der ersten Auswahl:
   * kind:corner|smooth|symmetric, split (an Knoten auftrennen), join (Endpunkte verbinden), close, reverse, separate (Teilpfade trennen)
   */
  pathCmd(cmd) {
    const el0 = this.sel[0];
    if (!el0 || !NODE_SHAPES.includes(el0.localName) || isLocked(el0)) return;
    const el = this.toPath(el0);
    let subs = toNodes(parsePath(el.getAttribute('d')));
    const keys = [...this.nodeSel].map(k => k.split(':').map(Number)).filter(([a, b]) => subs[a]?.nodes[b]);
    let label = '';
    if (cmd.startsWith('kind:')) {
      if (!keys.length) return;
      for (const [a, b] of keys) setNodeKind(subs[a], b, cmd.slice(5));
      label = t('Knotenart geändert');
    } else if (cmd === 'split') {
      if (!keys.length) { toast(t('Bitte zuerst einen Knoten auswählen.'), 'info'); return; }
      const bySub = new Map();
      for (const [a, b] of keys) bySub.set(a, [...(bySub.get(a) || []), b]);
      for (const si of [...bySub.keys()].sort((x, y) => y - x)) {
        let sub = subs[si], idx = [...new Set(bySub.get(si))].sort((x, y) => x - y);
        const n = sub.nodes.length;
        if (sub.closed) {
          // erst am ersten Knoten öffnen (Anfang = dieser Knoten), die übrigen Indizes verschieben sich mit
          const k0 = idx.shift();
          sub = splitAt(sub, k0)[0];
          idx = idx.map(i => (i - k0 + n) % n);
        }
        let parts = [sub];
        for (const ni of idx.sort((x, y) => y - x)) parts = [...splitAt(parts[0], ni), ...parts.slice(1)];
        subs.splice(si, 1, ...parts);
      }
      this.nodeSel.clear();
      label = t('Pfad aufgetrennt');
    } else if (cmd === 'join') {
      if (keys.length === 2) {
        if (!joinEnds(subs, keys[0], keys[1])) { toast(t('Verbinden geht nur mit zwei Endpunkten offener Pfade.'), 'info'); return; }
      } else {
        const open = subs.filter(s => !s.closed);
        if (!open.length) { toast(t('Der Pfad ist schon geschlossen.'), 'info'); return; }
        if (open.length === 1) closeSub(open[0]);
        else { const b = nearestEnds(subs); if (b) joinEnds(subs, b.a, b.b); }
      }
      this.nodeSel.clear();
      label = t('Verbunden');
    } else if (cmd === 'close') {
      const only = new Set(keys.map(k => subs[k[0]]));
      subs.forEach(sub => { if (!only.size || only.has(sub)) closeSub(sub); });
      label = t('Pfad geschlossen');
    } else if (cmd === 'reverse') {
      subs.forEach(reverseSub);
      this.nodeSel.clear();
      label = t('Richtung umgekehrt');
    } else if (cmd === 'separate') {
      if (subs.length < 2) return;
      const out = [el];
      let after = el;
      subs.slice(1).forEach((sub, i) => {
        const c = el.cloneNode(false);
        c.removeAttribute('id');
        if (c.getAttribute('data-name')) c.setAttribute('data-name', c.getAttribute('data-name') + ' ' + (i + 2));
        c.setAttribute('d', pathToString(fromNodes([sub])));
        after.after(c); after = c; out.push(c);
      });
      subs = subs.slice(0, 1);
      el.setAttribute('d', pathToString(fromNodes(subs)));
      this.nodeSel.clear();
      this.sel = out;
      this.commit(t('Teilpfade getrennt'));
      return;
    } else return;
    el.setAttribute('d', pathToString(fromNodes(subs)));
    this.commit(label);
  }

  /** Mehrere Kurven zu einer verbinden (Auswahl-Werkzeug): Teilpfade in die unterste, offene Enden der Reihe nach verbunden */
  joinPaths() {
    const list = this.ordered(this.sel.filter(el => NODE_SHAPES.includes(el.localName) && !isLocked(el)));
    if (list.length < 2) return;
    const base = this.toPath(list[0]);
    const Bi = docMatrix(base, this.root).inverse();
    const subs = toNodes(parsePath(base.getAttribute('d')));
    for (const el of list.slice(1)) {
      const d = el.localName === 'path' ? el.getAttribute('d') : shapeToPath(el);
      if (!d) continue;
      subs.push(...toNodes(transformPath(parsePath(d), Bi.multiply(docMatrix(el, this.root)))));
      el.remove();
    }
    for (let guard = 0; guard < 200 && subs.filter(s => !s.closed).length > 1; guard++) {
      const b = nearestEnds(subs);
      if (!b || !joinEnds(subs, b.a, b.b)) break;
    }
    base.setAttribute('d', pathToString(fromNodes(subs)));
    this.sel = [base];
    this.commit(t('Pfade verbunden'));
  }

  /** Boolesche Operationen: unite | subtract | intersect | exclude | divide (bool.js, Paper.js nachgeladen) */
  async bool(op) {
    let list = this.ordered(this.sel.filter(el => !isLocked(el)));
    if (list.length < 2) { toast(t('Für diese Funktion bitte mindestens zwei Formen auswählen (Rechtecke, Ellipsen, Kurven oder Gruppen davon).'), 'info'); return; }
    if (list.some(el => el.localName === 'text' || el.querySelector?.('text'))) {
      list = await this.outlineTexts(list);
      if (!list) return;
    }
    this.busy(true);
    try {
      const r = await booleanOp(this, op, list);
      if (r.error) { toast(r.error, 'warn'); return; }
      this.sel = r.els;
      this.commit({ unite: t('Vereint'), subtract: t('Abgezogen'), intersect: t('Schnittmenge'), exclude: t('Ausschluss'), divide: t('Geteilt') }[op]);
    } catch (e) {
      console.error(e);
      toast(t('Die Geometrie-Funktionen konnten nicht geladen werden.'), 'error');
    } finally { this.busy(false); }
  }

  /** Zusammengesetzte Form (zerstörungsfrei): Originale bleiben als verborgene Gruppe, das Ergebnis wird berechnet */
  async compound(op) {
    const list = this.ordered(this.sel.filter(el => !isLocked(el)));
    if (list.length < 2) { toast(t('Für diese Funktion bitte mindestens zwei Formen auswählen (Rechtecke, Ellipsen, Kurven oder Gruppen davon).'), 'info'); return; }
    this.busy(true);
    try {
      const r = await makeCompound(this, op, list);
      if (r.error) { toast(r.error, 'warn'); return; }
      this.sel = [r.el];
      this.commit(t('Zusammengesetzte Form'));
    } catch (e) { console.error(e); toast(t('Die Geometrie-Funktionen konnten nicht geladen werden.'), 'error'); } finally { this.busy(false); }
  }

  /** Originale einer zusammengesetzten Form bearbeiten (Doppelklick): Originale sichtbar, Ergebnis ausgeblendet */
  editCompound(g) {
    const src = [...g.children].find(c => c.getAttribute('data-ge-src') === '1'), res = [...g.children].find(c => c.getAttribute('data-ge-result') === '1');
    if (!src) return;
    src.removeAttribute('display'); res?.setAttribute('display', 'none');
    this.compoundEdit = g;
    this.scope = src;
    this.select(drawnChildren(src).slice(-1));
    this.hint(t('Originale bearbeiten – Esc oder Klick daneben berechnet die zusammengesetzte Form neu.'));
  }

  /** Bearbeitung der Originale beenden: neu berechnen, Originale wieder verborgen */
  async endCompound() {
    const g = this.compoundEdit;
    if (!g || this.compoundBusy) return;
    this.compoundEdit = null;
    this.compoundBusy = true;
    try {
      const src = [...g.children].find(c => c.getAttribute('data-ge-src') === '1');
      if (this.scope === src || src?.contains(this.scope)) this.scope = this.root;
      src?.setAttribute('display', 'none');
      const r = g.isConnected ? await recomputeCompound(this, g) : null;
      [...g.children].filter(c => c.getAttribute('data-ge-result') === '1').forEach(c => c.removeAttribute('display'));
      if (r?.error) toast(r.error, 'warn');
      this.sel = g.isConnected ? [g] : [];
      this.hint();
      this.commit(t('Zusammengesetzte Form geändert'));
    } finally { this.compoundBusy = false; }
  }

  /** Operation einer zusammengesetzten Form wechseln bzw. in einen fertigen Pfad umwandeln */
  async compoundSet(g, op) {
    if (op === 'flatten') {
      const res = [...g.children].find(c => c.getAttribute('data-ge-result') === '1');
      if (!res) return;
      res.removeAttribute('data-ge-result');
      if (g.getAttribute('data-name') && !res.getAttribute('data-name')) res.setAttribute('data-name', g.getAttribute('data-name'));
      g.replaceWith(res);
      this.sel = [res];
      this.commit(t('In Pfad umgewandelt'));
      return;
    }
    g.setAttribute('data-ge-bool', op);
    this.busy(true);
    try { const r = await recomputeCompound(this, g); if (r.error) toast(r.error, 'warn'); } finally { this.busy(false); }
    this.commit(t('Zusammengesetzte Form geändert'));
  }

  /** Kontur in Fläche umwandeln (für Stanz-/Schneide-Optik und Logos) */
  async strokeToFill() {
    const list = this.sel.filter(el => NODE_SHAPES.includes(el.localName) && !isLocked(el));
    if (!list.length) { toast(t('Bitte eine Form mit Kontur auswählen.'), 'info'); return; }
    this.busy(true);
    const out = [];
    try {
      for (const el of list) {
        const r = await outlineStroke(this, el);
        if (r.error) { toast(r.error, 'info'); out.push(el); continue; }
        if (r.dashed) toast(t('Strichmuster werden als durchgehende Kontur umgewandelt.'), 'info');
        out.push(r.el);
      }
    } catch (e) { console.error(e); toast(t('Die Geometrie-Funktionen konnten nicht geladen werden.'), 'error'); return; } finally { this.busy(false); }
    this.sel = out.filter(el => el.isConnected);
    this.commit(t('Kontur in Fläche umgewandelt'));
  }

  /** Pfad versetzen: Abstand (+ außen / − innen), Ecken, Original behalten – mit Vorschau */
  async offsetPath() {
    const el = this.sel.find(x => !isLocked(x));
    if (!el || ['text', 'line', 'polyline'].includes(el.localName)) { toast(t('Bitte eine geschlossene Form auswählen.'), 'info'); return; }
    let prev = null, seq = 0;
    const show = async (dist, join, keep) => {
      const my = ++seq;
      try {
        const d = await offsetArea(this, el, dist, join);
        if (my !== seq) return;
        prev?.remove();
        prev = d ? pathLike(el, this.root, d) : null;
        if (prev) { prev.removeAttribute('id'); prev.setAttribute('data-name', t('Versatz')); if (keep && dist > 0) el.before(prev); else el.after(prev); el.style.visibility = keep ? '' : 'hidden'; }
        this.draw();
      } catch (e) { console.error(e); toast(t('Die Geometrie-Funktionen konnten nicht geladen werden.'), 'error'); }
    };
    const r = await modal(this.el, {
      title: t('Pfad versetzen'), ok: t('Übernehmen'),
      body: `<label class="f">${esc(t('Abstand (px, negativ = nach innen)'))}<input type="number" name="dist" step="0.5" value="4"></label>
        <label class="f">${esc(t('Ecken'))}<select name="join"><option value="round">${esc(t('Rund'))}</option><option value="miter">${esc(t('Spitz'))}</option><option value="bevel">${esc(t('Abgeschrägt'))}</option></select></label>
        <label class="f-check"><input type="checkbox" name="keep" checked> ${esc(t('Original behalten (Versatz als neue Form)'))}</label>`,
      onOpen: d => {
        const f = () => show(+d.querySelector('[name=dist]').value || 0, d.querySelector('[name=join]').value, d.querySelector('[name=keep]').checked);
        d.querySelectorAll('input,select').forEach(i => i.addEventListener(i.type === 'number' ? 'input' : 'change', f));
        f();
      },
    });
    seq++;
    el.style.visibility = '';
    if (el.getAttribute('style') === '') el.removeAttribute('style');
    if (!r || !prev) { prev?.remove(); this.draw(); return; }
    if (!r.keep) { if (el.id) prev.id = el.id; el.remove(); }
    this.sel = [prev];
    this.commit(t('Pfad versetzt'));
  }

  /** Vereinfachen (weniger Knoten, Form innerhalb der Toleranz) mit Vorschau */
  async simplifyPath() {
    const el0 = this.sel[0];
    if (!el0 || !NODE_SHAPES.includes(el0.localName) || isLocked(el0)) return;
    const el = this.toPath(el0), d0 = el.getAttribute('d');
    const n0 = toNodes(parsePath(d0)).reduce((a, x) => a + x.nodes.length, 0);
    const m = decompose(docMatrix(el, this.root)), unit = 1 / (Math.abs(m.sx) || 1);
    const vb = this.vb, def = Math.max(vb.w, vb.h) / 400;
    let seq = 0;
    const apply = async (tol, out) => {
      const my = ++seq;
      try {
        const r = await simplifyD(this, d0, tol * unit);
        if (my !== seq) return;
        el.setAttribute('d', r.d);
        if (out) out.textContent = t('{a} → {b} Knoten', { a: n0, b: r.nodes });
        this.draw();
      } catch (e) { console.error(e); toast(t('Die Geometrie-Funktionen konnten nicht geladen werden.'), 'error'); }
    };
    const r = await modal(this.el, {
      title: t('Vereinfachen'), ok: t('Übernehmen'),
      body: `<label class="f">${esc(t('Toleranz'))}<input type="range" name="tol" min="${num(def / 10, 4)}" max="${num(def * 10, 4)}" step="${num(def / 10, 4)}" value="${num(def, 4)}"></label>
        <p class="f-help" data-out aria-live="polite">${esc(t('{a} Knoten', { a: n0 }))}</p>
        <p class="f-help">${esc(t('Die Kurve wird neu angepasst – größere Toleranz = weniger Knoten, mehr Abweichung.'))}</p>`,
      onOpen: d => {
        const inp = d.querySelector('[name=tol]'), out = d.querySelector('[data-out]');
        inp.addEventListener('input', () => apply(+inp.value, out));
        apply(+inp.value, out);
      },
    });
    seq++;
    if (!r) { el.setAttribute('d', d0); this.draw(); return; }
    await apply(+r.tol, null);
    this.nodeSel.clear();
    this.commit(t('Vereinfacht'));
  }

  /** Beschneiden (kind 'clip') bzw. Maske (kind 'mask'): oberstes Objekt der Auswahl wirkt auf die übrigen */
  clip(kind) {
    const list = this.ordered(this.sel.filter(el => !isLocked(el) && !el.closest('clipPath,mask')));
    const r = makeClip(this.root, list, kind);
    if (r.error) { toast(r.error, 'info'); return; }
    this.sel = [r.el];
    this.commit(kind === 'clip' ? t('Beschnitten') : kind === 'knockout' ? t('Ausgestanzt') : t('Maske erstellt'));
  }

  /** Beschneidung bzw. Maske der Auswahl freigeben */
  unclip() {
    const list = this.sel.map(el => el.closest('clipPath,mask') ? el.closest('clipPath,mask').parentNode : el).filter(el => el && clipOf(el, this.root));
    if (!list.length) { toast(t('Die Auswahl ist weder beschnitten noch maskiert.'), 'info'); return; }
    const out = [];
    for (const g of [...new Set(list)]) out.push(...(releaseClip(this.root, g) || []));
    this.sel = out.filter(isDrawn);
    this.commit(t('Maske freigegeben'));
  }

  /** Menü „Objekt“ (Kopfzeile): alle Befehle mit Kürzeln – auch per Tastatur erreichbar */
  objectMenu(btn) {
    const sel = this.sel, n = sel.length;
    const shapes = sel.filter(el => NODE_SHAPES.includes(el.localName));
    const curvable = sel.some(el => ['rect', 'ellipse', 'circle', 'polygon', 'polyline', 'line', 'text'].includes(el.localName) || el.querySelector?.('text'));
    const clipped = sel.some(el => clipOf(el.closest('clipPath,mask')?.parentNode || el, this.root));
    const subs = shapes[0] ? toNodes(parsePath(shapes[0].localName === 'path' ? shapes[0].getAttribute('d') : shapeToPath(shapes[0]) || '')) : [];
    menu(btn, [
      { label: t('Gruppieren'), key: MOD + 'G', disabled: !n, run: () => this.group() },
      { label: t('Gruppierung aufheben'), key: SHIFT + MOD + 'G', disabled: !sel.some(el => el.localName === 'g'), run: () => this.ungroup() },
      { sep: true },
      { label: t('Vereinen'), disabled: n < 2, run: () => this.bool('unite') },
      { label: t('Abziehen'), disabled: n < 2, run: () => this.bool('subtract') },
      { label: t('Schnittmenge'), disabled: n < 2, run: () => this.bool('intersect') },
      { label: t('Ausschluss'), disabled: n < 2, run: () => this.bool('exclude') },
      { label: t('Teilen'), disabled: n < 2, run: () => this.bool('divide') },
      { label: t('Zusammengesetzt: Abziehen (bearbeitbar)'), disabled: n < 2, run: () => this.compound('subtract') },
      { label: t('Zusammengesetzt: Vereinen (bearbeitbar)'), disabled: n < 2, run: () => this.compound('unite') },
      { sep: true },
      { label: t('Kontur in Fläche umwandeln'), disabled: !shapes.some(el => (getProp(el, 'stroke') || 'none') !== 'none'), run: () => this.strokeToFill() },
      { label: t('Pfad versetzen …'), disabled: !n, run: () => this.offsetPath() },
      { sep: true },
      { label: t('Beschneiden'), key: MOD + '7', disabled: n < 2, run: () => this.clip('clip') },
      { label: t('Als Maske verwenden'), disabled: n < 2, run: () => this.clip('mask') },
      { label: t('Ausstanzen per Maske (umgekehrt)'), disabled: n < 2, run: () => this.clip('knockout') },
      { label: t('Maske freigeben'), key: ALT + MOD + '7', disabled: !clipped, run: () => this.unclip() },
      { sep: true },
      { label: t('In Kurven umwandeln'), key: MOD + '↵', disabled: !curvable, run: () => this.toCurves() },
      { label: t('Pfade verbinden'), key: MOD + 'J', disabled: !(shapes.length > 1 || subs.some(x => !x.closed)), run: () => shapes.length > 1 ? this.joinPaths() : this.pathCmd('join') },
      { label: t('Richtung umkehren'), disabled: !shapes.length, run: () => this.pathCmd('reverse') },
      { label: t('Teilpfade trennen'), disabled: subs.length < 2, run: () => this.pathCmd('separate') },
      { label: t('Vereinfachen …'), disabled: shapes.length !== 1, run: () => this.simplifyPath() },
    ]);
  }

  // ================================================================= Text: Zeichen und Absatz (textpanel.js)
  /**
   * Zeichen-Eigenschaft setzen: während der Bearbeitung mit markiertem Bereich nur für diese Zeichen (Lauf-tspans),
   * sonst für die ganzen Texte der Auswahl (Läufe verlieren dieselbe Eigenschaft, damit sie überall gilt).
   */
  textProp(prop, value) {
    const runProp = ['font-weight', 'font-style', 'fill', 'text-decoration', 'letter-spacing', 'font-family', 'font-size', 'baseline-shift', 'font-feature-settings'].includes(prop);
    if (this.textEd && runProp && this.textEd.range[0] !== this.textEd.range[1]) { this.textEd.format({ [prop]: value }); this.panels.refresh(); return; }
    const list = this.textEl ? [this.textEl] : this.sel.filter(el => el.localName === 'text');
    if (!list.length) { this.renderCtx(); this.panels.refresh(); return; }
    for (const el of list) {
      setProp(el, prop, value);
      for (const r of el.querySelectorAll('tspan')) { r.removeAttribute(prop); r.style?.removeProperty(prop); if (r.getAttribute('style') === '') r.removeAttribute('style'); }
      if (prop === 'font-kerning' && value === null) el.style?.removeProperty('font-kerning');
      if (prop === 'font-kerning' && value) { el.removeAttribute('font-kerning'); el.style.setProperty('font-kerning', value); }
      if (el.hasAttribute('data-ge-frame') || el.querySelector('tspan[dy]')) this.retext(el);
    }
    if (this.textEd) { this.textEd.dirty = true; this.textEd.before = null; this.draw(); this.panels.refresh(); return; }
    this.commit(t('Schrift geändert'));
  }

  /**
   * Absatz: anchor (Ausrichtung start|middle|end|justify), lh (Zeilenabstand), frame (Rahmenbreite), offset (Startversatz
   * auf dem Pfad, %), ws (Wortabstand), pb/pa (Abstand vor/nach Absätzen), li/ri/fi (Einzüge links/rechts/erste Zeile),
   * hy (Silbentrennung: '', 'de', 'en' – Trennmuster werden vorher geladen)
   */
  async textPara(k, v) {
    const list = this.textEl ? [this.textEl] : this.sel.filter(el => el.localName === 'text');
    if (k === 'hy' && v) { this.busy(true); await loadHyphens(v); this.busy(false); }
    for (const el of list) {
      if (k === 'anchor') {
        // Blocksatz = links ausgerichtet, Wortabstände füllen die Zeilen des Textrahmens
        const just = v === 'justify';
        if (just !== (paraOf(el).al === 'justify')) setPara(el, { al: just ? 'justify' : '' });
        if (just) v = 'start';
        const w = parseFloat(el.getAttribute('data-ge-frame')) || 0, tp = el.querySelector('textPath');
        if (w && !tp) {
          // Textrahmen: linke Kante bleibt, x wandert mit der Ausrichtung
          const off = a => a === 'middle' ? w / 2 : a === 'end' ? w : 0;
          const x = parseFloat((el.getAttribute('x') || '0').split(/[\s,]+/)[0]) || 0, left = x - off(getProp(el, 'text-anchor') || 'start');
          el.setAttribute('x', num(left + off(v)));
          setProp(el, 'text-anchor', v === 'start' ? null : v);
          this.retext(el);
        } else if (tp) {
          setProp(el, 'text-anchor', v === 'start' ? null : v);
          tp.setAttribute('startOffset', v === 'middle' ? '50%' : v === 'end' ? '100%' : '0%');
        } else {
          // Punkttext: sichtbare Lage bleibt (wie in Affinity springt der Text nicht)
          const before = docBounds(el, this.root);
          setProp(el, 'text-anchor', v === 'start' ? null : v);
          const after = docBounds(el, this.root);
          if (before && after) applyTransform(el, new DOMMatrix().translate(before.x - after.x, 0), this.root);
        }
      } else if (k === 'lh') { el.setAttribute('data-ge-lh', num(v, 3)); this.retext(el); }
      else if (k === 'frame') { this.setTextFrame(el, v, false); }
      else if (k === 'offset') { const tp = el.querySelector('textPath'); if (tp) tp.setAttribute('startOffset', num(v, 2) + '%'); }
      else if (k === 'ws') { setProp(el, 'word-spacing', v ? num(v, 3) : null); this.retext(el); }
      else if (['pb', 'pa', 'li', 'ri', 'fi', 'hy'].includes(k)) { setPara(el, { [k]: v }); this.retext(el); }
    }
    if (this.textEd) { this.textEd.dirty = true; this.textEd.before = null; this.textEd.place(); this.draw(); this.panels.refresh(); return; }
    this.commit({ anchor: t('Ausrichtung geändert'), lh: t('Zeilenabstand geändert'), frame: t('Textrahmen geändert'), offset: t('Startversatz geändert'),
      ws: t('Wortabstand geändert'), pb: t('Abstand vor Absatz'), pa: t('Abstand nach Absatz'), li: t('Einzug links'), ri: t('Einzug rechts'), fi: t('Einzug erste Zeile'),
      hy: t('Silbentrennung') }[k]);
  }

  /**
   * OpenType-Merkmale ändern (patch: { tag: 1|0|null }, null = Standard): während der Bearbeitung mit markiertem Bereich nur
   * für diese Zeichen (jeder Lauf behält seine übrigen Merkmale), sonst für die ganzen Texte samt ihren Läufen.
   */
  textFeature(patch) {
    const merge = cur => { const o = parseFeatures(cur); for (const [k, v] of Object.entries(patch)) { if (v === null) delete o[k]; else o[k] = v; } return fmtFeatures(o) || null; };
    const te = this.textEd;
    if (te && te.range[0] !== te.range[1]) {
      const base = getProp(te.el, 'font-feature-settings', false);
      te.model = mapRange(te.model, te.range[0], te.range[1], s => ({ ...s, 'font-feature-settings': merge(s['font-feature-settings'] ?? base) }));
      te.dirty = true; te.relayout(); te.ta.setSelectionRange(...te.range); this.draw(); this.panels.refresh();
      return;
    }
    const list = this.textEl ? [this.textEl] : this.sel.filter(el => el.localName === 'text');
    if (!list.length) return;
    for (const el of list) {
      setProp(el, 'font-feature-settings', merge(getProp(el, 'font-feature-settings', false)));
      for (const r of el.querySelectorAll('tspan')) { const v = r.style.getPropertyValue('font-feature-settings'); if (v) setProp(r, 'font-feature-settings', merge(v)); }
      if (el.hasAttribute('data-ge-frame') || el.querySelector('tspan[dy]')) this.retext(el);
    }
    if (te) { te.dirty = true; te.before = null; this.draw(); this.panels.refresh(); return; }
    this.commit(t('OpenType geändert'));
  }

  /**
   * Hoch-/Tiefstellen: mit den Merkmalen sups/subs der Schrift (has = Schrift kennt sie), sonst Ersatz mit Grundlinienversatz
   * und 60 % Schriftgröße. pos: 'sups' | 'subs' | '' (normal)
   */
  textPosition(pos, has) {
    const te = this.textEd, sel = te && te.range[0] !== te.range[1];
    const patch = { sups: pos === 'sups' && has ? 1 : null, subs: pos === 'subs' && has ? 1 : null };
    const fake = pos && !has ? { 'baseline-shift': pos === 'sups' ? 'super' : 'sub', 'font-size': '60%' } : { 'baseline-shift': null };
    if (sel) {
      const base = getProp(te.el, 'font-feature-settings', false);
      te.model = mapRange(te.model, te.range[0], te.range[1], s => {
        const o = parseFeatures(s['font-feature-settings'] ?? base);
        for (const [k, v] of Object.entries(patch)) { if (v === null) delete o[k]; else o[k] = v; }
        const n = { ...s, 'font-feature-settings': fmtFeatures(o) || null, ...fake };
        if (!pos || has) { if (/%$/.test(n['font-size'] || '')) delete n['font-size']; delete n['baseline-shift']; }
        for (const k of Object.keys(n)) if (n[k] === null) delete n[k];
        return n;
      });
      te.dirty = true; te.relayout(); te.ta.setSelectionRange(...te.range); this.draw(); this.panels.refresh();
      return;
    }
    const list = this.textEl ? [this.textEl] : this.sel.filter(el => el.localName === 'text');
    for (const el of list) {
      const o = parseFeatures(getProp(el, 'font-feature-settings', false));
      for (const [k, v] of Object.entries(patch)) { if (v === null) delete o[k]; else o[k] = v; }
      setProp(el, 'font-feature-settings', fmtFeatures(o) || null);
      // ganzer Text: Ersatz über die Läufe (ein Grundlinienversatz am Text selbst verschöbe nur alles)
      const m = readText(el);
      const all = mapRange(m, 0, Infinity, s => {
        const n = { ...s, ...fake };
        if (!pos || has) { if (/%$/.test(n['font-size'] || '')) delete n['font-size']; delete n['baseline-shift']; }
        for (const k of Object.keys(n)) if (n[k] === null) delete n[k];
        return n;
      });
      layoutText(el, all);
    }
    if (te) { te.model = readText(te.el); te.dirty = true; te.before = null; te.relayout(); this.draw(); this.panels.refresh(); return; }
    this.commit(t('Position geändert'));
  }

  /** Horizontal/vertikal skalieren (Prozent) – als Skalierung des Textes um seinen Ankerpunkt (data-ge-sx/-sy merken den Wert) */
  textScale(axis, pct) {
    const list = this.textEl ? [this.textEl] : this.sel.filter(el => el.localName === 'text');
    for (const el of list) {
      const a = axis === 'x' ? 'data-ge-sx' : 'data-ge-sy';
      const old = (parseFloat(el.getAttribute(a)) || 100) / 100, nw = Math.max(1, Math.min(1000, pct || 100)) / 100;
      if (Math.abs(old - nw) < 1e-6) continue;
      const x = parseFloat((el.getAttribute('x') || '0').split(/[\s,]+/)[0]) || 0, y = parseFloat((el.getAttribute('y') || '0').split(/[\s,]+/)[0]) || 0;
      const k = nw / old;
      const M = (el.transform?.baseVal?.consolidate()?.matrix ? DOMMatrix.fromMatrix(el.transform.baseVal.consolidate().matrix) : new DOMMatrix())
        .multiply(new DOMMatrix().translate(x, y).scale(axis === 'x' ? k : 1, axis === 'y' ? k : 1).translate(-x, -y));
      // SVGMatrix rechnet mit 32 Bit: auf 6 Stellen runden, damit 100 % wieder ohne Transformation endet
      for (const k2 of ['a', 'b', 'c', 'd']) M[k2] = Math.round(M[k2] * 1e6) / 1e6;
      for (const k2 of ['e', 'f']) M[k2] = Math.round(M[k2] * 1e3) / 1e3 || 0;
      const f = fmtMatrix(M);
      if (f) el.setAttribute('transform', f); else el.removeAttribute('transform');
      if (Math.abs(nw - 1) < 1e-6) el.removeAttribute(a); else el.setAttribute(a, num(nw * 100, 2));
    }
    if (this.textEd) { this.textEd.dirty = true; this.textEd.before = null; this.textEd.place(); this.draw(); this.panels.refresh(); return; }
    this.commit(axis === 'x' ? t('Horizontal skaliert') : t('Vertikal skaliert'));
  }

  /** Groß-/Kleinschreibung als echte Zeichen (während der Bearbeitung: Auswahl, sonst ganze Texte) */
  textCase(mode) {
    if (this.textEd) { this.textEd.setCase(mode); this.panels.refresh(); return; }
    const list = this.sel.filter(el => el.localName === 'text');
    if (!list.length) return;
    for (const el of list) layoutText(el, transformCase(readText(el), mode));
    this.commit(t('Schreibung geändert'));
  }

  /** Text auf Pfad: Auswahl aus einem Text und einer Kurve → textPath; die Kurve wird unsichtbare Grundlinie */
  textOnPath() {
    const text = this.sel.find(el => el.localName === 'text'), shape = this.sel.find(el => NODE_SHAPES.includes(el.localName));
    if (!text || !shape) { toast(t('Bitte einen Text und eine Kurve auswählen.'), 'info'); return; }
    this.endText();
    const path = this.toPath(shape);
    if (!path.id) path.id = uid(this.root, 'textpfad-');
    // Text im Raum der Kurve (textPath nimmt die Kurve im Raum des Textes)
    const T = parentMatrix(text, this.root).inverse().multiply(parentMatrix(path, this.root));
    const m = readText(text);
    const runs = m.paras.flatMap((p, i) => i ? [{ t: ' ', s: {} }, ...p] : p);
    const tp = document.createElementNS(NS, 'textPath');
    tp.setAttribute('href', '#' + path.id);
    const an = getProp(text, 'text-anchor') || 'start';
    tp.setAttribute('startOffset', an === 'middle' ? '50%' : an === 'end' ? '100%' : '0%');
    for (const a of ['x', 'y', 'dx', 'dy', 'data-ge-frame', 'data-ge-lh']) text.removeAttribute(a);
    const f = fmtMatrix(T);
    if (f) text.setAttribute('transform', f); else text.removeAttribute('transform');
    layoutText(text, { paras: [runs], path: tp });
    if (!path.getAttribute('data-name')) path.setAttribute('data-name', t('Textpfad'));
    path.setAttribute('fill', 'none'); path.setAttribute('stroke', 'none');
    path.style?.removeProperty('fill'); path.style?.removeProperty('stroke');
    this.sel = [text];
    this.commit(t('Text auf Pfad'));
  }

  /** Text vom Pfad lösen: wieder Punkttext am Anfang der Kurve */
  textOffPath() {
    const text = this.textEl || this.sel.find(el => el.localName === 'text' && el.querySelector('textPath'));
    const tp = text?.querySelector('textPath');
    if (!tp) return;
    this.endText();
    let start = { x: 0, y: 0 };
    try { start = text.getStartPositionOfChar(0); } catch { /* leer */ }
    const m = readText(text);
    tp.remove();
    text.setAttribute('x', num(start.x)); text.setAttribute('y', num(start.y));
    layoutText(text, { paras: m.paras, path: null });
    this.sel = [text];
    this.commit(t('Vom Pfad gelöst'));
  }

  /**
   * Vorschau einer Schrift (Schriftauswahl: Zeiger oder Pfeile auf einer Schrift) – wirkt sofort auf die markierten Zeichen
   * bzw. die ausgewählten Texte, ohne Schritt im Protokoll. null = zurücknehmen. Übernehmen geht danach über textProp().
   */
  textPreview(props) {
    const te = this.textEd;
    const texts = this.textEl ? [this.textEl] : this.sel.filter(el => el.localName === 'text');
    if (!this._preview) {
      if (!props) return;
      this._preview = te && te.range[0] !== te.range[1]
        ? { te, model: te.model, range: te.range }
        : { els: texts.map(el => ({ el, attrs: [...el.attributes].map(a => [a.name, a.value, a.namespaceURI]), kids: [...el.childNodes].map(n => n.cloneNode(true)) })) };
    }
    const pv = this._preview;
    // erst zurück auf den Stand vor der Vorschau
    if (pv.te) { pv.te.model = pv.model; }
    else for (const s of pv.els) {
      const el = s.el;
      for (const a of [...el.attributes]) el.removeAttributeNS(a.namespaceURI, a.localName);
      for (const [n, v, ns] of s.attrs) ns ? el.setAttributeNS(ns, n, v) : el.setAttribute(n, v);
      while (el.firstChild) el.firstChild.remove();
      for (const k of s.kids) el.append(k.cloneNode(true));
    }
    if (!props) {
      if (pv.te) pv.te.relayout(); else this.textEd?.relayout();
      this._preview = null;
      this.draw();
      return;
    }
    if (pv.te) { pv.te.model = styleRange(pv.model, pv.range[0], pv.range[1], props); pv.te.relayout(); }
    else for (const s of pv.els) {
      for (const [k, v] of Object.entries(props)) {
        setProp(s.el, k, v);
        for (const r of s.el.querySelectorAll('tspan')) { r.removeAttribute(k); r.style?.removeProperty(k); }
      }
      if (s.el.hasAttribute('data-ge-frame') || s.el.querySelector('tspan[dy]')) this.retext(s.el);
    }
    this.draw();
  }

  /** Mehrere Zeichen-Eigenschaften als ein Schritt (Schriftauswahl: Familie + Schnitt) */
  textProps(props) {
    if (this._preview) this.textPreview(null);
    const te = this.textEd;
    if (te && te.range[0] !== te.range[1]) { te.format(props); this.panels.refresh(); return; }
    const list = this.textEl ? [this.textEl] : this.sel.filter(el => el.localName === 'text');
    if (props['font-family']) this.opt.fontFamily = props['font-family'];
    if ('font-weight' in props) this.opt.fontWeight = props['font-weight'] || '400';
    if (!list.length) { this.renderCtx(true); this.panels.refresh(); return; }
    for (const el of list) {
      for (const [k, v] of Object.entries(props)) {
        setProp(el, k, v);
        for (const r of el.querySelectorAll('tspan')) { r.removeAttribute(k); r.style?.removeProperty(k); if (r.getAttribute('style') === '') r.removeAttribute('style'); }
      }
      if (el.hasAttribute('data-ge-frame') || el.querySelector('tspan[dy]')) this.retext(el);
    }
    if (te) { te.dirty = true; te.before = null; this.draw(); this.panels.refresh(); this.renderCtx(true); return; }
    this.commit(t('Schrift geändert'));
  }

  /** Persönliche Einstellung merken (Core\UiPrefs) – sofort im Editor, gespeichert gebündelt */
  setPref(key, value) {
    this.prefs[key] = value;
    if (!this.boot.urls?.prefs) return;
    clearTimeout(this._prefT?.[key]);
    (this._prefT ||= {})[key] = setTimeout(() => {
      fetch(this.boot.urls.prefs, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify({ key, value }) }).catch(() => {});
    }, 300);
  }

  /** Kurze Meldung für Bildschirmleser (Statuszeile, aria-live) */
  announce(msg) { this.hint(msg); clearTimeout(this._annT); this._annT = setTimeout(() => this.hint(), 4000); }

  /** Raster ein/aus (zeigen und einrasten) */
  toggleGrid() { this.opt.grid = !this.opt.grid; this.snapCache = null; this.renderCtx(true); this.draw(); }

  /** Laufende Arbeit anzeigen (Bibliothek lädt, Rechnung läuft) */
  busy(on) { this.el.classList.toggle('is-busy', on); this.el.setAttribute('aria-busy', on ? 'true' : 'false'); }

  /** Texte in der Liste (auch in Gruppen) in Kurven umwandeln – Rückgabe: neue Liste oder null (Meldung kam schon) */
  async outlineTexts(list) {
    this.endText();
    this.busy(true);
    try {
      const r = await outlineAll(this, list);
      // Texte ohne Schriftdatei bleiben Text (Meldung), die übrigen sind umgewandelt
      if (r.errors.length) toast(r.errors[0], 'warn');
      return r.els;
    } catch (e) {
      console.error(e);
      toast(t('Die Schriftdatei konnte nicht gelesen werden.'), 'error');
      return null;
    } finally { this.busy(false); }
  }

  /** Schriften der Website in der Verwaltung laden (font.css des Schriften-Managers), damit Texte wie auf der Website aussehen */
  loadFonts() {
    for (const [, f] of Object.entries(this.boot.fontFiles || {})) {
      if (!f.css || document.querySelector(`link[data-ge-font="${CSS.escape(f.css)}"]`)) continue;
      const l = document.createElement('link');
      l.rel = 'stylesheet'; l.href = f.css; l.dataset.geFont = f.css;
      document.head.append(l);
    }
  }

  // ================================================================= Befehle
  /** ids einer Kopie eindeutig machen (Verweise innerhalb der Kopie folgen) */
  freshIds(el) { remapIds(el, this.root); }

  duplicate() {
    if (!this.sel.length) return;
    const off = this.format === 'icon' ? 1 : 10;
    const clones = this.sel.map(el => { const c = el.cloneNode(true); el.after(c); this.freshIds(c); applyTransform(c, new DOMMatrix().translate(off, off), this.root); return c; });
    // Text in einer Form: Kopie gehört zur Kopie der Form
    this.sel.forEach((el, i) => {
      if (!el.id || isConn(el)) return;
      const inner = this.root.querySelector(`text[data-ge-in="${CSS.escape(el.id)}"]`);
      if (!inner || this.sel.includes(inner)) return;
      if (!clones[i].id) clones[i].id = uid(this.root, 'form-');
      const ti = inner.cloneNode(true);
      ti.removeAttribute('id');
      ti.setAttribute('data-ge-in', clones[i].id);
      clones[i].after(ti);
    });
    this.sel = clones;
    this.commit(t('Dupliziert'));
  }

  remove() {
    if (this.tool === 'node' && this.nodeSel.size) return this.deleteNodes();
    const list = this.sel.filter(el => !isLocked(el));
    if (!list.length) return;
    // mit dem Verbinder seine Beschriftung, mit der Form ihr Text (Verbinder an der Form werden an ihrer Stelle gelöst)
    for (const el of list) for (const n of [el, ...el.querySelectorAll('[id]')]) {
      if (!n.id) continue;
      for (const t2 of this.root.querySelectorAll(`text[data-ge-for="${CSS.escape(n.id)}"], text[data-ge-in="${CSS.escape(n.id)}"]`)) if (!list.includes(t2)) t2.remove();
    }
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
    let kept = 0;
    for (const g of this.sel) {
      if (!['g', 'switch'].includes(g.localName)) { out.push(g); continue; }
      // Beschneidung, Maske, Filter und <switch> wirken nur auf die Gruppe als Ganzes – Auflösen würde das Bild verändern
      if (g.localName === 'switch' || ['clip-path', 'mask', 'filter'].some(a => { const v = getProp(g, a, false); return v && v !== 'none'; })) { kept++; out.push(g); continue; }
      // Eigenschaften der Gruppe (transform, Darstellung) an die Kinder weitergeben – auch aus Klassenregeln (<style>)
      const T = g.hasAttribute('transform') ? docMatrix(g, this.root).multiply(docMatrix(g.parentNode, this.root).inverse()) : null;
      const cs = g.hasAttribute('class') ? getComputedStyle(g) : null, ps = cs ? getComputedStyle(g.parentNode) : null;
      const vals = {};
      for (const a of INHERITED) {
        let v = getProp(g, a, false);
        if (!v && cs && cs.getPropertyValue(a) !== ps.getPropertyValue(a)) v = cs.getPropertyValue(a).trim();
        if (v && v !== 'inherit') vals[a] = v;
      }
      const go = parseFloat(cs ? cs.opacity : getProp(g, 'opacity', false));
      const hidden = isHidden(g);
      for (const c of [...g.children]) {
        g.before(c);
        if (!isDrawn(c)) continue;
        for (const [a, v] of Object.entries(vals)) if (!getProp(c, a, false)) c.setAttribute(a, v);
        if (Number.isFinite(go) && go !== 1) {
          const co = parseFloat(getProp(c, 'opacity', false));
          setProp(c, 'opacity', num(go * (Number.isFinite(co) ? co : 1), 4));
        }
        if (hidden) c.setAttribute('display', 'none');
        if (T) { const pm = parentMatrix(c, this.root); applyTransform(c, pm.multiply(localMatrixOf(g)).multiply(pm.inverse()), this.root); }
        out.push(c);
      }
      g.remove();
    }
    if (kept) toast(t('Gruppen mit Beschneidung, Maske, Effekt oder Alternativen (switch) bleiben als Gruppe erhalten – Beschneidung und Maske lassen sich unter Objekt → Maske freigeben lösen.'), 'warn');
    if (out.length === this.sel.length && out.every((el, i) => el === this.sel[i])) return;
    // verschachtelte Gruppen in der Auswahl: aufgelöste innere Gruppen sind nicht mehr im Dokument
    this.sel = [...new Set(out)].filter(el => isDrawn(el) && el.isConnected);
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
    this.nudgeLabel = t('Verschoben');
    this.nudgeT = setTimeout(() => this.flushNudge(), 400);
  }

  async toCurves() {
    const list = this.sel.filter(el => ['rect', 'ellipse', 'circle', 'polygon', 'polyline', 'line'].includes(el.localName));
    const texts = this.sel.filter(el => el.localName === 'text' || el.querySelector?.('text'));
    if (!list.length && !texts.length) return;
    list.forEach(el => this.toPath(el));
    if (texts.length) {
      const out = await this.outlineTexts(this.sel);
      if (!out) { if (list.length) this.commit(t('In Kurven umgewandelt')); return; }
      this.sel = out;
    }
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
      // geerbte Darstellung aus Gruppen mitnehmen (sonst ist die Kopie außerhalb der Gruppe schwarz)
      for (let p = el.parentNode; p && p !== this.root && p.nodeType === 1; p = p.parentNode) {
        for (const a of INHERITED) { const v = getProp(p, a, false); if (v && !getProp(c, a, false)) c.setAttribute(a, v); }
      }
      const P = parentMatrix(el, this.root), m = [P.a, P.b, P.c, P.d, P.e, P.f].map(v => num(v, 6)).join(' ');
      if (m !== '1 0 0 1 0 0') { const g = document.createElementNS(NS, 'g'); g.setAttribute('transform', `matrix(${m})`); g.append(c); return new XMLSerializer().serializeToString(g); }
      return new XMLSerializer().serializeToString(c);
    });
    // Verläufe, Pfeilspitzen, Schatten, Masken … gehören zur Kopie, damit sie in einer anderen Grafik genauso aussieht
    const refs = new Map(), re = /url\(\s*['"]?#([^'")\s]+)/g;
    const scan = n => {
      for (const x of [n, ...n.querySelectorAll('*')]) {
        const ids = [];
        for (const a of x.attributes) { for (const m of a.value.matchAll(re)) ids.push(m[1]); if (a.localName === 'href' && a.value.startsWith('#')) ids.push(a.value.slice(1)); }
        for (const id of ids) {
          if (refs.has(id)) continue;
          const d = this.root.querySelector('#' + CSS.escape(id));
          if (!d || this.sel.some(s => s === d || s.contains(d))) continue;
          refs.set(id, d); scan(d);
        }
      }
    };
    this.sel.forEach(scan);
    const defsText = refs.size ? `<defs>${[...refs.values()].map(d => new XMLSerializer().serializeToString(d)).join('')}</defs>` : '';
    if (cut) { this.sel.forEach(el => el.remove()); this.sel = []; this.commit(t('Ausgeschnitten')); }
    // auch als SVG-Text in die Zwischenablage des Systems (für Desktop-Programme und andere Grafiken)
    this.clipText = `<svg xmlns="${NS}" data-ge-clip="1" viewBox="${[this.vb.x, this.vb.y, this.vb.w, this.vb.h].map(v => num(v)).join(' ')}">${defsText}${this.clip.join('')}</svg>`;
    navigator.clipboard?.writeText?.(this.clipText).catch(() => { /* ohne Berechtigung: nur intern */ });
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
      if (mod && k === 'v') return;   // Einfügen über das paste-Ereignis (Zwischenablage des Systems: SVG-Text, Pixelbild → Meldung)
      if (mod && (k === '0')) { e.preventDefault(); this.fit(); return; }
      if (mod && (k === '1')) { e.preventDefault(); this.setZoom(1); return; }
      if (mod && (k === '+' || k === '=')) { e.preventDefault(); this.setZoom(this.zoom * 1.25); return; }
      if (mod && (k === '-')) { e.preventDefault(); this.setZoom(this.zoom / 1.25); return; }
      if (mod && e.key === ']') { e.preventDefault(); this.arrange(e.shiftKey ? 'front' : 'forward'); return; }
      if (mod && e.key === '[') { e.preventDefault(); this.arrange(e.shiftKey ? 'back' : 'backward'); return; }
      if (mod && e.shiftKey && k === 'f') { e.preventDefault(); this.opts.shell?.toggleMax?.(); return; }
      if (mod && k === 'r' && !e.shiftKey) { e.preventDefault(); this.rulers.toggleRulers(); return; }
      if (mod && (e.key === ';' || e.code === 'Semicolon' || e.code === 'Comma' && e.shiftKey)) { e.preventDefault(); this.rulers.toggleGuides(); return; }
      if (mod && (e.key === "'" || e.code === 'Quote' || e.code === 'Backslash')) { e.preventDefault(); this.toggleGrid(); return; }
      if (mod && e.code === 'Digit7') { e.preventDefault(); e.altKey ? this.unclip() : this.clip('clip'); return; }
      if (mod && e.key === 'Enter') { e.preventDefault(); this.toCurves(); return; }
      if (mod && k === 'j') { e.preventDefault(); this.sel.filter(el => NODE_SHAPES.includes(el.localName)).length > 1 ? this.joinPaths() : this.pathCmd('join'); return; }
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
        if (this.tool === 'gradient' && this.grad.current()) { e.preventDefault(); this.grad.deleteStop(); return; }
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
    // Einfügen: eigene Kopie an Ort und Stelle; SVG-Text aus anderen Programmen über den Server bereinigt; Pixelbilder → Meldung
    this.pasteHandler = e => {
      if (!this.el.isConnected || this.el.closest('[inert]') || e.target.closest?.('dialog.ge-modal')) return;
      if (e.target.matches?.('input,textarea,select,[contenteditable]') && !e.target.closest('.ge-stage')) return;
      if (e.target.closest?.('.ge-textedit')) return;
      if (!this.el.contains(document.activeElement) && document.activeElement !== document.body) return;
      const dt = e.clipboardData;
      const text = dt?.getData('text/plain') || '';
      const raster = [...(dt?.items || [])].some(it => it.kind === 'file' && /^image\/(?!svg)/.test(it.type));
      e.preventDefault();
      if (text && this.clipText && text.trim() === this.clipText.trim() && this.clip?.length) { this.paste(); return; }
      // Kopie aus einer anderen Grafik (anderer Tab/Editor): an derselben Stelle, ohne Hülle
      if (/<svg[\s>]/i.test(text)) { this.importSvg(text, '', { inPlace: /^<svg[^>]*\sdata-ge-clip="1"/.test(text.trim()) }); return; }
      if (raster) { toast(t('Der Grafik-Editor arbeitet nur mit Vektoren – Pixelbilder (PNG, JPG …) lassen sich nicht einfügen.'), 'warn'); return; }
      if (this.clip?.length) this.paste();
    };
    document.addEventListener('paste', this.pasteHandler);
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
    this.nudgeLabel = t('Knoten bearbeitet');
    this.nudgeT = setTimeout(() => this.flushNudge(), 400);
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
  renderCtx(force = false) {
    const ctx = this.$('.ge-ctx');
    if (!ctx || ctx.contains(document.activeElement) && document.activeElement.matches('input,select')) return;
    if (force) ctx._html = '';
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
      const fs = el ? parseFloat(getProp(el, 'font-size')) : o.fontSize;
      const fw = el ? getProp(el, 'font-weight') : o.fontWeight, an = el ? (getProp(el, 'text-anchor') || 'start') : o.anchor;
      html += `<span class="ge-ctx__f"><span aria-hidden="true">${esc(t('Schrift'))}</span><span data-fpslot></span></span>`
        + `<label class="ge-ctx__f">${esc(t('Größe'))} <input type="number" class="ge-in ge-in--s" data-text="font-size" value="${esc(num(fs || 16, 2))}" min="1" max="2000" step="1"></label>`
        + (() => {
          // Stärken, die die Schrift hat (variable Schriften: Bereich der Achse), dazu die aktuelle
          const it = itemFor(this.fontItems ||= fontItems(this.boot), el ? getProp(el, 'font-family') : o.fontFamily);
          const cur = fw === 'bold' ? 700 : fw === 'normal' || !fw ? 400 : parseInt(fw, 10) || 400;
          const ws = [...new Set([...(it ? it.styles.map(s => s.w) : [300, 400, 700, 900]), cur])].sort((a, b) => a - b);
          return `<label class="ge-ctx__f">${esc(t('Stärke'))} <select class="ge-in" data-text="font-weight">${ws.map(w => `<option value="${w}"${w === cur ? ' selected' : ''}>${esc(WEIGHTS[w]?.() || String(w))}</option>`).join('')}</select></label>`;
        })()
        + `<span class="ge-seg" role="group" aria-label="${esc(t('Ausrichtung'))}">${[['start', 'align-left', t('Links')], ['middle', 'align-center-horizontal', t('Mitte')], ['end', 'align-right', t('Rechts')]].map(([v, i, l]) => `<button type="button" class="ge-ib" data-text-anchor="${v}" aria-pressed="${an === v}" aria-label="${esc(l)}" title="${esc(l)}">${ico(i)}</button>`).join('')}</span>`;
    }
    // Verbinder: Art, Spitzen, Größe, Beschriftung (Werkzeug aktiv oder Verbinder ausgewählt)
    const conns = this.sel.filter(isConn);
    if (this.tool === 'connector' || (n && conns.length === n)) {
      const co = conns.length ? parseConn(conns[0]) : { r: o.connRoute, hs: o.connStart, he: o.connEnd, s: o.connSize };
      const headOpts = cur => [['none', t('Keine')], ['arrow', t('Pfeil')], ['triangle', t('Dreieck')], ['open', t('Dreieck offen')], ['circle', t('Kreis')], ['diamond', t('Raute')], ['bar', t('Strich')]]
        .map(([v, l]) => `<option value="${v}"${v === cur ? ' selected' : ''}>${esc(l)}</option>`).join('');
      html += `<span class="ge-seg" role="radiogroup" aria-label="${esc(t('Verlauf des Verbinders'))}">${[['straight', 'line-segment', t('Gerade')], ['ortho', 'arrow-elbow-right-down', t('Rechtwinklig')], ['curve', 'bezier-curve', t('Gebogen')]]
        .map(([v, i, l]) => `<button type="button" class="ge-ib" role="radio" data-conn-r="${v}" aria-checked="${co.r === v}" aria-label="${esc(l)}" title="${esc(l)}">${ico(i)}</button>`).join('')}</span>`
        + `<label class="ge-ctx__f">${esc(t('Anfang'))} <select class="ge-in" data-conn-h="hs">${headOpts(co.hs)}</select></label>`
        + `<label class="ge-ctx__f">${esc(t('Ende'))} <select class="ge-in" data-conn-h="he">${headOpts(co.he)}</select></label>`
        + `<label class="ge-ctx__f">${esc(t('Spitzengröße'))} <input type="number" class="ge-in ge-in--s" data-conn-s value="${esc(num(co.s, 1))}" min="1" max="20" step="0.5"></label>`
        + (conns.length === 1 ? `<button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-cmd="connlabel">${ico('text-t')} ${esc(t('Beschriftung'))}</button>` : '');
    }
    // Baum anordnen: eine Form, an der Verbinder hängen
    if (n === 1 && !isConn(this.sel[0]) && this.sel[0].id && this.root.querySelector(`path[data-ge-conn*="a=${CSS.escape(this.sel[0].id)}@"]`)) {
      html += `<span class="ge-seg" role="group" aria-label="${esc(t('Baum anordnen'))}">${iconBtn('tree-structure', t('Baum anordnen (von oben nach unten)'), 'data-cmd="treedown"')}${iconBtn('arrow-elbow-right-down', t('Baum anordnen (von links nach rechts)'), 'data-cmd="treeright"')}</span>`;
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
        + `<span class="ge-seg" role="group" aria-label="${esc(t('Gruppe'))}">${iconBtn('selection-plus', t('Gruppieren'), 'data-cmd="group"', MOD + 'G')}${this.sel.some(el => el.localName === 'g') ? iconBtn('selection', t('Gruppierung aufheben'), 'data-cmd="ungroup"', SHIFT + MOD + 'G') : ''}${this.sel.some(el => ['rect', 'ellipse', 'circle', 'polygon', 'polyline', 'line', 'text'].includes(el.localName)) ? iconBtn(this.sel.some(el => el.localName === 'text') ? 'text-aa' : 'bezier-curve', t('In Kurven umwandeln'), 'data-cmd="curves"', MOD + '↵') : ''}</span>`;
      if (n > 1) html += `<span class="ge-seg" role="group" aria-label="${esc(t('Geometrie'))}">${[['unite', 'unite', t('Vereinen')], ['subtract', 'subtract', t('Abziehen')], ['intersect', 'intersect', t('Schnittmenge')], ['exclude', 'exclude', t('Ausschluss')], ['divide', 'squares-four', t('Teilen')]]
        .map(([op, i, l]) => iconBtn(i, l, `data-bool="${op}"`)).join('')}</span>`
        + `<span class="ge-seg" role="group" aria-label="${esc(t('Beschneiden'))}">${iconBtn('crop', t('Beschneiden'), 'data-cmd="clip"', MOD + '7')}${iconBtn('selection-background', t('Als Maske verwenden'), 'data-cmd="mask"')}${iconBtn('selection-inverse', t('Ausstanzen per Maske (umgekehrt)'), 'data-cmd="knockout"')}</span>`;
      if (n === 1 && isCompound(this.sel[0])) {
        const op = this.sel[0].getAttribute('data-ge-bool');
        html += `<span class="ge-ctx__sep"></span><label class="ge-ctx__f">${esc(t('Zusammengesetzt'))} <select class="ge-in" data-compop>${[['unite', t('Vereinen')], ['subtract', t('Abziehen')], ['intersect', t('Schnittmenge')], ['exclude', t('Ausschluss')]].map(([v, l]) => `<option value="${v}"${v === op ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></label>`
          + `<button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-cmd="compedit">${esc(t('Originale bearbeiten'))}</button><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-cmd="compflat">${esc(t('In Pfad umwandeln'))}</button>`;
      }
      if (this.sel.some(el => NODE_SHAPES.includes(el.localName) && (getProp(el, 'stroke') || 'none') !== 'none')) html += `<span class="ge-seg" role="group" aria-label="${esc(t('Kontur'))}">${iconBtn('pen-nib', t('Kontur in Fläche umwandeln'), 'data-cmd="strokefill"')}</span>`;
      else if (this.sel.some(el => clipOf(el, this.root))) html += `<span class="ge-seg" role="group" aria-label="${esc(t('Beschneiden'))}">${iconBtn('selection', t('Maske freigeben'), 'data-cmd="unclip"', ALT + MOD + '7')}</span>`;
      if (this.sel.some(el => ['line', 'path', 'polyline'].includes(el.localName))) {
        const el = this.sel[0];
        html += chk2('arrowStart', t('Pfeil am Anfang'), !!getProp(el, 'marker-start', false)) + chk2('arrowEnd', t('Pfeil am Ende'), !!getProp(el, 'marker-end', false));
      }
    }
    if (this.tool === 'node' && n === 1 && NODE_SHAPES.includes(this.sel[0].localName)) {
      const nm = this.nodeModel(), subs = nm?.subs || [], ns = this.nodeSel.size;
      const kinds = new Set([...this.nodeSel].map(k => { const [a, b] = k.split(':').map(Number); const nd = subs[a]?.nodes[b]; return nd ? nodeKind(nd) : ''; }));
      const kindOn = k => kinds.size === 1 && (kinds.has(k) || (k === 'corner' && (kinds.has('cusp') || kinds.has('half'))));
      html += `<span class="ge-ctx__info">${esc(ns ? t('{n} Knoten ausgewählt', { n: ns }) : t('Knoten anklicken oder ziehen – Klick auf die Kurve fügt einen Knoten ein'))}</span>`;
      if (ns) html += `<span class="ge-seg" role="group" aria-label="${esc(t('Knotenart'))}">${[['corner', 'square', t('Ecke')], ['smooth', 'circle', t('Glatt')], ['symmetric', 'diamond', t('Symmetrisch')]]
        .map(([k, i, l]) => `<button type="button" class="ge-ib" data-path="kind:${k}" aria-pressed="${kindOn(k)}" aria-label="${esc(l)}" title="${esc(l)}">${ico(i)}</button>`).join('')}</span>`;
      html += `<span class="ge-seg" role="group" aria-label="${esc(t('Pfad'))}">`
        + (ns ? iconBtn('scissors', t('An Knoten auftrennen'), 'data-path="split"') : '')
        + (subs.some(x => !x.closed) ? iconBtn('link-simple', t('Endpunkte verbinden'), 'data-path="join"') + iconBtn('circle-half', t('Pfad schließen'), 'data-path="close"') : '')
        + iconBtn('arrows-counter-clockwise', t('Richtung umkehren'), 'data-path="reverse"')
        + (subs.length > 1 ? iconBtn('link-break', t('Teilpfade trennen'), 'data-path="separate"') : '')
        + (subs.reduce((a, x) => a + x.nodes.length, 0) > 8 ? iconBtn('path', t('Vereinfachen …'), 'data-cmd="simplify"') : '')
        + `</span>`;
      if (ns) html += `<button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-cmd="delnodes">${ico('trash')} ${esc(t('Knoten löschen'))}</button>`;
    }
    html += `<span class="ge-ctx__grow"></span>${chk('snap', t('Einrasten'))}${chk('grid', t('Raster'))}
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-act="docset">${ico('sliders-horizontal')} ${esc(t('Dokument …'))}</button>`;
    // Schriftauswahl (eigene Komponente, bleibt über Neuaufbauten erhalten)
    const fpVal = this.tool === 'text' || (n === 1 && this.sel[0].localName === 'text') ? (n === 1 && this.sel[0].localName === 'text' ? getProp(this.sel[0], 'font-family') : o.fontFamily) : null;
    if (fpVal !== null) {
      this.ctxFp ||= fontPicker(this, { compact: true, label: t('Schrift'), prefs: () => this.prefs, setPref: (k, v) => this.setPref(k, v),
        onPreview: p => this.textPreview(p), onPick: p => this.textProps(p) });
      this.ctxFp.set(fpVal);
    }
    if (ctx._html === html) return;   // unverändert: nicht neu aufbauen (Symbole flackern sonst)
    ctx._html = html;
    this.ctxFp?.close(true);
    ctx.innerHTML = html;
    const slot = ctx.querySelector('[data-fpslot]');
    if (slot && this.ctxFp) slot.replaceWith(this.ctxFp.el);
    function chk2(k, label, on) { return `<label class="ge-ctx__f ge-ctx__chk"><input type="checkbox" data-arrow="${k}"${on ? ' checked' : ''}> ${esc(label)}</label>`; }
    ctx.querySelectorAll('[data-opt]').forEach(i => i.addEventListener('change', () => {
      const k = i.dataset.opt;
      o[k] = i.type === 'checkbox' ? i.checked : +i.value;
      if (k === 'grid' || k === 'snap') this.draw();
    }));
    ctx.querySelectorAll('[data-text]').forEach(i => i.addEventListener('change', () => {
      const a = i.dataset.text, v = i.value;
      if (a === 'font-size') o.fontSize = +v; if (a === 'font-weight') o.fontWeight = v;
      // wie im Bereich Zeichen: während der Bearbeitung nur für die markierten Zeichen
      if (a === 'font-size') this.textProp('font-size', num(Math.max(1, +v), 2));
      else this.textProp('font-weight', v === '400' ? null : v);
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
    ctx.querySelectorAll('[data-path]').forEach(b => b.addEventListener('click', () => this.pathCmd(b.dataset.path)));
    ctx.querySelector('[data-cmd=simplify]')?.addEventListener('click', () => this.simplifyPath());
    ctx.querySelectorAll('[data-bool]').forEach(b => b.addEventListener('click', () => this.bool(b.dataset.bool)));
    ctx.querySelector('[data-cmd=clip]')?.addEventListener('click', () => this.clip('clip'));
    ctx.querySelector('[data-cmd=mask]')?.addEventListener('click', () => this.clip('mask'));
    ctx.querySelector('[data-cmd=unclip]')?.addEventListener('click', () => this.unclip());
    ctx.querySelector('[data-cmd=knockout]')?.addEventListener('click', () => this.clip('knockout'));
    ctx.querySelector('[data-cmd=strokefill]')?.addEventListener('click', () => this.strokeToFill());
    ctx.querySelector('[data-compop]')?.addEventListener('change', e => this.compoundSet(this.sel[0], e.target.value));
    ctx.querySelector('[data-cmd=compedit]')?.addEventListener('click', () => this.editCompound(this.sel[0]));
    ctx.querySelector('[data-cmd=compflat]')?.addEventListener('click', () => this.compoundSet(this.sel[0], 'flatten'));
    ctx.querySelector('[data-act=docset]').addEventListener('click', () => this.docSettings());
    ctx.querySelectorAll('[data-conn-r]').forEach(b => b.addEventListener('click', () => this.connSet({ r: b.dataset.connR })));
    ctx.querySelectorAll('[data-conn-h]').forEach(s => s.addEventListener('change', () => this.connSet({ [s.dataset.connH]: s.value })));
    ctx.querySelector('[data-conn-s]')?.addEventListener('change', e => this.connSet({ s: Math.max(1, Math.min(20, +e.target.value || 4)) }));
    ctx.querySelector('[data-cmd=connlabel]')?.addEventListener('click', () => this.connLabel(this.sel[0]));
    ctx.querySelector('[data-cmd=treedown]')?.addEventListener('click', () => this.arrangeTree('down'));
    ctx.querySelector('[data-cmd=treeright]')?.addEventListener('click', () => this.arrangeTree('right'));
  }

  // ================================================================= Kopfzeile: Speichern, Export, Zoom
  bindTop() {
    this.el.querySelector('[data-act=undo]').addEventListener('click', () => this.undo());
    this.el.querySelector('[data-act=redo]').addEventListener('click', () => this.redo());
    this.el.querySelector('[data-act=save]').addEventListener('click', () => this.save());
    this.el.querySelector('[data-act=swapfs]')?.addEventListener('click', () => this.swapFs());
    const ob = this.el.querySelector('[data-act=objmenu]');
    ob.addEventListener('click', () => this.objectMenu(ob));
    const zb = this.el.querySelector('[data-act=zoommenu]');
    zb.addEventListener('click', () => menu(zb, [
      { label: t('Lineale'), key: MOD + 'R', checked: this.rulers.showRulers, run: () => this.rulers.toggleRulers() },
      { label: t('Hilfslinien'), key: MOD + ';', checked: this.rulers.showGuides, run: () => this.rulers.toggleGuides() },
      { label: t('Raster'), key: MOD + "'", checked: !!this.opt.grid, run: () => this.toggleGrid() },
      { label: t('Einrasten'), checked: !!this.opt.snap, run: () => { this.opt.snap = !this.opt.snap; this.snapCache = null; this.renderCtx(true); } },
      { label: t('Hilfslinien …'), run: () => this.rulers.dialog() },
      { sep: true },
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
    // Hell | Dunkel | Nebeneinander – Optionsfeld mit Pfeiltasten (WAI-ARIA radiogroup, ein Tabstopp)
    const sg = this.el.querySelector('.ge-scheme');
    sg.querySelectorAll('[data-scheme]').forEach(b => b.addEventListener('click', () => this.sv.set(b.dataset.scheme)));
    sg.addEventListener('keydown', e => {
      const list = [...sg.querySelectorAll('[data-scheme]')], i = list.indexOf(document.activeElement);
      const d = ['ArrowRight', 'ArrowDown'].includes(e.key) ? 1 : ['ArrowLeft', 'ArrowUp'].includes(e.key) ? -1 : 0;
      if (!d || i < 0) return;
      e.preventDefault(); e.stopPropagation();
      const n = list[(i + d + list.length) % list.length];
      n.focus(); this.sv.set(n.dataset.scheme);
    });
    const sm = this.el.querySelector('[data-act=schememenu]');
    sm.addEventListener('click', () => menu(sm, [
      ...(this.boot.sections || []).map(s => ({ label: t('Hintergrund: {name}', { name: s.label }), checked: s.id === this.sectionId, run: () => { this.sectionId = s.id; this.sv.refresh(); this.renderScheme(); this.panels.refresh(); } })),
      { sep: true },
      { label: t('Dunkel-Fassung vorschlagen'), run: () => this.suggestDark() },
      { label: t('Eigene Dunkel-Farben entfernen'), run: () => this.clearDark() },
      { sep: true },
      { label: t('Grafik prüfen (Kontrast, Barrierefreiheit) …'), run: () => this.panels.showCheck(true) },
    ]));
    this.renderScheme();
    const ex = this.el.querySelector('[data-act=export]');
    ex.addEventListener('click', () => menu(ex, [
      { label: t('SVG für das Web (optimiert)'), run: () => this.download('web') },
      { label: t('SVG für Desktop-Programme (Affinity, Illustrator, Inkscape, Figma)'), run: () => this.download('desktop') },
      ...(this.root.querySelector('[data-ge-ftoken],[data-ge-stoken],[data-ge-ctoken],[data-ge-fdark],[data-ge-sdark],[data-ge-cdark]') ? [{ label: t('SVG für Desktop-Programme – Dunkel-Fassung'), run: () => this.download('desktop-dark') }] : []),
      { label: t('PDF (Vektor)'), run: () => this.download('pdf') },
      { sep: true },
      { label: t('SVG-Datei importieren …'), run: () => this.importFile() },
      { sep: true },
      { label: t('Speichern unter …'), key: SHIFT + MOD + 'S', run: () => this.save(true) },
      { label: t('Tastaturkürzel'), key: '?', run: () => this.shortcuts() },
    ]));
  }

  fileBase() { return (this.doc.name || this.doc.title || 'grafik').toLowerCase().replace(/[^a-z0-9äöüß_-]+/gi, '-').replace(/^-+|-+$/g, '') || 'grafik'; }

  /**
   * Herunterladen: web (optimiert wie gespeichert), desktop (Ebenen, eingerückt), pdf (Vektor, Texte möglichst in Kurven).
   * Kein Pixelformat – der Editor arbeitet nur mit Vektoren.
   */
  async download(kind) {
    this.finishPen(); this.endText();
    let blob, ext = 'svg';
    this.busy(true);
    try {
      if (kind === 'web') {
        const w = await this.webSvg();
        blob = new Blob([w.svg], { type: 'image/svg+xml' });
        toast(t('Für das Web optimiert: {a} → {b}', { a: kb(w.before), b: kb(w.after) }));
      } else if (kind === 'desktop' || kind === 'desktop-dark') {
        // Design-Farben sind schon feste Farben (Hex) – die Dunkel-Fassung setzt die Dunkel-Werte ein
        blob = new Blob([desktopSvg(kind === 'desktop-dark' ? bakeScheme(this, this.getSvg(), 'dark') : this.getSvg())], { type: 'image/svg+xml' });
      } else {
        ext = 'pdf';
        const svg = await this.outlinedCopy(this.getSvg(), true);
        blob = await pdfBlob(this.boot.modules?.pdf, svg, m => toast(m, 'info'));
      }
    } catch (e) {
      console.error(e);
      toast(t('Der Export ist fehlgeschlagen.'), 'error');
      return;
    } finally { this.busy(false); }
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = this.fileBase() + (kind === 'desktop' ? '-desktop' : kind === 'desktop-dark' ? '-desktop-dunkel' : '') + '.' + ext;
    document.body.append(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 4000);
  }

  /** Einstellungen der Web-Ausgabe (je Browser gemerkt): Nachkommastellen, Texte in Kurven */
  get webPrefs() {
    let p = {};
    try { p = JSON.parse(localStorage.getItem('klxm-studio-grafik-web') || '{}') || {}; } catch { /* privat */ }
    return { decimals: p.decimals || 'auto', outline: p.outline !== false };
  }
  set webPrefs(v) { try { localStorage.setItem('klxm-studio-grafik-web', JSON.stringify(v)); } catch { /* privat */ } }

  /** Texte mit Webschriften (selbst gehostet): im Bild auf der Website fehlt die Schrift → Kurven empfohlen */
  webFontTexts() {
    const files = this.boot.fontFiles || {};
    return [...this.root.querySelectorAll('text')].filter(tx => {
      const fam = (getProp(tx, 'font-family') || '').split(',')[0].trim().replace(/^["']|["']$/g, '');
      return Object.keys(files).some(k => k.toLowerCase() === fam.toLowerCase());
    });
  }

  /**
   * Kopie der Grafik mit Texten in Kurven (für Web/PDF) – gerendert in einer unsichtbaren Kopie im Editor, damit die
   * Positionen des Browsers gelten. Die Arbeitsfassung behält ihre Texte. all = auch Texte ohne Webschrift versuchen.
   */
  async outlinedCopy(svg, all = false) {
    const files = this.boot.fontFiles || {};
    const host = document.createElement('div');
    host.style.cssText = 'position:absolute;left:-20000px;top:0;width:800px;height:500px;visibility:hidden';
    const c = document.importNode(new DOMParser().parseFromString(svg, 'image/svg+xml').documentElement, true);
    host.append(c);
    this.shadow.append(host);
    try {
      const texts = [...c.querySelectorAll('text')].filter(tx => {
        if (all) return true;
        const fam = (getProp(tx, 'font-family') || '').split(',')[0].trim().replace(/^["']|["']$/g, '');
        return Object.keys(files).some(k => k.toLowerCase() === fam.toLowerCase());
      });
      if (texts.length) { try { await document.fonts.ready; } catch { /* egal */ } await outlineAll(this, texts); }
      c.removeAttribute('style');
      return new XMLSerializer().serializeToString(c);
    } finally { host.remove(); }
  }

  /**
   * Web-Fassung: (Texte in Kurven) → optimieren → Sicherheitsvergleich der Darstellung. Rückgabe { svg, before, after, safe }.
   * opts überschreibt die gemerkten Einstellungen (Speichern-Dialog).
   */
  async webSvg(opts = this.webPrefs) {
    const src = this.getSvg();
    let base = src;
    if (opts.outline && this.webFontTexts().length) base = await this.outlinedCopy(src);
    let out = optimizeSvg(base, { decimals: opts.decimals });
    let safe = false;
    // Sicherheitsnetz: Optimierung darf die Darstellung nicht ändern – sonst nur Editor-Daten entfernen
    if (!(await checkSame(optimizeSvg(base, { safe: true }), out))) { out = optimizeSvg(base, { safe: true }); safe = true; }
    return { svg: out, before: new Blob([src]).size, after: new Blob([out]).size, safe };
  }

  /** SVG-Datei importieren (als Gruppe in die Grafik) – Pixelbilder werden nicht übernommen (Meldung) */
  importFile() {
    const inp = document.createElement('input');
    inp.type = 'file'; inp.accept = '.svg,image/svg+xml';
    inp.addEventListener('change', async () => {
      const f = inp.files?.[0];
      if (!f) return;
      if (!/svg/i.test(f.type) && !/\.svg$/i.test(f.name)) { toast(t('Der Grafik-Editor arbeitet nur mit Vektoren – Pixelbilder (PNG, JPG …) lassen sich nicht einfügen.'), 'warn'); return; }
      this.importSvg(await f.text(), f.name.replace(/\.svg$/i, ''));
    });
    inp.click();
  }

  /**
   * SVG-Text einfügen: erst über den Server bereinigen, dann als Gruppe in die Mitte (bzw. an die Stelle at = Dokumentpunkt).
   * inPlace: eigene Kopie aus einem anderen Grafik-Editor – Objekte ohne Hülle an derselben Stelle wie im Original.
   */
  async importSvg(text, name = '', { at = null, inPlace = false } = {}) {
    const res = await this.api('POST', this.boot.urls.clean, { svg: text });
    if (!res) return;
    if (res.images) toast(t('Pixelbilder in der SVG wurden nicht übernommen – der Grafik-Editor arbeitet nur mit Vektoren.'), 'warn');
    const doc = new DOMParser().parseFromString(res.svg, 'image/svg+xml').documentElement;
    const vb = (doc.getAttribute('viewBox') || '0 0 100 100').split(/[\s,]+/).map(Number);
    const g = document.createElementNS(NS, 'g');
    if (name) g.setAttribute('data-name', name);
    // Darstellung der Wurzel (z. B. fill="none" stroke="currentColor" bei Strich-Symbolen) gilt für alle Kinder → auf die Gruppe
    const ROOT_ONLY = ['xmlns', 'version', 'baseProfile', 'viewBox', 'width', 'height', 'x', 'y', 'id', 'preserveAspectRatio', 'zoomAndPan', 'data-name', 'data-ge-clip'];
    for (const a of [...doc.attributes]) if (!a.name.startsWith('xmlns') && !ROOT_ONLY.includes(a.name)) g.setAttribute(a.name, a.value);
    for (const c of [...doc.children]) if (!['title', 'desc', 'metadata'].includes(c.localName)) g.append(document.importNode(c, true));
    this.freshIds(g);
    remapClasses(g, this.root);
    const own = inPlace && !g.attributes.length;
    if (!own) {
      const tvb = this.vb, sc = Math.min(1, tvb.w * 0.8 / (vb[2] || 1), tvb.h * 0.8 / (vb[3] || 1));
      const cx = at ? at.x : tvb.x + tvb.w / 2, cy = at ? at.y : tvb.y + tvb.h / 2;
      g.setAttribute('transform', `translate(${num(cx - vb[2] * sc / 2 - vb[0] * sc)} ${num(cy - vb[3] * sc / 2 - vb[1] * sc)})${sc !== 1 ? ` scale(${num(sc, 5)})` : ''}`);
    }
    this.target.append(g);
    if (own) {
      // Kopie aus einer anderen Grafik: die Objekte selbst einfügen (keine zusätzliche Gruppe), Lage wie im Original
      const kids = [...g.children];
      g.replaceWith(...kids);
      if (this.target !== this.root) for (const k of kids) if (isDrawn(k)) applyTransform(k, new DOMMatrix(), this.root);
      this.sel = kids.filter(isDrawn);
      this.commit(t('Eingefügt'));
      return;
    }
    this.sel = [g];
    this.commit(t('Importiert'));
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

  // ================================================================= Hell / Dunkel (scheme.js)
  /** Umschalter und Knopf „Hintergrund“ auf den Stand bringen */
  renderScheme() {
    const m = this.sv.mode;
    this.el.querySelectorAll('.ge-scheme [data-scheme]').forEach(b => { const on = b.dataset.scheme === m; b.setAttribute('aria-checked', String(on)); b.tabIndex = on ? 0 : -1; });
    const s = (this.boot.sections || []).find(x => x.id === this.sectionId) || (this.boot.sections || [])[0];
    const lbl = this.el.querySelector('[data-bglbl]'), sw = this.el.querySelector('[data-bgsw]');
    if (lbl) lbl.textContent = s ? s.label : t('Hintergrund');
    sw?.style.setProperty('--c', s ? (m === 'dark' ? s.dark : s.light) : '#fff');
    sw?.style.setProperty('--c2', s ? s.dark : '#111');
    this.el.querySelector('[data-act=schememenu]')?.setAttribute('aria-label', t('Hintergrund der Vorschau: {name}', { name: s ? s.label : '' }));
  }

  /** „Dunkel-Fassung vorschlagen“ – für die Auswahl, ohne Auswahl für die ganze Grafik; ein Schritt im Protokoll */
  suggestDark() {
    const els = this.sel.length ? this.sel.flatMap(el => [el, ...el.querySelectorAll('*')]) : paintedElements(this.root);
    const n = suggestDark(this, els);
    if (!n) { toast(t('Die Dunkel-Fassung passt schon – nichts zu ändern.'), 'info'); return; }
    this.commit(t('Dunkel-Fassung vorgeschlagen'));
    if (this.sv.mode === 'light') this.sv.set('side'); else this.sv.refresh();
    toast(t('Dunkel-Fassung vorgeschlagen: {n} Farben angepasst. Design-Farben folgen dem Design.', { n }));
  }

  /** Eigene Dunkel-Farben entfernen (Auswahl bzw. ganze Grafik) */
  clearDark() {
    const els = this.sel.length ? this.sel.flatMap(el => [el, ...el.querySelectorAll('*')]) : [...this.root.querySelectorAll('*')];
    let n = 0;
    for (const el of els) for (const a of ['data-ge-fdark', 'data-ge-sdark', 'data-ge-cdark']) if (el.hasAttribute(a)) { el.removeAttribute(a); n++; }
    if (!n) { toast(t('Keine eigenen Dunkel-Farben vorhanden.'), 'info'); return; }
    this.commit(t('Eigene Dunkel-Farben entfernt'));
    this.sv.refresh();
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
          <label class="f-check"><input type="checkbox" name="scale"> ${esc(t('Inhalt mit skalieren'))}</label>
          <label class="f">${esc(t('Breite auf der Website (px)'))}<input type="number" name="dispw" min="16" max="4000" step="1" placeholder="${esc(num(vb.w))}" value="${esc(this.root.getAttribute('data-ge-dispw') || '')}"><span class="f-help">${esc(t('Für die Prüfung der Schriftgröße (mindestens 12 px). Leer = so breit wie die Zeichenfläche.'))}</span></label>`
        : `<p class="f-help">${esc(t('Feste Größe: {w} × {h}', { w: num(vb.w), h: num(vb.h) }))}</p>`}
        <div class="ge-row2"><label class="f">${esc(t('Raster: Abstand'))}<input type="number" name="gs" min="0.1" max="1000" step="0.1" value="${esc(num(readGrid(this.root, f).size, 2))}"></label>
          <label class="f">${esc(t('Unterteilungen'))}<input type="number" name="gsub" min="1" max="20" step="1" value="${esc(readGrid(this.root, f).sub)}"></label></div>
        <label class="f">${esc(t('Titel in der Grafik'))}<input name="title" maxlength="180" value="${esc(title)}"><span class="f-help">${esc(t('Wird als <title> gespeichert – Screenreader lesen ihn, wenn die Grafik direkt eingebunden ist.'))}</span></label>
        <label class="f">${esc(t('Beschreibung in der Grafik'))}<textarea name="desc" rows="3" maxlength="1000">${esc(desc)}</textarea><span class="f-help">${esc(t('Ausführlicher Text für Infografiken (<desc>). Texte in der Grafik bleiben Text.'))}</span></label>
        ${this.root.querySelector('path[data-ge-conn]') ? `<button type="button" class="adm-btn adm-btn--small" data-flowdesc>${ico('flow-arrow')} ${esc(t('Ablauf des Diagramms als Beschreibung einfügen'))}</button>` : ''}
        ${vers.length ? `<h3 class="ge-modal__h3">${esc(t('Frühere Fassungen'))}</h3><ul class="ge-versions">${vers.map(v => `<li><span>${esc(new Date(v.created_at.replace(' ', 'T')).toLocaleString())}</span><button type="button" class="adm-btn adm-btn--small" data-ver="${v.id}">${esc(t('Laden'))}</button></li>`).join('')}</ul>` : ''}`,
      onOpen: (d, finish) => {
        // Lesereihenfolge der Verbinder (connect.js describe) – für Bildschirmleser, die die Grafik nicht sehen
        d.querySelector('[data-flowdesc]')?.addEventListener('click', () => {
          const ta = d.querySelector('[name=desc]');
          const flow = this.conn.describe();
          ta.value = (ta.value.trim() ? ta.value.trim() + '\n' : '') + t('Ablauf:') + '\n' + flow;
          ta.focus();
        });
        d.querySelectorAll('[data-ver]').forEach(b => b.addEventListener('click', async () => {
        const res = await this.api('GET', this.boot.urls.version + '?ref=' + encodeURIComponent(this.doc.ref) + '&id=' + b.dataset.ver);
        if (!res?.ok) return;
        finish(null);
        this.load(res.svg); this.commit(t('Frühere Fassung geladen'));
      }));
      },
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
      const dw = Math.round(+r.dispw || 0);
      if (dw >= 16 && dw <= 4000) this.root.setAttribute('data-ge-dispw', String(dw)); else this.root.removeAttribute('data-ge-dispw');
    }
    this.setA11y(r.title.trim(), r.desc.trim());
    const gs = Math.max(0.1, Math.min(1000, +r.gs || 10)), gsub = Math.max(1, Math.min(20, Math.round(+r.gsub || 1)));
    const def = readGrid(document.createElementNS(NS, 'svg'), f);
    if (gs !== def.size || gsub !== def.sub) this.root.setAttribute('data-ge-grid', num(gs, 2) + ' ' + gsub); else this.root.removeAttribute('data-ge-grid');
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
      [MOD + 'R / ' + MOD + '; / ' + MOD + "'", t('Lineale / Hilfslinien / Raster')], [MOD + '7 / ' + ALT + MOD + '7', t('Beschneiden / Maske freigeben')],
      [MOD + '↵', t('In Kurven umwandeln')], [MOD + 'J', t('Pfade verbinden')], [MOD + 'B / I / U', t('Text: fett / kursiv / unterstrichen')],
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
    if (this.compoundEdit) { this.scope = this.root; await this.endCompound(); }
    const d = this.doc;
    if (d.target === 'none') {
      // Einbettung ohne Ablage (z. B. Motion): bereinigtes SVG an den Aufrufer, der es selbst speichert
      const res = await this.api('POST', this.boot.urls.clean, { svg: this.getSvg() });
      if (!res) return null;
      let w = null;
      try { w = await this.webSvg(); } catch { /* ohne Web-Fassung */ }
      this.savedAt = this.hi; this.changed(false);
      // svg = bereinigte Arbeitsfassung (ids, Ebenen), web = optimierte Fassung für die Website
      const result = { target: 'none', item: null, svg: res.svg, web: w?.svg || res.svg, format: this.format };
      this.opts.onSave?.(result); this.emit('save', result);
      return result;
    }
    if (d.shared && !asNew) asNew = true;
    let meta = {};
    let prefs = this.webPrefs, web = null;
    const hasWebFont = this.webFontTexts().length > 0;
    // Abschnitt „Für das Web“ im Speichern-Dialog: Größe vorher/nachher, Nachkommastellen, Texte in Kurven
    const webBox = `<fieldset class="ge-webopt"><legend>${esc(t('Für das Web'))}</legend>
        <p class="ge-webopt__size" data-websize aria-live="polite">${esc(t('Wird berechnet …'))}</p>
        <label class="f">${esc(t('Genauigkeit (Nachkommastellen)'))}<select name="decimals" data-dec>${[['auto', t('Automatisch')], ['1', '1'], ['2', '2'], ['3', '3'], ['4', '4']].map(([v, l]) => `<option value="${v}"${String(prefs.decimals) === v ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></label>
        ${hasWebFont ? `<label class="f-check"><input type="checkbox" name="outline" data-outl${prefs.outline ? ' checked' : ''}> ${esc(t('Texte in Kurven umwandeln (empfohlen)'))}</label>
        <p class="f-help">${esc(t('Die Schriften der Website wirken in SVG-Bildern nicht – Besucher sähen eine Ersatzschrift. Im Editor bleiben die Texte bearbeitbar.'))}</p>` : ''}
      </fieldset>`;
    const bindWeb = dlg => {
      const out = dlg.querySelector('[data-websize]'), dec = dlg.querySelector('[data-dec]'), ol = dlg.querySelector('[data-outl]');
      let seq = 0;
      const calc = async () => {
        const my = ++seq;
        prefs = { decimals: dec.value, outline: ol ? ol.checked : prefs.outline };
        out.textContent = t('Wird berechnet …');
        try {
          const w = await this.webSvg(prefs);
          if (my !== seq) return;
          web = w;
          const pc = Math.round((1 - w.after / Math.max(1, w.before)) * 100);
          out.textContent = (pc >= 0 ? t('Größe: {a} → {b} ({p} % kleiner)', { a: kb(w.before), b: kb(w.after), p: pc })
            : t('Größe: {a} → {b} (größer: Texte als Kurven)', { a: kb(w.before), b: kb(w.after) })) + (w.safe ? ' · ' + t('sichere Stufe') : '');
        } catch (e) { console.error(e); out.textContent = t('Optimierung nicht möglich – es wird unverändert gespeichert.'); web = null; }
      };
      dec.addEventListener('change', calc); ol?.addEventListener('change', calc);
      calc();
    };
    const isMedia = d.target !== 'graphics';
    const first = isMedia ? (!d.mediaId || asNew) : (!d.id || asNew);
    if (first) {
      const title = [...this.root.children].find(c => c.localName === 'title')?.textContent || d.title || '';
      const body = isMedia
        ? `<label class="f">${esc(t('Dateiname'))}<input name="name" maxlength="120" value="${esc(d.name || this.fileBase())}" required></label>
           <label class="f">${esc(t('Titel'))}<input name="title" maxlength="180" value="${esc(title)}"></label>
           <label class="f">${esc(t('Alt-Text (Bildbeschreibung)'))}<textarea name="alt" rows="2" maxlength="250" data-alt>${esc(d.alt || '')}</textarea><span class="f-help">${esc(t('Pflicht für Grafiken mit Inhalt: Was zeigt die Grafik? Bei Infografiken die Kernaussage.'))}</span></label>
           ${suggestAlt(this) ? `<button type="button" class="adm-btn adm-btn--small" data-altsug title="${esc(suggestAlt(this))}">${ico('sparkle')} ${esc(t('Vorschlag aus Titel und Texten'))}</button>` : ''}
           <label class="f-check"><input type="checkbox" name="decorative" data-deco> ${esc(t('Rein dekorativ (kein Alt-Text nötig)'))}</label>`
        : `<label class="f">${esc(t('Name'))}<input name="name" maxlength="120" value="${esc(d.name || '')}" required></label>
           ${d.kind === 'preview' || this.format === 'preview' ? `<label class="f">${esc(t('Block'))}<select name="block"><option value="">${esc(t('– keinem Block zuordnen –'))}</option>${Object.entries({ ...(d.block && !(this.boot.blocks || {})[d.block] ? { [d.block]: d.name || d.block } : {}), ...(this.boot.blocks || {}) }).map(([k, v]) => `<option value="${esc(k)}"${k === d.block ? ' selected' : ''}>${esc(v)} (${esc(k)})</option>`).join('')}</select></label>
             <label class="f-check"><input type="checkbox" name="assign" checked> ${esc(t('In der Blockauswahl dieser Website verwenden'))}</label>` : ''}`;
      const r = await modal(this.el, {
        title: asNew ? t('Speichern unter') : isMedia ? t('In der Mediathek speichern') : t('Grafik speichern'), ok: t('Speichern'), body: body + webBox,
        onOpen: dlg => {
          const deco = dlg.querySelector('[data-deco]'), alt = dlg.querySelector('[data-alt]');
          if (deco) { const s = () => { alt.required = !deco.checked; alt.disabled = deco.checked; }; deco.addEventListener('change', s); s(); }
          // Alt-Text-Vorschlag: Titel (aus dem Feld darüber) und Texte der Grafik in Lesereihenfolge
          dlg.querySelector('[data-altsug]')?.addEventListener('click', () => { const tt = dlg.querySelector('[name=title]')?.value.trim(); const s = suggestAlt(this); alt.value = tt && !s.startsWith(tt) ? tt + ': ' + s : s; alt.focus(); });
          bindWeb(dlg);
        },
      });
      if (!r) return null;
      meta = r;
      // Titel gehört zur Grafik: als Schritt ins Protokoll, sonst verschwände er mit Rückgängig/Wiederholen
      if (isMedia && r.title) { this.setA11y(r.title, [...this.root.children].find(c => c.localName === 'desc')?.textContent || ''); this.commit(t('Dokument geändert')); }
    }
    this.webPrefs = prefs;
    try { if (!web) web = await this.webSvg(prefs); } catch (e) { console.error(e); web = null; }
    const body = { svg: this.getSvg(), web: web?.svg || '', target: isMedia ? 'media' : 'graphics' };
    if (isMedia) Object.assign(body, first ? meta : {}, { mediaId: first ? null : d.mediaId });
    // Alt-Text aus „Prüfen“ (beim Ersetzen einer vorhandenen Grafik)
    if (isMedia && !first && d.altDirty) body.alt = d.alt;
    else Object.assign(body, { kind: d.kind || this.format, id: first ? null : d.id, name: meta.name ?? d.name, block: meta.block ?? d.block, assign: meta.assign ?? false });
    const btn = this.el.querySelector('[data-act=save]');
    btn.disabled = true;
    const res = await this.api('POST', this.boot.urls.save, body);
    btn.disabled = false;
    if (!res) return null;
    if (isMedia) { d.mediaId = res.item.id; d.ref = 'media:' + res.item.id; d.shared = false; if (first) { d.name = meta.name; d.title = meta.title; d.alt = meta.alt; d.decorative = !!meta.decorative; } }
    else { d.id = res.item.id; d.ref = 'graphic:' + res.item.id; d.name = res.item.name; d.block = res.item.block; }
    d.versions = res.versions || [];
    this.savedAt = this.hi;
    this.changed(false);
    d.altDirty = false;
    // Prüfung beim Speichern (blockiert nicht): Hinweise im Bereich „Prüfen“, Sprung zum Element
    let hints = 0;
    try { hints = this.panels.check.run().filter(i => i.sev !== 'info').length; } catch (e) { console.error(e); }
    if (hints) this.panels.showCheck(false);
    toast((isMedia ? t('In der Mediathek gespeichert') : t('Gespeichert')) + (web ? ' · ' + t('Web-Fassung {a} → {b}', { a: kb(web.before), b: kb(web.after) }) : '')
      + (hints ? ' · ' + t('{n} Hinweise zur Barrierefreiheit (Bereich „Prüfen“)', { n: hints }) : ''));
    const result = { target: body.target, item: res.item, svg: body.svg, format: this.format };
    this.opts.onSave?.(result);
    this.emit('save', result);
    return result;
  }

  destroy() {
    document.removeEventListener('keydown', this.keyHandler);
    document.removeEventListener('paste', this.pasteHandler);
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
