<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Auth;
use Core\Csrf;
use Core\Invites;
use Core\Mfa;
use Core\Passkeys;
use Core\Permissions;
use Core\RateLimiter;
use Core\Totp;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Einladungen (Core\Invites): „Person einladen“ unter Benutzer & Rollen (Recht users.manage) und die öffentliche Seite
 * /admin/einladung/{token} zum Annehmen – Passkey (resources/js/invite.js, Core\Passkeys) und/oder Passwort.
 *
 * Sicherheit: CSRF auf allen Formularen und JSON-Endpunkten; Begrenzung je Anschluss; ungültige, abgelaufene, benutzte und
 * unbekannte Tokens sehen gleich aus (keine Rückschlüsse auf Adressen); Referrer-Policy no-referrer; Anmeldung erst,
 * wenn Passwort bzw. Passkey gespeichert sind.
 */
final class InviteController extends AdminController
{
    private const TRIES = 10;      // Fehlversuche je Anschluss …
    private const WINDOW = 900;    // … in 15 Minuten

    // ================================================================= Verwaltung (Benutzer & Rollen)

    public function store(Request $r): Response
    {
        $me = $this->auth($r, 'users.manage');
        $limiter = new RateLimiter(app()->db);
        $key = 'invite-send:' . (int) $me['id'];
        $old = ['email' => $r->str('email'), 'name' => $r->str('name'), 'role' => $r->str('role'), 'message' => (string) ($r->post['message'] ?? ''), 'locale' => $r->str('locale')];
        if ($limiter->tooMany($key, 30, 3600)) {
            return (new UserController())->index($r, ['invite' => __('Zu viele Einladungen in kurzer Zeit. Bitte warten Sie eine Stunde.')], $old + ['_invite' => 1], 'einladen');
        }
        $res = Invites::create($old, $me, app()->auth->role());
        if (!isset($res['token'])) {
            return (new UserController())->index($r, array_combine(array_map(fn($k) => 'inv_' . $k, array_keys($res)), $res), $old + ['_invite' => 1], 'einladen');
        }
        $limiter->hit($key);
        $inv = Invites::get((int) $res['id']);
        $sent = Invites::send($inv, $res['token']);
        Invites::log('user.invite', (string) $me['email'], $inv['email'] . ' · ' . $inv['role'] . ($sent['delivered'] ? '' : ' · ohne E-Mail'));
        return $this->after($inv, $res['token'], $sent, __('Einladung an {email} gesendet.', ['email' => $inv['email']]));
    }

    public function resend(Request $r, string $id): Response
    {
        $me = $this->auth($r, 'users.manage');
        $inv = Invites::get((int) $id);
        // Nur in Rollen, in die man selbst einladen darf
        if (!$inv || !Invites::assignable(Permissions::role((string) $inv['role']), app()->auth->role())) {
            return $this->back('/admin/users/einladen#einladungen', 'error', __('Einladung nicht gefunden.'));
        }
        $limiter = new RateLimiter(app()->db);
        $key = 'invite-send:' . (int) $me['id'];
        if ($limiter->tooMany($key, 30, 3600)) return $this->back('/admin/users/einladen#einladungen', 'error', __('Zu viele Einladungen in kurzer Zeit. Bitte warten Sie eine Stunde.'));
        if (app()->db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ?', [strtolower((string) $inv['email'])])) {
            return $this->back('/admin/users/einladen#einladungen', 'error', __('Diese E-Mail-Adresse ist bereits registriert.'));
        }
        $token = Invites::renew((int) $id);
        if ($token === null) return $this->back('/admin/users/einladen#einladungen', 'error', __('Einladung nicht gefunden.'));
        $limiter->hit($key);
        $inv = Invites::get((int) $id);
        $sent = Invites::send($inv, $token);
        Invites::log('user.invite-resend', (string) $me['email'], $inv['email'] . ($sent['delivered'] ? '' : ' · ohne E-Mail'));
        return $this->after($inv, $token, $sent, __('Einladung an {email} erneut gesendet – der vorige Link gilt nicht mehr.', ['email' => $inv['email']]));
    }

