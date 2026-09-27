/*
 * CMS-Oberfläche auf der Website isolieren (Shadow DOM) – Teil von admin.js.
 *
 *  - Werkzeugleiste: serverseitig als Declarative Shadow DOM gerendert (Core\Theme::toolbarHost, .cms-bar-host);
 *    upgradeDSD() hängt den Schatten nach, falls der Browser <template shadowrootmode> nicht kennt.
 *  - Ebene (layer()): ein gemeinsamer Shadow-Host für Seitenleiste „Block“, Dialoge (Mediathek, Zuschnitt, Link,
 *    Suche), schwebende Formatierungsleiste, Kontextmenüs und Meldungen. Stylesheets per <link> (CSP: keine Inline-Styles).
 *  - In der Verwaltung (html.adm-ui) gibt es keine Theme-Styles: dort bleibt alles im Dokument (layerBox() = body).
 *
 * Ereignisse aus Schatten-Bäumen kommen am Dokument mit dem Host als target an – daher pathTarget()/pathClosest(),
 * und nicht „composed“ Ereignisse (change, submit) über listen() zusätzlich an jeder CMS-Wurzel.
 */
const d = document;

/** Verwaltung (admin.css im Dokument) – sonst Website mit Theme-CSS */
export const IN_ADMIN = d.documentElement.classList.contains('adm-ui');

/** Declarative Shadow DOM nachrüsten (ältere Browser): <template shadowrootmode> → attachShadow */
export function upgradeDSD(scope = d) {
  scope.querySelectorAll('template[shadowrootmode]').forEach(t => {
    const host = t.parentElement;
    if (!host || host.shadowRoot) { t.remove(); return; }
    host.attachShadow({ mode: t.getAttribute('shadowrootmode') === 'closed' ? 'closed' : 'open' }).append(t.content);
    t.remove();
  });
}

/** Host der Werkzeugleiste (.cms-bar-host) und seine Schatten-Wurzel */
export const barHost = () => d.querySelector('.cms-bar-host');
export const barRoot = () => barHost()?.shadowRoot || null;

/** Stylesheets für Schatten-Wurzeln (vom Server am Host der Werkzeugleiste, sonst vom Aufrufer gesetzt) */
let cssCfg = null;
export function uiCss() {
  if (!cssCfg) { try { cssCfg = JSON.parse(barHost()?.dataset.cmsCss || 'null'); } catch { cssCfg = null; } }
  return cssCfg || {};
}
export function setUiCss(c) { cssCfg = { ...uiCss(), ...c }; }
/** Darstellung der Verwaltung (hell/dunkel/automatisch) für Schatten-Hosts */
export const appearance = () => barHost()?.dataset.theme || '';

const roots = new Set();
const handlers = [];
/** Nicht „composed“ Ereignisse (change, submit, reset …) am Dokument UND an allen CMS-Schatten-Wurzeln empfangen */
export function listen(type, fn, opts) {
  handlers.push([type, fn, opts]);
  d.addEventListener(type, fn, opts);
  roots.forEach(r => r.addEventListener(type, fn, opts));
}
/** Schatten-Wurzel registrieren (bekommt alle listen()-Handler) */
export function addRoot(r) {
  if (!r || roots.has(r)) return r;
  roots.add(r);
  handlers.forEach(([type, fn, opts]) => r.addEventListener(type, fn, opts));
  return r;
}

/** Eigentliches Ziel eines Ereignisses (auch aus Schatten-Bäumen) */
export function pathTarget(e) {
  let t = e.composedPath?.()[0] || e.target;
  if (t && t.nodeType !== 1) t = t.parentElement || t.host || null;
  return t;
}
/** Nächstes Element im Ereignispfad, das zum Selektor passt (über Schatten-Grenzen hinweg) */
export function pathClosest(e, sel) {
  for (const n of e.composedPath?.() || [e.target]) {
    if (n.nodeType === 1 && n.matches(sel)) return n;
  }
  return null;
}
/** Liegt node (auch über Schatten-Grenzen) im Ereignispfad? */
export const inPath = (e, node) => !!node && (e.composedPath?.() || []).includes(node);

