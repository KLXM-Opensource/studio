# Kit „galerie“

Kit für **Kunstgalerien, Kunstvereine und Projekträume** – abgeleitet vom breakpointlosen Kit „fluid“ (gleiche Architektur:
Design-Tokens, Container-Queries statt Breakpoints, CSP-fest, CSS je Block). Ausstellungen mit **berechnetem Status**
(jetzt / demnächst / Archiv), Künstlerliste, Werke mit Verfügbarkeit, Preis und Anfrage, **Viewing Room**, Besuch mit
Öffnungszeiten, Ausnahmen und mehreren Orten, gemischte Galerien aus Bildern und Videos, Hochladen per Ziehen & Ablegen.
Sechs geprüfte Vorlagen (WCAG 2.2 AA hell + dunkel). Cookiefrei, keine externen Anfragen.

## Struktur

```
kits/galerie/
├── theme.php        Blöcke (8 Galerie-Blöcke + 15 aus fluid), Website (Gruppe „Galerie“), conditional_css, editor_js
├── design.php       Tokens (Farben inkl. Punkte „Verfügbar/Verkauft“, 12 Schriften, Gruppe „Galerie“) + 6 Vorlagen
├── functions.php    Helfer galerie_* – fluid-Helfer + Galerie (Tabellen, Status, Daten, Werkangaben, Preis, Anfrage, Orte, Medien)
├── seed.php         Einstellungen + Rechtsseiten; 'after' → tools/demo-content.php (Tabellen, Platzhalter-Bilder, Seiten, Detailseiten)
├── blocks/          exhibition_feature, exhibitions_list, exhibition_detail, artists_index, artist_profile, artworks, artwork_detail,
│                    visit + hero, richtext, media_text, features, cards, logos, quote, steps, faq, cta, tabs, video, contact, downloads, map
├── templates/       wie fluid; Fußbereich „Spalten“ mit Öffnungszeiten
├── assets/css/      site.css … (fluid) · b-gallery.css (gemeinsam) · b-exhibitions.css · b-artists.css · b-artworks.css · b-visit.css ·
│                    gallery-edit.css (nur Redaktion)
├── assets/js/       site.js, tabs.js, reel.js · gallery.js (Filter) · gallery-edit.js (Medien per Ziehen & Ablegen, nur Redaktion)
├── tools/           contrast.php (WCAG-Prüfung aller Vorlagen, inkl. Punkte) · demo.php + demo-content.php (Galerie-Demo)
├── lang/            en.php (Verwaltung) · site/en.php (lt())
├── docs/            manual.php + manual/*.php (Handbuch) · css-js.md
├── package.json     Fontsource: Inter, Manrope, Instrument Sans, Hanken Grotesk, Space Grotesk, Atkinson Hyperlegible Next,
│                    Newsreader, Fraunces, Instrument Serif, EB Garamond, JetBrains Mono, IBM Plex Mono
└── build.mjs        TTF für den App-Icon-Generator; Webfonts: design.fonts → 'fontsource' (Schriften-Manager, fonts:sync)
```

Build: `cd tools && pnpm run build` → `public/assets/kits/galerie/{css,js}`.

## Datenmodell

Drei Datentabellen mit festen Feldnamen (Kurznamen wählbar unter Website → Galerie → Datentabellen; Standard unten).
Fehlende Felder sind erlaubt – die Blöcke zeigen dann weniger. Detailseiten: Vorlagen-Seiten `_vorlage-{tabelle}`.

| Tabelle (Route) | Felder |
|---|---|
| `kuenstler` (/kuenstler) | `name`*, `sortname`, `featured` (bool), `portraet` (media), `geboren`, `lebt`, `kurzbio`, `bio` (richtext), `website` (url), `medien_1…4` (Datei: Bild oder Video), `video_url` |
| `ausstellungen` (/ausstellungen) | `titel`*, `untertitel`, `kuenstler` (relations → kuenstler), `beginn`* (date), `ende` (date), `eroeffnung` (datetime), `ort` (select: galerie, projektraum, showroom, messe, extern), `ort_text`, `kurztext`, `text` (richtext), `bild` (media), `ansicht_1…6` (Datei: Bild oder Video), `werke` (relations → werke), `pressetext` (Datei, PDF), `video_url` |
| `werke` (/werke) | `titel`*, `kuenstler` (relation), `jahr`, `technik`, `masse`, `auflage`, `verfuegbarkeit` (verfuegbar, reserviert, verkauft), `bild` (media), `ansicht_1…4` (Datei: Bild oder Video), `preis` (number), `preis_anzeige` (anfrage, zeigen, aus), `beschreibung` (richtext), `video_url` |
| `anfragen` (Demo) | öffentliches Formular: `name`, `email`, `telefon`, `werk` (wird vorausgefüllt), `nachricht` |

