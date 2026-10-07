<?php /** Entwicklerhandbuch · Kommandozeile (vollständige Liste aus bin/console) */
$__cmds = [
    'Konten & Einrichtung' => [
        ['user:create <email> [admin|editor]', 'Benutzer anlegen (Passwort wird abgefragt); Rolle = Schlüssel einer Rolle'],
        ['user:password <email>', 'Passwort neu setzen – alle bestehenden Sitzungen des Kontos enden (auth_ver), Hinweis-E-Mail an das Konto'],
        ['user:2fa-reset <email>', 'Zwei-Faktor-Anmeldung eines lokalen Kontos zurücksetzen (Sitzungen enden; Netzwerk-Konten: network:user --reset-2fa)'],
        ['user:email <alt> <neu>', 'E-Mail-Adresse eines Kontos direkt ändern – ohne Bestätigung, Hinweis-E-Mail an beide Adressen, Sitzungen enden (auth_ver), offene Änderungen verfallen. Netzwerk-Konten nur auf der Netzwerk-Website (Schatten-Konten werden nachgezogen)'],
        ['account:selftest', 'Selbsttest der Anmeldedaten und von „Passwort vergessen“: Tokens, Ablauf, einmalig, Abbrechen, vergebene/unbekannte Adresse (keine Rückschlüsse), auth_ver, 2FA bleibt Pflicht – in einer Transaktion, zurückgerollt (Exit-Code 1 bei Fehlern)'],
        ['user:invite <email> [rolle] [--name="…"] [--lang=de|en]', 'Person einladen: E-Mail mit Link (einmalig, invite_days Tage gültig); ohne zugestellte E-Mail wird der Link ausgegeben. Rolle nie „network“'],
        ['invites:selftest', 'Selbsttest der Einladungen (Token, Hash, Rollenprüfung; Netzwerk-Admins einladen: nur Netzwerk-Website und Netzwerk-Konten, Liste, Annehmen, Ablauf – zurückgerollt; Exit-Code 1 bei Fehlern)'],
        ['setup:token', 'Setup-Token für /admin/setup anzeigen'],
        ['keys:generate [--force]', 'Schlüsselpaar für verschlüsselte Eingänge erzeugen (geheimer Schlüssel wird einmal angezeigt)'],
    ],
    'Websites & Netzwerk' => [
        ['site:list', 'Websites dieser Installation'],
        ['site:create <key> <domain,…> [kit] [--content=ask|full|empty]', 'Neue Website (eigene Datenbank, Medien, Benutzer); gibt das Setup-Token aus. Ohne Kit oder mit --content=ask wählt das erste Login Kit und Startinhalte (Willkommen-Bildschirm); mit Kit ohne --content sofort mit Startinhalten'],
        ['site:hosts <key> [add|remove <domain>] [--landing]', 'Domains einer Website anzeigen/ändern; --landing = Domain für Landingpages (ändert nur hosts bzw. landing_hosts in config/sites/{key}.php, Sicherung .bak) – siehe Landingpages'],
        ['network:list', 'Netzwerk-Konten und Websites'],
        ['network:user <email> [--invite|--create|--disable|--enable|--reset-2fa|--password] [--name="…"] [--lang=de|en]', 'Netzwerk-Admin einladen (E-Mail mit Link, 7 Tage, 2FA beim Annehmen; ohne Versand wird der Link angezeigt) bzw. Netzwerk-Konto anlegen, sperren, entsperren, 2FA zurücksetzen, Passwort setzen'],
        ['support:stats [site]', 'Support-Meldungen: offen, neu, dringend, wartend, gesamt'],
    ],
    'Kits & Erweiterungen' => [
        ['theme:list', 'Installierte Kits mit Herkunft (lokal, upload, composer), Version, nutzenden Websites und verdeckten Kits gleichen Namens (Alias: kit:list)'],
        ['kit:install <datei.zip|ordner> [--force]', 'Kit-Paket prüfen und installieren (storage/kits/{name}, Assets nach public/assets/kits/{name}); --force ersetzt ein hochgeladenes Kit gleichen Namens – ein Kit enthält PHP-Code'],
        ['kit:remove <name>', 'Hochgeladenes Kit entfernen – nicht, solange eine Website es nutzt; mitgelieferte und Composer-Kits nie'],
        ['kits:publish [name]', 'Fertige Assets der Kit-Pakete (Upload, Composer) aus ihrem Ordner public/ nach public/assets/kits/{name} kopieren (nach composer install und bei jedem Deploy)'],
        ['theme:create <name> <vorlage>', 'Neues Kit als Kopie (Präfix vorlage_* → name_*; Alias: kit:create)'],
        ['extensions:list', 'Installierte Erweiterungen: aktiv je Website, Quelle (config bzw. verwaltung); Verwaltungsrouten aktiver Erweiterungen – Altform ohne Recht und benannte Ausnahmen (csrf, public)'],
        ['extensions:selftest | db:selftest', 'Selbsttest der Schnittstellen für Erweiterungen (Verwaltungsseiten, Werkzeuge, Ereignisse, Verwaltungsrouten, Tabellen, Slots) | Selbsttest Core\\Db\\Table in einer Wegwerf-Datenbank (Exit-Code 1 bei Fehlern)'],
        ['format:selftest', 'Selbsttest Core\\Format: Datum, relative Angaben, Zahlen, Beträge, Größen, Dauer, Auszug (DE/EN) und gleiche Ausgaben wie die früheren Helfer'],
        ['features:list', 'Funktionen dieser Website: an/aus und Quelle (Konfiguration, Preset, Verwaltung, Standard); „ruht“ bei fehlender Voraussetzung'],
        ['features:release [--dry-run] [--only=features|extensions]', 'In der Konfiguration festgelegte preset/features/extensions an Einrichtung › System › Funktionen & Erweiterungen übergeben: Schalter setzen, Einträge aus config/sites/{key}.php (Einzel-Installation auch config.local.php) entfernen, Sicherung {datei}.{zeit}.bak, Diff – wirksamer Stand bleibt gleich'],
        ['extensions:publish', 'Öffentliche Dateien der Erweiterungen nach public/assets/ext kopieren'],
        ['guide:list | guide:selftest', 'Hinweise zu diesem Projekt (Kit + Website, guide/*.md) mit Herkunft und Bezug auflisten | Selbsttest des Einlesens'],
        ['notes:convert [--dry-run] | notes:selftest', 'Alte Marker ([bitte ergänzen: …], [Platzhalter], „NEU (bitte prüfen)“) in Seiten und Einträgen in Redaktionsnotizen [# … #] umstellen | Selbsttest der Notizen (Entfernen, Hinweis, Umstellung, Suchindex)'],
        ['kit:check [--kit=name|--all] [--strict] [-v]', 'Überschreibungen eines Kits prüfen: verwaiste Block-Vorlagen, Kern-Blöcke mit geändertem Vertrag (Angaben, die der Kern nicht mehr kennt; -v: Felder, die nur der Kern liest), Kern-Fragmente mit neuem Original, fehlende Rollen des Kit-Vertrags (--kit-*); --strict: Exit-Code 1 bei Hinweisen'],
        ['fragments:list [--all] [--accept]', 'Kern-Fragmente: Herkunft je Fragment (Projekt, Kit, Kern) für das aktive Kit bzw. alle; überschriebene mit geändertem Original; --accept markiert geänderte Originale als geprüft'],
        ['docs:assets [--kit=name|--kit-dir=pfad] [--kit-only] [--out[=datei|ordner]] [--update] [--json] | docs:selftest', 'CSS-&-JS-Referenz aus den Quellen (Variablen mit Vorgaben, conditional_css → Block → Dateien, Ereignisse, data-*, Bewegung je Datei) als Markdown: stdout, --out ohne Wert storage/docs/; --update erneuert den Anhang von kits/{name}/docs/css-js.md (Kapitel CSS & JS) | Selbsttest der Parser'],
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
        ['site:extract <key> [--out=ordner] [--archive] [--all-kits]', 'Website als eigenständige Installation herauslösen: Kopie mit Code, Kit, aktiven Erweiterungen, Datenbank, Medien, Pools und eigenem app_key; prüft die Kopie mit health und legt EIGENE-INSTANZ.md an. Original bleibt unverändert (Betrieb → „Website herauslösen“)'],
        ['pool:backup [key|--all] [--keep=N]', 'Geteilte Medien-Pools sichern; nur die neuesten N je Pool behalten'],
        ['pool:restore <datei> --force', 'Pool-Sicherung einspielen'],
        ['shared:backup [key|--all] [--keep=N]', 'Geteilte Datentabellen sichern (Bilder: pool:backup data-…); nur die neuesten N je Tabelle behalten'],
        ['shared:restore <datei> --force', 'Sicherung einer geteilten Tabelle einspielen'],
    ],
    'Medien & Daten' => [
        ['pool:create <key> "Name"', 'Geteilten Medien-Pool anlegen'],
        ['pool:list', 'Pools und nutzende Websites'],
        ['media:thumbs [--missing|--all] [--pool=key]', 'Vorschaubilder für Videos erzeugen (nur mit ffmpeg): fehlende bzw. alle neu'],
        ['media:selftest', 'Selbsttest der Bildbearbeitung (Drehen, einbeschriebenes Rechteck, Entzerren, Format, Reihenfolge, GD)'],
        ['vcard:selftest', 'Selbsttest der Visitenkarten (Core\\VCard): Aufbau vCard 3.0, Maskierung, Faltung nach 75 Oktetts, CRLF, UTF-8, Bild (PNG/JPEG, kein SVG) – dazu die Karte der Website und je eine Personen-Karte (Exit-Code 1 bei Fehlern)'],
        ['svg:selftest [--dump=ordner] [-v]', 'Selbsttest der SVG-Bereinigung (Core\\Svg): Schadcode-Proben und Exporte aus Illustrator, Inkscape, Figma'],
        ['data:share <handle> --members=a,b [--see-members] [--merge]', 'Tabelle dieser Website für weitere Websites freigeben (diese Website = Eigentümer, --site=…)'],
        ['data:unshare <handle> [--all-entries]', 'Freigabe beenden – wieder eigene Tabelle des Eigentümers'],
        ['data:template <vorlage> [--handle=…] [--slug=…] [--form=…] [--with-detail-page] [--with-list-page] [--dry-run]', 'Tabelle aus Vorlage anlegen bzw. ergänzen (wiederholbar), z. B. jobs = Stellenangebote mit Eingang „Bewerbungen“ (Kapitel „Stellenangebote & Google for Jobs“)'],
        ['jobs:from-page <seite> [--title=…] [--slug=…] [--employment=…] [--dry-run]', 'Alte Stellenseite → Eintrag der Stellen-Tabelle; Formular, Links und 301 werden umgestellt (wiederholbar)'],
        ['jobs:selftest', 'Selbsttest Stellenangebote: Vorlage, JSON-LD nach Google-Vorgaben, Ablauf, Gehalt, Remote; prüft alle laufenden Stellen'],
        ['data:selftest [--roundtrip]', 'Selbsttest Tabellen-Definition: Rundlauf aller Tabellen und „Felder bearbeiten“ im Seiten-Editor (vorübergehende Tabellen); --roundtrip: nur Rundlauf, ändert nichts'],
        ['shared:list', 'Geteilte Tabellen, Eigentümer, Mitglieder, Einträge je Website'],
        ['inbox:migrate [--all]', 'Alte Online-Anfragen → Eingangs-Tabellen (idempotent, läuft sonst automatisch)'],
        ['inbox:purge [--all]', 'Aufbewahrungsfristen anwenden (Cron, täglich)'],
    ],
    'Weiterleitungen' => [
        ['redirects:import <datei.json|csv> [--dry-run] [--overwrite] [--keep-paths]', 'Weiterleitungen importieren: JSON [{"from","to"}] oder CSV quelle;ziel;code;notiz – interne Pfade mit Seite werden zu page:ID (--keep-paths: nicht)'],
        ['redirects:list [--q=text] [--limit=N]', 'Weiterleitungen mit Code, Ziel, Treffern und Herkunft'],
        ['redirects:test <pfad>', 'Wohin führt eine Adresse? (Seite, Weiterleitung, 410 oder 404)'],
        ['redirects:selftest', 'Selbsttest: Normalisieren und Vergleichen (ohne Datenbank, Exit-Code 1 bei Fehlern)'],
        ['notfound:create [--lang=en] [--publish]', 'Seite „Nicht gefunden (404)“ mit den Startinhalten des Kits anlegen (ohne --publish als Entwurf)'],
        ['notfound:selftest', 'Selbsttest der 404-Seite: Anlegen, Sichtbarkeit, eigene Adresse, Vorschläge (Transaktion, wird zurückgerollt)'],
    ],
    'Glossar' => [
        ['glossary:install [--publish] [--enable] [--dry-run]', 'Glossar einrichten: Tabelle „glossar“, Detailseiten /glossar/…, Übersicht /glossar (ohne --publish als Entwurf), --enable schaltet die Funktion ein'],
        ['glossary:import <datei.csv> [--overwrite] [--dry-run] | glossary:export [--out=datei.csv]', 'Begriffe aus CSV übernehmen (neue als Entwurf) bzw. ausgeben'],
        ['glossary:check', 'Hinweise: doppelte Varianten, Überschneidungen, fehlende oder zu lange Kurz-Erklärungen'],
        ['glossary:selftest [--bench]', 'Selbsttest der Markierung (Wortgrenzen, Abkürzungen, Umlaute, Ausnahmen, Escaping, keine Doppel-Markierung); --bench: Laufzeit einer großen Seite'],
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
