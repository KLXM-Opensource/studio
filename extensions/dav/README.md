# Erweiterung „dav“ – CalDAV/CardDAV

Termine (Kalender-Tabellen) und Kontakte (Adressbuch-Tabellen) aus KLXM Studio mit Kalender- und
Adressbuch-Apps abgleichen: Apple Kalender/Kontakte (macOS, iOS), Thunderbird, DAVx⁵ (Android) u. a.
Technik: [sabre/dav](https://sabre.io/dav/) 4.7 (per Composer im Projekt), eigene Backends auf die Datentabellen.

## Aktivieren

```php
// config/sites/{key}.php bzw. config/config.local.php (Hauptwebsite)
'extensions' => ['dav'],
// optional abschalten, ohne die Erweiterung zu entfernen:
'features' => ['dav' => false],
```

Beim ersten Aufruf legt die Migration die Tabellen `dav_passwords` und `dav_objects` an (je Website).
Als Composer-Paket: `klxm/studio-dav` (Typ `klxm-studio-extension`; `sabre/dav` bringt der Core mit).
Voraussetzungen: Funktion `data` (und für Kalender `calendar`) an, HTTPS in Produktion.

## Einrichten

1. **Rechte:** Die Rolle braucht `dav.use` (Administration hat alles) und `data.edit` für die Tabellen.
   Rollen mit Tabellen-Einschränkung sehen nur ihre Tabellen.
2. **Kalender:** Daten → Tabelle → Felder & Einstellungen → „Als Kalender nutzen“ (Core-Funktion `calendar`).
3. **Adressbuch:** Administration → Einstellungen → „Kalender & Kontakte in Apps“ → Tabellen (Recht `data.schema`) → „Als Adressbuch (CardDAV) bereitstellen“,
   Zuordnung der vCard-Felder prüfen (wird aus Feldnamen wie vorname, nachname, firma, strasse, plz, ort vorbelegt).
   Je Tabelle außerdem „Nur veröffentlichte Einträge“ und – bei Adressbüchern ohne Feld „Firma“ – die feste Organisation.
4. **App-Passwort:** Konto → Abschnitt „Kalender & Kontakte in Apps“ → „App-Passwort anlegen“ (Seite `/admin/dav`) → „Neues App-Passwort“ (nur einmal sichtbar,
   „Lesen und ändern“ oder „Nur lesen“). Alternativ: `php bin/console dav:password <e-mail> [name] [read|write]`.

## Verbinden

| | |
|---|---|
| Server | `https://ihre-domain.de/dav/` (Discovery: `/.well-known/caldav`, `/.well-known/carddav`) |
| Benutzername | E-Mail-Adresse des CMS-Kontos |
| Passwort | App-Passwort (nicht das Login-Passwort) |

- **Apple macOS/iOS:** Einstellungen → Kalender (bzw. Kontakte) → Accounts → Account hinzufügen → Andere →
  CalDAV-Account (bzw. CardDAV-Account) → „Manuell“ → Server `ihre-domain.de`, Benutzername, App-Passwort.
- **Thunderbird:** Kalender → Neuer Kalender → Im Netzwerk → Benutzername + Adresse `https://ihre-domain.de/dav/`
  (findet alle Kalender). Adressbuch → Neues Adressbuch → CardDAV → gleiche Adresse.
- **DAVx⁵ (Android):** Konto hinzufügen → „Mit URL und Benutzername anmelden“ → `https://ihre-domain.de/dav/`.

Pfade: `/dav/principals/{e-mail}/`, `/dav/calendars/{e-mail}/{tabelle}/`, `/dav/addressbooks/{e-mail}/{tabelle}/`.

**Abo-Token statt App-Passwort (nur lesen):** Mit der Core-Funktion „Kalender und Kontakte per App abonnieren“ (`subscriptions`)
legt die Redaktion unter Daten → Kontakte-Tabelle → Abonnieren benannte Abo-Tokens an. Als Passwort (Benutzername beliebig, z. B. `abo`)
öffnen sie genau diese Kontakte-Tabelle als Adressbuch – nur lesen, Umfang „nur öffentlich“ oder „intern“. Prüfung, Ablauf,
Rate-Limit und Protokoll liegen im Core (`Core\Data\Subscriptions::verify()`); Pfade `/dav/addressbooks/abo-{id}/{tabelle}/`.

## Abbildung

### Adressbücher (Tabellenart „contacts“)

Tabellen der Art „Adressbuch“ (Core `Core\Data\Kinds`, z. B. aus der Vorlage „Adressbuch“ oder per `data:convert … --kind=contacts`)
brauchen keine Zuordnung: N (Nachname;Vorname;;Titel), FN (Anzeigename), ORG (Organisation;Abteilung), TITLE (Funktion), PHOTO, BDAY,
NOTE, CATEGORIES (Listen) und die Mehrfachfelder Telefon, E-Mail, Adresse, Web – Standard-Beschriftungen als `TYPE`
(CELL, WORK, HOME, FAX, MAIN …), alle übrigen (z. B. „Geschäftsstelle“, eigene) als Apple-Beschriftung `itemN.X-ABLabel`. Aus der
App kommt die Beschriftung zurück; eine neue oder geänderte Angabe ist **nie von selbst öffentlich**, „Öffentlich“ bleibt nur bei
unverändertem Wert. Eigene Zusatzfelder reisen wie bisher als `X-MYCMS-*`.

Freigabe je Feld (Core `Exposure`): Felder, die bewusst nur anderen Zielen gehören (z. B. „Nur Website“: Vita), gehen nie an Apps und
werden von Apps nie geschrieben; „Nie öffentlich“ und „Redaktion entscheidet“ sehen die Apps der Redaktion vollständig.

### Einstellungen je Tabelle

Website-Einstellung `ext.dav.tables` (je Tabelle ein Eintrag, Kurzname der Tabelle als Schlüssel), gepflegt auf `/admin/dav`:

```php
'mitarbeitende' => [
    'addressbook' => true,          // Adressbuch (CardDAV); Kalender-Tabellen: 'caldav' => true (Standard)
    'status' => 'published',        // neue Einträge aus Apps: published | draft (ohne Angabe: draft bei Adressbüchern mit Detailseite)
    'on_delete' => 'draft',         // in der App gelöscht: draft | delete (mit Recht data.delete)
    'published_only' => true,       // nur veröffentlichte Einträge an Apps (Standard false = bisheriges Verhalten)
    'org' => true,                  // ORG ohne Feld „Firma“: true = org_name(), 'Text' = fest, false = kein ORG
    'map' => ['fn' => 'name', 'note' => 'notiz'],
],
```

- **`published_only`:** Entwürfe und geplante Einträge („Online ab“ in der Zukunft = Entwurf) erscheinen nicht in den Apps.
  Abruf und Ändern solcher Einträge ergeben 404, Löschen ist ohne Wirkung; ihre Adresse `cms-{id}.vcf|ics` können Apps nicht
  für neue Objekte belegen (403). Wird ein Eintrag veröffentlicht, erscheint er beim nächsten Abgleich. Mit „Neue Einträge aus
  Apps: als Entwurf“ verschwinden in der App angelegte Objekte bis zur Freigabe. **Standard `false`**, damit bestehende
  Kalender und Adressbücher sich durch ein Update nicht unbemerkt ändern; die Verwaltung schlägt `true` für Tabellen vor, die
  noch nicht bereitgestellt werden.
- **`org`:** gilt nur, wenn kein Feld „Firma (ORG)“ zugeordnet ist. Fehlend/`true` = Name der Organisation aus den
  Website-Einstellungen (`org_name()`: Organisationsname, sonst Kurzname, sonst Name der Website).

**VEVENT ↔ Eintrag** (nur Einträge der Standardsprache; Entwürfe erscheinen als `STATUS:TENTATIVE`, mit `published_only` gar nicht):

| iCalendar | Feld (Kalender-Einstellung der Tabelle) |
|---|---|
| SUMMARY | Titel-Feld |
| DTSTART / DTEND / DURATION, `VALUE=DATE` | Beginn / Ende / Ganztägig (ohne Ende: Standarddauer) |
| RRULE, EXDATE | Wiederholung (DAILY–YEARLY) |
| DESCRIPTION | Beschreibung (reiner Text; unverändert → Formatierung im CMS bleibt) |
| LOCATION bzw. GEO | Ort (Text bzw. Feld vom Typ „Ort (Karte)“) |
| CATEGORIES | Kategorie (Auswahltexte; unbekannte Werte werden ignoriert) |
| URL | Detailseite (nur lesen) |

**vCard 3.0 ↔ Eintrag:** FN bzw. N (Nachname; Vorname), ORG, TITLE, ADR (Straße, PLZ, Ort, Land), URL, NOTE, BDAY,
PHOTO (Bildfeld → eingebettetes JPEG, max. 400 px, nur Ausgabe), CATEGORIES; EMAIL und TEL aus **allen** Feldern
dieser Typen – Typ aus Feldname/Beschriftung: „mobil/handy“ → CELL, „fax“ → FAX, „privat“ → HOME, sonst WORK.
**Alle übrigen Felder** als `X-MYCMS-{FELD}` (Kurzname groß, `_` → `-`), z. B. `X-MYCMS-FACHGEBIET:Kardiologie`,
`X-MYCMS-RUECKRUF-ERWUENSCHT:1`; Ja/Nein = 1/0, Mehrfachauswahl/Verknüpfungen kommagetrennt (Kurznamen bzw. IDs).
Titelzusätze (N-Präfix „Dr.“) und der Anzeigename der App bleiben erhalten, solange Vor-/Nachname gleich sind.

- **Nur FN zugeordnet:** N wird aus dem ganzen Namen abgeleitet – `Prof. Dr. Erika von Musterstadt` →
  `N:von Musterstadt;Erika;;Prof. Dr.;`. Letztes Wort = Nachname, Partikel davor (von, van, de, der, zu, …) gehören dazu, führende
  Titel/Anreden (Dr., Prof., Dr. med., Dipl.-Ing., Priv.-Doz., Herr, Frau) sind Präfix, Zusätze (M.A., MBA, jun.) Suffix;
  `Musterfrau, Erika` = Nachname, Vorname. Zurück aus der App hat FN Vorrang (Pflicht in vCard 3/4); fehlt FN oder hat die App nur
  Vor-/Nachname in N geändert (FN unverändert), wird der Name aus N zusammengesetzt.
- **Rich-Text** (NOTE und `X-MYCMS-*`) als reiner Text: Absätze → Leerzeile, Umbrüche/Listenpunkte → Zeile, Entities dekodiert,
  Redaktionsnotizen `[# … #]` entfernt. Unveränderter Text aus der App lässt Formatierung und Notizen im CMS stehen; geänderter
  Text wird zu Absätzen, vorhandene Notizen werden angehängt.
- Felder mit verschachtelten Werten (Inhaltsblöcke, Kombifelder wie Ansprechperson/Adresse/FAQ/Downloads, Gruppen, Medien + Text,
  Wiederholung) werden nicht als `X-MYCMS-*` übertragen.

**Verlustfreie Abgleiche:** `dav_objects` speichert je Eintrag URI, UID und die zuletzt vom Client gesendeten Rohdaten.
Bei der Ausgabe werden die abgebildeten Eigenschaften aus dem Eintrag gesetzt, alles andere (Erinnerungen/VALARM,
Teilnehmer, X-Eigenschaften, Social-Profile …) aus den Rohdaten übernommen. ETag = Hash der Ausgabe → ändert sich bei
jeder Änderung im CMS (updated_at); ctag je Sammlung aus Anzahl/letzter Änderung.

**Neu / Löschen:** Neue Objekte aus Apps werden Einträge mit dem je Tabelle eingestellten Status (Standard: online, bei
Adressbüchern mit Detailseite **Entwurf** – Datenschutz, damit ein Kontakt aus der App nicht von selbst auf der Website erscheint;
gespeicherte Einstellungen gelten unverändert; ohne `data.publish` Entwurf, wenn die Tabelle den Freigabe-Workflow nutzt).
Felder mit „Nie öffentlich“ (Core) gibt DAV weiter an Apps – es ist ein interner Zugang. Löschen in der App setzt standardmäßig **Entwurf** (sicher; der Eintrag bleibt im
CMS und verschwindet aus der App, bis er wieder veröffentlicht wird). „Endgültig löschen“ je Tabelle einstellbar,
wirkt nur mit `data.delete`.

## Grenzen

- Kalender und Adressbücher entstehen, heißen und verschwinden nur im CMS (MKCALENDAR/Löschen → 403, Umbenennen aus der App
  wird ignoriert, Farbe aus dem Kurznamen abgeleitet). Höchstens 5000 Einträge je Sammlung.
- Einträge anderer Websites in geteilten Tabellen sind in Apps schreibgeschützt (403).
- Nur VEVENT (keine Aufgaben/VTODO). Geänderte Einzeltermine einer Serie (`RECURRENCE-ID`) bleiben nur in den Rohdaten:
  Apps zeigen sie, Website-Kalender und iCal-Feed zeigen den Serientermin.
- Regeln außerhalb DAILY–YEARLY (z. B. stündlich) werden nicht ins CMS übernommen (nur Rohdaten).
- Ganztägige Termine ohne Feld „Ganztägig“ werden als 00:00 Uhr gespeichert.
- Mehr E-Mail-Adressen/Telefonnummern als passende Felder, Fotos aus Apps und Länder ohne Feld werden nicht übernommen.
- Übersetzte Einträge (weitere Sprachen) werden nicht abgeglichen.
- Keine WebDAV-Sync (sync-collection); Clients gleichen über ctag/ETag ab. Kein Scheduling (Einladungen per iMIP).
- HTTP Basic: nur über HTTPS betreiben. 20 Fehlversuche je IP und 15 Minuten.

## Kern-Änderungen (dokumentiert in der Technik-Hilfe)

- `Core\Http\Router::any()` – Route für alle HTTP-Methoden.
- `Core\Http\Response::alreadySent()` / `$sent` – sabre/dav schreibt die Antwort selbst.
- Staging-Passwortschutz (`staging_auth`) überspringt `/dav` und `/.well-known/`.
- `Extensions::adminNav()` übersetzt Beschriftungen mit `__()`.

## Test

```bash
php bin/console dav:selftest --site=key   # Abbildung (Namen, ORG, Text, Rückweg, published_only) ohne Datenbank
curl -u "mail@example.org:APP-PASSWORT" -X PROPFIND -H "Depth: 1" https://ihre-domain.de/dav/calendars/mail@example.org/
python -m pip install caldav   # caldav.DAVClient(url="https://ihre-domain.de/dav/", username=…, password=…)
```

## Lizenz

MIT wie KLXM Studio – siehe `LICENSE`. Die verwendeten sabre/*-Pakete stehen unter BSD-3-Clause (© fruux GmbH).

## Einbindung in die Verwaltung

| Stelle | Was |
|---|---|
| Slot `account` (Recht `dav.use`) | Abschnitt im Konto: Anzahl eigener App-Passwörter, zuletzt benutzt, Link zur Seite `/admin/dav` |
| `adminPage` Art `settings` (Recht `data.schema`) | Karte auf Administration → Einstellungen → Einstellungen der Funktionen: Tabellen als Kalender bzw. Adressbuch bereitstellen |
| Filter `subscriptions.apps` | Seite „Abonnieren“ (Core): CardDAV-Zugang mit Abo-Token für Kontakte-Tabellen |
| Routen | `GET /admin/dav`, `POST /admin/dav/passwords`, `POST /admin/dav/passwords/{id}/delete` mit `dav.use`; `POST /admin/dav/tables` mit `data.schema` – Anmeldung, Recht und CSRF prüft der Core |

vCard-Felder `X-MYCMS-*` bleiben als historische technische Kennung (in den Apps gespeichert).
