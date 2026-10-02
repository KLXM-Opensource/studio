# CSS & JS – Kit „Modern – klar und selbstbewusst“ (modern)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=modern --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden (Layout, Wartung, offline): `css/editing.css`, `css/nav-extended.css`, `css/overlay.css`, `css/pages.css`, `css/preview.css`, `css/site.css`, `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, 24 Einträge).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/modern/`).

## 2. Tokens & Farben

- Kit-Variablen: `--m-*` an `:root`.
- Gesetzte Kern-Variablen: `--cal-accent`, `--cal-accent-ink`, `--cal-ev-bg`, `--cal-line`, `--cal-muted`, `--cal-radius`, `--cal-surface`, `--cal-today-bg`, `--cms-map-accent`, `--cms-map-bg`, `--cms-map-line`, `--cms-map-radius`, `--dff-gap`, `--dial-accent`, `--dial-font`, `--dial-ink`, `--dial-line`, `--dial-muted`, `--dial-surface`, `--dial-tick`, `--dial-track`, `--dl-gap`, `--ha-accent`, `--ha-bg` ….

## 3. Blöcke → Dateien

- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.

## 4. Animationen & Regeln

- Dateien mit Keyframes/Scroll-Animation: `css/_header.css`, `css/b-faq.css`, `css/b-logos.css`, `css/hero-figures.css`, `css/hero-marquee.css`, `css/hero-product.css`, `css/hero-x.css`, `css/media.css`, `css/overlay.css`.
- `prefers-reduced-motion` in `css/site.css`: nein · `forced-colors`: nein · `:focus-visible`: nein.
- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.

## 5. Overlays, Sheets, Dialoge

- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).

## 6. JavaScript & Ereignisse

- Skripte: `js/site.js`, `js/tabs.js`.
- Ereignisse: keine eigenen.

## 7. Sonderfälle

- _keine bekannt_

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=modern --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Modern – klar und selbstbewusst“ (modern 1.0.0)

### Von Vorlagen eingebunden (theme_asset() in templates/)

Feste Namen; zusammengesetzte (z. B. `css/nav-{stil}.css`) erkennt der Generator nicht.

- `css/editing.css` – templates/layout.php
- `css/nav-extended.css` – templates/layout.php
- `css/overlay.css` – templates/layout.php
- `css/pages.css` – templates/error.php, templates/maintenance.php, templates/offline.php
- `css/preview.css` – templates/layout.php
- `css/site.css` – templates/layout.php, templates/maintenance.php, templates/offline.php
- `js/site.js` – templates/layout.php

### Bedingt geladen – theme.php → conditional_css

