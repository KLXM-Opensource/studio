<?php
// SPDX-License-Identifier: MIT
// Portions ported from FriendsOfREDAXO/consent_kit lib/Log.php (MIT, © KLXM Crossmedia GmbH)
declare(strict_types=1);

namespace MyCms\Consent;

/**
 * Protokoll als Nachweis (Art. 7 Abs. 1 DSGVO) – datensparsam: Einwilligungs-ID, Zeit, Domain, Stand, Entscheidung,
 * akzeptierte/abgelehnte Dienste, GPC-Signal, Sprache. NICHT gespeichert: IP-Adresse, User-Agent, aufgerufene Seite.
 */
final class Log
{
    public const ACTIONS = ['accept_all' => 'Alle akzeptiert', 'reject_all' => 'Alle abgelehnt', 'custom' => 'Auswahl', 'embed' => 'Über Platzhalter erlaubt', 'withdraw' => 'Widerrufen'];

    public static function add(string $id, string $action, array $accepted, array $rejected, int $revision, string $domain, bool $gpc, string $lang): void
    {
        app()->db->insert('consent_log', [
            'consent_id' => $id, 'created_at' => now(), 'host' => $domain, 'revision_id' => $revision, 'action' => $action,
            'accepted' => json_encode(array_values($accepted)), 'rejected' => json_encode(array_values($rejected)), 'gpc' => $gpc ? 1 : 0, 'lang' => $lang,
        ]);
    }

    public static function count(): int
    {
        return Repository::ready() ? (int) app()->db->fetchValue('SELECT COUNT(*) FROM consent_log') : 0;
    }

    /** Einträge älter als $days Tage löschen (null = Einstellung, 0 = nie). @return int Anzahl */
    public static function purge(?int $days = null): int
    {
        if (!Repository::ready()) return 0;
        $days ??= (int) Repository::settings()['retention'];
        if ($days <= 0) return 0;
        $cut = date('Y-m-d H:i:s', time() - $days * 86400);
        $n = (int) app()->db->fetchValue('SELECT COUNT(*) FROM consent_log WHERE created_at < ?', [$cut]);
        if ($n) app()->db->query('DELETE FROM consent_log WHERE created_at < ?', [$cut]);
        app()->settings->set('consent.purged_at', now());
        return $n;
    }

    /** Nebenbei in der Verwaltung (höchstens einmal täglich) – zusätzlich zu Cron/CLI consent:purge */
    public static function maybePurge(): void
    {
        $last = (string) app()->settings->get('consent.purged_at', '');
        if ($last === '' || strtotime($last) < time() - 86400) self::purge();
    }

    /** Filter: id, action, from (JJJJ-MM-TT), to, domain */
    private static function where(array $f): array
    {
        $w = ['1=1'];
        $p = [];
        if (($f['id'] ?? '') !== '') { $w[] = 'consent_id = ?'; $p[] = (string) $f['id']; }
        if (isset(self::ACTIONS[$f['action'] ?? ''])) { $w[] = 'action = ?'; $p[] = (string) $f['action']; }
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) ($f['from'] ?? ''))) { $w[] = 'created_at >= ?'; $p[] = $f['from'] . ' 00:00:00'; }
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) ($f['to'] ?? ''))) { $w[] = 'created_at <= ?'; $p[] = $f['to'] . ' 23:59:59'; }
        if (($f['domain'] ?? '') !== '') { $w[] = 'host = ?'; $p[] = (string) $f['domain']; }
        return [implode(' AND ', $w), $p];
    }

    public static function list(array $f, int $limit = 50, int $offset = 0): array
    {
        [$w, $p] = self::where($f);
        $rows = app()->db->fetchAll("SELECT * FROM consent_log WHERE $w ORDER BY id DESC LIMIT " . max(1, $limit) . ' OFFSET ' . max(0, $offset), $p);
        return array_map(fn($r) => ['accepted' => json_decode((string) $r['accepted'], true) ?: [], 'rejected' => json_decode((string) $r['rejected'], true) ?: []] + $r, $rows);
    }

    public static function total(array $f): int
    {
        [$w, $p] = self::where($f);
        return (int) app()->db->fetchValue("SELECT COUNT(*) FROM consent_log WHERE $w", $p);
    }

    /** Kennzahlen der letzten $days Tage – je Einwilligungs-ID zählt nur die letzte Entscheidung */
    public static function stats(int $days = 30): array
    {
        $since = date('Y-m-d H:i:s', time() - $days * 86400);
        $rows = app()->db->fetchAll('SELECT l.consent_id, l.action, l.accepted FROM consent_log l
            JOIN (SELECT consent_id, MAX(id) AS mid FROM consent_log WHERE created_at >= ? GROUP BY consent_id) x ON x.mid = l.id', [$since]);
        $actions = array_fill_keys(array_keys(self::ACTIONS), 0);
        $services = [];
        foreach ($rows as $r) {
            $actions[$r['action']] = ($actions[$r['action']] ?? 0) + 1;
            foreach ((array) json_decode((string) $r['accepted'], true) as $k) $services[$k] = ($services[$k] ?? 0) + 1;
        }
        arsort($services);
        return ['total' => count($rows), 'actions' => $actions, 'services' => $services];
    }

    /** CSV (Semikolon, UTF-8 mit BOM – öffnet direkt in Excel) mit den Filtern der Ansicht */
    public static function csv(array $f): string
    {
        $out = "\xEF\xBB\xBF" . implode(';', ['ID', 'Einwilligungs-ID', 'Zeitpunkt', 'Domain', 'Stand', 'Entscheidung', 'Akzeptiert', 'Abgelehnt', 'GPC', 'Sprache']) . "\r\n";
        $cell = function ($v): string {
            $v = (string) $v;
            if ($v !== '' && str_contains('=+-@', $v[0])) $v = "'" . $v;   // keine Formeln in Tabellenprogrammen
            return '"' . str_replace('"', '""', $v) . '"';
        };
        for ($off = 0; ; $off += 1000) {
            $rows = self::list($f, 1000, $off);
            foreach ($rows as $r) {
                $out .= implode(';', array_map($cell, [$r['id'], $r['consent_id'], $r['created_at'], $r['host'], $r['revision_id'], __(self::ACTIONS[$r['action']] ?? $r['action']),
                    implode(', ', $r['accepted']), implode(', ', $r['rejected']), $r['gpc'] ? '1' : '0', $r['lang']])) . "\r\n";
            }
            if (count($rows) < 1000) break;
        }
        return $out;
    }
}
