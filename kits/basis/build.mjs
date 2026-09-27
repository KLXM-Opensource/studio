/**
 * Theme-Vendoren „basis“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   Webfonts (WOFF2)       → public/kits/basis/fonts            (latin + latin-ext, 400/600/700)
 *   @font-face je Familie  → public/kits/basis/css/font-{key}.css (Style-Editor: theme.php → design.fonts;
 *                            design_head() bindet nur die gewählten Familien ein – ohne externe Anfragen)
 *   TTF für Icons          → kits/basis/fonts                    (nur serverseitig für den App-Icon-Generator)
 */
import fs from 'node:fs';
import path from 'node:path';

// Schlüssel (theme.php → design.fonts) → fontsource-Paket
export const FAMILIES = {
  inter: { pkg: 'inter', family: 'Inter' },
  manrope: { pkg: 'manrope', family: 'Manrope' },
  plex: { pkg: 'ibm-plex-sans', family: 'IBM Plex Sans' },
  'source-serif': { pkg: 'source-serif-4', family: 'Source Serif 4' },
  lora: { pkg: 'lora', family: 'Lora' },
  fraunces: { pkg: 'fraunces', family: 'Fraunces' },
};
const WEIGHTS = [400, 600, 700];
const SUBSETS = ['latin', 'latin-ext'];

export function vendors({ copy, pkg, themeDir, publicDir }) {
  const web = path.join(publicDir, 'fonts');
  const cssDir = path.join(publicDir, 'css');
  fs.mkdirSync(cssDir, { recursive: true });

  for (const [key, f] of Object.entries(FAMILIES)) {
    const base = pkg('@fontsource/' + f.pkg);
    const ranges = JSON.parse(fs.readFileSync(path.join(base, 'unicode.json'), 'utf8'));
    let css = `/* ${f.family} – SIL Open Font License 1.1 (../fonts/OFL-${key}.txt) · lokal, ohne externe Anfragen */\n`;
    const faces = WEIGHTS.map(w => [w, 'normal']);   // Kursiv wird vom Browser schräg gestellt (spart Ladezeit)
    for (const [w, style] of faces) {
      for (const sub of SUBSETS) {
        const file = `${f.pkg}-${sub}-${w}-${style}.woff2`;
        const src = path.join(base, 'files', file);
        if (!fs.existsSync(src)) continue;
        copy(src, path.join(web, file));
        css += `@font-face{font-family:"${f.family}";font-style:${style};font-weight:${w};font-display:swap;`
          + `src:url(../fonts/${file}) format("woff2");unicode-range:${ranges[sub]}}\n`;
      }
    }
    fs.writeFileSync(path.join(cssDir, `font-${key}.css`), css);
    console.log('  vendor  ' + path.relative(path.resolve(publicDir, '../../..'), path.join(cssDir, `font-${key}.css`)));
    copy(path.join(base, 'LICENSE'), path.join(web, `OFL-${key}.txt`));
  }

  const ttf = path.join(themeDir, 'fonts');
  copy(pkg('@expo-google-fonts/inter', '700Bold', 'Inter_700Bold.ttf'), path.join(ttf, 'Inter_700Bold.ttf'));
  copy(pkg('@expo-google-fonts/inter/LICENSE_FONT'), path.join(ttf, 'OFL.txt'));
}
