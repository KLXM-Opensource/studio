#!/usr/bin/env node
// SPDX-License-Identifier: MIT
/**
 * Lizenzprüfung für CI – KLXM Studio steht unter der MIT-Lizenz.
 *
 *   node tools/licenses.mjs            Prüfen: Composer (composer.lock) + npm (tools/, kits/*), Lizenzdateien in public/
 *   node tools/licenses.mjs --list     zusätzlich alle Pakete mit Version und Lizenz ausgeben
 *   node tools/licenses.mjs --json     vollständiges Ergebnis als JSON (z. B. für THIRD-PARTY-NOTICES.md)
 *   pnpm --dir tools licenses          dasselbe über package.json
 *
 * Unterschieden wird zwischen mitgelieferten Paketen (Composer ohne require-dev; npm-Pakete, die in public/ bzw.
 * die Theme-Ausgabe gebaut werden) und reinen Build-Werkzeugen (BUILD_ONLY und deren Abhängigkeiten).
 * Exit-Code 1, wenn ein mitgeliefertes Paket unter starkem Copyleft steht (GPL/AGPL/LGPL/EUPL/OSL … – das würde die
 * MIT-Weitergabe binden), ein Paket nicht frei ist (NONFREE, auch als Werkzeug) oder keine Lizenz angibt, oder eine
 * Lizenzdatei fehlt, die neben mitgelieferten Dateien liegen muss. Schwaches/dateibezogenes Copyleft (MPL, EPL, CDDL)
 * in mitgelieferten Paketen und unbekannte Kennungen erzeugen eine Warnung; Copyleft in Build-Werkzeugen nur einen
 * Hinweis (wird nicht ausgeliefert). Ausnahmen mit Begründung: ALLOW.
 * npm-Pakete werden aus node_modules/.pnpm gelesen – vorher `pnpm install` (tools/build.mjs erledigt das für Themes).
 * Nicht erfasst (Hinweise in THIRD-PARTY-NOTICES.md): Schriften/Stimmen/Daten außerhalb von Paketmanagern. Webfonts der Kits
 * liefert der Schriften-Manager zur Laufzeit (Core\Fonts, je Schrift LICENSE.txt in public/assets/fonts/installed) – nur noch
 * mitgelieferte Kit-Schriften (public/assets/kits/{kit}/fonts) werden hier auf ihre Lizenzdatei geprüft.
 */
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const args = new Set(process.argv.slice(2));
const PROJECT_LICENSE = 'MIT';

// Nicht frei – Abbruch, auch bei Build-Werkzeugen
const NONFREE = [
  [/^SSPL/i, 'SSPL'],
  [/-NC(-|$)/i, 'nicht kommerziell (NC)'],
  [/-ND(-|$)/i, 'keine Bearbeitung (ND)'],
  [/^(proprietary|UNLICENSED|SEE LICENSE IN)/i, 'proprietär / ohne freie Lizenz'],
];
// Starkes Copyleft – mitgeliefert unvereinbar mit der MIT-Weitergabe (Abbruch), als Werkzeug nur Hinweis
const STRONG = [
  [/^AGPL/i, 'AGPL (starkes Copyleft, Netzwerkklausel)'],
  [/^LGPL/i, 'LGPL (Copyleft auf Bibliotheksebene)'],
  [/^GPL/i, 'GPL (starkes Copyleft)'],
  [/^(EUPL|OSL|CPAL|RPL|Sleepycat|CC-BY-SA)/i, 'Copyleft'],
];
// Schwaches/dateibezogenes Copyleft – mitgeliefert zulässig, geänderte Dateien bleiben unter ihrer Lizenz (Warnung)
const WEAK = [
  [/^MPL/i, 'MPL (dateibezogenes Copyleft)'],
  [/^EPL/i, 'EPL (schwaches Copyleft)'],
  [/^CDDL/i, 'CDDL (dateibezogenes Copyleft)'],
];
// Bekannt unproblematisch (permissiv)
const OK = /^(MIT|MIT-0|ISC|0BSD|BSD-2-Clause|BSD-3-Clause|Apache-2\.0|OFL-1\.1|CC0-1\.0|CC-BY-3\.0|CC-BY-4\.0|Unlicense|Zlib|BlueOak-1\.0\.0|Python-2\.0|Artistic-2\.0|WTFPL)$/i;
// Reine Build-Werkzeuge (npm-Wurzeln): sie und ihre Abhängigkeiten landen nicht in der Auslieferung
const BUILD_ONLY = new Set(['esbuild', 'playwright']);
// Ausnahmen: 'paket' => Begründung (nur mit Begründung und Verweis auf THIRD-PARTY-NOTICES.md eintragen)
const ALLOW = {};

