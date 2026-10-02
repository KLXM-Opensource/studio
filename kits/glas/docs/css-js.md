# CSS & JS – Kit „Glas – Mattglas über Farbfeldern“ (glas)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=glas --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden (Layout, Wartung, offline): `css/dock-desktop.css`, `css/dock-tabbar.css`, `css/editing.css`, `css/langswitch.css`, `css/overlay.css`, `css/pages.css`, `css/preview.css`, `css/print.css`, `css/site.css`, `css/topnote.css`, `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, 21 Einträge).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/glas/`).

## 2. Tokens & Farben

- Kit-Variablen: `--g-*` an `:root`.
- Gesetzte Kern-Variablen: `--cal-accent`, `--cal-accent-ink`, `--cal-ev-bg`, `--cal-line`, `--cal-muted`, `--cal-radius`, `--cal-surface`, `--cal-today-bg`, `--cms-chat-lift`, `--cms-map-accent`, `--cms-map-bg`, `--cms-map-line`, `--cms-map-radius`, `--dff-gap`, `--dial-accent`, `--dial-font`, `--dial-ink`, `--dial-line`, `--dial-muted`, `--dial-surface`, `--dial-tick`, `--dial-track`, `--dl-gap`, `--ha-accent` ….

## 3. Blöcke → Dateien

- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.

## 4. Animationen & Regeln

- Dateien mit Keyframes/Scroll-Animation: `css/_header.css`, `css/h-aurora.css`, `css/media.css`, `css/overlay.css`.
- `prefers-reduced-motion` in `css/site.css`: nein · `forced-colors`: nein · `:focus-visible`: nein.
- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.

## 5. Overlays, Sheets, Dialoge

- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).

## 6. JavaScript & Ereignisse

- Skripte: `js/site.js`.
- Ereignisse: keine eigenen.

## 7. Sonderfälle

- _keine bekannt_

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=glas --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Glas – Mattglas über Farbfeldern“ (glas 1.0.0)

### Von Vorlagen eingebunden (theme_asset() in templates/)

Feste Namen; zusammengesetzte (z. B. `css/nav-{stil}.css`) erkennt der Generator nicht.

- `css/dock-desktop.css` – templates/layout.php
- `css/dock-tabbar.css` – templates/layout.php
- `css/editing.css` – templates/layout.php
- `css/langswitch.css` – templates/layout.php
- `css/overlay.css` – templates/layout.php
- `css/pages.css` – templates/error.php, templates/maintenance.php, templates/offline.php
- `css/preview.css` – templates/layout.php
- `css/print.css` – templates/layout.php
- `css/site.css` – templates/layout.php, templates/maintenance.php, templates/offline.php
- `css/topnote.css` – templates/layout.php
- `js/site.js` – templates/layout.php

### Bedingt geladen – theme.php → conditional_css

