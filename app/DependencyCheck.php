<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Optionale Abhängigkeiten statisch prüfen (php bin/console extensions:check) – für Erweiterungen und Kits, ohne sie zu laden.
 *
 * Hintergrund: Erbt eine Klasse von einer Klasse (bzw. implementiert ein Interface, nutzt einen Trait) aus einem Paket, das nicht
 * sicher installiert ist, bricht PHP beim Laden der Datei mit „Class … not found“ ab – und mit dem Start der Erweiterung die ganze
 * Website. Sicher installiert ist, was in der composer.json des Pakets unter "require" steht (samt deren Abhängigkeiten),
 * was der Kern selbst verlangt, PHP-eigene Klassen, Core\… und die Klassen des Pakets selbst. Alles andere – nur "suggest",
 * "require-dev" oder gar nicht angegeben – ist optional und braucht eine Absicherung:
 *
 *   Klassen-Datei:  if (!class_exists(\Anbieter\Paket\Basis::class)) return;   vor der Deklaration
 *   Manifest/Kit:   if (class_exists(\Anbieter\Paket\Basis::class)) { $x->…(new Eigene\Klasse()); }
 *
 * Geprüft wird je Paket:
 *  - alle PHP-Dateien (ohne vendor/, node_modules/, tests/, tools/): Deklarationen mit extends/implements/use (Trait) einer
 *    optionalen Klasse ohne class_exists()/interface_exists() davor → Fehler;
 *  - Einstiegsdateien (extension.php, theme.php/kit.php, console.php, functions.php): new X, X::methode(), X::KONSTANTE einer
 *    optionalen Klasse – oder einer eigenen Klasse, deren Datei sich selbst absichert (bedingt deklariert) – ohne passende
 *    class_exists()-Prüfung in derselben Datei → Fehler. X::class und instanceof laden nichts und bleiben erlaubt.
 * Ergebnis: Liste ['file' => relativ, 'line' => n, 'level' => 'error'|'warn', 'text' => …, 'fix' => …].
 */
final class DependencyCheck
{
    /** Ordner, die nicht zum Laufzeit-Code gehören */
    private const SKIP_DIRS = ['vendor', 'node_modules', 'tests', 'test', 'tools', '.git', 'public', 'assets'];
    /** Einstiegsdateien, die beim Start ausgeführt werden */
    private const ENTRY = ['extension.php', 'theme.php', 'kit.php', 'console.php', 'functions.php'];
    /** Aufrufe, die eine Absicherung darstellen */
    private const GUARDS = ['class_exists', 'interface_exists', 'trait_exists', 'enum_exists'];

    /** @var array<string, array{ns: list<string>, require: list<string>}>|null Paketname => Namensräume, Abhängigkeiten */
    private static ?array $packages = null;

    /**
     * Alle installierten Erweiterungen und Kits prüfen.
     * @return array<string, array{kind: string, dir: string, findings: list<array>}> Schlüssel „extension:name“ bzw. „kit:name“
     */
    public static function all(): array
    {
        $out = [];
        foreach (self::extensionDirs() as $n => $dir) $out['extension:' . $n] = ['kind' => 'extension', 'dir' => $dir, 'findings' => self::run($dir)];
        foreach (array_keys(Kit::all()) as $k) {
            $dir = Kit::dir($k);
            $out['kit:' . $k] = ['kind' => 'kit', 'dir' => $dir, 'findings' => self::run($dir)];
        }
        return $out;
    }

