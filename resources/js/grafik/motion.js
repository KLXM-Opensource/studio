/*
 * Grafik-Editor – Persona „Motion“ (Ausbaustufe 4): Zeitleiste unter der Zeichenfläche, Bereich „Bewegung | Interaktion | Szene“
 * rechts. Bedienung wie in der Erweiterung Motion (Zeitleiste mit Schlüsselbildern, fertige Bewegungen, Verläufe, Auslöser):
 *   - Abspielkopf ziehen oder ins Lineal klicken (springt an Schlüsselbilder und volle Sekunden, ⌥ = frei), Leertaste = abspielen
 *   - Objekt ziehen = Schlüsselbild „Position“ am Abspielkopf (bei 0 s ohne Bewegung: Grundform verschieben wie in Vektor)
 *   - Rauten ziehen = Zeitpunkt ändern (⇧ = mehrere, ⌥ = ohne Einrasten), ⌫ löscht, ←/→ verschiebt um 0,1 s
 *   - Werte am Abspielkopf im Bereich „Bewegung“ (Raute daneben = Schlüsselbild an/aus), Verlauf je Abschnitt mit Kurven-Editor
 * Vorschau: eigenes <style> im Shadow Root (anim.js → previewCss), das Dokument ändert sich nur über Schlüsselbilder – ein Schritt
 * im Protokoll je Handlung. MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { toast } from '../_toast.js';
import { num, parentMatrix, pt, docBounds } from './geom.js';
import { nameOf, typeLabel, uid, getProp } from './doc.js';
import { h, menu, SHIFT, ALT, isMac } from './ui.js';
import { hex } from './color.js';
import { paintIn, invertLightness } from './scheme.js';
import {
  PROPS, COLOR_PROPS, EASES, PRESETS, getAnim, setAnim, getScene, setScene, animated, interactive, stateAt, valueAt, localTime, totalTime,
  pivotOf, boxOf, presetKeys, followKeys, previewCss, parseNumber, formatNumber, easePts, bez, isCountText, bakeAt, hasMotion,
} from './anim.js';

const FPS = 30;
const DRAWABLE = 'path,rect,circle,ellipse,line,polyline,polygon';
const label = {
  pos: () => t('Position'), s: () => t('Größe'), r: () => t('Drehung'), o: () => t('Deckkraft'), w: () => t('Linie zeichnen'),
  f: () => t('Füllung'), c: () => t('Kontur'),
};
const EASE_LABEL = {
  smooth: () => t('sanft'), out: () => t('abbremsen'), in: () => t('beschleunigen'), linear: () => t('gleichmäßig'), spring: () => t('federnd'), hold: () => t('halten'),
};
const PRESET_LABEL = {
  fade: () => t('Einblenden'), left: () => t('Von links'), right: () => t('Von rechts'), up: () => t('Von unten'), down: () => t('Von oben'),
  zoom: () => t('Heranzoomen'), pop: () => t('Aufploppen'), spin: () => t('Drehen'), pulse: () => t('Pulsieren'), shake: () => t('Wackeln'),
  float: () => t('Schweben'), draw: () => t('Linie zeichnen'), out: () => t('Ausblenden'), count: () => t('Zählen'), follow: () => t('Pfad folgen'),
};
const GROUP_LABEL = { in: () => t('Erscheinen'), loop: () => t('Dauerhaft'), out: () => t('Verschwinden'), special: () => t('Text und Wege') };
const de = (v, d = 2) => (+v).toLocaleString(document.documentElement.lang || 'de', { minimumFractionDigits: 0, maximumFractionDigits: d });
const sec = v => t('{n} s', { n: de(v) });
const PLAY = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" width="1em" height="1em" fill="currentColor"><path d="M7 4.5v15l12.5-7.5z"/></svg>';
const PAUSE = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" width="1em" height="1em" fill="currentColor"><path d="M6 4h4.5v16H6zM13.5 4H18v16h-4.5z"/></svg>';
const START = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" width="1em" height="1em" fill="currentColor"><path d="M5 4h2.5v16H5zM20 4.5v15L9 12z"/></svg>';
const KEY = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" width="1em" height="1em" fill="currentColor"><path d="M12 3l9 9-9 9-9-9z"/></svg>';
const KEY_OFF = '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2"><path style="fill:none" d="M12 4l8 8-8 8-8-8z"/></svg>';

export class MotionPersona {
  constructor(ed) {
    this.ed = ed;
    this.time = 0;
    this.playing = false;
    this.pps = 120;            // Pixel je Sekunde in der Zeitleiste
    this.snap = true;
    this.keySel = new Set();   // "id|prop|i"
    this.collapsed = new Set();
    this.reduced = false;      // Vorschau „Bewegung reduziert“ (Endzustand)
    this.counts = [];          // laufende Zähler der Vorschau (Textknoten, Original)
    this.mounted = false;
    this.style = document.createElement('style');
    this.style.setAttribute('data-ge-motion-preview', '');
    // Abgeleitete Angaben (Drehpunkt, Lage der Tooltips) vor jedem Speichern/Protokollschritt aus der Geometrie – deterministisch,
    // gilt auch, wenn in der Persona Vektor verschoben wurde. Vorschau-Zähler vorher zurück auf den Originaltext.
    const orig = ed.getSvg.bind(ed);
    ed.getSvg = () => {
      const had = this.counts.length;
      this.restoreCounts();
      this.syncDerived();
      const s = orig();
      if (had && this.mounted) this.pose();
      return s;
    };
    // Verbinder und Text in Formen nicht während der Vorschau neu berechnen (sie sähen die bewegten Formen)
    const cu = ed.conn.update.bind(ed.conn);
    ed.conn.update = () => { if (!this.posed) cu(); };
  }

  get persona() {
    return { id: 'motion', label: t('Motion'), icon: 'film-strip', mount: (ed, area) => this.mount(area), unmount: () => this.unmount() };
  }

  get root() { return this.ed.root; }
  get scene() { return getScene(this.root); }
  get vb() { return this.ed.vb; }

  // ================================================================= Abgeleitete Angaben
  syncDerived() {
    const root = this.root;
    if (!root) return;
    // Maße ohne Vorschau (Text-Layout hängt in Chrome von der Skalierung ab); kleine Abweichungen (Zoom, Rundung) bleiben stehen
    const keep = this.style.textContent;
    if (keep) this.style.textContent = '';
    const tol = Math.max(1.5, Math.max(this.vb.w, this.vb.h) / 400);
    for (const el of animated(root)) {
      const a = getAnim(el);
      if (!a || !el.isConnected) continue;
      const p = pivotOf(el);
      // kleine Abweichungen (Textmaße, Rundung) nicht schreiben – sonst änderte schon das Öffnen die Datei
      if (!a.p || Math.abs(a.p[0] - p[0]) > tol || Math.abs(a.p[1] - p[1]) > tol) { a.p = p; setAnim(el, a); }
    }
    for (const el of root.querySelectorAll('[data-ge-tip]')) {
      const box = this.tipBox(el);
      const old = (el.getAttribute('data-ge-tipbox') || '').split(' ').map(Number);
      if (box && (old.length !== 4 || box.split(' ').some((v, i) => Math.abs(+v - old[i]) > tol))) el.setAttribute('data-ge-tipbox', box);
    }
    if (keep) this.style.textContent = keep;
  }

  /** Lage des sichtbaren Tooltips (über dem Objekt, sonst darunter; innerhalb der Zeichenfläche) */
  tipBox(el) {
    const text = el.getAttribute('data-ge-tip') || '';
    if (!text) return null;
    const vb = this.vb;
    const dw = parseFloat(this.root.getAttribute('data-ge-dispw')) || 0;
    const fs = dw > 0 ? 14 * vb.w / dw : Math.max(6, Math.min(vb.w, vb.h * 1.6) / 45);
    const c = document.createElement('canvas').getContext('2d');
    c.font = `${fs}px system-ui, -apple-system, Segoe UI, Roboto, sans-serif`;
    const w = Math.min(vb.w, c.measureText(text).width + fs * 1.4), hh = fs * 2;
    // Rahmen ohne Vorschau-Bewegung (Grundform)
    const wasPosed = this.posed && this.style.textContent;
    if (wasPosed) this.style.textContent = '';
    const b = docBounds(el, this.root);
    if (wasPosed) this.style.textContent = wasPosed;
    if (!b) return null;
    let x = b.x + b.w / 2 - w / 2, y = b.y - hh - fs * 0.4;
    if (y < vb.y) y = Math.min(vb.y + vb.h - hh, b.y + b.h + fs * 0.4);
    x = Math.max(vb.x, Math.min(vb.x + vb.w - w, x));
    return [x, y, w, hh].map(v => num(v, 2)).join(' ');
  }

  // ================================================================= Ein- und Aushängen
  mount(area) {
    const ed = this.ed;
    this.mounted = true;
    this.area = area;
    area.classList.add('gm-area');
    area.innerHTML = this.timelineHtml();
    this.bindTimeline();
    if (!this.side) {
      this.side = h(`<section class="ge-pg ge-pg--motion" aria-label="${esc(t('Motion'))}">
        <div class="ge-pg__tabs"><div class="ge-pg__list" role="tablist">
          <button type="button" role="tab" id="${ed.uid}-mt-anim" aria-controls="${ed.uid}-mp-anim" aria-selected="true" tabindex="0">${esc(t('Bewegung'))}</button>
          <button type="button" role="tab" id="${ed.uid}-mt-ia" aria-controls="${ed.uid}-mp-ia" aria-selected="false" tabindex="-1">${esc(t('Interaktion'))}</button>
          <button type="button" role="tab" id="${ed.uid}-mt-scene" aria-controls="${ed.uid}-mp-scene" aria-selected="false" tabindex="-1">${esc(t('Szene'))}</button>
        </div></div>
        <div class="ge-pg__panel gm-panel" role="tabpanel" id="${ed.uid}-mp-anim" aria-labelledby="${ed.uid}-mt-anim"></div>
        <div class="ge-pg__panel gm-panel" role="tabpanel" id="${ed.uid}-mp-ia" aria-labelledby="${ed.uid}-mt-ia" hidden></div>
        <div class="ge-pg__panel gm-panel" role="tabpanel" id="${ed.uid}-mp-scene" aria-labelledby="${ed.uid}-mt-scene" hidden></div>
      </section>`);
      const tabs = [...this.side.querySelectorAll('[role=tab]')];
      const pick = (b, focus) => { for (const x of tabs) { const on = x === b; x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1; this.side.querySelector('#' + x.getAttribute('aria-controls')).hidden = !on; } if (focus) b.focus(); this.renderSide(); };
      tabs.forEach((b, i) => {
        b.addEventListener('click', () => pick(b));
        b.addEventListener('keydown', e => { const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0; if (d) { e.preventDefault(); pick(tabs[(i + d + tabs.length) % tabs.length], true); } });
      });
      this.bindSide();
    }
    ed.$('.ge-side').prepend(this.side);
    this.side.hidden = false;
    ed.shadow.append(this.style);
    this.offs = [
      ed.on('change', () => { if (!this.dragging) this.refresh(); }),
      ed.on('selection', () => { if (!this.dragging) this.refresh(); }),
    ];
    this.down = e => this.stageDown(e);
    ed.stage.addEventListener('pointerdown', this.down, true);
    this.keys = e => this.onKey(e);
    document.addEventListener('keydown', this.keys, true);
    this.refresh();
    requestAnimationFrame(() => ed.fit());
    ed.hint(t('Motion: Abspielkopf ziehen, Objekt ziehen = Schlüsselbild „Position“, Leertaste = abspielen. Werte und fertige Bewegungen im Bereich „Bewegung“.'));
  }

  unmount() {
    const ed = this.ed;
    this.stop();
    this.mounted = false;
    this.restoreCounts();
    this.style.textContent = '';
    this.style.remove();
    this.posed = false;
    this.offs?.forEach(f => f());
    ed.stage.removeEventListener('pointerdown', this.down, true);
    document.removeEventListener('keydown', this.keys, true);
    if (this.side) this.side.hidden = true;
    this.area?.classList.remove('gm-area');
    requestAnimationFrame(() => { ed.fit(); ed.hint(); });
    ed.draw();
  }

  refresh() {
    if (!this.mounted) return;
    const sc = this.scene;
    if (this.time > sc.d) this.time = sc.d;
    // ausgewählte Rauten, die es nicht mehr gibt, vergessen
    for (const k of [...this.keySel]) { const [id, p, i] = k.split('|'); const el = id && this.byId(id); if (!el || !getAnim(el)?.k?.[p]?.[+i]) this.keySel.delete(k); }
    this.renderTimeline();
    this.renderSide();
    this.pose();
  }

  byId(id) { try { return this.root.querySelector('#' + CSS.escape(id)); } catch { return null; } }

  /** id für Objekte mit Bewegung/Interaktion (das Modell hängt an der id – sie bleibt beim Speichern erhalten) */
  ensureId(el) {
    if (!el.id) el.id = uid(this.root, (nameOf(el) || typeLabel(el) || 'objekt').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 24) + '-');
    return el.id;
  }

  // ================================================================= Vorschau
  /** Zustand zur Zeit this.time zeigen (Vorschau-Stil, Zähler, Überlagerung) */
  pose() {
    if (!this.mounted) return;
    const sc = this.scene, root = this.root;
    const tt = this.reduced ? sc.d : this.time;
    const css = previewCss(root, tt, { dark: this.ed.sv?.mode === 'dark', minOpacity: this.playing ? 0 : 0.12 });
    this.style.textContent = css;
    this.posed = !!css || this.counts.length > 0;
    this.applyCounts(tt);
    this.posed = this.posed || this.counts.length > 0;
    this.drawHead();
    this.ed.draw();
    this.renderValues();
  }

  /** Zähler: Text zeigt den Zwischenwert (Original bleibt in getSvg() erhalten) */
  applyCounts(tt) {
    this.restoreCounts();
    for (const el of this.root.querySelectorAll('text[data-ge-anim]')) {
      const a = getAnim(el);
      if (!a?.n) continue;
      const node = [...el.querySelectorAll('*'), el].flatMap(x => [...x.childNodes]).find(n => n.nodeType === 3 && /\d/.test(n.data));
      if (!node) continue;
      const n = parseNumber(node.data);
      if (!n) continue;
      const k = tt <= a.n.t0 ? 0 : tt >= a.n.t1 ? 1 : bez(easePts(a.n.e || 'smooth'), (tt - a.n.t0) / Math.max(1e-6, a.n.t1 - a.n.t0));
      if (k >= 1) continue;
      const v = a.n.a + (a.n.b - a.n.a) * k;
      const orig = node.data;
      node.data = orig.slice(0, n.index) + formatNumber(v, { d: a.n.d, g: a.n.g, p: a.n.p }) + orig.slice(n.index + n.length);
      this.counts.push([node, orig]);
    }
  }
  restoreCounts() { for (const [node, orig] of this.counts) node.data = orig; this.counts = []; }

  // ================================================================= Abspielen
  toggle() { this.playing ? this.stop() : this.play(); }
  play() {
    const sc = this.scene;
    if (this.playing) return;
    this.playing = true;
    // vom Abspielkopf; am Ende: von vorn
    const start = this.time >= sc.d - 1e-3 ? 0 : this.time;
    const total = totalTime(sc);
    let run = sc.dl > 0 && start === 0 ? 0 : sc.dl + start;
    let last = performance.now();
    const step = now => {
      if (!this.playing) return;
      run += (now - last) / 1000; last = now;
      if (run >= total) { this.time = localTime(sc, total); this.stop(); return; }
      this.time = localTime(sc, run);
      this.pose();
      this.raf = requestAnimationFrame(step);
    };
    this.updatePlayBtn();
    this.raf = requestAnimationFrame(step);
  }
  stop() {
    if (!this.playing) return;
    this.playing = false;
    cancelAnimationFrame(this.raf);
    this.updatePlayBtn();
    this.pose();
  }
  updatePlayBtn() {
    const b = this.area?.querySelector('[data-m=play]');
    if (!b) return;
    b.innerHTML = this.playing ? PAUSE : PLAY;
    b.setAttribute('aria-label', this.playing ? t('Anhalten') : t('Abspielen'));
    b.title = (this.playing ? t('Anhalten') : t('Abspielen')) + ' (' + t('Leertaste') + ')';
    b.setAttribute('aria-pressed', String(this.playing));
  }
  seek(tt, snap = this.snap) {
    const sc = this.scene;
    tt = Math.max(0, Math.min(sc.d, tt));
    if (snap) tt = this.snapTime(tt, 'head', 8);
    this.time = Math.round(tt * 1000) / 1000;
    this.pose();
  }

  /** Einrasten: Schlüsselbilder, volle/halbe Sekunden, Abspielkopf; sonst Zehntel */
  snapTime(tt, skip = null, px = 6) {
    const tol = px / this.pps;
    const cand = [0, this.scene.d];
    for (let s = 0; s <= this.scene.d + 1e-9; s += 0.5) cand.push(+s.toFixed(3));
    if (skip !== 'head') cand.push(this.time);
    for (const el of animated(this.root)) {
      const a = getAnim(el);
      for (const p of PROPS) (a?.k?.[p] || []).forEach((k, i) => { if (!skip?.has?.(`${el.id}|${p}|${i}`)) cand.push(k[0]); });
    }
    let best = null;
    for (const c of cand) if (Math.abs(c - tt) <= tol && (best === null || Math.abs(c - tt) < Math.abs(best - tt))) best = c;
    return best ?? Math.round(tt * 10) / 10;
  }

  // ================================================================= Schlüsselbilder
  /** Wert einer Eigenschaft am Abspielkopf (interpoliert), für Farben die Grundfarbe ohne Spur */
  valueNow(el, prop, a = getAnim(el)) {
    const k = a?.k?.[prop];
    if (k?.length) return valueAt(prop, k, this.time);
    if (prop === 'pos') return [0, 0];
    if (prop === 'f' || prop === 'c') { const which = prop === 'f' ? 'fill' : 'stroke'; const v = hex(getProp(this.paintTarget(el), which)) || '#000000'; return v.toLowerCase(); }
    return { s: 1, r: 0, o: 1, w: 1 }[prop];
  }
  paintTarget(el) { return el.localName === 'g' ? (el.querySelector(DRAWABLE + ',text') || el) : el; }

  /** Schlüsselbild setzen bzw. ändern (ohne Protokoll) – Farben: Dunkel-Wert passend vorschlagen */
  setKey(el, prop, tt, value, ease) {
    this.ensureId(el);
    const a = getAnim(el) || { p: pivotOf(el), k: {} };
    a.k ||= {};
    const list = a.k[prop] ||= [];
    tt = Math.round(tt * 1000) / 1000;
    let k = list.find(x => Math.abs(x[0] - tt) < 1e-3);
    const prev = [...list].reverse().find(x => x[0] < tt);
    const e = ease ?? (k ? (prop === 'pos' ? k[3] : k[2]) : prev ? (prop === 'pos' ? prev[3] : prev[2]) : 'smooth');
    if (!k) { k = [tt]; list.push(k); list.sort((x, y) => x[0] - y[0]); }
    if (prop === 'pos') { k[1] = value[0]; k[2] = value[1]; k[3] = e; }
    else if (COLOR_PROPS.includes(prop)) {
      const dark = this.ed.sv?.mode === 'dark';
      const v = String(value).toLowerCase();
      if (dark) { k[1] ||= v; k[3] = v; }
      else { k[1] = v; k[3] = this.darkFor(el, prop, v); }
      k[2] = e;
    } else { k[1] = value; k[2] = e; }
    setAnim(el, a);
  }
  /** Dunkel-Wert einer Farbe: Grundfarbe des Elements → seine Dunkel-Farbe, sonst Helligkeit umkehren */
  darkFor(el, prop, v) {
    const which = prop === 'f' ? 'fill' : 'stroke', tg = this.paintTarget(el);
    const base = hex(getProp(tg, which));
    if (base && base.toLowerCase() === v.toLowerCase()) return (hex(paintIn(this.ed, tg, which, 'dark')) || v).toLowerCase();
    return (invertLightness(v) || v).toLowerCase();
  }
  removeKey(el, prop, tt) {
    const a = getAnim(el);
    if (!a?.k?.[prop]) return;
    a.k[prop] = a.k[prop].filter(x => Math.abs(x[0] - tt) >= 1e-3);
    if (!a.k[prop].length) delete a.k[prop];
    setAnim(el, a);
  }
  hasKey(el, prop) { return !!getAnim(el)?.k?.[prop]?.some(x => Math.abs(x[0] - this.time) < 1e-3); }

  /** Wert am Abspielkopf ändern (Bereich „Bewegung“, Ziehen auf der Fläche): ohne Spur bei t > 0 entsteht Anfang + Ziel */
  changeValue(els, prop, fn, label) {
    for (const el of els) {
      const a = getAnim(el);
      const cur = this.valueNow(el, prop, a);
      const next = fn(cur, el);
      if (!a?.k?.[prop]?.length && this.time > 1e-3) this.setKey(el, prop, 0, cur);
      this.setKey(el, prop, this.time, next);
    }
    if (label) this.ed.commit(label);
  }

  // ================================================================= Fertige Bewegungen
  applyPreset(key, start, len, stagger = 0) {
    const ed = this.ed, sc = this.scene;
    const els = ed.sel.filter(el => el !== this.root);
    if (!els.length) { toast(t('Bitte zuerst ein Objekt auswählen.'), 'info'); return; }
    const p = PRESETS[key];
    if (key === 'count') return this.applyCount(els, start, len, stagger);
    if (key === 'follow') return this.applyFollow(els, start, len);
    let need = 0;
    els.forEach((el, i) => {
      if (p.stroke && !(el.matches(DRAWABLE) || el.querySelector(DRAWABLE))) return;
      const s0 = start + i * stagger;
      const keys = presetKeys(key, s0, len, this.vb);
      this.ensureId(el);
      const a = getAnim(el) || { p: pivotOf(el), k: {} };
      // Wege relativ zur Größe des Objekts bei „Schweben“; „Von links“ … aus dem Rand der Zeichenfläche
      for (const [prop, list] of Object.entries(keys)) a.k[prop] = list;
      setAnim(el, a);
      need = Math.max(need, s0 + len);
    });
    if (need > sc.d + 1e-6) { setScene(this.root, { ...sc, d: Math.ceil(need * 10) / 10 }); toast(t('Die Szene dauert jetzt {s}.', { s: sec(Math.ceil(need * 10) / 10) }), 'info'); }
    if (['spin', 'pulse', 'shake', 'float'].includes(key) && sc.it === 1 && !this.root.hasAttribute('data-ge-motion-loopasked')) {
      // dauerhafte Bewegungen wirken erst als Schleife – nur vorschlagen, nicht umschalten
      toast(t('Tipp: Unter „Szene“ lässt sich „Wiederholen: endlos“ einstellen.'), 'info');
    }
    if (!this.root.hasAttribute('data-ge-motion')) setScene(this.root, this.scene);
    ed.commit(t('Fertige Bewegung: {name}', { name: PRESET_LABEL[key]() }));
    this.seek(Math.max(0, start - 0.2), false);
    this.play();
  }

  applyCount(els, start, len, stagger) {
    const texts = els.filter(el => el.localName === 'text');
    if (!texts.length) { toast(t('„Zählen“ gilt für Texte mit einer Zahl.'), 'info'); return; }
    let ok = 0;
    texts.forEach((el, i) => {
      const n = parseNumber(el.textContent);
      if (!n) return;
      this.ensureId(el);
      const a = getAnim(el) || { p: pivotOf(el), k: {} };
      a.n = { a: 0, b: n.value, d: n.d, g: n.g, p: n.p, t0: start + i * stagger, t1: start + i * stagger + len, e: 'out' };
      setAnim(el, a);
      ok++;
    });
    if (!ok) { toast(t('Im Text steht keine Zahl.'), 'warn'); return; }
    const need = start + (texts.length - 1) * stagger + len;
    const sc = this.scene;
    if (need > sc.d) setScene(this.root, { ...sc, d: Math.ceil(need * 10) / 10 }); else if (!this.root.hasAttribute('data-ge-motion')) setScene(this.root, sc);
    this.ed.commit(t('Fertige Bewegung: {name}', { name: PRESET_LABEL.count() }));
    this.seek(0, false);
    this.play();
  }

  applyFollow(els, start, len) {
    // Weg: der zuletzt gewählte Pfad; bewegt werden die übrigen Objekte
    const path = [...els].reverse().find(el => ['path', 'line', 'polyline', 'polygon', 'circle', 'ellipse', 'rect'].includes(el.localName));
    const movers = els.filter(el => el !== path);
    if (!path || !movers.length) { toast(t('„Pfad folgen“: ein Objekt und zuletzt den Weg (Pfad) auswählen.'), 'info'); return; }
    const orient = !!this.side?.querySelector('[data-f=orient]')?.checked;
    for (const el of movers) {
      this.ensureId(el);
      const a = getAnim(el) || { p: pivotOf(el), k: {} };
      a.p = pivotOf(el);
      const k = followKeys(this.ed, el, path, start, len, 'smooth', orient);
      a.k.pos = k.pos;
      if (k.r) a.k.r = k.r;
      setAnim(el, a);
    }
    const sc = this.scene;
    if (start + len > sc.d) setScene(this.root, { ...sc, d: Math.ceil((start + len) * 10) / 10 }); else if (!this.root.hasAttribute('data-ge-motion')) setScene(this.root, sc);
    this.ed.select(movers);
    this.ed.commit(t('Fertige Bewegung: {name}', { name: PRESET_LABEL.follow() }));
    this.seek(0, false);
    this.play();
  }

  // ================================================================= Zeichenfläche: Ziehen = Schlüsselbild Position
  stageDown(e) {
    const ed = this.ed;
    if (ed.persona !== 'motion' || e.button !== 0 || ed.space || ed.tool !== 'select' || ed.textEd) return;
    const hit = ed.hitAt(e)?.el || null;
    if (!hit) return;
    if (this.playing) this.stop();
    const sel = ed.sel.includes(hit) ? ed.sel : (e.shiftKey ? [...ed.sel, hit] : [hit]);
    // 0 s und noch keine Bewegung: Grundform verschieben wie in der Persona Vektor
    const anyTrack = sel.some(el => getAnim(el)?.k?.pos?.length);
    if (this.time < 1e-3 && !anyTrack) return;
    e.stopPropagation(); e.preventDefault();
    ed.stage.focus({ preventScroll: true });
    if (e.shiftKey && ed.sel.includes(hit)) { ed.select(ed.sel.filter(x => x !== hit)); return; }
    if (!ed.sel.includes(hit) || e.shiftKey) ed.select(sel);
    const start = ed.toDoc(e.clientX, e.clientY);
    const items = ed.sel.map(el => ({ el, base: this.valueNow(el, 'pos'), inv: parentMatrix(el, ed.root).inverse(), had: !!getAnim(el)?.k?.pos?.length, snap: getAnim(el) }));
    let moved = false;
    this.dragging = true;
    ed.track(e, (p, ev) => {
      const dx = p.x - start.x, dy = p.y - start.y;
      if (!moved && Math.hypot(dx, dy) * ed.zoom < 3) return;
      moved = true;
      for (const it of items) {
        const o = pt(it.inv, 0, 0), q = pt(it.inv, dx * (ev.shiftKey && Math.abs(dx) < Math.abs(dy) ? 0 : 1), dy * (ev.shiftKey && Math.abs(dx) >= Math.abs(dy) ? 0 : 1));
        if (!it.had && this.time > 1e-3 && !getAnim(it.el)?.k?.pos?.length) this.setKey(it.el, 'pos', 0, [0, 0]);
        this.setKey(it.el, 'pos', this.time, [it.base[0] + q.x - o.x, it.base[1] + q.y - o.y]);
      }
      this.pose();
      this.renderTimeline();
    }, () => {
      this.dragging = false;
      if (moved) { if (!this.root.hasAttribute('data-ge-motion')) setScene(this.root, this.scene); ed.commit(t('Position (Schlüsselbild)')); }
    });
  }

  // ================================================================= Tastatur
  onKey(e) {
    const ed = this.ed;
    if (ed.persona !== 'motion' || !ed.el.isConnected || ed.el.closest('[inert]')) return;
    if (e.target.closest?.('dialog.ge-modal,.ge-menu')) return;
    if (e.target.matches?.('input,textarea,select,[contenteditable]')) return;
    if (!ed.el.contains(e.target) && e.target !== document.body) return;
    const mod = e.metaKey || e.ctrlKey;
    if (mod) return;
    if (e.key === ' ' && !e.target.closest?.('button,[role=tab],[role=radio]')) { e.preventDefault(); e.stopImmediatePropagation(); this.toggle(); return; }
    if (e.key === ' ' && e.target.closest?.('.gm-key,[data-m=ruler]')) { e.preventDefault(); e.stopImmediatePropagation(); this.toggle(); return; }
    if ((e.key === 'k' || e.key === 'K') && !e.altKey) { e.preventDefault(); e.stopImmediatePropagation(); this.keyHere(); return; }
    if (e.key === 'Home' && e.target.closest?.('.ge-stage,.gm-tl')) { e.preventDefault(); e.stopImmediatePropagation(); this.stop(); this.seek(0, false); return; }
    if (e.key === 'End' && e.target.closest?.('.ge-stage,.gm-tl')) { e.preventDefault(); e.stopImmediatePropagation(); this.stop(); this.seek(this.scene.d, false); return; }
    // Pfeiltasten auf der Fläche: Position am Abspielkopf (bei 0 s ohne Bewegung: wie in Vektor)
    if (e.key.startsWith('Arrow') && e.target.closest?.('.ge-stage') && ed.sel.length) {
      if (this.time < 1e-3 && !ed.sel.some(el => getAnim(el)?.k?.pos?.length)) return;
      e.preventDefault(); e.stopImmediatePropagation();
      const s = e.shiftKey ? 10 : 1, d = { ArrowLeft: [-s, 0], ArrowRight: [s, 0], ArrowUp: [0, -s], ArrowDown: [0, s] }[e.key];
      this.changeValue(ed.sel, 'pos', (cur, el) => { const inv = parentMatrix(el, ed.root).inverse(), o = pt(inv, 0, 0), q = pt(inv, d[0], d[1]); return [cur[0] + q.x - o.x, cur[1] + q.y - o.y]; }, t('Position (Schlüsselbild)'));
      return;
    }
    // Rauten: ⌫ löscht die gewählten
    if ((e.key === 'Backspace' || e.key === 'Delete') && this.keySel.size && e.target.closest?.('.gm-tl,.ge-stage') ) {
      e.preventDefault(); e.stopImmediatePropagation();
      this.deleteKeys();
    }
  }

  /** K: Schlüsselbild für alle vorhandenen Spuren der Auswahl am Abspielkopf (ohne Spuren: Position) */
  keyHere() {
    const els = this.ed.sel;
    if (!els.length) return;
    for (const el of els) {
      const a = getAnim(el);
      const props = PROPS.filter(p => a?.k?.[p]?.length);
      for (const p of props.length ? props : ['pos']) this.setKey(el, p, this.time, this.valueNow(el, p, getAnim(el)));
    }
    if (!this.root.hasAttribute('data-ge-motion')) setScene(this.root, this.scene);
    this.ed.commit(t('Schlüsselbild bei {s}', { s: sec(this.time) }));
  }

  deleteKeys() {
    const by = new Map();
    for (const k of this.keySel) { const [id, p, i] = k.split('|'); (by.get(id) || by.set(id, []).get(id)).push([p, +i]); }
    for (const [id, list] of by) {
      const el = this.byId(id), a = getAnim(el);
      if (!a) continue;
      for (const p of new Set(list.map(x => x[0]))) {
        const drop = new Set(list.filter(x => x[0] === p).map(x => x[1]));
        a.k[p] = (a.k[p] || []).filter((_, i) => !drop.has(i));
        if (!a.k[p].length) delete a.k[p];
      }
      setAnim(el, a);
    }
    const n = this.keySel.size;
    this.keySel.clear();
    this.ed.commit(n === 1 ? t('Schlüsselbild gelöscht') : t('{n} Schlüsselbilder gelöscht', { n }));
  }

  // ================================================================= Zeitleiste
  timelineHtml() {
    return `<div class="gm-tl" aria-label="${esc(t('Zeitleiste'))}" role="region">
      <div class="gm-tl__bar" role="toolbar" aria-label="${esc(t('Zeitleiste'))}">
        <button type="button" class="ge-ib" data-m="start" aria-label="${esc(t('Zum Anfang'))}" title="${esc(t('Zum Anfang'))} (Pos1)">${START}</button>
        <button type="button" class="ge-ib gm-play" data-m="play" aria-pressed="false" aria-label="${esc(t('Abspielen'))}" title="${esc(t('Abspielen'))} (${esc(t('Leertaste'))})">${PLAY}</button>
        <output class="gm-time" data-m="time" aria-live="off"></output>
        <span class="ge-ctx__sep"></span>
        <button type="button" class="adm-btn adm-btn--small" data-m="key" title="${esc(t('Schlüsselbild am Abspielkopf'))} (K)">${KEY} ${esc(t('Schlüsselbild'))}</button>
        <button type="button" class="ge-ib" data-m="snap" aria-pressed="${this.snap}" aria-label="${esc(t('Einrasten'))}" title="${esc(t('Einrasten an Schlüsselbildern und Sekunden ({k} beim Ziehen = frei)', { k: ALT }))}">${ico('magnet')}</button>
        <span class="ge-ctx__sep"></span>
        <button type="button" class="ge-ib" data-m="zout" aria-label="${esc(t('Zeitleiste verkleinern'))}" title="${esc(t('Zeitleiste verkleinern'))}">${ico('minus')}</button>
        <input type="range" class="ge-range gm-zoom" data-m="zoom" min="30" max="600" step="10" value="${this.pps}" aria-label="${esc(t('Zoom der Zeitleiste'))}">
        <button type="button" class="ge-ib" data-m="zin" aria-label="${esc(t('Zeitleiste vergrößern'))}" title="${esc(t('Zeitleiste vergrößern'))}">${ico('plus')}</button>
        <span class="gm-tl__grow"></span>
        <span class="gm-tl__hint">${esc(t('Rauten ziehen, ⌫ löscht'))}</span>
      </div>
      <div class="gm-tl__scroll" data-m="scroll">
        <div class="gm-tl__inner" data-m="inner">
          <div class="gm-row gm-row--ruler"><div class="gm-name gm-name--ruler">${esc(t('Ebene'))}</div><div class="gm-lane gm-ruler" data-m="ruler" role="slider" tabindex="0" aria-label="${esc(t('Abspielkopf'))}" aria-valuemin="0"></div></div>
          <div data-m="rows"></div>
          <div class="gm-head" data-m="head" aria-hidden="true"><span class="gm-head__knob"></span></div>
        </div>
      </div>
    </div>`;
  }

  bindTimeline() {
    const A = this.area, q = s => A.querySelector(`[data-m=${s}]`);
    q('play').addEventListener('click', () => this.toggle());
    q('start').addEventListener('click', () => { this.stop(); this.seek(0, false); });
    q('key').addEventListener('click', () => { if (!this.ed.sel.length) { toast(t('Bitte zuerst ein Objekt auswählen.'), 'info'); return; } this.keyHere(); });
    q('snap').addEventListener('click', () => { this.snap = !this.snap; q('snap').setAttribute('aria-pressed', String(this.snap)); });
    const zoomTo = v => { this.pps = Math.max(30, Math.min(600, v)); q('zoom').value = this.pps; this.renderTimeline(); };
    q('zoom').addEventListener('input', () => zoomTo(+q('zoom').value));
    q('zin').addEventListener('click', () => zoomTo(this.pps * 1.25));
    q('zout').addEventListener('click', () => zoomTo(this.pps / 1.25));
    q('scroll').addEventListener('wheel', e => { if (e.ctrlKey || e.metaKey) { e.preventDefault(); zoomTo(this.pps * Math.exp(-e.deltaY * 0.01)); } }, { passive: false });
    // Lineal: klicken/ziehen = Abspielkopf
    const ruler = q('ruler');
    ruler.addEventListener('pointerdown', e => {
      if (e.button !== 0) return;
      e.preventDefault();
      this.stop();
      ruler.focus({ preventScroll: true });
      ruler.setPointerCapture?.(e.pointerId);
      const at = ev => (ev.clientX - ruler.getBoundingClientRect().left - 8) / this.pps;
      this.seek(at(e), this.snap && !e.altKey);
      const mv = ev => this.seek(at(ev), this.snap && !ev.altKey);
      const up = () => { ruler.removeEventListener('pointermove', mv); ruler.removeEventListener('pointerup', up); ruler.removeEventListener('pointercancel', up); };
      ruler.addEventListener('pointermove', mv); ruler.addEventListener('pointerup', up); ruler.addEventListener('pointercancel', up);
    });
    ruler.addEventListener('keydown', e => {
      const step = e.shiftKey ? 1 : 1 / FPS;
      const map = { ArrowRight: step, ArrowLeft: -step, ArrowUp: step, ArrowDown: -step, PageUp: 1, PageDown: -1 };
      if (e.key in map) { e.preventDefault(); e.stopPropagation(); this.stop(); this.seek(this.time + map[e.key], false); }
    });
    // Zeilen: Auswahl, Auf-/Zuklappen, Rauten
    const rows = q('rows');
    rows.addEventListener('click', e => {
      const fold = e.target.closest('[data-fold]');
      if (fold) { const id = fold.dataset.fold; this.collapsed.has(id) ? this.collapsed.delete(id) : this.collapsed.add(id); this.renderTimeline(); A.querySelector(`[data-fold="${CSS.escape(id)}"]`)?.focus(); return; }
      const nm = e.target.closest('[data-pick]');
      if (nm) { const el = this.byId(nm.dataset.pick); if (el) this.ed.select(e.shiftKey ? [...this.ed.sel, el] : [el]); }
    });
    rows.addEventListener('pointerdown', e => {
      const k = e.target.closest('.gm-key');
      if (k && e.button === 0) { this.keyDown(e, k); return; }
    });
    rows.addEventListener('dblclick', e => {
      const lane = e.target.closest('.gm-lane[data-lane]');
      if (!lane || e.target.closest('.gm-key')) return;
      const [id, p] = lane.dataset.lane.split('|');
      const el = this.byId(id);
      if (!el) return;
      const tt = this.snapTime((e.clientX - lane.getBoundingClientRect().left - 8) / this.pps);
      this.time = Math.max(0, Math.min(this.scene.d, tt));
      this.setKey(el, p, this.time, this.valueNow(el, p));
      this.ed.commit(t('Schlüsselbild bei {s}', { s: sec(this.time) }));
    });
    rows.addEventListener('keydown', e => {
      const k = e.target.closest('.gm-key');
      if (!k) return;
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        e.preventDefault(); e.stopPropagation();
        if (!this.keySel.has(k.dataset.k)) { this.keySel.clear(); this.keySel.add(k.dataset.k); }
        const d = (e.key === 'ArrowRight' ? 1 : -1) * (e.shiftKey ? 1 : 0.1);
        const id = this.moveKeys(d);
        this.ed.commit(t('Schlüsselbild verschoben'));
        this.area.querySelector(`.gm-key[data-k="${CSS.escape(id || k.dataset.k)}"]`)?.focus();
      } else if (e.key === 'Enter') { e.preventDefault(); const [id, p, i] = k.dataset.k.split('|'); const kk = getAnim(this.byId(id))?.k?.[p]?.[+i]; if (kk) this.seek(kk[0], false); }
    });
  }

  /** Raute ziehen: alle gewählten um denselben Abstand (Einrasten an Sekunden/anderen Rauten, ⌥ = frei); Klick = Abspielkopf dorthin */
  keyDown(e, btn) {
    e.preventDefault(); e.stopPropagation();
    this.stop();
    const id = btn.dataset.k;
    if (e.shiftKey) { this.keySel.has(id) ? this.keySel.delete(id) : this.keySel.add(id); this.renderTimeline(); this.renderSide(); this.area.querySelector(`.gm-key[data-k="${CSS.escape(id)}"]`)?.focus(); return; }
    if (!this.keySel.has(id)) { this.keySel.clear(); this.keySel.add(id); }
    const [eid, p, i] = id.split('|');
    const el = this.byId(eid);
    const k0 = getAnim(el)?.k?.[p]?.[+i];
    if (!k0) return;
    this.dragging = true;
    if (!this.ed.sel.includes(el)) this.ed.select([el]);
    const x0 = e.clientX, t0 = k0[0];
    const before = new Map([...this.keySel].map(k => { const [a, b, c] = k.split('|'); return [k, getAnim(this.byId(a))?.k?.[b]?.[+c]?.[0]]; }));
    const snapshot = this.keySnapshot();
    let moved = false, applied = 0;
    this.dragging = true;
    const mv = ev => {
      const dx = ev.clientX - x0;
      if (!moved && Math.abs(dx) < 3) return;
      moved = true;
      let target = t0 + dx / this.pps;
      target = this.snap && !ev.altKey ? this.snapTime(target, new Set(this.keySel)) : Math.round(target * 100) / 100;
      let d = target - t0;
      // alle Rauten innerhalb der Szene halten
      const sc = this.scene;
      for (const v of before.values()) { if (v + d < 0) d = -v; if (v + d > sc.d) d = sc.d - v; }
      this.keyRestore(snapshot);
      this.keySel = new Set(before.keys());
      this.moveKeys(d);
      applied = d;
      this.renderTimeline();
      this.pose();
      this.drawHead();
    };
    const up = () => {
      window.removeEventListener('pointermove', mv); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up);
      this.dragging = false;
      if (moved && Math.abs(applied) > 1e-6) this.ed.commit(this.keySel.size > 1 ? t('Schlüsselbilder verschoben') : t('Schlüsselbild verschoben'));
      else if (!moved) { this.keyRestore(snapshot); this.seek(t0, false); this.renderTimeline(); this.renderSide(); }
      else { this.keyRestore(snapshot); this.refresh(); }
      this.area.querySelector(`.gm-key[data-k="${CSS.escape([...this.keySel][0] || id)}"]`)?.focus({ preventScroll: true });
    };
    window.addEventListener('pointermove', mv); window.addEventListener('pointerup', up); window.addEventListener('pointercancel', up);
  }
  keySnapshot() { return animated(this.root).map(el => [el, el.getAttribute('data-ge-anim')]); }
  keyRestore(s) { for (const [el, v] of s) v === null ? el.removeAttribute('data-ge-anim') : el.setAttribute('data-ge-anim', v); }

  /** Gewählte Rauten um d Sekunden verschieben; gleiche Zeit einer Spur: die bewegte gewinnt. Rückgabe: neue Kennung der ersten */
  moveKeys(d) {
    const sc = this.scene, by = new Map();
    for (const k of this.keySel) { const [id, p, i] = k.split('|'); (by.get(id) || by.set(id, []).get(id)).push([p, +i]); }
    const next = new Set();
    let first = null;
    for (const [id, list] of by) {
      const el = this.byId(id), a = getAnim(el);
      if (!a) continue;
      for (const p of new Set(list.map(x => x[0]))) {
        const idx = new Set(list.filter(x => x[0] === p).map(x => x[1]));
        const keys = a.k[p] || [];
        const moving = keys.filter((_, i) => idx.has(i)).map(k => { const c = [...k]; c[0] = Math.round(Math.max(0, Math.min(sc.d, k[0] + d)) * 1000) / 1000; return c; });
        const stay = keys.filter((_, i) => !idx.has(i)).filter(k => !moving.some(m => Math.abs(m[0] - k[0]) < 1e-3));
        a.k[p] = [...stay, ...moving].sort((x, y) => x[0] - y[0]);
        for (const m of moving) { const key = `${id}|${p}|${a.k[p].indexOf(m)}`; next.add(key); first ||= key; }
      }
      setAnim(el, a);
    }
    this.keySel = next;
    return first;
  }

  /** Zeilen der Zeitleiste: Objekte mit Bewegung und die Auswahl, je Eigenschaft eine Spur */
  rowsList() {
    const list = animated(this.root);
    for (const el of this.ed.sel) if (!list.includes(el) && el !== this.root) list.push(el);
    return list.sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1));
  }

  renderTimeline() {
    if (!this.area) return;
    const sc = this.scene, A = this.area;
    const W = Math.max(sc.d * this.pps + 40, A.querySelector('[data-m=scroll]').clientWidth - 200);
    A.querySelector('[data-m=inner]').style.setProperty('--gm-w', W + 'px');
    const x = tt => 8 + tt * this.pps;
    // Lineal
    const ruler = A.querySelector('[data-m=ruler]');
    const step = this.pps >= 240 ? 0.25 : this.pps >= 100 ? 0.5 : this.pps >= 50 ? 1 : 2;
    let ticks = '';
    for (let s = 0; s <= sc.d + 1e-9; s += step) {
      const full = Math.abs(s - Math.round(s)) < 1e-6;
      ticks += `<span class="gm-tick${full ? ' is-full' : ''}" style="left:${x(s)}px">${full ? esc(de(s, 0)) + ' s' : ''}</span>`;
    }
    ticks += `<span class="gm-end" style="left:${x(sc.d)}px" title="${esc(t('Ende der Szene'))}"></span>`;
    ruler.innerHTML = ticks;
    ruler.setAttribute('aria-valuemax', String(sc.d));
    // Zeilen
    const rows = this.rowsList();
    const sel = new Set(this.ed.sel);
    let html = '';
    if (!rows.length) html = `<p class="gm-empty">${esc(t('Objekt auf der Zeichenfläche wählen, dann eine fertige Bewegung anwenden oder mit K ein Schlüsselbild setzen.'))}</p>`;
    for (const el of rows) {
      const a = getAnim(el);
      const id = el.id || '';
      const name = nameOf(el) || typeLabel(el);
      const props = PROPS.filter(p => a?.k?.[p]?.length);
      const open = props.length && !this.collapsed.has(id);
      const all = new Map();
      for (const p of props) for (const k of a.k[p]) all.set(Math.round(k[0] * 1000), k[0]);
      html += `<div class="gm-row gm-row--el${sel.has(el) ? ' is-sel' : ''}">
        <div class="gm-name">${props.length ? `<button type="button" class="gm-fold" data-fold="${esc(id)}" aria-expanded="${!!open}" aria-label="${esc(open ? t('Spuren einklappen') : t('Spuren ausklappen'))}">${ico(open ? 'caret-down' : 'caret-right')}</button>` : '<span class="gm-fold"></span>'}
          <button type="button" class="gm-pick" ${id ? `data-pick="${esc(id)}"` : 'disabled'} aria-pressed="${sel.has(el)}">${esc(name)}</button></div>
        <div class="gm-lane gm-lane--sum">${[...all.values()].map(v => `<span class="gm-dot" style="left:${x(v)}px"></span>`).join('')}${a?.n ? `<span class="gm-bar gm-bar--count" style="left:${x(a.n.t0)}px;width:${Math.max(4, (a.n.t1 - a.n.t0) * this.pps)}px" title="${esc(t('Zählen'))}">${esc(t('Zählen'))}</span>` : ''}</div></div>`;
      if (!open) continue;
      for (const p of props) {
        const keys = a.k[p];
        let lane = '';
        for (let i = 0; i < keys.length - 1; i++) {
          const e = p === 'pos' ? keys[i][3] : keys[i][2];
          const el2 = keys[i + 1];
          lane += `<span class="gm-seg${e === 'hold' ? ' is-hold' : ''}" style="left:${x(keys[i][0])}px;width:${Math.max(0, (el2[0] - keys[i][0]) * this.pps)}px" title="${esc(this.easeName(e))}"></span>`;
        }
        keys.forEach((k, i) => {
          const kid = `${id}|${p}|${i}`, on = this.keySel.has(kid);
          lane += `<button type="button" class="gm-key${on ? ' is-sel' : ''}${Math.abs(k[0] - this.time) < 1e-3 ? ' is-now' : ''}" data-k="${esc(kid)}" style="left:${x(k[0])}px" aria-pressed="${on}" aria-label="${esc(t('{prop}, Schlüsselbild bei {s}', { prop: label[p](), s: sec(k[0]) }))}" title="${esc(label[p]() + ' · ' + sec(k[0]) + ' · ' + this.keyValue(p, k))}"></button>`;
        });
        html += `<div class="gm-row gm-row--prop"><div class="gm-name gm-name--prop">${esc(label[p]())}</div><div class="gm-lane" data-lane="${esc(id + '|' + p)}">${lane}</div></div>`;
      }
    }
    const fid = document.activeElement?.closest?.('.gm-key')?.dataset.k;
    A.querySelector('[data-m=rows]').innerHTML = html;
    if (fid && A.contains(document.activeElement) === false) A.querySelector(`.gm-key[data-k="${CSS.escape(fid)}"]`)?.focus({ preventScroll: true });
    this.drawHead();
  }
  keyValue(p, k) {
    if (p === 'pos') return `${num(k[1], 1)}, ${num(k[2], 1)}`;
    if (p === 's' || p === 'o' || p === 'w') return Math.round(k[1] * 100) + ' %';
    if (p === 'r') return num(k[1], 1) + '°';
    return String(k[1]);
  }
  easeName(e) { return Array.isArray(e) ? t('eigene Kurve') : (EASE_LABEL[e] || EASE_LABEL.smooth)(); }

  drawHead() {
    if (!this.area) return;
    const head = this.area.querySelector('[data-m=head]');
    const nw = this.area.querySelector('.gm-name')?.offsetWidth || 200;
    if (head) head.style.left = (nw + 8 + this.time * this.pps) + 'px';
    const out = this.area.querySelector('[data-m=time]');
    if (out) out.textContent = `${de(this.time, 2)} / ${de(this.scene.d, 2)} s`;
    const r = this.area.querySelector('[data-m=ruler]');
    if (r) { r.setAttribute('aria-valuenow', String(Math.round(this.time * 100) / 100)); r.setAttribute('aria-valuetext', sec(this.time)); }
    // Rauten am Abspielkopf markieren
    for (const k of this.area.querySelectorAll('.gm-key')) {
      const [id, p, i] = k.dataset.k.split('|');
      const kk = getAnim(this.byId(id))?.k?.[p]?.[+i];
      k.classList.toggle('is-now', !!kk && Math.abs(kk[0] - this.time) < 1e-3);
    }
    // Abspielkopf im sichtbaren Bereich halten
    const sc = this.area.querySelector('[data-m=scroll]');
    if (sc && this.playing) { const xx = 208 + this.time * this.pps; if (xx > sc.scrollLeft + sc.clientWidth - 40) sc.scrollLeft = xx - 240; if (xx < sc.scrollLeft + 200) sc.scrollLeft = Math.max(0, xx - 240); }
  }

  // ================================================================= Bereich rechts
  panel(id) { return this.side?.querySelector(`#${this.ed.uid}-mp-${id}`); }
  currentTab() { return this.side?.querySelector('[role=tab][aria-selected=true]')?.id.split('-mt-')[1] || 'anim'; }

  renderSide() {
    if (!this.side || this.side.hidden) return;
    const tab = this.currentTab();
    const p = this.panel(tab);
    const f = document.activeElement;
    const fkey = p.contains(f) ? f.dataset.f || f.dataset.preset || f.dataset.act : null;
    if (tab === 'anim') p.innerHTML = this.animHtml();
    else if (tab === 'ia') p.innerHTML = this.iaHtml();
    else p.innerHTML = this.sceneHtml();
    if (fkey) p.querySelector(`[data-f="${fkey}"],[data-preset="${fkey}"],[data-act="${fkey}"]`)?.focus({ preventScroll: true });
    if (tab === 'anim') this.drawCurve();
  }

  /** Nur die Werte am Abspielkopf auffrischen (beim Abspielen/Scrubben ohne neu zu bauen) */
  renderValues() {
    if (!this.side || this.side.hidden || this.currentTab() !== 'anim') return;
    const p = this.panel('anim');
    const el = this.ed.sel[0];
    const head = p.querySelector('[data-now]');
    if (head) head.textContent = t('Am Abspielkopf ({s})', { s: sec(this.time) });
    if (!el) return;
    const a = getAnim(el);
    for (const inp of p.querySelectorAll('[data-v]')) {
      if (inp === document.activeElement) continue;
      const [prop, i] = inp.dataset.v.split(':');
      const v = this.valueNow(el, prop, a);
      inp.value = this.fmtVal(prop, v, +i);
    }
    for (const b of p.querySelectorAll('[data-keytoggle]')) {
      const on = this.hasKey(el, b.dataset.keytoggle);
      b.setAttribute('aria-pressed', String(on));
      b.innerHTML = on ? KEY : KEY_OFF;
    }
  }
  fmtVal(prop, v, i = 0) {
    if (prop === 'pos') return num(v[i], 1);
    if (prop === 's' || prop === 'o' || prop === 'w') return num(v * 100, 1);
    if (prop === 'r') return num(v, 1);
    return String(v).slice(0, 7);
  }

  animHtml() {
    const ed = this.ed, els = ed.sel.filter(el => el !== this.root);
    const el = els[0];
    const sc = this.scene;
    const pre = this.preState || { start: 0, len: 1, stagger: 0.15 };
    const isText = els.some(x => x.localName === 'text');
    const canDraw = els.some(x => x.matches(DRAWABLE) || x.querySelector(DRAWABLE));
    const presets = Object.entries(GROUP_LABEL).map(([g, gl]) => `<p class="gm-pgroup">${esc(gl())}</p><div class="gm-presets">${Object.entries(PRESETS).filter(([, p]) => p.group === g).map(([k, p]) => {
      const dis = !el || (p.stroke && !canDraw) || (p.text && !isText) || (p.path && els.length < 2);
      const why = !el ? t('Bitte zuerst ein Objekt auswählen.') : p.stroke && !canDraw ? t('Nur für Linien und Formen') : p.text && !isText ? t('Nur für Texte mit einer Zahl') : p.path && els.length < 2 ? t('Objekt und zuletzt den Weg (Pfad) auswählen') : PRESET_LABEL[k]();
      return `<button type="button" class="gm-preset" data-preset="${k}"${dis ? ' aria-disabled="true"' : ''} title="${esc(why)}">${ico(p.icon)}<span>${esc(PRESET_LABEL[k]())}</span></button>`;
    }).join('')}</div>`).join('');
    let html = `<div class="ge-form">
      <h2 class="ge-h3 gm-h3">${esc(t('Fertige Bewegungen'))}</h2>
      <div class="ge-row4">
        <label>${esc(t('Beginn'))} <input type="number" class="ge-in ge-in--s" data-f="pstart" min="0" step="0.1" value="${num(pre.start, 2)}" aria-label="${esc(t('Beginn in Sekunden'))}"></label>
        <label>${esc(t('Dauer'))} <input type="number" class="ge-in ge-in--s" data-f="plen" min="0.05" step="0.1" value="${num(pre.len, 2)}" aria-label="${esc(t('Dauer in Sekunden'))}"></label>
        ${els.length > 1 ? `<label title="${esc(t('Jedes weitere Objekt beginnt so viel später'))}">${esc(t('Versetzt'))} <input type="number" class="ge-in ge-in--s" data-f="pstagger" min="0" step="0.05" value="${num(pre.stagger, 2)}" aria-label="${esc(t('Versatz je Objekt in Sekunden'))}"></label>` : ''}
        <label class="ge-chk" title="${esc(t('Beim Pfad folgen in Laufrichtung drehen'))}"><input type="checkbox" data-f="orient"${pre.orient ? ' checked' : ''}> ${esc(t('Richtung folgen'))}</label>
      </div>
      ${presets}
    </div>`;
    if (!el) return html + `<p class="ge-note">${esc(t('Objekt auswählen – die Werte am Abspielkopf erscheinen hier.'))}</p>`;
    const a = getAnim(el);
    const row = (p, inner) => `<div class="gm-val"><button type="button" class="ge-ib gm-kt" data-keytoggle="${p}" aria-pressed="${this.hasKey(el, p)}" aria-label="${esc(t('Schlüsselbild {prop} am Abspielkopf', { prop: label[p]() }))}" title="${esc(t('Schlüsselbild {prop} am Abspielkopf', { prop: label[p]() }))}">${this.hasKey(el, p) ? KEY : KEY_OFF}</button><span class="gm-val__l">${esc(label[p]())}</span><span class="gm-val__in">${inner}</span></div>`;
    const v = p => this.valueNow(el, p, a);
    const nin = (p, i, unit, step = 1, extra = '') => `<input type="number" class="ge-in ge-in--s" data-v="${p}:${i}" data-f="v-${p}-${i}" step="${step}" value="${this.fmtVal(p, v(p), i)}" aria-label="${esc(label[p]() + (unit ? ' (' + unit + ')' : ''))}"${extra}>${unit ? `<span class="gm-unit" aria-hidden="true">${esc(unit)}</span>` : ''}`;
    const colorIn = p => `<input type="color" class="gm-color" data-v="${p}:0" data-f="v-${p}" value="${esc(String(v(p)).slice(0, 7))}" aria-label="${esc(label[p]())}">`;
    html += `<div class="ge-form"><h2 class="ge-h3 gm-h3" data-now>${esc(t('Am Abspielkopf ({s})', { s: sec(this.time) }))}</h2>
      ${row('pos', `<span class="gm-xy">X ${nin('pos', 0, '', 1)} Y ${nin('pos', 1, '', 1)}</span>`)}
      ${row('s', nin('s', 0, '%', 1, ' min="0"'))}
      ${row('r', nin('r', 0, '°', 1))}
      ${row('o', nin('o', 0, '%', 1, ' min="0" max="100"'))}
      ${canDraw ? row('w', nin('w', 0, '%', 1, ' min="0" max="100"')) : ''}
      ${row('f', colorIn('f'))}
      ${row('c', colorIn('c'))}
      <p class="ge-note">${esc(this.ed.sv?.mode === 'dark' ? t('Dunkel-Ansicht: Farben gelten für die Dunkel-Fassung.') : t('Werte relativ zur Grundform; Raute = Schlüsselbild an dieser Stelle.'))}</p>
    </div>`;
    // Verlauf: gewählte Rauten, sonst die Schlüsselbilder am Abspielkopf
    const targets = this.easeTargets();
    const cur = targets.length ? targets[0].e : null;
    const isCustom = Array.isArray(cur);
    html += `<div class="ge-form"><h2 class="ge-h3 gm-h3">${esc(t('Verlauf'))}</h2>
      ${targets.length ? `<p class="ge-note">${esc(targets.length === 1 ? t('Ab dem gewählten Schlüsselbild bis zum nächsten') : t('Für {n} Schlüsselbilder', { n: targets.length }))}</p>
      <div class="gm-eases" role="radiogroup" aria-label="${esc(t('Verlauf'))}">${Object.keys(EASES).map(k => `<button type="button" role="radio" class="gm-ease" data-ease="${k}" aria-checked="${cur === k}" data-f="ease-${k}">${this.easeIcon(EASES[k])}<span>${esc(EASE_LABEL[k]())}</span></button>`).join('')}
        <button type="button" role="radio" class="gm-ease" data-ease="custom" aria-checked="${isCustom}" data-f="ease-custom">${this.easeIcon(isCustom ? cur : [0.25, 0.1, 0.25, 1])}<span>${esc(t('eigene Kurve'))}</span></button></div>
      <div class="gm-curve" data-curve${isCustom || cur === 'custom' ? '' : ' hidden'}>
        <svg class="gm-curve__svg" viewBox="-10 -60 120 220" data-curvesvg aria-hidden="true"></svg>
        <div class="ge-row4">${['x1', 'y1', 'x2', 'y2'].map((n, i) => `<label>${n.toUpperCase()} <input type="number" class="ge-in ge-in--s" data-bz="${i}" data-f="bz-${i}" step="0.01" ${i % 2 ? 'min="-2" max="3"' : 'min="0" max="1"'} value="${num((isCustom ? cur : [0.25, 0.1, 0.25, 1])[i], 3)}"></label>`).join('')}</div>
      </div>` : `<p class="ge-note">${esc(t('Raute in der Zeitleiste wählen (⇧ = mehrere) oder den Abspielkopf auf ein Schlüsselbild stellen.'))}</p>`}
    </div>`;
    // Start und Zählen
    const hots = [...this.root.querySelectorAll('[data-ge-hot="1"]')].filter(x => x !== el);
    const startVal = a?.on ? 'on:' + a.on : a?.tr === 'hover' ? 'hover' : '';
    html += `<div class="ge-form"><h2 class="ge-h3 gm-h3">${esc(t('Start'))}</h2>
      <label class="ge-lbl">${esc(t('Bewegung startet'))} <select class="ge-in" data-f="start">
        <option value=""${startVal === '' ? ' selected' : ''}>${esc(t('mit der Grafik (Szene)'))}</option>
        <option value="hover"${startVal === 'hover' ? ' selected' : ''}>${esc(t('beim Zeigen auf dieses Objekt'))}</option>
        ${hots.map(x => `<option value="on:${esc(x.id)}"${startVal === 'on:' + x.id ? ' selected' : ''}>${esc(t('bei Klick auf „{name}“', { name: nameOf(x) || typeLabel(x) }))}</option>`).join('')}
      </select></label>
      ${a?.n ? `<div class="ge-row4"><label>${esc(t('Zählen von'))} <input type="number" class="ge-in ge-in--s" data-f="nfrom" value="${num(a.n.a, 4)}"></label><label>${esc(t('bis'))} <input type="number" class="ge-in ge-in--s" value="${num(a.n.b, 4)}" disabled title="${esc(t('Der Wert im Text'))}"></label>
        <label>${esc(t('Beginn'))} <input type="number" class="ge-in ge-in--s" data-f="nt0" min="0" step="0.1" value="${num(a.n.t0, 2)}"></label><label>${esc(t('Ende'))} <input type="number" class="ge-in ge-in--s" data-f="nt1" min="0" step="0.1" value="${num(a.n.t1, 2)}"></label></div>
        <button type="button" class="adm-btn adm-btn--small" data-act="nocount">${esc(t('Zählen entfernen'))}</button>` : ''}
      ${a ? `<button type="button" class="adm-btn adm-btn--small gm-danger" data-act="clear">${ico('trash')} ${esc(els.length > 1 ? t('Bewegung der Auswahl entfernen') : t('Bewegung entfernen'))}</button>` : ''}
    </div>`;
    void sc;
    return html;
  }

  easeIcon(p) {
    if (!p) return '<svg class="gm-eicon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21H15V3H21"/></svg>';
    const pts = [];
    for (let i = 0; i <= 16; i++) { const x = i / 16; pts.push(`${num(3 + x * 18, 2)} ${num(21 - bez(p, x) * 18, 2)}`); }
    return `<svg class="gm-eicon" viewBox="0 0 24 24" aria-hidden="true"><path d="M${pts.join('L')}"/></svg>`;
  }

  /** Schlüsselbilder für „Verlauf“: gewählte Rauten, sonst die am Abspielkopf der Auswahl (nicht das letzte einer Spur) */
  easeTargets() {
    const out = [];
    const push = (el, p, i) => { const k = getAnim(el)?.k?.[p]; if (k?.[i] && i < k.length - 1) out.push({ el, p, i, e: p === 'pos' ? k[i][3] : k[i][2] }); };
    if (this.keySel.size) for (const s of this.keySel) { const [id, p, i] = s.split('|'); push(this.byId(id), p, +i); }
    else for (const el of this.ed.sel) for (const p of PROPS) { const k = getAnim(el)?.k?.[p]; const i = k?.findIndex(x => Math.abs(x[0] - this.time) < 1e-3) ?? -1; if (i > -1) push(el, p, i); }
    return out.filter(x => x.el);
  }
  setEase(e, label = t('Verlauf')) {
    const targets = this.easeTargets();
    if (!targets.length) return;
    const by = new Map();
    for (const x of targets) { const a = by.get(x.el) || getAnim(x.el); by.set(x.el, a); a.k[x.p][x.i][x.p === 'pos' ? 3 : 2] = e; }
    for (const [el, a] of by) setAnim(el, a);
    this.ed.commit(label);
  }
  drawCurve() {
    const svg = this.side?.querySelector('[data-curvesvg]');
    if (!svg || svg.closest('[hidden]')) return;
    const tg = this.easeTargets()[0];
    const p = Array.isArray(tg?.e) ? tg.e : [0.25, 0.1, 0.25, 1];
    const X = x => num(x * 100, 2), Y = y => num(100 - y * 100, 2);
    let d = '';
    for (let i = 0; i <= 40; i++) { const x = i / 40; d += (i ? 'L' : 'M') + X(x) + ' ' + Y(bez(p, x)); }
    svg.innerHTML = `<rect x="0" y="0" width="100" height="100" class="gm-curve__box"/>
      <line x1="0" y1="100" x2="${X(p[0])}" y2="${Y(p[1])}" class="gm-curve__arm"/><line x1="100" y1="0" x2="${X(p[2])}" y2="${Y(p[3])}" class="gm-curve__arm"/>
      <path d="${d}" class="gm-curve__path"/>
      <circle cx="${X(p[0])}" cy="${Y(p[1])}" r="5" class="gm-curve__h" data-bh="0"/><circle cx="${X(p[2])}" cy="${Y(p[3])}" r="5" class="gm-curve__h" data-bh="1"/>`;
  }

  iaHtml() {
    const els = this.ed.sel.filter(el => el !== this.root), el = els[0];
    const order = interactive(this.root).filter(x => x.hasAttribute('data-ge-link') || x.getAttribute('data-ge-hot') === '1');
    const list = order.length ? `<ol class="gm-order">${order.map(x => `<li><button type="button" class="gm-pick" data-pick="${esc(x.id)}">${esc(nameOf(x) || typeLabel(x))}</button> <span class="ge-ctx__info">${esc(x.hasAttribute('data-ge-link') ? t('Link') : t('Schalter'))}</span></li>`).join('')}</ol>
      <p class="ge-note">${esc(t('Tab-Reihenfolge auf der Website = Reihenfolge der Ebenen. „Prüfen“ meldet, wenn sie von der Lesereihenfolge abweicht.'))}</p>` : `<p class="ge-note">${esc(t('Noch keine Links oder Schalter.'))}</p>`;
    if (!el) return `<div class="ge-form"><p class="ge-note">${esc(t('Objekt auswählen, um es zu verlinken, einen Tooltip zu geben oder als Schalter zu nutzen.'))}</p></div><div class="ge-form"><h2 class="ge-h3 gm-h3">${esc(t('Fokus-Reihenfolge'))}</h2>${list}</div>`;
    const link = el.getAttribute('data-ge-link') || '';
    const hot = el.getAttribute('data-ge-hot') === '1';
    return `<div class="ge-form">
      <h2 class="ge-h3 gm-h3">${esc(t('Link-Bereich'))}</h2>
      <div class="gm-link">${link ? `<span class="gm-link__v" data-linkv>${esc(link)}</span>` : `<span class="ge-note">${esc(t('kein Link'))}</span>`}</div>
      <div class="ge-row3"><button type="button" class="adm-btn adm-btn--small" data-act="link" data-f="link">${ico('link')} ${esc(link ? t('Ziel ändern …') : t('Ziel wählen …'))}</button>
        ${link ? `<button type="button" class="adm-btn adm-btn--small" data-act="unlink" data-f="unlink">${esc(t('Link entfernen'))}</button>` : ''}</div>
      <label class="ge-lbl">${esc(t('Name für Screenreader'))} <input type="text" class="ge-in" data-f="linklabel" maxlength="200" value="${esc(el.getAttribute('data-ge-linklabel') || '')}" placeholder="${esc(nameOf(el) || '')}"></label>
      <label class="ge-lbl">${esc(t('Tooltip'))} <input type="text" class="ge-in" data-f="tip" maxlength="300" value="${esc(el.getAttribute('data-ge-tip') || '')}"></label>
      <p class="ge-note">${esc(t('Der Tooltip erscheint beim Zeigen und bei Tastatur-Fokus; Screenreader lesen ihn als Titel.'))}</p>
      <label class="ge-lbl ge-chk"><input type="checkbox" data-f="hot"${hot ? ' checked' : ''}${link ? ' disabled' : ''}> ${esc(t('Schalter (Hotspot): Klick blendet zugeordnete Bewegungen ein und wieder aus'))}</label>
    </div><div class="ge-form"><h2 class="ge-h3 gm-h3">${esc(t('Fokus-Reihenfolge'))}</h2>${list}</div>`;
  }

  sceneHtml() {
    const sc = this.scene;
    const opt = (v, l, cur) => `<option value="${v}"${String(cur) === String(v) ? ' selected' : ''}>${esc(l)}</option>`;
    return `<div class="ge-form">
      <div class="ge-row4">
        <label>${esc(t('Dauer'))} <input type="number" class="ge-in ge-in--s" data-f="d" min="0.1" max="120" step="0.1" value="${num(sc.d, 2)}" aria-label="${esc(t('Dauer der Szene in Sekunden'))}"></label>
        <label>${esc(t('Verzögerung'))} <input type="number" class="ge-in ge-in--s" data-f="dl" min="0" max="60" step="0.1" value="${num(sc.dl, 2)}" aria-label="${esc(t('Verzögerung in Sekunden'))}"></label>
      </div>
      <label class="ge-lbl">${esc(t('Wiederholen'))} <select class="ge-in" data-f="it">${[1, 2, 3, 5].map(n => opt(n, n === 1 ? t('einmal') : t('{n}-mal', { n }), sc.it)).join('')}${opt(0, t('endlos'), sc.it)}</select></label>
      <label class="ge-lbl">${esc(t('Richtung'))} <select class="ge-in" data-f="dir">${opt('normal', t('vorwärts'), sc.dir)}${opt('reverse', t('rückwärts'), sc.dir)}${opt('alternate', t('hin und zurück'), sc.dir)}${opt('alternate-reverse', t('zurück und hin'), sc.dir)}</select></label>
      <label class="ge-lbl">${esc(t('Auslöser'))} <select class="ge-in" data-f="tr">${opt('view', t('sobald sichtbar'), sc.tr)}${opt('load', t('beim Laden'), sc.tr)}${opt('hover', t('beim Zeigen auf die Grafik'), sc.tr)}${opt('click', t('bei Klick auf die Grafik'), sc.tr)}</select></label>
      <p class="ge-note">${esc(t('Auf der Website: ohne JavaScript und bei „Bewegung reduzieren“ steht das Endbild, Schleifen halten außerhalb des Bildes an.'))}</p>
      <label class="ge-lbl ge-chk"><input type="checkbox" data-f="reduced"${this.reduced ? ' checked' : ''}> ${esc(t('Vorschau „Bewegung reduzieren“ (Endbild)'))}</label>
    </div>`;
  }

  bindSide() {
    const S = this.side;
    S.addEventListener('click', e => {
      const b = e.target.closest('button');
      if (!b) return;
      if (b.dataset.preset) {
        if (b.getAttribute('aria-disabled') === 'true') { toast(b.title, 'info'); return; }
        const st = this.preRead();
        this.applyPreset(b.dataset.preset, st.start, st.len, st.stagger);
        return;
      }
      if (b.dataset.keytoggle) {
        const p = b.dataset.keytoggle, els = this.ed.sel.filter(x => x !== this.root);
        if (!els.length) return;
        const on = this.hasKey(els[0], p);
        for (const el of els) on ? this.removeKey(el, p, this.time) : this.setKey(el, p, this.time, this.valueNow(el, p));
        if (!on && !this.root.hasAttribute('data-ge-motion')) setScene(this.root, this.scene);
        this.ed.commit(on ? t('Schlüsselbild gelöscht') : t('Schlüsselbild bei {s}', { s: sec(this.time) }));
        return;
      }
      if (b.dataset.ease) {
        if (b.dataset.ease === 'custom') {
          const tg = this.easeTargets()[0];
          this.setEase(Array.isArray(tg?.e) ? tg.e : [0.25, 0.1, 0.25, 1], t('Verlauf: eigene Kurve'));
        } else this.setEase(b.dataset.ease, t('Verlauf: {name}', { name: EASE_LABEL[b.dataset.ease]() }));
        return;
      }
      if (b.dataset.pick) { const el = this.byId(b.dataset.pick); if (el) this.ed.select([el]); return; }
      const act = b.dataset.act;
      if (act === 'clear') { for (const el of this.ed.sel) el.removeAttribute('data-ge-anim'); this.keySel.clear(); this.ed.commit(t('Bewegung entfernt')); }
      if (act === 'nocount') { const el = this.ed.sel[0], a = getAnim(el); if (a) { delete a.n; setAnim(el, a); this.ed.commit(t('Zählen entfernt')); } }
      if (act === 'link') this.pickLink();
      if (act === 'unlink') { const el = this.ed.sel[0]; el.removeAttribute('data-ge-link'); this.ed.commit(t('Link entfernt')); }
    });
    // Radiogruppe Verlauf: Pfeiltasten
    S.addEventListener('keydown', e => {
      const r = e.target.closest('.gm-ease');
      if (!r) return;
      const list = [...S.querySelectorAll('.gm-ease')], i = list.indexOf(r);
      const d = ['ArrowRight', 'ArrowDown'].includes(e.key) ? 1 : ['ArrowLeft', 'ArrowUp'].includes(e.key) ? -1 : 0;
      if (!d) return;
      e.preventDefault(); e.stopPropagation();
      list[(i + d + list.length) % list.length].click();
    });
    // Werte: live beim Tippen/Ziehen, ein Schritt beim Übernehmen (change)
    let liveSnap = null;
    const onVal = (inp, commit) => {
      const els = this.ed.sel.filter(x => x !== this.root);
      if (!els.length) return;
      const [prop, i] = inp.dataset.v.split(':');
      let raw = inp.type === 'color' ? inp.value : parseFloat(inp.value);
      if (inp.type !== 'color' && !Number.isFinite(raw)) return;
      if (!liveSnap) liveSnap = this.keySnapshot().concat(els.filter(el => !el.hasAttribute('data-ge-anim')).map(el => [el, null]));
      else this.keyRestore(liveSnap);
      const conv = prop === 's' || prop === 'o' || prop === 'w' ? raw / 100 : raw;
      this.changeValue(els, prop, cur => (prop === 'pos' ? (+i ? [cur[0], conv] : [conv, cur[1]]) : prop === 'o' || prop === 'w' ? Math.max(0, Math.min(1, conv)) : prop === 's' ? Math.max(0, conv) : conv));
      if (commit) {
        liveSnap = null;
        if (!this.root.hasAttribute('data-ge-motion')) setScene(this.root, this.scene);
        this.ed.commit(t('{prop} (Schlüsselbild)', { prop: label[prop]() }));
      } else this.pose();
    };
    S.addEventListener('input', e => {
      const inp = e.target;
      if (inp.dataset.v) { onVal(inp, false); return; }
      if (inp.dataset.bz !== undefined) {
        const vals = [...S.querySelectorAll('[data-bz]')].map(x => parseFloat(x.value));
        if (vals.every(Number.isFinite)) this.previewEase(vals);
      }
    });
    S.addEventListener('change', e => {
      const inp = e.target, f = inp.dataset.f;
      if (inp.dataset.v) { onVal(inp, true); return; }
      if (inp.dataset.bz !== undefined) {
        const vals = [...S.querySelectorAll('[data-bz]')].map(x => parseFloat(x.value));
        if (vals.every(Number.isFinite)) this.setEase([Math.max(0, Math.min(1, vals[0])), Math.max(-2, Math.min(3, vals[1])), Math.max(0, Math.min(1, vals[2])), Math.max(-2, Math.min(3, vals[3]))].map(v => +num(v, 3)), t('Verlauf: eigene Kurve'));
        return;
      }
      if (['pstart', 'plen', 'pstagger', 'orient'].includes(f)) { this.preState = this.preRead(); return; }
      if (['d', 'dl', 'it', 'dir', 'tr'].includes(f)) {
        const sc = this.scene;
        const v = ['dir', 'tr'].includes(f) ? inp.value : Math.max(f === 'd' ? 0.1 : 0, parseFloat(inp.value) || 0);
        if (f === 'd' && v < sc.d) {
          // kürzer: Schlüsselbilder dahinter an das Ende rücken (sonst lägen sie außerhalb der Szene)
          for (const el of animated(this.root)) { const a = getAnim(el); let ch = false; for (const p of PROPS) for (const k of a?.k?.[p] || []) if (k[0] > v) { k[0] = v; ch = true; } if (ch) setAnim(el, a); }
        }
        setScene(this.root, { ...sc, [f]: v });
        this.ed.commit(t('Szene: {name}', { name: { d: t('Dauer'), dl: t('Verzögerung'), it: t('Wiederholen'), dir: t('Richtung'), tr: t('Auslöser') }[f] }));
        return;
      }
      if (f === 'reduced') { this.reduced = inp.checked; this.pose(); return; }
      const el = this.ed.sel[0];
      if (!el) return;
      if (f === 'start') {
        this.ensureId(el);
        const a = getAnim(el) || { p: pivotOf(el), k: {} };
        delete a.tr; delete a.on;
        if (inp.value === 'hover') a.tr = 'hover';
        else if (inp.value.startsWith('on:')) a.on = inp.value.slice(3);
        if (!Object.keys(a.k).length && !a.n) { toast(t('Erst eine Bewegung anlegen – dann gilt der Start.'), 'info'); this.renderSide(); return; }
        setAnim(el, a);
        this.ed.commit(t('Start der Bewegung'));
      }
      if (['nfrom', 'nt0', 'nt1'].includes(f)) {
        const a = getAnim(el);
        if (!a?.n) return;
        const v = parseFloat(inp.value);
        if (!Number.isFinite(v)) return;
        if (f === 'nfrom') a.n.a = v; if (f === 'nt0') a.n.t0 = Math.max(0, Math.min(v, a.n.t1)); if (f === 'nt1') a.n.t1 = Math.max(a.n.t0, Math.min(v, this.scene.d));
        setAnim(el, a);
        this.ed.commit(t('Zählen'));
      }
      if (f === 'linklabel' || f === 'tip') {
        this.ensureId(el);
        const attr = f === 'tip' ? 'data-ge-tip' : 'data-ge-linklabel';
        const v = inp.value.trim();
        v ? el.setAttribute(attr, v) : el.removeAttribute(attr);
        if (f === 'tip' && !v) el.removeAttribute('data-ge-tipbox');
        this.ed.commit(f === 'tip' ? t('Tooltip') : t('Name für Screenreader'));
      }
      if (f === 'hot') {
        this.ensureId(el);
        inp.checked ? el.setAttribute('data-ge-hot', '1') : el.removeAttribute('data-ge-hot');
        if (!inp.checked) for (const x of animated(this.root)) { const a = getAnim(x); if (a?.on === el.id) { delete a.on; setAnim(x, a); } }
        this.ed.commit(inp.checked ? t('Schalter (Hotspot)') : t('Schalter entfernt'));
      }
    });
    // Kurven-Editor: Griffe ziehen (Vorschau live, ein Schritt beim Loslassen); Pfeiltasten in den Feldern
    S.addEventListener('pointerdown', e => {
      const hdl = e.target.closest('[data-bh]');
      if (!hdl) return;
      e.preventDefault();
      const svg = hdl.ownerSVGElement, i = +hdl.dataset.bh;
      const tg = this.easeTargets()[0];
      const p = [...(Array.isArray(tg?.e) ? tg.e : [0.25, 0.1, 0.25, 1])];
      svg.setPointerCapture?.(e.pointerId);
      const at = ev => { const m = svg.getScreenCTM().inverse(); const q = new DOMPoint(ev.clientX, ev.clientY).matrixTransform(m); return [Math.max(0, Math.min(1, q.x / 100)), Math.max(-2, Math.min(3, (100 - q.y) / 100))]; };
      const mv = ev => { const [x, y] = at(ev); p[i * 2] = +num(x, 3); p[i * 2 + 1] = +num(y, 3); this.previewEase(p); };
      const up = () => { svg.removeEventListener('pointermove', mv); svg.removeEventListener('pointerup', up); this.setEase(p, t('Verlauf: eigene Kurve')); };
      svg.addEventListener('pointermove', mv); svg.addEventListener('pointerup', up);
    });
  }
  /** Kurve nur zeigen (ohne Protokoll) */
  previewEase(p) {
    const svg = this.side?.querySelector('[data-curvesvg]');
    if (!svg) return;
    const X = x => num(x * 100, 2), Y = y => num(100 - y * 100, 2);
    let d = '';
    for (let i = 0; i <= 40; i++) { const x = i / 40; d += (i ? 'L' : 'M') + X(x) + ' ' + Y(bez(p, x)); }
    svg.querySelector('.gm-curve__path')?.setAttribute('d', d);
    const hs = svg.querySelectorAll('.gm-curve__h'), arms = svg.querySelectorAll('.gm-curve__arm');
    if (hs.length === 2) { hs[0].setAttribute('cx', X(p[0])); hs[0].setAttribute('cy', Y(p[1])); hs[1].setAttribute('cx', X(p[2])); hs[1].setAttribute('cy', Y(p[3])); arms[0].setAttribute('x2', X(p[0])); arms[0].setAttribute('y2', Y(p[1])); arms[1].setAttribute('x2', X(p[2])); arms[1].setAttribute('y2', Y(p[3])); }
    this.side.querySelectorAll('[data-bz]').forEach((x, i) => { if (x !== document.activeElement) x.value = num(p[i], 3); });
  }
  preRead() {
    const q = f => this.side?.querySelector(`[data-f=${f}]`);
    const r = (f, d) => { const v = parseFloat(q(f)?.value); return Number.isFinite(v) ? v : d; };
    return { start: Math.max(0, r('pstart', 0)), len: Math.max(0.05, r('plen', 1)), stagger: Math.max(0, r('pstagger', this.preState?.stagger ?? 0.15)), orient: !!q('orient')?.checked };
  }

  async pickLink() {
    const el = this.ed.sel[0];
    if (!el) return;
    const open = window.CMSAdmin?.openLinkPicker;
    let r = null;
    if (open) r = await open({ mode: 'field', value: el.getAttribute('data-ge-link') || '' });
    else { const v = prompt(t('Adresse')); r = v ? { value: v } : null; }
    if (!r || r.remove) { if (r?.remove) { el.removeAttribute('data-ge-link'); this.ed.commit(t('Link entfernt')); } return; }
    const v = String(r.value || '').trim();
    if (!v) return;
    this.ensureId(el);
    el.setAttribute('data-ge-link', v);
    el.removeAttribute('data-ge-hot');
    if (!el.getAttribute('data-ge-linklabel') && r.label && !nameOf(el)) el.setAttribute('data-ge-linklabel', String(r.label).slice(0, 200));
    this.ed.commit(t('Link-Bereich'));
    this.side?.querySelector('[data-f=link]')?.focus();
  }

  // ================================================================= Prüfen, Export
  /** Hinweise für „Prüfen“ (check.js) */
  checks(add) {
    const root = this.root, sc = getScene(root);
    if (!hasMotion(root)) return;
    // WCAG 2.2.2: länger als 5 s oder endlos → auf der Website erscheint immer ein Knopf „Animation anhalten“ (Core\Graphics\Motion,
    // nicht abschaltbar) – nur noch ein Hinweis, keine Warnung
    if (animated(root).length && sc.tr !== 'hover' && (sc.it === 0 || sc.dl + sc.d * sc.it > 5)) add({ key: 'loop', sev: 'info', mode: null, title: sc.it === 0 ? t('Endlose Bewegung') : t('Bewegung länger als 5 Sekunden'), detail: t('Auf der Website erscheint in der Ecke der Grafik ein Knopf „Animation anhalten“ (WCAG 2.2.2), außerhalb des Bildes hält die Bewegung an, bei „Bewegung reduzieren“ steht das Endbild.') });
    for (const el of root.querySelectorAll('[data-ge-link]')) {
      const name = el.getAttribute('data-ge-linklabel') || el.getAttribute('data-ge-tip') || nameOf(el);
      if (!name) add({ key: 'linkname', sev: 'error', mode: null, el, title: t('Link ohne Namen'), detail: t('Ein Link-Bereich braucht einen Namen für Screenreader (Bereich Interaktion).') });
    }
    // Fokus-Reihenfolge = Lesereihenfolge (oben → unten, links → rechts)
    const foc = [...root.querySelectorAll('[data-ge-link],[data-ge-hot="1"]')];
    if (foc.length > 1) {
      const tol = Math.max(4, this.vb.h / 60);
      const vis = foc.map(el => ({ el, b: docBounds(el, root) })).filter(x => x.b).sort((a, b) => Math.abs(a.b.y - b.b.y) > tol ? a.b.y - b.b.y : a.b.x - b.b.x).map(x => x.el);
      if (vis.some((el, i) => foc[i] !== el)) {
        const sameParent = foc.every(el => el.parentNode === foc[0].parentNode);
        add({ key: 'focusorder', sev: 'warn', mode: null, el: vis[0], title: t('Fokus-Reihenfolge weicht von der Lesereihenfolge ab'), detail: t('Mit der Tabulatortaste springt der Fokus in der Reihenfolge der Ebenen – sichtbar ist eine andere Reihenfolge.'),
          ...(sameParent ? { fix: { label: t('Ebenen in Lesereihenfolge bringen'), run: () => { const marks = foc.map(el => { const m = document.createComment(''); el.before(m); return m; }); vis.forEach((el, i) => marks[i].replaceWith(el)); this.ed.commit(t('Fokus-Reihenfolge')); } } } : {}) });
      }
    }
  }

  /** Standbild (Anfang/Ende) für Desktop/PDF: SVG-Text mit festgeschriebenem Zustand */
  stillSvg(svg, which = 'end', dark = false) {
    const sc = getScene(this.root);
    const host = document.createElement('div');
    host.style.cssText = 'position:absolute;left:-20000px;top:0;width:800px;height:500px;visibility:hidden';
    const c = document.importNode(new DOMParser().parseFromString(svg, 'image/svg+xml').documentElement, true);
    host.append(c);
    this.ed.shadow.append(host);
    try {
      bakeAt(c, which === 'start' ? 0 : sc.d, dark);
      for (const el of c.querySelectorAll('[data-ge-anim]')) {
        const a = getAnim(el);
        if (a?.n && which === 'start') { const node = [...el.querySelectorAll('*'), el].flatMap(x => [...x.childNodes]).find(n => n.nodeType === 3 && /\d/.test(n.data)); const n = node && parseNumber(node.data); if (n) node.data = node.data.slice(0, n.index) + formatNumber(a.n.a, a.n) + node.data.slice(n.index + n.length); }
      }
      return new XMLSerializer().serializeToString(c);
    } finally { host.remove(); }
  }
}

export { isCountText, hasMotion, SHIFT, isMac, stateAt };
