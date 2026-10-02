<?php
declare(strict_types=1);

namespace Core\Data;

use Core\Config;
use Core\Database;
use Core\Features;
use Core\Media;
use Core\MediaPools;
use Core\PageCache;
use Core\Pages;
use Core\Site;
use Core\Sites;
use Doctrine\DBAL\Connection;

/**
 * Geteilte Datentabellen („Geteilte Daten“, Verbund-Tabellen): eine Tabelle, mehrere Websites dieser Installation.
 *
 *   storage/shared/{key}/share.json    Bezeichnung, Eigentümer-Website (owner), Mitglieder (members), Schalter
 *                                      (members_see_members), automatische Übernahme (auto), Angaben der Websites (sites_info)
 *   storage/shared/{key}/share.sqlite  Registereintrag (data_tables), echte Tabelle data_{key} (+ origin_site, suggest),
 *                                      Verknüpfungstabellen und share_picks (Auswahl je Website: visible|hidden|featured|rejected)
 *
 * Der Schlüssel ist zugleich der Kurzname (handle) der Tabelle. Das Schema legt die Eigentümer-Website fest (ein gemeinsames Schema);
 * jede Website pflegt nur Einträge mit origin_site = eigene Website. Welche fremden Einträge eine Website zeigt, steht in deren
 * Einstellungen („shared.{key}“: owner, members, sites, where, link_origin, canonical, detail_page_id). Bilder liegen im Medien-Pool „data-{key}“,
 * gespeichert werden Pool-IDs; beim Lesen werden sie in Verweise der jeweiligen Website übersetzt (MediaPools::mirror).
 * Einladungen (share.json „invited“): Websites, die der Eigentümer zum Beitreten freigegeben hat – beitreten (shareLocal mit $merge bzw.
 * addMember) macht die Website selbst, so stimmen beide Seiten zu. Verlassen: leave() (Mitglied), unshare() (Eigentümer).
 */
final class Shared
{
    /** Tabellen-IDs geteilter Tabellen beginnen hier (stabil je Schlüssel, kollidiert nicht mit lokalen IDs) */
    public const ID_BASE = 100000000;
    /** Zusätzliche Spalten der Eintragstabelle (als Feldnamen reserviert) */
    public const COLUMNS = ['origin_site', 'suggest'];
    /** Auswahl einer Website: visible (übernommen), featured (hervorgehoben), hidden (ausgeblendet), rejected (Vorschlag abgelehnt) */
    public const STATES = ['visible', 'featured', 'hidden', 'rejected'];
    /**
     * Quellen für Entries::query(…, ['source' => …]):
     * site (Standard: wie für die Website eingestellt), own, owner, members, own_owner, all (alles Sichtbare), featured (nur hervorgehobene)
     */
    public const SOURCES = ['site', 'own', 'owner', 'members', 'own_owner', 'all', 'featured'];
    public const CONFIG = ['owner' => true, 'members' => 'off', 'sites' => [], 'where' => [], 'link_origin' => false, 'canonical' => 'origin', 'detail_page_id' => null];
    /** Canonical fremder Einträge: origin (Ursprungs-Website, falls sie Detailseiten hat – Standard) oder self (eigene Adresse, dann auch in der Sitemap) */
    public const CANONICAL = ['origin', 'self'];

    private static array $dbs = [];
    private static ?array $metas = null;
    private static array $mirrors = [];
    private static array $siteDbs = [];

    // ================================================================= Register

    public static function dir(?string $key = null): string
    {
        return ROOT . '/storage/shared' . ($key !== null ? '/' . $key : '');
    }

    public static function validKey(string $key): bool
    {
        return (bool) preg_match('~^[a-z][a-z0-9_]{1,40}$~', $key);
    }

    /** Alle geteilten Tabellen der Installation [key => meta] */
    public static function all(): array
    {
        if (self::$metas !== null) return self::$metas;
        $out = [];
        foreach (glob(self::dir() . '/*/share.json') ?: [] as $f) {
            $key = basename(dirname($f));
            if (!self::validKey($key)) continue;
            $m = json_decode((string) file_get_contents($f), true);
            if (is_array($m)) $out[$key] = self::normMeta($key, $m);
        }
        ksort($out);
        return self::$metas = $out;
    }

    public static function flush(): void
    {
        self::$metas = null;
    }

