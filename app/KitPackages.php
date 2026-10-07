<?php
declare(strict_types=1);

namespace Core;

use Core\Network\Network;
use Core\Network\Stats;

/**
 * Kits als Pakete (Core\Kit findet sie): ZIP-Upload bzw. kit:install, Entfernen, Veröffentlichen der fertigen Assets.
 *
 * Paket-Aufbau (ZIP: Kit-Ordner direkt in der Wurzel oder genau ein Ordner darüber):
 *   theme.php | kit.php     Definition wie bei jedem Kit ('label', 'version', 'requires' => '>=1.0.0' …)
 *   composer.json           "type": "klxm-studio-kit", "extra": {"klxm-studio": {"kit": "<name>", "public": "public"}}
 *   templates/ blocks/ …    wie unter kits/{name}/
 *   public/                 fertig gebaute Assets (css/, js/, img/, fonts/ …) – Zielserver haben keinen Node-Build;
 *                           kits:publish kopiert sie nach public/assets/kits/{name}/ (nur Dateitypen aus PUBLIC_TYPES)
 *
 * Hochgeladene Kits liegen unter storage/kits/{name}/ (übersteht Deploys) mit der Merkdatei .klxm-kit.json;
 * Composer-Pakete unter vendor/. Kits unter kits/ (mitgeliefert bzw. lokal entwickelt) fasst diese Klasse nie an.
 * Ein Kit enthält PHP-Code, der auf allen Websites der Installation läuft – nur aus vertrauenswürdiger Quelle installieren.
 */
final class KitPackages
{
    /** Merkdatei eines hochgeladenen Kits (Version, Zeitpunkt, Person, Prüfsumme) */
    public const MARKER = '.klxm-kit.json';
    /** Größte ZIP-Datei, größter entpackter Umfang, meiste Einträge */
    public const MAX_ZIP = 50 * 1024 * 1024;
    public const MAX_UNPACKED = 200 * 1024 * 1024;
    public const MAX_ENTRIES = 5000;
    /** Dateitypen, die aus public/ des Pakets öffentlich ausgeliefert werden (nie PHP, .htaccess o. Ä.) */
    public const PUBLIC_TYPES = ['css', 'js', 'mjs', 'map', 'json', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
        'woff', 'woff2', 'ttf', 'otf', 'eot', 'mp4', 'webm', 'vtt', 'mp3', 'txt', 'pdf', 'webmanifest'];
    /** Beim Entpacken übersprungen */
    private const SKIP = ['__MACOSX', '.DS_Store', '.git', 'node_modules', 'Thumbs.db'];

    /**
     * Darf diese Person Kits installieren/entfernen? Kits gelten für die ganze Installation (alle Websites) –
     * daher wie bei Schriften: Integratoren, Netzwerk-Administration bzw. Administration der Netzwerk-Website.
     */
    public static function canManage(): bool
    {
        return Features::integrator() || (can('system.manage') && Network::isNetworkSite());
    }

    /** ZIP-Upload in der Verwaltung erlaubt? (config 'kit_upload' => false schaltet ihn ab; kit:install geht weiter) */
    public static function uploadAllowed(): bool
    {
        return (bool) app()->config->get('kit_upload', true) && class_exists(\ZipArchive::class);
    }

    /** Ordner hochgeladener Kits: storage/kits */
    public static function uploadDir(string $name = ''): string
    {
        return ROOT . '/' . Kit::UPLOADS . ($name !== '' ? '/' . $name : '');
    }

    // ------------------------------------------------------------------ Übersicht

    /**
     * Alle Kits mit Herkunft, Version und Verwendung (Verwaltung → Grundeinstellungen → Kits, kit:list).
     * @return array<string, array{name: string, label: string, version: string, requires: string, compatible: bool, source: string, dir: string, package: string, installed: array, used: list<string>, published: ?bool, shadowed: list<array>}>
     */
    public static function overview(): array
    {
        $usage = self::usageAll();
        $shadow = Kit::shadowed();
        $out = [];
        foreach (Kit::all() as $name => $dir) {
            $info = (array) Kit::info($name);
            $m = Kit::meta($dir);
            $src = (string) $info['source'];
            $pkg = (array) ($info['package'] ?? []);
            $out[$name] = [
                'name' => $name, 'label' => $m['label'] !== '' ? $m['label'] : $name,
                'version' => $m['version'] !== '' ? $m['version'] : ltrim((string) ($pkg['version'] ?? ''), 'v'),
                'requires' => $m['requires'], 'compatible' => Kit::compatible($m['requires']),
                'source' => $src, 'dir' => Kit::relative($dir), 'package' => (string) ($pkg['name'] ?? ''),
                'installed' => $src === 'upload' ? self::marker($name) : [],
                'used' => $usage[$name] ?? [],
                'published' => $src === 'local' ? null : is_dir(Kit::publicDir($name)),
                'shadowed' => $shadow[$name] ?? [],
            ];
        }
        return $out;
    }

