<?php
/** Übersicht · „Mitteilungen“ (Core\Push\Stats::card, nur Zähler) – nachgeladen. @var array $data */
use Core\Charts;
use Core\Dashboard\Dashboard;

$d = (array) $data;
$days = (array) ($d['days'] ?? []);
$in = array_sum(array_column($days, 'sub'));
$out = array_sum(array_column($days, 'lost'));
?>
<p class="dash-big"><strong><?= (int) ($d['visitors'] ?? 0) ?></strong> <?= e(__('Abos von Besuchern')) ?> <span class="adm-muted">· <?= e(__('+{in} / −{out} in 14 Tagen', ['in' => $in, 'out' => $out])) ?></span></p>
<?php if ($in + $out > 0): ?>
<?= Charts::render([
    'type' => 'bar', 'height' => 160,
    'labels' => array_map(fn($k) => date('d.m.', (int) strtotime($k)), array_keys($days)),
    'series' => [['name' => __('neue Abos'), 'data' => array_map(fn($v) => (int) $v['sub'], array_values($days))],
        ['name' => __('abgemeldet'), 'data' => array_map(fn($v) => (int) $v['lost'], array_values($days))]],
    'title' => __('Tag'),
    'description' => __('Neue Abos je Tag, letzte 14 Tage: insgesamt {n}', ['n' => $in]),
], ['caption' => false, 'class' => 'dash-chart']) ?>
<?php endif; ?>
<?php if (!empty($d['last'])): ?><p class="adm-muted"><?= e(__('Zuletzt: „{title}“ – {when}, {sent} von {n} zugestellt', ['title' => (string) $d['last']['title'], 'when' => Dashboard::ago((string) $d['last']['created_at']), 'sent' => (int) $d['last']['sent'], 'n' => (int) $d['last']['recipients']])) ?></p><?php endif; ?>
<?php if (!empty($d['scheduled'])): ?><p class="adm-muted"><?= e(__('{n} geplant', ['n' => (int) $d['scheduled']])) ?></p><?php endif; ?>
<p class="dash-links"><a href="<?= e(url('/admin/mitteilungen/statistik')) ?>"><?= e(__('Statistik')) ?></a><?php if (can('push.send')): ?> · <a href="<?= e(url('/admin/mitteilungen/neu')) ?>"><?= e(__('Neue Mitteilung')) ?></a><?php endif; ?></p>
