<?php
/** Fußbereich (UIkit): uk-section-secondary + uk-light („inverse“), Raster mit uk-grid */
$address = frameworks_address_lines();
$phone = frameworks_phone();
$email = frameworks_email();
$pages = \Core\Pages::menu(false);
$legal = frameworks_legal_links();
$text = trim((string) setting('footer_text'));
?>
<footer class="uk-section uk-section-secondary uk-light uk-section-small fw-footer">
  <div class="uk-container">
    <div class="uk-child-width-1-3@m uk-grid-large" uk-grid>
      <div>
        <p class="uk-text-large uk-text-bold uk-margin-small"><?= e(frameworks_name()) ?></p>
        <?php if (filled(setting('tagline'))): ?><p class="uk-margin-small"><?= emphasis((string) setting('tagline')) ?></p><?php endif; ?>
        <?php if ($text !== ''): ?><p class="uk-text-small"><?= nl2br(emphasis($text), false) ?></p><?php endif; ?>
      </div>
      <?php if ($address || filled($phone) || $email !== ''): ?>
      <div>
        <h2 class="uk-h6 uk-text-uppercase"><?= e(lt('Kontakt')) ?></h2>
        <?php if ($address): ?><address><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
        <ul class="uk-list uk-list-collapse">
          <?php if (filled($phone) && ($tel = tel_href($phone))): ?><li><a href="<?= e($tel) ?>"><?= icon('phone') ?> <?= e($phone) ?></a></li><?php endif; ?>
          <?php if ($email !== ''): ?><li><a href="mailto:<?= e($email) ?>"><?= icon('envelope-simple') ?> <?= e($email) ?></a></li><?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($pages): ?>
      <nav aria-labelledby="ftr-pages">
        <h2 id="ftr-pages" class="uk-h6 uk-text-uppercase"><?= e(lt('Seiten')) ?></h2>
        <ul class="uk-list uk-list-collapse"><?php foreach ($pages as $m): ?><li><a href="<?= e($m['href']) ?>"<?= frameworks_is_current($m) ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul>
      </nav>
      <?php endif; ?>
    </div>
    <hr>
    <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-text-small">
      <p class="uk-margin-remove">© <?= e(date('Y')) ?> <?= e(frameworks_name()) ?> · <?= e(lt('Gestaltet mit UIkit')) ?></p>
      <?php if ($legal): ?><nav aria-label="<?= e(lt('Rechtliches')) ?>"><ul class="uk-subnav uk-margin-remove"><?php foreach ($legal as $l): ?><li><a href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?></ul></nav><?php endif; ?>
    </div>
  </div>
</footer>
