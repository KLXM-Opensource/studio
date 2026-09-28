<?php
declare(strict_types=1);

namespace Core\Review;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Database;
use Core\Features;
use Core\Lang;
use Core\Pages;

/**
 * Entwürfe (Verwaltung → Entwürfe, /admin/entwuerfe): alles, was geprüft und veröffentlicht – oder verworfen – werden sollte.
 *
 * Quellen (nur die aktuelle Website, gefiltert nach den Rechten der angemeldeten Person):
 * - Seiten im Status „Entwurf“ (neu bzw. offline) und veröffentlichte Seiten, deren Arbeitsstand sich von der
 *   veröffentlichten Fassung unterscheidet (blockweise verglichen, der Zeitstempel des Editors zählt nicht),
 * - Einträge eigener Datentabellen (nicht geteilt, keine Eingänge) mit Status „draft“.
 *
 * Jeder Entwurf hat einen Schlüssel („page:12“, „entry:news:5“). Dazu speichert draft_notes eine Notiz
 * („wartet auf Freigabe durch …“) und optional eine zuständige Person. Verworfene Einträge (Datentabellen haben keine
 * Versionen) sichert draft_discards als JSON – wiederherstellbar; verworfene Seiten-Entwürfe bleiben als Version erhalten
 * (Pages::discardDraft).
 *
 * Erweiterbar: Weitere Quellen (z. B. offene Einreichungen der Prüf-Ebene, Core\Review\Queue) liefern Einträge im selben
 * Format (item()) mit eigenem Schlüssel („change:ID“); Liste, Filter, Notizen und die Unterschieds-Ansicht
 * (review/_diff.php, Core\Review\Diff) sind dafür schon ausgelegt.
 */
final class Drafts
{
    /** Ab so vielen Tagen ohne Änderung gilt ein Entwurf als „vergessen?“ (wie der Hinweis auf der Übersicht) */
    public const STALE_DAYS = 14;
    public const FILTERS = ['all', 'mine', 'review', 'stale', 'auto'];
    /** Verworfene Einträge so lange aufbewahren */
    private const KEEP_DISCARDS_DAYS = 90;

