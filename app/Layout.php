<?php
declare(strict_types=1);

namespace Core;

/**
 * Kern-Block „Layout“ (Typ layout): Blöcke in Spalten nebeneinander – Raster wählen, Blöcke in die Spalten stellen.
 * Ersetzt die frühere Abschnitts-Option „Neben den vorigen Block stellen“ (Tune row, Theme::renderRow – nur noch zur Darstellung
 * alter Inhalte bis zur Umstellung mit php bin/console layout:migrate-rows).
 *
 * Daten: { preset, valign, gap, stack, reverse, columns: [ { blocks: [ {id, type, data, tunes?: {section: {anchor, visible, background}}} ] } ] }
 *  - Anzahl der Spalten folgt dem Raster; weniger Spalten → überzählige Blöcke wandern in die letzte Spalte (nie Inhalt verlieren).
 *  - Nur verschachtelbare Blöcke (theme.php → blocks → {typ} → 'nestable' => true | ['variante', …]); keine Layouts im Layout.
 *  - Die Abschnitts-Optionen des Layout-Blocks gelten für den ganzen Abschnitt; Kinder erben den Hintergrund. Ein Kind mit eigenem
 *    Hintergrund (tunes.section.background ≠ Abschnitt) wird zur Karte.
 * Ausgabe: Theme::renderBlock → Partial section → app/Blocks/layout.php → self::render(); CSS: css/layout.css (Kern, Kit überschreibbar).
 */
final class Layout
{
    public const TYPE = 'layout';

    /** Raster: Schlüssel → Anteile je Spalte */
    public const PRESETS = [
        '1-1' => [1, 1], '2-1' => [2, 1], '1-2' => [1, 2], '1-1-1' => [1, 1, 1], '1-1-1-1' => [1, 1, 1, 1], '1-3' => [1, 3], '3-1' => [3, 1],
    ];
    public const PRESET_LABELS = [
        '1-1' => '½ + ½', '2-1' => '⅔ + ⅓', '1-2' => '⅓ + ⅔', '1-1-1' => '⅓ × 3', '1-1-1-1' => '¼ × 4', '1-3' => '¼ + ¾', '3-1' => '¾ + ¼',
    ];

    /** Verschachtelbare Blöcke, wenn ein Kit selbst nichts markiert (kein Block mit 'nestable') */
    public const DEFAULT_NESTABLE = ['richtext', 'text', 'quote', 'faq', 'downloads', 'notice'];

    /** Block-Definition (Theme fügt sie jedem Kit hinzu, außer theme.php → 'layout' => false) */
    public static function definition(): array
    {
        return [
            'label' => 'Layout (Spalten)', 'icon' => '▥', 'group' => 'Layout', 'core' => true, 'nestable' => false,
            'help' => 'Blöcke in Spalten nebeneinander: Raster wählen, dann in jede Spalte Blöcke einfügen („+ Block in diese Spalte“). Auf schmalen Bildschirmen stehen die Spalten untereinander. Hintergrund, Abstände und Sprungmarke gelten für den ganzen Abschnitt.',
            'fields' => [
                ['name' => 'preset', 'label' => 'Raster', 'type' => 'select', 'required' => true, 'default' => '1-1', 'options' => self::PRESET_LABELS,
                    'help' => 'Weniger Spalten als bisher: Die Blöcke der wegfallenden Spalten wandern in die letzte Spalte – es geht nichts verloren.'],
                ['name' => 'valign', 'label' => 'Ausrichtung vertikal', 'type' => 'select', 'required' => true, 'default' => 'top', 'width' => 'half',
                    'options' => ['top' => 'Oben', 'center' => 'Mitte', 'bottom' => 'Unten', 'stretch' => 'Gestreckt (gleich hoch)']],
                ['name' => 'gap', 'label' => 'Abstand zwischen den Spalten', 'type' => 'select', 'required' => true, 'default' => 'normal', 'width' => 'half',
                    'options' => ['small' => 'Klein', 'normal' => 'Normal', 'large' => 'Groß']],
                ['name' => 'stack', 'label' => 'Auf schmalen Bildschirmen', 'type' => 'select', 'required' => true, 'default' => 'default',
                    'options' => ['default' => 'Untereinander (Standard des Designs, meist unter 768 px)', 'tablet' => 'Schon auf Tablets untereinander (unter ca. 1024 px)']],
                ['name' => 'reverse', 'label' => 'Reihenfolge mobil umkehren (letzte Spalte zuerst)', 'type' => 'bool', 'default' => false],
            ],
        ];
    }

