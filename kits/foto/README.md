# Kit „foto“

Kit für Fotografinnen, Fotografen und Portfolios in KLXM Studio. Bilder tragen das Design: ruhige Navigation mit
Aufklappmenü für Serien, Serien als eigene Seiten, Fotostrecken als Mosaik, bündige Zeilen, Raster, große Einzelbilder
oder waagerechtes Band, Lightbox, sparsame Bildunterschriften, viel Weißraum, neutrale Farben. Bilder und Videos lassen
sich mischen; im Bearbeiten-Modus zieht die Redaktion Fotos und Videos direkt auf die Seite.

Grundlage ist die breakpointlose (intrinsische) Architektur des Kits „fluid“ (angelegt mit
`php bin/console kit:create foto --from=fluid`): Design-Tokens, fließende Schrift- und Abstandsstufen (clamp), auto-fit-Raster,
CSS-Spalten, Container-Queries, CSP-fest (keine Inline-Skripte/-Styles; JavaScript setzt höchstens `el.style`), Block-CSS nur
dort, wo der Block steht, selbst gehostete Schriften, WCAG 2.2 AA.

## Struktur

```
kits/foto/
├── theme.php        Blöcke (5 Foto-Blöcke + 16 übernommene), Website, conditional_css, editor_js, 'design' => design.php
├── design.php       Tokens (Farben, Typografie, Form, Kopf/Fuß, Bewegung) + Gruppe „Bilder & Galerien“ + 7 Vorlagen
├── functions.php    Helfer foto_* (aus fluid) + Abschnitt „Fotografie“: foto_brand, foto_gw, foto_photo, foto_video, foto_embed,
│                    foto_images, foto_lightbox, foto_series_items/meta, foto_drop_zone, foto_item_tools
├── seed.php         Einstellungen + Rechtstexte; 'after' → tools/demo-content.php (Startseite, Arbeiten + 3 Serien, Über mich, Kontakt)
├── blocks/          photo_hero, photo_grid, moments, series_index, series_head, photo_text + hero, richtext, media_text, features, cards,
│                    logos, quote, steps, pricing, faq, cta, scrolly, video, contact, downloads, map
├── templates/       layout, error, maintenance, offline; partials: header, sheet, footer, section (Marke über foto_brand())
├── assets/css/      site.css (_tokens, _base, _header, _footer, _hero, _options, _photo) · b-photo-grid, b-photo-hero, b-series,
│                    b-photo-text, lightbox (je Block) · opt-*.css (gewählte Design-Optionen) · editing.css (Bearbeiten-Modus,
│                    Ablagefläche) · editor-photos.css (Upload-Dialog im Editor) · übernommene Block-Stylesheets aus fluid
├── assets/js/       site.js · lightbox.js · photo-hero.js · series.js · reel.js (Band) · scrolly.js · editor-photos.js (nur Editor)
├── tools/           contrast.php (WCAG-Prüfung aller Vorlagen) · demo.php + demo-content.php (Demo mit erzeugten Platzhaltern)
├── docs/            manual.php + manual/*.php (Handbuch), css-js.md (Kit-Seite „CSS & JS“)
├── lang/            en.php (Verwaltung) · site/en.php (feste Website-Texte lt())
├── package.json     Schriften (Fontsource): Inter, Manrope, DM Sans, Instrument Sans, Space Grotesk, Roboto Condensed, Fraunces,
│                    Newsreader, Instrument Serif, JetBrains Mono
└── build.mjs        kopiert die Schriften (latin + latin-ext) und erzeugt css/font-{key}.css
```

Build: `cd tools && pnpm run build` → `public/assets/kits/foto/{css,js,fonts}`.

## Design-Tokens

Alle Tokens aus „fluid“ (Farben hell/dunkel mit Kontrastprüfung, Typografie mit `fs_min`/`fs_max`/`ratio`/Schriften, Form & Raum,
Kopf & Fuß, Kopfbereich-Aktionen, Bewegung) – ohne „Seitenhintergrund“ und „Bildwirkung“ (ersetzt durch die Gruppe unten).
Standardwerte = Vorlage „Weiß & still“, identisch in `assets/css/_tokens.css` (`:root` und Dunkel-Block).

