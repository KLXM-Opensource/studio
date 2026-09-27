<?php /** Leistungen – nummerierte Liste. @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <div class="head-split head-split--services">
    <?= praxis_heading($b) ?>
    <?php if ($d['intro']): ?><p class="muted" data-reveal="up" data-delay="80"<?= $b->edit('intro') ?>><?= e($d['intro']) ?></p><?php endif; ?>
  </div>
  <div class="services">
    <?php foreach ($d['items'] as $i => $s): ?>
    <article class="service" data-reveal="up">
      <div class="service__head">
        <span class="service__no" aria-hidden="true"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
        <h3 class="h3"<?= $b->edit("items.$i.title") ?>><?= e($s['title']) ?></h3>
      </div>
      <p class="muted"<?= $b->edit("items.$i.text") ?>><?= e($s['text']) ?></p>
    </article>
    <?php endforeach; ?>
  </div>
</div>
