/*
 * Grafik-Editor – Farben: CSS-Farbe lesen (über Canvas), umrechnen (Hex, RGB, HSL, HSB/HSV, OKLCH), Kontrast nach WCAG,
 * Farbwähler (Fläche + Farbton + Kanäle des gewählten Farbmodells + Deckkraft + Eingabe beliebiger CSS-Farben),
 * tastaturbedienbar (Pfeiltasten in der Fläche, Schieberegler, Zahlenfelder).
 *
 * Gespeichert wird immer sRGB (Hex, Deckkraft getrennt als fill-opacity/stop-opacity) – Desktop-Programme und die
 * SVG-Bereinigung verstehen das; OKLCH, HSL usw. sind nur Eingabehilfen.
 *
 * Farbangabe als Objekt (Andockstelle für Design-Farben als Variablen, Ausbaustufe 3b):
 *   { kind: 'value', value: '#5B73AD', alpha: 1 }                     feste Farbe
 *   { kind: 'token', value: '#581D47', token: 'accent', alpha: 1 }    Farbe des Designs (Core\Design); value = heutiger Wert
 * paintValue(…) baut so ein Objekt, panels.js merkt sich token am Element (data-ge-ftoken / data-ge-stoken, nur Arbeitsfassung). MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';

let ctx = null;
/** Beliebige CSS-Farbe → {r, g, b, a} oder null (none, url(…), currentColor ohne Wert) */
export function parseColor(v) {
  if (!v) return null;
  v = String(v).trim();
  if (v === 'none' || v === 'transparent' || v.startsWith('url(')) return null;
  // #RGB/#RGBA/#RRGGBB/#RRGGBBAA auch ohne #
  if (/^[0-9a-f]{3,4}$|^[0-9a-f]{6}$|^[0-9a-f]{8}$/i.test(v)) v = '#' + v;
  ctx ||= document.createElement('canvas').getContext('2d');
  ctx.fillStyle = '#000001';
  ctx.fillStyle = v;
  let s = ctx.fillStyle;
  if (s === '#000001' && !/^#?0+1$/.test(v)) return null;
  // neuere Farbräume (oklch(), lab(), color(…)) liefert der Canvas ggf. unverändert zurück: über ein Pixel auflösen
  if (!/^#|^rgba?\(/.test(s)) {
    ctx.clearRect(0, 0, 1, 1); ctx.fillRect(0, 0, 1, 1);
    const d = ctx.getImageData(0, 0, 1, 1).data;
    return { r: d[0], g: d[1], b: d[2], a: d[3] / 255 };
  }
  if (s.startsWith('#')) return { r: parseInt(s.slice(1, 3), 16), g: parseInt(s.slice(3, 5), 16), b: parseInt(s.slice(5, 7), 16), a: 1 };
  const m = s.match(/rgba?\(([^)]+)\)/);
  if (!m) return null;
  const p = m[1].split(/[\s,/]+/).filter(Boolean).map(x => parseFloat(x));
  return { r: p[0], g: p[1], b: p[2], a: p[3] ?? 1 };
}

const h2 = n => Math.round(Math.max(0, Math.min(255, n))).toString(16).padStart(2, '0');
export const toHex = c => c ? ('#' + h2(c.r) + h2(c.g) + h2(c.b)).toUpperCase() : null;
export const hex = v => toHex(parseColor(v));

/** Farbangabe als Objekt (siehe oben) */
export const paintValue = (value, alpha = 1, token = null) => ({ kind: token ? 'token' : 'value', value, token: token || null, alpha });

export function rgbToHsv({ r, g, b }) {
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min;
  let h = 0;
  if (d) h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;
  h = (h * 60 + 360) % 360;
  return { h, s: max ? d / max : 0, v: max };
}

export function hsvToRgb({ h, s, v }) {
  const f = n => { const k = (n + h / 60) % 6; return v - v * s * Math.max(0, Math.min(k, 4 - k, 1)); };
  return { r: f(5) * 255, g: f(3) * 255, b: f(1) * 255 };
}

export function rgbToHsl({ r, g, b }) {
  const { h } = rgbToHsv({ r, g, b });
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2;
  const s = max === min ? 0 : (max - min) / (1 - Math.abs(2 * l - 1));
  return { h, s, l };
}
export function hslToRgb({ h, s, l }) {
  const a = s * Math.min(l, 1 - l);
  const f = n => { const k = (n + h / 30) % 12; return l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1)); };
  return { r: f(0) * 255, g: f(8) * 255, b: f(4) * 255 };
}

