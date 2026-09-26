<?php
/**
 * Handlungsaufruf: aurora (Text und Buttons auf Glas vor einem kleinen, ruhenden Farbfeld) · band (volle Breite, wirkt mit
 * dem Hintergrund „Akzentfarbe“ oder „Nacht“) · minimal (zentriert, ohne Fläche).
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['aurora', 'band', 'minimal'], true) ? $b->variant() : 'aurora';
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));
$text = trim((string) ($d['text'] ?? ''));
?>
<div class="wrap">
  <div class="cta cta--<?= e($v) ?>">
    <?php if ($v === 'aurora'): ?><span class="cta__field" aria-hidden="true"></span><?php endif; ?>
    <div class="cta__inner<?= $v === 'aurora' ? ' glass glass--strong' : '' ?>">
      <div class="cta__text">
        <?php if ($eyebrow !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($eyebrow) ?></p><?php endif; ?>
        <h2 id="<?= e($b->titleId()) ?>" class="h2 cta__title"<?= $b->edit('title') ?>><?= glas_title((string) $d['title']) ?></h2>
        <?php if ($text !== '' || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(e($text), false) ?></p><?php endif; ?>
      </div>
      <?= glas_buttons($b, 'cta__actions') ?>
    </div>
  </div>
</div>
