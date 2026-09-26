<?php /** Handlungsaufruf: Band über die volle Breite oder hervorgehobene Box. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <div class="cta cta--<?= $b->variant() === 'box' ? 'box' : 'band' ?>">
    <div class="cta__text">
      <h2 id="<?= e($b->titleId()) ?>" class="h2 cta__title"<?= $b->edit('title') ?>><?= e($d['title']) ?></h2>
      <?php if ($d['text'] !== '' || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(e($d['text']), false) ?></p><?php endif; ?>
    </div>
    <?= basis_buttons($b, 'cta__actions') ?>
  </div>
</div>
