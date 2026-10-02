<?php $title = 'Anmelden'; $pkLogin = \Core\Http\Controllers\Admin\PasskeyController::passwordlessOffered(); ?>
<div class="adm-auth">
  <?php include __DIR__ . '/invite/_brand.php'; // App-Icon und Name der Website (wie Einladung, Links aus E-Mails) ?>
  <form class="adm-card" method="post" action="<?= e(url('/admin/login')) ?>" novalidate>
    <p class="auth-greet"><?= e(\Core\AuthScreen::greeting()) ?></p>
    <h1><?= e(__('Anmelden')) ?></h1>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php $errAttr = $error ? ' aria-describedby="login-err" aria-invalid="true"' : ''; // Fehler an beide Felder koppeln ?>
    <?php if ($error): ?><p class="adm-flash adm-flash--error" id="login-err" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="f"><label for="email"><?= e(__('E-Mail-Adresse')) ?></label><input id="email" name="email" type="email" autocomplete="<?= $pkLogin ? 'username webauthn' : 'username' ?>" required value="<?= e($email) ?>" autofocus<?= $errAttr ?>></div>
    <div class="f"><div class="auth-pwlabel"><label for="password"><?= e(__('Passwort')) ?></label><a class="auth-forgot" href="<?= e(url('/admin/passwort-vergessen')) ?>"><?= e(__('Passwort vergessen?')) ?></a></div><input id="password" name="password" type="password" autocomplete="current-password" required<?= $errAttr ?>></div>
    <button class="adm-btn adm-btn--primary adm-btn--block" type="submit"><?= e(__('Anmelden')) ?></button>
    <?php if ($pkLogin): // Anmeldung ohne Passwort (Core\Passkeys) – erscheint nur mit JavaScript und Passkey-fähigem Browser ?>
    <div class="pk-login" data-pk-login="<?= e(url('/admin/login/passkey')) ?>" data-next="<?= e($next) ?>" hidden>
      <p class="pk-or"><span><?= e(__('oder')) ?></span></p>
      <button class="adm-btn adm-btn--block" type="button" data-pk-login-btn><?= icon('fingerprint') ?> <?= e(__('Mit Passkey anmelden')) ?></button>
      <p class="adm-flash pk-msg" data-pk-msg aria-live="polite" hidden></p>
    </div>
    <?php endif; ?>
  </form>
  <?php if ($pkLogin): ?><script src="<?= e(asset('js/passkey.js')) ?>" defer></script><?php endif; ?>
  <p class="adm-muted"><a href="<?= e(url('/')) ?>">← <?= e(__('Zur Website')) ?></a></p>
  <p class="adm-product"><?= cms_mark() ?></p>
</div>
