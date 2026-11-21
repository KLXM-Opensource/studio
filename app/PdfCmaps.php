<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Zeichentabellen (CMaps) der PDF-Anzeige (pdf.js) – nicht im Download (1,6 MB), weil sie nur für chinesische, japanische und
 * koreanische PDFs ohne eingebettete Schrift gebraucht werden. `php bin/console pdfjs:cmaps` lädt sie einmal passend zur
 * mitgelieferten pdf.js-Version (public/assets/vendor/pdfjs/VERSION) von jsDelivr (npm-Paket pdfjs-dist, Apache-2.0) nach
 * public/assets/vendor/pdfjs/cmaps – danach liegen sie lokal, Besucher haben keinen Kontakt zu Dritten. `--remove` entfernt sie.
 * Ohne CMaps zeigt die Anzeige solche PDFs mit falschen Zeichen; alle anderen PDFs sind nicht betroffen.
 */
final class PdfCmaps
{
    public static function dir(): string
    {
        return ROOT . '/public/assets/vendor/pdfjs/cmaps';
    }

    public static function installed(): bool
    {
        return is_file(self::dir() . '/LICENSE') || (glob(self::dir() . '/*.bcmap') ?: []) !== [];
    }

    public static function console(array $args): int
    {
        $dir = self::dir();
        if (Cli::flag($args, 'remove')) {
            foreach (glob($dir . '/*') ?: [] as $f) if (is_file($f)) @unlink($f);
            @rmdir($dir);
            echo "Zeichentabellen entfernt.\n";
            return 0;
        }
        $ver = trim((string) @file_get_contents(ROOT . '/public/assets/vendor/pdfjs/VERSION'));
        if (!preg_match('~^\d+\.\d+\.\d+$~', $ver)) {
            fwrite(STDERR, "Version von pdf.js unbekannt (public/assets/vendor/pdfjs/VERSION fehlt) – erst bauen: cd tools && pnpm run build\n");
            return 1;
        }
        try {
            $list = Http\Fetch::get("https://data.jsdelivr.com/v1/package/npm/pdfjs-dist@$ver/flat", ['timeout' => 20, 'max' => 2_000_000]);
        } catch (Http\FetchException $e) {
            fwrite(STDERR, 'Liste nicht abrufbar: ' . $e->getMessage() . "\n");
            return 1;
        }
        $files = [];
        foreach ((array) (json_decode((string) $list['body'], true)['files'] ?? []) as $f) {
            $name = (string) ($f['name'] ?? '');
            if (preg_match('~^/cmaps/([A-Za-z0-9._-]+\.bcmap|LICENSE)$~', $name, $m)) $files[] = $m[1];
        }
        if (!$files) {
            fwrite(STDERR, "Keine Zeichentabellen für pdf.js $ver gefunden.\n");
            return 1;
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            fwrite(STDERR, "Ordner nicht anlegbar: $dir\n");
            return 1;
        }
        $ok = 0; $bytes = 0;
        foreach ($files as $f) {
            if (is_file("$dir/$f")) { $ok++; continue; }
            try {
                $r = Http\Fetch::get("https://cdn.jsdelivr.net/npm/pdfjs-dist@$ver/cmaps/" . rawurlencode($f), ['timeout' => 20, 'max' => 1_000_000]);
            } catch (Http\FetchException) {
                continue;
            }
            if ($r['status'] !== 200 || $r['body'] === '') continue;
            if (@file_put_contents("$dir/$f", $r['body'], LOCK_EX) !== false) { $ok++; $bytes += strlen($r['body']); }
        }
        printf("pdf.js %s: %d von %d Zeichentabellen vorhanden (%.1f MB neu geladen) – %s\n", $ver, $ok, count($files), $bytes / 1048576, $dir);
        return $ok === count($files) ? 0 : 1;
    }
}