    public function revoke(Request $r, string $id): Response
    {
        $me = $this->auth($r, 'users.manage');
        $inv = Invites::get((int) $id);
        if (!$inv || !Invites::assignable(Permissions::role((string) $inv['role']), app()->auth->role()) || !Invites::revoke((int) $id)) {
            return $this->back('/admin/users/einladen#einladungen', 'error', __('Einladung nicht gefunden.'));
        }
        Invites::log('user.invite-revoke', (string) $me['email'], (string) $inv['email']);
        return $this->back('/admin/users/einladen#einladungen', 'success', __('Einladung an {email} zurückgezogen – der Link gilt nicht mehr.', ['email' => $inv['email']]));
    }

    /** Nach dem Versand: Erfolg – oder den Link einmal anzeigen (E-Mail nicht zugestellt) */
    private function after(array $inv, string $token, array $sent, string $ok): Response
    {
        if ($sent['delivered']) return $this->back('/admin/users/einladen#einladungen', 'success', $ok);
        app()->session->set('_invite_link', ['email' => (string) $inv['email'], 'url' => Invites::url($token), 'error' => $sent['error']]);
        return $this->back('/admin/users/einladen#einladung-link');
    }

    // ================================================================= Öffentlich: Einladung annehmen

    public function show(Request $r, string $token, array $errors = [], array $old = []): Response
    {
        $limiter = new RateLimiter(app()->db);
        $key = $this->limitKey($r);
        if ($limiter->tooMany($key, self::TRIES * 2, self::WINDOW)) return $this->page('invite/invalid', ['limited' => true], 429);
        $inv = Invites::find($token);
        if (!$inv) {
            $limiter->hit($key);
            return $this->page('invite/invalid', ['limited' => false], 410);
        }
        $this->locale($inv);
        $pol = Mfa::allowed(['role' => (string) $inv['role']]);   // Netzwerk-Administration: Richtlinie der Netzwerk-Konten
        $role = Permissions::role((string) $inv['role']);
        return $this->page('invite/accept', ['inv' => $inv, 'token' => $token, 'role' => $role, 'errors' => $errors, 'old' => $old,
            // Passkey anbieten: erlaubt und unter dieser Adresse möglich; ohne Passwort nur, wenn die Anmeldung ohne Passwort erlaubt ist
            'passkey' => $pol['passkey'] && Passkeys::available(), 'passwordless' => $pol['passwordless'],
            'twofa' => Mfa::requirement(['role' => (string) $inv['role']]), 'me' => app()->auth->user(), 'network' => Invites::isNetwork($inv)], $errors ? 422 : 200);
    }

    /** Annehmen mit Passwort (Formular ohne JavaScript bzw. „Mit Passwort fortfahren“) */
    public function accept(Request $r, string $token): Response
    {
        $limiter = new RateLimiter(app()->db);
        $key = $this->limitKey($r);
        if ($limiter->tooMany($key, self::TRIES, self::WINDOW)) return $this->page('invite/invalid', ['limited' => true], 429);
        $old = ['name' => $r->str('name')];
        if (!Csrf::valid($r)) return $this->show($r, $token, ['form' => __('Sitzung abgelaufen – bitte erneut versuchen.')], $old);
        if (app()->auth->check()) return $this->show($r, $token);   // angemeldet: erst abmelden (Hinweis auf der Seite)
        $inv = Invites::find($token);
        if (!$inv) {
            $limiter->hit($key);
            return $this->page('invite/invalid', ['limited' => false], 410);
        }
        $this->locale($inv);
        $errors = $this->validate($r, true, $inv);
        if ($errors) {
            $limiter->hit($key);
            return $this->show($r, $token, $errors, $old);
        }
        $name = Invites::clean($r->str('name'), Invites::NAME_MAX);
        $uid = Invites::accept($inv, $name, (string) $r->post['password'], 'password');
        if (is_string($uid)) {
            $limiter->hit($key);
            return $this->show($r, $token, ['form' => $uid], $old);
        }
        return $this->welcome($inv, $uid, $name, 'password');
    }

