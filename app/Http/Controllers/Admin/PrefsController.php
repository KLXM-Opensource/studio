<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\AdminPages;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Sammelseiten der Verwaltung (Core\AdminPages): „Einstellungen“ (/admin/einstellungen) mit einer Karte je Einstellungsseite
 * von Funktionen und Erweiterungen, „Statistiken“ (/admin/statistiken) für Berichte. Sichtbar ist, was die Rolle öffnen darf –
 * jede Zielseite prüft ihre Rechte trotzdem selbst.
 */
final class PrefsController extends AdminController
{
    public function settings(Request $r): Response
    {
        $this->auth($r);
        $groups = AdminPages::settings();
        if (!$groups && !\Core\Features::canView() && !can('system.manage')) throw new HttpException(404);
        return $this->view('prefs/settings', ['groups' => $groups, 'title' => __('Einstellungen')]);
    }

    public function stats(Request $r): Response
    {
        $this->auth($r);
        $groups = AdminPages::stats();
        if (!$groups) throw new HttpException(404);
        return $this->view('prefs/stats', ['groups' => $groups, 'title' => __('Statistiken')]);
    }
}
