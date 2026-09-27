<?php /** Entwicklerhandbuch · Konfiguration */ ?>
  <p>Drei Ebenen, die spätere gewinnt: <code>config/config.php</code> (Standardwerte, versioniert) → <code>config/config.local.php</code> (Installation und Hauptwebsite, Geheimnisse, nicht versionieren) → <code>config/sites/{key}.php</code> (je weitere Website, nicht versionieren). Laufzeit-Einstellungen (E-Mail, Spamschutz, Wartungsmodus, Sprachen, Suche, KI-Schalter, Schlüssel) liegen in der Datenbank der Website und werden unter <b>Grundeinstellungen</b> gepflegt.</p>
  <table class="doc-table">
    <tr><th>Schlüssel (config.php)</th><th>Bedeutung</th></tr>
    <tr><td><code>app_key</code></td><td>HMAC-Schlüssel der Website (Formular-Tokens, IP-Hashes, Verschlüsselung der 2FA-Geheimnisse und des SMTP-Passworts). Automatisch erzeugt; je Website eigener Wert.</td></tr>
    <tr><td><code>setup_token</code></td><td>Einmal-Token für <code>/admin/setup</code> (<code>php bin/console setup:token</code>).</td></tr>
    <tr><td><code>debug</code></td><td>Fehlerausgabe, Seiten-Cache aus. Nur lokal.</td></tr>
    <tr><td><code>timezone</code></td><td>Zeitzone der Website (Standard <code>Europe/Berlin</code>) – Kalender, „jetzt geöffnet“, Zeitstempel.</td></tr>
    <tr><td><code>base_url</code></td><td>Feste Basis-URL (alternativ Grundeinstellungen → Website → Kanonische Adresse).</td></tr>
    <tr><td><code>url_rewrite</code></td><td><code>true</code> = <code>/impressum</code>, <code>false</code> = <code>/index.php/impressum</code>.</td></tr>
    <tr><td><code>theme</code></td><td>Kit-Vorgabe, falls keines gewählt ist. Der Core enthält keine Kit-Namen.</td></tr>
    <tr><td><code>fallback_site</code></td><td>Website für Domains ohne eigenen Eintrag (Standard <code>default</code>; <code>null</code> = unbekannte Domains abweisen). Nur in config.php/config.local.php.</td></tr>
    <tr><td><code>network_site</code></td><td>Netzwerk-Website (Standard <code>default</code>): Heimat der Netzwerk-Konten und von <code>/admin/network</code>, Verwaltung geteilter Medien, Support-Team, Liste „Websites dieser Installation“ in der Systeminfo. Nur config.php/config.local.php – ein Eintrag in <code>config/sites/{key}.php</code> wird ignoriert (einzige Quelle: <code>Network::siteKey()</code>), eine Website kann sich also nicht selbst zur Netzwerk-Website erklären.</td></tr>
    <tr><td><code>backup_keep</code></td><td>Anzahl Sicherungen, die <code>site:backup</code>, <code>pool:backup</code> und <code>shared:backup</code> je Website/Pool/Tabelle behalten (Standard 14, <code>0</code> = nichts löschen; Aufruf: <code>--keep=N</code>). Für Pools und geteilte Tabellen gilt der Wert aus config.php/config.local.php.</td></tr>
    <tr><td><code>db</code></td><td><code>driver</code> <code>sqlite</code> (Standard, <code>path</code>) oder <code>mysql</code> mit host/port/name/user/pass/charset.</td></tr>
    <tr><td><code>session</code></td><td>Cookie-Name (<code>cms_sess</code>, weitere Websites <code>cms_sess_{key}</code>) und Leerlauf-Lebensdauer (8 h).</td></tr>
    <tr><td><code>page_cache</code>, <code>revisions</code></td><td>Seiten-Cache an/aus; Anzahl gespeicherter Versionen je Seite (20).</td></tr>
    <tr><td><code>media</code></td><td><code>max_upload_mb</code> (50), <code>sizes</code> (480, 800, 1200, 1600, 2400 px), <code>quality</code> (80).</td></tr>
  </table>
  <table class="doc-table">
    <tr><th>Weitere Schlüssel (config.local.php bzw. config/sites/{key}.php)</th><th>Bedeutung</th></tr>
    <tr><td><code>hosts</code>, <code>label</code>, <code>theme</code>, <code>themes</code></td><td>Nur Website-Dateien: Domains, Bezeichnung, Kit beim Erststart, erlaubte Kits (gleichwertig: <code>kit</code>, <code>kits</code>).</td></tr>
    <tr><td><code>environment</code></td><td><code>production</code> (Standard) | <code>staging</code> | <code>development</code> – siehe <a href="#deploy">Staging</a>.</td></tr>
    <tr><td><code>mail_redirect</code>, <code>staging_auth</code></td><td>Außerhalb der Produktion: E-Mails an diese Adresse(n) statt an Empfänger (Betreff „[STAGING]“; ohne Angabe nur protokolliert); HTTP-Basic-Schutz <code>['user' =&gt; …, 'pass' =&gt; …]</code>.</td></tr>
    <tr><td><code>mail_dump</code></td><td>Nur für lokale Tests: E-Mails nicht versenden, sondern als <code>.eml</code> (und bei HTML-Mails als <code>.html</code>-Vorschau) ablegen – <code>true</code> = <code>{storage}/mail</code> der Website oder ein Ordner. <code>Core\Mailer::$delivered</code> ist dann <code>false</code> (Einladungen zeigen den Link an).</td></tr>
    <tr><td><code>invite_days</code></td><td>Gültigkeit von Einladungen in Tagen (Standard 7, 1–60) – <code>Core\Invites</code>, Benutzer &amp; Rollen → Person einladen, <code>user:invite</code>. Tabelle <code>user_invites</code> (Token nur als SHA-256, einmalig); E-Mail-Vorlage im Kit überschreibbar: <code>templates/mail/invitation.php</code> und <code>invitation.txt.php</code>.</td></tr>
    <tr><td><code>preset</code>, <code>features</code>, <code>blocks</code>, <code>extensions</code>, <code>integrators</code></td><td>Funktionsumfang je Website – siehe <a href="#funktionen">Funktionsumfang &amp; Erweiterungen</a>.</td></tr>
    <tr><td><code>media_pools</code></td><td>Geteilte Medien-Pools, die die Website nutzt, z. B. <code>['marke']</code>.</td></tr>
    <tr><td><code>ai</code>, <code>ai_brand</code></td><td>KI-Anbieter (hat Vorrang vor der Oberfläche) und Menüname des KI-Bereichs (Standard „KLXM Ai“) – siehe <a href="#ki"><?= e($aiBrand) ?></a>.</td></tr>
    <tr><td><code>support_notify</code></td><td>Zusätzliche Empfänger für neue Support-Meldungen.</td></tr>
    <tr><td><code>currency</code></td><td>Währung in strukturierten Daten (Standard <code>EUR</code>).</td></tr>
    <tr><td><code>network_key</code>, <code>php_cli</code>, <code>network_2fa</code></td><td>Nur config.local.php (Installation): Schlüssel für Netzwerk-SSO (automatisch angelegt), Pfad zum PHP-CLI-Binary für Hintergrundprozesse, <code>network_2fa =&gt; false</code> schaltet die 2FA-Pflicht für Netzwerk-Konten für lokale Tests ab (wirkt nur auf localhost oder außerhalb der Produktion).</td></tr>
  </table>
