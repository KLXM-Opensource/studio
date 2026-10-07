<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Lang;
use Core\Media;
use Core\Pages;
use Core\Search\Text;

/**
 * SEO-Check ohne KI: prüft Seiten auf Titel- und Beschreibungslänge, doppelte Titel/Beschreibungen, Überschriften-Struktur,
 * Bilder ohne Alt-Text, wenig Text und Vorschaubild. Grundlage für den SEO-Check im Seiten-Formular und die SEO-Übersicht
 * (Core\Http\Controllers\Admin\AiController). KI-Vorschläge kommen zusätzlich aus Core\AI\Assist::seo().
 */
final class SeoCheck
{
    public const TITLE_MAX = 60;
    public const TITLE_MIN_OWN = 30;   // Zeichen, die dem Seitentitel neben dem Titel-Zusatz mindestens bleiben (Assist::titleBudget)
    public const DESC_MIN = 70;
    public const DESC_MAX = 160;
    public const WORDS_MIN = 150;

    /** Titel, wie er im <title> erscheint (Meta-Titel oder Seitentitel + Zusatz) */
    public static function effectiveTitle(array $page): string
    {
        $t = trim((string) ($page['meta_title'] ?? '')) ?: (string) $page['title'];
        $suffix = Assist::titleSuffix();
        return $suffix !== '' && !str_contains($t, trim($suffix, ' |')) ? $t . $suffix : $t;
    }

    /**
     * Prüfungen einer Seite. @return list<array{level: 'error'|'warn'|'info'|'ok', text: string}>
     */
    public static function page(array $page, bool $render = true): array
    {
        $out = [];
        $add = function (string $level, string $text) use (&$out) { $out[] = ['level' => $level, 'text' => $text]; };
        $title = self::effectiveTitle($page);
        $tl = mb_strlen($title);
        if ($tl > self::TITLE_MAX) $add('warn', __('Titel in Suchergebnissen ist {n} Zeichen lang (empfohlen höchstens {max}) – „{title}“.', ['n' => $tl, 'max' => self::TITLE_MAX, 'title' => $title]));
        else $add('ok', __('Titellänge in Ordnung ({n} Zeichen).', ['n' => $tl]));
        $desc = trim((string) ($page['meta_description'] ?? ''));
        $dl = mb_strlen($desc);
        if ($desc === '') $add('error', __('Keine Beschreibung für Suchmaschinen – es erscheint der Standardtext bzw. ein zufälliger Ausschnitt.'));
        elseif ($dl < self::DESC_MIN) $add('warn', __('Beschreibung ist kurz ({n} Zeichen, ideal 120–{max}).', ['n' => $dl, 'max' => self::DESC_MAX]));
        elseif ($dl > self::DESC_MAX) $add('warn', __('Beschreibung ist lang ({n} Zeichen) und wird in Suchergebnissen abgeschnitten.', ['n' => $dl]));
        else $add('ok', __('Beschreibung in Ordnung ({n} Zeichen).', ['n' => $dl]));

        // Doppelte Titel/Beschreibungen in derselben Sprache
        $same = self::duplicates($page);
        if ($same['title']) $add('warn', __('Gleicher Titel wie: {list}', ['list' => implode(', ', array_map(fn($p) => '„' . $p['title'] . '“', $same['title']))]));
        if ($same['description']) $add('warn', __('Gleiche Beschreibung wie: {list}', ['list' => implode(', ', array_map(fn($p) => '„' . $p['title'] . '“', $same['description']))]));

        // Text
        $words = count(preg_split('~\s+~u', trim(Assist::pageText($page, 20000))) ?: []);
        if ($words < self::WORDS_MIN) $add('warn', __('Wenig Text ({n} Wörter) – Suchmaschinen und Besucher finden wenig Inhalt.', ['n' => $words]));
        else $add('ok', __('{n} Wörter Text.', ['n' => $words]));

        // Bilder der Blöcke ohne Alt-Text (auch in der Sprache der Seite)
        $lang = Lang::norm($page['lang'] ?? null);
        $missing = [];
        foreach (self::mediaIds(Pages::blocks($page, true)) as $id) {
            $m = Media::find($id);
            if (!$m || !str_starts_with((string) $m['mime'], 'image/') || (int) $m['decorative']) continue;
            $alt = trim((string) $m['alt']);
            if ($lang !== Lang::default()) $alt = trim((string) ((json_decode((string) ($m['i18n'] ?? ''), true) ?: [])[$lang]['alt'] ?? ''));
            if ($alt === '') $missing[] = $m['title'] ?: $m['original_name'];
        }
        if ($missing) $add('error', __('Bilder ohne Alt-Text ({lang}): {list}', ['lang' => strtoupper($lang), 'list' => implode(', ', array_slice(array_unique($missing), 0, 8))]));

        // Überschriften in der gerenderten Seite (Entwurf)
        if ($render) {
            foreach (self::headings($page) as $h) $add($h[0], $h[1]);
        }
        if (empty($page['og_image']) && !setting(app()->theme->def['seo']['og_image'] ?? 'og_default_image')) {
            $add('info', __('Kein Vorschaubild für soziale Netzwerke.'));
        }
        if (!empty($page['noindex'])) $add('info', __('Seite ist von Suchmaschinen ausgeschlossen (noindex).'));
        if (($page['status'] ?? '') !== 'published') $add('info', __('Seite ist ein Entwurf und noch nicht online.'));
        return $out;
    }