    /**
     * Ordner der installierten Erweiterungen, ohne ihre Manifeste auszuführen (wie Extensions::available: extensions/{name}/ und
     * Composer-Pakete vom Typ klxm-studio-extension) – die Prüfung läuft so auch, wenn eine Erweiterung beim Laden scheitert.
     * @return array<string, string> Name (Ordner bzw. Paket) => Ordner
     */
    public static function extensionDirs(): array
    {
        $out = [];
        foreach (glob(ROOT . '/extensions/*/extension.php') ?: [] as $f) $out[basename(dirname($f))] = dirname($f);
        $installed = ROOT . '/vendor/composer/installed.json';
        if (is_file($installed)) {
            $data = (array) json_decode((string) file_get_contents($installed), true);
            foreach ((array) ($data['packages'] ?? $data) as $p) {
                if (!is_array($p) || ($p['type'] ?? '') !== 'klxm-studio-extension') continue;
                $dir = realpath(ROOT . '/vendor/composer/' . ($p['install-path'] ?? ('../' . ($p['name'] ?? ''))));
                if ($dir) $out[(string) $p['name']] = $dir;
            }
        }
        ksort($out);
        return $out;
    }

    /**
     * Einen Ordner (Erweiterung oder Kit) prüfen. $extra: weitere sicher vorhandene Pakete (z. B. Selbsttest).
     * @return list<array{file: string, line: int, level: string, text: string, fix: string}>
     */
    public static function run(string $dir, array $extra = []): array
    {
        $dir = rtrim($dir, '/');
        $composer = is_file("$dir/composer.json") ? (array) json_decode((string) file_get_contents("$dir/composer.json"), true) : [];
        $packages = self::packages() + self::localPackages($dir);
        // Sicher vorhanden: require des Pakets + require des Kerns, jeweils mit deren Abhängigkeiten
        $core = is_file(ROOT . '/composer.json') ? (array) (json_decode((string) file_get_contents(ROOT . '/composer.json'), true)['require'] ?? []) : [];
        $sure = self::closure(array_merge(array_keys((array) ($composer['require'] ?? [])), array_keys($core), $extra), $packages);
        $sureNs = ['Core\\'];
        foreach ($sure as $p) foreach ($packages[$p]['ns'] ?? [] as $ns) $sureNs[] = $ns;
        foreach (array_keys((array) ($composer['autoload']['psr-4'] ?? [])) as $ns) $sureNs[] = (string) $ns;
        $suggest = array_keys((array) ($composer['suggest'] ?? []) + (array) ($composer['require-dev'] ?? []));
        // Pakete aus require, die nirgends installiert sind: ihre Namensräume kennen wir nicht → unbekannte Klassen nur als Hinweis
        $unresolved = array_values(array_filter(array_keys((array) ($composer['require'] ?? [])), fn($p) => str_contains($p, '/') && !isset($packages[$p])));

        // 1. Durchgang: alle Dateien lesen, Deklarationen sammeln
        $files = [];
        foreach (self::files($dir) as $f) $files[$f] = self::scan((string) file_get_contents($f));
        $own = [];          // eigene Klassen (FQN, klein) => bedingt deklariert?
        $ownNs = [];
        foreach ($files as $info) {
            foreach ($info['decls'] as $d) $own[strtolower($d['name'])] = $d['guarded'];
            foreach ($info['namespaces'] as $ns) if ($ns !== '') $ownNs[] = $ns . '\\';
        }
        $classify = function (string $fqn) use ($own, $ownNs, $sureNs, $packages, $suggest, $unresolved): ?array {
            $l = strtolower($fqn);
            if (isset($own[$l])) return null;
            if (self::internal($fqn)) return null;
            foreach ($sureNs as $ns) if (str_starts_with($l, strtolower($ns))) return null;
            foreach ($ownNs as $ns) if (str_starts_with($l, strtolower($ns))) return null;
            foreach ($packages as $name => $p) {
                foreach ($p['ns'] as $ns) if ($ns !== '' && str_starts_with($l, strtolower($ns))) {
                    return ['error', in_array($name, $suggest, true) ? "aus dem optionalen Paket {$name} – nur unter suggest bzw. require-dev" : "aus dem Paket {$name}, das nicht unter require steht"];
                }
            }
            if ($unresolved) return ['warn', 'Herkunft unbekannt – nicht installiertes Paket aus require? (' . implode(', ', $unresolved) . ')'];
            return ['error', 'aus einem nicht installierten Paket, das nicht unter require steht' . ($suggest ? ' (suggest: ' . implode(', ', $suggest) . ')' : '')];
        };

        $out = [];
        $rel = fn(string $f): string => str_starts_with($f, ROOT . '/') ? substr($f, strlen(ROOT) + 1) : substr($f, strlen($dir) + 1);
        foreach ($files as $f => $info) {
            if (isset($info['parse'])) {
                $out[] = ['file' => $rel($f), 'line' => (int) $info['parse_line'], 'level' => 'error', 'text' => 'Syntaxfehler: ' . $info['parse'],
                    'fix' => 'Datei mit php -l prüfen und korrigieren.'];
                continue;
            }
            // Deklarationen: Eltern, Interfaces, Traits
            foreach ($info['decls'] as $d) {
                if ($d['guarded']) continue;
                foreach ($d['parents'] as [$p, $line]) {
                    if (!($c = $classify($p))) continue;
                    $out[] = ['file' => $rel($f), 'line' => $line, 'level' => $c[0],
                        'text' => "{$d['kind']} {$d['name']} erbt von bzw. nutzt {$p} {$c[1]}. Ohne Absicherung bricht das Laden der Datei mit „Class not found“ ab.",
                        'fix' => "Vor der Deklaration: if (!class_exists(\\{$p}::class)) return; – beim Verwenden class_exists(\\{$d['name']}::class) prüfen; oder das Paket unter \"require\" aufnehmen."];
                }
            }
            // Einstiegsdateien: Zugriffe, die laden
            if (!in_array(basename($f), self::ENTRY, true)) continue;
            foreach ($info['uses'] as [$name, $line, $inGuard]) {
                if ($inGuard) continue;   // im Block bzw. in der Anweisung einer class_exists()-Prüfung
                $l = strtolower($name);
                $c = isset($own[$l]) ? ($own[$l] ? ['error', 'eigene Klasse, die nur bedingt deklariert wird – ihre Datei sichert sich mit class_exists ab'] : null) : $classify($name);
                if (!$c || self::guardedUse($name, $info['guards'])) continue;
                $out[] = ['file' => $rel($f), 'line' => $line, 'level' => $c[0],
                    'text' => "Zugriff auf {$name} ohne class_exists()-Prüfung: {$c[1]}. Fehlt das Paket, scheitert der Start.",
                    'fix' => "if (class_exists(\\{$name}::class)) { … } um den Zugriff legen – oder das Paket unter \"require\" aufnehmen."];
            }
        }
        usort($out, fn($a, $b) => [$a['file'], $a['line']] <=> [$b['file'], $b['line']]);
        return $out;
    }

