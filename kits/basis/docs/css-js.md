# CSS & JS – Kit „Basis (neutral)“ (basis)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=basis --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden (Layout, Wartung, offline): `css/blocks.css`, `css/preview.css`, `css/site.css`, `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, 5 Einträge).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/basis/`).

## 2. Tokens & Farben

- Kit-Variablen: `--b-*` an `:root`.
- Gesetzte Kern-Variablen: `--cal-accent`, `--cal-accent-ink`, `--cal-ev-bg`, `--cal-ev-ink`, `--cal-focus`, `--cal-line`, `--cal-muted`, `--cal-radius`, `--cal-surface`, `--cal-today-bg`, `--cms-accent`, `--cms-card-bg`, `--cms-card-ink`, `--cms-card-line`, `--cms-gallery-gap`, `--cms-lb-bg`, `--cms-light-ink`, `--cms-line`, `--cms-map-accent`, `--cms-map-bg`, `--cms-map-line`, `--cms-map-radius`, `--cms-muted`, `--cms-on-accent` ….

## 3. Blöcke → Dateien

- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.

## 4. Animationen & Regeln

- Dateien mit Keyframes/Scroll-Animation: `css/nav-extended.css`, `css/nav-minimal.css`, `css/nav-modern.css`, `css/site.css`.
- `prefers-reduced-motion` in `css/site.css`: ja · `forced-colors`: nein · `:focus-visible`: ja.
- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.

## 5. Overlays, Sheets, Dialoge

- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).

## 6. JavaScript & Ereignisse

- Skripte: `js/blocks.js`, `js/site.js`.
- Ereignisse: keine eigenen.

## 7. Sonderfälle

- _keine bekannt_

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=basis --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Basis (neutral)“ (basis 1.0.0)

### Von Vorlagen eingebunden (theme_asset() in templates/)

Feste Namen; zusammengesetzte (z. B. `css/nav-{stil}.css`) erkennt der Generator nicht.

- `css/blocks.css` – templates/maintenance.php, templates/offline.php
- `css/preview.css` – templates/layout.php
- `css/site.css` – templates/layout.php, templates/maintenance.php, templates/offline.php
- `js/site.js` – templates/layout.php

### Bedingt geladen – theme.php → conditional_css

| Datei | lädt bei |
|---|---|
| `css/blocks.css` | stats, quote, faq, logos, downloads, contact, pricing, steps, tabs, video |
| `css/extra.css` | gallery, slideshow, stack_cards, contact, map |
| `js/blocks.js` | tabs |
| `css/rich.css` | @rich |
| `css/hero-x.css` | hero:search, hero:form, hero:map |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `contact` Kontakt | `blocks/contact.php` | – | `css/blocks.css`, `css/extra.css` |
| `cta` Handlungsaufruf | `blocks/cta.php` | band, box | – |
| `downloads` Downloads | `blocks/downloads.php` | – | `css/blocks.css` |
| `faq` Fragen & Antworten | `blocks/faq.php` | – | `css/blocks.css` |
| `features` Merkmale / Leistungen | `blocks/features.php` | – | – |
| `hero` Einstieg (Hero) | `blocks/hero.php` | split, centered, compact, search, form, map | `css/hero-x.css` (search, form, map) |
| `logos` Logos (Partner, Kunden) | `blocks/logos.php` | – | `css/blocks.css` |
| `map` Karte | `blocks/map.php` | – | `css/extra.css` |
| `pricing` Leistungspakete / Preise | `blocks/pricing.php` | – | `css/blocks.css` |
| `quote` Zitat / Stimmen | `blocks/quote.php` | – | `css/blocks.css` |
| `richtext` Fließtext | `blocks/richtext.php` | – | – |
| `stats` Kennzahlen | `blocks/stats.php` | – | `css/blocks.css` |
| `steps` Ablauf / Zeitleiste | `blocks/steps.php` | numbers, timeline | `css/blocks.css` |
| `tabs` Reiter (Tabs) | `blocks/tabs.php` | top, side | `css/blocks.css`, `js/blocks.js` |
| `text_image` Text + Bild | `blocks/text_image.php` | right, left | – |
| `video` Video | `blocks/video.php` | wide, text | `css/blocks.css` |

