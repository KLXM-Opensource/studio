<?php
/**
 * Netzwerk-Domain (Core\NetworkHost) – Grundeinstellungen → Adresse der Verwaltung und Netzwerk → Administratoren.
 * Felder per form-Attribut ($nhForm; das leere Formular legt der Aufrufer an), Rückweg $nhBack ('system' | 'network').
 * @var string $nhForm  @var string $nhBack
 */
$nhCur = \Core\NetworkHost::get();
$nhCan = \Core\NetworkHost::canManage();
$nhErr = $nhCur !== null ? \Core\NetworkHost::problem($nhCur) : null;
$nhMulti = \Core\Sites::multi();
$nhNetSite = new \Core\Site(\Core\Network\Network::siteKey(), \Core\Sites::all()[\Core\Network\Network::siteKey()] ?? []);
$nhPrimary = $nhNetSite->hosts()[0] ?? '';
$nhHasPw = (string) (\Core\Mfa::row(app()->auth->user() ?? [])['password_hash'] ?? '') !== '';
$nhId = $nhForm . '-';
?>
<section class="set-group" aria-labelledby="<?= e($nhId) ?>h">
  <h3 class="set-group__title" id="<?= e($nhId) ?>h"><?= e($nhMulti ? __('Netzwerk-Domain') : __('Verwaltungs-Domain')) ?></h3>
  <p class="set-group__note"><?= e($nhMulti
      ? __('Optional: eine zentrale Domain für Verwaltung und Anmeldung der Netzwerk-Website (z. B. die Adresse Ihrer Agentur). Anmeldung, Passkeys, Anbieter-Anmeldung (OpenID Connect), Einmal-Anmeldung zu den anderen Websites und Links in E-Mails laufen dann über diese Domain. Die Website selbst bleibt unter ihrer Hauptadresse.')
      : __('Optional: eine eigene Domain für die Verwaltung (z. B. die Adresse Ihrer Agentur). Anmeldung, Passkeys, Anbieter-Anmeldung (OpenID Connect) und Links in E-Mails laufen dann über diese Domain. Die Website selbst bleibt unter ihrer Hauptadresse.')) ?></p>
  <div class="set-list">
    <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Aktuell')) ?></span>
      <span class="set-row__sub"><?= $nhCur !== null ? '<code>' . e(admin_url_abs('/admin')) . '</code>' : e(__('keine – die Verwaltung ist unter jeder Domain der Website erreichbar')) ?></span></div>
      <div class="set-row__ctl"><?= $nhCur === null ? '<span class="adm-badge adm-badge--muted">' . e(__('aus')) . '</span>' : ($nhErr === null ? '<span class="adm-badge">' . e(__('aktiv')) . '</span>' : '<span class="adm-badge adm-badge--adm-warn">' . e(__('Problem')) . '</span>') ?></div></div>
    <?php if ($nhPrimary !== ''): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Hauptadresse der Website')) ?></span><span class="set-row__sub"><?= e(__('bleibt für Besucher, Canonical und Sitemap')) ?></span></div><div class="set-row__ctl"><code><?= e($nhPrimary) ?></code></div></div><?php endif; ?>
  </div>
  <?php if ($nhErr !== null): ?><p class="adm-flash adm-flash--error" role="alert"><?= icon('warning') ?> <?= e($nhErr) ?></p><?php endif; ?>
  <?php if (!$nhCan): ?>
  <p class="adm-flash adm-flash--info"><?= e(__('Die Netzwerk-Domain gilt für die ganze Installation – ändern kann sie nur die Netzwerk-Administration auf der Netzwerk-Website.')) ?></p>
  <?php else: ?>
  <div class="set-list">
    <div class="f"><label for="<?= e($nhId) ?>host"><?= e(__('Domain')) ?></label>
      <input id="<?= e($nhId) ?>host" name="network_host" form="<?= e($nhForm) ?>" autocomplete="off" spellcheck="false" maxlength="253" inputmode="url" value="<?= e((string) $nhCur) ?>" placeholder="verwaltung.example.org" aria-describedby="<?= e($nhId) ?>help">
      <p class="f-help" id="<?= e($nhId) ?>help"><?= e(__('Ohne https://. Die Domain muss schon auf diese Installation zeigen und zur Netzwerk-Website führen: als Domain der Website eingetragen oder als Rückfall (fallback_site). Beim Speichern wird das geprüft.')) ?></p></div>
    <?php if ($nhHasPw && !\Core\Mfa::recentAuth()): ?><div class="f f--inline"><label for="<?= e($nhId) ?>pw"><?= e(__('Ihr Passwort zur Bestätigung')) ?></label><input id="<?= e($nhId) ?>pw" name="password" type="password" form="<?= e($nhForm) ?>" autocomplete="current-password" required></div><?php endif; ?>
    <input type="hidden" name="back" value="<?= e($nhBack) ?>" form="<?= e($nhForm) ?>">
  </div>
  <p class="set-group__note"><?= e(__('Passkeys gelten je Domain: Legen Sie nach dem Umstellen unter Konto → Passkeys einen Passkey für die neue Domain an. Bestehende Sitzungen bleiben – beim nächsten Aufruf der Verwaltung geht es angemeldet auf der neuen Domain weiter. Beim Anbieter (z. B. Agentur-Hub) die neuen Adressen unter Benutzer & Rollen → Anmeldung & Sicherheit eintragen.')) ?></p>
  <div class="set-actions">
    <button class="adm-btn adm-btn--primary" type="submit" form="<?= e($nhForm) ?>"><?= e(__('Netzwerk-Domain speichern')) ?></button>
    <?php if ($nhCur !== null): ?><button class="adm-btn" type="submit" form="<?= e($nhForm) ?>" name="reset" value="1" formnovalidate><?= e(__('Netzwerk-Domain entfernen')) ?></button><?php endif; ?>
  </div>
  <?php endif; ?>
</section>
