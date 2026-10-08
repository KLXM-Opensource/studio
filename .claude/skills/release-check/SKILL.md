---
name: release-check
description: Release-Prüfung für KLXM Studio vor einem Tag/GitHub-Release (vX.Y.Z) – Build, PHP-Lint, alle Selbsttests, i18n, Lizenzen, Sicherheit des öffentlichen Repos, Doku, Release-Notes. Tag und Release nur nach Zustimmung.
---

# Release-Prüfung

Version in `app/bootstrap.php` (`CMS_VERSION`), Änderungen in `CHANGELOG.md`. Bei Tags `v*` baut
`.github/workflows/release.yml` das Installations-ZIP.

1. `cd tools && pnpm run build` – danach ist `git status` sauber (gebaute Dateien committet).
2. PHP-Syntax: `find app bin lib kits extensions -name '*.php' -print0 | xargs -0 -n1 -P8 php -l | grep -v '^No syntax'` → leer.
3. Alle Selbsttests: `for t in $(php bin/console list | grep -oE '[a-z-]+:selftest' | sort -u); do php bin/console $t | tail -1; done`
   → nirgends fehlgeschlagene Prüfungen.
4. `php bin/console i18n:missing en` und `php bin/console i18n:missing en --site-texts` → 0; `health --all`, `kit:check --all` ohne Fehler.
5. `composer audit`; `cd tools && pnpm run licenses` → `THIRD-PARTY-NOTICES.md` aktuell, nur permissive Lizenzen.
6. Öffentliches Repo: keine Geheimnisse (`git grep -nE 'BEGIN (RSA|OPENSSH|PRIVATE)'`, Schlüssel/Tokens in Konfig-Beispielen),
   keine Kundendaten, keine internen Kits oder Erweiterungen (AGENTS.md).
7. Doku auf Stand: Handbuch (`app/Admin/views/help/manual/`), Entwicklerhandbuch (`…/technical/`), README,
   Installationsanleitung, optionale Server-Programme (ffmpeg, poppler-utils).
8. Release-Notes aus dem CHANGELOG-Abschnitt: kurze Bereiche, Hinweise zum Update (Migrationen, neue Abhängigkeiten).
9. **Erst nach Zustimmung:** `git tag -a vX.Y.Z -m "KLXM Studio X.Y.Z"`, `git push origin vX.Y.Z`,
   `gh release create vX.Y.Z --notes-file <notes.md>`. Nie force-pushen, nie Tags verschieben.