### Variablen je Stylesheet

**css/blocks.css** (1)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-cols` | `2` | `.stats` | 4 |

**css/calendar.css** (10)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cal-accent` | `var(--b-btn-bg)` | `.cal` | – |
| `--cal-accent-ink` | `var(--b-btn-ink)` | `.cal` | – |
| `--cal-ev-bg` | `var(--b-a-soft)` | `.cal` | – |
| `--cal-ev-ink` | `var(--b-ink)` | `.cal` | – |
| `--cal-focus` | `var(--b-focus)` | `.cal` | – |
| `--cal-line` | `var(--b-line)` | `.cal` | – |
| `--cal-muted` | `var(--b-muted)` | `.cal` | – |
| `--cal-radius` | `var(--b-radius-l)` | `.cal` | – |
| `--cal-surface` | `var(--b-surface)` | `.cal` | 1 |
| `--cal-today-bg` | `var(--b-a-soft)` | `.cal` | – |

**css/data.css** (4)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--dl-accent` | `var(--b-link)` | `.dl,.df` | – |
| `--dl-muted` | `var(--b-muted)` | `.dl,.df` | – |
| `--dl-radius` | `var(--b-radius-l)` | `.dl,.df` | – |
| `--dl-surface` | `var(--b-card)` | `.dl,.df` | – |

**css/dataform.css** (10)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-danger` | `color-mix(in srgb,#E5484D 68%,var(--b-ink))` | `.dff-wrap` | – |
| `--dff-accent` | `var(--b-a)` | `.dff-wrap` | – |
| `--dff-bg` | `var(--b-bg)` | `.dff-wrap` | 1 |
| `--dff-err` | `var(--b-danger)` | `.dff-wrap` | – |
| `--dff-err-bg` | `color-mix(in srgb,var(--b-danger) 10%,var(--b-bg))` | `.dff-wrap` | – |
| `--dff-focus` | `var(--b-focus)` | `.dff-wrap` | – |
| `--dff-ink` | `var(--b-ink)` | `.dff-wrap` | – |
| `--dff-line` | `var(--b-muted)` | `.dff-wrap` | – |
| `--dff-muted` | `var(--b-muted)` | `.dff-wrap` | – |
| `--dff-radius` | `var(--b-radius)` | `.dff-wrap` | – |

**css/extra.css** (30)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-btn-bg` | `var(--b-on-dark)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--b-btn-bg-hover` | `color-mix(in srgb,var(--b-on-dark) 86%,var(--b-dark-sec))` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--b-btn-ink` | `var(--b-dark-sec)` | `:is(.cms-slide--ov-dark,.cms-slide--ov-none) .cms-slide__cta` | – |
| `--b-ink` | `var(--b0-ink)` | `:is(.bg-dark,.bg-accent,.sec--ov-dark) .cms-stack__inner` | – |
| `--b-link` | `var(--b0-link)` | `:is(.bg-dark,.bg-accent,.sec--ov-dark) .cms-stack__inner` | – |
| `--b-muted` | `var(--b0-muted)` | `:is(.bg-dark,.bg-accent,.sec--ov-dark) .cms-stack__inner` | – |
| `--b-text` | `var(--b0-text)` | `:is(.bg-dark,.bg-accent,.sec--ov-dark) .cms-stack__inner` | – |
| `--b0-ink` | `var(--b-ink)` | `:root` | – |
| `--b0-line` | `var(--b-line)` | `:root` | – |
| `--b0-link` | `var(--b-link)` | `:root` | – |
| `--b0-muted` | `var(--b-muted)` | `:root` | – |
| `--b0-text` | `var(--b-text)` | `:root` | – |
| `--cms-accent` | `var(--b-link)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-card-bg` | `var(--b-card)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 3 |
| `--cms-card-ink` | `var(--b-text)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-card-line` | `var(--b-card-bd)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 1 |
| `--cms-gallery-gap` | `clamp(12px,2vw,24px)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-lb-bg` | `color-mix(in srgb,var(--b-dark-sec) 96%,transparent)` | `.cms-lb` | – |
| `--cms-light-ink` | `var(--b-ink)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-line` | `var(--b-line-strong)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-map-accent` | `var(--b-a)` | `.cms-map` | – |
| `--cms-map-bg` | `var(--b-surface-2)` | `.cms-map` | – |
| `--cms-map-line` | `var(--b-line)` | `.cms-map` | – |
| `--cms-map-radius` | `var(--b-radius-l)` | `.cms-map` | – |
| `--cms-muted` | `var(--b-muted)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-on-accent` | `var(--b-btn-ink)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-radius` | `var(--b-radius-l)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-shadow` | `var(--b-shadow)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | 1 |
| `--cms-stack-top` | `calc(var(--b-header-h) + 32px)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |
| `--cms-surface` | `var(--b-surface-2)` | `.cms-gallery,.cms-slider,.cms-stack,.cms-lb` | – |