    public static function meta(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    private static function normMeta(string $key, array $m): array
    {
        $m += ['label' => $key, 'owner' => Site::DEFAULT, 'members' => [], 'invited' => [], 'members_see_members' => false, 'auto' => [], 'sites_info' => [], 'created' => null];
        $m['members'] = array_values(array_diff(array_map('strval', (array) $m['members']), [$m['owner']]));
        $m['invited'] = array_values(array_diff(array_map('strval', (array) $m['invited']), [$m['owner']], $m['members']));
        $m['auto'] = (array) $m['auto'] + ['enabled' => false, 'sites' => [], 'where' => []];
        $m['members_see_members'] = (bool) $m['members_see_members'];
        return $m;
    }

    private static function saveMeta(string $key, array $meta): void
    {
        @mkdir(self::dir($key), 0770, true);
        file_put_contents(self::dir($key) . '/share.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        self::flush();
        Tables::flush();
    }

    /** Eigentümer + Mitglieder */
    public static function participants(array $meta): array
    {
        return array_values(array_unique(array_merge([(string) $meta['owner']], (array) $meta['members'])));
    }

    /** Geteilte Tabellen, an denen eine Website teilnimmt [key => meta] */
    public static function forSite(?string $site = null): array
    {
        $site ??= site()->key;
        return array_filter(self::all(), fn($m) => in_array($site, self::participants($m), true));
    }

    public static function tableId(string $key): int
    {
        return self::ID_BASE + (int) (crc32($key) % self::ID_BASE);
    }

    // ================================================================= Datenbank

    public static function db(string $key): Database
    {
        if (!isset(self::$dbs[$key])) {
            $db = new Database(['driver' => 'sqlite', 'path' => self::dir($key) . '/share.sqlite']);
            self::init($db);
            self::$dbs[$key] = $db;
        }
        return self::$dbs[$key];
    }

    /** Doctrine-Verbindung (Schema-Abgleich der Eintragstabelle) */
    public static function conn(string $key): Connection
    {
        self::db($key);
        return Dbal::sqlite(self::dir($key) . '/share.sqlite');
    }

    private static function init(Database $db): void
    {
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS data_tables (id INTEGER PRIMARY KEY AUTOINCREMENT, handle VARCHAR(64) NOT NULL UNIQUE, name VARCHAR(191) NOT NULL,
            singular VARCHAR(191), icon VARCHAR(40), description TEXT, fields_json TEXT, settings_json TEXT, sort INT NOT NULL DEFAULT 0,
            created_at VARCHAR(25), updated_at VARCHAR(25))');
        $db->pdo->exec('CREATE TABLE IF NOT EXISTS share_picks (pick_site VARCHAR(40) NOT NULL, pick_entry INT NOT NULL, pick_state VARCHAR(12) NOT NULL,
            pick_at VARCHAR(25), pick_by VARCHAR(191), PRIMARY KEY (pick_site, pick_entry))');
        $db->pdo->exec('CREATE INDEX IF NOT EXISTS share_picks_entry ON share_picks (pick_entry)');
    }

    /** Registereintrag der Tabelle (roh) */
    public static function row(string $key): ?array
    {
        if (!is_file(self::dir($key) . '/share.json')) return null;
        return self::db($key)->fetch('SELECT * FROM data_tables WHERE handle = ?', [$key]);
    }

    /** Datenbank einer anderen Website (nur lesend für Prüfungen; die eigene: app()->db) */
    public static function siteDb(string $site): ?Database
    {
        if ($site === site()->key) return app()->db;
        if (!isset(Sites::all()[$site])) return null;
        if (!array_key_exists($site, self::$siteDbs)) {
            try {
                $cfg = Config::load(ROOT . '/config', $site)->get('db');
                self::$siteDbs[$site] = ($cfg['driver'] ?? 'sqlite') === 'sqlite' && !is_file((string) $cfg['path']) ? null : new Database($cfg);
            } catch (\Throwable) {
                self::$siteDbs[$site] = null;
            }
        }
        return self::$siteDbs[$site];
    }

    // ================================================================= Rechte

    public static function isShared(array $t): bool
    {
        return isset($t['shared']);
    }

    public static function isOwner(array $t, ?string $site = null): bool
    {
        return isset($t['shared']) && ($site ?? site()->key) === $t['shared']['owner'];
    }

    /** Eintrag einer anderen Website? */
    public static function isForeign(array $t, array $e): bool
    {
        return isset($t['shared']) && (string) ($e['origin_site'] ?? site()->key) !== site()->key;
    }

    /** Felder ändern: Eigentümer-Website mit „Tabellen und Felder ändern“ oder Integrator */
    public static function canSchema(array $t): bool
    {
        if (!isset($t['shared'])) return can('data.schema');
        return Features::integrator() || (self::isOwner($t) && can('data.schema'));
    }

    /** Geteilte Tabellen anlegen und Mitglieder zuordnen (Recht „data.shared.manage“ oder Integrator) */
    public static function canManage(): bool
    {
        return Features::integrator() || (Features::on('data.shared', false) && can('data.shared.manage'));
    }

    /** Diese geteilte Tabelle verwalten (Mitglieder, Schalter, Regeln): Eigentümer-Website oder Integrator */
    public static function canAdmin(string $key): bool
    {
        $m = self::meta($key);
        return $m && (Features::integrator() || (self::canManage() && $m['owner'] === site()->key));
    }

    // ================================================================= Anzeige-Einstellungen der Website

    public static function localConfig(string $key): array
    {
        $c = (array) app()->settings->get('shared.' . $key, []);
        $c += self::CONFIG;
        $c['owner'] = (bool) $c['owner'];
        $c['members'] = in_array($c['members'], ['off', 'all', 'selected'], true) ? $c['members'] : 'off';
        $c['sites'] = array_values(array_map('strval', (array) $c['sites']));
        $c['where'] = array_values(array_filter((array) $c['where'], 'is_array'));
        $c['link_origin'] = (bool) $c['link_origin'];
        $c['canonical'] = in_array($c['canonical'], self::CANONICAL, true) ? (string) $c['canonical'] : 'origin';
        $c['detail_page_id'] = ctype_digit((string) ($c['detail_page_id'] ?? '')) ? (int) $c['detail_page_id'] : null;
        return $c;
    }

    public static function saveLocal(string $key, array $values): void
    {
        $c = array_replace(self::localConfig($key), $values);
        app()->settings->set('shared.' . $key, $c);
        Tables::flush();
        PageCache::clear();
    }

    /** Filterbedingungen [[feld, op, wert], …] prüfen (nur echte Felder, bekannte Operatoren) */
    public static function cleanWhere(array $t, array $rows): array
    {
        $out = [];
        $names = array_column($t['fields'], 'name');
        foreach ($rows as $w) {
            [$f, $op, $v] = array_pad(array_values((array) $w), 3, '');
            $f = (string) $f;
            $v = trim((string) $v);
            if (!in_array($f, $names, true) || $v === '') continue;
            $op = in_array($op, ['=', '!=', 'contains', '>=', '<='], true) ? $op : '=';
            $out[] = [$f, $op, mb_substr($v, 0, 200)];
        }
        return array_slice($out, 0, 5);
    }

    // ================================================================= Abfrage

    /**
     * WHERE-Teil für eine Quelle (Parameter werden in derselben Reihenfolge angehängt).
     * $cond: fn(array $where, array &$params): array – Bedingungen wie in Entries (Felder, Operatoren, „heute“)
     */
    public static function sourceSql(array $t, string $source, array &$params, callable $cond): string
    {
        $site = site()->key;
        $owner = (string) $t['shared']['owner'];
        $isOwner = $site === $owner;
        $mm = !empty($t['shared']['members_see_members']);
        $members = array_values(array_diff((array) $t['shared']['members'], [$owner]));
        $pub = "status = 'published'";
        $in = function (array $list) use (&$params): string {
            array_push($params, ...$list);
            return implode(', ', array_fill(0, count($list), '?'));
        };
        $own = function () use (&$params, $site): string { $params[] = $site; return 'origin_site = ?'; };
        $ownerPart = function () use (&$params, $owner, $pub): string { $params[] = $owner; return "(origin_site = ? AND $pub)"; };
        $notHidden = function () use (&$params, $site): string {
            $params[] = $site;
            return "id NOT IN (SELECT pick_entry FROM share_picks WHERE pick_site = ? AND pick_state IN ('hidden', 'rejected'))";
        };
        // Einträge der Mitglieder (ohne diese Website und ohne Eigentümer), optional nur bestimmte Websites und Felder
        $membersPart = function (array $only = [], array $where = []) use (&$params, $site, $owner, $pub, $members, $in, $cond): string {
            $allowed = array_values(array_diff($members, [$site]));
            if ($only) $allowed = array_values(array_intersect($allowed, $only));
            if (!$allowed) return '1 = 0';
            $sql = "(origin_site IN (" . $in($allowed) . ") AND $pub";
            foreach ($cond($where, $params) as $c) $sql .= " AND $c";
            return $sql . ')';
        };
        $or = fn(array $parts) => count($parts) === 1 ? $parts[0] : '(' . implode(' OR ', $parts) . ')';

        switch ($source) {
            case 'own':
                return $own();
            case 'owner':
                return $isOwner ? $own() : $ownerPart();
            case 'members':
                return $isOwner || $mm ? $membersPart() : '1 = 0';
            case 'own_owner':
                return $isOwner ? $own() : $or([$own(), $ownerPart()]);
            case 'all':
                $parts = [$own()];
                if (!$isOwner) $parts[] = $ownerPart();
                if ($isOwner || $mm) $parts[] = $membersPart();
                return $or($parts);
        }
        // site / featured: so, wie die Website es eingestellt hat
        $parts = [$own()];
        if ($isOwner) {
            // Verband: übernommene Vorschläge + automatische Übernahme nach Regeln, abzüglich ausgeblendeter/abgelehnter
            $auto = (array) (self::meta($t['shared']['key'])['auto'] ?? []);
            $sql = '(' . $membersPart() . ' AND ' . $notHidden() . ' AND (id IN (SELECT pick_entry FROM share_picks WHERE pick_site = ? AND pick_state IN (\'visible\', \'featured\'))';
            $params[] = $site;
            if (!empty($auto['enabled'])) {
                $sql .= ' OR ' . $membersPart((array) ($auto['sites'] ?? []), self::cleanWhere($t, (array) ($auto['where'] ?? [])));
            }
            $parts[] = $sql . '))';
        } else {
            $c = self::localConfig($t['shared']['key']);
            if ($c['owner']) $parts[] = '(' . $ownerPart() . ' AND ' . $notHidden() . ')';
            if ($mm && $c['members'] !== 'off' && ($c['members'] === 'all' || $c['sites'])) {
                $parts[] = '(' . $membersPart($c['members'] === 'selected' ? $c['sites'] : [], self::cleanWhere($t, $c['where'])) . ' AND ' . $notHidden() . ')';
            }
        }
        $sql = $or($parts);
        if ($source === 'featured') {
            $params[] = $site;
            $sql = "($sql AND id IN (SELECT pick_entry FROM share_picks WHERE pick_site = ? AND pick_state = 'featured'))";
        }
        return $sql;
    }

    /** Auswahl dieser (oder einer anderen) Website für Einträge [id => state] */
    public static function picks(array $t, array $ids, ?string $site = null): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids || !isset($t['shared'])) return [];
        $out = [];
        foreach (self::db($t['shared']['key'])->fetchAll('SELECT pick_entry, pick_state FROM share_picks WHERE pick_site = ? AND pick_entry IN (' . implode(',', $ids) . ')',
            [$site ?? site()->key]) as $r) {
            $out[(int) $r['pick_entry']] = (string) $r['pick_state'];
        }
        return $out;
    }

