<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Data;

use Core\Database;
use Core\Features;
use Core\Http\Response;
use Core\Permissions;
use Core\RateLimiter;
use Core\SecurityLog;

/**
 * Abonnieren per App: Kalender- und Kontakte-Tabellen in Kalender- und Adressbuch-Apps (iOS/macOS, Android, Outlook, Thunderbird).
 *
 * Öffentlich (ohne Token, nur freigegebene Einträge und Angaben – Core\Data\Exposure):
 *   GET /kalender/{tabelle}.ics                 bestehender Feed (Tabellen-Einstellung „Kalender → Feed“, Funktion calendar)
 *   GET /vcard/{tabelle}.vcf                    Sammeldatei aller freigegebenen Kontakte (Funktion subscriptions, öffentliche Tabelle)
 * Mit Abo-Token (Funktion „subscriptions“, Standard aus):
 *   GET /kalender/{tabelle}/abo/{token}.ics     Kalender-Abo (webcal://…)
 *   GET /vcard/{tabelle}/abo/{token}.vcf        Kontakte als Sammeldatei
 *   CardDAV (Erweiterung dav): Token als Passwort, nur lesen, nur die gebundene Tabelle (Klxm\Dav\Dav::login → verify())
 *
 * Token: benannt, an eine Tabelle gebunden, Umfang „nur öffentlich“ (public: wie ein Besucher, Ziel ics/vcard) oder „inkl. intern“
 * (internal: auch Entwürfe, nicht freigegebene Kontakte und Angaben – wie die Apps der Redaktion, Exposure::forApps; „Nur Website“-Felder
 * nie), nur lesen, optional ablaufend, widerrufbar, „zuletzt genutzt“. Format abo-{24 Zeichen} (≈ 119 Bit), gespeichert nur als
 * SHA-256 (wie API-Tokens), Klartext genau einmal in der Verwaltung. Gültig nur, solange das anlegende Konto besteht, nicht gesperrt ist
 * und das Recht data.edit auf die Tabelle hat.
 *
 * Token im Pfad statt in ?token=: Caches/CDNs, die Query-Strings ignorieren, könnten sonst eine Antwort mit internen Einträgen unter der
 * öffentlichen Adresse ausliefern; Pfad-Token kollidieren nicht mit ?lang=. Antworten mit Token: Cache-Control private, no-store,
 * Referrer-Policy no-referrer, X-Robots-Tag noindex. Der Token erscheint nie in Protokollen von KLXM Studio: das 404-Protokoll
 * (Core\Redirects) überspringt /abo/-Pfade, das Sicherheitsprotokoll kürzt ihn (redact()), Fehlerantworten nennen ihn nicht, Parameter
 * sind #[\SensitiveParameter] (nicht in Stacktraces). Zugriffs-Logs des Webservers sehen die Adresse wie bei jedem Kalender-Abo.
 *
 * Rate-Limit: je Token 120 Abrufe in 15 Minuten (CardDAV-Abgleiche brauchen mehrere Anfragen), ungültige Tokens 20 je IP in 15 Minuten
 * (danach 429, Sicherheitsprotokoll abo-token-invalid für fail2ban). Jeder gültige Abruf: abo-access (Info, ohne Sperre).
 */
final class Subscriptions
{
    public const FEATURE = 'subscriptions';
    public const PREFIX = 'abo-';
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    private const LENGTH = 24;
    public const SCOPES = ['public' => 'Nur öffentliche Einträge und Angaben', 'internal' => 'Inklusive interner Einträge und Angaben'];
    public const RATE_TOKEN = 120;
    public const RATE_IP_FAIL = 20;
    public const RATE_WINDOW = 900;
    public const NAME_MAX = 100;

    /** Selbsttest: Funktion ohne Konfiguration einschalten */
    private static bool $forceOn = false;
    /** Gerade läuft ein Abruf mit Umfang „intern“ (Exposure::websiteRequest, PageAccess::audience sehen dann alles) */
    private static bool $internal = false;

