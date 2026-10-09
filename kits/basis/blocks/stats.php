<?php /** Kennzahlen. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= basis_head($b) ?>
  <?php if ($d['items']): ?>
  <dl class="stats cols-<?= min(4, max(2, count($d['items']))) ?>">
    <?php foreach ($d['items'] as $i => $it): ?>
    <div class="stat">
      <dt class="stat__label"<?= $b->edit("items.$i.label") ?>><?= emphasis((string) $it['label']) ?></dt>
      <dd class="stat__value"<?= $b->edit("items.$i.value") ?>><?= e($it['value']) ?></dd>
      <?php if (($it['text'] ?? '') !== ''): ?><dd class="stat__text"<?= $b->edit("items.$i.text") ?>><?= emphasis((string) $it['text']) ?></dd><?php endif; ?>
    </div>
    <?php endforeach; ?>
  </dl>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Kennzahlen – in der Seitenleiste hinzufügen.</p><?php endif; ?>
</div>
