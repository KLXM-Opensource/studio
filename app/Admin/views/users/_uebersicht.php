<?php
/** Benutzer & Rollen › Übersicht: Kennzahlen und Listen aus echten Daten (eingebunden von users.php, Variablen von dort) */
$local = array_values(array_filter($users, fn($u) => $u['role'] !== 'network' && empty($u['network_uid'])));
$noMfa = array_values(array_filter($local, fn($u) => !(int) ($u['totp_enabled'] ?? 0) && !(int) ($u['passkeys'] ?? 0)));
$withPk = array_values(array_filter($users, fn($u) => (int) ($u['passkeys'] ?? 0) > 0));
$openInv = array_values(array_filter($invites, fn($i) => !$i['expired']));
$recent = array_values(array_filter($users, fn($u) => !empty($u['last_login'])));
usort($recent, fn($a, $b) => strcmp((string) $b['last_login'], (string) $a['last_login']));
$never = array_values(array_filter($users, fn($u) => empty($u['last_login'])));
$byId = array_column($users, null, 'id');
$who = fn(array $u) => (string) ($u['name'] ?: $u['email']);
?>
<div class="us-tiles">
  <a class="us-tile" href="<?= e(url('/admin/users/personen')) ?>"><span class="us-tile__n"><?= count($users) ?></span><span class="us-tile__l"><?= e(__('Personen')) ?></span></a>
  <a class="us-tile<?= $noMfa ? ' is-warn' : '' ?>" href="<?= e(url('/admin/users/sicherheit')) ?>"><span class="us-tile__n"><?= count($noMfa) ?></span><span class="us-tile__l"><?= e(__('ohne zweiten Faktor')) ?></span></a>
  <a class="us-tile" href="<?= e(url('/admin/users/personen')) ?>"><span class="us-tile__n"><?= count($withPk) ?></span><span class="us-tile__l"><?= e(__('mit Passkey')) ?></span></a>
  <a class="us-tile" href="<?= e(url('/admin/users/einladen#einladungen')) ?>"><span class="us-tile__n"><?= count($openInv) ?></span><span class="us-tile__l"><?= e(__('offene Einladungen')) ?></span></a>
</div>

<div class="us-dash">
  <section class="set-group">
    <h3 class="set-group__title"><?= e(__('Personen je Rolle')) ?></h3>
    <div class="set-list">
      <?php foreach ($roles as $k => $ro): ?>
      <a class="set-row set-row--link" href="<?= e(url('/admin/users/rollen')) ?>"><span class="set-row__main"><span class="set-row__label"><?= e($ro['name']) ?></span></span>
        <span class="set-row__ctl"><?= (int) ($count[$k] ?? 0) ?></span></a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="set-group">
    <h3 class="set-group__title"><?= e(__('Zwei-Faktor-Anmeldung')) ?></h3>
    <div class="set-list">
      <?php if ($mfaMissing): foreach ($mfaMissing as $id): $u = $byId[$id] ?? null; if (!$u) continue; ?>
      <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($who($u)) ?></span><span class="set-row__sub"><?= e($roles[$u['role']]['name'] ?? $u['role']) ?></span></div>
        <div class="set-row__ctl"><span class="adm-badge adm-badge--warn"><?= e(__('Pflicht, noch nicht eingerichtet')) ?></span></div></div>
      <?php endforeach; endif; ?>
      <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Mit App-Code')) ?></span></div><div class="set-row__ctl"><?= count(array_filter($local, fn($u) => (int) ($u['totp_enabled'] ?? 0))) ?></div></div>
      <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Mit Passkey')) ?></span></div><div class="set-row__ctl"><?= count($withPk) ?></div></div>
      <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Ohne zweiten Faktor')) ?></span>
        <?php if ($noMfa): ?><span class="set-row__sub"><?= e(implode(', ', array_map($who, array_slice($noMfa, 0, 6))) . (count($noMfa) > 6 ? ' …' : '')) ?></span><?php endif; ?></div>
        <div class="set-row__ctl"><?= count($noMfa) ?></div></div>
      <a class="set-row set-row--link" href="<?= e(url('/admin/users/sicherheit')) ?>"><span class="set-row__main"><span class="set-row__label"><?= e(__('Anmeldung & Sicherheit')) ?></span><span class="set-row__sub"><?= e(__('Erlaubte Verfahren und Pflicht je Rolle')) ?></span></span></a>
    </div>
  </section>

  <section class="set-group">
    <h3 class="set-group__title"><?= e(__('Offene Einladungen')) ?></h3>
    <div class="set-list">
      <?php if (!$invites): ?><div class="set-row"><div class="set-row__main"><span class="set-row__sub"><?= e(__('Keine offenen Einladungen.')) ?></span></div></div><?php endif; ?>
      <?php foreach (array_slice($invites, 0, 5) as $iv): ?>
      <a class="set-row set-row--link" href="<?= e(url('/admin/users/einladen#einladungen')) ?>"><span class="set-row__main"><span class="set-row__label"><?= e($iv['name'] ?: $iv['email']) ?></span>
        <span class="set-row__sub"><?= e($roles[$iv['role']]['name'] ?? $iv['role']) ?> · <?= e($iv['expired'] ? __('abgelaufen am {date}', ['date' => date('d.m.Y', (int) $iv['expires_at'])]) : __('gültig bis {date}', ['date' => date('d.m.Y', (int) $iv['expires_at'])])) ?></span></span>
        <?php if ($iv['expired']): ?><span class="set-row__ctl"><span class="adm-badge adm-badge--muted"><?= e(__('Einladung abgelaufen')) ?></span></span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="set-group">
    <h3 class="set-group__title"><?= e(__('Zuletzt angemeldet')) ?></h3>
    <div class="set-list">
      <?php if (!$recent): ?><div class="set-row"><div class="set-row__main"><span class="set-row__sub"><?= e(__('Noch niemand angemeldet.')) ?></span></div></div><?php endif; ?>
      <?php foreach (array_slice($recent, 0, 5) as $u): ?>
      <div class="set-row"><?= \Core\Avatar::html($u, 'set-avatar adm-ava') ?><div class="set-row__main"><span class="set-row__label"><?= e($who($u)) ?></span><span class="set-row__sub"><?= e($roles[$u['role']]['name'] ?? $u['role']) ?></span></div>
        <div class="set-row__ctl"><?= e(date('d.m.Y H:i', strtotime((string) $u['last_login']))) ?></div></div>
      <?php endforeach; ?>
      <?php if ($never): ?>
      <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Noch nie angemeldet')) ?></span><span class="set-row__sub"><?= e(implode(', ', array_map($who, array_slice($never, 0, 6))) . (count($never) > 6 ? ' …' : '')) ?></span></div>
        <div class="set-row__ctl"><?= count($never) ?></div></div>
      <?php endif; ?>
    </div>
  </section>
</div>

<div class="set-actions us-quick">
  <?php if ($inviteRoles): ?><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/users/einladen#einladen')) ?>"><?= icon('paper-plane-tilt') ?> <?= e(__('Person einladen')) ?></a><?php endif; ?>
  <a class="adm-btn" href="<?= e(url('/admin/roles/new')) ?>"><?= icon('plus') ?> <?= e(__('Neue Rolle')) ?></a>
  <a class="adm-btn" href="<?= e(url('/admin/users/sicherheit')) ?>"><?= icon('shield-check') ?> <?= e(__('Anmeldung & Sicherheit')) ?></a>
</div>