    /** Merkdatei eines hochgeladenen Kits */
    public static function marker(string $name): array
    {
        $f = self::uploadDir(Kit::clean($name)) . '/' . self::MARKER;
        return is_file($f) ? (array) json_decode((string) file_get_contents($f), true) : [];
    }

    /** Welche Websites nutzen das Kit als aktives Kit? [kit => [website-schlüssel …]] */
    public static function usageAll(): array
    {
        $out = [];
        foreach (array_keys(Sites::all()) as $key) {
            $kit = self::siteKit((string) $key);
            if ($kit !== '') $out[$kit][] = (string) $key;
        }
        return $out;
    }

    /** @return list<string> Websites, die das Kit nutzen */
    public static function usage(string $name): array
    {
        return self::usageAll()[Kit::clean($name)] ?? [];
    }

    /** Aktives Kit einer Website: Einstellung sys.theme, sonst Konfiguration 'kit'/'theme' – ohne die Website zu starten */
    private static function siteKit(string $key): string
    {
        try {
            if ($key === site()->key) return (string) app()->settings->get('sys.theme', '') ?: (string) (app()->config->get('kit') ?: app()->config->get('theme') ?: site()->defaultTheme());
            $cfg = Network::config($key);
            $conf = (string) ($cfg->get('kit') ?: $cfg->get('theme') ?: '');
            $db = (array) $cfg->get('db');
            if (($db['driver'] ?? 'sqlite') === 'sqlite' && !is_file((string) ($db['path'] ?? ''))) return $conf;
            $v = (new Database($db))->fetchValue('SELECT value_json FROM settings WHERE skey = ?', ['sys.theme']);
            $set = $v !== null && $v !== false ? (string) json_decode((string) $v, true) : '';
            return $set !== '' ? $set : $conf;
        } catch (\Throwable $e) {
            error_log('[Kits] Verwendung von Website „' . $key . '“: ' . $e->getMessage());
            return '';
        }
    }

    // ------------------------------------------------------------------ Veröffentlichen

    /**
     * Fertige Assets eines Pakets (upload/composer) nach public/assets/kits/{name} kopieren – ersetzt den Ordner vollständig
     * (wiederholbar). Lokale Kits (kits/) baut tools/build.mjs; sie werden nie angefasst. Ohne Namen: alle Pakete.
     * @return array<string, string> [kit => Meldung]
     */
    public static function publish(?string $only = null): array
    {
        $out = [];
        foreach (Kit::all() as $name => $dir) {
            if ($only !== null && $name !== Kit::clean($only)) continue;
            $info = (array) Kit::info($name);
            if (($info['source'] ?? 'local') === 'local') {
                if ($only !== null) $out[$name] = __('lokales Kit – Assets baut tools/build.mjs');
                continue;
            }
            $src = (string) ($info['public'] ?? '');
            $dst = Kit::publicDir($name);
            if ($src === '' || !is_dir($src)) {
                $out[$name] = __('keine fertigen Assets im Paket ({dir})', ['dir' => Kit::relative($src ?: $dir . '/public')]);
                continue;
            }
            @mkdir(dirname($dst), 0775, true);
            $tmp = dirname($dst) . '/.' . $name . '.tmp-' . bin2hex(random_bytes(4));
            $n = $skipped = 0;
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($it as $f) {
                if ($f->isLink()) { $skipped++; continue; }
                $rel = substr($f->getPathname(), strlen($src) + 1);
                if ($f->isDir()) continue;
                if (!in_array(strtolower($f->getExtension()), self::PUBLIC_TYPES, true) || str_starts_with(basename($rel), '.')) { $skipped++; continue; }
                @mkdir(dirname("$tmp/$rel"), 0775, true);
                if (copy($f->getPathname(), "$tmp/$rel")) $n++;
            }
            @mkdir($tmp, 0775, true);
            // Austauschen: alter Stand beiseite, neuer an seinen Platz, alter weg
            $old = is_dir($dst) ? dirname($dst) . '/.' . $name . '.old-' . bin2hex(random_bytes(4)) : null;
            if ($old) rename($dst, $old);
            rename($tmp, $dst);
            if ($old) self::rmdir($old);
            $out[$name] = __('{n} Datei(en) veröffentlicht', ['n' => $n]) . ($skipped ? ' · ' . __('{n} übersprungen (Dateityp nicht erlaubt)', ['n' => $skipped]) : '');
        }
        return $out;
    }