**Status ohne Statusfeld:** `galerie_status($e)` → `upcoming` (Beginn > heute), `past` (Ende < heute), sonst `current`
(ohne Ende: „bis auf Weiteres“). Sortierung in `galerie_exhibitions()`: laufende nach Ende, kommende nach Beginn,
vergangene neueste zuerst; „Letzte Tage“ in der letzten Woche. Der Seiten-Cache des Kerns gilt je Kalendertag, daher
stimmt der Status jeden Tag ohne Cron.

**Bilder und Videos:** Hauptbild = Bildfeld (`bild`, `portraet`), weitere Plätze = Dateifelder (nehmen Bilder und MP4).
`galerie_media_slots()` liest die Plätze aus der Tabelle, `galerie_media_gallery()` rendert Bilder in die Lightbox des Kerns
(`MediaBlocks`) und Videos als Kern-Fragment `video-embed` (Poster, Untertitel, Transkript; YouTube/Vimeo per Zwei-Klick).

## Blöcke (Galerie)

| Block | Varianten / Besonderheiten |
|---|---|
| exhibition_feature „Aktuelle Ausstellung“ | bleed (randlos, Titel unten links, Verlauf/stark/ohne, bildschirmhoch/groß) · split (Switcher) · type (Titel in `cqi`). Automatisch laufend → sonst nächste, optional nur an einem Ort; H1 schaltbar |
| exhibitions_list „Ausstellungen“ | list · cards · timeline (Jahre, sticky ab 46 rem) · fairs. Anzeige: Reiter (tabs.js, ARIA, ohne JS Abschnitte) / Abschnitte / nur laufende, kommende, vergangene; Archiv nach Jahren; Ort-Filter (inkl. „ohne Messen“); auf Künstler-Detailseiten automatisch gefiltert |
| exhibition_detail | bleed · split · text. Kopf, Angaben-Spalte (dl), Pressetext, Ansichten + Videos, gezeigte Werke, JSON-LD `ExhibitionEvent` |
| artists_index | list (Bild beim Zeigen/Fokus, reines CSS ab 44 rem, darunter Vorschaubild) · grid (Porträts) · works (erstes Werk) · az (Register, Spaltensatz) |
| artist_profile | split · name. JSON-LD `Person`, Bilder und Videos, „Verfügbare Werke anfragen“ |
| artworks „Werke“ | grid (Werk eingepasst, unten bündig) · masonry (CSS-Spalten) · salon (Flex-Linie, Höhen in `cqi`, Angaben als `table-caption`) · room (Viewing Room, `scroll-snap` proximity). Quelle automatisch (Detailseite Künstler/Ausstellung/Werk) oder gewählt; Filter (gallery.js, aria-pressed, aria-live); Anfrage-Button |
| artwork_detail | Abbildung im Originalformat, weitere Ansichten (Lightbox), Videos, vollständiges Museumsschild (Titel = H1), Anfrage, weitere Werke; JSON-LD `VisualArtwork` (+ `Offer` bei sichtbarem Preis) |
| visit „Besuch“ | columns · split. „Heute geöffnet/geschlossen“ (inkl. Ausnahmen), abweichende Zeiten (60 Tage), Orte (Stammdaten + Website → Galerie → weitere Orte) mit OSM-Link, Eintritt, Termine |

Kern-Block `contact`: liest `?werk=slug` und füllt das Formularfeld `werk` vor (Anfrage-Ziel unter Website → Galerie).

## Design-Tokens (Gruppe „Galerie“)

| Token | Ausgabe |
|---|---|
| `artframe` | Klasse `af-{flat|wall|mat}` – Schatten bzw. Passepartout auf `.art img` (Werke nie beschnitten) |
| `caption` | `cap-{museum|minimal|line}` – Museumsschild, knapp, eine Zeile (Detailseiten immer vollständig, `.cap--full`) |
| `dots` | `has-dots` – Punkt neben dem Wort (Farben `--g-avail`, `--g-sold`, kontrastgeprüft ≥ 3:1) |
| `price_mode` | serverseitig in `galerie_price()`: je Werk · immer „auf Anfrage“ · nie; verkauft = nie |
| `date_style` | serverseitig in `galerie_dates()`: long · numeric · relative („Bis …“, „Ab …“) |
| `label_style` | `lbl-{text|pill|dot|outline}` – Etikett „Jetzt/Demnächst/Archiv“ |
| `list_density` | `ld-*` + `--g-density` (Abstände in Listen und Rastern) |

