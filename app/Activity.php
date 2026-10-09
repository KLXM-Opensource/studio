<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Aktionslog (Verwaltung → Werkzeuge → Aktionslog): wer hat wann Seiten, Einträge und Medien angelegt, geändert,
 * veröffentlicht, zurückgezogen oder gelöscht – mit Link zum Objekt. Tabelle activity_log.
 *
 * Quellen: typisierte Ereignisse (Extensions::emit → fromEvent: Seiten, Einträge), Medien-Ereignisse (fromMedia),
 * direkte Aufrufe (Pages::create, Seiteneinstellungen). Wiederholtes Speichern derselben Sache durch dieselbe Person
 * innerhalb von 15 Minuten wird zusammengefasst (Spalte n), damit das Log lesbar bleibt. Aufbewahrung 365 Tage.
 * Beim ersten Aufruf füllt seed() das Log aus vorhandenen Versionen (Seiten, Einträge) und Medien der letzten 90 Tage.
 */
final class Activity
{
    /** Art → Bezeichnung (Filter, Sortierung) */
    public const TYPES = ['page', 'entry', 'media'];
    public const ACTIONS = ['created', 'updated', 'settings', 'published', 'unpublished', 'discarded', 'uploaded', 'replaced', 'edited', 'trashed', 'deleted'];
    private const MERGE_MIN = 15;
    private const KEEP_DAYS = 365;

    private static bool $off = false;

    /** Darf der angemeldete Benutzer das Log sehen? */
    public static function canView(): bool
    {
        return can('system.manage');
    }

    public static function typeLabel(string $t): string
    {
        return ['page' => __('Seite'), 'entry' => __('Datensatz'), 'media' => __('Medium')][$t] ?? $t;
    }

    public static function typeLabels(): array
    {
        return ['page' => __('Seiten'), 'entry' => __('Datensätze'), 'media' => __('Medien')];
    }

    public static function actionLabel(string $a): string
    {
        return [
            'created' => __('angelegt'), 'updated' => __('geändert'), 'settings' => __('Einstellungen geändert'),
            'published' => __('veröffentlicht'), 'unpublished' => __('offline genommen'), 'discarded' => __('Entwurf verworfen'),
            'uploaded' => __('hochgeladen'), 'replaced' => __('ersetzt'), 'edited' => __('bearbeitet'),
            'trashed' => __('in den Papierkorb'), 'deleted' => __('gelöscht'),
        ][$a] ?? $a;
    }

    /** Aktionen zu Filtergruppen: neu | geändert | veröffentlicht | gelöscht */
    public const GROUPS = [
        'new' => ['created', 'uploaded'],
        'changed' => ['updated', 'settings', 'replaced', 'edited', 'discarded'],
        'live' => ['published', 'unpublished'],
        'gone' => ['trashed', 'deleted'],
    ];

    public static function groupLabels(): array
    {
        return ['new' => __('Neu'), 'changed' => __('Geändert'), 'live' => __('Veröffentlicht / offline'), 'gone' => __('Gelöscht')];
    }

