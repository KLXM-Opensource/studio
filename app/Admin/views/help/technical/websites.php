<?php /** Entwicklerhandbuch · Mehrere Websites */ ?>
  <p class="lead">Eine Installation, beliebig viele Websites und Domains. Code, Kits und Updates teilen sich alle; Inhalte, Benutzer, Medien und Schlüssel sind je Website vollständig getrennt.</p>
  <table class="doc-table">
    <tr><th>Gemeinsam (einmal je Installation)</th><th>Getrennt (je Website)</th></tr>
    <tr><td><code>app/</code>, <code>vendor/</code>, <code>kits/</code>, <code>public/assets</code> (inkl. Kits, Erweiterungen, installierte Schriften), Proxy-Cache, Sitzungsdateien, Netzwerk-Konten, Medien-Pools, geteilte Tabellen, Support-Datenbank, KI-Anbieter (<code>storage/ai/config.json</code>)</td>
        <td>Datenbank (Seiten, Daten, Benutzer, Rollen, API-Tokens, Einstellungen, Anfragen), Medien, Seiten-Cache, Uploads, Spam-Log, <code>app_key</code>, <code>setup_token</code>, Session-Cookie, App-Icons, Suchindex, KI-Schalter und -Zähler, Design-Werte</td></tr>
  </table>
  <h3>Neue Website anlegen</h3>
  <pre><code>php bin/console site:create kunde www.kunde.de,kunde.de basis                  # sofort mit Startinhalten (wie bisher)
php bin/console site:create kunde www.kunde.de,kunde.de basis --content=empty  # Kit fest, ohne Startinhalte
php bin/console site:create kunde www.kunde.de,kunde.de                        # Kit und Inhalte wählt das erste Login
php bin/console site:list
php bin/console user:create chefin@kunde.de admin --site=kunde   # oder /admin/setup mit dem ausgegebenen Token</code></pre>
  <p>Netzwerk-Administratoren legen Websites auch in der Netzwerk-Übersicht an („Neue Website“ – gleicher Code wie <code>site:create</code>, zeigt die nächsten Schritte und das Setup-Token). Dort lassen sich Kit und Startinhalte vorgeben oder auf „beim ersten Anmelden“ stellen – dann fragt der Willkommen-Bildschirm der neuen Website danach (<code>Core\Onboarding</code>).</p>
  <ol class="doc-steps">
    <li><b>Plesk:</b> Domain (oder Alias) im selben Abonnement anlegen und den <b>Dokumentstamm auf <code>httpdocs/public</code></b> der Installation setzen; dieselben Apache-/nginx-Anweisungen wie bei der Hauptdomain; Let’s Encrypt für die neue Domain.</li>
    <li><b>Erster Aufruf</b> der Domain legt Datenbank und Medienordner an. Stehen Kit und Startinhalte fest, werden sie sofort eingespielt; sonst sehen Besucher „Hier entsteht eine neue Website“, bis die Administration im Willkommen-Bildschirm gewählt hat.</li>
    <li>Sobald mehrere Websites laufen, in <code>config/config.local.php</code> <code>'fallback_site' =&gt; null</code> setzen – fremde Domains, die auf den Server zeigen, erhalten dann „nicht eingerichtet“.</li>
  </ol>
  <table class="doc-table">
    <tr><th>Datei / Ordner</th><th>Inhalt</th></tr>
    <tr><td><code>config/sites/{key}.php</code></td><td><code>hosts</code>, <code>label</code>, <code>theme</code> (Vorgabe beim Erststart), optional <code>seed</code> (<code>'full'</code> | <code>'empty'</code> | <code>'ask'</code> – Startinhalte beim Erststart, siehe Installation → Willkommen-Bildschirm), optional <code>themes</code> (erlaubte Kits; Aliase <code>kit</code>/<code>kits</code>), eigene <code>app_key</code>/<code>setup_token</code>, optional <code>db</code> (z. B. eigene MySQL-Datenbank) und jeder andere Wert aus <code>config.php</code> – außer den Installationswerten <code>network_site</code>, <code>network_key</code>, <code>fallback_site</code> (nur config.php/config.local.php; in Website-Dateien wirkungslos). Nicht versionieren.</td></tr>
    <tr><td><code>storage/sites/{key}/</code></td><td>SQLite-Datenbank, Seiten-Cache, Uploads, Logs</td></tr>
    <tr><td><code>public/sites/{key}/media/</code></td><td>Medien (Adresse <code>/sites/{key}/media/…</code>)</td></tr>
    <tr><td>Website <code>default</code></td><td>nutzt die bisherigen Pfade <code>storage/</code> und <code>public/media</code> – Einzel-Installationen laufen unverändert; Domains optional in <code>config/sites/default.php</code>.</td></tr>
  </table>
  <h3>Gemeinsam genutzte Daten</h3>
  <ul>
    <li><b>Geteilte Medien-Pools</b> – zentrale Mediatheken für mehrere Websites: siehe <a href="#medien">Medien, Pools &amp; Untertitel</a>.</li>
    <li><b>Geteilte Datentabellen</b> – z. B. Termine eines Verbands und seiner Vereine: siehe <a href="#geteilt">Geteilte Datentabellen</a>.</li>
    <li><b>Support &amp; Wissensdatenbank</b> – eine Datenbank für alle Websites: siehe <a href="#support">Support</a>.</li>
    <li><b>Netzwerk-Konten</b> – zentrale Administration mit SSO: siehe <a href="#netzwerk">Netzwerk-Administration</a>.</li>
  </ul>
  <p>Im Code: <code>site()</code> liefert die aktuelle Website (<code>key</code>, <code>storage()</code>, <code>mediaDir()</code>, <code>mediaUrl()</code>, <code>allowedThemes()</code>). Anmeldungen gelten nur für die Website, auf der sie erfolgten (Sitzung ist an den Schlüssel der Website gebunden; ein kopiertes Cookie funktioniert auf keiner anderen Website). Alle CLI-Kommandos wirken auf <code>--site=key</code>; <code>cache:clear --all</code> leert alle.</p>
