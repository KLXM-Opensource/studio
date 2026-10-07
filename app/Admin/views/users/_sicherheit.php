<?php
/** Benutzer & Rollen – Unterseite (eingebunden von users.php, Variablen von dort) */
?>
<?php // Anmeldung & Sicherheit (Core\Mfa): erlaubte Verfahren, zweiter Faktor je Rolle, Übergangsfrist
$pol = $policy ?? \Core\Mfa::policy(); ?>
<form class="set-group us-2fa" id="zwei-faktor" method="post" action="<?= e(url('/admin/users/2fa')) ?>">
  <?= csrf_field() ?><input type="hidden" name="methods" value="1">
  <h2 class="set-group__title"><?= e(__('Anmeldung & Sicherheit')) ?></h2>
  <p class="set-group__note"><?= e(__('Welche Verfahren dürfen Konten dieser Website nutzen, und für welche Rollen ist ein zweiter Faktor Pflicht? Eingerichtete Verfahren sehen Sie in der Liste oben („2FA“, „Passkey“).')) ?></p>
  <h3 class="set-group__title set-group__title--sub"><?= e(__('Erlaubte Verfahren')) ?></h3>
  <div class="set-list">
    <div class="f f--bool"><label class="f-check"><input type="checkbox" role="switch" name="totp" value="1"<?= $pol['totp'] ? ' checked' : '' ?>> <span><?= e(__('Authenticator-App (6-stelliger Code)')) ?></span></label></div>
    <div class="f f--bool"><label class="f-check"><input type="checkbox" role="switch" name="passkey" value="1"<?= $pol['passkey'] ? ' checked' : '' ?>> <span><?= e(__('Passkeys (Touch ID, Face ID, Windows Hello, Android, Passwortmanager, Sicherheitsschlüssel)')) ?></span></label></div>
    <div class="f f--bool"><label class="f-check"><input type="checkbox" role="switch" name="passwordless" value="1" aria-describedby="us-pwl-h"<?= $pol['passwordless'] ? ' checked' : '' ?>> <span><?= e(__('Anmeldung ohne Passwort mit Passkey')) ?></span></label>
      <p class="f-help" id="us-pwl-h"><?= e(__('Zählt als Zwei-Faktor-Anmeldung (Gerät + Fingerabdruck, Gesicht oder PIN). Setzt „Passkeys“ voraus.')) ?></p></div>
  </div>
  <h3 class="set-group__title set-group__title--sub"><?= e(__('Zweiter Faktor je Rolle')) ?></h3>
  <div class="set-list">
  <?php foreach ($roles as $k => $ro): $cur = $pol['roles'][$k] ?? ''; ?>
    <?php if ($k === 'network'): ?>
    <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($ro['name']) ?></span></div><div class="set-row__ctl"><?= e(__('immer – Richtlinie in der Netzwerk-Übersicht')) ?></div></div>
    <?php else: ?>
    <div class="f f--inline"><label for="us-req-<?= e($k) ?>"><?= e($ro['name']) ?></label>
      <select id="us-req-<?= e($k) ?>" name="req[<?= e($k) ?>]">
        <option value=""<?= $cur === '' ? ' selected' : '' ?>><?= e(__('freiwillig')) ?></option>
        <option value="any"<?= $cur === 'any' ? ' selected' : '' ?>><?= e(__('verlangt (App oder Passkey)')) ?></option>
        <option value="passkey"<?= $cur === 'passkey' ? ' selected' : '' ?>><?= e(__('nur Passkey')) ?></option>
      </select></div>
    <?php endif; ?>
  <?php endforeach; ?>
    <div class="f f--inline"><label for="us-grace"><?= e(__('Übergangsfrist in Tagen')) ?></label><input id="us-grace" name="grace_days" type="number" min="0" max="365" step="1" inputmode="numeric" value="<?= (int) $pol['grace_days'] ?>" aria-describedby="us-grace-h">
      <p class="f-help" id="us-grace-h"><?= e(__('0 = bei der nächsten Anmeldung einrichten. Sonst erinnert die Anmeldung bis zum Ablauf der Frist daran; danach geht es erst nach der Einrichtung weiter.')) ?></p></div>
  </div>
  <p class="set-group__note"><?= e(__('„Nur Passkey“ ist phishing-resistent: Code-Apps genügen dann nicht.')) ?></p>
  <div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Speichern')) ?></button></div>
</form>
