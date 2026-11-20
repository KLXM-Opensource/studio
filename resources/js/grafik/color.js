/*
 * Grafik-Editor – Farben: CSS-Farbe lesen (über Canvas), Hex/HSV umrechnen, Farbwähler (Fläche + Farbton + Deckkraft + Hex),
 * tastaturbedienbar (Pfeiltasten in der Fläche, Schieberegler). MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';

let ctx = null;
/** Beliebige CSS-Farbe → {r, g, b, a} oder null (none, url(…), currentColor ohne Wert) */
export function parseColor(v) {
  if (!v) return null;
  v = String(v).trim();
  if (v === 'none' || v === 'transparent' || v.startsWith('url(')) return null;
  ctx ||= document.createElement('canvas').getContext('2d');
  ctx.fillStyle = '#000001';
  ctx.fillStyle = v;
  const s = ctx.fillStyle;
  if (s === '#000001' && !/^#?0+1$/.test(v)) return null;
  if (s.startsWith('#')) return { r: parseInt(s.slice(1, 3), 16), g: parseInt(s.slice(3, 5), 16), b: parseInt(s.slice(5, 7), 16), a: 1 };
  const m = s.match(/rgba?\(([^)]+)\)/);
  if (!m) return null;
  const p = m[1].split(',').map(x => parseFloat(x));
  return { r: p[0], g: p[1], b: p[2], a: p[3] ?? 1 };
}

const h2 = n => Math.round(Math.max(0, Math.min(255, n))).toString(16).padStart(2, '0');
export const toHex = c => c ? ('#' + h2(c.r) + h2(c.g) + h2(c.b)).toUpperCase() : null;
export const hex = v => toHex(parseColor(v));

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

/** Kontrast-taugliche Textfarbe auf einer Farbe (für Farbfelder) */
export const onColor = c => { const p = parseColor(c); if (!p) return '#000'; return (p.r * 299 + p.g * 587 + p.b * 114) / 1000 > 150 ? '#000' : '#fff'; };

/**
 * Farbwähler in ein Element einbauen. opts.onInput(hex, alpha) bei jeder Änderung, opts.onChange(hex, alpha) am Ende
 * (Loslassen, Enter) – für einen Schritt im Protokoll. Rückgabe: { set(hex, alpha), el }
 */
export function colorPicker(host, opts = {}) {
  let hsv = { h: 0, s: 0, v: 0 }, alpha = 1;
  host.innerHTML = `<div class="ge-cp">
    <div class="ge-cp__sv" tabindex="0" role="slider" aria-label="${esc(t('Sättigung und Helligkeit'))}" aria-valuetext=""><span class="ge-cp__knob"></span></div>
    <label class="ge-cp__row"><span>${esc(t('Farbton'))}</span><input type="range" class="ge-cp__hue" min="0" max="359" step="1"></label>
    <label class="ge-cp__row"><span>${esc(t('Deckkraft'))}</span><input type="range" class="ge-cp__alpha" min="0" max="100" step="1"><output class="ge-cp__ao"></output></label>
    <label class="ge-cp__row ge-cp__hexrow"><span>Hex</span><input class="ge-cp__hex" maxlength="7" spellcheck="false" autocomplete="off"><span class="ge-cp__prev" aria-hidden="true"></span></label>
  </div>`;
  const sv = host.querySelector('.ge-cp__sv'), knob = host.querySelector('.ge-cp__knob'), hue = host.querySelector('.ge-cp__hue'),
    al = host.querySelector('.ge-cp__alpha'), ao = host.querySelector('.ge-cp__ao'), hx = host.querySelector('.ge-cp__hex'), prev = host.querySelector('.ge-cp__prev');
  const cur = () => toHex(hsvToRgb(hsv));
  const paint = () => {
    sv.style.setProperty('--h', hsv.h);
    knob.style.left = (hsv.s * 100) + '%'; knob.style.top = ((1 - hsv.v) * 100) + '%';
    hue.value = Math.round(hsv.h); al.value = Math.round(alpha * 100); ao.textContent = Math.round(alpha * 100) + ' %';
    if (document.activeElement !== hx) hx.value = cur();
    prev.style.background = cur();
    al.style.setProperty('--c', cur());
    sv.setAttribute('aria-valuetext', `${cur()} – ${t('Sättigung')} ${Math.round(hsv.s * 100)} %, ${t('Helligkeit')} ${Math.round(hsv.v * 100)} %`);
  };
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
  hx.addEventListener('change', () => { const c = parseColor(hx.value); if (c) { hsv = rgbToHsv(c); paint(); emit(true); } else hx.value = cur(); });
  hx.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); hx.dispatchEvent(new Event('change')); } });
  const api = {
    el: host,
    set(color, a = 1) {
      const c = parseColor(color);
      if (c) { const n = rgbToHsv(c); if (n.s === 0 || n.v === 0) n.h = hsv.h; hsv = n; }
      alpha = Math.max(0, Math.min(1, +a || 0));
      paint();
    },
    get: () => ({ hex: cur(), alpha }),
  };
  paint();
  return api;
}
