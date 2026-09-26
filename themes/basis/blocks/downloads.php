<?php /** Downloads – einzelne Dateien oder eine ganze Sammlung; PDFs mit Viewer (PDF.js). @var \Core\Block $b  @var array $d */
use Core\Media;

$rows = [];
if (($d['source'] ?? 'manual') === 'collection' && !empty($d['collection'])) {
    foreach (Media::all(['collection' => (int) $d['collection']]) as $m) {
        $rows[] = ['m' => $m, 'label' => Media::displayName($m), 'note' => '', 'path' => null];
    }
} else {
    foreach ($d['files'] as $i => $f) {
        $m = media($f['file'] ?? null);
        if (!$m && !is_editing()) continue;
        $rows[] = ['m' => $m, 'label' => trim((string) ($f['label'] ?? '')) ?: ($m ? Media::displayName($m) : '[Datei wählen]'),
            'note' => (string) ($f['note'] ?? ''), 'path' => "files.$i.label"];
    }
}
?>
<div class="wrap">
  <?= basis_head($b) ?>
  <?php if ($rows): ?>
  <ul class="downloads" role="list">
    <?php foreach ($rows as $row): $m = $row['m']; $pdf = $m && $m['mime'] === 'application/pdf'; $pages = $m ? Media::pages($m) : null;
        $meta = $m ? Media::typeLabel($m['mime']) . ($pages ? ' · ' . ($pages === 1 ? lt('1 Seite') : lt('{n} Seiten', ['n' => $pages])) : '') . ' · ' . Media::humanSize((int) $m['size']) : ''; ?>
    <li class="download">
      <span class="download__type" aria-hidden="true"><?= e($m ? Media::typeLabel($m['mime']) : '–') ?></span>
      <span class="download__main">
        <span class="download__name"<?= $row['path'] ? $b->edit($row['path']) : '' ?>><?= e($row['label']) ?></span>
        <span class="download__meta"><?= e(implode(' · ', array_filter([$meta, $row['note']]))) ?></span>
      </span>
      <?php if ($m): ?>
      <span class="download__actions">
        <?php if ($pdf && !empty($d['show_viewer']) && ($view = Media::viewerUrl($m))): ?><a class="download__view" href="<?= e($view) ?>"><?= e(lt('Ansehen')) ?><span class="sr-only">: <?= e($row['label']) ?></span></a><?php endif; ?>
        <a class="download__dl" href="<?= e(Media::url($m)) ?>" download><?= basis_icon('download') ?><span class="sr-only"><?= e(lt('{name} herunterladen ({info})', ['name' => $row['label'], 'info' => $meta])) ?></span></a>
      </span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Dateien – in der Seitenleiste Dateien oder eine Sammlung wählen.</p><?php endif; ?>
</div>
