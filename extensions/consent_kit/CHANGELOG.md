# Changelog – Erweiterung „consent_kit“

## 1.0.0 (2026-09-25)

- Erste Fassung für KLXM Studio: Port des REDAXO-AddOns consent_kit (MIT, 1.0.0-beta3).
- Dienste in Gruppen (Notwendig, Funktional, Statistik, Marketing, Externe Medien), 38 Vorlagen DE/EN, eigene Vorlagen
  (Import/Export im Format des AddOns), Domain-Matrix (Hauptdomain + Landing-Domains), Varianten je Domain/Sprache.
- Web Component mit nativem `<dialog>`, Shadow DOM, Box/Leiste/Dialog/Off-Canvas, gleichwertige Schaltflächen,
  `prefers-reduced-motion`, `forced-colors`, Farben aus den Design-Tokens der Website (hell/dunkel).
- Strikte CSP: kein Inline-Code; Dienst-Code als Datei der eigenen Domain; fremde Hosts erst nach Einwilligung in der
  CSP (einmaliges Neuladen); iframe-Hosts aktiver Dienste für den 2-Klick-Platzhalter.
- Integration mit den Video-Blöcken der Kits (`window.cmsConsent`), Block „Externer Inhalt (mit Einwilligung)“,
  „Cookie-Einstellungen“ im Fußbereich (`footer_links()`), `consent_settings_link()`, `consent_embed()`, `consent_has()`.
- Google Consent Mode v2 (Basic Mode), Global Privacy Control, Anbieter-Aufrufe (UET, Clarity, Meta) aus den Vorlagen,
  Conversions ohne Code.
- Protokoll ohne IP/User-Agent mit Stand-Schnappschuss, Kennzahlen, CSV-Export, Aufbewahrung + `consent:purge`.
- Design-Editor mit Live-Vorschau und Kontrastprüfung.
