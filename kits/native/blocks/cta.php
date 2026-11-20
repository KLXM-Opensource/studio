<?php
/**
 * Handlungsaufruf im Kit „NativeCSS“:
 *   band  Farbfläche in Markenfarbe (.ncss-tile, Verlauf Akzent → zweite Farbe; auf farbigem Abschnitt ohne zweite Fläche)
 *   box   Kachel auf getönter Fläche
 * „split“ (mit Bild), „big“ (großer Schriftzug) und „photo“ (Foto mit Textkarte) kommen aus dem Kit fluid.
 * @var \Core\Block $b  @var array $d
 */
$v = in_array($b->variant(), ['band', 'box', 'split', 'big', 'photo'], true) ? $b->variant() : 'band';
if (!in_array($v, ['band', 'box'], true)) {
    require native_parent_view('cta');
    return;
}
native_mark('@nv-cta');
?>
<div class="wrap">
  <div class="ncss-tile nv-cta nv-cta--<?= e($v) ?>">
    <div class="nv-cta__text">
      <?php if (trim((string) ($d['eyebrow'] ?? '')) !== ''): ?><p class="nv-cta__eyebrow"<?= $b->edit('eyebrow') ?>><?= fluid_title((string) $d['eyebrow']) ?></p><?php endif; ?>
      <h2 id="<?= e($b->titleId()) ?>" class="h2 nv-cta__title"<?= $b->edit('title') ?>><?= fluid_title((string) $d['title']) ?></h2>
      <?php if (trim((string) $d['text']) !== '' || is_editing()): ?><p class="nv-cta__lead"<?= $b->edit('text') ?>><?= nl2br(fluid_title((string) $d['text']), false) ?></p><?php endif; ?>
    </div>
    <?= fluid_buttons($b, 'nv-cta__actions') ?>
  </div>
</div>
