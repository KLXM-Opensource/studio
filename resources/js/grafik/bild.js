/*
 * Grafik-Editor – Persona „Bild“ (Ausbaustufe 6): gemischt wie in Fireworks. Fotos der Mediathek als Ebenen (Verweis auf die ID,
 * nicht eingebettet), Ausschnitt nach Fokuspunkt, Korrektur (Helligkeit, Kontrast, Sättigung, Weichzeichnen), Verlauf über dem
 * Foto, Beschneiden mit einer Form, Deckkraft und Mischmodus – keine Pinsel/Retusche. Das Ergebnis ist immer ein Pixelbild der
 * Mediathek (Faktor 2, JPEG bzw. PNG mit Transparenz; die Mediathek erzeugt alle Größen in WebP/AVIF); die Arbeitsfassung bleibt
 * ein bearbeitbares SVG (Core\Graphics\Compose).
 * Reiter: Foto | Formate (Social-Media-Größen mit Anker-Regeln, formats.js) | Vorlage (Felder freigeben, nur mit Recht „Vorlagen
 * gestalten“, fields.js). Alle Aufrufe an das CMS über ed.cms (host.js). MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { toast } from '../_toast.js';
import { NS, num, docBounds } from './geom.js';
import { setProp, getProp, defs, uid, nameOf, typeLabel } from './doc.js';
import { h, modal } from './ui.js';
import { getPhoto, setPhoto, isPhoto, isRasterDoc, outType, renderImage, outScale } from './photo.js';
import { FORMATS, fmtLabel, boards, baseBox, getAnchor, setAnchor, anchorFor, addFormat, removeFormat } from './formats.js';
import { getField, setField, fieldList, kindsFor, KIND_LABEL, newField } from './fields.js';
import { getSlices } from './slices.js';

const BLENDS = ['normal', 'multiply', 'screen', 'overlay', 'soft-light', 'hard-light', 'darken', 'lighten', 'color-burn', 'color-dodge', 'luminosity'];
const BLEND_LABEL = { normal: () => t('Normal'), multiply: () => t('Multiplizieren'), screen: () => t('Negativ multiplizieren'), overlay: () => t('Überlagern'),
  'soft-light': () => t('Weiches Licht'), 'hard-light': () => t('Hartes Licht'), darken: () => t('Abdunkeln'), lighten: () => t('Aufhellen'),
  'color-burn': () => t('Farbig nachbelichten'), 'color-dodge': () => t('Farbig abwedeln'), luminosity: () => t('Luminanz') };
const H_LABEL = { l: () => t('links'), c: () => t('Mitte'), r: () => t('rechts'), s: () => t('Breite wächst mit') };
const V_LABEL = { t: () => t('oben'), m: () => t('Mitte'), b: () => t('unten'), s: () => t('Höhe wächst mit') };

/** Alt-Text-Vorschlag aus den Textebenen des Hauptformats (Lesereihenfolge: von oben, links nach rechts) */
export function altFromTexts(root) {
  const base = baseBox(root);
  const list = [...root.querySelectorAll('text')].filter(x => !x.closest('[data-ge-board],defs,clipPath,mask') && x.getAttribute('display') !== 'none' && !x.closest('[display=none]'))
    .map(el => ({ el, b: docBounds(el, root) })).filter(x => x.b && x.b.x < base.x + base.w && x.b.y < base.y + base.h);
  list.sort((a, b) => Math.abs(a.b.y - b.b.y) > base.h / 40 ? a.b.y - b.b.y : a.b.x - b.b.x);
  const parts = [...new Set(list.map(x => x.el.textContent.replace(/\s+/g, ' ').trim()).filter(Boolean))];
  const s = parts.join(' – ');
  return s.length > 240 ? s.slice(0, 237).replace(/\s+\S*$/, '') + ' …' : s;
}

export class BildPersona {
  constructor(ed) {
    this.ed = ed;
    this.mounted = false;
    this.tab = 'photo';
  }

  get persona() {
    return { id: 'bild', label: t('Bild'), icon: 'image', mount: (ed, area) => this.mount(area), unmount: () => this.unmount(), overlay: () => this.overlay() };
  }

  /** Rahmen und Namen der Formate auf der Fläche (wie Zeichenflächen in Export) – nur mit mehreren Formaten */
  overlay() {
    if (!this.mounted) return '';
    const list = boards(this.root);
    if (list.length < 2) return '';
    return list.map(b => {
      const a = this.ed.toOv({ x: b.x, y: b.y }), c = this.ed.toOv({ x: b.x + b.w, y: b.y + b.h });
      return `<rect class="gx-slice gx-slice--board" x="${a.x}" y="${a.y}" width="${c.x - a.x}" height="${c.y - a.y}"/><text class="gx-label" x="${a.x + 4}" y="${a.y - 5}">${esc(b.id === 'main' ? t('Hauptformat') + ` · ${num(b.w)} × ${num(b.h)}` : b.n)}</text>`;
    }).join('');
  }
  get root() { return this.ed.root; }
  get host() { return this.ed.cms; }
  get photo() { const s = this.ed.sel; return s.length >= 1 && isPhoto(s[s.length - 1]) ? s[s.length - 1] : (s.find(isPhoto) || null); }

