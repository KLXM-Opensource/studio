<?php
/**
 * Mitteilungen → Verfassen: Inhalt (Titel, Text, Ziel über die Linkauswahl, Bild aus der Mediathek), Empfänger (Kanäle, Rollen,
 * Personen – mit erreichbaren Geräten live), Zeitpunkt (sofort/geplant); rechts Vorschau Telefon/Computer. Erster Klick prüft und
 * zeigt die Bestätigung mit Empfängerzahl, der zweite sendet bzw. plant (MessagesController::send). Verhalten: resources/js/_pushadmin.js
 * @var array $old  @var array $errors  @var ?array $confirm
 */
use Core\Fields;
use Core\Push\Channels;
use Core\Push\Compose;
use Core\Push\Push;

$b = \Core\Http\Controllers\Admin\MessagesController::BASE;
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="pm-err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$inv = fn(string $k) => isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="pm-err-' . e($k) . '"' : '';
$channels = array_filter(Channels::all(), fn($c) => !$c['archived']);
$roles = \Core\Permissions::roles();
$fp = \Core\Push\Keys::fingerprint();
$devices = [];
foreach (app()->db->fetchAll('SELECT user_id, COUNT(*) AS n FROM push_subscriptions WHERE user_id IS NOT NULL AND key_fp = ? GROUP BY user_id', [$fp]) as $r) $devices[(int) $r['user_id']] = (int) $r['n'];
$users = app()->db->fetchAll('SELECT id, name, email, role FROM users WHERE COALESCE(disabled, 0) = 0 ORDER BY name, email');
$selT = array_map('strval', (array) ($old['topics'] ?? []));
$selR = array_map('strval', (array) ($old['roles'] ?? []));
$selU = array_map('intval', (array) ($old['users'] ?? []));
$later = ($old['when'] ?? 'now') === 'later';
$host = (string) (parse_url(Push::origin(), PHP_URL_HOST) ?: (app()->request?->host() ?? ''));
$visitorsBlocked = !Push::visitorsAllowed();
?>
<h1 class="adm-sr"><?= e(__('Neue Mitteilung')) ?></h1>
<form class="pm pm--compose" method="post" action="<?= e(url($b . '/neu')) ?>" novalidate data-pm-compose data-pm-reach-url="<?= e(url('/admin/api/push/reach')) ?>"
  data-pm-t-reach="<?= e(__('Erreicht derzeit {total} Gerät(e): {visitors} über Kanäle, {staff} der Redaktion ({people} Personen).')) ?>">
  <?= csrf_field() ?>
  <section class="pm-col pm-col--main" aria-labelledby="pm-h">
    <div class="pm-bar"><div class="pm-bar__title"><h2 id="pm-h"><?= e(__('Neue Mitteilung')) ?></h2><small><?= e(__('Push-Mitteilung an Abos von Besuchern und Geräte der Redaktion')) ?></small></div></div>
    <div class="pm-scroll">
    <?php if ($confirm): $rc = $confirm['reach']; $d = $confirm['data']; ?>
    <div class="pm-confirm" role="alert" tabindex="-1" data-pm-confirm>
      <p class="pm-confirm__title"><?= icon('paper-plane-tilt') ?> <?= e($d['at'] ? __('Mitteilung planen?') : __('Mitteilung jetzt senden?')) ?></p>
      <p><?= e(__('„{title}“ geht an {total} Gerät(e): {visitors} über Kanäle, {staff} der Redaktion ({people} Personen).', ['title' => $d['title'], 'total' => $rc['total'], 'visitors' => $rc['visitors'], 'staff' => $rc['staff'], 'people' => $rc['people']])) ?>
        <?= $d['at'] ? e(__('Versand am {when} – die Empfänger werden erst dann ermittelt.', ['when' => fmt()->datetime($d['at'])])) : '' ?></p>
      <p class="adm-muted"><?= e(Compose::summary(['targets_json' => json_encode($d['targets']), 'kind' => 'manual'])) ?></p>
      <?php if ($rc['blocked']): ?><p class="pm-confirm__warn"><?= e(__('Testumgebung: Besucher erhalten keine Mitteilung (config push_staging_visitors).')) ?></p><?php endif; ?>
      <div class="pm-confirm__actions">
        <button class="adm-btn adm-btn--primary" type="submit" name="confirm" value="1"><?= e($d['at'] ? __('Planen') : __('Jetzt senden')) ?></button>
        <button class="adm-btn" type="submit" name="back" value="1"><?= e(__('Weiter bearbeiten')) ?></button>
      </div>
    </div>
    <?php elseif ($errors): ?>
    <p class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte prüfen Sie die markierten Angaben.')) ?></p>
    <?php endif; ?>

    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Inhalt')) ?></h3>
      <div class="set-list">
        <div class="f<?= isset($errors['title']) ? ' f--error' : '' ?>"><label for="pm-title"><?= e(__('Titel')) ?></label>
          <input id="pm-title" name="m[title]" value="<?= e((string) ($old['title'] ?? '')) ?>" maxlength="120" required data-pm-title<?= $inv('title') ?>><?= $err('title') ?></div>
        <div class="f"><label for="pm-body"><?= e(__('Text')) ?></label>
          <textarea id="pm-body" name="m[body]" rows="3" maxlength="240" data-pm-body aria-describedby="pm-body-h"><?= e((string) ($old['body'] ?? '')) ?></textarea>
          <p class="f-help" id="pm-body-h"><?= e(__('Kurz halten: Telefone zeigen etwa zwei Zeilen. Höchstens 240 Zeichen, ohne Formatierung.')) ?></p></div>
        <div class="f<?= isset($errors['link']) ? ' f--error' : '' ?>"><label for="pm-link"><?= e(__('Ziel beim Antippen')) ?></label>
          <?= Fields::renderLink('pm-link', 'm[link]', (string) ($old['link'] ?? ''), $inv('link')) ?>
          <p class="f-help"><?= e(__('Seite, Eintrag oder Adresse dieser Website. Leer = Startseite.')) ?></p><?= $err('link') ?></div>
        <div class="f<?= isset($errors['image']) ? ' f--error' : '' ?>"><label><?= e(__('Bild (optional)')) ?></label>
          <div class="media-field" data-accept="image" data-pm-image><input type="hidden" id="pm-image" name="m[image]" value="<?= e((string) ($old['image'] ?? '')) ?>">
            <div class="media-field-preview"><?= Fields::mediaPreview(($old['image'] ?? '') !== '' ? (int) $old['image'] : null) ?></div>
            <button type="button" class="btn btn--small" data-media-pick><?= e(__('Auswählen …')) ?></button> <button type="button" class="btn btn--small btn--ghost" data-media-clear><?= e(__('Entfernen')) ?></button></div>
          <p class="f-help"><?= e(__('Großes Bild in der Mitteilung – zeigen Chrome, Edge und Android; Safari und Firefox zeigen nur Titel, Text und das App-Icon.')) ?></p><?= $err('image') ?></div>
      </div>
    </section>

    <fieldset class="set-group" data-pm-targets>
      <legend class="set-group__title"><?= e(__('Empfänger')) ?></legend>
      <?= $err('targets') ?>
      <div class="set-list">
        <div class="set-row set-row--stack"><div class="set-row__main"><span class="set-row__label"><?= e(__('Kanäle (Besucher und alle, die abonniert haben)')) ?></span>
          <?php if ($visitorsBlocked): ?><span class="set-row__sub"><?= e(__('Testumgebung: Besucher erhalten keine Mitteilung.')) ?></span><?php endif; ?></div>
          <?php if (!$channels): ?><p class="set-row__sub"><?= e(__('Noch keine Kanäle.')) ?> <a href="<?= e(url($b . '/kanaele?neu=1')) ?>"><?= e(__('Kanal anlegen')) ?></a></p><?php endif; ?>
          <div class="set-checks pm-checks">
          <?php foreach ($channels as $t => $c): ?>
            <label class="f-check"><input type="checkbox" name="m[topics][]" value="<?= e($t) ?>"<?= in_array($t, $selT, true) ? ' checked' : '' ?>>
              <span><?= e($c['name']) ?> <small class="adm-muted"><?= e(__('{n} Abo(s)', ['n' => fmt()->number(Channels::subscribers($t))])) ?><?= $c['kind'] === 'table' ? ' · ' . e(__('Tabelle')) : '' ?><?= !$c['public'] ? ' · ' . e(__('nicht öffentlich')) : '' ?></small></span></label>
          <?php endforeach; ?>
          </div></div>
        <div class="set-row set-row--stack"><div class="set-row__main"><span class="set-row__label"><?= e(__('Redaktion nach Rolle')) ?></span>
          <span class="set-row__sub"><?= e(__('Nur Geräte, die unter Konto → Benachrichtigungen angemeldet sind und „Mitteilungen der Redaktion“ eingeschaltet haben.')) ?></span></div>
          <div class="set-checks pm-checks">
          <?php foreach ($roles as $k => $r): if ($k === 'network') continue; ?>
            <label class="f-check"><input type="checkbox" name="m[roles][]" value="<?= e($k) ?>"<?= in_array($k, $selR, true) ? ' checked' : '' ?>> <span><?= e($r['name']) ?></span></label>
          <?php endforeach; ?>
          </div></div>
        <details class="set-row set-row--stack pm-people"<?= $selU ? ' open' : '' ?>><summary class="set-row__label"><?= e(__('Einzelne Personen')) ?> <small class="adm-muted" data-pm-people-n><?= $selU ? '(' . count($selU) . ')' : '' ?></small></summary>
          <div class="set-checks pm-checks pm-checks--people">
          <?php foreach ($users as $u): $n = $devices[(int) $u['id']] ?? 0; ?>
            <label class="f-check<?= $n ? '' : ' is-off' ?>"><input type="checkbox" name="m[users][]" value="<?= (int) $u['id'] ?>"<?= in_array((int) $u['id'], $selU, true) ? ' checked' : '' ?>>
              <span><?= e((string) ($u['name'] ?: $u['email'])) ?> <small class="adm-muted"><?= e($n === 1 ? __('1 Gerät') : __('{n} Geräte', ['n' => $n])) ?></small></span></label>
          <?php endforeach; ?>
          </div></details>
        <div class="set-row pm-reach"><div class="set-row__main"><span class="set-row__label"><?= icon('device-mobile') ?> <span data-pm-reach aria-live="polite"><?= e(__('Erreichbare Geräte werden berechnet, sobald Sie Empfänger wählen.')) ?></span></span></div></div>
      </div>
    </fieldset>

    <fieldset class="set-group">
      <legend class="set-group__title"><?= e(__('Zeitpunkt')) ?></legend>
      <div class="set-list">
        <div class="set-row"><div class="set-row__main"><label class="f-check"><input type="radio" name="m[when]" value="now"<?= $later ? '' : ' checked' ?> data-pm-when> <span><?= e(__('Sofort senden')) ?></span></label></div></div>
        <div class="set-row"><div class="set-row__main"><label class="f-check"><input type="radio" name="m[when]" value="later"<?= $later ? ' checked' : '' ?> data-pm-when> <span><?= e(__('Planen für …')) ?></span></label></div>
          <div class="set-row__ctl"><label class="adm-sr" for="pm-at"><?= e(__('Datum und Uhrzeit')) ?></label>
            <input type="datetime-local" id="pm-at" name="m[at]" value="<?= e((string) ($old['at'] ?? '')) ?>" min="<?= e(date('Y-m-d\TH:i', time() + 120)) ?>"<?= $inv('at') ?> data-pm-at></div></div>
      </div>
      <?= $err('at') ?>
      <p class="set-group__note"><?= e(__('Geplante Mitteilungen verschickt der Cronjob push:send pünktlich; ohne Cron gehen sie beim nächsten Aufruf der Verwaltung hinaus.')) ?></p>
    </fieldset>
    <?php if (!$confirm): ?>
    <div class="set-actions pm-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Prüfen und senden …')) ?></button>
      <a class="adm-btn adm-btn--ghost" href="<?= e(url($b)) ?>"><?= e(__('Abbrechen')) ?></a></div>
    <?php endif; ?>
    </div>
  </section>
  <aside class="pm-col pm-col--side" aria-labelledby="pm-prev-h">
    <div class="pm-bar"><div class="pm-bar__title"><h2 id="pm-prev-h"><?= e(__('Vorschau')) ?></h2><small><?= e(__('So ungefähr – jedes System zeigt Mitteilungen etwas anders')) ?></small></div></div>
    <div class="pm-scroll pm-preview" data-pm-preview data-pm-icon="<?= e(Push::icon()) ?>">
      <p class="pm-preview__label"><?= icon('device-mobile') ?> <?= e(__('Telefon')) ?></p>
      <div class="pm-phone"><div class="pm-note pm-note--phone">
        <img class="pm-note__icon" src="<?= e(Push::icon()) ?>" alt="" width="36" height="36">
        <div class="pm-note__text"><p class="pm-note__app"><span><?= e($host) ?></span><span><?= e(__('jetzt')) ?></span></p>
          <p class="pm-note__title" data-pm-p-title><?= e((string) ($old['title'] ?? '') ?: __('Titel der Mitteilung')) ?></p>
          <p class="pm-note__body" data-pm-p-body><?= e((string) ($old['body'] ?? '')) ?></p></div>
        <img class="pm-note__image" data-pm-p-image alt="" hidden>
      </div></div>
      <p class="pm-preview__label"><?= icon('monitor') ?> <?= e(__('Computer')) ?></p>
      <div class="pm-desk"><div class="pm-note pm-note--desk">
        <img class="pm-note__icon" src="<?= e(Push::icon()) ?>" alt="" width="40" height="40">
        <div class="pm-note__text"><p class="pm-note__title" data-pm-p-title><?= e((string) ($old['title'] ?? '') ?: __('Titel der Mitteilung')) ?></p>
          <p class="pm-note__body" data-pm-p-body><?= e((string) ($old['body'] ?? '')) ?></p>
          <p class="pm-note__app"><span><?= e($host) ?></span></p></div>
        <img class="pm-note__image" data-pm-p-image alt="" hidden>
      </div></div>
      <p class="pm-preview__hint"><?= e(__('Ein Antippen öffnet das gewählte Ziel auf dieser Website.')) ?></p>
    </div>
  </aside>
</form>
