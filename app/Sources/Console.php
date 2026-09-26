<?php
declare(strict_types=1);

namespace Core\Sources;

use Core\Sites;

/**
 * Kommandozeile der externen Quellen (bin/console):
 *   sources:sync [--all] [--source=ID] [--force]   fällige Quellen abgleichen (--source: nur diese, sofort; --force: alle aktiven, Zwischenspeicher umgehen)
 *   sources:list [--all]                            Quellen mit Stand
 * Cron (empfohlen): *\/15 * * * * php bin/console sources:sync --all
 */
final class Console
{
    public static function run(string $cmd, array $args, string $script): int
    {
        if (in_array('--all', $args, true)) {
            $rest = array_values(array_filter($args, fn($a) => $a !== '--all'));
            $fail = 0;
            foreach (array_keys(Sites::all()) as $k) {
                echo "── $k\n";
                passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . $cmd . ' ' . implode(' ', array_map('escapeshellarg', $rest)) . ' --site=' . escapeshellarg($k), $code);
                $fail = $fail ?: $code;
            }
            return $fail;
        }
        $opt = function (string $name) use ($args): ?string {
            foreach ($args as $a) {
                if ($a === '--' . $name) return '';
                if (str_starts_with($a, '--' . $name . '=')) return substr($a, strlen($name) + 3);
            }
            return null;
        };
        if (!Sources::enabled()) {
            echo "Externe Quellen sind auf dieser Website ausgeschaltet (" . site()->key . ", Funktion „sources“).\n";
            return 0;
        }
        return match ($cmd) {
            'sources:list' => self::list(),
            default => self::sync($opt('source'), $opt('force') !== null),
        };
    }

    private static function list(): int
    {
        $all = Sources::all();
        if (!$all) { echo "Keine Quellen (" . site()->key . ").\n"; return 0; }
        foreach ($all as $s) {
            printf("#%-4d %-32s %-9s %-7s %4d Einträge  %s\n", $s['id'], mb_strimwidth($s['name'], 0, 32, '…'), $s['format'], $s['active'] ? $s['options']['schedule'] : 'pausiert',
                $s['items'], $s['last_error'] ? '✗ ' . mb_strimwidth((string) $s['last_error'], 0, 70, '…') : ($s['last_ok_at'] ? '✓ ' . $s['last_ok_at'] : '– noch nie'));
        }
        return 0;
    }

    private static function sync(?string $only, bool $force): int
    {
        $print = function (array $src, array $s): void {
            echo ($s['ok'] ? ($s['failed'] ? '  ! ' : '  ✓ ') : '  ✗ ') . '#' . $src['id'] . ' ' . $src['name'] . ': ' . $s['message'] . "\n";
            foreach (array_slice($s['details'], 0, 5) as $d) echo "      $d\n";
        };
        if ($only !== null && $only !== '') {
            $src = Sources::find((int) $only);
            if (!$src) { fwrite(STDERR, "Quelle #$only gibt es auf " . site()->key . " nicht.\n"); return 1; }
            $s = Sync::run($src, 'cron', ['force' => true]);
            $print($src, $s);
            return $s['ok'] ? 0 : 1;
        }
        if ($force) {
            $bad = 0;
            foreach (Sources::all() as $src) {
                if (!$src['active']) continue;
                $s = Sync::run($src, 'cron', ['force' => true]);
                $print($src, $s);
                $bad += $s['ok'] ? 0 : 1;
            }
            return $bad ? 1 : 0;
        }
        $done = Sync::runDue('cron', 0.0, $print);
        if (!$done) echo "Keine fälligen Quellen (" . site()->key . ").\n";
        return count(array_filter($done, fn($s) => !$s['ok'])) ? 1 : 0;
    }
}
