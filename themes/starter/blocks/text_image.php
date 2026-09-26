<?php
/**
 * Text + Bild – Varianten right | left (Klasse v-left am Abschnitt, gespiegelt per CSS in blocks.css).
 * starter_image(): <picture> im gewählten Zuschnitt, im Editor ein Platzhalter, sonst nichts.
 * @var \Core\Block $b  @var array $d
 */
$media = starter_image($d['image'], '(min-width: 1100px) 560px, 100vw', (string) $d['ratio']);
?>
<div class="wrap split<?= $media === '' ? ' split--solo' : '' ?>">
  <div class="split__text">
    <?= starter_head($b) ?>
    <div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div>
    <?php if (filled($d['button_label']) && $d['button_link'] !== ''): ?>
    <div class="btn-row"><a class="btn btn--secondary" <?= starter_link_attrs($d['button_link']) ?>><span<?= $b->edit('button_label') ?>><?= e($d['button_label']) ?></span></a></div>
    <?php endif; ?>
  </div>
  <?= $media ?>
</div>
