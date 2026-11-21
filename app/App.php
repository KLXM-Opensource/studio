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
    /** Modus „Bearbeiten“ einer Detailseite: Inhaltsblöcke des Eintrags als Platzhalter [data-cms-content-slot] (Editor kommt dorthin) */
    public bool $contentSlot = false;
    /** Detailseite im Modus „Inhalt“ (?edit=inhalt): Editor für die Inhaltsblöcke des Eintrags {table, entry, field, def, at} – Core\Data\EntryBlocks */
    public ?array $contentEdit = null;
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
        AdminPath::reset();   // Verwaltungsadresse je Website neu lesen
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

        // Erststart: Inhalte des Kits einspielen – sobald Kit und Startinhalte entschieden sind (Core\Onboarding;
        // sonst wählt die Administration beides im Willkommen-Bildschirm)
        // KLXM_NO_SEED: keine Startinhalte (z. B. backup:restore-all spielt gleich die gesicherte Datenbank ein)
        if (!getenv('KLXM_NO_SEED') && !$app->db->fetchValue('SELECT COUNT(*) FROM pages') && ($seedMode = Onboarding::mode($app)) !== null) {
            (new Seeder($app))->run($seedMode);
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
            // Ohne Staging-Passwort nur die per Schlüssel angemeldeten Schnittstellen (öffentliche /api/live, /api/chat … bleiben geschützt)
            || str_starts_with($request->path, '/api/v1/') || $request->path === '/mcp' || $request->path === '/health'
            // WebDAV-Erweiterungen (CalDAV/CardDAV) melden sich selbst per HTTP Basic an
            || $request->path === '/dav' || str_starts_with($request->path, '/dav/') || str_starts_with($request->path, '/.well-known/')
            // Abo-Tokens für Kalender/Kontakte (Core\Data\Subscriptions) sind selbst die Anmeldung
            || (bool) preg_match('~^/(kalender|vcard)/[^/]+/abo/[^/]+\.(ics|vcf)$~', $request->path)) {
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

    /** Weiterleitung auf die Netzwerk-Domain; mit Sitzung auf dieser Domain über den Domainwechsel (bleibt angemeldet) */
    private function toNetworkHost(Request $request, string $to): Response
    {
        $noStore = fn(Response $res) => $res->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
        if (!$this->session->hasCookie()) return $noStore(Response::redirect($to, 302));
        try {
            $this->session->start($request->isSecure());
            $u = $this->auth->user();
            $nh = (string) NetworkHost::get();
            if (!$u || !in_array($nh, HostHandover::targets(), true)) return $noStore(Response::redirect($to, 302));
            $limiter = new RateLimiter($this->db);
            $lk = 'handover-auto:' . (int) $u['id'];
            if ($limiter->tooMany($lk, 30, 600)) return $noStore(Response::redirect($to, 302));
            $limiter->hit($lk);
            $q = (string) parse_url((string) ($request->server['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
            $path = HostHandover::safePath($request->path . ($q !== '' ? '?' . $q : '')) ?: HostHandover::safePath($request->path);
            $token = HostHandover::issue((int) $u['id'], $nh, (string) ($request->server['HTTP_USER_AGENT'] ?? ''), $path ?: '/admin');
            Network\Network::log('handover.issue', $this->site->key, (string) ($u['email'] ?? ''), strtolower($request->host()) . ' → ' . $nh . ' (Netzwerk-Domain)');
            return $noStore(Response::redirect(NetworkHost::origin() . url(HostHandover::PATH) . '?t=' . rawurlencode($token), 302));
        } catch (\Throwable $e) {
            error_log('[network-host] ' . $e->getMessage());
            return $noStore(Response::redirect($to, 302));
        }
    }

    public function handle(Request $request): Response
    {
        $router = new Router();
        (require ROOT . '/app/routes.php')($router);
        // Eigene Verwaltungsadresse (Core\AdminPath): intern weiter /admin; /admin direkt nur angemeldet (sonst 404, kein Cookie)
        $direct = false;
        if (AdminPath::custom()) {
            if (($in = AdminPath::toInternal($request->path)) !== null) {
                $request = $request->withPath($in);
            } elseif ($request->isAdminPath() && !AdminPath::directAllowed($request->path, $router, $request->method)) {
                $direct = true;
            }
        }
        $this->request = $request;

        // Weitere Domains auf die Hauptadresse (System → Domain, config 'redirect_to_primary'; Core\Domains)
        if ($to = Domains::redirectTarget($this->site, $request->method, $request->host(), $request->path,
            (string) ($request->server['REQUEST_URI'] ?? '/'), $request->isSecure())) {
            return Http\Response::redirect($to, 301);
        }

        // Netzwerk-Domain (Core\NetworkHost): Seitenaufrufe der Verwaltung auf anderen Domains der Netzwerk-Website dorthin –
        // angemeldet mit Einmal-Token (Core\HostHandover), damit die Sitzung mitkommt
        if ($to = NetworkHost::redirectTarget($request, $router, $direct)) {
            return $this->toNetworkHost($request, $to);
        }

        // Landing-Domains (Core\Landings): Verwaltung nur auf der Hauptdomain – dort gelten Sitzung, Cookies und Passkeys (RP-ID = Domain)
        if ($request->isAdminPath() && Landings::current()) {
            return Http\Response::redirect(Landings::mainOrigin() . url($request->path), 302);
        }

        // Sessions nur für Admin-Routen oder wenn bereits eingeloggt
        // (Besucher erhalten KEINE Cookies).
        if (($request->isAdminPath() && !$direct) || $this->session->hasCookie()) {
            $this->session->start($request->isSecure());
        }
        // Oberflächensprache: Benutzer → Grundeinstellung → Deutsch
        if (($request->isAdminPath() && !$direct) || $this->session->hasCookie()) {
            $u = $this->auth->user();
            I18n::setLocale((string) (($u['locale'] ?? '') ?: ($this->settings->get('sys.admin_locale') ?: I18n::SOURCE)));
        }

        // Staging: Website nur mit Passwort (HTTP Basic Auth; API/MCP nutzen ihre Tokens)
        if ($gate = $this->stagingGate($request)) {
            return $gate;
        }
        // Zeitgesteuert veröffentlichen: fällige „Online ab“/„Offline ab“ vor jeder Auslieferung ausführen (Core\Schedule, ohne Cron)
        Schedule::tick();
        try {
            if (!Features::allowsPath($request->path)) {
                throw new Http\HttpException(404);
            }
            if ($direct && !($this->session->hasCookie() && $this->auth->user())) {
                throw new Http\HttpException(404);
            }
            // Netzwerk-Bereich (Core\Network\Area): umgezogene Seiten leiten weiter, Netzwerk-Seiten nur auf der Netzwerk-Website
            if ($request->isAdminPath() && ($to = Network\Area::redirect($request))) return Http\Response::redirect($to[0], $to[1]);
            return $router->dispatch($request);
        } catch (Http\Controllers\Admin\RedirectException $e) {
            return $request->wantsJson()
                ? Http\Response::fail('Nicht angemeldet.', 401)
                : Http\Response::redirect($e->to);
        } catch (Http\HttpException $e) {
            // Bearbeitungssperre (Core\EditLock) bei JSON-Aufrufen (Editor, Seitenleisten): Meldung statt Fehlerseite
            if ($e instanceof EditLocked && !str_starts_with($request->path, '/api/') && $request->wantsJson()) {
                return Http\Response::json(['ok' => false, 'locked' => true, 'error' => $e->getMessage()], 423);
            }
            if (str_starts_with($request->path, '/api/') || $request->path === '/mcp') {
                return Http\Controllers\ApiController::fail(new Api\ApiError($e->getCode() ?: 404,
                    $e->getCode() === 405 ? 'Methode nicht erlaubt.' : 'Unbekannter API-Endpunkt. Übersicht: ' . absolute_url('/api/v1/openapi.json')));
            }
            // Umbenannte Mediendatei (Media::rename): alte Adresse → aktuelle (301) – unabhängig von der Funktion „Weiterleitungen“
            if ($e->getCode() === 404 && in_array($request->method, ['GET', 'HEAD'], true) && ($to = Media::legacyRedirect($request->path))) {
                return Response::redirect($to, 301)->header('Cache-Control', 'public, max-age=3600');
            }
            // Keine Seite, keine Route: Weiterleitungen (Core\Redirects) – echte Seiten gehen damit immer vor
            if ($e->getCode() === 404 && ($to = Redirects\Redirects::handle404($request))) {
                return $to;
            }
            // Typische Angriffsadresse (/wp-login.php, /.env …) ohne Weiterleitung → Sicherheits-Log für fail2ban
            if ($e->getCode() === 404 && SecurityLog::isProbe($request->path)) SecurityLog::write('probe');
            return (new Http\Controllers\SiteController())->error($e->getCode(), $e->getMessage());
        } catch (\Throwable $e) {
            error_log((string) $e);
            // Fehler aus dem Code einer Erweiterung (Route, Block, Vorlage): Erweiterung und Datei nennen und für die Verwaltung merken
            $ext = null;
            try {
                if ($owner = ExtensionError::owner($e)) $ext = Extensions::failed($owner[0], $owner[1], 'runtime', $e);
            } catch (\Throwable) {
            }
            if ($this->config->get('debug')) {
                return new Response('<pre>' . e(($ext ? $ext->getMessage() . "\n\n" : '') . (string) $e) . '</pre>', 500);
            }
            // Angemeldete Redaktion bzw. Entwicklungsumgebung: Meldung mit Erweiterung und Datei statt einer anonymen 500
            // (Kits zeigen $message nicht – die Zeile kommt direkt hinter <body>, ohne style-Attribut wegen CSP)
            $show = $ext && (environment() === 'development' || $this->auth->check());
            $res = (new Http\Controllers\SiteController())->error(500, $show ? $ext->getMessage() : '');
            if ($show && str_contains($res->body, '<body')) {
                $note = '<p class="cms-ext-error" role="alert"><strong>' . e(__('Fehler in einer Erweiterung')) . ':</strong> ' . e($ext->getMessage()) . '</p>';
                $res->body = (string) preg_replace('~(<body\b[^>]*>)~i', '$1' . str_replace(['\\', '$'], ['\\\\', '\\$'], $note), $res->body, 1);
            }
            return $res;
        }
    }
}
