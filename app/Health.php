<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Prüfungen für `php bin/console health` (Deploy) und die Überwachung (Core\Monitoring\Status):
 * ['Bezeichnung' => true (gut) | false (Fehler) | null (Hinweis)]. Keine Inhalte, keine Personendaten.
 *
 * Jede Zeile gehört zu einer Kategorie (rows()):
 *   technical  Betrieb und Technik – alarmiert in der Überwachung (Datenbank, Ordner, Versionen, Cron, Sicherung, Integrität …)
 *   setup      Einrichtung – nur anzeigen (fehlende optionale Schlüssel, Werkzeuge, Impressum für E-Mails, Suchmaschinen gesperrt …)
 *   editorial  Redaktion – nur anzeigen (SEO-Hinweise zu Titeln/Beschreibungen, Inhaltsfunde)
 * Ohne Angabe gilt technical, damit nichts Wichtiges verschwindet. Eine Prüfung kann ihre Kategorie je Zeile mitgeben:
 * ['Bezeichnung' => ['ok' => true|false|null, 'category' => 'setup']] (Erweiterungen: Extension::health($fn, 'setup')).
 */
final class Health
{
    public const TECHNICAL = 'technical';
    public const SETUP = 'setup';
    public const EDITORIAL = 'editorial';
    public const CATEGORIES = [self::TECHNICAL, self::SETUP, self::EDITORIAL];
    /** Ende der Sicherheitsupdates je PHP-Version (php.net/supported-versions) */
    public const PHP_EOL = ['8.3' => '2027-12-31', '8.4' => '2028-12-31', '8.5' => '2029-12-31'];

    /** @return array<string, ?bool> */
    public static function checks(): array
    {
        $out = [];
        foreach (self::rows() as $r) $out[$r['label']] = $r['ok'];
        return $out;
    }

    /** @return list<array{label: string, ok: ?bool, category: string}> */
    public static function rows(): array
    {
        $out = [];
        $add = function (array $rows, string $category) use (&$out): void {
            foreach ($rows as $label => $v) {
                $label = (string) $label;
                if (!isset($out[$label])) $out[$label] = self::row($label, $v, $category);   // erste Zeile gewinnt (wie „+“)
            }
        };
        $t = self::TECHNICAL;
        $add([
            'PHP >= 8.4' => version_compare(PHP_VERSION, '8.4.0', '>='),
        ] + self::phpSupport(PHP_VERSION) + [
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
            'Verwaltung unter ' . \Core\AdminPath::prefix() . (\Core\AdminPath::custom() ? ' (eigene Adresse)' : '') => true,
        ], $t);
        $add(\Core\NetworkHost::health(), $t);             // Netzwerk-Domain: führt sie (noch) zur Netzwerk-Website?
        $add(\Core\Data\Shared::health() + \Core\Search\Search::health() + \Core\AI\Ai::health(), $t);
        $add(\Core\Sources\Sources::health(true), $t);       // Abruf-Fehler technisch, fehlende Zieltabelle Einrichtung
        $add(\Core\Push\Push::health(true), $t);             // Versand hängt technisch, VAPID-Schlüssel fehlt Einrichtung
        $add(\Core\Ffmpeg::health() + \Core\Ffmpeg::pdfHealth(), self::SETUP);   // optional: Vorschaubilder für Videos/PDFs (nur Hinweis)
        foreach (\Core\Extensions::healthRows() as $r) {     // Prüfungen aktiver Erweiterungen (Extension::health, Kategorie je Anmeldung)
            if (!isset($out[$r['label']])) $out[$r['label']] = $r;
        }
        $add(\Core\Cron::health(), $t);                      // Geplante Aufgaben: Cronjob läuft, fehlgeschlagene Aufgaben
        $add(\Core\Integrity::health(), $t);                 // Integrität: unerwartete Dateien/Daten (letzte Prüfung, \Core\Integrity)
        $add(site()->isDefault() ? \Core\Backup\Offsite::health() : [], $t);   // Sicherung außer Haus: je Ziel (gehört zur Installation, einmal)
        $add(\Core\SeoCheck::health(true), self::EDITORIAL);   // Suchmaschinen gesperrt/Domains: Einrichtung, Titel/Beschreibungen: Redaktion
        $add(\Core\KitPackages::health(true), $t);           // Kit-Pakete: Core-Version, Assets technisch; verdeckte Kits Einrichtung
        $add(\Core\Fragments::health(true), $t);             // geänderte Kern-Fragmente technisch, ignorierte Kit-Dateien Einrichtung
        $add(\Core\Fonts::health(), self::SETUP);            // Kit-Schriften installiert? (fehlend: Hinweis – fonts:sync)
        $add(\Core\Mail\Accounts::health(), self::SETUP);    // E-Mail-Konten: Netzwerk-Konto, Zwecke mit fehlendem Konto
        $add(\Core\Mail\Imprint::health(), self::SETUP);     // Impressum für E-Mails: weder Text noch Impressum-Seite
        $add(\Core\RoleCleanup::health(), self::SETUP);     // Rollen mit unbekannten Rechten (entfernt nur roles:clean)
        return array_values($out);
    }

