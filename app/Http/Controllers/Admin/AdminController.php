<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Csrf;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\PageCache;
use Core\Theme;

/** Basis für alle Admin-Controller: Login-Pflicht, Rollen, CSRF, Views. */
abstract class AdminController
{
    /**
     * Anmeldung prüfen und optional ein Recht (z. B. 'pages.publish'); true = nur Administration.
     * Bei Datentabellen kann zusätzlich die Tabelle geprüft werden.
     */
    protected function auth(Request $r, bool|string $perm = false, ?string $table = null): array
    {
        $user = app()->auth->user();
        if (!$user) {
            throw new RedirectException(url('/admin/login') . '?next=' . rawurlencode($r->path));
        }
        // Zweiter Faktor ist für die Rolle verlangt, aber noch nicht eingerichtet → erst einrichten; während einer
        // Übergangsfrist einmal je Anmeldung daran erinnern (Core\Mfa, Core\Totp, Core\Passkeys)
        if (($need = app()->session->get('2fa_setup')) && !in_array($r->path, \Core\Mfa::SETUP_PATHS, true)) {
            if (\Core\Mfa::satisfied($user, is_array($need) ? ($need['req'] ?? null) : null)) {
                app()->session->forget('2fa_setup');
            } elseif (\Core\Mfa::due($need)) {
                throw new RedirectException(url(\Core\Mfa::setupPath($user)));
            } elseif (!$r->isPost() && !app()->session->get('2fa_nagged')) {
                app()->session->set('2fa_nagged', 1);
                throw new RedirectException(url('/admin/account/2fa/choose') . '?next=' . rawurlencode($r->path));
            }
        }
        if ($perm === true && !in_array($user['role'], ['admin', 'network'], true)) {
            throw new HttpException(403, __('Keine Berechtigung.'));
        }
        if (is_string($perm) && !app()->auth->can($perm, $table)) {
            throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        }
        if ($r->isPost() && !Csrf::valid($r)) {
            throw new HttpException(419, 'Sitzung abgelaufen. Bitte Seite neu laden.');
        }
        // Aufbewahrung der Anfragen (Eingangs-Tabellen): gelegentlich nebenbei, ≈ 1 % der Verwaltungsaufrufe
        \Core\Data\Inbox::maybePurge();
        // Externe Quellen: fällige Abrufe nach der Antwort erledigen (billige Prüfung; zuverlässiger per Cron sources:sync)
        \Core\Sources\Sources::maybeRun();
        // Erweiterungen: fällige Arbeiten nach der Antwort (z. B. Hintergrund-Aufträge; billige Prüfung, siehe Extension::afterAdminResponse)
        \Core\Extensions::afterAdminResponse();
        return $user;
    }

    protected function view(string $view, array $vars = [], int $status = 200): Response
    {
        $vars['user'] ??= app()->auth->user();
        $vars['flash'] = app()->session->takeFlash();
        $vars['css'] ??= [];
        $content = Theme::capture(ROOT . '/app/Admin/views/' . $view . '.php', $vars);
        // Seitentitel = erste H1 der View
        if (!isset($vars['title']) && preg_match('~<h1[^>]*>(.*?)</h1>~s', $content, $m)) {
            $vars['title'] = trim(preg_replace('~\s+~', ' ', html_entity_decode(strip_tags(str_replace('<br>', ' ', $m[1])))));
        }
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => $view]);
        return self::secure(new Response($html, $status));
    }

    public static function secure(Response $r): Response
    {
        return $r->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Content-Security-Policy', "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; worker-src 'self' blob:; frame-ancestors 'self'; form-action 'self'; base-uri 'self'; object-src 'none'");
    }

    protected function back(string $to, string $type = 'success', string $msg = ''): Response
    {
        if ($msg !== '') {
            app()->session->flash($type, $msg);
        }
        return Response::redirect(url($to));
    }

    protected function changed(): void
    {
        PageCache::clear();
    }
}
