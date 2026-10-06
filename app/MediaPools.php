<?php
declare(strict_types=1);

namespace Core;

/**
 * Geteilte Medien (Pools): zentrale Mediatheken einer Installation, die mehrere Websites nutzen.
 *
 *   storage/pools/{key}/pool.json     Name des Pools
 *   storage/pools/{key}/pool.sqlite   Medien, Sammlungen, Tags, Zuschnitte (gleiches Schema wie eine Website)
 *   public/pools/{key}/               Dateien (Adresse /pools/{key}/…)
 *
 * Welche Websites einen Pool nutzen, steht in deren Konfiguration: 'media_pools' => ['marke'].
 * Pflegen (hochladen, Alt-Texte, Zuschnitte, Sammlungen) dürfen nur Rollen mit „media.shared“;
 * alle anderen sehen den Pool und verwenden die Dateien. Eine verwendete Pool-Datei bekommt auf der
 * Website einen schlanken Verweis-Eintrag (media.pool_ref) mit eigener ID – Blöcke, Felder und Themes
 * funktionieren unverändert, und Änderungen im Pool wirken sofort überall.
 *
 * Geschützter Pool (pool.json 'protected' => true, z. B. für einen Mitgliederbereich): Dateien samt aller Größen, Zuschnitte,
 * Bearbeitungen und Video-Vorschaubilder liegen außerhalb von public/ in storage/pools/{key}/files und haben die Adresse
 * /geschuetzt/{key}/… – ausgeliefert nur von Core\Http\Controllers\ProtectedMediaController nach Prüfung: angemeldete Redaktion
 * oder eine Erweiterung erlaubt es (Extension::mediaAccess, z. B. angemeldete Mitglieder). Keine Unschärfe-Kopien (ImageFit),
 * nicht im Suchindex, PDF-Ansicht nur mit Zugriff. Ob ein Pool geschützt ist, wird beim Anlegen festgelegt.
 */
final class MediaPools
{
    private static array $dbs = [];
    private static array $rows = [];

    public static function dir(?string $key = null): string
    {
        return ROOT . '/storage/pools' . ($key !== null ? '/' . $key : '');
    }

    public const PROTECTED_PATH = '/geschuetzt';

    public static function mediaDir(string $key): string
    {
        return self::isProtected($key) ? self::dir($key) . '/files' : ROOT . '/public/pools/' . $key;
    }

    public static function url(string $key, string $rel): string
    {
        return base_path() . (self::isProtected($key) ? self::PROTECTED_PATH : '/pools') . '/' . $key . '/' . ltrim($rel, '/');
    }

    /** Geschützter Pool? (pool.json 'protected') – je Anfrage gemerkt */
    public static function isProtected(?string $key): bool
    {
        static $memo = [];
        if ($key === null || $key === '') return false;
        return $memo[$key] ??= !empty(self::meta($key)['protected']);
    }

    /** Gehört eine Mediendatei (Website-Verweis mit pool_ref oder Pool-Zeile mit _pool) zu einem geschützten Pool? */
    public static function mediaProtected(?array $m): bool
    {
        if (!$m) return false;
        $key = (string) ($m['_pool'] ?? '');
        if ($key === '' && !empty($m['pool_ref'])) $key = (string) strtok((string) $m['pool_ref'], ':');
        return self::isProtected($key);
    }

