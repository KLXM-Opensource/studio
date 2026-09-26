<?php /** Karte (überschreibt den Kern-Block für die Überschrift im Kit-Stil). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?php if ($d['title_strong'] ?? ''): ?><?= praxis_heading($b, 'h2', 'h2 h2--m map__title') ?><?php endif; ?>
  <?= \Core\Maps::renderBlock($d) ?>
</div>
