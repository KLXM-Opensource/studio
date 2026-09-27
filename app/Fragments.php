<?php
declare(strict_types=1);

namespace Core;

/**
 * Kern-Fragmente (wie REDAXO-Fragmente): kleine Vorlagen, die der Kern mitbringt und jedes Kit nutzt.
 * Kits sind eigenständige Projekte ohne Eltern/Kind-Vererbung – der Kern ist der Werkzeugkasten darunter.
 *
 * A) Nur Kern (CORE_ONLY): Redaktions-Editor, Werkzeugleiste, 2-Klick-Video (Hinweistext, Datenschutz-Link,
 *    Reihenfolge Hinweis vor Schaltfläche, „künftig direkt laden“, Skript mit window.cmsConsent / cms:consent).
 *    Kits liefern diese Dateien nicht aus und können sie nicht ersetzen – vorhandene Kit-Dateien werden ignoriert
 *    (Entwicklermodus: Warnung als HTML-Kommentar, Protokoll, Prüfung „health“). Gestaltung nur per CSS
 *    (.vembed, .vembed__gate, .vembed__info, .vembed__row, .vembed__play …) und Optionen in theme.php → 'fragments'.
 *
 * B) Überschreibbar: Der Kern liefert eine barrierearme, CSP-sichere Standardfassung; ein Kit (oder das Projekt)
 *    ersetzt sie vollständig durch eine gleichnamige Datei. Suchreihenfolge:
 *      project/overrides/kits/{kit}/fragments/{name}.php   Anpassung dieses Projekts (von Updates nie angefasst)
 *      kits/{kit}/fragments/{name}.php                     Fassung des Kits
 *      kits/{kit}/templates/partials/{name}.php            bisheriger Ort im Kit (weiter gültig)
 *      app/Views/fragments/{name}.php                      Kern
 *      app/Views/{name}.php                                ältere Kern-Ansichten (search-form, header-actions)
 *    Überschreibt ein Kit ein Kern-Fragment, merkt sich der Kern die Prüfsumme des Originals (storage/fragments.json)
 *    und meldet, wenn sich das Original seitdem geändert hat (Übersicht → Technik & Betrieb, php bin/console health,
 *    php bin/console fragments:list [--accept]).
 *
 * Aufruf: $kit->partial('brand', [...]) bzw. fragment('brand', [...]). Optionen des Kits für ein Kern-Fragment:
 * theme.php → 'fragments' => ['video-embed' => ['ratio_class' => 'ratio-'], 'brand' => ['mark' => 'dot']] – im Fragment
 * als $options. Entwicklermodus (config 'debug'): HTML-Kommentar mit der Ebene, aus der das Fragment stammt.
 */
final class Fragments
{
    /** Nur Kern – Kits können diese Fragmente nicht ersetzen */
    public const CORE_ONLY = ['video-embed', 'editor', 'toolbar'];

    /** Ältere Kern-Ansichten unter app/Views/, die als überschreibbare Fragmente gelten */
    public const CORE_VIEWS = ['search-form', 'header-actions'];

    /** Ebenen der Suchreihenfolge (Bezeichnung im Entwicklermodus und in der Übersicht) */
    public const LAYERS = ['project' => 'Projekt', 'kit' => 'Kit', 'kit-partial' => 'Kit (templates/partials)', 'core' => 'Kern', 'core-view' => 'Kern'];

    /** Bereits gemeldete ignorierte Kit-Dateien (je Anfrage einmal protokollieren) */
    private static array $warned = [];

    public static function coreDir(): string
    {
        return ROOT . '/app/Views/fragments';
    }

    public static function isCoreOnly(string $name): bool
    {
        return in_array($name, self::CORE_ONLY, true);
    }

    private static function clean(string $name): string
    {
        return (string) preg_replace('~[^a-z0-9_\-]~i', '', $name);
    }

    private static function kitName(?string $kit): string
    {
        return Kit::clean($kit ?? (isset(app()->theme) ? app()->theme->name : ''));
    }

