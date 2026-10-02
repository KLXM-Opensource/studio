<?php
declare(strict_types=1);

namespace Core;

/**
 * Feldtyp „pages“ – mehrere Seiten auswählen (Chips mit Pfad im Seitenbaum + Dialog mit Baum, Suche, „mit Unterseiten“).
 * Oberfläche: resources/js/_pages.js (Baum aus GET /admin/api/links?format=tree, wie die Linkauswahl).
 *
 * Feld: ['name' => …, 'type' => 'pages', 'store' => 'ids' | 'paths', 'subpages' => true|false]
 *   store 'ids'   (Standard) Wert = Liste aus Seiten-IDs als Zeichenketten, „12*“ = Seite 12 mit allen Unterseiten
 *                 (gleiches Format wie früher die Mehrfachauswahl „multiselect“ mit Seiten-IDs – alte Werte bleiben gültig).
 *   store 'paths' Wert = Pfade, eine je Zeile, „/pfad/*“ = alle Seiten darunter (Format der Glossar-Ausnahmen); Pfade, die zu
 *                 keiner Seite gehören (z. B. Detailseiten von Einträgen), bleiben erhalten und lassen sich im Dialog ergänzen.
 * Prüfen, ob eine Seite gemeint ist: PagePicker::matches($wert, $seite) bzw. Glossary::excluded() für Pfade.
 */
final class PagePicker
{
    public const MAX = 500;

    /** Bereinigter Wert (ids: Liste, paths: Zeilen) */
    public static function clean(array $f, mixed $raw): array|string
    {
        $paths = ($f['store'] ?? 'ids') === 'paths';
        $sub = ($f['subpages'] ?? true) !== false;
        $list = is_array($raw) ? $raw : (preg_split('~[\r\n,]+~', is_scalar($raw) ? (string) $raw : '') ?: []);
        $out = [];
        foreach ($list as $v) {
            if (!is_scalar($v)) continue;
            $v = trim((string) $v);
            if ($paths) {
                if (!preg_match('~^/[^\s<>"]{0,300}$~', $v)) continue;
                if (!$sub) $v = (string) preg_replace('~/?\*$~', '', $v) ?: '/';
            } else {
                if (!preg_match('~^[1-9]\d{0,9}\*?$~', $v)) continue;
                if (!$sub) $v = rtrim($v, '*');
            }
            $out[$v] = true;
        }
        $out = array_slice(array_keys($out), 0, self::MAX);
        return $paths ? implode("\n", $out) : array_map('strval', $out);
    }

    /** Werte als Liste (beide Formate) */
    public static function values(mixed $value): array
    {
        if (is_array($value)) return array_values(array_filter(array_map(fn($v) => trim((string) $v), $value), 'strlen'));
        return array_values(array_filter(array_map('trim', preg_split('~\R~', (string) $value) ?: []), 'strlen'));
    }

    /** Pfad einer Seite relativ zur Website (ohne Basis-Pfad), z. B. /en/kontakt; Startseite: / bzw. /en */
    public static function pathOf(array $p): string
    {
        $prefix = Lang::prefix($p['lang'] ?? null);
        if (!empty($p['is_home'])) return $prefix !== '' ? $prefix : '/';
        return $prefix . '/' . trim((string) (($p['path'] ?? '') !== '' ? $p['path'] : $p['slug']), '/');
    }

    /** Ist $page gemeint? (Format ids: „12“ = genau diese Seite, „12*“ = Seite und alle Unterseiten) */
    public static function matches(array $list, ?array $page): bool
    {
        if (!$page || empty($page['id'])) return false;
        $id = (int) $page['id'];
        $deep = [];
        foreach ($list as $v) {
            $v = (string) $v;
            if ((int) $v === $id) return true;
            if (str_ends_with($v, '*')) $deep[(int) $v] = true;
        }
        if (!$deep) return false;
        foreach (Pages::ancestors($page) as $a) if (isset($deep[(int) $a['id']])) return true;
        return false;
    }

