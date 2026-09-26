/*
 * Kern-Blöcke „Bildergalerie“ (Lightbox) und „Slider“ – kleines ES-Modul, nur auf Seiten mit diesen Blöcken.
 * Ohne JavaScript: Galerie-Bilder sind Links auf die große Fassung, der Slider ist ein wischbarer Scroll-Snap-Streifen.
 *  Lightbox: <dialog> (modal), Fokus bleibt im Dialog, Esc schließt, ←/→ und Wischen blättern, Bildunterschrift + Zähler.
 *  Slider:  WAI-ARIA-„Carousel“; Autoplay nur mit Pause-Schaltfläche, stoppt bei Maus/Fokus, nie bei „Bewegung reduzieren“.
 */
const d = document;
const rm = matchMedia('(prefers-reduced-motion: reduce)');
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const swipe = (el, fn) => {
  let x = null;
  el.addEventListener('pointerdown', e => { x = e.isPrimary ? e.clientX : null; });
  el.addEventListener('pointerup', e => { const dx = x === null ? 0 : e.clientX - x; x = null; if (Math.abs(dx) > 50) fn(dx < 0 ? 1 : -1); });
};

// ------------------------------------------------------------------ Lightbox
const dlg = d.querySelector('dialog.cms-lb'), img = dlg?.querySelector('img'), [txt, cnt] = dlg ? dlg.querySelectorAll('figcaption span') : [];
let items = [], idx = 0, opener;
function show(i) {
  idx = (i + items.length) % items.length;
  const a = items[idx];
  img.removeAttribute('srcset');
  img.src = a.href;
  if (a.dataset.srcset) img.srcset = a.dataset.srcset;
  img.alt = a.querySelector('img')?.alt || '';
  txt.textContent = a.dataset.caption || '';
  cnt.textContent = ' ' + dlg.dataset.count.replace('{n}', idx + 1).replace('{total}', items.length);
}
if (dlg) {
  dlg.addEventListener('click', e => {
    const b = e.target.closest('button');
    if (b) b.dataset.d ? show(idx + +b.dataset.d) : dlg.close();
    else if (e.target === dlg || e.target.classList.contains('cms-lb__fig')) dlg.close();
  });
  dlg.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); if (items.length > 1) show(idx + (e.key === 'ArrowLeft' ? -1 : 1)); }
    else if (e.key === 'Tab') { // Fokus im Dialog halten
      const f = $$('button:not([hidden])', dlg), first = f[0], last = f[f.length - 1];
      if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  });
  swipe(dlg, dir => items.length > 1 && show(idx + dir));
  dlg.addEventListener('close', () => opener?.focus());
  $$('[data-cms-lightbox] a').forEach(a => a.setAttribute('aria-haspopup', 'dialog'));
  d.addEventListener('click', e => {
    const a = e.target.closest?.('[data-cms-lightbox] a');
    if (!a || e.button || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    e.preventDefault();
    items = $$('a', a.closest('[data-cms-lightbox]'));
    opener = a;
    $$('[data-d]', dlg).forEach(b => { b.hidden = items.length < 2; });
    show(items.indexOf(a));
    dlg.showModal();
  });
}

// ------------------------------------------------------------------ Slider
function slider(el) {
  const c = JSON.parse(el.dataset.cmsSlider), track = el.querySelector('.cms-slider__track'), slides = [...track.children], n = slides.length;
  const dots = $$('.cms-slider__dot', el), arrows = $$('.cms-slider__arrow', el), play = el.querySelector('.cms-slider__play');
  const fade = el.classList.contains('cms-slider--fade'), nav = el.querySelector('.cms-slider__nav');
  const auto = c.autoplay && !rm.matches && n > 1;
  let i = 0, timer, paused = false, hold = false, sync;
  const set = (k, scroll = true) => {
    i = c.loop ? (k + n) % n : Math.max(0, Math.min(n - 1, k));
    slides.forEach((s, j) => { s.classList.toggle('is-current', j === i); s.inert = j !== i; });
    dots.forEach((b, j) => j === i ? b.setAttribute('aria-current', 'true') : b.removeAttribute('aria-current'));
    if (!c.loop && arrows.length === 2) { arrows[0].setAttribute('aria-disabled', i === 0); arrows[1].setAttribute('aria-disabled', i === n - 1); }
    if (scroll && !fade) track.scrollTo({ left: i * track.clientWidth, behavior: rm.matches ? 'auto' : 'smooth' });
  };
  const tick = () => {
    clearTimeout(timer);
    const run = auto && !paused && !hold && !d.hidden;
    track.setAttribute('aria-live', run ? 'off' : 'polite');
    if (run) timer = setTimeout(() => { set((i + 1) % n); tick(); }, c.autoplay);
  };
  el.classList.add('is-js');
  if (nav) nav.hidden = false;
  arrows.forEach(b => b.addEventListener('click', () => set(i + +b.dataset.dir)));
  dots.forEach((b, j) => b.addEventListener('click', () => set(j)));
  track.addEventListener('keydown', e => {
    const k = { ArrowLeft: i - 1, ArrowRight: i + 1, Home: 0, End: n - 1 }[e.key];
    if (k !== undefined && e.target === track) { e.preventDefault(); set(k); }
  });
  if (fade) swipe(track, dir => set(i + dir));
  else track.addEventListener('scroll', () => {
    clearTimeout(sync);
    sync = setTimeout(() => { const k = Math.round(track.scrollLeft / track.clientWidth); if (k !== i) set(k, false); }, 120);
  }, { passive: true });
  if (auto) {
    play.hidden = false;
    const label = play.querySelector('[data-label]');
    play.addEventListener('click', () => {
      paused = !paused;
      el.classList.toggle('is-paused', paused);
      label.textContent = paused ? c.play : c.pause;
      tick();
    });
    el.addEventListener('mouseenter', () => { hold = true; tick(); });
    el.addEventListener('mouseleave', () => { hold = el.contains(d.activeElement) && d.activeElement !== play; tick(); });
    el.addEventListener('focusin', e => { hold = e.target !== play; tick(); });
    el.addEventListener('focusout', e => { if (!el.contains(e.relatedTarget)) { hold = el.matches(':hover'); tick(); } });
    d.addEventListener('visibilitychange', tick);
  }
  set(0, false);
  tick();
}
$$('[data-cms-slider]').forEach(slider);
