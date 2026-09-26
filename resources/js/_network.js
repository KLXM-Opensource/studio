/*
 * Netzwerk-Übersicht (/admin/network): Websites filtern und durchsuchen – ohne JavaScript sind alle Karten sichtbar.
 * Karten: [data-net-card] mit data-q (Suchtext), data-warn, data-maintenance, data-staging.
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
  apply();
}
