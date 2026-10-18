/**
 * Nach dem Abmelden über die Leiste der Website (?abgemeldet): Zusatz aus der Adresse entfernen (Neu laden zeigt die Meldung
 * nicht noch einmal) und die Meldung nach dem Ausblenden aus dem Dokument nehmen.
 */
(() => {
  try {
    const u = new URL(location.href);
    if (u.searchParams.has('abgemeldet')) {
      u.searchParams.delete('abgemeldet');
      history.replaceState(history.state, '', u.pathname + u.search + u.hash);
    }
  } catch (e) {}
  const el = document.querySelector('[data-signed-out]');
  if (el) el.addEventListener('animationend', () => el.remove());
})();
