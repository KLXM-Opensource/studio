/**
 * Symbole (Phosphor Icons, Stil „duotone“, MIT) → SVG-Sprite + Katalog.
 *
 *   Quelle:  resources/icons/icons.json            kuratierte Auswahl nach Themen (de/en-Bezeichnung, Suchbegriffe)
 *   Paket:   node_modules/@phosphor-icons/core      Roh-SVGs je Stil (assets/duotone/{name}-duotone.svg) + Metadaten (Tags)
 *   Ziel:    public/assets/icons/core.svg           Kern-Sprite: Thema „ui“ + Liste „core“ (Verwaltung, Bedienelemente)
 *            public/assets/icons/{thema}.svg        ein Sprite je Thema (Symbole, die dort definiert sind; null-Verweise liegen in ihrer Heimat)
 *            public/assets/icons/icons.svg          alle Symbole (Rückwärtskompatibilität)
 *            public/assets/icons/icons-map.json     Symbolname → Datei (ohne .svg), z. B. {"car":"transport"}
 *            public/assets/icons/catalog.json       Themen + Bezeichnungen + Suchbegriffe (Symbolauswahl, Übersicht „Symbole“)
 *   Einbindung: <svg><use href="/assets/icons/{datei}.svg?v=…#i-name"/></svg>; Website-Besucher: Sprite der Website (Core\Icons::siteSprite)
 *            public/assets/icons/LICENSE.txt        MIT-Lizenz von Phosphor
 *
 * Duotone: jedes Symbol hat eine Fläche mit opacity 0.2 (zweite Ebene) und die Kontur. Beide übernehmen currentColor;
 * die Deckkraft der zweiten Ebene lässt sich per CSS-Variable --ico-2-opacity steuern (Standard 0.2).
 */
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';

const ARGS = { M: 2, L: 2, H: 1, V: 1, C: 6, S: 4, Q: 4, T: 2, A: 7, Z: 0 };

/**
 * Pfaddaten kompakt: ganzzahlige Koordinaten (256er-Raster – Abweichung ≤ 0,5 Einheiten, bei 18 px < 0,04 px),
 * relative Befehle gegen die gerundete Position gerechnet (kein Aufsummieren von Rundungsfehlern), minimale Trennzeichen.
 */
function minifyPath(d) {
  const toks = d.match(/[a-zA-Z]|-?(?:\d+\.?\d*|\.\d+)(?:e[-+]?\d+)?/g) || [];
  let i = 0, out = '', last = '', cmd = '';
  let cx = 0, cy = 0, rx = 0, ry = 0, sx = 0, sy = 0, srx = 0, sry = 0;   // Position exakt / gerundet, Start des Teilpfads
  const num = () => parseFloat(toks[i++]);
  // Zahlenfolge mit minimalen Trennzeichen an eine Zeichenkette hängen
  const join = (str, vals) => {
    for (const v of vals) {
      const s = typeof v === 'string' ? v : String(v === 0 ? 0 : v);
      str += (/[\d]$/.test(str) && !s.startsWith('-') ? ' ' : '') + s;
    }
    return str;
  };
  while (i < toks.length) {
    if (/^[a-zA-Z]$/.test(toks[i])) cmd = toks[i++];
    const up = cmd.toUpperCase(), rel = cmd !== up;
    if (up === 'Z') { if (last !== 'z') out += 'z'; last = 'z'; cx = sx; cy = sy; rx = srx; ry = sry; continue; }
    const a = Array.from({ length: ARGS[up] }, num);
    const bx = rel ? cx : 0, by = rel ? cy : 0;
    // Zielpunkte absolut (gerundet); relative Ausgabe gegen die gerundete Position → keine Fehlerfortpflanzung
    const AX = v => Math.round(bx + v), AY = v => Math.round(by + v);
    const variant = relOut => {
      const X = v => AX(v) - (relOut ? rx : 0), Y = v => AY(v) - (relOut ? ry : 0);
      if (up === 'H') return [X(a[0])];
      if (up === 'V') return [Y(a[0])];
      if (up === 'A') return [Math.round(a[0]) || 1, Math.round(a[1]) || 1, Math.round(a[2]), (a[3] ? '1' : '0') + (a[4] ? '1' : '0'), X(a[5]), Y(a[6])];
      return a.map((v, k) => (k % 2 ? Y(v) : X(v)));
    };
    const fmt = (letter, vals) => {
      // Flags (Bogen) ohne Trennzeichen: „0 01“ + Zahl direkt dahinter
      if (up === 'A') {
        let s = join(letter === last && up !== 'M' ? out : out + letter, vals.slice(0, 3));
        s += ' ' + vals[3];
        s += String(vals[4]);
        return join(s, [vals[5]]);
      }
      return join(letter === last && up !== 'M' ? out : out + letter, vals);
    };
    const first = out === '';
    const absL = up, relL = up.toLowerCase();
    const cand = [fmt(absL, variant(false))];
    if (!first) cand.push(fmt(relL, variant(true)));
    const best = cand.reduce((x, y) => (y.length < x.length ? y : x));
    const usedRel = !first && best === cand[1] && cand[1].length < cand[0].length;
    out = best;
    last = up === 'M' ? (usedRel ? 'l' : 'L') : (usedRel ? relL : absL);
    if (up === 'H') cx = bx + a[0];
    else if (up === 'V') cy = by + a[0];
    else { cx = bx + a[a.length - 2]; cy = by + a[a.length - 1]; }
    rx = Math.round(cx); ry = Math.round(cy);
    if (up === 'M') { sx = cx; sy = cy; srx = rx; sry = ry; cmd = rel ? 'l' : 'L'; }   // weitere Paare nach M = Linie
  }
  return out;
}

