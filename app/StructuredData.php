<?php
declare(strict_types=1);

namespace Core;

use Core\Data\Calendar;
use Core\Data\Entries;
use Core\Data\Tables;

/**
 * Strukturierte Daten (schema.org, JSON-LD) als verknüpfter @graph je Seite.
 *
 *  - Organisation: liefert das Theme (theme.php → 'jsonld' => Funktion; vollständig auf der Startseite,
 *    sonst als Verweis). Standort der Karte ergänzt „geo“.
 *  - WebSite, WebPage, BreadcrumbList: automatisch für jede Seite und jede Detailseite.
 *  - Blöcke: Theme-Blöcke erklären ihre Daten deklarativ in theme.php, z. B.
 *      'accordion' => [..., 'jsonld' => ['type' => 'faq', 'items' => 'items', 'question' => 'q', 'answer' => 'a']]
 *      'video'     => [..., 'jsonld' => ['type' => 'video', 'url' => 'video_url', 'file' => 'video_file', 'poster' => 'poster', 'name' => 'title_strong', 'description' => 'caption']]
 *    oder mit einer Funktion: 'jsonld' => fn(Block $b): ?array => [...].
 *    Kern-Blöcke (Datenliste, Kalender, Nächste Termine) melden ihre Einträge selbst (itemList(), events()).
 *  - Detailseiten: Typ je Datentabelle (Einstellung „Schema.org-Typ“): NewsArticle, Article, BlogPosting, Event,
 *    Person, Product, Service, Place, Organization, CreativeWork – Felder werden über Typ und Namen zugeordnet.
 */
final class StructuredData
{
    public const TYPES = [
        '' => 'Automatisch (Termine → Event, sonst keiner)', 'none' => 'Keiner',
        'NewsArticle' => 'Nachricht (NewsArticle)', 'Article' => 'Artikel (Article)', 'BlogPosting' => 'Blogbeitrag (BlogPosting)',
        'Event' => 'Veranstaltung/Termin (Event)', 'Person' => 'Person', 'Product' => 'Produkt (Product)', 'Service' => 'Leistung (Service)',
        'Place' => 'Ort (Place)', 'Organization' => 'Organisation', 'CreativeWork' => 'Sonstiges Werk (CreativeWork)',
    ];

    /** Während des Renderns gesammelte Knoten der Blöcke */
    private static array $nodes = [];
    private static int $faqCount = 0;

    public static function reset(): void
    {
        self::$nodes = [];
        self::$faqCount = 0;
    }

    public static function add(array $node): void
    {
        if (!app()->editing) self::$nodes[] = $node;
    }

    // ------------------------------------------------------------------ Blöcke

    /** Nach dem Rendern eines Blocks: deklarierte Daten des Blocks übernehmen */
    public static function collect(Block $b): void
    {
        if (app()->editing) return;
        $spec = $b->def['jsonld'] ?? null;
        if (is_callable($spec)) {
            if ($n = $spec($b)) self::add($n);
            return;
        }
        if (!is_array($spec)) return;
        $d = $b->data;
        match ($spec['type'] ?? '') {
            'faq' => self::faq((array) ($d[$spec['items'] ?? 'items'] ?? []), (string) ($spec['question'] ?? 'q'), (string) ($spec['answer'] ?? 'a')),
            'video' => self::video($d, $spec),
            default => null,
        };
    }

    public static function faq(array $items, string $q, string $a): void
    {
        $qs = [];
        foreach ($items as $it) {
            $question = trim(strip_tags((string) ($it[$q] ?? '')));
            $answer = self::plain((string) ($it[$a] ?? ''));
            if ($question !== '' && $answer !== '' && !str_contains($question . $answer, '[')) {
                $qs[] = ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
            }
        }
        if ($qs) self::add(['@type' => 'FAQPage', '@id' => '#faq' . (++self::$faqCount), 'mainEntity' => $qs]);
    }

