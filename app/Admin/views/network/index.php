<?php
/**
 * Netzwerk-Übersicht: alle Websites der Installation (Core\Network\Stats), Netzwerk-Konten, geteilte Ressourcen, Protokoll.
 * Karten je Website mit App-Icon (Core\Network\SiteIcon), Status, Inhalten, Aktivität und Betrieb; Stile in resources/css/network.css,
 * Filter/Suche/Ansicht in resources/js/_network.js.
 * @var array $stats  @var array $warn  @var array $accounts  @var array $log  @var array $shared  @var array $themes  @var array $me  @var ?array $newSite
 * @var array $invites offene Einladungen als Netzwerk-Administration (Core\Invites::open(true))  @var ?array $inviteLink  @var ?array $inviteOld
 */
$invites ??= [];
$inviteOld ??= null;
$invErr = (array) ($inviteOld['errors'] ?? []);
$title = __('Netzwerk');
$size = function (?int $b): string {
    if ($b === null) return '–';
    foreach ([__('B'), 'KB', 'MB', 'GB'] as $i => $u) {
        if ($b < 1024 ** ($i + 1) || $u === 'GB') return ($i ? number_format($b / 1024 ** $i, $i > 1 ? 1 : 0, ',', '.') : (string) $b) . ' ' . $u;
    }
    return (string) $b;
};
$ts = fn($t): int => is_int($t) ? $t : (int) strtotime((string) $t);
$when = fn($t) => $t ? date('d.m.Y H:i', $ts($t)) : '–';
// Relative Zeitangabe („vor 3 Std.“) mit genauem Datum als <time> + title
$ago = function ($t) use ($ts, $when): string {
    if (!$t) return '–';
    $d = time() - $ts($t);
    $txt = match (true) {
        $d < 90 => __('gerade eben'),
        $d < 3600 => __('vor {n} Min.', ['n' => (int) round($d / 60)]),
        $d < 86400 => __('vor {n} Std.', ['n' => (int) round($d / 3600)]),
        $d < 2 * 86400 => __('gestern'),
        $d < 45 * 86400 => __('vor {n} Tagen', ['n' => (int) floor($d / 86400)]),
        default => date('d.m.Y', $ts($t)),
    };
    return '<time datetime="' . e(date('c', $ts($t))) . '" title="' . e($when($t)) . '">' . e($txt) . '</time>';
};
$envLabel = ['production' => __('Live'), 'staging' => __('Staging'), 'development' => __('Entwicklung')];
$nWarn = count(array_filter($warn));
$nMaint = count(array_filter($stats, fn($s) => !empty($s['maintenance'])));
$nOff = count(array_filter($stats, fn($s) => ($s['environment'] ?? 'production') !== 'production' || !empty($s['noindex'])));
$inbox = array_sum(array_map(fn($s) => (int) ($s['inbox_new'] ?? 0), $stats));
$review = array_sum(array_map(fn($s) => (int) ($s['review_pending'] ?? 0), $stats));
$pools = array_column($shared['pools'], null, 'key');
$poolBytes = array_sum(array_map(fn($p) => (int) ($p['size'] ?? 0), $pools));
$bytes = array_sum(array_map(fn($s) => (int) ($s['db_size'] ?? 0) + (int) ($s['media_size'] ?? 0), $stats)) + $poolBytes;
$oldest = min(array_map(fn($s) => (int) ($s['at'] ?? time()), $stats) ?: [time()]);
$actionUrl = fn(string $k, string $a) => url('/admin/network/site/' . $k . '/' . $a);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Netzwerk-Administration')) ?></p><h1><?= e(__('Alle Websites')) ?></h1>
    <p class="adm-muted"><?= e(__('Übersicht aller Websites dieser Installation. „Öffnen“ meldet Sie mit Ihrem Netzwerk-Konto in der Verwaltung der jeweiligen Website an.')) ?></p></div>
  <a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e(url('/admin/network?refresh=1')) ?>" title="<?= e(__('Stand: {time}', ['time' => date('H:i:s', $oldest)])) ?>"><?= e(__('Aktualisieren')) ?></a>
