<?php
declare(strict_types=1);

namespace Core;

/**
 * ffmpeg/ffprobe finden und aufrufen (optional – ohne ffmpeg läuft alles wie bisher).
 *
 * Ausschließlich proc_open mit Argument-Array: keine Shell, keine Eingaben von Nutzern in Befehlen.
 *
 * Erkennung per AUFRUF, nicht per Dateiprüfung: `{kandidat} -version` muss mit Exit 0 „ffmpeg version …“ (bzw.
 * „ffprobe version …“) liefern. So klappt es auch mit open_basedir (Plesk: PHP darf /usr/bin nicht lesen, aber
 * Programme starten) – Dateiprüfungen außerhalb von open_basedir finden nicht statt (keine Warnungen im Log).
 * Kandidaten in dieser Reihenfolge:
 *   1. eigener Pfad einer Erweiterung (z. B. 'video_tools' => ['ffmpeg' => …]), sonst Konfiguration 'ffmpeg_path' /
 *      'ffprobe_path' (absolut oder relativ zur Installation)
 *   2. der Name selbst über PATH (proc_open sucht mit PATH=/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin)
 *   3. /usr/bin, /usr/local/bin, /opt/homebrew/bin, storage/video/bin
 * 'ffmpeg_search' => false schaltet 2 und 3 ab (nur der konfigurierte Pfad zählt).
 * Ergebnis je Kandidatenliste in storage/cache/ffmpeg.json (gefunden: 1 Stunde, nicht gefunden: 10 Minuten),
 * damit nicht jede Anfrage Prozesse startet; `health` und `media:thumbs` prüfen frisch (refresh()).
 * Genutzt von Core\VideoThumbs (Vorschaubilder für Videos), Core\AI\Transcriber und der Erweiterung video_tools.
 */
final class Ffmpeg
{
    private const DIRS = ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin'];
    private const TTL_FOUND = 3600;
    private const TTL_MISSING = 600;
    private static array $cache = [];
    private static ?array $disk = null;
    private static bool $fresh = false;

    /** Konfigurierter Pfad ('' = automatisch suchen) */
    public static function configured(string $name): string
    {
        return trim((string) app()->config->get($name . '_path', ''));
    }

    /** proc_open erlaubt (nicht in disable_functions)? */
    public static function canExec(): bool
    {
        if (isset(self::$cache['exec'])) return self::$cache['exec'];
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return self::$cache['exec'] = function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    }

    /** Ist open_basedir gesetzt? (dann keine Dateiprüfungen außerhalb) */
    public static function openBasedir(): string
    {
        return trim((string) ini_get('open_basedir'));
    }

    /** Nächste Aufrufe prüfen neu (CLI: health, media:thumbs) und schreiben den Zwischenspeicher */
    public static function refresh(): void
    {
        self::$cache = [];
        self::$disk = null;
        self::$fresh = true;
    }

    /**
     * Wirksamer Aufruf für 'ffmpeg' bzw. 'ffprobe' ('' = nicht gefunden): absoluter Pfad oder der Name (über PATH).
     * $override: Pfad aus einer eigenen Konfiguration (z. B. video_tools), geht vor 'ffmpeg_path'.
     */
    public static function bin(string $name, string $override = ''): string
    {
        return self::detect($name, $override)['bin'];
    }

    /** Versionszeile des gefundenen Programms („ffmpeg version 7.1.1 …“) */
    public static function version(string $name = 'ffmpeg', string $override = ''): string
    {
        return self::detect($name, $override)['version'];
    }

    /** Konfigurierter Pfad, der beim Aufruf nicht funktioniert? (Statusanzeige – evtl. wurde ein anderer gefunden) */
    public static function misconfigured(string $name, string $override = ''): bool
    {
        return self::detect($name, $override)['bad_config'];
    }

    /** ffmpeg und ffprobe gefunden und proc_open erlaubt? */
    public static function available(): bool
    {
        return self::canExec() && self::bin('ffmpeg') !== '' && self::bin('ffprobe') !== '';
    }

    /** Funktioniert genau dieser Aufruf? (z. B. Pfad aus einer anderen Konfiguration, Core\AI\Transcriber) */
    public static function works(string $bin, string $name = 'ffmpeg'): bool
    {
        return $bin !== '' && self::probe($bin, $name) !== null;
    }

