<?php
/**
 * Grundeinstellungen → „Geteilte Daten“: geteilte Tabellen dieser Installation anlegen und verwalten.
 * Wird zweimal eingebunden: $part = 'panel' (im Formular der Grundeinstellungen, Eingaben mit form="…") und 'forms' (eigene Formulare dahinter).
 * @var string $part
 */
use Core\Data\Shared;
use Core\Data\Tables;

$manage = Shared::canManage();
$allSites = \Core\Sites::all();
$mine = Shared::forSite();
$visible = \Core\Features::integrator() ? Shared::all() : $mine;
$siteName = fn(string $k) => $k === 'default' ? __('Hauptwebsite') . ' (' . Shared::siteInfo($k)['name'] . ')' : Shared::siteInfo($k)['name'];
$localTables = array_values(array_filter(Tables::content(), fn($t) => !Tables::isShared($t)));

if ($part === 'forms'):
    if ($manage): ?>
<form id="sh-new" method="post" action="<?= e(url('/admin/system/shared')) ?>"><?= csrf_field() ?></form>
<?php foreach ($visible as $k => $m): if (!Shared::canAdmin($k)) continue; ?>
<form id="sh-<?= e($k) ?>" method="post" action="<?= e(url('/admin/system/shared/' . $k)) ?>"><?= csrf_field() ?></form>
<form id="sh-un-<?= e($k) ?>" method="post" action="<?= e(url('/admin/system/shared/' . $k . '/unshare')) ?>"><?= csrf_field() ?></form>
<?php endforeach; endif;
    return;
