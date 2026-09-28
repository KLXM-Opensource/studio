# Kit „starter“ – Start-Kit für eigene Kits

Das kleinste **vollständige** Kit für KLXM Studio: alles, was ein Kit kann, ist vorhanden und in den Dateien
kommentiert – aber jeweils nur einmal und so knapp wie möglich. Kein Showcase, sondern ein Ausgangspunkt.

- Sechs Beispielblöcke (`hero`, `text`, `text_image`, `cards`, `cta`, `faq`) plus `video` und `downloads` als dünne
  Hüllen um Kern-Helfer (Zwei-Klick-Video, PDF-Viewer)
- Design-Tokens für den Style-Editor, eine Akzentfarbe, Systemschrift, hell und dunkel, zwei Navigationsvarianten,
  drei geprüfte Voreinstellungen
- Kern-Blöcke (Datenliste, Detailseite, Formular, Kalender, Kennzahlen, Karte, Galerie …), Suche, Besucher-Chat und
  Links von Erweiterungen (`footer_links()`) sind eingebunden
- CSP-sicher, WCAG 2.2 AA, keine externen Anfragen, keine Cookies, mehrsprachig (Deutsch/Englisch)

> „Kit“ ist der Begriff in der Oberfläche; technisch ist ein Kit ein Theme (`kits/{name}/theme.php`).

## Schritt für Schritt

1. **Kopie anlegen** – benennt Ordner, Funktionspräfix (`starter_` → `meinkit_`), Bezeichnung und Paketnamen um:
   ```
   php bin/console kit:create meinkit              # Standard: --from=starter
   php bin/console kit:create meinkit --from=basis # oder ein anderes Kit als Vorlage
   ```
2. **Bauen**: `cd tools && pnpm run build` → `public/assets/kits/meinkit/{css,js}`
3. **Testwebsite**: `php bin/console site:create meinkit meinkit.localhost meinkit`, dann
   `php bin/console user:create ich@example.com admin --site=meinkit` – beim ersten Aufruf spielt der Core
   `seed.php` ein.
4. **Anpassen** in dieser Reihenfolge:
   - `theme.php` § 1: `label`, `description`, `version`
   - Farben: `theme.php` § 6 **und** `assets/css/_tokens.css` (Standardwerte müssen übereinstimmen), dann
     `php kits/meinkit/tools/contrast.php`
   - Kopf/Fuß: `templates/partials/header.php`, `footer.php`, CSS in `assets/css/site.css`
   - Blöcke: bestehende ändern oder neue hinzufügen (siehe unten)
   - „Website“-Formular: `theme.php` § 7; Startinhalte: `seed.php`, `tools/demo-content.php`
5. **Prüfen** (Prüfliste unten), `CHANGELOG.md` und `LICENSE` anpassen.

## Aufbau

```
kits/starter/
├── theme.php                 Definition, Abschnitt für Abschnitt kommentiert (§ 1–11, siehe unten)
├── functions.php             Helfer starter_* (Stammdaten, Links, Menü, Abschnittskopf, Buttons, JSON-LD, Manifest)
├── seed.php                  Startinhalte: settings, pages, page_refs, after
├── blocks/                   Renderer je Blocktyp – $b (Core\Block), $d (Daten)
│   ├── hero.php  text.php  text_image.php  cards.php  cta.php  faq.php
│   └── video.php  downloads.php
├── templates/
│   ├── layout.php            <head>, Kopf, <main>, Fuß, Besucher-Chat
│   ├── error.php  maintenance.php  offline.php
│   └── partials/
│       ├── header.php        Marke, Navigation (popover mobil), Sprachen, Suche
│       ├── footer.php        Kontakt, Seiten, Rechtliches + footer_links()
│       ├── section.php       Hülle je Block (Abschnitts-Optionen)
│       └── editor.php        Editor-Mount (unverändert übernehmen)
├── assets/
│   ├── css/
│   │   ├── _tokens.css       Design-Tokens + Rollen (einzige Stelle mit Farbwerten), hell/dunkel
│   │   ├── _base.css         Reset, Typografie, Abschnitte, Buttons, Rich-Text
│   │   ├── site.css          immer: Tokens + Basis + Kopf, Navigation, Suche, Einstieg, Fuß
│   │   ├── blocks.css        nur bei Beispielblöcken (conditional_css)
│   │   └── core.css          nur bei Kern-Blöcken: Variablen für die Kern-Stylesheets
│   └── js/
│       ├── site.js           Progressive Enhancement (Menü, Suchvorschläge)
│       └── (Video-Skript: Kern, resources/js/embed.js – lädt das Kern-Fragment video-embed selbst)
├── lang/
│   ├── en.php                Verwaltung: Beschriftungen aus theme.php
│   └── site/en.php           Website: Texte aus lt('…')
├── tools/
│   ├── demo-content.php      Demo-Seiten: Startseite + „Über uns“
│   └── contrast.php          WCAG-Prüfung aller Voreinstellungen (hell + dunkel)
├── build.mjs                 optionaler Build-Hook (hier leer, Beispiel für Schriften)
├── composer.json             Stub „klxm-studio-kit“ – Paket-Installation geplant
├── README.md  CHANGELOG.md  LICENSE
```

