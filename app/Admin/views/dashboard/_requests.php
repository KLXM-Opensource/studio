<?php
/** Übersicht · „Anfragen je Woche“ (12 Wochen, nur Anzahlen) – nachgeladen, Diagramm über Core\Charts. @var array $data [Montag Y-m-d => Anzahl] */
use Core\Charts;

$data = (array) $data;
$total = array_sum($data);
$last = (int) end($data);
?>
<p class="dash-big"><strong><?= $last ?></strong> <?= e(__('diese Woche')) ?> <span class="adm-muted">· <?= e(__('{n} in 12 Wochen', ['n' => $total])) ?></span></p>
<?php if ($total > 0): ?>
<?= Charts::render([
    'type' => 'bar', 'height' => 160,
    'labels' => array_map(fn($d) => date('d.m.', (int) strtotime($d)), array_keys($data)),
    'series' => [['name' => __('Anfragen'), 'data' => array_map('intval', array_values($data))]],
    'title' => __('Woche ab'),
    'description' => __('Anfragen je Woche, letzte 12 Wochen: insgesamt {n}', ['n' => $total]),
], ['caption' => false, 'class' => 'dash-chart']) ?>
<?php else: ?>
<p class="adm-muted"><?= e(__('Keine Anfragen in den letzten 12 Wochen.')) ?></p>
<?php endif; ?>
<p class="dash-links"><a href="<?= e(url('/admin/requests')) ?>"><?= e(__('Zu den Anfragen')) ?></a></p>
