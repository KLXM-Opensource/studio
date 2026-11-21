<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Monitoring;

/**
 * Fehlerprotokoll der Installation (storage/logs/php-error.log) für die Überwachung zählen – nur Anzahl und Art, nie Inhalte.
 *
 * Arten: fatal (PHP Fatal/Parse/Recoverable fatal error), exception (nicht abgefangene Ausnahme, Core\App), warning (PHP Warning),
 * notice, deprecated (PHP Notice/Deprecated/Strict) und application (eigene Meldungen von Core und Erweiterungen per error_log()).
 * errors = fatal + exception, warnings = warning. Gelesen wird das Ende der Datei (höchstens MAX_BYTES), Zeitfenster 24 Stunden.
 * Das Protokoll gehört zur Installation – alle Websites einer Installation melden dieselben Zahlen.
 */
final class ErrorLog
{
    public const WINDOW = 86400;
    public const MAX_BYTES = 4_000_000;
    public const TYPES = ['fatal', 'exception', 'warning', 'notice', 'deprecated', 'application'];

    public static function file(): string
    {
        return ROOT . '/storage/logs/php-error.log';
    }

    /**
     * @return array{window_hours: int, errors: int, warnings: int, counts: array<string, int>, last_error_at: ?string,
     *   last_warning_at: ?string, truncated: bool}
     */
    public static function summary(?string $file = null, ?int $now = null): array
    {
        $now ??= time();
        $since = $now - self::WINDOW;
        $counts = array_fill_keys(self::TYPES, 0);
        $lastErr = $lastWarn = 0;
        $truncated = false;
        foreach (self::lines($file ?? self::file(), $truncated) as $line) {
            $t = self::time($line);
            if ($t === null || $t < $since || $t > $now + 300) continue;
            $type = self::type(substr($line, (int) strpos($line, ']') + 2));
            $counts[$type]++;
            if ($type === 'fatal' || $type === 'exception') $lastErr = max($lastErr, $t);
            elseif ($type === 'warning') $lastWarn = max($lastWarn, $t);
        }
        return ['window_hours' => intdiv(self::WINDOW, 3600), 'errors' => $counts['fatal'] + $counts['exception'], 'warnings' => $counts['warning'],
            'counts' => $counts, 'last_error_at' => $lastErr ? date('c', $lastErr) : null, 'last_warning_at' => $lastWarn ? date('c', $lastWarn) : null,
            'truncated' => $truncated];
    }

    /** Art einer Meldung (ohne Zeitstempel) */
    public static function type(string $msg): string
    {
        if (preg_match('~^PHP (?:Fatal error|Parse error|Recoverable fatal error|Core error|Compile error)\b~', $msg)) return 'fatal';
        if (preg_match('~^PHP Warning\b~', $msg)) return 'warning';
        if (preg_match('~^PHP Notice\b~', $msg)) return 'notice';
        if (preg_match('~^PHP (?:Deprecated|Strict Standards)\b~', $msg)) return 'deprecated';
        // (string) $e einer Ausnahme: „TypeError: … in /pfad:12“ bzw. „Core\Http\HttpException: …“
        if (preg_match('~^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)*[A-Za-z_][A-Za-z0-9_]*(?:Exception|Error)(?:: | in /)~', $msg)) return 'exception';
        return 'application';
    }

    /** Zeitstempel „[21-Nov-2026 17:04:00 Europe/Berlin]“ bzw. „[21-Nov-2026 16:04:00 UTC]“ */
    private static function time(string $line): ?int
    {
        if (!preg_match('~^\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2})(?: ([A-Za-z_/+\-0-9]+))?\]~', $line, $m)) return null;
        try {
            $tz = isset($m[2]) ? new \DateTimeZone($m[2]) : null;
        } catch (\Throwable) {
            $tz = null;
        }
        $d = \DateTimeImmutable::createFromFormat('d-M-Y H:i:s', $m[1], $tz);
        return $d ? $d->getTimestamp() : null;
    }

    /** Zeilen am Ende der Datei (Folgezeilen eines Stacktrace ohne Zeitstempel fallen später heraus) @return list<string> */
    private static function lines(string $file, bool &$truncated): array
    {
        $size = is_file($file) ? (int) @filesize($file) : 0;
        if ($size <= 0) return [];
        $fh = @fopen($file, 'rb');
        if (!$fh) return [];
        $start = max(0, $size - self::MAX_BYTES);
        $truncated = $start > 0;
        fseek($fh, $start);
        $data = (string) stream_get_contents($fh);
        fclose($fh);
        $lines = explode("\n", $data);
        if ($start > 0) array_shift($lines);   // angeschnittene erste Zeile
        return array_values(array_filter($lines, fn($l) => $l !== '' && $l[0] === '['));
    }
}
