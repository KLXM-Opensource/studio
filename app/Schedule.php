<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Data\Entries;
use Core\Data\Tables;

/**
 * Zeitgesteuert veröffentlichen: „Online ab“ (online_from) und „Offline ab“ (online_until) für Seiten und Einträge eigener Tabellen.
 * Beide Angaben sind geplante Aktionen: Zum Zeitpunkt wird der Inhalt veröffentlicht (Pages::publish, Entries::setStatus) bzw.
 * offline genommen, danach ist das Feld wieder leer. Bis dahin gilt der Status – damit wirken alle Prüfungen auf „published“
 * (Aufruf → 404, Sitemap, Menü, Listen, Suche, Kalender, Feeds, Kits) ohne eigene Zeitlogik.
 *
 * Ausgeführt wird ohne Cron bei der nächsten Anfrage, bevor etwas ausgeliefert wird (App::handle → tick): Ein Vergleich mit dem
 * nächsten fälligen Zeitpunkt (sys.schedule_next) kostet nichts; ist er erreicht, läuft run() unter Sperre, leert Seiten-Cache
 * und Suchindex (PageCache::clear) und berechnet den nächsten Zeitpunkt neu.
 */
final class Schedule
{
    public const KEY = 'sys.schedule_next';

    /** @var array<string,bool> Tabellen mit geprüften Spalten (je Anfrage) */
    private static array $ready = [];

    /** Spalten einer Datentabelle anlegen (additiv); false für Eingangs-Tabellen */
    public static function ensure(array $table): bool
    {
        if (Tables::isInbox($table)) return false;
        $k = $table['table'];
        if (!isset(self::$ready[$k])) {
            Tables::db($table)->ensureColumns($k, ['online_from' => 'VARCHAR(25) NULL', 'online_until' => 'VARCHAR(25) NULL']);
            self::$ready[$k] = true;
        }
        return true;
    }

    /** Eingabe (datetime-local „2026-11-01T08:00“ oder „2026-11-01 08:00“) → „Y-m-d H:i:s“, leer/ungültig → null */
    public static function norm(mixed $v): ?string
    {
        $v = trim(str_replace('T', ' ', (string) $v));
        if ($v === '') return null;
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', substr($v, 0, 16));
        return $d ? $d->format('Y-m-d H:i:00') : null;
    }

    /** Für <input type="datetime-local"> */
    public static function input(?string $v): string
    {
        return $v ? str_replace(' ', 'T', substr($v, 0, 16)) : '';
    }

    /**
     * Eingaben prüfen: „Online ab“ in der Vergangenheit entfällt (sofort), „Offline ab“ muss in der Zukunft und nach „Online ab“ liegen.
     * @return array{0: ?string, 1: ?string, 2: array<string,string>} [online_from, online_until, Fehler je Feld]
     */
    public static function check(mixed $from, mixed $until): array
    {
        $errors = [];
        $f = self::norm($from);
        $u = self::norm($until);
        if (trim((string) $from) !== '' && $f === null) $errors['online_from'] = __('Bitte Datum und Uhrzeit angeben.');
        if (trim((string) $until) !== '' && $u === null) $errors['online_until'] = __('Bitte Datum und Uhrzeit angeben.');
        if ($f !== null && $f <= now()) $f = null;
        if ($u !== null && $u <= now()) $errors['online_until'] = __('„Offline ab“ liegt in der Vergangenheit.');
        elseif ($u !== null && $f !== null && $u <= $f) $errors['online_until'] = __('„Offline ab“ muss nach „Online ab“ liegen.');
        return [$f, $u, $errors];
    }

    /** Wartet der Inhalt auf „Online ab“? */
    public static function pending(array $row): bool
    {
        return !empty($row['online_from']) && $row['online_from'] > now();
    }

    /** Hinweis für Listen und Werkzeugleiste, z. B. „Online ab 01.11.2026, 08:00“ – leer ohne Zeitplan */
    public static function label(array $row): string
    {
        $out = [];
        if (!empty($row['online_from'])) $out[] = __('Online ab {date}', ['date' => fmt()->datetime($row['online_from'])]);
        if (!empty($row['online_until'])) $out[] = __('Offline ab {date}', ['date' => fmt()->datetime($row['online_until'])]);
        return implode(' · ', $out);
    }

    /** Je Anfrage vor der Auslieferung (App::handle): fällige Aktionen ausführen */
    public static function tick(): void
    {
        $next = (string) app()->settings->get(self::KEY, '');
        if ($next === '' || $next > now()) return;
        try {
            self::run();
        } catch (\Throwable $e) {
            error_log('[schedule] ' . $e->getMessage());
        }
    }

