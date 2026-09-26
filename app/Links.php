<?php
declare(strict_types=1);

namespace Core;

use Core\Data\Entries;
use Core\Data\Tables;

/**
 * Linkziele: stabile Verweise, Auflösung und Quellen für die Linkauswahl (resources/js/_links.js, GET /admin/api/links).
 *
 * Verweise (in Link-Feldern als Wert, im Rich-Text als data-link am <a>) überleben geänderte Adressen:
 *   page:12            Seite 12              page:12#anker   Anker auf Seite 12
 *   entry:news:5       Eintrag 5 der Tabelle „news“ (Detailseite)
 *   media:9            Datei 9 (direkt)      media:9:viewer  PDF 9 im PDF-Viewer (/pdf/9)
 */
final class Links
{
    public const REF = '~^(page:\d+(#[\w-]{1,80})?|entry:[a-z][a-z0-9_]{0,40}:\d+|media:\d+(:viewer)?)$~';

    public static function isRef(string $v): bool
    {
        return (bool) preg_match(self::REF, $v);
    }

    /** Aktuelle Adresse eines Verweises (null: unbekannt oder Ziel gelöscht) */
    public static function href(string $ref): ?string
    {
        if (!preg_match(self::REF, $ref)) return null;
        [$kind, $rest] = explode(':', $ref, 2);
        if ($kind === 'page') {
            [$id, $anchor] = array_pad(explode('#', $rest, 2), 2, '');
            $p = Pages::find((int) $id);
            return $p ? Pages::url($p) . ($anchor !== '' ? '#' . $anchor : '') : null;
        }
        if ($kind === 'entry') {
            [$handle, $id] = explode(':', $rest);
            $t = Tables::findContent($handle);
            $e = $t ? Entries::find($t, (int) $id) : null;
            return $e ? Entries::href($t, $e) : null;
        }
        [$id, $viewer] = array_pad(explode(':', $rest), 2, '');
        $m = Media::find((int) $id);
        if (!$m) return null;
        return ($viewer !== '' ? Media::viewerUrl($m) : null) ?? Media::url($m);
    }

    /**
     * Lesbare Beschreibung eines Link-Werts (Feld „link“: Anzeige des gewählten Ziels).
     * @return array{kind: string, type: string, label: string, href: string, missing: bool}
     */
    public static function describe(string $v): array
    {
        $v = trim($v);
        $out = ['kind' => 'url', 'type' => __('Adresse'), 'label' => $v, 'href' => $v, 'missing' => false];
        if ($v === '') return $out;
        if (self::isRef($v)) {
            $href = self::href($v);
            [$kind, $rest] = explode(':', $v, 2);
            $out['href'] = (string) $href;
            $out['missing'] = $href === null;
            if ($kind === 'page') {
                [$id, $anchor] = array_pad(explode('#', $rest, 2), 2, '');
                $p = Pages::find((int) $id);
                return ['kind' => 'page', 'type' => $anchor !== '' ? __('Anker') : __('Seite'),
                    'label' => $p ? $p['title'] . ($anchor !== '' ? ' → #' . $anchor : '') : __('Seite nicht gefunden'), 'lang' => $p['lang'] ?? ''] + $out;
            }
            if ($kind === 'entry') {
                [$handle, $id] = explode(':', $rest);
                $t = Tables::findContent($handle);
                $e = $t ? Entries::find($t, (int) $id) : null;
                return ['kind' => 'entry', 'type' => $t ? (string) $t['singular'] : __('Eintrag'),
                    'label' => $t && $e ? Entries::title($t, $e) : __('Eintrag nicht gefunden'), 'icon' => (string) ($t['icon'] ?? '')] + $out;
            }
            [$id, $viewer] = array_pad(explode(':', $rest), 2, '');
            $m = Media::find((int) $id);
            return ['kind' => 'file', 'type' => $viewer !== '' ? __('PDF im Viewer') : __('Datei'),
                'label' => $m ? Media::displayName($m) : __('Datei nicht gefunden')] + $out;
        }
        if ($v[0] === '#') return ['kind' => 'anchor', 'type' => __('Anker'), 'label' => $v] + $out;
        if (str_starts_with($v, 'mailto:')) return ['kind' => 'mail', 'type' => __('E-Mail'), 'label' => substr($v, 7)] + $out;
        if (str_starts_with($v, 'tel:')) return ['kind' => 'tel', 'type' => __('Telefon'), 'label' => substr($v, 4)] + $out;
        if (in_array($v, (array) (app()->theme->def['link_keywords'] ?? []), true)) {
            $labels = (array) project('link_labels', []);
            return ['kind' => 'keyword', 'type' => __('Sonderziel'), 'label' => (string) ($labels[$v] ?? $v)] + $out;
        }
        if ($v[0] === '/') {
            $p = Pages::byPath(parse_url($v, PHP_URL_PATH) ?: $v);
            return ['kind' => 'path', 'type' => __('Interner Pfad'), 'label' => $p ? $p['title'] . ' (' . $v . ')' : $v] + $out;
        }
        return ['kind' => 'url', 'type' => __('Externe Adresse'), 'label' => (string) (parse_url($v, PHP_URL_HOST) ?: $v) . ((string) (parse_url($v, PHP_URL_PATH) ?? '') !== '/' ? (string) parse_url($v, PHP_URL_PATH) : ''),
            'missing' => Sanitizer::safeHref($v) === null] + $out;
    }

