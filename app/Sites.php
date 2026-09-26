<?php
declare(strict_types=1);

namespace Core;

/**
 * Verzeichnis der Websites einer Installation (config/sites/*.php) und Auflösung über die Domain.
 * Ohne Dateien in config/sites gibt es genau eine Website „default“ (Einzel-Installation).
 */
final class Sites
{
    private static ?array $cache = null;

    public static function dir(): string
    {
        return ROOT . '/config/sites';
    }

    /** @return array<string, array> key => Konfiguration (roh, ohne Geheimnisse zu filtern) */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $out = [Site::DEFAULT => []];
        foreach (glob(self::dir() . '/*.php') ?: [] as $f) {
            $key = basename($f, '.php');
            if (!self::validKey($key)) continue;
            $cfg = require $f;
            $out[$key] = is_array($cfg) ? $cfg : [];
            if (isset($out[$key]['kit']) && !isset($out[$key]['theme'])) $out[$key]['theme'] = $out[$key]['kit'];   // 'kit' = Alias von 'theme'
        }
        return self::$cache = $out;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    public static function multi(): bool
    {
        return count(self::all()) > 1;
    }

    public static function validKey(string $key): bool
    {
        return (bool) preg_match('~^[a-z][a-z0-9-]{1,31}$~', $key);
    }

    /**
     * Website zur Domain. Reihenfolge: exakter Host (mit Port) → Host ohne Port → Rückfall.
     * Rückfall = 'fallback_site' aus config/config.local.php (Standard: default). null = Domain abweisen.
     */
    public static function resolve(string $host, ?string $fallback = Site::DEFAULT): ?string
    {
        $host = strtolower(trim($host));
        $bare = preg_replace('~:\d+$~', '', $host);
        foreach ([$host, $bare] as $h) {
            foreach (self::all() as $key => $cfg) {
                // 'landing_hosts': weitere Domains für Landingpages (Core\Landings) – gehören zur Website, sind aber nicht ihre Hauptadresse
                if (in_array($h, array_map('strtolower', array_merge((array) ($cfg['hosts'] ?? []), (array) ($cfg['landing_hosts'] ?? []))), true)) {
                    return $key;
                }
            }
        }
        return $fallback !== null && isset(self::all()[$fallback]) ? $fallback : null;
    }

    /** Website von Kommandozeile/Umgebung: --site=key oder CMS_SITE=key */
    public static function fromCli(array &$argv): string
    {
        $key = (string) (getenv('CMS_SITE') ?: '');
        foreach ($argv as $i => $a) {
            if (str_starts_with((string) $a, '--site=')) {
                $key = substr($a, 7);
                unset($argv[$i]);
            }
        }
        $argv = array_values($argv);
        return $key !== '' ? $key : Site::DEFAULT;
    }

    /** Domain prüfen und normalisieren (klein, ohne Schema/Pfad, optional :port) – null = ungültig */
    public static function normalizeHost(string $host): ?string
    {
        $h = strtolower(trim($host));
        $h = (string) preg_replace('~^[a-z]+://~', '', $h);
        $h = rtrim($h, '/.');
        if ($h === '' || strlen($h) > 253 || !preg_match('~^(?=.{1,253}(:\d+)?$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*(:\d{1,5})?$~', $h)) return null;
        return $h;
    }

