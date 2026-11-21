/*
 * Grafik-Editor – Ebeneneffekte (fx, Ausbaustufe 3b) wie in Affinity: je Ebene mehrere Effekte, einzeln an/aus, Vorschau sofort.
 * Schlagschatten, innerer Schatten, äußeres/inneres Leuchten, Weichzeichnen, Kontur außen, Farbüberlagerung – als SVG-Filter aus
 * den Primitiven, die Core\Svg erlaubt (feGaussianBlur, feOffset, feFlood, feComposite, feComponentTransfer, feMorphology, feMerge,
 * feDropShadow). Das Modell steht am Filter: data-ge-fx="ds:dx=4,dy=6,b=6,c=#000000,o=0.25;!og:b=8,…" (! = aus; nur Arbeitsfassung),
 * die Web-Fassung trägt nur den fertigen Filter. Nur ein Schlagschatten → wie bisher ein einzelnes feDropShadow.
 * Verlaufsüberlagerung gibt es bewusst nicht als Filter (bräuchte feImage – das die Bereinigung als Pixelbild entfernt). MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { NS, num } from './geom.js';
import { getProp, setProp, refOf, uid, defs, useCount } from './doc.js';

export const FX = [
  { id: 'ds', label: () => t('Schlagschatten'), p: { dx: 4, dy: 6, b: 6, c: '#000000', o: 0.25 } },
  { id: 'is', label: () => t('Innerer Schatten'), p: { dx: 2, dy: 3, b: 4, c: '#000000', o: 0.35 } },
  { id: 'og', label: () => t('Äußeres Leuchten'), p: { b: 8, c: '#FFD54A', o: 0.8 } },
  { id: 'ig', label: () => t('Inneres Leuchten'), p: { b: 6, c: '#FFFFFF', o: 0.7 } },
  { id: 'ol', label: () => t('Kontur außen'), p: { w: 3, c: '#FFFFFF', o: 1 } },
  { id: 'co', label: () => t('Farbüberlagerung'), p: { c: '#5B73AD', o: 0.5 } },
  { id: 'bl', label: () => t('Weichzeichnen'), p: { b: 4 } },
];
const FIELDS = {
  dx: () => 'X', dy: () => 'Y', b: () => t('Weich'), w: () => t('Breite'), c: () => t('Farbe'), o: () => t('Deckkraft'),
};

/** Modell lesen: data-ge-fx bzw. ein einzelnes feDropShadow (ältere Grafiken, Schalter „Schatten“) */
export function fxModel(filter) {
  const out = {};
  if (!filter) return out;
  const s = filter.getAttribute('data-ge-fx');
  if (s) {
    for (const part of s.split(';')) {
      const m = /^(!?)([a-z]{2}):?(.*)$/.exec(part.trim());
      if (!m || !FX.some(f => f.id === m[2])) continue;
      const def = FX.find(f => f.id === m[2]).p;
      const p = { ...def };
      for (const kv of m[3].split(',')) { const [k, v] = kv.split('='); if (k in def) p[k] = k === 'c' ? (/^#[0-9a-f]{6}$/i.test(v) ? v.toUpperCase() : def.c) : (Number.isFinite(+v) ? +v : def[k]); }
      out[m[2]] = { on: !m[1], ...p };
    }
    return out;
  }
  const ds = filter.children.length === 1 && filter.firstElementChild.localName === 'feDropShadow' ? filter.firstElementChild : null;
  if (ds) out.ds = { on: true, dx: +(ds.getAttribute('dx') ?? 2), dy: +(ds.getAttribute('dy') ?? 2), b: +(ds.getAttribute('stdDeviation') ?? 2), c: (ds.getAttribute('flood-color') || '#000000').toUpperCase(), o: +(ds.getAttribute('flood-opacity') ?? 1) };
  return out;
}

/** Ist der Filter einer, den die Ebeneneffekte verwalten? */
export const isFxFilter = f => !!f && (f.hasAttribute('data-ge-fx') || (f.children.length === 1 && f.firstElementChild.localName === 'feDropShadow'));

const fmtModel = m => FX.filter(f => m[f.id]).map(f => (m[f.id].on ? '' : '!') + f.id + ':' + Object.keys(f.p).map(k => k + '=' + (k === 'c' ? m[f.id][k] : num(m[f.id][k], 3))).join(',')).join(';');

/** Filter-Primitive aus dem Modell schreiben */
export function writeFilter(f, m) {
  while (f.firstChild) f.firstChild.remove();
  f.setAttribute('data-ge-fx', fmtModel(m));
  const on = FX.filter(x => m[x.id]?.on).map(x => x.id);
  const el = (tag, a, parent = f) => { const e = document.createElementNS(NS, tag); for (const k in a) e.setAttribute(k, String(a[k])); parent.append(e); return e; };
  const n = v => num(v, 3);
  // Rand für Unschärfe und Versatz: großzügig (Objektmaß), sonst schneidet der Filter ab
  for (const [k, v] of Object.entries({ x: '-50%', y: '-50%', width: '200%', height: '200%' })) f.setAttribute(k, v);
  f.removeAttribute('color-interpolation-filters');
  if (!on.length) return;
  if (on.length === 1 && on[0] === 'ds') {
    const p = m.ds;
    el('feDropShadow', { dx: n(p.dx), dy: n(p.dy), stdDeviation: n(p.b), 'flood-color': p.c, 'flood-opacity': n(p.o) });
    return;
  }
  f.setAttribute('color-interpolation-filters', 'sRGB');
  const merge = [];
  const colored = (src, p, name) => { el('feFlood', { 'flood-color': p.c, 'flood-opacity': n(p.o), result: name + 'c' }); el('feComposite', { in: name + 'c', in2: src, operator: 'in', result: name }); return name; };
  // umgekehrte Alpha (für innere Effekte)
  let inv = null;
  const invAlpha = () => { if (inv) return inv; const ct = el('feComponentTransfer', { in: 'SourceAlpha', result: 'inv' }); el('feFuncA', { type: 'table', tableValues: '1 0' }, ct); return (inv = 'inv'); };
  if (on.includes('ds')) { const p = m.ds; el('feGaussianBlur', { in: 'SourceAlpha', stdDeviation: n(p.b), result: 'dsb' }); el('feOffset', { in: 'dsb', dx: n(p.dx), dy: n(p.dy), result: 'dso' }); merge.push(colored('dso', p, 'ds')); }
  if (on.includes('og')) { const p = m.og; el('feGaussianBlur', { in: 'SourceAlpha', stdDeviation: n(p.b), result: 'ogb' }); merge.push(colored('ogb', p, 'og')); }
  if (on.includes('ol')) { const p = m.ol; el('feMorphology', { in: 'SourceAlpha', operator: 'dilate', radius: n(p.w), result: 'olm' }); merge.push(colored('olm', p, 'ol')); }
  if (on.includes('bl')) { el('feGaussianBlur', { in: 'SourceGraphic', stdDeviation: n(m.bl.b), result: 'src' }); merge.push('src'); } else merge.push('SourceGraphic');
  if (on.includes('co')) merge.push(colored('SourceAlpha', m.co, 'co'));
  if (on.includes('is')) {
    const p = m.is; invAlpha();
    el('feGaussianBlur', { in: 'inv', stdDeviation: n(p.b), result: 'isb' }); el('feOffset', { in: 'isb', dx: n(p.dx), dy: n(p.dy), result: 'iso' });
    colored('iso', p, 'isc0'); el('feComposite', { in: 'isc0', in2: 'SourceAlpha', operator: 'in', result: 'is' }); merge.push('is');
  }
  if (on.includes('ig')) {
    const p = m.ig; invAlpha();
    el('feGaussianBlur', { in: 'inv', stdDeviation: n(p.b), result: 'igb' });
    colored('igb', p, 'igc0'); el('feComposite', { in: 'igc0', in2: 'SourceAlpha', operator: 'in', result: 'ig' }); merge.push('ig');
  }
  const mg = el('feMerge', {});
  for (const r of merge) el('feMergeNode', { in: r }, mg);
}

/** Bereich „Ebeneneffekte“ in Darstellung: box = Container, sel()/commit()/draw aus panels.js */
export function buildFx(ed, box, { sel, commit }) {
  box.innerHTML = `<h2 class="ge-h3">${esc(t('Ebeneneffekte'))}</h2><div class="ge-fx" role="list"></div><p class="ge-note" data-fxnote hidden></p>`;
  const list = box.querySelector('.ge-fx'), note = box.querySelector('[data-fxnote]');
  const open = new Set();
  const filterOf = el => { const v = getProp(el, 'filter', false); return v && v !== 'none' ? refOf(ed.root, v) : null; };
  /** eigener Filter je Element (geteilte Filter werden beim Ändern kopiert) */
  const own = el => {
    let f = filterOf(el);
    if (f && !isFxFilter(f)) return null;
    if (f && useCount(ed.root, f.id) > 1) { const c = f.cloneNode(true); c.id = uid(ed.root, 'fx-'); f.after(c); f = c; setProp(el, 'filter', `url(#${f.id})`); }
    if (!f) { f = document.createElementNS(NS, 'filter'); f.id = uid(ed.root, 'fx-'); defs(ed.root).append(f); setProp(el, 'filter', `url(#${f.id})`); }
    return f;
  };
  const apply = (fn, label) => {
    for (const el of sel()) {
      const f = own(el);
      if (!f) continue;
      const m = fxModel(f);
      fn(m);
      if (!FX.some(x => m[x.id])) { setProp(el, 'filter', null); if (!useCount(ed.root, f.id)) f.remove(); continue; }
      writeFilter(f, m);
      // alles aus: Filter bleibt (Einstellungen merken), zeichnet aber nichts zusätzlich
      if (!FX.some(x => m[x.id]?.on)) { setProp(el, 'filter', null); f.setAttribute('data-ge-fxoff', '1'); el.setAttribute('data-ge-fxref', f.id); }
    }
    if (label) commit(label); else ed.draw();
  };
  function render() {
    const el = sel()[0];
    let f = el ? filterOf(el) : null;
    // ausgeschaltete Effekte: Filter über data-ge-fxref wiederfinden
    if (!f && el?.getAttribute('data-ge-fxref')) f = ed.root.getElementById?.(el.getAttribute('data-ge-fxref')) || ed.root.querySelector('#' + CSS.escape(el.getAttribute('data-ge-fxref')));
    const foreign = f && !isFxFilter(f);
    const m = foreign ? {} : fxModel(f);
    list.innerHTML = FX.map(x => {
      const p = m[x.id], on = !!p?.on, ex = open.has(x.id) && !!p;
      return `<div class="ge-fx__it${on ? ' is-on' : ''}" role="listitem">
        <div class="ge-fx__row"><label class="ge-chk"><input type="checkbox" data-fx="${x.id}"${on ? ' checked' : ''}${!el || foreign ? ' disabled' : ''}> ${esc(x.label())}</label>
          ${p ? `<button type="button" class="ge-ib ge-fx__more" data-fxopen="${x.id}" aria-expanded="${ex}" aria-label="${esc(t('Einstellungen: {name}', { name: x.label() }))}" title="${esc(t('Einstellungen'))}">…</button>` : ''}</div>
        ${ex ? `<div class="ge-fx__opts">${Object.keys(x.p).map(k => k === 'c'
          ? `<label class="ge-lbl">${esc(FIELDS[k]())} <input type="color" class="ge-fx__color" data-fxk="${x.id}.${k}" value="${esc(p[k])}"></label>`
          : `<label class="ge-lbl">${esc(FIELDS[k]())} <input type="number" class="ge-in ge-in--s" data-fxk="${x.id}.${k}" value="${esc(num(k === 'o' ? p[k] * 100 : p[k], 2))}" step="${k === 'o' ? 5 : 0.5}" ${k === 'o' ? 'min="0" max="100"' : k === 'b' || k === 'w' ? 'min="0" max="200"' : ''}>${k === 'o' ? ' %' : ''}</label>`).join('')}</div>` : ''}
      </div>`;
    }).join('');
    note.hidden = !foreign && !hasHeavy(m);
    note.textContent = foreign ? t('Dieses Objekt hat einen eigenen Filter (aus der Datei) – Ebeneneffekte ersetzen ihn nicht.')
      : t('Hinweis: Große Weichzeichner und viele Effekte kosten Rechenzeit beim Anzeigen. Im PDF fehlen Effekte (Filter).');
    list.querySelectorAll('[data-fx]').forEach(c => c.addEventListener('change', () => {
      const id = c.dataset.fx;
      apply(mm => { const def = FX.find(x => x.id === id).p; mm[id] = { ...(mm[id] || def), on: c.checked }; }, c.checked ? t('Effekt: {name} an', { name: FX.find(x => x.id === id).label() }) : t('Effekt: {name} aus', { name: FX.find(x => x.id === id).label() }));
      if (c.checked) open.add(id);
      render();
      list.querySelector(`[data-fx="${id}"]`)?.focus();
    }));
    list.querySelectorAll('[data-fxopen]').forEach(b => b.addEventListener('click', () => { const id = b.dataset.fxopen; open.has(id) ? open.delete(id) : open.add(id); render(); list.querySelector(`[data-fxopen="${id}"]`)?.focus(); }));
    list.querySelectorAll('[data-fxk]').forEach(i => {
      const set = label => { const [id, k] = i.dataset.fxk.split('.'); const v = k === 'c' ? i.value.toUpperCase() : (k === 'o' ? Math.max(0, Math.min(100, +i.value || 0)) / 100 : +i.value || 0); apply(mm => { if (mm[id]) mm[id][k] = v; }, label); };
      i.addEventListener('input', () => set(null));
      i.addEventListener('change', () => set(t('Effekt geändert')));
    });
  }
  return { render };
}

/** viel Rechenlast? (Weichzeichner > 20 oder mehr als 3 Effekte) */
function hasHeavy(m) {
  const on = FX.filter(x => m[x.id]?.on);
  return on.length > 3 || on.some(x => (m[x.id].b || 0) > 20);
}
