/* Downloads (Variante „Liste“) – nur auf Seiten mit dem Block. Suche über Name, Hinweis und Dateiangaben (alle Wörter müssen
   passen, ohne Akzente), Filter nach Schlagworten (mehrere = eines davon), Umschalter Liste/Kacheln (Wahl bleibt im Browser).
   Ohne JavaScript: Liste ohne Werkzeugleiste. */
const KEY = 'fluid.downloads.view';
const store = { get() { try { return localStorage.getItem(KEY); } catch { return null; } }, set(v) { try { localStorage.setItem(KEY, v); } catch { /* privat */ } } };
const norm = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

document.querySelectorAll('[data-downloads]').forEach(box => {
  const bar = box.querySelector('.dlbar');
  const list = box.querySelector('.downloads');
  if (!bar || !list) return;
  const rows = [...list.children].map(li => ({ li, q: norm(li.dataset.q || li.textContent), tags: (li.dataset.tags || '').split(',').filter(Boolean) }));
  const input = bar.querySelector('[data-dl-search]');
  const count = bar.querySelector('[data-dl-count]');
  const views = [...bar.querySelectorAll('[data-dl-view]')];
  const tagBtns = [...bar.querySelectorAll('[data-dl-tag]')];
  const picked = new Set();
  let empty = null;

  const setView = (v, save) => {
    const apply = () => {
      list.classList.toggle('downloads--tiles', v === 'tiles');
      box.dataset.view = v;
      views.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.dlView === v)));
    };
    // Weicher Wechsel per View Transition, wo verfügbar und gewünscht
    if (save && document.startViewTransition && !matchMedia('(prefers-reduced-motion: reduce)').matches) document.startViewTransition(apply);
    else apply();
    if (save) store.set(v);
  };

  const filter = () => {
    const words = input ? norm(input.value.trim()).split(/\s+/).filter(Boolean) : [];
    let n = 0;
    rows.forEach(r => {
      const hit = words.every(w => r.q.includes(w)) && (!picked.size || r.tags.some(t => picked.has(t)));
      r.li.hidden = !hit;
      if (hit) n++;
    });
    const active = words.length || picked.size;
    count.textContent = !active ? '' : n ? count.dataset.of.replace('{n}', n).replace('{total}', rows.length) : count.dataset.none;
    if (!empty) { empty = document.createElement('p'); empty.className = 'dlbox__empty'; list.after(empty); }
    empty.hidden = n > 0;
    empty.textContent = n ? '' : count.dataset.none;
  };

  tagBtns.forEach(b => b.addEventListener('click', () => {
    const t = b.dataset.dlTag;
    if (!t) picked.clear();
    else if (picked.has(t)) picked.delete(t);
    else picked.add(t);
    tagBtns.forEach(x => x.setAttribute('aria-pressed', String(x.dataset.dlTag ? picked.has(x.dataset.dlTag) : !picked.size)));
    filter();
  }));
  views.forEach(b => b.addEventListener('click', () => setView(b.dataset.dlView, true)));
  if (input) {
    input.addEventListener('input', filter);
    input.addEventListener('keydown', e => { if (e.key === 'Escape' && input.value) { input.value = ''; filter(); } });
  }
  const saved = store.get();
  if (views.length && (saved === 'list' || saved === 'tiles')) setView(saved, false);
  bar.hidden = false;
});