</header>

<?php // Zusammenfassung: eine Zeile, Nullen ruhig; Hinweise/Wartung/Nicht live filtern die Karten ?>
<ul class="net-strip" aria-label="<?= e(__('Zusammenfassung')) ?>">
  <li class="net-strip__item net-strip__item--lead"><strong><?= count($stats) ?></strong> <span><?= e(__('Websites')) ?></span></li>
  <li class="net-strip__item<?= $nWarn ? ' is-warn' : ' is-calm' ?>"><a href="#websites" data-net-show="warn"><strong><?= $nWarn ?></strong> <span><?= e(__('mit Hinweisen')) ?></span></a></li>
  <li class="net-strip__item<?= $nMaint ? ' is-warn' : ' is-calm' ?>"><a href="#websites" data-net-show="maintenance"><strong><?= $nMaint ?></strong> <span><?= e(__('im Wartungsmodus')) ?></span></a></li>
  <li class="net-strip__item<?= $nOff ? '' : ' is-calm' ?>"><a href="#websites" data-net-show="staging"><strong><?= $nOff ?></strong> <span><?= e(__('nicht live')) ?></span></a></li>
  <li class="net-strip__item<?= $inbox ? ' is-hot' : ' is-calm' ?>"><strong><?= $inbox ?></strong> <span><?= e(__('neue Anfragen')) ?></span></li>
  <li class="net-strip__item<?= $review ? ' is-hot' : ' is-calm' ?>"><strong><?= $review ?></strong> <span title="<?= e(__('Änderungen zur Freigabe (API, MCP, KI)')) ?>"><?= e(__('zur Freigabe')) ?></span></li>
  <li class="net-strip__item net-strip__item--end"><strong><?= e($size($bytes)) ?></strong> <span title="<?= e(__('Datenbanken, Medien der Websites und geteilte Pools')) ?>"><?= e(__('Speicher gesamt')) ?></span></li>
</ul>