**css/header-actions-kit.css** (8)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-accent` | `var(--b-btn-bg)` | `.ha,.ha-below` | – |
| `--ha-bg` | `var(--b-bg)` | `.ha,.ha-below` | – |
| `--ha-ink` | `var(--b-ink)` | `.ha,.ha-below` | – |
| `--ha-line` | `var(--b-line-strong)` | `.ha,.ha-below` | – |
| `--ha-muted` | `var(--b-muted)` | `.ha,.ha-below` | – |
| `--ha-on` | `var(--b-btn-ink)` | `.ha,.ha-below` | – |
| `--ha-open` | `var(--b-link)` | `.ha,.ha-below` | – |
| `--ha-surface` | `var(--b-surface)` | `.ha,.ha-below` | – |

**css/nav-modern.css** (7)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-bg` | `color-mix(in srgb,var(--b-on-dark) 22%,transparent)` | `.is-over-dark:not(.is-stuck,.is-open) :is(.brand,.nav--dropdown>.nav__item>.nav__link,.nav--dropdown>.nav__item>.nav__sub>.nav__link,.menu-btn,.site-nav__end>.langswitch)` | – |
| `--b-ink` | `var(--b-on-dark)` | `.is-over-dark:not(.is-stuck,.is-open) :is(.brand,.nav--dropdown>.nav__item>.nav__link,.nav--dropdown>.nav__item>.nav__sub>.nav__link,.menu-btn,.site-nav__end>.langswitch)` | – |
| `--b-line` | `transparent` | `.is-over-dark:not(.is-stuck,.is-open) :is(.brand,.nav--dropdown>.nav__item>.nav__link,.nav--dropdown>.nav__item>.nav__sub>.nav__link,.menu-btn,.site-nav__end>.langswitch)` | – |
| `--b-line-strong` | `color-mix(in srgb,var(--b-on-dark) 55%,transparent)` | `.is-over-dark:not(.is-stuck,.is-open) :is(.brand,.nav--dropdown>.nav__item>.nav__link,.nav--dropdown>.nav__item>.nav__sub>.nav__link,.menu-btn,.site-nav__end>.langswitch)` | – |
| `--b-muted` | `var(--b-on-dark)` | `.is-over-dark:not(.is-stuck,.is-open) :is(.brand,.nav--dropdown>.nav__item>.nav__link,.nav--dropdown>.nav__item>.nav__sub>.nav__link,.menu-btn,.site-nav__end>.langswitch)` | – |
| `--b-surface` | `color-mix(in srgb,var(--b-on-dark) 14%,transparent)` | `.is-over-dark:not(.is-stuck,.is-open) :is(.brand,.nav--dropdown>.nav__item>.nav__link,.nav--dropdown>.nav__item>.nav__sub>.nav__link,.menu-btn,.site-nav__end>.langswitch)` | – |
| `--b-text` | `var(--b-on-dark)` | `.is-over-dark:not(.is-stuck,.is-open) :is(.brand,.nav--dropdown>.nav__item>.nav__link,.nav--dropdown>.nav__item>.nav__sub>.nav__link,.menu-btn,.site-nav__end>.langswitch)` | – |

