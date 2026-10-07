<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

/**
 * VAPID-Schlüssel der Installation (RFC 8292): ein P-256-Schlüsselpaar für alle Websites, abgelegt wie network_key in
 * config/config.local.php ('push_vapid_public', 'push_vapid_private' – Base64url, nie im Repository, nie in config/sites/*.php;
 * Website-Konfigurationen können die Werte nicht setzen). Erzeugt beim ersten Gebrauch (Funktion „push“ eingeschaltet,
 * Grundeinstellungen → Push-Benachrichtigungen, `php bin/console push:keys --generate`).
 *
 * Abos gehören zum öffentlichen Schlüssel, mit dem der Browser sie angelegt hat (Fingerabdruck key_fp je Abo). Neue Schlüssel
 * (push:keys --regenerate --force) machen alle bestehenden Abos ungültig – Browser abonnieren beim nächsten Besuch neu.
 */
final class Keys
{
    public const PUBLIC = 'push_vapid_public';
    public const PRIVATE = 'push_vapid_private';
    public const CREATED = 'push_vapid_created';

    private static ?array $cache = null;

    /** Werte aus config/config.local.php (nur die Installation – Website-Konfigurationen zählen nicht) */
    private static function local(): array
    {
        $file = ROOT . '/config/config.local.php';
        if (!is_file($file)) return [];
        $v = (static fn(string $__f) => require $__f)($file);
        return is_array($v) ? $v : [];
    }

    /** Schlüsselpaar roh: ['public' => 65 Byte, 'private' => 32 Byte, 'created' => ?string] oder null */
    public static function get(): ?array
    {
        if (self::$cache !== null) return self::$cache ?: null;
        $l = self::local();
        $pub = WebPush::unb64u((string) ($l[self::PUBLIC] ?? ''));
        $priv = WebPush::unb64u((string) ($l[self::PRIVATE] ?? ''));
        if ($pub === false || $priv === false || strlen($pub) !== 65 || strlen($priv) !== 32) {
            self::$cache = [];
            return null;
        }
        self::$cache = ['public' => $pub, 'private' => $priv, 'created' => isset($l[self::CREATED]) ? (string) $l[self::CREATED] : null];
        return self::$cache;
    }

    public static function ready(): bool
    {
        return self::get() !== null;
    }

    /** Öffentlicher Schlüssel als Base64url (applicationServerKey der Browser) bzw. '' */
    public static function publicB64(): string
    {
        $k = self::get();
        return $k ? WebPush::b64u($k['public']) : '';
    }

    /** Kurzer Fingerabdruck des öffentlichen Schlüssels (je Abo gespeichert; wechselt mit neuen Schlüsseln) */
    public static function fingerprint(): string
    {
        $k = self::get();
        return $k ? substr(hash('sha256', $k['public']), 0, 16) : '';
    }

    /** Schlüssel anlegen, falls sie fehlen → true, wenn danach welche da sind */
    public static function ensure(): bool
    {
        if (self::ready()) return true;
        return self::write(WebPush::generateKeys());
    }

    /** Neues Schlüsselpaar (alle Abos werden ungültig) → true bei Erfolg */
    public static function regenerate(): bool
    {
        return self::write(WebPush::generateKeys());
    }

    /**
     * Schlüssel in config/config.local.php schreiben: alte Zeilen entfernen, neue vor dem Ende des Arrays einfügen.
     * Wie Network::ensureKey erst eine temporäre Datei einlesen und prüfen, dann ersetzen – eine defekte Datei wäre fatal.
     */
    private static function write(array $keys): bool
    {
        if (!WebPush::keysMatch($keys['private'], $keys['public'])) return false;
        $file = ROOT . '/config/config.local.php';
        if (!is_file($file) || !is_writable($file) || !is_writable(dirname($file))) return false;
        $src = (string) file_get_contents($file);
        $src = (string) preg_replace("~^[ \t]*'push_vapid_(public|private|created)'\s*=>[^\n]*\n~m", '', $src);
        $lines = "  '" . self::PUBLIC . "' => '" . WebPush::b64u($keys['public']) . "',   // Web Push (VAPID, öffentlich) – gilt für alle Websites dieser Installation\n"
            . "  '" . self::PRIVATE . "' => '" . WebPush::b64u($keys['private']) . "',   // Web Push (VAPID, geheim) – neu nur mit push:keys --regenerate (Abos werden ungültig)\n"
            . "  '" . self::CREATED . "' => '" . date('Y-m-d H:i') . "',\n";
        $new = preg_replace('~(\n\s*)(\)|\]);\s*$~', "\n" . $lines . '$2;' . "\n", $src, 1, $n);
        if (!$n || !is_string($new)) return false;
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $new, LOCK_EX);
        try {
            $check = (static fn(string $__f) => require $__f)($tmp);
        } catch (\Throwable) {
            $check = null;
        }
        if (!is_array($check) || ($check[self::PUBLIC] ?? '') !== WebPush::b64u($keys['public']) || ($check[self::PRIVATE] ?? '') !== WebPush::b64u($keys['private'])) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0640);
        rename($tmp, $file);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
        self::$cache = null;
        return self::ready();
    }

    /** Schlüssel nur im Speicher vorgeben (Selbsttest; null = wieder aus der Datei lesen) */
    public static function override(?array $keys): void
    {
        self::$cache = $keys === null ? null : ['public' => $keys['public'], 'private' => $keys['private'], 'created' => $keys['created'] ?? null];
    }

    /** Zwischenspeicher verwerfen (Tests, nach dem Schreiben) */
    public static function reset(): void
    {
        self::$cache = null;
    }

    /**
     * Kontakt für die Push-Dienste (VAPID „sub“): config 'push_subject' (mailto: oder https:), sonst Absender der E-Mails,
     * sonst die Adresse der Website (https) bzw. mailto:webmaster@{domain}.
     */
    public static function subject(): string
    {
        $cfg = trim((string) app()->config->get('push_subject', ''));
        if (preg_match('~^(mailto:[^@\s]+@[^@\s]+|https://\S+)$~', $cfg)) return $cfg;
        foreach (['sys.mail_from'] as $k) {
            $m = trim((string) app()->settings->get($k, ''));
            if (filter_var($m, FILTER_VALIDATE_EMAIL)) return 'mailto:' . $m;
        }
        $site = Push::origin();
        if (str_starts_with($site, 'https://')) return $site;
        $host = (string) (parse_url($site, PHP_URL_HOST) ?: 'localhost');
        return 'mailto:webmaster@' . (str_contains($host, '.') ? $host : $host . '.invalid');
    }
}
