# Kit „glas“

Glassmorphism für KLXM Studio – **mit Lesbarkeit zuerst**: matte, durchscheinende Glasflächen (backdrop-filter:
Unschärfe + Sättigung) über einem weichen, gemalten Farbfeld aus radialen Verläufen, Lichtkanten (1-px-Innenkante mit
Alpha), weiche, getönte Tiefe, ein schwebender gläserner Kopf, Glaskarten und -dialoge und eine sanft bewegte „Aurora“ im
Einstieg. Keine Bilddateien für Hintergründe, keine externen Anfragen, keine Cookies, Schriften lokal, CSP-fest (keine
Inline-Skripte/-Styles), WCAG 2.2 AA, MIT.

## Lesbarkeit und Rücksicht

| Situation | Verhalten |
|---|---|
| Text auf Glas | Deckkraft der Glastönung je Farbschema und Glasdichte (`gd-light/standard/dense`: hell .62/.72/.84, dunkel .68/.76/.86) – geprüft über der ungünstigsten Feldfarbe, inkl. `saturate(1.4)` des Hintergrundfilters und Lichtschimmer: ≥ 4,5:1 |
| Text direkt auf dem Farbfeld | Feldstärke hell .58 (sanft) / .78 (kräftig), dunkel .42 / .60 – ebenfalls ≥ 4,5:1 |
| Kopf, Glasblatt, Aufklappmenüs, Such-Popover | Deckkraft .80 (hell) / .82 (dunkel) – geprüft über Schwarz, Weiß und allen Feldfarben |
| `prefers-reduced-transparency: reduce`, Design „Ohne Transparenz“ | deckende Flächen, kein Farbfeld, keine Unschärfe, Aurora abgeschwächt |
| `prefers-contrast: more` | deckend, Nebentext = Fließtext, kräftigere Linien, keine Körnung |
| ohne `backdrop-filter`-Unterstützung | Mindestdeckkraft .94 |
| Touch-Geräte / schmale Fenster | Karten ohne Unschärfe (Deckkraft +.10), Kopf/Blätter mit 60 % Unschärfe, Aurora ruht |
| `prefers-reduced-motion` / Design „Sanfte Bewegung“ aus | keine Animation; außer Sicht pausiert die Aurora ohnehin (IntersectionObserver) |
| `forced-colors` | Systemfarben, Kanten sichtbar, Farbfeld aus |

Prüfung aller Vorlagen × Schemata × Glasdichten × Feldstärken: `php themes/glas/tools/contrast.php` (Exit-Code 1 bei
Verstößen, `-v` zeigt die Tiefstwerte). Das Modell dort ist identisch mit `assets/css/_tokens.css`.

## Struktur

```
themes/glas/
├── theme.php        15 Blöcke + Kern-Blöcke, Website (Schlüssel wie „essenz“/„fluid“), conditional_css, 'design' => design.php
├── design.php       Tokens (12 Farben hell/dunkel, Glas & Farbfeld, 4 Schriften + System, Stufen, Radius, Kopf/Fuß, Bewegung) + 4 Vorlagen
├── functions.php    Helfer glas_* (Kontakt, Zeiten inkl. „Jetzt geöffnet“, Menü + Fit-Schätzung, Abschnittskopf, JSON-LD, App-Info)
├── seed.php         Startinhalte „Lumen Labs – App-Entwicklung & UX (fiktiv)“ + 'after' → Musterseiten (tools/demo-content.php)
├── blocks/          hero, richtext, text_image, features, cards, stats, steps, team, quote, faq, cta, contact, downloads, video, map
├── templates/       layout, error, maintenance, offline; partials/: header (Glas-Dock u. a.), tabbar, sheet (Glasblatt), brand, footer, hours, langswitch,
│                    section, video-embed (Zwei-Klick + window.cmsConsent + Media::posterFor), editor, toolbar
├── assets/css/      site.css (_tokens, _base, _header, _hero – renderblockierend) · overlay.css (+ _footer: Fußbereich,
│                    Aufklappmenüs, Such-Popover, Glasblatt – am Ende von <body>) · h-aurora/h-statement/h-split/h-command/h-video/h-stack (Einstiegs-Varianten)
│                    · opt-header-*/opt-footer-* (nur gewählte Option) · b-*.css, orb.css (je Block) · data, media, calendar,
│                    dataform, sections, search (ersetzen die Kern-Stylesheets) · pages, editing, preview
├── assets/js/       site.js (≈ 5 KB) · video.js (nur mit Video-Block, ≈ 1,3 KB)
├── tools/           contrast.php (Glas-Kontrastmodell) · demo.php + demo-content.php (Musterseiten, GD-Bilder)
├── lang/            en.php (Verwaltung) · site/en.php (feste Website-Texte lt())
├── docs/manual.php  Handbuch (Gestaltungsprinzip, Design/Kopf/Fuß, Beschreibung aller Blöcke)
├── package.json     @fontsource-variable/{outfit,figtree,sora,urbanist} (OFL 1.1), @expo-google-fonts/outfit (TTF für App-Icons)
└── build.mjs        kopiert die variablen Schriften (latin + latin-ext) und erzeugt css/font-{key}.css
```

