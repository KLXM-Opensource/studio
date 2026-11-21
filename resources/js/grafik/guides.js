/*
 * Grafik-Editor – Lineale, Hilfslinien, Raster (wie Affinity/Illustrator: ⌘R Lineale, ⌘; Hilfslinien, ⌘' Raster).
 *
 * Hilfslinien und Raster gehören zum Dokument, aber nur zur Bearbeitung: sie stehen als Editor-Daten an der Wurzel
 * (data-ge-guides="v:120 h:40", data-ge-grid="Abstand Unterteilung") – die Ausgabe für das Web entfernt sie, Rückgängig
 * erfasst sie. Lineale in Pixeln (Einheiten der viewBox), Nullpunkt oben links der Zeichenfläche.
 * Bedienung: aus einem Lineal ziehen = neue Hilfslinie, Linie ziehen = verschieben, zurück aufs Lineal = löschen,
 * Doppelklick = genaue Position. Tastatur: Menü „Ansicht“ → „Hilfslinien …“ (Liste zum Bearbeiten). MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { num } from './geom.js';
import { modal } from './ui.js';

export const RULER = 20;

/** Hilfslinien der Grafik: [{ o: 'v'|'h', p }] */
export function readGuides(root) {
  return String(root.getAttribute('data-ge-guides') || '').trim().split(/\s+/).filter(Boolean).map(s => {
    const [o, p] = s.split(':');
    return (o === 'v' || o === 'h') && Number.isFinite(+p) ? { o, p: +p } : null;
  }).filter(Boolean);
}
export function writeGuides(root, list) {
  const v = list.map(g => g.o + ':' + num(g.p, 2)).join(' ');
  if (v) root.setAttribute('data-ge-guides', v); else root.removeAttribute('data-ge-guides');
}

/** Raster: { size, sub } – Symbole standardmäßig 1 px, sonst 10 mit 1 Unterteilung */
export function readGrid(root, format) {
  const [a, b] = String(root.getAttribute('data-ge-grid') || '').split(/\s+/).map(Number);
  return { size: a > 0 ? a : (format === 'icon' ? 1 : format === 'preview' ? 4 : 10), sub: b >= 1 ? Math.min(20, Math.round(b)) : (format === 'icon' ? 2 : 1) };
}

/** Schrittweite der Lineal-Beschriftung: Abstand am Bildschirm mindestens ~ 60 px */
function step(zoom) {
  for (const s of [0.5, 1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000]) if (s * zoom >= 60) return s;
  return 10000;
}

export class Guides {
  constructor(ed) {
    this.ed = ed;
    const pref = k => { try { return localStorage.getItem('klxm-studio-grafik-' + k); } catch { return null; } };
    this.showRulers = pref('rulers') !== '0';
    this.showGuides = pref('guides') !== '0';
    const st = ed.stage;
    this.h = document.createElement('canvas'); this.h.className = 'ge-ruler ge-ruler--h';
    this.v = document.createElement('canvas'); this.v.className = 'ge-ruler ge-ruler--v';
    this.c = document.createElement('div'); this.c.className = 'ge-ruler__corner';
    for (const el of [this.h, this.v]) {
      el.setAttribute('aria-hidden', 'true');
      el.addEventListener('pointerdown', e => this.fromRuler(e, el === this.h ? 'h' : 'v'));
    }
    st.append(this.h, this.v, this.c);
    this.apply();
  }

  save(k, v) { try { localStorage.setItem('klxm-studio-grafik-' + k, v ? '1' : '0'); } catch { /* privat */ } }

  apply() {
    this.ed.el.classList.toggle('has-rulers', this.showRulers);
    for (const el of [this.h, this.v, this.c]) el.hidden = !this.showRulers;
    this.draw();
  }

  toggleRulers() { this.showRulers = !this.showRulers; this.save('rulers', this.showRulers); this.apply(); }
  toggleGuides() { this.showGuides = !this.showGuides; this.save('guides', this.showGuides); this.ed.snapCache = null; this.ed.draw(); }

