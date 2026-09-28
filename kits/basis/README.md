# Kit „basis“

Neutrales Start-Kit für KLXM Studio – Demo-Kit mit Musterseiten und Ausgangspunkt für Kundenprojekte
(Unternehmen, Agentur, Kanzlei, Verein, Handwerk, Studio). Vollständig über **Design-Tokens** gesteuert
(Style-Editor: Verwaltung → Design), vier **Navigationen**, sieben geprüfte **Voreinstellungen**, hell und dunkel,
mehrsprachig ab dem ersten Tag, keine externen Anfragen.

## Struktur

```
kits/basis/
├── theme.php          Blöcke, Website, project, seo/jsonld, fonts, conditional_css, 'design' => design.php
├── design.php         Design-Tokens (Farben, Typografie, Form, Navigation, Farbschema), Schriften, 7 Voreinstellungen
├── functions.php      Helfer basis_* (Kontakt, Öffnungszeiten, Menü/Mega-Menü, Symbole, JSON-LD, Migration alter Farbwerte)
├── seed.php           Demo-Inhalte „Musterfirma (Demo)“ + 'after' → Musterseiten (tools/demo-content.php)
├── blocks/            Renderer je Block ($b = Core\Block, $d = Daten)
├── templates/         layout, error, maintenance, offline, partials/
│                      header.php → header-modern | header-bar (klassisch/ausführlich) | header-minimal, topbar, menu-btn,
│                      footer (Spalten/schlicht), section (Marke, Sprachen, Öffnungszeiten, Video, Editor, Werkzeugleiste: Kern-Fragmente)
├── assets/css/        site.css (immer) · nav-{modern,classic,minimal,extended}.css (je nach Navigation) ·
│                      blocks.css, extra.css, data.css, calendar.css, dataform.css, hero-x.css (nur bei passenden Blöcken/Varianten) ·
│                      preview.css (nur Vorschau im Style-Editor) · _topbar.css/_quick.css (Teile, per @import)
├── assets/js/         site.js (Menüs, Tastatur, „jetzt geöffnet“, Scroll-Zustand; ≈ 4,1 KB) · blocks.js (Reiter, Video; 1,8 KB)
├── tools/             contrast.php (WCAG-Prüfung aller Voreinstellungen) · demo.php + demo-content.php (Musterseiten)
├── lang/              en.php (Verwaltung) · site/en.php (feste Website-Texte lt())
├── docs/manual.php    Kapitel für das Handbuch der Redaktion (Array: eigene Kapitel in docs/manual/, Rest aus dem Core)
├── package.json       @fontsource/{inter,manrope,ibm-plex-sans,source-serif-4,lora,fraunces} + Inter-TTF (Icons)
└── build.mjs          kopiert die Schriften (latin + latin-ext, 400/600/700) und erzeugt css/font-{key}.css
```

Build: `cd tools && pnpm run build` → `public/kits/basis/{css,js,fonts}` (installiert Kit-Pakete bei Bedarf).

**Budgets** (minifiziert, Stand des aktuellen Builds): immer geladenes CSS = site.css ≈ 27,5 KB + nav-*.css 0,9–4,8 KB
+ Schrift ≈ 2 KB (je Familie) + Design-Datei ≈ 1,3 KB (nur wenn vom Standard abweichend). Damit liegt die Startseite je nach
Navigation bei gut 30 KB CSS – knapp über dem Richtwert des Cores. JS auf der Startseite: site.js ≈ 4,1 KB.

## Design-Tokens (design.php)

Alle Farben, Schriften und Maße kommen aus Variablen `--b-*` bzw. Klassen am `<html>` – das Kit-CSS enthält keine
festen Farbwerte außer den Standardwerten (= Voreinstellung „Petrol Business“, hell + dunkel in site.css).

