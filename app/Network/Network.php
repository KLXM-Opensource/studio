<?php
declare(strict_types=1);

namespace Core\Network;

use Core\Config;
use Core\Database;
use Core\RateLimiter;
use Core\Site;
use Core\Sites;

/**
 * Netzwerk-Administration: zentrale Konten für alle Websites einer Installation.
 *
 * - Konten mit der Rolle „network“ liegen in der Tabelle users der Netzwerk-Website (config 'network_site', Standard „default“).
 * - Anmeldung zentral auf der Netzwerk-Website → Übersicht /admin/network. „Öffnen“ erzeugt ein signiertes Einmal-Token
 *   (HMAC-SHA256 mit dem Installationsschlüssel 'network_key' aus config/config.local.php – nie in Website-Konfigurationen),
 *   60 s gültig, einmal verwendbar (Nonce in network_sso der Netzwerk-Datenbank), gebunden an Ziel-Website, Konto und Browser.
 * - Die Ziel-Website prüft das Token gegen die Netzwerk-Datenbank, legt ein Schatten-Konto an (users.network_uid, Rolle network,
 *   ohne eigenes Passwort) und startet eine normale Sitzung dieser Website (Cookie je Website, wie bisher).
 * - Jede Anfrage eines Schatten-Kontos prüft das Netzwerk-Konto (gesperrt? Passwort/2FA geändert? → auth_ver): Sitzungen auf
 *   allen Websites enden sofort.
 * - Direkte Anmeldung auf jeder Website mit den Netzwerk-Zugangsdaten (immer mit zweitem Faktor).
 */
final class Network
{
    private static ?Database $db = null;
    private static ?array $install = null;
    private static bool $schema = false;
    private static array $valid = [];

    /** Konfiguration der Installation (config.php ← config.local.php) – ohne Werte einzelner Websites */
    public static function install(): array
    {
        if (self::$install === null) {
            $base = require ROOT . '/config/config.php';
            $local = is_file(ROOT . '/config/config.local.php') ? require ROOT . '/config/config.local.php' : [];
            self::$install = array_replace_recursive($base, is_array($local) ? $local : []);
        }
        return self::$install;
    }

    public static function siteKey(): string
    {
        $k = (string) (self::install()['network_site'] ?? Site::DEFAULT);
        return isset(Sites::all()[$k]) ? $k : Site::DEFAULT;
    }

    public static function isNetworkSite(): bool
    {
        return site()->key === self::siteKey();
    }

    /**
     * Installationsschlüssel für Netzwerk-Tokens. Fehlt er, wird er in config/config.local.php ergänzt
     * (migrate, site:create oder beim ersten Gebrauch). Website-Konfigurationen können ihn nicht setzen oder überschreiben.
     */
    public static function key(): string
    {
        $k = (string) (self::install()['network_key'] ?? '');
        if (strlen($k) >= 32) return $k;
        if (!self::ensureKey()) throw new \RuntimeException('network_key fehlt in config/config.local.php (Datei nicht beschreibbar).');
        return (string) self::install()['network_key'];
    }

