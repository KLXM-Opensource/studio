/*
 * Karten (Kern): MapLibre GL + OpenFreeMap über den eigenen Proxy (/proxy/ofm/…).
 * MapLibre (~800 KB) wird erst geladen, wenn eine Karte in Sichtweite kommt.
 * Bei „Datensparen“ (Save-Data) erst nach Klick auf „Karte anzeigen“.
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
if (saveData || !('IntersectionObserver' in window)) {
  for (const el of maps) {
    const b = el.querySelector('[data-cms-map-load]');
    if (b) { b.hidden = false; b.addEventListener('click', () => { b.hidden = true; init(el); }, { once: true }); }
  }
} else {
  const io = new IntersectionObserver(entries => {
    for (const en of entries) if (en.isIntersecting) { io.unobserve(en.target); init(en.target); }
  }, { rootMargin: '300px 0px' });
  maps.forEach(el => io.observe(el));
}
