<?php
declare(strict_types=1);

namespace Core\Http;

use Core\Http\Controllers\Admin\AdminController;

/**
 * Routen. Der Core meldet seine Routen in app/routes.php an (Controller prüfen Anmeldung, Recht und CSRF selbst mit auth()).
 *
 * Routen von Erweiterungen (Extensions::routes → scoped()) unter /admin sind geschützt, bevor der Handler läuft:
 *   $r->get('/admin/kalender', [C::class, 'index'], 'calendar.edit');                 Recht als dritte Angabe
 *   $r->post('/admin/kalender/sync', [C::class, 'sync'], ['perm' => 'calendar.edit']);  Anmeldung + Recht + CSRF (Nicht-GET)
 *   $r->post('/admin/kalender/hook', $fn, ['perm' => 'calendar.edit', 'csrf' => false]);   benannte Ausnahme: ohne CSRF
 *   $r->get('/admin/kalender/status', $fn, ['public' => true]);                         benannte Ausnahme: ohne Anmeldung
 * Ohne Recht: in der Entwicklung (environment development bzw. debug) Fehler beim Anmelden der Route, sonst 403.
 * Rückwärtskompatibel (Altform): Handler [Klasse, Methode] mit einem Controller auf Basis von AdminController prüfen selbst
 * (auth()) – sie laufen weiter, der Core prüft zusätzlich Anmeldung und CSRF; extensions:list meldet sie.
 */
final class Router
{
    /** Methoden ohne CSRF-Prüfung */
    private const SAFE = ['GET', 'HEAD', 'OPTIONS'];

    private array $routes = [];
    /** @var list<array> Angaben je Route von Erweiterungen (extensions:list, Selbsttest) */
    private array $meta = [];
    /** Erweiterung, deren Routen gerade angemeldet werden (scoped()) */
    private ?string $owner = null;

    /** $strict: Routen ohne Recht als Fehler melden (null = nach Umgebung, siehe strict()) – für Selbsttests */
    public function __construct(private readonly ?bool $strict = null) {}

    public function get(string $pattern, callable|array $handler, array|string $opts = []): void
    {
        $this->add('GET', $pattern, $handler, $opts);
    }

    public function post(string $pattern, callable|array $handler, array|string $opts = []): void
    {
        $this->add('POST', $pattern, $handler, $opts);
    }

    public function put(string $pattern, callable|array $handler, array|string $opts = []): void
    {
        $this->add('PUT', $pattern, $handler, $opts);
    }

    public function patch(string $pattern, callable|array $handler, array|string $opts = []): void
    {
        $this->add('PATCH', $pattern, $handler, $opts);
    }

    public function delete(string $pattern, callable|array $handler, array|string $opts = []): void
    {
        $this->add('DELETE', $pattern, $handler, $opts);
    }

    /** Alle HTTP-Methoden (z. B. WebDAV: PROPFIND, REPORT, MKCALENDAR … für Erweiterungen wie „dav“) */
    public function any(string $pattern, callable|array $handler, array|string $opts = []): void
    {
        $this->add('*', $pattern, $handler, $opts);
    }

    /**
     * $opts (nur für Routen von Erweiterungen unter /admin wirksam): Recht als Zeichenkette oder
     * ['perm' => 'recht', 'csrf' => false (Ausnahme), 'public' => true (Ausnahme: ohne Anmeldung und Recht)].
     */
    public function add(string $method, string $pattern, callable|array $handler, array|string $opts = []): void
    {
        if ($this->owner !== null && self::isAdminPath($pattern)) {
            $handler = $this->guard($method, $pattern, $handler, is_string($opts) ? ['perm' => $opts] : $opts);
        }
        // {name} → benannte Gruppe, {name*} → inkl. Schrägstriche
        $regex = preg_replace_callback('/\{(\w+)(\*)?\}/', fn($m) =>
            '(?P<' . $m[1] . '>' . (isset($m[2]) ? '.+' : '[^/]+') . ')', $pattern);
        $this->routes[] = [$method, '#^' . $regex . '$#u', $handler];
    }