// OKLCH (Björn Ottosson, OKLab) ↔ sRGB; außerhalb von sRGB wird die Buntheit verringert, bis die Farbe passt
const lin = c => { c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; };
const gam = c => 255 * (c <= 0.0031308 ? 12.92 * c : 1.055 * c ** (1 / 2.4) - 0.055);
export function rgbToOklch({ r, g, b }) {
  const R = lin(r), G = lin(g), B = lin(b);
  const l = Math.cbrt(0.4122214708 * R + 0.5363325363 * G + 0.0514459929 * B);
  const m = Math.cbrt(0.2119034982 * R + 0.6806995451 * G + 0.1073969566 * B);
  const s = Math.cbrt(0.0883024619 * R + 0.2817188376 * G + 0.6299787005 * B);
  const L = 0.2104542553 * l + 0.7936177850 * m - 0.0040720468 * s;
  const a = 1.9779984951 * l - 2.4285922050 * m + 0.4505937099 * s;
  const bb = 0.0259040371 * l + 0.7827717662 * m - 0.8086757660 * s;
  const C = Math.hypot(a, bb);
  return { l: L, c: C, h: C < 1e-4 ? 0 : (Math.atan2(bb, a) * 180 / Math.PI + 360) % 360 };
}
function oklchRaw({ l: L, c: C, h }) {
  const a = C * Math.cos(h * Math.PI / 180), b = C * Math.sin(h * Math.PI / 180);
  const l = (L + 0.3963377774 * a + 0.2158037573 * b) ** 3, m = (L - 0.1055613458 * a - 0.0638541728 * b) ** 3, s = (L - 0.0894841775 * a - 1.2914855480 * b) ** 3;
  return [4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s, -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s, -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s];
}
export function oklchToRgb(o) {
  let c = o.c, v = oklchRaw(o);
  for (let i = 0; i < 24 && v.some(x => x < -1e-4 || x > 1 + 1e-4); i++) { c *= 0.9; v = oklchRaw({ ...o, c }); }
  const [r, g, b] = v.map(x => gam(Math.max(0, Math.min(1, x))));
  return { r, g, b };
}

/** Relative Leuchtdichte und Kontrastverhältnis nach WCAG 2.x */
const lum = ({ r, g, b }) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
export function contrast(a, b) {
  const A = typeof a === 'string' ? parseColor(a) : a, B = typeof b === 'string' ? parseColor(b) : b;
  if (!A || !B) return null;
  const x = lum(A), y = lum(B);
  return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
}
/** Farbe mit Deckkraft über einem Hintergrund (für den Kontrast) */
export const over = (c, a, bg) => ({ r: c.r * a + bg.r * (1 - a), g: c.g * a + bg.g * (1 - a), b: c.b * a + bg.b * (1 - a) });

/** Kontrast-taugliche Textfarbe auf einer Farbe (für Farbfelder) */
export const onColor = c => { const p = parseColor(c); if (!p) return '#000'; return (p.r * 299 + p.g * 587 + p.b * 114) / 1000 > 150 ? '#000' : '#fff'; };

/** Farbmodelle: Kanäle [Schlüssel, Bezeichnung, min, max, Schritt, Einheit] */
export const MODES = {
  hex: { label: 'Hex', ch: [] },
  rgb: { label: 'RGB', ch: [['r', 'R', 0, 255, 1, ''], ['g', 'G', 0, 255, 1, ''], ['b', 'B', 0, 255, 1, '']] },
  hsl: { label: 'HSL', ch: [['h', 'H', 0, 360, 1, '°'], ['s', 'S', 0, 100, 1, '%'], ['l', 'L', 0, 100, 1, '%']] },
  hsb: { label: 'HSB', ch: [['h', 'H', 0, 360, 1, '°'], ['s', 'S', 0, 100, 1, '%'], ['v', 'B', 0, 100, 1, '%']] },
  oklch: { label: 'OKLCH', ch: [['l', 'L', 0, 100, 0.5, '%'], ['c', 'C', 0, 0.37, 0.005, ''], ['h', 'H', 0, 360, 1, '°']] },
};
const CH_NAME = { rgb: { r: () => t('Rot'), g: () => t('Grün'), b: () => t('Blau') }, hsl: { h: () => t('Farbton'), s: () => t('Sättigung'), l: () => t('Helligkeit') },
  hsb: { h: () => t('Farbton'), s: () => t('Sättigung'), v: () => t('Hellwert') }, oklch: { l: () => t('Helligkeit'), c: () => t('Buntheit'), h: () => t('Farbton') } };

