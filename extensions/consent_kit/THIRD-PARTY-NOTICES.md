# Third-party notices – extension „consent_kit“

## FriendsOfREDAXO/consent_kit (MIT) – ported

- Source: https://github.com/FriendsOfREDAXO/consent_kit (1.0.0-beta3, commit f4777e7), developed by KLXM Crossmedia
  GmbH for Friends Of REDAXO.
- License: MIT – © 2026 Friends Of REDAXO, KLXM Crossmedia GmbH. Full text in `LICENSE` of this extension.
- Ported and adapted: 38 service templates (`presets/*.json`, unchanged except that they are loaded by
  `src/Presets.php`), `presets/FORMAT.md`, the web component `<consent-kit>`/`<consent-embed>` (rewritten for a strict
  CSP without inline scripts/styles and split into core + lazily loaded UI), the frontend texts, config building,
  variants/placeholders, fingerprints and revisions, conversion events and the log.
- Added by KLXM Studio (MIT): `presets/_csp.json` (CSP hosts per template), CSP integration, theme
  integration (2-click videos, footer link), design derivation from the site's design tokens, admin UI, block.

## Open Cookie Database (Apache-2.0) – not used, not bundled

The optional catalogue of the REDAXO add-on (https://github.com/jkwakman/Open-Cookie-Database) is not part of this
port and is not shipped.
