# Kit „modern“

Zeitgemäß, selbstbewusst, klar – ein Business-Kit für Studios, Beratungen und Unternehmen in KLXM Studio.
Große Grotesk-Typografie, viel Weißraum, kräftige Farbflächen, ein Bento-Raster und präzise Karten mit feiner Kante.
Bedienelemente mit 8 px Radius (keine Pillen), Akzentsystem mit geprüftem Kontrast. CSP-fest (keine Inline-Skripte/-Styles),
keine externen Anfragen, cookiefrei ab Werk, Schriften lokal, WCAG 2.2 AA, MIT.

Bewusst anders als die übrigen Kits: kein Rams-Minimalismus (essenz), keine Serifen-Redaktion (editorial), nicht
breakpointlos (fluid) – „modern“ ordnet Layouts in klaren Stufen (Media-Queries bei 30, 40, 48 und 64 em).

## Formensprache

| Element | Umsetzung |
|---|---|
| Schrift | Space Grotesk (Überschriften, eng gesetzt, −3,5/100 em) + Plus Jakarta Sans (Fließtext); beide variabel, lokal (Fontsource, OFL 1.1) |
| Stufen | Grundgröße × Verhältnis^n; bis 48 em flacheres Verhältnis, ab 64 em volles (Style-Editor: 1,15–1,45) |
| Farben | Akzent (Links, Symbole, Abschnitt „Akzentfarbe“), **Blockfarbe** (kräftige Flächen: Bento-Kacheln, Zitate, Handlungsaufrufe, Unterstrich der *Hervorhebung*), Nachtblau für dunkle Flächen |
| Buttons | gefüllt in Überschriftenfarbe, Hover in Akzentfarbe; Varianten Kontur und „mit Pfeil“ – immer 8 px Radius |
| Karten | feine Kante (Standard), Fläche oder Kante mit Schatten; Radius einstellbar (Standard 14 px) |
| Dachzeilen | Farbquadrat, nummeriert (01, 02 … per CSS-Zähler) oder schlicht |
| Bewegung | Einblenden beim Scrollen (gestaffelt), Mosaik steigt auf, Kacheln heben sich – nie bei „Bewegung reduzieren“ |
| Dunkel | per `prefers-color-scheme` (Design → „Dunkles Farbschema“); Buttons wechseln auf den Akzent |

## Struktur

```
themes/modern/
├── theme.php        19 Blöcke, Website (Schlüssel wie „fluid“/„essenz“), conditional_css, 'design' => design.php
├── design.php       Tokens (11 Farben hell/dunkel, 4 Schriften + System, Stufen, Radius, Buttons, Karten, Dachzeilen,
│                    4 Navigationen, 3 Fußbereiche) + 4 Vorlagen
├── functions.php    Helfer modern_* (Kontakt, Öffnungszeiten inkl. „Jetzt geöffnet“, Menü, Abschnittskopf, Initialen, JSON-LD)
├── seed.php         zentrale Angaben + Rechtsseiten; 'after' → tools/demo-content.php (Studio Nordlicht)
├── blocks/          hero, richtext, media_text, features, bento, cards, logos, stats, quote, steps, team, pricing, faq,
│                    cta, tabs, video, contact, downloads, map
├── templates/       layout, error, maintenance, offline; partials/: header (4 Navigationen), sheet (Mobilmenü), brand,
│                    footer, hours, langswitch, section, video-embed (Zwei-Klick + Consent Kit), editor, toolbar
├── assets/css/      site.css (_tokens, _base, _header, _footer, _hero) · overlay.css (Aufklappmenüs, Such-Popover,
│                    Seitenblatt – am Ende von <body>) · nav-extended.css · hero-x.css · hero-product|marquee|figures.css · b-*.css (je Block) · prose.css ·
│                    data, media, calendar, dataform, sections, search (ersetzen die Kern-Stylesheets) · pages, editing, preview
├── assets/js/       site.js · video.js (nur mit Video-Block) · tabs.js (nur mit Reitern)
├── tools/           contrast.php (WCAG-Prüfung aller Vorlagen) · demo.php + demo-content.php (Demo mit erzeugten Bildern)
├── lang/            en.php (Verwaltung) · site/en.php (feste Website-Texte lt())
├── docs/manual.php  Handbuch (Demo, Design & Navigation, Tipps, alle Blöcke)
├── package.json     @fontsource-variable/{plus-jakarta-sans,space-grotesk,inter-tight,manrope}, @expo-google-fonts/space-grotesk (TTF für App-Icons)
└── build.mjs        kopiert die variablen Schriften (latin + latin-ext) und erzeugt css/font-{key}.css
```

