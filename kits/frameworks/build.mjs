// SPDX-License-Identifier: MIT
/**
 * Build-Hook des Kits „frameworks“ – tools/build.mjs (cd tools && pnpm run build) ruft vendors(ctx) vor dem Minifizieren auf.
 * Pakete: kits/frameworks/package.json (installiert der Core-Build automatisch nach kits/frameworks/node_modules).
 *
 *   Tailwind CSS v4   tailwind/app.css       → public/assets/kits/frameworks/css/tailwind.css        (Preflight in @layer base)
 *                     tailwind/app-nopf.css  → public/assets/kits/frameworks/css/tailwind-nopf.css   (ohne Preflight, Vergleich)
 *                     Quellen (@source) = views/tailwind, templates, blocks, functions.php – nichts anderes wird gescannt.
 *                     Kein Play-CDN: Die Website erlaubt nur Skripte/Styles vom eigenen Server (CSP), und das Ergebnis soll
 *                     ohne Node auf dem Server laufen. Das gebaute CSS wird mit dem Kit eingecheckt.
 *   UIkit 3           node_modules/uikit/dist → public/assets/kits/frameworks/vendor/uikit/ (CSS, JS, Icons, LICENSE)
 *
 * Nur Tailwind neu bauen (schneller beim Arbeiten an den Vorlagen):  node kits/frameworks/build.mjs
 */
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const KIT = path.dirname(fileURLToPath(import.meta.url));

/** Tailwind-CLI des Kits (node_modules/.bin) – zwei Fassungen: mit und ohne Preflight */
export function tailwind(publicDir, log = console.log) {
  const bin = path.join(KIT, 'node_modules', '.bin', process.platform === 'win32' ? 'tailwindcss.cmd' : 'tailwindcss');
  if (!fs.existsSync(bin)) throw new Error('Tailwind-CLI fehlt – im Kit-Ordner „pnpm install“ ausführen (tools/build.mjs tut das automatisch).');
  fs.mkdirSync(path.join(publicDir, 'css'), { recursive: true });
  for (const [src, out] of [['app.css', 'tailwind.css'], ['app-nopf.css', 'tailwind-nopf.css']]) {
    const to = path.join(publicDir, 'css', out);
    execFileSync(bin, ['-i', path.join(KIT, 'tailwind', src), '-o', to, '--minify'], { cwd: KIT, stdio: ['ignore', 'ignore', 'pipe'] });
    log(`  tailwind ${path.relative(path.resolve(publicDir, '../../../..'), to)}  (${(fs.statSync(to).size / 1024).toFixed(1)} KB)`);
  }
}

export function vendors({ copy, pkg, publicDir }) {
  // UIkit: nur die minifizierten Dateien + Lizenz (MIT, YOOtheme GmbH) – THIRD-PARTY-NOTICES.md nennt sie
  const uk = pkg('uikit');
  const out = path.join(publicDir, 'vendor', 'uikit');
  copy(path.join(uk, 'dist/css/uikit.min.css'), path.join(out, 'uikit.min.css'));
  copy(path.join(uk, 'dist/js/uikit.min.js'), path.join(out, 'uikit.min.js'));
  copy(path.join(uk, 'dist/js/uikit-icons.min.js'), path.join(out, 'uikit-icons.min.js'));
  copy(path.join(uk, 'LICENSE.md'), path.join(out, 'LICENSE.md'));
  const v = JSON.parse(fs.readFileSync(path.join(uk, 'package.json'), 'utf8')).version;
  fs.writeFileSync(path.join(out, 'VERSION'), v + '\n');
  // Tailwind + Typography-Plugin: Lizenzen (MIT) neben das gebaute CSS
  copy(path.join(pkg('tailwindcss'), 'LICENSE'), path.join(publicDir, 'css', 'LICENSE-tailwindcss.txt'));
  copy(path.join(pkg('@tailwindcss/typography'), 'LICENSE'), path.join(publicDir, 'css', 'LICENSE-tailwindcss-typography.txt'));
  tailwind(publicDir);
}

// Direkt aufgerufen: nur Tailwind bauen
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  tailwind(path.resolve(KIT, '../../public/assets/kits', path.basename(KIT)));   // Ordnername = Kit-Name (auch nach kit:create)
}
