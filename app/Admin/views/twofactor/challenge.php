<?php
/** Zweiter Anmeldeschritt: Passkey und/oder Code aus der App (TOTP); Wiederherstellungscode immer möglich.
 *  @var string $error  @var list<string> $methods bevorzugtes Verfahren zuerst  @var bool $recovery */
$methods ??= ['totp'];
$recovery ??= true;
$hasPk = in_array('passkey', $methods, true);
$hasTotp = in_array('totp', $methods, true);
$pkFirst = ($methods[0] ?? '') === 'passkey';
$codeHelp = $hasTotp ? __('Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein. Ohne Gerät: einen Ihrer Wiederherstellungscodes.')
    : __('Kein Zugriff auf Ihren Passkey? Geben Sie einen Ihrer Wiederherstellungscodes ein.');
?>
<div class="adm-auth">
  <?php include __DIR__ . '/../invite/_brand.php'; // App-Icon und Name der Website ?>
  <?php if ($hasPk): ob_start(); // Passkey (Core\Passkeys) – resources/js/passkey.js ?>
  <section class="adm-card pk-2fa" data-pk-2fa="<?= e(url('/admin/login/2fa/passkey')) ?>" aria-labelledby="pk2-h">
    <?= csrf_field() ?>
    <?php if ($pkFirst): ?><h1 id="pk2-h"><?= e(__('Anmeldung bestätigen')) ?></h1><?php else: ?><h2 id="pk2-h"><?= e(__('Oder mit Passkey')) ?></h2><?php endif; ?>
    <?php if ($error && $pkFirst): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <p class="adm-muted"><?= e(__('Bestätigen Sie die Anmeldung mit Ihrem Passkey – per Fingerabdruck, Gesicht, Geräte-PIN oder Sicherheitsschlüssel.')) ?></p>
    <button class="adm-btn<?= $pkFirst ? ' adm-btn--primary' : '' ?> adm-btn--block" type="button" data-pk-2fa-btn<?= $pkFirst ? ' autofocus' : '' ?>><?= e(__('Mit Passkey bestätigen')) ?></button>
    <p class="adm-flash pk-msg" data-pk-msg aria-live="polite" hidden></p>
  </section>
  <?php $pkHtml = ob_get_clean(); endif; ?>
  <?php if ($pkFirst) echo $pkHtml; ?>
  <?php if ($hasTotp || $recovery || !$hasPk): ?>
  <?php if ($pkFirst): ?><details class="adm-card pk-alt"<?= $error ? ' open' : '' ?>><summary><?= e($hasTotp ? __('Stattdessen Code aus der App eingeben') : __('Wiederherstellungscode verwenden')) ?></summary><?php endif; ?>
  <form class="<?= $pkFirst ? 'pk-alt__form' : 'adm-card' ?>" method="post" action="<?= e(url('/admin/login/2fa')) ?>" novalidate>
    <?php if (!$pkFirst): ?><h1><?= e($hasTotp || !$hasPk && !$recovery ? __('Bestätigungscode') : __('Wiederherstellungscode')) ?></h1><?php endif; ?>
    <?= csrf_field() ?>
    <?php if ($error): // mit Passkey zuerst meldet die Passkey-Karte den Fehler (role=alert), hier nur die Beschreibung des Felds ?><p class="adm-flash adm-flash--error" id="code-err"<?= $pkFirst ? '' : ' role="alert"' ?>><?= e($error) ?></p><?php endif; ?>
    <?php if (!$hasTotp && !$hasPk): ?><p class="adm-muted"><?= e(__('Für diese Adresse ist kein Passkey eingerichtet.')) ?></p><?php endif; ?>
    <p class="adm-muted" id="code-help"><?= e($codeHelp) ?></p>
    <div class="f"><label for="code"><?= e($hasTotp ? __('Code') : __('Wiederherstellungscode')) ?></label><input id="code" name="code" aria-describedby="<?= $error ? 'code-err ' : '' ?>code-help"<?= $error ? ' aria-invalid="true"' : '' ?><?= $hasTotp ? ' inputmode="numeric"' : '' ?> autocomplete="one-time-code" required<?= $pkFirst ? '' : ' autofocus' ?> maxlength="24" class="tf-code"></div>
    <button class="adm-btn adm-btn--primary adm-btn--block" type="submit"><?= e(__('Bestätigen')) ?></button>
  </form>
  <?php if ($pkFirst): ?></details><?php endif; ?>
  <?php endif; ?>
  <?php if ($hasPk && !$pkFirst) echo $pkHtml; ?>
  <p class="adm-muted"><a href="<?= e(url('/admin/login')) ?>">← <?= e(__('Abbrechen')) ?></a></p>
  <p class="adm-product"><?= cms_mark() ?></p>
</div>
<?php if ($hasPk): ?><script src="<?= e(asset('js/passkey.js')) ?>" defer></script><?php endif; ?>
