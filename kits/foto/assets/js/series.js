/* Serien-Übersicht „Große Liste“ des Kits „foto“: Vorschaubild beim Zeigen – folgt dem Mauszeiger (Position per el.style,
   CSP-konform); bei „Bewegung reduzieren“ und beim Tastaturfokus fest am rechten Rand. Nur mit Maus (hover: hover);
   sonst bleibt das kleine Bild in der Zeile (CSS). */
{
if (matchMedia('(hover: hover)').matches) {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('[data-sx-list]').forEach(list => {
    const peek = list.parentElement.querySelector('[data-sx-peek]');
    if (!peek) return;
    list.setAttribute('data-sx-ready', '');
    peek.hidden = false;
    let x = 0, y = 0, raf = 0, cur = null, fixed = reduce;
    const place = () => {
      raf = 0;
      if (fixed) { peek.style.transform = ''; return; }
      const w = peek.offsetWidth, h = peek.offsetHeight;
      let l = x + 28;
      if (l + w > innerWidth - 12) l = x - w - 28;
      const t = Math.max(12, Math.min(innerHeight - h - 12, y - h / 2));
      peek.style.transform = `translate(${Math.round(l)}px,${Math.round(t)}px)`;
    };
    const show = (item, viaFocus) => {
      const th = item?.querySelector('.sx-list__thumb');
      if (!th) { peek.classList.remove('is-on'); return; }
      fixed = reduce || viaFocus;
      if (cur !== item) {
        peek.className = 'sx-peek ' + [...th.classList].filter(c => c.startsWith('r-')).join(' ');
        peek.innerHTML = th.innerHTML;
        peek.querySelectorAll('source,img').forEach(el => el.setAttribute('sizes', '24rem'));
        peek.querySelector('img')?.setAttribute('loading', 'eager');
        cur = item;
      }
      peek.classList.toggle('sx-peek--fixed', fixed);
      place();
      peek.classList.add('is-on');
    };
    list.addEventListener('pointermove', e => { x = e.clientX; y = e.clientY; if (!raf) raf = requestAnimationFrame(place); });
    list.addEventListener('pointerover', e => { const it = e.target.closest('.sx-list__item'); if (it) show(it, false); });
    list.addEventListener('pointerleave', () => peek.classList.remove('is-on'));
    list.addEventListener('focusin', e => show(e.target.closest('.sx-list__item'), true));
    list.addEventListener('focusout', e => { if (!list.contains(e.relatedTarget)) peek.classList.remove('is-on'); });
  });
}
}
