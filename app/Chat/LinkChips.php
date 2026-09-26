<?php
declare(strict_types=1);

namespace Core\Chat;

use Core\Support\Support;

/**
 * Verweise im Chat: Adressen der Verwaltung dieser Installation werden beim Senden erkannt und mit Titel und Symbol
 * gemerkt (Schnappschuss – der Titel stammt aus Sicht der sendenden Person, der Link führt zur jeweiligen Seite,
 * die selbst die Rechte prüft). Erkannt werden:
 *   /admin/pages/{id}                 Seite            /admin/data/{tabelle}/{id}     Eintrag
 *   /admin/media#m{id} bzw. ?m={id}   Datei            /admin/support/meldung/{id}     Support-Meldung
 *   /admin/support/wissen/{id}        Wissensartikel   /admin/support/fragen/{id}      Frage
 *   Favoriten der sendenden Person (Titel/Symbol des Favoriten), sonst jede andere /admin-Adresse (Titel = Bereich)
 * Absolute Adressen dieser Website (https://host/admin/…) und relative (/admin/…) werden gleich behandelt.
 */
final class LinkChips
{
    /** @return list<array{src:string,path:string,title:string,icon:string,kind:string,site:string}> */
    public static function extract(string $body): array
    {
        $base = preg_quote(rtrim(site_url(), '/'), '~');
        $bp = preg_quote(base_path(), '~');
        if (!preg_match_all('~(?<![\w/\]\(])(?:' . $base . ')?' . $bp . '(/admin(?:/[^\s<>"\'`)\]]*)?(?:[?#][^\s<>"\'`)\]]*)?)~u', $body, $m, PREG_SET_ORDER)) return [];
        $out = [];
        $seen = [];
        foreach ($m as $hit) {
            $src = rtrim($hit[0], '.,;:!?');
            $path = rtrim($hit[1], '.,;:!?');
            if (isset($seen[$src]) || count($out) >= 10) continue;
            $seen[$src] = true;
            $info = self::resolve($path);
            if ($info) $out[] = ['src' => $src, 'path' => $path, 'site' => site()->key] + $info;
        }
        // Längere zuerst ersetzen (…/pages/12 nicht innerhalb von …/pages/123)
        usort($out, fn($a, $b) => strlen($b['src']) <=> strlen($a['src']));
        return $out;
    }

    /** Titel und Symbol zu einer Verwaltungs-Adresse (null = nicht verlinken) */
    public static function resolve(string $path): ?array
    {
        if (str_starts_with($path, '/admin/api') || str_starts_with($path, '/admin/logout') || str_contains($path, '..')) return null;
        $p = parse_url($path);
        $route = (string) ($p['path'] ?? '');
        $frag = (string) ($p['fragment'] ?? '');
        parse_str((string) ($p['query'] ?? ''), $q);
        try {
            if (preg_match('~^/admin/pages/(\d+)~', $route, $x) && ($page = \Core\Pages::find((int) $x[1]))) {
                return ['title' => (string) $page['title'], 'icon' => 'file-text', 'kind' => 'page'];
            }
            if (preg_match('~^/admin/data/([\w-]+)/(\d+)$~', $route, $x) && ($t = \Core\Data\Tables::find($x[1])) && can('data.edit', $t['handle'])
                && ($entry = \Core\Data\Entries::find($t, (int) $x[2]))) {
                return ['title' => \Core\Data\Entries::title($t, $entry), 'icon' => \Core\Icons::resolve((string) ($t['icon'] ?? '')) ?? 'database', 'kind' => 'entry', 'sub' => (string) $t['name']];
            }
            $mid = preg_match('~^m(\d+)$~', $frag, $x) ? (int) $x[1] : (int) ($q['m'] ?? 0);
            if ($route === '/admin/media' && $mid > 0 && ($media = \Core\Media::find($mid))) {
                return ['title' => \Core\Media::displayName($media), 'icon' => 'image', 'kind' => 'media'];
            }
            if (preg_match('~^/admin/support/meldung/(\d+)~', $route, $x) && ($issue = \Core\Support\Tickets::find((int) $x[1]))) {
                return ['title' => '#' . (int) $issue['id'] . ' ' . $issue['title'], 'icon' => 'lifebuoy', 'kind' => 'issue'];
            }
            if (preg_match('~^/admin/support/wissen/(\d+)$~', $route, $x) && ($t = Support::db()->fetchValue('SELECT title FROM articles WHERE id = ?', [(int) $x[1]]))) {
                return ['title' => (string) $t, 'icon' => 'books', 'kind' => 'article'];
            }
            if (preg_match('~^/admin/support/fragen/(\d+)$~', $route, $x) && ($t = Support::db()->fetchValue('SELECT title FROM questions WHERE id = ?', [(int) $x[1]]))) {
                return ['title' => (string) $t, 'icon' => 'question', 'kind' => 'question'];
            }
        } catch (\Throwable $e) {
            error_log('[chat] link: ' . $e->getMessage());
        }
        $u = app()->auth->user();
        if ($u && ($norm = \Core\Favorites::normalize($path)) !== null) {
            foreach (\Core\Favorites::all((int) $u['id']) as $f) {
                if ($f['url'] === $norm) return ['title' => $f['title'], 'icon' => \Core\Icons::resolve($f['icon'] ?: 'fav') ?? 'star', 'kind' => 'favorite'];
            }
        }
        $area = explode('/', trim(substr($route, 6), '/'))[0] ?? '';
        $label = match ($area) {
            '' => __('Übersicht'), 'pages' => __('Seiten'), 'settings' => app()->theme->settingsTitle(), 'media' => __('Medien'), 'data' => __('Daten'),
            'requests' => __('Anfragen'), 'support' => __('Support'), 'ai' => \Core\AI\Assist::brand(), 'system' => __('Grundeinstellungen'),
            'design' => __('Design'), 'users' => __('Benutzer & Rollen'), 'hilfe' => __('Handbuch & Hilfe'), 'chat' => __('Chat'), 'account' => __('Konto'),
            default => null,
        };
        if ($label === null) return null;
        return ['title' => $label . ($route !== '/admin/' . $area && $area !== '' ? ' · ' . basename($route) : ''), 'icon' => \Core\Icons::resolve($area ?: 'dashboard') ?? 'link', 'kind' => 'admin'];
    }

    /** Chip-HTML (Link auf der Website des Verweises; fremde Websites mit absoluter Adresse) */
    public static function html(array $l): string
    {
        $path = (string) ($l['path'] ?? '');
        if (!str_starts_with($path, '/admin')) return e((string) ($l['src'] ?? ''));
        $site = (string) ($l['site'] ?? site()->key);
        $href = $site === site()->key ? url($path) : Support::urlOn($site, $path);
        $other = $site !== site()->key ? ' · ' . Support::siteLabel($site) : '';
        $icon = preg_match('~^[a-z][a-z0-9-]{0,39}$~', (string) ($l['icon'] ?? '')) ? (string) $l['icon'] : 'link';
        $title = trim((string) ($l['title'] ?? '')) ?: $path;
        return '<a class="uc-chip uc-chip--' . e((string) ($l['kind'] ?? 'admin')) . '" href="' . e($href) . '">' . icon($icon, ['class' => 'uc-chip__ico'])
            . '<span>' . e(mb_substr($title, 0, 80)) . '</span>' . ($other !== '' || !empty($l['sub']) ? '<small>' . e(trim(($l['sub'] ?? '') . $other, ' ·')) . '</small>' : '') . '</a>';
    }
}
