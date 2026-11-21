# Kit „NativeCSS“

Kind-Kit von **fluid** (`'extends' => 'fluid'`) mit Seitenrahmen und Blöcken aus
[NativeCSS](https://github.com/skerbis/nativecss) (MIT, © Thomas Skerbis / KLXM Crossmedia): native Elemente (`<dialog>`,
`<details>`, `input type=range`), Cascade Layers, Container Queries, `light-dark()`. Alles, was das Kit nicht selbst
gestaltet, kommt von fluid und übernimmt über die Token-Brücke (`assets/css/_native.css`) Farben, Schrift, Radien und
Schatten aus Verwaltung → Design. Gegenstück zum Kit „NativeCSS“ von fluid für REDAXO 6.

## Was das Kit mitbringt

| Bereich | Umsetzung |
|---|---|
| Kopfleiste | `.ncss-topbar` mit `.ncss-nav`, immer einzeilig: passt das Menü nicht neben Marke und Werkzeuge, öffnet die Schaltfläche dasselbe Menü als Off-Canvas-`<dialog>` (`command="show-modal"`, ohne Skript in aktuellen Browsern; `js/native.js` ergänzt Ersatz, „Klick daneben schließt“, Schließen nach Link). Untermenüs als `<details>` mit „Übersicht“. Kopfbereich-Aktionen (Suche, Handlungsaufruf …), Sprachen (ab drei als Aufklappmenü des Kerns), Hell/Dunkel-Schalter. Umschalten ohne Skript per Container Query: `native_nav_fit()` schätzt die nötige Breite der Zeile (rem) aus Menüpunkten, Marke, Kopfbereich-Aktionen, Sprachen, Hell/Dunkel und Darstellung → `data-ncss-nav-fit`; `js/nav.js` von NativeCSS misst nach. Eigenes CSS für den Kompakt-Zustand liest den Schalter `--ncss-nav-compact` (`_native.css`) |
| Fußbereich | `.ncss-footer` im Raster: Name und Claim, Kontakt, Seiten, Social Media, Rechtliches, Sprachen |
| Einblend-Effekte | Abschnitt → „Einblend-Effekt“: Sanft einblenden, Von unten, Kreis, Vorhang (`.ncss-reveal … --once`, theme.php → `reveal`) |
| Einstieg | Text + Bild, Zentriert, Seitenkopf, Großer Schriftzug, Vollflächig mit Hintergrundvideo (`js/video-bg.js`: Pause-Schalter, lädt erst in der Nähe, nie bei „Bewegung reduzieren“/„Daten sparen“) |
| Text + Bild, Video | Darstellung „Morphing beim Scrollen“ (`block_extend`, `.ncss-morph`, nur CSS) |
| Karten | `.ncss-card`: ganze Karte klickbar, „Hervorheben“ mit Etikett, Filter nach Kategorie (sobald fluid die Felder hat) |
| Kennzahlen | zählen beim Hereinscrollen hoch (`.ncss-count-up`) – nicht Jahreszahlen, „24/7“ oder „3,5“ |
| Logos | Laufband `.ncss-marquee` mit Schaltfläche „Laufband anhalten“ |
| Scrollytelling, Zeitleiste, Zitat, Pakete | `<ncss-scrolly>`, `.ncss-timeline` (Linie wächst), `.ncss-testimonial`, `.ncss-pricing` und Preisliste `.ncss-leaders` |
| Vorher/Nachher | `<ncss-compare>` mit echtem Regler (Tastatur: Pfeile, Pos1/Ende) |
| Video & Audio | eigene Videodatei im `<ncss-video-player>` (Kapitel, Untertitel aus der Mediathek), Audio im `<ncss-audio-player>` (Variante `audio` von fluid: Cover, Playlist, Kapitel und Transkript je Datei aus der Mediathek) |
| Darstellungs-Knopf | `fluid_prefs()` von fluid (Design → Bewegung & Farbschema, Standard aus), hier zusätzlich Lese-Schrift (`visitor_prefs_fonts`) |
| Filter, Tabellen | Karten-Filter mit `css/filter.css`/`js/filter.js` aus NativeCSS (ersetzen die von fluid); sortierbare Tabellen mit Skript und CSS des Kerns |

