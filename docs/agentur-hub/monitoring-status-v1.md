# Überwachungsprotokoll `status-v1`

Systemneutrale Spezifikation für die lesende, signierte Status-Abfrage, mit der ein Überwachungsdienst (z. B. der Agentur Hub)
Websites überwacht. Umgesetzt in KLXM Studio (`Core\Monitoring`, `GET /api/monitoring/status`) und im REDAXO-6-AddOn
`klxm_hub`; jedes andere System kann es ebenso umsetzen. Übersicht: [README.md](README.md).

Die Schlüsselwörter MUSS, DARF NICHT, SOLL und KANN sind wie in RFC 2119 zu lesen.

## 1. Rollen und Schlüssel

- Der **Dienst** besitzt ein Ed25519-Schlüsselpaar (RFC 8032, libsodium). Der private Schlüssel verlässt den Dienst nie.
- Die **Website** kennt nur öffentliche Schlüssel des Dienstes, weitergegeben als Text `ed25519:` + base64url (ohne `=`) der
  32 Byte, z. B. `ed25519:bcxoa7FRJ1bMtECxT18bKTfkJHZANNuiWv0flwFfRVQ`. Optional steht davor eine Bezeichnung
  (`Agentur Hub ed25519:…`).
- **Schlüssel-ID** (`kid`): die ersten 16 Zeichen von base64url(SHA-256(öffentlicher Schlüssel, 32 Byte roh)).
- Eine Website SOLL mehrere Schlüssel gleichzeitig annehmen (KLXM Studio: bis zu 10) – für den Schlüsselwechsel: neuen
  eintragen, Dienst wechselt, alten entfernen.
- Standard ist **aus**: Ohne Einschalten oder ohne Schlüssel antwortet der Endpunkt mit `404`.

## 2. Anfrage

`GET <Status-Adresse>` – die Adresse ist je System festgelegt und wird im Dienst je Website eingetragen (oder aus der
[Selbstauskunft](instance-v1.md) übernommen):

| System | Status-Adresse |
|---|---|
| KLXM Studio | `https://<host>/api/monitoring/status` |
| REDAXO 6 (AddOn `klxm_hub`) | `https://<host>/index.php?rex-api-call=klxm_hub_status` |
| eigenes System | frei wählbar, z. B. `https://<host>/.well-known/status` |

Kopfzeilen (alle Pflicht):

| Kopfzeile | Inhalt |
|---|---|
| `X-Status-Key` | Schlüssel-ID (16 Zeichen) |
| `X-Status-Timestamp` | Unix-Zeit in Sekunden (Dezimalzahl, 9–11 Ziffern) |
| `X-Status-Nonce` | 16–64 Zeichen aus `[A-Za-z0-9_-]`, zufällig je Anfrage |
| `X-Status-Signature` | base64url (ohne `=`) der 64-Byte-Ed25519-Signatur |

### Signierte Zeichenkette

Sechs Zeilen, getrennt durch `\n` (LF), **ohne** abschließenden Zeilenumbruch:

```
status-v1
GET
<Host in Kleinbuchstaben, mit :Port, wenn nicht 80 (http) bzw. 443 (https)>
<Pfad mit Query, genau wie in der Anfragezeile>
<Zeitstempel wie in X-Status-Timestamp>
<Nonce wie in X-Status-Nonce>
```

Beispiel für `https://www.example.org/api/monitoring/status`:

```
status-v1
GET
www.example.org
/api/monitoring/status
1795276800
Jm3k1pS0cT1x2y3z4AbCdEfG
```

Für REDAXO lautet die vierte Zeile z. B. `/index.php?rex-api-call=klxm_hub_status` – die Query gehört dazu, unverändert
(keine Umsortierung, keine Neu-Kodierung).

## 3. Prüfung durch die Website

1. Ausgeschaltet oder kein Schlüssel → `404 {"error":"not_found"}` – wie nicht vorhanden.
2. Rate-Limit je IP (KLXM Studio und REDAXO-AddOn: 30 Anfragen in 10 Minuten) → `429 {"error":"rate_limited"}`.
   KLXM Studio prüft das Limit vor Schritt 1; da nur Anfragen an eine eingeschaltete Abfrage zählen, ändert das nichts am
   Ergebnis.
