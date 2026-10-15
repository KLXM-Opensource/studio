<?php
/**
 * Netzwerk → Websites (Teil von network/index.php, Variablen von dort).
 * Oben „Braucht Aufmerksamkeit“: alle Hinweise aller Websites als eine Liste (je Website eine Zeile, Klick öffnet sie);
 * darunter ruhige Karten – Name, Adresse, Status, eine Zeile Kennzahlen; Betrieb und Domains unter „Details“.
 */
// Hinweise je Website (Wartung zählt als Status, nicht als Hinweis); Störung bzw. offene Einrichtung zuerst
$attention = [];
foreach ($stats as $k => $s) {
    $w = array_values(array_filter($warn[$k] ?? [], fn($m) => $m !== __('Wartungsmodus an')));
    $broken = isset($s['error']) || (isset($s['checks']) && in_array(false, $s['checks'], true));
    if (!empty($s['pending'])) array_unshift($w, __('Noch nicht eingerichtet – Kit und Startinhalte wählen'));
    if ($broken && !$w) $w = [(string) ($s['error'] ?? __('Störung'))];
    if ($w) $attention[$k] = ['msgs' => $w, 'broken' => $broken, 'pending' => !empty($s['pending'])];
}
uasort($attention, fn($a, $b) => [$b['broken'], $b['pending'], count($b['msgs'])] <=> [$a['broken'], $a['pending'], count($a['msgs'])]);
$attShow = 5;
// Wer ist gerade online (Core\Presence::ONLINE) – je Person die jüngste Meldung über alle Websites; je Website die Personen dort
$onlineAll = \Core\Http\Controllers\Admin\PresenceController::on() ? \Core\Presence::onlineList() : [];
$onlineBySite = [];
foreach ($onlineAll as $o) $onlineBySite[(string) $o['site']][] = $o;
$onlinePeople = [];
foreach ($onlineAll as $o) $onlinePeople[(string) $o['user']] ??= $o;   // Liste ist nach Zeit sortiert – erste Meldung = aktuell
$mins = fn(array $o) => max(0, (int) floor((time() - (int) $o['at']) / 60));
$onlineText = fn(array $o) => $mins($o) < 1 ? __('gerade eben') : __('vor {n} Min.', ['n' => $mins($o)]);
$openForm = function (string $k, string $label, string $path = '', string $cls = 'adm-btn adm-btn--small', bool $disabled = false): string {
    // Immer mit Ziel: ohne Pfad landete die Netzwerk-Website wieder hier (open() → /admin/network) – „Öffnen“ heißt: Übersicht der Website
    return '<form method="post" action="' . e(url('/admin/network/open')) . '">' . csrf_field() . '<input type="hidden" name="site" value="' . e($k) . '">'
        . '<input type="hidden" name="path" value="' . e($path !== '' ? $path : '/admin') . '">'
        . '<button class="' . e($cls) . '" type="submit"' . ($disabled ? ' disabled' : '') . '>' . $label . '</button></form>';
};
?>
<?php $netKey = \Core\Network\Network::siteKey(); if (!empty($stats[$netKey]['pending'])): ?>
<div class="net-setup net-setup--top">
  <p><strong><?= e(__('Willkommen! Als Erstes die Website „{site}“ einrichten.', ['site' => $stats[$netKey]['label'] ?? $netKey])) ?></strong> <?= e(__('Wählen Sie Kit und Startinhalte – danach legen Sie hier weitere Websites an.')) ?></p>
  <a class="adm-btn adm-btn--primary adm-btn--small" href="<?= e(url('/admin/willkommen')) ?>"><?= e(__('Jetzt einrichten')) ?></a>
</div>
<?php endif; ?>

