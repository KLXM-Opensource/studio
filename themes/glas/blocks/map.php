<?php /** Karte im Glasrahmen (überschreibt den Kern-Block für Überschrift und Rahmen im Kit-Stil). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= glas_head($b) ?>
  <div class="gframe gframe--map"><?= \Core\Maps::renderBlock($d) ?></div>
</div>
