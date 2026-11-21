<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Tasks\Tasks;

/**
 * Verwaltung → Aufgaben (/admin/aufgaben, Core\Tasks\Tasks): Liste mit Filter (offen, meine, erledigt, alle), anlegen (Dialog bzw.
 * eigene Seite ohne JavaScript, auch mit Ziel ?link=page:12), Einzelansicht mit Verlauf, bearbeiten, abhaken mit Kommentar,
 * wieder öffnen, löschen, Erinnerung (Website) und E-Mail-Schalter (Konto). Jede angemeldete Person sieht die Aufgaben, für die
 * sie zuständig ist; Rechte im Einzelnen: Tasks::canCreate/canDone/canEdit/manager.
 */
final class TaskController extends AdminController
{
    public function index(Request $r): Response
    {
        $u = $this->auth($r);
        $f = in_array($r->str('filter'), Tasks::FILTERS, true) ? $r->str('filter') : 'open';
        return $this->view('tasks/index', ['title' => __('Aufgaben'), 'css' => ['css/tasks.css'], 'f' => $f, 'items' => Tasks::list($u, $f),
            'counts' => Tasks::counts($u), 'me' => $u, 'form' => $this->formVars($u, $r)]);
    }

    /** Eigene Seite zum Anlegen (ohne JavaScript bzw. aus dem Zusammenhang: ?link=page:12&back=/admin/pages/12) */
    public function create(Request $r, array $values = [], array $errors = []): Response
    {
        $u = $this->auth($r);
        if (!Tasks::canCreate($u)) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        return $this->view('tasks/edit', ['title' => __('Neue Aufgabe'), 'css' => ['css/tasks.css'], 'task' => null, 'me' => $u,
            'form' => $this->formVars($u, $r, $values, $errors)], $errors ? 422 : 200);
    }

    public function store(Request $r): Response
    {
        $u = $this->auth($r);
        if (!Tasks::canCreate($u)) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        $in = $this->input($r, $u);
        [$vals, $err] = Tasks::clean($in);
        if (!$err && !Tasks::mayAssign($u, $vals['assignee_id'], $vals['assignee_role'])) $err['assignee'] = __('Für andere anlegen dürfen nur Personen mit dem Recht „Aufgaben für andere anlegen“.');
        if ($err) return $this->create($r, $in, $err);
        $id = Tasks::create($vals, Tasks::actor($u), ['source' => 'admin']);
        $back = $this->backUrl($r);
        return $this->back($back ?? '/admin/aufgaben/' . $id, 'success', __('Aufgabe „{title}“ angelegt.', ['title' => $vals['title']]));
    }

    public function show(Request $r, string $id): Response
    {
        $u = $this->auth($r);
        $t = $this->task($id, $u);
        return $this->view('tasks/show', ['title' => $t['title'], 'css' => ['css/tasks.css'], 't' => $t, 'log' => Tasks::log((int) $t['id']), 'me' => $u,
            'names' => Tasks::people()]);
    }

    public function edit(Request $r, string $id, array $values = [], array $errors = []): Response
    {
        $u = $this->auth($r);
        $t = $this->task($id, $u);
        if (!Tasks::canEdit($t, $u)) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        $values = $values ?: ['title' => $t['title'], 'body' => (string) $t['body'], 'link' => (string) $t['link'], 'assignee' => Tasks::assigneeValue($t),
            'due' => (string) $t['due'], 'priority' => $t['priority']];
        return $this->view('tasks/edit', ['title' => __('Aufgabe bearbeiten'), 'css' => ['css/tasks.css'], 'task' => $t, 'me' => $u,
            'form' => $this->formVars($u, $r, $values, $errors)], $errors ? 422 : 200);
    }

    public function update(Request $r, string $id): Response
    {
        $u = $this->auth($r);
        $t = $this->task($id, $u);
        if (!Tasks::canEdit($t, $u)) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        $in = $this->input($r, $u, $t);
        [$vals, $err] = Tasks::clean($in);
        $same = $vals['assignee_id'] === $t['assignee_id'] && $vals['assignee_role'] === $t['assignee_role'];
        if (!$err && !$same && !Tasks::mayAssign($u, $vals['assignee_id'], $vals['assignee_role'])) $err['assignee'] = __('Für andere anlegen dürfen nur Personen mit dem Recht „Aufgaben für andere anlegen“.');
        if ($err) return $this->edit($r, $id, $in, $err);
        Tasks::update((int) $t['id'], $vals, Tasks::actor($u));
        return $this->back('/admin/aufgaben/' . (int) $t['id'], 'success', __('Aufgabe gespeichert.'));
    }

