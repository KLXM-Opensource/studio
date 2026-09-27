/*
 * Netzwerk-Übersicht (/admin/network): Websites filtern und durchsuchen – ohne JavaScript sind alle Karten sichtbar.
 * Karten: [data-net-card] mit data-q (Suchtext), data-warn, data-maintenance, data-staging.
 * Ansicht Kacheln/Liste ([data-net-view]) wird im Browser gemerkt (localStorage, ohne Speicher einfach Kacheln).
 */
import { t } from './_i18n.js';

export function initNetwork() {
  const grid = document.querySelector('[data-net-grid]');
  if (!grid) return;
  const cards = [...grid.querySelectorAll('[data-net-card]')];
  const q = document.querySelector('[data-net-q]');
  const btns = [...document.querySelectorAll('[data-net-filter]')];
  const count = document.querySelector('[data-net-count]');
  const none = document.querySelector('[data-net-none]');
  let filter = 'all';
  const apply = () => {
    const words = (q?.value || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
    let n = 0;
    for (const c of cards) {
      const okFilter = filter === 'all' || c.dataset[filter] === '1';
      const okQ = words.every(w => c.dataset.q.includes(w));
      c.hidden = !(okFilter && okQ);
      if (!c.hidden) n++;
    }
    btns.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.netFilter === filter)));
    if (count) count.textContent = n === cards.length ? t('{n} Websites', { n }) : t('{n} von {total} Websites', { n, total: cards.length });
    if (none) none.hidden = n > 0;
  };
  q?.addEventListener('input', apply);
  btns.forEach(b => b.addEventListener('click', () => { filter = b.dataset.netFilter; apply(); }));
  document.querySelectorAll('[data-net-show]').forEach(a => a.addEventListener('click', () => { filter = a.dataset.netShow; apply(); }));
  // Aktionsmenüs: nur eines offen, Escape schließt
  const menus = [...document.querySelectorAll('.net-more')];
  menus.forEach(m => m.addEventListener('toggle', () => { if (m.open) menus.forEach(o => { if (o !== m) o.open = false; }); }));
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const open = menus.find(m => m.open);
    if (open) { open.open = false; open.querySelector('summary')?.focus(); }
  });
  // Ansicht: Kacheln oder Liste
  const views = document.querySelector('[data-net-views]');
  if (views) {
    const KEY = 'cms.net.view';
    const setView = (v, save) => {
      grid.classList.toggle('is-list', v === 'list');
      views.querySelectorAll('[data-net-view]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.netView === v)));
      if (save) try { localStorage.setItem(KEY, v); } catch { /* ohne Speicher */ }
    };
    let saved = 'grid';
    try { saved = localStorage.getItem(KEY) === 'list' ? 'list' : 'grid'; } catch { /* ohne Speicher */ }
    setView(saved, false);
    views.hidden = false;
    views.querySelectorAll('[data-net-view]').forEach(b => b.addEventListener('click', () => setView(b.dataset.netView, true)));
  }
  apply();
}
