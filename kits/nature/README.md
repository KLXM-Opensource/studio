# Kit „nature“

Organisch, warm und ruhig – für Höfe, Hofläden, Gärtnereien, Naturschutz, Umweltbildung, Outdoor und nachhaltige
Betriebe. Eigenständige Gestaltung: keine Marken, Logos, Texte, Fotos oder Code Dritter; alle Illustrationen und
Muster entstehen im Kit. CSP-fest (keine Inline-Skripte/-Styles), keine externen Anfragen, cookiefrei ab Werk,
Schriften lokal, WCAG 2.2 AA, MIT.

## Formensprache

| Element | Umsetzung |
|---|---|
| Palette | Papier (`background`), Sand (`surface`), Karten (`panel`), Moos als Akzent (`accent` Füllfarbe ≥ 3:1, `accent_ink` Schrift ≥ 4,5:1), Ton als Schmuckfarbe (`earth`, ≥ 3:1 als Markierung), Borke/Waldgrün als Schrift |
| Waldnacht | dunkles Schema (tiefes Waldgrün, aufgehellter Akzent) per `prefers-color-scheme`; Abschnitte „Waldnacht“ auch im hellen Schema, Akzent dort 45 % in Creme gemischt (Buttons mit dunkler Schrift) |
| Schrift | Fraunces mit SOFT-Achse (weich gerundete Serifen, `--n-soft`) oder Young Serif für Überschriften, Nunito Sans oder Source Sans 3 für Fließtext; Dachzeilen kursiv in der Serif oder als kleine Versalien (`lb-*`) |
| Formen | Karten mit weichem Radius (Standard 16 px), Buttons/Felder 8 px (`btn-soft`; Pille/Kontur wählbar), große Bilder als Blatt oder Kiesel (`img-*`), Symbole und Monogramme in Kieseln |
| Marken | Blatt (zwei gerundete Ecken) vor Dachzeilen, Listen, Menüpunkten, „heute“ in den Öffnungszeiten; Samenkorn als Alternative (`mk-*`) |
| Muster | erzeugte SVG-Masken (`tools/patterns.php`): Papierstruktur (feTurbulence), Höhenlinien, Blattadern als Seitenhintergrund (`pagebg-*`); Wellen- oder Hügelkanten zwischen farbigen Abschnitten (`dv-*`); Hügelsilhouette über dem Fuß |
| Illustration | Landschaft im Einstieg ohne Bild (`nature_landscape()`, Inline-SVG, Farben nur aus Tokens) mit Details je Jahreszeit: Blüten, Sonne + Vögel, fallende Blätter, Schnee (`sz-is-*`, „Automatisch“ nach Datum) |
| Bewegung | sanftes Einblenden, Jahresringe füllen sich; Blätter/Schnee/Höhenlinien treiben kaum merklich (`has-drift`, nur `transform`) – nie bei „Bewegung reduzieren“ |

## Struktur

