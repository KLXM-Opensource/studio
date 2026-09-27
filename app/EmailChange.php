<?php
declare(strict_types=1);

namespace Core;

/**
 * Anmeldedaten selbst ändern: E-Mail-Adresse mit Bestätigung, Hinweis-E-Mails zu Adresse und Passwort (Konto → „Anmeldedaten“).
 *
 * Ablauf E-Mail-Adresse (AccountController, CLI user:email):
 * - Anfrage im Konto mit aktuellem Passwort (Konten ohne Passwort: frische Bestätigung per Passkey, Mfa::recentAuth()).
 * - Tabelle user_email_changes der Website (Database::migrate), höchstens eine offene Änderung je Konto.
 * - Zwei Tokens (je 32 Zufallsbytes, base64url): „bestätigen“ geht an die NEUE Adresse, „abbrechen“ an die BISHERIGE.
 *   Gespeichert nur als SHA-256 mit Zweck und Kürzel der Website → gilt nur hier; Vergleich zusätzlich mit hash_equals.
 *   Gültig 24 Stunden (config 'email_change_hours', 1–72), einmal verwendbar.
 * - Adresse schon vergeben (anderes Konto dieser Website): nach außen dieselbe Antwort und dieselbe offene Änderung;
 *   die Inhaberin der Adresse bekommt statt des Bestätigungslinks einen Hinweis, der Bestätigungslink existiert nicht
 *   (conflict = 1). Keine Rückschlüsse auf vorhandene Konten.
 * - Wirksam erst mit dem Link aus der E-Mail an die neue Adresse (Token allein genügt, keine Anmeldung nötig – die Änderung
 *   muss aber noch offen, unverändert und gültig sein; die Adresse wird dabei erneut auf Eindeutigkeit geprüft).
 *   Danach: auth_ver + 1 (andere Sitzungen enden, die eigene bleibt), Protokoll, Bestätigung an beide Adressen.
 * - „Das war ich nicht“ (Link an die bisherige Adresse) bricht ab und beendet alle Sitzungen des Kontos (auth_ver + 1).
 * - Passkeys bleiben gültig: Sie hängen an der Konto-ID (Passkeys::userHandle), nicht an der E-Mail-Adresse.
 * - Netzwerk-Konten ändern die Adresse auf der Netzwerk-Website; Schatten-Konten der anderen Websites werden sofort
 *   nachgezogen (Network::syncShadowEmail), spätestens bei der nächsten Anmeldung (Network::loginShadow).
 * - E-Mails: HTML + Text, Vorlage app/Admin/views/mail/account(.txt).php (im Kit überschreibbar wie die Einladung),
 *   Sprache des Kontos.
 */
final class EmailChange
{
    public const HOURS = 24;
    public const PER_HOUR = 5;       // Änderungsanfragen (inkl. „Erneut senden“) je Konto und Stunde

    /** Selbsttest: keine E-Mails, kein Protokoll */
    private static bool $mute = false;