| Gruppe | Token → Ausgabe |
|---|---|
| Farben | accent `--b-a`, accent_strong `--b-a-strong`, on_accent `--b-a-on`, ink `--b-ink`, text `--b-text`, muted `--b-muted`, background `--b-bg`, surface `--b-surface`, line `--b-line`, dark_section `--b-dark-sec` (je hell + dunkel, Kontrastprüfung im Editor) |
| Typografie | font_body `--b-font`, font_head `--b-font-head`, base_size `--b-fs` (px-Zahl, als rem umgerechnet), heading_scale `--b-hs`, heading_weight `--b-hw`, heading_tracking `--b-track` |
| Form | radius `--b-radius`, buttons → `btn-solid/outline/pill`, cards → `cards-flat/outlined/elevated`, spacing `--b-sec-y` |
| Navigation | nav → `nav-modern/classic/minimal/extended` (+ Kopf-Partial), nav_sticky → `nav-sticky`, nav_transparent → `nav-over`, topbar → `has-topbar`, footer → `footer-columns/simple` |
| Kopfbereich: Suche & Aktionen | `ha_*` aus `Core\HeaderActions::designGroup()` – Standard: abgesetzter Menüpunkt „Kontakt“ + Lupe (Handlungsaufruf, Suche, Anordnung, Kontakt-Chip, Sprache, Social, Anmelden; `header_actions()`) |
| Farbschema | dark → `has-dark` (dunkle Werte in `@media (prefers-color-scheme: dark){html.has-dark{…}}`; Vorschau: `html.is-dark`) |

Abgeleitet per `color-mix()`: `--b-a-soft`, `--b-surface-2`, `--b-line-strong`, `--b-header-bg`, Schatten, Fehlerfarbe.
Abschnitte „Akzentfarbe“/„Dunkel“ stellen Rollen um (`--b-fg`, `--b-link`, `--b-btn-*` …); im dunklen Schema erben sie
die Seitenwerte (`inherit`). **Neue Farbe/Voreinstellung:** Werte in design.php ergänzen, `php kits/basis/tools/contrast.php`
ausführen (Exit-Code 1 bei Verstößen). **Frühere Websites:** `accent`/`dark_mode` aus „Website“ werden beim
ersten Aufruf einmalig in Design-Werte übernommen (`basis_design_migrate()`).

## Navigationen

| Variante | Desktop | Mobil | Ohne JavaScript |
|---|---|---|---|
| Modern | schwebende Leiste, Aufklappmenüs, optional transparent über dem ersten Abschnitt (serverseitig ermittelt: `basis_over_hero()`), beim Scrollen `.is-stuck` | Panel unter der Leiste | Leiste scrollt mit, Menü sichtbar |
| Klassisch | Infoleiste (optional), Logo links, Menü rechts, Aufklappmenüs (`details`) | Panel, Aufklapp-Akkordeon | Menü sichtbar |
| Minimal | Menü-Schaltfläche → Vollbild-Menü (große Schrift, Direktkontakt, Adresse, Social, Sprache) | gleich | Liste unter dem Kopf |
| Ausführlich | wie klassisch, Mega-Menü: Einleitung (Seiten-Beschreibung) · Unterseiten mit Beschreibung · Vorschaubild der ersten Unterseite mit og_image oder Direktkontakt | Akkordeon ohne Beschreibungen | Menü sichtbar |

Tastatur: Tab, ←/→ zwischen Hauptpunkten, ↓ öffnet und springt ins Untermenü, ↑/↓ darin, Pos1/Ende, Escape schließt
(Fokus zurück). Offenes Mobil-/Vollbild-Menü: Rest der Seite `inert`, Scrollen gesperrt. „Jetzt geöffnet“ in der Infoleiste
rechnet site.js im Browser aus `data-hours` (Zeitzone der Website) – der Seiten-Cache bleibt gültig.
Website-Suche (Kern, Funktion „search“): Lupe in `.site-nav__end` öffnet ein Popover-Suchfeld (Desktop), im Mobil- bzw. Vollbild-Menü steht das Suchfeld oben; Ergebnisse unter `/suche`. Vorschläge lädt `site.js` erst beim ersten Fokus (Kern-Skript `search.js`).

## Musterseiten (Showcase)

`CMS_SITE=<site> php kits/basis/tools/demo.php [--force|--remove] [--preset=mint]` legt den Seitenbaum `/musterseiten`
(Übersicht + Einstieg & Abschnitte, Inhalte, Medien, Daten & Termine, Formulare & Kontakt) an – mit 8 erzeugten Bildern
(Sammlung „Musterseiten (Demo)“), einer Beispiel-PDF, den Tabellen `demo_projekte` (mit Detailseite), `demo_termine`
(Kalender mit Wiederholung), `demo_rueckruf` und `demo_antrag` (öffentliche Formulare mit Bedingung, IBAN, Gruppe).
Neue Websites erhalten ihn automatisch (seed.php → `after`); `--remove`/`--force` entfernen auch den früheren Seitenbaum `/baukasten` älterer Installationen. Die Übersicht ist die Musterseite des Style-Editors (`design.sample`).

