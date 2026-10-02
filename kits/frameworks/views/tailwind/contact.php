<?php
/**
 * Kontakt (Tailwind) – Angaben zentral aus „Website“. $b->central() markiert sie im Editor als „zentral gepflegt“
 * (nicht direkt bearbeitbar, Link zu den Einstellungen).
 * @var \Core\Block $b  @var array $d
 */
$address = frameworks_address_lines();
$phone = frameworks_phone();
$email = frameworks_email();
?>
<div class="wrap grid gap-10 md:grid-cols-2">
  <div><?php include __DIR__ . '/_head.php'; ?></div>
  <ul role="list" class="space-y-4 text-lg"<?= $b->central() ?>>
    <?php if ($address): ?><li class="flex gap-3"><?= icon('map-pin', ['class' => 'mt-1 size-6 shrink-0 text-accent']) ?><span><?= implode('<br>', array_map('e', $address)) ?></span></li><?php endif; ?>
    <?php if (filled($phone) && ($tel = tel_href($phone))): ?><li class="flex gap-3"><?= icon('phone', ['class' => 'mt-1 size-6 shrink-0 text-accent']) ?><a class="text-accent underline underline-offset-4" href="<?= e($tel) ?>"><?= e($phone) ?></a></li><?php endif; ?>
    <?php if ($email !== ''): ?><li class="flex gap-3"><?= icon('envelope-simple', ['class' => 'mt-1 size-6 shrink-0 text-accent']) ?><a class="text-accent underline underline-offset-4" href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
    <?php if (!$address && !filled($phone) && $email === '' && is_editing()): ?><li class="empty-hint"><?= e(lt('Noch keine Kontaktangaben unter „Website“.')) ?></li><?php endif; ?>
  </ul>
</div>