| Datei | lädt bei |
|---|---|
| `css/hero-x.css` | hero:statement, hero:mosaic |
| `css/hero-product.css` | hero:product |
| `css/hero-marquee.css` | hero:marquee |
| `css/hero-figures.css` | hero:figures |
| `css/prose.css` | richtext, tabs, faq, media_text, video, data_fields, @rich |
| `css/b-article.css` | richtext:article, richtext:columns |
| `css/b-features.css` | features |
| `css/b-media-text.css` | media_text |
| `css/b-cta.css` | cta |
| `css/b-bento.css` | bento |
| `css/b-cards.css` | cards |
| `css/b-logos.css` | logos |
| `css/b-stats.css` | stats |
| `css/b-quote.css` | quote |
| `css/b-steps.css` | steps |
| `css/b-pricing.css` | pricing |
| `css/b-team.css` | team |
| `css/b-faq.css` | faq |
| `css/b-tabs.css` | tabs |
| `css/b-contact.css` | contact, map |
| `css/b-video.css` | video |
| `css/b-downloads.css` | downloads |
| `css/dataform.css` | contact |
| `js/tabs.js` | tabs |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `bento` Bento-Raster | `blocks/bento.php` | – | `css/b-bento.css` |
| `cards` Karten | `blocks/cards.php` | image, overlay, horizontal | `css/b-cards.css` |
| `contact` Kontakt (Karte, Formular, Zeiten) | `blocks/contact.php` | – | `css/b-contact.css`, `css/dataform.css` |
| `cta` Handlungsaufruf | `blocks/cta.php` | band, box, split, big | `css/b-cta.css` |
| `downloads` Downloads | `blocks/downloads.php` | – | `css/b-downloads.css` |
| `faq` Fragen & Antworten | `blocks/faq.php` | split, stacked | `css/prose.css`, `css/b-faq.css` |
| `features` Merkmale / Leistungen | `blocks/features.php` | cards, icons, list, numbered | `css/b-features.css` |
| `hero` Einstieg (Hero) | `blocks/hero.php` | split, statement, mosaic, compact, product, marquee, figures | `css/hero-x.css` (statement, mosaic), `css/hero-product.css` (product), `css/hero-marquee.css` (marquee), `css/hero-figures.css` (figures) |
| `logos` Logos (Partner, Kunden) | `blocks/logos.php` | grid, marquee | `css/b-logos.css` |
| `map` Karte | `blocks/map.php` | – | `css/b-contact.css` |
| `media_text` Text + Bild | `blocks/media_text.php` | auto, right, left, split, overlap | `css/prose.css`, `css/b-media-text.css` |
| `pricing` Angebote / Pakete | `blocks/pricing.php` | cards, table | `css/b-pricing.css` |
| `quote` Zitat / Stimmen | `blocks/quote.php` | single, grid | `css/b-quote.css` |
| `richtext` Fließtext / Artikel | `blocks/richtext.php` | standard, article, columns | `css/prose.css`, `css/b-article.css` (article, columns) |
| `stats` Kennzahlen | `blocks/stats.php` | row, cards, split | `css/b-stats.css` |
| `steps` Ablauf / Zeitleiste | `blocks/steps.php` | numbers, timeline, process | `css/b-steps.css` |
| `tabs` Reiter (Tabs) | `blocks/tabs.php` | top, side | `css/prose.css`, `css/b-tabs.css`, `js/tabs.js` |
| `team` Team | `blocks/team.php` | grid, list | `css/b-team.css` |
| `video` Video | `blocks/video.php` | wide, text, cinema | `css/prose.css`, `css/b-video.css` |

### Variablen je Stylesheet

**css/_base.css** (34)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dial-accent` | `var(--m-a2)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--dial-ink` | `var(--m-fg)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--dial-line` | `var(--m-line)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--dial-muted` | `var(--m-muted)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--dial-surface` | `color-mix(in srgb,var(--m-fg) 6%,transparent)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--dial-tick` | `color-mix(in srgb,var(--m-fg) 42%,transparent)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--dial-track` | `color-mix(in srgb,var(--m-fg) 16%,transparent)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--fx` | `0%` | `.fx0` | 9 |
| `--fy` | `0%` | `.fy0` | 9 |
| `--gap` | `var(--m-s-l)` | `.grid` | – |
| `--ico-2-opacity` | `.24` | `.ico` | – |
| `--m-a-soft` | `color-mix(in srgb,var(--m-fg) 12%,transparent)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--m-btn-bg` | `var(--m-fg)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--m-btn-hover` | `var(--m-a2)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--m-btn-hover-ink` | `var(--m-a)` | `.bg-accent` | 2 |
| `--m-btn-ink` | `var(--m-a)` | `.bg-accent` | 2 |
| `--m-card` | `var(--m-bg)` | `.bg-muted` | 1 |
| `--m-card-bd` | `var(--m-line)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--m-card-sh` | `none` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--m-fg` | `var(--m-a-on)` | `.bg-accent` | 2 |
| `--m-focus` | `var(--m-fg)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--m-ico` | `var(--m-a)` | `.bg-tint` | 2 |
| `--m-ico-bg` | `var(--m-bg)` | `.bg-tint` | 1 |
| `--m-ink` | `var(--m-pop-ink)` | `.bg-pop` | 1 |
| `--m-line` | `color-mix(in srgb,var(--m-ink) 16%,transparent)` | `.bg-pop` | 1 |
| `--m-line-strong` | `color-mix(in srgb,var(--m-fg) 48%,transparent)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--m-link` | `var(--m-ink)` | `.bg-pop` | 1 |
| `--m-mark` | `var(--m-ink)` | `.bg-pop` | 2 |
| `--m-muted` | `var(--m-text)` | `.bg-pop` | 4 |
| `--m-sec-bg` | `var(--m-surface)` | `.bg-muted` | 4 |
| `--m-surface` | `color-mix(in srgb,var(--m-bg) 60%,transparent)` | `.bg-tint` | 2 |
| `--m-surface-2` | `color-mix(in srgb,var(--m-fg) 14%,transparent)` | `.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--m-text` | `var(--m-pop-text)` | `.bg-pop` | 1 |
| `--min` | `13rem` | `.min-s` | 2 |

