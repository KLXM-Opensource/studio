<?php
/**
 * Kopfbereich – Variante aus Verwaltung → Design → „Kopfbereich“ (design('header')): bar | centered | split | index.
 *
 * Der Kopf ist ein Container (container-type: inline-size). Die Klasse fit-NN nennt die geschätzte Breite in rem, die
 * das Menü in einer Zeile braucht (essenz_nav_fit); ist der Kopf mindestens so breit, zeigt die Container-Query das Menü
 * in der Leiste, sonst die Menü-Schaltfläche. site.js prüft zusätzlich, ob es wirklich passt (is-overflow).
 * „Minimal“ (index) zeigt immer nur Marke, Suche und die Schaltfläche „Index“.
 * Menü-Schaltfläche → Seitenblatt (#mnav, HTML-popover; Escape/Klick daneben ohne JavaScript, Scroll-Sperre per :has();
 * site.js: Fokusfalle, inerter Rest, Fokus zurück). data-cms-sticky: Die Redaktions-Werkzeugleiste schiebt den Kopf nach unten.
 */
$variant = preg_replace('~[^a-z]~', '', (string) design('header')) ?: 'bar';
$menu = \Core\HeaderActions::menu(essenz_menu());   // Stil „Menüpunkt“: Ziel des Handlungsaufrufs nicht doppelt im Menü
$langs = language_links();
$cta = essenz_header_cta();
// Kopfbereich-Aktionen (Design → „Kopfbereich: Suche & Aktionen“, Core\HeaderActions): Suche, Handlungsaufruf, Kontakt-Chip …
$actions = header_actions('bar', ['compact' => true]);
$searchMenu = \Core\Search\Search::form('menu');
$brandHref = !empty(app()->currentPage['is_home']) ? '#main' : url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$fit = essenz_nav_fit($menu, $cta, $langs, $actions !== '', $variant);
$hasSheet = $menu || $langs || $cta || $searchMenu !== '';
?>
<header class="hdr hdr--<?= e($variant) ?> <?= e($fit) ?>" data-header data-cms-sticky>
  <div class="hdr__bar">
    <?= app()->theme->partial('brand', ['href' => $brandHref, 'class' => 'hdr__brand']) ?>
    <?= header_actions('center') ?>
    <?php if (($menu || $langs) && $variant !== 'index'): ?>
    <div class="hdr__nav">
      <?php if ($menu): ?><nav class="hnav" aria-label="<?= e(lt('Hauptnavigation')) ?>"><?= essenz_nav_inline($menu) ?></nav><?php endif; ?>
      <?php if ($langs): ?><?= header_actions_lang($langs, 'hdr__lang') ?><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="hdr__tools">
      <?= $actions ?>
      <?php if ($hasSheet): ?>
      <button type="button" class="menu-btn" popovertarget="mnav" aria-controls="mnav" aria-haspopup="dialog" aria-expanded="false">
        <span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__label"><?= e($variant === 'index' ? lt('Index') : lt('Menü')) ?></span>
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?= header_actions('below', ['wrap' => 'hdr__below']) ?>
</header>
