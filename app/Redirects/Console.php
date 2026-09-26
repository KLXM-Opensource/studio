<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Redirects;

/**
 * Kommandozeile der Weiterleitungen (bin/console, je Website mit --site=…):
 *   redirects:import <datei.json|csv> [--dry-run] [--overwrite] [--keep-paths]
 *   redirects:list [--q=text] [--limit=N]
 *   redirects:test <pfad>
 *   redirects:selftest                          Normalisieren und Vergleichen prüfen (ohne Datenbank)
 */
final class Console
{
    public static function run(string $cmd, array $args): int
    {
        $opt = function (string $name) use ($args): ?string {
            foreach ($args as $a) {
                if ($a === '--' . $name) return '';
                if (str_starts_with($a, '--' . $name . '=')) return substr($a, strlen($name) + 3);
            }
            return null;
        };
        $pos = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));
        if ($cmd === 'redirects:selftest') return self::selftest();
        if (!Redirects::enabled()) {
            echo "Weiterleitungen sind auf dieser Website ausgeschaltet (" . site()->key . ", Funktion „redirects“).\n";
            return $cmd === 'redirects:list' ? 0 : 1;
        }
        return match ($cmd) {
            'redirects:import' => self::import($pos[0] ?? '', $opt('dry-run') !== null, $opt('overwrite') !== null, $opt('keep-paths') === null),
            'redirects:test' => self::test($pos[0] ?? ''),
            default => self::list((string) $opt('q'), (int) ($opt('limit') ?? 500)),
        };
    }

    private static function import(string $file, bool $dry, bool $overwrite, bool $link): int
    {
        if ($file === '' || !is_file($file)) {
            fwrite(STDERR, "Aufruf: redirects:import <datei.json|datei.csv> [--dry-run] [--overwrite] [--keep-paths] [--site=key]\n");
            return 1;
        }
        try {
            $rows = Redirects::parse((string) file_get_contents($file), $file);
        } catch (\RuntimeException $e) {
            fwrite(STDERR, $e->getMessage() . "\n");
            return 1;
        }
        $res = Redirects::import($rows, ['dry' => $dry, 'overwrite' => $overwrite, 'link' => $link]);
        foreach ($res['errors'] as $line => $msg) echo "  Zeile $line: $msg\n";
        printf("%s%d Zeilen: %d neu, %d geändert, %d übersprungen, %d Ziele mit Seiten verknüpft (%s).\n", $dry ? 'Probelauf – ' : '',
            $res['total'], $res['created'], $res['updated'], $res['skipped'], $res['linked'], site()->key);
        return 0;
    }

    private static function list(string $q, int $limit): int
    {
        $l = Redirects::list(['q' => $q, 'per' => max(10, min(500, $limit))]);
        if (!$l['rows']) { echo "Keine Weiterleitungen (" . site()->key . ").\n"; return 0; }
        foreach ($l['rows'] as $r) {
            printf("#%-5d %s %-3d %-44s → %-40s %5d  %s\n", $r['id'], (int) $r['active'] ? ' ' : '-', $r['code'], mb_strimwidth((string) $r['source'], 0, 44, '…'),
                mb_strimwidth((int) $r['code'] === 410 ? '(entfernt)' : (string) $r['target'], 0, 40, '…'), $r['hits'], $r['origin']);
        }
        echo $l['total'] . " Weiterleitung(en)" . ($l['total'] > count($l['rows']) ? ', gezeigt ' . count($l['rows']) : '') . ".\n";
        return 0;
    }

    private static function test(string $path): int
    {
        if ($path === '') { fwrite(STDERR, "Aufruf: redirects:test <pfad> [--site=key]\n"); return 1; }
        $x = Redirects::explain($path);
        echo $x['path'] . "\n  " . $x['status'] . ': ' . $x['text'] . "\n";
        if ($x['location']) echo '  Location: ' . Redirects::encodeLocation((string) $x['location']) . "\n";
        return 0;
    }

    private static function selftest(): int
    {
        $res = SelfTest::run();
        foreach ($res['fails'] as $f) echo "  FEHLER: $f\n";
        echo "  {$res['ok']} Prüfungen bestanden, " . count($res['fails']) . " fehlgeschlagen\n";
        return $res['fails'] ? 1 : 0;
    }
}
