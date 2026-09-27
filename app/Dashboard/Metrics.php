<?php
declare(strict_types=1);

namespace Core\Dashboard;

use Core\Data\Calendar;
use Core\Data\Entries;
use Core\Data\Inbox;
use Core\Data\Tables;
use Core\Features;
use Core\FormCrypto;
use Core\Lang;
use Core\Media;
use Core\Pages;

/**
 * Zahlen der Übersicht (/admin) – nur echte Werte aus der Datenbank der Website, keine Besucherstatistik (kein Tracking).
 * Quellen: pages, revisions (je Seite die letzten 20 Stände – ältere fallen heraus), change_log (API, MCP, KI), media,
 * Datentabellen (created_at/updated_at/status), Eingangs-Tabellen (nur Anzahl, Inhalte bleiben verschlüsselt),
 * Such-Zähler „ohne Treffer“ (anonym, Core\Search\Stats). Alle Werte berücksichtigen die Rechte des Benutzers.
 */
final class Metrics
{
    /** Datum/Uhrzeit vor $days Tagen im Speicherformat (Y-m-d H:i:s, vergleichbar als Zeichenkette) */
    public static function ago(int $days): string
    {
        return date('Y-m-d H:i:s', time() - $days * 86400);
    }

    private static function val(string $sql, array $p = []): int
    {
        try {
            return (int) app()->db->fetchValue($sql, $p);
        } catch (\Throwable $e) {
            error_log('[dashboard] ' . $e->getMessage());
            return 0;
        }
    }

    /** Inhaltstabellen, die der Benutzer bearbeiten darf (ohne Eingangs-Tabellen) */
    public static function tables(): array
    {
        if (!Features::on('data', false)) return [];
        return array_values(array_filter(Tables::content(), fn($t) => can('data.edit', $t['handle'])));
    }

    /** Eigene (nicht geteilte) Tabellen – nur dort sind created_at/updated_at der Website eindeutig */
    private static function ownTables(): array
    {
        return array_values(array_filter(self::tables(), fn($t) => !Tables::isShared($t)));
    }

    /** Zeitraum-Vergleich: Anzahl in den letzten 30 Tagen und in den 30 Tagen davor */
    private static function window(string $table, string $col, string $extra = '', array $p = []): array
    {
        $w = $extra !== '' ? " AND ($extra)" : '';
        return ['now' => self::val("SELECT COUNT(*) FROM $table WHERE $col >= ?$w", [self::ago(30), ...$p]),
            'prev' => self::val("SELECT COUNT(*) FROM $table WHERE $col >= ? AND $col < ?$w", [self::ago(60), self::ago(30), ...$p])];
    }

    // ================================================================= Kennzahlen

