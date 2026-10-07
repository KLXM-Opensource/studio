<?php
declare(strict_types=1);

namespace Core;

/**
 * Zentrale Pfad-API für Kits – die einzige Stelle, die weiß, wo Kits liegen.
 *
 *   kits/{name}/                 Kit im Code-Stand (mitgeliefert bzw. lokal entwickelt): theme.php bzw. kit.php, Templates, Blöcke …
 *   storage/kits/{name}/         hochgeladenes Kit-Paket (ZIP über die Verwaltung bzw. kit:install – Core\KitPackages);
 *                                liegt in storage/, damit es Deploys (neue Releases) übersteht
 *   vendor/{hersteller}/{paket}/ Composer-Paket vom Typ „klxm-studio-kit“ (vendor/composer/installed.json)
 *   public/assets/kits/{name}/   öffentliche Assets – lokal gebaut von tools/build.mjs aus kits/{name}/assets, bei Paketen
 *                                aus deren fertig gebautem Ordner public/ kopiert (kits:publish) – Ablage: Core\PublicPaths
 *
 * Gleicher Name an mehreren Orten: kits/ vor storage/kits/ vor Composer; verdeckte Kits meldet shadowed() (kit:list, health).
 *
 * Technische Namen bleiben aus Kompatibilitätsgründen: Klasse Core\Theme, Datei theme.php (kit.php geht ebenso),
 * Konfiguration 'theme' (Alias 'kit'), app()->theme (Alias app()->kit), Einstellung sys.theme.
 *
 *   Kit::dir('praxis')            → /…/kits/praxis · Kit::dir() → /…/kits
 *   Kit::publicDir('praxis')      → /…/public/assets/kits/praxis
 *   Kit::url('praxis', 'css/x')   → /assets/kits/praxis/css/x (ohne Versions-Parameter; Theme::asset() hängt ?v= an)
 *   Kit::fragment('brand')        → Datei des Fragments nach der Suchreihenfolge (Core\Fragments)
 *   Kit::source('immobilien')     → 'local' | 'upload' | 'composer' (Kit::info(): Ordner, Paket, Ordner der fertigen Assets)
 */
final class Kit
{
    /** Ordner der Kits im Code-Stand (relativ zur Installation) */
    public const ROOTS = ['kits'];
    /** Ordner hochgeladener Kit-Pakete (relativ zur Installation; übersteht Deploys, siehe deploy/deploy.sh) */
    public const UPLOADS = 'storage/kits';
    /** Composer-Pakettyp eines Kits */
    public const PACKAGE_TYPE = 'klxm-studio-kit';
    /** Dateiname der Kit-Definition (kit.php als gleichwertige Alternative) */
    public const DEFINITIONS = ['theme.php', 'kit.php'];

    /** @var array<string, array{dir: string, source: string, package: array, public: ?string}>|null */
    private static ?array $index = null;
    /** @var array<string, list<array{dir: string, source: string}>> verdeckte Kits gleichen Namens */
    private static array $shadowed = [];
    /** Prüf-Naht: anderes installed.json (Tests); null = vendor/composer/installed.json */
    private static ?string $installedJson = null;

    /** Gültiger Kit-Name (Ordnername) */
    public static function clean(string $name): string
    {
        return (string) preg_replace('~[^a-z0-9_\-]~i', '', $name);
    }

    /**
     * Ordner eines Kits (kits/, storage/kits/ oder Composer-Paket) – ohne Namen der Hauptordner kits/.
     * Gibt es das Kit nicht, der Pfad unter kits/ (für Neuanlage und Fehlermeldungen).
     */
    public static function dir(string $name = ''): string
    {
        $name = self::clean($name);
        if ($name === '') return ROOT . '/' . self::ROOTS[0];
        if (isset(self::index()[$name])) return self::index()[$name]['dir'];
        // nach dem Zwischenspeichern neu angelegt (z. B. kit:create im selben Aufruf)
        foreach (self::ROOTS as $root) {
            $dir = ROOT . '/' . $root . '/' . $name;
            if (self::definitionFile($dir) !== null) return $dir;
        }
        return ROOT . '/' . self::ROOTS[0] . '/' . $name;
    }

