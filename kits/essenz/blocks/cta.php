<?php
/** Handlungsaufruf: panel (Gehäuse mit Punktraster und Tasten) · band (volle Breite, wirkt mit Hintergrund „Signalfarbe“/„Nachtpaneel“) · minimal (zentriert). @var \Core\Block $b  @var array $d */
$v = in_array($b->variant(), ['panel', 'band', 'minimal'], true) ? $b->variant() : 'panel';
$eyebrow = trim((string) ($d['eyebrow'] ?? ''));
$text = trim((string) ($d['text'] ?? ''));
?>
<div class="wrap">
  <div class="cta cta--<?= e($v) ?><?= $v === 'panel' ? ' panel' : '' ?>">
    <div class="cta__text">
      <?php if ($eyebrow !== ''): ?><p class="eyebrow"<?= $b->edit('eyebrow') ?>><?= essenz_title($eyebrow) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="h2 cta__title"<?= $b->edit('title') ?>><?= essenz_title((string) $d['title']) ?></h2>
      <?php if ($text !== '' || is_editing()): ?><p class="cta__lead"<?= $b->edit('text') ?>><?= nl2br(essenz_title($text), false) ?></p><?php endif; ?>
    </div>
    <?= essenz_buttons($b, 'cta__actions') ?>
    <?php if ($v === 'panel'): ?><span class="cta__grille" aria-hidden="true"></span><?php endif; ?>
  </div>
</div>
