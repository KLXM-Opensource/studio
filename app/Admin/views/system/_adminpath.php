<?php
/**
 * Adresse der Verwaltung (Core\AdminPath) – Inhalt des Reiters „Adresse der Verwaltung“ der Grundeinstellungen bzw. (mehrere Websites)
 * Netzwerk → Anmeldung & Domain. Felder per form-Attribut an das Formular #adminpath, das der Aufrufer anlegt.
 */
?>
<?php // eigenes Formular #adminpath, Felder per form-Attribut
    $apCustom = \Core\AdminPath::custom(); $apCan = \Core\AdminPath::canManage() && !\Core\AdminPath::fromEnv();
    $apHasPw = (string) (\Core\Mfa::row(app()->auth->user() ?? [])['password_hash'] ?? '') !== ''; ?>
    <h2><?= e(__('Adresse der Verwaltung')) ?></h2>
    <p class="set-page__lead"><?= e(__('Unter dieser Adresse melden Sie sich an. Standard ist /admin. Optional eine eigene Adresse: Sie hält automatische Login-Scanner fern – ersetzt aber keine starken Passwörter und keinen zweiten Faktor.')) ?></p>
    <section class="set-group">
      <div class="set-list">
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Aktuell')) ?></span><code class="set-row__sub" id="ap-current"><?= e(admin_url_abs('/admin')) ?></code></div>
          <div class="set-row__ctl"><?= $apCustom ? '<span class="adm-badge">' . e(__('eigene Adresse')) . '</span>' : '<span class="adm-badge adm-badge--muted">' . e(__('Standard')) . '</span>' ?>
            <button type="button" class="adm-btn adm-btn--small" data-copy="#ap-current"><?= e(__('Kopieren')) ?></button></div></div>
        <?php if (\Core\Sites::multi()): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Gilt für')) ?></span></div><div class="set-row__ctl"><?= e(__('alle Websites dieser Installation')) ?></div></div><?php endif; ?>
      </div>
    </section>
    <?php if (\Core\AdminPath::fromEnv()): ?>
    <p class="adm-flash adm-flash--info"><?= e(__('Festgelegt über die Umgebungsvariable KLXM_ADMIN_PATH beim Hosting – dort ändern.')) ?></p>
    <?php elseif (!$apCan): ?>
    <p class="adm-flash adm-flash--info"><?= e(__('Die Adresse gilt für alle Websites dieser Installation – ändern kann sie nur die Netzwerk-Administration.')) ?></p>
    <?php else: $apSuggest = \Core\AdminPath::random(); ?>
    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Adresse ändern')) ?></h3>
      <div class="set-list">
        <div class="f"><label for="ap-path"><?= e(__('Neue Adresse')) ?></label>
          <div class="adm-prefix ap-input"><span aria-hidden="true"><?= e(preg_replace('~^https?://~', '', rtrim(admin_url_abs('/'), '/')) . '/') ?></span><input id="ap-path" name="admin_path" form="adminpath" autocomplete="off" spellcheck="false" maxlength="40" pattern="[a-z0-9][a-z0-9\-]{4,39}" value="<?= e($apCustom ? ltrim(\Core\AdminPath::prefix(), '/') : '') ?>" placeholder="<?= e($apSuggest) ?>" aria-describedby="ap-help"></div>
          <p class="f-help ap-hint" id="ap-help"><?= e(__('5–40 Zeichen: Kleinbuchstaben, Ziffern, Bindestriche. Nicht erlaubt sind übliche Adressen wie admin, login, wp-admin, backend oder verwaltung.')) ?> <?= e(__('Vorschlag:')) ?> <code><?= e($apSuggest) ?></code></p></div>
        <?php if ($apHasPw && !\Core\Mfa::recentAuth()): // kürzlich angemeldet/bestätigt: kein Passwort nötig ?><div class="f f--inline"><label for="ap-pw"><?= e(__('Ihr Passwort zur Bestätigung')) ?></label><input id="ap-pw" name="password" type="password" form="adminpath" autocomplete="current-password" required></div><?php endif; ?>
      </div>
      <p class="set-group__note"><?= e(__('Nach dem Ändern geht es sofort unter der neuen Adresse weiter. Lesezeichen und die App auf dem Homescreen bitte neu anlegen. /admin zeigt ohne Anmeldung nur noch „Seite nicht gefunden“.')) ?></p>
      <div class="set-actions">
        <button class="adm-btn adm-btn--primary" type="submit" form="adminpath"><?= e(__('Adresse ändern')) ?></button>
        <?php if ($apCustom): ?><button class="adm-btn" type="submit" form="adminpath" name="reset" value="1" formnovalidate><?= e(__('Zurück zu /admin')) ?></button><?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