// Lizenzdateien, die neben mitgelieferten Dateien in public/ liegen müssen (nach `pnpm --dir tools build`)
const REQUIRED_FILES = [
  'public/assets/vendor/editorjs/LICENSE-editorjs.txt',
  'public/assets/vendor/editorjs/LICENSE-drag-drop.txt',
  'public/assets/vendor/editorjs/THIRD-PARTY-LICENSES.txt',
  'public/assets/vendor/maplibre/LICENSE.txt',
  'public/assets/vendor/maplibre/THIRD-PARTY-LICENSES.txt',
  'public/assets/vendor/pdfjs/LICENSE.txt',
  'public/assets/vendor/pdfjs/cmaps/LICENSE',
  'public/assets/vendor/pdfjs/iccs/LICENSE',
  'public/assets/vendor/pdfjs/standard_fonts/LICENSE_FOXIT',
  'public/assets/vendor/pdfjs/standard_fonts/LICENSE_LIBERATION',
  'public/assets/vendor/pdfjs/wasm/LICENSE_JBIG2',
  'public/assets/vendor/pdfjs/wasm/LICENSE_OPENJPEG',
  'public/assets/vendor/pdfjs/wasm/LICENSE_QCMS',
  'public/assets/vendor/pdfjs/wasm/LICENSE_QUICKJS',
  'public/assets/fonts/OFL-Lato.txt',
  'public/assets/icons/LICENSE.txt',
];

/** SPDX-Ausdruck bzw. Composer-Liste → Einstufung: ok | weak | strong | nonfree | unknown */
const RANK = { ok: 0, weak: 1, unknown: 2, strong: 3, nonfree: 4 };
function judge(expr) {
  if (!expr || !String(expr).trim()) return { level: 'nonfree', why: 'keine Lizenzangabe' };
  const s = String(expr).trim().replace(/^\((.*)\)$/, '$1');
  // OR: die günstigste Alternative gilt; AND: die strengste
  if (/\s+OR\s+/i.test(s)) return s.split(/\s+OR\s+/i).map(judge).sort((a, b) => RANK[a.level] - RANK[b.level])[0];
  if (/\s+AND\s+/i.test(s)) return s.split(/\s+AND\s+/i).map(judge).sort((a, b) => RANK[b.level] - RANK[a.level])[0];
  const id = s.replace(/[()]/g, '').replace(/\s+WITH\s+.*$/i, '');
  for (const [re, why] of NONFREE) if (re.test(id)) return { level: 'nonfree', why };
  for (const [re, why] of STRONG) if (re.test(id)) return { level: 'strong', why };
  for (const [re, why] of WEAK) if (re.test(id)) return { level: 'weak', why };
  if (OK.test(id)) return { level: 'ok' };
  return { level: 'unknown', why: `unbekannte Lizenzkennung „${id}“` };
}

// ------------------------------------------------------------------ Composer
function composer() {
  const lock = path.join(ROOT, 'composer.lock');
  if (!fs.existsSync(lock)) return [];
  const j = JSON.parse(fs.readFileSync(lock, 'utf8'));
  const out = [];
  for (const [list, dev] of [[j.packages ?? [], false], [j['packages-dev'] ?? [], true]]) {
    for (const p of list) {
      const lic = (p.license ?? []).join(' OR ');
      out.push({ eco: 'composer', project: 'composer.lock', name: p.name, version: p.version, license: lic, dev, bundled: !dev,
        url: p.homepage || p.source?.url?.replace(/\.git$/, '') || '' });
    }
  }
  // Pfad-Pakete/Ersatz im Projekt selbst (replace) sind keine Drittsoftware
  return out;
}

