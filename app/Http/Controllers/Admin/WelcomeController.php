<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Csrf;
use Core\Http\Request;
use Core\Http\Response;
use Core\Network\Network;
use Core\Onboarding;

/**
 * Willkommen-Bildschirm beim Erststart einer Website (Core\Onboarding): erklärt die nächsten Schritte und lässt die
 * Administration Kit und Startinhalte wählen – für eine einzelne Installation genauso wie für jede Website eines Netzwerks.
 * Nach der Wahl spielt die nächste Anfrage die Inhalte ein; danach führt die Übersicht mit ihrer Checkliste weiter.
 */
final class WelcomeController extends AdminController
{
    public function index(Request $r, array $errors = []): Response
    {
        $user = $this->auth($r);
        if (!Onboarding::pending()) return Response::redirect(url('/admin'));
        [$kit, $content] = Onboarding::preset();
        if ($errors) { $kit = $r->str('kit'); $content = $r->str('content'); }
        $kits = Onboarding::kits();
        if (!$kit && count($kits) === 1) $kit = (string) array_key_first($kits);
        return $this->view('welcome', [
            'user' => null, 'me' => $user, 'css' => ['css/welcome.css'], 'kits' => $kits, 'kit' => $kit, 'seed' => $content, 'errors' => $errors,
            'canSetup' => can('system.manage'), 'network' => Network::isNetworkUser(), 'siteKey' => site()->key,
        ], $errors ? 422 : 200);
    }

    public function save(Request $r): Response
    {
        $this->auth($r);
        if (!Onboarding::pending()) return Response::redirect(url('/admin'));
        if (!can('system.manage')) return $this->index($r, ['form' => __('Kit und Startinhalte wählt die Administration.')]);
        if (!Csrf::valid($r)) return $this->index($r, ['form' => __('Sitzung abgelaufen – bitte erneut versuchen.')]);
        try {
            Onboarding::choose($r->str('kit'), $r->str('content'));
        } catch (\InvalidArgumentException $e) {
            return $this->index($r, [$r->str('kit') === '' || !isset(Onboarding::kits()[$r->str('kit')]) ? 'kit' : 'content' => $e->getMessage()]);
        }
        $label = Onboarding::kits()[$r->str('kit')]['label'];
        app()->session->flash('success', $r->str('content') === 'full'
            ? __('Willkommen! Die Website nutzt das Kit „{kit}“ mit seinen Startinhalten. Die Checkliste unten zeigt die nächsten Schritte.', ['kit' => $label])
            : __('Willkommen! Die Website nutzt das Kit „{kit}“ – mit leerer Startseite sowie Impressum und Datenschutz als Vorlagen. Die Checkliste unten zeigt die nächsten Schritte.', ['kit' => $label]));
        // Die nächste Anfrage spielt die Inhalte ein (App::boot) – mit dem gewählten Kit
        return Response::redirect(url('/admin'));
    }
}
