<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Sicherheits-Log für fail2ban (Plesk: Tools & Einstellungen → IP-Adressen sperren): eine Zeile je Vorfall in
 * storage/logs/security.log – nur mit 'security_log' => true in der Konfiguration (Standard aus, die Datei enthält IP-Adressen).
 *
 *   2026-10-23T01:30:00+02:00 klxm-studio auth-failed ip=203.0.113.7 site=default path=/admin/login
 *
 * Ereignisse: auth-failed (falsches Passwort/unbekanntes Konto), auth-limited (zu viele Versuche), 2fa-failed, passkey-failed,
 * reset-limited (Passwort vergessen zu oft), api-key-invalid, probe (typische Angriffsadresse wie /wp-login.php, /.env – nur
 * wenn keine Weiterleitung greift). Keine E-Mail-Adressen, keine Passwörter. Größer als 5 MB → security.log.1 (eine Vorgängerdatei).
 * Filter und Jails für fail2ban: deploy/fail2ban/ (Entwicklerhandbuch → Sicherheit → fail2ban).
 */
final class SecurityLog
{
    public const EVENTS = ['auth-failed', 'auth-limited', '2fa-failed', 'passkey-failed', 'reset-limited', 'api-key-invalid', 'probe'];
    private const MAX_BYTES = 5_000_000;
    /** Adressen, die eine Website mit KLXM Studio nie hat – wer sie abfragt, sucht Lücken anderer Systeme */
    private const PROBE = '~^/(?:wp-login\.php|wp-admin(?:/|$)|wp-config|xmlrpc\.php|\.env|\.git/|\.svn/|\.aws/|\.ssh/|\.htpasswd|phpmyadmin|pma(?:/|$)|myadmin|'
        . 'vendor/phpunit|phpunit|cgi-bin/|boaform|hnap1|actuator(?:/|$)|server-status|solr/|_profiler|telescope|\.vscode/|config\.json$|.+\.(?:asp|aspx|jsp|cgi|env|sql|bak|old)$)~i';

    public static function enabled(): bool
    {
        return (bool) app()->config->get('security_log', false);
    }

    public static function file(): string
    {
        return ROOT . '/storage/logs/security.log';
    }

    /** Typische Angriffsadresse? (Pfad ohne Query) */
    public static function isProbe(string $path): bool
    {
        return (bool) preg_match(self::PROBE, $path);
    }

    /** IP der Anfrage – hinter einem vertrauenswürdigen Proxy die letzte Adresse aus X-Forwarded-For (sonst würde fail2ban den Proxy sperren) */
    public static function clientIp(?Http\Request $r = null): string
    {
        $r ??= app()->request;
        if (!$r) return '0.0.0.0';
        $ip = $r->ip();
        if ($r->fromProxy() && ($fwd = trim((string) ($r->server['HTTP_X_FORWARDED_FOR'] ?? ''))) !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $fwd))));
            $last = (string) end($parts);
            if (filter_var($last, FILTER_VALIDATE_IP)) $ip = $last;
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /** php bin/console security-log:selftest – Angriffsadressen, Format der Zeile, Filter in deploy/fail2ban passen zum Format */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = Testing\Check::eq($ok, $fails);
        $probe = ['/wp-login.php', '/xmlrpc.php', '/.env', '/.git/config', '/phpmyadmin/', '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php', '/backup.sql', '/index.aspx'];
        $fine = ['/', '/kontakt', '/wp-content/uploads/2020/bild.jpg', '/media/datei.pdf', '/admin/login', '/.well-known/acme-challenge/x', '/termine/2026'];
        $eq('Angriffsadressen erkannt', array_values(array_filter($probe, [self::class, 'isProbe'])), $probe);
        $eq('Normale Adressen nicht', array_values(array_filter($fine, [self::class, 'isProbe'])), []);
        $line = '2026-10-23T01:30:00+02:00 klxm-studio auth-failed ip=203.0.113.7 site=default path=/admin/login';
        $host = '(?:::f{4,6}:)?(?P<host>[\w\-.^_]*\w|[0-9a-fA-F:]+)';   // wie <HOST> in fail2ban
        foreach (['auth' => [$line, '203.0.113.7'], 'probe' => [str_replace('auth-failed', 'probe', $line), '203.0.113.7']] as $name => [$l, $ip]) {
            if (!is_file(ROOT . "/deploy/fail2ban/klxm-studio-$name.conf")) continue;   // Server ohne deploy/ (nur Code ausgerollt)
            $conf = (string) @file_get_contents(ROOT . "/deploy/fail2ban/klxm-studio-$name.conf");
            $rx = preg_match('~^failregex\s*=\s*(.+)$~m', $conf, $m) ? str_replace('<HOST>', $host, trim($m[1])) : '';
            $eq("Filter klxm-studio-$name passt zur Zeile", $rx !== '' && preg_match('~' . str_replace('~', '\~', $rx) . '~', $l, $mm) ? $mm['host'] : null, $ip);
        }
        $conf = (string) @file_get_contents(ROOT . '/deploy/fail2ban/klxm-studio-auth.conf');
        if ($conf === '') return ['ok' => $ok, 'fails' => $fails];
        $missing = array_filter(array_diff(self::EVENTS, ['probe']), fn($e) => !str_contains($conf, $e));
        $eq('Filter klxm-studio-auth kennt alle Ereignisse', array_values($missing), []);
        return ['ok' => $ok, 'fails' => $fails];
    }

    /** Vorfall schreiben (still, wenn ausgeschaltet oder die Datei nicht schreibbar ist) */
    public static function write(string $event, ?string $ip = null): void
    {
        if (!in_array($event, self::EVENTS, true) || !self::enabled()) return;
        try {
            $f = self::file();
            if (!is_dir(dirname($f))) @mkdir(dirname($f), 0770, true);
            if (@filesize($f) > self::MAX_BYTES) @rename($f, $f . '.1');
            $ip = $ip !== null && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : self::clientIp();
            $site = function_exists('site') ? (string) site()->key : '-';
            $path = (string) (app()->request->path ?? '-');
            $path = preg_replace('~[^\x21-\x7E]~', '?', mb_substr($path, 0, 200)) ?: '-';
            @file_put_contents($f, date('c') . " klxm-studio $event ip=$ip site=$site path=$path\n", FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
        }
    }
}