    /** Kacheln „Kennzahlen“: key, label, value, text, level (0–100 | null), trend, href, icon, hot */
    public static function figures(array $user): array
    {
        $tiles = [];
        if (can('pages.edit')) {
            $total = self::val("SELECT COUNT(*) FROM pages WHERE type = 'page'");
            $online = self::val("SELECT COUNT(*) FROM pages WHERE type = 'page' AND status = 'published'");
            $tiles[] = ['key' => 'pages', 'icon' => 'files', 'label' => __('Seiten online'), 'value' => (string) $online,
                'text' => __('von {n} Seiten', ['n' => $total]), 'level' => $total ? $online / $total * 100 : null,
                'trend' => self::window('revisions', 'created_at') + ['label' => __('Speicherungen')], 'href' => '/admin/pages'];
        }
        if ($tables = self::tables()) {
            $all = $pub = 0;
            $trend = ['now' => 0, 'prev' => 0];
            foreach ($tables as $t) {
                try {
                    $all += Entries::count($t, ['status' => 'all', 'lang' => 'all']);
                    $pub += Entries::count($t, ['status' => 'published', 'lang' => 'all']);
                    if (!Tables::isShared($t)) {
                        $w = self::window($t['table'], 'created_at');
                        $trend['now'] += $w['now'];
                        $trend['prev'] += $w['prev'];
                    }
                } catch (\Throwable $e) {
                    error_log('[dashboard] entries ' . $t['handle'] . ': ' . $e->getMessage());
                }
            }
            $tiles[] = ['key' => 'entries', 'icon' => 'database', 'label' => __('Einträge'), 'value' => (string) $all,
                'text' => __('{n} online in {t} Tabellen', ['n' => $pub, 't' => count($tables)]), 'level' => $all ? $pub / $all * 100 : null,
                'trend' => $trend + ['label' => __('neue Einträge')], 'href' => '/admin/data'];
        }
        if (can('media.upload') && Features::on('media')) {
            $c = self::mediaCounts();
            $img = (int) ($c['image'] ?? 0);
            $alt = $img ? ($img - (int) ($c['noalt'] ?? 0)) / $img * 100 : null;
            $tiles[] = ['key' => 'media', 'icon' => 'images', 'label' => __('Medien'), 'value' => (string) (int) ($c['alle'] ?? 0),
                'text' => $alt === null ? __('noch keine Bilder') : __('{p} % der Bilder mit Alt-Text', ['p' => (int) floor($alt)]),
                'level' => $alt, 'trend' => self::window('media', 'created_at', 'pool_ref IS NULL') + ['label' => __('neue Dateien')], 'href' => '/admin/media'];
        }
        if (can('requests.read') && Features::on('requests') && ($inboxes = Inbox::readable())) {
            $new = $total = 0;
            $trend = ['now' => 0, 'prev' => 0];
            foreach ($inboxes as $ib) {
                $new += Inbox::count($ib, 'neu');
                $total += Inbox::count($ib);
                $w = self::window($ib['table'], 'created_at');
                $trend['now'] += $w['now'];
                $trend['prev'] += $w['prev'];
            }
            $tiles[] = ['key' => 'requests', 'icon' => 'tray', 'label' => __('Neue Anfragen'), 'value' => (string) $new,
                'text' => __('{n} Anfragen insgesamt', ['n' => $total]), 'level' => null, 'hot' => $new > 0,
                'trend' => $trend + ['label' => __('eingegangen')], 'href' => '/admin/requests'];
        }
        if (\Core\Review\Queue::canReview()) {
            $tiles[] = ['key' => 'review', 'icon' => 'clipboard-text', 'label' => __('Eingereicht'), 'value' => (string) \Core\Review\Queue::pendingCount(),
                'text' => __('Änderungen warten auf Freigabe'), 'level' => null, 'hot' => \Core\Review\Queue::pendingCount() > 0,
                'trend' => self::window('change_log', 'created_at', "status = 'pending' OR reviewer_id IS NOT NULL") + ['label' => __('Einreichungen')],
                'href' => '/admin/ai/eingereicht'];
        }
        if (can('pages.edit')) {
            $pub = self::val("SELECT COUNT(*) FROM pages WHERE type = 'page' AND status = 'published'");
            $fresh = self::val("SELECT COUNT(*) FROM pages WHERE type = 'page' AND status = 'published' AND updated_at >= ?", [self::ago(90)]);
            $tiles[] = ['key' => 'fresh', 'icon' => 'clock-clockwise', 'label' => __('Aktualität'),
                'value' => $pub ? (int) round($fresh / $pub * 100) . ' %' : '–',
                'text' => __('{n} von {m} Seiten in 90 Tagen aktualisiert', ['n' => $fresh, 'm' => $pub]), 'level' => $pub ? $fresh / $pub * 100 : null,
                'trend' => null, 'href' => '/admin/pages'];
        }
        return $tiles;
    }

    public static function mediaCounts(): array
    {
        static $c = null;
        try {
            return $c ??= Media::counts();
        } catch (\Throwable) {
            return [];
        }
    }

    // ================================================================= Was ist zu tun?

