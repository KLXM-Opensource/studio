/*
 * Block-Skripte „editorial“ – nur auf Seiten mit Artikel oder Text (theme.php → conditional_css).
 *  - Teilen: „Link kopieren“ (Zwischenablage) – ohne JavaScript bleibt die Schaltfläche verborgen
 *  - Inhaltsverzeichnis: markiert den Abschnitt, der gerade gelesen wird (aria-current)
 */
const d = document;

// ------------------------------------------------------------ Teilen: Link kopieren
if (navigator.clipboard) d.querySelectorAll('[data-copy]').forEach(b => {
  b.hidden = false;
  const label = b.textContent;
  b.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(b.dataset.copy); b.textContent = b.dataset.done; setTimeout(() => { b.textContent = label; }, 2400); } catch { /* keine Berechtigung */ }
  });
});

// ------------------------------------------------------------ Inhaltsverzeichnis: aktueller Abschnitt
d.querySelectorAll('.toc').forEach(toc => {
  const links = [...toc.querySelectorAll('a[href^="#"]')];
  const map = new Map(links.map(a => [d.getElementById(decodeURIComponent(a.hash.slice(1))), a]).filter(([h]) => h));
  if (!map.size || !('IntersectionObserver' in window)) return;
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (!en.isIntersecting) return;
      links.forEach(a => a.removeAttribute('aria-current'));
      map.get(en.target)?.setAttribute('aria-current', 'true');
    });
  }, { rootMargin: '0px 0px -70% 0px' });
  map.forEach((_, h) => io.observe(h));
});
// Video (Zwei-Klick-Lösung): Kern – resources/js/embed.js über das Kern-Fragment video-embed