Build: `cd tools && pnpm run build` → `public/themes/glas/{css,js,fonts}`.

## Budgets (minifiziert, gemessen)

| | Wert |
|---|---|
| site.css (renderblockierend, alle Seiten) | ≈ 22,7 KB |
| Glas-Dock: opt-header-dock.css + dock-desktop.css (≥ 48 em) bzw. dock-tabbar.css (< 48 em, je per `media`) | 3,3 + 1,5 bzw. 2,1 KB |
| Schrift-CSS Standard (Outfit + Figtree) | 2 × ≈ 0,9 KB |
| **Grundlage Startseite** | **≈ 29,3 KB Desktop · 30,0 KB (29,3 KiB) Telefon** (< 30 KB; Design-Datei nur bei Abweichung vom Standard) |
| Block-CSS Startseite (h-aurora, orb, features, stats, steps, quote, cta, prose) | ≈ 18,6 KB, nur dort (gzip-übertragen deutlich weniger) |
| Einstieg, neue Varianten (nur mit der Variante) | h-command 5,5 KB (+ h-aurora 5,1 KB) · h-video 2,9 KB (+ Kern-hero.mjs ≈ 1,3 KB) · h-stack 3,5 KB · kein eigenes JS |
| overlay.css inkl. Fußbereich und Dock-Glaspanel (am Seitenende) | ≈ 11,4 KB |
| JS Startseite | site.js ≈ 7,1 KB (2,6 KB gzip; < 8 KB) |
| Schriften (WOFF2, variabel, latin) | Outfit ≈ 32 KB, Figtree ≈ 20 KB; nur Fließtext/Überschrift vorgeladen |

## Navigation: Glas-Dock (Standard)

| Teil | Umsetzung |
|---|---|
| Dock (≥ 48 em) | mittig schwebende Glasinsel, vom Rand gelöst; Lichtbrechung (schräger Glanz) und 1-px-Lichtkante. Das Glas liegt auf `.dock::before` – so bleibt das Dock keine „Backdrop Root“ und das Aufklappmenü darin zeichnet den Seiteninhalt weich |
| Glaslinse | ein Element (`.dnav__lens`), gleitet per `translate` + Breite zum Punkt unter Zeiger oder Tastaturfokus und ruht auf der aktuellen Seite (`aria-current`/Zweig aktiv); 8 px Radius. Ohne JavaScript steht sie fest auf der aktuellen Seite, bei „Bewegung reduzieren“ springt sie ohne Gleiten |
| Marke | Glasperle (Verlauf der Feldfarben) + Name |
| Aktionen (rechts) | `.dock__actions`: Suche (Popover an der Lupe), Button im Kopf als Glastaste mit Symbol (Beschriftung ab 64 em), Menü-Schaltfläche, falls das Menü nicht passt. **Andockstelle für Kopfbereich-Aktionen** (Befehlssuche, Kontakt-Chip …) |
| Verdichten | beim Scrollen (IntersectionObserver auf einem Wächter-Element): niedriger, stärkere Unschärfe, Schatten – der Kopf hat feste Höhe, nichts verschiebt sich |
| Aufklappmenü | `<details>`-Disclosure (Tab, Escape, Pfeiltasten, Klick daneben – site.js), Glaspanel mit sanftem Auf­blenden/Skalieren, Symbol + Kurzbeschreibung (Meta-Beschreibung der Seite), ab vier Unterseiten zweispaltig; dahinter glimmt die Aurora. Symbole leitet `glas_nav_icon()` aus Titel/Adresse ab |
| Telefone (< 48 em) | Tab-Leiste unten: Start + drei Hauptseiten (Duoton-Symbol + Beschriftung, 44-px-Ziele, Linse + `aria-current` auf der aktuellen Seite) und „Mehr“ (Glasblatt mit allen Seiten, Suche, Kontakt, Sprache). `env(safe-area-inset-bottom)`, Platz unten für den Inhalt, Besucher-Chat per `--cms-chat-lift` darüber, blendet beim Scrollen nach unten aus und nach oben ein (nie bei „Bewegung reduzieren“), `data-cms-hide-editing` |
| Rückfälle | deckend bei „Transparenz reduzieren“, „Mehr Kontrast“, „Ohne Transparenz“ und ohne backdrop-filter; Kontrast der Menüschrift über Schwarz, Weiß, Farbfeld und Aurora sowie auf der Linse geprüft (`tools/contrast.php`) |

