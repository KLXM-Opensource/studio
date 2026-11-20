/*
 * fluid – Scrollspy im Inhaltsverzeichnis (Fließtext „Artikel“, Feld scrollspy). Lädt site.js nur, wenn die Seite ein
 * [data-scrollspy] enthält.
 */
const d = document;
const reduce = matchMedia('(prefers-reduced-motion: reduce)');

// Aktueller Abschnitt → aria-current="location", Markierung gleitet dorthin (CSS-Variablen per JS – keine Inline-Styles im
// HTML), „progress“: Lesefortschritt als Linie. Ein Abschnitt gilt als aktuell, sobald seine Überschrift das obere Drittel des
// Fensters erreicht hat.
d.querySelectorAll('[data-scrollspy]').forEach(toc => {
  const list = toc.querySelector('.toc__list');
  const pairs = [...toc.querySelectorAll('.toc__item a[href^="#"]')].map(a => [a, d.getElementById(decodeURIComponent(a.hash.slice(1)))]).filter(p => p[1]);
  const links = pairs.map(p => p[0]), heads = pairs.map(p => p[1]);
  const body = toc.closest('.article__body')?.querySelector('.prose');
  if (!list || !heads.length) return;
  const marker = d.createElement('span');
  marker.className = 'toc__marker'; marker.setAttribute('aria-hidden', 'true');
  list.prepend(marker);
  let cur = -1, raf = 0;
  const update = () => {
    raf = 0;
    const line = innerHeight / 3;
    let i = -1;
    heads.forEach((h, k) => { if (h.getBoundingClientRect().top <= line) i = k; });
    if (body && toc.classList.contains('toc--spy-progress')) {
      const r = body.getBoundingClientRect();
      const p = Math.min(1, Math.max(0, (line - r.top) / Math.max(1, r.height - line)));
      list.style.setProperty('--toc-p', p.toFixed(3));
    }
    if (i === cur) return;
    cur = i;
    links.forEach((a, k) => { if (k === i) a.setAttribute('aria-current', 'location'); else a.removeAttribute('aria-current'); });
    const a = links[i];
    if (!a) { marker.classList.remove('is-on'); return; }
    const li = a.closest('.toc__item');
    marker.style.setProperty('--toc-y', li.offsetTop + 'px');
    marker.style.height = li.offsetHeight + 'px';
    marker.classList.add('is-on');
    // langes Inhaltsverzeichnis: aktuellen Eintrag sichtbar halten
    if (toc.scrollHeight > toc.clientHeight) {
      const top = li.offsetTop - toc.clientHeight / 3;
      toc.scrollTo({ top, behavior: reduce.matches ? 'auto' : 'smooth' });
    }
  };
  addEventListener('scroll', () => { raf ||= requestAnimationFrame(update); }, { passive: true });
  addEventListener('resize', () => { cur = -1; raf ||= requestAnimationFrame(update); }, { passive: true });
  update();
});
