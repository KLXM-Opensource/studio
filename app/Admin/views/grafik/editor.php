<?php
/** Grafik-Editor als eigene Seite (Vollbild, resources/js/grafik.js) – Daten als JSON (kein Inline-Skript, Admin-CSP). @var array $boot */
?>
<h1 class="adm-sr"><?= e(__('Grafik-Editor')) ?></h1>
<div class="ge-page" data-ge-page data-ge-src="<?= e(asset('js/grafik.js')) ?>">
  <noscript><p class="adm-flash adm-flash--error"><?= e(__('Der Grafik-Editor braucht JavaScript.')) ?></p></noscript>
  <p class="ge-loading"><?= e(__('Editor wird geladen …')) ?></p>
</div>
<script type="application/json" id="ge-boot"><?= json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= e(asset('js/grafik.js')) ?>" defer></script>
