<?php
/**
 * Downloads – Typ, Größe und Seitenzahl liefert Core\Media; PDFs zusätzlich „Ansehen“ im PDF-Viewer des Cores
 * (Media::viewerUrl, PDF.js vom eigenen Server).
 * @var \Core\Block $b  @var array $d
 */
use Core\Media;
?>
<div class="wrap wrap--text">
  <?= starter_head($b) ?>
  <ul class="downloads" role="list">
    <?php foreach ($d['files'] as $i => $f): $m = media((int) ($f['file'] ?? 0) ?: null);
        if (!$m) { if (is_editing()) echo '<li class="empty-hint">[Datei wählen]</li>'; continue; }
        $label = trim((string) ($f['label'] ?? '')) ?: Media::displayName($m);
        $pages = Media::pages($m);
        $info = Media::typeLabel($m['mime']) . ($pages ? ' · ' . ($pages === 1 ? lt('1 Seite') : lt('{n} Seiten', ['n' => $pages])) : '') . ' · ' . Media::humanSize((int) $m['size']); ?>
    <li class="download">
      <?= icon($m['mime'] === 'application/pdf' ? 'file-pdf' : 'file') ?>
      <span class="download__main"><span class="download__name"<?= $b->edit("files.$i.label") ?>><?= e($label) ?></span> <span class="download__meta"><?= e($info) ?></span></span>
      <?php if ($m['mime'] === 'application/pdf' && ($view = Media::viewerUrl($m))): ?><a href="<?= e($view) ?>"><?= e(lt('Ansehen')) ?><span class="sr-only">: <?= e($label) ?></span></a><?php endif; ?>
      <a href="<?= e(Media::url($m)) ?>" download><?= e(lt('Herunterladen')) ?><span class="sr-only">: <?= e($label) ?> (<?= e($info) ?>)</span></a>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
