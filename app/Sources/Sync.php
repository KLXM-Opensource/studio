<?php
declare(strict_types=1);

namespace Core\Sources;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Media;
use Core\PageCache;

/**
 * Abruf und Abgleich einer Quelle: laden (mit Zwischenspeicher), lesen, zuordnen, Einträge anlegen/aktualisieren
 * (nur bei geänderter Prüfsumme), fehlende ausblenden/löschen, Bilder in die Mediathek übernehmen, protokollieren.
 * Eine Sperrdatei je Quelle verhindert parallele Läufe (Cron + „Jetzt abrufen“).
 */
final class Sync
{
    /** Während des Abgleichs: Schreibschutz externer Einträge (Entries::save) aufheben */
    public static bool $writing = false;

    private const MAX_IMAGES_PER_RUN = 150;

    public static function dir(?int $id = null): string
    {
        $d = site()->storage() . '/sources' . ($id !== null ? '/' . $id : '');
        if (!is_dir($d)) @mkdir($d, 0770, true);
        return $d;
    }

    public static function clearFiles(int $id): void
    {
        $d = site()->storage() . '/sources/' . $id;
        if (!is_dir($d)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($d, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($d);
    }

    /** Zuletzt gefundene Pfade (Vorschau) für die Auswahlliste der Zuordnung */
    public static function knownPaths(int $id): array
    {
        $f = site()->storage() . '/sources/' . $id . '/paths.json';
        return $id > 0 && is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
    }

    /** Hochgeladene ZIP-/XML-Datei (OpenImmo) – bleibt für erneutes Einlesen liegen */
    public static function uploadFile(int $id): ?string
    {
        foreach (['upload.zip', 'upload.xml'] as $f) if (is_file(self::dir($id) . '/' . $f)) return self::dir($id) . '/' . $f;
        return null;
    }

    // ------------------------------------------------------------------ Laden

    /**
     * Rohdaten holen: Upload (OpenImmo) bzw. Adresse – mit Zwischenspeicher (Cache-Dauer der Quelle).
     * @return array{body: string, cached: bool, bytes: int, ms: int, files: array<string, string>, tmp: ?string, url: string}
     * @throws SourceException
     */
    public static function load(array $src, bool $force = false, ?string $upload = null): array
    {
        $o = $src['options'];
        $maxBytes = (int) $o['max_mb'] * 1024 * 1024;
        $upload ??= $src['url'] === '' ? self::uploadFile($src['id']) : null;
        if ($upload !== null) {
            return self::fromFile($src, $upload);
        }
        if ($src['url'] === '') throw new SourceException(__('Keine Adresse und keine hochgeladene Datei vorhanden.'));
        $cache = self::dir($src['id']) . '/cache.bin';
        $meta = is_file($cache . '.json') ? (json_decode((string) file_get_contents($cache . '.json'), true) ?: []) : [];
        $fresh = !$force && $meta && ($meta['url'] ?? '') === $src['url'] && time() - (int) ($meta['at'] ?? 0) < (int) $o['ttl'] * 60 && is_file($cache);
        if ($fresh) {
            $body = (string) file_get_contents($cache);
            $res = ['body' => $body, 'cached' => true, 'bytes' => strlen($body), 'ms' => 0, 'files' => [], 'tmp' => null, 'url' => $src['url']];
        } else {
            $kind = match ($src['format']) { 'json' => 'json', 'openimmo' => null, default => 'xml' };
            $r = Fetcher::get($src['url'], ['timeout' => (int) $o['timeout'], 'max' => $maxBytes, 'kind' => $kind, 'ua' => $o['ua'],
                'headers' => array_merge([self::accept($src['format'])], Sources::authOptions($src)['headers'] ?? [])] + array_intersect_key(Sources::authOptions($src), ['basic' => 1]));
            if ($src['format'] === 'openimmo' && !str_starts_with($r['body'], 'PK') && !preg_match('~^\s*<~', $r['body'])) {
                throw new SourceException(__('Unerwarteter Inhalt – erwartet wird OpenImmo-XML oder eine ZIP-Datei.'));
            }
            @file_put_contents($cache, $r['body'], LOCK_EX);
            @file_put_contents($cache . '.json', json_encode(['url' => $src['url'], 'at' => time(), 'type' => $r['type'], 'bytes' => $r['bytes']]), LOCK_EX);
            $res = ['body' => $r['body'], 'cached' => false, 'bytes' => $r['bytes'], 'ms' => $r['ms'], 'files' => [], 'tmp' => null, 'url' => $r['url']];
        }
        // OpenImmo als ZIP über die Adresse: entpacken wie beim Upload
        if ($src['format'] === 'openimmo' && str_starts_with($res['body'], 'PK')) {
            $zip = self::dir($src['id']) . '/pull.zip';
            file_put_contents($zip, $res['body']);
            $x = self::fromFile($src, $zip);
            return ['cached' => $res['cached'], 'ms' => $res['ms'], 'url' => $res['url']] + $x;
        }
        return $res;
    }

    private static function accept(string $format): string
    {
        return 'Accept: ' . match ($format) {
            'json' => 'application/json, application/*+json;q=0.9, */*;q=0.5',
            'atom' => 'application/atom+xml, application/xml;q=0.9, */*;q=0.5',
            'rss' => 'application/rss+xml, application/rdf+xml, application/xml;q=0.9, text/xml;q=0.9, */*;q=0.5',
            'openimmo' => 'application/zip, application/xml;q=0.9, */*;q=0.5',
            default => 'application/xml, text/xml;q=0.9, */*;q=0.5',
        };
    }

    /** Lokale Datei (Upload): ZIP entpacken (nur XML + Bilder) oder XML direkt */
    private static function fromFile(array $src, string $file): array
    {
        $head = (string) file_get_contents($file, false, null, 0, 4);
        if (str_starts_with($head, 'PK')) {
            $tmp = self::dir($src['id']) . '/unzip-' . bin2hex(random_bytes(4));
            try {
                $z = OpenImmo::unzip($file, $tmp);
            } catch (\Throwable $e) {
                self::rmTmp($tmp);
                throw $e;
            }
            $body = (string) file_get_contents($z['xml']);
            return ['body' => $body, 'cached' => false, 'bytes' => (int) filesize($file), 'ms' => 0, 'files' => $z['files'], 'tmp' => $tmp, 'url' => ''];
        }
        $body = (string) file_get_contents($file, false, null, 0, (int) $src['options']['max_mb'] * 1024 * 1024 + 1);
        if (strlen($body) > (int) $src['options']['max_mb'] * 1024 * 1024) throw new SourceException(__('Datei zu groß (mehr als {mb} MB).', ['mb' => $src['options']['max_mb']]));
        return ['body' => $body, 'cached' => false, 'bytes' => strlen($body), 'ms' => 0, 'files' => [], 'tmp' => null, 'url' => ''];
    }

    private static function rmTmp(?string $dir): void
    {
        if (!$dir || !is_dir($dir) || !str_starts_with(realpath($dir) ?: '', realpath(site()->storage() . '/sources') ?: "\0")) return;
        foreach (glob($dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($dir);
    }

    // ------------------------------------------------------------------ Vorschau

    /**
     * Vorschau ohne Schreiben: Rohdaten laden, lesen, erste $n zuordnen.
     * @return array{ok: bool, error: ?string, total: int, items: list<array>, paths: array, mapped: list<array>, meta: array, cached: bool, bytes: int, ms: int, suggested: ?array}
     */
    public static function preview(array $src, ?array $table, int $n = 5, bool $force = false): array
    {
        $out = ['ok' => false, 'error' => null, 'total' => 0, 'items' => [], 'paths' => [], 'mapped' => [], 'meta' => [], 'cached' => false, 'bytes' => 0, 'ms' => 0, 'suggested' => null];
        $raw = null;
        try {
            $raw = self::load($src, $force);
            $parsed = Parser::parse($raw['body'], $src['format'], ['items_path' => $src['options']['items_path'], 'xpath' => $src['options']['xpath'], 'max' => $src['options']['max_items']]);
            $out['total'] = (int) ($parsed['meta']['total'] ?? count($parsed['items']));
            $out['meta'] = $parsed['meta'];
            $out['items'] = array_slice($parsed['items'], 0, $n);
            $out['paths'] = Parser::paths($parsed['items']);
            // Gefundene Pfade merken (Auswahlliste der Zuordnung auch ohne neue Vorschau)
            if ($src['id'] > 0) @file_put_contents(self::dir($src['id']) . '/paths.json', json_encode($out['paths'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $out['cached'] = $raw['cached'];
            $out['bytes'] = $raw['bytes'];
            $out['ms'] = $raw['ms'];
            if ($table) {
                $mapping = $src['mapping'];
                if (!$mapping['rows'] && !$mapping['id_path']) {
                    $mapping = Mapper::suggest($table, $out['paths'], $src['format']);
                    $out['suggested'] = $mapping;
                }
                $out['mapped'] = Mapper::preview($table, $mapping, $parsed['items'], $n);
            }
            $out['ok'] = true;
        } catch (SourceException $e) {
            $out['error'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log('[sources] preview: ' . $e->getMessage());
            $out['error'] = __('Unerwarteter Fehler: {error}', ['error' => $e->getMessage()]);
        } finally {
            self::rmTmp($raw['tmp'] ?? null);
        }
        return $out;
    }

    // ------------------------------------------------------------------ Abgleich

    /**
     * Quelle abgleichen. $via: manual | cron | auto | upload. $o: force (Zwischenspeicher umgehen), upload (Pfad einer Datei)
     * @return array Statistik (created, updated, unchanged, hidden, deleted, failed, fetched, ok, message, details)
     */
    public static function run(array $src, string $via = 'manual', array $o = []): array
    {
        $stats = ['ok' => false, 'fetched' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'hidden' => 0, 'deleted' => 0, 'failed' => 0,
            'bytes' => 0, 'ms' => 0, 'message' => '', 'details' => []];
        $started = now();
        $t0 = microtime(true);
        $lock = @fopen(self::dir($src['id']) . '/.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $stats['message'] = __('Diese Quelle wird gerade schon abgerufen.');
            return $stats;
        }
        $raw = null;
        try {
            if (!Sources::enabled()) throw new SourceException(__('Die Funktion „Externe Quellen“ ist auf dieser Website ausgeschaltet.'));
            $table = $src['table'];
            if (!$table) throw new SourceException(__('Keine Zieltabelle gewählt.'));
            if (Tables::isShared($table) || Tables::isInbox($table)) throw new SourceException(__('Die Zieltabelle ist keine eigene Inhaltstabelle.'));
            if ($missing = Mapper::missingRequired($table, $src['mapping'])) {
                throw new SourceException(__('Pflichtfelder ohne Zuordnung: {list}', ['list' => implode(', ', $missing)]));
            }
            $raw = self::load($src, !empty($o['force']), $o['upload'] ?? null);
            $stats['bytes'] = $raw['bytes'];
            $parsed = Parser::parse($raw['body'], $src['format'], ['items_path' => $src['options']['items_path'], 'xpath' => $src['options']['xpath'], 'max' => $src['options']['max_items']]);
            $stats['fetched'] = count($parsed['items']);
            @file_put_contents(self::dir($src['id']) . '/paths.json', json_encode(Parser::paths($parsed['items']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            self::$writing = true;
            self::apply($src, $table, $parsed, $raw['files'], $stats);
            $stats['ok'] = true;
            $stats['message'] = __('{n} Einträge gelesen: {c} neu, {u} geändert, {s} unverändert, {h} ausgeblendet, {d} gelöscht, {f} Fehler.', [
                'n' => $stats['fetched'], 'c' => $stats['created'], 'u' => $stats['updated'], 's' => $stats['unchanged'],
                'h' => $stats['hidden'], 'd' => $stats['deleted'], 'f' => $stats['failed']]);
            if (!empty($parsed['meta']['partial'])) $stats['message'] .= ' ' . __('(Teilabgleich – fehlende Objekte bleiben unverändert)');
        } catch (SourceException $e) {
            $stats['message'] = $e->getMessage();
        } catch (\Throwable $e) {
            error_log('[sources] sync ' . $src['id'] . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $stats['message'] = __('Unerwarteter Fehler: {error}', ['error' => $e->getMessage()]);
        } finally {
            self::$writing = false;
            self::rmTmp($raw['tmp'] ?? null);
            $stats['ms'] = (int) round((microtime(true) - $t0) * 1000);
            self::log($src, $via, $started, $stats);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $stats;
    }

    /** Einträge abgleichen */
    private static function apply(array $src, array $table, array $parsed, array $files, array &$stats): void
    {
        $db = app()->db;
        $existing = [];
        foreach ($db->fetchAll('SELECT * FROM ext_source_items WHERE source_id = ?', [$src['id']]) as $r) $existing[$r['ext_id']] = $r;
        $seen = [];
        $imgBudget = self::MAX_IMAGES_PER_RUN;
        $deleteIds = [];
        foreach ($parsed['items'] as $item) {
            $m = Mapper::map($table, $src['mapping'], $item);
            $ext = $m['ext_id'];
            if (isset($seen[$ext])) continue;            // doppelte IDs in der Quelle: erster gewinnt
            if (!empty($item['_loeschen'])) { $deleteIds[$ext] = true; continue; }   // OpenImmo: aktionart="DELETE"
            $seen[$ext] = true;
            $hash = Mapper::hash($m);
            $row = $existing[$ext] ?? null;
            $entry = $row ? Entries::find($table, (int) $row['entry_id']) : null;
            try {
                if ($row && $entry && $row['hash'] === $hash) {
                    // unverändert – ggf. nach „fehlt“ wieder einblenden
                    if ($row['state'] !== 'active') self::reactivate($table, $row, $entry, $stats);
                    $db->update('ext_source_items', ['last_seen' => now(), 'state' => 'active'], 'id = :id', ['id' => (int) $row['id']]);
                    $stats['unchanged']++;
                    continue;
                }
                $in = $m['values'];
                $imgFailed = false;
                foreach ($m['media'] as $field => $ref) {
                    if (!$src['options']['images']) break;
                    $mid = self::image($src, $ref['ref'], $ref['alt'] !== '' ? $ref['alt'] : Mapper::title($table, $m), $files, $imgBudget, $stats);
                    if ($mid) $in[$field] = $mid; else $imgFailed = true;
                }
                // Bild nicht geladen: beim nächsten Abgleich erneut versuchen (Prüfsumme nicht als „fertig“ merken)
                if ($imgFailed) $hash = substr($hash, 0, 63) . '!';
                // Teilaktualisierung: nicht zugeordnete Felder bleiben – zugeordnete, aber leere Felder werden geleert
                // (Bilder, deren Laden fehlschlug, behalten ihren bisherigen Wert)
                foreach ((array) $src['mapping']['rows'] as $fname => $_) {
                    $fd = Tables::field($table, $fname);
                    if (!$fd || array_key_exists($fname, $in) || in_array($fd['type'], Mapper::UNSUPPORTED, true)) continue;
                    if (in_array($fd['type'], ['media', 'file'], true) && isset($m['media'][$fname])) continue;
                    $in[$fname] = null;
                }
                if ($m['slug'] !== '') $in['slug'] = $m['slug'];
                if ($entry) {
                    [$id, $errors] = Entries::save($table, (int) $entry['id'], $in);
                    if ($errors) throw new SourceException(implode(' ', array_map('strval', $errors)));
                    if ($row['state'] !== 'active') self::reactivate($table, $row, $entry, $stats);
                    $db->update('ext_source_items', ['hash' => $hash, 'last_seen' => now(), 'state' => 'active', 'hidden_by_sync' => 0], 'id = :id', ['id' => (int) $row['id']]);
                    $stats['updated']++;
                } else {
                    $in['status'] = $src['options']['status'];
                    [$id, $errors] = Entries::save($table, null, $in);
                    if ($errors) throw new SourceException(implode(' ', array_map('strval', $errors)));
                    if ($row) {
                        $db->update('ext_source_items', ['entry_id' => (int) $id, 'hash' => $hash, 'last_seen' => now(), 'state' => 'active', 'hidden_by_sync' => 0, 'table_handle' => $table['handle']], 'id = :id', ['id' => (int) $row['id']]);
                    } else {
                        $db->insert('ext_source_items', ['source_id' => $src['id'], 'ext_id' => $ext, 'table_handle' => $table['handle'], 'entry_id' => (int) $id,
                            'hash' => $hash, 'state' => 'active', 'hidden_by_sync' => 0, 'first_seen' => now(), 'last_seen' => now()]);
                    }
                    $stats['created']++;
                }
                foreach (array_slice($m['warnings'], 0, 2) as $w) self::detail($stats, Mapper::title($table, $m) . ': ' . $w);
            } catch (\Throwable $e) {
                $stats['failed']++;
                self::detail($stats, Mapper::title($table, $m) . ': ' . $e->getMessage());
            }
        }
        // Fehlende Einträge: nicht bei leerer Antwort (meist ein Fehler der Quelle) und nicht beim OpenImmo-Teilabgleich
        $mode = (string) $src['options']['missing'];
        $partial = !empty($parsed['meta']['partial']);
        foreach ($existing as $ext => $row) {
            if (isset($seen[$ext])) continue;
            $explicit = isset($deleteIds[$ext]);
            if (!$explicit && ($partial || !$parsed['items'] || $mode === 'keep' || $row['state'] !== 'active')) continue;
            $entry = Entries::find($table, (int) $row['entry_id']);
            if ($mode === 'delete' || ($explicit && $mode !== 'keep' && $mode !== 'hide')) {
                if ($entry) Entries::delete($table, (int) $row['entry_id']);
                $db->query('DELETE FROM ext_source_items WHERE id = ?', [(int) $row['id']]);
                $stats['deleted']++;
                continue;
            }
            $hidden = 0;
            if ($entry && $entry['status'] === 'published') {
                Entries::setStatus($table, [(int) $entry['id']], 'draft');
                $hidden = 1;
                $stats['hidden']++;
            }
            $db->update('ext_source_items', ['state' => 'missing', 'hidden_by_sync' => $hidden], 'id = :id', ['id' => (int) $row['id']]);
        }
        if (!$parsed['items'] && $existing) self::detail($stats, __('Die Quelle lieferte keine Einträge – vorhandene Einträge bleiben unverändert.'));
    }

    /** Wieder aufgetaucht: nur einblenden, was der Abgleich selbst ausgeblendet hat (von Hand Ausgeblendetes bleibt aus) */
    private static function reactivate(array $table, array $row, array $entry, array &$stats): void
    {
        if ((int) $row['hidden_by_sync'] === 1 && $entry['status'] === 'draft') {
            Entries::setStatus($table, [(int) $entry['id']], 'published');
        }
        app()->db->update('ext_source_items', ['state' => 'active', 'hidden_by_sync' => 0], 'id = :id', ['id' => (int) $row['id']]);
    }

    private static function detail(array &$stats, string $msg): void
    {
        if (count($stats['details']) < 30) $stats['details'][] = mb_strimwidth($msg, 0, 300, '…');
    }

    /**
     * Bild/Datei übernehmen: Dateiname aus dem ZIP oder Adresse (gleicher SSRF-Schutz) → Media::import (neu kodiert, ohne EXIF).
     * Bereits geladene Verweise werden wiederverwendet.
     */
    private static function image(array $src, string $ref, string $alt, array $files, int &$budget, array &$stats): ?int
    {
        $db = app()->db;
        $key = hash('sha256', $ref);
        $known = $db->fetchValue('SELECT media_id FROM ext_source_media WHERE source_id = ? AND ref_hash = ?', [$src['id'], $key]);
        if ($known && Media::find((int) $known)) return (int) $known;
        if ($budget-- <= 0) return null;
        $tmp = null;
        $name = basename((string) (parse_url($ref, PHP_URL_PATH) ?: $ref));
        try {
            if (preg_match('~^https?://~i', $ref)) {
                $r = Fetcher::get($ref, ['timeout' => (int) $src['options']['timeout'], 'max' => min(Media::maxBytes(), 15 * 1024 * 1024), 'kind' => 'image', 'ua' => $src['options']['ua']]);
                $tmp = tempnam(sys_get_temp_dir(), 'src');
                file_put_contents($tmp, $r['body']);
                $path = $tmp;
            } else {
                $path = $files[mb_strtolower(basename($ref))] ?? null;
                if ($path === null) throw new SourceException(__('Datei „{name}“ nicht gefunden.', ['name' => $name]));
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) throw new SourceException(__('„{name}“ ist kein Bild (JPG, PNG, WebP, GIF).', ['name' => $name]));
            [$m, $err] = Media::import($path, $name ?: 'bild.jpg', mb_strlen($alt) >= 3 ? $alt : '', [
                'require_alt' => false, 'tags' => ['quelle', 'quelle-' . $src['id']], 'credit' => mb_substr($src['name'], 0, 120),
                'collection' => self::collection($src),
            ]);
            if ($err || !$m) throw new SourceException((string) $err);
            $db->query('DELETE FROM ext_source_media WHERE source_id = ? AND ref_hash = ?', [$src['id'], $key]);
            $db->insert('ext_source_media', ['source_id' => $src['id'], 'ref_hash' => $key, 'media_id' => (int) $m['id']]);
            return (int) $m['id'];
        } catch (\Throwable $e) {
            self::detail($stats, __('Bild „{name}“: {error}', ['name' => mb_strimwidth($name, 0, 60, '…'), 'error' => $e->getMessage()]));
            return null;
        } finally {
            if ($tmp) @unlink($tmp);
        }
    }

    /** Medien-Sammlung „Quelle: {Name}“ (einmal je Quelle) */
    private static function collection(array $src): ?int
    {
        static $cache = [];
        if (isset($cache[$src['id']])) return $cache[$src['id']];
        try {
            $name = 'Quelle: ' . $src['name'];
            $id = app()->db->fetchValue('SELECT id FROM media_collections WHERE name = ?', [$name]);
            $id = $id ? (int) $id : Media::createCollection($name, __('Bilder der externen Quelle „{name}“ (automatisch)', ['name' => $src['name']]));
            return $cache[$src['id']] = $id;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function log(array $src, string $via, string $started, array $stats): void
    {
        $db = app()->db;
        try {
            $db->insert('ext_source_log', ['source_id' => $src['id'], 'started_at' => $started, 'finished_at' => now(), 'ok' => $stats['ok'] ? 1 : 0,
                'via' => $via, 'fetched' => $stats['fetched'], 'created' => $stats['created'], 'updated' => $stats['updated'], 'unchanged' => $stats['unchanged'],
                'hidden' => $stats['hidden'], 'deleted' => $stats['deleted'], 'failed' => $stats['failed'], 'bytes' => $stats['bytes'], 'ms' => $stats['ms'],
                'message' => mb_substr($stats['message'], 0, 1000), 'details' => $stats['details'] ? json_encode($stats['details'], JSON_UNESCAPED_UNICODE) : null]);
            $keep = $db->fetchValue('SELECT id FROM ext_source_log WHERE source_id = ? ORDER BY id DESC LIMIT 1 OFFSET ' . (Sources::LOG_KEEP - 1), [$src['id']]);
            if ($keep) $db->query('DELETE FROM ext_source_log WHERE source_id = ? AND id < ?', [$src['id'], (int) $keep]);
            $upd = ['last_run_at' => now(), 'last_error' => $stats['ok'] ? null : mb_substr($stats['message'], 0, 1000),
                'next_due' => Sources::nextDue((string) $src['options']['schedule'], time()), 'fails' => $stats['ok'] ? 0 : (int) ($src['fails'] ?? 0) + 1];
            if ($stats['ok']) {
                $upd['last_ok_at'] = now();
                $upd['last_count'] = (int) $db->fetchValue("SELECT COUNT(*) FROM ext_source_items WHERE source_id = ? AND state = 'active'", [$src['id']]);
            } elseif ($upd['next_due'] !== null && $upd['fails'] > 1) {
                // wiederholte Fehler: seltener versuchen (höchstens einmal täglich)
                $upd['next_due'] = time() + min(86400, 900 * 2 ** min(6, $upd['fails']));
            }
            $db->update('ext_sources', $upd, 'id = :id', ['id' => $src['id']]);
            Sources::touchDue();
            if ($stats['created'] || $stats['updated'] || $stats['hidden'] || $stats['deleted']) PageCache::clear();
        } catch (\Throwable $e) {
            error_log('[sources] log: ' . $e->getMessage());
        }
    }

    /** Fällige Quellen abarbeiten (Cron, nebenbei) – $budget: Sekunden */
    public static function runDue(string $via, float $budget = 0.0, ?\Closure $out = null): array
    {
        if (!Sources::enabled()) return [];
        $t0 = microtime(true);
        $done = [];
        foreach (Sources::due() as $src) {
            if ($budget > 0 && microtime(true) - $t0 > $budget) break;
            $s = self::run($src, $via);
            $done[$src['id']] = $s;
            if ($out) $out($src, $s);
        }
        Sources::touchDue();
        return $done;
    }
}
