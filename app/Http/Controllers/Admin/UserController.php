<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Auth;
use Core\Http\Request;
use Core\Http\Response;

final class UserController extends AdminController
{
    public function index(Request $r, array $errors = [], array $old = []): Response
    {
        $this->auth($r, 'users.manage');
        $users = app()->db->fetchAll('SELECT id, email, name, role, created_at, last_login, network_uid, totp_enabled, disabled FROM users ORDER BY email');
        // Eingerichtete Verfahren je Konto (Abzeichen „2FA“, „Passkey ×n“) und Anmelde-Richtlinie (Core\Mfa)
        $pk = \Core\Passkeys::counts(app()->db);
        foreach ($users as &$u) $u['passkeys'] = $pk[(int) $u['id']] ?? 0;
        unset($u);
        // Einladungen (Core\Invites): offene Einladungen, erlaubte Rollen, Link einmal anzeigen, wenn die E-Mail nicht zugestellt wurde
        $link = app()->session->get('_invite_link');
        app()->session->forget('_invite_link');
        return $this->view('users', ['users' => $users, 'errors' => $errors, 'old' => $old, 'policy' => \Core\Mfa::policy(),
            'invites' => \Core\Invites::open(), 'inviteRoles' => \Core\Invites::roleOptions(app()->auth->role()), 'inviteLink' => is_array($link) ? $link : null,
            'css' => ['css/passkey.css', 'css/invite.css']], $errors ? 422 : 200);
    }

