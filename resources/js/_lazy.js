/*
 * Teile von admin.js, die erst bei Bedarf laden: resources/js/admin-{name}.mjs → public/assets/js/admin-{name}.mjs.
 *   lazy('admin-ai').then(m => m.initAi(scope))
 * Adresse und ?v= kommen vom Skript-Tag von admin.js (document.currentScript – auch unter einem Unterordner und auf der
 * Website mit Werkzeugleiste). Gemeinsame Kern-Module (Ebene, Rückfragen, Symbole …) bringen die Teile nicht selbst mit:
 * tools/build.mjs leitet ihre Importe auf window.CMSAdminModules um (resources/js/_shared.js) – ein Zustand, eine Ebene.
 */
const SELF = document.currentScript?.src || '';
const cache = new Map();

export function lazy(name) {
  if (!cache.has(name)) {
    const u = new URL(name + '.mjs', SELF || new URL('/assets/js/', location.href));
    if (SELF) u.search = new URL(SELF).search;
    cache.set(name, import(u.href).catch(err => { console.error('[' + name + ']', err); return {}; }));
  }
  return cache.get(name);
}
