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
  <p>Installationsweit, von allen Websites nutzbar. Beim Deploy (<code>deploy/deploy.sh</code>) liegt der Ordner wie <code>public/media</code> unter <code>shared/public/fonts</code> und wird im Release als <code>public/assets/fonts/installed</code> verlinkt; in Git ignoriert. Nach <code>migrate</code> ruft <code>deploy.sh</code> <code>fonts:sync</code> auf – ohne Netz ohne Abbruch. Der Ordner muss für den Webserver <b>und</b> den Benutzer der Kommandozeile beschreibbar sein.</p>
  <h3>Sicherheit</h3>
  <ul>
    <li>Oberfläche „Schriften“: nur <code>system.manage</code> (oder Integratoren) und Funktion <code>fonts</code> (<code>Core\Features</code>; in den Presets <code>content</code>/<code>minimal</code> aus). Kit-Schriften installiert der Core unabhängig davon; die Vorschau-Route steht zusätzlich dem Recht <code>design.edit</code> offen (Style-Editor).</li>
    <li>IDs nur <code>[a-z0-9-]</code> (max. 64), Familiennamen bereinigt (<code>Fonts::cleanFamily</code>: Buchstaben, Ziffern, Leerzeichen, Punkt, Bindestrich); Dateinamen werden aus geprüften Teilen gebaut, nie aus Antworten übernommen; Löschen nur innerhalb von <code>public/assets/fonts/installed</code> (realpath-Prüfung).</li>
    <li>Jede Datei: woff2-Signatur <code>wOF2</code>, höchstens 2 MB; Auswahl höchstens 24 MB/120 Dateien. Nur Lizenzen <code>OFL-1.1</code>, <code>Apache-2.0</code>, <code>UFL-1.0</code> (Katalog) und ein als solcher erkannter Lizenztext – sonst wird nichts installiert.</li>
    <li>Installation in ein temporäres Verzeichnis, dann atomarer Austausch; Seiten-Cache wird geleert.</li>
  </ul>
  <h3>Style-Editor &amp; Kits</h3>
  <p><code>Design::fonts()</code> ergänzt die Schriften des Kits um alle installierten als <code>installed:{id}</code> („Name (installiert)“). <code>design_head()</code> bindet deren <code>font.css</code> ein (bei „Vorladen“ zusätzlich <code>&lt;link rel="preload" as="font"&gt;</code> für den lateinischen Hauptschnitt). Ausschalten je Kit:</p>
  <pre><code>// theme.php
'design' =&gt; ['fonts_extra' =&gt; false, 'groups' =&gt; [...], 'fonts' =&gt; [...]],</code></pre>
  <p>Überschreitet eine Schrift <?= \Core\Fonts::BUDGET_KB ?> KB (lateinische Dateien aller installierten Schnitte), zeigt der Style-Editor einen Hinweis. Beim Speichern im Style-Editor merkt sich <code>fonts.json</code>, welche Website bzw. welches Kit eine Schrift verwendet; Entfernen verlangt dann eine Bestätigung. Fehlt eine gewählte Schrift, gilt wieder der Standard des Kits. Landingpages (<code>Core\Landing</code>) sehen dieselbe Auswahl.</p>
  <h3>Schriften der Kits</h3>
  <p>Kits liefern Webfonts aus dem Katalog nicht mehr mit, sondern erklären sie in <code>design.fonts</code> (<code>'fontsource' =&gt; id</code>, optional <code>variable</code>, <code>weights</code>, <code>styles</code>, <code>axis</code>, <code>subsets</code>, <code>preload</code> – Details unter <b>Kits › Kit-Schriften</b>). <code>Design::fonts()</code> ergänzt installierte Kit-Schriften um <code>href</code>; nicht installierte bleiben beim Ersatz-Stapel. Installierte Schriften, die das Kit selbst erklärt, erscheinen im Style-Editor nicht doppelt.</p>
  <ul>
    <li><code>Fonts::ensure(array $fonts, bool $dryRun = false)</code> – installiert fehlende bzw. ergänzt vorhandene (Anforderungen werden zusammengeführt: Kursive, Stärken, Zeichensätze; verschiedene Achsen → <code>full</code>), ohne Rechteprüfung (Systemaufgabe). Rückgabe je ID: <code>present</code>, <code>installed</code>, <code>updated</code>, <code>failed</code>. Die angeforderte Auswahl steht in <code>fonts.json</code> (<code>requested</code>), damit nichts doppelt geladen wird.</li>
    <li><code>Fonts::needed()</code> / <code>missing()</code> / <code>syncSite()</code> – Bedarf der aktuellen Website (aktuelle Werte, Standardwerte, Landingpages), Fehlendes, Abgleich samt Verwendung.</li>
    <li>Auslöser: <code>Design::save()</code> (gibt neu installierte bzw. fehlgeschlagene Schriften zurück), <code>Onboarding::choose()</code>, <code>Seeder</code> und Kit-Wechsel in den Grundeinstellungen über <code>Fonts::requestSync()</code> (Einstellung <code>sys.fonts_pending</code>, ausgeführt in <code>AdminController::view()</code> – nie bei Seitenaufrufen), <code>fonts:sync</code>.</li>
    <li><code>Fonts::health()</code> – Zeile für <code>bin/console health</code> und die Übersicht: „Schrift fehlt: …“ als Hinweis (blockiert keinen Deploy).</li>
  </ul>
  <h3>Kommandozeile</h3>
  <pre><code>php bin/console fonts:sync [--all] [--dry-run]       # Schriften der Kits installieren (nach jedem Deploy, deploy.sh)
php bin/console fonts:search grotesk [--category=serif]
php bin/console fonts:install "Space Grotesk" --variable
php bin/console fonts:install lora --weights=400,700 --subsets=latin,latin-ext [--italic] [--preload]
php bin/console fonts:list
php bin/console fonts:remove lora [--force]</code></pre>
  <h3>Lizenzen</h3>
  <p><code>Fonts::licensesSection()</code> liefert den Abschnitt „Installierte Schriften“ (Familie, Lizenz, Copyright-Zeile, Link auf <code>LICENSE.txt</code>) für <b>Hilfe › Lizenzen &amp; Danksagungen</b>; <code>THIRD-PARTY-NOTICES.md</code> verweist in Abschnitt 2.1 darauf.</p>