<?php if ($onlinePeople): ?>
<section class="net-online" aria-labelledby="net-online-h">
  <h2 id="net-online-h" class="net-online__h"><span class="net-online__dot" aria-hidden="true"></span><?= e(__('Gerade online')) ?> <span class="net-online__n"><?= count($onlinePeople) ?></span></h2>
  <ul class="net-online__list">
  <?php foreach ($onlinePeople as $o): $sk = (string) $o['site']; ?>
    <li><form method="post" action="<?= e(url('/admin/network/open')) ?>"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e($sk) ?>"><input type="hidden" name="path" value="<?= e((string) ($o['url'] ?? '') ?: '/admin') ?>">
      <button type="submit" class="net-online__b" title="<?= e(__('Dorthin wechseln')) ?>"><span class="net-presence__ava" aria-hidden="true"><?= e((string) ($o['initials'] ?? '?')) ?></span>
        <span class="net-online__who"><span class="net-online__line"><b><?= e((string) ($o['name'] ?? '')) ?></b><?= !empty($o['net']) ? '<span class="net-tag net-tag--net">' . e(__('Netzwerk')) . '</span>' : '' ?></span>
        <small><?= e((string) ($stats[$sk]['label'] ?? $sk)) ?> · <?= e((string) ($o['label'] ?? '') ?: __('Verwaltung')) ?> · <?= e($onlineText($o)) ?></small></span></button></form></li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>

<?php // Kennzahlen: eine ruhige Zeile; Nullen gedämpft ?>
<ul class="net-strip net-strip--calm" aria-label="<?= e(__('Zusammenfassung')) ?>">
  <li class="net-strip__item net-strip__item--lead"><strong><?= count($stats) ?></strong> <span><?= e(__('Websites')) ?></span></li>
  <li class="net-strip__item<?= $nOff ? '' : ' is-calm' ?>"><a href="#websites" data-net-show="staging"><strong><?= $nOff ?></strong> <span><?= e(__('nicht live')) ?></span></a></li>
  <li class="net-strip__item<?= $nMaint ? ' is-warn' : ' is-calm' ?>"><a href="#websites" data-net-show="maintenance"><strong><?= $nMaint ?></strong> <span><?= e(__('im Wartungsmodus')) ?></span></a></li>
  <li class="net-strip__item<?= $inbox ? ' is-hot' : ' is-calm' ?>"><strong><?= $inbox ?></strong> <span><?= e(__('neue Anfragen')) ?></span></li>
  <li class="net-strip__item<?= $review ? ' is-hot' : ' is-calm' ?>"><strong><?= $review ?></strong> <span title="<?= e(__('Änderungen zur Freigabe (API, MCP, KI)')) ?>"><?= e(__('zur Freigabe')) ?></span></li>
  <li class="net-strip__item net-strip__item--end is-calm"><a href="<?= e(url('/admin/network/speicher')) ?>" title="<?= e(__('Datenbanken, Medien der Websites und geteilte Pools – aufschlüsseln')) ?>"><strong><?= e($size($bytes)) ?></strong> <span><?= e(__('Speicher')) ?></span></a></li>
</ul>

<?php if ($attention): ?>
<section class="net-att" aria-labelledby="net-att-h">
  <h2 id="net-att-h" class="net-att__h"><?= icon('warning') ?> <?= e(__('Braucht Aufmerksamkeit')) ?> <span class="net-att__n"><?= count($attention) ?></span></h2>
  <ul class="net-att__list">
  <?php $i = 0; foreach ($attention as $k => $a): $s = $stats[$k]; $name = $s['label'] ?? $k; $ico = \Core\Network\SiteIcon::for($k, $s); $more = count($a['msgs']) - 1; ?>
    <?php if ($i++ === $attShow): ?></ul><details class="net-att__more"><summary class="adm-link"><?= e(__('{n} weitere Websites mit Hinweisen', ['n' => count($attention) - $attShow])) ?></summary><ul class="net-att__list"><?php endif; ?>
    <li class="net-att__row<?= $a['broken'] ? ' is-err' : '' ?>">
      <img class="net-att__ico" src="<?= e($ico['src']) ?>" width="24" height="24" alt="" decoding="async">
      <a class="net-att__site" href="#site-<?= e($k) ?>"><?= e($name) ?></a>
      <span class="net-att__msg"><?= e($a['msgs'][0]) ?><?php if ($more > 0): ?> <details class="net-att__all"><summary><?= e(__('+{n} weitere', ['n' => $more])) ?></summary><ul><?php foreach (array_slice($a['msgs'], 1) as $m): ?><li><?= e($m) ?></li><?php endforeach; ?></ul></details><?php endif; ?></span>
      <?= $openForm($k, e(__('Öffnen')) . '<span class="adm-sr"> – ' . e($name) . '</span>', $a['pending'] ? '/admin/willkommen' : '', 'adm-btn adm-btn--small', empty($s['initialized'])) ?>
    </li>
  <?php endforeach; ?>
  </ul><?= count($attention) > $attShow ? '</details>' : '' ?>
