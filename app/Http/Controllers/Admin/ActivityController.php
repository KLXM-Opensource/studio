<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Activity;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * System → Aktionslog (Core\Activity): letzte Änderungen an Seiten, Datensätzen und Medien – filterbar nach Art, Aktion,
 * Person, Zeitraum und Name, sortierbar. Nur mit Recht system.manage.
 */
final class ActivityController extends AdminController
{
    private const PER_PAGE = 100;

    public function index(Request $r): Response
    {
        $this->auth($r);
        if (!Activity::canView()) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        $f = [
            'type' => in_array($r->str('art'), Activity::TYPES, true) ? $r->str('art') : '',
            'group' => isset(Activity::GROUPS[$r->str('aktion')]) ? $r->str('aktion') : '',
            'user' => $r->str('person') !== '' ? (string) (int) $r->str('person') : '',
            'days' => in_array((int) $r->str('zeitraum'), [1, 7, 30, 90, 365], true) ? (int) $r->str('zeitraum') : 30,
            'sort' => in_array($r->str('sortierung'), ['new', 'old', 'type', 'user'], true) ? $r->str('sortierung') : 'new',
            'q' => mb_substr(trim($r->str('q')), 0, 80),
        ];
        if ($r->str('zeitraum') === 'alle') $f['days'] = 0;
        $page = max(1, (int) $r->str('seite'));
        $res = Activity::query($f, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        // Anzahl je Art (mit den übrigen Filtern) für die Reiter
        $counts = ['' => 0];
        foreach (Activity::TYPES as $t) {
            $counts[$t] = Activity::query(['type' => $t] + $f, 1)['total'];
            $counts[''] += $counts[$t];
        }
        return $this->view('activity/index', ['f' => $f, 'rows' => $res['rows'], 'total' => $res['total'], 'page' => $page,
            'pages' => max(1, (int) ceil($res['total'] / self::PER_PAGE)), 'counts' => $counts, 'users' => Activity::users(), 'title' => __('Aktionslog')]);
    }
}