  // ================================================================= Ein- und Aushängen
  mount(area) {
    const ed = this.ed;
    this.mounted = true;
    area.hidden = true;
    if (!this.side) {
      const tabs = [['photo', t('Foto')], ['formats', t('Formate')], ...(this.host.can('templates') || ed.doc.target === 'template' ? [['tpl', t('Vorlage')]] : [])];
      this.side = h(`<section class="ge-pg ge-pg--bild" aria-label="${esc(t('Bild'))}">
        <div class="ge-pg__tabs"><div class="ge-pg__list" role="tablist" aria-label="${esc(t('Bild'))}">${tabs.map(([id, l], i) => `<button type="button" role="tab" id="${ed.uid}-bt-${id}" aria-controls="${ed.uid}-bp" aria-selected="${i === 0}" tabindex="${i === 0 ? 0 : -1}" data-btab="${id}">${esc(l)}</button>`).join('')}</div></div>
        <div class="ge-pg__panel gb-panel" role="tabpanel" id="${ed.uid}-bp" aria-labelledby="${ed.uid}-bt-photo"></div>
      </section>`);
      const tb = [...this.side.querySelectorAll('[role=tab]')];
      const pick = (b, focus) => {
        for (const x of tb) { const on = x === b; x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1; }
        this.tab = b.dataset.btab;
        this.side.querySelector('[role=tabpanel]').setAttribute('aria-labelledby', b.id);
        if (focus) b.focus();
        this.render();
      };
      tb.forEach((b, i) => {
        b.addEventListener('click', () => pick(b));
        b.addEventListener('keydown', e => { const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0; if (d) { e.preventDefault(); e.stopPropagation(); pick(tb[(i + d + tb.length) % tb.length], true); } });
      });
      this.bind();
    }
    ed.$('.ge-side').prepend(this.side);
    this.side.hidden = false;
    this.offs = [ed.on('change', () => this.render()), ed.on('selection', () => this.render())];
    this.render();
    ed.hint(t('Bild: Foto aus der Mediathek einfügen, Ausschnitt und Korrektur rechts einstellen. Ergebnis ist ein Pixelbild der Mediathek – die Arbeitsfassung bleibt bearbeitbar.'));
  }

  unmount() {
    this.mounted = false;
    this.offs?.forEach(f => f());
    if (this.side) this.side.hidden = true;
    this.ed.hint();
  }

  // ================================================================= Bereich rechts
  render() {
    if (!this.mounted || !this.side) return;
    const P = this.side.querySelector('.gb-panel');
    const act = document.activeElement;
    if (P.contains(act) && act.type === 'range' && this.sliding) return;
    const key = P.contains(act) ? act.dataset.focuskey : null;
    P.innerHTML = this.tab === 'formats' ? this.formatsHtml() : this.tab === 'tpl' ? this.tplHtml() : this.photoHtml();
    if (key) P.querySelector(`[data-focuskey="${CSS.escape(key)}"]`)?.focus();
  }

  range(key, label, v, min, max, step, unit = '%') {
    return `<label class="ge-lbl">${esc(label)} <input type="range" class="ge-range" data-ph="${key}" data-focuskey="ph-${key}" min="${min}" max="${max}" step="${step}" value="${esc(String(v))}"><output>${esc(num(v, 1))}${unit}</output></label>`;
  }

