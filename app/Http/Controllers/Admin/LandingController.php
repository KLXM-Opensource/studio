<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Features;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Landings;

/** Grundeinstellungen → Landingpages: weitere Domains zeigen eine Seite bzw. einen Seitenzweig (Core\Landings). */
final class LandingController extends AdminController
{
    private function gate(Request $r): array
    {
        $user = $this->auth($r, 'system.manage');
        if (!Features::on('landings')) throw new HttpException(404);
        Landings::rememberOrigin();   // Adresse der Hauptdomain merken (Rückfall für Links von Landing-Domains)
        return $user;
    }

    public function index(Request $r): Response
    {
        $this->gate($r);
        return $this->view('landings/index', ['landings' => Landings::all(), 'main' => Landings::mainOrigin(),
            'free' => array_values(array_filter(site()->landingHosts(), fn($h) => !Landings::isLandingHost($h)))]);
    }

    public function edit(Request $r, string $id = '', array $errors = [], ?array $values = null): Response
    {
        $this->gate($r);
        $l = $id !== '' ? (Landings::find((int) $id) ?? throw new HttpException(404)) : null;
        if (!$l && $values === null) {
            $values = Landings::values(null);
            if (($p = (int) ($r->query['page'] ?? 0)) > 0) $values['page_id'] = $p;   // aus den Seiteneinstellungen
        }
        return $this->view('landings/edit', ['landing' => $l, 'fields' => Landings::fields($l?->id), 'values' => $values ?? Landings::values($l),
            'errors' => $errors, 'check' => $l ? app()->session->get('_landing_check_' . $l->id) : null, 'main' => Landings::mainOrigin()],
            $errors ? 422 : 200);
    }

    public function save(Request $r, string $id = ''): Response
    {
        $this->gate($r);
        $existing = $id !== '' ? (Landings::find((int) $id) ?? throw new HttpException(404)) : null;
        $input = (array) ($r->post['f'] ?? []);
        [$newId, $errors] = Landings::save($input, $existing?->id);
        if ($errors) {
            app()->session->flash('error', __('Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.'));
            return $this->edit($r, $id, $errors, $input + Landings::values($existing));
        }
        return $this->back('/admin/landingpages/' . $newId, 'success', __('Landingpage gespeichert.'));
    }

    public function delete(Request $r, string $id): Response
    {
        $this->gate($r);
        $l = Landings::find((int) $id) ?? throw new HttpException(404);
        Landings::delete($l->id);
        return $this->back('/admin/landingpages', 'success', __('Landingpage „{name}“ gelöscht. Die Domain bleibt für die Website eingerichtet.', ['name' => $l->label ?: $l->host()]));
    }

    /** Status: DNS und Selbsttest (/health?landing=…) je Domain – Ergebnis bleibt bis zur nächsten Prüfung in der Sitzung */
    public function check(Request $r, string $id): Response
    {
        $this->gate($r);
        $l = Landings::find((int) $id) ?? throw new HttpException(404);
        $limiter = new \Core\RateLimiter(app()->db);
        if ($limiter->tooMany('landing-check:' . $l->id, 10, 300)) {
            return $this->back('/admin/landingpages/' . $l->id . '#status', 'error', __('Bitte einen Moment warten und dann erneut prüfen.'));
        }
        $limiter->hit('landing-check:' . $l->id);
        $out = [];
        foreach ($l->hosts as $h) $out[$h] = Landings::check($l, $h);
        app()->session->set('_landing_check_' . $l->id, ['at' => time(), 'hosts' => $out]);
        return $this->back('/admin/landingpages/' . $l->id . '#status', 'success', __('Status geprüft.'));
    }
}
