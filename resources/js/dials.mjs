/*
 * Kern-Block „Kennzahlen mit Skala“: einmal beim ersten Erscheinen Bogen füllen und Zahl hochzählen.
 * Den leeren Anfangszustand setzt das CSS nur mit @media (scripting:enabled) + keine reduzierte Bewegung; dieses Skript
 * setzt beim Erscheinen .is-in (Übergang auf den Endstand). Ohne JavaScript, beim Drucken und bei „Bewegung reduzieren“
 * steht sofort der Endstand da. [data-n] umschließt nur eine ganze Zahl („12“ in „12 Jahre“; Dezimalwerte zählen
 * nicht hoch, nur der Bogen); am Ende steht wieder exakt der Originaltext. Die Zahl ist aria-hidden – Screenreader lesen den festen Satz in .cms-dials__sr.
 */
const raf = requestAnimationFrame, io = new IntersectionObserver(es => es.forEach(({ isIntersecting, target: l }) => {
  if (isIntersecting) io.unobserve(l), l.classList.add('is-in'), matchMedia('(prefers-reduced-motion)').matches || l.querySelectorAll('[data-n]').forEach(run);
}), { threshold: .35 });
document.querySelectorAll('[data-dials]').forEach(l => io.observe(l));

function run(el) {
  let o = el.textContent, t0;
  const f = t => {
    const p = (t - (t0 ??= t)) / 1200;
    el.textContent = p < 1 ? Math.round(o * (1 - (1 - p) ** 3)) : o;
    p < 1 && raf(f);
  };
  raf(f);
}