  /** Lineale zeichnen (Canvas, Farben aus den Tokens der Verwaltung) */
  draw() {
    if (!this.showRulers || !this.ed.root) return;
    const ed = this.ed, r = ed.stage.getBoundingClientRect(), dpr = window.devicePixelRatio || 1;
    const cs = getComputedStyle(ed.el);
    const bg = cs.getPropertyValue('--adm-card').trim() || '#fff', ink = cs.getPropertyValue('--adm-t2').trim() || '#555', line = cs.getPropertyValue('--adm-line').trim() || '#ddd';
    const s = step(ed.zoom), minor = s / (String(s).startsWith('5') || String(s).startsWith('25') ? 5 : 10);
    const vb = ed.vb;
    for (const [cv, horiz] of [[this.h, true], [this.v, false]]) {
      const w = horiz ? r.width : RULER, hh = horiz ? RULER : r.height;
      if (cv.width !== Math.round(w * dpr) || cv.height !== Math.round(hh * dpr)) { cv.width = Math.round(w * dpr); cv.height = Math.round(hh * dpr); }
      cv.style.width = w + 'px'; cv.style.height = hh + 'px';
      const x = cv.getContext('2d');
      x.setTransform(dpr, 0, 0, dpr, 0, 0);
      x.fillStyle = bg; x.fillRect(0, 0, w, hh);
      x.strokeStyle = line; x.beginPath();
      if (horiz) { x.moveTo(0, hh - 0.5); x.lineTo(w, hh - 0.5); } else { x.moveTo(w - 0.5, 0); x.lineTo(w - 0.5, hh); }
      x.stroke();
      // sichtbarer Bereich in Dokumentkoordinaten
      const a = ed.toDoc(r.left, r.top), b = ed.toDoc(r.right, r.bottom);
      const lo = horiz ? a.x : a.y, hi = horiz ? b.x : b.y;
      x.fillStyle = ink; x.strokeStyle = ink; x.font = '10px ' + (cs.getPropertyValue('--adm-font').trim() || 'sans-serif'); x.lineWidth = 1;
      x.beginPath();
      for (let v = Math.floor(lo / minor) * minor; v <= hi; v += minor) {
        const q = ed.toOv(horiz ? { x: v, y: vb.y } : { x: vb.x, y: v });
        const pos = Math.round(horiz ? q.x : q.y) + 0.5;
        const major = Math.abs(v / s - Math.round(v / s)) < 1e-6;
        const len = major ? RULER : RULER * 0.3;
        if (horiz) { x.moveTo(pos, hh); x.lineTo(pos, hh - len); } else { x.moveTo(w, pos); x.lineTo(w - len, pos); }
        if (major) {
          const label = num(v - (horiz ? vb.x : vb.y), 1);
          if (horiz) x.fillText(label, pos + 3, 10);
          else { x.save(); x.translate(10, pos - 3); x.rotate(-Math.PI / 2); x.fillText(label, 0, 0); x.restore(); }
        }
      }
      x.stroke();
      // Lage der Auswahl auf dem Lineal
      const sb = ed.sel.length ? ed.selBounds() : null;
      if (sb) {
        const p1 = ed.toOv({ x: sb.x, y: sb.y }), p2 = ed.toOv({ x: sb.x + sb.w, y: sb.y + sb.h });
        x.fillStyle = cs.getPropertyValue('--adm-a').trim() || '#5B73AD'; x.globalAlpha = 0.25;
        if (horiz) x.fillRect(p1.x, 0, p2.x - p1.x, hh); else x.fillRect(0, p1.y, w, p2.y - p1.y);
        x.globalAlpha = 1;
      }
    }
  }