3. Format der Kopfzeilen falsch → `401`.
4. `|jetzt − Zeitstempel| > 300` Sekunden → `401`.
5. Schlüssel-ID unbekannt → `401`.
6. Signatur ungültig → `401`. Host aus der Anfrage (hinter einem Proxy der ursprüngliche Host, nur von vertrauenswürdigen
   Proxys übernommen), Pfad mit Query aus `REQUEST_URI`, Methode aus der Anfrage.
7. Erst **nach** gültiger Signatur den Nonce je Schlüssel merken – sonst könnten Fremde Nonces „verbrauchen“. Schon gesehen
   → `401`. Der Nonce MUSS mindestens so lange gemerkt werden, wie das Zeitfenster reicht (2 × 300 s); empfohlen sind
   11 Minuten (KLXM Studio: 10 Minuten, REDAXO-AddOn: 11 Minuten).
8. Antwort `200` mit JSON.

Alle `401`-Antworten lauten `{"error":"unauthorized"}` **ohne Grund**; den Grund nur ins eigene Protokoll schreiben (KLXM Studio:
`SecurityLog`-Ereignis `monitor-denied`, geeignet für fail2ban).

Kopfzeilen der Antwort: `Content-Type: application/json`, `Cache-Control: no-store`, `X-Robots-Tag: noindex`.
Die Website KANN den Inhalt kurz zwischenspeichern (KLXM Studio und REDAXO-AddOn: 60 Sekunden) – `nonce` wird aber je
Anfrage eingesetzt.

## 4. Antwort

Pflichtfelder: `protocol`, `system`, `system_version`, `php`, `generated_at`, `health`, `nonce`. Alle anderen Felder sind
optional; unbekannte Felder ignoriert der Dienst. Die Antwort DARF NICHT enthalten: Inhalte, Konten, Personendaten,
Geheimnisse, Pfade des Servers, Inhalte des Fehlerprotokolls.

| Feld | Typ | Bedeutung |
|---|---|---|
| `protocol` | string | immer `"status-v1"` |
| `system` | string | `"studio"`, `"redaxo6"`, `"redaxo5"` oder eine eigene Kennung in Kleinbuchstaben |
| `system_version` | string | Version des Systems |
| `php` | string | PHP-Version (bzw. Laufzeit des Systems) |
| `generated_at` | string | ISO 8601 mit Zeitzone |
| `nonce` | string | Echo von `X-Status-Nonce` – bindet die Antwort an die Anfrage |
| `site` | object | `key`, `url` |
| `theme` | object | `name`, `version` (KLXM Studio: Kit; REDAXO: Theme bzw. Template-Paket, falls vorhanden) |
| `components` | array | `[{ "type": "extension"\|"addon"\|"plugin"\|"package", "name", "version", "active": bool }]` |
| `health` | object | `status`: `"green"`\|`"yellow"`\|`"red"`, `errors`: int, `warnings`: int, `notices`: int, `messages`: Liste – siehe 4.1 |
| `log` | object | Fehlerprotokoll, nur Zahlen – siehe 4.2 |
| `backup` | object | `last_at` (letzte vollständige Sicherung, ISO 8601 oder `null`), `offsite_at` (letzte Übertragung außer Haus) |
| `integrity` | object | `status`: `"ok"`\|`"warning"`\|`"error"`\|`"unknown"`, `checked_at` |
| `ssl` | object | `expires_at` – der Dienst ermittelt den Ablauf zusätzlich selbst aus der TLS-Verbindung |

### 4.1 Meldungen und Kategorien

`health.messages[]`: `{ "level": "error"|"warning", "category": "technical"|"setup"|"editorial", "text": "…" }`, höchstens 50,
Text höchstens 200 Zeichen. Erfolgreiche Prüfungen werden nicht aufgeführt.