  photoHtml() {
    const el = this.photo, d = el && getPhoto(el), info = d && this.ed.photos.info[d.m];
    const raster = isRasterDoc(this.root);
    let s = `<div class="ge-form"><button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-act="insert" data-focuskey="a-insert">${ico('image')} ${esc(t('Foto aus der Mediathek …'))}</button>`;
    if (!el) s += `<p class="ge-note">${esc(t('Ein Foto wählen, um Ausschnitt und Korrektur einzustellen. Text, Formen und Verläufe zeichnen Sie wie gewohnt dazu.'))}</p>`;
    s += '</div>';
    if (el && d) {
      const op = Math.round((parseFloat(getProp(el, 'opacity', false)) || 1) * 100);
      const blend = getProp(el, 'mix-blend-mode', false) || 'normal';
      s += `<div class="ge-form gb-photo">
        <div class="gb-info">${info ? `<img src="${esc(info.url)}" alt="" class="gb-thumb">` : ''}<span><strong>${esc(info?.name || '#' + d.m)}</strong><span class="ge-note">${info ? esc(`${info.w} × ${info.h} px`) : esc(t('Foto wird geladen …'))}</span></span></div>
        <button type="button" class="adm-btn adm-btn--small" data-act="replace" data-focuskey="a-replace">${ico('swap')} ${esc(t('Foto ersetzen …'))}</button>
        <h2 class="ge-h3">${esc(t('Ausschnitt'))}</h2>
        ${this.range('z', t('Zoom'), Math.round((d.z || 1) * 100), 100, 400, 5)}
        ${this.range('fx', t('Lage waagerecht'), d.fx ?? info?.focus?.x ?? 50, 0, 100, 1)}
        ${this.range('fy', t('Lage senkrecht'), d.fy ?? info?.focus?.y ?? 50, 0, 100, 1)}
        <button type="button" class="adm-btn adm-btn--small" data-act="focus" data-focuskey="a-focus"${d.fx == null && d.fy == null && (d.z || 1) === 1 ? ' disabled' : ''}>${ico('crosshair')} ${esc(t('Fokuspunkt der Mediathek'))}</button>
        <p class="ge-note">${esc(t('Das Foto füllt immer seinen Rahmen. Ohne eigene Lage gilt der Fokuspunkt aus der Mediathek – auch in anderen Formaten.'))}</p>
        <h2 class="ge-h3">${esc(t('Korrektur'))}</h2>
        ${this.range('b', t('Helligkeit'), d.b ?? 100, 50, 150, 1)}
        ${this.range('c', t('Kontrast'), d.c ?? 100, 50, 150, 1)}
        ${this.range('s', t('Sättigung'), d.s ?? 100, 0, 200, 1)}
        ${this.range('bl', t('Weichzeichnen'), d.bl || 0, 0, 40, 0.5, '')}
        <button type="button" class="adm-btn adm-btn--small" data-act="reset" data-focuskey="a-reset">${ico('arrow-counter-clockwise')} ${esc(t('Korrektur zurücksetzen'))}</button>
        <h2 class="ge-h3">${esc(t('Darstellung'))}</h2>
        ${this.range('op', t('Deckkraft'), op, 0, 100, 1)}
        <label class="ge-lbl">${esc(t('Mischmodus'))} <select class="ge-in" data-ph="blend" data-focuskey="ph-blend">${BLENDS.map(b => `<option value="${b}"${b === blend ? ' selected' : ''}>${esc(BLEND_LABEL[b]())}</option>`).join('')}</select></label>
        <div class="gb-acts"><button type="button" class="adm-btn adm-btn--small" data-act="gradient" data-focuskey="a-grad">${ico('gradient')} ${esc(t('Verlauf darüber'))}</button>
        <button type="button" class="adm-btn adm-btn--small" data-act="clip" data-focuskey="a-clip"${this.ed.sel.length === 2 ? '' : ' disabled'}>${ico('crop')} ${esc(t('Mit Form darüber beschneiden'))}</button></div>
        <p class="ge-note">${esc(t('Beschneiden: Foto und eine Form darüber auswählen (⇧-Klick) – die Form wird zum Rahmen.'))}</p>
      </div>`;
    }
    s += `<div class="ge-form"><h2 class="ge-h3">${esc(t('Ergebnis'))}</h2>
      <label class="ge-lbl">${esc(t('Ausgabe'))} <select class="ge-in" data-out data-focuskey="o-out">
        <option value=""${raster ? '' : ' selected'}${raster && this.root.querySelector('[data-ge-photo]') ? ' disabled' : ''}>${esc(t('Vektorgrafik (SVG)'))}</option>
        <option value="jpeg"${raster && outType(this.root) === 'jpeg' ? ' selected' : ''}>${esc(t('Pixelbild, Hintergrund weiß (JPEG)'))}</option>
        <option value="png"${raster && outType(this.root) === 'png' ? ' selected' : ''}>${esc(t('Pixelbild mit Transparenz (PNG)'))}</option></select></label>
      <p class="ge-note">${esc(raster ? t('Speichern erzeugt ein Bild in der Mediathek (doppelte Auflösung, alle Größen als WebP) – je Format eines. Die Arbeitsfassung mit Ebenen bleibt erhalten; „Bearbeiten“ in der Mediathek öffnet sie wieder.') : t('Mit einem Foto wird die Grafik zum Pixelbild – Fotos gibt es nur dort, Infografiken bleiben reine Vektoren.'))}</p></div>`;
    return s;
  }

