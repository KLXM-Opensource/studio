<?php
/**
 * Fußbereich: Marke + Kurzbeschreibung, Kontakt (aus „Website“), Seiten, Rechtliches.
 * footer_links(): zusätzliche Links von Erweiterungen (z. B. „Cookie-Einstellungen“ von consent_kit) – immer anhängen,
 * damit Erweiterungen in jedem Kit funktionieren. Leer ohne Erweiterungen.
 */
$address = starter_address_lines();
$phone = starter_phone();
$email = starter_email();
$social = starter_social();
$pages = \Core\Pages::menu(false);
$legal = array_merge(starter_legal_links(), footer_links());
$text = trim((string) setting('footer_text'));
?>
<footer class="ftr">
  <div class="wrap ftr__grid">
    <div class="ftr__about">
      <p class="ftr__name"><?= e(starter_name()) ?></p>
      <?php if (filled(setting('tagline'))): ?><p><?= e((string) setting('tagline')) ?></p><?php endif; ?>
      <?php if ($text !== ''): ?><p class="ftr__text"><?= nl2br(e($text), false) ?></p><?php endif; ?>
    </div>
    <?php if ($address || filled($phone) || $email !== ''): ?>
    <div>
      <h2 class="ftr__h"><?= e(lt('Kontakt')) ?></h2>
      <?php if ($address): ?><address><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
      <ul class="ftr__list" role="list">
        <?php if (filled($phone) && ($tel = tel_href($phone))): ?><li><a href="<?= e($tel) ?>"><?= icon('phone') ?><?= e($phone) ?></a></li><?php endif; ?>
        <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= icon('envelope-simple') ?><?= e($email) ?></a></li><?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($pages): ?>
    <nav aria-labelledby="ftr-pages">
      <h2 class="ftr__h" id="ftr-pages"><?= e(lt('Seiten')) ?></h2>
      <ul class="ftr__list" role="list"><?php foreach ($pages as $m): ?><li><a href="<?= e($m['href']) ?>"<?= starter_is_current($m) ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul>
    </nav>
    <?php endif; ?>
  </div>
  <div class="wrap ftr__bottom">
    <p>© <?= e(date('Y')) ?> <?= e(starter_name()) ?></p>
    <?php if ($legal): ?>
    <nav aria-label="<?= e(lt('Rechtliches')) ?>"><ul class="ftr__inline" role="list"><?php foreach ($legal as $l): ?><li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?></ul></nav>
    <?php endif; ?>
    <?php if ($social): ?>
    <ul class="ftr__inline" role="list" aria-label="<?= e(lt('Social Media')) ?>"><?php foreach ($social as $s): ?><li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener me"><?= e($s['label']) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </div>
</footer>
