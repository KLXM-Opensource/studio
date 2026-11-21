<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Tasks;

use Core\Database;
use Core\Links;
use Core\Permissions;
use Core\Review\Drafts;

/**
 * Aufgaben der Redaktion („Was ist zu tun?“ auf der Übersicht, Verwaltung → Aufgaben, /admin/aufgaben).
 *
 * Eine Aufgabe hat Titel, Text (Markdown-lite wie der Support, Core\Support\Markdown – nie HTML), optional ein Ziel (stabiler
 * Verweis der Linkauswahl: page:12, page:12#anker für einen Block, entry:news:5, media:9 – oder ein Pfad /…), eine Zuständigkeit
 * (eine Person, eine Rolle oder alle), Fälligkeit, Priorität (low|normal|high) und den Status offen/erledigt. Sie bleibt sichtbar,
 * bis jemand sie abhakt (anders als Projekt-Hinweise mit „aufgabe: ja“, die je Person nach dem Lesen verschwinden).
 * Abhaken mit optionalem Kommentar, wieder öffnen – jede Änderung steht im Verlauf (Tabelle task_log).
 *
 * Wege: Verwaltung (Dialog in „Was ist zu tun?“, Liste, an Seite/Eintrag), Konsole (tasks:add|list|done), REST-API
 * (/api/v1/tasks) und MCP (task_list, task_create, task_done) – Core\Tasks\Api.
 * Entwurfs-Notizen mit zuständiger Person (draft_notes, Core\Review\Drafts) erscheinen zusätzlich in der Liste (Art „draft“,
 * ohne eigene Zeile in tasks – erledigt sind sie mit dem Veröffentlichen/Verwerfen des Entwurfs).
 *
 * Rechte: tasks.use (Aufgaben für sich selbst anlegen), tasks.manage (für andere anlegen, zuweisen, alle sehen, bearbeiten, löschen).
 * Abhaken darf jede zuständige Person, wer die Aufgabe angelegt hat, und tasks.manage. Administration hat beides immer.
 * Benachrichtigung: Core\Tasks\Notify (E-Mail über Core\Mailer, Push-Ereignis „tasks.assigned“), Erinnerung tasks:remind (Cron, Standard aus).
 */
final class Tasks
{
    public const PRIORITIES = ['high', 'normal', 'low'];
    public const FILTERS = ['open', 'mine', 'done', 'all'];
    public const TITLE_MAX = 160;
    public const BODY_MAX = 5000;
    public const COMMENT_MAX = 1000;
    /** Erinnerung an fällige Aufgaben (tasks:remind, Cron) – je Website, Standard aus */
    public const REMIND_SETTING = 'sys.tasks_remind';
    /** Persönlicher Schalter (Core\UiPrefs): E-Mail bei neuer Zuweisung und Erinnerung – Standard an */
    public const MAIL_PREF = 'tasks.mail';

    /** Selbsttest: keine Benachrichtigungen */
    public static bool $quiet = false;

    // ================================================================= Schema

