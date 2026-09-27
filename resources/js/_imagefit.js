import { t } from './_i18n.js';
import { layerBox } from './_shadow.js';
/*
 * Bild im Rahmen (Core\ImageFit) – füllen (zuschneiden), einpassen (mit Hintergrund) oder Originalformat.
 *  - Gleicher Dialog für die Mediathek (Standard des Bildes, Spalte media.fit) und den Seiten-Editor (je Einbindung, data._fit).
 *  - Speicherformat wie in PHP: „cover“, „original“, „contain“ (transparent), „contain blur“, „contain #1e2638“,
 *    „contain kit:surface“; leer = erben (Einbindung → Mediathek → automatisch: SVG/transparenter Rand = einpassen).
 *  - Vorschau mit denselben Klassen wie auf der Website (resources/css/image-fit.css, Teil von admin.css / admin.shadow.css)
 *    in einem Rahmen mit dem Seitenverhältnis der Stelle.
 */
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const MODES = ['cover', 'contain', 'original'];
let presetCache = null;

/** Einstellung zerlegen (tolerant) → { mode: ''|cover|contain|original, bg: ''|blur|#rrggbb|kit:name } */
export function parseFit(v) {
  const [mode = '', bg = ''] = String(v || '').trim().toLowerCase().split(/\s+/);
  if (!MODES.includes(mode)) return { mode: '', bg: '' };
  const okBg = bg === 'blur' || /^#[0-9a-f]{6}$/.test(bg) || /^kit:[a-z][a-z0-9_]*$/.test(bg);
  return { mode, bg: mode === 'contain' && okBg ? bg : '' };
}
/** Kanonische Schreibweise ('' = erben) */
export const fitString = o => (o.mode ? o.mode + (o.mode === 'contain' && o.bg ? ' ' + o.bg : '') : '');

/** Kurzbeschreibung, z. B. „Einpassen · unscharf“ */
export function fitLabel(v, presets = presetCache || []) {
  const o = typeof v === 'object' ? v : parseFit(v);
  if (!o.mode) return '';
  if (o.mode === 'cover') return t('Füllen (zuschneiden)');
  if (o.mode === 'original') return t('Originalformat');
  const bg = !o.bg ? t('transparent') : o.bg === 'blur' ? t('unscharf')
    : o.bg.startsWith('kit:') ? (presets.find(p => 'kit:' + p.name === o.bg)?.label || o.bg.slice(4)) : o.bg.toUpperCase();
  return t('Einpassen') + ' · ' + bg;
}

