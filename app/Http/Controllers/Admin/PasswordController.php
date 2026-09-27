<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Auth;
use Core\Csrf;
use Core\PasswordReset;
use Core\RateLimiter;
use Core\Http\Request;
use Core\Http\Response;

/**
 * „Passwort vergessen“ (Core\PasswordReset): Anfrage-Seite /admin/passwort-vergessen (Link auf der Anmeldeseite) und Seite zum
 * Link aus der E-Mail /admin/passwort/{token} – GET zeigt das Formular (ändert nichts), POST setzt das neue Passwort.
 *
 * Sicherheit: CSRF; immer dieselbe Antwort auf eine Anfrage (keine Rückschlüsse auf Konten); Begrenzung je Anschluss und je
 * Adresse; Link-Seite mit Begrenzung je Anschluss, kein Referrer, ungültige Links ohne Unterscheidung; danach keine
 * Anmeldung – der zweite Faktor bleibt Pflicht.
 */
final class PasswordController extends AdminController
{
    private const TRIES = 10;      // Fehlversuche je Anschluss …
    private const WINDOW = 900;    // … in 15 Minuten

    // ================================================================= Anfrage

    public function forgotForm(Request $r, array $vars = [], int $status = 200): Response
    {
        return $this->page('password/forgot', $vars + ['state' => 'ask', 'email' => $r->str('email'), 'error' => null], $status);
    }

    public function forgot(Request $r): Response
    {
        $email = strtolower(trim($r->str('email')));
        if (!Csrf::valid($r)) return $this->forgotForm($r, ['error' => __('Sitzung abgelaufen – bitte erneut versuchen.')], 419);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
            return $this->forgotForm($r, ['error' => __('Bitte geben Sie eine gültige E-Mail-Adresse ein.')], 422);
        }
        $res = PasswordReset::request($email, $r->ip());
        // Nur die Begrenzung je Anschluss ist sichtbar – sie hängt nicht an der Adresse
        if ($res === 'limited-ip') {
            return $this->forgotForm($r, ['error' => __('Zu viele Anfragen von diesem Anschluss. Bitte warten Sie 15 Minuten.')], 429);
        }
        return $this->page('password/forgot', ['state' => 'sent', 'email' => $email, 'error' => null, 'minutes' => PasswordReset::minutes()], 200);
    }

    // ================================================================= Link aus der E-Mail

    public function resetForm(Request $r, string $token, array $errors = []): Response
    {
        $limiter = new RateLimiter(app()->db);
        $key = $this->limitKey($r);
        if ($limiter->tooMany($key, self::TRIES * 2, self::WINDOW)) return $this->page('password/reset', ['state' => 'limited'], 429);
        $row = PasswordReset::find($token);
        if (!$row) {
            $limiter->hit($key);
            return $this->page('password/reset', ['state' => 'invalid'], 410);
        }
        return $this->page('password/reset', ['state' => 'ask', 'token' => $token, 'email' => (string) $row['email'],
            'expires' => (int) $row['expires_at'], 'errors' => $errors], $errors ? 422 : 200);
    }

    public function reset(Request $r, string $token): Response
    {
        $limiter = new RateLimiter(app()->db);
        $key = $this->limitKey($r);
        if ($limiter->tooMany($key, self::TRIES, self::WINDOW)) return $this->page('password/reset', ['state' => 'limited'], 429);
        if (!Csrf::valid($r)) return $this->resetForm($r, $token, ['form' => __('Sitzung abgelaufen – bitte erneut versuchen.')]);
        $row = PasswordReset::find($token);
        if (!$row) {
            $limiter->hit($key);
            return $this->page('password/reset', ['state' => 'invalid'], 410);
        }
        $pw = (string) ($r->post['password'] ?? '');
        $errors = [];
        if ($p = Auth::passwordProblem($pw)) $errors['password'] = __($p);
        elseif ($pw !== (string) ($r->post['password2'] ?? '')) $errors['password2'] = __('Die Passwörter stimmen nicht überein.');
        if ($errors) return $this->resetForm($r, $token, $errors);
        $user = PasswordReset::reset($row, $pw);
        if (!$user) {
            $limiter->hit($key);
            return $this->page('password/reset', ['state' => 'invalid'], 410);
        }
        return $this->page('password/reset', ['state' => 'done', 'email' => (string) $user['email']], 200);
    }

    // ================================================================= Hilfen

    private function limitKey(Request $r): string
    {
        return 'pwreset-link:' . hash_hmac('sha256', $r->ip(), app()->key());
    }

    /** Öffentliche Seite im Stil der Anmeldung: kein Referrer (Token in der Adresse), nicht zwischenspeichern */
    private function page(string $view, array $vars, int $status): Response
    {
        return $this->view($view, $vars + ['user' => null, 'css' => ['css/invite.css', 'css/account.css']], $status)
            ->header('Referrer-Policy', 'no-referrer');
    }
}
