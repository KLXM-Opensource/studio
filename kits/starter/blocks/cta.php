<?php
/**
 * Handlungsaufruf – Standard-Hintergrund „Akzentfarbe“ (theme.php: 'background' => 'accent'); Farben und Buttons stellen
 * die Abschnittsrollen in site.css um (.bg-accent, .bg-dark), der Block selbst kennt keine Farben.
 * @var \Core\Block $b  @var array $d
 */
?>
<div class="wrap cta">
  <h2 id="<?= e($b->titleId()) ?>"<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></h2>
  <?php if (filled($d['text']) || is_editing()): ?><p class="lead"<?= $b->edit('text') ?>><?= nl2br(emphasis((string) $d['text']), false) ?></p><?php endif; ?>
  <?= starter_buttons($b) ?>
</div>
