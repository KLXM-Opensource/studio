# CSS & JS – Kit „Praxis – Arzt- und Fachpraxen“ (praxis)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=praxis --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden (Layout, Wartung, offline): `css/blocks.css`, `css/card-back.css`, `css/form.css`, `css/hsearch.css`, `css/mnav.css`, `css/nav.css`, `css/site.css`, `js/form.js`, `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, 6 Einträge).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/praxis/`).

## 2. Tokens & Farben

- Kit-Variablen: `--c-*` an `:root`.
- Gesetzte Kern-Variablen: `--cal-accent`, `--cal-accent-ink`, `--cal-ev-bg`, `--cal-ev-ink`, `--cal-line`, `--cal-muted`, `--cal-radius`, `--cal-surface`, `--cal-today-bg`, `--cms-accent`, `--cms-card-bg`, `--cms-card-ink`, `--cms-card-line`, `--cms-chat-lift`, `--cms-gallery-gap`, `--cms-lb-bg`, `--cms-line`, `--cms-map-accent`, `--cms-map-bg`, `--cms-map-line`, `--cms-map-radius`, `--cms-muted`, `--cms-on-accent`, `--cms-radius` ….

## 3. Blöcke → Dateien

- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.

## 4. Animationen & Regeln

- Dateien mit Keyframes/Scroll-Animation: `css/mnav.css`, `css/site.css`.
- `prefers-reduced-motion` in `css/site.css`: ja · `forced-colors`: nein · `:focus-visible`: ja.
- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.

## 5. Overlays, Sheets, Dialoge

- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).

## 6. JavaScript & Ereignisse

- Skripte: `js/form.js`, `js/group.js`, `js/site.js`.
- Ereignisse: keine eigenen.

## 7. Sonderfälle

- _keine bekannt_

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=praxis --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Praxis – Arzt- und Fachpraxen“ (praxis 1.0.0)

### Von Vorlagen eingebunden (theme_asset() in templates/)

Feste Namen; zusammengesetzte (z. B. `css/nav-{stil}.css`) erkennt der Generator nicht.

- `css/blocks.css` – templates/maintenance.php, templates/offline.php
- `css/card-back.css` – templates/partials/contact-card.php
- `css/form.css` – templates/partials/contact-card.php
- `css/hsearch.css` – templates/layout.php
- `css/mnav.css` – templates/layout.php, templates/partials/header.php
- `css/nav.css` – templates/layout.php
- `css/site.css` – templates/layout.php, templates/maintenance.php, templates/offline.php
- `js/form.js` – templates/partials/contact-card.php, templates/partials/form.php
- `js/site.js` – templates/layout.php

### Bedingt geladen – theme.php → conditional_css

| Datei | lädt bei |
|---|---|
| `css/blocks.css` | text_image, text_video, image_wide, steps, cta, downloads, people, job, richtext, data_form, notice, teaser_tiles:image, quote:full, video, text_columns:columns, quick_contact |
| `css/media-blocks.css` | gallery, slideshow, stack_cards |
| `css/hero-media.css` | hero |
| `css/data-fields.css` | data_fields |
| `css/form.css` | form |
| `css/prose.css` | @rich |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `accordion` Akkordeon / FAQ (B09) | `blocks/accordion.php` | – | – |
| `contact` Kontakt | `blocks/contact.php` | – | – |
| `cta` Handlungsaufruf (B11) | `blocks/cta.php` | band, box | `css/blocks.css` |
| `doctors` Ärztinnen und Ärzte | `blocks/doctors.php` | – | – |
| `downloads` Downloads (B12) | `blocks/downloads.php` | – | `css/blocks.css` |
| `hero` Hero mit Themen | `blocks/hero.php` | silk, color, image, video | `css/hero-media.css` |
| `image_wide` Bild breit (B07) | `blocks/image_wide.php` | 21-9, 16-9 | `css/blocks.css` |
| `job` Stellenangebot (B14) | `blocks/job.php` | – | `css/blocks.css` |
| `map` Karte | `blocks/map.php` | – | – |
| `not_found` 404-Vorschläge | `blocks/not_found.php` | – | – |
| `notice` Hinweisbox (B10) | `blocks/notice.php` | – | `css/blocks.css` |
| `people` Personen kompakt (B13) | `blocks/people.php` | – | `css/blocks.css` |
| `quick_contact` Schnellkontakt-Karte | `blocks/quick_contact.php` | – | `css/blocks.css` |
| `quote` Zitat | `blocks/quote.php` | inset, full | `css/blocks.css` (full) |
| `richtext` Fließtext | `blocks/richtext.php` | – | `css/blocks.css` |
| `services` Leistungen (nummeriert) | `blocks/services.php` | – | – |
| `steps` Ablauf in Schritten (B08) | `blocks/steps.php` | – | `css/blocks.css` |
| `team_photo` Teamfoto + Text (veraltet) | `blocks/team_photo.php` | – | – |
| `teaser_tiles` Teaser-Kacheln | `blocks/teaser_tiles.php` | plain, image | `css/blocks.css` (image) |
| `text_columns` Überschrift + Text | `blocks/text_columns.php` | stacked, columns, compact | `css/blocks.css` (columns) |
| `text_image` Text + Bild | `blocks/text_image.php` | right, left | `css/blocks.css` |
| `text_video` Text + Video | `blocks/text_video.php` | left, right | `css/blocks.css` |
| `video` Video (breit) | `blocks/video.php` | 16-9, 4-3 | `css/blocks.css` |

