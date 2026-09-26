<?php
/**
 * Kopfbereich „Minimal“: Logo und Menü-Schaltfläche (auf allen Bildschirmen) → Vollbild-Menü mit großer Schrift,
 * Direktkontakt und Sprache. Geöffnet ist der Rest der Seite inert (Fokus bleibt im Menü), Escape schließt.
 * Ohne JavaScript steht das Menü als einfache Liste unter dem Kopfbereich.
 * @var array $menu  @var array $langs  @var string $brandHref  @var string $actions  @var string $center  @var string $below  @var string $searchMenu
 * Aktionen (Suche, Handlungsaufruf …) stehen neben der Menü-Schaltfläche; mobil verkleinert (compact).
 */
$searchMenu ??= '';
$actions = header_actions('bar', ['compact' => true]);
$address = basis_address_lines();
$social = basis_social();
?>
<header class="site-header site-header--minimal" data-header>
  <div class="wrap site-header__inner">
    <?= app()->theme->partial('brand', ['href' => $brandHref]) ?>
    <?= $center ?>
    <?php if ($actions !== ''): ?><div class="site-header__cta"><?= $actions ?></div><?php endif; ?>
    <?php if ($menu || $langs || $actions !== ''): ?>
    <?= app()->theme->partial('menu-btn') ?>
    <nav id="site-nav" class="site-nav site-nav--overlay" aria-label="<?= e(lt('Hauptnavigation')) ?>">
      <div class="wrap overlay">
        <?= $menu ? basis_nav_list($menu, 'overlay') : '' ?>
        <div class="overlay__aside">
          <?php if ($searchMenu !== ''): ?><div class="overlay__search"><?= $searchMenu ?></div><?php endif; ?>
          <?= basis_quick_contact('overlay__contact') ?>
          <?php if ($address): ?><address class="overlay__address"><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
          <?php if ($social): ?><ul class="overlay__social"><?php foreach ($social as $s): ?><li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener me"><?= e($s['label']) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></li><?php endforeach; ?></ul><?php endif; ?>
          <?php if ($langs): ?><?= header_actions_lang($langs) ?><?php endif; ?>
        </div>
      </div>
    </nav>
    <?php endif; ?>
  </div>
  <?= $below ?>
</header>
