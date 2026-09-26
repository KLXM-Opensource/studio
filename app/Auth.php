<?php
declare(strict_types=1);

namespace Core;

final class Auth
{
    /** @deprecated Rollen stehen in der Tabelle „roles“ – siehe Permissions::roles() */
    public const ROLES = ['admin' => 'Administration', 'editor' => 'Redaktion'];

    private ?array $role = null;

    private ?array $user = null;
    private bool $loaded = false;

    public function __construct(private Database $db, private Session $session) {}

    public function user(): ?array
    {
        if (!$this->loaded) {
            // Vor dem Start der Sitzung (z. B. can() im boot einer Erweiterung) nichts merken – sonst gälte die ganze
            // Anfrage als „nicht angemeldet“
            if (!$this->session->started() && PHP_SAPI !== 'cli') return null;
            $this->loaded = true;
            $id = $this->session->get('uid');
            // Multi-Site: Sitzung gilt nur für die Website, auf der angemeldet wurde
            if ($id && !hash_equals(self::siteMark(), (string) $this->session->get('site', ''))) {
                $id = null;
            }
            if ($id) {
                $this->user = $this->db->fetch('SELECT id, email, name, role, locale, appearance, ui_prefs, network_uid, auth_ver, disabled, totp_enabled FROM users WHERE id = ?', [$id]);
                // Konto gesperrt, Passwort/2FA geändert (auth_ver) oder Netzwerk-Konto nicht mehr gültig → Sitzung endet sofort
                if ($this->user && !$this->sessionValid($this->user)) {
                    $this->logout();
                    return null;
                }
                $this->role = $this->user ? (Permissions::role((string) $this->user['role']) ?? Permissions::role('author')) : null;
                // Absolute Timeout-Prüfung
                if ($this->user && time() - (int) $this->session->get('login_at', 0) > 60 * 60 * 12) {
                    $this->logout();
                }
            }
        }
        return $this->user;
    }

    /** Kennung der Website für die Sitzung (aus dem Schlüssel der Website – nicht übertragbar) */
    public static function siteMark(): string
    {
        return substr(hash_hmac('sha256', 'session-site|' . app()->site->key, app()->key()), 0, 32);
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return in_array($this->user()['role'] ?? '', ['admin', 'network'], true);
    }

    /** Sitzung noch gültig? (Sperre, auth_ver; Schatten-Konten: Netzwerk-Konto auf der Netzwerk-Website) */
    private function sessionValid(array $u): bool
    {
        if ((int) ($u['disabled'] ?? 0)) return false;
        if ((int) $this->session->get('auth_ver', 0) !== (int) ($u['auth_ver'] ?? 0)) return false;
        if (!empty($u['network_uid']) && !Network\Network::isNetworkSite()) {
            return ($u['role'] ?? '') === 'network' && Network\Network::stillValid((int) $u['network_uid'], (int) $this->session->get('net_ver', -1));
        }
        return true;
    }