    /**
     * Aufgaben mit Anzahl, Erklärung und Hauptaktion, nach Dringlichkeit sortiert (tone: err | warn | info).
     * @return list<array{key: string, tone: string, icon: string, count: int, title: string, text: string, href: string, action: string}>
     */
    public static function todos(array $user, array $checks): array
    {
        $out = [];
        $add = function (string $key, int $prio, string $tone, string $icon, int $count, string $title, string $text, string $href, string $action) use (&$out) {
            if ($count > 0) $out[] = compact('key', 'prio', 'tone', 'icon', 'count', 'title', 'text', 'href', 'action');
        };
        $s = app()->settings;
        $admin = can('system.manage');
        if ($admin && $s->get('sys.maintenance')) {
            $add('maintenance', 0, 'err', 'wrench', 1, __('Wartungsmodus ist eingeschaltet'), __('Besucher sehen nur den Wartungshinweis.'), '/admin/system#website', __('Wartungsmodus prüfen'));
        }
        if (can('requests.read') && Features::on('requests') && Inbox::readable()) {
            $n = Inbox::newCount();
            $add('requests', 1, 'warn', 'tray', $n, __('Neue Anfragen lesen'), __('Über Formulare eingegangen und noch nicht geöffnet.'), '/admin/requests', __('Anfragen öffnen'));
        }
        if (\Core\Review\Queue::canReview()) {
            $add('review', 2, 'warn', 'clipboard-text', \Core\Review\Queue::pendingCount(), __('Eingereichte Änderungen prüfen'),
                __('Von API, MCP oder KI vorgeschlagen – erst nach Ihrer Freigabe online.'), '/admin/ai/eingereicht', __('Prüfen'));
        }
        if (\Core\Support\Support::canReport()) {
            $add('support', 3, 'info', 'lifebuoy', \Core\Support\Support::navCount(), __('Antworten im Support'),
                \Core\Support\Support::isStaff() ? __('Neue oder unbeantwortete Meldungen.') : __('Das Support-Team hat Ihnen geantwortet.'), '/admin/support', __('Lesen'));
        }
        if (\Core\Chat\Chat::canUse()) {
            [$chatN] = \Core\Chat\Chat::navCount();
            $add('chat', 4, 'info', 'chats', (int) $chatN, __('Ungelesene Chat-Nachrichten'), __('Nachrichten aus dem Team.'), '/admin/chat', __('Chat öffnen'));
        }
        // Einrichtung (Kit-Prüfungen wie „Impressum zugeordnet“, E-Mail-Versand, Schlüssel, Platzhalter)
        if (can('settings.edit') || $admin) {
            $open = array_values(array_filter($checks, fn($c) => !$c['ok'] && !isset($c['placeholder']) && ($admin || !($c['admin'] ?? false))));
            if ($open) {
                $add('setup', 1, 'warn', 'list-checks', count($open), __('Einrichtung abschließen'),
                    implode(' · ', array_map(fn($c) => $c['label'], array_slice($open, 0, 3))) . (count($open) > 3 ? ' …' : ''), $open[0]['link'], __('Weiter einrichten'));
            }
        }
        // Platzhalter (Inhalt, daher auch für die Redaktion): Anzahl aller Fundstellen, erste Seite direkt öffnen, gewollte Klammern bestätigen
        if (can('pages.edit') || can('settings.edit') || $admin) {
            $all = self::placeholders(app()->db, self::placeholdersOk());
            if ($all) {
                $first = $all[0];
                $pages = array_values(array_unique(array_map(fn($h) => $h['title'], $all)));
                $add('placeholder', 2, 'warn', 'brackets-curly', count($all),
                    count($all) === 1 ? __('Platzhalter {text} ersetzen', ['text' => $first['text']]) : __('{n} Platzhalter in Seiten ersetzen', ['n' => count($all)]),
                    __('Zuerst: {text} auf „{page}“.', ['text' => mb_strimwidth($first['text'], 0, 90, '…]'), 'page' => $first['title']])
                        . (count($pages) > 1 ? ' ' . __('Weitere Seiten: {pages}', ['pages' => implode(', ', array_slice(array_diff($pages, [$first['title']]), 0, 4)) . (count($pages) > 5 ? ' …' : '')]) : '')
                        . ' ' . __('Ist eine Klammer Absicht (z. B. in einer Anleitung), bestätigen Sie sie.'),
                    '/admin/pages/' . $first['id'], __('Seite öffnen'));
                $out[count($out) - 1]['dismiss'] = ['url' => '/admin/api/dashboard/placeholder-ok', 'value' => $first['text'], 'label' => __('Ist gewollt')];
            }
        }
        if (can('pages.edit')) {
            $old = 0;
            $first = null;
            foreach (Pages::all() as $p) {
                if (($p['status'] === 'draft' || Pages::hasUnpublished($p)) && (string) $p['updated_at'] < self::ago(14)) {
                    $old++;
                    $first ??= $p;
                }
            }
            $add('drafts', 5, 'info', 'pencil-simple', $old, __('Liegengebliebene Entwürfe'), __('Seiten mit Änderungen, die seit über 14 Tagen nicht veröffentlicht sind.'),
                $old === 1 && $first ? Pages::plainUrl($first) . '?edit=1' : '/admin/pages', $old === 1 ? __('Weiter bearbeiten') : __('Seiten ansehen'));
            $noDesc = self::val("SELECT COUNT(*) FROM pages WHERE type = 'page' AND status = 'published' AND noindex = 0 AND (meta_description IS NULL OR meta_description = '')");
            $seo = Features::on('ai', false) && can('ai.use') && can('pages.manage');
            $add('seo', 7, 'info', 'magnifying-glass', $noDesc, __('Seiten ohne SEO-Beschreibung'), __('Suchmaschinen zeigen sonst einen zufälligen Textausschnitt.'),
                $seo ? '/admin/ai/seo' : '/admin/pages', $seo ? __('SEO-Übersicht') : __('Seiten ansehen'));
        }
        $entryDrafts = 0;
        $draftTable = null;
        foreach (self::ownTables() as $t) {
            $n = self::val("SELECT COUNT(*) FROM {$t['table']} WHERE status = 'draft' AND updated_at < ?", [self::ago(14)]);
            if ($n > 0 && ($draftTable === null || $n > $draftTable[1])) $draftTable = [$t, $n];
            $entryDrafts += $n;
        }
        if ($draftTable) {
            $add('entry-drafts', 6, 'info', 'pencil-simple', $entryDrafts, __('Einträge im Entwurf'), __('Seit über 14 Tagen nicht online gestellt – veröffentlichen oder löschen.'),
                '/admin/data/' . $draftTable[0]['handle'] . '?status=draft', __('{name} ansehen', ['name' => $draftTable[0]['name']]));
        }
        if (can('media.upload') && Features::on('media')) {
            $c = self::mediaCounts();
            $add('alt', 6, 'warn', 'image', (int) ($c['noalt'] ?? 0), __('Bilder ohne Alt-Text'),
                __('Screenreader und Suchmaschinen brauchen eine Bildbeschreibung (oder „dekorativ“).'), '/admin/media#check=noalt', __('Bilder ergänzen'));
            foreach (Lang::all() as $l => $info) {
                if ($l === Lang::default()) continue;
                $add('alt-' . $l, 8, 'info', 'translate', (int) ($c['missing_' . $l] ?? 0), __('Alt-Texte ohne Übersetzung ({lang})', ['lang' => strtoupper((string) $l)]),
                    __('Bilder, deren Beschreibung in dieser Sprache fehlt.'), '/admin/media#check=missing:' . $l, __('Übersetzen'));
            }
            if (Features::on('media.captions')) {
                $add('captions', 7, 'warn', 'article', (int) ($c['nocaptions'] ?? 0), __('Videos ohne Untertitel'),
                    __('Ohne Untertitel sind Videos für Gehörlose nicht zugänglich.'), '/admin/media#check=nocaptions', __('Untertitel anlegen'));
            }
        }
        if (\Core\Sources\Sources::canManage()) {
            $bad = array_filter(\Core\Sources\Sources::all(), fn($s) => $s['active'] && $s['last_error']);
            $add('sources', 3, 'err', 'rss', count($bad), __('Externe Quellen mit Fehler'),
                __('Der letzte Abruf ist fehlgeschlagen: {names}', ['names' => implode(', ', array_map(fn($s) => $s['name'], array_slice($bad, 0, 3)))]), '/admin/quellen', __('Quellen prüfen'));
        }
        $rank = ['err' => 0, 'warn' => 1, 'info' => 2];
        usort($out, fn($a, $b) => [$rank[$a['tone']], $a['prio']] <=> [$rank[$b['tone']], $b['prio']]);
        return $out;
    }