    /**
     * Fällige Aktionen ausführen – unter Sperre, damit parallele Anfragen warten und danach den neuen Stand sehen
     * (sonst könnte eine Anfrage den alten Stand in den Seiten-Cache schreiben).
     * @return array{published:int, unpublished:int}
     */
    public static function run(): array
    {
        $res = ['published' => 0, 'unpublished' => 0];
        $lock = @fopen(site()->storage('cache') . '/schedule.lock', 'c');
        if ($lock) flock($lock, LOCK_EX);
        try {
            app()->settings->forget();   // inzwischen von einer anderen Anfrage erledigt?
            $next = (string) app()->settings->get(self::KEY, '');
            if ($next === '' || $next > now()) return $res;
            $now = now();
            $db = app()->db;
            // Seiten: zuerst „Offline ab“ (ist beides fällig, bleibt die Seite offline)
            foreach ($db->fetchAll("SELECT id, online_from FROM pages WHERE online_until IS NOT NULL AND online_until <> '' AND online_until <= ?", [$now]) as $p) {
                $db->update('pages', ['online_until' => null] + (!empty($p['online_from']) && $p['online_from'] <= $now ? ['online_from' => null] : []), 'id = :id', ['id' => (int) $p['id']]);
                if (Pages::unpublish((int) $p['id'])) $res['unpublished']++;
            }
            foreach ($db->fetchAll("SELECT id FROM pages WHERE online_from IS NOT NULL AND online_from <> '' AND online_from <= ?", [$now]) as $p) {
                $db->update('pages', ['online_from' => null], 'id = :id', ['id' => (int) $p['id']]);
                try {
                    Pages::publish((int) $p['id'], null);
                    $res['published']++;
                } catch (\Throwable $e) {   // z. B. offene Platzhalter „[bitte ergänzen: …]“ – bleibt Entwurf
                    error_log('[schedule] page ' . $p['id'] . ': ' . $e->getMessage());
                }
            }
            // Einträge eigener Tabellen (geteilte: nur Einträge dieser Website)
            foreach (Tables::all() as $t) {
                if (!self::ensure($t)) continue;
                $tdb = Tables::db($t);
                [$own, $op] = Tables::isShared($t) ? [' AND origin_site = ?', [site()->key]] : ['', []];
                $off = $tdb->fetchAll("SELECT id, online_from FROM {$t['table']} WHERE online_until IS NOT NULL AND online_until <> '' AND online_until <= ?$own", array_merge([$now], $op));
                foreach ($off as $e) {
                    $tdb->update($t['table'], ['online_until' => null] + (!empty($e['online_from']) && $e['online_from'] <= $now ? ['online_from' => null] : []), 'id = :id', ['id' => (int) $e['id']]);
                }
                if ($off) {
                    Entries::setStatus($t, array_column($off, 'id'), 'draft');
                    $res['unpublished'] += count($off);
                }
                $on = array_column($tdb->fetchAll("SELECT id FROM {$t['table']} WHERE online_from IS NOT NULL AND online_from <> '' AND online_from <= ?$own", array_merge([$now], $op)), 'id');
                if ($on) {
                    $tdb->query("UPDATE {$t['table']} SET online_from = NULL WHERE id IN (" . implode(',', array_map('intval', $on)) . ')');
                    Entries::setStatus($t, $on, 'published');
                    $res['published'] += count($on);
                }
            }
            PageCache::clear();
            self::refresh();
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        return $res;
    }

    /** Nächsten fälligen Zeitpunkt neu berechnen (nach dem Speichern eines Zeitplans und nach run) */
    public static function refresh(): void
    {
        $min = [];
        $pick = function (Database $db, string $tbl, string $own = '', array $op = []) use (&$min): void {
            foreach (['online_from', 'online_until'] as $c) {
                $v = $db->fetchValue("SELECT MIN($c) FROM $tbl WHERE $c IS NOT NULL AND $c <> ''$own", $op);
                if ($v) $min[] = (string) $v;
            }
        };
        $pick(app()->db, 'pages');
        foreach (Tables::all() as $t) {
            if (!self::ensure($t)) continue;
            Tables::isShared($t) ? $pick(Tables::db($t), $t['table'], ' AND origin_site = ?', [site()->key]) : $pick(Tables::db($t), $t['table']);
        }
        $next = $min ? min($min) : '';
        if ((string) app()->settings->get(self::KEY, '') !== $next) app()->settings->set(self::KEY, $next);
    }
}
