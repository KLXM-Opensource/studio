<?php
/**
 * Kopfbereich „Seitenleiste mit Menübaum“ (design('header') = sidebar, CSS: opt-header-sidebar.css).
 *
 * Breite Fenster: Leiste links über die volle Höhe (sticky, eigener Bildlauf) – großes Logo oben, Website-Suche,
 * Menübaum mit allen Ebenen (aktueller Zweig offen, aktuelle Seite markiert), unten Handlungsaufruf, Telefon/E-Mail,
 * Social Media und Sprachen. Schmale Fenster: Leiste oben mit Logo und Menü-Schaltfläche → Seitenblatt (#mnav, wie
 * bei allen Kopfbereichen). Umschalten per Container-Query auf .page (Breite der Seite, nicht des Bildschirms).
 * Optionen: Breite (sw-*), Unterseiten (st-*), Hintergrund (hbg-*), Logo-Größe (logo-*), Farbwirkung (pal-*),
 * Kopfbanner (sb-off|band|image, Höhe sbh-*): Banner über die volle Breite mit Logo, Claim und Handlungsaufruf – die
 * Seitenleiste (.side--below) beginnt darunter und bleibt beim Scrollen oben stehen.
 */
$menu = \Core\HeaderActions::menu(fluid_menu());
$langs = language_links();
$cta = fluid_header_cta();
$brandHref = !empty(app()->currentPage['is_home']) ? '#main' : url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$search = (string) design('ha_search') !== 'none' ? \Core\Search\Search::form('side') : '';
$social = fluid_social();
$quick = fluid_quick_contact('quick side__quick');
$hasSheet = $menu || $langs || $cta || \Core\Search\Search::form('menu') !== '';
// Kopfbanner über die volle Breite (Design → „Kopfbanner über der Seitenleiste“): Logo, Claim und Handlungsaufruf im Banner,
// die Seitenleiste beginnt darunter (Suche, Menübaum, Kontakt). Auf jeder Seite gleich; auf Unterseiten etwas niedriger.
$banner = (string) design('side_banner');
$bannerImg = $banner === 'image' && ($bid = (int) setting('banner_image')) ? img($bid, '100vw', ['eager' => true, 'alt' => '', 'class' => 'side-banner__img']) : '';
if ($banner === 'band' || $banner === 'image'):
    $claim = trim((string) setting('tagline'));
?>
<header class="hdr side-banner side-banner--<?= e($banner) ?><?= $bannerImg !== '' ? ' side-banner--has-img' : '' ?>" data-header>
  <?php if ($bannerImg !== ''): ?><div class="side-banner__media" aria-hidden="true"><?= $bannerImg ?></div><?php endif; ?>
  <div class="side-banner__in">
    <div class="side-banner__id">
      <?= app()->theme->partial('brand', ['href' => $brandHref, 'class' => 'hdr__brand side-banner__brand']) ?>
      <?php if ($claim !== ''): ?><p class="side-banner__claim"><?= fluid_title($claim) ?></p><?php endif; ?>
    </div>
    <div class="side-banner__tools">
      <?php if ($cta): ?><a class="btn btn--primary side-banner__cta" <?= fluid_link_attrs($cta['link']) ?>><?= e($cta['label']) ?></a><?php endif; ?>
      <?php if ($hasSheet): ?>
      <button type="button" class="menu-btn side__menu" popovertarget="mnav" aria-controls="mnav" aria-haspopup="dialog">
        <span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__label"><?= e(lt('Menü')) ?></span>
      </button>
      <?php endif; ?>
    </div>
  </div>
</header>
<?php if ($menu || $search !== '' || $quick !== '' || $social || $langs): ?>
<div class="side side--below">
  <div class="side__in">
    <?php if ($search !== ''): ?><div class="side__search"><?= $search ?></div><?php endif; ?>
    <?php if ($menu): ?><nav class="side__nav snav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><?= fluid_nav_tree($menu) ?></nav><?php endif; ?>
    <?php if ($quick !== '' || $social || $langs): ?>
    <div class="side__foot">
      <?= $quick ?>
      <?php if ($social): ?>
      <ul class="side__social" role="list" aria-label="<?= e(lt('Social Media')) ?>">
        <?php foreach ($social as $s): ?><li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener me"><?= e($s['label']) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($langs): ?><?= header_actions_lang($langs, 'side__lang') ?><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php return; endif; ?>
<header class="hdr hdr--sidebar side" data-header data-side>
  <div class="side__in">
    <div class="side__top">
      <?= app()->theme->partial('brand', ['href' => $brandHref, 'class' => 'hdr__brand side__brand']) ?>
      <?php if ($hasSheet): ?>
      <button type="button" class="menu-btn side__menu" popovertarget="mnav" aria-controls="mnav" aria-haspopup="dialog">
        <span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__label"><?= e(lt('Menü')) ?></span>
      </button>
      <?php endif; ?>
    </div>
    <?php if ($search !== ''): ?><div class="side__search"><?= $search ?></div><?php endif; ?>
    <?php if ($menu): ?><nav class="side__nav snav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><?= fluid_nav_tree($menu) ?></nav><?php endif; ?>
    <?php if ($cta || $quick !== '' || $social || $langs): ?>
    <div class="side__foot">
      <?php if ($cta): ?><a class="btn btn--primary side__cta" <?= fluid_link_attrs($cta['link']) ?>><?= e($cta['label']) ?></a><?php endif; ?>
      <?= $quick ?>
      <?php if ($social): ?>
      <ul class="side__social" role="list" aria-label="<?= e(lt('Social Media')) ?>">
        <?php foreach ($social as $s): ?><li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener me"><?= e($s['label']) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($langs): ?><?= header_actions_lang($langs, 'side__lang') ?><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</header>