  /** Überlagerung: Hilfslinien (mit breiter, unsichtbarer Trefferfläche) und Raster */
  overlay() {
    const ed = this.ed, out = [];
    const r = ed.stage.getBoundingClientRect();
    if (ed.opt.grid) out.push(this.gridPath(r));
    if (this.showGuides) readGuides(ed.root).forEach((g, i) => {
      const q = ed.toOv(g.o === 'v' ? { x: g.p, y: 0 } : { x: 0, y: g.p });
      const a = g.o === 'v' ? `x1="${q.x}" y1="0" x2="${q.x}" y2="${r.height}"` : `x1="0" y1="${q.y}" x2="${r.width}" y2="${q.y}"`;
      out.push(`<line class="ge-ov__uguide" ${a}/><line class="ge-ov__ghit ge-ov__ghit--${g.o}" data-guide="${i}" ${a}/>`);
    });
    return out.join('');
  }

  gridPath(r) {
    const ed = this.ed, g = readGrid(ed.root, ed.format), vb = ed.vb;
    const lines = (size, cls) => {
      if (size * ed.zoom < 6) return '';
      const a = ed.toOv({ x: vb.x, y: vb.y }), b = ed.toOv({ x: vb.x + vb.w, y: vb.y + vb.h });
      let d = '';
      for (let x = vb.x; x <= vb.x + vb.w + 1e-9; x += size) { const p = ed.toOv({ x, y: vb.y }); d += `M${num(p.x, 1)} ${num(Math.max(0, a.y), 1)}V${num(Math.min(r.height, b.y), 1)}`; }
      for (let y = vb.y; y <= vb.y + vb.h + 1e-9; y += size) { const p = ed.toOv({ x: vb.x, y }); d += `M${num(Math.max(0, a.x), 1)} ${num(p.y, 1)}H${num(Math.min(r.width, b.x), 1)}`; }
      return d ? `<path class="${cls}" d="${d}"/>` : '';
    };
    return (g.sub > 1 ? lines(g.size / g.sub, 'ge-ov__grid ge-ov__grid--minor') : '') + lines(g.size, 'ge-ov__grid');
  }

  /** Einrasten: Positionen der Hilfslinien */
  targets() {
    if (!this.showGuides) return { xs: [], ys: [] };
    const gs = readGuides(this.ed.root);
    return { xs: gs.filter(g => g.o === 'v').map(g => g.p), ys: gs.filter(g => g.o === 'h').map(g => g.p) };
  }

  /** Raster-Einrasten (an Linien und Unterteilungen) */
  snapGrid(v) {
    const g = readGrid(this.ed.root, this.ed.format), s = g.size / g.sub;
    return Math.round(v / s) * s;
  }

  /** Neue Hilfslinie aus einem Lineal ziehen (oberes Lineal → waagrechte Linie) */
  fromRuler(e, ruler) {
    e.preventDefault(); e.stopPropagation();
    const o = ruler === 'h' ? 'h' : 'v';
    const list = readGuides(this.ed.root);
    list.push({ o, p: 0 });
    this.dragGuide(e, list.length - 1, list, true);
  }

