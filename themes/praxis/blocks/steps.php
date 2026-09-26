<?php /** Ablauf in Schritten (B08). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= praxis_heading($b, 'h2', 'h2 h2--m steps__title', 'title_strong', 'title_light', false) ?>
  <ol class="steps">
    <?php foreach ($d['items'] as $i => $s): ?>
    <li data-reveal="up" data-delay="<?= 60 * $i ?>">
      <span class="steps__no"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
      <h3<?= $b->edit("items.$i.title") ?>><?= e($s['title']) ?></h3>
      <?php if (!empty($s['text'])): ?><p<?= $b->edit("items.$i.text") ?>><?= e($s['text']) ?></p><?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ol>
</div>
