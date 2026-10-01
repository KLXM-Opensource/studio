/**
 * Glossar: Begriffe in Inhalten markieren, die erst im Browser entstehen (z. B. Prüfergebnisse einer Erweiterung).
 * Geladen von glossary.js, wenn die Seite einen „dynamischen Bereich“ hat (Einstellung bzw. data-glossary="live").
 * Begriffe kommen beim ersten Inhalt aus /_glossary.json (nur veröffentlichte). Gleiche Regeln wie Core\Glossary\Annotator:
 * Wortgrenzen, Abkürzungen nur in genauer Schreibweise, längste Variante zuerst, nie in Links/Buttons/Code/Überschriften usw.
 * Jeder Bereich zählt als eigener Abschnitt: dort je Begriff das erste Vorkommen. Nur DOM-Methoden, kein innerHTML.
 */
const SKIP = 'a,button,label,summary,legend,select,option,textarea,input,code,pre,kbd,samp,var,abbr,dfn,nav,time,script,style,svg,template,'
  + '[data-glossary=off],[contenteditable],[aria-hidden=true],[hidden],[inert],.gl,.sr-only,.visually-hidden,.screen-reader-text,[class*=eyebrow],[class*=kicker],[class*=badge],[class*=chip],[role=button],[role=link],[role=tab],[role=heading]';
const L = '[\\p{L}\\p{N}_@./:&=\\\\]', R = '(?![\\p{L}\\p{N}_@=]|[.:/][\\p{L}\\p{N}])';
const esc = (s) => s.replace(/[.*+?^${}()|[\]\\/]/g, '\\$&').replace(/ /g, '\\s+');
const OPT = { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['hidden'] };
let n = 0;

// Wortendungen je Sprache (wie Annotator::alternative): Englisch -s/-es bzw. y → ies, sonst die deutschen Endungen
const EN = (j) => /^en(-|$)/.test(j.lang || '');

function prep(j) {
  const cs = new Map(), ci = new Map(), a = [[], []], en = EN(j);
  for (const t of j.terms) {
    for (let [v, exact] of t.v) {
      const q = /^["„“](.+)["“”]$/u.exec(v);
      if (q) { v = q[1]; exact = 1; }
      if (v.length < 2) continue;
      // Endungen nach dem letzten Wort (wie Annotator::alternative): Abkürzung → Plural-s, Wort ab 4 Buchstaben → übliche Endungen
      const last = v.split(/[\s\-/]+/).pop();
      const word = !q && !(/\p{Lu}$/u.test(v) && (last.match(/\p{Lu}/gu) || []).length >= 2) && last.length >= 4 && /\p{Ll}$/u.test(v) && !/\p{Ll}\p{Lu}/u.test(last);
      const ies = word && en && /[^aeiouy]y$/i.test(last);
      const suf = q ? '' : /\p{Lu}$/u.test(v) && (last.match(/\p{Lu}/gu) || []).length >= 2 ? 's?'
        : word ? (en ? (ies ? '' : '(?:s|es)?') : '(?:e|en|n|s|es|er|ern)?') : '';
      (exact ? cs : ci).set(exact ? v : v.toLowerCase(), t);
      a[exact ? 0 : 1].push([v.length, (ies ? esc(v.slice(0, -1)) + '(?:y|ies)' : esc(v)) + suf]);
    }
  }
  const rx = (list, f) => list.length ? new RegExp('(?<!' + L + ')(?:' + list.sort((x, y) => y[0] - x[0]).map((x) => x[1]).join('|') + ')' + R, f) : null;
  return { cs, ci, en, rx: [rx(a[0], 'gu'), rx(a[1], 'giu')] };
}

// Begriff zum Treffer: genau, sonst ohne Plural-s bzw. übliche Endungen
function find(D, m, exact) {
  const s = m.replace(/\s+/g, ' '), map = exact ? D.cs : D.ci, k = exact ? s : s.toLowerCase();
  if (map.has(k)) return map.get(k);
  if (D.en && k.endsWith('ies') && map.has(k.slice(0, -3) + 'y')) return map.get(k.slice(0, -3) + 'y');
  for (const e of D.en ? ['es', 's'] : ['ern', 'er', 'es', 'en', 'e', 'n', 's']) if (k.endsWith(e) && map.has(k.slice(0, -e.length))) return map.get(k.slice(0, -e.length));
  return null;
}

