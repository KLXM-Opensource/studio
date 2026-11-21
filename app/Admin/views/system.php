<?php
/** Grundeinstellungen. */
use Core\Fields;
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?> › <?= e(__('Einstellungen')) ?></p><h1>Grundeinstellungen</h1>
    <p class="adm-muted">Technische Einstellungen: Website, E-Mail-Versand (Symfony Mailer), Spamschutz und Verschlüsselung.</p></div>
</header>

<?php if ($newSecret): ?>
<section class="adm-card adm-card--secret" role="alert">
  <h2>Geheimer <?= e(term('key')) ?> – nur jetzt sichtbar</h2>
  <p>Kopieren Sie diesen Schlüssel sofort in einen Passwortmanager der <?= e(term('org')) ?>. Er wird <strong>nicht</strong> gespeichert. Ohne ihn können Online-Anfragen <strong>nicht</strong> gelesen werden.</p>
  <div class="adm-secret"><code id="secret-key"><?= e($newSecret) ?></code><button type="button" class="adm-btn adm-btn--small" data-copy="#secret-key">Kopieren</button></div>
</section>
<?php endif; ?>

<?php /* Viele Reiter: auf breiten Bildschirmen senkrechte Navigation links (adm-tabs-form--side), schmal eine waagerecht scrollbare Zeile */ ?>
<form method="post" action="<?= e(url('/admin/system')) ?>" class="adm-tabs-form adm-tabs-form--side" data-tabs novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="_tab" value="">
  <?php
  // Symbole der Bereiche in der Seitenleiste (Phosphor über icon(); unbekannte Bereiche, z. B. aus Erweiterungen: Zahnrad)
  $tabIco = ['website' => 'gear-six', 'index' => 'tree-structure', 'app' => 'device-mobile', 'proxy' => 'map-trifold', 'sprachen' => 'translate',
      'mail' => 'envelope-simple', 'spam' => 'shield-check', 'suche' => 'magnifying-glass', 'ki' => 'sparkle', 'keys' => 'lock-key',
      'pools' => 'images', 'shared' => 'share-network', 'medien' => 'images', 'umgebung' => 'hard-drives', 'adminpath' => 'link', 'info' => 'info', 'push' => 'bell-ringing'];
  // Testumgebung (staging/development): „Umgebung“ orange mit sanft pulsierendem Punkt, Hinweis auch für Screenreader und im Auswahlfeld (schmal)
  // Fehlender Schlüssel für verschlüsselte Anfragen: Punkt an „Verschlüsselung“ (derselbe Grund wie der Punkt an „Grundeinstellungen“ im Menü)
  $tabWarn = array_filter(['umgebung' => environment() !== 'production' ? __('Testumgebung aktiv') : '',
      'keys' => !\Core\FormCrypto::ready() && \Core\Data\Inbox::anyNeedsKey() ? __('Kein Schlüssel für verschlüsselte Anfragen') : '']);
  $tab = fn(string $id, string $label, bool $on = false) => '<button type="button" role="tab" id="tab-' . e($id) . '" aria-controls="panel-' . e($id) . '" data-tab="' . e($id) . '" aria-selected="' . ($on ? 'true' : 'false') . '"' . ($on ? '' : ' tabindex="-1"')
      . (isset($tabWarn[$id]) ? ' class="is-warn" title="' . e($tabWarn[$id]) . '" data-tab-note="' . e($tabWarn[$id]) . '"' : '') . '>'
      . '<span class="adm-tabs__ico" aria-hidden="true">' . icon($tabIco[$id] ?? 'gear-six') . '</span><span class="adm-tabs__label">' . e($label) . '</span>'
      . (isset($tabWarn[$id]) ? '<span class="adm-tabs__dot" aria-hidden="true"></span><span class="adm-sr"> – ' . e($tabWarn[$id]) . '</span>' : '') . '</button>';
  ?>
  <div class="adm-tabs" role="tablist" aria-label="Bereiche">
    <?php foreach ($groups as $i => $g): ?><?= $tab($g['id'], $g['label'], $i === 0) ?><?php endforeach; ?>
    <?= $tab('keys', 'Verschlüsselung') ?>
    <?php // Netzwerk (Core\Network\Area): Geteilte Medien, Adresse der Verwaltung, KI-Verbindungen und die installationsweiten Unterseiten
    // stehen nur im Netzwerk-Bereich – hier nur, was diese Website betrifft
    $netMoved = \Core\Network\Area::active(); ?>
    <?php if (!$netMoved): ?><?= $tab('pools', __('Geteilte Medien')) ?><?php endif; ?>
    <?php $sharedTab = \Core\Data\Shared::canManage() || \Core\Data\Shared::forSite(); if ($sharedTab): ?><?= $tab('shared', __('Geteilte Daten')) ?><?php endif; ?>
    <?= $tab('push', __('Push-Benachrichtigungen')) ?>
    <?= $tab('umgebung', __('Umgebung')) ?>
    <?php if (!$netMoved): ?><?= $tab('adminpath', __('Adresse der Verwaltung')) ?><?php endif; ?>
    <?= $tab('info', 'Systeminfo') ?>
    <?php // Unterseiten mit eigener Adresse (Domain, Branding, Schriften, Geplante Aufgaben, Integrität, Sicherung außer Haus, Kits): Links unten in derselben Leiste (dort: system/_sidenav.php)
    $subLinks = $netMoved ? [] : array_filter(['domain' => \Core\Domains::available() ? [url('/admin/system/domain'), __('Domain'), 'globe'] : null,
        'branding' => \Core\Branding::canEdit() ? [url('/admin/system/branding'), __('Branding & Support'), 'paint-brush'] : null,
        'fonts' => \Core\Fonts::canManage() ? [url('/admin/system/fonts'), __('Schriften'), 'text-t'] : null,
        'aufgaben' => \Core\Cron::canManage() ? [url('/admin/system/aufgaben'), __('Geplante Aufgaben'), 'clock-countdown'] : null,
        'integritaet' => can('system.manage') ? [url('/admin/system/integritaet'), __('Integrität'), 'shield-check'] : null,
        'sicherung' => can('system.manage') && \Core\Permissions::installationAdmin() ? [url('/admin/system/sicherung'), __('Sicherung außer Haus'), 'cloud-arrow-up'] : null,
        'kits' => \Core\KitPackages::canManage() ? [url('/admin/system/kits'), __('Kits'), 'package'] : null]);
    if ($subLinks): ?><span class="adm-tabs__sep" aria-hidden="true"></span><?php endif;
    foreach ($subLinks as [$href, $label, $i]): ?><a class="adm-tabs__link" href="<?= e($href) ?>" role="tab" aria-selected="false" data-tab-link><span class="adm-tabs__ico" aria-hidden="true"><?= icon($i) ?></span><span class="adm-tabs__label"><?= e($label) ?></span></a><?php endforeach; ?>
  </div>
  <?php foreach ($groups as $i => $g): ?>
  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-<?= e($g['id']) ?>" aria-labelledby="tab-<?= e($g['id']) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
    <h2><?= e($g['label']) ?></h2>
    <?php if ($g['id'] === 'ki' && !$netMoved): /* KI: Übersicht, Verbindungen und Verwendung der Installation vor den Schaltern der Website (Netzwerk: im Netzwerk-Bereich) */ ?>
      <?= \Core\Theme::capture(ROOT . '/app/Admin/views/system/_ai.php', ['part' => 'top']) ?>
    <?php endif; ?>
    <?php $gFields = $g['fields'];
    if ($g['id'] === 'mail'): // E-Mail-Versand: Konten-Liste (bzw. erstes Konto) und Verwendung vor den übrigen Feldern (system/_mail.php)
      // Impressum für E-Mails (Core\Mail\Imprint): weder Text noch Impressum-Seite → deutlicher Hinweis oben im Reiter
      if (\Core\Mail\Imprint::missing()): ?><p class="adm-flash adm-flash--error" id="mail-imprint-missing" role="status"><?= e(__('E-Mails dieser Website enthalten derzeit kein Impressum: Bitte unten „Impressum in jeder E-Mail“ ausfüllen oder unter Einstellungen → Recht eine Impressum-Seite festlegen. Bis dahin ist der Newsletter-Versand gesperrt.')) ?> <a href="#f-f-sys-mail-imprint"><?= e(__('Zum Feld')) ?></a></p><?php endif;
      $std = array_values(\Core\Mail\Accounts::STANDARD_KEYS);
      $stdFields = array_values(array_filter($gFields, fn($f) => in_array($f['name'] ?? '', $std, true) || (($f['type'] ?? '') === 'heading' && ($f['label'] ?? '') === 'Konto „Standard“')));
      $gFields = array_values(array_filter($gFields, fn($f) => !in_array($f, $stdFields, true)));
      // Gruppen 1–5 mit allen übrigen Feldern (Empfänger, Test und Umleitung, Impressum) rendert system/_mail.php selbst
      echo \Core\Theme::capture(ROOT . '/app/Admin/views/system/_mail.php', ['stdFields' => $stdFields, 'values' => $values, 'errors' => $errors, 'fields' => $gFields, 'user' => $user]);
      $gFields = [];
    endif; ?>
    <div class="set-groups"><?= Fields::renderGroups($gFields, $values, $errors, 'f') ?></div>
    <?php if ($g['id'] === 'app'): ?>
      <div class="ai-preview" data-icon-preview="<?= e(url('/admin/system/icon-preview')) ?>" aria-live="polite">
        <strong class="ai-title">Vorschau</strong>
        <div class="ai-stage">
          <figure class="ai-tab"><div class="ai-tabbar"><span class="ai-tabitem"><img data-ai="favicon16" alt="" width="16" height="16"><span data-ai-name></span></span></div><figcaption>Browser-Tab</figcaption></figure>
          <figure class="ai-home ai-home--ios"><img data-ai="apple" alt="" width="60" height="60"><span data-ai-short></span><figcaption>iPhone</figcaption></figure>
          <figure class="ai-home ai-home--android"><img data-ai="maskable" alt="" width="60" height="60"><span data-ai-short></span><figcaption>Android</figcaption></figure>
          <figure class="ai-splash"><div class="ai-splash__screen" data-ai-splash><span class="ai-splash__bar" data-ai-bar></span><img data-ai="any" alt="" width="64" height="64"><span data-ai-name></span></div><figcaption>App-Start</figcaption></figure>
        </div>
        <p class="adm-muted ai-note">Nach dem Speichern werden alle Dateien erzeugt. Browser zeigen neue Favicons manchmal erst nach dem Neuladen.</p>
      </div>
    <?php endif; ?>
    <?php if ($g['id'] === 'proxy'): $stats = \Core\Proxy::stats(); ?>
      <div class="adm-inline-box" id="proxy">
        <strong>Registrierte externe Quellen</strong>
        <p class="adm-muted">Nur diese Anbieter sind über den Proxy erreichbar. Weitere Quellen registriert ein Kit in <code>theme.php → 'proxy'</code> oder Code über <code>Proxy::register()</code>.</p>
        <table class="adm-table px-table">
          <thead><tr><th>Quelle</th><th>Anbieter</th><th>Abruf</th><th class="num">Dateien</th><th class="num">Größe</th></tr></thead>
          <tbody>
          <?php foreach (\Core\Proxy::sources() as $k => $src): $st = $stats[$k] ?? ['files' => 0, 'bytes' => 0]; ?>
            <tr><td><strong><?= e($src['label']) ?></strong><br><code><?= e($src['public'] ? '/proxy/' . $k . '/…' : $k) ?></code></td>
              <td><code><?= e((string) parse_url($src['upstream'], PHP_URL_HOST)) ?></code></td>
              <td><?= $src['public'] ? 'Besucher (über Proxy)' : 'nur Server' ?></td>
              <td class="num"><?= (int) $st['files'] ?></td><td class="num"><?= $st['bytes'] ? e(\Core\Media::humanSize((int) $st['bytes'])) : '–' ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <button class="adm-btn" form="proxy-clear" type="submit">Zwischenspeicher leeren</button>
      </div>
    <?php endif; ?>
    <?php if ($g['id'] === 'suche' || $g['id'] === 'ki'): /* Website-Suche bzw. KI: Status, Tests, Anbieter (Core\Search, Core\AI) */ ?>
      <?= \Core\Theme::capture(ROOT . '/app/Admin/views/system/' . ($g['id'] === 'ki' ? '_ai' : '_search') . '.php', ['part' => 'panel']) ?>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-keys" aria-labelledby="tab-keys" hidden>
    <h2>Verschlüsselung der Online-Anfragen</h2>
    <p class="set-page__lead"><?= e(term('requests')) ?> enthalten <?= e(term('requests_data')) ?>. Sie werden mit <strong>libsodium (Sealed Box, X25519)</strong> verschlüsselt gespeichert. Auf dem Server liegt nur der <em>öffentliche</em> Schlüssel – selbst bei einem Server-Einbruch sind die Inhalte nicht lesbar.</p>
    <?php $__env = \Core\FormCrypto::envStatus(); ?>
    <section class="set-group">
      <h3 class="set-group__title">Schlüssel</h3>
      <div class="set-list">
        <div class="set-row"><div class="set-row__main"><span class="set-row__label">Status</span></div><div class="set-row__ctl"><?= $keyReady ? '<span class="adm-badge">Aktiv</span>' : '<span class="adm-badge adm-badge--warn">Kein Schlüssel – Formulare sind deaktiviert</span>' ?></div></div>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label">Fingerabdruck</span></div><div class="set-row__ctl"><code><?= e($fingerprint) ?></code></div></div>
        <?php if ($ts = setting('sys.form_key_created')): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label">Erzeugt</span></div><div class="set-row__ctl"><?= e(\Core\Format::admin()->datetime(strtotime((string) $ts))) ?></div></div><?php endif; ?>
        <?php if ($keyReady && $__env['state'] !== 'off'): ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label">Automatisch entschlüsseln</span></div><div class="set-row__ctl"><?= match ($__env['state']) {
            'active' => '<span class="adm-badge">Aktiv</span> <span>Schlüssel aus <code>' . e($__env['name']) . '</code></span>',
            'mismatch' => '<span class="adm-badge adm-badge--warn">Passt nicht</span> <span><code>' . e($__env['name']) . '</code> ist gesetzt, gehört aber nicht zu diesem Schlüssel</span>',
            default => '<span>Aus – optional über die Umgebungsvariable <code>' . e($__env['name']) . '</code></span>',
        } ?></div></div>
        <?php endif; ?>
      </div>
    </section>
    <?php if ($keyReady && $__env['state'] !== 'off'): ?>
    <details class="set-group set-group--collapse" id="key-env">
      <summary class="set-group__title">Geheimen Schlüssel im Hosting hinterlegen <span class="adm-muted">· automatisch entschlüsseln (optional)</span></summary>
      <div class="set-list"><div class="set-row set-row--stack"><div class="set-row__body">
      <p>Für kleine Websites: Liegt der geheime Schlüssel als Umgebungsvariable beim Hosting, sind Anfragen für alle mit Leserecht sofort lesbar – ohne Eingabe. Wer nur die <b>Datenbank</b> erbeutet (Sicherung, SQL-Lücke), liest weiterhin nichts; wer den <b>Server selbst</b> übernimmt, kann mitlesen. Ohne Variable bleibt alles wie bisher.</p>
      <ol>
        <li>Plesk: <i>Websites &amp; Domains → PHP-Einstellungen</i> → „Zusätzliche Konfigurationsanweisungen“ (PHP-FPM) bzw. im FPM-Pool:<br><code>env[<?= e(\Core\FormCrypto::envNames()[0] ?? 'KLXM_FORM_SECRET') ?>] = "…geheimer Schlüssel…"</code></li>
        <li>Andere Server: Apache <code>SetEnv <?= e(\Core\FormCrypto::envNames()[0] ?? 'KLXM_FORM_SECRET') ?> "…"</code> im vHost (nicht in <code>.htaccess</code> im Webverzeichnis) oder die Umgebung des PHP-Dienstes.</li>
        <li>Seite neu laden: hier erscheint „Aktiv“. Den Schlüssel trotzdem zusätzlich im Passwortmanager aufbewahren.</li>
      </ol>
      </div></div></div>
      <p class="set-group__note">Der Schlüssel wird nie gespeichert oder protokolliert. Abschalten für die ganze Website: <code>'form_secret_env' => false</code> in der Konfiguration.</p>
    </details>
    <?php endif; ?>
    <section class="set-group">
      <h3 class="set-group__title"><?= $keyReady ? 'Schlüssel ersetzen' : e(term('key')) . ' erzeugen' ?></h3>
      <div class="set-list">
      <?php if ($keyReady): ?>
        <div class="set-row"><div class="set-row__main"><label class="set-row__label" for="keys-confirm">Zur Bestätigung „NEU“ eintippen</label>
          <span class="set-row__sub">Nur nötig, wenn der geheime Schlüssel verloren ging oder kompromittiert wurde. Bereits gespeicherte Anfragen bleiben nur mit dem alten Schlüssel lesbar.</span></div>
          <div class="set-row__ctl"><input id="keys-confirm" form="keys" name="confirm" placeholder="NEU" autocomplete="off" size="8"><button class="adm-btn adm-btn--danger" form="keys" type="submit">Neuen Schlüssel erzeugen</button></div></div>
      <?php else: ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label">Schlüsselpaar erzeugen</span><span class="set-row__sub">Der geheime Schlüssel wird genau einmal angezeigt.</span></div>
          <div class="set-row__ctl"><button class="adm-btn adm-btn--primary" form="keys" type="submit">Schlüsselpaar erzeugen</button></div></div>
      <?php endif; ?>
      </div>
    </section>
  </section>

  <?php if (!$netMoved) { $poolsPanel = true; include __DIR__ . '/system/_pools.php'; } ?>

  <?php if ($sharedTab): ?><?= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_shared_system.php', ['part' => 'panel', 'user' => $user]) ?><?php endif; ?>

  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-push" aria-labelledby="tab-push" data-nosave hidden>
