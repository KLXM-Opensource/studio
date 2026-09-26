<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

/**
 * Einen ffmpeg-Lauf ausführen: Argument-Array über proc_open (ohne Shell), niedrige CPU-Priorität per `nice`,
 * Fortschritt aus `-progress pipe:1` (out_time_us), Abbruch auf Wunsch und nach Zeitlimit.
 */
final class Runner
{
    /**
     * @param list<string> $args  Argumente OHNE Programmpfad (Presets::…)
     * @param callable(int): void $progress  Prozent 1–99
     * @param callable(): bool $cancel  true = abbrechen
     * @return array{ok: bool, code: int, canceled: bool, timeout: bool, stderr: string, seconds: float}
     */
    public static function run(array $args, float $duration, callable $progress, callable $cancel, int $timeout): array
    {
        $bin = Ffmpeg::bin('ffmpeg');
        if ($bin === '' || !Ffmpeg::canExec()) throw new JobException(__('ffmpeg ist auf diesem Server nicht verfügbar.'));
        $argv = [$bin, ...array_map('strval', $args)];
        $nice = Ffmpeg::config()['nice'];
        if ($nice > 0 && ($n = self::niceBin()) !== '') $argv = [$n, '-n', (string) $nice, ...$argv];
        $t0 = microtime(true);
        // stdin als Pipe (sofort geschlossen) statt /dev/null – mit open_basedir (Plesk) wäre /dev/null gesperrt
        $p = @proc_open($argv, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT, Ffmpeg::env());
        if (!is_resource($p)) throw new JobException(__('ffmpeg konnte nicht gestartet werden.'));
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $buf = '';
        $err = '';
        $last = 0;
        $lastCheck = 0.0;
        $canceled = $timedOut = false;
        while (true) {
            $r = [$pipes[1], $pipes[2]];
            $w = $e = null;
            if (@stream_select($r, $w, $e, 0, 250000) === false) break;
            foreach ($r as $s) {
                $chunk = (string) fread($s, 65536);
                if ($s === $pipes[2]) { $err = substr($err . $chunk, -16384); continue; }
                $buf .= $chunk;
                while (($i = strpos($buf, "\n")) !== false) {
                    $line = substr($buf, 0, $i);
                    $buf = substr($buf, $i + 1);
                    if ($duration > 0 && preg_match('~^out_time_(?:us|ms)=(\d+)~', $line, $m)) {
                        $pct = (int) floor((int) $m[1] / 1_000_000 / $duration * 100);
                        $pct = max(1, min(99, $pct));
                        if ($pct !== $last) { $last = $pct; $progress($pct); }
                    }
                }
            }
            $st = proc_get_status($p);
            if (!$st['running']) {
                // Rest lesen
                $err = substr($err . (string) stream_get_contents($pipes[2]), -16384);
                break;
            }
            $now = microtime(true);
            if ($now - $lastCheck > 1.0) {
                $lastCheck = $now;
                if ($cancel()) { $canceled = true; break; }
            }
            if ($now - $t0 > $timeout) { $timedOut = true; break; }
        }
        if ($canceled || $timedOut) {
            proc_terminate($p, 15);
            usleep(300000);
            $st = proc_get_status($p);
            if ($st['running']) proc_terminate($p, 9);
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($p);
        if (isset($st) && !$st['running'] && $st['exitcode'] >= 0) $code = $st['exitcode'];
        return ['ok' => !$canceled && !$timedOut && $code === 0, 'code' => (int) $code, 'canceled' => $canceled, 'timeout' => $timedOut,
            'stderr' => trim($err), 'seconds' => round(microtime(true) - $t0, 1)];
    }

    private static function niceBin(): string
    {
        return \Core\Ffmpeg::hasNice() ? 'nice' : '';   // per Aufruf erkannt (open_basedir-fest, Core\Ffmpeg)
    }

    /** Befehl zur Anzeige (nur Administration, nur lesen): Pfade als Platzhalter, Argumente sicher zitiert */
    public static function display(array $args, array $replace = []): string
    {
        $parts = ['ffmpeg'];
        foreach ($args as $a) {
            $a = strtr((string) $a, $replace);
            $parts[] = preg_match('~^[\w@%+=:,./<>-]+$~u', $a) ? $a : "'" . str_replace("'", "'\\''", $a) . "'";
        }
        return implode(' ', $parts);
    }
}
