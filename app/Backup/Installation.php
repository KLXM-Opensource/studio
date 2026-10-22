<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Backup;

use Core\Extensions;
use Core\Kit;

/**
 * Sicherung der Installation – alles, was zum Wiederherstellen nötig ist und NICHT in den Sicherungen der Websites, Pools und
 * geteilten Tabellen steckt (site:backup, pool:backup, shared:backup), und NICHT der Kern selbst (der kommt neu aus GitHub):
 *
 *  - config/config.local.php (Schlüssel der Hauptwebsite, network_key) und config/sites/*.php
 *  - storage/: Daten des Kerns und der Erweiterungen (Support, KI-Konfiguration und -Verbrauch, Chat, Avatare, hochgeladene
 *    Kits, Statistik einer Erweiterung …) – ohne Caches, Logs, Sitzungen, Suchindex, Sicherungen und ohne das, was die
 *    anderen Sicherungen schon enthalten (storage/sites, storage/database, storage/pools, storage/shared).
 *    Erweiterungen nehmen eigene Caches heraus: $ext->backupSkip('meine-erweiterung/cache').
 *  - Eigene Kits und Erweiterungen (Ordner unter kits/ und extensions/, die nicht zum Kern gehören) – Code, ohne node_modules.
 *  - composer.json/composer.lock (über Composer installierte Pakete), project/ (Projekt-Überschreibungen), falls vorhanden.
 *  - manifest.json: Kern-Version, Websites, Kits und Erweiterungen mit Herkunft und Version, eingebundene Anwendungen.
 *
 * Einspielen: php bin/console backup:restore-install <install-….tar.gz> --force (Konfiguration und storage/), danach je Website
 * site:restore, pool:restore, shared:restore – Ablauf im Entwicklerhandbuch → Betrieb → „Sicherung außer Haus“.
 */
final class Installation
{
    /** Kits und Erweiterungen, die das Kern-Repository mitbringt (backup:selftest gleicht mit git ls-files ab) */
    public const BUNDLED_KITS = ['basis', 'editorial', 'essenz', 'fluid', 'foto', 'frameworks', 'galerie', 'glas', 'modern', 'nature', 'praxis', 'starter'];
    public const BUNDLED_EXTENSIONS = ['consent_kit', 'dav', 'video_tools'];

    /**
     * Ordner unter storage/, die nicht hineingehören: abgeleitet oder flüchtig (Cache, Logs, Sitzungen, Suchindex, Präsenz, Sperren),
     * eigene Sicherungen oder schon in den Website-/Pool-/Tabellen-Sicherungen enthalten.
     */
    public const SKIP = ['backups', 'backup-offsite', 'cache', 'logs', 'sessions', 'tmp', 'search', 'live', 'presence', 'integrity', 'kit-test',
        'visual-diff', 'uploads', 'video', 'cron', 'pdf-fonts', 'sites', 'database', 'pools', 'shared', 'ai/help.sqlite'];
    /** Ordner- und Dateinamen, die an jeder Stelle übersprungen werden */
    private const SKIP_NAMES = ['cache', 'tmp', 'node_modules', '.git', '.DS_Store'];

