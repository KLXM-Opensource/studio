<?php
/** Benutzer und Rollen. @var array $users  @var array $errors  @var array $old  @var array $user */
use Core\Permissions;

$roles = Permissions::roles();
$catalog = Permissions::catalog();
$labels = array_merge(...array_values($catalog));
$count = array_count_values(array_column($users, 'role'));
$err = fn($k) => isset($errors[$k]) ? '<p class="f-error">' . e($errors[$k]) . '</p>' : '';
// Einladungen (Core\Invites): offene Einladungen, Rollen zur Auswahl, Formularwerte nach Fehlern
$invites ??= [];
$inviteRoles ??= [];
$inv = !empty($old['_invite']) ? $old : [];
$createOld = $inv ? [] : $old;
$locales = \Core\I18n::available();
$defLocale = (string) (setting('sys.admin_locale') ?: \Core\I18n::SOURCE);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?> › <?= e(__('Einstellungen')) ?></p><h1><?= e(__('Benutzer & Rollen')) ?></h1>
    <p class="adm-muted"><?= e(__('Jede Person bekommt ein eigenes Konto. Was sie darf, bestimmt ihre Rolle – Rollen lassen sich frei zusammenstellen.')) ?></p></div>
</header>

<?php if (!empty($inviteLink)): // E-Mail nicht zugestellt: Link einmal anzeigen (nur jetzt sichtbar, gespeichert ist nur ein Hash) ?>
<section class="adm-card adm-card--secret inv-link" id="einladung-link" role="alert">
  <h2><?= e(__('Einladung angelegt – E-Mail nicht zugestellt')) ?></h2>
  <p><?= e($inviteLink['error'] ? __('Die E-Mail an {email} konnte nicht gesendet werden: {error}', ['email' => $inviteLink['email'], 'error' => $inviteLink['error']])
    : __('Die E-Mail an {email} wurde nicht zugestellt (Testumgebung oder Versand deaktiviert – nur protokolliert bzw. umgeleitet).', ['email' => $inviteLink['email']])) ?></p>
  <p><?= e(__('Geben Sie den Link selbst weiter, z. B. per Messenger. Er ist nur jetzt sichtbar, gilt einmal und bis zum Ablauf der Einladung.')) ?>
    <?php if (can('system.manage')): ?><a href="<?= e(url('/admin/system#mail')) ?>"><?= e(__('E-Mail-Versand einrichten')) ?> →</a><?php endif; ?></p>
  <div class="adm-secret"><code id="inv-url"><?= e($inviteLink['url']) ?></code><button type="button" class="adm-btn adm-btn--small" data-copy="#inv-url"><?= e(__('Kopieren')) ?></button></div>
</section>
<?php endif; ?>

