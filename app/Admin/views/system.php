<?php
/** Grundeinstellungen. */
use Core\Fields;
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Administration')) ?> › <?= e(__('Einstellungen')) ?></p><h1>Grundeinstellungen</h1>
    <p class="adm-muted">Technische Einstellungen: Website, E-Mail-Versand (Symfony Mailer), Spamschutz und Verschlüsselung.</p></div>
  <?php if (\Core\Fonts::canManage()): // Schriften aus Google Fonts selbst hosten (Core\Fonts) ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/system/fonts')) ?>"><?= icon('text-t') ?> <?= e(__('Schriften')) ?></a><?php endif; ?>
  <?php if (\Core\Domains::available()): // Domains, Hauptadresse, Umgebung – nur Einzel-Installation ohne Netzwerk (Core\Domains) ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/system/domain')) ?>"><?= icon('globe') ?> <?= e(__('Domain')) ?></a><?php endif; ?>
  <?php if (\Core\KitPackages::canManage()): // Kits der Installation: Übersicht, Paket hochladen (Core\KitPackages) ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/system/kits')) ?>"><?= icon('package') ?> <?= e(__('Kits')) ?></a><?php endif; ?>
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
      'pools' => 'images', 'shared' => 'share-network', 'umgebung' => 'hard-drives', 'adminpath' => 'link', 'info' => 'info'];
  // Testumgebung (staging/development): „Umgebung“ orange mit sanft pulsierendem Punkt, Hinweis auch für Screenreader und im Auswahlfeld (schmal)
  $envWarn = environment() !== 'production' ? __('Testumgebung aktiv') : '';
  $tab = fn(string $id, string $label, bool $on = false) => '<button type="button" role="tab" id="tab-' . e($id) . '" aria-controls="panel-' . e($id) . '" data-tab="' . e($id) . '" aria-selected="' . ($on ? 'true' : 'false') . '"' . ($on ? '' : ' tabindex="-1"')
      . ($id === 'umgebung' && $envWarn !== '' ? ' class="is-warn" title="' . e($envWarn) . '" data-tab-note="' . e($envWarn) . '"' : '') . '>'
      . '<span class="adm-tabs__ico" aria-hidden="true">' . icon($tabIco[$id] ?? 'gear-six') . '</span><span class="adm-tabs__label">' . e($label) . '</span>'
      . ($id === 'umgebung' && $envWarn !== '' ? '<span class="adm-tabs__dot" aria-hidden="true"></span><span class="adm-sr"> – ' . e($envWarn) . '</span>' : '') . '</button>';
  ?>
  <div class="adm-tabs" role="tablist" aria-label="Bereiche">
    <?php foreach ($groups as $i => $g): ?><?= $tab($g['id'], $g['label'], $i === 0) ?><?php endforeach; ?>
    <?= $tab('keys', 'Verschlüsselung') ?>
    <?= $tab('pools', __('Geteilte Medien')) ?>
    <?php $sharedTab = \Core\Data\Shared::canManage() || \Core\Data\Shared::forSite(); if ($sharedTab): ?><?= $tab('shared', __('Geteilte Daten')) ?><?php endif; ?>
    <?= $tab('umgebung', __('Umgebung')) ?>
    <?= $tab('adminpath', __('Adresse der Verwaltung')) ?>
    <?= $tab('info', 'Systeminfo') ?>
  </div>
  <?php foreach ($groups as $i => $g): ?>
  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-<?= e($g['id']) ?>" aria-labelledby="tab-<?= e($g['id']) ?>"<?= $i === 0 ? '' : ' hidden' ?>>
    <h2><?= e($g['label']) ?></h2>
    <?php if ($g['id'] === 'ki'): /* KI: Übersicht, Verbindungen und Verwendung der Installation vor den Schaltern der Website */ ?>
      <?= \Core\Theme::capture(ROOT . '/app/Admin/views/system/_ai.php', ['part' => 'top']) ?>
    <?php endif; ?>
    <div class="set-groups"><?= Fields::renderGroups($g['fields'], $values, $errors, 'f') ?></div>
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
    <?php if ($g['id'] === 'mail'): ?>
      <div class="adm-inline-box">
        <strong>Testnachricht senden</strong>
        <p class="adm-muted">Erst speichern, dann testen.</p>
        <div class="adm-row"><input type="email" form="testmail" name="to" placeholder="<?= e($user['email']) ?>" aria-label="Empfänger der Testnachricht"><button class="adm-btn" form="testmail" type="submit">Test senden</button></div>
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
        <?php if ($ts = setting('sys.form_key_created')): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label">Erzeugt</span></div><div class="set-row__ctl"><?= e(date('d.m.Y H:i', strtotime((string) $ts))) ?></div></div><?php endif; ?>
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

  <?php
  $pools = \Core\MediaPools::all();
  $manage = \Core\MediaPools::canManage();
  $network = \Core\Network\Network::siteKey();
  $allSites = \Core\Sites::all();
  // Websites, die einen Pool über ihre Konfigurationsdatei nutzen (in der Oberfläche nicht abwählbar)
  $viaConfig = function (string $pool) use ($allSites): array {
      $out = [];
      foreach ($allSites as $k => $c) {
          $list = $k === site()->key ? (array) app()->config->get('media_pools', []) : (array) ($c['media_pools'] ?? []);
          if (in_array($pool, $list, true)) $out[] = $k;
      }
      return $out;
  };
  ?>
  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-pools" aria-labelledby="tab-pools" hidden>
    <h2><?= e(__('Geteilte Medien')) ?></h2>
    <p class="adm-muted"><?= e(__('Zentrale Mediatheken für mehrere Websites dieser Installation, z. B. Logos und Markenbilder. In der Mediathek erscheint dafür eine Umschaltung. Pflegen dürfen Rollen mit „Geteilte Medien pflegen“ auf der Hauptwebsite und auf Websites, die unter „Pflegen dürfen“ freigegeben sind; alle anderen verwenden die Dateien nur. Änderungen wirken sofort auf allen Websites.')) ?></p>
    <?php if ($manage): ?>
      <?php
      // Pools geteilter Datentabellen („data-{key}“) verwaltet die Tabelle: eigene Darstellung, leere zusammengeklappt
      $autoPools = $emptyAuto = [];
      foreach ($pools as $pk => $pl) {
          if (($tk = \Core\Data\Shared::poolTable($pk)) === null) continue;
          $autoPools[$pk] = $tk;
          unset($pools[$pk]);
      }
      $siteLabel = fn(string $sk) => $sk === 'default' ? __('Hauptwebsite') : (isset($allSites[$sk]) ? (new \Core\Site($sk, $allSites[$sk]))->label() : $sk);
      ?>
      <?php foreach ($autoPools as $pk => $tk): $meta = \Core\MediaPools::meta($pk); $n = \Core\MediaPools::count($pk); $sm = \Core\Data\Shared::meta($tk);
        if ($n === 0) { $emptyAuto[$pk] = $tk; continue; } ?>
      <div class="pl-card pl-card--auto">
        <div class="pl-head">
          <strong><?= e(__('Bilder der geteilten Tabelle „{name}“', ['name' => $sm['label'] ?? $tk])) ?></strong>
          <code><?= e($pk) ?></code><?php if (!empty($meta['protected'])): ?> <span class="adm-badge"><?= icon('lock') ?> <?= e(__('Geschützt')) ?></span><?php endif; ?><span class="adm-muted"><?= e($n === 1 ? __('1 Datei') : __('{n} Dateien', ['n' => $n])) ?></span>
          <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/system?table=' . rawurlencode($tk) . '#shared')) ?>"><?= e(__('Zur Tabelle')) ?></a>
        </div>
        <p class="adm-muted"><?= e(__('Genutzt von: {sites} – automatisch alle beteiligten Websites der Tabelle.', ['sites' => implode(', ', array_map($siteLabel, (array) $meta['sites']))])) ?></p>
        <fieldset class="pl-sites"><legend><?= e(__('Pflegen dürfen')) ?> <small class="adm-muted"><?= e(__('Alt-Texte, Zuschnitte und Sammlungen in der Mediathek – Bilder der Einträge setzt jede beteiligte Website selbst')) ?></small></legend>
          <?php foreach ($allSites as $sk => $sc): $main = $sk === $network; ?>
          <label class="f-check"><input type="checkbox" form="pool-<?= e($pk) ?>" name="editors[]" value="<?= e($sk) ?>"<?= $main || in_array($sk, (array) $meta['editors'], true) ? ' checked' : '' ?><?= $main ? ' disabled' : '' ?>>
            <span><?= e($siteLabel($sk)) ?><?= $main ? ' <small class="adm-muted">' . e(__('immer')) . '</small>' : '' ?></span></label>
          <?php endforeach; ?>
        </fieldset>
        <div class="adm-row">
          <button class="adm-btn adm-btn--small" type="submit" form="pool-<?= e($pk) ?>"><?= e(__('Speichern')) ?></button>
          <span class="adm-muted pl-hint"><?= e(__('Wird mit der Tabelle verwaltet.')) ?></span>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if ($emptyAuto): ?>
      <details class="pl-card pl-card--auto">
        <summary><?= e(__('Leere Pools geteilter Tabellen ({n})', ['n' => count($emptyAuto)])) ?></summary>
        <p class="adm-muted"><?= e(__('Diese Pools gehören zu geteilten Datentabellen und enthalten noch keine Dateien. Sie werden mit der Tabelle verwaltet; Pools von Tabellen ohne Bild- oder Dateifelder entfernt „migrate“ automatisch.')) ?></p>
        <ul>
          <?php foreach ($emptyAuto as $pk => $tk): $sm = \Core\Data\Shared::meta($tk); ?>
          <li><?= e(__('Bilder der geteilten Tabelle „{name}“', ['name' => $sm['label'] ?? $tk])) ?> <code><?= e($pk) ?></code>
            <?= \Core\Data\Shared::hasMediaFields($tk) ? '' : '<small class="adm-muted">' . e(__('ohne Bild-/Dateifelder – wird beim nächsten „migrate“ entfernt')) . '</small>' ?></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endif; ?>
      <?php foreach ($pools as $pk => $pl): $meta = \Core\MediaPools::meta($pk); $cfgSites = $viaConfig($pk); $n = \Core\MediaPools::count($pk); ?>
      <div class="pl-card">
        <div class="pl-head">
          <label class="pl-name"><span class="adm-sr"><?= e(__('Name')) ?></span><input form="pool-<?= e($pk) ?>" name="label" value="<?= e($pl) ?>" maxlength="80"></label>
          <code><?= e($pk) ?></code><span class="adm-muted"><?= e($n === 1 ? __('1 Datei') : __('{n} Dateien', ['n' => $n])) ?></span>
        </div>
        <fieldset class="pl-sites"><legend><?= e(__('Genutzt von')) ?></legend>
          <?php foreach ($allSites as $sk => $sc): $st = new \Core\Site($sk, $sc); $fixed = in_array($sk, $cfgSites, true); ?>
          <label class="f-check"><input type="checkbox" form="pool-<?= e($pk) ?>" name="sites[]" value="<?= e($sk) ?>"<?= $fixed || in_array($sk, (array) $meta['sites'], true) ? ' checked' : '' ?><?= $fixed ? ' disabled' : '' ?>>
            <span><?= e($sk === 'default' ? __('Hauptwebsite') : $st->label()) ?><?= $sk === site()->key ? ' ' . e(__('(diese)')) : '' ?><?= $fixed ? ' <small class="adm-muted">' . e(__('per Konfiguration')) . '</small>' : '' ?></span></label>
          <?php endforeach; ?>
        </fieldset>
        <fieldset class="pl-sites"><legend><?= e(__('Pflegen dürfen')) ?> <small class="adm-muted"><?= e(__('Personen mit „Geteilte Medien pflegen“ auf diesen Websites – alle anderen verwenden die Dateien nur')) ?></small></legend>
          <?php foreach ($allSites as $sk => $sc): $st = new \Core\Site($sk, $sc); $main = $sk === $network; ?>
          <label class="f-check"><input type="checkbox" form="pool-<?= e($pk) ?>" name="editors[]" value="<?= e($sk) ?>"<?= $main || in_array($sk, (array) $meta['editors'], true) ? ' checked' : '' ?><?= $main ? ' disabled' : '' ?>>
            <span><?= e($sk === 'default' ? __('Hauptwebsite') : $st->label()) ?><?= $main ? ' <small class="adm-muted">' . e(__('immer')) . '</small>' : '' ?></span></label>
          <?php endforeach; ?>
        </fieldset>
        <div class="adm-row">
          <button class="adm-btn adm-btn--small" type="submit" form="pool-<?= e($pk) ?>"><?= e(__('Speichern')) ?></button>
          <?php if ($n === 0): ?><button class="adm-btn adm-btn--small adm-btn--ghost" type="submit" form="pool-del-<?= e($pk) ?>" data-confirm="<?= e(__('Pool „{name}“ löschen?', ['name' => $pl])) ?>"><?= e(__('Löschen')) ?></button>
          <?php else: ?><span class="adm-muted pl-hint"><?= e(__('Löschen erst, wenn der Pool leer ist.')) ?></span><?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <div class="adm-inline-box pl-new">
        <strong><?= e(__('Neuen Pool anlegen')) ?></strong>
        <div class="adm-row">
          <label class="pl-field"><span><?= e(__('Name')) ?></span><input form="pool-new" name="label" required maxlength="80" placeholder="<?= e(__('z. B. Markenbilder')) ?>"></label>
          <label class="pl-field"><span><?= e(__('Kurzname')) ?></span><input form="pool-new" name="key" required pattern="[a-z][a-z0-9\-]{1,31}" maxlength="32" placeholder="marke"></label>
        </div>
        <fieldset class="pl-sites"><legend><?= e(__('Nutzen dürfen')) ?></legend>
          <?php foreach ($allSites as $sk => $sc): $st = new \Core\Site($sk, $sc); ?>
          <label class="f-check"><input type="checkbox" form="pool-new" name="sites[]" value="<?= e($sk) ?>"<?= $sk === site()->key ? ' checked' : '' ?>> <span><?= e($sk === 'default' ? __('Hauptwebsite') : $st->label()) ?></span></label>
          <?php endforeach; ?>
        </fieldset>
        <label class="f-check"><input type="checkbox" form="pool-new" name="protected" value="1"> <span><?= e(__('Geschützt: Dateien nur für angemeldete Personen (z. B. Mitgliederbereich) – nicht öffentlich erreichbar, nicht in der Suche')) ?></span></label>
        <button class="adm-btn adm-btn--primary adm-btn--small" type="submit" form="pool-new"><?= e(__('Anlegen')) ?></button>
      </div>
    <?php else: ?>
      <?php $mine = \Core\MediaPools::forSite(); ?>
      <p><?= $mine ? e(__('Diese Website nutzt:')) . ' <strong>' . e(implode(', ', $mine)) . '</strong>' : e(__('Diese Website nutzt keine geteilten Medien.')) ?></p>
      <p class="adm-muted"><?= e(__('Pools anlegen und zuordnen kann die Hauptwebsite bzw. die Agentur.')) ?></p>
    <?php endif; ?>
  </section>

  <?php if ($sharedTab): ?><?= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_shared_system.php', ['part' => 'panel', 'user' => $user]) ?><?php endif; ?>

  <?php // Adresse der Verwaltung (Core\AdminPath): eigenes Formular #adminpath unten, Felder per form-Attribut
    $apCustom = \Core\AdminPath::custom(); $apCan = \Core\AdminPath::canManage() && !\Core\AdminPath::fromEnv();
    $apHasPw = (string) (\Core\Mfa::row(app()->auth->user() ?? [])['password_hash'] ?? '') !== ''; ?>
  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-umgebung" aria-labelledby="tab-umgebung" data-nosave hidden>
