<?php /** Bild breit mit Bildunterschrift (B07). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <figure class="wide">
    <div class="wide__img ph ratio-<?= e($b->variant() ?: '21-9') ?>" data-reveal="scale">
      <?= praxis_image($d['image'], '(min-width: 1280px) 1200px, 100vw', lt('Bild · {ratio} · min. {px} px', ['ratio' => str_replace('-', ':', $b->variant()), 'px' => 2400]), '', ['ratio' => $b->variant() ?: '21-9']) ?>
    </div>
    <?php if ($d['caption'] || $d['credit']): ?>
    <figcaption><strong<?= $b->edit('caption') ?>><?= e($d['caption']) ?></strong><span<?= $b->edit('credit') ?>><?= e($d['credit']) ?></span></figcaption>
    <?php endif; ?>
  </figure>
</div>
