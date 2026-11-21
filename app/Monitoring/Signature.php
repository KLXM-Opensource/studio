<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Monitoring;

use Core\Oidc\Jwk;

/**
 * Signierte Status-Abfrage, Protokoll „status-v1“ (systemneutral – ein Überwachungsdienst kann so Websites verschiedener Systeme
 * abfragen). Ed25519 (libsodium), Schlüssel des abfragenden Dienstes; die Website kennt nur dessen öffentliche Schlüssel.
 *
 * Kopfzeilen der Anfrage:
 *   X-Status-Key        Schlüssel-ID: erste 16 Zeichen von base64url(SHA-256(öffentlicher Schlüssel))
 *   X-Status-Timestamp  Unix-Zeit in Sekunden (±300 s)
 *   X-Status-Nonce      16–64 Zeichen [A-Za-z0-9_-], je Schlüssel nur einmal in 10 Minuten
 *   X-Status-Signature  base64url(Ed25519-Signatur über die Basiszeichenkette)
 * Basiszeichenkette (Zeilen mit \n, ohne abschließenden Zeilenumbruch):
 *   status-v1 · Methode (GET) · Host in Kleinbuchstaben (mit Port, wenn nicht 80/443) · Pfad mit Query · Zeitstempel · Nonce
 * Öffentliche Schlüssel werden als „ed25519:<base64url, 32 Byte>“ weitergegeben.
 */
final class Signature
{
    public const PROTOCOL = 'status-v1';
    public const SKEW = 300;
    public const NONCE_TTL = 600;

    public static function base(string $method, string $host, string $pathQuery, int $ts, string $nonce): string
    {
        return implode("\n", [self::PROTOCOL, strtoupper($method), strtolower($host), $pathQuery, (string) $ts, $nonce]);
    }

    public static function keyId(string $public): string
    {
        return substr(Jwk::b64u(hash('sha256', $public, true)), 0, 16);
    }

    /** „ed25519:…“ → 32 Byte oder null */
    public static function parseKey(string $s): ?string
    {
        $s = trim($s);
        if (!preg_match('~^ed25519:([A-Za-z0-9_-]{43})$~', $s, $m)) return null;
        $raw = Jwk::unb64u($m[1]);
        return $raw !== false && strlen($raw) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $raw : null;
    }

    public static function formatKey(string $public): string
    {
        return 'ed25519:' . Jwk::b64u($public);
    }

    /** Host-Teil für die Basiszeichenkette aus einer Adresse (Standard-Ports entfallen) */
    public static function hostOf(string $url): string
    {
        $p = parse_url($url);
        $host = strtolower((string) ($p['host'] ?? ''));
        $port = (int) ($p['port'] ?? 0);
        $scheme = strtolower((string) ($p['scheme'] ?? 'https'));
        return $host . ($port && !($scheme === 'https' && $port === 443) && !($scheme === 'http' && $port === 80) ? ':' . $port : '');
    }

    /** Kopfzeilen für eine Abfrage (Dienst-Seite). $secret: 64-Byte-Geheimschlüssel (libsodium) */
    public static function headers(string $secret, string $url, ?int $ts = null, ?string $nonce = null): array
    {
        $ts ??= time();
        $nonce ??= Jwk::b64u(random_bytes(18));
        $p = parse_url($url);
        $pq = ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
        $sig = sodium_crypto_sign_detached(self::base('GET', self::hostOf($url), $pq, $ts, $nonce), $secret);
        return ['X-Status-Key: ' . self::keyId(sodium_crypto_sign_publickey_from_secretkey($secret)), 'X-Status-Timestamp: ' . $ts,
            'X-Status-Nonce: ' . $nonce, 'X-Status-Signature: ' . Jwk::b64u($sig)];
    }

    /**
     * Anfrage prüfen (Website-Seite). $h: Kopfzeilen in Kleinbuchstaben; $keys: [öffentlicher Schlüssel, …];
     * $seen: fn(string $kid, string $nonce): bool – true = schon verwendet (merkt sich den Nonce).
     * @return string|null Fehlergrund oder null (gültig)
     */
    public static function verify(array $h, string $method, string $host, string $pathQuery, array $keys, callable $seen, ?int $now = null): ?string
    {
        $now ??= time();
        $kid = (string) ($h['x-status-key'] ?? '');
        $ts = (string) ($h['x-status-timestamp'] ?? '');
        $nonce = (string) ($h['x-status-nonce'] ?? '');
        $sig = Jwk::unb64u((string) ($h['x-status-signature'] ?? ''));
        if ($kid === '' || !preg_match('~^\d{9,11}$~', $ts) || !preg_match('~^[A-Za-z0-9_-]{16,64}$~', $nonce) || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) return 'format';
        if (abs($now - (int) $ts) > self::SKEW) return 'time';
        $pub = null;
        foreach ($keys as $k) if (is_string($k) && strlen($k) === 32 && hash_equals(self::keyId($k), $kid)) { $pub = $k; break; }
        if ($pub === null) return 'key';
        if (!sodium_crypto_sign_verify_detached($sig, self::base($method, $host, $pathQuery, (int) $ts, $nonce), $pub)) return 'signature';
        // Erst nach gültiger Signatur merken (sonst könnten Fremde Nonces „verbrauchen“)
        if ($seen($kid, $nonce)) return 'replay';
        return null;
    }
}
