<?php
declare(strict_types=1);

namespace Core\Network;

use Core\Database;
use Core\Features;
use Core\MediaPools;
use Core\PageCache;
use Core\Site;
use Core\Sites;
use Core\Theme;

/**
 * Kennzahlen und Wartungsaktionen je Website für die Netzwerk-Übersicht (/admin/network).
 * Liest die Datenbanken der Websites direkt (nur lesend, bis auf Wartungsmodus); Ergebnisse 60 s in storage/cache/network.
 * Anfragen (Eingangs-Tabellen) werden nur gezählt – Inhalte bleiben verschlüsselt.
 */
final class Stats
{
    public const TTL = 60;
    /** Größe der Medienordner wird länger zwischengespeichert (rekursives Zählen) */
    public const SIZE_TTL = 900;

    private static function cacheFile(string $key): string
    {
        return ROOT . '/storage/cache/network/site-' . $key . '.json';
    }

    /** @return array<string, array> Kennzahlen aller Websites */
    public static function all(bool $fresh = false): array
    {
        $out = [];
        foreach (array_keys(Sites::all()) as $k) $out[$k] = self::site($k, $fresh);
        return $out;
    }

    public static function forget(string $key): void
    {
        @unlink(self::cacheFile($key));
    }

    public static function site(string $key, bool $fresh = false): array
    {
        $f = self::cacheFile($key);
        if (!$fresh && is_file($f) && filemtime($f) > time() - self::TTL) {
            $d = json_decode((string) file_get_contents($f), true);
            if (is_array($d)) return $d;
        }
        $prev = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        try {
            $d = self::collect($key, $prev);
        } catch (\Throwable $e) {
            error_log('[network] stats ' . $key . ': ' . $e->getMessage());
            $d = self::base($key) + ['ok' => false, 'error' => $e->getMessage(), 'checks' => ['db' => false]];
        }
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0770, true);
        @file_put_contents($f, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $d;
    }

    private static function base(string $key): array
    {
        $cfg = Sites::all()[$key] ?? [];
        $site = new Site($key, $cfg);
        return ['key' => $key, 'label' => $key === Site::DEFAULT && empty($cfg['label']) ? __('Hauptwebsite') : $site->label(),
            'hosts' => $site->hosts(), 'url' => Network::siteUrl($key), 'network' => $key === Network::siteKey(), 'at' => time()];
    }