Build: `cd tools && pnpm run build` → `public/themes/modern/{css,js,fonts}` (installiert die Kit-Pakete bei Bedarf selbst).

## Blöcke

| Block | Varianten |
|---|---|
| hero | split (Text + Bild auf versetzter Blockfarben-Fläche, Kennzahl-Karte) · statement (Schriftzug über die volle Breite, Bild 21:9) · mosaic (drei Bilder + Kennzahl-Kachel) · compact · **product** (Produktbühne: Hauptbild mit rundem Preis-Sticker und Kennzeichnung, dunkle Datenblatt-Karte aus „Bezeichnung: Wert“-Zeilen, zwei Detailbilder) · **marquee** (Typo: Riesenschrift ohne Bild + schräge Laufzeile in Blockfarbe, Pause-Schaltfläche, steht bei „Bewegung reduzieren“) · **figures** (Aussage + 3–4 Kennzahl-Kacheln in Blockfarbe/Dunkel/Akzent/getönt mit den Kern-Rundinstrumenten) — CSS je Variante nur bei Bedarf: `css/hero-product.css`, `css/hero-marquee.css`, `css/hero-figures.css` (+ Kern-`dials.css` über `uses`); Musterseite `/baukasten/hero-varianten` (`tools/demo.php --heroes`) |
| bento | 12-Spalten-Raster: normal, breit, hoch, groß, ganze Breite × Karte, Akzent hell, Blockfarbe, Akzent, dunkel, Bild; kurze Zahl als Dachzeile erscheint groß |
| features | cards · icons · list · numbered |
| cards | image · overlay · horizontal |
| stats | row (Zahlen mit Blockfarben-Unterstrich) · cards · split — Rundinstrumente: Kern-Block „Kennzahlen mit Skala“ (dials, im Kit-Stil über `--dial-*`) |
| team | grid (Porträt 4:5, ohne Foto Initialen auf Farbfläche) · list |
| pricing („Angebote“) | cards · table (Vergleich, ja/nein mit Text für Screenreader) |
| steps | numbers · timeline · process |
| quote | single (Anführungszeichen in Blockfarbe) · grid |
| cta | band · box (Blockfarbe) · split · big |
| richtext, media_text, faq, tabs, logos, video, contact, downloads, map | wie „fluid“, im Stil von „modern“ |

Kern-Blöcke im Kit-Stil: data_list (Karten 2/3/4 Spalten, Liste, kompakt, Tabelle), data_fields (Detailseiten), data_form,
calendar, upcoming, gallery (feste Spaltenzahl je Stufe), slideshow, stack_cards, dials, Suche (Ergebnisseite + Popover an der
Lupe per CSS-Ankerpositionierung, sonst site.js), Besucher-Chat (`cms_chat_launcher()`), `footer_links()` (z. B.
„Cookie-Einstellungen“), Symbole über das Sprite der Website (`icon()`).

## Navigation (Design → Navigation)

`nav-modern` (schwebende Leiste) · `nav-classic` (volle Breite) · `nav-minimal` (nur Menü-Schaltfläche) · `nav-extended`
(Kontaktzeile mit Telefon, E-Mail, „Jetzt geöffnet“, Sprache). Bis 64 em öffnet die Menü-Schaltfläche ein Seitenblatt
(HTML-popover, ohne JavaScript bedienbar; site.js ergänzt Fokusfalle, inerten Rest und aria-expanded). Passt das Menü trotz
Breite nicht in die Leiste, schaltet site.js auf die Schaltfläche um. Aufklappmenüs: `<details>`, Pfeiltasten, Pos1/Ende, Escape.

