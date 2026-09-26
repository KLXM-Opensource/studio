<?php
/**
 * Kopfbereich „Klassisch“ und „Ausführlich“: optionale Infoleiste, Logo links, Menü rechts.
 * @var array $menu  @var array $langs  @var string $brandHref  @var string $actions  @var string $center  @var string $below  @var string $searchMenu  @var string $mode dropdown|mega  @var string $variant
 */
$searchMenu ??= '';
$topbar = (bool) design('topbar');
?>
<?php if ($topbar): ?><?= app()->theme->partial('topbar', ['langs' => $langs]) ?><?php endif; ?>
<header class="site-header site-header--<?= e($variant) ?>" data-header>
  <div class="wrap site-header__inner">
    <?= app()->theme->partial('brand', ['href' => $brandHref]) ?>
    <?= $center ?>
    <?php if ($menu || $langs || $actions !== ''): ?>
    <?= app()->theme->partial('menu-btn') ?>
    <nav id="site-nav" class="site-nav" aria-label="<?= e(lt('Hauptnavigation')) ?>">
      <?php if ($searchMenu !== ''): ?><div class="site-nav__search"><?= $searchMenu ?></div><?php endif; ?>
      <?= $menu ? basis_nav_list($menu, $mode) : '' ?>
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
</header>
