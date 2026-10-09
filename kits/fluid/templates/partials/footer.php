<?php
/**
 * Fußbereich – Design → Kopf & Fuß → „Fußbereich“:
 *   columns    Name + Claim, Kontakt, Seiten, Social Media als Raster (auto-fit – so viele Spalten, wie Platz ist)
 *   simple     eine ruhige, umbrechende Zeile: Wortmarke, Seiten, Rechtliches, Social Media, Sprache
 *   statement  großer Schriftzug (Satz aus „Website“) mit Button, darunter die Spalten
 */
$address = fluid_address_lines();
$phone = fluid_phone();
$email = fluid_email();
$social = fluid_social();
$pages = \Core\Pages::menu(false);
$legal = array_merge(fluid_legal_links(), footer_links());   // footer_links(): z. B. „Datenschutz-Einstellungen“ (Erweiterung consent_kit)
$langs = language_links();
$text = trim((string) setting('footer_text'));
$tagline = trim((string) setting('tagline'));
$home = url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$variant = (string) design('footer');
$cta = fluid_header_cta() ?? (trim((string) setting('header_cta_label')) !== '' && trim((string) setting('header_cta_link')) !== ''
    ? ['label' => trim((string) setting('header_cta_label')), 'link' => trim((string) setting('header_cta_link'))] : null);
$statement = trim((string) setting('footer_statement')) ?: fluid_name(true);
$socialList = function () use ($social): string {
    $h = '';
    foreach ($social as $s) $h .= '<li><a href="' . e($s['url']) . '" target="_blank" rel="noopener me">' . e($s['label']) . '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span></a></li>';
    return $h;
};
$pageList = function () use ($pages): string {
    $h = '';
    foreach ($pages as $m) $h .= '<li><a href="' . e($m['href']) . '"' . (fluid_is_current($m) ? ' aria-current="page"' : '') . '>' . e($m['label']) . '</a></li>';
    return $h;
};
?>
<footer class="ftr ftr--<?= e($variant) ?>">
  <div class="wrap">
  <?php if ($variant === 'statement'): ?>
    <div class="ftr__statement">
      <p class="ftr__big"><?= fluid_title($statement) ?></p>
      <?php if ($cta): ?><a class="btn btn--primary ftr__cta" <?= fluid_link_attrs($cta['link']) ?>><?= e($cta['label']) ?><?= icon('arrow-right') ?></a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if ($variant === 'simple' || $variant === 'centered'): ?>
    <div class="ftr__row">
      <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--footer']) ?>
      <?php if ($tagline !== ''): ?><p class="ftr__tagline"><?= fluid_title($tagline) ?></p><?php endif; ?>
      <?php if ($pages): ?><nav aria-label="<?= e(lt('Seiten')) ?>" class="ftr__inline"><ul class="cluster" role="list"><?= $pageList() ?></ul></nav><?php endif; ?>
    </div>
    <?php if ($text !== ''): ?><p class="ftr__text"><?= nl2br(fluid_title($text), false) ?></p><?php endif; ?>
  <?php else: ?>
    <div class="ftr__grid">
      <div class="ftr__about">
        <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--footer']) ?>
        <?php if ($tagline !== ''): ?><p class="ftr__tagline"><?= fluid_title($tagline) ?></p><?php endif; ?>
        <?php if ($text !== ''): ?><p class="ftr__text"><?= nl2br(fluid_title($text), false) ?></p><?php endif; ?>
      </div>
      <?php if ($address || filled($phone) || $email !== ''): ?>
      <div class="ftr__col">
        <h2 class="ftr__h"><?= e(lt('Kontakt')) ?></h2>
        <?php if ($address): ?><address class="ftr__address"><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
        <?= fluid_quick_contact('ftr__list') ?>
      </div>
      <?php endif; ?>
      <?php if ($pages): ?>
      <nav class="ftr__col" aria-labelledby="ftr-pages">
        <h2 class="ftr__h" id="ftr-pages"><?= e(lt('Seiten')) ?></h2>
        <ul class="ftr__list" role="list"><?= $pageList() ?></ul>
      </nav>
      <?php endif; ?>
      <?php if ($social): ?>
      <div class="ftr__col">
        <h2 class="ftr__h"><?= e(lt('Folgen Sie uns')) ?></h2>
        <ul class="ftr__list" role="list"><?= $socialList() ?></ul>
      </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
    <div class="ftr__bottom">
      <p class="ftr__copy">© <?= e(date('Y')) ?> <?= e(fluid_name()) ?></p>
      <?php if ($legal): ?>
      <nav aria-label="<?= e(lt('Rechtliches')) ?>"><ul class="cluster ftr__legal" role="list"><?php foreach ($legal as $l): ?><li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?></ul></nav>
      <?php endif; ?>
      <?php if (($variant === 'simple' || $variant === 'centered') && $social): ?><ul class="cluster ftr__legal" role="list" aria-label="<?= e(lt('Social Media')) ?>"><?= $socialList() ?></ul><?php endif; ?>
      <?php if ($langs): ?><?= app()->theme->partial('langswitch', ['langs' => $langs]) ?><?php endif; ?>
    </div>
  </div>
</footer>
