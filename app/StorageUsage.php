<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

use Core\Network\Stats;

/**
 * Speicherverbrauch einer Website, aufgeschlüsselt (Übersicht der Website, Netzwerk → Speicher):
 *   Medien nach Art (Bilder, Videos, Audio, Dokumente, Sonstiges – Summe der Originale laut Mediathek), Papierkorb,
 *   erzeugte Bildgrößen & Vorschaubilder (Rest des Medienordners), Datenbank, Suchindex, KI, Zwischenspeicher,
 *   Erweiterungen & Sonstiges (weitere Ordner im Datenordner der Website), Sicherungen (storage/backups/{key}-*.tar.gz).
 * Geteilte Medien-Pools zählen nicht zur Website (eigene Zeile „geteilt“), Installationsweites liefert installation().
 * Rekursives Zählen ist teuer: Ergebnis TTL Sekunden in storage/cache/storage-usage/ (fresh = neu zählen).
 */
final class StorageUsage
{
    public const TTL = 3600;

    /** Teile: Schlüssel => [Bezeichnung, Symbol, Farbton für den Balken] */
    public static function parts(): array
    {
        return [
            'images' => [__('Bilder'), 'image', 1], 'video' => [__('Videos'), 'film-slate', 2], 'audio' => [__('Audio'), 'music-notes', 3],
            'docs' => [__('Dokumente (PDF, Office)'), 'file-text', 4], 'other' => [__('Sonstige Dateien'), 'paperclip', 5],
            'trash' => [__('Papierkorb der Mediathek'), 'trash', 6], 'variants' => [__('Bildgrößen & Vorschaubilder'), 'images', 7],
            'database' => [__('Datenbank (Seiten, Einträge, Versionen)'), 'database', 8], 'search' => [__('Suchindex'), 'magnifying-glass', 9],
            'ai' => [__('KI (Hilfe-Index, Aufträge)'), 'sparkle', 10], 'cache' => [__('Zwischenspeicher'), 'lightning', 11],
            'extra' => [__('Erweiterungen & Sonstiges'), 'puzzle-piece', 12], 'backups' => [__('Sicherungen'), 'archive', 13],
        ];
    }

    /** Ordner im Datenordner der Hauptwebsite, die der ganzen Installation gehören (nicht der Website zugerechnet) */
    private const GLOBAL_DIRS = ['sites', 'pools', 'shared', 'sessions', 'presence', 'chat', 'support', 'branding', 'kits', 'logs', 'backups',
        'avatars', 'fonts', 'visual-diff', 'tmp', 'network', 'storage-usage'];

    /**
     * @return array{key: string, total: int, parts: array<string, array{bytes: int, files: int}>, pools: list<array>, extra: array<string, int>, at: int}
     */
    public static function site(string $key, bool $fresh = false): array
    {
        $f = ROOT . '/storage/cache/storage-usage/' . preg_replace('~[^a-z0-9_\-]~i', '', $key) . '.json';
        if (!$fresh && is_file($f)) {
            $c = json_decode((string) @file_get_contents($f), true);
            if (is_array($c) && ($c['at'] ?? 0) > time() - self::TTL) return $c;
        }
        $u = self::collect($key);
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0770, true);
        @file_put_contents($f, json_encode($u, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $u;
    }

    private static function collect(string $key): array
    {
        $site = new Site($key, Sites::all()[$key] ?? []);
        $p = array_fill_keys(array_keys(self::parts()), ['bytes' => 0, 'files' => 0]);
        $add = function (string $k, int $bytes, int $files = 0) use (&$p): void { $p[$k]['bytes'] += max(0, $bytes); $p[$k]['files'] += max(0, $files); };

        // Medien: Originale nach Art laut Mediathek (nur eigene Dateien, Verweise in Pools zählen dort)
        $originals = 0;
        try {
            $db = Stats::db($key);
            foreach ($db->fetchAll("SELECT mime, deleted_at IS NOT NULL AS gone, COUNT(*) AS n, COALESCE(SUM(size), 0) AS bytes FROM media
                WHERE pool_ref IS NULL OR pool_ref = '' GROUP BY mime, gone") as $r) {
                $originals += (int) $r['bytes'];
                $add((int) $r['gone'] ? 'trash' : self::kind((string) $r['mime']), (int) $r['bytes'], (int) $r['n']);
            }
            $dbCfg = (array) Network\Network::config($key)->get('db');
            if (($dbCfg['driver'] ?? 'sqlite') === 'sqlite') {
                foreach (['', '-wal', '-shm'] as $sfx) $add('database', (int) @filesize($dbCfg['path'] . $sfx), $sfx === '' ? 1 : 0);
            }
        } catch (\Throwable) {
            // Website noch nicht eingerichtet
        }
        // Rest des Medienordners: erzeugte Größen, Zuschnitte, Vorschaubilder, Einbettungen
        [$mediaBytes, $mediaFiles] = Stats::dirSize($site->mediaDir());
        $add('variants', $mediaBytes - $originals, max(0, $mediaFiles - $p['images']['files'] - $p['video']['files'] - $p['audio']['files'] - $p['docs']['files'] - $p['other']['files'] - $p['trash']['files']));

        // Datenordner der Website
        $extra = [];
        $base = $site->storage();
        foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $name = basename($dir);
            if ($site->isDefault() && in_array($name, self::GLOBAL_DIRS, true)) continue;
            if ($name === 'database') continue;   // oben aus der Konfiguration (auch Datenbank außerhalb)
            [$b, $n] = Stats::dirSize($dir);
            $part = match ($name) { 'search' => 'search', 'ai' => 'ai', 'cache', 'live', 'uploads' => 'cache', default => 'extra' };
            $add($part, $b, $n);
            if ($part === 'extra' && $b > 0) $extra[$name] = $b;
        }
        // Sicherungen dieser Website
        foreach (glob(ROOT . '/storage/backups/' . $key . '-*.tar.gz') ?: [] as $bk) {
            if (preg_match('~^' . preg_quote($key, '~') . '-\d{8}-\d{6}\.tar\.gz$~', basename($bk))) $add('backups', (int) @filesize($bk), 1);
        }
        // Geteilte Pools (nur zur Information)
        $pools = [];
        foreach (array_keys(MediaPools::all()) as $pk) {
            if (!in_array($key, (array) (MediaPools::meta($pk)['sites'] ?? []), true) && !in_array($pk, (array) Network\Network::config($key)->get('media_pools', []), true)) continue;
            [$b] = Stats::poolSize($pk);
            $pools[] = ['key' => $pk, 'label' => MediaPools::all()[$pk], 'bytes' => $b];
        }
        arsort($extra);
        return ['key' => $key, 'total' => array_sum(array_column($p, 'bytes')), 'parts' => $p, 'pools' => $pools, 'extra' => $extra, 'at' => time()];
    }

    /** Art einer Datei aus dem MIME-Typ */
    public static function kind(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'images',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $mime === 'application/pdf', str_starts_with($mime, 'text/'), str_contains($mime, 'officedocument'), str_contains($mime, 'opendocument'),
                in_array($mime, ['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint', 'application/rtf'], true) => 'docs',
            default => 'other',
        };
    }