    /** Sitzung für ein Konto starten (nach Passwort und ggf. zweitem Faktor bzw. Netzwerk-Token) */
    public function login(int $uid, array $extra = []): void
    {
        $this->session->regenerate();
        $this->session->forget('_2fa');
        $this->session->set('uid', $uid);
        $this->session->set('site', self::siteMark());
        $this->session->set('login_at', time());
        $this->session->set('auth_ver', (int) $this->db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [$uid]));
        foreach ($extra as $k => $v) $this->session->set($k, $v);
        $this->db->update('users', ['last_login' => now()], 'id = :id', ['id' => $uid]);
        $this->loaded = false;
        // Anmelde-Richtlinie: zweiter Faktor verlangt, aber (noch) nicht eingerichtet → Einrichtung bzw. Erinnerung (Core\Mfa)
        Mfa::afterLogin($uid);
    }

    /** Rückgabe von attempt(): Passwort stimmt, zweiter Faktor steht aus (→ /admin/login/2fa) */
    public const PENDING_2FA = "\0pending-2fa";

    /** Zweiten Faktor anfordern: Konto merken (lokal: uid, Netzwerk-Konto: net), 5 Minuten gültig */
    private function pendingSecondFactor(array $who): string
    {
        $this->session->regenerate();
        $this->session->set('_2fa', $who + ['at' => time(), 'tries' => 0]);
        return self::PENDING_2FA;
    }

    /**
     * Anmeldung mit Netzwerk-Konto auf einer anderen Website (keine lokale Zeile oder Schatten-Konto).
     * @return array|string|null Netzwerk-Konto, Fehlertext oder null (nicht zuständig)
     */
    private function networkAttempt(?array $row, string $email, string $password): array|string|null
    {
        if (Network\Network::isNetworkSite() || ($row && empty($row['network_uid']))) return null;
        $n = Network\Network::account($email);
        if (!$n) return $row ? 'E-Mail-Adresse oder Passwort ist falsch.' : null;
        $limiter = Network\Network::limiter();
        $key = 'netlogin:' . (int) $n['id'];
        if ($limiter->tooMany($key, 10, 900)) return 'Zu viele Anmeldeversuche. Bitte warten Sie 15 Minuten.';
        if (!password_verify($password, (string) $n['password_hash'])) {
            $limiter->hit($key);
            return 'E-Mail-Adresse oder Passwort ist falsch.';
        }
        if ((int) $n['disabled']) return __('Dieses Konto ist gesperrt.');
        // Zweiter Faktor auf dieser Domain: TOTP oder ein Passkey für diese Adresse (Passkeys gelten je Domain – Core\Mfa)
        if (!Mfa::networkStrong($n)) return Mfa::netPolicy()['passkey_only'] ? __('Bitte richten Sie zuerst in der Netzwerk-Verwaltung einen Passkey ein.')
            : __('Bitte richten Sie zuerst in der Netzwerk-Verwaltung die Zwei-Faktor-Anmeldung ein.');
        if (!Mfa::loginMethods($n, Network\Network::db())) {
            return __('Für diese Adresse ist noch kein Passkey eingerichtet. Bitte über die Netzwerk-Übersicht anmelden („Öffnen“) oder hier nach der Anmeldung unter „Konto“ einen Passkey hinzufügen.');
        }
        $limiter->clear($key);
        return $n;
    }

    /** Rolle des angemeldeten Benutzers inkl. Rechten */
    public function role(): ?array
    {
        $this->user();
        return $this->role;
    }

    public function can(string $perm, ?string $table = null): bool
    {
        return Permissions::allows($this->role(), $perm, $table);
    }

    /** @return string|null Fehlermeldung oder null bei Erfolg */
    public function attempt(string $email, string $password, string $ip): ?string
    {
        $limiter = new RateLimiter($this->db);
        $key = 'login:' . hash_hmac('sha256', $ip, app()->key());
        if ($limiter->tooMany($key, 8, 900)) {
            return 'Zu viele Anmeldeversuche. Bitte warten Sie 15 Minuten.';
        }

        $row = $this->db->fetch('SELECT * FROM users WHERE LOWER(email) = LOWER(?)', [$email]);
        // Netzwerk-Administration: Anmeldung mit den zentralen Zugangsdaten auf jeder Website (immer mit zweitem Faktor)
        if (($net = $this->networkAttempt($row, $email, $password)) !== null) {
            if (is_string($net)) {
                $limiter->hit($key);
                return $net;
            }
            $limiter->clear($key);
            return $this->pendingSecondFactor(['net' => (int) $net['id']]);
        }
        // Konstante Laufzeit auch bei unbekannter E-Mail
        $hash = $row['password_hash'] ?? '$2y$12$yaNnm6RkwijxMHDaR.bX5eCLU2vn8HWPVSNJ0WE.fRaZHEEdIQoim';
        if (!password_verify($password, $hash) || !$row) {
            $limiter->hit($key);
            return 'E-Mail-Adresse oder Passwort ist falsch.';
        }

        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            $this->db->update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = :id', ['id' => $row['id']]);
        }
        if ((int) ($row['disabled'] ?? 0)) {
            $limiter->hit($key);
            return __('Dieses Konto ist gesperrt.');
        }
        $limiter->clear($key);
        // Zweiter Faktor (Core\Totp, Core\Passkeys): eingerichtet → abfragen; verlangt, aber noch nicht eingerichtet →
        // Einrichtung verlangen bzw. daran erinnern (Mfa::afterLogin in login())
        if (Mfa::hasFactor($row, $this->db)) {
            return $this->pendingSecondFactor(['uid' => (int) $row['id']]);
        }
        $this->login((int) $row['id']);
        return null;
    }

    public function logout(): void
    {
        $this->user = null;
        $this->session->destroy();
    }

    public function createUser(string $email, string $password, string $role = 'editor', string $name = ''): int
    {
        return $this->db->insert('users', [
            'email'         => strtolower(trim($email)),
            'name'          => $name,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            // „network“ nur über Network::createAccount (Netzwerk-Website) bzw. als Schatten-Konto
            'role'          => $role !== 'network' && Permissions::role($role) ? $role : 'editor',
            'created_at'    => now(),
        ]);
    }

    public static function passwordProblem(string $pw): ?string
    {
        if (mb_strlen($pw) < 12) {
            return 'Das Passwort muss mindestens 12 Zeichen lang sein.';
        }
        return null;
    }
}
