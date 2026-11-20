/* Kit „fluid“ – „Filter nach Kategorie“ (Karten, fluid_filter()): nur auf Seiten mit Merkmal '@filter'.
   Das Filtern selbst erledigt CSS (css/filter.css, :has() mit den Nummern 1–12). Dieses Skript ergänzt nur die Trefferzahl
   als Live-Bereich (role=status, Texte aus data-label-status*) und markiert nicht passende Einträge (data-ncss-filter-out),
   falls ein Browser :has() nicht kennt. Markup wie <ncss-filter> von NativeCSS – ein Kind-Kit kann dessen Skript als
   js/filter.js mitbringen. */
document.querySelectorAll('ncss-filter').forEach(box => {
  const bar = box.querySelector('.ncss-filter-bar');
  if (!bar) return;
  const status = document.createElement('p');
  status.className = 'ncss-filter-status';
  status.setAttribute('role', 'status');
  bar.after(status);
  const text = (k, n) => (box.getAttribute('data-label-' + k) || '{n}').replace('{n}', n);
  const apply = announce => {
    const v = bar.querySelector('input:checked')?.value.trim() || '';
    let n = 0;
    box.querySelectorAll('.ncss-filter-item').forEach(it => {
      const hit = !v || (it.getAttribute('data-category') || '').split(/\s+/).includes(v);
      it.toggleAttribute('data-ncss-filter-out', !hit);
      if (hit) n++;
    });
    // Erste Ansage erst nach einer Wahl – beim Laden bleibt der Live-Bereich still
    if (announce) status.textContent = n === 0 ? text('status-none', 0) : n === 1 ? text('status-one', 1) : text('status', n);
  };
  bar.addEventListener('change', () => apply(true));
  apply(false);
});
