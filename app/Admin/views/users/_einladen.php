<?php
/** Benutzer & Rollen – Unterseite (eingebunden von users.php, Variablen von dort) */
?>
<div class="us-forms">
<?php if ($inviteRoles): // Person einladen (Core\Invites) – E-Mail mit Link, Passkey und/oder Passwort legt die Person selbst fest ?>
<form class="set-group inv-form" id="einladen" method="post" action="<?= e(url('/admin/users/invite')) ?>" novalidate>
  <?= csrf_field() ?>
  <h2 class="set-group__title"><?= e(__('Person einladen')) ?></h2>
  <?= $err('inv_invite') ?>
  <div class="set-list">
    <div class="f f--inline<?= isset($errors['inv_email']) ? ' f--error' : '' ?>"><label for="inv-email"><?= e(__('E-Mail-Adresse')) ?> <span class="req">*</span></label><input id="inv-email" name="email" type="email" required autocomplete="off" value="<?= e($inv['email'] ?? '') ?>"><?= $err('inv_email') ?></div>
    <div class="f f--inline<?= isset($errors['inv_name']) ? ' f--error' : '' ?>"><label for="inv-name"><?= e(__('Name (optional)')) ?></label><input id="inv-name" name="name" maxlength="<?= \Core\Invites::NAME_MAX ?>" autocomplete="off" value="<?= e($inv['name'] ?? '') ?>"><?= $err('inv_name') ?></div>
    <div class="f f--inline<?= isset($errors['inv_role']) ? ' f--error' : '' ?>"><label for="inv-role"><?= e(__('Rolle')) ?></label><select id="inv-role" name="role"><?php foreach ($inviteRoles as $k => $label): ?><option value="<?= e($k) ?>"<?= ($inv['role'] ?? (isset($inviteRoles['editor']) ? 'editor' : '')) === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $err('inv_role') ?></div>
    <?php if (count($locales) > 1): ?>
    <div class="f f--inline"><label for="inv-locale"><?= e(__('Sprache der Einladung')) ?></label><select id="inv-locale" name="locale"><?php foreach ($locales as $k => $label): ?><option value="<?= e($k) ?>"<?= ($inv['locale'] ?? $defLocale) === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="f<?= isset($errors['inv_message']) ? ' f--error' : '' ?>"><label for="inv-msg"><?= e(__('Persönliche Nachricht (optional)')) ?></label><textarea id="inv-msg" name="message" rows="3" maxlength="<?= \Core\Invites::MESSAGE_MAX ?>" aria-describedby="inv-msg-h"><?= e($inv['message'] ?? '') ?></textarea>
      <p class="f-help" id="inv-msg-h"><?= e(__('Reiner Text, höchstens {n} Zeichen. Erscheint als Zitat in der E-Mail.', ['n' => \Core\Invites::MESSAGE_MAX])) ?></p><?= $err('inv_message') ?></div>
  </div>
  <p class="set-group__note"><?= e(__('Die Person bekommt eine E-Mail mit einem Link ({n} Tage gültig) und richtet ihr Konto selbst ein – mit Passkey oder Passwort.', ['n' => \Core\Invites::days()])) ?></p>
  <div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Einladung senden')) ?></button></div>
</form>
<?php endif; ?>
<form class="set-group" id="anlegen" method="post" action="<?= e(url('/admin/users')) ?>" novalidate>
  <?= csrf_field() ?>
  <h2 class="set-group__title"><?= e(__('Neuen Benutzer anlegen')) ?></h2>
  <div class="set-list">
    <div class="f f--inline"><label for="u-name"><?= e(__('Name')) ?></label><input id="u-name" name="name" value="<?= e($createOld['name'] ?? '') ?>"></div>
    <div class="f f--inline<?= isset($errors['email']) ? ' f--error' : '' ?>"><label for="u-email"><?= e(__('E-Mail-Adresse')) ?> <span class="req">*</span></label><input id="u-email" name="email" type="email" required value="<?= e($createOld['email'] ?? '') ?>"><?= $err('email') ?></div>
    <div class="f f--inline<?= isset($errors['password']) ? ' f--error' : '' ?>"><label for="u-pw"><?= e(__('Startpasswort (mind. 12 Zeichen)')) ?> <span class="req">*</span></label><input id="u-pw" name="password" type="text" autocomplete="off" required><?= $err('password') ?></div>
    <div class="f f--inline"><label for="u-role"><?= e(__('Rolle')) ?></label><select id="u-role" name="role"><?php foreach ($roles as $k => $ro): if ($k === 'network') continue; ?><option value="<?= e($k) ?>"<?= ($createOld['role'] ?? 'editor') === $k ? ' selected' : '' ?>><?= e($ro['name']) ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Anlegen')) ?></button></div>
</form>
</div>

<?php if ($invites): ?>
<section class="set-group" id="einladungen">
  <h2 class="set-group__title"><?= e(__('Einladungen')) ?></h2>
  <div class="set-list inv-rows">
  <?php foreach ($invites as $iv): $ivRole = $roles[$iv['role']]['name'] ?? $iv['role']; ?>
    <div class="set-row inv-row<?= $iv['expired'] ? ' is-expired' : '' ?>">
      <span class="set-avatar set-avatar--ghost" aria-hidden="true"><?= e($initials($iv)) ?></span>
      <div class="set-row__main"><span class="set-row__label"><?= e($iv['name'] ?: $iv['email']) ?>
          <?php if ($iv['expired']): ?><span class="adm-badge adm-badge--muted inv-badge is-expired"><?= e(__('Einladung abgelaufen')) ?></span>
          <?php else: ?><span class="adm-badge inv-badge"><?= e(__('Eingeladen – wartet')) ?></span><?php endif; ?></span>
        <span class="set-row__sub"><?= e($iv['email']) ?> · <?= e($ivRole) ?> · <?= e(date('d.m.Y', strtotime((string) $iv['created_at']))) ?><?php if ($iv['invited_by_name']): ?> <?= e(__('von {name}', ['name' => $iv['invited_by_name']])) ?><?php endif; ?>
          · <?= e($iv['expired'] ? __('abgelaufen am {date}', ['date' => date('d.m.Y', (int) $iv['expires_at'])]) : __('gültig bis {date}', ['date' => date('d.m.Y', (int) $iv['expires_at'])])) ?></span></div>
      <div class="set-row__ctl"><?php if (isset($inviteRoles[$iv['role']])): ?>
        <form method="post" action="<?= e(url('/admin/users/invites/' . $iv['id'] . '/resend')) ?>" data-confirm="<?= e(__('Einladung an {email} erneut senden? Der bisherige Link gilt dann nicht mehr.', ['email' => $iv['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small"><?= e(__('Erneut senden')) ?></button></form>
        <form method="post" action="<?= e(url('/admin/users/invites/' . $iv['id'] . '/revoke')) ?>" data-confirm="<?= e(__('Einladung an {email} zurückziehen? Der Link gilt dann nicht mehr.', ['email' => $iv['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--danger-text"><?= e(__('Zurückziehen')) ?></button></form>
        <?php else: ?><span class="adm-muted us-readonly"><?= e(__('höhere Rolle')) ?></span><?php endif; ?></div>
    </div>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

