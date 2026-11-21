<?php
/**
 * Aufgabe anlegen bzw. bearbeiten (eigene Seite – ohne JavaScript und aus dem Zusammenhang, z. B. /admin/aufgaben/neu?link=page:12).
 * @var ?array $task  @var array $form  @var array $me
 */
$isNew = $task === null;
$action = $isNew ? '/admin/aufgaben/neu' : '/admin/aufgaben/' . (int) $task['id'];
$cancel = $form['back'] !== '' ? $form['back'] : ($isNew ? '/admin/aufgaben' : '/admin/aufgaben/' . (int) $task['id']);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/aufgaben')) ?>"><?= e(__('Aufgaben')) ?></a></p>
    <h1><?= e($isNew ? __('Neue Aufgabe') : __('Aufgabe bearbeiten')) ?></h1></div>
</header>
<?php if ($form['errors']): ?><p class="adm-flash adm-flash--error" role="alert"><?= e(__('Bitte prüfen Sie die markierten Felder.')) ?></p><?php endif; ?>
<form method="post" action="<?= e(url($action)) ?>" class="adm-card tk-page">
  <?= csrf_field() ?>
  <input type="hidden" name="back" value="<?= e($form['back']) ?>">
  <?= \Core\Theme::capture(__DIR__ . '/_form.php', ['form' => $form, 'idp' => 'tk-page']) ?>
  <div class="adm-form-actions">
    <button type="submit" class="adm-btn adm-btn--primary"><?= e($isNew ? __('Aufgabe anlegen') : __('Speichern')) ?></button>
    <a class="adm-btn adm-btn--ghost" href="<?= e(url($cancel)) ?>"><?= e(__('Abbrechen')) ?></a>
  </div>
</form>