  formatsHtml() {
    const root = this.root, list = boards(root), base = baseBox(root);
    const el = this.ed.sel.length === 1 ? this.ed.sel[0] : null;
    const inBoard = el?.closest?.('[data-ge-board]');
    let s = `<div class="ge-form"><h2 class="ge-h3">${esc(t('Formate dieses Bildes'))}</h2>
      <ul class="gb-boards">${list.map(b => `<li><strong>${esc(b.id === 'main' ? t('Hauptformat') : b.n)}</strong> <span class="ge-note">${num(b.w)} × ${num(b.h)}</span>
        ${b.id === 'main' ? '' : `<span class="gb-boards__acts"><button type="button" class="ge-ib" data-act="regen" data-board="${esc(b.id)}" data-focuskey="rg-${esc(b.id)}" title="${esc(t('Neu aus dem Hauptformat erzeugen'))}" aria-label="${esc(t('{name} neu aus dem Hauptformat erzeugen', { name: b.n }))}">${ico('arrows-clockwise')}</button><button type="button" class="ge-ib" data-act="unboard" data-board="${esc(b.id)}" data-focuskey="rm-${esc(b.id)}" title="${esc(t('Format entfernen'))}" aria-label="${esc(t('{name} entfernen', { name: b.n }))}">${ico('trash')}</button></span>`}</li>`).join('')}</ul>
      <fieldset class="gb-fmts"><legend class="ge-h3">${esc(t('Formate hinzufügen'))}</legend>
      ${FORMATS.filter(f => !(Math.round(base.w) === f.w && Math.round(base.h) === f.h)).map(f => `<label class="ge-chk"><input type="checkbox" data-addfmt="${f.id}" data-focuskey="af-${f.id}"${list.some(b => b.fmt === f.id) ? ' disabled' : ''}> ${esc(f.label())}</label>`).join('')}
      <div class="ge-row4 gb-free"><label>${esc(t('B'))} <input type="number" class="ge-in ge-in--s" min="16" max="4000" step="1" data-free="w" data-focuskey="fr-w" aria-label="${esc(t('Breite (frei)'))}" placeholder="1200"></label><label>${esc(t('H'))} <input type="number" class="ge-in ge-in--s" min="16" max="4000" step="1" data-free="h" data-focuskey="fr-h" aria-label="${esc(t('Höhe (frei)'))}" placeholder="900"></label></div>
      </fieldset>
      <button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-act="addfmts" data-focuskey="a-addfmts">${ico('plus')} ${esc(t('Formate anlegen'))}</button>
      <p class="ge-note">${esc(t('Jedes Format wird eine eigene Zeichenfläche rechts daneben – nach den Anker-Regeln der Ebenen gelegt und danach einzeln nachzubessern. Speichern erzeugt je Format ein eigenes Bild in der Mediathek.'))}</p></div>`;
    s += `<div class="ge-form"><h2 class="ge-h3">${esc(t('Anker der Ebene'))}</h2>`;
    if (!el || inBoard) {
      s += `<p class="ge-note">${esc(inBoard ? t('Anker gelten für Ebenen des Hauptformats.') : t('Eine Ebene des Hauptformats auswählen: wo sie in anderen Formaten liegt und ob sie mitwächst.'))}</p></div>`;
      return s;
    }
    const own = getAnchor(el), a = own || anchorFor(el, root, base);
    const radios = (name, map, cur) => `<div class="ge-seg gb-seg" role="radiogroup" aria-label="${esc(name === 'h' ? t('Waagerecht') : t('Senkrecht'))}">${Object.keys(map).map(k => `<label class="gb-opt"><input type="radio" name="gb-${name}-${this.ed.uid}" data-anchor="${name}" value="${k}" data-focuskey="an-${name}-${k}"${k === cur ? ' checked' : ''}> ${esc(map[k]())}</label>`).join('')}</div>`;
    s += `<p class="ge-note">${esc(nameOf(el) || typeLabel(el))}${own ? '' : ' · ' + esc(t('Vorschlag aus der Lage'))}</p>
      <span class="ge-lbl">${esc(t('Waagerecht'))}</span>${radios('h', H_LABEL, a.h)}
      <span class="ge-lbl">${esc(t('Senkrecht'))}</span>${radios('v', V_LABEL, a.v)}
      <label class="ge-chk"><input type="checkbox" data-anchor="k" data-focuskey="an-k"${a.k === 'f' ? ' checked' : ''}> ${esc(t('Feste Größe (wächst nicht mit dem Format)'))}</label>
      ${own ? `<button type="button" class="adm-btn adm-btn--small" data-act="noanchor" data-focuskey="a-noanchor">${esc(t('Vorschlag verwenden'))}</button>` : ''}</div>`;
    return s;
  }

