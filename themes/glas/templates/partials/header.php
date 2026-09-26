<?php
/**
 * Kopfbereich – Variante aus Verwaltung → Design → „Kopfbereich“ (design('header')): dock | floating | bar | centered | index.
 *
 * „Glas-Dock“ (dock, Standard): eine mittig schwebende Glasinsel – Marke als Glasperle links, Menü mit gleitender
 * Glaslinse in der Mitte, rechts der Bereich „Aktionen“ (.dock__actions: Suche, Button im Kopf, Menü-Schaltfläche –
 * hier docken auch künftige Kopfbereich-Aktionen an). Beim Scrollen verdichtet sich das Dock (site.js setzt is-condensed,
 * Höhe des Kopfs bleibt gleich → keine Verschiebung). Unterseiten öffnen ein Glaspanel mit Symbol und Kurzbeschreibung.
 * Auf Telefonen übernimmt die Tab-Leiste unten (partials/tabbar.php) das Menü.
 * Die übrigen Varianten: schwebende Leiste, Leiste über die volle Breite, zentriert, minimal.
 *
 * Der Kopf ist ein Container (container-type: inline-size). Die Klasse fit-NN nennt die geschätzte Breite in rem, die
 * das Menü in einer Zeile braucht (glas_nav_fit); ist der Kopf mindestens so breit, zeigt die Container-Query das Menü
 * in der Leiste, sonst die Menü-Schaltfläche. site.js prüft zusätzlich, ob es wirklich passt (is-overflow).
 * Menü-Schaltfläche → Glasblatt (#mnav, HTML-popover; Escape/Klick daneben ohne JavaScript, Scroll-Sperre per :has();
 * site.js: Fokusfalle, inerter Rest, Fokus zurück). data-cms-sticky: Die Redaktions-Werkzeugleiste schiebt den Kopf nach unten.
 */
$variant = preg_replace('~[^a-z]~', '', (string) design('header')) ?: 'dock';
$dock = $variant === 'dock';
$menu = \Core\HeaderActions::menu(glas_menu());   // Stil „Menüpunkt“: Ziel des Handlungsaufrufs nicht doppelt im Menü
$langs = language_links();
$cta = glas_header_cta();
// Kopfbereich-Aktionen (Design → „Kopfbereich: Suche & Aktionen“, Core\HeaderActions) – im Dock in .dock__actions:
// Standard Befehlsfeld aus Glas + Glastaste. Telefone (Dock): Suche und Aktion im Glasblatt „Mehr“ (dock-tabbar.css blendet sie hier aus)
$actions = header_actions('bar', ['compact' => !$dock]);
$searchMenu = \Core\Search\Search::form('menu');
$brandHref = !empty(app()->currentPage['is_home']) ? '#main' : url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$fit = glas_nav_fit($menu, $cta, $langs, $actions !== '', $variant);
$hasSheet = $menu || $langs || $cta || $searchMenu !== '';
?>
<header class="hdr hdr--<?= e($variant) ?> <?= e($fit) ?>" data-header data-cms-sticky>
  <div class="hdr__bar<?= $dock ? ' dock' : '' ?>"<?= $dock ? ' data-dock' : '' ?>>
    <?= app()->theme->partial('brand', ['href' => $brandHref, 'class' => 'hdr__brand']) ?>
    <?= header_actions('center') ?>
    <?php if (($menu || $langs) && $variant !== 'index'): ?>
    <div class="hdr__nav">
      <?php if ($menu): ?><nav class="hnav<?= $dock ? ' dnav' : '' ?>" aria-label="<?= e(lt('Hauptnavigation')) ?>"<?= $dock ? ' data-lens' : '' ?>><?= glas_nav_inline($menu, $dock) ?><?php if ($dock): ?><span class="dnav__lens" aria-hidden="true"></span><?php endif; ?></nav><?php endif; ?>
      <?php if ($langs): ?><?= header_actions_lang($langs, 'hdr__lang') ?><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="hdr__tools<?= $dock ? ' dock__actions' : '' ?>">
      <?= $actions ?>
      <?php if ($hasSheet): ?>
      <button type="button" class="menu-btn" popovertarget="mnav" aria-controls="mnav" aria-haspopup="dialog" aria-expanded="false">
        <span class="menu-btn__bars" aria-hidden="true"></span><span class="menu-btn__label"><?= e(lt('Menü')) ?></span>
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?= header_actions('below', ['wrap' => 'hdr__below']) ?>
</header>
<?php if ($dock && $hasSheet): ?><?= app()->theme->partial('tabbar', ['menu' => $menu]) ?><?php endif; ?>
