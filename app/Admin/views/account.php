<?php
/** Konto: Anmeldedaten (Core\EmailChange), Profil, Darstellung, Akzentfarbe, Zwei-Faktor, Passkeys, Favoriten.
 * @var array $user  @var array $errors  @var array $old  @var ?array $pendingEmail */
$err = fn($k) => isset($errors[$k]) ? '<p class="f-error" id="acc-err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$old ??= [];
$pendingEmail ??= null;
$isShadow = !empty($user['network_uid']);
?>
<header class="adm-head"><div><p class="adm-eyebrow"><?= e(__('Konto')) ?></p><h1><?= e(__('Mein Konto')) ?></h1><p class="adm-muted"><?= e($user['email']) ?> · <?= e(app()->auth->role()['name'] ?? '') ?></p></div></header>
<?php if ($isShadow): // Schatten-Konto der Netzwerk-Administration: Anmeldedaten zentral ?>
<section class="adm-card adm-narrow acc-cred" id="anmeldedaten" aria-labelledby="cred-h">
  <h2 id="cred-h"><?= e(__('Anmeldedaten')) ?> <span class="adm-badge us-net"><?= e(__('Netzwerk')) ?></span></h2>
  <p><?= e(__('Ihre Anmeldedaten verwalten Sie in der Netzwerk-Verwaltung.')) ?></p>
  <p class="adm-muted"><?= e(__('Sie sind mit Ihrem zentralen Netzwerk-Konto angemeldet. E-Mail-Adresse, Passwort, Name und Zwei-Faktor-Anmeldung ändern Sie dort – die Änderung gilt dann für alle Websites.')) ?></p>
  <dl class="acc-facts"><div><dt><?= e(__('E-Mail-Adresse')) ?></dt><dd><?= e($user['email']) ?></dd></div></dl>
  <form method="post" action="<?= e(url('/admin/network/open')) ?>"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e(\Core\Network\Network::siteKey()) ?>"><input type="hidden" name="path" value="/admin/account"><button class="adm-btn adm-btn--small" type="submit"><?= e(__('Konto in der Netzwerk-Verwaltung öffnen')) ?> ↗</button></form>
</section>
<?php else:
  $acRow = app()->db->fetch('SELECT * FROM users WHERE id = ?', [$user['id']]) ?? [];
  $acHasPw = \Core\EmailChange::hasPassword($acRow);
  $acPk = \Core\Passkeys::count(app()->db, (int) $user['id']);
  $acPkHere = \Core\Passkeys::count(app()->db, (int) $user['id'], \Core\Passkeys::rpId());
  $acTotp = (int) ($acRow['totp_enabled'] ?? 0);
  $acRecent = \Core\Mfa::recentAuth();
  $acNeedPk = !$acHasPw && !$acRecent;   // Konto ohne Passwort: vorher mit Passkey bestätigen
  $acDate = fn(int $ts) => date('d.m.Y, H:i', $ts); ?>
