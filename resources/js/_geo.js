/*
 * Feldtyp „geo“ (Ort): Koordinaten eingeben, Adresse suchen (Nominatim über den Server)
 * oder in die Karte klicken / Markierung ziehen. MapLibre lädt erst, wenn das Feld sichtbar wird.
 */
import { t } from './_i18n.js';

let lib = null;
function loadLib(cfg, node) {
  // Stylesheet in den Baum des Feldes (Shadow DOM der Seitenleisten auf der Website), sonst in <head>
  const root = node?.getRootNode?.();
  if (!(root instanceof ShadowRoot ? root : document).querySelector('link[data-maplibre-css]')) {
    const l = document.createElement('link');
    l.rel = 'stylesheet'; l.href = cfg.css; l.dataset.maplibreCss = '';
    if (root instanceof ShadowRoot) root.prepend(l); else document.head.append(l);
  }
  if (!lib) lib = import(cfg.vendor + 'maplibre-gl.mjs');
  return lib;
}
const parse = s => {
  const m = /^\s*(-?\d{1,2}(?:\.\d+)?)\s*[,;]\s*(-?\d{1,3}(?:\.\d+)?)\s*$/.exec(s || '');
  return m && Math.abs(+m[1]) <= 85 && Math.abs(+m[2]) <= 180 ? [+m[1], +m[2]] : null;
};
const fmt = n => String(+n.toFixed(6));
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

export function initGeo(scope = document) {
  scope.querySelectorAll('[data-geo]:not([data-geo-ready])').forEach(box => {
    box.dataset.geoReady = '1';
    const cfg = JSON.parse(box.dataset.geo);
    const input = box.querySelector('.geo-row input[type=text]');
    const q = box.querySelector('[data-geo-q]');
    const list = box.querySelector('[data-geo-results]');
    const status = box.querySelector('[data-geo-status]');
    const mapEl = box.querySelector('[data-geo-map]');
    const form = box.closest('form');
    let ml = null, map = null, marker = null;

    const address = () => (cfg.address || []).map(n => form?.querySelector(`[name="${n}"],[name$="[${n}]"]`)?.value?.trim())
      .filter(v => v && !v.startsWith('[')).join(', ');
    const emit = () => {
      input.dispatchEvent(new Event('input', { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    };
    const place = (lat, lng, move) => {
      if (!map) return;
      if (!marker) {
        marker = new ml.Marker({ draggable: true, color: '#314164' }).setLngLat([lng, lat]).addTo(map);
        marker.on('dragend', () => { const p = marker.getLngLat(); set(p.lat, p.lng, false); });
      } else marker.setLngLat([lng, lat]);
      if (move) map.jumpTo({ center: [lng, lat], zoom: Math.max(map.getZoom(), 15) });
    };
    const set = (lat, lng, move = true) => {
      input.value = `${fmt(lat)}, ${fmt(lng)}`;
      emit();
      place(lat, lng, move);
      status.textContent = t('Ort gesetzt: {p}', { p: input.value });
    };

    async function start() {
      if (map) return;
      ml = await loadLib(cfg, input);
      const p = parse(input.value);
      map = new ml.Map({
        container: mapEl, style: cfg.style,
        center: p ? [p[1], p[0]] : [10.45, 51.16], zoom: p ? 15 : 5,
        attributionControl: { compact: true },
      });
      map.addControl(new ml.NavigationControl({ showCompass: false }), 'top-right');
      map.on('click', e => set(e.lngLat.lat, e.lngLat.lng, false));
      if (p) map.once('load', () => place(p[0], p[1], false));
    }
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver(en => { if (en.some(x => x.isIntersecting)) { io.disconnect(); start(); } });
      io.observe(mapEl);
    } else start();

    input.addEventListener('change', () => {
      const p = parse(input.value);
      if (p) place(p[0], p[1], true);
    });
    box.querySelector('[data-geo-clear]').addEventListener('click', () => {
      input.value = ''; emit();
      marker?.remove(); marker = null;
      status.textContent = t('Ort entfernt.');
    });

    async function search() {
      const term = q.value.trim() || address();
      if (!term) { q.focus(); return; }
      q.value = term;
      status.textContent = t('Suche …');
      list.hidden = true;
      try {
        const res = await fetch(cfg.geocode + '?q=' + encodeURIComponent(term), { headers: { Accept: 'application/json' } });
        const { results = [] } = await res.json();
        if (!results.length) { status.textContent = t('Keine Adresse gefunden. Tipp: Straße, Hausnummer und Ort angeben.'); return; }
        if (results.length === 1) { set(results[0].lat, results[0].lng); return; }
        list.innerHTML = results.map((r, i) => `<li><button type="button" data-i="${i}">${esc(r.label)}</button></li>`).join('');
        list.hidden = false;
        status.textContent = t('{n} Treffer – bitte auswählen.', { n: results.length });
        list.onclick = e => {
          const b = e.target.closest('button[data-i]');
          if (!b) return;
          const r = results[+b.dataset.i];
          list.hidden = true;
          set(r.lat, r.lng);
        };
      } catch { status.textContent = t('Adresssuche nicht erreichbar.'); }
    }
    box.querySelector('[data-geo-find]').addEventListener('click', search);
    q.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); search(); } });
    q.addEventListener('focus', () => { if (!q.value) q.value = address(); });
  });
}
