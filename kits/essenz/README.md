# Kit „essenz“ – weniger, aber besser

Funktionaler Minimalismus für KLXM Studio, **inspiriert von den zehn Thesen für gutes Design von Dieter Rams**
(nützlich, verständlich, unaufdringlich, ehrlich, langlebig, konsequent bis ins Detail, umweltfreundlich, so wenig
Design wie möglich). Eigenständige Gestaltung: keine Marken, Logos, Texte, Bilder oder Code Dritter.
CSP-fest (keine Inline-Skripte/-Styles), keine externen Anfragen, cookiefrei ab Werk, WCAG 2.2 AA, MIT.

## Formensprache

| Element | Umsetzung |
|---|---|
| Flächen | warmes Hellgrau (Hintergrund), Paneel (Karten, Menüs, Formulare), getönte Fläche; Graphit-Schrift |
| EIN Signal | `accent` = Füllfarbe für Tasten, Punkte, Skalen (≥ 3:1 als Bedienelement), `accent_ink` = lesbare Fassung für Links/Nummern (≥ 4,5:1) |
| 8-Punkt-Raster | `--e-u` (7/8/9 px, Design → Rasterdichte); alle Abstände `--e-s-*` sind Vielfache |
| Paneele | Radius `--e-radius`, 1-px-Kante, Tiefe `depth-{flat,relief,deep}` (Lichtkante oben + weicher Schatten) |
| Tasten | `btn-key`: gefüllt mit Druckkante (`--e-key-sh`), `:active` drückt 1 px ein; alternativ Pille/Kontur |
| Beschriftung | Dachzeilen, Nummern, Kennwerte in Geist Mono (Tabellenziffern), Versalien mit Sperrung |
| Motive (sparsam) | Signalpunkt (Abschnittsmarke, Marke, „Jetzt geöffnet“), Punktraster (Einstieg ohne Bild, Paneel-Fuß, CTA), Rundinstrument (Kennzahlen), Schiene (Ablauf), Skala (große Aussage). `has-motifs` schaltet sie ab |
| Nachtpaneel | dunkles Schema (Anthrazit, gleiches Signal) per `prefers-color-scheme`, Vorlage „Nachtpaneel“ von Anfang an dunkel |
| Bewegung | nur Einblenden, Skala füllt sich, Druckpunkt – nie bei „Bewegung reduzieren“ |

## Struktur

```
kits/essenz/
├── theme.php        16 Blöcke, Website (Schlüssel wie „fluid“/„basis“), conditional_css, 'design' => design.php
├── design.php       Tokens (11 Farben hell/dunkel, 5 Schriften + System, Stufen, Radius, Tiefe, Raster, Marken, Kopf/Fuß) + 6 Vorlagen
├── functions.php    Helfer essenz_* (Kontakt, Zeiten inkl. Daten für „Jetzt geöffnet“, Menü + Fit-Schätzung, Index-Nummern, Abschnittskopf, JSON-LD)
├── seed.php         Startinhalte „Werkstatt Beispiel“ + 'after' → Musterseiten (tools/demo-content.php)
├── blocks/          hero, richtext, media_text, features, principles, specs, stats, steps, cards, quote, faq, cta, contact, downloads, video, map
├── templates/       layout, error, maintenance, offline; partials/: header, sheet (Menü/Index), footer, section
│                    (Marke, Sprachen, Öffnungszeiten, Video, Editor, Werkzeugleiste: Kern-Fragmente, app/Views/fragments)
├── assets/css/      site.css (_tokens, _base, _header, _footer, _hero) · overlay.css (Aufklappmenüs, Such-Popover, Seitenblatt – am
│                    Ende von <body>) · opt-{header,footer,pagebg}-*.css (nur gewählte Option) · b-*.css (je Block) · data, media,
│                    calendar, dataform, sections, search (ersetzen die Kern-Stylesheets) · pages, editing, preview
├── assets/js/       site.js (≈ 4,7 KB) (2-Klick-Video: Skript des Kerns, resources/js/embed.js)
├── tools/           contrast.php (WCAG-Prüfung aller Vorlagen) · demo.php + demo-content.php (Musterseiten; --heroes: nur /werkstatt/hero-varianten inkl. Demo-Video per ffmpeg)
├── lang/            en.php (Verwaltung) · site/en.php (feste Website-Texte lt())
├── docs/manual.php  Handbuch (Gestaltungsprinzip, Design/Kopf/Fuß, Beschreibung aller Blöcke)
├── package.json     @fontsource-variable/{inter,inter-tight,manrope,geist,geist-mono} (alle OFL 1.1), @expo-google-fonts/inter (TTF für App-Icons)
└── build.mjs        kopiert die variablen Schriften (latin + latin-ext) und erzeugt css/font-{key}.css
```

