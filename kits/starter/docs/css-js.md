# CSS & JS – Kit „Start-Kit“ (starter)

Einheitliche Kit-Seite (Entwicklerhandbuch › Kits & Design › Die Kit-Seite „CSS & JS“). Abschnitte 1–7 gehören dem Kit,
der Anhang zwischen den Markern entsteht mit `php bin/console docs:assets --kit=starter --update`.
Kern-Regeln (Ebenen, CSP, Präfixe, Z-Skala, Bewegung): Entwicklerhandbuch › CSS & JS.

## 1. Überblick

- Von den Vorlagen eingebunden (Layout, Wartung, offline): `css/site.css`, `js/site.js`.
- Block-Dateien nur, wo der Block steht (`theme.php → conditional_css`, 3 Einträge).
- Build: `cd tools && pnpm run build` (Quelle `assets/` → `/assets/kits/starter/`).

## 2. Tokens & Farben

- Kit-Variablen: `--s-*` an `:root`.
- Gesetzte Kern-Variablen: `--cal-accent`, `--cal-accent-ink`, `--cal-focus`, `--cal-radius`, `--cms-accent`, `--cms-card-bg`, `--cms-card-ink`, `--cms-card-line`, `--cms-line`, `--cms-map-accent`, `--cms-map-bg`, `--cms-map-line`, `--cms-map-radius`, `--cms-muted`, `--cms-on-accent`, `--cms-radius`, `--cms-shadow`, `--cms-surface`, `--dff-accent`, `--dff-bg`, `--dff-err`, `--dff-err-bg`, `--dff-focus`, `--dff-ink` ….

## 3. Blöcke → Dateien

- Tabelle im Anhang. Besonderheiten (geteilte Dateien, ersetzte Kern-Stylesheets wie `css/data.css`): _ergänzen_.

## 4. Animationen & Regeln

- Dateien mit Keyframes/Scroll-Animation: keine.
- `prefers-reduced-motion` in `css/site.css`: nein · `forced-colors`: nein · `:focus-visible`: nein.
- Endbild, Pausenknopf, Bearbeiten-Modus: _ergänzen_.

## 5. Overlays, Sheets, Dialoge

- _ergänzen_ (z-index des Kits unter 1000, Fokus, Esc).

## 6. JavaScript & Ereignisse

- Skripte: `js/site.js`.
- Ereignisse: keine eigenen.

## 7. Sonderfälle

- _keine bekannt_

<!-- docs:assets:start – generiert mit php bin/console docs:assets --kit=starter --update, nicht von Hand ändern -->

## Generierte Referenz: Kit „Start-Kit“ (starter 1.0.0)

### Von Vorlagen eingebunden (theme_asset() in templates/)

Feste Namen; zusammengesetzte (z. B. `css/nav-{stil}.css`) erkennt der Generator nicht.

- `css/site.css` – templates/layout.php, templates/maintenance.php, templates/offline.php
- `js/site.js` – templates/layout.php

### Bedingt geladen – theme.php → conditional_css

| Datei | lädt bei |
|---|---|
| `css/blocks.css` | text_image, cards, cta, faq, video, downloads |
| `css/core.css` | data_list, data_fields, data_form, calendar, upcoming, dials, map, gallery, slideshow, stack_cards |
| `css/hero-search.css` | hero:search |

### Blöcke → Dateien

| Block | Renderer | Varianten | CSS/JS (Variante) |
|---|---|---|---|
| `cards` Karten | `blocks/cards.php` | – | `css/blocks.css` |
| `cta` Handlungsaufruf | `blocks/cta.php` | – | `css/blocks.css` |
| `downloads` Downloads | `blocks/downloads.php` | – | `css/blocks.css` |
| `faq` Fragen & Antworten | `blocks/faq.php` | – | `css/blocks.css` |
| `hero` Einstieg (Hero) | `blocks/hero.php` | center, split, search | `css/hero-search.css` (search) |
| `text` Text | `blocks/text.php` | – | – |
| `text_image` Text + Bild | `blocks/text_image.php` | right, left | `css/blocks.css` |
| `video` Video | `blocks/video.php` | – | `css/blocks.css` |

### Variablen je Stylesheet

