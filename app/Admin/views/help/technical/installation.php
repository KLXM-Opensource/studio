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
SetEnvIf Authorization "(.+)" HTTP_AUTHORIZATION=$1
# Versionierte Dateien und Bildvarianten 1 Jahr cachen (neue Datei = neuer Name bzw. neues ?v=…), übrige Medien 30 Tage
&lt;IfModule mod_headers.c&gt;
  &lt;LocationMatch "^/(media|sites/[^/]+/media|pools)/"&gt;
    Header set Cache-Control "public, max-age=2592000"
  &lt;/LocationMatch&gt;
  &lt;LocationMatch "^/(assets/|media/(cache|design|fit)/|sites/[^/]+/media/(cache|design|fit)/|pools/[^/]+/cache/)"&gt;
    Header set Cache-Control "public, max-age=31536000, immutable"
  &lt;/LocationMatch&gt;
&lt;/IfModule&gt;</code></pre>
      <b>Nur nginx</b> (Plesk ohne Apache): unter „Zusätzliche nginx-Anweisungen“ – ein leeres Apache-Feld genügt dann. Die Ordner stehen als <code>location ^~</code>: Diese Regeln haben Vorrang vor den Regex-Regeln, die Plesk für „Statische Dateien direkt durch nginx bedienen“ selbst anlegt – mit <code>location ~*</code> griffen die Cache-Regeln dort nicht. Fehlt eine Datei (z. B. eine Bildvariante, die erst beim ersten Aufruf entsteht), antwortet Studio.<pre><code># Saubere Adressen
location / { try_files $uri $uri/ /index.php$is_args$args; }
location = /robots.txt { try_files $uri /index.php$is_args$args; }
# JavaScript-Module und Untertitel: viele nginx-Installationen kennen .mjs/.vtt nicht (sonst application/octet-stream → Karte, PDF-Viewer und Untertitel gehen nicht)
location ~* \.mjs$ { types { } default_type "text/javascript; charset=utf-8"; add_header Cache-Control "public, max-age=31536000, immutable"; }
location ~* \.vtt$ { types { } default_type "text/vtt; charset=utf-8"; }

