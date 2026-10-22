<?php
/**
 * Downloads – einzelne Dateien oder eine ganze Sammlung; PDFs mit Viewer (PDF.js).
 * Variante „Liste“: kleines Vorschaubild je Datei (Bild selbst, erste PDF-Seite, Videobild – sonst Symbol je Dateityp), die ganze
 * Zeile öffnet die Datei; Werkzeugleiste (js/downloads.js, ohne JavaScript verborgen): Suche, Umschalter Liste/Kacheln und – auf
 * Wunsch – Filter nach Schlagworten der Mediathek. Optional nur Dateien mit bestimmten Schlagworten (Feld „tags“, beide Varianten).
 * @var \Core\Block $b  @var array $d
 */
use Core\Media;

$rows = [];
if (($d['source'] ?? 'manual') === 'collection' && !empty($d['collection'])) {
    foreach (Media::all(['collection' => (int) $d['collection']]) as $m) {
        $rows[] = ['m' => $m, 'label' => Media::displayName($m), 'note' => '', 'path' => null];
    }
} else {
    foreach ((array) ($d['files'] ?? []) as $i => $f) {
        $m = media($f['file'] ?? null);
        if (!$m && !is_editing()) continue;
        $rows[] = ['m' => $m, 'label' => trim((string) ($f['label'] ?? '')) ?: ($m ? Media::displayName($m) : '[Datei wählen]'),
            'note' => (string) ($f['note'] ?? ''), 'path' => "files.$i.label"];
    }
}
// Nur Dateien mit einem dieser Schlagworte (Mediathek), z. B. „satzung, protokoll“
$only = array_values(array_filter(array_map(fn($t) => mb_strtolower(trim($t)), explode(',', (string) ($d['tags'] ?? '')))));
if ($only) $rows = array_values(array_filter($rows, fn($r) => !$r['m'] || array_intersect($only, Media::tagList($r['m']['tags'] ?? ''))));
$list = $b->variant() === 'list';
$fileIcon = fn(string $mime) => match (true) {
    $mime === 'application/pdf' => 'file-pdf', str_starts_with($mime, 'image/') => 'image', str_contains($mime, 'zip') => 'file-zip',
    str_contains($mime, 'sheet') || str_contains($mime, 'excel') => 'file-xls', str_contains($mime, 'word') => 'file-doc', default => 'file',
};
?>
<div class="wrap">
  <?= fluid_head($b) ?>
  <?php if ($rows && $list):
    // Schlagworte der gezeigten Dateien für den Filter – nur sinnvoll ab zwei verschiedenen
    $tagsOf = fn(array $r) => $r['m'] ? Media::tagList($r['m']['tags'] ?? '') : [];
    $allTags = [];
    if (!empty($d['tag_filter'])) {
        foreach ($rows as $r) foreach ($tagsOf($r) as $t) $allTags[$t] = ($allTags[$t] ?? 0) + 1;
        ksort($allTags, SORT_NATURAL | SORT_FLAG_CASE);
        // Nur diese Schlagworte als Filter (in dieser Reihenfolge) – interne Schlagworte der Mediathek bleiben so verborgen
        $chosen = array_values(array_filter(array_map(fn($t) => mb_strtolower(trim($t)), explode(',', (string) ($d['filter_tags'] ?? '')))));
        if ($chosen) $allTags = array_filter(array_replace(array_fill_keys($chosen, 0), array_intersect_key($allTags, array_flip($chosen))));
        if (count($allTags) < 2) $allTags = [];
    }
    $tools = ($d['tools'] ?? true) !== false && count($rows) > 2;
    $tiles = ($d['view'] ?? 'list') === 'tiles';
    $uid = 'dl-' . substr(md5($b->id), 0, 8); ?>
  <div class="dlbox" data-downloads data-view="<?= $tiles ? 'tiles' : 'list' ?>">
    <?php if ($tools || $allTags): ?>
    <div class="dlbar" hidden>
      <?php if ($tools): ?>
      <label class="dlbar__search">
        <span class="sr-only"><?= e(lt('Downloads durchsuchen')) ?></span>
        <?= icon('magnifying-glass') ?>
        <input type="search" placeholder="<?= e(lt('Downloads durchsuchen …')) ?>" autocomplete="off" aria-controls="<?= $uid ?>" data-dl-search>
      </label>
      <div class="dlbar__views" role="group" aria-label="<?= e(lt('Darstellung')) ?>">
        <button type="button" class="dlbar__view" data-dl-view="list" aria-pressed="<?= $tiles ? 'false' : 'true' ?>"><?= icon('list') ?><span><?= e(lt('Liste')) ?></span></button>
        <button type="button" class="dlbar__view" data-dl-view="tiles" aria-pressed="<?= $tiles ? 'true' : 'false' ?>"><?= icon('squares-four') ?><span><?= e(lt('Kacheln')) ?></span></button>
      </div>
      <?php endif; ?>
      <?php if ($allTags): ?>
      <div class="dlbar__tags" role="group" aria-label="<?= e(lt('Nach Schlagwort filtern')) ?>">
        <button type="button" class="dlbar__tag" data-dl-tag="" aria-pressed="true"><?= e(lt('Alle')) ?></button>
        <?php foreach ($allTags as $t => $n): ?>
        <button type="button" class="dlbar__tag" data-dl-tag="<?= e($t) ?>" aria-pressed="false" aria-controls="<?= $uid ?>"><?= e(mb_strtoupper(mb_substr($t, 0, 1)) . str_replace(['-', '_'], ' ', mb_substr($t, 1))) ?> <span class="dlbar__n"><?= (int) $n ?></span></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <p class="dlbar__count" aria-live="polite" data-dl-count data-of="<?= e(lt('{n} von {total} Dateien')) ?>" data-none="<?= e(lt('Keine Datei gefunden.')) ?>"></p>
    </div>
    <?php endif; ?>
  <ul class="downloads downloads--list<?= $tiles ? ' downloads--tiles' : '' ?>" role="list" id="<?= $uid ?>">
    <?php foreach ($rows as $row): $m = $row['m']; $pdf = $m && $m['mime'] === 'application/pdf'; $pages = $m ? Media::pages($m) : null;
        $meta = $m ? Media::typeLabel($m['mime']) . ($pages ? ' · ' . ($pages === 1 ? lt('1 Seite') : lt('{n} Seiten', ['n' => $pages])) : '') . ' · ' . Media::humanSize((int) $m['size']) : '';
        $view = $pdf ? Media::viewerUrl($m) : null;
        // Vorschau (Liste klein, Kacheln groß): Bild selbst, sonst automatisches Vorschaubild (erste PDF-Seite, Videobild); ohne Vorschau Symbol je Dateityp
        $still = $m ? (str_starts_with((string) $m['mime'], 'image/') ? $m : Media::autoPoster($m)) : null;
        $thumb = $still ? Media::pictureOf($still, ($tiles ? '(min-width: 40rem) 16rem, 50vw' : '6rem'), ['alt' => '', 'eager' => false, 'fit' => false, 'class' => 'dlrow__img']) : ''; ?>
    <li class="dlrow" data-q="<?= e(mb_strtolower(strip_emphasis((string) $row['label']) . ' ' . $row['note'] . ' ' . $meta)) ?>"<?= $allTags ? ' data-tags="' . e(implode(',', array_intersect($tagsOf($row), array_keys($allTags)))) . '"' : '' ?>>
      <span class="dlrow__thumb<?= $thumb ? '' : ' dlrow__thumb--none' ?>" aria-hidden="true"><?= $thumb ?: icon($m ? $fileIcon((string) $m['mime']) : 'file') ?></span>
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
  </div>
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
  <?php elseif (is_editing()): ?><p class="empty-hint"><?= $only ? 'Keine Datei mit diesen Schlagworten – Schlagworte in der Mediathek oder im Feld „Nur Dateien mit Schlagworten“ prüfen.' : 'Noch keine Dateien – in der Seitenleiste Dateien oder eine Sammlung wählen.' ?></p><?php endif; ?>
</div>