**css/_base.css** (16)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--fx` | `0%` | `.fx0` | 9 |
| `--fy` | `0%` | `.fy0` | 9 |
| `--rt-accent` | `currentColor` | `.bg-accent` | 1 |
| `--rt-danger` | `#FF9A8F` | `.bg-dark` | – |
| `--rt-muted` | `var(--s-mut)` | `.bg-accent` | 1 |
| `--rt-success` | `#6FD69A` | `.bg-dark` | – |
| `--rt-warning` | `#F2C063` | `.bg-dark` | – |
| `--s-btn-bg` | `var(--s-a-on)` | `.bg-accent` | 1 |
| `--s-btn-fg` | `var(--s-a)` | `.bg-accent` | 1 |
| `--s-card` | `var(--s-bg)` | `.bg-muted` | 2 |
| `--s-fg` | `var(--s-a-on)` | `.bg-accent` | 1 |
| `--s-head` | `var(--s-a-on)` | `.bg-accent` | 1 |
| `--s-link` | `var(--s-a-on)` | `.bg-accent` | 1 |
| `--s-mut` | `var(--s-a-on)` | `.bg-accent` | 1 |
| `--s-sec-bg` | `var(--s-surface)` | `.bg-muted` | 2 |
| `--s-wrap` | `46rem` | `.wrap--text` | – |

**css/_tokens.css** (36)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cb-theme-accent` | `var(--s-a)` | `:root` | – |
| `--cb-theme-on-accent` | `var(--s-a-on)` | `:root` | – |
| `--rt-accent` | `var(--s-link)` | `:root` | – |
| `--rt-danger` | `#B42318` | `:root` | 1 |
| `--rt-muted` | `var(--s-mut)` | `:root` | – |
| `--rt-success` | `#1A7240` | `:root` | 1 |
| `--rt-warning` | `#8A4B00` | `:root` | 1 |
| `--s-a` | `#1F5BC4` | `:root` | 1 |
| `--s-a-on` | `#FFFFFF` | `:root` | 1 |
| `--s-a-soft` | `color-mix(in srgb,var(--s-a) 12%,var(--s-bg))` | `:root` | – |
| `--s-bg` | `#FFFFFF` | `:root` | 1 |
| `--s-btn-bg` | `var(--s-a)` | `:root` | – |
| `--s-btn-fg` | `var(--s-a-on)` | `:root` | – |
| `--s-btn-r` | `8px` | `:root` | – |
| `--s-card` | `var(--s-surface)` | `:root` | – |
| `--s-dark` | `#15181D` | `:root` | 1 |
| `--s-fg` | `var(--s-text)` | `:root` | – |
| `--s-font` | `system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif` | `:root` | – |
| `--s-font-head` | `system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif` | `:root` | – |
| `--s-fs` | `17` | `:root` | – |
| `--s-gutter` | `clamp(1rem,4vw,2rem)` | `:root` | – |
| `--s-head` | `var(--s-ink)` | `:root` | – |
| `--s-hw` | `700` | `:root` | – |
| `--s-ink` | `#111418` | `:root` | 1 |
| `--s-line` | `#D9DEE5` | `:root` | 1 |
| `--s-line-strong` | `color-mix(in srgb,var(--s-text) 55%,var(--s-bg))` | `:root` | – |
| `--s-link` | `var(--s-a)` | `:root` | – |
| `--s-mut` | `var(--s-muted)` | `:root` | – |
| `--s-muted` | `#525A66` | `:root` | 1 |
| `--s-radius` | `10px` | `:root` | – |
| `--s-sec-bg` | `var(--s-bg)` | `:root` | – |
| `--s-shadow` | `0 12px 32px -12px rgb(0 0 0/.25)` | `:root` | 1 |
| `--s-space` | `clamp(3rem,7vw,6rem)` | `:root` | – |
| `--s-surface` | `#F2F4F7` | `:root` | 1 |
| `--s-text` | `#2B3038` | `:root` | 1 |
| `--s-wrap` | `72rem` | `:root` | – |