    public static function isLayout(array $b): bool
    {
        return ($b['type'] ?? '') === self::TYPE;
    }

    public static function preset(array $data): string
    {
        $p = (string) ($data['preset'] ?? '');
        return isset(self::PRESETS[$p]) ? $p : '1-1';
    }

    public static function count(string $preset): int
    {
        return count(self::PRESETS[$preset] ?? [1, 1]);
    }

    /**
     * Spalten auf die Anzahl des Rasters bringen: fehlende leer ergänzen, überzählige in die letzte Spalte zusammenführen.
     * @return list<array{blocks: list<array>}>
     */
    public static function fitColumns(array $columns, int $n): array
    {
        $cols = [];
        foreach (array_values($columns) as $c) {
            $cols[] = ['blocks' => array_values(array_filter((array) (is_array($c) ? ($c['blocks'] ?? []) : []), 'is_array'))];
        }
        while (count($cols) < $n) $cols[] = ['blocks' => []];
        if (count($cols) > $n) {
            $rest = array_splice($cols, $n);
            foreach ($rest as $c) array_push($cols[$n - 1]['blocks'], ...$c['blocks']);
        }
        return $cols;
    }

    /** Abschnitts-Optionen eines Kinds: Sprungmarke, sichtbar, eigener Hintergrund ('' = wie der Abschnitt) */
    public static function childTunes(array $t, Theme $theme): array
    {
        $anchor = trim((string) ($t['anchor'] ?? ''));
        $bg = (string) ($t['background'] ?? '');
        return [
            'anchor' => $anchor === '' ? '' : Pages::slugify($anchor),
            'visible' => !isset($t['visible']) || filter_var($t['visible'], FILTER_VALIDATE_BOOL),
            'background' => isset($theme->backgrounds()[$bg]) ? $bg : '',
        ];
    }

