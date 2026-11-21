<?php
/**
 * Dialog „Aufgabe anlegen“ (Core\Tasks) – öffnet über [data-task-open="{id}"] (resources/js/_tasks.js). Ohne JavaScript führt der
 * Auslöser auf /admin/aufgaben/neu (gleiche Felder). Abbrechen schließt ohne Skript (formmethod="dialog").
 * @var string $id  @var array $form  @var string $back
 */
?>
<dialog class="adm-dialog tk-dialog" id="<?= e($id) ?>" aria-labelledby="<?= e($id) ?>-h">
  <form method="post" action="<?= e(url('/admin/aufgaben/neu')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="adm-dialog__head"><h2 id="<?= e($id) ?>-h"><?= e(__('Aufgabe anlegen')) ?></h2>
      <button type="submit" formmethod="dialog" formnovalidate class="adm-btn adm-btn--small adm-btn--ghost" aria-label="<?= e(__('Schließen')) ?>"><?= icon('x') ?></button></div>
    <?= \Core\Theme::capture(__DIR__ . '/_form.php', ['form' => $form, 'idp' => $id]) ?>
    <div class="tk-dialog__actions">
      <button type="submit" class="adm-btn adm-btn--primary"><?= icon('plus') ?> <?= e(__('Aufgabe anlegen')) ?></button>
      <button type="submit" formmethod="dialog" formnovalidate class="adm-btn adm-btn--ghost"><?= e(__('Abbrechen')) ?></button>
    </div>
  </form>
</dialog>