    /** Gibt es das Kit (mit theme.php bzw. kit.php)? */
    public static function exists(string $name): bool
    {
        return self::clean($name) !== '' && self::definitionFile(self::dir($name)) !== null;
    }

    /** Definitionsdatei im Kit-Ordner: theme.php, sonst kit.php, sonst null */
    public static function definitionFile(string $dir): ?string
    {
        foreach (self::DEFINITIONS as $f) {
            if (is_file($dir . '/' . $f)) return $dir . '/' . $f;
        }
        return null;
    }

    /** Alle installierten Kits: [name => ordner] alphabetisch */
    public static function all(): array
    {
        return array_map(fn($k) => $k['dir'], self::index());
    }

    /** Herkunft eines Kits: 'local' (kits/), 'upload' (storage/kits/), 'composer' – null, wenn es das Kit nicht gibt */
    public static function source(string $name): ?string
    {
        return self::index()[self::clean($name)]['source'] ?? null;
    }

    /** Angaben zu einem Kit: dir, source, package (Eintrag aus installed.json bzw. composer.json), public (Ordner der fertigen Assets) */
    public static function info(string $name): ?array
    {
        return self::index()[self::clean($name)] ?? null;
    }

    /** Verdeckte Kits (gleicher Name an mehreren Orten): [name => [[dir, source], …]] – es gilt kits/ vor storage/kits/ vor Composer */
    public static function shadowed(): array
    {
        self::index();
        return self::$shadowed;
    }

    /** Zwischenspeicher leeren (nach Installation/Entfernen eines Pakets) */
    public static function reset(): void
    {
        self::$index = null;
        self::$shadowed = [];
    }

    /** Prüf-Naht: Composer-Pakete aus einer anderen installed.json lesen (null = vendor/composer/installed.json) */
    public static function useInstalledJson(?string $file): void
    {
        self::$installedJson = $file;
        self::reset();
    }

    /**
     * Einfache Angaben der Definitionsdatei, ohne sie auszuführen (Kits können gleichnamige Helfer definieren):
     * label, description, version, requires – nur Zeilen mit vier Leerzeichen Einzug wie in den mitgelieferten Kits.
     */
    public static function meta(string $dir): array
    {
        $src = (string) @file_get_contents((string) self::definitionFile($dir));
        $out = [];
        foreach (['label', 'description', 'version', 'requires'] as $k) {
            $out[$k] = preg_match("~^    '" . $k . "'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'~m", $src, $m) ? stripslashes($m[1]) : '';
        }
        return $out;
    }

    /** Passt eine Angabe wie '>=1.0.0' zur Core-Version? (leer bzw. unlesbar = ja) */
    public static function compatible(string $req): bool
    {
        $req = trim($req);
        if ($req === '' || !preg_match('~^(>=|>|<=|<|=|==)?\s*([\d.]+)$~', $req, $m)) return true;
        return version_compare(CMS_VERSION, $m[2], ($m[1] ?? '') ?: '>=');
    }

