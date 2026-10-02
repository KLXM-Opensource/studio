<?php
/**
 * Datenliste „Karten“ / „Liste“ (Tailwind) – Daten aus blocks/data_list.php: $items, $layout, $cols, $ratio, $hTag,
 * $titleClamp, $pager, $newButton. Spalten als vollständige Klassennamen (frameworks_cols): Tailwind erzeugt nur Klassen,
 * die wörtlich im Quelltext stehen – „lg:grid-cols-<?= $n ?>“ fände der Scanner nicht.
 * Der Stift je Eintrag ($it['pencil'], nur Redaktion) liegt absolut oben rechts – die Karte braucht position:relative.
 */
$ratioCls = 'r-' . str_replace(':', '-', $ratio);
?>
<div class="wrap">
  <?php include __DIR__ . '/_head.php'; ?>
  <?php if (!$items): ?>
  <p class="rounded-kit border border-dashed border-slate-300 p-6 text-slate-600 dark:border-white/20 dark:text-slate-400"><?= e($d['empty_text'] ?: lt('Zurzeit gibt es hier keine Einträge.')) ?></p>
  <?php elseif ($layout === 'cards'): ?>
  <ul role="list" class="grid gap-6 <?= frameworks_cols($cols, 'tailwind') ?>">
    <?php foreach ($items as $it): ?>
    <li class="relative flex flex-col overflow-hidden rounded-kit border border-slate-200 bg-white shadow-sm motion-safe:transition hover:shadow-md dark:border-white/10 dark:bg-white/5<?= $it['featured'] ? ' ring-2 ring-accent' : '' ?>"><?= $it['pencil'] ?>
      <?php if ($it['image'] !== null): ?><div class="fw-media <?= e($ratioCls) ?> rounded-none bg-slate-100 dark:bg-slate-800"><?= $it['image'] ?></div><?php endif; ?>
      <div class="flex flex-1 flex-col gap-2 p-5">
        <?php if ($it['meta']): ?><p class="text-sm font-medium text-accent"><?= implode('<span aria-hidden="true"> · </span>', $it['meta']) ?></p><?php endif; ?>
        <?php if ($showTitle): ?><<?= $hTag ?> class="text-lg font-semibold<?= $titleClamp ?>"><?= $it['url'] ? '<a class="text-slate-900 no-underline after:absolute after:inset-0 hover:text-accent hover:underline in-[.is-editing]:after:hidden dark:text-white" href="' . e($it['url']) . '">' . e($it['title']) . '</a>' : e($it['title']) ?></<?= $hTag ?>><?php endif; ?>
        <?php foreach ($it['texts'] as $tx): ?><div class="text-slate-600 dark:text-slate-400<?= $tx['clamp'] ?>"><?= $tx['html'] ?></div><?php endforeach; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <ul role="list" class="divide-y divide-slate-200 border-y border-slate-200 dark:divide-white/10 dark:border-white/10">
    <?php foreach ($items as $it): ?>
    <li class="relative flex gap-5 py-5"><?= $it['pencil'] ?>
      <?php if ($it['image'] !== null): ?><div class="fw-media <?= e($ratioCls) ?> w-28 shrink-0 bg-slate-100 sm:w-44 dark:bg-slate-800"><?= $it['image'] ?></div><?php endif; ?>
      <div class="min-w-0">
        <?php if ($it['meta']): ?><p class="text-sm font-medium text-accent"><?= implode('<span aria-hidden="true"> · </span>', $it['meta']) ?></p><?php endif; ?>
        <?php if ($showTitle): ?><<?= $hTag ?> class="text-lg font-semibold<?= $titleClamp ?>"><?= $it['url'] ? '<a class="text-slate-900 underline-offset-4 hover:text-accent hover:underline dark:text-white" href="' . e($it['url']) . '">' . e($it['title']) . '</a>' : e($it['title']) ?></<?= $hTag ?>><?php endif; ?>
        <?php foreach ($it['texts'] as $tx): ?><div class="mt-1 text-slate-600 dark:text-slate-400<?= $tx['clamp'] ?>"><?= $tx['html'] ?></div><?php endforeach; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($pager): ?>
  <nav class="mt-8 flex items-center justify-between gap-4" aria-label="<?= e(lt('Seiten')) ?>">
    <?php if ($pager['prev']): ?><a class="btn btn-secondary" href="<?= e($pager['prev']) ?>" rel="prev">← <?= e(lt('Neuere')) ?></a><?php else: ?><span></span><?php endif; ?>
    <span class="text-sm"><?= e(lt('Seite {page} von {pages}', ['page' => $pager['page'], 'pages' => $pager['pages']])) ?></span>
    <?php if ($pager['next']): ?><a class="btn btn-secondary" href="<?= e($pager['next']) ?>" rel="next"><?= e(lt('Ältere')) ?> →</a><?php endif; ?>
  </nav>
  <?php endif; ?>
  <?php if (!empty($d['more_label']) && !empty($d['more_link'])): ?>
  <p class="mt-10"><a class="btn btn-primary" href="<?= e(link_href((string) $d['more_link'])) ?>"<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></a></p>
  <?php endif; ?>
  <?= $newButton ?>
</div>