<section id="websites" aria-labelledby="net-sites-h">
  <div class="net-bar">
    <h2 id="net-sites-h" class="adm-sr"><?= e(__('Websites')) ?></h2>
    <label class="net-search"><span class="adm-sr"><?= e(__('Websites durchsuchen')) ?></span><?= icon('magnifying-glass', ['class' => 'net-search__ico']) ?><input type="search" data-net-q placeholder="<?= e(__('Name, Domain, Kit …')) ?>" autocomplete="off"></label>
    <div class="net-seg" role="group" aria-label="<?= e(__('Filter')) ?>">
      <?php foreach (['all' => __('Alle'), 'warn' => __('Mit Hinweisen'), 'maintenance' => __('Wartung'), 'staging' => __('Nicht live')] as $fk => $fl): ?>
      <button type="button" data-net-filter="<?= e($fk) ?>" aria-pressed="<?= $fk === 'all' ? 'true' : 'false' ?>"><?= e($fl) ?></button>
      <?php endforeach; ?>
    </div>
    <p class="adm-muted net-count" data-net-count aria-live="polite"></p>
    <div class="net-seg net-view" role="group" aria-label="<?= e(__('Ansicht')) ?>" data-net-views hidden>
      <button type="button" data-net-view="grid" aria-pressed="true" title="<?= e(__('Kacheln')) ?>"><?= icon('squares-four') ?><span class="adm-sr"><?= e(__('Kacheln')) ?></span></button>
      <button type="button" data-net-view="list" aria-pressed="false" title="<?= e(__('Liste')) ?>"><?= icon('list-bullets') ?><span class="adm-sr"><?= e(__('Liste')) ?></span></button>
    </div>
  </div>

  <div class="net-grid" data-net-grid>
  <?php foreach ($stats as $k => $s):
    $w = $warn[$k] ?? []; $env = $s['environment'] ?? 'production'; $hosts = $s['hosts'] ?? []; $name = $s['label'] ?? $k;
    $maint = !empty($s['maintenance']); $noindex = !empty($s['noindex']); $offline = $env !== 'production' || $noindex;
    $broken = isset($s['error']) || (isset($s['checks']) && in_array(false, $s['checks'], true));
    // Status: Störung > Wartung > Hinweise > Nicht live > in Ordnung (Wartung steht auch in den Hinweisen – dort nicht doppelt zählen)
    $wOther = array_values(array_filter($w, fn($m) => $m !== __('Wartungsmodus an')));
    [$pill, $pillText] = match (true) {
        $broken => ['err', __('Störung')],
        $maint => ['warn', __('Wartung')],
        (bool) $wOther => ['warn', count($wOther) === 1 ? __('1 Hinweis') : __('{n} Hinweise', ['n' => count($wOther)])],
        $offline => ['off', __('Nicht live')],
        default => ['ok', __('Alles in Ordnung')],
    };
    $pub = rtrim((string) ($s['public_url'] ?? $s['url'] ?? ''), '/');
    $pu = parse_url($pub) ?: [];
    $primary = isset($pu['host']) ? strtolower($pu['host'] . (isset($pu['port']) ? ':' . $pu['port'] : '')) : ($hosts[0] ?? '');
    $landingHosts = array_values((array) (\Core\Sites::all()[$k]['landing_hosts'] ?? []));
    $others = array_values(array_filter(array_merge($hosts, $landingHosts), fn($h) => $h !== $primary));
    $ico = \Core\Network\SiteIcon::for($k, $s);
    $ni = (int) ($s['inbox_new'] ?? 0); $rp = isset($s['review_pending']) ? (int) $s['review_pending'] : null; $so = isset($s['support_open']) ? (int) $s['support_open'] : null;
    $bk = !empty($s['backup']) ? (int) $s['backup']['at'] : null; $bkOld = $bk === null || $bk < time() - 2 * 86400;
    $search = mb_strtolower(implode(' ', [$k, $name, implode(' ', $hosts), implode(' ', $landingHosts), $s['theme'] ?? '', $s['theme_label'] ?? '', $s['preset'] ?? ''])); ?>
    <article class="net-card net-card--<?= e($pill) ?>" id="site-<?= e($k) ?>" data-net-card
      data-q="<?= e($search) ?>" data-warn="<?= $w ? '1' : '0' ?>" data-maintenance="<?= $maint ? '1' : '0' ?>" data-staging="<?= $offline ? '1' : '0' ?>" aria-labelledby="net-h-<?= e($k) ?>">
      <header class="net-card__head">
        <img class="net-ico<?= $ico['fallback'] ? ' net-ico--letters' : '' ?>" src="<?= e($ico['src']) ?>" width="44" height="44" alt="" decoding="async">
        <div class="net-card__title">
          <h3 id="net-h-<?= e($k) ?>"><?= e($name) ?></h3>
          <?php if ($primary !== ''): ?>
          <a class="net-card__host" href="<?= e($pub . '/') ?>" target="_blank" rel="noopener noreferrer"><?= e($primary) ?><span class="adm-sr"> <?= e(__('(öffnet in neuem Tab)')) ?></span></a>
          <?php else: ?><span class="net-card__host adm-muted"><?= e(__('alle übrigen Domains')) ?></span><?php endif; ?>
        </div>
        <span class="net-pill net-pill--<?= e($pill) ?>"><?= icon($pill === 'ok' ? 'check-circle' : ($pill === 'off' ? 'eye' : 'warning')) ?><?= e($pillText) ?></span>
      </header>
      <p class="net-tags">
        <code class="net-key" title="<?= e(__('Kurzname der Website')) ?>"><?= e($k) ?></code>
        <?php if (!empty($s['network'])): ?><span class="net-tag net-tag--net"><?= e(__('Netzwerk-Website')) ?></span><?php endif; ?>
        <span class="net-tag<?= $env === 'production' ? ' net-tag--live' : ' net-tag--warn' ?>"><?= e($envLabel[$env] ?? $env) ?></span>
        <?php if ($noindex): ?><span class="net-tag net-tag--muted" title="<?= e(__('Suchmaschinen sollen die Website nicht aufnehmen')) ?>">noindex</span><?php endif; ?>
        <?php if ($maint): ?><span class="net-tag net-tag--warn"><?= e(__('Wartungsmodus')) ?></span><?php endif; ?>
      </p>
      <?php if ($wOther || $broken): ?>
      <ul class="net-warn"><?php foreach ($wOther ?: $w as $msg): ?><li><?= icon('warning') ?><?= e($msg) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>

      <div class="net-facts">
        <section class="net-fact" aria-labelledby="net-f1-<?= e($k) ?>">
          <h4 id="net-f1-<?= e($k) ?>" class="net-fact__h"><?= icon('files') ?><?= e(__('Inhalte')) ?></h4>
          <p class="net-fact__big"><strong><?= (int) ($s['pages'] ?? 0) ?></strong> <?= e(__('Seiten')) ?></p>
          <p class="net-fact__sub"><?= (int) ($s['editors'] ?? 0) === 1 ? e(__('1 Konto')) : e(__('{n} Konten', ['n' => (int) ($s['editors'] ?? 0)])) ?> · <?= e(__('geändert')) ?> <?= $ago($s['last_change'] ?? null) ?></p>
        </section>
        <section class="net-fact" aria-labelledby="net-f2-<?= e($k) ?>">
          <h4 id="net-f2-<?= e($k) ?>" class="net-fact__h"><?= icon('tray') ?><?= e(__('Aktivität')) ?></h4>
          <?php if ($ni): ?><p class="net-fact__big is-hot"><strong><?= $ni ?></strong> <?= e($ni === 1 ? __('neue Anfrage') : __('neue Anfragen')) ?></p>
          <?php else: ?><p class="net-fact__calm"><?= e(__('Keine neuen Anfragen')) ?></p><?php endif; ?>
          <p class="net-fact__sub"><?php if ($rp !== null): ?><?= $rp ? '<b class="is-hot">' . e(__('{n} zur Freigabe', ['n' => $rp])) . '</b>' : e(__('nichts zur Freigabe')) ?><?php endif; ?><?php if ($so !== null): ?><?= $rp !== null ? ' · ' : '' ?><?= $so ? '<b>' . e(__('Support: {n} offen', ['n' => $so])) . '</b>' : e(__('Support: nichts offen')) ?><?php endif; ?></p>
        </section>
        <section class="net-fact net-fact--wide" aria-labelledby="net-f3-<?= e($k) ?>">
          <h4 id="net-f3-<?= e($k) ?>" class="net-fact__h"><?= icon('gear-six') ?><?= e(__('Betrieb')) ?></h4>
          <dl class="net-ops">
            <dt><?= e(__('Kit')) ?></dt><dd><?= e($s['theme_label'] ?? ($s['theme'] ?? '–')) ?></dd>
            <dt><?= e(__('Funktionen')) ?></dt><dd title="<?= e(implode(', ', $s['features_off'] ?? [])) ?>"><?= e(ucfirst((string) ($s['preset'] ?? 'full'))) ?><?= !empty($s['features_off']) ? ' · ' . e(__('{n} aus', ['n' => count($s['features_off'])])) : '' ?><?= !empty($s['extensions']) ? ' · ' . e(implode(', ', $s['extensions'])) : '' ?><?= !empty($s['features_delegated']) ? ' · ' . e(__('Website schaltet selbst')) : '' ?></dd>
            <dt><?= e(__('Speicher')) ?></dt><dd><?= e($size(isset($s['db_size']) ? (int) $s['db_size'] : null)) ?> <?= e(__('DB')) ?> · <?= e($size((int) ($s['media_size'] ?? 0))) ?> <?= e(__('eigene Medien')) ?>
              <?php foreach ((array) ($s['pools'] ?? []) as $pk): $p = $pools[$pk] ?? null; if (!$p) continue; $pn = count($p['sites']); ?>
              <span class="net-pool"><?= icon('images') ?><span><?= e(__('Pool „{pool}“', ['pool' => $p['label']])) ?> <?= e($size((int) ($p['size'] ?? 0))) ?> · <?= e($pn > 1 ? __('geteilt mit {n} Websites', ['n' => $pn]) : __('nur diese Website')) ?></span></span>
              <?php endforeach; ?></dd>
            <dt><?= e(__('Sicherung')) ?></dt><dd<?= $bkOld && $env === 'production' ? ' class="is-warn"' : '' ?>><?= $bk ? $ago($bk) : e(__('keine')) ?><?= $bkOld && $env === 'production' ? ' ' . icon('warning', ['label' => __('Älter als 2 Tage')]) : '' ?></dd>
          </dl>
        </section>
      </div>

      <details class="net-domains">
        <summary class="adm-link"><?= $others ? e(__('+{n} weitere Adressen', ['n' => count($others)])) : e(__('Domains bearbeiten')) ?><span class="adm-sr"> – <?= e($name) ?></span></summary>
        <ul class="net-domains__list">
        <?php foreach (array_merge($hosts, $landingHosts) as $h): ?>
          <li><form method="post" action="<?= e($actionUrl($k, 'hosts')) ?>" data-confirm="<?= e(__('Domain {host} von {site} entfernen?', ['host' => $h, 'site' => $name])) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="remove"><input type="hidden" name="host" value="<?= e($h) ?>"><code><?= e($h) ?></code><?= $h === $primary ? ' <span class="net-tag net-tag--muted">' . e(__('Hauptadresse')) . '</span>' : '' ?><?= in_array($h, $landingHosts, true) ? ' <span class="net-tag net-tag--muted">' . e(__('Landing')) . '</span>' : '' ?> <button type="submit" class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Entfernen')) ?><span class="adm-sr"> <?= e($h) ?></span></button></form></li>
        <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= e($actionUrl($k, 'hosts')) ?>" class="net-domains__add"><?= csrf_field() ?><input type="hidden" name="op" value="add">
          <label for="net-host-<?= e($k) ?>"><?= e(__('Weitere Domain (z. B. für eine Landingpage)')) ?></label>
          <input id="net-host-<?= e($k) ?>" name="host" required placeholder="kampagne.beispiel.de" autocomplete="off" spellcheck="false">
          <label class="f-check"><input type="checkbox" name="landing" value="1" checked> <span><?= e(__('Als Landing-Domain (Hauptadresse der Website bleibt)')) ?></span></label>
          <button type="submit" class="adm-btn adm-btn--small"><?= e(__('Hinzufügen')) ?></button></form>
        <p class="adm-muted"><?= e(__('Ändert config/sites/{site}.php (Sicherung .bak). DNS und Hosting (Plesk: Alias bzw. zusätzliche Domain) richtet die Agentur ein.', ['site' => $k])) ?></p>
      </details>

      <div class="net-actions">
        <form method="post" action="<?= e(url('/admin/network/open')) ?>"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e($k) ?>"><button class="adm-btn adm-btn--primary adm-btn--small" type="submit"<?= empty($s['initialized']) ? ' disabled' : '' ?>><?= e(__('Öffnen')) ?><span class="adm-sr"> – <?= e($name) ?></span></button></form>
        <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($pub . '/') ?>" target="_blank" rel="noopener noreferrer"><?= e(__('Website ansehen')) ?> <?= icon('arrow-square-out') ?><span class="adm-sr"> – <?= e($name) ?> <?= e(__('(öffnet in neuem Tab)')) ?></span></a>
        <details class="net-more">
          <summary class="adm-btn adm-btn--small adm-btn--ghost" aria-label="<?= e(__('Weitere Aktionen für {site}', ['site' => $name])) ?>"><?= e(__('Wartung')) ?> <?= icon('caret-down') ?></summary>
          <div class="net-more__menu">
            <form method="post" action="<?= e($actionUrl($k, 'maintenance')) ?>"<?= !$maint ? ' data-confirm="' . e(__('Wartungsmodus für {site} einschalten? Besucher sehen dann nur den Wartungshinweis.', ['site' => $name])) . '"' : '' ?>><?= csrf_field() ?><input type="hidden" name="on" value="<?= !$maint ? '1' : '0' ?>"><button type="submit"<?= empty($s['initialized']) ? ' disabled' : '' ?>><?= e(!$maint ? __('Wartungsmodus an') : __('Wartungsmodus aus')) ?></button></form>
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
    <?php if (!empty($inviteLink)): // E-Mail nicht zugestellt: Link einmal anzeigen (gespeichert ist nur ein Hash) ?>
    <div class="adm-inline-box adm-card--secret inv-link net-invlink" id="net-invite-link" role="alert">
      <strong><?= e(__('Einladung angelegt – E-Mail nicht zugestellt')) ?></strong>
      <p><?= e($inviteLink['error'] ? __('Die E-Mail an {email} konnte nicht gesendet werden: {error}', ['email' => $inviteLink['email'], 'error' => $inviteLink['error']])
        : __('Die E-Mail an {email} wurde nicht zugestellt (Testumgebung oder Versand deaktiviert – nur protokolliert bzw. umgeleitet).', ['email' => $inviteLink['email']])) ?></p>
      <p><?= e(__('Geben Sie den Link selbst weiter, z. B. per Messenger. Er ist nur jetzt sichtbar, gilt einmal und bis zum Ablauf der Einladung.')) ?></p>
      <div class="adm-secret"><code id="net-inv-url"><?= e($inviteLink['url']) ?></code><button type="button" class="adm-btn adm-btn--small" data-copy="#net-inv-url"><?= e(__('Kopieren')) ?></button></div>
    </div>
    <?php endif; ?>
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
      <?php if ($invites): // Offene Einladungen (Core\Invites, Rolle network): erneut senden, zurückziehen ?>
      <tbody id="net-einladungen" class="inv-rows">
      <?php foreach ($invites as $iv): ?>
        <tr class="inv-row<?= $iv['expired'] ? ' is-expired' : '' ?>"><td><strong><?= e($iv['name'] ?: $iv['email']) ?></strong><br><span class="adm-muted"><?= e($iv['email']) ?></span>
            <br><small class="adm-muted inv-meta"><?= e(__('Eingeladen am {date}', ['date' => date('d.m.Y', strtotime((string) $iv['created_at']))])) ?><?php if ($iv['invited_by_name']): ?> · <?= e(__('von {name}', ['name' => $iv['invited_by_name']])) ?><?php endif; ?>
            · <?= e($iv['expired'] ? __('abgelaufen am {date}', ['date' => date('d.m.Y', (int) $iv['expires_at'])]) : __('gültig bis {date}', ['date' => date('d.m.Y', (int) $iv['expires_at'])])) ?></small></td>
          <td><?php if ($iv['expired']): ?><span class="adm-badge adm-badge--muted inv-badge is-expired"><?= e(__('Einladung abgelaufen')) ?></span>
            <?php else: ?><span class="adm-badge inv-badge"><?= e(__('Eingeladen – wartet')) ?></span><?php endif; ?></td>
          <td class="adm-actions">
            <form method="post" action="<?= e(url('/admin/network/invites/' . $iv['id'] . '/resend')) ?>" data-confirm="<?= e(__('Einladung an {email} erneut senden? Der bisherige Link gilt dann nicht mehr.', ['email' => $iv['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('Erneut senden')) ?></button></form>
            <form method="post" action="<?= e(url('/admin/network/invites/' . $iv['id'] . '/revoke')) ?>" data-confirm="<?= e(__('Einladung an {email} zurückziehen? Der Link gilt dann nicht mehr.', ['email' => $iv['email']])) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" type="submit"><?= e(__('Zurückziehen')) ?></button></form></td></tr>
      <?php endforeach; ?>
      </tbody>
      <?php endif; ?>
    </table>
    <details class="net-add net-invite" id="net-invite"<?= $inviteOld ? ' open' : '' ?>>
      <summary class="adm-btn adm-btn--small adm-btn--primary"><?= e(__('+ Netzwerk-Admin einladen')) ?></summary>
      <form method="post" action="<?= e(url('/admin/network/invite')) ?>" class="inv-form" novalidate>
        <?= csrf_field() ?>
        <p class="adm-muted"><?= e(__('Die Person bekommt eine E-Mail mit einem Link ({n} Tage gültig), legt Passkey und/oder Passwort fest und richtet sofort die Zwei-Faktor-Anmeldung ein. Das Konto hat Zugriff auf alle Websites.', ['n' => \Core\Invites::days()])) ?></p>
        <div class="f<?= isset($invErr['email']) ? ' f--error' : '' ?>"><label for="ni-email"><?= e(__('E-Mail-Adresse')) ?> <span class="req">*</span></label><input id="ni-email" name="email" type="email" required maxlength="191" autocomplete="off" value="<?= e($inviteOld['email'] ?? '') ?>"<?= isset($invErr['email']) ? ' aria-invalid="true" aria-describedby="ni-err-email"' : '' ?>><?php if (isset($invErr['email'])): ?><p class="f-error" id="ni-err-email"><?= e($invErr['email']) ?></p><?php endif; ?></div>
        <div class="f"><label for="ni-name"><?= e(__('Name (optional)')) ?></label><input id="ni-name" name="name" maxlength="<?= \Core\Invites::NAME_MAX ?>" autocomplete="off" value="<?= e($inviteOld['name'] ?? '') ?>"></div>
        <div class="f<?= isset($invErr['message']) ? ' f--error' : '' ?>"><label for="ni-msg"><?= e(__('Persönliche Nachricht (optional)')) ?></label><textarea id="ni-msg" name="message" rows="3" maxlength="<?= \Core\Invites::MESSAGE_MAX ?>" aria-describedby="ni-msg-h"><?= e($inviteOld['message'] ?? '') ?></textarea>
          <p class="f-help" id="ni-msg-h"><?= e(__('Reiner Text, höchstens {n} Zeichen. Erscheint als Zitat in der E-Mail.', ['n' => \Core\Invites::MESSAGE_MAX])) ?></p><?php if (isset($invErr['message'])): ?><p class="f-error"><?= e($invErr['message']) ?></p><?php endif; ?></div>
        <?php if (count(\Core\I18n::available()) > 1): ?>
        <div class="f"><label for="ni-lang"><?= e(__('Sprache der Einladung')) ?></label><select id="ni-lang" name="locale"><?php foreach (\Core\I18n::available() as $lk => $ll): ?><option value="<?= e($lk) ?>"<?= $lk === \Core\I18n::locale() ? ' selected' : '' ?>><?= e($ll) ?></option><?php endforeach; ?></select></div>
        <?php endif; ?>
        <p class="inv-hint"><?= e(__('Netzwerk-Konten verwalten ALLE Websites dieser Installation. Laden Sie nur Personen ein, denen Sie das anvertrauen.')) ?></p>
        <button class="adm-btn adm-btn--primary adm-btn--small" type="submit"><?= e(__('Einladung senden')) ?></button>
      </form>
    </details>
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
      <summary class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Ohne E-Mail: mit Startpasswort anlegen')) ?></summary>
      <form method="post" action="<?= e(url('/admin/network/accounts')) ?>" novalidate>
        <?= csrf_field() ?>
        <p class="adm-muted"><?= e(__('Nur für Installationen ohne E-Mail-Versand – sonst besser einladen: Dann muss niemand ein Passwort weitergeben.')) ?></p>
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
    <ul class="adm-list net-list"><?php foreach ($shared['pools'] as $p): ?><li><strong><?= e($p['label']) ?></strong> <code><?= e($p['key']) ?></code><span class="adm-muted"><?= $p['files'] === null ? '–' : e(__('{n} Dateien', ['n' => $p['files']])) ?><?= isset($p['size']) ? ' · ' . e($size((int) $p['size'])) : '' ?><?= $p['sites'] ? ' · ' . e(__('genutzt von {sites}', ['sites' => implode(', ', $p['sites'])])) : '' ?></span></li><?php endforeach; ?></ul>
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