export async function icons({ root, nm, log = console.log }) {
  const src = path.join(root, 'resources/icons/icons.json');
  const dir = nm('@phosphor-icons/core/assets/duotone');
  if (!fs.existsSync(src)) return;
  if (!fs.existsSync(dir)) { log('  Symbole übersprungen (@phosphor-icons/core fehlt – pnpm install)'); return; }
  const def = JSON.parse(fs.readFileSync(src, 'utf8'));

  // Tags aus den Phosphor-Metadaten (englische Suchbegriffe)
  let tags = {};
  try {
    const meta = await import(new URL('file://' + nm('@phosphor-icons/core/dist/index.mjs')).href);
    for (const i of meta.icons || []) tags[i.name] = (i.tags || []).filter(t => !t.startsWith('*')).join(' ');
  } catch { tags = {}; }

  const info = {}, topics = [], missing = [];
  for (const t of def.topics) {
    for (const [name, v] of Object.entries(t.icons)) if (v) info[name] ??= v;
  }
  for (const t of def.topics) {
    const names = [];
    for (const name of Object.keys(t.icons)) {
      if (!info[name]) { missing.push(name + ' (ohne Bezeichnung)'); continue; }
      if (!fs.existsSync(path.join(dir, `${name}-duotone.svg`))) { missing.push(name); continue; }
      if (!names.includes(name)) names.push(name);
    }
    topics.push({ key: t.key, de: t.de, en: t.en, icons: names });
  }
  if (missing.length) throw new Error('Symbole nicht gefunden: ' + missing.join(', '));

  // Heimat je Symbol: Kern-Sprite („core“ + Thema „ui“) oder das Thema, in dem es definiert ist (nicht null)
  const coreSet = new Set([...(def.core || []), ...Object.keys(def.topics.find(t => t.key === 'ui')?.icons || {})]);
  const home = {};
  for (const n of coreSet) if (!info[n]) missing.push(n + ' (core)');
  if (missing.length) throw new Error('Symbole nicht gefunden: ' + missing.join(', '));
  for (const t of def.topics) for (const [n, v] of Object.entries(t.icons)) if (v && !home[n]) home[n] = coreSet.has(n) ? 'core' : t.key;

  const style = '<style>[opacity]{opacity:var(--ico-2-opacity,.2)}</style>';
  const wrap = syms => '<svg xmlns="http://www.w3.org/2000/svg">' + style + syms.join('') + '</svg>\n';
  const symbols = [], files = {}, catalog = {};
  for (const name of Object.keys(info).sort()) {
    const svg = fs.readFileSync(path.join(dir, `${name}-duotone.svg`), 'utf8');
    const paths = [...svg.matchAll(/<path d="([^"]+)"( opacity="[^"]+")?\/>/g)]
      .map(([, d, op]) => op ? `<path opacity=".2" d="${minifyPath(d)}"/>` : `<path d="${minifyPath(d)}"/>`);
    const sym = `<symbol id="i-${name}" viewBox="0 0 256 256">${paths.join('')}</symbol>`;
    symbols.push(sym);
    (files[home[name]] ??= []).push(sym);
    const [de, en, kw] = info[name];
    // [Bezeichnung de, label en, Suchbegriffe (de), Name + Tags (en, Phosphor)]
    catalog[name] = [de, en, kw, `${name.replace(/-/g, ' ')} ${tags[name] || ''}`.trim()];
  }

  const out = path.join(root, 'public/assets/icons');
  fs.mkdirSync(out, { recursive: true });
  // Alte Themen-Sprites entfernen (umbenannte/gelöschte Themen); icons.svg, core.svg und sites/ bleiben
  const keep = new Set(['icons.svg', ...Object.keys(files).map(f => f + '.svg')]);
  for (const f of fs.readdirSync(out)) if (f.endsWith('.svg') && !keep.has(f)) fs.unlinkSync(path.join(out, f));
  // Die zweite Ebene ([opacity]) nimmt --ico-2-opacity des einbindenden Elements (vererbt durch <use>)
  const sizes = [];
  const write = (file, content) => {
    fs.writeFileSync(path.join(out, file), content);
    sizes.push(`${file} ${(Buffer.byteLength(content) / 1024).toFixed(1)}/${(zlib.gzipSync(content, { level: 9 }).length / 1024).toFixed(1)}`);
  };
  write('icons.svg', wrap(symbols));   // alle Symbole (Rückwärtskompatibilität, eigene Themes/Erweiterungen)
  for (const [file, syms] of Object.entries(files)) write(file + '.svg', wrap(syms));
  // Index Symbolname → Datei (ohne .svg): Core\Icons::svg(), resources/js/_icons.js (von esbuild eingebunden)
  const map = Object.fromEntries(Object.keys(info).sort().map(n => [n, home[n]]));
  fs.writeFileSync(path.join(out, 'icons-map.json'), JSON.stringify(map));
  fs.writeFileSync(path.join(out, 'catalog.json'), JSON.stringify({ topics, icons: catalog, files: Object.keys(files) }));
  fs.copyFileSync(nm('@phosphor-icons/core/LICENSE'), path.join(out, 'LICENSE.txt'));
  styleSprites({ root, nm, names: Object.keys(info).sort(), out, log });
  const pkg = JSON.parse(fs.readFileSync(nm('@phosphor-icons/core/package.json'), 'utf8'));
  log(`  Symbole  ${symbols.length} aus @phosphor-icons/core ${pkg.version} (${pkg.license}) → public/assets/icons/ (KB roh/gzip): ${sizes.join(' · ')}`);
}

