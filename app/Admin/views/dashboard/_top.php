<?php
/** Übersicht · „Meistbearbeitete Seiten“ (Speicherungen in 30 Tagen) – nachgeladen. @var array $data */
$data = (array) $data;
$max = max(1, ...array_column($data, 'n') ?: [1]);
?>
<?php if ($data): ?>
<ol class="dash-rank" role="list">
  <?php foreach ($data as $p): ?>
  <li><a href="<?= e(url($p['href'])) ?>"><span class="dash-rank__title"><?= e($p['title']) ?></span><span class="dash-rank__n"><?= e(__('{n}× gespeichert', ['n' => (int) $p['n']])) ?></span>
    <span class="dash-rank__bar" aria-hidden="true"><span style="width:<?= round($p['n'] / $max * 100) ?>%"></span></span></a></li>
  <?php endforeach; ?>
</ol>
<?php else: ?>
<p class="adm-muted"><?= e(__('In den letzten 30 Tagen wurden keine Seiten gespeichert.')) ?></p>
<?php endif; ?>
