/*
 * Grafik-Editor – Text direkt auf der Fläche bearbeiten: Schreibmarke und Auswahl werden aus der Geometrie des SVG-Textes
 * gezeichnet (getStartPositionOfChar/getExtentOfChar), getippt wird in ein unsichtbares Textfeld (Tastatur, Eingabehilfen,
 * Zwischenablage und Bildschirmleser wie gewohnt). ⌘B/⌘I/⌘U formatieren die Auswahl (bzw. das nächste Getippte),
 * Farben aus dem Bereich „Farbe“ wirken während der Bearbeitung auf die Auswahl. MIT
 */
import { pt, docMatrix } from './geom.js';
import { getProp } from './doc.js';
import { readText, plainOf, applyInput, styleRange, rangeHas, layoutText, lineAt, plainIndex, transformCase, toChars } from './text.js';

export class TextEditor {
  /** at: { clientX, clientY } = Schreibmarke dort; sonst alles auswählen */
  constructor(ed, el, { at = null, fresh = false } = {}) {
    this.ed = ed; this.el = el; this.fresh = fresh;
    this.snap = { attrs: [...el.attributes].map(a => [a.name, a.value, a.namespaceURI]), kids: [...el.childNodes].map(n => n.cloneNode(true)) };
    this.model = readText(el);
    this.before = JSON.stringify(this.model.paras) + (el.getAttribute('data-ge-frame') || '');
    this.pending = null;
    this.dirty = false;
    this.relayout();
    const ta = this.ta = ed.$('.ge-textedit');
    ta.hidden = false;
    ta.value = plainOf(this.model);
    ta.oninput = () => this.input();
    // eigenes Rückgängig während der Bearbeitung: das Textfeld kennt nur Getipptes, nicht ⌘B/Farbe/Schreibweise
    this.ustack = []; this.rstack = [];
    ta.onbeforeinput = e => this.push(/^insertText|^insertCompositionText/.test(e.inputType) ? 'type' : e.inputType);
    ta.onkeydown = e => this.key(e);
    ta.onkeyup = ta.onselect = () => this.ed.draw();
    this.onSelChange = () => { if (document.activeElement === ta) this.ed.draw(); };
    document.addEventListener('selectionchange', this.onSelChange);
    ta.focus({ preventScroll: true });
    if (at) { const i = this.indexAt(at.clientX, at.clientY); ta.setSelectionRange(i, i); }
    else ta.select();
    this.place();
  }

  get range() { return [this.ta.selectionStart, this.ta.selectionEnd]; }

  /** Modell neu setzen und Abbildung erneuern */
  relayout() { this.map = layoutText(this.el, this.model); }

  input() {
    this.model = applyInput(this.model, this.ta.value, this.pending);
    this.pending = null;
    this.dirty = true;
    this.relayout();
    this.place();
    this.ed.draw();
    this.ed.emit('textinput', this.el);
  }

  key(e) {
    e.stopPropagation();
    const mod = e.metaKey || e.ctrlKey, k = e.key.toLowerCase();
    if (e.key === 'Escape') { e.preventDefault(); this.ed.endText(); this.ed.stage.focus(); return; }
    if (e.key === 'Enter' && this.model.path) { e.preventDefault(); return; }   // Text auf Pfad: eine Zeile
    if (mod && (k === 'z' || k === 'y')) { e.preventDefault(); this.undo(k === 'y' || e.shiftKey); return; }
    if (mod && ['b', 'i', 'u'].includes(k)) {
      e.preventDefault();
      if (k === 'b') this.toggle('font-weight', v => +v >= 600 || v === 'bold', '700', '400');
      if (k === 'i') this.toggle('font-style', v => v === 'italic', 'italic', 'normal');
      if (k === 'u') this.toggle('text-decoration', v => /underline/.test(v || ''), 'underline', 'none');
      return;
    }
    if (mod && k === 's') { e.preventDefault(); this.ed.endText(); this.ed.save(e.shiftKey); return; }
    if ((e.key === 'ArrowUp' || e.key === 'ArrowDown') && !mod && !e.altKey && this.lineMove(e.key === 'ArrowUp' ? -1 : 1, e.shiftKey)) { e.preventDefault(); return; }
    if (e.key !== 'Shift') this.goalX = null;
    requestAnimationFrame(() => this.ed.draw());
  }

