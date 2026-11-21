/*
 * Grafik-Editor – Persona „Daten“ (Ausbaustufe 5): Eigenschaften an Werte aus Datentabellen und an Zustände der Website binden.
 *   Bereich rechts: Bindung (gewähltes Objekt: Text, Breite/Höhe, Drehung, Füllung/Kontur, Deckkraft, Sichtbarkeit) | Werte (Datentabellen,
 *   Zustände). Leiste unter der Zeichenfläche: Vorschau mit Beispielwerten – Regler je Wert, Uhrzeit-Regler, Wochentag, Datum, Anzeige
 *   „Tag/Nacht“ und „geöffnet/geschlossen“ wie auf der Website.
 * Vorschau: eine Kopie der Grafik mit eingesetzten Werten liegt über dem Dokument (das Dokument bleibt die gezeichnete Fassung – die
 * Rahmen der Auswahl zeigen die gezeichnete Form = Wert „bis“). Jede Änderung der Bindungen ist ein Schritt im Protokoll. MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { toast } from '../_toast.js';
import { num } from './geom.js';
import { nameOf, typeLabel, uid, getProp } from './doc.js';
import { h, modal } from './ui.js';
import { hex } from './color.js';
import {
  PROPS, STATES, AGGS, OPS, FORMATS, PROP_LABEL, STATE_LABEL, AGG_LABEL, STATE_RANGE, getData, setData, getBinds, setBinds, bound, hasData,
  slug, valueLabel, statesAt, applyAll, anchorOf, format,
} from './bind.js';

const OP_LABEL = { '>': '>', '>=': '≥', '<': '<', '<=': '≤', '=': '=', '!=': '≠', between: () => t('zwischen') };
const FMT_LABEL = { num: () => t('Zahl'), pct: () => t('Prozent'), cur: () => t('Betrag (€)'), time: () => t('Uhrzeit'), raw: () => t('unverändert') };
const DAYS = () => [t('Montag'), t('Dienstag'), t('Mittwoch'), t('Donnerstag'), t('Freitag'), t('Samstag'), t('Sonntag')];
const hm = v => Math.floor(v) + ':' + String(Math.round((v % 1) * 60) % 60).padStart(2, '0');

export class DataPersona {
  constructor(ed) {
    this.ed = ed;
    this.info = ed.boot.data || { tables: [], hours: [], sun: [7, 19], states: {} };
    this.mounted = false;
    this.preview = true;
    this.samples = {};            // Beispielwerte der Vorschau (nicht im Dokument)
    this.live = {};               // aktuelle Werte der Datentabellen (Server)
    const now = new Date();
    const st = this.info.states || {};
    this.when = { hour: st['@uhrzeit'] ?? now.getHours() + now.getMinutes() / 60, dow: st['@wochentag'] || ((now.getDay() + 6) % 7) + 1, date: now.toISOString().slice(0, 10) };
    this.style = document.createElement('style');
    this.style.setAttribute('data-ge-data-preview', '');
    this.style.textContent = ':host>svg:not(.ge-data-view){opacity:0}:host>svg.ge-data-view{position:absolute;inset:0;pointer-events:none}';
    // Anker und Drehpunkte vor jedem Protokollschritt/Speichern aus der Geometrie (wie Motion: Drehpunkt)
    const orig = ed.getSvg.bind(ed);
    ed.getSvg = () => { this.syncAnchors(); return orig(); };
  }

  get persona() {
    return { id: 'data', label: t('Daten'), icon: 'database', mount: (ed, area) => this.mount(area), unmount: () => this.unmount() };
  }
  get root() { return this.ed.root; }
  get data() { return getData(this.root); }

  /** Anker/Drehpunkt gebundener Elemente nachziehen (kleine Abweichungen bleiben stehen – sonst änderte schon das Öffnen die Datei) */
  syncAnchors() {
    const root = this.root;
    if (!root || !root.querySelector('[data-ge-bind]')) return;
    const vb = this.ed.vb, tol = Math.max(1, Math.max(vb.w, vb.h) / 500);
    for (const el of bound(root)) {
      if (!el.isConnected) continue;
      const list = getBinds(el);
      let ch = false;
      for (const b of list) {
        if (!['w', 'h', 'r'].includes(b.p)) continue;
        const o = anchorOf(el, b);
        if (!b.o || Math.abs(b.o[0] - o[0]) > tol || Math.abs(b.o[1] - o[1]) > tol) { b.o = o; ch = true; }
      }
      if (ch) setBinds(el, list);
    }
  }

  // ================================================================= Ein- und Aushängen
  mount(area) {
    const ed = this.ed;
    this.mounted = true;
    this.area = area;
    area.classList.add('gd-area');
    area.innerHTML = this.barHtml();
    this.bindBar();
    if (!this.side) {
      this.side = h(`<section class="ge-pg ge-pg--data" aria-label="${esc(t('Daten'))}">
        <div class="ge-pg__tabs"><div class="ge-pg__list" role="tablist">
          <button type="button" role="tab" id="${ed.uid}-dt-bind" aria-controls="${ed.uid}-dp-bind" aria-selected="true" tabindex="0">${esc(t('Bindung'))}</button>
          <button type="button" role="tab" id="${ed.uid}-dt-vals" aria-controls="${ed.uid}-dp-vals" aria-selected="false" tabindex="-1">${esc(t('Werte'))}</button>
        </div></div>
        <div class="ge-pg__panel gd-panel" role="tabpanel" id="${ed.uid}-dp-bind" aria-labelledby="${ed.uid}-dt-bind"></div>
        <div class="ge-pg__panel gd-panel" role="tabpanel" id="${ed.uid}-dp-vals" aria-labelledby="${ed.uid}-dt-vals" hidden></div>
      </section>`);
      const tabs = [...this.side.querySelectorAll('[role=tab]')];
      this.pickTab = (b, focus) => { for (const x of tabs) { const on = x === b; x.setAttribute('aria-selected', String(on)); x.tabIndex = on ? 0 : -1; this.side.querySelector('#' + x.getAttribute('aria-controls')).hidden = !on; } if (focus) b.focus(); this.renderSide(); };
      tabs.forEach((b, i) => {
        b.addEventListener('click', () => this.pickTab(b));
        b.addEventListener('keydown', e => { const d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0; if (d) { e.preventDefault(); this.pickTab(tabs[(i + d + tabs.length) % tabs.length], true); } });
      });
      this.bindSide();
    }
    ed.$('.ge-side').prepend(this.side);
    this.side.hidden = false;
    ed.shadow.append(this.style);
    this.offs = [ed.on('change', () => this.refresh()), ed.on('selection', () => this.refresh())];
    // Während des Ziehens auf der Fläche das Dokument zeigen (die Vorschau zöge sonst nicht mit)
    this.down = () => { if (this.view) { this.style.disabled = true; this.view.remove(); this.view = null; } };
    this.up = () => { if (this.mounted) requestAnimationFrame(() => { this.style.disabled = false; this.pose(); }); };
    ed.stage.addEventListener('pointerdown', this.down, true);
    window.addEventListener('pointerup', this.up, true);
    this.fetchLive();
    this.refresh();
    requestAnimationFrame(() => ed.fit());
    ed.hint(t('Daten: Objekt wählen, im Bereich „Bindung“ eine Eigenschaft an einen Wert binden. Unten Beispielwerte und Uhrzeit ändern – die Fläche zeigt das Ergebnis wie auf der Website.'));
  }

  unmount() {
    const ed = this.ed;
    this.mounted = false;
    this.offs?.forEach(f => f());
    ed.stage.removeEventListener('pointerdown', this.down, true);
    window.removeEventListener('pointerup', this.up, true);
    this.view?.remove(); this.view = null;
    this.style.remove();
    if (this.side) this.side.hidden = true;
    this.area?.classList.remove('gd-area');
    requestAnimationFrame(() => { ed.fit(); ed.hint(); });
    ed.draw();
  }

  async fetchLive() {
    const defs = this.data.v || {};
    if (!Object.keys(defs).length || !this.ed.boot.urls.dataValues) return;
    const res = await this.ed.api('POST', this.ed.boot.urls.dataValues, { defs });
    if (!res) return;
    this.live = res.values || {};
    for (const [k, v] of Object.entries(this.live)) if (!(k in this.samples) && v !== null) this.samples[k] = v;
    if (this.mounted) { this.renderBar(); this.renderSide(); this.pose(); }
  }

  refresh() {
    if (!this.mounted) return;
    this.renderBar();
    this.renderSide();
    this.pose();
  }

  /** Werte der Vorschau: Beispielwerte + Zustände zur gewählten Zeit */
  values() {
    const out = { ...statesAt(this.info, this.when) };
    for (const k of Object.keys(this.data.v || {})) out[k] = k in this.samples ? this.samples[k] : (this.live[k] ?? null);
    return out;
  }

  /** Vorschau: Kopie mit eingesetzten Werten über dem Dokument */
  pose() {
    if (!this.mounted) return;
    const on = this.preview && bound(this.root).length > 0;
    this.style.disabled = !on;
    if (!on) { this.view?.remove(); this.view = null; return; }
    const c = this.root.cloneNode(true);
    c.classList.add('ge-data-view');
    c.setAttribute('aria-hidden', 'true');
    applyAll(c, this.values());
    if (this.view) this.view.replaceWith(c); else this.root.after(c);
    this.view = c;
  }

  ensureId(el) {
    if (!el.id) el.id = uid(this.root, (nameOf(el) || typeLabel(el) || 'objekt').toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 24) + '-');
    return el.id;
  }

  /** Alle Werte, an die man binden kann: eigene (Datentabellen) und Zustände */
  choices() {
    const d = this.data;
    return [...Object.keys(d.v || {}).map(k => ({ id: k, label: valueLabel(k, d) })), ...STATES.map(s => ({ id: s, label: STATE_LABEL[s]() }))];
  }

  /** Vorgabe „von–bis“ für einen Wert: Zustände ihr Bereich, Tabellenwerte 0 bis zur nächsten runden Zahl über dem 1,25-Fachen */
  rangeFor(v) {
    if (STATE_RANGE[v]) return [...STATE_RANGE[v]];
    const cur = Math.abs(+(this.samples[v] ?? this.live[v] ?? 0) || 0) * 1.25;
    if (!cur) return [0, 100];
    const p = Math.pow(10, Math.floor(Math.log10(cur)));
    const top = [1, 2, 2.5, 5, 10].map(m => m * p).find(x => x >= cur) || 10 * p;
    return [0, +num(Math.max(1, top), 2)];
  }

  // ================================================================= Leiste unter der Zeichenfläche (Vorschau)
  barHtml() {
    return `<div class="gd-bar" role="group" aria-label="${esc(t('Vorschau mit Beispielwerten'))}"></div>`;
  }

  renderBar() {
    const bar = this.area?.querySelector('.gd-bar');
    if (!bar) return;
    if (bar.contains(document.activeElement) && document.activeElement.type === 'range' && this.dragging) return;
    const d = this.data, st = statesAt(this.info, this.when), vals = this.values();
    const used = new Set(bound(this.root).flatMap(el => getBinds(el).map(b => b.v)));
    const own = Object.keys(d.v || {});
    const sliders = own.map(k => {
      const [lo, hi] = this.rangeFor(k);
      const v = vals[k] ?? 0;
      const max = Math.max(hi, +v || 0);
      return `<label class="gd-val"><span class="gd-val__n">${esc(valueLabel(k, d))}</span>
        <input type="range" class="ge-range" data-sample="${esc(k)}" min="${Math.min(lo, +v || 0)}" max="${max}" step="${max - lo > 50 ? 1 : 0.1}" value="${esc(String(+v || 0))}" aria-label="${esc(t('Beispielwert: {name}', { name: valueLabel(k, d) }))}">
        <input type="number" class="ge-in ge-in--s" data-samplen="${esc(k)}" value="${esc(String(v ?? ''))}" step="any" aria-label="${esc(t('Beispielwert: {name}', { name: valueLabel(k, d) }))}">
        ${this.live[k] !== undefined && this.live[k] !== null ? `<button type="button" class="adm-btn adm-btn--small gd-now" data-livev="${esc(k)}" title="${esc(t('Aktuellen Wert der Website übernehmen'))}">${esc(t('aktuell: {v}', { v: format(this.live[k], 'num', Number.isInteger(+this.live[k]) ? 0 : 2) }))}</button>` : ''}</label>`;
    }).join('');
    const days = DAYS().map((n, i) => `<option value="${i + 1}"${this.when.dow === i + 1 ? ' selected' : ''}>${esc(n)}</option>`).join('');
    bar.innerHTML = `<div class="gd-bar__row">
        <label class="ge-chk gd-chk"><input type="checkbox" data-pv${this.preview ? ' checked' : ''}> ${esc(t('Vorschau mit Beispielwerten'))}</label>
        <span class="gd-badge${st['@tag'] ? ' is-day' : ' is-night'}" data-daystate>${ico(st['@tag'] ? 'sun' : 'moon')} ${esc(st['@tag'] ? t('Tag') : t('Nacht'))}</span>
        <span class="gd-badge${st['@offen'] ? ' is-open' : ' is-closed'}" data-openstate>${esc(st['@offen'] ? t('geöffnet') : t('geschlossen'))}</span>
        <span class="gd-bar__grow"></span>
        <span class="gm-tl__hint">${esc(used.size ? t('{n} Werte gebunden', { n: used.size }) : t('Noch keine Bindungen'))}</span>
      </div>
      <div class="gd-bar__row gd-bar__time">
        <label class="gd-val gd-val--time"><span class="gd-val__n">${esc(t('Uhrzeit'))}</span>
          <input type="range" class="ge-range" data-hour min="0" max="23.75" step="0.25" value="${esc(String(this.when.hour))}" aria-label="${esc(t('Uhrzeit'))}" aria-valuetext="${esc(hm(this.when.hour))}">
          <output class="gd-hour" data-hourout>${esc(hm(this.when.hour))}</output></label>
        <label class="ge-lbl gd-lbl">${esc(t('Wochentag'))} <select class="ge-in" data-dow>${days}</select></label>
        <label class="ge-lbl gd-lbl">${esc(t('Datum'))} <input type="date" class="ge-in" data-date value="${esc(this.when.date)}"></label>
        <button type="button" class="adm-btn adm-btn--small" data-now>${esc(t('Jetzt'))}</button>
      </div>
      ${sliders ? `<div class="gd-bar__row gd-bar__vals">${sliders}</div>` : ''}
      <p class="gd-note">${esc(this.info.located ? t('Tag/Nacht nach Sonnenauf- und -untergang am Ort der Website ({a}–{b} Uhr heute).', { a: hm(this.info.sun[0]), b: hm(this.info.sun[1]) }) : t('Ohne Ort der Website gilt Tag von 7 bis 19 Uhr.'))} ${esc((this.info.hours || []).length ? t('Geöffnet nach den Öffnungszeiten der Website.') : t('Die Website hat keine Öffnungszeiten – „geöffnet“ ist nie erfüllt.'))}</p>`;
  }

  bindBar() {
    const bar = this.area.querySelector('.gd-bar');
    bar.addEventListener('input', e => {
      const x = e.target;
      if (x.dataset.sample) { this.samples[x.dataset.sample] = +x.value; const n = bar.querySelector(`[data-samplen="${CSS.escape(x.dataset.sample)}"]`); if (n) n.value = x.value; this.pose(); return; }
      if (x.dataset.samplen !== undefined && x.value !== '' && Number.isFinite(+x.value)) { this.samples[x.dataset.samplen] = +x.value; const r = bar.querySelector(`[data-sample="${CSS.escape(x.dataset.samplen)}"]`); if (r) r.value = x.value; this.pose(); return; }
      if (x.dataset.hour !== undefined) {
        this.when.hour = +x.value;
        bar.querySelector('[data-hourout]').textContent = hm(this.when.hour);
        x.setAttribute('aria-valuetext', hm(this.when.hour));
        this.badges();
        this.pose();
      }
    });
    bar.addEventListener('change', e => {
      const x = e.target;
      if (x.dataset.pv !== undefined) { this.preview = x.checked; this.pose(); return; }
      if (x.dataset.dow !== undefined) { this.when.dow = +x.value; this.badges(); this.pose(); return; }
      if (x.dataset.date !== undefined && x.value) { this.when.date = x.value; const d = new Date(x.value + 'T12:00'); this.when.dow = ((d.getDay() + 6) % 7) + 1; this.renderBar(); this.pose(); }
    });
    bar.addEventListener('click', e => {
      const b = e.target.closest('button');
      if (!b) return;
      if (b.dataset.now !== undefined) {
        const n = new Date();
        this.when = { hour: n.getHours() + Math.floor(n.getMinutes() / 15) / 4, dow: ((n.getDay() + 6) % 7) + 1, date: n.toISOString().slice(0, 10) };
        this.renderBar(); this.pose();
      }
      if (b.dataset.livev) { this.samples[b.dataset.livev] = this.live[b.dataset.livev]; this.renderBar(); this.pose(); }
    });
  }

  badges() {
    const st = statesAt(this.info, this.when), bar = this.area?.querySelector('.gd-bar');
    if (!bar) return;
    const d = bar.querySelector('[data-daystate]'), o = bar.querySelector('[data-openstate]');
    d.className = 'gd-badge ' + (st['@tag'] ? 'is-day' : 'is-night'); d.innerHTML = ico(st['@tag'] ? 'sun' : 'moon') + ' ' + esc(st['@tag'] ? t('Tag') : t('Nacht'));
    o.className = 'gd-badge ' + (st['@offen'] ? 'is-open' : 'is-closed'); o.textContent = st['@offen'] ? t('geöffnet') : t('geschlossen');
  }

  // ================================================================= Bereich rechts
  renderSide() {
    if (!this.side || this.side.hidden) return;
    const pb = this.side.querySelector('[id$="-dp-bind"]'), pv = this.side.querySelector('[id$="-dp-vals"]');
    const focus = this.side.contains(document.activeElement) ? document.activeElement : null;
    const key = focus?.dataset?.focuskey;
    if (!pb.hidden) pb.innerHTML = this.bindHtml();
    if (!pv.hidden) pv.innerHTML = this.valsHtml();
    if (key) this.side.querySelector(`[data-focuskey="${CSS.escape(key)}"]`)?.focus();
  }

  bindHtml() {
    const els = this.ed.sel.filter(x => x !== this.root), el = els[0];
    if (!el) {
      const list = bound(this.root);
      return `<div class="ge-form"><p class="ge-note">${esc(t('Objekt auswählen, um eine Eigenschaft an einen Wert zu binden – z. B. die Länge eines Balkens an die Zahl der Anmeldungen oder eine Sonne, die nur tagsüber erscheint.'))}</p>
        ${list.length ? `<h2 class="ge-h3 gm-h3">${esc(t('Gebundene Objekte'))}</h2><ol class="gm-order">${list.map(x => `<li><button type="button" class="gm-pick" data-pick="${esc(x.id)}">${esc(nameOf(x) || typeLabel(x))}</button> <span class="ge-ctx__info">${esc(getBinds(x).map(b => PROP_LABEL[b.p]()).join(', '))}</span></li>`).join('')}</ol>` : ''}</div>`;
    }
    if (els.length > 1) return `<div class="ge-form"><p class="ge-note">${esc(t('Bindungen gelten für ein Objekt – bitte nur eines auswählen.'))}</p></div>`;
    const list = getBinds(el), ch = this.choices();
    const isText = el.localName === 'text';
    const avail = PROPS.filter(p => (p !== 'text' || isText) && !list.some(b => b.p === p));
    const opt = (v, l, cur) => `<option value="${esc(v)}"${String(cur) === String(v) ? ' selected' : ''}>${esc(l)}</option>`;
    const cards = list.map((b, i) => {
      const k = `b${i}`;
      const vsel = `<label class="ge-lbl">${esc(t('Wert'))} <select class="ge-in" data-bf="v" data-bi="${i}" data-focuskey="${k}v">${ch.map(c => opt(c.id, c.label, b.v)).join('')}</select></label>`;
      const range = `<div class="ge-row4"><label>${esc(t('Wert von'))} <input type="number" class="ge-in ge-in--s" step="any" data-bf="a0" data-bi="${i}" data-focuskey="${k}a0" value="${esc(String(b.a?.[0] ?? 0))}"></label>
        <label>${esc(t('bis'))} <input type="number" class="ge-in ge-in--s" step="any" data-bf="a1" data-bi="${i}" data-focuskey="${k}a1" value="${esc(String(b.a?.[1] ?? 100))}"></label></div>`;
      let body = '';
      if (b.p === 'text') {
        body = `<label class="ge-lbl">${esc(t('Vorlage'))} <input type="text" class="ge-in" data-bf="tpl" data-bi="${i}" data-focuskey="${k}tpl" maxlength="300" value="${esc(b.tpl || '{v}')}"></label>
          <p class="ge-note">${esc(t('{v} steht für den Wert, z. B. „Noch {v} Plätze frei“.'))}</p>
          <div class="ge-row4"><label>${esc(t('Format'))} <select class="ge-in" data-bf="fmt" data-bi="${i}" data-focuskey="${k}fmt">${FORMATS.map(f => opt(f, FMT_LABEL[f](), b.fmt || 'num')).join('')}</select></label>
          <label>${esc(t('Nachkommastellen'))} <input type="number" class="ge-in ge-in--s" min="0" max="4" step="1" data-bf="d" data-bi="${i}" data-focuskey="${k}d" value="${esc(String(b.d || 0))}"></label></div>`;
      } else if (b.p === 'w' || b.p === 'h') {
        const lab = b.p === 'w' ? [t('links'), t('Mitte'), t('rechts')] : [t('oben'), t('Mitte'), t('unten')];
        body = range + `<p class="ge-note">${esc(t('Bei „bis“ so lang wie gezeichnet, bei „von“ null.'))}</p>
          <label class="ge-lbl">${esc(t('Wächst von'))} <select class="ge-in" data-bf="at" data-bi="${i}" data-focuskey="${k}at">${['start', 'center', 'end'].map((a, j) => opt(a, lab[j], b.at || (b.p === 'h' ? 'end' : 'start'))).join('')}</select></label>
          <label class="ge-lbl ge-chk"><input type="checkbox" data-bf="c" data-bi="${i}" data-focuskey="${k}c"${b.c !== 0 ? ' checked' : ''}> ${esc(t('Nicht über die gezeichnete Länge hinaus'))}</label>`;
      } else if (b.p === 'r') {
        body = range + `<div class="ge-row4"><label>${esc(t('Winkel von'))} <input type="number" class="ge-in ge-in--s" step="any" data-bf="g0" data-bi="${i}" data-focuskey="${k}g0" value="${esc(String(b.g?.[0] ?? 0))}"></label>
          <label>${esc(t('bis'))} <input type="number" class="ge-in ge-in--s" step="any" data-bf="g1" data-bi="${i}" data-focuskey="${k}g1" value="${esc(String(b.g?.[1] ?? 360))}"></label></div>
          <p class="ge-note">${esc(t('Dreht um die Mitte des Objekts – z. B. ein Zeiger, der mit dem Wert ausschlägt.'))}</p>`;
      } else if (b.p === 'o') {
        body = range + `<div class="ge-row4"><label>${esc(t('Deckkraft von'))} <input type="number" class="ge-in ge-in--s" min="0" max="100" step="1" data-bf="g0" data-bi="${i}" data-focuskey="${k}g0" value="${esc(String(Math.round((b.g?.[0] ?? 0) * 100)))}"></label>
          <label>${esc(t('bis'))} <input type="number" class="ge-in ge-in--s" min="0" max="100" step="1" data-bf="g1" data-bi="${i}" data-focuskey="${k}g1" value="${esc(String(Math.round((b.g?.[1] ?? 1) * 100)))}"></label></div>`;
      } else if (b.p === 'fill' || b.p === 'stroke') {
        const steps = b.m === 'steps';
        body = `<label class="ge-lbl">${esc(t('Art'))} <select class="ge-in" data-bf="m" data-bi="${i}" data-focuskey="${k}m">${opt('scale', t('Verlauf zwischen zwei Farben'), b.m || 'scale')}${opt('steps', t('Schwellen (ab Wert → Farbe)'), b.m || 'scale')}</select></label>`
          + (steps ? `<div class="gd-steps"><div class="gd-step"><span>${esc(t('sonst'))}</span><input type="color" class="gm-color" data-bf="b" data-bi="${i}" data-focuskey="${k}b" value="${esc(b.b || '#000000')}" aria-label="${esc(t('Farbe unter der ersten Schwelle'))}"></div>
              ${(b.s || []).map((s, j) => `<div class="gd-step"><span>${esc(t('ab'))}</span><input type="number" class="ge-in ge-in--s" step="any" data-bf="sx" data-bj="${j}" data-bi="${i}" data-focuskey="${k}sx${j}" value="${esc(String(s[0]))}" aria-label="${esc(t('Schwelle {n}', { n: j + 1 }))}">
                <input type="color" class="gm-color" data-bf="sc" data-bj="${j}" data-bi="${i}" data-focuskey="${k}sc${j}" value="${esc(s[1])}" aria-label="${esc(t('Farbe ab Schwelle {n}', { n: j + 1 }))}">
                <button type="button" class="ge-ib gd-x" data-bact="sdel" data-bj="${j}" data-bi="${i}" aria-label="${esc(t('Schwelle {n} entfernen', { n: j + 1 }))}">${ico('x')}</button></div>`).join('')}
              <button type="button" class="adm-btn adm-btn--small" data-bact="sadd" data-bi="${i}" data-focuskey="${k}sadd">${ico('plus')} ${esc(t('Schwelle'))}</button></div>`
            : range + `<div class="ge-row4"><label>${esc(t('Farbe bei „von“'))} <input type="color" class="gm-color" data-bf="c0" data-bi="${i}" data-focuskey="${k}c0" value="${esc(b.c?.[0] || b.b || '#000000')}"></label>
              <label>${esc(t('bei „bis“'))} <input type="color" class="gm-color" data-bf="c1" data-bi="${i}" data-focuskey="${k}c1" value="${esc(b.c?.[1] || b.b || '#000000')}"></label></div>`);
      } else if (b.p === 'show') {
        if (b.v === '@tag' || b.v === '@offen') {
          const yes = b.v === '@tag' ? t('am Tag') : t('wenn geöffnet'), no = b.v === '@tag' ? t('in der Nacht') : t('wenn geschlossen');
          const cur = b.op === '=' && +b.x === 0 ? '0' : '1';
          body = `<label class="ge-lbl">${esc(t('Sichtbar'))} <select class="ge-in" data-bf="yn" data-bi="${i}" data-focuskey="${k}yn">${opt('1', yes, cur)}${opt('0', no, cur)}</select></label>`;
        } else {
          body = `<div class="ge-row4"><label>${esc(t('Sichtbar, wenn Wert'))} <select class="ge-in" data-bf="op" data-bi="${i}" data-focuskey="${k}op">${OPS.map(o => opt(o, typeof OP_LABEL[o] === 'function' ? OP_LABEL[o]() : OP_LABEL[o], b.op || '>')).join('')}</select></label>
            <label>${esc(t('Schwelle'))} <input type="number" class="ge-in ge-in--s" step="any" data-bf="x" data-bi="${i}" data-focuskey="${k}x" value="${esc(String(b.x ?? 0))}"></label></div>
            ${b.op === 'between' ? `<label class="ge-lbl">${esc(t('und'))} <input type="number" class="ge-in ge-in--s" step="any" data-bf="y" data-bi="${i}" data-focuskey="${k}y" value="${esc(String(b.y ?? 0))}"></label>` : ''}
            ${b.v === '@wochentag' ? `<p class="ge-note">${esc(t('1 = Montag … 7 = Sonntag'))}</p>` : b.v === '@datum' ? `<p class="ge-note">${esc(t('Monat und Tag als Zahl: 1201 = 1. Dezember, „zwischen 1201 und 1226“ = Adventszeit.'))}</p>` : b.v === '@uhrzeit' ? `<p class="ge-note">${esc(t('Stunden: 6,5 = 6:30 Uhr'))}</p>` : ''}`;
        }
      }
      return `<div class="ge-form gd-card"><div class="gd-card__h"><h2 class="ge-h3 gm-h3">${esc(PROP_LABEL[b.p]())}</h2>
        <button type="button" class="ge-ib gd-x" data-bact="del" data-bi="${i}" aria-label="${esc(t('Bindung „{name}“ entfernen', { name: PROP_LABEL[b.p]() }))}" title="${esc(t('Bindung entfernen'))}">${ico('trash')}</button></div>${vsel}${body}</div>`;
    }).join('');
    const add = avail.length ? `<div class="ge-form"><label class="ge-lbl">${esc(t('Eigenschaft binden'))} <select class="ge-in" data-addprop data-focuskey="addprop"><option value="">${esc(t('– wählen –'))}</option>${avail.map(p => opt(p, PROP_LABEL[p](), '')).join('')}</select></label>
      <button type="button" class="adm-btn adm-btn--small" data-bact="add" data-focuskey="addbtn">${ico('link')} ${esc(t('Binden'))}</button></div>` : '';
    return `<div class="ge-form"><p class="gd-obj"><strong>${esc(nameOf(el) || typeLabel(el))}</strong></p></div>${cards}${add}`;
  }

  valsHtml() {
    const d = this.data, own = Object.entries(d.v || {});
    const tables = this.info.tables || [];
    const desc = def => {
      const tb = tables.find(x => x.handle === def.tb);
      const f = tb?.fields.find(x => x.name === def.f);
      const w = def.w ? ` · ${t('Filter')}: ${tb?.fields.find(x => x.name === def.w[0])?.label || def.w[0]} ${def.w[1]} ${def.w[2]}` : '';
      return (def.a === 'count' ? AGG_LABEL.count() : `${AGG_LABEL[def.a]?.() || def.a}: ${f?.label || def.f}`) + ` · ${tb?.name || def.tb}` + w;
    };
    return `<div class="ge-form"><h2 class="ge-h3 gm-h3">${esc(t('Werte aus Datentabellen'))}</h2>
      ${own.length ? `<ul class="gd-list">${own.map(([k, def]) => `<li><div><strong>${esc(def.l || k)}</strong> <code>{${esc(k)}}</code><br><span class="ge-note">${esc(desc(def))}${this.live[k] !== undefined ? ' · ' + esc(t('aktuell: {v}', { v: format(this.live[k], 'num', Number.isInteger(+this.live[k]) ? 0 : 2) })) : ''}</span></div>
        <span class="gd-list__acts"><button type="button" class="ge-ib" data-vact="edit" data-vk="${esc(k)}" aria-label="${esc(t('Wert „{name}“ bearbeiten', { name: def.l || k }))}">${ico('pencil-simple')}</button>
        <button type="button" class="ge-ib" data-vact="del" data-vk="${esc(k)}" aria-label="${esc(t('Wert „{name}“ löschen', { name: def.l || k }))}">${ico('trash')}</button></span></li>`).join('')}</ul>` : `<p class="ge-note">${esc(tables.length ? t('Noch keine Werte. Ein Wert fasst Einträge einer Datentabelle zusammen – Anzahl, Summe, Durchschnitt …') : t('Diese Website hat noch keine Datentabellen.'))}</p>`}
      ${tables.length ? `<button type="button" class="adm-btn adm-btn--small" data-vact="new" data-focuskey="vnew">${ico('plus')} ${esc(t('Wert aus Datentabelle …'))}</button>` : ''}
      <p class="ge-note">${esc(t('Im Alt-Text der Mediathek setzt {name} den aktuellen Wert ein; ohne Platzhalter werden die Werte angehängt.'))}</p></div>
      <div class="ge-form"><h2 class="ge-h3 gm-h3">${esc(t('Zustände der Website'))}</h2>
      <ul class="gd-list gd-list--states">${STATES.map(s => `<li><div><strong>${esc(STATE_LABEL[s]())}</strong> <code>{${esc(s)}}</code></div><span class="ge-note">${esc(this.stateNow(s))}</span></li>`).join('')}</ul>
      <p class="ge-note">${esc(t('Auf der Website rechnet der Server die Werte ein – ohne JavaScript. Seiten mit Zuständen werden nur bis zum nächsten Wechsel zwischengespeichert.'))}</p></div>`;
  }

  stateNow(s) {
    const v = this.info.states?.[s];
    if (v === undefined || v === null) return '';
    if (s === '@uhrzeit') return t('jetzt {v} Uhr', { v: hm(v) });
    if (s === '@tag') return v ? t('jetzt Tag') : t('jetzt Nacht');
    if (s === '@offen') return v ? t('jetzt geöffnet') : t('jetzt geschlossen');
    if (s === '@wochentag') return DAYS()[v - 1] || '';
    return t('jetzt {v}', { v: String(v) });
  }

  /** Wert anlegen oder bearbeiten (Dialog) */
  async editValue(key = null) {
    const d = this.data, def = key ? d.v[key] : { tb: this.info.tables[0]?.handle || '', a: 'count', f: '', w: null, l: '' };
    const tables = this.info.tables || [];
    const fieldOpts = (tb, cur, numOnly) => (tables.find(x => x.handle === tb)?.fields || []).filter(f => !numOnly || f.type === 'number').map(f => `<option value="${esc(f.name)}"${f.name === cur ? ' selected' : ''}>${esc(f.label)}</option>`).join('');
    const r = await modal(this.ed.el, {
      title: key ? t('Wert bearbeiten') : t('Wert aus Datentabelle'), ok: key ? t('Übernehmen') : t('Anlegen'),
      body: `<label class="f">${esc(t('Bezeichnung'))}<input name="l" maxlength="80" required value="${esc(def.l || '')}" placeholder="${esc(t('z. B. Freie Plätze'))}"></label>
        <label class="f">${esc(t('Datentabelle'))}<select name="tb" data-tb>${tables.map(x => `<option value="${esc(x.handle)}"${x.handle === def.tb ? ' selected' : ''}>${esc(x.name)}</option>`).join('')}</select></label>
        <label class="f">${esc(t('Kennzahl'))}<select name="a" data-agg>${AGGS.map(a => `<option value="${a}"${a === def.a ? ' selected' : ''}>${esc(AGG_LABEL[a]())}</option>`).join('')}</select></label>
        <label class="f" data-fwrap>${esc(t('Feld'))}<select name="f" data-f>${fieldOpts(def.tb, def.f, def.a !== 'latest')}</select></label>
        <fieldset class="ge-webopt"><legend>${esc(t('Nur Einträge, bei denen (optional)'))}</legend>
          <div class="ge-row2"><label class="f">${esc(t('Feld'))}<select name="wf" data-wf><option value="">${esc(t('– alle Einträge –'))}</option>${fieldOpts(def.tb, def.w?.[0] || '', false)}</select></label>
          <label class="f">${esc(t('Vergleich'))}<select name="wo">${['=', '!=', '>', '<', '>=', '<=', 'contains'].map(o => `<option value="${o}"${o === (def.w?.[1] || '=') ? ' selected' : ''}>${esc(o === 'contains' ? t('enthält') : o)}</option>`).join('')}</select></label></div>
          <label class="f">${esc(t('Wert'))}<input name="wv" maxlength="200" value="${esc(def.w?.[2] ?? '')}"></label></fieldset>
        <p class="f-help">${esc(t('Es zählen nur veröffentlichte Einträge – wie auf der Website. Ändern sich die Einträge, zeigt die Grafik beim nächsten Aufruf die neuen Werte.'))}</p>`,
      onOpen: dlg => {
        const tb = dlg.querySelector('[data-tb]'), agg = dlg.querySelector('[data-agg]'), f = dlg.querySelector('[data-f]'), wf = dlg.querySelector('[data-wf]'), wrap = dlg.querySelector('[data-fwrap]');
        const sync = () => {
          const cur = f.value;
          f.innerHTML = fieldOpts(tb.value, cur, agg.value !== 'latest');
          wrap.hidden = agg.value === 'count';
          f.required = agg.value !== 'count';
          const w = wf.value;
          wf.innerHTML = `<option value="">${esc(t('– alle Einträge –'))}</option>` + fieldOpts(tb.value, w, false);
        };
        tb.addEventListener('change', sync); agg.addEventListener('change', sync); sync();
      },
    });
    if (!r) return;
    const nd = this.data;
    const k = key || slug(r.l, Object.keys(nd.v || {}));
    nd.v = { ...(nd.v || {}), [k]: { t: 'table', tb: r.tb, a: r.a, f: r.a === 'count' ? '' : r.f, w: r.wf ? [r.wf, r.wo, r.wv] : null, l: r.l.trim() } };
    setData(this.root, nd);
    this.ed.commit(key ? t('Wert „{name}“ geändert', { name: r.l }) : t('Wert „{name}“ angelegt', { name: r.l }));
    delete this.samples[k];
    this.fetchLive();
  }

  deleteValue(key) {
    const d = this.data;
    const name = valueLabel(key, d);
    const users = bound(this.root).filter(el => getBinds(el).some(b => b.v === key));
    for (const el of users) setBinds(el, getBinds(el).filter(b => b.v !== key));
    delete d.v[key];
    setData(this.root, d);
    this.ed.commit(t('Wert „{name}“ gelöscht', { name }));
    delete this.samples[key];
    if (users.length) toast(t('{n} Bindungen mit diesem Wert entfernt.', { n: users.length }), 'info');
  }

  /** Neue Bindung mit sinnvollen Vorgaben */
  newBinding(el, p) {
    const ch = this.choices();
    const own = ch.find(c => !c.id.startsWith('@'));
    const v = (p === 'show' ? (own?.id || '@tag') : (own?.id || '@uhrzeit'));
    const a = this.rangeFor(v);
    const b = { p, v };
    if (p === 'text') {
      const txt = (el.textContent || '').trim();
      const m = txt.match(/[-+]?\d[\d.,]*/);
      b.tpl = m ? txt.slice(0, m.index) + '{v}' + txt.slice(m.index + m[0].length) : '{v}';
      b.fmt = 'num'; b.d = 0;
    } else if (p === 'w' || p === 'h') Object.assign(b, { a, at: p === 'h' ? 'end' : 'start', c: 1 });
    else if (p === 'r') Object.assign(b, { a, g: [0, 180] });
    else if (p === 'o') Object.assign(b, { a, g: [0.2, 1] });
    else if (p === 'fill' || p === 'stroke') {
      const cur = (hex(getProp(el, p) || '') || '#5b73ad').toLowerCase();
      Object.assign(b, { m: 'scale', a, c: ['#c9ced8', cur], b: cur });
    } else if (p === 'show') Object.assign(b, v === '@tag' || v === '@offen' ? { op: '=', x: 1 } : { op: '>', x: a[0] });
    if (['w', 'h', 'r'].includes(p)) b.o = anchorOf(el, b);
    return b;
  }

  bindSide() {
    const S = this.side;
    S.addEventListener('click', e => {
      const b = e.target.closest('button');
      if (!b) return;
      if (b.dataset.pick) { const el = this.root.querySelector('#' + CSS.escape(b.dataset.pick)); if (el) this.ed.select([el]); return; }
      if (b.dataset.vact === 'new') { this.editValue(); return; }
      if (b.dataset.vact === 'edit') { this.editValue(b.dataset.vk); return; }
      if (b.dataset.vact === 'del') { this.deleteValue(b.dataset.vk); return; }
      const el = this.ed.sel[0];
      if (!el || !b.dataset.bact) return;
      const list = getBinds(el), i = +b.dataset.bi;
      if (b.dataset.bact === 'add') {
        const p = S.querySelector('[data-addprop]')?.value;
        if (!p) { toast(t('Bitte zuerst eine Eigenschaft wählen.'), 'info'); S.querySelector('[data-addprop]')?.focus(); return; }
        this.addBinding(el, p);
        return;
      }
      if (b.dataset.bact === 'del') { const name = PROP_LABEL[list[i].p](); list.splice(i, 1); setBinds(el, list); this.ed.commit(t('Bindung „{name}“ entfernt', { name })); return; }
      if (b.dataset.bact === 'sadd') {
        const bb = list[i], s = bb.s || [];
        const last = s.length ? s[s.length - 1][0] : this.rangeFor(bb.v)[0];
        s.push([+num(last + Math.max(1, (this.rangeFor(bb.v)[1] - this.rangeFor(bb.v)[0]) / 4), 2), '#e5484d']);
        bb.s = s; setBinds(el, list); this.ed.commit(t('Schwelle hinzugefügt'));
        requestAnimationFrame(() => S.querySelector(`[data-focuskey="b${i}sx${s.length - 1}"]`)?.focus());
        return;
      }
      if (b.dataset.bact === 'sdel') { list[i].s.splice(+b.dataset.bj, 1); setBinds(el, list); this.ed.commit(t('Schwelle entfernt')); }
    });
    S.addEventListener('change', e => {
      const x = e.target;
      if (x.dataset.addprop !== undefined) return;
      const el = this.ed.sel[0];
      if (!el || x.dataset.bf === undefined) return;
      const list = getBinds(el), i = +x.dataset.bi, b = list[i];
      if (!b) return;
      const f = x.dataset.bf, n = parseFloat(x.value);
      const label = PROP_LABEL[b.p]();
      switch (f) {
        case 'v': {
          b.v = x.value;
          if (b.a) b.a = this.rangeFor(b.v);
          if (b.p === 'show') Object.assign(b, b.v === '@tag' || b.v === '@offen' ? { op: '=', x: 1 } : { op: b.op === '=' ? '>' : b.op, x: this.rangeFor(b.v)[0] });
          break;
        }
        case 'a0': case 'a1': if (!Number.isFinite(n)) return; b.a = b.a || [0, 100]; b.a[f === 'a0' ? 0 : 1] = n; break;
        case 'g0': case 'g1': if (!Number.isFinite(n)) return; b.g = b.g || (b.p === 'o' ? [0, 1] : [0, 360]); b.g[f === 'g0' ? 0 : 1] = b.p === 'o' ? Math.max(0, Math.min(1, n / 100)) : n; break;
        case 'tpl': b.tpl = x.value.includes('{v}') ? x.value : x.value + ' {v}'; break;
        case 'fmt': b.fmt = x.value; break;
        case 'd': b.d = Math.max(0, Math.min(4, Math.round(n) || 0)); break;
        case 'at': b.at = x.value; b.o = anchorOf(el, b); break;
        case 'c': b.c = x.checked ? 1 : 0; break;
        case 'm': b.m = x.value; if (b.m === 'steps' && !b.s?.length) { const r = this.rangeFor(b.v); b.s = [[+num((r[0] + r[1]) / 2, 2), '#e5484d']]; } if (b.m === 'scale' && !b.a) b.a = this.rangeFor(b.v); break;
        case 'b': b.b = x.value; break;
        case 'c0': case 'c1': b.c = b.c || [b.b, b.b]; b.c[f === 'c0' ? 0 : 1] = x.value; break;
        case 'sx': if (!Number.isFinite(n)) return; b.s[+x.dataset.bj][0] = n; break;
        case 'sc': b.s[+x.dataset.bj][1] = x.value; break;
        case 'op': b.op = x.value; break;
        case 'x': case 'y': if (!Number.isFinite(n)) return; b[f] = n; break;
        case 'yn': b.op = '='; b.x = +x.value; break;
        default: return;
      }
      setBinds(el, list);
      this.ed.commit(t('Bindung: {name}', { name: label }));
    });
  }

  addBinding(el, p) {
    this.ensureId(el);
    const list = getBinds(el);
    if (list.some(b => b.p === p)) return;
    list.push(this.newBinding(el, p));
    setBinds(el, list);
    this.ed.commit(t('{name} gebunden', { name: PROP_LABEL[p]() }));
    if (!Object.keys(this.data.v || {}).length && !list[list.length - 1].v.startsWith('@')) this.fetchLive();
    requestAnimationFrame(() => this.side?.querySelector(`[data-focuskey="b${list.length - 1}v"]`)?.focus());
  }

  // ================================================================= Prüfen
  checks(add) {
    const root = this.root;
    if (!hasData(root)) return;
    const d = getData(root), tables = this.info.tables || [];
    for (const [k, def] of Object.entries(d.v || {})) {
      if (!tables.some(x => x.handle === def.tb)) add({ key: 'datatable', sev: 'warn', mode: null, title: t('Datentabelle fehlt'), detail: t('Der Wert „{name}“ nutzt eine Tabelle, die es auf dieser Website nicht gibt – auf der Website erscheint „–“ bzw. der Wert „von“.', { name: def.l || k }) });
    }
    for (const el of bound(root)) {
      for (const b of getBinds(el)) {
        if (!b.v.startsWith('@') && !(d.v || {})[b.v]) add({ key: 'databind', sev: 'error', mode: null, el, title: t('Bindung ohne Wert'), detail: t('„{prop}“ ist an einen Wert gebunden, den es nicht mehr gibt (Bereich Daten → Werte).', { prop: PROP_LABEL[b.p]() }) });
        if (b.p === 'show' && el.querySelector('a, [data-ge-link]')) add({ key: 'datashow', sev: 'info', mode: null, el, title: t('Ausgeblendet mit Link'), detail: t('Ein Objekt mit Link verschwindet, wenn die Bedingung nicht erfüllt ist – der Link fehlt dann auch.') });
      }
    }
  }
}

export { hasData };
