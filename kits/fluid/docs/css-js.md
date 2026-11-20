# CSS & JS – Kit „Fluid (breakpointlos)“ (fluid)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=fluid --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden (Layout, Wartung, offline): `css/editing.css`, `css/overlay.css`, `css/pages.css`, `css/preview.css`, `css/site.css`, `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, 27 Einträge).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/fluid/`).

## 2. Tokens & Farben

- Kit-Variablen: `--f-*` an `:root`.
- Gesetzte Kern-Variablen: `--cal-accent`, `--cal-accent-ink`, `--cal-ev-bg`, `--cal-line`, `--cal-muted`, `--cal-radius`, `--cal-surface`, `--cal-today-bg`, `--cms-map-accent`, `--cms-map-bg`, `--cms-map-line`, `--cms-map-radius`, `--dff-gap`, `--dl-gap`, `--ha-accent`, `--ha-bg`, `--ha-gap`, `--ha-h`, `--ha-ink`, `--ha-line`, `--ha-muted`, `--ha-on`, `--ha-open`, `--ha-surface` ….

## 3. Blöcke → Dateien

- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.

## 4. Animationen & Regeln

- Dateien mit Keyframes/Scroll-Animation: `css/_header.css`, `css/b-faq.css`, `css/b-logos.css`, `css/hero-x.css`, `css/media.css`, `css/overlay.css`.
- `prefers-reduced-motion` in `css/site.css`: nein · `forced-colors`: nein · `:focus-visible`: nein.
- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.

## 5. Overlays, Sheets, Dialoge

- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).

## 6. JavaScript & Ereignisse

- Skripte: `js/reel.js`, `js/scrolly.js`, `js/site.js`, `js/tabs.js`.
- Ereignisse: keine eigenen.

## 7. Sonderfälle

- **Merkmale** (`Theme::markUsed`): `css/filter.css` + `js/filter.js` laden bei `'@filter'` (Karten „Filter nach Kategorie“, `fluid_filter()`); `css/b-audio.css` bei `video:audio`, `css/b-pricelist.css` bei `pricing:list`, `css/hx-compare.css` auch beim Block `compare`. Die Vergleichstabelle meldet `'@table'` (ohne eigene Datei – für Kind-Kits).
- **Darstellungs-Knopf für Besucher** (Design `visitor_prefs`, Standard aus): `js/preferences-early.js` + `js/prefs-theme.js` synchron im `<head>` vor den Stylesheets, `css/preferences.css` + `css/prefs.css` nach den Block-Stylesheets, `js/preferences.js` (defer) – nur wenn eingeschaltet, nie im Editor. `preferences*.js`/`preferences.css` stammen unverändert aus NativeCSS 1.1.0 (MIT); `prefs-theme.js` setzt `scheme-dark`/`scheme-light` (Wahl „Farbschema“, nur mit `data-scheme` am Skript) und nimmt `has-motion` bei „Bewegung: Reduziert“ zurück. Platz: `.hdr__tools`, Kopfbanner, Fuß der Seitenleiste (`.side__prefs`), bei „Seitenleiste“ ohne Banner schmal im Seitenblatt (`.mnav__prefs`); `fluid_nav_fit()` rechnet 9 rem dazu.
- **Unbekannte Varianten**: `media_text` → wie `right`, `video` `morph` → wie `text`.

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=fluid --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Fluid (breakpointlos)“ (fluid 1.0.0)

### Von Vorlagen eingebunden (theme_asset() in templates/)

Feste Namen; zusammengesetzte (z. B. `css/nav-{stil}.css`) erkennt der Generator nicht.

- `css/editing.css` – templates/layout.php
- `css/overlay.css` – templates/layout.php
- `css/pages.css` – templates/error.php, templates/maintenance.php, templates/offline.php
- `css/preferences.css` – templates/layout.php
- `css/prefs.css` – templates/layout.php
- `css/preview.css` – templates/layout.php
- `css/site.css` – templates/layout.php, templates/maintenance.php, templates/offline.php
- `js/backnav.js` – templates/layout.php
- `js/preferences-early.js` – templates/layout.php
- `js/preferences.js` – templates/layout.php
- `js/prefs-theme.js` – templates/layout.php
- `js/scheme.js` – templates/layout.php
- `js/site.js` – templates/layout.php
- `js/vt.js` – templates/layout.php

### Bedingt geladen – theme.php → conditional_css

| Datei | lädt bei |
|---|---|
| `css/hero-x.css` | hero:fullbleed, hero:cards, hero:type |
| `css/hx-scale.css` | hero:scale |
| `css/hx-collage.css` | hero:collage |
| `css/hx-compare.css` | hero:compare, compare |
| `css/prose.css` | richtext, tabs, faq, media_text, @rich |
| `css/b-article.css` | richtext:article, richtext:columns |
| `css/b-features.css` | features |
| `css/b-media-text.css` | media_text |
| `css/b-cta.css` | cta |
| `css/reel.css` | cards:reel, quote:reel |
| `css/b-bento.css` | bento |
| `css/b-cards.css` | cards |
| `css/filter.css` | @filter |
| `js/filter.js` | @filter |
| `css/b-logos.css` | logos |
| `css/b-stats.css` | stats |
| `css/b-quote.css` | quote |
| `css/b-steps.css` | steps |
| `css/b-pricing.css` | pricing |
| `css/b-pricelist.css` | pricing:list |
| `css/b-faq.css` | faq |
| `css/b-tabs.css` | tabs |
| `css/b-scrolly.css` | scrolly |
| `css/b-contact.css` | contact, map |
| `css/b-video.css` | video |
| `css/b-audio.css` | video:audio |
| `css/b-downloads.css` | downloads |
| `js/downloads.js` | downloads |
| `css/dataform.css` | contact |
| `js/tabs.js` | tabs |
| `js/reel.js` | quote, cards |
| `js/scrolly.js` | scrolly |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `bento` Bento-Raster | `blocks/bento.php` | – | `css/b-bento.css` |
| `cards` Karten | `blocks/cards.php` | image, overlay, horizontal, reel | `css/reel.css` (reel), `css/b-cards.css`, `js/reel.js` |
| `compare` Vorher/Nachher | `blocks/compare.php` | – | `css/hx-compare.css` |
| `contact` Kontakt (Karte, Formular, Zeiten) | `blocks/contact.php` | – | `css/b-contact.css`, `css/dataform.css` |
| `cta` Handlungsaufruf | `blocks/cta.php` | band, box, split, big, photo | `css/b-cta.css` |
| `downloads` Downloads | `blocks/downloads.php` | cards, list | `css/b-downloads.css`, `js/downloads.js` |
| `faq` Fragen & Antworten | `blocks/faq.php` | split, stacked | `css/prose.css`, `css/b-faq.css` |
| `features` Merkmale / Leistungen | `blocks/features.php` | cards, icons, list, numbered | `css/b-features.css` |
| `hero` Einstieg (Hero) | `blocks/hero.php` | split, centered, fullbleed, cards, type, compact, scale, collage, compare | `css/hero-x.css` (fullbleed, cards, type), `css/hx-scale.css` (scale), `css/hx-collage.css` (collage), `css/hx-compare.css` (compare) |
| `logos` Logos (Partner, Kunden) | `blocks/logos.php` | grid, marquee | `css/b-logos.css` |
| `map` Karte | `blocks/map.php` | – | `css/b-contact.css` |
| `media_text` Text + Bild | `blocks/media_text.php` | auto, right, left, split, overlap | `css/prose.css`, `css/b-media-text.css` |
| `pricing` Pakete / Vergleich | `blocks/pricing.php` | cards, table, list | `css/b-pricing.css`, `css/b-pricelist.css` (list) |
| `quote` Zitat / Stimmen | `blocks/quote.php` | single, grid, reel, marquee | `css/reel.css` (reel), `css/b-quote.css`, `js/reel.js` |
| `richtext` Fließtext / Artikel | `blocks/richtext.php` | standard, article, columns | `css/prose.css`, `css/b-article.css` (article, columns) |
| `scrolly` Scrollytelling | `blocks/scrolly.php` | left, right | `css/b-scrolly.css`, `js/scrolly.js` |
| `stats` Kennzahlen | `blocks/stats.php` | row, cards, split | `css/b-stats.css` |
| `steps` Ablauf / Zeitleiste | `blocks/steps.php` | numbers, timeline, process | `css/b-steps.css` |
| `tabs` Reiter (Tabs) | `blocks/tabs.php` | top, side, stage | `css/prose.css`, `css/b-tabs.css`, `js/tabs.js` |
| `video` Video & Audio | `blocks/video.php` | wide, text, cinema, audio | `css/b-video.css`, `css/b-audio.css` (audio) |

