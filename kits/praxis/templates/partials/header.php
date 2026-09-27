<?php
/** Kopfbereich mit Wortmarke, Navigation (aus Blöcken mit „In Navigation“), Telefon, Kontakt. */
$nav = app()->theme->navigation();
$cta = null;
foreach ($nav as $i => $n) {
    if ($n['id'] === 'kontakt') {
        $cta = $n;
    }
}
$desktop = array_values(array_filter($nav, fn($n) => $n['id'] !== 'kontakt'));
$services = praxis_services();
// Seiten mit „Im Menü“ aus dem Seitenbaum (Unterseiten als Aufklappmenü)
$pagesMenu = \Core\Pages::menu(app()->auth->check());
$sub = function (array $items) use (&$sub): string {
    $h = '';
    foreach ($items as $m) {
        $cur = $m['active'] ? ' aria-current="page"' : '';
        if ($m['children']) {
            $h .= '<li class="navsub"><details' . ($m['active'] ? ' data-active' : '') . '><summary>' . e($m['label']) . '</summary><ul>'
                . '<li><a href="' . e($m['href']) . '"' . $cur . '>' . e(lt('Übersicht')) . '</a></li>' . $sub($m['children']) . '</ul></details></li>';
        } else {
            $h .= '<li><a href="' . e($m['href']) . '"' . $cur . '>' . e($m['label']) . '</a></li>';
        }
    }
    return $h;
};
$home = url('/');
?>
<header class="site-header">
  <nav class="site-nav" aria-label="<?= e(lt('Hauptnavigation')) ?>">
    <?php $lp = landing(); /* Landing-Domain (Core\Landings): eigenes Logo bzw. Name + Unterzeile statt der Wortmarke */
      $wm1 = $lp?->name ?? (string) setting('wortmarke_1'); $wm2 = $lp?->name !== null ? (string) $lp->tagline : (string) setting('wortmarke_2'); ?>
    <a class="wordmark" href="<?= e(app()->currentPage['is_home'] ?? false ? '#top' : $home) ?>" aria-label="<?= e(lt('{name} – zum Seitenanfang', ['name' => trim($wm1 . ' ' . $wm2)])) ?>">
      <?php if ($lp?->logo): ?><?= img($lp->logo, '220px', ['eager' => true, 'alt' => '', 'class' => 'wordmark__img']) ?>
      <?php else: ?>
      <span class="wordmark__1"><?= e($wm1) ?><span class="dot">.</span></span>
      <?php if ($wm2 !== ''): ?><span class="wordmark__2"><?= e($wm2) ?></span><?php endif; ?>
      <?php endif; ?>
    </a>
    <div class="site-nav__wide">
      <ul class="navlist">
        <?php foreach ($desktop as $n): ?>
        <li><a href="<?= e($n['href']) ?>" data-spy="<?= e($n['id']) ?>"><?= e($n['label']) ?></a></li>
        <?php endforeach; ?>
        <?= $sub($pagesMenu) ?>
      </ul>
      <div class="site-nav__actions">
        <?= \Core\Search\Search::form('header') /* Website-Suche (Funktion „search“): Lupe → Popover mit Suchfeld */ ?>
        <?php if (praxis_has_phone() || is_editing()): ?>
        <a class="pill" href="<?= e(praxis_phone_href()) ?>">
          <svg aria-hidden="true" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg>
          <?= e($phone) ?>
        </a>
        <?php endif; ?>
        <a class="pill pill--primary" href="<?= e($cta['href'] ?? link_href('#kontakt')) ?>"<?= $cta ? ' data-spy="' . e($cta['id']) . '"' : '' ?>><?= e($cta['label'] ?? lt('Kontakt')) ?></a>
        <?php if ($langs = language_links()): ?>
        <ul class="langswitch" aria-label="Sprache / Language">
          <?php foreach ($langs as $l): ?><li><a href="<?= e($l['url']) ?>" hreflang="<?= e($l['code']) ?>" lang="<?= e($l['code']) ?>"<?= $l['active'] ? ' aria-current="true"' : '' ?> title="<?= e($l['label']) ?>"><?= e(strtoupper($l['code'])) ?></a></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
    </div>
    <button type="button" class="menu-btn" commandfor="mobilmenu" command="show-modal" aria-haspopup="dialog" aria-expanded="false" aria-controls="mobilmenu"><span class="menu-btn__bars" aria-hidden="true"></span><?= e(lt('Menü')) ?></button>
  </nav>
  <?php
  /* Mobilmenü: modaler <dialog> (Vollbild, oberste Ebene – liegt über Kopf und Schnellkontakt-Leiste). Öffnen/Schließen über
     Invoker Commands (command/commandfor) – ohne JavaScript nutzbar; Rest der Seite inert, Escape schließt, Fokus kehrt zur
     Menü-Schaltfläche zurück. site.js: Fallback für ältere Browser, aria-expanded, Schließen beim Klick auf einen Link.
     Aussehen: css/mnav.css – lädt site.js erst beim ersten Öffnen (data-css, vorgeladen beim Zeigen/Fokussieren der
     Schaltfläche); ohne JavaScript bindet layout.php sie per <noscript> ein. */
  $ico = fn(string $d) => '<svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
  $icons = [
      'tel' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
      'termin' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
      'rezept' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 11v6M9 14h6"/>',
      'ueberweisung' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6M13 12l3 3-3 3"/>',
      'anfahrt' => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
  ];
  $acc = function (array $items, int $lvl = 0) use (&$acc): string {
      $h = '';
      foreach ($items as $m) {
          $cur = $m['active'] ? ' aria-current="page"' : '';
          if ($m['children'] && !$lvl) {
              $h .= '<li><details class="mnav__acc"' . ($m['active'] ? ' open data-active' : '') . '><summary>' . e($m['label']) . '<span class="dot">.</span><span class="mnav__chev" aria-hidden="true"></span></summary>'
                  . '<ul class="mnav__sub"><li><a href="' . e($m['href']) . '"' . $cur . '>' . e(lt('Übersicht')) . '</a></li>' . $acc($m['children'], 1) . '</ul></details></li>';
          } elseif ($lvl) {
              $h .= '<li' . ($lvl > 1 ? ' class="mnav__deep"' : '') . '><a href="' . e($m['href']) . '"' . $cur . '>' . e($m['label']) . '</a></li>' . $acc($m['children'], $lvl + 1);
          } else {
              $h .= '<li><a href="' . e($m['href']) . '"' . $cur . '>' . e($m['label']) . '<span class="dot">.</span></a></li>';
          }
      }
      return $h;
  };
  $mail = (string) setting('email');
  ?>
  <dialog id="mobilmenu" class="mnav" aria-label="<?= e(lt('Menü')) ?>" data-css="<?= e(theme_asset('css/mnav.css')) ?>">
    <div class="mnav__head">
      <a class="wordmark" href="<?= e(app()->currentPage['is_home'] ?? false ? '#top' : $home) ?>">
        <span class="wordmark__1"><?= e(setting('wortmarke_1')) ?><span class="dot">.</span></span>
        <span class="wordmark__2"><?= e(setting('wortmarke_2')) ?></span>
      </a>
      <button type="button" class="mnav__close" commandfor="mobilmenu" command="close" autofocus><?= e(lt('Schließen')) ?><svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
    </div>
    <div class="mnav__body">
      <?php if (($searchForm = \Core\Search\Search::form('menu')) !== ''): ?><div class="mnav__search"><?= $searchForm ?></div><?php endif; ?>
      <ul class="mnav__nav">
        <?php foreach ($nav as $n): ?>
        <li><a href="<?= e($n['href']) ?>"><?= e($n['label']) ?><span class="dot">.</span></a></li>
        <?php endforeach; ?>
        <?= $acc($pagesMenu) ?>
      </ul>
      <h2 class="mnav__h"><?= e(lt('Schnellzugriff')) ?></h2>
      <div class="mnav__quick">
        <a href="<?= e(praxis_phone_href()) ?>"><?= $ico($icons['tel']) ?><?= e(lt('Anrufen')) ?></a>
        <?php foreach ($services as $key => $s): ?>
        <a href="<?= e($s['href']) ?>" data-flip="<?= e($key) ?>"<?= ext_attrs($s['href']) ?>><?= $ico($icons[$key] ?? $icons['termin']) ?><?= e($s['label']) ?></a>
        <?php endforeach; ?>
        <a href="<?= e(link_href('#anfahrt')) ?>"><?= $ico($icons['anfahrt']) ?><?= e(lt('Anfahrt')) ?></a>
      </div>
      <div class="mnav__contact">
        <h2 class="mnav__h"><?= e(lt('Kontakt')) ?></h2>
        <?= praxis_open_badge('openb--menu') ?>
        <?= praxis_phone_link('mnav__phone') ?>
        <?php if (filled($mail)): ?><a class="mnav__mail" href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a><?php endif; ?>
        <?php if (($addr = praxis_address_line()) !== ''): ?><p class="mnav__addr"><?= e($addr) ?></p><?php endif; ?>
      </div>
      <?php if ($langs): ?>
      <ul class="mnav__lang" aria-label="Sprache / Language">
        <?php foreach ($langs as $l): ?><li><a href="<?= e($l['url']) ?>" hreflang="<?= e($l['code']) ?>" lang="<?= e($l['code']) ?>"<?= $l['active'] ? ' aria-current="true"' : '' ?>><?= e($l['label']) ?></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </dialog>
</header>
