<?php /** Text + Bild (Bild rechts oder links, Raster aus dem Design). @var \Core\Block $b  @var array $d */
$left = $b->variant() === 'left';
$items = array_values(array_filter(array_map('trim', preg_split('~\R~', (string) ($d['list'] ?? '')) ?: []), fn($l) => $l !== ''));
$image = (int) ($d['image'] ?? 0) ?: null;
$media = editorial_image($image, '(min-width: 1360px) 620px, (min-width: 860px) 46vw, 100vw', (string) ($d['ratio'] ?: '4:5'));
$caption = trim((string) ($d['caption'] ?? ''));
?>
<div class="wrap duo<?= $left ? ' duo--left' : '' ?><?= $media === '' ? ' duo--solo' : '' ?>">
  <div class="duo__text">
    <?= editorial_head($b) ?>
    <?php if (trim(strip_tags((string) $d['text'])) !== '' || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) $d['text']) ?></div><?php endif; ?>
    <?php if ($items): ?><ul class="ticks"><?php foreach ($items as $it): ?><li><?= e($it) ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php if (trim((string) $d['button_label']) !== '' && trim((string) $d['button_link']) !== ''): ?>
    <p class="duo__more"><a class="more" <?= editorial_link_attrs((string) $d['button_link']) ?>><span<?= $b->edit('button_label') ?>><?= e($d['button_label']) ?></span> <span aria-hidden="true">→</span><?= editorial_ext_note(editorial_link((string) $d['button_link'])) ?></a></p>
    <?php endif; ?>
  </div>
  <?php if ($media !== ''): ?><figure class="duo__fig"><?= $media ?><?= editorial_caption($caption, editorial_credit($image)) ?></figure><?php endif; ?>
</div>