    /**
     * Chips für die Anzeige: value, label, trail (Elternseiten), href, sub (mit Unterseiten), lang, missing
     * @return list<array{value: string, label: string, trail: string, href: string, sub: bool, lang: string, missing: bool, custom: bool}>
     */
    public static function items(array $f, mixed $value): array
    {
        $paths = ($f['store'] ?? 'ids') === 'paths';
        $all = [];
        try { foreach (Pages::all() as $p) $all[(int) $p['id']] = $p; } catch (\Throwable) {}
        $byPath = [];
        if ($paths) foreach ($all as $p) $byPath[rtrim(self::pathOf($p), '/') ?: '/'] = $p;
        $multi = Lang::multi();
        $out = [];
        foreach (self::values($value) as $v) {
            $sub = str_ends_with($v, '*');
            if ($paths) {
                $base = rtrim((string) preg_replace('~\*$~', '', $v), '/') ?: '/';
                $p = $byPath[$base] ?? null;
            } else {
                $p = $all[(int) $v] ?? null;
            }
            $trail = $p ? implode(' › ', array_map(fn($a) => (string) $a['title'], Pages::ancestors($p))) : '';
            $out[] = ['value' => $v, 'label' => $p ? (string) $p['title'] : ($paths ? $v : __('Seite #{id}', ['id' => (int) $v])),
                'trail' => $trail, 'href' => $p ? self::pathOf($p) : ($paths ? $base : ''), 'sub' => $sub,
                'lang' => $p && $multi ? strtoupper(Lang::norm($p['lang'] ?? null)) : '',
                'missing' => !$p && !$paths, 'custom' => !$p && $paths];
        }
        return $out;
    }

    /** Ein Chip (auch Vorlage für das Skript) */
    public static function chip(array $it, string $name): string
    {
        return '<li class="pgf__chip' . ($it['missing'] ? ' is-missing' : '') . '" data-value="' . e($it['value']) . '">'
            . '<span class="pgf__txt"><span class="pgf__label">' . e($it['label']) . '</span>'
            . ($it['lang'] !== '' ? ' <span class="pgf__lang">' . e($it['lang']) . '</span>' : '')
            . ($it['sub'] ? ' <span class="pgf__sub">' . e(__('+ Unterseiten')) . '</span>' : '')
            . '<span class="pgf__trail">' . e($it['missing'] ? __('Seite nicht gefunden') : ($it['custom'] ? __('Eigener Pfad') : trim($it['trail'] . ($it['trail'] !== '' ? ' › ' : '') . $it['label']) . ' · ' . $it['href'])) . '</span></span>'
            . '<button type="button" class="pgf__x" data-pages-remove aria-label="' . e(__('„{label}“ entfernen', ['label' => $it['label']])) . '" title="' . e(__('Entfernen')) . '">×</button>'
            . '<input type="hidden" name="' . e($name) . '[]" value="' . e($it['value']) . '"></li>';
    }

    /** Feld rendern (Core\Fields::renderField) */
    public static function render(array $f, mixed $value, string $id, string $inputName, string $label, string $help, string $errHtml, string $describedBy, string $width): string
    {
        $items = self::items($f, $value);
        $store = ($f['store'] ?? 'ids') === 'paths' ? 'paths' : 'ids';
        $h = '<fieldset class="f f--pages' . $width . ($errHtml !== '' ? ' f--error' : '') . '" id="' . e($id) . '" data-pages-field data-store="' . $store . '"'
            . ' data-subpages="' . (($f['subpages'] ?? true) !== false ? '1' : '0') . '" data-name="' . e($inputName) . '"'
            . ($describedBy ? ' aria-describedby="' . e($describedBy) . '"' : '') . '><legend>' . $label . '</legend>'
            . '<input type="hidden" name="' . e($inputName) . '[]" value="">'
            . '<ul class="pgf__chips" data-pages-chips aria-label="' . e(__('Ausgewählte Seiten')) . '">';
        foreach ($items as $it) $h .= self::chip($it, $inputName);
        $h .= '</ul><p class="pgf__empty f-help" data-pages-empty' . ($items ? ' hidden' : '') . '>' . e(__('Keine Seiten ausgewählt.')) . '</p>'
            . '<p class="pgf__actions"><button type="button" class="adm-btn adm-btn--small" data-pages-pick aria-haspopup="dialog">' . icon('tree-structure') . ' ' . e(__('Seiten auswählen …')) . '</button>'
            . ' <span class="pgf__count f-help" data-pages-count aria-live="polite">' . ($items ? e(__('{n} ausgewählt', ['n' => count($items)])) : '') . '</span></p>'
            . $help . $errHtml . '</fieldset>';
        return $h;
    }
}
