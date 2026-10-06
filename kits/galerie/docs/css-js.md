# CSS & JS – Kit „Galerie“ (galerie)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=galerie --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Abgeleitet vom Kit „fluid“ (breakpointlos, Präfix der Variablen `--f-*` bleibt), dazu Galerie-Variablen `--g-*`.
- Von den Vorlagen eingebunden: `css/site.css`, `css/overlay.css`, `css/pages.css`, `css/preview.css`, `css/editing.css`, `js/site.js`.
- Galerie-Blöcke: `css/b-gallery.css` (gemeinsam: Etiketten, Werkangaben, Punkte, Präsentation, Lightbox-Liste, gemischte Galerie) + je Familie
  `b-exhibitions.css`, `b-artists.css`, `b-artworks.css`, `b-visit.css`; `js/gallery.js` (Filter), `js/tabs.js` (Reiter der Ausstellungsliste).
- Nur für die angemeldete Redaktion: `css/gallery-edit.css` + `js/gallery-edit.js` (Bilder und Videos per Ziehen & Ablegen, „Neues Werk aus Foto“) –
  von den Blöcken selbst eingebunden (Detailseite) bzw. `theme.php → editor_js` (Seiten-Editor).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/galerie/`).

## 2. Tokens & Farben

- Kit-Variablen: `--f-*` an `:root`; Galerie: `--g-avail`, `--g-sold` (Punkte, hell/dunkel), `--g-density` (Dichte), `--g-mat` (Passepartout).
- Klassen am `<html>`: `af-{flat|wall|mat}`, `cap-{museum|minimal|line}`, `has-dots`, `pr-*`, `ds-*`, `lbl-{text|pill|dot|outline}`, `ld-{compact|normal|airy}`.

## 3. Blöcke → Dateien

- Tabelle im Anhang. `css/media.css` (Kit) lädt für die Detailseiten (Lightbox des Kerns), `css/b-video.css` für Detailseiten mit Videos.

## 4. Animationen & Regeln

- Bild beim Zeigen (Künstlerliste): Übergang `opacity`/`scale`, bei „Bewegung reduzieren“ ohne Übergang (globale Regel in `_base.css`).
- Viewing Room: `scroll-snap-type: y proximity` nur auf Seiten mit `.vr` (`html:has(.vr)`); Videos starten nie automatisch.

## 5. Overlays, Sheets, Dialoge

- Lightbox: Kern (`js/media.mjs`, `MediaBlocks::lightbox()`), nur Bilder; Videos stehen in einer eigenen Liste daneben.

## 6. JavaScript & Ereignisse

- Skripte: `js/site.js`, `js/tabs.js`, `js/reel.js`, `js/gallery.js`, `js/gallery-edit.js`.
- `gallery-edit.js` nutzt `window.CMSMedia.Uploader` / `.pick` (Kern) und `POST /admin/api/entries/{tabelle}/{id|new}`; öffnet die Seitenleiste des Kerns über ein
  Element mit `data-entry-edit`.

## 7. Sonderfälle

- Salonhängung: Flex-Zeile mit Höhen in `cqi`, Angaben per `display: table-caption` so breit wie das Werk.

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=galerie --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Galerie“ (galerie 0.1.0)

### Von Vorlagen eingebunden (theme_asset() in templates/)

Feste Namen; zusammengesetzte (z. B. `css/nav-{stil}.css`) erkennt der Generator nicht.

- `css/editing.css` – templates/layout.php
- `css/overlay.css` – templates/layout.php
- `css/pages.css` – templates/error.php, templates/maintenance.php, templates/offline.php
- `css/preview.css` – templates/layout.php
- `css/site.css` – templates/layout.php, templates/maintenance.php, templates/offline.php
- `js/site.js` – templates/layout.php

### Bedingt geladen – theme.php → conditional_css

| Datei | lädt bei |
|---|---|
| `css/hero-x.css` | hero:fullbleed, hero:cards, hero:type |
| `css/hx-collage.css` | hero:collage |
| `css/prose.css` | richtext, tabs, faq, media_text, @rich |
| `css/b-article.css` | richtext:article, richtext:columns |
| `css/b-features.css` | features |
| `css/b-media-text.css` | media_text |
| `css/b-cta.css` | cta |
| `css/reel.css` | cards:reel, quote:reel |
| `css/b-cards.css` | cards |
| `css/b-logos.css` | logos |
| `css/b-quote.css` | quote |
| `css/b-steps.css` | steps |
| `css/b-faq.css` | faq |
| `css/b-tabs.css` | tabs |
| `css/b-contact.css` | contact, map |
| `css/b-downloads.css` | downloads |
| `css/dataform.css` | contact |
| `css/b-gallery.css` | exhibition_feature, exhibitions_list, exhibition_detail, artists_index, artist_profile, artworks, artwork_detail, visit |
| `css/b-exhibitions.css` | exhibition_feature, exhibitions_list, exhibition_detail |
| `css/b-artists.css` | artists_index, artist_profile |
| `css/b-artworks.css` | artworks, artwork_detail, exhibition_detail |
| `css/b-visit.css` | visit |
| `css/media.css` | artwork_detail, exhibition_detail |
| `js/tabs.js` | tabs, exhibitions_list |
| `js/gallery.js` | artworks |
| `css/b-video.css` | video, exhibition_detail, artwork_detail, artist_profile |
| `js/reel.js` | quote, cards |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `artist_profile` Künstler (Detailseite) | `blocks/artist_profile.php` | split, name | `css/b-gallery.css`, `css/b-artists.css`, `css/b-video.css` |
| `artists_index` Künstlerinnen und Künstler | `blocks/artists_index.php` | list, grid, works, az | `css/b-gallery.css`, `css/b-artists.css` |
| `artwork_detail` Werk (Detailseite) | `blocks/artwork_detail.php` | – | `css/b-gallery.css`, `css/b-artworks.css`, `css/media.css`, `css/b-video.css` |
| `artworks` Werke | `blocks/artworks.php` | grid, masonry, salon, room | `css/b-gallery.css`, `css/b-artworks.css`, `js/gallery.js` |
| `cards` Karten | `blocks/cards.php` | image, overlay, horizontal, reel | `css/reel.css` (reel), `css/b-cards.css`, `js/reel.js` |
| `contact` Kontakt (Karte, Formular, Zeiten) | `blocks/contact.php` | – | `css/b-contact.css`, `css/dataform.css` |
| `cta` Handlungsaufruf | `blocks/cta.php` | band, box, split, big | `css/b-cta.css` |
| `downloads` Downloads | `blocks/downloads.php` | – | `css/b-downloads.css` |
| `exhibition_detail` Ausstellung (Detailseite) | `blocks/exhibition_detail.php` | bleed, split, text | `css/b-gallery.css`, `css/b-exhibitions.css`, `css/b-artworks.css`, `css/media.css`, `css/b-video.css` |
| `exhibition_feature` Aktuelle Ausstellung | `blocks/exhibition_feature.php` | bleed, split, type | `css/b-gallery.css`, `css/b-exhibitions.css` |
| `exhibitions_list` Ausstellungen | `blocks/exhibitions_list.php` | list, cards, timeline, fairs | `css/b-gallery.css`, `css/b-exhibitions.css`, `js/tabs.js` |
| `faq` Fragen & Antworten | `blocks/faq.php` | split, stacked | `css/prose.css`, `css/b-faq.css` |
| `features` Merkmale / Leistungen | `blocks/features.php` | cards, icons, list, numbered | `css/b-features.css` |
| `hero` Einstieg (Hero) | `blocks/hero.php` | split, centered, fullbleed, cards, type, compact, collage | `css/hero-x.css` (fullbleed, cards, type), `css/hx-collage.css` (collage) |
| `logos` Logos (Partner, Kunden) | `blocks/logos.php` | grid, marquee | `css/b-logos.css` |
| `map` Karte | `blocks/map.php` | – | `css/b-contact.css` |
| `media_text` Text + Bild | `blocks/media_text.php` | auto, right, left, split, overlap | `css/prose.css`, `css/b-media-text.css` |
| `quote` Zitat / Stimmen | `blocks/quote.php` | single, grid, reel | `css/reel.css` (reel), `css/b-quote.css`, `js/reel.js` |
| `richtext` Fließtext / Artikel | `blocks/richtext.php` | standard, article, columns | `css/prose.css`, `css/b-article.css` (article, columns) |
| `steps` Ablauf / Zeitleiste | `blocks/steps.php` | numbers, timeline, process | `css/b-steps.css` |
| `tabs` Reiter (Tabs) | `blocks/tabs.php` | top, side | `css/prose.css`, `css/b-tabs.css`, `js/tabs.js` |
| `video` Video | `blocks/video.php` | wide, text, cinema | `css/b-video.css` |
| `visit` Besuch | `blocks/visit.php` | columns, split | `css/b-gallery.css`, `css/b-visit.css` |

### Variablen je Stylesheet

**css/_base.css** (24)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-a-soft` | `color-mix(in srgb,var(--f-fg) 12%,transparent)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-btn-bg` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-btn-hover` | `color-mix(in srgb,var(--f-fg) 86%,var(--f-sec-bg))` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-btn-ink` | `var(--f-a)` | `.bg-accent` | 2 |
| `--f-card` | `var(--f-bg)` | `.bg-muted` | 1 |
| `--f-card-bd` | `var(--f-line)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-card-sh` | `none` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-fg` | `var(--f-a-on)` | `.bg-accent` | 2 |
| `--f-focus` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-ico` | `var(--f-a)` | `.bg-tint` | 1 |
| `--f-ico-bg` | `var(--f-bg)` | `.bg-tint` | 1 |
| `--f-ink` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-line` | `color-mix(in srgb,var(--f-fg) 20%,transparent)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-line-strong` | `color-mix(in srgb,var(--f-fg) 48%,transparent)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-link` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-muted` | `var(--f-a-on)` | `.bg-accent` | 3 |
| `--f-sec-bg` | `var(--f-surface)` | `.bg-muted` | 3 |
| `--f-surface` | `color-mix(in srgb,var(--f-bg) 60%,transparent)` | `.bg-tint` | 1 |
| `--f-surface-2` | `color-mix(in srgb,var(--f-fg) 14%,transparent)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--f-text` | `var(--f-fg)` | `.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--fx` | `0%` | `.fx0` | 9 |
| `--fy` | `0%` | `.fy0` | 9 |
| `--ico-2-opacity` | `.24` | `.ico` | – |
| `--min` | `12.5rem` | `.min-s` | 2 |

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

