<?php
/**
 * Reiter (UIkit) – uk-tab + uk-switcher (UIkit setzt role="tablist/tab/tabpanel", Pfeiltasten, aria-selected).
 * Im Bearbeiten-Modus alle Inhalte untereinander (uk-switcher zeigte nur den aktiven).
 * @var \Core\Block $b  @var array $d
 */
$items = array_values($d['items']);
$js = !is_editing() && count($items) > 1;
$id = $b->domId() . '-sw';
?>
<div class="uk-container">
  <?php include __DIR__ . '/_head.php'; ?>
  <?php if ($js): ?>
  <ul uk-tab="connect: #<?= e($id) ?>; animation: uk-animation-fade" aria-label="<?= e((string) ($d['title'] ?: lt('Reiter'))) ?>">
    <?php foreach ($items as $i => $it): ?><li<?= $i === 0 ? ' class="uk-active"' : '' ?>><a href><?= e($it['label']) ?></a></li><?php endforeach; ?>
  </ul>
  <div id="<?= e($id) ?>" class="uk-switcher uk-margin">
    <?php foreach ($items as $it): ?><div class="fw-prose uk-width-2xlarge"><?= rich((string) ($it['text'] ?? '')) ?></div><?php endforeach; ?>
  </div>
  <?php else: ?>
  <?php foreach ($items as $i => $it): ?>
  <div class="uk-margin-medium uk-width-2xlarge">
    <h3 class="uk-h4"<?= $b->edit("items.$i.label") ?>><?= e($it['label']) ?></h3>
    <div class="fw-prose"<?= $b->edit("items.$i.text", 'rich') ?>><?= rich((string) ($it['text'] ?? '')) ?></div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
