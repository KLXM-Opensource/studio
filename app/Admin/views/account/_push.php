<?php
/**
 * Konto → Benachrichtigungen (Core\Push): Mitteilungen auf diesem Gerät (Schalter – die Abfrage des Browsers kommt erst beim
 * Einschalten), Schalter je Ereignis (nur, was die Rolle sehen darf), angemeldete Geräte, Testnachricht.
 * Verhalten: resources/js/_push.js (initPushAccount). Ohne Funktion „push“ oder ohne passende Ereignisse: nichts.
 * @var array $user
 */
use Core\Push\Keys;
use Core\Push\Push;

if (!Push::enabled()) return;
$puEvents = Push::visibleEvents($user);
if (!$puEvents) return;
$puOn = Push::userEvents((int) $user['id']);
$puDevices = Push::devices((int) $user['id']);
$puWhen = fn(?string $d) => $d ? fmt()->relative($d) : '–';
?>
<section class="set-group acc-group acc-push" id="benachrichtigungen" aria-labelledby="push-h" data-push-account
  data-key="<?= e(Keys::publicB64()) ?>" data-sw="<?= e(url(Push::SW_PATH)) ?>" data-scope="<?= e(url(Push::SW_SCOPE)) ?>"
  data-subscribe="<?= e(url('/admin/api/push/subscribe')) ?>" data-unsubscribe="<?= e(url('/admin/api/push/unsubscribe')) ?>"
  data-events="<?= e(url('/admin/account/push/events')) ?>">
  <h2 class="set-group__title" id="push-h"><?= e(__('Benachrichtigungen')) ?></h2>
  <div class="set-list acc-box">
    <div class="set-row set-row--keep">
      <div class="set-row__main"><label class="set-row__label" for="push-device"><?= e(__('Mitteilungen auf diesem Gerät')) ?></label>
        <span class="set-row__sub" id="push-device-sub" data-push-state><?= e(__('Auch bei geschlossenem Browser-Tab. Beim Einschalten fragt der Browser einmal nach der Erlaubnis.')) ?></span></div>
      <div class="set-row__ctl"><input type="checkbox" role="switch" id="push-device" data-push-device aria-describedby="push-device-sub" disabled></div>
    </div>
    <div class="set-row set-row--stack acc-push__hint" data-push-hint hidden><div class="set-row__body" data-push-hint-text></div></div>
  </div>
  <p class="set-group__note"><?= e(__('Gilt für diesen Browser auf diesem Gerät. Mitteilungen enthalten nur kurze Hinweise – Inhalte von Anfragen nie. Solange die Verwaltung in einem sichtbaren Fenster offen ist, zeigt der Chat seine eigenen Hinweise statt einer Mitteilung.')) ?></p>

  <h3 class="set-group__title set-group__title--sub acc-push__sub" id="push-ev-h"><?= e(__('Worüber?')) ?></h3>
  <form class="set-list" method="post" action="<?= e(url('/admin/account/push/events')) ?>" data-push-events aria-labelledby="push-ev-h">
    <?= csrf_field() ?>
    <?php foreach ($puEvents as $k => $ev): $fid = 'push-ev-' . preg_replace('~[^a-z0-9]+~', '-', $k); ?>
    <div class="set-row set-row--keep">
      <div class="set-row__main"><label class="set-row__label" for="<?= e($fid) ?>"><?= e($ev['label']) ?></label>
        <?php if (($ev['help'] ?? '') !== ''): ?><span class="set-row__sub" id="<?= e($fid) ?>-h"><?= e($ev['help']) ?></span><?php endif; ?></div>
      <div class="set-row__ctl"><input type="hidden" name="events[<?= e($k) ?>]" value="0"><input type="checkbox" role="switch" id="<?= e($fid) ?>" name="events[<?= e($k) ?>]" value="1"<?= !empty($puOn[$k]) ? ' checked' : '' ?><?= ($ev['help'] ?? '') !== '' ? ' aria-describedby="' . e($fid) . '-h"' : '' ?>></div>
    </div>
    <?php endforeach; ?>
    <div class="set-row acc-push__save" data-push-save><div class="set-row__main"><span class="set-row__sub"><?= e(__('Ohne JavaScript: Auswahl speichern.')) ?></span></div>
      <div class="set-row__ctl"><button class="adm-btn adm-btn--small" type="submit"><?= e(__('Speichern')) ?></button></div></div>
  </form>

  <h3 class="set-group__title set-group__title--sub acc-push__sub"><?= e(__('Angemeldete Geräte')) ?> <span class="adm-muted">(<?= count($puDevices) ?>)</span></h3>
  <div class="set-list" data-push-devices>
    <?php if (!$puDevices): ?>
    <div class="set-row set-row--keep"><div class="set-row__main"><span class="set-row__sub"><?= e(__('Noch kein Gerät angemeldet. Schalten Sie oben „Mitteilungen auf diesem Gerät“ ein – auf dem Telefon genauso.')) ?></span></div></div>
    <?php endif; ?>
    <?php foreach ($puDevices as $dv): $mobile = preg_match('~iPhone|iPad|Android~', (string) $dv['label']) === 1; $stale = $dv['key_fp'] !== Keys::fingerprint(); ?>
    <div class="set-row set-row--keep" data-push-hash="<?= e((string) $dv['endpoint_hash']) ?>">
      <span class="set-avatar set-avatar--ghost" aria-hidden="true"><?= icon($mobile ? 'device-mobile' : 'desktop') ?></span>
      <div class="set-row__main"><span class="set-row__label"><?= e((string) $dv['label']) ?> <span class="adm-badge" data-push-this hidden><?= e(__('dieses Gerät')) ?></span><?php if ($stale): ?> <span class="adm-badge adm-badge--warn"><?= e(__('veraltet')) ?></span><?php endif; ?></span>
        <span class="set-row__sub"><?= e(__('Angemeldet {when}', ['when' => $puWhen($dv['created_at'])])) ?> · <?= e(__('letzte Mitteilung {when}', ['when' => $puWhen($dv['last_sent_at'])])) ?><?= (int) $dv['failures'] > 0 ? ' · ' . e(__('{n} Fehlversuch(e)', ['n' => (int) $dv['failures']])) : '' ?></span></div>
      <div class="set-row__ctl"><form method="post" action="<?= e(url('/admin/account/push/devices/' . (int) $dv['id'] . '/delete')) ?>" data-confirm="<?= e(__('„{name}“ abmelden? Das Gerät erhält dann keine Mitteilungen der Verwaltung mehr.', ['name' => (string) $dv['label']])) ?>"><?= csrf_field() ?>
        <button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit"><?= e(__('Entfernen')) ?></button></form></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($puDevices): ?>
  <form class="set-actions" method="post" action="<?= e(url('/admin/account/push/test')) ?>"><?= csrf_field() ?>
    <button class="adm-btn adm-btn--small" type="submit"><?= icon('paper-plane-tilt') ?> <?= e(__('Testnachricht an meine Geräte')) ?></button></form>
  <?php endif; ?>
</section>
