# Kalkulation & Angebote für KLXM Studio (`kalkulation`)

Preise **intern kalkulieren** und als **Angebot** ausgeben – Stundensätze, Aufschläge, Leistungskatalog, Marge und
effektiver Stundensatz, Angebotsansicht zum Drucken bzw. „Als PDF speichern“ und CSV. Ersetzt die bisherige
Excel-Arbeitsmappe. **Nur Verwaltung**: nichts davon erscheint auf der Website. Lizenz: MIT (`LICENSE`).

## Installation

```bash
composer require klxm/studio-kalkulation     # Paket-Typ klxm-studio-extension
# oder als Ordner: extensions/kalkulation/
cd tools && pnpm run build                    # kopiert assets/ nach public/extensions/kalkulation
```

Einschalten je Website unter **Administration → Funktionen & Erweiterungen** (Haupt-Admin) – die Funktion
`kalkulation` wird dabei mit eingeschaltet (Standard: aus). Oder per Konfiguration:

```php
'extensions' => ['kalkulation'],
'features'   => ['kalkulation' => true],
```

Beim ersten Start entstehen die Tabellen `kalk_store`, `kalk_calcs`, `kalk_versions`, `kalk_log` in der Datenbank der
Website sowie Einstellungen (ohne Beträge) und ein **Beispiel-Katalog ohne Beträge**.

## Bedienung

- **Verwaltung → Kalkulation → Einstellungen**: Stundensätze (benannt, frei ergänzbar), Aufschlag auf
  Infrastruktur/Fremdkosten, Projektpuffer, USt, Währung, Rundung, optionale Selbstkosten je Stunde,
  Nummernkreis, Absender, Standardtexte, Verschlüsselung, Protokoll.
- **Leistungskatalog**: Leistungen mit Paketen – einmalig (Einrichtung/Projekt), monatlich (Betrieb) oder beides;
  Stunden × Satz oder Festpreis, Fremdkosten, Einheit (pauschal, je Nutzer, je GB, je Seite, je Modul …), Puffer.
- **Kalkulationen**: Liste (Suche, Status), Editor mit Positionen aus dem Katalog oder frei, Live-Summen,
  interne Sicht und Kundenansicht, automatisches Speichern, Stände, Duplizieren, Angebot/PDF, CSV.

Handbuch: Verwaltung → Hilfe → „Kalkulation & Angebote“ (`docs/manual.php`, nur für Berechtigte sichtbar).

## Rechenregeln

Je Position (Werte je Einheit): `Arbeit = Stunden × Satz` bzw. Festpreis; `Puffer = Arbeit × Projektpuffer %` (nur
mit „Puffer“); `Fremdleistung = Fremdkosten × (1 + Aufschlag %)`; `Einzelpreis = Arbeit + Puffer + Fremdleistung`,
gerundet nach Einstellung; `Gesamt = Menge × Einzelpreis × (1 − Rabatt %)`. Optionale und alternative Positionen
zählen nicht zur Summe. Je Art (einmalig/monatlich): Summe − Nachlass % = netto, USt auf netto, jährlich =
monatlich × 12. Intern: Marge = Umsatz − Fremdkosten (− Stunden × Selbstkosten), effektiver Stundensatz =
(Umsatz − Fremdkosten) ÷ Stunden. Implementiert in `src/Engine.php` und identisch in `assets/js/kalkulation.js`.

## Sicherheit und Datenschutz

- Eigenes Recht `kalkulation.manage`; die Rolle „Administration“ hat es immer, andere Rollen nur, wenn es unter
  Benutzer & Rollen ausdrücklich vergeben wird.
- Funktion `kalkulation` ist Standard **aus**; ausgeschaltet sind alle Adressen 404 und der Menüpunkt fehlt.
- Nur Verwaltungsrouten unter `/admin/kalkulation` (Anmeldung, CSRF, `Cache-Control: no-store`, strikte CSP). Keine
  Website-Routen, kein REST/MCP, kein Suchindex, kein Content-Sync, kein Seiten-Cache. Das Handbuch-Kapitel
  erscheint nur für Berechtigte.
- Protokoll (wer, wann, was) und Stände je Kalkulation; Löschen verlangt die Eingabe der Nummer.
- **Verschlüsselung (optional)**: Inhalte von Kalkulationen, Ständen, Katalog und Einstellungen mit libsodium
  secretbox über `Core\Settings::encrypt()` (Schlüssel aus `app_key` der Konfiguration). Schützt Datenbank-Kopien
  und Sicherungen ohne Konfiguration; Nummer, Status und Datum bleiben lesbar. Geht der `app_key` verloren, sind die
  Inhalte nicht mehr lesbar. `site:backup` enthält die Tabellen (verschlüsselt, wenn eingeschaltet).

## Mehrere Websites

Daten gehören zur jeweiligen Website (eigene Datenbank). Eine installationsweite Nutzung (gemeinsamer Katalog bzw.
gemeinsame Kalkulationen) ließe sich später über eine gemeinsame Datenbank ergänzen – wie bei den geteilten Daten
des Kerns (`data.shared`): `Repo` bekäme dafür eine eigene Verbindung statt `app()->db`.

## Grenzen

- Kein PDF-Generator auf dem Server: „Als PDF speichern“ über den Druckdialog des Browsers.
- Keine Rechnungen/Buchhaltung, keine Mehrwährungs-Umrechnung; Zahlen werden deutsch formatiert.
- Bearbeiten ab Tablet-Breite; auf dem Telefon nur lesen.

---

## English (short)

Internal **price calculation and quotes** for KLXM Studio: named hourly rates, markup on third-party costs, project
buffer, VAT, rounding; a service catalogue with packages (one-time and/or monthly); calculations with live totals,
internal view (costs, margin, effective hourly rate) and customer view; autosave with revision check, snapshots,
duplicate; printable A4 quote (save as PDF via the browser) and CSV. Admin only (own permission
`kalkulation.manage`, feature `kalkulation` off by default), no public routes, not exposed to REST/MCP/search.
Optional encryption at rest with the installation's `app_key`. Data is per site. MIT licence.