/** Fokussiertes Element – auch innerhalb von Schatten-Bäumen */
export function deepActive() {
  let a = d.activeElement;
  while (a?.shadowRoot?.activeElement) a = a.shadowRoot.activeElement;
  return a;
}

// ------------------------------------------------------------------ Gemeinsame Ebene für Dialoge & Leisten
let L = null;
/**
 * { host, root, box }: box ist der Container für Dialoge/Leisten. In der Verwaltung: body (kein Shadow DOM nötig).
 * Auf der Website: #cms-layer-host mit admin.shadow.css + editor.shadow.css.
 */
export function layer() {
  if (L) return L;
  if (IN_ADMIN) return (L = { host: null, root: d, box: d.body });
  const host = d.createElement('div');
  host.id = 'cms-layer-host';
  host.className = 'adm-ui';
  if (appearance()) host.dataset.theme = appearance();
  // Persönliche Akzentfarbe (Core\Accent) wie an der Werkzeugleiste
  for (const k of ['accent', 'side']) { const v = barHost()?.dataset[k]; if (v) host.dataset[k] = v; }
  // Lage per CSSOM (CSP-konform): Nullpunkt der Seite, damit absolute Kinder Dokument-Koordinaten nutzen
  host.style.cssText = 'position:absolute;top:0;left:0;width:0;height:0;z-index:2147483200;display:block';   // Z-Skala: editor.css
  const root = host.attachShadow({ mode: 'open' });
  const c = uiCss();
  for (const href of [c.admin, c.ui].filter(Boolean)) {
    const l = d.createElement('link'); l.rel = 'stylesheet'; l.href = href; root.append(l);
  }
  const box = d.createElement('div');
  box.className = 'cms-layer';
  root.append(box);
  // Link-Vorschläge (<input list="cms-links">) müssen im selben Baum liegen
  const dl = d.getElementById('cms-links');
  if (dl) box.append(dl);
  else { const n = d.createElement('datalist'); n.id = 'cms-links'; box.append(n); }
  d.body.append(host);
  addRoot(root);
  return (L = { host, root, box });
}
export const layerBox = () => layer().box;
export const layerRoot = () => layer().root;

/** Element per Selektor im Dokument, in der Werkzeugleiste oder in der Ebene suchen */
export function ui(sel) {
  return d.querySelector(sel) || barRoot()?.querySelector(sel) || (L?.host ? L.root.querySelector(sel) : null);
}
export function uiAll(sel) {
  return [...d.querySelectorAll(sel), ...(barRoot()?.querySelectorAll(sel) || []), ...(L?.host ? L.root.querySelectorAll(sel) : [])];
}
/** Offener modaler Dialog irgendwo (Dokument, Ebene, Seitenleiste „Eintrag bearbeiten“) */
export function openDialog() {
  if (d.querySelector('dialog[open]')) return d.querySelector('dialog[open]');
  for (const r of roots) { const x = r.querySelector('dialog[open]'); if (x) return x; }
  return null;
}

/**
 * Schatten-Wurzel für ein kleines Bedienelement mitten in der Seite (z. B. Block-Leiste) anlegen.
 * Gibt die Wurzel zurück; Stylesheet: editor.shadow.css.
 */
export function shadowFor(host, html = '') {
  const root = host.attachShadow({ mode: 'open' });
  const href = uiCss().ui;
  root.innerHTML = (href ? `<link rel="stylesheet" href="${href.replace(/"/g, '&quot;')}">` : '') + html;
  return root;
}

