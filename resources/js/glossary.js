/**
 * Glossar (Core\Glossary) auf der Website – nur auf Seiten mit markierten Begriffen bzw. dem Block „Glossar“.
 *  - Hinweisfenster: <button class="gl-term" popovertarget> öffnet <span class="gl-pop" popover> (Popover-API: Esc, Klick daneben,
 *    Fokus zurück übernimmt der Browser). Hier: aria-expanded, Position am Begriff (schmale Bildschirme: Blatt unten, nur CSS),
 *    Rückfall ohne Popover-API (Klasse is-open, Esc, Klick daneben, Fokus zurück).
 *  - Block „Glossar“: Suchfeld filtert die Liste (Umlaute/Akzente egal), Buchstaben ohne Treffer werden blass.
 *  - Dynamische Bereiche (data-live am Skript): glossary-live.mjs markiert Inhalte, die erst im Browser entstehen.
 */
(() => {
  const d = document, S = d.currentScript;
  const native = Object.prototype.hasOwnProperty.call(HTMLElement.prototype, 'popover');
  let open = null;
  const ctl = (id) => d.querySelector('.gl-term[aria-controls="' + id + '"]');

  const place = (p, first) => {
    const b = ctl(p.id), st = p.style;
    if (!b) return;
    if (matchMedia('(max-width:36em)').matches) {
      // Blatt unten: Begriff sichtbar halten (darüber), sonst ein Stück scrollen
      st.left = st.top = st.right = st.bottom = st.margin = '';
      const r = b.getBoundingClientRect(), free = innerHeight - p.offsetHeight - 12;
      if (first && p.offsetHeight && r.bottom > free) scrollBy(0, r.bottom - free);
      return;
    }
    const r = b.getClientRects()[0] || b.getBoundingClientRect(), m = 12;
    const w = p.offsetWidth, h = p.offsetHeight, vw = d.documentElement.clientWidth;
    let y = r.bottom + 6;
    if (y + h > innerHeight - m && r.top - h - 6 >= m) y = r.top - h - 6;
    st.right = st.bottom = 'auto';
    st.margin = '0';
    st.left = Math.round(Math.max(m, Math.min(r.left, vw - w - m))) + 'px';
    st.top = Math.round(Math.max(m, y)) + 'px';
  };
  const state = (p, on) => {
    ctl(p.id)?.setAttribute('aria-expanded', on ? 'true' : 'false');
    if (on) { open = p; place(p, true); } else if (open === p) open = null;
  };
  const close = (p, focus) => {
    p.classList.remove('is-open');
    state(p, false);
    if (focus) ctl(p.id)?.focus();
  };

  if (native) {
    // Vor dem Zeigen an den Begriff setzen (kein Aufblitzen), danach mit der echten Größe korrigieren
    d.addEventListener('beforetoggle', (e) => {
      if (e.newState === 'open' && e.target.classList?.contains('gl-pop')) place(e.target);
    }, true);
    d.addEventListener('toggle', (e) => {
      if (e.target.classList?.contains('gl-pop')) state(e.target, e.newState === 'open');
    }, true);
  } else {
    d.addEventListener('click', (e) => {
      const b = e.target.closest?.('.gl-term,.gl-pop__x');
      const p = b && d.getElementById(b.getAttribute('popovertarget'));
      if (p) {
        e.preventDefault();
        const on = b.classList.contains('gl-term') && !p.classList.contains('is-open');
        if (open && open !== p) close(open);
        if (on) { p.classList.add('is-open'); state(p, true); } else close(p, true);
      } else if (open && !e.target.closest?.('.gl-pop')) close(open);
    });
    d.addEventListener('keydown', (e) => { if (e.key === 'Escape' && open) close(open, true); });
  }
  const again = () => open && place(open);
  addEventListener('resize', again);
  addEventListener('scroll', again, { passive: true });

  // ---------------------------------------------------------------- Block „Glossar“: Filter
  const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  d.querySelectorAll('[data-glx-list]').forEach((box) => {
    const s = box.querySelector('[data-glx-search]');
    if (!s) return;
    s.hidden = false;
    const q = s.querySelector('input'), n = s.querySelector('[data-glx-count]');
    const items = [...box.querySelectorAll('[data-glx]')].map((i) => [i, norm(i.dataset.glx)]);
    const groups = box.querySelectorAll('[data-glx-group]'), letters = box.querySelectorAll('[data-glx-letter]');
    let t;
    const run = () => {
      const v = norm(q.value.trim());
      let c = 0;
      for (const [i, h] of items) { const on = !v || h.includes(v); i.hidden = !on; c += on; }
      groups.forEach((g) => { g.hidden = !g.querySelector('[data-glx]:not([hidden])'); });
      letters.forEach((a) => {
        const off = !!d.getElementById(a.dataset.glxLetter)?.hidden;
        a.classList.toggle('is-off', off);
        off ? a.setAttribute('aria-disabled', 'true') : a.removeAttribute('aria-disabled');
      });
      n.textContent = !v ? '' : !c ? n.dataset.none : c === 1 ? n.dataset.one : n.dataset.many.replace('{n}', c);
    };
    q.addEventListener('input', () => { clearTimeout(t); t = setTimeout(run, 120); });
  });

  // ---------------------------------------------------------------- Dynamische Bereiche
  if (S?.dataset.live && S.dataset.mod) import(S.dataset.mod).then((m) => m.default(S.dataset)).catch(() => {});
})();
