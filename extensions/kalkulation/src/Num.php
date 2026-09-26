<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Kalkulation;

/**
 * Zahlen lesen und deutsch ausgeben. Eingaben dürfen deutsch („1.234,50“) oder mit Punkt („1234.5“) kommen;
 * leer = null (kein Scheinwert). Gegenstück im Browser: assets/js/kalkulation.js (parseNum/fmt) – Regeln identisch halten.
 */
final class Num
{
    /** „1.234,5“ | „1234.5“ | „1.234“ (= 1234) | „12 %“ → float; leer/ungültig → null */
    public static function parse(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) return is_finite((float) $v) ? (float) $v : null;
        $s = trim(str_replace(["\u{00A0}", "\u{202F}", ' ', '€', '%'], '', (string) $v));
        if ($s === '') return null;
        if (str_contains($s, ',')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } elseif (preg_match('~^-?\d{1,3}(\.\d{3})+$~', $s)) {
            $s = str_replace('.', '', $s);   // „1.234“ = eintausendzweihundertvierunddreißig
        }
        if (!preg_match('~^-?\d+(\.\d+)?$~', $s)) return null;
        return (float) $s;
    }

    /** Ungültige Eingabe (nicht leer, aber keine Zahl)? */
    public static function invalid(mixed $v): bool
    {
        return trim((string) $v) !== '' && self::parse($v) === null;
    }

    /** 1234.5 → „1.234,50“ (Nachkommastellen fest) */
    public static function fmt(?float $v, int $dec = 2): string
    {
        return $v === null ? '' : number_format($v, $dec, ',', '.');
    }

    /** Für Eingabefelder: 1234.5 → „1.234,5“, 20.0 → „20“ (ohne überflüssige Nullen) */
    public static function input(?float $v): string
    {
        if ($v === null) return '';
        $s = number_format($v, 4, ',', '.');
        return rtrim(rtrim($s, '0'), ',');
    }

    /** Betrag mit Währung: „1.234,50 €“ (EUR), „CHF 1'234.50“ bleibt bewusst deutsch formatiert: „1.234,50 CHF“ */
    public static function money(?float $v, string $currency = 'EUR'): string
    {
        if ($v === null) return '–';
        return self::fmt($v) . "\u{00A0}" . self::symbol($currency);
    }

    public static function symbol(string $currency): string
    {
        return ['EUR' => '€', 'CHF' => 'CHF', 'USD' => 'US-$', 'GBP' => '£'][$currency] ?? $currency;
    }

    public static function pct(?float $v, int $dec = 1): string
    {
        return $v === null ? '–' : self::fmt($v, $dec) . "\u{00A0}%";
    }

    /** Kaufmännisch auf Cent (.5 vom Null weg) – im Browser dieselbe Formel (r2 in kalkulation.js) */
    public static function r2(float $v): float
    {
        $s = $v < 0 ? -1 : 1;
        return $s * floor(abs($v) * 100 + 0.5 + 1e-7) / 100;
    }
}
