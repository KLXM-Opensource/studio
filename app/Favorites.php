<?php
declare(strict_types=1);

namespace Core;

/**
 * Favoriten je Benutzer (Spalte users.favorites, JSON-Liste [{url, title, icon, added}], höchstens 30).
 * Jede per GET aufrufbare Seite der Verwaltung kann gemerkt werden – inkl. Query und #Hash
 * (z. B. /admin/data/termine/12, /admin/settings#hero, /admin/media#m123). Benutzer gehören zur Website,
 * die Favoriten damit auch. Oberfläche: Seitenleiste (views/layout.php), Stern neben der H1 (resources/js/_favorites.js),
 * Konto-Seite; Endpunkte: Http\Controllers\Admin\FavoriteController.
 */
final class Favorites
{
    public const MAX = 30;
    public const TITLE_MAX = 80;

    /** Sitzungs- und Einmal-Parameter, die nie in einem Favoriten landen */
    private const STRIP = ['_csrf', 'csrf', 'token', 'sid', 'phpsessid', 'session', 'next', 'pwa', '_'];
    /** Nicht merkbar: Anmelden/Abmelden, Einrichtung, JSON-Endpunkte */
    private const DENY = '~^/admin/(login|logout|setup|api(/|$))~';

    /** @return list<array{url:string,title:string,icon:string,added:string}> */
    public static function all(int $uid): array
    {
        try {
            $json = app()->db->fetchValue('SELECT favorites FROM users WHERE id = ?', [$uid]);
        } catch (\Throwable) {
            return [];   // Spalte fehlt noch (vor „migrate“)
        }
        $list = json_decode((string) $json, true);
        if (!is_array($list)) return [];
        $out = [];
        foreach ($list as $f) {
            if (is_array($f) && is_string($f['url'] ?? null) && ($u = self::normalize($f['url'])) !== null) {
                $out[] = ['url' => $u, 'title' => self::title((string) ($f['title'] ?? '')) ?: $u,
                    'icon' => self::icon((string) ($f['icon'] ?? '')), 'added' => (string) ($f['added'] ?? '')];
            }
        }
        return array_slice($out, 0, self::MAX);
    }