| Kategorie | Bedeutung | Beispiele | im Agentur Hub |
|---|---|---|---|
| `technical` | Betrieb und Technik | Datenbank, Ordner, PHP-Version und Ende der Sicherheitsupdates, inkompatible Versionen, geplante Aufgaben, Sicherung, Integrität | Ampel, E-Mail bei Rot |
| `setup` | Einrichtung | fehlender optionaler Schlüssel, Impressum für E-Mails, Schriften, optionale Werkzeuge, Suchmaschinen gesperrt | nur Anzeige |
| `editorial` | Redaktion | SEO-Hinweise zu Titeln und Beschreibungen, fehlende Alt-Texte | nur Anzeige |

- **Fehlt `category`, gilt die Meldung als `technical`**; unbekannte Kategorien ebenso.
- `status`, `errors` und `warnings` beziehen sich **nur auf technische Meldungen**; `notices` zählt `setup` und `editorial`.
  Eine Website mit SEO-Hinweisen ist damit grün.
- Technische Meldungen stehen vorn (Fehler vor Warnungen), damit die Begrenzung auf 50 sie nie abschneidet.

### 4.2 Fehlerprotokoll (`log`)

Anzahl der Einträge im Fehlerprotokoll der Installation der letzten 24 Stunden – **nur Zahlen und Arten, nie Inhalte**:

| Feld | Typ | Bedeutung |
|---|---|---|
| `window_hours` | int | Zeitfenster (24) |
| `errors` | int | schwere Fehler: PHP Fatal/Parse/Recoverable fatal error + nicht abgefangene Ausnahmen |
| `warnings` | int | PHP-Warnungen |
| `counts` | object | je Art: `fatal`, `exception`, `warning`, `notice`, `deprecated`, `application` (eigene Meldungen des Systems) |
| `last_error_at`, `last_warning_at` | string\|null | ISO 8601 des jüngsten schweren Fehlers bzw. der jüngsten Warnung |
| `truncated` | bool | nur das Ende einer sehr großen Datei wurde gelesen |

KLXM Studio liest `storage/logs/php-error.log` (alle Websites einer Installation melden dieselben Zahlen), das REDAXO-AddOn
das Systemprotokoll von REDAXO.

### 4.3 Beispiel

```json
{
  "protocol": "status-v1",
  "system": "studio",
  "system_version": "1.0.0",
  "php": "8.4.12",
  "generated_at": "2026-11-21T17:04:00+01:00",
  "site": { "key": "default", "url": "https://www.example.org" },
  "theme": { "name": "fluid", "version": "1.0.0" },
  "components": [
    { "type": "extension", "name": "beispiel", "version": "0.2.0", "active": true }
  ],
  "health": { "status": "yellow", "errors": 0, "warnings": 1, "notices": 2,
    "messages": [
      { "level": "warning", "category": "technical", "text": "Sicherung älter als drei Tage" },
      { "level": "warning", "category": "setup", "text": "Impressum für E-Mails fehlt" },
      { "level": "warning", "category": "editorial", "text": "SEO: 2 Hinweise zu Titeln und Beschreibungen" }
    ] },
  "log": { "window_hours": 24, "errors": 0, "warnings": 3,
    "counts": { "fatal": 0, "exception": 0, "warning": 3, "notice": 0, "deprecated": 5, "application": 1 },
    "last_error_at": null, "last_warning_at": "2026-11-21T16:40:12+01:00", "truncated": false },
  "backup": { "last_at": "2026-11-21T03:00:00+01:00", "offsite_at": null },
  "integrity": { "status": "ok", "checked_at": "2026-11-21T04:00:00+01:00" },
  "ssl": { "expires_at": null },
  "nonce": "Jm3k1pS0cT1x2y3z4AbCdEfG"
}
```

## 5. Bewertung im Agentur Hub

Die Ampel kommt **nur aus technischen Befunden**:

