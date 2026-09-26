/* Kopfbereich-Aktionen – Öffnungsstatus am Kontakt-Chip und am Chip-Button (Core\HeaderActions; nur mit Öffnungszeiten) */
(() => {
  const d = document, $$ = s => [...d.querySelectorAll(s)];
  // Öffnungsstatus (Kontakt-Chip, Chip mit Statuspunkt)
  $$('[data-ha-hours]').forEach(el => {
    try {
      const ranges = JSON.parse(el.dataset.haHours), p = {};
      new Intl.DateTimeFormat('en-GB', { timeZone: el.dataset.haTz, weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
        .formatToParts(new Date()).forEach(x => { p[x.type] = x.value; });
      const dow = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].indexOf(p.weekday), now = p.hour + ':' + p.minute;
      const open = ranges.some(r => r[0] === dow && r[1] <= now && now < r[2]);
      el.classList.toggle('is-open', open);
      el.classList.add('has-state');
      el.querySelectorAll('[data-ha-state]').forEach(s => { s.textContent = (s.classList.contains('sf-sr') ? ' – ' : '') + (open ? el.dataset.haOpen : el.dataset.haClosed); s.hidden = false; });
    } catch { /* ohne Status bleibt der Chip ein einfacher Link */ }
  });

})();
