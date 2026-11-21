/*
 * Bewegte Grafiken auf der Website (Core\Graphics\Motion) – nur auf Seiten mit Grafiken, die es brauchen (data-gm-js).
 * Die Bewegung selbst ist CSS (erzeugte Datei je Grafik); dieses Skript setzt nur Zustände:
 *   .is-armed  Anfangszustand (ohne Skript bleibt der Endzustand stehen)
 *   .is-play   abspielen – sobald zu einem Viertel sichtbar (data-gm="view") bzw. bei Klick/Enter/Leertaste (click)
 *   .is-off    außerhalb des Bildes: Schleifen halten an
 *   .is-hN / .was-hN  Hotspot N an bzw. war an (rückwärts)
 * Zählen: Text mit data-gm-count zählt zum geschriebenen Wert hoch. „Bewegung reduzieren“ und Design-Schalter
 * „Dezente Animationen“ aus (.gm-still): nichts – es bleibt der Endzustand. MIT
 */
const still = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
const EASE = { ease: [0.25, 0.1, 0.25, 1], 'ease-in': [0.42, 0, 1, 1], 'ease-out': [0, 0, 0.58, 1], 'ease-in-out': [0.42, 0, 0.58, 1], linear: [0, 0, 1, 1] };
/** CSS-Zeitfunktion → f(x) */
function easeFn(s) {
  if (/^steps/.test(s)) return x => (x >= 1 ? 1 : 0);
  const p = EASE[s] || (s.match(/cubic-bezier\(([^)]*)\)/)?.[1].split(',').map(Number)) || EASE['ease-in-out'];
  return x => {
    const cx = 3 * p[0], bx = 3 * (p[2] - p[0]) - cx, ax = 1 - cx - bx, cy = 3 * p[1], by = 3 * (p[3] - p[1]) - cy, ay = 1 - cy - by;
    let t = x;
    for (let i = 0; i < 8; i++) { const f = ((ax * t + bx) * t + cx) * t - x, d = (3 * ax * t + 2 * bx) * t + cx; if (Math.abs(f) < 1e-5 || !d) break; t -= f / d; }
    t = Math.max(0, Math.min(1, t));
    return ((ay * t + by) * t + cy) * t;
  };
}