<section class="adm-card adm-narrow acc-cred" id="anmeldedaten" aria-labelledby="cred-h">
  <h2 id="cred-h"><?= e(__('Anmeldedaten')) ?></h2>
  <p class="adm-muted"><?= e(__('Womit Sie sich bei dieser Website anmelden. Änderungen bestätigen Sie mit Ihrem Passwort bzw. Passkey; bei jeder Änderung erhalten Sie eine E-Mail.')) ?></p>
  <dl class="acc-facts">
    <div><dt><?= e(__('E-Mail-Adresse')) ?></dt><dd><strong><?= e($user['email']) ?></strong><?php if ($pendingEmail && !$pendingEmail['expired']): ?> <span class="adm-badge acc-badge-wait"><?= e(__('Änderung wartet')) ?></span><?php endif; ?></dd></div>
    <div><dt><?= e(__('Passwort')) ?></dt><dd><?= $acHasPw ? e(__('festgelegt')) : '<span class="adm-badge adm-badge--muted">' . e(__('keins')) . '</span> ' . e(__('Anmeldung nur mit Passkey')) ?></dd></div>
    <div><dt><?= e(__('Passkeys')) ?></dt><dd><?= e($acPk ? __('{n} eingerichtet', ['n' => $acPk]) : __('keiner')) ?> · <a href="#passkeys"><?= e(__('verwalten')) ?></a></dd></div>
    <div><dt><?= e(__('Zwei-Faktor (App)')) ?></dt><dd><?= e($acTotp ? __('aktiv') : __('aus')) ?> · <a href="#zwei-faktor"><?= e($acTotp ? __('verwalten') : __('einrichten')) ?></a></dd></div>
  </dl>

  <?php if ($pendingEmail): // Offene Änderung: wartet auf den Link in der E-Mail an die neue Adresse ?>
  <div class="acc-pending<?= $pendingEmail['expired'] ? ' is-expired' : '' ?>" role="status">
    <p class="acc-pending__title"><?= icon('envelope-simple') ?> <?= e($pendingEmail['expired'] ? __('Änderung abgelaufen') : __('Änderung wartet auf Bestätigung')) ?></p>
    <p><?= e(__('Neue Adresse: {email}', ['email' => $pendingEmail['new_email']])) ?></p>
    <p class="adm-muted"><?= e($pendingEmail['expired']
        ? __('Der Link ist am {date} abgelaufen. Ihre Adresse ist unverändert – senden Sie einen neuen Link oder brechen Sie ab.', ['date' => $acDate((int) $pendingEmail['expires_at'])])
        : __('Öffnen Sie den Link in der E-Mail an die neue Adresse und bestätigen Sie dort (gültig bis {date}). Bis dahin melden Sie sich weiter mit {old} an.', ['date' => $acDate((int) $pendingEmail['expires_at']), 'old' => $user['email']])) ?></p>
    <?php if (!$pendingEmail['sent_at']): ?><p class="acc-pending__warn"><?= e(__('Die letzte E-Mail wurde nicht zugestellt (E-Mail-Versand nicht eingerichtet oder Testumgebung). Ohne Bestätigung ändert sich nichts.')) ?></p><?php endif; ?>
    <div class="acc-pending__actions">
      <form method="post" action="<?= e(url('/admin/account/email/resend')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small" type="submit"><?= e(__('Erneut senden')) ?></button></form>
      <form method="post" action="<?= e(url('/admin/account/email/cancel')) ?>" data-confirm="<?= e(__('Änderung auf {email} abbrechen? Die Links in den E-Mails gelten dann nicht mehr.', ['email' => $pendingEmail['new_email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit"><?= e(__('Abbrechen')) ?></button></form>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($acNeedPk): // Konto ohne Passwort: erst mit Passkey bestätigen (gilt 15 Minuten) ?>
  <div class="acc-step">
    <p class="acc-step__lead"><?= e(__('Ihr Konto hat kein Passwort. Bestätigen Sie zuerst mit Ihrem Passkey, dass Sie es sind – danach können Sie 15 Minuten lang E-Mail-Adresse und Passwort ändern.')) ?></p>
    <?php $raId = 'acc-ra'; $raBack = 'anmeldedaten'; $raHasPw = false; $raPasskey = $acPkHere > 0 && \Core\Passkeys::available(); include __DIR__ . '/account/_reauth.php'; ?>
    <?= $err('email_current') ?><?= $err('current') ?>
  </div>
  <?php endif; ?>

  <details class="acc-sub" id="email-aendern"<?= isset($errors['email_new']) || isset($errors['email_current']) ? ' open' : '' ?>>
    <summary><?= icon('envelope-simple') ?> <span><?= e(__('E-Mail-Adresse ändern')) ?></span></summary>
    <form method="post" action="<?= e(url('/admin/account/email')) ?>" novalidate>
      <?= csrf_field() ?>
      <p class="adm-muted"><?= e(__('Wir senden einen Bestätigungslink an die neue Adresse – erst damit gilt sie. Ihre bisherige Adresse erhält einen Hinweis mit der Möglichkeit, die Änderung abzubrechen. Passkeys gelten weiter.')) ?></p>
      <div class="f<?= isset($errors['email_new']) ? ' f--error' : '' ?>"><label for="acc-email"><?= e(__('Neue E-Mail-Adresse')) ?></label><input id="acc-email" name="email" type="email" required maxlength="191" autocomplete="email" value="<?= e($old['email_new'] ?? '') ?>"<?= isset($errors['email_new']) ? ' aria-invalid="true" aria-describedby="acc-err-email_new"' : '' ?>><?= $err('email_new') ?></div>
      <?php if ($acHasPw): ?>
      <div class="f<?= isset($errors['email_current']) ? ' f--error' : '' ?>"><label for="acc-email-cur"><?= e(__('Aktuelles Passwort')) ?></label><input id="acc-email-cur" name="current" type="password" required autocomplete="current-password"<?= isset($errors['email_current']) ? ' aria-invalid="true" aria-describedby="acc-err-email_current"' : '' ?>><?= $err('email_current') ?></div>
      <?php endif; ?>
      <button class="adm-btn adm-btn--primary" type="submit"<?= $acNeedPk ? ' disabled' : '' ?>><?= e(__('Bestätigungslink senden')) ?></button>
    </form>
  </details>

  <details class="acc-sub" id="passwort"<?= isset($errors['password']) || isset($errors['password2']) || (isset($errors['current']) && $acHasPw) ? ' open' : '' ?>>
    <summary><?= icon('password') ?> <span><?= e($acHasPw ? __('Passwort ändern') : __('Passwort festlegen')) ?></span></summary>
    <form method="post" action="<?= e(url('/admin/account')) ?>" novalidate>
      <?= csrf_field() ?>
      <?php if (!$acHasPw): ?><p class="adm-muted"><?= e(__('Optional: Mit einem Passwort können Sie sich auch ohne Passkey anmelden, z. B. an einem fremden Gerät.')) ?></p><?php endif; ?>
      <?php if ($acHasPw): ?>
      <div class="f<?= isset($errors['current']) ? ' f--error' : '' ?>"><label for="a-cur"><?= e(__('Aktuelles Passwort')) ?></label><input id="a-cur" name="current" type="password" autocomplete="current-password" required<?= isset($errors['current']) ? ' aria-invalid="true" aria-describedby="acc-err-current"' : '' ?>><?= $err('current') ?></div>
      <?php endif; ?>
      <div class="f<?= isset($errors['password']) ? ' f--error' : '' ?>"><label for="a-pw"><?= e(__('Neues Passwort (mind. 12 Zeichen)')) ?></label><input id="a-pw" name="password" type="password" autocomplete="new-password" required minlength="12"<?= isset($errors['password']) ? ' aria-invalid="true" aria-describedby="acc-err-password"' : '' ?>><?= $err('password') ?></div>
      <div class="f<?= isset($errors['password2']) ? ' f--error' : '' ?>"><label for="a-pw2"><?= e(__('Neues Passwort wiederholen')) ?></label><input id="a-pw2" name="password2" type="password" autocomplete="new-password" required<?= isset($errors['password2']) ? ' aria-invalid="true" aria-describedby="acc-err-password2"' : '' ?>><?= $err('password2') ?></div>
      <button class="adm-btn adm-btn--primary" type="submit"<?= $acNeedPk ? ' disabled' : '' ?>><?= e($acHasPw ? __('Passwort ändern') : __('Passwort festlegen')) ?></button>
      <p class="f-help"><?= e(__('Andere Sitzungen Ihres Kontos enden, diese bleibt angemeldet. Sie erhalten eine Hinweis-E-Mail.')) ?></p>
    </form>
  </details>
