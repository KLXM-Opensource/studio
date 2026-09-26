/**
 * Kit-Vendoren „glas“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   Webfonts (WOFF2, variabel)  → public/themes/glas/fonts              (latin + latin-ext, eine Datei je Familie und Zeichensatz)
 *   @font-face je Familie        → public/themes/glas/css/font-{key}.css (Style-Editor: design.php → fonts;
 *                                  design_head() bindet nur die gewählten Familien ein – ohne externe Anfragen)
 *   TTF für App-Icons            → themes/glas/fonts                    (nur serverseitig, App-Icon-Generator)
 */
import fs from 'node:fs';
import path from 'node:path';

// Schlüssel (design.php → fonts) → Paket, Familienname, Dateipräfix, Gewichtsbereich
export const FAMILIES = {
  outfit: { pkg: '@fontsource-variable/outfit', file: 'outfit', family: 'Outfit', weight: '100 900' },
  figtree: { pkg: '@fontsource-variable/figtree', file: 'figtree', family: 'Figtree', weight: '300 900' },
  sora: { pkg: '@fontsource-variable/sora', file: 'sora', family: 'Sora', weight: '100 800' },
  urbanist: { pkg: '@fontsource-variable/urbanist', file: 'urbanist', family: 'Urbanist', weight: '100 900' },
};
const SUBSETS = ['latin', 'latin-ext'];

export function vendors({ copy, pkg, themeDir, publicDir }) {
  const web = path.join(publicDir, 'fonts');
  const cssDir = path.join(publicDir, 'css');
  fs.mkdirSync(cssDir, { recursive: true });

  for (const [key, f] of Object.entries(FAMILIES)) {
    const base = pkg(f.pkg);
    const ranges = JSON.parse(fs.readFileSync(path.join(base, 'unicode.json'), 'utf8'));
    let css = `/* ${f.family} – SIL Open Font License 1.1 (../fonts/OFL-${key}.txt) · lokal, ohne externe Anfragen */\n`;
    for (const sub of SUBSETS) {
      const file = `${f.file}-${sub}-wght-normal.woff2`;
      const src = path.join(base, 'files', file);
      if (!fs.existsSync(src)) continue;
      copy(src, path.join(web, file));
      css += `@font-face{font-family:"${f.family}";font-style:normal;font-weight:${f.weight};font-display:swap;`
        + `src:url(../fonts/${file}) format("woff2-variations"),url(../fonts/${file}) format("woff2");unicode-range:${ranges[sub]}}\n`;
    }
    fs.writeFileSync(path.join(cssDir, `font-${key}.css`), css);
    console.log('  vendor  ' + path.relative(path.resolve(publicDir, '../../..'), path.join(cssDir, `font-${key}.css`)));
    copy(path.join(base, 'LICENSE'), path.join(web, `OFL-${key}.txt`));
  }

  const ttf = path.join(themeDir, 'fonts');
  copy(pkg('@expo-google-fonts/outfit', '600SemiBold', 'Outfit_600SemiBold.ttf'), path.join(ttf, 'Outfit_600SemiBold.ttf'));
  copy(pkg('@expo-google-fonts/outfit/LICENSE_FONT'), path.join(ttf, 'OFL.txt'));
}
