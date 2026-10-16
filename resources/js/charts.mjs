/**
 * Diagramme (Core\Charts) – Lader, unabhängig von der Engine.
 * Findet <figure data-chart>, lädt die Engine (data-chart-engine, ES-Modul) erst in Sichtweite und ruft
 *   render(el, spec, theme) → { update(theme, {animation, print}?), resize(), dispose() }
 * auf (optional image() → Bild-URL für den Druck). theme = Farben aus CSS-Variablen der Figur (--chart-1 … --chart-8, --chart-ink, --chart-muted, --chart-grid, --chart-bg)
 * und Schrift – so folgen Diagramme Kit, Akzent und Hell/Dunkel. Nachgeladene Inhalte (z. B. Karten der Übersicht) erkennt
 * ein MutationObserver; Wechsel von Hell/Dunkel zeichnet neu. Ohne JavaScript bleibt die Tabelle.
 */
const engines = new Map();
const live = new Set();
const drawn = new WeakMap();   // figure → Promise der Engine-Instanz

// Farben: gesetzte Variablen (Kit, Verwaltung) haben Vorrang; sonst aus der Umgebung abgeleitet – Schriftfarbe und Hintergrund
// dort, wo das Diagramm steht. So passt es in jedes Kit und jeden Abschnitt, hell wie dunkel, ohne dessen Dunkelmodus zu kennen.
const rgb = (c) => (c.match(/[\d.]+/g) || []).map(Number);
const lum = ([r, g, b]) => { const f = (x) => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
const alpha = (c, a) => { const [r, g, b] = rgb(c); return `rgba(${r},${g},${b},${a})`; };
const background = (el) => {
  for (let n = el; n && n.nodeType === 1; n = n.parentElement) { const bg = getComputedStyle(n).backgroundColor; const p = rgb(bg); if (p.length >= 3 && (p.length < 4 || p[3] > 0.5)) return bg; }
  return 'rgb(255,255,255)';
};
const theme = (el) => {
  const cs = getComputedStyle(el);
  const v = (n) => cs.getPropertyValue(n).trim();
  const text = cs.color;
  const forced = v('--chart-dark');
  const dark = forced !== '' ? forced === '1' : lum(rgb(text)) > 0.4;   // helle Schrift = dunkler Grund
  const colors = [];
  for (let i = 1; i <= 8; i++) { const c = v((dark ? '--chart-d' : '--chart-') + i) || v('--chart-' + i); if (c) colors.push(c); }
  return { colors, dark, font: cs.fontFamily,
    ink: v('--chart-ink') || text, muted: v('--chart-muted') || alpha(text, 0.72), grid: v('--chart-grid') || alpha(text, 0.16), bg: v('--chart-bg') || background(el) };
};

function draw(fig) {
  if (!drawn.has(fig)) drawn.set(fig, drawNow(fig));
  return drawn.get(fig);
}

async function drawNow(fig) {
  fig.dataset.chartState = 'loading';
  try {
    const spec = JSON.parse(fig.querySelector('.cms-chart__data')?.textContent || '{}');
    const url = fig.dataset.chartEngine;
    if (!engines.has(url)) engines.set(url, import(url));
    const engine = await engines.get(url);
    const box = fig.querySelector('.cms-chart__canvas');
    const inst = engine.render(box, spec, theme(fig));
    live.add({ fig, inst });
    fig.dataset.chartState = 'ready';
    if ('ResizeObserver' in window) new ResizeObserver(() => { if (box.offsetWidth > 0) inst.resize?.(); }).observe(box);
    return inst;
  } catch (e) {
    fig.dataset.chartState = 'error';
    fig.querySelector('.cms-chart__table')?.setAttribute('open', '');   // Engine fehlt: Werte als Tabelle
    console.warn('[charts]', e);
    return null;
  }
}

const io = 'IntersectionObserver' in window
  ? new IntersectionObserver((list) => { for (const e of list) if (e.isIntersecting) { io.unobserve(e.target); draw(e.target); } }, { rootMargin: '200px' })
  : null;
const scan = (root = document) => root.querySelectorAll?.('[data-chart]:not([data-chart-state])').forEach((f) => {
  f.dataset.chartState = 'waiting';   // Fläche reservieren (ohne JavaScript bleibt nur die Tabelle)
  io ? io.observe(f) : draw(f);
});

scan();
new MutationObserver((ms) => { for (const m of ms) for (const n of m.addedNodes) if (n.nodeType === 1) { if (n.matches('[data-chart]')) scan(n.parentNode); else scan(n); } })
  .observe(document.body, { childList: true, subtree: true });

// Hell/Dunkel oder Akzent gewechselt: Farben neu übergeben
const refresh = () => { for (const { fig, inst } of live) if (fig.isConnected) inst.update?.(theme(fig)); };
matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', refresh);
new MutationObserver(refresh).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'data-accent', 'class'] });

// Drucken: Canvas-Diagramme passen sich der Papierbreite nicht zuverlässig an (der Browser setzt das Druckformat erst danach).
// Deshalb vor dem Drucken je Diagramm ein scharfes Bild (Engine: image(), optional) einsetzen, das im Druck mit der Breite skaliert.
const toPrint = () => {
  for (const { fig, inst } of live) {
    if (!fig.isConnected || !inst.image) continue;
    let img = fig.querySelector(':scope > .cms-chart__print');
    const box = fig.querySelector('.cms-chart__canvas');
    if (img && (!box || box.offsetWidth < 40 || matchMedia('print').matches)) continue;   // schon erzeugt, Fläche im Druck ausgeblendet
    if (!img) { img = document.createElement('img'); img.className = 'cms-chart__print'; img.alt = ''; fig.querySelector('.cms-chart__canvas')?.after(img); }
    try {
      const t = theme(fig);
      inst.update?.(t, { animation: false, print: true });   // Papierfassung: Legende vollständig
      img.src = inst.image();
      inst.update?.(t, { animation: false });
    } catch (e) { img.remove(); }
  }
};
const resizeAll = () => { for (const { fig, inst } of live) if (fig.isConnected) inst.resize?.(); };
addEventListener('beforeprint', toPrint);
addEventListener('afterprint', resizeAll);

// Bild eines Diagramms (z. B. für ein PDF vom Server): zeichnet bei Bedarf sofort, light = helle Fassung auch im Dunkelmodus
// (Papier). → Promise<string|null> (data:image/png)
const snapshot = async (fig, { light = true } = {}) => {
  if (io) io.unobserve(fig);
  const inst = await draw(fig);
  if (!inst?.image) return null;
  const t = theme(fig);
  let paper = t;
  if (light && t.dark) {
    const cs = getComputedStyle(fig);
    const colors = [];
    for (let i = 1; i <= 8; i++) { const c = cs.getPropertyValue('--chart-' + i).trim(); if (c) colors.push(c); }
    paper = { ...t, dark: false, colors, ink: '#1d2125', muted: 'rgba(29,33,37,.72)', grid: 'rgba(29,33,37,.16)', bg: '#ffffff' };
  }
  inst.update?.(paper, { animation: false, print: true });   // fertig gezeichnet (nicht mitten in der Animation), Legende vollständig
  try { return inst.image(); } finally { inst.update?.(t, { animation: false }); }
};

window.cmsCharts = { scan, resize: resizeAll, print: toPrint, snapshot };
