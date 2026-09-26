<?php
declare(strict_types=1);

namespace Core\Http;

/**
 * Server-Sent Events (text/event-stream) für kurzlebige Streams – z. B. eine KI-Antwort, die Wort für Wort kommt
 * (Besucher-Chat Core\AI\VisitorChat, Redaktions-Assistent Core\AI\Assistant). Auch für andere Kanäle nutzbar
 * (z. B. Chat zwischen Benutzern): ein Stream je Anfrage, danach schließen – der Browser verbindet sich bei Bedarf neu.
 *
 *   return Sse::stream(function (Sse $sse) {
 *       $sse->send('delta', ['t' => 'Hallo']);   // event: delta\ndata: {"t":"Hallo"}\n\n
 *       if ($sse->aborted()) return;             // Besucher hat geschlossen → Arbeit abbrechen
 *       $sse->send('done', ['ok' => true]);
 *   });
 *
 * Betrieb (Plesk, PHP-FPM hinter nginx/Apache):
 *   - Jeder offene Stream belegt einen PHP-Worker (pm.max_children!) – deshalb nur kurz (eine Antwort) und nie „ewig“ offen.
 *   - Sitzung wird sofort geschlossen (session_write_close), sonst warten alle weiteren Anfragen derselben Person.
 *   - nginx puffert sonst: Kopfzeile „X-Accel-Buffering: no“; gzip/Brotli für text/event-stream vermeiden (no-transform).
 *   - Der PHP-Entwicklungsserver (php -S) bedient nur eine Anfrage gleichzeitig: während eine Antwort läuft, warten andere.
 * Kann der Aufrufer keinen Stream lesen (Accept ohne text/event-stream), nutzt er stattdessen eine normale JSON-Antwort.
 */
final class Sse
{
    private bool $open = false;

    /**
     * Sind Streams hier sinnvoll? $mode: 'sse' (immer), 'poll' (nie) oder 'auto' – auf dem PHP-Entwicklungsserver (php -S,
     * nur eine Anfrage gleichzeitig) nein, sonst ja (PHP-FPM, Apache).
     */
    public static function supported(string $mode = 'auto'): bool
    {
        return match ($mode) {
            'sse' => true,
            'poll' => false,
            default => PHP_SAPI !== 'cli-server',
        };
    }

    /** Möchte der Browser einen Stream? (fetch mit Accept: text/event-stream bzw. EventSource) */
    public static function wanted(Request $r): bool
    {
        return str_contains((string) ($r->server['HTTP_ACCEPT'] ?? ''), 'text/event-stream');
    }

    /**
     * Stream öffnen, $fn(Sse) ausführen, schließen. Liefert eine bereits gesendete Response (App::handle schreibt nichts mehr).
     * $headers: zusätzliche Kopfzeilen; $retry: Wartezeit für automatische Neuverbindung (EventSource) in ms, 0 = keine Angabe
     */
    public static function stream(callable $fn, array $headers = [], int $retry = 0): Response
    {
        $sse = new self();
        $sse->start($headers, $retry);
        try {
            $fn($sse);
        } catch (\Throwable $e) {
            error_log('[sse] ' . $e->getMessage());
            $sse->send('error', ['message' => 'Interner Fehler.']);
        }
        return Response::alreadySent();
    }

    /** Kopfzeilen senden, Puffer abschalten, Sitzung freigeben */
    public function start(array $headers = [], int $retry = 0): void
    {
        if ($this->open) return;
        $this->open = true;
        // Sitzung freigeben: weitere Anfragen derselben Person dürfen nicht auf das Ende des Streams warten
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) @ob_end_clean();
        // Abbruch durch den Browser erkennen (connection_aborted nach flush) – die Antwort wird dann nicht weiter erzeugt
        ignore_user_abort(true);
        @set_time_limit(180);
        if (!headers_sent()) {
            http_response_code(200);
            foreach (array_replace([
                'Content-Type' => 'text/event-stream; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store, no-transform',
                'X-Accel-Buffering' => 'no',                      // nginx (auch Plesk): nicht puffern
                'X-Content-Type-Options' => 'nosniff',
                'X-Robots-Tag' => 'noindex',
                'Referrer-Policy' => 'same-origin',
                'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
            ], $headers) as $k => $v) {
                header($k . ': ' . $v);
            }
        }
        // Erster Kommentar (öffnet den Stream sofort; 2 KB Füllung für Puffer mancher Proxys/Browser)
        echo ':' . str_repeat(' ', 2048) . "\n" . ($retry > 0 ? 'retry: ' . $retry . "\n" : '') . "\n";
        $this->flush();
    }

    /** Ereignis senden (Daten als JSON). false = Verbindung abgebrochen */
    public function send(string $event, mixed $data = null, ?string $id = null): bool
    {
        if (!$this->open) $this->start();
        $out = '';
        if ($id !== null) $out .= 'id: ' . preg_replace('~[\r\n]~', '', $id) . "\n";
        $out .= 'event: ' . preg_replace('~[^a-z0-9_\-]~i', '', $event) . "\n";
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        foreach (explode("\n", (string) $json) as $line) $out .= 'data: ' . $line . "\n";
        echo $out . "\n";
        return $this->flush();
    }

    /** Kurzform von send() */
    public function event(string $event, mixed $data = null, ?string $id = null): bool
    {
        return $this->send($event, $data, $id);
    }

    /** Wartezeit für eine automatische Neuverbindung (EventSource) in Millisekunden */
    public function retry(int $ms): bool
    {
        echo 'retry: ' . max(0, $ms) . "\n\n";
        return $this->flush();
    }

    /** Kurzform von ping() */
    public function comment(string $text = 'ping'): bool
    {
        return $this->ping($text);
    }

    /** Kommentar (z. B. Lebenszeichen alle 15 s bei langen Pausen) */
    public function ping(string $text = 'ping'): bool
    {
        echo ': ' . preg_replace('~[\r\n]~', ' ', $text) . "\n\n";
        return $this->flush();
    }

    /** Hat der Browser die Verbindung geschlossen? */
    public function aborted(): bool
    {
        return connection_aborted() === 1;
    }

    private function flush(): bool
    {
        if (ob_get_level() > 0) @ob_flush();
        @flush();
        return !$this->aborted();
    }
}
