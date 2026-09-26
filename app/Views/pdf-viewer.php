<?php
/** PDF-Viewer (PDF.js). @var array $m  @var bool $embed */
use Core\Media;
$name = Media::displayName($m);
$pages = Media::pages($m);
$fileUrl = Media::url($m);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($name) ?> · PDF</title>
<link rel="icon" href="<?= e(base_path()) ?>/favicon.ico" sizes="48x48">
<link rel="stylesheet" href="<?= e(asset('css/pdfviewer.css')) ?>">
<script type="module" src="<?= e(asset('js/pdfviewer.mjs')) ?>"></script>
</head>
<body class="pv<?= $embed ? ' pv--embed' : '' ?>">
<header class="pv-bar">
  <?php if (!$embed): ?><button type="button" class="pv-btn pv-back" data-back aria-label="Zurück">←</button><?php endif; ?>
  <div class="pv-title"><strong><?= e($name) ?></strong><small>PDF<?= $pages ? ' · ' . $pages . ' ' . ($pages === 1 ? 'Seite' : 'Seiten') : '' ?> · <?= e(Media::humanSize((int) $m['size'])) ?></small></div>
  <div class="pv-tools" role="toolbar" aria-label="Seiten und Zoom">
    <button type="button" class="pv-btn" data-prev aria-label="Vorherige Seite">‹</button>
    <label class="pv-pagenum"><span class="sr-only">Seite</span><input type="number" min="1" value="1" data-page inputmode="numeric"> <span>/ <span data-pages><?= $pages ?: '–' ?></span></span></label>
    <button type="button" class="pv-btn" data-next aria-label="Nächste Seite">›</button>
    <span class="pv-sep" aria-hidden="true"></span>
    <button type="button" class="pv-btn" data-zoom-out aria-label="Verkleinern">−</button>
    <button type="button" class="pv-btn pv-zoom" data-fit aria-label="An Breite anpassen" title="An Breite anpassen"><span data-zoom>100 %</span></button>
    <button type="button" class="pv-btn" data-zoom-in aria-label="Vergrößern">+</button>
  </div>
  <a class="pv-dl" href="<?= e($fileUrl) ?>" download>Herunterladen <span aria-hidden="true">↓</span></a>
</header>
<main class="pv-doc" data-pdf="<?= e($fileUrl) ?>" tabindex="0" aria-label="Dokument: <?= e($name) ?>">
  <p class="pv-status" data-status role="status">Dokument wird geladen …</p>
</main>
<noscript><p class="pv-status">Die Vorschau benötigt JavaScript. <a href="<?= e($fileUrl) ?>">PDF herunterladen</a></p></noscript>
</body>
</html>
