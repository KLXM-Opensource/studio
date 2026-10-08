<?php
/** Benutzer & Rollen – Unterseite (eingebunden von users.php, Variablen von dort) */
?>
<section class="set-group" id="benutzer">
  <h2 class="set-group__title"><?= e(__('Benutzer')) ?> <span class="adm-muted">· <?= count($users) ?></span></h2>
  <div class="set-list us-list">
  <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $user['id']; $roleName = $roles[$u['role']]['name'] ?? $u['role']; ?>
    <?php if ($u['role'] === 'network' || !empty($u['network_uid'])): // Netzwerk-Administration: nur lesen (zentral verwaltet) ?>
    <div class="set-row us-row us-netrow">
      <?= \Core\Avatar::html($u, 'set-avatar adm-ava') ?>
      <div class="set-row__main"><span class="set-row__label"><?= e($u['name'] ?: $u['email']) ?> <span class="adm-badge us-net" title="<?= e(__('Zentral verwaltetes Konto der Netzwerk-Administration')) ?>"><?= e(__('Netzwerk')) ?></span></span>
        <span class="set-row__sub"><?= e($u['email']) ?> · <?= e($lastLogin($u)) ?></span></div>
      <div class="set-row__ctl"><span class="adm-badge adm-badge--muted"><?= e($roles['network']['name'] ?? __('Netzwerk-Administration')) ?></span>
        <?= $self ? '<span class="adm-badge">' . e(__('Sie')) . '</span>' : '<span class="adm-muted us-readonly">' . e(__('zentral verwaltet')) . '</span>' ?></div>
    </div>
    <?php continue; endif; ?>
    <?php $uPk = (int) ($u['passkeys'] ?? 0); $uMfa = (int) ($u['totp_enabled'] ?? 0) || $uPk > 0; ?>
    <details class="us-item">
      <summary class="set-row us-row">
        <?= \Core\Avatar::html($u, 'set-avatar adm-ava') ?>
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

