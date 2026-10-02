<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Fields;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Pages;
use Core\PageTemplates;

/** Werkzeuge → Seitenvorlagen: Vorlagen für die Redaktion anbieten und anordnen (Core\PageTemplates). */
final class PageTemplateController extends AdminController
{
    public function index(Request $r, array $errors = [], ?array $values = null): Response
    {
        $this->auth($r, 'system.manage');
        return $this->view('pagetemplates/index', ['fields' => PageTemplates::fields(), 'values' => $values ?? PageTemplates::values(),
            'errors' => $errors, 'templates' => PageTemplates::all()], $errors ? 422 : 200);
    }

    public function save(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        $input = (array) ($r->post['f'] ?? []);
        [$values, $errors] = Fields::sanitize(PageTemplates::fields(), $input);
        if ($errors) {
            app()->session->flash('error', __('Bitte prüfen Sie die markierten Felder – es wurde nichts gespeichert.'));
            return $this->index($r, $errors, $values);
        }
        PageTemplates::save((array) ($values['templates'] ?? []));
        return $this->back('/admin/seitenvorlagen', 'success', __('Seitenvorlagen gespeichert.'));
    }

    /** Seitenbaum „⋯“: Seite als Vorlage anbieten bzw. nicht mehr anbieten (JSON) */
    public function toggle(Request $r, string $id): Response
    {
        $this->auth($r, 'system.manage');
        $p = Pages::find((int) $id) ?? throw new HttpException(404);
        $on = !PageTemplates::isSource((int) $p['id']);
        $on ? PageTemplates::add((int) $p['id']) : PageTemplates::remove((int) $p['id']);
        return Response::json(['ok' => true, 'template' => $on]);
    }
}
