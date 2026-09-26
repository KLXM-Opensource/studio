/*
 * Einstiegs-Varianten der Kits (Core\Blocks\Hero): Hintergrundvideo, Vorher/Nachher-Regler, Laufzeile.
 * Wird nur auf Seiten geladen, deren Einstieg eine dieser Varianten nutzt (<script type="module"> aus Hero::script()).
 * Barrierefreiheit: Bewegung nur ohne „Bewegung reduzieren“ (auch wenn die Einstellung zur Laufzeit wechselt) und
 * immer mit sichtbarer Pause-Schaltfläche (WCAG 2.2.2); ohne JavaScript steht alles still (Standbild, ruhige Zeile,
 * beide Bilder nebeneinander).
 */
const d = document, calm = matchMedia('(prefers-reduced-motion: reduce)');

// Schaltfläche mit wechselnder Beschriftung: <button data-…-toggle><span data-l-pause="…" data-l-play="…">
const toggle = (btn, playing) => {
  if (!btn) return;
  const l = btn.querySelector('[data-l-pause]');
  if (l) l.textContent = playing ? l.dataset.lPause : l.dataset.lPlay;
  btn.classList.toggle('is-paused', !playing);
};

// ------------------------------------------------------------ Hintergrundvideo: stumm, Schleife, nur sichtbar abspielen
d.querySelectorAll('[data-hero-video]').forEach(v => {
  const box = v.closest('[data-hero-video-box]') || v.parentElement;
  const btn = box.querySelector('[data-hero-video-toggle]');
  let held = false, seen = false;
  const play = () => {
    if (held || calm.matches || !seen) return;
    v.preload = 'auto';
    v.play().then(() => { box.classList.add('is-playing'); if (btn) btn.hidden = false; toggle(btn, true); }).catch(() => {});
  };
  const stop = () => { v.pause(); toggle(btn, false); };
  btn?.addEventListener('click', () => { held = !v.paused; held ? stop() : play(); });
  new IntersectionObserver(([e]) => { seen = e.isIntersecting; seen ? play() : v.paused || (v.pause()); }).observe(box);
  calm.addEventListener('change', () => calm.matches ? (stop(), box.classList.remove('is-playing'), btn && (btn.hidden = true)) : play());
});

// ------------------------------------------------------------ Vorher/Nachher: Schieberegler (input range, Tastatur: Pfeile, Pos1/Ende)
d.querySelectorAll('[data-hero-compare]').forEach(box => {
  const r = box.querySelector('input[type=range]');
  if (!r) return;
  const set = () => { box.style.setProperty('--hx-pos', r.value + '%'); r.setAttribute('aria-valuetext', r.value + ' %'); };
  r.hidden = false;
  box.classList.add('is-ready');
  r.addEventListener('input', set);
  set();
});

// ------------------------------------------------------------ Laufzeile: läuft nur mit Skript, hält per Schaltfläche, Maus und Fokus (CSS)
d.querySelectorAll('[data-hero-marquee]').forEach(box => {
  const btn = box.querySelector('[data-hero-marquee-toggle]');
  const live = () => {
    box.classList.toggle('is-live', !calm.matches);
    if (btn) btn.hidden = calm.matches;
  };
  btn?.addEventListener('click', () => toggle(btn, box.classList.toggle('is-paused') === false));
  calm.addEventListener('change', live);
  live();
});