    public function store(Request $r): Response
    {
        $this->auth($r, 'users.manage');
        $email = strtolower($r->str('email'));
        $pw = (string) ($r->post['password'] ?? '');
        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Ungültige E-Mail-Adresse.';
        } elseif (app()->db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ?', [$email])) {
            $errors['email'] = 'Diese E-Mail-Adresse ist bereits registriert.';
        }
        if ($p = Auth::passwordProblem($pw)) {
            $errors['password'] = $p;
        }
        if ($r->str('role') === 'network') {
            $errors['role'] = __('Netzwerk-Konten werden in der Netzwerk-Verwaltung angelegt.');
        }
        if ($errors) {
            return $this->index($r, $errors, ['email' => $email, 'name' => $r->str('name'), 'role' => $r->str('role')]);
        }
        app()->auth->createUser($email, $pw, $r->str('role'), $r->str('name'));
        return $this->back('/admin/users', 'success', "Benutzer $email angelegt.");
    }

    public function delete(Request $r, string $id): Response
    {
        $me = $this->auth($r, 'users.manage');
        if ((int) $id === (int) $me['id']) {
            return $this->back('/admin/users', 'error', 'Sie können sich nicht selbst löschen.');
        }
        if (self::isNetworkAccount((int) $id)) {
            return $this->back('/admin/users', 'error', __('Netzwerk-Konten werden zentral in der Netzwerk-Verwaltung verwaltet.'));
        }
        $admins = (int) app()->db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?", [(int) $id]);
        if ($admins < 1) {
            return $this->back('/admin/users', 'error', 'Es muss mindestens ein Administrationskonto bestehen bleiben.');
        }
        app()->db->query('DELETE FROM users WHERE id = ?', [(int) $id]);
        return $this->back('/admin/users', 'success', 'Benutzer gelöscht.');
    }

    /** Zwei-Faktor-Anmeldung eines anderen lokalen Kontos zurücksetzen (z. B. Telefon verloren) – beendet dessen Sitzungen */
    public function resetTwoFactor(Request $r, string $id): Response
    {
        $me = $this->auth($r, 'users.manage');
        if ((int) $id === (int) $me['id']) {
            return $this->back('/admin/users', 'error', __('Die eigene Zwei-Faktor-Anmeldung ändern Sie unter „Konto“.'));
        }
        if (self::isNetworkAccount((int) $id)) {
            return $this->back('/admin/users', 'error', __('Netzwerk-Konten werden zentral in der Netzwerk-Verwaltung verwaltet.'));
        }
        $email = app()->db->fetchValue('SELECT email FROM users WHERE id = ?', [(int) $id]);
        if (!$email) return $this->back('/admin/users', 'error', __('Benutzer nicht gefunden.'));
        \Core\Totp::reset((int) $id);
        \Core\Network\Network::log('user.reset-2fa', site()->key, null, (string) $email);
        return $this->back('/admin/users', 'success', __('Zwei-Faktor-Anmeldung von {email} zurückgesetzt.', ['email' => $email]));
    }

    /** Netzwerk-Konto (Rolle network oder Schatten-Konto) – lokal nicht änderbar */
    public static function isNetworkAccount(int $id): bool
    {
        $u = app()->db->fetch('SELECT role, network_uid FROM users WHERE id = ?', [$id]);
        return $u && ($u['role'] === 'network' || !empty($u['network_uid']));
    }

    /**
     * Anmeldung & Sicherheit: erlaubte Verfahren, zweiter Faktor je Rolle, Übergangsfrist (Core\Mfa, Einstellung sys.auth_policy;
     * sys.twofa_roles wird mitgepflegt). Ältere Formulare mit roles[] = „verlangt“ werden weiter verstanden.
     */
    public function twoFactorRoles(Request $r): Response
    {
        $me = $this->auth($r, 'users.manage');
        $in = $r->post;
        if (!isset($in['methods'])) {
            $p = \Core\Mfa::policy();
            $in = ['totp' => $p['totp'], 'passkey' => $p['passkey'], 'passwordless' => $p['passwordless'], 'grace_days' => $p['grace_days'],
                'req' => array_fill_keys(array_map('strval', (array) ($r->post['roles'] ?? [])), 'any')];
        }
        if ($err = \Core\Mfa::savePolicy($in, array_keys(\Core\Permissions::roles()))) {
            return $this->back('/admin/users#zwei-faktor', 'error', $err);
        }
        $p = \Core\Mfa::policy();
        $detail = implode(', ', array_keys(array_filter(['totp' => $p['totp'], 'passkey' => $p['passkey'], 'passwordless' => $p['passwordless']])))
            . ' · ' . implode(', ', array_map(fn($k, $v) => "$k=$v", array_keys($p['roles']), $p['roles'])) . ' · ' . $p['grace_days'] . 'd';
        \Core\Network\Network::log('auth.policy', site()->key, (string) $me['email'], $detail);
        return $this->back('/admin/users#zwei-faktor', 'success', __('Einstellung zur Zwei-Faktor-Anmeldung gespeichert.'));
    }

    public function account(Request $r, array $errors = []): Response
    {
        $this->auth($r);
        return $this->view('account', ['errors' => $errors, 'css' => ['css/passkey.css']], $errors ? 422 : 200);
    }

    /** Oberflächensprache des eigenen Kontos */
    public function saveLocale(Request $r): Response
    {
        $user = $this->auth($r);
        $loc = $r->str('locale');
        $app = $r->str('appearance');
        app()->db->update('users', ['locale' => array_key_exists($loc, \Core\I18n::available()) ? $loc : null,
            'appearance' => in_array($app, ['light', 'dark'], true) ? $app : null], 'id = :id', ['id' => $user['id']]);
        return $this->back('/admin/account', 'success', \Core\I18n::translate('Einstellungen gespeichert.'));
    }

    /** Persönliche Akzentfarbe der Verwaltung (Core\Accent) */
    public function saveAccent(Request $r): Response
    {
        $user = $this->auth($r);
        \Core\Accent::save((int) $user['id'], $r->str('accent'), !empty($r->post['side']));
        return $this->back('/admin/account#akzent', 'success', __('Akzentfarbe gespeichert.'));
    }

    public function saveAccount(Request $r): Response
    {
        $user = $this->auth($r);
        if (!empty($user['network_uid'])) {
            return $this->back('/admin/account', 'error', __('Netzwerk-Konten ändern Passwort und Namen in der Netzwerk-Verwaltung.'));
        }
        $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [$user['id']]);
        $errors = [];
        if (!password_verify((string) ($r->post['current'] ?? ''), (string) $row['password_hash'])) {
            $errors['current'] = 'Das aktuelle Passwort ist falsch.';
        }
        $pw = (string) ($r->post['password'] ?? '');
        if ($p = Auth::passwordProblem($pw)) {
            $errors['password'] = $p;
        } elseif ($pw !== (string) ($r->post['password2'] ?? '')) {
            $errors['password2'] = 'Die Passwörter stimmen nicht überein.';
        }
        if ($errors) {
            return $this->account($r, $errors);
        }
        app()->db->update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'name' => $r->str('name') ?: $row['name']], 'id = :id', ['id' => $user['id']]);
        // Andere Sitzungen des Kontos beenden (auth_ver) – bei Netzwerk-Konten auf allen Websites; diese Sitzung bleibt
        app()->db->query('UPDATE users SET auth_ver = auth_ver + 1 WHERE id = ?', [$user['id']]);
        app()->session->regenerate();
        app()->session->set('auth_ver', (int) app()->db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [$user['id']]));
        if (\Core\Network\Network::isNetworkUser()) \Core\Network\Network::log('password', site()->key);
        return $this->back('/admin/account', 'success', 'Passwort geändert.');
    }
}
