<?php
/**
 * Aufgaben an einer Seite bzw. einem Eintrag (Seiteneinstellungen, Eintrag bearbeiten): offene Aufgaben zu diesem Ziel und
 * „Aufgabe anlegen“ mit vorbelegtem Bezug (Dialog, ohne JavaScript eigene Seite). Nichts, wenn die Person weder Aufgaben
 * dazu sieht noch welche anlegen darf.
 * $part: both (Standard) | panel | dialog – in einem umgebenden Formular (Eintrag bearbeiten) den Dialog getrennt außerhalb ausgeben.
 * @var string $link  Ziel (page:12 | entry:news:5)  @var string $back  Rücksprung (/admin/pages/12 …)  @var ?string $part
 */
use Core\Tasks\Tasks;

$tkUser = app()->auth->user();
if (!$tkUser) return;
$tkItems = Tasks::list($tkUser, 'open', $link);
$tkCan = Tasks::canCreate($tkUser);
if (!$tkItems && !$tkCan) return;
$part ??= 'both';
if ($part === 'dialog') {
    if ($tkCan) echo '<link rel="stylesheet" href="' . e(asset('css/tasks.css')) . '">' . \Core\Theme::capture(__DIR__ . '/_dialog.php', ['id' => 'tk-ctx-dlg', 'back' => $back, 'form' => Tasks::formDefaults($tkUser, $link)]);
    return;
}
?>
<link rel="stylesheet" href="<?= e(asset('css/tasks.css')) ?>"><?php // Stile der Aufgaben – die Seite lädt sie sonst nicht (im Body erlaubt) ?>
<section class="adm-card tk-context" aria-labelledby="tk-ctx-h">
  <h2 id="tk-ctx-h"><?= e(__('Aufgaben')) ?><?php if ($tkItems): ?> <span class="adm-badge"><?= count($tkItems) ?></span><?php endif; ?></h2>
  <?php if ($tkItems): ?>
  <ul class="tk-context__list" role="list">
    <?php foreach ($tkItems as $t): $st = Tasks::dueState($t['due']); ?>
    <li><a href="<?= e(url(Tasks::url($t))) ?>"><?= e((string) $t['title']) ?></a>
      <small class="adm-muted"><?= e(Tasks::assigneeLabel($t)) ?><?= $t['due'] ? ' · ' . e(__('fällig {date}', ['date' => fmt()->date($t['due'])])) : '' ?></small>
      <?php if ($st === 'overdue'): ?><span class="tk-tag tk-tag--late"><?= e(__('überfällig')) ?></span><?php endif; ?></li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p class="adm-muted"><?= e(__('Offene Frage dazu? Als Aufgabe hinterlegen – sie erscheint in „Was ist zu tun?“, bis jemand sie abhakt.')) ?></p>
  <?php endif; ?>
  <?php if ($tkCan): ?>
  <a class="adm-btn adm-btn--small" href="<?= e(url('/admin/aufgaben/neu?link=' . rawurlencode($link) . '&back=' . rawurlencode($back))) ?>" data-task-open="tk-ctx-dlg" aria-haspopup="dialog"><?= icon('plus') ?> <?= e(__('Aufgabe anlegen')) ?></a>
  <?php if ($part === 'both'): ?><?= \Core\Theme::capture(__DIR__ . '/_dialog.php', ['id' => 'tk-ctx-dlg', 'back' => $back, 'form' => Tasks::formDefaults($tkUser, $link)]) ?><?php endif; ?>
  <?php endif; ?>
</section>