<?php
// Anfangsbuchstaben für den runden Platzhalter (wie Kontaktbilder in den macOS-Einstellungen)
$initials = function (array $u): string {
    $n = trim((string) ($u['name'] ?? ''));
    if ($n === '') return mb_strtoupper(mb_substr((string) $u['email'], 0, 1));
    $parts = preg_split('~\s+~u', $n) ?: [$n];
    return mb_strtoupper(mb_substr($parts[0], 0, 1) . (count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : ''));
};
$lastLogin = fn(array $u) => $u['last_login'] ? __('Letzte Anmeldung: {date}', ['date' => date('d.m.Y H:i', strtotime($u['last_login']))]) : __('Noch nie angemeldet');
?>
<div class="set-page us-page">
<section class="set-group" id="benutzer">
  <h2 class="set-group__title"><?= e(__('Benutzer')) ?> <span class="adm-muted">· <?= count($users) ?></span></h2>
  <div class="set-list us-list">
  <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $user['id']; $roleName = $roles[$u['role']]['name'] ?? $u['role']; ?>
    <?php if ($u['role'] === 'network' || !empty($u['network_uid'])): // Netzwerk-Administration: nur lesen (zentral verwaltet) ?>
    <div class="set-row us-row us-netrow">
      <span class="set-avatar" aria-hidden="true"><?= e($initials($u)) ?></span>
      <div class="set-row__main"><span class="set-row__label"><?= e($u['name'] ?: $u['email']) ?> <span class="adm-badge us-net" title="<?= e(__('Zentral verwaltetes Konto der Netzwerk-Administration')) ?>"><?= e(__('Netzwerk')) ?></span></span>
        <span class="set-row__sub"><?= e($u['email']) ?> · <?= e($lastLogin($u)) ?></span></div>
      <div class="set-row__ctl"><span class="adm-badge adm-badge--muted"><?= e($roles['network']['name'] ?? __('Netzwerk-Administration')) ?></span>
        <?= $self ? '<span class="adm-badge">' . e(__('Sie')) . '</span>' : '<span class="adm-muted us-readonly">' . e(__('zentral verwaltet')) . '</span>' ?></div>
    </div>
    <?php continue; endif; ?>
    <?php $uPk = (int) ($u['passkeys'] ?? 0); $uMfa = (int) ($u['totp_enabled'] ?? 0) || $uPk > 0; ?>
    <details class="us-item">
      <summary class="set-row us-row">
        <span class="set-avatar" aria-hidden="true"><?= e($initials($u)) ?></span>
        <span class="set-row__main"><span class="set-row__label"><?= e($u['name'] ?: $u['email']) ?><?php if ($self): ?> <span class="adm-badge"><?= e(__('Sie')) ?></span><?php endif; ?>
          <?php if ((int) ($u['totp_enabled'] ?? 0)): ?> <span class="adm-badge adm-badge--muted" title="<?= e(__('Zwei-Faktor-Anmeldung aktiv')) ?>">2FA</span><?php endif; ?><?php if ($uPk): ?> <span class="adm-badge adm-badge--muted" title="<?= e(__('{n} Passkey(s) eingerichtet', ['n' => $uPk])) ?>"><?= e(__('Passkey')) ?><?= $uPk > 1 ? ' ×' . $uPk : '' ?></span><?php endif; ?></span>
          <span class="set-row__sub"><?= e($u['email']) ?> · <?= e($lastLogin($u)) ?></span></span>
        <span class="set-row__ctl"><span class="adm-badge us-rolebadge"><?= e($roleName) ?></span></span>
      </summary>
      <div class="us-detail">
        <?php if (!$self): ?>
        <div class="set-row"><div class="set-row__main"><label class="set-row__label" for="us-role-<?= (int) $u['id'] ?>"><?= e(__('Rolle')) ?></label></div>
          <div class="set-row__ctl"><form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/role')) ?>" class="us-role"><?= csrf_field() ?>
            <select id="us-role-<?= (int) $u['id'] ?>" name="role" aria-label="<?= e(__('Rolle von {name}', ['name' => $u['email']])) ?>" data-autosubmit>
              <?php foreach ($roles as $k => $ro): if ($k === 'network') continue; ?><option value="<?= e($k) ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= e($ro['name']) ?></option><?php endforeach; ?>
            </select><noscript><button class="adm-btn adm-btn--small"><?= e(__('Ändern')) ?></button></noscript></form></div></div>
          <?php if (\Core\Invites::assignable($roles[$u['role']] ?? null, app()->auth->role())): // E-Mail-Adresse direkt ändern (Core\EmailChange::adminChange) ?>
          <form class="set-row us-mail__form" method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/email')) ?>"><?= csrf_field() ?>
            <div class="set-row__main"><label class="set-row__label" for="us-mail-<?= (int) $u['id'] ?>"><?= e(__('E-Mail ändern')) ?></label>
              <span class="set-row__sub"><?= e(__('Gilt sofort, ohne Bestätigung. Beide Adressen erhalten einen Hinweis, die Sitzungen des Kontos enden. Personen ändern ihre Adresse sonst selbst unter „Konto“.')) ?></span></div>
            <div class="set-row__ctl"><input id="us-mail-<?= (int) $u['id'] ?>" name="email" type="email" required maxlength="191" autocomplete="off" value="<?= e($u['email']) ?>" aria-label="<?= e(__('Neue E-Mail-Adresse für {name}', ['name' => $u['name'] ?: $u['email']])) ?>">
              <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Adresse ändern')) ?></button></div>
          </form>
          <?php endif; ?>
          <?php if ($uMfa): ?>
          <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Zwei-Faktor-Anmeldung')) ?></span><span class="set-row__sub"><?= e(__('App-Code und alle Passkeys entfernen – bei Verlust des Geräts.')) ?></span></div>
            <div class="set-row__ctl"><form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/2fa-reset')) ?>" data-confirm="<?= e(__('Zwei-Faktor-Anmeldung von {email} zurücksetzen (App-Code und alle Passkeys)? Alle Sitzungen des Kontos enden; verlangt die Rolle 2FA, wird sie bei der nächsten Anmeldung neu eingerichtet.', ['email' => $u['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small"><?= e(__('2FA zurücksetzen')) ?></button></form></div></div>
          <?php endif; ?>
          <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Konto löschen')) ?></span></div>
            <div class="set-row__ctl"><form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" data-confirm="<?= e(__('Benutzer {email} löschen?', ['email' => $u['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form></div></div>
        <?php else: ?>
          <a class="set-row set-row--link" href="<?= e(url('/admin/account')) ?>"><span class="set-row__main"><span class="set-row__label"><?= e(__('Ihr eigenes Konto')) ?></span><span class="set-row__sub"><?= e(__('Name, E-Mail, Passwort und Anmeldung ändern Sie unter „Konto“.')) ?></span></span></a>
        <?php endif; ?>
      </div>
    </details>
  <?php endforeach; ?>
  </div>
</section>

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

<section class="set-group us-roles" id="rollen">
  <div class="set-group__head"><h2 class="set-group__title"><?= e(__('Rollen')) ?></h2><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/roles/new')) ?>">+ <?= e(__('Neue Rolle')) ?></a></div>
  <div class="set-list">
  <?php foreach ($roles as $k => $ro):
      $all = in_array('*', $ro['permissions'], true);
      $permCount = $all ? null : count(array_filter($ro['permissions'], fn($p) => isset($labels[$p])));
      $sub = array_filter([$ro['description'] ?: null,
          $all ? __('Alle Rechte') : ($permCount ? __('{n} Rechte', ['n' => $permCount]) : __('Keine Rechte')),
          is_array($ro['tables']) ? __('Datentabellen:') . ' ' . (implode(', ', $ro['tables']) ?: __('keine')) : null,
          $k === 'network' ? __('Zentral verwaltet: Konten der Netzwerk-Website, Anmeldung mit Zwei-Faktor-Anmeldung. Hier nicht vergebbar.') : null]);
      $sub = array_unique($sub);
      $editable = $k !== 'admin' && $k !== 'network'; ?>
    <div class="set-row us-rolerow">
      <div class="set-row__main"><?php if ($editable): ?><a class="set-row__label us-rolelink" href="<?= e(url('/admin/roles/' . $k)) ?>"><?= e($ro['name']) ?></a><?php else: ?><span class="set-row__label"><?= e($ro['name']) ?></span><?php endif; ?>
        <span class="set-row__sub"><?= e(implode(' · ', $sub)) ?></span></div>
      <div class="set-row__ctl"><span class="adm-badge adm-badge--muted"><?= e(__('{n} Benutzer', ['n' => (int) ($count[$k] ?? 0)])) ?></span>
        <?php if ($editable && !$ro['builtin'] && empty($count[$k])): ?><form method="post" action="<?= e(url('/admin/roles/' . $k . '/delete')) ?>" data-confirm="<?= e(__('Rolle „{name}“ löschen?', ['name' => $ro['name']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form><?php endif; ?>
        <?php if ($editable): ?><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/roles/' . $k)) ?>"><?= e(__('Rechte bearbeiten')) ?></a><?php endif; ?></div>
    </div>
  <?php endforeach; ?>
  </div>
</section>
</div>
