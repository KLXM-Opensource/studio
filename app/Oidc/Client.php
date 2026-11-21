<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Oidc;

use Core\Http\Fetch;

/**
 * OpenID-Connect-Client (Relying Party): Authorization Code Flow mit PKCE (S256), state und nonce.
 *
 *  - Discovery: {issuer}/.well-known/openid-configuration, „issuer“ muss exakt passen; 1 Stunde zwischengespeichert.
 *  - JWKS: 1 Stunde zwischengespeichert; unbekannte kid → einmal neu laden (Schlüsselwechsel), höchstens alle 60 Sekunden.
 *  - ID-Token: Signatur RS256/EdDSA (Core\Oidc\Jwt), iss, aud (+ azp bei mehreren), exp/iat mit 60 s Toleranz, iat nicht älter
 *    als 10 Minuten, nonce, at_hash falls vorhanden, sub vorhanden.
 *  - Optionales Feld logo_uri: Logo::sync legt das Logo des Anbieters einmal lokal ab (Anmeldeseite, nie Hotlink).
 *  - Abrufe über Core\Http\Fetch (SSRF-Schutz); für den Selbsttest ersetzt Client::$transport die Abrufe.
 *  - Offene Anmeldungen in der Sitzung (_oidc): je state Anbieter, nonce, code_verifier, Ziel – 10 Minuten, höchstens 5, einmal verwendbar.
 */
final class Client
{
    public const LEEWAY = 60;
    public const MAX_AGE = 600;
    public const PENDING_TTL = 600;
    private const CACHE_TTL = 3600;

    /** @var null|callable(string $method, string $url, list<string> $headers, ?string $body): array{status: int, body: string} */
    public static $transport = null;
    /** Uhr (Selbsttest) */
    public static ?int $now = null;
    /** Zwischenspeicher im Speicher (mit $transport, Selbsttest) und offene Anmeldungen ohne Sitzung (Kommandozeile) */
    private static array $mem = [];
    private static array $cliPending = [];

    /** Selbsttest: Zwischenspeicher und offene Anmeldungen verwerfen */
    public static function resetForTest(): void
    {
        self::$mem = [];
        self::$cliPending = [];
    }

    private static function getPending(): array
    {
        return app()->session->started() ? (array) app()->session->get('_oidc', []) : self::$cliPending;
    }

    private static function setPending(array $v): void
    {
        if (app()->session->started()) app()->session->set('_oidc', $v); else self::$cliPending = $v;
    }

    public static function now(): int
    {
        return self::$now ?? time();
    }

    // ================================================================= Abrufe

    /** @return array{status: int, body: string} */
    private static function http(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        if (self::$transport) return (self::$transport)($method, $url, $headers, $body);
        $o = ['timeout' => 10, 'max' => 512 * 1024, 'kind' => 'json', 'headers' => array_merge(['Accept: application/json'], $headers), 'any_status' => true];
        $r = $method === 'POST' ? Fetch::post($url, (string) $body, $o) : Fetch::get($url, $o);
        return ['status' => $r['status'], 'body' => $r['body']];
    }

