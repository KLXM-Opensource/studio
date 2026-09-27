<?php /** Entwicklerhandbuch · Installation & Anforderungen */ ?>
  <h3>Anforderungen</h3>
  <table class="doc-table">
    <tr><th>Bereich</th><th>Voraussetzung</th></tr>
    <tr><td>PHP</td><td>≥ 8.4.1 als FPM (8.5 wird unterstützt). Erweiterungen: <code>pdo_sqlite</code> (SQLite ≥ 3.35, auch für den Suchindex), <code>sodium</code>, <code>gd</code> (WebP, optional AVIF), <code>mbstring</code>, <code>dom</code>, <code>fileinfo</code>, <code>intl</code> (Datumsangaben im Kalender), <code>phar</code> + <code>zlib</code> (Sicherungen). Optional: <code>curl</code>, <code>exif</code> (automatisches Drehen von Fotos), <code>pdo_mysql</code> für MySQL/MariaDB.</td></tr>
    <tr><td>PHP-Funktionen</td><td><code>proc_open</code> für „Sicherung jetzt“ in der Netzwerk-Übersicht, KI-Aufträge im Hintergrund und Transkription (Plesk → PHP-Einstellungen → <code>disable_functions</code>); <code>open_basedir</code> muss die Installation und – falls genutzt – Programmpfade zulassen.</td></tr>
    <tr><td>Datenbank</td><td>SQLite (Standard, eine Datei je Website) oder MySQL/MariaDB je Website (<code>'db'</code>).</td></tr>
    <tr><td>SQLite-Version</td><td>PHP nutzt die SQLite-Bibliothek des Betriebssystems – auch bei Plesk-PHP. Nötig sind ≥ 3.35 (Suche, Loupe nutzt <code>RETURNING</code>) bzw. ≥ 3.27 (<code>site:backup</code>, <code>VACUUM INTO</code>); <code>php bin/console health</code> zeigt die Version. Stand der Distributionen: Debian 12 → 3.40, Debian 13 → 3.46, Ubuntu 22.04 → 3.37, Ubuntu 24.04 → 3.45 (alle geeignet); <b>AlmaLinux/Rocky/RHEL 9 → 3.34 und 8 → 3.26 sind zu alt</b> – dort eine neuere SQLite-Bibliothek bereitstellen oder einen Debian-/Ubuntu-Server wählen.</td></tr>
    <tr><td>Webserver</td><td>Apache oder nginx mit Front-Controller; HTTPS (Pflicht für Anmeldung, Netzwerk-SSO, CalDAV).</td></tr>
    <tr><td>Entwicklung/Build</td><td>Composer 2, Node 22 + pnpm 10 (nur lokal bzw. in der CI – der Server braucht weder Node noch pnpm).</td></tr>
    <tr><td>Optional</td><td><b>Ollama</b> oder ein OpenAI-kompatibler/EU-Anbieter für <?= e($aiBrand) ?> und semantische Suche; <b>ffmpeg</b> + <b>whisper.cpp</b> (<code>whisper-cli</code>) für lokale Transkription; Cron für Suchindex, KI-Aufträge und Aufbewahrungsfristen (siehe <a href="#betrieb">Betrieb</a>).</td></tr>
  </table>
  <h3>Installation auf Plesk</h3>
  <ol class="doc-steps">
    <li><b>Code holen:</b> entweder das Installationspaket <code>klxm-studio-&lt;version&gt;.zip</code> von <a href="https://github.com/KLXM-Opensource/studio/releases">GitHub Releases</a> (mit <code>vendor/</code> und gebauten Assets, Prüfsumme <code>.sha256</code>) – oder <code>git clone https://github.com/KLXM-Opensource/studio.git</code>, dann <code>composer install --no-dev</code>. Die gebauten Assets liegen versioniert in <code>public/</code>; <code>cd tools &amp;&amp; pnpm install &amp;&amp; pnpm build</code> ist nur nach Änderungen an CSS/JS-Quellen nötig.</li>
    <li><b>Hochladen:</b> Inhalt des Projektordners nach <code>httpdocs/</code> (ohne <code>tools/node_modules</code>, <code>kits/*/node_modules</code>, <code>config/config.local.php</code>, <code>config/sites/</code>, <code>storage/</code>-Inhalte, <code>public/media</code>, <code>public/sites</code>, <code>public/pools</code>). Für Releases mit Rollback: <a href="#deploy">Staging &amp; Deploy</a>.</li>
    <li><b>Document Root:</b> Hosting-Einstellungen → <code>httpdocs/public</code> (bei Deploy-Releases <code>current/public</code>).</li>
    <li><b>PHP:</b> 8.4.1 oder neuer als FPM mit den Erweiterungen oben; <code>upload_max_filesize</code> ≥ 2 MB (Uploads laufen in 1-MB-Stücken), <code>display_errors = Off</code>.</li>
    <li><b>Saubere URLs</b> (Apache &amp; nginx Settings → zusätzliche Apache-Anweisungen, HTTP und HTTPS):
      <pre><code>FallbackResource /index.php
# Authorization-Header an PHP-FPM durchreichen (für API/MCP):
SetEnvIf Authorization "(.+)" HTTP_AUTHORIZATION=$1</code></pre>
      <b>Nur nginx</b> (Plesk ohne Apache): unter „Zusätzliche nginx-Anweisungen“ – ein leeres Apache-Feld genügt dann:<pre><code># Saubere Adressen
location / { try_files $uri $uri/ /index.php$is_args$args; }
# JavaScript-Module und Untertitel: viele nginx-Installationen kennen .mjs/.vtt nicht (sonst application/octet-stream → Karte, PDF-Viewer und Untertitel gehen nicht)
location ~* \.mjs$ { types { } default_type "text/javascript; charset=utf-8"; expires 1y; add_header Cache-Control "public, immutable"; }
location ~* \.vtt$ { types { } default_type "text/vtt; charset=utf-8"; }
# Versionierte statische Dateien lange cachen
location ~* ^/(assets|kits|extensions)/.+\.(css|js|woff2|svg|png|webp|avif|jpg)$ { expires 1y; add_header Cache-Control "public, immutable"; }
# Alte Kit-Adressen /themes/… (vor kits/): an PHP geben, public/index.php leitet mit 301 auf /kits/… um
location ^~ /themes/ { try_files $uri /index.php$is_args$args; }
location ^~ /media/cache/ { expires 1y; add_header Cache-Control "public, immutable"; }
# SVG der Mediathek direkt aufgerufen: abschotten (Apache: .htaccess legt Core\Svg::guard() selbst an)
location ~* ^/(media|sites/[^/]+/media|pools)/.+\.svg$ { add_header Content-Security-Policy "<?= e(\Core\Svg::CSP) ?>" always; add_header X-Content-Type-Options "nosniff" always; }</code></pre>      Mit Apache hinter nginx stattdessen die Apache-Zeilen oben plus <code>AddType text/javascript .mjs</code> und <code>AddType text/vtt .vtt</code>. HSTS in Plesk unter SSL/TLS einschalten (eigene <code>add_header</code> in den Blöcken überdecken sonst dort gesetzte Kopfzeilen). Ohne Server-Konfiguration: <code>'url_rewrite' =&gt; false</code> (Adressen dann <code>/index.php/…</code>). Kits liegen unter <code>kits/</code> bzw. <code>public/kits/</code>; alte Adressen <code>/themes/…</code> leitet der Kern um (Apache: <code>FallbackResource</code> genügt) – Umstellung bestehender Installationen: <a href="#deploy">Staging &amp; Deploy</a> → <code>deploy/migrate-kits.sh</code>.</li>
    <li><b>HTTPS</b> mit Let’s Encrypt + 301-Weiterleitung aktivieren.</li>
    <li><b>Schreibrechte</b> für den PHP-Benutzer: <code>config/</code> (beim ersten Start), <code>storage/</code>, <code>public/media</code>, <code>public/sites</code>, <code>public/pools</code>, <code>public/extensions</code>.</li>
    <li><b>Kit vorher festlegen:</b> <code>config/sites/default.php</code> mit <code>hosts</code> und <code>theme</code> anlegen (siehe <a href="#websites">Websites</a>), <em>bevor</em> die Website zum ersten Mal aufgerufen wird – sonst spielt der Erststart die Startinhalte des Standard-Kits ein.</li>
    <li><b>Erster Aufruf</b> erzeugt Datenbank und <code>config/config.local.php</code> (mit zufälligem <code>app_key</code> und <code>setup_token</code>) und spielt die Startinhalte des Kits ein. Dann <code>/admin/setup</code> mit dem <code>setup_token</code> aus dieser Datei öffnen und das erste Konto anlegen – oder per SSH <code>php bin/console user:create name@example.org admin</code>.</li>
    <li><b>Einrichtung:</b> Grundeinstellungen → Verschlüsselung (Schlüsselpaar für Eingänge; der geheime Schlüssel wird nur einmal angezeigt), E-Mail-Versand (Testmail), Website → Kanonische Adresse; danach die zentralen Angaben des Kits. Die Übersicht der Verwaltung zeigt eine Einrichtungs-Checkliste.</li>
    <li><b>Cronjobs</b> einrichten (Suchindex, Aufbewahrung, ggf. KI-Aufträge) und <code>php bin/console health</code> ausführen.</li>
  </ol>
  <div class="doc-note doc-note--warn"><strong>Authorization-Header bei Apache + FPM</strong><p>Manche Setups verwerfen den Header <code>Authorization</code>. Abhilfe: die <code>SetEnvIf</code>-Zeile (s. o.) – oder in API-/MCP-Clients den Header <code>X-Api-Key: cms_…</code> verwenden, der immer ankommt.</p></div>
  <div class="doc-note doc-note--info"><strong>WebVTT-Dateien</strong><p>Untertitel werden als <code>.vtt</code> ausgeliefert. nginx und Apache kennen den Typ meist; sonst <code>AddType text/vtt .vtt</code> (Apache) bzw. <code>types { text/vtt vtt; }</code> (nginx) ergänzen.</p></div>
