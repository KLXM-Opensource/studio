<?php
/**
 * Fußbereich – Design → Kopf & Fuß → „Fußbereich“:
 *   index   Marke + Claim, nummerierter Seiten-Index, Kontakt, Öffnungszeiten – Raster, so viele Spalten wie Platz ist
 *   panel   ein Nachtpaneel mit Satz aus „Website“ und Button, darunter der Index
 *   simple  eine ruhige, umbrechende Zeile
 */
$address = essenz_address_lines();
$phone = essenz_phone();
$email = essenz_email();
$social = essenz_social();
$pages = \Core\Pages::menu(false);
$legal = array_merge(essenz_legal_links(), footer_links());   // footer_links(): z. B. „Datenschutz-Einstellungen“ (Erweiterung consent_kit)
$langs = language_links();
$hours = essenz_hours();
$text = trim((string) setting('footer_text'));
$tagline = trim((string) setting('tagline'));
$home = url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$variant = in_array(design('footer'), ['index', 'panel', 'simple'], true) ? (string) design('footer') : 'index';
$cta = essenz_header_cta() ?? (trim((string) setting('header_cta_label')) !== '' && trim((string) setting('header_cta_link')) !== ''
    ? ['label' => trim((string) setting('header_cta_label')), 'link' => trim((string) setting('header_cta_link'))] : null);
$statement = trim((string) setting('footer_statement')) ?: essenz_name(true);
$socialList = function () use ($social): string {
    $h = '';
    foreach ($social as $s) $h .= '<li><a href="' . e($s['url']) . '" target="_blank" rel="noopener me">' . e($s['label']) . '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span></a></li>';
    return $h;
};
?>
<footer class="ftr ftr--<?= e($variant) ?>">
  <div class="wrap">
  <?php if ($variant === 'panel'): ?>
    <div class="ftr__panel">
      <p class="ftr__big"><?= e($statement) ?></p>
      <?php if ($cta): ?><a class="btn btn--primary ftr__cta" <?= essenz_link_attrs($cta['link']) ?>><?= e($cta['label']) ?><?= icon('arrow-right') ?></a><?php endif; ?>
      <span class="ftr__grille" aria-hidden="true"></span>
    </div>
  <?php endif; ?>
  <?php if ($variant === 'simple'): ?>
    <div class="ftr__row">
      <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--footer']) ?>
      <?php if ($pages): ?><nav aria-label="<?= e(lt('Seiten')) ?>"><ul class="cluster ftr__inline" role="list"><?php foreach ($pages as $m): ?><li><a href="<?= e($m['href']) ?>"<?= essenz_is_current($m) ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul></nav><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="ftr__grid">
      <div class="ftr__about">
        <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--footer']) ?>
        <?php if ($tagline !== ''): ?><p class="ftr__tagline"><?= e($tagline) ?></p><?php endif; ?>
        <?php if ($text !== ''): ?><p class="ftr__text"><?= nl2br(e($text), false) ?></p><?php endif; ?>
        <?php if ($social): ?><ul class="cluster ftr__social" role="list" aria-label="<?= e(lt('Social Media')) ?>"><?= $socialList() ?></ul><?php endif; ?>
      </div>
      <?php if ($pages): ?>
      <nav class="ftr__col" aria-labelledby="ftr-pages">
        <h2 class="ftr__h" id="ftr-pages"><?= e(lt('Index')) ?></h2>
        <ol class="ftr__index" role="list"><?php foreach ($pages as $i => $m): ?><li><a href="<?= e($m['href']) ?>"<?= essenz_is_current($m) ? ' aria-current="page"' : '' ?>><span class="ftr__num" aria-hidden="true"><?= essenz_num($i + 1) ?></span><?= e($m['label']) ?></a></li><?php endforeach; ?></ol>
      </nav>
      <?php endif; ?>
      <?php if ($address || filled($phone) || $email !== ''): ?>
      <div class="ftr__col">
        <h2 class="ftr__h"><?= e(lt('Kontakt')) ?></h2>
        <?php if ($address): ?><address class="ftr__address"><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
        <?= essenz_quick_contact('ftr__list') ?>
      </div>
      <?php endif; ?>
      <?php if ($hours): ?>
      <div class="ftr__col">
        <h2 class="ftr__h"><?= e(lt('Öffnungszeiten')) ?></h2>
        <?= app()->theme->partial('hours', ['hours' => $hours]) ?>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
    <div class="ftr__bottom">
      <p class="ftr__copy">© <?= e(date('Y')) ?> <?= e(essenz_name()) ?></p>
      <?php if ($legal): ?>
      <nav aria-label="<?= e(lt('Rechtliches')) ?>"><ul class="cluster ftr__legal" role="list"><?php foreach ($legal as $l): ?><li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?></ul></nav>
      <?php endif; ?>
      <?php if ($variant === 'simple' && $social): ?><ul class="cluster ftr__legal" role="list" aria-label="<?= e(lt('Social Media')) ?>"><?= $socialList() ?></ul><?php endif; ?>
      <?php if ($langs): ?><?= app()->theme->partial('langswitch', ['langs' => $langs]) ?><?php endif; ?>
      <a class="ftr__top" href="#main"><?= e(lt('Nach oben')) ?><span aria-hidden="true"> ↑</span></a>
    </div>
  </div>
</footer>