</section>

<form class="adm-card adm-narrow" id="profil" method="post" action="<?= e(url('/admin/account/profile')) ?>">
  <?= csrf_field() ?>
  <h2><?= e(__('Profil')) ?></h2>
  <div class="f"><label for="a-name"><?= e(__('Name')) ?></label><input id="a-name" name="name" maxlength="<?= \Core\Invites::NAME_MAX ?>" autocomplete="name" value="<?= e($user['name']) ?>" aria-describedby="a-name-h">
    <p class="f-help" id="a-name-h"><?= e(__('Erscheint u. a. im Chat, bei Freigaben und in Einladungen.')) ?></p></div>
  <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Name speichern')) ?></button>
</form>
<?php endif; ?>
<form class="adm-card adm-narrow" method="post" action="<?= e(url('/admin/account/locale')) ?>">
  <?= csrf_field() ?>
  <h2><?= e(__('Darstellung & Sprache')) ?></h2>
  <div class="f"><label for="a-app"><?= e(__('Darstellung')) ?></label><select id="a-app" name="appearance" data-autosubmit>
    <?php foreach (['' => __('Automatisch (wie das System)'), 'light' => __('Hell'), 'dark' => __('Dunkel')] as $k => $l): ?><option value="<?= e($k) ?>"<?= ($user['appearance'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
  </select></div>
  <div class="f"><label for="a-loc"><?= e(__('Sprache')) ?></label><select id="a-loc" name="locale" data-autosubmit>
    <option value=""><?= e(__('Standard der Website')) ?></option>
    <?php foreach (\Core\I18n::available() as $k => $l): ?><option value="<?= e($k) ?>"<?= ($user['locale'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
  </select></div>
</form>
<?php include __DIR__ . '/account/_accent.php'; // Akzentfarbe der Verwaltung (Core\Accent) ?>
<?php if ($isShadow): include __DIR__ . '/twofactor/_passkeys.php'; // Passkeys des Netzwerk-Kontos für diese Domain ?>
<?php else: $tfRow = app()->db->fetch('SELECT * FROM users WHERE id = ?', [$user['id']]); $tfOn = (int) ($tfRow['totp_enabled'] ?? 0);
  // Pflicht (Core\Mfa): App nicht abschaltbar, wenn die Pflicht sonst nicht erfüllt wäre (z. B. kein Passkey)
  $tfNeed = \Core\Mfa::requirement($tfRow); $tfReq = $tfNeed !== null && !\Core\Mfa::satisfied(['totp_enabled' => 0] + $tfRow, $tfNeed, app()->db);
  $tfAllowed = \Core\Mfa::policy()['totp'] && $tfNeed !== 'passkey'; ?>
<?php if ($tfAllowed || $tfOn): ?>
<section class="adm-card adm-narrow" id="zwei-faktor" aria-labelledby="tf-h">
  <h2 id="tf-h"><?= e(__('Zwei-Faktor-Anmeldung')) ?> <span class="adm-badge<?= $tfOn ? '' : ' adm-badge--muted' ?>"><?= e($tfOn ? __('aktiv') : __('aus')) ?></span></h2>
  <?php if ($tfOn): $left = count(json_decode((string) $tfRow['totp_recovery'], true) ?: []); ?>
  <p class="adm-muted"><?= e(__('Bei der Anmeldung fragen wir nach dem Passwort einen Code aus Ihrer Authenticator-App ab. Noch {n} Wiederherstellungscodes übrig.', ['n' => $left])) ?></p>
  <form method="post" action="<?= e(url('/admin/account/2fa/recovery')) ?>" class="tf-inline"><?= csrf_field() ?>
    <label class="adm-sr" for="tf-pw1"><?= e(__('Passwort zur Bestätigung')) ?></label><input id="tf-pw1" name="password" type="password" autocomplete="current-password" required placeholder="<?= e(__('Passwort zur Bestätigung')) ?>">
    <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Neue Wiederherstellungscodes')) ?></button>
    <?php if (!$tfReq): ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit" formaction="<?= e(url('/admin/account/2fa/disable')) ?>" data-confirm="<?= e(__('Zwei-Faktor-Anmeldung wirklich ausschalten?')) ?>"><?= e(__('Ausschalten')) ?></button><?php endif; ?>
  </form>
  <?php if ($tfReq): ?><p class="f-help"><?= e(__('Für Ihre Rolle vorgeschrieben – nicht abschaltbar.')) ?></p><?php endif; ?>
  <?php else: ?>
  <p class="adm-muted"><?= e(__('Schützt Ihr Konto zusätzlich zum Passwort mit einem Code aus einer App auf Ihrem Telefon.')) ?><?= $tfReq ? ' ' . e(__('Für Ihre Rolle vorgeschrieben.')) : '' ?></p>
  <a class="adm-btn adm-btn--primary adm-btn--small" href="<?= e(url('/admin/account/2fa')) ?>"><?= e(__('Einrichten')) ?></a>
  <?php endif; ?>
  <?php if (!$tfAllowed): ?><p class="f-help"><?= e($tfNeed === 'passkey' ? __('Für Ihr Konto ist ein Passkey vorgeschrieben.') : __('Die Authenticator-App ist auf dieser Website abgeschaltet. Richten Sie stattdessen einen Passkey ein.')) ?></p><?php endif; ?>
</section>
<?php endif; ?>
<?php include __DIR__ . '/twofactor/_passkeys.php'; ?>
<?php endif; ?>
<?php $favs = \Core\Favorites::all((int) $user['id']); $fe = url('/admin/api/favorites'); ?>
<section class="adm-card adm-narrow" id="favoriten" aria-labelledby="fav-h">
  <h2 id="fav-h"><?= e(__('Favoriten')) ?></h2>
  <p class="adm-muted"><?= e(__('Mit dem Stern ☆ oben auf jeder Seite merken Sie sich Seiten, Tabellen, Einträge oder Einstellungen. Sie stehen dann in der Seitenleiste – nur für Sie. Höchstens {n}.', ['n' => \Core\Favorites::MAX])) ?></p>
  <?php if (!$favs): ?>
  <p class="acc-fav__none"><?= e(__('Noch keine Favoriten.')) ?></p>
  <?php else: ?>
  <ol class="acc-fav">
    <?php foreach ($favs as $i => $f): $n = count($favs); ?>
    <li>
      <form method="post" action="<?= e($fe . '/rename') ?>" class="acc-fav__row">
        <?= csrf_field() ?><input type="hidden" name="url" value="<?= e($f['url']) ?>"><input type="hidden" name="back" value="/admin/account#favoriten">
        <label class="adm-sr" for="acc-fav-<?= $i ?>"><?= e(__('Name von Favorit {n} von {total}', ['n' => $i + 1, 'total' => $n])) ?></label>
        <input id="acc-fav-<?= $i ?>" name="title" value="<?= e($f['title']) ?>" maxlength="<?= \Core\Favorites::TITLE_MAX ?>" required>
        <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Umbenennen')) ?></button>
        <button class="adm-btn adm-btn--small" type="submit" formaction="<?= e($fe . '/reorder') ?>" name="dir" value="up"<?= $i === 0 ? ' disabled' : '' ?> aria-label="<?= e(__('„{name}“ nach oben', ['name' => $f['title']])) ?>">↑</button>
        <button class="adm-btn adm-btn--small" type="submit" formaction="<?= e($fe . '/reorder') ?>" name="dir" value="down"<?= $i === $n - 1 ? ' disabled' : '' ?> aria-label="<?= e(__('„{name}“ nach unten', ['name' => $f['title']])) ?>">↓</button>
        <button class="adm-btn adm-btn--small adm-btn--danger-text" type="submit" formaction="<?= e($fe . '/remove') ?>" formnovalidate aria-label="<?= e(__('„{name}“ entfernen', ['name' => $f['title']])) ?>"><?= e(__('Entfernen')) ?></button>
        <a class="acc-fav__url" href="<?= e(url($f['url'])) ?>"><?= e(rawurldecode($f['url'])) ?></a>
      </form>
    </li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</section>
<?= /* Erweiterungen (Extension::account, Core\Slots): eigene Abschnitte – escaped vom Core */ \Core\Extensions::accountSections((array) ($user ?? app()->auth->user() ?? [])) ?>
