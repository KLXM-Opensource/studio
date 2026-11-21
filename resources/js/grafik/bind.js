/*
 * Grafik-Editor – Daten und Zustände (Persona „Daten“, Ausbaustufe 5): Modell und dieselbe Rechnung wie die Website
 * (Core\Graphics\Bind setzt die Werte serverseitig ein – ohne JavaScript, CSP-sauber). Hier für die Vorschau mit Beispielwerten.
 *   Wurzel:  data-ge-data = Base64(JSON) {v: {name: {t: 'table', tb, a, f, w, l}}}           Werte aus Datentabellen
 *   Element: data-ge-bind = Base64(JSON) [{p: text|w|h|r|fill|stroke|o|show, v: name, …}]    Bindungen
 * Zustände (eingebaut): @uhrzeit, @tag, @offen, @wochentag, @monat, @datum. Alles liegt im Dokument – Rückgängig gilt wie sonst. MIT
 */
import { NS, num } from './geom.js';
import { t } from '../_i18n.js';

export const PROPS = ['text', 'w', 'h', 'r', 'fill', 'stroke', 'o', 'show'];
export const STATES = ['@uhrzeit', '@tag', '@offen', '@wochentag', '@monat', '@datum'];
export const AGGS = ['count', 'sum', 'avg', 'min', 'max', 'latest'];
export const OPS = ['>', '>=', '<', '<=', '=', '!=', 'between'];
export const FORMATS = ['num', 'pct', 'cur', 'time', 'raw'];

export const PROP_LABEL = {
  text: () => t('Text'), w: () => t('Breite (Länge waagerecht)'), h: () => t('Höhe (Länge senkrecht)'), r: () => t('Drehung'),
  fill: () => t('Füllung'), stroke: () => t('Kontur'), o: () => t('Deckkraft'), show: () => t('Sichtbarkeit'),
};
export const STATE_LABEL = {
  '@uhrzeit': () => t('Uhrzeit'), '@tag': () => t('Tag oder Nacht'), '@offen': () => t('Öffnungsstatus'), '@wochentag': () => t('Wochentag'),
  '@monat': () => t('Monat'), '@datum': () => t('Datum (MMTT)'),
};
export const AGG_LABEL = {
  count: () => t('Anzahl der Einträge'), sum: () => t('Summe'), avg: () => t('Durchschnitt'), min: () => t('Kleinster Wert'), max: () => t('Größter Wert'),
  latest: () => t('Neuester Eintrag'),
};
/** Wertebereich, den ein Zustand annimmt (Vorgabe für neue Bindungen) */
export const STATE_RANGE = { '@uhrzeit': [0, 24], '@tag': [0, 1], '@offen': [0, 1], '@wochentag': [1, 7], '@monat': [1, 12], '@datum': [101, 1231] };

const b64 = s => btoa(String.fromCharCode(...new TextEncoder().encode(s)));
const unb64 = s => new TextDecoder().decode(Uint8Array.from(atob(s), c => c.charCodeAt(0)));

// ---------------------------------------------------------------- Lesen und Schreiben

export function getData(root) {
  const v = root?.getAttribute?.('data-ge-data');
  if (!v) return { v: {} };
  try { const d = JSON.parse(unb64(v)); return { v: {}, ...(d && typeof d === 'object' ? d : {}) }; } catch { return { v: {} }; }
}
export function setData(root, d) {
  if (!d || !Object.keys(d.v || {}).length) { root.removeAttribute('data-ge-data'); return; }
  root.setAttribute('data-ge-data', b64(JSON.stringify({ v: d.v })));
}
export function getBinds(el) {
  const v = el?.getAttribute?.('data-ge-bind');
  if (!v) return [];
  try { const l = JSON.parse(unb64(v)); return Array.isArray(l) ? l.filter(b => b && PROPS.includes(b.p)) : []; } catch { return []; }
}
export function setBinds(el, list) {
  if (!list?.length) { el.removeAttribute('data-ge-bind'); return; }
  el.setAttribute('data-ge-bind', b64(JSON.stringify(list)));
}
export const bound = root => [...root.querySelectorAll('[data-ge-bind]')];
export const hasData = root => !!root && (root.hasAttribute('data-ge-data') || !!root.querySelector('[data-ge-bind]'));
export const isBoundText = el => el.localName === 'text' && getBinds(el).some(b => b.p === 'text');

/** Name eines Werts aus einer Bezeichnung (klein, a–z, 0–9, _) */
export function slug(label, taken = []) {
  let s = String(label || 'wert').toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss')
    .normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 30);
  if (!/^[a-z]/.test(s)) s = 'wert_' + s;
  s = s.replace(/_+$/, '') || 'wert';
  let n = s, i = 2;
  while (taken.includes(n)) n = s + '_' + i++;
  return n;
}

