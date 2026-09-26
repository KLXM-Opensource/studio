<?php
declare(strict_types=1);

namespace Core;

/**
 * Mehrstufiger Spamschutz ohne Cookies und ohne externe Dienste:
 *  1. Honeypot-Feld
 *  2. Signiertes Zeit-Token (Mindest-Ausfüllzeit, Ablauf, Einmalverwendung)
 *  3. Proof-of-Work (SHA-256, Schwierigkeit einstellbar) – ersetzt ein Captcha
 *  4. Rate-Limit je (gehashter) IP und global
 *  5. Inhaltsprüfung (Links, Sperrbegriffe)
 */
final class SpamGuard
{
    public const HONEYPOT = 'website_url';

    public static function token(string $form): string
    {
        $ts = time();
        $nonce = bin2hex(random_bytes(8));
        return "$ts.$nonce." . self::sign("$form|$ts|$nonce");
    }

    private static function sign(string $data): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, app()->key(), true)), '+/', '-_'), '=');
    }

    public static function difficulty(): int
    {
        return app()->settings->get('sys.spam_pow') ? (int) app()->settings->get('sys.spam_pow_difficulty', 16) : 0;
    }

    /** Konfiguration für das Frontend-Script */
    public static function challenge(string $form): array
    {
        return [
            'token' => self::token($form),
            'difficulty' => self::difficulty(),
            'minSeconds' => (int) app()->settings->get('sys.spam_min_seconds', 4),
        ];
    }

    /**
     * @return array{ok: bool, silent?: bool, error?: string}
     *   silent = true → Bot erkannt; Antwort täuscht Erfolg vor, nichts wird gespeichert.
     */
    public static function check(string $form, array $post, string $ip): array
    {
        $s = app()->settings;
        $db = app()->db;
        $limiter = new RateLimiter($db);

        // 1. Honeypot
        if ($s->get('sys.spam_honeypot', true) && trim((string) ($post[self::HONEYPOT] ?? '')) !== '') {
            return self::log('honeypot', ['ok' => false, 'silent' => true]);
        }

        // 2. Token
        $token = (string) ($post['_token'] ?? '');
        $parts = explode('.', $token);
        if (count($parts) !== 3 || !hash_equals(self::sign("$form|{$parts[0]}|{$parts[1]}"), $parts[2])) {
            return self::log('token', ['ok' => false, 'error' => lt('Das Formular ist abgelaufen. Bitte laden Sie die Seite neu.')]);
        }
        [$ts, $nonce] = [(int) $parts[0], $parts[1]];
        $age = time() - $ts;
        if ($age < (int) $s->get('sys.spam_min_seconds', 4)) {
            return self::log('too-fast', ['ok' => false, 'silent' => true]);
        }
        $maxAge = max(1, (int) $s->get('sys.spam_max_hours', 3)) * 3600;
        if ($age > $maxAge) {
            return ['ok' => false, 'error' => lt('Das Formular ist abgelaufen. Bitte laden Sie die Seite neu.')];
        }
        if ($limiter->count('nonce:' . $nonce, $maxAge) > 0) {
            return self::log('replay', ['ok' => false, 'silent' => true]);
        }

        // 3. Proof-of-Work
        $d = self::difficulty();
        if ($d > 0) {
            $sol = (string) ($post['_pow'] ?? '');
            if (!ctype_digit($sol) || !self::leadingZeroBits(hash('sha256', $token . ':' . $sol, true), $d)) {
                return self::log('pow', ['ok' => false, 'error' => lt('Die Sicherheitsprüfung ist fehlgeschlagen. Bitte JavaScript aktivieren und erneut senden.')]);
            }
        }

        // 4. Rate-Limits (IP nur als HMAC-Hash)
        $ipKey = 'form-ip:' . hash_hmac('sha256', $ip, app()->key());
        if ($limiter->tooMany($ipKey, max(1, (int) $s->get('sys.spam_rate_ip', 5)), 3600)) {
            return self::log('rate-ip', ['ok' => false, 'error' => lt('Sie haben in kurzer Zeit mehrere Anfragen gesendet. Bitte versuchen Sie es später erneut oder rufen Sie uns an.')]);
        }
        if ($limiter->tooMany('form-global', max(1, (int) $s->get('sys.spam_rate_global', 60)), 3600)) {
            return self::log('rate-global', ['ok' => false, 'error' => lt('Das Online-Formular ist gerade stark ausgelastet. Bitte rufen Sie uns an.')]);
        }

        // 5. Inhalt
        $userInput = array_filter($post, fn($k) => !str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
        $text = mb_strtolower(implode(' ', array_map(fn($v) => is_scalar($v) ? (string) $v : '', $userInput)));
        if ($s->get('sys.spam_block_links', true) && preg_match('~(https?://|www\.|\[url|<a\s)~i', $text)) {
            return self::log('links', ['ok' => false, 'error' => lt('Bitte geben Sie keine Links oder Webadressen ein.')]);
        }
        foreach (preg_split('~\R~', (string) $s->get('sys.spam_blocklist', '')) as $word) {
            $word = mb_strtolower(trim($word));
            if ($word !== '' && str_contains($text, $word)) {
                return self::log('blocklist', ['ok' => false, 'silent' => true]);
            }
        }

        $limiter->hit('nonce:' . $nonce);
        $limiter->hit($ipKey);
        $limiter->hit('form-global');
        return ['ok' => true];
    }

    public static function leadingZeroBits(string $bin, int $bits): bool
    {
        $full = intdiv($bits, 8);
        for ($i = 0; $i < $full; $i++) {
            if (ord($bin[$i]) !== 0) {
                return false;
            }
        }
        $rest = $bits % 8;
        return $rest === 0 || (ord($bin[$full]) >> (8 - $rest)) === 0;
    }

    private static function log(string $reason, array $result): array
    {
        $f = site()->storage('logs/spam.log');
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0770, true);
        @file_put_contents($f, date('c') . " blocked: $reason\n", FILE_APPEND | LOCK_EX);
        return $result;
    }
}
