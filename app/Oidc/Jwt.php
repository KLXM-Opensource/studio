<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Oidc;

/**
 * Signierte JSON Web Tokens (JWS Compact, RFC 7515/7519) – nur asymmetrische Verfahren:
 *   RS256 (RSASSA-PKCS1-v1_5 mit SHA-256, openssl, Schlüssel ≥ 2048 Bit) und EdDSA (Ed25519, libsodium).
 * „none“, HS* (symmetrisch – ein öffentlicher Schlüssel würde als HMAC-Geheimnis missbraucht) und kritische Kopf-Erweiterungen
 * („crit“) werden immer abgewiesen. Schlüssel kommen aus einem JWKS ('keys' => [...]); mit kid nur der passende Schlüssel.
 */
final class Jwt
{
    public const ALGS = ['RS256', 'EdDSA'];
    private const MAX_LEN = 16384;

    /**
     * Signatur prüfen. @return array{0: array, 1: array} [Kopf, Inhalt]
     * @throws JwtException mit Grund (unknown_kid für „Schlüssel neu laden“)
     */
    public static function verify(string $jwt, array $jwks, array $algs = self::ALGS): array
    {
        if (strlen($jwt) > self::MAX_LEN) throw new JwtException('too_long');
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new JwtException('format');
        [$h64, $p64, $s64] = $parts;
        $header = json_decode((string) Jwk::unb64u($h64), true);
        $payload = json_decode((string) Jwk::unb64u($p64), true);
        $sig = Jwk::unb64u($s64);
        if (!is_array($header) || !is_array($payload) || $sig === false) throw new JwtException('format');
        $alg = (string) ($header['alg'] ?? '');
        if (!in_array($alg, self::ALGS, true) || !in_array($alg, $algs, true)) throw new JwtException('alg');
        if (isset($header['crit'])) throw new JwtException('crit');
        if (isset($header['typ']) && !in_array(strtolower((string) $header['typ']), ['jwt', 'at+jwt', 'logout+jwt'], true)) throw new JwtException('typ');
        $input = $h64 . '.' . $p64;
        $kty = $alg === 'RS256' ? 'RSA' : 'OKP';
        $kid = isset($header['kid']) ? (string) $header['kid'] : null;
        $cands = [];
        foreach ((array) ($jwks['keys'] ?? []) as $k) {
            if (!is_array($k) || ($k['kty'] ?? '') !== $kty) continue;
            if (isset($k['use']) && $k['use'] !== 'sig') continue;
            if (isset($k['alg']) && $k['alg'] !== $alg) continue;
            if ($kid !== null && (string) ($k['kid'] ?? '') !== $kid) continue;
            $cands[] = $k;
        }
        if (!$cands) throw new JwtException($kid !== null ? 'unknown_kid' : 'no_key');
        // Ohne kid: nur eindeutig (genau ein passender Schlüssel)
        if ($kid === null && count($cands) > 1) throw new JwtException('ambiguous_key');
        foreach ($cands as $k) {
            if (self::check($alg, $input, $sig, $k)) return [$header, $payload];
        }
        throw new JwtException('signature');
    }

    private static function check(string $alg, string $input, string $sig, array $jwk): bool
    {
        if ($alg === 'RS256') {
            $pem = Jwk::rsaPem($jwk);
            if ($pem === null) return false;
            return openssl_verify($input, $sig, $pem, OPENSSL_ALGO_SHA256) === 1;
        }
        $pk = Jwk::ed25519($jwk);
        if ($pk === null || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) return false;
        try {
            return sodium_crypto_sign_verify_detached($sig, $input, $pk);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Signieren (Anbieter, Selbsttest). $key: RS256 → privater Schlüssel (PEM/Objekt), EdDSA → 64-Byte-Geheimschlüssel (libsodium).
     */
    public static function sign(array $payload, string $alg, \OpenSSLAsymmetricKey|string $key, ?string $kid = null, array $header = []): string
    {
        if (!in_array($alg, self::ALGS, true)) throw new \InvalidArgumentException('Verfahren nicht erlaubt.');
        $h = ['alg' => $alg, 'typ' => 'JWT'] + ($kid !== null ? ['kid' => $kid] : []) + $header;
        $input = Jwk::b64u((string) json_encode($h, JSON_UNESCAPED_SLASHES)) . '.' . Jwk::b64u((string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if ($alg === 'RS256') {
            if (!openssl_sign($input, $sig, $key, OPENSSL_ALGO_SHA256)) throw new \RuntimeException('Signieren fehlgeschlagen.');
        } else {
            if (!is_string($key) || strlen($key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) throw new \InvalidArgumentException('Ed25519-Schlüssel erwartet.');
            $sig = sodium_crypto_sign_detached($input, $key);
        }
        return $input . '.' . Jwk::b64u((string) $sig);
    }

    /** Inhalt ohne Prüfung lesen (nur für Hinweise, z. B. id_token_hint beim Abmelden – nie für Entscheidungen) */
    public static function peek(string $jwt): ?array
    {
        $p = explode('.', $jwt);
        if (count($p) !== 3) return null;
        $d = json_decode((string) Jwk::unb64u($p[1]), true);
        return is_array($d) ? $d : null;
    }

    /** at_hash/c_hash (OIDC Core 3.1.3.6): linke Hälfte des Hashes – SHA-256 für RS256, SHA-512 für EdDSA (Ed25519) */
    public static function halfHash(string $value, string $alg): string
    {
        $h = hash($alg === 'EdDSA' ? 'sha512' : 'sha256', $value, true);
        return Jwk::b64u(substr($h, 0, intdiv(strlen($h), 2)));
    }
}