    /** Prüft die Datei genau diese Klasse irgendwo mit class_exists()? (z. B. $ok = class_exists(X::class); … if ($ok) new X) */
    private static function guardedUse(string $name, array $guards): bool
    {
        return in_array(strtolower($name), array_map('strtolower', $guards), true);
    }

    /** PHP-eigene Klasse/Interface (ohne Autoload)? */
    private static function internal(string $fqn): bool
    {
        if (!class_exists($fqn, false) && !interface_exists($fqn, false) && !trait_exists($fqn, false) && !enum_exists($fqn, false)) return false;
        return (new \ReflectionClass($fqn))->isInternal();
    }

    /** @return list<string> PHP-Dateien des Pakets ohne vendor/, tests/ … */
    private static function files(string $dir): array
    {
        if (!is_dir($dir)) return [];
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            fn(\SplFileInfo $f) => !$f->isDir() || !in_array($f->getFilename(), self::SKIP_DIRS, true)));
        foreach ($it as $f) if ($f->getExtension() === 'php') $out[] = $f->getPathname();
        sort($out);
        return $out;
    }

    /**
     * Tokens einer Datei auswerten: Deklarationen (Name, Art, Eltern mit Zeile, abgesichert?), Zugriffe (new X, X::…),
     * Argumente der class_exists()-Aufrufe, Namensräume.
     * @return array{decls: list<array>, uses: list<array{string, int, bool}>, guards: list<string>, namespaces: list<string>}
     */
    public static function scan(string $code): array
    {
        try {
            $t = token_get_all($code, TOKEN_PARSE);
        } catch (\ParseError $e) {
            return ['decls' => [], 'uses' => [], 'guards' => [], 'namespaces' => [], 'parse' => $e->getMessage(), 'parse_line' => $e->getLine()];
        }
        $t = array_values(array_filter($t, fn($x) => !is_array($x) || !in_array($x[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)));
        $n = count($t);
        $ns = '';
        $imports = [];
        $guarded = false;
        $depth = 0;
        $out = ['decls' => [], 'uses' => [], 'guards' => [], 'namespaces' => []];
        $nameTok = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
        $resolve = function (string $name) use (&$ns, &$imports): string {
            return self::resolveName($name, $ns, $imports);
        };
        $special = ['self', 'static', 'parent'];
        // Abgesicherte Bereiche für Zugriffe: Block eines if (class_exists(…)) { … }, dieselbe Anweisung (class_exists(X) ? new X : …,
        // if (…) ohne Klammern) und alles nach einem frühen Ausstieg auf oberster Ebene (if (!class_exists(X)) return;)
        $guardDepths = [];
        $pendingGuard = false;
        $stmtGuard = false;
        $topGuard = false;
        $isGuard = fn(int $k): bool => is_array($t[$k]) && $t[$k][0] === T_STRING && in_array(strtolower($t[$k][1]), self::GUARDS, true) && ($t[$k + 1] ?? null) === '(';
        for ($i = 0; $i < $n; $i++) {
            $x = $t[$i];
            if (!is_array($x)) {
                if ($x === '{') {
                    $depth++;
                    if ($pendingGuard) $guardDepths[] = $depth;
                    $pendingGuard = $stmtGuard = false;
                } elseif ($x === '}') {
                    if ($guardDepths && end($guardDepths) === $depth) array_pop($guardDepths);
                    $depth--;
                    $stmtGuard = false;
                } elseif ($x === ';') {
                    $stmtGuard = $pendingGuard = false;
                }
                continue;
            }
            if ($x[0] === T_CURLY_OPEN || $x[0] === T_DOLLAR_OPEN_CURLY_BRACES) { $depth++; continue; }
            if ($x[0] === T_IF || $x[0] === T_ELSEIF) {
                // Bedingung bis zur passenden Klammer: enthält sie class_exists()?
                $p = 0;
                $hit = false;
                for ($k = $i + 1; $k < $n; $k++) {
                    if ($t[$k] === '(') $p++;
                    elseif ($t[$k] === ')' && --$p === 0) break;
                    elseif ($isGuard($k)) $hit = true;
                }
                if ($hit) {
                    $next = $t[$k + 1] ?? null;
                    if ($next === '{') $pendingGuard = true; else $stmtGuard = true;
                    $exit = fn($y) => is_array($y) && in_array($y[0], [T_RETURN, T_EXIT, T_THROW], true);
                    if ($depth === 0 && ($exit($next) || ($next === '{' && $exit($t[$k + 2] ?? null)))) $topGuard = true;
                }
                continue;
            }
            if ($x[0] === T_NAMESPACE && is_array($t[$i + 1] ?? null) && in_array($t[$i + 1][0], [T_STRING, T_NAME_QUALIFIED], true)) {
                $ns = $t[$i + 1][1];
                $imports = [];
                $out['namespaces'][] = $ns;
                continue;
            }
            // use-Importe (nur oben in der Datei, nicht Trait-use in Klassen und nicht Closure-use)
            if ($x[0] === T_USE && $depth === 0 && !in_array($t[$i - 1] ?? null, [')'], true)) {
                $j = $i + 1;
                if (is_array($t[$j] ?? null) && in_array(strtolower($t[$j][1]), ['function', 'const'], true)) continue;
                $prefix = '';
                $name = '';
                for (; $j < $n && $t[$j] !== ';'; $j++) {
                    $y = $t[$j];
                    if (is_array($y) && in_array($y[0], $nameTok, true)) {
                        $name = ltrim($y[1], '\\');
                        $alias = substr($name, (int) strrpos('\\' . $name, '\\'));
                        if (is_array($t[$j + 1] ?? null) && $t[$j + 1][0] === T_AS) { $alias = $t[$j + 2][1]; $j += 2; }
                        if (($t[$j + 1] ?? null) === '{' || (is_array($t[$j + 1] ?? null) && $t[$j + 1][0] === T_NS_SEPARATOR)) { $prefix = rtrim($name, '\\') . '\\'; continue; }
                        $full = $prefix . $name;
                        $imports[strtolower(substr($alias, (int) strrpos('\\' . $alias, '\\')))] = $full;
                    } elseif ($y === '}') {
                        $prefix = '';
                    }
                }
                $i = $j;
                continue;
            }
            // Absicherung: class_exists(...) – Argument merken
            if ($x[0] === T_STRING && in_array(strtolower($x[1]), self::GUARDS, true) && ($t[$i + 1] ?? null) === '(' && !self::isMember($t, $i)) {
                $guarded = true;
                $stmtGuard = true;   // class_exists(X) && X::…, class_exists(X) ? new X : …
                $y = $t[$i + 2] ?? null;
                if (is_array($y) && in_array($y[0], $nameTok, true) && is_array($t[$i + 3] ?? null) && $t[$i + 3][0] === T_DOUBLE_COLON) {
                    $out['guards'][] = $resolve($y[1]);
                } elseif (is_array($y) && $y[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $out['guards'][] = ltrim(stripslashes(trim($y[1], '\'"')), '\\');
                }
                continue;
            }
            // Deklarationen (nicht anonyme Klassen, nicht X::class)
            if (in_array($x[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $prev = $t[$i - 1] ?? null;
                if (is_array($prev) && in_array($prev[0], [T_DOUBLE_COLON, T_NEW], true)) continue;
                if (is_array($prev) && in_array($prev[0], [T_READONLY, T_FINAL, T_ABSTRACT], true) && is_array($t[$i - 2] ?? null) && $t[$i - 2][0] === T_NEW) continue;
                $nm = $t[$i + 1] ?? null;
                if (!is_array($nm) || $nm[0] !== T_STRING) continue;
                $kind = match ($x[0]) { T_INTERFACE => 'Interface', T_TRAIT => 'Trait', T_ENUM => 'Enum', default => 'Klasse' };
                $parents = [];
                $j = $i + 2;
                for (; $j < $n && $t[$j] !== '{'; $j++) {
                    $y = $t[$j];
                    if (is_array($y) && in_array($y[0], $nameTok, true) && !in_array(strtolower($y[1]), ['string', 'int'], true)) $parents[] = [$resolve($y[1]), $y[2]];
                }
                // Traits im Klassenrumpf (use X;) – nur direkte Ebene
                $d = 0;
                for ($k = $j; $k < $n; $k++) {
                    if ($t[$k] === '{') { $d++; continue; }
                    if ($t[$k] === '}') { if (--$d === 0) break; continue; }
                    if (is_array($t[$k]) && in_array($t[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) { $d++; continue; }
                    if ($d === 1 && is_array($t[$k]) && $t[$k][0] === T_USE) {
                        for ($m = $k + 1; $m < $n && !in_array($t[$m], [';', '{'], true); $m++) {
                            if (is_array($t[$m]) && in_array($t[$m][0], $nameTok, true)) $parents[] = [$resolve($t[$m][1]), $t[$m][2]];
                        }
                    }
                }
                $out['decls'][] = ['name' => ltrim($ns . '\\' . $nm[1], '\\'), 'kind' => $kind, 'parents' => $parents, 'guarded' => $guarded, 'line' => $x[2]];
                continue;
            }
            // Zugriffe, die den Autoloader auslösen: new X, X::methode()/KONSTANTE/$statisch (nicht X::class)
            if ($x[0] === T_NEW && is_array($t[$i + 1] ?? null) && in_array($t[$i + 1][0], $nameTok, true) && !in_array(strtolower($t[$i + 1][1]), $special, true)) {
                $out['uses'][] = [$resolve($t[$i + 1][1]), $t[$i + 1][2], $topGuard || $stmtGuard || (bool) $guardDepths];
                continue;
            }
            if (in_array($x[0], $nameTok, true) && is_array($t[$i + 1] ?? null) && $t[$i + 1][0] === T_DOUBLE_COLON
                && !in_array(strtolower($x[1]), $special, true) && !(is_array($t[$i + 2] ?? null) && strtolower($t[$i + 2][1]) === 'class')) {
                $out['uses'][] = [$resolve($x[1]), $x[2], $topGuard || $stmtGuard || (bool) $guardDepths];
            }
        }
        return $out;
    }

    /** Klassennamen wie PHP auflösen: vollqualifiziert, namespace\…, über use-Importe (Alias klein geschrieben), sonst im Namensraum */
    private static function resolveName(string $name, string $ns, array $imports): string
    {
        if ($name[0] === '\\') return ltrim($name, '\\');
        if (str_starts_with(strtolower($name), 'namespace\\')) return ltrim($ns . '\\' . substr($name, 10), '\\');
        $first = strtolower(explode('\\', $name)[0]);
        if (isset($imports[$first])) return $imports[$first] . substr($name, strlen($first));
        return ltrim($ns . '\\' . $name, '\\');
    }

    /** Steht der Name hinter -> / ?-> / :: (Methode statt Funktion)? */
    private static function isMember(array $t, int $i): bool
    {
        $p = $t[$i - 1] ?? null;
        return is_array($p) && in_array($p[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true);
    }

    /** Abhängigkeiten transitiv auflösen. @return list<string> */
    private static function closure(array $names, array $packages): array
    {
        $seen = [];
        $todo = array_values(array_filter($names, fn($p) => str_contains((string) $p, '/')));
        while ($todo) {
            $p = strtolower((string) array_pop($todo));
            if (isset($seen[$p])) continue;
            $seen[$p] = true;
            foreach ($packages[$p]['require'] ?? [] as $r) if (!isset($seen[$r])) $todo[] = $r;
        }
        return array_keys($seen);
    }

    /** Installierte Composer-Pakete des Kerns: Name => Namensräume (psr-4/psr-0) + require */
    private static function packages(): array
    {
        return self::$packages ??= self::readInstalled(ROOT . '/vendor/composer/installed.json') + self::localPackages(ROOT . '/extensions', true);
    }

    /** Pakete aus vendor/ einer Erweiterung bzw. mitgelieferte Erweiterungen/Kits (composer.json im Ordner) */
    private static function localPackages(string $dir, bool $children = false): array
    {
        if (!$children) return self::readInstalled("$dir/vendor/composer/installed.json");
        $out = [];
        foreach (array_merge(glob("$dir/*/composer.json") ?: [], glob(ROOT . '/kits/*/composer.json') ?: []) as $f) {
            $c = (array) json_decode((string) file_get_contents($f), true);
            if (empty($c['name'])) continue;
            $out[strtolower((string) $c['name'])] = ['ns' => array_map('strval', array_keys((array) ($c['autoload']['psr-4'] ?? []))),
                'require' => array_map('strtolower', array_keys((array) ($c['require'] ?? [])))];
        }
        return $out;
    }

    private static function readInstalled(string $file): array
    {
        if (!is_file($file)) return [];
        $data = (array) json_decode((string) file_get_contents($file), true);
        $out = [];
        foreach ((array) ($data['packages'] ?? $data) as $p) {
            if (!is_array($p) || empty($p['name'])) continue;
            $ns = array_merge(array_keys((array) ($p['autoload']['psr-4'] ?? [])), array_keys((array) ($p['autoload']['psr-0'] ?? [])));
            $out[strtolower((string) $p['name'])] = ['ns' => array_values(array_filter(array_map('strval', $ns))), 'require' => array_map('strtolower', array_keys((array) ($p['require'] ?? [])))];
        }
        return $out;
    }
}