  tplHtml() {
    const ed = this.ed, root = this.root, can = this.host.can('templates');
    const el = ed.sel.length === 1 ? ed.sel[0] : null;
    const f = el && getField(el);
    const kinds = kindsFor(el);
    let s = `<div class="ge-form"><h2 class="ge-h3">${esc(t('Feld für die Redaktion'))}</h2>`;
    if (!el) s += `<p class="ge-note">${esc(t('Eine Ebene auswählen und als Feld freigeben: Bild, Text, Farbe oder Ein/Aus. Alles andere bleibt für die Redaktion gesperrt.'))}</p>`;
    else {
      s += `<label class="ge-lbl">${esc(t('Freigeben als'))} <select class="ge-in" data-fld="t" data-focuskey="fl-t"${can ? '' : ' disabled'}><option value="">${esc(t('– gesperrt –'))}</option>${kinds.map(k => `<option value="${k}"${f?.t === k ? ' selected' : ''}>${esc(KIND_LABEL[k]())}</option>`).join('')}</select></label>`;
      if (f) {
        s += `<label class="ge-lbl">${esc(t('Bezeichnung'))} <input type="text" class="ge-in" maxlength="80" data-fld="n" data-focuskey="fl-n" value="${esc(f.n || '')}"></label>`;
        if (f.t === 'text') s += `<label class="ge-chk"><input type="checkbox" data-fld="ml" data-focuskey="fl-ml"${f.ml ? ' checked' : ''}> ${esc(t('Mehrzeilig'))}</label>
          <div class="ge-row4"><label>${esc(t('Höchstens Zeichen'))} <input type="number" class="ge-in ge-in--s" min="1" max="2000" data-fld="max" data-focuskey="fl-max" value="${esc(String(f.max || 60))}"></label>
          <label>${esc(t('Zeilen'))} <input type="number" class="ge-in ge-in--s" min="1" max="20" data-fld="lines" data-focuskey="fl-lines" value="${esc(String(f.lines || 1))}"></label>
          <label>${esc(t('Kleinste Schrift %'))} <input type="number" class="ge-in ge-in--s" min="20" max="100" data-fld="min" data-focuskey="fl-min" value="${esc(String(f.min || 60))}"></label></div>
          <p class="ge-note">${esc(t('Längere Texte lassen die Schrift schrumpfen, höchstens bis zur kleinsten Größe – dann sieht die Redaktion einen Hinweis.'))}</p>`;
        if (f.t === 'toggle') s += `<label class="ge-chk"><input type="checkbox" data-fld="on" data-focuskey="fl-on"${f.on !== false ? ' checked' : ''}> ${esc(t('Standardmäßig eingeschaltet'))}</label>`;
        if (f.t === 'color') s += `<p class="ge-note">${esc(t('Die Redaktion wählt nur aus den Design-Farben der Website.'))}</p>`;
      }
    }
    s += '</div>';
    const list = fieldList(root);
    s += `<div class="ge-form"><h2 class="ge-h3">${esc(t('Felder dieser Vorlage'))}</h2>${list.length ? `<ul class="gb-fields">${list.map(x => `<li><button type="button" class="gb-field" data-pick="${esc(x.f.k)}" data-focuskey="pk-${esc(x.f.k)}">${esc(x.f.n)} <span class="ge-note">${esc(KIND_LABEL[x.f.t]())}${x.els.length > 1 ? ' · ' + esc(t('{n}×', { n: x.els.length })) : ''}</span></button></li>`).join('')}</ul>` : `<p class="ge-note">${esc(t('Noch keine Felder.'))}</p>`}
      ${can ? `<button type="button" class="adm-btn adm-btn--small adm-btn--primary" data-act="savetpl" data-focuskey="a-savetpl"${list.length ? '' : ' disabled'}>${ico('floppy-disk')} ${esc(ed.doc.target === 'template' ? t('Vorlage speichern') : t('Als Vorlage speichern …'))}</button>` : ''}
      <p class="ge-note">${esc(t('Die Redaktion füllt Vorlagen in der Mediathek aus (Neu → Aus Vorlage erstellen) – ohne Editor, mit Vorschau und den gewünschten Formaten.'))}</p></div>`;
    return s;
  }

  // ================================================================= Bedienung
  bind() {
    const S = this.side;
    S.addEventListener('click', e => {
      const b = e.target.closest('button');
      if (!b || b.disabled) return;
      const a = b.dataset.act;
      if (a === 'insert') this.insert();
      else if (a === 'replace') this.replace();
      else if (a === 'focus') this.patch({ fx: null, fy: null, z: 1 }, t('Ausschnitt: Fokuspunkt der Mediathek'));
      else if (a === 'reset') this.patch({ b: 100, c: 100, s: 100, bl: 0 }, t('Korrektur zurückgesetzt'));
      else if (a === 'gradient') this.gradient();
      else if (a === 'clip') this.ed.clip('clip');
      else if (a === 'addfmts') this.addFormats();
      else if (a === 'regen') { addFormat(this.root, getSlices(this.root).find(x => x.id === b.dataset.board), b.dataset.board); this.ed.photos.refresh(); this.ed.commit(t('Format neu aus dem Hauptformat')); }
      else if (a === 'unboard') { removeFormat(this.root, b.dataset.board); this.ed.fitted = false; this.ed.commit(t('Format entfernt')); this.ed.fit(); }
      else if (a === 'noanchor') { for (const el of this.ed.sel) setAnchor(el, null); this.ed.commit(t('Anker: Vorschlag')); }
      else if (a === 'savetpl') this.saveTemplate();
      else if (b.dataset.pick) { const it = fieldList(this.root).find(x => x.f.k === b.dataset.pick); if (it) this.ed.select(it.els[0]); }
    });
    S.addEventListener('input', e => {
      const x = e.target, k = x.dataset.ph;
      if (!k || x.type !== 'range') return;
      this.sliding = true;
      const v = +x.value;
      x.nextElementSibling && (x.nextElementSibling.textContent = num(v, 1) + (k === 'bl' ? '' : '%'));
      const el = this.photo;
      if (!el) return;
      if (k === 'op') { setProp(el, 'opacity', v >= 100 ? null : String(v / 100)); this.ed.draw(); return; }
      this.ed.photos.live(el, { [k]: k === 'z' ? v / 100 : v });
    });
    S.addEventListener('change', e => {
      const x = e.target;
      this.sliding = false;
      const el = this.photo;
      if (x.dataset.ph && el) {
        const k = x.dataset.ph;
        if (k === 'blend') { setProp(el, 'mix-blend-mode', x.value === 'normal' ? null : x.value); this.ed.commit(t('Mischmodus')); return; }
        if (k === 'op') { this.ed.commit(t('Deckkraft')); return; }
        this.ed.commit({ z: t('Ausschnitt: Zoom'), fx: t('Ausschnitt verschoben'), fy: t('Ausschnitt verschoben'), b: t('Helligkeit'), c: t('Kontrast'), s: t('Sättigung'), bl: t('Weichzeichnen') }[k] || t('Foto geändert'));
        return;
      }
      if (x.hasAttribute('data-out')) {
        if (x.value) this.root.setAttribute('data-ge-out', x.value); else this.root.removeAttribute('data-ge-out');
        this.ed.commit(t('Ausgabe: {name}', { name: x.selectedOptions[0]?.textContent || '' }));
        return;
      }
      if (x.dataset.anchor) {
        const base = baseBox(this.root);
        for (const s of this.ed.sel) {
          const a = { ...anchorFor(s, this.root, base) };
          if (x.dataset.anchor === 'k') a.k = x.checked ? 'f' : 'k'; else a[x.dataset.anchor] = x.value;
          setAnchor(s, a);
        }
        this.ed.commit(t('Anker geändert'));
        return;
      }
      if (x.dataset.fld) this.fieldChange(x);
    });
  }