**css/preview.css** (19)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-a-soft` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-btn-bg` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-btn-bg-hover` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-btn-ink` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-card` | `var(--b-surface)` | `html.is-dark:is(.cards-elevated,.cards-flat)` | 1 |
| `--b-card-bd` | `var(--b-line)` | `html.is-dark:is(.cards-elevated,.cards-flat)` | 1 |
| `--b-focus` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-icon-bg` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-icon-ink` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-ink` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-line` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-line-strong` | `color-mix(in srgb,var(--b-ink) 26%,var(--b-bg))` | `html.is-dark` | 1 |
| `--b-link` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-muted` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-sec-bg` | `var(--b-a-soft)` | `html.is-dark .bg-accent` | – |
| `--b-soft-mix` | `16%` | `html.is-dark` | – |
| `--b-surface` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-surface-2` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |
| `--b-text` | `inherit` | `html.is-dark :is(.bg-accent,.bg-dark)` | – |

**css/rich.css** (5)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--rt-accent` | `var(--b-link)` | `:root,.bg-white,.bg-muted,.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--rt-danger` | `#B42318` | `:root` | 4 |
| `--rt-muted` | `var(--b-muted)` | `:root,.bg-white,.bg-muted,.bg-accent,.bg-dark,.sec--ov-dark` | – |
| `--rt-success` | `#1A7240` | `:root` | 4 |
| `--rt-warning` | `#8A4B00` | `:root` | 4 |

**css/site.css** (50)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--b-a` | `#0F6E68` | `:root` | 1 |
| `--b-a-on` | `#fff` | `:root` | 1 |
| `--b-a-soft` | `color-mix(in srgb,var(--b-a) var(--b-soft-mix),var(--b-bg))` | `:root` | 3 |
| `--b-a-strong` | `#0A5752` | `:root` | 1 |
| `--b-bg` | `#fff` | `:root` | 1 |
| `--b-btn-bg` | `var(--b-a)` | `:root` | 2 |
| `--b-btn-bg-hover` | `var(--b-a-strong)` | `:root` | 2 |
| `--b-btn-ink` | `var(--b-a-on)` | `:root` | 4 |
| `--b-btn-r` | `var(--b-radius)` | `:root` | 1 |
| `--b-card` | `var(--b-bg)` | `:root` | 5 |
| `--b-card-bd` | `var(--b-line)` | `:root` | 3 |
| `--b-card-sh` | `none` | `:root` | 1 |
| `--b-cols` | `2` | `.cols-2` | 3 |
| `--b-dark-sec` | `#12161B` | `:root` | 1 |
| `--b-fg` | `var(--b-a-on)` | `.bg-accent` | 2 |
| `--b-focus` | `var(--b-a)` | `:root` | 2 |
| `--b-font` | `Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif` | `:root` | – |
| `--b-font-head` | `var(--b-font)` | `:root` | – |
| `--b-fs` | `17` | `:root` | – |
| `--b-fx` | `0%` | `.fx0` | 10 |
| `--b-fy` | `0%` | `.fy0` | 10 |
| `--b-gutter` | `clamp(20px,5vw,48px)` | `:root` | – |
| `--b-header-bg` | `color-mix(in srgb,var(--b-bg) 88%,transparent)` | `:root` | 1 |
| `--b-header-h` | `72px` | `:root` | – |
| `--b-hs` | `1` | `:root` | – |
| `--b-hw` | `600` | `:root` | – |
| `--b-icon-bg` | `var(--b-a-soft)` | `:root` | 2 |
| `--b-icon-ink` | `var(--b-a)` | `:root` | 2 |
| `--b-ink` | `#0F1419` | `:root` | 3 |
| `--b-line` | `#E3E6EA` | `:root` | 3 |
| `--b-line-strong` | `color-mix(in srgb,var(--b-ink) 22%,var(--b-bg))` | `:root` | 3 |
| `--b-link` | `var(--b-a)` | `:root` | 2 |
| `--b-muted` | `#59616B` | `:root` | 5 |
| `--b-on-dark` | `#fff` | `:root` | – |
| `--b-radius` | `6px` | `:root` | – |
| `--b-radius-l` | `calc(var(--b-radius) * 1.34)` | `:root` | – |
| `--b-sec-bg` | `var(--b-a-soft)` | `html.has-dark .bg-accent` | 5 |
| `--b-sec-y` | `clamp(64px,9vw,128px)` | `:root` | – |
| `--b-shade` | `#000` | `:root` | – |
| `--b-shadow` | `0 1px 2px color-mix(in srgb,var(--b-shade) 5%,transparent),0 18px 40px -18px color-mix(in srgb,var(--b-shade) 22%,transparent)` | `:root` | – |
| `--b-soft-mix` | `11%` | `:root` | 1 |
| `--b-surface` | `#F5F6F7` | `:root` | 4 |
| `--b-surface-2` | `color-mix(in srgb,var(--b-ink) 6%,var(--b-surface))` | `:root` | 2 |
| `--b-text` | `#2A3037` | `:root` | 3 |
| `--b-track` | `1` | `:root` | – |
| `--b-wrap` | `1200px` | `:root` | – |
| `--cms-overlay-dark` | `linear-gradient(180deg,color-mix(in srgb,var(--b-dark-sec) 55%,transparent),color-mix(in srgb,var(--b-dark-sec) 72%,transparent))` | `.sec--has-bg` | – |
| `--cms-overlay-light` | `color-mix(in srgb,var(--b-bg) 86%,transparent)` | `.sec--has-bg` | – |
| `--lay-card-radius` | `var(--b-radius-l)` | `.lay-grid` | – |
| `--row-card-radius` | `var(--b-radius-l)` | `.sec-row` | – |