    /** Seiten derselben Sprache mit gleichem Titel bzw. gleicher Beschreibung */
    public static function duplicates(array $page): array
    {
        $out = ['title' => [], 'description' => []];
        $t = mb_strtolower(trim((string) ($page['meta_title'] ?? '')) ?: trim((string) $page['title']));
        $d = mb_strtolower(trim((string) ($page['meta_description'] ?? '')));
        foreach (app()->db->fetchAll("SELECT * FROM pages WHERE type = 'page' AND id <> ? AND " . Lang::sql(), [(int) $page['id'], Lang::norm($page['lang'] ?? null)]) as $p) {
            if (mb_strtolower(trim((string) ($p['meta_title'] ?? '')) ?: trim((string) $p['title'])) === $t) $out['title'][] = $p;
            if ($d !== '' && mb_strtolower(trim((string) $p['meta_description'])) === $d) $out['description'][] = $p;
        }
        return $out;
    }

    /** Medien-IDs in Blockdaten (Felder vom Typ media, auch in Wiederholungen) */
    public static function mediaIds(array $blocks): array
    {
        $ids = [];
        $walk = function (array $fields, array $data) use (&$walk, &$ids) {
            foreach ($fields as $f) {
                $v = $data[$f['name'] ?? ''] ?? null;
                if (($f['type'] ?? '') === 'media' && is_numeric($v)) $ids[] = (int) $v;
                if (($f['type'] ?? '') === 'repeater' && is_array($v)) foreach ($v as $item) if (is_array($item)) $walk((array) ($f['fields'] ?? []), $item);
            }
        };
        foreach (\Core\Layout::flatten($blocks) as $b) {
            $def = app()->theme->block((string) ($b['type'] ?? ''));
            if ($def && is_array($b['data'] ?? null)) $walk((array) ($def['fields'] ?? []), $b['data']);
            if (!empty($b['tunes']['section']['bgImage'])) $ids[] = (int) $b['tunes']['section']['bgImage'];
        }
        return array_values(array_unique($ids));
    }

    /** Überschriften-Struktur der gerenderten Seite: genau eine H1, keine übersprungenen Ebenen, keine leeren Überschriften */
    public static function headings(array $page): array
    {
        $prev = app()->lang;
        app()->lang = ($page['lang'] ?? null) ?: null;
        try {
            $html = (new \Core\Http\Controllers\SiteController())->previewHtml(['content_published' => $page['content_draft'] ?? $page['content_published']] + $page);
        } catch (\Throwable $e) {
            error_log('[ai] seo-check render: ' . $e->getMessage());
            return [['info', __('Überschriften konnten nicht geprüft werden.')]];
        } finally {
            app()->lang = $prev;
        }
        if (preg_match('~<main\b[^>]*>(.*)</main>~is', $html, $m)) $html = $m[1];
        preg_match_all('~<h([1-6])\b[^>]*>(.*?)</h\1>~is', $html, $mm, PREG_SET_ORDER);
        $out = [];
        $levels = array_map(fn($x) => (int) $x[1], $mm);
        $h1 = count(array_filter($levels, fn($l) => $l === 1));
        if (!$mm) return [['warn', __('Die Seite hat keine Überschriften.')]];
        if ($h1 === 0) $out[] = ['warn', __('Keine Hauptüberschrift (H1) im Inhalt.')];
        elseif ($h1 > 1) $out[] = ['warn', __('{n} Hauptüberschriften (H1) – empfohlen ist genau eine.', ['n' => $h1])];
        $jumps = [];
        $last = 0;
        foreach ($mm as $x) {
            $l = (int) $x[1];
            if ($last && $l > $last + 1) $jumps[] = 'H' . $last . ' → H' . $l . ' („' . mb_strimwidth(Text::plain($x[2]), 0, 40, '…') . '“)';
            if (Text::plain($x[2]) === '') $out[] = ['warn', __('Leere Überschrift (H{n}).', ['n' => $l])];
            $last = $l;
        }
        if ($jumps) $out[] = ['warn', __('Übersprungene Überschriften-Ebenen: {list}', ['list' => implode('; ', array_slice($jumps, 0, 5))])];
        if (!$out) $out[] = ['ok', __('Überschriften-Struktur in Ordnung ({n} Überschriften).', ['n' => count($mm)])];
        return $out;
    }

    /**
     * SEO-Übersicht: Seiten (ohne Rendern) und Einträge mit Detailseiten, bei denen etwas fehlt.
     * @return array{pages: list<array>, entries: list<array>}
     */
    public static function overview(): array
    {
        $pages = [];
        $all = app()->db->fetchAll("SELECT * FROM pages WHERE type = 'page' ORDER BY COALESCE(lang, ''), path, title");
        foreach ($all as $p) {
            $issues = array_values(array_filter(self::page($p, false), fn($c) => in_array($c['level'], ['error', 'warn'], true)));
            $pages[] = ['page' => $p, 'issues' => $issues, 'missing' => trim((string) $p['meta_description']) === ''];
        }
        $entries = [];
        foreach (Tables::content() as $t) {
            $df = (string) ($t['settings']['description_field'] ?? '');
            if (($t['settings']['route'] ?? '') === '' || $df === '' || !Tables::field($t, $df) || !can('data.edit', $t['handle'])) continue;
            foreach (Entries::query($t, ['status' => 'all', 'lang' => 'all', 'source' => 'own', 'limit' => 300]) as $e) {
                $v = trim(Text::plain((string) ($e[$df] ?? '')));
                if ($v !== '' && mb_strlen($v) <= self::DESC_MAX) continue;
                $entries[] = ['table' => $t, 'entry' => $e, 'field' => Tables::field($t, $df), 'length' => mb_strlen($v)];
            }
        }
        return ['pages' => $pages, 'entries' => $entries];
    }
}
