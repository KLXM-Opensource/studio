<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Auth;
use Core\Csrf;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;

final class AuthController extends AdminController
{
    private function noUsers(): bool
    {
        return !app()->db->fetchValue('SELECT COUNT(*) FROM users');
    }

    public function loginForm(Request $r, string $error = ''): Response
    {
        if ($this->noUsers()) {
            return Response::redirect(url('/admin/setup'));
        }
        if (app()->auth->check()) {
            return Response::redirect(url('/admin'));
        }
        return $this->view('login', ['user' => null, 'error' => $error, 'email' => $r->str('email'), 'next' => $r->str('next'), 'css' => ['css/passkey.css']], $error ? 422 : 200);
    }

    public function login(Request $r): Response
    {
        if (!Csrf::valid($r)) {
            return $this->loginForm($r, 'Sitzung abgelaufen – bitte erneut versuchen.');
        }
        $err = app()->auth->attempt($r->str('email'), (string) ($r->post['password'] ?? ''), $r->ip());
        $next = $r->str('next');
        $safe = preg_match('~^/(?!/)[\w\-/]*$~', $next) ? $next : '/admin';
        // Passwort stimmt, zweiter Faktor fehlt noch (Core\Totp)
        if ($err === Auth::PENDING_2FA) {
            app()->session->set('_2fa_next', $safe);
            return Response::redirect(url('/admin/login/2fa'));
        }
        if ($err) {
            return $this->loginForm($r, $err);
        }
        // Netzwerk-Administration landet auf der Netzwerk-Website in der Übersicht aller Websites
        if ($safe === '/admin' && \Core\Network\Network::isNetworkSite() && \Core\Network\Network::isNetworkUser()) {
            $safe = \Core\Onboarding::pending() ? '/admin/willkommen' : '/admin/network';   // Erststart offen: zuerst Kit und Startinhalte
        }
        return Response::redirect(url($safe));
    }

    public function logout(Request $r): Response
    {
        if (Csrf::valid($r)) {
            app()->auth->logout();
        }
        return Response::redirect(url('/admin/login'));
    }

    public function setupForm(Request $r, array $errors = [], array $old = []): Response
    {
        if (!$this->noUsers()) {
            throw new HttpException(404);
        }
        // Auf der Netzwerk-Website, solange es noch kein Netzwerk-Konto gibt: Einzelinstallation oder Netzwerk wählen
        $canNetwork = \Core\Network\Network::isNetworkSite() && !\Core\Network\Network::accounts();
        return $this->view('setup', ['user' => null, 'errors' => $errors, 'old' => $old, 'canNetwork' => $canNetwork], $errors ? 422 : 200);
    }

    public function setup(Request $r): Response
    {
        if (!$this->noUsers()) {
            throw new HttpException(404);
        }
        $errors = [];
        $expected = (string) app()->config->get('setup_token');
        if (!Csrf::valid($r)) {
            $errors['form'] = 'Sitzung abgelaufen – bitte erneut versuchen.';
        }
        if ($expected === '' || !hash_equals($expected, $r->str('token'))) {
            $errors['token'] = 'Setup-Token ist falsch. Sie finden ihn in config/config.local.php (setup_token).';
        }
        $email = $r->str('email');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }
        $pw = (string) ($r->post['password'] ?? '');
        if ($p = Auth::passwordProblem($pw)) {
            $errors['password'] = $p;
        } elseif ($pw !== (string) ($r->post['password2'] ?? '')) {
            $errors['password2'] = 'Die Passwörter stimmen nicht überein.';
        }
        $network = $r->str('kind') === 'network' && \Core\Network\Network::isNetworkSite();
        if ($errors) {
            return $this->setupForm($r, $errors, ['email' => $email, 'name' => $r->str('name'), 'kind' => $r->str('kind')]);
        }
        if ($network) {
            // Netzwerk: erstes Konto der Netzwerk-Administration (Rolle network, Zwei-Faktor-Anmeldung bei der ersten Anmeldung)
            \Core\Network\Network::ensureKey();
            \Core\Network\Network::ensureSchema(app()->db);
            try {
                \Core\Network\Network::createAccount($email, $pw, $r->str('name'));
            } catch (\InvalidArgumentException $e) {
                return $this->setupForm($r, ['email' => $e->getMessage()], ['email' => $email, 'name' => $r->str('name'), 'kind' => 'network']);
            }
            \Core\Network\Network::log('account.create', null, 'setup', strtolower($email));
            $err = app()->auth->attempt($email, $pw, $r->ip());
            if ($err === \Core\Auth::PENDING_2FA) {
                app()->session->set('_2fa_next', \Core\Onboarding::pending() ? '/admin/willkommen' : '/admin/network');
                return Response::redirect(url('/admin/login/2fa'));
            }
            if ($err) {
                app()->session->flash('success', __('Netzwerk-Konto angelegt – bitte anmelden.'));
                return Response::redirect(url('/admin/login'));
            }
            return Response::redirect(url(\Core\Onboarding::pending() ? '/admin/willkommen' : '/admin/network'));
        }
        app()->auth->createUser($email, $pw, 'admin', $r->str('name'));
        app()->auth->attempt($email, $pw, $r->ip());
        // Erststart: zuerst Kit und Startinhalte wählen (Willkommen-Bildschirm), sonst direkt zu den Grundeinstellungen
        if (\Core\Onboarding::pending()) return Response::redirect(url('/admin/willkommen'));
        app()->session->flash('success', 'Willkommen! Ihr Administrationskonto ist angelegt. Bitte richten Sie als Nächstes den E-Mail-Versand und den ' . term('key') . ' ein.');
        return Response::redirect(url('/admin/system'));
    }
}
