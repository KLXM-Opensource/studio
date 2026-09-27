<?php
/**
 * Einladung annehmen (öffentlich, Stil der Anmeldung): Name, dann Passkey (bevorzugt, wenn der Browser es kann) und/oder Passwort.
 * @var array $inv  @var string $token  @var ?array $role  @var array $errors  @var array $old  @var bool $passkey  @var bool $passwordless
 * @var ?string $twofa Pflicht der Rolle (any|passkey|null)  @var ?array $me angemeldetes Konto
 * @var bool $network Einladung als Netzwerk-Administration (Zugriff auf alle Websites, 2FA Pflicht, danach Netzwerk-Übersicht)
 */
$network ??= false;
$title = __('Einladung annehmen');
$err = fn($k) => isset($errors[$k]) ? '<p class="f-error" id="inv-err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$site = site_name();
$roleName = (string) ($role['name'] ?? $inv['role']);
$inviter = (string) ($inv['invited_by_name'] ?: __('Die Administration'));
$action = url('/admin/einladung/' . $token);
// Passkey zuerst, wenn er allein genügt (Anmeldung ohne Passwort erlaubt); sonst Passwort + Passkey als zweiter Faktor
$pkFirst = $passkey && $passwordless;
$pwRequired = !$passwordless || !$passkey;
?>
<div class="adm-auth inv-auth">
  <?php include __DIR__ . '/_brand.php'; ?>
  <section class="adm-card inv-card" aria-labelledby="inv-h">
    <p class="inv-eyebrow"><?= e($network ? __('Einladung · Netzwerk-Administration') : __('Einladung')) ?></p>
    <?php if ($network): ?>
    <h1 id="inv-h"><?= e(__('Willkommen in der Netzwerk-Administration')) ?></h1>
    <p class="inv-lead"><?= e(__('{name} hat Sie zur Netzwerk-Administration eingeladen.', ['name' => $inviter])) ?></p>
    <div class="inv-net" role="note">
      <p><strong><?= e(__('Netzwerk-Konto: Zugriff auf ALLE Websites')) ?></strong></p>
      <p><?= e(__('Mit diesem Konto verwalten Sie alle Websites dieser Installation mit allen Inhalten, Daten und Konten.')) ?></p>
      <p><strong><?= e(__('Die Zwei-Faktor-Anmeldung ist Pflicht: Sie richten sie direkt beim Annehmen ein.')) ?></strong></p>
    </div>
    <?php else: ?>
    <h1 id="inv-h"><?= e(__('Willkommen bei {site}', ['site' => $site])) ?></h1>
    <p class="inv-lead"><?= e(__('{name} hat Sie als {role} eingeladen.', ['name' => $inviter, 'role' => $roleName])) ?></p>
    <?php endif; ?>
    <?php if (!empty($inv['message'])): ?>
    <blockquote class="inv-quote"><p><?= nl2br(e($inv['message'])) ?></p><footer>– <?= e($inviter) ?></footer></blockquote>
    <?php endif; ?>

    <?php if ($me): // Bereits angemeldet: nicht versehentlich das Konto wechseln ?>
    <p class="adm-flash adm-flash--info" role="status"><?= e(__('Sie sind als {email} angemeldet. Um die Einladung für {invited} anzunehmen, melden Sie sich zuerst ab und öffnen den Link dann erneut.', ['email' => $me['email'], 'invited' => $inv['email']])) ?></p>
    <form method="post" action="<?= e(url('/admin/logout')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--block" type="submit"><?= e(__('Abmelden')) ?></button></form>
    <?php else: ?>
    <form class="inv-form" method="post" action="<?= e($action) ?>" novalidate data-invite="<?= e($action) ?>"<?= $passkey ? ' data-passkey="1"' : '' ?><?= $pwRequired ? ' data-pw-required="1"' : '' ?>>
      <?= csrf_field() ?>
      <?php if (isset($errors['form'])): ?><p class="adm-flash adm-flash--error" role="alert"><?= e($errors['form']) ?></p><?php endif; ?>
      <div class="f"><label for="inv-email"><?= e(__('E-Mail-Adresse')) ?></label><input id="inv-email" type="email" value="<?= e($inv['email']) ?>" autocomplete="username" readonly aria-describedby="inv-email-h">
        <p class="f-help" id="inv-email-h"><?= e(__('Mit dieser Adresse melden Sie sich künftig an.')) ?></p></div>
      <div class="f<?= isset($errors['name']) ? ' f--error' : '' ?>"><label for="inv-name"><?= e(__('Ihr Name')) ?> <span class="req">*</span></label><input id="inv-name" name="name" required maxlength="<?= \Core\Invites::NAME_MAX ?>" autocomplete="name" value="<?= e($old['name'] ?? ($inv['name'] ?? '')) ?>"<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="inv-err-name"' : '' ?>><?= $err('name') ?></div>

      <?php if ($pkFirst): // Passkey – erscheint nur mit JavaScript und Passkey-fähigem Browser (resources/js/invite.js) ?>
      <div class="inv-pk" data-inv-pk hidden>
        <h2><?= e(__('Mit Passkey anmelden')) ?> <span class="adm-badge"><?= e(__('empfohlen')) ?></span></h2>
        <p class="adm-muted"><?= e(__('Ein Passkey ersetzt das Passwort: Sie melden sich mit Fingerabdruck, Gesicht oder der PIN Ihres Geräts an. Sicher gegen Phishing, nichts zu merken.')) ?></p>
        <button class="adm-btn adm-btn--primary adm-btn--block" type="button" data-inv-pk-btn><?= e(__('Passkey erstellen und loslegen')) ?></button>
        <p class="adm-flash pk-msg" data-pk-msg aria-live="polite" hidden></p>
        <p class="pk-or"><span><?= e(__('oder mit Passwort')) ?></span></p>
      </div>
      <?php endif; ?>

      <fieldset class="inv-pwset">
        <legend><?= e($pwRequired ? __('Passwort festlegen') : __('Passwort festlegen (optional mit Passkey)')) ?></legend>
        <div class="f<?= isset($errors['password']) ? ' f--error' : '' ?>"><label for="inv-pw"><?= e(__('Passwort (mind. 12 Zeichen)')) ?></label>
          <div class="inv-pwfield"><input id="inv-pw" name="password" type="password" autocomplete="new-password" minlength="12" aria-describedby="inv-pw-h<?= isset($errors['password']) ? ' inv-err-password' : '' ?>"<?= isset($errors['password']) ? ' aria-invalid="true"' : '' ?>>
            <button class="adm-btn adm-btn--small adm-btn--ghost inv-reveal" type="button" data-inv-reveal aria-pressed="false" aria-controls="inv-pw inv-pw2" hidden><?= e(__('Anzeigen')) ?></button></div>
          <div class="inv-meter" data-inv-meter hidden><span></span></div>
          <p class="f-help" id="inv-pw-h" data-inv-strength aria-live="polite"><?= e(__('Am besten eine Passphrase aus mehreren Wörtern oder ein Passwort aus Ihrem Passwortmanager.')) ?></p><?= $err('password') ?></div>
        <div class="f<?= isset($errors['password2']) ? ' f--error' : '' ?>"><label for="inv-pw2"><?= e(__('Passwort wiederholen')) ?></label><input id="inv-pw2" name="password2" type="password" autocomplete="new-password"<?= isset($errors['password2']) ? ' aria-invalid="true" aria-describedby="inv-err-password2"' : '' ?>><?= $err('password2') ?></div>
        <button class="adm-btn<?= $pkFirst ? '' : ' adm-btn--primary' ?> adm-btn--block" type="submit" data-inv-pw-btn><?= e(__('Mit Passwort fortfahren')) ?></button>
      </fieldset>

      <?php if ($passkey && !$passwordless): // Anmeldung ohne Passwort ausgeschaltet: Passkey als zweiter Faktor zusätzlich zum Passwort ?>
      <div class="inv-pk inv-pk--second" data-inv-pk hidden>
        <p class="pk-or"><span><?= e(__('oder')) ?></span></p>
        <p class="adm-muted"><?= e(__('Zusätzlich einen Passkey als zweiten Faktor einrichten – bei der Anmeldung bestätigen Sie dann mit Fingerabdruck, Gesicht oder Geräte-PIN.')) ?></p>
        <button class="adm-btn adm-btn--block" type="button" data-inv-pk-btn><?= e(__('Passwort festlegen und Passkey erstellen')) ?></button>
        <p class="adm-flash pk-msg" data-pk-msg aria-live="polite" hidden></p>
      </div>
      <?php endif; ?>
    </form>
    <?php if ($network && $twofa): ?><p class="inv-note"><?= e($twofa === 'passkey' ? __('Für Netzwerk-Konten ist ein Passkey vorgeschrieben. Legen Sie ihn hier an – sonst direkt im nächsten Schritt.')
      : __('Ein Passkey erfüllt den zweiten Faktor bereits. Mit Passwort richten Sie im nächsten Schritt die Authenticator-App ein.')) ?></p>
    <?php elseif ($twofa): ?><p class="inv-note"><?= e($twofa === 'passkey' ? __('Für Ihre Rolle ist ein Passkey vorgeschrieben. Legen Sie ihn hier an – oder direkt nach der ersten Anmeldung.')
      : __('Für Ihre Rolle ist ein zweiter Faktor vorgeschrieben. Ein Passkey erfüllt ihn; mit Passwort richten Sie ihn nach der ersten Anmeldung ein.')) ?></p><?php endif; ?>
    <p class="inv-note"><?= e(__('Die Einladung gilt bis {date}.', ['date' => \Core\Invites::date((int) $inv['expires_at'], \Core\I18n::locale())])) ?></p>
    <?php endif; ?>
  </section>
  <?php if (!$me): // Passkey, Passwort anzeigen, Stärke-Hinweis (resources/js/invite.js) ?><script src="<?= e(asset('js/invite.js')) ?>" defer></script><?php endif; ?>
  <p class="adm-muted"><a href="<?= e(url('/')) ?>">← <?= e(__('Zur Website')) ?></a></p>
  <p class="adm-product"><?= cms_mark() ?></p>
</div>
