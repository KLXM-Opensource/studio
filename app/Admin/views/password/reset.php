<?php
/**
 * Neues Passwort festlegen – Link aus der E-Mail „Passwort zurücksetzen“ (öffentlich, Stil der Anmeldung) – PasswordController.
 * GET zeigt das Formular, erst POST ändert das Passwort. Ungültige, abgelaufene, benutzte und unbekannte Links sehen gleich aus.
 * Anzeigen/Verbergen und Stärke-Hinweis: resources/js/invite.js (Formular [data-invite] ohne Passkey).
 * @var string $state ask|done|invalid|limited  @var ?string $token  @var string $email  @var int $expires  @var array $errors
 */
$title = __('Neues Passwort festlegen');
$errors ??= [];
$err = fn($k) => isset($errors[$k]) ? '<p class="f-error" id="pw-err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
?>
<div class="adm-auth inv-auth acc-link">
  <?php include __DIR__ . '/../invite/_brand.php'; ?>
  <section class="adm-card inv-card acc-link__card" aria-labelledby="pw-h">
    <p class="inv-eyebrow"><?= e(__('Passwort zurücksetzen')) ?></p>
    <?php if ($state === 'limited'): ?>
    <h1 id="pw-h"><?= e(__('Zu viele Versuche')) ?></h1>
    <p><?= e(__('Bitte warten Sie 15 Minuten und öffnen Sie den Link aus Ihrer E-Mail dann erneut.')) ?></p>

    <?php elseif ($state === 'invalid'): ?>
    <h1 id="pw-h"><?= e(__('Dieser Link gilt nicht mehr')) ?></h1>
    <p><?= e(__('Der Link ist abgelaufen oder wurde bereits verwendet. Aus Sicherheitsgründen gilt jeweils nur der neueste Link.')) ?></p>
    <a class="adm-btn adm-btn--primary adm-btn--block" href="<?= e(url('/admin/passwort-vergessen')) ?>"><?= e(__('Neuen Link anfordern')) ?></a>

    <?php elseif ($state === 'done'): ?>
    <p class="acc-link__icon acc-link__icon--ok" aria-hidden="true"><?= icon('check-circle') ?></p>
    <h1 id="pw-h"><?= e(__('Passwort geändert')) ?></h1>
    <p role="status"><?= e(__('Ihr neues Passwort gilt ab sofort. Alle Sitzungen Ihres Kontos wurden beendet; eine Bestätigung ist unterwegs.')) ?></p>
    <p class="inv-hint"><?= e(__('Haben Sie die Zwei-Faktor-Anmeldung oder einen Passkey eingerichtet, fragt die Anmeldung danach wie gewohnt nach dem zweiten Faktor.')) ?></p>
    <a class="adm-btn adm-btn--primary adm-btn--block" href="<?= e(url('/admin/login') . '?email=' . rawurlencode($email)) ?>"><?= e(__('Jetzt anmelden')) ?></a>

    <?php else: ?>
    <h1 id="pw-h"><?= e(__('Neues Passwort festlegen')) ?></h1>
    <form class="inv-form" method="post" action="<?= e(url('/admin/passwort/' . $token)) ?>" novalidate data-invite="<?= e(url('/admin/passwort/' . $token)) ?>">
      <?= csrf_field() ?>
      <?php if (isset($errors['form'])): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>
      <div class="f"><label for="pw-email"><?= e(__('E-Mail-Adresse')) ?></label><input id="pw-email" type="email" value="<?= e($email) ?>" autocomplete="username" readonly></div>
      <div class="f<?= isset($errors['password']) ? ' f--error' : '' ?>"><label for="inv-pw"><?= e(__('Neues Passwort (mind. 12 Zeichen)')) ?></label>
        <div class="inv-pwfield"><input id="inv-pw" name="password" type="password" autocomplete="new-password" required minlength="12" autofocus aria-describedby="inv-pw-h<?= isset($errors['password']) ? ' pw-err-password' : '' ?>"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
          <button class="adm-btn adm-btn--small adm-btn--ghost inv-reveal" type="button" data-inv-reveal aria-pressed="false" aria-controls="inv-pw inv-pw2" hidden><?= e(__('Anzeigen')) ?></button></div>
        <div class="inv-meter" data-inv-meter hidden><span></span></div>
        <p class="f-help" id="inv-pw-h" data-inv-strength aria-live="polite"><?= e(__('Am besten eine Passphrase aus mehreren Wörtern oder ein Passwort aus Ihrem Passwortmanager.')) ?></p><?= $err('password') ?></div>
      <div class="f<?= isset($errors['password2']) ? ' f--error' : '' ?>"><label for="inv-pw2"><?= e(__('Passwort wiederholen')) ?></label><input id="inv-pw2" name="password2" type="password" autocomplete="new-password" required<?= isset($errors['password2']) ? ' aria-invalid="true" aria-describedby="pw-err-password2"' : '' ?>><?= $err('password2') ?></div>
      <button class="adm-btn adm-btn--primary adm-btn--block" type="submit"><?= e(__('Passwort speichern')) ?></button>
    </form>
    <p class="inv-note"><?= e(__('Danach enden alle Sitzungen Ihres Kontos. Zwei-Faktor-Anmeldung und Passkeys bleiben unverändert.')) ?></p>
    <?php if (!empty($expires)): ?><p class="inv-note"><?= e(__('Der Link gilt bis {date} und nur einmal.', ['date' => \Core\Invites::date((int) $expires, \Core\I18n::locale())])) ?></p><?php endif; ?>
    <?php endif; ?>
  </section>
  <?php if ($state === 'ask'): ?><script src="<?= e(asset('js/invite.js')) ?>" defer></script><?php endif; ?>
  <p class="adm-muted"><a href="<?= e(url('/admin/login')) ?>">← <?= e(__('Zurück zur Anmeldung')) ?></a></p>
  <p class="adm-product"><?= cms_mark() ?></p>
</div>
