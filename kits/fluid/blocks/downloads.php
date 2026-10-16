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
$list = $b->variant() === 'list';
// Variante „Liste“: Symbol je Dateityp, die ganze Zeile öffnet die Datei (PDF im Viewer, der selbst „Herunterladen“ anbietet)
$fileIcon = fn(string $mime) => match (true) {
    $mime === 'application/pdf' => 'file-pdf', str_starts_with($mime, 'image/') => 'image', str_contains($mime, 'zip') => 'file-zip',
    str_contains($mime, 'sheet') || str_contains($mime, 'excel') => 'file-xls', str_contains($mime, 'word') => 'file-doc', default => 'file',
};
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($rows && $list): ?>
  <ul class="downloads downloads--list" role="list">
    <?php foreach ($rows as $row): $m = $row['m']; $pdf = $m && $m['mime'] === 'application/pdf'; $pages = $m ? Media::pages($m) : null;
        $meta = $m ? Media::typeLabel($m['mime']) . ($pages ? ' · ' . ($pages === 1 ? lt('1 Seite') : lt('{n} Seiten', ['n' => $pages])) : '') . ' · ' . Media::humanSize((int) $m['size']) : '';
        $view = $pdf ? Media::viewerUrl($m) : null; ?>
    <li class="dlrow">
      <span class="dlrow__icon" aria-hidden="true"><?= icon($m ? $fileIcon((string) $m['mime']) : 'file') ?></span>
      <span class="dlrow__main">
        <?php if ($m): ?><a class="dlrow__name" href="<?= e($view ?: Media::url($m)) ?>"<?= $view ? '' : ' download' ?><?= $row['path'] ? $b->edit($row['path']) : '' ?>><?= fluid_title((string) $row['label']) ?></a>
        <?php else: ?><span class="dlrow__name"<?= $row['path'] ? $b->edit($row['path']) : '' ?>><?= fluid_title((string) $row['label']) ?></span><?php endif; ?>
        <span class="dlrow__meta"><?= e(implode(' · ', array_filter([$meta, $row['note']]))) ?></span>
      </span>
      <?php if ($m): ?><span class="dlrow__hint" aria-hidden="true"><?= icon($view ? 'eye' : 'download-simple') ?></span>
      <?php if ($view): ?><a class="dlrow__dl" href="<?= e(Media::url($m)) ?>" download><?= icon('download-simple') ?><span class="sr-only"><?= e(lt('{name} herunterladen ({info})', ['name' => strip_emphasis((string) $row['label']), 'info' => $meta])) ?></span></a><?php endif; ?>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif ($rows): ?>
  <ul class="downloads" role="list">
    <?php foreach ($rows as $row): $m = $row['m']; $pdf = $m && $m['mime'] === 'application/pdf'; $pages = $m ? Media::pages($m) : null;
        $meta = $m ? Media::typeLabel($m['mime']) . ($pages ? ' · ' . ($pages === 1 ? lt('1 Seite') : lt('{n} Seiten', ['n' => $pages])) : '') . ' · ' . Media::humanSize((int) $m['size']) : ''; ?>
    <li class="download">
      <span class="download__type" aria-hidden="true"><?= e($m ? Media::typeLabel($m['mime']) : '–') ?></span>
      <span class="download__main">
        <span class="download__name"<?= $row['path'] ? $b->edit($row['path']) : '' ?>><?= fluid_title((string) $row['label']) ?></span>
        <span class="download__meta"><?= e(implode(' · ', array_filter([$meta, $row['note']]))) ?></span>
      </span>
      <?php if ($m): ?>
      <span class="download__actions">
        <?php if ($pdf && ($view = Media::viewerUrl($m))): ?><a class="download__view" href="<?= e($view) ?>"><?= e(lt('Ansehen')) ?><span class="sr-only">: <?= e(strip_emphasis((string) $row['label'])) ?></span></a><?php endif; ?>
        <a class="download__dl" href="<?= e(Media::url($m)) ?>" download><?= icon("download-simple") ?><span class="sr-only"><?= e(lt('{name} herunterladen ({info})', ['name' => strip_emphasis((string) $row['label']), 'info' => $meta])) ?></span></a>
      </span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php elseif (is_editing()): ?><p class="empty-hint">Noch keine Dateien – in der Seitenleiste Dateien oder eine Sammlung wählen.</p><?php endif; ?>
</div>