### theme.php – Abschnitte

| § | Inhalt |
|---|---|
| 1 Meta | `label`, `description`, `version`, `requires` (Core-Version), `source_lang` |
| 2 Abschnitte | `backgrounds` → Klasse `bg-*`, `dark_backgrounds`, `container_class`, `button_class`, `frame_hosts` |
| 3 SEO & JSON-LD | `seo` (Felder für Titel/Beschreibung/Bild), `jsonld` (Organisation), `link_keywords` |
| 4 Projekt | `project` (Begriffe, Karte, Einrichtungs-Checks, `public_info`, Hinweisbalken, MCP-Hinweise), `app` |
| 5 Assets | `fonts`, `image_ratios`, `conditional_css`; Hinweise zu `core_blocks`, `rich_text`, `proxy`, `forms` |
| 6 Design | Tokens (Farben, Schrift, Form), Navigationsvarianten `nav-bar`/`nav-center`, `presets`, `dark` |
| 7 Website | Formular „Website“: Stammdaten, Darstellung, Hinweisbalken, Recht, SEO |
| 8 Blöcke | Beispielblöcke mit Feldern, Varianten, `jsonld` (FAQPage, VideoObject) |
| 9 Daten | Datenlisten und Detailseiten (Kern-Blöcke, eigene Ausgabe möglich) |
| 10 Startinhalte | `seed.php` → `tools/demo-content.php` |
| 11 Sprachen | `lang/en.php`, `lang/site/en.php`, `i18n:missing` |

## Farben: Tokens und Rollen

`_tokens.css` definiert die Tokens (`--s-a`, `--s-text`, `--s-bg` …) und daraus **Rollen** (`--s-fg`, `--s-head`,
`--s-link`, `--s-mut`, `--s-card`, `--s-btn-bg`, `--s-btn-fg`). Komponenten verwenden nur Rollen. Die Abschnitte
„Akzentfarbe“ und „Dunkel“ stellen die Rollen um (`_base.css`) – deshalb sehen Karten, Buttons, Formulare und
Kern-Blöcke in jedem Abschnitt stimmig aus, ohne eigene Regeln.

## Neuen Block hinzufügen

1. `theme.php` → `blocks`: `'zitat' => ['label' => 'Zitat', 'icon' => 'quotes', 'group' => 'Inhalt', 'fields' => [...]]`
2. `blocks/zitat.php`: Ausgabe mit `e()`, `rich()`, `$b->edit('feld')` für das Bearbeiten im Frontend
3. CSS in `assets/css/blocks.css` (oder eigene Datei) und in `conditional_css` dem Typ zuordnen
4. Beschriftungen in `lang/en.php`, feste Texte (`lt()`) in `lang/site/en.php`

Der Block erscheint automatisch im Editor, in der REST-API und im MCP-Server.

## Eigene Variante eines Blocks

