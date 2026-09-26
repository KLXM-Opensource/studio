<?php
/**
 * Netzwerk-Übersicht: alle Websites der Installation (Core\Network\Stats), Netzwerk-Konten, geteilte Ressourcen, Protokoll.
 * @var array $stats  @var array $warn  @var array $accounts  @var array $log  @var array $shared  @var array $themes  @var array $me  @var ?array $newSite
 */
$title = __('Netzwerk');
$size = function (?int $b): string {
    if ($b === null) return '–';
    foreach ([__('B'), 'KB', 'MB', 'GB'] as $i => $u) {
        if ($b < 1024 ** ($i + 1) || $u === 'GB') return ($i ? number_format($b / 1024 ** $i, $i > 1 ? 1 : 0, ',', '.') : (string) $b) . ' ' . $u;
    }
    return (string) $b;
};
$when = fn($t) => $t ? date('d.m.Y H:i', is_int($t) ? $t : (int) strtotime((string) $t)) : '–';
$envLabel = ['production' => __('Live'), 'staging' => __('Staging'), 'development' => __('Entwicklung')];
$nWarn = count(array_filter($warn));
$nMaint = count(array_filter($stats, fn($s) => !empty($s['maintenance'])));
$inbox = array_sum(array_map(fn($s) => (int) ($s['inbox_new'] ?? 0), $stats));
$bytes = array_sum(array_map(fn($s) => (int) ($s['db_size'] ?? 0) + (int) ($s['media_size'] ?? 0), $stats));
$oldest = min(array_map(fn($s) => (int) ($s['at'] ?? time()), $stats) ?: [time()]);
$actionUrl = fn(string $k, string $a) => url('/admin/network/site/' . $k . '/' . $a);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Netzwerk-Administration')) ?></p><h1><?= e(__('Alle Websites')) ?></h1>
    <p class="adm-muted"><?= e(__('Übersicht aller Websites dieser Installation. „Öffnen“ meldet Sie mit Ihrem Netzwerk-Konto in der Verwaltung der jeweiligen Website an.')) ?></p></div>
  <a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e(url('/admin/network?refresh=1')) ?>" title="<?= e(__('Stand: {time}', ['time' => date('H:i:s', $oldest)])) ?>"><?= e(__('Aktualisieren')) ?></a>
</header>

<div class="adm-stats net-sum">
  <div class="adm-stat"><strong><?= count($stats) ?></strong><span><?= e(__('Websites')) ?></span></div>
  <a class="adm-stat<?= $nWarn ? ' net-stat--warn' : '' ?>" href="#websites" data-net-show="warn"><strong><?= $nWarn ?></strong><span><?= e(__('mit Hinweisen')) ?></span></a>
  <div class="adm-stat"><strong><?= $nMaint ?></strong><span><?= e(__('im Wartungsmodus')) ?></span></div>
  <div class="adm-stat"><strong><?= $inbox ?></strong><span><?= e(__('neue Anfragen (alle Websites)')) ?></span></div>
  <div class="adm-stat"><strong><?= array_sum(array_map(fn($s) => (int) ($s['review_pending'] ?? 0), $stats)) ?></strong><span><?= e(__('Änderungen zur Freigabe (API, MCP, KI)')) ?></span></div>
  <div class="adm-stat"><strong><?= e($size($bytes)) ?></strong><span><?= e(__('Speicher (Datenbanken + Medien)')) ?></span></div>
</div>

