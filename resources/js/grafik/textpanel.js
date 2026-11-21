/*
 * Grafik-Editor – Bereich „Text“ mit den Reitern Zeichen und Absatz (wie Affinity Designer):
 *   Zeichen  Schrift (Schriftauswahl mit Vorschau, fontpicker.js), Schnitt, Achsen variabler Schriften, Größe, Laufweite,
 *            Unterschneidung, Unterstrichen/Durchgestrichen, Groß-/Kleinschreibung (als echte Zeichen)
 *            Grundlinienversatz, horizontal/vertikal skalieren
 *   Absatz   Ausrichtung inkl. Blocksatz, Zeilen- und Wortabstand, Abstand vor/nach Absätzen, Einzüge, Silbentrennung (DE/EN),
 *            Textrahmen-Breite (Umbruch), Text auf Pfad (Startversatz, lösen)
 *   OpenType Ligaturen, Kapitälchen, Ziffern, Brüche, Hoch-/Tiefstellen, Stilsätze und Zeichenvarianten der Schrift (typo.js)
 * Während der Bearbeitung auf der Fläche wirken Zeichen-Eigenschaften auf die Auswahl im Text; sonst auf die ganzen Texte. MIT
 */
import { t } from '../_i18n.js';
import { esc } from '../_html.js';
import { ico } from '../_icons.js';
import { num } from './geom.js';
import { getProp } from './doc.js';
import { tabGroup, iconBtn, MOD } from './ui.js';
import { lineHeightOf } from './text.js';
import { fontPicker, firstFamily, styleLabel, fontItems, itemFor } from './fontpicker.js';
import { fontInfo, parseVariations, fmtVariations } from './outline.js';
import { FEATURES, RADIO_TAGS, parseFeatures, featureOn, paraOf } from './typo.js';
import { toChars } from './text.js';

export { SYSTEM_FONTS, firstFamily } from './fontpicker.js';

const normW = w => w === 'bold' ? 700 : w === 'normal' || !w ? 400 : parseInt(w, 10) || 400;
/** Bezeichnungen der registrierten Achsen variabler Schriften */
const AXIS_LABEL = { wght: () => t('Gewicht'), wdth: () => t('Breite'), slnt: () => t('Neigung'), ital: () => t('Kursiv'), opsz: () => t('Optische Größe'), GRAD: () => t('Strichstärke (Grade)') };

