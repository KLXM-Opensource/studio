<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Diagramme für Verwaltung und Website – mit austauschbarer Engine.
 *
 * Aufrufer beschreiben ein Diagramm in einem eigenen, schlichten Format (kein ECharts-Format):
 *   echo Charts::render([
 *       'type'   => 'line',                         // line | area | bar | hbar | pie | donut
 *       'labels' => ['Mo', 'Di', 'Mi'],              // Kategorien (x-Achse bzw. Stücke)
 *       'series' => [['name' => 'Besucher', 'data' => [12, 30, 21]]],
 *       'title'  => 'Besucher', 'description' => 'Besucher je Tag der letzten Woche',   // Beschriftung + Screenreader
 *       'format' => 'number',                       // number | percent | bytes | duration (Sekunden)
 *       'unit'   => '', 'stacked' => false, 'height' => 280,
 *   ]);
 * Ausgabe: <figure data-chart> mit den Daten als JSON (<script type="application/json"> – CSP-verträglich), einer Tabelle
 * („Als Tabelle anzeigen“, auch ohne JavaScript) und dem Lader resources/js/charts.mjs (einmal je Seite). Der Lader holt die
 * Engine erst in Sichtweite und übergibt Daten und Farben (CSS-Variablen --chart-1 … --chart-8, --chart-ink, --chart-muted,
 * --chart-grid, --chart-bg).
 *
 * Engine tauschen: eine Datei mit `export function render(el, spec, theme) → {update(theme), resize(), dispose()}`
 * (Vorlage resources/js/charts-echarts.mjs) und anmelden mit Charts::registerEngine('name', $url); wählen per Konfiguration
 * 'charts' => ['engine' => 'name'] bzw. im Kit (theme.php 'chart_engine'). Aufrufer ändern sich dabei nicht.
 */
final class Charts
{
    public const TYPES = ['line', 'area', 'bar', 'hbar', 'pie', 'donut'];
    public const FORMATS = ['number', 'percent', 'bytes', 'duration'];
    private const MAX_LABELS = 1000;
    private const MAX_SERIES = 12;

    /** @var array<string, array{label: string, fn: callable}> Datenquellen für den Block „Diagramm“ (Erweiterungen, z. B. Statistik) */
    private static array $sources = [];
    /** @var array<string, string> Name => Adresse des Engine-Moduls (zusätzlich zu ECharts) */
    private static array $engines = [];
    private static bool $loader = false;

    /** Text in der Sprache des Ortes: Verwaltung __(), Website lt() */
    private static function t(string $s, array $r = []): string
    {
        return app()->request && !app()->request->isAdminPath() ? lt($s, $r) : __($s, $r);
    }

    /** Lader ist schon auf der Seite (z. B. im Layout der Verwaltung) – render() gibt ihn nicht noch einmal aus */
    public static function loaderPrinted(): void
    {
        self::$loader = true;
    }

    /** Weitere Engine anmelden (Erweiterung, Kit): Adresse eines ES-Moduls mit render(el, spec, theme) */
    public static function registerEngine(string $name, string $url): void
    {
        if (preg_match('~^[a-z][a-z0-9_-]{0,30}$~', $name)) self::$engines[$name] = $url;
    }

    /** @return array<string, string> verfügbare Engines */
    public static function engines(): array
    {
        return ['echarts' => asset('js/charts-echarts.mjs')] + self::$engines;
    }

    /** Gewählte Engine: Kit (Website) → Konfiguration 'charts.engine' → echarts */
    public static function engine(): string
    {
        $all = self::engines();
        $kit = app()->request && !app()->request->isAdminPath() ? (string) (app()->theme->def['chart_engine'] ?? '') : '';   // Kit nur auf der Website
        foreach ([$kit, (string) app()->config->get('charts.engine', '')] as $k) if ($k !== '' && isset($all[$k])) return $k;
        return 'echarts';
    }

    /**
     * Datenquelle für den Block „Diagramm“ anmelden: $fn(array $blockData): array liefert type/labels/series (wie render()).
     * Beispiel (Erweiterung): Charts::registerSource('stats.visitors', __('Besucher je Tag (30 Tage)'), fn($d) => Stats::chartVisitors());
     */
    public static function registerSource(string $key, string $label, callable $fn): void
    {
        if (preg_match('~^[a-z][a-z0-9_.-]{0,40}$~', $key)) self::$sources[$key] = ['label' => $label, 'fn' => $fn];
    }

    /** @return array<string, string> Schlüssel => Bezeichnung */
    public static function sources(): array
    {
        return array_map(fn($x) => $x['label'], self::$sources);
    }