    /** Einrichtungs-Prüfungen (bisherige Checkliste der Übersicht); 'admin' = nur für die Administration relevant */
    public static function setupChecks(): array
    {
        $s = app()->settings;
        $checks = [
            ['ok' => FormCrypto::ready(), 'label' => __('{key} für verschlüsselte Formulare erzeugt', ['key' => term('key')]), 'link' => '/admin/system#keys', 'admin' => true],
            ['ok' => (string) $s->get('sys.mail_to') !== '' && (string) $s->get('sys.mail_from') !== '', 'label' => __('E-Mail-Versand eingerichtet'), 'link' => '/admin/system#mail', 'admin' => true],
        ];
        // Kit-Prüfungen (theme.php → project.setup_checks): Feld ausgefüllt, optional nur bei aktivem Schalter – z. B. „Impressum zugeordnet“
        foreach ((array) project('setup_checks', [['setting' => (string) project('name_setting', 'site_name'), 'label' => term('site_name') . ' eingetragen', 'link' => '/admin/settings']]) as $c) {
            $checks[] = ['ok' => filled($s->get($c['setting'])) || (!empty($c['when']) && !$s->get($c['when'])), 'label' => __((string) $c['label']), 'link' => $c['link'] ?? '/admin/settings'];
        }
        $checks[] = ['ok' => (string) $s->get('sys.site_url') !== '', 'label' => __('Kanonische Domain festgelegt'), 'link' => '/admin/system#website', 'admin' => true];
        // Platzhalter: nennt Fundstelle und Seite, führt direkt dorthin; gewollte Klammern („[Musik]“) lassen sich bestätigen
        $ph = self::placeholder();
        $checks[] = $ph
            ? ['ok' => false, 'label' => __('Platzhalter {text} auf der Seite „{page}“ ersetzen', ['text' => $ph['text'], 'page' => $ph['title']]), 'link' => '/admin/pages/' . $ph['id'], 'placeholder' => $ph['text'], 'page' => $ph['title']]
            : ['ok' => true, 'label' => __('Alle [Platzhalter] in den Seiten ersetzt'), 'link' => '/admin/pages'];
        return $checks;
    }

