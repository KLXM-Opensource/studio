<?php /** Karte (überschreibt den Kern-Block für die Überschrift im Kit-Stil). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= basis_head($b) ?>
  <?= \Core\Maps::renderBlock($d) ?>
</div>
