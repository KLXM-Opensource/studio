<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Impersonation;

/** Ansicht als Rolle bzw. als Person starten und beenden (Core\Impersonation) */
final class ImpersonateController extends AdminController
{
    /** POST /admin/impersonate {mode: role|user, value} – nur Administration (echtes Konto) */
    public function start(Request $r): Response
    {
        $u = $this->auth($r);
        // Echte Person (auch wenn schon eine Ansicht läuft: zuerst beenden)
        $realId = (int) ($u['_imp']['real_id'] ?? $u['id']);
        if (!empty($u['_imp'])) Impersonation::stop();
        $real = app()->db->fetch('SELECT id, email, name, role, network_uid, disabled FROM users WHERE id = ?', [$realId]);
        if (!Impersonation::canStart($real)) throw new HttpException(403, __('Nur die Administration kann die Ansicht wechseln.'));
        $err = Impersonation::start($real, (string) ($r->post['mode'] ?? ''), (string) ($r->post['value'] ?? ''));
        if ($err) return $this->back('/admin/users', 'error', $err);
        app()->auth->reset();
        $now = app()->auth->user();
        app()->session->flash('success', __('Sie arbeiten jetzt als „{name}“ – bis {time} oder bis Sie zurückwechseln.', ['name' => $now['_imp']['label'] ?? '', 'time' => date('H:i', (int) ($now['_imp']['until'] ?? time()))]));
        return Response::redirect(url('/admin'), 303);
    }

    /** POST /admin/impersonate/stop – zurück zum eigenen Konto */
    public function stop(Request $r): Response
    {
        $this->auth($r);
        Impersonation::stop();
        app()->session->flash('success', __('Sie arbeiten wieder mit Ihrem eigenen Konto.'));
        $back = (string) ($r->post['back'] ?? '');
        return Response::redirect($back !== '' && str_starts_with($back, '/') && !str_starts_with($back, '//') && !str_contains($back, '\\') ? $back : url('/admin/users'), 303);
    }
}
