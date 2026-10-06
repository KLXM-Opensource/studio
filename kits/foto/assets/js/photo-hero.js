/* Bühne (Bildfolge) des Kits „foto“ – nur auf Seiten mit dem Block. Ohne JavaScript steht das erste Bild.
   Überblenden per Klasse is-active (CSS-Übergang nur mit „Animationen“ und ohne „Bewegung reduzieren“). Automatisch nur ohne
   „Bewegung reduzieren“; Pause-Schaltfläche (WCAG 2.2.2), hält bei Maus/Fokus und in verborgenen Tabs an.
   Videos (stumm, Schleife): spielen nur im sichtbaren Bild, nie bei „Bewegung reduzieren“, Pause hält auch sie an. */
{
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
document.querySelectorAll('[data-stage]').forEach(st => {
  const slides = [...st.querySelectorAll('[data-slide]')];
  const ctrl = st.querySelector('[data-stage-ctrl]');
  // Ein einzelnes Video (Variante „Ein Bild“): nur abspielen/anhalten
  if (slides.length < 2) {
    const v = st.querySelector('[data-stage-video]');
    const toggle = ctrl?.querySelector('[data-stage-toggle]');
    if (!v || !toggle) return;
    const label = ctrl.querySelector('[data-stage-label]');
    const set = p => { toggle.setAttribute('aria-pressed', String(p)); label.textContent = p ? toggle.dataset.labelPlay : toggle.dataset.labelPause; if (p) v.pause(); else v.play().catch(() => set(true)); };
    ctrl.hidden = false;
    toggle.addEventListener('click', () => set(toggle.getAttribute('aria-pressed') !== 'true'));
    set(reduce);
    return;
  }
  if (!ctrl) return;
  const toggle = ctrl.querySelector('[data-stage-toggle]');
  const label = ctrl.querySelector('[data-stage-label]');
  const cnt = ctrl.querySelector('[data-stage-count]');
  const delay = Number(st.dataset.autoplay || 0) * 1000;
  const hasVideo = !!st.querySelector('[data-stage-video]');
  let i = 0, timer = 0, paused = reduce || (!delay && !hasVideo), hold = false;
  // Videos laufen nur im sichtbaren Bild, nur ohne „Bewegung reduzieren“ und nicht angehalten
  const syncVideo = () => slides.forEach((sl, k) => sl.querySelectorAll('[data-stage-video]').forEach(v => {
    if (k === i && !paused && !reduce) v.play().catch(() => {}); else v.pause();
  }));
  const go = k => {
    slides[i].classList.remove('is-active');
    slides[i].setAttribute('aria-hidden', 'true');
    i = (k + slides.length) % slides.length;
    slides[i].classList.add('is-active');
    slides[i].removeAttribute('aria-hidden');
    cnt.textContent = (cnt.dataset.count || '{n} / {total}').replace('{n}', i + 1).replace('{total}', slides.length);
    slides[(i + 1) % slides.length].querySelector('img')?.setAttribute('loading', 'eager');   // nächstes Bild vorladen
    syncVideo();
  };
  const tick = () => {
    clearTimeout(timer);
    if (delay && !paused && !hold && !document.hidden) timer = setTimeout(() => { go(i + 1); tick(); }, delay);
  };
  const setPaused = p => {
    paused = p;
    toggle.setAttribute('aria-pressed', String(p));
    label.textContent = p ? toggle.dataset.labelPlay : toggle.dataset.labelPause;
    syncVideo();
    tick();
  };
  ctrl.hidden = false;
  if (!delay && !hasVideo) toggle.hidden = true;
  ctrl.querySelector('[data-stage-prev]').addEventListener('click', () => { go(i - 1); tick(); });
  ctrl.querySelector('[data-stage-next]').addEventListener('click', () => { go(i + 1); tick(); });
  toggle.addEventListener('click', () => setPaused(!paused));
  const holdOn = on => { hold = on; tick(); };
  st.addEventListener('pointerenter', e => e.pointerType === 'mouse' && holdOn(true));
  st.addEventListener('pointerleave', () => holdOn(false));
  st.addEventListener('focusin', () => holdOn(true));
  st.addEventListener('focusout', e => { if (!st.contains(e.relatedTarget)) holdOn(false); });
  st.addEventListener('keydown', e => {
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { if (!e.target.closest('[data-stage-ctrl]')) return; e.preventDefault(); go(i + (e.key === 'ArrowRight' ? 1 : -1)); }
  });
  document.addEventListener('visibilitychange', tick);
  go(0);
  setPaused(paused);
});
}
