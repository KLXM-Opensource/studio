/* Nur beim Start der installierten App geladen (start_url …?pwa=1): Service Worker registrieren, Adresse bereinigen. */
if ('serviceWorker' in navigator) {
  const s = document.currentScript;
  navigator.serviceWorker.register(s.dataset.sw, { scope: s.dataset.scope }).catch(() => {});
}
if (location.search.includes('pwa=1')) history.replaceState(null, '', location.pathname + location.hash);
