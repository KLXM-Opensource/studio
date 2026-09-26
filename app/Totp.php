<?php
declare(strict_types=1);

namespace Core;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Zwei-Faktor-Anmeldung per Einmalcode (TOTP, RFC 6238 – SHA-1, 6 Stellen, 30 Sekunden; kompatibel mit
 * Authenticator-Apps wie 2FAS, Aegis, Google/Microsoft Authenticator, 1Password).
 *
 * Speicherung in der Tabelle users der Website, zu der das Konto gehört (Netzwerk-Konten: Netzwerk-Website):
 *   totp_secret    Geheimnis (Base32), verschlüsselt mit einem Schlüssel aus dem app_key dieser Website (libsodium secretbox)
 *   totp_recovery  JSON-Liste von SHA-256-Hashes der Wiederherstellungscodes (je 80 Bit, einmal verwendbar)
 *   totp_last      zuletzt verwendeter Zeitschritt – derselbe Code wird kein zweites Mal angenommen
 *   totp_enabled   1 = aktiv (erst nach Bestätigung eines Codes)
 * QR-Code: lokal als SVG erzeugt (chillerlan/php-qrcode, nur die Matrix; eigene SVG-Ausgabe als data:-Bild – CSP-tauglich).
 */
final class Totp
{
    public const DIGITS = 6;
    public const PERIOD = 30;
    private const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return self::base32(random_bytes(20));
    }

    public static function base32(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $out = '';
        foreach (str_split($bits, 5) as $chunk) $out .= self::B32[bindec(str_pad($chunk, 5, '0'))];
        return $out;
    }

    public static function base32Decode(string $s): string
    {
        $s = strtoupper(preg_replace('~[\s=-]~', '', $s));
        $bits = '';
        foreach (str_split($s) as $c) {
            $i = strpos(self::B32, $c);
            if ($i === false) return '';
            $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
        return $out;
    }

    /** Code für einen Zeitschritt (RFC 4226 HOTP mit Zähler = Zeitschritt) */
    public static function code(string $secret, int $step): string
    {
        $h = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $o = ord($h[19]) & 0x0f;
        $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string) ($n % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Zeitschritt des Codes (±1 Schritt Toleranz für Uhrabweichung) oder null */
    public static function match(string $secret, string $code, ?int $now = null): ?int
    {
        $code = preg_replace('~\D~', '', $code);
        if (strlen($code) !== self::DIGITS) return null;
        $step = intdiv($now ?? time(), self::PERIOD);
        foreach ([0, -1, 1] as $d) {
            if (hash_equals(self::code($secret, $step + $d), $code)) return $step + $d;
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        return 'otpauth://totp/' . $label . '?' . http_build_query(['secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1',
            'digits' => self::DIGITS, 'period' => self::PERIOD], '', '&', PHP_QUERY_RFC3986);
    }

    /** QR-Code als data:-URI (SVG) – für <img>, ohne Inline-Styles/Skripte im Dokument */
    public static function qrDataUri(string $text): string
    {
        $qr = new QRCode(new QROptions(['eccLevel' => EccLevel::M, 'addQuietzone' => true, 'quietzoneSize' => 2]));
        $qr->addByteSegment($text);
        $m = $qr->getQRMatrix()->getMatrix(true);
        $n = count($m);
        $d = '';
        foreach ($m as $y => $row) {
            foreach ($row as $x => $on) if ($on) $d .= "M{$x} {$y}h1v1h-1z";
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $n . ' ' . $n . '" shape-rendering="crispEdges">'
            . '<rect width="' . $n . '" height="' . $n . '" fill="#fff"/><path fill="#000" d="' . $d . '"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    // ---------------------------------------------------------------- Geheimnis verschlüsselt speichern

    private static function boxKey(string $appKey): string
    {
        return sodium_crypto_generichash('totp-secret|' . $appKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public static function seal(string $secret, string $appKey): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($secret, $nonce, self::boxKey($appKey)));
    }

    public static function open(?string $sealed, string $appKey): ?string
    {
        if (!$sealed || !str_starts_with($sealed, 'v1:')) return null;
        $raw = base64_decode(substr($sealed, 3), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::boxKey($appKey));
        return $plain === false ? null : $plain;
    }

    // ---------------------------------------------------------------- Wiederherstellungscodes

    /** @return array{0: list<string>, 1: string} Klartext-Codes (einmal anzeigen) und JSON der Hashes */
    public static function recoveryCodes(int $n = 10): array
    {
        $codes = [];
        for ($i = 0; $i < $n; $i++) {
            $codes[] = implode('-', str_split(self::base32(random_bytes(10)), 4));
        }
        return [$codes, json_encode(array_map([self::class, 'hashRecovery'], $codes))];
    }

    public static function hashRecovery(string $code): string
    {
        return hash('sha256', 'recovery|' . strtoupper(preg_replace('~[^A-Za-z2-7]~', '', $code)));
    }

    // ---------------------------------------------------------------- Prüfen (Code oder Wiederherstellungscode)

    /**
     * Prüft einen Code für einen Benutzer-Datensatz und vermerkt ihn (totp_last bzw. verbrauchter Wiederherstellungscode)
     * in der Datenbank, zu der das Konto gehört. @return 'code'|'recovery'|null
     */
    public static function verifyUser(Database $db, array $row, string $input, string $appKey): ?string
    {
        if (!(int) ($row['totp_enabled'] ?? 0)) return null;
        $input = trim($input);
        if (preg_match('~^\d[\d ]{4,8}$~', $input)) {
            $secret = self::open($row['totp_secret'] ?? null, $appKey);
            if ($secret === null) return null;
            $step = self::match($secret, $input);
            if ($step === null || $step <= (int) ($row['totp_last'] ?? 0)) return null;   // kein zweites Mal derselbe Code
            $db->update('users', ['totp_last' => $step], 'id = :id', ['id' => $row['id']]);
            return 'code';
        }
        return self::verifyRecovery($db, $row, $input);
    }

    /**
     * Nur Wiederherstellungscode prüfen und verbrauchen – auch für Konten, die nur Passkeys haben (Core\Passkeys).
     * @return 'recovery'|null
     */
    public static function verifyRecovery(Database $db, array $row, string $input): ?string
    {
        $list = json_decode((string) ($row['totp_recovery'] ?? ''), true) ?: [];
        $h = self::hashRecovery(trim($input));
        foreach ($list as $i => $stored) {
            if (is_string($stored) && hash_equals($stored, $h)) {
                unset($list[$i]);
                $db->update('users', ['totp_recovery' => json_encode(array_values($list))], 'id = :id', ['id' => $row['id']]);
                return 'recovery';
            }
        }
        return null;
    }

    private static function localHost(): bool
    {
        $h = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return PHP_SAPI === 'cli' || preg_match('~^(127\.0\.0\.1|localhost|[a-z0-9-]+\.localhost)(:\d+)?$~', $h) === 1;
    }

    /**
     * Verlangt diese Website für die Rolle die Zwei-Faktor-Anmeldung?
     * Netzwerk-Administration: Pflicht, abschaltbar nur für lokale Tests mit 'network_2fa' => false in config.local.php
     * (auf echten Domains in Produktion ignoriert). Wer 2FA eingerichtet hat, wird immer danach gefragt.
     */
    public static function required(string $role): bool
    {
        if ($role === 'network') return app()->config->get('network_2fa', true) !== false || environment() === 'production' && !self::localHost();
        // Richtlinie der Website (Core\Mfa: „verlangt“ oder „nur Passkey“; ältere Einstellung sys.twofa_roles als Rückfall)
        return isset(Mfa::policy()['roles'][$role]);
    }

    /**
     * Zwei-Faktor-Anmeldung (App und Passkeys) eines lokalen Kontos dieser Website zurücksetzen (Verwaltung „Benutzer & Rollen“, user:2fa-reset).
     * auth_ver steigt → alle Sitzungen des Kontos enden; verlangt die Rolle 2FA, wird sie bei der nächsten Anmeldung neu eingerichtet.
     */
    public static function reset(int $userId): void
    {
        $n = app()->db->query("UPDATE users SET totp_secret = NULL, totp_recovery = NULL, totp_last = NULL, totp_enabled = 0, auth_ver = auth_ver + 1
            WHERE id = ? AND role != 'network' AND (network_uid IS NULL OR network_uid = 0)", [$userId])->rowCount();
        // Passkeys des Kontos gehören dazu (Core\Passkeys)
        if ($n) Passkeys::deleteAll(app()->db, $userId);
    }
}
