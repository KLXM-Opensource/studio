<?php
declare(strict_types=1);

namespace Core;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Glossary\Glossary;

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
    /** Nur für den Selbsttest: Recht data.edit je Tabelle bzw. Funktion „Glossar“ vorgeben (null = echte Prüfung) */
    private static ?\Closure $testCanEdit = null;
    private static ?bool $testGlossary = null;

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

    /** Gruppen, die sich mit offset/limit weiterblättern lassen („Weitere laden“) */
    public const PAGED = ['pages', 'page-anchors', 'recent-entries', 'files'];

    /**
     * Quellen für die Linkauswahl, nach Art gruppiert.
     * $o: q (Suche), page (ID der aktuellen Seite → deren Anker zuerst), mode (rich|field; field = mit Sonderzielen des Kits), limit,
     *     group (nur diese Gruppe, z. B. „pages“, „entries:news“, „recent-entries“, „files“ – zum Weiterblättern), offset (ab dem n-ten Treffer),
     *     literal (mit group=entries:…: q nur als Filter – ohne „Suchbegriff = Tabellenname → alle Einträge“; Ansicht „Daten“)
     * Einträge je Tabelle: neueste zuerst (Datumsfeld der Sortierung bzw. Anlagedatum), mit kurzem Datum.
     * Jede Gruppe: id, label, icon, items, total (alle Treffer), offset. Ohne Suche: „Neueste Einträge“ über alle Inhaltstabellen
     * statt der Gruppen je Tabelle. Einträge im Entwurf nur mit data.edit für die Tabelle (sonst nur veröffentlichte), Glossar nur veröffentlicht.
     * @return list<array{id: string, label: string, icon: string, items: list<array>, total: int, offset: int}>
     */
    public static function sources(array $o = []): array
    {
        $q = trim((string) ($o['q'] ?? ''));
        $only = (string) ($o['group'] ?? '');
        $literal = !empty($o['literal']) && str_starts_with($only, 'entries:');
        $tables = self::linkableTables();
        $offset = $only !== '' ? max(0, (int) ($o['offset'] ?? 0)) : 0;
        $limit = max(3, min(50, (int) ($o['limit'] ?? ($q === '' ? 8 : 12))));
        $want = fn(string $id) => $only === '' || $only === $id;
        $match = fn(string ...$hay) => $q === '' || array_filter($hay, fn($h) => mb_stripos($h, $q) !== false);
        $multi = Lang::multi();
        $groups = [];
        $group = function (string $id, string $label, string $icon, array $items, ?int $total = null, int $max = 0) use (&$groups, $offset, $limit): void {
            if ($total === null) {   // ganze Liste übergeben → hier blättern
                $total = count($items);
                $items = array_slice($items, $offset, $max ?: $limit);
            }
            if ($items) $groups[] = ['id' => $id, 'label' => $label, 'icon' => $icon, 'items' => array_values($items), 'total' => $total, 'offset' => $offset];
        };

        // Anker auf der aktuellen Seite
        $cur = !empty($o['page']) ? Pages::find((int) $o['page']) : null;
        if ($cur && $want('anchors')) {
            $items = [];
            foreach (Pages::anchors($cur, true) as $a => $label) {
                $label = trim(strip_tags((string) $label)) ?: (string) $a;
                if ($match($label, (string) $a)) $items[] = ['value' => '#' . $a, 'href' => '#' . $a, 'label' => $label, 'meta' => '#' . $a, 'kind' => 'anchor'];
            }
            $group('anchors', __('Anker auf dieser Seite'), 'hash', $items, null, 50);
        }

        // Seiten (mit Pfad im Seitenbaum und Sprache) – bei Suche auch Anker anderer Seiten
        if ($want('pages') || ($q !== '' && $want('page-anchors'))) {
            $pages = $anchors = [];
            foreach (Pages::all() as $p) {
                $trail = implode(' › ', array_map(fn($a) => (string) $a['title'], Pages::ancestors($p)));
                $url = Pages::plainUrl($p);
                if ($match((string) $p['title'], (string) $p['slug'], $trail)) {
                    $pages[] = self::pageItem($p, $multi) + ['meta' => ($trail !== '' ? $trail . ' › ' : '') . $url];
                }
                if ($q !== '' && (!$cur || (int) $cur['id'] !== (int) $p['id'])) {
                    foreach (Pages::anchors($p, true) as $a => $label) {
                        $label = trim(strip_tags((string) $label)) ?: (string) $a;
                        if ($match($label, (string) $a)) $anchors[] = ['value' => 'page:' . $p['id'] . '#' . $a, 'href' => $url . '#' . $a,
                            'label' => $label, 'meta' => $p['title'] . ' → #' . $a, 'kind' => 'anchor'];
                    }
                }
            }
            if ($want('pages')) $group('pages', __('Seiten'), 'file-text', $pages, null, $q === '' && $only === '' ? 15 : 0);
            if ($want('page-anchors')) $group('page-anchors', __('Anker auf anderen Seiten'), 'hash', $anchors);
        }

        // Einträge: ohne Suche die neuesten über alle Inhaltstabellen, mit Suche je Tabelle
        // (passt die Suche auf den Namen der Tabelle, erscheinen alle ihre Einträge)
        if ($q === '' && $want('recent-entries')) {
            [$items, $total] = self::recentEntries($tables, $offset, $limit, $multi);
            $group('recent-entries', __('Neueste Einträge'), 'calendar', $items, $total);
        }
        if ($q !== '' || str_starts_with($only, 'entries:')) {
            foreach ($tables as $t) {
                if (!$want('entries:' . $t['handle'])) continue;
                // Suchbegriff = Name der Tabelle → alle ihre Einträge (nicht im Filter der Ansicht „Daten“)
                $tq = $q !== '' && !$literal && $match((string) $t['name'], (string) $t['singular'], (string) $t['handle'], self::sourceLabel($t)) ? '' : $q;
                [$items, $total] = self::tableEntries($t, $tq, $offset, $limit);
                if ($total === null) continue;
                $group('entries:' . $t['handle'], self::sourceLabel($t), (string) ($t['icon'] ?? ''), $items, $total);
            }
        }

        // Dateien: ohne Suche die neuesten Dokumente, mit Suche alle Arten
        if ($want('files')) {
            $files = [];
            foreach (Media::all($q !== '' ? ['q' => $q] : []) as $m) {
                if ($q === '' && str_starts_with((string) $m['mime'], 'image/')) continue;
                $files[] = $m;
            }
            $total = count($files);
            $items = [];
            foreach (array_slice($files, $offset, $limit) as $m) {
                $pdf = $m['mime'] === 'application/pdf';
                $items[] = ['value' => 'media:' . $m['id'] . ($pdf ? ':viewer' : ''), 'href' => $pdf ? (string) Media::viewerUrl($m) : Media::url($m),
                    'file' => 'media:' . $m['id'], 'fileHref' => Media::url($m), 'pdf' => $pdf,
                    'label' => Media::displayName($m), 'meta' => self::fileMeta($m), 'kind' => 'file',
                    'thumb' => str_starts_with((string) $m['mime'], 'image/') ? Media::url($m, 480) : null];
            }
            $group('files', __('Dateien & Medien'), 'file-pdf', $items, $total);
        }

        // Sonderziele des Kits (nur Link-Felder – im Rich-Text gibt es sie nicht)
        if (($o['mode'] ?? '') === 'field' && $want('special')) {
            $labels = (array) project('link_labels', []);
            $items = [];
            foreach ((array) (app()->theme->def['link_keywords'] ?? []) as $v) {
                $label = (string) ($labels[$v] ?? $v);
                if ($match($label, (string) $v)) $items[] = ['value' => $v, 'href' => $v, 'label' => $label, 'meta' => $v, 'kind' => 'keyword'];
            }
            $group('special', __('Sonderziele des Kits'), 'star', $items, null, 50);
        }
        return $groups;
    }

    /**
     * Seitenbaum für den Modus „Struktur“ der Linkauswahl – wie der Seitenbaum der Verwaltung (Reihenfolge, Ebenen), eine Sprache.
     * Der ganze Baum auf einmal (je Seite nur Titel, Adresse, Status, Anker) – auch bei einigen hundert Seiten klein genug.
     * $lang: Sprache; ungültig/leer → Sprache der Seite $pageId bzw. die aktuelle Sprache.
     * @return array{lang: string, langs: array<string, string>, count: int, nodes: list<array>}
     */
    public static function tree(?string $lang = null, int $pageId = 0): array
    {
        $multi = Lang::multi();
        if (!Lang::valid($lang)) {
            $cur = $pageId ? Pages::find($pageId) : null;
            $lang = $cur && ($cur['type'] ?? 'page') === 'page' ? Lang::norm($cur['lang'] ?? null) : Lang::current();
            if (!Lang::valid($lang)) $lang = Lang::default();
        }
        $count = 0;
        $walk = function (array $nodes) use (&$walk, &$count, $multi): array {
            $out = [];
            foreach ($nodes as $n) {
                $p = $n['page'];
                $count++;
                $url = Pages::plainUrl($p);
                $anchors = [];
                foreach (Pages::anchors($p, true) as $a => $label) {
                    $anchors[] = ['value' => 'page:' . $p['id'] . '#' . $a, 'href' => $url . '#' . $a,
                        'label' => trim(strip_tags((string) $label)) ?: (string) $a, 'meta' => '#' . $a, 'kind' => 'anchor'];
                }
                $out[] = self::pageItem($p, $multi) + ['id' => (int) $p['id'], 'meta' => $url, 'home' => (bool) $p['is_home'],
                    'path' => \Core\PagePicker::pathOf($p),   // Pfad ohne Basis-Pfad (Feldtyp „pages“ mit store 'paths')
                    'anchors' => $anchors, 'children' => $walk($n['children'])];
            }
            return $out;
        };
        $nodes = $walk(Pages::tree(false, $lang));
        return ['lang' => (string) $lang, 'langs' => $multi ? Lang::all() : [], 'count' => $count, 'nodes' => $nodes];
    }

    /**
     * Quellen der Ansicht „Daten“: alle Inhaltstabellen mit Detailseite (Glossar nur bei eingeschalteter Funktion), je Quelle
     * id (Gruppe für format=groups: entries:{tabelle}), handle, label, singular, icon, total (Einträge, die diese Rolle verlinken darf).
     * @return list<array{id: string, handle: string, label: string, singular: string, icon: string, total: int}>
     */
    public static function dataSources(): array
    {
        $out = [];
        foreach (self::linkableTables() as $t) {
            try {
                $total = Entries::count($t, ['status' => self::entryStatus($t)]);
            } catch (\Throwable) {
                continue;
            }
            $out[] = ['id' => 'entries:' . $t['handle'], 'handle' => (string) $t['handle'], 'label' => self::sourceLabel($t),
                'singular' => (string) $t['singular'], 'icon' => (string) ($t['icon'] ?? ''), 'total' => $total];
        }
        return $out;
    }

    /** Anzeigename einer Quelle: Name der Tabelle, beim Glossar immer „Glossar“ */
    private static function sourceLabel(array $t): string
    {
        return self::isGlossary($t) ? __('Glossar') : (string) $t['name'];
    }

    /** Eintrag der Linkauswahl für eine Seite (Status wie im Seitenbaum: online | offline | draft) */
    private static function pageItem(array $p, bool $multi): array
    {
        $state = Pages::state($p);
        return ['value' => 'page:' . $p['id'], 'href' => Pages::plainUrl($p), 'label' => (string) $p['title'],
            'badge' => $multi ? strtoupper(Lang::norm($p['lang'] ?? null)) : '', 'draft' => $state !== 'online', 'state' => $state, 'kind' => 'page'];
    }

    /**
     * Inhaltstabellen, deren Einträge eine eigene Adresse haben (URL-Basis + Detailseite). Das Glossar nur, solange die Funktion
     * „Glossar“ eingeschaltet ist (seine Begriffe sind gewöhnliche Einträge: entry:glossar:ID → /glossar/{slug}).
     */
    private static function linkableTables(): array
    {
        $glossaryOn = null;
        return array_values(array_filter(Tables::content(), function ($t) use (&$glossaryOn) {
            if (($t['settings']['route'] ?? '') === '' || empty($t['settings']['detail_page_id'])) return false;
            return !self::isGlossary($t) || ($glossaryOn ??= self::$testGlossary ?? Glossary::enabled());
        }));
    }

    private static function isGlossary(array $t): bool
    {
        return ($t['handle'] ?? '') === Glossary::HANDLE && Glossary::fieldsOk($t);
    }

    /**
     * Entwürfe nur für Rollen, die Einträge dieser Tabelle bearbeiten dürfen – sonst nur, was Besucher sehen.
     * Glossar: immer nur veröffentlichte Begriffe (nur die erscheinen auf der Website und im Hinweisfenster).
     */
    private static function entryStatus(array $t): string
    {
        $edit = self::$testCanEdit ? (self::$testCanEdit)((string) $t['handle']) : can('data.edit', (string) $t['handle']);
        return !self::isGlossary($t) && $edit ? 'all' : 'published';
    }

    /**
     * Sortierung in der Linkauswahl: neueste zuerst – nach dem Datumsfeld, nach dem die Tabelle sortiert ist (z. B. Datum einer
     * Meldung), sonst nach dem Anlagedatum (auch beim Glossar). Rückgabe: Datumsfeld (absteigend, bei Gleichstand nach ID)
     */
    private static function dateField(array $t): string
    {
        $sf = (string) ($t['settings']['sort_field'] ?? '');
        $f = $sf !== '' ? Tables::field($t, $sf) : null;
        return $f && in_array($f['type'], ['date', 'datetime'], true) ? $sf : 'created_at';
    }

    /**
     * Einträge einer Tabelle für die Linkauswahl (Suche je Tabelle, Ansicht „Daten“): neueste zuerst, mit Titel, kurzem Datum
     * (day, auch vorn in meta) und Entwurfskennzeichen; beim Glossar statt der Adresse die Kurz-Erklärung.
     * @return array{0: list<array>, 1: ?int} [Einträge, Gesamtzahl] – Gesamtzahl null bei Fehlern
     */
    public static function tableEntries(array $t, string $q, int $offset, int $limit): array
    {
        $df = self::dateField($t);
        $opt = ['status' => self::entryStatus($t), 'q' => $q];
        try {
            $total = Entries::count($t, $opt);
            $rows = $total > $offset ? Entries::query($t, $opt + ['sort' => $df, 'dir' => 'desc', 'limit' => $limit, 'offset' => $offset]) : [];
        } catch (\Throwable) {
            return [[], null];
        }
        $multi = Lang::multi();
        $glossary = self::isGlossary($t);
        $items = [];
        foreach ($rows as $e) {
            $date = (string) ($e[$df] ?? '');
            $ts = $date !== '' ? strtotime($date) : false;
            $day = $ts !== false ? date('d.m.Y', $ts) : '';
            $text = $glossary ? Glossary::short((string) ($e['kurz'] ?? '')) : '';
            if (mb_strlen($text) > 90) $text = rtrim(mb_substr($text, 0, 89)) . '…';
            $text = $text !== '' ? $text : (string) Entries::href($t, $e);
            if ($it = self::entryItem($t, $e, $multi, ($day !== '' ? $day . ' · ' : '') . $text)) $items[] = $it + ['date' => $date, 'day' => $day];
        }
        return [$items, $total];
    }

    private static function entryItem(array $t, array $e, bool $multi, string $meta = ''): ?array
    {
        $href = Entries::href($t, $e);
        if ($href === null) return null;
        return ['value' => 'entry:' . $t['handle'] . ':' . $e['id'], 'href' => $href, 'label' => Entries::title($t, $e),
            'meta' => $meta !== '' ? $meta : $href, 'badge' => $multi && !empty($e['lang']) ? strtoupper((string) $e['lang']) : '',
            'draft' => ($e['status'] ?? 'published') !== 'published', 'kind' => 'entry', 'icon' => (string) ($t['icon'] ?? '')];
    }

    /**
     * Neueste verlinkbare Einträge über alle Tabellen (zuletzt geändert zuerst): je Tabelle die ersten offset+limit holen,
     * mischen, Ausschnitt nehmen. Gesamtzahl = Summe der Tabellen.
     * @return array{0: list<array>, 1: int}
     */
    private static function recentEntries(array $tables, int $offset, int $limit, bool $multi): array
    {
        $all = [];
        $total = 0;
        foreach ($tables as $t) {
            $opt = ['status' => self::entryStatus($t)];
            try {
                $n = Entries::count($t, $opt);
                if (!$n) continue;
                $total += $n;
                foreach (Entries::query($t, $opt + ['sort' => 'updated_at', 'dir' => 'desc', 'limit' => $offset + $limit]) as $e) {
                    $all[] = [(string) (($e['updated_at'] ?? '') ?: ($e['created_at'] ?? '')), $t, $e];
                }
            } catch (\Throwable) {
                continue;
            }
        }
        usort($all, fn($a, $b) => strcmp($b[0], $a[0]));
        $items = [];
        foreach (array_slice($all, $offset, $limit) as [$ts, $t, $e]) {
            $it = self::entryItem($t, $e, $multi, (string) $t['singular'] . ($ts !== '' ? ' · ' . self::ago($ts) : ''));
            if ($it) $items[] = $it + ['date' => $ts];
        }
        return [$items, $total];
    }

    /** Kurzes Datum: „heute, 14:05“, „gestern“, „vor 3 Tagen“, sonst 12.09.2026 */
    public static function ago(string $ts, ?int $now = null): string
    {
        return Format::admin()->relative($ts, $now, 'day');
    }

    /**
     * Selbsttest (links:selftest) in einer Transaktion, die am Ende zurückgerollt wird: Seitenbaum für „Struktur“ (Reihenfolge,
     * Ebenen, Status, Anker), Weiterblättern (offset/limit ohne Lücken und Doppelte), „Neueste Einträge“ (Sortierung, Rechte),
     * Sonderziele nur im Feld-Modus, kurzes Datum.
     */
    public static function selftest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = function (string $what, mixed $got, mixed $want) use (&$ok, &$fails): void {
            if ($got === $want) { $ok++; return; }
            $fails[] = $what . ': erwartet ' . var_export($want, true) . ', erhalten ' . var_export($got, true);
        };
        $byId = function (array $groups, string $id): ?array {
            foreach ($groups as $g) if ($g['id'] === $id) return $g;
            return null;
        };
        $pdo = app()->db->pdo;
        $pdo->beginTransaction();
        try {
            // Seiten: Eltern (online) › Kind (Entwurf) › Enkel; dazu Geschwister in fester Reihenfolge und eine Seite mit Anker
            $lang = Lang::default();
            $base = ['lang' => $lang, 'sort' => 9000];
            $parent = Pages::create(['slug' => 'lnk-selbsttest', 'title' => 'Linkselbsttest Eltern', 'status' => 'published'] + $base);
            $child = Pages::create(['slug' => 'kind', 'title' => 'Linkselbsttest Kind', 'parent_id' => $parent, 'sort' => 0] + $base);
            $grand = Pages::create(['slug' => 'enkel', 'title' => 'Linkselbsttest Enkel', 'parent_id' => $child, 'status' => 'published'] + $base);
            for ($i = 1; $i <= 3; $i++) {
                Pages::create(['slug' => 'geschwister-' . $i, 'title' => 'Linkselbsttest Geschwister ' . $i, 'parent_id' => $parent, 'sort' => $i, 'status' => 'published'] + $base);
            }
            $anch = Pages::create(['slug' => 'anker', 'title' => 'Linkselbsttest Anker', 'parent_id' => $parent, 'sort' => 9, 'status' => 'published'] + $base,
                [['type' => 'paragraph', 'data' => ['text' => 'x'], 'tunes' => ['section' => ['anchor' => 'lnk-sprung', 'navLabel' => 'Sprungmarke']]]]);

            $tree = self::tree($lang);
            $find = function (array $nodes, int $id) use (&$find): ?array {
                foreach ($nodes as $n) {
                    if ($n['id'] === $id) return $n;
                    if ($x = $find($n['children'], $id)) return $x;
                }
                return null;
            };
            $p = $find($tree['nodes'], $parent);
            $eq('Baum: Elternseite vorhanden', $p !== null, true);
            $eq('Baum: Sprache', $tree['lang'], $lang);
            $eq('Baum: Anzahl = alle Seiten der Sprache', $tree['count'], count(array_filter(Pages::all(), fn($r) => !Lang::multi() || Lang::norm($r['lang']) === $lang)));
            $eq('Baum: Kinder in Reihenfolge des Seitenbaums', array_column($p['children'] ?? [], 'label'),
                ['Linkselbsttest Kind', 'Linkselbsttest Geschwister 1', 'Linkselbsttest Geschwister 2', 'Linkselbsttest Geschwister 3', 'Linkselbsttest Anker']);
            $c = $find($tree['nodes'], $child);
            $eq('Baum: Kind als Entwurf markiert', [$c['draft'] ?? null, $c['state'] ?? null], [true, 'draft']);
            $eq('Baum: Enkel unter Kind', array_column($c['children'] ?? [], 'id'), [$grand]);
            $eq('Baum: Verweis page:ID', $find($tree['nodes'], $grand)['value'] ?? null, 'page:' . $grand);
            $a = $find($tree['nodes'], $anch);
            $eq('Baum: Anker als Verweis page:ID#anker', array_column($a['anchors'] ?? [], 'value'), ['page:' . $anch . '#lnk-sprung']);
            $eq('Baum: Anker mit Bezeichnung', $a['anchors'][0]['label'] ?? null, 'Sprungmarke');
            Pages::unpublish($grand);
            $eq('Baum: offline genommene Seite', $find(self::tree($lang)['nodes'], $grand)['state'] ?? null, 'offline');
            $eq('Baum: ungültige Sprache → gültige', Lang::valid(self::tree('xx-ungueltig')['lang']), true);

            // Weiterblättern: 7 Treffer in Schritten zu 3 – lückenlos, ohne Doppelte, Gesamtzahl
            $seen = [];
            $total = null;
            for ($off = 0; $off < 12; $off += 3) {
                $g = $byId(self::sources(['q' => 'Linkselbsttest', 'group' => 'pages', 'offset' => $off, 'limit' => 3]), 'pages');
                if (!$g) break;
                $total = $g['total'];
                $eq("Seiten ab $off: offset zurückgegeben", $g['offset'], $off);
                foreach ($g['items'] as $it) $seen[] = $it['value'];
            }
            $eq('Seiten: Gesamtzahl', $total, 7);
            $eq('Seiten: alle Treffer genau einmal', [count($seen), count(array_unique($seen))], [7, 7]);
            $eq('Nur angefragte Gruppe', array_column(self::sources(['q' => 'Linkselbsttest', 'group' => 'pages', 'limit' => 3]), 'id'), ['pages']);
            $all = self::sources([]);
            $pg = $byId($all, 'pages');
            $eq('Ohne Suche: höchstens 15 Seiten, Gesamtzahl aller Seiten', [count($pg['items'] ?? []) <= 15, $pg['total'] ?? 0], [true, count(Pages::all())]);
            $eq('Ohne Suche: keine Gruppen je Tabelle', array_values(array_filter(array_column($all, 'id'), fn($id) => str_starts_with($id, 'entries:'))), []);
            $eq('Mit Suche: keine „Neuesten Einträge“', $byId(self::sources(['q' => 'Linkselbsttest']), 'recent-entries'), null);
            $eq('Sonderziele nur im Feld-Modus', $byId(self::sources(['mode' => 'rich']), 'special'), null);

            // Neueste Einträge: absteigend nach Datum, Blättern ohne Doppelte, ohne Anmeldung nur Veröffentlichtes
            $r1 = $byId(self::sources(['group' => 'recent-entries', 'limit' => 4]), 'recent-entries');
            if ($r1) {
                $dates = array_column($r1['items'], 'date');
                $sorted = $dates;
                rsort($sorted);
                $eq('Neueste Einträge: absteigend sortiert', $dates, $sorted);
                $eq('Neueste Einträge: nur Einträge mit Adresse', count(array_filter($r1['items'], fn($it) => ($it['href'] ?? '') === '')), 0);
                $eq('Neueste Einträge: Gesamtzahl ≥ Anzahl', $r1['total'] >= count($r1['items']), true);
                if (!(app()->auth->user() ?? null)) {
                    $eq('Neueste Einträge ohne Recht data.edit: keine Entwürfe', count(array_filter($r1['items'], fn($it) => $it['draft'])), 0);
                }
                if ($r1['total'] > 4) {
                    $r2 = $byId(self::sources(['group' => 'recent-entries', 'offset' => 4, 'limit' => 4]), 'recent-entries');
                    $eq('Neueste Einträge: zweite Seite ohne Doppelte', array_intersect(array_column($r1['items'], 'value'), array_column($r2['items'] ?? [], 'value')), []);
                    $eq('Neueste Einträge: zweite Seite nicht neuer', ($r2['items'][0]['date'] ?? '') <= (end($dates) ?: ''), true);
                }
            } else {
                $ok++;   // keine Tabelle mit Detailseite bzw. keine Einträge – nichts zu prüfen
            }

            // Kurzes Datum
            $now = strtotime('2026-09-30 15:00:00');
            $eq('Datum heute', self::ago('2026-09-30 09:05:00', $now), __('heute, {time}', ['time' => '09:05']));
            $eq('Datum gestern', self::ago('2026-09-29 23:59:00', $now), __('gestern'));
            $eq('Datum vor Tagen', self::ago('2026-09-26 10:00:00', $now), __('vor {n} Tagen', ['n' => 4]));
            $eq('Datum älter', self::ago('2026-08-01 10:00:00', $now), '01.08.2026');
            $eq('Datum ungültig', self::ago('kein Datum', $now), '');
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
            PageCache::clear();
        }
        self::selftestTables($eq, $byId, $fails);
        return ['ok' => $ok, 'fails' => $fails];
    }

    /**
     * Selbsttest Ansicht „Daten“ und Glossar: legt eine Testtabelle an (Meldungen mit Datum, dazu das Glossar, falls es noch keins
     * gibt), Einträge in einer Transaktion (zurückgerollt), die Tabellen werden am Ende wieder gelöscht.
     * Prüft: Quellenliste (Anzahl, Glossar nur bei eingeschalteter Funktion), neueste zuerst, Blättern ohne Lücken, kurzes Datum,
     * Entwurf, Filter (literal), Rechte (Entwürfe nur mit data.edit), Glossar nur veröffentlicht, Suche nach Begriff und Tabellenname.
     */
    private static function selftestTables(\Closure $eq, \Closure $byId, array &$fails): void
    {
        $handle = 'lnk_test_' . bin2hex(random_bytes(3));
        $made = [];
        $pdo = app()->db->pdo;
        try {
            $tpl = fn(string $h) => Pages::create(['slug' => '_vorlage-' . $h, 'title' => 'Vorlage ' . $h, 'type' => 'template', 'template_for' => $h, 'status' => 'published']);
            [$def, $err] = Tables::validate(['name' => 'Linktest Meldungen', 'singular' => 'Linktest Meldung', 'handle' => $handle, 'icon' => 'newspaper',
                'fields' => [['label' => 'Titel', 'name' => 'titel', 'type' => 'text', 'required' => 1], ['label' => 'Datum', 'name' => 'datum', 'type' => 'date']],
                'settings' => ['route' => str_replace('_', '-', $handle), 'title_field' => 'titel', 'sort_field' => 'datum', 'sort_dir' => 'desc', 'workflow' => 1]]);
            $eq('Testtabelle gültig', $err, []);
            if ($err) return;
            $def['settings']['detail_page_id'] = $tpl($handle);
            Tables::create($def);
            $made[] = $handle;
            $gl = null;
            if (!Tables::find(Glossary::HANDLE)) {
                [$gdef, $gerr] = Tables::validate(Glossary::definition());
                $eq('Glossar-Tabelle gültig', $gerr, []);
                if (!$gerr) {
                    $gdef['settings']['detail_page_id'] = $tpl(Glossary::HANDLE);
                    Tables::create($gdef);
                    $made[] = Glossary::HANDLE;
                    Glossary::flush();
                    $gl = Tables::find(Glossary::HANDLE);
                }
            }
            Tables::flush();
            $t = Tables::find($handle);

            $pdo->beginTransaction();
            self::$testCanEdit = fn(string $h) => true;
            self::$testGlossary = true;
            // 23 Meldungen mit Datum (eine im Entwurf)
            for ($i = 1; $i <= 23; $i++) {
                Entries::save($t, null, ['titel' => sprintf('Linktest Meldung %02d', $i), 'datum' => date('Y-m-d', strtotime('2026-01-01 +' . $i . ' days')),
                    'status' => $i === 23 ? 'draft' : 'published']);
            }
            if ($gl) {
                foreach (['Zertifikat' => 'published', 'DNS' => 'published', 'Ärztekammer' => 'published', 'Barrierefreiheit' => 'published', 'Entwurfsbegriff' => 'draft'] as $term => $st) {
                    Entries::save($gl, null, ['begriff' => $term, 'kurz' => 'Kurz erklärt: ' . $term, 'status' => $st]);
                }
            }
            $src = fn(string $h) => array_values(array_filter(self::dataSources(), fn($x) => $x['handle'] === $h))[0] ?? null;

            // Quellenliste
            $s1 = $src($handle);
            $eq('Quellen: Tabelle mit Detailseite', [$s1['id'] ?? null, $s1['label'] ?? null, $s1['icon'] ?? null], ['entries:' . $handle, 'Linktest Meldungen', 'newspaper']);
            $eq('Quellen: Anzahl mit data.edit inkl. Entwurf', $s1['total'] ?? null, 23);

            // Einträge: neueste zuerst nach Datum, Seiten zu 10 ohne Lücken und Doppelte, Entwurf gekennzeichnet
            $seen = [];
            $dates = [];
            for ($off = 0; $off < 30; $off += 10) {
                $g = $byId(self::sources(['group' => 'entries:' . $handle, 'offset' => $off, 'limit' => 10, 'literal' => 1]), 'entries:' . $handle);
                if (!$g) break;
                $eq("Einträge ab $off: Gesamtzahl und offset", [$g['total'], $g['offset']], [23, $off]);
                foreach ($g['items'] as $it) { $seen[] = $it['value']; $dates[] = $it['date'] ?? ''; }
            }
            $sorted = $dates;
            rsort($sorted);
            $eq('Einträge: alle genau einmal', [count($seen), count(array_unique($seen))], [23, 23]);
            $eq('Einträge: neueste zuerst (Datumsfeld)', $dates, $sorted);
            $first = $byId(self::sources(['group' => 'entries:' . $handle, 'limit' => 3]), 'entries:' . $handle)['items'][0] ?? [];
            $eq('Eintrag: neuester ist der Entwurf', [$first['label'] ?? null, $first['draft'] ?? null], ['Linktest Meldung 23', true]);
            $eq('Eintrag: kurzes Datum', [$first['day'] ?? null, str_starts_with((string) ($first['meta'] ?? ''), '24.01.2026 · ')], ['24.01.2026', true]);
            $eq('Eintrag: stabiler Verweis', (bool) preg_match('~^entry:' . $handle . ':\d+$~', (string) ($first['value'] ?? '')), true);

            // Filter der Ansicht „Daten“ (literal): sucht in den Einträgen, auch wenn er auf den Tabellennamen passt
            $g = $byId(self::sources(['group' => 'entries:' . $handle, 'q' => 'Meldung 07', 'literal' => 1]), 'entries:' . $handle);
            $eq('Filter: Treffer', array_column($g['items'] ?? [], 'label'), ['Linktest Meldung 07']);
            $g = $byId(self::sources(['group' => 'entries:' . $handle, 'q' => 'Linktest Meldungen', 'literal' => 1]), 'entries:' . $handle);
            $eq('Filter = Tabellenname: kein „alle Einträge“', $g, null);
            $g = $byId(self::sources(['q' => 'Linktest Meldungen']), 'entries:' . $handle);
            $eq('Suche = Tabellenname: alle Einträge', $g['total'] ?? null, 23);
            $g = $byId(self::sources(['q' => 'Linktest Meldungen', 'group' => 'entries:' . $handle, 'offset' => 12, 'limit' => 30]), 'entries:' . $handle);
            $eq('Suche = Tabellenname: Weitere laden', count($g['items'] ?? []), 11);

            // Rechte: ohne data.edit nur Veröffentlichtes (Quellen und Liste)
            self::$testCanEdit = fn(string $h) => false;
            $eq('Ohne data.edit: Anzahl ohne Entwurf', $src($handle)['total'] ?? null, 22);
            $g = $byId(self::sources(['group' => 'entries:' . $handle, 'limit' => 50]), 'entries:' . $handle);
            $eq('Ohne data.edit: keine Entwürfe', [count($g['items'] ?? []), count(array_filter($g['items'] ?? [], fn($it) => $it['draft']))], [22, 0]);
            self::$testCanEdit = fn(string $h) => true;

            if ($gl) {
                $gs = $src(Glossary::HANDLE);
                $eq('Glossar: Quelle „Glossar“', [$gs['id'] ?? null, $gs['label'] ?? null], ['entries:' . Glossary::HANDLE, __('Glossar')]);
                $eq('Glossar: nur veröffentlichte Begriffe (auch mit data.edit)', $gs['total'] ?? null, 4);
                $g = $byId(self::sources(['group' => 'entries:' . Glossary::HANDLE, 'limit' => 50, 'literal' => 1]), 'entries:' . Glossary::HANDLE);
                $labels = array_column($g['items'] ?? [], 'label');
                $eq('Glossar: neueste zuerst, ohne Entwurf', $labels, ['Barrierefreiheit', 'Ärztekammer', 'DNS', 'Zertifikat']);
                $g2 = $byId(self::sources(['group' => 'entries:' . Glossary::HANDLE, 'offset' => 2, 'limit' => 3]), 'entries:' . Glossary::HANDLE);
                $eq('Glossar: Weiterblättern', array_column($g2['items'] ?? [], 'label'), ['DNS', 'Zertifikat']);
                $eq('Glossar: Verweis entry:glossar:ID → /glossar/{slug}', [(bool) preg_match('~^entry:glossar:\d+$~', (string) ($g['items'][0]['value'] ?? '')),
                    (bool) preg_match('~/glossar/[a-z0-9-]+$~', (string) ($g['items'][0]['href'] ?? ''))], [true, true]);
                $eq('Glossar: Verweis löst auf', self::href((string) ($g['items'][0]['value'] ?? '')), $g['items'][0]['href'] ?? false);
                $eq('Glossar: Kurz-Erklärung in der Beschreibung', str_ends_with((string) ($g['items'][0]['meta'] ?? ''), 'Kurz erklärt: Barrierefreiheit'), true);
                $eq('Glossar: auch unter „Neueste Einträge“ nur Veröffentlichtes', count(array_filter(self::recentEntries([$gl], 0, 50, false)[0], fn($it) => $it['draft'])), 0);
                $g = $byId(self::sources(['q' => 'Zertifikat']), 'entries:' . Glossary::HANDLE);
                $eq('Glossar: Suche nach Begriff', array_column($g['items'] ?? [], 'label'), ['Zertifikat']);
                $g = $byId(self::sources(['q' => 'glossar']), 'entries:' . Glossary::HANDLE);
                $eq('Glossar: Suchbegriff „glossar“ zeigt alle Begriffe', $g['total'] ?? null, 4);
                $eq('Glossar: Entwurf nicht in der Suche', $byId(self::sources(['q' => 'Entwurfsbegriff']), 'entries:' . Glossary::HANDLE), null);
                self::$testGlossary = false;
                $eq('Glossar: Funktion aus → keine Quelle', $src(Glossary::HANDLE), null);
                $eq('Glossar: Funktion aus → nicht in der Suche', $byId(self::sources(['q' => 'glossar']), 'entries:' . Glossary::HANDLE), null);
            }
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme (Tabellen): ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            self::$testCanEdit = null;
            self::$testGlossary = null;
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($made as $h) {
                Tables::flush();
                if ($x = Tables::find($h)) Tables::delete($x);
            }
            Tables::flush();
            Glossary::flush();
            PageCache::clear();
        }
    }

    private static function fileMeta(array $m): string
    {
        return Media::typeLabel((string) $m['mime']) . ' · ' . Media::humanSize((int) $m['size']);
    }
}
