@AGENTS.md

# KLXM Studio – Orientierung für Claude Code

Flat-PHP-CMS (PHP 8.4+, SQLite, kein Framework; Bibliotheken über Composer, u. a. Symfony AI, Loupe). Mehrere Websites je
Installation (`config/sites/{key}.php`, Hosts → Website), Netzwerk-Konten mit Einmal-Anmeldung. Oberfläche und Kommentare
auf Deutsch. Ausführliche Doku liegt im Produkt: Handbuch `/admin/hilfe`, Entwicklerhandbuch `/admin/hilfe/technik`.

## Aufbau

| Pfad | Inhalt |
|---|---|
| `app/` | Kern, Namespace `Core\` (eine Klasse je Datei, z. B. `app/Media.php` = `Core\Media`, `app/AI/Ai.php` = `Core\AI\Ai`) |
| `app/routes.php` | alle Routen; Controller in `app/Http/Controllers` (Verwaltung: `…/Admin`) |
| `app/Views/` | Website-Ansichten des Kerns · `app/Admin/views/` Verwaltung · `…/help/manual/` Handbuch · `…/help/technical/` Entwicklerhandbuch |
| `app/Database.php` | Schema + Migrationen (nur additiv: `ensureColumns`), `php bin/console migrate --all` |
| `app/SystemSchema.php`, `app/*/AdminSettings.php` | Grundeinstellungen (`sys.*`, `app()->settings->get/set`) |
| `app/Features.php`, `FeatureInfo.php` | Funktionsschalter je Website (`Features::on('search')`) |
| `kits/{name}/` | Kits (Designs): `theme.php`, `blocks/`, `templates/`, `assets/` → gebaut nach `public/assets/kits/{name}` |
| `extensions/` | mitgelieferte Erweiterungen (eigene Repos, per Composer; nur über `Core\Extension`) |
| `resources/css`, `resources/js` | Quellen der Kern-Assets → `public/assets/{css,js}` (Dateien mit `_` sind Teile/Module) |
| `lang/en.php`, `lang/site/en.php` | Übersetzungen Verwaltung (`__()`) bzw. Website (`lt()`), Schlüssel = deutscher Text |
| `bin/console` | CLI (`php bin/console list`), `--site=key` für eine Website, `--all` für alle |
| `storage/`, `config/config.local.php`, `config/sites/` | Laufzeitdaten und Schlüssel – nie committen |

## Befehle

```bash
cd tools && pnpm run build                 # alle CSS/JS (Kern, Kits, Erweiterungen) bauen – Ergebnis in public/ mitcommitten
php -S 127.0.0.1:8099 -t public public/index.php   # lokaler Server; Websites über *.localhost:8099 (Hosts in config/sites/)
php bin/console migrate --all && php bin/console cache:clear --all && php bin/console health --all
php bin/console i18n:missing en            # muss 0 melden; Website-Texte: --site-texts
php bin/console list | grep selftest       # Selbsttests (data, blocks, push, inbox, ai, account, …) – alle grün halten
php bin/console search:index --all         # Suchindex; search:query "…" testet wie Besucher
```

Eigene PHP-Skripte gegen eine Website: `$_SERVER['argv'] = $argv = ['console', 'x', '--site=key']; require 'app/bootstrap.php';`
Browser-Tests: Playwright aus `tools/node_modules/playwright` (Shadow-DOM-Elemente über `page.locator()` erreichbar).

## Was man leicht übersieht

- **CSP der Website:** keine Inline-Skripte, keine `style`-Attribute. Dynamische Werte per Klasse oder CSSOM (`el.style.setProperty`).
- **Rich-Text** nur über `Core\Sanitizer` (Klassenvertrag `P_CLASSES`, Textfarben `c-*`); Kits gestalten die Klassen, Zusatz-CSS
  lädt bedingt über `conditional_css` mit Pseudo-Typ `'@rich'`. Keine Bilder im Rich-Text.
- **Kern-CSS je Block** über `Theme::conditionalCss()` / `coreCss('x.css')` – ein Kit ersetzt es mit eigener `css/x.css`.
- **Budgets:** Frontend-JS eines Kits < 8 KB, CSS der Startseite < 30 KB; Kern-Skripte für Besucher laden erst bei Bedarf.
- **Neue Funktionen standardmäßig aus**, wenn sie Daten nach außen schicken oder Kosten verursachen (z. B. KI-Antwort in der Suche).
- **Doku gehört zur Funktion:** Handbuch, Entwicklerhandbuch, `CHANGELOG.md` (Abschnitt der aktuellen Version), DE + EN-Texte.
- **KI** (`Core\AI\Ai`): aus, bis Anbieter + Modelle konfiguriert sind (Grundeinstellungen → KI oder `'ai' => […]` in der
  Website-Konfiguration). Lokal mit Ollama: `qwen3:4b-instruct` (Text), `nomic-embed-text` (Embeddings).
