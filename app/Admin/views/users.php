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
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?></p><h1><?= e(__('Benutzer & Rollen')) ?></h1>
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

<div class="adm-grid2 adm-grid2--wide">
  <div class="adm-card adm-card--flush">
    <table class="adm-table">
      <thead><tr><th scope="col"><?= e(__('Benutzer')) ?></th><th scope="col"><?= e(__('Rolle')) ?></th><th scope="col"><?= e(__('Letzte Anmeldung')) ?></th><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <?php if ($u['role'] === 'network' || !empty($u['network_uid'])): // Netzwerk-Administration: nur lesen (zentral verwaltet) ?>
        <tr class="us-netrow"><td><strong><?= e($u['name'] ?: '–') ?></strong> <span class="adm-badge us-net" title="<?= e(__('Zentral verwaltetes Konto der Netzwerk-Administration')) ?>"><?= e(__('Netzwerk')) ?></span><br><span class="adm-muted"><?= e($u['email']) ?></span></td>
          <td><?= e($roles['network']['name'] ?? __('Netzwerk-Administration')) ?></td>
          <td class="adm-muted"><?= $u['last_login'] ? e(date('d.m.Y H:i', strtotime($u['last_login']))) : e(__('nie')) ?></td>
          <td class="adm-actions"><?php if ((int) $u['id'] === (int) $user['id']): ?><span class="adm-badge"><?= e(__('Sie')) ?></span><?php else: ?><span class="adm-muted us-readonly"><?= e(__('zentral verwaltet')) ?></span><?php endif; ?></td></tr>
        <?php continue; endif; ?>
        <?php $uPk = (int) ($u['passkeys'] ?? 0); $uMfa = (int) ($u['totp_enabled'] ?? 0) || $uPk > 0; ?>
        <tr><td><strong><?= e($u['name'] ?: '–') ?></strong><?php if ((int) ($u['totp_enabled'] ?? 0)): ?> <span class="adm-badge adm-badge--muted" title="<?= e(__('Zwei-Faktor-Anmeldung aktiv')) ?>">2FA</span><?php endif; ?><?php if ($uPk): ?> <span class="adm-badge adm-badge--muted" title="<?= e(__('{n} Passkey(s) eingerichtet', ['n' => $uPk])) ?>"><?= e(__('Passkey')) ?><?= $uPk > 1 ? ' ×' . $uPk : '' ?></span><?php endif; ?><br><span class="adm-muted"><?= e($u['email']) ?></span></td>
          <td><form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/role')) ?>" class="us-role"><?= csrf_field() ?>
            <select name="role" aria-label="<?= e(__('Rolle von {name}', ['name' => $u['email']])) ?>" data-autosubmit>
              <?php foreach ($roles as $k => $ro): if ($k === 'network') continue; ?><option value="<?= e($k) ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= e($ro['name']) ?></option><?php endforeach; ?>
            </select><noscript><button class="adm-btn adm-btn--small"><?= e(__('Ändern')) ?></button></noscript></form></td>
          <td class="adm-muted"><?= $u['last_login'] ? e(date('d.m.Y H:i', strtotime($u['last_login']))) : e(__('nie')) ?></td>
          <td class="adm-actions"><?php if ((int) $u['id'] !== (int) $user['id']): ?>
            <?php if ($uMfa): ?><form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/2fa-reset')) ?>" data-confirm="<?= e(__('Zwei-Faktor-Anmeldung von {email} zurücksetzen (App-Code und alle Passkeys)? Alle Sitzungen des Kontos enden; verlangt die Rolle 2FA, wird sie bei der nächsten Anmeldung neu eingerichtet.', ['email' => $u['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('2FA zurücksetzen')) ?></button></form><?php endif; ?>
            <form method="post" action="<?= e(url('/admin/users/' . $u['id'] . '/delete')) ?>" data-confirm="<?= e(__('Benutzer {email} löschen?', ['email' => $u['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form>
            <?php else: ?><span class="adm-badge"><?= e(__('Sie')) ?></span><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <?php if ($invites): ?>
      <tbody id="einladungen" class="inv-rows">
      <?php foreach ($invites as $iv): $ivRole = $roles[$iv['role']]['name'] ?? $iv['role']; ?>
        <tr class="inv-row<?= $iv['expired'] ? ' is-expired' : '' ?>"><td><strong><?= e($iv['name'] ?: '–') ?></strong>
            <?php if ($iv['expired']): ?><span class="adm-badge adm-badge--muted inv-badge is-expired"><?= e(__('Einladung abgelaufen')) ?></span>
            <?php else: ?><span class="adm-badge inv-badge"><?= e(__('Eingeladen – wartet')) ?></span><?php endif; ?><br><span class="adm-muted"><?= e($iv['email']) ?></span></td>
          <td><?= e($ivRole) ?></td>
          <td class="adm-muted inv-meta"><?= e(date('d.m.Y', strtotime((string) $iv['created_at']))) ?><?php if ($iv['invited_by_name']): ?> · <?= e(__('von {name}', ['name' => $iv['invited_by_name']])) ?><?php endif; ?>
            <br><?= e($iv['expired'] ? __('abgelaufen am {date}', ['date' => date('d.m.Y', (int) $iv['expires_at'])]) : __('gültig bis {date}', ['date' => date('d.m.Y', (int) $iv['expires_at'])])) ?></td>
          <td class="adm-actions"><?php if (isset($inviteRoles[$iv['role']])): ?>
            <form method="post" action="<?= e(url('/admin/users/invites/' . $iv['id'] . '/resend')) ?>" data-confirm="<?= e(__('Einladung an {email} erneut senden? Der bisherige Link gilt dann nicht mehr.', ['email' => $iv['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Erneut senden')) ?></button></form>
            <form method="post" action="<?= e(url('/admin/users/invites/' . $iv['id'] . '/revoke')) ?>" data-confirm="<?= e(__('Einladung an {email} zurückziehen? Der Link gilt dann nicht mehr.', ['email' => $iv['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Zurückziehen')) ?></button></form>
            <?php else: ?><span class="adm-muted us-readonly"><?= e(__('höhere Rolle')) ?></span><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <?php endif; ?>
    </table>
  </div>
  <div class="us-side">
  <?php if ($inviteRoles): // Person einladen (Core\Invites) – E-Mail mit Link, Passkey und/oder Passwort legt die Person selbst fest ?>
  <form class="adm-card inv-form" id="einladen" method="post" action="<?= e(url('/admin/users/invite')) ?>" novalidate>
    <?= csrf_field() ?>
    <h2><?= e(__('Person einladen')) ?></h2>
    <p class="adm-muted"><?= e(__('Die Person bekommt eine E-Mail mit einem Link ({n} Tage gültig) und richtet ihr Konto selbst ein – mit Passkey oder Passwort.', ['n' => \Core\Invites::days()])) ?></p>
    <?= $err('inv_invite') ?>
    <div class="f<?= isset($errors['inv_email']) ? ' f--error' : '' ?>"><label for="inv-email"><?= e(__('E-Mail-Adresse')) ?> <span class="req">*</span></label><input id="inv-email" name="email" type="email" required autocomplete="off" value="<?= e($inv['email'] ?? '') ?>"><?= $err('inv_email') ?></div>
    <div class="f<?= isset($errors['inv_name']) ? ' f--error' : '' ?>"><label for="inv-name"><?= e(__('Name (optional)')) ?></label><input id="inv-name" name="name" maxlength="<?= \Core\Invites::NAME_MAX ?>" autocomplete="off" value="<?= e($inv['name'] ?? '') ?>"><?= $err('inv_name') ?></div>
    <div class="f<?= isset($errors['inv_role']) ? ' f--error' : '' ?>"><label for="inv-role"><?= e(__('Rolle')) ?></label><select id="inv-role" name="role"><?php foreach ($inviteRoles as $k => $label): ?><option value="<?= e($k) ?>"<?= ($inv['role'] ?? (isset($inviteRoles['editor']) ? 'editor' : '')) === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select><?= $err('inv_role') ?></div>
    <?php if (count($locales) > 1): ?>
    <div class="f"><label for="inv-locale"><?= e(__('Sprache der Einladung')) ?></label><select id="inv-locale" name="locale"><?php foreach ($locales as $k => $label): ?><option value="<?= e($k) ?>"<?= ($inv['locale'] ?? $defLocale) === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <?php endif; ?>
    <div class="f<?= isset($errors['inv_message']) ? ' f--error' : '' ?>"><label for="inv-msg"><?= e(__('Persönliche Nachricht (optional)')) ?></label><textarea id="inv-msg" name="message" rows="3" maxlength="<?= \Core\Invites::MESSAGE_MAX ?>" aria-describedby="inv-msg-h"><?= e($inv['message'] ?? '') ?></textarea>
      <p class="f-help" id="inv-msg-h"><?= e(__('Reiner Text, höchstens {n} Zeichen. Erscheint als Zitat in der E-Mail.', ['n' => \Core\Invites::MESSAGE_MAX])) ?></p><?= $err('inv_message') ?></div>
    <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Einladung senden')) ?></button>
  </form>
  <?php endif; ?>
  <form class="adm-card" method="post" action="<?= e(url('/admin/users')) ?>" novalidate>
    <?= csrf_field() ?>
    <h2><?= e(__('Neuen Benutzer anlegen')) ?></h2>
    <div class="f"><label for="u-name"><?= e(__('Name')) ?></label><input id="u-name" name="name" value="<?= e($createOld['name'] ?? '') ?>"></div>
    <div class="f<?= isset($errors['email']) ? ' f--error' : '' ?>"><label for="u-email"><?= e(__('E-Mail-Adresse')) ?> <span class="req">*</span></label><input id="u-email" name="email" type="email" required value="<?= e($createOld['email'] ?? '') ?>"><?= $err('email') ?></div>
    <div class="f<?= isset($errors['password']) ? ' f--error' : '' ?>"><label for="u-pw"><?= e(__('Startpasswort (mind. 12 Zeichen)')) ?> <span class="req">*</span></label><input id="u-pw" name="password" type="text" autocomplete="off" required><?= $err('password') ?></div>
    <div class="f"><label for="u-role"><?= e(__('Rolle')) ?></label><select id="u-role" name="role"><?php foreach ($roles as $k => $ro): if ($k === 'network') continue; ?><option value="<?= e($k) ?>"<?= ($createOld['role'] ?? 'editor') === $k ? ' selected' : '' ?>><?= e($ro['name']) ?></option><?php endforeach; ?></select></div>
    <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Anlegen')) ?></button>
  </form>
  </div>