export function valueLabel(name, data) {
  if (name.startsWith('@')) return STATE_LABEL[name]?.() || name;
  return data?.v?.[name]?.l || name;
}

// ---------------------------------------------------------------- Zustände für die Vorschau

/**
 * Zustände zu einer gewählten Zeit: {hour (0–24), dow (1 = Mo … 7 = So), date: 'YYYY-MM-DD'}; info aus dem Editor-Boot (Bind::forEditor):
 * Sonnenzeiten (Stunden) und Öffnungszeiten [[0 = So …, 'HH:MM', 'HH:MM']].
 */
export function statesAt(info, { hour, dow, date }) {
  const h = Math.max(0, Math.min(24, +hour || 0));
  const hm = String(Math.floor(h)).padStart(2, '0') + ':' + String(Math.round((h % 1) * 60) % 60).padStart(2, '0');
  const sun = info?.sun || [7, 19];
  const w = dow % 7;   // 0 = Sonntag wie in den Öffnungszeiten
  const open = (info?.hours || []).some(r => r[0] === w && r[1] <= hm && hm < r[2]);
  const [, mm, dd] = (date || '2026-01-01').split('-').map(Number);
  return { '@uhrzeit': Math.floor(h * 12) / 12, '@tag': h >= sun[0] && h < sun[1] ? 1 : 0, '@offen': open ? 1 : 0, '@wochentag': dow, '@monat': mm, '@datum': mm * 100 + dd };
}

// ---------------------------------------------------------------- Rechnung (wie Core\Graphics\Bind)

export function format(v, fmt = 'num', d = 0, lang = document.documentElement.lang || 'de') {
  if (v === null || v === undefined || v === '') return '–';
  if (typeof v === 'string' && !/^-?\d+(\.\d+)?$/.test(v)) return v;
  v = +v;
  const loc = lang.startsWith('en') ? 'en-GB' : 'de-DE';
  const nf = dec => new Intl.NumberFormat(loc, { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(v);
  if (fmt === 'pct') return nf(d) + ' %';
  if (fmt === 'cur') { const dec = d > 0 ? d : (v % 1 ? 2 : 0); const n = new Intl.NumberFormat(loc, { minimumFractionDigits: dec, maximumFractionDigits: dec }).format(Math.abs(v)); return (v < 0 ? '−' : '') + (loc === 'de-DE' ? n + ' €' : '€' + n); }
  if (fmt === 'time') return Math.floor(v) + ':' + String(Math.round((v % 1) * 60) % 60).padStart(2, '0');
  if (fmt === 'raw') return String(+v.toFixed(4));
  return nf(d);
}

export function ratio(v, a, clamp = true) {
  const x = Number.isFinite(+v) && v !== null && v !== '' ? +v : a[0];
  const k = a[1] === a[0] ? (x >= a[1] ? 1 : 0) : (x - a[0]) / (a[1] - a[0]);
  return clamp ? Math.max(0, Math.min(1, k)) : k;
}

export function test(v, b) {
  if (v === null || v === undefined || v === '' || !Number.isFinite(+v)) return false;
  const x = +v, X = +b.x || 0, Y = +b.y || 0;
  switch (b.op) {
    case '>': return x > X; case '>=': return x >= X; case '<': return x < X; case '<=': return x <= X;
    case '=': return Math.abs(x - X) < 1e-9; case '!=': return Math.abs(x - X) >= 1e-9;
    case 'between': return x >= Math.min(X, Y) && x <= Math.max(X, Y);
  }
  return false;
}

export function colorFor(b, v) {
  if (b.m === 'steps') {
    let c = b.b || '#000000';
    if (Number.isFinite(+v) && v !== null) for (const [at, col] of [...(b.s || [])].sort((x, y) => x[0] - y[0])) if (+v >= at) c = col;
    return c;
  }
  const k = ratio(v, b.a || [0, 100]);
  const p = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16));
  const [x, y] = [p(b.c?.[0] || b.b || '#000000'), p(b.c?.[1] || b.b || '#000000')];
  return '#' + [0, 1, 2].map(i => Math.round(x[i] + (y[i] - x[i]) * k).toString(16).padStart(2, '0')).join('');
}

function setTextNodes(el, text) {
  const nodes = [];
  const walk = n => { for (const c of n.childNodes) { if (c.nodeType === 3) nodes.push(c); else if (c.nodeType === 1 && !['title', 'desc', 'style'].includes(c.localName)) walk(c); } };
  walk(el);
  let first = null;
  for (const n of nodes) {
    if (!first && n.data.trim()) { first = n; continue; }
    if (first) n.data = '';
  }
  if (first) first.data = text; else if (el.localName === 'text') el.append(text);
}

