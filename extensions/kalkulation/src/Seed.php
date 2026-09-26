<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Kalkulation;

/**
 * Vorgaben beim ersten Start: Einstellungen (ohne Beträge) und ein Beispiel-Katalog mit der Struktur der bisherigen
 * Arbeitsmappe (Leistungen, Pakete, Kostentreiber, Textbausteine) – ALLE Zahlen leer, Pakete als „Beispiel“ markiert.
 * Leere Zahlen ergeben leere bzw. 0-Ergebnisse, nie Scheinwerte.
 */
final class Seed
{
    public static function settings(): array
    {
        return [
            'rates' => [
                ['key' => 'konzept', 'label' => 'Beratung/Konzept/Design', 'rate' => null],
                ['key' => 'dev', 'label' => 'Entwicklung', 'rate' => null],
                ['key' => 'support', 'label' => 'Support/Betrieb', 'rate' => null],
            ],
            'rate_once' => 'dev',          // Einrichtungsstunden (Leistungen mit Einrichtung + Betrieb)
            'rate_monthly' => 'support',   // Betreuungsstunden pro Monat
            'markup' => null,              // Aufschlag auf Infrastruktur/Fremdkosten in %
            'buffer' => null,              // Projektpuffer in % (nur Positionen mit „Puffer“)
            'vat' => 19.0,
            'currency' => 'EUR',
            'round_step' => 0.0,
            'round_mode' => 'nearest',
            'cost_rate' => null,           // interne Selbstkosten je Stunde (optional, nur für die Marge)
            'number_prefix' => 'A-{Y}-',
            'validity_days' => 30,
            'sender' => '',
            'text_intro' => "vielen Dank für Ihre Anfrage. Gern bieten wir Ihnen folgende Leistungen an:",
            'text_payment' => "Einmalige Leistungen berechnen wir nach Abnahme, zahlbar innerhalb von 14 Tagen ohne Abzug. Monatliche Leistungen berechnen wir quartalsweise im Voraus.",
            'text_validity' => "Dieses Angebot ist gültig bis {datum}.",
            'text_term' => "",
            'text_closing' => "Wir freuen uns auf die Zusammenarbeit. Bei Fragen melden Sie sich gern.",
            'encrypt' => false,
        ];
    }

