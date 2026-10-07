# Kit „editorial“

Typografisches Kit für inhaltsstarke Websites von KLXM Studio – Verband mit Vereinen, Kulturhaus, Stadtmagazin,
Bildungsträger, Stiftung. Starke Redaktions-Typografie (Serife für Schlagzeilen, humanistische Grotesk für Text, Mono für
Auszeichnungen), Titelkopf mit Datumszeile, Aufmacher, Nachrichtenstrom, „Kurz notiert“, Artikel mit Inhaltsverzeichnis,
Lesefortschritt und Teilen ohne Tracker, Nachtausgabe (dunkles Schema). Cookiefrei ab Werk, keine externen Anfragen, keine Inline-Skripte/-Styles.

Blocktypen, Feldnamen, Hintergründe und Website sind mit „basis“ verträglich (hero, richtext, text_image, features,
stats, quote, faq, cta, video, downloads, logos, contact, map; white/muted/accent/dark; org_name, tagline …):
eine Website kann ohne Inhaltsverlust zwischen beiden Kits wechseln (getestet mit verein-b und geteilten Tabellen).

## Struktur

```
kits/editorial/
├── theme.php          Blöcke, Website (+ „Ausgabe“), project, seo/jsonld, conditional_css, Datenliste mit Redaktions-Darstellungen
├── design.php         Design-Tokens (Farben hell/dunkel, 3 Schriften, Skala, Lesebreite, Dachzeilen, Initiale, Raster, Bilder, Linien, Kopf), 7 Voreinstellungen
├── functions.php      Helfer editorial_* (Menü, Ressorts, Datumszeile, Inhaltsverzeichnis, Teilen, Lesezeit, Symbole, JSON-LD)
├── seed.php           Startinhalte + 'after' → Demo-Ausgabe (tools/demo-content.php)
├── blocks/            hero (Aufmacher/Plakat/Titelbild/Ressortkopf/Titelgeschichte/Aktuell/Stimme), richtext, text_image, figure, features, teasers, stats, quote,
│                      faq, cta (Band/Kasten/Newsletter), video, downloads, logos, contact, map, article (Detailseite), data_list (Kern überschrieben)
├── templates/         layout, error („vergriffen“), maintenance, offline, partials/ (header, ressorts, brand, footer, toolbar = Kern-Hook …)
├── assets/css/        site.css (immer) · head-{masthead|compact|split|ressorts}.css (je Kopf) · hero, hero-x (nur Titelgeschichte/Aktuell/Stimme), story, blocks,
│                      data, calendar, dataform, media, search (nur wo nötig) · preview.css (Style-Editor „Dunkel“)
├── assets/js/         site.js (Menü-Blatt, Aufklappmenüs, Datumszeile, Suche; 2,8 KB) · blocks.js (Video, Link kopieren, Inhaltsverzeichnis; 1,8 KB)
├── tools/             contrast.php (WCAG-Prüfung aller Voreinstellungen) · demo.php + demo-content.php (Demo-Ausgabe)
├── lang/              en.php (Verwaltung) · site/en.php (feste Website-Texte lt())
├── docs/manual.php    Handbuch-Kapitel (Design, Redaktion)
└── build.mjs          TTF für den App-Icon-Generator; Webfonts: design.fonts → 'fontsource' (Schriften-Manager, fonts:sync)
```

Build: `cd tools && pnpm run build` → `public/assets/kits/editorial/{css,js}`.

**Budgets** (minifiziert, Startseite): site.css 22,1 KB + Kopf 0,3–2,4 KB + drei Schriften je 0,8–1,5 KB + Design-Datei 0,8 KB
→ 27,9–29,7 KB. JS: site.js 2,8 KB (+ blocks.js 1,8 KB nur bei Video/Artikel/Text). Bedingt: hero.css 4,4 KB (jeder Aufmacher),
hero-x.css 7,6 KB (nur bei Titelgeschichte, Aktuell, Stimme – kein JS).

## Aufmacher (Block „hero“) – Varianten

