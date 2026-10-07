/**
 * Kit-Vendoren „modern“ – wird von tools/build.mjs aufgerufen (pnpm build).
 *
 *   TTF für App-Icons  → kits/modern/fonts   (nur serverseitig für den App-Icon-Generator, nicht öffentlich)
 *
 * Webfonts liefert das Kit nicht mehr mit: design.fonts erklärt sie mit 'fontsource' => id, der Schriften-Manager
 * (Core\Fonts) installiert sie für die Installation (public/assets/fonts/installed – Kit-Wahl, Style-Editor, fonts:sync).
 */
import fs from 'node:fs';
import path from 'node:path';

export function vendors({ copy, pkg, themeDir }) {
  const ttf = path.join(themeDir, 'fonts');
  copy(pkg('@expo-google-fonts/space-grotesk', '700Bold', 'SpaceGrotesk_700Bold.ttf'), path.join(ttf, 'SpaceGrotesk_700Bold.ttf'));
  const lic = ['LICENSE_FONT', 'OFL.txt', 'LICENSE'].map((f) => pkg('@expo-google-fonts/space-grotesk', f)).find((f) => fs.existsSync(f));
  if (lic) copy(lic, path.join(ttf, 'OFL.txt'));
}