Die übrigen Varianten (schwebende Leiste, Leiste, zentriert, minimal) bleiben wählbar (Verwaltung → Design → Kopf & Fuß).
Hinweis Consent Kit: Banner und Einstellungen liegen immer über der Tab-Leiste; die kleine runde Schaltfläche für die
Einstellungen (unten links/rechts, Shadow DOM) kennt noch keinen Versatz und kann auf Telefonen einen Tab überdecken.

## Design-Tokens (design.php → `--g-*` bzw. Klassen am `<html>`)

| Gruppe | Tokens |
|---|---|
| Farben | accent `--g-a`, accent_ink `--g-a-ink`, on_accent, ink, text, muted, background `--g-bg`, glass `--g-glass`, field_1–3 `--g-f1…3`, dark_section – je hell/dunkel |
| Glas & Farbfeld | density `gd-{light,standard,dense}`, blur `--g-blur`, field `ff-{soft,rich,plain}`, grain `has-grain`, solid `is-solid` |
| Typografie | font_body, font_head, fs_min/fs_max, ratio, heading_weight, heading_tracking |
| Form & Abstände | radius `--g-radius` (Glasflächen; Buttons/Felder immer 8 px), buttons `btn-{soft,pill}`, space, wrap |
| Kopf & Fuß | header `hdr-{dock,floating,bar,centered,index}` (Standard: Glas-Dock), header_sticky, footer `ft-{glass,panel,simple}` |
| Kopfbereich: Suche & Aktionen | `ha_*` aus `Core\HeaderActions::designGroup()` – Standard: Glas-Befehlsfeld im Dock + Kontakt-Menü als Glaspaneel (Handlungsaufruf, Suche, Anordnung, Kontakt-Chip, Sprache, Social, Anmelden; `header_actions()`) |
| Bewegung & Schema | motion `has-motion`, dark `has-dark` (kein Umschalter – wie die übrigen Kits folgt das Schema dem Gerät) |

Vorlagen: **Aurora** (Standard: Violett/Türkis/Rosé) · **Lagune** (Türkis/Petrol/Blau, klares Glas, Leiste, Sora) ·
**Dämmerung** (Pfirsich/Koralle/Pflaume, kräftiges Feld, Pillen, Urbanist) · **Graphit** (Rauchglas, von Anfang an dunkel,
Milchglas, Minimal-Kopf).

## Blöcke

