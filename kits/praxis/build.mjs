/**
 * Theme-Vendoren „praxis“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   Webfonts (WOFF2)  → public/assets/kits/praxis/fonts   (in css/site.css als ../fonts/… eingebunden)
 *   TTF für Icons     → kits/praxis/fonts          (nur serverseitig für den App-Icon-Generator, nicht öffentlich)
 */
import path from 'node:path';

export function vendors({ copy, pkg, themeDir, publicDir }) {
  const web = path.join(publicDir, 'fonts');
  for (const w of [300, 400, 500, 600, 700, 800]) {
    for (const sub of ['latin', 'latin-ext']) {
      const f = `hanken-grotesk-${sub}-${w}-normal.woff2`;
      copy(pkg('@fontsource/hanken-grotesk/files', f), path.join(web, f));
    }
  }
  copy(pkg('@fontsource/hanken-grotesk/LICENSE'), path.join(web, 'OFL.txt'));

  const ttf = path.join(themeDir, 'fonts');
  for (const w of ['700Bold', '800ExtraBold']) {
    copy(pkg('@expo-google-fonts/hanken-grotesk', w, `HankenGrotesk_${w}.ttf`), path.join(ttf, `HankenGrotesk_${w}.ttf`));
  }
  copy(pkg('@expo-google-fonts/hanken-grotesk/LICENSE_FONT'), path.join(ttf, 'OFL.txt'));
}
