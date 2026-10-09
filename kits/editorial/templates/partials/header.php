<?php
/**
 * Kopfbereich – Variante aus Verwaltung → Design → Kopf & Navigation (design('header'), Klasse head-{variante} am <html>):
 *   masthead  Datumszeile, Name groß zentriert (Titelkopf), darunter die Rubriken-Leiste (bleibt beim Scrollen oben)
 *   compact   eine schmale Leiste: Wortmarke, Rubriken, Suche
 *   split     Wortmarke + Werkzeuge, darunter Rubriken als Reiter; mobil als waagerecht wischbare Reiterleiste
 *   ressorts  Wortmarke, Knopf „Ressorts“ (großes Menü mit allen Rubriken und Unterseiten), Rubriken als Schnellzugriff
 * Mobil (< 960 px) öffnet die Menü-Schaltfläche ein Blatt mit Suche, Rubriken (Akkordeon), Sprache und Button:
 * Fokus bleibt im Blatt, Rest der Seite inert, Seite gesperrt, Escape schließt (js/site.js). Ohne JavaScript steht das Menü unter dem Kopf.
 */
$variant = editorial_header();
$menu = \Core\HeaderActions::menu(editorial_menu());   // Stil „Menüpunkt“: Ziel des Handlungsaufrufs nicht doppelt im Menü
$langs = language_links();
$home = !empty(app()->currentPage['is_home']) ? '#main' : url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
// Kopfbereich-Aktionen (Design → „Kopfbereich: Suche & Aktionen“, Core\HeaderActions): Befehlsfeld „Suchen … ⌘K“,
// Newsletter als Textlink u. a. – mobil im Menü-Blatt (dort auch das Feld der Website-Suche)
$actions = header_actions('bar', ['lang' => $langs]);
$searchMenu = \Core\Search\Search::form('menu');
$tagline = trim((string) setting('tagline'));
$dateline = design('dateline') && in_array($variant, ['masthead', 'split'], true);
?>
<?php if ($dateline): ?>
<div class="dateline"><div class="wrap dateline__inner"><?= editorial_dateline() ?><?php if ($variant === 'split' && $tagline !== ''): ?><p class="dateline__claim"><?= emphasis($tagline) ?></p><?php endif; ?></div></div>
<?php endif; ?>
<?php if ($variant === 'masthead'): ?>
<div class="masthead">
  <div class="wrap masthead__inner">
    <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--mast']) ?>
    <?php if ($tagline !== ''): ?><p class="masthead__claim"><?= emphasis($tagline) ?></p><?php endif; ?>
  </div>
</div>
<?php endif; ?>
<header class="site-header site-header--<?= e($variant) ?>" data-header>
  <div class="wrap site-header__inner">
    <?= app()->theme->partial('brand', ['href' => $home, 'class' => $variant === 'masthead' ? 'brand--mini' : '']) ?>
    <?= header_actions('center') ?>
    <?php if ($variant === 'ressorts' && $menu): ?><?= app()->theme->partial('ressorts', ['menu' => $menu]) ?><?php endif; ?>
    <?php if ($variant === 'ressorts' && $menu): ?>
    <nav class="strip strip--quick" aria-label="<?= e(lt('Schnellzugriff')) ?>"><?= editorial_tab_links($menu) ?></nav>
    <?php endif; ?>
    <?php if ($menu || $langs || $actions !== ''): ?>
    <?= app()->theme->partial('menu-btn') ?>
    <nav id="site-nav" class="site-nav" aria-label="<?= e(lt('Hauptnavigation')) ?>">
      <?php if ($searchMenu !== ''): ?><div class="site-nav__search"><?= $searchMenu ?></div><?php endif; ?>
      <?= $menu ? editorial_nav_list($menu) : '' ?>
      <?php if ($actions !== ''): ?><div class="site-nav__end"><?= $actions ?></div><?php endif; ?>
    </nav>
    <?php endif; ?>
  </div>
  <?php if ($variant === 'split' && $menu): ?>
  <nav class="strip strip--tabs" aria-label="<?= e(lt('Rubriken')) ?>"><div class="wrap"><?= editorial_tab_links($menu) ?></div></nav>
  <?php endif; ?>
<?= header_actions('below', ['wrap' => 'wrap']) ?>
</header>