| Token (Gruppe „Bilder & Galerien“) | Ausgabe | Werte |
|---|---|---|
| `img_gap` Abstand zwischen den Bildern | `--f-gap-img` + `gap-*` | none · small · medium · large (fließend, clamp) |
| `images` Ecken der Bilder | `--f-img-r` + `img-*` | sharp · soft · round |
| `captions` Bildunterschriften | `cap-*` | below · hover (nur `hover:hover`, sonst darunter) · overlay · hidden (sr-only, bleibt in der Lightbox) |
| `caption_style` Schrift der Bildunterschriften | `capst-*` | plain · mono · italic · caps |
| `lightbox` Hintergrund der Lightbox | `lb-*` | dark · light · blur (gilt auch für `.cms-lb` der Kern-Galerie) |
| `grayscale` Schwarzweiß | `gs-*` | off · hover · always |
| `mat` Rahmen / Passepartout | `mat-*` | none · line · white (`--f-mat-bg`, im dunklen Schema gedämpft) |
| `gallery_width` Breite der Galerien | `gw-*` → `.gwrap--{contained,wide,full}` | contained · wide (Inhaltsbreite + 22 rem) · full (randlos, Rand = Bildabstand) |
| `img_hover` verlinkte Bilder beim Zeigen | `ih-*` | none · zoom (nur ohne „Bewegung reduzieren“) · fade |
| `logo_style` Marke oben links (Gruppe Kopf & Fuß) | `logo-*` | text (Wortmarke, `foto_brand()`) · image (Kern-Fragment „brand“) |

Block „Bildstrom“ (`moments`, `blocks/moments.php`, `css/b-moments.css`, `js/moments.js`): Bento (12er-Raster, `grid-auto-flow: dense`,
Kacheln s · m · wide · tall · l · full) oder Strom (Originalformat `r-*`, Startspalte `mo-c*`). Kachel öffnet Lightbox, Galerie (Sammlung
→ versteckte `a[data-lb]` derselben `[data-lb-group]`) oder Seite (`page:ID`). `foto_moments()` vereinheitlicht die Quellen (von Hand,
Unterseiten, Sammlung). Einblenden erst mit `.mo-armed` (JS), `mo-fx-drift` per `animation-timeline: view()`; Nachladen blendet nur
`.mo__later` ein (alles steht im HTML).

Kopfbereich zurücknehmen: `header_recede` → `hrc-off | hrc-hide | hrc-quiet` (site.js setzt `.is-away` beim Herunterscrollen;
„quiet“ = transparent, `mix-blend-mode: difference`).

Navigation: `header` = inline (Leiste oben) · minimal (nur Menü-Schaltfläche) · rail (Seitenleiste links) · centered · split · floating.
Kopfbereich-Aktionen: Handlungsaufruf als letzter Menüpunkt (`navitem`), Suche als Lupe.

### Vorlagen (alle hell + dunkel AA, `php kits/foto/tools/contrast.php`)

| Vorlage | Charakter |
|---|---|
| Weiß & still (Standard) | Weiß, Schwarz, Inter 450, eckig, Bildunterschriften darunter, Galerien breiter als der Text |
| Dunkelkammer | dunkel von Anfang an, Rotlicht-Akzent, Manrope, minimale Navigation, Bildunterschriften auf dem Bild, randlos |
| Galerie-Grau | warmes Grau, weißes Passepartout, Instrument Sans, zentrierter Kopf, Bildunterschriften beim Zeigen, Lightbox hell |
| Editorial | Newsreader-Überschriften, kursive Bildunterschriften, Tiefblau, geteilter Kopf, Fuß „Großer Schriftzug“ |
| Reportage | Roboto Condensed in Versalien, Signalrot, Schwarzweiß bis zum Zeigen, Monospace-Bildunterschriften, randlos |
| Analog | Creme/Rost, Instrument Serif + Sans, feine Linie, leicht gerundet, Seitenleiste, leichtes Vergrößern |

