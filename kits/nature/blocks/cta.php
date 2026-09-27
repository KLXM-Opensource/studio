<?php
/** Handlungsaufruf: panel (Karte mit Blattmotiv) · band (volle Breite, wirkt mit Hintergrund „Akzentfarbe“/„Waldnacht“) · minimal (zentriert). @var \Core\Block $b  @var array $d */
$v = in_array($b->variant(), ['panel', 'band', 'minimal'], true) ? $b->variant() : 'panel';
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));
$text = trim((string) ($d['text'] ?? ''));
?>
<div class="wrap">
  <div class="cta cta--<?= e($v) ?><?= $v === 'panel' ? ' panel' : '' ?>">
    <div class="cta__text">
      <?php if ($eyebrow !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= e($eyebrow) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="h2 cta__title"<?= $b->edit('title') ?>><?= nature_title((string) $d['title']) ?></h2>
      <?php if ($text !== '' || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(e($text), false) ?></p><?php endif; ?>
    </div>
    <?= nature_buttons($b, 'cta__actions') ?>
    <?php if ($v === 'panel'): ?><svg class="cta__leaf" viewBox="0 0 120 120" aria-hidden="true" focusable="false"><path class="cta__leaf-blade" d="M14 106C14 50 50 14 106 14C106 70 70 106 14 106Z"/><path class="cta__leaf-vein" d="M20 100L96 24M42 78L42 56M60 60L60 40M78 42L78 28M42 78L64 78M60 60L80 60"/></svg><?php endif; ?>
  </div>
</div>
