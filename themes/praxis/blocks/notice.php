<?php /** Hinweisbox(en) info / wichtig (B10). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap notices">
  <?php foreach ($d['items'] as $i => $n): $imp = ($n['style'] ?? '') === 'important'; ?>
  <div class="notice<?= $imp ? ' notice--important' : ' notice--info' ?>" role="note" data-reveal="up">
    <span aria-hidden="true" class="notice__icon"><?= $imp ? '!' : 'i' ?></span>
    <strong<?= $b->edit("items.$i.title") ?>><?= e($n['title']) ?></strong>
    <span class="notice__text"<?= $b->edit("items.$i.text", 'inline') ?>><?= inline($n['text']) ?></span>
  </div>
  <?php endforeach; ?>
</div>