    /** Kandidaten der Suchreihenfolge: [ebene => datei] (Nur-Kern-Fragmente: nur die Kern-Ebenen) */
    public static function candidates(string $name, ?string $kit = null): array
    {
        $name = self::clean($name);
        $kit = self::kitName($kit);
        $out = [];
        if (!self::isCoreOnly($name) && $kit !== '') {
            $dir = Kit::dir($kit);
            $out['project'] = ROOT . '/project/overrides/kits/' . $kit . '/fragments/' . $name . '.php';
            $out['kit'] = $dir . '/fragments/' . $name . '.php';
            $out['kit-partial'] = $dir . '/templates/partials/' . $name . '.php';
        }
        $out['core'] = self::coreDir() . '/' . $name . '.php';
        if (in_array($name, self::CORE_VIEWS, true)) $out['core-view'] = ROOT . '/app/Views/' . $name . '.php';
        return $out;
    }

    /** Erste vorhandene Datei: ['name', 'file', 'layer'] oder null */
    public static function find(string $name, ?string $kit = null): ?array
    {
        foreach (self::candidates($name, $kit) as $layer => $file) {
            if (is_file($file)) return ['name' => self::clean($name), 'file' => $file, 'layer' => $layer];
        }
        return null;
    }

    /** Gibt es das Fragment (in irgendeiner Ebene)? */
    public static function exists(string $name, ?string $kit = null): bool
    {
        return self::find($name, $kit) !== null;
    }

    /** Kommt das Fragment aus dem Kern (keine Fassung von Projekt oder Kit)? */
    public static function fromCore(string $name, ?string $kit = null): bool
    {
        return str_starts_with((string) (self::find($name, $kit)['layer'] ?? ''), 'core');
    }

    /** Kit-Dateien für Nur-Kern-Fragmente (werden ignoriert): [name => datei] */
    public static function ignored(?string $kit = null): array
    {
        $kit = self::kitName($kit);
        if ($kit === '') return [];
        $dir = Kit::dir($kit);
        $out = [];
        foreach (self::CORE_ONLY as $name) {
            foreach ([ROOT . '/project/overrides/kits/' . $kit . '/fragments/', $dir . '/fragments/', $dir . '/templates/partials/'] as $base) {
                if (is_file($base . $name . '.php')) $out[$name] = $base . $name . '.php';
            }
        }
        return $out;
    }

    public static function devMode(): bool
    {
        return (bool) app()->config->get('debug');
    }

    /**
     * Fragment rendern. $vars werden zu Variablen der Vorlage; dazu $options = Optionen des Kits
     * (theme.php → 'fragments' → {name}) und $fragment = ['name', 'layer', 'file'].
     */
    public static function render(string $name, array $vars = [], ?string $kit = null): string
    {
        $f = self::find($name, $kit);
        $kitName = self::kitName($kit);
        if (!$f) throw new \RuntimeException("Fragment '$name' fehlt (Kit '$kitName', Kern app/Views/fragments)");
        $theme = isset(app()->theme) && app()->theme->name === $kitName ? app()->theme : null;
        $vars += ['options' => (array) ($theme?->def['fragments'][$f['name']] ?? []), 'fragment' => $f];
        $html = Theme::capture($f['file'], $vars);
        if (!self::devMode()) return $html;

        // Entwicklermodus: Herkunft als HTML-Kommentar; ignorierte Kit-Dateien für Nur-Kern-Fragmente melden
        $note = '';
        if (self::isCoreOnly($f['name']) && ($skip = self::ignored($kitName)[$f['name']] ?? null)) {
            $note = "\n<!-- WARNUNG: " . Kit::relative($skip) . ' wird ignoriert – „' . $f['name'] . '“ ist ein Kern-Fragment (nur per CSS gestaltbar) -->';
            if (!isset(self::$warned[$skip])) {
                self::$warned[$skip] = true;
                error_log('[fragments] ' . Kit::relative($skip) . ' wird ignoriert: „' . $f['name'] . '“ gehört nur zum Kern');
            }
        }
        if (trim($html) === '' || in_array($f['name'], ['toolbar'], true)) return $html;   // leere Ausgaben bleiben leer (Aufrufer prüfen darauf)
        return '<!-- fragment ' . $f['name'] . ': ' . (self::LAYERS[$f['layer']] ?? $f['layer']) . ' (' . Kit::relative($f['file']) . ') -->' . $note . "\n"
            . $html . "\n<!-- /fragment " . $f['name'] . ' -->';
    }

    // ------------------------------------------------------------------ Überschriebene Kern-Fragmente (Prüfsummen)

