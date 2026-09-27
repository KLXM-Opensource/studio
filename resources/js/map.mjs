/*
 * Karten (Kern): MapLibre GL + OpenFreeMap über den eigenen Proxy (/proxy/ofm/…).
 * MapLibre (~800 KB) wird erst geladen, wenn eine Karte in Sichtweite kommt.
 * Erst nach Klick auf „Karte anzeigen“: bei „Datensparen“ (Save-Data) und im Zwei-Klick-Modus (data-click, Core\Maps).
 * Kits mit eigenem Lader importieren dieses Modul erst beim Klick und setzen vorher data-go – diese Karten starten sofort.
 */
const maps = [...document.querySelectorAll('[data-cms-map]:not([data-ready])')];
let lib = null;

function loadLib(cfg) {
  if (lib) return lib;
  if (!document.querySelector('link[data-maplibre-css]')) {
    const l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = cfg.css;
    l.dataset.maplibreCss = '';
    document.head.append(l);
  }
  lib = import(cfg.vendor + 'maplibre-gl.mjs');
  return lib;
}

async function init(el) {
  if (el.dataset.ready) return;
  el.dataset.ready = '1';
  const cfg = JSON.parse(el.dataset.cmsMap);
  const canvas = el.querySelector('.cms-map__canvas');
  el.classList.add('is-loading');
  try {
    const ml = await loadLib(cfg);
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    // Dunkler Stil nur, wenn das Theme Dunkelmodus unterstützt und das Gerät ihn verlangt
    const themeDark = /dark/.test(document.querySelector('meta[name="color-scheme"]')?.content || '');
    const mq = matchMedia('(prefers-color-scheme: dark)');
    const styleFor = () => (themeDark && mq.matches && cfg.styleDark ? cfg.styleDark : cfg.style);
    const map = new ml.Map({
      container: canvas,
      style: styleFor(),
      center: cfg.center,
      zoom: cfg.zoom,
      maxBounds: [[cfg.bounds[0], cfg.bounds[1]], [cfg.bounds[2], cfg.bounds[3]]],
      maxZoom: 18,
      cooperativeGestures: true,
      attributionControl: { compact: true },
      locale: cfg.i18n,
      fadeDuration: reduce ? 0 : 300,
    });
    map.addControl(new ml.NavigationControl({ showCompass: false }), 'top-right');
    const pin = document.createElement('div');
    pin.className = 'cms-map__marker';
    pin.setAttribute('role', 'img');
    pin.setAttribute('aria-label', cfg.label || 'Standort');
    new ml.Marker({ element: pin, anchor: 'bottom' }).setLngLat(cfg.center).addTo(map);
    map.once('load', () => el.classList.replace('is-loading', 'is-ready'));
    if (themeDark) mq.addEventListener('change', () => map.setStyle(styleFor()));
    map.on('error', e => console.warn('Karte:', e?.error?.message || e));
  } catch (e) {
    el.classList.remove('is-loading');
    el.classList.add('is-failed');
    delete el.dataset.ready;
    console.warn('Karte konnte nicht geladen werden', e);
  }
}

const saveData = navigator.connection?.saveData === true;
const start = el => { el.classList.add('is-started'); init(el); };
const io = 'IntersectionObserver' in window && new IntersectionObserver(entries => {
  for (const en of entries) if (en.isIntersecting) { io.unobserve(en.target); init(en.target); }
}, { rootMargin: '300px 0px' });
for (const el of maps) {
  if (el.dataset.go) { start(el); continue; }
  if (saveData || el.dataset.click !== undefined || !io) {
    const b = el.querySelector('[data-cms-map-load]');
    if (b && !b.dataset.bound) { b.dataset.bound = '1'; b.hidden = false; b.addEventListener('click', () => start(el), { once: true }); }
  } else io.observe(el);
}