    /** Als gewollt bestätigte Klammer-Texte (Einstellung sys.placeholders_ok, z. B. „[Musik]“ in einer Anleitung) */
    public static function placeholdersOk(): array
    {
        return array_values(array_filter((array) app()->settings->get('sys.placeholders_ok', []), 'is_string'));
    }

    /** [Platzhalter]: Klammer mit Buchstabe am Anfang – auch klein („[bitte ergänzen: …]“), aber keine Markdown-Links „[Text](url)“ */
    public const PLACEHOLDER_RX = '~\[\p{L}[^\]\[]{2,}\](?!\()~u';

    /** Erster [Platzhalter] in veröffentlichten Seiten (ohne bestätigte): ['id', 'title', 'text'] oder null */
    public static function placeholder(): ?array
    {
        return self::placeholders(app()->db, self::placeholdersOk(), 1)[0] ?? null;
    }

    /**
     * [Platzhalter] in den Seiten einer Website (auch für die Netzwerk-Übersicht mit fremder Datenbank)
     * @return list<array{id:int,title:string,text:string}>
     */
    public static function placeholders(\Core\Database $db, array $ok, int $max = 50): array
    {
        $out = [];
        foreach ($db->fetchAll("SELECT id, title, content_published FROM pages WHERE type = 'page' ORDER BY is_home DESC, sort, title") as $p) {
            if (!preg_match_all(self::PLACEHOLDER_RX, (string) $p['content_published'], $m)) continue;
            foreach (array_unique($m[0]) as $hit) {
                if (in_array($hit, $ok, true)) continue;
                $out[] = ['id' => (int) $p['id'], 'title' => (string) $p['title'], 'text' => $hit];
                if (count($out) >= $max) return $out;
            }
        }
        return $out;
    }