    /** Passkey: Optionen für navigator.credentials.create() – Konto-ID vorab (Benutzerkennung im Passkey), noch kein Konto */
    public function passkeyOptions(Request $r, string $token): Response
    {
        [$inv, $fail] = $this->jsonGuard($r, $token);
        if ($fail) return $fail;
        if ($errors = $this->validate($r, false, $inv)) return Response::json(['error' => reset($errors), 'field' => key($errors)], 422);
        $uid = Invites::nextUserId(app()->db);
        $name = Invites::clean($r->str('name'), Invites::NAME_MAX);
        $handle = Mfa::handle(['id' => $uid, 'role' => (string) $inv['role']]);
        return Response::json(['publicKey' => Passkeys::creationOptions($handle, (string) $inv['email'], $name ?: (string) $inv['email'], [],
            ['invite' => (int) $inv['id'], 'uid' => $uid])]);
    }

    /** Passkey speichern → Konto anlegen (mit optionalem Passwort) → anmelden */
    public function passkeyStore(Request $r, string $token): Response
    {
        [$inv, $fail] = $this->jsonGuard($r, $token);
        if ($fail) return $fail;
        $limiter = new RateLimiter(app()->db);
        $key = $this->limitKey($r);
        if ($errors = $this->validate($r, false, $inv)) return Response::json(['error' => reset($errors), 'field' => key($errors)], 422);
        $p = Passkeys::pending();
        if (!$p || (int) ($p['invite'] ?? 0) !== (int) $inv['id'] || empty($p['uid'])) {
            $limiter->hit($key);
            return Response::json(['error' => __('Die Anfrage ist abgelaufen. Bitte erneut versuchen.')], 422);
        }
        $uid = (int) $p['uid'];
        $name = Invites::clean($r->str('name'), Invites::NAME_MAX);
        $pw = (string) ($r->post['password'] ?? '');
        $cred = (array) ($r->post['credential'] ?? []);
        $res = Invites::accept($inv, $name, $pw !== '' ? $pw : null, $pw !== '' ? 'passkey+password' : 'passkey', $uid,
            function (int $id) use ($cred): ?string {
                $pk = Passkeys::register(app()->db, $id, $cred, '');
                return is_string($pk) ? $pk : null;
            });
        if (is_string($res)) {
            $limiter->hit($key);
            return Response::json(['error' => $res], 422);
        }
        Mfa::remember(app()->db, $res, 'passkey');
        $to = $this->welcome($inv, $res, $name, $pw !== '' ? 'passkey+password' : 'passkey', $pw !== '');
        return Response::json(['ok' => true, 'redirect' => $to->headers['Location'] ?? url('/admin')]);
    }

    // ================================================================= Hilfen