### Variablen je Stylesheet

**css/blocks.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--lay-card-pad` | `clamp(28px,4vw,44px)` | `.lay-grid` | – |
| `--lay-card-radius` | `24px` | `.lay-grid` | – |
| `--lay-stack` | `1000px` | `.lay-grid` | – |
| `--lay-stack-tablet` | `1000px` | `.lay-grid` | – |
| `--row-card-pad` | `clamp(28px,4vw,44px)` | `.sec-row` | – |
| `--row-card-radius` | `24px` | `.sec-row` | – |
| `--row-stack` | `1000px` | `.sec-row` | – |

**css/calendar.css** (9)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cal-accent` | `var(--c-bordeaux)` | `.cal` | 1 |
| `--cal-accent-ink` | `var(--c-white)` | `.cal` | 1 |
| `--cal-ev-bg` | `var(--c-rose-1)` | `.cal` | 1 |
| `--cal-ev-ink` | `var(--c-ink)` | `.cal` | 1 |
| `--cal-line` | `var(--c-line)` | `.cal` | 1 |
| `--cal-muted` | `var(--c-text-2)` | `.cal` | 1 |
| `--cal-radius` | `22px` | `.cal` | – |
| `--cal-surface` | `var(--c-gray-50)` | `.cal` | 2 |
| `--cal-today-bg` | `var(--c-rose-1)` | `.cal` | 1 |

**css/data.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dl-accent` | `var(--c-bordeaux)` | `.dl,.df` | 1 |
| `--dl-muted` | `var(--c-text-2)` | `.dl,.df` | 1 |
| `--dl-radius` | `22px` | `.dl,.df` | – |
| `--dl-surface` | `var(--c-gray-50)` | `.dl,.df` | 1 |

**css/dataform.css** (13)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dff-accent` | `var(--c-bordeaux)` | `.dff-wrap` | 1 |
| `--dff-bg` | `#fff` | `.dff-wrap` | 1 |
| `--dff-err` | `#A3201A` | `.dff-wrap` | 1 |
| `--dff-err-bg` | `#FBEAEA` | `.dff-wrap` | 1 |
| `--dff-err-ink` | `#3b0d0a` | `.dff-wrap` | 1 |
| `--dff-focus` | `var(--c-bordeaux)` | `.dff-wrap` | 1 |
| `--dff-gap` | `20px` | `.dff-wrap` | – |
| `--dff-ink` | `var(--c-ink)` | `.dff-wrap` | 1 |
| `--dff-line` | `#8A9290` | `.dff-wrap` | 2 |
| `--dff-muted` | `var(--c-text-2)` | `.dff-wrap` | 1 |
| `--dff-radius` | `12px` | `.dff-wrap` | – |
| `--dff-w-normal` | `46rem` | `.dff-wrap` | – |
| `--dff-w-text` | `calc(900px - 2 * var(--gutter))` | `.dff-wrap` | – |