Übrige Varianten (Einstieg Collage/Skala/Vergleich/Karten, Karten „Band“, Text + Bild „Geteilt“/„Überlappend“,
Handlungsaufruf „Mit Bild“/„Großer Schriftzug“/„Foto“, Ablauf „Nummeriert“/„Prozess“, Zitat „Band“/„Laufband“,
Pakete „Vergleichstabelle“) bindet die View über `native_parent_view('typ')` aus fluid ein.

## Laden nur bei Bedarf

`css/site.css` (immer): Farben und Tokens von NativeCSS, Grund-CSS von fluid im Layer `fluid` (ohne Kopf/Fuß),
Kopfleiste, Navigation, Off-Canvas, Fußbereich, Brücke. Reihenfolge der Layer steht in der Datei selbst – ein
`<style>` im Kopf erlaubt die CSP der Website nicht. Jede Komponente lädt über `conditional_css`, ausgelöst vom Block,
der Variante oder einem Merkmal (`Theme::markUsed`, z. B. `stats:@count`, `logos:@marquee`, `video:@player`,
`@videobg`, `@morph`, `@reveal`, `@nv-cards`). Player-Skripte (Video ≈ 23 KB, Audio ≈ 17 KB) laden nur mit eigener Datei.

## Struktur

```
kits/native/
├── theme.php            extends fluid, Design (Vorlagen „NativeCSS“, „NativeCSS · Graphit“), reveal, block_extend, conditional_css
├── functions.php        native_parent_view(), native_count(), native_prefs_element() …
├── templates/           layout.php, partials/header.php, footer.php, section.php (Attribut des Einblend-Effekts)
├── blocks/              Views mit NativeCSS (fallen für andere Varianten auf fluid zurück)
├── assets/css, js/      _native.css (Brücke), site.css, nv-*.css (Blöcke), ncss-*.css/js (Hüllen um vendor/ncss)
├── vendor/ncss/         NativeCSS unverändert – nur die verwendeten Dateien, Stand in VERSION.txt
├── build.mjs            kopiert die Lese-Schriften (OFL) nach public/assets/kits/native/fonts
├── seed.php             Startinhalte von fluid + Seite „Bausteine“ (tools/demo-content.php)
├── demo/media/          Demo-Medien (siehe unten)
└── tools/               ncss-update.php, demo.php, demo-content.php
```

## NativeCSS aktualisieren

```bash
php kits/native/tools/ncss-update.php --from=/pfad/zu/nativecss --dry-run     # Übersicht: neu, geändert, entfernt
php kits/native/tools/ncss-update.php --from=/pfad/zu/nativecss --ref=v1.2.0  # genau dieser Git-Stand
php kits/native/tools/ncss-update.php --tag=v1.2.0                            # von GitHub
cd tools && pnpm run build
```

Neue Komponente: Pfad in `NCSS_FILES` (tools/ncss-update.php) ergänzen, Hülle in `assets/css` bzw. `assets/js` anlegen
(`@import "../../vendor/ncss/components/x.css" layer(components);`) und in theme.php → `conditional_css` zuordnen.

## Demo

`--content=full` legt die Startinhalte von fluid (mit Showcase) und die Seite **Bausteine** an. Bestehende Website:
`CMS_SITE=key php kits/native/tools/demo.php [--force|--remove]`.

Medien in `demo/media/`:
- `ncss-*` – Kurzfilm (Ton entfernt), Untertitel, Kapitel, Schleifen und Vorschaubilder aus der Demo von NativeCSS:
  für NativeCSS erzeugt (gerenderte Szenen, eigene Untertitel), MIT, © Thomas Skerbis / KLXM Crossmedia
  (Herkunft: `demo/assets/media/README.md` im Repository von NativeCSS).
- `pexels-wohnzimmer-*` – Foto: Curtis Adams, [Pexels](https://www.pexels.com/photo/empty-living-room-6835103/)
  (Pexels-Lizenz); „Vorher“ ist dasselbe Foto, für das Beispiel flau bearbeitet.
