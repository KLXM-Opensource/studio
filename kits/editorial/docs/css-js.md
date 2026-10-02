# CSS & JS – Kit „Editorial (Magazin & Verband)“ (editorial)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=editorial --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden (Layout, Wartung, offline): `css/hero.css`, `css/preview.css`, `css/site.css`, `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, 8 Einträge).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/editorial/`).

## 2. Tokens & Farben

- Kit-Variablen: `--e-*` an `:root`.
- Gesetzte Kern-Variablen: `--cal-accent`, `--cal-accent-ink`, `--cal-ev-bg`, `--cal-ev-ink`, `--cal-focus`, `--cal-line`, `--cal-muted`, `--cal-radius`, `--cal-surface`, `--cal-today-bg`, `--cms-accent`, `--cms-card-bg`, `--cms-card-ink`, `--cms-card-line`, `--cms-gallery-gap`, `--cms-lb-bg`, `--cms-light-ink`, `--cms-line`, `--cms-map-accent`, `--cms-map-bg`, `--cms-map-line`, `--cms-map-radius`, `--cms-muted`, `--cms-on-accent` ….

## 3. Blöcke → Dateien

- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.

## 4. Animationen & Regeln

- Dateien mit Keyframes/Scroll-Animation: `css/data.css`, `css/site.css`, `css/story.css`.
- `prefers-reduced-motion` in `css/site.css`: ja · `forced-colors`: nein · `:focus-visible`: ja.
- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.

## 5. Overlays, Sheets, Dialoge

- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).

## 6. JavaScript & Ereignisse

- Skripte: `js/blocks.js`, `js/site.js`.
- Ereignisse: keine eigenen.

## 7. Sonderfälle

- _keine bekannt_

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=editorial --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Editorial (Magazin & Verband)“ (editorial 1.0.0)

### Von Vorlagen eingebunden (theme_asset() in templates/)

Feste Namen; zusammengesetzte (z. B. `css/nav-{stil}.css`) erkennt der Generator nicht.

- `css/hero.css` – templates/error.php, templates/maintenance.php, templates/offline.php
- `css/preview.css` – templates/layout.php
- `css/site.css` – templates/layout.php, templates/maintenance.php, templates/offline.php
- `js/site.js` – templates/layout.php

### Bedingt geladen – theme.php → conditional_css

| Datei | lädt bei |
|---|---|
| `css/hero.css` | hero |
| `css/hero-x.css` | hero:issue, hero:agenda, hero:voice |
| `css/rich.css` | @rich |
| `css/story.css` | richtext, figure, quote, text_image, article, faq, video, data_fields |
| `css/blocks.css` | features, stats, faq, cta, logos, downloads, contact, video, map |
| `css/data.css` | teasers |
| `css/dataform.css` | cta:newsletter |
| `js/blocks.js` | article, richtext |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `article` Artikel (Detailseite) | `blocks/article.php` | – | `css/story.css`, `js/blocks.js` |
| `contact` Kontakt | `blocks/contact.php` | – | `css/blocks.css` |
| `cta` Handlungsaufruf / Newsletter | `blocks/cta.php` | band, box, newsletter | `css/blocks.css`, `css/dataform.css` (newsletter) |
| `data_list` Datenliste | `blocks/data_list.php` | – | – |
| `downloads` Downloads | `blocks/downloads.php` | – | `css/blocks.css` |
| `faq` Fragen & Antworten | `blocks/faq.php` | – | `css/story.css`, `css/blocks.css` |
| `features` Rubriken / Themen | `blocks/features.php` | – | `css/blocks.css` |
| `figure` Bild (mit Unterschrift) | `blocks/figure.php` | wide, text, bleed, margin | `css/story.css` |
| `hero` Aufmacher / Seitenkopf | `blocks/hero.php` | split, centered, cover, compact, issue, agenda, voice | `css/hero.css`, `css/hero-x.css` (issue, agenda, voice) |
| `logos` Partner & Förderer | `blocks/logos.php` | – | `css/blocks.css` |
| `map` Karte | `blocks/map.php` | – | `css/blocks.css` |
| `quote` Zitat | `blocks/quote.php` | – | `css/story.css` |
| `richtext` Text (Artikel) | `blocks/richtext.php` | – | `css/story.css`, `js/blocks.js` |
| `stats` Zahlen & Fakten | `blocks/stats.php` | – | `css/blocks.css` |
| `teasers` Anreißer (von Hand) | `blocks/teasers.php` | grid, lead, list, brief | `css/data.css` |
| `text_image` Text + Bild | `blocks/text_image.php` | right, left | `css/story.css` |
| `video` Video | `blocks/video.php` | wide, text | `css/story.css`, `css/blocks.css` |

