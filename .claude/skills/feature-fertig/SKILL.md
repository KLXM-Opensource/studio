---
name: feature-fertig
description: Abschluss-Checkliste für eine neue oder geänderte Funktion in KLXM Studio – Standardwerte, Übersetzungen, Doku (Handbuch, Entwicklerhandbuch, CHANGELOG), Build, Selbsttests, Prüfung im Browser, Commit.
---

# Funktion fertigstellen

1. **Standard:** Schickt die Funktion Daten nach außen oder kostet sie (KI, externe Dienste)? → Schalter mit Standard „aus“
   (Grundeinstellungen `sys.*` über `AdminSettings`/`SystemSchema`, oder Funktion in `Features`).
2. **Ausgabe:** escapen (`e()`), keine Inline-Skripte/-Stile auf der Website (CSP), Rich-Text nur über den Sanitizer.
   Assets nur laden, wo sie gebraucht werden (Kit-JS < 8 KB, Startseiten-CSS < 30 KB).
3. **Texte:** Verwaltung `__()`, Website `lt()` → `php bin/console i18n:missing en` und `--site-texts` = 0
   (Sie-Form, „Kit“ statt „Theme“, „KI“/„AI“ groß).
4. **Schema:** nur additiv (`ensureColumns`, neue Tabellen); `php bin/console migrate --all`.
5. **Build:** `cd tools && pnpm run build`, gebaute Dateien in `public/` committen.
6. **Prüfen:** `php -l`, betroffene `*:selftest`; im Browser (Playwright aus `tools/node_modules`) hell und dunkel, Telefonbreite.
7. **Doku:** Handbuch (`app/Admin/views/help/manual/`, für die Redaktion), Entwicklerhandbuch (`…/technical/`),
   `CHANGELOG.md` im Abschnitt der aktuellen Version.
8. **Commit:** deutsch, `Bereich: was sich ändert`, kleine logische Commits. Push und Deploy nur auf Auftrag.