    private static ?int $count = null;

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $long = $my ? 'LONGTEXT' : 'TEXT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->query("CREATE TABLE IF NOT EXISTS draft_notes (id $pk, item VARCHAR(120) NOT NULL UNIQUE, note TEXT NULL, assignee_id INT NULL,
            user_id INT NULL, updated_at VARCHAR(25))$tail");
        $db->query("CREATE TABLE IF NOT EXISTS draft_discards (id $pk, table_handle VARCHAR(64) NOT NULL, entry_id INT NOT NULL,
            label VARCHAR(191) NULL, data_json $long, user_id INT NULL, created_at VARCHAR(25))$tail");
    }

    // ================================================================= Liste

    /** Tabellen mit Entwürfen: eigene Inhaltstabellen (geteilte Tabellen haben Einträge mehrerer Websites) */
    private static function tables(bool $checkRights = true): array
    {
        if (!Features::on('data', false)) return [];
        return array_values(array_filter(Tables::content(), fn($t) => !Tables::isShared($t) && (!$checkRights || can('data.edit', $t['handle']))));
    }

    /** Darf die angemeldete Person die Entwürfe-Übersicht sehen? */
    public static function canView(): bool
    {
        return can('pages.edit') || (bool) self::tables();
    }

    /** Arbeitsstand ≠ veröffentlichte Fassung (nur Blöcke – ein erneutes Speichern ohne Änderung zählt nicht) */
    public static function pageChanged(array $p): bool
    {
        if (($p['content_draft'] ?? null) === null || ($p['content_published'] ?? null) === null) return false;
        if ($p['content_draft'] === $p['content_published']) return false;
        $b = fn(string $json) => json_encode(json_decode($json, true)['blocks'] ?? []);
        return $b((string) $p['content_draft']) !== $b((string) $p['content_published']);
    }

    /**
     * Alle Entwürfe, die die angemeldete Person bearbeiten darf, neueste Änderung zuerst.
     * @return list<array> siehe item()
     */
    public static function items(): array
    {
        $out = [];
        $user = app()->auth->user();
        $uid = (int) ($user['id'] ?? 0);
        $notes = self::notes();
        $users = self::userNames();
        if (can('pages.edit')) {
            // Seiten und die Seite „Nicht gefunden (404)“ (Core\NotFound) – Detailseiten-Vorlagen nicht
            $rows = app()->db->fetchAll("SELECT * FROM pages WHERE " . \Core\NotFound::sqlPagesAnd404() . " AND (status != 'published'
                OR (content_draft IS NOT NULL AND content_published IS NOT NULL AND content_draft != content_published))");
            $rows = array_values(array_filter($rows, fn($p) => $p['status'] !== 'published' || self::pageChanged($p)));
            $last = self::lastRevisions(array_map(fn($p) => (int) $p['id'], $rows));
            $log = self::changeLog('page');
            foreach ($rows as $p) {
                $id = (int) $p['id'];
                $rev = $last[$id] ?? null;
                $origin = self::originFromNote((string) ($rev['note'] ?? ''));
                if ($origin === null && ($c = $log[(string) $id] ?? null) && (string) $c['created_at'] >= (string) ($p['published_at'] ?? '')) $origin = self::originFromLog($c);
                $state = $p['status'] === 'published' ? 'changed' : ($p['content_published'] !== null ? 'offline' : 'new');
                $out[] = self::item([
                    'key' => 'page:' . $id, 'type' => 'page', 'id' => $id,
                    'title' => \Core\NotFound::isPage($p) ? __('Nicht gefunden (404)') . ' · ' . $p['title'] : (string) $p['title'], 'lang' => Lang::norm($p['lang'] ?? null),
                    'state' => $state, 'updated_at' => (string) ($p['updated_at'] ?? ''),
                    'by_id' => isset($rev['user_id']) ? (int) $rev['user_id'] : null, 'origin' => $origin,
                    'edit' => Pages::plainUrl($p) . '?edit=1', 'settings' => '/admin/pages/' . $id,
                    'can_publish' => can('pages.publish'),
                    'can_discard' => $state !== 'new' && self::pageChanged($p),
                ], $notes, $users, $uid);
            }
        }
        foreach (self::tables() as $t) {
            try {
                $rows = Entries::query($t, ['status' => 'draft', 'lang' => 'all', 'sort' => 'updated_at', 'dir' => 'desc', 'limit' => 500]);
            } catch (\Throwable $e) {
                error_log('[drafts] ' . $t['handle'] . ': ' . $e->getMessage());
                continue;
            }
            $log = $rows ? self::changeLog('entry', $t['handle'] . ':') : [];
            foreach ($rows as $e) {
                $id = (int) $e['id'];
                $c = $log[$t['handle'] . ':' . $id] ?? null;
                $external = \Core\Sources\Sources::isExternal($t, $id);
                $out[] = self::item([
                    'key' => 'entry:' . $t['handle'] . ':' . $id, 'type' => 'entry', 'id' => $id, 'table' => $t,
                    'title' => Entries::title($t, $e), 'lang' => Lang::norm($e['lang'] ?? null),
                    'state' => empty($e['published_at']) ? 'new' : 'offline', 'updated_at' => (string) ($e['updated_at'] ?? ''),
                    'by_id' => $c && $c['user_id'] !== null ? (int) $c['user_id'] : null,
                    'origin' => $external ? ['source', __('Externe Quelle')] : ($c ? self::originFromLog($c) : null),
                    'edit' => '/admin/data/' . $t['handle'] . '/' . $id,
                    'can_publish' => can('data.publish', $t['handle']),
                    'can_discard' => can('data.delete', $t['handle']) && !$external,
                ], $notes, $users, $uid);
            }
        }
        usort($out, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
        return $out;
    }

    /** Einheitlicher Listeneintrag – auch für spätere Quellen (Einreichungen o. Ä.) */
    private static function item(array $i, array $notes, array $users, int $uid): array
    {
        $n = $notes[$i['key']] ?? null;
        $i += ['table' => null, 'settings' => null, 'by_id' => null, 'origin' => null];
        $i['by'] = $i['by_id'] !== null ? ($i['by_id'] === $uid ? __('Sie') : ($users[$i['by_id']] ?? '')) : '';
        $i['origin_key'] = $i['origin'][0] ?? '';
        $i['origin_label'] = $i['origin'][1] ?? '';
        $i['note'] = (string) ($n['note'] ?? '');
        $i['assignee_id'] = isset($n['assignee_id']) ? (int) $n['assignee_id'] : null;
        $i['assignee'] = $i['assignee_id'] !== null ? ($users[$i['assignee_id']] ?? '') : '';
        $i['note_by'] = isset($n['user_id']) ? ($users[(int) $n['user_id']] ?? '') : '';
        $i['note_at'] = (string) ($n['updated_at'] ?? '');
        $i['stale'] = $i['updated_at'] !== '' && $i['updated_at'] < date('Y-m-d H:i:s', time() - self::STALE_DAYS * 86400);
        $i['mine'] = ($i['by_id'] !== null && $i['by_id'] === $uid) || ($i['assignee_id'] !== null && $i['assignee_id'] === $uid);
        return $i;
    }

    /** Einträge je Filter; $counts erhält die Anzahl je Filter */
    public static function filter(array $items, string $f, ?array &$counts = null): array
    {
        $test = [
            'all' => fn($i) => true,
            'mine' => fn($i) => $i['mine'],
            'review' => fn($i) => $i['note'] !== '' || $i['assignee_id'] !== null,
            'stale' => fn($i) => $i['stale'],
            'auto' => fn($i) => in_array($i['origin_key'], ['sync', 'ai', 'mcp', 'api', 'source'], true),
        ];
        $counts = [];
        foreach ($test as $k => $fn) $counts[$k] = count(array_filter($items, $fn));
        return array_values(array_filter($items, $test[$f] ?? $test['all']));
    }

    /** Anzahl für die Seitenleiste (einmal je Aufruf, ohne Herkunft und Notizen – nur zählen) */
    public static function count(): int
    {
        if (self::$count !== null) return self::$count;
        try {
            $n = 0;
            if (can('pages.edit')) {
                foreach (app()->db->fetchAll("SELECT status, content_draft, content_published FROM pages WHERE " . \Core\NotFound::sqlPagesAnd404() . " AND (status != 'published'
                    OR (content_draft IS NOT NULL AND content_published IS NOT NULL AND content_draft != content_published))") as $p) {
                    if ($p['status'] !== 'published' || self::pageChanged($p)) $n++;
                }
            }
            foreach (self::tables() as $t) $n += Entries::count($t, ['status' => 'draft', 'lang' => 'all']);
            return self::$count = $n;
        } catch (\Throwable $e) {
            error_log('[drafts] count: ' . $e->getMessage());
            return self::$count = 0;
        }
    }

    public static function find(string $key): ?array
    {
        foreach (self::items() as $i) if ($i['key'] === $key) return $i;
        return null;
    }

    // ================================================================= Herkunft

    /** Letzte Version je Seite: [page_id => [user_id, note, created_at]] */
    private static function lastRevisions(array $ids): array
    {
        if (!$ids) return [];
        $out = [];
        foreach (array_chunk($ids, 400) as $chunk) {
            $in = implode(',', array_map('intval', $chunk));
            foreach (app()->db->fetchAll("SELECT r.page_id, r.user_id, r.note, r.created_at FROM revisions r
                JOIN (SELECT page_id, MAX(id) AS mid FROM revisions WHERE page_id IN ($in) GROUP BY page_id) m ON m.mid = r.id") as $r) {
                $out[(int) $r['page_id']] = $r;
            }
        }
        return $out;
    }

    /** Letzte übernommene Änderung über API, MCP oder KI je Gegenstand (Core\Review\Queue, Tabelle change_log) */
    private static function changeLog(string $type, string $prefix = ''): array
    {
        $out = [];
        try {
            $sql = "SELECT entity_id, channel, token_name, feature, user_id, created_at FROM change_log WHERE status = 'applied' AND entity_type = ?"
                . ($prefix !== '' ? ' AND entity_id LIKE ?' : '') . ' ORDER BY id DESC LIMIT 2000';
            foreach (app()->db->fetchAll($sql, $prefix !== '' ? [$type, $prefix . '%'] : [$type]) as $r) {
                $out[(string) $r['entity_id']] ??= $r;
            }
        } catch (\Throwable) {
            // Tabelle fehlt noch (vor „migrate“)
        }
        return $out;
    }

    /** Herkunft aus dem Vermerk der letzten Version: [schlüssel, beschriftung] oder null (Redaktion) */
    public static function originFromNote(string $note): ?array
    {
        if (str_starts_with($note, 'Content-Sync')) return ['sync', __('Content-Sync')];
        if (preg_match('~^(API|MCP|KI) „([^“]*)“~u', $note, $m)) {
            $ch = ['API' => 'api', 'MCP' => 'mcp', 'KI' => 'ai'][$m[1]];
            return [$ch, Queue::channelLabel($ch) . ($m[2] !== '' ? ' · ' . $m[2] : '')];
        }
        if (str_contains($note, 'Seiten-Generator') || str_contains($note, 'page generator')) return ['ai', __('KI') . ' · ' . Queue::featureLabel('pagegen')];
        return null;
    }

    private static function originFromLog(array $c): array
    {
        $ch = (string) $c['channel'];
        $name = $ch === 'ai' ? Queue::featureLabel($c['feature'] ?? null) : (string) ($c['token_name'] ?? '');
        return [$ch, Queue::channelLabel($ch) . ($name !== '' ? ' · ' . $name : '')];
    }

    public static function originLabel(string $key): string
    {
        return match ($key) {
            'sync' => __('Content-Sync'), 'source' => __('Externe Quelle'), 'api', 'mcp', 'ai' => Queue::channelLabel($key), default => __('Redaktion'),
        };
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) { 'new' => __('neu'), 'changed' => __('geändert'), 'offline' => __('offline'), default => $state };
    }

    // ================================================================= Notizen & Zuständigkeit

    private static function notes(): array
    {
        try {
            return array_column(app()->db->fetchAll('SELECT * FROM draft_notes'), null, 'item');
        } catch (\Throwable) {
            return [];
        }
    }

    /** Konten, die zuständig sein können: [id => Name] (ohne gesperrte) */
    public static function userNames(): array
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = [];
        foreach (app()->db->fetchAll('SELECT * FROM users ORDER BY name, email') as $u) {
            if (!empty($u['disabled'])) continue;
            $cache[(int) $u['id']] = (string) (trim((string) $u['name']) !== '' ? $u['name'] : $u['email']);
        }
        return $cache;
    }

    /** Notiz und zuständige Person setzen (beides leer = Notiz entfernen) */
    public static function setNote(string $key, string $note, ?int $assignee, int $userId): void
    {
        $note = mb_substr(trim(strip_tags($note)), 0, 500);
        if ($assignee !== null && !isset(self::userNames()[$assignee])) $assignee = null;
        $db = app()->db;
        $db->query('DELETE FROM draft_notes WHERE item = ?', [$key]);
        if ($note === '' && $assignee === null) return;
        $db->insert('draft_notes', ['item' => $key, 'note' => $note !== '' ? $note : null, 'assignee_id' => $assignee, 'user_id' => $userId, 'updated_at' => now()]);
    }

    private static function forgetNote(string $key): void
    {
        try {
            app()->db->query('DELETE FROM draft_notes WHERE item = ?', [$key]);
        } catch (\Throwable) {
        }
    }

    // ================================================================= Aktionen

    /** Veröffentlichen mit denselben Rechten und Wegen wie in Seiten- bzw. Datenverwaltung. @return ?string Fehler */
    public static function publish(array $i, int $userId): ?string
    {
        if (!$i['can_publish']) return __('Veröffentlichen ist Ihrer Rolle nicht erlaubt.');
        if ($i['type'] === 'page') {
            try {
                Pages::publish($i['id'], $userId);
            } catch (\RuntimeException $e) {   // offene Platzhalter „[bitte ergänzen: …]“
                return $e->getMessage();
            }
        } elseif ($i['type'] === 'entry') {
            Entries::setStatus($i['table'], [$i['id']], 'published');
        } else {
            return __('Unbekannte Art.');
        }
        self::forgetNote($i['key']);
        return null;
    }

    /**
     * Verwerfen: Seite → zurück zur veröffentlichten Fassung (der Entwurf bleibt als Version erhalten);
     * Eintrag → löschen, vorher als JSON gesichert (draft_discards, wiederherstellbar). @return ?string Fehler
     */
    public static function discard(array $i, int $userId): ?string
    {
        if ($i['type'] === 'page') {
            if ($i['state'] === 'new') return __('„{title}“ wurde noch nie veröffentlicht – eine neue Seite löschen Sie unter „Seiten“.', ['title' => $i['title']]);
            if (!$i['can_discard'] || !Pages::discardDraft($i['id'], $userId)) return __('„{title}“: kein Entwurf zum Verwerfen.', ['title' => $i['title']]);
        } elseif ($i['type'] === 'entry') {
            if (!$i['can_discard']) return __('„{title}“: Verwerfen (Löschen) ist Ihrer Rolle nicht erlaubt.', ['title' => $i['title']]);
            $state = Snapshot::take(['type' => 'entry', 'table' => $i['table']['handle'], 'id' => $i['id']]);
            if ($state === null) return __('Eintrag nicht gefunden.');
            $state['_published_at'] = Entries::find($i['table'], $i['id'])['published_at'] ?? null;   // war schon einmal online?
            app()->db->insert('draft_discards', ['table_handle' => $i['table']['handle'], 'entry_id' => $i['id'], 'label' => mb_substr($i['title'], 0, 190),
                'data_json' => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'user_id' => $userId, 'created_at' => now()]);
            Entries::delete($i['table'], $i['id']);
        } else {
            return __('Unbekannte Art.');
        }
        self::forgetNote($i['key']);
        return null;
    }

    /** Verworfene Einträge der letzten Tage (nur Tabellen mit Bearbeitungsrecht) */
    public static function discards(int $limit = 20): array
    {
        try {
            if (random_int(1, 50) === 1) app()->db->query('DELETE FROM draft_discards WHERE created_at < ?', [date('Y-m-d H:i:s', time() - self::KEEP_DISCARDS_DAYS * 86400)]);
            $rows = app()->db->fetchAll('SELECT id, table_handle, entry_id, label, user_id, created_at FROM draft_discards ORDER BY id DESC LIMIT 200');
        } catch (\Throwable) {
            return [];
        }
        $tables = array_column(self::tables(), null, 'handle');
        $users = self::userNames();
        $out = [];
        foreach ($rows as $r) {
            if (!isset($tables[$r['table_handle']])) continue;
            $out[] = $r + ['table' => $tables[$r['table_handle']], 'by' => $users[(int) $r['user_id']] ?? ''];
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** Verworfenen Eintrag als Entwurf wiederherstellen. @return array{0: ?int, 1: ?string} [neue ID, Fehler] */
    public static function restore(int $id): array
    {
        $row = app()->db->fetch('SELECT * FROM draft_discards WHERE id = ?', [$id]);
        $t = $row ? Tables::find((string) $row['table_handle']) : null;
        if (!$row || !$t || Tables::isShared($t) || Tables::isInbox($t)) return [null, __('Nicht mehr vorhanden.')];
        if (!can('data.edit', $t['handle'])) return [null, __('Für diese Tabelle fehlt Ihrer Rolle die Berechtigung.')];
        $data = (array) json_decode((string) $row['data_json'], true);
        $pub = $data['_published_at'] ?? null;
        unset($data['_published_at']);
        [$newId, $errors] = Entries::save($t, null, ['status' => 'draft'] + $data);
        if ($errors) return [null, implode(' ', $errors)];
        // Tabellen ohne Freigabe-Workflow speichern immer veröffentlicht – der wiederhergestellte Eintrag bleibt Entwurf
        Tables::db($t)->update($t['table'], ['status' => 'draft', 'published_at' => $pub], 'id = :id', ['id' => (int) $newId]);
        app()->db->query('DELETE FROM draft_discards WHERE id = ?', [$id]);
        return [(int) $newId, null];
    }

    // ================================================================= Unterschiede

    /** Entwurf gegen die veröffentlichte Fassung (Seite) bzw. alle Felder (Eintrag, noch nie online) – Format Core\Review\Diff */
    public static function diff(array $i): array
    {
        if ($i['type'] === 'page') {
            $p = Pages::find($i['id']);
            if (!$p) return [];
            $export = fn(bool $draft) => Snapshot::exportBlocks(Pages::sanitizeBlocks(Pages::blocks($p, $draft)));
            $live = $p['content_published'] !== null ? ['blocks' => $export(false)] : null;
            $diff = Diff::compute(['type' => 'page', 'id' => $i['id']], $live, ['blocks' => $export(true)]);
            foreach ($diff as &$d) if ($d['path'] === 'blocks') $d['label'] = __('Inhalt');
            return $diff;
        }
        if ($i['type'] === 'entry') {
            $target = ['type' => 'entry', 'table' => $i['table']['handle'], 'id' => $i['id']];
            $state = Snapshot::take($target);
            unset($state['status']);
            return Diff::compute($target, null, $state);
        }
        return [];
    }
}
