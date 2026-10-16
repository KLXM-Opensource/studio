<?php
/** Handlungsaufruf: band · box · split (mit Bild, Switcher) · big (großer Schriftzug in Container-Einheiten) · photo (Foto über die volle
 * Breite, Text als Karte darauf; Parallax und Einblenden per Scroll-Timeline in b-cta.css). @var \Core\Block $b  @var array $d */
$v = in_array($b->variant(), ['band', 'box', 'split', 'big', 'photo'], true) ? $b->variant() : 'band';
if ($v === 'photo'):
    $pos = ($d['card_pos'] ?? '') === 'right' ? 'right' : 'left'; ?>
<div class="cta-photo cta-photo--<?= $pos ?>">
  <div class="cta-photo__media" aria-hidden="true"><?= !empty($d['image']) ? img((int) $d['image'], '100vw', ['alt' => '']) : '' ?></div>
  <div class="wrap cta-photo__inner">
    <div class="cta-photo__card">
      <?php if (trim((string) ($d['eyebrow'] ?? '')) !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= fluid_title((string) $d['eyebrow']) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="h3 cta__title"<?= $b->edit('title') ?>><?= fluid_title((string) $d['title']) ?></h2>
      <?php if ($d['text'] !== '' || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(fluid_title((string) $d['text']), false) ?></p><?php endif; ?>
      <?= fluid_buttons($b, 'cta__actions') ?>
    </div>
  </div>
</div>
<?php return; endif;
$pic = $v === 'split' ? fluid_image($d['image'] ?? null, '(min-width: 1080px) 560px, 100vw', '4:3', 'cta__media') : '';
?>
<div class="wrap">
  <div class="cta cta--<?= e($v) ?><?= $pic !== '' ? ' cta--media' : '' ?>">
    <div class="cta__main">
    <div class="cta__text">
      <?php if (trim((string) ($d['eyebrow'] ?? '')) !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= fluid_title((string) $d['eyebrow']) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="<?= $v === 'big' ? 'cta__big' : 'h2' ?> cta__title"<?= $b->edit('title') ?>><?= fluid_title((string) $d['title']) ?></h2>
      <?php if ($d['text'] !== '' || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(fluid_title((string) $d['text']), false) ?></p><?php endif; ?>
    </div>
    <?= fluid_buttons($b, 'cta__actions') ?>
    </div>
    <?= $pic ?>
  </div>
</div>
