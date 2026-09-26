# Staging & Deploy

Code kommt per Release auf den Server, Inhalte entstehen live. Staging ist eine eigene Installation
(z. B. `staging.kunde.de`) zum Testen von Code, Kits und Erweiterungen – mit Kopie der Live-Inhalte.

```
Entwicklung (lokal) ──git push──▶ GitHub ──Actions──▶ Staging ──manuell + Freigabe──▶ Production
                                                        ▲                                  │
                                                        └──── deploy/sync-content.sh ◀─────┘  (Inhalte nur live → staging)
```

## Einmalig je Umgebung (Plesk)

1. Abonnement/Subdomain anlegen, **SSH-Zugang** für den Systembenutzer aktivieren (Web-Hosting-Zugang → „/bin/bash“).
2. **Dokumentstamm** auf `current/public` setzen (Hosting-Einstellungen).
3. PHP 8.4+ als FPM; zusätzliche Apache-Anweisungen wie in der Installationsanleitung (`FallbackResource`, Authorization-Header).
4. Lokal `deploy/targets/staging.env` bzw. `production.env` aus `example.env` anlegen (nicht versionieren).
5. Erster Deploy: `deploy/deploy.sh staging`. Dabei entstehen `shared/config/config.local.php` (Schlüssel) und die Datenbank.
6. Staging kennzeichnen – `shared/config/config.local.php` ergänzen:

```php
'environment'   => 'staging',                                   // noindex, Hinweisleiste, keine echten E-Mails
'staging_auth'  => ['user' => 'vorschau', 'pass' => '…'],        // Passwortschutz (HTTP Basic)
'mail_redirect' => 'test@agentur.de',                            // E-Mails hierhin statt an Kunden (Betreff „[STAGING]“; ohne Angabe nur protokolliert)
```

## Deploy

```bash
deploy/deploy.sh staging                 # bauen, hochladen, migrieren, prüfen, umschalten
deploy/deploy.sh production              # zusätzlich: vorher Sicherung (Websites, Pools, geteilte Tabellen)
deploy/deploy.sh production --rollback   # vorheriges Release aktivieren (nur Symlink – Datenbank und Caches bleiben)
deploy/sync-content.sh default           # Live-Inhalte der Website „default“ auf Staging holen
```

Ablauf: Build (Composer ohne Dev-Pakete, pnpm – `composer install --no-dev` läuft in der lokalen Arbeitskopie) → Upload in
`releases/<zeit>-<rev>` → gemeinsame Daten verlinken (`shared/`: Konfiguration, Websites, storage, Medien) → Sicherung
(production; `SKIP_BACKUP=1` überspringt) → `migrate` → `extensions:publish` → `health`.
Zu `shared/storage` gehören auch `storage/pools` (geteilte Medien) und `storage/shared` (geteilte Datentabellen
mehrerer Websites: `share.json` + `share.sqlite` je Tabelle). `migrate --all` legt deren Datenbanken an bzw. gleicht
Spalten additiv ab, `health --all` prüft sie; vor production-Deploys sichert `shared:backup --all` sie mit.
Nur wenn alles grün ist, wird `current` atomar umgeschaltet (sonst wird das neue Release entfernt); danach `cache:clear --all`,
optional `RELOAD_CMD` (z. B. PHP-FPM neu laden), Rauchtest auf `/health` und bei Fehler automatischer Rollback.
Es bleiben `KEEP` Releases (Standard 5) für Rollbacks.

Weitere Werte in `targets/*.env`: `PHP`, `SITES` (Standard `--all`), `SSH_OPTS`, `RSYNC_OPTS`, `CURL_OPTS`, `RELOAD_CMD`,
`SKIP_BUILD`, `SKIP_BACKUP` – siehe `targets/example.env`.

**Datenbank-Änderungen** laufen vor dem Umschalten gegen die Live-Datenbank. Schema-Änderungen im Core und in
Erweiterungen sind deshalb **additiv** (neue Tabellen/Spalten, nichts umbenennen oder löschen) – so funktioniert
auch das vorherige Release nach einem Rollback weiter.

## Inhalte holen, lokal bearbeiten, zurückspielen

Für Korrekturen an vielen Stellen oder mit Werkzeugen, die nur lokal laufen: Live-Stand holen, lokal (oder auf Staging)
bearbeiten, nur die geänderten Seiten zurückspielen – ohne Änderungen zu überschreiben, die live inzwischen gemacht wurden.

