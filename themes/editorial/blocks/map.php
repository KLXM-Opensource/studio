<?php /** Karte (überschreibt den Kern-Block für den Abschnittskopf im Kit-Stil). @var \Core\Block $b  @var array $d */ ?>
<div class="wrap">
  <?= editorial_head($b) ?>
  <?= \Core\Maps::renderBlock($d) ?>
</div>
