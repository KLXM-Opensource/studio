# CSS & JS – Kit „Essenz – weniger, aber besser“ (essenz)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=essenz --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden (Layout, Wartung, offline): `css/editing.css`, `css/overlay.css`, `css/pages.css`, `css/preview.css`, `css/site.css`, `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, 16 Einträge).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/essenz/`).

## 2. Tokens & Farben

- Kit-Variablen: `--e-*` an `:root`.
- Gesetzte Kern-Variablen: `--cal-accent`, `--cal-accent-ink`, `--cal-ev-bg`, `--cal-line`, `--cal-muted`, `--cal-radius`, `--cal-surface`, `--cal-today-bg`, `--cms-map-accent`, `--cms-map-bg`, `--cms-map-line`, `--cms-map-radius`, `--dff-gap`, `--dial-surface`, `--dial-track`, `--dl-gap`, `--ha-accent`, `--ha-bg`, `--ha-gap`, `--ha-h`, `--ha-ink`, `--ha-line`, `--ha-muted`, `--ha-on` ….

## 3. Blöcke → Dateien

- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.

## 4. Animationen & Regeln

- Dateien mit Keyframes/Scroll-Animation: `css/_header.css`, `css/_hero.css`, `css/hero-x.css`, `css/media.css`, `css/overlay.css`.
- `prefers-reduced-motion` in `css/site.css`: nein · `forced-colors`: nein · `:focus-visible`: nein.
- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.

## 5. Overlays, Sheets, Dialoge

- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).

## 6. JavaScript & Ereignisse

- Skripte: `js/site.js`.
- Ereignisse: keine eigenen.

## 7. Sonderfälle

- _keine bekannt_

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=essenz --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Essenz – weniger, aber besser“ (essenz 1.0.0)

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
| `css/b-prose.css` | richtext, faq, media_text, video, @rich |
| `css/b-media-text.css` | media_text |
| `css/b-features.css` | features |
| `css/b-principles.css` | principles |
| `css/b-specs.css` | specs |
| `css/b-stats.css` | stats |
| `css/b-steps.css` | steps |
| `css/b-cards.css` | cards |
| `css/b-quote.css` | quote |
| `css/b-faq.css` | faq |
| `css/b-cta.css` | cta |
| `css/b-contact.css` | contact, map |
| `css/b-downloads.css` | downloads |
| `css/b-video.css` | video |
| `css/dataform.css` | contact |
| `css/hero-x.css` | hero:console, hero:dials, hero:monitor |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `cards` Produkte / Leistungen (Karten) | `blocks/cards.php` | product, service, image | `css/b-cards.css` |
| `contact` Kontakt (Zeiten, Karte, Formular) | `blocks/contact.php` | – | `css/b-contact.css`, `css/dataform.css` |
| `cta` Handlungsaufruf | `blocks/cta.php` | panel, band, minimal | `css/b-cta.css` |
| `downloads` Downloads | `blocks/downloads.php` | – | `css/b-downloads.css` |
| `faq` Fragen & Antworten | `blocks/faq.php` | split, stacked | `css/b-prose.css`, `css/b-faq.css` |
| `features` Merkmale / Bedienfeld | `blocks/features.php` | panel, cards, list | `css/b-features.css` |
| `hero` Einstieg (Hero) | `blocks/hero.php` | panel, statement, split, compact, console, dials, monitor | `css/hero-x.css` (console, dials, monitor) |
| `map` Karte | `blocks/map.php` | – | `css/b-contact.css` |
| `media_text` Text + Bild | `blocks/media_text.php` | auto, right, left | `css/b-prose.css`, `css/b-media-text.css` |
| `principles` Grundsätze / Thesen | `blocks/principles.php` | list, grid, accordion | `css/b-principles.css` |
| `quote` Zitat / Stimmen | `blocks/quote.php` | single, grid | `css/b-quote.css` |
| `richtext` Fließtext / Artikel | `blocks/richtext.php` | standard, article | `css/b-prose.css` |
| `specs` Datenblatt (technische Daten) | `blocks/specs.php` | table, image | `css/b-specs.css` |
| `stats` Kennzahlen | `blocks/stats.php` | dials, row | `css/b-stats.css` |
| `steps` Ablauf / Zeitleiste | `blocks/steps.php` | steps, timeline | `css/b-steps.css` |
| `video` Video | `blocks/video.php` | wide, text | `css/b-prose.css`, `css/b-video.css` |

### Variablen je Stylesheet