```
kits/nature/
├── theme.php        17 Blöcke, Website (Schlüssel wie „essenz“/„fluid“ + seasons, directions), conditional_css, 'design' => design.php
├── design.php       Tokens (12 Farben hell/dunkel, 4 Schriften + System, Formen & Natur, Kopf/Fuß, Muster, Bewegung) + 6 Vorlagen
├── functions.php    Helfer nature_* (Kontakt, Zeiten, Saison, Menü + Fit-Schätzung, Abschnittskopf, JSON-LD, Landschaft, Monogramm)
├── seed.php         Startinhalte „Hofgut Wiesengrund (fiktiv)“ + 'after' → tools/demo-content.php
├── blocks/          hero, richtext, media_text, features, principles, specs, stats, steps, cards, team, quote, faq, cta, contact, downloads, video, map
├── templates/       layout, error, maintenance, offline; partials/: header, sheet, footer (Hügelkante), section – Marke (Option mark: empty), Sprachen, Öffnungszeiten, Video, Editor: Kern-Fragmente
├── assets/css/      site.css (_tokens, _base, _header, _footer, _hero) · overlay.css (am Ende von <body>) · opt-{header,footer,pagebg,dividers}-*.css
│                    (nur die gewählte Option) · b-*.css (je Block; b-hero.css nur für „Bildtafel“/„Große Aussage“, b-hero-{season,dates,form}.css nur für die gleichnamige Einstiegs-Variante) · data, media, calendar,
│                    dataform, sections, search (ersetzen die Kern-Stylesheets) · pages, editing, preview
├── assets/js/       site.js (≈ 4,7 KB) (2-Klick-Video: Skript des Kerns, resources/js/embed.js)
├── tools/           contrast.php (WCAG aller Vorlagen, --table) · patterns.php (SVG-Muster → opt-*.css) · demo.php + demo-content.php (--heroes: Musterseite „Hero-Varianten“)
├── lang/            en.php (Verwaltung) · site/en.php (feste Website-Texte lt())
├── docs/manual.php  Handbuch (Gestaltungsprinzip, Design/Kopf/Fuß, alle Blöcke)
├── package.json     @fontsource-variable/{fraunces,nunito-sans,source-sans-3}, @fontsource/young-serif (OFL 1.1), @expo-google-fonts/fraunces (TTF für App-Icons)
└── build.mjs        kopiert die Schriften (latin + latin-ext) und erzeugt css/font-{key}.css
```

Build: `cd tools && pnpm run build` → `public/kits/nature/{css,js,fonts}`. Muster neu erzeugen: `php kits/nature/tools/patterns.php`.

## Budgets (minifiziert, gemessen auf der Demo-Startseite)

| | Wert |
|---|---|
| site.css (renderblockierend, alle Seiten) | 24,1 KB |
| Optionen Standard (Papierstruktur 0,6 + Welle 1,1) | 1,7 KB |
| Schrift-CSS Standard (Fraunces 1,7 + Nunito Sans 0,9) | 2,6 KB |
| **Grundlage Startseite** | **≈ 28,4 KB** (< 30 KB) |
| Block-CSS der Demo-Startseite (Bildtafel, Merkmale, Text + Bild, Kennzahlen, Termine, Handlungsaufruf, Prosa) | ≈ 20,8 KB, nur dort |
| overlay.css (am Seitenende) | 5,5 KB |
| Einstieg „Jahreszeiten-Bühne“ / „Termine am Ast“ / „Papierkarte“ (nur auf Seiten mit der Variante; Papierkarte lädt zusätzlich dataform.css 5,0 KB) | 7,7 / 5,0 / 2,0 KB, kein zusätzliches JS (Öffnungsstatus: site.js) |
| JS Startseite | site.js 4,7 KB (< 8 KB) |
| Schriften (WOFF2, latin) | Fraunces SOFT 62 KB (+ Kursive 46 KB, nur wenn *Hervorhebung* vorkommt), Nunito Sans 31 KB; vorgeladen nur die aufrechten Dateien |
| Muster „Höhenlinien“ (nur wenn gewählt) | 4,8 KB |

## Farben und Kontrast

Alle sechs Vorlagen hell und dunkel nach WCAG 2.2 AA geprüft: `php kits/nature/tools/contrast.php [--table|-v]`
(27 Paare je Schema, inkl. abgeleiteter Mischfarben, Akzent/Erde als Bedienelement bzw. Markierung ≥ 3:1).

