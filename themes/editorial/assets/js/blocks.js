/*
 * Block-Skripte „editorial“ – nur auf Seiten mit Video, Artikel oder Text (theme.php → conditional_css).
 *  - Video: Zwei-Klick-Lösung – Player erst nach Klick; „künftig direkt laden“ je Anbieter im Browser (localStorage, widerrufbar, kein Cookie)
 *  - Teilen: „Link kopieren“ (Zwischenablage) – ohne JavaScript bleibt die Schaltfläche verborgen
 *  - Inhaltsverzeichnis: markiert den Abschnitt, der gerade gelesen wird (aria-current)
 */
const d = document;

// ------------------------------------------------------------ Video (Zwei-Klick-Lösung)
const KEY = p => 'editorial-embed-' + p;
// Einwilligungs-Verwaltung (Erweiterung consent_kit): window.cmsConsent.embed(p) → true/false, null = nicht verwaltet (dann localStorage)
const cms = p => window.cmsConsent?.embed(p) ?? null;
const allowed = p => cms(p) ?? (() => { try { return localStorage.getItem(KEY(p)) === '1'; } catch { return false; } })();
const remember = (p, on) => { if (cms(p) !== null) { window.cmsConsent.allowEmbed(p, on); return; } try { on ? localStorage.setItem(KEY(p), '1') : localStorage.removeItem(KEY(p)); } catch { /* privat */ } };

function load(el, autoplay) {
  const p = el.dataset.embed;
  const f = d.createElement('iframe');
  f.src = autoplay ? el.dataset.src : el.dataset.src.replace('autoplay=1', 'autoplay=0');
  f.title = el.dataset.title;
  f.className = 'vembed__frame';
  f.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
  f.allowFullscreen = true;
  f.referrerPolicy = 'strict-origin-when-cross-origin';
  el.replaceChildren(f);
  el.classList.add('is-loaded');
  if (allowed(p)) {
    const note = d.createElement('p');
    note.className = 'vembed__revoke';
    const b = d.createElement('button');
    b.type = 'button';
    b.textContent = el.dataset.tRevoke;
    b.addEventListener('click', () => { remember(p, false); location.reload(); });
    note.append(el.dataset.tDirect + ' ', b);
    el.after(note);
  }
  if (autoplay) f.focus();
}
d.querySelectorAll('[data-embed]').forEach(el => {
  if (allowed(el.dataset.embed)) { load(el, false); return; }
  el.querySelector('[data-embed-play]')?.addEventListener('click', () => {
    if (el.querySelector('[data-embed-remember]')?.checked) remember(el.dataset.embed, true);
    load(el, true);
  });
});
// Einwilligung über den Cookie-Hinweis (consent_kit) erteilt → wartende Videos laden (ohne Autoplay)
document.addEventListener('cms:consent', () => document.querySelectorAll('[data-embed]:not(.is-loaded)').forEach(el => { if (allowed(el.dataset.embed)) load(el, false); }));

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
