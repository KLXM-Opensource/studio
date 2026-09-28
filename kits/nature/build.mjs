/**
 * Kit-Vendoren „nature“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   Webfonts (WOFF2)             → public/assets/kits/nature/fonts              (latin + latin-ext)
 *   @font-face je Familie        → public/assets/kits/nature/css/font-{key}.css (Style-Editor: design.php → fonts;
 *                                  design_head() bindet nur die gewählten Familien ein – ohne externe Anfragen)
 *   TTF für App-Icons            → kits/nature/fonts                    (nur serverseitig, App-Icon-Generator)
 *
 * Fraunces: Achsen wght + SOFT („soft“-Datei) – die Überschriften nutzen SOFT 100 (weiche, runde Serifen);
 * Kursive (wght) für Hervorhebungen *so* in Überschriften – lädt nur, wenn eine Seite sie tatsächlich zeigt.
 */
import fs from 'node:fs';
import path from 'node:path';

// Schlüssel (design.php → fonts) → Paket, Familienname, Dateien je Schnitt ({sub} = Teilmenge)
export const FAMILIES = {
  fraunces: { pkg: '@fontsource-variable/fraunces', family: 'Fraunces', faces: [
    { file: 'fraunces-{sub}-soft-normal.woff2', style: 'normal', weight: '100 900', variable: true },
    { file: 'fraunces-{sub}-wght-italic.woff2', style: 'italic', weight: '100 900', variable: true },
  ] },
  'nunito-sans': { pkg: '@fontsource-variable/nunito-sans', family: 'Nunito Sans', faces: [
    { file: 'nunito-sans-{sub}-wght-normal.woff2', style: 'normal', weight: '200 1000', variable: true },
  ] },
  'young-serif': { pkg: '@fontsource/young-serif', family: 'Young Serif', faces: [
    { file: 'young-serif-{sub}-400-normal.woff2', style: 'normal', weight: '400', variable: false },
  ] },
  'source-sans-3': { pkg: '@fontsource-variable/source-sans-3', family: 'Source Sans 3', faces: [
    { file: 'source-sans-3-{sub}-wght-normal.woff2', style: 'normal', weight: '200 900', variable: true },
  ] },
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
    for (const face of f.faces) {
      for (const sub of SUBSETS) {
        const file = face.file.replace('{sub}', sub);
        const src = path.join(base, 'files', file);
        if (!fs.existsSync(src)) continue;
        copy(src, path.join(web, file));
        const url = face.variable ? `url(../fonts/${file}) format("woff2-variations"),url(../fonts/${file}) format("woff2")` : `url(../fonts/${file}) format("woff2")`;
        css += `@font-face{font-family:"${f.family}";font-style:${face.style};font-weight:${face.weight};font-display:swap;src:${url};unicode-range:${ranges[sub]}}\n`;
      }
    }
    fs.writeFileSync(path.join(cssDir, `font-${key}.css`), css);
    console.log('  vendor  ' + path.relative(path.resolve(publicDir, '../../..'), path.join(cssDir, `font-${key}.css`)));
    copy(path.join(base, 'LICENSE'), path.join(web, `OFL-${key}.txt`));
  }

  const ttf = path.join(themeDir, 'fonts');
  copy(pkg('@expo-google-fonts/fraunces', '600SemiBold', 'Fraunces_600SemiBold.ttf'), path.join(ttf, 'Fraunces_600SemiBold.ttf'));
  copy(pkg('@expo-google-fonts/fraunces/LICENSE_FONT'), path.join(ttf, 'OFL.txt'));
}
