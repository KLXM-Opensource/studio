<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Http\Response;

/**
 * Angemeldet auf eine andere Domain DERSELBEN Website wechseln – für den Domainwechsel: Passkeys gelten nur für die Domain, auf der
 * sie angelegt wurden. Vor dem Umstellen der Hauptadresse meldet man sich so auf der neuen Domain an und legt dort einen Passkey an.
 * Einmal-Token (HMAC mit dem Schlüssel der Website, 60 s, an Browser und Ziel-Domain gebunden, Nonce einmalig in host_handover);
 * die Anmeldung auf dem Ziel gilt wie die bestehende (zweiter Faktor wurde auf der alten Domain bereits geprüft).
 * Konten aller Art (lokal, Netzwerk-Konto auf der Netzwerk-Website, Schatten-Konto) – es wird dieselbe users.id angemeldet.
 */
final class HostHandover
{
    public const TTL = 60;
    public const TARGET = '/admin/account';
    public const PATH = '/admin/sso/domain';   // Ziel ohne Anmeldung erreichbar (AdminPath::directAllowed: /admin/sso/…)

    public static function ensure(Database $db): void
    {
        $tail = $db->driver === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS host_handover (nonce VARCHAR(64) NOT NULL PRIMARY KEY, uid INT NOT NULL,
            host VARCHAR(191) NOT NULL, expires_at INT NOT NULL)$tail");
    }

    /** Domains dieser Website, auf die gewechselt werden kann (ohne die aktuell aufgerufene, ohne Landing-Domains) */
    public static function targets(): array
    {
        // Gleiche RP-ID wie die aktuelle Domain (nur anderer Port) = derselbe Passkey → kein Ziel
        $cur = self::rp((string) (app()->request?->host() ?? ''));
        return array_values(array_filter(site()->hosts(), fn($h) => self::rp($h) !== $cur));
    }

    /** Adresse auf der Ziel-Domain (Schema wie die aktuelle Anfrage – lokal http, sonst https) */
    public static function link(string $host, string $path): string
    {
        $secure = app()->request?->isSecure() ?? true;
        return ($secure ? 'https://' : 'http://') . $host . url($path);   // url(): eigene Verwaltungsadresse, Rewrite, Unterordner
    }

    public static function issue(int $uid, string $host, string $ua): string
    {
        if (!in_array($host, self::targets(), true)) throw new \InvalidArgumentException(__('Diese Domain gehört nicht zu dieser Website.'));
        $db = app()->db;
        self::ensure($db);
        $db->query('DELETE FROM host_handover WHERE expires_at < ?', [time() - 300]);
        $nonce = bin2hex(random_bytes(16));
        $exp = time() + self::TTL;
        $db->insert('host_handover', ['nonce' => $nonce, 'uid' => $uid, 'host' => $host, 'expires_at' => $exp]);
        $payload = self::b64(json_encode(['v' => 1, 'h' => $host, 'u' => $uid, 'n' => $nonce, 'e' => $exp, 'a' => self::ua($ua)]));
        return $payload . '.' . self::b64(hash_hmac('sha256', 'handover|' . $payload, app()->key(), true));
    }

    /** @return int|string users.id oder Fehlertext */
    public static function consume(string $token, string $host, string $ua): int|string
    {
        $bad = __('Der Anmelde-Link ist ungültig oder abgelaufen. Bitte auf der bisherigen Domain erneut starten.');
        $parts = explode('.', $token);
        if (count($parts) !== 2 || strlen($token) > 1000) return $bad;
        [$payload, $sig] = $parts;
        $mac = self::unb64($sig);
        if ($mac === false || !hash_equals(hash_hmac('sha256', 'handover|' . $payload, app()->key(), true), $mac)) return $bad;
        $d = json_decode((string) self::unb64($payload), true);
        if (!is_array($d) || ($d['v'] ?? 0) !== 1 || (int) ($d['e'] ?? 0) < time()) return $bad;
        if (($d['h'] ?? '') !== $host || !hash_equals((string) ($d['a'] ?? ''), self::ua($ua))) return $bad;
        self::ensure(app()->db);
        $st = app()->db->query('DELETE FROM host_handover WHERE nonce = ? AND uid = ? AND host = ? AND expires_at >= ?',
            [(string) ($d['n'] ?? ''), (int) ($d['u'] ?? 0), $host, time()]);
        if ($st->rowCount() !== 1) return $bad;
        $u = app()->db->fetch('SELECT id, disabled FROM users WHERE id = ?', [(int) $d['u']]);
        if (!$u || (int) ($u['disabled'] ?? 0)) return __('Dieses Konto ist gesperrt.');
        return (int) $u['id'];
    }

    /**
     * Zwischenseite mit <meta refresh> statt 303 (CSP der Verwaltung: form-action 'self' gilt auch für Weiterleitungen nach Formularen).
     */
    public static function page(string $to, string $host): Response
    {
        $html = '<!doctype html><html lang="' . e(I18n::locale()) . '" class="adm-ui"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="referrer" content="no-referrer">'
            . '<meta http-equiv="refresh" content="0;url=' . e($to) . '"><title>' . e(__('Weiterleitung …')) . '</title>'
            . '<link rel="stylesheet" href="' . e(asset('css/admin.css')) . '"></head><body class="adm adm--bare"><main class="adm-auth">'
            . '<p class="adm-card">' . e(__('Anmeldung auf {host} …', ['host' => $host])) . ' <a href="' . e($to) . '" rel="noreferrer">' . e(__('Weiter')) . '</a></p>'
            . '</main></body></html>';
        return (new Response($html))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Domains, für die das Konto einen Passkey hat (RP-ID = Domain ohne Port) – Hinweise beim Domainwechsel.
     * @return list<string>
     */
    public static function passkeyHosts(array $user): array
    {
        try {
            [$db, $uid] = Mfa::store($user);
            return array_values(array_unique(array_map(fn($p) => (string) $p['rp_id'], Passkeys::list($db, $uid))));
        } catch (\Throwable) {
            return [];
        }
    }

    /** Hat das Konto Passkeys, aber keinen für $host? (Dann gilt der Passkey dort nicht – vorher dort einen anlegen) */
    public static function missing(array $user, string $host, ?array $pkHosts = null): bool
    {
        $pkHosts ??= self::passkeyHosts($user);
        return $pkHosts && !in_array(self::rp($host), $pkHosts, true);
    }

    /** RP-ID einer Domain (ohne Port, kleingeschrieben) */
    public static function rp(string $host): string
    {
        return rtrim(trim((string) preg_replace('~:\d+$~', '', strtolower($host)), '[]'), '.');
    }

    private static function ua(string $ua): string
    {
        return substr(hash('sha256', 'ua|' . $ua), 0, 24);
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string|false
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }
}