<?php include __DIR__ . '/system/_push.php'; // Push-Benachrichtigungen (Core\Push): Status, Abos, Versand, Test ?>
  </section>

  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-umgebung" aria-labelledby="tab-umgebung" data-nosave hidden>
<?php $env = environment(); include __DIR__ . '/system/_environment.php'; ?>
  </section>

  <?php if (!$netMoved): ?>
  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-adminpath" aria-labelledby="tab-adminpath" data-nosave hidden>
<?php include __DIR__ . '/system/_adminpath.php'; ?>
    <?php $nhForm = 'networkhost'; $nhBack = 'system'; include __DIR__ . '/system/_network_host.php'; // Netzwerk- bzw. Verwaltungs-Domain (Core\NetworkHost) ?>
  </section>
  <?php endif; ?>

  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-info" aria-labelledby="tab-info" data-nosave hidden>
    <h2>Systeminfo</h2>
    <?php if (!app()->theme->compatible()): ?><p class="adm-flash adm-flash--error">Das Kit „<?= e(app()->theme->name) ?>“ verlangt Core-Version <?= e((string) app()->theme->def['requires']) ?> – installiert ist <?= e(CMS_VERSION) ?>.</p><?php endif; ?>
    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Installation')) ?></h3>
      <div class="set-list">
        <?php foreach ($info as $k => $v): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($k) ?></span></div><div class="set-row__ctl"><?= e($v) ?></div></div><?php endforeach; ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label">Seiten-Cache</span><span class="set-row__sub">Fertige Seiten werden neu erzeugt, sobald sie das nächste Mal aufgerufen werden.</span></div><div class="set-row__ctl"><button class="adm-btn" form="cache" type="submit">Seiten-Cache leeren</button></div></div>
      </div>
    </section>
    <section class="set-group">
      <h3 class="set-group__title">Funktionsumfang dieser Website</h3>
      <div class="set-list"><div class="set-row set-row--stack">
        <ul class="ft-list">
          <?php foreach (\Core\Features::overview() as $fk => [$fl, $fon]): ?><li class="<?= $fon ? 'is-on' : 'is-off' ?>"><span aria-hidden="true"><?= $fon ? '✓' : '–' ?></span> <?= e($fl) ?> <code><?= e($fk) ?></code><span class="adm-sr"><?= $fon ? ' (an)' : ' (aus)' ?></span></li><?php endforeach; ?>
        </ul>
      </div></div>
      <p class="set-group__note">Schalter: <?= \Core\Features::canView() ? '<a href="' . e(url('/admin/funktionen')) . '">Administration → Funktionen &amp; Erweiterungen</a>' : 'Administration → Funktionen &amp; Erweiterungen (Haupt-Admin bzw. Agentur)' ?>. Werte aus <code><?= e(site()->isDefault() && !isset(\Core\Sites::all()['default']['features']) ? 'config/config.local.php' : 'config/sites/' . site()->key . '.php') ?></code> (<code>preset</code>, <code>features</code>, <code>blocks</code>, <code>extensions</code>) gehen vor und sind dort gesperrt.<?= \Core\Features::integrator() ? ' Sie sind als Integrator eingetragen und sehen alles.' : '' ?></p>
    </section>
    <?php $exts = \Core\Extensions::available(); if ($exts): ?>
    <section class="set-group">
      <h3 class="set-group__title">Erweiterungen</h3>
      <div class="set-list">
      <?php foreach ($exts as $en => $em): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($em['label']) ?></span><span class="set-row__sub"><code><?= e($en) ?></code> · <?= e($em['version'] ?: '–') ?> · <?= e($em['source']) ?></span></div>
        <div class="set-row__ctl"><?= \Core\Extensions::isActive($en) ? '<span class="adm-badge">aktiv</span>' : '<span class="adm-badge adm-badge--muted">inaktiv</span>' ?></div></div><?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </section>

  <div class="adm-savebar"><button class="adm-btn adm-btn--primary" type="submit">Speichern</button></div>
