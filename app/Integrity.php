<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Integritätsprüfung: meldet unerwartete Dateien und Daten – typische Spuren eines Einbruchs.
 *
 * Dateien (für die ganze Installation, Vergleich mit einem bestätigten Stand):
 *   - Prüfsummen der Dateien, die Angreifer bevorzugt verändern: public/index.php, PHP-Code von Kern, Kits und Erweiterungen,
 *     Autoloader (vendor/autoload.php, vendor/composer/), config/*.php, composer.json/.lock sowie jede .htaccess, .user.ini,
 *     php.ini, web.config und .env im Installations- und Web-Ordner. Neu oder geändert = Fehler (PHP, Serverkonfiguration) bzw. Hinweis.
 *   - Sperrzone ohne Vergleichsstand: ausführbarer Code (.php, .phtml, .phar …) in public/ (außer index.php und eigenständigen
 *     Programmen wie Matomo, siehe apps()) ist immer ein Fehler. Serverkonfiguration (.htaccess …) und ausführbare Dateien im
 *     Datenordner storage/ (außerhalb des Web-Ordners) überwachen die Prüfsummen; die SVG-Schutzdatei aus Svg::guard ist bekannt.
 *   - Neue Dateien in den Medienordnern: PHP-Code im Inhalt (z. B. als Bild getarnt) ist ein Fehler.
 * Datenbank (je Website): Trigger und Views (beliebtes Versteck), unbekannte Tabellen, neue Administrator-Konten, neue
 * API-Schlüssel, Skript-Code in Seiteninhalten (der Editor speichert nie <script>, javascript: oder on…-Attribute).
 *
 * Ablauf: geplante Aufgabe integrity.check (stündlich), Ergebnis in health und unter System → Integrität (/admin/system/integritaet);
 * neue Fehler per E-Mail an die Administration. Nach einem Update/Deploy den Stand bestätigen: `php bin/console integrity:accept --all`.
 * Zustand: storage/integrity/ (Dateien, Installation) und {Website-Speicher}/integrity-db.json; der Vergleichsstand ist mit app_key signiert.
 */
final class Integrity
{
    /** Ausführbarer Code bzw. Serverkonfiguration – in Sperrzonen nie erwartet */
    private const SCRIPT = '~\.(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|asp|aspx|jsp)$~i';
    private const CONFIG_NAMES = ['.htaccess', '.user.ini', 'php.ini', 'web.config', '.env'];
    /** Code-Ordner mit Prüfsummen (nur PHP-Dateien) */
    private const CODE_DIRS = ['app', 'lib', 'kits', 'extensions', 'vendor', 'storage/kits'];
    private const CODE_FILES = ['bin/console', 'public/index.php', 'composer.json', 'composer.lock'];
    private const SKIP_DIRS = ['.git', 'node_modules', '.raw', '.work', 'tests', 'phpstan'];
    /** Medien: nur neue Dateien seit der letzten Prüfung auf PHP-Code lesen (Anfang und Ende, je 64 KB) */
    private const SNIFF = 65536;

    // ============================================================ Ablage

    public static function dir(): string
    {
        $d = ROOT . '/storage/integrity';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }

    private static function read(string $file): ?array
    {
        $raw = @file_get_contents($file);
        $j = $raw === false ? null : json_decode($raw, true);
        return is_array($j) ? $j : null;
    }

    private static function write(string $file, array $data): void
    {
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        rename($tmp, $file);
    }

