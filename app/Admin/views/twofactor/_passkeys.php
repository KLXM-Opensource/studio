<?php
/** Konto → Passkeys (Core\Passkeys, Richtlinie Core\Mfa). Schatten-Konten: Passkeys des Netzwerk-Kontos für diese Domain. @var array $user */
$pkRow = \Core\Mfa::row($user) ?? $user;
[$pkDb, $pkUid] = \Core\Mfa::store($user);
$pkNet = $pkDb !== app()->db;
$pkPol = \Core\Mfa::allowed($user);
$pkRp = \Core\Passkeys::rpId();
$pkList = \Core\Passkeys::list($pkDb, $pkUid, $pkNet ? $pkRp : null);
$pkRecent = \Core\Mfa::recentAuth();
$pkAvail = \Core\Passkeys::available();
$pkBack = '';
$pkReq = \Core\Mfa::requirement($pkRow);
$pkDate = fn(?string $d) => $d ? date('d.m.Y H:i', (int) strtotime($d)) : null;
if (!$pkPol['passkey'] && !$pkList) return;
?>
<section class="adm-card adm-narrow" id="passkeys" aria-labelledby="pk-h">
  <h2 id="pk-h"><?= e(__('Passkeys')) ?> <span class="adm-badge<?= $pkList ? '' : ' adm-badge--muted' ?>"><?= e($pkList ? __('{n} eingerichtet', ['n' => count($pkList)]) : __('keiner')) ?></span></h2>
  <p class="adm-muted"><?= e(__('Anmelden mit Fingerabdruck, Gesicht oder Geräte-PIN statt mit einem Code – sicher gegen Phishing. Der Passkey bleibt auf Ihrem Gerät bzw. in Ihrem Passwortmanager; die Website speichert nur einen öffentlichen Schlüssel.')) ?>
    <?php if ($pkPol['passwordless']): ?><?= e(__('Mit einem Passkey können Sie sich auch ganz ohne Passwort anmelden.')) ?><?php endif; ?></p>
  <?php if ($pkNet): ?><p class="f-help"><?= e(__('Netzwerk-Konto: Passkeys gelten je Domain. Hier eingerichtete Passkeys gelten für {host}; über die Netzwerk-Übersicht („Öffnen“) brauchen Sie keinen.', ['host' => $pkRp])) ?></p><?php endif; ?>
  <?php if (!$pkPol['passkey']): ?><p class="adm-flash adm-flash--error"><?= e(__('Passkeys sind auf dieser Website abgeschaltet. Vorhandene Passkeys können Sie löschen.')) ?></p><?php endif; ?>
  <?php if ($pkReq === 'passkey'): ?><p class="f-help"><?= e(__('Für Ihr Konto ist ein Passkey vorgeschrieben.')) ?></p><?php endif; ?>
  <?php if ($pkList): ?>
  <ul class="pk-list">
    <?php foreach ($pkList as $i => $pk): $prov = \Core\Passkeys::providerName($pk['aaguid']); ?>
    <li class="pk-item">
      <form method="post" action="<?= e(url('/admin/account/passkeys/' . (int) $pk['id'] . '/rename')) ?>" class="pk-item__row">
        <?= csrf_field() ?>
        <label class="adm-sr" for="pk-n-<?= (int) $pk['id'] ?>"><?= e(__('Name von Passkey {n}', ['n' => $i + 1])) ?></label>
        <input id="pk-n-<?= (int) $pk['id'] ?>" name="name" value="<?= e($pk['name']) ?>" maxlength="<?= \Core\Passkeys::NAME_MAX ?>" required>
        <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Umbenennen')) ?></button>
        <button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit" formaction="<?= e(url('/admin/account/passkeys/' . (int) $pk['id'] . '/delete')) ?>" formnovalidate
          data-confirm="<?= e(__('Passkey „{name}“ löschen? Auf dem Gerät bleibt er gespeichert, funktioniert hier aber nicht mehr.', ['name' => $pk['name']])) ?>" aria-label="<?= e(__('„{name}“ löschen', ['name' => $pk['name']])) ?>"><?= e(__('Löschen')) ?></button>
      </form>
      <p class="pk-item__meta"><?= e(__('Hinzugefügt {date}', ['date' => $pkDate($pk['created_at']) ?? '–'])) ?> · <?= e($pk['last_used_at'] ? __('zuletzt verwendet {date}', ['date' => $pkDate($pk['last_used_at'])]) : __('noch nicht verwendet')) ?>
        <?= $prov ? ' · ' . e($prov) : '' ?><?= (int) $pk['backup'] ? ' · ' . e(__('synchronisiert')) : '' ?><?= $pk['rp_id'] !== $pkRp ? ' · ' . e(__('für {host}', ['host' => $pk['rp_id']])) : '' ?></p>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if ($pkPol['passkey']): ?><h3 class="pk-h3"><?= e(__('Neuen Passkey hinzufügen')) ?></h3><?php include __DIR__ . '/_passkey_add.php'; ?><?php endif; ?>
  <?php if ($pkList && !$pkNet && !(int) ($pkRow['totp_enabled'] ?? 0)): $left = count(json_decode((string) ($pkRow['totp_recovery'] ?? ''), true) ?: []); ?>
  <h3 class="pk-h3"><?= e(__('Wiederherstellungscodes')) ?></h3>
  <p class="adm-muted"><?= e(__('Für den Fall, dass Sie keinen Passkey zur Hand haben. Noch {n} Codes übrig.', ['n' => $left])) ?></p>
  <form method="post" action="<?= e(url('/admin/account/2fa/recovery')) ?>" class="tf-inline"><?= csrf_field() ?>
    <label class="adm-sr" for="pk-rc-pw"><?= e(__('Passwort zur Bestätigung')) ?></label><input id="pk-rc-pw" name="password" type="password" autocomplete="current-password" required placeholder="<?= e(__('Passwort zur Bestätigung')) ?>">
    <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Neue Wiederherstellungscodes')) ?></button>
  </form>
  <?php endif; ?>
</section>
