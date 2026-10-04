<?php
/**
 * Fußbereich – Design → Kopf & Navigation → „Fußbereich“:
 *   sitemap  Name + Unterzeile, alle Rubriken mit Unterseiten in Spalten (Sitemap), Kontakt, Social Media; darunter Rechtliches
 *   simple   eine ruhige Zeile: Wortmarke, Rubriken, Rechtliches, Sprache
 */
$address = editorial_address_lines();
$phone = editorial_phone();
$email = editorial_email();
$social = editorial_social();
$pages = \Core\Pages::menu(false);
$legal = array_merge(editorial_legal_links(), footer_links());   // footer_links(): z. B. „Datenschutz-Einstellungen“ (Erweiterung consent_kit)
$langs = language_links();
$text = trim((string) setting('footer_text'));
$tagline = trim((string) setting('tagline'));
$home = url(\Core\Lang::prefix(\Core\Lang::current()) . '/');
$currentId = (int) (app()->currentPage['id'] ?? 0);
$cur = fn(array $m) => (int) $m['id'] === $currentId ? ' aria-current="page"' : '';
$simple = design('footer') === 'simple';
?>
<footer class="site-footer site-footer--<?= $simple ? 'simple' : 'sitemap' ?>">
  <div class="wrap">
    <div class="site-footer__top">
      <?= app()->theme->partial('brand', ['href' => $home, 'class' => 'brand--foot']) ?>
      <?php if ($tagline !== ''): ?><p class="site-footer__claim"><?= e($tagline) ?></p><?php endif; ?>
    </div>
    <?php if ($simple): ?>
      <?php if ($pages): ?>
      <nav aria-label="<?= e(lt('Rubriken')) ?>"><ul class="site-footer__inline">
        <?php foreach ($pages as $m): ?><li><a href="<?= e($m['href']) ?>"<?= $cur($m) ?>><?= e($m['label']) ?></a></li><?php endforeach; ?>
      </ul></nav>
      <?php endif; ?>
    <?php else: ?>
    <div class="site-footer__grid">
      <?php if ($pages): ?>
      <nav class="site-footer__map" aria-label="<?= e(lt('Sitemap')) ?>">
        <?php foreach ($pages as $m): ?>
        <div class="site-footer__col">
          <a class="site-footer__h" href="<?= e($m['href']) ?>"<?= $cur($m) ?>><?= e($m['label']) ?></a>
          <?php if ($m['children']): ?><ul class="site-footer__list">
            <?php foreach ($m['children'] as $c): ?><li><a href="<?= e($c['href']) ?>"<?= $cur($c) ?>><?= e($c['label']) ?></a></li><?php endforeach; ?>
          </ul><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </nav>
      <?php endif; ?>
      <?php if ($address || filled($phone) || $email !== '' || $social): ?>
      <div class="site-footer__contact">
        <p class="site-footer__h"><?= e(lt('Kontakt')) ?></p>
        <?php if ($address): ?><address><?= e(editorial_name()) ?><br><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
        <ul class="site-footer__list">
          <?php if (filled($phone) && ($tel = editorial_phone_href())): ?><li><a href="<?= e($tel) ?>"><?= e($phone) ?></a></li><?php endif; ?>
          <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
          <?php foreach ($social as $s): ?><li><a href="<?= e($s['url']) ?>" target="_blank" rel="noopener me"><?= e($s['label']) ?><span class="sr-only"> <?= e(lt('(öffnet in neuem Tab)')) ?></span></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if ($text !== ''): ?><p class="site-footer__text"><?= nl2br(e($text), false) ?></p><?php endif; ?>
    <div class="site-footer__bottom">
      <p class="site-footer__copy">© <?= e(date('Y')) ?> <?= e(editorial_name()) ?></p>
      <?php if ($legal): ?>
      <nav aria-label="<?= e(lt('Rechtliches')) ?>"><ul class="site-footer__legal">
        <?php foreach ($legal as $l): ?><li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?>
      </ul></nav>
      <?php endif; ?>
      <?php if ($langs): ?><?= app()->theme->partial('langswitch', ['langs' => $langs]) ?><?php endif; ?>
      <a class="site-footer__top-link" href="#main"><?= e(lt('Nach oben')) ?> <span aria-hidden="true">↑</span></a>
    </div>
  </div>
</footer>