    /** @return array{bin: string, version: string, bad_config: bool} */
    private static function detect(string $name, string $override): array
    {
        $none = ['bin' => '', 'version' => '', 'bad_config' => false];
        if (!in_array($name, ['ffmpeg', 'ffprobe'], true)) return $none;
        $cfg = $override !== '' ? $override : self::configured($name);
        $search = app()->config->get('ffmpeg_search', true) !== false;
        $key = $name . '|' . $cfg . '|' . ($search ? 1 : 0);
        if (isset(self::$cache[$key])) return self::$cache[$key];
        if (!self::canExec()) return self::$cache[$key] = ['bad_config' => $cfg !== ''] + $none;

        // Zwischenspeicher (installationsweit, je Kandidatenliste)
        $disk = self::diskCache();
        $hit = $disk[$key] ?? null;
        if (!self::$fresh && is_array($hit) && ($hit['at'] ?? 0) > time() - (($hit['bin'] ?? '') !== '' ? self::TTL_FOUND : self::TTL_MISSING)) {
            return self::$cache[$key] = ['bin' => (string) $hit['bin'], 'version' => (string) ($hit['version'] ?? ''), 'bad_config' => (bool) ($hit['bad_config'] ?? false)];
        }

        $cands = [];
        if ($cfg !== '') $cands[] = str_starts_with($cfg, '/') ? $cfg : ROOT . '/' . ltrim($cfg, '/');
        if ($search) {
            $cands[] = $name;   // über PATH
            foreach ([...self::DIRS, ROOT . '/storage/video/bin'] as $d) $cands[] = $d . '/' . $name;
        }
        $res = $none;
        foreach (array_values(array_unique($cands)) as $i => $c) {
            $v = self::probe($c, $name);
            if ($v !== null) {
                $res = ['bin' => $c, 'version' => $v, 'bad_config' => $cfg !== '' && $i > 0];
                break;
            }
        }
        if ($res['bin'] === '' && $cfg !== '') $res['bad_config'] = true;
        $disk[$key] = $res + ['at' => time()];
        self::writeDiskCache($disk);
        return self::$cache[$key] = $res;
    }

    /** `{bin} -version` ausführen → Versionszeile oder null */
    private static function probe(string $bin, string $name): ?string
    {
        if (isset(self::$cache['probe:' . $bin])) return self::$cache['probe:' . $bin] ?: null;
        // relative/absolute Pfade nur prüfen, wenn open_basedir das Lesen erlaubt – sonst direkt aufrufen
        if (str_contains($bin, '/') && self::openBasedir() === '' && !@is_file($bin)) {
            self::$cache['probe:' . $bin] = '';
            return null;
        }
        [$c, $out] = self::run([$bin, '-hide_banner', '-version'], 5);
        $line = trim((string) strtok($out, "\n"));
        $ok = $c === 0 && str_starts_with($line, $name . ' version');
        self::$cache['probe:' . $bin] = $ok ? $line : '';
        return $ok ? $line : null;
    }

    private static function cacheFile(): string
    {
        return ROOT . '/storage/cache/ffmpeg.json';
    }

    private static function diskCache(): array
    {
        if (self::$disk !== null) return self::$disk;
        $j = @json_decode((string) @file_get_contents(self::cacheFile()), true);
        return self::$disk = is_array($j) ? $j : [];
    }

