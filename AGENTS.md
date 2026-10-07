# AGENTS.md – Regeln für Beiträge (Menschen und KI)

Kurzfassung für alle, die an KLXM Studio arbeiten. Ausführlich: [CONTRIBUTING.md](CONTRIBUTING.md) und das
Entwicklerhandbuch (`/admin/hilfe/technik`).

## Öffentliches Repository

- **Keine Geheimnisse, keine Kundendaten:** keine Zugangsdaten, Schlüssel, Tokens, echten Namen, Adressen oder Inhalte von
  Kunden – auch nicht in Tests, Beispielen, Screenshots oder Commit-Texten. Beispiele erfunden („Musterstadt“,
  „Erika Musterfrau“, `example.org`). Vor jedem Commit den Diff darauf prüfen.
- `config/config.local.php`, `config/sites/*.php`, `storage/`, `public/media/` gehören nie ins Repository.
- Lizenz MIT; neue Abhängigkeiten nur mit permissiver Lizenz (`node tools/licenses.mjs`, `THIRD-PARTY-NOTICES.md`).

## Erweiterungen und Kits

- **Jede Erweiterung lebt in einem eigenen Repository** und wird per Composer installiert (Typ `klxm-studio-extension`).
  Interne oder kundenspezifische Erweiterungen kommen nie in den Core – auch nicht als Beispiel oder Erwähnung.
- **Nur über die Integrationspunkte:** Erweiterungen greifen ausschließlich über die Methoden von `Core\Extension` ein
  (Entwicklerhandbuch → Erweiterungen → „Integrationspunkte und Regeln“): Routen mit Recht, Verwaltungsseiten mit Art,
  die sechs Slots (`pageList`, `pagePanel`, `tableActions`, `mediaPanel`, `dashboard`, `account`), typisierte Ereignisse
  (`Core\Events`), Tabellen mit `Core\Db\Table`. Keine Core-Dateien ändern, keine parallelen APIs. Fehlt eine Stelle:
  Vorschlag für einen neuen Slot im Core – mit Doku und Selbsttest.
- Formatieren mit `Core\Format` / `fmt()` statt eigener Helfer.
- Live-Aktualisierung für Besucher nur über `Core\Live` (Kanäle `ext:{name}:…`, `Live::touch()`, `Live::attrs()`) –
  keine eigenen Streams oder Polling-Schleifen.
- Kits überschreiben Kern-Blöcke und -Fragmente nur bewusst; `php bin/console kit:check --all` meldet Verwaistes und Veraltetes.

## Begriffe und Sprache

- Für Nutzer heißt es **„Kit“** (nicht „Theme“; Ordner `kits/`; technisch bleiben Namen wie `Core\Theme`, `theme.php`, Konfiguration `theme`).
- **„KI“** bzw. **„AI“** immer in Großbuchstaben; die KI heißt „KLXM AI“.
- Verwaltung und Handbuch in der **Sie-Form**, Kommentare im Code auf Deutsch, Texte der Verwaltung über `__()` (Website: `lt()`).
- Neue Texte der Verwaltung in `lang/en.php` übersetzen – `php bin/console i18n:missing en` muss 0 melden.

## Sicherheit und Ausgabe

- **CSP:** Website `script-src 'self'`, `style-src 'self'` – keine Inline-Skripte, keine `style`-Attribute, keine
  Event-Handler im HTML; Verwaltung erlaubt nur Inline-Stile. Fremde Quellen nur über `Extension::csp()` (Hosts, nie
  `'unsafe-inline'`/`'unsafe-eval'`).
- Jede Ausgabe mit `e()` escapen; Rich-Text nur über `rich()`/`inline()` (Whitelist). **Keine Bilder im Rich-Text** –
  Bilder kommen aus der Mediathek über Bildfelder und Blöcke.
- Verwaltungsrouten prüfen Anmeldung, Recht und CSRF (`auth()` im Core, für Erweiterungen der Router).
- Schema-Änderungen nur **additiv** (neue Tabellen/Spalten), damit Rollbacks möglich bleiben.

## Prüfen vor dem Commit

- `php -l` auf geänderte PHP-Dateien.
- Alle Selbsttests: `php bin/console` listet sie (`*:selftest`, u. a. `extensions:selftest`, `db:selftest`,
  `format:selftest`, `blocks:selftest`, `data:selftest`) – alle grün.
- `php bin/console i18n:missing en` → 0.
- CSS/JS-Quellen geändert (`resources/`, `kits/*/assets`, `extensions/*/assets`): `cd tools && pnpm run build`, die
  gebauten Dateien in `public/` mit committen.
- Jede Funktion mit Doku: Handbuch bzw. Entwicklerhandbuch und `CHANGELOG.md`.

## Commits

- Deutsch, eine Zeile Betreff im Stil `Bereich: was sich ändert` (z. B. `Glossar: Begriffe liegen über Karten-Links`),
  darunter bei Bedarf ein kurzer Absatz zum Warum. Logische, kleine Commits.
- Commits von KI-Assistenten enden mit einer `Co-Authored-By:`-Zeile.
- Kein Push und kein Deploy ohne ausdrücklichen Auftrag; nie auf Live-Servern arbeiten.

---

**English:** No secrets or client data in this public repo. Extensions live in their own repositories and integrate only
through the documented `Core\Extension` integration points (routes with permissions, admin pages, six admin slots, typed
events, `Core\Db\Table`). User-facing terms: “Kit”, uppercase “KI/AI”, formal “Sie” in the admin. Strict CSP (no inline
scripts/styles on the website), escape everything, no images in rich text. Run all `*:selftest` commands and
`i18n:missing en` (0) before committing; German commit messages.
