/* Wischbares Band (Karten, Stimmen) – nur auf Seiten mit diesen Blöcken. Ohne JavaScript: scroll-snap zum Wischen/Scrollen.
   Hier: Zurück/Weiter-Schaltflächen erscheinen nur, wenn es etwas zu blättern gibt; am Anfang/Ende aria-disabled. */
document.querySelectorAll('[data-reel-ctrl]').forEach(ctrl => {
  const reel = document.getElementById(ctrl.dataset.reelCtrl);
  if (!reel) return;
  const prev = ctrl.querySelector('[data-reel-prev]');
  const next = ctrl.querySelector('[data-reel-next]');
  const step = () => (reel.firstElementChild?.getBoundingClientRect().width || reel.clientWidth) + parseFloat(getComputedStyle(reel).columnGap || 0);
  const update = () => {
    const max = reel.scrollWidth - reel.clientWidth - 2;
    ctrl.hidden = max <= 0;
    prev.setAttribute('aria-disabled', String(reel.scrollLeft <= 1));
    next.setAttribute('aria-disabled', String(reel.scrollLeft >= max));
  };
  const go = dir => reel.scrollBy({ left: dir * step(), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  prev.addEventListener('click', () => go(-1));
  next.addEventListener('click', () => go(1));
  reel.addEventListener('scroll', () => requestAnimationFrame(update), { passive: true });
  new ResizeObserver(update).observe(reel);
});
