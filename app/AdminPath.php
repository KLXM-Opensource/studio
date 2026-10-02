<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Eigene Adresse der Verwaltung statt /admin (z. B. /werkstatt-k7m2x).
 *  - Einstellung: Umgebungsvariable KLXM_ADMIN_PATH, sonst config 'admin_path' (config.local.php, je Website config/sites/{key}.php);
 *    setzen in Grundeinstellungen → „Adresse der Verwaltung“ (Administration; bei mehreren Websites nur die Netzwerk-Administration,
 *    mit Passwort) oder `php bin/console admin:path <adresse>|--random|--reset`. Standard bleibt /admin.
 *  - Intern bleibt alles /admin (Routen, Rechte, Erweiterungen). Eingehend wird die eigene Adresse auf /admin abgebildet
 *    (App::handle), ausgehend url('/admin/…') auf die eigene Adresse.
 *  - /admin direkt: ohne Anmeldung 404 wie jede unbekannte Seite (Anmeldung, „Passwort vergessen“ usw. verraten nichts);
 *    angemeldet weiter erreichbar (Skripte, alte Lesezeichen). Ausgenommen: Netzwerk-SSO und öffentliche Routen von Erweiterungen.
 * Kein Ersatz für starke Passwörter und zweiten Faktor – hält aber automatische Login-Scanner fern.
 */
final class AdminPath
{
    public const INTERNAL = '/admin';
    /** Übliche Adressen, die Scanner ausprobieren, und eigene Bereiche der Website */
    public const RESERVED = ['admin', 'admins', 'administrator', 'administration', 'login', 'logout', 'signin', 'anmelden', 'anmeldung', 'wp-admin',
        'wp-login', 'wp-login.php', 'wordpress', 'backend', 'cms', 'redaxo', 'typo3', 'joomla', 'drupal', 'dashboard', 'user', 'users', 'account',
        'panel', 'cpanel', 'plesk', 'manage', 'manager', 'management', 'verwaltung', 'intern', 'internal', 'studio', 'klxm', 'editor', 'edit',
        'api', 'mcp', 'assets', 'media', 'medien', 'anfrage', 'formular', 'proxy', 'pdf', 'feed', 'suche', 'search', 'live', 'sse', 'sitemap.xml',
        'robots.txt', 'llms.txt', 'manifest.webmanifest', 'sw.js', 'favicon.ico', '_seitenvorlage', 'index.php'];
    private const WORDS = ['werkstatt', 'zentrale', 'pult', 'kontor', 'atelier', 'leitstand', 'schreibtisch', 'regie'];

    private static ?string $prefix = null;

    /** Öffentliche Adresse der Verwaltung, z. B. '/werkstatt-k7m2x' – ohne eigene Einstellung '/admin' */
    public static function prefix(): string
    {
        if (self::$prefix !== null) return self::$prefix;
        $raw = (string) (getenv('KLXM_ADMIN_PATH') ?: '');
        if ($raw === '' && function_exists('app') && ($a = app())) $raw = (string) $a->config->get('admin_path', '');
        $slug = self::normalize($raw);
        return self::$prefix = ($slug !== '' && self::error($slug) === null) ? '/' . $slug : self::INTERNAL;
    }

    public static function custom(): bool
    {
        return self::prefix() !== self::INTERNAL;
    }

    /** Nur für Tests und den Konsolenbefehl: zwischengespeicherte Adresse vergessen */
    public static function reset(?string $prefix = null): void
    {
        self::$prefix = $prefix;
    }

    public static function normalize(string $raw): string
    {
        return strtolower(trim(trim($raw), '/'));
    }

    /** Fehlermeldung für eine gewünschte Adresse (ohne Schrägstrich) oder null */
    public static function error(string $slug): ?string
    {
        if (!preg_match('~^[a-z0-9][a-z0-9-]{4,39}$~', $slug)) return __('Bitte 5–40 Zeichen: Kleinbuchstaben, Ziffern und Bindestriche (nicht am Anfang).');
        if (in_array($slug, self::RESERVED, true)) return __('Diese Adresse ist zu üblich oder schon vergeben – bitte eine eigene wählen.');
        return null;
    }

