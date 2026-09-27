<?php
/**
 * Links aus den E-Mails zur Änderung der E-Mail-Adresse (öffentlich, Stil der Anmeldung) – AccountController.
 * Ungültige, abgelaufene, benutzte und unbekannte Links sehen gleich aus (keine Rückschlüsse auf Konten).
 * @var string $mode confirm|cancel  @var string $state ask|done|invalid|limited  @var ?string $token  @var string $email  @var int $expires
 * @var ?string $error  @var bool $mine (bestätigt im angemeldeten Konto)
 */
$title = $mode === 'cancel' ? __('Änderung abbrechen') : __('E-Mail-Adresse bestätigen');
$token ??= '';
$email ??= '';
$expires ??= 0;
$action = url(($mode === 'cancel' ? '/admin/konto/email-abbrechen/' : '/admin/konto/email/') . $token);
?>
<div class="adm-auth inv-auth acc-link">
  <?php include __DIR__ . '/../invite/_brand.php'; ?>
  <section class="adm-card inv-card acc-link__card acc-link--<?= e($state) ?>" aria-labelledby="acc-link-h">
    <p class="inv-eyebrow"><?= e(__('Anmeldedaten')) ?></p>
    <?php if ($state === 'limited'): ?>
    <h1 id="acc-link-h"><?= e(__('Zu viele Versuche')) ?></h1>
    <p><?= e(__('Bitte warten Sie 15 Minuten und öffnen Sie den Link aus Ihrer E-Mail dann erneut.')) ?></p>

    <?php elseif ($state === 'invalid'): ?>
    <h1 id="acc-link-h"><?= e(__('Dieser Link gilt nicht mehr')) ?></h1>
    <p><?= e(__('Der Link ist abgelaufen, wurde bereits verwendet oder die Änderung wurde abgebrochen.')) ?></p>
    <p class="inv-hint"><?= e(__('Möchten Sie Ihre E-Mail-Adresse ändern? Melden Sie sich an und fordern Sie unter „Konto › Anmeldedaten“ einen neuen Link an.')) ?></p>
    <p class="adm-muted"><a href="<?= e(url('/admin/login')) ?>"><?= e(__('Zur Anmeldung')) ?></a></p>

    <?php elseif ($state === 'done' && $mode === 'confirm'): ?>
    <p class="acc-link__icon acc-link__icon--ok" aria-hidden="true"><?= icon('check-circle') ?></p>
    <h1 id="acc-link-h"><?= e(__('E-Mail-Adresse geändert')) ?></h1>
    <p><?= e(__('Ab jetzt melden Sie sich mit {email} an. Ihre Passkeys gelten weiter; andere Sitzungen Ihres Kontos wurden beendet. Eine Bestätigung ging an beide Adressen.', ['email' => $email])) ?></p>
    <?php if (!empty($mine)): ?>
    <a class="adm-btn adm-btn--primary adm-btn--block" href="<?= e(url('/admin/account#anmeldedaten')) ?>"><?= e(__('Zum Konto')) ?></a>
    <?php else: ?>
    <a class="adm-btn adm-btn--primary adm-btn--block" href="<?= e(url('/admin/login')) ?>"><?= e(__('Zur Anmeldung')) ?></a>
    <?php endif; ?>

    <?php elseif ($state === 'done'): ?>
    <p class="acc-link__icon acc-link__icon--warn" aria-hidden="true"><?= icon('shield-check') ?></p>
    <h1 id="acc-link-h"><?= e(__('Änderung abgebrochen')) ?></h1>
    <p><?= e(__('Ihre E-Mail-Adresse bleibt unverändert. Zur Sicherheit wurden alle Sitzungen Ihres Kontos beendet – auch eine, die jemand anderes vielleicht geöffnet hatte.')) ?></p>
    <p class="inv-hint"><?= e(__('Bitte melden Sie sich an und ändern Sie Ihr Passwort. Prüfen Sie unter „Konto“ auch Ihre Passkeys und informieren Sie die Administration der Website.')) ?></p>
    <a class="adm-btn adm-btn--primary adm-btn--block" href="<?= e(url('/admin/login')) ?>"><?= e(__('Zur Anmeldung')) ?></a>

    <?php elseif ($mode === 'confirm'): ?>
    <h1 id="acc-link-h"><?= e(__('Neue E-Mail-Adresse bestätigen')) ?></h1>
    <?php if (!empty($error)): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($email !== ''): ?>
    <p><?= e(__('Ihr Konto soll künftig diese E-Mail-Adresse verwenden:')) ?></p>
    <p class="acc-link__email"><?= e($email) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?>
      <button class="adm-btn adm-btn--primary adm-btn--block" type="submit"><?= e(__('Adresse bestätigen')) ?></button>
    </form>
    <?php if ($expires): ?><p class="inv-note"><?= e(__('Der Link gilt bis {date} und nur einmal. Danach melden Sie sich mit der neuen Adresse an; andere Sitzungen enden.', ['date' => date('d.m.Y, H:i', $expires)])) ?></p><?php endif; ?>

    <?php else: ?>
    <h1 id="acc-link-h"><?= e(__('Das war ich nicht')) ?></h1>
    <?php if (!empty($error)): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <p><?= e(__('Brechen Sie die Änderung Ihrer E-Mail-Adresse ab, wenn Sie sie nicht selbst angefordert haben. Ihre Adresse bleibt dann unverändert.')) ?></p>
    <p class="inv-hint"><?= e(__('Zur Sicherheit enden dabei alle Sitzungen Ihres Kontos. Ändern Sie danach Ihr Passwort.')) ?></p>
    <form method="post" action="<?= e($action) ?>"><?= csrf_field() ?>
      <button class="adm-btn adm-btn--danger adm-btn--block" type="submit"><?= e(__('Änderung abbrechen')) ?></button>
    </form>
    <?php endif; ?>
  </section>
  <p class="adm-muted"><a href="<?= e(url('/')) ?>">← <?= e(__('Zur Website')) ?></a></p>
  <p class="adm-product"><?= cms_mark() ?></p>
</div>