  /** Foto-Daten ändern (ein Schritt im Protokoll) */
  patch(p, label) {
    const el = this.photo;
    if (!el) return;
    setPhoto(el, { ...getPhoto(el), ...p });
    this.ed.photos.paint(el);
    this.ed.commit(label);
  }

  /** Foto einfügen: erstes Foto füllt das Hauptformat (Hintergrund), weitere halb so breit in der Mitte */
  async insert() {
    const m = await this.host.media.pick();
    if (!m) return;
    if (m.svg || (m.kind && m.kind !== 'image')) { toast(t('Bitte ein Foto wählen (JPG, PNG, WebP) – SVG-Grafiken fügen Sie in der Persona Vektor ein.'), 'warn'); return; }
    const ed = this.ed;
    await ed.photos.need([+m.id]);
    const info = ed.photos.info[m.id];
    if (!info) { toast(t('Das Foto konnte nicht geladen werden.'), 'error'); return; }
    const base = baseBox(this.root);
    const first = !this.root.querySelector('[data-ge-photo]');
    let w = base.w, hh = base.h, x = base.x, y = base.y;
    if (!first) { w = base.w / 2; hh = w * info.h / info.w; if (hh > base.h * 0.8) { hh = base.h * 0.8; w = hh * info.w / info.h; } x = base.x + (base.w - w) / 2; y = base.y + (base.h - hh) / 2; }
    const im = document.createElementNS(NS, 'image');
    for (const [k, v] of Object.entries({ x: num(x, 2), y: num(y, 2), width: num(w, 2), height: num(hh, 2), preserveAspectRatio: 'xMidYMid slice', 'data-name': info.name || t('Foto') })) im.setAttribute(k, v);
    setPhoto(im, { m: +m.id });
    // erstes Foto unter alles (Hintergrund), weitere über die Auswahl bzw. obenauf
    const parent = ed.scope || this.root;
    if (first) { const firstDrawn = [...parent.children].find(c => !['defs', 'title', 'desc', 'style', 'metadata'].includes(c.localName)); parent.insertBefore(im, firstDrawn || null); }
    else parent.append(im);
    if (!this.root.hasAttribute('data-ge-out')) this.root.setAttribute('data-ge-out', 'jpeg');
    ed.photos.paint(im);
    ed.select(im);
    ed.commit(t('Foto eingefügt'));
  }

  async replace() {
    const el = this.photo;
    if (!el) return;
    const m = await this.host.media.pick();
    if (!m) return;
    await this.ed.photos.need([+m.id]);
    if (!this.ed.photos.info[m.id]) { toast(t('Das Foto konnte nicht geladen werden.'), 'error'); return; }
    this.patch({ m: +m.id, fx: null, fy: null, z: 1 }, t('Foto ersetzt'));
  }

  /** Verlauf über das Foto: Rechteck im Rahmen des Fotos, unten dunkel → oben durchsichtig (für lesbaren Text) */
  gradient() {
    const el = this.photo;
    if (!el) return;
    const ed = this.ed;
    const g = document.createElementNS(NS, 'linearGradient');
    g.id = uid(this.root, 'verlauf-');
    for (const [k, v] of Object.entries({ x1: '0', y1: '0', x2: '0', y2: '1' })) g.setAttribute(k, v);
    g.innerHTML = '';
    for (const [o, op] of [['0', '0'], ['1', '0.65']]) { const s = document.createElementNS(NS, 'stop'); s.setAttribute('offset', o); s.setAttribute('stop-color', '#000000'); s.setAttribute('stop-opacity', op); g.append(s); }
    defs(this.root).append(g);
    const r = document.createElementNS(NS, 'rect');
    for (const a of ['x', 'y', 'width', 'height', 'transform']) if (el.hasAttribute(a)) r.setAttribute(a, el.getAttribute(a));
    r.setAttribute('fill', `url(#${g.id})`);
    r.setAttribute('data-name', t('Verlauf'));
    el.after(r);
    ed.select(r);
    ed.commit(t('Verlauf über dem Foto'));
  }