    /** Zufällige, gut lesbare Adresse, z. B. „kontor-x4m9q“ (ohne 0/o/1/l) */
    public static function random(): string
    {
        $abc = 'abcdefghjkmnpqrstuvwxyz23456789';
        $tail = '';
        for ($i = 0; $i < 5; $i++) $tail .= $abc[random_int(0, strlen($abc) - 1)];
        return self::WORDS[random_int(0, count(self::WORDS) - 1)] . '-' . $tail;
    }

    /** '/admin/x?y' → '/werkstatt-k7m2x/x?y'; andere Pfade unverändert */
    public static function toPublic(string $path): string
    {
        if (!self::isInternal($path)) return $path;
        $p = self::prefix();
        return $p === self::INTERNAL ? $path : $p . substr($path, strlen(self::INTERNAL));
    }

    /** '/werkstatt-k7m2x/x' → '/admin/x'; null, wenn der Pfad nicht zur eigenen Adresse gehört */
    public static function toInternal(string $path): ?string
    {
        $p = self::prefix();
        if ($p === self::INTERNAL) return null;
        if ($path === $p || str_starts_with($path, $p . '/')) return self::INTERNAL . substr($path, strlen($p));
        return null;
    }

    /** /admin oder /admin/… (auch mit ?Abfrage oder #Anker) */
    public static function isInternal(string $path): bool
    {
        if (!str_starts_with($path, self::INTERNAL)) return false;
        $c = $path[strlen(self::INTERNAL)] ?? '';
        return $c === '' || $c === '/' || $c === '?' || $c === '#';
    }

    /** Über die Umgebungsvariable festgelegt (dann nicht in der Verwaltung änderbar) */
    public static function fromEnv(): bool
    {
        return (string) getenv('KLXM_ADMIN_PATH') !== '';
    }

    /** Ändern in der Verwaltung: gilt für alle Websites der Installation → bei mehreren Websites nur die Netzwerk-Administration */
    public static function canManage(): bool
    {
        if (!can('system.manage')) return false;
        return !Sites::multi() || Network\Network::isNetworkUser();
    }

    /**
     * Adresse speichern (config/config.local.php, alle Websites der Installation); '' = wieder /admin.
     * @return ?string Fehlermeldung oder null
     */
    public static function save(string $slug): ?string
    {
        $slug = self::normalize($slug);
        if (self::fromEnv()) return __('Die Adresse ist über die Umgebungsvariable KLXM_ADMIN_PATH festgelegt – bitte dort ändern.');
        if ($slug !== '' && ($err = self::error($slug))) return $err;
        if ($slug !== '' && Pages::byPath('/' . $slug)) return __('Unter /{slug} gibt es bereits eine Seite – bitte eine andere Adresse wählen.', ['slug' => $slug]);
        $file = ROOT . '/config/config.local.php';
        $local = is_file($file) ? require $file : [];
        if (!is_array($local)) return __('config/config.local.php ist nicht lesbar.');
        if ($slug === '') unset($local['admin_path']); else $local['admin_path'] = $slug;
        $php = "<?php\n// Lokale Konfiguration – NICHT versionieren, NICHT weitergeben.\n// Geändert am " . date('Y-m-d H:i') . " (Adresse der Verwaltung)\nreturn " . var_export($local, true) . ";\n";
        if (!is_writable($file) || @file_put_contents($file, $php, LOCK_EX) === false) {
            return __('config/config.local.php ist nicht beschreibbar – bitte per Konsole setzen: {cmd}', ['cmd' => 'php bin/console admin:path ' . ($slug ?: '--reset')]);
        }
        @chmod($file, 0640);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
        self::reset($slug === '' ? null : '/' . $slug);
        if ($slug === '') self::$prefix = self::INTERNAL;
        return null;
    }

    /** Darf /admin… ohne Anmeldung direkt erreichbar bleiben? (Netzwerk-SSO, öffentliche Routen von Erweiterungen) */
    public static function directAllowed(string $internalPath, ?Http\Router $router = null, string $method = 'GET'): bool
    {
        if ($internalPath === '/admin/sso' || str_starts_with($internalPath, '/admin/sso/')) return true;
        return $router !== null && $router->isPublicExtensionRoute($method, $internalPath);
    }
}