/** Zähler eines Textes: Zahl im Text finden, Präfix/Suffix behalten */
function counter(el) {
  const [a, b, d, g, p, t0, t1, e] = el.dataset.gmCount.split(' ');
  const nodes = [];
  const walk = n => { for (const c of n.childNodes) { if (c.nodeType === 3) nodes.push(c); else if (c.nodeType === 1 && c.localName !== 'title') walk(c); } };
  walk(el);
  const node = nodes.find(n => /\d/.test(n.data));
  if (!node) return null;
  const orig = node.data, m = orig.match(/[-+]?\d[\d.,'   ]*\d|\d/);
  if (!m) return null;
  const sep = { '-': '', s: ' ', n: ' ' }[g] ?? g, dec = +d;
  const fmt = v => {
    const [i, f] = Math.abs(v).toFixed(dec).split('.');
    return (v < 0 ? '−' : '') + i.replace(/\B(?=(\d{3})+(?!\d))/g, sep) + (f ? p + f : '');
  };
  const ease = easeFn(e);
  return {
    set(u) {   // u = Zeit in der Szene (s)
      const k = u <= +t0 ? 0 : u >= +t1 ? 1 : ease((u - +t0) / Math.max(1e-6, +t1 - +t0));
      node.data = k >= 1 ? orig : orig.slice(0, m.index) + fmt(+a + (+b - +a) * k) + orig.slice(m.index + m[0].length);
    },
    end() { node.data = orig; },
  };
}

function init(svg) {
  if (svg.dataset.gmInit) return;
  svg.dataset.gmInit = '1';
  if (still() || svg.classList.contains('gm-still')) return;
  const [D, DL, IT, DIR] = (svg.dataset.gmT || '4 0 1 normal').split(' ');
  const dur = +D || 4, delay = +DL || 0, it = +IT, dir = DIR || 'normal';
  const tr = svg.dataset.gm;
  const counts = [...svg.querySelectorAll('[data-gm-count]')].map(counter).filter(Boolean);
  let clock = null;   // {start, paused, at}
  svg.classList.add('is-armed');
  // Zählen: eigener Takt, gleiche Zeitrechnung wie CSS (Verzögerung, Wiederholungen, Richtung)
  const local = s => {
    s -= delay;
    if (s <= 0) return dir === 'reverse' || dir === 'alternate-reverse' ? dur : 0;
    const total = it === 0 ? Infinity : it * dur;
    if (s >= total) s = total - 1e-6;
    const n = Math.floor(s / dur);
    let u = s - n * dur;
    const back = dir === 'reverse' || (dir === 'alternate' && n % 2) || (dir === 'alternate-reverse' && !(n % 2));
    return back ? dur - u : u;
  };
  const tick = () => {
    if (!clock) return;
    const now = performance.now();
    const s = clock.paused ? clock.at : (now - clock.start) / 1000;
    counts.forEach(c => c.set(local(s)));
    if (it !== 0 && s >= delay + it * dur) { counts.forEach(c => c.end()); clock = null; return; }
    requestAnimationFrame(tick);
  };
  const play = () => {
    svg.classList.remove('is-play');
    void svg.getBoundingClientRect();
    svg.classList.add('is-play');
    if (counts.length) { const was = !!clock; clock = { start: performance.now(), paused: false, at: 0 }; counts.forEach(c => c.set(local(0))); if (!was) requestAnimationFrame(tick); }
  };
  if (counts.length) counts.forEach(c => c.set(local(0)));
  if (tr === 'load') play();
  if (tr === 'hover') {
    const go = () => { if (counts.length) { clock = { start: performance.now(), paused: false, at: 0 }; requestAnimationFrame(tick); } };
    svg.addEventListener('pointerenter', go);
    svg.addEventListener('focusin', go);
  }
  if (tr === 'click') {
    svg.addEventListener('click', e => { if (!e.target.closest('a,[data-gm-hot]')) play(); });
    svg.addEventListener('keydown', e => { if ((e.key === 'Enter' || e.key === ' ') && e.target === svg) { e.preventDefault(); play(); } });
  }
  // Hotspots: an/aus (aria-pressed), gebundene Elemente spielen vorwärts bzw. rückwärts
  for (const h of svg.querySelectorAll('[data-gm-hot]')) {
    const n = h.dataset.gmHot;
    const toggle = () => {
      const on = !svg.classList.contains('is-h' + n);
      if (!on) svg.classList.add('was-h' + n);
      svg.classList.toggle('is-h' + n, on);
      h.setAttribute('aria-pressed', String(on));
    };
    h.addEventListener('click', toggle);
    h.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); } });
  }
  if (io) io.observe(svg); else if (tr === 'view') play();
  svg._gm = {
    vis(on) {
      svg.classList.toggle('is-off', !on);
      if (clock) {
        if (!on && !clock.paused) { clock.at = (performance.now() - clock.start) / 1000; clock.paused = true; }
        else if (on && clock.paused) { clock.start = performance.now() - clock.at * 1000; clock.paused = false; requestAnimationFrame(tick); }
      }
      if (on && tr === 'view' && !svg.classList.contains('is-play')) play();
    },
  };
}

const io = 'IntersectionObserver' in window ? new IntersectionObserver(list => {
  for (const e of list) {
    const r = e.boundingClientRect;
    e.target._gm?.vis(e.isIntersecting && (e.intersectionRatio >= 0.25 || r.height > innerHeight * 0.8));
  }
}, { threshold: [0, 0.25, 0.5] }) : null;

const scan = () => document.querySelectorAll('svg[data-gm-js]').forEach(init);
scan();
// nachgeladene Inhalte (Vorschau, Live-Blöcke)
if ('MutationObserver' in window) new MutationObserver(m => { if (m.some(x => x.addedNodes.length)) scan(); }).observe(document.documentElement, { childList: true, subtree: true });