Navigation: fluid-Kopfvarianten (Leiste, zentriert, geteilt, Seitenleiste, minimal). Fußbereich „Spalten“ zeigt die Öffnungszeiten.

### Vorlagen
White Cube (Standard) · Salon (EB Garamond, Bordeaux, Werke an der Wand) · Nacht (dunkel von Anfang an, Instrument Serif) ·
Kunstverein (Space Grotesk, Signalblau/Neongelb, Mono-Etiketten) · Archiv (IBM Plex Mono, Katalogzeile, numerische Daten,
Seitenleiste) · Atelier (Fraunces, Creme/Terrakotta, Passepartout). Prüfen: `php kits/galerie/tools/contrast.php`.

## Medien für die Redaktion (Ziehen & Ablegen)

Nur angemeldet und mit Recht `data.edit` auf die Tabelle (`Core\Data\EntryEdit::current()`), nie im Seiten-Cache:

- **Detailseiten** (Werk, Ausstellung, Künstler): Kasten „Bilder und Videos“ – Ablagefläche + „Dateien auswählen …“ +
  „Aus der Mediathek …“; Hochladen über `window.CMSMedia.Uploader` (Stücke, Fortschritt, Typ-/Größenprüfung mit deutschen
  Meldungen), Alt-Text aus den Angaben vorgeschlagen und automatisch gestartet; Reihenfolge ↑/↓, Entfernen; jede Änderung sofort
  per `POST /admin/api/entries/{tabelle}/{id}` (`partial`). Videos nie an Platz 1 (Hauptbild = Bildfeld).
- **Seiten-Editor, Block „Werke“:** „Neues Werk aus Foto“ – je Foto ein Entwurf (`POST …/werke/new`, Titel aus dem Dateinamen)
  und die Seitenleiste des Kerns öffnet sich (Element mit `data-entry-edit`). Skript per `editor_js`, Ereignis-Delegation
  (übersteht das Neuzeichnen der Vorschau). Die Medien gehören zum Eintrag, nicht zum Block – daher kein `markDirty()`/Rückgängig
  des Seiten-Editors; gespeichert wird sofort.

### Vorschläge für den Kern

1. **Feldtyp „Medienliste“ (`gallery`) für Datentabellen** – geordnete Liste von Medien-IDs (Bilder und Videos), gespeichert als
   JSON, mit Mehrfachauswahl, Sortieren und Ablage in Verwaltung und Seitenleiste. Ersetzt die nummerierten Felder `ansicht_n`;
   `galerie_media_slots()` würde ein solches Feld einfach als „alle Plätze“ lesen.
2. **Dateifeld mit erlaubten Arten `video`** (`DataForms::FILE_KINDS`), damit `accept` für Bild/Video-Felder sprechend gesetzt
   werden kann (heute nimmt ein Dateifeld der Verwaltung jede Datei der Mediathek).
3. **Öffentliche JS-Schnittstelle `CMSEntryEdit.open(endpoint)`** statt des Umwegs über ein Element mit `data-entry-edit`.

## Demo

`seed.php` → `galerie_demo_install()`: fünf erfundene Künstler, acht Ausstellungen (Daten relativ zum Tag der Anlage),
sechzehn Werke, Anfragen-Formular, Seiten (Start, Ausstellungen, Künstler, Werke, Viewing Room, Die Galerie, Kabinett,
Besuch) und drei Detailseiten. **Alle Bilder sind Platzhalter (GD):** abstrakte „Werke“, daraus montierte
Ausstellungsansichten und Monogramme – keine Fotos, keine fremden Werke. Ein stummes Platzhalter-Video (Farbverlauf) entsteht,
wenn `ffmpeg` auf dem Server läuft (`Core\Ffmpeg::available()`), sonst ohne Video.
Bestehende Website: `CMS_SITE=… php kits/galerie/tools/demo.php [--force|--remove|--data-only] [--preset=salon]`.

## Mehrsprachigkeit

Feste Website-Texte über `lt()` → `lang/site/en.php`; Verwaltung (Blöcke, Design, Website, Medienverwaltung) → `lang/en.php`.
Prüfen: `CMS_SITE=… php bin/console i18n:missing en [--site-texts]`.

## Lizenzen

Schriften: SIL Open Font License 1.1 (installiert vom Schriften-Manager, Lizenz je Schrift in `public/assets/fonts/installed/{id}/LICENSE.txt`). Symbole: Phosphor (Kern-Sprite, MIT).
Demo-Bilder und -Video: automatisch erzeugt, frei verwendbar.
