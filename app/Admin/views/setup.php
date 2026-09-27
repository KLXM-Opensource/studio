<?php $title = 'Einrichtung'; $err = fn($k) => isset($errors[$k]) ? '<p class="f-error" id="setup-err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$aria = fn($k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="setup-err-' . e($k) . '"' : '';   // Fehler am Feld (Screenreader) ?>
<div class="adm-auth">
  <p class="adm-brand adm-brand--auth adm-brand--product"><?= cms_mark() ?><small>Ersteinrichtung</small></p>
  <form class="adm-card" method="post" action="<?= e(url('/admin/setup')) ?>" novalidate>
    <h1>Administrationskonto anlegen</h1>
    <p class="adm-muted">Den Setup-Token finden Sie in <code>config/config.local.php</code> (Eintrag <code>setup_token</code>). Die Datei wurde beim ersten Aufruf automatisch erzeugt – im Plesk-Dateimanager unter <code>httpdocs/config/</code>.</p>
    <?= csrf_field() ?>
    <?= $err('form') ?>
    <div class="f<?= isset($errors['token']) ? ' f--error' : '' ?>"><label for="token">Setup-Token</label><input id="token" name="token" autocomplete="off" required<?= $aria('token') ?>><?= $err('token') ?></div>
    <div class="f"><label for="name">Name</label><input id="name" name="name" value="<?= e($old['name'] ?? '') ?>"></div>
    <div class="f<?= isset($errors['email']) ? ' f--error' : '' ?>"><label for="email">E-Mail-Adresse</label><input id="email" name="email" type="email" required<?= $aria('email') ?> value="<?= e($old['email'] ?? '') ?>"><?= $err('email') ?></div>
    <div class="f<?= isset($errors['password']) ? ' f--error' : '' ?>"><label for="password">Passwort (mind. 12 Zeichen)</label><input id="password" name="password" type="password" autocomplete="new-password" required<?= $aria('password') ?>><?= $err('password') ?></div>
    <div class="f<?= isset($errors['password2']) ? ' f--error' : '' ?>"><label for="password2">Passwort wiederholen</label><input id="password2" name="password2" type="password" autocomplete="new-password" required<?= $aria('password2') ?>><?= $err('password2') ?></div>
    <button class="adm-btn adm-btn--primary adm-btn--block" type="submit">Konto anlegen</button>
  </form>
</div>
