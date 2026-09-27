<?php
declare(strict_types=1);

namespace Core;

use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Router;

/**
 * Minimaler Service-Container. Zugriff überall per app().
 */
final class App
{
    private static ?App $instance = null;

    /** Aktuelle Website (Multi-Site) */
    public Site $site;
    public Database $db;
    public Settings $settings;
    public Theme $theme;
    /** Alias für app()->theme – das aktive Kit (Klasse und Eigenschaft heißen aus Kompatibilitätsgründen weiter „theme“) */
    public Theme $kit { get => $this->theme; }
    public Session $session;
    public Auth $auth;
    public ?Request $request = null;

    /** Aktuell gerenderte Seite (für Link-Auflösung, Navigation) */
    public ?array $currentPage = null;
    /** Bearbeitungsmodus aktiv (Inline-Editing) */
    public bool $editing = false;
    /** Aktueller Datensatz auf Detailseiten: ['table' => array, 'entry' => array] */
    public ?array $entry = null;
    /** Detailseite: Felder des Eintrags direkt auf der Website bearbeiten (Core\Data\EntryEdit) */
    public bool $entryEdit = false;
    /** Angemeldete Redaktion (nicht Live-Ansicht): Stift je Eintrag in Datenlisten, „+ Neuer Eintrag“ */
    public bool $dataEdit = false;
    /** Vorschau in der Verwaltung: Listenfeld → Index des Eintrags, der zuerst gezeigt werden soll (preview_focus()) */
    public array $previewFocus = [];
    /** Inhaltssprache der aktuellen Anfrage (null = Standardsprache) */
    public ?string $lang = null;

    private function __construct(public readonly Config $config) {}

    public static function boot(Config $config, ?Site $site = null): self
    {
        $app = new self($config);
        self::$instance = $app;
        $app->site = $site ?? new Site(Site::DEFAULT);
        foreach ([$app->site->storage('database'), $app->site->mediaDir()] as $dir) {
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
        }

        $app->db = new Database($config->get('db'));
        $app->db->migrate();

        $app->settings = new Settings($app->db);
        // Theme: Grundeinstellung → Vorgabe der Website → erstes installiertes (der Core kennt keine Theme-Namen)
        $theme = (string) $app->settings->get('sys.theme');
        $allowed = $app->site->allowedThemes();
        $cfgKit = (string) ($config->get('kit') ?: $config->get('theme'));   // Konfiguration: 'kit' (neu) oder 'theme'
        $app->theme = new Theme(isset($allowed[$theme]) ? $theme : ($cfgKit !== '' && isset($allowed[$cfgKit]) ? $cfgKit : $app->site->defaultTheme()));
        $app->session = new Session($config->get('session'));
        $app->auth = new Auth($app->db, $app->session);
        // Erweiterungen dieser Website (config: 'extensions')
        Extensions::boot();
        // KI (Symfony AI, config 'ai'): semantische Suche der Wissensdatenbank einsetzen, falls konfiguriert
        AI\Ai::boot();

        // Erststart: Inhalte des Themes einspielen
        if (!$app->db->fetchValue('SELECT COUNT(*) FROM pages')) {
            (new Seeder($app))->run();
        }
        // Datentabellen: neue Systemspalten (Mehrsprachigkeit) einmalig nachtragen
        if ((int) $app->settings->get('sys.data_schema', 0) < 2) {
            foreach ($app->db->fetchAll('SELECT handle FROM data_tables') as $t) {
                $app->db->ensureColumns('data_' . $t['handle'], ['lang' => 'VARCHAR(10) NULL', 'translation_group' => 'INT NULL']);
            }
            $app->settings->set('sys.data_schema', 2);
        }
        // Symbole der Datentabellen: Unicode-Zeichen → Symbolnamen (Core\Icons, einmalig; alte Werte in sys.icons_legacy)
        if ((int) $app->settings->get('sys.icons', 0) < 1 && Icons::catalog()['icons']) {
            try {
                Icons::migrateTables();
                $app->settings->set('sys.icons', 1);
            } catch (\Throwable $e) {
                error_log('[icons] migrate: ' . $e->getMessage());
            }
        }
        // Online-Anfragen → Eingangs-Tabellen (einmalig, idempotent): Theme-Formulare als verschlüsselte Eingänge anlegen,
        // Alt-Anfragen übernehmen. Auf der Kommandozeile mit Ausgabe über `migrate` bzw. `inbox:migrate`.
        if (PHP_SAPI !== 'cli' && Data\Inbox::needsMigration()) {
            try {
                Data\Inbox::migrate();
            } catch (\Throwable $e) {
                error_log('[inbox] migrate: ' . $e->getMessage());
            }
        }
        // Seitenpfade nachtragen (Aktualisierung von Installationen ohne Seitenbaum)
        if ($app->db->fetchValue('SELECT COUNT(*) FROM pages WHERE path IS NULL')) {
            Pages::rebuildPaths();
        }

        return $app;
    }

