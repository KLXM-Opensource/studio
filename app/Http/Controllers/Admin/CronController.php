<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Cron;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Grundeinstellungen → Geplante Aufgaben (/admin/system/aufgaben, Core\Cron): Zustand des einen Cronjobs, Aufgaben von Kern und
 * Erweiterungen mit Zeitplan, nächstem und letztem Lauf; Zeitplan ändern, an/aus, „Jetzt ausführen“ (Warteschlange), Protokoll.
 * Gilt für die ganze Installation – Recht wie bei Kits und Schriften.
 */
final class CronController extends AdminController
{
    private const BASE = '/admin/system/aufgaben';

    private function guard(Request $r): array
    {
        $user = $this->auth($r);
        if (!Cron::canManage()) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        return $user;
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        $tasks = Cron::tasks();
        $sites = [];
        foreach ($tasks as $k => $t) if ($t['owner'] !== 'core') $sites[$k] = Cron::sitesFor($k);
        $db = Cron::db();
        return $this->view('system/aufgaben', ['title' => __('Geplante Aufgaben'), 'tasks' => $tasks, 'sites' => $sites,
            'heartbeat' => Cron::heartbeat(), 'running' => Cron::running(), 'line' => Cron::line(),
            'queue' => $db->fetchAll("SELECT * FROM cron_queue WHERE status IN ('waiting', 'running') OR ended > ? ORDER BY id DESC LIMIT 20", [time() - 86400]),
            'runs' => $db->fetchAll('SELECT * FROM cron_runs ORDER BY id DESC LIMIT 40'), 'show' => (string) ($r->query['protokoll'] ?? '')]);
    }

    /** Zeitplan und an/aus einer Aufgabe */
    public function save(Request $r, string $key): Response
    {
        $this->guard($r);
        $tasks = Cron::tasks();
        if (!isset($tasks[$key])) throw new HttpException(404);
        $sched = trim((string) ($r->post['schedule'] ?? ''));
        if ($sched === 'custom') $sched = trim(preg_replace('~\s+~', ' ', (string) ($r->post['custom'] ?? '')));
        try {
            Cron::configure($key, !empty($r->post['enabled']), $sched === '' ? $tasks[$key]['default'] : $sched);
        } catch (\InvalidArgumentException $e) {
            return $this->back(self::BASE . '#t-' . $key, 'error', $e->getMessage() . ' ' . __('Format: Minute Stunde Tag Monat Wochentag, z. B. „30 3 * * *“.'));
        }
        return $this->back(self::BASE . '#t-' . $key, 'success', __('„{name}“ gespeichert.', ['name' => $tasks[$key]['label']]));
    }

    /** Jetzt ausführen: in die Warteschlange (läuft beim nächsten Aufruf von cron:run, spätestens in einer Minute) */
    public function run(Request $r, string $key): Response
    {
        $user = $this->guard($r);
        if (!isset(Cron::tasks()[$key])) throw new HttpException(404);
        Cron::enqueue($key, (string) ($user['email'] ?? ''));
        return $this->back(self::BASE . '#t-' . $key, 'success', Cron::running()
            ? __('Eingereiht – läuft innerhalb der nächsten Minute.')
            : __('Eingereiht – läuft, sobald der Cronjob eingerichtet ist (siehe oben).'));
    }
}