**css/media-blocks.css** (14)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-accent` | `var(--c-bordeaux)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 1 |
| `--cms-card-bg` | `#fff` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-card-ink` | `var(--c-ink)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-card-line` | `var(--c-line)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 1 |
| `--cms-gallery-gap` | `clamp(12px,1.6vw,20px)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-lb-bg` | `rgba(28,6,14,.96)` | `.cms-lb` | – |
| `--cms-line` | `var(--c-input)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 1 |
| `--cms-muted` | `var(--c-text-2)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 2 |
| `--cms-on-accent` | `#fff` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 1 |
| `--cms-radius` | `18px` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-shadow` | `0 40px 80px -40px rgba(30,4,14,.38)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 1 |
| `--cms-stack-step` | `28px` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-stack-top` | `112px` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-surface` | `var(--c-gray-100)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |

**css/prose.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--rt-accent` | `var(--c-bordeaux)` | `:root` | 1 |
| `--rt-danger` | `#B42318` | `:root` | 1 |
| `--rt-muted` | `var(--c-text-3)` | `:root` | 1 |
| `--rt-success` | `#1A7240` | `:root` | 1 |
| `--rt-warning` | `#8A4B00` | `:root` | 1 |

**css/site.css** (40)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--c-apricot` | `#F6C9A8` | `:root` | – |
| `--c-apricot-2` | `#F0C4A6` | `:root` | – |
| `--c-bordeaux` | `#7A1F35` | `:root` | – |
| `--c-bordeaux-dark` | `#5E1628` | `:root` | – |
| `--c-bordeaux-hero` | `#6A1530` | `:root` | – |
| `--c-gray-100` | `#F0F0F2` | `:root` | – |
| `--c-gray-50` | `#F2F2F3` | `:root` | – |
| `--c-ink` | `#16201E` | `:root` | – |
| `--c-input` | `#D4D4D8` | `:root` | – |
| `--c-line` | `#E6E6E9` | `:root` | – |
| `--c-on-dark` | `#D6DAD8` | `:root` | – |
| `--c-ph` | `#E4E4E7` | `:root` | – |
| `--c-ph-2` | `#DCDCE0` | `:root` | – |
| `--c-rose-1` | `#FBE9EC` | `:root` | – |
| `--c-rose-2` | `#F3E3E6` | `:root` | – |
| `--c-rose-3` | `#EBC9CF` | `:root` | – |
| `--c-sep` | `#DADADD` | `:root` | 2 |
| `--c-text-2` | `#3F4A47` | `:root` | 2 |
| `--c-text-3` | `#5A6461` | `:root` | 2 |
| `--c-white` | `#FFFFFF` | `:root` | – |
| `--c0-sep` | `var(--c-sep)` | `:root` | – |
| `--c0-text-2` | `var(--c-text-2)` | `:root` | – |
| `--c0-text-3` | `var(--c-text-3)` | `:root` | – |
| `--cms-chat-lift` | `76px` | `:root` | – |
| `--cms-map-accent` | `var(--c-bordeaux)` | `.cms-map` | – |
| `--cms-map-bg` | `#EBEBED` | `.cms-map` | – |
| `--cms-map-line` | `var(--c-ph-2)` | `.cms-map` | – |
| `--cms-map-radius` | `24px` | `.cms-map` | – |
| `--ease` | `cubic-bezier(.2,.7,.2,1)` | `:root` | – |
| `--font` | `"Hanken Grotesk",system-ui,-apple-system,"Segoe UI",sans-serif` | `:root` | – |
| `--fx` | `0%` | `.fx0` | 10 |
| `--fy` | `0%` | `.fy0` | 10 |
| `--gap-split` | `clamp(40px,6vw,96px)` | `:root` | – |
| `--gutter` | `clamp(20px,4vw,40px)` | `:root` | – |
| `--r-big` | `clamp(24px,3vw,40px)` | `:root` | – |
| `--r-card` | `24px` | `:root` | – |
| `--sec-y` | `clamp(72px,10vw,140px)` | `:root` | – |
| `--sec-y-s` | `clamp(40px,6vw,80px)` | `:root` | – |
| `--shadow-card` | `0 40px 80px -30px rgba(30,4,14,.65)` | `:root` | – |
| `--turn` | `.6s cubic-bezier(.65,0,.35,1)` | `.flip__inner` | – |