    /**
     * Quellen für die Linkauswahl, nach Art gruppiert.
     * $o: q (Suche), page (ID der aktuellen Seite → deren Anker zuerst), mode (rich|field; field = mit Sonderzielen des Themes), limit
     * @return list<array{id: string, label: string, icon: string, items: list<array>}>
     */
    public static function sources(array $o = []): array
    {
        $q = trim((string) ($o['q'] ?? ''));
        $limit = max(3, min(50, (int) ($o['limit'] ?? ($q === '' ? 8 : 12))));
        $match = fn(string ...$hay) => $q === '' || array_filter($hay, fn($h) => mb_stripos($h, $q) !== false);
        $multi = Lang::multi();
        $groups = [];

        // Anker auf der aktuellen Seite
        $cur = !empty($o['page']) ? Pages::find((int) $o['page']) : null;
        if ($cur) {
            $items = [];
            foreach (Pages::anchors($cur, true) as $a => $label) {
                $label = trim(strip_tags((string) $label)) ?: (string) $a;
                if ($match($label, (string) $a)) $items[] = ['value' => '#' . $a, 'href' => '#' . $a, 'label' => $label, 'meta' => '#' . $a, 'kind' => 'anchor'];
            }
            if ($items) $groups[] = ['id' => 'anchors', 'label' => __('Anker auf dieser Seite'), 'icon' => 'hash', 'items' => array_slice($items, 0, $limit)];
        }

        // Seiten (mit Pfad im Seitenbaum und Sprache) – bei Suche auch Anker anderer Seiten
        $pages = $anchors = [];
        foreach (Pages::all() as $p) {
            $trail = implode(' › ', array_map(fn($a) => (string) $a['title'], Pages::ancestors($p)));
            $url = Pages::plainUrl($p);
            if ($match((string) $p['title'], (string) $p['slug'], $trail)) {
                $pages[] = ['value' => 'page:' . $p['id'], 'href' => $url, 'label' => (string) $p['title'],
                    'meta' => ($trail !== '' ? $trail . ' › ' : '') . $url, 'badge' => $multi ? strtoupper((string) ($p['lang'] ?? Lang::default())) : '',
                    'draft' => ($p['status'] ?? '') !== 'published', 'kind' => 'page'];
            }
            if ($q !== '' && (!$cur || (int) $cur['id'] !== (int) $p['id'])) {
                foreach (Pages::anchors($p, true) as $a => $label) {
                    $label = trim(strip_tags((string) $label)) ?: (string) $a;
                    if ($match($label, (string) $a)) $anchors[] = ['value' => 'page:' . $p['id'] . '#' . $a, 'href' => $url . '#' . $a,
                        'label' => $label, 'meta' => $p['title'] . ' → #' . $a, 'kind' => 'anchor'];
                }
            }
        }
        if ($pages) $groups[] = ['id' => 'pages', 'label' => __('Seiten'), 'icon' => 'file-text', 'items' => array_slice($pages, 0, $q === '' ? 15 : $limit), 'total' => count($pages)];
        if ($anchors) $groups[] = ['id' => 'page-anchors', 'label' => __('Anker auf anderen Seiten'), 'icon' => 'hash', 'items' => array_slice($anchors, 0, $limit)];

        // Einträge aller Inhaltstabellen mit Detailseite
        foreach (Tables::content() as $t) {
            if (($t['settings']['route'] ?? '') === '' || empty($t['settings']['detail_page_id'])) continue;
            try {
                $rows = Entries::query($t, ['status' => 'all', 'q' => $q, 'limit' => $limit]);
            } catch (\Throwable) {
                continue;
            }
            $items = [];
            foreach ($rows as $e) {
                $href = Entries::href($t, $e);
                if ($href === null) continue;
                $items[] = ['value' => 'entry:' . $t['handle'] . ':' . $e['id'], 'href' => $href, 'label' => Entries::title($t, $e),
                    'meta' => $href, 'badge' => $multi && !empty($e['lang']) ? strtoupper((string) $e['lang']) : '',
                    'draft' => ($e['status'] ?? 'published') !== 'published', 'kind' => 'entry'];
            }
            if ($items) $groups[] = ['id' => 'entries:' . $t['handle'], 'label' => (string) $t['name'], 'icon' => (string) ($t['icon'] ?? ''), 'items' => $items];
        }

        // Dateien: ohne Suche die neuesten Dokumente, mit Suche alle Arten
        $files = [];
        foreach (Media::all($q !== '' ? ['q' => $q] : []) as $m) {
            if ($q === '' && str_starts_with((string) $m['mime'], 'image/')) continue;
            $pdf = $m['mime'] === 'application/pdf';
            $files[] = ['value' => 'media:' . $m['id'] . ($pdf ? ':viewer' : ''), 'href' => $pdf ? (string) Media::viewerUrl($m) : Media::url($m),
                'file' => 'media:' . $m['id'], 'fileHref' => Media::url($m), 'pdf' => $pdf,
                'label' => Media::displayName($m), 'meta' => self::fileMeta($m), 'kind' => 'file',
                'thumb' => str_starts_with((string) $m['mime'], 'image/') ? Media::url($m, 480) : null];
            if (count($files) >= $limit) break;
        }
        if ($files) $groups[] = ['id' => 'files', 'label' => __('Dateien & Medien'), 'icon' => 'file-pdf', 'items' => $files];

        // Sonderziele des Themes (nur Link-Felder – im Rich-Text gibt es sie nicht)
        if (($o['mode'] ?? '') === 'field') {
            $labels = (array) project('link_labels', []);
            $items = [];
            foreach ((array) (app()->theme->def['link_keywords'] ?? []) as $v) {
                $label = (string) ($labels[$v] ?? $v);
                if ($match($label, (string) $v)) $items[] = ['value' => $v, 'href' => $v, 'label' => $label, 'meta' => $v, 'kind' => 'keyword'];
            }
            if ($items) $groups[] = ['id' => 'special', 'label' => __('Sonderziele des Kits'), 'icon' => 'star', 'items' => $items];
        }
        return $groups;
    }

    private static function fileMeta(array $m): string
    {
        return Media::typeLabel((string) $m['mime']) . ' · ' . Media::humanSize((int) $m['size']);
    }
}