**css/_base.css** (26)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--e-a-soft` | `color-mix(in srgb,var(--e-fg) 12%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | 1 |
| `--e-a2` | `color-mix(in srgb,var(--e-fg) 18%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-btn-bg` | `var(--e-a-on)` | `.bg-accent` | – |
| `--e-btn-ink` | `var(--e-a)` | `.bg-accent` | – |
| `--e-btn2-bg` | `transparent` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-btn2-ink` | `var(--e-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-card` | `color-mix(in srgb,var(--e-fg) 6%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | 1 |
| `--e-card-bd` | `var(--e-line)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-card-sh` | `none` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-fg` | `var(--e-a-on)` | `.bg-accent` | 2 |
| `--e-focus` | `var(--e-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-hi` | `rgb(255 255 255/.06)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-ink` | `var(--e-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-line` | `color-mix(in srgb,var(--e-fg) 18%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-line-strong` | `color-mix(in srgb,var(--e-fg) 50%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-link` | `var(--e-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-muted` | `var(--e-a-on)` | `.bg-accent` | 2 |
| `--e-sec-bg` | `var(--e-surface)` | `.bg-muted` | 3 |
| `--e-surface` | `color-mix(in srgb,var(--e-panel) 55%,transparent)` | `.bg-tint` | 2 |
| `--e-surface-2` | `color-mix(in srgb,var(--e-fg) 13%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-text` | `var(--e-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--e-ui` | `var(--e-a-on)` | `.bg-accent` | 1 |
| `--fx` | `0%` | `.fx0` | 9 |
| `--fy` | `0%` | `.fy0` | 9 |
| `--ico-2-opacity` | `.2` | `.ico` | – |
| `--min` | `13rem` | `.min-s` | 2 |

**css/_footer.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `.25rem 1rem` | `.ftr__social` | 1 |

**css/_knob.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ico-2-opacity` | `.25` | `.knob .ico` | – |

**css/_tokens.css** (84)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-a` | `var(--e-a-ink)` | `:root` | – |
| `--b-a-on` | `var(--e-a-on)` | `:root` | – |
| `--b-bg` | `var(--e-panel)` | `:root` | – |
| `--b-ink` | `var(--e-ink)` | `:root` | – |
| `--b-line` | `var(--e-line)` | `:root` | – |
| `--b-muted` | `var(--e-muted)` | `:root` | – |
| `--b-radius` | `var(--e-radius)` | `:root` | – |
| `--b-surface` | `var(--e-surface)` | `:root` | – |
| `--e-a` | `#D85B19` | `:root` | 1 |
| `--e-a-ink` | `#A3400A` | `:root` | 1 |
| `--e-a-on` | `#111111` | `:root` | 1 |
| `--e-a-soft` | `color-mix(in srgb,var(--e-a) 16%,var(--e-bg))` | `:root` | – |
| `--e-a2` | `color-mix(in srgb,var(--e-a) 26%,var(--e-panel))` | `:root` | – |
| `--e-bg` | `#F2F1ED` | `:root` | 1 |
| `--e-btn-bg` | `var(--e-a)` | `:root` | 1 |
| `--e-btn-hover` | `var(--e-a)` | `:root` | – |
| `--e-btn-ink` | `var(--e-a-on)` | `:root` | 1 |
| `--e-btn-r` | `min(var(--e-radius),.5rem)` | `:root` | 1 |
| `--e-btn2-bg` | `var(--e-panel)` | `:root` | – |
| `--e-btn2-ink` | `var(--e-ink)` | `:root` | – |
| `--e-card` | `var(--e-panel)` | `:root` | – |
| `--e-card-bd` | `var(--e-line)` | `:root` | – |
| `--e-card-sh` | `inset 0 1px 0 var(--e-hi),0 1px 2px var(--e-sh1),0 12px 28px -18px var(--e-sh2)` | `:root` | 2 |
| `--e-danger` | `#B42318` | `:root` | 1 |
| `--e-dark` | `#232427` | `:root` | 1 |
| `--e-focus` | `var(--e-a-ink)` | `:root` | – |
| `--e-font` | `Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif` | `:root` | – |
| `--e-font-head` | `var(--e-font)` | `:root` | – |
| `--e-font-label` | `var(--e-font-mono)` | `:root` | – |
| `--e-font-mono` | `"Geist Mono",ui-monospace,SFMono-Regular,Menlo,Consolas,monospace` | `:root` | – |
| `--e-fs-max` | `18` | `:root` | – |
| `--e-fs-min` | `16` | `:root` | – |
| `--e-gutter` | `clamp(1rem,.5rem + 2.4vw,3rem)` | `:root` | – |
| `--e-hdr-bg` | `color-mix(in srgb,var(--e-bg) 88%,transparent)` | `:root` | – |
| `--e-hi` | `rgb(255 255 255/.75)` | `:root` | 2 |
| `--e-hw` | `600` | `:root` | – |
| `--e-ink` | `#1B1B19` | `:root` | 1 |
| `--e-key-sh` | `inset 0 1px 0 rgb(255 255 255/.28),inset 0 -2px 0 rgb(0 0 0/.16),0 1px 2px var(--e-sh1)` | `:root` | 3 |
| `--e-label` | `max(.75rem,calc(var(--e-t0)*.72))` | `:root` | – |
| `--e-line` | `#D6D3CC` | `:root` | 1 |
| `--e-line-strong` | `color-mix(in srgb,var(--e-ink) 55%,var(--e-panel))` | `:root` | – |
| `--e-link` | `var(--e-a-ink)` | `:root` | – |
| `--e-muted` | `#5E5C57` | `:root` | 1 |
| `--e-on-dark` | `#F2F1EE` | `:root` | – |
| `--e-pad` | `var(--e-s-m)` | `:root` | – |
| `--e-panel` | `#FBFAF8` | `:root` | 1 |
| `--e-r` | `calc(1 + (var(--e-ratio) - 1)*.72)` | `:root` | 1 |
| `--e-r-l` | `var(--e-radius)` | `:root` | – |
| `--e-r-s` | `calc(var(--e-radius)*.5)` | `:root` | – |
| `--e-r-xl` | `calc(var(--e-radius)*1.5)` | `:root` | – |
| `--e-radius` | `12px` | `:root` | – |
| `--e-ratio` | `1.25` | `:root` | – |
| `--e-s-2xl` | `calc(var(--e-u)*8)` | `:root` | – |
| `--e-s-2xs` | `calc(var(--e-u)*.5)` | `:root` | – |
| `--e-s-3xl` | `calc(var(--e-u)*12)` | `:root` | – |
| `--e-s-l` | `calc(var(--e-u)*4)` | `:root` | – |
| `--e-s-m` | `calc(var(--e-u)*3)` | `:root` | – |
| `--e-s-s` | `calc(var(--e-u)*2)` | `:root` | – |
| `--e-s-xl` | `calc(var(--e-u)*6)` | `:root` | – |
| `--e-s-xs` | `var(--e-u)` | `:root` | – |
| `--e-sec` | `calc(clamp(var(--e-u)*8,var(--e-u)*4 + 6vw,var(--e-u)*16)*var(--e-space))` | `:root` | – |
| `--e-sh1` | `rgb(20 18 14/.06)` | `:root` | 2 |
| `--e-sh2` | `rgb(20 18 14/.16)` | `:root` | 2 |
| `--e-space` | `1` | `:root` | – |
| `--e-surface` | `#E7E5E0` | `:root` | 1 |
| `--e-surface-2` | `color-mix(in srgb,var(--e-ink) 6%,var(--e-surface))` | `:root` | – |
| `--e-t-1` | `calc(var(--e-t0)*.875)` | `:root` | – |
| `--e-t-2` | `calc(var(--e-t0)*.75)` | `:root` | – |
| `--e-t0` | `clamp(var(--e-fs-min)*.0625rem,var(--e-fs-min)*.0625rem + (var(--e-fs-max) - var(--e-fs-min))*(100vw - 360px)/920,var(--e-fs-max)*.0625rem)` | `:root` | – |
| `--e-t1` | `calc(var(--e-t0)*var(--e-r))` | `:root` | – |
| `--e-t2` | `calc(var(--e-t0)*pow(var(--e-r),2))` | `:root` | – |
| `--e-t3` | `calc(var(--e-t0)*pow(var(--e-r),3))` | `:root` | – |
| `--e-t4` | `calc(var(--e-t0)*pow(var(--e-r),4))` | `:root` | – |
| `--e-t5` | `calc(var(--e-t0)*pow(var(--e-r),5))` | `:root` | – |
| `--e-t6` | `calc(var(--e-t0)*pow(var(--e-r),6))` | `:root` | – |
| `--e-text` | `#393835` | `:root` | 1 |
| `--e-track` | `-2.5` | `:root` | – |
| `--e-u` | `.5rem` | `:root` | – |
| `--e-well` | `inset 0 1px 2px var(--e-sh2)` | `:root` | 1 |
| `--e-wrap` | `76rem` | `:root` | – |
| `--e0-ink` | `var(--e-ink)` | `:root` | – |
| `--e0-link` | `var(--e-link)` | `:root` | – |
| `--e0-muted` | `var(--e-muted)` | `:root` | – |
| `--e0-text` | `var(--e-text)` | `:root` | – |

**css/b-cards.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `var(--e-s-m)` | `.pcards` | – |

**css/b-contact.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-map-accent` | `var(--e-a)` | `.cms-map` | – |
| `--cms-map-bg` | `var(--e-surface-2)` | `.cms-map` | – |
| `--cms-map-line` | `var(--e-line)` | `.cms-map` | – |
| `--cms-map-radius` | `var(--e-r-l)` | `.cms-map` | – |

**css/b-prose.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--rt-accent` | `var(--e-link)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-dark,.sec--ov-dark` | 1 |
| `--rt-danger` | `#B42318` | `:root` | 4 |
| `--rt-muted` | `var(--e-muted)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--rt-success` | `#1A7240` | `:root` | 4 |
| `--rt-warning` | `#8A4B00` | `:root` | 4 |

**css/calendar.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cal-accent` | `var(--e-btn-bg)` | `.cal` | – |
| `--cal-accent-ink` | `var(--e-btn-ink)` | `.cal` | – |
| `--cal-ev-bg` | `var(--e-a-soft)` | `.cal` | – |
| `--cal-line` | `var(--e-line)` | `.cal` | – |
| `--cal-muted` | `var(--e-muted)` | `.cal` | – |
| `--cal-radius` | `var(--e-r-l)` | `.cal` | – |
| `--cal-surface` | `var(--e-surface)` | `.cal` | 1 |
| `--cal-today-bg` | `var(--e-a-soft)` | `.cal` | – |

**css/data.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cols` | `3` | `.dl--cards .dl-items` | 2 |
| `--dl-gap` | `var(--e-s-l)` | `.dl,.df` | – |
| `--min` | `22rem` | `.dl--cards .dl-cols-2` | 2 |

**css/dataform.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dff-gap` | `var(--e-s-m)` | `.dff-wrap` | – |
| `--e-danger` | `color-mix(in srgb,#E5484D 66%,var(--e-ink))` | `.dff-wrap` | – |

**css/header-actions-kit.css** (10)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-accent` | `var(--e-a)` | `.ha,.ha-below` | – |
| `--ha-bg` | `var(--e-panel,var(--e-bg))` | `.ha,.ha-below` | – |
| `--ha-gap` | `.5rem` | `.ha,.ha-below` | – |
| `--ha-h` | `2.75rem` | `.ha,.ha-below` | – |
| `--ha-ink` | `var(--e-ink)` | `.ha,.ha-below` | – |
| `--ha-line` | `var(--e-line-strong)` | `.ha,.ha-below` | – |
| `--ha-muted` | `var(--e-muted)` | `.ha,.ha-below` | – |
| `--ha-on` | `var(--e-a-on)` | `.ha,.ha-below` | – |
| `--ha-open` | `#2F9E44` | `.ha,.ha-below` | 1 |
| `--ha-surface` | `var(--e-surface)` | `.ha,.ha-below` | – |

**css/header-actions-late.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-panel-bg` | `var(--e-panel,var(--e-bg))` | `.ha-menu__panel` | – |
| `--ha-panel-shadow` | `inset 0 1px 0 rgb(255 255 255/.5),0 1px 0 var(--e-line),0 18px 40px -18px rgb(0 0 0/.35)` | `.ha-menu__panel` | – |

**css/hero-x.css** (13)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dial-surface` | `var(--e-panel)` | `.hz-dl__panel .cms-dials` | – |
| `--dial-track` | `var(--e-surface-2)` | `.hz-dl__panel .cms-dials` | – |
| `--e-btn2-bg` | `transparent` | `.hz-mon` | – |
| `--e-btn2-ink` | `var(--e-fg)` | `.hz-mon` | – |
| `--e-fg` | `var(--e-on-dark)` | `.hz-mon` | – |
| `--e-focus` | `var(--e-fg)` | `.hz-mon` | – |
| `--e-ink` | `var(--e-fg)` | `.hz-mon` | – |
| `--e-line` | `color-mix(in srgb,var(--e-fg) 16%,transparent)` | `.hz-mon` | – |
| `--e-line-strong` | `color-mix(in srgb,var(--e-fg) 50%,transparent)` | `.hz-mon` | – |
| `--e-link` | `var(--e-fg)` | `.hz-mon` | – |
| `--e-muted` | `color-mix(in srgb,var(--e-fg) 80%,var(--_bz))` | `.hz-mon` | 1 |
| `--e-text` | `var(--e-fg)` | `.hz-mon` | – |
| `--e-ui` | `var(--e-a)` | `.hz-mon` | – |

**css/media.css** (11)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ar` | `5` | `.ar-5` | 19 |
| `--e-btn-bg` | `var(--e-on-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--e-btn-hover` | `color-mix(in srgb,var(--e-on-dark) 86%,var(--e-dark))` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--e-btn-ink` | `var(--e-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--e-ink` | `var(--e0-ink)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--e-link` | `var(--e0-link)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--e-muted` | `var(--e0-muted)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--e-text` | `var(--e0-text)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--i` | `1` | `.cms-stack__card:nth-child(2)` | 6 |
| `--n` | `2` | `.n-2` | 6 |
| `--row` | `clamp(8rem,3rem + 16vw,16rem)` | `.cms-gallery--justified` | 3 |

**css/opt-footer-simple.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `.25rem var(--e-s-m)` | `.ftr__inline` | – |

**css/pages.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--stack` | `var(--e-s-m)` | `.err__text` | – |

**css/preview.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--e-danger` | `#FF8A80` | `html.is-dark` | – |
| `--e-hi` | `rgb(255 255 255/.05)` | `html.is-dark` | – |
| `--e-sh1` | `rgb(0 0 0/.3)` | `html.is-dark` | – |
| `--e-sh2` | `rgb(0 0 0/.55)` | `html.is-dark` | – |

**css/search.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--se-accent` | `var(--e-a-ink)` | `.srch,.sf,.ssug` | – |
| `--se-bg` | `var(--e-bg)` | `.srch,.sf,.ssug` | – |
| `--se-line` | `var(--e-line)` | `.srch,.sf,.ssug` | – |
| `--se-mark` | `color-mix(in srgb,var(--e-a2) 70%,transparent)` | `.srch,.sf,.ssug` | – |
| `--se-muted` | `var(--e-muted)` | `.srch,.sf,.ssug` | – |
| `--se-radius` | `var(--e-radius)` | `.srch,.sf,.ssug` | – |
| `--se-surface` | `var(--e-surface)` | `.srch,.sf,.ssug` | – |

**css/sections.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--e-sec-bg` | `var(--e-bg)` | `.sec--has-bg.bg-white,.sec--has-bg.bg-muted,.sec--has-bg.bg-tint` | – |

### Bewegung & Barrierefreiheit je Datei

| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |
|---|---|---|---|---|---|
| `css/_base.css` | – | ja | ja | – | ja |
| `css/_header.css` | e-hdr | ja | – ⚠ | – | – |
| `css/_hero.css` | e-arc, e-ind | ja | ja | – | – |
| `css/b-cards.css` | – | ja | ja | – | – |
| `css/b-faq.css` | – | ja | – | – | – |
| `css/b-principles.css` | – | ja | – | – | – |
| `css/b-stats.css` | – | ja | ja | – | – |
| `css/calendar.css` | – | – | – | ja | ja |
| `css/data.css` | – | ja | – | – | ja |
| `css/dataform.css` | – | ja | – | – | ja |
| `css/hero-x.css` | hz-knob, hz-seg | ja | ja | ja | ja |
| `css/media.css` | cms-lb-in, cms-stack | ja | ja | – | ja |
| `css/overlay.css` | e-sheet | ja | – ⚠ | – | – |
| `css/search.css` | – | – | – | ja | ja |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/site.js` | ja | ja | ja | – | 8,5 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

_Keine Namensraum-Ereignisse._

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-active` | functions.php |
| `data-closed` | assets/js/site.js |
| `data-cms-sticky` | templates/partials/header.php |
| `data-design-preview` | templates/layout.php |
| `data-dows` | assets/js/site.js |
| `data-header` | templates/partials/header.php, assets/js/site.js |
| `data-hero-video-box` | blocks/hero.php |
| `data-hours` | assets/js/site.js |
| `data-mnav` | templates/partials/sheet.php, assets/js/site.js |
| `data-open` | assets/js/site.js |
| `data-openstate` | assets/js/site.js |
| `data-openstate-text` | assets/js/site.js |
| `data-reveal` | blocks/cards.php, blocks/features.php, blocks/principles.php, blocks/quote.php, blocks/stats.php … |
| `data-scope` | templates/layout.php |
| `data-slots` | assets/js/site.js |
| `data-sw` | templates/layout.php |

<!-- docs:assets:end -->