    private static function video(array $d, array $spec): void
    {
        $url = (string) ($d[$spec['url'] ?? 'video_url'] ?? '');
        $file = (int) ($d[$spec['file'] ?? 'video_file'] ?? 0);
        $node = ['@type' => 'VideoObject', 'name' => trim(strip_tags((string) ($d[$spec['name'] ?? 'title'] ?? ''))) ?: lt('Video')];
        if (!empty($spec['description']) && filled($d[$spec['description']] ?? '')) $node['description'] = self::plain((string) $d[$spec['description']]);
        if ($v = Embeds::parse($url)) {
            $meta = Embeds::meta($v);
            $node['embedUrl'] = str_replace('autoplay=1', 'autoplay=0', Embeds::playerUrl($v));
            $node['url'] = Embeds::watchUrl($v);
            if ($meta['title'] !== '' && $node['name'] === lt('Video')) $node['name'] = $meta['title'];
            if ($meta['poster']) $node['thumbnailUrl'] = site_url() . $meta['poster'];
        } elseif ($file && ($m = Media::find($file))) {
            $node['contentUrl'] = site_url() . Media::url($m);
            $node['uploadDate'] = date('c', strtotime((string) ($m['created_at'] ?? 'now')));
        } else {
            return;
        }
        if (($p = (int) ($d[$spec['poster'] ?? 'poster'] ?? 0)) && ($pm = Media::find($p))) $node['thumbnailUrl'] = site_url() . Media::url($pm, 1200);
        if (empty($node['thumbnailUrl'])) return;   // ohne Vorschaubild nicht gültig
        $node['uploadDate'] ??= date('c', strtotime((string) (app()->currentPage['published_at'] ?? 'now') ?: 'now'));
        self::add($node);
    }