    private static function collect(string $key, array $prev): array
    {
        $d = self::base($key);
        $cfg = Network::config($key);
        $site = new Site($key, Sites::all()[$key] ?? []);
        $dbCfg = (array) $cfg->get('db');
        $env = (string) $cfg->get('environment', 'production');
        $d['environment'] = in_array($env, ['production', 'staging', 'development'], true) ? $env : 'production';
        $preset = (string) $cfg->get('preset', 'full');
        $d['preset'] = $preset;
        // Abweichungen vom vollen Funktionsumfang (Preset + einzelne Schalter)
        $state = [];
        foreach (array_merge(Features::PRESETS[$preset] ?? [], (array) $cfg->get('features', [])) as $fk => $on) $state[$fk] = (bool) $on;
        $d['features_off'] = array_keys(array_filter($state, fn($v) => !$v));
        $d['extensions'] = array_keys(array_filter(\Core\Extensions::configured($cfg)));
        $d['integrators'] = count((array) $cfg->get('integrators', []));

        $sqlite = ($dbCfg['driver'] ?? 'sqlite') === 'sqlite';
        $d['initialized'] = !$sqlite || is_file((string) $dbCfg['path']);
        $checks = ['app_key' => strlen((string) $cfg->get('app_key')) >= 32,
            'storage' => is_writable($site->storage()) || (!is_dir($site->storage()) && is_writable(dirname($site->storage()))),
            'media' => is_writable($site->mediaDir()) || (!is_dir($site->mediaDir()) && is_writable(dirname($site->mediaDir())))];
        $d['migrate'] = false;
        if ($d['initialized']) {
            $db = $key === site()->key ? app()->db : new Database($dbCfg);
            $checks['db'] = (int) $db->fetchValue('SELECT 1') === 1;
            $set = self::settings($db, ['sys.theme', 'sys.maintenance', 'sys.site_url', Features::UI_KEY, \Core\Extensions::UI_KEY, Features::DELEGATE_KEY,
                'sys.noindex', 'sys.icon_version', 'sys.icon_bg', 'sys.icon_text']);
            // Schalter der Verwaltung (Funktionen & Erweiterungen) – Preset und 'features' der Konfiguration gehen vor
            $cfgF = array_merge(Features::PRESETS[$preset] ?? [], (array) $cfg->get('features', []));
            foreach ((array) ($set[Features::UI_KEY] ?? []) as $fk => $on) if (!array_key_exists($fk, $cfgF)) $state[$fk] = (bool) $on;
            $d['features_off'] = array_keys(array_filter($state, fn($v) => !$v));
            $ext = array_map('boolval', (array) ($set[\Core\Extensions::UI_KEY] ?? []));
            foreach (\Core\Extensions::configured($cfg) as $en => $on) $ext[$en] = $on;
            $d['extensions'] = array_keys(array_filter($ext));
            $d['features_delegated'] = (bool) ($set[Features::DELEGATE_KEY] ?? false);
            $d['theme'] = (string) ($set['sys.theme'] ?? '') ?: (string) ($cfg->get('theme') ?: $site->defaultTheme());
            $d['maintenance'] = (bool) ($set['sys.maintenance'] ?? false);
            // Website ausgeblendet für Suchmaschinen (Grundeinstellung) – außerhalb von production ohnehin (noindex_site())
            $d['noindex'] = (bool) ($set['sys.noindex'] ?? false) || $d['environment'] !== 'production';
            // App-Icon (Core\AppIcons): Version für Cache-Busting, Farbe und Buchstaben für den Ersatz (SiteIcon)
            $d['icon_version'] = (string) ($set['sys.icon_version'] ?? '');
            $d['icon_bg'] = (string) ($set['sys.icon_bg'] ?? '');
            $d['icon_text'] = (string) ($set['sys.icon_text'] ?? '');
            if (!empty($set['sys.site_url']) && empty((Sites::all()[$key] ?? [])['base_url'])) $d['public_url'] = rtrim((string) $set['sys.site_url'], '/');
            $cols = self::columns($db, 'users');
            // Schema älter als der Code? (neueste Spalten fehlen → php bin/console migrate --all)
            $d['migrate'] = !in_array('auth_ver', $cols, true) || !in_array('totp_enabled', $cols, true);
            $hasNet = in_array('network_uid', $cols, true);
            $d['editors'] = (int) $db->fetchValue('SELECT COUNT(*) FROM users' . ($hasNet ? " WHERE network_uid IS NULL AND role != 'network'" : ''));
            $d['pages'] = (int) $db->fetchValue("SELECT COUNT(*) FROM pages WHERE type = 'page'");
            // [Platzhalter] in Seiten (wie die Übersicht der Website; bestätigte „Ist gewollt“-Klammern zählen nicht)
            $okPh = array_values(array_filter((array) (json_decode((string) $db->fetchValue('SELECT value_json FROM settings WHERE skey = ?', ['sys.placeholders_ok']), true) ?: []), 'is_string'));
            $ph = \Core\Dashboard\Metrics::placeholders($db, $okPh);
            $d['placeholders'] = count($ph);
            $d['placeholder_first'] = $ph[0] ?? null;
            $last = [(string) $db->fetchValue('SELECT MAX(updated_at) FROM pages')];
            $d['inbox_new'] = 0;
            $d['entries'] = 0;
            foreach ($db->fetchAll('SELECT handle, settings_json, updated_at FROM data_tables') as $t) {
                $s = json_decode((string) $t['settings_json'], true) ?: [];
                if (!preg_match('~^[a-z0-9_]+$~', (string) $t['handle'])) continue;
                try {
                    if (($s['kind'] ?? 'content') === 'inbox') {
                        $d['inbox_new'] += (int) $db->fetchValue("SELECT COUNT(*) FROM data_{$t['handle']} WHERE status = 'neu'");
                    } else {
                        $d['entries'] += (int) $db->fetchValue("SELECT COUNT(*) FROM data_{$t['handle']}");
                        $last[] = (string) $db->fetchValue("SELECT MAX(updated_at) FROM data_{$t['handle']}");
                        $last[] = (string) $t['updated_at'];
                    }
                } catch (\Throwable) {
                    // Tabelle fehlt (z. B. geteilte Tabelle) – überspringen
                }
            }
            $d['last_change'] = max(array_filter($last) ?: ['']) ?: null;
            // Prüf-Ebene (Core\Review): offene Einreichungen über API, MCP und KI
            try {
                $d['review_pending'] = (int) $db->fetchValue("SELECT COUNT(*) FROM change_log WHERE status = 'pending'");
            } catch (\Throwable) {
                // Tabelle fehlt noch (vor „migrate“)
            }
            $d['db_size'] = $sqlite ? (int) @filesize((string) $dbCfg['path']) + (int) @filesize($dbCfg['path'] . '-wal') : null;
        } else {
            $d['theme'] = (string) ($cfg->get('theme') ?: $site->defaultTheme());
            $d['maintenance'] = false;
            $d['noindex'] = $d['environment'] !== 'production';
            $d['editors'] = 0;
        }
        if (($d['icon_bg'] ?? '') === '') $d['icon_bg'] = self::themeValue($d['theme'], 'icon_bg');
        if (($d['icon_text'] ?? '') === '') $d['icon_text'] = self::themeValue($d['theme'], 'icon_text');
        // Geteilte Medien-Pools dieser Website (Konfiguration 'media_pools' oder Zuordnung in pool.json)
        $d['pools'] = self::sitePools($key, (array) $cfg->get('media_pools', []));
        $req = self::themeRequires($d['theme']);
        $checks['theme'] = $req === '' || !preg_match('~^(>=|>|<=|<|=|==)?\s*([\d.]+)$~', $req, $m) || version_compare(CMS_VERSION, $m[2], ($m[1] ?? '') ?: '>=');
        $d['theme_label'] = Theme::available()[$d['theme']] ?? $d['theme'];
        $d['checks'] = $checks;
        $d['ok'] = !in_array(false, $checks, true);
        // Medien: Größe seltener neu zählen
        if (isset($prev['media_size'], $prev['media_at']) && $prev['media_at'] > time() - self::SIZE_TTL) {
            [$d['media_size'], $d['media_files'], $d['media_at']] = [$prev['media_size'], $prev['media_files'] ?? 0, $prev['media_at']];
        } else {
            [$d['media_size'], $d['media_files']] = self::dirSize($site->mediaDir());
            $d['media_at'] = time();
        }
        $d['backup'] = self::lastBackup($key);
        // Offene Support-Meldungen (Core\Support, falls vorhanden) – nur Anzahl
        if (class_exists(\Core\Support\Tickets::class) && method_exists(\Core\Support\Tickets::class, 'stats')) {
            try {
                $d['support_open'] = (int) (\Core\Support\Tickets::stats($key)['open'] ?? 0);
            } catch (\Throwable) {
            }
        }
        return $d;
    }

