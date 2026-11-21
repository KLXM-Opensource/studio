<?php
/**
 * Eine Aufgabe (Core\Tasks): Text, Bezug, Zuständigkeit, Fälligkeit, Priorität, Status – Abhaken mit Kommentar bzw. wieder öffnen,
 * Bearbeiten und Löschen (tasks.manage oder wer sie angelegt hat), Verlauf (wer hat wann was getan).
 * @var array $t  @var array $log  @var array $me  @var array $names
 */
use Core\Tasks\Tasks;

$done = $t['status'] === 'done';
$state = $done ? '' : Tasks::dueState($t['due']);
$target = Tasks::target($t['link']);
$actions = ['created' => __('angelegt'), 'done' => __('erledigt'), 'reopened' => __('wieder geöffnet'), 'assigned' => __('neu zugewiesen'), 'edited' => __('bearbeitet')];
$back = '/admin/aufgaben/' . (int) $t['id'];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/aufgaben')) ?>"><?= e(__('Aufgaben')) ?></a></p>
    <h1><?= e((string) $t['title']) ?></h1>
    <p class="tk-status"><span class="tk-tag<?= $done ? ' tk-tag--done' : '' ?>"><?= e($done ? __('erledigt') : __('offen')) ?></span>
      <?php if ($t['priority'] !== 'normal'): ?><span class="tk-tag<?= $t['priority'] === 'high' ? ' tk-tag--high' : '' ?>"><?= e(__('Priorität {p}', ['p' => Tasks::priorityLabel($t['priority'])])) ?></span><?php endif; ?>
      <?php if ($state === 'overdue'): ?><span class="tk-tag tk-tag--late"><?= e(__('überfällig')) ?></span><?php elseif ($state === 'today'): ?><span class="tk-tag tk-tag--soon"><?= e(__('heute fällig')) ?></span><?php endif; ?></p></div>
  <?php if (Tasks::canEdit($t, $me)): ?><div class="adm-head__actions">
    <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/aufgaben/' . (int) $t['id'] . '/bearbeiten')) ?>"><?= icon('pencil-simple') ?> <?= e(__('Bearbeiten')) ?></a>
  </div><?php endif; ?>
</header>

<div class="tk-show">
  <section class="adm-card tk-show__main" aria-labelledby="tk-desc-h">
    <h2 id="tk-desc-h" class="sr-only"><?= e(__('Beschreibung')) ?></h2>
    <dl class="tk-facts">
      <div><dt><?= e(__('Zuständig')) ?></dt><dd><?= e(Tasks::assigneeLabel($t)) ?></dd></div>
      <div><dt><?= e(__('Fällig')) ?></dt><dd><?= $t['due'] ? e(fmt()->date($t['due'], 'long')) : '–' ?></dd></div>
      <?php if ($target): ?><div><dt><?= e(__('Bezug')) ?></dt><dd><span class="adm-muted"><?= e($target['type']) ?>:</span>
        <?php if ($target['missing']): ?><?= e($target['label']) ?> <span class="tk-tag tk-tag--late"><?= e(__('Ziel nicht gefunden')) ?></span>
        <?php else: ?><?php if ($target['admin'] !== ''): ?><a href="<?= e(url($target['admin'])) ?>"><?= e($target['label']) ?></a><?php else: ?><?= e($target['label']) ?><?php endif; ?>
          <?php if ($target['href'] !== ''): ?> · <a href="<?= e($target['href']) ?>" target="_blank" rel="noopener"><?= e(__('auf der Website')) ?><span class="sr-only"> <?= e(__('(öffnet in neuem Tab)')) ?></span></a><?php endif; ?><?php endif; ?></dd></div><?php endif; ?>
      <div><dt><?= e(__('Angelegt')) ?></dt><dd><?= e(fmt()->datetime((string) $t['created_at'])) ?><?= (string) $t['created_label'] !== '' ? ' · ' . e(__('von {name}', ['name' => (string) $t['created_label']])) : '' ?></dd></div>
    </dl>
    <?php if ((string) ($t['body'] ?? '') !== ''): ?><div class="tk-body"><?= \Core\Support\Markdown::render((string) $t['body']) ?></div><?php endif; ?>

    <?php if (Tasks::canDone($t, $me)): ?>
    <?php if (!$done): ?>
    <form method="post" action="<?= e(url('/admin/aufgaben/' . (int) $t['id'] . '/erledigt')) ?>" class="tk-show__done">
      <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
      <div class="f"><label for="tk-comment"><?= e(__('Kommentar (optional)')) ?></label>
        <textarea id="tk-comment" name="comment" rows="2" maxlength="<?= Tasks::COMMENT_MAX ?>" placeholder="<?= e(__('z. B. was erledigt wurde oder wo es steht')) ?>"></textarea></div>
      <button type="submit" class="adm-btn adm-btn--primary"><?= icon('check') ?> <?= e(__('Als erledigt markieren')) ?></button>
    </form>
    <?php else: ?>
    <p class="tk-item__done"><?= icon('check') ?> <?= e(__('Erledigt {when} von {name}', ['when' => fmt()->datetime((string) $t['done_at']), 'name' => (string) ($t['done_label'] ?? '')])) ?><?php if ((string) ($t['done_comment'] ?? '') !== ''): ?>: <q><?= e((string) $t['done_comment']) ?></q><?php endif; ?></p>
    <form method="post" action="<?= e(url('/admin/aufgaben/' . (int) $t['id'] . '/oeffnen')) ?>" class="tk-show__done">
      <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($back) ?>">
      <button type="submit" class="adm-btn"><?= icon('arrow-counter-clockwise') ?> <?= e(__('Wieder öffnen')) ?></button>
    </form>
    <?php endif; ?>
    <?php endif; ?>
  </section>

  <aside class="tk-show__side">
    <section class="adm-card" aria-labelledby="tk-log-h">
      <h2 id="tk-log-h"><?= e(__('Verlauf')) ?></h2>
      <ol class="tk-log" role="list">
        <?php foreach (array_reverse($log) as $l): ?>
        <li><span class="tk-log__what"><b><?= e((string) ($l['label'] ?? '') ?: '–') ?></b> <?= e($actions[$l['action']] ?? $l['action']) ?></span>
          <time class="adm-muted" datetime="<?= e(date('c', (int) strtotime((string) $l['created_at']))) ?>"><?= e(fmt()->datetime((string) $l['created_at'])) ?></time>
          <?php if ((string) ($l['comment'] ?? '') !== ''): ?><span class="tk-log__comment"><?= e((string) $l['comment']) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ol>
    </section>
    <?php if (Tasks::canEdit($t, $me)): ?>
    <section class="adm-card" aria-labelledby="tk-del-h">
      <h2 id="tk-del-h"><?= e(__('Löschen')) ?></h2>
      <p class="adm-muted"><?= e(__('Erledigte Aufgaben besser abhaken statt löschen – dann bleibt der Verlauf erhalten.')) ?></p>
      <form method="post" action="<?= e(url('/admin/aufgaben/' . (int) $t['id'] . '/loeschen')) ?>" data-confirm="<?= e(__('Aufgabe „{title}“ endgültig löschen?', ['title' => (string) $t['title']])) ?>">
        <?= csrf_field() ?><button type="submit" class="adm-btn adm-btn--small adm-btn--danger-text"><?= icon('trash') ?> <?= e(__('Aufgabe löschen')) ?></button></form>
    </section>
    <?php endif; ?>
  </aside>
</div>
