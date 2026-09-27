/**
 * Theme-Vendoren „editorial“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   Webfonts (WOFF2)       → public/kits/editorial/fonts   latin + latin-ext; variable Schriften (eine Datei je Schnitt-Lage
 *                            für alle Stärken) bzw. statische Schnitte, jeweils aufrecht + kursiv
 *   @font-face je Familie  → public/kits/editorial/css/font-{key}.css (Style-Editor: design.php → fonts;
 *                            design_head() bindet nur die gewählten Familien ein – ohne externe Anfragen)
 *   TTF (Playfair Display) → kits/editorial/fonts  (nur serverseitig: App-Symbol, erzeugte Platzhalterbilder der Demo)
 */
import fs from 'node:fs';
import path from 'node:path';

// Schlüssel (design.php → fonts) → fontsource-Paket. var: variable Schrift (wght-Achse), sonst statische Schnitte
export const FAMILIES = {
  newsreader: { pkg: 'newsreader', family: 'Newsreader', var: true },
  fraunces: { pkg: 'fraunces', family: 'Fraunces', var: true },
  playfair: { pkg: 'playfair-display', family: 'Playfair Display', var: true },
  literata: { pkg: 'literata', family: 'Literata', var: true },
  'source-serif': { pkg: 'source-serif-4', family: 'Source Serif 4', var: true },
  'dm-serif': { pkg: 'dm-serif-display', family: 'DM Serif Display', weights: [400] },
  instrument: { pkg: 'instrument-serif', family: 'Instrument Serif', weights: [400] },
  'source-sans': { pkg: 'source-sans-3', family: 'Source Sans 3', var: true },
  'public-sans': { pkg: 'public-sans', family: 'Public Sans', var: true },
  'work-sans': { pkg: 'work-sans', family: 'Work Sans', var: true },
  franklin: { pkg: 'libre-franklin', family: 'Libre Franklin', var: true },
  'plex-sans': { pkg: 'ibm-plex-sans', family: 'IBM Plex Sans', var: true },
  inter: { pkg: 'inter', family: 'Inter', var: true },
  'plex-mono': { pkg: 'ibm-plex-mono', family: 'IBM Plex Mono', weights: [400, 500], upright: true },
  jetbrains: { pkg: 'jetbrains-mono', family: 'JetBrains Mono', var: true, upright: true },
};
const SUBSETS = ['latin', 'latin-ext'];

export function vendors({ copy, pkg, themeDir, publicDir }) {
  const web = path.join(publicDir, 'fonts');
  const cssDir = path.join(publicDir, 'css');
  fs.mkdirSync(cssDir, { recursive: true });

  for (const [key, f] of Object.entries(FAMILIES)) {
    const base = pkg((f.var ? '@fontsource-variable/' : '@fontsource/') + f.pkg);
    const ranges = JSON.parse(fs.readFileSync(path.join(base, 'unicode.json'), 'utf8'));
    const meta = JSON.parse(fs.readFileSync(path.join(base, 'metadata.json'), 'utf8'));
    const styles = f.upright ? ['normal'] : ['normal', 'italic'];
    let css = `/* ${f.family} – SIL Open Font License 1.1 (../fonts/OFL-${key}.txt) · lokal, ohne externe Anfragen */\n`;
    const faces = [];
    if (f.var) {
      const w = meta.variable?.wght ?? { min: 400, max: 700 };
      for (const style of styles) faces.push({ file: sub => `${f.pkg}-${sub}-wght-${style}.woff2`, weight: `${w.min} ${w.max}`, style });
    } else {
      for (const wt of f.weights) for (const style of styles) faces.push({ file: sub => `${f.pkg}-${sub}-${wt}-${style}.woff2`, weight: String(wt), style });
    }
    for (const face of faces) {
      for (const sub of SUBSETS) {
        const file = face.file(sub);
        const src = path.join(base, 'files', file);
        if (!fs.existsSync(src)) continue;
        copy(src, path.join(web, file));
        css += `@font-face{font-family:"${f.family}";font-style:${face.style};font-weight:${face.weight};font-display:swap;`
          + `src:url(../fonts/${file}) format("woff2");unicode-range:${ranges[sub]}}\n`;
      }
    }
    fs.writeFileSync(path.join(cssDir, `font-${key}.css`), css);
    console.log('  vendor  ' + path.relative(path.resolve(publicDir, '../../..'), path.join(cssDir, `font-${key}.css`)));
    copy(path.join(base, 'LICENSE'), path.join(web, `OFL-${key}.txt`));
  }

  const ttf = path.join(themeDir, 'fonts');
  for (const f of ['900Black/PlayfairDisplay_900Black.ttf', '400Regular_Italic/PlayfairDisplay_400Regular_Italic.ttf']) {
    copy(pkg('@expo-google-fonts/playfair-display', f), path.join(ttf, path.basename(f)));
  }
  copy(pkg('@expo-google-fonts/playfair-display/LICENSE_FONT'), path.join(ttf, 'OFL.txt'));
}
