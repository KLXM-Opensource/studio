<?php
declare(strict_types=1);

namespace Core;

/**
 * IBAN (ISO 13616): Normalisieren, Prüfen, Formatieren, Maskieren.
 * Gespeichert wird die Kurzform ohne Leerzeichen in Großbuchstaben („DE89370400440532013000“),
 * angezeigt in Vierergruppen („DE89 3704 0044 0532 0130 00“).
 */
final class Iban
{
    /** Länge je Land (SEPA-Raum) */
    public const LENGTHS = [
        'AD' => 24, 'AL' => 28, 'AT' => 20, 'BE' => 16, 'BG' => 22, 'CH' => 21, 'CY' => 28, 'CZ' => 24, 'DE' => 22, 'DK' => 18,
        'EE' => 20, 'ES' => 24, 'FI' => 18, 'FR' => 27, 'GB' => 22, 'GI' => 23, 'GR' => 27, 'HR' => 21, 'HU' => 28, 'IE' => 22,
        'IS' => 26, 'IT' => 27, 'LI' => 21, 'LT' => 20, 'LU' => 20, 'LV' => 21, 'MC' => 27, 'MD' => 24, 'ME' => 22, 'MK' => 19,
        'MT' => 31, 'NL' => 18, 'NO' => 15, 'PL' => 28, 'PT' => 25, 'RO' => 24, 'SE' => 24, 'SI' => 19, 'SK' => 24, 'SM' => 27,
        'VA' => 22,
    ];

    public static function normalize(string $s): string
    {
        return strtoupper((string) preg_replace('~[\s\-.]+~u', '', trim($s)));
    }

    /** null = gültig, sonst Fehlercode: format | country | length | checksum */
    public static function error(string $iban): ?string
    {
        if (!preg_match('~^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$~', $iban)) return 'format';
        $len = self::LENGTHS[substr($iban, 0, 2)] ?? null;
        if ($len === null) return 'country';
        if (strlen($iban) !== $len) return 'length';
        return self::checksum($iban) ? null : 'checksum';
    }

    /** Prüfziffer nach ISO 7064 Mod 97-10 */
    public static function checksum(string $iban): bool
    {
        $moved = substr($iban, 4) . substr($iban, 0, 4);
        $rest = 0;
        foreach (str_split($moved) as $c) {
            $n = ctype_alpha($c) ? (string) (ord($c) - 55) : $c;
            foreach (str_split($n) as $digit) {
                $rest = ($rest * 10 + (int) $digit) % 97;
            }
        }
        return $rest === 1;
    }

    public static function format(string $iban): string
    {
        return trim(chunk_split(self::normalize($iban), 4, ' '));
    }

    /** Maskiert: Land, Prüfziffer und die letzten zwei Zeichen bleiben sichtbar („DE89 **** **** **** **** 00“) */
    public static function mask(string $iban): string
    {
        $i = self::normalize($iban);
        if (strlen($i) < 8) return str_repeat('*', strlen($i));
        return self::format(substr($i, 0, 4) . str_repeat('*', strlen($i) - 6) . substr($i, -2));
    }
}