  /**
   * Pfeil hoch/runter: Schreibmarke in die Zeile darüber/darunter an dieselbe waagerechte Stelle (wie in Textprogrammen –
   * auch über automatisch umbrochene Zeilen im Textrahmen). Rückgabe: true, wenn behandelt.
   */
  lineMove(d, extend) {
    const lines = this.map?.lines || [];
    if (lines.length < 2) return false;
    const ta = this.ta, back = ta.selectionDirection === 'backward';
    const focus = extend ? (back ? ta.selectionStart : ta.selectionEnd) : (d < 0 ? ta.selectionStart : ta.selectionEnd);
    const anchor = extend ? (back ? ta.selectionEnd : ta.selectionStart) : null;
    let li = lines.findIndex(l => focus >= l.p0 && focus <= l.p1 && !(focus === l.p1 && lines[lines.indexOf(l) + 1]?.p0 === focus));
    if (li < 0) li = lines.findIndex(l => focus <= l.p1);
    const to = li + d;
    let target;
    if (to < 0) target = 0;
    else if (to >= lines.length) target = this.plain.length;
    else {
      const cur = this.caretPos(focus);
      if (this.goalX == null && cur) this.goalX = cur.x;
      const ln = lines[to];
      let best = ln.p0, bd = Infinity;
      for (let i = ln.p0; i <= ln.p1; i++) {
        if (i === ln.p1 && lines[to + 1]?.p0 === i) continue;   // Ende vor Trennstrich = Anfang der nächsten Zeile
        const c = this.caretPos(i);
        if (!c) continue;
        const dd = Math.abs(c.x - (this.goalX ?? 0));
        if (dd < bd) { bd = dd; best = i; }
      }
      target = best;
    }
    if (extend) ta.setSelectionRange(Math.min(anchor, target), Math.max(anchor, target), target < anchor ? 'backward' : 'forward');
    else ta.setSelectionRange(target, target);
    this.place();
    this.ed.draw();
    return true;
  }

  /** Eigenschaft für die Auswahl umschalten (alles fett → normal, sonst fett); ohne Auswahl: gilt für das nächste Getippte */
  toggle(prop, isOn, on, off) {
    const [a, b] = this.range;
    const base = getProp(this.el, prop);
    if (a === b) {
      const cur = this.pending?.[prop] ?? (toChars(this.model)[Math.max(0, a - 1)]?.s[prop] || base);
      this.pending = { ...(this.pending || toChars(this.model)[Math.max(0, a - 1)]?.s || {}), [prop]: isOn(cur) ? off : on };
      return;
    }
    const all = rangeHas(this.model, a, b, prop, v => isOn(v || base));
    this.format({ [prop]: all ? (isOn(off) === isOn(base) ? null : off) : (isOn(base) ? null : on) });
  }

  /** Stand vor einer Änderung merken (Tippen in einem Zug = ein Schritt) */
  push(kind) {
    const now = Date.now();
    if (kind === 'type' && this.lastKind === 'type' && now - this.lastAt < 1500) { this.lastAt = now; return; }
    this.ustack.push(this.state());
    if (this.ustack.length > 200) this.ustack.shift();
    this.rstack = []; this.lastKind = kind; this.lastAt = now;
  }
  state() {
    return { paras: JSON.parse(JSON.stringify(this.model.paras)), value: this.ta.value, a: this.ta.selectionStart, b: this.ta.selectionEnd, pending: this.pending };
  }
  /** ⌘Z / ⇧⌘Z während der Bearbeitung: Text und Formatierung Schritt für Schritt */
  undo(redo = false) {
    const from = redo ? this.rstack : this.ustack, to = redo ? this.ustack : this.rstack;
    if (!from.length) return false;
    to.push(this.state());
    const s = from.pop();
    this.model = { ...this.model, paras: s.paras };
    this.ta.value = s.value; this.pending = s.pending; this.dirty = true; this.lastKind = null;
    this.relayout();
    this.ta.setSelectionRange(s.a, s.b);
    this.place();
    this.ed.draw();
    this.ed.panels?.refresh();
    return true;
  }