    // ================================================================= Tabelle

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS user_email_changes (id $pk, user_id INT NOT NULL, old_email VARCHAR(191) NOT NULL,
            new_email VARCHAR(191) NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, cancel_hash VARCHAR(64) NOT NULL UNIQUE,
            conflict INT NOT NULL DEFAULT 0, created_at VARCHAR(25) NOT NULL, expires_at INT NOT NULL, sent_at VARCHAR(25) NULL,
            sends INT NOT NULL DEFAULT 0, confirmed_at VARCHAR(25) NULL, cancelled_at VARCHAR(25) NULL, cancelled_via VARCHAR(10) NULL)$tail");
        if (!$my) $db->pdo->exec('CREATE INDEX IF NOT EXISTS user_email_changes_user ON user_email_changes (user_id)');
    }

    // ================================================================= Token

    /** Gültigkeit in Stunden (config 'email_change_hours') */
    public static function hours(): int
    {
        return max(1, min(72, (int) app()->config->get('email_change_hours', self::HOURS)));
    }

    public static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /** Hash des Tokens – an Zweck (confirm|cancel) und Website gebunden */
    public static function hash(string $kind, string $token): string
    {
        return hash('sha256', 'email-change|' . $kind . '|' . site()->key . '|' . $token);
    }

    public static function wellFormed(string $token): bool
    {
        return (bool) preg_match('~^[A-Za-z0-9_-]{43}$~', $token);
    }

    public static function url(string $kind, string $token): string
    {
        return Invites::base() . url(($kind === 'cancel' ? '/admin/konto/email-abbrechen/' : '/admin/konto/email/') . $token);
    }

    /** Unbrauchbarer Platzhalter für Hash-Spalten (UNIQUE, nie ein gültiger SHA-256-Hex) */
    private static function dead(string $why): string
    {
        return $why . '-' . bin2hex(random_bytes(16));
    }

    // ================================================================= Prüfungen

    /** Gültige, freie Adresse? @return string|null Fehlertext (ohne Auskunft, ob die Adresse vergeben ist) */
    public static function invalid(string $email, string $current): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191 || preg_match('~[\s<>"]~', $email)) {
            return __('Ungültige E-Mail-Adresse.');
        }
        if (strtolower($email) === strtolower($current)) return __('Das ist bereits Ihre E-Mail-Adresse.');
        return null;
    }

    /** Adresse von einem anderen Konto dieser Website belegt? */
    public static function taken(Database $db, string $email, int $uid): bool
    {
        return (bool) $db->fetchValue('SELECT COUNT(*) FROM users WHERE LOWER(email) = ? AND id != ?', [strtolower($email), $uid]);
    }

    /** Hat das Konto ein eigenes Passwort? ('!passkey' = nur Passkey aus einer Einladung, '!network' = Schatten-Konto) */
    public static function hasPassword(array $row): bool
    {
        $h = (string) ($row['password_hash'] ?? '');
        return $h !== '' && $h[0] !== '!';
    }

    // ================================================================= Anfrage, erneut senden, abbrechen (Konto)

    /**
     * Änderung anlegen und E-Mails senden. Vorher geprüft: Anmeldung, Passwort/Passkey, Begrenzung, invalid().
     * @return array{delivered:bool, error:?string} delivered = E-Mail an die neue Adresse übergeben
     */
    public static function request(array $user, string $newEmail): array
    {
        $db = app()->db;
        $new = strtolower(trim($newEmail));
        $uid = (int) $user['id'];
        $confirm = self::newToken();
        $cancel = self::newToken();
        $conflict = self::taken($db, $new, $uid);
        // Höchstens eine offene Änderung je Konto: frühere (offene wie erledigte) entfernen
        $db->query('DELETE FROM user_email_changes WHERE user_id = ?', [$uid]);
        $id = $db->insert('user_email_changes', ['user_id' => $uid, 'old_email' => strtolower((string) $user['email']), 'new_email' => $new,
            // Adresse vergeben: kein Bestätigungslink (der gespeicherte Hash gehört zu keinem Token)
            'token_hash' => $conflict ? self::dead('conflict') : self::hash('confirm', $confirm), 'cancel_hash' => self::hash('cancel', $cancel),
            'conflict' => $conflict ? 1 : 0, 'created_at' => now(), 'expires_at' => time() + self::hours() * 3600]);
        $row = self::get($id);
        $sent = self::sendConfirm($row, $user, $confirm);
        self::mail($row['old_email'], $user, 'notice', ['new' => $new, 'old' => $row['old_email'],
            'expires' => (int) $row['expires_at'], 'url' => self::url('cancel', $cancel)]);
        self::log('user.email-request', (string) $user['email'], $row['old_email'] . ' → ' . $new . ($sent['delivered'] ? '' : ' · ohne E-Mail'));
        return $sent;
    }

    /** Bestätigung (bzw. bei vergebener Adresse den Hinweis) an die neue Adresse */
    private static function sendConfirm(array $row, array $user, string $token): array
    {
        $to = (string) $row['new_email'];
        $res = (int) $row['conflict']
            ? self::mail($to, ['name' => '', 'locale' => $user['locale'] ?? null], 'taken', ['new' => $to])
            : self::mail($to, $user, 'confirm', ['new' => $to, 'old' => (string) $row['old_email'], 'expires' => (int) $row['expires_at'], 'url' => self::url('confirm', $token)]);
        app()->db->query('UPDATE user_email_changes SET sends = sends + 1, sent_at = ? WHERE id = ?', [$res['delivered'] ? now() : null, (int) $row['id']]);
        return $res;
    }

    /** Offene Änderung des Kontos (auch abgelaufen – dann mit expired = true), sonst null */
    public static function pending(int $uid): ?array
    {
        try {
            $row = app()->db->fetch('SELECT * FROM user_email_changes WHERE user_id = ? AND confirmed_at IS NULL AND cancelled_at IS NULL ORDER BY id DESC LIMIT 1', [$uid]);
        } catch (\Throwable) {
            return null;
        }
        if (!$row) return null;
        $row['expired'] = (int) $row['expires_at'] <= time();
        return $row;
    }

    /**
     * „Erneut senden“: neuer Bestätigungslink (der vorige gilt nicht mehr), neue Frist. Der Abbrechen-Link an die bisherige
     * Adresse bleibt gültig. @return array{delivered:bool, error:?string}|null null = keine offene Änderung
     */
    public static function resend(array $user): ?array
    {
        $p = self::pending((int) $user['id']);
        if (!$p) return null;
        $token = self::newToken();
        app()->db->update('user_email_changes', ['token_hash' => (int) $p['conflict'] ? self::dead('conflict') : self::hash('confirm', $token),
            'expires_at' => time() + self::hours() * 3600], 'id = :id', ['id' => (int) $p['id']]);
        $row = self::get((int) $p['id']);
        $sent = self::sendConfirm($row, $user, $token);
        self::log('user.email-resend', (string) $user['email'], (string) $row['new_email'] . ($sent['delivered'] ? '' : ' · ohne E-Mail'));
        return $sent;
    }

    /** Abbrechen im Konto (angemeldet) – Links werden ungültig */
    public static function cancelOwn(array $user): bool
    {
        $p = self::pending((int) $user['id']);
        if (!$p) return false;
        self::close((int) $p['id'], 'account');
        self::log('user.email-cancel', (string) $user['email'], (string) $p['new_email'] . ' · Konto');
        return true;
    }

    private static function close(int $id, string $via): void
    {
        app()->db->update('user_email_changes', ['cancelled_at' => now(), 'cancelled_via' => $via,
            'token_hash' => self::dead('closed'), 'cancel_hash' => self::dead('closed')], 'id = :id', ['id' => $id]);
    }

    public static function get(int $id): ?array
    {
        return app()->db->fetch('SELECT * FROM user_email_changes WHERE id = ?', [$id]);
    }

    // ================================================================= Links aus den E-Mails

    /**
     * Änderung zum Token ($kind confirm|cancel) – nur, wenn sie noch gilt; sonst null (keine Unterscheidung nach außen:
     * unbekannt, abgelaufen, benutzt, abgebrochen, Konto gesperrt/gelöscht, Adresse inzwischen anders).
     */
    public static function find(string $kind, string $token): ?array
    {
        if (!in_array($kind, ['confirm', 'cancel'], true) || !self::wellFormed($token)) return null;
        $col = $kind === 'cancel' ? 'cancel_hash' : 'token_hash';
        $hash = self::hash($kind, $token);
        try {
            $row = app()->db->fetch("SELECT * FROM user_email_changes WHERE $col = ?", [$hash]);
        } catch (\Throwable) {
            return null;
        }
        if (!$row || !hash_equals((string) $row[$col], $hash)) return null;
        if ($kind === 'confirm' && (int) $row['conflict']) return null;
        return self::usable($row) ? $row : null;
    }

    /** Noch offen und gültig? (Frist, einmalig, Konto vorhanden und nicht gesperrt, Adresse des Kontos unverändert) */
    public static function usable(array $row): bool
    {
        if ($row['confirmed_at'] || $row['cancelled_at'] || (int) $row['expires_at'] <= time()) return false;
        $u = app()->db->fetch('SELECT email, disabled, network_uid FROM users WHERE id = ?', [(int) $row['user_id']]);
        return $u && !(int) $u['disabled'] && empty($u['network_uid']) && strtolower((string) $u['email']) === strtolower((string) $row['old_email']);
    }

    /**
     * Neue Adresse übernehmen (Link aus der E-Mail an die neue Adresse). Prüft in einer Transaktion erneut, ob die Änderung
     * noch gilt und die Adresse frei ist. @return array|null Konto nach der Änderung oder null (nicht mehr gültig)
     */
    public static function confirm(array $row): ?array
    {
        $db = app()->db;
        try {
            $user = $db->transaction(function (Database $db) use ($row): ?array {
                $now = $db->fetch('SELECT * FROM user_email_changes WHERE id = ?', [(int) $row['id']]);
                if (!$now || !hash_equals((string) $row['token_hash'], (string) $now['token_hash']) || (int) $now['conflict'] || !self::usable($now)) return null;
                $uid = (int) $now['user_id'];
                if (self::taken($db, (string) $now['new_email'], $uid)) {
                    // Inzwischen vergeben: Änderung verfällt (Anzeige wie ein ungültiger Link)
                    $db->update('user_email_changes', ['cancelled_at' => now(), 'cancelled_via' => 'taken',
                        'token_hash' => self::dead('closed'), 'cancel_hash' => self::dead('closed')], 'id = :id', ['id' => (int) $now['id']]);
                    return null;
                }
                $db->query('UPDATE users SET email = ?, auth_ver = auth_ver + 1 WHERE id = ?', [(string) $now['new_email'], $uid]);
                $db->update('user_email_changes', ['confirmed_at' => now(), 'token_hash' => self::dead('used'), 'cancel_hash' => self::dead('used')],
                    'id = :id', ['id' => (int) $now['id']]);
                return $db->fetch('SELECT * FROM users WHERE id = ?', [$uid]);
            });
        } catch (\Throwable $e) {
            error_log('[email-change] confirm: ' . $e->getMessage());
            return null;
        }
        if (!$user) return null;
        self::keepOwnSession((int) $user['id']);
        $sync = self::syncNetwork($user);
        self::log('user.email', (string) $user['email'], $row['old_email'] . ' → ' . $user['email'] . $sync);
        foreach ([(string) $row['old_email'], (string) $user['email']] as $to) {
            self::mail($to, $user, 'changed', ['old' => (string) $row['old_email'], 'new' => (string) $user['email'], 'toOld' => $to === $row['old_email']]);
        }
        return $user;
    }

    /** „Das war ich nicht“ (Link an die bisherige Adresse): abbrechen und alle Sitzungen des Kontos beenden */
    public static function cancelByToken(array $row): bool
    {
        $db = app()->db;
        $n = $db->query('UPDATE user_email_changes SET cancelled_at = ?, cancelled_via = ?, token_hash = ?, cancel_hash = ?
            WHERE id = ? AND cancel_hash = ? AND confirmed_at IS NULL AND cancelled_at IS NULL',
            [now(), 'mail', self::dead('closed'), self::dead('closed'), (int) $row['id'], (string) $row['cancel_hash']])->rowCount();
        if ($n !== 1) return false;
        $db->query('UPDATE users SET auth_ver = auth_ver + 1 WHERE id = ?', [(int) $row['user_id']]);
        self::log('user.email-cancel', (string) $row['old_email'], (string) $row['new_email'] . ' · E-Mail-Link, Sitzungen beendet');
        return true;
    }

    // ================================================================= Administration, Kommandozeile

    /**
     * Adresse direkt ändern (Benutzer & Rollen, CLI user:email) – ohne Bestätigung, mit Hinweis an beide Adressen.
     * Offene Änderungen verfallen; andere Sitzungen des Kontos enden (auth_ver). @return string|null Fehlertext
     */
    public static function adminChange(array $target, string $newEmail, ?string $by): ?string
    {
        $db = app()->db;
        $new = strtolower(trim($newEmail));
        if ($err = self::invalid($new, (string) $target['email'])) return $err;
        if (self::taken($db, $new, (int) $target['id'])) return __('Diese E-Mail-Adresse ist bereits registriert.');
        $old = strtolower((string) $target['email']);
        $db->query('UPDATE users SET email = ?, auth_ver = auth_ver + 1 WHERE id = ?', [$new, (int) $target['id']]);
        try {
            foreach ($db->fetchAll('SELECT id FROM user_email_changes WHERE user_id = ? AND confirmed_at IS NULL AND cancelled_at IS NULL', [(int) $target['id']]) as $p) {
                self::close((int) $p['id'], 'admin');
            }
        } catch (\Throwable) {
        }
        $user = $db->fetch('SELECT * FROM users WHERE id = ?', [(int) $target['id']]);
        $sync = self::syncNetwork($user);
        self::log('user.email-admin', $by ?? 'cli', $old . ' → ' . $new . $sync);
        foreach ([$old, $new] as $to) {
            self::mail($to, $user, 'admin', ['old' => $old, 'new' => $new, 'toOld' => $to === $old, 'by' => $by]);
        }
        return null;
    }

    // ================================================================= Passwort

    /** Hinweis an das Konto nach jeder Passwortänderung (auch erstes Passwort eines Passkey-Kontos, CLI user:password) */
    public static function passwordChanged(array $user, bool $first = false, ?string $by = null): void
    {
        self::mail((string) $user['email'], $user, 'password', ['first' => $first, 'by' => $by]);
    }

    // ================================================================= Hilfen

    /** Eigene Sitzung behalten, wenn das geänderte Konto hier angemeldet ist (auth_ver neu einlesen) */
    private static function keepOwnSession(int $uid): void
    {
        $s = app()->session;
        if (PHP_SAPI === 'cli' || (int) $s->get('uid', 0) !== $uid || !hash_equals(Auth::siteMark(), (string) $s->get('site', ''))) return;
        $s->regenerate();
        $s->set('auth_ver', (int) app()->db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [$uid]));
    }

    /** Netzwerk-Konto auf der Netzwerk-Website: Schatten-Konten der anderen Websites nachziehen → Zusatz fürs Protokoll */
    private static function syncNetwork(array $user): string
    {
        if (($user['role'] ?? '') !== 'network' || !Network\Network::isNetworkSite()) return '';
        try {
            [$done, $skipped] = Network\Network::syncShadowEmail((int) $user['id'], (string) $user['email']);
            return ' · Netzwerk: ' . count($done) . ' Website(s)' . ($skipped ? ', offen: ' . implode(', ', $skipped) : '');
        } catch (\Throwable $e) {
            error_log('[email-change] network sync: ' . $e->getMessage());
            return ' · Netzwerk: bei nächster Anmeldung';
        }
    }

    public static function log(string $action, ?string $by, string $detail): void
    {
        if (self::$mute) return;
        try {
            Network\Network::log($action, site()->key, $by, $detail);
        } catch (\Throwable) {
        }
    }

    // ================================================================= E-Mail

    /**
     * E-Mail zu einem Ereignis ($kind confirm|notice|taken|changed|admin|password|reset|reset-network|reset-done) in der Sprache des Kontos.
     * @return array{delivered:bool, error:?string}
     */
    public static function mail(string $to, array $user, string $kind, array $v): array
    {
        [$subject, $text, $html, $inline] = self::compose($user, $kind, $v);
        if (self::$mute) return ['delivered' => false, 'error' => null];
        $err = Mailer::send($subject, $text, [$to], ['html' => $html, 'inline' => $inline]);
        return ['delivered' => $err === null && Mailer::$delivered, 'error' => $err];
    }

    /** @return array{0:string, 1:string, 2:string, 3:array} Betreff, Text, HTML, eingebettete Bilder */
    public static function compose(array $user, string $kind, array $v): array
    {
        $prev = I18n::locale();
        $lang = (string) (($user['locale'] ?? null) ?: (app()->settings->get('sys.admin_locale') ?: I18n::SOURCE));
        if (!array_key_exists($lang, I18n::available())) $lang = I18n::SOURCE;
        I18n::setLocale($lang);
        try {
            $inline = [];
            $logo = null;
            if (AppIcons::ensure() && is_file($f = AppIcons::dir() . '/icon-192.png')) {
                $inline['logo'] = ['path' => $f, 'type' => 'image/png'];
                $logo = 'cid:logo';
            }
            $site = site_name();
            $name = trim((string) ($user['name'] ?? ''));
            $date = fn(int $ts) => Invites::date($ts, $lang);
            $facts = [];
            $cta = null;
            $after = [];
            $foot = __('Diese E-Mail wurde automatisch zu Ihrem Konto versendet.');
            switch ($kind) {
                case 'confirm':
                    $subject = __('Neue E-Mail-Adresse bestätigen – {site}', ['site' => $site]);
                    $title = __('Bitte bestätigen Sie Ihre neue E-Mail-Adresse');
                    $paras = [__('Sie möchten die E-Mail-Adresse Ihres Kontos bei {site} ändern. Die Änderung wird erst wirksam, wenn Sie sie hier bestätigen.', ['site' => $site])];
                    $facts = [__('Bisher') => $v['old'], __('Neu') => $v['new']];
                    $cta = ['label' => __('Neue Adresse bestätigen'), 'url' => $v['url'], 'note' => __('Der Link gilt bis {date} und nur einmal.', ['date' => $date((int) $v['expires'])])];
                    $foot = __('Sie haben das nicht angefordert? Dann ignorieren Sie diese E-Mail – die Adresse bleibt unverändert.');
                    break;
                case 'taken':
                    $subject = __('Hinweis zu Ihrem Konto bei {site}', ['site' => $site]);
                    $title = __('Diese Adresse hat bereits ein Konto');
                    $paras = [__('Für ein anderes Konto bei {site} wurde angefragt, die E-Mail-Adresse auf {email} zu ändern. Zu dieser Adresse gibt es bereits ein Konto – deshalb wird nichts geändert.', ['site' => $site, 'email' => $v['new']]),
                        __('Sie müssen nichts tun. Wenn Sie zwei Konten zusammenlegen möchten, wenden Sie sich an die Administration der Website.')];
                    break;
                case 'notice':
                    $subject = __('Ihre E-Mail-Adresse soll geändert werden – {site}', ['site' => $site]);
                    $title = __('Ihre E-Mail-Adresse soll geändert werden');
                    $paras = [__('Ihre E-Mail-Adresse soll geändert werden auf {email}.', ['email' => $v['new']]),
                        __('Die Änderung wird erst wirksam, wenn der Link in der E-Mail an die neue Adresse bestätigt wird – spätestens bis {date}. Bis dahin melden Sie sich weiter mit dieser Adresse an.', ['date' => $date((int) $v['expires'])])];
                    $facts = [__('Bisher') => $v['old'], __('Neu') => $v['new']];
                    $cta = ['label' => __('Das war ich nicht – Änderung abbrechen'), 'url' => $v['url'], 'note' => __('Beim Abbrechen enden außerdem alle Sitzungen Ihres Kontos. Ändern Sie danach Ihr Passwort.')];
                    $foot = __('Waren Sie das selbst? Dann müssen Sie nichts tun.');
                    break;
                case 'changed':
                    $subject = __('E-Mail-Adresse geändert – {site}', ['site' => $site]);
                    $title = __('Ihre E-Mail-Adresse wurde geändert');
                    $paras = [__('Die E-Mail-Adresse Ihres Kontos bei {site} wurde am {date} geändert. Sie melden sich ab jetzt mit der neuen Adresse an; Ihre Passkeys gelten weiter. Andere Sitzungen Ihres Kontos wurden beendet.', ['site' => $site, 'date' => $date(time())])];
                    $facts = [__('Bisher') => $v['old'], __('Neu') => $v['new']];
                    if (!empty($v['toOld'])) $after[] = __('Diese Nachricht geht auch an Ihre bisherige Adresse. Waren Sie das nicht? Wenden Sie sich bitte sofort an die Administration der Website.');
                    break;
                case 'admin':
                    $subject = __('E-Mail-Adresse geändert – {site}', ['site' => $site]);
                    $title = __('Ihre E-Mail-Adresse wurde geändert');
                    $paras = [__('Die Administration der Website {site} hat die E-Mail-Adresse Ihres Kontos am {date} geändert. Sie melden sich ab jetzt mit der neuen Adresse an; Ihre Passkeys gelten weiter. Bitte melden Sie sich neu an.', ['site' => $site, 'date' => $date(time())])];
                    $facts = [__('Bisher') => $v['old'], __('Neu') => $v['new']];
                    $after[] = __('Haben Sie das nicht erwartet? Wenden Sie sich bitte an die Administration der Website.');
                    break;
                case 'password':
                    $subject = !empty($v['first']) ? __('Passwort festgelegt – {site}', ['site' => $site]) : __('Passwort geändert – {site}', ['site' => $site]);
                    $title = !empty($v['first']) ? __('Sie haben ein Passwort festgelegt') : __('Ihr Passwort wurde geändert');
                    $paras = [!empty($v['first'])
                        ? __('Für Ihr Konto bei {site} wurde am {date} ein Passwort festgelegt. Sie können sich jetzt auch mit E-Mail-Adresse und Passwort anmelden; Ihre Passkeys gelten weiter.', ['site' => $site, 'date' => $date(time())])
                        : (!empty($v['by']) ? __('Das Passwort Ihres Kontos bei {site} wurde am {date} von der Administration neu gesetzt. Andere Sitzungen Ihres Kontos wurden beendet.', ['site' => $site, 'date' => $date(time())])
                            : __('Das Passwort Ihres Kontos bei {site} wurde am {date} geändert. Andere Sitzungen Ihres Kontos wurden beendet.', ['site' => $site, 'date' => $date(time())]))];
                    $facts = [__('Konto') => (string) $user['email']];
                    $after[] = __('Waren Sie das nicht? Wenden Sie sich bitte sofort an die Administration der Website.');
                    break;
                // „Passwort vergessen“ (Core\PasswordReset)
                case 'reset':
                    $subject = __('Passwort zurücksetzen – {site}', ['site' => $site]);
                    $title = __('Neues Passwort festlegen');
                    $paras = [__('Für Ihr Konto bei {site} wurde ein neues Passwort angefordert. Über die Schaltfläche legen Sie es fest.', ['site' => $site]),
                        __('Haben Sie die Zwei-Faktor-Anmeldung oder Passkeys eingerichtet, bleiben sie unverändert – nach dem Passwort fragt die Anmeldung wie gewohnt nach dem zweiten Faktor.')];
                    $facts = [__('Konto') => (string) ($user['email'] ?? '')];
                    $cta = ['label' => __('Neues Passwort festlegen'), 'url' => $v['url'],
                        'note' => __('Der Link gilt {n} Minuten (bis {date}) und nur einmal.', ['n' => (int) ($v['minutes'] ?? 60), 'date' => $date((int) $v['expires'])])];
                    $foot = __('Sie haben das nicht angefordert? Dann ignorieren Sie diese E-Mail – Ihr Passwort bleibt unverändert.');
                    break;
                case 'reset-network':
                    $subject = __('Passwort zurücksetzen – {site}', ['site' => $site]);
                    $title = __('Ihr Konto ist ein Netzwerk-Konto');
                    $paras = [__('Für Ihre Adresse wurde bei {site} ein neues Passwort angefordert. Sie melden sich hier mit einem zentralen Netzwerk-Konto an – dessen Passwort setzen Sie auf der Netzwerk-Website zurück.', ['site' => $site]),
                        __('Öffnen Sie dort „Passwort vergessen?“ und fordern Sie den Link noch einmal an. Das neue Passwort gilt danach auf allen Websites.')];
                    $facts = [__('Netzwerk-Website') => (string) ($v['network'] ?? '')];
                    $cta = ['label' => __('Zur Netzwerk-Website'), 'url' => $v['url'], 'note' => ''];
                    $foot = __('Sie haben das nicht angefordert? Dann ignorieren Sie diese E-Mail – Ihr Passwort bleibt unverändert.');
                    break;
                case 'reset-done':
                    $subject = !empty($v['first']) ? __('Passwort festgelegt – {site}', ['site' => $site]) : __('Passwort geändert – {site}', ['site' => $site]);
                    $title = !empty($v['first']) ? __('Sie haben ein Passwort festgelegt') : __('Ihr Passwort wurde geändert');
                    $paras = [__('Das Passwort Ihres Kontos bei {site} wurde am {date} über „Passwort vergessen“ neu festgelegt. Alle Sitzungen Ihres Kontos wurden beendet; Zwei-Faktor-Anmeldung und Passkeys gelten unverändert weiter.', ['site' => $site, 'date' => $date(time())])];
                    $facts = [__('Konto') => (string) $user['email']];
                    $after[] = __('Waren Sie das nicht? Wenden Sie sich bitte sofort an die Administration der Website.');
                    break;
                default:
                    throw new \InvalidArgumentException('Unbekannte E-Mail: ' . $kind);
            }
            $brand = Invites::brand();
            $siteUrl = Invites::base() . rtrim(url('/'), '/');
            $vars = ['lang' => $lang, 'subject' => $subject, 'site' => $site, 'siteUrl' => $siteUrl,
                'siteHost' => (string) (parse_url($siteUrl, PHP_URL_HOST) ?: $siteUrl), 'logo' => $logo,
                'brand' => $brand['light'], 'brandDark' => $brand['dark'], 'onBrand' => $brand['on'], 'onBrandDark' => $brand['onDark'],
                'title' => $title, 'greeting' => $name !== '' ? __('Guten Tag {name},', ['name' => $name]) : __('Guten Tag,'),
                'paras' => $paras, 'facts' => $facts, 'cta' => $cta, 'after' => $after, 'foot' => $foot,
                // Warn-Schaltfläche („Das war ich nicht“) nicht in der Markenfarbe
                'danger' => $kind === 'notice'];
            $html = Theme::capture(Invites::template('account.php'), $vars);
            $text = Theme::capture(Invites::template('account.txt.php'), $vars);
            return [$subject, trim($text) . "\n", $html, $inline];
        } finally {
            I18n::setLocale($prev);
        }
    }

    // ================================================================= Selbsttest

    /**
     * Selbsttest (Konsole account:selftest): Token, Hash, Ablauf, Abbrechen, Adresse vergeben (keine Rückschlüsse), auth_ver.
     * Arbeitet in einer Transaktion mit einem Testkonto und rollt alles zurück; E-Mails werden nicht versendet.
     */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        // Token und Hash
        $t = self::newToken();
        $eq('Token 43 Zeichen base64url (32 Byte)', self::wellFormed($t), true);
        $eq('Token zufällig', $t === self::newToken(), false);
        $eq('Hash SHA-256 (64 hex)', (bool) preg_match('~^[0-9a-f]{64}$~', self::hash('confirm', $t)), true);
        $eq('Hash je Zweck verschieden', self::hash('confirm', $t) === self::hash('cancel', $t), false);
        $eq('Hash an Website gebunden', self::hash('confirm', $t) === hash('sha256', $t), false);
        foreach (['', 'abc', str_repeat('a', 42), str_repeat('a', 44), str_repeat('a', 42) . '/', "x' OR 1=1 --"] as $bad) {
            $eq('Token ungültig: ' . $bad, self::find('confirm', $bad), null);
        }
        $eq('Unbekannter Token → null', self::find('confirm', self::newToken()), null);
        $eq('Falscher Zweck → null', self::find('reset', $t), null);
        $eq('Adresse ungültig', self::invalid('kein-at', 'a@example.com') !== null, true);
        $eq('Adresse gleich', self::invalid('A@example.com', 'a@example.com') !== null, true);
        $eq('Adresse ok', self::invalid('neu@example.com', 'a@example.com'), null);
        $eq('Passwort vorhanden', self::hasPassword(['password_hash' => '$2y$10$abc']), true);
        $eq('Nur Passkey', self::hasPassword(['password_hash' => '!passkey']), false);

        // Ablauf mit Testkonten – alles in einer Transaktion, danach zurückgerollt; E-Mails abgeschaltet
        $db = app()->db;
        $db->pdo->beginTransaction();
        self::$mute = true;
        try {
            $sfx = bin2hex(random_bytes(4));
            $a = $db->insert('users', ['email' => "selftest-a-$sfx@example.invalid", 'name' => 'Test A', 'password_hash' => '!passkey', 'role' => 'editor', 'created_at' => now()]);
            $b = $db->insert('users', ['email' => "selftest-b-$sfx@example.invalid", 'name' => 'Test B', 'password_hash' => '!passkey', 'role' => 'editor', 'created_at' => now()]);
            $ua = $db->fetch('SELECT * FROM users WHERE id = ?', [$a]);
            $ver = (int) $ua['auth_ver'];

            // 1) Normale Änderung: Token aus dem Hash nicht ableitbar → Token direkt setzen
            self::request($ua, "selftest-neu-$sfx@example.invalid");
            $p = self::pending($a);
            $eq('Offene Änderung angelegt', $p !== null && $p['new_email'] === "selftest-neu-$sfx@example.invalid", true);
            $eq('Adresse noch unverändert', (string) $db->fetchValue('SELECT email FROM users WHERE id = ?', [$a]), "selftest-a-$sfx@example.invalid");
            $tc = self::newToken();
            $tx = self::newToken();
            $db->update('user_email_changes', ['token_hash' => self::hash('confirm', $tc), 'cancel_hash' => self::hash('cancel', $tx)], 'id = :id', ['id' => (int) $p['id']]);
            $eq('Bestätigen-Token findet Änderung', (int) (self::find('confirm', $tc)['id'] ?? 0), (int) $p['id']);
            $eq('Abbrechen-Token ist kein Bestätigen-Token', self::find('confirm', $tx), null);
            $eq('Bestätigen-Token ist kein Abbrechen-Token', self::find('cancel', $tc), null);
            // Ablauf
            $db->update('user_email_changes', ['expires_at' => time() - 1], 'id = :id', ['id' => (int) $p['id']]);
            $eq('Abgelaufen → null', self::find('confirm', $tc), null);
            $eq('Abgelaufen: Abbrechen → null', self::find('cancel', $tx), null);
            $db->update('user_email_changes', ['expires_at' => time() + 3600], 'id = :id', ['id' => (int) $p['id']]);
            // Bestätigen
            $row = self::find('confirm', $tc);
            $u2 = $row ? self::confirm($row) : null;
            $eq('Bestätigt: neue Adresse', (string) ($u2['email'] ?? ''), "selftest-neu-$sfx@example.invalid");
            $eq('auth_ver + 1 (andere Sitzungen enden)', (int) ($u2['auth_ver'] ?? -1), $ver + 1);
            $eq('Link einmal verwendbar', self::find('confirm', $tc), null);
            $eq('Abbrechen nach Bestätigung → null', self::find('cancel', $tx), null);
            $eq('Keine offene Änderung mehr', self::pending($a), null);

            // 2) Abbrechen per Link an die bisherige Adresse → Sitzungen enden
            $ua = $db->fetch('SELECT * FROM users WHERE id = ?', [$a]);
            self::request($ua, "selftest-zwei-$sfx@example.invalid");
            $p = self::pending($a);
            $tc = self::newToken();
            $tx = self::newToken();
            $db->update('user_email_changes', ['token_hash' => self::hash('confirm', $tc), 'cancel_hash' => self::hash('cancel', $tx)], 'id = :id', ['id' => (int) $p['id']]);
            $row = self::find('cancel', $tx);
            $eq('Abbrechen-Link gültig', $row !== null, true);
            $eq('Abbrechen', $row ? self::cancelByToken($row) : false, true);
            $eq('Abbrechen: auth_ver + 1', (int) $db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [$a]), $ver + 2);
            $eq('Nach Abbrechen: Bestätigen ungültig', self::find('confirm', $tc), null);
            $eq('Nach Abbrechen: Abbrechen ungültig', self::find('cancel', $tx), null);
            $eq('Nach Abbrechen: Adresse unverändert', (string) $db->fetchValue('SELECT email FROM users WHERE id = ?', [$a]), "selftest-neu-$sfx@example.invalid");
            $eq('Abbrechen zweimal → false', $row ? self::cancelByToken($row) : true, false);

            // 3) Adresse eines anderen Kontos: gleiche offene Änderung, aber kein Bestätigungslink
            $ua = $db->fetch('SELECT * FROM users WHERE id = ?', [$a]);
            $free = self::request($ua, "selftest-frei-$sfx@example.invalid");
            $pFree = self::pending($a);
            $taken = self::request($ua, "selftest-b-$sfx@example.invalid");
            $pTaken = self::pending($a);
            $eq('Vergeben: gleiche Antwort wie frei', array_keys($taken) === array_keys($free) && $taken['delivered'] === $free['delivered'], true);
            $eq('Vergeben: offene Änderung sichtbar', ($pTaken['new_email'] ?? '') === "selftest-b-$sfx@example.invalid" && !$pTaken['expired'], true);
            $eq('Vergeben: gleiche Frist', abs((int) $pTaken['expires_at'] - (int) $pFree['expires_at']) <= 5, true);
            $eq('Vergeben: Hash gehört zu keinem Token', (bool) preg_match('~^[0-9a-f]{64}$~', (string) $pTaken['token_hash']), false);
            $db->update('user_email_changes', ['token_hash' => self::hash('confirm', $tc)], 'id = :id', ['id' => (int) $pTaken['id']]);
            $eq('Vergeben: Bestätigen unmöglich', self::find('confirm', $tc), null);
            $eq('Vergeben: confirm() lehnt ab', self::confirm($pTaken + ['token_hash' => self::hash('confirm', $tc)]), null);
            $eq('Konto B unverändert', (string) $db->fetchValue('SELECT email FROM users WHERE id = ?', [$b]), "selftest-b-$sfx@example.invalid");
            // Zwischenzeitlich vergeben: Adresse frei angefragt, dann legt jemand ein Konto damit an
            $db->update('user_email_changes', ['conflict' => 0], 'id = :id', ['id' => (int) $pTaken['id']]);
            $db->query('UPDATE users SET email = ? WHERE id = ?', ["selftest-b2-$sfx@example.invalid", $b]);
            $ua = $db->fetch('SELECT * FROM users WHERE id = ?', [$a]);
            self::request($ua, "selftest-spaeter-$sfx@example.invalid");
            $p = self::pending($a);
            $db->update('user_email_changes', ['token_hash' => self::hash('confirm', $tc)], 'id = :id', ['id' => (int) $p['id']]);
            $db->query('UPDATE users SET email = ? WHERE id = ?', ["selftest-spaeter-$sfx@example.invalid", $b]);
            $row = self::find('confirm', $tc);
            $eq('Später vergeben: Bestätigung scheitert', $row ? self::confirm($row) : null, null);
            $eq('Später vergeben: Konto A unverändert', (string) $db->fetchValue('SELECT email FROM users WHERE id = ?', [$a]), "selftest-neu-$sfx@example.invalid");

            // 4) Eigenes Abbrechen, erneut senden, Adresse geändert
            $ua = $db->fetch('SELECT * FROM users WHERE id = ?', [$a]);
            self::request($ua, "selftest-drei-$sfx@example.invalid");
            $p = self::pending($a);
            $before = (string) $p['token_hash'];
            self::resend($ua);
            $eq('Erneut senden: neuer Bestätigungslink', (string) self::pending($a)['token_hash'] !== $before, true);
            $eq('Erneut senden: Abbrechen-Link bleibt', (string) self::pending($a)['cancel_hash'], (string) $p['cancel_hash']);
            $eq('Im Konto abbrechen', self::cancelOwn($ua), true);
            $eq('Im Konto abbrechen: nichts mehr offen', self::pending($a), null);
            self::request($ua, "selftest-vier-$sfx@example.invalid");
            $p = self::pending($a);
            $db->update('user_email_changes', ['token_hash' => self::hash('confirm', $tc)], 'id = :id', ['id' => (int) $p['id']]);
            $db->query('UPDATE users SET email = ? WHERE id = ?', ["selftest-anders-$sfx@example.invalid", $a]);
            $eq('Adresse inzwischen anders → Link ungültig', self::find('confirm', $tc), null);
            $db->query('UPDATE users SET email = ?, disabled = 1 WHERE id = ?', ["selftest-neu-$sfx@example.invalid", $a]);
            $eq('Konto gesperrt → Link ungültig', self::find('confirm', $tc), null);
            $db->query('UPDATE users SET disabled = 0 WHERE id = ?', [$a]);

            // 5) Administration: direkt, offene Änderung verfällt, auth_ver
            $ua = $db->fetch('SELECT * FROM users WHERE id = ?', [$a]);
            $v0 = (int) $ua['auth_ver'];
            $eq('Admin: vergebene Adresse abgelehnt', self::adminChange($ua, "selftest-spaeter-$sfx@example.invalid", 'selftest') !== null, true);
            $eq('Admin: Änderung', self::adminChange($ua, "selftest-admin-$sfx@example.invalid", 'selftest'), null);
            $eq('Admin: Adresse', (string) $db->fetchValue('SELECT email FROM users WHERE id = ?', [$a]), "selftest-admin-$sfx@example.invalid");
            $eq('Admin: auth_ver + 1', (int) $db->fetchValue('SELECT auth_ver FROM users WHERE id = ?', [$a]), $v0 + 1);
            $eq('Admin: offene Änderung verfallen', self::pending($a), null);

            // 6) E-Mails: alle Arten lassen sich erzeugen, Links und Adressen escaped
            foreach (['confirm', 'notice', 'taken', 'changed', 'admin', 'password'] as $k) {
                [$s, $txt, $html] = self::compose(['name' => '<b>X</b>', 'email' => 'a@example.invalid'], $k,
                    ['old' => 'a@example.invalid', 'new' => 'n@example.invalid', 'url' => 'https://example.invalid/x?a=1&b=2', 'expires' => time() + 60]);
                $eq("E-Mail $k: Betreff", $s !== '', true);
                $eq("E-Mail $k: kein ungefiltertes HTML", str_contains($html, '<b>X</b>'), false);
                $eq("E-Mail $k: Text", str_contains($txt, 'X'), true);
            }
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            if ($db->pdo->inTransaction()) $db->pdo->rollBack();
            self::$mute = false;
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
