# CSS & JS – Kit „Nature – organisch & ruhig“ (nature)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=nature --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden (Layout, Wartung, offline): `css/editing.css`, `css/overlay.css`, `css/pages.css`, `css/preview.css`, `css/site.css`, `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, 20 Einträge).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/nature/`).

## 2. Tokens & Farben

- Kit-Variablen: `--n-*` an `:root`.
- Gesetzte Kern-Variablen: `--cal-accent`, `--cal-accent-ink`, `--cal-ev-bg`, `--cal-line`, `--cal-muted`, `--cal-radius`, `--cal-surface`, `--cal-today-bg`, `--cms-map-accent`, `--cms-map-bg`, `--cms-map-line`, `--cms-map-radius`, `--dff-gap`, `--dial-accent`, `--dial-font`, `--dial-ink`, `--dial-line`, `--dial-muted`, `--dial-surface`, `--dial-tick`, `--dial-track`, `--dl-gap`, `--ha-accent`, `--ha-bg` ….

## 3. Blöcke → Dateien

- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.

## 4. Animationen & Regeln

- Dateien mit Keyframes/Scroll-Animation: `css/_header.css`, `css/b-hero-season.css`, `css/b-hero.css`, `css/media.css`, `css/opt-pagebg-contours.css`, `css/opt-pagebg-leaves.css`, `css/overlay.css`.
- `prefers-reduced-motion` in `css/site.css`: nein · `forced-colors`: nein · `:focus-visible`: nein.
- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.

## 5. Overlays, Sheets, Dialoge

- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).

## 6. JavaScript & Ereignisse

- Skripte: `js/site.js`.
- Ereignisse: keine eigenen.

## 7. Sonderfälle

- _keine bekannt_

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=nature --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Nature – organisch & ruhig“ (nature 1.0.0)

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
| `css/b-hero.css` | hero:panel, hero:statement |
| `css/b-hero-season.css` | hero:season |
| `css/b-hero-dates.css` | hero:dates |
| `css/b-hero-form.css` | hero:form |
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
| `css/b-team.css` | team |
| `css/b-downloads.css` | downloads |
| `css/b-video.css` | video |
| `css/dataform.css` | contact |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `cards` Produkte / Angebote (Karten) | `blocks/cards.php` | product, service, image | `css/b-cards.css` |
| `contact` Kontakt (Zeiten, Saison, Karte, Formular) | `blocks/contact.php` | – | `css/b-contact.css`, `css/dataform.css` |
| `cta` Handlungsaufruf | `blocks/cta.php` | panel, band, minimal | `css/b-cta.css` |
| `downloads` Downloads | `blocks/downloads.php` | – | `css/b-downloads.css` |
| `faq` Fragen & Antworten | `blocks/faq.php` | split, stacked | `css/b-prose.css`, `css/b-faq.css` |
| `features` Merkmale / Angebote | `blocks/features.php` | panel, cards, list | `css/b-features.css` |
| `hero` Einstieg (Hero) | `blocks/hero.php` | panel, statement, split, compact, season, dates, form | `css/b-hero.css` (panel, statement), `css/b-hero-season.css` (season), `css/b-hero-dates.css` (dates), `css/b-hero-form.css` (form) |
| `map` Karte | `blocks/map.php` | – | `css/b-contact.css` |
| `media_text` Text + Bild | `blocks/media_text.php` | auto, right, left | `css/b-prose.css`, `css/b-media-text.css` |
| `principles` Grundsätze / Versprechen | `blocks/principles.php` | list, grid, accordion | `css/b-principles.css` |
| `quote` Zitat / Stimmen | `blocks/quote.php` | single, grid | `css/b-quote.css` |
| `richtext` Fließtext / Artikel | `blocks/richtext.php` | standard, article | `css/b-prose.css` |
| `specs` Steckbrief (Eckdaten) | `blocks/specs.php` | table, image | `css/b-specs.css` |
| `stats` Kennzahlen | `blocks/stats.php` | dials, row | `css/b-stats.css` |
| `steps` Ablauf / Zeitleiste | `blocks/steps.php` | steps, timeline | `css/b-steps.css` |
| `team` Team / Menschen | `blocks/team.php` | grid, list | `css/b-team.css` |
| `video` Video | `blocks/video.php` | wide, text | `css/b-prose.css`, `css/b-video.css` |

### Variablen je Stylesheet

