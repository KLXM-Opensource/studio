<?php
declare(strict_types=1);

namespace Core;

/**
 * Gesundheitsdaten (Art. 9 DSGVO): Anfragen werden mit libsodium „sealed box“
 * verschlüsselt gespeichert. Auf dem Server liegt NUR der öffentliche Schlüssel.
 * Der geheime Schlüssel bleibt beim Betreiber (z. B. Passwortmanager) und wird
 * zum Lesen im Admin eingegeben – er wird nicht gespeichert.
 */
final class FormCrypto
{
    public static function publicKey(): ?string
    {
        $b64 = (string) app()->settings->get('sys.form_public_key', '');
        $pk = $b64 !== '' ? base64_decode($b64, true) : false;
        return $pk !== false && strlen($pk) === SODIUM_CRYPTO_BOX_PUBLICKEYBYTES ? $pk : null;
    }

    public static function ready(): bool
    {
        return self::publicKey() !== null;
    }

    /** @return array{public: string, secret: string} Base64 */
    public static function generate(): array
    {
        $kp = sodium_crypto_box_keypair();
        $out = [
            'public' => base64_encode(sodium_crypto_box_publickey($kp)),
            'secret' => base64_encode(sodium_crypto_box_secretkey($kp)),
        ];
        sodium_memzero($kp);
        return $out;
    }

    public static function fingerprint(): string
    {
        $pk = self::publicKey();
        return $pk ? implode(' ', str_split(strtoupper(substr(hash('sha256', $pk), 0, 16)), 4)) : '–';
    }

    public static function seal(array $data): string
    {
        $pk = self::publicKey();
        if (!$pk) {
            throw new \RuntimeException('Kein öffentlicher Schlüssel hinterlegt.');
        }
        return base64_encode(sodium_crypto_box_seal(json_encode($data, JSON_UNESCAPED_UNICODE), $pk));
    }

    /** @return array|null Entschlüsselte Daten oder null bei falschem Schlüssel */
    public static function open(string $payload, string $secretB64): ?array
    {
        $sk = base64_decode(trim($secretB64), true);
        $pk = self::publicKey();
        if ($sk === false || strlen($sk) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES || !$pk) {
            return null;
        }
        // Passt der geheime zum öffentlichen Schlüssel?
        if (!hash_equals($pk, sodium_crypto_box_publickey_from_secretkey($sk))) {
            return null;
        }
        $kp = sodium_crypto_box_keypair_from_secretkey_and_publickey($sk, $pk);
        $plain = sodium_crypto_box_seal_open((string) base64_decode($payload), $kp);
        sodium_memzero($kp);
        if ($plain === false) {
            return null;
        }
        $d = json_decode($plain, true);
        return is_array($d) ? $d : null;
    }

    public static function keyMatches(string $secretB64): bool
    {
        $sk = base64_decode(trim($secretB64), true);
        $pk = self::publicKey();
        return $sk !== false && strlen($sk) === SODIUM_CRYPTO_BOX_SECRETKEYBYTES && $pk
            && hash_equals($pk, sodium_crypto_box_publickey_from_secretkey($sk));
    }
}
