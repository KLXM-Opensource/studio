<?php
declare(strict_types=1);

namespace Core\Data;

/**
 * Versionen von Einträgen – wie bei Seiten (Tabelle revisions): jeder gespeicherte Stand eines Eintrags landet in
 * entry_revisions (Datenbank der Website, auch für geteilte Tabellen), höchstens config 'revisions' (Standard 20) je Eintrag.
 *
 *  - Entries::save ruft record() nach jedem Speichern auf (Verwaltung, Website, API, MCP, DAV, Abgleich externer Quellen
 *    nicht – die Quelle ist dort die Wahrheit). Beim ersten Ändern eines Eintrags ohne Versionen wird zuerst der bisherige
 *    Stand gesichert („Ausgangsstand“), damit es immer etwas zum Zurückgehen gibt.
 *  - Gleiche Stände hintereinander werden nicht doppelt gespeichert (Vergleich ohne Zeitstempel).
 *  - restore(): Feldwerte des Stands per Entries::save zurückschreiben (Status bleibt, wie er ist) – das ergibt selbst eine Version.
 *  - Ansicht „Versionen“ (resources/js/versions.mjs): Admin\VersionsController::entryVersions / entryRestore.
 */
final class Revisions
{
    /** Diese Werte gehören nicht zum Inhalt eines Stands */
    private const SKIP = ['id', 'created_at', 'updated_at', 'published_at', 'sort', 'origin_site', 'suggest'];

    public static bool $off = false;   // z. B. beim Wiederherstellen kein doppelter Eintrag durch record() in save()

    public static function keep(): int
    {
        return max(5, (int) app()->config->get('revisions', 20));
    }

    /** Inhalt eines Eintrags für eine Version: Feldwerte + slug, lang, status */
    public static function snapshot(array $table, array $e): array
    {
        $out = [];
        foreach ($table['fields'] as $f) $out[$f['name']] = $e[$f['name']] ?? null;
        foreach (['slug', 'lang', 'status'] as $k) if (array_key_exists($k, $e)) $out[$k] = $e[$k];
        return $out;
    }

    /** Stand speichern (nach dem Speichern bzw. als Ausgangsstand) */
    public static function record(array $table, array $entry, string $note = ''): void
    {
        if (self::$off || empty($entry['id'])) return;
        try {
            $db = app()->db;
            $json = json_encode(self::snapshot($table, $entry), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $last = $db->fetchValue('SELECT data_json FROM entry_revisions WHERE tbl = ? AND entry_id = ? ORDER BY id DESC LIMIT 1', [$table['handle'], (int) $entry['id']]);
            if ($last !== null && $last === $json) return;
            $u = app()->auth->user() ?? null;
            $db->insert('entry_revisions', ['tbl' => $table['handle'], 'entry_id' => (int) $entry['id'], 'data_json' => $json,
                'status' => (string) ($entry['status'] ?? ''), 'created_at' => now(), 'user_id' => $u ? (int) $u['id'] : null,
                'user_email' => $u ? (string) $u['email'] : null, 'note' => mb_substr($note, 0, 180)]);
            $ids = $db->fetchAll('SELECT id FROM entry_revisions WHERE tbl = ? AND entry_id = ? ORDER BY id DESC', [$table['handle'], (int) $entry['id']]);
            foreach (array_slice($ids, self::keep()) as $r) $db->query('DELETE FROM entry_revisions WHERE id = ?', [(int) $r['id']]);
        } catch (\Throwable $e) {
            error_log('[Versionen] ' . $e->getMessage());   // Versionen dürfen das Speichern nie verhindern
        }
    }

    /** Hat der Eintrag schon Versionen? (sonst sichert save() vorher den Ausgangsstand) */
    public static function has(array $table, int $id): bool
    {
        try {
            return (bool) app()->db->fetchValue('SELECT 1 FROM entry_revisions WHERE tbl = ? AND entry_id = ? LIMIT 1', [$table['handle'], $id]);
        } catch (\Throwable) {
            return true;   // Tabelle fehlt (vor der Migration): nichts tun
        }
    }

    /** Versionen, neueste zuerst: [['id', 'created_at', 'note', 'user_email', 'status', 'data' => [...]], …] */
    public static function list(array $table, int $id): array
    {
        try {
            $rows = app()->db->fetchAll('SELECT * FROM entry_revisions WHERE tbl = ? AND entry_id = ? ORDER BY id DESC', [$table['handle'], $id]);
        } catch (\Throwable) {
            return [];
        }
        return array_map(fn($r) => ['id' => (int) $r['id'], 'created_at' => (string) $r['created_at'], 'note' => (string) ($r['note'] ?? ''),
            'user_email' => (string) ($r['user_email'] ?? ''), 'status' => (string) ($r['status'] ?? ''),
            'data' => (array) (json_decode((string) $r['data_json'], true) ?: [])], $rows);
    }

    public static function find(array $table, int $id, int $rev): ?array
    {
        foreach (self::list($table, $id) as $r) if ($r['id'] === $rev) return $r;
        return null;
    }

    /** Stand zurückschreiben (Status bleibt). @return array errors (leer = ok) */
    public static function restore(array $table, int $id, int $rev): array
    {
        $r = self::find($table, $id, $rev);
        if (!$r) return ['_' => __('Version nicht gefunden.')];
        $in = $r['data'];
        unset($in['status']);
        foreach (self::SKIP as $k) unset($in[$k]);
        [$ok, $errors] = Entries::save($table, $id, $in, __('Wiederhergestellt (Stand {date})', ['date' => $r['created_at']]));
        return $ok ? [] : $errors;
    }

    /** Beim Löschen eines Eintrags */
    public static function forget(array $table, int $id): void
    {
        try {
            app()->db->query('DELETE FROM entry_revisions WHERE tbl = ? AND entry_id = ?', [$table['handle'], $id]);
        } catch (\Throwable) {
        }
    }
}