## Foto-Blöcke

| Block | Varianten / Optionen |
|---|---|
| `photo_hero` Bühne | single (Bild oder stummes Video) · slideshow (Bilder und Videos, Überblendung, Autoplay mit Intervall, Zurück/Pause/Weiter, Zähler) · Höhe screen/tall/21:9 · Text unten links/rechts, Mitte, oben links, unter dem Bild · Abdunkelung (Verlauf ≥ 55 % Schwarz hinter dem Text) · Hinweis „weiter nach unten“ · randlos oder mit Rand · H1 (leer = Seitentitel, sr-only) |
| `photo_grid` Fotostrecke | masonry (CSS-Spalten `columns: N Mindestbreite`) · justified (flex-grow = Seitenverhältnis, Klassen `ar-5…24`) · grid (auto-fill mit höchstens N Spalten, Format 1:1/3:2/2:3/4:5/16:9/4:3/3:4/Original) · single · strip (scroll-snap, Pfeile über js/reel.js) · Spalten auto/2/3/4 · Zeilen-/Bandhöhe · Breite · Bildunterschriften · Lightbox · Quelle: einzeln (Bild oder Video + Bildunterschrift + optional YouTube/Vimeo) oder Sammlung (Bilder und Videos) |
| `series_index` Serien-Übersicht | grid · list (große Titel, Vorschaubild folgt dem Mauszeiger – js/series.js; „Bewegung reduzieren“/Tastatur: fest am Rand; Touch: Bild in der Zeile) · rows · Quelle Unterseiten (Angaben aus `series_head`, sonst Vorschaubild der Seite bzw. erstes Bild) oder von Hand · Format, Spalten, Breite, Anzahl |
| `series_head` Serie (Kopf) | text · cover · side · Titel (H1), Jahr, Kategorie, Ort, Auftrag, Statement, Titelbild, Link zurück |
| `photo_text` Bild & Text | left · right · Breite ⅓/½/⅔ · Format · Ausrichtung · Bildunterschrift · Lightbox |

Bilder kommen immer über `Core\Media::pictureOf()` (AVIF/WebP-srcset, `width`/`height`, lazy, Zuschnitt je Format mit Fokuspunkt,
Bild anpassen/Rahmen des Kerns). Videos: `foto_video()` – Vorschaubild über `Media::posterFor()`, Untertitel/Transkript über
`Core\MediaTracks`, Lightbox klont ein `<template>` mit `<video controls>` (hält beim Blättern/Schließen an); ohne Lightbox
`<video controls preload="none">` in der Kachel. YouTube/Vimeo über das Kern-Fragment `video-embed` (Zwei-Klick).

**Lightbox** (`js/lightbox.js`, ≈ 2,3 KB, nur mit `photo_grid`/`photo_text`): `dialog.showModal()`, Pfeiltasten, Pos1/Ende,
Escape, Wischen, Nachbarn vorladen, Fokus zurück; ohne JavaScript Links zur großen Fassung.

## Bearbeiten-Modus: Fotos und Videos per Drag & Drop

`theme.php → 'editor_js' => ['js/editor-photos.js']` (nur im Seiten-Editor). `foto_drop_zone()` gibt im Bearbeiten-Modus eine
Ablagefläche „Fotos und Videos hierher ziehen oder auswählen“ aus (Knopf → Dateiauswahl mit Mehrfachauswahl; Tastatur und
mobil). Abgelegte Dateien werden nach Art geprüft (Bilder JPG/PNG/WebP/GIF, Videos MP4; sonst Meldung), dann im Dialog mit
`window.CMSMedia.Uploader` hochgeladen (1-MB-Stücke über `/admin/media/chunk` + `/finalize`, Fortschritt je Datei,
Größenprüfung, Alt-Text-Pflicht für Bilder, KI-Vorschlag). Danach:

- Liste (`images`, `slides`): Dateien werden angehängt; „Alle als Sammlung anlegen“ legt eine Sammlung an
  (`CMSMedia.api.collection`) und stellt die Fotostrecke auf „Sammlung“.
