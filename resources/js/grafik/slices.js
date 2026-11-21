/*
 * Grafik-Editor – Persona „Export“ (Ausbaustufe 5): Bereiche (Slices wie in Fireworks/Affinity) auf der Zeichenfläche, je Bereich
 * Formate: SVG fürs Web, SVG für Desktop-Programme, PDF (Vektor) und – als einzige Pixel-Ausgaben – Favicon (ICO 16/32/48 + PNG),
 * App-Symbole (180/192/512 + „maskable“ mit Schutzzone) und Social-Vorschau (1200 × 630 PNG/JPEG). Kleine Größen mit Ausrichtung am
 * Pixelraster je Größe (Vorschau 16/24/32/48). Alles als ZIP; „Als Favicon/App-Symbol der Website übernehmen“ nutzt die
 * App-Icons des Kerns (Core\AppIcons: Bild aus der Mediathek).
 * Modell an der Wurzel: data-ge-slices = Base64(JSON) [{id, n, x, y, w, h, f: [Formate], bg: '#rrggbb' | '', snap: {16: 1, …}}] –
 * Bereiche gehören zum Dokument (Rückgängig wie sonst), die Web-Fassung trägt sie nicht. MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { toast } from '../_toast.js';
import { num } from './geom.js';
import { h, modal } from './ui.js';
import { optimizeSvg, desktopSvg, pdfBlob } from './export.js';
import { hasMotion } from './anim.js';
import { renderPng, icoFile, zipFile, bytes } from './raster.js';

export const FORMATS = ['svg', 'svgd', 'pdf', 'ico', 'app', 'social', 'socialjpg'];
const FORMAT_LABEL = {
  svg: () => t('SVG fürs Web'), svgd: () => t('SVG für Desktop-Programme'), pdf: () => t('PDF (Vektor)'),
  ico: () => t('Favicon (ICO 16/32/48 und PNG)'), app: () => t('App-Symbole (180/192/512, maskable)'),
  social: () => t('Social-Vorschau PNG (1200 × 630)'), socialjpg: () => t('Social-Vorschau JPEG (1200 × 630)'),
};
const RASTER = ['ico', 'app', 'social', 'socialjpg'];
export const ICON_SIZES = [16, 24, 32, 48];

const b64 = s => btoa(String.fromCharCode(...new TextEncoder().encode(s)));
const unb64 = s => new TextDecoder().decode(Uint8Array.from(atob(s), c => c.charCodeAt(0)));
export function getSlices(root) {
  const v = root?.getAttribute?.('data-ge-slices');
  if (!v) return [];
  try { const l = JSON.parse(unb64(v)); return Array.isArray(l) ? l.filter(s => s && s.w > 0 && s.h > 0) : []; } catch { return []; }
}
export function setSlices(root, list) {
  if (!list.length) root.removeAttribute('data-ge-slices'); else root.setAttribute('data-ge-slices', b64(JSON.stringify(list)));
}
const fileSlug = s => String(s || 'bereich').toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss').normalize('NFKD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'bereich';

export class ExportPersona {
  constructor(ed) {
    this.ed = ed;
    this.mounted = false;
    this.cur = null;       // id des gewählten Bereichs
    this.previews = {};    // Vorschaubilder (Object-URLs) je Größe
  }

  get persona() {
    return { id: 'export', label: t('Export'), icon: 'export', mount: (ed, area) => this.mount(area), unmount: () => this.unmount(), overlay: () => this.overlay() };
  }
  get root() { return this.ed.root; }
  get slices() { return getSlices(this.root); }
  get slice() { return this.slices.find(s => s.id === this.cur) || null; }

  // ================================================================= Ein- und Aushängen
  mount(area) {
    const ed = this.ed;
    this.mounted = true;
    this.area = area;
    area.classList.add('gx-area');
    area.innerHTML = `<div class="gx-sizes" role="group" aria-label="${esc(t('Vorschau der Größen'))}"></div>`;
    if (!this.side) {
      this.side = h(`<section class="ge-pg ge-pg--export" aria-label="${esc(t('Export'))}"><div class="ge-pg__panel gx-panel"></div></section>`);
      this.bindSide();
    }
    ed.$('.ge-side').prepend(this.side);
    this.side.hidden = false;
    if (!this.slices.some(s => s.id === this.cur)) this.cur = this.slices[0]?.id || null;
    this.offs = [ed.on('change', () => this.refresh())];
    // Auswahl des Bereichs bleibt über Rückgängig/Wiederholen bestehen (die id kommt mit dem Dokument zurück)
    this.down = e => this.stageDown(e);
    ed.stage.addEventListener('pointerdown', this.down, true);
    this.keys = e => this.onKey(e);
    document.addEventListener('keydown', this.keys, true);
    ed.select([]);
    this.refresh();
    requestAnimationFrame(() => ed.fit());
    ed.hint(t('Export: auf der Fläche einen Bereich aufziehen (Slice), ziehen = verschieben, Ecken = Größe, Pfeiltasten = 1 (⇧ 10), ⌫ = löschen. Formate je Bereich im Bereich rechts.'));
  }

  unmount() {
    const ed = this.ed;
    this.mounted = false;
    this.offs?.forEach(f => f());
    ed.stage.removeEventListener('pointerdown', this.down, true);
    document.removeEventListener('keydown', this.keys, true);
    if (this.side) this.side.hidden = true;
    this.area?.classList.remove('gx-area');
    this.clearPreviews();
    requestAnimationFrame(() => { ed.fit(); ed.hint(); });
    ed.draw();
  }

  refresh() {
    if (!this.mounted) return;
    this.renderSide();
    this.renderSizes();
    this.ed.draw();
  }

  // ================================================================= Überlagerung (Bereiche, Griffe, Schutzzone)
  overlay() {
    if (!this.mounted) return '';
    const ed = this.ed, out = [];
    for (const s of [...this.slices, ...(this.drag?.ghost ? [this.drag.ghost] : [])]) {
      const a = ed.toOv({ x: s.x, y: s.y }), b = ed.toOv({ x: s.x + s.w, y: s.y + s.h });
      const sel = s.id === this.cur;
      out.push(`<rect class="gx-slice${sel ? ' is-sel' : ''}" x="${a.x}" y="${a.y}" width="${b.x - a.x}" height="${b.y - a.y}"/>`);
      if ((s.f || []).includes('app')) {
        const r = Math.min(b.x - a.x, b.y - a.y) * 0.4;
        out.push(`<circle class="gx-safe" cx="${(a.x + b.x) / 2}" cy="${(a.y + b.y) / 2}" r="${r}"/>`);
      }
      if (s.n) out.push(`<text class="gx-label${sel ? ' is-sel' : ''}" x="${a.x + 4}" y="${a.y - 5}">${esc(s.n)} · ${num(s.w)} × ${num(s.h)}</text>`);
      if (sel && !this.drag?.ghost) for (const [hx, hy] of [[a.x, a.y], [b.x, a.y], [a.x, b.y], [b.x, b.y]]) out.push(`<rect class="gx-h" x="${hx - 4}" y="${hy - 4}" width="8" height="8"/>`);
    }
    return out.join('');
  }

  /** Treffer: Griff (0–3: oben links, oben rechts, unten links, unten rechts) oder Fläche eines Bereichs */
  hit(e) {
    const ed = this.ed, r = ed.stage.getBoundingClientRect(), mx = e.clientX - r.left, my = e.clientY - r.top;
    const s = this.slice;
    if (s) {
      const a = ed.toOv({ x: s.x, y: s.y }), b = ed.toOv({ x: s.x + s.w, y: s.y + s.h });
      const corners = [[a.x, a.y], [b.x, a.y], [a.x, b.y], [b.x, b.y]];
      const i = corners.findIndex(([x, y]) => Math.abs(mx - x) <= 7 && Math.abs(my - y) <= 7);
      if (i >= 0) return { s, handle: i };
    }
    const p = ed.toDoc(e.clientX, e.clientY);
    const list = this.slices;
    for (let i = list.length - 1; i >= 0; i--) { const q = list[i]; if (p.x >= q.x && p.x <= q.x + q.w && p.y >= q.y && p.y <= q.y + q.h) return { s: q, handle: -1 }; }
    return null;
  }

  stageDown(e) {
    if (e.button !== 0 || this.ed.space || this.ed.tool === 'hand' || this.ed.tool === 'zoom') return;
    e.preventDefault(); e.stopPropagation();
    this.ed.stage.focus({ preventScroll: true });
    const ed = this.ed, p0 = ed.toDoc(e.clientX, e.clientY), hit = this.hit(e);
    const R = v => Math.round(v);
    const vb = ed.vb;
    let mode = 'new', start = null;
    if (hit) { this.cur = hit.s.id; mode = hit.handle >= 0 ? 'resize' : 'move'; start = { ...hit.s }; this.renderSide(); this.renderSizes(); }
    this.drag = { mode, ghost: null };
    let moved = false;
    const mv = ev => {
      const p = ed.toDoc(ev.clientX, ev.clientY);
      const dx = p.x - p0.x, dy = p.y - p0.y;
      if (!moved && Math.hypot(ev.clientX - e.clientX, ev.clientY - e.clientY) < 3) return;
      moved = true;
      const list = this.slices;
      if (mode === 'new') {
        let x0 = Math.min(p0.x, p.x), y0 = Math.min(p0.y, p.y), w = Math.abs(dx), hh = Math.abs(dy);
        if (ev.shiftKey) { const m = Math.max(w, hh); w = hh = m; if (p.x < p0.x) x0 = p0.x - m; if (p.y < p0.y) y0 = p0.y - m; }
        this.drag.ghost = { id: '_neu', n: '', x: R(x0), y: R(y0), w: Math.max(1, R(w)), h: Math.max(1, R(hh)), f: [] };
      } else {
        const s = list.find(q => q.id === start.id);
        if (!s) return;
        if (mode === 'move') { s.x = R(start.x + dx); s.y = R(start.y + dy); }
        else {
          const hdl = hit.handle, left = hdl === 0 || hdl === 2, top = hdl === 0 || hdl === 1;
          let x0 = left ? start.x + dx : start.x, x1 = left ? start.x + start.w : start.x + start.w + dx;
          let y0 = top ? start.y + dy : start.y, y1 = top ? start.y + start.h : start.y + start.h + dy;
          if (ev.shiftKey || (s.f || []).some(f => f === 'ico' || f === 'app')) {   // Symbole bleiben quadratisch
            const m = Math.max(Math.abs(x1 - x0), Math.abs(y1 - y0));
            if (left) x0 = x1 - m; else x1 = x0 + m;
            if (top) y0 = y1 - m; else y1 = y0 + m;
          }
          s.x = R(Math.min(x0, x1)); s.y = R(Math.min(y0, y1)); s.w = Math.max(1, R(Math.abs(x1 - x0))); s.h = Math.max(1, R(Math.abs(y1 - y0)));
        }
        setSlices(this.root, list);   // Vorschau während des Ziehens; ein Schritt im Protokoll beim Loslassen
      }
      ed.draw();
      ed.$('[data-pos]').textContent = `X ${num(p.x)} Y ${num(p.y)}`;
    };
    const up = () => {
      window.removeEventListener('pointermove', mv); window.removeEventListener('pointerup', up);
      const g = this.drag.ghost;
      this.drag = null;
      if (mode === 'new') {
        if (moved && g && g.w >= 2 && g.h >= 2) {
          const list = this.slices;
          const id = 's' + (Math.max(0, ...list.map(s => +String(s.id).slice(1) || 0)) + 1);
          const square = Math.abs(g.w - g.h) < 1;
          list.push({ ...g, id, n: t('Bereich {n}', { n: list.length + 1 }), f: square ? ['svg', 'ico', 'app'] : ['svg', 'pdf'], bg: square ? '#ffffff' : '', snap: { 16: 1, 24: 1, 32: 1, 48: 1 } });
          setSlices(this.root, list);
          this.cur = id;
          ed.commit(t('Bereich angelegt'));
        } else if (!moved) { this.cur = null; this.refresh(); }
        else ed.draw();
        return;
      }
      if (moved) ed.commit(mode === 'move' ? t('Bereich verschoben') : t('Bereichsgröße'));
      else this.refresh();
    };
    window.addEventListener('pointermove', mv); window.addEventListener('pointerup', up);
    void vb;
  }

  onKey(e) {
    // nur mit dem Fokus auf der Zeichenfläche (in der Liste der Bereiche gelten deren eigene Tasten)
    if (!this.mounted || !e.target.closest?.('.ge-stage')) return;
    if (e.metaKey || e.ctrlKey || e.altKey) return;
    const s = this.slice;
    if (!s) return;
    const d = e.shiftKey ? 10 : 1;
    const mv = { ArrowLeft: [-d, 0], ArrowRight: [d, 0], ArrowUp: [0, -d], ArrowDown: [0, d] }[e.key];
    if (mv) {
      e.preventDefault(); e.stopPropagation();
      const list = this.slices, q = list.find(x => x.id === s.id);
      q.x += mv[0]; q.y += mv[1];
      setSlices(this.root, list);
      this.ed.commit(t('Bereich verschoben'));
      return;
    }
    if (e.key === 'Backspace' || e.key === 'Delete') { e.preventDefault(); e.stopPropagation(); this.remove(s.id); return; }
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); this.cur = null; this.refresh(); }
  }

  remove(id) {
    const all = this.slices, i = all.findIndex(s => s.id === id);
    const list = all.filter(s => s.id !== id);
    setSlices(this.root, list);
    this.cur = (list[i] || list[i - 1] || list[0])?.id || null;
    this.ed.commit(t('Bereich gelöscht'));
  }

  /** Bereich mit Vorgabe: ganze Zeichenfläche, Symbol (quadratisch, Mitte) oder Social-Vorschau (1,905 : 1) */
  addPreset(kind) {
    const vb = this.ed.vb, list = this.slices;
    const id = 's' + (Math.max(0, ...list.map(s => +String(s.id).slice(1) || 0)) + 1);
    let s;
    if (kind === 'icon') { const m = Math.round(Math.min(vb.w, vb.h)); s = { n: t('App-Symbol'), x: Math.round(vb.x + (vb.w - m) / 2), y: Math.round(vb.y + (vb.h - m) / 2), w: m, h: m, f: ['svg', 'ico', 'app'], bg: '#ffffff' }; }
    else if (kind === 'social') {
      let w = vb.w, hh = Math.round(vb.w * 630 / 1200);
      if (hh > vb.h) { hh = vb.h; w = Math.round(vb.h * 1200 / 630); }
      s = { n: t('Social-Vorschau'), x: Math.round(vb.x + (vb.w - w) / 2), y: Math.round(vb.y + (vb.h - hh) / 2), w: Math.round(w), h: Math.round(hh), f: ['social'], bg: '#ffffff' };
    } else s = { n: t('Zeichenfläche'), x: Math.round(vb.x), y: Math.round(vb.y), w: Math.round(vb.w), h: Math.round(vb.h), f: ['svg', 'pdf'], bg: '' };
    list.push({ id, ...s, snap: { 16: 1, 24: 1, 32: 1, 48: 1 } });
    setSlices(this.root, list);
    this.cur = id;
    this.ed.commit(t('Bereich „{name}“ angelegt', { name: s.n }));
  }

  // ================================================================= Bereich rechts
  renderSide() {
    if (!this.side || this.side.hidden) return;
    const P = this.side.querySelector('.gx-panel');
    const key = this.side.contains(document.activeElement) ? document.activeElement.dataset.focuskey : null;
    const list = this.slices, s = this.slice;
    const canIcon = !!this.ed.boot.canAppIcon && !!this.ed.boot.urls.appIcon;
    P.innerHTML = `<div class="ge-form"><h2 class="ge-h3 gm-h3">${esc(t('Bereiche'))}</h2>
      ${list.length ? `<ul class="gx-list" role="listbox" aria-label="${esc(t('Bereiche'))}">${list.map(x => `<li role="option" aria-selected="${x.id === this.cur}" tabindex="${x.id === this.cur || (!this.cur && x === list[0]) ? 0 : -1}" data-slice="${esc(x.id)}" data-focuskey="sl-${esc(x.id)}"><strong>${esc(x.n || x.id)}</strong> <span class="ge-note">${num(x.w)} × ${num(x.h)} · ${esc((x.f || []).map(f => ({ svg: 'SVG', svgd: 'SVG Desktop', pdf: 'PDF', ico: 'ICO', app: t('App'), social: 'PNG 1200', socialjpg: 'JPEG 1200' }[f])).join(', ') || t('keine Formate'))}</span></li>`).join('')}</ul>`
        : `<p class="ge-note">${esc(t('Noch keine Bereiche. Auf der Fläche einen Bereich aufziehen oder eine Vorlage wählen.'))}</p>`}
      <div class="gx-presets"><button type="button" class="adm-btn adm-btn--small" data-preset="all" data-focuskey="p-all">${ico('frame-corners')} ${esc(t('Ganze Zeichenfläche'))}</button>
        <button type="button" class="adm-btn adm-btn--small" data-preset="icon" data-focuskey="p-icon">${ico('squares-four')} ${esc(t('App-Symbol (quadratisch)'))}</button>
        <button type="button" class="adm-btn adm-btn--small" data-preset="social" data-focuskey="p-social">${ico('share-network')} ${esc(t('Social-Vorschau'))}</button></div></div>
      ${s ? `<div class="ge-form gx-props">
        <label class="ge-lbl">${esc(t('Name'))} <input type="text" class="ge-in" data-sf="n" data-focuskey="f-n" maxlength="60" value="${esc(s.n || '')}"></label>
        <div class="ge-row4">${['x', 'y', 'w', 'h'].map(k => `<label>${esc({ x: 'X', y: 'Y', w: t('B'), h: t('H') }[k])} <input type="number" class="ge-in ge-in--s" step="1" ${k === 'w' || k === 'h' ? 'min="1"' : ''} data-sf="${k}" data-focuskey="f-${k}" value="${esc(num(s[k]))}" aria-label="${esc({ x: t('X-Position'), y: t('Y-Position'), w: t('Breite'), h: t('Höhe') }[k])}"></label>`).join('')}</div>
        <fieldset class="gx-formats"><legend class="ge-h3">${esc(t('Formate'))}</legend>
          ${FORMATS.map(f => `<label class="ge-lbl ge-chk"><input type="checkbox" data-fmt="${f}" data-focuskey="fmt-${f}"${(s.f || []).includes(f) ? ' checked' : ''}> ${esc(FORMAT_LABEL[f]())}</label>`).join('')}
        </fieldset>
        ${(s.f || []).some(f => RASTER.includes(f)) ? `<div class="gx-bg"><span class="gx-bg__l">${esc(t('Hintergrund (Pixel-Formate)'))}</span>
          <label class="ge-chk"><input type="checkbox" data-sf="bgon" data-focuskey="f-bgon"${s.bg ? ' checked' : ''}> ${esc(t('Fläche'))}</label>
          <input type="color" class="gm-color" data-sf="bg" data-focuskey="f-bg" value="${esc(s.bg || '#ffffff')}"${s.bg ? '' : ' disabled'} aria-label="${esc(t('Hintergrundfarbe'))}"></div>
          <p class="ge-note">${esc(t('iPhone-Symbol, „maskable“ und JPEG brauchen eine Fläche – ohne Auswahl Weiß.'))}</p>` : ''}
        ${(s.f || []).includes('app') ? `<p class="ge-note">${esc(t('Der Kreis zeigt die Schutzzone für „maskable“ (80 %): Wichtiges gehört hinein, Android schneidet außen ab.'))}</p>` : ''}
        ${(s.f || []).includes('social') || (s.f || []).includes('socialjpg') ? (Math.abs(s.w / s.h - 1200 / 630) > 0.02 ? `<p class="ge-note gm-danger">${esc(t('Seitenverhältnis weicht von 1200 × 630 ab – der Rest wird mit dem Hintergrund gefüllt.'))}</p>` : '') : ''}
        <div class="gx-acts"><button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-act="one" data-focuskey="a-one">${ico('download-simple')} ${esc(t('Bereich exportieren'))}</button>
          <button type="button" class="ge-ib" data-act="del" data-focuskey="a-del" aria-label="${esc(t('Bereich löschen'))}" title="${esc(t('Bereich löschen'))}">${ico('trash')}</button></div>
        ${canIcon && (s.f || []).some(f => f === 'ico' || f === 'app') ? `<button type="button" class="adm-btn adm-btn--small" data-act="appicon" data-focuskey="a-appicon">${ico('app-window')} ${esc(t('Als Favicon/App-Symbol der Website übernehmen'))}</button>` : ''}
      </div>` : ''}
      <div class="ge-form"><button type="button" class="adm-btn adm-btn--small" data-act="zip" data-focuskey="a-zip"${list.length ? '' : ' disabled'}>${ico('file-zip')} ${esc(t('Alle Bereiche als ZIP'))}</button>
        <p class="ge-note">${esc(t('Pixel-Formate entstehen nur hier beim Herunterladen – die Grafik selbst bleibt Vektor.'))}</p></div>`;
    if (key) this.side.querySelector(`[data-focuskey="${CSS.escape(key)}"]`)?.focus();
  }

  bindSide() {
    const S = this.side;
    S.addEventListener('click', e => {
      const li = e.target.closest('[data-slice]');
      if (li) { this.cur = li.dataset.slice; this.refresh(); S.querySelector(`[data-slice="${CSS.escape(this.cur)}"]`)?.focus(); return; }
      const b = e.target.closest('button');
      if (!b) return;
      if (b.dataset.preset) { this.addPreset(b.dataset.preset); return; }
      if (b.dataset.act === 'del' && this.cur) { this.remove(this.cur); return; }
      if (b.dataset.act === 'one' && this.slice) { this.download([this.slice]); return; }
      if (b.dataset.act === 'zip') { this.download(this.slices, true); return; }
      if (b.dataset.act === 'appicon' && this.slice) this.takeAppIcon(this.slice);
    });
    S.addEventListener('keydown', e => {
      const li = e.target.closest('[data-slice]');
      if (!li) return;
      const all = [...S.querySelectorAll('[data-slice]')], i = all.indexOf(li);
      const d = e.key === 'ArrowDown' ? 1 : e.key === 'ArrowUp' ? -1 : 0;
      if (d) { e.preventDefault(); e.stopPropagation(); const n = all[(i + d + all.length) % all.length]; this.cur = n.dataset.slice; this.refresh(); S.querySelector(`[data-slice="${CSS.escape(this.cur)}"]`)?.focus(); }
      if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); e.stopPropagation(); this.remove(li.dataset.slice); }
    });
    S.addEventListener('change', e => {
      const x = e.target, s = this.slice;
      if (!s) return;
      const list = this.slices, q = list.find(z => z.id === s.id);
      let label = '';
      if (x.dataset.fmt) {
        const f = new Set(q.f || []);
        x.checked ? f.add(x.dataset.fmt) : f.delete(x.dataset.fmt);
        q.f = FORMATS.filter(z => f.has(z));
        if ((x.dataset.fmt === 'ico' || x.dataset.fmt === 'app') && x.checked && q.w !== q.h) { const m = Math.min(q.w, q.h); q.x += Math.round((q.w - m) / 2); q.y += Math.round((q.h - m) / 2); q.w = q.h = m; toast(t('Symbole sind quadratisch – der Bereich wurde angepasst.'), 'info'); }
        if (RASTER.includes(x.dataset.fmt) && x.checked && q.bg === undefined) q.bg = '#ffffff';
        label = t('Format: {name}', { name: FORMAT_LABEL[x.dataset.fmt]() });
      } else if (x.dataset.sf === 'n') { q.n = x.value.trim().slice(0, 60); label = t('Bereich umbenannt'); }
      else if (['x', 'y', 'w', 'h'].includes(x.dataset.sf)) {
        const v = Math.round(parseFloat(x.value));
        if (!Number.isFinite(v)) return;
        q[x.dataset.sf] = x.dataset.sf === 'w' || x.dataset.sf === 'h' ? Math.max(1, v) : v;
        if ((q.f || []).some(f => f === 'ico' || f === 'app')) { if (x.dataset.sf === 'w') q.h = q.w; if (x.dataset.sf === 'h') q.w = q.h; }
        label = t('Bereichsgröße');
      } else if (x.dataset.sf === 'bg') { q.bg = x.value; label = t('Hintergrund'); }
      else if (x.dataset.sf === 'bgon') { q.bg = x.checked ? (q.bg || '#ffffff') : ''; label = t('Hintergrund'); }
      else if (x.dataset.snap) { q.snap = { ...(q.snap || {}), [x.dataset.snap]: x.checked ? 1 : 0 }; label = t('Pixelraster {n} px', { n: x.dataset.snap }); }
      else return;
      setSlices(this.root, list);
      this.ed.commit(label);
    });
  }

  // ================================================================= Größen-Vorschau (unten)
  clearPreviews() { for (const u of Object.values(this.previews)) URL.revokeObjectURL(u); this.previews = {}; }

  async renderSizes() {
    const box = this.area?.querySelector('.gx-sizes');
    if (!box) return;
    const s = this.slice;
    const my = (this.seq = (this.seq || 0) + 1);
    if (!s) { box.innerHTML = `<p class="gd-note">${esc(t('Bereich wählen – hier erscheinen die Symbolgrößen mit Pixelraster und die Social-Vorschau.'))}</p>`; return; }
    const f = s.f || [];
    const items = [];
    if (f.includes('ico') || f.includes('app') || (!f.some(x => RASTER.includes(x)))) for (const n of ICON_SIZES) items.push({ key: 'i' + n, n, w: n, h: n, snap: !!(s.snap?.[n] ?? 1), label: n + ' px', icon: true });
    if (f.includes('app')) items.push({ key: 'a180', w: 180, h: 180, label: t('iPhone 180'), bg: true }, { key: 'm192', w: 192, h: 192, label: t('maskable (Schutzzone)'), bg: true, mask: true });
    if (f.includes('social') || f.includes('socialjpg')) items.push({ key: 'so', w: 1200, h: 630, label: t('Social 1200 × 630'), bg: true, social: true });
    box.innerHTML = `<div class="gx-sizes__row">${items.map(it => `<figure class="gx-size${it.social ? ' gx-size--social' : ''}" data-size="${it.key}">
        <div class="gx-size__img"><span class="gx-size__ph" aria-hidden="true"></span></div>
        <figcaption>${esc(it.label)}${it.icon ? ` <label class="ge-chk gx-snap"><input type="checkbox" data-snap="${it.n}"${it.snap ? ' checked' : ''}> ${esc(t('Pixelraster'))}</label>` : ''}</figcaption></figure>`).join('')}</div>`;
    box.querySelectorAll('[data-snap]').forEach(c => c.addEventListener('change', () => { const list = this.slices, q = list.find(z => z.id === s.id); q.snap = { ...(q.snap || {}), [c.dataset.snap]: c.checked ? 1 : 0 }; setSlices(this.root, list); this.ed.commit(t('Pixelraster {n} px', { n: c.dataset.snap })); }));
    const svg = await this.sourceSvg();
    this.clearPreviews();
    for (const it of items) {
      if (my !== this.seq) return;
      try {
        const bg = it.bg ? (s.bg || '#ffffff') : (s.bg || null);
        const blob = await renderPng(svg, s, it.w, it.h, { bg, snap: it.snap, host: this.ed.shadow });
        const url = URL.createObjectURL(blob);
        this.previews[it.key] = url;
        const cell = box.querySelector(`[data-size="${it.key}"] .gx-size__img`);
        if (!cell) continue;
        const alt = t('Vorschau {name}', { name: it.label });
        cell.innerHTML = it.icon
          ? `<img src="${url}" width="${it.w}" height="${it.h}" alt="${esc(alt)}" class="gx-px1"><img src="${url}" width="96" height="96" alt="" class="gx-pxz">`
          : `<img src="${url}" alt="${esc(alt)}" class="${it.social ? 'gx-social' : 'gx-app'}">${it.mask ? '<span class="gx-mask" aria-hidden="true"></span>' : ''}`;
      } catch (e) { console.error(e); }
    }
  }

  // ================================================================= Ausgabe
  /** Grafik wie gezeichnet, Texte mit Webschrift in Kurven (Pixel-Formate sehen die Schriften der Website nicht), Bewegung = Endbild */
  async sourceSvg(outlined = true) {
    const ed = this.ed;
    let svg = ed.getSvg();
    if (hasMotion(ed.root)) svg = ed.motion.stillSvg(svg, 'end');
    return outlined ? await ed.outlinedCopy(svg) : svg;
  }

  /** Dateien eines Bereichs: [{name, data: Uint8Array}] */
  async files(s, base) {
    const ed = this.ed, out = [], f = s.f || [];
    const vbox = svg => { const r = new DOMParser().parseFromString(svg, 'image/svg+xml').documentElement; r.setAttribute('viewBox', `${s.x} ${s.y} ${s.w} ${s.h}`); r.setAttribute('width', String(s.w)); r.setAttribute('height', String(s.h)); r.removeAttribute('data-ge-slices'); return new XMLSerializer().serializeToString(r); };
    const enc = str => new TextEncoder().encode(str);
    const work = await this.sourceSvg(false);
    if (f.includes('svg')) { const w = await ed.webSvg(); out.push({ name: base + '.svg', data: enc(optimizeSvg(vbox(w.svg), { decimals: ed.webPrefs.decimals })) }); }
    if (f.includes('svgd')) out.push({ name: base + '-desktop.svg', data: enc(desktopSvg(vbox(work))) });
    if (f.includes('pdf')) out.push({ name: base + '.pdf', data: await bytes(await pdfBlob(ed.boot.modules?.pdf, await ed.outlinedCopy(vbox(work), true), m => toast(m, 'info'))) });
    const raster = f.some(x => RASTER.includes(x)) ? await this.sourceSvg() : null;
    const png = async (w, hh, o = {}) => bytes(await renderPng(raster, s, w, hh, { host: ed.shadow, ...o }));
    if (f.includes('ico')) {
      const set = [];
      for (const n of [16, 32, 48]) { const b = await png(n, n, { bg: s.bg || null, snap: !!(s.snap?.[n] ?? 1) }); set.push([n, b]); out.push({ name: `${base}-favicon-${n}.png`, data: b }); }
      out.push({ name: base + '-favicon.ico', data: icoFile(set) });
    }
    if (f.includes('app')) {
      const bg = s.bg || '#ffffff';
      out.push({ name: `${base}-apple-touch-icon-180.png`, data: await png(180, 180, { bg }) });
      out.push({ name: `${base}-icon-192.png`, data: await png(192, 192, { bg: s.bg || null }) });
      out.push({ name: `${base}-icon-512.png`, data: await png(512, 512, { bg: s.bg || null }) });
      out.push({ name: `${base}-icon-maskable-512.png`, data: await png(512, 512, { bg }) });
    }
    if (f.includes('social')) out.push({ name: `${base}-social-1200x630.png`, data: await png(1200, 630, { bg: s.bg || '#ffffff' }) });
    if (f.includes('socialjpg')) out.push({ name: `${base}-social-1200x630.jpg`, data: await png(1200, 630, { bg: s.bg || '#ffffff', type: 'image/jpeg', quality: 0.9 }) });
    return out;
  }

  async download(list, zip = false) {
    const ed = this.ed;
    if (!list.length) return;
    ed.busy?.(true);
    try {
      const all = [];
      const fb = ed.fileBase();
      for (const s of list) {
        const base = list.length > 1 || zip ? fileSlug(s.n) + '/' + fb + '-' + fileSlug(s.n) : fb + '-' + fileSlug(s.n);
        all.push(...await this.files(s, base));
      }
      if (!all.length) { toast(t('Für diesen Bereich ist kein Format gewählt.'), 'info'); return; }
      let blob, name;
      if (all.length === 1 && !zip) {
        const x = all[0];
        name = x.name.split('/').pop();
        blob = new Blob([x.data], { type: { svg: 'image/svg+xml', pdf: 'application/pdf', png: 'image/png', jpg: 'image/jpeg', ico: 'image/x-icon' }[name.split('.').pop()] || 'application/octet-stream' });
      } else { blob = zipFile(all); name = fb + (list.length === 1 ? '-' + fileSlug(list[0].n) : '-export') + '.zip'; }
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob); a.download = name;
      document.body.append(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 4000);
      toast(t('{n} Dateien exportiert.', { n: all.length }));
    } catch (e) {
      console.error(e);
      toast(t('Der Export ist fehlgeschlagen.'), 'error');
    } finally { ed.busy?.(false); }
  }

  /** Als Favicon/App-Symbol der Website: 512-px-Bild in die Mediathek, App-Icons des Kerns daraus erzeugen (Core\AppIcons) */
  async takeAppIcon(s) {
    const ed = this.ed;
    const ok = await modal(ed.el, { title: t('Als Favicon/App-Symbol übernehmen'), ok: t('Übernehmen'),
      body: `<p>${esc(t('Favicon und App-Symbole der Website werden aus diesem Bereich neu erzeugt (Grundeinstellungen → App-Icon & PWA zeigt danach dieses Bild aus der Mediathek). Die bisherigen Symbole werden ersetzt.'))}</p>` });
    if (!ok) return;
    ed.busy?.(true);
    try {
      const blob = await renderPng(await this.sourceSvg(), s, 512, 512, { bg: s.bg || null, host: ed.shadow });
      const b = await bytes(blob);
      let bin = ''; for (let i = 0; i < b.length; i += 0x8000) bin += String.fromCharCode(...b.subarray(i, i + 0x8000));
      const res = await ed.api('POST', ed.boot.urls.appIcon, { png: btoa(bin), name: s.n || ed.fileBase() });
      if (res) toast(t('Favicon und App-Symbole der Website erneuert.'));
    } finally { ed.busy?.(false); }
  }
}
