// SPDX-License-Identifier: MIT
/**
 * Optionaler Build-Hook des Kits – tools/build.mjs (cd tools && pnpm run build) ruft vendors(ctx) für jedes Kit auf.
 * Das Start-Kit braucht keine Vendoren (Systemschrift, keine Bibliotheken) und tut hier nichts.
 *
 * CSS/JS baut der Core-Build ohnehin: assets/css/*.css und assets/js/*.js → public/kits/{kit}/ (esbuild, minifiziert,
 * @import wird gebündelt; Dateien mit „_“ am Anfang sind nur Bausteine).
 *
 * Beispiel: Schrift mit dem Kit ausliefern (package.json im Kit-Ordner mit "@fontsource/inter" – installiert der Build
 * automatisch), dann in theme.php → design.fonts: 'inter' => [..., 'css' => 'css/font-inter.css'].
 * Einfacher, ohne Build: php bin/console fonts:install Inter (siehe theme.php § 5).
 *
 *   import fs from 'node:fs';
 *   import path from 'node:path';
 *   export function vendors({ copy, pkg, publicDir }) {
 *     const base = pkg('@fontsource/inter');
 *     for (const w of [400, 700]) copy(path.join(base, 'files', `inter-latin-${w}-normal.woff2`), path.join(publicDir, 'fonts', `inter-${w}.woff2`));
 *     fs.mkdirSync(path.join(publicDir, 'css'), { recursive: true });
 *     fs.writeFileSync(path.join(publicDir, 'css', 'font-inter.css'), [400, 700].map(w =>
 *       `@font-face{font-family:Inter;font-weight:${w};font-display:swap;src:url(../fonts/inter-${w}.woff2) format("woff2")}`).join('\n'));
 *   }
 *   // Lizenz der Schrift (OFL) mitkopieren und in README/THIRD-PARTY-NOTICES nennen (node tools/licenses.mjs prüft).
 */
export function vendors() {}
