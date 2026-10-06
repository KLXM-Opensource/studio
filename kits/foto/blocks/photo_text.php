<?php
/**
 * Bild & Text – Bild links oder rechts, Breite ein Drittel / Hälfte / zwei Drittel (flex-basis; Flex-Umbruch statt Breakpoint:
 * untereinander, sobald der Text weniger als 18 rem hätte). Text oben/mittig/unten ausgerichtet, optional Lightbox.
 * @var \Core\Block $b  @var array $d
 */
$v = $b->variant() === 'right' ? 'right' : 'left';
$size = in_array($d['size'] ?? '', ['third', 'half', 'large'], true) ? $d['size'] : 'half';
$align = in_array($d['align'] ?? '', ['start', 'center', 'end'], true) ? $d['align'] : 'center';
$lightbox = !empty($d['lightbox']) && !is_editing();
$m = !empty($d['image']) ? media((int) $d['image']) : null;
$ratio = (string) ($d['ratio'] ?? '4:5');
$sizes = ['third' => '(min-width: 1000px) 33vw, 100vw', 'half' => '(min-width: 1000px) 50vw, 100vw', 'large' => '(min-width: 1000px) 66vw, 100vw'][$size];
$photo = $m ? foto_photo($m, ['sizes' => $sizes, 'ratio' => $ratio, 'caption' => trim((string) ($d['caption'] ?? '')), 'capEdit' => $b->edit('caption'),
    'lightbox' => $lightbox, 'class' => 'pt__media', 'path' => 'image']) : foto_photo_empty($ratio);
$hasText = trim(strip_tags((string) ($d['text'] ?? ''))) !== '' || is_editing();
?>
<div class="wrap pt pt--<?= e($v) ?> pt--<?= e($size) ?> pt--<?= e($align) ?>"<?= $lightbox ? ' data-lb-group' : '' ?>>
  <?= $photo ?>
  <div class="pt__text">
    <?= foto_head($b, 'pt__head') ?>
    <?php if ($hasText): ?><div class="prose pt__prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) ($d['text'] ?? '')) ?></div><?php endif; ?>
    <?= foto_buttons($b) ?>
    <?= foto_drop_zone('image', 'single') ?>
  </div>
</div>
<?= $lightbox && $m ? foto_lightbox() : '' ?>