    private static function stateFile(): string
    {
        return ROOT . '/storage/fragments.json';
    }

    private static function state(): array
    {
        $d = is_file(self::stateFile()) ? json_decode((string) @file_get_contents(self::stateFile()), true) : null;
        return is_array($d) ? $d : [];
    }

    private static function saveState(array $s): void
    {
        ksort($s);
        @file_put_contents(self::stateFile(), json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n", LOCK_EX);
    }

    /** Alle Kern-Fragmente (überschreibbare), die es als Datei im Kern gibt: [name => datei] */
    public static function coreFragments(): array
    {
        $out = [];
        foreach (glob(self::coreDir() . '/*.php') ?: [] as $f) $out[basename($f, '.php')] = $f;
        foreach (self::CORE_VIEWS as $n) if (is_file(ROOT . "/app/Views/$n.php")) $out[$n] ??= ROOT . "/app/Views/$n.php";
        ksort($out);
        return $out;
    }

    /**
     * Überschriebene Kern-Fragmente aller installierten Kits (bzw. eines Kits): Liste mit
     * kit, name, layer, file, core (Datei), hash (aktuelle Prüfsumme des Originals), known (gemerkte), changed (bool), since.
     * Beim ersten Sehen wird die Prüfsumme des Originals gemerkt ($record = true).
     */
    public static function overrides(?string $kit = null, bool $record = true): array
    {
        $state = self::state();
        $dirty = false;
        $out = [];
        $kits = $kit !== null ? [Kit::clean($kit)] : array_keys(Kit::all());
        foreach ($kits as $k) {
            foreach (self::coreFragments() as $name => $core) {
                if (self::isCoreOnly($name)) continue;
                $f = self::find($name, $k);
                if (!$f || str_starts_with($f['layer'], 'core')) continue;
                $key = $k . '/' . $name;
                $hash = sha1_file($core) ?: '';
                if (!isset($state[$key]) && $record) {
                    $state[$key] = ['core' => $hash, 'since' => date('Y-m-d')];
                    $dirty = true;
                }
                $known = (string) ($state[$key]['core'] ?? '');
                $out[] = ['kit' => $k, 'name' => $name, 'layer' => $f['layer'], 'file' => $f['file'], 'core' => $core,
                    'hash' => $hash, 'known' => $known, 'changed' => $known !== '' && $known !== $hash, 'since' => (string) ($state[$key]['since'] ?? '')];
            }
        }
        if ($dirty) self::saveState($state);
        return $out;
    }

    /** Geänderte Originale als geprüft markieren (neue Prüfsumme merken). Rückgabe: Anzahl */
    public static function accept(?string $kit = null, ?string $name = null): int
    {
        $state = self::state();
        $n = 0;
        foreach (self::overrides($kit) as $o) {
            if ($name !== null && $o['name'] !== $name) continue;
            if ($o['changed'] || !isset($state[$o['kit'] . '/' . $o['name']])) {
                $state[$o['kit'] . '/' . $o['name']] = ['core' => $o['hash'], 'since' => date('Y-m-d')];
                $n++;
            }
        }
        if ($n) self::saveState($state);
        return $n;
    }

    /**
     * Prüfungen für health und die Übersicht (Technik & Betrieb): null = Hinweis (blockiert keinen Deploy).
     * Nur das aktive Kit – so erscheint die Meldung auf der Website, die das Kit nutzt.
     */
    public static function health(): array
    {
        $kit = isset(app()->theme) ? app()->theme->name : '';
        if ($kit === '') return [];
        $out = [];
        foreach (self::overrides($kit) as $o) {
            if ($o['changed']) {
                $out[__('Kern-Fragment „{name}“ hat sich geändert, seit Kit „{kit}“ es überschreibt ({file} prüfen, dann php bin/console fragments:list --accept)', ['name' => $o['name'], 'kit' => $kit, 'file' => Kit::relative($o['file'])])] = null;
            }
        }
        foreach (self::ignored($kit) as $name => $file) {
            $out[__('Kit „{kit}“: {file} wird ignoriert („{name}“ ist ein Kern-Fragment)', ['kit' => $kit, 'file' => Kit::relative($file), 'name' => $name])] = null;
        }
        return $out;
    }
}
