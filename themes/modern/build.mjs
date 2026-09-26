/**
 * Kit-Vendoren „modern“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   Webfonts (WOFF2, variabel)  → public/themes/modern/fonts            (latin + latin-ext; eine Datei je Schnittlage
 *                                                                       statt 3–4 fester Schnitte)
 *   @font-face je Familie        → public/themes/modern/css/font-{key}.css (Style-Editor: design.php → fonts;
 *                                  design_head() bindet nur die gewählten Familien ein – ohne externe Anfragen)
 *   TTF für App-Icons            → themes/modern/fonts                    (nur serverseitig, App-Icon-Generator)
 */
import fs from 'node:fs';
import path from 'node:path';

// Schlüssel (design.php → fonts) → Paket, Familienname, Achsen-Datei, Gewichtsbereich, Kursiv
export const FAMILIES = {
  jakarta: { pkg: '@fontsource-variable/plus-jakarta-sans', file: 'plus-jakarta-sans', axis: 'wght', family: 'Plus Jakarta Sans', weight: '200 800' },
  'space-grotesk': { pkg: '@fontsource-variable/space-grotesk', file: 'space-grotesk', axis: 'wght', family: 'Space Grotesk', weight: '300 700' },
  'inter-tight': { pkg: '@fontsource-variable/inter-tight', file: 'inter-tight', axis: 'wght', family: 'Inter Tight', weight: '100 900' },
  manrope: { pkg: '@fontsource-variable/manrope', file: 'manrope', axis: 'wght', family: 'Manrope', weight: '200 800' },
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
    for (const style of f.italic ? ['normal', 'italic'] : ['normal']) {
      for (const sub of SUBSETS) {
        const file = `${f.file}-${sub}-${f.axis}-${style}.woff2`;
        const src = path.join(base, 'files', file);
        if (!fs.existsSync(src)) continue;
        copy(src, path.join(web, file));
        const fmt = f.axis === '400' ? 'woff2' : 'woff2-variations';
        css += `@font-face{font-family:"${f.family}";font-style:${style};font-weight:${f.weight};font-display:swap;`
          + `src:url(../fonts/${file}) format("${fmt}"),url(../fonts/${file}) format("woff2");unicode-range:${ranges[sub]}}\n`;
      }
    }
    fs.writeFileSync(path.join(cssDir, `font-${key}.css`), css);
    console.log('  vendor  ' + path.relative(path.resolve(publicDir, '../../..'), path.join(cssDir, `font-${key}.css`)));
    copy(path.join(base, 'LICENSE'), path.join(web, `OFL-${key}.txt`));
  }

  const ttf = path.join(themeDir, 'fonts');
  copy(pkg('@expo-google-fonts/space-grotesk', '700Bold', 'SpaceGrotesk_700Bold.ttf'), path.join(ttf, 'SpaceGrotesk_700Bold.ttf'));
  const lic = ['LICENSE_FONT', 'OFL.txt', 'LICENSE'].map((f) => pkg('@expo-google-fonts/space-grotesk', f)).find((f) => fs.existsSync(f));
  if (lic) copy(lic, path.join(ttf, 'OFL.txt'));
}
