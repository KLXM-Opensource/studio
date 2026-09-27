<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Csrf;
use Core\EmailChange;
use Core\Invites;
use Core\Mfa;
use Core\RateLimiter;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Konto → Anmeldedaten (Core\EmailChange): Name, E-Mail-Adresse ändern (mit Bestätigung), offene Änderung erneut senden
 * oder abbrechen; öffentliche Seiten zu den Links aus den E-Mails (/admin/konto/email/{token}, /admin/konto/email-abbrechen/{token}).
 * Passwort ändern: UserController::saveAccount.
 *
 * Sicherheit: CSRF auf allen Formularen; Identität bestätigen mit dem aktuellen Passwort bzw. (Konten ohne Passwort) einer
 * frischen Anmeldung oder Passkey-Bestätigung (Mfa::recentAuth, PasskeyController::reauthPasskey); höchstens 5 Anfragen je
 * Stunde und Konto; Link-Seiten: Begrenzung je Anschluss, kein Referrer, keine Unterscheidung ungültiger Links, Änderung erst
 * per POST (Vorschau-Programme in E-Mail-Postfächern lösen nichts aus).
 */
final class AccountController extends AdminController
{
    private const TRIES = 10;      // Fehlversuche je Anschluss …
    private const WINDOW = 900;    // … in 15 Minuten

    // ================================================================= Konto (angemeldet)

    /** Name des eigenen Kontos */
    public function saveProfile(Request $r): Response
    {
        $user = $this->auth($r);
        if (!empty($user['network_uid'])) return $this->back('/admin/account', 'error', __('Ihre Anmeldedaten verwalten Sie in der Netzwerk-Verwaltung.'));
        $name = Invites::clean($r->str('name'), Invites::NAME_MAX);
        app()->db->update('users', ['name' => $name], 'id = :id', ['id' => (int) $user['id']]);
        return $this->back('/admin/account#profil', 'success', __('Name gespeichert.'));
    }

    /** Neue E-Mail-Adresse anfragen → Bestätigungslink an die neue, Hinweis an die bisherige Adresse */
    public function requestEmail(Request $r): Response
    {
        $user = $this->auth($r);
        if (!empty($user['network_uid'])) return $this->back('/admin/account', 'error', __('Ihre Anmeldedaten verwalten Sie in der Netzwerk-Verwaltung.'));
        $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
        $new = strtolower(trim($r->str('email')));
        $old = ['email_new' => $new];
        $limiter = new RateLimiter(app()->db);
        $key = 'email-change:' . (int) $user['id'];
        if ($limiter->tooMany($key, EmailChange::PER_HOUR, 3600)) {
            return $this->form($r, ['email_new' => __('Zu viele Änderungsanfragen. Bitte warten Sie eine Stunde.')], $old);
        }
        if ($err = EmailChange::invalid($new, (string) $row['email'])) return $this->form($r, ['email_new' => $err], $old);
        if ($err = self::confirmIdentity($r, $row)) return $this->form($r, ['email_current' => $err], $old);
        $limiter->hit($key);
        $sent = EmailChange::request($row, $new);
        return $this->afterSend($sent, $new, __('Fast geschafft: Wir haben einen Bestätigungslink an {email} gesendet. Die neue Adresse gilt, sobald Sie den Link öffnen und bestätigen (24 Stunden gültig).', ['email' => $new]));
    }