    public static function save(int $uid, array $list): void
    {
        app()->db->update('users', ['favorites' => json_encode(array_values(array_slice($list, 0, self::MAX)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            'id = :id', ['id' => $uid]);
    }

    /** @return string|null Fehlermeldung oder null */
    public static function add(int $uid, string $url, string $title, string $icon): ?string
    {
        $u = self::normalize($url);
        if ($u === null) return __('Diese Adresse kann nicht als Favorit gespeichert werden.');
        $list = self::all($uid);
        if (self::index($list, $u) !== null) return null;   // schon vorhanden
        if (count($list) >= self::MAX) return __('Höchstens {n} Favoriten möglich – bitte zuerst einen entfernen.', ['n' => self::MAX]);
        $list[] = ['url' => $u, 'title' => self::title($title) ?: self::defaultTitle($u), 'icon' => self::icon($icon), 'added' => now()];
        self::save($uid, $list);
        return null;
    }

    public static function remove(int $uid, string $url): void
    {
        $list = self::all($uid);
        $i = self::index($list, (string) self::normalize($url));
        if ($i !== null) {
            array_splice($list, $i, 1);
            self::save($uid, $list);
        }
    }

    public static function rename(int $uid, string $url, string $title): void
    {
        $list = self::all($uid);
        $i = self::index($list, (string) self::normalize($url));
        $title = self::title($title);
        if ($i !== null && $title !== '') {
            $list[$i]['title'] = $title;
            self::save($uid, $list);
        }
    }

    /** Neue Reihenfolge (Liste von URLs; fehlende bleiben hinten in bisheriger Reihenfolge) */
    public static function reorder(int $uid, array $order): void
    {
        $list = self::all($uid);
        $out = [];
        foreach ($order as $url) {
            $i = is_string($url) ? self::index($list, (string) self::normalize($url)) : null;
            if ($i !== null && !isset($out[$i])) $out[$i] = $list[$i];
        }
        foreach ($list as $i => $f) $out[$i] ??= $f;
        self::save($uid, array_values($out));
    }

    /** Einen Favoriten um eine Position verschieben (-1 hoch, 1 runter) – Formular ohne JavaScript */
    public static function move(int $uid, string $url, int $dir): void
    {
        $list = self::all($uid);
        $i = self::index($list, (string) self::normalize($url));
        $j = $i === null ? null : $i + ($dir < 0 ? -1 : 1);
        if ($i !== null && isset($list[$j])) {
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
            self::save($uid, $list);
        }
    }

    /**
     * Lokale Verwaltungsadresse „/admin…?query#hash“ oder null.
     * Kein Schema/Host, kein „//“, nur GET-Seiten der Verwaltung (Router), Sitzungsparameter entfernt.
     */
    public static function normalize(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 500 || preg_match('~[\x00-\x1F\x7F\\\\]~', $url) || str_starts_with($url, '//')) return null;
        $base = base_path();
        if ($base !== '' && str_starts_with($url, $base . '/')) $url = substr($url, strlen($base));
        if (str_starts_with($url, '/index.php/')) $url = substr($url, 10);
        $p = parse_url($url);
        if ($p === false || isset($p['scheme']) || isset($p['host']) || isset($p['user']) || isset($p['port'])) return null;
        if (str_contains((string) ($p['path'] ?? ''), '//')) return null;
        $path = '/' . trim(rawurldecode((string) ($p['path'] ?? '')), '/');
        if (str_contains($path, '/..') || str_contains($path, '/./')) return null;
        if (($path !== '/admin' && !str_starts_with($path, '/admin/')) || preg_match(self::DENY, $path)) return null;
        if (!self::isPage($path)) return null;
        parse_str((string) ($p['query'] ?? ''), $q);
        foreach (array_keys($q) as $k) {
            if (in_array(strtolower((string) $k), self::STRIP, true)) unset($q[$k]);
        }
        $q = array_filter($q, fn($v) => $v !== '' && $v !== []);
        ksort($q);
        $hash = (string) ($p['fragment'] ?? '');
        $hash = preg_match('~^[\w\-.:/]{1,80}$~u', $hash) ? $hash : '';
        $enc = implode('/', array_map('rawurlencode', explode('/', $path)));
        return $enc . ($q ? '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986) : '') . ($hash !== '' ? '#' . $hash : '');
    }

    /** Gibt es für diesen Pfad eine GET-Seite der Verwaltung (nicht die Website-Auffangroute)? */
    private static function isPage(string $path): bool
    {
        static $router = null;
        if ($router === null) {
            $router = new Http\Router();
            (require ROOT . '/app/routes.php')($router);
        }
        $pattern = $router->match('GET', $path);
        return $pattern !== null && str_starts_with($pattern, '/admin');
    }

    public static function title(string $t): string
    {
        $t = trim(preg_replace('~\s+~u', ' ', strip_tags($t)) ?? '');
        return mb_substr($t, 0, self::TITLE_MAX);
    }

    /** Symbol: Schlüssel eines Menüpunkts (data-ico, z. B. „data“) oder ein kurzes Zeichen (Symbol einer Datentabelle) */
    public static function icon(string $i): string
    {
        $i = trim($i);
        if (preg_match('~^[a-z][a-z0-9_-]{0,30}$~', $i)) return $i;
        return $i !== '' && mb_strlen($i) <= 4 && !preg_match('~[<>&"\'\s]~u', $i) ? $i : '';
    }

    public static function isGlyph(string $icon): bool
    {
        return $icon !== '' && !preg_match('~^[a-z][a-z0-9_-]*$~', $icon);
    }

    private static function defaultTitle(string $url): string
    {
        return self::title(rawurldecode((string) parse_url($url, PHP_URL_PATH))) ?: __('Favorit');
    }

    private static function index(array $list, string $url): ?int
    {
        foreach ($list as $i => $f) {
            if ($f['url'] === $url) return $i;
        }
        return null;
    }

    /**
     * Einmalige Migration (migrate): Im Handbuch des Themes „basis“ stehen die Grundeinstellungen jetzt unter #einstellungen,
     * #daten ist das Kapitel „Eigene Daten“. Alte Favoriten /admin/hilfe#daten zeigen deshalb auf #einstellungen.
     * Nur auf Websites mit Theme „basis“, nur einmal (sys.fav_basis_anchor) – später bewusst gesetzte #daten-Favoriten bleiben.
     * @return int Anzahl geänderter Favoriten
     */
    public static function migrateBasisAnchors(): int
    {
        if (app()->theme->name !== 'basis' || app()->settings->get('sys.fav_basis_anchor')) return 0;
        $n = 0;
        try {
            $rows = app()->db->fetchAll("SELECT id, favorites FROM users WHERE favorites LIKE '%/admin/hilfe#daten%'");
        } catch (\Throwable) {
            return 0;   // Spalte fehlt noch
        }
        foreach ($rows as $row) {
            $list = json_decode((string) $row['favorites'], true);
            if (!is_array($list)) continue;
            $hit = 0;
            foreach ($list as &$f) {
                if (is_array($f) && ($f['url'] ?? null) === '/admin/hilfe#daten') { $f['url'] = '/admin/hilfe#einstellungen'; $hit++; }
            }
            unset($f);
            if ($hit) { self::save((int) $row['id'], $list); $n += $hit; }
        }
        app()->settings->set('sys.fav_basis_anchor', 1);
        return $n;
    }
}
