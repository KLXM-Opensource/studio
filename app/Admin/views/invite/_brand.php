<?php /** Kopf der Einladungsseiten: App-Icon der Website und Name (wie die Anmeldung) */
[$b1, $b2] = admin_brand();
$invIcon = \Core\AppIcons::ensure() && is_file(\Core\AppIcons::dir() . '/icon-192.png') ? \Core\AppIcons::url('icon-192.png') : null; ?>
  <p class="adm-brand adm-brand--auth inv-brand"><?php if ($invIcon): ?><img class="inv-brand__icon" src="<?= e($invIcon) ?>" alt="" width="56" height="56"><?php endif; ?><span><?= e($b1) ?><i>.</i></span><small><?= e(trim($b2 . ' · ' . __('Verwaltung'), ' ·')) ?></small></p>
