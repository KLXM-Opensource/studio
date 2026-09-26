/**
 * Theme-Vendoren „fluid“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   Webfonts (WOFF2, variabel)  → public/themes/fluid/fonts            (latin + latin-ext; eine Datei je Schnittlage
 *                                                                       statt 3–4 fester Schnitte)
 *   @font-face je Familie        → public/themes/fluid/css/font-{key}.css (Style-Editor: design.php → fonts;
 *                                  design_head() bindet nur die gewählten Familien ein – ohne externe Anfragen)
 *   TTF für App-Icons            → themes/fluid/fonts                    (nur serverseitig, App-Icon-Generator)
 */
import fs from 'node:fs';
import path from 'node:path';

// Schlüssel (design.php → fonts) → Paket, Familienname, Achsen-Datei, Gewichtsbereich, Kursiv
export const FAMILIES = {
  inter: { pkg: '@fontsource-variable/inter', file: 'inter', axis: 'wght', family: 'Inter', weight: '100 900' },
  'instrument-sans': { pkg: '@fontsource-variable/instrument-sans', file: 'instrument-sans', axis: 'wght', family: 'Instrument Sans', weight: '400 700' },
  bricolage: { pkg: '@fontsource-variable/bricolage-grotesque', file: 'bricolage-grotesque', axis: 'wght', family: 'Bricolage Grotesque', weight: '200 800' },
  'dm-sans': { pkg: '@fontsource-variable/dm-sans', file: 'dm-sans', axis: 'wght', family: 'DM Sans', weight: '100 1000' },
  'space-grotesk': { pkg: '@fontsource-variable/space-grotesk', file: 'space-grotesk', axis: 'wght', family: 'Space Grotesk', weight: '300 700' },
  fraunces: { pkg: '@fontsource-variable/fraunces', file: 'fraunces', axis: 'opsz', family: 'Fraunces', weight: '100 900', italic: true },
  newsreader: { pkg: '@fontsource-variable/newsreader', file: 'newsreader', axis: 'wght', family: 'Newsreader', weight: '200 800', italic: true },
  'instrument-serif': { pkg: '@fontsource/instrument-serif', file: 'instrument-serif', axis: '400', family: 'Instrument Serif', weight: '400', italic: true },
  'jetbrains-mono': { pkg: '@fontsource-variable/jetbrains-mono', file: 'jetbrains-mono', axis: 'wght', family: 'JetBrains Mono', weight: '100 800' },
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
  copy(pkg('@expo-google-fonts/inter', '700Bold', 'Inter_700Bold.ttf'), path.join(ttf, 'Inter_700Bold.ttf'));
  copy(pkg('@expo-google-fonts/inter/LICENSE_FONT'), path.join(ttf, 'OFL.txt'));
}
