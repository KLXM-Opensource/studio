/* Start-Kit – site.js (lädt auf jeder Seite, defer). Nur progressive enhancement: Ohne JavaScript funktioniert alles –
   das Menü öffnet mobil als HTML-popover, die Suche ebenso, FAQ über <details>. Kein Framework, keine Abhängigkeiten.
   Budget: < 8 KB (minifiziert). */
const d = document;
d.documentElement.classList.replace('no-js', 'js');

// Mobiles Menü (popover): nach Klick auf einen Sprunganker der Seite schließen, damit der Abschnitt sichtbar wird
const nav = d.querySelector('[data-nav]');
nav?.addEventListener('click', e => {
  if (e.target.closest('a[href*="#"]') && nav.matches?.(':popover-open')) nav.hidePopover();
});

// Suchvorschläge: Kern-Skript search.js erst beim ersten Fokus eines Suchfelds nachladen (data-suggest-js)
let suggest = false;
d.addEventListener('focusin', e => {
  const src = e.target.dataset?.suggestJs;
  if (!src || suggest) return;
  suggest = true;
  d.head.append(Object.assign(d.createElement('script'), { src }));
});
