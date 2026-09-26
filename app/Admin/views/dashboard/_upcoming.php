<?php
/** Übersicht · „Termine (7 Tage)“ aus Kalender-Tabellen – nachgeladen. @var array $data */
$data = (array) $data;
?>
<?php if ($data): ?>
<ul class="dash-list" role="list">
  <?php foreach ($data as $o): ?>
  <li><a href="<?= e(url($o['href'])) ?>"><span class="dash-list__ico"><?= icon($o['icon']) ?></span><span class="dash-list__main"><span class="dash-list__title"><?= e($o['title']) ?></span><small><?= e($o['when']) ?> · <?= e($o['table']) ?></small></span></a></li>
  <?php endforeach; ?>
</ul>
<?php else: ?>
<p class="adm-muted"><?= e(__('Keine Termine in den nächsten 7 Tagen.')) ?></p>
<?php endif; ?>