    /**
     * Installation (nur Netzwerk): geteilte Pools und Ordner, die keiner Website gehören (Protokolle, Sitzungen, Chat, Support …).
     * @return array{total: int, rows: list<array{label: string, bytes: int}>, at: int}
     */
    public static function installation(bool $fresh = false): array
    {
        $f = ROOT . '/storage/cache/storage-usage/_installation.json';
        if (!$fresh && is_file($f)) {
            $c = json_decode((string) @file_get_contents($f), true);
            if (is_array($c) && ($c['at'] ?? 0) > time() - self::TTL) return $c;
        }
        $rows = [];
        foreach (MediaPools::all() as $pk => $label) $rows[] = ['label' => __('Geteilte Medien „{pool}“', ['pool' => $label]), 'bytes' => Stats::poolSize($pk)[0]];
        $names = ['shared' => __('Geteilte Daten'), 'logs' => __('Protokolle'), 'sessions' => __('Sitzungen'), 'chat' => __('Chat'), 'support' => __('Support'),
            'branding' => __('Branding'), 'avatars' => __('Profilbilder'), 'kits' => __('Hochgeladene Kits'), 'presence' => __('Präsenz')];
        $other = 0;
        foreach (self::GLOBAL_DIRS as $d) {
            if (in_array($d, ['sites', 'pools', 'backups', 'storage-usage'], true) || !is_dir(ROOT . '/storage/' . $d)) continue;
            $b = Stats::dirSize(ROOT . '/storage/' . $d)[0];
            if (isset($names[$d])) $rows[] = ['label' => $names[$d], 'bytes' => $b]; else $other += $b;
        }
        // Sicherungen ohne Website-Zuordnung (z. B. vor einem Deploy)
        $keys = array_keys(Sites::all());
        foreach (glob(ROOT . '/storage/backups/*') ?: [] as $bk) {
            $own = false;
            foreach ($keys as $k) if (preg_match('~^' . preg_quote($k, '~') . '-\d{8}-\d{6}\.tar\.gz$~', basename($bk))) { $own = true; break; }
            if (!$own && is_file($bk)) $other += (int) @filesize($bk);
            elseif (!$own && is_dir($bk)) $other += Stats::dirSize($bk)[0];
        }
        $rows[] = ['label' => __('Sonstiges (Sicherungen der Installation, temporäre Dateien)'), 'bytes' => $other];
        $rows = array_values(array_filter($rows, fn($r) => $r['bytes'] > 0));
        usort($rows, fn($a, $b) => $b['bytes'] <=> $a['bytes']);
        $u = ['total' => array_sum(array_column($rows, 'bytes')), 'rows' => $rows, 'at' => time()];
        if (!is_dir(dirname($f))) @mkdir(dirname($f), 0770, true);
        @file_put_contents($f, json_encode($u, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $u;
    }

    /** Zwischenspeicher verwerfen (nach „Neu zählen“) */
    public static function forget(?string $key = null): void
    {
        foreach (glob(ROOT . '/storage/cache/storage-usage/' . ($key !== null ? preg_replace('~[^a-z0-9_\-]~i', '', $key) : '*') . '.json') ?: [] as $f) @unlink($f);
    }
}
