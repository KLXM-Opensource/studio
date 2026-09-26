<?php
declare(strict_types=1);

namespace Core\Sources;

use Core\Data\Tables;
use Core\Database;
use Core\Features;
use Core\Settings;

/**
 * Externe Quellen: Feeds (RSS, Atom), JSON-APIs, XML und OpenImmo werden serverseitig abgerufen und per Zuordnung als
 * Einträge einer Datentabelle gespeichert – danach funktionieren Datenliste, Detailseiten, Suche, API/MCP und JSON-LD
 * wie bei selbst gepflegten Einträgen. Besucher stellen nie Anfragen an die Quelle.
 *
 * Tabellen der Website:
 *   ext_sources        Quelle: Adresse, Format, Anmeldung (verschlüsselt), Optionen, Zuordnung, Zieltabelle, Stand
 *   ext_source_items   Herkunft je Eintrag: Quelle + externe ID + Prüfsumme → Eintrag der Zieltabelle (schreibgeschützt)
 *   ext_source_log     Protokoll der Abrufe (je Quelle die letzten 20)
 *   ext_source_media   geladene Bilder je Quelle (Adresse → Medien-ID, kein zweiter Download)
 * Funktion „sources“ (Core\Features, Standard AUS), Recht „sources.manage“.
 */
final class Sources
{
    public const FORMATS = ['rss' => 'RSS 2.0 / RSS 1.0', 'atom' => 'Atom', 'json' => 'JSON-API', 'xml' => 'XML (XPath)', 'openimmo' => 'OpenImmo (XML oder ZIP)'];
    public const AUTH = ['none', 'bearer', 'header', 'basic'];
    public const LOG_KEEP = 20;

    public const DEFAULTS = [
        'items_path' => '', 'xpath' => '', 'schedule' => 'daily', 'ttl' => 60, 'timeout' => 15, 'max_items' => 200, 'max_mb' => 5,
        'ua' => '', 'missing' => 'hide', 'status' => 'published', 'images' => true, 'example' => false,
    ];

    public static function schedules(): array
    {
        return ['manual' => __('nur manuell'), 'hourly' => __('stündlich'), 'daily' => __('täglich')];
    }

    public static function missingModes(): array
    {
        return ['hide' => __('ausblenden (als Entwurf)'), 'delete' => __('löschen'), 'keep' => __('behalten')];
    }

