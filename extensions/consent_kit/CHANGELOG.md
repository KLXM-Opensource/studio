# Changelog – Erweiterung „consent_kit“

## 1.2.0 (2026-10-04)

Abgleich mit FriendsOfREDAXO/consent_kit 1.1.0:

- **Neu: „Hinweis beim Seitenaufruf“** (Einstellungen → Darstellung, `open_mode`): *Immer* (Standard, wie bisher), *Nur bei
  Bedarf* (nur wenn auf der Seite ein `<consent-embed>` eines bekannten, noch nicht erlaubten Dienstes steht) oder *Nie*
  (nur über Platzhalter, schwebende Schaltfläche oder den Link „Datenschutz-Einstellungen“). Bringt ein optionaler Dienst
  eigenen Code mit (head/body, js_accept, Ereignisse), erscheint der Hinweis bei „Nur bei Bedarf“ trotzdem – sonst würde
  er nie starten; „Nie“ unterdrückt ausnahmslos. GPC und „für diese Sitzung geschlossen“ haben weiter Vorrang, die Vorschau
  zeigt immer. Unbekannte Werte fallen auf „Immer“ zurück. Die Einstellungsseite warnt und nennt solche Dienste beim Namen.
- **Neu: Niederländisch und Italienisch** für alle Texte auf der Website (`lang/site/nl.php`, `it.php`) und in den
  Dienst-Vorlagen (Beschreibungen, Hinweise, Parameter) – gilt für neu angelegte Dienste. Die Zwecke der Cookie- und
  Storage-Einträge speichert die Erweiterung weiterhin deutsch/englisch; fehlt eine Sprache, gilt jetzt Englisch vor Deutsch.
- **Wording:** „Datenschutz-Einstellungen“ statt „Cookie-Einstellungen“ (Link im Fußbereich, schwebende Schaltfläche,
  Hinweistext, Platzhalter; en: „Privacy settings“) – der Dialog verwaltet Dienste und jede Art von Speicher. Im Kit
  überschriebene Texte bleiben; der Anker `#cookie-einstellungen` funktioniert weiter.
- Keine Datenbankänderung nötig; ohne Einstellung bleibt das Verhalten unverändert.

## 1.1.0 (2026-10-02)

Angepasst an die Integrationspunkte von KLXM Studio 1.0 (Entwicklerhandbuch → Erweiterungen → „Integrationspunkte und Regeln“):

- Verwaltung als Einstellungsseite (`adminPage`, Art `settings`): **Administration → Einstellungen → Cookie-Einwilligung**
  statt eigenem Menüpunkt; nur sichtbar mit Recht `consent.manage` und eingeschalteter Funktion `consent`. Adresse unverändert.
- Jede Verwaltungsroute nennt ihr Recht (`consent.manage`) – Anmeldung, Recht und CSRF prüft der Router vor dem Controller
  (`extensions:list` meldet keine Altform mehr).
- Datum im Protokoll und bei Ständen über `Core\Format` (Sprache der Verwaltung, „24.09.2026, 14:05“).
- `composer.json` (Paket `klxm/studio-consent-kit`, Typ `klxm-studio-extension`).
- Begriffe: „Kit“ statt „Theme“ in Kommentaren.

## 1.0.1 (2026-09-27)

Abgleich mit FriendsOfREDAXO/consent_kit 1.0.0 (commit c5f31ce):

- Platzhalter `<consent-embed>` auf schmalen Schirmen nicht mehr abgeschnitten: Mindesthöhe am Host, Platzhalter wächst
  mit seinem Inhalt (vorher lief der Text oben heraus bzw. schob den Platzhalter unter ~400 px über den Rand).
- Fehlender oder inaktiver Dienst: „Dieser Inhalt ist derzeit nicht verfügbar“ statt „einmal laden“ (vorher ließ sich der
  Inhalt ohne Angaben im Hinweis laden); Name aus der Vorlage, Hinweis für angemeldete Redakteure, Warnung in der Konsole.
  `consent_embed()` gibt dafür den Platzhalter aus statt nichts.
- Platzhaltertext: `{privacy}`, `{imprint}`, `{service_privacy}` werden zu Links.
- JS-API `cmsConsent.accept(key|keys)` für eigene 2-Klick-Lösungen (Protokoll-Aktion `embed`).
- Nicht übernommen, weil im Port nicht vorhanden bzw. schon gelöst: Cache-Buster für eigenes Stylesheet, `--ck-rem`
  (kein Kit verkleinert die Grundschrift), Backend-Filter mit `hidden`.

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
