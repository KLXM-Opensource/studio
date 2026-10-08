<?php
declare(strict_types=1);

namespace Core\Review;

use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;
use Core\Media;
use Core\Pages;

/**
 * Zustand eines Gegenstands als vergleichbares Array (für Diff, Konflikterkennung und Vorschau der Prüf-Ebene).
 *
 * Ziele ($target): ['type' => page|entry|media|settings|design|pick|table, 'id' => …, 'table' => …, 'keys' => […], 'lang' => …]
 * Zeitstempel bleiben außen vor – sonst wäre jeder Stand „geändert“.
 */
final class Snapshot
{
    public const TYPES = ['page', 'entry', 'media', 'settings', 'design', 'pick', 'table'];

    /** Zustand oder null (existiert nicht bzw. noch nicht) */
    public static function take(array $target): ?array
    {
        $id = $target['id'] ?? null;
        return match ($target['type'] ?? '') {
            'page' => $id !== null ? self::page((int) $id) : null,
            'entry' => $id !== null ? self::entry((string) $target['table'], (int) $id) : null,
            'media' => $id !== null ? self::media((int) $id) : null,
            'settings' => self::settings((array) ($target['keys'] ?? []), $target['lang'] ?? null),
            'design' => \Core\Design::enabled() ? \Core\Design::values() : [],
            'pick' => self::picks((string) $target['table'], (array) ($target['ids'] ?? [])),
            'table' => $id !== null ? self::table((string) $id) : null,
            default => null,
        };
    }

    /** Prüfsumme für die Konflikterkennung */
    public static function hash(?array $state): string
    {
        return hash('sha256', json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '');
    }

    /** Datenbanken, die ein Probelauf für dieses Ziel in eine Transaktion nehmen muss */
    public static function dbs(array $target): array
    {
        $dbs = [app()->db];
        if (in_array($target['type'] ?? '', ['entry', 'pick'], true) && ($t = Tables::find((string) ($target['table'] ?? '')))) {
            $dbs[] = Tables::db($t);
        }
        $out = [];
        foreach ($dbs as $db) $out[spl_object_id($db)] = $db;
        return array_values($out);
    }

    /** Zwischenspeicher nach einem zurückgerollten Probelauf verwerfen */
    public static function reset(array $target): void
    {
        app()->settings->forget();
        if (($target['type'] ?? '') === 'media' && isset($target['id'])) Media::forget((int) $target['id']);
    }

    // ------------------------------------------------------------------ Gegenstände

    public static function exportBlocks(array $blocks): array
    {
        return array_values(array_map(fn($b) => ['id' => (string) ($b['id'] ?? ''), 'type' => (string) ($b['type'] ?? ''),
            'data' => (array) ($b['data'] ?? []), 'section' => (array) ($b['tunes']['section'] ?? [])], $blocks));
    }

    /** Export-Format ({id, type, data, section}) → Editor.js-Format mit tunes */
    public static function importBlocks(array $blocks): array
    {
        return array_values(array_map(fn($b) => ['id' => $b['id'] ?? '', 'type' => $b['type'] ?? '', 'data' => (array) ($b['data'] ?? []),
            'tunes' => ['section' => (array) ($b['section'] ?? [])]], $blocks));
    }

    private static function page(int $id): ?array
    {
        $p = Pages::find($id);
        if (!$p) return null;
        $parent = $p['parent_id'] ? Pages::find((int) $p['parent_id']) : null;
        return [
            'title' => (string) $p['title'], 'slug' => (string) $p['slug'], 'path' => (string) ($p['path'] ?? ''),
            'nav_title' => (string) ($p['nav_title'] ?? ''), 'menu' => (bool) ($p['menu'] ?? false),
            'parent' => $parent ? ($parent['path'] ?: $parent['slug']) : '', 'status' => (string) $p['status'],
            'noindex' => (bool) $p['noindex'], 'meta_title' => (string) ($p['meta_title'] ?? ''),
            'meta_description' => (string) ($p['meta_description'] ?? ''), 'lang' => \Core\Lang::norm($p['lang'] ?? null),
            // normalisiert wie beim Speichern – sonst erschienen ergänzte Standardwerte als Änderung
            'blocks' => self::exportBlocks(Pages::sanitizeBlocks(Pages::blocks($p, true))),
            'live' => $p['content_published'] !== null ? self::exportBlocks(Pages::sanitizeBlocks(Pages::blocks($p, false))) : null,
        ];
    }

    private static function entry(string $handle, int $id): ?array
    {
        $t = Tables::find($handle);
        if (!$t || Tables::isInbox($t)) return null;
        $e = Entries::find($t, $id);
        if (!$e) return null;
        $out = [];
        foreach ($t['fields'] as $f) $out[$f['name']] = $e[$f['name']] ?? null;
        return $out + ['slug' => (string) ($e['slug'] ?? ''), 'status' => (string) ($e['status'] ?? ''), 'lang' => \Core\Lang::norm($e['lang'] ?? null)];
    }

    private static function media(int $id): ?array
    {
        $m = Media::find($id);
        if (!$m) return null;
        $crops = [];
        foreach (Media::crops($m) as $ratio => $c) $crops[$ratio] = implode(', ', [$c['x'], $c['y'], $c['w'], $c['h']]);
        return [
            'alt' => (string) $m['alt'], 'title' => (string) ($m['title'] ?? ''), 'credit' => (string) ($m['credit'] ?? ''),
            'decorative' => (bool) ($m['decorative'] ?? false), 'tags' => implode(', ', Media::tagList($m['tags'] ?? '')),
            'focus' => (int) ($m['focus_x'] ?? 50) . ' / ' . (int) ($m['focus_y'] ?? 50),
            'i18n' => Media::translations($m), 'crops' => $crops,
            'collections' => array_map('intval', Media::collectionIds($id)),
        ];
    }