## Website (Einstellungen)

| Gruppe | Felder |
|---|---|
| Stammdaten | org_name, short_name, tagline, street, zip, city, country, phone, email, hours (Repeater, API/MCP-fähig), hours_note, geo |
| Darstellung | logo, logo_dark, header_cta_label/_link, footer_text (Farben/Schriften: Verwaltung → Design) |
| Hinweisbalken | notice_active, notice_text |
| Social Media | social (label + url) |
| Recht | imprint_page, privacy_page, accessibility_page |
| SEO | site_title, site_title_suffix, default_meta_description, og_default_image, schema_type |

Sprachunabhängige Felder tragen `'translate' => false`; Textfelder sind je Sprache pflegbar.
`project.map` speist die Kern-Karte (Standort `geo`, Adresse `street/zip/city`).
Link-Sonderwerte in Link-Feldern: `phone`, `email`.

## Blöcke

hero (split/centered/compact, H1; mit Werkzeug: search = Such-Einstieg mit Vorschlägen und Chips, form = Text + Formular einer Datentabelle, map = Karte + Kontaktkarte mit „jetzt geöffnet“ – CSS nur dort: css/hero-x.css ≈ 5 KB; Musterseite /musterseiten/hero-varianten, `tools/demo.php --heroes`) · richtext · text_image (rechts/links) · features (Raster/Karten/schlicht, 2–4 Spalten,
Symbol oder Bild) · stats · quote (1 = groß, 2–3 = Karten) · faq · cta (Band/Box) · **pricing** (2–4 Pakete, Hervorhebung) ·
**steps** (nummeriert/Zeitleiste) · **tabs** (oben/links, ARIA-Tabs, ohne JS untereinander) · **video** (YouTube/Vimeo
Zwei-Klick mit lokalem Vorschaubild, MP4 direkt; breit/mit Text) · logos · contact · downloads · map.
Kern-Blöcke (Stil über die Tokens): data_list, data_fields, data_form (css/dataform.css), calendar, upcoming
(css/calendar.css), gallery, slideshow, stack_cards + Lightbox (css/extra.css), Abschnitte mit Vollbild/Hintergrundbild.

## Mehrsprachigkeit

Alle festen Texte über `lt('Deutscher Text')`, Datumsangaben über `date_local()` (Wochentage der Öffnungszeiten).
Übersetzungen in `lang/site/{sprache}.php`. Prüfen: `php bin/console i18n:missing en --site-texts --site=…`.
`<html lang>`, hreflang + x-default und Sprachumschalter (Kopf + Fuß, nur bei mehreren Sprachen) sind eingebaut.
**Wichtig:** `lt()` nur mit einfachen Anführungszeichen und festem Text aufrufen – sonst findet das Prüfkommando den Text nicht.

## Kundentheme ableiten

```
php bin/console theme:create kunde basis     # Kopie, Präfix basis_ → kunde_
cd tools && pnpm run build
php bin/console site:create kunde www.kunde.de kunde
```

Danach im neuen Kit: Label/Version in theme.php, Standardwerte/Voreinstellungen in design.php (Standardwerte auch
in site.css `:root` und im Dunkel-Block anpassen – sie müssen übereinstimmen), seed.php (eigene Startinhalte; `after`
entfernen, wenn keine Musterseiten gewünscht sind), Blöcke ergänzen oder entfernen, docs/manual.php und docs/manual/*.php anpassen.

## Lizenzen

Inter, Manrope, IBM Plex Sans, Source Serif 4, Lora, Fraunces – SIL Open Font License 1.1
(`public/kits/basis/fonts/OFL-{key}.txt`). Beispielbilder der Musterseiten: automatisch erzeugt (GD), frei verwendbar.
Symbole des Kits (`basis_icon()`): eigene Pfade (24 × 24, Linie); zusätzlich stehen die Kern-Symbole (Phosphor duotone,
MIT) über `icon()` zur Verfügung.
