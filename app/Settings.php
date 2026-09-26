<?php
declare(strict_types=1);

namespace Core;

/**
 * Key/Value-Einstellungen (JSON). Theme-Felder (z. B. „Praxisdaten“) ohne Präfix,
 * System-Grundeinstellungen mit Präfix "sys.".
 * Standardwerte kommen aus den Schemata (Theme bzw. SystemSchema).
 */
final class Settings
{
    /** Geheimnisse: in der Datenbank verschlüsselt (libsodium secretbox, Schlüssel aus dem app_key), get() liefert Klartext */
    public const SECRETS = ['sys.mail_pass'];
    private const SEAL = 'enc1:';

    private ?array $values = null;
    private array $defaults = [];

    public function __construct(private Database $db) {}

    public function registerDefaults(array $defaults): void
    {
        $this->defaults = array_replace($this->defaults, $defaults);
    }

    private function load(): void
    {
        if ($this->values !== null) {
            return;
        }
        $this->values = [];
        foreach ($this->db->fetchAll('SELECT skey, value_json FROM settings') as $r) {
            $this->values[$r['skey']] = json_decode((string) $r['value_json'], true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();
        if (array_key_exists($key, $this->values)) {
            return in_array($key, self::SECRETS, true) ? self::open($this->values[$key]) : $this->values[$key];
        }
        return $this->defaults[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        $this->load();
        return array_key_exists($key, $this->values);
    }

    public function set(string $key, mixed $value): void
    {
        $this->load();
        if (in_array($key, self::SECRETS, true)) $value = self::seal($value);
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($this->db->driver === 'mysql') {
            $this->db->query('REPLACE INTO settings (skey, value_json) VALUES (?, ?)', [$key, $json]);
        } else {
            $this->db->query('INSERT INTO settings (skey, value_json) VALUES (?, ?)
                ON CONFLICT(skey) DO UPDATE SET value_json = excluded.value_json', [$key, $json]);
        }
        $this->values[$key] = $value;
    }

    /** Zwischenspeicher verwerfen (nach einem zurückgerollten Probelauf, Core\Review\Queue) */
    public function forget(): void
    {
        $this->values = null;
    }

    /** Werte nur für diese Anfrage ersetzen (Vorschau) – nichts wird gespeichert */
    public function override(array $values): void
    {
        $this->load();
        foreach ($values as $k => $v) {
            if ($v === null) unset($this->values[$k]); else $this->values[$k] = $v;
        }
    }

    public function delete(string $key): void
    {
        $this->load();
        $this->db->query('DELETE FROM settings WHERE skey = ?', [$key]);
        unset($this->values[$key]);
    }

    public function setMany(array $values): void
    {
        $this->db->transaction(function () use ($values) {
            foreach ($values as $k => $v) {
                $this->set($k, $v);
            }
        });
    }

    // ---------------------------------------------------------------- Geheimnisse (z. B. SMTP-Passwort)

    /** Noch im Klartext gespeicherte Geheimnisse verschlüsseln (migrate) – idempotent. @return list<string> geänderte Schlüssel */
    public function sealSecrets(): array
    {
        $this->load();
        $done = [];
        foreach (self::SECRETS as $k) {
            $raw = $this->values[$k] ?? null;
            if (is_string($raw) && $raw !== '' && !str_starts_with($raw, self::SEAL)) {
                $this->set($k, $raw);
                $done[] = $k;
            }
        }
        return $done;
    }

    /** Beliebiges Geheimnis verschlüsseln (wie das SMTP-Passwort), z. B. Zugangsdaten externer Quellen (Core\Sources) */
    public static function encrypt(string $value): string
    {
        return (string) self::seal($value);
    }

    /** Gegenstück zu encrypt(); Klartext-Werte kommen unverändert zurück, Fehler → '' */
    public static function decrypt(string $value): string
    {
        return (string) self::open($value);
    }

    private static function boxKey(): string
    {
        $appKey = (string) app()->config->get('app_key', '');
        if (strlen($appKey) < 32) throw new \RuntimeException('app_key fehlt – Geheimnisse können nicht verschlüsselt werden.');
        return sodium_crypto_generichash('settings-secret|' . $appKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function seal(mixed $value): mixed
    {
        if (!is_string($value) || $value === '' || str_starts_with($value, self::SEAL)) return $value;
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::SEAL . base64_encode($nonce . sodium_crypto_secretbox($value, $nonce, self::boxKey()));
    }

    /** Entschlüsseln; Werte im alten Klartext-Format unverändert (werden beim nächsten Speichern verschlüsselt) */
    private static function open(mixed $value): mixed
    {
        if (!is_string($value) || !str_starts_with($value, self::SEAL)) return $value;
        $raw = base64_decode(substr($value, strlen(self::SEAL)), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
        try {
            $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::boxKey());
        } catch (\Throwable) {
            $plain = false;
        }
        return $plain === false ? '' : $plain;
    }
}
