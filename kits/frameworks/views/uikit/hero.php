<?php
/** Einstieg (UIkit) – Varianten center | split (uk-grid, Bild eager). @var \Core\Block $b  @var array $d */
$split = $b->variant() === 'split';
$image = $split ? frameworks_image($d['image'], '(min-width: 960px) 560px, 100vw', '4:3', 'uk-box-shadow-large', ['eager' => true]) : '';
$btn = frameworks_buttons($b, ['uk-button uk-button-primary uk-button-large', 'uk-button uk-button-default uk-button-large']);
?>
<div class="uk-container">
  <?php if ($image !== ''): ?><div class="uk-grid-large uk-child-width-1-2@m uk-flex-middle" uk-grid><div><?php else: ?><div class="uk-width-2xlarge uk-margin-auto uk-text-center"><?php endif; ?>
    <?php if (filled($d['eyebrow'])): ?><p class="fw-eyebrow"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <h1 id="<?= e($b->titleId()) ?>" class="uk-heading-medium uk-margin-small-top"<?= $b->edit('title') ?>><?= e($d['title']) ?></h1>
    <?php if (filled($d['text']) || is_editing()): ?><p class="uk-text-lead"<?= $b->edit('text') ?>><?= nl2br(e($d['text']), false) ?></p><?php endif; ?>
    <?php if ($btn !== ''): ?><div class="uk-margin-medium-top fw-btns<?= $image !== '' ? '' : ' uk-flex-center' ?>"><?= $btn ?></div><?php endif; ?>
  </div>
  <?php if ($image !== ''): ?><div><?= $image ?></div></div><?php endif; ?>
</div>
