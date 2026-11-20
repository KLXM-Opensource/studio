/*
 * fluid – Design „Link + Pfeil, öffnet auch beim Überfahren“ (np-hover): nur mit Maus, kurze Verzögerung beim Verlassen.
 * Lädt site.js nur bei html.np-hover und feinem Zeiger (Maus).
 */
const d = document;
const inline = s => getComputedStyle(s.querySelector('.hnav__panel')).position === 'absolute';
d.querySelectorAll('.hnav__item--split').forEach(li => {
  const s = li.querySelector('.hnav__sub');
  let t = 0, o = 0;
  li.addEventListener('mouseenter', () => { clearTimeout(t); if (inline(s) && !s.open) s.open = o = 1; });
  // Der erste Klick auf den Pfeil nach dem Öffnen per Maus schließt nicht gleich wieder
  s.firstElementChild.addEventListener('click', e => { if (s.open && o) e.preventDefault(); o = 0; });
  li.addEventListener('mouseleave', () => { t = setTimeout(() => { if (inline(s) && !s.contains(d.activeElement)) s.open = false; }, 250); });
});