**css/_footer.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `.25rem 1.5rem` | `.ftr__legal` | 1 |
| `--m-card` | `var(--m-bg)` | `.ftr` | – |

**css/_header.css** (11)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-accent` | `var(--m-a)` | `.ha,.ha-below` | – |
| `--ha-bg` | `var(--m-bg)` | `.ha,.ha-below` | – |
| `--ha-gap` | `.5rem` | `.ha,.ha-below` | – |
| `--ha-h` | `2.75rem` | `.ha,.ha-below` | – |
| `--ha-ink` | `var(--m-ink)` | `.ha,.ha-below` | – |
| `--ha-line` | `var(--m-line-strong)` | `.ha,.ha-below` | – |
| `--ha-muted` | `var(--m-muted)` | `.ha,.ha-below` | – |
| `--ha-on` | `var(--m-a-on)` | `.ha,.ha-below` | – |
| `--ha-open` | `var(--m-link)` | `.ha,.ha-below` | – |
| `--ha-surface` | `var(--m-surface)` | `.ha,.ha-below` | – |
| `--m-hdr-bg` | `color-mix(in srgb,var(--m-bg) 84%,transparent)` | `.hdr` | – |

**css/_tokens.css** (87)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-a` | `var(--m-a)` | `:root` | – |
| `--b-a-on` | `var(--m-a-on)` | `:root` | – |
| `--b-bg` | `var(--m-bg)` | `:root` | – |
| `--b-ink` | `var(--m-ink)` | `:root` | – |
| `--b-line` | `var(--m-line)` | `:root` | – |
| `--b-muted` | `var(--m-muted)` | `:root` | – |
| `--b-radius` | `var(--m-r-s)` | `:root` | – |
| `--b-surface` | `var(--m-surface)` | `:root` | – |
| `--dial-accent` | `var(--m-link)` | `:root` | – |
| `--dial-font` | `var(--m-font-head)` | `:root` | – |
| `--dial-ink` | `var(--m-ink)` | `:root` | – |
| `--dial-line` | `var(--m-line)` | `:root` | – |
| `--dial-muted` | `var(--m-muted)` | `:root` | – |
| `--dial-surface` | `var(--m-card)` | `:root` | – |
| `--dial-tick` | `var(--m-line-strong)` | `:root` | – |
| `--dial-track` | `var(--m-surface-2)` | `:root` | – |
| `--m-a` | `#086B4B` | `:root` | 1 |
| `--m-a-on` | `#FFFFFF` | `:root` | 1 |
| `--m-a-soft` | `color-mix(in srgb,var(--m-a) 14%,var(--m-bg))` | `:root` | – |
| `--m-a-strong` | `#05533A` | `:root` | 1 |
| `--m-a2` | `#C9F25E` | `:root` | 1 |
| `--m-bg` | `#FFFFFF` | `:root` | 1 |
| `--m-btn-bg` | `var(--m-ink)` | `:root` | 1 |
| `--m-btn-hover` | `var(--m-a)` | `:root` | 1 |
| `--m-btn-hover-ink` | `var(--m-a-on)` | `:root` | – |
| `--m-btn-ink` | `var(--m-bg)` | `:root` | 1 |
| `--m-btn-r` | `8px` | `:root` | – |
| `--m-card` | `var(--m-bg)` | `:root` | 1 |
| `--m-card-bd` | `var(--m-line)` | `:root` | 1 |
| `--m-card-sh` | `none` | `:root` | 1 |
| `--m-dark` | `#0B1320` | `:root` | 1 |
| `--m-display` | `1` | `:root` | – |
| `--m-focus` | `var(--m-a)` | `:root` | – |
| `--m-font` | `"Plus Jakarta Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif` | `:root` | – |
| `--m-font-head` | `"Space Grotesk",ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif` | `:root` | – |
| `--m-font-label` | `var(--m-font-head)` | `:root` | – |
| `--m-font-mono` | `ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace` | `:root` | – |
| `--m-fs` | `17` | `:root` | – |
| `--m-gutter` | `1.25rem` | `:root` | 2 |
| `--m-hw` | `600` | `:root` | – |
| `--m-ico` | `var(--m-a)` | `:root` | – |
| `--m-ico-bg` | `var(--m-a-soft)` | `:root` | – |
| `--m-ink` | `#0A0D12` | `:root` | 1 |
| `--m-line` | `#E2E5EA` | `:root` | 1 |
| `--m-line-strong` | `color-mix(in srgb,var(--m-ink) 30%,var(--m-bg))` | `:root` | – |
| `--m-link` | `var(--m-a)` | `:root` | – |
| `--m-mark` | `var(--m-a)` | `:root` | – |
| `--m-muted` | `#565E6B` | `:root` | 1 |
| `--m-on-dark` | `#FFFFFF` | `:root` | – |
| `--m-pad` | `1.375rem` | `:root` | 2 |
| `--m-pop-ink` | `var(--m-ink)` | `:root` | – |
| `--m-pop-text` | `var(--m-text)` | `:root` | – |
| `--m-r` | `calc(1 + (var(--m-ratio) - 1) * .55)` | `:root` | 2 |
| `--m-r-l` | `var(--m-radius)` | `:root` | – |
| `--m-r-s` | `8px` | `:root` | – |
| `--m-radius` | `14px` | `:root` | – |
| `--m-ratio` | `1.3` | `:root` | – |
| `--m-s-2xl` | `3rem` | `:root` | 2 |
| `--m-s-2xs` | `.25rem` | `:root` | – |
| `--m-s-3xl` | `4.5rem` | `:root` | 2 |
| `--m-s-l` | `1.5rem` | `:root` | – |
| `--m-s-m` | `1rem` | `:root` | – |
| `--m-s-s` | `.75rem` | `:root` | – |
| `--m-s-xl` | `2rem` | `:root` | – |
| `--m-s-xs` | `.5rem` | `:root` | – |
| `--m-sec` | `calc(4.5rem * var(--m-space))` | `:root` | 2 |
| `--m-sh1` | `rgb(10 13 18/.06)` | `:root` | 2 |
| `--m-sh2` | `rgb(10 13 18/.18)` | `:root` | 2 |
| `--m-space` | `1` | `:root` | – |
| `--m-surface` | `#F3F4F6` | `:root` | 1 |
| `--m-surface-2` | `color-mix(in srgb,var(--m-ink) 5%,var(--m-surface))` | `:root` | – |
| `--m-t-1` | `max(.875rem,calc(var(--m-t0) * .875))` | `:root` | – |
| `--m-t-2` | `max(.8125rem,calc(var(--m-t0) * .78))` | `:root` | – |
| `--m-t0` | `calc(var(--m-fs) * .0625rem)` | `:root` | – |
| `--m-t1` | `calc(var(--m-t0) * var(--m-r))` | `:root` | – |
| `--m-t2` | `calc(var(--m-t0) * pow(var(--m-r),2))` | `:root` | – |
| `--m-t3` | `calc(var(--m-t0) * pow(var(--m-r),3))` | `:root` | – |
| `--m-t4` | `calc(var(--m-t0) * pow(var(--m-r),4))` | `:root` | – |
| `--m-t5` | `calc(var(--m-t0) * pow(var(--m-r),5))` | `:root` | – |
| `--m-t6` | `calc(var(--m-t0) * pow(var(--m-r),6))` | `:root` | – |
| `--m-text` | `#2A303A` | `:root` | 1 |
| `--m-track` | `-3.5` | `:root` | – |
| `--m-wrap` | `80rem` | `:root` | – |
| `--m0-ink` | `var(--m-ink)` | `:root` | – |
| `--m0-link` | `var(--m-link)` | `:root` | – |
| `--m0-muted` | `var(--m-muted)` | `:root` | – |
| `--m0-text` | `var(--m-text)` | `:root` | – |

