<?php $err = fn($k) => isset($errors[$k]) ? '<p class="f-error">' . e($errors[$k]) . '</p>' : ''; ?>
<header class="adm-head"><div><p class="adm-eyebrow"><?= e(__('Konto')) ?></p><h1><?= e(__('Mein Konto')) ?></h1><p class="adm-muted"><?= e($user['email']) ?> · <?= e(app()->auth->role()['name'] ?? '') ?></p></div></header>
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
<?php if (!empty($user['network_uid'])): // Schatten-Konto der Netzwerk-Administration: Passwort und 2FA zentral ?>
<section class="adm-card adm-narrow" id="zwei-faktor" aria-labelledby="net-acc-h">
  <h2 id="net-acc-h"><?= e(__('Netzwerk-Konto')) ?> <span class="adm-badge us-net"><?= e(__('Netzwerk')) ?></span></h2>
  <p class="adm-muted"><?= e(__('Sie sind mit Ihrem zentralen Netzwerk-Konto angemeldet. Passwort, Name und Zwei-Faktor-Anmeldung ändern Sie in der Netzwerk-Verwaltung – die Änderung gilt dann für alle Websites.')) ?></p>
  <form method="post" action="<?= e(url('/admin/network/open')) ?>"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e(\Core\Network\Network::siteKey()) ?>"><input type="hidden" name="path" value="/admin/account"><button class="adm-btn adm-btn--small" type="submit"><?= e(__('Konto in der Netzwerk-Verwaltung öffnen')) ?> ↗</button></form>
</section>
<?php include __DIR__ . '/twofactor/_passkeys.php'; // Passkeys des Netzwerk-Kontos für diese Domain ?>
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
<form class="adm-card adm-narrow" method="post" action="<?= e(url('/admin/account')) ?>" novalidate>
  <?= csrf_field() ?>
  <div class="f"><label for="a-name"><?= e(__('Name')) ?></label><input id="a-name" name="name" value="<?= e($user['name']) ?>"></div>
  <div class="f<?= isset($errors['current']) ? ' f--error' : '' ?>"><label for="a-cur"><?= e(__('Aktuelles Passwort')) ?></label><input id="a-cur" name="current" type="password" autocomplete="current-password" required><?= $err('current') ?></div>
  <div class="f<?= isset($errors['password']) ? ' f--error' : '' ?>"><label for="a-pw"><?= e(__('Neues Passwort (mind. 12 Zeichen)')) ?></label><input id="a-pw" name="password" type="password" autocomplete="new-password" required><?= $err('password') ?></div>
  <div class="f<?= isset($errors['password2']) ? ' f--error' : '' ?>"><label for="a-pw2"><?= e(__('Neues Passwort wiederholen')) ?></label><input id="a-pw2" name="password2" type="password" autocomplete="new-password" required><?= $err('password2') ?></div>
  <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Passwort ändern')) ?></button>
</form>
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
