<?php /** Entwicklerhandbuch · Betrieb: Cronjobs, Sicherungen, Updates, Logs */ ?>
  <h3>Cronjobs</h3>
  <p>Das CMS läuft ohne Cron, einige Aufgaben erledigt es dann aber nur „nebenbei“ bei Aufrufen. Für den Produktivbetrieb empfohlen (Pfade an die Installation anpassen, Plesk: <b>Geplante Aufgaben</b>, PHP-CLI der richtigen Version):</p>
  <pre><code># Suchindex abgleichen (nur geänderte Dokumente) und nachts vollständig neu aufbauen
*/15 * * * *  cd /pfad/zur/installation &amp;&amp; php bin/console search:index --all
30 3 * * *    cd /pfad/zur/installation &amp;&amp; php bin/console search:index --all --full

# Aufbewahrungsfristen: erledigte Anfragen und alte Protokolleinträge löschen
15 4 * * *    cd /pfad/zur/installation &amp;&amp; php bin/console inbox:purge --all

# KI-Aufträge (Transkription, Untertitel-Übersetzung) – Rückfall, falls die Verwaltung keine Hintergrundprozesse starten darf
*/5 * * * *   cd /pfad/zur/installation &amp;&amp; php bin/console ai:jobs --all

# Sicherungen – danach nur die neuesten 14 je Website/Pool/Tabelle behalten (--keep=N, Standard config 'backup_keep')
0 2 * * *     cd /pfad/zur/installation &amp;&amp; php bin/console site:backup --all --keep=14 &amp;&amp; php bin/console pool:backup --all --keep=14 &amp;&amp; php bin/console shared:backup --all --keep=14</code></pre>
  <p>Ohne Cron: Der Suchindex gleicht nach jeder Änderung und bei der ersten Suche selbst ab; <code>inbox:purge</code> läuft bei etwa 1 % der Verwaltungsaufrufe (höchstens alle 6 Stunden) – auf Websites, in die sich niemand einloggt, also gar nicht. Rate-Limit-Einträge räumen sich selbst auf.</p>
  <h3 id="sicherung">Sicherungen</h3>
  <ul>
    <li><code>site:backup [--all] [--out=dir]</code> packt Datenbank (SQLite per <code>VACUUM INTO</code>, MySQL per <code>mysqldump</code>), Medien und bei weiteren Websites <code>config/sites/{key}.php</code> nach <code>storage/backups/{site}-JJJJMMTT-HHMMSS.tar.gz</code>; <code>pool:backup</code> und <code>shared:backup</code> sichern geteilte Medien und Datentabellen (<code>pool-…</code>, <code>shared-…</code>). Einspielen mit <code>site:restore</code>, <code>pool:restore</code>, <code>shared:restore</code> (jeweils <code>&lt;datei&gt; --force</code>).</li>
    <li><b>Rotation:</b> Nach einer erfolgreichen Sicherung löschen alle drei Befehle ältere Sicherungen derselben Website bzw. desselben Pools/derselben Tabelle im Zielordner und behalten die neuesten <code>--keep=N</code> (Standard <code>'backup_keep' =&gt; 14</code> aus config.php/config.local.php, bei <code>site:backup</code> je Website überschreibbar; <code>0</code> = nichts löschen). Gelöschte Dateien werden auf STDERR gemeldet – die letzte Zeile auf STDOUT bleibt der Pfad der neuen Sicherung.</li>
    <li>Die Netzwerk-Übersicht zeigt je Website die letzte Sicherung, warnt nach 7 Tagen ohne Sicherung (Produktion) und startet „Sicherung jetzt“ als eigenen PHP-Prozess (<code>php_cli</code>, höchstens 3 je 10 Minuten).</li>
    <li>Nicht in den tar-Sicherungen enthalten und separat zu sichern: <code>config/config.local.php</code> (Schlüssel der Hauptwebsite, <code>network_key</code>), <code>storage/support/</code> (zentrale Support-Datenbank und Bildschirmfotos), <code>storage/ai/config.json</code>. Suchindizes (<code>{storage}/search</code>) sind abgeleitet und lassen sich mit <code>search:index --full</code> neu aufbauen.</li>
    <li>Ein Plesk-Backup des Abonnements erfasst alles; zusätzlich die tar-Sicherungen außerhalb des Servers aufbewahren.</li>
  </ul>
  <h3 id="herausloesen">Website herauslösen – eigene Instanz aus einer Multi-Site (<code>site:extract</code>)</h3>
  <p>Soll eine Website einer Multi-Site (z. B. eine von mehreren Kundenwebsites) künftig allein laufen – auf einem eigenen Server oder in einem eigenen Ordner –, erzeugt ein Befehl eine vollständige, sofort lauffähige Kopie. Das Original bleibt unverändert; in der Kopie ist die gewählte Website die einzige (Website „default“).</p>
  <pre><code>php bin/console site:extract neo                         # → storage/exports/neo-&lt;zeit&gt;/
