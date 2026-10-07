/**
 * Kit-Vendoren „galerie“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   TTF für App-Icons  → kits/galerie/fonts   (nur serverseitig für den App-Icon-Generator, nicht öffentlich)
 *
 * Webfonts liefert das Kit nicht mehr mit: design.fonts erklärt sie mit 'fontsource' => id, der Schriften-Manager
 * (Core\Fonts) installiert sie für die Installation (public/assets/fonts/installed – Kit-Wahl, Style-Editor, fonts:sync).
 */
import path from 'node:path';

export function vendors({ copy, pkg, themeDir }) {
  const ttf = path.join(themeDir, 'fonts');
  copy(pkg('@expo-google-fonts/inter', '700Bold', 'Inter_700Bold.ttf'), path.join(ttf, 'Inter_700Bold.ttf'));
  copy(pkg('@expo-google-fonts/inter/LICENSE_FONT'), path.join(ttf, 'OFL.txt'));
}