/** Werkzeugleiste: Schatten-Wurzel registrieren und Höhe für klebende Theme-Köpfe bereitstellen */
export function initBar() {
  upgradeDSD();
  const host = barHost();
  if (!host?.shadowRoot) return;
  addRoot(host.shadowRoot);
  const html = d.documentElement;
  html.classList.add('cms-has-bar');
  // --cms-bar-h: Höhe der Leiste · --cms-bar-offset: sichtbare Unterkante (Desktop = Höhe, da klebend;
  // Telefon: Leiste scrollt mit → schrumpft bis 0). Klebende/feste Theme-Köpfe: top: var(--cms-bar-offset) (editor.css)
  let raf = 0;
  const offset = () => { raf = 0; html.style.setProperty('--cms-bar-offset', Math.max(0, Math.round(host.getBoundingClientRect().bottom)) + 'px'); pinHeads(); };
  const set = () => { html.style.setProperty('--cms-bar-h', host.offsetHeight + 'px'); offset(); };
  set();
  if ('ResizeObserver' in window) new ResizeObserver(set).observe(host);
  window.addEventListener('scroll', () => { raf ||= requestAnimationFrame(offset); }, { passive: true });
  window.addEventListener('resize', () => { raf ||= requestAnimationFrame(offset); }, { passive: true });
  // Kits schalten klebende Köpfe per Klasse um (is-stuck, nav-sticky …): Lage neu prüfen
  if ('MutationObserver' in window) {
    const mo = new MutationObserver(() => { raf ||= requestAnimationFrame(offset); });
    [html, d.body, ...siteHeads()].forEach(n => n && mo.observe(n, { attributes: true, attributeFilter: ['class'] }));
  }
}

/*
 * Kopf des Kits neben der Werkzeugleiste (editor.css, „Z-Skala“):
 *  - data-cms-header: Kopf der Website (header außerhalb von main/article/section …, .site-header, [role=banner],
 *    [data-cms-sticky]) – liegt im Bearbeiten über den Bedienelementen der Blöcke, seine Menüs damit auch.
 *  - data-cms-pinned: nur solange der Kopf wirklich klebt/fest steht (position sticky/fixed) – nur dann schiebt ihn
 *    editor.css unter die Leiste (top: --cms-bar-offset). Ein Kit, das seinen Kopf im Bearbeiten nicht kleben lässt
 *    (z. B. .is-editing .hdr{position:relative}), wird so nicht mehr um die Höhe der Leiste verschoben.
 */
const HEAD_SEL = '[data-cms-sticky],[data-cms-header],.site-header,[role=banner],header';
const HEAD_SKIP = 'main,article,section,aside,footer,nav,dialog,[popover],.cms-editor,.cms-block,.cms-bar-host';
function siteHeads() {
  return [...d.querySelectorAll(HEAD_SEL)].filter(el => el.matches('[data-cms-sticky],[data-cms-header]') || !el.parentElement?.closest(HEAD_SKIP));
}
function pinHeads() {
  for (const el of siteHeads()) {
    if (!el.hasAttribute('data-cms-header')) el.setAttribute('data-cms-header', '');
    const p = getComputedStyle(el).position;
    el.toggleAttribute('data-cms-pinned', p === 'sticky' || p === 'fixed');
  }
}

/**
 * Oberkante des frei sichtbaren Bereichs (Viewport-Pixel): Unterkante der Werkzeugleiste bzw. eines oben klebenden
 * Kit-Kopfs. Schwebende Bedienelemente (Knöpfe am Bild …) rücken darunter, statt den Kopf zu verdecken.
 */
export function topInset() {
  let y = Math.max(0, barHost()?.getBoundingClientRect().bottom || 0);
  for (const el of d.querySelectorAll('[data-cms-pinned]')) {
    const r = el.getBoundingClientRect();
    if (r.height && r.top <= y + 2 && r.bottom > y) y = r.bottom;
  }
  return y;
}

// Polyfill sofort (vor allen anderen Modulen, die die Werkzeugleiste abfragen)
upgradeDSD();