    /** Felder des Blocks „Diagramm“ (app/Blocks/blocks.php) */
    public static function blockFields(): array
    {
        $src = ['' => 'Werte von Hand eingeben'] + self::sources();
        return [
            ['type' => 'heading', 'label' => 'Diagramm'],
            ['name' => 'chart_type', 'label' => 'Art', 'type' => 'select', 'required' => true, 'default' => 'bar', 'width' => 'half',
                'options' => ['bar' => 'Balken', 'hbar' => 'Balken waagerecht', 'line' => 'Linie', 'area' => 'Fläche', 'donut' => 'Ring', 'pie' => 'Torte']],
            ['name' => 'source', 'label' => 'Daten', 'type' => 'select', 'default' => '', 'width' => 'half', 'options' => $src,
                'help' => count($src) > 1 ? 'Von Hand oder aus einer Quelle der Website (z. B. Statistik) – dann werden die Werte laufend aktualisiert.' : ''],
            ['name' => 'labels', 'label' => 'Beschriftungen (eine je Zeile)', 'type' => 'textarea', 'rows' => 5, 'max' => 4000,
                'placeholder' => "Januar\nFebruar\nMärz", 'show_if' => ['source' => ['']]],
            ['name' => 'series', 'label' => 'Reihen', 'type' => 'repeater', 'item_label' => 'Reihe', 'title_field' => 'name', 'max_items' => 8, 'show_if' => ['source' => ['']],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'max' => 60, 'required' => true, 'placeholder' => 'z. B. Anfragen'],
                    ['name' => 'values', 'label' => 'Werte (je Beschriftung einer, getrennt mit ; oder Zeilenumbruch)', 'type' => 'textarea', 'rows' => 3, 'max' => 4000,
                        'placeholder' => '12; 19; 15'],
                ]],
            ['name' => 'format', 'label' => 'Zahlen', 'type' => 'select', 'default' => 'number', 'width' => 'half',
                'options' => ['number' => 'Zahl', 'percent' => 'Prozent', 'bytes' => 'Dateigröße', 'duration' => 'Dauer (Sekunden)']],
            ['name' => 'unit', 'label' => 'Einheit (optional)', 'type' => 'text', 'max' => 12, 'width' => 'half', 'placeholder' => 'z. B. €, kg'],
            ['name' => 'stacked', 'label' => 'Reihen stapeln (Balken, Fläche)', 'type' => 'bool', 'default' => false, 'width' => 'half'],
            ['name' => 'height', 'label' => 'Höhe', 'type' => 'select', 'default' => '320', 'width' => 'half', 'options' => ['240' => 'Niedrig', '320' => 'Mittel', '440' => 'Hoch']],
            ['name' => 'description', 'label' => 'Beschreibung für Screenreader', 'type' => 'text', 'max' => 300,
                'help' => 'Ein Satz, was das Diagramm zeigt (z. B. „Anfragen je Monat, höchster Wert im Mai“). Die Werte stehen zusätzlich als Tabelle bereit.'],
        ];
    }

    /** Beschreibung aus den Feldern des Blocks (von Hand oder aus einer angemeldeten Quelle) */
    public static function fromBlock(array $d): array
    {
        $key = (string) ($d['source'] ?? '');
        if ($key !== '' && isset(self::$sources[$key])) {
            try { $spec = (array) (self::$sources[$key]['fn'])($d); } catch (\Throwable $e) { error_log('[charts] Quelle ' . $key . ': ' . $e->getMessage()); $spec = []; }
        } else {
            $spec = ['labels' => self::lines((string) ($d['labels'] ?? '')), 'series' => array_map(fn($r) => ['name' => (string) ($r['name'] ?? ''),
                'data' => array_map([self::class, 'number'], preg_split('~\s*[;\n]\s*~', trim((string) ($r['values'] ?? ''))) ?: [])], (array) ($d['series'] ?? []))];
        }
        return ['type' => (string) ($d['chart_type'] ?? ($spec['type'] ?? 'bar')), 'format' => (string) ($d['format'] ?? ($spec['format'] ?? 'number')),
            'unit' => (string) ($d['unit'] ?? ''), 'stacked' => !empty($d['stacked']), 'height' => (int) ($d['height'] ?? 320),
            'description' => (string) ($d['description'] ?? '') ?: (string) ($spec['description'] ?? '')] + $spec;
    }

    private static function lines(string $s): array
    {
        return array_values(array_filter(array_map('trim', preg_split('~\R~', $s) ?: []), fn($l) => $l !== ''));
    }

    /** Zahl aus einer Eingabe: „1.250“, „12,5“, „1 250“, „3.5“ → float; leer/ungültig → null */
    public static function number(string $v): ?float
    {
        $v = str_replace(["\u{00A0}", ' ', '%', '€'], '', trim($v));
        if ($v === '' || !preg_match('~^-?[\d.,]+$~', $v)) return null;
        if (str_contains($v, ',') && str_contains($v, '.')) $v = strrpos($v, ',') > strrpos($v, '.') ? str_replace(['.', ','], ['', '.'], $v) : str_replace(',', '', $v);
        elseif (str_contains($v, ',')) $v = str_replace(',', '.', $v);
        elseif (preg_match('~^-?\d{1,3}(\.\d{3})+$~', $v)) $v = str_replace('.', '', $v);
        return is_numeric($v) ? (float) $v : null;
    }

    /** Beschreibung prüfen und vereinheitlichen (Typ, Zahlen, Grenzen) */
    public static function normalize(array $spec): array
    {
        $type = in_array($spec['type'] ?? '', self::TYPES, true) ? (string) $spec['type'] : 'bar';
        $labels = array_slice(array_map(fn($l) => mb_substr(trim((string) $l), 0, 80), array_values((array) ($spec['labels'] ?? []))), 0, self::MAX_LABELS);
        $series = [];
        foreach (array_slice(array_values((array) ($spec['series'] ?? [])), 0, self::MAX_SERIES) as $i => $s) {
            if (!is_array($s)) continue;
            $data = array_map(fn($v) => is_numeric($v) ? $v + 0 : null, array_slice(array_values((array) ($s['data'] ?? [])), 0, self::MAX_LABELS));
            $series[] = ['name' => mb_substr(trim((string) ($s['name'] ?? '')), 0, 80) ?: self::t('Reihe {n}', ['n' => $i + 1]), 'data' => $data];
        }
        if (in_array($type, ['pie', 'donut'], true)) $series = array_slice($series, 0, 1);   // Torte: eine Reihe
        return [
            'type' => $type, 'labels' => $labels, 'series' => $series,
            'title' => mb_substr(trim((string) ($spec['title'] ?? '')), 0, 160),
            'description' => mb_substr(trim((string) ($spec['description'] ?? '')), 0, 400),
            'format' => in_array($spec['format'] ?? '', self::FORMATS, true) ? (string) $spec['format'] : 'number',
            'unit' => mb_substr(trim((string) ($spec['unit'] ?? '')), 0, 12),
            'stacked' => !empty($spec['stacked']) && in_array($type, ['bar', 'hbar', 'area'], true),
            'height' => max(160, min(720, (int) ($spec['height'] ?? 280))),
            'locale' => Lang::current(),
        ];
    }

    /** Diagramm ausgeben. $opt: class (zusätzliche Klasse), table (true = Tabelle aufgeklappt), caption (false = ohne Titel) */
    public static function render(array $spec, array $opt = []): string
    {
        $s = self::normalize($spec);
        if (!$s['series'] || !$s['labels']) return '<p class="cms-chart__empty">' . e(self::t('Keine Daten für dieses Diagramm.')) . '</p>';
        $label = $s['description'] !== '' ? $s['description'] : $s['title'];
        $json = json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        $h = '<figure class="cms-chart cms-chart--' . e($s['type']) . (!empty($opt['class']) ? ' ' . e((string) $opt['class']) : '') . '" data-chart'
            . ' data-chart-engine="' . e(self::engines()[self::engine()]) . '">'
            . (($opt['caption'] ?? true) && $s['title'] !== '' ? '<figcaption class="cms-chart__title">' . e($s['title']) . '</figcaption>' : '')
            . '<div class="cms-chart__canvas cms-chart__h' . (int) (round($s['height'] / 40) * 40) . '" role="img" aria-label="' . e($label) . '"></div>'
            . '<script type="application/json" class="cms-chart__data">' . $json . '</script>'
            . '<details class="cms-chart__table"' . (!empty($opt['table']) ? ' open' : '') . '><summary>' . e(self::t('Als Tabelle anzeigen')) . '</summary>' . self::table($s) . '</details>'
            . '</figure>';
        if (!self::$loader) {   // einmal je Seite: Lader (und auf der Website die Stile; die Verwaltung hat sie in admin.css)
            self::$loader = true;
            if (!(app()->request && app()->request->isAdminPath())) $h .= '<link rel="stylesheet" href="' . e(asset('css/charts.css')) . '">';
            $h .= '<script type="module" src="' . e(asset('js/charts.mjs')) . '"></script>';
        }
        return $h;
    }

    /** Werte als Tabelle (Fallback ohne JavaScript, Screenreader, Ausdruck) */
    public static function table(array $s): string
    {
        $h = '<div class="cms-chart__scroll"><table><thead><tr><th scope="col">' . e($s['title'] !== '' ? $s['title'] : '') . '</th>';
        foreach ($s['series'] as $se) $h .= '<th scope="col">' . e($se['name']) . '</th>';
        $h .= '</tr></thead><tbody>';
        foreach ($s['labels'] as $i => $l) {
            $h .= '<tr><th scope="row">' . e($l) . '</th>';
            foreach ($s['series'] as $se) $h .= '<td>' . e(self::format($se['data'][$i] ?? null, $s['format'], $s['unit'])) . '</td>';
            $h .= '</tr>';
        }
        return $h . '</tbody></table></div>';
    }

    /** Ein Wert lesbar (gleiche Regeln wie die Engine) */
    public static function format(int|float|null $v, string $format = 'number', string $unit = ''): string
    {
        if ($v === null) return '–';
        $out = match ($format) {
            'percent' => fmt()->number($v, abs($v) < 10 && floor($v) != $v ? 1 : 0) . ' %',
            'bytes' => fmt()->bytes((int) $v),
            'duration' => $v >= 60 ? sprintf('%d:%02d min', intdiv((int) $v, 60), (int) $v % 60) : fmt()->number($v) . ' s',
            default => fmt()->number($v, floor($v) != $v ? 1 : 0),
        };
        return $unit !== '' ? $out . ' ' . $unit : $out;
    }
}
