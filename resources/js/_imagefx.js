import { t } from './_i18n.js';
import { layerBox } from './_shadow.js';
/*
 * Bild anpassen (Core\ImageFx) – Effekte, Sättigung, Helligkeit, Kontrast; zerstörungsfrei per CSS-Klassen.
 *  - Gleicher Dialog für die Mediathek (global je Bild) und den Seiten-Editor (je Einbindung im Block, data._fx).
 *  - Speicherformat wie in PHP: Effekt zuerst, dann s/b/c in Prozent ohne Standardwerte, z. B. „sepia s120 c110“;
 *    „none“ = an dieser Stelle ausdrücklich ohne Anpassung (hebt die globale Einstellung auf).
 *  - Vorschau über dieselben Klassen wie auf der Website (resources/css/image-fx.css, Teil von admin.css / admin.shadow.css).
 */
const d = document;
export const FX_PRESETS = ['gray', 'sepia', 'warm', 'cool', 'muted', 'vivid', 'contrast'];
const RANGES = { s: [0, 200], b: [50, 150], c: [50, 150] };
const STEP = 10;
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const presetLabels = () => ({ gray: t('S/W'), sepia: t('Sepia'), warm: t('Warm'), cool: t('Kühl'), muted: t('Entsättigt'), vivid: t('Kräftig'), contrast: t('Kontrast') });

/** Anpassung zerlegen (tolerant: Ungültiges wird ignoriert) → { p, s, b, c, none } */
export function parseFx(v) {
  const o = { p: '', s: 100, b: 100, c: 100, none: false };
  for (const tok of String(v || '').toLowerCase().split(/[\s,;]+/)) {
    if (tok === 'none') o.none = true;
    else if (FX_PRESETS.includes(tok)) o.p = tok;
    else {
      const m = tok.match(/^([sbc])(\d{1,3})$/);
      if (m) { const n = +m[2], [min, max] = RANGES[m[1]]; if (n >= min && n <= max && n % STEP === 0) o[m[1]] = n; }
    }
  }
  return o;
}
/** Kanonische Schreibweise ('' = keine Anpassung) */
export function fxString(o) {
  if (o.none) return 'none';
  return [o.p, ...['s', 'b', 'c'].filter(k => o[k] !== 100).map(k => k + o[k])].filter(Boolean).join(' ');
}
export const isNeutral = o => !o.none && !o.p && o.s === 100 && o.b === 100 && o.c === 100;
/** CSS-Klassen wie Core\ImageFx::classes() */
export function fxClasses(v) {
  const o = typeof v === 'object' ? v : parseFx(v);
  if (o.none || isNeutral(o)) return [];
  return ['ifx', ...(o.p ? ['ifx-' + o.p] : []), ...['s', 'b', 'c'].filter(k => o[k] !== 100).map(k => 'ifx-' + k + o[k] / STEP)];
}
/** Klassen an einem <img> setzen (vorhandene ifx-Klassen ersetzen) */
export function applyFx(img, v) {
  if (!img) return;
  [...img.classList].filter(c => c === 'ifx' || c.startsWith('ifx-')).forEach(c => img.classList.remove(c));
  img.classList.add(...fxClasses(v));
}
/** Kurzbeschreibung, z. B. „Sepia · Sättigung 120 %“ */
export function fxLabel(v) {
  const o = typeof v === 'object' ? v : parseFx(v);
  if (o.none) return t('Ohne Anpassung');
  const L = { s: t('Sättigung'), b: t('Helligkeit'), c: t('Kontrast') };
  return [o.p && presetLabels()[o.p], ...['s', 'b', 'c'].filter(k => o[k] !== 100).map(k => `${L[k]} ${o[k]} %`)].filter(Boolean).join(' · ');
}

/**
 * Dialog „Bild anpassen“.
 * opts: { src, name, scope: 'global' | 'place', value, global (nur 'place': Einstellung der Mediathek), note, onApply(value) → Promise }
 * Ergebnis (Promise): gespeicherter Wert – bei 'place' null = wie in der Mediathek (keine eigene Einstellung) –, undefined bei Abbruch.
 */