Build: `cd tools && pnpm run build` → `public/assets/kits/essenz/{css,js,fonts}`.

## Budgets (minifiziert, gemessen)

| | Wert |
|---|---|
| site.css (renderblockierend, alle Seiten) | ≈ 24,8 KB |
| Schrift-CSS Standard (Inter + Geist Mono) | 2 × ≈ 0,9 KB |
| Design-Datei | nur bei Abweichung vom Standard, ≈ 1 KB |
| **Grundlage Startseite** | **≈ 26,6 KB** (< 30 KB) |
| Block-CSS Startseite (principles, features, stats, cta) | ≈ 7 KB, nur dort |
| hero-x.css (Einstieg console/dials/monitor) | ≈ 11,7 KB (≈ 3,1 KB gzip), nur auf Seiten mit diesen Varianten; JS: nur Kern-hero.mjs (Video) bzw. dials.mjs |
| overlay.css (am Seitenende) | ≈ 5,4 KB |
| JS Startseite | site.js ≈ 4,7 KB (< 8 KB) |
| Schriften (WOFF2, variabel, latin) | Inter ≈ 48 KB, Geist Mono ≈ 30 KB; nur Fließtext/Überschrift vorgeladen |

## Design-Tokens (design.php → `--e-*` bzw. Klassen am `<html>`)

| Gruppe | Tokens |
|---|---|
| Farben | accent `--e-a`, accent_ink `--e-a-ink`, on_accent `--e-a-on`, ink, text, muted, background, surface, panel, line, dark_section – je hell/dunkel |
| Typografie | font_body, font_head, font_mono, label_font, fs_min/fs_max, ratio, heading_weight, heading_tracking |
| Form, Raster & Tiefe | radius, buttons `btn-{key,pill,outline}`, depth `depth-{flat,relief,deep}`, grid `--e-u`, space, wrap, markers `mk-{dot,number,plain}`, motifs `has-motifs` |
| Kopf & Fuß | header `hdr-{bar,centered,split,index}`, header_sticky, footer `ft-{index,panel,simple}`, pagebg `pagebg-{plain,dots,lines}` |
| Kopfbereich: Suche & Aktionen | `ha_*` aus `Core\HeaderActions::designGroup()` – Standard: Kontakt-Menü (Geräte-Paneel) + Telefon-Chip mit Statuspunkt (Handlungsaufruf, Suche, Anordnung, Kontakt-Chip, Sprache, Social, Anmelden; `header_actions()`) |
| Bewegung & Schema | motion `has-motion`, dark `has-dark` |

Vorlagen: Signalorange (Standard) · Graphit (Ocker) · Signalgrün · Signalblau · Nachtpaneel · Papier – alle AA hell + dunkel:
`php kits/essenz/tools/contrast.php` (Exit-Code 1 bei Verstößen; prüft auch abgeleitete Mischfarben und 3:1 für das Signal als Bedienelement).

## Blöcke

