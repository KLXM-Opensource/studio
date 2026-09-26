<?php
declare(strict_types=1);

namespace Core\Http;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable|array $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, callable|array $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, callable|array $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    /** Alle HTTP-Methoden (z. B. WebDAV: PROPFIND, REPORT, MKCALENDAR … für Erweiterungen wie „dav“) */
    public function any(string $pattern, callable|array $handler): void
    {
        $this->add('*', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable|array $handler): void
    {
        // {name} → benannte Gruppe, {name*} → inkl. Schrägstriche
        $regex = preg_replace_callback('/\{(\w+)(\*)?\}/', fn($m) =>
            '(?P<' . $m[1] . '>' . (isset($m[2]) ? '.+' : '[^/]+') . ')', $pattern);
        $this->routes[] = [$method, '#^' . $regex . '$#u', $handler];
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