</div>

<?php // Anmeldung & Sicherheit (Core\Mfa): erlaubte Verfahren, zweiter Faktor je Rolle, Übergangsfrist
$pol = $policy ?? \Core\Mfa::policy(); ?>
<form class="adm-card us-2fa" id="zwei-faktor" method="post" action="<?= e(url('/admin/users/2fa')) ?>">
  <?= csrf_field() ?><input type="hidden" name="methods" value="1">
  <h2><?= e(__('Anmeldung & Sicherheit')) ?></h2>
  <p class="adm-muted"><?= e(__('Welche Verfahren dürfen Konten dieser Website nutzen, und für welche Rollen ist ein zweiter Faktor Pflicht? Eingerichtete Verfahren sehen Sie in der Liste oben („2FA“, „Passkey“).')) ?></p>
  <fieldset class="us-2fa__set">
    <legend><?= e(__('Erlaubte Verfahren')) ?></legend>
    <label class="f-check"><input type="checkbox" name="totp" value="1"<?= $pol['totp'] ? ' checked' : '' ?>> <span><?= e(__('Authenticator-App (6-stelliger Code)')) ?></span></label>
    <label class="f-check"><input type="checkbox" name="passkey" value="1"<?= $pol['passkey'] ? ' checked' : '' ?>> <span><?= e(__('Passkeys (Touch ID, Face ID, Windows Hello, Android, Passwortmanager, Sicherheitsschlüssel)')) ?></span></label>
    <label class="f-check"><input type="checkbox" name="passwordless" value="1" aria-describedby="us-pwl-h"<?= $pol['passwordless'] ? ' checked' : '' ?>> <span><?= e(__('Anmeldung ohne Passwort mit Passkey')) ?></span></label>
    <p class="f-help" id="us-pwl-h"><?= e(__('Zählt als Zwei-Faktor-Anmeldung (Gerät + Fingerabdruck, Gesicht oder PIN). Setzt „Passkeys“ voraus.')) ?></p>
  </fieldset>
  <fieldset class="us-2fa__set">
    <legend><?= e(__('Zweiter Faktor je Rolle')) ?></legend>
    <div class="us-2fa__roles us-2fa__grid">
    <?php foreach ($roles as $k => $ro): $cur = $pol['roles'][$k] ?? ''; ?>
      <?php if ($k === 'network'): ?>
      <p class="us-2fa__role"><span><?= e($ro['name']) ?></span> <small class="adm-muted"><?= e(__('immer – Richtlinie in der Netzwerk-Übersicht')) ?></small></p>
      <?php else: ?>
      <div class="us-2fa__role"><label for="us-req-<?= e($k) ?>"><?= e($ro['name']) ?></label>
        <select id="us-req-<?= e($k) ?>" name="req[<?= e($k) ?>]">
          <option value=""<?= $cur === '' ? ' selected' : '' ?>><?= e(__('freiwillig')) ?></option>
          <option value="any"<?= $cur === 'any' ? ' selected' : '' ?>><?= e(__('verlangt (App oder Passkey)')) ?></option>
          <option value="passkey"<?= $cur === 'passkey' ? ' selected' : '' ?>><?= e(__('nur Passkey')) ?></option>
        </select></div>
      <?php endif; ?>
    <?php endforeach; ?>
    </div>
    <p class="f-help"><?= e(__('„Nur Passkey“ ist phishing-resistent: Code-Apps genügen dann nicht.')) ?></p>
  </fieldset>
  <div class="f us-2fa__grace"><label for="us-grace"><?= e(__('Übergangsfrist in Tagen')) ?></label><input id="us-grace" name="grace_days" type="number" min="0" max="365" step="1" inputmode="numeric" value="<?= (int) $pol['grace_days'] ?>" aria-describedby="us-grace-h">
    <p class="f-help" id="us-grace-h"><?= e(__('0 = bei der nächsten Anmeldung einrichten. Sonst erinnert die Anmeldung bis zum Ablauf der Frist daran; danach geht es erst nach der Einrichtung weiter.')) ?></p></div>
  <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Speichern')) ?></button>