    private static function settings(Database $db, array $keys): array
    {
        $out = [];
        $in = implode(',', array_fill(0, count($keys), '?'));
        foreach ($db->fetchAll("SELECT skey, value_json FROM settings WHERE skey IN ($in)", $keys) as $r) {
            $out[$r['skey']] = json_decode((string) $r['value_json'], true);
        }
        return $out;
    }

    private static function columns(Database $db, string $table): array
    {
        return $db->driver === 'mysql' ? array_column($db->fetchAll("SHOW COLUMNS FROM $table"), 'Field')
            : array_column($db->fetchAll("PRAGMA table_info($table)"), 'name');
    }

    /** Einfacher Vorgabewert aus theme.php (z. B. 'icon_bg' => '#0F6E68') – ohne die Datei auszuführen */
    private static function themeValue(string $theme, string $name): string
    {
        $f = ROOT . '/themes/' . preg_replace('~[^a-z0-9_\-]~i', '', $theme) . '/theme.php';
        return is_file($f) && preg_match("~'" . preg_quote($name, '~') . "'\s*=>\s*'([^']*)'~", (string) file_get_contents($f), $m) ? $m[1] : '';
    }

    /** @return list<string> Pools, die eine Website nutzt */
    private static function sitePools(string $key, array $configured): array
    {
        $out = [];
        foreach (array_keys(MediaPools::all()) as $p) {
            if (in_array($p, $configured, true) || in_array($key, (array) (MediaPools::meta($p)['sites'] ?? []), true)) $out[] = $p;
        }
        return $out;
    }