function paint(el, prop, color) {
  const k = prop === 'fill' ? 'f' : 's';
  const list = [el, ...[...el.querySelectorAll('*')].filter(c => c.hasAttribute(prop) || (c.getAttribute('style') || '').includes(prop))];
  for (const x of list) {
    const cur = x.getAttribute(prop) || '';
    if (x !== el && (cur === 'none' || cur.startsWith('url('))) continue;
    x.setAttribute(prop, color);
    x.style?.removeProperty(prop);
    if (x.getAttribute('style') === '') x.removeAttribute('style');
    // Design-Farbe dieser Eigenschaft weicht der gebundenen Farbe
    for (const a of [prop === 'fill' ? 'data-ge-ftoken' : 'data-ge-stoken', prop === 'fill' ? 'data-ge-fdark' : 'data-ge-sdark']) x.removeAttribute(a);
    if (x.getAttribute('class')) x.setAttribute('class', x.getAttribute('class').split(/\s+/).filter(c => c && !new RegExp('^ge-[td]' + k + '-').test(c)).join(' '));
  }
}

/** Bindungen in eine Kopie der Grafik einsetzen (Vorschau) – vals: {name: Wert} */
export function applyAll(root, vals) {
  const remove = [];
  for (const el of bound(root)) {
    const pre = [];
    for (const b of getBinds(el)) {
      const v = vals[b.v];
      const a = b.a || [0, 100], o = b.o || [0, 0];
      switch (b.p) {
        case 'text': setTextNodes(el, String(b.tpl && b.tpl.includes('{v}') ? b.tpl : '{v}').replace('{v}', format(v, b.fmt || 'num', b.d || 0))); break;
        case 'w': case 'h': {
          const k = num(ratio(v, a, b.c !== 0), 5);
          pre.push(`translate(${num(o[0])} ${num(o[1])}) scale(${b.p === 'w' ? k + ' 1' : '1 ' + k}) translate(${num(-o[0])} ${num(-o[1])})`);
          break;
        }
        case 'r': { const k = ratio(v, a), g = b.g || [0, 360]; pre.unshift(`rotate(${num(g[0] + (g[1] - g[0]) * k)} ${num(o[0])} ${num(o[1])})`); break; }
        case 'o': { const k = ratio(v, a), g = b.g || [0, 1]; el.setAttribute('opacity', num(g[0] + (g[1] - g[0]) * k, 4)); el.style?.removeProperty('opacity'); break; }
        case 'fill': case 'stroke': paint(el, b.p, colorFor(b, v)); break;
        case 'show': if (!test(v, b)) remove.push(el); break;
      }
    }
    if (pre.length) el.setAttribute('transform', (pre.join(' ') + ' ' + (el.getAttribute('transform') || '')).trim());
  }
  for (const el of remove) el.remove();
  return root;
}

/** Rahmen eines Elements im Raum des Elternelements (für Anker und Drehpunkt) */
export function parentBox(el) {
  let b;
  try { b = el.getBBox(); } catch { return { x: 0, y: 0, w: 0, h: 0 }; }
  const list = el.transform?.baseVal;
  const m = list && list.numberOfItems ? list.consolidate()?.matrix : null;
  const M = m ? new DOMMatrix([m.a, m.b, m.c, m.d, m.e, m.f]) : new DOMMatrix();
  const c = [[b.x, b.y], [b.x + b.width, b.y], [b.x + b.width, b.y + b.height], [b.x, b.y + b.height]].map(([x, y]) => new DOMPoint(x, y).matrixTransform(M));
  const xs = c.map(q => q.x), ys = c.map(q => q.y);
  return { x: Math.min(...xs), y: Math.min(...ys), w: Math.max(...xs) - Math.min(...xs), h: Math.max(...ys) - Math.min(...ys) };
}

/** Anker/Drehpunkt einer Bindung aus der Lage des Elements: at = start|center|end (w: links/Mitte/rechts, h: oben/Mitte/unten) */
export function anchorOf(el, b) {
  const r = parentBox(el);
  const at = b.at || (b.p === 'h' ? 'end' : b.p === 'w' ? 'start' : 'center');
  const fx = at === 'start' ? 0 : at === 'end' ? 1 : 0.5;
  if (b.p === 'w') return [+num(r.x + r.w * fx, 3), +num(r.y + r.h / 2, 3)];
  if (b.p === 'h') return [+num(r.x + r.w / 2, 3), +num(r.y + r.h * fx, 3)];
  return [+num(r.x + r.w / 2, 3), +num(r.y + r.h / 2, 3)];
}

export { NS };