    /** Alle Pools der Installation [key => Bezeichnung] */
    public static function all(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*/pool.json') ?: [] as $f) {
            $key = basename(dirname($f));
            $meta = json_decode((string) file_get_contents($f), true) ?: [];
            $out[$key] = (string) ($meta['label'] ?? $key);
        }
        return $out;
    }

    /**
     * Angaben eines Pools aus pool.json: label, sites (Websites, die ihn nutzen), editors (Websites, die ihn pflegen dürfen), created,
     * shared_table (nur automatisch angelegte Pools geteilter Datentabellen: deren Kurzname)
     */
    public static function meta(string $key): array
    {
        $f = self::dir($key) . '/pool.json';
        $m = is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
        return $m + ['label' => $key, 'sites' => [], 'editors' => []];
    }

    private static function saveMeta(string $key, array $meta): void
    {
        file_put_contents(self::dir($key) . '/pool.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /** Pools, die diese Website nutzen darf [key => Bezeichnung] – aus der Konfiguration ('media_pools') oder der Zuordnung in den Grundeinstellungen */
    public static function forSite(): array
    {
        $cfg = array_flip((array) app()->config->get('media_pools', []));
        $out = [];
        foreach (self::all() as $key => $label) {
            if (isset($cfg[$key]) || in_array(site()->key, (array) self::meta($key)['sites'], true)) $out[$key] = $label;
        }
        return $out;
    }

    /** Websites, die den Pool nutzen (pool.json 'sites' oder Konfiguration 'media_pools' der Website) */
    public static function sitesUsing(string $key): array
    {
        $out = array_map('strval', (array) self::meta($key)['sites']);
        foreach (array_keys(Sites::all()) as $k) {
            try {
                if (in_array($key, (array) Network\Network::config((string) $k)->get('media_pools', []), true)) $out[] = (string) $k;
            } catch (\Throwable) {
                // Website-Konfiguration nicht lesbar
            }
        }
        return array_values(array_unique($out));
    }

    /** Signatur der Abfrage „Verwendungen von Pool-Dateien“ zwischen Websites einer Installation (Netzwerk-Schlüssel) */
    public static function usageSig(string $key, string $ids, int $exp): string
    {
        return hash_hmac('sha256', 'media-usages|' . $key . '|' . $ids . '|' . $exp, Network\Network::key());
    }

    /**
     * Verwendungen von Pool-Dateien auf den ANDEREN Websites, die den Pool nutzen – jede Website prüft im eigenen Kontext
     * (Blöcke ihres Kits, Datensätze, Einstellungen) über GET /admin/network/media-usages (signiert, 60 s gültig).
     * @param int[] $poolIds
     * @return array<string, array{label: string, items: ?array<int, array<int, array{label: string, url: ?string}>>}>
     *         je Website: items = [poolId => Fundstellen] oder null (nicht erreichbar → Verwendung unbekannt)
     */
    public static function usagesElsewhere(string $key, array $poolIds): array
    {
        $ids = implode(',', array_values(array_unique(array_filter(array_map('intval', $poolIds)))));
        if ($ids === '' || strlen(Network\Network::key()) < 32) return [];
        $out = [];
        foreach (self::sitesUsing($key) as $site) {
            if ($site === site()->key || !isset(Sites::all()[$site])) continue;
            $exp = time() + 60;
            $url = Network\Network::siteLink($site, '/admin/network/media-usages') . '?' . http_build_query(['pool' => $key, 'ids' => $ids, 'exp' => $exp, 'sig' => self::usageSig($key, $ids, $exp)]);
            $label = (new Site($site, Sites::all()[$site]))->label();
            $res = self::getJson($url);
            $items = null;
            if (is_array($res) && ($res['ok'] ?? false) && is_array($res['usages'] ?? null)) {
                $items = [];
                foreach ($res['usages'] as $pid => $list) {
                    $items[(int) $pid] = array_map(fn($u) => ['label' => (string) ($u['label'] ?? ''),
                        'url' => !empty($u['url']) ? (preg_match('~^https?://~', (string) $u['url']) ? (string) $u['url'] : Network\Network::siteUrl($site) . $u['url']) : null], (array) $list);
                }
            }
            $out[$site] = ['label' => $label, 'items' => $items];
        }
        return $out;
    }

    private static function getJson(string $url): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_USERAGENT => 'KLXM-Studio-Network']);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return $code === 200 && is_string($body) ? (json_decode($body, true) ?: null) : null;
        }
        $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8, 'header' => "Accept: application/json\r\n", 'ignore_errors' => true]]));
        return is_string($body) ? (json_decode($body, true) ?: null) : null;
    }

    /** Darf die angemeldete Person Pools verwalten? (Hauptwebsite mit Grundeinstellungen-Recht oder Integrator) */
    public static function canManage(): bool
    {
        return Features::integrator() || (can('system.manage') && \Core\Network\Network::isNetworkSite());
    }

    /**
     * Darf die angemeldete Person in diesem Pool ändern (hochladen, löschen, Alt-Texte, Sammlungen)?
     * Recht „Geteilte Medien pflegen“ UND die Website ist zum Pflegen freigegeben (Hauptwebsite immer, andere laut pool.json 'editors').
     * Integratoren dürfen immer. So kann der Admin einer Kunden-Website (Rolle mit allen Rechten) geteilte Dateien nur nutzen.
     */
    public static function canEdit(?string $key): bool
    {
        if ($key === null || $key === '' || !self::available($key)) return false;
        if (Features::integrator()) return true;
        if (!can('media.shared')) return false;
        return \Core\Network\Network::isNetworkSite()
            || in_array(site()->key, (array) self::meta($key)['editors'], true);
    }

    /** Bezeichnung, nutzende und pflegende Websites ändern */
    public static function update(string $key, string $label, array $sites, ?array $editors = null): void
    {
        if (!is_file(self::dir($key) . '/pool.json')) throw new \InvalidArgumentException('Unbekannter Pool.');
        $meta = self::meta($key);
        $meta['label'] = mb_substr(trim(strip_tags($label)), 0, 80) ?: $meta['label'];
        $meta['sites'] = array_values(array_intersect(array_map('strval', $sites), array_keys(Sites::all())));
        if ($editors !== null) $meta['editors'] = array_values(array_intersect(array_map('strval', $editors), array_keys(Sites::all())));
        self::saveMeta($key, $meta);
    }

    /** Leeren Pool entfernen (mit Dateien nur über die Kommandozeile, damit nichts versehentlich verschwindet) */
    public static function delete(string $key): void
    {
        if (!is_file(self::dir($key) . '/pool.json')) throw new \InvalidArgumentException('Unbekannter Pool.');
        if ((int) self::db($key)->fetchValue('SELECT COUNT(*) FROM media') > 0) {
            throw new \InvalidArgumentException('Der Pool enthält noch Dateien. Bitte zuerst in der Mediathek löschen.');
        }
        unset(self::$dbs[$key]);
        foreach (glob(self::dir($key) . '/*') ?: [] as $f) @unlink($f);
        @rmdir(self::dir($key));
        $rm = function (string $dir) use (&$rm): void {
            foreach (glob($dir . '/{,.}[!.,!..]*', GLOB_BRACE) ?: [] as $f) is_dir($f) ? $rm($f) : @unlink($f);
            @rmdir($dir);
        };
        if (is_dir(self::mediaDir($key))) $rm(self::mediaDir($key));
    }

    /**
     * Leeren Pool beiseitelegen (Aufräumen automatisch angelegter Pools, Core\Data\Shared::cleanupPools): pool.json und Datenbank
     * wandern nach storage/pools/_removed/{key}-{Zeit}/, der (leere) Dateiordner public/pools/{key} wird entfernt.
     */
    public static function retire(string $key): void
    {
        if (!is_file(self::dir($key) . '/pool.json')) throw new \InvalidArgumentException('Unbekannter Pool.');
        if (self::count($key) > 0) throw new \InvalidArgumentException('Der Pool enthält noch Dateien.');
        unset(self::$dbs[$key]);
        @mkdir(self::dir('_removed'), 0770, true);
        if (!@rename(self::dir($key), self::dir('_removed') . '/' . $key . '-' . date('YmdHis'))) throw new \RuntimeException("Pool „{$key}“ konnte nicht verschoben werden.");
        $rm = function (string $dir) use (&$rm): void {
            foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) is_dir("$dir/$f") ? $rm("$dir/$f") : @unlink("$dir/$f");
            @rmdir($dir);
        };
        if (is_dir(self::mediaDir($key))) $rm(self::mediaDir($key));
        self::forget();
    }

    public static function count(string $key): int
    {
        return (int) self::db($key)->fetchValue('SELECT COUNT(*) FROM media');
    }

    public static function available(string $key): bool
    {
        return isset(self::forSite()[$key]);
    }

    /** $extra: weitere Angaben für pool.json (z. B. 'shared_table' => Kurzname bei automatisch angelegten Pools geteilter Tabellen) */
    public static function create(string $key, string $label, array $sites = [], array $extra = []): void
    {
        if (!preg_match('~^[a-z][a-z0-9-]{1,31}$~', $key)) {
            throw new \InvalidArgumentException('Kurzname: a–z, 0–9, Bindestrich (2–32 Zeichen).');
        }
        if (is_file(self::dir($key) . '/pool.json')) {
            throw new \InvalidArgumentException("Pool „{$key}“ gibt es schon.");
        }
        @mkdir(self::dir($key), 0770, true);
        self::saveMeta($key, ['label' => mb_substr(trim(strip_tags($label)), 0, 80) ?: $key, 'sites' => array_values($sites), 'created' => date('c')] + $extra);
        @mkdir(self::mediaDir($key), !empty($extra['protected']) ? 0770 : 0775, true);   // geschützt: storage/pools/{key}/files
        self::db($key);
    }

    /** Datenbank eines Pools (gleiches Schema wie eine Website) */
    public static function db(string $key): Database
    {
        if (!isset(self::$dbs[$key])) {
            $db = new Database(['driver' => 'sqlite', 'path' => self::dir($key) . '/pool.sqlite']);
            $db->migrate();
            self::$dbs[$key] = $db;
        }
        return self::$dbs[$key];
    }

    /** Datensatz einer Pool-Datei (zwischengespeichert je Anfrage) */
    public static function row(string $key, int $id): ?array
    {
        $k = "$key:$id";
        if (!array_key_exists($k, self::$rows)) {
            self::$rows[$k] = is_file(self::dir($key) . '/pool.json') ? self::db($key)->fetch('SELECT * FROM media WHERE id = ?', [$id]) : null;
        }
        return self::$rows[$k];
    }

    public static function forget(): void
    {
        self::$rows = [];
    }

    /** Verweis-Eintrag dieser Website für eine Pool-Datei (anlegen, falls nötig) → lokale Medien-ID */
    public static function mirror(string $key, int $poolId): int
    {
        $row = self::row($key, $poolId) ?? throw new \RuntimeException('Datei im Pool nicht gefunden.');
        $ref = "$key:$poolId";
        $db = app()->db;
        $id = $db->fetchValue('SELECT id FROM media WHERE pool_ref = ?', [$ref]);
        if ($id) return (int) $id;
        return (int) $db->insert('media', [
            'file' => $row['file'], 'original_name' => $row['original_name'], 'mime' => $row['mime'],
            'width' => $row['width'], 'height' => $row['height'], 'size' => $row['size'],
            'alt' => '', 'variants_json' => '{}', 'created_at' => now(), 'pool_ref' => $ref,
        ]);
    }
}
