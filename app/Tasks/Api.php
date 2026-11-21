<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Tasks;

use Core\Api\ApiError;

/**
 * Aufgaben über REST-API (/api/v1/tasks) und MCP (task_list, task_create, task_done) – eine Implementierung für beide.
 * Gehandelt wird im Namen des Benutzers, dem das Token gehört: Sichtbarkeit und Rechte wie in der Verwaltung
 * (tasks.use = für sich selbst anlegen, tasks.manage = für andere; abhaken darf jede zuständige Person). Tokens mit Benutzer gehören
 * heute immer einem Konto mit allen Rechten (Core\Api\Tokens::checkOwner); Tokens ohne Benutzer (Kommandozeile) handeln wie die
 * Administration, im Verlauf mit dem Namen des Tokens. Aufgaben ändern keine Inhalte – sie laufen daher nicht über die Prüf-Ebene „Eingereicht“.
 */
final class Api
{
    /** Benutzer des Tokens (Rechte über seine Rolle, nicht über eine Sitzung); ohne Benutzer: wie die Administration (id 0) */
    private static function user(array $token): array
    {
        $uid = $token['user_id'] ?? null;
        if ($uid === null || $uid === '') return ['id' => 0, 'role' => 'admin', 'name' => '', 'email' => '', '_test' => 1, '_token' => true];
        $u = app()->db->fetch('SELECT * FROM users WHERE id = ?', [(int) $uid]);
        if (!$u || !empty($u['disabled'])) throw new ApiError(403, 'Das Konto dieses Tokens ist gelöscht oder gesperrt.');
        return $u + ['_test' => 1];
    }

    private static function actor(array $token, array $u, string $channel): array
    {
        $via = strtoupper($channel) . ' „' . ($token['name'] ?? '') . '“';
        if (!empty($u['_token'])) return Tasks::actor(null, $channel, mb_substr($via, 0, 191));
        $name = trim((string) ($u['name'] ?? '')) ?: (string) $u['email'];
        return Tasks::actor($u, $channel, mb_substr($name . ' (' . $via . ')', 0, 191));
    }

    /** Liste: status open (Standard) | done | all, mine = nur persönlich zuständig, link = nur zu diesem Ziel */
    public static function list(array $token, array $q): array
    {
        $u = self::user($token);
        $status = (string) ($q['status'] ?? 'open');
        $filter = !empty($q['mine']) && filter_var($q['mine'], FILTER_VALIDATE_BOOL) ? 'mine' : (in_array($status, ['open', 'done', 'all'], true) ? $status : 'open');
        $link = isset($q['link']) && (string) $q['link'] !== '' ? (string) $q['link'] : null;
        $limit = max(1, min(200, (int) ($q['limit'] ?? 100)));
        $rows = array_slice(Tasks::list($u, $filter, $link), 0, $limit);
        return ['tasks' => array_map(fn($t) => self::out($t, $u), $rows), 'can_create' => Tasks::canCreate($u), 'can_assign_others' => Tasks::manager($u),
            'assignees' => Tasks::manager($u) ? ['roles' => array_keys(Tasks::roles()), 'hint' => 'assignee: Benutzer-ID, E-Mail, role:{schlüssel} oder all'] : null];
    }

    /** Anlegen: title (Pflicht), body (Markdown-lite), link (page:ID, page:ID#anker, entry:tabelle:ID, media:ID, /pfad), assignee, due, priority */
    public static function create(array $token, array $in, string $channel = 'api'): array
    {
        $u = self::user($token);
        if (!Tasks::canCreate($u)) throw new ApiError(403, 'Die Rolle des Token-Benutzers darf keine Aufgaben anlegen (Recht tasks.use bzw. tasks.manage).');
        // Ohne Angabe: tasks.manage → alle, sonst die Person selbst
        if (!array_key_exists('assignee', $in) || $in['assignee'] === null) $in['assignee'] = Tasks::manager($u) ? '' : 'user:' . (int) $u['id'];
        [$vals, $err] = Tasks::clean($in);
        if ($err) throw new ApiError(422, implode(' ', $err), ['fields' => $err]);
        if (!Tasks::mayAssign($u, $vals['assignee_id'], $vals['assignee_role'])) {
            throw new ApiError(403, 'Für andere Personen, Rollen oder alle anlegen braucht das Recht tasks.manage – ohne bleibt nur der Token-Benutzer selbst.');
        }
        $id = Tasks::create($vals, self::actor($token, $u, $channel), ['source' => $channel]);
        return self::out(Tasks::find($id) ?? [], $u);
    }

    /** Abhaken mit optionalem Kommentar */
    public static function done(array $token, int $id, string $comment = '', string $channel = 'api'): array
    {
        $u = self::user($token);
        $t = Tasks::find($id);
        if (!$t || !Tasks::canSee($t, $u)) throw new ApiError(404, 'Aufgabe nicht gefunden.');
        if (!Tasks::canDone($t, $u)) throw new ApiError(403, 'Abhaken darf nur, wer zuständig ist, wer sie angelegt hat, oder tasks.manage.');
        if ($t['status'] !== 'done') Tasks::done($id, self::actor($token, $u, $channel), $comment);
        return self::out(Tasks::find($id) ?? [], $u);
    }

    /** Wieder öffnen */
    public static function reopen(array $token, int $id, string $comment = '', string $channel = 'api'): array
    {
        $u = self::user($token);
        $t = Tasks::find($id);
        if (!$t || !Tasks::canSee($t, $u)) throw new ApiError(404, 'Aufgabe nicht gefunden.');
        if (!Tasks::canDone($t, $u)) throw new ApiError(403, 'Wieder öffnen darf nur, wer zuständig ist, wer sie angelegt hat, oder tasks.manage.');
        if ($t['status'] !== 'open') Tasks::reopen($id, self::actor($token, $u, $channel), $comment);
        return self::out(Tasks::find($id) ?? [], $u);
    }

    /** Ausgabe einer Aufgabe (Entwurfs-Notizen mit kind = draft, ohne id) */
    public static function out(array $t, array $u): array
    {
        $target = Tasks::target($t['link'] ?? null);
        return [
            'id' => $t['id'] ?? null, 'kind' => $t['kind'] ?? 'task', 'title' => (string) ($t['title'] ?? ''), 'body' => (string) ($t['body'] ?? ''), 'body_format' => 'markdown-lite',
            'status' => (string) ($t['status'] ?? 'open'), 'priority' => (string) ($t['priority'] ?? 'normal'), 'due' => $t['due'] ?? null,
            'due_state' => Tasks::dueState($t['due'] ?? null) ?: null,
            'link' => $t['link'] ?? null, 'target' => $target ? ['type' => $target['type'], 'label' => $target['label'], 'url' => $target['href'] !== '' ? abs_url($target['href']) : null, 'missing' => $target['missing']] : null,
            'assignee' => ['user_id' => $t['assignee_id'] ?? null, 'role' => $t['assignee_role'] ?? null, 'label' => Tasks::assigneeLabel($t)],
            'created_by' => (string) ($t['created_label'] ?? ''), 'created_at' => $t['created_at'] ?? null,
            'done' => ($t['status'] ?? '') === 'done' ? ['at' => $t['done_at'], 'by' => (string) ($t['done_label'] ?? ''), 'comment' => $t['done_comment'] ?? null] : null,
            'can_done' => ($t['kind'] ?? 'task') === 'task' && Tasks::canDone($t, $u),
            'url' => admin_url_abs(Tasks::url($t)),
        ];
    }
}
