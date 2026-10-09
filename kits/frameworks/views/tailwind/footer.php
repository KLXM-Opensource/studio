<?php
/** Fußbereich (Tailwind): Marke, Kontakt (zentral aus „Website“), Seiten, Rechtliches + footer_links() der Erweiterungen */
$address = frameworks_address_lines();
$phone = frameworks_phone();
$email = frameworks_email();
$pages = \Core\Pages::menu(false);
$legal = frameworks_legal_links();
$text = trim((string) setting('footer_text'));
$a = 'text-slate-300 underline decoration-slate-500 underline-offset-4 hover:text-white hover:decoration-white';
?>
<footer class="fw-footer bg-slate-900 py-14 text-slate-300 dark:bg-black">
  <div class="wrap grid gap-10 md:grid-cols-3">
    <div>
      <p class="text-lg font-bold text-white"><?= e(frameworks_name()) ?></p>
      <?php if (filled(setting('tagline'))): ?><p class="mt-2"><?= emphasis((string) setting('tagline')) ?></p><?php endif; ?>
      <?php if ($text !== ''): ?><p class="mt-4 text-sm text-slate-400"><?= nl2br(emphasis($text), false) ?></p><?php endif; ?>
    </div>
    <?php if ($address || filled($phone) || $email !== ''): ?>
    <div>
      <h2 class="text-sm font-semibold tracking-wider text-white uppercase"><?= e(lt('Kontakt')) ?></h2>
      <?php if ($address): ?><address class="mt-3 not-italic"><?= implode('<br>', array_map('e', $address)) ?></address><?php endif; ?>
      <ul role="list" class="mt-3 space-y-1">
        <?php if (filled($phone) && ($tel = tel_href($phone))): ?><li><a class="<?= $a ?>" href="<?= e($tel) ?>"><?= icon('phone', ['class' => 'mr-1']) ?><?= e($phone) ?></a></li><?php endif; ?>
        <?php if ($email !== ''): ?><li><a class="<?= $a ?>" href="mailto:<?= e($email) ?>"><?= icon('envelope-simple', ['class' => 'mr-1']) ?><?= e($email) ?></a></li><?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($pages): ?>
    <nav aria-labelledby="ftr-pages">
      <h2 id="ftr-pages" class="text-sm font-semibold tracking-wider text-white uppercase"><?= e(lt('Seiten')) ?></h2>
      <ul role="list" class="mt-3 space-y-1"><?php foreach ($pages as $m): ?><li><a class="<?= $a ?>" href="<?= e($m['href']) ?>"<?= frameworks_is_current($m) ? ' aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul>
    </nav>
    <?php endif; ?>
  </div>
  <div class="wrap mt-10 flex flex-wrap items-center justify-between gap-4 border-t border-slate-700 pt-6 text-sm">
    <p>© <?= e(date('Y')) ?> <?= e(frameworks_name()) ?> · <?= e(lt('Gestaltet mit Tailwind CSS')) ?></p>
    <?php if ($legal): ?><nav aria-label="<?= e(lt('Rechtliches')) ?>"><ul role="list" class="flex flex-wrap gap-5"><?php foreach ($legal as $l): ?><li><a class="<?= $a ?>" href="<?= e($l['href']) ?>"><?= e($l['label']) ?></a></li><?php endforeach; ?></ul></nav><?php endif; ?>
  </div>
</footer>
