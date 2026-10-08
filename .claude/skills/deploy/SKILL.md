---
name: deploy
description: KLXM Studio auf einen Server ausrollen (Staging/Production) mit deploy/deploy.sh – Releases, Sicherung, Migration, Health, Rollback. Nur auf ausdrücklichen Auftrag.
---

# Deploy

Nur wenn der Nutzer es ausdrücklich verlangt. „push“ heißt nur `git push` – Deploy separat bestätigen lassen.

## Vorbereitung (einmalig je Ziel)

`deploy/targets/<ziel>.env` aus `deploy/targets/example.env` (nie versionieren): `SSH_TARGET`, `BASE`, `URL`, `PHP`.
Server-Aufbau und Plesk-Einstellungen: `deploy/README.md`.

## Ablauf

1. Alles committen – ausgerollt wird der committete Stand. Vorher: `cd tools && pnpm run build` (gebaute Dateien committet),
   `php bin/console i18n:missing en` = 0, betroffene `*:selftest` grün.
2. Optional optischer Vergleich: `node tools/visual-diff.mjs vorher <URL>` (Skill `visual-check`).
3. `deploy/deploy.sh staging` bzw. `deploy/deploy.sh production` – sichert vorher, migriert, prüft `health`, schaltet
   `current` atomar um, Rauchtest, sonst automatischer Rollback.
4. Danach `node tools/visual-diff.mjs nachher <URL> && node tools/visual-diff.mjs diff`.
5. Bericht: Health-Ergebnis und Auffälligkeiten. Problem → `deploy/deploy.sh production --rollback`.

**Installation ohne Releases** (direkt im Web-Ordner): vorher Code und Datenbanken sichern (`tar` bzw.
`sqlite3 … ".backup …"` nach `storage/backups/`), dann nur den committeten Stand übertragen:
`git archive --format=tar HEAD app bin lang lib resources kits public/index.php public/assets | ssh <ziel> 'cd <ordner> && tar xf -'`,
danach `php bin/console migrate --all && php bin/console kits:publish && php bin/console cache:clear --all && php bin/console health --all`.

## Nie

- Dateilisten aus `git status`/`find` hochladen; `config/`, `storage/`, `public/media`, `public/sites` übertragen (Schlüssel, Daten).
  Der Hook `.claude/hooks/guard-bash.sh` blockiert das.
- `klxm:seed` oder andere Inhalts-Neuaufsetzer auf Servern – dort pflegt die Redaktion live.
- Neue Funktionen auf dem Server einschalten, ohne dass der Nutzer es will (neue Schalter mit Standard „aus“ ausliefern).
