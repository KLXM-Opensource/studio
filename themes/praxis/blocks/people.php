<?php /** Personen kompakt (B13). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?php if ($d['title_strong']): ?><div class="people__head"><?= praxis_heading($b, 'h2', 'h2 h2--m') ?></div><?php endif; ?>
  <ul class="people">
    <?php foreach ($d['items'] as $i => $p): ?>
    <li class="person" data-reveal="up">
      <span class="person__photo ph"><?= img($p['foto'] ?? null, '72px', ['alt' => '', 'ratio' => '1:1']) ?></span>
      <span class="person__text"><strong<?= $b->edit("items.$i.name") ?>><?= e($p['name']) ?></strong><span<?= $b->edit("items.$i.rolle") ?>><?= e($p['rolle']) ?></span></span>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