**css/_options.css** (21)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-bg` | `var(--f-zone)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-btn-bg` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-btn-hover` | `color-mix(in srgb,var(--f-fg) 86%,var(--f-zone))` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-btn-ink` | `var(--f-b)` | `.bg-secondary` | 1 |
| `--f-card` | `color-mix(in srgb,var(--f-fg) 7%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-fg` | `var(--f-b-on)` | `.bg-secondary` | 2 |
| `--f-focus` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-hdr-bg` | `var(--f-surface)` | `.hbg-surface .hdr` | 1 |
| `--f-ico` | `var(--f-b)` | `html:is(.sec2-details,.sec2-all)` | – |
| `--f-ico-bg` | `color-mix(in srgb,var(--f-b) 16%,var(--f-bg))` | `html:is(.sec2-details,.sec2-all)` | – |
| `--f-ink` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-line` | `color-mix(in srgb,var(--f-fg) 20%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-line-strong` | `color-mix(in srgb,var(--f-fg) 48%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-link` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-muted` | `var(--f-b-on)` | `.bg-secondary` | 1 |
| `--f-sec-bg` | `var(--f-b)` | `.bg-secondary` | – |
| `--f-surface` | `color-mix(in srgb,var(--f-fg) 12%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-surface-2` | `color-mix(in srgb,var(--f-fg) 16%,transparent)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-text` | `var(--f-fg)` | `:is(.hbg-accent,.hbg-secondary,.hbg-dark) .hdr,:is(.fbg-accent,.fbg-secondary,.fbg-dark) .ftr,:is(.meta-accent,.meta-secondary,.meta-dark) .hdr__meta` | – |
| `--f-zone` | `var(--f-a)` | `.hbg-accent .hdr,.fbg-accent .ftr,.meta-accent .hdr__meta` | 2 |
| `--ico-2-opacity` | `0` | `.ic-outline .feat__ico` | – |

**css/_tokens.css** (101)

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
| `--f-a` | `#111111` | `:root` | 1 |
| `--f-a-on` | `#FFFFFF` | `:root` | 1 |
| `--f-a-soft` | `color-mix(in srgb,var(--f-a) 14%,var(--f-bg))` | `:root` | – |
| `--f-a-strong` | `#000000` | `:root` | 1 |
| `--f-a2` | `#EDEDE9` | `:root` | 1 |
| `--f-b` | `#3D3D3A` | `:root` | 1 |
| `--f-b-on` | `#FFFFFF` | `:root` | 1 |
| `--f-bg` | `#FFFFFF` | `:root` | 1 |
| `--f-btn-bg` | `var(--f-a)` | `:root` | – |
| `--f-btn-hover` | `var(--f-a-strong)` | `:root` | – |
| `--f-btn-ink` | `var(--f-a-on)` | `:root` | – |
| `--f-btn-r` | `var(--f-radius)` | `:root` | 2 |
| `--f-card` | `var(--f-bg)` | `:root` | 1 |
| `--f-card-bd` | `var(--f-line)` | `:root` | 2 |
| `--f-card-blur` | `none` | `:root` | – |
| `--f-card-sh` | `none` | `:root` | 1 |
| `--f-dark` | `#111111` | `:root` | 1 |
| `--f-density` | `1` | `:root` | – |
| `--f-focus` | `var(--f-a)` | `:root` | – |
| `--f-font` | `Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif` | `:root` | – |
| `--f-font-head` | `var(--f-font)` | `:root` | – |
| `--f-font-label` | `var(--f-font)` | `:root` | – |
| `--f-font-mono` | `ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace` | `:root` | – |
| `--f-fs-max` | `18` | `:root` | – |
| `--f-fs-min` | `16` | `:root` | – |
| `--f-gutter` | `clamp(1rem,.45rem + 2.6cqi,3rem)` | `:root` | – |
| `--f-hdr-bg` | `color-mix(in srgb,var(--f-bg) 86%,transparent)` | `:root` | – |
| `--f-hw` | `500` | `:root` | – |
| `--f-ico` | `var(--f-a)` | `:root` | – |
| `--f-ico-bg` | `var(--f-a-soft)` | `:root` | – |
| `--f-ink` | `#0A0A0A` | `:root` | 1 |
| `--f-line` | `#E4E4E0` | `:root` | 1 |
| `--f-line-strong` | `color-mix(in srgb,var(--f-ink) 26%,var(--f-bg))` | `:root` | – |
| `--f-link` | `var(--f-a)` | `:root` | – |
| `--f-muted` | `#5C5C59` | `:root` | 1 |
| `--f-on-dark` | `#FFFFFF` | `:root` | – |
| `--f-p` | `calc((100vw - var(--f-vw-min)*1px)/(var(--f-vw-max) - var(--f-vw-min)))` | `:root` | – |
| `--f-pad` | `calc(var(--f-s-l)*var(--f-density))` | `:root` | – |
| `--f-r-l` | `calc(var(--f-radius)*1.4)` | `:root` | – |
| `--f-r-s` | `calc(var(--f-radius)*.6)` | `:root` | – |
| `--f-radius` | `0px` | `:root` | – |
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
| `--f-sh1` | `rgb(8 10 20/.07)` | `:root` | 2 |
| `--f-sh2` | `rgb(8 10 20/.22)` | `:root` | 2 |
| `--f-shadow` | `none` | `:root` | – |
| `--f-space` | `1.32` | `:root` | – |
| `--f-surface` | `#F4F4F2` | `:root` | 1 |
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
| `--f-text` | `#262626` | `:root` | 1 |
| `--f-track` | `-2.5` | `:root` | – |
| `--f-vw-max` | `1440` | `:root` | – |
| `--f-vw-min` | `360` | `:root` | – |
| `--f-wrap` | `90rem` | `:root` | – |
| `--f0-ink` | `var(--f-ink)` | `:root` | – |
| `--f0-link` | `var(--f-link)` | `:root` | – |
| `--f0-muted` | `var(--f-muted)` | `:root` | – |
| `--f0-text` | `var(--f-text)` | `:root` | – |
| `--g-avail` | `#1A7F37` | `:root` | 1 |
| `--g-density` | `1` | `:root` | – |
| `--g-sold` | `#C62828` | `:root` | 1 |

**css/b-artworks.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--h` | `clamp(15rem,36cqi,30rem)` | `.aws--salon .aw--s0` | 4 |

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

**css/b-features.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `var(--f-s-2xl) var(--f-s-xl)` | `.feats--icons` | 1 |
| `--ico-2-opacity` | `.35` | `.feats--icons .feat__ico` | – |

**css/b-gallery.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-mat` | `#EDEBE6` | `.af-mat.is-darkbase .art img` | – |

**css/b-media-text.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-card` | `var(--f-surface)` | `.mt-over__card` | – |

**css/b-quote.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ico-2-opacity` | `.35` | `.pull__mark` | – |
| `--min` | `22rem` | `.quotes--reel` | – |

**css/b-steps.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--min` | `14rem` | `.steps--numbers` | – |

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

**css/data.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cols` | `3` | `.dl--cards .dl-items` | 2 |
| `--dl-gap` | `var(--f-s-l)` | `.dl,.df` | – |
| `--min` | `22rem` | `.dl--cards .dl-cols-2` | 2 |

**css/dataform.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dff-gap` | `var(--f-s-m)` | `.dff-wrap` | – |
| `--f-danger` | `color-mix(in srgb,#E5484D 66%,var(--f-ink))` | `.dff-wrap` | – |

**css/hero-x.css** (13)

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

**css/opt-cards-glass.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-card` | `color-mix(in srgb,var(--f-bg) 62%,transparent)` | `.cards-glass` | – |
| `--f-card-bd` | `color-mix(in srgb,var(--f-ink) 14%,transparent)` | `.cards-glass` | – |
| `--f-card-blur` | `blur(16px) saturate(1.4)` | `.cards-glass` | – |
| `--f-card-sh` | `var(--f-shadow)` | `.cards-glass` | – |

**css/opt-dividers-curve.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--div-h` | `clamp(1.25rem,.6rem + 3vw,3.25rem)` | `.div-curve :is(main,.page__main) :is(.sec.bg-white+.sec:not(.bg-white,.pt-none),.sec.bg-muted+.sec:not(.bg-muted,.pt-none),.sec.bg-tint+.sec:not(.bg-tint,.pt-none),.sec.bg-accent+.sec:not(.bg-accent,.pt-none),.sec.bg-secondary+.sec:not(.bg-secondary,.pt-none),.sec.bg-dark+.sec:not(.bg-dark,.pt-none),.sec+.sec.sec--has-bg:not(.pt-none))` | – |

**css/opt-dividers-slant.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--div-h` | `clamp(1.25rem,.6rem + 3vw,3.25rem)` | `.div-slant :is(main,.page__main) :is(.sec.bg-white+.sec:not(.bg-white,.pt-none),.sec.bg-muted+.sec:not(.bg-muted,.pt-none),.sec.bg-tint+.sec:not(.bg-tint,.pt-none),.sec.bg-accent+.sec:not(.bg-accent,.pt-none),.sec.bg-secondary+.sec:not(.bg-secondary,.pt-none),.sec.bg-dark+.sec:not(.bg-dark,.pt-none),.sec+.sec.sec--has-bg:not(.pt-none))` | – |

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

**css/pages.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--stack` | `var(--f-s-m)` | `.err__text` | – |

**css/preview.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-sh1` | `rgb(0 0 0/.3)` | `html.is-dark` | – |
| `--f-sh2` | `rgb(0 0 0/.55)` | `html.is-dark` | – |

**css/prose.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--rt-accent` | `var(--f-link)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--rt-danger` | `#B42318` | `:root` | 6 |
| `--rt-muted` | `var(--f-muted)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-secondary,.bg-dark,.on-media,.sec--ov-dark` | – |
| `--rt-success` | `#1A7240` | `:root` | 6 |
| `--rt-warning` | `#8A4B00` | `:root` | 6 |

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
| `css/_header.css` | f-hdr | ja | – ⚠ | – | – |
| `css/_options.css` | – | ja | ja | ja | – |
| `css/b-artists.css` | – | ja | – | – | ja |
| `css/b-cards.css` | – | ja | – | – | – |
| `css/b-faq.css` | f-drop | ja | ja | – | – |
| `css/b-gallery.css` | – | ja | – | ja | ja |
| `css/b-logos.css` | f-marquee | ja | ja | – | – |
| `css/b-visit.css` | – | – | – | ja | – |
| `css/calendar.css` | – | – | – | ja | ja |
| `css/data.css` | – | ja | – | – | ja |
| `css/dataform.css` | – | ja | – | – | ja |
| `css/gallery-edit.css` | – | ja | – | – | – |
| `css/hero-x.css` | f-float | ja | ja | – | – |
| `css/media.css` | cms-lb-in, cms-stack | ja | ja | – | ja |
| `css/opt-buttons-sharp.css` | – | ja | – | – | – |
| `css/overlay.css` | f-sheet | ja | – ⚠ | – | – |
| `css/search.css` | – | – | – | ja | ja |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/gallery-edit.js` | – | – | – | – | 12,5 KB (Quelle) |
| `js/gallery.js` | – | – | – | – | 1,2 KB (Quelle) |
| `js/reel.js` | ja | – | ja | – | 1,3 KB (Quelle) |
| `js/site.js` | ja | ja | ja | – | 7,5 KB (Quelle) |
| `js/tabs.js` | – | – | – | – | 1,3 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

_Keine Namensraum-Ereignisse._

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-active` | functions.php |
| `data-artist` | blocks/artworks.php, assets/js/gallery.js |
| `data-avail` | blocks/artworks.php, assets/js/gallery.js |
| `data-aw-count` | blocks/artworks.php, assets/js/gallery.js |
| `data-aw-filter` | blocks/artworks.php, assets/js/gallery.js |
| `data-bg-video` | blocks/hero.php, assets/js/site.js |
| `data-bg-video-toggle` | blocks/hero.php, assets/js/site.js |
| `data-cms-lightbox` | blocks/artwork_detail.php, functions.php |
| `data-design-preview` | templates/layout.php |
| `data-entry-edit` | assets/js/gallery-edit.js |
| `data-f` | assets/js/gallery.js |
| `data-gmm` | assets/js/gallery-edit.js, functions.php |
| `data-gmm-choose` | assets/js/gallery-edit.js, functions.php |
| `data-gmm-down` | assets/js/gallery-edit.js |
| `data-gmm-drop` | functions.php |
| `data-gmm-library` | assets/js/gallery-edit.js, functions.php |
| `data-gmm-list` | assets/js/gallery-edit.js, functions.php |
| `data-gmm-queue` | assets/js/gallery-edit.js, functions.php |
| `data-gmm-refresh` | assets/js/gallery-edit.js, functions.php |
| `data-gmm-reload` | assets/js/gallery-edit.js, functions.php |
| `data-gmm-remove` | assets/js/gallery-edit.js |
| `data-gmm-status` | assets/js/gallery-edit.js, functions.php |
| `data-gmm-up` | assets/js/gallery-edit.js |
| `data-gnew` | assets/js/gallery-edit.js, functions.php |
| `data-gnew-choose` | assets/js/gallery-edit.js, functions.php |
| `data-gnew-queue` | assets/js/gallery-edit.js, functions.php |
| `data-gnew-status` | assets/js/gallery-edit.js, functions.php |
| `data-header` | templates/partials/header.php, assets/js/site.js |
| `data-id` | assets/js/gallery-edit.js |
| `data-kind` | assets/js/gallery-edit.js |
| `data-label-pause` | blocks/hero.php, assets/js/site.js |
| `data-label-play` | blocks/hero.php, assets/js/site.js |
| `data-many` | blocks/artworks.php, assets/js/gallery.js |
| `data-mnav` | templates/partials/sheet.php, assets/js/site.js |
| `data-one` | blocks/artworks.php, assets/js/gallery.js |
| `data-reel-ctrl` | blocks/cards.php, blocks/quote.php, assets/js/reel.js |
| `data-reel-next` | blocks/cards.php, blocks/quote.php, assets/js/reel.js |
| `data-reel-prev` | blocks/cards.php, blocks/quote.php, assets/js/reel.js |
| `data-reveal` | blocks/artists_index.php, blocks/cards.php, blocks/exhibitions_list.php, blocks/features.php, blocks/quote.php … |
| `data-scope` | templates/layout.php |
| `data-sw` | templates/layout.php |
| `data-tabs` | blocks/exhibitions_list.php, blocks/tabs.php, assets/js/tabs.js |
| `data-v` | assets/js/gallery.js |

<!-- docs:assets:end -->
