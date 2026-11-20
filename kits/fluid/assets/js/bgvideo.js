/*
 * fluid – Hintergrundvideo (Einstieg „vollflächig“): startet nur ohne „Bewegung reduzieren“, mit Pause-Schaltfläche.
 * Lädt site.js nur, wenn die Seite ein [data-bg-video] enthält.
 */
const reduce = matchMedia('(prefers-reduced-motion: reduce)');
document.querySelectorAll('[data-bg-video]').forEach(v => {
  const box = v.closest('.hero-bleed');
  const t = box?.querySelector('[data-bg-video-toggle]');
  const label = t?.querySelector('span');
  const set = playing => {
    if (!t) return;
    t.setAttribute('aria-pressed', String(!playing));
    label.textContent = playing ? label.dataset.labelPause : label.dataset.labelPlay;
  };
  if (reduce.matches) return;
  v.play().then(() => { if (t) t.hidden = false; set(true); }).catch(() => {});
  t?.addEventListener('click', () => { if (v.paused) { v.play(); set(true); } else { v.pause(); set(false); } });
});
