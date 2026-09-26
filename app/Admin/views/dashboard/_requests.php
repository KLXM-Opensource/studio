<?php
/** Übersicht · „Anfragen je Woche“ (12 Wochen, nur Anzahlen) – nachgeladen. @var array $data [Montag Y-m-d => Anzahl] */
use Core\Dashboard\Dashboard;

$data = (array) $data;
$rows = array_map(fn($d, $n) => [__('Woche ab {date}', ['date' => date('d.m.', (int) strtotime($d))]), (int) $n], array_keys($data), $data);
$total = array_sum($data);
$last = (int) end($data);
?>
<p class="dash-big"><strong><?= $last ?></strong> <?= e(__('diese Woche')) ?> <span class="adm-muted">· <?= e(__('{n} in 12 Wochen', ['n' => $total])) ?></span></p>
<?php if ($total > 0): ?>
<figure class="dash-chart dash-chart--small">
  <?= Dashboard::bars($rows, __('Anfragen je Woche, letzte 12 Wochen: insgesamt {n}', ['n' => $total])) ?>
  <figcaption class="dash-axis" aria-hidden="true"><span><?= e(date('d.m.', (int) strtotime((string) array_key_first($data)))) ?></span><span><?= e(__('diese Woche')) ?></span></figcaption>
</figure>
<details class="dash-table">
  <summary><?= e(__('Als Tabelle anzeigen')) ?></summary>
  <table class="adm-table"><thead><tr><th scope="col"><?= e(__('Woche')) ?></th><th scope="col"><?= e(__('Anfragen')) ?></th></tr></thead><tbody>
  <?php foreach (array_reverse($rows) as [$l, $n]): ?><tr><th scope="row"><?= e($l) ?></th><td><?= $n ?></td></tr><?php endforeach; ?>
  </tbody></table>
</details>
<?php else: ?>
<p class="adm-muted"><?= e(__('Keine Anfragen in den letzten 12 Wochen.')) ?></p>
<?php endif; ?>
<p class="dash-links"><a href="<?= e(url('/admin/requests')) ?>"><?= e(__('Zu den Anfragen')) ?></a></p>
