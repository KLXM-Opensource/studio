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
Variante **Seitenleiste mit Menübaum** (`header` = `sidebar`, `templates/partials/header-sidebar.php`, `opt-header-sidebar.css`):
`.page` ist ein Container (`fpage`); ab 62 rem Seitenbreite steht der Kopf als Leiste links über die volle Höhe (sticky,
eigener Bildlauf, Breite `sw-{narrow,normal,wide}` = 16/18,5/22 rem) – großes Logo (Höhe nach `logo-*`, auch quadratisch),
Website-Suche (`Search::form('side')`, aus bei `ha_search` = none), Menübaum `fluid_nav_tree()` mit allen Ebenen (jede Seite
ein Link, Unterseiten per Schaltfläche mit `aria-expanded`/`aria-controls`; aktueller Zweig offen, `st-open` = alles offen;
ohne JavaScript ist der ganze Baum sichtbar), unten Handlungsaufruf, Telefon/E-Mail, Social Media, Sprachen. Darunter eine
Leiste oben mit Logo und Menü-Schaltfläche → dasselbe Seitenblatt wie bei allen Kopfbereichen.
Kopfbanner (`side_banner` → `sb-{off,band,image}`, Höhe `side_banner_height` → `sbh-{normal,tall}`): bei `band`/`image` rendert
`header-sidebar.php` einen `<header class="hdr side-banner">` über die volle Breite (Logo, Claim = Einstellung `tagline`,
Handlungsaufruf; Farbe über `hbg-*`; Bild = Einstellung `banner_image` per `img()` in natürlichem Seitenverhältnis – ganz gezeigt, bestimmt die Höhe;
Logo + Claim auf heller Fläche, optional abgedunkelt `side_banner_overlay` → `sbo-{none,soft,strong}`; Unterseiten: höchstens
22 rem mit Fokuspunkt) und darunter `.side--below` (Suche, Menübaum, Kontakt; sticky, höchstens Fensterhöhe). Auf jeder Seite
gleich, auf Unterseiten (`body.is-sub`) niedriger; bei „Farbenfroh“ das Farbband an der Unterkante. Schmal: Banner als
Leiste mit Logo und Menü-Schaltfläche, die Seitenleiste entfällt (Seitenblatt).

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
├── package.json     @expo-google-fonts (TTF für App-Icons)
└── build.mjs        TTF für den App-Icon-Generator; Webfonts: design.fonts → 'fontsource' (Schriften-Manager, fonts:sync)
```

Build: `cd tools && pnpm run build` → `public/assets/kits/fluid/{css,js}`.

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
| Farben | palette `pal-{brand,colorful}` („Farbenfroh“: `opt-palette-colorful.css` – Farbton `--f-tone` je Abschnitt/Eintrag aus `--f-a`, `--f-b`, color3–5 `--f-c3…--f-c5`; nur Grafik ≥ 3 : 1 und Tönung `--f-tone-soft` (14 %) unter Schrift in Textfarbe), accent `--f-a`, accent_strong, on_accent, highlight `--f-a2` (Hervorhebung: Flächen, Marker), secondary `--f-b` + on_secondary `--f-b-on` (zweite Markenfarbe, Einsatz über secondary_role `sec2-{sections,details,buttons,all}`), ink, text, muted, background, surface, line, dark_section – je hell/dunkel mit Kontrastprüfung |
| Typografie | font_body, font_head, font_mono, label_font (Dachzeilen), fs_min/fs_max (Grundschrift klein/groß), ratio (1,125–1,6), vw_min/vw_max (Fließbereich), heading_weight (300–900 stufenlos), heading_tracking, heading_case `hcase-*`, emphasis `em-{accent,italic,bold,marker,underline,gradient}`, links `ln-{underline,accent,marker}` |
| Form & Raum | radius, buttons `btn-{solid,pill,outline,soft,sharp}`, cards `cards-{flat,outlined,elevated,glass}`, shadow (none/soft/crisp/layered), density, space, wrap (Inhaltsbreite), eyebrow `eb-{line,pill,dot,plain}`, images `img-{radius,sharp,round,arch}`, image_fx `imgfx-{none,mono,tint,frame}`, hover `hov-{lift,glow,none}`, icons `ic-{soft,circle,outline,plain}`, icon_pos `icp-{top,inline,side}` (Symbol über dem Titel, in einer Zeile damit, links neben dem Text), dividers `div-{none,wave,slant,curve,zigzag}` (opt-dividers-*.css, nur zwischen Abschnitten mit unterschiedlichem Hintergrund) |
| Modern (neu) | buttons zusätzlich `btn-{gradient,brutal}`, cards `cards-{tinted,gradient,brutal}`, pagebg `pagebg-{aurora,noise,lines}`, emphasis `em-outline`, hover `hov-zoom`, nav_style `nl-dot`, motion_style `mo-{clip,slide}`, sections `secs-{flush,inset}` (farbige Abschnitte als abgerundete Flächen), progress `has-progress` (Lesefortschritt, scroll-getrieben), totop `has-totop` („Nach oben“), Zitat-Variante `marquee` (Laufband), Fließtext „Artikel“ Feld `scrollspy` (`toc--spy`, site.js); Vorlagen „Software & Produkt · Aurora“ und „Studio · Neo-Brutalismus“ |
| Kopf & Fuß | header `hdr-{inline,centered,split,floating,rail,sidebar,minimal}`, logo_size `logo-{normal,large,xl}` (Logo/Wortmarke in allen Köpfen), side_width `sw-{narrow,normal,wide}`, side_tree `st-{branch,open}`, side_banner `sb-{off,band,image}` und side_banner_height `sbh-{normal,tall}` (nur Seitenleiste mit Menübaum; Bild: Einstellung `banner_image`), header_sticky, header_bg `hbg-{page,surface,accent,secondary,dark,gradient}`, nav_style `nl-{plain,underline,pill,caps}`, topbar `meta-{off,dark,accent,secondary,surface}` (Infoleiste mit Telefon, E-Mail, Social und Text aus den Einstellungen `topbar_text`), footer `ft-{columns,simple,statement,centered}`, footer_bg `fbg-{surface,page,dark,accent,secondary}`, eyebrow_case `ebc-{auto,upper,normal,smallcaps}` (Schreibweise der Dachzeilen, unabhängig von eyebrow), nav_parent `np-{split,hover,overview}` (Menüpunkt als Link + Pfeil bzw. bisher „Übersicht“ im Aufklappmenü; `fluid_nav_parent()`), nav_levels `nv-{accordion,slide,indent,groups}` (Standard accordion; accordion/slide über `<details name>` ohne JavaScript) (dritte Ebene im Aufklappmenü), header_height `hh-{auto,tall,xtall}` (größeres Logo; beim Scrollen `html.is-scrolled` und in Köpfen ≤ 40 rem wieder normal), footer_line `fl-{auto,none,line,shadow,emboss,band}` mit footer_line_color `flc-{auto,accent,secondary,ink,white}` und footer_line_width `flw-{auto,w1,w2,w4,w6,w10}`, pagebg `pagebg-{plain,grid,dots,glow}` |
| Kopfbereich: Suche & Aktionen | `ha_*` aus `Core\HeaderActions::designGroup()` – Standard: Suchfeld + Textlink („Projekt besprechen →“) (Handlungsaufruf, Suche, Anordnung, Kontakt-Chip, Sprache, Social, Anmelden; `header_actions()`) |
| Bewegung & Schema | motion `has-motion` (Einblenden, Laufband – nie bei „Bewegung reduzieren“), motion_style `mo-{rise,fade,scale,blur}`, dark `has-dark` |

Abgeleitet (color-mix): `--f-a-soft`, `--f-surface-2`, `--f-line-strong`, Rollen je Fläche (`.bg-accent`, `.bg-dark`,
`.on-media`, Hintergrundbild mit Abdunkelung). Ist der Hintergrund schon im hellen Schema dunkel (Vorlage „Tech-Startup“),
setzt `fluid_html_class()` `is-darkbase` (color-scheme dark). Serifen-Überschriften → `head-serif` (Hervorhebung kursiv).
**Neue Vorlage:** Werte in design.php ergänzen, `php kits/fluid/tools/contrast.php` ausführen (Exit-Code 1 bei Verstößen).

### Vorlagen
Fluid Standard · Kanzlei (Newsreader, eckig, zentriert) · Handwerk (Bricolage, Pille, schwebend, Punktraster) ·
Praxis (Salbei, Instrument Sans, Schatten, Verlauf) · Agentur (Verhältnis 1,45, Violett/Limette, geteilt, Raster) ·
Verein (Blau/Gelb, DM Sans 800) · Restaurant (Fraunces, Creme, Kontur) · Tech-Startup (dunkel, Space Grotesk, Glas,
Mono-Etiketten) · Kultur (Instrument Serif 1,5, Signalrot, Seitenleiste) · Sozialträger (Orange/Grün, Lato, Textmarker,
Infoleiste) · Verband (Roboto Condensed in Versalien, Kopf in Markenfarbe, schräge Übergänge) · Gesundheit (Atkinson
Hyperlegible, größere Grundschrift, runde Bilder) · Betrieb & Notdienst (Roboto/Roboto Slab, dunkler Kopf, rote Infoleiste) ·
Kommune & Portal (Poppins, Violett/Petrol, Wellen, zentrierter Fuß) · Wissen & Kampagne (dunkel, Manrope, Verlaufs-Betonung) ·
Netzwerk & Bildung (Kopfbanner „Farbband“, Seitenleiste mit Menübaum, Logo sehr groß, Farbenfroh, Nunito, Verlauf in der Leiste).
Alle 16 AA hell + dunkel (bei „Farbenfroh“ zusätzlich die fünf Farben als Grafik und als Tönung unter Text). Zusätzliche Schriften: Open Sans, Source Sans 3, Lato, Roboto (+ Condensed, Slab), PT Sans, Poppins,
Manrope, Atkinson Hyperlegible Next (selbst gehostet über den Schriften-Manager, Fontsource).

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
JetBrains Mono – SIL Open Font License 1.1 (installiert vom Schriften-Manager, Lizenz je Schrift in `public/assets/fonts/installed/{id}/LICENSE.txt`). Symbole: Phosphor (Kern-Sprite, MIT).
Beispielbilder: automatisch erzeugt (GD), frei verwendbar.

## Demo-Set „Netzwerk & Bildung“ (Mitglieder-Verzeichnis)

Nicht Teil der Startinhalte – auf Wunsch: `CMS_SITE=<site> php kits/fluid/tools/demo.php --network --preset=netzwerk`
(`--network-remove` entfernt es). `tools/demo-network.php` legt an: Datentabelle `netzwerk_mitglieder` (Logo, Name,
Schwerpunkt als Mehrfachauswahl, Region, Ansprechperson, Straße, PLZ, Ort, Telefon, E-Mail, Website, Kurztext, Beschreibung,
Standort), zwölf erfundene Mitglieder mit erzeugten Monogramm-Logos, Seite `/mitglieder` (Datenliste „Verzeichnis“ mit
Filter-Schaltflächen nach Schwerpunkt und Region, Suche; kompakte A–Z-Liste), Detailseiten-Vorlage (Datensatz-Felder
„Profil“, Layout ⅔ + ⅓ mit Beschreibung und „Kontaktkarte“, „Weitere Mitglieder“), Seitenbaum `/netzwerk` mit drei Ebenen
und ein Logo (hell/dunkel) – Name und Logo der Website nur, solange dort noch die Fluid-Demo steht. Alles Kern-Bausteine:
Datenliste (`layout` directory, `image_fit`, `visitor_filter`, `visitor_filter2`, `visitor_search`, `az_index`) und
Datensatz-Felder (`layout` profile, contact) – siehe Entwicklerhandbuch → Daten.

