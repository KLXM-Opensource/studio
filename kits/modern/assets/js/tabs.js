/* Reiter (Tabs) – nur auf Seiten mit dem Block. role=tablist/tab/tabpanel, Pfeiltasten, Pos1/Ende; ohne JavaScript stehen
   alle Inhalte untereinander. Ein Anker auf ein Panel (#…-panel-2) öffnet diesen Reiter. */
document.querySelectorAll('[data-tabs]').forEach(box => {
  const list = box.querySelector('[role=tablist]');
  const tabs = [...list.querySelectorAll('[role=tab]')];
  const panels = tabs.map(t => document.getElementById(t.getAttribute('aria-controls')));
  const select = (i, focus) => {
    tabs.forEach((t, k) => {
      const on = k === i;
      t.setAttribute('aria-selected', String(on));
      t.tabIndex = on ? 0 : -1;
      panels[k].hidden = !on;
    });
    if (focus) tabs[i].focus();
  };
  panels.forEach(p => { p.setAttribute('role', 'tabpanel'); p.tabIndex = 0; });
  list.hidden = false;
  box.classList.add('is-js');
  select(Math.max(0, panels.findIndex(p => location.hash && p.id === location.hash.slice(1))), false);
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => select(i, false));
    t.addEventListener('keydown', e => {
      const n = tabs.length;
      const k = { ArrowRight: i + 1, ArrowDown: i + 1, ArrowLeft: i - 1 + n, ArrowUp: i - 1 + n, Home: 0, End: n - 1 }[e.key];
      if (k === undefined) return;
      e.preventDefault();
      select(k % n, true);
    });
  });
});