    private static function writeDiskCache(array $data): void
    {
        self::$disk = $data;
        $f = self::cacheFile();
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0775, true);
        $tmp = $f . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_SLASHES)) !== false) @rename($tmp, $f); else @unlink($tmp);
    }

    /** Für Tests: Zwischenspeicher der Anfrage leeren */
    public static function forget(): void
    {
        self::$cache = [];
        self::$disk = null;
    }

    /**
     * Programm mit Argument-Array ausführen (synchron, mit Zeitlimit): [exit, stdout, stderr].
     * $nice > 0: mit niedriger CPU-Priorität (nice -n N), falls vorhanden.
     */
    public static function exec(array $argv, int $timeout = 30, int $nice = 0): array
    {
        if (!self::canExec() || !$argv || (string) $argv[0] === '') return [127, '', 'proc_open nicht verfügbar'];
        if ($nice > 0 && self::hasNice()) $argv = ['nice', '-n', (string) min(19, $nice), ...$argv];
        return self::run($argv, $timeout);
    }

    /** `nice` aufrufbar? (per Aufruf geprüft, zwischengespeichert wie die Programme) */
    public static function hasNice(): bool
    {
        if (isset(self::$cache['nice'])) return self::$cache['nice'];
        if (!self::canExec()) return self::$cache['nice'] = false;
        $disk = self::diskCache();
        if (!self::$fresh && isset($disk['nice']['at']) && $disk['nice']['at'] > time() - self::TTL_FOUND) return self::$cache['nice'] = (bool) $disk['nice']['ok'];
        [$c] = self::run(['nice', '-n', '1', 'true'], 3);
        $disk['nice'] = ['ok' => $c === 0, 'at' => time()];
        self::writeDiskCache($disk);
        return self::$cache['nice'] = $c === 0;
    }

    private static function run(array $argv, int $timeout): array
    {
        // stdin als Pipe (sofort geschlossen) statt /dev/null: open_basedir (Plesk) sperrt sonst schon den Start
        $p = @proc_open(array_map('strval', $argv), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT, self::env());
        if (!is_resource($p)) return [127, '', 'Start fehlgeschlagen'];
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = $err = '';
        $end = microtime(true) + $timeout;
        $killed = false;
        while (true) {
            $r = [$pipes[1], $pipes[2]];
            $w = $e = null;
            if (@stream_select($r, $w, $e, 0, 200000) === false) break;
            foreach ($r as $s) {
                $chunk = (string) fread($s, 65536);
                if ($s === $pipes[1]) $out .= $chunk; elseif (strlen($err) < 65536) $err .= $chunk;
            }
            if (feof($pipes[1]) && feof($pipes[2])) break;
            if (microtime(true) > $end) { proc_terminate($p, 9); $err .= "\nZeitlimit"; $killed = true; break; }
            if (strlen($out) > 16 * 1024 * 1024) { proc_terminate($p, 9); $killed = true; break; }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($p);
        return [$killed && $code === 0 ? 124 : $code, $out, $err];
    }

    /** Schlanke Umgebung für Unterprozesse (kein Erbe von Geheimnissen aus $_ENV) */
    public static function env(): array
    {
        return ['PATH' => '/usr/local/bin:/usr/bin:/bin:/opt/homebrew/bin', 'LANG' => 'C', 'HOME' => sys_get_temp_dir()];
    }

    /** Hinweis, warum nichts gefunden wurde (Status, health) */
    public static function hint(): string
    {
        if (!self::canExec()) {
            return __('proc_open ist gesperrt (disable_functions) – ffmpeg lässt sich nicht starten. Plesk: PHP-Einstellungen → disable_functions.');
        }
        if (self::misconfigured('ffmpeg') || self::misconfigured('ffprobe')) {
            return __('Der konfigurierte Pfad (ffmpeg_path/ffprobe_path) lässt sich nicht aufrufen.');
        }
        if (self::openBasedir() !== '') {
            return __('open_basedir ist gesetzt (Plesk: PHP-Einstellungen) und enthält /usr/bin meist nicht – die Erkennung ruft ffmpeg deshalb direkt auf (PATH, /usr/bin, /usr/local/bin). Wird trotzdem nichts gefunden, ist ffmpeg nicht installiert: apt install ffmpeg, oder ein statisches Build nach storage/video/bin/ legen bzw. ffmpeg_path setzen.');
        }
        return __('ffmpeg ist nicht installiert: apt install ffmpeg (bzw. dnf install ffmpeg), oder ein statisches Build nach storage/video/bin/ legen bzw. ffmpeg_path setzen.');
    }

    /** Zeile für `health`: Warnung, nie Fehler (ffmpeg ist optional) */
    public static function health(): array
    {
        if (self::available()) {
            $bin = self::bin('ffmpeg');
            $v = preg_match('~version\s+(\S+)~', self::version('ffmpeg'), $m) ? ', ' . $m[1] : '';
            return [__('ffmpeg für Video-Vorschaubilder: gefunden ({path})', ['path' => (str_contains($bin, '/') ? $bin : $bin . ' via PATH') . $v]) => true];
        }
        return [__('ffmpeg für Video-Vorschaubilder: fehlt (optional) – {hint}', ['hint' => self::hint()]) => null];
    }
}