    /** Abhaken (optional mit Kommentar) bzw. wieder öffnen */
    public function done(Request $r, string $id): Response
    {
        return $this->state($r, $id, true);
    }

    public function reopen(Request $r, string $id): Response
    {
        return $this->state($r, $id, false);
    }

    private function state(Request $r, string $id, bool $done): Response
    {
        $u = $this->auth($r);
        $t = $this->task($id, $u);
        $back = $this->backUrl($r) ?? '/admin/aufgaben/' . (int) $t['id'];
        if (!Tasks::canDone($t, $u)) throw new HttpException(403, __('Abhaken darf nur, wer zuständig ist oder die Aufgabe angelegt hat.'));
        $comment = (string) ($r->post['comment'] ?? '');
        if ($done && $t['status'] !== 'done') Tasks::done((int) $t['id'], Tasks::actor($u), $comment);
        if (!$done && $t['status'] !== 'open') Tasks::reopen((int) $t['id'], Tasks::actor($u), $comment);
        return $this->back($back, 'success', $done ? __('Erledigt: {title}', ['title' => $t['title']]) : __('Wieder offen: {title}', ['title' => $t['title']]));
    }

    public function delete(Request $r, string $id): Response
    {
        $u = $this->auth($r);
        $t = $this->task($id, $u);
        if (!Tasks::canEdit($t, $u)) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        Tasks::delete((int) $t['id']);
        return $this->back('/admin/aufgaben', 'success', __('Aufgabe „{title}“ gelöscht.', ['title' => $t['title']]));
    }

    /** Erinnerung an fällige Aufgaben für diese Website ein/aus (tasks.manage) */
    public function settings(Request $r): Response
    {
        $u = $this->auth($r);
        if (!Tasks::manager($u)) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        $on = ($r->post['remind'] ?? '') === '1';
        app()->settings->set(Tasks::REMIND_SETTING, $on);
        return $this->back('/admin/aufgaben', 'success', $on ? __('Erinnerung an fällige Aufgaben eingeschaltet.') : __('Erinnerung an fällige Aufgaben ausgeschaltet.'));
    }

    /** Konto → Mitteilungen: E-Mails zu Aufgaben ein/aus */
    public function prefs(Request $r): Response
    {
        $u = $this->auth($r);
        $on = ($r->post['mail'] ?? '') === '1';
        \Core\UiPrefs::set((int) $u['id'], Tasks::MAIL_PREF, $on ? null : false);
        return $this->back('/admin/account#aufgaben', 'success', $on ? __('E-Mails zu Aufgaben eingeschaltet.') : __('E-Mails zu Aufgaben ausgeschaltet.'));
    }

    // ------------------------------------------------------------------ Hilfen

    private function task(string $id, array $u): array
    {
        $t = ctype_digit($id) ? Tasks::find((int) $id) : null;
        if (!$t || !Tasks::canSee($t, $u)) throw new HttpException(404, __('Aufgabe nicht gefunden.'));
        return $t;
    }

    /** Eingaben des Formulars; ohne tasks.manage ist man selbst zuständig */
    private function input(Request $r, array $u, ?array $t = null): array
    {
        $in = ['title' => (string) ($r->post['title'] ?? ''), 'body' => (string) ($r->post['body'] ?? ''), 'link' => (string) ($r->post['link'] ?? ''),
            'assignee' => (string) ($r->post['assignee'] ?? ''), 'due' => (string) ($r->post['due'] ?? ''), 'priority' => (string) ($r->post['priority'] ?? 'normal')];
        if (!Tasks::manager($u)) $in['assignee'] = $t ? Tasks::assigneeValue($t) : 'user:' . (int) $u['id'];
        return $in;
    }

    /** Werte für das Formular (Dialog und eigene Seite) */
    private function formVars(array $u, Request $r, array $values = [], array $errors = []): array
    {
        $link = Tasks::cleanLink($r->str('link'));
        return Tasks::formDefaults($u, is_string($link) ? $link : '', $values, $errors) + ['back' => $this->backUrl($r) ?? ''];
    }

    /** Rücksprung nur zu Verwaltungsseiten (Übersicht, Aufgaben, Seite, Eintrag) */
    private function backUrl(Request $r): ?string
    {
        $b = $r->str('back');
        return preg_match('~^/admin(/aufgaben(\?filter=[a-z]+)?|/pages/\d+|/data/[a-z][a-z0-9_]*/\d+)?(#[a-z0-9-]+)?$~', $b) ? $b : null;
    }
}
