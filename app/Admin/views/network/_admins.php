<?php /** Netzwerk → Administratoren (Teil von network/index.php, Variablen von dort) */ ?>
  <section class="adm-card" id="konten" aria-labelledby="net-acc-h">
    <h2 id="net-acc-h" class="adm-sr"><?= e(__('Netzwerk-Administratoren')) ?></h2>
    <p class="adm-muted"><?= e(__('Diese Konten verwalten alle Websites. Anmeldung immer mit Zwei-Faktor-Anmeldung; Sperren beendet sofort alle Sitzungen auf allen Websites.')) ?></p>
    <?php if (!empty($inviteLink)): // E-Mail nicht zugestellt: Link einmal anzeigen (gespeichert ist nur ein Hash) ?>
    <div class="adm-inline-box adm-card--secret inv-link net-invlink" id="net-invite-link" role="alert">
      <strong><?= e(__('Einladung angelegt – E-Mail nicht zugestellt')) ?></strong>
      <p><?= e($inviteLink['error'] ? __('Die E-Mail an {email} konnte nicht gesendet werden: {error}', ['email' => $inviteLink['email'], 'error' => $inviteLink['error']])
        : __('Die E-Mail an {email} wurde nicht zugestellt (Testumgebung oder Versand deaktiviert – nur protokolliert bzw. umgeleitet).', ['email' => $inviteLink['email']])) ?></p>
      <p><?= e(__('Geben Sie den Link selbst weiter, z. B. per Messenger. Er ist nur jetzt sichtbar, gilt einmal und bis zum Ablauf der Einladung.')) ?></p>
      <div class="adm-secret"><code id="net-inv-url"><?= e($inviteLink['url']) ?></code><button type="button" class="adm-btn adm-btn--small" data-copy="#net-inv-url"><?= e(__('Kopieren')) ?></button></div>
    </div>
    <?php endif; ?>
    <table class="adm-table net-acc">
      <thead><tr><th scope="col"><?= e(__('Konto')) ?></th><th scope="col"><?= e(__('Status')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Aktionen')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($accounts as $a): $self = (int) $a['id'] === (int) $me['id']; ?>
        <tr><td><strong><?= e($a['name'] ?: $a['email']) ?></strong><br><span class="adm-muted"><?= e($a['email']) ?></span><br><small class="adm-muted"><?= e(__('Letzte Anmeldung')) ?>: <?= e($a['last_login'] ? $when($a['last_login']) : __('nie')) ?></small></td>
          <td><?php if ((int) $a['disabled']): ?><span class="adm-badge adm-badge--muted"><?= e(__('gesperrt')) ?></span><?php else: ?><span class="adm-badge"><?= e(__('aktiv')) ?></span><?php endif; ?>
            <?php $aPk = (int) ($a['passkeys'] ?? 0); if ((int) $a['totp_enabled']): ?><span class="adm-badge adm-badge--muted">2FA</span><?php endif; ?><?php if ($aPk): ?> <span class="adm-badge adm-badge--muted"><?= e(__('Passkey')) ?><?= $aPk > 1 ? ' ×' . $aPk : '' ?></span><?php endif; ?><?php if (!(int) $a['totp_enabled'] && !$aPk): ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('2FA ausstehend')) ?></span><?php endif; ?></td>
          <td class="adm-actions"><?php if ($self): ?><span class="adm-badge"><?= e(__('Sie')) ?></span><?php else: ?>
            <form method="post" action="<?= e(url('/admin/network/accounts/' . $a['id'] . '/' . ((int) $a['disabled'] ? 'enable' : 'disable'))) ?>"<?= (int) $a['disabled'] ? '' : ' data-confirm="' . e(__('{email} sperren? Alle Sitzungen auf allen Websites enden sofort.', ['email' => $a['email']])) . '"' ?>><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost<?= (int) $a['disabled'] ? '' : ' adm-btn--danger-text' ?>" type="submit"><?= e((int) $a['disabled'] ? __('Entsperren') : __('Sperren')) ?></button></form>
            <?php if ((int) $a['totp_enabled'] || $aPk): ?><form method="post" action="<?= e(url('/admin/network/accounts/' . $a['id'] . '/reset-2fa')) ?>" data-confirm="<?= e(__('Zwei-Faktor-Anmeldung von {email} zurücksetzen (App-Code und alle Passkeys)?', ['email' => $a['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('2FA zurücksetzen')) ?></button></form><?php endif; ?>
          <?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <?php if ($invites): // Offene Einladungen (Core\Invites, Rolle network): erneut senden, zurückziehen ?>
      <tbody id="net-einladungen" class="inv-rows">
      <?php foreach ($invites as $iv): ?>
        <tr class="inv-row<?= $iv['expired'] ? ' is-expired' : '' ?>"><td><strong><?= e($iv['name'] ?: $iv['email']) ?></strong><br><span class="adm-muted"><?= e($iv['email']) ?></span>
            <br><small class="adm-muted inv-meta"><?= e(__('Eingeladen am {date}', ['date' => \Core\Format::admin()->date(strtotime((string) $iv['created_at']))])) ?><?php if ($iv['invited_by_name']): ?> · <?= e(__('von {name}', ['name' => $iv['invited_by_name']])) ?><?php endif; ?>
            · <?= e($iv['expired'] ? __('abgelaufen am {date}', ['date' => \Core\Format::admin()->date((int) $iv['expires_at'])]) : __('gültig bis {date}', ['date' => \Core\Format::admin()->date((int) $iv['expires_at'])])) ?></small></td>
          <td><?php if ($iv['expired']): ?><span class="adm-badge adm-badge--muted inv-badge is-expired"><?= e(__('Einladung abgelaufen')) ?></span>
            <?php else: ?><span class="adm-badge inv-badge"><?= e(__('Eingeladen – wartet')) ?></span><?php endif; ?></td>
          <td class="adm-actions">
            <form method="post" action="<?= e(url('/admin/network/invites/' . $iv['id'] . '/resend')) ?>" data-confirm="<?= e(__('Einladung an {email} erneut senden? Der bisherige Link gilt dann nicht mehr.', ['email' => $iv['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('Erneut senden')) ?></button></form>
            <form method="post" action="<?= e(url('/admin/network/invites/' . $iv['id'] . '/revoke')) ?>" data-confirm="<?= e(__('Einladung an {email} zurückziehen? Der Link gilt dann nicht mehr.', ['email' => $iv['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit"><?= e(__('Zurückziehen')) ?></button></form></td></tr>
      <?php endforeach; ?>
      </tbody>
      <?php endif; ?>
    </table>
    <details class="net-add net-invite" id="net-invite"<?= $inviteOld ? ' open' : '' ?>>
      <summary class="adm-btn adm-btn--small adm-btn--primary"><?= e(__('+ Netzwerk-Admin einladen')) ?></summary>
      <form method="post" action="<?= e(url('/admin/network/invite')) ?>" class="inv-form" novalidate>
        <?= csrf_field() ?>
        <p class="adm-muted"><?= e(__('Die Person bekommt eine E-Mail mit einem Link ({n} Tage gültig), legt Passkey und/oder Passwort fest und richtet sofort die Zwei-Faktor-Anmeldung ein. Das Konto hat Zugriff auf alle Websites.', ['n' => \Core\Invites::days()])) ?></p>
        <div class="f<?= isset($invErr['email']) ? ' f--error' : '' ?>"><label for="ni-email"><?= e(__('E-Mail-Adresse')) ?> <span class="req">*</span></label><input id="ni-email" name="email" type="email" required maxlength="191" autocomplete="off" value="<?= e($inviteOld['email'] ?? '') ?>"<?= isset($invErr['email']) ? ' aria-invalid="true" aria-describedby="ni-err-email"' : '' ?>><?php if (isset($invErr['email'])): ?><p class="f-error" id="ni-err-email"><?= e($invErr['email']) ?></p><?php endif; ?></div>
        <div class="f"><label for="ni-name"><?= e(__('Name (optional)')) ?></label><input id="ni-name" name="name" maxlength="<?= \Core\Invites::NAME_MAX ?>" autocomplete="off" value="<?= e($inviteOld['name'] ?? '') ?>"></div>
        <div class="f<?= isset($invErr['message']) ? ' f--error' : '' ?>"><label for="ni-msg"><?= e(__('Persönliche Nachricht (optional)')) ?></label><textarea id="ni-msg" name="message" rows="3" maxlength="<?= \Core\Invites::MESSAGE_MAX ?>" aria-describedby="ni-msg-h"><?= e($inviteOld['message'] ?? '') ?></textarea>
          <p class="f-help" id="ni-msg-h"><?= e(__('Reiner Text, höchstens {n} Zeichen. Erscheint als Zitat in der E-Mail.', ['n' => \Core\Invites::MESSAGE_MAX])) ?></p><?php if (isset($invErr['message'])): ?><p class="f-error"><?= e($invErr['message']) ?></p><?php endif; ?></div>
        <?php if (count(\Core\I18n::available()) > 1): ?>
        <div class="f"><label for="ni-lang"><?= e(__('Sprache der Einladung')) ?></label><select id="ni-lang" name="locale"><?php foreach (\Core\I18n::available() as $lk => $ll): ?><option value="<?= e($lk) ?>"<?= $lk === \Core\I18n::locale() ? ' selected' : '' ?>><?= e($ll) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <p class="inv-hint"><?= e(__('Netzwerk-Konten verwalten ALLE Websites dieser Installation. Laden Sie nur Personen ein, denen Sie das anvertrauen.')) ?></p>
        <button class="adm-btn adm-btn--primary adm-btn--small" type="submit"><?= e(__('Einladung senden')) ?></button>
      </form>
    </details>
    <?php $np = \Core\Mfa::netPolicy(); // Anmelde-Richtlinie der Netzwerk-Konten (Core\Mfa, sys.net_auth) ?>
    <details class="net-add" id="net-auth">
      <summary class="adm-btn adm-btn--small"><?= e(__('Anmelde-Richtlinie')) ?></summary>
      <form method="post" action="<?= e(url('/admin/network/auth-policy')) ?>">
        <?= csrf_field() ?>
        <fieldset class="us-2fa__set"><legend><?= e(__('Erlaubte Verfahren für Netzwerk-Konten')) ?></legend>
          <label class="f-check"><input type="checkbox" name="totp" value="1"<?= $np['totp'] ? ' checked' : '' ?>> <span><?= e(__('Authenticator-App (6-stelliger Code)')) ?></span></label>
          <label class="f-check"><input type="checkbox" name="passkey" value="1"<?= $np['passkey'] ? ' checked' : '' ?>> <span><?= e(__('Passkeys')) ?></span></label>
          <label class="f-check"><input type="checkbox" name="passwordless" value="1"<?= $np['passwordless'] ? ' checked' : '' ?>> <span><?= e(__('Anmeldung ohne Passwort mit Passkey')) ?></span></label>
          <label class="f-check"><input type="checkbox" name="passkey_only" value="1"<?= $np['passkey_only'] ? ' checked' : '' ?>> <span><?= e(__('Nur Passkey als zweiter Faktor (phishing-resistent)')) ?></span></label>
        </fieldset>
        <p class="f-help"><?= e(__('Netzwerk-Konten melden sich immer mit zweitem Faktor an (App oder Passkey). Passkeys gelten je Domain – für die direkte Anmeldung auf einer anderen Website dort einen eigenen Passkey hinzufügen; „Öffnen“ aus dieser Übersicht braucht keinen.')) ?></p>
        <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Speichern')) ?></button>
      </form>
    </details>
    <details class="net-add">
      <summary class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Ohne E-Mail: mit Startpasswort anlegen')) ?></summary>
      <form method="post" action="<?= e(url('/admin/network/accounts')) ?>" novalidate>
        <?= csrf_field() ?>
        <p class="adm-muted"><?= e(__('Nur für Installationen ohne E-Mail-Versand – sonst besser einladen: Dann muss niemand ein Passwort weitergeben.')) ?></p>
        <div class="f"><label for="na-name"><?= e(__('Name')) ?></label><input id="na-name" name="name" autocomplete="off"></div>
        <div class="f"><label for="na-email"><?= e(__('E-Mail-Adresse')) ?> <span class="req">*</span></label><input id="na-email" name="email" type="email" required autocomplete="off"></div>
        <div class="f"><label for="na-pw"><?= e(__('Startpasswort (mind. 12 Zeichen)')) ?> <span class="req">*</span></label><input id="na-pw" name="password" type="text" required autocomplete="off" minlength="12"><p class="f-help"><?= e(__('Persönlich übergeben. Bei der ersten Anmeldung richtet die Person die Zwei-Faktor-Anmeldung ein.')) ?></p></div>
        <button class="adm-btn adm-btn--primary adm-btn--small" type="submit"><?= e(__('Anlegen')) ?></button>
      </form>
    </details>
  </section>