function el(tag, cls, text) {
  const e = document.createElement(tag);
  if (cls) e.className = cls;
  if (text) e.textContent = text;
  return e;
}

function make(t, text, lab) {
  const id = 'gll-' + (++n), w = el('span', 'gl'), b = el('button', 'gl-term', text), p = el('span', 'gl-pop'), h = el('span', 'gl-pop__head'), x = el('button', 'gl-pop__x');
  w.dataset.gl = t.k;
  b.type = x.type = 'button';
  b.setAttribute('popovertarget', id);
  b.setAttribute('aria-expanded', 'false');
  b.setAttribute('aria-controls', id);
  p.id = id;
  p.setAttribute('popover', '');
  x.setAttribute('popovertarget', id);
  x.setAttribute('popovertargetaction', 'hide');
  x.setAttribute('aria-label', lab.close);
  const xi = el('span', '', '×');
  xi.setAttribute('aria-hidden', 'true');
  x.append(xi);
  h.append(el('span', 'gl-pop__t', t.t), x);
  p.append(h, el('span', 'gl-pop__d', t.s));
  if (t.u) {
    const m = el('a', 'gl-pop__more', lab.more + ' '), ar = el('span', '', '→');
    m.href = t.u;
    ar.setAttribute('aria-hidden', 'true');
    m.append(ar);
    p.append(m);
  }
  w.append(b, p);
  return w;
}

function scan(D, j, root) {
  const seen = new Set([...root.querySelectorAll('.gl[data-gl]')].map((g) => g.dataset.gl));
  const skip = SKIP + (j.headings > 0 ? ',' + Array.from({ length: j.headings }, (_, i) => 'h' + (i + 1)).join(',') : '');
  const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
    acceptNode: (t) => /\p{L}{2}/u.test(t.data) && !t.parentElement?.closest(skip) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT,
  });
  const nodes = [];
  while (w.nextNode()) nodes.push(w.currentNode);
  for (let node of nodes) {
    // Treffer beider Ausdrücke (genaue Schreibweise / ohne Groß-Klein), nach Position; gleiche Position: längerer zuerst
    const hits = [];
    D.rx.forEach((r, k) => { if (r) for (const m of node.data.matchAll(r)) hits.push([m.index, m[0], !k]); });
    hits.sort((a, b) => a[0] - b[0] || b[1].length - a[1].length);
    let end = -1;
    const todo = [];
    for (const [i, s, exact] of hits) {
      if (i < end) continue;
      end = i + s.length;
      const t = find(D, s, exact);
      if (t && !seen.has(t.k)) { seen.add(t.k); todo.push([i, s, t]); }
    }
    for (const [i, s, t] of todo.reverse()) {
      const rest = node.splitText(i);
      rest.splitText(s.length);
      rest.replaceWith(make(t, s, j.labels));
    }
  }
}

export default function (cfg) {
  const roots = [...document.querySelectorAll(cfg.live)];
  if (!roots.length) return;
  let data = null;
  const load = () => (data ??= fetch(cfg.src, { credentials: 'same-origin' }).then((r) => (r.ok ? r.json() : null)).then((j) => (j?.terms?.length ? [prep(j), j] : null)).catch(() => null));
  const run = (root, mo) => {
    if (!/\p{L}{2}/u.test(root.textContent || '')) return;
    load().then((x) => {
      if (!x) return;
      mo?.disconnect();
      scan(x[0], x[1], root);
      mo?.observe(root, OPT);
    });
  };
  for (const root of roots) {
    let t;
    const mo = new MutationObserver(() => { clearTimeout(t); t = setTimeout(() => run(root, mo), 150); });
    mo.observe(root, OPT);
    run(root, mo);
  }
}