<section id="websites" aria-labelledby="net-sites-h">
  <div class="net-bar">
    <h2 id="net-sites-h" class="adm-sr"><?= e(__('Websites')) ?></h2>
    <label class="net-search"><span class="adm-sr"><?= e(__('Websites durchsuchen')) ?></span><input type="search" data-net-q placeholder="<?= e(__('Name, Domain, Kit …')) ?>" autocomplete="off"></label>
    <div class="net-seg" role="group" aria-label="<?= e(__('Filter')) ?>">
      <?php foreach (['all' => __('Alle'), 'warn' => __('Mit Hinweisen'), 'maintenance' => __('Wartung'), 'staging' => __('Nicht live')] as $fk => $fl): ?>
      <button type="button" data-net-filter="<?= e($fk) ?>" aria-pressed="<?= $fk === 'all' ? 'true' : 'false' ?>"><?= e($fl) ?></button>
      <?php endforeach; ?>
    </div>
    <p class="adm-muted net-count" data-net-count aria-live="polite"></p>
  </div>

  <div class="net-grid" data-net-grid>
  <?php foreach ($stats as $k => $s): $w = $warn[$k] ?? []; $env = $s['environment'] ?? 'production'; $hosts = $s['hosts'] ?? [];
    $search = mb_strtolower(implode(' ', [$k, $s['label'] ?? '', implode(' ', $hosts), $s['theme'] ?? '', $s['theme_label'] ?? '', $s['preset'] ?? ''])); ?>
    <article class="net-card<?= $w ? ' has-warn' : '' ?><?= !empty($s['maintenance']) ? ' is-maint' : '' ?>" id="site-<?= e($k) ?>" data-net-card
      data-q="<?= e($search) ?>" data-warn="<?= $w ? '1' : '0' ?>" data-maintenance="<?= !empty($s['maintenance']) ? '1' : '0' ?>" data-staging="<?= $env !== 'production' ? '1' : '0' ?>" aria-labelledby="net-h-<?= e($k) ?>">
      <header class="net-card__head">
        <span class="net-dot<?= $w ? ' is-warn' : '' ?><?= isset($s['error']) || (isset($s['checks']) && in_array(false, $s['checks'], true)) ? ' is-err' : '' ?>" aria-hidden="true"></span>
        <div class="net-card__title">
          <h3 id="net-h-<?= e($k) ?>"><?= e($s['label'] ?? $k) ?></h3>
          <p><code><?= e($k) ?></code>
            <?php if (!empty($s['network'])): ?><span class="adm-badge us-net"><?= e(__('Netzwerk-Website')) ?></span><?php endif; ?>
            <?php if ($env !== 'production'): ?><span class="adm-badge adm-badge--adm-warn"><?= e($envLabel[$env] ?? $env) ?></span><?php endif; ?>
            <?php if (!empty($s['maintenance'])): ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('Wartung')) ?></span><?php endif; ?></p>
        </div>
      </header>
      <p class="net-hosts"><?php if ($hosts): ?><?php foreach ($hosts as $i => $h): ?><?= $i ? ', ' : '' ?><?= e($h) ?><?php endforeach; ?><?php else: ?><span class="adm-muted"><?= e(__('alle übrigen Domains')) ?></span><?php endif; ?></p>
      <details class="net-hostsedit">
        <summary class="adm-link"><?= e(__('Domains bearbeiten')) ?><span class="sr-only"> – <?= e($s['label'] ?? $k) ?></span></summary>
        <?php $landingHosts = array_values((array) (\Core\Sites::all()[$k]['landing_hosts'] ?? [])); ?>
        <?php foreach (array_merge($hosts, $landingHosts) as $h): ?>
        <form method="post" action="<?= e($actionUrl($k, 'hosts')) ?>" data-confirm="<?= e(__('Domain {host} von {site} entfernen?', ['host' => $h, 'site' => $s['label'] ?? $k])) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="remove"><input type="hidden" name="host" value="<?= e($h) ?>"><code><?= e($h) ?></code><?= in_array($h, $landingHosts, true) ? ' <span class="adm-badge adm-badge--muted">' . e(__('Landing')) . '</span>' : '' ?> <button type="submit" class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Entfernen')) ?><span class="sr-only"> <?= e($h) ?></span></button></form>
        <?php endforeach; ?>
        <form method="post" action="<?= e($actionUrl($k, 'hosts')) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="add">
          <label for="net-host-<?= e($k) ?>"><?= e(__('Weitere Domain (z. B. für eine Landingpage)')) ?></label>
          <input id="net-host-<?= e($k) ?>" name="host" required placeholder="kampagne.beispiel.de" autocomplete="off" spellcheck="false">
          <label class="f-check"><input type="checkbox" name="landing" value="1" checked> <span><?= e(__('Als Landing-Domain (Hauptadresse der Website bleibt)')) ?></span></label>
          <button type="submit" class="adm-btn adm-btn--small"><?= e(__('Hinzufügen')) ?></button></form>
        <p class="adm-muted"><?= e(__('Ändert config/sites/{site}.php (Sicherung .bak). DNS und Hosting (Plesk: Alias bzw. zusätzliche Domain) richtet die Agentur ein.', ['site' => $k])) ?></p>
      </details>
      <?php if ($w): ?>
      <ul class="net-warn"><?php foreach ($w as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul>
      <?php else: ?>
      <p class="net-ok"><?= e(__('Alles in Ordnung')) ?></p>
      <?php endif; ?>
      <dl class="net-dl">
        <dt><?= e(__('Kit')) ?></dt><dd><?= e($s['theme_label'] ?? ($s['theme'] ?? '–')) ?></dd>
        <dt><?= e(__('Funktionsumfang')) ?></dt><dd title="<?= e(implode(', ', $s['features_off'] ?? [])) ?>"><?= e(ucfirst((string) ($s['preset'] ?? 'full'))) ?><?= !empty($s['features_off']) ? ' · ' . e(__('{n} aus', ['n' => count($s['features_off'])])) : '' ?><?= !empty($s['extensions']) ? ' · ' . e(implode(', ', $s['extensions'])) : '' ?><?= !empty($s['features_delegated']) ? ' · ' . e(__('Website schaltet selbst')) : '' ?></dd>
        <dt><?= e(__('Letzte Änderung')) ?></dt><dd><?= e($when($s['last_change'] ?? null)) ?></dd>
        <dt><?= e(__('Redaktion')) ?></dt><dd><?= (int) ($s['editors'] ?? 0) ?> <?= e(__('Konten')) ?> · <?= (int) ($s['pages'] ?? 0) ?> <?= e(__('Seiten')) ?></dd>
        <dt><?= e(__('Neue Anfragen')) ?></dt><dd><?php $ni = (int) ($s['inbox_new'] ?? 0); ?><?= $ni ? '<strong>' . $ni . '</strong>' : '0' ?></dd>
        <?php if (isset($s['review_pending'])): $rp = (int) $s['review_pending']; ?><dt><?= e(__('Eingereicht')) ?></dt><dd><?= $rp ? '<strong>' . e(__('{n} zur Freigabe', ['n' => $rp])) . '</strong>' : e(__('nichts offen')) ?></dd><?php endif; ?>
        <?php if (isset($s['support_open'])): ?><dt><?= e(__('Support')) ?></dt><dd><?= e(__('{n} offen', ['n' => (int) $s['support_open']])) ?></dd><?php endif; ?>
        <dt><?= e(__('Speicher')) ?></dt><dd><?= e($size(isset($s['db_size']) ? (int) $s['db_size'] : null)) ?> <?= e(__('DB')) ?> · <?= e($size((int) ($s['media_size'] ?? 0))) ?> <?= e(__('Medien')) ?></dd>
        <dt><?= e(__('Sicherung')) ?></dt><dd><?= !empty($s['backup']) ? e($when((int) $s['backup']['at'])) : '<span class="adm-muted">' . e(__('keine')) . '</span>' ?></dd>
      </dl>
      <div class="net-actions">
        <form method="post" action="<?= e(url('/admin/network/open')) ?>"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e($k) ?>"><button class="adm-btn adm-btn--primary adm-btn--small" type="submit"<?= empty($s['initialized']) ? ' disabled' : '' ?>><?= e(__('Öffnen')) ?></button></form>
        <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(($s['public_url'] ?? $s['url'] ?? '') . '/') ?>" target="_blank" rel="noopener noreferrer"><?= e(__('Website ansehen')) ?> ↗</a>
        <details class="net-more">
          <summary class="adm-btn adm-btn--small adm-btn--ghost" aria-label="<?= e(__('Weitere Aktionen für {site}', ['site' => $s['label'] ?? $k])) ?>"><?= e(__('Wartung')) ?> ▾</summary>
          <div class="net-more__menu">
            <form method="post" action="<?= e($actionUrl($k, 'maintenance')) ?>"<?= empty($s['maintenance']) ? ' data-confirm="' . e(__('Wartungsmodus für {site} einschalten? Besucher sehen dann nur den Wartungshinweis.', ['site' => $s['label'] ?? $k])) . '"' : '' ?>><?= csrf_field() ?><input type="hidden" name="on" value="<?= empty($s['maintenance']) ? '1' : '0' ?>"><button type="submit"<?= empty($s['initialized']) ? ' disabled' : '' ?>><?= e(empty($s['maintenance']) ? __('Wartungsmodus an') : __('Wartungsmodus aus')) ?></button></form>
            <form method="post" action="<?= e($actionUrl($k, 'backup')) ?>"><?= csrf_field() ?><button type="submit"<?= empty($s['initialized']) ? ' disabled' : '' ?>><?= e(__('Sicherung jetzt')) ?></button></form>
            <form method="post" action="<?= e($actionUrl($k, 'cache')) ?>"><?= csrf_field() ?><button type="submit"><?= e(__('Seiten-Cache leeren')) ?></button></form>
            <form method="post" action="<?= e(url('/admin/network/open')) ?>"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e($k) ?>"><input type="hidden" name="path" value="/admin/funktionen"><button type="submit"<?= empty($s['initialized']) ? ' disabled' : '' ?>><?= e(__('Funktionen & Erweiterungen')) ?></button></form>
          </div>
        </details>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
  <p class="net-none adm-muted" data-net-none hidden><?= e(__('Keine Website passt zum Filter.')) ?></p>
</section>

<div class="adm-grid2 net-lower">
  <section class="adm-card" id="konten" aria-labelledby="net-acc-h">
    <h2 id="net-acc-h"><?= e(__('Netzwerk-Administratoren')) ?></h2>
    <p class="adm-muted"><?= e(__('Diese Konten verwalten alle Websites. Anmeldung immer mit Zwei-Faktor-Anmeldung; Sperren beendet sofort alle Sitzungen auf allen Websites.')) ?></p>
    <table class="adm-table net-acc">
      <thead><tr><th scope="col"><?= e(__('Konto')) ?></th><th scope="col"><?= e(__('Status')) ?></th><th scope="col"><span class="adm-sr"><?= e(__('Aktionen')) ?></span></th></tr></thead>
      <tbody>
      <?php foreach ($accounts as $a): $self = (int) $a['id'] === (int) $me['id']; ?>
        <tr><td><strong><?= e($a['name'] ?: $a['email']) ?></strong><br><span class="adm-muted"><?= e($a['email']) ?></span><br><small class="adm-muted"><?= e(__('Letzte Anmeldung')) ?>: <?= e($a['last_login'] ? $when($a['last_login']) : __('nie')) ?></small></td>
          <td><?php if ((int) $a['disabled']): ?><span class="adm-badge adm-badge--muted"><?= e(__('gesperrt')) ?></span><?php else: ?><span class="adm-badge"><?= e(__('aktiv')) ?></span><?php endif; ?>
            <?php $aPk = (int) ($a['passkeys'] ?? 0); if ((int) $a['totp_enabled']): ?><span class="adm-badge adm-badge--muted">2FA</span><?php endif; ?><?php if ($aPk): ?> <span class="adm-badge adm-badge--muted"><?= e(__('Passkey')) ?><?= $aPk > 1 ? ' ×' . $aPk : '' ?></span><?php endif; ?><?php if (!(int) $a['totp_enabled'] && !$aPk): ?><span class="adm-badge adm-badge--adm-warn"><?= e(__('2FA ausstehend')) ?></span><?php endif; ?></td>
          <td class="adm-actions"><?php if ($self): ?><span class="adm-badge"><?= e(__('Sie')) ?></span><?php else: ?>
            <form method="post" action="<?= e(url('/admin/network/accounts/' . $a['id'] . '/' . ((int) $a['disabled'] ? 'enable' : 'disable'))) ?>"<?= (int) $a['disabled'] ? '' : ' data-confirm="' . e(__('{email} sperren? Alle Sitzungen auf allen Websites enden sofort.', ['email' => $a['email']])) . '"' ?>><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost<?= (int) $a['disabled'] ? '' : ' adm-btn--danger-text' ?>" type="submit"><?= e((int) $a['disabled'] ? __('Entsperren') : __('Sperren')) ?></button></form>
            <?php if ((int) $a['totp_enabled'] || $aPk): ?><form method="post" action="<?= e(url('/admin/network/accounts/' . $a['id'] . '/reset-2fa')) ?>" data-confirm="<?= e(__('Zwei-Faktor-Anmeldung von {email} zurücksetzen (App-Code und alle Passkeys)?', ['email' => $a['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('2FA zurücksetzen')) ?></button></form><?php endif; ?>
          <?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php $np = \Core\Mfa::netPolicy(); // Anmelde-Richtlinie der Netzwerk-Konten (Core\Mfa, sys.net_auth) ?>
    <details class="net-add" id="net-auth">
      <summary class="adm-btn adm-btn--small"><?= e(__('Anmelde-Richtlinie')) ?></summary>
      <form method="post" action="<?= e(url('/admin/network/auth-policy')) ?>">
        <?= csrf_field() ?>
        <fieldset class="us-2fa__set"><legend><?= e(__('Erlaubte Verfahren für Netzwerk-Konten')) ?></legend>
          <label class="f-check"><input type="checkbox" name="totp" value="1"<?= $np['totp'] ? ' checked' : '' ?>> <span><?= e(__('Authenticator-App (6-stelliger Code)')) ?></span></label>
          <label class="f-check"><input type="checkbox" name="passkey" value="1"<?= $np['passkey'] ? ' checked' : '' ?>> <span><?= e(__('Passkeys')) ?></span></label>
          <label class="f-check"><input type="checkbox" name="passwordless" value="1"<?= $np['passwordless'] ? ' checked' : '' ?>> <span><?= e(__('Anmeldung ohne Passwort mit Passkey')) ?></span></label>
          <label class="f-check"><input type="checkbox" name="passkey_only" value="1"<?= $np['passkey_only'] ? ' checked' : '' ?>> <span><?= e(__('Nur Passkey als zweiter Faktor (phishing-resistent)')) ?></span></label>
        </fieldset>
        <p class="f-help"><?= e(__('Netzwerk-Konten melden sich immer mit zweitem Faktor an (App oder Passkey). Passkeys gelten je Domain – für die direkte Anmeldung auf einer anderen Website dort einen eigenen Passkey hinzufügen; „Öffnen“ aus dieser Übersicht braucht keinen.')) ?></p>
        <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Speichern')) ?></button>
      </form>
    </details>
    <details class="net-add">
      <summary class="adm-btn adm-btn--small"><?= e(__('+ Netzwerk-Konto anlegen')) ?></summary>
      <form method="post" action="<?= e(url('/admin/network/accounts')) ?>" novalidate>
        <?= csrf_field() ?>
        <div class="f"><label for="na-name"><?= e(__('Name')) ?></label><input id="na-name" name="name" autocomplete="off"></div>
        <div class="f"><label for="na-email"><?= e(__('E-Mail-Adresse')) ?> <span class="req">*</span></label><input id="na-email" name="email" type="email" required autocomplete="off"></div>
        <div class="f"><label for="na-pw"><?= e(__('Startpasswort (mind. 12 Zeichen)')) ?> <span class="req">*</span></label><input id="na-pw" name="password" type="text" required autocomplete="off" minlength="12"><p class="f-help"><?= e(__('Persönlich übergeben. Bei der ersten Anmeldung richtet die Person die Zwei-Faktor-Anmeldung ein.')) ?></p></div>
        <button class="adm-btn adm-btn--primary adm-btn--small" type="submit"><?= e(__('Anlegen')) ?></button>
      </form>
    </details>
  </section>

  <section class="adm-card" id="neu" aria-labelledby="net-new-h">
    <h2 id="net-new-h"><?= e(__('Neue Website')) ?></h2>
    <?php if ($newSite): ?>
    <div class="adm-inline-box net-created">
      <strong><?= e(__('Nächste Schritte für „{site}“', ['site' => $newSite['key']])) ?></strong>
      <ol>
        <li><?= e(__('Domain im Hosting auf denselben Ordner zeigen lassen (Dokumentstamm = httpdocs/public).')) ?></li>
        <li><?= e(__('Erster Aufruf legt Datenbank, Medienordner und Startinhalte an – danach mit „Öffnen“ direkt in die Verwaltung.')) ?></li>
        <li><?= e(__('Oder erstes lokales Konto über /admin/setup mit dem Setup-Token:')) ?> <code><?= e($newSite['token']) ?></code></li>
      </ol>
      <p class="adm-muted"><?= e(__('Konfiguration:')) ?> <code><?= e($newSite['file']) ?></code></p>
    </div>
    <?php endif; ?>
    <p class="adm-muted"><?= e(__('Legt eine Konfiguration mit eigenen Schlüsseln an – wie auf der Kommandozeile:')) ?></p>
    <pre class="net-cli"><code>php bin/console site:create &lt;key&gt; &lt;domain[,domain2]&gt; [kit]</code></pre>
    <form method="post" action="<?= e(url('/admin/network/sites')) ?>" class="net-newsite" novalidate>
      <?= csrf_field() ?>
      <div class="f"><label for="ns-key"><?= e(__('Kurzname')) ?> <span class="req">*</span></label><input id="ns-key" name="key" required pattern="[a-z][a-z0-9\-]{1,31}" maxlength="32" placeholder="kunde" autocomplete="off"></div>
      <div class="f"><label for="ns-hosts"><?= e(__('Domains (mit Komma getrennt)')) ?> <span class="req">*</span></label><input id="ns-hosts" name="hosts" required placeholder="www.kunde.de, kunde.de" autocomplete="off"></div>
      <div class="f"><label for="ns-theme"><?= e(__('Kit')) ?></label><select id="ns-theme" name="theme"><option value=""><?= e(__('Standard')) ?></option><?php foreach ($themes as $tk => $tl): ?><option value="<?= e($tk) ?>"><?= e($tl) ?></option><?php endforeach; ?></select></div>
      <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Website anlegen')) ?></button>
    </form>
  </section>

  <section class="adm-card" id="geteilt" aria-labelledby="net-shared-h">
    <h2 id="net-shared-h"><?= e(__('Geteilte Ressourcen')) ?></h2>
    <?php if (!$shared['pools'] && !$shared['tables']): ?><p class="adm-muted"><?= e(__('Keine geteilten Medien oder Daten.')) ?></p><?php endif; ?>
    <?php if ($shared['pools']): ?>
    <h3 class="net-h3"><?= e(__('Geteilte Medien')) ?></h3>
    <ul class="adm-list net-list"><?php foreach ($shared['pools'] as $p): ?><li><strong><?= e($p['label']) ?></strong> <code><?= e($p['key']) ?></code><span class="adm-muted"><?= $p['files'] === null ? '–' : e(__('{n} Dateien', ['n' => $p['files']])) ?><?= $p['sites'] ? ' · ' . e(implode(', ', $p['sites'])) : '' ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
    <?php if ($shared['tables']): ?>
    <h3 class="net-h3"><?= e(__('Geteilte Daten')) ?></h3>
    <ul class="adm-list net-list"><?php foreach ($shared['tables'] as $t): ?><li><strong><?= e($t['label']) ?></strong> <code><?= e($t['key']) ?></code><span class="adm-muted"><?= $t['entries'] === null ? '–' : e(__('{n} Einträge', ['n' => $t['entries']])) ?><?= $t['owner'] ? ' · ' . e(__('Eigentümer: {site}', ['site' => $t['owner']])) : '' ?><?= $t['members'] ? ' · ' . e(implode(', ', $t['members'])) : '' ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>

  <section class="adm-card" id="protokoll" aria-labelledby="net-log-h">
    <h2 id="net-log-h"><?= e(__('Protokoll')) ?></h2>
    <?php if (!$log): ?><p class="adm-muted"><?= e(__('Noch keine Einträge.')) ?></p><?php else: ?>
    <?php $labels = ['sso.issue' => __('Website geöffnet'), 'sso.login' => __('Angemeldet per Netzwerk'), 'login' => __('Angemeldet'),
        'maintenance.on' => __('Wartungsmodus an'), 'maintenance.off' => __('Wartungsmodus aus'), 'backup' => __('Sicherung'), 'cache.clear' => __('Cache geleert'), 'hosts.add' => __('Domain hinzugefügt'), 'hosts.remove' => __('Domain entfernt'),
        'site.create' => __('Website angelegt'), 'account.create' => __('Konto angelegt'), 'account.disable' => __('Konto gesperrt'), 'account.enable' => __('Konto entsperrt'),
        'account.reset-2fa' => __('2FA zurückgesetzt'), '2fa.enable' => __('2FA eingerichtet'), 'password' => __('Passwort geändert'),
        'user.reset-2fa' => __('2FA zurückgesetzt'), 'passkey.add' => __('Passkey hinzugefügt'), 'passkey.remove' => __('Passkey gelöscht'),
        'auth.policy' => __('Anmelde-Richtlinie geändert')]; ?>
    <ol class="net-log">
      <?php foreach ($log as $l): ?>
      <li><time datetime="<?= e(date('c', (int) strtotime((string) $l['created_at']))) ?>"><?= e($when($l['created_at'])) ?></time>
        <span><strong><?= e($labels[$l['action']] ?? $l['action']) ?></strong><?= $l['site'] ? ' · <code>' . e($l['site']) . '</code>' : '' ?><?= $l['detail'] ? ' · ' . e($l['detail']) : '' ?></span>
        <small class="adm-muted"><?= e((string) $l['user_email']) ?></small></li>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </section>
</div>