**css/b-cards.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `var(--m-s-l)` | `.cards` | – |
| `--m-fg` | `var(--m-on-dark)` | `.tcard--overlay .tcard__body` | – |
| `--m-ink` | `var(--m-fg)` | `.tcard--overlay .tcard__body` | – |
| `--m-link` | `var(--m-fg)` | `.tcard--overlay .tcard__body` | – |
| `--m-muted` | `color-mix(in srgb,var(--m-fg) 88%,transparent)` | `.tcard--overlay .tcard__body` | – |
| `--m-text` | `var(--m-fg)` | `.tcard--overlay .tcard__body` | – |
| `--min` | `19rem` | `.cards--reel` | – |

**css/b-contact.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-map-accent` | `var(--m-a)` | `.cms-map` | – |
| `--cms-map-bg` | `var(--m-surface-2)` | `.cms-map` | – |
| `--cms-map-line` | `var(--m-line)` | `.cms-map` | – |
| `--cms-map-radius` | `var(--m-r-l)` | `.cms-map` | – |

**css/b-cta.css** (12)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--m-btn-bg` | `var(--m-pop-ink)` | `.cta--box` | – |
| `--m-btn-hover` | `var(--m-bg)` | `.cta--box` | – |
| `--m-btn-hover-ink` | `var(--m-pop-ink)` | `.cta--box` | – |
| `--m-btn-ink` | `var(--m-a2)` | `.cta--box` | – |
| `--m-focus` | `var(--m-pop-ink)` | `.cta--box` | – |
| `--m-ink` | `var(--m-pop-ink)` | `.cta--box` | – |
| `--m-line-strong` | `color-mix(in srgb,var(--m-pop-ink) 45%,transparent)` | `.cta--box` | – |
| `--m-link` | `var(--m-pop-ink)` | `.cta--box` | – |
| `--m-mark` | `var(--m-pop-ink)` | `.cta--box` | – |
| `--m-muted` | `var(--m-pop-ink)` | `.cta--box` | – |
| `--m-surface` | `color-mix(in srgb,var(--m-bg) 40%,transparent)` | `.cta--box` | – |
| `--m-text` | `var(--m-pop-ink)` | `.cta--box` | – |

