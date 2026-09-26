<?php
/** Übersicht · „Aktivität (30 Tage)“ – nachgeladen. @var array $data  [Y-m-d => [pages, published, entries, api]] */
use Core\Dashboard\Dashboard;

$data = (array) $data;
$sum = fn(string $k) => array_sum(array_column($data, $k));
$total = $sum('pages') + $sum('entries') + $sum('api');
$fmt = fn(string $d) => date('d.m.', (int) strtotime($d));
$rows = array_map(fn($d, $v) => [$fmt($d), $v['pages'] + $v['entries'] + $v['api']], array_keys($data), $data);
$busiest = $rows ? max(array_column($rows, 1)) : 0;
?>
<p class="dash-big"><strong><?= (int) $total ?></strong> <?= e(__('Änderungen in 30 Tagen')) ?></p>
<?php if ($total > 0): ?>
<figure class="dash-chart">
  <?= Dashboard::bars($rows, __('Änderungen je Tag, letzte 30 Tage: insgesamt {n}, höchstens {max} an einem Tag', ['n' => $total, 'max' => $busiest])) ?>
  <figcaption class="dash-axis" aria-hidden="true"><span><?= e($rows[0][0]) ?></span><span><?= e($rows[14][0] ?? '') ?></span><span><?= e(__('heute')) ?></span></figcaption>
</figure>
<ul class="dash-legend" role="list">
  <li><b><?= (int) $sum('pages') ?></b> <?= e(__('Seiten gespeichert')) ?> <span class="adm-muted">(<?= e(__('{n} veröffentlicht', ['n' => (int) $sum('published')])) ?>)</span></li>
  <li><b><?= (int) $sum('entries') ?></b> <?= e(__('neue Einträge')) ?></li>
  <li><b><?= (int) $sum('api') ?></b> <?= e(__('über API, MCP & KI')) ?></li>
</ul>
<details class="dash-table">
  <summary><?= e(__('Als Tabelle anzeigen')) ?></summary>
  <table class="adm-table">
    <caption class="sr-only"><?= e(__('Änderungen je Tag')) ?></caption>
    <thead><tr><th scope="col"><?= e(__('Tag')) ?></th><th scope="col"><?= e(__('Seiten')) ?></th><th scope="col"><?= e(__('veröffentlicht')) ?></th><th scope="col"><?= e(__('Einträge')) ?></th><th scope="col"><?= e(__('API/KI')) ?></th></tr></thead>
    <tbody>
    <?php foreach (array_reverse($data, true) as $d => $v): if (!array_sum($v)) continue; ?>
      <tr><th scope="row"><?= e($fmt($d)) ?></th><td><?= (int) $v['pages'] ?></td><td><?= (int) $v['published'] ?></td><td><?= (int) $v['entries'] ?></td><td><?= (int) $v['api'] ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</details>
<?php else: ?>
<p class="adm-muted"><?= e(__('In den letzten 30 Tagen wurde nichts geändert.')) ?></p>
<?php endif; ?>
