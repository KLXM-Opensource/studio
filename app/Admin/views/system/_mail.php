<?php
/**
 * Grundeinstellungen → E-Mail-Versand: fünf klare Gruppen, jede mit einem Satz, wofür sie gilt und was greift, wenn sie leer ist.
 *   1 Versandweg und Konten (bzw. das erste Konto „Standard“, gespeichert mit den Grundeinstellungen, Feld mail_std_inline)
 *   2 Absender je Zweck (mail_routes[…], SystemController::save) – darunter der wirksame Absender (Core\Mail\Accounts::resolve)
 *   3 Empfänger (sys.mail_to)
 *   4 Test und Umleitung (sys.mail_redirect, Testnachricht direkt an der Gruppe)
 *   5 Impressum in jeder E-Mail (sys.mail_imprint)
 * Alles gilt nur für diese Website. Konten bearbeiten: /admin/system/mail. Markup wie Fields::renderGroups.
 * @var array $stdFields  @var array $values  @var array $errors  @var array $fields übrige Felder der Gruppe „mail“ (SystemSchema)  @var array $user
 */
use Core\Fields;
use Core\Mail\Accounts;
use Core\Mail\Purposes;

$accounts = Accounts::all();
$net = $accounts['network'] ?? null;
$own = array_filter($accounts, fn($a) => $a['id'] !== 'network');
$anyReady = (bool) array_filter($own, fn($a) => Accounts::ready($a));
$stdErr = array_intersect_key($errors, array_flip(array_values(Accounts::STANDARD_KEYS)));
$routes = Accounts::routes();
$key = fn(string $p) => preg_replace('~[^a-z0-9_]~', '_', $p);   // Feldname je Zweck (ext:x.y → ext_x_y)
$edit = fn(string $id) => url('/admin/system/mail?konto=' . $id . '#konto-' . $id);
$who = fn(array $a) => $a['from'] !== '' ? trim(($a['from_name'] !== '' ? $a['from_name'] . ' ' : '') . '<' . $a['from'] . '>') : __('kein Absender');
$accLabel = fn(array $a) => $a['id'] === 'network' ? __('Absender der Installation') . ' (' . $a['from'] . ')' : $a['label'];
// Verständliche Namen je Zweck statt Technik („Absender für Mails an Besucher“ statt „Zweck Besucher“)
$senderLabel = fn(string $pk, array $p) => match ($pk) {
    'account' => __('Absender für Passwort, Einladungen und Anmeldehinweise'),
    'notify' => __('Absender für Hinweise an die Redaktion'),
    'visitor' => __('Absender für Mails an Besucher'),
    'newsletter' => __('Absender für Newsletter und Massenversand'),
    default => __('Absender für {name}', ['name' => $p['label']]),
};
$field = fn(string $name) => array_values(array_filter($fields ?? [], fn($f) => ($f['name'] ?? '') === $name));
$group = function (string $n, string $title, string $lead, string $id): string {
    return '<h3 class="set-group__title mg-title" id="' . e($id) . '"><span class="mg-num" aria-hidden="true">' . e($n) . '</span>' . e($title) . '</h3>'
        . '<p class="mg-lead">' . $lead . '</p>';
};
$req = '<span class="req" aria-hidden="true">*</span>';
?>
<div class="set-groups mg">
<p class="mg-scope" id="mail-geltung"><strong><?= e(__('Gilt nur für diese Website ({site}).', ['site' => site()->label()])) ?></strong>
  <?= e(__('Felder mit * braucht der Versand.')) ?> <a href="<?= e(url('/admin/system/mail/uebersicht')) ?>"><?= e(__('Übersicht: welche Adresse gilt wofür')) ?></a></p>

<?= $group('1', __('Versandweg und Konten'), e(__('Über welches Postfach bzw. welchen Dienst diese Website E-Mails verschickt.'))
    . ' ' . e($net && Accounts::ready($net) ? __('Ist kein Konto versandbereit, versendet ersatzweise der Absender der Installation: {from}.', ['from' => $who($net)])
        : __('Ist kein Konto versandbereit, werden E-Mails nur protokolliert.')), 'mail-konten') ?>
<?php if (!$anyReady || $stdErr): // noch kein Konto: direkt das erste einrichten ?>
  <input type="hidden" name="mail_std_inline" value="1">
  <?php $first = array_values(array_filter($stdFields, fn($f) => ($f['type'] ?? '') !== 'heading'));
  // Pflichtangaben für den Versand markieren (ohne Formular-Pflicht: die Grundeinstellungen speichern alle Reiter zusammen)
  foreach ($first as &$f) if (in_array($f['name'] ?? '', ['sys.mail_host', 'sys.mail_from'], true)) $f['label'] = (string) $f['label'] . ' *';
  unset($f);
  foreach ($first as &$f) if (($f['name'] ?? '') === 'sys.mail_from' && $net) $f['placeholder'] = __('leer: {from}', ['from' => $net['from']]);
  unset($f); ?>
  <?= Fields::renderGroups(array_merge([['type' => 'heading', 'label' => __('Erstes Konto „Standard“'),
      'help' => __('Über dieses Konto versendet die Website, bis Sie weitere Konten anlegen. Für Plesk empfohlen: SMTP mit dem Postfach der Domain (Port 587, STARTTLS).')]], $first), $values, $errors, 'f') ?>
