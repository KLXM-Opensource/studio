<?php
declare(strict_types=1);

namespace Core;

/**
 * Einladungen für Konten dieser Website (Benutzer & Rollen → „Person einladen“, CLI user:invite).
 *
 * - Tabelle user_invites der Website (Database::migrate). Das Konto entsteht erst beim Annehmen – vorher gibt es keine
 *   Zeile in users (keine Anmeldung, nicht in Chat, Benachrichtigungen o. Ä.).
 * - Token: 32 Zufallsbytes (base64url, 43 Zeichen), gespeichert nur als SHA-256 mit dem Kürzel der Website → gilt nur hier.
 *   Einmal verwendbar; „Erneut senden“ erzeugt einen neuen Token (der alte gilt nicht mehr), „Zurückziehen“ macht ihn ungültig.
 * - Gültig 7 Tage (config 'invite_days', 1–60). Ungültig auch, wenn die Rolle fehlt, die Adresse inzwischen ein Konto hat
 *   oder das einladende Konto gesperrt bzw. gelöscht ist.
 * - Rollen: nie „network“; niemand lädt in eine Rolle ein, die mehr darf als die eigene (assignable()).
 * - E-Mail: HTML + Text (Core\Mailer), Vorlage app/Admin/views/mail/invitation(.txt).php, im Kit überschreibbar unter
 *   themes/{kit}/templates/mail/invitation(.txt).php. Sprache der Einladung (de/en), App-Icon als eingebettetes Bild (CID).
 * - Annehmen: /admin/einladung/{token} (InviteController) – Passkey und/oder Passwort, dann Anmeldung.
 */
final class Invites
{
    public const DAYS = 7;
    public const MESSAGE_MAX = 500;
    public const NAME_MAX = 120;

