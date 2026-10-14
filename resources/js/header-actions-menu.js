/*
 * Kopfbereich-Aktionen – Aufklapplisten (<details data-ha-menu>): Kontakt-Menü und Sprachen (Core\HeaderActions).
 * Öffnen mit Enter/Leertaste (nativ), Escape und Klick daneben schließen, Fokus zurück auf die Schaltfläche; aria-expanded
 * an <summary> für Hilfsmittel, die den Zustand von <details> nicht melden. Ohne JavaScript bleibt <details> bedienbar.
 */
(() => {
  const d = document, menus = [...d.querySelectorAll('[data-ha-menu]')];
  if (!menus.length) return;
  const sync = m => m.querySelector('summary').setAttribute('aria-expanded', String(m.open));
  // Platz prüfen: unten zu wenig → nach oben aufklappen (z. B. Sprachen unten in einer Seitenleiste), links zu wenig → nach rechts
  const place = m => {
    const list = m.querySelector('ul,[role=menu]');
    if (!list) return;
    m.classList.remove('is-up', 'is-start');
    // Grenzen: Fenster und nächster Vorfahr, der abschneidet (z. B. eine scrollende Seitenleiste)
    let lo = innerHeight, top = 0, left = 0;
    for (let e = m.parentElement; e && e !== d.body; e = e.parentElement) {
      const o = getComputedStyle(e);
      if (/(auto|scroll|hidden|clip)/.test(o.overflowY + o.overflowX)) { const b = e.getBoundingClientRect(); lo = Math.min(lo, b.bottom); top = Math.max(top, b.top); left = Math.max(left, b.left); break; }
    }
    const r = list.getBoundingClientRect(), mb = m.getBoundingClientRect();
    if (r.bottom > lo - 8 && mb.top - top > r.height + 16) m.classList.add('is-up');
    if (r.left < left + 8 || mb.left + mb.width / 2 < innerWidth / 2) m.classList.add('is-start');   // linke Bildhälfte: am Knopf ausrichten
  };
  menus.forEach(m => { sync(m); m.addEventListener('toggle', () => { sync(m); if (m.open) { place(m); menus.forEach(o => { if (o !== m) o.open = false; }); } }); });
  d.addEventListener('click', e => menus.forEach(m => { if (m.open && !m.contains(e.target)) m.open = false; }));
  d.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    menus.forEach(m => { if (m.open) { m.open = false; m.querySelector('summary').focus(); } });
  });
})();
