# Changelog – Erweiterung „dav“

## 1.3.1 (2026-11-21)

- **Datenschutz: neue Kontakte aus Apps bei Tabellen mit Detailseiten als Entwurf.** Ohne gespeicherte Einstellung schlägt die
  Verwaltung für Adressbücher (keine Kalender) mit Detailseite „als Entwurf“ vor (`Dav::statusPreset()`) – ein in der App
  angelegter Kontakt erscheint so nicht von selbst auf der Website. Gespeicherte Einstellungen (`ext.dav.tables.<tabelle>.status`)
  bleiben unverändert, Tabellen ohne Detailseite und Kalender wie bisher „sofort online“; die Einstellung bleibt änderbar.
- Felder mit „Nie öffentlich“ (Core) sehen Adressbuch- und Kalender-Apps weiter – DAV ist ein interner Zugang.

## 1.3.0 (2026-11-21)

- **Nur veröffentlichte Einträge** je Tabelle (`published_only`, Website-Einstellung `ext.dav.tables.<tabelle>.published_only`,
  in der Verwaltung als Haken): Entwürfe und geplante Einträge erscheinen nicht in Kalender- und Adressbuch-Apps; Abruf und Ändern
  solcher Einträge ergeben 404, ihre Adresse `cms-{id}.vcf|ics` lässt sich aus Apps nicht neu belegen (403). Gilt für CardDAV und
  CalDAV. **Standard aus** – bestehende Kalender und Adressbücher verhalten sich wie bisher (Entwürfe sichtbar, Termine als
  `STATUS:TENTATIVE`); die Verwaltung schlägt den Haken für Tabellen vor, die noch nicht bereitgestellt werden.
- **Name aus FN:** Ist nur „Name (FN)“ zugeordnet, enthält N jetzt Nachname;Vorname;;Präfix;Suffix (vorher stand der ganze Name im
  Nachnamen) – Titel/Anreden („Dr.“, „Prof. Dr.“, „Dipl.-Ing.“, „Frau“) als Präfix, Partikel („von“, „van der“, „zu“) beim Nachnamen.
  Zurück aus der App hat FN Vorrang; ohne FN oder wenn nur N geändert wurde, wird der Name aus N zusammengesetzt.
- **Feste Organisation (ORG)** je Adressbuch (`org`): ohne zugeordnetes Feld „Firma“ steht standardmäßig der Name der Organisation
  der Website (`org_name()`) in ORG; eigener Text möglich, `org => false` schaltet ab.
- **Text statt HTML:** `X-MYCMS-*` aus Rich-Text-Feldern und NOTE als reiner Text (Absätze, Umbrüche, dekodierte Entities), ohne
  Redaktionsnotizen `[# … #]`. Unveränderter Text aus der App lässt Formatierung und Notizen im CMS stehen.
- **Korrektur:** Felder mit verschachtelten Werten (Inhaltsblöcke, Kombifelder, Gruppen, Medien + Text, Wiederholung) erscheinen nicht
  mehr als `X-MYCMS-…:Array,Array` und können aus Apps nicht mehr mit diesem Text überschrieben werden.
- Selbsttest `php bin/console dav:selftest --site=…`.
- **Hinweis:** Durch die geänderte Ausgabe (N, ORG, Text statt HTML) ändern sich die ETags aller Kontakte – Apps laden die
  Adressbücher einmal neu. Daten im CMS ändern sich dadurch nicht.

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
