<?php /** Administration → Weiterleitungen: Reiter (Liste | 404-Protokoll). @var string $tab  @var int $nRules  @var int $n404 */ ?>
<nav class="adm-filter rv-tabs" aria-label="<?= e(__('Bereich')) ?>">
  <a href="<?= e(url('/admin/weiterleitungen')) ?>"<?= $tab === 'list' ? ' aria-current="page"' : '' ?>><?= e(__('Weiterleitungen')) ?> <small class="rv-tabs__n"><?= (int) $nRules ?></small></a>
  <a href="<?= e(url('/admin/weiterleitungen/404')) ?>"<?= $tab === '404' ? ' aria-current="page"' : '' ?>><?= e(__('Nicht gefunden (404)')) ?> <small class="rv-tabs__n"><?= (int) $n404 ?></small></a>
</nav>
