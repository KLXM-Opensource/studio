<?php
/** Passkey hinzufügen (Konto, Einrichtung) – resources/js/passkey.js. @var bool $pkRecent  @var bool $pkAvail  @var string $pkBack  @var string $pkId */
$pkId ??= 'pk';
?>
<?php if (!$pkAvail): ?>
<p class="adm-flash adm-flash--error"><?= e(__('Passkeys brauchen eine sichere Verbindung (HTTPS) und einen Domainnamen.')) ?></p>
<?php elseif (!$pkRecent): ?>
<form method="post" action="<?= e(url('/admin/account/reauth')) ?>" class="tf-inline pk-reauth"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($pkBack) ?>">
  <label class="adm-sr" for="<?= e($pkId) ?>-pw"><?= e(__('Passwort zur Bestätigung')) ?></label><input id="<?= e($pkId) ?>-pw" name="password" type="password" autocomplete="current-password" required placeholder="<?= e(__('Passwort zur Bestätigung')) ?>" aria-describedby="<?= e($pkId) ?>-pw-h">
  <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Passwort bestätigen')) ?></button>
</form>
<p class="f-help" id="<?= e($pkId) ?>-pw-h"><?= e(__('Zum Hinzufügen oder Löschen von Passkeys bestätigen Sie bitte zuerst Ihr Passwort (gilt 15 Minuten).')) ?></p>
<?php else: ?>
<form class="pk-add" data-pk-add="<?= e(url('/admin/account/passkeys')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="f"><label for="<?= e($pkId) ?>-name"><?= e(__('Name des Passkeys (optional)')) ?></label><input id="<?= e($pkId) ?>-name" name="name" maxlength="<?= \Core\Passkeys::NAME_MAX ?>" autocomplete="off" placeholder="<?= e(__('z. B. MacBook Touch ID')) ?>" aria-describedby="<?= e($pkId) ?>-name-h">
    <p class="f-help" id="<?= e($pkId) ?>-name-h"><?= e(__('Damit Sie ihn später wiedererkennen. Ohne Namen verwenden wir den Anbieter, z. B. „iCloud-Schlüsselbund“.')) ?></p></div>
  <button class="adm-btn adm-btn--primary adm-btn--small" type="submit"><?= e(__('Passkey hinzufügen')) ?></button>
  <p class="adm-flash pk-msg" data-pk-msg aria-live="polite" hidden></p>
</form>
<script src="<?= e(asset('js/passkey.js')) ?>" defer></script>
<?php endif; ?>
