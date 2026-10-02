<?php
declare(strict_types=1);

namespace Core;

/**
 * Gesundheitsdaten (Art. 9 DSGVO): Anfragen werden mit libsodium „sealed box“
 * verschlüsselt gespeichert. Auf dem Server liegt NUR der öffentliche Schlüssel.
 * Der geheime Schlüssel bleibt beim Betreiber (z. B. Passwortmanager) und wird
 * zum Lesen im Admin eingegeben – er wird nicht gespeichert.
 *
 * Optional (kleine Websites): der geheime Schlüssel als Umgebungsvariable des Hostings – KLXM_FORM_SECRET_{WEBSITE}
 * (Kurzname in Großbuchstaben, z. B. KLXM_FORM_SECRET_DEFAULT) oder KLXM_FORM_SECRET; eigener Name per config
 * 'form_secret_env', abschalten mit 'form_secret_env' => false. Passt er zum öffentlichen Schlüssel, entsperrt die
 * Anfragen-Ansicht automatisch (für Personen mit Leserecht). Schutz dann: Wer nur die Datenbank (Sicherung, SQL-Lücke)
 * erbeutet, liest nichts; wer den Server selbst übernimmt, schon. Der Schlüssel steht nie in Datenbank, Dateien oder Logs.
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

    /** Namen der Umgebungsvariablen in Prüf-Reihenfolge ([] = abgeschaltet) */
    public static function envNames(): array
    {
        $cfg = app()->config->get('form_secret_env', null);
        if ($cfg === false) return [];
        if (is_string($cfg) && preg_match('~^[A-Z_][A-Z0-9_]{0,63}$~', $cfg)) return [$cfg];
        return ['KLXM_FORM_SECRET_' . strtoupper(preg_replace('~[^A-Za-z0-9]+~', '_', site()->key)), 'KLXM_FORM_SECRET'];
    }

    /** Erste gesetzte Umgebungsvariable: [Name, Wert] oder null */
    private static function envRaw(): ?array
    {
        foreach (self::envNames() as $n) {
            $v = getenv($n);
            if (!is_string($v) || $v === '') $v = (string) ($_SERVER[$n] ?? $_ENV[$n] ?? '');
            if (trim($v) !== '') return [$n, trim($v)];
        }
        return null;
    }

    /** Geheimer Schlüssel aus der Hosting-Umgebung – nur wenn er zum öffentlichen Schlüssel passt */
    public static function envSecret(): ?string
    {
        $raw = self::envRaw();
        return $raw && self::keyMatches($raw[1]) ? $raw[1] : null;
    }

    /** Zustand für die Systemseite: ['state' => off|missing|mismatch|active, 'name' => Variable] */
    public static function envStatus(): array
    {
        $names = self::envNames();
        if (!$names) return ['state' => 'off', 'name' => ''];
        $raw = self::envRaw();
        if (!$raw) return ['state' => 'missing', 'name' => $names[0]];
        return ['state' => self::keyMatches($raw[1]) ? 'active' : 'mismatch', 'name' => $raw[0]];
    }
}
