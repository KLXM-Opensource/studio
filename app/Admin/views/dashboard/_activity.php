<?php
/** Übersicht · „Aktivität (30 Tage)“ – nachgeladen, Diagramm über Core\Charts (gestapelte Balken). @var array $data  [Y-m-d => [pages, published, entries, api]] */
use Core\Charts;

$data = (array) $data;
$sum = fn(string $k) => array_sum(array_column($data, $k));
$total = $sum('pages') + $sum('entries') + $sum('api');
$col = fn(string $k) => array_map(fn($v) => (int) $v[$k], array_values($data));
?>
<p class="dash-big"><strong><?= (int) $total ?></strong> <?= e(__('Änderungen in 30 Tagen')) ?></p>
<?php if ($total > 0): ?>
<?= Charts::render([
    'type' => 'bar', 'stacked' => true, 'height' => 200,
    'labels' => array_map(fn($d) => date('d.m.', (int) strtotime($d)), array_keys($data)),
    'series' => [
        ['name' => __('Seiten gespeichert'), 'data' => $col('pages')],
        ['name' => __('neue Einträge'), 'data' => $col('entries')],
        ['name' => __('über API, MCP & KI'), 'data' => $col('api')],
    ],
    'title' => __('Änderungen je Tag'),
    'description' => __('Änderungen je Tag, letzte 30 Tage: insgesamt {n}', ['n' => $total]),
], ['caption' => false, 'class' => 'dash-chart']) ?>
<p class="adm-muted dash-note"><?= e(__('{n} Seiten veröffentlicht', ['n' => (int) $sum('published')])) ?></p>
<?php else: ?>
<p class="adm-muted"><?= e(__('In den letzten 30 Tagen wurde nichts geändert.')) ?></p>
<?php endif; ?>
