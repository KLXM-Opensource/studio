<?php
declare(strict_types=1);

namespace Core;

use Core\Network\Network;

/**
 * „Passwort vergessen“: Link per E-Mail, neues Passwort festlegen (Anmeldeseite → /admin/passwort-vergessen, PasswordController).
 *
 * - Anfrage: immer dieselbe Antwort („Wenn ein Konto zu dieser Adresse existiert …“) – unbekannte, gesperrte und
 *   Schatten-Konten, Begrenzung je Adresse und fehlender E-Mail-Versand sehen nach außen gleich aus. Begrenzung je Anschluss
 *   (sichtbar, verrät nichts über Adressen) und je Adresse (still). Die E-Mail geht nach der Antwort hinaus
 *   (fastcgi_finish_request), damit die Antwortzeit nichts über vorhandene Konten verrät.
 * - Tabelle user_password_resets der Website (Database::migrate). Token: 32 Zufallsbytes (base64url, 43 Zeichen), gespeichert
 *   nur als SHA-256 mit Zweck und Kürzel der Website → gilt nur hier; Vergleich zusätzlich mit hash_equals. Gültig 60 Minuten
 *   (config 'password_reset_minutes', 10–1440), einmal verwendbar; ein neuer Link macht ältere ungültig.
 * - Ungültig auch, wenn das Konto inzwischen gesperrt oder gelöscht ist, die E-Mail-Adresse sich geändert hat oder es ein
 *   Schatten-Konto ist.
 * - Neues Passwort (Auth::passwordProblem): auth_ver + 1 → alle Sitzungen enden (Netzwerk-Konten: auf allen Websites),
 *   Hinweis-E-Mail, Protokoll. KEINE Anmeldung: Zwei-Faktor-Anmeldung und Passkeys bleiben unverändert und werden bei der
 *   nächsten Anmeldung wie gewohnt abgefragt. Konten nur mit Passkey ('!passkey') bekommen so ein Passwort.
 * - Netzwerk-Konten setzen ihr Passwort auf der Netzwerk-Website zurück. Auf anderen Websites bekommt die Adresse eines
 *   Netzwerk-Kontos (Schatten-Konto oder nur im Netzwerk) eine E-Mail mit dem Weg zur Netzwerk-Website – ohne Link mit Token.
 * - Adresse im Link: nie aus dem Host-Header einer beliebigen Anfrage (sys.site_url, base_url, eine Domain der Website).
 * - Kein E-Mail-Versand eingerichtet: gleiche Antwort, Warnung im Protokoll (network_log) und im PHP-Fehlerprotokoll.
 */
final class PasswordReset
{
    public const MINUTES = 60;
    public const PER_IP = 5;          // Anfragen je Anschluss …
    public const IP_WINDOW = 900;     // … in 15 Minuten
    public const PER_ADDRESS = 3;     // E-Mails je Adresse und Stunde

    /** Selbsttest: keine E-Mails, kein Protokoll, nichts nach der Antwort */
    private static bool $mute = false;
    /** Nach der Antwort zu sendende E-Mails */
    private static array $queue = [];
    private static bool $deferred = false;

