<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Backup;

/**
 * Verschlüsselung der Sicherungen, bevor sie den Server verlassen (Core\Backup\Offsite) – libsodium secretstream
 * (XChaCha20-Poly1305), in Stücken von 1 MB, auch für große Dateien ohne viel Speicher.
 *
 * Schlüssel: 32 Byte, zufällig, einmal je Installation (storage/backup-offsite/key, nur für den Eigentümer lesbar). Er gehört
 * NICHT in die Sicherung selbst – ohne ihn lässt sich nichts entschlüsseln. Die Verwaltung (Grundeinstellungen → Sicherung
 * außer Haus) zeigt ihn als Text „kbk1.…“ zum Aufbewahren an einem anderen Ort (Passwort-Manager, Tresor).
 *
 * Dateiformat .kbk: Kennung „KLXMBK1\n“, 24 Byte Kopf des Streams, dann je Stück 4 Byte Länge (big-endian) + Chiffrat.
 * Entschlüsseln: php bin/console backup:decrypt <datei.kbk> --key="kbk1.…" (läuft auch auf einer frischen Installation).
 */
final class Vault
{
    public const MAGIC = "KLXMBK1\n";
    public const EXT = '.kbk';
    private const CHUNK = 1048576;

    /** Ordner der Einstellungen und des Schlüssels (nicht öffentlich, nicht in der Installations-Sicherung) */
    public static function dir(): string
    {
        return ROOT . '/storage/backup-offsite';
    }

    /** Schlüssel der Installation (roh, 32 Byte) – legt ihn beim ersten Bedarf an */
    public static function key(): string
    {
        $f = self::dir() . '/key';
        if (!is_file($f)) {
            @mkdir(self::dir(), 0700, true);
            file_put_contents($f, self::export(sodium_crypto_secretstream_xchacha20poly1305_keygen()), LOCK_EX);
            @chmod($f, 0600);
        }
        $k = self::import(trim((string) file_get_contents($f)));
        if ($k === null) throw new \RuntimeException('Schlüssel der Sicherungen ist beschädigt (storage/backup-offsite/key).');
        return $k;
    }

    public static function hasKey(): bool
    {
        return is_file(self::dir() . '/key');
    }

    /** Schlüssel als Text zum Aufbewahren: „kbk1.“ + Base64url */
    public static function export(string $raw): string
    {
        return 'kbk1.' . rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /** Text „kbk1.…“ → roher Schlüssel; null bei falschem Format */
    public static function import(string $text): ?string
    {
        $text = trim($text);
        if (!str_starts_with($text, 'kbk1.')) return null;
        $raw = base64_decode(strtr(substr($text, 5), '-_', '+/'), true);
        return $raw !== false && strlen($raw) === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES ? $raw : null;
    }

    /** Kurzer Fingerabdruck des Schlüssels (zum Abgleich, verrät ihn nicht) */
    public static function fingerprint(?string $raw = null): string
    {
        return substr(bin2hex(sodium_crypto_generichash('kbk-fingerprint|' . ($raw ?? self::key()), '', 16)), 0, 12);
    }

    /** Datei verschlüsseln → $out */
    public static function encryptFile(string $in, string $out, ?string $key = null): void
    {
        $src = fopen($in, 'rb');
        $dst = fopen($out, 'wb');
        if (!$src || !$dst) throw new \RuntimeException('Datei für die Verschlüsselung nicht lesbar/schreibbar.');
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key ?? self::key());
        fwrite($dst, self::MAGIC . $header);
        $size = (int) filesize($in);
        $done = 0;
        do {
            $chunk = (string) fread($src, self::CHUNK);
            $done += strlen($chunk);
            $last = $done >= $size || feof($src);
            $c = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, '',
                $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
            fwrite($dst, pack('N', strlen($c)) . $c);
        } while (!$last);
        fclose($src);
        fclose($dst);
    }

    /** Datei entschlüsseln → $out; Ausnahme bei falschem Schlüssel oder veränderter Datei */
    public static function decryptFile(string $in, string $out, ?string $key = null): void
    {
        $src = fopen($in, 'rb');
        if (!$src || fread($src, strlen(self::MAGIC)) !== self::MAGIC) throw new \RuntimeException('Keine verschlüsselte Sicherung (.kbk).');
        $header = (string) fread($src, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key ?? self::key());
        $dst = fopen($out, 'wb');
        $final = false;
        while (!$final) {
            $len = fread($src, 4);
            if ($len === false || strlen($len) < 4) break;
            $n = (int) unpack('N', $len)[1];
            $res = sodium_crypto_secretstream_xchacha20poly1305_pull($state, (string) fread($src, $n));
            if ($res === false) { fclose($dst); @unlink($out); throw new \RuntimeException('Entschlüsseln fehlgeschlagen – falscher Schlüssel oder beschädigte Datei.'); }
            [$plain, $tag] = $res;
            fwrite($dst, $plain);
            $final = $tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }
        fclose($src);
        fclose($dst);
        if (!$final) { @unlink($out); throw new \RuntimeException('Sicherung unvollständig (Ende fehlt).'); }
    }
}
