<?php /** Zitat: eingerückte Fläche (Praxis) oder Vollfläche (B04). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
<?php if ($b->variant() === 'inset'): ?>
  <figure class="quote-inset">
    <div class="quote-inset__bg" data-reveal="grow" aria-hidden="true"></div>
    <blockquote data-reveal="up" data-delay="300"><span<?= $b->edit('text') ?>><?= e($d['text']) ?></span>
      <?php if ($d['highlight'] || is_editing()): ?><strong<?= $b->edit('highlight') ?>><?= e($d['highlight']) ?></strong><?php endif; ?></blockquote>
    <?php if ($d['source']): ?><figcaption<?= $b->edit('source') ?>>— <?= e($d['source']) ?></figcaption><?php endif; ?>
  </figure>
<?php else: ?>
  <figure class="quote-full">
    <blockquote data-reveal="up"><span<?= $b->edit('text') ?>><?= e($d['text']) ?></span>
      <?php if ($d['highlight'] || is_editing()): ?><strong<?= $b->edit('highlight') ?>><?= e($d['highlight']) ?></strong><?php endif; ?></blockquote>
    <?php if ($d['source'] || is_editing()): ?><figcaption>— <span<?= $b->edit('source') ?>><?= e($d['source']) ?></span></figcaption><?php endif; ?>
  </figure>
<?php endif; ?>
</div>
