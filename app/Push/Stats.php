<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Database;

/**
 * Statistik der Push-Benachrichtigungen – datensparsam: nur Zähler je Tag, Kanal und Art (Tabelle push_stats), nie Endpunkte,
 * IP-Adressen, Konten oder Geräte. Arten: sub (neues Abo eines Kanals), unsub (abbestellt), gone (vom Push-Dienst abgelaufen bzw.
 * nach Fehlversuchen oder Schlüsselwechsel gelöscht). Kanal „@staff“ = Geräte der Redaktion (Konto → Benachrichtigungen).
 * Aktueller Stand kommt aus push_subscriptions, Versand aus push_messages (Zähler je Nachricht, 90 Tage).
 */
final class Stats
{
    public const STAFF = '@staff';
    public const KINDS = ['sub', 'unsub', 'gone'];

    public static function ensureTable(Database $db): void
    {
        $tail = $db->driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->query("CREATE TABLE IF NOT EXISTS push_stats (day VARCHAR(10) NOT NULL, topic VARCHAR(80) NOT NULL, kind VARCHAR(8) NOT NULL,
            n INT NOT NULL DEFAULT 0, PRIMARY KEY (day, topic, kind))$tail");
    }

    /** Zähler erhöhen (wirft nie – Statistik darf ein Abo nie verhindern) */
    public static function hit(string $topic, string $kind, int $n = 1, ?Database $db = null): void
    {
        if ($n <= 0 || !in_array($kind, self::KINDS, true) || ($topic !== self::STAFF && !Push::validTopic($topic))) return;
        $db ??= app()->db;
        try {
            $sql = $db->driver === 'mysql'
                ? 'INSERT INTO push_stats (day, topic, kind, n) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE n = n + VALUES(n)'
                : 'INSERT INTO push_stats (day, topic, kind, n) VALUES (?, ?, ?, ?) ON CONFLICT(day, topic, kind) DO UPDATE SET n = n + excluded.n';
            $db->query($sql, [date('Y-m-d'), $topic, $kind, $n]);
        } catch (\Throwable $e) {
            error_log('[push] Statistik: ' . $e->getMessage());
        }
    }

    /** Abgänge eines gelöschten Abos zählen (alle Kanäle + ggf. Gerät der Redaktion) */
    public static function lost(array $row, string $kind, ?Database $db = null): void
    {
        foreach (Push::topicsOf($row) as $t) self::hit($t, $kind, 1, $db);
        if (!empty($row['user_id'])) self::hit(self::STAFF, $kind, 1, $db);
    }

    /** Aktuelle Abos je Kanal (nur gültiger Schlüssel) */
    public static function current(): array
    {
        $out = [];
        foreach (app()->db->fetchAll("SELECT topics FROM push_subscriptions WHERE topics IS NOT NULL AND topics != '' AND key_fp = ?", [Keys::fingerprint()]) as $r) {
            foreach (Push::topicsOf($r) as $t) $out[$t] = ($out[$t] ?? 0) + 1;
        }
        return $out;
    }