Beispiel im Kit: der Einstieg (`hero`) hat neben „Zentriert“ und „Text und Bild“ die Variante **„Such-Einstieg“**
(`search`) – großes Suchfeld mit Vorschlägen beim Tippen und häufigen Suchbegriffen darunter. Vier Stellen:

1. `theme.php` § 8 → `'variants' => [..., 'search' => 'Such-Einstieg (Suchfeld mit Vorschlägen)']` (+ `'variant_help'`
   für das Handbuch: wann welche Variante)
2. Felder nur für diese Variante: `'variants' => ['search']` am Feld – die Seitenleiste blendet sie bei anderen Varianten
   aus. Fertige Feldgruppen: `...\Core\Blocks\Hero::fields('search', ['search'])` (auch `video`, `figures`, `dates`,
   `form`, `map`, `compare`, `gallery`, `quote`, `cards`, `marquee` – Ausgabe-Helfer in `app/Blocks/Hero.php`)
3. `blocks/hero.php`: `if ($b->variant() === 'search') …` – bestehende Varianten bleiben unverändert
4. CSS nur für diese Variante: `conditional_css` → `'css/hero-search.css' => ['hero:search']`

Die Demo-Inhalte enthalten die Seite `/hero-varianten`.

## Eigene Schrift

Ohne Build: `php bin/console fonts:install Inter --weights=400,700` – die Schrift wird selbst ausgeliefert und steht
im Style-Editor zur Auswahl. Mit dem Kit ausliefern: `build.mjs` + `package.json` (Beispiel in `build.mjs`).

## Budgets (Stand 1.0.0, minifiziert)

| Datei | Größe | lädt |
|---|---|---|
| `css/site.css` | ≈ 12,5 KB | immer |
| `css/blocks.css` | ≈ 3,2 KB | bei Beispielblöcken |
| `css/core.css` | ≈ 1,2 KB | bei Kern-Blöcken |
| `css/hero-search.css` | ≈ 1,0 KB | nur bei Einstieg „Such-Einstieg“ |
| `js/site.js` | ≈ 0,4 KB | immer |
| `assets/js/embed.js` (Kern) | ≈ 1,2 KB | bei Block „Video“ |

Startseite der Demo: ≈ 15,7 KB CSS, 0,4 KB JS (Richtwerte des Cores: CSS < 30 KB, JS < 8 KB). Keine Schriftdatei.

## Prüfliste

- [ ] `php bin/console health --site=<website>` – u. a. „Kit kompatibel“ (`requires`)
- [ ] `cd tools && pnpm run build` – Größen in der Ausgabe gegen die Budgets prüfen
- [ ] `php kits/<kit>/tools/contrast.php` – alle Voreinstellungen WCAG 2.2 AA (hell + dunkel)
- [ ] `php bin/console i18n:missing en --site-texts --site=<website>` und ohne `--site-texts` – keine eigenen Texte offen
- [ ] `node tools/licenses.mjs` – Lizenzen eigener Pakete (z. B. Schriften) verträglich
- [ ] Browser: keine Konsolenfehler, keine externen Anfragen, keine Cookies; Tastatur (Tab, Escape), 200 % Zoom,
      Kontrastmodus (forced colors), `prefers-reduced-motion`, hell/dunkel, 320 px Breite
- [ ] Kern-Blöcke auf einer Testseite: Datenliste + Detailseite, Formular, Kennzahlen, Karte, Video, Downloads, Suche

## Lizenz und Paket-Installation

Das Start-Kit steht unter der MIT-Lizenz (`LICENSE`). Ihr daraus entwickeltes Kit dürfen Sie unter einer Lizenz Ihrer
Wahl veröffentlichen.

**Paket-Installation geplant:** Erweiterungen lassen sich schon per Composer installieren (Typ
`klxm-studio-extension`); Kits erkennt der Core bisher nur unter `kits/{name}/`. `composer.json` ist daher ein Stub
mit dem vorgesehenen Typ `klxm-studio-kit` – bis dahin den Kit-Ordner nach `kits/` kopieren.