    /** Zusammenstellen: kits/ → storage/kits/ → Composer; erster Fund gewinnt */
    private static function index(): array
    {
        if (self::$index !== null) return self::$index;
        $out = [];
        $add = function (string $name, string $dir, string $source, array $package = [], ?string $public = null) use (&$out): void {
            if (self::clean($name) !== $name || $name === '' || self::definitionFile($dir) === null) return;
            if (isset($out[$name])) {
                self::$shadowed[$name][] = ['dir' => $dir, 'source' => $source];
                return;
            }
            $out[$name] = ['dir' => $dir, 'source' => $source, 'package' => $package, 'public' => $public];
        };
        foreach (self::ROOTS as $root) {
            foreach (glob(ROOT . '/' . $root . '/*', GLOB_ONLYDIR) ?: [] as $dir) $add(basename($dir), $dir, 'local');
        }
        foreach (glob(ROOT . '/' . self::UPLOADS . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $c = is_file($dir . '/composer.json') ? (array) json_decode((string) file_get_contents($dir . '/composer.json'), true) : [];
            $add(basename($dir), $dir, 'upload', $c, $dir . '/' . self::publicFolder($c));
        }
        foreach (self::packages() as $name => $p) $add($name, $p['dir'], 'composer', $p['package'], $p['public']);
        ksort($out);
        return self::$index = $out;
    }

    /** Ordner der fertigen Assets im Paket: composer.json → extra.klxm-studio.public (Standard: public) – ohne „..“ */
    public static function publicFolder(array $composer): string
    {
        $p = trim(str_replace('\\', '/', (string) ($composer['extra']['klxm-studio']['public'] ?? 'public')), '/');
        return $p === '' || in_array('..', explode('/', $p), true) ? 'public' : $p;
    }

    /** Composer-Pakete vom Typ klxm-studio-kit: [kit-name => [dir, package, public]] */
    private static function packages(): array
    {
        $file = self::$installedJson ?? ROOT . '/vendor/composer/installed.json';
        if (!is_file($file)) return [];
        $data = json_decode((string) file_get_contents($file), true);
        $out = [];
        foreach ((array) ($data['packages'] ?? $data ?? []) as $p) {
            if (!is_array($p) || ($p['type'] ?? '') !== self::PACKAGE_TYPE) continue;
            $dir = realpath(dirname($file) . '/' . ($p['install-path'] ?? ('../' . ($p['name'] ?? ''))));
            if (!$dir) continue;
            // Kit-Name: extra.klxm-studio.kit, sonst Paketname ohne Hersteller und Präfix „kit-“
            $name = (string) ($p['extra']['klxm-studio']['kit'] ?? preg_replace('~^(klxm-studio-)?kit-~', '', basename((string) ($p['name'] ?? ''))));
            if (!preg_match('~^[a-z][a-z0-9_-]{1,40}$~', $name) || isset($out[$name])) continue;
            $out[$name] = ['dir' => $dir, 'package' => $p, 'public' => $dir . '/' . self::publicFolder($p)];
        }
        return $out;
    }

    /** Öffentlicher Asset-Ordner eines Kits: public/assets/kits/{name}; ohne Namen der Hauptordner public/assets/kits */
    public static function publicDir(string $name = ''): string
    {
        $name = self::clean($name);
        return PublicPaths::dir(PublicPaths::KITS, $name);
    }

    /** Öffentliche Adresse (ohne Versions-Parameter) einer Datei des Kits, z. B. für Schriften, die exakt der URL im CSS entsprechen müssen */
    public static function url(string $name, string $path = ''): string
    {
        return PublicPaths::url(PublicPaths::KITS, self::clean($name), $path);
    }

    /** Datei eines Fragments für das aktive (bzw. angegebene) Kit nach der Suchreihenfolge – siehe Core\Fragments::find() */
    public static function fragment(string $name, ?string $kit = null): ?string
    {
        return Fragments::find($name, $kit)['file'] ?? null;
    }

    /**
     * Befehle des aktiven Kits für bin/console: kits/{kit}/console.php gibt ['name' => ['Beschreibung', fn(array $args): int], …]
     * zurück (z. B. Inhalts-Umstellungen des Kits wie praxis:migrate-team). Kern- und Erweiterungsbefehle haben Vorrang.
     */
    public static function commands(): array
    {
        static $cache = [];
        $file = app()->theme->path . '/console.php';
        if (!isset($cache[$file])) {
            $cmds = is_file($file) ? require $file : [];
            $cache[$file] = is_array($cmds) ? array_filter($cmds, fn($c, $k) => is_string($k) && is_array($c) && is_callable($c[1] ?? null), ARRAY_FILTER_USE_BOTH) : [];
        }
        return $cache[$file];
    }

    /** Pfad relativ zur Installation (für Meldungen, Kommentare im Entwicklermodus) */
    public static function relative(string $path): string
    {
        return str_starts_with($path, ROOT . '/') ? substr($path, strlen(ROOT) + 1) : $path;
    }
}