### Bewegung & Barrierefreiheit je Datei

| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |
|---|---|---|---|---|---|
| `css/blocks.css` | – | ja | ja | – | ja |
| `css/data.css` | – | ja | – | – | – |
| `css/dataform.css` | – | ja | – | – | – |
| `css/hero-x.css` | – | ja | – | – | ja |
| `css/nav-extended.css` | b-mega | ja | – ⚠ | – | – |
| `css/nav-minimal.css` | b-rise | ja | – ⚠ | – | – |
| `css/nav-modern.css` | b-drop | ja | – ⚠ | – | – |
| `css/site.css` | b-sub | ja | ja | – | ja |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/blocks.js` | – | – | – | – | 1,5 KB (Quelle) |
| `js/site.js` | – ⚠ | – | ja | – | 7,8 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

_Keine Namensraum-Ereignisse._

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-active` | functions.php |
| `data-days` | templates/partials/openstate.php |
| `data-design-preview` | templates/layout.php |
| `data-header` | templates/partials/header-bar.php, templates/partials/header-minimal.php, templates/partials/header-modern.php, assets/js/site.js |
| `data-hours` | templates/partials/openstate.php |
| `data-label-close` | templates/partials/menu-btn.php, assets/js/site.js |
| `data-label-open` | templates/partials/menu-btn.php, assets/js/site.js |
| `data-scope` | templates/layout.php |
| `data-sw` | templates/layout.php |
| `data-t-closed` | templates/partials/openstate.php |
| `data-t-open` | templates/partials/openstate.php |
| `data-t-opens` | templates/partials/openstate.php |
| `data-t-today` | templates/partials/openstate.php |
| `data-t-tomorrow` | templates/partials/openstate.php |
| `data-t-until` | templates/partials/openstate.php |
| `data-tabs` | blocks/tabs.php, assets/js/blocks.js |
| `data-tz` | templates/partials/openstate.php |

<!-- docs:assets:end -->