    // ------------------------------------------------------------------ Installieren

    /**
     * Kit-Paket installieren: ZIP-Datei oder Ordner → prüfen → storage/kits/{name} (atomar) → Assets veröffentlichen.
     * $force: ein bereits hochgeladenes Kit gleichen Namens ersetzen. Mitgelieferte/lokale Kits (kits/) und Composer-Pakete
     * werden nie ersetzt.
     * @return array{ok: bool, name: string, message: string, warnings: list<string>}
     */
    public static function install(string $path, bool $force = false, string $by = '', string $filename = ''): array
    {
        $fail = fn(string $msg, string $name = '') => ['ok' => false, 'name' => $name, 'message' => $msg, 'warnings' => []];
        @mkdir(self::uploadDir(), 0775, true);
        if (!is_dir(self::uploadDir()) || !is_writable(self::uploadDir())) return $fail(__('Ordner {dir} ist nicht beschreibbar.', ['dir' => Kit::UPLOADS]));
        $work = self::uploadDir('.tmp-' . bin2hex(random_bytes(6)));
        mkdir($work, 0775);
        try {
            if (is_dir($path)) {
                $err = self::copyDir($path, $work . '/x');
            } elseif (is_file($path)) {
                $err = self::unzip($path, $work . '/x');
            } else {
                $err = __('Datei bzw. Ordner nicht gefunden.');
            }
            if ($err !== null) return $fail($err);
            $root = self::findRoot($work . '/x');
            if ($root === null) return $fail(__('Kein Kit gefunden: theme.php bzw. kit.php fehlt (im ZIP direkt oder in genau einem Ordner).'));
            $composer = is_file("$root/composer.json") ? json_decode((string) file_get_contents("$root/composer.json"), true) : [];
            if (!is_array($composer)) return $fail(__('composer.json ist kein gültiges JSON.'));
            if (isset($composer['type']) && $composer['type'] !== Kit::PACKAGE_TYPE) return $fail(__('composer.json: „type“ muss „{type}“ sein.', ['type' => Kit::PACKAGE_TYPE]));
            // Name: composer.json → extra.klxm-studio.kit, sonst Ordnername, sonst Dateiname des ZIP
            $base = $root !== $work . '/x' ? basename($root) : pathinfo($filename !== '' ? $filename : $path, PATHINFO_FILENAME);
            $name = strtolower((string) ($composer['extra']['klxm-studio']['kit'] ?? $base));
            if (!preg_match('~^[a-z][a-z0-9_-]{1,40}$~', $name)) return $fail(__('Ungültiger Kit-Name „{name}“ (erlaubt: a–z, 0–9, - und _, beginnt mit einem Buchstaben, 2–41 Zeichen).', ['name' => $name]));
            $check = self::check($root);
            $reqCore = (string) ($composer['extra']['klxm-studio']['requires-core'] ?? '');
            if (!Kit::compatible($reqCore)) $check['errors'][] = __('Das Kit verlangt Core {req} – installiert ist {core}.', ['req' => $reqCore, 'core' => CMS_VERSION]);
            if ($check['errors']) return $fail(implode(' ', $check['errors']), $name);
            // Namenskonflikte
            $src = Kit::source($name);
            if ($src === 'local') return $fail(__('Es gibt bereits ein mitgeliefertes bzw. lokales Kit „{name}“ (kits/{name}) – es wird nie ersetzt. Bitte das Paket umbenennen.', ['name' => $name]), $name);
            if ($src === 'composer') return $fail(__('Kit „{name}“ ist bereits per Composer installiert – bitte dort aktualisieren.', ['name' => $name]), $name);
            $target = self::uploadDir($name);
            if (is_dir($target) && !$force) return $fail(__('Kit „{name}“ ist bereits installiert. Zum Ersetzen erneut mit „Ersetzen“ (bzw. --force) installieren.', ['name' => $name]), $name);
            // Merkdatei, dann atomar an den Platz
            $hash = is_file($path) ? (string) hash_file('sha256', $path) : '';
            file_put_contents("$root/" . self::MARKER, json_encode(['name' => $name, 'version' => $check['meta']['version'], 'installed_at' => date('c'),
                'by' => $by, 'file' => $filename !== '' ? $filename : basename($path), 'sha256' => $hash, 'core' => CMS_VERSION], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $old = null;
            if (is_dir($target)) {
                $old = self::uploadDir('.old-' . $name . '-' . bin2hex(random_bytes(4)));
                if (!rename($target, $old)) return $fail(__('Das bisherige Kit konnte nicht ersetzt werden.'), $name);
            }
            if (!rename($root, $target)) {
                if ($old) rename($old, $target);
                return $fail(__('Kit konnte nicht nach {dir} verschoben werden.', ['dir' => Kit::UPLOADS . '/' . $name]), $name);
            }
            if ($old) self::rmdir($old);
            Kit::reset();
            $pub = self::publish($name)[$name] ?? '';
            $warn = $check['warnings'];
            if (!is_dir(Kit::publicDir($name))) $warn[] = __('Keine fertigen Assets (Ordner public/) – das Kit hat auf der Website kein CSS/JS.');
            foreach (array_keys(Sites::all()) as $k) PageCache::clearSite((string) $k);
            $verb = $old ? __('ersetzt') : __('installiert');
            return ['ok' => true, 'name' => $name, 'warnings' => $warn,
                'message' => __('Kit „{label}“ ({name} {version}) {verb}.', ['label' => $check['meta']['label'], 'name' => $name, 'version' => $check['meta']['version'], 'verb' => $verb]) . ($pub !== '' ? ' ' . $pub . '.' : '')];
        } finally {
            self::rmdir($work);
        }
    }

    /**
     * Kit-Ordner prüfen, ohne Code auszuführen: Definitionsdatei mit 'label', Core-Version ('requires'), Syntax (php -l,
     * falls ein PHP-Kommandozeilenprogramm verfügbar ist).
     * @return array{errors: list<string>, warnings: list<string>, meta: array}
     */
    public static function check(string $dir): array
    {
        $errors = $warnings = [];
        $def = Kit::definitionFile($dir);
        $meta = Kit::meta($dir);
        if ($def === null) return ['errors' => [__('theme.php bzw. kit.php fehlt.')], 'warnings' => [], 'meta' => $meta];
        $src = (string) file_get_contents($def);
        if (!str_contains($src, '<?php') || !preg_match('~\breturn\b~', $src)) $errors[] = __('{file} gibt keine Kit-Definition zurück.', ['file' => basename($def)]);
        if ($meta['label'] === '') $errors[] = __('{file}: Angabe \'label\' fehlt.', ['file' => basename($def)]);
        if ($meta['version'] === '') $warnings[] = __('{file}: Angabe \'version\' fehlt.', ['file' => basename($def)]);
        if (!Kit::compatible($meta['requires'])) $errors[] = __('Das Kit verlangt Core {req} – installiert ist {core}.', ['req' => $meta['requires'], 'core' => CMS_VERSION]);
        if (!is_dir("$dir/templates")) $warnings[] = __('Ordner templates/ fehlt.');
        // Syntax prüfen (php -l führt nichts aus)
        $files = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) if (in_array(strtolower($f->getExtension()), ['php', 'phtml'], true)) $files[] = $f->getPathname();
        $bin = class_exists(Stats::class) ? Stats::phpBinary() : PHP_BINARY;
        $checked = 0;
        foreach (array_slice($files, 0, 800) as $f) {
            [$code, $out, $err] = Ffmpeg::exec([$bin, '-n', '-l', $f], 15) + [2 => ''];
            if ($code === 127) { $warnings[] = __('Syntaxprüfung (php -l) nicht möglich – proc_open fehlt.'); break; }
            $checked++;
            if ($code !== 0) {
                $msg = trim((string) preg_replace('~\s+~', ' ', str_replace([$dir . '/', 'No syntax errors detected in'], '', $out . ' ' . $err)));
                $errors[] = __('Syntaxfehler: {msg}', ['msg' => mb_substr($msg !== '' ? $msg : substr($f, strlen($dir) + 1), 0, 300)]);
                if (count($errors) > 5) break;
            }
        }
        return ['errors' => $errors, 'warnings' => $warnings, 'meta' => $meta + ['php_files' => count($files), 'checked' => $checked]];
    }

    /** Kit-Wurzel: theme.php/kit.php direkt oder in genau einem Ordner */
    private static function findRoot(string $dir): ?string
    {
        if (Kit::definitionFile($dir) !== null) return $dir;
        $entries = array_values(array_filter(scandir($dir) ?: [], fn($f) => $f !== '.' && $f !== '..' && !in_array($f, self::SKIP, true)));
        if (count($entries) === 1 && is_dir("$dir/{$entries[0]}") && Kit::definitionFile("$dir/{$entries[0]}") !== null) return "$dir/{$entries[0]}";
        return null;
    }

    /**
     * ZIP sicher entpacken: Größen- und Anzahlgrenzen, keine absoluten Pfade, kein „..“, keine Symlinks, keine Gerätedateien.
     * Gibt eine Fehlermeldung zurück oder null.
     */
    private static function unzip(string $file, string $to): ?string
    {
        if (!class_exists(\ZipArchive::class)) return __('PHP-Erweiterung zip fehlt.');
        if ((int) filesize($file) > self::MAX_ZIP) return __('ZIP-Datei ist zu groß (höchstens {mb} MB).', ['mb' => self::MAX_ZIP / 1048576]);
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::RDONLY) !== true) return __('Keine gültige ZIP-Datei.');
        try {
            if ($zip->numFiles > self::MAX_ENTRIES) return __('Zu viele Dateien im ZIP (höchstens {n}).', ['n' => self::MAX_ENTRIES]);
            $total = 0;
            $plan = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $st = $zip->statIndex($i);
                $n = (string) ($st['name'] ?? '');
                if ($n === '' || str_contains($n, "\0") || str_contains($n, '\\') || str_starts_with($n, '/') || preg_match('~^[a-z]:~i', $n)) return __('Unzulässiger Pfad im ZIP: {path}', ['path' => mb_substr($n, 0, 120)]);
                $parts = explode('/', rtrim($n, '/'));
                if (in_array('..', $parts, true) || in_array('.', $parts, true)) return __('Unzulässiger Pfad im ZIP: {path}', ['path' => mb_substr($n, 0, 120)]);
                $attr = $opsys = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === \ZipArchive::OPSYS_UNIX) {
                    $type = ($attr >> 16) & 0170000;
                    if ($type === 0120000) return __('Symbolische Links sind im Kit nicht erlaubt: {path}', ['path' => mb_substr($n, 0, 120)]);
                    if ($type !== 0 && $type !== 0100000 && $type !== 0040000) return __('Unzulässiger Dateityp im ZIP: {path}', ['path' => mb_substr($n, 0, 120)]);
                }
                if (array_intersect($parts, self::SKIP)) continue;
                $total += (int) ($st['size'] ?? 0);
                if ($total > self::MAX_UNPACKED) return __('Entpackt zu groß (höchstens {mb} MB).', ['mb' => self::MAX_UNPACKED / 1048576]);
                $plan[$i] = [$n, str_ends_with($n, '/')];
            }
            mkdir($to, 0775, true);
            foreach ($plan as $i => [$n, $isDir]) {
                $target = $to . '/' . rtrim($n, '/');
                if ($isDir) { @mkdir($target, 0775, true); continue; }
                @mkdir(dirname($target), 0775, true);
                $in = $zip->getStream($n);
                $out = @fopen($target, 'xb');
                if (!$in || !$out) return __('Datei konnte nicht entpackt werden: {path}', ['path' => mb_substr($n, 0, 120)]);
                // Größe beim Schreiben erneut begrenzen (Angaben im ZIP können falsch sein)
                $written = 0;
                while (!feof($in)) {
                    $chunk = (string) fread($in, 65536);
                    $written += strlen($chunk);
                    if ($written > self::MAX_UNPACKED) { fclose($in); fclose($out); return __('Entpackt zu groß (höchstens {mb} MB).', ['mb' => self::MAX_UNPACKED / 1048576]); }
                    fwrite($out, $chunk);
                }
                fclose($in);
                fclose($out);
            }
        } finally {
            $zip->close();
        }
        return null;
    }

    /** Ordner kopieren (kit:install <ordner>) – Symlinks werden abgelehnt, node_modules/.git übersprungen */
    private static function copyDir(string $src, string $dst): ?string
    {
        $src = rtrim((string) realpath($src), '/');
        mkdir($dst, 0775, true);
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS),
            fn(\SplFileInfo $f) => !in_array($f->getFilename(), self::SKIP, true)), \RecursiveIteratorIterator::SELF_FIRST);
        $total = 0;
        foreach ($it as $f) {
            $rel = substr($f->getPathname(), strlen($src) + 1);
            if ($f->isLink()) return __('Symbolische Links sind im Kit nicht erlaubt: {path}', ['path' => $rel]);
            if ($f->isDir()) { @mkdir("$dst/$rel", 0775, true); continue; }
            $total += $f->getSize();
            if ($total > self::MAX_UNPACKED) return __('Entpackt zu groß (höchstens {mb} MB).', ['mb' => self::MAX_UNPACKED / 1048576]);
            @mkdir(dirname("$dst/$rel"), 0775, true);
            copy($f->getPathname(), "$dst/$rel");
        }
        return null;
    }

    // ------------------------------------------------------------------ Entfernen

    /**
     * Hochgeladenes Kit entfernen (storage/kits/{name} und public/assets/kits/{name}). Nie, solange eine Website es nutzt;
     * mitgelieferte/lokale Kits und Composer-Pakete (composer remove) nie.
     * @return array{ok: bool, message: string}
     */
    public static function remove(string $name): array
    {
        $name = Kit::clean($name);
        $src = Kit::source($name);
        if ($src === null) return ['ok' => false, 'message' => __('Kit „{name}“ nicht gefunden.', ['name' => $name])];
        if ($src === 'local') return ['ok' => false, 'message' => __('„{name}“ ist ein mitgeliefertes bzw. lokales Kit (kits/{name}) und lässt sich hier nicht entfernen.', ['name' => $name])];
        if ($src === 'composer') return ['ok' => false, 'message' => __('„{name}“ ist per Composer installiert – entfernen mit composer remove {package}.', ['name' => $name, 'package' => (string) ((Kit::info($name)['package']['name'] ?? '') ?: '…')])];
        if ($used = self::usage($name)) return ['ok' => false, 'message' => __('Kit „{name}“ wird noch verwendet: {sites}. Bitte dort zuerst ein anderes Kit wählen.', ['name' => $name, 'sites' => implode(', ', $used)])];
        $dir = self::uploadDir($name);
        $trash = self::uploadDir('.old-' . $name . '-' . bin2hex(random_bytes(4)));
        if (!rename($dir, $trash)) return ['ok' => false, 'message' => __('Kit konnte nicht entfernt werden.')];
        self::rmdir($trash);
        if (is_dir(Kit::publicDir($name))) self::rmdir(Kit::publicDir($name));
        Kit::reset();
        return ['ok' => true, 'message' => __('Kit „{name}“ entfernt.', ['name' => $name])];
    }

    // ------------------------------------------------------------------ Prüfung

    /** Für health: verdeckte Kits, Pakete ohne veröffentlichte Assets, unpassende Core-Version (nur Hinweise) */
    public static function health(): array
    {
        $out = [];
        foreach (Kit::shadowed() as $name => $list) {
            foreach ($list as $s) $out[__('Kit „{name}“ unter {dir} ({source}) ist verdeckt – es gilt {active}', ['name' => $name, 'dir' => Kit::relative($s['dir']), 'source' => $s['source'], 'active' => Kit::relative(Kit::dir($name))])] = null;
        }
        foreach (Kit::all() as $name => $dir) {
            $src = Kit::source($name);
            if ($src === 'local') continue;
            if (!is_dir(Kit::publicDir($name))) $out[__('Kit „{name}“: Assets nicht veröffentlicht – php bin/console kits:publish', ['name' => $name])] = null;
            $req = Kit::meta($dir)['requires'];
            if (!Kit::compatible($req)) $out[__('Kit „{name}“ verlangt Core {req}', ['name' => $name, 'req' => $req])] = null;
        }
        return $out;
    }

    private static function rmdir(string $dir): void
    {
        if (is_link($dir) || is_file($dir)) { @unlink($dir); return; }
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) ($f->isDir() && !$f->isLink()) ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }
}
