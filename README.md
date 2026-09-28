# KLXM Studio

[![CI](https://github.com/KLXM-Opensource/studio/actions/workflows/ci.yml/badge.svg)](https://github.com/KLXM-Opensource/studio/actions/workflows/ci.yml)
[![Lizenz: MIT](https://img.shields.io/badge/Lizenz-MIT-blue.svg)](LICENSE)

**Schlankes Multi-Site-CMS ohne Framework – PHP 8.4, Kits, Datentabellen, KLXM Ai, REST-API und MCP-Server.**

Quellcode: [github.com/KLXM-Opensource/studio](https://github.com/KLXM-Opensource/studio) · Website: [studio.klxm.de](https://studio.klxm.de) · Tutorials: [studio.klxm.de/tutorials](https://studio.klxm.de/tutorials) · Downloads: [Releases](https://github.com/KLXM-Opensource/studio/releases) · Fehler und Wünsche: [Issues](https://github.com/KLXM-Opensource/studio/issues)

KLXM Studio (früher „MyCMS.dev light“) betreibt eine oder viele Websites aus einer Installation. Redaktionen
bearbeiten direkt auf der Website (Blockeditor, Inline-Editing), pflegen zentrale Angaben, eigene Datentabellen,
Medien mit Untertiteln und verschlüsselte Online-Anfragen. Agenturen steuern Funktionsumfang, Kits und
Websites zentral über die Netzwerk-Administration.

- **Keine `.htaccess`** – nur `public/` liegt im Webroot, alles andere darüber.
- **Datenschutz für Besucher**: cookiefrei ab Werk – keine Cookies, keine Tracker, keine CDNs; Karten und Video-Vorschaubilder über den eigenen Proxy. Wer Dienste wie Statistik oder Videos einbindet, holt über das Consent Kit vorher die Einwilligung ein.
- **Verschlüsselte Eingänge** für sensible Anfragen (libsodium Sealed Box, Art. 9 DSGVO).
- **Mehrsprachig** (Inhalte und Verwaltung DE/EN), barrierearm, schnell (Ganzseiten-Cache, Budgets für JS/CSS).

Aktuelle Version: siehe `CMS_VERSION` in `app/bootstrap.php` (derzeit 1.0.0) · Änderungsübersicht: [CHANGELOG.md](CHANGELOG.md)

---

## Funktionen im Überblick

| Bereich | Kurz |
|---|---|
| Websites & Netzwerk | Beliebig viele Websites je Installation (eigene Domain, Datenbank, Medien, Benutzer), Netzwerk-Übersicht mit App-Icons, Status und Pool-Speicher, Website-Umschalter mit Single Sign-on, Zwei-Faktor-Anmeldung und Passkeys, Personen per E-Mail einladen, Funktionsumfang je Website (Presets `full`/`content`/`minimal`, Erweiterungen) |
| Inhalte | Seitenbaum, Blockeditor auf der Website mit Entwurf/Veröffentlichen/Versionen, Platzhalter-Liste mit Sprung zum Block, zentrale Angaben des Kits, Style-Editor (Design-Tokens), Projekt-Hinweise aus dem Kit (`guide/*.md`) |
| Daten | Eigene Tabellen ohne Code (23 Feldtypen inkl. Verknüpfungen, Gruppe, IBAN, Ort, Wiederholung), Bedingungen, Detailseiten, öffentliche Formulare, Kalender mit iCal, CalDAV/CardDAV (Erweiterung), geteilte Tabellen mehrerer Websites |
| Anfragen | Verschlüsselte Eingangs-Tabellen mit Protokoll, Zuweisung und Aufbewahrungsfrist |
| Medien | Mediathek im Finder-Stil, zerstörungsfreie Bildbearbeitung (Zuschneiden, Drehen, Spiegeln, Ausrichten, Entzerren), Zuschnitte je Format, SVG mit Bereinigung, geteilte Medien-Pools, Untertitel & Transkripte (auch per KI), dekorative Videos |
| Suche | Website-Suche mit Tippfehlertoleranz (Loupe), optional semantisch/hybrid (Symfony AI) |
| KLXM Ai | Schreiben, Übersetzen, SEO, Alt-Texte, Seiten- und Tabellen-Generator, Transkription – mit Ollama, EU-Anbietern oder OpenAI-kompatiblen Servern; Prüf-Ebene „Eingereicht“ |
| Schnittstellen | REST-API (OpenAPI 3.1) und MCP-Server (Streamable HTTP) mit gemeinsamer Fachlogik und Tokens |
| Support | Meldungen an das Support-Team, Fragen & Antworten, Wissensdatenbank – zentral für alle Websites |

## Anforderungen

- **PHP ≥ 8.4.1** (FPM; getestet mit 8.4 und 8.5) mit `pdo_sqlite` (SQLite ≥ 3.35), `sodium`, `gd`, `mbstring`, `dom`, `fileinfo`, `intl`, `phar`, `zlib`;
  optional `curl`, `exif`, `pdo_mysql`. `proc_open` für Hintergrundprozesse (Sicherungen aus der Netzwerk-Übersicht, KI-Aufträge).
- Apache oder nginx mit Front-Controller, HTTPS.
- Datenbank: SQLite (Standard) oder MySQL/MariaDB je Website.
- Installation aus dem Git-Repository: **Composer 2** (`vendor/` ist nicht eingecheckt). Das Installations-ZIP enthält `vendor/` bereits.
- Nur zum Ändern von CSS/JS-Quellen: **Node 22 + pnpm 10** – die gebauten Dateien liegen versioniert in `public/`.
- Optional: **Ollama** (lokale KI, z. B. `gemma3` für Text/Bilder, `bge-m3` als mehrsprachiges Embedding-Modell) oder
  ein EU-/OpenAI-kompatibler Anbieter; **ffmpeg** und **whisper.cpp** (`whisper-cli`) für lokale Transkription; Cron.

## Schnellstart (lokal)

```bash
git clone https://github.com/KLXM-Opensource/studio.git klxm-studio && cd klxm-studio
composer install
php -S localhost:8000 -t public public/index.php
```

Die gebauten Assets (Verwaltung, Editor, Vendoren, Kits) sind im Repository enthalten – **pnpm ist nur nötig, wenn Sie
CSS/JS-Quellen ändern** (`resources/`, `kits/*/assets`, `extensions/*/assets`):
`cd tools && pnpm install && pnpm run build` (danach `public/` mit einchecken; die CI prüft das).

**Installation auf einem Server ohne Git/Composer:** das fertige Paket `klxm-studio-<version>.zip` (mit `vendor/` und
gebauten Assets, Prüfsumme `.sha256`) von [GitHub Releases](https://github.com/KLXM-Opensource/studio/releases) laden, entpacken,
Dokumentstamm auf `public/` setzen.

Beim ersten Aufruf entstehen Datenbank und `config/config.local.php` (mit `app_key` und `setup_token`) und das Kit
spielt seine Startinhalte ein. Danach `/admin/setup` mit dem Setup-Token öffnen (oder
`php bin/console user:create name@example.org admin`). Für die Entwicklung `'debug' => true` in `config/config.local.php`.

Produktion (Plesk, Cronjobs, KI-Einrichtung, Sicherheits-Checkliste): Installations- und Betriebsanleitung bzw.
Entwicklerhandbuch → „Installation & Anforderungen“, „Betrieb“, „Staging & Deploy“.

## Verzeichnisstruktur

```
httpdocs/
├── public/            DOCUMENT ROOT: index.php, assets/ (Verwaltung, Editor, Vendoren, Symbole, Schrift Lato),
│                      kits/ + extensions/ (gebaute Assets), media/, sites/{key}/media/, pools/{key}/
├── app/               Core (Namespace Core\): Http/, Api/, Data/, Network/, Search/, AI/, Review/, Support/, Blocks/, Admin/views/ …
├── kits/              starter (Start-Kit für eigene Kits) · basis (neutrales Business-Kit mit Musterseiten) · praxis (Arztpraxis) …
├── extensions/        optionale Erweiterungen, z. B. dav (CalDAV/CardDAV)
├── config/            config.php (Standard) · config.local.php (Geheimnisse) · sites/{key}.php (weitere Websites)
├── storage/           Datenbanken, Caches, Sitzungen, Logs, Suchindex, Sicherungen, pools/, shared/, support/, ai/
├── resources/         Quellen: CSS/JS der Verwaltung, Symbol-Auswahl (icons.json), Service-Worker-Vorlage
├── vendor/            Composer-Pakete
├── tools/             Build (pnpm, esbuild, Symbol-Sprite) – nicht auf den Server
├── deploy/            deploy.sh, sync-content.sh, targets/ (Staging & Production)
└── bin/console        Kommandozeile (Websites, Netzwerk, Migration, Sicherung, Suche, KI …)
```

## Dokumentation

| Wo | Inhalt |
|---|---|
| Verwaltung → **Handbuch & Hilfe** (`/admin/hilfe`) | Handbuch für die Redaktion (Kern-Kapitel + Kapitel des Kits), Wissensdatenbank, Fragen & Antworten, Symbole |
| `/admin/hilfe/technik` | **Entwicklerhandbuch**: Architektur, Installation, Konfiguration, Build, Deploy, Betrieb, CLI, Multi-Site & Netzwerk, Rechte, Sicherheit, Kits & Design, Editor, Datentabellen, Formulare, Kalender, Medien, Sprachen, Suche, KLXM Ai, Freigabe, REST-API, MCP, Support, Verwaltung, Symbole, Karten, PWA – API- und MCP-Referenz live aus dem Code |
| `/admin/hilfe/tutorials` | Tutorials für Redaktion, Administration und Agenturen als Text (DE/EN, offline) – mit Links zu den Videos |
| [studio.klxm.de/tutorials](https://studio.klxm.de/tutorials) | **Tutorial-Videos und Trailer** (ohne Ton, Untertitel DE/EN). Sie werden nicht mit dem CMS ausgeliefert; die Verwaltung verlinkt dorthin (`config/config.php` → `docs_url`, eigene Adresse oder `''` für White-Label) |
| `/api/v1/openapi.json` | Maschinenlesbare OpenAPI-3.1-Beschreibung |
| [deploy/README.md](deploy/README.md) | Staging & Deploy mit Releases und Rollback; Vorlage für GitHub Actions im Projekt-Repository (`deploy/github-actions/`) |
| [kits/starter/README.md](kits/starter/README.md) | Start-Kit: eigenes Kit entwickeln (`php bin/console kit:create meinkit`), Schritt für Schritt |
| [kits/basis/README.md](kits/basis/README.md) | Referenz-Kit: Design-Tokens, Navigationen, Musterseiten |
| [extensions/dav/README.md](extensions/dav/README.md) | CalDAV/CardDAV-Erweiterung |
| [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md) | Mitgelieferte Drittsoftware und Lizenzen |
| [CONTRIBUTING.md](CONTRIBUTING.md) · [SECURITY.md](SECURITY.md) | Mitwirken · Sicherheitslücken vertraulich melden |

## REST-API & MCP in Kürze

Tokens: Verwaltung → **API & MCP** (Stufe `read`/`write`, Ablaufdatum, Modus „Direkt übernehmen“ oder „Zur Freigabe“);
gespeichert wird nur ein SHA-256-Hash. Header `Authorization: Bearer cms_…` (Rückfall `X-Api-Key`). 300 Anfragen je 5 Minuten und Token.

```bash
curl -H "Authorization: Bearer $TOKEN" https://ihre-domain.de/api/v1/me
claude mcp add --transport http mycms https://ihre-domain.de/mcp --header "Authorization: Bearer cms_…"
```

Inhaltsänderungen entstehen als Entwurf mit Revision; Zugänge „Zur Freigabe“ reichen Änderungen zur Prüfung ein
(Verwaltung → KLXM Ai → Eingereicht). Anfragen aus Eingängen sind über API/MCP nur als Metadaten sichtbar.

## Lizenz

KLXM Studio – Copyright (c) 2026 KLXM Crossmedia GmbH and contributors – **MIT-Lizenz**
(`MIT`, Volltext: [LICENSE](LICENSE), Hinweis: [COPYRIGHT](COPYRIGHT)). Das gilt für den Kern, die mitgelieferten
Kits (`kits/*`) und Erweiterungen (`extensions/*`), soweit dort nichts anderes steht.
Quelltext: <https://github.com/KLXM-Opensource/studio>.

Was das für Agenturen und Betreiber bedeutet (Kurzfassung, keine Rechtsberatung):

- **Nutzen, anpassen, betreiben, weitergeben und verkaufen** ist ohne Einschränkung erlaubt – auch kommerziell und
  für Kundenprojekte, mit oder ohne Quelltext.
- **Eigene Kits und Erweiterungen** dürfen unter jeder beliebigen Lizenz stehen – offen oder proprietär.
- **Inhalte, hochgeladene Medien, Datentabellen und Konfiguration** einer Website gehören dem Betreiber und werden
  von der Lizenz nicht berührt.
- Wer den Code weitergibt, lässt den Copyright- und Lizenzhinweis (`LICENSE`, `COPYRIGHT`) stehen und legt
  `THIRD-PARTY-NOTICES.md` bei.

Mitgelieferte Drittsoftware, Schriften, Symbole, Daten und Medien behalten ihre eigenen Lizenzen (u. a. MIT, BSD,
Apache-2.0, SIL OFL 1.1, CC BY 3.0; die Liberation-Schriften in PDF.js stehen unter der GPL-2.0 mit Font-Ausnahme und
liegen als eigene Dateien bei): [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md), in der Verwaltung unter
**Handbuch & Hilfe → Lizenzen**. Prüfung für CI: `node tools/licenses.mjs` (Abbruch bei Copyleft-Lizenzen wie
GPL/AGPL/LGPL in ausgelieferten Paketen).
