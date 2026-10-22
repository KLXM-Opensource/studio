<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * php bin/console charts:selftest – Core\Charts: Prüfen der Beschreibung (Typ, Zahlen, Grenzen), Zahlen aus Eingaben,
 * Ausgabe ohne style-Attribute und ohne ausführbares Skript (CSP), Tabelle, Quellen und Engines.
 */
final class ChartsSelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails);
        $n = Charts::normalize(['type' => 'kaputt', 'labels' => ['A', 'B'], 'series' => [['name' => '', 'data' => ['3', 'x', 2.5]]], 'height' => 5000, 'format' => 'nix', 'stacked' => true]);
        $eq('unbekannter Typ → Balken', $n['type'], 'bar');
        $eq('Zahlen geprüft (Text → null)', $n['series'][0]['data'], [3, null, 2.5]);
        $eq('Höhe begrenzt, Format zurückgesetzt', [$n['height'], $n['format']], [720, 'number']);
        $eq('Name ergänzt', $n['series'][0]['name'] !== '', true);
        $pie = Charts::normalize(['type' => 'pie', 'labels' => ['A'], 'series' => [['data' => [1]], ['data' => [2]]], 'stacked' => true]);
        $eq('Torte: eine Reihe, nicht gestapelt', [count($pie['series']), $pie['stacked']], [1, false]);
        $eq('Zahlen aus Eingaben', array_map([Charts::class, 'number'], ['1.250', '12,5', '1.234,56', '3.5', '1 250', '42 %', 'abc', '']), [1250.0, 12.5, 1234.56, 3.5, 1250.0, 42.0, null, null]);

        $html = Charts::render(['type' => 'line', 'labels' => ['Mo', '<b>Di</b>'], 'series' => [['name' => 'Besucher</script>', 'data' => [1, 2]]], 'description' => 'Test']);
        $eq('ohne style-Attribute (CSP)', str_contains($html, ' style='), false);
        $eq('Daten als JSON, nicht ausführbar', [str_contains($html, 'type="application/json"'), str_contains($html, '</script><script')], [true, false]);
        $eq('JSON maskiert </script>', str_contains(explode('cms-chart__data">', $html)[1] ?? '', 'Besucher</script>'), false);
        $eq('Tabelle maskiert', str_contains($html, '&lt;b&gt;Di&lt;/b&gt;'), true);
        $eq('Lader nur einmal je Seite', substr_count($html . Charts::render(['labels' => ['a'], 'series' => [['data' => [1]]]]), 'js/charts.mjs'), 1);
        $eq('ohne Daten: Hinweis statt Diagramm', str_contains(Charts::render(['labels' => [], 'series' => []]), 'data-chart'), false);

        Charts::registerSource('selftest.demo', 'Demo', fn(array $d) => ['labels' => ['x', 'y'], 'series' => [['name' => 'S', 'data' => [4, 5]]]]);
        $b = Charts::fromBlock(['source' => 'selftest.demo', 'chart_type' => 'donut', 'height' => '240']);
        $eq('Quelle liefert Daten für den Block', [$b['type'], $b['labels'], $b['series'][0]['data']], ['donut', ['x', 'y'], [4, 5]]);
        $m = Charts::fromBlock(['labels' => "Jan\nFeb", 'series' => [['name' => 'A', 'values' => "1,5; 2\n"]]]);
        $eq('Block von Hand', [$m['labels'], $m['series'][0]['data']], [['Jan', 'Feb'], [1.5, 2.0]]);
        $eq('Tabelle unter dem Diagramm: Option des Blocks', array_map(fn($v) => Charts::tableOption(['table_view' => $v]), ['', 'open', 'none', 'x']), [false, true, 'none', false]);
        $eq('ohne Tabelle: kein „Als Tabelle anzeigen“', str_contains(Charts::render(['labels' => ['a'], 'series' => [['data' => [1]]]], ['table' => 'none']), 'cms-chart__table'), false);
        $eq('Datentabelle: unbekannte Tabelle → keine Daten', Charts::fromTable(['table' => 'gibt-es-nicht', 'value_fields' => ['gibt-es-nicht.x']]), []);
        $dt = \Core\Data\Tables::content()[0] ?? null;
        if ($dt) $eq('Datentabelle: nur Zahlenfelder als Reihen', Charts::fromTable(['table' => $dt['handle'], 'value_fields' => [$dt['handle'] . '._title', 'andere.zahl']]), []);
        $eq('Engine echarts vorhanden', isset(Charts::engines()['echarts']), true);
        $eq('Engine-Datei gebaut', is_file(ROOT . '/public/assets/js/charts-echarts.mjs') && is_file(ROOT . '/public/assets/js/charts.mjs'), true);
        $eq('Format bytes/percent', [Charts::format(1536, 'bytes'), Charts::format(12, 'percent')], [fmt()->bytes(1536), fmt()->number(12) . ' %']);
        return ['ok' => $ok, 'fails' => $fails];
    }
}
