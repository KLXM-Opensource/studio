/* Bildstrom des Kits „foto“ (blocks/moments.php; ≈ 2 KB, ohne Abhängigkeiten). Ohne JavaScript ist alles sichtbar.
   - Einblenden beim Scrollen: Kacheln, die gemeinsam ins Bild kommen, erscheinen nacheinander (Verzögerung per el.style)
   - Stumme Video-Schleifen laufen nur, solange sie sichtbar sind; Pause-Schaltfläche; nie bei „Bewegung reduzieren“
   - Nachladen: weitere Kacheln (.mo__later, schon im HTML) beim Scrollen bzw. per „Mehr zeigen“
   - Zähler „07 / 24“ mit Titel der Kachel (Scrollspy) */
{
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
const motion = document.documentElement.classList.contains('has-motion') && !reduce;
const IO = 'IntersectionObserver' in window;

document.querySelectorAll('[data-mo]').forEach(box => {
  if (!IO) return;
  const tiles = () => [...box.querySelectorAll('[data-mo-tile]')];
  box.classList.add('mo-armed');

  // ---------------------------------------------------------- Einblenden (versetzt)
  let queue = [], timer = 0;
  const flush = () => {
    queue.sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top || a.getBoundingClientRect().left - b.getBoundingClientRect().left);
    queue.forEach((el, k) => { el.style.transitionDelay = motion ? Math.min(k, 6) * 90 + 'ms' : ''; el.classList.add('is-in'); });
    queue = [];
    timer = 0;
  };
  const reveal = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    reveal.unobserve(e.target);
    queue.push(e.target);
    timer ||= requestAnimationFrame(flush);
  }), { rootMargin: '0px 0px -6% 0px' });
  // Nach dem Erscheinen keine Verzögerung mehr (Hover-Effekte sollen sofort reagieren)
  box.addEventListener('transitionend', e => { if (e.target.matches?.('[data-mo-tile]')) e.target.style.transitionDelay = ''; });

  // ---------------------------------------------------------- Stumme Video-Schleifen
  const loops = new IntersectionObserver(es => es.forEach(e => {
    const v = e.target, btn = v.closest('[data-mo-tile]')?.querySelector('[data-mo-pause]');
    if (btn?.getAttribute('aria-pressed') === 'true') return;
    if (e.isIntersecting) v.play().then(() => { if (btn) btn.hidden = false; }).catch(() => {});
    else v.pause();
  }), { threshold: .25 });
  box.addEventListener('click', e => {
    const btn = e.target.closest('[data-mo-pause]');
    if (!btn) return;
    const v = btn.closest('[data-mo-tile]').querySelector('video[data-mo-loop]');
    const stop = btn.getAttribute('aria-pressed') !== 'true';
    btn.setAttribute('aria-pressed', String(stop));
    if (stop) v.pause(); else v.play().catch(() => {});
  });

  // ---------------------------------------------------------- Zähler (Scrollspy)
  const spy = box.querySelector('.mo__spy');
  const spyN = spy?.querySelector('[data-mo-n]'), spyT = spy?.querySelector('[data-mo-t]');
  const seen = new IntersectionObserver(es => es.forEach(e => {
    if (!e.isIntersecting) return;
    const all = tiles(), i = all.indexOf(e.target);
    spyN.textContent = String(i + 1).padStart(2, '0');
    let t = '';
    for (let k = i; k >= 0 && !t; k--) t = all[k].dataset.moTitle || '';
    spyT.textContent = t;
  }), { rootMargin: '-45% 0px -45% 0px' });
  if (spy) spy.hidden = false;

  const watch = el => {
    reveal.observe(el);
    if (spy) seen.observe(el);
    if (!reduce) el.querySelectorAll('video[data-mo-loop]').forEach(v => loops.observe(v));
  };
  tiles().filter(t => !t.classList.contains('mo__later')).forEach(watch);

  // ---------------------------------------------------------- Nachladen
  const mode = box.dataset.moMore, size = +box.dataset.moBatch || 12;
  const btn = box.querySelector('[data-mo-load]'), status = box.querySelector('[data-mo-status]');
  if (mode === 'all' || !btn) return;
  const next = () => {
    const later = [...box.querySelectorAll('.mo__later')].slice(0, size);
    later.forEach(el => { el.classList.remove('mo__later'); watch(el); });
    if (status) status.textContent = (status.dataset.msg || '{n}').replace('{n}', later.length);
    const foot = btn.closest('.mo__foot');
    if (!box.querySelector('.mo__later')) { end?.disconnect(); foot.remove(); }
    else if (end) requestAnimationFrame(() => { end.unobserve(foot); end.observe(foot); });   // Ende noch im Bild → gleich weiter
    return later[0];
  };
  btn.hidden = false;
  btn.addEventListener('click', () => next()?.querySelector('a,button')?.focus({ preventScroll: true }));
  // Endlos: kurz bevor das Ende des Stroms erreicht ist, kommen die nächsten Kacheln (Schaltfläche bleibt für Tastatur und als Halt)
  let end = null;
  if (mode === 'scroll') {
    end = new IntersectionObserver(es => { if (es[0].isIntersecting) next(); }, { rootMargin: '0px 0px 80% 0px' });
    end.observe(btn.closest('.mo__foot'));
  }
});
}