endif; ?>
<section class="adm-card adm-panel" role="tabpanel" id="panel-shared" aria-labelledby="tab-shared" hidden>
  <h2><?= e(__('Geteilte Daten')) ?></h2>
  <p class="adm-muted"><?= e(__('Datentabellen für mehrere Websites dieser Installation, z. B. Neuigkeiten oder Termine eines Verbands und seiner Vereine. Eine Website ist Eigentümerin und legt die Felder fest; jede beteiligte Website pflegt ihre eigenen Einträge und entscheidet, welche fremden Einträge sie zeigt. Bilder liegen automatisch in geteilten Medien.')) ?></p>

  <?php if (!$visible): ?><p><?= e(__('Diese Website nimmt an keiner geteilten Tabelle teil.')) ?></p><?php endif; ?>
  <?php foreach ($visible as $k => $m): $admin = Shared::canAdmin($k) && $manage; $t = Tables::sharedTable($k); ?>
  <div class="pl-card sh-card">
    <div class="pl-head">
      <?php if ($admin): ?><label class="pl-name"><span class="adm-sr"><?= e(__('Name')) ?></span><input form="sh-<?= e($k) ?>" name="label" value="<?= e($m['label']) ?>" maxlength="80"></label>
      <?php else: ?><strong><?= e($m['label']) ?></strong><?php endif; ?>
      <code><?= e($k) ?></code>
      <span class="adm-muted"><?= e(__('Eigentümer: {site}', ['site' => $siteName($m['owner'])])) ?><?= $m['owner'] === site()->key ? ' ' . e(__('(diese)')) : '' ?></span>
      <?php if ($t && in_array(site()->key, Shared::participants($m), true)): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/data/' . $k)) ?>"><?= e(__('Einträge')) ?></a><?php endif; ?>
    </div>
    <?php if ($admin): ?>
    <fieldset class="pl-sites"><legend><?= e(__('Beteiligte Websites')) ?> <small class="adm-muted"><?= e(__('pflegen eigene Einträge und nutzen die Tabelle auf ihrer Website')) ?></small></legend>
      <?php foreach ($allSites as $sk => $sc): if ($sk === $m['owner']) continue; ?>
      <label class="f-check"><input type="checkbox" form="sh-<?= e($k) ?>" name="members[]" value="<?= e($sk) ?>"<?= in_array($sk, $m['members'], true) ? ' checked' : '' ?>> <span><?= e($siteName($sk)) ?><?= $sk === site()->key ? ' ' . e(__('(diese)')) : '' ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <label class="f-check"><input type="checkbox" form="sh-<?= e($k) ?>" name="members_see_members" value="1"<?= $m['members_see_members'] ? ' checked' : '' ?>> <span><?= e(__('Beteiligte Websites dürfen Einträge der anderen Beteiligten zeigen')) ?></span></label>
    <p class="adm-muted"><?= e(__('Automatische Übernahme und die Auswahl für die eigene Website stellen Sie bei der Tabelle ein (Daten → Tabelle → Anzeige auf dieser Website).')) ?></p>
    <div class="adm-row">
      <button class="adm-btn adm-btn--small" type="submit" form="sh-<?= e($k) ?>"><?= e(__('Speichern')) ?></button>
    </div>
    <?php if ($m['owner'] === site()->key): ?>
    <details class="sh-unshare"><summary><?= e(__('Freigabe beenden …')) ?></summary>
      <p class="adm-muted"><?= e(__('Die Tabelle wird wieder eine eigene Tabelle dieser Website – mit den eigenen Einträgen. Die übrigen Websites verlieren den Zugriff; die geteilten Daten bleiben als Sicherung erhalten. Zum Bestätigen den Kurznamen eintippen:')) ?></p>
      <div class="adm-row"><input form="sh-un-<?= e($k) ?>" name="confirm" placeholder="<?= e($k) ?>" aria-label="<?= e(__('Kurzname zur Bestätigung')) ?>" autocomplete="off"><button class="adm-btn adm-btn--small adm-btn--danger" type="submit" form="sh-un-<?= e($k) ?>"><?= e(__('Freigabe beenden')) ?></button></div>
    </details>
    <?php endif; ?>
    <?php else: ?>
    <p class="adm-muted"><?= e(__('Beteiligt: {sites}', ['sites' => implode(', ', array_map($siteName, Shared::participants($m)))])) ?><?= $m['members_see_members'] ? ' · ' . e(__('Beteiligte sehen sich gegenseitig')) : '' ?></p>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if ($manage): ?>
  <div class="adm-inline-box pl-new sh-new">
    <strong><?= e(__('Neue geteilte Tabelle')) ?></strong>
    <p class="adm-muted"><?= e(__('Diese Website wird Eigentümerin: sie legt die Felder fest und entscheidet über Vorschläge der übrigen Websites.')) ?></p>
    <fieldset class="pl-sites"><legend><?= e(__('Grundlage')) ?></legend>
      <label class="f-check"><input type="radio" form="sh-new" name="mode" value="new" checked> <span><?= e(__('Neue Tabelle aus Vorlage')) ?></span></label>
      <div class="adm-row sh-indent">
        <label class="pl-field"><span><?= e(__('Name')) ?></span><input form="sh-new" name="name" maxlength="80" placeholder="<?= e(__('z. B. Verbandsnews')) ?>"></label>
        <label class="pl-field"><span><?= e(__('Kurzname')) ?></span><input form="sh-new" name="handle" pattern="[a-z][a-z0-9_]{1,40}" maxlength="41" placeholder="verbandsnews"></label>
        <label class="pl-field"><span><?= e(__('Vorlage')) ?></span><select form="sh-new" name="preset"><option value=""><?= e(__('Leer (nur Titel)')) ?></option>
          <?php foreach (\Core\Http\Controllers\Admin\DataController::presets() as $pk => $p): if (($p['kind'] ?? '') === 'inbox') continue; ?><option value="<?= e($pk) ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select></label>
      </div>
      <?php if ($localTables): ?>
      <label class="f-check"><input type="radio" form="sh-new" name="mode" value="local"> <span><?= e(__('Vorhandene Tabelle dieser Website teilen (mit allen Einträgen)')) ?></span></label>
      <div class="adm-row sh-indent"><select form="sh-new" name="local" aria-label="<?= e(__('Tabelle')) ?>">
        <?php foreach ($localTables as $lt): ?><option value="<?= e($lt['handle']) ?>"><?= e($lt['name']) ?> (<?= e($lt['handle']) ?>)</option><?php endforeach; ?>
      </select></div>
      <?php endif; ?>
    </fieldset>
    <fieldset class="pl-sites"><legend><?= e(__('Beteiligte Websites')) ?></legend>
      <?php foreach ($allSites as $sk => $sc): if ($sk === site()->key) continue; ?>
      <label class="f-check"><input type="checkbox" form="sh-new" name="members[]" value="<?= e($sk) ?>"> <span><?= e($siteName($sk)) ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <label class="f-check"><input type="checkbox" form="sh-new" name="members_see_members" value="1"> <span><?= e(__('Beteiligte Websites dürfen Einträge der anderen Beteiligten zeigen')) ?></span></label>
    <button class="adm-btn adm-btn--primary adm-btn--small" type="submit" form="sh-new"><?= e(__('Anlegen')) ?></button>
  </div>
  <?php endif; ?>
</section>