| Datei | lädt bei |
|---|---|
| `css/h-aurora.css` | hero:aurora, hero:, hero:command |
| `css/h-command.css` | hero:command |
| `css/h-video.css` | hero:video |
| `css/h-stack.css` | hero:stack |
| `css/h-statement.css` | hero:statement |
| `css/h-split.css` | hero:split |
| `css/orb.css` | features, cards |
| `css/b-prose.css` | richtext, faq, text_image, video, @rich |
| `css/b-text-image.css` | text_image |
| `css/b-features.css` | features |
| `css/b-stats.css` | stats, dials |
| `css/b-steps.css` | steps |
| `css/b-cards.css` | cards |
| `css/b-team.css` | team |
| `css/b-quote.css` | quote |
| `css/b-faq.css` | faq |
| `css/b-cta.css` | cta |
| `css/b-contact.css` | contact, map |
| `css/b-downloads.css` | downloads |
| `css/b-video.css` | video |
| `css/dataform.css` | contact |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `cards` Produkte / Leistungen (Karten) | `blocks/cards.php` | glass, service, overlay | `css/orb.css`, `css/b-cards.css` |
| `contact` Kontakt (Zeiten, Karte, Formular) | `blocks/contact.php` | – | `css/b-contact.css`, `css/dataform.css` |
| `cta` Handlungsaufruf | `blocks/cta.php` | aurora, band, minimal | `css/b-cta.css` |
| `downloads` Downloads | `blocks/downloads.php` | – | `css/b-downloads.css` |
| `faq` Fragen & Antworten | `blocks/faq.php` | split, stacked | `css/b-prose.css`, `css/b-faq.css` |
| `features` Merkmale / Vorteile | `blocks/features.php` | cards, panel, list | `css/orb.css`, `css/b-features.css` |
| `hero` Einstieg (Hero) | `blocks/hero.php` | aurora, split, statement, compact, command, video, stack | `css/h-aurora.css` (aurora, command), `css/h-command.css` (command), `css/h-video.css` (video), `css/h-stack.css` (stack), `css/h-statement.css` (statement), `css/h-split.css` (split) |
| `map` Karte | `blocks/map.php` | – | `css/b-contact.css` |
| `quote` Zitat / Stimmen | `blocks/quote.php` | single, grid | `css/b-quote.css` |
| `richtext` Fließtext / Artikel | `blocks/richtext.php` | standard, article, glass | `css/b-prose.css` |
| `stats` Kennzahlen | `blocks/stats.php` | rings, row | `css/b-stats.css` |
| `steps` Ablauf / Zeitleiste | `blocks/steps.php` | steps, timeline | `css/b-steps.css` |
| `team` Team / Personen | `blocks/team.php` | cards, compact | `css/b-team.css` |
| `text_image` Text + Bild | `blocks/text_image.php` | auto, right, left | `css/b-prose.css`, `css/b-text-image.css` |
| `video` Video | `blocks/video.php` | wide, text | `css/b-prose.css`, `css/b-video.css` |

### Variablen je Stylesheet

