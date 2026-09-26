<?php $title = 'Anmelden'; $pkLogin = \Core\Http\Controllers\Admin\PasskeyController::passwordlessOffered(); ?>
<div class="adm-auth">
  <p class="adm-brand adm-brand--auth"><?php [$b1, $b2] = admin_brand(); ?><span><?= e($b1) ?><i>.</i></span><small><?= e(trim($b2 . " · " . __("Verwaltung"), " ·")) ?></small></p>
  <form class="adm-card" method="post" action="<?= e(url('/admin/login')) ?>" novalidate>
    <h1>Anmelden</h1>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php if ($error): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <div class="f"><label for="email">E-Mail-Adresse</label><input id="email" name="email" type="email" autocomplete="<?= $pkLogin ? 'username webauthn' : 'username' ?>" required value="<?= e($email) ?>" autofocus></div>
    <div class="f"><label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <button class="adm-btn adm-btn--primary adm-btn--block" type="submit">Anmelden</button>
    <?php if ($pkLogin): // Anmeldung ohne Passwort (Core\Passkeys) – erscheint nur mit JavaScript und Passkey-fähigem Browser ?>
    <div class="pk-login" data-pk-login="<?= e(url('/admin/login/passkey')) ?>" data-next="<?= e($next) ?>" hidden>
      <p class="pk-or"><span><?= e(__('oder')) ?></span></p>
      <button class="adm-btn adm-btn--block" type="button" data-pk-login-btn><?= e(__('Mit Passkey anmelden')) ?></button>
      <p class="adm-flash pk-msg" data-pk-msg aria-live="polite" hidden></p>
    </div>
    <?php endif; ?>
  </form>
  <?php if ($pkLogin): ?><script src="<?= e(asset('js/passkey.js')) ?>" defer></script><?php endif; ?>
  <p class="adm-muted"><a href="<?= e(url('/')) ?>">← Zur Website</a></p>
  <p class="adm-product"><?= cms_mark() ?></p>
</div>
