<?php
declare(strict_types=1);

namespace Core\Dashboard;

use Core\Features;
use Core\Theme;

/**
 * Übersicht der Verwaltung (/admin): Karten, persönliche Anordnung, Zwischenspeicher der Statistiken, kleine SVG-Grafiken.
 *
 * Karten: Kern-Karten (CARDS) + Karten aktiver Erweiterungen (Extension::dashboard → Schlüssel „x-{erweiterung}-{name}“).
 * Anordnung je Konto in users.ui_prefs → "dash": {"order": [...], "hidden": [...], "closed": [...]} (wie Akzentfarbe/Favoriten).
 * Schwere Karten (lazy) lädt resources/js/dashboard.js über GET /admin/api/dashboard/{karte} nach; die Daten liegen
 * TTL Sekunden je Website und Rolle in storage/cache/dashboard (ohne JavaScript: /admin?alle=1 rendert alles sofort).
 */
final class Dashboard
{
    public const TTL = 300;

    /** Größen im 12er-Raster (Desktop): third = 4, half = 6, two-thirds = 8, full = 12 */
    public const SIZES = ['third' => 4, 'half' => 6, 'two-thirds' => 8, 'full' => 12];

    /** Kern-Karten in der Standard-Reihenfolge: [Titel, Symbol, Größe, nachladen] */
    private static function core(): array
    {
        return [
            'figures' => [__('Kennzahlen'), 'chart-bar', 'full', false],
            'todo' => [__('Was ist zu tun?'), 'list-checks', 'two-thirds', false],
            'help' => [__('Hilfe & Einstieg'), 'lifebuoy', 'third', false],
            'activity' => [__('Aktivität (30 Tage)'), 'chart-line-up', 'half', true],
            'recent' => [__('Zuletzt bearbeitet'), 'clock-clockwise', 'half', false],
            'requests' => [__('Anfragen je Woche'), 'tray', 'third', true],
            'top' => [__('Meistbearbeitete Seiten'), 'fire', 'third', true],
            'upcoming' => [__('Termine (7 Tage)'), 'calendar-dots', 'third', true],
            'search' => [__('Gesucht, nicht gefunden'), 'magnifying-glass', 'half', true],
            'health' => [__('Technik & Betrieb'), 'heartbeat', 'half', true],
        ];
    }

    public static function isAdmin(array $user): bool
    {
        return in_array($user['role'] ?? '', ['admin', 'network'], true) || can('system.manage');
    }

    /** Welche Kern-Karte darf dieser Benutzer sehen? */
    private static function allowed(string $key, array $user): bool
    {
        $pages = can('pages.edit');
        return match ($key) {
            'figures', 'todo', 'help' => true,
            'activity', 'recent' => $pages || Metrics::tables() !== [],
            'requests' => can('requests.read') && Features::on('requests') && \Core\Data\Inbox::readable() !== [],
            'top' => $pages,
            'upcoming' => Features::on('calendar', false) && array_filter(\Core\Data\Calendar::tables(), fn($t) => can('data.edit', $t['handle'])) !== [],
            'search' => $pages && Metrics::searchMisses(1) !== null,
            'health' => self::isAdmin($user),
            default => false,
        };
    }

    /**
     * Alle sichtbaren Karten in der Reihenfolge des Benutzers.
     * @return array<string, array{key: string, title: string, icon: string, size: string, lazy: bool, hidden: bool, closed: bool, ext?: array}>
     */
    public static function cards(array $user): array
    {
        $all = [];
        foreach (self::core() as $k => [$title, $icon, $size, $lazy]) {
            if (self::allowed($k, $user)) $all[$k] = ['key' => $k, 'title' => $title, 'icon' => $icon, 'size' => $size, 'lazy' => $lazy];
        }
        foreach (self::extensions($user)['cards'] as $k => $c) {
            $all[$k] = ['key' => $k, 'title' => $c['title'], 'icon' => $c['icon'], 'size' => $c['size'], 'lazy' => $c['lazy'], 'ext' => $c];
        }
        $p = self::prefs($user);
        $order = array_values(array_unique(array_merge(array_values(array_intersect($p['order'], array_keys($all))), array_keys($all))));
        $out = [];
        foreach ($order as $k) {
            $out[$k] = $all[$k] + ['hidden' => in_array($k, $p['hidden'], true), 'closed' => in_array($k, $p['closed'], true)];
        }
        return $out;
    }

