<?php
declare(strict_types=1);

namespace Core;

/**
 * Lädt config/config.php (Standardwerte, versioniert) und überschreibt sie mit
 * config/config.local.php (Geheimnisse, NICHT versioniert). Fehlt die lokale
 * Datei, wird sie beim ersten Aufruf mit zufälligen Schlüsseln und zufälliger Verwaltungsadresse (admin_path) angelegt.
 */
final class Config
{
    private function __construct(private array $data) {}

    /**
     * Reihenfolge: config.php ← config.local.php ← config/sites/{site}.php (Multi-Site).
     * Weitere Websites erhalten automatisch eine eigene SQLite-Datenbank unter storage/sites/{key}.
     */
    public static function load(string $dir, string $site = Site::DEFAULT): self
    {
        $base = require $dir . '/config.php';
        $localFile = $dir . '/config.local.php';

        if (!is_file($localFile)) {
            self::writeLocal($localFile);
        }
        $local = is_file($localFile) ? require $localFile : [];
        if (is_array($local) && isset($local['kit']) && !isset($local['theme'])) $local['theme'] = $local['kit'];   // 'kit' = Alias von 'theme'
        $data = array_replace_recursive($base, is_array($local) ? $local : []);

        $siteCfg = Sites::all()[$site] ?? [];
        if ($site !== Site::DEFAULT) {
            $data['db']['path'] = ROOT . '/storage/sites/' . $site . '/database/site.sqlite';
            // Schlüssel der Hauptinstallation nie erben
            $data['app_key'] = '';
            $data['setup_token'] = '';
            // Eigenes Session-Cookie je Website (wichtig, wenn Websites denselben Host teilen, z. B. lokal)
            $data['session']['name'] = ($data['session']['name'] ?? 'cms_sess') . '_' . str_replace('-', '_', $site);
        }
        $data = array_replace_recursive($data, $siteCfg);
        $data['site'] = $site;
        return new self($data);
    }

    /** Legt config.local.php mit zufälligem App-Key und Setup-Token an. */
    public static function writeLocal(string $file, array $overrides = []): bool
    {
        $values = array_replace([
            'app_key'     => bin2hex(random_bytes(32)),
            'setup_token' => bin2hex(random_bytes(12)),
            'admin_path'  => AdminPath::random(),   // eigene Verwaltungsadresse statt /admin (Core\AdminPath)
            'debug'       => false,
        ], $overrides);

        $php = "<?php\n// Lokale Konfiguration – NICHT versionieren, NICHT weitergeben.\n"
             . "// Automatisch erzeugt am " . date('Y-m-d H:i') . "\nreturn " . var_export($values, true) . ";\n";

        $ok = @file_put_contents($file, $php, LOCK_EX) !== false;
        if ($ok) {
            @chmod($file, 0640);
        }
        return $ok;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $node = $this->data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($node) || !array_key_exists($part, $node)) {
                return $default;
            }
            $node = $node[$part];
        }
        return $node;
    }

    public function all(): array
    {
        return $this->data;
    }
}
