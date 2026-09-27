<?php /** Downloads (B12) – einzelne Dateien oder eine ganze Sammlung; PDFs mit Viewer (PDF.js). @var \Core\Block $b  @var array $d */
use Core\Media;

$rows = [];
if (($d['source'] ?? 'manual') === 'collection' && !empty($d['collection'])) {
    foreach (Media::all(['collection' => (int) $d['collection']]) as $m) {
        $rows[] = ['m' => $m, 'label' => Media::displayName($m), 'note' => '', 'path' => null];
    }
} else {
    foreach ($d['files'] as $i => $f) {
        $m = media($f['file'] ?? null);
        $rows[] = ['m' => $m, 'label' => trim((string) ($f['label'] ?? '')) ?: ($m ? Media::displayName($m) : praxis_note(lt('[Datei]'))),
            'note' => (string) ($f['note'] ?? ''), 'path' => "files.$i.label"];
    }
}
?>
<div class="wrap split split--start">
  <div>
    <?= praxis_heading($b, 'h2', 'h2 h2--m') ?>
    <?php if (!empty($d['intro'])): ?><p class="muted downloads__intro"<?= $b->edit('intro') ?>><?= e($d['intro']) ?></p><?php endif; ?>
  </div>
  <ul class="downloads">
    <?php foreach ($rows as $row): $m = $row['m']; $pdf = $m && $m['mime'] === 'application/pdf'; $pages = $m ? Media::pages($m) : null; ?>
    <li class="downloads__item" data-reveal="up">
      <span class="downloads__icon" aria-hidden="true"><?= e($m ? Media::typeLabel($m['mime']) : 'PDF') ?></span>
      <span class="downloads__main">
        <span class="downloads__name"<?= $row['path'] ? $b->edit($row['path']) : '' ?>><?= e($row['label']) ?></span>
        <span class="downloads__meta"><?= $m ? e(Media::typeLabel($m['mime'])) . ($pages ? ' · ' . e($pages === 1 ? lt('1 Seite') : lt('{n} Seiten', ['n' => $pages])) : '') . ' · ' . e(Media::humanSize((int) $m['size'])) : 'PDF' . e(praxis_note(lt('[Größe]'))) ?><?= $row['note'] !== '' ? ' · ' . e($row['note']) : '' ?></span>
      </span>
      <?php if ($m): ?>
      <span class="downloads__actions">
        <?php if ($pdf && !empty($d['show_viewer'])): ?><a class="downloads__view" href="<?= e(Media::viewerUrl($m)) ?>"><?= e(lt('Ansehen')) ?><span class="sr-only">: <?= e($row['label']) ?></span></a><?php endif; ?>
        <a class="downloads__dl" href="<?= e(Media::url($m)) ?>" download aria-label="<?= e(lt('{name} herunterladen ({type}, {size})', ['name' => $row['label'], 'type' => Media::typeLabel($m['mime']), 'size' => Media::humanSize((int) $m['size'])])) ?>"><span aria-hidden="true">↓</span></a>
      </span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
    <?php if (!$rows && is_editing()): ?><li class="downloads__item"><span class="downloads__main muted"><?= e(__('Noch keine Dateien – in der Seitenleiste Dateien oder eine Sammlung wählen.')) ?></span></li><?php endif; ?>
  </ul>
</div>