    private static function json(string $url): array
    {
        $r = self::http('GET', $url);
        $j = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($j)) throw new OidcException(__('Der Anbieter antwortet nicht wie erwartet ({url}).', ['url' => $url]));
        return $j;
    }

    private static function cacheFile(string $kind, string $key): string
    {
        return site()->storage('cache/oidc') . '/' . $kind . '-' . substr(hash('sha256', $key), 0, 24) . '.json';
    }

    private static function cached(string $kind, string $key, bool $force, callable $load, int $ttl = self::CACHE_TTL): array
    {
        if (self::$transport) {
            $mk = $kind . '|' . $key;
            if (!$force && isset(self::$mem[$mk])) return self::$mem[$mk];
            return self::$mem[$mk] = $load();
        }
        $f = self::cacheFile($kind, $key);
        if (!$force && is_file($f) && filemtime($f) > time() - $ttl) {
            $j = json_decode((string) @file_get_contents($f), true);
            if (is_array($j)) return $j;
        }
        $j = $load();
        @mkdir(dirname($f), 0770, true);
        @file_put_contents($f, json_encode($j, JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $j;
    }

    public static function forgetCache(string $issuer): void
    {
        if ($issuer === '') return;
        @unlink(self::cacheFile('disc', $issuer));
    }

    /** Discovery-Dokument (geprüft) */
    public static function discovery(string $issuer, bool $force = false): array
    {
        return self::cached('disc', $issuer, $force, function () use ($issuer, $force): array {
            $d = self::json($issuer . '/.well-known/openid-configuration');
            if (!hash_equals($issuer, rtrim((string) ($d['issuer'] ?? ''), '/')) || (string) ($d['issuer'] ?? '') === '') {
                throw new OidcException(__('Das Discovery-Dokument nennt einen anderen Issuer – Anmeldung abgebrochen.'));
            }
            foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $k) {
                if (!self::safeEndpoint((string) ($d[$k] ?? ''))) throw new OidcException(__('Im Discovery-Dokument fehlt „{key}“ oder die Adresse ist unsicher.', ['key' => $k]));
            }
            if (isset($d['code_challenge_methods_supported']) && !in_array('S256', (array) $d['code_challenge_methods_supported'], true)) {
                throw new OidcException(__('Der Anbieter unterstützt PKCE (S256) nicht.'));
            }
            // Optionales Logo (logo_uri) einmal lokal ablegen – Fehler dabei stören die Anmeldung nie (Core\Oidc\Logo)
            try {
                Logo::sync($issuer, $d, $force);
            } catch (\Throwable $e) {
                error_log('[oidc] Logo: ' . $e->getMessage());
            }
            return $d;
        });
    }

    /** Endpunkte nur per https (http nur in der Entwicklung bzw. im Selbsttest) */
    private static function safeEndpoint(string $u): bool
    {
        if (preg_match('~^https://[^\s/?#]+~', $u)) return true;
        return preg_match('~^http://[^\s/?#]+~', $u) && (self::$transport !== null || environment() === 'development');
    }

    public static function jwks(string $uri, bool $force = false): array
    {
        return self::cached('jwks', $uri, $force, function () use ($uri): array {
            $j = self::json($uri);
            if (!isset($j['keys']) || !is_array($j['keys'])) throw new OidcException(__('Die Schlüssel des Anbieters (JWKS) sind ungültig.'));
            return ['keys' => array_values(array_filter($j['keys'], 'is_array'))];
        });
    }

    // ================================================================= Ablauf

    /** PKCE: code_verifier (43–128 Zeichen) → code_challenge (S256) */
    public static function challenge(string $verifier): string
    {
        return Jwk::b64u(hash('sha256', $verifier, true));
    }

    /**
     * Anmeldung beginnen: offene Anmeldung in der Sitzung merken. $ctx: next (Pfad), site (Ziel-Website im Netzwerk), login_hint.
     * @return string Adresse beim Anbieter
     */
    public static function begin(array $p, array $ctx = []): string
    {
        $d = self::discovery($p['issuer']);
        $state = Jwk::b64u(random_bytes(32));
        $nonce = Jwk::b64u(random_bytes(32));
        $verifier = Jwk::b64u(random_bytes(48));
        $pending = self::pending();
        $pending[$state] = ['p' => $p['id'], 'n' => $nonce, 'v' => $verifier, 'at' => self::now(), 'r' => Providers::redirectUri(),
            'next' => (string) ($ctx['next'] ?? ''), 'site' => (string) ($ctx['site'] ?? '')];
        // höchstens 5 offene Anmeldungen je Sitzung (älteste fallen weg)
        uasort($pending, fn($a, $b) => $b['at'] <=> $a['at']);
        self::setPending(array_slice($pending, 0, 5, true));
        $q = ['response_type' => 'code', 'client_id' => $p['client_id'], 'redirect_uri' => Providers::redirectUri(), 'scope' => $p['scopes'] ?: 'openid',
            'state' => $state, 'nonce' => $nonce, 'code_challenge' => self::challenge($verifier), 'code_challenge_method' => 'S256'];
        if (!empty($ctx['login_hint'])) $q['login_hint'] = mb_substr((string) $ctx['login_hint'], 0, 191);
        $ep = (string) $d['authorization_endpoint'];
        return $ep . (str_contains($ep, '?') ? '&' : '?') . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    /** Gültige offene Anmeldungen der Sitzung */
    private static function pending(): array
    {
        $out = [];
        foreach (self::getPending() as $s => $v) {
            if (is_array($v) && (int) ($v['at'] ?? 0) > self::now() - self::PENDING_TTL) $out[(string) $s] = $v;
        }
        return $out;
    }

    /** Offene Anmeldung zum state holen und sofort verbrauchen (einmal verwendbar) */
    public static function takeState(string $state): ?array
    {
        $pending = self::pending();
        $hit = null;
        foreach ($pending as $s => $v) {
            if (strlen($state) >= 32 && hash_equals((string) $s, $state)) { $hit = $v; unset($pending[$s]); break; }
        }
        self::setPending($pending);
        return $hit;
    }

    /**
     * Rückkehr vom Anbieter: Code einlösen und ID-Token prüfen.
     * @return array{claims: array, id_token: string, alg: string}
     * @throws OidcException
     */
    public static function finish(array $p, array $pend, string $code): array
    {
        if ($code === '' || strlen($code) > 2048) throw new OidcException(__('Der Anbieter hat keinen gültigen Code geliefert.'));
        $d = self::discovery($p['issuer']);
        $form = ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => (string) $pend['r'], 'code_verifier' => (string) $pend['v']];
        $headers = ['Content-Type: application/x-www-form-urlencoded'];
        if ($p['auth'] === 'client_secret_post') {
            $form += ['client_id' => $p['client_id'], 'client_secret' => $p['client_secret']];
        } else {
            // RFC 6749 2.3.1: Kennung und Geheimnis URL-kodiert in Basic
            $headers[] = 'Authorization: Basic ' . base64_encode(rawurlencode($p['client_id']) . ':' . rawurlencode($p['client_secret']));
            $form['client_id'] = $p['client_id'];
        }
        $r = self::http('POST', (string) $d['token_endpoint'], $headers, http_build_query($form, '', '&', PHP_QUERY_RFC3986));
        $tok = json_decode($r['body'], true);
        if ($r['status'] !== 200 || !is_array($tok)) {
            $err = is_array($tok) ? preg_replace('~[^\w.-]~', '', (string) ($tok['error'] ?? '')) : '';
            throw new OidcException(__('Der Anbieter hat den Code abgelehnt ({error}).', ['error' => $err ?: 'HTTP ' . $r['status']]));
        }
        if (strtolower((string) ($tok['token_type'] ?? 'bearer')) !== 'bearer' || !is_string($tok['id_token'] ?? null)) {
            throw new OidcException(__('Die Antwort des Anbieters enthält kein ID-Token.'));
        }
        $alg = '';
        $claims = self::validateIdToken($p, $d, (string) $tok['id_token'], (string) $pend['n'], is_string($tok['access_token'] ?? null) ? $tok['access_token'] : null, $alg);
        return ['claims' => $claims, 'id_token' => (string) $tok['id_token'], 'alg' => $alg];
    }

    /** ID-Token prüfen (Signatur mit Schlüsselwechsel, Claims). @throws OidcException */
    public static function validateIdToken(array $p, array $d, string $idt, string $nonce, ?string $accessToken = null, string &$alg = ''): array
    {
        $algs = array_values(array_intersect(Jwt::ALGS, (array) ($d['id_token_signing_alg_values_supported'] ?? Jwt::ALGS))) ?: ['RS256'];
        try {
            try {
                [$h, $c] = Jwt::verify($idt, self::jwks((string) $d['jwks_uri']), $algs);
            } catch (JwtException $e) {
                if (!in_array($e->getMessage(), ['unknown_kid', 'no_key'], true) || !self::mayRefresh((string) $d['jwks_uri'])) throw $e;
                [$h, $c] = Jwt::verify($idt, self::jwks((string) $d['jwks_uri'], true), $algs);   // Schlüsselwechsel beim Anbieter
            }
        } catch (JwtException $e) {
            throw new OidcException(__('Das ID-Token ist ungültig ({reason}).', ['reason' => $e->getMessage()]));
        }
        $alg = (string) $h['alg'];
        $now = self::now();
        $fail = fn(string $why) => new OidcException(__('Das ID-Token ist ungültig ({reason}).', ['reason' => $why]));
        if (!is_string($c['iss'] ?? null) || !hash_equals($p['issuer'], rtrim($c['iss'], '/'))) throw $fail('iss');
        $aud = is_array($c['aud'] ?? null) ? array_map('strval', $c['aud']) : [(string) ($c['aud'] ?? '')];
        if (!in_array($p['client_id'], $aud, true)) throw $fail('aud');
        if ((count($aud) > 1 || isset($c['azp'])) && (string) ($c['azp'] ?? '') !== $p['client_id']) throw $fail('azp');
        if (!is_numeric($c['exp'] ?? null) || (int) $c['exp'] < $now - self::LEEWAY) throw $fail('exp');
        if (!is_numeric($c['iat'] ?? null) || (int) $c['iat'] > $now + self::LEEWAY || (int) $c['iat'] < $now - self::MAX_AGE) throw $fail('iat');
        if (isset($c['nbf']) && (int) $c['nbf'] > $now + self::LEEWAY) throw $fail('nbf');
        if (!is_string($c['nonce'] ?? null) || $nonce === '' || !hash_equals($nonce, $c['nonce'])) throw $fail('nonce');
        if (!is_string($c['sub'] ?? null) || $c['sub'] === '' || strlen($c['sub']) > 255) throw $fail('sub');
        if (isset($c['at_hash']) && $accessToken !== null && !hash_equals(Jwt::halfHash($accessToken, $alg), (string) $c['at_hash'])) throw $fail('at_hash');
        return $c;
    }

    /** Schlüssel neu laden höchstens alle 60 Sekunden je JWKS */
    private static function mayRefresh(string $uri): bool
    {
        if (self::$transport) return true;
        $f = self::cacheFile('jwks', $uri);
        return !is_file($f) || filemtime($f) < time() - 60;
    }

    /** Abmelde-Adresse beim Anbieter (RP-initiated logout) oder null */
    public static function logoutUrl(array $p, string $idToken, string $back): ?string
    {
        try {
            $ep = (string) (self::discovery($p['issuer'])['end_session_endpoint'] ?? '');
        } catch (\Throwable) {
            return null;
        }
        if (!self::safeEndpoint($ep)) return null;
        $q = ['id_token_hint' => $idToken, 'post_logout_redirect_uri' => $back, 'client_id' => $p['client_id']];
        return $ep . (str_contains($ep, '?') ? '&' : '?') . http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    /** Belegt das Token einen zweiten Faktor? (amr nach RFC 8176) */
    public static function strongAmr(array $claims): bool
    {
        $amr = array_map('strval', (array) ($claims['amr'] ?? []));
        return (bool) array_intersect($amr, ['mfa', 'otp', 'hwk', 'swk', 'sc', 'fpt', 'face', 'iris', 'retina', 'vbm']);
    }
}
