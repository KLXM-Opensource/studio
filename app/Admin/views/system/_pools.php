<?php
/**
 * Geteilte Medien (Core\MediaPools): Pools anlegen, Websites zuordnen, „Pflegen dürfen“. Reiter der Grundeinstellungen ($poolsPanel,
 * Einzelinstallation) bzw. Netzwerk → Geteilte Medien & Daten (mehrere Websites, Core\Network\Area).
 * @var bool $poolsPanel
 */
$poolsPanel ??= true;
?>
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
  <?php if ($poolsPanel): ?><section class="adm-card adm-panel adm-panel--groups" role="tabpanel" id="panel-pools" aria-labelledby="tab-pools" hidden><?php else: ?><section class="adm-card" id="pools" aria-labelledby="pools-h"><?php endif; ?>
    <h2 id="pools-h"><?= e(__('Geteilte Medien')) ?></h2>
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
<?php if (!$poolsPanel && $manage): // Netzwerk-Bereich: eigene Formulare (in den Grundeinstellungen stehen sie hinter dem Hauptformular) ?>
<form id="pool-new" method="post" action="<?= e(url('/admin/system/pools')) ?>"><?= csrf_field() ?></form>
<?php foreach (\Core\MediaPools::all() as $pk => $pl): ?>
<form id="pool-<?= e($pk) ?>" method="post" action="<?= e(url('/admin/system/pools/' . $pk)) ?>"><?= csrf_field() ?></form>
<form id="pool-del-<?= e($pk) ?>" method="post" action="<?= e(url('/admin/system/pools/' . $pk . '/delete')) ?>"><?= csrf_field() ?></form>
<?php endforeach; endif; ?>
