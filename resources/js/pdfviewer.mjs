/*
 * PDF-Viewer auf Basis von Mozilla PDF.js (lokal unter /assets/vendor/pdfjs).
 * Seiten werden erst gerendert, wenn sie in Sichtweite kommen (schnell auch bei langen PDFs).
 * Scharfe Darstellung auf Retina-Displays, markierbarer Text (Textebene), Zoom, Seitennavigation.
 */
import * as pdfjs from '../vendor/pdfjs/pdf.min.mjs';

const base = new URL('../vendor/pdfjs/', import.meta.url).href;
pdfjs.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.mjs';

const $ = s => document.querySelector(s);
const doc = $('[data-pdf]'), status = $('[data-status]');
const pageInput = $('[data-page]'), pagesOut = $('[data-pages]'), zoomOut = $('[data-zoom]');
const ZOOMS = [0.5, 0.67, 0.8, 0.9, 1, 1.1, 1.25, 1.5, 1.75, 2, 2.5, 3];

let pdf, pages = [], scale = 1, fit = true, current = 1;

$('[data-back]')?.addEventListener('click', () => (history.length > 1 ? history.back() : (location.href = '/')));

async function start() {
  try {
    pdf = await pdfjs.getDocument({
      url: doc.dataset.pdf,
      cMapUrl: base + 'cmaps/', cMapPacked: true,
      standardFontDataUrl: base + 'standard_fonts/',
      wasmUrl: base + 'wasm/', iccUrl: base + 'iccs/',
      isEvalSupported: false,
    }).promise;
  } catch (e) {
    status.textContent = 'Das Dokument konnte nicht geladen werden. Bitte über „Herunterladen“ öffnen.';
    return;
  }
  status.remove();
  pagesOut.textContent = pdf.numPages;
  pageInput.max = pdf.numPages;
  const first = await pdf.getPage(1);
  const base1 = first.getViewport({ scale: 1 });
  for (let i = 1; i <= pdf.numPages; i++) {
    const el = document.createElement('section');
    el.className = 'pv-page';
    el.dataset.page = i;
    el.setAttribute('aria-label', `Seite ${i} von ${pdf.numPages}`);
    doc.append(el);
    pages.push({ el, w: base1.width, h: base1.height, rendered: 0, task: null });
  }
  applyScale();
  observe();
}

/** Zoom „an Breite anpassen“ = Breite des Dokumentbereichs */
function fitScale() {
  const avail = Math.min(doc.clientWidth - 32, 980);
  return Math.max(0.3, avail / pages[0].w);
}

function applyScale() {
  if (fit) scale = fitScale();
  zoomOut.textContent = Math.round(scale * 100) + ' %';
  for (const p of pages) {
    p.el.style.width = Math.floor(p.w * scale) + 'px';
    p.el.style.height = Math.floor(p.h * scale) + 'px';
    p.el.style.setProperty('--scale-factor', scale);
    p.el.style.setProperty('--total-scale-factor', scale);
    if (p.rendered && p.rendered !== scale) { p.rendered = 0; if (visible.has(p.el)) render(p); }
  }
}

const visible = new Set();
let io;
function observe() {
  io = new IntersectionObserver(entries => {
    for (const e of entries) {
      const p = pages[+e.target.dataset.page - 1];
      if (e.isIntersecting) { visible.add(e.target); render(p); } else visible.delete(e.target);
    }
  }, { root: doc, rootMargin: '600px 0px' });
  pages.forEach(p => io.observe(p.el));
  // Aktuelle Seite = die mit dem größten sichtbaren Anteil
  const spy = new IntersectionObserver(entries => {
    let best = null;
    for (const e of entries) if (e.isIntersecting && (!best || e.intersectionRatio > best.intersectionRatio)) best = e;
    if (best) { current = +best.target.dataset.page; pageInput.value = current; }
  }, { root: doc, threshold: [0.25, 0.5, 0.75] });
  pages.forEach(p => spy.observe(p.el));
}

async function render(p) {
  if (p.rendered === scale || p.busy) return;
  p.busy = true;
  const s = scale;
  try {
    const page = await pdf.getPage(+p.el.dataset.page);
    const vp = page.getViewport({ scale: s });
    // Seitengröße korrigieren, falls die Seite vom ersten Format abweicht
    const v1 = page.getViewport({ scale: 1 });
    if (v1.width !== p.w || v1.height !== p.h) { p.w = v1.width; p.h = v1.height; p.el.style.width = Math.floor(vp.width) + 'px'; p.el.style.height = Math.floor(vp.height) + 'px'; }
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(vp.width * ratio);
    canvas.height = Math.floor(vp.height * ratio);
    canvas.style.width = Math.floor(vp.width) + 'px';
    canvas.style.height = Math.floor(vp.height) + 'px';
    canvas.setAttribute('aria-hidden', 'true');
    await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp, transform: ratio !== 1 ? [ratio, 0, 0, ratio, 0, 0] : null }).promise;
    if (s !== scale) return;                       // inzwischen gezoomt → neu rendern
    const text = document.createElement('div');
    text.className = 'textLayer';
    p.el.replaceChildren(canvas, text);
    new pdfjs.TextLayer({ textContentSource: page.streamTextContent(), container: text, viewport: vp }).render().catch(() => {});
    p.rendered = s;
  } finally {
    p.busy = false;
    if (p.rendered !== scale && visible.has(p.el)) render(p);
  }
}

function go(n) {
  n = Math.max(1, Math.min(pdf?.numPages || 1, n));
  pages[n - 1]?.el.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
}
function zoom(dir) {
  fit = false;
  const i = ZOOMS.findIndex(z => z >= scale - 0.001);
  scale = dir > 0 ? (ZOOMS.find(z => z > scale + 0.001) ?? ZOOMS.at(-1)) : ([...ZOOMS].reverse().find(z => z < scale - 0.001) ?? ZOOMS[0]);
  keep(() => applyScale());
}
/** Beim Zoomen die aktuelle Seite im Blick behalten */
function keep(fn) {
  const n = current;
  fn();
  pages[n - 1]?.el.scrollIntoView({ block: 'start' });
}

$('[data-prev]').addEventListener('click', () => go(current - 1));
$('[data-next]').addEventListener('click', () => go(current + 1));
pageInput.addEventListener('change', () => go(+pageInput.value));
$('[data-zoom-in]').addEventListener('click', () => zoom(1));
$('[data-zoom-out]').addEventListener('click', () => zoom(-1));
$('[data-fit]').addEventListener('click', () => { fit = true; keep(applyScale); });
addEventListener('resize', () => { if (fit && pages.length) applyScale(); });
document.addEventListener('keydown', e => {
  if (e.target.matches('input')) return;
  if (e.key === '+' || e.key === '=') { e.preventDefault(); zoom(1); }
  else if (e.key === '-') { e.preventDefault(); zoom(-1); }
  else if (e.key === 'PageDown' || (e.key === 'ArrowRight' && !e.metaKey)) { e.preventDefault(); go(current + 1); }
  else if (e.key === 'PageUp' || (e.key === 'ArrowLeft' && !e.metaKey)) { e.preventDefault(); go(current - 1); }
});

start();
