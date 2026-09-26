/*
 * Kopfbereich-Aktionen – Aufklapplisten (<details data-ha-menu>): Kontakt-Menü und Sprachen (Core\HeaderActions).
 * Öffnen mit Enter/Leertaste (nativ), Escape und Klick daneben schließen, Fokus zurück auf die Schaltfläche; aria-expanded
 * an <summary> für Hilfsmittel, die den Zustand von <details> nicht melden. Ohne JavaScript bleibt <details> bedienbar.
 */
(() => {
  const d = document, menus = [...d.querySelectorAll('[data-ha-menu]')];
  if (!menus.length) return;
  const sync = m => m.querySelector('summary').setAttribute('aria-expanded', String(m.open));
  menus.forEach(m => { sync(m); m.addEventListener('toggle', () => { sync(m); if (m.open) menus.forEach(o => { if (o !== m) o.open = false; }); }); });
  d.addEventListener('click', e => menus.forEach(m => { if (m.open && !m.contains(e.target)) m.open = false; }));
  d.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    menus.forEach(m => { if (m.open) { m.open = false; m.querySelector('summary').focus(); } });
  });
})();