<?php $env = environment(); include __DIR__ . '/system/_environment.php'; ?>
  </section>

  <section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-adminpath" aria-labelledby="tab-adminpath" data-nosave hidden>
    <h2><?= e(__('Adresse der Verwaltung')) ?></h2>
    <p class="set-page__lead"><?= e(__('Unter dieser Adresse melden Sie sich an. Standard ist /admin. Optional eine eigene Adresse: Sie hält automatische Login-Scanner fern – ersetzt aber keine starken Passwörter und keinen zweiten Faktor.')) ?></p>
    <section class="set-group">
      <div class="set-list">
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Aktuell')) ?></span><code class="set-row__sub" id="ap-current"><?= e(absolute_url('/admin')) ?></code></div>
          <div class="set-row__ctl"><?= $apCustom ? '<span class="adm-badge">' . e(__('eigene Adresse')) . '</span>' : '<span class="adm-badge adm-badge--muted">' . e(__('Standard')) . '</span>' ?>
            <button type="button" class="adm-btn adm-btn--small" data-copy="#ap-current"><?= e(__('Kopieren')) ?></button></div></div>
        <?php if (\Core\Sites::multi()): ?><div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Gilt für')) ?></span></div><div class="set-row__ctl"><?= e(__('alle Websites dieser Installation')) ?></div></div><?php endif; ?>
      </div>
    </section>
    <?php if (\Core\AdminPath::fromEnv()): ?>
    <p class="adm-flash adm-flash--info"><?= e(__('Festgelegt über die Umgebungsvariable KLXM_ADMIN_PATH beim Hosting – dort ändern.')) ?></p>
    <?php elseif (!$apCan): ?>
    <p class="adm-flash adm-flash--info"><?= e(__('Die Adresse gilt für alle Websites dieser Installation – ändern kann sie nur die Netzwerk-Administration.')) ?></p>
    <?php else: $apSuggest = \Core\AdminPath::random(); ?>
    <section class="set-group">
      <h3 class="set-group__title"><?= e(__('Adresse ändern')) ?></h3>
      <div class="set-list">
        <div class="f"><label for="ap-path"><?= e(__('Neue Adresse')) ?></label>
          <div class="adm-prefix ap-input"><span aria-hidden="true"><?= e(preg_replace('~^https?://~', '', site_url()) . '/') ?></span><input id="ap-path" name="admin_path" form="adminpath" autocomplete="off" spellcheck="false" maxlength="40" pattern="[a-z0-9][a-z0-9\-]{4,39}" value="<?= e($apCustom ? ltrim(\Core\AdminPath::prefix(), '/') : '') ?>" placeholder="<?= e($apSuggest) ?>" aria-describedby="ap-help"></div>
          <p class="f-help ap-hint" id="ap-help"><?= e(__('5–40 Zeichen: Kleinbuchstaben, Ziffern, Bindestriche. Nicht erlaubt sind übliche Adressen wie admin, login, wp-admin, backend oder verwaltung.')) ?> <?= e(__('Vorschlag:')) ?> <code><?= e($apSuggest) ?></code></p></div>
        <?php if ($apHasPw && !\Core\Mfa::recentAuth()): // kürzlich angemeldet/bestätigt: kein Passwort nötig ?><div class="f f--inline"><label for="ap-pw"><?= e(__('Ihr Passwort zur Bestätigung')) ?></label><input id="ap-pw" name="password" type="password" form="adminpath" autocomplete="current-password" required></div><?php endif; ?>
      </div>
      <p class="set-group__note"><?= e(__('Nach dem Ändern geht es sofort unter der neuen Adresse weiter. Lesezeichen und die App auf dem Homescreen bitte neu anlegen. /admin zeigt ohne Anmeldung nur noch „Seite nicht gefunden“.')) ?></p>
      <div class="set-actions">
        <button class="adm-btn adm-btn--primary" type="submit" form="adminpath"><?= e(__('Adresse ändern')) ?></button>
        <?php if ($apCustom): ?><button class="adm-btn" type="submit" form="adminpath" name="reset" value="1" formnovalidate><?= e(__('Zurück zu /admin')) ?></button><?php endif; ?>
      </div>
    </section>
    <?php endif; ?>
  </section>

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
    <?php if (\Core\Sites::multi() && \Core\Network\Network::isNetworkSite()): ?>
    <section class="set-group">
      <h3 class="set-group__title">Websites dieser Installation</h3>
      <div class="set-list">
      <?php foreach (\Core\Sites::all() as $k => $c): $s = new \Core\Site($k, $c); $h = $s->hosts()[0] ?? null; ?>
        <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e($s->label()) ?><?= $k === site()->key ? ' <span class="adm-badge">diese</span>' : '' ?></span>
          <span class="set-row__sub"><code><?= e($k) ?></code> · <?= e(implode(', ', $s->hosts()) ?: 'alle übrigen Domains') ?> · Kit: <?= e($k === site()->key ? app()->theme->name : ((string) ($c['theme'] ?? '') ?: '–')) ?></span></div>
          <div class="set-row__ctl"><?php if ($h && $k !== site()->key): ?><a href="<?= e(\Core\Network\Network::siteLink($k, '/admin')) ?>" target="_blank" rel="noopener">Verwaltung ↗</a><?php endif; ?></div></div>
      <?php endforeach; ?>
      </div>
      <p class="set-group__note">Jede Website hat eigene Datenbank, Medien, Benutzer und Schlüssel. Neue Website: <code>php bin/console site:create &lt;key&gt; &lt;domain&gt; [kit]</code></p>
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
<?php if (\Core\MediaPools::canManage()): ?>
<form id="pool-new" method="post" action="<?= e(url('/admin/system/pools')) ?>"><?= csrf_field() ?></form>
<?php foreach (\Core\MediaPools::all() as $pk => $pl): ?>
<form id="pool-<?= e($pk) ?>" method="post" action="<?= e(url('/admin/system/pools/' . $pk)) ?>"><?= csrf_field() ?></form>
<form id="pool-del-<?= e($pk) ?>" method="post" action="<?= e(url('/admin/system/pools/' . $pk . '/delete')) ?>"><?= csrf_field() ?></form>
<?php endforeach; endif; ?>
<form id="cache" method="post" action="<?= e(url('/admin/system/cache')) ?>"><?= csrf_field() ?></form>
<?= \Core\Theme::capture(ROOT . '/app/Admin/views/system/_search.php', ['part' => 'forms']) ?><?= \Core\Theme::capture(ROOT . '/app/Admin/views/system/_ai.php', ['part' => 'forms']) ?>
<?php if ($sharedTab): ?><?= \Core\Theme::capture(ROOT . '/app/Admin/views/data/_shared_system.php', ['part' => 'forms']) ?><?php endif; ?>