  addFormats() {
    const S = this.side, ed = this.ed;
    const picked = [...S.querySelectorAll('[data-addfmt]:checked')].map(x => FORMATS.find(f => f.id === x.dataset.addfmt)).filter(Boolean);
    const fw = +S.querySelector('[data-free=w]')?.value, fh = +S.querySelector('[data-free=h]')?.value;
    if (fw >= 16 && fh >= 16) picked.push({ id: 'free', w: Math.min(4000, Math.round(fw)), h: Math.min(4000, Math.round(fh)) });
    if (!picked.length) { toast(t('Bitte mindestens ein Format wählen.'), 'info'); return; }
    for (const f of picked) addFormat(this.root, f);
    ed.photos.refresh();
    ed.fitted = false;
    ed.commit(picked.length === 1 ? t('Format „{name}“ angelegt', { name: fmtLabel(picked[0]) }) : t('{n} Formate angelegt', { n: picked.length }));
    ed.fit();
  }

  fieldChange(x) {
    const el = this.ed.sel[0];
    if (!el) return;
    const k = x.dataset.fld;
    let f = getField(el);
    if (k === 't') {
      if (!x.value) { setField(el, null); this.ed.commit(t('Feld gesperrt')); return; }
      f = { ...newField(el, x.value, this.root), ...(f && f.t === x.value ? f : {}) };
      setField(el, f);
      this.ed.commit(t('Als Feld freigegeben: {name}', { name: f.n }));
      return;
    }
    if (!f) return;
    if (k === 'n') f.n = x.value.trim().slice(0, 80) || f.n;
    else if (k === 'ml' || k === 'on') f[k] = x.checked;
    else f[k] = Math.round(+x.value) || f[k];
    // gleicher Schlüssel in Kopien (Formate): überall dieselbe Angabe
    for (const it of fieldList(this.root).find(z => z.f.k === f.k)?.els || [el]) setField(it, { ...f, w: getField(it)?.w ?? f.w });
    this.ed.commit(t('Feld geändert'));
  }

  // ================================================================= Speichern
  /** Zeichenflächen als Pixelbilder rendern (Faktor 2) */
  async renderBoards(list) {
    const ed = this.ed, svg = ed.getSvg(), type = outType(this.root);
    const out = [];
    for (const b of list) {
      const scale = outScale(b, ed.boot.compose?.maxPx || 4000);
      out.push({ board: b.id, blob: await renderImage(svg, b, scale, { info: ed.photos.info, boot: ed.boot, host: ed.el }, { type }), w: Math.round(b.w * scale), h: Math.round(b.h * scale), b });
    }
    return out;
  }

