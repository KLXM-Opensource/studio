/**
 * Diagramme (Core\Charts) – Lader, unabhängig von der Engine.
 * Findet <figure data-chart>, lädt die Engine (data-chart-engine, ES-Modul) erst in Sichtweite und ruft
 *   render(el, spec, theme) → { update(theme), resize(), dispose() }
 * auf. theme = Farben aus CSS-Variablen der Figur (--chart-1 … --chart-8, --chart-ink, --chart-muted, --chart-grid, --chart-bg)
 * und Schrift – so folgen Diagramme Kit, Akzent und Hell/Dunkel. Nachgeladene Inhalte (z. B. Karten der Übersicht) erkennt
 * ein MutationObserver; Wechsel von Hell/Dunkel zeichnet neu. Ohne JavaScript bleibt die Tabelle.
 */
const engines = new Map();
const live = new Set();

const theme = (el) => {
  const cs = getComputedStyle(el);
  const v = (n) => cs.getPropertyValue(n).trim();
  const colors = [];
  for (let i = 1; i <= 8; i++) { const c = v('--chart-' + i); if (c) colors.push(c); }
  return { colors, ink: v('--chart-ink'), muted: v('--chart-muted'), grid: v('--chart-grid'), bg: v('--chart-bg'), font: cs.fontFamily,
    dark: v('--chart-dark') === '1' };
};

async function draw(fig) {
  if (fig.dataset.chartState && fig.dataset.chartState !== 'waiting') return;
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
    if ('ResizeObserver' in window) new ResizeObserver(() => inst.resize?.()).observe(box);
  } catch (e) {
    fig.dataset.chartState = 'error';
    fig.querySelector('.cms-chart__table')?.setAttribute('open', '');   // Engine fehlt: Werte als Tabelle
    console.warn('[charts]', e);
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

window.cmsCharts = { scan };