- **rot:** nicht erreichbar, HTTP ≠ 200, kein JSON, `protocol` ≠ `status-v1`, `nonce` passt nicht, Pflichtfeld fehlt,
  technische Meldung mit `level = error` (ohne Meldungen: `health.status = red`), `integrity.status = error`, `log.errors > 0`,
  TLS-Zertifikat läuft in weniger als 7 Tagen ab.
- **gelb:** technische Meldung mit `level = warning` (bzw. `health.status = yellow`), `integrity` `warning`, `unknown` **oder
  fehlend**, letzte Sicherung (`backup.last_at` bzw. `offsite_at`) älter als 3 Tage **oder fehlend**, `log.warnings > 0`,
  Zertifikat läuft in weniger als 21 Tagen ab.
- **grün:** sonst.

Wer kein `backup` bzw. keine Integritätsprüfung liefert, bleibt also gelb. Bewusst hingenommene technische Befunde lassen sich
im Hub mit Begründung und optionalem Ablaufdatum **als bekannt bestätigen**; ändert sich der Text der Meldung oder kommt ein
neuer Eintrag ins Fehlerprotokoll, alarmiert der Befund wieder. Erreichbarkeit und Protokollfehler lassen sich nicht bestätigen.
Der Hub fragt alle 15 Minuten ab; ein Wechsel auf Rot schickt eine E-Mail an die Hub-Verwaltung, Hinweise für Einrichtung und
Redaktion nie.

## 6. Fehlercodes im Überblick

| HTTP | Körper | Bedeutung |
|---|---|---|
| 200 | Status-JSON | gültige Anfrage |
| 401 | `{"error":"unauthorized"}` | Format, Zeitfenster, Schlüssel, Signatur oder Wiederholung – ohne Grund |
| 404 | `{"error":"not_found"}` | Abfrage ausgeschaltet oder kein Schlüssel eingetragen |
| 429 | `{"error":"rate_limited"}` | zu viele Anfragen von dieser IP |
| 5xx | – | Fehler der Website; der Dienst wertet das als rot |

## 7. Referenz (PHP, libsodium)

```php
// Dienst: Anfrage signieren
$base = implode("\n", ['status-v1', 'GET', $host, $pathQuery, (string) $ts, $nonce]);
$sig  = sodium_crypto_sign_detached($base, $secretKey64);
// Website: prüfen
$ok   = sodium_crypto_sign_verify_detached($sig, $base, $publicKey32);
// Schlüssel-ID
$kid  = substr(rtrim(strtr(base64_encode(hash('sha256', $publicKey32, true)), '+/', '-_'), '='), 0, 16);
```

KLXM Studio: `Core\Monitoring\Signature` (`base`, `headers`, `verify`, `keyId`, `parseKey`), Selbsttest
`php bin/console monitor:selftest`. Eine vollständige Prüfung für eigene Systeme steht in [eigene-loesung.md](eigene-loesung.md).

---

## English summary

`status-v1` is a read-only, signed status endpoint. The monitoring service owns an Ed25519 key pair; the website stores only
public keys (`ed25519:<base64url 32 bytes>`, key id = first 16 chars of base64url(SHA-256(public key))). Each `GET` carries
`X-Status-Key`, `X-Status-Timestamp` (±300 s), `X-Status-Nonce` (16–64 chars `[A-Za-z0-9_-]`) and `X-Status-Signature`
(base64url Ed25519) over six LF-separated lines without trailing newline: `status-v1`, method, lowercase host (port only if
non-default), path with query exactly as requested, timestamp, nonce. The website answers 404 when off, 429 when rate limited,
401 `{"error":"unauthorized"}` without a reason for any verification failure, and remembers the nonce per key only after a valid
signature (at least the full 600 s window, 11 minutes recommended). The 200 JSON requires `protocol`, `system`, `system_version`,
`php`, `generated_at`, `health`, `nonce` (echo) and may add `site`, `theme`, `components`, `log` (error-log counts only),
`backup`, `integrity`, `ssl`. Health messages carry `category` `technical` (traffic light), `setup` or `editorial` (display only);
missing category means `technical`. Never include content, accounts, personal data, secrets or server paths. The hub turns
yellow when `backup` or `integrity` are missing.
