<?php
/**
 * Anmeldeseite der Verwaltung. Reihenfolge und Gewicht:
 *  1. „Mit Passkey anmelden“ – einziger Hauptknopf (nur mit JavaScript und Passkey-fähigem Browser, resources/js/passkey.js)
 *  2. E-Mail + Passwort – „Anmelden“ tritt zurück, solange der Passkey-Knopf sichtbar ist, sonst ist er der Hauptknopf
 *  3. Anmeldung über einen Anbieter (OpenID Connect, Core\Oidc) – dezent ganz unten in der Karte, mit Logo des Anbieters
 * Darunter die Fußzeile: „Zur Website“, Zeichen der Agentur bzw. KLXM Studio, Support-Zeile (System → Branding & Support).
 */
$title = 'Anmelden';
$pkLogin = \Core\Http\Controllers\Admin\PasskeyController::passwordlessOffered();
$oidc = \Core\Oidc\Providers::forLogin();
?>
<div class="adm-auth">
  <?php include __DIR__ . '/invite/_brand.php'; // App-Icon und Name der Website (wie Einladung, Links aus E-Mails) ?>
  <form class="adm-card auth-card" method="post" action="<?= e(url('/admin/login')) ?>" novalidate>
    <p class="auth-greet"><?= e(\Core\AuthScreen::greeting()) ?></p>
    <h1><?= e(__('Anmelden')) ?></h1>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <?php $errAttr = $error ? ' aria-describedby="login-err" aria-invalid="true"' : ''; // Fehler an beide Felder koppeln ?>
    <?php if ($error): ?><p class="adm-flash adm-flash--error" id="login-err" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?php if ($pkLogin): // Passkey zuerst (Core\Passkeys) – erscheint nur mit JavaScript und Passkey-fähigem Browser, sonst nur das Formular ?>
    <div class="pk-login pk-login--first" data-pk-login="<?= e(url('/admin/login/passkey')) ?>" data-next="<?= e($next) ?>" hidden>
      <button class="adm-btn adm-btn--primary adm-btn--block" type="button" data-pk-login-btn><?= icon('fingerprint') ?> <?= e(__('Mit Passkey anmelden')) ?></button>
      <p class="adm-flash pk-msg" data-pk-msg aria-live="polite" hidden></p>
      <p class="pk-or"><span><?= e(__('oder mit E-Mail und Passwort')) ?></span></p>
    </div>
    <?php endif; ?>
    <div class="f"><label for="email"><?= e(__('E-Mail-Adresse')) ?></label><input id="email" name="email" type="email" autocomplete="<?= $pkLogin ? 'username webauthn' : 'username' ?>" required value="<?= e($email) ?>" autofocus<?= $errAttr ?>></div>
    <div class="f"><div class="auth-pwlabel"><label for="password"><?= e(__('Passwort')) ?></label><a class="auth-forgot" href="<?= e(url('/admin/passwort-vergessen')) ?>"><?= e(__('Passwort vergessen?')) ?></a></div><input id="password" name="password" type="password" autocomplete="current-password" required<?= $errAttr ?>></div>
    <button class="adm-btn adm-btn--primary adm-btn--block auth-submit" type="submit"><?= e(__('Anmelden')) ?></button>
    <?php if ($oidc): // Agentur-Zugang: bewusst leise – kleiner Link mit Logo des Anbieters (Providers::logoHtml), Name steht im Text ?>
    <p class="auth-alt"><a class="auth-alt__link" href="<?= e(url('/admin/login/oidc') . ($next !== '' ? '?next=' . rawurlencode($next) : '')) ?>" rel="nofollow"><?= \Core\Oidc\Providers::logoHtml($oidc) ?><span><?= e(\Core\Oidc\Providers::buttonLabel($oidc)) ?></span></a></p>
    <?php endif; ?>
  </form>
  <?php if ($pkLogin): ?><script src="<?= e(asset('js/passkey.js')) ?>" defer></script><?php endif; ?>
  <?php $footSupport = true; include __DIR__ . '/invite/_foot.php'; // Zur Website · Zeichen · Support ?>
</div>
