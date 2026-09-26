# Third-party notices

**KLXM Studio** – Copyright (c) 2026 KLXM Crossmedia GmbH and contributors – is licensed under the **MIT License**
(`MIT`, see `LICENSE` and `COPYRIGHT`). The bundled kits (`themes/*`) and extensions (`extensions/*`) are part of
the project and licensed the same way, except for the third-party assets listed below. Third-party kits and
extensions may use any license; the licenses of the third-party components bundled with KLXM Studio still apply to
those components.

This file lists every third-party component that KLXM Studio ships, builds with or can optionally use, with the
license each one is distributed under. Licenses were taken from the packages' own metadata and license files
(`composer.lock`, `package.json`, `LICENSE*`, model cards) as of the versions pinned in `composer.lock`,
`tools/pnpm-lock.yaml` and `themes/*/pnpm-lock.yaml`. No bundled component is under a copyleft license that would
extend to KLXM Studio's own code: everything shipped is permissive (MIT, BSD, Apache-2.0, ISC, CC0, OFL fonts), with
the single exception of the Liberation fonts inside PDF.js (GPL-2.0 with font exception, separate font files, see
1.3). Copyleft tools (Piper TTS, ffmpeg) are build-only and not distributed. `joomla/string`
(GPL-2.0-or-later) is no longer installed (see section 3). `node tools/licenses.mjs` re-checks the Composer and npm
dependencies (see section 9).

Legend – **Bundled**: shipped to servers/visitors (`vendor/`, `public/`, `themes/*/fonts`). **Build-only**: used on a
developer machine to produce `public/`, not deployed. **Optional**: installed or downloaded by the operator,
not part of the distribution. **Service**: an online service contacted at runtime.

## Summary of third-party licenses

