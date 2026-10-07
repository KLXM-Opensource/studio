/* Begrüßungsfenster (app/Admin/views/_welcome-modal.php): ohne JavaScript ein offenes <dialog> im Inhalt – hier wird daraus
   ein modales Fenster (Fokus im Fenster, Escape, Klick auf den Hintergrund schließt). ?erste-schritte verschwindet danach aus der Adresse. */
(() => {
  const d = document.getElementById('wlc');
  if (!d || typeof d.showModal !== 'function') return;
  d.close();
  d.showModal();
  d.addEventListener('click', e => { if (e.target === d) d.close(); });
  d.addEventListener('close', () => {
    const u = new URL(location.href);
    if (u.searchParams.has('erste-schritte')) { u.searchParams.delete('erste-schritte'); history.replaceState(null, '', u); }
  });
  // „Erste Schritte“ auf derselben Seite erneut öffnen, ohne Neuladen
  document.addEventListener('click', e => {
    const a = e.target.closest?.('a[data-welcome-open]');
    if (!a) return;
    e.preventDefault();
    if (!d.open) d.showModal();
  });
})();
