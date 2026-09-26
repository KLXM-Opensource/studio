# Changelog – Kalkulation & Angebote (`kalkulation`)

## 1.0.0 – 2026-09-26

Erste Version – ersetzt die Arbeitsmappe „KLXM-Kalkulation-intern.xlsx“.

- **Einstellungen**: benannte Stundensätze (umbenennen, ergänzen, entfernen), Satz für Einrichtungs- und
  Betreuungsstunden, Aufschlag auf Infrastruktur/Fremdkosten, Projektpuffer, USt (19 % vorbelegt), Währung,
  Rundung der Einzelpreise (Cent, 10 Cent, 1, 5, 10, 50; kaufmännisch oder aufrunden), optionale Selbstkosten je
  Stunde, Nummernkreis (`A-{Y}-001`), Gültigkeit, Absender, Standardtexte (Einleitung, Zahlung, Gültigkeit,
  Laufzeit, Schluss).
- **Leistungskatalog**: Leistungen mit Paketen (einmalig, monatlich oder beides; Preisbasis Stundensatz oder
  Festpreis; Fremdkosten; Einheit; Puffer), Textbausteine und Kostentreiber je Leistung. Beispiel-Katalog mit der
  Struktur der Arbeitsmappe (Managed Hosting KLXM Studio, KLXM Ai Hosting, Managed Nextcloud, Support & Wartung,
  Projekte) – **ohne Beträge**, als „Beispiel“ markiert.
- **Kalkulationen**: Liste mit Suche und Status (Entwurf, Angeboten, Angenommen, Abgelehnt, Archiv); Editor mit
  Positionen aus dem Katalog oder frei, Menge/Einheit, Stunden × Satz oder Festpreis, Fremdkosten mit Aufschlag,
  Rabatt je Position, Nachlass je Art, optional/Alternative; Live-Summen (einmalig, monatlich, jährlich, USt,
  brutto, erstes Jahr) und interne Sicht (Fremdkosten, Stunden, Marge € und %, effektiver Stundensatz);
  Kundenansicht; Grundlage je Kalkulation fest (neu laden auf Knopfdruck).
- Automatisches Speichern mit Stand-Prüfung (kein stilles Überschreiben bei gleichzeitigem Bearbeiten),
  Stände (automatisch höchstens alle 15 Minuten und bei Statuswechsel, eigene mit Notiz, wiederherstellbar),
  Duplizieren, Löschen mit Bestätigung der Nummer, Protokoll (wer/wann/was).
- **Angebotsansicht** (A4, Druck/„Als PDF speichern“) ohne interne Angaben; **CSV** (intern oder für Kunden).
- Tastatur-Workflow, deutsche Zahlen, Tabellenziffern, Live-Region für Summen, helles/dunkles Farbschema,
  Tablet-Bedienung, Telefon nur lesend.
- Sicherheit: eigenes Recht `kalkulation.manage`, Funktion `kalkulation` Standard aus, nur Verwaltung (keine
  Website-Routen, API, MCP, Suche, Content-Sync, Seiten-Cache); optionale Verschlüsselung der Inhalte (libsodium,
  Schlüssel aus `app_key`).
