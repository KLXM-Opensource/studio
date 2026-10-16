<?php
/**
 * Netzwerk → Protokoll (Teil von network/index.php): Sicherheits- und Betriebsprotokoll aller Websites (network_log, neueste 200),
 * Aktionen lesbar benannt, Filter nach Website und Suchbegriff (?website=…&q=…, ohne JavaScript).
 * @var array $log  @var string $logSite  @var string $logQ
 */
$labels = ['sso.issue' => __('Website geöffnet'), 'sso.login' => __('Angemeldet per Netzwerk'), 'login' => __('Angemeldet'),
    'handover.issue' => __('Domain gewechselt'), 'handover.login' => __('Angemeldet nach Domainwechsel'),
    'maintenance.on' => __('Wartungsmodus an'), 'maintenance.off' => __('Wartungsmodus aus'), 'backup' => __('Sicherung'), 'cache.clear' => __('Cache geleert'),
    'hosts.add' => __('Domain hinzugefügt'), 'hosts.remove' => __('Domain entfernt'), 'site.label' => __('Name geändert'), 'site.environment' => __('Umgebung geändert'),
    'site.create' => __('Website angelegt'), 'account.create' => __('Konto angelegt'), 'account.disable' => __('Konto gesperrt'), 'account.enable' => __('Konto entsperrt'),
    'account.reset-2fa' => __('2FA zurückgesetzt'), '2fa.enable' => __('2FA eingerichtet'), 'password' => __('Passwort geändert'),
    'user.reset-2fa' => __('2FA zurückgesetzt'), 'passkey.add' => __('Passkey hinzugefügt'), 'passkey.remove' => __('Passkey gelöscht'),
    'auth.policy' => __('Anmelde-Richtlinie geändert'), 'admin.path' => __('Adresse der Verwaltung geändert'),
    'network.invite' => __('Netzwerk-Admin eingeladen'), 'network.invite-resend' => __('Einladung erneut gesendet'), 'network.invite-revoke' => __('Einladung zurückgezogen'),
    'impersonate.start' => __('Ansicht als Rolle/Person begonnen'), 'impersonate.stop' => __('Ansicht als Rolle/Person beendet'),
    'user.email' => __('E-Mail-Adresse geändert'), 'user.email-admin' => __('E-Mail-Adresse von der Administration geändert'), 'user.email-request' => __('Änderung der E-Mail-Adresse angefragt'),
    'user.email-resend' => __('Bestätigung erneut gesendet'), 'user.email-cancel' => __('Änderung der E-Mail-Adresse abgebrochen'),
    'user.password-reset' => __('Passwort zurückgesetzt'), 'user.password-reset-request' => __('Passwort vergessen angefragt'), 'user.password-reset-limit' => __('Passwort vergessen: Grenze erreicht'),
    'warn.password-reset-nomail' => __('Passwort vergessen: kein E-Mail-Versand')];
// Präfixe (features.*, account.* …) ohne eigenen Eintrag
$label = function (string $a) use ($labels): string {
    if (isset($labels[$a])) return $labels[$a];
    if (str_starts_with($a, 'features.')) return __('Funktionen geändert');
    return $a;
};
$tone = fn(string $a) => match (true) {
    str_starts_with($a, 'warn.') || str_contains($a, 'limit') || str_ends_with($a, '.disable') => 'warn',
    str_starts_with($a, 'impersonate.') || str_contains($a, '2fa') || str_starts_with($a, 'passkey') || $a === 'password' || $a === 'auth.policy' => 'sec',
    default => '',
};
$logSite ??= ''; $logQ ??= '';
$sitesInLog = array_values(array_unique(array_filter(array_map(fn($l) => (string) $l['site'], $log))));
sort($sitesInLog);
$q = mb_strtolower(trim($logQ));
$rows = array_values(array_filter($log, fn($l) => ($logSite === '' || (string) $l['site'] === $logSite)
    && ($q === '' || str_contains(mb_strtolower($label((string) $l['action']) . ' ' . $l['action'] . ' ' . $l['detail'] . ' ' . $l['user_email'] . ' ' . $l['site']), $q))));
?>
<section class="adm-card net-logcard" aria-labelledby="net-log-h">
  <h2 id="net-log-h" class="adm-sr"><?= e(__('Protokoll')) ?></h2>
  <form method="get" action="<?= e(url('/admin/network/protokoll')) ?>" class="net-logfilter">
    <label class="net-search"><span class="adm-sr"><?= e(__('Protokoll durchsuchen')) ?></span><?= icon('magnifying-glass', ['class' => 'net-search__ico']) ?><input type="search" name="q" value="<?= e($logQ) ?>" placeholder="<?= e(__('Aktion, Person, Detail …')) ?>"></label>
    <label><span class="adm-sr"><?= e(__('Website')) ?></span><select name="website"><option value=""><?= e(__('Alle Websites')) ?></option><?php foreach ($sitesInLog as $sk): ?><option value="<?= e($sk) ?>"<?= $sk === $logSite ? ' selected' : '' ?>><?= e((string) ($stats[$sk]['label'] ?? $sk)) ?></option><?php endforeach; ?></select></label>
    <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Filtern')) ?></button>
    <?php if ($logSite !== '' || $logQ !== ''): ?><a class="adm-link" href="<?= e(url('/admin/network/protokoll')) ?>"><?= e(__('Zurücksetzen')) ?></a><?php endif; ?>
    <span class="adm-muted net-logfilter__n"><?= e(__('{n} Einträge', ['n' => count($rows)])) ?></span>
  </form>
  <?php if (!$rows): ?><p class="adm-muted"><?= e($log ? __('Kein Eintrag passt zum Filter.') : __('Noch keine Einträge.')) ?></p><?php else: ?>
  <ol class="net-log">
    <?php $day = ''; foreach ($rows as $l): $t = (int) strtotime((string) $l['created_at']); $d = \Core\Format::admin()->date($t); $a = (string) $l['action']; ?>
    <?php if ($d !== $day): $day = $d; ?><li class="net-log__day"><?= e($d === \Core\Format::admin()->date(time()) ? __('Heute') : ($d === \Core\Format::admin()->date(time() - 86400) ? __('Gestern') : $d)) ?></li><?php endif; ?>
    <li class="net-log__row<?= ($tn = $tone($a)) ? ' is-' . $tn : '' ?>"><time datetime="<?= e(date('c', $t)) ?>"><?= e(date('H:i', $t)) ?></time>
      <span class="net-log__what"><strong><?= e($label($a)) ?></strong><?= $l['site'] ? ' <span class="net-log__site">' . e((string) ($stats[$l['site']]['label'] ?? $l['site'])) . '</span>' : '' ?><?= $l['detail'] ? '<span class="net-log__detail">' . e((string) $l['detail']) . '</span>' : '' ?></span>
      <small class="adm-muted"><?= e((string) $l['user_email']) ?></small></li>
    <?php endforeach; ?>
  </ol>
  <?php endif; ?>
</section>
