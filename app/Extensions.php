<?php
declare(strict_types=1);

namespace Core;

/**
 * Erweiterungen (Module) – zusätzlich zu Core und Theme.
 *
 * Fundorte:
 *   extensions/{name}/extension.php                  projektspezifisch, liegt im Projekt
 *   vendor/{hersteller}/{paket}/extension.php         per Composer: "type": "klxm-studio-extension" (Altname "mycms-extension" wird weiter erkannt)
 *
 * Aktiv ist eine Erweiterung, wenn die Website sie einschaltet:
 *   - Konfiguration config/sites/{key}.php (bzw. config/config.local.php): 'extensions' => ['kalender', 'dav'] (an) oder
 *     ['dav' => false] (aus) – gilt vorrangig und sperrt den Schalter in der Verwaltung;
 *   - sonst Verwaltung → Administration → Funktionen & Erweiterungen (Einstellung sys.extensions_ui, Recht system.features).
 * Abschalten ist nicht destruktiv: Daten und Einstellungen bleiben, die Erweiterung wird nur nicht mehr gestartet – damit
 * entfallen Routen, Menüpunkte, Rechte, CSP-Quellen, Befehle (Cron) und Haken für die nächste Anfrage.
 *
 * extension.php liefert ein Manifest:
 *   return [
 *     'name' => 'kalender', 'label' => 'Kalender', 'version' => '1.0.0', 'requires' => '>=1.0.0',
 *     'description' => '…',
 *     'boot' => function (Core\Extension $x) { $x->blocks(…); $x->routes(…); … },
 *     // optional – für „Funktionen & Erweiterungen“ und den Lebenszyklus:
 *     'author' => '…', 'license' => 'MIT', 'homepage' => 'https://…',   // sonst aus composer.json
 *     'risk' => 'Startet Programme auf dem Server …',                    // Sicherheitshinweis (verlangt Bestätigung + Passwort)
 *     'provides' => ['Menüpunkt „Kalender“', 'Cron: kalender:sync'],     // „Was passiert beim Einschalten“
 *     'requirements' => fn(): array => ['ffmpeg gefunden' => true],       // Prüfung ohne Start (true/false/null = Warnung)
 *     'usage' => fn(): ?string => '12 Termine',                           // „Wird auf dieser Website verwendet von …“
 *     'commands' => ['kalender:sync'],                                    // Cron-Befehle: ohne Aktivierung ruhig beenden
 *     'install' => fn(Core\Database $db) => …,                            // einmalig je Website beim ersten Start (nach Tabellen und Migrationen)
 *     'deactivate' => fn() => …,                                          // beim Abschalten in der Verwaltung (Daten bleiben)
 *     'required' => true,                                                 // Kern der Website: in der Verwaltung nicht abschaltbar
 *   ];
 */
final class Extensions
{
    /** Schalter der Verwaltung je Website: ['video_tools' => true, 'dav' => false] */
    public const UI_KEY = 'sys.extensions_ui';

    /** @var array<string, array> alle gefundenen Manifeste */
    private static ?array $found = null;
    /** @var array<string, Extension> aktive Erweiterungen */
    private static array $active = [];

    /** Alle installierten Erweiterungen [name => manifest + dir] */
    public static function available(): array
    {
        if (self::$found !== null) return self::$found;
        $out = [];
        foreach (glob(ROOT . '/extensions/*/extension.php') ?: [] as $f) {
            self::add($out, $f, 'lokal');
        }
        // Composer-Pakete vom Typ „klxm-studio-extension“
        $installed = ROOT . '/vendor/composer/installed.json';
        if (is_file($installed)) {
            $data = json_decode((string) file_get_contents($installed), true);
            foreach ((array) ($data['packages'] ?? $data ?? []) as $p) {
                // Paket-Typ „klxm-studio-extension“; „mycms-extension“ (historische technische Kennung) bleibt still gültig
                if (!in_array($p['type'] ?? '', ['klxm-studio-extension', 'mycms-extension'], true)) continue;
                $dir = realpath(ROOT . '/vendor/composer/' . ($p['install-path'] ?? ('../' . ($p['name'] ?? ''))));
                $entry = (string) ($p['extra']['klxm-studio']['entry'] ?? $p['extra']['mycms']['entry'] ?? 'extension.php');
                if ($dir && is_file("$dir/$entry")) {
                    self::add($out, "$dir/$entry", 'composer: ' . $p['name'] . ' ' . ($p['version'] ?? ''), $p);
                }
            }
        }
        return self::$found = $out;
    }

    private static function add(array &$out, string $file, string $source, array $package = []): void
    {
        $m = require $file;
        if (!is_array($m) || !preg_match('~^[a-z][a-z0-9_-]{1,40}$~', (string) ($m['name'] ?? ''))) return;
        $out[$m['name']] = $m + ['label' => $m['name'], 'version' => '', 'description' => '', 'dir' => dirname($file), 'source' => $source, 'package' => $package];
    }

