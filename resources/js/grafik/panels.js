/*
 * Grafik-Editor – Bereiche rechts (Reiter wie in Vektorprogrammen):
 *   Farbe | Farbfelder | Kontur | Darstellung
 *   Ebenen | Bibliothek | Symbole
 *   Transformieren | Protokoll
 * Änderungen beim Ziehen eines Reglers wirken sofort, ein Schritt im Protokoll entsteht beim Loslassen (change). MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico, spriteUrl } from '../_icons.js';
import { toast } from '../_toast.js';
import { NS, num, pt, decompose, docBounds } from './geom.js';
import {
  drawnChildren, isDrawn, nameOf, typeLabel, isLocked, isHidden, getProp, setProp, eachShape, defs, uid, refOf, useCount,
  gradientStops, writeStops, setAngle, getAngle, applyTransform, remapIds, paintEl,
} from './doc.js';
import { colorPicker, parseColor, toHex, hex, onColor } from './color.js';
import { h, tabGroup, iconBtn, modal } from './ui.js';
import { clipOf } from './clip.js';
import { buildTextPanel } from './textpanel.js';

const BLEND = ['normal', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'color-burn', 'hard-light', 'soft-light', 'difference', 'exclusion', 'hue', 'saturation', 'color', 'luminosity'];
const blendLabel = b => ({ normal: t('Normal'), multiply: t('Multiplizieren'), screen: t('Negativ multiplizieren'), overlay: t('Ineinanderkopieren'), darken: t('Abdunkeln'), lighten: t('Aufhellen'),
  'color-dodge': t('Farbig abwedeln'), 'color-burn': t('Farbig nachbelichten'), 'hard-light': t('Hartes Licht'), 'soft-light': t('Weiches Licht'), difference: t('Differenz'),
  exclusion: t('Ausschluss'), hue: t('Farbton'), saturation: t('Sättigung'), color: t('Farbe'), luminosity: t('Luminanz') }[b] || b);

export function buildPanels(ed) {
  const A = tabGroup(ed.uid + '-a', [{ id: 'color', label: t('Farbe') }, { id: 'swatches', label: t('Farbfelder') }, { id: 'stroke', label: t('Kontur') }, { id: 'look', label: t('Darstellung') }], () => api.refresh());
  const B = tabGroup(ed.uid + '-b', [{ id: 'layers', label: t('Ebenen') }, { id: 'lib', label: t('Bibliothek') }, { id: 'icons', label: t('Symbole') }], () => api.refresh());
  const C = tabGroup(ed.uid + '-c', [{ id: 'transform', label: t('Transformieren') }, { id: 'history', label: t('Protokoll') }], () => api.refresh());
  const TP = buildTextPanel(ed);
  A.el.classList.add('ge-pg--color'); B.el.classList.add('ge-pg--layers'); C.el.classList.add('ge-pg--transform');
  let lock = false, anchor = 'c';
  const expanded = new Set();     // geöffnete Gruppen (Pfad als Text)
  let stopIdx = 0;                // gewählter Verlaufs-Stopp
  let libBuilt = false, iconsBuilt = false;

  // ------------------------------------------------------------------ Hilfen
  const sel = () => ed.sel;
  const first = () => ed.sel[0] || null;
  const target = () => ed.style.target;              // 'fill' | 'stroke'
  const live = (fn) => { fn(); ed.draw(); ed.paintFs(); };
  const commit = (label) => ed.commit(label);
  const rememberColor = c => { if (!c || c === 'none' || /^url/.test(c)) return; ed.recent = [c, ...ed.recent.filter(x => x !== c)].slice(0, 14); };

  /** Füllung/Kontur der Auswahl setzen (bei Gruppen auf die Formen darin); ohne Auswahl: Vorgabe für neue Formen */
  function setPaint(which, value, alpha = 1) {
    if (!sel().length) { ed.style[which] = value; ed.paintFs(); return; }
    // Text wird bearbeitet und ein Bereich ist markiert: Farbe nur für diese Zeichen
    if (which === 'fill' && ed.textEd && alpha >= 1 && !/^url/.test(value) && ed.textEd.format({ fill: value })) return;
    for (const el of sel()) eachShape(el, s => {
      setProp(s, which, value);
      setProp(s, which + '-opacity', alpha < 1 ? num(alpha, 3) : null);
      if (which === 'stroke' && value !== 'none' && !getProp(s, 'stroke-width', false) && getProp(s, 'stroke-width') === '1' && ed.format !== 'icon') setProp(s, 'stroke-width', num(ed.style.strokeWidth));
      if (which === 'stroke' && (getProp(s, 'marker-end', false) || getProp(s, 'marker-start', false))) ed.applyArrows(s, !!getProp(s, 'marker-start', false), !!getProp(s, 'marker-end', false));
    });
  }

  /** Aktueller Wert: {mode: none|solid|linear|radial, color, alpha, grad} */
  function paintOf(which) {
    const el = paintEl(first());
    const v = el ? getProp(el, which) : ed.style[which];
    if (!v || v === 'none' || v === 'transparent') return { mode: 'none' };
    if (/^url/.test(v)) {
      const g = el ? refOf(ed.root, v) : null;
      if (g && (g.localName === 'linearGradient' || g.localName === 'radialGradient')) return { mode: g.localName === 'linearGradient' ? 'linear' : 'radial', grad: g };
      return { mode: 'other' };
    }
    if (v === 'currentColor' || v === 'currentcolor') return { mode: 'solid', color: 'currentColor', alpha: 1 };
    const c = parseColor(v);
    const op = el ? parseFloat(getProp(el, which + '-opacity') || '1') : 1;
    return c ? { mode: 'solid', color: toHex(c), alpha: (Number.isFinite(op) ? op : 1) * c.a } : { mode: 'none' };
  }

  /** Verlauf der Auswahl nur für die Auswahl (andere Verwendungen bekommen ihn nicht mit geändert) */
  function ownGradient(g) {
    const others = useCount(ed.root, g.id);
    const mine = sel().length;
    if (others <= mine) return g;
    const c = g.cloneNode(true);
    c.id = uid(ed.root, 'verlauf-');
    const stops = gradientStops(ed.root, g);
    if (!c.querySelector('stop')) writeStops(c, stops);
    c.removeAttribute('href');
    g.after(c);
    for (const el of sel()) eachShape(el, s => setProp(s, target(), `url(#${c.id})`));
    return c;
  }

  function newGradient(kind, base) {
    const g = document.createElementNS(NS, kind === 'linear' ? 'linearGradient' : 'radialGradient');
    g.id = uid(ed.root, 'verlauf-');
    const c1 = base && base !== 'currentColor' ? base : '#5B73AD';
    writeStops(g, [{ offset: 0, color: c1, opacity: 1 }, { offset: 1, color: '#FFFFFF', opacity: 1 }]);
    if (kind === 'linear') setAngle(g, 0);
    defs(ed.root).append(g);
    return g;
  }

  // ================================================================= Farbe
  const colorPanel = A.panel('color');
  colorPanel.innerHTML = `<div class="ge-paint">
      <div class="ge-seg ge-seg--wide" role="radiogroup" aria-label="${esc(t('Bearbeitet'))}" data-which>
        <button type="button" role="radio" data-w="fill">${esc(t('Füllung'))}</button><button type="button" role="radio" data-w="stroke">${esc(t('Kontur'))}</button></div>
      <div class="ge-seg ge-seg--wide" role="radiogroup" aria-label="${esc(t('Art'))}" data-mode>
        <button type="button" role="radio" data-m="none">${esc(t('Keine'))}</button><button type="button" role="radio" data-m="solid">${esc(t('Farbe'))}</button>
        <button type="button" role="radio" data-m="linear">${esc(t('Linear'))}</button><button type="button" role="radio" data-m="radial">${esc(t('Radial'))}</button></div>
      <div class="ge-grad" data-grad hidden>
        <div class="ge-grad__bar" data-gbar title="${esc(t('Klick = Farbstopp hinzufügen'))}"></div>
        <div class="ge-grad__row"><label class="ge-lbl">${esc(t('Winkel'))} <input type="number" class="ge-in ge-in--s" data-gangle min="-360" max="360" step="15"></label>
          <label class="ge-lbl">${esc(t('Position'))} <input type="number" class="ge-in ge-in--s" data-goff min="0" max="100" step="1"> %</label>
          ${iconBtn('trash', t('Farbstopp entfernen'), 'data-gdel')}</div>
      </div>
      <div data-cp></div>
      <p class="ge-note" data-cnote hidden></p>
    </div>`;
  const cp = colorPicker(colorPanel.querySelector('[data-cp]'), {
    onInput: (c, a) => live(() => applyColor(c, a)),
    onChange: (c, a) => { applyColor(c, a); rememberColor(c); commit(target() === 'fill' ? t('Füllung') : t('Kontur')); },
  });
  function applyColor(c, a) {
    const p = paintOf(target());
    if (p.mode === 'linear' || p.mode === 'radial') {
      const g = ownGradient(p.grad);
      const stops = gradientStops(ed.root, g);
      if (!stops[stopIdx]) return;
      stops[stopIdx].color = c; stops[stopIdx].opacity = a;
      writeStops(g, stops);
      paintGradBar();
      return;
    }
    setPaint(target(), c, a);
  }
  colorPanel.querySelectorAll('[data-w]').forEach(b => b.addEventListener('click', () => { ed.style.target = b.dataset.w; ed.paintFs(); api.refresh(); }));
  colorPanel.querySelectorAll('[data-m]').forEach(b => b.addEventListener('click', () => {
    const m = b.dataset.m, p = paintOf(target()), w = target();
    if (m === p.mode) return;
    if (m === 'none') setPaint(w, 'none');
    else if (m === 'solid') setPaint(w, p.mode === 'solid' ? p.color : (p.grad ? gradientStops(ed.root, p.grad)[0]?.color : null) || ed.recent[0] || '#5B73AD');
    else {
      if (!sel().length) { toast(t('Verläufe gelten für ausgewählte Objekte – bitte zuerst etwas auswählen.'), 'info'); return; }
      let g;
      if (p.grad) {
        // linear ↔ radial: Stopps übernehmen
        g = newGradient(m, null);
        writeStops(g, gradientStops(ed.root, p.grad));
      } else g = newGradient(m, p.color);
      stopIdx = 0;
      setPaint(w, `url(#${g.id})`);
    }
    commit(t('Farbart geändert'));
  }));
  const gbar = colorPanel.querySelector('[data-gbar]');
  function paintGradBar() {
    const p = paintOf(target());
    if (!p.grad) return;
    const stops = gradientStops(ed.root, p.grad);
    gbar.style.setProperty('--g', `linear-gradient(90deg, ${stops.map(s => { const c = parseColor(s.color); return `rgba(${c.r},${c.g},${c.b},${s.opacity}) ${num(s.offset * 100, 1)}%`; }).join(', ')})`);
    gbar.innerHTML = stops.map((s, i) => `<button type="button" class="ge-grad__stop${i === stopIdx ? ' is-on' : ''}" data-si="${i}" style="left:${s.offset * 100}%;--c:${esc(s.color)}" aria-label="${esc(t('Farbstopp {n}: {c}', { n: i + 1, c: s.color }))}" aria-pressed="${i === stopIdx}"></button>`).join('');
    gbar.querySelectorAll('[data-si]').forEach(b => {
      b.addEventListener('click', e => { e.stopPropagation(); stopIdx = +b.dataset.si; api.refresh(); });
      b.addEventListener('pointerdown', e => {
        e.stopPropagation();
        const i = +b.dataset.si, r = gbar.getBoundingClientRect();
        stopIdx = i;
        const g = ownGradient(paintOf(target()).grad);
        b.setPointerCapture(e.pointerId);
        let moved = false;
        const mv = ev => {
          moved = true;
          const st = gradientStops(ed.root, g);
          st[i].offset = Math.max(0, Math.min(1, (ev.clientX - r.left) / r.width));
          writeStops(g, st.map((s, k) => ({ ...s, _k: k })));
          b.style.left = st[i].offset * 100 + '%';
          ed.draw();
        };
        const up = () => { b.removeEventListener('pointermove', mv); b.removeEventListener('pointerup', up); if (moved) { stopIdx = gradientStops(ed.root, g).findIndex((s, k) => k === i) ; commit(t('Verlauf geändert')); } };
        b.addEventListener('pointermove', mv); b.addEventListener('pointerup', up);
      });
      b.addEventListener('keydown', e => {
        const d = e.key === 'ArrowRight' ? 0.01 : e.key === 'ArrowLeft' ? -0.01 : 0;
        if (!d) return;
        e.preventDefault(); e.stopPropagation();
        const g = ownGradient(paintOf(target()).grad), st = gradientStops(ed.root, g), i = +b.dataset.si;
        st[i].offset = Math.max(0, Math.min(1, st[i].offset + d * (e.shiftKey ? 10 : 1)));
        writeStops(g, st); commit(t('Verlauf geändert'));
      });
    });
  }
  gbar.addEventListener('click', e => {
    const p = paintOf(target());
    if (!p.grad) return;
    const r = gbar.getBoundingClientRect(), off = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width));
    const g = ownGradient(p.grad), st = gradientStops(ed.root, g);
    const c = st.reduce((best, s) => Math.abs(s.offset - off) < Math.abs(best.offset - off) ? s : best, st[0] || { color: '#000000', opacity: 1 });
    st.push({ offset: off, color: c.color, opacity: c.opacity });
    st.sort((a, b) => a.offset - b.offset);
    stopIdx = st.findIndex(s => s.offset === off);
    writeStops(g, st);
    commit(t('Farbstopp hinzugefügt'));
  });
  colorPanel.querySelector('[data-gangle]').addEventListener('change', e => {
    const p = paintOf(target());
    if (p.mode !== 'linear') return;
    const g = ownGradient(p.grad);
    if (g.getAttribute('gradientUnits') === 'userSpaceOnUse') { toast(t('Dieser Verlauf ist an feste Koordinaten gebunden – Winkel bitte im Ursprungsprogramm ändern.'), 'info'); return; }
    setAngle(g, +e.target.value || 0);
    commit(t('Verlauf gedreht'));
  });
  colorPanel.querySelector('[data-goff]').addEventListener('change', e => {
    const p = paintOf(target());
    if (!p.grad) return;
    const g = ownGradient(p.grad), st = gradientStops(ed.root, g);
    if (!st[stopIdx]) return;
    st[stopIdx].offset = Math.max(0, Math.min(1, (+e.target.value || 0) / 100));
    writeStops(g, st); commit(t('Verlauf geändert'));
  });
  colorPanel.querySelector('[data-gdel]').addEventListener('click', () => {
    const p = paintOf(target());
    if (!p.grad) return;
    const g = ownGradient(p.grad), st = gradientStops(ed.root, g);
    if (st.length <= 2) { toast(t('Ein Verlauf braucht mindestens zwei Farbstopps.'), 'info'); return; }
    st.splice(stopIdx, 1); stopIdx = Math.max(0, stopIdx - 1);
    writeStops(g, st); commit(t('Farbstopp entfernt'));
  });

  function refreshColor() {
    const w = target(), p = paintOf(w);
    colorPanel.querySelectorAll('[data-w]').forEach(b => b.setAttribute('aria-checked', String(b.dataset.w === w)));
    colorPanel.querySelectorAll('[data-m]').forEach(b => b.setAttribute('aria-checked', String(b.dataset.m === p.mode)));
    const grad = colorPanel.querySelector('[data-grad]'), note = colorPanel.querySelector('[data-cnote]');
    grad.hidden = !(p.mode === 'linear' || p.mode === 'radial');
    note.hidden = true;
    cp.el.hidden = p.mode === 'none' || p.mode === 'other';
    if (p.mode === 'solid') cp.set(p.color === 'currentColor' ? '#1F2430' : p.color, p.alpha);
    if (p.mode === 'solid' && p.color === 'currentColor') { note.hidden = false; note.textContent = t('Schriftfarbe (currentColor): Die Grafik übernimmt die Textfarbe der Umgebung.'); }
    if (p.mode === 'other') { note.hidden = false; note.textContent = t('Muster oder unbekannte Füllung – bitte Farbe oder Verlauf wählen, um sie zu ersetzen.'); }
    if (p.grad) {
      const stops = gradientStops(ed.root, p.grad);
      if (stopIdx >= stops.length) stopIdx = 0;
      const s = stops[stopIdx];
      if (s) cp.set(s.color, s.opacity);
      colorPanel.querySelector('[data-gangle]').value = getAngle(p.grad);
      colorPanel.querySelector('[data-gangle]').closest('label').hidden = p.mode !== 'linear';
      colorPanel.querySelector('[data-goff]').value = Math.round((s?.offset || 0) * 100);
      paintGradBar();
    }
  }

  // ================================================================= Farbfelder
  const swPanel = A.panel('swatches');
  function refreshSwatches() {
    const docColors = new Set();
    for (const el of ed.root.querySelectorAll('[fill],[stroke],[stop-color],[style]')) {
      for (const a of ['fill', 'stroke', 'stop-color']) { const v = hex(getProp(el, a, false)); if (v) docColors.add(v); }
      if (docColors.size > 40) break;
    }
    const sw = (c, name) => `<button type="button" class="ge-sw" data-c="${esc(c)}" style="--c:${esc(c === 'currentColor' ? '#1F2430' : c)};--on:${onColor(c === 'currentColor' ? '#1F2430' : c)}" aria-label="${esc((name ? name + ': ' : '') + c)}" title="${esc((name ? name + ' – ' : '') + c)}">${c === 'currentColor' ? 'A' : ''}</button>`;
    const block = (title, items) => items.length ? `<h3 class="ge-h3">${esc(title)}</h3><div class="ge-sws">${items.join('')}</div>` : '';
    swPanel.innerHTML = `<p class="ge-note">${esc(t('Klick = für {w} übernehmen', { w: target() === 'fill' ? t('Füllung') : t('Kontur') }))}</p>`
      + block(t('Design der Website'), (ed.boot.swatches || []).map(s => sw(s.color, s.name)))
      + block(t('Grundfarben'), [sw('currentColor', t('Schriftfarbe')), sw('#FFFFFF', t('Weiß')), sw('#000000', t('Schwarz')), sw('#1F2430', t('Tinte')), sw('#8A94A6', t('Grau'))].concat(`<button type="button" class="ge-sw ge-sw--none" data-c="none" aria-label="${esc(t('Keine'))}" title="${esc(t('Keine'))}"></button>`))
      + block(t('Zuletzt verwendet'), ed.recent.map(c => sw(c)))
      + block(t('In dieser Grafik'), [...docColors].slice(0, 32).map(c => sw(c)));
    swPanel.querySelectorAll('[data-c]').forEach(b => b.addEventListener('click', () => {
      const c = b.dataset.c;
      const p = paintOf(target());
      if ((p.mode === 'linear' || p.mode === 'radial') && c !== 'none') { applyColor(c, 1); }
      else setPaint(target(), c);
      rememberColor(c);
      if (sel().length) commit(t('Farbe aus Farbfeld')); else api.refresh();
    }));
  }

  // ================================================================= Kontur
  const stPanel = A.panel('stroke');
  stPanel.innerHTML = `<div class="ge-form">
    <label class="ge-lbl">${esc(t('Breite'))} <input type="number" class="ge-in" data-sw min="0" max="500" step="0.5"></label>
    <input type="range" class="ge-range" data-swr min="0" max="40" step="0.5" aria-label="${esc(t('Konturbreite'))}">
    <label class="ge-lbl">${esc(t('Strich'))} <select class="ge-in" data-dash>
      <option value="">${esc(t('Durchgehend'))}</option><option value="dash">${esc(t('Gestrichelt'))}</option><option value="dot">${esc(t('Gepunktet'))}</option><option value="custom">${esc(t('Eigenes Muster'))}</option></select></label>
    <label class="ge-lbl" data-dashcustom hidden>${esc(t('Muster'))} <input class="ge-in" data-dasharray placeholder="6 4"></label>
    <div class="ge-lbl">${esc(t('Enden'))} <span class="ge-seg" role="radiogroup" aria-label="${esc(t('Enden'))}" data-cap>${[['butt', t('Flach')], ['round', t('Rund')], ['square', t('Eckig')]].map(([v, l]) => `<button type="button" role="radio" data-v="${v}">${esc(l)}</button>`).join('')}</span></div>
    <div class="ge-lbl">${esc(t('Ecken'))} <span class="ge-seg" role="radiogroup" aria-label="${esc(t('Ecken'))}" data-join>${[['miter', t('Spitz')], ['round', t('Rund')], ['bevel', t('Abgeschrägt')]].map(([v, l]) => `<button type="button" role="radio" data-v="${v}">${esc(l)}</button>`).join('')}</span></div>
    <label class="ge-lbl ge-chk"><input type="checkbox" data-nonscale> ${esc(t('Breite beim Skalieren beibehalten'))}</label>
  </div>`;
  const setStroke = (prop, v, label) => {
    if (!sel().length) { if (prop === 'stroke-width') ed.style.strokeWidth = +v; return; }
    for (const el of sel()) eachShape(el, s => setProp(s, prop, v));
    if (label) commit(label); else ed.draw();
  };
  stPanel.querySelector('[data-sw]').addEventListener('change', e => setStroke('stroke-width', num(Math.max(0, +e.target.value)), t('Konturbreite')));
  stPanel.querySelector('[data-swr]').addEventListener('input', e => { stPanel.querySelector('[data-sw]').value = e.target.value; setStroke('stroke-width', e.target.value); });
  stPanel.querySelector('[data-swr]').addEventListener('change', () => commit(t('Konturbreite')));
  stPanel.querySelector('[data-dash]').addEventListener('change', e => {
    const v = e.target.value, w = parseFloat(first() ? getProp(first(), 'stroke-width') : ed.style.strokeWidth) || 1;
    stPanel.querySelector('[data-dashcustom]').hidden = v !== 'custom';
    if (v === 'custom') { stPanel.querySelector('[data-dasharray]').focus(); return; }
    setStroke('stroke-dasharray', v === 'dash' ? `${num(w * 3)} ${num(w * 2)}` : v === 'dot' ? `0 ${num(w * 2)}` : null);
    if (v === 'dot') setStroke('stroke-linecap', 'round');
    commit(t('Strichart'));
  });
  stPanel.querySelector('[data-dasharray]').addEventListener('change', e => {
    const v = e.target.value.trim().replace(/[^\d.,\s]/g, '');
    setStroke('stroke-dasharray', v || null, t('Strichart'));
  });
  for (const [k, prop] of [['cap', 'stroke-linecap'], ['join', 'stroke-linejoin']]) {
    stPanel.querySelectorAll(`[data-${k}] [data-v]`).forEach(b => b.addEventListener('click', () => setStroke(prop, b.dataset.v, k === 'cap' ? t('Linienenden') : t('Linienecken'))));
  }
  stPanel.querySelector('[data-nonscale]').addEventListener('change', e => setStroke('vector-effect', e.target.checked ? 'non-scaling-stroke' : null, t('Konturbreite')));
  function refreshStroke() {
    const el = paintEl(first());
    const w = el ? parseFloat(getProp(el, 'stroke-width')) || 0 : ed.style.strokeWidth;
    stPanel.querySelector('[data-sw]').value = num(w, 2);
    stPanel.querySelector('[data-swr]').value = w;
    const da = el ? getProp(el, 'stroke-dasharray') : '';
    const dsel = stPanel.querySelector('[data-dash]');
    dsel.value = !da || da === 'none' ? '' : /^0[\s,]/.test(da) ? 'dot' : da.split(/[\s,]+/).length === 2 ? 'dash' : 'custom';
    stPanel.querySelector('[data-dashcustom]').hidden = dsel.value !== 'custom';
    stPanel.querySelector('[data-dasharray]').value = da && da !== 'none' ? da : '';
    const cap = el ? getProp(el, 'stroke-linecap') || 'butt' : 'butt', join = el ? getProp(el, 'stroke-linejoin') || 'miter' : 'miter';
    stPanel.querySelectorAll('[data-cap] [data-v]').forEach(b => b.setAttribute('aria-checked', String(b.dataset.v === cap)));
    stPanel.querySelectorAll('[data-join] [data-v]').forEach(b => b.setAttribute('aria-checked', String(b.dataset.v === join)));
    stPanel.querySelector('[data-nonscale]').checked = el ? getProp(el, 'vector-effect') === 'non-scaling-stroke' : false;
    stPanel.querySelectorAll('input,select,button').forEach(i => { i.disabled = !el && !['data-sw', 'data-swr'].some(a => i.hasAttribute(a)); });
  }

  // ================================================================= Darstellung (Deckkraft, Mischmodus, Schatten, Ecken)
  const lookPanel = A.panel('look');
  const lookHtml = (cls) => `<div class="ge-form ${cls}">
    <label class="ge-lbl">${esc(t('Deckkraft'))} <input type="range" class="ge-range" data-op min="0" max="100" step="1"><output data-opo></output></label>
    <label class="ge-lbl">${esc(t('Mischmodus'))} <select class="ge-in" data-blend>${BLEND.map(b => `<option value="${b}">${esc(blendLabel(b))}</option>`).join('')}</select></label>
  </div>`;
  lookPanel.innerHTML = lookHtml('') + `<div class="ge-form">
    <label class="ge-lbl" data-radiusrow>${esc(t('Eckenradius'))} <input type="number" class="ge-in ge-in--s" data-radius min="0" max="9999" step="1"></label>
    <label class="ge-lbl ge-chk"><input type="checkbox" data-shadow> ${esc(t('Schatten'))}</label>
    <div class="ge-row4" data-shadowopts>
      <label>X <input type="number" class="ge-in ge-in--s" data-sh="dx" step="1"></label><label>Y <input type="number" class="ge-in ge-in--s" data-sh="dy" step="1"></label>
      <label>${esc(t('Weich'))} <input type="number" class="ge-in ge-in--s" data-sh="stdDeviation" min="0" step="0.5"></label>
      <label>${esc(t('Deckkraft'))} <input type="number" class="ge-in ge-in--s" data-sh="flood-opacity" min="0" max="1" step="0.05"></label></div>
  </div>`;
  function bindLook(box) {
    box.querySelector('[data-op]').addEventListener('input', e => { box.querySelector('[data-opo]').textContent = e.target.value + ' %'; for (const el of sel()) setProp(el, 'opacity', +e.target.value >= 100 ? null : num(+e.target.value / 100, 2)); ed.draw(); });
    box.querySelector('[data-op]').addEventListener('change', () => commit(t('Deckkraft')));
    box.querySelector('[data-blend]').addEventListener('change', e => { for (const el of sel()) setProp(el, 'mix-blend-mode', e.target.value === 'normal' ? null : e.target.value); commit(t('Mischmodus')); });
  }
  bindLook(lookPanel);
  lookPanel.querySelector('[data-radius]').addEventListener('change', e => {
    for (const el of sel()) if (el.localName === 'rect') { const v = Math.max(0, +e.target.value || 0); el.setAttribute('rx', num(v)); el.removeAttribute('ry'); if (!v) el.removeAttribute('rx'); }
    commit(t('Eckenradius'));
  });
  const shadowOf = el => { const f = refOf(ed.root, getProp(el, 'filter', false)); return f?.querySelector('feDropShadow') ? f : null; };
  lookPanel.querySelector('[data-shadow]').addEventListener('change', e => {
    for (const el of sel()) {
      if (!e.target.checked) { setProp(el, 'filter', null); continue; }
      const f = document.createElementNS(NS, 'filter');
      f.id = uid(ed.root, 'schatten-');
      Object.entries({ x: '-50%', y: '-50%', width: '200%', height: '200%' }).forEach(([k, v]) => f.setAttribute(k, v));
      const ds = document.createElementNS(NS, 'feDropShadow');
      const s = ed.format === 'info' ? 1 : 0.25;
      Object.entries({ dx: num(4 * s), dy: num(6 * s), stdDeviation: num(6 * s), 'flood-color': '#000000', 'flood-opacity': '0.25' }).forEach(([k, v]) => ds.setAttribute(k, v));
      f.append(ds); defs(ed.root).append(f);
      setProp(el, 'filter', `url(#${f.id})`);
    }
    commit(t('Schatten'));
  });
  lookPanel.querySelectorAll('[data-sh]').forEach(i => i.addEventListener('change', () => {
    for (const el of sel()) { const f = shadowOf(el); if (f) f.querySelector('feDropShadow').setAttribute(i.dataset.sh, num(+i.value || 0, 3)); }
    commit(t('Schatten'));
  }));
  function refreshLook(box) {
    const el = first();
    box.querySelectorAll('input,select').forEach(i => { i.disabled = !el; });
    const op = el ? Math.round((parseFloat(getProp(el, 'opacity', false) || '1')) * 100) : 100;
    box.querySelector('[data-op]').value = op; box.querySelector('[data-opo]').textContent = op + ' %';
    box.querySelector('[data-blend]').value = el ? (getProp(el, 'mix-blend-mode', false) || 'normal') : 'normal';
    if (box !== lookPanel) return;
    const rr = box.querySelector('[data-radiusrow]');
    rr.hidden = !(el && el.localName === 'rect');
    if (!rr.hidden) box.querySelector('[data-radius]').value = num(parseFloat(el.getAttribute('rx') || el.getAttribute('ry') || '0'), 2);
    const f = el ? shadowOf(el) : null;
    box.querySelector('[data-shadow]').checked = !!f;
    box.querySelector('[data-shadowopts]').hidden = !f;
    if (f) { const ds = f.querySelector('feDropShadow'); box.querySelectorAll('[data-sh]').forEach(i => { i.value = ds.getAttribute(i.dataset.sh) ?? ''; }); }
  }

  // ================================================================= Ebenen
  const layPanel = B.panel('layers');
  layPanel.innerHTML = lookHtml('ge-laytop') + `<ul class="ge-layers" role="tree" aria-label="${esc(t('Ebenen'))}" aria-multiselectable="true"></ul>
    <div class="ge-layacts">${iconBtn('selection-plus', t('Gruppieren'), 'data-la="group"')}${iconBtn('selection', t('Gruppierung aufheben'), 'data-la="ungroup"')}${iconBtn('copy', t('Duplizieren'), 'data-la="dup"')}${iconBtn('stack', t('Als Baustein speichern'), 'data-la="part"')}${iconBtn('trash', t('Löschen'), 'data-la="del"')}</div>`;
  bindLook(layPanel.querySelector('.ge-laytop'));
  const tree = layPanel.querySelector('.ge-layers');
  layPanel.querySelector('[data-la=group]').addEventListener('click', () => ed.group());
  layPanel.querySelector('[data-la=ungroup]').addEventListener('click', () => ed.ungroup());
  layPanel.querySelector('[data-la=dup]').addEventListener('click', () => ed.duplicate());
  layPanel.querySelector('[data-la=del]').addEventListener('click', () => ed.remove());
  layPanel.querySelector('[data-la=part]').addEventListener('click', () => savePart());
  // eigene Bausteine (Bibliothek der Website) nur mit Recht „blocks.build“
  if (ed.boot.canParts === false) layPanel.querySelector('[data-la=part]').hidden = true;

  const thumbCache = new Map();
  function thumb(el) {
    const b = docBounds(el, ed.root);
    if (!b || !b.w && !b.h) return '';
    const key = ed.hi + ':' + ed.pathOf(el).join('.');
    if (thumbCache.has(key)) return thumbCache.get(key);
    // Eigenständiges Mini-SVG mit Stilen und Definitionen der Grafik (als Bild: keine Konflikte mit der Seite)
    const extra = [...ed.root.children].filter(c => c.localName === 'style' || c.localName === 'defs').map(c => new XMLSerializer().serializeToString(c)).join('');
    if (extra.length > 200000) return '';
    const P = el.parentNode === ed.root ? null : ed.root.getScreenCTM() && docMatrixOf(el.parentNode);
    const pad = Math.max(b.w, b.h) * 0.06 + 0.5;
    const inner = new XMLSerializer().serializeToString(el);
    const svg = `<svg xmlns="${NS}" viewBox="${num(b.x - pad)} ${num(b.y - pad)} ${num(b.w + pad * 2)} ${num(b.h + pad * 2)}"${ed.root.getAttribute('fill') ? ` fill="${esc(ed.root.getAttribute('fill'))}"` : ''}${ed.root.getAttribute('stroke') ? ` stroke="${esc(ed.root.getAttribute('stroke'))}" stroke-width="${esc(ed.root.getAttribute('stroke-width') || '1')}" stroke-linecap="round" stroke-linejoin="round"` : ''} color="#1F2430">${extra}${P ? `<g transform="matrix(${[P.a, P.b, P.c, P.d, P.e, P.f].map(v => num(v, 5)).join(' ')})">${inner}</g>` : inner}</svg>`;
    const html = `<img alt="" src="data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg).replace(/'/g, '%27')}">`;
    if (thumbCache.size > 600) thumbCache.clear();
    thumbCache.set(key, html);
    return html;
  }
  const docMatrixOf = el => { const a = ed.root.getScreenCTM(), b = el.getScreenCTM(); return a && b ? DOMMatrix.fromMatrix(a).inverse().multiply(DOMMatrix.fromMatrix(b)) : new DOMMatrix(); };

  function rowHtml(el, depth, many, role = '') {
    const kids = ['g', 'switch'].includes(el.localName) ? drawnChildren(el) : [];
    // Beschneidung/Maske dieser Gruppe: Form(en) als eigene Zeilen oben in der Gruppe
    const cl = clipOf(el, ed.root), clipKids = cl && el.contains(cl.def) ? drawnChildren(cl.def) : [];
    const expandable = kids.length + clipKids.length > 0;
    const key = ed.pathOf(el).join('.');
    const open = expanded.has(key) || (ed.sel.some(s => el !== s && el.contains(s)));
    const on = ed.sel.includes(el);
    const lockd = isLocked(el), hid = isHidden(el);
    const prefix = role === 'clip' ? t('Beschneidungsform') + ': ' : role === 'mask' ? t('Maske') + ': ' : '';
    const badge = cl ? `<span class="ge-lay__badge" title="${esc(cl.kind === 'clip' ? t('Beschnitten') : t('Maskiert'))}">${ico(cl.kind === 'clip' ? 'crop' : 'selection-background')}</span>` : '';
    let s = `<li role="treeitem" class="ge-lay${on ? ' is-on' : ''}${hid ? ' is-hidden' : ''}${role ? ' is-clipshape' : ''}" data-path="${key}" aria-selected="${on}" aria-level="${depth + 1}"${expandable ? ` aria-expanded="${open}"` : ''} tabindex="-1" draggable="${role ? 'false' : 'true'}">
      <span class="ge-lay__row" style="--d:${depth}">
        ${expandable ? `<button type="button" class="ge-lay__exp" data-exp tabindex="-1" aria-label="${esc(open ? t('Zuklappen') : t('Aufklappen'))}">${ico(open ? 'caret-down' : 'caret-right')}</button>` : '<span class="ge-lay__exp"></span>'}
        <span class="ge-lay__thumb" aria-hidden="true">${many || role ? ico(role === 'clip' ? 'crop' : role === 'mask' ? 'selection-background' : el.localName === 'text' ? 'text-t' : kids.length ? 'folder' : 'shapes') : thumb(el)}</span>
        <span class="ge-lay__name" title="${esc(typeLabel(el))}">${esc(prefix + nameOf(el))}${cl ? ' <span class="adm-sr">(' + esc(cl.kind === 'clip' ? t('Beschnitten') : t('Maskiert')) + ')</span>' : ''}</span>${badge}
        <button type="button" class="ge-lay__btn${lockd ? ' is-set' : ''}" data-lock tabindex="-1" aria-pressed="${lockd}" aria-label="${esc(lockd ? t('Entsperren') : t('Sperren'))}" title="${esc(lockd ? t('Entsperren') : t('Sperren'))}">${ico(lockd ? 'lock' : 'lock-open')}</button>
        <button type="button" class="ge-lay__btn${hid ? ' is-set' : ''}" data-eye tabindex="-1" aria-pressed="${hid}" aria-label="${esc(hid ? t('Einblenden') : t('Ausblenden'))}" title="${esc(hid ? t('Einblenden') : t('Ausblenden'))}">${ico(hid ? 'eye-slash' : 'eye')}</button>
      </span>`;
    if (expandable && open) s += `<ul role="group">${[...clipKids].reverse().map(c => rowHtml(c, depth + 1, many, cl.kind)).join('')}${[...kids].reverse().map(c => rowHtml(c, depth + 1, many)).join('')}</ul>`;
    return s + '</li>';
  }

  function refreshLayers() {
    refreshLook(layPanel.querySelector('.ge-laytop'));
    const top = drawnChildren(ed.root);
    const many = ed.root.querySelectorAll('*').length > 400;
    const focusPath = document.activeElement?.closest?.('.ge-lay')?.dataset.path;
    tree.innerHTML = top.length ? [...top].reverse().map(el => rowHtml(el, 0, many)).join('') : `<li class="ge-lay__empty">${esc(t('Noch leer – Werkzeug links wählen oder Baustein aus der Bibliothek ziehen.'))}</li>`;
    const items = [...tree.querySelectorAll('[role=treeitem]')];
    const cur = items.find(li => li.dataset.path === focusPath) || items.find(li => li.classList.contains('is-on')) || items[0];
    if (cur) cur.tabIndex = 0;
    if (focusPath && cur && tree.contains(document.activeElement) === false && cur.dataset.path === focusPath) cur.focus();
    layPanel.querySelectorAll('[data-la]').forEach(b => { b.disabled = !ed.sel.length; });
  }
  const elOf = li => ed.byPath(li.dataset.path.split('.').filter(x => x !== '').map(Number));
  tree.addEventListener('click', e => {
    const li = e.target.closest('[role=treeitem]');
    if (!li) return;
    const el = elOf(li);
    if (!el) return;
    if (e.target.closest('[data-exp]')) { const k = li.dataset.path; expanded.has(k) || li.getAttribute('aria-expanded') === 'true' ? (expanded.delete(k), li.setAttribute('aria-expanded', 'false')) : expanded.add(k); if (li.getAttribute('aria-expanded') === 'false') { /* zugeklappt */ } refreshLayers(); return; }
    if (e.target.closest('[data-lock]')) { el.getAttribute('data-locked') === '1' ? el.removeAttribute('data-locked') : el.setAttribute('data-locked', '1'); if (isLocked(el)) ed.sel = ed.sel.filter(s => s !== el); commit(isLocked(el) ? t('Gesperrt') : t('Entsperrt')); return; }
    if (e.target.closest('[data-eye]')) { isHidden(el) ? (setProp(el, 'display', null)) : setProp(el, 'display', 'none'); if (isHidden(el)) ed.sel = ed.sel.filter(s => s !== el); commit(isHidden(el) ? t('Ausgeblendet') : t('Eingeblendet')); return; }
    if (isLocked(el)) return;
    // Form einer Beschneidung/Maske: bearbeiten über Pfeiltasten, Transformieren, Knoten
    if (el.closest('clipPath,mask')) { ed.scope = ed.root; ed.select([el]); return; }
    if (el.parentNode !== ed.root && el.parentNode !== ed.scope) ed.scope = el.parentNode;
    else if (el.parentNode === ed.root) ed.scope = ed.root;
    if (e.shiftKey || e.metaKey || e.ctrlKey) ed.select(ed.sel.includes(el) ? ed.sel.filter(s => s !== el) : [...ed.sel, el]);
    else ed.select([el]);
  });
  tree.addEventListener('dblclick', e => {
    const li = e.target.closest('[role=treeitem]');
    if (li && e.target.closest('.ge-lay__name')) rename(li);
  });
  tree.addEventListener('keydown', e => {
    const items = [...tree.querySelectorAll('[role=treeitem]')], li = e.target.closest('[role=treeitem]');
    if (!li) return;
    const i = items.indexOf(li);
    const go = n => { if (!n) return; items.forEach(x => { x.tabIndex = -1; }); n.tabIndex = 0; n.focus(); };
    if (e.key === 'ArrowDown') { e.preventDefault(); e.stopPropagation(); go(items[i + 1]); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); e.stopPropagation(); go(items[i - 1]); }
    else if (e.key === 'ArrowRight' && li.getAttribute('aria-expanded') === 'false') { e.preventDefault(); e.stopPropagation(); expanded.add(li.dataset.path); refreshLayers(); }
    else if (e.key === 'ArrowLeft' && li.getAttribute('aria-expanded') === 'true') { e.preventDefault(); e.stopPropagation(); expanded.delete(li.dataset.path); refreshLayers(); }
    else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); li.querySelector('.ge-lay__name').click(); }
    else if (e.key === 'F2') { e.preventDefault(); e.stopPropagation(); rename(li); }
  });
  function rename(li) {
    const el = elOf(li);
    const span = li.querySelector('.ge-lay__name');
    const inp = h(`<input class="ge-in ge-lay__rename" maxlength="120" aria-label="${esc(t('Name der Ebene'))}">`);
    inp.value = el.getAttribute('data-name') || nameOf(el);
    span.replaceWith(inp); inp.focus(); inp.select();
    let done = false;
    const fin = ok => {
      if (done) return; done = true;
      const v = inp.value.trim();
      if (ok && v !== (el.getAttribute('data-name') || '')) { v ? el.setAttribute('data-name', v) : el.removeAttribute('data-name'); commit(t('Umbenannt')); }
      else refreshLayers();
    };
    inp.addEventListener('keydown', e => { e.stopPropagation(); if (e.key === 'Enter') { e.preventDefault(); fin(true); } if (e.key === 'Escape') { e.preventDefault(); fin(false); } });
    inp.addEventListener('blur', () => fin(true));
  }
  // Ziehen zum Umsortieren: obere Hälfte = davor (weiter oben in der Liste = weiter vorn), untere = dahinter, Mitte einer Gruppe = hinein
  let dragPath = null;
  tree.addEventListener('dragstart', e => { const li = e.target.closest('[role=treeitem]'); if (!li) return; dragPath = li.dataset.path; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', nameOf(elOf(li))); });
  tree.addEventListener('dragover', e => {
    const li = e.target.closest('[role=treeitem]');
    if (!li || !dragPath) return;
    e.preventDefault();
    const r = li.querySelector('.ge-lay__row').getBoundingClientRect(), y = (e.clientY - r.top) / r.height;
    const grp = li.hasAttribute('aria-expanded');
    tree.querySelectorAll('.is-drop-a,.is-drop-b,.is-drop-in').forEach(x => x.classList.remove('is-drop-a', 'is-drop-b', 'is-drop-in'));
    li.classList.add(grp && y > .3 && y < .7 ? 'is-drop-in' : y < .5 ? 'is-drop-a' : 'is-drop-b');
  });
  tree.addEventListener('dragleave', e => { if (!tree.contains(e.relatedTarget)) tree.querySelectorAll('.is-drop-a,.is-drop-b,.is-drop-in').forEach(x => x.classList.remove('is-drop-a', 'is-drop-b', 'is-drop-in')); });
  tree.addEventListener('drop', e => {
    const li = e.target.closest('[role=treeitem]');
    if (!li || !dragPath) return;
    e.preventDefault();
    const src = ed.byPath(dragPath.split('.').map(Number)), dst = elOf(li);
    const how = li.classList.contains('is-drop-in') ? 'in' : li.classList.contains('is-drop-a') ? 'above' : 'below';
    dragPath = null;
    if (!src || !dst || src === dst || src.contains(dst)) { refreshLayers(); return; }
    const before = src.getScreenCTM ? docMatrixOf(src) : null;
    if (how === 'in') dst.append(src);
    else if (how === 'above') dst.after(src);    // oben in der Liste = später im Dokument
    else dst.before(src);
    // Lage im Bild bleibt gleich, auch wenn die neue Gruppe verschoben ist
    if (before) { const after = docMatrixOf(src); if (!after.multiply(before.inverse()).isIdentity) applyTransform(src, before.multiply(after.inverse()), ed.root); }
    ed.sel = [src];
    commit(t('Ebene verschoben'));
  });
  tree.addEventListener('dragend', () => { dragPath = null; tree.querySelectorAll('.is-drop-a,.is-drop-b,.is-drop-in').forEach(x => x.classList.remove('is-drop-a', 'is-drop-b', 'is-drop-in')); });

  // ================================================================= Bibliothek (Bausteine, eigene Bausteine)
  const libPanel = B.panel('lib');
  function itemSvg(it) {
    const body = it.svg;
    return `<svg xmlns="${NS}" viewBox="0 0 ${it.w} ${it.h}" color="${esc(it.color || '#1F2430')}">${body}</svg>`;
  }
  const tile = (it, kind) => `<li><button type="button" class="ge-tile" draggable="true" data-kind="${kind}" data-id="${esc(String(it.id))}" title="${esc(it.label || it.name)}" aria-label="${esc(t('Einfügen: {name}', { name: it.label || it.name }))}">
      <img alt="" src="data:image/svg+xml;charset=utf-8,${encodeURIComponent(kind === 'part' ? it.svg : itemSvg(it)).replace(/'/g, '%27')}"><span>${esc(it.label || it.name)}</span></button></li>`;
  function buildLib() {
    libBuilt = true;
    const groups = ed.boot.libraryGroups || {};
    const parts = ed.boot.parts || [];
    libPanel.innerHTML = `<label class="ge-search">${ico('magnifying-glass')}<input type="search" class="ge-in" data-q placeholder="${esc(t('Bausteine suchen'))}" aria-label="${esc(t('Bausteine suchen'))}"></label>
      <p class="ge-note">${esc(t('Anklicken fügt in der Mitte ein, Ziehen legt den Baustein an die Stelle.'))}</p>
      <h3 class="ge-h3">${esc(t('Eigene Bausteine'))}</h3>${parts.length ? `<ul class="ge-tiles" data-parts>${parts.map(p => tile(p, 'part')).join('')}</ul>` : `<p class="ge-note" data-noparts>${esc(t('Noch keine – Auswahl unter Ebenen als Baustein speichern.'))}</p><ul class="ge-tiles" data-parts></ul>`}
      ${Object.entries(groups).map(([g, label]) => `<h3 class="ge-h3">${esc(label)}</h3><ul class="ge-tiles">${(ed.boot.library || []).filter(it => it.group === g).map(it => tile(it, 'lib')).join('')}</ul>`).join('')}`;
    libPanel.querySelector('[data-q]').addEventListener('input', e => {
      const q = e.target.value.trim().toLowerCase();
      libPanel.querySelectorAll('.ge-tile').forEach(b => { b.parentNode.hidden = !!q && !b.title.toLowerCase().includes(q); });
    });
    bindTiles(libPanel);
  }
  function bindTiles(box) {
    box.querySelectorAll('.ge-tile:not([data-bound])').forEach(b => {
      b.dataset.bound = '1';
      const item = () => ({ kind: b.dataset.kind, id: b.dataset.id });
      b.addEventListener('click', () => api.insertItem(item()));
      b.addEventListener('dragstart', e => { e.dataTransfer.setData('application/x-ge-item', JSON.stringify(item())); e.dataTransfer.effectAllowed = 'copy'; });
    });
  }

  /** Teil in die Grafik einfügen (zentriert an p, sonst in der Mitte der Ansicht); passt sehr große Teile ein */
  function placeFragment(g, w, h, p, label) {
    const vb = ed.vb;
    const at = p || ed.toDoc(ed.stage.getBoundingClientRect().left + ed.stage.clientWidth / 2, ed.stage.getBoundingClientRect().top + ed.stage.clientHeight / 2);
    const s = Math.min(1, (vb.w * 0.6) / w, (vb.h * 0.6) / h);
    const local = ed.local(at);
    g.setAttribute('transform', `translate(${num(local.x - w * s / 2)} ${num(local.y - h * s / 2)})${s < 1 ? ` scale(${num(s, 5)})` : ''}`);
    ed.target.append(g);
    remapIds(g, ed.root);
    ed.sel = [g];
    commit(t('Eingefügt: {name}', { name: label }));
  }

  // ================================================================= Symbole (Symbolsatz des Kerns)
  const icoPanel = B.panel('icons');
  let sprite = null;
  async function buildIcons() {
    iconsBuilt = true;
    icoPanel.innerHTML = `<p class="ge-note">${esc(t('Wird geladen …'))}</p>`;
    let cat;
    try { cat = await (await fetch(ed.boot.icons)).json(); } catch { icoPanel.innerHTML = `<p class="ge-note">${esc(t('Symbole konnten nicht geladen werden.'))}</p>`; return; }
    const de = (document.documentElement.lang || 'de').startsWith('de');
    icoPanel.innerHTML = `<label class="ge-search">${ico('magnifying-glass')}<input type="search" class="ge-in" data-q placeholder="${esc(t('Symbole suchen'))}" aria-label="${esc(t('Symbole suchen'))}"></label>
      <p class="ge-note">${esc(t('Symbole aus dem Symbolsatz von KLXM Studio (Phosphor, MIT) – werden als Vektorpfade eingefügt.'))}</p>
      ${(cat.topics || []).map(tp => `<h3 class="ge-h3">${esc(de ? tp.de : tp.en)}</h3><ul class="ge-icons">${(tp.icons || []).map(n => `<li><button type="button" class="ge-icon" draggable="true" data-ico="${esc(n)}" title="${esc(n)}" aria-label="${esc(t('Symbol einfügen: {name}', { name: n }))}">${ico(n)}</button></li>`).join('')}</ul>`).join('')}`;
    icoPanel.querySelector('[data-q]').addEventListener('input', e => {
      const q = e.target.value.trim().toLowerCase();
      icoPanel.querySelectorAll('.ge-icon').forEach(b => { b.parentNode.hidden = !!q && !b.dataset.ico.includes(q); });
    });
    icoPanel.querySelectorAll('[data-ico]').forEach(b => {
      b.addEventListener('click', () => api.insertItem({ kind: 'icon', id: b.dataset.ico }));
      b.addEventListener('dragstart', e => { e.dataTransfer.setData('application/x-ge-item', JSON.stringify({ kind: 'icon', id: b.dataset.ico })); e.dataTransfer.effectAllowed = 'copy'; });
    });
  }
  async function iconSymbol(name) {
    if (!sprite) {
      const txt = await (await fetch(spriteUrl())).text();
      sprite = new DOMParser().parseFromString(txt, 'image/svg+xml');
    }
    return sprite.getElementById('i-' + name);
  }

  // ================================================================= Transformieren
  const trPanel = C.panel('transform');
  trPanel.innerHTML = `<div class="ge-tr">
    <div class="ge-anchor" role="radiogroup" aria-label="${esc(t('Bezugspunkt'))}">${['nw', 'n', 'ne', 'w', 'c', 'e', 'sw', 's', 'se'].map(a => `<button type="button" role="radio" data-anchor="${a}" aria-label="${esc(t('Bezugspunkt {a}', { a }))}"></button>`).join('')}</div>
    <div class="ge-trf">
      <label>X <input type="number" class="ge-in" data-tf="x" step="any"></label><label>${esc(t('B'))} <input type="number" class="ge-in" data-tf="w" step="any" min="0" aria-label="${esc(t('Breite'))}"></label>
      <label>Y <input type="number" class="ge-in" data-tf="y" step="any"></label><label>${esc(t('H'))} <input type="number" class="ge-in" data-tf="h" step="any" min="0" aria-label="${esc(t('Höhe'))}"></label>
      <label>R <input type="number" class="ge-in" data-tf="r" step="any" aria-label="${esc(t('Drehung in Grad'))}"></label>
      ${iconBtn('link-simple', t('Seitenverhältnis sperren'), 'data-lockratio aria-pressed="false"')}
    </div></div>`;
  trPanel.querySelectorAll('[data-anchor]').forEach(b => b.addEventListener('click', () => { anchor = b.dataset.anchor; refreshTransform(); }));
  trPanel.querySelector('[data-lockratio]').addEventListener('click', e => { lock = !lock; e.currentTarget.setAttribute('aria-pressed', String(lock)); e.currentTarget.innerHTML = ico(lock ? 'link-simple' : 'link-break'); });
  const AU = { nw: [0, 0], n: [.5, 0], ne: [1, 0], w: [0, .5], c: [.5, .5], e: [1, .5], sw: [0, 1], s: [.5, 1], se: [1, 1] };
  function tfState() {
    const f = ed.frame();
    if (!f) return null;
    const [u, v] = AU[anchor];
    const d = decompose(f.F);
    const a = { x: f.b.x + f.b.w * u, y: f.b.y + f.b.h * v };
    const A = pt(f.F, a.x, a.y);
    return { f, a, A, w: f.b.w * d.sx, h: f.b.h * Math.abs(d.sy), r: ed.sel.length === 1 ? d.rot : 0 };
  }
  function refreshTransform() {
    trPanel.querySelectorAll('[data-anchor]').forEach(b => b.setAttribute('aria-checked', String(b.dataset.anchor === anchor)));
    const s = tfState();
    trPanel.querySelectorAll('[data-tf]').forEach(i => { i.disabled = !s; if (!s) i.value = ''; });
    if (!s || trPanel.contains(document.activeElement) && document.activeElement.matches('input')) return;
    const set = (k, v) => { trPanel.querySelector(`[data-tf="${k}"]`).value = num(v, 2); };
    set('x', s.A.x); set('y', s.A.y); set('w', s.w); set('h', s.h); set('r', ((s.r % 360) + 360) % 360 > 180 ? s.r : s.r);
    trPanel.querySelector('[data-tf="r"]').disabled = ed.sel.length !== 1;
  }
  trPanel.querySelectorAll('[data-tf]').forEach(i => i.addEventListener('change', () => {
    const s = tfState();
    if (!s) return;
    const v = parseFloat(i.value);
    if (!Number.isFinite(v)) { refreshTransform(); return; }
    const { F, b } = s.f, Fi = F.inverse();
    let T = null;
    if (i.dataset.tf === 'x') T = new DOMMatrix().translate(v - s.A.x, 0);
    if (i.dataset.tf === 'y') T = new DOMMatrix().translate(0, v - s.A.y);
    if (i.dataset.tf === 'w' || i.dataset.tf === 'h') {
      const isW = i.dataset.tf === 'w', cur = isW ? s.w : s.h;
      if (v <= 0 || !cur) { refreshTransform(); return; }
      const k = v / cur, sx = isW || lock ? k : 1, sy = !isW || lock ? k : 1;
      T = F.multiply(new DOMMatrix().translate(s.a.x, s.a.y).scale(sx, sy).translate(-s.a.x, -s.a.y)).multiply(Fi);
    }
    if (i.dataset.tf === 'r') T = new DOMMatrix().translate(s.A.x, s.A.y).rotate(v - s.r).translate(-s.A.x, -s.A.y);
    if (!T) return;
    ed.sel.forEach(el => applyTransform(el, T, ed.root));
    void b;
    commit(t('Transformiert'));
  }));

  // ================================================================= Protokoll
  const hiPanel = C.panel('history');
  function refreshHistory() {
    hiPanel.innerHTML = `<ol class="ge-hist">${ed.history.map((x, i) => `<li><button type="button" data-hi="${i}" class="${i === ed.hi ? 'is-on' : ''}${i > ed.hi ? ' is-future' : ''}" aria-current="${i === ed.hi ? 'step' : 'false'}">${esc(x.label)}${i === ed.savedAt ? ` <span class="ge-hist__saved">${esc(t('gespeichert'))}</span>` : ''}</button></li>`).join('')}</ol>`;
    hiPanel.querySelectorAll('[data-hi]').forEach(b => b.addEventListener('click', () => ed.goto(+b.dataset.hi)));
    hiPanel.querySelector('.is-on')?.scrollIntoView({ block: 'nearest' });
  }

  // ================================================================= Als Baustein speichern
  async function savePart() {
    if (!ed.sel.length) return;
    const b = ed.selBounds();
    if (!b) return;
    const r = await modal(ed.el, { title: t('Als Baustein speichern'), ok: t('Speichern'), body: `<label class="f">${esc(t('Name'))}<input name="name" maxlength="120" required value="${esc(nameOf(ed.sel[0]))}"></label><p class="f-help">${esc(t('Der Baustein erscheint in der Bibliothek des Editors auf dieser Website.'))}</p>` });
    if (!r) return;
    const parts = ed.ordered(ed.sel).map(el => {
      const c = el.cloneNode(true);
      const P = el.parentNode === ed.root ? null : docMatrixOf(el.parentNode);
      if (!P) return new XMLSerializer().serializeToString(c);
      const g = document.createElementNS(NS, 'g');
      g.setAttribute('transform', `matrix(${[P.a, P.b, P.c, P.d, P.e, P.f].map(v => num(v, 6)).join(' ')})`);
      g.append(c);
      return new XMLSerializer().serializeToString(g);
    }).join('');
    const used = [...ed.root.children].filter(c => c.localName === 'defs' || c.localName === 'style').map(c => new XMLSerializer().serializeToString(c)).join('');
    const pad = 0;
    const svg = `<svg xmlns="${NS}" viewBox="${num(b.x - pad)} ${num(b.y - pad)} ${num(b.w + pad * 2)} ${num(b.h + pad * 2)}" width="${num(b.w)}" height="${num(b.h)}">${used}${parts}</svg>`;
    const res = await ed.api('POST', ed.boot.urls.part, { name: r.name, svg });
    if (!res) return;
    ed.boot.parts = [res.item, ...(ed.boot.parts || [])];
    toast(t('Als Baustein gespeichert'));
    if (libBuilt) buildLib();
  }

  // ================================================================= Schnittstelle für den Editor
  const api = {
    groups: [A.el, TP.el, B.el, C.el],
    text: TP,
    aspectLocked: () => lock,
    showColor() { A.select('color'); refreshColor(); },
    refresh() {
      thumbCache.clear();
      const a = A.current(), b = B.current(), c = C.current();
      if (a === 'color') refreshColor(); else if (a === 'swatches') refreshSwatches(); else if (a === 'stroke') refreshStroke(); else refreshLook(lookPanel);
      if (b === 'layers') refreshLayers(); else if (b === 'lib' && !libBuilt) buildLib(); else if (b === 'icons' && !iconsBuilt) buildIcons();
      if (c === 'transform') refreshTransform(); else refreshHistory();
      TP.refresh();
    },
    async insertItem(item, p) {
      if (item.kind === 'lib') {
        const it = (ed.boot.library || []).find(x => x.id === item.id);
        if (!it) return;
        const g = document.createElementNS(NS, 'g');
        g.setAttribute('data-name', it.label);
        g.setAttribute('color', it.color);
        g.innerHTML = it.svg;
        placeFragment(g, it.w, it.h, p, it.label);
      } else if (item.kind === 'part') {
        const it = (ed.boot.parts || []).find(x => String(x.id) === String(item.id));
        if (!it) return;
        const doc = new DOMParser().parseFromString(it.svg, 'image/svg+xml').documentElement;
        const vb = (doc.getAttribute('viewBox') || '0 0 100 100').split(/[\s,]+/).map(Number);
        const g = document.createElementNS(NS, 'g');
        g.setAttribute('data-name', it.name);
        const inner = document.createElementNS(NS, 'g');
        if (vb[0] || vb[1]) inner.setAttribute('transform', `translate(${num(-vb[0])} ${num(-vb[1])})`);
        for (const c of [...doc.children]) if (!['title', 'desc', 'metadata'].includes(c.localName)) inner.append(document.importNode(c, true));
        g.append(inner);
        placeFragment(g, vb[2] || 100, vb[3] || 100, p, it.name);
      } else if (item.kind === 'icon') {
        const sym = await iconSymbol(item.id);
        if (!sym) return;
        const g = document.createElementNS(NS, 'g');
        g.setAttribute('data-name', item.id);
        const size = ed.format === 'icon' ? 24 : ed.format === 'preview' ? 24 : 64;
        const s = size / 256;
        const inner = document.createElementNS(NS, 'g');
        inner.setAttribute('transform', `scale(${num(s, 6)})`);
        const fill = ed.format === 'icon' ? 'currentColor' : (ed.style.fill !== 'none' ? ed.style.fill : '#1F2430');
        inner.setAttribute('fill', fill);
        if (ed.format === 'icon') { inner.setAttribute('stroke', 'none'); }
        for (const c of [...sym.children]) {
          const n = document.importNode(c, true);
          if (n.getAttribute('opacity') === '.2') n.setAttribute('opacity', '0.2');
          inner.append(n);
        }
        g.append(inner);
        placeFragment(g, size, size, p, item.id);
      }
    },
  };
  return api;
}

export { isDrawn };
