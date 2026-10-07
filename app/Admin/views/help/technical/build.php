<?php /** Entwicklerhandbuch · Build & Entwicklung */ ?>
  <pre><code>composer install                  # PHP-Abhängigkeiten (Server/Release: composer install --no-dev)
cd tools
pnpm install                      # Editor.js, editorjs-drag-drop, pdfjs-dist, maplibre-gl, @fontsource/lato, @phosphor-icons/core, esbuild
pnpm build                        # Vendoren + Symbole + CSS/JS minifizieren (Core, Kits, Erweiterungen)
pnpm vendor                       # nur Vendoren kopieren
pnpm watch                        # bei Änderungen neu bauen

php -S localhost:8000 -t public public/index.php   # lokal starten ('debug' =&gt; true in config.local.php)</code></pre>
  <table class="doc-table">
    <tr><th>Quelle</th><th>Ziel</th></tr>
    <tr><td><code>resources/css</code>, <code>resources/js</code></td><td><code>public/assets/css|js</code> (admin, editor, docs, search, dataform, calendar, pdfviewer …); <code>*.mjs</code> als ES-Modul, sonst IIFE; Ziel es2020/Safari 15</td></tr>
    <tr><td><code>admin.css</code></td><td>zusätzlich <code>admin.shadow.css</code> (<code>:root</code> → <code>:host</code>) für die Shadow-DOM-Oberfläche auf der Website</td></tr>
    <tr><td>Dateien mit <code>_</code>-Präfix</td><td>Module, die gebündelt werden (z. B. <code>_media.js</code>, <code>_spotlight.js</code>, <code>_ai.js</code>, <code>_spotlight.css</code>)</td></tr>
    <tr><td>npm-Pakete (Core)</td><td><code>public/assets/vendor/editorjs</code>, <code>…/pdfjs</code>, <code>…/maplibre</code>, Schrift Lato → <code>public/assets/fonts</code> (jeweils mit Lizenzdatei)</td></tr>
    <tr><td><code>resources/icons/icons.json</code></td><td><code>tools/icons.mjs</code> → <code>public/assets/icons/core.svg</code>, <code>{thema}.svg</code>, <code>icons.svg</code>, <code>icons-map.json</code>, <code>catalog.json</code>, <code>LICENSE.txt</code> (siehe <a href="#symbole">Symbole</a>)</td></tr>
    <tr><td><code>kits/{name}/assets</code></td><td><code>public/assets/kits/{name}</code> (<code>css/</code>, <code>js/</code>, <code>img/</code> 1:1) – Adresse <code>/assets/kits/{name}/…</code>. Alles, was der Build ausgibt, liegt unter <code>public/assets/</code>: ganz oben in <code>public/</code> gibt es nur <code>index.php</code>, <code>assets/</code> und die Upload-Ordner <code>media/</code>, <code>pools/</code>, <code>sites/</code> – jeder weitere Ordner würde die gleichnamige Seitenadresse sperren (<code>Core\PublicPaths</code>).</td></tr>
    <tr><td><code>kits/{name}/build.mjs</code> + <code>package.json</code></td><td>Kit-Vendoren, z. B. TTF für den Icon-Generator → <code>kits/{name}/fonts</code>; WOFF2 → <code>public/assets/kits/{name}/fonts</code> nur für Schriften, die der Schriften-Manager nicht liefern kann (Webfonts aus dem Google-Fonts-Katalog erklärt das Kit in <code>design.fonts</code> mit <code>'fontsource'</code>). Pakete werden bei Bedarf automatisch installiert (<code>node_modules</code> nicht hochladen).</td></tr>
    <tr><td>Kit-Pakete (<code>storage/kits/{name}</code>, Composer)</td><td><code>public/assets/kits/{name}</code> – kein Build auf dem Server: <code>php bin/console kits:publish</code> kopiert den fertigen Ordner <code>public/</code> des Pakets</td></tr>
    <tr><td><code>extensions/{name}/assets</code></td><td><code>public/assets/ext/{name}</code> (ohne Node auf dem Server: <code>php bin/console extensions:publish</code> kopiert <code>extensions/{name}/public</code>)</td></tr>
    <tr><td>Installierte Schriften</td><td>kein Build: <code>Core\Fonts</code> schreibt zur Laufzeit nach <code>public/assets/fonts/installed/{id}</code> (eigener Unterordner neben Lato, in Git ignoriert) – auch die Schriften der Kits (<code>php bin/console fonts:sync</code>)</td></tr>
  </table>
  <h3>Budgets</h3>
  <p>Frontend-JavaScript des Kits &lt; 8 KB, CSS der Startseite &lt; 30 KB (minifiziert, ohne gzip); Zusatzblöcke und Kern-Styles (<code>blocks.css</code>, <code>data.css</code>, <code>calendar.css</code>, <code>dataform.css</code>, <code>search.css</code>) laden nur bei Bedarf. Stand dieses Builds: praxis <code>site.js</code> ≈ 6,4 KB (Formulare in <code>form.js</code>, Mobilmenü-Stile und Karten-Modul erst bei Bedarf) / <code>site.css</code> ≈ 28,3 KB (Suche in <code>hsearch.css</code>), basis <code>site.js</code> ≈ 4,1 KB / <code>site.css</code> ≈ 27,5 KB (+ Navigation 0,9–4,8 KB und Schrift – je nach Navigation knapp über 30 KB). Kern-Skripte für Besucher: <code>search.js</code> ≈ 2,8 KB (lädt erst beim ersten Fokus), <code>map.mjs</code> ≈ 2 KB (MapLibre erst in Sichtweite bzw. im Zwei-Klick-Modus nach Klick), <code>media.mjs</code> ≈ 3,6 KB (Galerie/Lightbox). Die Verwaltung (<code>admin.js</code>, <code>admin.css</code>) lädt nur für Angemeldete.</p>
  <p>Prüfungen: <code>php -l</code> (CI), <code>php bin/console i18n:missing en</code> (Oberfläche) und <code>--site-texts</code> (feste Website-Texte), <code>php kits/basis/tools/contrast.php</code> (Kontrast aller Design-Vorlagen).</p>
