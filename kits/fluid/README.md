# Kit „fluid“

Breakpointloses (intrinsisches) Kit für KLXM Studio – für viele Projektarten (Kanzlei, Handwerk, Praxis, Agentur,
Verein, Restaurant, Tech-Startup, Kultur …) über **Design-Tokens** und **neun geprüfte Vorlagen**. Unterstützt alle
Funktionen des Kerns (Datenlisten, Formulare, Kalender, Karte, Galerie, Slider, Stapelkarten, Video mit Untertiteln,
Downloads/PDF-Viewer, Suche, Mehrsprachigkeit, Hinweisbalken, JSON-LD, Style-Editor, Inline-Bearbeitung).
CSP-fest (keine Inline-Skripte/-Styles), keine externen Anfragen, cookiefrei ab Werk, WCAG 2.2 AA.

## Breakpointlos – das Prinzip

Kein Layout fragt die Bildschirmbreite. Jede Komponente richtet sich nach dem Platz, den sie **selbst** hat –
deshalb funktioniert alles auch in schmalen Spalten, in der Seitenleiste, in der Mobil-Vorschau des Style-Editors
oder auf sehr breiten Bildschirmen.

| Technik | Wo |
|---|---|
| **Fließende Stufen** (Utopia-Prinzip): `--f-t0 … --f-t6` = `clamp(min, min + (max − min) · p, max)`; `p` wächst zwischen `--f-vw-min` und `--f-vw-max` von 0 auf 1; die Stufen entstehen per `pow()` aus Grundgröße und Verhältnis (auf kleinen Bildschirmen automatisch flacher) | alle Schriftgrößen, Abstände (`--f-s-*`), Abschnittsabstand `--f-sec` |
| **Container-Einheiten** `cqi`: große Überschriften `max(t3, min(t6, 13cqi))` – füllen ihren Platz, laufen nie über | Einstieg, Handlungsaufruf, Fußbereich „Großer Schriftzug“, Kennzahlen, Bento-Zahlen |
| **Container-relativer Seitenrand** `--f-gutter: clamp(1rem, .45rem + 2.6cqi, 3rem)` – jeder Abschnitt ist ein Container | `.wrap` |
| **auto-fit/auto-fill-Raster** `repeat(auto-fit, minmax(min(100%, var(--min)), 1fr))` – statt Spaltenzahl wählt die Redaktion eine Mindestbreite (`min-s/m/l`) | Merkmale, Karten, Pakete, Kennzahlen, Datenlisten, Galerie, Formularfelder, Fußspalten |
| **Switcher / Sidebar** (Every Layout): Flex-Umbruch mit `flex-basis` + `min-inline-size` | Einstieg, Text + Bild, Split-Screen, FAQ, Artikel mit Inhaltsverzeichnis, Kontakt, Reiter links, Seitenleiste des Kopfes |
| **Container-Queries** (`container-type: inline-size` + `@container`) | Kopf/Menü, Karten „Quer“, Zeitleiste, Prozess-Pfeile, Scrollytelling, Bento-Spannweiten, Liste mit Bild, Kalender, Slider, Stapelkarten, Lightbox, Überlappung |
| **CSS-Spalten** mit `column-width` | Mosaik-Galerie, Zitat-Mauerwerk, Zeitungssatz |
| `aspect-ratio`, logische Eigenschaften, `:has()`, `scroll-snap`, `popover`, `<details name>` | Bildrahmen, Reels, Scroll-Sperre, Menü, Akkordeons |

**Navigation ohne Breakpoint:** Der Kopf ist ein Container (`hdr`). `fluid_nav_fit()` schätzt serverseitig, wie
breit Logo + Menü + Werkzeuge in einer Zeile sind (in rem, 6-rem-Schritte → Klasse `fit-NN`). Eine Container-Query
`@container hdr (min-width: NNrem)` zeigt das Menü in der Leiste, sonst die Menü-Schaltfläche. `site.js` prüft per
ResizeObserver, ob es wirklich passt (`.is-overflow` erzwingt sonst die Schaltfläche). Die Schaltfläche öffnet ein
**Seitenblatt** als HTML-`popover` (Escape/Klick daneben ohne JavaScript, Scroll-Sperre per `html:has(.mnav:popover-open)`);
`site.js` ergänzt Fokusfalle, `inert` für den Rest, Fokus beim Öffnen und zurück zur Schaltfläche. Unterseiten im Blatt
als Akkordeon (`<details name>`), dazu Suche, Direktkontakt, Sprache und Button. In der Leiste: Aufklappmenüs mit
Pfeiltasten, Pos1/Ende, Escape, Klick daneben. Variante **Seitenleiste**: `.page` bricht per Flex-Umbruch um (Kopf 15,5 rem
links, solange der Inhalt ≥ 72 % behält); die Container-Query auf den Kopf (≤ 18 rem) stellt das Menü senkrecht.