### Bewegung & Barrierefreiheit je Datei

| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |
|---|---|---|---|---|---|
| `css/blocks.css` | – | ja | ja | – | – |
| `css/card-back.css` | – | ja | ja | – | – |
| `css/data.css` | – | ja | – | – | – |
| `css/dataform.css` | – | ja | – | – | ja |
| `css/hero-media.css` | – | ja | ja | – | – |
| `css/mnav.css` | mnav-in | ja | ja | – | – |
| `css/nav.css` | – | ja | – | – | – |
| `css/site.css` | silkA, silkB, sheen, base, fill, openpulse | ja | ja | – | ja |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/form.js` | – | – | – | – | 4,8 KB (Quelle) |
| `js/group.js` | – | – | – | – | 0,3 KB (Quelle) |
| `js/site.js` | ja | ja | – | ja | 15,2 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

_Keine Namensraum-Ereignisse._

Globale Objekte: `praxisForm` (js/form.js)

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-active` | templates/partials/header.php |
| `data-apple` | assets/js/site.js |
| `data-apple-name` | assets/js/site.js |
| `data-assets` | templates/partials/contact-card.php, assets/js/site.js |
| `data-bg` | blocks/hero.php, assets/js/site.js |
| `data-bound` | assets/js/site.js |
| `data-central` | templates/partials/contact-card.php |
| `data-cf` | assets/js/form.js |
| `data-cms-hide-editing` | templates/layout.php |
| `data-cms-map-js` | assets/js/site.js |
| `data-cms-map-load` | assets/js/site.js |
| `data-css` | templates/partials/header.php, assets/js/site.js |
| `data-delay` | blocks/accordion.php, blocks/contact.php, blocks/doctors.php, blocks/hero.php, blocks/quote.php … |
| `data-flip` | templates/layout.php, templates/partials/contact-card.php, templates/partials/header.php, assets/js/site.js |
| `data-flip-back` | templates/partials/contact-card.php, templates/partials/form.php, assets/js/site.js |
| `data-flip-title` | templates/partials/contact-card.php, assets/js/site.js |
| `data-flipcard` | templates/partials/contact-card.php, assets/js/form.js, assets/js/site.js |
| `data-form` | templates/partials/form.php, assets/js/form.js, assets/js/group.js |
| `data-go` | blocks/hero.php, assets/js/site.js |
| `data-greeting` | blocks/hero.php, assets/js/site.js |
| `data-group` | assets/js/form.js |
| `data-hero` | blocks/hero.php, assets/js/site.js |
| `data-hero-video` | blocks/hero.php, assets/js/site.js |
| `data-l10n` | templates/layout.php, assets/js/form.js, assets/js/site.js, functions.php |
| `data-label` | blocks/hero.php |
| `data-leave` | templates/partials/contact-card.php, assets/js/site.js |
| `data-main` | blocks/hero.php |
| `data-max-bytes` | templates/partials/form.php |
| `data-now` | assets/js/site.js |
| `data-now-closed` | templates/partials/contact-card.php, assets/js/site.js, functions.php |
| `data-now-open` | templates/partials/contact-card.php, assets/js/site.js, functions.php |
| `data-openb` | assets/js/site.js, functions.php |
| `data-panel` | templates/partials/contact-card.php, assets/js/site.js |
| `data-pause` | blocks/hero.php, assets/js/site.js |
| `data-reopen` | templates/partials/contact-card.php, assets/js/site.js |
| `data-reveal` | blocks/accordion.php, blocks/contact.php, blocks/cta.php, blocks/doctors.php, blocks/downloads.php … |
| `data-route` | assets/js/site.js |
| `data-route-via` | assets/js/site.js |
| `data-scope` | templates/layout.php |
| `data-slide` | blocks/hero.php |
| `data-spy` | templates/partials/header.php, assets/js/site.js |
| `data-sw` | templates/layout.php |
| `data-title` | templates/partials/contact-card.php, assets/js/site.js |
| `data-today` | templates/partials/contact-card.php, assets/js/site.js |

<!-- docs:assets:end -->