    /** Alle Blöcke einer Seite einschließlich der Blöcke in Layouts (Kinder direkt nach ihrem Layout; unsichtbares Layout → Kinder unsichtbar) */
    public static function flatten(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            $out[] = $b;
            if (!self::isLayout($b)) continue;
            $hidden = !(($b['tunes']['section']['visible'] ?? true));
            foreach ((array) ($b['data']['columns'] ?? []) as $col) {
                foreach ((array) ($col['blocks'] ?? []) as $c) {
                    if (!is_array($c)) continue;
                    if ($hidden) $c['tunes']['section']['visible'] = false;
                    $out[] = $c;
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ Ausgabe

    /** Inhalt des Layout-Blocks (ohne Abschnitts-Hülle – die setzt Theme::renderBlock über das Partial section) */
    public static function render(Theme $theme, Block $layout): string
    {
        $editing = app()->editing;
        $preset = self::preset($layout->data);
        $cols = self::fitColumns((array) ($layout->data['columns'] ?? []), self::count($preset));
        $d = $layout->data;
        $valign = in_array($d['valign'] ?? '', ['top', 'center', 'bottom', 'stretch'], true) ? $d['valign'] : 'top';
        $gap = in_array($d['gap'] ?? '', ['small', 'normal', 'large'], true) ? $d['gap'] : 'normal';
        $wrap = (string) ($theme->def['layout']['wrap'] ?? $theme->def['rows']['wrap'] ?? 'wrap');
        $cls = [$wrap, 'lay-grid', 'lay-grid--' . $preset, 'lay-v-' . $valign, 'lay-gap-' . $gap];
        if (($d['stack'] ?? '') === 'tablet') $cls[] = 'lay-stack-tablet';
        if (!empty($d['reverse'])) $cls[] = 'lay-rev';
        $parentBg = $layout->bg();
        $weights = self::PRESETS[$preset];
        $html = '<div class="' . e(trim(implode(' ', $cls))) . '">' . "\n";
        foreach ($cols as $ci => $col) {
            $items = '';
            foreach ($col['blocks'] as $bi => $c) {
                $items .= self::renderChild($theme, $layout, $c, $ci, $bi, $parentBg);
            }
            if (!$editing && trim($items) === '') $items = '';
            $html .= '<div class="lay-col lay-col--w' . (int) $weights[$ci] . ($items === '' ? ' lay-col--empty' : '') . '"'
                . ($editing ? ' data-lay-col="' . $ci . '"' : '') . '>' . $items . "</div>\n";
        }
        return $html . '</div>';
    }

    private static function renderChild(Theme $theme, Block $layout, array $c, int $ci, int $bi, string $parentBg): string
    {
        $editing = app()->editing;
        $def = $theme->block((string) ($c['type'] ?? ''));
        if (!$def || !$theme->nestable((string) $c['type'], (string) ($c['data']['variant'] ?? ''))) {
            return $editing ? '<div class="lay-item lay-item--invalid" data-lay-item="' . $ci . '.' . $bi . '"><p class="cms-error">'
                . e(__('Block „{type}“ kann nicht in einer Spalte stehen.', ['type' => (string) ($def['label'] ?? $c['type'] ?? '?')])) . '</p></div>' : '';
        }
        $t = self::childTunes((array) ($c['tunes']['section'] ?? []), $theme);
        if (!$t['visible'] && !$editing) return '';
        $own = $t['background'];
        $card = $own !== '' && $own !== $parentBg;
        $c['tunes'] = ['section' => ['background' => $card ? $own : $parentBg, 'anchor' => $t['anchor'], 'visible' => $t['visible']]];
        $child = $theme->makeBlock($c, $layout);
        if (!$child) return '';
        $child->parent = $layout;
        $child->editPrefix = "columns.$ci.blocks.$bi.data.";
        StructuredData::collect($child);
        $inner = $theme->renderInner($child);
        if ($inner === null) $inner = $editing ? '<p>' . e(__('Renderer für „{type}“ fehlt.', ['type' => $child->type])) . '</p>' : '';
        if (trim($inner) === '' && !$editing) return '';
        $cls = ['lay-item', 'sec--' . str_replace('_', '-', $child->type)];
        if ($child->variant()) $cls[] = 'v-' . $child->variant();
        // Karte: eigener Hintergrund; „sec“ dazu, weil viele Kits Fläche/Schriftfarbe nur an .sec.bg-* setzen (Variablen)
        if ($card) array_push($cls, 'sec', 'lay-item--card', 'bg-' . $own);
        if (!$t['visible']) $cls[] = 'is-hidden-block';
        $attrs = $t['anchor'] !== '' ? ' id="' . e($child->domId()) . '"' : '';
        if ($editing) {
            $attrs .= ' data-lay-item="' . $ci . '.' . $bi . '" data-lay-id="' . e($child->id) . '" data-lay-type="' . e($child->type) . '"';
        }
        return '<div' . $attrs . ' class="' . e(implode(' ', $cls)) . '">' . $inner . "</div>\n";
    }

    // ------------------------------------------------------------------ Umstellung alter Reihen (Tune row)

    /** Nächstes Raster zu Spaltenanteilen in Zwölfteln (Theme::rowSpans) */
    public static function closestPreset(array $spans): ?string
    {
        $n = count($spans);
        $sum = array_sum($spans) ?: 1;
        $best = null; $dist = INF;
        foreach (self::PRESETS as $key => $w) {
            if (count($w) !== $n) continue;
            $tw = array_sum($w);
            $dd = 0.0;
            foreach ($w as $i => $x) $dd += ($x / $tw - $spans[$i] / $sum) ** 2;
            if ($dd < $dist - 1e-9) { $dist = $dd; $best = $key; }
        }
        return $best;
    }

    /**
     * Reihen (Block + folgende Blöcke mit Tune „row“) einer Blockliste in Layout-Blöcke umwandeln.
     * Gruppe nur aus verschachtelbaren Blöcken und höchstens 4 Spalten → EIN Layout-Block (Abschnitts-Optionen des ersten Blocks,
     * Kinder mit Sprungmarke/Sichtbarkeit, anderer Hintergrund → Karte). Sonst: Option „row“ entfernen (Blöcke untereinander wie
     * vorher in einem eigenen Abschnitt) und melden. Idempotent: ohne „row“ ändert sich nichts.
     * @return array{blocks: array, changed: int, layouts: int, cleared: int, log: list<string>}
     */
    public static function migrateRows(array $blocks, Theme $theme): array
    {
        $r = ['blocks' => [], 'changed' => 0, 'layouts' => 0, 'cleared' => 0, 'log' => []];
        $row = fn(array $b) => ($v = $b['tunes']['section']['row'] ?? '') === true ? 'auto' : (string) ($v ?: '');
        $raw = fn(array $b) => !empty($theme->block((string) ($b['type'] ?? ''))['raw']);
        $label = fn(array $b) => (string) ($theme->block((string) ($b['type'] ?? ''))['label'] ?? $b['type'] ?? '?') . ' (' . ($b['type'] ?? '?') . ')';
        $clear = function (array $b): array { unset($b['tunes']['section']['row']); return $b; };
        $blocks = array_values(array_filter($blocks, 'is_array'));
        for ($i = 0, $n = count($blocks); $i < $n; $i++) {
            $lead = $blocks[$i];
            $group = [$lead];
            while ($i + 1 < $n && $row($blocks[$i + 1]) !== '' && !$raw($lead) && !$raw($blocks[$i + 1])) $group[] = $blocks[++$i];
            if (count($group) === 1) {
                if ($row($lead) !== '') {   // Option ohne Wirkung (erster Block bzw. nach einem Block mit eigener Hülle)
                    $r['blocks'][] = $clear($lead);
                    $r['changed']++; $r['cleared']++;
                    $r['log'][] = 'Option „neben den vorigen Block“ ohne Wirkung entfernt: ' . $label($lead);
                } else {
                    $r['blocks'][] = $lead;
                }
                continue;
            }
            $r['changed']++;
            $bad = array_values(array_filter($group, fn($b) => !$theme->nestable((string) ($b['type'] ?? ''), (string) ($b['data']['variant'] ?? ''))));
            $spans = Theme::rowSpans(array_map($row, $group));
            $preset = count($group) <= 4 ? self::closestPreset($spans) : null;
            if ($bad || $preset === null) {
                foreach ($group as $b) $r['blocks'][] = $clear($b);
                $r['cleared'] += count($group) - 1;
                $why = $bad ? 'nicht in Spalten möglich: ' . implode(', ', array_map($label, $bad)) : 'mehr als 4 Blöcke';
                $r['log'][] = 'Reihe aufgehoben (Blöcke wieder untereinander) – ' . $why . '; Reihe: ' . implode(' + ', array_map($label, $group));
                continue;
            }
            $leadBg = (string) ($lead['tunes']['section']['background'] ?? '') ?: (string) ($theme->block((string) $lead['type'])['background'] ?? '');
            $cols = [];
            foreach ($group as $k => $b) {
                $t = (array) ($b['tunes']['section'] ?? []);
                $bg = (string) ($t['background'] ?? '') ?: (string) ($theme->block((string) $b['type'])['background'] ?? '');
                $ct = ['anchor' => $k === 0 ? '' : (string) ($t['anchor'] ?? ''), 'visible' => $k === 0 ? true : ($t['visible'] ?? true),
                    'background' => $k > 0 && $bg !== '' && $bg !== $leadBg ? $bg : ''];
                $cols[] = ['blocks' => [['id' => (string) ($b['id'] ?? ''), 'type' => (string) $b['type'], 'data' => (array) ($b['data'] ?? []), 'tunes' => ['section' => $ct]]]];
            }
            $tunes = (array) ($lead['tunes']['section'] ?? []);
            unset($tunes['row']);
            $r['blocks'][] = [
                'id' => 'lay' . substr(md5((string) ($lead['id'] ?? '') . '|layout'), 0, 9),
                'type' => self::TYPE,
                'data' => ['preset' => $preset, 'valign' => 'top', 'gap' => 'normal', 'stack' => 'default', 'reverse' => false, 'columns' => $cols],
                'tunes' => ['section' => $tunes],
            ];
            $r['layouts']++;
            $r['log'][] = 'Layout ' . self::PRESET_LABELS[$preset] . ': ' . implode(' + ', array_map($label, $group));
        }
        return $r;
    }

    /**
     * Alle Seiten der aktuellen Website umstellen (veröffentlichte Fassung und offener Entwurf getrennt; gleiche Fassungen bleiben gleich).
     * Version „Reihen in Layout umgewandelt“. @return array{pages: int, versions: int, layouts: int, cleared: int, log: list<string>}
     */
    public static function migrateSite(bool $dry): array
    {
        $db = app()->db;
        $theme = app()->theme;
        $res = ['pages' => 0, 'versions' => 0, 'layouts' => 0, 'cleared' => 0, 'log' => []];
        foreach ($db->fetchAll('SELECT * FROM pages ORDER BY id') as $p) {
            $upd = [];
            $lines = [];
            foreach (['content_published' => 'veröffentlicht', 'content_draft' => 'Entwurf'] as $col => $what) {
                if (($p[$col] ?? null) === null || $p[$col] === '') continue;
                if ($col === 'content_draft' && $p['content_draft'] === $p['content_published']) continue;   // gleiche Fassung: s. u.
                $json = json_decode((string) $p[$col], true);
                if (!is_array($json) || !is_array($json['blocks'] ?? null)) continue;
                $m = self::migrateRows($json['blocks'], $theme);
                if (!$m['changed']) continue;
                $json['blocks'] = $m['blocks'];
                $upd[$col] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $res['versions']++;
                $res['layouts'] += $m['layouts'];
                $res['cleared'] += $m['cleared'];
                $same = $col === 'content_published' && $p['content_draft'] === $p['content_published'];
                foreach ($m['log'] as $l) $lines[] = '    [' . ($same ? 'veröffentlicht = Entwurf' : $what) . '] ' . $l;
            }
            if (isset($upd['content_published']) && $p['content_draft'] === $p['content_published']) $upd['content_draft'] = $upd['content_published'];
            if (!$upd) continue;
            $res['pages']++;
            $res['log'][] = sprintf('  Seite #%d %s', $p['id'], $p['title']);
            array_push($res['log'], ...$lines);
            if ($dry) continue;
            $db->update('pages', $upd, 'id = :id', ['id' => (int) $p['id']]);
            $rev = $upd['content_draft'] ?? $p['content_draft'] ?? $upd['content_published'];
            if ($rev !== null) Pages::addRevision((int) $p['id'], (string) $rev, null, 'Reihen in Layout umgewandelt');
        }
        if (!$dry && $res['pages']) PageCache::clear();
        return $res;
    }
}