    public static function catalog(): array
    {
        $n = 0;
        $item = function (string $name, string $desc, string $bill, string $unit = 'pauschal', array $extra = []) use (&$n): array {
            $n++;
            return $extra + ['id' => 'i' . $n, 'name' => $name, 'desc' => $desc, 'bill' => $bill, 'unit' => $unit,
                'once_basis' => '', 'once_amount' => null, 'once_cost' => null, 'buffer' => false,
                'monthly_basis' => '', 'monthly_amount' => null, 'monthly_cost' => null, 'example' => true];
        };
        return ['services' => [
            ['id' => 's1', 'name' => 'Managed Hosting KLXM Studio',
                'desc' => 'Betrieb von KLXM-Studio-Installationen: Server, Updates, Sicherungen, TLS, Überwachung, Staging.',
                'notes' => "Serverkosten (vServer/Plesk-Lizenz anteilig), Speicher, Backup-Speicher, Domains/Zertifikate\nUpdate-Aufwand pro Release, Monitoring, Reaktionszeit (SLA) – als eigenes Paket oder im Support-Kontingent\nOptional: ffmpeg/Video-Werkzeuge, KI-Funktionen → siehe KLXM Ai Hosting",
                'text' => 'Wir betreiben Ihre Website auf gepflegten Servern: eingerichtet, abgesichert und überwacht – mit Updates, Sicherungen und TLS-Zertifikaten. Änderungen testen wir vor dem Livegang auf Staging.',
                'items' => [
                    $item('Start', '1 Website, SQLite, tägliche Sicherung, Updates, TLS, Überwachung', 'both', 'Monat'),
                    $item('Business', 'bis 3 Websites (Multi-Site), Staging, Medien-Pool, erweiterte Sicherung', 'both', 'Monat'),
                    $item('Netzwerk / Agentur', 'viele Websites, Netzwerk-Verwaltung, SSO, individuelle Absprachen', 'both', 'Monat'),
                ]],
            ['id' => 's2', 'name' => 'KLXM Ai Hosting',
                'desc' => 'KI-Funktionen und KI-Hosting: KLXM Ai in KLXM Studio (Texte, Übersetzung, SEO, Alt-Texte, Assistent, Suche) und betriebene Modelle – optional lokal statt US-Cloud.',
                'notes' => "Nutzungskosten: Tokens/Anfragen beim Anbieter bzw. CPU/GPU-Stunden der eigenen Instanz\nModellgröße bestimmt Hardware (RAM/VRAM) – Kosten je Instanz ermitteln, Auslastung schätzen\nDatenschutz: AVV, keine Nutzung der Daten für Training, Speicherort dokumentieren\nFair-Use-Grenze oder Kontingent je Monat festlegen (Überschreitung nach Aufwand)",
                'text' => 'KI-Funktionen für Texte, Übersetzungen, SEO und Alt-Texte – auf Wunsch lokal betrieben statt in US-Clouds. Ihre Daten werden nicht zum Training verwendet.',
                'items' => [
                    $item('Einstieg', 'KLXM Ai in KLXM Studio über EU-/externen Anbieter, Nutzung wird weiterberechnet oder pauschal', 'both', 'Monat'),
                    $item('Lokal (geteilt)', 'Modelle auf von KLXM betriebener Instanz (geteilt), keine Daten an Dritte', 'both', 'Monat'),
                    $item('Dediziert', 'eigene Instanz/Modelle für den Kunden, individuelle Anbindung (API/MCP)', 'both', 'je Instanz'),
                ]],
            ['id' => 's3', 'name' => 'Managed Nextcloud',
                'desc' => 'Einrichtung, Hosting, Updates, Sicherungen und Support für Nextcloud (Dateien, Kalender, Kontakte, Zusammenarbeit), optional Nextcloud Office.',
                'notes' => "Speicher (Primär + Backup), RAM/CPU je Nutzerzahl, Office-Server (ressourcenintensiv)\nNextcloud Enterprise-Abo nur, wenn Kunde es wünscht (Lizenzkosten durchreichen)\nUpdate-Zyklen (Major-Releases), Migration bestehender Daten als einmaliger Aufwand",
                'text' => 'Dateien, Kalender, Kontakte und gemeinsames Arbeiten an Dokumenten im Browser – selbstbestimmt statt Big-Tech-Cloud. Wir richten ein, sichern, aktualisieren und helfen, wenn es klemmt.',
                'items' => [
                    $item('Team', 'bis [x] Nutzer, [x] GB Speicher, Updates, Sicherung', 'both', 'Monat'),
                    $item('Business', 'bis [x] Nutzer, [x] GB, Gruppen/Freigaben, Anbindung (LDAP/SSO) nach Absprache', 'both', 'Monat'),
                    $item('Zusatz: Nextcloud Office', 'Dokumente im Browser bearbeiten (Office-Server), je Instanz', 'both', 'je Instanz'),
                    $item('Zusatz: Speicher', 'je weitere [x] GB', 'monthly', 'je GB'),
                ]],
            ['id' => 's4', 'name' => 'Support & Wartung',
                'desc' => 'Kontingente für Support per E-Mail/Telefon und im Hilfesystem von KLXM Studio (größere Projekte).',
                'notes' => "Nicht verbrauchte Stunden: verfallen / übertragbar? festlegen\nMehraufwand nach Stundensatz Support\nReaktionszeit je Kontingent in der Beschreibung festhalten",
                'text' => 'Persönlich statt Ticketschleife: per E-Mail, Telefon und bei größeren Projekten direkt im Hilfesystem von KLXM Studio.',
                'items' => [
                    $item('Kontingent S', 'Fragen, kleine Änderungen · Reaktionszeit: [festlegen]', 'monthly', 'Monat', ['monthly_basis' => 'support']),
                    $item('Kontingent M', 'regelmäßige Pflege, Inhalte, kleine Erweiterungen · Reaktionszeit: [festlegen]', 'monthly', 'Monat', ['monthly_basis' => 'support']),
                    $item('Kontingent L', 'feste Ansprechzeiten, Weiterentwicklung mit Plan · Reaktionszeit: [festlegen]', 'monthly', 'Monat', ['monthly_basis' => 'support']),
                ]],
            ['id' => 's5', 'name' => 'Projekte',
                'desc' => 'Einmalige Leistungen – Schätzung je Baustein (Stunden je Einheit × Satz, mit Projektpuffer).',
                'notes' => "Einheiten an echte Projekte anpassen\nAnzahl Module/Seiten und Puffer bestimmen den Preis",
                'text' => '',
                'items' => [
                    $item('Kit nach Maß', 'eigenes Design-Kit auf Basis Start-Kit (Tokens, Blöcke, Datenstruktur)', 'once', 'pauschal', ['once_basis' => 'konzept', 'buffer' => true]),
                    $item('Block / Erweiterung', 'je Block bzw. Erweiterung (Felder, Vorlage, Tests)', 'once', 'je Block', ['once_basis' => 'dev', 'buffer' => true]),
                    $item('Migration REDAXO → Studio: Analyse', 'Analysebericht, Modul-Zuordnung', 'once', 'pauschal', ['once_basis' => 'dev', 'buffer' => true]),
                    $item('Migration: je Modul-Zuordnung', 'Modul → Block inkl. Vorlage', 'once', 'je Modul', ['once_basis' => 'dev', 'buffer' => true]),
                    $item('Migration: Inhalte je 100 Seiten', 'Import, Kontrolle, Weiterleitungen', 'once', 'je 100 Seiten', ['once_basis' => 'dev', 'buffer' => true]),
                    $item('Schulung Redaktion', 'je Termin', 'once', 'je Termin', ['once_basis' => 'konzept', 'buffer' => true]),
                ]],
        ], 'next' => $n + 1];
    }
}