</form>

<section class="us-roles" id="rollen">
  <div class="us-roles__head"><h2><?= e(__('Rollen')) ?></h2><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/roles/new')) ?>">+ <?= e(__('Neue Rolle')) ?></a></div>
  <div class="us-rolegrid">
    <?php foreach ($roles as $k => $ro): $all = in_array('*', $ro['permissions'], true); ?>
    <article class="adm-card us-role-card">
      <header><strong><?= e($ro['name']) ?></strong><span class="adm-muted"><?= (int) ($count[$k] ?? 0) ?> <?= e(__('Benutzer')) ?></span></header>
      <?php if ($ro['description']): ?><p class="adm-muted"><?= e($ro['description']) ?></p><?php endif; ?>
      <ul class="us-perms">
        <?php if ($all): ?><li class="is-all"><?= e(__('Alle Rechte')) ?></li>
        <?php else: foreach ($ro['permissions'] as $p): if (!isset($labels[$p])) continue; ?><li><?= e($labels[$p]) ?></li><?php endforeach; if (!$ro['permissions']): ?><li class="is-none"><?= e(__('Keine Rechte')) ?></li><?php endif; endif; ?>
      </ul>
      <?php if (is_array($ro['tables'])): ?><p class="us-tables"><?= e(__('Datentabellen:')) ?> <?= e(implode(', ', $ro['tables']) ?: __('keine')) ?></p><?php endif; ?>
      <?php if ($k === 'network'): ?><p class="us-tables"><?= e(__('Zentral verwaltet: Konten der Netzwerk-Website, Anmeldung mit Zwei-Faktor-Anmeldung. Hier nicht vergebbar.')) ?></p><?php endif; ?>
      <?php if ($k !== 'admin' && $k !== 'network'): ?><div class="adm-row"><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/roles/' . $k)) ?>"><?= e(__('Rechte bearbeiten')) ?></a>
        <?php if (!$ro['builtin'] && empty($count[$k])): ?><form method="post" action="<?= e(url('/admin/roles/' . $k . '/delete')) ?>" data-confirm="<?= e(__('Rolle „{name}“ löschen?', ['name' => $ro['name']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form><?php endif; ?></div><?php endif; ?>
    </article>
    <?php endforeach; ?>
  </div>
</section>
