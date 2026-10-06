# CSS & JS – Kit „Foto (Portfolio)“ (foto)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=foto --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden: `css/site.css` (mit `_photo.css` für Bilder & Galerien), `css/overlay.css` (am Ende von `<body>`),
  `css/opt-*.css` (nur gewählte Design-Optionen), `css/editing.css` (Bearbeiten-Modus), `css/preview.css` (Style-Editor), `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`): `b-photo-grid.css`, `b-photo-hero.css`, `b-series.css`,
  `b-photo-text.css`, `lightbox.css` + `js/lightbox.js`, `js/photo-hero.js`, `js/series.js` (nur „Große Liste“), `js/reel.js` (Band).
- Nur im Seiten-Editor (`theme.php → editor_js`): `js/editor-photos.js` + `css/editor-photos.css` (im Upload-Dialog verlinkt).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/foto/`).

## 2. Tokens & Farben

- Kit-Variablen `--f-*` an `:root` (Standard = Vorlage „Weiß & still“), neu: `--f-gap-img` (Bildabstand), `--f-img-r` (Ecken der Bilder),
  `--f-mat-bg` (Passepartout, im dunklen Schema gedämpft).
- Klassen am `<html>` für Bilder: `gap-*`, `img-*`, `cap-*`, `capst-*`, `lb-*`, `gs-*`, `mat-*`, `gw-*`, `ih-*`, `logo-*`.

## 3. Blöcke → Dateien

- Siehe Anhang. `lightbox.css` styled auch die Lightbox der Kern-Galerie (`.cms-lb`) nach `lb-light`/`lb-blur`.

## 4. Animationen & Regeln

- Überblenden der Bühne, Einblenden, Hinweis „weiter nach unten“, Lightbox-Einblendung: nur mit `has-motion` und ohne
  `prefers-reduced-motion`. Videos der Bühne starten nie bei „Bewegung reduzieren“; Pause-Schaltfläche (WCAG 2.2.2).
- `@media (hover:hover)` nur für „Bildunterschrift beim Zeigen“ und die schwebende Vorschau der Serienliste.

## 5. Overlays, Sheets, Dialoge

- Lightbox `dialog.flb` (showModal: Fokusfalle und Escape nativ, Fokus zurück zum Bild), Scroll-Sperre per `html:has(.flb[open])`.
- Upload-Dialog im Editor: `#foto-upload` in der Shadow-DOM-Ebene der Verwaltung (`CMSMedia.ui.inBox`).

## 6. JavaScript & Ereignisse

- `lightbox.js`: Klick auf `a[data-lb]` (Gruppe `[data-lb-group]`), Videos über `data-lb-video` → `<template>`.
- `photo-hero.js`: `[data-stage]`, `[data-stage-video]`, Steuerung `[data-stage-ctrl]`.
- `series.js`: `[data-sx-list]` + `[data-sx-peek]` (Position per `el.style.transform`).
- `editor-photos.js`: hört auf `cms:block-preview`, nutzt `window.CMSMedia.Uploader` und – falls vorhanden – `window.CMSEditor.block(node)`
  (vorgeschlagene Kern-Schnittstelle: `get(path)`, `set({pfad: wert})` → markDirty, Seitenleiste, Vorschau).

## 7. Sonderfälle