/** Kanalwerte eines Modells aus RGB (Anzeigewerte: Prozent als 0–100) */
function channels(mode, rgb, hsv) {
  if (mode === 'rgb') return { r: rgb.r, g: rgb.g, b: rgb.b };
  if (mode === 'hsl') { const x = rgbToHsl(rgb); return { h: hsv.h, s: x.s * 100, l: x.l * 100 }; }
  if (mode === 'hsb') return { h: hsv.h, s: hsv.s * 100, v: hsv.v * 100 };
  if (mode === 'oklch') { const x = rgbToOklch(rgb); return { l: x.l * 100, c: x.c, h: x.h }; }
  return {};
}
/** Kanalwerte → RGB */
function fromChannels(mode, v) {
  if (mode === 'rgb') return { r: v.r, g: v.g, b: v.b };
  if (mode === 'hsl') return hslToRgb({ h: v.h, s: v.s / 100, l: v.l / 100 });
  if (mode === 'hsb') return hsvToRgb({ h: v.h, s: v.s / 100, v: v.v / 100 });
  if (mode === 'oklch') return oklchToRgb({ l: v.l / 100, c: v.c, h: v.h });
  return null;
}
const fmt = (v, step) => step < 1 ? String(Math.round(v / step) * step).replace(/(\.\d{3})\d+$/, '$1') : String(Math.round(v));

let seq = 0;
/**
 * Farbwähler in ein Element einbauen. opts.onInput(hex, alpha) bei jeder Änderung, opts.onChange(hex, alpha) am Ende
 * (Loslassen, Enter) – für einen Schritt im Protokoll. opts.mode (hex|rgb|hsl|hsb|oklch), opts.onMode(mode) beim Umschalten.
 * Rückgabe: { set(color, alpha), get(), setMode(m), contrast(info|null), el }
 */
