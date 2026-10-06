/* Werke: Filter nach Künstler und Verfügbarkeit (nur auf Seiten mit dem Block „Werke“ und eingeschalteten Filtern).
   Ohne JavaScript bleibt die Filterleiste verborgen und alle Werke sichtbar. Schaltflächen mit aria-pressed, Anzahl per aria-live. */
document.querySelectorAll('[data-aw-filter]').forEach(bar => {
  const list = document.getElementById(bar.dataset.awFilter + '-works');
  if (!list) return;
  const items = [...list.children];
  const count = bar.querySelector('[data-aw-count]');
  const state = { artist: '', avail: '' };
  const apply = () => {
    let n = 0;
    items.forEach(li => {
      const on = (!state.artist || li.dataset.artist === state.artist) && (!state.avail || li.dataset.avail === state.avail);
      li.hidden = !on;
      if (on) { n++; li.classList.add('is-in'); }
    });
    if (count) count.textContent = (n === 1 ? count.dataset.one : count.dataset.many).replace('{n}', n);
  };
  bar.addEventListener('click', e => {
    const btn = e.target.closest('button[data-f]');
    if (!btn) return;
    state[btn.dataset.f] = btn.dataset.v;
    bar.querySelectorAll(`button[data-f="${btn.dataset.f}"]`).forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
    apply();
  });
  bar.hidden = false;
});
