<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Data\DataForms;
use Core\Forms;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Seo;
use Core\SpamGuard;

/**
 * Theme-Formulare unter stabilen Adressen: /anfrage/{form} (z. B. /anfrage/rezept) und /api/form/{form} (Token).
 * Dahinter steht die Eingangs-Tabelle des Formulars (Core\Data\Inbox) – Prüfung, Spamschutz und Verschlüsselung
 * wie beim Block „Formular (Datentabelle)“ (DataForms). Keine Session, keine Cookies.
 */
final class FormController
{
    /** Sprache der Formularseite: ?lang=en, Feld _lang oder die Seite, von der aus gesendet wurde */
    private function lang(Request $r): void
    {
        $l = (string) ($r->query['lang'] ?? $r->post['_lang'] ?? '');
        if ($l === '' && ($ref = (string) ($r->server['HTTP_REFERER'] ?? '')) !== '') {
            $path = (string) parse_url($ref, PHP_URL_PATH);
            $base = base_path();
            if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
            if (preg_match('~^/([a-z]{2}(?:-[a-z]{2})?)(?:/|$)~', $path, $m)) $l = $m[1];
        }
        if (\Core\Lang::valid($l) && $l !== \Core\Lang::default()) {
            app()->lang = $l;
        }
    }

    /** Sprache setzen – auch für die Formulare der Datentabellen (DataFormController) */
    public function useLang(Request $r): void
    {
        $this->lang($r);
    }

    private function guard(string $form): array
    {
        $def = Forms::def($form);
        if (!$def || !Forms::enabled($form)) {
            throw new HttpException(404);
        }
        return $def;
    }

    /** Liefert Token + Proof-of-Work-Aufgabe (wird beim Öffnen des Formulars geholt) */
    public function challenge(Request $r, string $form): Response
    {
        $this->guard($form);
        $t = Forms::table($form) ?? throw new HttpException(404);
        return Response::json(SpamGuard::challenge(DataForms::key($t)))->header('Cache-Control', 'no-store');
    }

    /** Eigenständige Formularseite (auch Fallback ohne JavaScript) */
    public function show(Request $r, string $form, array $values = [], array $errors = [], ?string $message = null, bool $sent = false, int $status = 200): Response
    {
        $this->lang($r);
        $def = $this->guard($form);
        if (Forms::mode($form) === 'external' && ($ext = Forms::externalUrl($form))) {
            return Response::redirect($ext, 302);
        }
        $t = Forms::table($form) ?? throw new HttpException(404);
        $theme = app()->theme;
        // Theme ohne eigene Formularseite: neutrale Seite mit dem Block „Formular (Datentabelle)“
        if (!is_file($theme->path . '/templates/form-page.php')) {
            DataForms::$state[$t['handle']] = ['values' => $values, 'errors' => $errors, 'message' => $sent ? null : $message, 'sent' => $sent];
            return (new DataFormController())->show($r, $t['handle'], $status);
        }
        $page = ['id' => 0, 'title' => (string) $def['label'], 'slug' => 'anfrage/' . $form, 'is_home' => 0,
            'meta_description' => '', 'noindex' => 1, 'status' => 'published'];
        app()->currentPage = $page;
        $content = $theme->render('form-page', [
            'form' => $form, 'def' => $def, 'table' => $t, 'values' => $values, 'errors' => $errors,
            'message' => $message, 'sent' => $sent, 'challenge' => SpamGuard::challenge(DataForms::key($t)),
        ]);
        $seo = Seo::forError(200);
        $seo['title'] = (string) $def['label'];
        $html = $theme->render('layout', ['page' => $page, 'content' => $content, 'seo' => $seo, 'editor' => null, 'toolbar' => null,
            'extraCss' => $theme->conditionalCss(['form'])]);   // nur die Formular-Styles (theme.php → conditional_css 'form')
        return (new SiteController())->respond($html, false, $status)->header('Cache-Control', 'no-store');
    }

    public function submit(Request $r, string $form): Response
    {
        $this->lang($r);
        $this->guard($form);
        $result = Forms::submit($form, $r->post, $r->ip(), $r->files);

        if ($r->wantsJson()) {
            return Response::json($result, $result['ok'] ? 200 : 422)->header('Cache-Control', 'no-store');
        }
        $values = array_filter($r->post, fn($k) => !str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
        return $this->show($r, $form, $result['ok'] ? [] : $values, $result['errors'] ?? [], $result['message'] ?? null,
            $result['ok'], $result['ok'] ? 200 : 422);
    }
}