    /**
     * Domain einer Website hinzufügen oder entfernen (site:hosts, Netzwerk-Übersicht).
     * $landing = true: als Landing-Domain ('landing_hosts', Core\Landings) – die Hauptadresse der Website ('hosts'[0]) bleibt.
     * Ändert in config/sites/{key}.php nur diesen Eintrag (Kommentare und übrige Werte bleiben unverändert),
     * prüft die neue Datei vor dem Ersetzen und legt eine Sicherung {key}.php.bak an. Website „default“ ohne Datei: wird angelegt.
     * Entfernen sucht die Domain in beiden Listen.
     * @return list<string> neue Domains der geänderten Liste
     */
    public static function setHosts(string $key, string $action, string $host, bool $landing = false): array
    {
        if (!isset(self::all()[$key])) throw new \InvalidArgumentException("Unbekannte Website „{$key}“.");
        $h = self::normalizeHost($host) ?? throw new \InvalidArgumentException("Ungültige Domain: {$host}");
        $cfg = self::all()[$key];
        $list = fn(string $k) => array_values(array_map('strtolower', (array) ($cfg[$k] ?? [])));
        if ($action === 'add') {
            $other = self::resolve($h, null);
            if ($other !== null && $other !== $key) throw new \InvalidArgumentException("Domain {$h} gehört schon zur Website „{$other}“.");
            $field = $landing ? 'landing_hosts' : 'hosts';
            $hosts = $list($field);
            if (in_array($h, array_merge($list('hosts'), $list('landing_hosts')), true)) return $hosts;
            $hosts[] = $h;
        } elseif ($action === 'remove') {
            $field = in_array($h, $list('landing_hosts'), true) ? 'landing_hosts' : 'hosts';
            $hosts = $list($field);
            if (!in_array($h, $hosts, true)) throw new \InvalidArgumentException("Domain {$h} ist für „{$key}“ nicht eingetragen.");
            $hosts = array_values(array_filter($hosts, fn($x) => $x !== $h));
            if ($field === 'hosts' && !$hosts && $key !== Site::DEFAULT) throw new \InvalidArgumentException('Die letzte Domain einer Website kann nicht entfernt werden.');
        } else {
            throw new \InvalidArgumentException('Aktion: add oder remove.');
        }
        $file = self::dir() . "/$key.php";
        $export = '[' . implode(', ', array_map(fn($x) => var_export($x, true), $hosts)) . ']';
        if (is_file($file)) {
            $src = (string) file_get_contents($file);
            $new = self::replaceTopLevel($src, $field, $export) ?? throw new \RuntimeException("config/sites/{$key}.php hat kein erkennbares return-Array – bitte '{$field}' von Hand ändern.");
        } else {
            $new = "<?php\n// Website „{$key}“ – Domains (angelegt von site:hosts am " . date('Y-m-d H:i') . ")\nreturn [\n    '{$field}' => {$export},\n];\n";
        }
        if (!is_dir(self::dir())) mkdir(self::dir(), 0770, true);
        // Neue Datei zuerst prüfen (Syntax + Ergebnis), dann atomar ersetzen
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $new, LOCK_EX);
        @chmod($tmp, 0640);
        try {
            $check = (static fn(string $__f) => require $__f)($tmp);
        } catch (\Throwable $e) {
            @unlink($tmp);
            throw new \RuntimeException('Geänderte Konfiguration ist ungültig (' . $e->getMessage() . ') – nichts geändert.');
        }
        if (!is_array($check) || array_values(array_map('strtolower', (array) ($check[$field] ?? []))) !== $hosts) {
            @unlink($tmp);
            throw new \RuntimeException('Geänderte Konfiguration liefert andere Domains als erwartet – nichts geändert.');
        }
        if (is_file($file)) {
            @copy($file, $file . '.bak');
            @chmod($file . '.bak', 0640);
        }
        rename($tmp, $file);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
        self::flush();
        return $hosts;
    }

    /**
     * Wert eines Schlüssels im obersten return-Array einer PHP-Datei ersetzen bzw. ergänzen (Tokenizer, keine Auswertung).
     * @return ?string neuer Quelltext oder null, wenn kein return-Array gefunden wurde
     */
    public static function replaceTopLevel(string $src, string $key, string $phpValue): ?string
    {
        $tokens = token_get_all($src);
        $pos = 0;          // Byte-Position je Token
        $offsets = [];
        foreach ($tokens as $i => $t) {
            $offsets[$i] = $pos;
            $pos += strlen(is_array($t) ? $t[1] : $t);
        }
        $n = count($tokens);
        $text = fn($t) => is_array($t) ? $t[1] : $t;
        $sig = fn($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
        // return [ … ] bzw. return array ( … )
        $open = null;
        for ($i = 0; $i < $n; $i++) {
            if (is_array($tokens[$i]) && $tokens[$i][0] === T_RETURN) {
                for ($j = $i + 1; $j < $n && !$sig($tokens[$j]); $j++);
                if ($j < $n && $text($tokens[$j]) === '[') { $open = $j; break; }
                if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_ARRAY) {
                    for ($k = $j + 1; $k < $n && !$sig($tokens[$k]); $k++);
                    if ($k < $n && $text($tokens[$k]) === '(') { $open = $k; break; }
                }
            }
        }
        if ($open === null) return null;
        $depth = 0;
        $close = null;
        $valStart = $valEnd = null;
        for ($i = $open; $i < $n; $i++) {
            $tx = $text($tokens[$i]);
            if (in_array($tx, ['[', '(', '{'], true) || (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; continue; }
            if (in_array($tx, [']', ')', '}'], true)) {
                $depth--;
                if ($depth === 0) { $close = $i; if ($valStart !== null && $valEnd === null) $valEnd = $i; break; }
                continue;
            }
            if ($depth !== 1) continue;
            if ($valStart !== null && $valEnd === null && $tx === ',') { $valEnd = $i; continue; }
            if ($valStart === null && is_array($tokens[$i]) && $tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING && trim($tx, '\'"') === $key) {
                for ($j = $i + 1; $j < $n && !$sig($tokens[$j]); $j++);
                if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_DOUBLE_ARROW) $valStart = $j + 1;
            }
        }
        if ($close === null) return null;
        if ($valStart !== null && $valEnd !== null) {
            // Leerraum/Kommentare am Ende des Werts stehen lassen
            $end = $valEnd;
            while ($end - 1 > $valStart && !$sig($tokens[$end - 1])) $end--;
            return substr($src, 0, $offsets[$valStart]) . ' ' . $phpValue . substr($src, $offsets[$end]);
        }
        // Schlüssel fehlt: direkt nach der öffnenden Klammer einfügen
        $at = $offsets[$open] + 1;
        return substr($src, 0, $at) . "\n  " . var_export($key, true) . ' => ' . $phpValue . ',' . substr($src, $at);
    }

    /**
     * Schlüssel samt Wert aus dem obersten return-Array einer PHP-Datei entfernen (Tokenizer, keine Auswertung).
     * Ein Kommentar am Zeilenende des Eintrags und die dann leere Zeile verschwinden mit; $leadingComment = true entfernt auch
     * eine unmittelbar darüberstehende Kommentarzeile (// …). Fehlt der Schlüssel: Quelltext unverändert.
     * @return ?string neuer Quelltext oder null, wenn kein return-Array gefunden wurde
     */
    public static function removeTopLevel(string $src, string $key, bool $leadingComment = false): ?string
    {
        $tokens = token_get_all($src);
        $offsets = [];
        $pos = 0;
        foreach ($tokens as $i => $t) {
            $offsets[$i] = $pos;
            $pos += strlen(is_array($t) ? $t[1] : $t);
        }
        $n = count($tokens);
        $text = fn($t) => is_array($t) ? $t[1] : $t;
        $sig = fn($t) => !is_array($t) || !in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
        $open = null;
        for ($i = 0; $i < $n && $open === null; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_RETURN) continue;
            for ($j = $i + 1; $j < $n && !$sig($tokens[$j]); $j++);
            if ($j < $n && $text($tokens[$j]) === '[') $open = $j;
            elseif ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_ARRAY) {
                for ($k = $j + 1; $k < $n && !$sig($tokens[$k]); $k++);
                if ($k < $n && $text($tokens[$k]) === '(') $open = $k;
            }
        }
        if ($open === null) return null;
        $depth = 0;
        $keyIdx = $valEnd = null;
        for ($i = $open; $i < $n; $i++) {
            $tx = $text($tokens[$i]);
            if (in_array($tx, ['[', '(', '{'], true) || (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) { $depth++; continue; }
            if (in_array($tx, [']', ')', '}'], true)) {
                $depth--;
                if ($depth === 0) { if ($keyIdx !== null && $valEnd === null) $valEnd = $i; break; }
                continue;
            }
            if ($depth !== 1) continue;
            if ($keyIdx !== null && $valEnd === null && $tx === ',') { $valEnd = $i; break; }
            if ($keyIdx === null && is_array($tokens[$i]) && $tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING && trim($tx, '\'"') === $key) {
                for ($j = $i + 1; $j < $n && !$sig($tokens[$j]); $j++);
                if ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_DOUBLE_ARROW) $keyIdx = $i;
            }
        }
        if ($keyIdx === null) return $src;
        if ($valEnd === null) return null;
        $start = $offsets[$keyIdx];
        if ($text($tokens[$valEnd]) === ',') {
            $end = $offsets[$valEnd] + 1;
        } else {
            // letzter Eintrag ohne Komma: bis vor die schließende Klammer (Leerraum davor bleibt)
            $end = $offsets[$valEnd];
            while ($end > $start && ctype_space($src[$end - 1])) $end--;
        }
        // Kommentar am Zeilenende mitnehmen, dann die leere Zeile
        if (preg_match('~\G[ \t]*(//[^\n]*|#[^\n]*)?~', $src, $m, 0, $end)) $end += strlen($m[0]);
        $lineStart = strrpos(substr($src, 0, $start), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        if (trim(substr($src, $lineStart, $start - $lineStart)) === '' && ($src[$end] ?? '') === "\n") {
            $start = $lineStart;
            $end++;
            if ($leadingComment && $start > 0) {
                $prev = strrpos(substr($src, 0, $start - 1), "\n");
                $prev = $prev === false ? 0 : $prev + 1;
                if (preg_match('~^[ \t]*//[^\n]*\n$~', substr($src, $prev, $start - $prev))) $start = $prev;
            }
        }
        return substr($src, 0, $start) . substr($src, $end);
    }

    /**
     * Neue Website anlegen: schreibt config/sites/{key}.php mit eigenen Schlüsseln.
     * Datenbank, Medienordner und Startinhalte (aus dem Theme) entstehen beim ersten Aufruf.
     * @return array{file: string, setup_token: string}
     */
    public static function create(string $key, array $hosts, string $theme = '', array $extra = []): array
    {
        if (!self::validKey($key) || $key === Site::DEFAULT) {
            throw new \InvalidArgumentException('Kurzname: 2–32 Zeichen, a–z, 0–9, Bindestrich; nicht „default“.');
        }
        if (isset(self::all()[$key])) {
            throw new \InvalidArgumentException("Website „{$key}“ gibt es schon.");
        }
        $hosts = array_values(array_filter(array_map(fn($h) => strtolower(trim($h)), $hosts)));
        foreach ($hosts as $h) {
            if (!preg_match('~^[a-z0-9.-]+(:\d+)?$~', $h)) throw new \InvalidArgumentException("Ungültige Domain: {$h}");
            $other = self::resolve($h, null);
            if ($other !== null) throw new \InvalidArgumentException("Domain {$h} gehört schon zur Website „{$other}“.");
        }
        if (!$hosts) throw new \InvalidArgumentException('Mindestens eine Domain angeben.');
        if ($theme !== '' && !isset(Theme::available()[$theme])) {
            throw new \InvalidArgumentException("Kit „{$theme}“ ist nicht installiert.");
        }
        $cfg = array_replace([
            'label' => $hosts[0],
            'hosts' => $hosts,
            'theme' => $theme,
            'app_key' => bin2hex(random_bytes(32)),
            'setup_token' => bin2hex(random_bytes(12)),
        ], $extra);
        if (!is_dir(self::dir())) {
            mkdir(self::dir(), 0770, true);
        }
        $file = self::dir() . "/$key.php";
        $php = "<?php\n// Website „{$key}“ – NICHT versionieren (enthält Schlüssel). Angelegt am " . date('Y-m-d H:i') . "\n"
            . "// Weitere Werte aus config/config.php können hier je Website überschrieben werden.\nreturn " . var_export($cfg, true) . ";\n";
        file_put_contents($file, $php, LOCK_EX);
        @chmod($file, 0640);
        self::flush();
        return ['file' => $file, 'setup_token' => $cfg['setup_token']];
    }
}
