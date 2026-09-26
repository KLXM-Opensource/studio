<?php
declare(strict_types=1);

namespace Core;

/**
 * `php bin/console features:release [--site=key] [--dry-run] [--only=features|extensions]`
 *
 * Überträgt Funktionen und Erweiterungen, die in der Konfiguration der Website festgelegt sind ('preset', 'features',
 * 'extensions' in config/sites/{key}.php bzw. config/config.local.php), in die Schalter der Verwaltung
 * (sys.features_ui, sys.extensions_ui, Chat/Externe Quellen: deren Schalter) und entfernt sie aus der Datei.
 * Der wirksame Zustand bleibt gleich – danach steuert „Administration → Funktionen & Erweiterungen“.
 *
 * - Nur diese Schlüssel werden angefasst; Kommentare und alle übrigen Werte bleiben unverändert (Tokenizer, Sites::removeTopLevel).
 * - Erweiterungen mit 'required' => true im Manifest bleiben in der Konfiguration (fester Bestandteil der Website).
 * - Vor dem Schreiben: Sicherung {datei}.{zeitstempel}.bak, Prüfung der neuen Datei (Syntax + Ergebnis), Ausgabe als Diff.
 * - Werte aus config/config.php (versioniert, gilt für alle Websites) werden nicht angefasst, nur gemeldet.
 */
