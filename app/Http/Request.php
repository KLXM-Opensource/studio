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
        /** POST kam unvollständig an: mehr Formularfelder als max_input_vars erlaubt – PHP hat den Rest verworfen (Router lehnt ab) */
        public readonly bool $truncated = false,
    ) {}

    /** Name des Feldes, in dem admin.js große Formulare gebündelt sendet (JSON-Liste [[name, wert], …]) – siehe unpack() */
    public const PACKED = '_packed';

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
        $truncated = false;
        if (str_contains($ct, 'application/json')) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            $post = is_array($json) ? $json : [];
        } elseif (!in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $truncated = self::overflow($ct);
            if (is_string($post[self::PACKED] ?? null)) $post = self::unpack($post);
        }

        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET, $post, $_FILES, $_SERVER, $truncated);
    }

    /**
     * Mehr Formularfelder als max_input_vars? PHP verwirft dann still alle weiteren Felder – ein Speichern würde Daten verlieren
     * (z. B. Felder einer Tabelle). Formular-kodiert: Paare im Rohinhalt zählen; multipart: Warnung beim Start der Anfrage (auch max_multipart_body_parts).
     */
    private static function overflow(string $ct): bool
    {
        $limit = (int) ini_get('max_input_vars');
        if ($limit <= 0) return false;
        if (str_contains($ct, 'application/x-www-form-urlencoded')) {
            $raw = (string) file_get_contents('php://input');
            return $raw !== '' && count(array_filter(explode('&', $raw), fn($p) => $p !== '')) > $limit;
        }
        // multipart: Rohinhalt nicht lesbar – Warnung beim Start der Anfrage (public/index.php hält sie fest, bevor sie überschrieben wird)
        return defined('CMS_INPUT_OVERFLOW') ? CMS_INPUT_OVERFLOW : (bool) preg_match('~Input variables exceeded|body parts limit exceeded~', (string) (error_get_last()['message'] ?? ''));
    }

    /**
     * Gebündeltes Formular (admin.js packt Formulare mit sehr vielen Feldern beim Absenden in ein Feld „_packed“, damit
     * max_input_vars nicht greift) wieder in die übliche Struktur bringen – mit denselben Regeln wie PHP für name[a][]
     * (Punkt/Leerzeichen im Grundnamen → „_“, [] hängt an). Dateien bleiben echte Felder ($_FILES), übrige Felder bleiben erhalten.
     */
    public static function unpack(array $post): array
    {
        $list = json_decode((string) $post[self::PACKED], true);
        unset($post[self::PACKED]);
        if (!is_array($list)) return $post;
        $out = [];
        foreach ($list as $pair) {
            if (!is_array($pair) || count($pair) !== 2 || !is_string($pair[0]) || !is_scalar($pair[1])) continue;
            self::assign($out, $pair[0], (string) $pair[1]);
        }
        return $out + $post;
    }

    /** Einen Wert unter einem Feldnamen wie „settings[form][fields][]“ ablegen (wie PHP beim Einlesen von $_POST) */
    private static function assign(array &$out, string $name, string $value): void
    {
        $name = ltrim($name, ' ');
        $p = strpos($name, '[');
        $keys = [];
        if ($p !== false && preg_match_all('~\[([^\]]*)\]~A', substr($name, $p), $m, PREG_SET_ORDER, 0)) {
            $base = substr($name, 0, $p);
            foreach ($m as $x) $keys[] = $x[1];        // wie PHP: Rest nach der letzten schließenden Klammer zählt nicht
        } else {
            $base = $p !== false ? substr_replace($name, '_', $p, 1) : $name;
        }
        $base = strtr($base, ['.' => '_', ' ' => '_']);
        if ($base === '') return;
        if (!$keys) { $out[$base] = $value; return; }
        if (!isset($out[$base]) || !is_array($out[$base])) $out[$base] = [];
        $ref = &$out[$base];
        $last = count($keys) - 1;
        foreach ($keys as $i => $k) {
            if ($i === $last) {
                if ($k === '') $ref[] = $value; else $ref[$k] = $value;
                break;
            }
            if ($k === '') { $ref[] = []; $k = array_key_last($ref); }
            elseif (!isset($ref[$k]) || !is_array($ref[$k])) $ref[$k] = [];
            $ref = &$ref[$k];
        }
        unset($ref);
    }

    /** Gleiche Anfrage mit anderen GET-Parametern (Editor-Vorschau: Parameter der bearbeiteten Seite, z. B. ?seite=2) */
    public function withQuery(array $query): self
    {
        return new self($this->method, $this->path, $query, $this->post, $this->files, $this->server, $this->truncated);
    }

    /** Gleiche Anfrage mit anderem (internem) Pfad – Core\AdminPath bildet die eigene Verwaltungsadresse auf /admin ab */
    public function withPath(string $path): self
    {
        return new self($this->method, $path, $this->query, $this->post, $this->files, $this->server, $this->truncated);
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