    /** Routen einer Erweiterung anmelden: $register() läuft mit $owner als Herkunft (Schutz für /admin, Angaben für extensions:list) */
    public function scoped(string $owner, callable $register): void
    {
        $prev = $this->owner;
        $this->owner = $owner;
        try {
            $register($this);
        } finally {
            $this->owner = $prev;
        }
    }

    /** Angaben zu den Routen von Erweiterungen: [owner, method, pattern, perm, csrf, public, legacy, denied] – optional nur einer Herkunft */
    public function meta(?string $owner = null): array
    {
        return $owner === null ? $this->meta : array_values(array_filter($this->meta, fn($m) => $m['owner'] === $owner));
    }

    public static function isAdminPath(string $pattern): bool
    {
        return $pattern === '/admin' || str_starts_with($pattern, '/admin/');
    }

    /** Entwicklung (environment development oder debug): Fehler früh und laut */
    public static function strict(): bool
    {
        try {
            return environment() === 'development' || (bool) app()->config->get('debug');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Schutz vor dem Handler: Anmeldung (außer public), Recht, CSRF bei Nicht-GET (außer csrf false) */
    private function guard(string $method, string $pattern, callable|array $handler, array $opts): \Closure
    {
        $perm = isset($opts['perm']) && is_string($opts['perm']) && trim($opts['perm']) !== '' ? trim($opts['perm']) : null;
        $public = !empty($opts['public']);
        $csrf = ($opts['csrf'] ?? true) !== false;
        // Altform: Controller auf Basis von AdminController prüft Recht selbst (auth()) – weiter erlaubt, wird gemeldet
        $legacy = !$public && $perm === null && is_array($handler) && is_string($handler[0] ?? null) && is_subclass_of($handler[0], AdminController::class);
        $denied = !$public && $perm === null && !$legacy;
        $this->meta[] = ['owner' => $this->owner, 'method' => $method, 'pattern' => $pattern, 'perm' => $perm, 'csrf' => $csrf,
            'public' => $public, 'legacy' => $legacy, 'denied' => $denied];
        if ($denied) {
            $msg = '[' . $this->owner . '] Route ' . $method . ' ' . $pattern . ' unter /admin ohne Recht (perm) – abgelehnt.';
            if ($this->strict ?? self::strict()) throw new \LogicException($msg . " Bitte z. B. \$r->get('$pattern', …, 'recht.name') angeben.");
            return static function () use ($msg): never {
                error_log($msg);
                throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
            };
        }
        return static function (Request $req, mixed ...$params) use ($handler, $perm, $public, $csrf): mixed {
            $checkCsrf = $csrf && !in_array($req->method, self::SAFE, true);
            if ($public) {
                if ($checkCsrf && !\Core\Csrf::valid($req)) throw new HttpException(419, __('Sitzung abgelaufen – bitte Seite neu laden.'));
            } else {
                AdminController::routeGuard($req, $perm ?? false, $checkCsrf);
            }
            if (is_array($handler)) $handler = [new $handler[0](), $handler[1]];
            return $handler($req, ...$params);
        };
    }

    /** Muster der ersten Route, die Methode + Pfad bedient (ohne Aufruf), sonst null – z. B. für Favoriten */
    public function match(string $method, string $path): ?string
    {
        foreach ($this->routes as [$m, $regex]) {
            if (($m === $method || $m === '*') && preg_match($regex, $path)) {
                return (string) preg_replace(['~^#\^~', '~\$#u$~'], '', $regex);
            }
        }
        return null;
    }

    public function dispatch(Request $request): Response
    {
        $methodMismatch = false;
        foreach ($this->routes as [$method, $regex, $handler]) {
            if (!preg_match($regex, $request->path, $m)) {
                continue;
            }
            if ($method !== '*' && $method !== $request->method && !($method === 'GET' && $request->method === 'HEAD')) {
                $methodMismatch = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            if (is_array($handler)) {
                [$class, $action] = $handler;
                $handler = [new $class(), $action];
            }
            return $handler($request, ...$params);
        }
        throw new HttpException($methodMismatch ? 405 : 404);
    }
}
