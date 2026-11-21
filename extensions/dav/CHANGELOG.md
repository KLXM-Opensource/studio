# Changelog – Erweiterung „dav“

## 1.2.0 (2026-11-21)

- **Namensraum umbenannt:** `MyCms\Dav\…` heißt jetzt `Klxm\Dav\…` (wie `Klxm\Members`, `Klxm\Motion`) – der Altname „MyCms“
  verschwindet aus dem Code. Für Website und Verwaltung ändert sich nichts; eigener Code, der Klassen der Erweiterung direkt aufruft,
  muss den neuen Namensraum verwenden.
- Die vCard-Felder `X-MYCMS-*` bleiben unverändert, weil Kalender- und Adressbuch-Apps die Felder so gespeichert haben.

## 1.1.0 (2026-10-02)

Angepasst an die Integrationspunkte von KLXM Studio 1.0 (Entwicklerhandbuch → Erweiterungen → „Integrationspunkte und Regeln“):

- Persönliche Einrichtung über den Slot **Konto** (`account`): Abschnitt „Kalender & Kontakte in Apps“ mit Anzahl der eigenen
  App-Passwörter, letzter Nutzung und Link zur Seite – statt eigenem Menüpunkt im Hauptmenü.
- Einstellungen je Tabelle als Einstellungsseite (`adminPage`, Art `settings`, Recht `data.schema`) unter
  **Administration → Einstellungen → Einstellungen der Funktionen**. Adresse `/admin/dav` unverändert.
- Jede Verwaltungsroute nennt ihr Recht (`dav.use` bzw. `data.schema` für die Tabellen-Einstellungen) – Anmeldung, Recht und
  CSRF prüft der Router vor dem Controller (`extensions:list` meldet keine Altform mehr).
- Datum der App-Passwörter über `Core\Format` (Sprache der Verwaltung).
- `composer.json` (Paket `klxm/studio-dav`, Typ `klxm-studio-extension`), Changelog.

## 1.0.0 (2026-09-25)

- Erste Fassung: CalDAV (Kalender-Tabellen) und CardDAV (beliebige Tabellen als Adressbuch) mit sabre/dav 4.7,
  App-Passwörter je Benutzer, Einstellungen je Tabelle.