// ------------------------------------------------------------------ npm (pnpm)
function pnpmProjects() {
  const dirs = [path.join(ROOT, 'tools')];
  for (const root of ['kits']) {
    const themes = path.join(ROOT, root);
    for (const t of fs.existsSync(themes) ? fs.readdirSync(themes).sort() : []) {
      if (fs.existsSync(path.join(themes, t, 'package.json'))) dirs.push(path.join(themes, t));
    }
  }
  return dirs;
}

function npm(dir) {
  const rel = path.relative(ROOT, dir);
  const own = JSON.parse(fs.readFileSync(path.join(dir, 'package.json'), 'utf8'));
  const store = path.join(dir, 'node_modules', '.pnpm');
  const res = { own, packages: [], missing: !fs.existsSync(store) };
  if (res.missing) return res;
  const seen = new Set(), deps = new Map();
  for (const entry of fs.readdirSync(store)) {
    const nmDir = path.join(store, entry, 'node_modules');
    if (!fs.existsSync(nmDir)) continue;
    // Das Paket selbst ist ein echtes Verzeichnis, Abhängigkeiten sind Symlinks
    const cands = [];
    for (const d of fs.readdirSync(nmDir)) {
      const p = path.join(nmDir, d);
      if (d.startsWith('@')) { for (const s of fs.readdirSync(p)) cands.push(path.join(p, s)); } else cands.push(p);
    }
    for (const p of cands) {
      if (fs.lstatSync(p).isSymbolicLink() || !fs.existsSync(path.join(p, 'package.json'))) continue;
      const pj = JSON.parse(fs.readFileSync(path.join(p, 'package.json'), 'utf8'));
      const key = pj.name + '@' + pj.version;
      if (seen.has(key)) continue;
      seen.add(key);
      let lic = pj.license ?? (Array.isArray(pj.licenses) ? pj.licenses.map((l) => l.type ?? l).join(' OR ') : pj.licenses?.type);
      if (typeof lic === 'object' && lic) lic = lic.type;
      const repo = typeof pj.repository === 'string' ? pj.repository : pj.repository?.url;
      res.packages.push({ eco: 'npm', project: rel, name: pj.name, version: pj.version, license: lic ?? '', dev: true,
        url: pj.homepage || (repo ?? '').replace(/^git\+/, '').replace(/\.git$/, '') });
      deps.set(pj.name, [...(deps.get(pj.name) ?? []), ...Object.keys({ ...pj.dependencies, ...pj.optionalDependencies })]);
    }
  }
  // Mitgeliefert: alles, was von einer direkten Abhängigkeit außer BUILD_ONLY erreichbar ist
  const bundled = new Set();
  const stack = Object.keys({ ...own.dependencies, ...own.devDependencies }).filter((n) => !BUILD_ONLY.has(n));
  while (stack.length) {
    const n = stack.pop();
    if (bundled.has(n)) continue;
    bundled.add(n);
    stack.push(...(deps.get(n) ?? []));
  }
  for (const p of res.packages) p.bundled = bundled.has(p.name);
  res.packages.sort((a, b) => a.name.localeCompare(b.name));
  return res;
}

// ------------------------------------------------------------------ Auswertung
const problems = [], warnings = [], all = [];

const rootComposer = JSON.parse(fs.readFileSync(path.join(ROOT, 'composer.json'), 'utf8'));
if (rootComposer.license !== PROJECT_LICENSE) problems.push(`composer.json: "license" ist „${rootComposer.license}“, erwartet ${PROJECT_LICENSE}`);
if (!fs.existsSync(path.join(ROOT, 'LICENSE'))) problems.push('LICENSE fehlt');

