<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Fields;
use Core\Lang;
use Core\Media;
use Core\Pages;

/**
 * Daten für den Bereich „KLXM Ai“ (Core\Http\Controllers\Admin\AiController): fehlende Übersetzungen je Sprache
 * (Seiten, Einträge, Alt-Texte, Website-Texte) und Bilder ohne Alt-Text. Ohne KI-Aufrufe.
 */
final class Center
{
    /**
     * Lücken je weiterer Sprache.
     * @return array<string, array{label: string, pages: list<array>, entries: list<array>, media: list<array>, settings: int}>
     */
    public static function translationGaps(): array
    {
        $out = [];
        $def = Lang::default();
        foreach (Lang::all() as $code => $label) {
            if ($code === $def) continue;
            $gap = ['label' => $label, 'pages' => [], 'entries' => [], 'media' => [], 'settings' => 0];
            if (can('pages.manage')) {
                $have = [];
                foreach (app()->db->fetchAll("SELECT translation_group FROM pages WHERE lang = ? AND translation_group IS NOT NULL", [$code]) as $r) $have[(int) $r['translation_group']] = true;
                foreach (app()->db->fetchAll("SELECT * FROM pages WHERE type = 'page' AND " . Lang::sql() . ' ORDER BY path, title', [$def]) as $p) {
                    if (!isset($have[(int) ($p['translation_group'] ?: $p['id'])])) $gap['pages'][] = $p;
                }
            }
            foreach (Tables::content() as $t) {
                if (!can('data.edit', $t['handle']) || !array_filter($t['fields'], fn($f) => Fields::translatable($f))) continue;
                $groups = [];
                foreach (Entries::query($t, ['status' => 'all', 'lang' => $code, 'source' => 'own', 'limit' => 1000]) as $e) $groups[(int) ($e['translation_group'] ?: $e['id'])] = true;
                foreach (Entries::query($t, ['status' => 'all', 'lang' => $def, 'source' => 'own', 'limit' => 300]) as $e) {
                    if (!isset($groups[(int) ($e['translation_group'] ?: $e['id'])])) $gap['entries'][] = ['table' => $t, 'entry' => $e];
                }
            }
            if (can('media.upload')) {
                foreach (Media::all(['kind' => 'image']) as $m) {
                    if ((int) $m['decorative'] || trim((string) $m['alt']) === '' || !empty($m['pool_ref'])) continue;
                    $tr = json_decode((string) ($m['i18n'] ?? ''), true) ?: [];
                    if (trim((string) ($tr[$code]['alt'] ?? '')) === '') $gap['media'][] = $m;
                }
            }
            if (can('settings.edit')) {
                foreach (app()->theme->settingsFields() as $f) {
                    if (!isset($f['name']) || !in_array($f['type'] ?? 'text', ['text', 'textarea', 'richtext', 'inline'], true) || !Fields::translatable($f)) continue;
                    $v = app()->settings->get($f['name']);
                    if (is_string($v) && trim($v) !== '' && (string) app()->settings->get($f['name'] . '@' . $code, '') === '') $gap['settings']++;
                }
            }
            $out[$code] = $gap;
        }
        return $out;
    }

    /** Bilder mit Alt-Text in der Standardsprache, denen er in $lang fehlt (nicht dekorativ) – Quelle für „Übersetzen“ */
    public static function missingAltLang(string $lang): array
    {
        if (!\Core\Lang::valid($lang) || $lang === \Core\Lang::default()) return [];
        return array_values(array_filter(Media::all(['kind' => 'image', 'missing_lang' => $lang]),
            fn($m) => !(int) $m['decorative'] && trim((string) $m['alt']) !== ''));
    }

    /** Bilder dieser Website ohne Alt-Text (nicht dekorativ) */
    public static function missingAlt(): array
    {
        return array_values(array_filter(Media::all(['kind' => 'image', 'noalt' => true]), fn($m) => !(int) $m['decorative']));
    }
}