    // ================================================================= Tabelle

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS subscription_tokens (id $pk, table_handle VARCHAR(64) NOT NULL, name VARCHAR(191) NOT NULL,
            scope VARCHAR(10) NOT NULL DEFAULT 'public', prefix VARCHAR(16) NOT NULL, token_hash VARCHAR(64) NOT NULL UNIQUE, user_id INT NULL,
            created_at VARCHAR(25) NOT NULL, expires_at VARCHAR(10) NULL, last_used_at VARCHAR(25) NULL)$tail");
        if (!$my) $db->pdo->exec('CREATE INDEX IF NOT EXISTS subscription_tokens_table ON subscription_tokens (table_handle)');
    }

    // ================================================================= Funktion, Tabellen, Adressen

    /** Funktion „Abonnieren mit Token“ (und öffentliche Kontakte-Datei) eingeschaltet? */
    public static function enabled(): bool
    {
        return self::$forceOn || (Features::on(self::FEATURE, false) && Features::on('data', false));
    }

    /** Art des Abos einer Tabelle: calendar | contacts | null (nicht abonnierbar, z. B. Eingänge) */
    public static function kind(array $t): ?string
    {
        if (Tables::isInbox($t)) return null;
        if (Calendar::enabled($t)) return Features::on('calendar', false) || self::$forceOn ? 'calendar' : null;
        if (Kinds::isContacts($t) || \Core\StructuredData::typeOf($t) === 'Person') return 'contacts';
        return null;
    }

    /** Öffentliche Kontakte-Datei möglich? (Funktion an, Tabelle für die Website: nicht intern, nicht geschützt) */
    public static function publicContacts(array $t): bool
    {
        return self::enabled() && self::kind($t) === 'contacts' && Purpose::of($t) !== 'internal' && !\Core\PageAccess::tableRestricted($t);
    }

    /** Öffentlicher Kalender-Feed vorhanden? (bestehende Einstellung der Tabelle) */
    public static function publicCalendar(array $t): bool
    {
        return self::kind($t) === 'calendar' && !empty(Calendar::config($t)['feed']);
    }

    /** Adressen eines Abos: ['https' => …, 'webcal' => … (nur Kalender)] – mit $token die Token-Adresse */
    public static function urls(array $t, #[\SensitiveParameter] ?string $token = null): array
    {
        $h = rawurlencode((string) $t['handle']);
        if (self::kind($t) === 'calendar') {
            if ($token === null) return Calendar::feedUrls($t);
            $https = site_url() . url('/kalender/' . $h . '/abo/' . rawurlencode($token) . '.ics');
            return ['https' => $https, 'webcal' => (string) preg_replace('~^https?://~', 'webcal://', $https)];
        }
        return ['https' => site_url() . url('/vcard/' . $h . ($token === null ? '' : '/abo/' . rawurlencode($token)) . '.vcf')];
    }

    // ================================================================= Tokens

    /** Neuer Token (Klartext nur in der Rückgabe) @return array{id: int, token: string} */
    public static function create(array $t, string $name, string $scope, ?int $userId, ?string $expires = null): array
    {
        if (self::kind($t) === null) throw new \InvalidArgumentException('Tabelle ist nicht abonnierbar.');
        $raw = '';
        for ($i = 0; $i < self::LENGTH; $i++) $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        $token = self::PREFIX . $raw;
        $expires = $expires !== null && preg_match('~^\d{4}-\d{2}-\d{2}$~', $expires) ? $expires : null;
        $id = (int) app()->db->insert('subscription_tokens', [
            'table_handle' => (string) $t['handle'],
            'name' => mb_substr(trim(strip_tags($name)) ?: 'Abo', 0, self::NAME_MAX),
            'scope' => isset(self::SCOPES[$scope]) ? $scope : 'public',
            'prefix' => substr($raw, 0, 4),
            'token_hash' => self::hash($token),
            'user_id' => $userId,
            'created_at' => now(),
            'expires_at' => $expires,
        ]);
        return ['id' => $id, 'token' => $token];
    }

    public static function hash(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    /** Sieht der Text wie ein Abo-Token aus? */
    public static function looksLike(#[\SensitiveParameter] string $s): bool
    {
        return (bool) preg_match('~^' . self::PREFIX . '[' . self::ALPHABET . ']{' . self::LENGTH . '}$~', $s);
    }

    /** Tokens einer Tabelle (ohne Hash) mit Name des anlegenden Kontos */
    public static function forTable(string $handle): array
    {
        return app()->db->fetchAll('SELECT s.id, s.table_handle, s.name, s.scope, s.prefix, s.user_id, s.created_at, s.expires_at, s.last_used_at,
            u.name AS user_name, u.email AS user_email FROM subscription_tokens s LEFT JOIN users u ON u.id = s.user_id
            WHERE s.table_handle = ? ORDER BY s.id DESC', [$handle]);
    }

    public static function revoke(string $handle, int $id): bool
    {
        return app()->db->query('DELETE FROM subscription_tokens WHERE id = ? AND table_handle = ?', [$id, $handle])->rowCount() > 0;
    }

    /** Abgelaufen? (Ablaufdatum gilt bis einschließlich dieses Tages) */
    public static function expired(array $row): bool
    {
        return !empty($row['expires_at']) && (string) $row['expires_at'] < date('Y-m-d');
    }

    /**
     * Token prüfen. $handle = Tabelle aus der Adresse (null bei CardDAV: Tabelle aus dem Token). Ergebnis: status 200 mit token, table,
     * user (Konto) und role; sonst 404 (unbekannt, falsche Tabelle, Konto ohne Recht – ohne Hinweis, was davon), 410 (abgelaufen),
     * 429 (zu viele Abrufe). Zählt Rate-Limits, schreibt „zuletzt genutzt“ und das Sicherheitsprotokoll.
     * @return array{status: int, token?: array, table?: array, user?: array, role?: array}
     */
    public static function verify(#[\SensitiveParameter] string $plain, ?string $handle, string $channel = 'http'): array
    {
        if (!self::enabled()) return ['status' => 404];
        $limiter = new RateLimiter(app()->db);
        $ipKey = 'abo-ip:' . hash_hmac('sha256', SecurityLog::clientIp(), app()->key());
        if ($limiter->tooMany($ipKey, self::RATE_IP_FAIL, self::RATE_WINDOW)) return ['status' => 429];
        $fail = function () use ($limiter, $ipKey): array {
            $limiter->hit($ipKey);
            SecurityLog::write('abo-token-invalid');
            return ['status' => 404];
        };
        $row = self::looksLike($plain) ? app()->db->fetch('SELECT * FROM subscription_tokens WHERE token_hash = ?', [self::hash($plain)]) : null;
        if (!$row || ($handle !== null && $row['table_handle'] !== $handle)) return $fail();
        $t = Tables::find((string) $row['table_handle']);
        if (!$t || self::kind($t) === null) return $fail();
        if (self::expired($row)) return ['status' => 410];
        // Konto hinter dem Token: besteht, nicht gesperrt (auch im Netzwerk), darf die Tabelle bearbeiten
        $u = !empty($row['user_id']) ? app()->db->fetch('SELECT id, email, name, role, disabled, network_uid FROM users WHERE id = ?', [(int) $row['user_id']]) : null;
        $role = $u ? Permissions::role((string) $u['role']) : null;
        if (!$u || (int) ($u['disabled'] ?? 0) || (!empty($u['network_uid']) && !\Core\Network\Network::accountActive((int) $u['network_uid']))
            || !Permissions::allows($role, 'data.edit', (string) $t['handle'])) return $fail();
        $key = 'abo:' . (int) $row['id'];
        if ($limiter->tooMany($key, self::RATE_TOKEN, self::RATE_WINDOW)) return ['status' => 429];
        $limiter->hit($key);
        if (($row['last_used_at'] ?? '') < date('Y-m-d H:i:s', time() - 60)) {
            app()->db->update('subscription_tokens', ['last_used_at' => now()], 'id = :id', ['id' => (int) $row['id']]);
        }
        SecurityLog::write('abo-access');
        unset($row['token_hash'], $u['disabled'], $u['network_uid']);
        return ['status' => 200, 'token' => $row, 'table' => $t, 'user' => $u, 'role' => $role];
    }

    /** Token in Texten (Pfade, Meldungen) kürzen: abo-k7m2... (nur ASCII, Sicherheitsprotokoll) – Kürzel bleibt für die Zuordnung */
    public static function redact(string $s): string
    {
        return (string) preg_replace('~' . self::PREFIX . '([a-z0-9]{4})[a-z0-9]+~i', self::PREFIX . '$1...', $s);
    }

    // ================================================================= Umfang „intern“

    /** $fn mit Sicht der Apps der Redaktion ausführen: Website-Filter (Kontakt-Freigabe, geschützte Tabellen) greifen nicht */
    public static function internally(callable $fn): mixed
    {
        $prev = self::$internal;
        self::$internal = true;
        try {
            return $fn();
        } finally {
            self::$internal = $prev;
        }
    }

    public static function inInternal(): bool
    {
        return self::$internal;
    }

    // ================================================================= Inhalte

    /** ICS eines Abos (Umfang public = wie der öffentliche Feed, internal = auch Entwürfe und interne Angaben) */
    public static function calendarBody(array $t, string $scope): string
    {
        $internal = $scope === 'internal';
        $c = Calendar::config($t);
        $q = fn() => Entries::query($t, ['status' => $internal ? 'all' : 'published', 'sort' => $c['start'], 'dir' => 'asc', 'limit' => 2000, 'internal' => $internal]);
        $entries = $internal ? self::internally($q) : $q();
        return ICal::calendar($t, $entries, ['publish' => true, 'name' => $t['name'] . ' – ' . site_name(), 'internal' => $internal]);
    }

    /** Sammeldatei (mehrere vCards) eines Abos */
    public static function contactsBody(array $t, string $scope): string
    {
        $internal = $scope === 'internal';
        $q = fn() => Entries::query($t, ['status' => $internal ? 'all' : 'published', 'limit' => 5000, 'internal' => $internal]);
        $out = '';
        foreach ($internal ? self::internally($q) : $q() as $e) {
            if (!$internal && !Exposure::visible($t, $e)) continue;
            $out .= \Core\VCard::person($t, $e, $internal);
        }
        return $out;
    }

    /** Antwort: mit Token nie zwischenspeichern, öffentlich wie der bisherige Feed */
    public static function response(string $body, string $type, string $file, bool $private): Response
    {
        return new Response($body, 200, [
            'Content-Type' => ($type === 'ics' ? 'text/calendar' : 'text/vcard') . '; charset=utf-8',
            'Content-Disposition' => ($type === 'ics' ? 'inline' : 'attachment') . '; filename="' . preg_replace('~[^a-z0-9._-]+~i', '-', $file) . '"',
            'Cache-Control' => $private ? 'private, no-store, max-age=0' : 'public, max-age=900',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    /** Fehlerantwort ohne Token, ohne Fehlerseite (nichts landet im 404-Protokoll) */
    public static function error(int $status): Response
    {
        $text = match ($status) { 410 => 'Dieses Abo ist abgelaufen.', 429 => 'Zu viele Abrufe – bitte später erneut versuchen.', default => 'Nicht gefunden.' };
        $res = new Response($text . "\n", $status, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow', 'Referrer-Policy' => 'no-referrer']);
        if ($status === 429) $res->header('Retry-After', (string) self::RATE_WINDOW);
        return $res;
    }

    // ================================================================= Selbsttest (subscriptions:selftest)

    public static function forceForTest(bool $on): void
    {
        self::$forceOn = $on;
    }
}