**css/_base.css** (30)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--fx` | `0%` | `.fx0` | 9 |
| `--fy` | `0%` | `.fy0` | 9 |
| `--g-a-soft` | `color-mix(in srgb,var(--g-fg) 12%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-a2` | `color-mix(in srgb,var(--g-fg) 18%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-btn-bg` | `var(--g-a-on)` | `.bg-accent` | – |
| `--g-btn-ink` | `var(--g-a)` | `.bg-accent` | – |
| `--g-btn2-bg` | `color-mix(in srgb,#fff 8%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-btn2-ink` | `var(--g-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-card` | `var(--g-panel-strong)` | `.glass--strong` | 2 |
| `--g-card-bd` | `rgb(255 255 255/.16)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-card-sh` | `inset 0 1px 0 rgb(255 255 255/.14),0 18px 40px -22px rgb(0 0 0/.5)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-edge` | `rgb(255 255 255/.16)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-fg` | `var(--g-a-on)` | `.bg-accent` | 2 |
| `--g-focus` | `var(--g-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-hi` | `rgb(255 255 255/.14)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-ink` | `var(--g-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-key-sh` | `none` | `.bg-accent` | – |
| `--g-line` | `color-mix(in srgb,var(--g-fg) 18%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-line-strong` | `color-mix(in srgb,var(--g-fg) 50%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-link` | `var(--g-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-muted` | `var(--g-a-on)` | `.bg-accent` | 2 |
| `--g-panel` | `var(--g-card)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-panel-strong` | `color-mix(in srgb,var(--g-fg) 12%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-sec-bg` | `var(--g-surface)` | `.bg-muted` | 3 |
| `--g-sheen` | `rgb(255 255 255/.06)` | `.bg-accent,.bg-dark,.sec--ov-dark` | 1 |
| `--g-surface` | `color-mix(in srgb,#fff 6%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-surface-2` | `color-mix(in srgb,var(--g-fg) 12%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--g-text` | `var(--g-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--ico-2-opacity` | `.22` | `.ico` | – |
| `--min` | `13rem` | `.min-s` | 2 |

**css/_footer.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `.25rem 1rem` | `.ftr__social` | 1 |

**css/_header.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-hdr-h` | `4.75rem` | `.hdr` | – |

**css/_tokens.css** (102)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-a` | `var(--g-a-ink)` | `:root` | – |
| `--b-a-on` | `var(--g-a-on)` | `:root` | – |
| `--b-bg` | `var(--g-panel-strong)` | `:root` | – |
| `--b-ink` | `var(--g-ink)` | `:root` | – |
| `--b-line` | `var(--g-line)` | `:root` | – |
| `--b-muted` | `var(--g-muted)` | `:root` | – |
| `--b-radius` | `var(--g-radius)` | `:root` | – |
| `--b-surface` | `var(--g-surface)` | `:root` | – |
| `--g-a` | `#6D28D9` | `:root` | 1 |
| `--g-a-ink` | `#5B21B6` | `:root` | 1 |
| `--g-a-on` | `#FFFFFF` | `:root` | 1 |
| `--g-a-soft` | `color-mix(in srgb,var(--g-a) 12%,transparent)` | `:root` | – |
| `--g-a2` | `color-mix(in srgb,var(--g-a) 22%,var(--g-glass))` | `:root` | – |
| `--g-alpha` | `min(1,max(var(--g-floor),var(--g-ga) + var(--g-boost)))` | `:root` | – |
| `--g-alpha-hdr` | `min(1,max(var(--g-floor),var(--g-ha)))` | `:root` | – |
| `--g-alpha-strong` | `min(1,max(var(--g-floor),var(--g-ga) + var(--g-boost) + .06))` | `:root` | – |
| `--g-bg` | `#F3F1FB` | `:root` | 1 |
| `--g-blur` | `20px` | `:root` | – |
| `--g-blur-now` | `calc(var(--g-blur) * .6)` | `:root` | 1 |
| `--g-boost` | `.1` | `:root` | 1 |
| `--g-btn-bg` | `var(--g-a)` | `:root` | – |
| `--g-btn-ink` | `var(--g-a-on)` | `:root` | – |
| `--g-btn-r` | `8px` | `:root` | 1 |
| `--g-btn2-bg` | `var(--g-panel)` | `:root` | – |
| `--g-btn2-ink` | `var(--g-ink)` | `:root` | – |
| `--g-card` | `var(--g-panel)` | `:root` | – |
| `--g-card-bd` | `var(--g-edge)` | `:root` | – |
| `--g-card-sh` | `inset 0 1px 0 var(--g-hi),0 0 0 1px var(--g-rim),0 18px 44px -22px var(--g-sh2)` | `:root` | – |
| `--g-danger` | `#B42318` | `:root` | 2 |
| `--g-dark` | `#15123A` | `:root` | 1 |
| `--g-edge` | `rgb(255 255 255/.62)` | `:root` | 3 |
| `--g-f1` | `#B9A6FF` | `:root` | 1 |
| `--g-f2` | `#8FE3F0` | `:root` | 1 |
| `--g-f3` | `#FFB3D9` | `:root` | 1 |
| `--g-ff` | `.58` | `:root` | 10 |
| `--g-floor` | `0` | `:root` | 4 |
| `--g-focus` | `var(--g-a-ink)` | `:root` | – |
| `--g-font` | `Figtree,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif` | `:root` | – |
| `--g-font-head` | `Outfit,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif` | `:root` | – |
| `--g-font-label` | `var(--g-font)` | `:root` | – |
| `--g-font-mono` | `var(--g-font-head)` | `:root` | – |
| `--g-fs-max` | `18` | `:root` | – |
| `--g-fs-min` | `16` | `:root` | – |
| `--g-ga` | `.72` | `:root` | 8 |
| `--g-glass` | `#FFFFFF` | `:root` | 1 |
| `--g-grain` | `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix values='0 0 0 0 .5 0 0 0 0 .5 0 0 0 0 .5 0 0 0 1.4 -.3'/%3E%3C/filter%3E%3Crect width='180' height='180' filter='url(%23n)'/%3E%3C/svg%3E")` | `:root` | – |
| `--g-gutter` | `clamp(1rem,.5rem + 2.4vw,3rem)` | `:root` | – |
| `--g-ha` | `.8` | `:root` | 2 |
| `--g-hi` | `rgb(255 255 255/.9)` | `:root` | 2 |
| `--g-hw` | `600` | `:root` | – |
| `--g-ink` | `#12142B` | `:root` | 1 |
| `--g-key-sh` | `inset 0 1px 0 rgb(255 255 255/.35),0 6px 16px -8px color-mix(in srgb,var(--g-a) 70%,transparent)` | `:root` | – |
| `--g-label` | `max(.8125rem,calc(var(--g-t0)*.8))` | `:root` | – |
| `--g-line` | `color-mix(in srgb,var(--g-ink) 12%,transparent)` | `:root` | 1 |
| `--g-line-strong` | `color-mix(in srgb,var(--g-ink) 46%,transparent)` | `:root` | – |
| `--g-link` | `var(--g-a-ink)` | `:root` | – |
| `--g-muted` | `#44475F` | `:root` | 2 |
| `--g-on-dark` | `#F4F3FF` | `:root` | – |
| `--g-pad` | `var(--g-s-m)` | `:root` | – |
| `--g-panel` | `color-mix(in srgb,var(--g-glass) calc(var(--g-alpha)*100%),transparent)` | `:root` | – |
| `--g-panel-hdr` | `color-mix(in srgb,var(--g-glass) calc(var(--g-alpha-hdr)*100%),transparent)` | `:root` | – |
| `--g-panel-strong` | `color-mix(in srgb,var(--g-glass) calc(var(--g-alpha-strong)*100%),transparent)` | `:root` | – |
| `--g-r` | `calc(1 + (var(--g-ratio) - 1)*.72)` | `:root` | 1 |
| `--g-r-l` | `var(--g-radius)` | `:root` | – |
| `--g-r-s` | `8px` | `:root` | – |
| `--g-r-xl` | `calc(var(--g-radius) * 1.4)` | `:root` | – |
| `--g-radius` | `20px` | `:root` | – |
| `--g-ratio` | `1.25` | `:root` | – |
| `--g-rim` | `rgb(18 20 43/.07)` | `:root` | 2 |
| `--g-s-2xl` | `calc(var(--g-u)*8)` | `:root` | – |
| `--g-s-2xs` | `calc(var(--g-u)*.5)` | `:root` | – |
| `--g-s-3xl` | `calc(var(--g-u)*12)` | `:root` | – |
| `--g-s-l` | `calc(var(--g-u)*4)` | `:root` | – |
| `--g-s-m` | `calc(var(--g-u)*3)` | `:root` | – |
| `--g-s-s` | `calc(var(--g-u)*2)` | `:root` | – |
| `--g-s-xl` | `calc(var(--g-u)*6)` | `:root` | – |
| `--g-s-xs` | `var(--g-u)` | `:root` | – |
| `--g-sec` | `calc(clamp(var(--g-u)*8,var(--g-u)*4 + 6vw,var(--g-u)*15)*var(--g-space))` | `:root` | – |
| `--g-sh1` | `rgb(40 28 110/.08)` | `:root` | 2 |
| `--g-sh2` | `rgb(40 28 110/.22)` | `:root` | 2 |
| `--g-sheen` | `rgb(255 255 255/.38)` | `:root` | 2 |
| `--g-space` | `1` | `:root` | – |
| `--g-surface` | `color-mix(in srgb,var(--g-glass) calc(var(--g-alpha)*55%),transparent)` | `:root` | – |
| `--g-surface-2` | `color-mix(in srgb,var(--g-ink) 7%,transparent)` | `:root` | – |
| `--g-t-1` | `calc(var(--g-t0)*.875)` | `:root` | – |
| `--g-t-2` | `calc(var(--g-t0)*.8)` | `:root` | – |
| `--g-t0` | `clamp(var(--g-fs-min)*.0625rem,var(--g-fs-min)*.0625rem + (var(--g-fs-max) - var(--g-fs-min))*(100vw - 360px)/920,var(--g-fs-max)*.0625rem)` | `:root` | – |
| `--g-t1` | `calc(var(--g-t0)*var(--g-r))` | `:root` | – |
| `--g-t2` | `calc(var(--g-t0)*pow(var(--g-r),2))` | `:root` | – |
| `--g-t3` | `calc(var(--g-t0)*pow(var(--g-r),3))` | `:root` | – |
| `--g-t4` | `calc(var(--g-t0)*pow(var(--g-r),4))` | `:root` | – |
| `--g-t5` | `calc(var(--g-t0)*pow(var(--g-r),5))` | `:root` | – |
| `--g-t6` | `calc(var(--g-t0)*pow(var(--g-r),6))` | `:root` | – |
| `--g-text` | `#2A2D48` | `:root` | 1 |
| `--g-track` | `-2` | `:root` | – |
| `--g-u` | `.5rem` | `:root` | – |
| `--g-well` | `inset 0 1px 2px var(--g-sh1)` | `:root` | – |
| `--g-wrap` | `76rem` | `:root` | – |
| `--g0-ink` | `var(--g-ink)` | `:root` | – |
| `--g0-link` | `var(--g-link)` | `:root` | – |
| `--g0-muted` | `var(--g-muted)` | `:root` | – |
| `--g0-text` | `var(--g-text)` | `:root` | – |

**css/b-cards.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-card` | `var(--g-panel-strong)` | `.pcards--overlay .pcard__body` | – |
| `--gap` | `var(--g-s-m)` | `.pcards` | – |

**css/b-contact.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-map-accent` | `var(--g-a)` | `.cms-map` | – |
| `--cms-map-bg` | `var(--g-surface-2)` | `.cms-map` | – |
| `--cms-map-line` | `var(--g-line)` | `.cms-map` | – |
| `--cms-map-radius` | `var(--g-r-l)` | `.cms-map` | – |

**css/b-prose.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--rt-accent` | `var(--g-link)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-dark,.sec--ov-dark` | 1 |
| `--rt-danger` | `#B42318` | `:root` | 4 |
| `--rt-muted` | `var(--g-muted)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--rt-success` | `#1A7240` | `:root` | 4 |
| `--rt-warning` | `#8A4B00` | `:root` | 4 |

**css/b-stats.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dial-accent` | `var(--g-a)` | `.cms-dials` | – |
| `--dial-font` | `var(--g-font-head)` | `.cms-dials` | – |
| `--dial-ink` | `var(--g-ink)` | `.cms-dials` | – |
| `--dial-line` | `var(--g-edge)` | `.cms-dials` | – |
| `--dial-muted` | `var(--g-muted)` | `.cms-dials` | – |
| `--dial-surface` | `var(--g-panel)` | `.cms-dials` | – |
| `--dial-tick` | `color-mix(in srgb,var(--g-ink) 34%,transparent)` | `.cms-dials` | – |
| `--dial-track` | `color-mix(in srgb,var(--g-ink) 10%,transparent)` | `.cms-dials` | – |

**css/calendar.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cal-accent` | `var(--g-btn-bg)` | `.cal` | – |
| `--cal-accent-ink` | `var(--g-btn-ink)` | `.cal` | – |
| `--cal-ev-bg` | `var(--g-a-soft)` | `.cal` | – |
| `--cal-line` | `var(--g-line)` | `.cal` | – |
| `--cal-muted` | `var(--g-muted)` | `.cal` | – |
| `--cal-radius` | `var(--g-r-l)` | `.cal` | – |
| `--cal-surface` | `var(--g-surface)` | `.cal` | 1 |
| `--cal-today-bg` | `var(--g-a-soft)` | `.cal` | – |

**css/data.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cols` | `3` | `.dl--cards .dl-items` | 2 |
| `--dl-gap` | `var(--g-s-l)` | `.dl,.df` | – |
| `--min` | `22rem` | `.dl--cards .dl-cols-2` | 2 |

**css/dataform.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dff-gap` | `var(--g-s-m)` | `.dff-wrap` | – |
| `--g-danger` | `color-mix(in srgb,#E5484D 66%,var(--g-ink))` | `.dff-wrap` | – |

**css/dock-tabbar.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-chat-lift` | `calc(4.75rem + env(safe-area-inset-bottom,0px))` | `html.hdr-dock` | – |
| `--ico-2-opacity` | `.28` | `.tab__ico` | 1 |

**css/h-aurora.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-card` | `color-mix(in srgb,var(--g-glass) 34%,transparent)` | `.prism .glass` | – |

**css/h-command.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gcmd-loupe` | `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='2.2' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='m20 20-3.5-3.5'/%3E%3C/svg%3E")` | `.gcmd` | – |

**css/h-video.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-card` | `color-mix(in srgb,var(--g-glass) calc(max(var(--g-alpha-strong),var(--g-alpha-hdr),var(--g-vid-a,.88)) * 100%),transparent)` | `.gvid__panel` | – |
| `--g-vid-a` | `.8` | `html.has-dark .gvid__panel` | – |

**css/header-actions-kit.css** (12)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-accent` | `var(--g-a)` | `.ha,.ha-below` | – |
| `--ha-bg` | `var(--g-panel-strong)` | `.ha,.ha-below` | – |
| `--ha-field-bg` | `color-mix(in srgb,var(--g-glass) 55%,transparent)` | `.ha,.ha-below` | – |
| `--ha-gap` | `.3125rem` | `.ha,.ha-below` | – |
| `--ha-h` | `2.75rem` | `.ha,.ha-below` | – |
| `--ha-ink` | `var(--g-ink)` | `.ha,.ha-below` | – |
| `--ha-line` | `var(--g-edge)` | `.ha,.ha-below` | – |
| `--ha-muted` | `var(--g-muted)` | `.ha,.ha-below` | – |
| `--ha-on` | `var(--g-a-on)` | `.ha,.ha-below` | – |
| `--ha-open` | `var(--g-a-ink)` | `.ha,.ha-below` | – |
| `--ha-surface` | `color-mix(in srgb,var(--g-glass) 55%,transparent)` | `.ha,.ha-below` | – |
| `--ico-2-opacity` | `.35` | `.dock__cta .ha-ico` | – |

**css/header-actions-late.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-panel-bg` | `linear-gradient(180deg,var(--g-sheen),transparent 40%),var(--g-panel-hdr)` | `.ha-menu__panel` | – |
| `--ha-panel-shadow` | `var(--g-card-sh),0 24px 60px -24px var(--g-sh2)` | `.ha-menu__panel` | – |
| `--ico-2-opacity` | `.35` | `.ha-menu__list .ha-ico` | – |

**css/media.css** (11)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ar` | `5` | `.ar-5` | 19 |
| `--g-btn-bg` | `var(--g-on-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--g-btn-hover` | `color-mix(in srgb,var(--g-on-dark) 86%,var(--g-dark))` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--g-btn-ink` | `var(--g-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--g-ink` | `var(--g0-ink)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--g-link` | `var(--g0-link)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--g-muted` | `var(--g0-muted)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--g-text` | `var(--g0-text)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--i` | `1` | `.cms-stack__card:nth-child(2)` | 6 |
| `--n` | `2` | `.n-2` | 6 |
| `--row` | `clamp(8rem,3rem + 16vw,16rem)` | `.cms-gallery--justified` | 3 |

**css/opt-footer-simple.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `.25rem var(--g-s-m)` | `.ftr__inline` | – |

**css/opt-header-bar.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-hdr-h` | `4rem` | `.hdr` | – |

**css/opt-header-centered.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-hdr-h` | `4.75rem` | `.hdr` | – |

**css/opt-header-dock.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-hdr-h` | `4.75rem` | `.hdr--dock` | – |
| `--ico-2-opacity` | `.35` | `.dock__cta-ico` | – |

**css/orb.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ico-2-opacity` | `.3` | `.orb .ico` | – |

**css/overlay.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ico-2-opacity` | `.35` | `.mega__ico .ico` | – |

**css/pages.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--stack` | `var(--g-s-m)` | `.err__text` | – |

**css/preview.css** (10)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-danger` | `#FF8A80` | `html.is-dark` | – |
| `--g-edge` | `rgb(255 255 255/.11)` | `html.is-dark` | – |
| `--g-ff` | `.42` | `html.is-dark` | 2 |
| `--g-ga` | `.76` | `html.is-dark` | 2 |
| `--g-ha` | `.82` | `html.is-dark` | – |
| `--g-hi` | `rgb(255 255 255/.14)` | `html.is-dark` | – |
| `--g-rim` | `rgb(0 0 0/.35)` | `html.is-dark` | – |
| `--g-sh1` | `rgb(0 0 0/.3)` | `html.is-dark` | – |
| `--g-sh2` | `rgb(0 0 0/.62)` | `html.is-dark` | – |
| `--g-sheen` | `rgb(255 255 255/.05)` | `html.is-dark` | – |

**css/search.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--se-accent` | `var(--g-a-ink)` | `.srch,.sf,.ssug` | – |
| `--se-bg` | `var(--g-bg)` | `.srch,.sf,.ssug` | – |
| `--se-line` | `var(--g-line)` | `.srch,.sf,.ssug` | – |
| `--se-mark` | `color-mix(in srgb,var(--g-a2) 70%,transparent)` | `.srch,.sf,.ssug` | – |
| `--se-muted` | `var(--g-muted)` | `.srch,.sf,.ssug` | – |
| `--se-radius` | `var(--g-radius)` | `.srch,.sf,.ssug` | – |
| `--se-surface` | `var(--g-surface)` | `.srch,.sf,.ssug` | – |

**css/sections.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--g-sec-bg` | `var(--g-bg)` | `.sec--has-bg.bg-white,.sec--has-bg.bg-muted,.sec--has-bg.bg-tint` | – |

### Bewegung & Barrierefreiheit je Datei

| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |
|---|---|---|---|---|---|
| `css/_base.css` | – | ja | ja | ja | ja |
| `css/_header.css` | e-hdr | ja | – ⚠ | – | – |
| `css/b-cards.css` | – | ja | ja | – | – |
| `css/b-faq.css` | – | ja | – | – | – |
| `css/b-stats.css` | – | ja | ja | ja | – |
| `css/calendar.css` | – | – | – | ja | ja |
| `css/data.css` | – | ja | – | – | ja |
| `css/dataform.css` | – | ja | – | – | ja |
| `css/dock-desktop.css` | – | ja | ja | – | – |
| `css/dock-tabbar.css` | – | ja | ja | – | ja |
| `css/h-aurora.css` | g-drift, g-sway, g-float | ja | ja | – | – |
| `css/h-command.css` | – | ja | ja | ja | ja |
| `css/h-stack.css` | – | ja | ja | ja | – |
| `css/h-video.css` | – | ja | ja | ja | ja |
| `css/media.css` | cms-lb-in, cms-stack | ja | ja | – | ja |
| `css/opt-header-dock.css` | – | ja | ja | – | – |
| `css/overlay.css` | g-sheet, g-sheet-up, g-mega | ja | – ⚠ | – | ja |
| `css/search.css` | – | – | – | ja | ja |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/site.js` | ja | ja | ja | – | 12,2 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

_Keine Namensraum-Ereignisse._

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-active` | assets/js/site.js, functions.php |
| `data-aurora` | blocks/hero.php, assets/js/site.js |
| `data-closed` | assets/js/site.js |
| `data-cms-hide-editing` | templates/partials/tabbar.php |
| `data-cms-sticky` | templates/partials/header.php |
| `data-design-preview` | templates/layout.php |
| `data-dock` | templates/partials/header.php, assets/js/site.js |
| `data-dows` | assets/js/site.js |
| `data-header` | templates/partials/header.php, assets/js/site.js |
| `data-hero-video-box` | blocks/hero.php |
| `data-hours` | assets/js/site.js |
| `data-lens` | templates/partials/header.php, assets/js/site.js |
| `data-mnav` | templates/partials/sheet.php, assets/js/site.js |
| `data-open` | assets/js/site.js |
| `data-openstate` | assets/js/site.js |
| `data-openstate-text` | assets/js/site.js |
| `data-reveal` | blocks/cards.php, blocks/features.php, blocks/quote.php, blocks/stats.php, blocks/steps.php … |
| `data-scope` | templates/layout.php |
| `data-slots` | assets/js/site.js |
| `data-sw` | templates/layout.php |
| `data-tabbar` | templates/partials/tabbar.php, assets/js/site.js |

<!-- docs:assets:end -->
