<?php
/**
 * Übersicht → „Was ist zu tun?“: „Aufgabe anlegen“ (Dialog, Core\Tasks) und „Alle Aufgaben“. Nur, was die Rolle darf.
 * @var array $user  @var string $back
 */
use Core\Tasks\Tasks;

$tkCan = Tasks::canCreate($user);
?>
<div class="tk-bar">
  <?php if ($tkCan): ?>
  <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/aufgaben/neu?back=' . rawurlencode($back))) ?>" data-task-open="tk-dash-dlg" aria-haspopup="dialog"><?= icon('plus') ?> <?= e(__('Aufgabe anlegen')) ?></a>
  <?php endif; ?>
  <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/aufgaben')) ?>"><?= icon('list-checks') ?> <?= e(__('Alle Aufgaben')) ?></a>
</div>
<?php if ($tkCan): ?>
<?= \Core\Theme::capture(__DIR__ . '/_dialog.php', ['id' => 'tk-dash-dlg', 'back' => $back, 'form' => Tasks::formDefaults($user)]) ?>
<?php endif; ?>
