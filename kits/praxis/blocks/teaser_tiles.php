<?php /** Teaser-Kacheln (Hero-Stil) bzw. Karten mit Bild (B06). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?php if ($d['intro']): ?><p class="teaser-intro" data-reveal="up"<?= $b->edit('intro') ?>><?= e($d['intro']) ?></p><?php endif; ?>
  <nav class="tiles<?= $b->variant() === 'image' ? ' tiles--image' : '' ?>" aria-label="<?= e(lt('Übersicht')) ?>">
    <?php foreach ($d['items'] as $i => $t): ?>
    <a class="tile" data-reveal="up" <?= praxis_link_attrs($t['link'] ?? '') ?>>
      <?php if ($b->variant() === 'image'): ?><span class="tile__img"><?= img($t['image'] ?? null, '(min-width: 1080px) 400px, 100vw', ['alt' => '', 'ratio' => '16:10']) ?></span><?php endif; ?>
      <span class="tile__body">
        <span class="tile__title"><span<?= $b->edit("items.$i.title") ?>><?= e($t['title']) ?></span><span class="tile__dot">.</span></span>
        <span class="tile__sub"><span<?= $b->edit("items.$i.sub") ?>><?= e($t['sub'] ?? '') ?></span><span aria-hidden="true" class="tile__arrow">→</span></span>
      </span>
    </a>
    <?php endforeach; ?>
  </nav>
</div>
