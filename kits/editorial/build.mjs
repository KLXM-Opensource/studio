/**
 * Kit-Vendoren „editorial“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   TTF für App-Icons  → kits/editorial/fonts   (nur serverseitig für den App-Icon-Generator, nicht öffentlich)
 *
 * Webfonts liefert das Kit nicht mehr mit: design.fonts erklärt sie mit 'fontsource' => id, der Schriften-Manager
 * (Core\Fonts) installiert sie für die Installation (public/assets/fonts/installed – Kit-Wahl, Style-Editor, fonts:sync).
 */
import path from 'node:path';

export function vendors({ copy, pkg, themeDir }) {
  const ttf = path.join(themeDir, 'fonts');
  for (const f of ['900Black/PlayfairDisplay_900Black.ttf', '400Regular_Italic/PlayfairDisplay_400Regular_Italic.ttf']) {
    copy(pkg('@expo-google-fonts/playfair-display', f), path.join(ttf, path.basename(f)));
  }
  copy(pkg('@expo-google-fonts/playfair-display/LICENSE_FONT'), path.join(ttf, 'OFL.txt'));
}