    /** Offene Änderung: neuen Bestätigungslink senden */
    public function resendEmail(Request $r): Response
    {
        $user = $this->auth($r);
        $limiter = new RateLimiter(app()->db);
        $key = 'email-change:' . (int) $user['id'];
        if ($limiter->tooMany($key, EmailChange::PER_HOUR, 3600)) {
            return $this->back('/admin/account#anmeldedaten', 'error', __('Zu viele Änderungsanfragen. Bitte warten Sie eine Stunde.'));
        }
        $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [(int) $user['id']]);
        $p = EmailChange::pending((int) $user['id']);
        $sent = $p ? EmailChange::resend($row) : null;
        if ($sent === null) return $this->back('/admin/account#anmeldedaten', 'error', __('Es gibt keine offene Änderung.'));
        $limiter->hit($key);
        return $this->afterSend($sent, (string) $p['new_email'], __('Neuer Bestätigungslink an {email} gesendet – der vorige gilt nicht mehr.', ['email' => $p['new_email']]));
    }

    /** Offene Änderung abbrechen (angemeldet) */
    public function cancelEmail(Request $r): Response
    {
        $user = $this->auth($r);
        $ok = EmailChange::cancelOwn(['id' => (int) $user['id'], 'email' => (string) $user['email']]);
        return $this->back('/admin/account#anmeldedaten', $ok ? 'success' : 'error',
            $ok ? __('Änderung abgebrochen – Ihre E-Mail-Adresse bleibt unverändert, die Links gelten nicht mehr.') : __('Es gibt keine offene Änderung.'));
    }

    /**
     * Identität bestätigen: aktuelles Passwort (5 Fehlversuche in 15 Minuten, wie „Passwort bestätigen“) – Konten ohne Passwort
     * (nur Passkey): Anmeldung oder Passkey-Bestätigung vor höchstens 15 Minuten. @return string|null Fehlertext
     */
    public static function confirmIdentity(Request $r, array $row): ?string
    {
        if (!EmailChange::hasPassword($row)) {
            return Mfa::recentAuth() ? null : __('Bitte bestätigen Sie zuerst mit Ihrem Passkey, dass Sie es sind.');
        }
        $limiter = new RateLimiter(app()->db);
        $key = 'reauth:' . (int) $row['id'] . ':l';
        if ($limiter->tooMany($key, 5, 900)) return __('Zu viele Versuche. Bitte warten Sie 15 Minuten.');
        if (!password_verify((string) ($r->post['current'] ?? ''), (string) $row['password_hash'])) {
            $limiter->hit($key);
            return __('Das aktuelle Passwort ist falsch.');
        }
        $limiter->clear($key);
        return null;
    }

    /** Nach dem Versand: Erfolg – oder deutlicher Hinweis, dass die E-Mail nicht zugestellt wurde (ohne Bestätigung keine Änderung) */
    private function afterSend(array $sent, string $email, string $ok): Response
    {
        if ($sent['delivered']) return $this->back('/admin/account#anmeldedaten', 'success', $ok);
        $why = $sent['error']
            ? __('Die E-Mail an {email} konnte nicht gesendet werden: {error}', ['email' => $email, 'error' => $sent['error']])
            : __('Die E-Mail an {email} wurde nicht zugestellt (Testumgebung oder Versand deaktiviert – nur protokolliert, umgeleitet oder als Datei abgelegt).', ['email' => $email]);
        return $this->back('/admin/account#anmeldedaten', 'info', $why . ' ' . __('Ihre Adresse bleibt unverändert, bis der Link bestätigt ist. Bitten Sie die Administration, den E-Mail-Versand einzurichten oder Ihre Adresse unter „Benutzer & Rollen“ zu ändern.'));
    }

    private function form(Request $r, array $errors, array $old): Response
    {
        return (new UserController())->account($r, $errors, $old);
    }

    // ================================================================= Öffentlich: Links aus den E-Mails

    /** Neue Adresse bestätigen – Seite mit Schaltfläche (GET ändert nichts) */
    public function confirmPage(Request $r, string $token): Response
    {
        return $this->show($r, 'confirm', $token);
    }

    public function confirm(Request $r, string $token): Response
    {
        if ($stop = $this->guard($r, 'confirm', $token)) return $stop;
        $row = EmailChange::find('confirm', $token);
        $user = $row ? EmailChange::confirm($row) : null;
        if (!$user) return $this->invalid($r);
        $mine = (int) (app()->auth->user()['id'] ?? 0) === (int) $user['id'];
        return $this->page('account/email-link', ['mode' => 'confirm', 'state' => 'done', 'email' => (string) $user['email'], 'mine' => $mine], 200);
    }

    /** „Das war ich nicht“ – Seite mit Schaltfläche (GET ändert nichts) */
    public function cancelPage(Request $r, string $token): Response
    {
        return $this->show($r, 'cancel', $token);
    }

    public function cancel(Request $r, string $token): Response
    {
        if ($stop = $this->guard($r, 'cancel', $token)) return $stop;
        $row = EmailChange::find('cancel', $token);
        if (!$row || !EmailChange::cancelByToken($row)) return $this->invalid($r);
        // Alle Sitzungen des Kontos sind beendet – auch eine hier angemeldete
        if ((int) (app()->auth->user()['id'] ?? 0) === (int) $row['user_id']) app()->auth->logout();
        return $this->page('account/email-link', ['mode' => 'cancel', 'state' => 'done', 'email' => ''], 200);
    }

    private function show(Request $r, string $mode, string $token): Response
    {
        $limiter = new RateLimiter(app()->db);
        $key = $this->limitKey($r);
        if ($limiter->tooMany($key, self::TRIES * 2, self::WINDOW)) return $this->page('account/email-link', ['mode' => $mode, 'state' => 'limited'], 429);
        $row = EmailChange::find($mode, $token);
        if (!$row) {
            $limiter->hit($key);
            return $this->page('account/email-link', ['mode' => $mode, 'state' => 'invalid'], 410);
        }
        return $this->page('account/email-link', ['mode' => $mode, 'state' => 'ask', 'token' => $token,
            'email' => (string) $row['new_email'], 'expires' => (int) $row['expires_at']], 200);
    }

    /** POST auf einer Link-Seite: Begrenzung und CSRF → Fehlerseite oder null */
    private function guard(Request $r, string $mode, string $token): ?Response
    {
        $limiter = new RateLimiter(app()->db);
        $key = $this->limitKey($r);
        if ($limiter->tooMany($key, self::TRIES, self::WINDOW)) return $this->page('account/email-link', ['mode' => $mode, 'state' => 'limited'], 429);
        if (!Csrf::valid($r)) return $this->page('account/email-link', ['mode' => $mode, 'state' => 'ask', 'token' => $token, 'email' => '',
            'expires' => 0, 'error' => __('Sitzung abgelaufen – bitte erneut versuchen.')], 419);
        return null;
    }

    private function invalid(Request $r): Response
    {
        (new RateLimiter(app()->db))->hit($this->limitKey($r));
        return $this->page('account/email-link', ['mode' => 'confirm', 'state' => 'invalid'], 410);
    }

    private function limitKey(Request $r): string
    {
        return 'email-link:' . hash_hmac('sha256', $r->ip(), app()->key());
    }

    /** Öffentliche Seite im Stil der Anmeldung: kein Referrer (Token in der Adresse), nicht zwischenspeichern */
    private function page(string $view, array $vars, int $status): Response
    {
        return $this->view($view, $vars + ['user' => null, 'css' => ['css/invite.css', 'css/account.css']], $status)
            ->header('Referrer-Policy', 'no-referrer');
    }
}
