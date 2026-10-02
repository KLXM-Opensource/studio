/*
 * Service Worker (KLXM Studio) – nur für die installierte App.
 * Seiten: erst Netz, dann Zwischenspeicher, sonst Offline-Seite. Statische Dateien: Zwischenspeicher zuerst.
 * Nie gespeichert: Verwaltung, API, MCP, Formulare, private Antworten (angemeldete Nutzer).
 */
const VERSION = '__VERSION__';
const CACHE = 'klxm-studio-' + VERSION;
const PRECACHE = __PRECACHE__;
const OFFLINE = __OFFLINE__;
const BASE = __BASE__;
const SKIP = /^\/(admin|api|mcp|anfrage|sw\.js|manifest\.webmanifest)(\/|$|\?)/;
const STATIC = /^\/(assets|kits|themes|media)\//;

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(caches.keys()
    .then(keys => Promise.all(keys.filter(k => /^(klxm-studio|mycms)-/.test(k) && k !== CACHE).map(k => caches.delete(k))))
    .then(() => self.clients.claim()));
});

const storable = res => res && res.ok && res.type === 'basic' && !/no-store|private/i.test(res.headers.get('Cache-Control') || '');

self.addEventListener('fetch', e => {
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;
  const path = url.pathname.slice(BASE.length) || '/';
  if (SKIP.test(path) || url.searchParams.has('edit') || url.searchParams.has('live')) return;

  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).then(res => {
      if (storable(res)) { const copy = res.clone(); caches.open(CACHE).then(c => c.put(url.pathname, copy)); }
      return res;
    }).catch(() => caches.match(url.pathname).then(hit => hit || caches.match(OFFLINE))));
    return;
  }

  if (STATIC.test(path)) {
    e.respondWith(caches.match(req).then(hit => hit || fetch(req).then(res => {
      if (storable(res)) { const copy = res.clone(); caches.open(CACHE).then(c => c.put(req, copy)); }
      return res;
    })));
  }
});
