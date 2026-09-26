<?php
/**
 * Text + Bild (Bild im Glasrahmen). auto: mehrere Blöcke hintereinander wechseln die Seite (Bild rechts, links, rechts …) · right · left.
 * Nebeneinander, sobald der Abschnitt breit genug ist (Container-Query), sonst Bild über dem Text.
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['auto', 'right', 'left'], true) ? $b->variant() : 'auto';
if ($v === 'auto') {
    $n = 0;
    for ($p = $b->prev; $p && $p->type === 'text_image' && $p->variant() === 'auto'; $p = $p->prev) $n++;
    $v = $n % 2 ? 'left' : 'right';
}
$ratio = (string) ($d['ratio'] ?? '') ?: '4:3';
$pic = glas_image(!empty($d['image']) ? (int) $d['image'] : null, '(min-width: 1080px) 560px, 100vw', $ratio, 'gframe mt__media');
?>
<div class="wrap mt mt--<?= e($v) ?><?= $pic !== '' ? ' mt--has-media' : '' ?>">
  <div class="mt__text">
    <?= glas_head($b, 'mt__head') ?>
    <?php if (trim(strip_tags((string) $d['text'])) !== '' || is_editing()): ?><div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich((string) $d['text']) ?></div><?php endif; ?>
    <?= glas_checks(glas_lines($d['list'] ?? ''), 'mt__list') ?>
    <?= glas_buttons($b, 'mt__actions') ?>
  </div>
  <?= $pic ?>
</div>