# Cache-Dauer: versionierte Dateien (?v=…), Bildvarianten (cache/) und Design-CSS (design/, fit/ – Prüfwert im Namen) 1 Jahr,
# übrige Medien 30 Tage (Ersetzen = neuer Dateiname). SVG der Mediathek direkt aufgerufen: abgeschottet
# (Apache: .htaccess legt Core\Svg::guard() selbst an)
location ^~ /assets/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
    try_files $uri /index.php$is_args$args;
    location ~* \.mjs$ { types { } default_type "text/javascript; charset=utf-8"; add_header Cache-Control "public, max-age=31536000, immutable"; }
}
location ^~ /media/ {
    add_header Cache-Control "public, max-age=2592000";
    try_files $uri /index.php$is_args$args;
    location ~* \.svg$ { add_header Content-Security-Policy "<?= e(\Core\Svg::CSP) ?>" always; add_header X-Content-Type-Options "nosniff" always; }
    location ~* \.vtt$ { types { } default_type "text/vtt; charset=utf-8"; }
    location ~* ^/media/(cache|design|fit)/ { add_header Cache-Control "public, max-age=31536000, immutable"; try_files $uri /index.php$is_args$args; }
}
location ^~ /sites/ {
    add_header Cache-Control "public, max-age=2592000";
    try_files $uri /index.php$is_args$args;
    location ~* ^/sites/[^/]+/media/.+\.svg$ { add_header Content-Security-Policy "<?= e(\Core\Svg::CSP) ?>" always; add_header X-Content-Type-Options "nosniff" always; }
    location ~* \.vtt$ { types { } default_type "text/vtt; charset=utf-8"; }
    location ~* ^/sites/[^/]+/media/(cache|design|fit)/ { add_header Cache-Control "public, max-age=31536000, immutable"; try_files $uri /index.php$is_args$args; }
}
location ^~ /pools/ {
    add_header Cache-Control "public, max-age=2592000";
    try_files $uri /index.php$is_args$args;
    location ~* \.svg$ { add_header Content-Security-Policy "<?= e(\Core\Svg::CSP) ?>" always; add_header X-Content-Type-Options "nosniff" always; }
    location ~* \.vtt$ { types { } default_type "text/vtt; charset=utf-8"; }
    location ~* ^/pools/[^/]+/cache/ { add_header Cache-Control "public, max-age=31536000, immutable"; try_files $uri /index.php$is_args$args; }
}</code></pre>      Mit Apache hinter nginx stattdessen die Apache-Zeilen oben plus <code>AddType text/javascript .mjs</code> und <code>AddType text/vtt .vtt</code>. <b>HSTS</b> sendet Studio selbst (über HTTPS im Produktivbetrieb). Schaltet man es zusätzlich in Plesk unter SSL/TLS ein, kommt der Header doppelt – dann in <code>config/config.local.php</code> <code>'hsts' =&gt; false</code> setzen (siehe <a href="#konfiguration">Konfiguration</a>). Ohne Server-Konfiguration: <code>'url_rewrite' =&gt; false</code> (Adressen dann <code>/index.php/…</code>). Kits liegen unter <code>kits/</code>, ihre gebauten Assets unter <code>public/assets/kits/</code> (siehe <a href="#deploy">Staging &amp; Deploy</a> → Ablage in <code>public/</code>).</li>
    <li><b>HTTPS</b> mit Let’s Encrypt + 301-Weiterleitung aktivieren.</li>
    <li><b>Schreibrechte</b> für den PHP-Benutzer: <code>config/</code> (beim ersten Start), <code>storage/</code>, <code>public/media</code>, <code>public/sites</code>, <code>public/pools</code>, <code>public/assets/ext</code>, <code>public/assets/fonts/installed</code>.</li>
    <li><b>Erster Aufruf</b> erzeugt Datenbank und <code>config/config.local.php</code> (mit zufälligem <code>app_key</code> und <code>setup_token</code>). Ist noch kein Kit festgelegt, spielt er <em>nichts</em> ein – Besucher sehen „Hier entsteht eine neue Website“ (503, <code>noindex</code>). Dann <code>/admin/setup</code> mit dem <code>setup_token</code> öffnen (<code>php bin/console setup:token</code> zeigt Token und Adresse) und das erste Konto anlegen – oder per SSH <code>php bin/console user:create name@example.org admin</code>.</li>
    <li><b>Willkommen-Bildschirm</b> (<code>/admin/willkommen</code>, <code>Core\Onboarding</code>): Nach dem ersten Anmelden wählt die Administration das <b>Kit</b> und ob die <b>Startinhalte</b> eingespielt werden (Musterseiten, Beispieltexte, ggf. Demo) – oder <b>ohne</b>: leere Startseite, Impressum und Datenschutz als Vorlagen, Beispiel-Angaben wie „(Demo)“ oder <code>example.com</code> bleiben leer. Erst danach entstehen die Seiten. Wer beides vorab festlegen will: <code>config/sites/default.php</code> mit <code>'theme' =&gt; 'basis'</code> und optional <code>'seed' =&gt; 'full' | 'empty' | 'ask'</code> (ohne <code>seed</code> und mit Kit: sofort mit Startinhalten wie bisher).</li>
    <li><b>Einrichtung:</b> Grundeinstellungen → Verschlüsselung (Schlüsselpaar für Eingänge; der geheime Schlüssel wird nur einmal angezeigt), E-Mail-Versand (Testmail), Website → Kanonische Adresse; danach die zentralen Angaben des Kits. Die Übersicht der Verwaltung zeigt eine Einrichtungs-Checkliste.</li>
    <li><b>Cronjobs</b> einrichten (Suchindex, Aufbewahrung, ggf. KI-Aufträge) und <code>php bin/console health</code> ausführen.</li>
  </ol>
  <div class="doc-note doc-note--info"><strong>Adresse der Verwaltung</strong><p>Optional statt <code>/admin</code> (Standard) eine eigene Adresse (<code>Core\AdminPath</code>): Grundeinstellungen → „Adresse der Verwaltung“ (Administration; bei mehreren Websites nur die Netzwerk-Administration; mit Passwort bestätigen) oder <code>php bin/console admin:path --random</code> (oder <code>admin:path &lt;adresse&gt;</code>, zurück mit <code>--reset</code>), gespeichert als <code>admin_path</code> in <code>config/config.local.php</code> für alle Websites der Installation; Vorrang hat die Umgebungsvariable <code>KLXM_ADMIN_PATH</code>. Übliche Adressen (admin, login, wp-admin, backend, cms, verwaltung …) sind gesperrt. <code>/admin</code> zeigt ohne Anmeldung „Seite nicht gefunden“ und setzt kein Cookie; angemeldet bleibt es erreichbar. Intern (Routen, Rechte, <code>url('/admin/…')</code>, Erweiterungen) ändert sich nichts. Lesezeichen und die App auf dem Homescreen nach dem Umstellen neu anlegen. Kein Ersatz für starke Passwörter und zweiten Faktor – hält aber automatische Login-Scanner fern.</p></div>
  <div class="doc-note doc-note--warn"><strong>Authorization-Header bei Apache + FPM</strong><p>Manche Setups verwerfen den Header <code>Authorization</code>. Abhilfe: die <code>SetEnvIf</code>-Zeile (s. o.) – oder in API-/MCP-Clients den Header <code>X-Api-Key: cms_…</code> verwenden, der immer ankommt.</p></div>
  <div class="doc-note doc-note--info"><strong>WebVTT-Dateien</strong><p>Untertitel werden als <code>.vtt</code> ausgeliefert. nginx und Apache kennen den Typ meist; sonst <code>AddType text/vtt .vtt</code> (Apache) bzw. <code>types { text/vtt vtt; }</code> (nginx) ergänzen.</p></div>
  <div class="doc-note doc-note--info"><strong>Projekt-Installation mit verknüpftem Kern (Entwicklung)</strong><p>Für mehrere Projekte auf einem Rechner: ein Ordner je Projekt mit eigenen <code>config/</code> (<code>config.local.php</code>, <code>sites/</code>), <code>storage/</code>, <code>public/</code> (Medien, <code>assets/kits</code>, <code>assets/ext</code>), <code>kits/</code> und <code>extensions/</code>; Kern-Code (<code>app</code>, <code>vendor</code>, <code>lang</code>, <code>lib</code>, <code>resources</code>, <code>tools</code>, <code>config/config.php</code>, <code>public/assets/{css,js,…}</code>, Kern-Kits samt gebauter Assets) als Verknüpfung auf eine Kern-Installation. Die Umgebungsvariable <code>KLXM_ROOT</code> (nur wenn <code>$KLXM_ROOT/config</code> existiert) legt <code>ROOT</code> fest – PHP löst bei <code>__DIR__</code> Verknüpfungen auf und würde sonst den Kern-Ordner nehmen. Zwei Startdateien im Projekt setzen sie: <code>public/index.php</code> (<code>putenv('KLXM_ROOT=' . dirname(__DIR__)); return require dirname(__DIR__) . '/app/../public/index.php';</code>) und <code>bin/console</code> (setzt sie und startet die Kern-Konsole; Unterprozesse erben sie). Der Entwicklungsserver liefert statische Dateien aus seinem Dokumentstamm (<code>-t public</code>). Kern-Änderungen wirken sofort in allen Projekten. Für Server weiterhin eine normale Installation (Deploy per <code>git archive</code>).</p></div>