**css/core.css** (33)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--cal-accent` | `var(--s-btn-bg)` | `.cal` | – |
| `--cal-accent-ink` | `var(--s-btn-fg)` | `.cal` | – |
| `--cal-focus` | `var(--s-link)` | `.cal` | – |
| `--cal-radius` | `var(--s-radius)` | `.cal` | – |
| `--cms-accent` | `var(--s-link)` | `.sec` | – |
| `--cms-card-bg` | `var(--s-sec-bg)` | `.sec` | – |
| `--cms-card-ink` | `var(--s-fg)` | `.sec` | – |
| `--cms-card-line` | `var(--cms-line)` | `.sec` | – |
| `--cms-line` | `color-mix(in srgb,currentColor 16%,transparent)` | `.sec` | – |
| `--cms-map-accent` | `var(--s-a)` | `.cms-map` | – |
| `--cms-map-bg` | `var(--s-surface)` | `.cms-map` | – |
| `--cms-map-line` | `var(--s-line)` | `.cms-map` | – |
| `--cms-map-radius` | `var(--s-radius)` | `.cms-map` | – |
| `--cms-muted` | `var(--s-mut)` | `.sec` | – |
| `--cms-on-accent` | `var(--s-btn-fg)` | `.sec` | – |
| `--cms-radius` | `var(--s-radius)` | `.sec` | – |
| `--cms-shadow` | `var(--s-shadow)` | `.sec` | – |
| `--cms-surface` | `var(--s-card)` | `.sec` | – |
| `--dff-accent` | `var(--s-a)` | `.dff-wrap` | 1 |
| `--dff-bg` | `var(--s-bg)` | `.dff-wrap` | 1 |
| `--dff-err` | `#FF9A8F` | `.has-dark .dff-wrap` | – |
| `--dff-err-bg` | `#3A1714` | `.has-dark .dff-wrap` | – |
| `--dff-focus` | `var(--s-a)` | `.dff-wrap` | 1 |
| `--dff-ink` | `var(--s-head)` | `.dff-wrap` | 1 |
| `--dff-line` | `var(--s-line-strong)` | `.dff-wrap` | 1 |
| `--dff-muted` | `var(--s-mut)` | `.dff-wrap` | – |
| `--dff-radius` | `var(--s-btn-r)` | `.dff-wrap` | – |
| `--dial-accent` | `var(--s-link)` | `.cms-dials` | – |
| `--dial-font` | `var(--s-font-head)` | `.cms-dials` | – |
| `--dl-accent` | `var(--s-link)` | `.dl,.df` | – |
| `--dl-muted` | `var(--s-mut)` | `.dl,.df` | – |
| `--dl-radius` | `var(--s-radius)` | `.dl,.df` | – |
| `--dl-surface` | `var(--s-card)` | `.dl,.df` | – |

**css/site.css** (15)

| Variable | erster Wert | an | weitere Werte |
|---|---|---|---|
| `--ha-accent` | `var(--s-a)` | `.ha,.ha-below` | – |
| `--ha-bg` | `var(--s-bg)` | `.ha,.ha-below` | – |
| `--ha-h` | `2.75rem` | `.ha,.ha-below` | – |
| `--ha-ink` | `var(--s-ink)` | `.ha,.ha-below` | – |
| `--ha-line` | `var(--s-line-strong)` | `.ha,.ha-below` | – |
| `--ha-muted` | `var(--s-muted)` | `.ha,.ha-below` | – |
| `--ha-on` | `var(--s-a-on)` | `.ha,.ha-below` | – |
| `--ha-open` | `var(--s-a)` | `.ha,.ha-below` | – |
| `--ha-surface` | `var(--s-surface)` | `.ha,.ha-below` | – |
| `--se-accent` | `var(--s-a)` | `:root .sf,:root .srch,:root .ssug` | – |
| `--se-bg` | `var(--s-bg)` | `:root .sf,:root .srch,:root .ssug` | – |
| `--se-line` | `var(--s-line)` | `:root .sf,:root .srch,:root .ssug` | – |
| `--se-muted` | `var(--s-muted)` | `:root .sf,:root .srch,:root .ssug` | – |
| `--se-radius` | `var(--s-radius)` | `:root .sf,:root .srch,:root .ssug` | – |
| `--se-surface` | `var(--s-surface)` | `:root .sf,:root .srch,:root .ssug` | – |

### Bewegung & Barrierefreiheit je Datei

| Datei | Keyframes | animiert | reduced-motion | forced-colors | :focus-visible |
|---|---|---|---|---|---|
| `css/_base.css` | – | – | ja | – | ja |

| Skript | reduced-motion | IntersectionObserver | requestAnimationFrame | Modul/Nachladen | Größe |
|---|---|---|---|---|---|
| `js/site.js` | – | – | – | – | 1,0 KB (Quelle) |

⚠ = Keyframes/Scroll-Animation bzw. requestAnimationFrame ohne eigene `prefers-reduced-motion`-Abfrage in derselben Datei (kann trotzdem korrekt sein, wenn eine andere Datei die Regel setzt – prüfen).

### Ereignisse

_Keine Namensraum-Ereignisse._

### data-*-Attribute

| Attribut | Fundstellen |
|---|---|
| `data-active` | templates/partials/header.php |
| `data-cms-sticky` | templates/partials/header.php |
| `data-hdr` | templates/partials/header.php |
| `data-nav` | templates/partials/header.php, assets/js/site.js |
| `data-scope` | templates/layout.php |
| `data-sw` | templates/layout.php |

<!-- docs:assets:end -->
