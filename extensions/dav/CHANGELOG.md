# Changelog – Erweiterung „dav“

## 1.1.0 (2026-10-02)

Angepasst an die Integrationspunkte von KLXM Studio 1.0 (Entwicklerhandbuch → Erweiterungen → „Integrationspunkte und Regeln“):

- Persönliche Einrichtung über den Slot **Konto** (`account`): Abschnitt „Kalender & Kontakte in Apps“ mit Anzahl der eigenen
  App-Passwörter, letzter Nutzung und Link zur Seite – statt eigenem Menüpunkt im Hauptmenü.
- Einstellungen je Tabelle als Einstellungsseite (`adminPage`, Art `settings`, Recht `data.schema`) unter
  **Administration → Einstellungen**. Adresse `/admin/dav` unverändert.
- Jede Verwaltungsroute nennt ihr Recht (`dav.use` bzw. `data.schema` für die Tabellen-Einstellungen) – Anmeldung, Recht und
  CSRF prüft der Router vor dem Controller (`extensions:list` meldet keine Altform mehr).
- Datum der App-Passwörter über `Core\Format` (Sprache der Verwaltung).
- `composer.json` (Paket `klxm/studio-dav`, Typ `klxm-studio-extension`), Changelog.

## 1.0.0 (2026-09-25)

- Erste Fassung: CalDAV (Kalender-Tabellen) und CardDAV (beliebige Tabellen als Adressbuch) mit sabre/dav 4.7,
  App-Passwörter je Benutzer, Einstellungen je Tabelle.
