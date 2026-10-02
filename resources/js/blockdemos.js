/*
 * Block-Designer: Hinweise zu Blöcken aus den Beispielen (Core\Blocks\Demos, app/Admin/views/blocks/edit.php).
 * „Hinweise ausblenden“ gilt je Block und Browser (localStorage); „einblenden“ holt sie zurück.
 */
const KEY = 'cb.demohints.off';
const load = () => { try { const a = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(a) ? a : []; } catch { return []; } };
const save = a => { try { localStorage.setItem(KEY, JSON.stringify(a.slice(-100))); } catch { /* privat/gesperrt */ } };

const box = document.querySelector('[data-cb-demohints]');
if (box) {
  const id = box.dataset.key || '';
  const show = document.querySelector('[data-cb-demohints-show]');
  const apply = off => {
    document.querySelectorAll('[data-cb-demohint]').forEach(el => { el.hidden = off; });
    box.hidden = off;
    if (show) show.hidden = !off;
  };
  apply(id !== '' && load().includes(id));
  box.querySelector('[data-cb-demohints-off]')?.addEventListener('click', () => {
    if (id !== '') save([...load().filter(k => k !== id), id]);
    apply(true);
    show?.querySelector('button')?.focus();
  });
  show?.querySelector('[data-cb-demohints-on]')?.addEventListener('click', () => {
    save(load().filter(k => k !== id));
    apply(false);
    box.querySelector('h2')?.setAttribute('tabindex', '-1');
    box.querySelector('h2')?.focus();
  });
}