| Vorlage | Text/Papier | Nebentext/Sand | Akzent-Schrift/Papier | Button-Schrift/Akzent | Akzent/Papier | Erde/Papier | Waldnacht: Akzent |
|---|---|---|---|---|---|---|---|
| Moos (Standard) | 9,57 / 11,96 | 5,25 / 7,12 | 6,86 / 10,88 | 5,62 / 8,41 | 5,03 / 8,86 | 4,50 / 7,51 | 6,83 / 10,81 |
| Frühling | 9,52 / 12,02 | 5,39 / 7,09 | 6,63 / 11,08 | 5,17 / 8,75 | 4,75 / 9,05 | 4,69 / 7,80 | 6,97 / 10,59 |
| Sommer | 9,62 / 12,29 | 5,24 / 7,11 | 7,25 / 10,05 | 6,05 / 8,05 | 5,50 / 7,92 | 3,96 / 9,13 | 6,23 / 10,06 |
| Herbst | 9,81 / 12,33 | 5,37 / 7,02 | 6,73 / 9,05 | 6,17 / 7,01 | 5,35 / 6,85 | 4,30 / 8,84 | 6,94 / 10,18 |
| Winter | 9,54 / 12,33 | 5,37 / 7,25 | 8,29 / 10,13 | 7,48 / 7,73 | 6,76 / 7,89 | 5,99 / 6,90 | 6,65 / 10,51 |
| Waldnacht | 11,63 / 12,37 | 6,89 / 7,35 | 10,58 / 11,25 | 8,41 / 8,41 | 8,62 / 9,17 | 7,30 / 7,77 | 10,34 / 11,06 |

(hell / dunkel; Mindestwerte 4,5 für Text, 3 für Bedienelemente und Markierungen)

## Design-Tokens (design.php → `--n-*` bzw. Klassen am `<html>`)

| Gruppe | Tokens |
|---|---|
| Farben | accent `--n-a`, accent_ink `--n-a-ink`, on_accent `--n-a-on`, ink, text, muted, background, surface, panel, line, dark_section `--n-dark`, earth `--n-earth` – je hell/dunkel |
| Typografie | font_body, font_head, label_style `lb-{italic,caps}`, fs_min/fs_max, ratio, heading_weight, soft (Fraunces SOFT), heading_tracking |
| Formen & Natur | radius, buttons `btn-{soft,pill,outline}`, images `img-{soft,leaf,pebble}`, depth `depth-{flat,relief,deep}`, grid, space, wrap, markers `mk-{leaf,dot,plain}`, motifs `has-motifs`, season `sz-{auto,fruehling,sommer,herbst,winter,none}` |
| Kopf & Fuß | header `hdr-{bar,centered,split,index}`, header_sticky, footer `ft-{index,panel,simple}`, pagebg `pagebg-{plain,grain,contours,leaves}`, dividers `dv-{wave,hills,straight}` |
| Kopfbereich: Suche & Aktionen | `ha_*` aus `Core\HeaderActions::designGroup()` – Standard: Kontakt-Chip mit Öffnungsstatus + Textlink („Termine →“) (Handlungsaufruf, Suche, Anordnung, Kontakt-Chip, Sprache, Social, Anmelden; `header_actions()`) |
| Bewegung & Schema | motion `has-motion`, drift `has-drift`, dark `has-dark` |

## Blöcke

| Block | Varianten |
|---|---|
| hero | panel (Bildtafel: Bild oder Landschaft je Jahreszeit + Eckdaten „Bezeichnung: Wert“) · statement · split · compact · **season** (Jahreszeiten-Bühne: Panorama-SVG je Jahreszeit, automatisch nach Monat oder fest, + Hofschild „Jetzt geöffnet“ und Öffnungszeiten aus „Website“, Status im Browser berechnet) · **dates** (Termine am Ast: die nächsten 2–4 Termine einer Datentabelle als Packpapier-Anhänger, `Hero::dates`) · **form** (Papierkarte: Text + Pluspunkte, kompaktes öffentliches Formular `Hero::form` auf gestapeltem Papier mit Klebestreifen) – Muster: /erleben/hero-varianten |
| richtext | standard · article (Inhaltsverzeichnis, Lesezeit) |
| media_text | auto (abwechselnd) · right · left – Bild als Blatt/Kiesel |
| features | panel („Beet“: eine Fläche, feine Linien) · cards · list – Symbole im Kiesel |
| principles | list · grid · accordion (Grundsätze/Versprechen) |
| specs | table · image (Steckbrief) |
| stats | dials (Jahresringe: Samenpunkt-Skala, Füllstand, Holzringe) · row |
| steps | steps (Pfad aus Samenpunkten, Kieselknoten) · timeline |
| cards | product (freigestellt auf Fläche) · service · image |
| team | grid · list (Foto oder Monogramm im Kiesel) |
| quote | single (kursive Serif, Blattzeichen) · grid |
| faq | split · stacked (JSON-LD FAQPage) |
| cta | panel (Karte mit Blattmotiv) · band · minimal |
| contact | Adresse + Anfahrt, Öffnungszeiten mit „Jetzt geöffnet“, Saisonzeiten, Karte, Formular |
| video, downloads, map | Zwei-Klick-Video (Consent Kit, Poster über `Media::posterFor()`), PDF-Viewer, Karte ohne Cookies |

