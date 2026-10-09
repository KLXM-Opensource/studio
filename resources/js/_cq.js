/*
 * Seiten-Editor: Website reagiert auf ihre eigene Breite statt auf die Fensterbreite (Teil von editor.js).
 *
 * Mit offener Seitenleiste wird die Website schmaler (editor.css body.has-drawer) – Media Queries des Kits fragen aber die
 * Fensterbreite ab, die Seite bliebe im Desktop-Layout. Deshalb im Editor:
 *  - Hülle .cms-cq-site um Kopf, Inhalt und Fuß (alle Kinder von <body> außer den Ebenen des Editors und Elementen mit
 *    position:fixed – die blieben sonst in der Hülle gefangen); sie ist ein Container („site“, inline-size).
 *  - Stylesheets der Seite (gleiche Domain, nicht die des Editors) werden geladen und umgeschrieben: @media-Abfragen, die nur die
 *    Breite prüfen, werden zu @container site (…); vw → calc(n * var(--cms-vw)) – 1 % der Seitenbreite in px, am <html> gesetzt
 *    (ResizeObserver); nicht cqi: in Variablen wie Schriftskalen würde cqi erst am verwendenden Element und damit gegen den
 *    nächsten (oft schmalen) Container gerechnet – Überschriften wurden im Editor kleiner; darin :root/html/body → .cms-cq-site>*
 *    (Variablen und Schriftgrößen der Haltepunkte); url(…) bleiben gültig. Das Original wird abgeschaltet (<link disabled>).
 *  - Abfragen mit anderen Merkmalen (prefers-*, hover, print, orientation …) bleiben unverändert.
 * Grenzen: Skripte des Kits, die die Fensterbreite abfragen (matchMedia), sehen weiter das Fenster; rem bezieht sich weiter auf <html>.
 */
