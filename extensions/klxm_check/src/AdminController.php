<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Theme;

/**
 * Verwaltung → KLXM Check: Zustand (ausgehende Verbindungen), Grenzen, anonyme Zähler, Schalter „eigene Seite /check“
 * (Einstellung ext.klxm_check.page; die Konfiguration 'klxm_check' => ['page' => …] geht vor). Recht system.manage.
 */
final class AdminController extends \Core\Http\Controllers\Admin\AdminController
{
    private function guard(Request $r): array
    {
        if (!\Core\Extensions::isActive(Check::NAME)) throw new HttpException(404);
        return $this->auth($r, 'system.manage');
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        $vars = ['title' => __('KLXM Check'), 'flash' => app()->session->takeFlash(), 'user' => app()->auth->user(), 'css' => [],
            'health' => Check::health(), 'limits' => Check::limits(), 'ttl' => Check::ttl(), 'smtp' => Check::smtpProbe(), 'on' => Check::on(),
            'page' => Check::pageOn(), 'pageSetting' => Check::pageSetting(), 'pageLocked' => Check::pageLock(), 'path' => Check::path(),
            'stats' => Check::totals(30), 'days' => Check::stats(), 'resolver' => DnsWire::resolver() !== null, 'curl' => function_exists('curl_init'),
            'intl' => function_exists('idn_to_ascii')];
        $content = Theme::capture(dirname(__DIR__) . '/views/admin.php', $vars);
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => 'klxm-check']);
        return self::secure(new Response($html));
    }

    public function save(Request $r): Response
    {
        $this->guard($r);
        if (Check::pageLock() !== null) return $this->back('/admin/klxm-check', 'error', __('Per Konfiguration festgelegt – hier nicht änderbar.'));
        $on = $r->str('page') === '1';
        app()->settings->set(Check::PAGE_SETTING, $on);
        \Core\PageCache::clear();
        return $this->back('/admin/klxm-check', 'success', $on
            ? __('Die eigene Seite {path} ist eingeschaltet.', ['path' => Check::path()])
            : __('Die eigene Seite {path} ist ausgeschaltet.', ['path' => Check::path()]));
    }
}
