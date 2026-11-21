/**
 * Kit-Vendoren „NativeCSS“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   Lese-Schriften des Darstellungs-Knopfs (vendor/ncss/fonts, SIL Open Font License) → public/assets/kits/native/fonts
 *   Lizenz und Stand von NativeCSS                                                   → public/assets/kits/native/LICENSE-nativecss.txt
 *
 * CSS und JavaScript von NativeCSS bündelt tools/build.mjs über die Hüllen in assets/css und assets/js (esbuild); die
 * Schriften bleiben eigene Dateien (url("../fonts/…") in css/ncss-preferences-fonts.css), damit sie erst bei Bedarf laden.
 */
import fs from 'node:fs';
import path from 'node:path';

export function vendors({ copy, themeDir, publicDir }) {
  const src = path.join(themeDir, 'vendor/ncss');
  const walk = (dir) => fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => e.isDirectory() ? walk(path.join(dir, e.name)) : [path.join(dir, e.name)]);
  // Quelle vendor/ncss/fonts gibt es nur im Repository (.gitattributes export-ignore) – im Paket liegen die Schriften schon
  // fertig unter public/assets/kits/native/fonts und bleiben dort unverändert
  if (fs.existsSync(path.join(src, 'fonts'))) for (const f of walk(path.join(src, 'fonts'))) copy(f, path.join(publicDir, 'fonts', path.relative(path.join(src, 'fonts'), f)));
  const lic = fs.readFileSync(path.join(src, 'LICENSE.txt'), 'utf8') + '\n' + fs.readFileSync(path.join(src, 'VERSION.txt'), 'utf8');
  fs.mkdirSync(publicDir, { recursive: true });
  fs.writeFileSync(path.join(publicDir, 'LICENSE-nativecss.txt'), lic);
}
