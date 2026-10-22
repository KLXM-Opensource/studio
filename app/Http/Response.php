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

    /** Fehler als JSON in einheitlicher Form {ok: false, error: …} (+ weitere Felder) */
    public static function fail(string $error, int $status = 422, array $extra = []): self
    {
        return self::json(['ok' => false, 'error' => $error] + $extra, $status);
    }

    /** Datei zum Herunterladen (Dateiname wird bereinigt; nie zwischengespeichert) */
    public static function download(string $body, string $filename, string $type = 'application/octet-stream'): self
    {
        $name = (string) preg_replace('~[^A-Za-z0-9._-]+~', '-', $filename) ?: 'download';
        return new self($body, 200, ['Content-Type' => $type, 'Content-Disposition' => 'attachment; filename="' . $name . '"', 'Cache-Control' => 'private, no-store']);
    }

    /** CSV wie Excel sie erwartet: Semikolon, UTF-8 mit BOM, eine Kopfzeile. $rows: Listen von Werten */
    public static function csvText(array $header, iterable $rows): string
    {
        $fh = fopen('php://temp', 'w+');
        fputcsv($fh, $header, ';', '"', '');
        foreach ($rows as $row) fputcsv($fh, array_map(fn($v) => self::csvCell(is_scalar($v) || $v === null ? (string) $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE)), (array) $row), ';', '"', '');
        rewind($fh);
        return "\xEF\xBB\xBF" . stream_get_contents($fh);
    }

    /** Zelle gegen Formel-Ausführung in Tabellenprogrammen schützen (=, +, -, @, Tab, CR am Anfang) – Zahlen bleiben Zahlen */
    public static function csvCell(string $v): string
    {
        if ($v === '' || !in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) || is_numeric($v)) return $v;
        return "'" . $v;
    }

    /** CSV zum Herunterladen (Response::csvText + download) */
    public static function csv(string $filename, array $header, iterable $rows): self
    {
        return self::download(self::csvText($header, $rows), $filename, 'text/csv; charset=utf-8');
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
            // HTTPS erzwingen (HSTS) – nur über HTTPS und im Produktivbetrieb; ohne includeSubDomains (fremde Subdomains bleiben frei).
            // Konfiguration 'hsts': false = Studio sendet keinen (setzt ihn schon der Webserver, z. B. Plesk), Text = eigener Wert
            $hsts = function_exists('app') ? app()->config->get('hsts', true) : true;
            if ($hsts !== false && function_exists('app') && app()->request?->isSecure() && function_exists('environment') && environment() === 'production') {
                $defaults['Strict-Transport-Security'] = is_string($hsts) && trim($hsts) !== '' ? trim($hsts) : 'max-age=31536000';
            }
            // Keine Versionsangaben nach außen (PHP setzt X-Powered-By, wenn expose_php an ist)
            header_remove('X-Powered-By');
            foreach (array_replace($defaults, $this->headers) as $k => $v) {
                header("$k: $v");
            }
        }
        echo $this->body;
    }
}