**css/b-features.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `var(--m-s-s)` | `.feats` | 4 |
| `--ico-2-opacity` | `.35` | `.feats--icons .feat__ico` | – |

**css/b-media-text.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--m-card` | `var(--m-surface)` | `.mt-over__card` | – |

**css/b-pricing.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--min` | `16rem` | `.plans` | – |

**css/b-quote.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ico-2-opacity` | `.4` | `.pull__mark` | – |

**css/b-stats.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `var(--m-s-xl) var(--m-s-xl)` | `.stats` | – |
| `--min` | `9rem` | `.stats` | – |

**css/b-steps.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--min` | `14rem` | `.steps--numbers` | – |

**css/b-team.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `var(--m-s-m)` | `.team` | – |
| `--min` | `9.5rem` | `.team.min-s` | 3 |

**css/b-video.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--m-fg` | `var(--m-on-dark)` | `.sec.v-cinema` | – |
| `--m-focus` | `var(--m-fg)` | `.sec.v-cinema` | – |
| `--m-ink` | `var(--m-fg)` | `.sec.v-cinema` | – |
| `--m-line` | `color-mix(in srgb,var(--m-fg) 20%,transparent)` | `.sec.v-cinema` | – |
| `--m-link` | `var(--m-fg)` | `.sec.v-cinema` | – |
| `--m-muted` | `color-mix(in srgb,var(--m-fg) 80%,var(--m-dark))` | `.sec.v-cinema` | – |
| `--m-sec-bg` | `var(--m-dark)` | `.sec.v-cinema` | – |
| `--m-text` | `var(--m-fg)` | `.sec.v-cinema` | – |

**css/calendar.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cal-accent` | `var(--m-btn-bg)` | `.cal` | – |
| `--cal-accent-ink` | `var(--m-btn-ink)` | `.cal` | – |
| `--cal-ev-bg` | `var(--m-a-soft)` | `.cal` | – |
| `--cal-line` | `var(--m-line)` | `.cal` | – |
| `--cal-muted` | `var(--m-muted)` | `.cal` | – |
| `--cal-radius` | `var(--m-r-l)` | `.cal` | – |
| `--cal-surface` | `var(--m-surface)` | `.cal` | 1 |
| `--cal-today-bg` | `var(--m-a-soft)` | `.cal` | – |