    /** Beiträge der Erweiterungen (je Anfrage einmal) */
    public static function extensions(array $user): array
    {
        static $cache = null;
        return $cache ??= \Core\Extensions::dashboard($user);
    }

    // ================================================================= Persönliche Anordnung

    public static function prefs(array $user): array
    {
        $p = json_decode((string) ($user['ui_prefs'] ?? ''), true);
        $d = is_array($p) && is_array($p['dash'] ?? null) ? $p['dash'] : [];
        $list = fn($v) => array_values(array_filter((array) $v, fn($k) => is_string($k) && preg_match('~^[a-z][a-z0-9-]{1,60}$~', $k)));
        return ['order' => $list($d['order'] ?? []), 'hidden' => $list($d['hidden'] ?? []), 'closed' => $list($d['closed'] ?? [])];
    }

    /** Speichern (null = Standard wiederherstellen) – andere Einträge in ui_prefs bleiben unverändert */
    public static function savePrefs(int $userId, ?array $dash): void
    {
        $row = app()->db->fetch('SELECT ui_prefs FROM users WHERE id = ?', [$userId]);
        $p = json_decode((string) ($row['ui_prefs'] ?? ''), true);
        $p = is_array($p) ? $p : [];
        if ($dash === null) {
            unset($p['dash']);
        } else {
            $p['dash'] = self::prefs(['ui_prefs' => json_encode(['dash' => $dash])]);
            $p['dash'] = array_map(fn($l) => array_slice($l, 0, 60), $p['dash']);
        }
        app()->db->update('users', ['ui_prefs' => $p ? json_encode($p) : null], 'id = :id', ['id' => $userId]);
    }

    /**
     * Eine Änderung anwenden: up | down | hide | show | open | close | order (Liste) | reset.
     * @param list<string> $visible aktuelle Reihenfolge aller sichtbaren Karten (für up/down)
     */
    public static function apply(array $user, string $do, string $card, array $visible, array $order = []): void
    {
        if ($do === 'reset') {
            self::savePrefs((int) $user['id'], null);
            return;
        }
        $p = self::prefs($user);
        $cur = array_values(array_unique(array_merge(array_intersect($p['order'], $visible), $visible)));
        $i = array_search($card, $cur, true);
        $toggle = function (string $list, bool $on) use (&$p, $card) {
            $p[$list] = array_values(array_diff($p[$list], [$card]));
            if ($on) $p[$list][] = $card;
        };
        switch ($do) {
            case 'up':
            case 'down':
                if ($i === false) break;
                // über ausgeblendete Karten hinweg zur nächsten sichtbaren tauschen
                $step = $do === 'up' ? -1 : 1;
                for ($j = $i + $step; $j >= 0 && $j < count($cur); $j += $step) {
                    if (!in_array($cur[$j], $p['hidden'], true)) {
                        [$cur[$i], $cur[$j]] = [$cur[$j], $cur[$i]];
                        break;
                    }
                }
                $p['order'] = $cur;
                break;
            case 'order':
                $p['order'] = array_values(array_unique(array_merge(array_intersect($order, $visible), $cur)));
                break;
            case 'hide': $toggle('hidden', true); break;
            case 'show': $toggle('hidden', false); break;
            case 'close': $toggle('closed', true); break;
            case 'open': $toggle('closed', false); break;
        }
        self::savePrefs((int) $user['id'], $p);
    }

    // ================================================================= Nachgeladene Karten