| Variante | Schlüssel | Inhalt | Eigene Felder |
|---|---|---|---|
| Aufmacher | `split` | Text und Bild im Seitenraster (Raster aus Design: klassisch/asymmetrisch/zentriert) | image, ratio, caption |
| Plakat | `centered` | Schlagzeile zentriert, Bild 21:9 darunter | image, caption |
| Titelbild | `cover` | Bild vollflächig, Text auf dunkler Verlaufsfläche | image, caption |
| Ressortkopf | `compact` | große Rubrik-Überschrift, Unterseiten als Reiter | subnav |
| Titelgeschichte | `issue` | Ausgabe-Zeile (doppelte Linie), Bild 4:5, Schlagzeile, Vorspann, Autorzeile, „Außerdem in dieser Ausgabe“ mit nummerierten Anrissen | issue, issue_more_title, issue_more (Rubrik, Titel, Zusatz, Link; max. 3), image, caption |
| Aktuell | `agenda` | Schlagzeile + nächste Termine (Kalender-Tabelle) bzw. neueste Einträge in Zeitungsspalten: große Tagesziffer, Monat in Mono, Linien | dates_table, dates_limit (2–4), dates_title, dates_more_label/-link (Core\Blocks\Hero::fields('dates')) |
| Stimme | `voice` | Schlagzeile links, großes Zitat rechts, Initialen statt Porträt, optional Sternebewertung mit Quelle | quote, quote_name, quote_role, rating, rating_note (Hero::fields('quote')) |

Die Felder erscheinen in der Seitenleiste nur bei ihrer Variante (`'variants'`); „Wann verwenden?“ steht in `variant_help` (Handbuch).
Musterseite: `/hero-varianten` (Unterseite der Startseite) – `CMS_SITE=editorial php kits/editorial/tools/demo.php --heroes`.

## Design-Tokens

| Gruppe | Token → Ausgabe |
|---|---|
| Farben | accent `--e-a`, accent_strong, on_accent, ink, text (≥ 7:1), muted, background (Papier), surface, line, dark_section `--e-night` – je hell/dunkel |
| Typografie | font_display, font_body, font_mono, base_size, scale `--e-ratio` (1,2–1,414; Stufen per calc), heading_weight, heading_tracking, measure `--e-measure`, kicker → `kick-*`, dropcap → `has-dropcap` |
| Raster, Bilder, Linien | grid → `grid-classic/asym/center`, images → `img-color/duotone/gray`, rules → `rules-hair/double/bold`, radius, spacing |
| Kopf & Navigation | header → `head-masthead/compact/split/ressorts` (+ css/head-*.css), nav_sticky, dateline, footer → `foot-sitemap/simple` |
| Kopfbereich: Suche & Aktionen | `ha_*` aus `Core\HeaderActions::designGroup()` – Standard: Befehlsfeld ⌘K + Newsletter-Textlink (Handlungsaufruf, Suche, Anordnung, Kontakt-Chip, Sprache, Social, Anmelden; `header_actions()`) |
| Farbschema | dark → `has-dark` (Nachtausgabe; Vorschau: `html.is-dark`) |

Neue Farbe/Voreinstellung: design.php ergänzen, `php kits/editorial/tools/contrast.php` ausführen (Exit-Code 1 bei Verstößen).

## Moderne Technik (progressiv)

View Transitions beim Seitenwechsel (`@view-transition`, nie bei „Bewegung reduzieren“), Lesefortschritt und Bild-Einblendung per
scroll-driven animations (`animation-timeline`), `:has()` für Fokusrahmen ganzer Anreißer, `text-wrap: balance/pretty`, `initial-letter`
für die Initiale, CSS Anchor Positioning für das Such-Popover. Ohne Unterstützung bleibt alles lesbar und bedienbar.

## Demo

`CMS_SITE=… php kits/editorial/tools/demo.php [--force|--remove|--heroes] [--preset=kulturhaus]` – „Kulturnetz Musterland (Demo)“: Tabellen
`ed_news` (Rubriken, Autor = Person, NewsArticle), `ed_termine` (Kalender, Wiederholungen, iCal), `ed_personen` (Person), `ed_newsletter`
und `ed_mitglied` (öffentliche Formulare mit Bedingungen, IBAN, Gruppe), Rubrikseiten, Detailvorlagen, 22 erzeugte Platzhalterbilder
(GD + Playfair Display, OFL) – keine Fotos, keine echten Personen, Firmen oder Adressen.

## Lizenzen

Newsreader, Fraunces, Playfair Display, Literata, Source Serif 4, DM Serif Display, Instrument Serif, Source Sans 3, Public Sans,
Work Sans, Libre Franklin, IBM Plex Sans/Mono, Inter, JetBrains Mono – SIL Open Font License 1.1 (installiert vom Schriften-Manager, Lizenz je Schrift in `public/assets/fonts/installed/{id}/LICENSE.txt`).
Symbole: Phosphor (Kern). Platzhalterbilder: automatisch erzeugt, frei verwendbar.