### Variablen je Stylesheet

**css/_base.css** (25)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-a-soft` | `color-mix(in srgb,var(--f-fg) 12%,transparent)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-btn-bg` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | 1 |
| `--f-btn-hover` | `color-mix(in srgb,var(--f-fg) 86%,var(--f-sec-bg))` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | 1 |
| `--f-btn-ink` | `var(--f-a)` | `.bg-accent` | 3 |
| `--f-card` | `var(--f-bg)` | `.bg-muted` | 1 |
| `--f-card-bd` | `var(--f-line)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-card-sh` | `none` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-fg` | `var(--f-a-on)` | `.bg-accent` | 3 |
| `--f-focus` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-ico` | `var(--f-a)` | `.bg-tint` | 1 |
| `--f-ico-bg` | `var(--f-bg)` | `.bg-tint` | 1 |
| `--f-ink` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | 1 |
| `--f-line` | `color-mix(in srgb,var(--f-fg) 20%,transparent)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-line-strong` | `color-mix(in srgb,var(--f-fg) 48%,transparent)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-link` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | 1 |
| `--f-muted` | `var(--f-a-on)` | `.bg-accent` | 4 |
| `--f-sec-bg` | `var(--f-surface)` | `.bg-muted` | 3 |
| `--f-surface` | `color-mix(in srgb,var(--f-bg) 60%,transparent)` | `.bg-tint` | 1 |
| `--f-surface-2` | `color-mix(in srgb,var(--f-fg) 14%,transparent)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-text` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | 1 |
| `--fx` | `0%` | `.fx0` | 9 |
| `--fy` | `0%` | `.fy0` | 9 |
| `--ico-2-opacity` | `.24` | `.ico` | – |
| `--min` | `12.5rem` | `.min-s` | 2 |
| `--mt-r` | `var(--f-img-r,var(--f-r-l))` | `.mt` | – |

**css/_footer.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-card` | `var(--f-bg)` | `.ftr` | – |
| `--gap` | `.25rem 1.5rem` | `.ftr__legal` | 1 |