### Variablen je Stylesheet

**css/blocks.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-map-accent` | `var(--e-a)` | `.cms-map` | – |
| `--cms-map-bg` | `var(--e-surface-2)` | `.cms-map` | – |
| `--cms-map-line` | `var(--e-line)` | `.cms-map` | – |
| `--cms-map-radius` | `var(--e-radius)` | `.cms-map` | – |
| `--ico-2-opacity` | `.22` | `.feat__ico` | – |

**css/calendar.css** (11)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cal-accent` | `var(--e-a)` | `.cal` | – |
| `--cal-accent-ink` | `var(--e-a-on)` | `.cal` | – |
| `--cal-ev-bg` | `var(--e-a-soft)` | `.cal` | – |
| `--cal-ev-ink` | `var(--e-ink)` | `.cal` | – |
| `--cal-focus` | `var(--e-focus)` | `.cal` | – |
| `--cal-line` | `var(--e-line)` | `.cal` | – |
| `--cal-muted` | `var(--e-muted)` | `.cal` | – |
| `--cal-radius` | `var(--e-radius)` | `.cal` | – |
| `--cal-surface` | `var(--e-box)` | `.cal` | – |
| `--cal-today-bg` | `var(--e-a-soft)` | `.cal` | – |
| `--e-bg` | `var(--e-sec-bg)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cal-grid` | – |

**css/data.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dl-accent` | `var(--e-a)` | `.dl,.df` | – |
| `--dl-gap` | `var(--e-gap)` | `.dl,.df` | – |
| `--dl-muted` | `var(--e-muted)` | `.dl,.df` | – |
| `--dl-radius` | `var(--e-radius)` | `.dl,.df` | – |
| `--dl-surface` | `var(--e-box)` | `.dl,.df` | – |

**css/dataform.css** (10)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dff-accent` | `var(--e-a)` | `.dff-wrap` | – |
| `--dff-bg` | `var(--e-sec-bg,var(--e-bg))` | `.dff-wrap` | 2 |
| `--dff-err` | `var(--e-danger)` | `.dff-wrap` | – |
| `--dff-err-bg` | `color-mix(in srgb,var(--e-danger) 10%,var(--e-sec-bg,var(--e-bg)))` | `.dff-wrap` | – |
| `--dff-focus` | `var(--e-focus)` | `.dff-wrap` | – |
| `--dff-ink` | `var(--e-ink)` | `.dff-wrap` | – |
| `--dff-line` | `var(--e-muted)` | `.dff-wrap` | – |
| `--dff-muted` | `var(--e-muted)` | `.dff-wrap` | – |
| `--dff-radius` | `var(--e-radius)` | `.dff-wrap` | – |
| `--e-danger` | `color-mix(in srgb,#E5484D 66%,var(--e-ink))` | `.dff-wrap` | – |

**css/hero-x.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--n` | `2` | `.agd__cols--2,.agd__cols--4` | 2 |

**css/hero.css** (9)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--e-btn-bg` | `var(--e-on-dark)` | `.aufm--cover` | – |
| `--e-btn-bg-hover` | `color-mix(in srgb,var(--e-on-dark) 86%,var(--e-night))` | `.aufm--cover` | – |
| `--e-btn-ink` | `var(--e-night)` | `.aufm--cover` | – |
| `--e-focus` | `var(--e-on-dark)` | `.aufm--cover` | – |
| `--e-ink` | `var(--e-on-dark)` | `.aufm--cover` | – |
| `--e-kick` | `var(--e-on-dark)` | `.aufm--cover` | – |
| `--e-link` | `var(--e-on-dark)` | `.aufm--cover` | – |
| `--e-muted` | `color-mix(in srgb,var(--e-on-dark) 86%,transparent)` | `.aufm--cover` | – |
| `--e-text` | `var(--e-on-dark)` | `.aufm--cover` | – |

**css/media.css** (17)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-accent` | `var(--e-a)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-card-bg` | `var(--e-bg)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 1 |
| `--cms-card-ink` | `var(--e-text)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-card-line` | `var(--e-line)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-gallery-gap` | `clamp(10px,1.6vw,20px)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-lb-bg` | `color-mix(in srgb,var(--e-night) 97%,transparent)` | `.cms-lb` | – |
| `--cms-light-ink` | `var(--e-ink)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-line` | `var(--e-line-strong)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-muted` | `var(--e-muted)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-on-accent` | `var(--e-a-on)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-radius` | `var(--e-radius)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-shadow` | `0 30px 60px -36px color-mix(in srgb,var(--e-shade) 50%,transparent)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-stack-top` | `calc(var(--e-header-h) + 32px)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-surface` | `var(--e-surface-2)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--e-btn-bg` | `var(--e-on-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--e-btn-bg-hover` | `color-mix(in srgb,var(--e-on-dark) 86%,var(--e-night))` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--e-btn-ink` | `var(--e-night)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |

