# Changelog – Start-Kit „starter“

Format: [Keep a Changelog](https://keepachangelog.com/de/1.1.0/), Versionen nach [SemVer](https://semver.org/lang/de/).
Die Version steht auch in `theme.php` → `'version'` (Verwaltung → System, `kit:list`).

## [Unveröffentlicht]

### Neu
- Einstieg (`hero`): Variante **„Such-Einstieg“** (`search`) als kommentiertes Beispiel für eigene Varianten –
  Felder nur für diese Variante (`'variants'`), Feldgruppe `Core\Blocks\Hero::fields('search', …)`, CSS nur bei
  `hero:search` (`css/hero-search.css`, ≈ 1 KB), `variant_help` fürs Handbuch, Demo-Seite `/hero-varianten`.

## [1.0.0] – 2026-09-25

### Neu
- Erstes Release: kleinstes vollständiges Kit als Vorlage für `php bin/console kit:create <name>`.
- Sechs Beispielblöcke (hero, text, text_image, cards, cta, faq) plus video und downloads als dünne Hüllen um Kern-Helfer.
- Design-Tokens für den Style-Editor (Farben hell/dunkel, Schrift, Form, zwei Navigationsvarianten), drei AA-geprüfte Voreinstellungen.
- Kern-Blöcke im Kit-Stil nur über Variablen (`css/core.css`); Suche, Besucher-Chat und `footer_links()` eingebunden.
- Englisch für Verwaltung und Website, Kontrastprüfung `tools/contrast.php`, Demo-Inhalte (Startseite + Unterseite).