| License | Where | Type |
|---|---|---|
| MIT | FriendsOfREDAXO/consent_kit (ported into `extensions/consent_kit`), most PHP packages, Phosphor Icons, editorjs-drag-drop, Editor.js sub-packages, MapLibre sub-packages, qcms, QuickJS, lbuchs/webauthn | Bundled |
| MIT OR Apache-2.0 | chillerlan/php-qrcode, @maplibre/mlt | Bundled |
| Apache-2.0 | Editor.js, Mozilla PDF.js (incl. its JBIG2 wrapper) | Bundled |
| BSD-3-Clause | sabre/* (CalDAV/CardDAV), MapLibre GL JS, pbf, @mapbox/vector-tile, PDFium JBIG2 and Foxit fonts in PDF.js, Adobe CMaps (BSD-style) | Bundled |
| BSD-2-Clause | OpenJPEG (PDF.js), @mapbox/tiny-sdf, @mapbox/unitbezier | Bundled |
| ISC | earcut, kdbush, potpack, quickselect, tinyqueue, geojson-vt, point-geometry, maplibre-gl-style-spec | Bundled |
| CC0-1.0 | ICC colour profile in PDF.js | Bundled |
| GPL-2.0 with font exception (Red Hat Liberation font license) | Liberation Sans fonts inside PDF.js (`standard_fonts/`) – separate font files, see 1.3 | Bundled |
| SIL Open Font License 1.1 | Lato (admin) and all kit fonts | Bundled |
| CC BY 3.0 | “Big Buck Bunny” (Blender Foundation), embedded from YouTube in kit demo content | Service / demo |
| ODbL 1.0 (data), OpenMapTiles attribution | OpenStreetMap data via OpenFreeMap | Service |
| Apache-2.0 | Playwright (tutorial recordings) | Build-only |
| GPL-3.0-or-later | Piper TTS (`piper-tts` 1.8, optional tutorial narration tooling – videos currently silent) | Build-only |
| MIT / BSD-style / public domain | Piper voice models and their training data (section 5) | Build-only (not shipped; videos are silent) |
| MIT | whisper.cpp and OpenAI Whisper model weights | Optional |

## 0. Ported code in extensions (Bundled)

- **FriendsOfREDAXO/consent_kit** (https://github.com/FriendsOfREDAXO/consent_kit, 1.0.0-beta3) – MIT,
  © 2026 Friends Of REDAXO, KLXM Crossmedia GmbH. Service templates (`extensions/consent_kit/presets/*.json`), the web
  component and parts of the PHP logic are ported into `extensions/consent_kit` (MIT as a whole). The MIT
  license text and the list of ported parts are in `extensions/consent_kit/LICENSE` and
  `extensions/consent_kit/THIRD-PARTY-NOTICES.md`. The optional Open Cookie Database (Apache-2.0) of the add-on is not
  used and not bundled.

## 1. Browser assets in `public/assets` (Bundled)

Copied by `tools/build.mjs` / `tools/icons.mjs` from the npm packages in `tools/package.json`. The license texts
are written next to the files by the build.

| Component | Version | License | Shipped as | License text |
|---|---|---|---|---|
| Editor.js (`@editorjs/editorjs`), © CodeX | 2.31.7 | Apache-2.0 | `public/assets/vendor/editorjs/editorjs.umd.js` | `LICENSE-editorjs.txt` |
| – bundled: `@editorjs/caret` 1.1.0, `@editorjs/dom` 1.1.0, `@editorjs/helpers` 1.2.2, `codex-notifier` 1.1.2, `codex-tooltip` 1.0.6 | | MIT | inside `editorjs.umd.js` | `THIRD-PARTY-LICENSES.txt` |
| editorjs-drag-drop, © 2021 kommitters Open Source | 1.1.16 | MIT | `public/assets/vendor/editorjs/drag-drop.js` | `LICENSE-drag-drop.txt` |
| MapLibre GL JS (`maplibre-gl`), © 2023 MapLibre contributors; contains code © 2020 Mapbox (BSD-3-Clause), glfx.js © 2011 Evan Wallace (MIT), d3-color © 2010-2016 Mike Bostock (BSD-3-Clause) | 6.11.2 | BSD-3-Clause | `public/assets/vendor/maplibre/` | `LICENSE.txt` |
| – bundled: @mapbox/point-geometry, @maplibre/geojson-vt, @maplibre/maplibre-gl-style-spec, earcut, kdbush, potpack, quickselect, tinyqueue (ISC); @mapbox/tiny-sdf, @mapbox/unitbezier (BSD-2-Clause); @mapbox/vector-tile, pbf (BSD-3-Clause); @maplibre/mlt (MIT OR Apache-2.0); @maplibre/vt-pbf, bidi-js, gl-matrix, murmurhash-js, @mapbox/jsonlint-lines-primitives, json-stringify-pretty-compact and others (MIT) | | see left | inside the MapLibre bundle | `THIRD-PARTY-LICENSES.txt` (generated) |
| Mozilla PDF.js (`pdfjs-dist`), © Mozilla Foundation | 6.3.289 | Apache-2.0 | `public/assets/vendor/pdfjs/` | `LICENSE.txt` |
| Lato (`@fontsource/lato`), © 2010-2011 tyPoland Łukasz Dziedzic, Reserved Font Name “Lato” | 5.3.0 | OFL-1.1 | `public/assets/fonts/lato-*.woff2` (admin UI) | `public/assets/fonts/OFL-Lato.txt` |
| Phosphor Icons, duotone (`@phosphor-icons/core`), © 2023 Phosphor Icons | 2.1.1 | MIT | `public/assets/icons/*.svg` (core, one sprite per topic, `icons.svg`), `sites/*.svg` (generated per website), `icons-map.json`, `catalog.json` | `public/assets/icons/LICENSE.txt` |

Phosphor: the sprites are built by `tools/icons.mjs` from the curated list in `resources/icons/icons.json`; path data
is rounded to whole units of the 256 grid, shapes are otherwise unchanged.

Brand logos in the icon topic “Social Web” (e.g. Facebook, Instagram, LinkedIn, X/Twitter, Mastodon, Threads, YouTube,
TikTok, WhatsApp, Telegram, Pinterest, GitHub, Discord, Spotify) are drawn by Phosphor Icons and licensed under MIT as
artwork only. The names and logos are trademarks of their respective owners; the MIT license grants no trademark rights.
Use them only to link to the respective service (e.g. a profile link), unaltered and without suggesting endorsement or
partnership, and follow the owners' brand guidelines.

### 1.3 Components bundled inside PDF.js (`public/assets/vendor/pdfjs/`)

| Component | License | License text |
|---|---|---|
| CMaps (`cmaps/*.bcmap`), © 1990-2009 Adobe Systems Incorporated | BSD-3-Clause-style (Adobe) | `cmaps/LICENSE` |
| Foxit standard fonts (`standard_fonts/Foxit*.pfb`), © 2014 PDFium Authors | BSD-3-Clause | `standard_fonts/LICENSE_FOXIT` |
| Liberation Sans (`standard_fonts/LiberationSans-*.ttf`), © 2007-2011 Red Hat, Inc. | GNU GPL v2 with font embedding exception (Red Hat Liberation font license) | `standard_fonts/LICENSE_LIBERATION` |
| JBIG2 decoder (`wasm/jbig2*`), © 2014 The PDFium Authors; PDF.js wrapper | BSD-3-Clause; wrapper Apache-2.0 | `wasm/LICENSE_JBIG2`, `wasm/LICENSE_PDFJS_JBIG2` |
| OpenJPEG (`wasm/openjpeg*`); PDF.js wrapper © 2024 Mozilla Foundation | BSD-2-Clause; wrapper BSD | `wasm/LICENSE_OPENJPEG`, `wasm/LICENSE_PDFJS_OPENJPEG` |
| qcms (`wasm/qcms_bg.wasm`), © 2009-2024 Mozilla Corporation, © 1998-2007 Marti Maria | MIT | `wasm/LICENSE_QCMS`, `wasm/LICENSE_PDFJS_QCMS` |
| QuickJS (`wasm/quickjs-eval.*`), © 2017-2021 Fabrice Bellard, Charlie Gordon; build scripts © Mozilla Foundation (mozilla/pdf.js.quickjs) | MIT | `wasm/LICENSE_QUICKJS` (added by `tools/build.mjs` from `resources/licenses/`, because pdfjs-dist ships none) |
| ICC colour profile (`iccs/*.icc`) | CC0-1.0 | `iccs/LICENSE` |

The Liberation fonts are separate font files that PDF.js loads to render PDFs without embedded fonts. They are not
linked into KLXM Studio code (mere aggregation); the license text ships next to them. The TTF files are their own
source form. Their GPL-2.0 license (with font exception) applies only to these font files and does not affect the
MIT license of KLXM Studio's code; whoever redistributes them keeps `LICENSE_LIBERATION` next to them.

## 2. Fonts (Bundled)

All fonts are self-hosted (no requests to Google Fonts) and licensed under the **SIL Open Font License 1.1**
(`OFL-1.1`). The license text ships next to the files; fonts can be used, bundled and redistributed with any
software, but may not be sold on their own, and modified versions must not use Reserved Font Names. The npm
packages are published by Fontsource (`@fontsource/*`, `@fontsource-variable/*`: `OFL-1.1`) and Expo
(`@expo-google-fonts/*`: package code MIT, font files OFL-1.1). Copyright lines as in the fonts' own license
files or name tables:

| Kit | Font | Package (version) | Copyright | License text |
|---|---|---|---|---|
| Admin (core) | Lato | `@fontsource/lato` 5.3.0 | © 2010-2011 tyPoland Łukasz Dziedzic, RFN “Lato” | `public/assets/fonts/OFL-Lato.txt` |
| praxis | Hanken Grotesk | `@fontsource/hanken-grotesk` 5.3.0 | © 2021 The Hanken Grotesk Project Authors | `public/themes/praxis/fonts/OFL.txt` |
| praxis | Hanken Grotesk TTF (server-side, app icons – not public) | `@expo-google-fonts/hanken-grotesk` 0.4.3 | as above | `themes/praxis/fonts/OFL.txt` |
| basis | Inter | `@fontsource/inter` 5.3.0 | © 2016 The Inter Project Authors | `public/themes/basis/fonts/OFL-inter.txt` |
| basis | Manrope | `@fontsource/manrope` 5.3.0 | © 2019 The Manrope Project Authors | `…/OFL-manrope.txt` |
| basis | IBM Plex Sans | `@fontsource/ibm-plex-sans` 5.3.0 | © 2019 IBM Corp. | `…/OFL-plex.txt` |
| basis | Source Serif 4 | `@fontsource/source-serif-4` 5.3.0 | © 2014-2021 Adobe Systems Incorporated, RFN “Source” | `…/OFL-source-serif.txt` |
| basis | Lora | `@fontsource/lora` 5.3.0 | © 2011 The Lora Project Authors, RFN “Lora” | `…/OFL-lora.txt` |
| basis | Fraunces | `@fontsource/fraunces` 5.3.0 | © 2020 The Fraunces Project Authors | `…/OFL-fraunces.txt` |
| basis | Inter TTF (server-side, app icons – not public) | `@expo-google-fonts/inter` 0.4.2 | © 2020 The Inter Project Authors | `themes/basis/fonts/OFL.txt` |
| editorial | Fraunces (variable) | `@fontsource-variable/fraunces` 5.3.0 | © 2020 The Fraunces Project Authors | `public/themes/editorial/fonts/OFL-fraunces.txt` |
| editorial | IBM Plex Sans (variable) | `@fontsource-variable/ibm-plex-sans` 5.3.0 | © 2019 IBM Corp. | `…/OFL-plex-sans.txt` |
| editorial | Inter (variable) | `@fontsource-variable/inter` 5.3.0 | © 2016 The Inter Project Authors | `…/OFL-inter.txt` |
| editorial | JetBrains Mono (variable) | `@fontsource-variable/jetbrains-mono` 5.3.0 | © 2020 The JetBrains Mono Project Authors | `…/OFL-jetbrains.txt` |
| editorial | Libre Franklin (variable) | `@fontsource-variable/libre-franklin` 5.3.0 | © 2020 The Libre Franklin Project Authors | `…/OFL-franklin.txt` |
| editorial | Literata (variable) | `@fontsource-variable/literata` 5.3.0 | © 2017 The Literata Project Authors | `…/OFL-literata.txt` |
| editorial | Newsreader (variable) | `@fontsource-variable/newsreader` 5.3.0 | © 2020 The Newsreader Project Authors | `…/OFL-newsreader.txt` |
| editorial | Playfair Display (variable) | `@fontsource-variable/playfair-display` 5.3.0 | © 2017 The Playfair Display Project Authors, RFN “Playfair Display” | `…/OFL-playfair.txt` |
| editorial | Public Sans (variable) | `@fontsource-variable/public-sans` 5.3.0 | © 2015 The Public Sans Project Authors | `…/OFL-public-sans.txt` |
| editorial | Source Sans 3 (variable) | `@fontsource-variable/source-sans-3` 5.3.0 | © 2023 Adobe, RFN “Source” | `…/OFL-source-sans.txt` |
| editorial | Source Serif 4 (variable) | `@fontsource-variable/source-serif-4` 5.3.0 | © 2014-2021 Adobe Systems Incorporated, RFN “Source” | `…/OFL-source-serif.txt` |
| editorial | Work Sans (variable) | `@fontsource-variable/work-sans` 5.3.0 | © 2019 The Work Sans Project Authors | `…/OFL-work-sans.txt` |
| editorial | DM Serif Display | `@fontsource/dm-serif-display` 5.3.0 | © 2014-2017 Adobe Systems Incorporated, RFN “Source”; © 2019 Google LLC | `…/OFL-dm-serif.txt` |
| editorial | IBM Plex Mono | `@fontsource/ibm-plex-mono` 5.3.0 | © 2017 IBM Corp. | `…/OFL-plex-mono.txt` |
| editorial | Instrument Serif | `@fontsource/instrument-serif` 5.3.0 | © 2022 The Instrument Serif Project Authors | `…/OFL-instrument.txt` |
| editorial | Playfair Display TTF (server-side, app icons and placeholders – not public) | `@expo-google-fonts/playfair-display` 0.4.2 | © 2017 The Playfair Display Project Authors, RFN “Playfair Display” | `themes/editorial/fonts/OFL.txt` |
| fluid | Inter (variable) | `@fontsource-variable/inter` 5.3.0 | © 2016 The Inter Project Authors | `public/themes/fluid/fonts/OFL-inter.txt` |
| fluid | Instrument Sans (variable) | `@fontsource-variable/instrument-sans` 5.3.0 | © 2022 The Instrument Sans Project Authors | `…/OFL-instrument-sans.txt` |
| fluid | Bricolage Grotesque (variable) | `@fontsource-variable/bricolage-grotesque` 5.3.0 | © 2022 The Bricolage Grotesque Project Authors | `…/OFL-bricolage.txt` |
| fluid | DM Sans (variable) | `@fontsource-variable/dm-sans` 5.3.0 | © 2014 The DM Sans Project Authors | `…/OFL-dm-sans.txt` |
| fluid | Space Grotesk (variable) | `@fontsource-variable/space-grotesk` 5.3.0 | © 2020 The Space Grotesk Project Authors | `…/OFL-space-grotesk.txt` |
| fluid | Fraunces (variable) | `@fontsource-variable/fraunces` 5.3.0 | © 2020 The Fraunces Project Authors | `…/OFL-fraunces.txt` |
| fluid | Newsreader (variable) | `@fontsource-variable/newsreader` 5.3.0 | © 2020 The Newsreader Project Authors | `…/OFL-newsreader.txt` |
| fluid | Instrument Serif | `@fontsource/instrument-serif` 5.3.0 | © 2022 The Instrument Serif Project Authors | `…/OFL-instrument-serif.txt` |
| fluid | JetBrains Mono (variable) | `@fontsource-variable/jetbrains-mono` 5.3.0 | © 2020 The JetBrains Mono Project Authors | `…/OFL-jetbrains-mono.txt` |
| fluid | Inter TTF (server-side, app icons – not public) | `@expo-google-fonts/inter` 0.4.2 | © 2020 The Inter Project Authors | `themes/fluid/fonts/OFL.txt` |
| nature | Fraunces (variable: wght + SOFT, italic wght) | `@fontsource-variable/fraunces` 5.3.0 | © 2020 The Fraunces Project Authors | `public/themes/nature/fonts/OFL-fraunces.txt` |
| nature | Nunito Sans (variable) | `@fontsource-variable/nunito-sans` 5.3.0 | © 2016 The Nunito Sans Project Authors | `…/OFL-nunito-sans.txt` |
| nature | Young Serif | `@fontsource/young-serif` 5.3.0 | © 2023 The Young Serif Project Authors | `…/OFL-young-serif.txt` |
| nature | Source Sans 3 (variable) | `@fontsource-variable/source-sans-3` 5.3.0 | © 2023 Adobe, RFN “Source” | `…/OFL-source-sans-3.txt` |
| nature | Fraunces TTF (server-side, app icons – not public) | `@expo-google-fonts/fraunces` 0.4.1 | © 2020 The Fraunces Project Authors | `themes/nature/fonts/OFL.txt` |
| modern | Plus Jakarta Sans (variable) | `@fontsource-variable/plus-jakarta-sans` 5.3.0 | © 2020 The Plus Jakarta Sans Project Authors | `public/themes/modern/fonts/OFL-jakarta.txt` |
| modern | Space Grotesk (variable) | `@fontsource-variable/space-grotesk` 5.3.0 | © 2020 The Space Grotesk Project Authors | `…/OFL-space-grotesk.txt` |
| modern | Inter Tight (variable) | `@fontsource-variable/inter-tight` 5.3.0 | © 2022 The Inter Project Authors | `…/OFL-inter-tight.txt` |
| modern | Manrope (variable) | `@fontsource-variable/manrope` 5.3.0 | © 2019 The Manrope Project Authors | `…/OFL-manrope.txt` |
| modern | Space Grotesk TTF (server-side, app icons – not public) | `@expo-google-fonts/space-grotesk` 0.4.1 | © 2020 The Space Grotesk Project Authors | `themes/modern/fonts/OFL.txt` |
| glas | Outfit (variable) | `@fontsource-variable/outfit` 5.3.0 | © 2021 The Outfit Project Authors | `public/themes/glas/fonts/OFL-outfit.txt` |
| glas | Figtree (variable) | `@fontsource-variable/figtree` 5.3.0 | © 2022 The Figtree Project Authors | `…/OFL-figtree.txt` |
| glas | Sora (variable) | `@fontsource-variable/sora` 5.3.0 | © 2019 The Sora Project Authors | `…/OFL-sora.txt` |
| glas | Urbanist (variable) | `@fontsource-variable/urbanist` 5.3.0 | © 2021 The Urbanist Project Authors | `…/OFL-urbanist.txt` |
| glas | Outfit TTF (server-side, app icons – not public) | `@expo-google-fonts/outfit` 0.4.3 | © 2021 The Outfit Project Authors | `themes/glas/fonts/OFL.txt` |

RFN = Reserved Font Name. PDF.js additionally ships the Foxit and Liberation fonts listed in 1.3.

### 2.1 Installed fonts (Runtime, not bundled)

Administrators can install further fonts from the Google Fonts catalog (Grundeinstellungen → Schriften,
`php bin/console fonts:install`, `Core\Fonts`). They are downloaded once by the server (Fontsource API
`api.fontsource.org`, files from `cdn.jsdelivr.net/fontsource`, fallback Google Fonts CSS2 API) and served from
`public/fonts/{id}/` – visitors never contact Google, Fontsource or jsDelivr. Only `OFL-1.1`, `Apache-2.0` and
`UFL-1.0` fonts can be installed; each installation stores the package's license text as
`public/fonts/{id}/LICENSE.txt` and its license and copyright line in `public/fonts/fonts.json`. They are not part of
the distribution and therefore not listed here; the in-product page “Lizenzen & Danksagungen” lists them
automatically in the section “Installierte Schriften”.

## 3. PHP packages in `vendor/` (Bundled, Composer)

All packages from `composer.lock` (there are no dev packages). “via” names the package that requires it.

| Package | Version | License | Required | URL |
|---|---|---|---|---|
| chillerlan/php-qrcode | 6.0.1 | MIT OR Apache-2.0 | direct | https://github.com/chillerlan/php-qrcode |
| chillerlan/php-settings-container | 3.3.0 | MIT | via chillerlan/php-qrcode | https://github.com/chillerlan/php-settings-container |
| doctrine/dbal | 4.5.0 | MIT | direct | https://www.doctrine-project.org/projects/dbal.html |
| doctrine/deprecations | 1.1.6 | MIT | via doctrine/dbal, phpdocumentor/reflection-docblock | https://www.doctrine-project.org/ |
| doctrine/lexer | 3.0.2 | MIT | via egulias/email-validator, loupe/loupe | https://www.doctrine-project.org/projects/lexer.html |
| egulias/email-validator | 4.0.4 | MIT | via symfony/mailer | https://github.com/egulias/EmailValidator |
| lbuchs/webauthn | 2.2.0 | MIT | direct | https://github.com/lbuchs/webauthn |
| loupe/loupe | 1.1.0 | MIT | direct | https://github.com/loupe-php/loupe |
| loupe/matcher | 0.4.0 | MIT | via loupe/loupe | https://github.com/loupe-php/matcher |
| mjaschen/phpgeo | 6.0.4 | MIT | via loupe/loupe | https://phpgeo.marcusjaschen.de/ |
| oskarstark/enum-helper | 1.8.4 | MIT | via symfony/ai-platform | https://github.com/OskarStark/enum-helper |
| phpdocumentor/reflection-common | 2.2.0 | MIT | via phpdocumentor/reflection-docblock, phpdocumentor/type-resolver | http://www.phpdoc.org |
| phpdocumentor/reflection-docblock | 6.0.3 | MIT | via symfony/ai-platform | https://github.com/phpDocumentor/ReflectionDocBlock |
| phpdocumentor/type-resolver | 2.0.0 | MIT | via phpdocumentor/reflection-docblock | https://github.com/phpDocumentor/TypeResolver |
| phpstan/phpdoc-parser | 2.3.5 | MIT | via phpdocumentor/reflection-docblock, phpdocumentor/type-resolver | https://github.com/phpstan/phpdoc-parser |
| psr/cache | 3.0.0 | MIT | via doctrine/dbal, loupe/loupe | https://github.com/php-fig/cache |
| psr/clock | 1.0.0 | MIT | via symfony/clock | https://github.com/php-fig/clock |
| psr/container | 2.0.2 | MIT | via symfony/service-contracts, symfony/type-info | https://github.com/php-fig/container |
| psr/event-dispatcher | 1.0.0 | MIT | via symfony/event-dispatcher-contracts, symfony/mailer | https://github.com/php-fig/event-dispatcher |
| psr/log | 3.0.2 | MIT | via doctrine/dbal, loupe/loupe | https://github.com/php-fig/log |
| rlanvin/php-rrule | 3.0.0 | MIT | direct | https://github.com/rlanvin/php-rrule |
| sabre/dav | 4.7.1 | BSD-3-Clause | direct | http://sabre.io/ |
| sabre/event | 5.1.9 | BSD-3-Clause | via sabre/dav, sabre/http | http://sabre.io/event/ |
| sabre/http | 5.1.13 | BSD-3-Clause | via sabre/dav | https://github.com/fruux/sabre-http |
| sabre/uri | 2.3.4 | BSD-3-Clause | via sabre/dav, sabre/http | http://sabre.io/uri/ |
| sabre/vobject | 4.6.1 | BSD-3-Clause | via sabre/dav | http://sabre.io/vobject/ |
| sabre/xml | 2.2.11 | BSD-3-Clause | via sabre/dav, sabre/vobject | https://sabre.io/xml/ |
| symfony/ai-generic-platform | 0.14.0 | MIT | direct | https://github.com/symfony/ai-generic-platform |
| symfony/ai-mistral-platform | 0.14.0 | MIT | direct | https://github.com/symfony/ai-mistral-platform |
| symfony/ai-ollama-platform | 0.14.0 | MIT | direct | https://github.com/symfony/ai-ollama-platform |
| symfony/ai-open-ai-platform | 0.14.0 | MIT | direct | https://github.com/symfony/ai-open-ai-platform |
| symfony/ai-open-responses-platform | 0.14.0 | MIT | via symfony/ai-open-ai-platform | https://github.com/symfony/ai-open-responses-platform |
| symfony/ai-platform | 0.14.0 | MIT | direct | https://github.com/symfony/ai-platform |
| symfony/ai-store | 0.14.0 | MIT | direct | https://github.com/symfony/ai-store |
| symfony/clock | 8.1.0 | MIT | via symfony/ai-platform, symfony/ai-store | https://symfony.com |
| symfony/deprecation-contracts | 3.7.1 | MIT | via symfony/service-contracts | https://symfony.com |
| symfony/event-dispatcher | 8.1.5 | MIT | via symfony/ai-platform, symfony/mailer | https://symfony.com |
| symfony/event-dispatcher-contracts | 3.7.1 | MIT | via symfony/ai-store, symfony/event-dispatcher | https://symfony.com |
| symfony/http-client | 8.1.7 | MIT | direct | https://symfony.com |
| symfony/http-client-contracts | 3.7.3 | MIT | via symfony/http-client | https://symfony.com |
| symfony/mailer | 8.1.7 | MIT | direct | https://symfony.com |
| symfony/mime | 8.1.7 | MIT | via symfony/mailer | https://symfony.com |
| symfony/polyfill-ctype | 1.37.0 | MIT | via symfony/serializer, symfony/string | https://symfony.com |
| symfony/polyfill-intl-grapheme | 1.41.0 | MIT | via symfony/string | https://symfony.com |
| symfony/polyfill-intl-idn | 1.42.0 | MIT | via egulias/email-validator, symfony/mime | https://symfony.com |
| symfony/polyfill-intl-normalizer | 1.42.0 | MIT | via symfony/polyfill-intl-idn, symfony/string | https://symfony.com |
| symfony/polyfill-mbstring | 1.38.2 | MIT | via symfony/mime | https://symfony.com |
| symfony/polyfill-php83 | 1.41.0 | MIT | via symfony/ai-store | https://symfony.com |
| symfony/polyfill-uuid | 1.37.0 | MIT | via symfony/uid | https://symfony.com |
| symfony/property-access | 8.1.4 | MIT | via symfony/ai-platform | https://symfony.com |
| symfony/property-info | 8.1.7 | MIT | via symfony/ai-platform, symfony/property-access | https://symfony.com |
| symfony/serializer | 8.1.7 | MIT | via symfony/ai-platform | https://symfony.com |
| symfony/service-contracts | 3.7.3 | MIT | via symfony/ai-store, symfony/http-client | https://symfony.com |
| symfony/string | 8.1.7 | MIT | via symfony/property-info | https://symfony.com |
| symfony/type-info | 8.1.5 | MIT | via symfony/ai-platform, symfony/property-info | https://symfony.com |
| symfony/uid | 8.1.5 | MIT | via symfony/ai-platform, symfony/ai-store | https://symfony.com |
| toflar/fast-set | 1.0.1 | MIT | via loupe/matcher | https://github.com/Toflar/FastSet |
| toflar/state-set-index | 3.3.0 | MIT | via loupe/loupe | https://github.com/Toflar/state-set-index |
| wamania/php-stemmer | 4.0.0 | MIT | via loupe/loupe | https://github.com/wamania/php-stemmer |
| webmozart/assert | 2.4.1 | MIT | via phpdocumentor/reflection-docblock | https://github.com/webmozarts/assert |

Notes:

- **joomla/string is not bundled.** `wamania/php-stemmer` (MIT, used by `loupe/loupe` for stemming) requires
  `joomla/string` (GPL-2.0-or-later) but only calls five UTF-8 helpers (`strlen`, `substr`, `strpos`, `strrpos`,
  `strtolower`). `composer.json → replace` drops the package; `lib/compat/joomla-string/StringHelper.php` is an
  independent MIT implementation of those methods on top of ext-mbstring (not derived from Joomla code). Stemming
  results were verified identical before/after (35 words, 6 languages).
- `wamania/php-stemmer` itself contains `src/Transliterate.php` (used only by its Spanish stemmer), described by its
  author as a copy of Joomla's port of phputf8's `utf8_accents_to_ascii()`; the package is published as MIT.
- `nitotm/efficient-language-detector` (a dependency of loupe/loupe, ≈ 170 MB of data) is excluded via
  `composer.json → replace` and is **not** installed: every search index has exactly one language.
- The CalDAV/CardDAV extension (`extensions/dav`) uses `sabre/dav` and the `sabre/*` packages (BSD-3-Clause,
  © 2007-2016 fruux GmbH) from the root `vendor/`; it has no dependencies of its own.
- `lbuchs/webauthn` (MIT, © Lukas Buchs) provides passkey sign-in.

## 4. Build-only tools (not deployed)

| Tool | Version | License | Purpose |
|---|---|---|---|
| esbuild | 0.25.12 | MIT | Minifying CSS/JS (`tools/build.mjs`) |
| Playwright / playwright-core, © Microsoft Corporation | 1.62.1 | Apache-2.0 | Recording the tutorial videos (`tools/tutorials/record.mjs`); downloads its own browser builds |
| @napi-rs/canvas | 1.0.9 | MIT | Optional dependency of pdfjs-dist, not used at runtime |
| Piper TTS (`piper-tts`, OHF-Voice/piper1-gpl), incl. espeak-ng phonemizer | 1.8.0 | GPL-3.0-or-later | Tutorial narration (section 5), run locally via pipx |
| ffmpeg | operator's system | LGPL-2.1+/GPL-2.0+ depending on build | Encoding tutorial videos (build-only, output goes to the product website); also optional for transcription (section 6) |
| pnpm, Node.js | – | MIT | Package management/build |

## 5. Tutorial narration and videos

**The tutorial videos and the trailer are not part of the KLXM Studio distribution** (since 2026-09-26). They are
published on the product website https://studio.klxm.de (`/tutorials`, trailer on the home page); the admin only links
there (config `docs_url`) and makes no request to it. The videos (MP4/WebM, posters, WebVTT subtitles in German and
English) are screen recordings of KLXM Studio made with the build-only tools `tools/tutorials/record.mjs` and
`tools/trailer/trailer.mjs` (output: `TUT_OUT`/`TRAILER_OUT`, by default the website install). They are
**project-owned** (© KLXM Crossmedia GmbH and contributors, MIT). The step texts of the tutorials remain in the
distribution as text (`app/Admin/tutorials.php`, `app/Admin/tutorials.en.php`).

**Since 2026-09-25 all videos are silent** (no audio track; captions only). **No synthesized voice and no voice data
are shipped** any more, and no speaker credit is required. The rest of this section documents the optional,
build-only narration tooling (`record.mjs --voice`, `tools/tutorials/speech.mjs`, `narrate.py`,
`narrate_chatterbox.py`, `voice-check.mjs`) for a possible later voiced version – check the licenses below again and
add a credit line to the tutorial pages before publishing any voiced video.

**Piper TTS** (GPL-3.0-or-later, build-only; generated audio is program output and not covered by Piper's license)
with these voice models, downloaded by `tools/tutorials/fetch-voices.sh` from https://huggingface.co/rhasspy/piper-voices
(repository license: MIT, revision `c10ece1aade47bb51c153c893d14e5bf8e5b7117`). The models are not deployed.

| Voice | Language | Model license | Training data (model card) | Dataset license |
|---|---|---|---|---|
| `de_DE-eva_k-x_low` | German | MIT (rhasspy/piper-voices) | M-AILABS Speech Dataset, https://www.caito.de/2019/01/03/the-m-ailabs-speech-dataset/ | Copyright (c) 2017-2019 by the original creators @ M-AILABS; redistribution and use, including commercial use, permitted with copyright notice and disclaimer retained (BSD-style) |
| `en_US-ljspeech-high` | English (US) | MIT (rhasspy/piper-voices) | LJ Speech Dataset, https://keithito.com/LJ-Speech-Dataset/ | Public domain |

Credit to show if voiced videos are published again: “Narration: Piper TTS – voices ‘eva_k’ (M-AILABS Speech Dataset,
© 2017-2019 M-AILABS) and ‘ljspeech’ (LJ Speech Dataset, public domain).”

M-AILABS license notice (reproduced in full, relevant only for voiced output):

> Copyright (c) 2017-2019 by the original creators @ M-AILABS with the following license: Redistribution and use
> in any form, including any commercial use, with or without modification are permitted provided that the
> following conditions are met: Redistributions of source data must retain the above copyright notice, this list
> of conditions and the following disclaimer. Neither the name of the copyright holder nor the names of its
> contributors may be used to endorse or promote products derived from this downloaded data, source-code or
> binary-code without specific prior written permission. THIS DATA IS PROVIDED BY THE COPYRIGHT HOLDERS AND
> CONTRIBUTORS 'AS IS' AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
> WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
> COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
> CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
> DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
> STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE
> and/or DATA, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

LJ Speech: “This dataset is in the public domain in the US (and most likely other countries as well). There are no
restrictions on its use.” (Keith Ito, Linda Johnson, 2017). Both models were trained from scratch (model cards).

Voices deliberately **not** used: models whose training data or base checkpoint is licensed for non-commercial
research only – `lessac` (Blizzard 2013 research license), `ryan` (CC BY-NC-SA 4.0), `pavoque` (CC BY-NC-SA 4.0) and
every voice fine-tuned from them, including `de_DE-thorsten-medium/-high` (Thorsten-Voice data is CC0, but these
models are fine-tuned from the `lessac` checkpoint) and `de_DE-kerstin-low`/`karlsson-low` (fine-tuned from `ryan`).

### 5.1 Candidate: Chatterbox Multilingual (evaluation only, not in use)

A re-voiced trailer **candidate** was generated locally (removed again; nothing of it is shipped) with **Chatterbox
Multilingual** by Resemble AI (`chatterbox-tts` 0.1.7, MIT, https://github.com/resemble-ai/chatterbox), weights
`ResembleAI/chatterbox` (MIT, Hugging Face revision `5bb1f6ee58e50c3b8d408bc82a6d3740c2db6e18`, files
`t3_mtl23ls_v2.safetensors`, `s3gen.pt`, `ve.pt`, `conds.pt`, tokenizer JSON). Voice: the model's **built-in default
voice** (`conds.pt`); no voice cloning, no reference recording of any person. Build-only: a Python venv in
`tools/tutorials/.venv-chatterbox` (git-ignored) and the Hugging Face cache; nothing of it is deployed, only the
generated audio would be part of the video. Key dependencies (build-only): PyTorch/torchaudio 2.6.0 (BSD-3-Clause),
`resemble-perth` 1.0.1 (MIT), transformers 5.2.0 and diffusers 0.29.0 (Apache-2.0), s3tokenizer 0.3.0 (Apache-2.0),
conformer 0.3.2 (MIT), librosa 0.11.0 (ISC), safetensors 0.5.3 (Apache-2.0), numpy 1.26.4 (BSD-3-Clause).
The model card does not itemize the training data.

**German reference voice (samples only, not in use):** For a native German voice, Chatterbox can clone a reference
recording (`CB_REF`). Samples were made with a 15.8 s reference built from Thorsten-Voice
**“TV-24kHz-2025.12-Neutral-FT-Mini”** by Thorsten Müller (https://huggingface.co/datasets/Thorsten-Voice/TV-24kHz-2025.12-Neutral-FT-Mini,
revision `afacb9d80927c36307dc16991c99b927ffa0a702`, **CC0 1.0**: “The speaker explicitly dedicates these recordings to
the public domain for unrestricted use.”). Used clips: `57` and `58` (neutral read speech), joined, normalized, 24 kHz mono
→ `tools/tutorials/voices/ref-thorsten.wav` (not versioned, not deployed). Note: the larger Thorsten-Voice datasets on
Zenodo (2021.02, 2022.10, emotional, Hessisch) and `TV-24kHz-Neutral` are published under **CC BY 4.0**, not CC0 – they
were not used. Credit if adopted (courtesy, not required by CC0): “Voice: Thorsten-Voice (Thorsten Müller), CC0”.
A reference of the user's own voice (`ref-own.wav`) requires a written consent note next to the file.

**Watermark:** Chatterbox embeds Resemble AI's **PerTh** watermark (imperceptible, neural “perceptual threshold”
watermark, `resemble-perth`) into every generated clip, so the audio of a Chatterbox-voiced video is detectable as
synthetic speech. This is intended and stays in; it has no audible effect. Should the candidate be adopted, this
section moves to the credit line (“Narration: Chatterbox Multilingual by Resemble AI, built-in voice, MIT; audio
carries an imperceptible PerTh watermark”).

## 6. AI models and runtimes (Optional, not bundled)

KLXM Ai features are off unless the operator configures a provider. Nothing below ships with KLXM Studio.

| Component | License | Notes |
|---|---|---|
| whisper.cpp (`whisper-cli`), © The ggml authors | MIT | Local transcription; installed by the operator |
| Whisper model weights, e.g. `ggml-large-v3-turbo-q5_0.bin` (OpenAI Whisper large-v3-turbo, ggml conversion by ggerganov/whisper.cpp) | MIT (“Whisper's code and model weights are released under the MIT License”, © 2022 OpenAI) | Downloaded by the operator to `storage/ai/models/` |
| Ollama | MIT | Local LLM server run by the operator |
| Ollama models (e.g. gemma3, bge-m3, nomic-embed-text) | per model (e.g. Gemma Terms of Use, MIT, Apache-2.0) | Chosen and downloaded by the operator; check each model's license |
| Mistral, OpenAI or other OpenAI-compatible APIs | provider terms | External services configured by the operator |
| ffmpeg | LGPL-2.1+/GPL-2.0+ depending on build | Audio extraction for transcription |

## 7. Online services and data (Service)

- **OpenStreetMap data via OpenFreeMap** (`tiles.openfreemap.org`): map styles, vector tiles, sprites and glyphs,
  fetched server-side through the built-in proxy (`/proxy/ofm/…`). Map data © OpenStreetMap contributors, licensed
  under the Open Database License (ODbL 1.0); vector tile schema © OpenMapTiles; hosting by OpenFreeMap. Required
  attribution (supplied by the tile source and shown by the MapLibre attribution control on every map):
  “OpenFreeMap © OpenMapTiles Data from OpenStreetMap”, linking to https://openfreemap.org,
  https://www.openmaptiles.org/ and https://www.openstreetmap.org/copyright. Do not remove or hide this control.
  The “liberty” style also uses Natural Earth shaded relief (public domain).
- **Nominatim** (`nominatim.openstreetmap.org`): address search in the admin area (and the `geocode` API/MCP
  tool), server-side only, sent with an identifying User-Agent (`KLXM Studio/<version> (+site URL)`) and only on
  explicit searches. The Nominatim Usage Policy applies (https://operations.osmfoundation.org/policies/nominatim/:
  max. 1 request per second, no bulk or systematic geocoding). Results are © OpenStreetMap contributors (ODbL).
- **YouTube / Vimeo**: preview images are fetched server-side; players load only after the visitor clicks.

## 8. Media in demo content

- **“Big Buck Bunny”** – © 2008 Blender Foundation / www.bigbuckbunny.org, licensed under Creative Commons
  Attribution 3.0 (https://creativecommons.org/licenses/by/3.0/). Used as the example YouTube video
  (`aqz-KE-bpKQ`) in the demo content of the basis, editorial and fluid kits (`themes/*/tools/demo-content.php`);
  the video itself is not bundled – only a cached preview frame is stored at runtime in `public/media/embeds/`. The
  attribution is part of the demo block text and caption: “Big Buck Bunny” © 2008 Blender Foundation /
  www.bigbuckbunny.org – CC BY 3.0.
- Demo and placeholder images, the demo PDF, app icons and the screenshots in `public/assets/docs/` are generated
  by KLXM Studio (GD, `themes/*/tools/demo-content.php`) or recorded from it and are project-owned
  (MIT). All names in the demo content are fictitious.

## 9. Checking licenses

`node tools/licenses.mjs` (or `pnpm --dir tools licenses`) reads `composer.lock` and the installed npm packages of
`tools/` and every `themes/*` package. It separates **bundled** packages (Composer `require`, npm packages built
into `public/`) from **build-only** tools (`esbuild`, `playwright` and their dependencies) and
fails on bundled strong copyleft (GPL, AGPL, LGPL, EUPL, OSL, CC-BY-SA – incompatible with shipping the project under
MIT), on non-free licenses anywhere (SSPL, `*-NC`, `*-ND`, proprietary/UNLICENSED or missing license) and on missing
license files in `public/`. Bundled weak/file-level copyleft (MPL, EPL, CDDL) and unknown identifiers are reported
for review; copyleft build-only tools are listed as notes.
`--list` prints every package, `--json` the full inventory.
