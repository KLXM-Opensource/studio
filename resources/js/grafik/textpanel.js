/*
 * Grafik-Editor – Bereich „Text“ mit den Reitern Zeichen und Absatz (wie Affinity Designer):
 *   Zeichen  Schrift (selbst gehostete Schriften der Website + Systemschriften), Schnitt, Kursiv, Größe, Laufweite,
 *            Unterschneidung, Unterstrichen/Durchgestrichen, Groß-/Kleinschreibung (als echte Zeichen)
 *   Absatz   Ausrichtung, Zeilenabstand, Textrahmen-Breite (Umbruch), Text auf Pfad (Startversatz, lösen)
 * Während der Bearbeitung auf der Fläche wirken Zeichen-Eigenschaften auf die Auswahl im Text; sonst auf die ganzen Texte. MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { num } from './geom.js';
import { getProp } from './doc.js';
import { tabGroup, iconBtn, MOD } from './ui.js';
import { lineHeightOf } from './text.js';

export const SYSTEM_FONTS = [
  ['system-ui, sans-serif', () => t('Systemschrift')], ['sans-serif', () => t('Serifenlos (System)')], ['serif', () => t('Serifen (System)')],
  ['monospace', () => t('Festbreite (System)')], ['Georgia, serif', () => 'Georgia'], ['Arial, Helvetica, sans-serif', () => 'Arial / Helvetica'],
];
const WEIGHTS = { 100: () => t('Hauchdünn'), 200: () => t('Extraleicht'), 300: () => t('Leicht'), 400: () => t('Normal'), 500: () => t('Mittel'), 600: () => t('Halbfett'), 700: () => t('Fett'), 800: () => t('Extrafett'), 900: () => t('Schwarz') };

/** Erste Familie eines Stapels ohne Anführungszeichen */
export const firstFamily = v => String(v || '').split(',')[0].trim().replace(/^["']|["']$/g, '');
const normW = w => w === 'bold' ? 700 : w === 'normal' || !w ? 400 : parseInt(w, 10) || 400;

export function buildTextPanel(ed) {
  const G = tabGroup(ed.uid + '-d', [{ id: 'char', label: t('Zeichen') }, { id: 'para', label: t('Absatz') }], () => api.refresh());
  G.el.classList.add('ge-pg--text');
  const files = ed.boot.fontFiles || {};
  const siteFonts = [...new Set([...Object.keys(files), ...(ed.boot.fonts || []).filter(f => !/^(system-ui|sans-serif|serif|monospace)$/.test(f))])].sort((a, b) => a.localeCompare(b));
  const charP = G.panel('char'), paraP = G.panel('para');
  charP.innerHTML = `<div class="ge-form">
    <label class="ge-lbl ge-lbl--wide">${esc(t('Schrift'))} <select class="ge-in" data-tp="font-family">
      <optgroup label="${esc(t('Schriften der Website'))}">${siteFonts.map(f => `<option value="${esc(f)}">${esc(f)}</option>`).join('')}</optgroup>
      <optgroup label="${esc(t('Systemschriften'))}">${SYSTEM_FONTS.map(([v, l]) => `<option value="${esc(v)}">${esc(l())}</option>`).join('')}</optgroup></select></label>
    <label class="ge-lbl">${esc(t('Schnitt'))} <select class="ge-in" data-tp="font-weight"></select></label>
    <label class="ge-lbl">${esc(t('Größe'))} <input type="number" class="ge-in ge-in--s" data-tp="font-size" min="1" max="2000" step="1"></label>
    <label class="ge-lbl">${esc(t('Laufweite'))} <input type="number" class="ge-in ge-in--s" data-tp="tracking" min="-500" max="2000" step="10" title="${esc(t('Tausendstel der Schriftgröße'))}"> ‰</label>
    <div class="ge-lbl">${esc(t('Stil'))} <span class="ge-seg" role="group" aria-label="${esc(t('Stil'))}">
      ${iconBtn('text-italic', t('Kursiv'), 'data-tt="italic" aria-pressed="false"', MOD + 'I')}${iconBtn('text-underline', t('Unterstrichen'), 'data-tt="underline" aria-pressed="false"', MOD + 'U')}${iconBtn('text-strikethrough', t('Durchgestrichen'), 'data-tt="line-through" aria-pressed="false"')}</span></div>
    <div class="ge-lbl">${esc(t('Schreibung'))} <span class="ge-seg" role="group" aria-label="${esc(t('Groß- und Kleinschreibung'))}">
      <button type="button" class="ge-ib ge-ib--txt" data-case="upper" title="${esc(t('GROSSBUCHSTABEN'))}" aria-label="${esc(t('Großbuchstaben'))}">AB</button>
      <button type="button" class="ge-ib ge-ib--txt" data-case="lower" title="${esc(t('kleinbuchstaben'))}" aria-label="${esc(t('Kleinbuchstaben'))}">ab</button>
      <button type="button" class="ge-ib ge-ib--txt" data-case="title" title="${esc(t('Wortanfänge groß'))}" aria-label="${esc(t('Wortanfänge groß'))}">Ab</button></span></div>
    <label class="ge-lbl ge-chk"><input type="checkbox" data-tp="kerning"> ${esc(t('Unterschneidung (Kerning) der Schrift'))}</label>
    <p class="ge-note" data-tnote hidden></p>
  </div>`;
  paraP.innerHTML = `<div class="ge-form">
    <div class="ge-lbl">${esc(t('Ausrichtung'))} <span class="ge-seg" role="radiogroup" aria-label="${esc(t('Ausrichtung'))}">${[['start', 'text-align-left', t('Links')], ['middle', 'text-align-center', t('Mitte')], ['end', 'text-align-right', t('Rechts')]]
      .map(([v, i, l]) => `<button type="button" class="ge-ib" role="radio" data-anchor="${v}" aria-checked="false" aria-label="${esc(l)}" title="${esc(l)}">${ico(i)}</button>`).join('')}</span></div>
    <label class="ge-lbl">${esc(t('Zeilenabstand'))} <input type="number" class="ge-in ge-in--s" data-tp="lh" min="0.5" max="5" step="0.05"> ×</label>
    <label class="ge-lbl">${esc(t('Textrahmen'))} <input type="number" class="ge-in ge-in--s" data-tp="frame" min="0" max="10000" step="1" title="${esc(t('Breite für den automatischen Umbruch, 0 = ohne Rahmen'))}"></label>
    <p class="f-help ge-help">${esc(t('Mit dem Text-Werkzeug einen Rahmen aufziehen: Zeilen brechen automatisch um. 0 = Punkttext ohne Umbruch.'))}</p>
    <div data-pathbox>
      <button type="button" class="adm-btn adm-btn--small" data-tcmd="onpath">${ico('path')} ${esc(t('Text auf Pfad'))}</button>
      <label class="ge-lbl" data-offrow>${esc(t('Startversatz'))} <input type="number" class="ge-in ge-in--s" data-tp="offset" min="-100" max="100" step="1"> %</label>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-tcmd="offpath">${esc(t('Vom Pfad lösen'))}</button>
    </div>
  </div>`;

  const texts = () => ed.textEl ? [ed.textEl] : ed.sel.filter(el => el.localName === 'text');
  const first = () => texts()[0] || null;
  const q = s => G.el.querySelector(s);

  function weightsFor(fam) {
    const f = files[firstFamily(fam)];
    if (!f) return [100, 200, 300, 400, 500, 600, 700, 800, 900];
    if (f.files.some(x => x.weight === null)) return [100, 200, 300, 400, 500, 600, 700, 800, 900];
    return [...new Set(f.files.map(x => x.weight))].sort((a, b) => a - b);
  }
  const hasItalic = fam => { const f = files[firstFamily(fam)]; return !f || f.files.some(x => x.style === 'italic'); };

  /** Schriftwahl → Stapel mit Ersatz (für die Website und Desktop-Programme ohne die Schrift) */
  const stackOf = v => files[v] || siteFonts.includes(v) ? `${/\s/.test(v) ? `'${v}'` : v}, ${/serif$/i.test(v) && !/sans/i.test(v) ? 'serif' : 'sans-serif'}` : v;

  function refresh() {
    const el = first();
    G.el.hidden = !el && ed.tool !== 'text';
    if (G.el.hidden) return;
    const o = ed.opt;
    const fam = el ? getProp(el, 'font-family') : o.fontFamily;
    const fs = el ? parseFloat(getProp(el, 'font-size')) || 16 : o.fontSize;
    const fw = el ? normW(getProp(el, 'font-weight')) : normW(o.fontWeight);
    const sel = q('[data-tp=font-family]'), ff = firstFamily(fam);
    if (![...sel.options].some(op => op.value === ff || op.value === fam)) sel.insertAdjacentHTML('afterbegin', `<option value="${esc(ff)}">${esc(ff)}</option>`);
    sel.value = [...sel.options].some(op => op.value === fam) ? fam : ff;
    const ws = weightsFor(fam);
    q('[data-tp=font-weight]').innerHTML = ws.map(w => `<option value="${w}"${w === fw ? ' selected' : ''}>${w} ${esc(WEIGHTS[w]?.() || '')}</option>`).join('');
    q('[data-tp=font-size]').value = num(fs, 2);
    const ls = el ? parseFloat(getProp(el, 'letter-spacing', false)) || 0 : 0;
    q('[data-tp=tracking]').value = Math.round(ls / fs * 1000);
    q('[data-tp=kerning]').checked = !el || getProp(el, 'font-kerning', false) !== 'none';
    const st = el ? getProp(el, 'font-style') : 'normal', deco = el ? getProp(el, 'text-decoration', false) : '';
    q('[data-tt=italic]').setAttribute('aria-pressed', String(st === 'italic'));
    q('[data-tt=italic]').disabled = !hasItalic(fam);
    q('[data-tt=underline]').setAttribute('aria-pressed', String(/underline/.test(deco)));
    q('[data-tt="line-through"]').setAttribute('aria-pressed', String(/line-through/.test(deco)));
    const note = q('[data-tnote]');
    note.hidden = !(el && !files[ff] && !/^(system-ui|sans-serif|serif|monospace|Georgia|Arial)$/i.test(ff));
    note.textContent = note.hidden ? '' : t('Für „{font}“ liegt keine Schriftdatei vor – auf der Website erscheint eine Ersatzschrift, „In Kurven umwandeln“ ist nicht möglich.', { font: ff });
    // Absatz
    const an = el ? getProp(el, 'text-anchor') || 'start' : o.anchor;
    G.el.querySelectorAll('[data-anchor]').forEach(b => b.setAttribute('aria-checked', String(b.dataset.anchor === an)));
    q('[data-tp=lh]').value = num(el ? lineHeightOf(el) : 1.2, 2);
    q('[data-tp=frame]').value = el ? num(parseFloat(el.getAttribute('data-ge-frame')) || 0, 1) : 0;
    const tp = el?.querySelector('textPath');
    const pathSel = ed.sel.length === 2 && ed.sel.some(x => x.localName === 'text') && ed.sel.some(x => ['path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'line'].includes(x.localName));
    q('[data-tcmd=onpath]').hidden = !pathSel;
    q('[data-offrow]').hidden = !tp;
    q('[data-tcmd=offpath]').hidden = !tp;
    if (tp) q('[data-tp=offset]').value = parseFloat(tp.getAttribute('startOffset')) || 0;
    q('[data-tp=frame]').disabled = !!tp;
    G.el.querySelectorAll('input,select,button').forEach(i => { if (!el && !['font-family', 'font-weight', 'font-size'].includes(i.dataset.tp) && !i.dataset.anchor) i.disabled = true; else if (i.dataset.tp !== 'frame' && i.dataset.tt !== 'italic') i.disabled = false; });
  }

  G.el.addEventListener('change', e => {
    const i = e.target, k = i.dataset.tp;
    if (!k) return;
    const el = first();
    if (k === 'font-family') { ed.opt.fontFamily = stackOf(i.value); return ed.textProp('font-family', stackOf(i.value)); }
    if (k === 'font-weight') { ed.opt.fontWeight = i.value; return ed.textProp('font-weight', i.value === '400' ? null : i.value); }
    if (k === 'font-size') { ed.opt.fontSize = +i.value; return ed.textProp('font-size', num(Math.max(1, +i.value), 2)); }
    if (!el) return;
    const fs = parseFloat(getProp(el, 'font-size')) || 16;
    if (k === 'tracking') return ed.textProp('letter-spacing', +i.value ? num(fs * +i.value / 1000, 3) : null);
    if (k === 'kerning') return ed.textProp('font-kerning', i.checked ? null : 'none');
    if (k === 'lh') return ed.textPara('lh', Math.max(0.5, Math.min(5, +i.value || 1.2)));
    if (k === 'frame') return ed.textPara('frame', Math.max(0, +i.value || 0));
    if (k === 'offset') return ed.textPara('offset', +i.value || 0);
  });
  G.el.querySelectorAll('[data-tt]').forEach(b => b.addEventListener('click', () => {
    const v = b.dataset.tt, on = b.getAttribute('aria-pressed') === 'true';
    if (v === 'italic') ed.textProp('font-style', on ? null : 'italic');
    else ed.textProp('text-decoration', on ? null : v);
  }));
  G.el.querySelectorAll('[data-case]').forEach(b => b.addEventListener('click', () => ed.textCase(b.dataset.case)));
  G.el.querySelectorAll('[data-anchor]').forEach(b => b.addEventListener('click', () => { ed.opt.anchor = b.dataset.anchor; if (first()) ed.textPara('anchor', b.dataset.anchor); else refresh(); }));
  q('[data-tcmd=onpath]').addEventListener('click', () => ed.textOnPath());
  q('[data-tcmd=offpath]').addEventListener('click', () => ed.textOffPath());

  const api = { el: G.el, refresh, group: G };
  return api;
}
