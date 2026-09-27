<?php
/** Kennzahlen: row (große Zahlen, Trennlinien) · cards · split (Überschrift neben den Zahlen, Sidebar-Muster). @var \Core\Block $b  @var array $d */
$v = $b->variant() ?: 'row';
$items = array_values((array) ($d['items'] ?? []));
?>
<div class="wrap<?= $v === 'split' ? ' stats-split' : '' ?>">
  <?= fluid_head($b) ?>
  <?php if ($items): ?>
  <dl class="grid stats stats--<?= e($v) ?>">
    <?php foreach ($items as $i => $it): ?>
    <div class="stat<?= $v === 'cards' ? ' card' : '' ?>" data-reveal>
      <dt class="stat__label"<?= $b->edit("items.$i.label") ?>><?= e($it['label']) ?></dt>
      <dd class="stat__value"<?= $b->edit("items.$i.value") ?>><?= e($it['value']) ?></dd>
      <?php if (($it['text'] ?? '') !== ''): ?><dd class="stat__text"<?= $b->edit("items.$i.text") ?>><?= e($it['text']) ?></dd><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </dl>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Kennzahlen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
