<?php
/** Text + Bild (UIkit) – uk-grid, Bild links per uk-flex-first@m. Rich-Text: UIkit-Grundstile + .fw-prose (css/uikit.css). */
$media = frameworks_image($d['image'], '(min-width: 960px) 560px, 100vw', (string) $d['ratio'], 'uk-box-shadow-medium');
$left = $b->variant() === 'left';
?>
<div class="uk-container">
  <div class="uk-grid-large uk-flex-middle<?= $media !== '' ? ' uk-child-width-1-2@m' : '' ?>" uk-grid>
    <div>
      <?php if (filled($d['eyebrow'])): ?><p class="fw-eyebrow"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="uk-h2 uk-margin-remove-top"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h2>
      <div class="fw-prose"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div>
      <?php if (filled($d['button_label']) && $d['button_link'] !== ''): ?>
      <p class="uk-margin-medium-top"><a class="uk-button uk-button-default" <?= frameworks_link_attrs($d['button_link']) ?>><span<?= $b->edit('button_label') ?>><?= e($d['button_label']) ?></span></a></p>
      <?php endif; ?>
    </div>
    <?php if ($media !== ''): ?><div<?= $left ? ' class="uk-flex-first@m"' : '' ?>><?= $media ?></div><?php endif; ?>
  </div>
</div>