    /**
     * Neues Konto anmelden, protokollieren, begrüßen; mit Passkey und Passwort einmal die Wiederherstellungscodes zeigen.
     * Netzwerk-Administration: ohne Passkey direkt zur Pflicht-Einrichtung der Zwei-Faktor-Anmeldung, danach die Netzwerk-Übersicht.
     */
    private function welcome(array $inv, int $uid, string $name, string $method, bool $codes = false): Response
    {
        $network = Invites::isNetwork($inv);
        app()->auth->login($uid);
        Invites::log($network ? 'network.invite-accept' : 'user.invite-accept', (string) $inv['email'], $inv['role'] . ' · ' . $method);
        if ($network) {
            $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [$uid]);
            if ($row && !Mfa::satisfied($row)) {
                app()->session->flash('success', __('Willkommen! Ihr Netzwerk-Konto ist angelegt. Richten Sie jetzt die Zwei-Faktor-Anmeldung ein – ohne sie geht es nicht weiter.'));
                return Response::redirect(url(Mfa::setupPath($row)) . '?next=' . rawurlencode('/admin/network'));
            }
        }
        app()->session->flash('success', $name !== '' ? __('Willkommen, {name}! Ihr Konto ist eingerichtet.', ['name' => $name])
            : __('Willkommen! Ihr Konto ist eingerichtet.'));
        if ($codes) {
            [$list, $hashes] = Totp::recoveryCodes();
            app()->db->update('users', ['totp_recovery' => $hashes], 'id = :id', ['id' => $uid]);
            app()->session->set('_pk_codes', $list);
            return Response::redirect(url('/admin/account/2fa/codes'));
        }
        return Response::redirect(url($network ? '/admin/network' : '/admin'));
    }

    /**
     * Name und Passwort prüfen. Passwort: Pflicht ohne Passkey bzw. wenn die Anmeldung ohne Passwort nicht erlaubt ist;
     * sonst freiwillig (dann Richtlinie + Wiederholung). @return array<string,string> Fehler je Feld
     */
    private function validate(Request $r, bool $passwordOnly, array $inv): array
    {
        $errors = [];
        $name = $r->str('name');
        if ($name === '') $errors['name'] = __('Bitte geben Sie Ihren Namen an.');
        elseif (mb_strlen($name) > Invites::NAME_MAX) $errors['name'] = __('Der Name ist zu lang.');
        $pw = (string) ($r->post['password'] ?? '');
        $required = $passwordOnly || !Mfa::allowed(['role' => (string) $inv['role']])['passwordless'];
        if ($pw === '' && $required) {
            $errors['password'] = __('Bitte legen Sie ein Passwort fest.');
        } elseif ($pw !== '') {
            if ($p = Auth::passwordProblem($pw)) $errors['password'] = __($p);
            elseif ($pw !== (string) ($r->post['password2'] ?? '')) $errors['password2'] = __('Die Passwörter stimmen nicht überein.');
        }
        return $errors;
    }

    /** JSON-Endpunkte: CSRF, Begrenzung, gültige Einladung, Passkeys erlaubt → [Einladung, null] oder [null, Fehlerantwort] */
    private function jsonGuard(Request $r, string $token): array
    {
        $limiter = new RateLimiter(app()->db);
        $key = $this->limitKey($r);
        if (!Csrf::valid($r)) return [null, Response::json(['error' => __('Sitzung abgelaufen – bitte Seite neu laden.')], 419)];
        if ($limiter->tooMany($key, self::TRIES, self::WINDOW)) return [null, Response::json(['error' => __('Zu viele Versuche. Bitte warten Sie 15 Minuten.')], 429)];
        if (app()->auth->check()) return [null, Response::json(['error' => __('Bitte melden Sie sich zuerst ab.')], 409)];
        $inv = Invites::find($token);
        if (!$inv) {
            $limiter->hit($key);
            return [null, Response::json(['error' => __('Diese Einladung ist nicht mehr gültig.'), 'redirect' => url('/admin/einladung/' . $token)], 410)];
        }
        $this->locale($inv);
        if (!Mfa::allowed(['role' => (string) $inv['role']])['passkey'] || !Passkeys::available()) {
            return [null, Response::json(['error' => __('Passkeys sind auf dieser Website nicht freigegeben.')], 403)];
        }
        return [$inv, null];
    }

    /** Seite und Meldungen in der Sprache der Einladung (wie die E-Mail) */
    private function locale(array $inv): void
    {
        if ($inv['locale'] && $inv['locale'] !== \Core\I18n::locale() && array_key_exists((string) $inv['locale'], \Core\I18n::available())) {
            \Core\I18n::setLocale((string) $inv['locale']);
        }
    }

    private function limitKey(Request $r): string
    {
        return 'invite:' . hash_hmac('sha256', $r->ip(), app()->key());
    }

    /** Öffentliche Seite im Stil der Anmeldung: kein Referrer (Token in der Adresse), nicht zwischenspeichern */
    private function page(string $view, array $vars, int $status): Response
    {
        return $this->view($view, $vars + ['user' => null, 'css' => ['css/passkey.css', 'css/invite.css']], $status)
            ->header('Referrer-Policy', 'no-referrer');
    }
}