- Einzelbild (`image`, `cover`): ersetzt; mehrere Dateien auf eine Bühne „Ein Bild“ → Variante „Bildfolge“.
- Fotostrecke mit Sammlung: Dateien kommen in die Sammlung.
- Je Bild (`foto_item_tools()`): ← → und Entfernen; Bilder lassen sich per Ziehen umsortieren.

Schreiben in den Block über die **vorgeschlagene Kern-Schnittstelle** `window.CMSEditor.block(node)` →
`{ type, id, def, get(path), set({pfad: wert}) }` (`set` = `setPath` + `markDirty()` (Rückgängig/Wiederholen) + Seitenleiste
auffrischen + `loadPreview()`). Fehlt sie, lädt die Ablagefläche trotzdem hoch und meldet, dass die Dateien in der Seitenleiste
gewählt werden müssen. Der Editor setzt Knöpfe in der Vorschau auf `tabIndex = -1`; das Skript stellt die eigenen nach jedem
`cms:block-preview` wieder auf 0.

## Demo

`seed.php` → `foto_demo_install()`: „Mara Beispiel Fotografie (Demo)“, Startseite (Bühne mit Bildfolge, Serien-Übersicht,
Bild & Text, Handlungsaufruf), „Arbeiten“ (Liste) mit „Porträt“ (Raster 4:5), „Reportage“ (Band, Zitat, Mosaik aus Sammlung),
„Landschaft“ (große Einzelbilder, bündige Zeilen auf dunklem Grund), „Über mich“, „Kontakt“ (Kontakt, Pakete, FAQ).
Bilder: **Platzhalter**, mit PHP GD erzeugt (Hügel im Nebel, Dünen, Meer, Silhouetten statt Porträts, Schwarzweiß-Geometrie,
Filmkorn) – Titel „… (Platzhalter)“, Alt-Text „Platzhalter: …“, Bildnachweis „Platzhalter, automatisch erzeugt (Kit „foto“) –
frei verwendbar“, Schlagwort `foto-demo`, Sammlungen „Landschaft/Porträt/Reportage (Demo)“. Mit ffmpeg zusätzlich zwei
Platzhalter-Videos (Kamerafahrt über ein erzeugtes Motiv). Keine fremden Bildrechte, keine Personen.

```
CMS_SITE=<site> php kits/foto/tools/demo.php [--force|--remove] [--preset=dunkelkammer]
```

## Budgets (minifiziert)

| Datei | Größe |
|---|---|
| site.css (inkl. `_photo.css`) | 36 KB (8,6 KB gzip) |
| b-photo-grid.css / b-photo-hero.css / b-series.css / b-photo-text.css / lightbox.css | 3,3 / 5,5 / 4,7 / 0,5 / 3,0 KB |
| lightbox.js / photo-hero.js / series.js | 2,3 / 2,3 / 1,4 KB |
| editor-photos.js (nur Editor) | 8,7 KB |

## Erlaubte Media-Queries

`prefers-color-scheme`, `prefers-reduced-motion`, `forced-colors`, `print` und `hover: hover` (Bildunterschrift beim Zeigen,
Vorschau der Serienliste, Werkzeuge im Editor). Layouts richten sich nach Container-Breite (`cqi`, `@container`, auto-fill, Flex-Umbruch).

## Mehrsprachigkeit

Feste Website-Texte über `lt('…')` → `lang/site/en.php`; Verwaltung/Editor (`__()`, `t()` in JS, Feldbeschriftungen) →
`lang/en.php`. Prüfen: `CMS_SITE=<site> php bin/console i18n:missing en [--site-texts]`.

## Eigenes Kit ableiten

```
php bin/console kit:create kunde --from=foto     # Präfix foto_ → kunde_
cd tools && pnpm run build
```

## Lizenzen

Schriften: SIL Open Font License 1.1 (`public/assets/kits/foto/fonts/OFL-{key}.txt`). Symbole: Phosphor (Kern-Sprite, MIT).
Demo-Bilder und -Videos: automatisch erzeugt, frei verwendbar.
