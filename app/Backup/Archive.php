<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Backup;

/** tar.gz aus einer Dateiliste packen und wieder entpacken – System-tar bevorzugt (streamt, ohne Shell), sonst PharData */
final class Archive
{
    /** @param array<string, string> $files Pfad im Archiv => Pfad auf der Platte */
    public static function pack(array $files, string $out): string
    {
        $tar = self::tar();
        if ($tar) {
            // Bühne aus harten Links bzw. Kopien: tar liest relative Pfade aus einem Ordner (-C), Liste per Datei (-T)
            $stage = dirname($out) . '/.stage-' . bin2hex(random_bytes(4));
            @mkdir($stage, 0700, true);
            foreach ($files as $rel => $abs) {
                @mkdir(dirname("$stage/$rel"), 0700, true);
                if (!@link($abs, "$stage/$rel")) copy($abs, "$stage/$rel");
            }
            file_put_contents("$stage.list", implode("\n", array_keys($files)) . "\n");
            $p = proc_open([$tar, '-czf', $out, '-C', $stage, '-T', "$stage.list"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            stream_get_contents($pipes[1]); $err = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]); fclose($pipes[2]);
            $ok = proc_close($p) === 0 && is_file($out);
            self::remove($stage);
            @unlink("$stage.list");
            if ($ok) return $out;
            @unlink($out);
            error_log('[backup] tar fehlgeschlagen: ' . $err);
        }
        $plain = preg_replace('~\.gz$~', '', $out);
        $phar = new \PharData($plain);
        foreach ($files as $rel => $abs) $phar->addFile($abs, $rel);
        unset($phar);
        return $plain;
    }

    public static function unpack(string $file, string $dir): void
    {
        @mkdir($dir, 0700, true);
        if ($tar = self::tar()) {
            $p = proc_open([$tar, str_ends_with($file, '.gz') ? '-xzf' : '-xf', $file, '-C', $dir], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            if (proc_close($p) === 0) return;
        }
        (new \PharData($file))->extractTo($dir, null, true);
    }

    /** Arbeitsordner entfernen (nur unter storage/ bzw. dem Ordner einer Bühne; Links werden nie verfolgt) */
    public static function remove(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) return;
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            $p = "$dir/$f";
            is_dir($p) && !is_link($p) ? self::remove($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private static function tar(): ?string
    {
        if (!function_exists('proc_open')) return null;
        foreach (['/usr/bin/tar', '/bin/tar', '/usr/local/bin/tar'] as $b) if (is_executable($b)) return $b;
        return null;
    }
}