    // ================================================================= Tabelle

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS user_invites (id $pk, token_hash VARCHAR(64) NOT NULL UNIQUE, email VARCHAR(191) NOT NULL,
            name VARCHAR(191) NULL, role VARCHAR(40) NOT NULL, message TEXT NULL, locale VARCHAR(10) NULL, invited_by INT NULL,
            invited_by_name VARCHAR(191) NULL, created_at VARCHAR(25) NOT NULL, expires_at INT NOT NULL, sent_at VARCHAR(25) NULL,
            sends INT NOT NULL DEFAULT 0, accepted_at VARCHAR(25) NULL, revoked_at VARCHAR(25) NULL, user_id INT NULL, method VARCHAR(20) NULL)$tail");
        if (!$my) $db->pdo->exec('CREATE INDEX IF NOT EXISTS user_invites_email ON user_invites (email)');
    }

    // ================================================================= Token

    /** Gültigkeit in Tagen (config 'invite_days') */
    public static function days(): int
    {
        return max(1, min(60, (int) app()->config->get('invite_days', self::DAYS)));
    }

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** Hash des Tokens – an die Website gebunden (ein Token einer anderen Website passt nie) */
    public static function hash(string $token): string
    {
        return hash('sha256', 'invite|' . site()->key . '|' . $token);
    }

    /** Form des Tokens (vor jeder Datenbankabfrage) */
    public static function wellFormed(string $token): bool
    {
        return (bool) preg_match('~^[A-Za-z0-9_-]{43}$~', $token);
    }

    public static function url(string $token): string
    {
        return self::base() . url('/admin/einladung/' . $token);
    }

    /** Adresse der Website (sys.site_url bzw. aufgerufene Domain; Kommandozeile ohne sys.site_url: erste Domain der Website) */
    public static function base(): string
    {
        $base = site_url();
        if ($base === '') {
            $host = (string) (site()->hosts()[0] ?? 'localhost');
            $base = (str_contains($host, 'localhost') || str_starts_with($host, '127.') ? 'http://' : 'https://') . $host;
        }
        return $base;
    }

    // ================================================================= Rollen

    /** Darf die eigene Rolle ($mine) in $target einladen? Nie mehr Rechte oder Datentabellen als die eigene Rolle; null = CLI. */
    public static function assignable(?array $target, ?array $mine): bool
    {
        if (!$target || $target['key'] === 'network') return false;
        if ($mine === null || in_array('*', $mine['permissions'], true)) return true;
        if (in_array('*', $target['permissions'], true)) return false;
        foreach ($target['permissions'] as $p) {
            if (!in_array($p, $mine['permissions'], true)) return false;
        }
        // Eigene Rolle nur für einzelne Tabellen: Zielrolle muss ebenso eingeschränkt sein (und nicht auf andere Tabellen)
        if (is_array($mine['tables'])) {
            $scoped = array_filter($target['permissions'], fn($p) => (str_starts_with($p, 'data.') && $p !== 'data.schema') || str_starts_with($p, 'requests.'));
            if ($scoped && ($target['tables'] === null || array_diff($target['tables'], $mine['tables']))) return false;
        }
        return true;
    }

    /** Rollen, in die $mine einladen darf: [key => Name] */
    public static function roleOptions(?array $mine): array
    {
        $out = [];
        foreach (Permissions::roles() as $k => $r) {
            if (self::assignable($r, $mine)) $out[$k] = $r['name'];
        }
        return $out;
    }

    // ================================================================= Anlegen, erneut senden, zurückziehen

    /**
     * Einladung anlegen (ohne Versand).
     * @param array{email:string, name?:string, role:string, message?:string, locale?:string} $in
     * @param ?array $by einladendes Konto (null = Kommandozeile)
     * @return array{id:int, token:string}|array<string,string> Einladung oder Fehler je Feld
     */
    public static function create(array $in, ?array $by, ?array $byRole): array
    {
        $db = app()->db;
        $email = strtolower(trim((string) ($in['email'] ?? '')));
        $name = self::clean((string) ($in['name'] ?? ''), self::NAME_MAX);
        $message = self::cleanMessage((string) ($in['message'] ?? ''));
        $role = Permissions::role((string) ($in['role'] ?? ''));
        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
            $errors['email'] = __('Ungültige E-Mail-Adresse.');
        } elseif ($db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ?', [$email])) {
            $errors['email'] = __('Diese E-Mail-Adresse ist bereits registriert.');
        } elseif ($db->fetchValue('SELECT COUNT(*) FROM user_invites WHERE email = ? AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > ?', [$email, time()])) {
            $errors['email'] = __('Für diese Adresse gibt es bereits eine offene Einladung – dort „Erneut senden“ wählen.');
        }
        if (($role['key'] ?? '') === 'network') {
            $errors['role'] = __('Netzwerk-Konten werden in der Netzwerk-Verwaltung angelegt.');
        } elseif (!$role) {
            $errors['role'] = __('Unbekannte Rolle.');
        } elseif (!self::assignable($role, $byRole)) {
            $errors['role'] = __('In diese Rolle können Sie nicht einladen – sie hat mehr Rechte als Ihre eigene.');
        }
        if (mb_strlen(trim((string) ($in['message'] ?? ''))) > self::MESSAGE_MAX) {
            $errors['message'] = __('Die Nachricht darf höchstens {n} Zeichen lang sein.', ['n' => self::MESSAGE_MAX]);
        }
        if ($errors) return $errors;
        $locale = (string) ($in['locale'] ?? '');
        if (!array_key_exists($locale, I18n::available())) $locale = (string) (app()->settings->get('sys.admin_locale') ?: I18n::SOURCE);
        // Abgelaufene oder zurückgezogene Einladungen derselben Adresse aufräumen (nur eine Zeile je offener Einladung)
        $db->query('DELETE FROM user_invites WHERE email = ? AND accepted_at IS NULL', [$email]);
        $token = self::newToken();
        $id = $db->insert('user_invites', ['token_hash' => self::hash($token), 'email' => $email, 'name' => $name !== '' ? $name : null,
            'role' => $role['key'], 'message' => $message !== '' ? $message : null, 'locale' => $locale,
            'invited_by' => $by ? (int) $by['id'] : null, 'invited_by_name' => $by ? (string) ($by['name'] ?: $by['email']) : null,
            'created_at' => now(), 'expires_at' => time() + self::days() * 86400]);
        return ['id' => $id, 'token' => $token];
    }

    /** Neuer Token und neue Frist (der alte Link gilt nicht mehr) → Token oder null */
    public static function renew(int $id): ?string
    {
        $inv = self::get($id);
        if (!$inv || $inv['accepted_at'] || $inv['revoked_at']) return null;
        $token = self::newToken();
        app()->db->update('user_invites', ['token_hash' => self::hash($token), 'expires_at' => time() + self::days() * 86400], 'id = :id', ['id' => $id]);
        return $token;
    }

    /** Zurückziehen: Token ungültig (Zeile bleibt für das Protokoll bis zur nächsten Einladung derselben Adresse) */
    public static function revoke(int $id): ?array
    {
        $inv = self::get($id);
        if (!$inv || $inv['accepted_at'] || $inv['revoked_at']) return null;
        app()->db->update('user_invites', ['revoked_at' => now(), 'token_hash' => 'revoked-' . bin2hex(random_bytes(16))], 'id = :id', ['id' => $id]);
        return $inv;
    }

    public static function get(int $id): ?array
    {
        try {
            return app()->db->fetch('SELECT * FROM user_invites WHERE id = ?', [$id]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Offene Einladungen (auch abgelaufene, bis sie erneut gesendet oder zurückgezogen werden), neueste zuerst */
    public static function open(): array
    {
        try {
            $rows = app()->db->fetchAll('SELECT * FROM user_invites WHERE accepted_at IS NULL AND revoked_at IS NULL ORDER BY id DESC');
        } catch (\Throwable) {
            return [];   // Tabelle fehlt (noch nicht migriert)
        }
        foreach ($rows as &$r) $r['expired'] = (int) $r['expires_at'] <= time();
        return $rows;
    }

    // ================================================================= Annehmen

    /** Einladung zum Token – nur, wenn sie noch angenommen werden kann (sonst null; keine Unterscheidung nach außen) */
    public static function find(string $token): ?array
    {
        if (!self::wellFormed($token)) return null;
        try {
            $inv = app()->db->fetch('SELECT * FROM user_invites WHERE token_hash = ?', [self::hash($token)]);
        } catch (\Throwable) {
            return null;
        }
        return $inv && self::usable($inv) ? $inv : null;
    }

    /** Noch annehmbar? (Frist, einmalig, nicht zurückgezogen, Rolle vorhanden, Adresse frei, einladendes Konto aktiv) */
    public static function usable(array $inv): bool
    {
        if ($inv['accepted_at'] || $inv['revoked_at'] || (int) $inv['expires_at'] <= time()) return false;
        $role = Permissions::role((string) $inv['role']);
        if (!$role || $role['key'] === 'network') return false;
        $db = app()->db;
        if ($db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ?', [strtolower((string) $inv['email'])])) return false;
        if ($inv['invited_by'] !== null) {
            $by = $db->fetch('SELECT disabled FROM users WHERE id = ?', [(int) $inv['invited_by']]);
            if (!$by || (int) $by['disabled']) return false;
        }
        return true;
    }

    /**
     * Konto anlegen und Einladung als verwendet markieren – in einer Transaktion; $then (z. B. Passkey speichern) läuft
     * darin und bricht mit einem Fehlertext alles ab. Kein Passwort = Anmeldung nur mit Passkey ('!passkey' ist kein gültiger Hash).
     * @return int|string ID des neuen Kontos oder Fehlertext
     */
    public static function accept(array $inv, string $name, ?string $password, string $method, ?int $uid = null, ?callable $then = null): int|string
    {
        $db = app()->db;
        try {
            return $db->transaction(function (Database $db) use ($inv, $name, $password, $method, $uid, $then): int|string {
                // Erneut prüfen (gleichzeitige Anfragen): noch dieselbe, unbenutzte Einladung?
                $now = $db->fetch('SELECT * FROM user_invites WHERE id = ?', [(int) $inv['id']]);
                if (!$now || !hash_equals((string) $inv['token_hash'], (string) $now['token_hash']) || !self::usable($now)) {
                    throw new \RuntimeException(__('Diese Einladung ist nicht mehr gültig.'));
                }
                if ($uid !== null && $db->fetchValue('SELECT COUNT(*) FROM users WHERE id = ?', [$uid])) {
                    throw new \RuntimeException(__('Das hat nicht geklappt. Bitte erneut versuchen.'));
                }
                $default = (string) (app()->settings->get('sys.admin_locale') ?: I18n::SOURCE);
                $row = ['email' => strtolower((string) $now['email']), 'name' => $name,
                    'password_hash' => $password !== null && $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : '!passkey',
                    'role' => (string) $now['role'], 'created_at' => now(),
                    'locale' => $now['locale'] && $now['locale'] !== $default ? (string) $now['locale'] : null];
                if ($uid !== null) $row['id'] = $uid;
                $id = $db->insert('users', $row);
                if ($uid !== null) $id = $uid;
                if ($then && is_string($err = $then($id))) throw new \RuntimeException($err);
                $db->update('user_invites', ['accepted_at' => now(), 'user_id' => $id, 'method' => $method,
                    'token_hash' => 'used-' . bin2hex(random_bytes(16))], 'id = :id', ['id' => (int) $now['id']]);
                return $id;
            });
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        } catch (\Throwable $e) {
            error_log('[invite] accept: ' . $e->getMessage());
            return __('Das hat nicht geklappt. Bitte erneut versuchen.');
        }
    }

    /**
     * ID des nächsten Kontos – für einen Passkey vor dem Anlegen (die Benutzerkennung im Passkey hängt an der ID, Mfa::handle).
     * Immer höher als jede je vergebene ID (keine Wiederverwendung gelöschter Konten); accept() prüft, ob sie noch frei ist.
     */
    public static function nextUserId(Database $db): int
    {
        $max = (int) $db->fetchValue('SELECT MAX(id) FROM users');
        try {
            $max = max($max, (int) $db->fetchValue('SELECT MAX(user_id) FROM user_passkeys'));
        } catch (\Throwable) {
        }
        try {
            $max = $db->driver === 'mysql'
                ? max($max, (int) $db->fetchValue("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'") - 1)
                : max($max, (int) $db->fetchValue("SELECT seq FROM sqlite_sequence WHERE name = 'users'"));
        } catch (\Throwable) {
        }
        return $max + 1;
    }

    // ================================================================= E-Mail

    /**
     * Einladung versenden (neuer Token bei „Erneut senden“ über renew()).
     * @return array{delivered:bool, error:?string} delivered = an die Adresse übergeben; sonst Link selbst weitergeben
     */
    public static function send(array $inv, string $token): array
    {
        [$subject, $text, $html, $inline] = self::mail($inv, $token);
        $err = Mailer::send($subject, $text, [(string) $inv['email']], ['html' => $html, 'inline' => $inline]);
        $delivered = $err === null && Mailer::$delivered;
        app()->db->query('UPDATE user_invites SET sends = sends + 1, sent_at = ? WHERE id = ?', [$delivered ? now() : null, (int) $inv['id']]);
        return ['delivered' => $delivered, 'error' => $err];
    }

    /**
     * Betreff, Text, HTML und eingebettete Bilder in der Sprache der Einladung.
     * @return array{0:string, 1:string, 2:string, 3:array<string,array{path:string, type:string}>}
     */
    public static function mail(array $inv, string $token): array
    {
        $prev = I18n::locale();
        $lang = (string) ($inv['locale'] ?: (app()->settings->get('sys.admin_locale') ?: I18n::SOURCE));
        I18n::setLocale($lang);
        try {
            $inline = [];
            $logo = null;
            if (AppIcons::ensure() && is_file($f = AppIcons::dir() . '/icon-192.png')) {
                $inline['logo'] = ['path' => $f, 'type' => 'image/png'];
                $logo = 'cid:logo';
            }
            $brand = self::brand();
            $role = Permissions::role((string) $inv['role']);
            $pol = Mfa::policy();
            $vars = [
                'lang' => $lang,
                'site' => site_name(),
                'siteUrl' => self::base() . rtrim(url('/'), '/'),
                'logo' => $logo,
                'brand' => $brand['light'],
                'brandDark' => $brand['dark'],
                'onBrand' => $brand['on'],
                'onBrandDark' => $brand['onDark'],
                'inviter' => (string) ($inv['invited_by_name'] ?: __('Die Administration')),
                'role' => (string) ($role['name'] ?? $inv['role']),
                'name' => (string) ($inv['name'] ?? ''),
                'message' => (string) ($inv['message'] ?? ''),
                'url' => self::url($token),
                'expires' => self::date((int) $inv['expires_at'], $lang),
                'passkeys' => $pol['passkey'],
                'passwordless' => $pol['passwordless'],
            ];
            $vars['siteHost'] = (string) (parse_url($vars['siteUrl'], PHP_URL_HOST) ?: $vars['siteUrl']);
            $subject = __('Einladung zu {site}', ['site' => $vars['site']]);
            $html = Theme::capture(self::template('invitation.php'), $vars + ['subject' => $subject]);
            $text = Theme::capture(self::template('invitation.txt.php'), $vars + ['subject' => $subject]);
            return [$subject, trim($text) . "\n", $html, $inline];
        } finally {
            I18n::setLocale($prev);
        }
    }

    /** Vorlage: Kit (themes/{kit}/templates/mail/…) vor Core (app/Admin/views/mail/…) */
    public static function template(string $file): string
    {
        $kit = app()->theme->path . '/templates/mail/' . $file;
        return is_file($kit) ? $kit : ROOT . '/app/Admin/views/mail/' . $file;
    }

    /**
     * Markenfarbe: Token „accent“ (bzw. primary/brand) des Kits aus dem Style-Editor, sonst Farbe der App (PWA/Icon).
     * Schrift auf der Farbe: Weiß oder fast Schwarz – je nach Kontrast.
     * @return array{light:string, dark:string, on:string, onDark:string}
     */
    public static function brand(): array
    {
        $light = $dark = null;
        $tokens = Design::tokens();
        foreach (['accent', 'primary', 'brand', 'akzent'] as $n) {
            if (isset($tokens[$n]) && ($tokens[$n]['type'] ?? 'color') === 'color' && ($c = Design::color(Design::get($n)))) {
                $light = $c;
                $dark = Design::color(Design::values()[$n . '@dark'] ?? null);
                break;
            }
        }
        $light ??= Design::color(AppIcons::appInfo()['theme_color']) ?? Design::color(AppIcons::config()['bg']) ?? '#314164';
        // Auf Weiß muss die Farbe als Link/Button lesbar sein – sonst die Schrift des Buttons dunkel, Links in Schwarz
        $dark ??= $light;
        $on = fn(string $c) => Design::contrast($c, '#FFFFFF') >= 4.5 ? '#FFFFFF' : '#111111';
        return ['light' => $light, 'dark' => $dark, 'on' => $on($light), 'onDark' => $on($dark)];
    }

    /** Datum + Uhrzeit in der Sprache der Einladung */
    public static function date(int $ts, string $lang): string
    {
        if (class_exists(\IntlDateFormatter::class)) {
            $f = new \IntlDateFormatter($lang === 'de' ? 'de_DE' : $lang, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE,
                date_default_timezone_get(), null, $lang === 'de' ? "d. MMMM y, HH:mm 'Uhr'" : 'd MMMM y, HH:mm');
            $out = $f->format($ts);
            if (is_string($out)) return $out;
        }
        return date($lang === 'de' ? 'd.m.Y, H:i \U\h\r' : 'j F Y, H:i', $ts);
    }

    // ================================================================= Hilfen

    public static function clean(string $s, int $max): string
    {
        $s = trim(preg_replace('~[\x00-\x1f\x7f]+~u', ' ', $s) ?? '');
        return mb_substr($s, 0, $max);
    }

    /** Persönliche Nachricht: reiner Text, Zeilenumbrüche bleiben (höchstens zwei Leerzeilen), keine Steuerzeichen */
    public static function cleanMessage(string $s): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        $s = preg_replace('~[\x00-\x09\x0b-\x1f\x7f]+~u', ' ', $s) ?? '';
        $s = preg_replace("~\n{3,}~", "\n\n", $s) ?? '';
        return mb_substr(trim($s), 0, self::MESSAGE_MAX);
    }

    /** Protokoll wie andere Anmelde-Ereignisse (Netzwerk-Protokoll mit Kürzel der Website) */
    public static function log(string $action, ?string $by, string $detail): void
    {
        try {
            Network\Network::log($action, site()->key, $by, $detail);
        } catch (\Throwable) {
        }
    }

    // ================================================================= Selbsttest

    /** Selbsttest (Konsole invites:selftest): Token, Hash, Form, Rollenprüfung, Nachricht – ohne Datenbankänderung */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $t = self::newToken();
        $eq('Token 43 Zeichen base64url', self::wellFormed($t), true);
        $eq('Token zufällig', $t === self::newToken(), false);
        $eq('Hash SHA-256 (64 hex)', (bool) preg_match('~^[0-9a-f]{64}$~', self::hash($t)), true);
        $eq('Hash ist nicht der Token', str_contains(self::hash($t), $t), false);
        $eq('Hash stabil', self::hash($t), self::hash($t));
        $eq('Hash an Website gebunden', self::hash($t) === hash('sha256', $t), false);
        foreach (['', 'abc', str_repeat('a', 42), str_repeat('a', 44), str_repeat('a', 42) . '/', '../' . str_repeat('a', 40), str_repeat('a', 42) . '='] as $bad) {
            $eq('Token ungültig: ' . $bad, self::wellFormed($bad), false);
        }
        $eq('Unbekannter Token → null', self::find(self::newToken()), null);
        $eq('Kaputter Token → null', self::find("x' OR 1=1 --"), null);

        $role = fn(array $p, ?array $tables = null, string $k = 'x') => ['key' => $k, 'name' => $k, 'description' => '', 'permissions' => $p, 'tables' => $tables, 'builtin' => false];
        $admin = $role(['*'], null, 'admin');
        $editor = $role(['pages.edit', 'pages.publish', 'data.edit'], null, 'editor');
        $eq('Admin → Redaktion', self::assignable($editor, $admin), true);
        $eq('Admin → Admin', self::assignable($admin, $admin), true);
        $eq('Redaktion → Admin', self::assignable($admin, $editor), false);
        $eq('Redaktion → Autor (Teilmenge)', self::assignable($role(['pages.edit']), $editor), true);
        $eq('Redaktion → mehr Rechte', self::assignable($role(['pages.edit', 'users.manage']), $editor), false);
        $eq('Nie Netzwerk', self::assignable($role(['*'], null, 'network'), $admin), false);
        $eq('Kommandozeile → Redaktion', self::assignable($editor, null), true);
        $scoped = $role(['data.edit'], ['news']);
        $eq('Tabellen: gleiche Auswahl', self::assignable($role(['data.edit'], ['news']), $scoped), true);
        $eq('Tabellen: alle statt Auswahl', self::assignable($role(['data.edit']), $scoped), false);
        $eq('Tabellen: andere Tabelle', self::assignable($role(['data.edit'], ['termine']), $scoped), false);

        $eq('Nachricht: Steuerzeichen, Zeilen', self::cleanMessage("Hallo\r\n\r\n\r\n\r\nTeam\x07!"), "Hallo\n\nTeam !");
        $eq('Nachricht: Länge', mb_strlen(self::cleanMessage(str_repeat('ä', 900))), self::MESSAGE_MAX);
        $eq('Name: eine Zeile', self::clean("Anna\nMüller", self::NAME_MAX), 'Anna Müller');
        $eq('Gültigkeit 1–60 Tage', self::days() >= 1 && self::days() <= 60, true);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