**css/_header.css** (10)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-accent` | `var(--f-a)` | `.ha,.ha-below` | – |
| `--ha-bg` | `var(--f-bg)` | `.ha,.ha-below` | – |
| `--ha-gap` | `.5rem` | `.ha,.ha-below` | – |
| `--ha-h` | `2.75rem` | `.ha,.ha-below` | – |
| `--ha-ink` | `var(--f-ink)` | `.ha,.ha-below` | – |
| `--ha-line` | `var(--f-line-strong)` | `.ha,.ha-below` | – |
| `--ha-muted` | `var(--f-muted)` | `.ha,.ha-below` | – |
| `--ha-on` | `var(--f-a-on)` | `.ha,.ha-below` | – |
| `--ha-open` | `var(--f-link)` | `.ha,.ha-below` | – |
| `--ha-surface` | `var(--f-surface)` | `.ha,.ha-below` | – |

**css/_options.css** (35)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-a-soft` | `color-mix(in srgb,var(--f-fg) 18%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-bg` | `var(--f-zone)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-btn-bg` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-btn-hover` | `color-mix(in srgb,var(--f-fg) 86%,var(--f-zone))` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-btn-ink` | `var(--f-b)` | `.bg-secondary` | 5 |
| `--f-card` | `color-mix(in srgb,var(--f-fg) 7%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-fg` | `var(--f-b-on)` | `.bg-secondary` | 6 |
| `--f-focus` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-hdr-bg` | `var(--f-surface)` | `.hbg-surface .hdr` | 2 |
| `--f-ico` | `var(--f-b)` | `html:is(.sec2-details,.sec2-all)` | – |
| `--f-ico-bg` | `color-mix(in srgb,var(--f-b) 16%,var(--f-bg))` | `html:is(.sec2-details,.sec2-all)` | – |
| `--f-img-r` | `0` | `.img-sharp` | 1 |
| `--f-ink` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-line` | `color-mix(in srgb,var(--f-fg) 20%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-line-strong` | `color-mix(in srgb,var(--f-fg) 48%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-link` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-mark` | `color-mix(in srgb,var(--f-a2) 62%,var(--f-a))` | `html.em-marker .bg-tint` | – |
| `--f-muted` | `var(--f-b-on)` | `.bg-secondary` | 5 |
| `--f-nav-mark` | `var(--f-a2)` | `.nm-highlight .hdr` | 1 |
| `--f-sec-bg` | `var(--f-b)` | `.bg-secondary` | 4 |
| `--f-surface` | `color-mix(in srgb,var(--f-fg) 12%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-surface-2` | `color-mix(in srgb,var(--f-fg) 16%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-text` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-zone` | `var(--f-a)` | `.hbg-accent .hdr,.fbg-accent .ftr,.meta-accent .hdr__meta` | 2 |
| `--fl-bw` | `5px` | `html` | 5 |
| `--fl-c` | `var(--f-line)` | `html` | 4 |
| `--fl-w` | `1px` | `html` | 4 |
| `--ha-accent` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr :is(.ha,.ha-below)` | – |
| `--ha-on` | `var(--f-zone)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr :is(.ha,.ha-below)` | – |
| `--hdr-h` | `6rem` | `html.hh-tall` | 2 |
| `--hdr-logo` | `4rem` | `html.hh-tall` | 2 |
| `--hdr-mark` | `3.2rem` | `html.hh-tall` | 2 |
| `--hdr-name` | `1.45rem` | `html.hh-tall` | 2 |
| `--ico-2-opacity` | `0` | `.ic-outline .feat__ico` | – |
| `--lo-h` | `5.5rem` | `html.logo-over` | 4 |

**css/_tokens.css** (113)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-a` | `var(--f-a)` | `:root` | – |
| `--b-a-on` | `var(--f-a-on)` | `:root` | – |
| `--b-bg` | `var(--f-bg)` | `:root` | – |
| `--b-ink` | `var(--f-ink)` | `:root` | – |
| `--b-line` | `var(--f-line)` | `:root` | – |
| `--b-muted` | `var(--f-muted)` | `:root` | – |
| `--b-radius` | `var(--f-radius)` | `:root` | – |
| `--b-surface` | `var(--f-surface)` | `:root` | – |
| `--f-a` | `#4338CA` | `:root` | 2 |
| `--f-a-on` | `#FFFFFF` | `:root` | 2 |
| `--f-a-soft` | `color-mix(in srgb,var(--f-a) 14%,var(--f-bg))` | `:root` | – |
| `--f-a-strong` | `#3730A3` | `:root` | 2 |
| `--f-a2` | `#FDE68A` | `:root` | 2 |
| `--f-b` | `#0F766E` | `:root` | 2 |
| `--f-b-on` | `#FFFFFF` | `:root` | 2 |
| `--f-bg` | `#FFFFFF` | `:root` | 2 |
| `--f-btn-bg` | `var(--f-a)` | `:root` | – |
| `--f-btn-hover` | `var(--f-a-strong)` | `:root` | – |
| `--f-btn-ink` | `var(--f-a-on)` | `:root` | – |
| `--f-btn-r` | `var(--f-radius)` | `:root` | 2 |
| `--f-c3` | `#B45309` | `:root` | 2 |
| `--f-c4` | `#BE185D` | `:root` | 2 |
| `--f-c5` | `#0369A1` | `:root` | 2 |
| `--f-card` | `var(--f-bg)` | `:root` | 1 |
| `--f-card-bd` | `var(--f-line)` | `:root` | 2 |
| `--f-card-blur` | `none` | `:root` | – |
| `--f-card-sh` | `none` | `:root` | 1 |
| `--f-dark` | `#0E1022` | `:root` | 2 |
| `--f-density` | `1` | `:root` | – |
| `--f-focus` | `var(--f-a)` | `:root` | – |
| `--f-font` | `Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif` | `:root` | – |
| `--f-font-head` | `var(--f-font)` | `:root` | – |
| `--f-font-label` | `var(--f-font)` | `:root` | – |
| `--f-font-mono` | `ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace` | `:root` | – |
| `--f-fs-max` | `18.5` | `:root` | – |
| `--f-fs-min` | `16` | `:root` | – |
| `--f-gutter` | `clamp(1rem,.45rem + 2.6cqi,3rem)` | `:root` | – |
| `--f-hdr-bg` | `color-mix(in srgb,var(--f-bg) 86%,transparent)` | `:root` | – |
| `--f-hw` | `650` | `:root` | – |
| `--f-ico` | `var(--f-a)` | `:root` | – |
| `--f-ico-bg` | `var(--f-a-soft)` | `:root` | – |
| `--f-ink` | `#0B0D17` | `:root` | 2 |
| `--f-line` | `#E2E4EB` | `:root` | 2 |
| `--f-line-strong` | `color-mix(in srgb,var(--f-ink) 26%,var(--f-bg))` | `:root` | – |
| `--f-link` | `var(--f-a)` | `:root` | – |
| `--f-muted` | `#565C6B` | `:root` | 2 |
| `--f-on-dark` | `#FFFFFF` | `:root` | – |
| `--f-p` | `calc((100vw - var(--f-vw-min)*1px)/(var(--f-vw-max) - var(--f-vw-min)))` | `:root` | – |
| `--f-pad` | `calc(var(--f-s-l)*var(--f-density))` | `:root` | – |
| `--f-r-l` | `calc(var(--f-radius)*1.4)` | `:root` | – |
| `--f-r-s` | `calc(var(--f-radius)*.6)` | `:root` | – |
| `--f-radius` | `10px` | `:root` | – |
| `--f-ratio` | `1.25` | `:root` | – |
| `--f-rmin` | `calc(1 + (var(--f-ratio) - 1)*.7)` | `:root` | – |
| `--f-s-2xl` | `calc(var(--f-t0)*3)` | `:root` | – |
| `--f-s-2xs` | `calc(var(--f-t0)*.25)` | `:root` | – |
| `--f-s-3xl` | `calc(var(--f-t0)*4.5)` | `:root` | – |
| `--f-s-l` | `calc(var(--f-t0)*1.5)` | `:root` | – |
| `--f-s-m` | `var(--f-t0)` | `:root` | – |
| `--f-s-s` | `calc(var(--f-t0)*.75)` | `:root` | – |
| `--f-s-xl` | `calc(var(--f-t0)*2)` | `:root` | – |
| `--f-s-xs` | `calc(var(--f-t0)*.5)` | `:root` | – |
| `--f-sec` | `calc(clamp(3rem,1.9rem + 5.4vw,7.5rem)*var(--f-space))` | `:root` | – |
| `--f-sh1` | `rgb(8 10 20/.07)` | `:root` | 3 |
| `--f-sh2` | `rgb(8 10 20/.22)` | `:root` | 3 |
| `--f-shadow` | `0 1px 2px var(--f-sh1),0 12px 32px -12px var(--f-sh2)` | `:root` | – |
| `--f-space` | `1` | `:root` | – |
| `--f-surface` | `#F4F5F8` | `:root` | 2 |
| `--f-surface-2` | `color-mix(in srgb,var(--f-ink) 5%,var(--f-surface))` | `:root` | – |
| `--f-t-1` | `calc(var(--f-t0)*.875)` | `:root` | – |
| `--f-t-2` | `calc(var(--f-t0)*.78)` | `:root` | – |
| `--f-t0` | `clamp(var(--f-t0a)*.0625rem,var(--f-t0a)*.0625rem + (var(--f-t0b) - var(--f-t0a))*var(--f-p),var(--f-t0b)*.0625rem)` | `:root` | – |
| `--f-t0a` | `var(--f-fs-min)` | `:root` | – |
| `--f-t0b` | `var(--f-fs-max)` | `:root` | – |
| `--f-t1` | `clamp(var(--f-t1a)*.0625rem,var(--f-t1a)*.0625rem + (var(--f-t1b) - var(--f-t1a))*var(--f-p),var(--f-t1b)*.0625rem)` | `:root` | – |
| `--f-t1a` | `calc(var(--f-fs-min)*var(--f-rmin))` | `:root` | – |
| `--f-t1b` | `calc(var(--f-fs-max)*var(--f-ratio))` | `:root` | – |
| `--f-t2` | `clamp(var(--f-t2a)*.0625rem,var(--f-t2a)*.0625rem + (var(--f-t2b) - var(--f-t2a))*var(--f-p),var(--f-t2b)*.0625rem)` | `:root` | – |
| `--f-t2a` | `calc(var(--f-fs-min)*pow(var(--f-rmin),2))` | `:root` | – |
| `--f-t2b` | `calc(var(--f-fs-max)*pow(var(--f-ratio),2))` | `:root` | – |
| `--f-t3` | `clamp(var(--f-t3a)*.0625rem,var(--f-t3a)*.0625rem + (var(--f-t3b) - var(--f-t3a))*var(--f-p),var(--f-t3b)*.0625rem)` | `:root` | – |
| `--f-t3a` | `calc(var(--f-fs-min)*pow(var(--f-rmin),3))` | `:root` | – |
| `--f-t3b` | `calc(var(--f-fs-max)*pow(var(--f-ratio),3))` | `:root` | – |
| `--f-t4` | `clamp(var(--f-t4a)*.0625rem,var(--f-t4a)*.0625rem + (var(--f-t4b) - var(--f-t4a))*var(--f-p),var(--f-t4b)*.0625rem)` | `:root` | – |
| `--f-t4a` | `calc(var(--f-fs-min)*pow(var(--f-rmin),4))` | `:root` | – |
| `--f-t4b` | `calc(var(--f-fs-max)*pow(var(--f-ratio),4))` | `:root` | – |
| `--f-t5` | `clamp(var(--f-t5a)*.0625rem,var(--f-t5a)*.0625rem + (var(--f-t5b) - var(--f-t5a))*var(--f-p),var(--f-t5b)*.0625rem)` | `:root` | – |
| `--f-t5a` | `calc(var(--f-fs-min)*pow(var(--f-rmin),5))` | `:root` | – |
| `--f-t5b` | `calc(var(--f-fs-max)*pow(var(--f-ratio),5))` | `:root` | – |
| `--f-t6` | `clamp(var(--f-t6a)*.0625rem,var(--f-t6a)*.0625rem + (var(--f-t6b) - var(--f-t6a))*var(--f-p),var(--f-t6b)*.0625rem)` | `:root` | – |
| `--f-t6a` | `calc(var(--f-fs-min)*pow(var(--f-rmin),6))` | `:root` | – |
| `--f-t6b` | `calc(var(--f-fs-max)*pow(var(--f-ratio),6))` | `:root` | – |
| `--f-text` | `#2B2F3A` | `:root` | 2 |
| `--f-track` | `-2` | `:root` | – |
| `--f-vw-max` | `1280` | `:root` | – |
| `--f-vw-min` | `360` | `:root` | – |
| `--f-wrap` | `76rem` | `:root` | – |
| `--f0-ink` | `var(--f-ink)` | `:root` | – |
| `--f0-link` | `var(--f-link)` | `:root` | – |
| `--f0-muted` | `var(--f-muted)` | `:root` | – |
| `--f0-text` | `var(--f-text)` | `:root` | – |
| `--kit-accent` | `var(--f-a)` | `:where(*)` | – |
| `--kit-bg` | `var(--f-bg)` | `:where(*)` | – |
| `--kit-font` | `var(--f-font)` | `:where(*)` | – |
| `--kit-font-head` | `var(--f-font-head)` | `:where(*)` | – |
| `--kit-ink` | `var(--f-ink)` | `:where(*)` | – |
| `--kit-line` | `var(--f-line)` | `:where(*)` | – |
| `--kit-link` | `var(--f-link)` | `:where(*)` | – |
| `--kit-muted` | `var(--f-muted)` | `:where(*)` | – |
| `--kit-on-accent` | `var(--f-a-on)` | `:where(*)` | – |
| `--kit-radius` | `var(--f-radius)` | `:where(*)` | – |
| `--kit-surface` | `var(--f-surface)` | `:where(*)` | – |
| `--kit-text` | `var(--f-text)` | `:where(*)` | – |

**css/b-bento.css** (6)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-ico` | `var(--f-ink)` | `.tile--highlight` | – |
| `--f-ico-bg` | `color-mix(in srgb,var(--f-ink) 10%,transparent)` | `.tile--highlight` | – |
| `--f-ink` | `var(--f0-ink)` | `.tile--highlight` | – |
| `--f-link` | `var(--f-ink)` | `.tile--highlight` | – |
| `--f-muted` | `var(--f-text)` | `.tile--highlight` | – |
| `--f-text` | `var(--f0-text)` | `.tile--highlight` | – |

**css/b-cards.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-fg` | `var(--f-on-dark)` | `.tcard--overlay .tcard__body` | – |
| `--f-ink` | `var(--f-fg)` | `.tcard--overlay .tcard__body` | – |
| `--f-link` | `var(--f-fg)` | `.tcard--overlay .tcard__body` | – |
| `--f-muted` | `color-mix(in srgb,var(--f-fg) 88%,transparent)` | `.tcard--overlay .tcard__body` | – |
| `--f-text` | `var(--f-fg)` | `.tcard--overlay .tcard__body` | – |
| `--gap` | `var(--f-s-l)` | `.cards` | – |
| `--min` | `19rem` | `.cards--reel` | – |

**css/b-contact.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-map-accent` | `var(--f-a)` | `.cms-map` | – |
| `--cms-map-bg` | `var(--f-surface-2)` | `.cms-map` | – |
| `--cms-map-line` | `var(--f-line)` | `.cms-map` | – |
| `--cms-map-radius` | `var(--f-r-l)` | `.cms-map` | – |

**css/b-cta.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-btn-bg` | `var(--f-a)` | `.cta-photo__card` | – |
| `--f-btn-hover` | `var(--f-a-strong)` | `.cta-photo__card` | – |
| `--f-btn-ink` | `var(--f-a-on)` | `.cta-photo__card` | – |
| `--f-fg` | `var(--f0-ink)` | `.cta-photo__card` | – |
| `--f-ink` | `var(--f0-ink)` | `.cta-photo__card` | – |
| `--f-link` | `var(--f0-link)` | `.cta-photo__card` | – |
| `--f-muted` | `var(--f0-muted)` | `.cta-photo__card` | – |
| `--f-text` | `var(--f0-text)` | `.cta-photo__card` | – |

**css/b-features.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `var(--f-s-2xl) var(--f-s-xl)` | `.feats--icons` | 1 |
| `--ico-2-opacity` | `.35` | `.feats--icons .feat__ico` | – |

**css/b-media-text.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-card` | `var(--f-surface)` | `.mt-over__card` | – |

**css/b-pricing.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--min` | `16rem` | `.plans` | – |

**css/b-quote.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ico-2-opacity` | `.35` | `.pull__mark` | – |
| `--min` | `22rem` | `.quotes--reel` | – |

**css/b-stats.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `var(--f-s-l) var(--f-s-xl)` | `.stats` | – |
| `--min` | `11rem` | `.stats` | – |

**css/b-steps.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-tone` | `var(--f-a)` | `.steps.steps--c-alternate>li.step` | 5 |
| `--f-tone-soft` | `color-mix(in srgb,var(--f-tone) 14%,var(--f-bg))` | `.steps:is(.steps--c-alternate,.steps--c-accent,.steps--c-neutral)>li.step` | – |
| `--i` | `1` | `.steps--a-sequence>li:nth-child(2)` | 10 |
| `--min` | `14rem` | `.steps--numbers` | – |
| `--step-ring` | `0 0 0 0 transparent` | `.steps.steps--m-filled>li.step .step__num` | 2 |

**css/b-video.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-fg` | `var(--f-on-dark)` | `.sec.v-cinema` | – |
| `--f-focus` | `var(--f-fg)` | `.sec.v-cinema` | – |
| `--f-ink` | `var(--f-fg)` | `.sec.v-cinema` | – |
| `--f-line` | `color-mix(in srgb,var(--f-fg) 20%,transparent)` | `.sec.v-cinema` | – |
| `--f-link` | `var(--f-fg)` | `.sec.v-cinema` | – |
| `--f-muted` | `color-mix(in srgb,var(--f-fg) 80%,var(--f-dark))` | `.sec.v-cinema` | – |
| `--f-sec-bg` | `var(--f-dark)` | `.sec.v-cinema` | – |
| `--f-text` | `var(--f-fg)` | `.sec.v-cinema` | – |

**css/calendar.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cal-accent` | `var(--f-btn-bg)` | `.cal` | – |
| `--cal-accent-ink` | `var(--f-btn-ink)` | `.cal` | – |
| `--cal-ev-bg` | `var(--f-a-soft)` | `.cal` | – |
| `--cal-line` | `var(--f-line)` | `.cal` | – |
| `--cal-muted` | `var(--f-muted)` | `.cal` | – |
| `--cal-radius` | `var(--f-r-l)` | `.cal` | – |
| `--cal-surface` | `var(--f-surface)` | `.cal` | 1 |
| `--cal-today-bg` | `var(--f-a-soft)` | `.cal` | – |

**css/data.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cols` | `3` | `.dl--cards .dl-items` | 4 |
| `--dl-gap` | `var(--f-s-l)` | `.dl,.df` | – |
| `--min` | `22rem` | `.dl--cards .dl-cols-2` | 4 |
| `--tile` | `#FFFFFF` | `.dl-img--contain,.df-img--contain` | 3 |
| `--tile-tint` | `9%` | `.pal-colorful :is(.dl-img--contain,.df-img--contain)` | – |

**css/dataform.css** (6)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dff-gap` | `var(--f-s-m)` | `.dff-wrap` | – |
| `--dff-ink` | `var(--f-ink)` | `.dff-wrap` | – |
| `--dff-muted` | `var(--f-muted)` | `.dff-wrap` | – |
| `--dff-sec-line` | `var(--f-line-strong)` | `.dff-wrap` | – |
| `--dff-sec-radius` | `var(--f-radius)` | `.dff-wrap` | – |
| `--f-danger` | `color-mix(in srgb,#E5484D 66%,var(--f-ink))` | `.dff-wrap` | – |

**css/hero-x.css** (16)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-a-soft` | `color-mix(in srgb,var(--f-fg) 14%,transparent)` | `.hero-bleed` | – |
| `--f-btn-bg` | `var(--f-fg)` | `.hero-bleed` | – |
| `--f-btn-hover` | `color-mix(in srgb,var(--f-fg) 86%,var(--f-dark))` | `.hero-bleed` | – |
| `--f-btn-ink` | `var(--f-dark)` | `.hero-bleed` | – |
| `--f-fg` | `var(--f-on-dark)` | `.hero-bleed` | – |
| `--f-focus` | `var(--f-fg)` | `.hero-bleed` | – |
| `--f-ink` | `var(--f-fg)` | `.hero-bleed` | – |
| `--f-line-strong` | `color-mix(in srgb,var(--f-fg) 55%,transparent)` | `.hero-bleed` | – |
| `--f-link` | `var(--f-fg)` | `.hero-bleed` | – |
| `--f-muted` | `color-mix(in srgb,var(--f-on-dark) 88%,transparent)` | `.hero-bleed` | – |
| `--f-sec-bg` | `var(--f-dark)` | `.hero-bleed` | – |
| `--f-surface` | `color-mix(in srgb,var(--f-fg) 12%,transparent)` | `.hero-bleed` | – |
| `--f-text` | `var(--f-fg)` | `.hero-bleed` | – |
| `--hb-d` | `-1.5s` | `.hb-slide:nth-child(1)` | 5 |
| `--hb-kf` | `hb-f2` | `.has-motion .hb-n2` | 4 |
| `--hb-t` | `12s` | `.has-motion .hb-n2` | 4 |

**css/hx-compare.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--hx-ar` | `16/9` | `.hx-ar-16-9` | 5 |

**css/media.css** (11)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ar` | `5` | `.ar-5` | 19 |
| `--f-btn-bg` | `var(--f-on-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--f-btn-hover` | `color-mix(in srgb,var(--f-on-dark) 86%,var(--f-dark))` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--f-btn-ink` | `var(--f-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--f-ink` | `var(--f0-ink)` | `:is(.bg-accent,.bg-secondary,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--f-link` | `var(--f0-link)` | `:is(.bg-accent,.bg-secondary,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--f-muted` | `var(--f0-muted)` | `:is(.bg-accent,.bg-secondary,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--f-text` | `var(--f0-text)` | `:is(.bg-accent,.bg-secondary,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--i` | `1` | `.cms-stack__card:nth-child(2)` | 6 |
| `--n` | `2` | `.n-2` | 6 |
| `--row` | `clamp(8rem,3rem + 16vw,16rem)` | `.cms-gallery--justified` | 3 |

**css/opt-cards-brutal.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-card` | `var(--f-bg)` | `.cards-brutal` | – |
| `--f-card-bd` | `var(--f-ink)` | `.cards-brutal` | – |
| `--f-card-sh` | `5px 5px 0 var(--f-ink)` | `.cards-brutal` | – |

**css/opt-cards-glass.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-card` | `color-mix(in srgb,var(--f-bg) 62%,transparent)` | `.cards-glass` | – |
| `--f-card-bd` | `color-mix(in srgb,var(--f-ink) 14%,transparent)` | `.cards-glass` | – |
| `--f-card-blur` | `blur(16px) saturate(1.4)` | `.cards-glass` | – |
| `--f-card-sh` | `var(--f-shadow)` | `.cards-glass` | – |

**css/opt-cards-gradient.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-card-bd` | `transparent` | `.cards-gradient` | – |
| `--f-card-sh` | `0 10px 30px -18px var(--f-sh2,rgb(0 0 0/.25))` | `.cards-gradient` | – |

**css/opt-cards-tinted.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-card` | `var(--f-a-soft,color-mix(in srgb,var(--f-a) 10%,var(--f-bg)))` | `.cards-tinted` | 1 |
| `--f-card-bd` | `transparent` | `.cards-tinted` | – |
| `--f-card-sh` | `none` | `.cards-tinted` | – |

**css/opt-dividers-curve.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--div-h` | `clamp(1.25rem,.6rem + 3vw,3.25rem)` | `.div-curve :is(main,.page__main) :is(.sec.bg-white+.sec:not(.bg-white,.pt-none),.sec.bg-muted+.sec:not(.bg-muted,.pt-none),.sec.bg-tint+.sec:not(.bg-tint,.pt-none),.sec.bg-accent+.sec:not(.bg-accent,.pt-none),.sec.bg-secondary+.sec:not(.bg-secondary,.pt-none),.sec.bg-dark+.sec:not(.bg-dark,.pt-none),.sec+.sec.sec--has-bg:not(.pt-none))` | – |

**css/opt-dividers-slant.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--div-h` | `clamp(1.25rem,.6rem + 3vw,3.25rem)` | `.div-slant :is(main,.page__main) :is(.sec.bg-white+.sec:not(.bg-white,.pt-none),.sec.bg-muted+.sec:not(.bg-muted,.pt-none),.sec.bg-tint+.sec:not(.bg-tint,.pt-none),.sec.bg-accent+.sec:not(.bg-accent,.pt-none),.sec.bg-secondary+.sec:not(.bg-secondary,.pt-none),.sec.bg-dark+.sec:not(.bg-dark,.pt-none),.sec+.sec.sec--has-bg:not(.pt-none))` | – |

**css/opt-dividers-wave.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--div-h` | `clamp(1.25rem,.6rem + 3vw,3.25rem)` | `.div-wave :is(main,.page__main) :is(.sec.bg-white+.sec:not(.bg-white,.pt-none),.sec.bg-muted+.sec:not(.bg-muted,.pt-none),.sec.bg-tint+.sec:not(.bg-tint,.pt-none),.sec.bg-accent+.sec:not(.bg-accent,.pt-none),.sec.bg-secondary+.sec:not(.bg-secondary,.pt-none),.sec.bg-dark+.sec:not(.bg-dark,.pt-none),.sec+.sec.sec--has-bg:not(.pt-none))` | – |

**css/opt-dividers-zigzag.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--div-h` | `clamp(1.25rem,.6rem + 3vw,3.25rem)` | `.div-zigzag :is(main,.page__main) :is(.sec.bg-white+.sec:not(.bg-white,.pt-none),.sec.bg-muted+.sec:not(.bg-muted,.pt-none),.sec.bg-tint+.sec:not(.bg-tint,.pt-none),.sec.bg-accent+.sec:not(.bg-accent,.pt-none),.sec.bg-secondary+.sec:not(.bg-secondary,.pt-none),.sec.bg-dark+.sec:not(.bg-dark,.pt-none),.sec+.sec.sec--has-bg:not(.pt-none))` | – |

**css/opt-footer-statement.css** (14)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-a-soft` | `color-mix(in srgb,var(--f-fg) 12%,transparent)` | `.ftr--statement` | – |
| `--f-btn-bg` | `var(--f-fg)` | `.ftr--statement` | – |
| `--f-btn-hover` | `color-mix(in srgb,var(--f-fg) 86%,var(--f-dark))` | `.ftr--statement` | – |
| `--f-btn-ink` | `var(--f-dark)` | `.ftr--statement` | – |
| `--f-fg` | `var(--f-on-dark)` | `.ftr--statement` | – |
| `--f-focus` | `var(--f-fg)` | `.ftr--statement` | – |
| `--f-ink` | `var(--f-fg)` | `.ftr--statement` | – |
| `--f-line` | `color-mix(in srgb,var(--f-fg) 18%,transparent)` | `.ftr--statement` | – |
| `--f-line-strong` | `color-mix(in srgb,var(--f-fg) 45%,transparent)` | `.ftr--statement` | – |
| `--f-link` | `var(--f-fg)` | `.ftr--statement` | – |
| `--f-muted` | `color-mix(in srgb,var(--f-fg) 80%,var(--f-dark))` | `.ftr--statement` | – |
| `--f-surface` | `color-mix(in srgb,var(--f-fg) 8%,transparent)` | `.ftr--statement` | – |
| `--f-surface-2` | `color-mix(in srgb,var(--f-fg) 12%,transparent)` | `.ftr--statement` | – |
| `--f-text` | `color-mix(in srgb,var(--f-fg) 88%,var(--f-dark))` | `.ftr--statement` | – |

**css/opt-header-sidebar.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--banner-h` | `9.5rem` | `.side-banner` | 3 |
| `--f-hdr-bg` | `var(--f-bg)` | `.hbg-page .hdr--sidebar` | – |
| `--f0-bg` | `var(--f-bg)` | `:root` | – |
| `--side-logo` | `3.75rem` | `.hdr-sidebar` | 2 |
| `--side-w` | `18.5rem` | `.hdr-sidebar` | 2 |

**css/opt-palette-colorful.css** (12)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-a-soft` | `var(--f-tone-soft)` | `.pal-colorful :is(main>.sec,.feat,.card,.tile,.stat,.step,.dl-item,.dl-chip,.dl-fchip,.snav__item)` | – |
| `--f-band` | `linear-gradient(90deg,var(--f-p1) 0 20%,var(--f-p2) 20% 40%,var(--f-p3) 40% 60%,var(--f-p4) 60% 80%,var(--f-p5) 80%)` | `.pal-colorful .ftr,.pal-colorful .hdr--sidebar` | – |
| `--f-ico` | `var(--f-tone)` | `.pal-colorful .feat` | – |
| `--f-ico-bg` | `var(--f-tone-soft)` | `.pal-colorful .feat` | – |
| `--f-p1` | `var(--f-a)` | `html.pal-colorful` | – |
| `--f-p2` | `var(--f-b)` | `html.pal-colorful` | – |
| `--f-p3` | `var(--f-c3)` | `html.pal-colorful` | – |
| `--f-p4` | `var(--f-c4)` | `html.pal-colorful` | – |
| `--f-p5` | `var(--f-c5)` | `html.pal-colorful` | – |
| `--f-sec-bg` | `var(--f-tone-soft)` | `.pal-colorful main>.sec.bg-tint` | – |
| `--f-tone` | `var(--f-p1)` | `html.pal-colorful` | 8 |
| `--f-tone-soft` | `color-mix(in srgb,var(--f-tone) 14%,var(--f-bg))` | `.pal-colorful :is(main>.sec,.feat,.card,.tile,.stat,.step,.dl-item,.dl-chip,.dl-fchip,.snav__item)` | – |

**css/pages.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--stack` | `var(--f-s-m)` | `.err__text` | – |

**css/prefs.css** (37)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-ink` | `light-dark(#000,#fff)` | `:root[data-contrast="more"]` | – |
| `--f-line` | `light-dark(#5c6370,#a3a9b4)` | `:root[data-contrast="more"]` | – |
| `--f-muted` | `light-dark(#2b2f36,#dcdfe5)` | `:root[data-contrast="more"]` | – |
| `--f-text` | `light-dark(#000,#fff)` | `:root[data-contrast="more"]` | – |
| `--fp-a` | `var(--f-a)` | `:root` | – |
| `--fp-a-on` | `var(--f-a-on)` | `:root` | – |
| `--fp-bg` | `var(--f-bg)` | `:root` | – |
| `--fp-focus` | `var(--f-focus)` | `:root` | – |
| `--fp-line` | `var(--f-line)` | `:root` | – |
| `--fp-muted` | `var(--f-muted)` | `:root` | – |
| `--fp-surface` | `var(--f-surface)` | `:root` | – |
| `--fp-text` | `var(--f-text)` | `:root` | – |
| `--ncss-border-width` | `1px` | `:root` | – |
| `--ncss-color-bg` | `var(--fp-bg)` | `html .ncss-preferences-panel` | – |
| `--ncss-color-bg-subtle` | `var(--fp-surface)` | `html .ncss-preferences-panel` | – |
| `--ncss-color-border` | `var(--fp-line)` | `html .ncss-preferences-panel` | – |
| `--ncss-color-brand` | `var(--fp-a)` | `html .ncss-preferences-panel` | – |
| `--ncss-color-brand-contrast` | `var(--fp-a-on)` | `html .ncss-preferences-panel` | – |
| `--ncss-color-focus-ring` | `var(--fp-focus)` | `html .ncss-preferences-panel` | – |
| `--ncss-color-surface` | `var(--fp-bg)` | `html .ncss-preferences-panel` | – |
| `--ncss-color-text` | `var(--fp-text)` | `html .ncss-preferences-panel` | – |
| `--ncss-color-text-muted` | `var(--fp-muted)` | `html .ncss-preferences-panel` | – |
| `--ncss-focus-ring-width` | `2px` | `:root` | – |
| `--ncss-font-size-md` | `1.125rem` | `:root` | – |
| `--ncss-font-size-sm` | `.875rem` | `:root` | – |
| `--ncss-font-weight-medium` | `500` | `:root` | – |
| `--ncss-font-weight-semibold` | `600` | `:root` | – |
| `--ncss-motion-duration-fast` | `100ms` | `:root` | – |
| `--ncss-motion-easing` | `cubic-bezier(.4,0,.2,1)` | `:root` | – |
| `--ncss-radius-full` | `999px` | `:root` | – |
| `--ncss-radius-lg` | `calc(var(--f-radius) * 1.5)` | `:root` | – |
| `--ncss-radius-sm` | `calc(var(--f-radius) * .5)` | `:root` | – |
| `--ncss-shadow-lg` | `0 18px 48px -16px var(--f-sh2),0 2px 6px var(--f-sh1)` | `:root` | – |
| `--ncss-space-2xs` | `.5rem` | `:root` | – |
| `--ncss-space-3xs` | `.25rem` | `:root` | – |
| `--ncss-space-sm` | `1rem` | `:root` | – |
| `--ncss-space-xs` | `.75rem` | `:root` | – |

**css/preview.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-sh1` | `rgb(0 0 0/.3)` | `html.is-dark` | – |
| `--f-sh2` | `rgb(0 0 0/.55)` | `html.is-dark` | – |

**css/prose.css** (6)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--rt-accent` | `var(--f-link)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--rt-danger` | `#B42318` | `:root` | 8 |
| `--rt-muted` | `var(--f-muted)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--rt-success` | `#1A7240` | `:root` | 8 |
| `--rt-warning` | `#8A4B00` | `:root` | 8 |
| `--rx` | `var(--rt-success)` | `.t-tip,.t-warn` | 1 |

**css/search.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--se-accent` | `var(--f-a)` | `.srch,.sf,.ssug` | – |
| `--se-bg` | `var(--f-bg)` | `.srch,.sf,.ssug` | – |
| `--se-line` | `var(--f-line)` | `.srch,.sf,.ssug` | – |
| `--se-mark` | `color-mix(in srgb,var(--f-a2) 70%,transparent)` | `.srch,.sf,.ssug` | – |
| `--se-muted` | `var(--f-muted)` | `.srch,.sf,.ssug` | – |
| `--se-radius` | `var(--f-radius)` | `.srch,.sf,.ssug` | – |
| `--se-surface` | `var(--f-surface)` | `.srch,.sf,.ssug` | – |

**css/sections.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-sec-bg` | `var(--f-bg)` | `.sec--has-bg.bg-white,.sec--has-bg.bg-muted,.sec--has-bg.bg-tint` | – |

### Bewegung & Barrierefreiheit je Datei

| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |
|---|---|---|---|---|---|
| `css/_base.css` | – | ja | ja | – | ja |
| `css/_header.css` | f-hdr | ja | ja | – | – |
| `css/_options.css` | f-progress | ja | ja | ja | ja |
| `css/b-article.css` | – | ja | ja | – | – |
| `css/b-bento.css` | – | ja | – | – | – |
| `css/b-cards.css` | – | ja | – | ja | – |
| `css/b-cta.css` | cta-par, cta-in | ja | ja | – | – |
| `css/b-downloads.css` | – | ja | ja | – | ja |
| `css/b-faq.css` | f-drop | ja | ja | – | – |
| `css/b-logos.css` | f-marquee | ja | ja | – | – |
| `css/b-pricelist.css` | – | – | – | ja | – |
| `css/b-quote.css` | f-qmarquee | ja | ja | – | – |
| `css/b-scrolly.css` | – | ja | – | – | – |
| `css/b-steps.css` | steps-draw, steps-pop | ja | ja | – | – |
| `css/b-tabs.css` | – | ja | ja | – | ja |
| `css/calendar.css` | – | – | – | ja | ja |
| `css/data.css` | – | ja | – | – | ja |
| `css/dataform.css` | – | ja | – | – | ja |
| `css/filter.css` | – | – | – | ja | ja |
| `css/hero-x.css` | f-float, hb-zoom, hb-par, hb-f2, hb-f3, hb-f4, hb-f5, hb-f6 | ja | ja | – | – |
| `css/hx-compare.css` | – | – | – | ja | ja |
| `css/media.css` | cms-lb-in, cms-stack | ja | ja | – | ja |
| `css/opt-buttons-brutal.css` | – | ja | – | – | – |
| `css/opt-buttons-gradient.css` | – | – | – | ja | – |
| `css/opt-buttons-sharp.css` | – | ja | – | – | – |
| `css/opt-cards-gradient.css` | – | – | – | ja | – |
| `css/opt-header-sidebar.css` | – | ja | ja | – | – |
| `css/opt-palette-colorful.css` | – | – | – | ja | – |
| `css/opt-transitions-morph.css` | f-vt-out, f-vt-in | ja | ja | – | – |
| `css/overlay.css` | nv-slide, f-sheet, fl-nd-in | ja | ja | – | ja |
| `css/preferences.css` | – | ja | – | ja | ja |
| `css/search.css` | – | – | – | ja | ja |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/backnav.js` | – | – | – | – | 2,8 KB (Quelle) |
| `js/downloads.js` | ja | – | – | – | 3,1 KB (Quelle) |
| `js/filter.js` | – | – | – | – | 1,7 KB (Quelle) |
| `js/preferences-early.js` | – | – | – | – | 3,0 KB (Quelle) |
| `js/preferences.js` | ja | – | – | – | 16,1 KB (Quelle) |
| `js/prefs-theme.js` | – | – | – | – | 1,4 KB (Quelle) |
| `js/reel.js` | ja | – | ja | – | 1,3 KB (Quelle) |
| `js/scheme.js` | – | – | – | – | 1,6 KB (Quelle) |
| `js/scrolly.js` | – | ja | – | – | 1,0 KB (Quelle) |
| `js/site.js` | ja | ja | ja | – | 14,2 KB (Quelle) |
| `js/tabs.js` | – | – | – | – | 1,3 KB (Quelle) |
| `js/vt.js` | ja | – | – | – | 2,2 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

_Keine Namensraum-Ereignisse._

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-active` | functions.php |
| `data-bg-video` | blocks/hero.php, assets/js/site.js |
| `data-bg-video-toggle` | blocks/hero.php, assets/js/site.js |
| `data-category` | blocks/cards.php, assets/js/filter.js, functions.php |
| `data-cms-edit` | blocks/quote.php |
| `data-contrast` | assets/js/preferences-early.js, assets/js/preferences.js |
| `data-design-preview` | templates/layout.php |
| `data-dl-count` | blocks/downloads.php, assets/js/downloads.js |
| `data-dl-search` | blocks/downloads.php, assets/js/downloads.js |
| `data-dl-tag` | blocks/downloads.php, assets/js/downloads.js |
| `data-dl-view` | blocks/downloads.php, assets/js/downloads.js |
| `data-downloads` | blocks/downloads.php, assets/js/downloads.js |
| `data-focus` | assets/js/preferences-early.js, assets/js/preferences.js |
| `data-font-preview` | assets/js/preferences.js |
| `data-header` | templates/partials/header-sidebar.php, templates/partials/header.php, assets/js/site.js |
| `data-key` | assets/js/preferences.js |
| `data-label-dark` | templates/partials/header.php, assets/js/scheme.js |
| `data-label-light` | templates/partials/header.php, assets/js/scheme.js |
| `data-label-pause` | blocks/hero.php, assets/js/site.js |
| `data-label-play` | blocks/hero.php, assets/js/site.js |
| `data-label-status` | functions.php |
| `data-label-status-none` | functions.php |
| `data-label-status-one` | functions.php |
| `data-links` | assets/js/preferences-early.js, assets/js/preferences.js |
| `data-mnav` | templates/partials/sheet.php, assets/js/site.js |
| `data-motion` | assets/js/preferences-early.js, assets/js/preferences.js, assets/js/prefs-theme.js |
| `data-motion-hint` | assets/js/preferences.js |
| `data-ncss-filter-out` | assets/js/filter.js |
| `data-ncss-font` | assets/js/preferences-early.js, assets/js/preferences.js |
| `data-ncss-preferences-fallback` | assets/js/preferences.js |
| `data-none` | blocks/downloads.php, assets/js/downloads.js |
| `data-of` | blocks/downloads.php, assets/js/downloads.js |
| `data-q` | assets/js/downloads.js |
| `data-reel-ctrl` | blocks/cards.php, blocks/quote.php, assets/js/reel.js |
| `data-reel-next` | blocks/cards.php, blocks/quote.php, assets/js/reel.js |
| `data-reel-prev` | blocks/cards.php, blocks/quote.php, assets/js/reel.js |
| `data-reveal` | blocks/bento.php, blocks/cards.php, blocks/features.php, blocks/pricing.php, blocks/quote.php … |
| `data-reveal-children` | blocks/richtext.php, assets/js/site.js |
| `data-scheme` | templates/layout.php, assets/js/prefs-theme.js |
| `data-scheme-toggle` | templates/partials/header.php, assets/js/scheme.js |
| `data-scope` | templates/layout.php |
| `data-scrollspy` | blocks/richtext.php, assets/js/site.js |
| `data-scrolly` | blocks/scrolly.php, assets/js/scrolly.js |
| `data-side` | templates/partials/header-sidebar.php |
| `data-step` | blocks/scrolly.php, assets/js/scrolly.js |
| `data-step-img` | blocks/scrolly.php, assets/js/scrolly.js |
| `data-sw` | templates/layout.php |
| `data-tabs` | blocks/tabs.php, assets/js/tabs.js |
| `data-tags` | blocks/downloads.php, assets/js/downloads.js |
| `data-text-scale` | assets/js/preferences-early.js, assets/js/preferences.js |
| `data-text-spacing` | assets/js/preferences-early.js, assets/js/preferences.js |
| `data-theme` | assets/js/preferences-early.js, assets/js/preferences.js, assets/js/prefs-theme.js |
| `data-theme-resolved` | assets/js/preferences-early.js, assets/js/preferences.js |
| `data-totop` | templates/layout.php, assets/js/site.js |
| `data-view` | blocks/downloads.php, assets/js/downloads.js |

<!-- docs:assets:end -->