    /** Daten einer nachgeladenen Karte (zwischengespeichert je Website + Rolle); HTML-Ausgabe über die Ansicht dashboard/_{karte}.php */
    public static function data(string $key, array $user): mixed
    {
        $fn = match ($key) {
            'activity' => fn() => Metrics::activity(),
            'requests' => fn() => Metrics::requestsWeekly(),
            'top' => fn() => Metrics::topPages(),
            'upcoming' => fn() => Metrics::upcoming(),
            'search' => fn() => Metrics::searchMisses(),
            'health' => fn() => array_map(fn($l, $v) => [$l, $v], array_keys($h = Metrics::health()), $h),
            default => null,
        };
        return $fn ? self::cached($key . '-' . ($user['role'] ?? ''), $key === 'health' ? 600 : self::TTL, $fn) : null;   // Betrieb: 10 Min. (prüft u. a. KI-Anbieter)
    }

    /** HTML einer Karte (Kern: Ansicht; Erweiterung: render()) */
    public static function render(string $key, array $user, array $card): string
    {
        if (isset($card['ext'])) {
            $c = $card['ext'];
            return $c['lazy'] && $c['ttl'] > 0 ? (string) self::cached($key, $c['ttl'], $c['render']) : $c['render']();
        }
        $file = ROOT . '/app/Admin/views/dashboard/_' . $key . '.php';
        if (!is_file($file)) return '';
        return Theme::capture($file, ['user' => $user, 'data' => self::data($key, $user)]);
    }

