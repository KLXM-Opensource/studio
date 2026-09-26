<?php
/**
 * Fußbereich – Design → Navigation → „Fußbereich“:
 *   columns  Name + Claim, Kontakt, Seiten, Social Media in Spalten, darunter Rechtliches und Sprache
 *   simple   eine ruhige Zeile: Wortmarke, Seiten, Rechtliches, Social Media, Sprache
 */
$address = basis_address_lines();
$phone = basis_phone();
$email = basis_email();
$social = basis_social();
$pages = \Core\Pages::menu(false);
$legal = array_merge(basis_legal_links(), footer_links());   // footer_links(): z. B. „Cookie-Einstellungen“ (Erweiterung consent_kit)
$langs = language_links();
$text = trim((string) setting('footer_text'));
$tagline = trim((string) setting('tagline'));
$home = url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$currentId = (int) (app()->currentPage['id'] ?? 0);
$simple = design('footer') === 'simple';
?>
<footer class="site-footer site-footer--<?= $simple ? 'simple' : 'columns' ?>">
  <?php if ($simple): ?>
  <div class="wrap site-footer__row">
    <div class="site-footer__about">
      <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--footer']) ?>
      <?php if ($tagline !== ''): ?><p class="site-footer__tagline"><?= e($tagline) ?></p><?php endif; ?>
    </div>
    <?php if ($pages): ?>
    <nav aria-label="<?= e(lt('Seiten')) ?>">
      <ul class="site-footer__inline">
        <?php foreach ($pages as $m): ?><li><a href="<?= e($m['href']) ?>"<?= (int) $m['id'] === $currentId ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
  <?php if ($text !== ''): ?><div class="wrap"><p class="site-footer__text"><?= nl2br(e($text), false) ?></p></div><?php endif; ?>
  <?php else: ?>
  <div class="wrap site-footer__grid">
    <div class="site-footer__about">
      <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--footer']) ?>
      <?php if ($tagline !== ''): ?><p class="site-footer__tagline"><?= e($tagline) ?></p><?php endif; ?>
      <?php if ($text !== ''): ?><p class="site-footer__text"><?= nl2br(e($text), false) ?></p><?php endif; ?>
    </div>
    <?php if ($address || filled($phone) || $email !== ''): ?>
    <div class="site-footer__col">
      <h2 class="site-footer__h"><?= e(lt('Kontakt')) ?></h2>
      <?php if ($address): ?><address class="site-footer__address"><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
      <ul class="site-footer__list">
        <?php if (filled($phone) && ($tel = basis_phone_href())): ?><li><a href="<?= e($tel) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($pages): ?>
    <nav class="site-footer__col" aria-labelledby="footer-pages">
      <h2 class="site-footer__h" id="footer-pages"><?= e(lt('Seiten')) ?></h2>
      <ul class="site-footer__list">
        <?php foreach ($pages as $m): ?><li><a href="<?= e($m['href']) ?>"<?= (int) $m['id'] === $currentId ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>
    <?php if ($social): ?>
    <div class="site-footer__col">
      <h2 class="site-footer__h"><?= e(lt('Folgen Sie uns')) ?></h2>
      <ul class="site-footer__list">
        <?php foreach ($social as $s): ?><li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener me"><?= e($s['label']) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="wrap site-footer__bar"><div class="site-footer__bottom">
    <p class="site-footer__copy">© <?= e(date('Y')) ?> <?= e(basis_name()) ?></p>
    <?php if ($legal): ?>
    <nav aria-label="<?= e(lt('Rechtliches')) ?>">
      <ul class="site-footer__legal">
        <?php foreach ($legal as $l): ?><li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?>
      </ul>
    </nav>
    <?php endif; ?>
    <?php if ($simple && $social): ?>
    <ul class="site-footer__legal site-footer__social">
      <?php foreach ($social as $s): ?><li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener me"><?= e($s['label']) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></li><?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php if ($langs): ?><?= app()->theme->partial('langswitch', ['langs' => $langs]) ?><?php endif; ?>
  </div></div>
</footer>
