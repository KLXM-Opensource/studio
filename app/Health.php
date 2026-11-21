<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Prüfungen für `php bin/console health` (Deploy) und die Überwachung (Core\Monitoring\Status):
 * ['Bezeichnung' => true (gut) | false (Fehler) | null (Hinweis)]. Keine Inhalte, keine Personendaten.
 */
final class Health
{
    /** @return array<string, ?bool> */
    public static function checks(): array
    {
        return [
            'PHP >= 8.4' => version_compare(PHP_VERSION, '8.4.0', '>='),
            'Datenbank' => (function ()  { try { return app()->db->fetchValue('SELECT 1') == 1; } catch (\Throwable) { return false; } })(),
            'Datenordner beschreibbar' => is_writable(site()->storage()),
            'Medienordner beschreibbar' => is_writable(site()->mediaDir()),
            'Erweiterungen gd, sodium, pdo, mbstring' => extension_loaded('gd') && extension_loaded('sodium') && extension_loaded('pdo') && extension_loaded('mbstring'),
            // pdo_sqlite immer nötig (Suche, Chat, Pools, Support …); ≥ 3.35 für die Suche (RETURNING), ≥ 3.27 für site:backup (VACUUM INTO).
            // PHP nutzt die SQLite-Bibliothek des Betriebssystems – z. B. AlmaLinux/RHEL 8: 3.26 → zu alt
            'pdo_sqlite mit SQLite >= 3.35 (' . (function () { try { return (new \PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn(); } catch (\Throwable) { return 'fehlt'; } })() . ')'
                => (function () { try { return version_compare((string) (new \PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn(), '3.35.0', '>='); } catch (\Throwable) { return false; } })(),
            'app_key gesetzt' => strlen((string) app()->config->get('app_key')) >= 32,
            'network_key gesetzt (Netzwerk-Anmeldung)' => !\Core\Sites::multi() || strlen((string) (\Core\Network\Network::install()['network_key'] ?? '')) >= 32,
            'Kit kompatibel' => app()->theme->compatible(),
        ] + ['Verwaltung unter ' . \Core\AdminPath::prefix() . (\Core\AdminPath::custom() ? ' (eigene Adresse)' : '') => true]
          + \Core\Data\Shared::health() + \Core\Search\Search::health() + \Core\AI\Ai::health() + \Core\Sources\Sources::health() + \Core\Push\Push::health()
            + \Core\Ffmpeg::health()        // optional: Vorschaubilder für Videos (nur Warnung, nie Fehler)
            + \Core\Ffmpeg::pdfHealth()     // optional: Vorschaubild der 1. Seite von PDFs (poppler-utils)
            + \Core\Extensions::health()    // Prüfungen aktiver Erweiterungen (Extension::health)
            + \Core\Cron::health()          // Geplante Aufgaben: Cronjob läuft, fehlgeschlagene Aufgaben (nur Hinweise)
            + \Core\Integrity::health()     // Integrität: unerwartete Dateien/Daten (letzte Prüfung, \Core\Integrity)
            + (site()->isDefault() ? \Core\Backup\Offsite::health() : [])   // Sicherung außer Haus: je Ziel (gehört zur Installation, einmal)
            + \Core\SeoCheck::health()      // Suchmaschinen gesperrt (Fehler), Domains, Titel/Beschreibungen (Hinweise, \Core\SeoCheck)
            + \Core\KitPackages::health()   // Kit-Pakete: verdeckte Namen, nicht veröffentlichte Assets, Core-Version (nur Hinweise)
            + \Core\Fragments::health()     // überschriebene Kern-Fragmente mit geändertem Original, ignorierte Kit-Dateien (nur Hinweis)
            + \Core\Fonts::health()         // Kit-Schriften installiert? (fehlend: Hinweis – fonts:sync)
            + \Core\Mail\Accounts::health()  // E-Mail-Konten: Netzwerk-Konto, Zwecke mit fehlendem Konto (nur Hinweise)
            + \Core\Mail\Imprint::health();  // Impressum für E-Mails: weder Text noch Impressum-Seite = Fehler
    }
}