  /** Bild speichern (Persona Bild): je Format ein Bild in der Mediathek, Arbeitsfassung dazu */
  async save(asNew = false) {
    const ed = this.ed, d = ed.doc;
    if (d.target === 'template') return this.saveTemplate();
    const list = boards(this.root);
    const first = asNew || d.target !== 'compose' || !d.id;
    let meta = { name: d.name, title: d.title, alt: d.alt || '', decorative: !!d.decorative };
    if (first) {
      const sug = altFromTexts(this.root);
      const r = await modal(ed.el, {
        title: asNew ? t('Speichern unter') : t('Bild in der Mediathek speichern'), ok: t('Speichern'),
        body: `<label class="f">${esc(t('Dateiname'))}<input name="name" maxlength="120" value="${esc(d.name || ed.fileBase())}" required></label>
          <label class="f">${esc(t('Titel'))}<input name="title" maxlength="180" value="${esc(d.title || '')}"></label>
          <label class="f">${esc(t('Alt-Text (Bildbeschreibung)'))}<textarea name="alt" rows="2" maxlength="250" data-alt>${esc(d.alt || '')}</textarea><span class="f-help">${esc(t('Was zeigt das Bild? Text im Bild gehört dazu – Screenreader lesen ihn sonst nicht.'))}</span></label>
          ${sug ? `<button type="button" class="adm-btn adm-btn--small" data-altsug>${ico('sparkle')} ${esc(t('Vorschlag aus den Textebenen'))}</button>` : ''}
          <label class="f-check"><input type="checkbox" name="decorative" data-deco> ${esc(t('Rein dekorativ (kein Alt-Text nötig)'))}</label>
          <fieldset class="ge-webopt"><legend>${esc(t('Bilder je Format'))}</legend>${list.map(b => `<label class="f-check"><input type="checkbox" name="out-${esc(b.id)}" checked> ${esc(b.id === 'main' ? t('Hauptformat') : b.n)} (${num(b.w)} × ${num(b.h)})</label>`).join('')}
          <p class="f-help">${esc(t('Jedes Format wird ein eigenes Bild in der Mediathek (doppelte Auflösung, die Mediathek erzeugt alle Größen).'))}</p></fieldset>`,
        onOpen: dlg => {
          const deco = dlg.querySelector('[data-deco]'), alt = dlg.querySelector('[data-alt]');
          const s = () => { alt.required = !deco.checked; alt.disabled = deco.checked; };
          deco.addEventListener('change', s); s();
          dlg.querySelector('[data-altsug]')?.addEventListener('click', () => { alt.value = sug; alt.focus(); });
        },
      });
      if (!r) return null;
      meta = { name: r.name, title: r.title, alt: r.decorative ? '' : (r.alt || ''), decorative: !!r.decorative };
      this.chosen = list.filter(b => r['out-' + b.id]).map(b => b.id);
      if (!this.chosen.length) { toast(t('Bitte mindestens ein Format wählen.'), 'info'); return null; }
    }
    const use = first ? list.filter(b => this.chosen.includes(b.id)) : list;
    const btn = ed.el.querySelector('[data-act=save]');
    btn.disabled = true;
    ed.busy?.(true);
    try {
      const imgs = await this.renderBoards(use);
      const base = (meta.name || ed.fileBase()).trim();
      const outputs = [];
      for (const x of imgs) outputs.push({ board: x.board, upload: await this.host.upload(x.blob, base), name: x.board === 'main' ? base : `${base}-${Math.round(x.b.w)}x${Math.round(x.b.h)}` });
      const res = await this.host.saveImage({ svg: ed.getSvg(), id: first ? null : d.id, ...meta, outputs, tpl: d.tpl || null });
      Object.assign(d, { target: 'compose', kind: 'photo', id: res.id, ref: 'graphic:' + res.id, name: meta.name, title: meta.title, alt: meta.alt, decorative: meta.decorative,
        outputs: Object.fromEntries(Object.entries(res.outputs).map(([k, v]) => [k, v.id])), versions: res.versions || [] });
      d.mainMedia = res.outputs.main?.id || Object.values(res.outputs)[0]?.id;
      ed.savedAt = ed.hi;
      ed.changed(false);
      const n = Object.keys(res.outputs).length;
      toast(n > 1 ? t('{n} Bilder in der Mediathek gespeichert', { n }) : t('Bild in der Mediathek gespeichert') + (res.errors?.length ? ' · ' + res.errors.join(' ') : ''));
      const item = res.outputs.main || Object.values(res.outputs)[0];
      const result = { target: 'media', item: { id: item.id, url: item.url, name: item.display }, svg: ed.getSvg(), format: ed.format, compose: res.id };
      ed.opts.onSave?.(result);
      ed.emit('save', result);
      return result;
    } catch (e) {
      console.error(e);
      toast(e.message || t('Speichern fehlgeschlagen.'), 'error');
      return null;
    } finally { btn.disabled = false; ed.busy?.(false); }
  }

  /** Als Vorlage speichern (Recht „Vorlagen gestalten“): neu (Name, Website/alle Websites) bzw. vorhandene ersetzen */
  async saveTemplate() {
    const ed = this.ed, d = ed.doc;
    if (!this.host.can('templates')) { toast(t('Für Vorlagen fehlt Ihrer Rolle die Berechtigung.'), 'error'); return null; }
    if (!fieldList(this.root).length) { toast(t('Die Vorlage hat noch keine Felder – geben Sie mindestens eine Ebene als Feld frei.'), 'warn'); return null; }
    let body = { svg: ed.getSvg(), name: d.name, ref: d.template || null, scope: d.templateScope || 'site' };
    if (!d.template) {
      const r = await modal(ed.el, { title: t('Als Vorlage speichern'), ok: t('Speichern'), body: `<label class="f">${esc(t('Name der Vorlage'))}<input name="name" maxlength="120" required value="${esc(d.name || '')}"></label>
        <fieldset class="ge-webopt"><legend>${esc(t('Verfügbar für'))}</legend><label class="f-check"><input type="radio" name="scope" value="site" checked> ${esc(t('Diese Website'))}</label><label class="f-check"><input type="radio" name="scope" value="network"> ${esc(t('Alle Websites der Installation'))}</label></fieldset>` });
      if (!r) return null;
      body = { ...body, name: r.name, scope: r.scope };
    }
    try {
      const res = await this.host.templates.save(body);
      Object.assign(d, { target: 'template', template: res.item.ref, templateScope: res.item.scope, name: res.item.name });
      ed.savedAt = ed.hi; ed.changed(false);
      toast(t('Vorlage „{name}“ gespeichert', { name: res.item.name }));
      ed.opts.onSave?.({ target: 'template', item: res.item });
      ed.emit('save', { target: 'template', item: res.item });
      return res;
    } catch (e) { toast(e.message, 'error'); return null; }
  }
}