**css/_base.css** (35)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dial-accent` | `var(--n-ui,var(--n-a))` | `.cms-dials` | – |
| `--dial-font` | `var(--n-font-head)` | `.cms-dials` | – |
| `--dial-ink` | `var(--n-ink)` | `.cms-dials` | – |
| `--dial-line` | `var(--n-line)` | `.cms-dials` | – |
| `--dial-muted` | `var(--n-muted)` | `.cms-dials` | – |
| `--dial-surface` | `var(--n-card)` | `.cms-dials` | – |
| `--dial-tick` | `var(--n-line-strong)` | `.cms-dials` | – |
| `--dial-track` | `var(--n-surface-2)` | `.cms-dials` | – |
| `--fx` | `0%` | `.fx0` | 9 |
| `--fy` | `0%` | `.fy0` | 9 |
| `--ico-2-opacity` | `.2` | `.ico` | – |
| `--min` | `13rem` | `.min-s` | 2 |
| `--n-a-soft` | `color-mix(in srgb,var(--n-fg) 12%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | 1 |
| `--n-a2` | `color-mix(in srgb,var(--n-fg) 18%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-btn-bg` | `var(--n-a-on)` | `.bg-accent` | 1 |
| `--n-btn-ink` | `var(--n-a)` | `.bg-accent` | 1 |
| `--n-btn2-bg` | `transparent` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-btn2-ink` | `var(--n-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-card` | `color-mix(in srgb,var(--n-fg) 6%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | 1 |
| `--n-card-bd` | `var(--n-line)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-card-sh` | `none` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-fg` | `var(--n-a-on)` | `.bg-accent` | 2 |
| `--n-focus` | `var(--n-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-hi` | `rgb(255 255 255/.06)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-ink` | `var(--n-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-line` | `color-mix(in srgb,var(--n-fg) 18%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-line-strong` | `color-mix(in srgb,var(--n-fg) 50%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-link` | `var(--n-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-mark` | `var(--n-a-on)` | `.bg-accent` | 1 |
| `--n-muted` | `var(--n-a-on)` | `.bg-accent` | 2 |
| `--n-sec-bg` | `var(--n-surface)` | `.bg-muted` | 3 |
| `--n-surface` | `color-mix(in srgb,var(--n-panel) 55%,transparent)` | `.bg-tint` | 2 |
| `--n-surface-2` | `color-mix(in srgb,var(--n-fg) 13%,transparent)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-text` | `var(--n-fg)` | `.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--n-ui` | `var(--n-a-on)` | `.bg-accent` | 1 |

**css/_footer.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `.25rem 1rem` | `.ftr__social` | 1 |

**css/_knob.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ico-2-opacity` | `.28` | `.knob .ico` | – |

**css/_tokens.css** (90)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-a` | `var(--n-a-ink)` | `:root` | – |
| `--b-a-on` | `var(--n-a-on)` | `:root` | – |
| `--b-bg` | `var(--n-panel)` | `:root` | – |
| `--b-ink` | `var(--n-ink)` | `:root` | – |
| `--b-line` | `var(--n-line)` | `:root` | – |
| `--b-muted` | `var(--n-muted)` | `:root` | – |
| `--b-radius` | `var(--n-radius)` | `:root` | – |
| `--b-surface` | `var(--n-surface)` | `:root` | – |
| `--n-a` | `#52703A` | `:root` | 1 |
| `--n-a-ink` | `#3F5B28` | `:root` | 1 |
| `--n-a-on` | `#FFFFFF` | `:root` | 1 |
| `--n-a-soft` | `color-mix(in srgb,var(--n-a) 16%,var(--n-bg))` | `:root` | – |
| `--n-a2` | `color-mix(in srgb,var(--n-a) 26%,var(--n-panel))` | `:root` | – |
| `--n-bg` | `#F6F2E8` | `:root` | 1 |
| `--n-btn-bg` | `var(--n-a)` | `:root` | 1 |
| `--n-btn-ink` | `var(--n-a-on)` | `:root` | 1 |
| `--n-btn-r` | `.5rem` | `:root` | 1 |
| `--n-btn2-bg` | `transparent` | `:root` | – |
| `--n-btn2-ink` | `var(--n-ink)` | `:root` | – |
| `--n-card` | `var(--n-panel)` | `:root` | – |
| `--n-card-bd` | `color-mix(in srgb,var(--n-line) 70%,transparent)` | `:root` | 1 |
| `--n-card-sh` | `0 1px 2px var(--n-sh1),0 14px 32px -20px var(--n-sh2)` | `:root` | 2 |
| `--n-danger` | `#B42318` | `:root` | 1 |
| `--n-dark` | `#1F2B22` | `:root` | 1 |
| `--n-earth` | `#A85A32` | `:root` | 1 |
| `--n-focus` | `var(--n-a-ink)` | `:root` | – |
| `--n-font` | `"Nunito Sans",ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif` | `:root` | – |
| `--n-font-head` | `Fraunces,"Iowan Old Style",Georgia,ui-serif,serif` | `:root` | – |
| `--n-font-label` | `var(--n-font-head)` | `:root` | 1 |
| `--n-font-mono` | `var(--n-font)` | `:root` | – |
| `--n-fs-max` | `18.5` | `:root` | – |
| `--n-fs-min` | `16` | `:root` | – |
| `--n-gutter` | `clamp(1rem,.5rem + 2.4vw,3rem)` | `:root` | – |
| `--n-hdr-bg` | `color-mix(in srgb,var(--n-bg) 86%,transparent)` | `:root` | – |
| `--n-hi` | `rgb(255 255 255/.7)` | `:root` | 2 |
| `--n-hw` | `560` | `:root` | – |
| `--n-img-r` | `calc(var(--n-radius)*3) calc(var(--n-radius)*.5)` | `:root` | 2 |
| `--n-ink` | `#1F281D` | `:root` | 1 |
| `--n-key-sh` | `0 1px 0 rgb(0 0 0/.08),0 4px 12px -6px var(--n-sh2)` | `:root` | 3 |
| `--n-label` | `calc(var(--n-t0)*.95)` | `:root` | 1 |
| `--n-lb-case` | `none` | `:root` | 1 |
| `--n-lb-style` | `italic` | `:root` | 1 |
| `--n-lb-track` | `0em` | `:root` | 1 |
| `--n-line` | `#DCD3BE` | `:root` | 1 |
| `--n-line-strong` | `color-mix(in srgb,var(--n-ink) 55%,var(--n-panel))` | `:root` | – |
| `--n-link` | `var(--n-a-ink)` | `:root` | – |
| `--n-mark` | `var(--n-earth)` | `:root` | – |
| `--n-muted` | `#5A5F4F` | `:root` | 1 |
| `--n-on-dark` | `#F1EEE3` | `:root` | – |
| `--n-pad` | `var(--n-s-m)` | `:root` | – |
| `--n-panel` | `#FCFAF4` | `:root` | 1 |
| `--n-r` | `calc(1 + (var(--n-ratio) - 1)*.72)` | `:root` | 1 |
| `--n-r-l` | `var(--n-radius)` | `:root` | – |
| `--n-r-s` | `calc(var(--n-radius)*.5)` | `:root` | – |
| `--n-r-xl` | `calc(var(--n-radius)*1.5)` | `:root` | – |
| `--n-radius` | `16px` | `:root` | – |
| `--n-ratio` | `1.25` | `:root` | – |
| `--n-s-2xl` | `calc(var(--n-u)*8)` | `:root` | – |
| `--n-s-2xs` | `calc(var(--n-u)*.5)` | `:root` | – |
| `--n-s-3xl` | `calc(var(--n-u)*12)` | `:root` | – |
| `--n-s-l` | `calc(var(--n-u)*4)` | `:root` | – |
| `--n-s-m` | `calc(var(--n-u)*3)` | `:root` | – |
| `--n-s-s` | `calc(var(--n-u)*2)` | `:root` | – |
| `--n-s-xl` | `calc(var(--n-u)*6)` | `:root` | – |
| `--n-s-xs` | `var(--n-u)` | `:root` | – |
| `--n-sec` | `calc(clamp(var(--n-u)*8,var(--n-u)*4 + 6vw,var(--n-u)*16)*var(--n-space))` | `:root` | – |
| `--n-sh1` | `rgb(52 40 20/.06)` | `:root` | 2 |
| `--n-sh2` | `rgb(52 40 20/.16)` | `:root` | 2 |
| `--n-soft` | `100` | `:root` | – |
| `--n-space` | `1` | `:root` | – |
| `--n-surface` | `#ECE5D3` | `:root` | 1 |
| `--n-surface-2` | `color-mix(in srgb,var(--n-ink) 6%,var(--n-surface))` | `:root` | – |
| `--n-t-1` | `calc(var(--n-t0)*.875)` | `:root` | – |
| `--n-t-2` | `calc(var(--n-t0)*.75)` | `:root` | – |
| `--n-t0` | `clamp(var(--n-fs-min)*.0625rem,var(--n-fs-min)*.0625rem + (var(--n-fs-max) - var(--n-fs-min))*(100vw - 360px)/920,var(--n-fs-max)*.0625rem)` | `:root` | – |
| `--n-t1` | `calc(var(--n-t0)*var(--n-r))` | `:root` | – |
| `--n-t2` | `calc(var(--n-t0)*pow(var(--n-r),2))` | `:root` | – |
| `--n-t3` | `calc(var(--n-t0)*pow(var(--n-r),3))` | `:root` | – |
| `--n-t4` | `calc(var(--n-t0)*pow(var(--n-r),4))` | `:root` | – |
| `--n-t5` | `calc(var(--n-t0)*pow(var(--n-r),5))` | `:root` | – |
| `--n-t6` | `calc(var(--n-t0)*pow(var(--n-r),6))` | `:root` | – |
| `--n-text` | `#3A4034` | `:root` | 1 |
| `--n-track` | `-1.5` | `:root` | – |
| `--n-u` | `.5rem` | `:root` | – |
| `--n-well` | `inset 0 1px 3px var(--n-sh1)` | `:root` | 1 |
| `--n-wrap` | `74rem` | `:root` | – |
| `--n0-ink` | `var(--n-ink)` | `:root` | – |
| `--n0-link` | `var(--n-link)` | `:root` | – |
| `--n0-muted` | `var(--n-muted)` | `:root` | – |
| `--n0-text` | `var(--n-text)` | `:root` | – |

**css/b-cards.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `var(--n-s-m)` | `.pcards` | – |

**css/b-contact.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cms-map-accent` | `var(--n-a)` | `.cms-map` | – |
| `--cms-map-bg` | `var(--n-surface-2)` | `.cms-map` | – |
| `--cms-map-line` | `var(--n-line)` | `.cms-map` | – |
| `--cms-map-radius` | `var(--n-r-l)` | `.cms-map` | – |

**css/b-hero-dates.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--hd-cut` | `1.1rem` | `.hd` | – |
| `--hd-hole` | `.45rem` | `.hd` | – |
| `--hd-paper` | `color-mix(in srgb,var(--n-earth) 13%,var(--n-card))` | `.hd` | – |
| `--hd-twine` | `color-mix(in srgb,var(--n-earth) 55%,var(--n-ink))` | `.hd` | – |
| `--hd-wood` | `color-mix(in srgb,var(--n-earth) 40%,var(--n-ink))` | `.hd` | – |

**css/b-hero-form.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dff-gap` | `var(--n-s-s)` | `.hf__paper` | – |

**css/b-hero-season.css** (17)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--hs-barn` | `color-mix(in srgb,var(--n-earth) 82%,var(--n-ink))` | `.hs` | – |
| `--hs-bloom` | `color-mix(in srgb,var(--n-earth) 42%,var(--n-panel))` | `.hs` | 1 |
| `--hs-crown` | `color-mix(in srgb,var(--n-a) 80%,var(--n-ink))` | `.hs` | 2 |
| `--hs-far` | `color-mix(in srgb,var(--n-a) 32%,var(--n-surface))` | `.hs` | 6 |
| `--hs-fence` | `color-mix(in srgb,var(--n-earth) 50%,var(--n-ink))` | `.hs` | – |
| `--hs-fir` | `color-mix(in srgb,var(--n-a) 50%,var(--n-ink))` | `.hs` | – |
| `--hs-line` | `color-mix(in srgb,var(--n-ink) 16%,transparent)` | `.hs` | 1 |
| `--hs-mid` | `color-mix(in srgb,var(--n-a) 60%,var(--n-surface))` | `.hs` | 6 |
| `--hs-near` | `var(--n-a)` | `.hs` | 8 |
| `--hs-roof` | `color-mix(in srgb,var(--n-ink) 72%,var(--n-earth))` | `.hs` | – |
| `--hs-silo` | `color-mix(in srgb,var(--n-ink) 14%,var(--n-panel))` | `.hs` | 2 |
| `--hs-sky` | `color-mix(in srgb,var(--n-a) 13%,var(--n-card))` | `.hs` | 3 |
| `--hs-sky2` | `color-mix(in srgb,var(--n-earth) 6%,var(--n-card))` | `.hs` | 3 |
| `--hs-snow` | `var(--n-panel)` | `.hs` | 2 |
| `--hs-sun` | `var(--n-earth)` | `.hs` | 2 |
| `--hs-trunk` | `color-mix(in srgb,var(--n-earth) 45%,var(--n-ink))` | `.hs` | – |
| `--hs-wheat` | `color-mix(in srgb,var(--n-earth) 62%,var(--n-panel))` | `.hs--sommer` | – |

**css/b-prose.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--rt-accent` | `var(--n-link)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-dark,.sec--ov-dark` | 1 |
| `--rt-danger` | `#B42318` | `:root` | 4 |
| `--rt-muted` | `var(--n-muted)` | `:root,.bg-white,.bg-muted,.bg-tint,.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--rt-success` | `#1A7240` | `:root` | 4 |
| `--rt-warning` | `#8A4B00` | `:root` | 4 |

**css/calendar.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cal-accent` | `var(--n-btn-bg)` | `.cal` | – |
| `--cal-accent-ink` | `var(--n-btn-ink)` | `.cal` | – |
| `--cal-ev-bg` | `var(--n-a-soft)` | `.cal` | – |
| `--cal-line` | `var(--n-line)` | `.cal` | – |
| `--cal-muted` | `var(--n-muted)` | `.cal` | – |
| `--cal-radius` | `var(--n-r-l)` | `.cal` | – |
| `--cal-surface` | `var(--n-surface)` | `.cal` | 1 |
| `--cal-today-bg` | `var(--n-a-soft)` | `.cal` | – |

**css/data.css** (3)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cols` | `3` | `.dl--cards .dl-items` | 2 |
| `--dl-gap` | `var(--n-s-l)` | `.dl,.df` | – |
| `--min` | `22rem` | `.dl--cards .dl-cols-2` | 2 |

**css/dataform.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dff-gap` | `var(--n-s-m)` | `.dff-wrap` | – |
| `--n-danger` | `color-mix(in srgb,#E5484D 66%,var(--n-ink))` | `.dff-wrap` | – |

**css/header-actions-kit.css** (10)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-accent` | `var(--n-a)` | `.ha,.ha-below` | – |
| `--ha-bg` | `var(--n-panel)` | `.ha,.ha-below` | – |
| `--ha-gap` | `.5rem` | `.ha,.ha-below` | – |
| `--ha-h` | `2.75rem` | `.ha,.ha-below` | – |
| `--ha-ink` | `var(--n-ink)` | `.ha,.ha-below` | – |
| `--ha-line` | `var(--n-line)` | `.ha,.ha-below` | – |
| `--ha-muted` | `var(--n-muted)` | `.ha,.ha-below` | – |
| `--ha-on` | `var(--n-a-on)` | `.ha,.ha-below` | – |
| `--ha-open` | `var(--n-a)` | `.ha,.ha-below` | – |
| `--ha-surface` | `var(--n-surface)` | `.ha,.ha-below` | – |

**css/header-actions-late.css** (2)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-panel-bg` | `var(--n-panel)` | `.ha-menu__panel` | – |
| `--ha-panel-shadow` | `0 1px 0 var(--n-line),0 20px 40px -20px rgb(40 30 10/.35)` | `.ha-menu__panel` | – |

**css/media.css** (11)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ar` | `5` | `.ar-5` | 19 |
| `--i` | `1` | `.cms-stack__card:nth-child(2)` | 6 |
| `--n` | `2` | `.n-2` | 6 |
| `--n-btn-bg` | `var(--n-on-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--n-btn-hover` | `color-mix(in srgb,var(--n-on-dark) 86%,var(--n-dark))` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--n-btn-ink` | `var(--n-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--n-ink` | `var(--n0-ink)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--n-link` | `var(--n0-link)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--n-muted` | `var(--n0-muted)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--n-text` | `var(--n0-text)` | `:is(.bg-accent,.bg-dark,.sec--ov-dark) .cms-stack__inner` | – |
| `--row` | `clamp(8rem,3rem + 16vw,16rem)` | `.cms-gallery--justified` | 3 |

**css/opt-dividers-hills.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--n-dv` | `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 40' preserveAspectRatio='none'%3E%3Cpath d='M0 40V30C90 30 150 6 300 6S470 30 560 30S700 12 820 12S980 34 1080 34S1160 24 1200 22V40Z'/%3E%3C/svg%3E")` | `:is(.bg-muted,.bg-tint,.bg-accent,.bg-dark):not(.sec--has-bg,.sec--screen)` | – |

**css/opt-dividers-wave.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--n-dv` | `url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 40' preserveAspectRatio='none'%3E%3Cpath d='M0 40V22C140 4 300 2 460 16S760 38 920 24S1110 6 1200 14V40Z'/%3E%3C/svg%3E")` | `:is(.bg-muted,.bg-tint,.bg-accent,.bg-dark):not(.sec--has-bg,.sec--screen)` | – |

**css/opt-footer-panel.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--n-btn-bg` | `var(--n-ui)` | `.ftr__panel` | – |
| `--n-btn-ink` | `var(--n-dark)` | `.ftr__panel` | – |
| `--n-focus` | `var(--n-on-dark)` | `.ftr__panel` | – |
| `--n-ui` | `color-mix(in srgb,var(--n-a) 45%,var(--n-on-dark))` | `.ftr__panel` | – |

**css/opt-footer-simple.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--gap` | `.25rem var(--n-s-m)` | `.ftr__inline` | – |

**css/pages.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--stack` | `var(--n-s-m)` | `.err__text` | – |

**css/preview.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--n-danger` | `#FF8A80` | `html.is-dark` | – |
| `--n-hi` | `rgb(255 255 255/.05)` | `html.is-dark` | – |
| `--n-sh1` | `rgb(0 0 0/.3)` | `html.is-dark` | – |
| `--n-sh2` | `rgb(0 0 0/.55)` | `html.is-dark` | – |

**css/search.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--se-accent` | `var(--n-a-ink)` | `.srch,.sf,.ssug` | – |
| `--se-bg` | `var(--n-bg)` | `.srch,.sf,.ssug` | – |
| `--se-line` | `var(--n-line)` | `.srch,.sf,.ssug` | – |
| `--se-mark` | `color-mix(in srgb,var(--n-a2) 70%,transparent)` | `.srch,.sf,.ssug` | – |
| `--se-muted` | `var(--n-muted)` | `.srch,.sf,.ssug` | – |
| `--se-radius` | `var(--n-radius)` | `.srch,.sf,.ssug` | – |
| `--se-surface` | `var(--n-surface)` | `.srch,.sf,.ssug` | – |

**css/sections.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--n-sec-bg` | `var(--n-bg)` | `.sec--has-bg.bg-white,.sec--has-bg.bg-muted,.sec--has-bg.bg-tint` | – |

### Bewegung & Barrierefreiheit je Datei

| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |
|---|---|---|---|---|---|
| `css/_base.css` | – | ja | ja | – | ja |
| `css/_header.css` | n-hdr | ja | – ⚠ | – | – |
| `css/b-cards.css` | – | ja | ja | – | – |
| `css/b-faq.css` | – | ja | – | – | – |
| `css/b-hero-dates.css` | – | ja | ja | – | ja |
| `css/b-hero-season.css` | hs-fall | ja | ja | – | – |
| `css/b-hero.css` | n-drift, n-sway | ja | ja | – | – |
| `css/b-principles.css` | – | ja | – | – | – |
| `css/b-stats.css` | – | ja | ja | – | – |
| `css/calendar.css` | – | – | – | ja | ja |
| `css/data.css` | – | ja | – | – | ja |
| `css/dataform.css` | – | ja | – | – | ja |
| `css/media.css` | cms-lb-in, cms-stack | ja | ja | – | ja |
| `css/opt-pagebg-contours.css` | n-float | ja | ja | – | – |
| `css/opt-pagebg-leaves.css` | n-float | ja | ja | – | – |
| `css/overlay.css` | n-sheet | ja | – ⚠ | – | – |
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
| `data-hours` | assets/js/site.js |
| `data-mnav` | templates/partials/sheet.php, assets/js/site.js |
| `data-open` | assets/js/site.js |
| `data-openstate` | assets/js/site.js |
| `data-openstate-text` | assets/js/site.js |
| `data-overflow-ok` | blocks/hero.php |
| `data-reveal` | blocks/cards.php, blocks/features.php, blocks/principles.php, blocks/quote.php, blocks/stats.php … |
| `data-scope` | templates/layout.php |
| `data-slots` | assets/js/site.js |
| `data-sw` | templates/layout.php |

<!-- docs:assets:end -->