</form>
<form id="testmail" method="post" action="<?= e(url('/admin/system/testmail')) ?>"><?= csrf_field() ?></form>
<form id="keys" method="post" action="<?= e(url('/admin/system/keys')) ?>"><?= csrf_field() ?></form>
<form id="proxy-clear" method="post" action="<?= e(url('/admin/system/proxy-clear')) ?>"><?= csrf_field() ?></form>
<form id="environment" method="post" action="<?= e(url('/admin/system/environment')) ?>"><?= csrf_field() ?></form>
<?php if (\Core\AdminPath::canManage()): ?><form id="adminpath" method="post" action="<?= e(url('/admin/system/admin-path')) ?>"><?= csrf_field() ?></form><?php endif; ?>
<?php if (\Core\NetworkHost::canManage()): ?><form id="networkhost" method="post" action="<?= e(url('/admin/system/network-host')) ?>"><?= csrf_field() ?></form><?php endif; ?>
<?php if (\Core\MediaPools::canManage()): ?>
<form id="pool-new" method="post" action="<?= e(url('/admin/system/pools')) ?>"><?= csrf_field() ?></form>
<?php foreach (\Core\MediaPools::all() as $pk => $pl): ?>
<form id="pool-<?= e($pk) ?>" method="post" action="<?= e(url('/admin/system/pools/' . $pk)) ?>"><?= csrf_field() ?></form>
<form id="pool-del-<?= e($pk) ?>" method="post" action="<?= e(url('/admin/system/pools/' . $pk . '/delete')) ?>"><?= csrf_field() ?></form>
<?php endforeach; endif; ?>
<form id="cache" method="post" action="<?= e(url('/admin/system/cache')) ?>"><?= csrf_field() ?></form>
<form id="push-keys" method="post" action="<?= e(url('/admin/system/push/keys')) ?>"><?= csrf_field() ?></form>
<form id="push-test" method="post" action="<?= e(url('/admin/account/push/test')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="system"></form>
<?= \Core\Theme::capture(ROOT . '/app/Admin/views/system/_search.php', ['part' => 'forms']) ?><?= \Core\Theme::capture(ROOT . '/app/Admin/views/system/_ai.php', ['part' => 'forms']) ?>
<?php if ($sharedTab): ?><?= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_shared_system.php', ['part' => 'forms']) ?><?php endif; ?>