    /** Datenliste: verlinkte Einträge als ItemList */
    public static function itemList(array $table, array $rows, string $name = ''): void
    {
        $items = [];
        foreach (array_values($rows) as $i => $e) {
            if (!($u = Entries::absUrl($table, $e))) continue;
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $u, 'name' => Entries::title($table, $e)];
        }
        if ($items) self::add(['@type' => 'ItemList', 'name' => $name ?: (string) $table['name'], 'itemListElement' => $items]);
    }

    /** Kalender/Nächste Termine: Termine als Event */
    public static function events(array $table, array $occurrences, int $max = 20): void
    {
        foreach (array_slice($occurrences, 0, $max) as $o) {
            self::add(self::event($table, $o['entry'], $o['start'], $o['end'], (bool) $o['all_day']));
        }
    }

    private static function event(array $table, array $e, \DateTimeInterface $start, ?\DateTimeInterface $end, bool $allDay): array
    {
        $c = Calendar::config($table);
        $n = ['@type' => 'Event', 'name' => Entries::title($table, $e),
            'startDate' => $allDay ? $start->format('Y-m-d') : $start->format('c'),
            'eventStatus' => 'https://schema.org/EventScheduled', 'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'organizer' => self::orgOf($table, $e)];
        if ($end) $n['endDate'] = $allDay ? $end->modify('-1 day')->format('Y-m-d') : $end->format('c');
        $loc = ($c['location'] ?? '') !== '' ? trim(Entries::text($table, $e, $c['location'])) : '';
        $foreign = Data\Shared::isForeign($table, $e);
        $n['location'] = $foreign ? ['@type' => 'Place', 'name' => $loc !== '' ? $loc : $n['organizer']['name']]
            : ($loc !== '' ? ['@type' => 'Place', 'name' => $loc] + self::orgAddress() : ['@type' => 'Place', 'name' => site_name()] + self::orgAddress());
        if (($c['description'] ?? '') !== '' && ($t = trim(Entries::text($table, $e, $c['description']))) !== '') $n['description'] = mb_strimwidth($t, 0, 300, '…');
        if ($u = Entries::absUrl($table, $e)) $n['url'] = $u;
        if ($img = self::image($table, $e)) $n['image'] = $img;
        return $n;
    }

    /**
     * Veranstalter/Herausgeber eines Eintrags: die eigene Organisation – bei fremden Einträgen geteilter Tabellen
     * die Ursprungs-Website (Name und Adresse aus dem Register, Core\Data\Shared).
     */
    public static function orgOf(array $table, array $e): array
    {
        if (!Data\Shared::isForeign($table, $e)) return ['@id' => site_url() . '/#org'];
        $info = Data\Shared::siteInfo((string) $e['origin_site'], $table['shared']['key']);
        return array_filter(['@type' => 'Organization', 'name' => $info['name'], 'url' => $info['url'] !== '' ? $info['url'] . '/' : null]);
    }

    // ------------------------------------------------------------------ Seite

    /** Vollständiger Graph für eine Seite bzw. Detailseite */
    public static function graph(array $page, ?array $table = null, ?array $entry = null, array $seo = []): ?array
    {
        if (empty($page['id']) && !$entry) return null;
        $base = site_url();
        $url = $seo['canonical'] ?? abs_url(Pages::url($page));
        $lang = str_replace('_', '-', Lang::current());
        $graph = [];

        // Organisation (Theme) – vollständig auf der Startseite, sonst schlanker Verweis
        $org = self::organization();
        if ($org) {
            $graph[] = !empty($page['is_home']) && !$entry ? $org
                : array_filter(['@type' => $org['@type'], '@id' => $org['@id'], 'name' => $org['name'] ?? null, 'url' => $org['url'] ?? null]);
        }
        $graph[] = array_filter(['@type' => 'WebSite', '@id' => $base . '/#website', 'url' => $base . url('/'), 'name' => site_name(),
            'inLanguage' => $lang, 'publisher' => $org ? ['@id' => $org['@id']] : null,
            'potentialAction' => Search\Search::potentialAction()]);   // Website-Suche (Sitelinks-Suchfeld)

        $crumbs = self::breadcrumb($page, $table, $entry);
        $webpage = array_filter([
            '@type' => 'WebPage', '@id' => $url . '#webpage', 'url' => $url, 'name' => $seo['title'] ?? $page['title'],
            'description' => ($seo['description'] ?? '') ?: null, 'inLanguage' => $lang, 'isPartOf' => ['@id' => $base . '/#website'],
            'breadcrumb' => $crumbs ? ['@id' => $url . '#breadcrumb'] : null,
            'primaryImageOfPage' => !empty($seo['og_image']) ? ['@type' => 'ImageObject', 'url' => $seo['og_image']] : null,
            'datePublished' => !empty($page['published_at']) ? date('c', strtotime((string) $page['published_at'])) : null,
            'dateModified' => !empty($page['updated_at']) ? date('c', strtotime((string) $page['updated_at'])) : null,
        ]);
        if ($crumbs) {
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $url . '#breadcrumb', 'itemListElement' => array_map(fn($c, $i) =>
                ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]], $crumbs, array_keys($crumbs))];
        }
        // Detailseite: Hauptobjekt des Eintrags
        if ($table && $entry && ($main = self::entryNode($table, $entry, $url))) {
            $main['@id'] = $url . '#main';
            $main['mainEntityOfPage'] = ['@id' => $url . '#webpage'];
            $webpage['mainEntity'] = ['@id' => $url . '#main'];
            $graph[] = $main;
        }
        $graph[] = $webpage;
        // Knoten der Blöcke (FAQ, Listen, Termine, Videos) – IDs seitenbezogen machen
        foreach (self::$nodes as $n) {
            if (isset($n['@id']) && str_starts_with($n['@id'], '#')) $n['@id'] = $url . $n['@id'];
            $graph[] = $n;
        }
        return ['@context' => 'https://schema.org', '@graph' => array_values($graph)];
    }

    /** Organisation aus dem Theme (+ Geo-Koordinaten der Karte) */
    public static function organization(): ?array
    {
        $fn = app()->theme->def['jsonld'] ?? null;
        if (!is_callable($fn)) return null;
        $home = Pages::home() ?? ['is_home' => 1, 'id' => 0];
        $org = $fn(['is_home' => 1] + $home);
        if (!is_array($org) || empty($org['@type'])) return null;
        unset($org['@context']);
        // Organisation bleibt die der Website – auch auf Landing-Domains (Core\Landings)
        $org['@id'] = main_url() . '/#org';
        if (Landings::current()) $org['url'] = main_url() . url('/');
        if (empty($org['geo']) && ($p = Maps::siteLocation())) {
            $org['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $p[0], 'longitude' => $p[1]];
        }
        return $org;
    }

    private static function orgAddress(): array
    {
        $org = self::organization();
        return !empty($org['address']) ? ['address' => $org['address']] : [];
    }

    /** Brotkrumen: Start › Elternseiten › Seite bzw. Liste › Eintrag */
    private static function breadcrumb(array $page, ?array $table, ?array $entry): array
    {
        $base = site_url();
        $home = Pages::home();
        $out = [];
        if ($entry && $table) {
            if ($home) $out[] = [$home['title'], abs_url(Pages::url($home))];
            $route = (string) ($table['settings']['route'] ?? '');
            if ($route !== '' && ($list = Pages::byPath($route)) && empty($list['is_home'])) {
                foreach (Pages::ancestors($list) as $a) $out[] = [$a['title'], abs_url(Pages::url($a))];
                $out[] = [$list['title'], abs_url(Pages::url($list))];
            }
            if ($u = Entries::url($table, $entry)) $out[] = [Entries::title($table, $entry), $base . $u];   // Brotkrumen: immer auf dieser Website
            return count($out) > 1 ? $out : [];
        }
        if (!empty($page['is_home'])) return [];
        // Landing-Domain: Brotkrumen beginnen bei der Einstiegsseite der Landingpage
        $lp = Landings::current();
        if ($lp) {
            $home = $lp->root(Lang::current());
            if ($home && (int) $home['id'] === (int) ($page['id'] ?? 0)) return [];
        }
        if ($home) $out[] = [($lp ? (($home['nav_title'] ?? '') ?: $home['title']) : $home['title']), abs_url(Pages::url($home))];
        foreach (Pages::ancestors($page) as $a) {
            if ($lp && (!$lp->contains($a) || (int) $a['id'] === (int) ($home['id'] ?? 0))) continue;
            $out[] = [$a['nav_title'] ?: $a['title'], abs_url(Pages::url($a))];
        }
        $out[] = [($page['nav_title'] ?? '') ?: $page['title'], abs_url(Pages::url($page))];
        return count($out) > 1 ? $out : [];
    }

    // ------------------------------------------------------------------ Datensätze

    /** Schema.org-Typ einer Tabelle (Einstellung oder automatisch) */
    public static function typeOf(array $table): ?string
    {
        $t = (string) ($table['settings']['schema_type'] ?? '');
        if ($t === 'none') return null;
        if ($t === '') return Calendar::enabled($table) ? 'Event' : null;
        return isset(self::TYPES[$t]) ? $t : null;
    }

    private static function entryNode(array $table, array $e, string $url): ?array
    {
        $type = self::typeOf($table);
        if (!$type) return null;
        if ($type === 'Event' && Calendar::enabled($table) && ($span = Calendar::span($table, $e))) {
            // Wiederkehrende Termine: nächster Termin ab heute
            $next = Calendar::occurrences($table, new \DateTimeImmutable('today'), new \DateTimeImmutable('+1 year'), ['ids' => [(int) $e['id']], 'limit' => 1, 'status' => $e['status'] === 'published' ? 'published' : 'all'])[0] ?? null;
            return $next ? self::event($table, $e, $next['start'], $next['end'], (bool) $next['all_day']) : self::event($table, $e, $span['start'], $span['end'], $span['all_day']);
        }
        $title = Entries::title($table, $e);
        $desc = ($table['settings']['description_field'] ?? '') !== '' ? trim(Entries::text($table, $e, $table['settings']['description_field'])) : '';
        $n = ['@type' => $type, 'name' => $title, 'url' => $url];
        if ($desc !== '') $n['description'] = mb_strimwidth(preg_replace('~\s+~', ' ', $desc), 0, 300, '…');
        if ($img = self::image($table, $e)) $n['image'] = $img;
        $org = self::orgOf($table, $e);
        $find = fn(array $types, array $names = []) => self::field($table, $e, $types, $names);

        switch ($type) {
            case 'NewsArticle': case 'Article': case 'BlogPosting': case 'CreativeWork':
                $n['headline'] = mb_strimwidth($title, 0, 110, '…');
                $pub = $e['published_at'] ?? null;
                if ($d = $find(['date', 'datetime'], ['datum', 'date', 'veroeffentlicht'])) $pub = $d;
                if ($pub) $n['datePublished'] = date('c', strtotime((string) $pub));
                if (!empty($e['updated_at'])) $n['dateModified'] = date('c', strtotime((string) $e['updated_at']));
                $author = null;
                foreach ($table['fields'] as $f) {
                    if (in_array($f['type'], ['relation'], true) && preg_match('~autor|author|verfasser~i', $f['name']) && !empty($e[$f['name']])) {
                        $target = Tables::findContent((string) ($f['target'] ?? ''));
                        $p = $target ? Entries::find($target, (int) $e[$f['name']]) : null;
                        if ($p) $author = ['@type' => 'Person', 'name' => Entries::title($target, $p)] + (($u = Entries::absUrl($target, $p)) ? ['url' => $u] : []);
                    }
                }
                $n['author'] = $author ?? $org;
                $n['publisher'] = $org;
                if ($kw = $find(['multiselect', 'relations'], ['schlagwort', 'tag', 'keyword'])) $n['keywords'] = $kw;
                break;
            case 'Person':
                if ($v = $find(['text'], ['position', 'funktion', 'rolle', 'job', 'beruf'])) $n['jobTitle'] = $v;
                if ($v = $find(['email'])) $n['email'] = $v;
                if ($v = $find(['tel'])) $n['telephone'] = substr((string) tel_href($v), 4) ?: $v;
                $n['worksFor'] = $org;
                break;
            case 'Product':
                if (($price = $find(['number'], ['preis', 'price', 'kosten'])) !== null && $price !== '') {
                    $avail = $find(['bool'], ['verfuegbar', 'lieferbar', 'available']);
                    $n['offers'] = ['@type' => 'Offer', 'price' => number_format((float) $price, 2, '.', ''), 'priceCurrency' => (string) (app()->config->get('currency', 'EUR')),
                        'availability' => 'https://schema.org/' . ($avail === false ? 'OutOfStock' : 'InStock'), 'url' => $url];
                }
                $n['brand'] = $org;
                break;
            case 'Service':
                $n['provider'] = $org;
                break;
            case 'Place': case 'Organization':
                $addr = array_filter([
                    'streetAddress' => $find(['text'], ['strasse', 'street', 'adresse']),
                    'postalCode' => $find(['text'], ['plz', 'postleitzahl', 'zip']),
                    'addressLocality' => $find(['text'], ['ort', 'stadt', 'city']),
                ]);
                if ($addr) $n['address'] = ['@type' => 'PostalAddress'] + $addr;
                if (($g = $find(['geo'])) && ($p = Maps::parse($g))) $n['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $p[0], 'longitude' => $p[1]];
                if ($v = $find(['tel'])) $n['telephone'] = substr((string) tel_href($v), 4) ?: $v;
                if ($v = $find(['email'])) $n['email'] = $v;
                if ($v = $find(['url'])) $n['sameAs'] = $v;
                break;
        }
        return $n;
    }

    /** Wert des ersten passenden Feldes (Typ + optional Namensmuster) als Text bzw. Rohwert */
    private static function field(array $table, array $e, array $types, array $names = []): mixed
    {
        foreach ($table['fields'] as $f) {
            if (!in_array($f['type'], $types, true)) continue;
            if ($names && !preg_match('~' . implode('|', array_map('preg_quote', $names)) . '~i', $f['name'] . ' ' . $f['label'])) continue;
            $v = $e[$f['name']] ?? null;
            if ($v === null || $v === '' || $v === []) continue;
            return match ($f['type']) {
                'number' => $v,
                'bool' => (bool) $v,
                'multiselect', 'relations' => implode(', ', array_filter(array_map('trim', explode(',', Entries::text($table, $e, $f['name']))))),
                default => trim(Entries::text($table, $e, $f['name'])),
            };
        }
        return null;
    }

    private static function image(array $table, array $e): ?string
    {
        $f = Tables::imageField($table);
        return $f !== '' && !empty($e[$f]) && ($m = Media::find((int) $e[$f])) ? site_url() . Media::url($m, 1200) : null;
    }

    private static function plain(string $html): string
    {
        return trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</li>'], [' ', ' ', ' '], $html)), ENT_QUOTES | ENT_HTML5)));
    }
}
