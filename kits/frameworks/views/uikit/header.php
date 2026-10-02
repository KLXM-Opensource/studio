<?php
/**
 * Kopf (UIkit): uk-navbar mit Aufklappmenü (uk-navbar-dropdown, öffnet bei Hover und Tastaturfokus), mobil uk-offcanvas
 * über einen Knopf mit uk-toggle. Klebend mit uk-sticky (nur Design-Option „Kopf mitscrollen“).
 * Mit Werkzeugleiste der Redaktion: editor.css schiebt .uk-sticky/.uk-sticky-fixed um --cms-bar-offset nach unten
 * (UIkit setzt top inline, daher dort !important). offset in uk-sticky bleibt 0 – den Versatz macht der Kern.
 * CSP: Symbole aus uikit-icons (uk-icon="menu") – NICHT uk-navbar-toggle-icon/uk-accordion-icon, die bringen ein
 * <style> im SVG mit und lösen ohne 'unsafe-inline' eine CSP-Meldung aus.
 */
$menu = frameworks_menu();
$langs = language_links();
$home = url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$isHome = !empty(app()->currentPage['is_home']);
$sticky = (bool) design('sticky');
?>
<header class="fw-hdr fw-hdr--uk" data-cms-sticky<?= $sticky ? ' uk-sticky="sel-target: .uk-navbar-container; cls-active: uk-navbar-sticky"' : '' ?>>
  <div class="uk-navbar-container">
    <div class="uk-container">
      <div uk-navbar="mode: hover; delay-hide: 300">
        <div class="uk-navbar-left">
          <a class="uk-navbar-item uk-logo" href="<?= e($home) ?>"<?= $isHome ? ' aria-current="page"' : '' ?>>
            <span class="fw-mark" aria-hidden="true"><?= e(mb_substr(frameworks_name(true), 0, 1)) ?></span><?= e(frameworks_name(true)) ?><span class="uk-hidden-visually"> – <?= e(lt('Startseite')) ?></span>
          </a>
        </div>
        <?php if ($menu): ?>
        <div class="uk-navbar-right">
          <nav class="uk-visible@m" aria-label="<?= e(lt('Hauptnavigation')) ?>">
            <ul class="uk-navbar-nav">
              <?php foreach ($menu as $m): $cur = frameworks_is_current($m); ?>
              <li<?= $cur || $m['active'] ? ' class="uk-active"' : '' ?>>
                <a href="<?= e($m['href']) ?>"<?= $cur ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?><?php if ($m['children']): ?> <span uk-navbar-parent-icon></span><?php endif; ?></a>
                <?php if ($m['children']): ?>
                <div class="uk-navbar-dropdown">
                  <ul class="uk-nav uk-navbar-dropdown-nav">
                    <?php foreach ($m['children'] as $c): ?><li<?= frameworks_is_current($c) ? ' class="uk-active"' : '' ?>><a href="<?= e($c['href']) ?>"<?= frameworks_is_current($c) ? ' aria-current="page"' : '' ?>><?= e($c['label']) ?></a></li><?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>
              </li>
              <?php endforeach; ?>
            </ul>
          </nav>
          <button class="uk-navbar-toggle uk-hidden@m fw-toggle" type="button" uk-toggle="target: #fw-offcanvas" aria-controls="fw-offcanvas">
            <span uk-icon="icon: menu"></span><span class="uk-margin-small-left"><?= e(lt('Menü')) ?></span>
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>
<?php if ($menu): ?>
<div id="fw-offcanvas" uk-offcanvas="overlay: true; flip: true">
  <div class="uk-offcanvas-bar fw-offcanvas">
    <button class="uk-offcanvas-close" type="button" uk-close aria-label="<?= e(lt('Schließen')) ?>"></button>
    <nav aria-label="<?= e(lt('Hauptnavigation')) ?>">
      <ul class="uk-nav uk-nav-default">
        <?php foreach ($menu as $m): ?>
        <li<?= frameworks_is_current($m) ? ' class="uk-active"' : '' ?>><a href="<?= e($m['href']) ?>"<?= frameworks_is_current($m) ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a>
          <?php if ($m['children']): ?>
          <ul class="uk-nav-sub">
            <?php foreach ($m['children'] as $c): ?><li><a href="<?= e($c['href']) ?>"<?= frameworks_is_current($c) ? ' aria-current="page"' : '' ?>><?= e($c['label']) ?></a></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <?php if ($langs): ?><?= header_actions_lang($langs, 'uk-subnav uk-margin-top') ?><?php endif; ?>
  </div>
</div>
<?php endif; ?>
