<?php
declare(strict_types=1);

namespace Core\Http;

final class Response
{
    public array $headers = [];

    /** Antwort wurde bereits direkt ausgegeben (z. B. von sabre/dav) – send() schreibt dann nichts mehr */
    public bool $sent = false;

    public static function alreadySent(): self
    {
        $r = new self();
        $r->sent = true;
        return $r;
    }

    public function __construct(public string $body = '', public int $status = 200, array $headers = [])
    {
        $this->headers = array_replace(['Content-Type' => 'text/html; charset=utf-8'], $headers);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']);
    }

    public static function redirect(string $to, int $status = 303): self
    {
        return new self('', $status, ['Location' => $to]);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if ($this->sent) {
            return;
        }
        if (!headers_sent()) {
            http_response_code($this->status);
            $defaults = [
                'X-Content-Type-Options' => 'nosniff',
                'Referrer-Policy'        => 'strict-origin-when-cross-origin',
                'X-Frame-Options'        => 'SAMEORIGIN',
                'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
            ];
            foreach (array_replace($defaults, $this->headers) as $k => $v) {
                header("$k: $v");
            }
        }
        echo $this->body;
    }
}