    /** Tabellen anlegen (idempotent, nur ergänzend) – aus Database::migrate; Rolle „Redaktion“ einmalig ergänzen */
    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->query("CREATE TABLE IF NOT EXISTS tasks (id $pk, title VARCHAR(191) NOT NULL, body TEXT NULL, link VARCHAR(255) NULL,
            assignee_id INT NULL, assignee_role VARCHAR(64) NULL, due VARCHAR(10) NULL, priority VARCHAR(10) NOT NULL DEFAULT 'normal',
            status VARCHAR(10) NOT NULL DEFAULT 'open', source VARCHAR(120) NULL, created_by INT NULL, created_label VARCHAR(191) NULL,
            created_at VARCHAR(25), updated_at VARCHAR(25), done_at VARCHAR(25) NULL, done_by INT NULL, done_label VARCHAR(191) NULL,
            done_comment TEXT NULL, reminded_at VARCHAR(10) NULL)$tail");
        $db->query("CREATE TABLE IF NOT EXISTS task_log (id $pk, task_id INT NOT NULL, action VARCHAR(20) NOT NULL, user_id INT NULL,
            label VARCHAR(191) NULL, comment TEXT NULL, created_at VARCHAR(25))$tail");
        if (!$my) {
            $db->pdo->exec('CREATE INDEX IF NOT EXISTS tasks_status ON tasks (status, due)');
            $db->pdo->exec('CREATE INDEX IF NOT EXISTS tasks_link ON tasks (link)');
            $db->pdo->exec('CREATE INDEX IF NOT EXISTS task_log_task ON task_log (task_id, id)');
        }
        self::ensureRoles($db);
    }

    /** Rolle „Redaktion“ bekommt einmalig tasks.use (danach frei änderbar; Merker sys.tasks_roles) */
    private static function ensureRoles(Database $db): void
    {
        try {
            if ($db->fetchValue("SELECT COUNT(*) FROM settings WHERE skey = 'sys.tasks_roles'")) return;
            $r = $db->fetch("SELECT permissions_json FROM roles WHERE rkey = 'editor'");
            if ($r) {
                $p = json_decode((string) $r['permissions_json'], true) ?: [];
                if (!in_array('*', $p, true) && !in_array('tasks.use', $p, true)) {
                    $p[] = 'tasks.use';
                    $db->update('roles', ['permissions_json' => json_encode(array_values($p))], "rkey = 'editor'", []);
                }
            }
            $db->insert('settings', ['skey' => 'sys.tasks_roles', 'value_json' => '1']);
        } catch (\Throwable $e) {
            error_log('[tasks] roles: ' . $e->getMessage());
        }
    }

    // ================================================================= Rechte

    /** Recht eines Kontos (angemeldete Person: wie can(), sonst über ihre Rolle) */
    public static function may(array $u, string $perm): bool
    {
        if (!empty($u['disabled'])) return false;
        $me = isset(app()->auth) ? app()->auth->user() : null;
        if ($me && (int) $me['id'] === (int) ($u['id'] ?? 0) && empty($u['_test'])) return can($perm);
        $roles = Permissions::roles();
        return Permissions::allows($roles[(string) ($u['role'] ?? '')] ?? null, $perm);
    }

    /** Darf für andere anlegen, zuweisen, alle Aufgaben sehen, bearbeiten und löschen */
    public static function manager(array $u): bool
    {
        return self::may($u, 'tasks.manage');
    }

    /** Darf Aufgaben anlegen (mindestens für sich selbst) */
    public static function canCreate(array $u): bool
    {
        return self::manager($u) || self::may($u, 'tasks.use');
    }

    /** Zuständig: genau diese Person, ihre Rolle oder „alle“ (ohne Zuständigkeit) */
    public static function responsible(array $t, array $u): bool
    {
        if ($t['assignee_id'] !== null && $t['assignee_id'] !== '') return (int) $t['assignee_id'] === (int) ($u['id'] ?? 0);
        if ((string) ($t['assignee_role'] ?? '') !== '') return (string) $t['assignee_role'] === (string) ($u['role'] ?? '');
        return true;
    }

    /** Persönlich zuständig (Person oder Rolle – nicht „alle“) – Filter „Meine“ */
    public static function mine(array $t, array $u): bool
    {
        return (($t['assignee_id'] ?? null) !== null || (string) ($t['assignee_role'] ?? '') !== '') && self::responsible($t, $u);
    }

    public static function creator(array $t, array $u): bool
    {
        return ($t['created_by'] ?? null) !== null && (int) $t['created_by'] === (int) ($u['id'] ?? 0);
    }

    public static function canSee(array $t, array $u): bool
    {
        return self::manager($u) || self::creator($t, $u) || self::responsible($t, $u);
    }

    /** Abhaken / wieder öffnen */
    public static function canDone(array $t, array $u): bool
    {
        return self::manager($u) || self::creator($t, $u) || self::responsible($t, $u);
    }

    /** Bearbeiten / löschen */
    public static function canEdit(array $t, array $u): bool
    {
        return self::manager($u) || (self::creator($t, $u) && self::may($u, 'tasks.use'));
    }

    // ================================================================= Lesen

    public static function find(int $id): ?array
    {
        $t = app()->db->fetch('SELECT * FROM tasks WHERE id = ?', [$id]);
        return $t ? self::hydrate($t) : null;
    }

    /** Spalten → einheitliche Form (Zahlen, Kind) */
    public static function hydrate(array $t): array
    {
        foreach (['id', 'assignee_id', 'created_by', 'done_by'] as $k) $t[$k] = isset($t[$k]) && $t[$k] !== '' ? (int) $t[$k] : null;
        $t['kind'] = 'task';
        $t['key'] = 'task:' . $t['id'];
        $t['assignee_role'] = (string) ($t['assignee_role'] ?? '') ?: null;
        $t['priority'] = in_array($t['priority'] ?? '', self::PRIORITIES, true) ? $t['priority'] : 'normal';
        return $t;
    }

    /**
     * Aufgaben, die das Konto sehen darf, plus Entwurfs-Notizen mit Zuständigkeit (Art „draft“).
     * $filter: open (Standard) | mine (offen, persönlich zuständig) | done | all. $link: nur Aufgaben zu diesem Ziel.
     * Sortierung offen: Priorität hoch zuerst, dann Fälligkeit (ohne Datum zuletzt), neueste zuerst; erledigt: zuletzt erledigt zuerst.
     * @return list<array>
     */
    public static function list(array $u, string $filter = 'open', ?string $link = null, int $limit = 500): array
    {
        $filter = in_array($filter, self::FILTERS, true) ? $filter : 'open';
        $where = match ($filter) { 'done' => "status = 'done'", 'all' => '1=1', default => "status = 'open'" };
        $params = [];
        if ($link !== null && $link !== '') {
            $where .= ' AND (link = ? OR link LIKE ?)';
            $params = [$link, $link . '#%'];
        }
        $order = $filter === 'done' ? 'done_at DESC, id DESC' : 'id DESC';
        $out = [];
        try {
            foreach (app()->db->fetchAll("SELECT * FROM tasks WHERE $where ORDER BY $order LIMIT " . max(1, min(2000, $limit)), $params) as $row) {
                $t = self::hydrate($row);
                if (!self::canSee($t, $u) || ($filter === 'mine' && !self::mine($t, $u))) continue;
                $out[] = $t;
            }
        } catch (\Throwable $e) {
            error_log('[tasks] list: ' . $e->getMessage());   // Tabelle fehlt noch (vor „migrate“)
            return [];
        }
        if ($filter !== 'done') $out = [...$out, ...self::drafts($u, $filter === 'mine', $link)];
        if ($filter !== 'done') usort($out, [self::class, 'compare']);
        return $out;
    }

    /** Sortierung offener Aufgaben: erledigt zuletzt, Priorität, Fälligkeit, neueste */
    public static function compare(array $a, array $b): int
    {
        $rank = ['high' => 0, 'normal' => 1, 'low' => 2];
        return [($a['status'] ?? 'open') === 'done' ? 1 : 0, $rank[$a['priority']] ?? 1, ($a['due'] ?? '') ?: '9999', -(int) strtotime((string) ($a['created_at'] ?? '')), -(int) ($a['id'] ?? 0)]
            <=> [($b['status'] ?? 'open') === 'done' ? 1 : 0, $rank[$b['priority']] ?? 1, ($b['due'] ?? '') ?: '9999', -(int) strtotime((string) ($b['created_at'] ?? '')), -(int) ($b['id'] ?? 0)];
    }

    /** Anzahl je Filter (für die Reiter) */
    public static function counts(array $u): array
    {
        $open = self::list($u, 'open');
        return ['open' => count($open), 'mine' => count(array_filter($open, fn($t) => self::mine($t, $u))),
            'done' => count(self::list($u, 'done')), 'all' => count(self::list($u, 'all'))];
    }

    /**
     * Entwurfs-Notizen mit zuständiger Person (Core\Review\Drafts, Tabelle draft_notes) als Aufgaben – ohne eigene Zeile in tasks.
     * Sichtbar für die zuständige Person und tasks.manage; erledigt mit dem Veröffentlichen/Verwerfen des Entwurfs.
     * @return list<array>
     */
    public static function drafts(array $u, bool $mineOnly = false, ?string $link = null): array
    {
        try {
            if (!app()->db->fetchValue('SELECT COUNT(*) FROM draft_notes WHERE assignee_id IS NOT NULL')) return [];
            if (!Drafts::canView()) return [];
            $items = Drafts::items();
        } catch (\Throwable) {
            return [];
        }
        $manager = self::manager($u);
        $out = [];
        foreach ($items as $i) {
            if ($i['assignee_id'] === null) continue;
            $mine = $i['assignee_id'] === (int) ($u['id'] ?? 0);
            if (!$mine && ($mineOnly || !$manager)) continue;
            $target = $i['type'] === 'page' ? 'page:' . $i['id'] : 'entry:' . $i['table']['handle'] . ':' . $i['id'];
            if ($link !== null && $link !== '' && $target !== $link) continue;
            $out[] = ['id' => null, 'kind' => 'draft', 'key' => 'draft:' . $i['key'], 'title' => __('Entwurf prüfen: {title}', ['title' => $i['title']]),
                'body' => $i['note'], 'link' => $target, 'assignee_id' => $i['assignee_id'], 'assignee_role' => null, 'due' => null, 'priority' => 'normal',
                'status' => 'open', 'source' => 'draft', 'created_by' => null, 'created_label' => $i['note_by'], 'created_at' => $i['note_at'],
                'href' => '/admin/entwuerfe#' . \Core\Http\Controllers\Admin\DraftController::anchor($i['key']), 'edit' => $i['edit']];
        }
        return $out;
    }

    /** Verlauf einer Aufgabe, älteste zuerst @return list<array> */
    public static function log(int $id): array
    {
        return app()->db->fetchAll('SELECT * FROM task_log WHERE task_id = ? ORDER BY id', [$id]);
    }

    // ================================================================= Schreiben

    /**
     * Wer handelt: ['id' => ?int (Konto), 'label' => Name (z. B. „Konsole“, Token-Name), 'channel' => admin|cli|api|mcp]
     * @return array{id: ?int, label: string, channel: string}
     */
    public static function actor(?array $u, string $channel = 'admin', string $label = ''): array
    {
        $name = $u ? (trim((string) ($u['name'] ?? '')) ?: (string) ($u['email'] ?? '')) : '';
        return ['id' => $u ? (int) $u['id'] : null, 'label' => mb_substr($label !== '' ? $label : ($name !== '' ? $name : __('Konsole')), 0, 191), 'channel' => $channel];
    }

    /**
     * Eingaben prüfen und bereinigen. $in: title, body, link, assignee (siehe parseAssignee), due (JJJJ-MM-TT), priority.
     * @return array{0: array, 1: array<string, string>} [Werte, Fehler je Feld]
     */
    public static function clean(array $in): array
    {
        $err = [];
        $title = trim((string) preg_replace('~\s+~u', ' ', strip_tags((string) ($in['title'] ?? ''))));
        if ($title === '') $err['title'] = __('Bitte einen Titel eingeben.');
        if (mb_strlen($title) > self::TITLE_MAX) $err['title'] = __('Der Titel ist zu lang (höchstens {n} Zeichen).', ['n' => self::TITLE_MAX]);
        $body = trim(str_replace(["\r\n", "\r"], "\n", (string) ($in['body'] ?? '')));
        if (mb_strlen($body) > self::BODY_MAX) $err['body'] = __('Der Text ist zu lang (höchstens {n} Zeichen).', ['n' => self::BODY_MAX]);
        $link = self::cleanLink((string) ($in['link'] ?? ''));
        if ($link === false) $err['link'] = __('Als Ziel bitte eine Seite, einen Eintrag, eine Datei oder eine Adresse dieser Website wählen.');
        $due = trim((string) ($in['due'] ?? ''));
        if ($due !== '' && !self::validDate($due)) $err['due'] = __('Fälligkeit bitte als Datum (JJJJ-MM-TT) angeben.');
        $prio = (string) ($in['priority'] ?? 'normal');
        if (!in_array($prio, self::PRIORITIES, true)) $prio = 'normal';
        try {
            [$uid, $role] = self::parseAssignee($in['assignee'] ?? '');
        } catch (\InvalidArgumentException $e) {
            [$uid, $role] = [null, null];
            $err['assignee'] = $e->getMessage();
        }
        return [['title' => mb_substr($title, 0, self::TITLE_MAX), 'body' => $body !== '' ? $body : null, 'link' => $link ?: null, 'due' => $due !== '' ? $due : null,
            'priority' => $prio, 'assignee_id' => $uid, 'assignee_role' => $role], $err];
    }

    public static function validDate(string $d): bool
    {
        if (!preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $d, $m)) return false;
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** Ziel: stabiler Verweis (page:…, entry:…, media:…) oder interner Pfad /… – '' = keins, false = ungültig */
    public static function cleanLink(string $v): string|false
    {
        $v = trim($v);
        if ($v === '') return '';
        if (Links::isRef($v)) return $v;
        if (str_starts_with($v, '/') && !str_starts_with($v, '//') && strlen($v) <= 255 && !preg_match('~[\s"<>\\\\]~', $v)) return $v;
        return false;
    }

    /**
     * Zuständigkeit aus Eingabe: '' | 'all' | 'alle' → alle; 'role:editor' bzw. Rollenschlüssel → Rolle; 'user:5', 5 oder E-Mail → Person.
     * @return array{0: ?int, 1: ?string} [Benutzer-ID, Rolle]
     */
    public static function parseAssignee(mixed $v): array
    {
        $v = trim((string) $v);
        if ($v === '' || in_array(mb_strtolower($v), ['all', 'alle', '*'], true)) return [null, null];
        if (str_starts_with($v, 'role:')) $v = substr($v, 5);
        elseif (str_starts_with($v, 'user:')) $v = substr($v, 5);
        elseif (!ctype_digit($v) && !str_contains($v, '@') && Permissions::role($v) === null) {
            throw new \InvalidArgumentException(__('Unbekannte Rolle oder Person: {v}', ['v' => mb_substr($v, 0, 80)]));
        }
        if (ctype_digit($v) || str_contains($v, '@')) {
            $u = ctype_digit($v) ? app()->db->fetch('SELECT id, disabled FROM users WHERE id = ?', [(int) $v])
                : app()->db->fetch('SELECT id, disabled FROM users WHERE LOWER(email) = ?', [mb_strtolower($v)]);
            if (!$u || !empty($u['disabled'])) throw new \InvalidArgumentException(__('Unbekannte Person: {v}', ['v' => mb_substr($v, 0, 80)]));
            return [(int) $u['id'], null];
        }
        if (Permissions::role($v) === null || in_array($v, ['network'], true)) throw new \InvalidArgumentException(__('Unbekannte Rolle oder Person: {v}', ['v' => mb_substr($v, 0, 80)]));
        return [null, $v];
    }

    /** Darf $u eine Aufgabe mit dieser Zuständigkeit anlegen/zuweisen? Ohne tasks.manage nur sich selbst. */
    public static function mayAssign(array $u, ?int $uid, ?string $role): bool
    {
        if (self::manager($u)) return true;
        return self::may($u, 'tasks.use') && $role === null && $uid !== null && $uid === (int) ($u['id'] ?? 0);
    }

    /**
     * Aufgabe anlegen (Rechte prüft der Aufrufer bzw. $u). $vals aus clean(); $o: source (Herkunft, z. B. „guide:10-start“),
     * notify (Standard true). @return int ID
     */
    public static function create(array $vals, array $actor, array $o = []): int
    {
        $now = now();
        $db = app()->db;
        $id = $db->transaction(function () use ($db, $vals, $actor, $o, $now) {
            $id = $db->insert('tasks', array_intersect_key($vals, array_flip(['title', 'body', 'link', 'due', 'priority', 'assignee_id', 'assignee_role'])) + [
                'status' => 'open', 'source' => isset($o['source']) ? mb_substr((string) $o['source'], 0, 120) : $actor['channel'],
                'created_by' => $actor['id'], 'created_label' => $actor['label'], 'created_at' => $now, 'updated_at' => $now]);
            self::addLog($id, 'created', $actor, null);
            return $id;
        });
        if (($o['notify'] ?? true) && ($vals['assignee_id'] !== null || $vals['assignee_role'] !== null)) Notify::assigned((int) $id, $actor);
        return (int) $id;
    }

    /** Ändern (Titel, Text, Ziel, Zuständigkeit, Fälligkeit, Priorität); neue Zuständigkeit benachrichtigt */
    public static function update(int $id, array $vals, array $actor): void
    {
        $old = self::find($id) ?? throw new \RuntimeException('Aufgabe nicht gefunden');
        $set = array_intersect_key($vals, array_flip(['title', 'body', 'link', 'due', 'priority', 'assignee_id', 'assignee_role']));
        $reassigned = array_key_exists('assignee_id', $set) && ($set['assignee_id'] !== $old['assignee_id'] || ($set['assignee_role'] ?? null) !== $old['assignee_role']);
        app()->db->update('tasks', $set + ['updated_at' => now()] + ($reassigned || (($set['due'] ?? null) !== $old['due']) ? ['reminded_at' => null] : []), 'id = :id', ['id' => $id]);
        self::addLog($id, $reassigned ? 'assigned' : 'edited', $actor, $reassigned ? self::assigneeLabel($set + $old) : null);
        if ($reassigned && $old['status'] === 'open' && ($set['assignee_id'] !== null || ($set['assignee_role'] ?? null) !== null)) Notify::assigned($id, $actor);
    }

    /** Abhaken mit optionalem Kommentar */
    public static function done(int $id, array $actor, string $comment = ''): void
    {
        $comment = mb_substr(trim(strip_tags($comment)), 0, self::COMMENT_MAX);
        app()->db->update('tasks', ['status' => 'done', 'done_at' => now(), 'done_by' => $actor['id'], 'done_label' => $actor['label'],
            'done_comment' => $comment !== '' ? $comment : null, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        self::addLog($id, 'done', $actor, $comment !== '' ? $comment : null);
    }

    /** Wieder öffnen (erledigt-Angaben bleiben im Verlauf) */
    public static function reopen(int $id, array $actor, string $comment = ''): void
    {
        $comment = mb_substr(trim(strip_tags($comment)), 0, self::COMMENT_MAX);
        app()->db->update('tasks', ['status' => 'open', 'done_at' => null, 'done_by' => null, 'done_label' => null, 'done_comment' => null,
            'reminded_at' => null, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        self::addLog($id, 'reopened', $actor, $comment !== '' ? $comment : null);
    }

    public static function delete(int $id): void
    {
        $db = app()->db;
        $db->transaction(function () use ($db, $id) {
            $db->query('DELETE FROM task_log WHERE task_id = ?', [$id]);
            $db->query('DELETE FROM tasks WHERE id = ?', [$id]);
        });
    }

    private static function addLog(int $id, string $action, array $actor, ?string $comment): void
    {
        app()->db->insert('task_log', ['task_id' => $id, 'action' => $action, 'user_id' => $actor['id'], 'label' => $actor['label'],
            'comment' => $comment, 'created_at' => now()]);
    }

    // ================================================================= Anzeige

    /** „Erika Musterfrau“, „Rolle: Redaktion“ bzw. „Alle“ */
    public static function assigneeLabel(array $t): string
    {
        if (($t['assignee_id'] ?? null) !== null) return Drafts::userNames()[(int) $t['assignee_id']] ?? __('Person #{id}', ['id' => (int) $t['assignee_id']]);
        if ((string) ($t['assignee_role'] ?? '') !== '') {
            $r = Permissions::role((string) $t['assignee_role']);
            return __('Rolle: {name}', ['name' => $r ? __($r['name']) : (string) $t['assignee_role']]);
        }
        return __('Alle');
    }

    /** Wert für das Auswahlfeld „Zuständig“ */
    public static function assigneeValue(array $t): string
    {
        if (($t['assignee_id'] ?? null) !== null) return 'user:' . (int) $t['assignee_id'];
        if ((string) ($t['assignee_role'] ?? '') !== '') return 'role:' . $t['assignee_role'];
        return '';
    }

    public static function priorityLabel(string $p): string
    {
        return match ($p) { 'high' => __('hoch'), 'low' => __('niedrig'), default => __('normal') };
    }

    /** überfällig | heute | bald (≤ 3 Tage) | '' */
    public static function dueState(?string $due, ?string $today = null): string
    {
        if ($due === null || $due === '') return '';
        $today ??= date('Y-m-d');
        if ($due < $today) return 'overdue';
        if ($due === $today) return 'today';
        return $due <= date('Y-m-d', (int) strtotime($today . ' +3 days')) ? 'soon' : '';
    }

    /** Adresse der Aufgabe in der Verwaltung (Entwurfs-Notizen: Entwürfe) */
    public static function url(array $t): string
    {
        return $t['kind'] === 'draft' ? (string) $t['href'] : '/admin/aufgaben/' . (int) $t['id'];
    }

    /** Ziel als [Art, Beschriftung, Adresse, fehlt?] (Linkauswahl Core\Links) – Verwaltungsadresse für Seiten/Einträge, wo sinnvoll */
    public static function target(?string $link): ?array
    {
        if ($link === null || $link === '') return null;
        try {
            $d = Links::describe($link);
        } catch (\Throwable) {
            return ['type' => __('Ziel'), 'label' => $link, 'href' => '', 'missing' => true, 'admin' => ''];
        }
        $admin = '';
        if (preg_match('~^page:(\d+)~', $link, $m)) $admin = '/admin/pages/' . $m[1];
        elseif (preg_match('~^entry:([a-z][a-z0-9_]*):(\d+)$~', $link, $m)) $admin = '/admin/data/' . $m[1] . '/' . $m[2];
        elseif (preg_match('~^media:(\d+)~', $link, $m)) $admin = '/admin/media#m' . $m[1];
        return ['type' => (string) $d['type'], 'label' => (string) $d['label'], 'href' => (string) $d['href'], 'missing' => (bool) $d['missing'], 'admin' => $admin];
    }

    /** Vorgaben für das Formular (Dialog und eigene Seite): ohne tasks.manage ist man selbst zuständig */
    public static function formDefaults(array $u, string $link = '', array $values = [], array $errors = []): array
    {
        $manager = self::manager($u);
        return ['values' => $values + ['title' => '', 'body' => '', 'link' => $link, 'assignee' => $manager ? '' : 'user:' . (int) $u['id'], 'due' => '', 'priority' => 'normal'],
            'errors' => $errors, 'manager' => $manager, 'can' => self::canCreate($u), 'people' => $manager ? self::people() : [], 'roles' => $manager ? self::roles() : []];
    }

    /** Konten, die zuständig sein können: [id => Name] */
    public static function people(): array
    {
        return Drafts::userNames();
    }

    /** Rollen, denen sich Aufgaben zuweisen lassen: [key => Name] (ohne Netzwerk) */
    public static function roles(): array
    {
        $out = [];
        foreach (Permissions::roles() as $k => $r) if ($k !== 'network') $out[$k] = __((string) $r['name']);
        return $out;
    }

    // ================================================================= Übernahme der Projekt-Hinweise

    /**
     * Projekt-Hinweise mit „aufgabe: ja“ (Core\Guide) als Aufgaben für alle anlegen – je Hinweis höchstens einmal (Herkunft guide:{key}).
     * $dry: nur zählen. @return list<array{key: string, title: string, state: string}> state: neu | vorhanden
     */
    public static function importGuide(bool $dry, array $actor): array
    {
        $out = [];
        foreach (\Core\Guide::notes() as $n) {
            if (empty($n['task'])) continue;
            $src = 'guide:' . $n['key'];
            $have = (bool) app()->db->fetchValue('SELECT COUNT(*) FROM tasks WHERE source = ?', [$src]);
            $out[] = ['key' => $n['key'], 'title' => $n['title'], 'state' => $have ? 'vorhanden' : 'neu'];
            if ($have || $dry) continue;
            self::create(['title' => mb_substr($n['title'], 0, self::TITLE_MAX), 'body' => mb_substr(\Core\Guide::plain($n, 1500) . "\n\n" . __('Ausführlich im Handbuch: {url}', ['url' => \Core\Guide::url($n)]), 0, self::BODY_MAX),
                'link' => null, 'due' => null, 'priority' => 'normal', 'assignee_id' => null, 'assignee_role' => null], $actor, ['source' => $src, 'notify' => false]);
        }
        return $out;
    }

    /** Schlüssel übernommener Projekt-Hinweise (stehen dann nicht mehr doppelt in „Was ist zu tun?“) @return list<string> */
    public static function importedGuideKeys(): array
    {
        try {
            return array_map(fn($s) => substr((string) $s, 6), array_column(app()->db->fetchAll("SELECT source FROM tasks WHERE source LIKE 'guide:%'"), 'source'));
        } catch (\Throwable) {
            return [];
        }
    }

    // ================================================================= Übersicht „Was ist zu tun?“

    /**
     * Offene Aufgaben, für die das Konto zuständig ist (Person, Rolle, alle) – je Aufgabe ein Punkt in „Was ist zu tun?“.
     * @return list<array> Format von Dashboard\Metrics::todos (+ task, done)
     */
    public static function todos(array $u): array
    {
        $out = [];
        foreach (self::list($u, 'open') as $t) {
            if (!self::responsible($t, $u)) continue;
            $state = self::dueState($t['due']);
            $tone = $state === 'overdue' || ($t['priority'] === 'high' && $state !== '') ? 'warn' : ($t['priority'] === 'high' ? 'warn' : 'info');
            $bits = [];
            if ($t['due']) $bits[] = match ($state) { 'overdue' => __('überfällig seit {date}', ['date' => fmt()->date($t['due'])]), 'today' => __('heute fällig'),
                default => __('fällig am {date}', ['date' => fmt()->date($t['due'])]) };
            $bits[] = __('Zuständig: {who}', ['who' => self::assigneeLabel($t)]);
            if ((string) ($t['body'] ?? '') !== '') $bits[] = \Core\Support\Markdown::plain((string) $t['body'], 140);
            $out[] = ['key' => $t['key'], 'prio' => $t['priority'] === 'high' ? 1 : ($state === 'overdue' ? 1 : 3), 'tone' => $tone,
                'icon' => $t['kind'] === 'draft' ? 'pencil-simple' : 'list-checks', 'count' => 1, 'title' => (string) $t['title'], 'text' => implode(' · ', $bits),
                'href' => self::url($t), 'action' => $t['kind'] === 'draft' ? __('Entwurf öffnen') : __('Öffnen'),
                'task' => $t, 'done' => $t['kind'] === 'task' && self::canDone($t, $u) ? ['url' => '/admin/aufgaben/' . (int) $t['id'] . '/erledigt'] : null];
        }
        return $out;
    }

    // ================================================================= Selbsttest (php bin/console tasks:selftest)

    /** In einer Transaktion der Website-Datenbank, die am Ende zurückgerollt wird. @return array{ok: int, fails: list<string>} */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails);
        // Ohne Datenbank: Eingaben, Datum, Ziel, Fälligkeit
        $eq('Datum gültig', self::validDate('2026-12-31'), true);
        $eq('Datum ungültig', [self::validDate('2026-02-30'), self::validDate('31.12.2026'), self::validDate('')], [false, false, false]);
        $eq('Ziel: Verweis', self::cleanLink('page:12#b-ab12'), 'page:12#b-ab12');
        $eq('Ziel: Eintrag', self::cleanLink('entry:news:5'), 'entry:news:5');
        $eq('Ziel: Pfad', self::cleanLink('/kontakt'), '/kontakt');
        $eq('Ziel: fremd abgelehnt', [self::cleanLink('https://example.org'), self::cleanLink('//evil.example'), self::cleanLink('javascript:alert(1)'), self::cleanLink('/a b')], [false, false, false, false]);
        $eq('Fälligkeit', [self::dueState('2026-01-01', '2026-01-02'), self::dueState('2026-01-02', '2026-01-02'), self::dueState('2026-01-04', '2026-01-02'), self::dueState('2026-02-01', '2026-01-02'), self::dueState(null)],
            ['overdue', 'today', 'soon', '', '']);
        [$v, $e] = self::clean(['title' => "  <b>Impressum</b>\n prüfen ", 'body' => str_repeat('x', 10), 'priority' => 'quatsch', 'due' => '2026-13-01']);
        $eq('Titel ohne HTML', $v['title'], 'Impressum prüfen');
        $eq('Priorität Rückfall', $v['priority'], 'normal');
        $eq('Fälligkeit-Fehler', isset($e['due']), true);
        $eq('Pflicht: Titel', isset(self::clean(['title' => ' '])[1]['title']), true);
        $eq('Ziel-Fehler', isset(self::clean(['title' => 'x', 'link' => 'https://example.org'])[1]['link']), true);

        $db = app()->db;
        $quiet = self::$quiet;
        self::$quiet = true;
        $db->pdo->beginTransaction();
        try {
            self::ensureTable($db);
            $mk = function (string $mail, string $role) use ($db): array {
                $id = $db->insert('users', ['email' => $mail, 'name' => $mail, 'password_hash' => '-', 'role' => $role, 'created_at' => now()]);
                return ['id' => $id, 'email' => $mail, 'name' => $mail, 'role' => $role, '_test' => 1];
            };
            $admin = $mk('tasks-admin@example.test', 'admin');
            $ed = $mk('tasks-editor@example.test', 'editor');
            $ed2 = $mk('tasks-editor2@example.test', 'editor');
            $au = $mk('tasks-author@example.test', 'author');
            $eq('Standardrolle Redaktion: tasks.use', in_array('tasks.use', Permissions::defaults()['editor']['permissions'], true), true);
            // Rolle „Redaktion“ dieser Website für den Test auf den Standard setzen (wird zurückgerollt)
            $db->update('roles', ['permissions_json' => json_encode(Permissions::defaults()['editor']['permissions'])], "rkey = 'editor'", []);
            $eq('Redaktion: kein tasks.manage', self::manager($ed), false);
            $eq('Admin: tasks.manage', self::manager($admin), true);
            $eq('Autor: darf nicht anlegen', self::canCreate($au), false);
            $eq('Redaktion: nur sich selbst zuweisen', [self::mayAssign($ed, $ed['id'], null), self::mayAssign($ed, $ed2['id'], null), self::mayAssign($ed, null, 'editor'), self::mayAssign($ed, null, null)], [true, false, false, false]);
            $eq('Admin: alle zuweisen', self::mayAssign($admin, null, 'author'), true);
            $eq('Zuständigkeit: E-Mail', self::parseAssignee('TASKS-EDITOR@example.test'), [$ed['id'], null]);
            $eq('Zuständigkeit: Rolle', self::parseAssignee('role:editor'), [null, 'editor']);
            $eq('Zuständigkeit: alle', self::parseAssignee('all'), [null, null]);
            $bad = false;
            try { self::parseAssignee('gibtsnicht'); } catch (\InvalidArgumentException) { $bad = true; }
            $eq('Zuständigkeit: unbekannt', $bad, true);

            $act = self::actor($admin, 'cli');
            [$v1] = self::clean(['title' => 'Für Redaktion', 'assignee' => 'role:editor', 'due' => date('Y-m-d', time() - 86400), 'priority' => 'high', 'link' => 'page:1']);
            $t1 = self::create($v1, $act);
            [$v2] = self::clean(['title' => 'Für Autor', 'assignee' => (string) $au['id']]);
            $t2 = self::create($v2, $act);
            [$v3] = self::clean(['title' => 'Für alle']);
            $t3 = self::create($v3, $act);
            $ids = fn(array $list) => array_values(array_filter(array_map(fn($t) => $t['id'], $list), fn($i) => in_array($i, [$t1, $t2, $t3], true)));
            $eq('Redaktion sieht Rolle + alle', $ids(self::list($ed, 'open')), [$t1, $t3]);
            $eq('Autor sieht eigene + alle (neueste zuerst)', $ids(self::list($au, 'open')), [$t3, $t2]);
            $eq('Admin sieht alle (hoch zuerst)', $ids(self::list($admin, 'open')), [$t1, $t3, $t2]);
            $eq('Meine: ohne „alle“', $ids(self::list($ed, 'mine')), [$t1]);
            $eq('Ziel-Filter', $ids(self::list($admin, 'open', 'page:1')), [$t1]);
            $eq('Autor darf fremde nicht abhaken', self::canDone(self::find($t1), $au), false);
            $eq('Redaktion darf Rollen-Aufgabe abhaken', self::canDone(self::find($t1), $ed2), true);
            $eq('Redaktion darf fremde nicht bearbeiten', self::canEdit(self::find($t1), $ed), false);
            $eq('Übersicht: überfällig = Hinweis', array_values(array_map(fn($x) => $x['tone'], array_filter(self::todos($ed), fn($x) => $x['key'] === 'task:' . $t1))), ['warn']);

            self::done($t1, self::actor($ed2), "  erledigt <i>danke</i> ");
            $d = self::find($t1);
            $eq('Erledigt: Status, wer, Kommentar', [$d['status'], $d['done_by'], $d['done_comment']], ['done', $ed2['id'], 'erledigt danke']);
            $eq('Erledigt: nicht mehr offen', $ids(self::list($ed, 'open')), [$t3]);
            $eq('Erledigt-Liste', $ids(self::list($ed, 'done')), [$t1]);
            self::reopen($t1, self::actor($ed));
            $eq('Wieder offen', [self::find($t1)['status'], self::find($t1)['done_comment']], ['open', null]);
            $eq('Verlauf', array_column(self::log($t1), 'action'), ['created', 'done', 'reopened']);
            [$v4] = self::clean(['title' => 'Für Autor', 'assignee' => 'user:' . $ed['id']]);
            self::update($t2, $v4, $act);
            $eq('Neu zugewiesen', [self::find($t2)['assignee_id'], array_column(self::log($t2), 'action')], [$ed['id'], ['created', 'assigned']]);
            $eq('Autor sieht neu zugewiesene nicht mehr', $ids(self::list($au, 'open')), [$t3]);
            // API/MCP (Core\Tasks\Api): Rechte über die Rolle des Token-Benutzers, Token ohne Benutzer wie die Administration
            $deny = 0;
            try { Api::create(['name' => 't', 'user_id' => $ed['id']], ['title' => 'Für alle', 'assignee' => 'all']); } catch (\Core\Api\ApiError $x) { $deny = $x->status; }
            $eq('API: Redaktion nicht für andere', $deny, 403);
            $own = Api::create(['name' => 't', 'user_id' => $ed['id']], ['title' => 'Eigene']);
            $eq('API: Redaktion für sich', $own['assignee']['user_id'], $ed['id']);
            $tok = Api::create(['name' => 'Umzug', 'user_id' => null], ['title' => 'Offene Frage', 'link' => 'page:1'], 'mcp');
            $eq('API: Token ohne Benutzer', [$tok['assignee']['user_id'], $tok['created_by'], $tok['can_done']], [null, 'MCP „Umzug“', true]);
            $deny = 0;
            try { Api::done(['name' => 't', 'user_id' => $au['id']], (int) $own['id']); } catch (\Core\Api\ApiError $x) { $deny = $x->status; }
            $eq('API: fremde Aufgabe unsichtbar', $deny, 404);
            $eq('API: abhaken mit Kommentar', Api::done(['name' => 't', 'user_id' => $ed['id']], (int) $own['id'], 'ok')['done']['comment'] ?? null, 'ok');
            self::delete($t3);
            $eq('Gelöscht', [self::find($t3), self::log($t3)], [null, []]);
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            if ($db->pdo->inTransaction()) $db->pdo->rollBack();
            self::$quiet = $quiet;
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
