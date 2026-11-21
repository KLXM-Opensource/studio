<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Monitoring\Status;
use Core\RateLimiter;
use Core\SecurityLog;

/**
 * Überwachung (Core\Monitoring): Status-Endpunkt (öffentliche Adresse, nur mit signierter Anfrage) und Einstellungen
 * (Grundeinstellungen → Überwachung, Recht system.manage).
 */
final class MonitoringController extends AdminController
{
    public function status(Request $r): Response
    {
        $json = fn(array $d, int $s) => Response::json($d, $s)->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex');
        $limiter = new RateLimiter(app()->db);
        $ipKey = 'mon:' . hash_hmac('sha256', $r->ip(), app()->key());
        if ($limiter->tooMany($ipKey, 30, 600)) return $json(['error' => 'rate_limited'], 429);
        $err = Status::check($r);
        // Ausgeschaltet: wie nicht vorhanden
        if ($err === 'off') return $json(['error' => 'not_found'], 404);
        $limiter->hit($ipKey);
        if ($err !== null) {
            SecurityLog::write('monitor-denied');
            return $json(['error' => 'unauthorized'], 401);
        }
        return $json(Status::collect((string) ($r->server['HTTP_X_STATUS_NONCE'] ?? '')), 200);
    }

    public function index(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        return $this->view('system/ueberwachung', ['title' => __('Überwachung'), 'mon' => Status::settings()]);
    }

    public function save(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        if (!Csrf::valid($r)) return $this->back('/admin/system/ueberwachung', 'error', __('Sitzung abgelaufen – bitte erneut versuchen.'));
        if (Status::settings()['from_config']) return $this->back('/admin/system/ueberwachung', 'error', __('Die Überwachung ist in der Konfiguration der Website festgelegt.'));
        $err = Status::save(!empty($r->post['enabled']), (string) ($r->post['keys'] ?? ''));
        \Core\InstanceInfo::flush();   // Selbstauskunft für den Hub (/.well-known/klxm-studio)
        return $this->back('/admin/system/ueberwachung', $err ? 'error' : 'success', $err ?? __('Überwachung gespeichert.'));
    }
}