    /**
     * Signatur eines Vergleichsstands – ein still ausgetauschter Stand fällt auf. Dateien gelten für die ganze Installation:
     * Schlüssel der Installation (network_key, sonst app_key der Hauptwebsite); Datenbank: app_key der Website.
     */
    private static function sign(array $data, bool $installation = false): string
    {
        unset($data['sig']);
        $key = $installation ? self::installKey() : (string) app()->config->get('app_key');
        return hash_hmac('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 'integrity|' . $key);
    }

    private static function installKey(): string
    {
        $i = Network\Network::install();
        return (string) (($i['network_key'] ?? '') ?: ($i['app_key'] ?? '') ?: app()->config->get('app_key'));
    }

    /** Vergleichsstand laden; null = keiner, ['_bad' => true] = Signatur passt nicht */
    private static function baseline(string $file, bool $installation = false): ?array
    {
        $b = self::read($file);
        if ($b === null) return null;
        return hash_equals(self::sign($b, $installation), (string) ($b['sig'] ?? '')) ? $b : ['_bad' => true];
    }

    private static function saveBaseline(string $file, array $data, bool $installation = false): void
    {
        $data['sig'] = self::sign($data, $installation);
        self::write($file, $data);
    }

    private static function rel(string $path): string
    {
        return ltrim(substr($path, strlen(ROOT)), '/');
    }

    // ============================================================ Dateien

    /** @return array<string, string> relativer Pfad → sha256 der überwachten Dateien */
    public static function scanFiles(): array
    {
        $out = [];
        foreach (self::CODE_FILES as $f) if (is_file(ROOT . '/' . $f)) $out[$f] = hash_file('sha256', ROOT . '/' . $f);
        foreach (glob(ROOT . '/config/*.php') ?: [] as $f) $out[self::rel($f)] = hash_file('sha256', $f);
        foreach (self::CODE_DIRS as $d) {
            self::walk(ROOT . '/' . $d, function (string $f) use (&$out) {
                if (preg_match('~\.(php|phtml|phar|inc)$~i', $f) || in_array(basename($f), self::CONFIG_NAMES, true)) $out[self::rel($f)] = hash_file('sha256', $f);
            });
        }
        // Serverkonfiguration im Installationsordner (oberste Ebene) und im ganzen Web-Ordner
        foreach (self::CONFIG_NAMES as $n) if (is_file(ROOT . '/' . $n)) $out[$n] = hash_file('sha256', ROOT . '/' . $n);
        $guard = Svg::guardContent();   // SVG-Schutz, den der Kern selbst in Medienordnern anlegt – bekannt, nie ein Fund
        self::walk(ROOT . '/public', function (string $f) use (&$out, $guard) {
            if (in_array(basename($f), self::CONFIG_NAMES, true) && @file_get_contents($f) !== $guard) $out[self::rel($f)] = hash_file('sha256', $f);
        });
        // Datenordner (außerhalb des Web-Ordners): ausführbare Dateien und Serverkonfiguration über Prüfsummen – vorhandene
        // (z. B. Sicherungskopien, Schutzdatei einer Erweiterung) lassen sich übernehmen, jede neue ist ein Fehler
        self::walk(ROOT . '/storage', function (string $f) use (&$out) {
            $rel = self::rel($f);
            if (str_starts_with($rel, 'storage/kits/') || str_starts_with($rel, 'storage/cache/phpstan/')) return;
            if (preg_match(self::SCRIPT, basename($f)) || in_array(basename($f), self::CONFIG_NAMES, true)) $out[$rel] = hash_file('sha256', $f);
        });
        // Eigenständige Programme im Web-Ordner (Matomo …): ihr Code, ohne Cache
        foreach (array_keys(self::apps()) as $pre) {
            self::walk(ROOT . '/' . rtrim($pre, '/'), function (string $f) use (&$out) {
                $rel = self::rel($f);
                if (!self::ignored($rel) && preg_match('~\.(php\d?|phtml|phar|inc)$~i', $f)) $out[$rel] = hash_file('sha256', $f);
            });
        }
        ksort($out);
        return $out;
    }

    /**
     * Pakete mit Version: Erweiterungen (extension.php), Kits (theme.php, auch hochgeladene unter storage/kits) und die
     * Composer-Abhängigkeiten (Prüfsumme von composer.lock). Ändert sich die Version, ist die Änderung der Dateien gewollt
     * (Update) und wird übernommen; geänderter Code bei gleicher Version bleibt ein Fehler.
     * Version nur per Textsuche gelesen – fremder Code wird dafür nicht ausgeführt.
     * @return array<string, string> Pfad-Präfix → Version
     */
    public static function packages(): array
    {
        $out = [];
        $ver = function (string $file): ?string {
            $head = (string) @file_get_contents($file, false, null, 0, 8192);
            return preg_match("~['\"]version['\"]\s*=>\s*['\"]([^'\"]{1,40})['\"]~", $head, $m) ? $m[1] : null;
        };
        foreach (glob(ROOT . '/extensions/*/extension.php') ?: [] as $f) $out[self::rel(dirname($f)) . '/'] = $ver($f) ?? 'ohne Version';
        foreach (array_merge(glob(ROOT . '/kits/*/theme.php') ?: [], glob(ROOT . '/storage/kits/*/theme.php') ?: []) as $f) $out[self::rel(dirname($f)) . '/'] = $ver($f) ?? 'ohne Version';
        if (is_file(ROOT . '/composer.lock')) $out['vendor/'] = substr(hash_file('sha256', ROOT . '/composer.lock'), 0, 16);
        foreach (self::apps() as $pre => $a) $out[$pre] = $a['version'];
        ksort($out);
        return $out;
    }

    /** Gehört der Pfad zum Paket? (vendor/ umfasst composer.json und composer.lock) */
    private static function inPackage(string $path, string $prefix): bool
    {
        return str_starts_with($path, $prefix) || ($prefix === 'vendor/' && in_array($path, ['composer.json', 'composer.lock'], true));
    }

    private static function packageLabel(string $prefix): string
    {
        if ($prefix === 'vendor/') return __('Abhängigkeiten (Composer)');
        $name = basename(rtrim($prefix, '/'));
        if (str_starts_with($prefix, 'public/')) return __('Programm „{name}“', ['name' => $name]);
        return str_starts_with($prefix, 'extensions/') ? __('Erweiterung „{name}“', ['name' => $name]) : __('Kit „{name}“', ['name' => $name]);
    }

    /**
     * Eigenständige Programme im Web-Ordner (z. B. Matomo unter public/piwik): Code wird wie ein Paket überwacht (Prüfsummen,
     * neue Version = Update übernommen), Cache-Ordner ausgenommen – nicht als „ausführbare Datei im Web-Ordner“ gemeldet.
     * Matomo/Piwik wird erkannt (core/Version.php); weitere in der Konfiguration der Installation:
     *   'integrity' => ['apps' => ['public/forum/' => ['ignore' => ['cache/']]]]
     * @return array<string, array{version: string, ignore: list<string>}> Präfix → Version, ausgenommene Pfade (absolut relativ zu ROOT)
     */
    public static function apps(): array
    {
        static $memo = null;
        if ($memo !== null) return $memo;
        $out = [];
        foreach (glob(ROOT . '/public/*/core/Version.php') ?: [] as $f) {
            $head = (string) @file_get_contents($f, false, null, 0, 4096);
            if (!preg_match('~namespace\s+(Piwik|Matomo)~', $head)) continue;
            $pre = self::rel(dirname($f, 2)) . '/';
            $out[$pre] = ['version' => preg_match("~VERSION\s*=\s*'([^']{1,30})'~", $head, $m) ? $m[1] : 'ohne Version', 'ignore' => [$pre . 'tmp/']];
        }
        try { $cfg = (array) ((Network\Network::install()['integrity']['apps'] ?? []) ?: []); } catch (\Throwable) { $cfg = []; }
        foreach ($cfg as $pre => $o) {
            $pre = trim((string) $pre, '/') . '/';
            if (!str_starts_with($pre, 'public/') || str_contains($pre, '..')) continue;
            $out[$pre] = ['version' => (string) ($o['version'] ?? ($out[$pre]['version'] ?? 'ohne Version')),
                'ignore' => array_values(array_unique(array_merge($out[$pre]['ignore'] ?? [], array_map(fn($x) => $pre . trim((string) $x, '/') . '/', (array) ($o['ignore'] ?? [])))))];
        }
        return $memo = $out;
    }

    /** Liegt der Pfad im Cache eines eigenständigen Programms (ausgenommen)? */
    private static function ignored(string $rel): bool
    {
        foreach (self::apps() as $a) foreach ($a['ignore'] as $x) if (str_starts_with($rel, $x)) return true;
        return false;
    }

    /** Rekursiv alle Dateien (Symlinks auf Ordner werden verfolgt, z. B. per Symlink eingebundene Erweiterungen) */
    private static function walk(string $dir, callable $fn): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS),
            fn(\SplFileInfo $c) => !($c->isDir() && in_array($c->getFilename(), self::SKIP_DIRS, true))
        ), \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($it as $f) if ($f->isFile()) $fn($f->getPathname());
    }

    /** Sperrzonen: Code oder Serverkonfiguration, wo nie welche sein darf. @return list<array> Funde */
    public static function forbidden(): array
    {
        $out = [];
        self::walk(ROOT . '/public', function (string $f) use (&$out) {
            $rel = self::rel($f);
            $name = basename($f);
            if ($rel === 'public/index.php' || str_starts_with($rel, 'public/assets/')) {
                if ($rel === 'public/index.php' || !preg_match(self::SCRIPT, $name)) return;   // Assets: nur Code melden
            }
            // Serverkonfiguration (.htaccess …) überwachen die Prüfsummen (neu/geändert = Fehler) – z. B. der Front-Controller
            // unter Apache; eigenständige Programme (Matomo …) samt Cache ebenso
            if (in_array($name, self::CONFIG_NAMES, true) || self::ignored($rel)) return;
            foreach (array_keys(self::apps()) as $pre) if (str_starts_with($rel, $pre)) return;
            if (preg_match(self::SCRIPT, $name)) {
                $out[] = ['level' => 'error', 'kind' => 'forbidden', 'path' => $rel, 'text' => __('Ausführbare Datei im Web-Ordner: {path}', ['path' => $rel])];
            }
        });
        return $out;
    }

    /** Neue Mediendateien seit $since mit PHP-Code im Inhalt (getarnt als Bild, PDF …). @return list<array> */
    public static function mediaCode(int $since): array
    {
        $out = [];
        foreach (array_merge(glob(ROOT . '/public/media', GLOB_ONLYDIR) ?: [], glob(ROOT . '/public/sites/*/media', GLOB_ONLYDIR) ?: [], glob(ROOT . '/public/pools/*', GLOB_ONLYDIR) ?: []) as $dir) {
            self::walk($dir, function (string $f) use (&$out, $since) {
                if (@filemtime($f) < $since || @filesize($f) === 0) return;
                $h = @fopen($f, 'rb');
                if (!$h) return;
                $head = (string) fread($h, self::SNIFF);
                $size = (int) @filesize($f);
                $tail = '';
                if ($size > self::SNIFF * 2 && fseek($h, -self::SNIFF, SEEK_END) === 0) $tail = (string) fread($h, self::SNIFF);
                fclose($h);
                // nur eindeutige Öffnung (kurze Formen wie „<?=“ kommen in Binärdaten zufällig vor)
                if (preg_match('~<\?php\s|<script\s+language\s*=\s*["\']?php~i', $head . $tail)) {
                    $rel = self::rel($f);
                    $out[] = ['level' => 'error', 'kind' => 'media-code', 'path' => $rel, 'text' => __('PHP-Code in einer Mediendatei: {path}', ['path' => $rel])];
                }
            });
        }
        return $out;
    }

    /** Prüfung der Dateien (Installation). @return array{time: int, findings: list<array>, baseline: ?int, count: int} */
    public static function checkFiles(): array
    {
        $file = self::dir() . '/files.json';
        $last = self::read(self::dir() . '/files-result.json');
        $since = (int) ($last['time'] ?? (time() - 30 * 86400));
        $base = self::baseline($file, true);
        $now = self::scanFiles();
        $findings = [];
        if ($base === null) {
            $findings[] = ['level' => 'warning', 'kind' => 'no-baseline', 'path' => '', 'text' => __('Noch kein bestätigter Stand der Dateien – nach der Prüfung „Als bekannt übernehmen“.')];
        } elseif (!empty($base['_bad'])) {
            $findings[] = ['level' => 'error', 'kind' => 'baseline', 'path' => '', 'text' => __('Der gespeicherte Vergleichsstand der Dateien wurde verändert (Signatur passt nicht).')];
        } else {
            $old = (array) ($base['files'] ?? []);
            // Pakete mit neuer Version (Update, Installation, Entfernen): deren Dateien übernehmen – nur ein Info-Eintrag
            $pkNow = self::packages();
            $pkOld = isset($base['packages']) ? (array) $base['packages'] : null;
            $changed = [];
            if ($pkOld !== null) {
                foreach (array_unique(array_merge(array_keys($pkNow), array_keys($pkOld))) as $pre) {
                    if (($pkNow[$pre] ?? null) === ($pkOld[$pre] ?? null)) continue;
                    $changed[] = $pre;
                    $findings[] = ['level' => 'info', 'kind' => 'package', 'path' => $pre, 'text' => isset($pkOld[$pre], $pkNow[$pre])
                        ? __('{name}: {old} → {new} – übernommen', ['name' => self::packageLabel($pre), 'old' => $pkOld[$pre], 'new' => $pkNow[$pre]])
                        : (isset($pkNow[$pre]) ? __('{name} {new} neu – übernommen', ['name' => self::packageLabel($pre), 'new' => $pkNow[$pre]]) : __('{name} entfernt – übernommen', ['name' => self::packageLabel($pre)]))];
                }
            }
            if ($changed || $pkOld === null) {
                $in = fn(string $p) => (bool) array_filter($changed, fn($x) => self::inPackage($p, $x));
                $old = array_filter($old, fn($p) => !$in((string) $p), ARRAY_FILTER_USE_KEY);
                foreach ($now as $p => $h) if ($in($p)) $old[$p] = $h;
                ksort($old);
                self::saveBaseline($file, ['files' => $old, 'packages' => $pkNow] + $base, true);
            }
            foreach ($now as $p => $h) {
                if (!isset($old[$p])) $findings[] = self::fileFinding($p, 'added');
                elseif ($old[$p] !== $h) $findings[] = self::fileFinding($p, 'changed');
            }
            foreach (array_diff_key($old, $now) as $p => $_) $findings[] = self::fileFinding($p, 'removed');
        }
        $findings = array_merge($findings, self::forbidden(), self::mediaCode($since));
        $res = ['time' => time(), 'findings' => self::dedupe($findings), 'baseline' => isset($base['time']) ? (int) $base['time'] : null, 'count' => count($now)];
        self::write(self::dir() . '/files-result.json', $res);
        return $res;
    }

    private static function fileFinding(string $p, string $how): array
    {
        $critical = (bool) preg_match('~\.(php|phtml|phar|inc)$~i', $p) || in_array(basename($p), self::CONFIG_NAMES, true) || $p === 'bin/console';
        $text = match ($how) {
            'added' => __('Neue Datei: {path}', ['path' => $p]),
            'changed' => __('Geändert: {path}', ['path' => $p]),
            default => __('Entfernt: {path}', ['path' => $p]),
        };
        return ['level' => $critical && $how !== 'removed' ? 'error' : 'warning', 'kind' => 'file-' . $how, 'path' => $p, 'text' => $text];
    }

    /** Aktuellen Dateistand als bekannt übernehmen */
    public static function acceptFiles(string $by = ''): int
    {
        $files = self::scanFiles();
        self::saveBaseline(self::dir() . '/files.json', ['time' => time(), 'by' => $by, 'core' => CMS_VERSION, 'files' => $files, 'packages' => self::packages()], true);
        self::checkFiles();
        return count($files);
    }

    /**
     * Nur einzelne Pfade in den bestätigten Stand übernehmen (z. B. nach Installation eines Kits über die Verwaltung) –
     * alles andere bleibt, wie es war. Ohne gültigen Stand: nichts tun (dann zeigt die Prüfung ohnehin „kein Stand“).
     * @param list<string> $prefixes relative Pfade, z. B. „storage/kits/name/“
     */
    public static function acceptPaths(array $prefixes, string $by = ''): void
    {
        try {
            $file = self::dir() . '/files.json';
            $base = self::baseline($file, true);
            if ($base === null || !empty($base['_bad'])) return;
            $in = fn(string $p) => (bool) array_filter($prefixes, fn($x) => str_starts_with($p, $x));
            $files = array_filter((array) $base['files'], fn($p) => !$in((string) $p), ARRAY_FILTER_USE_KEY);
            foreach (self::scanFiles() as $p => $h) if ($in($p)) $files[$p] = $h;
            ksort($files);
            self::saveBaseline($file, ['files' => $files, 'packages' => self::packages(), 'by' => $by ?: (string) ($base['by'] ?? '')] + $base, true);
        } catch (\Throwable $e) {
            error_log('[integrity] acceptPaths: ' . $e->getMessage());
        }
    }

    // ============================================================ Datenbank (aktuelle Website)

    /** @return array{tables: list<string>, objects: array<string, string>, admins: array<int, string>, tokens: list<int>, scripts: list<string>} */
    public static function scanDb(): array
    {
        $db = app()->db;
        $tables = $objects = [];
        foreach ($db->fetchAll("SELECT type, name, sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'") as $r) {
            if ($r['type'] === 'table') $tables[] = (string) $r['name'];
            elseif (in_array($r['type'], ['trigger', 'view'], true)) $objects[$r['type'] . ':' . $r['name']] = hash('sha256', (string) $r['sql']);
        }
        sort($tables);
        $adminRoles = array_keys(array_filter(Permissions::roles(), fn($r) => in_array('*', (array) $r['permissions'], true)));
        $admins = [];
        if ($adminRoles) {
            foreach ($db->fetchAll('SELECT id, email FROM users WHERE disabled = 0 AND role IN (' . implode(',', array_fill(0, count($adminRoles), '?')) . ')', $adminRoles) as $u) $admins[(int) $u['id']] = (string) $u['email'];
        }
        ksort($admins);
        $tokens = [];
        try { $tokens = array_map('intval', array_column($db->fetchAll('SELECT id FROM api_tokens ORDER BY id'), 'id')); } catch (\Throwable) { /* ohne API */ }
        $scripts = array_values(array_map(fn($x) => (string) $x['path'], self::scriptContent()));
        sort($scripts);
        return ['tables' => $tables, 'objects' => $objects, 'admins' => $admins, 'tokens' => $tokens, 'scripts' => $scripts];
    }

    /** Tabellen, die erwartet sind, auch wenn sie neu sind: Datentabellen und deklarierte Tabellen aktiver Erweiterungen */
    private static function expectedTables(): array
    {
        $out = [];
        try { foreach (app()->db->fetchAll('SELECT handle FROM data_tables') as $t) $out[] = 'data_' . $t['handle']; } catch (\Throwable) { /* ohne Daten */ }
        try {
            foreach (Extensions::active() as $x) {
                foreach ($x->tableNames() as $n) $out[] = $n;
                $out[] = str_replace('-', '_', $x->name) . '_*';   // Konvention: Tabellen einer Erweiterung tragen ihren Namen als Präfix
            }
        } catch (\Throwable) { /* egal */ }
        return $out;
    }

    /** Skript-Code in Seiten (der Editor speichert ihn nie). @return list<array> */
    public static function scriptContent(): array
    {
        $out = [];
        $rows = app()->db->fetchAll("SELECT id, title, lang FROM pages WHERE content_published LIKE '%<script%' OR content_draft LIKE '%<script%'
            OR content_published LIKE '%javascript:%' OR content_draft LIKE '%javascript:%' OR content_published LIKE '%onerror=%' OR content_draft LIKE '%onerror=%'
            OR content_published LIKE '%onload=%' OR content_draft LIKE '%onload=%' OR title LIKE '%<script%' OR meta_description LIKE '%<script%' LIMIT 50");
        foreach ($rows as $p) {
            // Schlüssel = Seite + Prüfsumme der gefundenen Stellen: ein bestätigtes Beispiel (z. B. Anleitung zum SVG-Filter) bleibt
            // bekannt, jede neue oder geänderte Stelle meldet wieder
            $raw = (string) app()->db->fetchValue("SELECT coalesce(content_published, '') || coalesce(content_draft, '') || coalesce(title, '') || coalesce(meta_description, '') FROM pages WHERE id = ?", [(int) $p['id']]);
            preg_match_all('~<script[^>]*>[^<]{0,200}|javascript:[^\s"]{0,120}|\bon(?:error|load)\s*=\s*[^ >]{0,120}~i', $raw, $mm);
            $sig = substr(hash('sha256', implode('|', array_unique($mm[0]))), 0, 12);
            $out[] = ['level' => 'error', 'kind' => 'script', 'path' => 'page:' . (int) $p['id'] . ':' . $sig,
                'text' => __('Skript-Code im Inhalt der Seite „{title}“ (#{id}) – so speichert der Editor nie.', ['title' => (string) $p['title'], 'id' => (int) $p['id']])];
        }
        try {
            foreach (app()->db->fetchAll("SELECT skey FROM settings WHERE value_json LIKE '%<script%' OR value_json LIKE '%javascript:%'") as $s) {
                $v = (string) app()->db->fetchValue('SELECT value_json FROM settings WHERE skey = ?', [$s['skey']]);
                $out[] = ['level' => 'error', 'kind' => 'script', 'path' => 'setting:' . $s['skey'] . ':' . substr(hash('sha256', $v), 0, 12), 'text' => __('Skript-Code in der Einstellung {key}', ['key' => (string) $s['skey']])];
            }
        } catch (\Throwable) { /* ohne Einstellungen */ }
        return $out;
    }

    /** Prüfung der Datenbank der aktuellen Website */
    public static function checkDb(): array
    {
        $file = site()->storage('integrity-db.json');
        $base = self::baseline($file);
        $now = self::scanDb();
        $f = [];
        if ($base === null) {
            $f[] = ['level' => 'warning', 'kind' => 'no-baseline', 'path' => '', 'text' => __('Noch kein bestätigter Stand der Datenbank – nach der Prüfung „Als bekannt übernehmen“.')];
        } elseif (!empty($base['_bad'])) {
            $f[] = ['level' => 'error', 'kind' => 'baseline', 'path' => '', 'text' => __('Der gespeicherte Vergleichsstand der Datenbank wurde verändert (Signatur passt nicht).')];
        } else {
            $known = array_merge((array) $base['tables'], self::expectedTables());
            foreach ($now['tables'] as $t) {
                if (in_array($t, $known, true) || array_filter($known, fn($k) => (str_ends_with($k, '_*') && str_starts_with($t, substr($k, 0, -1)))
                    || (str_starts_with($t, 'data_') && str_starts_with($t, $k . '__')))) continue;
                $f[] = ['level' => 'warning', 'kind' => 'table', 'path' => 'table:' . $t, 'text' => __('Neue Tabelle: {name} (z. B. durch eine neue Erweiterung – sonst prüfen)', ['name' => $t])];
            }
            foreach ($now['objects'] as $k => $h) {
                if (($base['objects'][$k] ?? null) === $h) continue;
                [$type, $name] = explode(':', $k, 2);
                $f[] = ['level' => 'error', 'kind' => $type, 'path' => $k, 'text' => $type === 'trigger'
                    ? __('Trigger in der Datenbank: {name} – führt bei jeder Änderung eigenen Code aus', ['name' => $name])
                    : __('View in der Datenbank: {name}', ['name' => $name])];
            }
            foreach (array_diff_key($now['admins'], (array) $base['admins']) as $id => $mail) {
                $f[] = ['level' => 'error', 'kind' => 'admin', 'path' => 'user:' . $id, 'text' => __('Neues Konto mit allen Rechten: {email}', ['email' => $mail])];
            }
            foreach (array_diff($now['tokens'], (array) $base['tokens']) as $id) {
                $f[] = ['level' => 'warning', 'kind' => 'token', 'path' => 'token:' . $id, 'text' => __('Neuer API-Schlüssel #{id} (Benutzer → API & MCP)', ['id' => $id])];
            }
        }
        // Skript-Code: nur, was nicht im bestätigten Stand steht (bekannte Beispiele lassen sich übernehmen)
        $known = $base !== null && empty($base['_bad']) ? (array) ($base['scripts'] ?? []) : [];
        $f = array_merge($f, array_values(array_filter(self::scriptContent(), fn($x) => !in_array($x['path'], $known, true))));
        $res = ['time' => time(), 'findings' => self::dedupe($f), 'baseline' => isset($base['time']) ? (int) $base['time'] : null];
        self::write(site()->storage('integrity-db-result.json'), $res);
        return $res;
    }

    public static function acceptDb(string $by = ''): void
    {
        self::saveBaseline(site()->storage('integrity-db.json'), ['time' => time(), 'by' => $by] + self::scanDb());
        self::checkDb();
    }

    // ============================================================ Ergebnis, Hinweise

    private static function dedupe(array $f): array
    {
        $seen = [];
        return array_values(array_filter($f, function ($x) use (&$seen) {
            $k = $x['kind'] . '|' . $x['path'];
            if (isset($seen[$k])) return false;
            return $seen[$k] = true;
        }));
    }

    /** Letzte Ergebnisse (ohne neu zu prüfen) */
    public static function lastFiles(): ?array
    {
        return self::read(self::dir() . '/files-result.json');
    }

    public static function lastDb(): ?array
    {
        return self::read(site()->storage('integrity-db-result.json'));
    }

    /** Für health: Fehler = false, Hinweis = null, alles gut = true. Dateien nur, wer die Installation verwaltet (bzw. auf der Kommandozeile) */
    public static function health(): array
    {
        $out = [];
        $sets = ['db' => self::lastDb()];
        if (PHP_SAPI === 'cli' || Permissions::installationAdmin()) $sets = ['files' => self::lastFiles()] + $sets;
        foreach ($sets as $k => $r) {
            $label = $k === 'files' ? __('Integrität der Dateien') : __('Integrität der Datenbank');
            if ($r === null) { $out[$label . ': ' . __('noch nicht geprüft – System → Integrität')] = null; continue; }
            if ((int) $r['time'] < time() - 3 * 86400) { $out[$label . ': ' . __('letzte Prüfung älter als drei Tage')] = null; continue; }
            $err = count(array_filter($r['findings'], fn($x) => $x['level'] === 'error'));
            $warn = count(array_filter($r['findings'], fn($x) => $x['level'] === 'warning'));   // Info (übernommene Updates) zählt nicht
            $out[$label . ($err || $warn ? ': ' . __('{e} Fehler, {w} Hinweise – System → Integrität', ['e' => $err, 'w' => $warn]) : '')] = $err ? false : ($warn ? null : true);
        }
        return $out;
    }

    /**
     * Neue Fehler per E-Mail melden – je Fund nur einmal (Merkliste in storage/integrity/notified.json bzw. je Website).
     * Empfänger: Konten mit allen Rechten der aktuellen Website (bei Dateien: der Website, auf der die Prüfung läuft).
     */
    public static function notify(array $result, string $scope): int
    {
        $errors = array_values(array_filter($result['findings'], fn($x) => $x['level'] === 'error'));
        $file = $scope === 'files' ? self::dir() . '/notified.json' : site()->storage('integrity-db-notified.json');
        $old = self::read($file) ?? [];
        $keys = array_map(fn($x) => $x['kind'] . '|' . $x['path'], $errors);
        $new = array_values(array_filter($errors, fn($x) => !in_array($x['kind'] . '|' . $x['path'], $old, true)));
        self::write($file, $keys);
        if (!$new || !app()->settings->get('sys.integrity_mail', true)) return 0;
        $to = array_values(self::scanDb()['admins']);
        if (!$to) return 0;
        $host = (string) (parse_url((string) (app()->settings->get('sys.site_url') ?: (app()->config->get('url') ?? '')), PHP_URL_HOST) ?: site()->key);
        $lines = array_map(fn($x) => '- ' . $x['text'], array_slice($new, 0, 30));
        $text = __('Die Integritätsprüfung von {host} hat Unerwartetes gefunden:', ['host' => $host]) . "\n\n" . implode("\n", $lines)
            . (count($new) > 30 ? "\n" . __('… und {n} weitere.', ['n' => count($new) - 30]) : '')
            . "\n\n" . __('Bitte prüfen: Verwaltung → Grundeinstellungen → Integrität. War es eine gewollte Änderung (Update, neue Erweiterung, neues Konto), dort „Als bekannt übernehmen“.')
            . "\n\n" . absolute_url('/admin/system/integritaet');
        Mailer::send(__('Sicherheitshinweis: unerwartete Änderungen auf {host}', ['host' => $host]), $text, $to);
        return count($new);
    }

    /** Selbsttest: Vergleich, Sperrzonen, Signatur – in einem Spielordner, ohne die echte Installation zu berühren */
    public static function selftest(): array
    {
        $r = [];
        $eq = function (string $name, $got, $want) use (&$r) { $r[] = [$name, $got === $want, $got === $want ? '' : json_encode($got)]; };
        $eq('Skript-Endungen erkannt', array_map(fn($n) => (bool) preg_match(self::SCRIPT, $n), ['a.php', 'b.PHTML', 'c.phar', 'x.php7', 'bild.jpg', 'doc.pdf', 'x.php.jpg']), [true, true, true, true, false, false, false]);
        $a = ['time' => 1, 'files' => ['a.php' => 'x']];
        $a['sig'] = self::sign($a);
        $eq('Signatur gültig', hash_equals(self::sign($a), $a['sig']), true);
        $b = $a; $b['files']['b.php'] = 'y';
        $eq('Veränderter Stand fällt auf', hash_equals(self::sign($b), $b['sig']), false);
        $eq('Geänderte PHP-Datei = Fehler', self::fileFinding('app/x.php', 'changed')['level'], 'error');
        $eq('Geänderte composer.json = Hinweis', self::fileFinding('composer.json', 'changed')['level'], 'warning');
        $eq('Neue .htaccess = Fehler', self::fileFinding('public/sites/a/media/.htaccess', 'added')['level'], 'error');
        $eq('Kein Fehlalarm bei „<?=“ in Binärdaten', (bool) preg_match('~<\?php\s~i', "\x89PNG<?=\x00<?php\x00"), false);
        $eq('PHP in Bild erkannt', (bool) preg_match('~<\?php\s~i', "GIF89a<?php system(\$_GET[1]); ?>"), true);
        $eq('SVG-Schutz erkannt', str_contains(Svg::guardContent(), 'Svg::guard'), true);
        return $r;
    }
}
