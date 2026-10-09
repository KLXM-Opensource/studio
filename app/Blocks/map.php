<?php
/**
 * Karte (Kern-Block, vom Theme überschreibbar: kits/{name}/blocks/map.php).
 * @var \Core\Block $b  @var array $d
 */
$wrap = app()->theme->def['container_class'] ?? 'wrap';
?>
<div class="<?= e($wrap) ?>">
  <?php if (!empty($d['eyebrow']) || !empty($d['title'])): ?>
  <header class="dl-head">
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2 h2--m dl-title"><span<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></span></h2><?php endif; ?>
  </header>
  <?php endif; ?>
  <?= \Core\Maps::renderBlock($d) ?>
</div>
