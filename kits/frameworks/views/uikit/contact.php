<?php /** Kontakt (UIkit) – zentral aus „Website“, im Editor mit $b->central() markiert. */
$address = frameworks_address_lines();
$phone = frameworks_phone();
$email = frameworks_email();
?>
<div class="uk-container">
  <div class="uk-child-width-1-2@m uk-grid-large" uk-grid>
    <div><?php include __DIR__ . '/_head.php'; ?></div>
    <div>
      <ul class="uk-list uk-list-large uk-text-large fw-contact"<?= $b->central() ?>>
        <?php if ($address): ?><li><?= icon('map-pin', ['class' => 'fw-ci']) ?> <span><?= implode('<br>', array_map('e', $address)) ?></span></li><?php endif; ?>
        <?php if (filled($phone) && ($tel = tel_href($phone))): ?><li><?= icon('phone', ['class' => 'fw-ci']) ?> <a href="<?= e($tel) ?>"><?= e($phone) ?></a></li><?php endif; ?>
        <?php if ($email !== ''): ?><li><?= icon('envelope-simple', ['class' => 'fw-ci']) ?> <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
        <?php if (!$address && !filled($phone) && $email === '' && is_editing()): ?><li class="empty-hint"><?= e(lt('Noch keine Kontaktangaben unter „Website“.')) ?></li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>