    public static function cached(string $key, int $ttl, callable $fn): mixed
    {
        $f = site()->storage('cache/dashboard/' . preg_replace('~[^a-z0-9_-]~i', '_', $key) . '.json');
        if (is_file($f) && filemtime($f) > time() - $ttl) {
            $d = json_decode((string) file_get_contents($f), true);
            if (is_array($d) && array_key_exists('v', $d)) return $d['v'];
        }
        $v = $fn();
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0770, true);
        @file_put_contents($f, json_encode(['v' => $v], JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $v;
    }

    /** Zwischenspeicher leeren (z. B. nach Tests) */
    public static function forget(): void
    {
        foreach (glob(site()->storage('cache/dashboard/*.json')) ?: [] as $f) @unlink($f);
    }

    // ================================================================= Hilfe & Einstieg

    /** Top-3-Tutorials der empfohlenen Zielgruppe (Rolle) mit Link zum Video (Produkt-Website, config 'docs_url'), Trailer-Link, Neuigkeiten aus CHANGELOG.md (nur Administration) */
    public static function help(array $user): array
    {
        $cat = \Core\Tutorials::catalogue();
        $tracks = $cat['tracks'];
        // gleiche Empfehlung wie „Handbuch & Hilfe › Tutorials“
        $track = \Core\Tutorials::recommended($tracks);
        $pick = [];
        foreach ($cat['tutorials'] as $slug => $t) {
            if ($t['track'] !== $track || $t['off']) continue;
            $pick[] = ['slug' => $slug, 'title' => (string) $t['title'], 'icon' => (string) ($t['icon'] ?? 'play-circle'), 'summary' => (string) ($t['summary'] ?? ''),
                'level' => (string) $t['levelLabel'], 'web' => $t['web']];
            if (count($pick) === 3) break;
        }
        $news = [];
        if (self::isAdmin($user) && is_file(ROOT . '/CHANGELOG.md')) {
            $version = '';
            foreach (file(ROOT . '/CHANGELOG.md', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
                if (str_starts_with($line, '## ')) {
                    if ($version !== '') break;
                    $version = trim(substr($line, 3));
                } elseif ($version !== '' && str_starts_with($line, '### ')) {
                    $news[] = trim(str_replace(['**', '`'], '', substr($line, 4)));
                    if (count($news) === 4) break;
                }
            }
            $news = ['version' => $version, 'items' => $news];
        }
        return ['track' => $tracks[$track]['title'] ?? '', 'tutorials' => $pick, 'trailer' => \Core\Tutorials::trailerUrl(), 'docsHost' => \Core\Tutorials::docsHost(),
            'news' => $news, 'ai' => \Core\AI\Assistant::available(), 'brand' => \Core\AI\Assist::brand()];
    }

    // ================================================================= Grafiken (Inline-SVG, CSP-tauglich: nur Attribute)

    private static function n(float $x): string
    {
        return rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.') ?: '0';
    }

    /** Rundinstrument über 270° wie der Block „Kennzahlen mit Skala“ (dials); $level 0–100, null = neutrale Skala */
    public static function gauge(?float $level): string
    {
        $pt = fn(float $deg, float $r): string => self::n(60 + $r * cos(deg2rad($deg))) . ' ' . self::n(60 + $r * sin(deg2rad($deg)));
        $arc = 'M' . $pt(135, 50) . 'A50 50 0 1 1 ' . $pt(45, 50);
        $out = '<svg class="dash-gauge" viewBox="0 0 120 120" aria-hidden="true" focusable="false"><path class="dash-gauge__track" d="' . $arc . '" pathLength="100"/>';
        if ($level !== null && $level > 0) {
            $out .= '<path class="dash-gauge__arc" d="' . $arc . '" pathLength="100" stroke-dasharray="' . self::n(max(1.5, min(100, $level))) . ' 200"/>';
        }
        return $out . '</svg>';
    }

    /**
     * Säulen (eine Reihe) als SVG: $rows = [[Beschriftung für Tooltip/Tabelle, Wert], …]. Höhe per CSS, Breite 100 %.
     * Jede Säule trägt ein <title> (Tooltip); die Tabelle daneben ist die zugängliche Alternative.
     */
    public static function bars(array $rows, string $label): string
    {
        $n = max(1, count($rows));
        $max = max(1, ...array_map(fn($r) => (int) $r[1], $rows ?: [[0, 0]]));
        $w = 10;
        $h = 100;
        $out = '<svg class="dash-bars" viewBox="0 0 ' . ($n * $w) . ' ' . $h . '" preserveAspectRatio="none" role="img" aria-label="' . e($label) . '" focusable="false">';
        $out .= '<line class="dash-bars__grid" x1="0" x2="' . ($n * $w) . '" y1="0.5" y2="0.5" vector-effect="non-scaling-stroke"/>';
        foreach (array_values($rows) as $i => [$lab, $v]) {
            $bh = (int) $v > 0 ? max(2, $v / $max * ($h - 4)) : 0;
            $out .= '<g><title>' . e($lab . ': ' . $v) . '</title><rect class="dash-bars__hit" x="' . ($i * $w) . '" y="0" width="' . $w . '" height="' . $h . '"/>';
            if ($bh > 0) $out .= '<rect class="dash-bars__bar" x="' . self::n($i * $w + 1.5) . '" y="' . self::n($h - $bh) . '" width="7" height="' . self::n($bh) . '"/>';
            $out .= '</g>';
        }
        return $out . '<line class="dash-bars__base" x1="0" x2="' . ($n * $w) . '" y1="' . ($h - 0.5) . '" y2="' . ($h - 0.5) . '" vector-effect="non-scaling-stroke"/></svg>';
    }

    /** Relative Zeitangabe: „vor 5 Min.“, „gestern“, „12.09.“ */
    public static function ago(string $at): string
    {
        $t = strtotime($at);
        if (!$t) return '';
        $d = time() - $t;
        return match (true) {
            $d < 90 => __('gerade eben'),
            $d < 3600 => __('vor {n} Min.', ['n' => (int) round($d / 60)]),
            $d < 86400 && date('Y-m-d', $t) === date('Y-m-d') => __('heute, {time}', ['time' => date('H:i', $t)]),
            date('Y-m-d', $t) === date('Y-m-d', time() - 86400) => __('gestern, {time}', ['time' => date('H:i', $t)]),
            $d < 7 * 86400 => __('vor {n} Tagen', ['n' => (int) ceil($d / 86400)]),
            default => date('d.m.Y', $t),
        };
    }
}
