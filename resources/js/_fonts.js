/*
 * Grundeinstellungen → Schriften (Core\Fonts): Schriftproben.
 * Jede Probe [data-font-sample] lädt ihre Schrift als FontFace von der EIGENEN Domain (data-font-src: Vorschau-Route
 * /admin/system/fonts/preview/{id} bzw. installierte Datei unter /assets/fonts/installed/…) – erst, wenn sie sichtbar wird.
 * Schrift per CSSOM (CSP: keine Inline-Styles). Der Vorschautext [data-font-text] gilt für alle Proben.
 */
const d = document;

export function initFonts() {
  const samples = [...d.querySelectorAll('[data-font-sample]')];
  if (!samples.length) return;
  const loaded = new Map();
  const load = el => {
    const src = el.dataset.fontSrc, name = el.dataset.fontName;
    if (!src || !name || !('FontFace' in window)) return;
    let p = loaded.get(name);
    if (!p) {
      p = new FontFace(name, `url("${src.replace(/"/g, '%22')}")`, { display: 'swap' }).load().then(f => { d.fonts.add(f); return true; }).catch(() => false);
      loaded.set(name, p);
    }
    el.setAttribute('aria-busy', 'true');
    p.then(ok => {
      el.removeAttribute('aria-busy');
      if (ok) el.style.fontFamily = `"${name}", ${el.dataset.fontFallback || 'system-ui, sans-serif'}`;
      else el.classList.add('is-failed');
    });
  };
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => entries.forEach(en => { if (en.isIntersecting) { io.unobserve(en.target); load(en.target); } }), { rootMargin: '200px' });
    samples.forEach(el => io.observe(el));
  } else samples.forEach(load);

  const input = d.querySelector('[data-font-text]');
  input?.addEventListener('input', () => {
    const txt = input.value.trim() || input.defaultValue;
    samples.forEach(el => { el.textContent = txt; });
  });
}
