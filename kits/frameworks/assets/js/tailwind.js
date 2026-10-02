/* Kit „frameworks“ – tailwind.js: nur im Tailwind-Modus (Tailwind selbst bringt kein JavaScript mit).
   - Reiter [data-fw-tabs]: WAI-ARIA-Tabs (Pfeiltasten, Pos1/Ende, roving tabindex). Ohne Skript stehen alle Inhalte da.
   - Dialoge [data-fw-dialog]: Browser mit Invoker-Befehlen (commandfor/command) öffnen <dialog> ohne Skript;
     sonst showModal() hier. Fokus, Esc und Top Layer übernimmt das native <dialog>. */
const d = document;

d.querySelectorAll('[data-fw-tabs]').forEach(box => {
  const tabs = [...box.querySelectorAll('[role=tab]')];
  const panels = tabs.map(t => d.getElementById(t.getAttribute('aria-controls')));
  box.querySelectorAll('[data-fw-later]').forEach(p => { p.hidden = true; });
  const select = (i, focus) => {
    tabs.forEach((t, n) => {
      const on = n === i;
      t.setAttribute('aria-selected', on ? 'true' : 'false');
      t.tabIndex = on ? 0 : -1;
      if (panels[n]) panels[n].hidden = !on;
    });
    if (focus) tabs[i].focus();
  };
  tabs.forEach((t, i) => {
    t.addEventListener('click', () => select(i, false));
    t.addEventListener('keydown', e => {
      const k = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
      if (k === undefined) return;
      e.preventDefault();
      select((k + tabs.length) % tabs.length, true);
    });
  });
});

if (!('command' in HTMLButtonElement.prototype)) {
  d.addEventListener('click', e => {
    const b = e.target.closest('[data-fw-dialog]');
    const dlg = b && d.getElementById(b.dataset.fwDialog);
    if (dlg?.showModal && !dlg.open) dlg.showModal();
  });
}
