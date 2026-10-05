<?php
declare(strict_types=1);

namespace Core\Http;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly array $server,
    ) {}

    public static function fromGlobals(): self
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');

        // Ohne Rewrite: /index.php/pfad → /pfad
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        // Nur einen echten Skriptnamen abschneiden – der PHP-Entwicklungsserver meldet bei Pfaden in vorhandenen
        // Ordnern (z. B. /media/fehlt.jpg) den Ordner als SCRIPT_NAME, sonst würde daraus die Startseite
        if (str_ends_with($script, '.php') && str_starts_with($path, $script)) {
            $path = substr($path, strlen($script)) ?: '/';
        }
        $path = '/' . trim($path, '/');

        $post = $_POST;
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $post = is_array($json) ? $json : [];
        }

        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET, $post, $_FILES, $_SERVER);
    }

    /** Gleiche Anfrage mit anderen GET-Parametern (Editor-Vorschau: Parameter der bearbeiteten Seite, z. B. ?seite=2) */
    public function withQuery(array $query): self
    {
        return new self($this->method, $this->path, $query, $this->post, $this->files, $this->server);
    }

    /** Gleiche Anfrage mit anderem (internem) Pfad – Core\AdminPath bildet die eigene Verwaltungsadresse auf /admin ab */
    public function withPath(string $path): self
    {
        return new self($this->method, $path, $this->query, $this->post, $this->files, $this->server);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function wantsJson(): bool
    {
        return str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($this->server['CONTENT_TYPE'] ?? '', 'application/json');
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || ($this->server['SERVER_PORT'] ?? '') == 443
            || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function isAdminPath(): bool
    {
        return $this->path === '/admin' || str_starts_with($this->path, '/admin/');
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function host(): string
    {
        return (string) ($this->server['HTTP_HOST'] ?? 'localhost');
    }
}