| Block | Varianten |
|---|---|
| hero | aurora (Glaspaneel über bewegter Aurora + Glasplättchen „Bezeichnung: Wert“, Bild im Glasrahmen oder Prisma) · split · statement (Glas-Chips) · compact · command (Befehlssuche: großes Glas-Suchfeld mit Vorschlägen beim Tippen und Chips über der Aurora, Pause-Schalter) · video (stummes Loop-Video vollflächig, Abdunkelung, Text auf dichtem Glas, Pause; „Bewegung reduzieren“ → Standbild) · stack (2–3 überlappende Glaskarten, fächern bei Zeiger/Fokus auf; Touch und „Bewegung reduzieren“ → ruhig aufgefächert) – Musterseite /labor/hero-varianten (`tools/demo.php --heroes`) |
| richtext | standard · article (Inhaltsverzeichnis im Glaspaneel, Lesezeit) · glass (Text auf Glas) |
| text_image | auto (abwechselnd) · right · left – Bild im Glasrahmen |
| features | cards (Glaskarten, Symbol in Glasperle) · panel (ein Glaspaneel) · list |
| cards | glass (Bild oben) · service (Symbol) · overlay (Text auf Glasleiste über dem Bild) |
| stats | rings (Glasringe mit Verlauf, Füllstand optional) · row (große Zahlen auf Glas + Pegel) |
| steps | steps (Glasperlen auf leuchtender Linie) · timeline |
| team | cards (Porträt oder Initialen in Glasperle, Rolle, Text, E-Mail) · compact |
| quote, faq, cta | single/grid · split/stacked (JSON-LD FAQPage) · aurora/band/minimal |
| contact, video, downloads, map | Glas + „Jetzt geöffnet“ · Zwei-Klick mit Consent-Kit (`window.cmsConsent`), VideoObject · PDF-Viewer · Karte im Glasrahmen |

Kern-Blöcke im Kit-Stil: data_list, data_fields, data_form, calendar, upcoming, gallery (Lichtkante, gläserne
Lightbox-Tasten), slideshow, stack_cards, dials (Skalen auf Glas über `--dial-*`), Suche (Popover an der Lupe),
Besucher-Chat (`cms_chat_launcher()`), `footer_links()` (z. B. „Cookie-Einstellungen“), Symbol-Sprite je Website (`icon()`).

## Musterseiten (Demo)

`CMS_SITE=<site> php themes/glas/tools/demo.php [--force|--remove] [--preset=lagune]` legt `/labor` an (Übersicht,
Projekte & Termine, Formular & Kontakt, Medien, Journal) – mit erzeugten Bildern (GD: Farbfelder mit Glasflächen,
erfundene App-Oberflächen und Telefon-Entwürfe; keine Fotos, keine Personen, keine Marken), Tabellen `labor_projekte`
(Detailseite `/projekte/…`), `labor_termine` (Wiederholung, ganztägig, Kategorien), `labor_anfragen` (öffentliches
Formular mit Bedingung). Alle Namen sind erfunden. Kartenpunkt: geografischer Mittelpunkt Deutschlands. Video: „Big Buck
Bunny“ © Blender Foundation, CC BY 3.0 (Zwei-Klick). Die Übersicht ist die Musterseite des Style-Editors (`design.sample`).

## Mehrsprachigkeit

Feste Texte über `lt('…')`, Übersetzungen in `lang/site/{sprache}.php`; Verwaltungsbegriffe in `lang/en.php`.
Prüfen: `php bin/console i18n:missing en --site-texts --site=…`.

## Grenzen

- Glas zeigt seine Unschärfe nur in Browsern mit `backdrop-filter` und nur mit feinem Zeiger ab 48 em Breite; sonst ist
  es bewusst nur getönt (Leistung beim Scrollen).
- Die Kontrastprüfung rechnet mit Mittelwerten (Unschärfe glättet); die feine Körnung (Deckkraft .05–.07) ist darin nicht
  enthalten. Eigene Feldfarben prüft der Style-Editor nur paarweise – für die Glas-Rechnung `tools/contrast.php` nutzen.
- Kein Hell/Dunkel-Umschalter auf der Website (wie bei den übrigen Kits folgt das Schema der Geräte-Einstellung).

## Lizenzen

Outfit, Figtree, Sora, Urbanist – SIL Open Font License 1.1 (`public/themes/glas/fonts/OFL-{key}.txt`; Outfit-TTF für
den Icon-Generator: `themes/glas/fonts/OFL.txt`). Symbole: Phosphor (Kern-Sprite, MIT). Beispielbilder: automatisch
erzeugt (GD), frei verwendbar.
