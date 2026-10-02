<?php
/**
 * Kopf (Tailwind): Desktop-Navigation mit Aufklappliste (CSS: group-hover / group-focus-within, ohne Skript),
 * mobil ein HTML-popover (popovertarget – öffnet ohne Skript, Esc und Klick daneben schließen; liegt im Top Layer und
 * damit auch über der Werkzeugleiste der Redaktion).
 * data-cms-sticky: Mit Werkzeugleiste setzt der Kern den Kopf unter die Leiste (editor.css, --cms-bar-offset).
 */
$menu = frameworks_menu();
$langs = language_links();
$home = url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$isHome = !empty(app()->currentPage['is_home']);
$link = 'block rounded-md px-1 py-2 font-medium text-slate-700 no-underline decoration-2 underline-offset-8 hover:text-accent hover:underline aria-[current=page]:text-accent aria-[current=page]:underline data-active:text-accent dark:text-slate-200';
?>
<header class="fw-hdr border-b border-slate-200 bg-white/95 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-950/95" data-cms-sticky>
  <div class="wrap flex min-h-16 items-center gap-6">
    <a class="mr-auto text-lg font-extrabold tracking-tight text-slate-900 no-underline dark:text-white" href="<?= e($home) ?>"<?= $isHome ? ' aria-current="page"' : '' ?>>
      <span class="mr-1 inline-grid size-8 place-items-center rounded-lg bg-accent align-middle text-sm text-on-accent" aria-hidden="true"><?= e(mb_substr(frameworks_name(true), 0, 1)) ?></span>
      <?= e(frameworks_name(true)) ?><span class="sr-only"> – <?= e(lt('Startseite')) ?></span>
    </a>
    <?php if ($menu): ?>
    <nav class="hidden md:block" aria-label="<?= e(lt('Hauptnavigation')) ?>">
      <ul role="list" class="flex items-center gap-5">
        <?php foreach ($menu as $m): ?>
        <li class="group relative">
          <a class="<?= $link ?>" href="<?= e($m['href']) ?>"<?= frameworks_is_current($m) ? ' aria-current="page"' : ($m['active'] ? ' data-active' : '') ?>><?= e($m['label']) ?><?php if ($m['children']): ?> <?= icon('caret-down', ['class' => 'inline size-3.5 motion-safe:transition-transform group-hover:rotate-180 group-focus-within:rotate-180']) ?><?php endif; ?></a>
          <?php if ($m['children']): ?>
          <ul role="list" class="invisible absolute top-full left-0 z-50 min-w-56 translate-y-1 rounded-lg border border-slate-200 bg-white p-2 opacity-0 shadow-lg motion-safe:transition group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 dark:border-slate-700 dark:bg-slate-900">
            <?php foreach ($m['children'] as $c): ?><li><a class="block rounded-md px-3 py-2 text-slate-700 no-underline hover:bg-slate-100 aria-[current=page]:font-semibold aria-[current=page]:text-accent dark:text-slate-200 dark:hover:bg-slate-800" href="<?= e($c['href']) ?>"<?= frameworks_is_current($c) ? ' aria-current="page"' : '' ?>><?= e($c['label']) ?></a></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-slate-300 px-3 font-semibold text-slate-900 md:hidden dark:border-slate-600 dark:text-white" popovertarget="fw-nav">
      <?= icon('list', ['class' => 'size-5']) ?><span><?= e(lt('Menü')) ?></span>
    </button>
    <div id="fw-nav" popover class="fixed inset-y-0 right-0 left-auto m-0 h-dvh w-[min(22rem,100%)] overflow-y-auto border-0 border-l border-slate-200 bg-white p-6 pt-20 text-slate-700 shadow-2xl backdrop:bg-slate-950/50 md:hidden dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200" data-fw-popnav>
      <button type="button" class="btn btn-secondary absolute top-4 right-4" popovertarget="fw-nav" popovertargetaction="hide"><?= icon('x') ?> <?= e(lt('Schließen')) ?></button>
      <nav aria-label="<?= e(lt('Hauptnavigation')) ?>">
        <ul role="list" class="divide-y divide-slate-200 dark:divide-slate-800">
          <?php foreach ($menu as $m): ?>
          <li class="py-1">
            <a class="<?= $link ?> text-lg" href="<?= e($m['href']) ?>"<?= frameworks_is_current($m) ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a>
            <?php if ($m['children']): ?>
            <ul role="list" class="mb-2 ml-4">
              <?php foreach ($m['children'] as $c): ?><li><a class="<?= $link ?>" href="<?= e($c['href']) ?>"<?= frameworks_is_current($c) ? ' aria-current="page"' : '' ?>><?= e($c['label']) ?></a></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php if ($langs): ?><?= header_actions_lang($langs, 'mt-6 flex gap-4 font-semibold') ?><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</header>