export function colorPicker(host, opts = {}) {
  const id = 'gecp' + (++seq);
  let hsv = { h: 0, s: 0, v: 0 }, alpha = 1, mode = MODES[opts.mode] ? opts.mode : 'hex';
  // Kanalwerte des Modells, solange dort eingegeben wird (eingegebene Werte bleiben, auch wenn sRGB sie nicht ganz trifft –
  // z. B. hohe OKLCH-Buntheit); jede Änderung von außen (Fläche, Farbton, Eingabe, set) rechnet sie neu
  let vals = null;
  host.innerHTML = `<div class="ge-cp">
    <div class="ge-cp__sv" tabindex="0" role="slider" aria-label="${esc(t('Sättigung und Helligkeit'))}" aria-valuetext=""><span class="ge-cp__knob"></span></div>
    <label class="ge-cp__row"><span>${esc(t('Farbton'))}</span><input type="range" class="ge-cp__hue" min="0" max="359" step="1"></label>
    <div class="ge-seg ge-seg--wide ge-cp__modes" role="radiogroup" aria-label="${esc(t('Farbmodell'))}">${Object.entries(MODES).map(([k, m]) => `<button type="button" role="radio" data-cm="${k}" aria-checked="${k === mode}" tabindex="${k === mode ? 0 : -1}">${esc(m.label)}</button>`).join('')}</div>
    <div class="ge-cp__chans" data-chans></div>
    <div class="ge-cp__row"><label for="${id}-a">${esc(t('Deckkraft'))}</label><input type="range" class="ge-cp__alpha" id="${id}-a" min="0" max="100" step="1"><span class="ge-cp__num"><input type="number" class="ge-in ge-in--xs" data-an min="0" max="100" step="1" aria-label="${esc(t('Deckkraft in Prozent'))}">%</span></div>
    <div class="ge-cp__row ge-cp__hexrow"><label for="${id}-h">${esc(t('Farbe'))}</label><input class="ge-in ge-cp__hex" id="${id}-h" spellcheck="false" autocomplete="off" title="${esc(t('Hex, rgb(), hsl(), oklch() oder Farbname – wird umgerechnet'))}"><span class="ge-cp__prev" aria-hidden="true"></span></div>
    <p class="ge-cp__contrast" data-contrast hidden></p>
  </div>`;
  const q = s => host.querySelector(s);
  const sv = q('.ge-cp__sv'), knob = q('.ge-cp__knob'), hue = q('.ge-cp__hue'), al = q('.ge-cp__alpha'), an = q('[data-an]'), hx = q('.ge-cp__hex'), prev = q('.ge-cp__prev'), chans = q('[data-chans]');
  const rgb = () => hsvToRgb(hsv);
  const cur = () => toHex(rgb());

  function buildChans() {
    const m = MODES[mode];
    chans.hidden = !m.ch.length;
    chans.innerHTML = m.ch.map(([k, lbl, mi, ma, st, unit]) => `<div class="ge-cp__row ge-cp__ch"><label for="${id}-${k}" title="${esc(CH_NAME[mode]?.[k]?.() || lbl)}">${esc(lbl)}</label>
      <input type="range" class="ge-range ge-cp__chr" id="${id}-${k}" data-ch="${k}" min="${mi}" max="${ma}" step="${st}" aria-label="${esc(CH_NAME[mode]?.[k]?.() || lbl)}">
      <span class="ge-cp__num"><input type="number" class="ge-in ge-in--xs" data-chn="${k}" min="${mi}" max="${ma}" step="${st}" aria-label="${esc((CH_NAME[mode]?.[k]?.() || lbl) + (unit ? ' (' + unit + ')' : ''))}">${esc(unit)}</span></div>`).join('');
    chans.querySelectorAll('[data-ch]').forEach(r => {
      r.addEventListener('input', () => fromChan(r.dataset.ch, +r.value, false));
      r.addEventListener('change', () => emit(true));
    });
    chans.querySelectorAll('[data-chn]').forEach(n => {
      n.addEventListener('change', () => { if (n.value !== '' && Number.isFinite(+n.value)) fromChan(n.dataset.chn, Math.max(+n.min, Math.min(+n.max, +n.value)), true); else paint(); });
      n.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); n.dispatchEvent(new Event('change')); } e.stopPropagation(); });
    });
  }
  /** Kanal geändert: übrige Kanäle des Modells bleiben, Ergebnis über RGB zurück in HSV (Fläche, Farbton) */
  function fromChan(k, v, end) {
    vals = { ...(vals || channels(mode, rgb(), hsv)), [k]: v };
    const c = fromChannels(mode, vals);
    if (!c) return;
    const n = rgbToHsv(c);
    // Farbton bleibt bei Grau bzw. Schwarz erhalten (sonst springt der Regler auf 0)
    if (n.s === 0 || n.v === 0) n.h = vals.h ?? hsv.h;
    else if ((mode === 'hsl' || mode === 'hsb') && 'h' in vals) n.h = vals.h % 360;
    hsv = n;
    paint(true);
    emit(end);
  }

  /** keepVals: Kanalwerte stammen aus der Eingabe (nicht aus RGB zurückrechnen) */
  function paint(keepVals = false) {
    if (!keepVals) vals = null;
    sv.style.setProperty('--h', hsv.h);
    knob.style.left = (hsv.s * 100) + '%'; knob.style.top = ((1 - hsv.v) * 100) + '%';
    hue.value = Math.round(hsv.h); al.value = Math.round(alpha * 100);
    if (document.activeElement !== an) an.value = Math.round(alpha * 100);
    if (document.activeElement !== hx) hx.value = cur();
    prev.style.setProperty('--c', cur());
    prev.style.setProperty('--a', alpha);
    al.style.setProperty('--c', cur());
    sv.setAttribute('aria-valuetext', `${cur()} – ${t('Sättigung')} ${Math.round(hsv.s * 100)} %, ${t('Helligkeit')} ${Math.round(hsv.v * 100)} %`);
    const cv = vals || channels(mode, rgb(), hsv);
    for (const [k, , , , st] of MODES[mode].ch) {
      const r = chans.querySelector(`[data-ch="${k}"]`), n = chans.querySelector(`[data-chn="${k}"]`);
      if (r) r.value = cv[k];
      if (n && document.activeElement !== n) n.value = fmt(cv[k], st);
    }
  }
  const emit = (end) => { (end ? opts.onChange : opts.onInput)?.(cur(), alpha); };
  const fromEvent = (e) => {
    const r = sv.getBoundingClientRect();
    hsv.s = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
    hsv.v = 1 - Math.max(0, Math.min(1, (e.clientY - r.top) / r.height));
    paint(); emit(false);
  };
  sv.addEventListener('pointerdown', e => {
    sv.setPointerCapture(e.pointerId); fromEvent(e);
    const mv = ev => fromEvent(ev);
    const up = () => { sv.removeEventListener('pointermove', mv); sv.removeEventListener('pointerup', up); emit(true); };
    sv.addEventListener('pointermove', mv); sv.addEventListener('pointerup', up);
  });
  sv.addEventListener('keydown', e => {
    const st = e.shiftKey ? 0.1 : 0.02;
    const k = { ArrowLeft: ['s', -st], ArrowRight: ['s', st], ArrowUp: ['v', st], ArrowDown: ['v', -st] }[e.key];
    if (!k) return;
    e.preventDefault(); e.stopPropagation();
    hsv[k[0]] = Math.max(0, Math.min(1, hsv[k[0]] + k[1])); paint(); emit(true);
  });
  hue.addEventListener('input', () => { hsv.h = +hue.value; paint(); emit(false); });
  hue.addEventListener('change', () => emit(true));
  al.addEventListener('input', () => { alpha = +al.value / 100; paint(); emit(false); });
  al.addEventListener('change', () => emit(true));
  an.addEventListener('change', () => { alpha = Math.max(0, Math.min(100, +an.value || 0)) / 100; paint(); emit(true); });
  // beliebige CSS-Farbangabe: Hex (auch #RGBA/#RRGGBBAA), rgb()/rgba(), hsl()/hsla(), oklch(), Farbnamen; Alpha wird Deckkraft
  hx.addEventListener('change', () => {
    const c = parseColor(hx.value);
    if (c) { const n = rgbToHsv(c); if (n.s === 0 || n.v === 0) n.h = hsv.h; hsv = n; if (c.a < 1 || /^#?([0-9a-f]{4}|[0-9a-f]{8})$|rgba|hsla|\//i.test(hx.value.trim())) alpha = c.a; hx.value = cur(); paint(); emit(true); }
    else hx.value = cur();
  });
  for (const i of [hx, an]) i.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); i.dispatchEvent(new Event('change')); } e.stopPropagation(); });
  // Farbmodell umschalten (Pfeiltasten wie eine Radiogruppe)
  const modeBtns = [...host.querySelectorAll('[data-cm]')];
  const setMode = (m, focus = false) => {
    if (!MODES[m]) return;
    mode = m;
    modeBtns.forEach(b => { const on = b.dataset.cm === m; b.setAttribute('aria-checked', String(on)); b.tabIndex = on ? 0 : -1; if (on && focus) b.focus(); });
    buildChans(); paint();
  };
  modeBtns.forEach((b, i) => {
    b.addEventListener('click', () => { setMode(b.dataset.cm); opts.onMode?.(mode); });
    b.addEventListener('keydown', e => {
      const d = e.key === 'ArrowRight' || e.key === 'ArrowDown' ? 1 : e.key === 'ArrowLeft' || e.key === 'ArrowUp' ? -1 : 0;
      if (!d) return;
      e.preventDefault(); e.stopPropagation();
      setMode(modeBtns[(i + d + modeBtns.length) % modeBtns.length].dataset.cm, true); opts.onMode?.(mode);
    });
  });
  buildChans();
  const api = {
    el: host,
    set(color, a = 1) {
      const c = parseColor(color);
      // dieselbe Farbe (Rückmeldung nach eigener Eingabe): eingegebene Kanalwerte bleiben stehen
      const same = c && toHex(c) === cur();
      if (c && !same) { const n = rgbToHsv(c); if (n.s === 0 || n.v === 0) n.h = hsv.h; hsv = n; }
      alpha = Math.max(0, Math.min(1, +a || 0));
      paint(!!same && !!vals);
    },
    get: () => ({ hex: cur(), alpha, mode }),
    setMode,
    get mode() { return mode; },
    /** Kontrast anzeigen: { ratio, bg, large } oder null */
    contrast(info) {
      const p = q('[data-contrast]');
      p.hidden = !info;
      if (!info) { p.innerHTML = ''; return; }
      const r = info.ratio, need = info.large ? 3 : 4.5;
      const lvl = r >= (info.large ? 4.5 : 7) ? 'AAA' : r >= need ? 'AA' : '';
      p.className = 'ge-cp__contrast ' + (lvl ? 'is-ok' : 'is-low');
      p.innerHTML = `<span class="ge-cp__bg" style="--c:${esc(info.bg)}" aria-hidden="true"></span><span>${esc(t('Kontrast zum Hintergrund'))}</span> <b>${esc(r.toFixed(2).replace('.', document.documentElement.lang === 'en' ? '.' : ','))} : 1</b> <span class="ge-cp__lvl">${esc(lvl || t('zu gering'))}</span>`;
      p.title = info.large ? t('Großer Text: mindestens 3 : 1 (AA), 4,5 : 1 (AAA)') : t('Text: mindestens 4,5 : 1 (AA), 7 : 1 (AAA)');
    },
  };
  paint();
  return api;
}
