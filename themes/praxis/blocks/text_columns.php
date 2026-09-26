<?php /** Überschrift + Text. Varianten: stacked | columns (B05) | compact (H3). @var \Core\Block $b  @var array $d */
$v = $b->variant();
?>
<div class="wrap">
<?php if ($v === 'compact'): ?>
  <div class="split split--compact">
    <?= praxis_heading($b, 'h3', 'h3', 'title_strong', 'title_light', false) ?>
    <div class="prose">
      <?php foreach ($d['columns'] as $i => $c): ?><div data-reveal="up" data-delay="<?= 80 * ($i + 1) ?>"<?= $b->edit("columns.$i.text", 'rich') ?>><?= rich($c['text'] ?? '') ?></div><?php endforeach; ?>
    </div>
  </div>
<?php elseif ($v === 'columns'): ?>
  <div class="cols">
    <?= praxis_heading($b, 'h2', 'h2 h2--m') ?>
    <?php foreach ($d['columns'] as $i => $c): ?><div class="prose" data-reveal="up" data-delay="<?= 80 * $i ?>"<?= $b->edit("columns.$i.text", 'rich') ?>><?= rich($c['text'] ?? '') ?></div><?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="split">
    <?= praxis_heading($b) ?>
    <div class="prose prose--stack">
      <?php foreach ($d['columns'] as $i => $c): ?><div data-reveal="up" data-delay="<?= 80 * $i ?>"<?= $b->edit("columns.$i.text", 'rich') ?>><?= rich($c['text'] ?? '') ?></div><?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>
</div>