export function adjustDialog(opts) {
  return new Promise(resolve => {
    const box = layerBox();
    let dlg = box.querySelector('#ifx-dlg');
    if (!dlg) { box.insertAdjacentHTML('beforeend', '<dialog id="ifx-dlg" class="adm-dialog ifx-dlg" aria-labelledby="ifx-title"></dialog>'); dlg = box.querySelector('#ifx-dlg'); }
    const place = opts.scope === 'place';
    const glob = place ? String(opts.global || '') : '';
    let inherit = place && (opts.value == null || opts.value === '');
    let st = parseFx(inherit ? glob : opts.value);
    if (st.none) st = parseFx('');
    let result;
    const P = presetLabels();
    const range = (k, label, [min, max]) => `<div class="ifx-range">
        <label for="ifx-${k}">${esc(label)}</label><output id="ifx-${k}-o" for="ifx-${k}"></output>
        <input id="ifx-${k}" type="range" min="${min}" max="${max}" step="${STEP}" data-k="${k}"></div>`;
    dlg.innerHTML = `
      <div class="ifx-head"><h2 id="ifx-title">${esc(t('Bild anpassen'))} <span>${esc(opts.name || '')}</span></h2></div>
      <p class="ifx-scope">${esc(place ? t('Gilt nur für diese Stelle auf der Seite. Die Einstellung in der Mediathek bleibt unverändert.') : t('Gilt überall, wo das Bild verwendet wird – außer an Stellen mit eigener Einstellung. Das Original bleibt unverändert.'))}${opts.note ? ' ' + esc(opts.note) : ''}</p>
      <div class="ifx-body">
        <div class="ifx-prev"><img src="${esc(opts.src)}" alt="" draggable="false">
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost ifx-cmp" aria-pressed="false" data-cmp>${esc(t('Original zeigen'))}</button></div>
        <div class="ifx-ctrls">
          <fieldset class="ifx-presets"><legend>${esc(t('Effekt'))}</legend>
            ${['', ...FX_PRESETS].map(p => `<label class="ifx-pre"><input type="radio" name="ifx-p" value="${p}"><span class="ifx-pre__img"><img src="${esc(opts.thumb || opts.src)}" alt="" draggable="false" class="${fxClasses(p).join(' ')}"></span><span class="ifx-pre__l">${esc(p ? P[p] : t('Ohne'))}</span></label>`).join('')}
          </fieldset>
          ${range('s', t('Sättigung'), RANGES.s)}${range('b', t('Helligkeit'), RANGES.b)}${range('c', t('Kontrast'), RANGES.c)}
          <div class="ifx-tools">
            <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-reset>${esc(t('Zurücksetzen'))}</button>
            ${place && glob ? `<button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-inherit>${esc(t('Wie in der Mediathek'))}</button>` : ''}
          </div>
          <p class="ifx-state" aria-live="polite"></p>
        </div>
      </div>
      <p class="ifx-err f-error" role="alert" hidden></p>
      <div class="ifx-foot">
        <button type="button" class="adm-btn adm-btn--ghost" data-cancel>${esc(t('Abbrechen'))}</button>
        <button type="button" class="adm-btn adm-btn--primary" data-apply>${esc(t('Übernehmen'))}</button>
      </div>`;
    const $ = s => dlg.querySelector(s), $$ = s => [...dlg.querySelectorAll(s)];
    const prev = $('.ifx-prev img'), cmp = $('[data-cmp]'), state = $('.ifx-state'), err = $('.ifx-err');
    const draw = () => {
      $$('input[name="ifx-p"]').forEach(r => { r.checked = r.value === st.p; });
      for (const k of ['s', 'b', 'c']) {
        const inp = $('#ifx-' + k);
        inp.value = st[k];
        inp.setAttribute('aria-valuetext', st[k] + ' %');
        $('#ifx-' + k + '-o').textContent = st[k] + ' %';
      }
      applyFx(prev, cmp.getAttribute('aria-pressed') === 'true' ? '' : st);
      const lbl = fxLabel(st);
      state.textContent = place && inherit
        ? (glob ? t('Wie in der Mediathek: {label}', { label: fxLabel(glob) }) : t('Wie in der Mediathek (ohne Anpassung)'))
        : (lbl ? t('Anpassung: {label}', { label: lbl }) : t('Original (ohne Anpassung)'));
    };
    const touched = () => { inherit = false; draw(); };
    $$('input[name="ifx-p"]').forEach(r => r.addEventListener('change', () => { st.p = r.value; touched(); }));
    $$('input[type=range]').forEach(inp => inp.addEventListener('input', () => { st[inp.dataset.k] = +inp.value; touched(); }));
    $('[data-reset]').addEventListener('click', () => { st = parseFx(''); touched(); });
    $('[data-inherit]')?.addEventListener('click', () => { st = parseFx(glob); inherit = true; draw(); });
    cmp.addEventListener('click', () => { cmp.setAttribute('aria-pressed', cmp.getAttribute('aria-pressed') === 'true' ? 'false' : 'true'); draw(); });
    $('[data-cancel]').addEventListener('click', () => dlg.close());
    $('[data-apply]').addEventListener('click', async e => {
      const btn = e.currentTarget;
      // Einbindung: „wie Mediathek“ → keine eigene Einstellung (null); neutral bei vorhandener Mediathek-Einstellung → „none“
      const v = place ? (inherit ? null : (isNeutral(st) ? (glob ? 'none' : null) : fxString(st))) : fxString(st);
      btn.disabled = true; err.hidden = true;
      try { await opts.onApply?.(v); result = v; dlg.close(); }
      catch (ex) { err.textContent = ex.message; err.hidden = false; btn.disabled = false; }
    });
    dlg.onclose = () => { dlg.innerHTML = ''; resolve(result); };
    draw();
    dlg.showModal();
    ($('input[name="ifx-p"]:checked') || $('input[name="ifx-p"]')).focus();
  });
}
