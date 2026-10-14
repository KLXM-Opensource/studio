<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Branding;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

/** System → Branding & Support (Core\Branding): Logo, Name, Hinweis „Technik“, Support-Angaben der Agentur – für die ganze Installation */
final class BrandingController extends AdminController
{
    private function guard(Request $r): void
    {
        $this->auth($r);
        if (!Branding::canEdit()) throw new HttpException(403, __('Das Branding ändert im Netzwerk nur die Netzwerk-Administration, sonst die Administration.'));
    }

    public function index(Request $r): Response
    {
        $this->guard($r);
        return $this->view('system/branding', ['b' => Branding::get(), 'network' => !\Core\Domains::available()]);
    }

    public function save(Request $r): Response
    {
        $this->guard($r);
        $c = Branding::get();
        $c['name'] = mb_substr(trim(strip_tags($r->str('name'))), 0, 80);
        $c['show_product'] = $r->str('show_product') === '1';
        foreach (['name' => 80, 'phone' => 40, 'email' => 120, 'web' => 200, 'hours' => 120, 'note' => 600] as $k => $max) {
            $c['support'][$k] = mb_substr(trim(strip_tags((string) ($r->post['support'][$k] ?? ''))), 0, $max);
        }
        if ($c['support']['email'] !== '' && !filter_var($c['support']['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->back('/admin/system/branding', 'error', __('Bitte eine gültige E-Mail-Adresse für den Support angeben.'));
        }
        Branding::save($c);
        foreach (['logo' => 'logo', 'logo_dark' => 'logo_dark'] as $field => $which) {
            if (!empty($r->post['remove_' . $field])) Branding::removeLogo($which);
            $f = $r->files[$field] ?? null;
            if (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && ($err = Branding::storeLogo($f, $which))) {
                return $this->back('/admin/system/branding', 'error', $err);
            }
        }
        return $this->back('/admin/system/branding', 'success', __('Branding gespeichert.'));
    }

    /** Öffentlich: Logo (/branding/logo, /branding/logo-dark) – auch auf der Anmeldeseite */
    public function logo(Request $r): Response
    {
        return Branding::serve('light');
    }

    public function logoDark(Request $r): Response
    {
        return Branding::serve('dark');
    }
}