**css/preview.css** (18)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--e-a-soft` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-box` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-btn-bg` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-btn-bg-hover` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-btn-ink` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-duo` | `color-mix(in srgb,var(--e-a) 70%,var(--e-bg))` | `html.is-dark` | – |
| `--e-focus` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-ink` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-kick` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-line` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-line-strong` | `color-mix(in srgb,var(--e-ink) 34%,var(--e-bg))` | `html.is-dark` | 1 |
| `--e-link` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-muted` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-sec-bg` | `var(--e-a-soft)` | `html.is-dark .bg-accent` | – |
| `--e-soft-mix` | `16%` | `html.is-dark` | – |
| `--e-surface` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-surface-2` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--e-text` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |

**css/rich.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--rt-accent` | `var(--e-link)` | `:root,.bg-white,.bg-muted,.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--rt-danger` | `#B42318` | `:root` | 4 |
| `--rt-muted` | `var(--e-muted)` | `:root,.bg-white,.bg-muted,.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--rt-success` | `#1A7240` | `:root` | 4 |
| `--rt-warning` | `#8A4B00` | `:root` | 4 |

**css/search.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--se-accent` | `var(--e-a)` | `.srch,.sf,.ssug` | – |
| `--se-bg` | `var(--e-bg)` | `.srch,.sf,.ssug` | – |
| `--se-line` | `var(--e-line)` | `.srch,.sf,.ssug` | – |
| `--se-mark` | `var(--e-a-soft)` | `.srch,.sf,.ssug` | – |
| `--se-muted` | `var(--e-muted)` | `.srch,.sf,.ssug` | – |
| `--se-radius` | `var(--e-radius)` | `.srch,.sf,.ssug` | – |
| `--se-surface` | `var(--e-surface)` | `.srch,.sf,.ssug` | – |

**css/site.css** (66)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-overlay-dark` | `linear-gradient(180deg,color-mix(in srgb,var(--e-night) 50%,transparent),color-mix(in srgb,var(--e-night) 78%,transparent))` | `.sec--has-bg` | – |
| `--cms-overlay-light` | `color-mix(in srgb,var(--e-bg) 88%,transparent)` | `.sec--has-bg` | – |
| `--cols` | `2` | `.cols-2` | 3 |
| `--e-a` | `#B42318` | `:root` | 1 |
| `--e-a-on` | `#fff` | `:root` | 1 |
| `--e-a-soft` | `color-mix(in srgb,var(--e-a) var(--e-soft-mix),var(--e-bg))` | `:root` | 2 |
| `--e-a-strong` | `#8E1B12` | `:root` | 1 |
| `--e-bg` | `#FBFAF7` | `:root` | 1 |
| `--e-box` | `var(--e-surface)` | `:root` | 2 |
| `--e-btn-bg` | `var(--e-a)` | `:root` | 2 |
| `--e-btn-bg-hover` | `var(--e-a-strong)` | `:root` | 2 |
| `--e-btn-ink` | `var(--e-a-on)` | `:root` | 4 |
| `--e-d` | `clamp(calc(var(--e-r2) * 1rem + .55rem),calc(var(--e-r3) * 1rem + 3.2vw),min(calc(var(--e-r7) * 1rem),7.5rem))` | `:root` | – |
| `--e-duo` | `var(--e-a)` | `:root` | 1 |
| `--e-fg` | `var(--e-a-on)` | `.bg-accent` | 2 |
| `--e-focus` | `var(--e-a)` | `:root` | 2 |
| `--e-font` | `"Source Sans 3",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif` | `:root` | – |
| `--e-font-display` | `Newsreader,ui-serif,Georgia,Cambria,serif` | `:root` | – |
| `--e-font-mono` | `"IBM Plex Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace` | `:root` | – |
| `--e-fs` | `18` | `:root` | – |
| `--e-fx` | `0%` | `.fx0` | 9 |
| `--e-fy` | `0%` | `.fy0` | 9 |
| `--e-gap` | `clamp(20px,2.6vw,40px)` | `:root` | – |
| `--e-gutter` | `clamp(16px,4.5vw,48px)` | `:root` | – |
| `--e-h1` | `clamp(calc(var(--e-r2) * 1rem + .4rem),calc(var(--e-r2) * 1rem + 2.6vw),calc(var(--e-r5) * 1rem))` | `:root` | – |
| `--e-h2` | `clamp(calc(var(--e-ratio) * 1rem + .45rem),calc(var(--e-r2) * 1rem + 1vw),calc(var(--e-r4) * 1rem))` | `:root` | – |
| `--e-h3` | `clamp(calc(var(--e-ratio) * 1rem),calc(var(--e-ratio) * 1rem + .35vw),calc(var(--e-r2) * 1rem))` | `:root` | – |
| `--e-header-h` | `64px` | `:root` | 1 |
| `--e-hw` | `600` | `:root` | – |
| `--e-ink` | `#111` | `:root` | 3 |
| `--e-kick` | `var(--e-a)` | `:root` | 4 |
| `--e-line` | `#DDD8CD` | `:root` | 3 |
| `--e-line-strong` | `color-mix(in srgb,var(--e-ink) 30%,var(--e-bg))` | `:root` | 3 |
| `--e-link` | `var(--e-a)` | `:root` | 2 |
| `--e-measure` | `66ch` | `:root` | – |
| `--e-muted` | `#5C5850` | `:root` | 5 |
| `--e-night` | `#15130F` | `:root` | 1 |
| `--e-on-dark` | `#fff` | `:root` | – |
| `--e-r2` | `calc(var(--e-ratio) * var(--e-ratio))` | `:root` | – |
| `--e-r3` | `calc(var(--e-r2) * var(--e-ratio))` | `:root` | – |
| `--e-r4` | `calc(var(--e-r2) * var(--e-r2))` | `:root` | – |
| `--e-r5` | `calc(var(--e-r4) * var(--e-ratio))` | `:root` | – |
| `--e-r7` | `calc(var(--e-r5) * var(--e-r2))` | `:root` | – |
| `--e-radius` | `0px` | `:root` | – |
| `--e-ratio` | `1.25` | `:root` | – |
| `--e-rule-s` | `double` | `:root` | 2 |
| `--e-rule-w` | `5px` | `:root` | 1 |
| `--e-sec-bg` | `var(--e-a-soft)` | `html.has-dark .bg-accent` | 4 |
| `--e-sec-y` | `clamp(56px,7.5vw,112px)` | `:root` | – |
| `--e-shade` | `#000` | `:root` | – |
| `--e-soft-mix` | `10%` | `:root` | 1 |
| `--e-surface` | `#F2EFE8` | `:root` | 3 |
| `--e-surface-2` | `color-mix(in srgb,var(--e-ink) 7%,var(--e-surface))` | `:root` | 2 |
| `--e-text` | `#262522` | `:root` | 3 |
| `--e-track` | `1` | `:root` | – |
| `--e-wrap` | `1280px` | `:root` | – |
| `--ha-accent` | `var(--e-a)` | `.ha,.ha-below` | – |
| `--ha-anchor` | `--e-hs` | `.ha,.ha-below` | – |
| `--ha-bg` | `var(--e-bg)` | `.ha,.ha-below` | – |
| `--ha-h` | `40px` | `.ha,.ha-below` | – |
| `--ha-ink` | `var(--e-ink)` | `.ha,.ha-below` | – |
| `--ha-line` | `var(--e-line-strong)` | `.ha,.ha-below` | – |
| `--ha-muted` | `var(--e-muted)` | `.ha,.ha-below` | – |
| `--ha-on` | `var(--e-a-on)` | `.ha,.ha-below` | – |
| `--ha-open` | `var(--e-link)` | `.ha,.ha-below` | – |
| `--ha-surface` | `var(--e-surface)` | `.ha,.ha-below` | – |

