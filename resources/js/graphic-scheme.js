/*
 * Infografiken mit Design-Farben (Core\Graphics\Themed): <picture class="cms-scheme-img"> trägt eine helle und eine dunkle
 * Fassung. Ohne Skript entscheidet die Einstellung des Geräts (<source media="(prefers-color-scheme: dark)">). Dieses Skript
 * folgt dem, was die Website an der Stelle tatsächlich zeigt – Hell/Dunkel-Schalter (scheme-dark/scheme-light, data-theme),
 * dunkle Abschnitte auf heller Seite: wie bei Karten und Diagrammen gilt helle Schrift als dunkler Grund. Nur auf Seiten mit
 * solchen Bildern geladen. MIT
 */
const mq = matchMedia('(prefers-color-scheme: dark)');
const lum = el => {
  const m = getComputedStyle(el).color.match(/[\d.]+/g) || [];
  if (m.length < 3) return null;
  const [r, g, b] = m.slice(0, 3).map(n => +n / 255).map(c => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const sync = () => {
  for (const pic of document.querySelectorAll('picture.cms-scheme-img')) {
    const src = pic.querySelector('source[data-scheme=dark]');
    if (!src) continue;
    const l = lum(pic);
    const want = l === null ? '(prefers-color-scheme: dark)' : l > 0.4 ? 'all' : 'not all';
    if (src.media !== want) src.media = want;
  }
};
let queued = false;
const later = () => { if (queued) return; queued = true; requestAnimationFrame(() => { queued = false; sync(); }); };
sync();
mq.addEventListener('change', later);
const opt = { attributes: true, attributeFilter: ['class', 'data-theme', 'data-scheme'] };
new MutationObserver(later).observe(document.documentElement, opt);
if (document.body) new MutationObserver(later).observe(document.body, opt);
