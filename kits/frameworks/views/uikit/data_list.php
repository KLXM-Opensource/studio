<?php
/**
 * Datenliste „Karten“ / „Liste“ (UIkit) – Daten aus blocks/data_list.php ($items, $layout, $cols, $ratio, $hTag, $pager …).
 * Karten: uk-card im uk-grid (Spalten über uk-child-width-*), Liste: uk-list-divider mit Bild links.
 */
$ratioCls = 'r-' . str_replace(':', '-', $ratio);
?>
<div class="uk-container">
  <?php include __DIR__ . '/_head.php'; ?>
  <?php if (!$items): ?>
  <p class="uk-placeholder"><?= e($d['empty_text'] ?: lt('Zurzeit gibt es hier keine Einträge.')) ?></p>
  <?php elseif ($layout === 'cards'): ?>
  <ul class="<?= frameworks_cols($cols, 'uikit') ?> uk-grid-match fw-list" uk-grid>
    <?php foreach ($items as $it): ?>
    <li>
      <div class="uk-card uk-card-default uk-card-hover uk-position-relative fw-card<?= $it['featured'] ? ' fw-featured' : '' ?>"><?= $it['pencil'] ?>
        <?php if ($it['image'] !== null): ?><div class="uk-card-media-top fw-media fw-media--flat <?= e($ratioCls) ?>"><?= $it['image'] ?></div><?php endif; ?>
        <div class="uk-card-body uk-card-small">
          <?php if ($it['meta']): ?><p class="uk-text-meta uk-margin-small"><?= implode('<span aria-hidden="true"> · </span>', $it['meta']) ?></p><?php endif; ?>
          <?php if ($showTitle): ?><<?= $hTag ?> class="uk-card-title uk-margin-small<?= $titleClamp ?>"><?= $it['url'] ? '<a class="uk-link-heading fw-cover" href="' . e($it['url']) . '">' . e($it['title']) . '</a>' : e($it['title']) ?></<?= $hTag ?>><?php endif; ?>
          <?php foreach ($it['texts'] as $tx): ?><div class="uk-margin-small<?= $tx['clamp'] ?>"><?= $tx['html'] ?></div><?php endforeach; ?>
        </div>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <ul class="uk-list uk-list-divider uk-list-large">
    <?php foreach ($items as $it): ?>
    <li class="uk-position-relative"><?= $it['pencil'] ?>
      <div class="uk-grid-small uk-flex-middle" uk-grid>
        <?php if ($it['image'] !== null): ?><div class="uk-width-1-4@s uk-width-1-3"><div class="fw-media <?= e($ratioCls) ?>"><?= $it['image'] ?></div></div><?php endif; ?>
        <div class="uk-width-expand">
          <?php if ($it['meta']): ?><p class="uk-text-meta uk-margin-remove"><?= implode('<span aria-hidden="true"> · </span>', $it['meta']) ?></p><?php endif; ?>
          <?php if ($showTitle): ?><<?= $hTag ?> class="uk-h4 uk-margin-remove<?= $titleClamp ?>"><?= $it['url'] ? '<a class="uk-link-heading" href="' . e($it['url']) . '">' . e($it['title']) . '</a>' : e($it['title']) ?></<?= $hTag ?>><?php endif; ?>
          <?php foreach ($it['texts'] as $tx): ?><div class="uk-margin-small-top<?= $tx['clamp'] ?>"><?= $tx['html'] ?></div><?php endforeach; ?>
        </div>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($pager): ?>
  <nav class="uk-margin-medium-top" aria-label="<?= e(lt('Seiten')) ?>">
    <ul class="uk-pagination uk-flex-between">
      <li><?php if ($pager['prev']): ?><a href="<?= e($pager['prev']) ?>" rel="prev">← <?= e(lt('Neuere')) ?></a><?php endif; ?></li>
      <li class="uk-disabled"><span><?= e(lt('Seite {page} von {pages}', ['page' => $pager['page'], 'pages' => $pager['pages']])) ?></span></li>
      <li><?php if ($pager['next']): ?><a href="<?= e($pager['next']) ?>" rel="next"><?= e(lt('Ältere')) ?> →</a><?php endif; ?></li>
    </ul>
  </nav>
  <?php endif; ?>
  <?php if (!empty($d['more_label']) && !empty($d['more_link'])): ?>
  <p class="uk-margin-medium-top"><a class="uk-button uk-button-primary" href="<?= e(link_href((string) $d['more_link'])) ?>"<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></a></p>
  <?php endif; ?>
  <?= $newButton ?>
</div>
