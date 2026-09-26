<?php /** 2FA einrichten. @var string $secret  @var string $qr  @var string $error  @var bool $forced */ ?>
<header class="adm-head"><div><p class="adm-eyebrow"><a href="<?= e(url('/admin/account')) ?>"><?= e(__('Konto')) ?></a></p><h1><?= e(__('Zwei-Faktor-Anmeldung einrichten')) ?></h1>
  <p class="adm-muted"><?= e($forced ? __('Für Ihr Konto ist die Zwei-Faktor-Anmeldung vorgeschrieben. Bitte richten Sie sie jetzt ein – danach geht es weiter.') : __('Zusätzlich zum Passwort fragt die Anmeldung dann einen Code aus einer App auf Ihrem Telefon ab.')) ?></p></div></header>
<div class="adm-card adm-narrow tf-setup">
  <ol class="tf-steps">
    <li><?= e(__('Authenticator-App öffnen (z. B. 2FAS, Aegis, Google oder Microsoft Authenticator, 1Password) und „Konto hinzufügen“ wählen.')) ?></li>
    <li><?= e(__('QR-Code scannen oder den Schlüssel von Hand eingeben.')) ?>
      <img class="tf-qr" src="<?= e($qr) ?>" width="200" height="200" alt="<?= e(__('QR-Code für die Authenticator-App')) ?>">
      <p class="adm-secret"><code id="tf-secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code><button type="button" class="adm-btn adm-btn--small" data-copy="#tf-secret"><?= e(__('Kopieren')) ?></button></p></li>
    <li><?= e(__('Den angezeigten 6-stelligen Code hier eingeben:')) ?>
      <form method="post" action="<?= e(url('/admin/account/2fa/enable')) ?>" novalidate class="tf-confirm">
        <?= csrf_field() ?>
        <?php if ($error): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($error) ?></p><?php endif; ?>
        <div class="f"><label for="tf-code"><?= e(__('Code aus der App')) ?></label><input id="tf-code" name="code" inputmode="numeric" autocomplete="one-time-code" required maxlength="8" class="tf-code" autofocus></div>
        <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Aktivieren')) ?></button>
      </form></li>
  </ol>
</div>
<?php if ($forced): ?><form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--ghost" type="submit"><?= e(__('Abmelden')) ?></button></form><?php endif; ?>
