/*
 * Zentrale Einstellungen: Live-Vorschau der Website mit ungespeicherten Werten
 * und einklappbare Listeneinträge (Repeater) mit Kurzinfo.
 */
import { t } from './_i18n.js';
import { livePreview } from './_preview.js';

const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => [...c.querySelectorAll(s)];

// ------------------------------------------------------------ Repeater: einklappen + Kurzinfo
function metaFor(item) {
  const fields = $('.rep-fields', item);
  const parts = [];
  const img = $('.media-field-preview img', fields);
  const sel = $('select', fields);
  if (sel && sel.value) parts.push(`<span class="rep-tag">${esc(sel.selectedOptions[0]?.text || '')}</span>`);
  const active = $$('input[type=checkbox]', fields).find(c => /\[(aktiv|active|sichtbar|visible)\]$/.test(c.name));
  if (active) parts.push(`<span class="rep-state ${active.checked ? 'is-on' : 'is-off'}">${esc(active.checked ? t('Aktiv') : t('Inaktiv'))}</span>`);
  const dates = $$('input[type=date]', fields).map(i => i.value).filter(Boolean)
    .map(v => new Date(v + 'T00:00').toLocaleDateString(document.documentElement.lang || 'de', { day: '2-digit', month: '2-digit', year: 'numeric' }));
  if (dates.length) parts.push(`<span class="rep-when">${esc(dates.join(' – '))}</span>`);
  return (img ? `<img src="${esc(img.getAttribute('src'))}" alt="">` : '') + parts.join('');
}
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

function setOpen(item, open) {
  item.classList.toggle('is-collapsed', !open);
  $(':scope > legend .rep-toggle', item)?.setAttribute('aria-expanded', String(open));
}

export function initRepeaterCollapse(scope = document) {
  $$('.rep', scope).forEach(rep => {
    if (rep._collapse) return; rep._collapse = true;
    const items = () => $$(':scope > .rep-items > .rep-item', rep);
    const refresh = item => { const m = $(':scope > [data-rep-meta]', item); if (m) m.innerHTML = metaFor(item); };
    // Mehr als zwei Einträge: eingeklappt starten (Einträge mit Fehlern bleiben offen)
    const many = items().length > 2;
    items().forEach(it => { refresh(it); setOpen(it, !many || it.hasAttribute('data-open')); });
    const allBtn = $(':scope > .rep-head [data-rep=all]', rep);
    const syncAll = () => { if (allBtn) allBtn.textContent = items().every(i => !i.classList.contains('is-collapsed')) ? t('Alle zuklappen') : t('Alle aufklappen'); };
    syncAll();
    rep.addEventListener('click', e => {
      const b = e.target.closest('[data-rep]');
      if (!b || b.closest('.rep') !== rep) return;
      if (b.dataset.rep === 'toggle') { const it = b.closest('.rep-item'); setOpen(it, it.classList.contains('is-collapsed')); syncAll(); }
      if (b.dataset.rep === 'all') { const open = items().some(i => i.classList.contains('is-collapsed')); items().forEach(i => setOpen(i, open)); syncAll(); }
      if (b.dataset.rep === 'add') setTimeout(() => { const it = items().at(-1); if (it) { refresh(it); setOpen(it, true); } syncAll(); });
    });
    rep.addEventListener('input', e => { const it = e.target.closest('.rep-item'); if (it?.closest('.rep') === rep) refresh(it); });
    rep.addEventListener('change', e => { const it = e.target.closest('.rep-item'); if (it?.closest('.rep') === rep) refresh(it); });
  });
}

// ------------------------------------------------------------ Live-Vorschau (gemeinsames Panel: _preview.js)
export function initSettingsPreview() {
  const wrap = $('[data-st-preview]');
  if (!wrap || wrap.hasAttribute('data-ds')) return;
  const form = $('form', wrap), pane = $('.st-pv', wrap), singleNote = $('[data-st-single]', wrap);
  let single = null;

  // Sprachwechsel behält den geöffneten Reiter
  $$('a[data-keep-hash]').forEach(a => a.addEventListener('click', () => { a.href = a.href.split('#')[0] + location.hash; }));

  const pv = livePreview({
    wrap, endpoint: wrap.dataset.stPreview, key: 'st',
    body: () => {
      const fd = new FormData(form);
      if (single) { fd.set('_repeater', single.name); fd.set('_index', String(single.index)); }
      return fd;
    },
  });
  form.addEventListener('input', () => pv.later());
  form.addEventListener('change', () => pv.later());
  $('[data-st-all]', pane)?.addEventListener('click', () => { single = null; singleNote.hidden = true; pv.refresh(); });

  // „Vorschau dieses Eintrags“ (z. B. einzelnes Hero-Thema)
  form.addEventListener('click', e => {
    const b = e.target.closest('[data-rep=preview]');
    if (!b) return;
    const item = b.closest('.rep-item'), rep = item.closest('.rep');
    const m = /\[([^\]]+)\]$/.exec(rep.dataset.name || '');
    single = { name: m ? m[1] : '', index: [...item.parentElement.children].indexOf(item) };
    singleNote.hidden = false;
    if (pv.isOpen()) pv.refresh(); else pv.open(true);
  });
}