    /**
     * Auswahl setzen (null = zurücksetzen). Regeln: übernehmen/ablehnen nur der Eigentümer für Einträge der Mitglieder,
     * ausblenden nur fremde Einträge, hervorheben jeden auf dieser Website sichtbaren Eintrag. @return int Anzahl
     */
    public static function setPick(array $t, array $ids, ?string $state, string $by = ''): int
    {
        if (!isset($t['shared'])) throw new \InvalidArgumentException(__('Keine geteilte Tabelle.'));
        if ($state !== null && !in_array($state, self::STATES, true)) throw new \InvalidArgumentException(__('Unbekannte Auswahl.'));
        $db = self::db($t['shared']['key']);
        $site = site()->key;
        $n = 0;
        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            $e = $db->fetch("SELECT id, origin_site, status FROM {$t['table']} WHERE id = ?", [$id]);
            if (!$e) continue;
            $foreign = (string) $e['origin_site'] !== $site;
            if (in_array($state, ['visible', 'rejected'], true) && (!self::isOwner($t) || !$foreign)) {
                throw new \InvalidArgumentException(__('Übernehmen und Ablehnen kann nur die Website, der die Tabelle gehört – für Einträge anderer Websites.'));
            }
            if ($state === 'hidden' && !$foreign) throw new \InvalidArgumentException(__('Eigene Einträge lassen sich nicht ausblenden – dafür den Status „Entwurf“ nutzen.'));
            if ($foreign && !self::visibleAll($t, $e)) continue;
            $db->query('DELETE FROM share_picks WHERE pick_site = ? AND pick_entry = ?', [$site, $id]);
            if ($state !== null) {
                $db->insert('share_picks', ['pick_site' => $site, 'pick_entry' => $id, 'pick_state' => $state, 'pick_at' => now(), 'pick_by' => mb_substr($by, 0, 190)]);
            }
            $n++;
        }
        PageCache::clear();
        return $n;
    }

    /** Darf diese Website den (fremden) Eintrag überhaupt sehen (Quelle „all“)? */
    public static function visibleAll(array $t, array $e): bool
    {
        if (!isset($t['shared'])) return true;
        $origin = (string) ($e['origin_site'] ?? '');
        if ($origin === site()->key) return true;
        if (($e['status'] ?? 'published') !== 'published') return false;
        if ($origin === $t['shared']['owner']) return true;
        if (!in_array($origin, (array) $t['shared']['members'], true)) return false;
        return self::isOwner($t) || !empty($t['shared']['members_see_members']);
    }

    // ================================================================= Anlegen, Mitglieder, Teilen

    /**
     * Prüft, ob Kurzname oder Adresse (URL-Basis) auf einer der Websites schon vergeben sind. @return list<string> Meldungen
     */
    public static function conflicts(string $key, array $sites, string $route = ''): array
    {
        $out = [];
        foreach ($sites as $s) {
            $label = self::siteInfo($s)['name'];
            // andere geteilte Tabellen dieser Website
            foreach (self::forSite($s) as $k => $m) {
                if ($k === $key) continue;
                $r = self::row($k);
                $st = $r ? (json_decode((string) $r['settings_json'], true) ?: []) : [];
                if ($route !== '' && ($st['route'] ?? '') === $route) $out[] = __('{site}: Die Adresse /{route} nutzt schon die geteilte Tabelle „{name}“.', ['site' => $label, 'route' => $route, 'name' => $m['label']]);
            }
            $db = self::siteDb($s);
            if (!$db) continue;
            try {
                foreach ($db->fetchAll('SELECT handle, name, settings_json FROM data_tables') as $r) {
                    if ($r['handle'] === $key) $out[] = __('{site}: Es gibt dort schon eine eigene Tabelle mit dem Kurznamen „{key}“.', ['site' => $label, 'key' => $key]);
                    $st = json_decode((string) $r['settings_json'], true) ?: [];
                    if ($route !== '' && ($st['route'] ?? '') === $route && $r['handle'] !== $key) {
                        $out[] = __('{site}: Die Adresse /{route} nutzt dort schon „{name}“.', ['site' => $label, 'route' => $route, 'name' => $r['name']]);
                    }
                }
            } catch (\Throwable) {
                // Website noch nie aufgerufen (keine Tabellen) → kein Konflikt
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Neue geteilte Tabelle anlegen. $def aus Tables::validate (mit _shared_owner), Eigentümer = diese Website.
     */
    public static function create(array $def, array $members, array $flags = []): string
    {
        $key = (string) $def['handle'];
        $owner = site()->key;
        if (!self::validKey($key)) throw new \InvalidArgumentException(__('Kurzname: nur a–z, 0–9 und _ (mind. 2 Zeichen).'));
        if (is_dir(self::dir($key)) && is_file(self::dir($key) . '/share.json')) throw new \InvalidArgumentException(__('Eine geteilte Tabelle „{key}“ gibt es schon.', ['key' => $key]));
        $members = self::cleanMembers($members, $owner);
        if ($c = self::conflicts($key, array_merge([$owner], $members), (string) ($def['settings']['route'] ?? ''))) {
            throw new \InvalidArgumentException(implode(' ', $c));
        }
        self::saveMeta($key, self::normMeta($key, [
            'label' => $def['name'], 'owner' => $owner, 'members' => $members,
            'members_see_members' => !empty($flags['members_see_members']), 'created' => date('c'),
        ]));
        $settings = $def['settings'];
        $settings['detail_page_id'] = null;
        self::db($key)->insert('data_tables', [
            'handle' => $key, 'name' => $def['name'], 'singular' => $def['singular'], 'icon' => $def['icon'],
            'description' => $def['description'], 'fields_json' => json_encode($def['fields'], JSON_UNESCAPED_UNICODE),
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE), 'sort' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Tables::flush();
        $t = Tables::sharedTable($key) ?? throw new \RuntimeException('Geteilte Tabelle konnte nicht angelegt werden.');
        Tables::sync($t);
        self::ensurePool($key);
        self::touch($t);
        self::clearCaches($key);
        return $key;
    }

    private static function cleanMembers(array $members, string $owner): array
    {
        $all = array_keys(Sites::all());
        return array_values(array_unique(array_diff(array_intersect(array_map('strval', $members), $all), [$owner])));
    }

    /**
     * Einstellungen ändern: label, members, members_see_members, auto (enabled, sites, where). Neue Mitglieder werden auf Konflikte geprüft.
     */
    public static function update(string $key, array $in): void
    {
        $meta = self::meta($key) ?? throw new \InvalidArgumentException(__('Unbekannte geteilte Tabelle.'));
        $before = self::participants($meta);
        if (isset($in['label']) && trim((string) $in['label']) !== '') $meta['label'] = mb_substr(trim(strip_tags((string) $in['label'])), 0, 80);
        if (isset($in['members'])) {
            $members = self::cleanMembers((array) $in['members'], $meta['owner']);
            $new = array_diff($members, $meta['members']);
            if ($new) {
                $row = self::row($key);
                $st = $row ? (json_decode((string) $row['settings_json'], true) ?: []) : [];
                if ($c = self::conflicts($key, $new, (string) ($st['route'] ?? ''))) throw new \InvalidArgumentException(implode(' ', $c));
            }
            $meta['members'] = $members;
        }
        if (array_key_exists('members_see_members', $in)) $meta['members_see_members'] = !empty($in['members_see_members']);
        if (isset($in['invited'])) {
            $meta['invited'] = array_values(array_diff(self::cleanMembers((array) $in['invited'], $meta['owner']), $meta['members']));
        }
        if (isset($in['auto'])) {
            $t = Tables::sharedTable($key);
            $a = (array) $in['auto'];
            $meta['auto'] = ['enabled' => !empty($a['enabled']),
                'sites' => array_values(array_intersect(array_map('strval', (array) ($a['sites'] ?? [])), $meta['members'])),
                'where' => $t ? self::cleanWhere($t, (array) ($a['where'] ?? [])) : []];
        }
        self::saveMeta($key, self::normMeta($key, $meta));
        self::ensurePool($key);
        foreach (array_unique(array_merge($before, self::participants($meta))) as $s) PageCache::clearSite($s);
    }

    /** Ist diese (oder eine andere) Website zum Beitreten eingeladen (und noch nicht beteiligt)? */
    public static function isInvited(string $key, ?string $site = null): bool
    {
        $m = self::meta($key);
        return $m !== null && in_array($site ?? site()->key, $m['invited'], true);
    }

    /**
     * Diese Website tritt bei (nur auf Einladung des Eigentümers): Mitglied werden, Einladung erledigt. Ohne Konfliktprüfung –
     * eine gleichnamige lokale Tabelle führt shareLocal(…, merge: true) vorher zusammen.
     */
    public static function addMember(string $key): void
    {
        $meta = self::meta($key) ?? throw new \InvalidArgumentException(__('Unbekannte geteilte Tabelle.'));
        $site = site()->key;
        if (in_array($site, self::participants($meta), true)) return;
        if (!in_array($site, $meta['invited'], true)) throw new \InvalidArgumentException(__('Diese Website ist nicht zum Beitreten eingeladen – die Website, der die Tabelle gehört, gibt sie frei.'));
        $meta['members'][] = $site;
        $meta['invited'] = array_values(array_diff($meta['invited'], [$site]));
        self::saveMeta($key, self::normMeta($key, $meta));
        self::ensurePool($key);
        self::clearCaches($key);
    }

    /** Angaben dieser Website (Name, Adresse, Detailseiten vorhanden?) im Register aktualisieren */
    public static function touch(array $t): void
    {
        if (!isset($t['shared']) || !($meta = self::meta($t['shared']['key']))) return;
        $site = site()->key;
        $cur = (array) ($meta['sites_info'][$site] ?? []);
        $info = $cur;
        $name = trim(site_name());
        if ($name !== '' && $name !== CMS_NAME) $info['name'] = $name;
        $url = rtrim(site_url(), '/');
        if ($url !== '' && PHP_SAPI !== 'cli') $info['url'] = $url;
        $tpl = self::localConfig($t['shared']['key'])['detail_page_id'];
        $info['detail'] = $tpl && ($t['settings']['route'] ?? '') !== '' && Pages::find((int) $tpl) !== null;
        if ($info != $cur) {
            $meta['sites_info'][$site] = $info;
            self::saveMeta($t['shared']['key'], $meta);
        }
    }

    /** Name und Adresse einer Website (aus dem Register, sonst aus ihrer Konfiguration) */
    public static function siteInfo(string $site, ?string $key = null): array
    {
        $info = [];
        foreach ($key !== null ? [self::meta($key) ?? []] : self::all() as $m) {
            if (!empty($m['sites_info'][$site])) { $info = (array) $m['sites_info'][$site] + $info; if ($key !== null) break; }
        }
        $cfg = Sites::all()[$site] ?? [];
        $h = (string) (((array) ($cfg['hosts'] ?? []))[0] ?? '');
        $bare = preg_replace('~:\d+$~', '', $h);
        $fallback = (string) ($cfg['base_url'] ?? '') ?: ($h !== '' ? (str_contains($h, ':') || $bare === 'localhost' || str_ends_with($bare, '.localhost') ? 'http://' : 'https://') . $h : '');
        return [
            'name' => (string) (($info['name'] ?? '') ?: ($cfg['label'] ?? '') ?: ($h ?: $site)),
            'url' => rtrim((string) (($info['url'] ?? '') ?: $fallback), '/'),
            'detail' => (bool) ($info['detail'] ?? false),
        ];
    }

    /** Adresse des Eintrags auf seiner Ursprungs-Website (nur fremde Einträge, wenn dort Detailseiten existieren) */
    public static function originUrl(array $t, array $e): ?string
    {
        if (!self::isForeign($t, $e) || ($t['settings']['route'] ?? '') === '' || empty($e['slug'])) return null;
        $info = self::siteInfo((string) $e['origin_site'], $t['shared']['key']);
        if (!$info['detail'] || $info['url'] === '') return null;
        return $info['url'] . \Core\Lang::prefix($e['lang'] ?? null) . '/' . $t['settings']['route'] . '/' . rawurlencode((string) $e['slug']);
    }

    /** Seiten-Caches aller beteiligten Websites leeren */
    public static function clearCaches(string $key): void
    {
        $meta = self::meta($key);
        foreach ($meta ? self::participants($meta) : [site()->key] as $s) PageCache::clearSite($s);
    }

    // ================================================================= Medien (Pool „data-{key}“)

    public static function poolKey(string $key): string
    {
        return rtrim(substr('data-' . str_replace('_', '-', $key), 0, 32), '-');
    }

    /** Pool anlegen bzw. alle Beteiligten eintragen */
    public static function ensurePool(string $key): string
    {
        $pk = self::poolKey($key);
        $meta = self::meta($key);
        $sites = $meta ? self::participants($meta) : [site()->key];
        if (!is_file(MediaPools::dir($pk) . '/pool.json')) {
            MediaPools::create($pk, __('Daten: {name}', ['name' => $meta['label'] ?? $key]), $sites);
        } else {
            $pm = MediaPools::meta($pk);
            if (array_diff($sites, (array) $pm['sites'])) MediaPools::update($pk, (string) $pm['label'], array_values(array_unique(array_merge((array) $pm['sites'], $sites))));
        }
        return $pk;
    }

    /**
     * Medien-ID dieser Website → ID im Pool der Tabelle. Eigene Dateien wandern in den Pool (Media::shareToPool – die Website
     * behält einen Verweis), Dateien anderer Pools werden kopiert.
     */
    public static function toPool(array $t, mixed $localId): ?int
    {
        $id = (int) $localId;
        if ($id <= 0) return null;
        $pk = self::ensurePool($t['shared']['key']);
        $row = app()->db->fetch('SELECT id, pool_ref FROM media WHERE id = ?', [$id]);
        if (!$row) return null;
        if (!empty($row['pool_ref'])) {
            [$p, $pid] = explode(':', (string) $row['pool_ref'], 2) + [1 => 0];
            return $p === $pk ? (int) $pid : self::copyFromPool((string) $p, (int) $pid, $pk);
        }
        return Media::shareToPool($id, $pk);
    }

    private static function copyFromPool(string $src, int $pid, string $dst): ?int
    {
        $m = MediaPools::row($src, $pid);
        if (!$m) return null;
        $have = MediaPools::db($dst)->fetchValue('SELECT id FROM media WHERE file = ?', [$m['file']]);
        if ($have) return (int) $have;
        foreach (Media::fileList($m) as $rel) {
            $from = MediaPools::mediaDir($src) . '/' . $rel;
            if (!is_file($from)) continue;
            @mkdir(dirname(MediaPools::mediaDir($dst) . '/' . $rel), 0775, true);
            copy($from, MediaPools::mediaDir($dst) . '/' . $rel);
        }
        $row = array_filter($m, fn($k) => is_string($k) && $k !== 'id' && $k[0] !== '_' && $k !== 'pool_ref', ARRAY_FILTER_USE_KEY);
        return (int) MediaPools::db($dst)->insert('media', $row + ['updated_at' => now()]);
    }

    /** Pool-ID → Medien-ID dieser Website (Verweis wird bei Bedarf angelegt) */
    public static function fromPool(array $t, mixed $poolId): ?int
    {
        $pid = (int) $poolId;
        if ($pid <= 0) return null;
        $pk = self::poolKey($t['shared']['key']);
        $k = site()->key . ":$pk:$pid";
        if (!array_key_exists($k, self::$mirrors)) {
            try {
                self::$mirrors[$k] = MediaPools::row($pk, $pid) ? MediaPools::mirror($pk, $pid) : null;
            } catch (\Throwable) {
                self::$mirrors[$k] = null;
            }
        }
        return self::$mirrors[$k];
    }

    // ================================================================= Lokale Tabelle teilen / Freigabe beenden

    /**
     * Lokale Tabelle dieser Website in eine geteilte Tabelle verschieben (diese Website wird Eigentümer).
     * Einträge behalten ihre IDs (origin_site = diese Website), Bilder wandern in den Pool, die lokale Tabelle bleibt als Sicherung
     * „zz_unshared_{handle}_{zeit}“ erhalten. Mit $merge in eine bestehende geteilte Tabelle gleichen Kurznamens (neue IDs,
     * Verweise lokaler Tabellen werden umgeschrieben) – als Eigentümer oder als eingeladene Website (wird dabei Mitglied).
     * $skip: [lokale ID => ID in der geteilten Tabelle] – diese Einträge nicht übernehmen, Verweise zeigen auf den vorhandenen
     * Eintrag (z. B. doppelte Glossar-Begriffe). $map (Rückgabe): [alte lokale ID => ID in der geteilten Tabelle]. @return list<string> Protokoll
     */
    public static function shareLocal(string $handle, array $members, bool $merge = false, array $flags = [], ?array &$map = null, array $skip = []): array
    {
        $log = [];
        $row = app()->db->fetch('SELECT * FROM data_tables WHERE handle = ?', [$handle]) ?? throw new \InvalidArgumentException(__('Lokale Tabelle „{key}“ nicht gefunden.', ['key' => $handle]));
        Tables::flush();
        $local = Tables::find((int) $row['id']);
        if (!$local || Tables::isInbox($local)) throw new \InvalidArgumentException(__('Eingangs-Tabellen (verschlüsselte Anfragen) lassen sich nicht teilen.'));
        foreach ($local['fields'] as $f) {
            if (in_array($f['name'], self::COLUMNS, true)) throw new \InvalidArgumentException(__('Das Feld „{name}“ ist in geteilten Tabellen reserviert – bitte zuerst umbenennen.', ['name' => $f['name']]));
            if (in_array($f['type'], ['relation', 'relations'], true) && ($f['target'] ?? '') !== $handle) {
                $target = self::meta((string) ($f['target'] ?? ''));
                if (!$target || $target['owner'] !== site()->key) {
                    throw new \InvalidArgumentException(__('Feld „{label}“ verknüpft die lokale Tabelle „{target}“. Verknüpfungen sind nur zu geteilten Tabellen derselben Website möglich – bitte zuerst „{target}“ teilen.', ['label' => $f['label'], 'target' => (string) ($f['target'] ?? '')]));
                }
            }
        }
        $localCfgDetail = $local['settings']['detail_page_id'] ?? null;
        if ($merge) {
            $meta = self::meta($handle) ?? throw new \InvalidArgumentException(__('Zum Zusammenführen gibt es keine geteilte Tabelle „{key}“.', ['key' => $handle]));
            $joining = $meta['owner'] !== site()->key;
            if ($joining && !in_array(site()->key, $meta['invited'], true) && !in_array(site()->key, $meta['members'], true)) {
                throw new \InvalidArgumentException(__('Zusammenführen kann nur die Website, der die geteilte Tabelle gehört – oder eine Website, die sie zum Beitreten eingeladen hat.'));
            }
            // Kurzname ist lokal belegt – nur die geteilte Tabelle direkt lesen
            $shared = self::hydrateRaw($handle);
            if ($members && !$joining) self::update($handle, ['members' => array_unique(array_merge($meta['members'], $members))]);
        } else {
            if (self::meta($handle)) throw new \InvalidArgumentException(__('Eine geteilte Tabelle „{key}“ gibt es schon – mit --merge zusammenführen.', ['key' => $handle]));
            $members = self::cleanMembers($members, site()->key);
            // eigene Website: lokaler Kurzname ist naturgemäß belegt → nur die anderen prüfen
            if ($c = self::conflicts($handle, $members, (string) $local['settings']['route'])) throw new \InvalidArgumentException(implode(' ', $c));
            self::saveMeta($handle, self::normMeta($handle, ['label' => $local['name'], 'owner' => site()->key, 'members' => $members,
                'members_see_members' => !empty($flags['members_see_members']), 'created' => date('c')]));
            $settings = $local['settings'];
            $settings['detail_page_id'] = null;
            self::db($handle)->insert('data_tables', [
                'handle' => $handle, 'name' => $local['name'], 'singular' => $local['singular'], 'icon' => $local['icon'], 'description' => $local['description'],
                'fields_json' => json_encode($local['fields'], JSON_UNESCAPED_UNICODE), 'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                'sort' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $shared = self::hydrateRaw($handle);
            Tables::sync($shared);
            $log[] = __('Geteilte Tabelle „{key}“ angelegt (Eigentümer: {site}).', ['key' => $handle, 'site' => site()->key]);
        }
        self::ensurePool($handle);
        $sdb = self::db($handle);
        $have = array_column($sdb->fetchAll("PRAGMA table_info({$shared['table']})"), 'name');
        $skip = $merge ? array_map('intval', $skip) : [];
        $map = $skip;
        $media = array_column(array_filter($local['fields'], fn($f) => in_array($f['type'], ['media', 'file'], true)), 'name');
        $moved = 0;
        $renamed = [];
        $sdb->transaction(function () use ($local, $shared, $sdb, $have, $merge, $media, $skip, &$map, &$moved, &$renamed) {
            $fix = [];
            foreach (app()->db->fetchAll("SELECT * FROM {$local['table']} ORDER BY id") as $r) {
                if (isset($skip[(int) $r['id']])) continue;
                $ins = array_intersect_key($r, array_flip($have));
                foreach ($media as $mf) {
                    if (!empty($r[$mf])) $ins[$mf] = self::toPool($shared, (int) $r[$mf]);
                }
                $ins['origin_site'] = site()->key;
                $ins['suggest'] = 0;
                if ($merge) {
                    unset($ins['id']);
                    // Adresse (Slug) muss in der geteilten Tabelle je Sprache eindeutig bleiben
                    $base = (string) ($ins['slug'] ?? '') ?: 'eintrag';
                    for ($n = 2, $slug = $base; $sdb->fetchValue("SELECT id FROM {$shared['table']} WHERE slug = ? AND COALESCE(lang, '') = ?", [$slug, (string) ($ins['lang'] ?? '')]); $n++) {
                        $slug = $base . '-' . $n;
                    }
                    if ($slug !== $base && $base === (string) ($ins['slug'] ?? '')) $renamed[] = $base . ' → ' . $slug;
                    $ins['slug'] = $slug;
                }
                $newId = $sdb->insert($shared['table'], $ins);
                $map[(int) $r['id']] = $merge ? $newId : (int) $r['id'];
                $fix[$map[(int) $r['id']]] = $r;
                $moved++;
            }
            // Zusammenführen: Übersetzungsgruppen und Selbstverweise auf die neuen IDs umschreiben (je Zeile, keine Überschneidung)
            if ($merge) {
                $selfRel = array_column(array_filter($local['fields'], fn($f) => $f['type'] === 'relation' && ($f['target'] ?? '') === $local['handle']), 'name');
                foreach ($fix as $newId => $r) {
                    $upd = [];
                    if (!empty($r['translation_group'])) $upd['translation_group'] = isset($skip[(int) $r['translation_group']]) ? null : ($map[(int) $r['translation_group']] ?? null);
                    foreach ($selfRel as $fn) if (!empty($r[$fn])) $upd[$fn] = $map[(int) $r[$fn]] ?? null;
                    if ($upd) $sdb->update($shared['table'], $upd, 'id = :id', ['id' => $newId]);
                }
            }
            foreach ($local['fields'] as $f) {
                if ($f['type'] !== 'relations') continue;
                $pv = Tables::pivot($local, $f['name']);
                $self = ($f['target'] ?? '') === $local['handle'];
                foreach (app()->db->fetchAll("SELECT * FROM $pv") as $p) {
                    $eid = $map[(int) $p['entry_id']] ?? null;
                    if ($eid === null) continue;
                    $tid = $self ? ($map[(int) $p['target_id']] ?? null) : (int) $p['target_id'];
                    if ($tid === null) continue;
                    $sdb->query('INSERT OR IGNORE INTO ' . Tables::pivot($shared, $f['name']) . ' (entry_id, target_id, sort) VALUES (?, ?, ?)', [$eid, $tid, (int) $p['sort']]);
                }
            }
        });
        $log[] = __('{n} Einträge übernommen.', ['n' => $moved]);
        if ($renamed) $log[] = __('Adresse geändert, weil sie in der geteilten Tabelle schon vergeben war: {list}', ['list' => implode(', ', $renamed)]);
        if ($skip) $log[] = __('{n} Einträge nicht übernommen – stattdessen gilt der vorhandene Eintrag der geteilten Tabelle.', ['n' => count($skip)]);
        // Verweise anderer lokaler Tabellen auf umnummerierte Einträge
        if ($merge && $map) {
            foreach (app()->db->fetchAll('SELECT * FROM data_tables WHERE handle != ?', [$handle]) as $or) {
                $ot = Tables::find((int) $or['id']);
                if (!$ot || Tables::isInbox($ot)) continue;
                foreach ($ot['fields'] as $f) {
                    if (($f['target'] ?? '') !== $handle) continue;
                    foreach ($map as $old => $new) {
                        if ($f['type'] === 'relation') app()->db->query("UPDATE {$ot['table']} SET {$f['name']} = ? WHERE {$f['name']} = ?", [-$new, $old]);
                        elseif ($f['type'] === 'relations') app()->db->query('UPDATE ' . Tables::pivot($ot, $f['name']) . ' SET target_id = ? WHERE target_id = ?', [-$new, $old]);
                    }
                    // zweistufig (negativ → positiv), damit sich alte und neue IDs nicht überschneiden
                    if ($f['type'] === 'relation') app()->db->query("UPDATE {$ot['table']} SET {$f['name']} = -{$f['name']} WHERE {$f['name']} < 0");
                    elseif ($f['type'] === 'relations') app()->db->query('UPDATE ' . Tables::pivot($ot, $f['name']) . ' SET target_id = -target_id WHERE target_id < 0');
                    $log[] = __('Verweise in „{table}“ → „{field}“ umgeschrieben.', ['table' => $ot['name'], 'field' => $f['label']]);
                }
            }
            $log[] = __('Hinweis: Eintrags-IDs in Blockinhalten (z. B. Filterwerte) werden nicht umgeschrieben – Filter nach Namen/Slugs funktionieren weiter.');
        }
        // Lokale Tabelle als Sicherung umbenennen, Registereintrag entfernen
        $sm = Dbal::conn()->createSchemaManager();
        $stamp = date('YmdHis');
        foreach ($local['fields'] as $f) {
            if ($f['type'] === 'relations' && $sm->tablesExist([Tables::pivot($local, $f['name'])])) {
                $sm->renameTable(Tables::pivot($local, $f['name']), 'zz_unshared_' . $handle . '_' . $stamp . '_' . $f['name']);
            }
        }
        $sm->renameTable($local['table'], 'zz_unshared_' . $handle . '_' . $stamp);
        self::dropIndexes('zz_unshared_' . $handle . '_' . $stamp);
        app()->db->query('DELETE FROM data_tables WHERE id = ?', [(int) $local['id']]);
        $log[] = __('Lokale Tabelle als Sicherung „{name}“ behalten.', ['name' => 'zz_unshared_' . $handle . '_' . $stamp]);
        Tables::flush();
        if ($merge && !empty($joining)) {
            self::addMember($handle);
            $log[] = __('Diese Website ist jetzt beteiligt.');
        }
        if ($localCfgDetail) self::saveLocal($handle, ['detail_page_id' => (int) $localCfgDetail]);
        if ($t = Tables::sharedTable($handle)) self::touch($t);
        self::clearCaches($handle);
        return $log;
    }

    /**
     * Indizes einer Sicherungstabelle entfernen: Index-Namen gelten in SQLite datenbankweit (data_{handle}_slug …) und
     * würden sonst ein späteres Wiederanlegen der Tabelle (data:unshare) blockieren.
     */
    private static function dropIndexes(string $table): void
    {
        $sm = Dbal::conn()->createSchemaManager();
        foreach ($sm->listTableIndexes($table) as $idx) {
            if ($idx->isPrimary()) continue;
            try {
                $sm->dropIndex($idx->getName(), $table);
            } catch (\Throwable) {
                // SQLite-Autoindex o. Ä. – bleibt
            }
        }
    }

    /** Geteilte Tabelle ohne Website-Überlagerung (auch wenn der Kurzname lokal belegt ist) */
    private static function hydrateRaw(string $key): array
    {
        Tables::flush();
        return Tables::sharedTable($key) ?? throw new \RuntimeException('Geteilte Tabelle „' . $key . '“ nicht lesbar.');
    }

    /**
     * Freigabe beenden (nur Eigentümer): Tabelle wird wieder eine lokale Tabelle dieser Website mit den eigenen Einträgen
     * (mit $all: allen Einträgen). Die geteilten Daten bleiben als Sicherung unter storage/shared/_removed/ liegen. @return list<string>
     */
    public static function unshare(string $key, bool $all = false): array
    {
        $meta = self::meta($key) ?? throw new \InvalidArgumentException(__('Unbekannte geteilte Tabelle.'));
        if ($meta['owner'] !== site()->key) throw new \InvalidArgumentException(__('Die Freigabe beenden kann nur die Website, der die Tabelle gehört ({site}).', ['site' => $meta['owner']]));
        $t = self::hydrateRaw($key);
        $rows = self::db($key)->fetchAll("SELECT * FROM {$t['table']}" . ($all ? '' : ' WHERE origin_site = ?') . ' ORDER BY id', $all ? [] : [site()->key]);
        self::toLocal($key, $t, $rows);
        unset(self::$dbs[$key]);
        @mkdir(self::dir('_removed'), 0770, true);
        rename(self::dir($key), self::dir('_removed') . '/' . $key . '-' . date('YmdHis'));
        self::flush();
        Tables::flush();
        foreach (self::participants($meta) as $s) PageCache::clearSite($s);
        return [__('„{name}“ ist wieder eine eigene Tabelle dieser Website ({n} Einträge).', ['name' => $t['name'], 'n' => count($rows)]),
            __('Die geteilten Daten liegen als Sicherung unter storage/shared/_removed/.')];
    }

    /**
     * Mitglied verlässt die geteilte Tabelle: sie wird wieder eine eigene Tabelle dieser Website – mit den eigenen Einträgen und mit
     * $keepForeign zusätzlich Kopien der fremden Einträge, die diese Website zuletzt gezeigt hat (Quelle „site“, veröffentlicht).
     * IDs und Adressen bleiben gleich (Verweise entry:{key}:{id} wirken weiter). Die eigenen Einträge verlassen die geteilte Tabelle
     * (Sicherung als JSON unter storage/shared/{key}/left/), Auswahl und Anzeige-Einstellungen dieser Website werden entfernt.
     * @return list<string> Protokoll
     */
    public static function leave(string $key, bool $keepForeign = true): array
    {
        $meta = self::meta($key) ?? throw new \InvalidArgumentException(__('Unbekannte geteilte Tabelle.'));
        $site = site()->key;
        if ($meta['owner'] === $site) throw new \InvalidArgumentException(__('Die Website, der die Tabelle gehört, kann sie nicht verlassen – sie beendet die Freigabe.'));
        if (!in_array($site, $meta['members'], true)) throw new \InvalidArgumentException(__('Diese Website ist an „{key}“ nicht beteiligt.', ['key' => $key]));
        if (app()->db->fetch('SELECT id FROM data_tables WHERE handle = ?', [$key])) {
            throw new \InvalidArgumentException(__('Es gibt hier schon eine eigene Tabelle „{key}“ – bitte zuerst umbenennen.', ['key' => $key]));
        }
        Tables::flush();
        $t = Tables::sharedTable($key) ?? throw new \RuntimeException('Geteilte Tabelle „' . $key . '“ nicht lesbar.');
        $db = self::db($key);
        $own = $db->fetchAll("SELECT * FROM {$t['table']} WHERE origin_site = ? ORDER BY id", [$site]);
        $foreign = [];
        if ($keepForeign) {
            $ids = array_column(array_filter(Entries::query($t, ['status' => 'published', 'lang' => 'all', 'source' => 'site', 'limit' => 100000]),
                fn($e) => (string) $e['origin_site'] !== $site), 'id');
            foreach (array_chunk(array_map('intval', $ids), 500) as $chunk) {
                array_push($foreign, ...$db->fetchAll("SELECT * FROM {$t['table']} WHERE id IN (" . implode(',', $chunk) . ') ORDER BY id'));
            }
        }
        self::toLocal($key, $t, array_merge($own, $foreign));
        // Eigene Einträge aus der geteilten Tabelle nehmen (Sicherung vorher)
        $ownIds = array_map(fn($r) => (int) $r['id'], $own);
        $backup = ['site' => $site, 'left' => date('c'), 'rows' => $own, 'pivots' => []];
        foreach ($t['fields'] as $f) {
            if ($f['type'] !== 'relations' || !$ownIds) continue;
            $backup['pivots'][$f['name']] = $db->fetchAll('SELECT * FROM ' . Tables::pivot($t, $f['name']) . ' WHERE entry_id IN (' . implode(',', $ownIds) . ')');
        }
        @mkdir(self::dir($key) . '/left', 0770, true);
        file_put_contents(self::dir($key) . '/left/' . $site . '-' . date('YmdHis') . '.json', json_encode($backup, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $db->transaction(function () use ($db, $t, $site, $ownIds) {
            foreach (array_chunk($ownIds, 500) as $chunk) {
                $in = implode(',', $chunk);
                foreach ($t['fields'] as $f) if ($f['type'] === 'relations') $db->query('DELETE FROM ' . Tables::pivot($t, $f['name']) . " WHERE entry_id IN ($in)");
                $db->query("DELETE FROM share_picks WHERE pick_entry IN ($in)");
                $db->query("DELETE FROM {$t['table']} WHERE id IN ($in)");
            }
            $db->query('DELETE FROM share_picks WHERE pick_site = ?', [$site]);
        });
        $meta['members'] = array_values(array_diff($meta['members'], [$site]));
        unset($meta['sites_info'][$site]);
        self::saveMeta($key, self::normMeta($key, $meta));
        app()->settings->delete('shared.' . $key);
        Tables::flush();
        foreach (array_unique(array_merge([$site], self::participants($meta))) as $s) PageCache::clearSite($s);
        $log = [__('„{name}“ ist wieder eine eigene Tabelle dieser Website: {own} eigene Einträge, {copies} Kopien von anderen Websites.', ['name' => $t['name'], 'own' => count($own), 'copies' => count($foreign)])];
        if ($own) $log[] = __('Die eigenen Einträge erscheinen nicht mehr bei den anderen Websites (Sicherung unter storage/shared/{key}/left/).', ['key' => $key]);
        return $log;
    }

    /**
     * Lokale Tabelle dieser Website aus der geteilten anlegen (gleicher Kurzname, gleiche IDs) und $rows (Rohzeilen der geteilten
     * Tabelle) eintragen: Bilder aus dem Pool als Verweise dieser Website, Verknüpfungen der übernommenen Einträge, Detailseite.
     */
    private static function toLocal(string $key, array $t, array $rows): int
    {
        $detail = self::localConfig($key)['detail_page_id'];
        $settings = $t['settings'];
        $settings['detail_page_id'] = $detail;
        $def = ['handle' => $key, 'name' => $t['name'], 'singular' => $t['singular'], 'icon' => $t['icon'], 'description' => (string) $t['description'],
            'fields' => $t['fields'], 'settings' => $settings];
        $media = array_column(array_filter($t['fields'], fn($f) => in_array($f['type'], ['media', 'file'], true)), 'name');
        $pivots = [];
        foreach ($t['fields'] as $f) if ($f['type'] === 'relations') $pivots[$f['name']] = self::db($key)->fetchAll('SELECT * FROM ' . Tables::pivot($t, $f['name']));
        // Reste früherer Sicherungen (gleichnamige Indizes) dürfen das Anlegen nicht blockieren
        foreach (app()->db->driver === 'sqlite' ? app()->db->fetchAll("SELECT name, tbl_name FROM sqlite_master WHERE type = 'index' AND name LIKE ? AND tbl_name != ?", ['data_' . $key . '_%', 'data_' . $key]) : [] as $ix) {
            if (str_starts_with((string) $ix['tbl_name'], 'zz_unshared_')) app()->db->pdo->exec('DROP INDEX IF EXISTS "' . str_replace('"', '', (string) $ix['name']) . '"');
        }
        try {
            $id = Tables::create($def);
        } catch (\Throwable $e) {
            // halb angelegte lokale Tabelle zurücknehmen – die geteilte bleibt unverändert
            app()->db->query('DELETE FROM data_tables WHERE handle = ?', [$key]);
            $sm = Dbal::conn()->createSchemaManager();
            if ($sm->tablesExist(['data_' . $key])) $sm->dropTable('data_' . $key);
            Tables::flush();
            throw new \RuntimeException(__('Freigabe konnte nicht beendet werden: {error}', ['error' => $e->getMessage()]));
        }
        $local = Tables::find($id);
        $cols = array_column(app()->db->fetchAll(app()->db->driver === 'mysql' ? "SHOW COLUMNS FROM {$local['table']}" : "PRAGMA table_info({$local['table']})"), app()->db->driver === 'mysql' ? 'Field' : 'name');
        $ids = [];
        foreach ($rows as $r) {
            foreach ($media as $mf) $r[$mf] = self::fromPool($t, $r[$mf] ?? null);
            app()->db->insert($local['table'], array_intersect_key($r, array_flip($cols)));
            $ids[(int) $r['id']] = true;
        }
        foreach ($pivots as $name => $prs) {
            foreach ($prs as $p) {
                if (isset($ids[(int) $p['entry_id']])) app()->db->insert(Tables::pivot($local, $name), ['entry_id' => (int) $p['entry_id'], 'target_id' => (int) $p['target_id'], 'sort' => (int) $p['sort']]);
            }
        }
        return $id;
    }

    // ================================================================= Blöcke

    /**
     * Blockfeld „Quelle“ für Datenliste, Kalender und Nächste Termine – nur auf Websites, die an geteilten Tabellen teilnehmen.
     * $list: zusätzlich „Hervorgehobene zuerst“ (Datenliste).
     */
    public static function blockFields(bool $list = false): array
    {
        try {
            if (!self::forSite()) return [];
        } catch (\Throwable) {
            return [];
        }
        $owner = term('shared_owner');
        $out = [['name' => 'source', 'label' => __('Quelle (geteilte Tabellen)'), 'type' => 'select', 'default' => '', 'width' => 'half',
            'options' => ['' => __('Wie für die Website eingestellt'), 'own' => __('Nur eigene Einträge'), 'own_owner' => __('Eigene + {owner}', ['owner' => $owner]),
                'owner' => __('Nur {owner}', ['owner' => $owner]), 'all' => __('Alle freigegebenen Einträge'), 'featured' => __('Nur hervorgehobene')],
            'help' => __('Nur für geteilte Tabellen: welche Einträge dieser Block zeigt. Filter gelten zusätzlich.')]];
        if ($list) $out[] = ['name' => 'featured_first', 'label' => __('Hervorgehobene Einträge zuerst'), 'type' => 'bool', 'default' => false, 'width' => 'half'];
        return $out;
    }

    // ================================================================= Wartung

    /** Datenbanken anlegen/abgleichen (migrate) – idempotent. @return list<string> */
    public static function migrate(): array
    {
        $out = [];
        foreach (self::all() as $key => $meta) {
            try {
                self::db($key);
                $t = Tables::sharedTable($key);
                if ($t) {
                    Tables::sync($t);
                    self::ensurePool($key);
                }
                $out[] = "geteilt: $key " . ($t ? 'ok' : '(kein Registereintrag)');
            } catch (\Throwable $e) {
                $out[] = "geteilt: $key FEHLER " . $e->getMessage();
            }
        }
        return $out;
    }

    /** Prüfungen für `health` [Bezeichnung => ok] */
    public static function health(): array
    {
        $out = [];
        if (is_dir(self::dir()) && !is_writable(self::dir())) $out['storage/shared beschreibbar'] = false;
        foreach (self::all() as $key => $meta) {
            $ok = isset(Sites::all()[$meta['owner']]);
            try {
                $row = self::row($key);
                $ok = $ok && $row && (int) self::db($key)->fetchValue("SELECT COUNT(*) FROM data_{$key}") >= 0 && is_writable(self::dir($key) . '/share.sqlite');
            } catch (\Throwable) {
                $ok = false;
            }
            $out["Geteilte Daten „{$key}“"] = (bool) $ok;
        }
        return $out;
    }
}