final class FeatureRelease
{
    /** @return int Exit-Code */
    public static function run(array $args, callable $out): int
    {
        $dry = in_array('--dry-run', $args, true);
        $only = '';
        foreach ($args as $a) if (str_starts_with($a, '--only=')) $only = substr($a, 7);
        if (!in_array($only, ['', 'features', 'extensions'], true)) {
            $out('Aufruf: features:release [--site=key] [--dry-run] [--only=features|extensions]');
            return 1;
        }
        $cfg = app()->config;
        $site = site()->key;
        $moveKeys = [];
        if ($only !== 'extensions') {
            if ($cfg->get('features') !== null) $moveKeys[] = 'features';
            if ($cfg->get('preset') !== null) $moveKeys[] = 'preset';
        }
        if ($only !== 'features' && $cfg->get('extensions') !== null) $moveKeys[] = 'extensions';
        if (!$moveKeys) {
            $out("Website „{$site}“: nichts festgelegt ('preset', 'features', 'extensions') – die Verwaltung steuert bereits alles.");
            return 0;
        }

        // Quelle je Schlüssel: Datei der Website; config/config.local.php nur in Einzel-Installationen (im Netzwerk gilt sie für
        // alle Websites); config/config.php (versioniert) bleibt unangetastet
        $byFile = [];
        $siteFile = 'config/sites/' . $site . '.php';
        $local = is_file(ROOT . '/config/config.local.php') ? (static fn(string $__f) => require $__f)(ROOT . '/config/config.local.php') : [];
        $base = (static fn(string $__f) => require $__f)(ROOT . '/config/config.php');
        foreach ($moveKeys as $k) {
            $found = false;
            if (array_key_exists($k, Sites::all()[$site] ?? []) && is_file(ROOT . '/' . $siteFile)) { $byFile[$siteFile][] = $k; $found = true; }
            if (is_array($local) && array_key_exists($k, $local)) {
                if (Sites::multi()) $out("  ! '{$k}' steht auch in config/config.local.php und gilt dort für alle Websites – bleibt, bitte bei Bedarf von Hand ändern.");
                else { $byFile['config/config.local.php'][] = $k; $found = true; }
            }
            if (is_array($base) && array_key_exists($k, $base)) $out("  ! '{$k}' steht in config/config.php (versioniert, gilt für alle Websites) – bleibt, bitte von Hand ändern.");
            elseif (!$found && !isset($byFile[$siteFile])) $out("  ! '{$k}': keine änderbare Quelle gefunden.");
        }
        if (!$byFile) return 1;

        // Neue Werte für die Verwaltung (wirksamer Zustand bleibt gleich)
        $features = [];
        $moving = array_merge(...array_values($byFile));
        if (in_array('preset', $moving, true)) {
            foreach (Features::PRESETS[(string) $cfg->get('preset', 'full')] ?? [] as $k => $v) $features[$k] = (bool) $v;
        }
        if (in_array('features', $moving, true)) {
            foreach ((array) $cfg->get('features', []) as $k => $v) $features[(string) $k] = (bool) $v;
        }
        $extUi = [];
        $keepExt = [];
        if (in_array('extensions', $moving, true)) {
            foreach (Extensions::configured() as $n => $on) {
                if ($on && !empty(Extensions::available()[$n]['required'])) $keepExt[] = $n;
                else $extUi[$n] = $on;
            }
        }

        // Dateien umschreiben (erst im Speicher, prüfen, Diff zeigen)
        $writes = [];
        foreach ($byFile as $rel => $keys) {
            $path = ROOT . '/' . $rel;
            $src = (string) file_get_contents($path);
            $new = $src;
            foreach (['features', 'preset', 'extensions'] as $k) {
                if (!in_array($k, $keys, true)) continue;
                if ($k === 'extensions' && $keepExt) {
                    $new = Sites::replaceTopLevel($new, 'extensions', '[' . implode(', ', array_map(fn($x) => var_export($x, true), $keepExt)) . ']');
                } else {
                    $new = Sites::removeTopLevel($new, $k, $k !== 'extensions');
                }
                if ($new === null) {
                    $out("  ✗ {$rel}: kein erkennbares return-Array – bitte von Hand ändern. Nichts geändert.");
                    return 1;
                }
            }
            if ($new === $src) continue;
            $err = self::verify($src, $new, $keys, $keepExt);
            if ($err !== null) {
                $out("  ✗ {$rel}: {$err} – nichts geändert.");
                return 1;
            }
            $writes[$rel] = [$path, $src, $new];
        }

        $out(($dry ? '[Probelauf] ' : '') . "Website „{$site}“: Konfiguration → Verwaltung");
        foreach ($features as $k => $v) $out(sprintf('  Funktion   %-18s %s', $k, $v ? 'an' : 'aus'));
        foreach ($extUi as $n => $v) $out(sprintf('  Erweiterung %-17s %s', $n, $v ? 'an' : 'aus'));
        foreach ($keepExt as $n) $out(sprintf('  Erweiterung %-17s bleibt in der Konfiguration (fester Bestandteil)', $n));
        foreach ($writes as $rel => [, $src, $new]) {
            $out("\n--- {$rel}\n+++ {$rel} (neu)");
            foreach (self::diff($src, $new) as $line) $out($line);
        }
        if ($dry) {
            $out("\nProbelauf – nichts geschrieben. Ohne --dry-run ausführen, um zu übernehmen.");
            return 0;
        }

        // 1. Schalter der Verwaltung setzen (Konfiguration gilt bis zum Umschreiben weiter – kein Zwischenzustand)
        foreach ($features as $k => $v) Features::setUi($k, $v);
        if ($extUi) {
            $ui = Extensions::ui();
            foreach ($extUi as $n => $v) $ui[$n] = $v;
            ksort($ui);
            app()->settings->set(Extensions::UI_KEY, $ui);
        }
        // 2. Dateien: Sicherung, neue Datei prüfen, atomar ersetzen
        foreach ($writes as $rel => [$path, $src, $new]) {
            $bak = $path . '.' . date('Ymd-His') . '.bak';
            if (@file_put_contents($bak, $src, LOCK_EX) === false) {
                $out("  ✗ Sicherung {$bak} ließ sich nicht schreiben – {$rel} bleibt unverändert (die Schalter der Verwaltung sind gesetzt, die Konfiguration gilt weiter).");
                return 1;
            }
            @chmod($bak, 0640);
            $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
            file_put_contents($tmp, $new, LOCK_EX);
            @chmod($tmp, fileperms($path) & 0777 ?: 0640);
            if (!@rename($tmp, $path)) {
                @unlink($tmp);
                $out("  ✗ {$rel} ließ sich nicht ersetzen (Schreibrechte?). Sicherung: {$bak}");
                return 1;
            }
            if (function_exists('opcache_invalidate')) @opcache_invalidate($path, true);
            $out("\n  ✓ {$rel} geändert, Sicherung: " . str_replace(ROOT . '/', '', $bak));
            FeatureLog::add('release', $rel, null, null, 'features: ' . count($features) . ', extensions: ' . count($extUi));
        }
        Sites::flush();
        PageCache::clear();
        // Werte aus anderen Dateien (config.local.php im Netzwerk, config.php) gelten weiter und sperren ihre Schalter
        foreach ($moving as $k) {
            $rest = array_key_exists($k, (array) $local) && Sites::multi() ? 'config/config.local.php' : (array_key_exists($k, (array) $base) ? 'config/config.php' : null);
            if ($rest) $out("  Hinweis: '{$k}' aus {$rest} gilt weiter: " . json_encode(($rest === 'config/config.php' ? $base : $local)[$k], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $out('Fertig. Ab jetzt schalten Sie unter Administration → Funktionen & Erweiterungen. Zurück: Sicherung zurückkopieren.');
        return 0;
    }

    /** Neue Datei prüfen: gültiges PHP, verschobene Schlüssel weg, alles andere unverändert */
    private static function verify(string $old, string $new, array $keys, array $keepExt): ?string
    {
        $tmpDir = ROOT . '/storage/cache';
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0770, true);
        $load = function (string $src) use ($tmpDir) {
            $f = $tmpDir . '/release-' . bin2hex(random_bytes(4)) . '.php';
            file_put_contents($f, $src);
            try {
                return (static fn(string $__f) => require $__f)($f);
            } finally {
                @unlink($f);
            }
        };
        try {
            $a = $load($old);
            $b = $load($new);
        } catch (\Throwable $e) {
            return 'geänderte Datei ist ungültig (' . $e->getMessage() . ')';
        }
        if (!is_array($a) || !is_array($b)) return 'Datei liefert kein Array';
        foreach ($keys as $k) {
            if ($k === 'extensions' && $keepExt) {
                if (array_values((array) ($b['extensions'] ?? [])) !== $keepExt) return "'extensions' nicht wie erwartet";
                unset($a[$k], $b[$k]);
            } else {
                if (array_key_exists($k, $b)) return "'{$k}' ist noch vorhanden";
                unset($a[$k]);
            }
        }
        return $a == $b ? null : 'übrige Werte würden sich ändern';
    }

    /** Einfacher Zeilen-Diff (LCS) mit 2 Zeilen Kontext */
    public static function diff(string $a, string $b): array
    {
        $x = explode("\n", $a);
        $y = explode("\n", $b);
        $n = count($x);
        $m = count($y);
        $l = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $l[$i][$j] = $x[$i] === $y[$j] ? $l[$i + 1][$j + 1] + 1 : max($l[$i + 1][$j], $l[$i][$j + 1]);
            }
        }
        $ops = [];
        $i = $j = 0;
        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $x[$i] === $y[$j]) { $ops[] = [' ', $x[$i]]; $i++; $j++; }
            elseif ($j < $m && ($i >= $n || $l[$i][$j + 1] >= $l[$i + 1][$j])) { $ops[] = ['+', $y[$j]]; $j++; }
            else { $ops[] = ['-', $x[$i]]; $i++; }
        }
        $show = [];
        foreach ($ops as $k => [$t]) {
            if ($t !== ' ') for ($c = max(0, $k - 2); $c <= min(count($ops) - 1, $k + 2); $c++) $show[$c] = true;
        }
        $out = [];
        $last = -1;
        foreach ($ops as $k => [$t, $line]) {
            if (!isset($show[$k])) continue;
            if ($last !== -1 && $k !== $last + 1) $out[] = '@@';
            $out[] = $t . ' ' . $line;
            $last = $k;
        }
        return $out;
    }
}
