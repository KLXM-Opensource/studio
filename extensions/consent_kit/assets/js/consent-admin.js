/*
 * KLXM Studio – Consent-Kit, Verwaltung (MIT): Vorlagen filtern, Domain-Matrix speichern,
 * Schlüssel entsperren, Design-Editor mit Live-Vorschau und Kontrastprüfung (WCAG 2.2 AA).
 * Ohne JavaScript funktionieren alle Formulare weiter (Filter zeigen dann die ganze Liste).
 */
const d = document;
const $$ = (s, r = d) => [...r.querySelectorAll(s)];

// ------------------------------------------------------------ Vorlagen: Suche + Gruppe
const filter = d.querySelector('[data-ck-filter]');
if (filter) {
  filter.hidden = false;
  const q = filter.querySelector('[data-ck-q]'), g = filter.querySelector('[data-ck-g]'), count = filter.querySelector('[data-ck-count]');
  const items = $$('.ck-presets > li');
  const apply = () => {
    const term = q.value.trim().toLowerCase();
    let n = 0;
    for (const li of items) {
      const on = (!term || li.dataset.text.includes(term)) && (!g.value || li.dataset.group === g.value);
      li.hidden = !on;
      if (on) n++;
    }
    count.textContent = n + ' / ' + items.length;
  };
  q.addEventListener('input', apply);
  g.addEventListener('change', apply);
  apply();
}

// ------------------------------------------------------------ Domain-Matrix: Haken speichert sofort
let timer;
$$('[data-ck-autosubmit]').forEach(cb => cb.addEventListener('change', () => {
  clearTimeout(timer);
  timer = setTimeout(() => d.getElementById(cb.getAttribute('form'))?.requestSubmit(), 600);
}));

// ------------------------------------------------------------ Schlüssel ändern (nach Rückfrage)
$$('[data-ck-unlock]').forEach(cb => cb.addEventListener('change', () => {
  const input = d.querySelector(cb.dataset.ckUnlock);
  if (!input) return;
  input.readOnly = !cb.checked;
  if (cb.checked) input.focus();
}));

// ------------------------------------------------------------ Design-Editor
const form = d.querySelector('[data-ck-design]');
if (form) {
  const stage = d.querySelector('[data-ck-stage]');
  const hosts = () => $$('consent-kit, consent-embed', stage);
  const hex = v => /^#[0-9a-f]{6}$/i.test(v) ? v : null;
  const lum = h => {
    const c = [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16) / 255).map(x => x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4);
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const values = () => Object.fromEntries($$('[data-key]', form).map(i => [i.dataset.key, i.value]));
  const nf = n => n.toFixed(2).replace('.', ',');

  const update = () => {
    for (const input of $$('[data-var]', form)) {
      const v = input.dataset.unit ? (input.value !== '' ? input.value + input.dataset.unit : '') : hex(input.value);
      for (const h of hosts()) v ? h.style.setProperty(input.dataset.var, v) : h.style.removeProperty(input.dataset.var);
    }
    const vals = values();
    for (const tr of $$('[data-ck-contrast] tbody tr')) {
      const fg = hex(vals[tr.dataset.fg]), bg = hex(vals[tr.dataset.bg]);
      if (!fg || !bg) continue;
      const r = ratio(fg, bg), min = parseFloat(tr.dataset.min), ok = r >= min;
      tr.querySelector('[data-ratio]').textContent = nf(r) + ' : 1';
      const b = tr.querySelector('[data-result] .adm-badge');
      b.classList.toggle('adm-badge--adm-warn', !ok);
      b.textContent = ok ? b.dataset.ok : b.dataset.fail;
    }
    const pre = d.querySelector('[data-ck-css] code');
    if (pre) {
      const lines = $$('[data-key]', form).map(i => '  ' + i.dataset.var + ': ' + i.value + ';');
      pre.textContent = 'consent-kit, consent-embed {\n' + lines.join('\n') + '\n}';
    }
  };
  form.addEventListener('input', e => {
    const t = e.target;
    if (t.type === 'color' && t.dataset.colorFor) { const text = d.getElementById(t.dataset.colorFor); if (text) text.value = t.value.toUpperCase(); }
    else if (t.dataset.key && hex(t.value)) { const pick = form.querySelector(`[data-color-for="${t.id}"]`); if (pick) pick.value = t.value; }
    update();
  });

  // Vorschau-Werkzeuge: Form, Hell/Dunkel, Hinweis/Einstellungen, Mobil
  let view = 'banner';
  const kit = () => stage.querySelector('consent-kit');
  const reopen = () => customElements.whenDefined('consent-kit').then(() => kit().open(view));
  const press = (sel, btn) => $$(sel).forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
  $$('[data-ck-layout]').forEach(b => b.addEventListener('click', () => { kit().setAttribute('layout', b.dataset.ckLayout); press('[data-ck-layout]', b); reopen(); }));
  $$('[data-ck-theme]').forEach(b => b.addEventListener('click', () => { hosts().forEach(h => h.setAttribute('theme', b.dataset.ckTheme)); stage.dataset.theme = b.dataset.ckTheme; press('[data-ck-theme]', b); }));
  $$('[data-ck-view]').forEach(b => b.addEventListener('click', () => { view = b.dataset.ckView; press('[data-ck-view]', b); reopen(); }));
  d.querySelector('[data-ck-mobile]')?.addEventListener('click', e => {
    const on = e.currentTarget.getAttribute('aria-pressed') !== 'true';
    e.currentTarget.setAttribute('aria-pressed', String(on));
    stage.classList.toggle('is-mobile', on);
  });
  // Vorschau starten, sobald die Oberfläche geladen ist (consent.js öffnet den Hinweis ohne Entscheidung selbst)
  customElements.whenDefined('consent-kit').then(() => { update(); setTimeout(reopen, 50); });
  update();
}
