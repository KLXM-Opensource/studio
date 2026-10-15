/*
 * „Online“ (Core\Presence::ONLINE): Herzschlag der offenen Seite – alle 2 Minuten, nur wenn der Tab sichtbar ist, und sofort beim
 * Zurückkehren. Der Aufruf der Seite selbst meldet schon (Layout der Verwaltung, Website mit Werkzeugleiste); das hier hält den
 * Status, solange die Seite offen bleibt. Ziel und CSRF aus [data-online-endpoint] / [data-online-csrf].
 */
const EVERY = 120000;

export function initOnline() {
  const host = document.querySelector('[data-online-endpoint]');
  if (!host) return;
  const url = host.dataset.onlineEndpoint;
  const csrf = () => host.dataset.onlineCsrf || document.getElementById('adm-csrf')?.value || '';
  let last = Date.now();
  const beat = () => {
    if (document.visibilityState !== 'visible') return;
    last = Date.now();
    const label = (document.querySelector('[data-online-label]')?.dataset.onlineLabel || document.title || '').slice(0, 120);
    fetch(url, { method: 'POST', credentials: 'same-origin', keepalive: true,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() },
      body: JSON.stringify({ label, url: location.pathname + location.search }) }).catch(() => {});
  };
  setInterval(beat, EVERY);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && Date.now() - last > EVERY / 2) beat(); });
}
