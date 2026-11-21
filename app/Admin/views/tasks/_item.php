<?php
/**
 * Eine Aufgabe in der Liste (Core\Tasks): Abhaken (mit optionalem Kommentar), Titel, Zuständigkeit, Fälligkeit, Priorität, Bezug,
 * Herkunft; erledigte mit wer/wann/Kommentar und „Wieder öffnen“. Entwurfs-Notizen (kind draft) führen zu den Entwürfen.
 * @var array $t  @var array $me  @var string $back  @var int $n
 */
use Core\Tasks\Tasks;

$done = $t['status'] === 'done';
$draft = $t['kind'] === 'draft';
$state = $done ? '' : Tasks::dueState($t['due']);
$target = Tasks::target($t['link']);
$canDone = !$draft && Tasks::canDone($t, $me);
$tid = 'tk-t-' . $n;
?>
<li class="tk-item<?= $done ? ' is-done' : '' ?><?= $t['priority'] === 'high' && !$done ? ' is-high' : '' ?><?= $state === 'overdue' ? ' is-overdue' : '' ?>" id="<?= e($draft ? 'tk-d-' . $n : 'tk-' . (int) $t['id']) ?>">
  <span class="tk-item__ico" aria-hidden="true"><?= icon($done ? 'check-circle' : ($draft ? 'pencil-simple' : 'circle')) ?></span>
  <div class="tk-item__main">
    <p class="tk-item__title"><a id="<?= e($tid) ?>" href="<?= e(url(Tasks::url($t))) ?>"><?= e((string) $t['title']) ?></a>
      <?php if ($draft): ?> <span class="tk-tag"><?= e(__('Entwurf')) ?></span><?php endif; ?>
      <?php if ($t['priority'] === 'high' && !$done): ?> <span class="tk-tag tk-tag--high"><?= e(__('Priorität hoch')) ?></span><?php endif; ?>
      <?php if ($state === 'overdue'): ?> <span class="tk-tag tk-tag--late"><?= e(__('überfällig')) ?></span><?php elseif ($state === 'today'): ?> <span class="tk-tag tk-tag--soon"><?= e(__('heute fällig')) ?></span><?php endif; ?></p>
    <p class="tk-item__meta">
      <span><?= icon($t['assignee_id'] !== null ? 'user' : 'users') ?> <?= e(Tasks::assigneeLabel($t)) ?></span>
      <?php if ($t['due']): ?><span><?= icon('calendar') ?> <?= e(__('fällig {date}', ['date' => fmt()->date($t['due'])])) ?></span><?php endif; ?>
      <?php if ($target): ?><span class="tk-item__target<?= $target['missing'] ? ' is-missing' : '' ?>"><?= icon('link-simple') ?> <?= e($target['type']) ?>: <?php if ($target['admin'] !== '' || $target['href'] !== ''): ?><a href="<?= e($target['admin'] !== '' ? url($target['admin']) : $target['href']) ?>"><?= e($target['label']) ?></a><?php else: ?><?= e($target['label']) ?><?php endif; ?></span><?php endif; ?>
      <?php if (!$done && (string) ($t['created_label'] ?? '') !== ''): ?><span class="adm-muted"><?= e(__('von {name}', ['name' => (string) $t['created_label']])) ?><?= $t['created_at'] ? ' · ' . e(fmt()->relative((string) $t['created_at'])) : '' ?></span><?php endif; ?>
    </p>
    <?php if (!$done && (string) ($t['body'] ?? '') !== ''): ?><p class="tk-item__body"><?= e(\Core\Support\Markdown::plain((string) $t['body'], 220)) ?></p><?php endif; ?>
    <?php if ($done): ?>
    <p class="tk-item__done"><?= icon('check') ?> <?= e(__('Erledigt {when} von {name}', ['when' => fmt()->datetime((string) $t['done_at']), 'name' => (string) ($t['done_label'] ?? '')])) ?><?php if ((string) ($t['done_comment'] ?? '') !== ''): ?>: <q><?= e((string) $t['done_comment']) ?></q><?php endif; ?></p>
    <?php endif; ?>
  </div>
  <div class="tk-item__act">
    <?php if ($draft): ?>
    <a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url(Tasks::url($t))) ?>"><?= e(__('In Entwürfe öffnen')) ?><span class="sr-only">: <?= e((string) $t['title']) ?></span></a>
    <?php elseif ($canDone && !$done): ?>
    <details class="tk-done">
      <summary class="adm-btn adm-btn--small"><?= icon('check') ?> <?= e(__('Erledigt')) ?><span class="sr-only">: <?= e((string) $t['title']) ?></span></summary>
      <form method="post" action="<?= e(url('/admin/aufgaben/' . (int) $t['id'] . '/erledigt')) ?>" class="tk-done__form">
        <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
        <label for="tk-c-<?= $n ?>"><?= e(__('Kommentar (optional)')) ?></label>
        <textarea id="tk-c-<?= $n ?>" name="comment" rows="2" maxlength="<?= Tasks::COMMENT_MAX ?>" placeholder="<?= e(__('z. B. was erledigt wurde')) ?>"></textarea>
        <button type="submit" class="adm-btn adm-btn--small adm-btn--primary"><?= e(__('Als erledigt markieren')) ?></button>
      </form>
    </details>
    <?php elseif ($canDone && $done): ?>
    <form method="post" action="<?= e(url('/admin/aufgaben/' . (int) $t['id'] . '/oeffnen')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
      <button type="submit" class="adm-btn adm-btn--small adm-btn--ghost"><?= icon('arrow-counter-clockwise') ?> <?= e(__('Wieder öffnen')) ?><span class="sr-only">: <?= e((string) $t['title']) ?></span></button></form>
    <?php endif; ?>
  </div>
</li>