- Ohne `CMSEditor.block` lädt die Ablagefläche trotzdem hoch und bittet, die Dateien in der Seitenleiste zu wählen.
- Kern-Upload akzeptiert Videos nur als MP4; andere Formate lehnt die Ablagefläche mit Meldung ab.

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=foto --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Foto“ (foto 0.1.0)

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
| `css/b-photo-grid.css` | photo_grid |
| `css/b-photo-hero.css` | photo_hero |
| `css/b-series.css` | series_index, series_head |
| `css/b-photo-text.css` | photo_text |
| `css/lightbox.css` | photo_grid, photo_text, series_head |
| `js/lightbox.js` | photo_grid, photo_text, series_head |
| `js/photo-hero.js` | photo_hero |
| `js/series.js` | series_index:list |
| `js/reel.js` | quote, cards, photo_grid:strip |
| `css/hero-x.css` | hero:fullbleed, hero:cards, hero:type |
| `css/hx-scale.css` | hero:scale |
| `css/hx-collage.css` | hero:collage |
| `css/hx-compare.css` | hero:compare |
| `css/prose.css` | richtext, faq, media_text, photo_text, series_head, @rich |
| `css/b-article.css` | richtext:article, richtext:columns |
| `css/b-features.css` | features |
| `css/b-media-text.css` | media_text |
| `css/b-cta.css` | cta |
| `css/reel.css` | cards:reel, quote:reel, photo_grid:strip |
| `css/b-cards.css` | cards |
| `css/b-logos.css` | logos |
| `css/b-quote.css` | quote |
| `css/b-steps.css` | steps |
| `css/b-pricing.css` | pricing |
| `css/b-faq.css` | faq |
| `css/b-scrolly.css` | scrolly |
| `css/b-contact.css` | contact, map |
| `css/b-video.css` | video |
| `css/b-downloads.css` | downloads |
| `css/dataform.css` | contact |
| `js/scrolly.js` | scrolly |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `cards` Karten | `blocks/cards.php` | image, overlay, horizontal, reel | `js/reel.js`, `css/reel.css` (reel), `css/b-cards.css` |
| `contact` Kontakt (Karte, Formular, Zeiten) | `blocks/contact.php` | – | `css/b-contact.css`, `css/dataform.css` |
| `cta` Handlungsaufruf | `blocks/cta.php` | band, box, split, big | `css/b-cta.css` |
| `downloads` Downloads | `blocks/downloads.php` | – | `css/b-downloads.css` |
| `faq` Fragen & Antworten | `blocks/faq.php` | split, stacked | `css/prose.css`, `css/b-faq.css` |
| `features` Leistungen | `blocks/features.php` | cards, icons, list, numbered | `css/b-features.css` |
| `hero` Einstieg (Hero) | `blocks/hero.php` | split, centered, fullbleed, cards, type, compact, scale, collage, compare | `css/hero-x.css` (fullbleed, cards, type), `css/hx-scale.css` (scale), `css/hx-collage.css` (collage), `css/hx-compare.css` (compare) |
| `logos` Logos (Kunden, Publikationen) | `blocks/logos.php` | grid, marquee | `css/b-logos.css` |
| `map` Karte | `blocks/map.php` | – | `css/b-contact.css` |
| `media_text` Text + Bild | `blocks/media_text.php` | auto, right, left, split, overlap | `css/prose.css`, `css/b-media-text.css` |
| `photo_grid` Fotostrecke | `blocks/photo_grid.php` | masonry, justified, grid, single, strip | `css/b-photo-grid.css`, `css/lightbox.css`, `js/lightbox.js`, `js/reel.js` (strip), `css/reel.css` (strip) |
| `photo_hero` Bühne (Vollbild) | `blocks/photo_hero.php` | single, slideshow | `css/b-photo-hero.css`, `js/photo-hero.js` |
| `photo_text` Bild & Text | `blocks/photo_text.php` | left, right | `css/b-photo-text.css`, `css/lightbox.css`, `js/lightbox.js`, `css/prose.css` |
| `pricing` Pakete / Preise | `blocks/pricing.php` | cards, table | `css/b-pricing.css` |
| `quote` Zitat / Stimmen | `blocks/quote.php` | single, grid, reel | `js/reel.js`, `css/reel.css` (reel), `css/b-quote.css` |
| `richtext` Fließtext / Artikel | `blocks/richtext.php` | standard, article, columns | `css/prose.css`, `css/b-article.css` (article, columns) |
| `scrolly` Scrollytelling | `blocks/scrolly.php` | left, right | `css/b-scrolly.css`, `js/scrolly.js` |
| `series_head` Serie (Kopf) | `blocks/series_head.php` | text, cover, side | `css/b-series.css`, `css/lightbox.css`, `js/lightbox.js`, `css/prose.css` |
| `series_index` Serien-Übersicht | `blocks/series_index.php` | grid, list, rows | `css/b-series.css`, `js/series.js` (list) |
| `steps` Ablauf / Zeitleiste | `blocks/steps.php` | numbers, timeline, process | `css/b-steps.css` |
| `video` Video | `blocks/video.php` | wide, text, cinema | `css/b-video.css` |

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