php bin/console site:extract neo --out=/pfad/neo --archive  # eigener Ordner + neo.tar.gz zum Hochladen
php bin/console site:extract neo --all-kits                 # alle Kits mitnehmen (sonst nur das der Website)</code></pre>
  <table class="doc-table">
    <tr><th>Kommt mit</th><th>Wie</th></tr>
    <tr><td>Code</td><td>app, bin, lang, lib, resources, deploy, vendor, Doku; tools ohne <code>node_modules</code> und Arbeitsordner</td></tr>
    <tr><td>Kit und Erweiterungen</td><td>das Kit der Website (bzw. ihre erlaubten Kits) und nur die aktiven Erweiterungen – Verweise (Symlinks) werden als echte Ordner kopiert</td></tr>
    <tr><td>Daten</td><td>Datenbank (SQLite konsistent per <code>VACUUM INTO</code>, auch im laufenden Betrieb; MySQL als Dump <code>storage/database/import.sql</code>), Medien, Suchindex, Medien-Pools der Website (auch geschützte)</td></tr>
    <tr><td>Konfiguration</td><td><code>config/config.local.php</code> aus Installation und Website – mit dem <b>eigenen <code>app_key</code></b> der Website (sonst wären verschlüsselte Einstellungen wie das SMTP-Passwort unlesbar), ohne Domains der Multi-Site, Erweiterungen fest eingetragen, neues Setup-Token</td></tr>
  </table>
  <ul>
    <li><b>Nicht übernommen</b> (der Befehl meldet es): geteilte Datentabellen – vorher <code>data:unshare</code> im Eigentümer –, Netzwerk-Konten, zentrale Support-Daten, Sitzungen, Cache und Protokolle. Passkeys gelten weiter, solange die Domain gleich bleibt.</li>
    <li>Nach dem Kopieren prüft der Befehl die neue Installation (<code>health</code>) und legt <code>EIGENE-INSTANZ.md</code> mit allen Schritten ab: hochladen (Dokumentstamm <code>public</code>), Cron einrichten, über eine Testdomain prüfen, DNS umstellen – erst dann im Original die Website entfernen.</li>
    <li>MySQL: Die Kopie zeigt zunächst auf dieselbe Datenbank wie das Original – vor dem Start eine neue anlegen, den Dump einspielen und <code>db</code> in <code>config/config.local.php</code> anpassen.</li>
  </ul>

  <h3>Updates</h3>
  <ul>
    <li>Code ersetzen (<code>app/</code>, <code>vendor/</code>, <code>resources/</code>, <code>public/assets/</code>, <code>bin/</code>, Kits) – am besten per <a href="#deploy">Deploy-Skript</a>. Danach <code>php bin/console migrate --all</code> und <code>health --all</code>; Website-Datenbanken migrieren sonst beim nächsten Aufruf selbst, geteilte, Netzwerk- und Support-Datenbanken nur über <code>migrate</code>.</li>
    <li>Kits und Erweiterungen melden ihre Mindest-Version des Cores (<code>requires</code>); inkompatible zeigt die Systeminfo bzw. <code>health</code> an. Aktuelle Version: <?= e(CMS_VERSION) ?>.</li>
  </ul>
  <h3>Schreibrechte, Logs, Datenschutz</h3>
  <ul>
    <li><b>Schreibrechte:</b> <code>storage/</code> (inkl. <code>sessions</code>, <code>cache</code>, <code>logs</code>, <code>backups</code>, <code>uploads</code>), <code>public/media</code>, <code>public/sites</code>, <code>public/pools</code>, <code>public/assets/ext</code> (<code>extensions:publish</code>), <code>public/assets/fonts/installed</code> (Schriften).</li>
    <li><b>Logs:</b> <code>storage/logs/php-error.log</code>, <code>{storage}/logs/spam.log</code> (je Website), <code>storage/logs/ai-jobs.log</code>; Protokolle in der Verwaltung: Anfragen → Protokoll, Netzwerk-Protokoll, <?= e($aiBrand) ?> → Verlauf/Eingereicht.</li>
    <li><b>Datenschutz:</b> Aufbewahrungsfrist je Eingang prüfen, API-Tokens mit Ablaufdatum vergeben und ungenutzte widerrufen, Datenschutzerklärung um genutzte externe Dienste (Videos, externer KI-Anbieter) ergänzen.</li>
  </ul>
