<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Verwaltung → System → Domain (Admin\DomainController): Domains, Hauptadresse, Weiterleitung und Umgebung einer
 * Einzel-Installation. Gibt es mehrere Websites oder Netzwerk-Konten, verwaltet die Netzwerk-Administration die Domains
 * (/admin/network, site:hosts) – die Seite gibt es dann nicht.
 *
 *  - Erreichbarkeit: check() legt einen Zufallswert (Frage + Antwort, 2 Minuten gültig) in den Einstellungen ab und ruft
 *    https://{domain}/health?domain_check={frage} auf (danach http://). Nur diese Installation kennt die Antwort – stimmt sie,
 *    zeigt die Domain hierher. Erst dann darf sie Hauptadresse werden.
 *  - Weiterleitung: config 'redirect_to_primary' (config/sites/{key}.php) → GET/HEAD auf weitere Domains aus 'hosts' per 301
 *    auf die Hauptadresse (App::handle, redirect()). Nie für Landing-Domains, /health, lokale Adressen.
 */
final class Domains
{
    public const CHECK_KEY = 'sys.domain_check';
    public const CHECK_TTL = 120;

    private static ?bool $available = null;

    /** Seite „Domain“ verfügbar? Nur Einzel-Installation ohne Netzwerk-Konten */
    public static function available(): bool
    {
        if (self::$available !== null) return self::$available;
        if (Sites::multi()) return self::$available = false;
        try {
            return self::$available = !(int) Network\Network::db()->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'network'");
        } catch (\Throwable $e) {
            error_log('[domains] ' . $e->getMessage());
            return self::$available = false;
        }
    }

    /** Lokale Entwicklungsadresse (localhost, *.localhost, *.test, 127.0.0.1, ::1) – nie Ziel/Quelle einer Weiterleitung */
    public static function isLocal(string $host): bool
    {
        $h = strtolower((string) preg_replace('~:\d+$~', '', trim($host, '[]')));
        $h = trim($h, '[]');
        return $h === 'localhost' || str_ends_with($h, '.localhost') || str_ends_with($h, '.test') || $h === '::1' || str_starts_with($h, '127.');
    }

    /**
     * Zeigt die Domain auf diese Installation? HTTPS zuerst (mit Zertifikatsprüfung), dann HTTP.
     * @return array{ok: bool, scheme: string, reachable: bool}
     */
    public static function check(string $host): array
    {
        $s = app()->settings;
        $q = bin2hex(random_bytes(16));
        $answer = bin2hex(random_bytes(16));
        $s->set(self::CHECK_KEY, ['q' => $q, 'a' => $answer, 'exp' => time() + self::CHECK_TTL]);
        $out = ['ok' => false, 'scheme' => '', 'reachable' => false];
        try {
            foreach (['https', 'http'] as $scheme) {
                $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true, 'follow_location' => 0, 'header' => "Accept: application/json\r\n"],
                    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
                $body = @file_get_contents($scheme . '://' . $host . base_path() . '/health?domain_check=' . $q, false, $ctx);
                if ($body === false) continue;
                $out['reachable'] = true;
                $j = json_decode($body, true);
                if (is_array($j) && is_string($j['domain_check'] ?? null) && hash_equals($answer, $j['domain_check'])) {
                    return ['ok' => true, 'scheme' => $scheme, 'reachable' => true];
                }
            }
        } finally {
            $s->delete(self::CHECK_KEY);
        }
        return $out;
    }

    /** Antwort für /health?domain_check=… – nur mit gültiger, nicht abgelaufener Frage */
    public static function answer(string $q): ?string
    {
        $c = app()->settings->get(self::CHECK_KEY);
        if (!is_array($c) || !is_string($c['q'] ?? null) || (int) ($c['exp'] ?? 0) < time() || !hash_equals($c['q'], $q)) return null;
        return (string) ($c['a'] ?? '');
    }

    /** Weiterleitung auf die Hauptadresse eingeschaltet? */
    public static function redirectOn(?Site $site = null): bool
    {
        return !empty(($site ?? site())->cfg['redirect_to_primary']);
    }

    /**
     * Ziel der 301-Weiterleitung auf die Hauptadresse oder null. Nur GET/HEAD, nur weitere Domains aus 'hosts'
     * (Landing-Domains und unbekannte Domains bleiben), nie /health (Domain-Prüfung) und nie lokale Adressen.
     */
    public static function redirectTarget(Site $site, string $method, string $host, string $path, string $uri, bool $secure): ?string
    {
        if (!self::redirectOn($site) || !in_array($method, ['GET', 'HEAD'], true) || $path === '/health') return null;
        $hosts = $site->hosts();
        $host = strtolower(trim($host));
        if (count($hosts) < 2 || $host === $hosts[0] || !in_array($host, $hosts, true)) return null;
        if (self::isLocal($host) || self::isLocal($hosts[0])) return null;
        $uri = '/' . ltrim($uri, '/');
        return ($secure ? 'https' : 'http') . '://' . $hosts[0] . $uri;
    }
}