## Vorlagen (alle hell + dunkel WCAG 2.2 AA)

Nordlicht (Standard: Nachtblau, Polarlicht-Grün, Limette) · Kobalt (Blau + Sonnengelb, Inter Tight, Kontur, klassisch) ·
Koralle (Korallenrot auf Creme, Manrope, Schatten-Karten, Kontaktzeile) · Graphit (Schwarz-Weiß, plakativ, minimal).
Prüfen: `php themes/modern/tools/contrast.php` (Exit-Code 1 bei Verstößen; prüft auch abgeleitete Mischfarben).

## Budgets (minifiziert, gemessen an der Demo-Startseite)

| | Wert |
|---|---|
| site.css (renderblockierend, alle Seiten) | ≈ 23,7 KB |
| Schrift-CSS Standard (Plus Jakarta Sans + Space Grotesk) | 2 × ≈ 1 KB |
| Design-Datei | nur bei Abweichung vom Standard, ≈ 1 KB |
| **Grundlage Startseite** | **≈ 25,7 KB** (< 30 KB) |
| Block-CSS Startseite (hero-x, bento, logos, cards, stats, quote, cta) | ≈ 11,5 KB, nur dort |
| Einstieg „Produkt“ / „Typo“ / „Kennzahlen“ (bedingt, je Variante) | hero-product.css ≈ 4,1 KB · hero-marquee.css ≈ 3,8 KB (+ Kern-js/hero.mjs ≈ 1,3 KB) · hero-figures.css ≈ 4,0 KB (+ Kern-css/dials.css, js/dials.mjs) |
| overlay.css (am Seitenende, nicht renderblockierend) | ≈ 4,8 KB |
| CSS Startseite gesamt | ≈ 42 KB, gzip ≈ 13,4 KB |
| JS Startseite | site.js ≈ 4,7 KB (< 8 KB) |
| Schriften (WOFF2, variabel, latin) | Plus Jakarta Sans ≈ 27 KB, Space Grotesk ≈ 22 KB (beide vorgeladen) |

## Demo „Studio Nordlicht – Produktdesign & Beratung (fiktiv)“

Neue Websites erhalten die Demo automatisch (seed.php → `after`); bestehende Websites:
`CMS_SITE=<site> php themes/modern/tools/demo.php [--force|--remove] [--preset=kobalt]` (dann unterhalb von `/nordlicht`).
Seiten: Start (Mosaik, Laufband, Bento, Karten, Zahlen, Zitat), Leistungen, Projekte (Datentabelle `nordlicht_projekte` mit
Detailseiten unter `/projekte/…`, Tabelle, Galerie), Studio (Team ohne Fotos, Rundinstrumente, Zeitleiste), Journal (Artikel,
Zwei-Klick-Video, PDF), Kontakt (öffentliches Formular `nordlicht_anfragen` mit Bedingung, Öffnungszeiten, Karte) und
„Baukasten“ (alle übrigen Varianten; Musterseite des Style-Editors). Bilder werden prozedural mit GD erzeugt
(Polarlicht-Verläufe, gezeichnete Produkt-Entwürfe, Formstudien) – keine Fotos, keine Personen, keine Marken. Alle Namen,
Auftraggeber, Zahlen und Stimmen sind erfunden; Kartenpunkt: geografischer Mittelpunkt Deutschlands. Video: „Big Buck
Bunny“ © Blender Foundation, CC BY 3.0 (Zwei-Klick).

## Mehrsprachigkeit

Feste Texte über `lt('…')`, Übersetzungen in `lang/site/{sprache}.php`; Verwaltungsbegriffe in `lang/en.php`.
Prüfen: `php bin/console i18n:missing en --site-texts --site=…`.

## Lizenzen

Plus Jakarta Sans, Space Grotesk, Inter Tight, Manrope – SIL Open Font License 1.1 (`public/themes/modern/fonts/OFL-{key}.txt`,
TTF für App-Icons: `themes/modern/fonts/OFL.txt`). Symbole: Phosphor (Kern-Sprite, MIT). Beispielbilder: automatisch erzeugt, frei verwendbar.