**css/data.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dl-gap` | `var(--m-s-l)` | `.dl,.df` | – |

**css/dataform.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dff-gap` | `var(--m-s-m)` | `.dff-wrap` | – |
| `--m-danger` | `color-mix(in srgb,#E5484D 66%,var(--m-ink))` | `.dff-wrap` | – |

**css/hero-figures.css** (10)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dial-accent` | `var(--m-link)` | `.hfg .cms-dial` | 4 |
| `--dial-ink` | `var(--m-ink)` | `.hfg .cms-dial` | 4 |
| `--dial-line` | `var(--m-line)` | `.hfg .cms-dial` | 3 |
| `--dial-muted` | `var(--m-muted)` | `.hfg .cms-dial` | 4 |
| `--dial-surface` | `var(--m-bg)` | `.hfg .cms-dial` | 3 |
| `--dial-tick` | `color-mix(in srgb,var(--m-ink) 35%,transparent)` | `.hfg .cms-dial` | 3 |
| `--dial-track` | `color-mix(in srgb,var(--m-ink) 12%,transparent)` | `.hfg .cms-dial` | 3 |
| `--hfg-bd` | `transparent` | `.hfg .cms-dial` | 1 |
| `--hfg-bg` | `var(--m-a-soft)` | `.hfg .cms-dial` | 5 |
| `--hfg-lime` | `var(--m-a2)` | `.hfg` | 1 |

**css/hero-product.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--hpr-dot` | `var(--m-a)` | `html.has-dark .hpr` | – |