    public static function get(): self
    {
        if (!self::$instance) {
            throw new \RuntimeException('App nicht gebootet');
        }
        return self::$instance;
    }

    public function key(): string
    {
        $key = (string) $this->config->get('app_key');
        if (strlen($key) < 32) {
            throw new \RuntimeException('app_key fehlt in config/config.local.php');
        }
        return $key;
    }

    /** Passwortschutz für Staging (config 'staging_auth' => ['user' => …, 'pass' => …]) */
    private function stagingGate(Request $request): ?Response
    {
        $auth = (array) $this->config->get('staging_auth', []);
        if (environment() === 'production' || empty($auth['user']) || empty($auth['pass'])
            || str_starts_with($request->path, '/api/') || $request->path === '/mcp' || $request->path === '/health'
            // WebDAV-Erweiterungen (CalDAV/CardDAV) melden sich selbst per HTTP Basic an
            || $request->path === '/dav' || str_starts_with($request->path, '/dav/') || str_starts_with($request->path, '/.well-known/')) {
            return null;
        }
        $user = $request->server['PHP_AUTH_USER'] ?? null;
        $pass = $request->server['PHP_AUTH_PW'] ?? null;
        $hdr = (string) ($request->server['HTTP_AUTHORIZATION'] ?? $request->server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($user === null && str_starts_with($hdr, 'Basic ')) {
            [$user, $pass] = array_pad(explode(':', (string) base64_decode(substr($hdr, 6)), 2), 2, '');
        }
        if (is_string($user) && hash_equals((string) $auth['user'], $user) && hash_equals((string) $auth['pass'], (string) $pass)) {
            return null;
        }
        return new Response('Staging – Zugang nur mit Passwort.', 401, ['Content-Type' => 'text/plain; charset=utf-8',
            'WWW-Authenticate' => 'Basic realm="Staging", charset="UTF-8"', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function handle(Request $request): Response
    {
        $this->request = $request;
        $router = new Router();
        (require ROOT . '/app/routes.php')($router);

        // Landing-Domains (Core\Landings): Verwaltung nur auf der Hauptdomain – dort gelten Sitzung, Cookies und Passkeys (RP-ID = Domain)
        if ($request->isAdminPath() && Landings::current()) {
            return Http\Response::redirect(Landings::mainOrigin() . url($request->path), 302);
        }

        // Sessions nur für Admin-Routen oder wenn bereits eingeloggt
        // (Besucher erhalten KEINE Cookies).
        if ($request->isAdminPath() || $this->session->hasCookie()) {
            $this->session->start($request->isSecure());
        }
        // Oberflächensprache: Benutzer → Grundeinstellung → Deutsch
        if ($request->isAdminPath() || $this->session->hasCookie()) {
            $u = $this->auth->user();
            I18n::setLocale((string) (($u['locale'] ?? '') ?: ($this->settings->get('sys.admin_locale') ?: I18n::SOURCE)));
        }

        // Staging: Website nur mit Passwort (HTTP Basic Auth; API/MCP nutzen ihre Tokens)
        if ($gate = $this->stagingGate($request)) {
            return $gate;
        }
        try {
            if (!Features::allowsPath($request->path)) {
                throw new Http\HttpException(404);
            }
            return $router->dispatch($request);
        } catch (Http\Controllers\Admin\RedirectException $e) {
            return $request->wantsJson()
                ? Http\Response::json(['ok' => false, 'error' => 'Nicht angemeldet.'], 401)
                : Http\Response::redirect($e->to);
        } catch (Http\HttpException $e) {
            if (str_starts_with($request->path, '/api/') || $request->path === '/mcp') {
                return Http\Controllers\ApiController::fail(new Api\ApiError($e->getCode() ?: 404,
                    $e->getCode() === 405 ? 'Methode nicht erlaubt.' : 'Unbekannter API-Endpunkt. Übersicht: ' . absolute_url('/api/v1/openapi.json')));
            }
            // Keine Seite, keine Route: Weiterleitungen (Core\Redirects) – echte Seiten gehen damit immer vor
            if ($e->getCode() === 404 && ($to = Redirects\Redirects::handle404($request))) {
                return $to;
            }
            return (new Http\Controllers\SiteController())->error($e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            error_log((string) $e);
            if ($this->config->get('debug')) {
                return new Response('<pre>' . e((string) $e) . '</pre>', 500);
            }
            return (new Http\Controllers\SiteController())->error(500);
        }
    }
}