    /** Zuletzt gespeicherte Kennzahlen ohne Neuberechnung (z. B. für Symbole im Website-Umschalter) */
    public static function cached(string $key): ?array
    {
        $f = self::cacheFile($key);
        $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        return is_array($d) ? $d : null;
    }

    private static function themeRequires(string $theme): string
    {
        $f = ROOT . '/themes/' . preg_replace('~[^a-z0-9_\-]~i', '', $theme) . '/theme.php';
        return is_file($f) && preg_match("~'requires'\s*=>\s*'([^']*)'~", (string) file_get_contents($f), $m) ? trim($m[1]) : '';
    }

    /** @return array{0:int,1:int} Bytes, Dateien */
    public static function dirSize(string $dir): array
    {
        if (!is_dir($dir)) return [0, 0];
        $bytes = $files = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $bytes += $f->getSize();
                $files++;
            }
        }
        return [$bytes, $files];
    }

    /** Letzte Sicherung (site:backup legt storage/backups/{key}-JJJJMMTT-HHMMSS.tar.gz an) */
    public static function lastBackup(string $key): ?array
    {
        $best = null;
        foreach (glob(ROOT . '/storage/backups/' . $key . '-*.tar.gz') ?: [] as $f) {
            if (!preg_match('~^' . preg_quote($key, '~') . '-\d{8}-\d{6}\.tar\.gz$~', basename($f))) continue;
            $t = (int) filemtime($f);
            if (!$best || $t > $best['at']) $best = ['at' => $t, 'file' => basename($f), 'size' => (int) filesize($f)];
        }
        return $best;
    }

    /** Warnungen einer Website (für Zusammenfassung und Filter) */
    public static function warnings(array $s): array
    {
        $w = [];
        if (empty($s['initialized']) && !isset($s['error'])) $w[] = __('Noch nicht eingerichtet (erster Aufruf fehlt)');
        if (isset($s['error'])) $w[] = __('Datenbank nicht erreichbar');
        foreach (($s['checks'] ?? []) as $c => $ok) {
            if (!$ok && $c !== 'db') $w[] = match ($c) {
                'app_key' => __('app_key fehlt'), 'storage' => __('Datenordner nicht beschreibbar'),
                'media' => __('Medienordner nicht beschreibbar'), 'theme' => __('Kit nicht kompatibel'), default => $c,
            };
        }
        if (!empty($s['migrate'])) $w[] = __('Datenbank-Aktualisierung ausstehend (migrate)');
        if (!empty($s['maintenance'])) $w[] = __('Wartungsmodus an');
        if (!empty($s['placeholders'])) $w[] = $s['placeholders'] === 1
            ? __('Platzhalter {text} auf „{page}“', ['text' => $s['placeholder_first']['text'] ?? '', 'page' => $s['placeholder_first']['title'] ?? ''])
            : __('{n} Platzhalter in Seiten (z. B. {text} auf „{page}“)', ['n' => $s['placeholders'], 'text' => $s['placeholder_first']['text'] ?? '', 'page' => $s['placeholder_first']['title'] ?? '']);
        if (($s['environment'] ?? 'production') === 'production' && !empty($s['initialized'])
            && (empty($s['backup']) || $s['backup']['at'] < time() - 7 * 86400)) $w[] = __('Keine Sicherung in den letzten 7 Tagen');
        return $w;
    }

    // ================================================================= Aktionen

    public static function setMaintenance(string $key, bool $on): void
    {
        $db = self::db($key);
        $json = json_encode($on);
        if ($db->driver === 'mysql') {
            $db->query('REPLACE INTO settings (skey, value_json) VALUES (?, ?)', ['sys.maintenance', $json]);
        } else {
            $db->query('INSERT INTO settings (skey, value_json) VALUES (?, ?) ON CONFLICT(skey) DO UPDATE SET value_json = excluded.value_json', ['sys.maintenance', $json]);
        }
        if ($key === site()->key) app()->settings->override(['sys.maintenance' => $on]);
        PageCache::clearSite($key);
        self::forget($key);
    }

    public static function clearCache(string $key): void
    {
        PageCache::clearSite($key);
    }

    /**
     * Sicherung über die Kommandozeile (gleicher Code wie `site:backup`) in einem eigenen PHP-Prozess.
     * @return string Pfad der Sicherung
     */
    public static function backup(string $key): string
    {
        if (!function_exists('proc_open')) throw new \RuntimeException(__('Auf diesem Server nicht möglich (proc_open gesperrt). Bitte per Kommandozeile: {cmd}', ['cmd' => "php bin/console site:backup --site=$key"]));
        $php = self::phpBinary();
        $cmd = [$php, ROOT . '/bin/console', 'site:backup', '--site=' . $key];
        $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT);
        if (!is_resource($p)) throw new \RuntimeException(__('Sicherung konnte nicht gestartet werden.'));
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($p);
        $file = trim((string) $out);
        if ($code !== 0 || !is_file($file)) throw new \RuntimeException(__('Sicherung fehlgeschlagen: {msg}', ['msg' => trim((string) $err) ?: 'exit ' . $code]));
        self::forget($key);
        return $file;
    }

    /**
     * PHP-Kommandozeile für Hintergrund-Arbeiter (unter PHP-FPM ist PHP_BINARY der FPM-Prozess).
     * Vorrang: Konfiguration 'php_cli'. Sonst Kandidaten in dieser Reihenfolge – PHP_BINDIR/php (gleiche Version wie FPM),
     * neben dem FPM-Programm (Plesk: /opt/plesk/php/8.5/sbin/php-fpm → …/bin/php), php8.x über PATH, /usr/bin/php, php –
     * jeweils per Aufruf geprüft (`-r 'echo PHP_VERSION;'`, mindestens 8.4), NICHT per is_executable: mit open_basedir
     * (Plesk) scheitert die Dateiprüfung, und /usr/bin/php ist dort oft ein älteres System-PHP. Ergebnis 1 Stunde in
     * storage/cache/php-cli.json.
     */
    public static function phpBinary(): string
    {
        $cfg = (string) (Network::install()['php_cli'] ?? app()->config->get('php_cli', ''));
        if ($cfg !== '') return $cfg;
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') return PHP_BINARY;
        if (preg_match('~^php[\d.]*$~', basename(PHP_BINARY))) return PHP_BINARY;
        static $found = null;
        if ($found !== null) return $found;
        $cache = ROOT . '/storage/cache/php-cli.json';
        $c = @json_decode((string) @file_get_contents($cache), true);
        if (is_array($c) && ($c['at'] ?? 0) > time() - 3600 && ($c['fpm'] ?? '') === PHP_BINARY && !empty($c['bin'])) return $found = (string) $c['bin'];
        $cands = array_values(array_unique([PHP_BINDIR . '/php', dirname(PHP_BINARY, 2) . '/bin/php', dirname(PHP_BINARY) . '/php',
            'php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, '/usr/bin/php', 'php']));
        $found = PHP_BINDIR . '/php';
        $basedir = trim((string) ini_get('open_basedir')) !== '';
        foreach ($cands as $cand) {
            if (!$basedir && str_contains($cand, '/') && !@is_file($cand)) continue;   // ohne open_basedir: billige Vorprüfung
            [$code, $out] = \Core\Ffmpeg::exec([$cand, '-n', '-r', 'echo PHP_VERSION;'], 5);
            if ($code === 0 && preg_match('~^(\d+)\.(\d+)~', trim((string) $out), $m) && version_compare($m[1] . '.' . $m[2], '8.4', '>=')) {
                $found = $cand;
                break;
            }
        }
        if (!is_dir(dirname($cache))) @mkdir(dirname($cache), 0775, true);
        @file_put_contents($cache, json_encode(['bin' => $found, 'fpm' => PHP_BINARY, 'at' => time()], JSON_UNESCAPED_SLASHES));
        return $found;
    }

    private static function db(string $key): Database
    {
        if ($key === site()->key) return app()->db;
        $cfg = (array) Network::config($key)->get('db');
        if (($cfg['driver'] ?? 'sqlite') === 'sqlite' && !is_file((string) $cfg['path'])) throw new \RuntimeException(__('Website noch nicht eingerichtet.'));
        return new Database($cfg);
    }

    // ================================================================= Geteilte Ressourcen (nur Anzahlen)

    /** @return array{0:int,1:int} Bytes und Dateien eines Pool-Ordners (public/pools/{key}), SIZE_TTL zwischengespeichert */
    public static function poolSize(string $key): array
    {
        $f = ROOT . '/storage/cache/network/pool-' . preg_replace('~[^a-z0-9_\-]~i', '', $key) . '.json';
        $c = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        if (is_array($c) && ($c['at'] ?? 0) > time() - self::SIZE_TTL) return [(int) $c['bytes'], (int) $c['files']];
        [$bytes, $files] = self::dirSize(MediaPools::mediaDir($key));
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0770, true);
        @file_put_contents($f, json_encode(['bytes' => $bytes, 'files' => $files, 'at' => time()]), LOCK_EX);
        return [$bytes, $files];
    }

    public static function shared(): array
    {
        $pools = [];
        // Welche Websites nutzen den Pool? Zuordnung in pool.json und 'media_pools' der Konfigurationen
        $users = [];
        foreach (array_keys(Sites::all()) as $sk) {
            try {
                foreach ((array) Network::config($sk)->get('media_pools', []) as $p) $users[(string) $p][] = $sk;
            } catch (\Throwable) {
            }
        }
        foreach (MediaPools::all() as $k => $label) {
            $sites = array_values(array_unique(array_merge((array) (MediaPools::meta($k)['sites'] ?? []), $users[$k] ?? [])));
            [$bytes, $count] = self::poolSize($k);
            try {
                $pools[] = ['key' => $k, 'label' => $label, 'files' => (int) MediaPools::db($k)->fetchValue('SELECT COUNT(*) FROM media'),
                    'sites' => $sites, 'size' => $bytes, 'disk_files' => $count];
            } catch (\Throwable) {
                $pools[] = ['key' => $k, 'label' => $label, 'files' => null, 'sites' => $sites, 'size' => $bytes, 'disk_files' => $count];
            }
        }
        $tables = [];
        foreach (glob(ROOT . '/storage/shared/*/share.json') ?: [] as $f) {
            $m = json_decode((string) file_get_contents($f), true) ?: [];
            $k = basename(dirname($f));
            $n = null;
            $db = dirname($f) . '/share.sqlite';
            if (is_file($db) && preg_match('~^[a-z][a-z0-9_]{1,40}$~', $k)) {
                try {
                    $n = (int) (new \PDO('sqlite:' . $db, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]))->query("SELECT COUNT(*) FROM data_$k")->fetchColumn();
                } catch (\Throwable) {
                    $n = null;
                }
            }
            $tables[] = ['key' => $k, 'label' => (string) ($m['name'] ?? $m['label'] ?? $k), 'owner' => (string) ($m['owner'] ?? ''),
                'members' => array_values((array) ($m['members'] ?? [])), 'entries' => $n];
        }
        return ['pools' => $pools, 'tables' => $tables];
    }
}
