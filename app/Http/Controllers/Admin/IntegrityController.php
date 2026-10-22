<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Integrity;
use Core\Permissions;

/**
 * Grundeinstellungen → Integrität (/admin/system/integritaet, Core\Integrity): letzte Prüfung von Dateien (nur wer die Installation
 * verwaltet) und Datenbank der Website, „Jetzt prüfen“, „Als bekannt übernehmen“, E-Mail-Hinweis an/aus.
 */
final class IntegrityController extends AdminController
{
    private const BASE = '/admin/system/integritaet';

    public function index(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        $files = Permissions::installationAdmin();
        return $this->view('system/integritaet', ['title' => __('Integrität'), 'files' => $files ? Integrity::lastFiles() : null, 'canFiles' => $files,
            'db' => Integrity::lastDb(), 'mail' => (bool) app()->settings->get('sys.integrity_mail', true)]);
    }

    /** Jetzt prüfen (Datenbank immer, Dateien nur für die Installations-Verwaltung) */
    public function check(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        if (Permissions::installationAdmin()) Integrity::checkFiles();
        Integrity::checkDb();
        return $this->back(self::BASE, 'success', __('Geprüft.'));
    }

    /** Aktuellen Stand als bekannt übernehmen: files | db */
    public function accept(Request $r, string $what): Response
    {
        $user = $this->auth($r, 'system.manage');
        $by = (string) ($user['email'] ?? '');
        if ($what === 'files') {
            if (!Permissions::installationAdmin()) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
            $n = Integrity::acceptFiles($by);
            return $this->back(self::BASE, 'success', __('{n} Dateien als bekannt übernommen.', ['n' => $n]));
        }
        if ($what !== 'db') throw new HttpException(404);
        Integrity::acceptDb($by);
        return $this->back(self::BASE, 'success', __('Stand der Datenbank als bekannt übernommen.'));
    }

    public function settings(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        app()->settings->set('sys.integrity_mail', !empty($r->post['mail']));
        return $this->back(self::BASE, 'success', __('Gespeichert.'));
    }
}
