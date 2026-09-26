<?php
declare(strict_types=1);

namespace Core\Api;

use Core\Http\Request;
use Core\RateLimiter;

/**
 * API-Tokens für REST-API und MCP.
 * Format: cms_<48 Hex>. Gespeichert wird nur der SHA-256-Hash – der Klartext
 * wird genau einmal beim Erzeugen angezeigt.
 *
 * Berechtigungen: read (nur lesen) · write (lesen + ändern + veröffentlichen)
 */
final class Tokens
{
    public const SCOPES = ['read' => 'Nur lesen', 'write' => 'Lesen und ändern'];
    public const RATE_LIMIT = 300;        // Anfragen …
    public const RATE_WINDOW = 300;       // … je 5 Minuten und Token

    /** Umgang mit Änderungen (Core\Review): direkt übernehmen oder zur Freigabe einreichen */
    public const MODES = ['direct' => 'Direkt übernehmen', 'review' => 'Zur Freigabe'];

    public static function setMode(int $id, string $mode): void
    {
        app()->db->update('api_tokens', ['review_mode' => isset(self::MODES[$mode]) ? $mode : 'direct'], 'id = :id', ['id' => $id]);
    }

    /** @return array{id: int, token: string} */
    public static function create(string $name, string $scope, ?int $userId, ?string $expires = null, string $mode = 'direct'): array
    {
        $token = 'cms_' . bin2hex(random_bytes(24));
        $id = app()->db->insert('api_tokens', [
            'review_mode' => isset(self::MODES[$mode]) ? $mode : 'direct',
            'name' => mb_substr(trim($name) ?: 'Token', 0, 120),
            'token_hash' => hash('sha256', $token),
            'prefix' => substr($token, 0, 10),
            'scope' => isset(self::SCOPES[$scope]) ? $scope : 'read',
            'user_id' => $userId,
            'created_at' => now(),
            'expires_at' => $expires ?: null,
        ]);
        return ['id' => $id, 'token' => $token];
    }

    public static function all(): array
    {
        return app()->db->fetchAll('SELECT t.*, u.email FROM api_tokens t LEFT JOIN users u ON u.id = t.user_id ORDER BY t.id DESC');
    }

    public static function revoke(int $id): void
    {
        app()->db->query('DELETE FROM api_tokens WHERE id = ?', [$id]);
    }

    /** Token aus Authorization-Header (Bearer) oder X-Api-Key lesen */
    public static function fromRequest(Request $r): ?string
    {
        $h = $r->server['HTTP_AUTHORIZATION'] ?? $r->server['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($h === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) {
                    $h = $v;
                }
            }
        }
        if (preg_match('~^Bearer\s+(\S+)$~i', trim((string) $h), $m)) {
            return $m[1];
        }
        $key = trim((string) ($r->server['HTTP_X_API_KEY'] ?? ''));
        return $key !== '' ? $key : null;
    }

    /**
     * Prüft Token, Ablauf und Rate-Limit.
     * @return array Token-Datensatz
     */
    public static function authenticate(Request $r): array
    {
        $plain = self::fromRequest($r);
        if (!$plain || !str_starts_with($plain, 'cms_')) {
            throw new ApiError(401, 'API-Token fehlt. Header „Authorization: Bearer cms_…“ oder „X-Api-Key“ senden.');
        }
        $row = app()->db->fetch('SELECT * FROM api_tokens WHERE token_hash = ?', [hash('sha256', $plain)]);
        if (!$row) {
            throw new ApiError(401, 'API-Token ist ungültig oder wurde widerrufen.');
        }
        if ($row['expires_at'] && $row['expires_at'] < date('Y-m-d')) {
            throw new ApiError(401, 'API-Token ist abgelaufen.');
        }
        $limiter = new RateLimiter(app()->db);
        $key = 'api:' . $row['id'];
        if ($limiter->tooMany($key, self::RATE_LIMIT, self::RATE_WINDOW)) {
            throw new ApiError(429, 'Zu viele Anfragen. Bitte kurz warten.');
        }
        $limiter->hit($key);
        if (!$row['last_used_at'] || $row['last_used_at'] < date('Y-m-d H:i:s', time() - 60)) {
            app()->db->update('api_tokens', ['last_used_at' => now()], 'id = :id', ['id' => $row['id']]);
        }
        return $row;
    }

    public static function requireWrite(array $token): void
    {
        if ($token['scope'] !== 'write') {
            throw new ApiError(403, 'Dieses Token darf nur lesen (Berechtigung „write“ erforderlich).');
        }
    }
}
