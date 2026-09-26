<?php
declare(strict_types=1);

namespace Core;

/** Einfaches Sliding-Window-Rate-Limit in der Datenbank (keine IP im Klartext). */
final class RateLimiter
{
    public function __construct(private Database $db) {}

    public function tooMany(string $key, int $max, int $windowSec): bool
    {
        return $this->count($key, $windowSec) >= $max;
    }

    public function count(string $key, int $windowSec): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM hits WHERE hkey = ? AND created_at > ?',
            [$key, time() - $windowSec]);
    }

    public function hit(string $key): void
    {
        $this->db->insert('hits', ['hkey' => $key, 'created_at' => time()]);
        // Gelegentlich aufräumen (älter als 1 Tag)
        if (random_int(1, 50) === 1) {
            $this->db->query('DELETE FROM hits WHERE created_at < ?', [time() - 86400]);
        }
    }

    public function clear(string $key): void
    {
        $this->db->query('DELETE FROM hits WHERE hkey = ?', [$key]);
    }
}