    // ================================================================= Tabelle

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS user_password_resets (id $pk, user_id INT NOT NULL, email VARCHAR(191) NOT NULL,
            token_hash VARCHAR(64) NOT NULL UNIQUE, created_at VARCHAR(25) NOT NULL, expires_at INT NOT NULL, used_at VARCHAR(25) NULL)$tail");
        if (!$my) $db->pdo->exec('CREATE INDEX IF NOT EXISTS user_password_resets_user ON user_password_resets (user_id)');
    }

    // ================================================================= Token

    /** Gültigkeit in Minuten (config 'password_reset_minutes') */
    public static function minutes(): int
    {
        return max(10, min(1440, (int) app()->config->get('password_reset_minutes', self::MINUTES)));
    }

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** Hash des Tokens – an Zweck und Website gebunden */
    public static function hash(string $token): string
    {
        return hash('sha256', 'password-reset|' . site()->key . '|' . $token);
    }

    public static function wellFormed(string $token): bool
    {
        return (bool) preg_match('~^[A-Za-z0-9_-]{43}$~', $token);
    }

    /**
     * Vertrauenswürdige Adresse dieser Website für Links in E-Mails – nie der Host-Header einer beliebigen Anfrage
     * (sonst könnte jemand Links auf eine fremde Domain erzeugen). null = keine Adresse bekannt.
     */
    public static function base(): ?string
    {
        foreach ([(string) app()->settings->get('sys.site_url', ''), (string) app()->config->get('base_url', '')] as $u) {
            if (preg_match('~^https?://[^/\s]+~i', $u)) return rtrim($u, '/');
        }
        $hosts = site()->hosts();
        $r = app()->request;
        $host = $r ? strtolower(preg_replace('~:\d+$~', '', $r->host()) ?? '') : '';
        if ($r && $host !== '' && in_array($host, $hosts, true)) return ($r->isSecure() ? 'https://' : 'http://') . $r->host();
        if ($hosts) {
            $h = $hosts[0];
            $local = $h === 'localhost' || str_ends_with($h, '.localhost') || str_starts_with($h, '127.');
            return ($local ? 'http://' : 'https://') . $h;
        }
        $admin = (string) app()->settings->get('sys.admin_url', '');   // zuletzt gesehene Adresse der Verwaltung (Network::rememberUrl)
        return preg_match('~^https?://[^/\s]+~i', $admin) ? rtrim($admin, '/') : null;
    }

    public static function url(string $token): ?string
    {
        $b = self::base();
        return $b === null ? null : $b . url('/admin/passwort/' . $token);
    }

    // ================================================================= Anfrage

    /**
     * „Passwort vergessen“ anfragen. Nach außen immer dieselbe Antwort; der Rückgabewert dient nur Protokoll und Selbsttest.
     * @return string sent|limited-ip|limited-address|invalid|unknown|disabled|network|nomail|nourl
     */
    public static function request(string $email, string $ip): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) return 'invalid';
        $db = app()->db;
        $limiter = new RateLimiter($db);
        $ipKey = 'pwreset-ip:' . hash_hmac('sha256', $ip, app()->key());
        if ($limiter->tooMany($ipKey, self::PER_IP, self::IP_WINDOW)) return 'limited-ip';
        $limiter->hit($ipKey);
        $mailKey = 'pwreset-mail:' . hash_hmac('sha256', $email, app()->key());
        if ($limiter->tooMany($mailKey, self::PER_ADDRESS, 3600)) {
            self::log('user.password-reset-limit', $email, 'zu viele Anfragen für diese Adresse – keine E-Mail');
            return 'limited-address';
        }
        $limiter->hit($mailKey);

        $user = $db->fetch('SELECT * FROM users WHERE LOWER(email) = ?', [$email]);
        // Netzwerk-Konto auf einer anderen Website: Schatten-Konto oder nur im Netzwerk → Weg zur Netzwerk-Website
        if (!Network::isNetworkSite() && (!$user || !empty($user['network_uid']) || ($user['role'] ?? '') === 'network')) {
            $n = Network::account($email);
            if (!$n) return $user ? 'disabled' : 'unknown';
            if ((int) $n['disabled']) {
                self::log('user.password-reset-request', $email, 'Netzwerk-Konto gesperrt – keine E-Mail');
                return 'disabled';
            }
            if (!Mailer::ready()) return self::noMail($email, 'Netzwerk-Konto');
            $url = Network::siteLink(Network::siteKey(), '/admin/passwort-vergessen');
            self::queue($email, $n, 'reset-network', ['url' => $url, 'network' => Network::siteUrl(Network::siteKey())]);
            self::log('user.password-reset-request', $email, 'Netzwerk-Konto → Hinweis auf die Netzwerk-Website');
            return 'network';
        }
        if (!$user) return 'unknown';
        if ((int) ($user['disabled'] ?? 0)) {
            self::log('user.password-reset-request', $email, 'Konto gesperrt – keine E-Mail');
            return 'disabled';
        }
        if (!Mailer::ready()) return self::noMail($email, 'Konto');
        $token = self::newToken();
        $url = self::url($token);
        if ($url === null) return self::noMail($email, 'Adresse der Website unbekannt (Grundeinstellungen › Adresse der Website)', 'nourl');
        // Nur der neueste Link gilt
        $db->query('DELETE FROM user_password_resets WHERE user_id = ?', [(int) $user['id']]);
        $expires = time() + self::minutes() * 60;
        $db->insert('user_password_resets', ['user_id' => (int) $user['id'], 'email' => $email, 'token_hash' => self::hash($token),
            'created_at' => now(), 'expires_at' => $expires]);
        self::queue($email, $user, 'reset', ['url' => $url, 'expires' => $expires, 'minutes' => self::minutes()]);
        self::log('user.password-reset-request', $email, 'Link gesendet');
        return 'sent';
    }

    /** Kein Versand möglich: gleiche Antwort nach außen, Warnung für die Administration */
    private static function noMail(string $email, string $what, string $code = 'nomail'): string
    {
        error_log('[password-reset] Keine E-Mail möglich (' . $what . '): E-Mail-Versand bzw. Adresse der Website einrichten.');
        self::log('warn.password-reset-nomail', $email, $code === 'nourl' ? $what : 'E-Mail-Versand nicht eingerichtet (' . $what . ')');
        return $code;
    }

    /** E-Mail nach der Antwort senden (Antwortzeit gleich für alle Adressen); Kommandozeile/Selbsttest: sofort bzw. gar nicht */
    private static function queue(string $to, array $user, string $kind, array $v): void
    {
        if (self::$mute) return;
        if (PHP_SAPI === 'cli') {
            EmailChange::mail($to, $user, $kind, $v);
            return;
        }
        self::$queue[] = [$to, $user, $kind, $v];
        if (self::$deferred) return;
        self::$deferred = true;
        register_shutdown_function(static function (): void {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            foreach (self::$queue as [$to, $user, $kind, $v]) {
                try {
                    $res = EmailChange::mail($to, $user, $kind, $v);
                    if ($res['error']) error_log('[password-reset] ' . $res['error']);
                } catch (\Throwable $e) {
                    error_log('[password-reset] ' . $e->getMessage());
                }
            }
            self::$queue = [];
        });
    }

    // ================================================================= Link aus der E-Mail

    /** Anfrage zum Token – nur, wenn sie noch gilt; sonst null (keine Unterscheidung nach außen) */
    public static function find(string $token): ?array
    {
        if (!self::wellFormed($token)) return null;
        $hash = self::hash($token);
        try {
            $row = app()->db->fetch('SELECT * FROM user_password_resets WHERE token_hash = ?', [$hash]);
        } catch (\Throwable) {
            return null;
        }
        if (!$row || !hash_equals((string) $row['token_hash'], $hash)) return null;
        return self::usable($row) ? $row : null;
    }

    /** Noch gültig? (Frist, einmalig, Konto vorhanden, nicht gesperrt, Adresse unverändert, kein Schatten-Konto) */
    public static function usable(array $row): bool
    {
        if ($row['used_at'] || (int) $row['expires_at'] <= time()) return false;
        $u = app()->db->fetch('SELECT email, role, disabled, network_uid FROM users WHERE id = ?', [(int) $row['user_id']]);
        if (!$u || (int) $u['disabled'] || !empty($u['network_uid'])) return false;
        if ($u['role'] === 'network' && !Network::isNetworkSite()) return false;
        return strtolower((string) $u['email']) === strtolower((string) $row['email']);
    }

    /**
     * Neues Passwort setzen (Richtlinie vorher geprüft). Prüft in einer Transaktion erneut; alle Sitzungen enden (auth_ver),
     * alle offenen Links des Kontos verfallen. Meldet NICHT an – der zweite Faktor bleibt Pflicht.
     * @return array|null Konto nach der Änderung oder null (Link nicht mehr gültig)
     */
    public static function reset(array $row, string $password): ?array
    {
        $db = app()->db;
        try {
            $res = $db->transaction(function (Database $db) use ($row, $password): ?array {
                $now = $db->fetch('SELECT * FROM user_password_resets WHERE id = ?', [(int) $row['id']]);
                if (!$now || !hash_equals((string) $row['token_hash'], (string) $now['token_hash']) || !self::usable($now)) return null;
                $uid = (int) $now['user_id'];
                $before = $db->fetch('SELECT * FROM users WHERE id = ?', [$uid]);
                $n = $db->query('UPDATE user_password_resets SET used_at = ?, token_hash = ? WHERE id = ? AND used_at IS NULL',
                    [now(), 'used-' . bin2hex(random_bytes(16)), (int) $now['id']])->rowCount();
                if ($n !== 1) return null;
                $db->query('UPDATE users SET password_hash = ?, auth_ver = auth_ver + 1 WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $uid]);
                $db->query('DELETE FROM user_password_resets WHERE user_id = ? AND used_at IS NULL', [$uid]);
                return ['user' => $db->fetch('SELECT * FROM users WHERE id = ?', [$uid]), 'first' => !EmailChange::hasPassword($before)];
            });
        } catch (\Throwable $e) {
            error_log('[password-reset] reset: ' . $e->getMessage());
            return null;
        }
        if (!$res) return null;
        $user = $res['user'];
        // Diese Sitzung (falls jemand anderes hier angemeldet war) nicht weiterführen
        if (PHP_SAPI !== 'cli' && (int) (app()->auth->user()['id'] ?? 0) === (int) $user['id']) app()->auth->logout();
        self::log('user.password-reset', (string) $user['email'], ($res['first'] ? 'erstes Passwort' : 'neu gesetzt') . ' · Sitzungen beendet'
            . (Mfa::hasFactor($user) ? ' · 2FA bleibt' : ''));
        if (!self::$mute) EmailChange::mail((string) $user['email'], $user, 'reset-done', ['first' => $res['first']]);
        return $user;
    }

    // ================================================================= Hilfen

    public static function log(string $action, ?string $email, string $detail): void
    {
        if (self::$mute) return;
        try {
            Network::log($action, site()->key, $email, $detail);
        } catch (\Throwable) {
        }
    }

    // ================================================================= Selbsttest

    /**
     * Selbsttest (Konsole account:selftest): Token, Hash, Frist, einmalig, keine Rückschlüsse, 2FA/Passkeys bleiben,
     * Sitzungen enden, gesperrte Konten. In einer Transaktion mit Testkonten, danach zurückgerollt; keine E-Mails.
     */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = 'Passwort vergessen · ' . $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $t = self::newToken();
        $eq('Token 43 Zeichen base64url (32 Byte)', self::wellFormed($t), true);
        $eq('Token zufällig', $t === self::newToken(), false);
        $eq('Hash SHA-256 (64 hex)', (bool) preg_match('~^[0-9a-f]{64}$~', self::hash($t)), true);
        $eq('Hash an Zweck und Website gebunden', self::hash($t) === hash('sha256', $t) || self::hash($t) === EmailChange::hash('confirm', $t), false);
        foreach (['', 'abc', str_repeat('a', 42), str_repeat('a', 44), str_repeat('a', 42) . '/', "x' OR 1=1 --"] as $bad) {
            $eq('Token ungültig: ' . $bad, self::find($bad), null);
        }
        $eq('Unbekannter Token → null', self::find(self::newToken()), null);
        $eq('Frist 10–1440 Minuten', self::minutes() >= 10 && self::minutes() <= 1440, true);

        $db = app()->db;
        $db->pdo->beginTransaction();
        self::$mute = true;
        try {
            $sfx = bin2hex(random_bytes(4));
            $ip = 'selftest-' . $sfx;
            $mk = fn(string $n, array $x = []) => $db->insert('users', $x + ['email' => "reset-$n-$sfx@example.invalid", 'name' => 'Test', 'password_hash' => password_hash('altes-passwort-123', PASSWORD_DEFAULT), 'role' => 'editor', 'created_at' => now()]);
            $a = $mk('a');
            $pk = $mk('pk', ['password_hash' => '!passkey']);
            $off = $mk('off', ['disabled' => 1]);
            $ready = Mailer::ready();
            $issue = function (int $uid) use ($db): string {
                // Token aus dem Hash nicht ableitbar → eigenen Token für die offene Anfrage setzen
                $tok = self::newToken();
                $db->update('user_password_resets', ['token_hash' => self::hash($tok)], 'user_id = :u AND used_at IS NULL', ['u' => $uid]);
                return $tok;
            };

            // Keine Rückschlüsse: unbekannt, gesperrt, vorhanden → nach außen gleich (Controller zeigt immer dieselbe Meldung)
            $eq('Unbekannte Adresse: keine Anfrage', self::request("niemand-$sfx@example.invalid", $ip . '-1'), 'unknown');
            $eq('Gesperrt: keine Anfrage', self::request("reset-off-$sfx@example.invalid", $ip . '-2'), 'disabled');
            $eq('Gesperrt: kein Link gespeichert', (int) $db->fetchValue('SELECT COUNT(*) FROM user_password_resets WHERE user_id = ?', [$off]), 0);
            $got = self::request("RESET-A-$sfx@example.invalid", $ip . '-3');
            $eq('Vorhanden: Link (bzw. ohne Versand: kein Link)', $got, $ready ? (self::base() ? 'sent' : 'nourl') : 'nomail');
            if ($got !== 'sent') {
                // Ohne eingerichteten Versand weiter mit einer direkt angelegten Anfrage
                $db->insert('user_password_resets', ['user_id' => $a, 'email' => "reset-a-$sfx@example.invalid", 'token_hash' => self::hash(self::newToken()),
                    'created_at' => now(), 'expires_at' => time() + 3600]);
            }
            $eq('Nur Hash gespeichert', (bool) preg_match('~^[0-9a-f]{64}$~', (string) $db->fetchValue('SELECT token_hash FROM user_password_resets WHERE user_id = ?', [$a])), true);
            $exp = (int) $db->fetchValue('SELECT expires_at FROM user_password_resets WHERE user_id = ?', [$a]);
            if ($got === 'sent') $eq('Frist = Einstellung', abs($exp - (time() + self::minutes() * 60)) <= 5, true);

            // Begrenzung je Anschluss und je Adresse
            for ($i = 0; $i < self::PER_IP; $i++) self::request("x$i-$sfx@example.invalid", $ip . '-ip');
            $eq('Begrenzung je Anschluss', self::request("y-$sfx@example.invalid", $ip . '-ip'), 'limited-ip');
            for ($i = 0; $i < self::PER_ADDRESS; $i++) self::request("z-$sfx@example.invalid", $ip . '-m' . $i);
            $eq('Begrenzung je Adresse (still)', self::request("z-$sfx@example.invalid", $ip . '-m9'), 'limited-address');

            // Link: gültig, abgelaufen, einmalig
            $tok = $issue($a);
            $eq('Link gültig', (int) (self::find($tok)['user_id'] ?? 0), $a);
            $db->update('user_password_resets', ['expires_at' => time() - 1], 'user_id = :u', ['u' => $a]);
            $eq('Abgelaufen → null', self::find($tok), null);
            $db->update('user_password_resets', ['expires_at' => time() + 600], 'user_id = :u', ['u' => $a]);
            $eq('Anderer Zweck (E-Mail-Änderung) → null', EmailChange::find('confirm', $tok), null);
            // Adresse inzwischen geändert → ungültig
            $db->query('UPDATE users SET email = ? WHERE id = ?', ["reset-a2-$sfx@example.invalid", $a]);
            $eq('Adresse geändert → null', self::find($tok), null);
            $db->query('UPDATE users SET email = ? WHERE id = ?', ["reset-a-$sfx@example.invalid", $a]);

            // 2FA bleibt Pflicht: Konto mit App-Code; Passwort zurücksetzen meldet nicht an und lässt TOTP/Passkeys unverändert
            $db->query('UPDATE users SET totp_enabled = 1, totp_secret = ? WHERE id = ?', ['selftest', $a]);
            $ver = (int) $db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [$a]);
            $row = self::find($tok);
            $u = $row ? self::reset($row, 'neues-passwort-selftest-1') : null;
            $eq('Zurückgesetzt', (int) ($u['id'] ?? 0), $a);
            $eq('Passwort neu', $u ? password_verify('neues-passwort-selftest-1', (string) $u['password_hash']) : false, true);
            $eq('auth_ver + 1 (alle Sitzungen enden)', (int) ($u['auth_ver'] ?? -1), $ver + 1);
            $eq('2FA unverändert', (int) ($u['totp_enabled'] ?? 0) === 1 && ($u['totp_secret'] ?? '') === 'selftest', true);
            $eq('Link einmal verwendbar', self::find($tok), null);
            $eq('Zweites Absenden → null', $row ? self::reset($row, 'noch-ein-passwort-123') : null, null);
            $eq('Anmeldung verlangt weiter den zweiten Faktor', app()->auth->attempt("reset-a-$sfx@example.invalid", 'neues-passwort-selftest-1', $ip . '-login'), Auth::PENDING_2FA);
            $old = app()->auth->attempt("reset-a-$sfx@example.invalid", 'altes-passwort-123', $ip . '-login2');
            $eq('Altes Passwort gilt nicht mehr', is_string($old) && $old !== Auth::PENDING_2FA, true);

            // Nur Passkey: bekommt ein Passwort, Passkeys bleiben (Konto-ID unverändert)
            $db->insert('user_password_resets', ['user_id' => $pk, 'email' => "reset-pk-$sfx@example.invalid", 'token_hash' => self::hash($tp = self::newToken()),
                'created_at' => now(), 'expires_at' => time() + 600]);
            $u = ($row = self::find($tp)) ? self::reset($row, 'passkey-plus-passwort-1') : null;
            $eq('Nur Passkey → Passwort festgelegt', $u ? EmailChange::hasPassword($u) : false, true);

            // Gesperrt nach der Anfrage → Link ungültig
            $db->insert('user_password_resets', ['user_id' => $off, 'email' => "reset-off-$sfx@example.invalid", 'token_hash' => self::hash($to = self::newToken()),
                'created_at' => now(), 'expires_at' => time() + 600]);
            $eq('Gesperrtes Konto → null', self::find($to), null);
            // Schatten-Konto (Netzwerk) → nie ein Link auf dieser Website
            if (!Network::isNetworkSite()) {
                $sh = $mk('shadow', ['role' => 'network', 'network_uid' => 999999, 'password_hash' => '!network']);
                $db->insert('user_password_resets', ['user_id' => $sh, 'email' => "reset-shadow-$sfx@example.invalid", 'token_hash' => self::hash($ts = self::newToken()),
                    'created_at' => now(), 'expires_at' => time() + 600]);
                $eq('Schatten-Konto → null', self::find($ts), null);
            }

            // E-Mails lassen sich erzeugen, Werte escaped
            foreach (['reset', 'reset-network', 'reset-done'] as $k) {
                [$s, $txt, $html] = EmailChange::compose(['name' => '<b>X</b>', 'email' => 'a@example.invalid'], $k,
                    ['url' => 'https://example.invalid/x?a=1&b=2', 'expires' => time() + 3600, 'minutes' => 60, 'network' => 'https://example.invalid', 'first' => false]);
                $eq("E-Mail $k: Betreff", $s !== '', true);
                $eq("E-Mail $k: kein ungefiltertes HTML", str_contains($html, '<b>X</b>'), false);
                $eq("E-Mail $k: Text", str_contains($txt, 'X'), true);
            }
        } catch (\Throwable $e) {
            $fails[] = 'Passwort vergessen · Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            if ($db->pdo->inTransaction()) $db->pdo->rollBack();
            self::$mute = false;
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
