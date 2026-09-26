<?php /** Stellenangebot (B14). @var \Core\Block $b  @var array $d */
$tags = array_filter(array_map('trim', explode(',', (string) $d['tags'])));
?>
<div class="wrap">
  <article class="job" data-reveal="up">
    <div>
      <?php if ($tags): ?><div class="job__tags"><?php foreach (array_values($tags) as $i => $t): ?><span class="tag<?= $i === 0 ? ' tag--accent' : '' ?>"><?= e($t) ?></span><?php endforeach; ?></div><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="job__title"<?= $b->edit('title') ?>><?= e($d['title']) ?></h2>
      <?php if ($d['text']): ?><p class="muted"<?= $b->edit('text') ?>><?= e($d['text']) ?></p><?php endif; ?>
    </div>
    <div class="btn-row"><a class="btn btn--primary" <?= praxis_link_attrs($d['link']) ?>><?= e($d['button_label']) ?> <span aria-hidden="true">→</span></a></div>
  </article>
</div>