  /** Stil auf die Auswahl anwenden (Farbe, Gewicht …) – Rückgabe: true, wenn es eine Auswahl gab */
  format(patch) {
    const [a, b] = this.range;
    if (a === b) { this.pending = { ...(this.pending || {}), ...patch }; return false; }
    this.push('format');
    this.model = styleRange(this.model, a, b, patch);
    this.dirty = true;
    this.relayout();
    this.ta.setSelectionRange(a, b);
    this.ed.draw();
    return true;
  }

  /** Groß-/Kleinschreibung für die Auswahl (ohne Auswahl: ganzer Text) */
  setCase(mode) {
    let [a, b] = this.range;
    if (a === b) { a = 0; b = Infinity; }
    this.push('case');
    this.model = transformCase(this.model, mode, a, b);
    this.ta.value = plainOf(this.model);
    this.dirty = true;
    this.relayout();
    this.ed.draw();
  }

  /** Textfeld an die Schreibmarke legen (Eingabehilfen erscheinen dort) */
  place() {
    const c = this.caretPos(this.range[1]);
    const ta = this.ta;
    const q = c ? this.ed.toOv(pt(docMatrix(this.el, this.ed.root), c.x, c.y)) : { x: 0, y: 0 };
    ta.style.left = q.x + 'px'; ta.style.top = q.y + 'px';
  }

  /** Position (Grundlinie) vor dem Zeichen i im Raum des Textes */
  caretPos(i) {
    const el = this.el, map = this.map;
    if (!map?.lines.length) return null;
    const ln = lineAt(map, i);
    const n = el.getNumberOfChars?.() || 0;
    try {
      if (!n) return { x: parseFloat(el.getAttribute('x')) || 0, y: parseFloat(el.getAttribute('y')) || 0, r: 0 };
      if (ln.empty) { const p = el.getStartPositionOfChar(Math.min(ln.s0, n - 1)); return { x: p.x, y: p.y, r: 0 }; }
      const j = ln.s0 + (i - ln.p0);
      if (i < ln.p1 && j < n) { const p = el.getStartPositionOfChar(j); return { x: p.x, y: p.y, r: el.getRotationOfChar(j) }; }
      const jj = Math.min(n - 1, Math.max(0, j - 1)), p = el.getEndPositionOfChar(jj);
      return { x: p.x, y: p.y, r: el.getRotationOfChar(jj) };
    } catch { return null; }
  }

  /** Überlagerung: Auswahl und Schreibmarke (Bildschirmpunkte der Zeichenfläche) */
  draw() {
    if (document.activeElement !== this.ta) return '';
    const el = this.el, M = docMatrix(el, this.ed.root), S = p => this.ed.toOv(pt(M, p.x, p.y));
    const fs = parseFloat(getProp(el, 'font-size')) || 16;
    let s = '';
    const [a, b] = this.range;
    if (a !== b) {
      for (const ln of this.map.lines) {
        if (ln.empty) continue;
        const from = Math.max(a, ln.p0), to = Math.min(b, ln.p1);
        for (let i = from; i < to; i++) {
          try {
            const r = el.getExtentOfChar(ln.s0 + i - ln.p0);
            const c = [S({ x: r.x, y: r.y }), S({ x: r.x + r.width, y: r.y }), S({ x: r.x + r.width, y: r.y + r.height }), S({ x: r.x, y: r.y + r.height })];
            s += `<polygon class="ge-ov__tsel" points="${c.map(p => p.x + ',' + p.y).join(' ')}"/>`;
          } catch { /* nicht gezeichnet */ }
        }
      }
    }
    const c = this.caretPos(b);
    if (c) {
      const rad = (c.r || 0) * Math.PI / 180, ux = Math.sin(rad), uy = -Math.cos(rad);
      const p1 = S({ x: c.x + ux * fs * 0.85, y: c.y + uy * fs * 0.85 }), p2 = S({ x: c.x - ux * fs * 0.22, y: c.y - uy * fs * 0.22 });
      s += `<line class="ge-ov__caret" x1="${p1.x}" y1="${p1.y}" x2="${p2.x}" y2="${p2.y}"/>`;
    }
    // Textrahmen: gestrichelte Breite
    return s;
  }

