<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Data\DataForms;
use Core\Data\Tables;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Seo;
use Core\SpamGuard;

/**
 * Öffentliche Formulare für Datentabellen (Funktion „forms.data“, Block „data_form“) und Eingangs-Tabellen (Funktion „requests“, verschlüsselt).
 * Keine Session, keine Cookies – Schutz über SpamGuard (signiertes Token statt CSRF-Session), wie FormController.
 *   GET  /formular/{table}/challenge   Token + Proof-of-Work-Aufgabe (JSON, beim ersten Fokus im Formular)
 *   GET  /formular/{table}             eigenständige Formularseite (Rückfall ohne JavaScript, nicht zwischengespeichert)
 *   POST /formular/{table}             Einsendung (JSON bei Accept: application/json, sonst Formularseite mit Meldungen)
 */
final class DataFormController
{
    private function table(string $table): array
    {
        $t = Tables::find($table);
        if (!$t || !ctype_alpha($table[0] ?? '') || !DataForms::enabled($t)) {
            throw new HttpException(404);
        }
        return $t;
    }

    public function challenge(Request $r, string $table): Response
    {
        $t = $this->table($table);
        return Response::json(SpamGuard::challenge(DataForms::key($t)))->header('Cache-Control', 'no-store');
    }

    public function show(Request $r, string $table, int $status = 200): Response
    {
        $t = $this->table($table);
        (new FormController())->useLang($r);
        $state = DataForms::$state[$t['handle']] ?? [];
        DataForms::$state[$t['handle']] = $state + ['challenge' => SpamGuard::challenge(DataForms::key($t))];
        $theme = app()->theme;
        $page = ['id' => 0, 'title' => $t['name'], 'slug' => 'formular/' . $t['handle'], 'is_home' => 0,
            'meta_description' => '', 'noindex' => 1, 'status' => 'published'];
        $inbox = \Core\Data\Inbox::is($t);
        $title = $inbox ? \Core\Data\Inbox::text($t, 'title') : $t['name'];
        $page['title'] = $title;
        app()->currentPage = $page;
        $block = $theme->makeBlock(['id' => 'formular', 'type' => 'data_form', 'data' => ['table' => $t['handle'], 'title' => $title]
            + ($inbox ? ['intro' => \Core\Data\Inbox::text($t, 'intro')] : [])]);
        $content = $block ? $theme->renderBlock($block) : '';
        $seo = Seo::forError(200);
        $seo['title'] = $title;
        $html = $theme->render('layout', ['page' => $page, 'content' => $content, 'seo' => $seo, 'editor' => null, 'toolbar' => null,
            'extraCss' => $theme->conditionalCss(['data_form'])]);
        return (new SiteController())->respond($html, false, $status)->header('Cache-Control', 'no-store');
    }

    public function submit(Request $r, string $table): Response
    {
        $t = $this->table($table);
        (new FormController())->useLang($r);
        // Bewerbung auf einer Stellenseite: Feld „Stelle“ setzt der Server aus `_job` (Core\Data\Jobs)
        $post = \Core\Data\Jobs::applyPost($t, $r->post);
        $result = DataForms::submit($t, $post, $r->files, $r->ip());
        if ($r->wantsJson()) {
            return Response::json(array_diff_key($result, ['id' => 1, 'stored' => 1]), $result['ok'] ? 200 : 422)->header('Cache-Control', 'no-store');
        }
        $values = array_filter($post, fn($k) => !str_starts_with((string) $k, '_') || $k === DataForms::PRIVACY, ARRAY_FILTER_USE_KEY);
        DataForms::$state[$t['handle']] = [
            'values' => $result['ok'] ? [] : $values, 'errors' => $result['errors'] ?? [],
            'message' => $result['ok'] ? null : ($result['message'] ?? null), 'sent' => $result['ok'],
        ];
        return $this->show($r, $table, $result['ok'] ? 200 : 422);
    }
}
