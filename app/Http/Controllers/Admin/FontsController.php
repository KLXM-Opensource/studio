<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Features;
use Core\Fonts;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Grundeinstellungen → Schriften (Core\Fonts): Google-Fonts-Katalog durchsuchen, Schriften installieren (selbst gehostet
 * unter public/assets/fonts/installed, für alle Websites), Vorladen, Entfernen. Vorschau-Schriften kommen von der eigenen Domain.
 * Recht: system.manage (oder Integrator), Funktion „fonts“.
 */
final class FontsController extends AdminController
{
    private function guard(Request $r): array
    {
        $user = $this->auth($r);
        if (!Features::on('fonts')) throw new HttpException(404);
        if (!Fonts::canManage()) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        return $user;
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        $q = mb_substr(trim($r->str('q')), 0, 60);
        $cat = $r->str('cat');
        if (!isset(Fonts::CATEGORIES[$cat])) $cat = '';
        $pick = strtolower($r->str('font'));
        $detail = $pick !== '' && Fonts::validId($pick) ? Fonts::detail($pick) : null;
        $catalog = Fonts::catalog();
        return $this->view('system/fonts', [
            'title' => __('Schriften'), 'q' => $q, 'cat' => $cat, 'text' => mb_substr($r->str('text'), 0, 120),
            'results' => $catalog ? Fonts::search($q, $cat) : [], 'catalogOk' => (bool) $catalog, 'catalogSize' => count($catalog),
            'detail' => $detail, 'installed' => Fonts::installed(), 'css' => [],
        ]);
    }

    public function install(Request $r): Response
    {
        $user = $this->guard($r);
        $id = strtolower($r->str('font'));
        $opt = [
            'variable' => $r->str('mode') === 'variable',
            'weights' => array_map('intval', (array) ($r->post['weights'] ?? [])),
            'styles' => array_map('strval', (array) ($r->post['styles'] ?? ['normal'])),
            'subsets' => array_map('strval', (array) ($r->post['subsets'] ?? Fonts::DEFAULT_SUBSETS)),
            'preload' => !empty($r->post['preload']),
        ];
        @set_time_limit(120);
        $res = Fonts::install($id, $opt, (string) ($user['email'] ?? ''));
        $this->changed();
        return $this->back('/admin/system/fonts' . ($res['ok'] ? '#installiert' : '?font=' . rawurlencode($id) . '#installieren'), $res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function remove(Request $r, string $id): Response
    {
        $this->guard($r);
        $res = Fonts::remove($id, !empty($r->post['force']));
        $this->changed();
        return $this->back('/admin/system/fonts#installiert', $res['ok'] ? 'success' : 'error', $res['message']);
    }

    public function preload(Request $r, string $id): Response
    {
        $this->guard($r);
        $on = !empty($r->post['preload']);
        Fonts::setPreload($id, $on);
        $this->changed();
        return $this->back('/admin/system/fonts#installiert', 'success', $on ? __('Hauptschnitt wird vorgeladen.') : __('Vorladen ausgeschaltet.'));
    }

    /** Vorschau-Schrift (woff2) – serverseitig geladen und zwischengespeichert, ausgeliefert von der eigenen Domain */
    public function preview(Request $r, string $id): Response
    {
        $this->guard($r);
        $file = Fonts::previewFile($id);
        if (!$file) throw new HttpException(404);
        return (new Response((string) file_get_contents($file), 200, ['Content-Type' => 'font/woff2']))
            ->header('Cache-Control', 'private, max-age=604800')->header('X-Content-Type-Options', 'nosniff');
    }
}
