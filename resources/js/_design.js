/*
 * Verwaltung → Design (Style-Editor): Farbwähler ↔ Hex, Schieberegler ↔ Zahl, Schriftprobe, Kontrastprüfung (WCAG),
 * Vorlagen, Verwerfen/Standard, Verlauf, Export/Import und Live-Vorschau (hell/dunkel).
 */
import { t } from './_i18n.js';
import { livePreview, store } from './_preview.js';

const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => [...c.querySelectorAll(s)];

// ------------------------------------------------------------ Kontrast (wie Core\Design::contrast)
const lum = hex => {
  const h = hex.replace('#', '');
  const c = [0, 2, 4].map(i => parseInt(h.slice(i, i + 2), 16) / 255).map(x => (x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4));
  return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
};
export const contrast = (a, b) => { const [x, y] = [lum(a), lum(b)]; return Math.round(((Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)) * 100) / 100; };
export const normColor = v => {
  let s = String(v ?? '').trim().toUpperCase();
  if (s && s[0] !== '#') s = '#' + s;
  if (/^#[0-9A-F]{3}$/.test(s)) s = '#' + [...s.slice(1)].map(c => c + c).join('');
  return /^#[0-9A-F]{6}$/.test(s) ? s : null;
};

export function initDesign() {
  const wrap = $('[data-ds]');
  if (!wrap) return;
  let data;
  try { data = JSON.parse($('#ds-data').textContent); } catch { return; }
  const form = $('[data-ds-form]', wrap), stateEl = $('[data-ds-state]', wrap);
  const tokens = {};
  data.schema.groups.forEach(g => g.tokens.forEach(tk => { tokens[tk.name] = { ...tk, group: g.id }; }));
  const fmt = n => n.toLocaleString(document.documentElement.lang || 'de', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

  // ---------------------------------------------------------- Werte lesen/setzen
  const read = () => {
    const out = {};
    for (const [k, v] of new FormData(form)) {
      const m = /^v\[(.+)\]$/.exec(k);
      if (m) out[m[1]] = v;
    }
    for (const [n, tk] of Object.entries(tokens)) {
      if (tk.type === 'bool') out[n] = out[n] === '1';
      if (tk.type === 'range' && n in out) out[n] = parseFloat(out[n]);
      if (tk.type === 'color') { out[n] = normColor(out[n]) || out[n]; if (n + '@dark' in out) out[n + '@dark'] = normColor(out[n + '@dark']) || out[n + '@dark']; }
    }
    return out;
  };
  const field = name => form.elements[`v[${name}]`];

  function set(values) {
    for (const [k, v] of Object.entries(values)) {
      const base = k.replace(/@dark$/, ''), tk = tokens[base];
      if (!tk) continue;
      if (tk.type === 'color') {
        const el = field(k), c = normColor(v);
        if (el && c) { el.value = c; syncPicker(k); }
      } else if (tk.type === 'range') {
        const el = field(k); if (el) { el.value = v; syncNum(k); }
      } else if (tk.type === 'choice') {
        $$(`input[type=radio][name="v[${k}]"]`, form).forEach(r => { r.checked = r.value === String(v); });
      } else if (tk.type === 'bool') {
        const cb = $(`input[type=checkbox][name="v[${k}]"]`, form); if (cb) cb.checked = !!v && v !== '0';
      } else if (tk.type === 'font') {
        const el = field(k); if (el) { el.value = v; syncFont(el); }
      }
    }
    changed();
  }

  // ---------------------------------------------------------- Farbe ↔ Hex, Regler ↔ Zahl, Schriftprobe
  const syncPicker = name => {
    const hex = field(name), pick = $(`[data-ds-picker="${name}"]`, form), c = normColor(hex?.value);
    hex?.classList.toggle('is-bad', !c);
    if (pick && c) pick.value = c.toLowerCase();
  };
  const syncNum = name => { const n = $(`[data-ds-num="${name}"]`, form); if (n) n.value = field(name).value; };
  const syncFont = sel => {
    const box = sel.closest('.ds-font'), s = $('[data-ds-sample]', box), o = sel.selectedOptions[0];
    if (s) s.style.fontFamily = o?.dataset.stack || 'inherit';
    // Installierte Schrift (Core\Fonts): Hinweis bei großem Ladeumfang
    const w = $('[data-ds-fontwarn]', box), kb = +(o?.dataset.kb || 0);
    if (w) { w.hidden = !(kb > +(w.dataset.budget || 150)); if (!w.hidden) w.textContent = (w.dataset.text || '').replace('{kb}', kb); }
  };

  form.addEventListener('input', e => {
    const el = e.target;
    if (el.dataset.dsPicker) { const hex = field(el.dataset.dsPicker); hex.value = el.value.toUpperCase(); hex.classList.remove('is-bad'); }
    else if (el.classList.contains('ds-hex')) syncPicker(/^v\[(.+)\]$/.exec(el.name)[1]);
    else if (el.dataset.dsNum) { const r = field(el.dataset.dsNum); if (el.value !== '') r.value = el.value; }
    else if (el.type === 'range') syncNum(/^v\[(.+)\]$/.exec(el.name)[1]);
    changed();
  });
  form.addEventListener('change', e => { if (e.target.matches('[data-ds-font]')) syncFont(e.target); if (e.target.classList.contains('ds-hex')) { const c = normColor(e.target.value); if (c) e.target.value = c; } changed(); });

  // ---------------------------------------------------------- Kontrast
  const summary = $('[data-ds-summary]', form);
  function checks(v) {
    const out = [];
    const resolve = (ref, dark) => tokens[ref] ? (dark ? (v[ref + '@dark'] ?? v[ref]) : v[ref]) : normColor(ref);
    for (const [n, tk] of Object.entries(tokens)) {
      if (tk.type !== 'color' || !tk.contrast?.with) continue;
      const min = parseFloat(tk.contrast.min ?? 4.5);
      for (const mode of ['light', 'dark']) {
        const fg = normColor(mode === 'light' ? v[n] : v[n + '@dark']);
        const w = mode === 'dark' ? (tk.contrast.dark_with ?? (tokens[tk.contrast.with] ? tk.contrast.with : '')) : tk.contrast.with;
        const bg = w ? normColor(resolve(w, mode === 'dark' && !!tokens[w])) : null;
        if (!fg || !bg) continue;
        const r = contrast(fg, bg), aaa = min >= 4.5 ? 7 : 4.5;
        out.push({ token: n, mode, fg, bg, ratio: r, min, ok: r >= min, level: r >= aaa ? 'AAA' : (r >= min ? 'AA' : 'fail'), with: w });
      }
    }
    return out;
  }
  function paintChecks(v) {
    const list = checks(v);
    $$('.ds-cr', form).forEach(el => { el.innerHTML = ''; });
    $$('[data-ds-tabdot]', form).forEach(d => { d.hidden = true; });
    $$('.ds-tok.is-fail', form).forEach(el => el.classList.remove('is-fail'));
    for (const c of list) {
      const el = $(`.ds-cr[data-cr="${CSS.escape(c.token)}"][data-mode="${c.mode}"]`, form);
      if (!el) continue;
      const partner = tokens[c.with]?.label || c.with;
      const b = document.createElement('span');
      b.className = 'ds-cr__b ' + (c.ok ? (c.level === 'AAA' ? 'is-aaa' : 'is-aa') : 'is-fail');
      b.textContent = `${fmt(c.ratio)}:1 · ${c.ok ? c.level : t('zu schwach')}`;
      b.title = t('Kontrast zu {partner}: {ratio}:1 (mindestens {min}:1)', { partner, ratio: fmt(c.ratio), min: fmt(c.min) });
      el.append(b);
      if (!c.ok) {
        el.closest('.ds-tok')?.classList.add('is-fail');
        const dot = $(`[data-ds-tabdot="${CSS.escape(tokens[c.token].group)}"]`, form); if (dot) dot.hidden = false;
      }
    }
    const fails = list.filter(c => !c.ok).length;
    if (summary) {
      summary.hidden = !list.length;
      summary.className = 'ds-sum ' + (fails ? 'is-fail' : 'is-ok');
      summary.textContent = fails ? (fails === 1 ? t('1 Kontrastproblem') : t('{n} Kontrastprobleme', { n: fails })) : t('Kontrast in Ordnung');
    }
  }
  summary?.addEventListener('click', () => {
    const bad = $('.ds-tok.is-fail', form);
    if (!bad) return;
    $(`[role=tab][data-tab="${CSS.escape(bad.dataset.group)}"]`, form)?.click();
    bad.scrollIntoView({ block: 'center', behavior: 'smooth' });
    $('input:not([type=hidden]), select', bad)?.focus({ preventScroll: true });
  });

  // ---------------------------------------------------------- Zustand: geändert?
  const discardBtn = $('[data-ds-discard]', form);
  const same = (a, b) => Object.keys(b).every(k => String(a[k]) === String(b[k]));
  let dirty = false;
  function changed(quiet = false) {
    const v = read();
    paintChecks(v);
    dirty = !same(v, data.saved);
    discardBtn.disabled = !dirty;
    stateEl.textContent = dirty ? t('Ungespeicherte Änderungen') : '';
    if (!quiet) pv.later(400);
  }
  form.addEventListener('submit', () => { dirty = false; });
  addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  // ---------------------------------------------------------- Vorlagen, Verwerfen, Standard
  $$('[data-ds-preset]', form).forEach(b => b.addEventListener('click', () => {
    const p = data.schema.presets[b.dataset.dsPreset];
    if (!p) return;
    set(p.values);
    $$('[data-ds-preset]', form).forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    stateEl.textContent = t('Vorlage „{name}“ übernommen – mit „Speichern“ veröffentlichen.', { name: p.label });
  }));
  discardBtn.addEventListener('click', () => { set(data.saved); $$('[data-ds-preset]', form).forEach(x => x.removeAttribute('aria-pressed')); });
  $('[data-ds-reset]', form).addEventListener('click', () => {
    set(data.defaults);
    stateEl.textContent = t('Standardwerte des Kits geladen – mit „Speichern“ übernehmen.');
  });

  // ---------------------------------------------------------- Dialoge: Verlauf, Export, Import
  const dlg = name => $(`[data-ds-dialog="${name}"]`);
  $$('[data-ds-open]').forEach(b => b.addEventListener('click', () => {
    const d = dlg(b.dataset.dsOpen);
    if (b.dataset.dsOpen === 'export') $('[data-ds-export]', d).value = JSON.stringify({ theme: data.theme, values: read() }, null, 2);
    d?.showModal();
  }));
  $$('[data-ds-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));
  $$('[data-ds-restore]').forEach(b => b.addEventListener('click', () => {
    set(data.history[+b.dataset.dsRestore] || {});
    b.closest('dialog').close();
    stateEl.textContent = t('Früheren Stand geladen – mit „Speichern“ übernehmen.');
  }));
  $('[data-ds-copy]')?.addEventListener('click', async () => {
    const ta = $('[data-ds-export]'), msg = $('[data-ds-copied]');
    try { await navigator.clipboard.writeText(ta.value); msg.textContent = t('Kopiert.'); }
    catch { ta.select(); msg.textContent = t('Bitte mit Strg/⌘ + C kopieren.'); }
  });
  $('[data-ds-import-go]')?.addEventListener('click', async () => {
    const msg = $('[data-ds-import-msg]'), text = $('[data-ds-import-text]').value.trim();
    msg.className = 'ds-imp-msg';
    if (!text) { msg.textContent = t('Bitte JSON einfügen.'); return; }
    const fd = new FormData();
    fd.set('json', text);
    fd.set('_csrf', form.elements._csrf?.value || '');
    try {
      const r = await fetch(wrap.dataset.dsImport, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
      const j = await r.json();
      if (!j.ok) { msg.classList.add('is-bad'); msg.textContent = j.error || t('Import nicht möglich.'); return; }
      set(j.values);
      dlg('import').close();
      const notes = [t('Import übernommen – mit „Speichern“ veröffentlichen.')];
      if (j.ignored?.length) notes.push(t('Ignoriert: {keys}', { keys: j.ignored.join(', ') }));
      if (j.other_theme) notes.push(t('Hinweis: Export stammt vom Kit „{theme}“.', { theme: j.other_theme }));
      stateEl.textContent = notes.join(' ');
    } catch { msg.classList.add('is-bad'); msg.textContent = t('Import nicht möglich.'); }
  });

  // ---------------------------------------------------------- Vorschau
  const prefs = store('ds');
  let scheme = 'light';
  const pageSel = $('[data-ds-page]', wrap);
  if (pageSel) pageSel.value = prefs.get('page', '') ?? '';
  if (pageSel && pageSel.selectedIndex < 0) pageSel.selectedIndex = 0;
  const pv = livePreview({
    wrap, endpoint: wrap.dataset.stPreview, key: 'ds', openDefault: innerWidth >= 1200,
    body: () => {
      const fd = new FormData(form);
      if (pageSel?.value) fd.set('_page', pageSel.value);
      if (scheme === 'dark') fd.set('_dark', '1');
      return fd;
    },
  });
  pageSel?.addEventListener('change', () => { prefs.set('page', pageSel.value); pv.refresh(); });
  $$('[data-ds-scheme]', wrap).forEach(b => b.addEventListener('click', () => {
    scheme = b.dataset.dsScheme;
    $$('[data-ds-scheme]', wrap).forEach(x => x.setAttribute('aria-pressed', String(x === b)));
    pv.refresh();
  }));

  changed(true);
}
