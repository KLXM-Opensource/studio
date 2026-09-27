<?php
/**
 * Identität bestätigen (gilt 15 Minuten, Mfa::recentAuth): Passwort und/oder Passkey – Konten ohne Passwort (nur Passkey,
 * z. B. aus einer Einladung) bestätigen mit dem Passkey (resources/js/passkey.js, PasskeyController::reauthPasskey).
 * @var string $raId  @var string $raBack (anmeldedaten|choose|'')  @var bool $raHasPw  @var bool $raPasskey Passkey für diese Domain vorhanden
 */
?>
<div class="acc-reauth">
  <?php if ($raHasPw): ?>
  <form method="post" action="<?= e(url('/admin/account/reauth')) ?>" class="tf-inline pk-reauth"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($raBack) ?>">
    <label class="adm-sr" for="<?= e($raId) ?>-pw"><?= e(__('Passwort zur Bestätigung')) ?></label><input id="<?= e($raId) ?>-pw" name="password" type="password" autocomplete="current-password" required placeholder="<?= e(__('Passwort zur Bestätigung')) ?>">
    <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Passwort bestätigen')) ?></button>
  </form>
  <?php endif; ?>
  <?php if ($raPasskey): ?>
  <div class="acc-reauth__pk" data-pk-reauth="<?= e(url('/admin/account/reauth/passkey')) ?>" data-back="<?= e($raBack) ?>"<?= $raHasPw ? ' hidden' : '' ?>>
    <?= csrf_field() ?>
    <?php if ($raHasPw): ?><span class="acc-reauth__or"><?= e(__('oder')) ?></span><?php endif; ?>
    <button class="adm-btn adm-btn--small<?= $raHasPw ? '' : ' adm-btn--primary' ?>" type="button" data-pk-reauth-btn><?= icon('fingerprint') ?> <?= e(__('Mit Passkey bestätigen')) ?></button>
    <p class="adm-flash pk-msg" data-pk-msg aria-live="polite" hidden></p>
  </div>
  <script src="<?= e(asset('js/passkey.js')) ?>" defer></script>
  <?php elseif (!$raHasPw): ?>
  <p class="adm-flash adm-flash--info"><?= e(__('Für Ihr Konto ist kein Passwort festgelegt und auf dieser Adresse kein Passkey eingerichtet. Melden Sie sich bitte ab und wieder an – danach gilt die Anmeldung 15 Minuten als Bestätigung.')) ?></p>
  <?php endif; ?>
</div>
