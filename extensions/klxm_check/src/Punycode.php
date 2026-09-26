<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * Punycode nach RFC 3492 – Rückfall, wenn die PHP-Erweiterung intl (idn_to_ascii) fehlt.
 * Nur Kodierung einzelner Labels (Unicode → xn--…); Kleinschreibung übernimmt der Aufrufer (mb_strtolower).
 * Kein vollständiges UTS-46-Mapping – für übliche Umlaut-Domains (münchen.de, bücher.example) reicht das.
 */
final class Punycode
{
    private const BASE = 36, TMIN = 1, TMAX = 26, SKEW = 38, DAMP = 700, INITIAL_BIAS = 72, INITIAL_N = 128;

    public static function label(string $label): string
    {
        if (preg_match('~^[\x00-\x7f]*$~', $label)) return $label;
        $cps = self::codepoints($label);
        $out = '';
        foreach ($cps as $c) if ($c < 0x80) $out .= chr($c);
        $b = $h = strlen($out);
        if ($b > 0) $out .= '-';
        $n = self::INITIAL_N;
        $delta = 0;
        $bias = self::INITIAL_BIAS;
        $len = count($cps);
        while ($h < $len) {
            $m = PHP_INT_MAX;
            foreach ($cps as $c) if ($c >= $n && $c < $m) $m = $c;
            $delta += ($m - $n) * ($h + 1);
            $n = $m;
            foreach ($cps as $c) {
                if ($c < $n) $delta++;
                if ($c === $n) {
                    $q = $delta;
                    for ($k = self::BASE; ; $k += self::BASE) {
                        $t = $k <= $bias ? self::TMIN : ($k >= $bias + self::TMAX ? self::TMAX : $k - $bias);
                        if ($q < $t) break;
                        $out .= self::digit($t + ($q - $t) % (self::BASE - $t));
                        $q = intdiv($q - $t, self::BASE - $t);
                    }
                    $out .= self::digit($q);
                    $bias = self::adapt($delta, $h + 1, $h === $b);
                    $delta = 0;
                    $h++;
                }
            }
            $delta++;
            $n++;
        }
        return 'xn--' . $out;
    }

    private static function codepoints(string $s): array
    {
        $out = [];
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) $out[] = mb_ord($ch, 'UTF-8');
        return $out;
    }

    private static function digit(int $d): string
    {
        return chr($d + 22 + 75 * ($d < 26 ? 1 : 0));
    }

    private static function adapt(int $delta, int $numPoints, bool $first): int
    {
        $delta = $first ? intdiv($delta, self::DAMP) : intdiv($delta, 2);
        $delta += intdiv($delta, $numPoints);
        $k = 0;
        while ($delta > intdiv((self::BASE - self::TMIN) * self::TMAX, 2)) {
            $delta = intdiv($delta, self::BASE - self::TMIN);
            $k += self::BASE;
        }
        return $k + intdiv((self::BASE - self::TMIN + 1) * $delta, $delta + self::SKEW);
    }
}