<?php else: ?>
<section class="set-group" aria-labelledby="mail-konten">
  <div class="set-list">
    <?php foreach ($own as $id => $a): $ready = Accounts::ready($a); ?>
    <div class="f f--inline mk-row">
      <span class="mk-row__label"><strong><?= e($a['label']) ?></strong>
        <span class="f-help"><?= e(Accounts::describe($a)) ?></span></span>
      <span class="mk-row__act">
        <span class="adm-badge adm-badge--<?= $ready ? 'ok' : 'warn' ?>"><?= e($ready ? __('bereit') : __('nicht eingerichtet')) ?></span>
        <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($edit($id)) ?>"><?= e(__('Bearbeiten')) ?></a>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
  <p class="set-group__note"><a class="adm-btn adm-btn--small" href="<?= e(url('/admin/system/mail?neu=1#neu')) ?>"><?= icon('plus') ?> <?= e(__('Konto hinzufügen')) ?></a>
    <span class="adm-muted"><?= e(__('z. B. für Newsletter – per SMTP oder über einen Dienst wie Mailjet, Mailchimp Transactional oder Brevo.')) ?></span></p>
</section>
<?php endif; ?>

<?= $group('2', __('Absender je Zweck'), e(count($accounts) > 1 ? __('Welches Konto für welche Mails gilt. Ohne Auswahl gilt „Standard“; ist das gewählte Konto nicht versandbereit, greift der Ersatz – der wirksame Absender steht jeweils darunter.')
    : __('Alle Mails dieser Website gehen über das Konto „Standard“. Mit einem weiteren Konto lässt sich hier je Zweck ein anderer Absender wählen.')), 'mail-verwendung') ?>
<section class="set-group" aria-labelledby="mail-verwendung">
  <div class="set-list">
    <?php foreach (Purposes::all() as $pk => $p): $fid = 'mr-' . $key($pk); $sel = $routes[$pk] ?? 'standard';
      $eff = Accounts::resolve($pk, false, $accounts, $routes, $net, Accounts::standard()); ?>
    <div class="f f--inline">
      <label for="<?= e($fid) ?>"><?= e($senderLabel($pk, $p)) ?></label>
      <?php if (count($accounts) > 1): ?>
      <select id="<?= e($fid) ?>" name="mail_routes[<?= e($key($pk)) ?>]" aria-describedby="<?= e($fid) ?>-h">
        <?php foreach ($accounts as $aid => $a): ?><option value="<?= e($aid) ?>"<?= $sel === $aid ? ' selected' : '' ?>><?= e($accLabel($a)) ?></option><?php endforeach; ?>
      </select>
      <?php else: ?><output id="<?= e($fid) ?>" aria-describedby="<?= e($fid) ?>-h"><?= e(__('Standard')) ?></output><?php endif; ?>
      <p class="f-help" id="<?= e($fid) ?>-h"><?= e($p['help']) ?><?= $p['owner'] !== 'core' ? ' · ' . e(__('Erweiterung {name}', ['name' => $p['owner']])) : '' ?>
        <span class="mg-eff"><?= e(__('Wirksam:')) ?> <strong><?= e($who($eff['account'])) ?></strong><?= $eff['origin'] === 'fallback' ? ' – ' . e(__('Ersatz, weil das gewählte Konto nicht versandbereit ist')) : '' ?><?= !Accounts::ready($eff['account']) ? ' – ' . e(__('nicht versandbereit')) : '' ?></span></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<?= $group('3', __('Empfänger'), e(__('Wer Hinweise der Website bekommt („Neue Anfrage“, „Neuer Eintrag“, „Zustellung fehlgeschlagen“). Eine Tabelle mit eigenem Empfänger („Benachrichtigung an“) geht vor; Integrität, Support, Chat und Freigaben schreiben an die zuständigen Konten. Leer: Diese Hinweise gehen an niemanden.')), 'mail-empfaenger') ?>
<?= Fields::renderGroups($field('sys.mail_to'), $values, $errors, 'f') ?>

<?= $group('4', __('Test und Umleitung'), e(__('Zum Ausprobieren: eine Testnachricht senden oder alle E-Mails dieser Website vorübergehend an eine Adresse umleiten (Betreff mit [TEST]). Mails zur Kontosicherheit gehen weiter an die Person. Leer: keine Umleitung.')), 'mail-test') ?>
<?= Fields::renderGroups($field('sys.mail_redirect'), $values, $errors, 'f') ?>
<div class="adm-inline-box mg-test">
  <strong><?= e(__('Testnachricht senden')) ?></strong>
  <p class="adm-muted"><?= e(__('Erst speichern, dann testen. Geht über das Konto für „Hinweise an die Redaktion“.')) ?></p>
  <div class="adm-row"><input type="email" form="testmail" name="to" placeholder="<?= e((string) ($user['email'] ?? '')) ?>" aria-label="<?= e(__('Empfänger der Testnachricht')) ?>"><button class="adm-btn" form="testmail" type="submit"><?= e(__('Test senden')) ?></button></div>
</div>

<?= $group('5', __('Impressum in jeder E-Mail'), e(__('Jede E-Mail dieser Website endet mit diesem Impressum und dem Link zur Impressum-Seite (Einstellungen → Recht). Leer: nur der Link; fehlt auch die Impressum-Seite, ist der Newsletter-Versand gesperrt.')), 'mail-impressum') ?>
<?= Fields::renderGroups($field('sys.mail_imprint'), $values, $errors, 'f') ?>
</div>
