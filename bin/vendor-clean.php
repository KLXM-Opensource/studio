<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

/**
 * Testdaten von Abhängigkeiten entfernen (Composer: post-install-cmd, post-update-cmd).
 * Manche Pakete liefern ihre Tests im Dist-Archiv mit – allein wamania/php-stemmer (über loupe/loupe) rund 17 MB.
 * Zur Laufzeit braucht sie niemand. Nur feste Pfade innerhalb von vendor/, keine Muster.
 * Aufruf von Hand: php bin/vendor-clean.php
 */
$vendor = dirname(__DIR__) . '/vendor';
$dirs = [
    'wamania/php-stemmer/test',
    'toflar/fast-set/tests',
    'mjaschen/phpgeo/tests',
    'sabre/http/tests',
];

$rm = function (string $path) use (&$rm): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    foreach (scandir($path) ?: [] as $e) {
        if ($e !== '.' && $e !== '..') $rm($path . '/' . $e);
    }
    @rmdir($path);
};

$freed = 0;
foreach ($dirs as $d) {
    $path = $vendor . '/' . $d;
    if (!is_dir($path) || is_link($path)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) $freed += $f->isFile() ? $f->getSize() : 0;
    $rm($path);
}
if ($freed > 0) fwrite(STDOUT, sprintf("vendor-clean: %.1f MB Testdaten entfernt\n", $freed / 1048576));
