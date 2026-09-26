<?php
/**
 * Kopfbereich „Modern“: schwebende, abgerundete Leiste. Mit „Transparent über dem ersten Abschnitt“ liegt sie über dem
 * Einstieg und wird beim Scrollen deckend (site.js setzt .is-stuck; ohne JavaScript scrollt sie einfach mit).
 * @var array $menu  @var array $langs  @var string $brandHref  @var string $actions  @var string $center  @var string $below  @var string $searchMenu  @var string $over ''|light|dark
 */
$searchMenu ??= '';
$cls = 'site-header site-header--modern' . ($over !== '' ? ' is-over' . ($over === 'dark' ? ' is-over-dark' : '') : '');
?>
<header class="<?= e($cls) ?>" data-header>
  <div class="wrap">
    <div class="site-header__inner">
      <?= app()->theme->partial('brand', ['href' => $brandHref]) ?>
      <?= $center ?>
      <?php if ($menu || $langs || $actions !== ''): ?>
      <?= app()->theme->partial('menu-btn') ?>
      <nav id="site-nav" class="site-nav" aria-label="<?= e(lt('Hauptnavigation')) ?>">
        <?php if ($searchMenu !== ''): ?><div class="site-nav__search"><?= $searchMenu ?></div><?php endif; ?>
        <?= $menu ? basis_nav_list($menu, 'dropdown') : '' ?>
        <?= app()->theme->partial('nav-contact') /* nur im Mobilmenü sichtbar */ ?>
        <?php if ($actions !== ''): ?>
        <div class="site-nav__end">
          <?= $actions ?>
        </div>
        <?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>
    <?= $below ?>
  </div>
</header>
