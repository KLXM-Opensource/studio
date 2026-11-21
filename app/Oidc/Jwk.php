<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Oidc;

/**
 * JSON Web Keys (RFC 7517/8037) ohne Zusatzbibliothek: öffentliche RSA-Schlüssel (kty RSA, n/e → PEM für openssl) und
 * Ed25519 (kty OKP, crv Ed25519, x = 32 Byte für libsodium). Andere Schlüsselarten werden ignoriert.
 */
final class Jwk
{
    /** Kleinste erlaubte RSA-Schlüssellänge */
    public const RSA_MIN_BITS = 2048;

    public static function b64u(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64u(string $s): string|false
    {
        if ($s === '' || !preg_match('~^[A-Za-z0-9_-]+$~', $s)) return false;
        return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    /** RSA-JWK → PEM (SubjectPublicKeyInfo) oder null bei ungültigem/zu kurzem Schlüssel */
    public static function rsaPem(array $jwk): ?string
    {
        if (($jwk['kty'] ?? '') !== 'RSA') return null;
        $n = self::unb64u((string) ($jwk['n'] ?? ''));
        $e = self::unb64u((string) ($jwk['e'] ?? ''));
        if ($n === false || $e === false) return null;
        $n = ltrim($n, "\0");
        if (strlen($n) * 8 < self::RSA_MIN_BITS || strlen($e) > 8 || $e === '') return null;
        $rsa = self::seq(self::int($n) . self::int(ltrim($e, "\0")));
        $algo = self::seq("\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . "\x05\x00");   // rsaEncryption, NULL
        $spki = self::seq($algo . "\x03" . self::len(strlen($rsa) + 1) . "\0" . $rsa);
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** Ed25519-JWK → 32 Byte öffentlicher Schlüssel oder null */
    public static function ed25519(array $jwk): ?string
    {
        if (($jwk['kty'] ?? '') !== 'OKP' || ($jwk['crv'] ?? '') !== 'Ed25519') return null;
        $x = self::unb64u((string) ($jwk['x'] ?? ''));
        return $x !== false && strlen($x) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $x : null;
    }

    /** Öffentlicher RSA-Schlüssel (PEM oder Schlüsselobjekt) → JWK mit kid */
    public static function fromRsa(\OpenSSLAsymmetricKey|string $key, ?string $kid = null): array
    {
        $k = is_string($key) ? openssl_pkey_get_public($key) : $key;
        $d = $k ? openssl_pkey_get_details($k) : false;
        if (!$d || ($d['type'] ?? -1) !== OPENSSL_KEYTYPE_RSA) throw new \InvalidArgumentException('Kein RSA-Schlüssel.');
        $jwk = ['kty' => 'RSA', 'n' => self::b64u($d['rsa']['n']), 'e' => self::b64u($d['rsa']['e'])];
        return $jwk + ['kid' => $kid ?? self::thumbprint($jwk), 'use' => 'sig', 'alg' => 'RS256'];
    }

    /** Öffentlicher Ed25519-Schlüssel (32 Byte) → JWK mit kid */
    public static function fromEd25519(string $public, ?string $kid = null): array
    {
        $jwk = ['crv' => 'Ed25519', 'kty' => 'OKP', 'x' => self::b64u($public)];
        return $jwk + ['kid' => $kid ?? self::thumbprint($jwk), 'use' => 'sig', 'alg' => 'EdDSA'];
    }

    /** JWK-Fingerabdruck (RFC 7638) als kid */
    public static function thumbprint(array $jwk): string
    {
        $req = ($jwk['kty'] ?? '') === 'RSA' ? ['e', 'kty', 'n'] : ['crv', 'kty', 'x'];
        $o = [];
        foreach ($req as $k) $o[$k] = (string) ($jwk[$k] ?? '');
        return self::b64u(hash('sha256', (string) json_encode($o, JSON_UNESCAPED_SLASHES), true));
    }

    // ---------------------------------------------------------------- DER

    private static function len(int $n): string
    {
        if ($n < 128) return chr($n);
        $b = ltrim(pack('N', $n), "\0");
        return chr(0x80 | strlen($b)) . $b;
    }

    private static function seq(string $inner): string
    {
        return "\x30" . self::len(strlen($inner)) . $inner;
    }

    private static function int(string $bytes): string
    {
        if ($bytes === '' || ord($bytes[0]) & 0x80) $bytes = "\0" . $bytes;
        return "\x02" . self::len(strlen($bytes)) . $bytes;
    }
}
