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

/** Einrichtung › Website › Seitenvorlagen: Vorlagen anlegen, gestalten, anordnen (Core\PageTemplates) – nur Administration. */
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

    /** Neue Vorlage (leer oder aus einer Seite kopiert) → gleich im Block-Editor öffnen */
    public function create(Request $r): Response
    {
        $this->auth($r, 'system.manage');
        $from = (int) ($r->post['from'] ?? 0);
        $id = PageTemplates::create((string) ($r->post['label'] ?? ''), $from > 0 ? $from : null);
        app()->session->flash('success', __('Vorlage angelegt. Gestalten Sie jetzt die Blöcke – Name, Symbol und Reihenfolge unter Einrichtung › Website › Seitenvorlagen.'));
        return Response::redirect(PageTemplates::editUrl($id));
    }

    /** Seitenbaum „⋯ → Als Vorlage speichern“: Kopie der Seite als neue Vorlage (JSON) */
    public function fromPage(Request $r, string $id): Response
    {
        $this->auth($r, 'system.manage');
        $p = Pages::find((int) $id) ?? throw new HttpException(404);
        $tpl = PageTemplates::create((string) $p['title'], (int) $p['id']);
        return Response::json(['ok' => true, 'id' => $tpl, 'url' => url('/admin/seitenvorlagen')]);
    }
}
