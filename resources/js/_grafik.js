/*
 * Grafik-Editor laden (Core\Graphics): das Bündel grafik.js + grafik.css kommt erst beim ersten Öffnen.
 * Adressen vom Layout (data-grafik-js, data-grafik-css an <html>, nur mit Recht „media.upload“).
 *   openGraphicEditor(opts) → Promise<{ saved, svg }>   (CMSAdmin.graphic.open)
 *   Knöpfe mit data-ge-open (data-format, data-id, data-media, data-block) öffnen das Overlay.
 */
import { toast } from './_toast.js';
import { t } from './_i18n.js';

let loading = null;
function load() {
  if (window.CMSGraphicEditor?.open) return Promise.resolve(window.CMSGraphicEditor);
  const ds = document.documentElement.dataset;
  if (!ds.grafikJs) return Promise.reject(new Error(t('Für den Grafik-Editor fehlt Ihrer Rolle die Berechtigung.')));
  return (loading ||= new Promise((res, rej) => {
    if (ds.grafikCss && !document.querySelector('link[data-grafik-css]')) {
      const l = document.createElement('link');
      l.rel = 'stylesheet'; l.href = ds.grafikCss; l.dataset.grafikCss = '1';
      document.head.append(l);
    }
    const s = document.createElement('script');
    s.src = ds.grafikJs; s.defer = true;
    s.onload = () => res(window.CMSGraphicEditor);
    s.onerror = () => { loading = null; rej(new Error(t('Der Grafik-Editor konnte nicht geladen werden.'))); };
    document.head.append(s);
  }));
}

export async function openGraphicEditor(opts = {}) {
  try {
    const api = await load();
    return await api.open(opts);
  } catch (e) {
    toast(e.message, 'error');
    return { saved: null, svg: null };
  }
}

export function initGrafik() {
  document.addEventListener('click', e => {
    const b = e.target.closest?.('[data-ge-open]');
    if (!b || e.metaKey || e.ctrlKey) return;
    e.preventDefault();
    const d = b.dataset;
    // Übersicht „Grafiken“: nach dem Speichern neu laden, damit die Liste stimmt
    openGraphicEditor({ format: d.format, id: d.id, mediaId: d.media, block: d.block })
      .then(r => { if (r.saved && b.closest('.ge-store, .adm-head')) location.reload(); });
  });
}