export function buildTextPanel(ed) {
  const G = tabGroup(ed.uid + '-d', [{ id: 'char', label: t('Zeichen') }, { id: 'para', label: t('Absatz') }, { id: 'ot', label: t('OpenType') }], () => api.refresh());
  G.el.classList.add('ge-pg--text');
  const files = ed.boot.fontFiles || {};
  const items = fontItems(ed.boot);
  const charP = G.panel('char'), paraP = G.panel('para'), otP = G.panel('ot');
  charP.innerHTML = `<div class="ge-form">
    <div class="ge-lbl ge-lbl--wide"><span aria-hidden="true">${esc(t('Schrift'))}</span><span data-fpslot></span></div>
    <label class="ge-lbl">${esc(t('Schnitt'))} <select class="ge-in" data-tp="face"></select></label>
    <div class="ge-axes" data-axes hidden></div>
    <label class="ge-lbl">${esc(t('Größe'))} <input type="number" class="ge-in ge-in--s" data-tp="font-size" min="1" max="2000" step="1"></label>
    <label class="ge-lbl">${esc(t('Laufweite'))} <input type="number" class="ge-in ge-in--s" data-tp="tracking" min="-500" max="2000" step="10" title="${esc(t('Tausendstel der Schriftgröße'))}"> ‰</label>
    <div class="ge-lbl">${esc(t('Stil'))} <span class="ge-seg" role="group" aria-label="${esc(t('Stil'))}">
      ${iconBtn('text-italic', t('Kursiv'), 'data-tt="italic" aria-pressed="false"', MOD + 'I')}${iconBtn('text-underline', t('Unterstrichen'), 'data-tt="underline" aria-pressed="false"', MOD + 'U')}${iconBtn('text-strikethrough', t('Durchgestrichen'), 'data-tt="line-through" aria-pressed="false"')}</span></div>
    <div class="ge-lbl">${esc(t('Schreibung'))} <span class="ge-seg" role="group" aria-label="${esc(t('Groß- und Kleinschreibung'))}">
      <button type="button" class="ge-ib ge-ib--txt" data-case="upper" title="${esc(t('GROSSBUCHSTABEN'))}" aria-label="${esc(t('Großbuchstaben'))}">AB</button>
      <button type="button" class="ge-ib ge-ib--txt" data-case="lower" title="${esc(t('kleinbuchstaben'))}" aria-label="${esc(t('Kleinbuchstaben'))}">ab</button>
      <button type="button" class="ge-ib ge-ib--txt" data-case="title" title="${esc(t('Wortanfänge groß'))}" aria-label="${esc(t('Wortanfänge groß'))}">Ab</button></span></div>
    <label class="ge-lbl ge-chk"><input type="checkbox" data-tp="kerning"> ${esc(t('Unterschneidung (Kerning) der Schrift'))}</label>
    <label class="ge-lbl">${esc(t('Grundlinienversatz'))} <input type="number" class="ge-in ge-in--s" data-tp="bshift" min="-1000" max="1000" step="0.5" title="${esc(t('Verschiebt die markierten Zeichen nach oben (positiv) oder unten'))}"></label>
    <div class="ge-row4"><label>${esc(t('Breite'))} <span class="ge-cp__num"><input type="number" class="ge-in ge-in--xs" data-tp="sx" min="1" max="1000" step="1" aria-label="${esc(t('Horizontal skalieren in Prozent'))}">%</span></label>
      <label>${esc(t('Höhe'))} <span class="ge-cp__num"><input type="number" class="ge-in ge-in--xs" data-tp="sy" min="1" max="1000" step="1" aria-label="${esc(t('Vertikal skalieren in Prozent'))}">%</span></label></div>
    <p class="ge-note" data-tnote hidden></p>
  </div>`;
  paraP.innerHTML = `<div class="ge-form">
    <div class="ge-lbl">${esc(t('Ausrichtung'))} <span class="ge-seg" role="radiogroup" aria-label="${esc(t('Ausrichtung'))}">${[['start', 'text-align-left', t('Links')], ['middle', 'text-align-center', t('Mitte')], ['end', 'text-align-right', t('Rechts')], ['justify', 'text-align-justify', t('Blocksatz (nur Textrahmen)')]]
      .map(([v, i, l]) => `<button type="button" class="ge-ib" role="radio" data-anchor="${v}" aria-checked="false" aria-label="${esc(l)}" title="${esc(l)}">${ico(i)}</button>`).join('')}</span></div>
    <label class="ge-lbl">${esc(t('Zeilenabstand'))} <input type="number" class="ge-in ge-in--s" data-tp="lh" min="0.5" max="5" step="0.05"> ×</label>
    <label class="ge-lbl">${esc(t('Wortabstand'))} <input type="number" class="ge-in ge-in--s" data-tp="ws" min="-100" max="1000" step="0.5"></label>
    <div class="ge-row4"><label>${esc(t('Abstand vor'))} <input type="number" class="ge-in ge-in--xs" data-tp="pb" min="0" max="1000" step="1" aria-label="${esc(t('Abstand vor Absätzen'))}"></label>
      <label>${esc(t('Abstand nach'))} <input type="number" class="ge-in ge-in--xs" data-tp="pa" min="0" max="1000" step="1" aria-label="${esc(t('Abstand nach Absätzen'))}"></label>
      <label>${esc(t('Einzug links'))} <input type="number" class="ge-in ge-in--xs" data-tp="li" min="0" max="5000" step="1"></label>
      <label>${esc(t('Einzug rechts'))} <input type="number" class="ge-in ge-in--xs" data-tp="ri" min="0" max="5000" step="1"></label>
      <label>${esc(t('Erste Zeile'))} <input type="number" class="ge-in ge-in--xs" data-tp="fi" min="-5000" max="5000" step="1" aria-label="${esc(t('Einzug der ersten Zeile'))}"></label></div>
    <label class="ge-lbl">${esc(t('Silbentrennung'))} <select class="ge-in" data-tp="hy"><option value="">${esc(t('Aus'))}</option><option value="de">${esc(t('Deutsch'))}</option><option value="en">${esc(t('Englisch'))}</option></select></label>
    <label class="ge-lbl">${esc(t('Textrahmen'))} <input type="number" class="ge-in ge-in--s" data-tp="frame" min="0" max="10000" step="1" title="${esc(t('Breite für den automatischen Umbruch, 0 = ohne Rahmen'))}"></label>
    <p class="f-help ge-help">${esc(t('Mit dem Text-Werkzeug einen Rahmen aufziehen: Zeilen brechen automatisch um. 0 = Punkttext ohne Umbruch.'))}</p>
    <div data-pathbox>
      <button type="button" class="adm-btn adm-btn--small" data-tcmd="onpath">${ico('path')} ${esc(t('Text auf Pfad'))}</button>
      <label class="ge-lbl" data-offrow>${esc(t('Startversatz'))} <input type="number" class="ge-in ge-in--s" data-tp="offset" min="-100" max="100" step="1"> %</label>
      <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-tcmd="offpath">${esc(t('Vom Pfad lösen'))}</button>
    </div>
  </div>`;

  // ---------------------------------------------------------------- OpenType
  otP.innerHTML = `<div class="ge-form ge-ot" data-ot></div>`;
  const otBox = otP.querySelector('[data-ot]');
  let otKey = '';
  /** Merkmale des Ziels: markierte Zeichen (erstes Zeichen) bzw. der Text */
  function featuresNow(el) {
    const te = ed.textEd;
    if (te && te.range[0] !== te.range[1]) {
      const ch = toChars(te.model)[te.range[0]];
      return parseFeatures(ch?.s['font-feature-settings'] ?? getProp(el, 'font-feature-settings', false));
    }
    return parseFeatures(getProp(el, 'font-feature-settings', false));
  }
  const posNow = el => {
    const f = featuresNow(el);
    if (f.sups) return 'sups';
    if (f.subs) return 'subs';
    const te = ed.textEd, ch = te && te.range[0] !== te.range[1] ? toChars(te.model)[te.range[0]] : null;
    const bs = ch ? ch.s['baseline-shift'] : null;
    return bs === 'super' ? 'sups' : bs === 'sub' ? 'subs' : '';
  };
  async function refreshOT(el, fam) {
    if (G.current() !== 'ot') return;
    const it = itemFor(items, fam);
    let feats = null, info = null;
    if (it?.hasFile) { try { info = await fontInfo(ed, it.family); feats = info?.features || null; } catch { /* ohne Datei */ } }
    const f = el ? featuresNow(el) : {};
    const has = tag => !feats || feats.has(tag);
    const key = JSON.stringify([it?.key, [...(feats?.keys() || [])], f, el ? posNow(el) : '', !!el]);
    if (key === otKey && otBox.childElementCount) return;
    otKey = key;
    const dis = el ? '' : ' disabled';
    let h = !it?.hasFile ? `<p class="ge-note">${esc(t('Systemschrift: welche Merkmale wirken, hängt vom Gerät ab. Mit einer Schrift der Website zeigt die Liste genau, was die Schrift kann.'))}</p>` : '';
    for (const g of FEATURES()) {
      if (g.items) {
        const list = g.items.filter(([tag]) => has(tag));
        if (!list.length) continue;
        h += `<fieldset class="ge-ot__grp"><legend class="ge-h3">${esc(g.label)}</legend>${list.map(([tag, l]) => `<label class="ge-lbl ge-chk"><input type="checkbox" data-feat="${tag}"${featureOn(f, tag) ? ' checked' : ''}${dis}> ${esc(l)} <code class="ge-ot__tag">${tag}</code></label>`).join('')}</fieldset>`;
      } else {
        const opts = g.radio.filter(([tag]) => !tag || has(tag) || (g.id === 'pos'));
        if (opts.length < 2) continue;
        const cur = g.id === 'pos' ? (el ? posNow(el) : '') : (RADIO_TAGS[g.id].find(tg => f[tg]) || '');
        h += `<label class="ge-lbl">${esc(g.label)} <select class="ge-in" data-featgrp="${g.id}"${dis}>${opts.map(([tag, l]) => `<option value="${tag}"${tag === cur ? ' selected' : ''}>${esc(l)}${g.id === 'pos' && tag && feats && !feats.has(tag) ? ' ' + esc(t('(nachgebildet)')) : ''}</option>`).join('')}</select></label>`;
      }
    }
    // Stilsätze und Zeichenvarianten – nur, was die Schrift hat (Namen aus der Schrift, falls vorhanden)
    for (const [prefix, label] of [['ss', t('Stilsätze')], ['cv', t('Zeichenvarianten')]]) {
      const tags = feats ? [...feats.keys()].filter(k => new RegExp('^' + prefix + '\\d\\d$').test(k)).sort() : [];
      if (!tags.length) continue;
      h += `<fieldset class="ge-ot__grp"><legend class="ge-h3">${esc(label)}</legend>${tags.map(tag => `<label class="ge-lbl ge-chk"><input type="checkbox" data-feat="${tag}"${f[tag] ? ' checked' : ''}${dis}> ${esc(feats.get(tag) || (prefix === 'ss' ? t('Stilsatz {n}', { n: +tag.slice(2) }) : t('Zeichenvariante {n}', { n: +tag.slice(2) })))} <code class="ge-ot__tag">${tag}</code></label>`).join('')}</fieldset>`;
    }
    otBox.innerHTML = h || `<p class="ge-note">${esc(t('Diese Schrift hat keine wählbaren OpenType-Merkmale.'))}</p>`;
    otBox.querySelectorAll('[data-feat]').forEach(c => c.addEventListener('change', () => {
      const tag = c.dataset.feat;
      ed.textFeature({ [tag]: c.checked ? 1 : 0 });
    }));
    otBox.querySelectorAll('[data-featgrp]').forEach(s => s.addEventListener('change', () => {
      const g = s.dataset.featgrp, v = s.value;
      if (g === 'pos') return ed.textPosition(v, !!v && (!feats || feats.has(v)));
      const patch = {};
      for (const tag of RADIO_TAGS[g]) patch[tag] = null;
      if (v === 'c2sc') { patch.c2sc = 1; patch.smcp = 1; } else if (v) patch[v] = 1;
      ed.textFeature(patch);
    }));
  }

  const fp = fontPicker(ed, { label: t('Schrift'), prefs: () => ed.prefs, setPref: (k, v) => ed.setPref(k, v),
    onPreview: p => ed.textPreview(p), onPick: p => ed.textProps(p) });
  charP.querySelector('[data-fpslot]').replaceWith(fp.el);

  const texts = () => ed.textEl ? [ed.textEl] : ed.sel.filter(el => el.localName === 'text');
  const first = () => texts()[0] || null;
  const q = s => G.el.querySelector(s);

  // ---------------------------------------------------------------- Achsen variabler Schriften
  const axesBox = q('[data-axes]');
  let axesFor = '';
  /** Achsen der Schrift: aus der Datei (fontkit, nachgeladen), bis dahin aus dem Schriften-Manager */
  async function refreshAxes(el, fam) {
    const it = itemFor(items, fam);
    const key = (it?.key || '') + '|' + (el ? getProp(el, 'font-weight') + getProp(el, 'font-variation-settings', false) : '');
    if (!it || !it.variable) { axesBox.hidden = true; axesBox.innerHTML = ''; axesFor = ''; return; }
    if (axesFor === key && !axesBox.hidden) return;
    axesFor = key;
    let axes = it.axes || {};
    try { const info = await fontInfo(ed, it.family); if (info?.axes && Object.keys(info.axes).length) axes = info.axes; } catch { /* Angaben des Schriften-Managers */ }
    if (axesFor !== key) return;
    const vs = el ? parseVariations(getProp(el, 'font-variation-settings', false)) : {};
    const fw = el ? normW(getProp(el, 'font-weight')) : normW(ed.opt.fontWeight);
    const tags = Object.keys(axes);
    if (!tags.length) { axesBox.hidden = true; return; }
    axesBox.hidden = false;
    axesBox.innerHTML = `<p class="ge-h3">${esc(t('Achsen der variablen Schrift'))}</p>` + tags.map(tag => {
      const a = axes[tag], v = tag === 'wght' ? fw : (vs[tag] ?? a.default);
      const step = a.max - a.min <= 2 ? 0.1 : 1;
      const lbl = AXIS_LABEL[tag]?.() || tag;
      return `<div class="ge-axis"><label class="ge-lbl" for="${ed.uid}-ax-${tag}">${esc(lbl)} <span class="ge-axis__tag">${esc(tag)}</span></label>
        <input type="range" class="ge-range" id="${ed.uid}-ax-${tag}" data-axis="${esc(tag)}" min="${a.min}" max="${a.max}" step="${step}" value="${v}">
        <input type="number" class="ge-in ge-in--s" data-axisn="${esc(tag)}" min="${a.min}" max="${a.max}" step="${step}" value="${num(v, 1)}" aria-label="${esc(lbl)}"></div>`;
    }).join('');
    const val = () => {
      const o = {};
      axesBox.querySelectorAll('[data-axis]').forEach(r => { o[r.dataset.axis] = +r.value; });
      return o;
    };
    const props = (o) => {
      const rest = { ...o }; delete rest.wght;
      // nur Abweichungen vom Standard schreiben (kompakte Ausgabe)
      for (const k of Object.keys(rest)) if (rest[k] === axes[k]?.default) delete rest[k];
      return { 'font-weight': 'wght' in o ? (o.wght === 400 ? null : num(o.wght, 1)) : undefined, 'font-variation-settings': fmtVariations(rest) || null };
    };
    const clean = p => Object.fromEntries(Object.entries(p).filter(([, v]) => v !== undefined));
    axesBox.querySelectorAll('[data-axis]').forEach(r => {
      r.addEventListener('input', () => { axesBox.querySelector(`[data-axisn="${r.dataset.axis}"]`).value = num(+r.value, 1); ed.textPreview(clean(props(val()))); });
      r.addEventListener('change', () => { axesFor = ''; ed.textProps(clean(props(val()))); });
    });
    axesBox.querySelectorAll('[data-axisn]').forEach(n => n.addEventListener('change', () => {
      const r = axesBox.querySelector(`[data-axis="${n.dataset.axisn}"]`);
      r.value = n.value; axesFor = ''; ed.textProps(clean(props(val())));
    }));
  }

  function refresh() {
    const el = first();
    G.el.hidden = !el && ed.tool !== 'text';
    if (G.el.hidden) { fp.close(true); return; }
    const o = ed.opt;
    const fam = el ? getProp(el, 'font-family') : o.fontFamily;
    const fs = el ? parseFloat(getProp(el, 'font-size')) || 16 : o.fontSize;
    const fw = el ? normW(getProp(el, 'font-weight')) : normW(o.fontWeight);
    const ital = el ? /italic|oblique/.test(getProp(el, 'font-style')) : false;
    const ff = firstFamily(fam);
    if (!fp.isOpen && document.activeElement !== fp.input) fp.set(fam);
    // Schnitte der Schrift (Gewicht + kursiv), dazu der aktuelle, falls die Schrift ihn nicht hat
    const it = itemFor(items, fam);
    const styles = it ? [...it.styles] : [400, 700].flatMap(w => [{ w, i: false }, { w, i: true }]);
    if (!styles.some(s => s.w === fw && s.i === ital)) styles.push({ w: fw, i: ital });
    styles.sort((a, b) => a.w - b.w || +a.i - +b.i);
    const face = q('[data-tp=face]');
    if (document.activeElement !== face) face.innerHTML = styles.map(s => `<option value="${s.w}|${s.i ? 1 : 0}"${s.w === fw && s.i === ital ? ' selected' : ''}>${s.w} ${esc(styleLabel(s))}</option>`).join('');
    refreshAxes(el, fam);
    refreshOT(el, fam);
    const pa = el ? paraOf(el) : { al: '', pb: 0, pa: 0, li: 0, ri: 0, fi: 0, hy: '' };
    for (const k of ['pb', 'pa', 'li', 'ri', 'fi']) { const i = q(`[data-tp=${k}]`); if (document.activeElement !== i) i.value = num(pa[k], 2); }
    q('[data-tp=hy]').value = pa.hy || '';
    q('[data-tp=ws]').value = el ? num(parseFloat(getProp(el, 'word-spacing', false)) || 0, 2) : 0;
    const te = ed.textEd, rch = te && te.range[0] !== te.range[1] ? toChars(te.model)[te.range[0]] : null;
    const bs = rch ? rch.s['baseline-shift'] : el ? getProp(el, 'baseline-shift', false) : '';
    q('[data-tp=bshift]').value = /^-?[\d.]+$/.test(bs || '') ? num(parseFloat(bs), 2) : 0;
    q('[data-tp=sx]').value = el ? num(parseFloat(el.getAttribute('data-ge-sx')) || 100, 1) : 100;
    q('[data-tp=sy]').value = el ? num(parseFloat(el.getAttribute('data-ge-sy')) || 100, 1) : 100;
    q('[data-tp=font-size]').value = num(fs, 2);
    const ls = el ? parseFloat(getProp(el, 'letter-spacing', false)) || 0 : 0;
    q('[data-tp=tracking]').value = Math.round(ls / fs * 1000);
    q('[data-tp=kerning]').checked = !el || getProp(el, 'font-kerning', false) !== 'none';
    const deco = el ? getProp(el, 'text-decoration', false) : '';
    q('[data-tt=italic]').setAttribute('aria-pressed', String(ital));
    q('[data-tt=italic]').disabled = !!it && !it.styles.some(s => s.i);
    q('[data-tt=underline]').setAttribute('aria-pressed', String(/underline/.test(deco)));
    q('[data-tt="line-through"]').setAttribute('aria-pressed', String(/line-through/.test(deco)));
    const note = q('[data-tnote]');
    note.hidden = !(el && !files[ff] && !/^(system-ui|sans-serif|serif|monospace|Georgia|Arial)$/i.test(ff));
    note.textContent = note.hidden ? '' : t('Für „{font}“ liegt keine Schriftdatei vor – auf der Website erscheint eine Ersatzschrift, „In Kurven umwandeln“ ist nicht möglich.', { font: ff });
    // Absatz
    const an = el ? (paraOf(el).al === 'justify' ? 'justify' : getProp(el, 'text-anchor') || 'start') : o.anchor;
    G.el.querySelectorAll('[data-anchor]').forEach(b => b.setAttribute('aria-checked', String(b.dataset.anchor === an)));
    q('[data-tp=lh]').value = num(el ? lineHeightOf(el) : 1.2, 2);
    q('[data-tp=frame]').value = el ? num(parseFloat(el.getAttribute('data-ge-frame')) || 0, 1) : 0;
    const tp = el?.querySelector('textPath');
    const pathSel = ed.sel.length === 2 && ed.sel.some(x => x.localName === 'text') && ed.sel.some(x => ['path', 'rect', 'circle', 'ellipse', 'polygon', 'polyline', 'line'].includes(x.localName));
    q('[data-tcmd=onpath]').hidden = !pathSel;
    q('[data-offrow]').hidden = !tp;
    q('[data-tcmd=offpath]').hidden = !tp;
    if (tp) q('[data-tp=offset]').value = parseFloat(tp.getAttribute('startOffset')) || 0;
    // ohne Text: nur Vorgaben für neue Texte (Schrift, Schnitt, Größe, Ausrichtung)
    G.el.querySelectorAll('input:not(.ge-fp__in),select,button').forEach(i => {
      if (!el && !['face', 'font-size'].includes(i.dataset.tp) && !i.dataset.anchor && !i.dataset.axis && !i.dataset.axisn) i.disabled = true;
      else if (i.dataset.tp !== 'frame' && i.dataset.tt !== 'italic') i.disabled = false;
    });
    q('[data-tp=frame]').disabled = !el || !!tp;
  }

  G.el.addEventListener('change', e => {
    const i = e.target, k = i.dataset.tp;
    if (!k) return;
    const el = first();
    if (k === 'face') {
      const [w, it] = i.value.split('|');
      ed.opt.fontWeight = w;
      return ed.textProps({ 'font-weight': w === '400' ? null : w, 'font-style': it === '1' ? 'italic' : null });
    }
    if (k === 'font-size') { ed.opt.fontSize = +i.value; return ed.textProp('font-size', num(Math.max(1, +i.value), 2)); }
    if (!el) return;
    const fs = parseFloat(getProp(el, 'font-size')) || 16;
    if (k === 'tracking') return ed.textProp('letter-spacing', +i.value ? num(fs * +i.value / 1000, 3) : null);
    if (k === 'kerning') return ed.textProp('font-kerning', i.checked ? null : 'none');
    if (k === 'lh') return ed.textPara('lh', Math.max(0.5, Math.min(5, +i.value || 1.2)));
    if (k === 'frame') return ed.textPara('frame', Math.max(0, +i.value || 0));
    if (k === 'offset') return ed.textPara('offset', +i.value || 0);
    if (['ws', 'pb', 'pa', 'li', 'ri', 'fi'].includes(k)) return ed.textPara(k, +i.value || 0);
    if (k === 'hy') return ed.textPara('hy', i.value);
    if (k === 'bshift') return ed.textProp('baseline-shift', +i.value ? num(+i.value, 2) : null);
    if (k === 'sx' || k === 'sy') return ed.textScale(k === 'sx' ? 'x' : 'y', +i.value || 100);
  });
  G.el.querySelectorAll('[data-tt]').forEach(b => b.addEventListener('click', () => {
    const v = b.dataset.tt, on = b.getAttribute('aria-pressed') === 'true';
    if (v === 'italic') ed.textProp('font-style', on ? null : 'italic');
    else ed.textProp('text-decoration', on ? null : v);
  }));
  G.el.querySelectorAll('[data-case]').forEach(b => b.addEventListener('click', () => ed.textCase(b.dataset.case)));
  G.el.querySelectorAll('[data-anchor]').forEach(b => b.addEventListener('click', () => { ed.opt.anchor = b.dataset.anchor === 'justify' ? 'start' : b.dataset.anchor; if (first()) ed.textPara('anchor', b.dataset.anchor); else refresh(); }));
  q('[data-tcmd=onpath]').addEventListener('click', () => ed.textOnPath());
  q('[data-tcmd=offpath]').addEventListener('click', () => ed.textOffPath());

  const api = { el: G.el, refresh, group: G, picker: fp };
  return api;
}