/**
 * Symbolstile für die Website (Grundeinstellungen → „Symbolstil auf der Website“, Core\Icons::style()): je Stil ein Sprite mit
 * ALLEN Symbolen unter gleichem Namen – public/assets/icons/styles/{stil}.svg. Die Website baut daraus ihr kleines Sprite.
 *   Phosphor: thin, light, regular, bold, fill (gleiche Namen wie duotone, vollständig)
 *   Lucide (ISC) und Tabler (MIT): Linien-Symbole; Zuordnung Phosphor-Name → Name in resources/icons/sets.json,
 *   fehlende Symbole aus Phosphor „regular“ (gleiche Bildsprache: Linie)
 * Linien-Symbole bringen fill/stroke am <symbol> mit (vererbt sich im <use>-Baum, schlägt fill:currentColor der .ico-Hülle).
 */
function styleSprites({ root, nm, names, out, log }) {
  const dir = path.join(out, 'styles');
  fs.mkdirSync(dir, { recursive: true });
  const inner = svg => svg.replace(/<!--.*?-->/gs, '').replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '')
    .replace(/<path stroke="none" d="M0 0h24v24H0z" fill="none"\s*\/>/, '').replace(/\s+/g, ' ').replace(/> </g, '><').trim();
  const phosphor = (name, style) => {
    const f = nm(`@phosphor-icons/core/assets/${style}/${style === 'regular' ? name : name + '-' + style}.svg`);
    if (!fs.existsSync(f)) return null;
    const paths = [...fs.readFileSync(f, 'utf8').matchAll(/<path d="([^"]+)"\/>/g)].map(([, d]) => `<path d="${minifyPath(d)}"/>`);
    return paths.length ? `<symbol id="i-${name}" viewBox="0 0 256 256">${paths.join('')}</symbol>` : null;
  };
  const sizes = [];
  const write = (style, syms) => {
    const svg = '<svg xmlns="http://www.w3.org/2000/svg">' + syms.join('') + '</svg>\n';
    fs.writeFileSync(path.join(dir, style + '.svg'), svg);
    sizes.push(`${style} ${(zlib.gzipSync(svg, { level: 9 }).length / 1024).toFixed(0)}`);
  };
  for (const style of ['thin', 'light', 'regular', 'bold', 'fill']) write(style, names.map(n => phosphor(n, style)).filter(Boolean));
  let sets = {};
  try { sets = JSON.parse(fs.readFileSync(path.join(root, 'resources/icons/sets.json'), 'utf8')); } catch { sets = {}; }
  const line = { lucide: n => nm(`lucide-static/icons/${n}.svg`), tabler: n => nm(`@tabler/icons/icons/outline/${n}.svg`) };
  for (const [set, file] of Object.entries(line)) {
    if (!fs.existsSync(path.dirname(file('x')))) { log(`  Symbole  Stil ${set} übersprungen (Paket fehlt)`); continue; }
    let own = 0;
    const syms = names.map(n => {
      const f = sets[set]?.[n] ? file(sets[set][n]) : null;
      if (f && fs.existsSync(f)) {
        own++;
        return `<symbol id="i-${n}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${inner(fs.readFileSync(f, 'utf8'))}</symbol>`;
      }
      return phosphor(n, 'regular');
    }).filter(Boolean);
    write(set, syms);
    sizes.push(`(${set}: ${own}/${names.length} eigene)`);
    const lic = set === 'lucide' ? nm('lucide-static/LICENSE') : nm('@tabler/icons/LICENSE');
    if (fs.existsSync(lic)) fs.copyFileSync(lic, path.join(dir, `LICENSE-${set}.txt`));
  }
  log(`  Symbole  Stile → public/assets/icons/styles/ (KB gzip): ${sizes.join(' · ')}`);
}
