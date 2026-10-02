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
        // Verwaltung noch unter der üblichen Adresse /admin (Standard) – sollte geändert werden (Core\AdminPath); nur, wer es darf
        if (\Core\AdminPath::canManage() && !\Core\AdminPath::custom() && !\Core\AdminPath::fromEnv()) {
            $add('adminpath', 1, 'warn', 'shield-check', 1, __('Adresse der Verwaltung ändern'),
                __('Die Anmeldung liegt noch unter /admin – dort suchen automatische Login-Scanner zuerst. Eine eigene Adresse hält sie fern.'),
                '/admin/system#adminpath', __('Adresse ändern'));
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
        // Platzhalter und Redaktionsnotizen (Inhalt, daher auch für die Redaktion): alle Fundstellen je Seite/Eintrag und Block
        // aufklappbar (View: pages/_placeholders.php) mit „Im Frontend bearbeiten“ (Editor springt zum Block), „In der Verwaltung“
        // und – nur bei Platzhaltern – „Ist gewollt“ je Fundstelle. Notizen sind öffentlich unsichtbar, daher nur Hinweis (info).
        if (can('pages.edit') || can('settings.edit') || $admin) {
            $all = self::placeholders(app()->db, self::placeholdersOk(), 200);
            if ($all) {
                $first = $all[0];
                $nPh = count(array_filter($all, fn($h) => $h['kind'] === 'placeholder'));
                $nNote = count($all) - $nPh;
                $pages = array_values(array_unique(array_map(fn($h) => $h['title'], $all)));
                $title = match (true) {
                    count($all) === 1 && $nPh === 1 => __('Platzhalter {text} ersetzen', ['text' => mb_strimwidth($first['text'], 0, 90, '…]')]),
                    count($all) === 1 => __('Notiz: {text}', ['text' => mb_strimwidth($first['text'], 0, 90, '…')]),
                    $nNote === 0 => __('{n} Platzhalter in Seiten ersetzen', ['n' => $nPh]),
                    $nPh === 0 => __('{n} Redaktionsnotizen offen', ['n' => $nNote]),
                    default => __('{n} Platzhalter und {m} Notizen offen', ['n' => $nPh, 'm' => $nNote]),
                };
                $add('placeholder', 2, $nPh ? 'warn' : 'info', 'brackets-curly', count($all), $title,
                    (count($pages) === 1 ? __('Auf der Seite „{page}“.', ['page' => $first['title']])
                        : __(array_filter($all, fn($h) => $h['entry'] !== '') ? 'An {n} Stellen (Seiten und Einträge): {pages}' : 'Auf {n} Seiten: {pages}', ['n' => count($pages), 'pages' => implode(', ', array_slice($pages, 0, 4)) . (count($pages) > 4 ? ' …' : '')]))
                        . ' ' . ($nPh ? __('Ist eine Klammer Absicht (z. B. in einer Anleitung), bestätigen Sie sie.') : __('Notizen [# … #] sehen nur Angemeldete – erledigt? Im Editor löschen.')),
                    self::placeholderEditUrl($first), $first['entry'] !== '' ? __('Eintrag bearbeiten') : __('Im Frontend bearbeiten'));
                $out[count($out) - 1]['hits'] = $all;
            }
        }
        if (can('pages.edit')) {
            $old = 0;
            foreach (Pages::all() as $p) {
                if (($p['status'] !== 'published' || \Core\Review\Drafts::pageChanged($p)) && (string) $p['updated_at'] < self::ago(\Core\Review\Drafts::STALE_DAYS)) {
                    $old++;
                }
            }
            $add('drafts', 5, 'info', 'pencil-simple', $old, __('Liegengebliebene Entwürfe'), __('Seiten mit Änderungen, die seit über 14 Tagen nicht veröffentlicht sind.'),
                '/admin/entwuerfe?filter=stale', __('Entwürfe prüfen'));   // Verwaltung → Entwürfe (Core\Review\Drafts)
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
                '/admin/entwuerfe?filter=stale', __('Entwürfe prüfen'));
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
        // Nur nötig, wenn die Adresse nicht eindeutig ist: mehrere Domains in der Website-Konfiguration und keine base_url
        // (www/ohne www leitet meist schon der Server um; E-Mails, Sitemap und Cron brauchen dann trotzdem eine feste Adresse)
        $hosts = array_filter(site()->hosts(), fn($h) => !str_contains($h, 'localhost') && !preg_match('~^[\d.:]+$~', $h));
        $checks[] = ['ok' => (string) $s->get('sys.site_url') !== '' || (string) app()->config->get('base_url', '') !== '' || count($hosts) <= 1,
            'label' => __('Kanonische Domain festgelegt'), 'link' => '/admin/system#website', 'admin' => true];
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

    /** Erster [Platzhalter] in veröffentlichten Seiten (ohne bestätigte, ohne Redaktionsnotizen): ['id', 'title', 'text'] oder null */
    public static function placeholder(): ?array
    {
        foreach (self::placeholders(app()->db, self::placeholdersOk(), 100) as $h) {
            if ($h['kind'] === 'placeholder') return $h;
        }
        return null;
    }

    /** Sprung zur Fundstelle: Frontend-Editor am Block (resources/js/editor.js: #b-{id} scrollt hin, markiert und öffnet die Felder), Einträge in der Verwaltung */
    public static function placeholderEditUrl(array $hit): string
    {
        if (($hit['entry'] ?? '') !== '') return $hit['url'];
        return $hit['url'] . '?edit=1' . ($hit['block'] !== '' ? '#b-' . rawurlencode($hit['block']) : '');
    }

    /**
     * Offene Stellen in den Seiten einer Website (auch für die Netzwerk-Übersicht mit fremder Datenbank), je Block:
     *   kind „placeholder“: [Platzhalter] in der veröffentlichten Fassung (öffentlich sichtbar!) – ohne „Ist gewollt“-Klammern;
     *   kind „note“:        Redaktionsnotizen [# … #] (Core\EditorNotes, öffentlich unsichtbar) im Arbeitsstand (Entwurf) –
     *                       auch in Einträgen der Inhalts-Tabellen (entry = Tabelle, Sprung in die Verwaltung).
     * Gesucht wird in allen Texten eines Blocks (auch verschachtelt, z. B. Listen-Einträge). url und label (Blocktyp) nur für
     * die eigene Website (Adresse und Kit der fremden Website sind hier unbekannt). $page: nur diese Seite (ohne Einträge).
     * @return list<array{id:int,title:string,text:string,block:string,type:string,label:string,url:string,kind:string,entry:string}>
     */
    public static function placeholders(\Core\Database $db, array $ok, int $max = 100, ?int $page = null): array
    {
        $out = [];
        $own = $db === app()->db;
        $where = $page === null ? '' : ' AND id = ' . $page;
        foreach ($db->fetchAll("SELECT * FROM pages WHERE type = 'page'{$where} ORDER BY is_home DESC, sort, title") as $p) {
            $pub = (string) $p['content_published'];
            $work = (string) ($p['content_draft'] ?? '') ?: $pub;
            if (!str_contains($pub, '[') && !str_contains($work, '[#')) continue;
            $url = null;
            foreach (['note' => $work, 'placeholder' => $pub] as $kind => $raw) {
                if ($raw === '' || !str_contains($raw, $kind === 'note' ? '[#' : '[')) continue;
                $blocks = json_decode($raw, true)['blocks'] ?? null;
                if (!is_array($blocks)) continue;
                foreach ($blocks as $b) {
                    if (!is_array($b) || !isset($b['data'])) continue;
                    $seen = [];
                    $found = [];
                    if ($kind === 'note') {
                        foreach (self::texts($b['data'], false) as $s) foreach (\Core\EditorNotes::find($s) as $n) $found[] = $n;
                    } else {
                        // Notizen zuerst entfernen – Klammern darin („[# siehe [Quelle] #]“) sind keine Platzhalter
                        foreach (self::texts($b['data']) as $s) {
                            $s = \Core\EditorNotes::strip($s);
                            if (str_contains($s, '[') && preg_match_all(self::PLACEHOLDER_RX, $s, $m)) array_push($found, ...$m[0]);
                        }
                    }
                    foreach ($found as $hit) {
                        if (isset($seen[$hit]) || ($kind === 'placeholder' && in_array($hit, $ok, true))) continue;
                        $seen[$hit] = true;
                        $type = (string) ($b['type'] ?? '');
                        $url ??= $own ? Pages::plainUrl($p) : '';
                        $out[] = ['id' => (int) $p['id'], 'title' => (string) $p['title'], 'text' => $hit, 'block' => (string) ($b['id'] ?? ''), 'type' => $type,
                            'label' => $own ? (string) (app()->theme->block($type)['label'] ?? $type) : $type, 'url' => $url, 'kind' => $kind, 'entry' => ''];
                        if (count($out) >= $max) return $out;
                    }
                }
            }
        }
        if ($page !== null) return $out;
        // Redaktionsnotizen in Einträgen (Inhalts-Tabellen; Eingänge nie – verschlüsselt, Angaben von Besuchern)
        foreach ($db->fetchAll('SELECT handle, name, settings_json FROM data_tables ORDER BY id') as $t) {
            $set = json_decode((string) $t['settings_json'], true) ?: [];
            if (($set['kind'] ?? 'content') === 'inbox' || !preg_match('~^[a-z0-9_]+$~', (string) $t['handle'])) continue;
            try {
                $rows = $db->fetchAll("SELECT * FROM data_{$t['handle']} ORDER BY id");
            } catch (\Throwable) {
                continue;   // geteilte Tabelle einer anderen Website
            }
            $tf = (string) ($set['title_field'] ?? '');
            foreach ($rows as $e) {
                $seen = [];
                foreach ($e as $col => $v) {
                    if (!is_string($v) || !str_contains($v, '[#')) continue;
                    foreach (\Core\EditorNotes::find($v) as $hit) {
                        if (isset($seen[$hit])) continue;
                        $seen[$hit] = true;
                        $title = \Core\EditorNotes::strip(strip_tags((string) ($e[$tf] ?? ''))) ?: '#' . $e['id'];
                        $out[] = ['id' => (int) $e['id'], 'title' => (string) $t['name'] . ': ' . $title, 'text' => $hit, 'block' => (string) $col, 'type' => 'entry',
                            'label' => (string) $t['name'], 'url' => $own ? url('/admin/data/' . $t['handle'] . '/' . (int) $e['id']) : '', 'kind' => 'note', 'entry' => (string) $t['handle']];
                        if (count($out) >= $max) return $out;
                    }
                }
            }
        }
        return $out;
    }

    /** Alle Texte eines Block-Inhalts (rekursiv); $plain: HTML ohne Tags und Entities – Klammern über Formatierungen hinweg werden so gefunden */
    private static function texts(mixed $v, bool $plain = true): \Generator
    {
        if (is_string($v)) {
            yield $plain && (str_contains($v, '<') || str_contains($v, '&')) ? html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_HTML5, 'UTF-8') : $v;
        } elseif (is_array($v)) {
            foreach ($v as $k => $x) if ($k !== '_fx' && $k !== '_fit') yield from self::texts($x, $plain);
        }
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
            fn() => \Core\Sources\Sources::health(), fn() => \Core\Extensions::health(), fn() => \Core\Fragments::health()] as $fn) {
            try {
                foreach ((array) $fn() as $l => $ok) $rows[(string) $l] = $ok === null ? null : (bool) $ok;
            } catch (\Throwable $e) {
                error_log('[dashboard] health: ' . $e->getMessage());
            }
        }
        return $rows;
    }
}