    // ================================================================= Zuletzt bearbeitet

    /** Eigene Seiten (Versionen mit user_id) und Team (Seiten + Einträge nach updated_at) */
    public static function recent(array $user, int $limit = 5): array
    {
        $mine = $team = [];
        if (can('pages.edit')) {
            try {
                foreach (app()->db->fetchAll('SELECT r.page_id, MAX(r.created_at) AS at FROM revisions r JOIN pages p ON p.id = r.page_id
                    WHERE r.user_id = ? AND p.type = ? GROUP BY r.page_id ORDER BY at DESC LIMIT ' . $limit, [(int) $user['id'], 'page']) as $r) {
                    if (!($p = Pages::find((int) $r['page_id']))) continue;
                    $mine[] = ['title' => (string) $p['title'], 'href' => Pages::plainUrl($p) . '?edit=1', 'at' => (string) $r['at'], 'kind' => __('Seite'),
                        'icon' => 'file-text', 'draft' => $p['status'] === 'draft' || Pages::hasUnpublished($p)];
                }
                foreach (app()->db->fetchAll("SELECT p.*, (SELECT u.name FROM revisions r LEFT JOIN users u ON u.id = r.user_id WHERE r.page_id = p.id ORDER BY r.id DESC LIMIT 1) AS who,
                    (SELECT r.user_id FROM revisions r WHERE r.page_id = p.id ORDER BY r.id DESC LIMIT 1) AS who_id
                    FROM pages p WHERE p.type = 'page' AND p.updated_at IS NOT NULL ORDER BY p.updated_at DESC LIMIT " . $limit) as $p) {
                    $team[] = ['title' => (string) $p['title'], 'href' => Pages::plainUrl($p) . '?edit=1', 'at' => (string) $p['updated_at'], 'kind' => __('Seite'),
                        'icon' => 'file-text', 'who' => (int) $p['who_id'] === (int) $user['id'] ? __('Sie') : (string) ($p['who'] ?? ''),
                        'draft' => $p['status'] === 'draft' || Pages::hasUnpublished($p)];
                }
            } catch (\Throwable $e) {
                error_log('[dashboard] recent: ' . $e->getMessage());
            }
        }
        foreach (self::ownTables() as $t) {
            try {
                foreach (Entries::query($t, ['status' => 'all', 'lang' => 'all', 'sort' => 'updated_at', 'dir' => 'desc', 'limit' => $limit]) as $e) {
                    if (empty($e['updated_at'])) continue;
                    $team[] = ['title' => Entries::title($t, $e), 'href' => '/admin/data/' . $t['handle'] . '/' . (int) $e['id'], 'at' => (string) $e['updated_at'],
                        'kind' => (string) ($t['singular'] ?: $t['name']), 'icon' => (string) ($t['icon'] ?: 'table'), 'who' => '', 'draft' => ($e['status'] ?? '') === 'draft'];
                }
            } catch (\Throwable $e) {
                error_log('[dashboard] recent ' . $t['handle'] . ': ' . $e->getMessage());
            }
        }
        usort($team, fn($a, $b) => strcmp($b['at'], $a['at']));
        return ['mine' => $mine, 'team' => array_slice($team, 0, $limit + 2)];
    }

    // ================================================================= Statistiken (nachgeladen, zwischengespeichert)

    /** Änderungen je Tag der letzten 30 Tage: Seiten gespeichert (revisions), davon veröffentlicht, neue Einträge, über API/MCP/KI */
    public static function activity(): array
    {
        $days = [];
        for ($i = 29; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i days"))] = ['pages' => 0, 'published' => 0, 'entries' => 0, 'api' => 0];
        $from = array_key_first($days) . ' 00:00:00';
        $db = app()->db;
        try {
            foreach ($db->fetchAll("SELECT SUBSTR(created_at, 1, 10) AS d, COUNT(*) AS n, SUM(CASE WHEN note = 'Veröffentlicht' THEN 1 ELSE 0 END) AS pub
                FROM revisions WHERE created_at >= ? GROUP BY SUBSTR(created_at, 1, 10)", [$from]) as $r) {
                if (isset($days[$r['d']])) { $days[$r['d']]['pages'] = (int) $r['n']; $days[$r['d']]['published'] = (int) $r['pub']; }
            }
            foreach ($db->fetchAll('SELECT SUBSTR(created_at, 1, 10) AS d, COUNT(*) AS n FROM change_log WHERE created_at >= ? GROUP BY SUBSTR(created_at, 1, 10)', [$from]) as $r) {
                if (isset($days[$r['d']])) $days[$r['d']]['api'] = (int) $r['n'];
            }
        } catch (\Throwable $e) {
            error_log('[dashboard] activity: ' . $e->getMessage());
        }
        foreach (self::ownTables() as $t) {
            try {
                foreach ($db->fetchAll("SELECT SUBSTR(created_at, 1, 10) AS d, COUNT(*) AS n FROM {$t['table']} WHERE created_at >= ? GROUP BY SUBSTR(created_at, 1, 10)", [$from]) as $r) {
                    if (isset($days[$r['d']])) $days[$r['d']]['entries'] += (int) $r['n'];
                }
            } catch (\Throwable) {
            }
        }
        return $days;
    }

    /** Anfragen je Kalenderwoche (12 Wochen), nur lesbare Eingangs-Tabellen – nur Anzahlen */
    public static function requestsWeekly(): array
    {
        $weeks = [];
        $monday = strtotime('monday this week');
        for ($i = 11; $i >= 0; $i--) $weeks[date('Y-m-d', $monday - $i * 7 * 86400)] = 0;
        $from = array_key_first($weeks) . ' 00:00:00';
        foreach (Inbox::readable() as $ib) {
            try {
                foreach (app()->db->fetchAll("SELECT SUBSTR(created_at, 1, 10) AS d, COUNT(*) AS n FROM {$ib['table']} WHERE created_at >= ? GROUP BY SUBSTR(created_at, 1, 10)", [$from]) as $r) {
                    $wk = date('Y-m-d', strtotime('monday this week', strtotime((string) $r['d'])));
                    if (isset($weeks[$wk])) $weeks[$wk] += (int) $r['n'];
                }
            } catch (\Throwable) {
            }
        }
        return $weeks;
    }

    /** Meistbearbeitete Seiten (Speicherungen in 30 Tagen) */
    public static function topPages(int $limit = 5): array
    {
        $out = [];
        try {
            foreach (app()->db->fetchAll("SELECT r.page_id, COUNT(*) AS n FROM revisions r JOIN pages p ON p.id = r.page_id WHERE r.created_at >= ? AND p.type = 'page'
                GROUP BY r.page_id ORDER BY n DESC LIMIT " . $limit, [self::ago(30)]) as $r) {
                if ($p = Pages::find((int) $r['page_id'])) $out[] = ['title' => (string) $p['title'], 'href' => Pages::plainUrl($p) . '?edit=1', 'n' => (int) $r['n']];
            }
        } catch (\Throwable $e) {
            error_log('[dashboard] top: ' . $e->getMessage());
        }
        return $out;
    }

    /** Termine der nächsten 7 Tage aus Kalender-Tabellen, die der Benutzer bearbeiten darf */
    public static function upcoming(int $limit = 8): array
    {
        $out = [];
        if (!Features::on('calendar', false)) return [];
        foreach (Calendar::tables() as $t) {
            if (!can('data.edit', $t['handle'])) continue;
            try {
                foreach (Calendar::upcoming($t, $limit, 7, ['lang' => Lang::default()]) as $o) {
                    $out[] = ['ts' => $o['start']->getTimestamp(), 'title' => Entries::title($t, $o['entry']), 'when' => Calendar::when($o),
                        'table' => (string) $t['name'], 'href' => '/admin/data/' . $t['handle'] . '/' . (int) $o['entry']['id'], 'icon' => (string) ($t['icon'] ?: 'calendar-dots')];
                }
            } catch (\Throwable $e) {
                error_log('[dashboard] upcoming ' . $t['handle'] . ': ' . $e->getMessage());
            }
        }
        usort($out, fn($a, $b) => $a['ts'] <=> $b['ts']);
        return array_slice($out, 0, $limit);
    }

    /** Anonyme Suchbegriffe ohne Treffer (nur wenn die Website-Suche sie zählt – Grundeinstellungen → Suche) */
    public static function searchMisses(int $limit = 8): ?array
    {
        try {
            if (!Features::on('search', false) || !\Core\Search\Search::enabled() || empty(\Core\Search\Search::settings()['misses'])) return null;
            return \Core\Search\Stats::top($limit);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Betriebs-Prüfungen für die Administration (Auszug aus `bin/console health`, ohne Shell): [label => true|false|null] */
    public static function health(): array
    {
        $cfg = app()->config;
        $rows = [
            __('Datenbank erreichbar') => (function () { try { return (int) app()->db->fetchValue('SELECT 1') === 1; } catch (\Throwable) { return false; } })(),
            __('Datenordner beschreibbar') => is_writable(site()->storage()),
            __('Medienordner beschreibbar') => is_writable(site()->mediaDir()) || !is_dir(site()->mediaDir()),
            __('app_key gesetzt') => strlen((string) $cfg->get('app_key')) >= 32,
            __('Kit kompatibel') => app()->theme->compatible(),
            __('Schlüssel für verschlüsselte Formulare') => FormCrypto::ready() ?: null,
        ];
        if (app()->settings->get('sys.maintenance')) $rows[__('Wartungsmodus ist eingeschaltet')] = null;
        if (environment() === 'production') {
            $b = \Core\Network\Stats::lastBackup(site()->key);
            $rows[$b ? __('Letzte Sicherung: {date}', ['date' => date('d.m.Y', $b['at'])]) : __('Keine Sicherung gefunden (site:backup)')] = $b && $b['at'] > time() - 7 * 86400 ? true : null;
        }
        try {
            if (Inbox::needsMigration()) $rows[__('Anfragen: Übernahme in Eingangs-Tabellen ausstehend')] = null;
        } catch (\Throwable) {
        }
        foreach ([fn() => \Core\Data\Shared::health(), fn() => \Core\Search\Search::health(), fn() => \Core\AI\Ai::health(),
            fn() => \Core\Sources\Sources::health(), fn() => \Core\Extensions::health()] as $fn) {
            try {
                foreach ((array) $fn() as $l => $ok) $rows[(string) $l] = $ok === null ? null : (bool) $ok;
            } catch (\Throwable $e) {
                error_log('[dashboard] health: ' . $e->getMessage());
            }
        }
        return $rows;
    }
}