```bash
deploy/content-pull.sh production default              # Live-Inhalte + Medien holen (überschreibt die lokalen!) und Stand merken
#   … lokal in der Verwaltung bearbeiten (Entwurf oder veröffentlicht) …
deploy/content-push.sh production default --dry-run    # zeigt, welche Seiten zurückgehen und ob es Konflikte gibt
deploy/content-push.sh production default              # übernimmt als Entwurf (live prüfen, dann veröffentlichen)
deploy/content-push.sh production default --publish    # übernimmt und veröffentlicht sofort
```

- **Konfliktschutz:** Beim Holen merkt sich `content:snapshot` je Seite einen Fingerabdruck (Blöcke, Titel, SEO-Felder, im Menü).
  `content:import` übernimmt eine Seite nur, wenn sie live noch genau so aussieht und dort kein offener Entwurf liegt –
  sonst wird **nichts** übernommen. Dann neu holen und die Änderung wiederholen (oder bewusst `--force`).
- **Rückgängig:** Jede übernommene Seite erhält eine Version „Content-Sync (…)“ – über *Versionen* wiederherstellbar.
- **Grenzen:** Zuordnung über Pfad + Sprache. Neue Seiten, gelöschte Seiten, neu hochgeladene Medien und Grundeinstellungen
  gehen nicht mit (Medien-Verweise werden geprüft: fehlt ein Bild live, bricht die Übernahme ab).
- **Ohne Releases** (Installation direkt im Web-Ordner): in `targets/<ziel>.env` `APP_DIR` setzen; bei Passwort-Anmeldung
  `SSH_CMD="sshpass -e ssh"` und `SCP_CMD="sshpass -e scp"` (Passwort in der Umgebungsvariable `SSHPASS`).
  Anderer Website-Key lokal: `LOCAL_SITE=kopie deploy/content-pull.sh production default`.

## GitHub Actions

Vorlage: [`deploy/github-actions/deploy.yml.example`](github-actions/deploy.yml.example) – gedacht für das
**Repository eines Projekts** (Agentur/Kundenwebsite), nicht für das öffentliche KLXM-Studio-Repository, das nur prüft
(`.github/workflows/ci.yml`) und bei Tags `v*` das Installations-ZIP baut (`release.yml`).

1. Datei im eigenen Repository als `.github/workflows/deploy.yml` ablegen.
2. *Settings → Environments*: `staging` und `production` anlegen, bei `production` Freigabe durch eine Person
   verlangen (*Required reviewers*).
3. Secrets je Umgebung: `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_ENV` (Inhalt der .env-Datei – mindestens
   `SSH_TARGET`, `BASE`, `URL`, sonst bricht `deploy.sh` ab).

Ablauf: Push auf `main` → Staging (mit PHP-Syntaxprüfung; PHP 8.4, Node 22, pnpm 10). Production über „Run workflow“
mit Ziel `production`.

Alternative ohne CI: `deploy/deploy.sh` lokal ausführen, oder Plesk-Git mit „Zusätzliche Bereitstellungsaktionen“
(`php bin/console migrate --all && php bin/console health --all`) – dann ohne Releases/Rollback.

## Nützliche Befehle auf dem Server

```bash
php bin/console health --all              # Prüfung (Exit-Code 1 bei Fehlern)
php bin/console migrate --all             # Datenbanken auf den Stand des Codes
php bin/console maintenance on --all      # Wartungsmodus an/aus
php bin/console site:backup --all         # Sicherungen nach storage/backups (behält die neuesten 14, --keep=N)
php bin/console pool:backup --all         # geteilte Medien-Pools sichern
php bin/console shared:backup --all       # geteilte Datentabellen sichern (Bilder: pool:backup data-…)
php bin/console shared:list               # geteilte Datentabellen, Eigentümer, Mitglieder
php bin/console site:restore <datei> --force --site=<key>
```

Sicherungen landen als `{site}-JJJJMMTT-HHMMSS.tar.gz`, `pool-…` bzw. `shared-…` in `storage/backups` und werden **nicht
rotiert** – alte Dateien regelmäßig entfernen bzw. extern ablegen. Cronjobs für Suchindex, Aufbewahrungsfristen und
KI-Aufträge: Entwicklerhandbuch → „Betrieb“.

## Inhalte von Live auf Staging holen

`deploy/sync-content.sh <site>` braucht `production.env` und `staging.env` (beide per SSH; `SSH_OPTS` kommt aus
`staging.env`), sichert die Website auf production (`site:backup`, die Datei bleibt dort liegen), überträgt sie über ein
lokales Temp-Verzeichnis und spielt sie auf Staging ein (`site:restore --force` – überschreibt auch die Benutzerkonten).
Übertragen werden Datenbank und Medien einer Website; geteilte Medien-Pools und geteilte Tabellen nicht.
