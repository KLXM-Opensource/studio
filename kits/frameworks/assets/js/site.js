/* Kit „frameworks“ – site.js (beide Frameworks, defer). Nur Komfort: no-js → js, Menü-Popover schließt nach Klick auf einen
   Sprunganker. Kein Inline-Skript, keine Abhängigkeiten. */
const d = document;
d.documentElement.classList.replace('no-js', 'js');
const nav = d.querySelector('[data-fw-popnav]');
nav?.addEventListener('click', e => {
  if (e.target.closest('a[href*="#"]') && nav.matches?.(':popover-open')) nav.hidePopover();
});