    /** network_key in config.local.php ergänzen (idempotent). @return bool vorhanden bzw. angelegt */
    public static function ensureKey(): bool
    {
        if (strlen((string) (self::install()['network_key'] ?? '')) >= 32) return true;
        $file = ROOT . '/config/config.local.php';
        if (!is_file($file)) return false;
        $src = (string) file_get_contents($file);
        $line = "  'network_key' => '" . bin2hex(random_bytes(32)) . "',   // Netzwerk-Anmeldung (SSO) – gilt für alle Websites dieser Installation\n";
        $new = preg_replace('~(\n\s*)(\)|\]);\s*$~', "\n" . $line . '$2;' . "\n", $src, 1, $n);
        if (!$n || !is_writable($file)) return false;
        // Erst prüfen (temporäre Datei einlesen), dann ersetzen – eine defekte config.local.php wäre fatal
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $new, LOCK_EX);
        try {
            $check = require $tmp;
        } catch (\Throwable) {
            $check = null;
        }
        if (!is_array($check) || strlen((string) ($check['network_key'] ?? '')) < 32) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0640);
        rename($tmp, $file);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
        self::$install = null;
        return true;
    }

    /** Konfiguration einer Website (inkl. Standardwerten) */
    public static function config(string $site): Config
    {
        return $site === site()->key ? app()->config : Config::load(ROOT . '/config', $site);
    }

    /** Datenbank der Netzwerk-Website (auf der Netzwerk-Website: die eigene) */
    public static function db(): Database
    {
        if (self::$db === null) {
            $key = self::siteKey();
            if ($key === site()->key) {
                self::$db = app()->db;
            } else {
                $cfg = (array) self::config($key)->get('db');
                if (($cfg['driver'] ?? 'sqlite') === 'sqlite' && !is_file((string) $cfg['path'])) {
                    throw new \RuntimeException('Datenbank der Netzwerk-Website fehlt.');
                }
                self::$db = new Database($cfg);
            }
        }
        if (!self::$schema) {
            self::$schema = true;
            self::ensureSchema(self::$db);
        }
        return self::$db;
    }

    /** app_key der Netzwerk-Website (verschlüsselt die 2FA-Geheimnisse der Netzwerk-Konten) */
    public static function appKey(): string
    {
        return self::siteKey() === site()->key ? app()->key() : (string) self::config(self::siteKey())->get('app_key');
    }

    /** Tabellen der Netzwerk-Datenbank (nur dort; idempotent) */
    public static function ensureSchema(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        // Einmal-Tokens (Nonce) für die Anmeldung auf anderen Websites
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS network_sso (nonce VARCHAR(64) NOT NULL PRIMARY KEY, uid INT NOT NULL,
            site VARCHAR(40) NOT NULL, expires_at INT NOT NULL)$tail");
        // Protokoll: wer hat welche Website geöffnet, Wartung, Sicherungen, Konten
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS network_log (id $pk, created_at VARCHAR(25) NOT NULL, user_email VARCHAR(191) NULL,
            site VARCHAR(40) NULL, action VARCHAR(40) NOT NULL, detail VARCHAR(191) NULL, ip_hash VARCHAR(32) NULL)$tail");
        $db->ensureColumns('users', self::USER_COLUMNS);
        \Core\Passkeys::ensureTable($db);   // Passkeys der Netzwerk-Konten (eine Zeile je Domain)
    }

    /** Neue Spalten der Tabelle users (auch in Database::migrate für jede Website) */
    public const USER_COLUMNS = [
        'disabled' => 'INT NOT NULL DEFAULT 0',          // Konto gesperrt
        'auth_ver' => 'INT NOT NULL DEFAULT 0',          // steigt bei Passwort-/2FA-Änderung, Sperre → alte Sitzungen enden
        'network_uid' => 'INT NULL',                     // Schatten-Konto: ID des Netzwerk-Kontos (Netzwerk-Website)
        'totp_secret' => 'TEXT NULL',                    // 2FA-Geheimnis (verschlüsselt, Core\Totp)
        'totp_recovery' => 'TEXT NULL',                  // Hashes der Wiederherstellungscodes
        'totp_last' => 'INT NULL',                       // zuletzt verwendeter Zeitschritt
        'totp_enabled' => 'INT NOT NULL DEFAULT 0',
        'mfa_last' => 'VARCHAR(10) NULL',                // zuletzt verwendeter zweiter Faktor (totp|passkey, Core\Mfa)
    ];

    // ================================================================= Konten

    /** Ist das (angemeldete) Konto eine Netzwerk-Administration? Auf anderen Websites nur als geprüftes Schatten-Konto. */
    public static function isNetworkUser(?array $u = null): bool
    {
        if ($u === null) {
            if (!isset(app()->auth)) return false;
            $u = app()->auth->user();
        }
        if (!$u || ($u['role'] ?? '') !== 'network') return false;
        return self::isNetworkSite() || !empty($u['network_uid']);
    }

    /** ID des Netzwerk-Kontos zum angemeldeten Konto */
    public static function uid(array $u): int
    {
        return self::isNetworkSite() ? (int) $u['id'] : (int) ($u['network_uid'] ?? 0);
    }

    /** Netzwerk-Konto nach E-Mail (nur Rolle network) */
    public static function account(string $email): ?array
    {
        try {
            return self::db()->fetch("SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND role = 'network'", [trim($email)]);
        } catch (\Throwable $e) {
            error_log('[network] ' . $e->getMessage());
            return null;
        }
    }

    public static function accountById(int $id): ?array
    {
        return self::db()->fetch("SELECT * FROM users WHERE id = ? AND role = 'network'", [$id]);
    }

    /** @return list<array> alle Netzwerk-Konten */
    public static function accounts(): array
    {
        $rows = self::db()->fetchAll("SELECT id, email, name, disabled, totp_enabled, last_login, created_at FROM users WHERE role = 'network' ORDER BY email");
        $pk = \Core\Passkeys::counts(self::db());
        foreach ($rows as &$r) $r['passkeys'] = $pk[(int) $r['id']] ?? 0;
        return $rows;
    }

    /**
     * Gilt die Sitzung eines Schatten-Kontos noch? (Netzwerk-Konto vorhanden, nicht gesperrt, auth_ver unverändert)
     * Eine Abfrage je Anfrage auf die Netzwerk-Datenbank (SQLite: Datei öffnen + Primärschlüssel).
     */
    public static function stillValid(int $uid, int $ver): bool
    {
        if (!isset(self::$valid[$uid])) {
            try {
                $row = self::db()->fetch("SELECT disabled, auth_ver FROM users WHERE id = ? AND role = 'network'", [$uid]);
                self::$valid[$uid] = $row && !(int) $row['disabled'] ? (int) $row['auth_ver'] : null;
            } catch (\Throwable $e) {
                error_log('[network] ' . $e->getMessage());
                self::$valid[$uid] = null;
            }
        }
        return self::$valid[$uid] !== null && self::$valid[$uid] === $ver;
    }

    /** Netzwerk-Konto anlegen (Konsole, Übersicht) */
    public static function createAccount(string $email, string $password, string $name = ''): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException(__('Ungültige E-Mail-Adresse.'));
        if ($p = \Core\Auth::passwordProblem($password)) throw new \InvalidArgumentException(__($p));
        $db = self::db();
        if ($db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ?', [$email])) {
            throw new \InvalidArgumentException(__('Diese E-Mail-Adresse ist auf der Netzwerk-Website bereits registriert.'));
        }
        return $db->insert('users', ['email' => $email, 'name' => $name, 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'network', 'created_at' => now()]);
    }

    /** Sperren, entsperren, 2FA (App und Passkeys) zurücksetzen – beendet alle Sitzungen des Kontos auf allen Websites (auth_ver) */
    public static function setDisabled(int $id, bool $off): void
    {
        self::db()->query("UPDATE users SET disabled = ?, auth_ver = auth_ver + 1 WHERE id = ? AND role = 'network'", [$off ? 1 : 0, $id]);
    }

    public static function resetTwoFactor(int $id): void
    {
        $n = self::db()->query("UPDATE users SET totp_enabled = 0, totp_secret = NULL, totp_recovery = NULL, totp_last = NULL, auth_ver = auth_ver + 1
            WHERE id = ? AND role = 'network'", [$id])->rowCount();
        if ($n) \Core\Passkeys::deleteAll(self::db(), $id);   // Passkeys aller Domains
    }

    // ================================================================= Adressen

    /** Basisadresse einer Website (config base_url → erste Domain; http nur für lokale Test-Domains ohne HTTPS) */
    public static function siteUrl(string $key): string
    {
        $cfg = Sites::all()[$key] ?? [];
        if (!empty($cfg['base_url'])) return rtrim((string) $cfg['base_url'], '/');
        $site = new Site($key, $cfg);
        $host = $site->hosts()[0] ?? null;
        if ($host === null) {
            // Website ohne eigene Domain (z. B. „default“ als Rückfall): aktuelle Adresse, Grundeinstellung „Adresse der Website“,
            // zuletzt gesehene Adresse der Verwaltung (sys.admin_url, gemerkt von rememberUrl) oder base_url
            if ($key === site()->key && ($r = app()->request)) return ($r->isSecure() ? 'https://' : 'http://') . $r->host();
            $base = (string) ($cfg['base_url'] ?? '');
            try {
                $db = $key === self::siteKey() ? self::db() : null;
                if ($db) {
                    foreach (['sys.site_url', 'sys.admin_url'] as $sk) {
                        $v = json_decode((string) $db->fetchValue('SELECT value_json FROM settings WHERE skey = ?', [$sk]), true);
                        if (is_string($v) && preg_match('~^https?://~', $v)) return rtrim($v, '/');
                    }
                }
            } catch (\Throwable) {
            }
            return rtrim($base ?: (string) (self::install()['base_url'] ?? ''), '/');
        }
        $local = str_contains($host, ':') || $host === 'localhost' || str_ends_with($host, '.localhost') || str_starts_with($host, '127.');
        $secure = app()->request ? app()->request->isSecure() : false;
        return ($secure || !$local ? 'https://' : 'http://') . $host;
    }

    /** Adresse der Verwaltung dieser Website merken, wenn sie keine eigene Domain hat (Rückweg „Netzwerk-Übersicht“) */
    public static function rememberUrl(): void
    {
        if (site()->hosts() || !($r = app()->request)) return;
        $u = ($r->isSecure() ? 'https://' : 'http://') . $r->host();
        if (app()->settings->get('sys.admin_url') !== $u) app()->settings->set('sys.admin_url', $u);
    }

    /** Adresse eines Pfads auf einer anderen Website (berücksichtigt url_rewrite der Ziel-Website) */
    public static function siteLink(string $key, string $path): string
    {
        $rewrite = (bool) self::config($key)->get('url_rewrite', true);
        return self::siteUrl($key) . ($rewrite ? $path : '/index.php' . $path);
    }

    // ================================================================= Einmal-Token (SSO)

    public const TOKEN_TTL = 60;

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string|false
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }

    public static function uaHash(string $ua): string
    {
        return substr(hash('sha256', 'ua|' . $ua), 0, 24);
    }

    /** Token für die Anmeldung auf $site ausstellen; Nonce landet in der Netzwerk-Datenbank */
    public static function issue(int $uid, string $site, string $path, string $ua): string
    {
        if (!isset(Sites::all()[$site])) throw new \InvalidArgumentException('Unbekannte Website.');
        $nonce = bin2hex(random_bytes(16));
        $exp = time() + self::TOKEN_TTL;
        $db = self::db();
        $db->query('DELETE FROM network_sso WHERE expires_at < ?', [time() - 300]);
        $db->insert('network_sso', ['nonce' => $nonce, 'uid' => $uid, 'site' => $site, 'expires_at' => $exp]);
        $payload = self::b64(json_encode(['v' => 1, 's' => $site, 'u' => $uid, 'n' => $nonce, 'e' => $exp, 'a' => self::uaHash($ua), 'p' => $path]));
        return $payload . '.' . self::b64(hash_hmac('sha256', 'sso|' . $payload, self::key(), true));
    }

    /**
     * Token prüfen und verbrauchen. @return array{uid:int, path:string} oder Fehlertext
     */
    public static function consume(string $token, string $ua): array|string
    {
        $bad = __('Der Anmelde-Link ist ungültig oder abgelaufen. Bitte über die Netzwerk-Übersicht erneut öffnen.');
        $parts = explode('.', $token);
        if (count($parts) !== 2 || strlen($token) > 2000) return $bad;
        [$payload, $sig] = $parts;
        $mac = self::unb64($sig);
        if ($mac === false || !hash_equals(hash_hmac('sha256', 'sso|' . $payload, self::key(), true), $mac)) return $bad;
        $d = json_decode((string) self::unb64($payload), true);
        if (!is_array($d) || ($d['v'] ?? 0) !== 1) return $bad;
        if (($d['s'] ?? '') !== site()->key || (int) ($d['e'] ?? 0) < time()) return $bad;
        if (!hash_equals((string) ($d['a'] ?? ''), self::uaHash($ua))) return $bad;
        // Einmalig: Nonce atomar löschen – nur wer ihn tatsächlich entfernt, darf sich anmelden
        $st = self::db()->query('DELETE FROM network_sso WHERE nonce = ? AND uid = ? AND site = ? AND expires_at >= ?',
            [(string) ($d['n'] ?? ''), (int) ($d['u'] ?? 0), site()->key, time()]);
        if ($st->rowCount() !== 1) return $bad;
        return ['uid' => (int) $d['u'], 'path' => self::safePath((string) ($d['p'] ?? '/admin'))];
    }

    /** Nur Verwaltungspfade dieser Website als Ziel */
    public static function safePath(string $p): string
    {
        return preg_match('~^/admin(/[\w\-/]*)?$~', $p) && !str_starts_with($p, '/admin/sso') && !str_starts_with($p, '/admin/login') ? $p : '/admin';
    }

    /**
     * Netzwerk-Konto auf dieser Website anmelden: Schatten-Konto anlegen bzw. aktualisieren und Sitzung starten.
     * @return string|null Fehlertext
     */
    public static function loginShadow(array $n, string $via): ?string
    {
        if (($n['role'] ?? '') !== 'network' || (int) $n['disabled']) return __('Dieses Konto ist gesperrt.');
        // Starke Anmeldung Pflicht: TOTP oder Passkey (Richtlinie „nur Passkey“: Passkey) – Core\Mfa
        if (!\Core\Mfa::networkStrong($n)) return \Core\Mfa::netPolicy()['passkey_only']
            ? __('Bitte richten Sie zuerst in der Netzwerk-Verwaltung einen Passkey ein.')
            : __('Bitte richten Sie zuerst in der Netzwerk-Verwaltung die Zwei-Faktor-Anmeldung ein.');
        if (self::isNetworkSite()) {
            app()->auth->login((int) $n['id']);
            self::log($via, site()->key, (string) $n['email']);
            return null;
        }
        $db = app()->db;
        $email = strtolower((string) $n['email']);
        $local = $db->fetch('SELECT id FROM users WHERE network_uid = ?', [(int) $n['id']])
            ?? $db->fetch('SELECT id FROM users WHERE LOWER(email) = ?', [$email]);
        // Schatten-Konto: kein eigenes Passwort ('!network' ist kein gültiger Hash), keine eigene 2FA
        $data = ['email' => $email, 'name' => (string) $n['name'], 'role' => 'network', 'network_uid' => (int) $n['id'],
            'password_hash' => '!network', 'disabled' => 0, 'totp_secret' => null, 'totp_recovery' => null, 'totp_enabled' => 0];
        if ($local) {
            // Anderes lokales Konto mit derselben E-Mail blockiert die Umbenennung? Dann bleibt die alte Adresse stehen.
            $clash = $db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id != ?', [$email, $local['id']]);
            if ($clash) unset($data['email']);
            $db->update('users', $data, 'id = :id', ['id' => $local['id']]);
            $id = (int) $local['id'];
        } else {
            $id = $db->insert('users', $data + ['created_at' => now()]);
        }
        app()->auth->login($id, ['net_ver' => (int) $n['auth_ver']]);
        self::log($via, site()->key, $email);
        return null;
    }

    // ================================================================= Protokoll, Begrenzung

    public static function log(string $action, ?string $site, ?string $email = null, string $detail = ''): void
    {
        try {
            $ip = app()->request ? app()->request->ip() : 'cli';
            self::db()->insert('network_log', ['created_at' => now(), 'user_email' => $email ?? (app()->auth->user()['email'] ?? null),
                'site' => $site, 'action' => $action, 'detail' => mb_substr($detail, 0, 190),
                'ip_hash' => substr(hash_hmac('sha256', $ip, self::key()), 0, 16)]);
        } catch (\Throwable $e) {
            error_log('[network] log: ' . $e->getMessage());
        }
    }

    public static function recentLog(int $n = 25): array
    {
        return self::db()->fetchAll('SELECT * FROM network_log ORDER BY id DESC LIMIT ' . max(1, min(200, $n)));
    }

    /** Begrenzung in der Netzwerk-Datenbank (gilt über alle Websites hinweg, z. B. je Netzwerk-Konto) */
    public static function limiter(): RateLimiter
    {
        return new RateLimiter(self::db());
    }

    /** Für Tests/CLI: zwischengespeicherte Verbindung verwerfen */
    public static function reset(): void
    {
        self::$db = null;
        self::$schema = false;
        self::$valid = [];
        self::$install = null;
    }
}
