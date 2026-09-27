<?php /** Einladung ungültig, abgelaufen, bereits verwendet oder zurückgezogen – bewusst ohne Unterscheidung. @var bool $limited */
$title = __('Einladung'); ?>
<div class="adm-auth inv-auth">
  <?php include __DIR__ . '/_brand.php'; ?>
  <section class="adm-card inv-card inv-card--invalid" aria-labelledby="inv-h">
    <p class="inv-eyebrow"><?= e(__('Einladung')) ?></p>
    <?php if ($limited): ?>
    <h1 id="inv-h"><?= e(__('Zu viele Versuche')) ?></h1>
    <p><?= e(__('Bitte warten Sie 15 Minuten und öffnen Sie den Link aus Ihrer E-Mail dann erneut.')) ?></p>
    <?php else: ?>
    <h1 id="inv-h"><?= e(__('Dieser Link gilt nicht mehr')) ?></h1>
    <p><?= e(__('Die Einladung ist abgelaufen, wurde bereits angenommen oder zurückgezogen.')) ?></p>
    <p class="inv-hint"><?= e(__('Bitten Sie um eine neue Einladung – die Person, die Sie eingeladen hat, kann sie unter „Benutzer & Rollen“ erneut senden.')) ?></p>
    <p class="adm-muted"><?= e(__('Sie haben Ihr Konto schon eingerichtet?')) ?> <a href="<?= e(url('/admin/login')) ?>"><?= e(__('Zur Anmeldung')) ?></a></p>
    <?php endif; ?>
  </section>
  <p class="adm-muted"><a href="<?= e(url('/')) ?>">← <?= e(__('Zur Website')) ?></a></p>
  <p class="adm-product"><?= cms_mark() ?></p>
</div>
