<?php
/**
 * Grundeinstellungen → E-Mail-Versand (oben): Liste der Konten – solange keins eingerichtet ist, das Formular für das erste
 * (Konto „Standard“, gespeichert mit den Grundeinstellungen, Feld mail_std_inline) – und die Verwendung je Zweck
 * (mail_routes[…], SystemController::save). Konten bearbeiten: /admin/system/mail. Markup wie Fields::renderGroups.
 * @var array $stdFields  @var array $values  @var array $errors
 */
use Core\Fields;
use Core\Mail\Accounts;
use Core\Mail\Purposes;

$accounts = Accounts::all();
$anyReady = (bool) array_filter($accounts, fn($a) => $a['id'] !== 'network' && Accounts::ready($a));
$stdErr = array_intersect_key($errors, array_flip(array_values(Accounts::STANDARD_KEYS)));
$routes = Accounts::routes();
$key = fn(string $p) => preg_replace('~[^a-z0-9_]~', '_', $p);   // Feldname je Zweck (ext:x.y → ext_x_y)
$edit = fn(string $id) => url('/admin/system/mail?konto=' . $id . '#konto-' . $id);
?>
<div class="set-groups">
<?php if (!$anyReady || $stdErr): // noch kein Konto: direkt das erste einrichten ?>
  <input type="hidden" name="mail_std_inline" value="1">
  <?= Fields::renderGroups(array_merge([['type' => 'heading', 'label' => __('Erstes E-Mail-Konto einrichten'),
      'help' => __('Über dieses Konto („Standard“) versendet die Website, bis Sie weitere Konten anlegen. Für Plesk empfohlen: SMTP mit dem Postfach der Domain (Port 587, STARTTLS).')]],
      array_values(array_filter($stdFields, fn($f) => ($f['type'] ?? '') !== 'heading'))), $values, $errors, 'f') ?>
<?php else: ?>
<section class="set-group" id="mail-konten">
  <h3 class="set-group__title"><?= e(__('Konten')) ?></h3>
  <div class="set-list">
    <?php foreach ($accounts as $id => $a): $ready = Accounts::ready($a); ?>
    <div class="f f--inline mk-row">
      <span class="mk-row__label"><strong><?= e($a['label']) ?></strong>
        <span class="f-help"><?= e(Accounts::describe($a)) ?><?= $id === 'network' ? ' · ' . e(__('für alle Websites, im Netzwerk-Dashboard')) : '' ?></span></span>
      <span class="mk-row__act">
        <span class="adm-badge adm-badge--<?= $ready ? 'ok' : 'warn' ?>"><?= e($ready ? __('bereit') : __('nicht eingerichtet')) ?></span>
        <?php if ($id !== 'network'): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($edit($id)) ?>"><?= e(__('Bearbeiten')) ?></a><?php endif; ?>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="set-group__note"><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/system/mail?neu=1#neu')) ?>"><?= icon('plus') ?> <?= e(__('Konto hinzufügen')) ?></a>
    <span class="adm-muted"><?= e(__('z. B. für Newsletter – per SMTP oder über einen Dienst wie Mailjet, Mailchimp Transactional oder Brevo.')) ?></span></p>
</section>
<?php endif; ?>
<?php if (count($accounts) > 1): // Verwendung erst, wenn es eine Wahl gibt ?>
<section class="set-group" id="mail-verwendung">
  <h3 class="set-group__title"><?= e(__('Verwendung')) ?></h3>
  <div class="set-list">
    <?php foreach (Purposes::all() as $pk => $p): $fid = 'mr-' . $key($pk); $sel = $routes[$pk] ?? 'standard'; ?>
    <div class="f f--inline">
      <label for="<?= e($fid) ?>" id="<?= e($fid) ?>-l"><?= e($p['label']) ?></label>
      <select id="<?= e($fid) ?>" name="mail_routes[<?= e($key($pk)) ?>]" aria-describedby="<?= e($fid) ?>-h">
        <?php foreach ($accounts as $aid => $a): ?><option value="<?= e($aid) ?>"<?= $sel === $aid ? ' selected' : '' ?>><?= e($a['label']) ?></option><?php endforeach; ?>
      </select>
      <p class="f-help" id="<?= e($fid) ?>-h"><?= e($p['help']) ?><?= $p['owner'] !== 'core' ? ' · ' . e(__('Erweiterung {name}', ['name' => $p['owner']])) : '' ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="set-group__note"><?= e(__('Hat das gewählte Konto keinen gültigen Absender, versendet das E-Mail-Konto des Netzwerks (falls eingerichtet). Mails an Netzwerk-Konten gehen immer darüber.')) ?></p>
</section>
<?php endif; ?>
</div>