all.push(...composer());
for (const dir of pnpmProjects()) {
  const r = npm(dir);
  const rel = path.relative(ROOT, dir);
  if (r.own.license !== PROJECT_LICENSE) problems.push(`${rel}/package.json: "license" ist „${r.own.license ?? '–'}“, erwartet ${PROJECT_LICENSE}`);
  if (r.missing) { warnings.push(`${rel}: node_modules fehlt – npm-Lizenzen nicht geprüft (pnpm install)`); continue; }
  all.push(...r.packages);
}

const notes = [];
for (const p of all) {
  const j = judge(p.license);
  p.verdict = j.level;
  const label = `${p.project}: ${p.name} ${p.version} (${p.license || 'keine Angabe'})${p.bundled ? '' : ' [Build-Werkzeug]'}`;
  if (j.level !== 'ok' && ALLOW[p.name]) { p.verdict = 'allowed'; p.note = ALLOW[p.name]; continue; }
  if (j.level === 'nonfree') problems.push(`${label} – ${j.why}`);
  else if (j.level === 'strong') (p.bundled ? problems : notes).push(`${label} – ${j.why}${p.bundled ? ': mitgeliefert, mit MIT-Weitergabe unvereinbar' : ''}`);
  else if (j.level === 'weak') (p.bundled ? warnings : notes).push(`${label} – ${j.why}`);
  else if (j.level === 'unknown') warnings.push(`${label} – ${j.why}`);
}

if (fs.existsSync(path.join(ROOT, 'public/assets/vendor'))) {
  for (const f of REQUIRED_FILES) if (!fs.existsSync(path.join(ROOT, f))) problems.push(`Lizenzdatei fehlt: ${f} (pnpm --dir tools build)`);
  // Mitgelieferte Schriften der Kits (nur, was der Schriften-Manager nicht liefern kann): jede braucht eine Lizenzdatei
  for (const root of ['public/assets/kits']) {
    const pubThemes = path.join(ROOT, root);
    for (const t of fs.existsSync(pubThemes) ? fs.readdirSync(pubThemes) : []) {
      const fonts = path.join(pubThemes, t, 'fonts');
      if (!fs.existsSync(fonts)) continue;
      const files = fs.readdirSync(fonts);
      if (files.some((f) => /\.(woff2?|ttf|otf)$/i.test(f)) && !files.some((f) => /^(OFL|LICENSE)/i.test(f))) problems.push(`${root}/${t}/fonts: Schriften ohne Lizenzdatei`);
    }
  }
} else {
  warnings.push('public/assets/vendor fehlt – Lizenzdateien nicht geprüft (pnpm --dir tools build)');
}

// ------------------------------------------------------------------ Ausgabe
if (args.has('--json')) {
  process.stdout.write(JSON.stringify({ project: PROJECT_LICENSE, packages: all, problems, warnings, notes }, null, 2) + '\n');
  process.exit(problems.length ? 1 : 0);
}
if (args.has('--list')) {
  let last = '';
  for (const p of all) {
    if (p.project !== last) { console.log(`\n${p.project}`); last = p.project; }
    console.log(`  ${p.name.padEnd(46)} ${String(p.version).padEnd(12)} ${p.license}${p.bundled ? '' : '  (Build)'}${p.verdict !== 'ok' ? '  [' + p.verdict + ']' : ''}`);
  }
  console.log('');
}
const byLicense = {};
for (const p of all) byLicense[p.license || '–'] = (byLicense[p.license || '–'] ?? 0) + 1;
console.log(`Lizenzen (${all.length} Pakete, Projekt ${PROJECT_LICENSE}): ` + Object.entries(byLicense).sort((a, b) => b[1] - a[1]).map(([l, n]) => `${l} ${n}`).join(' · '));
for (const n of notes) console.log('  Hinweis: ' + n);
for (const w of warnings) console.log('  Prüfen:  ' + w);
for (const e of problems) console.log('  FEHLER:  ' + e);
console.log(problems.length ? `${problems.length} Problem(e) – siehe oben.` : 'Lizenzprüfung bestanden.');
process.exit(problems.length ? 1 : 0);
