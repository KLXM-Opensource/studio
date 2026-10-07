<?php
/** Rolle bearbeiten: Rechte als Matrix. @var ?array $role  @var array $catalog  @var array $tables */
$isNew = $role === null;
$perms = $role['permissions'] ?? [];
$some = is_array($role['tables'] ?? null);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/users/rollen')) ?>"><?= e(__('Benutzer & Rollen')) ?></a></p>
    <h1><?= $isNew ? e(__('Neue Rolle')) : e($role['name']) ?></h1></div>
</header>
<form method="post" action="<?= e(url($isNew ? '/admin/roles' : '/admin/roles/' . $role['key'])) ?>" class="us-form set-page" novalidate>
  <?= csrf_field() ?>
  <section class="set-group">
    <h2 class="set-group__title"><?= e(__('Rolle')) ?></h2>
    <div class="set-list">
      <div class="f f--inline"><label for="r-name"><?= e(__('Name')) ?> <span class="req">*</span></label><input id="r-name" name="name" required maxlength="60" value="<?= e($role['name'] ?? '') ?>" placeholder="<?= e(__('z. B. Presse, Empfang, Praktikum')) ?>"></div>
      <div class="f f--inline"><label for="r-desc"><?= e(__('Beschreibung')) ?></label><input id="r-desc" name="description" maxlength="200" value="<?= e($role['description'] ?? '') ?>"></div>
    </div>
  </section>
  <?php // Rechte je Bereich: eine Gruppe mit einem Schalter je Recht ?>
  <div class="us-matrix">
  <?php foreach ($catalog as $group => $items): ?>
    <fieldset class="set-group">
      <legend class="set-group__title"><?= e($group) ?></legend>
      <div class="set-list">
      <?php foreach ($items as $k => $label): ?>
        <div class="f f--bool"><label class="f-check"><input type="checkbox" role="switch" name="perms[]" value="<?= e($k) ?>"<?= in_array($k, $perms, true) ? ' checked' : '' ?>> <span><?= e($label) ?></span></label></div>
      <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endforeach; ?>
  </div>
  <?php if ($tables): ?>
  <fieldset class="set-group">
    <legend class="set-group__title"><?= e(__('Datentabellen')) ?></legend>
    <div class="set-list">
      <label class="set-row us-radio"><input type="radio" name="tables_mode" value="all"<?= !$some ? ' checked' : '' ?>> <span class="set-row__main"><span class="set-row__label"><?= e(__('Alle Tabellen')) ?></span></span></label>
      <div class="set-row set-row--stack">
        <label class="us-radio"><input type="radio" name="tables_mode" value="some"<?= $some ? ' checked' : '' ?>> <span class="set-row__label"><?= e(__('Nur ausgewählte:')) ?></span></label>
        <div class="set-checks us-tablepick">
          <?php foreach ($tables as $t): ?><label class="f-check"><input type="checkbox" name="tables[]" value="<?= e($t['handle']) ?>"<?= $some && in_array($t['handle'], $role['tables'], true) ? ' checked' : '' ?>> <span><?= icon($t['icon'], ['class' => 'us-tico']) ?> <?= e($t['name']) ?><?php if (\Core\Data\Tables::isInbox($t)): ?> <small class="adm-muted">(<?= e(__('Anfragen')) ?>)</small><?php endif; ?></span></label><?php endforeach; ?>
        </div>
      </div>
    </div>
    <p class="set-group__note"><?= e(__('Gilt für die Rechte im Bereich „Daten“ (außer „Tabellen und Felder ändern“) und für „Anfragen“ – z. B. nur eine Eingangs-Tabelle lesen.')) ?></p>
  </fieldset>
  <?php endif; ?>
  <div class="adm-savebar"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Rolle speichern')) ?></button> <a class="adm-btn" href="<?= e(url('/admin/users/rollen')) ?>"><?= e(__('Abbrechen')) ?></a></div>
</form>