    /**
     * Zu- und Abgänge je Tag (die letzten $days Tage, ältester zuerst). $topic null = alle Kanäle (ohne Redaktion).
     * → [Y-m-d => ['sub' => n, 'lost' => n]]
     */
    public static function daily(?string $topic, int $days = 30): array
    {
        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) $out[date('Y-m-d', strtotime("-$i days"))] = ['sub' => 0, 'lost' => 0];
        $sql = 'SELECT day, kind, SUM(n) AS n FROM push_stats WHERE day >= ? AND ' . ($topic === null ? 'topic != ?' : 'topic = ?') . ' GROUP BY day, kind';
        foreach (app()->db->fetchAll($sql, [$from, $topic ?? self::STAFF]) as $r) {
            if (!isset($out[$r['day']])) continue;
            $out[$r['day']][$r['kind'] === 'sub' ? 'sub' : 'lost'] += (int) $r['n'];
        }
        return $out;
    }

    /** Wöchentlich zusammengefasst (Montag als Schlüssel, $weeks Wochen, älteste zuerst) */
    public static function weekly(?string $topic, int $weeks = 12): array
    {
        $out = [];
        $mon = strtotime('monday this week');
        for ($i = $weeks - 1; $i >= 0; $i--) $out[date('Y-m-d', $mon - $i * 7 * 86400)] = ['sub' => 0, 'lost' => 0];
        foreach (self::daily($topic, $weeks * 7 + 7) as $day => $v) {
            $k = date('Y-m-d', strtotime('monday this week', strtotime($day)));
            if (!isset($out[$k])) continue;
            $out[$k]['sub'] += $v['sub'];
            $out[$k]['lost'] += $v['lost'];
        }
        return $out;
    }

    /**
     * Versand je Woche: Nachrichten, zugestellt, nicht zugestellt (fehlgeschlagen + abgelaufen + Abo ungültig), Quote in %.
     * Nur abgeschlossene bzw. laufende Nachrichten, keine geplanten oder abgebrochenen.
     */
    public static function deliveries(int $weeks = 12): array
    {
        $out = [];
        $mon = strtotime('monday this week');
        for ($i = $weeks - 1; $i >= 0; $i--) $out[date('Y-m-d', $mon - $i * 7 * 86400)] = ['messages' => 0, 'sent' => 0, 'lost' => 0];
        $from = array_key_first($out) . ' 00:00:00';
        foreach (app()->db->fetchAll("SELECT created_at, sent, failed, gone, expired FROM push_messages
            WHERE created_at >= ? AND COALESCE(status, 'sent') NOT IN ('scheduled', 'canceled', 'draft') AND kind != 'test'", [$from]) as $m) {
            $k = date('Y-m-d', strtotime('monday this week', strtotime((string) $m['created_at'])));
            if (!isset($out[$k])) continue;
            $out[$k]['messages']++;
            $out[$k]['sent'] += (int) $m['sent'];
            $out[$k]['lost'] += (int) $m['failed'] + (int) $m['gone'] + (int) $m['expired'];
        }
        foreach ($out as &$w) $w['rate'] = $w['sent'] + $w['lost'] > 0 ? (int) round($w['sent'] / ($w['sent'] + $w['lost']) * 100) : null;
        return $out;
    }

    /** Geräte der Redaktion je Ereignis (Schalter an + Recht) – ohne Namen: [Ereignis => ['label' => …, 'devices' => n, 'people' => n]] */
    public static function staff(): array
    {
        $db = app()->db;
        $fp = Keys::fingerprint();
        $users = $db->fetchAll('SELECT u.id, u.role, u.disabled, COUNT(s.id) AS devices FROM users u JOIN push_subscriptions s ON s.user_id = u.id
            WHERE s.key_fp = ? GROUP BY u.id, u.role, u.disabled', [$fp]);
        $roles = \Core\Permissions::roles();
        $out = [];
        foreach (Push::events() as $k => $ev) {
            if (!Push::featureOn($ev['feature'] ?? null)) continue;
            $d = 0;
            $p = 0;
            foreach ($users as $u) {
                if (!Push::userCan($u, $ev['perm'] ?? null, null, $roles) || !(Push::userEvents((int) $u['id'])[$k] ?? false)) continue;
                $d += (int) $u['devices'];
                $p++;
            }
            $out[$k] = ['label' => (string) $ev['label'], 'devices' => $d, 'people' => $p];
        }
        return $out;
    }

    /** Protokoll: Zu-/Abgänge je Tag und Kanal (nur Zähler), neueste zuerst → [['day', 'topic', 'sub', 'unsub', 'gone'], …] */
    public static function log(int $days = 60, int $limit = 200): array
    {
        $rows = [];
        foreach (app()->db->fetchAll('SELECT day, topic, kind, n FROM push_stats WHERE day >= ? ORDER BY day DESC, topic', [date('Y-m-d', strtotime('-' . $days . ' days'))]) as $r) {
            $k = $r['day'] . '|' . $r['topic'];
            $rows[$k] ??= ['day' => $r['day'], 'topic' => $r['topic'], 'sub' => 0, 'unsub' => 0, 'gone' => 0];
            $rows[$k][$r['kind']] = (int) $r['n'];
        }
        return array_slice(array_values($rows), 0, $limit);
    }

    /** Kennzahlen für die Karte „Mitteilungen“ der Übersicht (zwischengespeichert von Core\Dashboard\Dashboard) */
    public static function card(): array
    {
        $fp = Keys::fingerprint();
        $last = app()->db->fetch("SELECT title, created_at, sent, recipients FROM push_messages WHERE kind != 'test' AND COALESCE(status, 'sent') = 'sent' ORDER BY id DESC LIMIT 1");
        return ['visitors' => (int) app()->db->fetchValue("SELECT COUNT(*) FROM push_subscriptions WHERE topics IS NOT NULL AND topics != '' AND key_fp = ?", [$fp]),
            'days' => self::daily(null, 14), 'scheduled' => (int) app()->db->fetchValue("SELECT COUNT(*) FROM push_messages WHERE status = 'scheduled'"), 'last' => $last];
    }

    /** Alte Zähler löschen (nach 2 Jahren) */
    /**
     * Verlauf zurücksetzen (Verwaltung → Mitteilungen → Statistik, nur Administration): alle Mitteilungen samt Warteschlange und
     * Tageszähler; mit $subscriptions auch alle Abos (Geräte von Besuchern und Redaktion – sie müssen neu zustimmen).
     * @return array{messages: int, subscriptions: int}
     */
    public static function reset(bool $subscriptions = false): array
    {
        $db = app()->db;
        $n = (int) $db->fetchValue('SELECT COUNT(*) FROM push_messages');
        $subs = 0;
        $db->query('DELETE FROM push_queue');
        $db->query('DELETE FROM push_messages');
        $db->query('DELETE FROM push_stats');
        if ($subscriptions) {
            $subs = (int) $db->fetchValue('SELECT COUNT(*) FROM push_subscriptions');
            $db->query('DELETE FROM push_subscriptions');
        }
        return ['messages' => $n, 'subscriptions' => $subs];
    }

    public static function purge(?Database $db = null): void
    {
        ($db ?? app()->db)->query('DELETE FROM push_stats WHERE day < ?', [date('Y-m-d', strtotime('-730 days'))]);
    }

    // ================================================================= Grafik

    /**
     * Zu- und Abgänge als maßstäbliche Säulen (SVG, Breite 100 %): Zugänge nach oben, Abgänge nach unten, Null-Linie, Achsenwerte
     * (größter Wert oben/unten). $rows = [Beschriftung => ['sub' => n, 'lost' => n]]. Farben per CSS (hell/dunkel): .pst-*.
     */
    public static function chart(array $rows, string $label): string
    {
        $n = max(1, count($rows));
        $up = max(1, ...array_values(array_map(fn($r) => (int) $r['sub'], $rows ?: [['sub' => 0]])));
        $down = max(0, ...array_values(array_map(fn($r) => (int) $r['lost'], $rows ?: [['lost' => 0]])));
        // Ein Maßstab für beide Richtungen: Höhe oben/unten im Verhältnis der größten Werte (maßstäblich, nichts abgeschnitten)
        $w = 10;
        $h = 100;
        $unit = ($h - 2) / ($up + $down);
        $hUp = $up * $unit + 1;
        $f = fn(float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        $out = '<svg class="pst-chart" viewBox="0 0 ' . ($n * $w) . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="' . e($label) . '" focusable="false">';
        $out .= '<line class="pst-chart__grid" x1="0" x2="' . ($n * $w) . '" y1="' . $f($hUp - $up * $unit / 2) . '" y2="' . $f($hUp - $up * $unit / 2) . '" vector-effect="non-scaling-stroke"/>';
        $i = 0;
        foreach ($rows as $lab => $r) {
            $s = (int) $r['sub'];
            $l = (int) $r['lost'];
            $out .= '<g><title>' . e($lab . ': +' . $s . ' / −' . $l) . '</title><rect class="pst-chart__hit" x="' . ($i * $w) . '" y="0" width="' . $w . '" height="' . $h . '"/>';
            if ($s > 0) {
                $bh = max(1, $s * $unit);
                $out .= '<rect class="pst-chart__up" x="' . $f($i * $w + 1.5) . '" y="' . $f($hUp - $bh) . '" width="7" height="' . $f($bh) . '"/>';
            }
            if ($l > 0) {
                $bh = max(1, $l * $unit);
                $out .= '<rect class="pst-chart__down" x="' . $f($i * $w + 1.5) . '" y="' . $f($hUp) . '" width="7" height="' . $f($bh) . '"/>';
            }
            $out .= '</g>';
            $i++;
        }
        $hUp = $f($hUp);
        return $out . '<line class="pst-chart__zero" x1="0" x2="' . ($n * $w) . '" y1="' . $hUp . '" y2="' . $hUp . '" vector-effect="non-scaling-stroke"/></svg>';
    }

    /** Lage der Null-Linie in Prozent der Höhe (wie chart()) – für die Achsenbeschriftung */
    public static function zero(array $rows): float
    {
        $sc = self::scale($rows);
        return round((($sc['up'] * (98 / ($sc['up'] + $sc['down']))) + 1), 2);
    }

    /** Größte Werte nach oben/unten (Achsenbeschriftung) */
    public static function scale(array $rows): array
    {
        return ['up' => max(1, ...array_values(array_map(fn($r) => (int) $r['sub'], $rows ?: [['sub' => 0]]))), 'down' => max(0, ...array_values(array_map(fn($r) => (int) $r['lost'], $rows ?: [['lost' => 0]])))];
    }
}