  /** Hilfslinie ziehen; über einem Lineal bzw. außerhalb loslassen = löschen */
  dragGuide(e, i, list = readGuides(this.ed.root), fresh = false) {
    const ed = this.ed, st = ed.stage;
    if (!this.showGuides) { this.showGuides = true; this.save('guides', true); }
    st.setPointerCapture?.(e.pointerId);
    ed.drag = true;
    const r = st.getBoundingClientRect();
    const out = ev => (list[i].o === 'h' ? ev.clientY - r.top < RULER : ev.clientX - r.left < RULER) && this.showRulers || ev.clientX < r.left || ev.clientY < r.top || ev.clientX > r.right || ev.clientY > r.bottom;
    const mv = ev => {
      const p = ed.toDoc(ev.clientX, ev.clientY);
      let v = list[i].o === 'v' ? p.x : p.y;
      if (ed.opt.grid) v = this.snapGrid(v);
      else if (!ev.shiftKey) v = Math.round(v * (ed.zoom >= 4 ? 10 : 1)) / (ed.zoom >= 4 ? 10 : 1);
      list[i].p = v;
      writeGuides(ed.root, out(ev) ? list.filter((_, k) => k !== i) : list);
      ed.hint(out(ev) ? t('Loslassen = Hilfslinie löschen') : (list[i].o === 'v' ? 'X ' : 'Y ') + num(v - (list[i].o === 'v' ? ed.vb.x : ed.vb.y), 2));
      ed.draw();
    };
    const up = ev => {
      st.removeEventListener('pointermove', mv); st.removeEventListener('pointerup', up); st.removeEventListener('pointercancel', up);
      ed.drag = false; ed.hint(); ed.snapCache = null;
      const del = out(ev);
      writeGuides(ed.root, del ? list.filter((_, k) => k !== i) : list);
      if (fresh && del) { ed.draw(); return; }
      ed.commit(del ? t('Hilfslinie gelöscht') : fresh ? t('Hilfslinie') : t('Hilfslinie verschoben'));
    };
    st.addEventListener('pointermove', mv); st.addEventListener('pointerup', up); st.addEventListener('pointercancel', up);
    mv(e);
  }

  /** Hilfslinien als Liste bearbeiten (Tastatur): Ausrichtung, Position, Löschen, Hinzufügen */
  async dialog(focus = null) {
    const ed = this.ed, vb = ed.vb;
    let list = readGuides(ed.root);
    const row = (g, i) => `<li class="ge-guide-row"><select name="o${i}" aria-label="${esc(t('Ausrichtung'))}"><option value="v"${g.o === 'v' ? ' selected' : ''}>${esc(t('Senkrecht (X)'))}</option><option value="h"${g.o === 'h' ? ' selected' : ''}>${esc(t('Waagrecht (Y)'))}</option></select>
      <input type="number" name="p${i}" step="0.5" value="${esc(num(g.p - (g.o === 'v' ? vb.x : vb.y), 2))}" aria-label="${esc(t('Position'))}"><button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-del="${i}">${esc(t('Löschen'))}</button></li>`;
    const r = await modal(ed.el, {
      title: t('Hilfslinien'), ok: t('Übernehmen'),
      body: `<ul class="ge-guide-list" data-list>${list.map(row).join('')}</ul>
        <button type="button" class="adm-btn adm-btn--small" data-add>${esc(t('Hilfslinie hinzufügen'))}</button>
        <p class="f-help">${esc(t('Position in Pixeln ab der linken bzw. oberen Kante. Auf der Fläche: aus dem Lineal ziehen, zurück aufs Lineal = löschen.'))}</p>`,
      onOpen: d => {
        const ul = d.querySelector('[data-list]');
        const sync = () => { list = [...ul.querySelectorAll('li')].map(li => { const o = li.querySelector('select').value; const v = +li.querySelector('input').value || 0; return { o, p: v + (o === 'v' ? vb.x : vb.y) }; }); };
        const render = () => { ul.innerHTML = list.map(row).join(''); };
        d.querySelector('[data-add]').addEventListener('click', () => { sync(); list.push({ o: 'v', p: vb.x + vb.w / 2 }); render(); ul.querySelector('li:last-child input')?.focus(); });
        ul.addEventListener('click', ev => { const b = ev.target.closest('[data-del]'); if (!b) return; sync(); list.splice(+b.dataset.del, 1); render(); d.querySelector('[data-add]').focus(); });
        d._sync = sync;
        if (focus != null) ul.querySelectorAll('input')[focus]?.focus();
      },
    });
    if (!r) return;
    const out = [];
    for (let i = 0; i < 500; i++) {
      if (!(('o' + i) in r)) { if (i > list.length + 50) break; continue; }
      const o = r['o' + i] === 'h' ? 'h' : 'v';
      out.push({ o, p: (+r['p' + i] || 0) + (o === 'v' ? vb.x : vb.y) });
    }
    writeGuides(ed.root, out);
    ed.commit(t('Hilfslinien geändert'));
  }
}