const d = document;
const SKIP_HOST = '.cms-bar-host,#cms-layer-host,#cms-epanel-host,script,style,link,template,noscript';
const WIDTH = /\(\s*(?:min-|max-)?width\s*[:<>=]|\(\s*[\d.]+[a-z%]*\s*[<>]=?\s*width|\bwidth\s*[<>]=?/i;
const OTHER = /\(\s*(?!(?:min-|max-)?width\b)[a-z-]+\s*[:)]|\b(print|speech)\b/i;

/** Eine Medienabfrage: nur Breite (ggf. mit screen/all) → Container-Bedingung, sonst null */
function toContainer(prelude) {
  const parts = prelude.split(',').map(p => p.trim()).filter(Boolean);
  const out = [];
  for (let q of parts) {
    q = q.replace(/^only\s+/i, '').replace(/^(screen|all)\s+and\s+/i, '');
    if (/^(screen|all)$/i.test(q)) return null;   // gilt immer – nicht umschreiben
    if (!WIDTH.test(q) || OTHER.test(q) || /^not\b/i.test(q)) return null;
    out.push(q);
  }
  return out.length ? out.join(' or ') : null;
}

/** Passende schließende Klammer ab Index der öffnenden (Strings/Kommentare grob überspringen) */
function closing(css, open) {
  let depth = 0;
  for (let i = open; i < css.length; i++) {
    const c = css[i];
    if (c === '/' && css[i + 1] === '*') { const e = css.indexOf('*/', i + 2); if (e < 0) return -1; i = e + 1; continue; }
    if (c === '"' || c === "'") { const e = css.indexOf(c, i + 1); if (e < 0) return -1; i = e; continue; }
    if (c === '{') depth++;
    else if (c === '}' && --depth === 0) return i;
  }
  return -1;
}

/** Selektoren :root, html, body (allein stehend) in einem Container-Block auf die Kinder der Hülle umlenken */
const rootSel = block => block.replace(/(^|[{};]\s*)((?:\s*(?::root|html|body)\s*,?)+)(?=\{)/g, (m, pre, sels) =>
  pre + sels.split(',').map(s => s.trim()).filter(Boolean).map(() => '.cms-cq-site>*').filter((v, i, a) => a.indexOf(v) === i).join(',') + ' ');

export function convertCss(css, base) {
  // url(…) relativ zur Datei → absolut (der Text landet in einem <style> der Seite)
  css = css.replace(/url\(\s*(['"]?)(?!data:|https?:|\/|#)([^'")]+)\1\s*\)/gi, (m, q, p) => `url(${q}${new URL(p, base).href}${q})`);
  let out = '', i = 0;
  const re = /@media\b([^{;]*)\{/gi;
  let m;
  while ((m = re.exec(css))) {
    const cond = toContainer(m[1]);
    if (!cond) continue;
    const open = m.index + m[0].length - 1, end = closing(css, open);
    if (end < 0) break;
    out += css.slice(i, m.index) + '@container site ' + cond + ' {' + rootSel(css.slice(open + 1, end)) + '}';
    i = end + 1;
    re.lastIndex = i;
  }
  out += css.slice(i);
  return out.replace(/(-?\d*\.?\d+)vw\b/g, 'calc($1 * var(--cms-vw, 1vw))');
}

const cache = new Map();   // Adresse → umgeschriebenes CSS (Promise) – Blöcke werden oft neu gezeichnet
const own = l => {
  try {
    const u = new URL(l.href, location.href);
    return u.origin === location.origin && !/\/assets\/(css\/(editor|admin)|vendor\/)/.test(u.pathname) && !l.disabled && !l.dataset.cq;
  } catch { return false; }
};
async function convertLink(l) {
  if (!own(l)) return;
  l.dataset.cq = '1';
  try {
    if (!cache.has(l.href)) cache.set(l.href, fetch(l.href, { credentials: 'same-origin' }).then(r => (r.ok ? r.text() : '')).then(css => (/@media|\dvw\b/i.test(css) ? convertCss(css, l.href) : '')));
    const css = await cache.get(l.href);
    if (!css || !l.isConnected) return;
    const st = d.createElement('style');
    st.dataset.cqFrom = l.getAttribute('href');
    if (l.media && l.media !== 'all') st.media = l.media;
    st.textContent = css;
    l.after(st);
    l.disabled = true;
  } catch { /* Original bleibt */ }
}

/** Hülle anlegen und Stylesheets umschreiben – einmal beim Start des Editors */
export async function responsiveEditing() {
  if (d.querySelector('.cms-cq-site') || !('container' in d.documentElement.style || CSS.supports?.('container-type', 'inline-size'))) return;
  const kids = [...d.body.children].filter(el => !el.matches(SKIP_HOST) && getComputedStyle(el).position !== 'fixed');
  if (!kids.length) return;
  const wrap = d.createElement('div');
  wrap.className = 'cms-cq-site';
  kids[0].before(wrap);
  kids.forEach(k => wrap.append(k));
  // 1 % der Seitenbreite als feste Länge (ersetzt vw in den umgeschriebenen Stylesheets) – folgt der Seitenleiste
  const setVw = () => d.documentElement.style.setProperty('--cms-vw', wrap.clientWidth / 100 + 'px');
  setVw();
  if ('ResizeObserver' in window) new ResizeObserver(setVw).observe(wrap);
  [...d.querySelectorAll('link[rel~="stylesheet"][href]')].forEach(convertLink);
  // Später eingefügte Stylesheets (Blöcke bringen eigenes CSS in ihrer Vorschau mit, z. B. der Einstieg) ebenfalls umschreiben
  new MutationObserver(list => list.forEach(r => r.addedNodes.forEach(n => {
    if (n.nodeType !== 1) return;
    if (n.matches('link[rel~="stylesheet"][href]')) convertLink(n);
    else n.querySelectorAll?.('link[rel~="stylesheet"][href]').forEach(convertLink);
  }))).observe(d.body, { childList: true, subtree: true });
}