**css/_photo.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-gw` | `calc(var(--f-wrap) + 22rem)` | `.gwrap--wide` | – |
| `--f-mat-bg` | `color-mix(in srgb,var(--f-bg) 78%,#FFF)` | `html.has-dark` | – |
| `--f-mat-in` | `calc(.35rem + 1px)` | `.mat-line .ph` | 1 |

**css/_tokens.css** (100)

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
| `--f-a` | `#1A1A1A` | `:root` | 1 |
| `--f-a-on` | `#FFFFFF` | `:root` | 1 |
| `--f-a-soft` | `color-mix(in srgb,var(--f-a) 14%,var(--f-bg))` | `:root` | – |
| `--f-a-strong` | `#000000` | `:root` | 1 |
| `--f-a2` | `#EDEDEA` | `:root` | 1 |
| `--f-b` | `#6B6B6B` | `:root` | 1 |
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
| `--f-dark` | `#141414` | `:root` | 1 |
| `--f-density` | `1` | `:root` | – |
| `--f-focus` | `var(--f-a)` | `:root` | – |
| `--f-font` | `Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif` | `:root` | – |
| `--f-font-head` | `var(--f-font)` | `:root` | – |
| `--f-font-label` | `var(--f-font)` | `:root` | – |
| `--f-font-mono` | `ui-monospace,SFMono-Regular,Menlo,Consolas,"Liberation Mono",monospace` | `:root` | – |
| `--f-fs-max` | `18` | `:root` | – |
| `--f-fs-min` | `16` | `:root` | – |
| `--f-gap-img` | `clamp(.5rem,.25rem + 1vw,1.25rem)` | `:root` | – |
| `--f-gutter` | `clamp(1rem,.45rem + 2.6cqi,3rem)` | `:root` | – |
| `--f-hdr-bg` | `color-mix(in srgb,var(--f-bg) 86%,transparent)` | `:root` | – |
| `--f-hw` | `450` | `:root` | – |
| `--f-ico` | `var(--f-a)` | `:root` | – |
| `--f-ico-bg` | `var(--f-a-soft)` | `:root` | – |
| `--f-img-r` | `0px` | `:root` | – |
| `--f-ink` | `#111111` | `:root` | 1 |
| `--f-line` | `#E6E6E3` | `:root` | 1 |
| `--f-line-strong` | `color-mix(in srgb,var(--f-ink) 26%,var(--f-bg))` | `:root` | – |
| `--f-link` | `var(--f-a)` | `:root` | – |
| `--f-muted` | `#5E5E5E` | `:root` | 1 |
| `--f-on-dark` | `#FFFFFF` | `:root` | – |
| `--f-p` | `calc((100vw - var(--f-vw-min)*1px)/(var(--f-vw-max) - var(--f-vw-min)))` | `:root` | – |
| `--f-pad` | `calc(var(--f-s-l)*var(--f-density))` | `:root` | – |
| `--f-r-l` | `calc(var(--f-radius)*1.4)` | `:root` | – |
| `--f-r-s` | `calc(var(--f-radius)*.6)` | `:root` | – |
| `--f-radius` | `0px` | `:root` | – |
| `--f-ratio` | `1.2` | `:root` | – |
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
| `--f-surface` | `#F5F5F3` | `:root` | 1 |
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
| `--f-text` | `#2E2E2E` | `:root` | 1 |
| `--f-track` | `-1.5` | `:root` | – |
| `--f-vw-max` | `1440` | `:root` | – |
| `--f-vw-min` | `360` | `:root` | – |
| `--f-wrap` | `80rem` | `:root` | – |
| `--f0-ink` | `var(--f-ink)` | `:root` | – |
| `--f0-link` | `var(--f-link)` | `:root` | – |
| `--f0-muted` | `var(--f-muted)` | `:root` | – |
| `--f0-text` | `var(--f-text)` | `:root` | – |

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

**css/b-media-text.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-card` | `var(--f-surface)` | `.mt-over__card` | – |

**css/b-photo-grid.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ar` | `5` | `.ar-5` | 19 |
| `--min` | `16rem` | `.pg--grid.cols-2` | 2 |
| `--n` | `2` | `.pg--grid.cols-2` | 2 |
| `--rh` | `clamp(8rem,20cqi,13rem)` | `.pg--justified.rh-s,.pg--strip.rh-s` | 2 |
| `--sh` | `clamp(11rem,38svh,18rem)` | `.pg--justified.rh-s,.pg--strip.rh-s` | 2 |

**css/b-photo-hero.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ov` | `.7` | `.stage--ov-medium` | 1 |
| `--ov2` | `.66` | `.stage--ov-medium` | 1 |
| `--pad` | `clamp(1.25rem,.6rem + 3cqi,3.5rem)` | `.stage` | – |

**css/b-pricing.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--min` | `16rem` | `.plans` | – |

**css/b-quote.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ico-2-opacity` | `.35` | `.pull__mark` | – |
| `--min` | `22rem` | `.quotes--reel` | – |

**css/b-series.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--min` | `17rem` | `.sx-grid.cols-2` | 2 |
| `--n` | `2` | `.sx-grid.cols-2` | 2 |

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

**css/hx-compare.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--hx-ar` | `16/9` | `.hx-ar-16-9` | 5 |

**css/lightbox.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--lb-bg` | `#0A0A0A` | `.flb` | 2 |
| `--lb-fg` | `#F2F2F2` | `.flb` | 2 |
| `--lb-muted` | `#C2C2C2` | `.flb` | 2 |

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
| `--f-a-soft` | `color-mix(in srgb,var(--f-fg) 12%,transparent)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-btn-bg` | `var(--f-fg)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-btn-hover` | `color-mix(in srgb,var(--f-fg) 86%,var(--f-dark))` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-btn-ink` | `var(--f-dark)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-fg` | `var(--f-on-dark)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-focus` | `var(--f-fg)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-ink` | `var(--f-fg)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-line` | `color-mix(in srgb,var(--f-fg) 18%,transparent)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-line-strong` | `color-mix(in srgb,var(--f-fg) 45%,transparent)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-link` | `var(--f-fg)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-muted` | `color-mix(in srgb,var(--f-fg) 80%,var(--f-dark))` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-surface` | `color-mix(in srgb,var(--f-fg) 8%,transparent)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-surface-2` | `color-mix(in srgb,var(--f-fg) 12%,transparent)` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |
| `--f-text` | `color-mix(in srgb,var(--f-fg) 88%,var(--f-dark))` | `html:not(.fbg-page,.fbg-surface) .ftr--statement` | – |

**css/pages.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--stack` | `var(--f-s-m)` | `.err__text` | – |

**css/preview.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--f-mat-bg` | `color-mix(in srgb,var(--f-bg) 78%,#FFF)` | `html.is-dark` | – |
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
| `css/_photo.css` | – | ja | ja | ja | ja |
| `css/b-cards.css` | – | ja | – | – | – |
| `css/b-faq.css` | f-drop | ja | ja | – | – |
| `css/b-logos.css` | f-marquee | ja | ja | – | – |
| `css/b-photo-hero.css` | f-hint | ja | ja | ja | ja |
| `css/b-scrolly.css` | – | ja | – | – | – |
| `css/b-series.css` | – | ja | ja | – | ja |
| `css/calendar.css` | – | – | – | ja | ja |
| `css/data.css` | – | ja | – | – | ja |
| `css/dataform.css` | – | ja | – | – | ja |
| `css/editing.css` | – | ja | – | – | ja |
| `css/hero-x.css` | f-float | ja | ja | – | – |
| `css/hx-compare.css` | – | – | – | ja | ja |
| `css/lightbox.css` | f-lb | ja | ja | ja | ja |
| `css/media.css` | cms-lb-in, cms-stack | ja | ja | – | ja |
| `css/opt-buttons-sharp.css` | – | ja | – | – | – |
| `css/overlay.css` | f-sheet | ja | – ⚠ | – | – |
| `css/search.css` | – | – | – | ja | ja |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/editor-photos.js` | – | – | – | – | 14,1 KB (Quelle) |
| `js/lightbox.js` | – | – | – | – | 3,9 KB (Quelle) |
| `js/photo-hero.js` | ja | – | – | – | 3,9 KB (Quelle) |
| `js/reel.js` | ja | – | ja | – | 1,3 KB (Quelle) |
| `js/scrolly.js` | – | ja | – | – | 1,0 KB (Quelle) |
| `js/series.js` | ja | – | ja | – | 2,3 KB (Quelle) |
| `js/site.js` | ja | ja | ja | – | 7,6 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

| Ereignis | Payload | gesendet in | empfangen in |
|---|---|---|---|
| `cms:block-preview` | – |  | js/editor-photos.js:155 |

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-accept` | assets/js/editor-photos.js, functions.php |
| `data-active` | functions.php |
| `data-allow-collection` | assets/js/editor-photos.js, functions.php |
| `data-as-col` | assets/js/editor-photos.js |
| `data-autoplay` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-bg-video` | blocks/hero.php, assets/js/site.js |
| `data-bg-video-toggle` | blocks/hero.php, assets/js/site.js |
| `data-caption` | assets/js/lightbox.js, functions.php |
| `data-close` | assets/js/editor-photos.js |
| `data-col-name` | assets/js/editor-photos.js |
| `data-collection` | assets/js/editor-photos.js, functions.php |
| `data-count` | blocks/photo_hero.php, assets/js/lightbox.js, assets/js/photo-hero.js, functions.php |
| `data-design-preview` | templates/layout.php |
| `data-field` | assets/js/editor-photos.js, functions.php |
| `data-flb-close` | assets/js/lightbox.js, functions.php |
| `data-flb-step` | assets/js/lightbox.js, functions.php |
| `data-foto-act` | assets/js/editor-photos.js, functions.php |
| `data-foto-choose` | assets/js/editor-photos.js, functions.php |
| `data-foto-drop` | assets/js/editor-photos.js, functions.php |
| `data-foto-item` | assets/js/editor-photos.js, functions.php |
| `data-h` | assets/js/lightbox.js |
| `data-header` | templates/partials/header.php, assets/js/site.js |
| `data-label-pause` | blocks/hero.php, blocks/photo_hero.php, assets/js/photo-hero.js, assets/js/site.js |
| `data-label-play` | blocks/hero.php, blocks/photo_hero.php, assets/js/photo-hero.js, assets/js/site.js |
| `data-lb` | assets/js/lightbox.js, functions.php |
| `data-lb-group` | blocks/photo_grid.php, blocks/photo_text.php, assets/js/lightbox.js |
| `data-lb-video` | assets/js/lightbox.js, functions.php |
| `data-mnav` | templates/partials/sheet.php, assets/js/site.js |
| `data-mode` | assets/js/editor-photos.js, functions.php |
| `data-more` | assets/js/editor-photos.js |
| `data-mu-start` | assets/js/editor-photos.js |
| `data-reel-ctrl` | blocks/cards.php, blocks/photo_grid.php, blocks/quote.php, assets/js/reel.js |
| `data-reel-next` | blocks/cards.php, blocks/photo_grid.php, blocks/quote.php, assets/js/reel.js |
| `data-reel-prev` | blocks/cards.php, blocks/photo_grid.php, blocks/quote.php, assets/js/reel.js |
| `data-reveal` | blocks/cards.php, blocks/features.php, blocks/photo_grid.php, blocks/pricing.php, blocks/quote.php … |
| `data-scope` | templates/layout.php |
| `data-scrolly` | blocks/scrolly.php, assets/js/scrolly.js |
| `data-slide` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-srcset` | assets/js/lightbox.js |
| `data-stage` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-stage-count` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-stage-ctrl` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-stage-label` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-stage-next` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-stage-prev` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-stage-toggle` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-stage-video` | blocks/photo_hero.php, assets/js/photo-hero.js |
| `data-step` | blocks/scrolly.php, assets/js/scrolly.js |
| `data-step-img` | blocks/scrolly.php, assets/js/scrolly.js |
| `data-sw` | templates/layout.php |
| `data-switch-field` | assets/js/editor-photos.js, functions.php |
| `data-switch-variant` | assets/js/editor-photos.js, functions.php |
| `data-sx-list` | blocks/series_index.php, assets/js/series.js |
| `data-sx-peek` | blocks/series_index.php, assets/js/series.js |
| `data-sx-ready` | assets/js/series.js |
| `data-up-status` | assets/js/editor-photos.js |
| `data-w` | assets/js/lightbox.js |

<!-- docs:assets:end -->
