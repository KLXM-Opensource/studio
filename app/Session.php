<?php
declare(strict_types=1);

namespace Core;

/**
 * Session nur für Redakteur:innen. Besucher bekommen kein Cookie.
 * Cookie: HttpOnly; Secure (bei HTTPS); SameSite=Lax.
 */
final class Session
{
    private bool $started = false;

    public function __construct(private array $cfg) {}

    public function name(): string
    {
        return $this->cfg['name'] ?? 'cms_sess';
    }

    public function hasCookie(): bool
    {
        return isset($_COOKIE[$this->name()]);
    }

    public function start(bool $secure): void
    {
        if ($this->started || PHP_SAPI === 'cli') {
            return;
        }
        $dir = ROOT . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        if (is_writable($dir)) {
            session_save_path($dir);
        }
        $lifetime = (int) ($this->cfg['lifetime'] ?? 28800);
        ini_set('session.gc_maxlifetime', (string) $lifetime);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name($this->name());
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $this->started = true;
    }

    public function started(): bool
    {
        return $this->started;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->started ? ($_SESSION[$key] ?? $default) : $default;
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->started) {
            $_SESSION[$key] = $value;
        }
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /** Einmal-Nachricht für die nächste Seite */
    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = [$type, $message];
    }

    public function takeFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    public function regenerate(): void
    {
        if ($this->started) {
            session_regenerate_id(true);
        }
    }

    public function destroy(): void
    {
        if (!$this->started) {
            return;
        }
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie($this->name(), '', [
            'expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'],
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        session_destroy();
        $this->started = false;
    }
}
