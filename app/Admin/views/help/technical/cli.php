<?php /** Entwicklerhandbuch · Kommandozeile (vollständige Liste aus bin/console) */
$__cmds = [
    'Konten & Einrichtung' => [
        ['user:create <email> [admin|editor]', 'Benutzer anlegen (Passwort wird abgefragt); Rolle = Schlüssel einer Rolle'],
        ['user:password <email>', 'Passwort neu setzen – alle bestehenden Sitzungen des Kontos enden (auth_ver)'],
        ['user:2fa-reset <email>', 'Zwei-Faktor-Anmeldung eines lokalen Kontos zurücksetzen (Sitzungen enden; Netzwerk-Konten: network:user --reset-2fa)'],
        ['setup:token', 'Setup-Token für /admin/setup anzeigen'],
        ['keys:generate [--force]', 'Schlüsselpaar für verschlüsselte Eingänge erzeugen (geheimer Schlüssel wird einmal angezeigt)'],
    ],
    'Websites & Netzwerk' => [
        ['site:list', 'Websites dieser Installation'],
        ['site:create <key> <domain,…> [kit]', 'Neue Website (eigene Datenbank, Medien, Benutzer); gibt das Setup-Token aus'],
        ['site:hosts <key> [add|remove <domain>] [--landing]', 'Domains einer Website anzeigen/ändern; --landing = Domain für Landingpages (ändert nur hosts bzw. landing_hosts in config/sites/{key}.php, Sicherung .bak) – siehe Landingpages'],
        ['network:list', 'Netzwerk-Konten und Websites'],
        ['network:user <email> [--create|--disable|--enable|--reset-2fa|--password] [--name="…"]', 'Netzwerk-Konto anlegen, sperren, entsperren, 2FA zurücksetzen, Passwort setzen'],
        ['support:stats [site]', 'Support-Meldungen: offen, neu, dringend, wartend, gesamt'],
    ],
    'Kits & Erweiterungen' => [
        ['theme:list', 'Installierte Kits (Alias: kit:list)'],
        ['theme:create <name> <vorlage>', 'Neues Kit als Kopie (Präfix vorlage_* → name_*; Alias: kit:create)'],
        ['extensions:list', 'Installierte Erweiterungen: aktiv je Website, Quelle (config bzw. verwaltung)'],
        ['features:list', 'Funktionen dieser Website: an/aus und Quelle (Konfiguration, Preset, Verwaltung, Standard); „ruht“ bei fehlender Voraussetzung'],
        ['features:release [--dry-run] [--only=features|extensions]', 'In der Konfiguration festgelegte preset/features/extensions an Administration → Funktionen & Erweiterungen übergeben: Schalter setzen, Einträge aus config/sites/{key}.php (Einzel-Installation auch config.local.php) entfernen, Sicherung {datei}.{zeit}.bak, Diff – wirksamer Stand bleibt gleich'],
        ['extensions:publish', 'Öffentliche Dateien der Erweiterungen nach public/extensions kopieren'],
        ['reseed --force', 'ALLE Inhalte löschen und Startinhalte des Kits neu einspielen (Schlüssel bleiben)'],
    ],
    'Betrieb & Deploy' => [
        ['migrate [--all]', 'Datenbanken auf den Stand des Codes bringen (auch geteilte, Netzwerk- und Support-Datenbanken)'],
        ['health [--all]', 'Prüfung für Deploys (Exit-Code 1 bei Fehlern)'],
        ['maintenance on|off [--all]', 'Wartungsmodus'],
        ['cache:clear [--all]', 'Seiten-Cache leeren'],
        ['i18n:missing en [--site-texts]', 'Fehlende Übersetzungen der Oberfläche bzw. der festen Website-Texte (lt())'],
        ['tutorials:export [--out=datei.json] [--videos=ordner]', 'Tutorial-Katalog als JSON für die Produkt-Website (Schritte DE/EN, Dauer, Dateien) – siehe Tutorials & Videos'],
    ],
    'Sicherungen' => [
        ['site:backup [--out=dir] [--keep=N] [--all]', 'Datenbank + Medien sichern (storage/backups); danach nur die neuesten N Sicherungen der Website behalten'],
        ['site:restore <datei> --force', 'Sicherung einspielen (überschreibt Inhalte und Konten der Website)'],
        ['pool:backup [key|--all] [--keep=N]', 'Geteilte Medien-Pools sichern; nur die neuesten N je Pool behalten'],
        ['pool:restore <datei> --force', 'Pool-Sicherung einspielen'],
        ['shared:backup [key|--all] [--keep=N]', 'Geteilte Datentabellen sichern (Bilder: pool:backup data-…); nur die neuesten N je Tabelle behalten'],
        ['shared:restore <datei> --force', 'Sicherung einer geteilten Tabelle einspielen'],
    ],
    'Medien & Daten' => [
        ['pool:create <key> "Name"', 'Geteilten Medien-Pool anlegen'],
        ['pool:list', 'Pools und nutzende Websites'],
        ['media:thumbs [--missing|--all] [--pool=key]', 'Vorschaubilder für Videos erzeugen (nur mit ffmpeg): fehlende bzw. alle neu'],
        ['data:share <handle> --members=a,b [--see-members] [--merge]', 'Tabelle dieser Website für weitere Websites freigeben (diese Website = Eigentümer, --site=…)'],
        ['data:unshare <handle> [--all-entries]', 'Freigabe beenden – wieder eigene Tabelle des Eigentümers'],
        ['shared:list', 'Geteilte Tabellen, Eigentümer, Mitglieder, Einträge je Website'],
        ['inbox:migrate [--all]', 'Alte Online-Anfragen → Eingangs-Tabellen (idempotent, läuft sonst automatisch)'],
        ['inbox:purge [--all]', 'Aufbewahrungsfristen anwenden (Cron, täglich)'],
    ],
    'Weiterleitungen' => [
        ['redirects:import <datei.json|csv> [--dry-run] [--overwrite] [--keep-paths]', 'Weiterleitungen importieren: JSON [{"from","to"}] oder CSV quelle;ziel;code;notiz – interne Pfade mit Seite werden zu page:ID (--keep-paths: nicht)'],
        ['redirects:list [--q=text] [--limit=N]', 'Weiterleitungen mit Code, Ziel, Treffern und Herkunft'],
        ['redirects:test <pfad>', 'Wohin führt eine Adresse? (Seite, Weiterleitung, 410 oder 404)'],
        ['redirects:selftest', 'Selbsttest: Normalisieren und Vergleichen (ohne Datenbank, Exit-Code 1 bei Fehlern)'],
    ],
    'Suche & KI' => [
        ['search:index [--all] [--full] [--no-vectors] [--kb]', 'Suchindex abgleichen (Cron alle 15 min; --full nachts; --kb Wissensdatenbank)'],
        ['search:status [--all]', 'Stand des Suchindex und des KI-Anbieters'],
        ['search:query "<text>" [--lang=en]', 'Testsuche wie Besucher'],
        ['ai:test [--cap=text|embed|vision|transcribe]', 'KI-Anbieter je Fähigkeit prüfen'],
        ['ai:transcribe <medien-id> [--lang=de|auto] [--pool=key]', 'Video/Audio transkribieren → Untertitel-Entwurf'],
        ['ai:jobs [--all]', 'Wartende Medien-Aufträge (Transkription, Übersetzung) abarbeiten'],
    ],
];
?>
  <p><code>php bin/console &lt;befehl&gt;</code> – jedes Kommando wirkt auf die Website <code>--site=&lt;key&gt;</code> (Standard: <code>default</code>); <code>--all</code> führt die Befehle <code>migrate</code>, <code>health</code>, <code>maintenance</code>, <code>site:backup</code>, <code>inbox:migrate</code>, <code>inbox:purge</code>, <code>cache:clear</code> und <code>ai:jobs</code> für jede Website aus. Aktive Erweiterungen bringen eigene Befehle mit (z. B. <code>dav:password</code>). Ohne Befehl zeigt <code>php bin/console</code> diese vollständige Liste. Sicherungen rotieren: <code>--keep=N</code> (Standard <code>'backup_keep' =&gt; 14</code> in config.php/config.local.php, <code>0</code> = nichts löschen) löscht nach einer erfolgreichen Sicherung ältere Sicherungen derselben Website bzw. desselben Pools/derselben Tabelle im Zielordner.</p>
  <?php foreach ($__cmds as $__group => $__rows): ?>
  <h3><?= e($__group) ?></h3>
  <div class="doc-scroll"><table class="doc-table">
    <tr><th>Befehl</th><th>Zweck</th></tr>
    <?php foreach ($__rows as [$__c, $__d]): ?><tr><td><code><?= e($__c) ?></code></td><td><?= e($__d) ?></td></tr><?php endforeach; ?>
  </table></div>
  <?php endforeach; ?>
  <?php if (\Core\Extensions::commands()): ?>
  <h3>Befehle aktiver Erweiterungen (diese Website)</h3>
  <table class="doc-table">
    <tr><th>Befehl</th><th>Zweck</th></tr>
    <?php foreach (\Core\Extensions::commands() as $__c => $__def): ?><tr><td><code><?= e($__c) ?></code></td><td><?= e((string) ($__def[0] ?? '')) ?></td></tr><?php endforeach; ?>
  </table>
  <?php endif; ?>