| Block | Varianten |
|---|---|
| hero | panel (Geräte-Paneel: Bild oder Inline-SVG-Punktraster mit Regler + nummerierte Kennwerte „Bezeichnung: Wert“) · statement · split · compact · **console** (Bedienfeld: Anzeige in Segment-Optik + bis zu 6 Kanäle aus „Kennwerte“, Pegel bei „87 %“/„4 / 5“, sonst Drehregler – Dekor aria-hidden, Werte als `<dl>`) · **dials** (Aussage + 3–4 Kern-Rundinstrumente im Paneel, `Hero::dials`, `uses` → dials) · **monitor** (stummes Loop-Video im Geräterahmen mit Scrim, Pause als Geräte-Taste, `Hero::video`; bei „Bewegung reduzieren“ Standbild) – CSS der drei: css/hero-x.css, nur dort |
| richtext | standard · article (Inhaltsverzeichnis aus H2/H3, Lesezeit) – Zitate, H2–H4 |
| media_text | auto (abwechselnd) · right · left |
| features | panel (Bedienfeld – ein Paneel mit Haarlinien) · cards · list |
| principles | list (Thesen mit großen Nummern) · grid · accordion |
| specs | table · image (Datenblatt/Typenschild, Gruppen) |
| stats | dials (Rundinstrument, SVG, Füllstand 0–100 optional) · row (große Zahlen + Pegel) |
| steps | steps (Schiene) · timeline |
| cards | product (Bild vertieft auf Fläche, ideal PNG) · service (Symbol) · image |
| quote | single · grid |
| faq | split · stacked (JSON-LD FAQPage) |
| cta | panel · band · minimal |
| contact | Angaben + Öffnungszeiten-Paneel mit „Jetzt geöffnet“ (im Browser berechnet, cache-sicher) + Karte + Formular |
| video, downloads, map | Zwei-Klick-Video mit Untertiteln/Transkript, PDF-Viewer, Karte ohne Cookies |

Kern-Blöcke im Kit-Stil: data_list, data_fields, data_form (Bedingungen, IBAN, Gruppen), calendar, upcoming, gallery,
slideshow, stack_cards, Suche (Ergebnisseite + Popover an der Lupe per CSS-Ankerpositionierung, sonst site.js),
Abschnitte mit Vollbild/Hintergrundbild. Abschnittsköpfe der Kern-Blöcke folgen dem zweispaltigen Kopf des Kits.

## Musterseiten (Demo)

`CMS_SITE=<site> php kits/essenz/tools/demo.php [--force|--remove] [--preset=graphit]` legt `/werkstatt` an (Übersicht,
Produkte & Termine, Formular & Kontakt, Medien, Journal) – mit erzeugten Bildern (GD: sechs erfundene Geräte als
freigestellte PNG, elf geometrische Kompositionen; keine Fotos, keine Personen, keine Marken), Tabellen
`werkstatt_produkte` (Detailseite `/produkte/…`), `werkstatt_termine` (Wiederholung, ganztägig, Kategorien),
`werkstatt_anfragen` (öffentliches Formular mit Bedingung). Alle Namen sind erfunden. Kartenpunkt: geografischer
Mittelpunkt Deutschlands. Video: „Big Buck Bunny“ © Blender Foundation, CC BY 3.0 (Zwei-Klick). Die Übersicht ist die
Musterseite des Style-Editors (`design.sample`).

## Mehrsprachigkeit

Feste Texte über `lt('…')`, Übersetzungen in `lang/site/{sprache}.php`; Verwaltungsbegriffe in `lang/en.php`.
Prüfen: `php bin/console i18n:missing en --site-texts --site=…`.

## Lizenzen

Inter, Inter Tight, Manrope, Geist, Geist Mono – SIL Open Font License 1.1 (`public/assets/kits/essenz/fonts/OFL-{key}.txt`).
Symbole: Phosphor (Kern-Sprite, MIT). Beispielbilder: automatisch erzeugt (GD), frei verwendbar.
