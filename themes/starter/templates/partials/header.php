<?php
/**
 * Kopfbereich: Marke, Hauptnavigation (mit Unterseiten), Sprachumschalter, Suche, Menü-Schaltfläche.
 *
 * Navigationsvarianten (Design → Navigation, theme.php § 6): Klasse nav-bar | nav-center am <html>, Anordnung per CSS.
 * Mobil: Die Schaltfläche „Menü“ öffnet die Navigation als HTML-popover (popovertarget) – ohne JavaScript, Escape und
 * Klick daneben schließen, der Browser meldet aria-expanded. Ohne popover-Unterstützung steht das Menü umbrechend unter
 * der Marke. site.js ergänzt nur: Menü schließt nach Klick auf einen Sprunganker (progressive enhancement).
 * Unterseiten: auf großen Bildschirmen Aufklappliste bei Hover/Fokus (:focus-within), mobil immer ausgeklappt.
 * Aktionen (Suche, Handlungsaufruf, Kontakt-Chip …): header_actions() des Cores (Core\HeaderActions) – was erscheint, stellt
 * die Redaktion unter Design → „Kopfbereich: Suche & Aktionen“ ein (Standard des Start-Kits: gefüllter Button „Kontakt“ + Lupe).
 *   header_actions('bar', ['compact' => true])   in der Leiste; compact = mobil (< 30 em) wandern Text-Aktionen ins Menü
 *   header_actions('center')                     nur bei Anordnung „Suche mittig“ (sonst leer)
 *   header_actions('below', ['wrap' => 'wrap'])  Suchleiste unter dem Kopf („Suchleiste“ / „Suchfeld unter der Navigation“)
 *   header_cta()                                 der Handlungsaufruf als Daten – hier für das mobile Menü
 * Aussehen: Variablen --ha-* und Kit-Regeln in css/site.css; Button-Klassen in theme.php → 'header_actions'. Ganz eigenes
 * Markup: templates/partials/header-actions.php (bekommt $ha, $slot, $opt). Die Suche selbst: Core\Search\Search::form,
 * Vorschläge lädt site.js beim ersten Fokus (data-suggest-js); Taste „/“ bzw. ⌘K springt hinein (header-actions.js).
 * data-cms-sticky: Die Redaktions-Werkzeugleiste schiebt einen mitlaufenden Kopf nach unten.
 */
$menu = \Core\HeaderActions::menu(starter_menu());   // Stil „Menüpunkt“: Ziel des Handlungsaufrufs nicht doppelt im Menü
$langs = language_links();
$actions = header_actions('bar', ['compact' => true]);   // Suche + Handlungsaufruf (Design → Kopfbereich)
$cta = header_cta();                                      // derselbe Handlungsaufruf fürs mobile Menü
$home = url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$logo = (int) setting('logo') ?: null;
$logoDark = (int) setting('logo_dark') ?: null;
$img = $logo ? img($logo, '240px', ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';
$imgDark = $img !== '' && $logoDark ? img($logoDark, '240px', ['eager' => true, 'alt' => '', 'class' => 'brand__img']) : '';   // helle Fassung fürs dunkle Schema
?>
<header class="hdr" data-hdr data-cms-sticky>
  <div class="wrap hdr__bar">
    <a class="brand" href="<?= e($home) ?>"<?= !empty(app()->currentPage['is_home']) ? ' aria-current="page"' : '' ?>>
      <?php if ($img !== ''): ?><span class="brand__logo"><?= $img ?></span><?php if ($imgDark !== ''): ?><span class="brand__logo brand__logo--dark"><?= $imgDark ?></span><?php endif; ?><span class="sr-only"><?= e(starter_name()) ?></span>
      <?php else: ?><span class="brand__name"><?= e(starter_name(true)) ?></span><?php endif; ?>
      <span class="sr-only"> – <?= e(lt('Startseite')) ?></span>
    </a>
    <?= header_actions('center') ?>
    <div class="hdr__tools">
      <?= $actions ?>
      <?php if ($menu || $langs || $cta): ?>
      <button type="button" class="nav-toggle" popovertarget="site-nav">
        <span class="nav-toggle__bars" aria-hidden="true"></span><span><?= e(lt('Menü')) ?></span>
      </button>
      <?php endif; ?>
    </div>
    <?php if ($menu || $langs || $cta): ?>
    <div class="nav" id="site-nav" popover data-nav>
      <button type="button" class="btn btn--secondary nav__close" popovertarget="site-nav" popovertargetaction="hide"><?= e(lt('Schließen')) ?></button>
      <?php if ($menu): ?>
      <nav aria-label="<?= e(lt('Hauptnavigation')) ?>">
        <ul class="nav__list" role="list">
          <?php foreach ($menu as $m): ?>
          <li class="nav__item<?= $m['children'] ? ' has-sub' : '' ?>">
            <a class="nav__link" href="<?= e($m['href']) ?>"<?= starter_is_current($m) ? ' aria-current="page"' : ($m['active'] ? ' data-active' : '') ?>><?= e($m['label']) ?></a>
            <?php if ($m['children']): ?>
            <ul class="nav__sub" role="list">
              <?php foreach ($m['children'] as $c): ?><li><a class="nav__link" href="<?= e($c['href']) ?>"<?= starter_is_current($c) ? ' aria-current="page"' : '' ?>><?= e($c['label']) ?></a></li><?php endforeach; ?>
            </ul>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php endif; ?>
      <?php if ($langs): ?><?= header_actions_lang($langs, 'langs') /* Stil aus Design: Kürzel, Aufklappliste oder Namen */ ?><?php endif; ?>
      <?php if ($cta): ?><a class="btn btn--primary nav__cta" href="<?= e($cta['href']) ?>"<?= ext_attrs($cta['href']) ?>><?= e($cta['label']) ?></a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?= header_actions('below', ['wrap' => 'wrap']) ?>
</header>
