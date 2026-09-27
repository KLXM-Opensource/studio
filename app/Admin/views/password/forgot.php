<?php
/**
 * „Passwort vergessen“ – Anfrage (öffentlich, Stil der Anmeldung) – PasswordController, Core\PasswordReset.
 * Nach dem Absenden immer dieselbe Antwort, egal ob es ein Konto zur Adresse gibt.
 * @var string $state ask|sent  @var string $email  @var ?string $error  @var int $minutes
 */
$title = __('Passwort vergessen');
?>
<div class="adm-auth inv-auth acc-link">
  <?php include __DIR__ . '/../invite/_brand.php'; ?>
  <section class="adm-card inv-card acc-link__card" aria-labelledby="pw-h">
    <p class="inv-eyebrow"><?= e(__('Anmelden')) ?></p>
    <?php if ($state === 'sent'): ?>
    <p class="acc-link__icon acc-link__icon--ok" aria-hidden="true"><?= icon('envelope-simple') ?></p>
    <h1 id="pw-h"><?= e(__('Bitte sehen Sie in Ihr Postfach')) ?></h1>
    <p role="status"><?= e(__('Wenn ein Konto zu dieser Adresse existiert, haben wir Ihnen einen Link geschickt.')) ?></p>
    <p class="inv-hint"><?= e(__('Der Link gilt {n} Minuten und nur einmal. Keine E-Mail da? Sehen Sie auch im Spam-Ordner nach oder fordern Sie in einigen Minuten einen neuen Link an.', ['n' => $minutes ?? \Core\PasswordReset::MINUTES])) ?></p>
    <a class="adm-btn adm-btn--primary adm-btn--block" href="<?= e(url('/admin/login')) ?>"><?= e(__('Zur Anmeldung')) ?></a>
    <?php else: ?>
    <h1 id="pw-h"><?= e(__('Passwort vergessen?')) ?></h1>
    <p class="inv-lead"><?= e(__('Geben Sie die E-Mail-Adresse Ihres Kontos ein. Wir schicken Ihnen einen Link, mit dem Sie ein neues Passwort festlegen.')) ?></p>
    <form method="post" action="<?= e(url('/admin/passwort-vergessen')) ?>" novalidate>
      <?= csrf_field() ?>
      <?php if (!empty($error)): ?><p class="adm-flash adm-flash--error" id="pw-err" role="alert"><?= e($error) ?></p><?php endif; ?>
      <div class="f"><label for="pw-email"><?= e(__('E-Mail-Adresse')) ?></label><input id="pw-email" name="email" type="email" autocomplete="username" required maxlength="191" value="<?= e($email) ?>" autofocus<?= !empty($error) ? ' aria-invalid="true" aria-describedby="pw-err"' : '' ?>></div>
      <button class="adm-btn adm-btn--primary adm-btn--block" type="submit"><?= e(__('Link anfordern')) ?></button>
    </form>
    <p class="inv-note"><?= e(__('Zwei-Faktor-Anmeldung und Passkeys bleiben unverändert – nach dem neuen Passwort fragt die Anmeldung wie gewohnt nach dem zweiten Faktor.')) ?></p>
    <?php endif; ?>
  </section>
  <p class="adm-muted"><a href="<?= e(url('/admin/login')) ?>">← <?= e(__('Zurück zur Anmeldung')) ?></a></p>
  <p class="adm-product"><?= cms_mark() ?></p>
</div>
