<?php
/** Rolle bearbeiten: Rechte als Matrix. @var ?array $role  @var array $catalog  @var array $tables */
$isNew = $role === null;
$perms = $role['permissions'] ?? [];
$some = is_array($role['tables'] ?? null);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/users#rollen')) ?>"><?= e(__('Benutzer & Rollen')) ?></a></p>
    <h1><?= $isNew ? e(__('Neue Rolle')) : e($role['name']) ?></h1></div>
</header>
<form method="post" action="<?= e(url($isNew ? '/admin/roles' : '/admin/roles/' . $role['key'])) ?>" class="us-form" novalidate>
  <?= csrf_field() ?>
  <section class="adm-card">
    <div class="adm-grid2">
      <div class="f"><label for="r-name"><?= e(__('Name')) ?> <span class="req">*</span></label><input id="r-name" name="name" required maxlength="60" value="<?= e($role['name'] ?? '') ?>" placeholder="<?= e(__('z. B. Presse, Empfang, Praktikum')) ?>"></div>
      <div class="f"><label for="r-desc"><?= e(__('Beschreibung')) ?></label><input id="r-desc" name="description" maxlength="200" value="<?= e($role['description'] ?? '') ?>"></div>
    </div>
  </section>
  <section class="adm-card">
    <h2><?= e(__('Rechte')) ?></h2>
    <div class="us-matrix">
      <?php foreach ($catalog as $group => $items): ?>
      <fieldset><legend><?= e($group) ?></legend>
        <?php foreach ($items as $k => $label): ?>
        <label class="f-check"><input type="checkbox" name="perms[]" value="<?= e($k) ?>"<?= in_array($k, $perms, true) ? ' checked' : '' ?>> <span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <?php endforeach; ?>
    </div>
  </section>
  <?php if ($tables): ?>
  <section class="adm-card">
    <h2><?= e(__('Datentabellen')) ?></h2>
    <p class="adm-muted"><?= e(__('Gilt für die Rechte im Bereich „Daten“ (außer „Tabellen und Felder ändern“) und für „Anfragen“ – z. B. nur eine Eingangs-Tabelle lesen.')) ?></p>
    <label class="f-check"><input type="radio" name="tables_mode" value="all"<?= !$some ? ' checked' : '' ?>> <span><?= e(__('Alle Tabellen')) ?></span></label>
    <label class="f-check"><input type="radio" name="tables_mode" value="some"<?= $some ? ' checked' : '' ?>> <span><?= e(__('Nur ausgewählte:')) ?></span></label>
    <div class="us-tablepick">
      <?php foreach ($tables as $t): ?><label class="f-check"><input type="checkbox" name="tables[]" value="<?= e($t['handle']) ?>"<?= $some && in_array($t['handle'], $role['tables'], true) ? ' checked' : '' ?>> <span><?= e($t['icon'] . ' ' . $t['name']) ?><?php if (\Core\Data\Tables::isInbox($t)): ?> <small class="adm-muted">(<?= e(__('Anfragen')) ?>)</small><?php endif; ?></span></label><?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  <div class="adm-form-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Rolle speichern')) ?></button> <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/users#rollen')) ?>"><?= e(__('Abbrechen')) ?></a></div>
</form>
