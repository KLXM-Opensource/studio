<?php
/**
 * Text – Rich-Text aus dem Editor. rich() gibt nur erlaubtes HTML aus (Core\Sanitizer, Klassenvertrag: Hilfe → Kits &
 * Design → Rich-Text-Stile). $b->edit('text', 'rich') öffnet im Editor die Formatierungsleiste.
 * @var \Core\Block $b  @var array $d
 */
?>
<div class="wrap wrap--text">
  <?= starter_head($b) ?>
  <div class="prose"<?= $b->edit('text', 'rich') ?>><?= rich($d['text']) ?></div>
</div>