**Erlaubte Media-Queries** (Audit: `grep -n "@media" assets/css/*.css`): nur `prefers-color-scheme` (dunkles Schema),
`prefers-reduced-motion` (Animationen, Laufband, Stapelkarten, weiches Scrollen), `forced-colors` (Kalender, Suche)
und `print`. JavaScript nutzt `matchMedia` nur für „Bewegung reduzieren“.

## Struktur

```
kits/fluid/
├── theme.php        Blöcke (19 eigene), Website, project, seo/jsonld, conditional_css, 'design' => design.php
├── design.php       Tokens (Farben hell/dunkel, 9 Schriften, fließende Stufen, Form, Kopf/Fuß, Bewegung) + 9 Vorlagen
├── functions.php    Helfer fluid_* (Kontakt, Zeiten, Menü + Fit-Schätzung, Abschnittskopf, Bild, Inhaltsverzeichnis, JSON-LD)
├── seed.php         Startinhalte „Studio Beispiel (Demo)“ + 'after' → Showcase (tools/demo-content.php)
├── blocks/          Renderer je Block
├── templates/       layout, error, maintenance, offline; partials/: header, sheet (Seitenblatt), brand, footer, langswitch,
│                    section (Öffnungszeiten, Video, Editor, Werkzeugleiste: Kern-Fragmente, app/Views/fragments)
├── assets/css/      site.css (_tokens, _base, _header, _footer, _hero) · overlay.css (Aufklappmenüs, Such-Popover, Seitenblatt –
│                    am Ende von <body>, nicht renderblockierend) · opt-{token}-{wert}.css (nur gewählte Design-Optionen) ·
│                    b-*.css / hero-x.css / prose.css / reel.css (je Block bzw. Variante) · data, media, calendar, dataform,
│                    sections, search (ersetzen die Kern-Stylesheets – intrinsisch, ohne Breiten-Queries) · pages.css · editing.css · preview.css
├── assets/js/       site.js (≈ 4,1 KB) · tabs.js, reel.js, scrolly.js (je Block, < 1,2 KB; Video-Skript kommt aus dem Kern)
├── tools/           contrast.php (WCAG-Prüfung aller Vorlagen) · demo.php + demo-content.php (Showcase)
├── lang/            en.php (Verwaltung) · site/en.php (feste Website-Texte lt())
├── docs/manual.php  Handbuch (Kapitel: Breakpointlos, Design/Kopf/Fuß, Seiten-Tipps, Beschreibung aller Blöcke)
├── package.json     @fontsource-variable/{inter,instrument-sans,bricolage-grotesque,dm-sans,space-grotesk,fraunces,newsreader,jetbrains-mono}, @fontsource/instrument-serif
└── build.mjs        kopiert die variablen Schriften (latin + latin-ext) und erzeugt css/font-{key}.css
```

Build: `cd tools && pnpm run build` → `public/kits/fluid/{css,js,fonts}`.

## Budgets (minifiziert, gemessen)

| | Standard | Agentur | Kultur (Seitenleiste) |
|---|---|---|---|
| site.css | 21,7 KB | 21,7 KB | 21,7 KB |
| Design-Optionen (opt-*) | – | 1,8 KB | 2,8 KB |
| Schrift-CSS | 0,9 KB | 3,0 KB | 3,7 KB |
| Design-Datei | – (Standard) | 1,0 KB | 0,9 KB |
| **Grundlage (renderblockierend)** | **≈ 22,6 KB** | **≈ 27,5 KB** | **≈ 29,1 KB** |
| overlay.css (am Seitenende) | 4,6 KB | 4,6 KB | 4,6 KB |
| JS Startseite | site.js 4,1 + reel.js 0,8 KB | gleich | gleich |