    private static function settings(array $keys, ?string $lang): array
    {
        $out = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            $out[$k] = app()->settings->get($lang !== null ? $k . '@' . $lang : $k);
        }
        ksort($out);
        return $out;
    }

    private static function picks(string $handle, array $ids): array
    {
        $t = Tables::find($handle);
        if (!$t || !Tables::isShared($t)) return [];
        $picks = Shared::picks($t, $ids);
        $out = [];
        foreach ($ids as $i) $out[(string) (int) $i] = $picks[(int) $i] ?? null;
        return $out;
    }

    private static function table(string $handle): ?array
    {
        $t = Tables::find($handle);
        if (!$t) return null;
        $fields = [];
        foreach ($t['fields'] as $f) $fields[$f['name']] = $f['label'] . ' (' . $f['type'] . ')' . (!empty($f['required']) ? ' *' : '');
        return ['name' => (string) $t['name'], 'singular' => (string) ($t['singular'] ?? ''), 'fields' => $fields];
    }

    // ------------------------------------------------------------------ Beschriftungen

    /** Name des Gegenstands für Listen (z. B. Seitentitel, Eintragstitel, Dateiname) */
    public static function label(array $target, ?array $state): string
    {
        $id = $target['id'] ?? null;
        return match ($target['type'] ?? '') {
            'page' => (string) ($state['title'] ?? (($p = $id ? Pages::find((int) $id) : null) ? $p['title'] : __('Neue Seite'))),
            'entry' => self::entryLabel($target, $state),
            'media' => ($m = $id ? Media::find((int) $id) : null) ? Media::displayName($m) : __('Datei') . ' #' . (int) $id,
            'settings' => app()->theme->settingsTitle(),
            'design' => __('Design'),
            'pick' => (($t = Tables::find((string) ($target['table'] ?? ''))) ? $t['name'] : (string) ($target['table'] ?? '')) . ' · ' . __('Auswahl'),
            'table' => (string) ($state['name'] ?? $id),
            'push' => (string) ($state['title'] ?? __('Mitteilung')),
            'source' => (($src = $id ? \Core\Sources\Sources::find((int) $id) : null) ? (string) $src['name'] : __('Externe Quelle')),
            default => '',
        };
    }

    private static function entryLabel(array $target, ?array $state): string
    {
        $t = Tables::find((string) ($target['table'] ?? ''));
        if (!$t) return (string) ($target['table'] ?? '');
        $tf = (string) ($t['settings']['title_field'] ?? '');
        $title = $state !== null && $tf !== '' ? trim(strip_tags((string) ($state[$tf] ?? ''))) : '';
        if ($title === '' && !empty($target['id']) && ($e = Entries::find($t, (int) $target['id']))) $title = Entries::title($t, $e);
        return $t['name'] . ' · ' . ($title !== '' ? $title : __('Neuer Eintrag'));
    }

    /** Beschriftungen der Felder eines Gegenstands: Pfad → Label */
    public static function labels(array $target): array
    {
        $out = match ($target['type'] ?? '') {
            'page' => ['title' => __('Titel'), 'slug' => __('Adresse (Slug)'), 'path' => __('Pfad'), 'nav_title' => __('Beschriftung im Menü'),
                'menu' => __('Im Hauptmenü'), 'parent' => __('Übergeordnete Seite'), 'status' => __('Status'), 'noindex' => __('Nicht indexieren'),
                'meta_title' => __('SEO-Titel'), 'meta_description' => __('SEO-Beschreibung'), 'lang' => __('Sprache'),
                'blocks' => __('Inhalt (Entwurf)'), 'live' => __('Inhalt (veröffentlicht)')],
            'media' => ['alt' => __('Alt-Text'), 'title' => __('Anzeigename'), 'credit' => __('Fotonachweis'), 'decorative' => __('Dekorativ'),
                'tags' => __('Tags'), 'focus' => __('Fokuspunkt'), 'i18n' => __('Übersetzungen'), 'crops' => __('Zuschnitte'), 'collections' => __('Sammlungen')],
            'table' => ['name' => __('Name'), 'singular' => __('Einzahl'), 'fields' => __('Felder')],
            'push' => ['title' => __('Titel'), 'body' => __('Text'), 'link' => __('Ziel'), 'to' => __('Empfänger'), 'at' => __('Zeitpunkt'), 'state' => __('Status')],
            'source' => ['sync' => __('Abgleich')],
            default => [],
        };
        if (($target['type'] ?? '') === 'entry' && ($t = Tables::find((string) ($target['table'] ?? '')))) {
            foreach ($t['fields'] as $f) $out[$f['name']] = (string) $f['label'];
            $out += ['slug' => __('Adresse (Slug)'), 'status' => __('Status'), 'lang' => __('Sprache')];
        }
        if (($target['type'] ?? '') === 'settings') {
            foreach (app()->theme->settingsFields() as $f) if (isset($f['name'])) $out[$f['name']] = (string) ($f['label'] ?? $f['name']);
        }
        return $out;
    }
}