    /**
     * Eintrag schreiben. $o: tbl (Kurzname der Datentabelle), lang, user (id; Standard: angemeldet), detail (kurze Notiz)
     */
    public static function log(string $action, string $type, int $id, string $label, array $o = []): void
    {
        if (self::$off || !in_array($type, self::TYPES, true)) return;
        try {
            $db = app()->db;
            $uid = array_key_exists('user', $o) ? ($o['user'] !== null ? (int) $o['user'] : null) : self::userId();
            $tbl = isset($o['tbl']) ? mb_substr((string) $o['tbl'], 0, 64) : null;
            $label = mb_substr(trim(strip_emphasis($label)) ?: '#' . $id, 0, 190);
            $now = now();
            // Zusammenfassen: dieselbe Person speichert dieselbe Sache mehrfach kurz hintereinander
            if ($action === 'updated' || $action === 'edited') {
                $since = date('Y-m-d H:i:s', time() - self::MERGE_MIN * 60);
                // ohne COALESCE: SQLite vergleicht Ausdrücke ohne Spaltentyp nicht mit als Text gebundenen Zahlen
                $args = [$type, $id];
                $sql = 'SELECT id, action FROM activity_log WHERE type = ? AND entity_id = ?';
                $sql .= $tbl === null ? ' AND tbl IS NULL' : ' AND tbl = ?';
                if ($tbl !== null) $args[] = $tbl;
                $sql .= $uid === null ? ' AND user_id IS NULL' : ' AND user_id = ?';
                if ($uid !== null) $args[] = $uid;
                $args[] = $since;
                $last = $db->fetch($sql . ' AND created_at >= ? ORDER BY id DESC LIMIT 1', $args);
                if ($last && in_array($last['action'], ['created', 'uploaded', 'updated', 'edited'], true)) {
                    $db->query('UPDATE activity_log SET created_at = ?, n = n + 1, label = ? WHERE id = ?', [$now, $label, (int) $last['id']]);
                    return;
                }
            }
            $db->insert('activity_log', ['created_at' => $now, 'user_id' => $uid, 'user_name' => self::userName($uid), 'action' => mb_substr($action, 0, 16),
                'type' => $type, 'tbl' => $tbl, 'entity_id' => $id, 'label' => $label, 'lang' => isset($o['lang']) ? mb_substr((string) $o['lang'], 0, 8) : null,
                'detail' => isset($o['detail']) && trim((string) $o['detail']) !== '' ? mb_substr(trim((string) $o['detail']), 0, 190) : null]);
            if (random_int(1, 200) === 1) self::prune();
        } catch (\Throwable $e) {
            error_log('[activity] ' . $e->getMessage());
        }
    }

    /** Typisierte Ereignisse (Extensions::emit): Seiten speichern/veröffentlichen/zurückziehen/verwerfen/löschen, Einträge */
    public static function fromEvent(Events\Event $e): void
    {
        if ($e instanceof Events\PageEvent) {
            $p = $e->page;
            $action = match (true) {
                $e instanceof Events\PageSaved => 'updated',
                $e instanceof Events\PagePublished => 'published',
                $e instanceof Events\PageUnpublished => 'unpublished',
                $e instanceof Events\PageDiscarded => 'discarded',
                $e instanceof Events\PageDeleted => 'deleted',
                default => null,
            };
            if ($action) self::log($action, 'page', $e->id, (string) ($p['title'] ?? ''), ['lang' => $e->lang, 'user' => $e->userId]);
            return;
        }
        if ($e instanceof Events\EntryEvent) {
            $action = match (true) {
                $e instanceof Events\EntrySaved => $e->created ? 'created' : 'updated',
                $e instanceof Events\EntryPublished => 'published',
                $e instanceof Events\EntryUnpublished => 'unpublished',
                $e instanceof Events\EntryDeleted => 'deleted',
                default => null,
            };
            if (!$action) return;
            $label = '';
            try {
                $label = $e->entry ? Data\Entries::title($e->definition, $e->entry) : '';
            } catch (\Throwable) {
            }
            self::log($action, 'entry', $e->id, $label, ['tbl' => $e->table, 'lang' => $e->lang, 'user' => $e->userId]);
        }
    }

    /** Medien-Ereignisse (Media: media.imported, .replaced, .edited, .trashed, .deleted – erstes Argument = Datensatz) */
    public static function fromMedia(string $name, array $args): void
    {
        $action = ['media.imported' => 'uploaded', 'media.replaced' => 'replaced', 'media.edited' => 'edited',
            'media.trashed' => 'trashed', 'media.deleted' => 'deleted'][$name] ?? null;
        $m = $args[0] ?? null;
        if (!$action || !is_array($m) || empty($m['id'])) return;
        self::log($action, 'media', (int) $m['id'], (string) (($m['title'] ?? '') ?: ($m['original_name'] ?? '') ?: ($m['file'] ?? '')));
    }