Blöcke laden ihr CSS nur, wo sie stehen (Startseite der Demo zusätzlich ≈ 18 KB für 10 Blocktypen).
Einstiegs-Varianten (nur bei Verwendung): hero-x.css 4,1 KB · hx-scale.css 2,3 KB · hx-collage.css 3,1 KB · hx-compare.css 3,2 KB
(+ Kern-Skript hero.mjs ≈ 1,3 KB nur bei Vorher/Nachher). Musterseite: `/showcase/hero-varianten`
(`CMS_SITE=<site> php kits/fluid/tools/demo.php --heroes` legt sie neu an, inkl. erzeugtem Vorher/Nachher-Bildpaar).

## Design-Tokens (design.php → CSS-Variablen `--f-*` bzw. Klassen am `<html>`)

| Gruppe | Tokens |
|---|---|
| Farben | accent `--f-a`, accent_strong, on_accent, highlight `--f-a2` (Zweitfarbe), ink, text, muted, background, surface, line, dark_section – je hell/dunkel mit Kontrastprüfung |
| Typografie | font_body, font_head, font_mono, label_font (Dachzeilen), fs_min/fs_max (Grundschrift klein/groß), ratio (1,125–1,6), vw_min/vw_max (Fließbereich), heading_weight (300–900 stufenlos), heading_tracking |
| Form & Raum | radius, buttons `btn-{solid,pill,outline,soft,sharp}`, cards `cards-{flat,outlined,elevated,glass}`, shadow (none/soft/crisp/layered), density, space, wrap (Inhaltsbreite), eyebrow `eb-{line,pill,dot,plain}` |
| Kopf & Fuß | header `hdr-{inline,centered,split,floating,rail}`, header_sticky, footer `ft-{columns,simple,statement}`, pagebg `pagebg-{plain,grid,dots,glow}` |
| Kopfbereich: Suche & Aktionen | `ha_*` aus `Core\HeaderActions::designGroup()` – Standard: Suchfeld + Textlink („Projekt besprechen →“) (Handlungsaufruf, Suche, Anordnung, Kontakt-Chip, Sprache, Social, Anmelden; `header_actions()`) |
| Bewegung & Schema | motion `has-motion` (Einblenden, Laufband – nie bei „Bewegung reduzieren“), dark `has-dark` |

Abgeleitet (color-mix): `--f-a-soft`, `--f-surface-2`, `--f-line-strong`, Rollen je Fläche (`.bg-accent`, `.bg-dark`,
`.on-media`, Hintergrundbild mit Abdunkelung). Ist der Hintergrund schon im hellen Schema dunkel (Vorlage „Tech-Startup“),
setzt `fluid_html_class()` `is-darkbase` (color-scheme dark). Serifen-Überschriften → `head-serif` (Hervorhebung kursiv).
**Neue Vorlage:** Werte in design.php ergänzen, `php kits/fluid/tools/contrast.php` ausführen (Exit-Code 1 bei Verstößen).

### Vorlagen
Fluid Standard · Kanzlei (Newsreader, eckig, zentriert) · Handwerk (Bricolage, Pille, schwebend, Punktraster) ·
Praxis (Salbei, Instrument Sans, Schatten, Verlauf) · Agentur (Verhältnis 1,45, Violett/Limette, geteilt, Raster) ·
Verein (Blau/Gelb, DM Sans 800) · Restaurant (Fraunces, Creme, Kontur) · Tech-Startup (dunkel, Space Grotesk, Glas,
Mono-Etiketten) · Kultur (Instrument Serif 1,5, Signalrot, Seitenleiste). Alle AA hell + dunkel.

## Blöcke und Varianten