  /** Klartext-Index unter einem Bildschirmpunkt */
  indexAt(cx, cy) {
    const el = this.el;
    const m = el.getScreenCTM();
    if (!m) return 0;
    const p = new DOMPoint(cx, cy).matrixTransform(DOMMatrix.fromMatrix(m).inverse());
    let j = -1;
    try { j = el.getCharNumAtPosition(p); } catch { /* alt */ }
    if (j >= 0) {
      const r = el.getExtentOfChar(j);
      const i = plainIndex(this.map, j);
      return p.x > r.x + r.width / 2 ? i + 1 : i;
    }
    // neben dem Text: nächste Zeile nach der Höhe, dann Anfang oder Ende
    let best = null;
    for (const ln of this.map.lines) {
      const c = this.caretPos(ln.p0);
      if (!c) continue;
      const d = Math.abs(c.y - p.y);
      if (!best || d < best.d) best = { d, ln, x: c.x };
    }
    if (!best) return 0;
    return p.x < best.x ? best.ln.p0 : best.ln.p1;
  }

  /** Zeiger im Text: Schreibmarke setzen, Ziehen = auswählen, Doppelklick = Wort */
  pointerDown(e) {
    e.preventDefault();
    const i = this.indexAt(e.clientX, e.clientY);
    const ta = this.ta;
    if (e.detail === 2) {
      const v = ta.value;
      let a = i, b = i;
      while (a > 0 && /[\p{L}\p{N}_-]/u.test(v[a - 1])) a--;
      while (b < v.length && /[\p{L}\p{N}_-]/u.test(v[b])) b++;
      ta.focus({ preventScroll: true }); ta.setSelectionRange(a, b); this.ed.draw();
      return;
    }
    const anchor = e.shiftKey ? ta.selectionStart : i;
    ta.focus({ preventScroll: true });
    ta.setSelectionRange(Math.min(anchor, i), Math.max(anchor, i), i < anchor ? 'backward' : 'forward');
    this.ed.draw();
    const st = this.ed.stage;
    st.setPointerCapture?.(e.pointerId);
    const mv = ev => {
      const k = this.indexAt(ev.clientX, ev.clientY);
      ta.setSelectionRange(Math.min(anchor, k), Math.max(anchor, k), k < anchor ? 'backward' : 'forward');
      this.ed.draw();
    };
    const up = () => { st.removeEventListener('pointermove', mv); st.removeEventListener('pointerup', up); ta.focus({ preventScroll: true }); };
    st.addEventListener('pointermove', mv); st.addEventListener('pointerup', up);
  }

  /** Ist der Bildschirmpunkt im Text (Rahmen des Textes)? */
  contains(cx, cy) {
    try {
      const b = this.el.getBBox(), m = this.el.getScreenCTM();
      const p = new DOMPoint(cx, cy).matrixTransform(DOMMatrix.fromMatrix(m).inverse());
      const pad = (parseFloat(getProp(this.el, 'font-size')) || 16) * 0.3;
      const w = Math.max(b.width, parseFloat(this.el.getAttribute('data-ge-frame')) || 0);
      return p.x >= b.x - pad && p.x <= b.x + w + pad && p.y >= b.y - pad && p.y <= b.y + b.height + pad;
    } catch { return false; }
  }

  /** Bearbeitung beenden – Rückgabe: geändert? (unverändert: alter Inhalt Zeichen für Zeichen zurück) */
  end() {
    document.removeEventListener('selectionchange', this.onSelChange);
    const ta = this.ta;
    ta.hidden = true; ta.oninput = ta.onkeydown = ta.onkeyup = ta.onselect = ta.onbeforeinput = null;
    const now = JSON.stringify(this.model.paras) + (this.el.getAttribute('data-ge-frame') || '');
    const changed = this.dirty && now !== this.before;
    if (!changed && !this.fresh) {
      const el = this.el;
      for (const a of [...el.attributes]) el.removeAttributeNS(a.namespaceURI, a.localName);
      for (const [n, v, ns] of this.snap.attrs) ns ? el.setAttributeNS(ns, n, v) : el.setAttribute(n, v);
      while (el.firstChild) el.firstChild.remove();
      el.append(...this.snap.kids);
    }
    return changed;
  }

  get plain() { return plainOf(this.model); }
}
