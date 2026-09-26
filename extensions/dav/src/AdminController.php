<?php
declare(strict_types=1);

namespace MyCms\Dav;

use Core\Data\Calendar;
use Core\Data\Tables;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Theme;

/** Verwaltung → CalDAV/CardDAV: eigene App-Passwörter (alle mit Recht dav.use), Tabellen-Einstellungen (Recht data.schema). */
final class AdminController extends \Core\Http\Controllers\Admin\AdminController
{
    private function page(array $vars = [], int $status = 200): Response
    {
        $user = app()->auth->user();
        $vars += ['user' => $user, 'newPassword' => null, 'errors' => []];
        $vars['passwords'] = Dav::passwords((int) $user['id']);
        $vars['tables'] = can('data.schema') ? Tables::content() : [];
        $vars['flash'] = app()->session->takeFlash();
        $vars['css'] = [];
        $content = Theme::capture(dirname(__DIR__) . '/views/admin.php', $vars);
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => 'dav', 'title' => __('Kalender & Kontakte in Apps')]);
        return self::secure(new Response($html, $status));
    }

    private function guard(Request $r, string $perm = 'dav.use'): array
    {
        if (!Dav::enabled()) throw new HttpException(404);
        return $this->auth($r, $perm);
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        return $this->page();
    }

    public function create(Request $r): Response
    {
        $user = $this->guard($r);
        $name = $r->str('name');
        if ($name === '') return $this->page(['errors' => ['name' => __('Bitte einen Namen angeben, z. B. „iPhone“ oder „Thunderbird Büro“.')]], 422);
        $pw = Dav::createPassword((int) $user['id'], $name, $r->str('scope', 'write'));
        return $this->page(['newPassword' => $pw]);
    }

    public function delete(Request $r, string $id): Response
    {
        $user = $this->guard($r);
        Dav::revoke((int) $user['id'], (int) $id);
        return $this->back('/admin/dav', 'success', __('App-Passwort widerrufen.'));
    }

    public function tables(Request $r): Response
    {
        $this->guard($r, 'data.schema');
        foreach (Tables::content() as $t) {
            $in = (array) ($r->post['t'][$t['handle']] ?? []);
            if (!$in) continue;
            $s = Dav::tableSettings($t);
            $s['caldav'] = !empty($in['caldav']);
            $s['addressbook'] = !Calendar::enabled($t) && !empty($in['addressbook']);
            $s['status'] = ($in['status'] ?? '') === 'draft' ? 'draft' : 'published';
            $s['on_delete'] = ($in['on_delete'] ?? '') === 'delete' ? 'delete' : 'draft';
            $map = [];
            $types = array_column($t['fields'], 'type', 'name');
            foreach (Dav::CARD_MAP as $k => $allowed) {
                $v = (string) ($in['map'][$k] ?? '');
                if (isset($types[$v]) && in_array($types[$v], $allowed, true)) $map[$k] = $v;
            }
            $s['map'] = $s['addressbook'] ? ($map ?: Dav::guessMap($t)) : $map;
            Dav::saveTableSettings($t['handle'], $s);
        }
        return $this->back('/admin/dav#tabellen', 'success', __('Einstellungen gespeichert.'));
    }
}