    /** Zeile vereinheitlichen: Wert true|false|null oder ['ok' => …, 'category' => …] */
    public static function row(string $label, mixed $v, string $category = self::TECHNICAL): array
    {
        if (is_array($v)) return ['label' => $label, 'ok' => self::value($v['ok'] ?? null), 'category' => self::category($v['category'] ?? null, $category)];
        return ['label' => $label, 'ok' => self::value($v), 'category' => self::category($category)];
    }

    /** true | false | null (Hinweis) – auch aus einer Zeile ['ok' => …] */
    public static function value(mixed $v): ?bool
    {
        if (is_array($v)) $v = $v['ok'] ?? null;
        return $v === null ? null : (bool) $v;
    }

    /** Kategorie prüfen (auch deutsch: technisch, einrichtung, redaktionell); unbekannt → $default */
    public static function category(mixed $c, string $default = self::TECHNICAL): string
    {
        $c = strtolower(trim((string) $c));
        $c = ['technisch' => self::TECHNICAL, 'einrichtung' => self::SETUP, 'redaktionell' => self::EDITORIAL, 'redaktion' => self::EDITORIAL][$c] ?? $c;
        if (in_array($c, self::CATEGORIES, true)) return $c;
        return in_array($default, self::CATEGORIES, true) ? $default : self::TECHNICAL;
    }

    /** Wert mit Kategorie, wenn gewünscht (Prüfungen mit gemischten Zeilen: health(true)) */
    public static function cat(?bool $ok, string $category, bool $categorized): array|bool|null
    {
        return $categorized ? ['ok' => $ok, 'category' => $category] : $ok;
    }

    /**
     * Sicherheitsupdates der PHP-Version: bis 180 Tage vor dem Ende gut, danach Hinweis, nach dem Ende Fehler.
     * Unbekannte (neuere) Versionen: keine Zeile. @return array<string, ?bool>
     */
    public static function phpSupport(string $version, ?int $now = null): array
    {
        $branch = implode('.', array_slice(explode('.', $version), 0, 2));
        $eol = self::PHP_EOL[$branch] ?? null;
        if ($eol === null) return [];
        $end = (int) strtotime($eol . ' 23:59:59');
        $now ??= time();
        $date = date('d.m.Y', $end);
        if ($now > $end) return ["PHP $branch: keine Sicherheitsupdates mehr seit $date – PHP aktualisieren" => false];
        if ($now > $end - 180 * 86400) return ["PHP $branch: Sicherheitsupdates enden am $date – PHP-Wechsel planen" => null];
        return ["PHP $branch: Sicherheitsupdates bis $date" => true];
    }
}