Kern-Blöcke im Kit-Stil: data_list, data_fields, data_form, calendar, upcoming, dials (`--dial-*` aus den Tokens),
gallery, slideshow, stack_cards; Such-Popover an der Lupe (CSS-Ankerpositionierung, sonst site.js), Besucher-Chat
(`cms_chat_launcher()`), `footer_links()` (z. B. Cookie-Einstellungen), Symbol-Sprite je Website (`icon()`).

## Website-Formular

Wie „essenz“/„fluid“ (Stammdaten, Adresse, Kontakt, Öffnungszeiten, Karte, Darstellung, Hinweisbalken, Social Media,
Recht, SEO) plus **Saisonzeiten** (`seasons`: Bezeichnung, Zeitraum, Zeiten/Hinweis – im Kontakt-Block und im Fuß)
und **Anfahrt** (`directions`). Strukturierte Daten zusätzlich als Laden, Ausflugsziel, Park, Campingplatz.

## Demo „Hofgut Wiesengrund (fiktiv)“

Neue Websites erhalten die Demo automatisch (`seed.php` → `tools/demo-content.php`); bestehende:
`CMS_SITE=<site> php kits/nature/tools/demo.php [--force|--remove] [--preset=herbst]`.

- Seiten: Start, Der Hof (Chronik, Grundsätze, Team), Hofladen (Karten, Datenliste, Preisliste, Detailseite
  `/sortiment/…`), Termine (Nächste Termine, Monatskalender mit Filter, Tabelle, Abonnieren) mit Unterseite
  „Gruppen & Schulklassen“ (öffentliches Formular mit Bedingung), Erleben (Musterseite des Style-Editors mit allen
  Blöcken) mit „Medien“ und „Journal“, Kontakt, Impressum, Datenschutz.
- Tabellen `hof_produkte`, `hof_termine` (wöchentliche Führung, zweiwöchentlicher Markttag, Kurse, ganztägiges
  Hoffest, Schließtag), `hof_gruppen`.
- Illustrationen (GD): acht Landschaften (Acker, Streuobstwiese, Blumenwiese, Teich, Waldrand, Hof, Abend, Bienenstand),
  drei Hochformate, sechs freigestellte Hofladen-Produkte – keine Fotos, keine Personen, keine Marken. Alle Namen,
  Preise, Termine und Stimmen sind erfunden und als fiktiv gekennzeichnet (Hinweisbalken). Kartenpunkt: geografischer
  Mittelpunkt Deutschlands (Wiese). Video: „Big Buck Bunny“ © Blender Foundation, CC BY 3.0 (Zwei-Klick).

## Mehrsprachigkeit

Feste Texte über `lt('…')`, Übersetzungen in `lang/site/{sprache}.php`; Verwaltungsbegriffe in `lang/en.php`.
Prüfen: `php bin/console i18n:missing en --site-texts --site=…`.

## Lizenzen

Fraunces, Nunito Sans, Young Serif, Source Sans 3 – SIL Open Font License 1.1 (`public/kits/nature/fonts/OFL-{key}.txt`).
Symbole: Phosphor (Kern-Sprite, MIT). Illustrationen und Muster: im Kit erzeugt, frei verwendbar.
