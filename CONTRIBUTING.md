# Mitwirken an KLXM Studio

Danke für Ihr Interesse! Fehlerberichte, Verbesserungsvorschläge und Pull Requests sind willkommen.

## Fehler und Wünsche

- **Issues:** <https://github.com/klxm/studio/issues> – mit Version (`CMS_VERSION` in `app/bootstrap.php`), PHP-Version,
  Schritten zum Nachvollziehen und erwartetem bzw. tatsächlichem Verhalten.
- **Sicherheitslücken** bitte nicht öffentlich melden, sondern wie in [SECURITY.md](SECURITY.md) beschrieben.

## Pull Requests

1. Forken, Branch anlegen, Änderung möglichst klein und in sich geschlossen halten.
2. Lokal einrichten: `composer install`; nur wenn Sie CSS/JS-Quellen (`resources/`, `themes/*/assets`,
   `extensions/*/assets`) ändern: `cd tools && pnpm install && pnpm run build` – die gebauten Dateien in `public/`
   gehören mit in den Commit.
3. Vor dem Einreichen prüfen (dasselbe prüft die CI):
   - `php -l` auf geänderte PHP-Dateien,
   - `php bin/console i18n:missing en` meldet „0 von … Texten fehlen“ – neue Texte der Verwaltung in `lang/en.php` übersetzen,
   - `node tools/licenses.mjs` besteht – neue Abhängigkeiten nur mit permissiver Lizenz (keine GPL/AGPL/LGPL in
     ausgelieferten Paketen), Eintrag in `THIRD-PARTY-NOTICES.md`.
4. Schema-Änderungen an Datenbanken nur **additiv** (neue Tabellen/Spalten) – Rollbacks müssen möglich bleiben.
5. Keine echten Kundendaten, Zugangsdaten oder Schlüssel in Code, Tests, Beispielen oder Screenshots – nur erfundene
   Beispiele („Musterstadt“, „Erika Musterfrau“, `example.org`).

Code-Stil: wie im umgebenden Code (PHP ≥ 8.4.1, kein Framework), Kommentare und Texte der
Oberfläche auf Deutsch; UI-Texte über `__()`/`t()` bzw. `lt()` für feste Website-Texte.

## Lizenz der Beiträge

KLXM Studio steht unter der [MIT-Lizenz](LICENSE). Mit einem Pull Request erklären Sie, dass Sie den Beitrag
selbst erstellt haben bzw. weitergeben dürfen, und stellen ihn unter dieselbe MIT-Lizenz („inbound = outbound“).
Ein Contributor License Agreement oder ein DCO-Sign-off (`Signed-off-by`) ist nicht erforderlich.

---

**English:** Contributions are welcome – issues and pull requests in English are fine. Please run `composer install`,
rebuild `public/` via `cd tools && pnpm run build` only if you touch CSS/JS sources (commit the built files), keep
`php bin/console i18n:missing en` at 0 and `node tools/licenses.mjs` green. By submitting a pull request you agree
that your contribution is licensed under the project's MIT License; no CLA or DCO sign-off is required. Report
security issues privately as described in [SECURITY.md](SECURITY.md).