### Bewegung & Barrierefreiheit je Datei

| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |
|---|---|---|---|---|---|
| `css/blocks.css` | – | ja | – | – | ja |
| `css/data.css` | e-reveal | ja | ja | – | ja |
| `css/dataform.css` | – | ja | – | – | – |
| `css/head-ressorts.css` | – | ja | – | – | – |
| `css/site.css` | e-out, e-in, e-drop | ja | ja | – | ja |
| `css/story.css` | e-reveal, e-read | ja | ja | – | – |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/blocks.js` | – | ja | – | – | 1,6 KB (Quelle) |
| `js/site.js` | – | – | – | – | 5,1 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

_Keine Namensraum-Ereignisse._

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-active` | functions.php |
| `data-copy` | assets/js/blocks.js, functions.php |
| `data-design-preview` | templates/layout.php |
| `data-done` | assets/js/blocks.js, functions.php |
| `data-header` | templates/partials/header.php, assets/js/site.js |
| `data-label-close` | templates/partials/menu-btn.php, assets/js/site.js |
| `data-label-open` | templates/partials/menu-btn.php, assets/js/site.js |
| `data-scope` | templates/layout.php |
| `data-sw` | templates/layout.php |
| `data-today` | assets/js/site.js, functions.php |

<!-- docs:assets:end -->