    // ================================================================== Schema

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $long = $my ? 'LONGTEXT' : 'TEXT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->query("CREATE TABLE IF NOT EXISTS ext_sources (id $pk, name VARCHAR(191) NOT NULL, format VARCHAR(20) NOT NULL, url TEXT,
            auth_json TEXT, options_json TEXT, mapping_json $long, table_handle VARCHAR(64) NULL, active INT NOT NULL DEFAULT 1,
            last_run_at VARCHAR(25) NULL, last_ok_at VARCHAR(25) NULL, last_error TEXT NULL, last_count INT NOT NULL DEFAULT 0,
            last_hash VARCHAR(64) NULL, next_due INT NULL, fails INT NOT NULL DEFAULT 0, created_at VARCHAR(25), updated_at VARCHAR(25))$tail");
        $db->query("CREATE TABLE IF NOT EXISTS ext_source_items (id $pk, source_id INT NOT NULL, ext_id VARCHAR(191) NOT NULL,
            table_handle VARCHAR(64) NOT NULL, entry_id INT NOT NULL, hash VARCHAR(64) NULL, state VARCHAR(12) NOT NULL DEFAULT 'active',
            hidden_by_sync INT NOT NULL DEFAULT 0, first_seen VARCHAR(25), last_seen VARCHAR(25))$tail");
        $db->query("CREATE TABLE IF NOT EXISTS ext_source_log (id $pk, source_id INT NOT NULL, started_at VARCHAR(25), finished_at VARCHAR(25),
            ok INT NOT NULL DEFAULT 0, via VARCHAR(12) NULL, fetched INT NOT NULL DEFAULT 0, created INT NOT NULL DEFAULT 0,
            updated INT NOT NULL DEFAULT 0, unchanged INT NOT NULL DEFAULT 0, hidden INT NOT NULL DEFAULT 0, deleted INT NOT NULL DEFAULT 0,
            failed INT NOT NULL DEFAULT 0, bytes INT NOT NULL DEFAULT 0, ms INT NOT NULL DEFAULT 0, message TEXT NULL, details TEXT NULL)$tail");
        $db->query("CREATE TABLE IF NOT EXISTS ext_source_media (source_id INT NOT NULL, ref_hash VARCHAR(64) NOT NULL, media_id INT NOT NULL,
            PRIMARY KEY (source_id, ref_hash))$tail");
        if ($my) {
            try { $db->query('CREATE UNIQUE INDEX ext_items_key ON ext_source_items (source_id, ext_id)'); } catch (\Throwable) {}
            try { $db->query('CREATE INDEX ext_items_entry ON ext_source_items (table_handle, entry_id)'); } catch (\Throwable) {}
            try { $db->query('CREATE INDEX ext_log_source ON ext_source_log (source_id, id)'); } catch (\Throwable) {}
        } else {
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS ext_items_key ON ext_source_items (source_id, ext_id)');
            $db->query('CREATE INDEX IF NOT EXISTS ext_items_entry ON ext_source_items (table_handle, entry_id)');
            $db->query('CREATE INDEX IF NOT EXISTS ext_log_source ON ext_source_log (source_id, id)');
        }
    }

    // ================================================================== Freigabe & Rechte

    /** Funktion auf dieser Website eingeschaltet (ohne Integrator-Ausnahme) – gilt für Abruf, Cron und Verwaltung */
    public static function enabled(): bool
    {
        return Features::on('sources', false);
    }

    /** Schalter der Website (Netzwerk-Administration/Integratoren) – wirkt, solange config 'features' nichts vorgibt */
    public static function siteSwitch(): bool
    {
        try {
            return isset(app()->settings) && (bool) app()->settings->get('sys.sources_enabled', false);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Ist 'sources' in config 'features' ausdrücklich gesetzt? (dann ist der Schalter gesperrt) */
    public static function configured(): ?bool
    {
        $f = (array) app()->config->get('features', []);
        return array_key_exists('sources', $f) ? (bool) $f['sources'] : null;
    }

    /** Einrichten und abrufen: Funktion an + Recht sources.manage + Datentabellen */
    public static function canManage(): bool
    {
        return self::enabled() && Features::on('data', false) && can('sources.manage');
    }

    /** Menüpunkt sichtbar (Integratoren sehen ihn auch bei ausgeschalteter Funktion – zum Einschalten) */
    public static function navVisible(): bool
    {
        return isset(app()->auth) && app()->auth->user() && (self::canManage() || (Features::integrator() && Features::on('data', false)));
    }

    // ================================================================== Lesen

    public static function all(): array
    {
        try {
            return array_map([self::class, 'hydrate'], app()->db->fetchAll('SELECT * FROM ext_sources ORDER BY name'));
        } catch (\Throwable) {
            return [];
        }
    }

    public static function find(int $id): ?array
    {
        $r = app()->db->fetch('SELECT * FROM ext_sources WHERE id = ?', [$id]);
        return $r ? self::hydrate($r) : null;
    }

    public static function hydrate(array $r): array
    {
        $r['id'] = (int) $r['id'];
        $r['options'] = (json_decode((string) $r['options_json'], true) ?: []) + self::DEFAULTS;
        $r['mapping'] = (json_decode((string) $r['mapping_json'], true) ?: []) + ['id_path' => '', 'slug_path' => '', 'rows' => []];
        $r['auth'] = (json_decode((string) $r['auth_json'], true) ?: []) + ['type' => 'none', 'header' => '', 'user' => '', 'secret' => ''];
        $r['active'] = (bool) $r['active'];
        $r['table'] = $r['table_handle'] ? Tables::findContent((string) $r['table_handle']) : null;
        $r['items'] = (int) (app()->db->fetchValue("SELECT COUNT(*) FROM ext_source_items WHERE source_id = ? AND state = 'active'", [$r['id']]) ?? 0);
        return $r;
    }

    /** Kopfzeilen und Basic-Auth für den Abruf (Geheimnis wird erst hier entschlüsselt) */
    public static function authOptions(array $src): array
    {
        $a = $src['auth'];
        $secret = $a['secret'] !== '' ? Settings::decrypt((string) $a['secret']) : '';
        return match ($a['type']) {
            'bearer' => $secret !== '' ? ['headers' => ['Authorization: Bearer ' . $secret]] : [],
            'header' => $secret !== '' && preg_match('~^[A-Za-z0-9-]{1,60}$~', (string) $a['header']) ? ['headers' => [$a['header'] . ': ' . $secret]] : [],
            'basic' => $a['user'] !== '' ? ['basic' => [(string) $a['user'], $secret]] : [],
            default => [],
        };
    }

    public static function logs(int $sourceId, int $n = self::LOG_KEEP): array
    {
        return app()->db->fetchAll('SELECT * FROM ext_source_log WHERE source_id = ? ORDER BY id DESC LIMIT ' . max(1, $n), [$sourceId]);
    }

    /** Herkunft eines Eintrags (Badge „aus Quelle …“) – null bei selbst gepflegten Einträgen */
    public static function origin(array $table, int $entryId): ?array
    {
        try {
            $r = app()->db->fetch('SELECT i.*, s.name AS source_name, s.format AS source_format FROM ext_source_items i JOIN ext_sources s ON s.id = i.source_id
                WHERE i.table_handle = ? AND i.entry_id = ?', [$table['handle'], $entryId]);
        } catch (\Throwable) {
            return null;
        }
        return $r ?: null;
    }

    /** Herkunft mehrerer Einträge (Listenansicht): entry_id → Name der Quelle */
    public static function originMap(array $table, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids || Tables::isShared($table)) return [];
        try {
            $rows = app()->db->fetchAll('SELECT i.entry_id, s.name, s.id FROM ext_source_items i JOIN ext_sources s ON s.id = i.source_id
                WHERE i.table_handle = ? AND i.entry_id IN (' . implode(',', $ids) . ')', [$table['handle']]);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) $out[(int) $r['entry_id']] = ['name' => (string) $r['name'], 'id' => (int) $r['id']];
        return $out;
    }

    /** Stammt der Eintrag aus einer Quelle? (Schreibschutz in Entries::save) */
    public static function isExternal(array $table, int $entryId): bool
    {
        if (Tables::isShared($table)) return false;
        try {
            return (bool) app()->db->fetchValue('SELECT 1 FROM ext_source_items WHERE table_handle = ? AND entry_id = ?', [$table['handle'], $entryId]);
        } catch (\Throwable) {
            return false;
        }
    }

    // ================================================================== Speichern

    /**
     * Eingaben prüfen und bereinigen (auch für die Vorschau ungespeicherter Quellen).
     * @return array{0: array, 1: array} [Werte wie hydrate() (ohne Stand), errors]
     */
    public static function normalize(?array $src, array $in): array
    {
        $errors = [];
        $name = mb_substr(trim(strip_tags((string) ($in['name'] ?? ''))), 0, 120);
        if ($name === '') $errors['name'] = __('Bitte einen Namen angeben.');
        $format = array_key_exists((string) ($in['format'] ?? ''), self::FORMATS) ? (string) $in['format'] : 'rss';
        $url = trim((string) ($in['url'] ?? ''));
        if ($url !== '' && (!preg_match('~^https?://[^\s/$.?#][^\s]*$~i', $url) || !filter_var($url, FILTER_VALIDATE_URL))) {
            $errors['url'] = __('Bitte eine vollständige Adresse mit http:// oder https:// angeben.');
        } elseif ($url !== '' && (parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
            $errors['url'] = __('Zugangsdaten gehören nicht in die Adresse – bitte unter „Anmeldung“ eintragen.');
        }
        if ($url === '' && $format !== 'openimmo') $errors['url'] = __('Bitte die Adresse der Quelle angeben.');
        $o = (array) ($in['options'] ?? []);
        $old = $src['options'] ?? self::DEFAULTS;
        $options = [
            'items_path' => mb_substr(trim((string) ($o['items_path'] ?? '')), 0, 200),
            'xpath' => mb_substr(trim((string) ($o['xpath'] ?? '')), 0, 300),
            'schedule' => array_key_exists((string) ($o['schedule'] ?? ''), self::schedules()) ? (string) $o['schedule'] : 'daily',
            'ttl' => max(0, min(10080, (int) ($o['ttl'] ?? 60))),
            'timeout' => max(2, min(60, (int) ($o['timeout'] ?? 15))),
            'max_items' => max(1, min(Parser::MAX_ITEMS, (int) ($o['max_items'] ?? 200))),
            'max_mb' => max(1, min((int) app()->config->get('sources_max_mb', 20), (int) ($o['max_mb'] ?? 5))),
            'ua' => mb_substr(trim(preg_replace('~[\r\n]+~', ' ', (string) ($o['ua'] ?? ''))), 0, 200),
            'missing' => array_key_exists((string) ($o['missing'] ?? ''), self::missingModes()) ? (string) $o['missing'] : 'hide',
            'status' => ($o['status'] ?? 'published') === 'draft' ? 'draft' : 'published',
            'images' => !isset($o['images']) || !empty($o['images']),
            'example' => (bool) ($old['example'] ?? false),
        ];
        if ($format === 'xml' && $options['xpath'] !== '' && preg_match('~(document\s*\(|php:|unparsed-entity)~i', $options['xpath'])) $errors['options.xpath'] = __('Dieser XPath-Ausdruck ist nicht erlaubt.');
        $a = (array) ($in['auth'] ?? []);
        $type = in_array($a['type'] ?? 'none', self::AUTH, true) ? (string) ($a['type'] ?? 'none') : 'none';
        $auth = ['type' => $type, 'header' => mb_substr(trim((string) ($a['header'] ?? '')), 0, 60), 'user' => mb_substr(trim((string) ($a['user'] ?? '')), 0, 190),
            'secret' => (string) ($src['auth']['secret'] ?? '')];
        if (($a['secret'] ?? '') !== '') $auth['secret'] = Settings::encrypt((string) $a['secret']);
        if (!empty($a['clear_secret'])) $auth['secret'] = '';
        if ($type === 'header' && !preg_match('~^[A-Za-z0-9-]{1,60}$~', $auth['header'])) $errors['auth.header'] = __('Name der Kopfzeile: nur Buchstaben, Ziffern und Bindestrich (z. B. X-Api-Key).');
        if ($type === 'none') $auth = ['type' => 'none', 'header' => '', 'user' => '', 'secret' => ''];
        $table = null;
        $handle = trim((string) ($in['table_handle'] ?? ''));
        if ($handle !== '') {
            $table = Tables::findContent($handle);
            if (!$table || Tables::isShared($table)) { $errors['table_handle'] = __('Bitte eine eigene Inhaltstabelle wählen (keine geteilte Tabelle, keine Eingangs-Tabelle).'); $table = null; $handle = ''; }
        }
        // Zuordnung nur übernehmen, wenn sie zur gewählten Tabelle gehört (nach einem Tabellenwechsel: neu vorschlagen)
        $sameTable = ($in['mapping_table'] ?? $handle) === $handle;
        $mapping = $table && $sameTable ? Mapper::clean($table, (array) ($in['mapping'] ?? [])) : ['id_path' => '', 'slug_path' => '', 'rows' => []];
        $vals = ['id' => (int) ($src['id'] ?? 0), 'name' => $name, 'format' => $format, 'url' => $url, 'auth' => $auth, 'options' => $options, 'mapping' => $mapping,
            'table_handle' => $handle !== '' ? $handle : null, 'table' => $table, 'active' => !isset($in['active']) || !empty($in['active']),
            'last_run_at' => $src['last_run_at'] ?? null, 'fails' => (int) ($src['fails'] ?? 0)];
        return [$vals, $errors];
    }

    /**
     * Quelle anlegen/ändern. @return array{0: ?int, 1: array, 2: array} [id, errors, bereinigte Werte]
     */
    public static function save(?array $src, array $in): array
    {
        [$vals, $errors] = self::normalize($src, $in);
        if ($errors) return [null, $errors, $vals];
        ['name' => $name, 'format' => $format, 'url' => $url, 'auth' => $auth, 'options' => $options, 'mapping' => $mapping] = $vals;
        $row = ['name' => $name, 'format' => $format, 'url' => $url, 'auth_json' => json_encode($auth), 'options_json' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'mapping_json' => json_encode($mapping, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'table_handle' => $vals['table_handle'],
            'active' => $vals['active'] ? 1 : 0, 'updated_at' => now()];
        if ($src) {
            // Zeitplan geändert → nächsten Termin neu berechnen
            $row['next_due'] = self::nextDue($options['schedule'], $src['last_run_at'] ? (int) strtotime((string) $src['last_run_at']) : null);
            app()->db->update('ext_sources', $row, 'id = :id', ['id' => $src['id']]);
            $id = $src['id'];
        } else {
            $row['created_at'] = now();
            $row['next_due'] = self::nextDue($options['schedule'], null);
            $id = app()->db->insert('ext_sources', $row);
        }
        self::touchDue();
        return [$id, [], $vals];
    }

    /** Beispiel-Kennzeichnung (Demo-Inhalte) setzen */
    public static function markExample(int $id, bool $on): void
    {
        $s = self::find($id);
        if (!$s) return;
        $o = $s['options'];
        $o['example'] = $on;
        app()->db->update('ext_sources', ['options_json' => json_encode($o, JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $id]);
    }

    /** Quelle löschen; $entries: auch die übernommenen Einträge löschen (sonst werden sie normale, bearbeitbare Einträge) */
    public static function delete(array $src, bool $entries): int
    {
        $n = 0;
        if ($entries && $src['table']) {
            Sync::$writing = true;
            try {
                foreach (app()->db->fetchAll('SELECT entry_id FROM ext_source_items WHERE source_id = ?', [$src['id']]) as $r) {
                    \Core\Data\Entries::delete($src['table'], (int) $r['entry_id']);
                    $n++;
                }
            } finally {
                Sync::$writing = false;
            }
        }
        foreach (['ext_source_items', 'ext_source_log', 'ext_source_media'] as $t) app()->db->query("DELETE FROM $t WHERE source_id = ?", [$src['id']]);
        app()->db->query('DELETE FROM ext_sources WHERE id = ?', [$src['id']]);
        Sync::clearFiles($src['id']);
        \Core\PageCache::clear();
        return $n;
    }

    // ================================================================== Zeitplan

    public static function nextDue(string $schedule, ?int $lastRun): ?int
    {
        $step = match ($schedule) { 'hourly' => 3600, 'daily' => 86400, default => 0 };
        if ($step === 0) return null;
        return $lastRun ? $lastRun + $step : time() + 60;
    }

    /** Frühester Termin aller aktiven Quellen merken (billige Prüfung bei jedem Verwaltungsaufruf) */
    public static function touchDue(): void
    {
        try {
            $min = app()->db->fetchValue('SELECT MIN(next_due) FROM ext_sources WHERE active = 1 AND next_due IS NOT NULL');
            app()->settings->set('sys.sources_due', $min !== null ? (int) $min : 0);
        } catch (\Throwable) {
        }
    }

    /** Fällige Quellen */
    public static function due(): array
    {
        return array_map([self::class, 'hydrate'], app()->db->fetchAll('SELECT * FROM ext_sources WHERE active = 1 AND next_due IS NOT NULL AND next_due <= ? ORDER BY next_due', [time()]));
    }

    /**
     * Nebenbei abrufen (Verwaltungsaufrufe): fällige Quellen nach der Antwort abarbeiten – Cron ist zuverlässiger
     * (php bin/console sources:sync --all, z. B. alle 15 Minuten).
     */
    public static function maybeRun(): void
    {
        try {
            $due = (int) app()->settings->get('sys.sources_due', 0);
            if ($due <= 0 || $due > time() || !self::enabled()) return;
            register_shutdown_function(static function (): void {
                try {
                    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
                    ignore_user_abort(true);
                    @set_time_limit(300);
                    Sync::runDue('auto', 120.0);
                } catch (\Throwable $e) {
                    error_log('[sources] auto: ' . $e->getMessage());
                }
            });
        } catch (\Throwable $e) {
            error_log('[sources] maybeRun: ' . $e->getMessage());
        }
    }

    // ================================================================== Betrieb

    /**
     * Für `health`: je aktiver Quelle mit Fehler eine Warnzeile (null = „!“ – gemeldet, aber kein Deploy-Fehler, denn die
     * Quelle liegt außerhalb der eigenen Installation) – nur wenn die Funktion an ist
     */
    public static function health(): array
    {
        if (!self::enabled()) return [];
        $out = [];
        $bad = 0;
        foreach (self::all() as $s) {
            if (!$s['active']) continue;
            if ($s['last_error']) {
                $out[__('Externe Quelle „{name}“: {error}', ['name' => $s['name'], 'error' => mb_strimwidth((string) $s['last_error'], 0, 120, '…')])] = null;
                $bad++;
            } elseif (!$s['table']) {
                $out[__('Externe Quelle „{name}“: keine Zieltabelle', ['name' => $s['name']])] = null;
                $bad++;
            }
        }
        $n = count(self::all());
        if (!$bad) $out[__('Externe Quellen ({n}) ohne Fehler', ['n' => $n])] = true;
        return $out;
    }

    // ================================================================== Vorlagen

    /** Vorlagen für neue Quellen: Format, Optionen, Tabellen-Vorlage, Zuordnung */
    public static function presets(): array
    {
        return [
            'rss' => ['label' => __('RSS/Atom-Feed'), 'icon' => 'rss', 'format' => 'rss', 'name' => __('Meldungen (Feed)'),
                'help' => __('Nachrichten, Blogbeiträge, Pressemitteilungen – Titel, Datum, Text, Bild und Link.'),
                'options' => ['schedule' => 'hourly', 'ttl' => 30, 'max_items' => 50], 'table' => self::feedTable(), 'mapping' => self::feedMapping('rss')],
            'atom' => ['label' => __('Atom-Feed'), 'icon' => 'rss', 'format' => 'atom', 'name' => __('Meldungen (Atom)'), 'hidden' => true,
                'options' => ['schedule' => 'hourly', 'ttl' => 30, 'max_items' => 50], 'table' => self::feedTable(), 'mapping' => self::feedMapping('atom')],
            'openimmo' => ['label' => __('OpenImmo'), 'icon' => 'house-line', 'format' => 'openimmo', 'name' => __('Immobilien (OpenImmo)'),
                'help' => __('Immobilienangebote aus Makler-Software: ZIP hochladen oder Adresse zum Abholen – mit Bildern, Energieausweis und Kontakt.'),
                'options' => ['schedule' => 'manual', 'ttl' => 60, 'max_items' => 500, 'max_mb' => 20], 'table' => OpenImmo::tableDef(), 'mapping' => OpenImmo::mapping()],
            'json' => ['label' => __('JSON-API'), 'icon' => 'brackets-curly', 'format' => 'json', 'name' => __('Einträge aus einer API'),
                'help' => __('Beliebige JSON-Schnittstelle: Pfad zur Liste angeben, Felder in der Vorschau zuordnen.'),
                'options' => ['schedule' => 'daily', 'ttl' => 60, 'max_items' => 200], 'table' => null, 'mapping' => null],
            'xml' => ['label' => __('XML (XPath)'), 'icon' => 'file-code', 'format' => 'xml', 'name' => __('Einträge aus XML'),
                'help' => __('Beliebiges XML: Einträge per XPath wählen (z. B. //produkt), Felder in der Vorschau zuordnen.'),
                'options' => ['schedule' => 'daily', 'ttl' => 60, 'max_items' => 200], 'table' => null, 'mapping' => null],
        ];
    }

    /** Tabellen-Vorlage „Meldungen“ für Feeds */
    public static function feedTable(): array
    {
        return [
            'name' => 'Meldungen', 'singular' => 'Meldung', 'icon' => 'newspaper', 'handle' => 'meldungen', 'description' => 'Aus einem Feed (Externe Quellen)',
            'fields' => [
                ['label' => 'Titel', 'type' => 'text', 'required' => 1, 'in_list' => 1, 'searchable' => 1],
                ['label' => 'Datum', 'type' => 'datetime', 'in_list' => 1, 'width' => 'half'],
                ['label' => 'Autor', 'type' => 'text', 'width' => 'half'],
                ['label' => 'Teaser', 'type' => 'textarea', 'searchable' => 1],
                ['label' => 'Text', 'type' => 'richtext', 'searchable' => 1],
                ['label' => 'Bild', 'type' => 'media'],
                ['label' => 'Link', 'type' => 'url', 'help' => 'Originalbeitrag'],
                ['label' => 'Kategorie', 'type' => 'text', 'width' => 'half'],
            ],
            'settings' => ['route' => 'meldungen', 'title_field' => 'titel', 'image_field' => 'bild', 'description_field' => 'teaser',
                'sort_field' => 'datum', 'sort_dir' => 'desc', 'workflow' => 1],
        ];
    }

    public static function feedMapping(string $format): array
    {
        $r = fn(string $path, string $tx = '', array $x = []) => ['path' => $path, 'tx' => $tx] + $x;
        return $format === 'atom' ? [
            'id_path' => 'id | link[@rel=alternate]@href | link@href', 'slug_path' => '',
            'rows' => [
                'titel' => $r('title', 'text'), 'datum' => $r('published | updated', 'date'), 'autor' => $r('author.name'),
                'teaser' => $r('summary', 'text'), 'text' => $r('content | summary', 'html'),
                'bild' => $r('link[@rel=enclosure]@href | media:thumbnail@url | media:content@url', '', ['alt' => 'title']),
                'link' => $r('link[@rel=alternate]@href | link@href'), 'kategorie' => $r('category@term | category'),
            ],
        ] : [
            'id_path' => 'guid | link', 'slug_path' => '',
            'rows' => [
                'titel' => $r('title', 'text'), 'datum' => $r('pubDate | dc:date', 'date'), 'autor' => $r('dc:creator | author'),
                'teaser' => $r('description', 'text'), 'text' => $r('content:encoded | description', 'html'),
                'bild' => $r('enclosure[@type!=audio/mpeg]@url | media:content@url | media:thumbnail@url', '', ['alt' => 'title']),
                'link' => $r('link'), 'kategorie' => $r('category'),
            ],
        ];
    }
}
