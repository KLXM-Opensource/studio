/*
 * Block-Skripte „basis“ – nur auf Seiten mit Reitern (theme.php → conditional_css).
 *  - Reiter: role=tablist/tab/tabpanel, Pfeiltasten, Pos1/Ende (ohne JavaScript: Inhalte untereinander)
 */
const d = document;

// ------------------------------------------------------------ Reiter
d.querySelectorAll('[data-tabs]').forEach(box => {
  const list = box.querySelector('[role=tablist]');
  const tabs = [...list.querySelectorAll('[role=tab]')];
  const panels = tabs.map(t => d.getElementById(t.getAttribute('aria-controls')));
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
  const start = Math.max(0, panels.findIndex(p => location.hash && p.id === location.hash.slice(1)));
  select(start, false);
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
// Video (Zwei-Klick-Lösung): Kern – resources/js/embed.js über das Kern-Fragment video-embed