    /** Konfiguration der Website: 'extensions' => ['dav'] (an) bzw. ['dav' => false] (aus). @return array<string, bool> */
    public static function configured(?Config $cfg = null): array
    {
        $out = [];
        foreach ((array) ($cfg ?? app()->config)->get('extensions', []) as $k => $v) {
            if (is_int($k)) {
                if (is_string($v) && $v !== '') $out[$v] = true;
            } else {
                $out[(string) $k] = (bool) $v;
            }
        }
        return $out;
    }

    /** Schalter der Verwaltung (sys.extensions_ui). @return array<string, bool> */
    public static function ui(): array
    {
        try {
            return isset(app()->settings) ? array_map('boolval', (array) app()->settings->get(self::UI_KEY, [])) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Namen der Erweiterungen, die auf dieser Website laufen sollen (Konfiguration vor Verwaltung) */
    public static function enabled(): array
    {
        $state = self::ui();
        foreach (self::configured() as $n => $on) $state[$n] = $on;
        return array_keys(array_filter($state));
    }

    /** Legt die Konfiguration die Erweiterung fest? ['file' => …, 'value' => bool] oder null */
    public static function lock(string $name): ?array
    {
        $c = self::configured();
        return array_key_exists($name, $c) ? ['file' => Features::configFile('extensions'), 'value' => $c[$name]] : null;
    }

    /** Ist die Erweiterung auf einer (anderen) Website eingeschaltet? Konfiguration, sonst Schalter der Verwaltung dieser Website */
    public static function enabledForSite(string $site, string $name): bool
    {
        if ($site === site()->key) return in_array($name, self::enabled(), true);
        $cfg = Network\Network::config($site);
        $c = self::configured($cfg);
        if (array_key_exists($name, $c)) return $c[$name];
        try {
            $dbc = (array) $cfg->get('db');
            if (($dbc['driver'] ?? 'sqlite') === 'sqlite' && !is_file((string) ($dbc['path'] ?? ''))) return false;
            $v = (new Database($dbc))->fetchValue('SELECT value_json FROM settings WHERE skey = ?', [self::UI_KEY]);
            return !empty((json_decode((string) $v, true) ?: [])[$name]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Aktive Erweiterungen dieser Website starten (nach Theme, vor dem Routing) */
    public static function boot(): void
    {
        foreach (self::enabled() as $name) self::start($name);
    }

    /**
     * Eine Erweiterung starten: Manifest-boot, offene Datenbank-Schritte, einmalige Einrichtung ('install').
     * $strict = true (Einschalten in der Verwaltung): Fehler als Ausnahme statt nur im Fehlerprotokoll.
     */
    private static function start(string $name, bool $strict = false): ?Extension
    {
        if (isset(self::$active[$name])) return self::$active[$name];
        $m = self::available()[$name] ?? null;
        if (!$m) {
            error_log("[Erweiterung] „{$name}“ ist eingeschaltet, aber nicht installiert.");
            if ($strict) throw new \RuntimeException(__('Die Erweiterung „{name}“ ist nicht installiert.', ['name' => $name]));
            return null;
        }
        if (!self::compatible($m)) {
            error_log("[Erweiterung] „{$name}“ verlangt Core {$m['requires']} – installiert ist " . CMS_VERSION . '.');
            if ($strict) throw new \RuntimeException(__('„{name}“ verlangt Core {req} – installiert ist {core}.', ['name' => $name, 'req' => (string) $m['requires'], 'core' => CMS_VERSION]));
            return null;
        }
        $x = new Extension($m['name'], $m['dir'], $m);
        self::$active[$name] = $x;
        if (is_callable($m['boot'] ?? null)) ($m['boot'])($x);
        $x->ensureTables();
        $x->runMigrations();
        // Einrichtung einmalig je Website (Manifest 'install'), nach den Datenbank-Schritten
        if (is_callable($m['install'] ?? null) && isset(app()->settings) && !app()->settings->get('ext.' . $name . '.installed')) {
            ($m['install'])(app()->db);
            app()->settings->set('ext.' . $name . '.installed', now());
        }
        return $x;
    }

    /**
     * In der Verwaltung einschalten: Schalter setzen und sofort starten (Datenbank-Schritte und 'install' laufen jetzt).
     * Scheitert der Start, bleibt die Erweiterung aus. @return list<string> neu angemeldete Funktionen (Features::register)
     */
    public static function activate(string $name): array
    {
        $m = self::available()[$name] ?? throw new \InvalidArgumentException(__('Die Erweiterung „{name}“ ist nicht installiert.', ['name' => $name]));
        if ($l = self::lock($name)) throw new \InvalidArgumentException(__('Per Konfiguration festgelegt ({file}) – hier nicht änderbar.', ['file' => $l['file']]));
        if (!self::compatible($m)) throw new \InvalidArgumentException(__('„{name}“ verlangt Core {req} – installiert ist {core}.', ['name' => $name, 'req' => (string) $m['requires'], 'core' => CMS_VERSION]));
        $before = array_keys(Features::catalog());
        $prev = self::ui();
        self::setUi($name, true);
        try {
            self::start($name, true);
        } catch (\Throwable $e) {
            unset(self::$active[$name]);
            app()->settings->set(self::UI_KEY, $prev);
            error_log('[Erweiterung ' . $name . '] Start: ' . $e->getMessage());
            throw new \RuntimeException(__('„{name}“ ließ sich nicht starten und bleibt aus: {msg}', ['name' => (string) $m['label'], 'msg' => $e->getMessage()]));
        }
        Features::flush();
        return array_values(array_diff(array_keys(Features::catalog()), $before));
    }

    /** In der Verwaltung abschalten – nicht destruktiv: Daten bleiben, die Erweiterung startet ab der nächsten Anfrage nicht mehr */
    public static function deactivate(string $name): void
    {
        $m = self::available()[$name] ?? null;
        if ($l = self::lock($name)) throw new \InvalidArgumentException(__('Per Konfiguration festgelegt ({file}) – hier nicht änderbar.', ['file' => $l['file']]));
        if ($m && !empty($m['required'])) throw new \InvalidArgumentException(__('„{name}“ gehört fest zu dieser Website und lässt sich nicht abschalten.', ['name' => (string) $m['label']]));
        if ($m && isset(self::$active[$name]) && is_callable($m['deactivate'] ?? null)) {
            self::safe(self::$active[$name], 'deactivate', $m['deactivate']);
        }
        self::setUi($name, false);
        unset(self::$active[$name]);
        Features::flush();
    }

    private static function setUi(string $name, bool $on): void
    {
        $ui = self::ui();
        $ui[$name] = $on;
        ksort($ui);
        app()->settings->set(self::UI_KEY, $ui);
    }

    /** Text einer Erweiterung übersetzen – auch wenn sie nicht läuft (dann aus {dir}/lang/{locale}.php) */
    public static function tr(string $name, string $text): string
    {
        static $dicts = [];
        if ($text === '' || I18n::locale() === I18n::SOURCE) return $text;
        if (!isset($dicts[$name])) {
            $f = (self::available()[$name]['dir'] ?? '') . '/lang/' . I18n::locale() . '.php';
            $dicts[$name] = is_file($f) ? (array) require $f : [];
        }
        return (string) ($dicts[$name][$text] ?? __($text));
    }

    /** Angaben für „Funktionen & Erweiterungen“: Manifest, composer.json, Lizenz, Risiko (übersetzt) */
    public static function meta(string $name): array
    {
        $m = self::available()[$name] ?? [];
        $c = [];
        if (is_file(($m['dir'] ?? '') . '/composer.json')) $c = (array) json_decode((string) file_get_contents($m['dir'] . '/composer.json'), true);
        $c = array_replace($c, (array) ($m['package'] ?? []));
        $lic = $m['license'] ?? ($c['license'] ?? '');
        if ($lic === '' && is_file(($m['dir'] ?? '') . '/LICENSE')) $lic = preg_match('~\bMIT\b~', (string) file_get_contents($m['dir'] . '/LICENSE', false, null, 0, 400)) ? 'MIT' : '';
        $docs = [];
        foreach ((array) ($m['docs'] ?? []) as $k => $href) $docs[self::tr($name, (string) $k)] = (string) $href;
        if (!$docs && is_file(($m['dir'] ?? '') . '/README.md')) $docs['README.md'] = '';
        return [
            'name' => $name, 'label' => self::tr($name, (string) ($m['label'] ?? $name)), 'version' => (string) (($m['version'] ?? '') ?: ltrim((string) ($c['version'] ?? ''), 'v')),
            'description' => self::tr($name, (string) ($m['description'] ?? ($c['description'] ?? ''))),
            'author' => (string) ($m['author'] ?? implode(', ', array_filter(array_map(fn($a) => (string) ($a['name'] ?? ''), (array) ($c['authors'] ?? []))))),
            'license' => is_array($lic) ? implode(' OR ', $lic) : (string) $lic,
            'homepage' => (string) ($m['homepage'] ?? ($c['homepage'] ?? '')),
            'package' => (string) ($c['name'] ?? ''), 'source' => (string) ($m['source'] ?? ''),
            'dir' => str_starts_with((string) ($m['dir'] ?? ''), ROOT . '/') ? substr((string) $m['dir'], strlen(ROOT) + 1) : (string) ($m['dir'] ?? ''),
            'requires' => (string) ($m['requires'] ?? ''), 'php' => (string) ($c['require']['php'] ?? ''),
            'risk' => self::tr($name, (string) ($m['risk'] ?? '')), 'required' => !empty($m['required']),
            'provides' => array_values(array_map(fn($p) => self::tr($name, (string) $p), (array) ($m['provides'] ?? []))), 'docs' => $docs,
            'compatible' => $m ? self::compatible($m) : false,
        ];
    }

    /** Voraussetzungen ohne Start (Manifest 'requirements') plus Core-/PHP-Version. @return array<string, ?bool> */
    public static function requirements(string $name): array
    {
        $m = self::available()[$name] ?? null;
        if (!$m) return [];
        $out = [__('Core {req} (installiert: {core})', ['req' => (string) ($m['requires'] ?: '–'), 'core' => CMS_VERSION]) => self::compatible($m)];
        $php = (string) (self::meta($name)['php'] ?? '');
        if (preg_match('~^(>=|>)?\s*([\d.]+)$~', $php, $r)) $out[__('PHP {req} (installiert: {php})', ['req' => $php, 'php' => PHP_VERSION])] = version_compare(PHP_VERSION, $r[2], ($r[1] ?? '') ?: '>=');
        if (is_callable($m['requirements'] ?? null)) {
            try {
                foreach ((array) ($m['requirements'])() as $label => $ok) $out[self::tr($name, (string) $label)] = $ok === null ? null : (bool) $ok;
            } catch (\Throwable $e) {
                error_log('[Erweiterung ' . $name . '] requirements: ' . $e->getMessage());
                $out[__('Prüfung fehlgeschlagen')] = null;
            }
        }
        return $out;
    }

    /** Prüfungen einer aktiven Erweiterung (Extension::health) */
    public static function healthOf(string $name): array
    {
        $x = self::$active[$name] ?? null;
        if (!$x) return [];
        $out = [];
        foreach ($x->healthProviders as $fn) {
            foreach ((array) self::safe($x, 'health', $fn, []) as $label => $ok) $out[(string) $label] = $ok === null ? null : (bool) $ok;
        }
        return $out;
    }

    /** Was eine aktive Erweiterung beiträgt (Menü, Rechte, Funktionen, Befehle, Blöcke, CSP) – für die Übersicht */
    public static function contributions(string $name): array
    {
        $x = self::$active[$name] ?? null;
        if (!$x) return [];
        $blocks = array_keys(array_filter(app()->theme->blocks(), fn($b) => ($b['extension'] ?? '') === $name));
        return ['nav' => array_map(fn($n) => [$n[0], self::tr($name, (string) $n[1]), (string) ($n[5] ?? '')], $x->nav), 'perms' => array_merge([], ...array_values($x->perms)),
            'commands' => array_map(fn($c) => (string) $c[0], $x->commands), 'blocks' => $blocks, 'csp' => (bool) $x->cspProviders,
            'routes' => count($x->routeCallbacks), 'frontend' => (bool) ($x->htmlFilters || $x->footerLinkProviders)];
    }

    /** „Wird verwendet von …“: Manifest 'usage' plus Seiten mit Blöcken der Erweiterung (nur aktive) */
    public static function usage(string $name): array
    {
        $out = [];
        $m = self::available()[$name] ?? [];
        if (is_callable($m['usage'] ?? null)) {
            try {
                $u = ($m['usage'])();
                if (is_string($u) && trim($u) !== '') $out[] = trim($u);
            } catch (\Throwable $e) {
                error_log('[Erweiterung ' . $name . '] usage: ' . $e->getMessage());
            }
        }
        foreach ((self::contributions($name)['blocks'] ?? []) as $type) {
            try {
                $n = (int) app()->db->fetchValue('SELECT COUNT(*) FROM pages WHERE content_published LIKE ? OR content_draft LIKE ?', ['%"type":"' . $type . '"%', '%"type":"' . $type . '"%']);
                if ($n) $out[] = __('Block „{type}“ auf {n} Seite(n)', ['type' => (string) (app()->theme->block($type)['label'] ?? $type), 'n' => $n]);
            } catch (\Throwable) {
            }
        }
        return $out;
    }

    /** CLI: Befehl einer installierten, hier aber nicht aktiven Erweiterung (Manifest 'commands')? → Name der Erweiterung */
    public static function inactiveCommand(string $cmd): ?string
    {
        foreach (self::available() as $n => $m) {
            if (!isset(self::$active[$n]) && in_array($cmd, (array) ($m['commands'] ?? []), true)) return $n;
        }
        return null;
    }

    public static function compatible(array $m): bool
    {
        $req = trim((string) ($m['requires'] ?? ''));
        if ($req === '' || !preg_match('~^(>=|>|<=|<|=|==)?\s*([\d.]+)$~', $req, $r)) return true;
        return version_compare(CMS_VERSION, $r[2], ($r[1] ?? '') ?: '>=');
    }

    /** @return array<string, Extension> */
    public static function active(): array
    {
        return self::$active;
    }

    public static function isActive(string $name): bool
    {
        return isset(self::$active[$name]);
    }

    // ------------------------------------------------------------------ Sammelpunkte für den Core

    /** Routen aller aktiven Erweiterungen (vor der Seiten-Route registrieren) */
    public static function routes(Http\Router $r): void
    {
        // Herkunft je Erweiterung: Routen unter /admin schützt der Router (Anmeldung, Recht, CSRF – siehe Core\Http\Router)
        foreach (self::$active as $x) {
            foreach ($x->routeCallbacks as $cb) $r->scoped($x->name, $cb);
        }
    }

    /**
     * Verwaltungsrouten aktiver Erweiterungen für extensions:list: [name => ['routes' => n, 'perm' => n, 'legacy' => [...],
     * 'denied' => [...], 'no_csrf' => [...], 'public' => [...], 'error' => ?string]] – Routen in einem eigenen Router angemeldet.
     */
    public static function routeReport(): array
    {
        $out = [];
        foreach (self::$active as $x) {
            $r = new Http\Router();
            $err = null;
            try {
                foreach ($x->routeCallbacks as $cb) $r->scoped($x->name, $cb);
            } catch (\Throwable $e) {
                $err = $e->getMessage();
            }
            $rows = $r->meta($x->name);
            $fmt = fn(array $m): string => $m['method'] . ' ' . $m['pattern'];
            $out[$x->name] = ['routes' => count($rows), 'perm' => count(array_filter($rows, fn($m) => $m['perm'] !== null)),
                'legacy' => array_map($fmt, array_values(array_filter($rows, fn($m) => $m['legacy']))),
                'denied' => array_map($fmt, array_values(array_filter($rows, fn($m) => $m['denied']))),
                'no_csrf' => array_map($fmt, array_values(array_filter($rows, fn($m) => !$m['csrf']))),
                'public' => array_map($fmt, array_values(array_filter($rows, fn($m) => $m['public']))), 'error' => $err];
        }
        return $out;
    }

    /**
     * Veraltet (seit Core\AdminPages): Menüeinträge der Erweiterungen [href, label, key, sichtbar] – nur Inhalte/Werkzeuge,
     * $place 'main' (Hauptmenü) oder 'admin' (Administration). Das Layout nutzt AdminPages::nav().
     */
    public static function adminNav(string $place = 'main'): array
    {
        return array_values(array_filter(AdminPages::nav($place), fn($n) => (AdminPages::match($n[0])['source'] ?? '') !== 'core'));
    }

    /** Zusätzliche Rechte-Gruppen: [Gruppe => [recht => Bezeichnung]] */
    public static function permissions(): array
    {
        $out = [];
        foreach (self::$active as $x) {
            foreach ($x->perms as $group => $perms) $out[$group] = array_merge($out[$group] ?? [], $perms);
        }
        return $out;
    }

    /** HTML-Ausgabe der Website durch die Filter aktiver Erweiterungen schicken (Fehler einer Erweiterung brechen die Seite nicht) */
    public static function filterHtml(string $html, array $ctx = []): string
    {
        foreach (self::$active as $x) {
            foreach ($x->htmlFilters as $fn) {
                try {
                    $out = $fn($html, $ctx);
                    if (is_string($out) && $out !== '') $html = $out;
                } catch (\Throwable $e) {
                    error_log('[Erweiterung ' . $x->name . '] htmlFilter: ' . $e->getMessage());
                }
            }
        }
        return $html;
    }

    /**
     * Zusätzliche CSP-Quellen der aktiven Erweiterungen je Direktive. Erlaubt sind nur Hosts/Schemes (https://…, *.host);
     * Schlüsselwörter wie 'unsafe-inline', 'unsafe-eval', data: oder * werden verworfen.
     * @return array<string, list<string>>
     */
    public static function cspSources(): array
    {
        $out = [];
        foreach (self::$active as $x) {
            foreach ($x->cspProviders as $fn) {
                try {
                    foreach ((array) $fn() as $dir => $srcs) {
                        if (!in_array($dir, ['script-src', 'connect-src', 'img-src', 'frame-src', 'media-src', 'style-src', 'font-src'], true)) continue;
                        foreach ((array) $srcs as $s) {
                            $s = trim((string) $s);
                            if (preg_match('~^https://(\*\.)?[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+(:\d+)?(/[\w./-]*)?$~i', $s)
                                || preg_match('~^http://(localhost|127\.0\.0\.1|[a-z0-9-]+\.localhost)(:\d+)?$~i', $s)) {
                                $out[$dir][] = $s;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    error_log('[Erweiterung ' . $x->name . '] csp: ' . $e->getMessage());
                }
            }
        }
        return array_map(fn($l) => array_values(array_unique($l)), $out);
    }

    /** Links der aktiven Erweiterungen für den Fußbereich (Rechtliches): [['label' => …, 'href' => …], …] */
    public static function footerLinks(): array
    {
        $out = [];
        foreach (self::$active as $x) {
            foreach ($x->footerLinkProviders as $fn) {
                try {
                    foreach ((array) $fn() as $l) {
                        if (!empty($l['label']) && isset($l['href'])) $out[] = ['label' => (string) $l['label'], 'href' => (string) $l['href']];
                    }
                } catch (\Throwable $e) {
                    error_log('[Erweiterung ' . $x->name . '] footerLinks: ' . $e->getMessage());
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ Verwaltung, Mediathek, Betrieb

    /** Fehler einer Erweiterung protokollieren statt die Anfrage abzubrechen */
    private static function safe(Extension $x, string $hook, callable $fn, mixed $fallback = null): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log('[Erweiterung ' . $x->name . '] ' . $hook . ': ' . $e->getMessage());
            return $fallback;
        }
    }

    /** <link>/<script>-Tags der aktiven Erweiterungen für eine Verwaltungsansicht (Layout, nur 'self' → CSP unverändert) */
    public static function adminHead(string $view): string
    {
        $out = '';
        foreach (self::$active as $x) {
            foreach ($x->adminAssetProviders as $fn) {
                foreach ((array) self::safe($x, 'adminAssets', fn() => $fn($view), []) as $path) {
                    $path = ltrim((string) $path, '/');
                    if (!preg_match('~^[a-z0-9_./-]+\.(css|js|mjs)$~i', $path) || str_contains($path, '..')) continue;
                    $out .= str_ends_with($path, '.css')
                        ? '<link rel="stylesheet" href="' . e($x->asset($path)) . '">' . "\n"
                        : '<script src="' . e($x->asset($path)) . '"' . (str_ends_with($path, '.mjs') ? ' type="module"' : ' defer') . "></script>\n";
                }
            }
        }
        return $out;
    }

    /** Zeilen für `health`: ['Bezeichnung' => true|false|null] */
    public static function health(): array
    {
        $out = [];
        foreach (self::$active as $x) {
            foreach ($x->healthProviders as $fn) {
                $rows = self::safe($x, 'health', $fn, ['Erweiterung ' . $x->name . ': Prüfung fehlgeschlagen' => false]);
                foreach ((array) $rows as $label => $ok) $out[(string) $label] = $ok === null ? null : (bool) $ok;
            }
        }
        return $out;
    }

    /**
     * Nach der Antwort eines Verwaltungsaufrufs: fällige Arbeiten der Erweiterungen (billige Prüfung jetzt, Arbeit nach
     * fastcgi_finish_request()). Aufruf in AdminController::auth – wie Sources::maybeRun().
     */
    public static function afterAdminResponse(): void
    {
        $jobs = [];
        foreach (self::$active as $x) {
            foreach ($x->afterAdmin as $fn) {
                $job = self::safe($x, 'afterAdminResponse', $fn);
                if (is_callable($job)) $jobs[] = [$x, $job];
            }
        }
        if (!$jobs) return;
        static $registered = false;
        if ($registered) return;
        $registered = true;
        register_shutdown_function(static function () use ($jobs): void {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            ignore_user_abort(true);
            foreach ($jobs as [$x, $job]) self::safe($x, 'afterAdminResponse (Arbeit)', $job);
        });
    }

    /**
     * Hört eine aktive Erweiterung auf das Ereignis? – teure Angaben (z. B. Eintrag neu laden) nur dann berechnen.
     * $event: Name ('page.saved') oder Klasse (PageSaved::class) – beide Schreibweisen zählen.
     */
    public static function listens(string $event): bool
    {
        $keys = self::eventKeys($event);
        foreach (self::$active as $x) {
            foreach ($keys as $k) if (!empty($x->listeners[$k])) return true;
        }
        return false;
    }

    /**
     * Ereignis an alle Erweiterungen melden (Fehler einer Erweiterung brechen den Ablauf nicht ab).
     * Typisiert: emit(new Events\PageSaved($page, $uid)) – Listener auf die Klasse bekommen das Objekt, Listener auf den Namen
     * je nach Typ des ersten Parameters das Objekt oder die Argumente der Altform (Event::legacyArgs()).
     * Ohne Typ (media.*, inbox.*): emit('media.deleted', $m).
     */
    public static function emit(string|Events\Event $event, mixed ...$args): void
    {
        if (is_string($event)) {
            foreach (self::$active as $x) {
                foreach ($x->listeners[$event] ?? [] as $fn) self::safe($x, 'on ' . $event, fn() => $fn(...$args));
            }
            return;
        }
        $name = $event->name();
        foreach (self::$active as $x) {
            foreach ($x->listeners[$event::class] ?? [] as $fn) self::safe($x, 'on ' . $name, fn() => $fn($event));
            foreach ($x->listeners[$name] ?? [] as $fn) {
                self::safe($x, 'on ' . $name, fn() => self::wantsEvent($fn, $event) ? $fn($event) : $fn(...$event->legacyArgs()));
            }
        }
    }

    /** Schlüssel eines Ereignisses in Extension::$listeners: Name und Klasse */
    private static function eventKeys(string $event): array
    {
        $event = ltrim($event, '\\');
        $class = Events\Event::classFor($event);
        if (!$class) return [$event];
        return array_values(array_unique([$event, $class, $class::NAME]));
    }

    /** Erwartet der Listener (registriert mit dem Namen) das Ereignis-Objekt? – am Typ des ersten Parameters */
    private static function wantsEvent(callable $fn, Events\Event $event): bool
    {
        static $cache = null;
        $cache ??= new \WeakMap();
        $c = \Closure::fromCallable($fn);
        if (isset($cache[$c])) return $cache[$c];
        $type = ((new \ReflectionFunction($c))->getParameters()[0] ?? null)?->getType();
        $want = false;
        foreach ($type instanceof \ReflectionUnionType ? $type->getTypes() : [$type] as $t) {
            if ($t instanceof \ReflectionNamedType && !$t->isBuiltin() && is_a($event, $t->getName())) $want = true;
        }
        return $cache[$c] = $want;
    }

    /** Filter unter „Prüfen“: ['schlüssel' => ['label', 'icon', 'where', 'params', 'kind', 'extension']] */
    public static function mediaChecks(): array
    {
        $out = [];
        foreach (self::$active as $x) {
            foreach ($x->mediaCheckProviders as $fn) {
                foreach ((array) self::safe($x, 'mediaChecks', $fn, []) as $key => $c) {
                    if (!preg_match('~^[a-z][a-z0-9_]{1,40}$~', (string) $key) || empty($c['where']) || empty($c['label'])) continue;
                    $out[(string) $key] = ['label' => (string) $c['label'], 'icon' => (string) ($c['icon'] ?? 'warning'), 'where' => (string) $c['where'],
                        'params' => array_values((array) ($c['params'] ?? [])), 'kind' => $c['kind'] ?? null, 'extension' => $x->name];
                }
            }
        }
        return $out;
    }

    /** Zusatzangaben je Datei für Media::toJson: [name => array] */
    public static function mediaJson(array $m): array
    {
        $out = [];
        foreach (self::$active as $x) {
            foreach ($x->mediaJsonProviders as $fn) {
                $v = self::safe($x, 'mediaJson', fn() => $fn($m));
                if (is_array($v)) $out[$x->name] = $v;
            }
        }
        return $out;
    }

    /** Zusätzliche Dateitypen der Mediathek: [mime => ['ext', 'magic', 'label']] */
    public static function mediaTypes(): array
    {
        $out = [];
        foreach (self::$active as $x) $out += $x->mediaTypeDefs;
        return $out;
    }

    /** Bild-ID eines Vorschaubilds für ein Video (erste Erweiterung mit Ergebnis) */
    public static function poster(array $m): ?int
    {
        foreach (self::$active as $x) {
            foreach ($x->posterProviders as $fn) {
                $id = self::safe($x, 'mediaPoster', fn() => $fn($m));
                if (is_int($id) && $id > 0) return $id;
            }
        }
        return null;
    }

    /** Kapitel der Erweiterungen für 'manual' bzw. 'technical' (Dateien liegen im Erweiterungsordner) */
    public static function docs(string $book): array
    {
        $out = [];
        foreach (self::$active as $x) {
            foreach ($x->docChapters[$book] ?? [] as $key => $spec) {
                if (!is_array($spec) || !is_file((string) ($spec['file'] ?? ''))) continue;
                $out[(string) $key] = $spec;
            }
        }
        return $out;
    }

    /**
     * Beiträge zur Übersicht (Extension::dashboard): ['tiles' => [...], 'cards' => ['x-{erweiterung}-{schlüssel}' => spec]].
     * 'render' wird in einen fehlertoleranten Aufruf verpackt (Fehler → leere Karte, Eintrag im Fehlerprotokoll).
     */
    public static function dashboard(array $user): array
    {
        $tiles = $cards = [];
        foreach (self::$active as $x) {
            foreach ($x->dashboardProviders as $fn) {
                $d = (array) self::safe($x, 'dashboard', fn() => $fn($user), []);
                foreach ((array) ($d['tiles'] ?? []) as $t) {
                    if (!is_array($t) || trim((string) ($t['label'] ?? '')) === '' || !isset($t['value'])) continue;
                    $tiles[] = $t + ['extension' => $x->name];
                }
                foreach ((array) ($d['cards'] ?? []) as $key => $c) {
                    if (!is_array($c) || !preg_match('~^[a-z][a-z0-9_-]{1,40}$~', (string) $key) || !is_callable($c['render'] ?? null)) continue;
                    $render = $c['render'];
                    $cards['x-' . preg_replace('~[^a-z0-9-]~', '-', strtolower($x->name)) . '-' . str_replace('_', '-', (string) $key)] = [
                        'title' => (string) ($c['title'] ?? $key), 'icon' => (string) ($c['icon'] ?? 'puzzle-piece'),
                        'size' => in_array($c['size'] ?? '', ['third', 'half', 'two-thirds', 'full'], true) ? $c['size'] : 'half',
                        'lazy' => !empty($c['lazy']), 'ttl' => max(0, min(3600, (int) ($c['ttl'] ?? 300))), 'extension' => $x->name,
                        'render' => fn(): string => (string) self::safe($x, 'dashboard ' . $key, $render, ''),
                    ];
                }
            }
        }
        return ['tiles' => $tiles, 'cards' => $cards];
    }

    /**
     * Seitenbaum: Hinweise und Kontextmenü-Einträge aktiver Erweiterungen für eine Seite (Extension::pageList).
     * @return array{badges: list<array{label:string, title:string, tone:string}>, actions: list<array{label:string, href:string}>}
     */
    public static function pageList(array $page): array
    {
        $badges = $actions = [];
        foreach (self::$active as $x) {
            foreach ($x->pageListProviders as $fn) {
                $d = (array) self::safe($x, 'pageList', fn() => $fn($page), []);
                foreach ((array) ($d['badges'] ?? []) as $b) {
                    if (!is_array($b) || trim((string) ($b['label'] ?? '')) === '') continue;
                    $badges[] = ['label' => (string) $b['label'], 'title' => (string) ($b['title'] ?? ''),
                        'tone' => in_array($b['tone'] ?? '', ['ok', 'warn', 'info', 'muted'], true) ? (string) $b['tone'] : 'info'];
                }
                foreach ((array) ($d['actions'] ?? []) as $a) {
                    $href = (string) ($a['href'] ?? '');
                    // nur Adressen dieser Installation (relativ), kein javascript:
                    if (!is_array($a) || trim((string) ($a['label'] ?? '')) === '' || !str_starts_with($href, '/') || str_starts_with($href, '//')) continue;
                    $actions[] = ['label' => (string) $a['label'], 'href' => $href];
                }
            }
        }
        return ['badges' => $badges, 'actions' => $actions];
    }

    /** Seiteneinstellungen: Karten aktiver Erweiterungen für die Seitenleiste (Extension::pagePanel) – fertiges HTML */
    public static function pagePanels(array $page): string
    {
        $out = '';
        foreach (self::$active as $x) {
            foreach ($x->pagePanelProviders as $fn) $out .= (string) self::safe($x, 'pagePanel', fn() => $fn($page), '');
        }
        return $out;
    }

    /**
     * Werkzeugleiste der Website: Menüeinträge, Skripte und Hinweis zum Veröffentlichen aktiver Erweiterungen (Extension::toolbar).
     * @return array{items: list<array{label:string, hint:string, icon:string, href:?string, data:array<string,string>}>, scripts: list<array{src:string, module:bool}>, notes: list<string>}
     */
    public static function toolbar(array $bar): array
    {
        $items = $scripts = $notes = [];
        foreach (self::$active as $x) {
            foreach ($x->toolbarProviders as $fn) {
                $d = (array) self::safe($x, 'toolbar', fn() => $fn($bar), []);
                foreach ((array) ($d['items'] ?? []) as $it) {
                    if (!is_array($it) || trim((string) ($it['label'] ?? '')) === '') continue;
                    $href = isset($it['href']) ? (string) $it['href'] : null;
                    if ($href !== null && (!str_starts_with($href, '/') || str_starts_with($href, '//'))) continue;
                    $data = [];
                    foreach ((array) ($it['data'] ?? []) as $k => $v) {
                        if (preg_match('~^[a-z][a-z0-9-]{0,40}$~', (string) $k)) $data[(string) $k] = (string) $v;
                    }
                    $items[] = ['label' => (string) $it['label'], 'hint' => (string) ($it['hint'] ?? ''), 'icon' => (string) ($it['icon'] ?? 'puzzle-piece'),
                        'href' => $href, 'data' => $data];
                }
                foreach ((array) ($d['scripts'] ?? []) as $path) {
                    $path = ltrim((string) $path, '/');
                    if (preg_match('~^[a-z0-9_./-]+\.(js|mjs)$~i', $path) && !str_contains($path, '..')) {
                        $scripts[] = ['src' => $x->asset($path), 'module' => str_ends_with($path, '.mjs')];
                    }
                }
                if (trim((string) ($d['publishNote'] ?? '')) !== '') $notes[] = trim((string) $d['publishNote']);
            }
        }
        return ['items' => $items, 'scripts' => $scripts, 'notes' => $notes];
    }

    /**
     * Einstellungen einer Erweiterung für eine Eingangs-Tabelle (Extension::inbox) – erste zuständige Erweiterung gewinnt.
     * @return array{statuses?: array, info?: callable, guard?: callable, direct_form?: bool, extension?: string}
     */
    public static function inbox(array $t): array
    {
        foreach (self::$active as $x) {
            foreach ($x->inboxProviders as $fn) {
                $c = self::safe($x, 'inbox', fn() => $fn($t));
                if (is_array($c)) return $c + ['extension' => $x->name];
            }
        }
        return [];
    }

    /** CLI-Befehle: [name => [beschreibung, callable]] */
    public static function commands(): array
    {
        $out = [];
        foreach (self::$active as $x) $out += $x->commands;
        return $out;
    }
}
