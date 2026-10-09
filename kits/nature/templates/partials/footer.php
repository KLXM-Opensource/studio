<?php
/**
 * Fußbereich – Design → Kopf & Fuß → „Fußbereich“:
 *   index   Marke + Claim, Seiten, Kontakt, Öffnungs- und Saisonzeiten – Raster, so viele Spalten wie Platz ist
 *   panel   ein Band in Waldnacht mit Satz aus „Website“ und Button, darunter die Spalten
 *   simple  eine ruhige, umbrechende Zeile
 */
$address = nature_address_lines();
$phone = nature_phone();
$email = nature_email();
$social = nature_social();
$pages = \Core\Pages::menu(false);
$legal = array_merge(nature_legal_links(), footer_links());   // footer_links(): z. B. „Datenschutz-Einstellungen“ (Erweiterung consent_kit)
$langs = language_links();
$hours = nature_hours();
$text = trim((string) setting('footer_text'));
$tagline = trim((string) setting('tagline'));
$home = url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$variant = in_array(design('footer'), ['index', 'panel', 'simple'], true) ? (string) design('footer') : 'index';
$cta = nature_header_cta() ?? (trim((string) setting('header_cta_label')) !== '' && trim((string) setting('header_cta_link')) !== ''
    ? ['label' => trim((string) setting('header_cta_label')), 'link' => trim((string) setting('header_cta_link'))] : null);
$statement = trim((string) setting('footer_statement')) ?: nature_name(true);
$socialList = function () use ($social): string {
    $h = '';
    foreach ($social as $s) $h .= '<li><a href="' . e($s['url']) . '" target="_blank" rel="noopener me">' . e($s['label']) . '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span></a></li>';
    return $h;
};
?>
<footer class="ftr ftr--<?= e($variant) ?>">
  <svg class="ftr__hills" viewBox="0 0 1200 60" preserveAspectRatio="none" aria-hidden="true" focusable="false"><path class="ftr__hills-2" d="M0 60V38C120 18 240 12 380 26S640 52 800 34S1060 6 1200 22V60Z"/><path d="M0 60V48C160 34 300 40 460 46S760 30 920 40S1120 52 1200 44V60Z"/></svg>
  <div class="wrap">
  <?php if ($variant === 'panel'): ?>
    <div class="ftr__panel">
      <p class="ftr__big"><?= nature_title($statement) ?></p>
      <?php if ($cta): ?><a class="btn btn--primary ftr__cta" <?= nature_link_attrs($cta['link']) ?>><?= e($cta['label']) ?><?= icon('arrow-right') ?></a><?php endif; ?>
      <span class="ftr__sprout" aria-hidden="true"></span>
    </div>
  <?php endif; ?>
  <?php if ($variant === 'simple'): ?>
    <div class="ftr__row">
      <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--footer']) ?>
      <?php if ($pages): ?><nav aria-label="<?= e(lt('Seiten')) ?>"><ul class="cluster ftr__inline" role="list"><?php foreach ($pages as $m): ?><li><a href="<?= e($m['href']) ?>"<?= nature_is_current($m) ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul></nav><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="ftr__grid">
      <div class="ftr__about">
        <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--footer']) ?>
        <?php if ($tagline !== ''): ?><p class="ftr__tagline"><?= nature_title($tagline) ?></p><?php endif; ?>
        <?php if ($text !== ''): ?><p class="ftr__text"><?= nl2br(nature_title($text), false) ?></p><?php endif; ?>
        <?php if ($social): ?><ul class="cluster ftr__social" role="list" aria-label="<?= e(lt('Social Media')) ?>"><?= $socialList() ?></ul><?php endif; ?>
      </div>
      <?php if ($pages): ?>
      <nav class="ftr__col" aria-labelledby="ftr-pages">
        <h2 class="ftr__h" id="ftr-pages"><?= e(lt('Seiten')) ?></h2>
        <ul class="ftr__index" role="list"><?php foreach ($pages as $m): ?><li><a href="<?= e($m['href']) ?>"<?= nature_is_current($m) ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul>
      </nav>
      <?php endif; ?>
      <?php if ($address || filled($phone) || $email !== ''): ?>
      <div class="ftr__col">
        <h2 class="ftr__h"><?= e(lt('Kontakt')) ?></h2>
        <?php if ($address): ?><address class="ftr__address"><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
        <?= nature_quick_contact('ftr__list') ?>
      </div>
      <?php endif; ?>
      <?php if ($hours): ?>
      <div class="ftr__col">
        <h2 class="ftr__h"><?= e(lt('Öffnungszeiten')) ?></h2>
        <?= app()->theme->partial('hours', ['hours' => $hours]) ?>
        <?= nature_seasons_list('seasons ftr__seasons') ?>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
    <div class="ftr__bottom">
      <p class="ftr__copy">© <?= e(date('Y')) ?> <?= e(nature_name()) ?></p>
      <?php if ($legal): ?>
      <nav aria-label="<?= e(lt('Rechtliches')) ?>"><ul class="cluster ftr__legal" role="list"><?php foreach ($legal as $l): ?><li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?></ul></nav>
      <?php endif; ?>
      <?php if ($variant === 'simple' && $social): ?><ul class="cluster ftr__legal" role="list" aria-label="<?= e(lt('Social Media')) ?>"><?= $socialList() ?></ul><?php endif; ?>
      <?php if ($langs): ?><?= app()->theme->partial('langswitch', ['langs' => $langs]) ?><?php endif; ?>
      <a class="ftr__top" href="#main"><?= e(lt('Nach oben')) ?><span aria-hidden="true"> ↑</span></a>
    </div>
  </div>
</footer>
