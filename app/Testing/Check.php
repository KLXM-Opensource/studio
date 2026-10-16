<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Testing;

/**
 * Vergleich für die Selbsttests (*:selftest): zählt Treffer in $ok und sammelt Abweichungen in $fails.
 *
 *   $ok = 0; $fails = [];
 *   $eq = Check::eq($ok, $fails);                 // optional: Präfix der Meldung, verbose (✓/✗ je Prüfung ausgeben)
 *   $eq('Summe', 1 + 1, 2);
 *   return ['ok' => $ok, 'fails' => $fails];
 */
final class Check
{
    /** @return \Closure(string, mixed, mixed): void */
    public static function eq(int &$ok, array &$fails, string $prefix = '', bool $verbose = false): \Closure
    {
        return function (string $what, mixed $got, mixed $want) use (&$ok, &$fails, $prefix, $verbose): void {
            if ($got === $want) {
                $ok++;
                if ($verbose) echo "  ✓ $what\n";
                return;
            }
            $msg = $prefix . $what . ': erwartet ' . self::show($want) . ', erhalten ' . self::show($got);
            $fails[] = $verbose ? $what : $msg;
            if ($verbose) echo "  ✗ $msg\n";
        };
    }

    /** Wert lesbar für Meldungen */
    public static function show(mixed $v): string
    {
        return is_string($v) || is_array($v) ? (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : var_export($v, true);
    }
}
