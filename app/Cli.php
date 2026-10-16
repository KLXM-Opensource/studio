<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Kleine Helfer für die Kommandozeile (bin/console, Befehle von Kern, Kits und Erweiterungen).
 *
 *   $out  = Cli::opt($args, 'out') ?? 'standard';     // --out=wert (letzte Angabe gilt)
 *   $all  = Cli::flag($args, 'all');                   // --all
 *   [$key] = Cli::positional($args);                   // Werte ohne „--“
 *   exit(Cli::eachSite('cache:clear'));                 // Befehl für jede Website der Installation (--site=…)
 */
final class Cli
{
    /** Wert einer Option --name=wert (letzte Angabe gilt) oder null */
    public static function opt(array $args, string $name): ?string
    {
        $v = null;
        foreach ($args as $a) if (str_starts_with((string) $a, '--' . $name . '=')) $v = substr((string) $a, strlen($name) + 3);
        return $v;
    }

    /** Schalter --name gesetzt? */
    public static function flag(array $args, string $name): bool
    {
        return in_array('--' . $name, $args, true);
    }

    /** Argumente ohne „-“ am Anfang, in Reihenfolge */
    public static function positional(array $args): array
    {
        return array_values(array_filter($args, fn($a) => !str_starts_with((string) $a, '-')));
    }

    /**
     * Befehl für jede Website als eigenen Prozess ausführen (bin/console {cmd} {args} --site={key}).
     * $header: „── key“ vor jeder Website ausgeben. Rückgabe: erster Fehlercode ≠ 0, sonst 0.
     */
    public static function eachSite(string $cmd, array $args = [], bool $header = true): int
    {
        $code = 0;
        $pass = implode('', array_map(fn($a) => ' ' . escapeshellarg((string) $a), array_values(array_filter($args, fn($a) => $a !== '--all' && !str_starts_with((string) $a, '--site=')))));
        foreach (array_keys(Sites::all()) as $k) {
            if ($header) echo "── $k\n";
            passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT . '/bin/console') . ' ' . escapeshellarg($cmd) . $pass . ' --site=' . escapeshellarg((string) $k), $c);
            if ($c !== 0 && $code === 0) $code = (int) $c;
        }
        return $code;
    }
}
