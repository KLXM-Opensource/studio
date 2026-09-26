/* Scrollytelling – nur auf Seiten mit dem Block. Der Abschnitt in der Bildschirmmitte wird aktiv und blendet sein Bild in
   der stehenden Spalte ein (IntersectionObserver). Ohne JavaScript bleibt das erste Bild stehen; jedes Bild steht zusätzlich
   bei seinem Text, wenn der Platz für die stehende Spalte fehlt (CSS Container-Query). */
document.querySelectorAll('[data-scrolly]').forEach(box => {
  const steps = [...box.querySelectorAll('[data-step]')];
  const imgs = [...box.querySelectorAll('[data-step-img]')];
  const activate = i => {
    steps.forEach(s => s.classList.toggle('is-active', s.dataset.step === i));
    const has = imgs.some(im => im.dataset.stepImg === i);
    if (has) imgs.forEach(im => im.classList.toggle('is-active', im.dataset.stepImg === i));
  };
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => { if (en.isIntersecting) activate(en.target.dataset.step); });
  }, { rootMargin: '-45% 0px -45% 0px' });
  steps.forEach(s => io.observe(s));
});
