<?php /** Entwicklerhandbuch · Schriften aus Google Fonts (Core\Fonts) */ ?>
  <p class="lead">Schriften aus dem Google-Fonts-Katalog werden einmal serverseitig geladen, geprüft und unter <code>public/assets/fonts/installed/</code> (Adresse <code>/assets/fonts/installed/…</code>) von der eigenen Domain ausgeliefert. Weder Besucher noch Redaktion haben Kontakt zu Google, Fontsource oder jsDelivr – keine Einwilligung nötig (vgl. LG München I, 3 O 17493/20).</p>
  <h3>Quelle &amp; Abruf</h3>
  <ul>
    <li><b>Katalog:</b> Fontsource-API <code>https://api.fontsource.org/v1/fonts</code> (≈ 2 100 Familien, davon ≈ 1 980 <code>type: google</code>; Felder <code>id, family, subsets, weights, styles, variable, category, license</code>), 1 Tag zwischengespeichert in <code>storage/cache/fonts/catalog.json</code>. Details: <code>/v1/fonts/{id}</code> (<code>unicodeRange</code> je Zeichensatz, <code>variants</code>, <code>version</code>, <code>npmVersion</code>), Achsen der variablen Fassung: <code>/v1/variable/{id}</code>.</li>
    <li><b>Dateien:</b> <code>https://cdn.jsdelivr.net/fontsource/fonts/{id}@latest/{subset}-{weight}-{style}.woff2</code>, variabel <code>…/{id}:vf@latest/{subset}-wght-{style}.woff2</code>; Lizenztext aus dem npm-Paket <code>cdn.jsdelivr.net/npm/@fontsource/{id}@{npmVersion}/LICENSE</code>.</li>
    <li><b>Ausweichweg:</b> Ist eine Datei bei jsDelivr nicht erreichbar, liest der Server die Google Fonts CSS2-API (<code>fonts.googleapis.com/css2?family=…</code> mit woff2-fähigem User-Agent) und lädt dieselbe Datei von <code>fonts.gstatic.com</code>.</li>
    <li>Alle Abrufe laufen über <code>Core\Proxy::http()</code>, Quelle <code>fontsource</code> (<code>public =&gt; false</code>, Hosts <code>api.fontsource.org</code>, <code>cdn.jsdelivr.net</code>, <code>fonts.googleapis.com</code>, <code>fonts.gstatic.com</code>) – https, keine Weiterleitungen, Größenlimit, Content-Type-Prüfung.</li>
    <li><b>Vorschau in der Verwaltung:</b> <code>GET /admin/system/fonts/preview/{id}</code> lädt den lateinischen Normalschnitt serverseitig (Cache <code>storage/cache/fonts/preview</code>, 30 Tage) und liefert ihn als <code>font/woff2</code>; <code>resources/js/_fonts.js</code> bindet ihn per <code>FontFace</code> ein (CSP ohne Inline-Styles, erst wenn die Probe sichtbar wird).</li>
  </ul>
  <h3>Ablage</h3>
  <pre><code>public/assets/fonts/installed/     (eigener Unterordner: public/assets/fonts/ enthält die Kern-Schrift Lato der Verwaltung)
  fonts.json                 Metadaten + Verwendung (Website:Kit) aller installierten Schriften
  {id}/font.css              @font-face je Schnitt/Zeichensatz: font-display: swap, unicode-range, relative url()
  {id}/{subset}-{w}-{style}.woff2   bzw. {subset}-wght-{style}.woff2 (variabel: font-weight 300 700)
  {id}/LICENSE.txt           Lizenztext (OFL-1.1, Apache-2.0 oder UFL-1.0)</code></pre>
  <p>Installationsweit, von allen Websites nutzbar. Beim Deploy (<code>deploy/deploy.sh</code>) liegt der Ordner wie <code>public/media</code> unter <code>shared/public/fonts</code> und wird im Release als <code>public/assets/fonts/installed</code> verlinkt; in Git ignoriert.</p>
  <h3>Sicherheit</h3>
  <ul>
    <li>Nur <code>system.manage</code> (oder Integratoren) und Funktion <code>fonts</code> (<code>Core\Features</code>; in den Presets <code>content</code>/<code>minimal</code> aus).</li>
    <li>IDs nur <code>[a-z0-9-]</code> (max. 64), Familiennamen bereinigt (<code>Fonts::cleanFamily</code>: Buchstaben, Ziffern, Leerzeichen, Punkt, Bindestrich); Dateinamen werden aus geprüften Teilen gebaut, nie aus Antworten übernommen; Löschen nur innerhalb von <code>public/assets/fonts/installed</code> (realpath-Prüfung).</li>
    <li>Jede Datei: woff2-Signatur <code>wOF2</code>, höchstens 2 MB; Auswahl höchstens 24 MB/120 Dateien. Nur Lizenzen <code>OFL-1.1</code>, <code>Apache-2.0</code>, <code>UFL-1.0</code> (Katalog) und ein als solcher erkannter Lizenztext – sonst wird nichts installiert.</li>
    <li>Installation in ein temporäres Verzeichnis, dann atomarer Austausch; Seiten-Cache wird geleert.</li>
  </ul>
  <h3>Style-Editor &amp; Kits</h3>
  <p><code>Design::fonts()</code> ergänzt die Schriften des Kits um alle installierten als <code>installed:{id}</code> („Name (installiert)“). <code>design_head()</code> bindet deren <code>font.css</code> ein (bei „Vorladen“ zusätzlich <code>&lt;link rel="preload" as="font"&gt;</code> für den lateinischen Hauptschnitt). Ausschalten je Kit:</p>
  <pre><code>// theme.php
'design' =&gt; ['fonts_extra' =&gt; false, 'groups' =&gt; [...], 'fonts' =&gt; [...]],</code></pre>
  <p>Überschreitet eine Schrift <?= \Core\Fonts::BUDGET_KB ?> KB (lateinische Dateien aller installierten Schnitte), zeigt der Style-Editor einen Hinweis. Beim Speichern im Style-Editor merkt sich <code>fonts.json</code>, welche Website bzw. welches Kit eine Schrift verwendet; Entfernen verlangt dann eine Bestätigung. Fehlt eine gewählte Schrift, gilt wieder der Standard des Kits. Landingpages (<code>Core\Landing</code>) sehen dieselbe Auswahl.</p>
  <h3>Kommandozeile</h3>
  <pre><code>php bin/console fonts:search grotesk [--category=serif]
php bin/console fonts:install "Space Grotesk" --variable
php bin/console fonts:install lora --weights=400,700 --subsets=latin,latin-ext [--italic] [--preload]
php bin/console fonts:list
php bin/console fonts:remove lora [--force]</code></pre>
  <h3>Lizenzen</h3>
  <p><code>Fonts::licensesSection()</code> liefert den Abschnitt „Installierte Schriften“ (Familie, Lizenz, Copyright-Zeile, Link auf <code>LICENSE.txt</code>) für <b>Hilfe › Lizenzen &amp; Danksagungen</b>; <code>THIRD-PARTY-NOTICES.md</code> verweist in Abschnitt 2.1 darauf.</p>
