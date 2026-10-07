<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\Auth;
use Core\Http\Request;
use Core\Http\Response;

final class UserController extends AdminController
{
    /** Unterseiten von Benutzer & Rollen (Seitenleiste wie die Grundeinstellungen, je Bereich eine Adresse) */
    public const SECTIONS = ['personen', 'einladen', 'rollen', 'sicherheit'];

    /** /admin/users/{section}: Personen, Einladen & Anlegen, Rollen, Anmeldung & Sicherheit */
    public function section(Request $r, string $section): Response
    {
        if (!in_array($section, self::SECTIONS, true)) throw new \Core\Http\HttpException(404);
        return $this->index($r, [], [], $section);
    }

    /** Übersicht (Dashboard) bzw. eine Unterseite; Formularfehler aus „Einladen & Anlegen“ landen wieder dort */
    public function index(Request $r, array $errors = [], array $old = [], string $section = ''): Response
    {
        $this->auth($r, 'users.manage');
        $users = app()->db->fetchAll('SELECT id, email, name, role, created_at, last_login, network_uid, totp_enabled, disabled FROM users ORDER BY email');
        // Eingerichtete Verfahren je Konto (Abzeichen „2FA“, „Passkey ×n“) und Anmelde-Richtlinie (Core\Mfa)
        $pk = \Core\Passkeys::counts(app()->db);
        foreach ($users as &$u) $u['passkeys'] = $pk[(int) $u['id']] ?? 0;
        unset($u);
        // Einladungen (Core\Invites): offene Einladungen, erlaubte Rollen, Link einmal anzeigen, wenn die E-Mail nicht zugestellt wurde
        // Übersicht: Konten, deren Rolle einen zweiten Faktor verlangt, die ihn aber noch nicht eingerichtet haben (Core\Mfa)
        $mfaMissing = [];
        if ($section === '') {
            foreach ($users as $u) {
                if ($u['role'] === 'network' || !empty($u['network_uid'])) continue;
                try {
                    $need = \Core\Mfa::requirement($u);
                    if ($need !== null && !\Core\Mfa::satisfied($u, $need, app()->db)) $mfaMissing[] = (int) $u['id'];
                } catch (\Throwable) {
                }
            }
        }
        // Link nur auf „Einladen & Anlegen“ zeigen (und erst dort verbrauchen)
        $link = null;
        if ($section === 'einladen') {
            $link = app()->session->get('_invite_link');
            app()->session->forget('_invite_link');
        }
        return $this->view('users', ['section' => $section, 'mfaMissing' => $mfaMissing, 'users' => $users, 'errors' => $errors, 'old' => $old, 'policy' => \Core\Mfa::policy(),
            'invites' => \Core\Invites::open(), 'inviteRoles' => \Core\Invites::roleOptions(app()->auth->role()), 'inviteLink' => is_array($link) ? $link : null,
            'css' => ['css/passkey.css', 'css/invite.css', 'css/account.css']], $errors ? 422 : 200);
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
            return $this->index($r, $errors, ['email' => $email, 'name' => $r->str('name'), 'role' => $r->str('role')], 'einladen');
        }
        app()->auth->createUser($email, $pw, $r->str('role'), $r->str('name'));
        return $this->back('/admin/users/personen', 'success', "Benutzer $email angelegt.");
    }

    public function delete(Request $r, string $id): Response
    {
        $me = $this->auth($r, 'users.manage');
        if ((int) $id === (int) $me['id']) {
            return $this->back('/admin/users/personen', 'error', 'Sie können sich nicht selbst löschen.');
        }
        if (self::isNetworkAccount((int) $id)) {
            return $this->back('/admin/users/personen', 'error', __('Netzwerk-Konten werden zentral in der Netzwerk-Verwaltung verwaltet.'));
        }
        $admins = (int) app()->db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?", [(int) $id]);
        if ($admins < 1) {
            return $this->back('/admin/users/personen', 'error', 'Es muss mindestens ein Administrationskonto bestehen bleiben.');
        }
        app()->db->query('DELETE FROM users WHERE id = ?', [(int) $id]);
        return $this->back('/admin/users/personen', 'success', 'Benutzer gelöscht.');
    }

    /** Zwei-Faktor-Anmeldung eines anderen lokalen Kontos zurücksetzen (z. B. Telefon verloren) – beendet dessen Sitzungen */
    public function resetTwoFactor(Request $r, string $id): Response
    {
        $me = $this->auth($r, 'users.manage');
        if ((int) $id === (int) $me['id']) {
            return $this->back('/admin/users/personen', 'error', __('Die eigene Zwei-Faktor-Anmeldung ändern Sie unter „Konto“.'));
        }
        if (self::isNetworkAccount((int) $id)) {
            return $this->back('/admin/users/personen', 'error', __('Netzwerk-Konten werden zentral in der Netzwerk-Verwaltung verwaltet.'));
        }
        $email = app()->db->fetchValue('SELECT email FROM users WHERE id = ?', [(int) $id]);
        if (!$email) return $this->back('/admin/users/personen', 'error', __('Benutzer nicht gefunden.'));
        \Core\Totp::reset((int) $id);
        \Core\Network\Network::log('user.reset-2fa', site()->key, null, (string) $email);
        return $this->back('/admin/users/personen', 'success', __('Zwei-Faktor-Anmeldung von {email} zurückgesetzt.', ['email' => $email]));
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
            return $this->back('/admin/users/sicherheit', 'error', $err);
        }
        $p = \Core\Mfa::policy();
        $detail = implode(', ', array_keys(array_filter(['totp' => $p['totp'], 'passkey' => $p['passkey'], 'passwordless' => $p['passwordless']])))
            . ' · ' . implode(', ', array_map(fn($k, $v) => "$k=$v", array_keys($p['roles']), $p['roles'])) . ' · ' . $p['grace_days'] . 'd';
        \Core\Network\Network::log('auth.policy', site()->key, (string) $me['email'], $detail);
        return $this->back('/admin/users/sicherheit', 'success', __('Einstellung zur Zwei-Faktor-Anmeldung gespeichert.'));
    }

    public function account(Request $r, array $errors = [], array $old = []): Response
    {
        $user = $this->auth($r);
        // Anmeldedaten (Core\EmailChange): offene Änderung der E-Mail-Adresse, Passwort vorhanden?
        return $this->view('account', ['errors' => $errors, 'old' => $old, 'pendingEmail' => \Core\EmailChange::pending((int) $user['id']),
            'css' => ['css/passkey.css', 'css/account.css']], $errors ? 422 : 200);
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

    /**
     * Passwort ändern – oder als erstes Passwort festlegen (Konten nur mit Passkey, z. B. aus einer Einladung: statt des
     * aktuellen Passworts eine frische Anmeldung bzw. Passkey-Bestätigung). Andere Sitzungen enden, Hinweis-E-Mail an das Konto.
     */
    public function saveAccount(Request $r): Response
    {
        $user = $this->auth($r);
        if (!empty($user['network_uid'])) {
            return $this->back('/admin/account', 'error', __('Ihre Anmeldedaten verwalten Sie in der Netzwerk-Verwaltung.'));
        }
        $row = app()->db->fetch('SELECT * FROM users WHERE id = ?', [$user['id']]);
        $first = !\Core\EmailChange::hasPassword($row);
        $errors = [];
        if ($err = AccountController::confirmIdentity($r, $row)) {
            $errors['current'] = $err;
        }
        $pw = (string) ($r->post['password'] ?? '');
        if ($p = Auth::passwordProblem($pw)) {
            $errors['password'] = __($p);
        } elseif ($pw !== (string) ($r->post['password2'] ?? '')) {
            $errors['password2'] = __('Die Passwörter stimmen nicht überein.');
        }
        if ($errors) {
            return $this->account($r, $errors);
        }
        $data = ['password_hash' => password_hash($pw, PASSWORD_DEFAULT)];
        // Ältere Formulare schickten den Namen mit dem Passwort
        if (isset($r->post['name']) && $r->str('name') !== '') $data['name'] = \Core\Invites::clean($r->str('name'), \Core\Invites::NAME_MAX);
        app()->db->update('users', $data, 'id = :id', ['id' => $user['id']]);
        // Andere Sitzungen des Kontos beenden (auth_ver) – bei Netzwerk-Konten auf allen Websites; diese Sitzung bleibt
        app()->db->query('UPDATE users SET auth_ver = auth_ver + 1 WHERE id = ?', [$user['id']]);
        app()->session->regenerate();
        app()->session->set('auth_ver', (int) app()->db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [$user['id']]));
        if (\Core\Network\Network::isNetworkUser()) \Core\Network\Network::log('password', site()->key);
        else \Core\EmailChange::log('user.password', (string) $row['email'], $first ? 'festgelegt' : 'geändert');
        \Core\EmailChange::passwordChanged($row, $first);
        return $this->back('/admin/account#anmeldedaten', 'success', $first ? __('Passwort festgelegt – Sie können sich jetzt auch mit Passwort anmelden.') : __('Passwort geändert.'));
    }

    /**
     * E-Mail-Adresse eines Kontos direkt ändern (Benutzer & Rollen) – ohne Bestätigung; Hinweis an beide Adressen, Protokoll.
     * Nicht für das eigene Konto (dort mit Bestätigung), nicht für Netzwerk-Konten, nicht für Rollen mit mehr Rechten.
     */
    public function changeEmail(Request $r, string $id): Response
    {
        $me = $this->auth($r, 'users.manage');
        $back = '/admin/users/personen';
        if ((int) $id === (int) $me['id']) {
            return $this->back($back, 'error', __('Ihre eigene Adresse ändern Sie unter „Konto › Anmeldedaten“ – mit Bestätigung.'));
        }
        if (self::isNetworkAccount((int) $id)) {
            return $this->back($back, 'error', __('Netzwerk-Konten werden zentral in der Netzwerk-Verwaltung verwaltet.'));
        }
        $target = app()->db->fetch('SELECT * FROM users WHERE id = ?', [(int) $id]);
        if (!$target) return $this->back($back, 'error', __('Benutzer nicht gefunden.'));
        if (!\Core\Invites::assignable(\Core\Permissions::role((string) $target['role']), app()->auth->role())) {
            return $this->back($back, 'error', __('Dieses Konto hat mehr Rechte als Ihres – die Adresse kann nur eine Administration mit allen Rechten ändern.'));
        }
        $limiter = new \Core\RateLimiter(app()->db);
        $key = 'email-admin:' . (int) $me['id'];
        if ($limiter->tooMany($key, 30, 3600)) return $this->back($back, 'error', __('Zu viele Änderungen in kurzer Zeit. Bitte warten Sie eine Stunde.'));
        $new = strtolower(trim($r->str('email')));
        if ($err = \Core\EmailChange::adminChange($target, $new, (string) $me['email'])) {
            return $this->back($back, 'error', $err);
        }
        $limiter->hit($key);
        return $this->back($back, 'success', __('E-Mail-Adresse von {old} auf {new} geändert – beide Adressen wurden benachrichtigt, Sitzungen des Kontos beendet.', ['old' => $target['email'], 'new' => $new]));
    }
}