/** Farben des Kits (Core\ImageFit::presets) – einmal je Seite laden */
export async function fitPresets(base) {
  if (presetCache) return presetCache;
  try {
    const r = await fetch(base + '/api/media/fit/options', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    presetCache = (await r.json()).presets || [];
  } catch { presetCache = []; }
  return presetCache;
}

/** Vorschau: Klassen und Variablen wie auf der Website an <picture> setzen */
export function applyFit(pic, o, { thumb, presets = presetCache || [] } = {}) {
  [...pic.classList].filter(c => c.startsWith('img-fit')).forEach(c => pic.classList.remove(c));
  pic.style.removeProperty('--img-fit-bg'); pic.style.removeProperty('--img-fit-src');
  if (o.mode !== 'contain' && o.mode !== 'original') return;
  pic.classList.add('img-fit', 'img-fit--' + o.mode);
  if (o.mode !== 'contain') return;
  if (o.bg === 'blur') { pic.classList.add('img-fit--blur'); pic.style.setProperty('--img-fit-src', `url("${String(thumb || '').replace(/["\\\n]/g, encodeURIComponent)}")`); }
  else if (o.bg.startsWith('#')) pic.style.setProperty('--img-fit-bg', o.bg);
  else if (o.bg.startsWith('kit:')) { const p = presets.find(x => 'kit:' + x.name === o.bg); if (p?.value) pic.style.setProperty('--img-fit-bg', p.value); }
}

/**
 * Dialog „Darstellung im Rahmen“.
 * opts: { src, thumb, name, scope: 'global' | 'place', value, global (nur 'place': Standard aus der Mediathek),
 *         auto ('contain' bei SVG/transparentem Rand), svg, ratio (Rahmen, z. B. '16:10' oder Zahl), base (Admin-URL), note, onApply(value) }
 * Ergebnis: gespeicherter Wert – bei 'place' null = wie in der Mediathek –, undefined bei Abbruch.
 */
export function fitDialog(opts) {
  return new Promise(async resolve => {
    const presets = await fitPresets(opts.base || '/admin');
    const box = layerBox();
    let dlg = box.querySelector('#ifit-dlg');
    if (!dlg) { box.insertAdjacentHTML('beforeend', '<dialog id="ifit-dlg" class="adm-dialog ifx-dlg ifit-dlg" aria-labelledby="ifit-title"></dialog>'); dlg = box.querySelector('#ifit-dlg'); }
    const place = opts.scope === 'place';
    const glob = place ? String(opts.global || '') : '';
    const autoV = String(opts.auto || '');
    let st = parseFit(opts.value);
    let custom = st.bg.startsWith('#') ? st.bg : '#ffffff';
    let result;
    const ratio = typeof opts.ratio === 'number' ? opts.ratio : (/^\d+(\.\d+)?[:/]\d+(\.\d+)?$/.test(String(opts.ratio || '')) ? String(opts.ratio).split(/[:/]/).reduce((a, b) => a / b) : 0);
    const inheritLabel = () => {
      const eff = glob || autoV;
      if (place && glob) return t('Wie in der Mediathek: {label}', { label: fitLabel(glob, presets) });
      return eff ? t('Automatisch: {label}', { label: fitLabel(eff, presets) }) + ' – ' + (opts.svg ? t('SVG-Grafik') : t('transparenter Rand')) : t('Automatisch: Füllen (zuschneiden) – wie im Kit');
    };
    const modeCard = (v, label, help) => `<label class="ifit-mode"><input type="radio" name="ifit-mode" value="${v}"><span class="ifit-mode__l">${esc(label)}</span><span class="ifit-mode__h">${esc(help)}</span></label>`;
    const bgChip = (v, label, sw = '') => `<label class="ifit-bg" title="${esc(label)}"><input type="radio" name="ifit-bg" value="${esc(v)}"><span class="ifit-bg__sw ${sw}" data-sw="${esc(v)}"></span><span class="ifit-bg__l">${esc(label)}</span></label>`;
    dlg.innerHTML = `
      <div class="ifx-head"><h2 id="ifit-title">${esc(t('Darstellung im Rahmen'))} <span>${esc(opts.name || '')}</span></h2></div>
      <p class="ifx-scope">${esc(place ? t('Gilt nur für diese Stelle auf der Seite. Der Standard in der Mediathek bleibt unverändert.') : t('Standard für alle Stellen, an denen das Bild verwendet wird – außer Stellen mit eigener Einstellung.'))}${opts.note ? ' ' + esc(opts.note) : ''}</p>
      <div class="ifx-body">
        <div class="ifit-stage">
          <div class="ifit-frame${ratio ? '' : ' ifit-frame--free'}"><picture><img src="${esc(opts.src)}" alt="" draggable="false"></picture></div>
          <p class="f-help ifit-ratio">${ratio ? esc(t('Vorschau im Rahmen dieser Stelle ({ratio})', { ratio: typeof opts.ratio === 'number' ? ratio.toFixed(2).replace('.', ',') + ' : 1' : String(opts.ratio) })) : esc(t('Beispielrahmen 16:10 – die Stelle hat kein festes Format.'))}</p>
        </div>
        <div class="ifx-ctrls">
          <fieldset class="ifit-modes"><legend>${esc(t('Darstellung'))}</legend>
            ${modeCard('', place ? t('Standard') : t('Automatisch'), '')}
            ${modeCard('cover', t('Füllen (zuschneiden)'), t('Rahmen ganz gefüllt, Ränder werden abgeschnitten (Fokuspunkt, Zuschnitt).'))}
            ${modeCard('contain', t('Einpassen'), t('Ganzes Bild im Rahmen, freie Fläche mit Hintergrund.'))}
            ${modeCard('original', t('Originalformat'), t('Rahmen im Seitenverhältnis des Bildes – nichts wird abgeschnitten.'))}
          </fieldset>
          <fieldset class="ifit-bgs" data-bgs><legend>${esc(t('Hintergrund beim Einpassen'))}</legend>
            ${bgChip('', t('Transparent'), 'ifit-bg__sw--none')}
            ${bgChip('blur', t('Unscharf (Bild)'), 'ifit-bg__sw--blur')}
            ${presets.map(p => bgChip('kit:' + p.name, p.label)).join('')}
            <label class="ifit-bg ifit-bg--custom"><input type="radio" name="ifit-bg" value="custom"><span class="ifit-bg__sw" data-sw="custom"></span><span class="ifit-bg__l">${esc(t('Eigene Farbe'))}</span></label>
            <input type="color" class="ifit-color" value="${esc(custom)}" aria-label="${esc(t('Eigene Farbe wählen'))}" data-color>
          </fieldset>
          <p class="ifx-state" aria-live="polite"></p>
        </div>
      </div>
      <p class="ifx-err f-error" role="alert" hidden></p>
      <div class="ifx-foot">
        <button type="button" class="adm-btn adm-btn--ghost" data-cancel>${esc(t('Abbrechen'))}</button>
        <button type="button" class="adm-btn adm-btn--primary" data-apply>${esc(t('Übernehmen'))}</button>
      </div>`;
    const $ = s => dlg.querySelector(s), $$ = s => [...dlg.querySelectorAll(s)];
    const frame = $('.ifit-frame'), pic = $('.ifit-frame picture'), state = $('.ifx-state'), err = $('.ifx-err'), color = $('[data-color]');
    frame.style.aspectRatio = String(ratio || 1.6);
    frame.style.width = `min(100%, calc(56vh * ${ratio || 1.6}))`;   // hohe Rahmen (Hochformat) nicht zu groß
    // Farbfelder der Vorschläge (Farbe als Variable, wie auf der Website)
    $$('[data-sw^="kit:"]').forEach(s => { const p = presets.find(x => 'kit:' + x.name === s.dataset.sw); if (p) s.style.background = p.value; });
    $('[data-sw="blur"]').style.backgroundImage = `url("${String(opts.thumb || opts.src).replace(/["\\\n]/g, encodeURIComponent)}")`;
    // Label des Standards (erst jetzt bekannt)
    $('input[name="ifit-mode"][value=""]').closest('label').querySelector('.ifit-mode__h').textContent = inheritLabel();
    const draw = () => {
      $$('input[name="ifit-mode"]').forEach(r => { r.checked = r.value === st.mode; });
      const bgv = st.bg.startsWith('#') ? 'custom' : st.bg;
      $$('input[name="ifit-bg"]').forEach(r => { r.checked = r.value === bgv; });
      $('[data-sw="custom"]').style.background = custom;
      $('[data-bgs]').disabled = st.mode !== 'contain';
      $('[data-bgs]').hidden = st.mode !== 'contain';
      const eff = st.mode ? st : parseFit(glob || autoV);
      applyFit(pic, eff, { thumb: opts.thumb || opts.src, presets });
      state.textContent = st.mode ? t('Hier: {label}', { label: fitLabel(st, presets) }) : inheritLabel();
    };
    $$('input[name="ifit-mode"]').forEach(r => r.addEventListener('change', () => { st = { mode: r.value, bg: r.value === 'contain' ? st.bg : '' }; draw(); }));
    $$('input[name="ifit-bg"]').forEach(r => r.addEventListener('change', () => { st.bg = r.value === 'custom' ? custom : r.value; draw(); }));
    color.addEventListener('input', () => { custom = color.value.toLowerCase(); st.bg = custom; draw(); });
    $('[data-cancel]').addEventListener('click', () => dlg.close());
    $('[data-apply]').addEventListener('click', async e => {
      const btn = e.currentTarget;
      const v = fitString(st);
      const out = place && v === '' ? null : v;
      btn.disabled = true; err.hidden = true;
      try { await opts.onApply?.(out); result = out; dlg.close(); }
      catch (ex) { err.textContent = ex.message; err.hidden = false; btn.disabled = false; }
    });
    dlg.onclose = () => { dlg.innerHTML = ''; resolve(result); };
    draw();
    dlg.showModal();
    ($('input[name="ifit-mode"]:checked') || $('input[name="ifit-mode"]')).focus();
  });
}