    /** Zusätzliche Ausnahmen der Erweiterungen (Extension::backupSkip) – Pfade relativ zu storage/ */
    public static function skips(): array
    {
        $out = self::SKIP;
        foreach (Extensions::active() as $x) foreach ($x->backupSkip as $p) $out[] = trim(str_replace('..', '', (string) $p), '/');
        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Zu sichernde Dateien: [Pfad im Archiv => Pfad auf der Platte]
     * @param bool $code eigene Kits und Erweiterungen (nicht aus dem Kern) mitsichern
     */
    public static function files(bool $code = true): array
    {
        $out = [];
        $add = function (string $abs, string $rel) use (&$out): void {
            if (is_file($abs) && !is_link($abs)) $out[$rel] = $abs;
        };
        $walk = function (string $absDir, string $relDir, array $skip = []) use (&$walk, &$out): void {
            foreach (scandir($absDir) ?: [] as $f) {
                // Sperrdateien (jobs.lock …) überspringen – composer.lock gehört zum Code einer Erweiterung
                if ($f === '.' || $f === '..' || in_array($f, self::SKIP_NAMES, true) || (str_ends_with($f, '.lock') && $f !== 'composer.lock')) continue;
                $abs = "$absDir/$f";
                $rel = ltrim("$relDir/$f", '/');
                if (in_array($rel, $skip, true) || is_link($abs)) continue;
                if (is_dir($abs)) $walk($abs, $rel, $skip);
                elseif (is_file($abs)) $out[$rel] = $abs;
            }
        };
        $add(ROOT . '/config/config.local.php', 'config/config.local.php');
        foreach (glob(ROOT . '/config/sites/*.php') ?: [] as $f) $add($f, 'config/sites/' . basename($f));
        $skip = array_map(fn($p) => 'storage/' . $p, self::skips());
        if (is_dir(ROOT . '/storage')) $walk(ROOT . '/storage', 'storage', $skip);
        foreach (['composer.json', 'composer.lock'] as $f) $add(ROOT . "/$f", $f);
        if (is_dir(ROOT . '/project')) $walk(ROOT . '/project', 'project');
        if ($code) {
            foreach (self::ownCode() as $rel) $walk(ROOT . "/$rel", $rel);
        }
        ksort($out);
        return $out;
    }

    /** Eigene Kits und Erweiterungen im Code-Stand (nicht aus dem Kern, nicht über Composer): ['kits/klxm', 'extensions/kalkulation', …] */
    public static function ownCode(): array
    {
        $out = [];
        foreach (Kit::all() as $name => $dir) {
            if (Kit::source($name) === 'local' && !in_array($name, self::BUNDLED_KITS, true) && str_starts_with($dir, ROOT . '/kits/')) $out[] = 'kits/' . $name;
        }
        foreach (glob(ROOT . '/extensions/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if (!in_array($name, self::BUNDLED_EXTENSIONS, true) && !is_link($dir)) $out[] = 'extensions/' . $name;
        }
        return $out;
    }

    /** Beschreibung der Installation für die Wiederherstellung */
    public static function manifest(bool $code = true): array
    {
        $kits = [];
        foreach (Kit::all() as $name => $dir) {
            $meta = Kit::meta($dir);
            $kits[$name] = ['source' => Kit::source($name), 'bundled' => in_array($name, self::BUNDLED_KITS, true), 'version' => (string) ($meta['version'] ?? '')];
        }
        $exts = [];
        foreach (glob(ROOT . '/extensions/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $j = json_decode((string) @file_get_contents("$dir/composer.json"), true) ?: [];
            $name = basename($dir);
            $exts[$name] = ['bundled' => in_array($name, self::BUNDLED_EXTENSIONS, true), 'symlink' => is_link($dir),
                'package' => (string) ($j['name'] ?? ''), 'version' => (string) ($j['version'] ?? '')];
        }
        $sites = [];
        foreach (\Core\Sites::all() as $key => $cfg) $sites[$key] = ['label' => (string) ($cfg['label'] ?? $key), 'kit' => (string) (($cfg['kit'] ?? '') ?: ($cfg['theme'] ?? ''))];
        return [
            'type' => 'installation', 'created' => date('c'), 'core' => CMS_VERSION, 'php' => PHP_VERSION, 'host' => gethostname() ?: '',
            'sites' => $sites, 'kits' => $kits, 'extensions' => $exts, 'own_code' => $code ? self::ownCode() : [],
            'apps' => array_keys(\Core\Integrity::apps()), 'skipped' => self::skips(),
        ];
    }

    /** Archiv install-JJJJMMTT-HHMMSS.tar.gz in $outDir anlegen → Pfad */
    public static function create(string $outDir, bool $code = true): string
    {
        @mkdir($outDir, 0770, true);
        $name = 'install-' . date('Ymd-His');
        $tmp = ROOT . '/storage/tmp/' . $name;
        @mkdir($tmp, 0700, true);
        file_put_contents("$tmp/manifest.json", json_encode(self::manifest($code), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $files = ['manifest.json' => "$tmp/manifest.json"] + self::files($code);
        $out = Archive::pack($files, "$outDir/$name.tar.gz");
        @unlink("$tmp/manifest.json");
        @rmdir($tmp);
        return $out;
    }

    /**
     * Archiv einspielen: Konfiguration und storage/ (und mit $code eigene Kits/Erweiterungen) in die Installation schreiben.
     * Vorhandene Dateien werden überschrieben. Gibt die Zahl der Dateien und das Manifest zurück.
     */
    public static function restore(string $archive, bool $code = true): array
    {
        $tmp = ROOT . '/storage/tmp/restore-install-' . bin2hex(random_bytes(4));
        Archive::unpack($archive, $tmp);
        $man = json_decode((string) @file_get_contents("$tmp/manifest.json"), true);
        if (($man['type'] ?? '') !== 'installation') { Archive::remove($tmp); throw new \RuntimeException('Keine Installations-Sicherung (manifest.json fehlt oder passt nicht).'); }
        $n = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $rel = substr($f->getPathname(), strlen($tmp) + 1);
            if ($rel === 'manifest.json' || str_contains($rel, '..')) continue;
            $top = explode('/', $rel)[0];
            if (!in_array($top, ['config', 'storage', 'project', 'composer.json', 'composer.lock'], true) && !($code && in_array($top, ['kits', 'extensions'], true))) continue;
            @mkdir(dirname(ROOT . "/$rel"), 0775, true);
            copy($f->getPathname(), ROOT . "/$rel");
            $n++;
        }
        Archive::remove($tmp);
        return ['files' => $n, 'manifest' => $man];
    }
}