    /** Link zum Objekt in der Verwaltung (null: gelöscht bzw. nicht mehr vorhanden) */
    public static function link(array $r): ?string
    {
        $id = (int) $r['entity_id'];
        return match ($r['type']) {
            'page' => Pages::find($id) ? url('/admin/pages/' . $id) : null,
            'entry' => ($t = Data\Tables::find((string) $r['tbl'])) && Data\Entries::find($t, $id) ? url('/admin/data/' . $t['handle'] . '/' . $id) : null,
            'media' => ($m = Media::find($id)) && empty($m['deleted_at']) ? url('/admin/media') . '#m' . $id   /* öffnet die Datei mit ihren Infos (resources/js/_media.js) */ : null,
            default => null,
        };
    }

    /** Ansehen auf der Website (nur Seiten, die es gibt) */
    public static function viewUrl(array $r): ?string
    {
        if ($r['type'] !== 'page') return null;
        $p = Pages::find((int) $r['entity_id']);
        return $p ? Pages::url($p) : null;
    }

    /**
     * Einträge mit Filtern: type (page|entry|media), group (new|changed|live|gone), user (id; 0 = System), q (Suche im Namen),
     * days (Zeitraum), sort (new|old|type|user). @return array{rows: list<array>, total: int}
     */
    public static function query(array $f, int $limit = 100, int $offset = 0): array
    {
        self::seed();
        $w = [];
        $a = [];
        if (in_array($f['type'] ?? '', self::TYPES, true)) { $w[] = 'type = ?'; $a[] = $f['type']; }
        if (isset(self::GROUPS[$f['group'] ?? ''])) {
            $acts = self::GROUPS[$f['group']];
            $w[] = 'action IN (' . implode(',', array_fill(0, count($acts), '?')) . ')';
            array_push($a, ...$acts);
        }
        if (isset($f['user']) && $f['user'] !== '' && $f['user'] !== null) {
            if ((int) $f['user'] === 0) $w[] = 'user_id IS NULL';
            else { $w[] = 'user_id = ?'; $a[] = (int) $f['user']; }
        }
        if (($q = trim((string) ($f['q'] ?? ''))) !== '') { $w[] = 'label LIKE ?'; $a[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; }
        if (($d = (int) ($f['days'] ?? 0)) > 0) { $w[] = 'created_at >= ?'; $a[] = date('Y-m-d H:i:s', time() - $d * 86400); }
        $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';
        $order = match ($f['sort'] ?? 'new') {
            'old' => 'created_at ASC, id ASC',
            'type' => 'type ASC, created_at DESC, id DESC',
            'user' => 'COALESCE(user_name, \'\') ASC, created_at DESC, id DESC',
            default => 'created_at DESC, id DESC',
        };
        try {
            $total = (int) (app()->db->fetch('SELECT COUNT(*) AS c FROM activity_log' . $where, $a)['c'] ?? 0);
            $rows = app()->db->fetchAll('SELECT * FROM activity_log' . $where . ' ORDER BY ' . $order . ' LIMIT ' . max(1, min(500, $limit)) . ' OFFSET ' . max(0, $offset), $a);
        } catch (\Throwable $e) {
            error_log('[activity] ' . $e->getMessage());
            return ['rows' => [], 'total' => 0];
        }
        return ['rows' => $rows, 'total' => $total];
    }

    /** Personen, die im Log vorkommen: id → Name (0 = System) */
    public static function users(): array
    {
        $out = [];
        try {
            foreach (app()->db->fetchAll('SELECT user_id, MAX(user_name) AS name FROM activity_log GROUP BY user_id ORDER BY MAX(user_name)') as $r) {
                $id = $r['user_id'] === null ? 0 : (int) $r['user_id'];
                $out[$id] = $id === 0 ? __('System') : ((string) $r['name'] ?: '#' . $id);
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    /** Ältere Einträge entfernen */
    public static function prune(): void
    {
        try {
            app()->db->query('DELETE FROM activity_log WHERE created_at < ?', [date('Y-m-d H:i:s', time() - self::KEEP_DAYS * 86400)]);
        } catch (\Throwable) {
        }
    }

    /** Einmalig: Log aus vorhandenen Versionen und Medien füllen (letzte 90 Tage), damit es nicht leer beginnt */
    public static function seed(): void
    {
        try {
            if (app()->settings->get('activity.seeded')) return;
            app()->settings->set('activity.seeded', now());
            $db = app()->db;
            if ((int) ($db->fetch('SELECT COUNT(*) AS c FROM activity_log')['c'] ?? 0) > 0) return;
            $since = date('Y-m-d H:i:s', time() - 90 * 86400);
            $names = [];
            foreach ($db->fetchAll('SELECT id, name, email FROM users') as $u) $names[(int) $u['id']] = (string) ($u['name'] ?: $u['email']);
            $rows = [];
            foreach ($db->fetchAll('SELECT r.page_id, r.created_at, r.user_id, r.note, p.title FROM revisions r JOIN pages p ON p.id = r.page_id WHERE r.created_at >= ? ORDER BY r.id DESC LIMIT 1500', [$since]) as $r) {
                $rows[] = [$r['created_at'], $r['user_id'], stripos((string) $r['note'], 'veröffentlicht') !== false ? 'published' : 'updated', 'page', null, (int) $r['page_id'], (string) $r['title'], (string) $r['note']];
            }
            foreach ($db->fetchAll('SELECT tbl, entry_id, created_at, user_id, status, note FROM entry_revisions WHERE created_at >= ? ORDER BY id DESC LIMIT 1500', [$since]) as $r) {
                $t = Data\Tables::find((string) $r['tbl']);
                $e = $t ? Data\Entries::find($t, (int) $r['entry_id']) : null;
                $rows[] = [$r['created_at'], $r['user_id'], 'updated', 'entry', (string) $r['tbl'], (int) $r['entry_id'], $t && $e ? Data\Entries::title($t, $e) : '#' . $r['entry_id'], (string) $r['note']];
            }
            foreach ($db->fetchAll('SELECT id, created_at, title, original_name FROM media WHERE created_at >= ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1500', [$since]) as $r) {
                $rows[] = [$r['created_at'], null, 'uploaded', 'media', null, (int) $r['id'], (string) (($r['title'] ?? '') ?: $r['original_name']), ''];
            }
            usort($rows, fn($x, $y) => strcmp((string) $x[0], (string) $y[0]));
            foreach ($rows as [$at, $uid, $action, $type, $tbl, $id, $label, $note]) {
                $db->insert('activity_log', ['created_at' => (string) $at, 'user_id' => $uid !== null ? (int) $uid : null, 'user_name' => $uid !== null ? ($names[(int) $uid] ?? null) : null,
                    'action' => $action, 'type' => $type, 'tbl' => $tbl, 'entity_id' => $id, 'label' => mb_substr(strip_emphasis($label) ?: '#' . $id, 0, 190),
                    'detail' => trim(__('aus Versionen') . ($note !== '' ? ' · ' . mb_substr($note, 0, 150) : ''))]);
            }
        } catch (\Throwable $e) {
            error_log('[activity] seed: ' . $e->getMessage());
        }
    }

    /** Für Selbsttests und Massenimporte: Protokollieren aussetzen */
    public static function pause(bool $off = true): void
    {
        self::$off = $off;
    }

    private static function userId(): ?int
    {
        try {
            $id = isset(app()->auth) ? (app()->auth->user()['id'] ?? null) : null;
            return $id !== null ? (int) $id : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function userName(?int $id): ?string
    {
        if ($id === null) return PHP_SAPI === 'cli' ? __('Kommandozeile') : null;
        try {
            $u = app()->db->fetch('SELECT name, email FROM users WHERE id = ?', [$id]);
            return $u ? mb_substr((string) ($u['name'] ?: $u['email']), 0, 190) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