</section>
<?php else: ?>
<p class="net-allok"><?= icon('check-circle') ?> <?= e(__('Alles in Ordnung – keine Website braucht gerade Aufmerksamkeit.')) ?></p>
<?php endif; ?>

<section id="websites" aria-labelledby="net-sites-h">
  <div class="net-bar">
    <h2 id="net-sites-h" class="net-bar__h"><?= e(__('Websites')) ?></h2>
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
    $nHints = count($attention[$k]['msgs'] ?? []);
    // Status: Störung > Einrichtung offen > Wartung > Hinweise > Nicht live > in Ordnung
    [$pill, $pillText] = match (true) {
        $broken => ['err', __('Störung')],
        !empty($s['pending']) => ['warn', __('Einrichtung offen')],
        $maint => ['warn', __('Wartung')],
        $nHints > 0 => ['warn', $nHints === 1 ? __('1 Hinweis') : __('{n} Hinweise', ['n' => $nHints])],
        $offline => ['off', $envLabel[$env] ?? __('Nicht live')],
        default => ['ok', __('Live')],
    };
    $pub = rtrim((string) ($s['public_url'] ?? $s['url'] ?? ''), '/');
    $pu = parse_url($pub) ?: [];
    $primary = isset($pu['host']) ? strtolower($pu['host'] . (isset($pu['port']) ? ':' . $pu['port'] : '')) : ($hosts[0] ?? '');
    $landingHosts = array_values((array) (\Core\Sites::all()[$k]['landing_hosts'] ?? []));
    $others = array_values(array_filter(array_merge($hosts, $landingHosts), fn($h) => $h !== $primary));
    $ico = \Core\Network\SiteIcon::for($k, $s);
    $ni = (int) ($s['inbox_new'] ?? 0); $rp = (int) ($s['review_pending'] ?? 0); $so = (int) ($s['support_open'] ?? 0);
    $bk = !empty($s['backup']) ? (int) $s['backup']['at'] : null; $bkOld = $bk === null || $bk < time() - 2 * 86400;
    $search = mb_strtolower(implode(' ', [$k, $name, implode(' ', $hosts), implode(' ', $landingHosts), $s['theme'] ?? '', $s['theme_label'] ?? '', $s['preset'] ?? ''])); ?>
    <article class="net-card net-card--<?= e($pill) ?>" id="site-<?= e($k) ?>" data-net-card
      data-q="<?= e($search) ?>" data-warn="<?= $nHints ? '1' : '0' ?>" data-maintenance="<?= $maint ? '1' : '0' ?>" data-staging="<?= $offline ? '1' : '0' ?>" aria-labelledby="net-h-<?= e($k) ?>">
      <header class="net-card__head">
        <img class="net-ico<?= $ico['fallback'] ? ' net-ico--letters' : '' ?>" src="<?= e($ico['src']) ?>" width="40" height="40" alt="" decoding="async">
        <div class="net-card__title">
          <h3 id="net-h-<?= e($k) ?>"><?= e($name) ?><?php if (!empty($s['network'])): ?> <span class="net-tag net-tag--net"><?= e(__('Netzwerk')) ?></span><?php endif; ?></h3>
          <?php if ($primary !== ''): ?>
          <a class="net-card__host" href="<?= e($pub . '/') ?>" target="_blank" rel="noopener noreferrer"><?= e($primary) ?><span class="adm-sr"> <?= e(__('(öffnet in neuem Tab)')) ?></span></a>
          <?php else: ?><span class="net-card__host adm-muted"><?= e(__('alle übrigen Domains')) ?></span><?php endif; ?>
        </div>
        <?php if ($nHints): // Hinweise: Klick auf den Status klappt sie in der Karte auf ?>
        <button type="button" class="net-pill net-pill--<?= e($pill) ?> net-pill--btn" aria-expanded="false" aria-controls="net-hints-<?= e($k) ?>" data-net-hints><?= icon('warning') ?><?= e($pillText) ?><?= icon('caret-down', ['class' => 'net-pill__chev']) ?></button>
        <?php else: ?>
        <span class="net-pill net-pill--<?= e($pill) ?>"><?= icon($pill === 'ok' ? 'check-circle' : ($pill === 'off' ? 'eye' : 'warning')) ?><?= e($pillText) ?></span>
        <?php endif; ?>
      </header>
      <?php if ($nHints): ?>
      <ul class="net-hints" id="net-hints-<?= e($k) ?>" hidden><?php foreach ($attention[$k]['msgs'] as $msg): ?><li><?= icon('warning') ?><span><?= e($msg) ?></span></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if (!empty($onlineBySite[$k])): $on = $onlineBySite[$k]; ?>
      <p class="net-card__online"><span class="net-online__dot" aria-hidden="true"></span><span><?= e(count($on) === 1 ? __('1 Person online') : __('{n} Personen online', ['n' => count($on)])) ?>:
        <?= e(implode(', ', array_map(fn($o) => (string) $o['name'] . ((string) ($o['label'] ?? '') !== '' ? ' (' . $o['label'] . ')' : ''), array_slice($on, 0, 4)))) ?><?= count($on) > 4 ? ' …' : '' ?></span></p>
      <?php endif; ?>
      <?php // Präsenz: wer arbeitet gerade auf dieser Website (Core\Presence) – Klick meldet per Einmal-Anmeldung an und öffnet den Ort
      $act = \Core\Http\Controllers\Admin\PresenceController::on() ? \Core\Presence::list(['site' => $k]) : []; if ($act): ?>
      <ul class="net-presence" aria-label="<?= e(__('Gerade aktiv')) ?>">
        <?php foreach (array_slice($act, 0, 3) as $pe): $mins = max(0, (int) round((time() - (int) $pe['since']) / 60)); ?>
        <li><form method="post" action="<?= e(url('/admin/network/open')) ?>"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e($k) ?>"><input type="hidden" name="path" value="<?= e((string) ($pe['url'] ?? '') ?: '/admin') ?>">
          <button type="submit" class="net-presence__b"><span class="net-presence__ava" aria-hidden="true"><?= e((string) ($pe['initials'] ?? '?')) ?></span><span><b><?= e((string) ($pe['name'] ?? '')) ?></b> · <?= e((string) ($pe['label'] ?? '') ?: $pe['type'] . ' ' . $pe['id']) ?> <small><?= e($mins < 1 ? __('gerade eben') : __('seit {n} Min.', ['n' => $mins])) ?></small></span></button></form></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <p class="net-card__meta">
        <span><?= (int) ($s['pages'] ?? 0) === 1 ? e(__('1 Seite')) : e(__('{n} Seiten', ['n' => (int) ($s['pages'] ?? 0)])) ?></span>
        <span><?= (int) ($s['editors'] ?? 0) === 1 ? e(__('1 Konto')) : e(__('{n} Konten', ['n' => (int) ($s['editors'] ?? 0)])) ?></span>
        <span><?= e(__('geändert')) ?> <?= $ago($s['last_change'] ?? null) ?></span>
      </p>
      <?php if ($ni || $rp || $so): // Aktivität nur, wenn etwas offen ist ?>
      <p class="net-card__hot">
        <?php if ($ni): ?><span class="net-chip"><?= icon('tray') ?><?= e($ni === 1 ? __('1 neue Anfrage') : __('{n} neue Anfragen', ['n' => $ni])) ?></span><?php endif; ?>
        <?php if ($rp): ?><span class="net-chip"><?= icon('clipboard-text') ?><?= e(__('{n} zur Freigabe', ['n' => $rp])) ?></span><?php endif; ?>
        <?php if ($so): ?><span class="net-chip"><?= icon('lifebuoy') ?><?= e(__('Support: {n} offen', ['n' => $so])) ?></span><?php endif; ?>
      </p>
      <?php endif; ?>

      <details class="net-card__more">
        <summary class="adm-link"><?= e(__('Details')) ?><span class="adm-sr"> – <?= e($name) ?></span></summary>
        <dl class="net-ops">
          <dt><?= e(__('Kurzname')) ?></dt><dd><code class="net-key" title="<?= e(__('Technischer Kurzname (Konfiguration, Kommandozeile --site=…) – nicht änderbar')) ?>"><?= e($k) ?></code></dd>
          <dt><?= e(__('Umgebung')) ?></dt><dd><?= e($envLabel[$env] ?? $env) ?><?= $noindex ? ' · noindex' : '' ?><?= $maint ? ' · ' . e(__('Wartungsmodus')) : '' ?></dd>
          <dt><?= e(__('Kit')) ?></dt><dd><?= e($s['theme_label'] ?? ($s['theme'] ?? '–')) ?></dd>
          <dt><?= e(__('Funktionen')) ?></dt><dd title="<?= e(implode(', ', $s['features_off'] ?? [])) ?>"><?= e(ucfirst((string) ($s['preset'] ?? 'full'))) ?><?= !empty($s['features_off']) ? ' · ' . e(__('{n} aus', ['n' => count($s['features_off'])])) : '' ?><?= !empty($s['extensions']) ? ' · ' . e(implode(', ', $s['extensions'])) : '' ?><?= !empty($s['features_delegated']) ? ' · ' . e(__('Website schaltet selbst')) : '' ?></dd>
          <dt><?= e(__('Speicher')) ?></dt><dd><?= e($size(isset($s['db_size']) ? (int) $s['db_size'] : null)) ?> <?= e(__('DB')) ?> · <?= e($size((int) ($s['media_size'] ?? 0))) ?> <?= e(__('eigene Medien')) ?> · <a href="<?= e(url('/admin/network/speicher') . '#su-' . $k) ?>"><?= e(__('aufschlüsseln')) ?></a>
            <?php foreach ((array) ($s['pools'] ?? []) as $pk): $p = $pools[$pk] ?? null; if (!$p) continue; $pn = count($p['sites']); ?>
            <span class="net-pool"><?= icon('images') ?><span><?= e(__('Pool „{pool}“', ['pool' => $p['label']])) ?> <?= e($size((int) ($p['size'] ?? 0))) ?> · <?= e($pn > 1 ? __('geteilt mit {n} Websites', ['n' => $pn]) : __('nur diese Website')) ?></span></span>
            <?php endforeach; ?></dd>
          <dt><?= e(__('Sicherung')) ?></dt><dd<?= $bkOld && $env === 'production' ? ' class="is-warn"' : '' ?>><?= $bk ? $ago($bk) : e(__('keine')) ?></dd>
        </dl>
        <form method="post" action="<?= e($actionUrl($k, 'label')) ?>" class="net-rename__form"><?= csrf_field() ?>
          <label for="net-label-<?= e($k) ?>"><?= e(__('Name in der Übersicht')) ?></label>
          <span class="net-inline"><input id="net-label-<?= e($k) ?>" name="label" maxlength="60" value="<?= e((string) ($s['label_own'] ?? '')) ?>" placeholder="<?= e($name) ?>" autocomplete="off">
          <button type="submit" class="adm-btn adm-btn--small"><?= e(__('Speichern')) ?></button></span>
          <p class="adm-muted"><?= e(__('Leer lassen: Name der Website aus den Einstellungen bzw. die Domain.')) ?></p>
        </form>
        <h4 class="net-h4"><?= e(__('Domains')) ?><?= $others ? ' <span class="adm-muted">(' . (count($others) + 1) . ')</span>' : '' ?></h4>
        <ul class="net-domains__list">
        <?php foreach (array_merge($hosts, $landingHosts) as $h): ?>
          <?php // Netzwerk-Website: dort meldet sich die Netzwerk-Administration direkt an – Passkey gilt je Domain (andere Websites: „Öffnen“ ohne Passkey)
          if (!empty($s['network']) && in_array($h, $hosts, true) && \Core\HostHandover::rp($h) !== \Core\HostHandover::rp((string) app()->request?->host()) && \Core\HostHandover::missing($me, $h, $pkHosts)): ?>
          <li class="net-pk"><form method="post" action="<?= e(url('/admin/network/open')) ?>"><?= csrf_field() ?><input type="hidden" name="site" value="<?= e($k) ?>"><input type="hidden" name="host" value="<?= e($h) ?>"><input type="hidden" name="path" value="/admin/account">
            <span><?= icon('warning') ?> <?= e(__('Ihr Passkey gilt auf {host} noch nicht.', ['host' => $h])) ?></span>
            <button type="submit" class="adm-btn adm-btn--small"><?= e(__('Dort anmelden und Passkey anlegen')) ?></button></form></li>
          <?php endif; ?>
          <li><form method="post" action="<?= e($actionUrl($k, 'hosts')) ?>" data-confirm="<?= e(__('Domain {host} von {site} entfernen?', ['host' => $h, 'site' => $name])) ?>"><?= csrf_field() ?><input type="hidden" name="op" value="remove"><input type="hidden" name="host" value="<?= e($h) ?>"><code><?= e($h) ?></code><?= $h === $primary ? ' <span class="net-tag net-tag--muted">' . e(__('Hauptadresse')) . '</span>' : '' ?><?= in_array($h, $landingHosts, true) ? ' <span class="net-tag net-tag--muted">' . e(__('Landing')) . '</span>' : '' ?> <button type="submit" class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Entfernen')) ?><span class="adm-sr"> <?= e($h) ?></span></button></form></li>
        <?php endforeach; ?>
        </ul>
        <form method="post" action="<?= e($actionUrl($k, 'hosts')) ?>" class="net-domains__add"><?= csrf_field() ?><input type="hidden" name="op" value="add">
          <label for="net-host-<?= e($k) ?>"><?= e(__('Weitere Domain (z. B. für eine Landingpage)')) ?></label>
          <span class="net-inline"><input id="net-host-<?= e($k) ?>" name="host" required placeholder="kampagne.beispiel.de" autocomplete="off" spellcheck="false">
          <button type="submit" class="adm-btn adm-btn--small"><?= e(__('Hinzufügen')) ?></button></span>
          <label class="f-check"><input type="checkbox" name="landing" value="1" checked> <span><?= e(__('Als Landing-Domain (Hauptadresse der Website bleibt)')) ?></span></label></form>
        <p class="adm-muted"><?= e(__('Ändert config/sites/{site}.php (Sicherung .bak). DNS und Hosting (Plesk: Alias bzw. zusätzliche Domain) richtet die Agentur ein.', ['site' => $k])) ?></p>
      </details>

      <div class="net-actions">
        <?= $openForm($k, e(__('Öffnen')) . '<span class="adm-sr"> – ' . e($name) . '</span>', '', 'adm-btn adm-btn--primary adm-btn--small', empty($s['initialized'])) ?>
        <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($pub . '/') ?>" target="_blank" rel="noopener noreferrer"><?= e(__('Ansehen')) ?> <?= icon('arrow-square-out') ?><span class="adm-sr"> – <?= e($name) ?> <?= e(__('(öffnet in neuem Tab)')) ?></span></a>
        <details class="net-more">
          <summary class="adm-btn adm-btn--small adm-btn--ghost" aria-label="<?= e(__('Weitere Aktionen für {site}', ['site' => $name])) ?>" title="<?= e(__('Weitere Aktionen')) ?>"><?= icon('dots-three') ?></summary>
          <div class="net-more__menu">
            <form method="post" action="<?= e($actionUrl($k, 'maintenance')) ?>"<?= !$maint ? ' data-confirm="' . e(__('Wartungsmodus für {site} einschalten? Besucher sehen dann nur den Wartungshinweis.', ['site' => $name])) . '"' : '' ?>><?= csrf_field() ?><input type="hidden" name="on" value="<?= !$maint ? '1' : '0' ?>"><button type="submit"<?= empty($s['initialized']) ? ' disabled' : '' ?>><?= e(!$maint ? __('Wartungsmodus an') : __('Wartungsmodus aus')) ?></button></form>
            <form method="post" action="<?= e($actionUrl($k, 'environment')) ?>" data-confirm="<?= e($env === 'production' ? __('{site} zur Testumgebung (staging) machen? Suchmaschinen werden ausgesperrt, E-Mails gehen nicht mehr an echte Empfänger.', ['site' => $name]) : __('Testumgebung für {site} aufheben und in den Livebetrieb (production) schalten? Suchmaschinen dürfen die Website dann aufnehmen, E-Mails gehen an echte Empfänger.', ['site' => $name])) ?>"><?= csrf_field() ?><input type="hidden" name="environment" value="<?= $env === 'production' ? 'staging' : 'production' ?>"><button type="submit"><?= e($env === 'production' ? __('Als Testumgebung (staging)') : __('Testumgebung aufheben (live)')) ?></button></form>
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