| Block | Varianten |
|---|---|
| hero | split · centered · fullbleed (Bild/MP4 mit Abdunkelung, Pause-Schaltfläche) · cards (gestapelte Karten) · type (großer Schriftzug) · compact · **scale** (Fließende Skala: Überschrift in cqi ihrer Spalte, ab 48 rem Platz Seitenspalte + Stufenleiste `scale_items`, deren Einträge je eine Schriftstufe kleiner werden) · **collage** (Bild + `gallery`, 1–5 Bilder; unter 52 rem auto-fit-Kacheln, darüber 12er-Raster je Anzahl mit schwebender Textkarte) · **compare** (Vorher/Nachher, `Core\Blocks\Hero::compare`, nativer Regler mit Pfeiltasten, ohne Skript nebeneinander) – Stile in hero-x.css bzw. hx-scale/hx-collage/hx-compare.css, jeweils nur auf Seiten mit der Variante |
| richtext | standard · article (Lesebreite, Lesezeit, Initiale, Inhaltsverzeichnis) · columns (Zeitungssatz) |
| media_text | auto (abwechselnd) · right · left · split (Split-Screen, optional bildschirmhoch) · overlap |
| features | cards · icons · list · numbered – Mindestbreite statt Spalten |
| bento | Kacheln normal/breit/hoch/groß × Karte/Akzent hell/Zweitfarbe/Akzent/dunkel/Bild |
| cards | image · overlay · horizontal · reel |
| logos | grid · marquee (reines CSS) |
| stats | row · cards · split |
| quote | single (Pull-Quote) · grid (Mauerwerk) · reel |
| steps | numbers · timeline · process |
| pricing | cards · table (Vergleich, erste Spalte fix) |
| faq | split · stacked (JSON-LD FAQPage) |
| cta | band · box · split · big |
| tabs | top · side |
| scrolly | left · right (Scrollytelling) |
| video | wide · text · cinema (Zwei-Klick, MP4 mit Untertiteln/Transkript) |
| contact | Angaben + Öffnungszeiten + Karte + optional Formular |
| downloads, map | – |

Kern-Blöcke im Kit-Stil (eigene Stylesheets): data_list (Karten/Liste/kompakt/Tabelle; 1:1- oder 3:4-Bilder in Listen →
Porträts; `dl-item--featured` für hervorgehobene Einträge geteilter Tabellen), data_fields, data_form (Bedingungen, IBAN,
Gruppen, Fehler/Erfolg), calendar, upcoming, gallery (Raster/Mosaik/Zeilen + Lightbox), slideshow, stack_cards, Suche
(Ergebnisseite + Popover), Abschnitte mit Vollbild/Hintergrundbild. Inline-Bearbeitung (`$b->edit()`,
`entry_edit_attr` über den Kern), Werkzeugleiste ausschließlich über `cms_toolbar()`.

## Showcase (Demo)

`CMS_SITE=<site> php kits/fluid/tools/demo.php [--force|--remove] [--preset=agentur]` legt `/showcase` an (Übersicht,
Abschnitte, Inhalte, Medien, Daten & Termine, Formulare & Kontakt, Magazin) – mit erzeugten abstrakten Bildern und
Monogramm-Avataren (keine Personen), Tabellen `showcase_projekte` (Detailseite), `showcase_termine` (Wiederholung,
ganztägig, Kategorien), `showcase_team`, `showcase_rueckruf`, `showcase_antrag` (öffentliche Formulare). Alle Namen sind
erfunden. Kartenpunkt: geografischer Mittelpunkt Deutschlands (Wiese). Video: „Big Buck Bunny“ © Blender Foundation,
CC BY 3.0 (YouTube, Zwei-Klick). Die Übersicht ist die Musterseite des Style-Editors (`design.sample`).

## Kundentheme ableiten

```
php bin/console theme:create kunde fluid     # Kopie, Präfix fluid_ → kunde_
cd tools && pnpm run build
php bin/console site:create kunde www.kunde.de kunde
```
Danach: Label in theme.php, Standardwerte/Vorlagen in design.php (Standardwerte auch in `_tokens.css` `:root` und im
Dunkel-Block anpassen), seed.php, Blöcke ergänzen/entfernen, docs/manual.php.

## Mehrsprachigkeit

Feste Texte über `lt('…')` (nur einfache Anführungszeichen, fester Text), Übersetzungen in `lang/site/{sprache}.php`.
Prüfen: `php bin/console i18n:missing en --site-texts --site=…`. Verwaltungsbegriffe: `lang/en.php`.

## Lizenzen

Inter, Instrument Sans, Bricolage Grotesque, DM Sans, Space Grotesk, Fraunces, Newsreader, Instrument Serif,
JetBrains Mono – SIL Open Font License 1.1 (`public/kits/fluid/fonts/OFL-{key}.txt`). Symbole: Phosphor (Kern-Sprite, MIT).
Beispielbilder: automatisch erzeugt (GD), frei verwendbar.