**css/media.css** (11)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ar` | `5` | `.ar-5` | 19 |
| `--i` | `1` | `.cms-stack__card:nth-child(2)` | 6 |
| `--m-btn-bg` | `var(--m-on-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--m-btn-hover` | `color-mix(in srgb,var(--m-on-dark) 86%,var(--m-dark))` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--m-btn-ink` | `var(--m-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--m-ink` | `var(--m0-ink)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--m-link` | `var(--m0-link)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--m-muted` | `var(--m0-muted)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--m-text` | `var(--m0-text)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--n` | `2` | `.n-2` | 6 |
| `--row` | `clamp(8rem,3rem + 16vw,16rem)` | `.cms-gallery--justified` | 3 |

**css/pages.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--stack` | `var(--m-s-m)` | `.err__text` | – |

**css/preview.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--m-btn-bg` | `var(--m-a)` | `html.is-dark` | – |
| `--m-btn-hover` | `var(--m-a-strong)` | `html.is-dark` | – |
| `--m-btn-ink` | `var(--m-a-on)` | `html.is-dark` | – |
| `--m-sh1` | `rgb(0 0 0/.3)` | `html.is-dark` | – |
| `--m-sh2` | `rgb(0 0 0/.6)` | `html.is-dark` | – |

**css/prose.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--rt-accent` | `var(--m-link)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--rt-danger` | `#B42318` | `:root` | 6 |
| `--rt-muted` | `var(--m-muted)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--rt-success` | `#1A7240` | `:root` | 6 |
| `--rt-warning` | `#8A4B00` | `:root` | 6 |

**css/search.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--se-accent` | `var(--m-a)` | `.srch,.sf,.ssug` | – |
| `--se-bg` | `var(--m-bg)` | `.srch,.sf,.ssug` | – |
| `--se-line` | `var(--m-line)` | `.srch,.sf,.ssug` | – |
| `--se-mark` | `color-mix(in srgb,var(--m-a2) 70%,transparent)` | `.srch,.sf,.ssug` | – |
| `--se-muted` | `var(--m-muted)` | `.srch,.sf,.ssug` | – |
| `--se-radius` | `var(--m-radius)` | `.srch,.sf,.ssug` | – |
| `--se-surface` | `var(--m-surface)` | `.srch,.sf,.ssug` | – |

**css/sections.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--m-sec-bg` | `var(--m-bg)` | `.sec--has-bg.bg-white,.sec--has-bg.bg-muted,.sec--has-bg.bg-tint` | – |

### Bewegung & Barrierefreiheit je Datei

| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |
|---|---|---|---|---|---|
| `css/_base.css` | – | ja | ja | – | ja |
| `css/_header.css` | m-hdr | ja | – ⚠ | – | – |
| `css/b-bento.css` | – | ja | – | – | – |
| `css/b-cards.css` | – | ja | – | – | – |
| `css/b-faq.css` | m-drop | ja | ja | – | – |
| `css/b-features.css` | – | ja | – | – | – |
| `css/b-logos.css` | m-marquee | ja | ja | – | – |
| `css/b-team.css` | – | ja | – | – | – |
| `css/calendar.css` | – | – | – | ja | ja |
| `css/data.css` | – | ja | – | – | ja |
| `css/dataform.css` | – | ja | – | – | ja |
| `css/hero-figures.css` | hfg-rise | ja | ja | ja | – |
| `css/hero-marquee.css` | hmq-run | ja | ja | ja | ja |
| `css/hero-product.css` | hpr-stamp, hpr-rise | ja | ja | ja | – |
| `css/hero-x.css` | m-rise | ja | ja | – | – |
| `css/media.css` | cms-lb-in, cms-stack | ja | ja | – | ja |
| `css/overlay.css` | m-sheet | ja | – ⚠ | – | – |
| `css/search.css` | – | – | – | ja | ja |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/site.js` | ja | ja | ja | – | 8,2 KB (Quelle) |
| `js/tabs.js` | – | – | – | – | 1,3 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

_Keine Namensraum-Ereignisse._

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-active` | functions.php |
| `data-closed` | templates/partials/header.php, assets/js/site.js |
| `data-design-preview` | templates/layout.php |
| `data-dows` | templates/partials/header.php, assets/js/site.js |
| `data-header` | templates/partials/header.php, assets/js/site.js |
| `data-hours` | templates/partials/header.php, assets/js/site.js |
| `data-mnav` | templates/partials/sheet.php, assets/js/site.js |
| `data-open` | templates/partials/header.php, assets/js/site.js |
| `data-openstate` | templates/partials/header.php, assets/js/site.js |
| `data-openstate-text` | templates/partials/header.php, assets/js/site.js |
| `data-reveal` | blocks/bento.php, blocks/cards.php, blocks/features.php, blocks/pricing.php, blocks/quote.php … |
| `data-scope` | templates/layout.php |
| `data-slots` | templates/partials/header.php, assets/js/site.js |
| `data-sw` | templates/layout.php |
| `data-tabs` | blocks/tabs.php, assets/js/tabs.js |

<!-- docs:assets:end -->
