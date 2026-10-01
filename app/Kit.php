<?php
declare(strict_types=1);

namespace Core;

/**
 * Zentrale Pfad-API für Kits – die einzige Stelle, die weiß, wo Kits liegen.
 *
 *   kits/{name}/                 Kit (theme.php bzw. kit.php, Templates, Blöcke, Fragmente, Startinhalte, lang/)
 *   public/assets/kits/{name}/   öffentliche Assets (gebaut von tools/build.mjs aus kits/{name}/assets) – Ablage: Core\PublicPaths
 *   themes/{name}/               Rückfall: ältere Installationen und Kits von Dritten (vor der Umbenennung in kits/)
 *   public/kits/{name}/, public/themes/{name}/
 *                                Rückfall für Assets, solange der Server nicht umgestellt ist (bin/console assets:migrate);
 *                                alte Adressen /kits/… und /themes/… leitet public/index.php mit 301 auf /assets/kits/… um,
 *                                sobald die Datei dort liegt
 *
 * Technische Namen bleiben aus Kompatibilitätsgründen: Klasse Core\Theme, Datei theme.php (kit.php geht ebenso),
 * Konfiguration 'theme' (Alias 'kit'), app()->theme (Alias app()->kit), Einstellung sys.theme.
 *
 *   Kit::dir('praxis')            → /…/kits/praxis (oder /…/themes/praxis) · Kit::dir() → /…/kits
 *   Kit::publicDir('praxis')      → /…/public/assets/kits/praxis (oder public/kits|themes/praxis, falls nur dort vorhanden)
 *   Kit::url('praxis', 'css/x')   → /assets/kits/praxis/css/x (ohne Versions-Parameter; Theme::asset() hängt ?v= an)
 *   Kit::fragment('brand')        → Datei des Fragments nach der Suchreihenfolge (Core\Fragments)
 */
final class Kit
{
    /** Ordner der Kits, in dieser Reihenfolge durchsucht (themes = Rückfall für ältere Installationen) */
    public const ROOTS = ['kits', 'themes'];
    /** Dateiname der Kit-Definition (kit.php als gleichwertige Alternative) */
    public const DEFINITIONS = ['theme.php', 'kit.php'];

    /** Gültiger Kit-Name (Ordnername) */
    public static function clean(string $name): string
    {
        return (string) preg_replace('~[^a-z0-9_\-]~i', '', $name);
    }

    /**
     * Ordner eines Kits (erster Treffer in kits/, dann themes/) – ohne Namen der Hauptordner kits/.
     * Gibt es das Kit nicht, der Pfad unter kits/ (für Neuanlage und Fehlermeldungen).
     */
    public static function dir(string $name = ''): string
    {
        $name = self::clean($name);
        if ($name === '') return ROOT . '/' . self::ROOTS[0];
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

    /** Alle installierten Kits: [name => ordner] (kits/ vor themes/, gleiche Namen nur einmal), alphabetisch */
    public static function all(): array
    {
        $out = [];
        foreach (self::ROOTS as $root) {
            foreach (glob(ROOT . '/' . $root . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                if (!isset($out[$name]) && self::clean($name) === $name && self::definitionFile($dir) !== null) $out[$name] = $dir;
            }
        }
        ksort($out);
        return $out;
    }

    /** Liegt das Kit noch im alten Ordner themes/? (für Hinweise, bin/console kit:list) */
    public static function isLegacy(string $name): bool
    {
        return str_starts_with(self::dir($name), ROOT . '/themes/');
    }

    /**
     * Öffentlicher Asset-Ordner eines Kits: public/assets/kits/{name}, Rückfall public/kits/{name} bzw. public/themes/{name}
     * (nur wenn allein dort vorhanden – Server noch nicht umgestellt). Ohne Namen der Hauptordner public/assets/kits.
     */
    public static function publicDir(string $name = ''): string
    {
        $name = self::clean($name);
        return $name === '' ? PublicPaths::root() . '/' . PublicPaths::AREAS[PublicPaths::KITS][0] : PublicPaths::dir(PublicPaths::KITS, $name);
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
