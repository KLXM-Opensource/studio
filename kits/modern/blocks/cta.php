<?php
/** Handlungsaufruf: band · box · split (mit Bild, Switcher) · big (großer Schriftzug in Container-Einheiten). @var \Core\Block $b  @var array $d */
$v = in_array($b->variant(), ['band', 'box', 'split', 'big'], true) ? $b->variant() : 'band';
$pic = $v === 'split' ? modern_image($d['image'] ?? null, '(min-width: 1080px) 560px, 100vw', '4:3', 'cta__media') : '';
?>
<div class="wrap">
  <div class="cta cta--<?= e($v) ?><?= $pic !== '' ? ' cta--media' : '' ?>">
    <div class="cta__main">
    <div class="cta__text">
      <?php if (trim((string) ($d['eyebrow'] ?? '')) !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="<?= $v === 'big' ? 'cta__big' : 'h2' ?> cta__title"<?= $b->edit('title') ?>><?= modern_title((string) $d['title']) ?></h2>
      <?php if ($d['text'] !== '' || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(e($d['text']), false) ?></p><?php endif; ?>
    </div>
    <?= modern_buttons($b, 'cta__actions') ?>
    </div>
    <?= $pic ?>
  </div>
</div>
