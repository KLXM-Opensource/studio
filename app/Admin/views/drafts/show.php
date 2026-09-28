<?php
/**
 * Ein Entwurf (Core\Review\Drafts): Unterschiede Entwurf ↔ veröffentlicht (review/_diff.php), Angaben, Notiz, Aktionen.
 * @var array $i  @var array $diff  @var array $users
 */
use Core\Http\Controllers\Admin\DraftController;
use Core\Lang;
use Core\Review\Drafts;

$when = fn(?string $t) => $t ? date('d.m.Y H:i', (int) strtotime($t)) : '–';
$self = $i['type'] === 'page' ? '/admin/entwuerfe/seite/' . $i['id'] : '/admin/entwuerfe/eintrag/' . $i['table']['handle'] . '/' . $i['id'];
$isNew = $i['state'] !== 'changed';
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/entwuerfe')) ?>"><?= e(__('Entwürfe')) ?></a> · <?= e($i['type'] === 'page' ? __('Seite') : $i['table']['name']) ?></p>
    <h1><?= e($i['title']) ?></h1>
    <p class="rv-head__sum"><span class="dr-st dr-st--<?= e($i['state']) ?>"><?= e(Drafts::stateLabel($i['state'])) ?></span>
      <?php if ($i['stale']): ?><span class="dr-st dr-st--stale"><?= e(__('vergessen?')) ?></span><?php endif; ?>
      <?php if ($i['origin_label'] !== ''): ?><span class="rv-ch rv-ch--<?= e($i['origin_key']) ?>"><?= e($i['origin_label']) ?></span><?php endif; ?></p></div>
  <a class="adm-btn adm-btn--small" href="<?= e(url($i['edit'])) ?>"><?= e($i['type'] === 'page' ? __('Im Editor öffnen') : __('Bearbeiten')) ?></a>
</header>

<div class="rv-layout">
  <section class="adm-card rv-changes" aria-labelledby="dr-diff-h">
    <div class="rv-changes__head"><h2 id="dr-diff-h"><?= e($isNew ? __('Inhalt des Entwurfs') : __('Unterschiede zur veröffentlichten Fassung')) ?></h2></div>
    <?php if ($i['state'] === 'offline'): ?><p class="adm-muted"><?= e(__('Diese Seite bzw. dieser Eintrag war schon online und ist derzeit offline (Status „Entwurf“). Veröffentlichen stellt ihn wieder online.')) ?></p><?php endif; ?>
    <?php if (!$diff): ?>
    <p class="adm-muted"><?= e(__('Keine inhaltlichen Unterschiede – der Entwurf entspricht der veröffentlichten Fassung.')) ?></p>
    <?php else: ?>
    <?= \Core\Theme::capture(ROOT . '/app/Admin/views/review/_diff.php', ['diff' => $diff, 'newLabel' => __('Entwurf'), 'oldLabel' => __('Veröffentlicht'),
        'removedLabel' => __('Veröffentlicht (fällt weg)')]) ?>
    <?php endif; ?>
  </section>

  <aside class="rv-side">
    <section class="adm-card" aria-labelledby="dr-meta-h">
      <h2 id="dr-meta-h"><?= e(__('Angaben')) ?></h2>
      <dl class="adm-dl rv-dl">
        <dt><?= e(__('Art')) ?></dt><dd><?= e($i['type'] === 'page' ? __('Seite') : __('Eintrag') . ' · ' . $i['table']['name']) ?></dd>
        <?php if (Lang::multi()): ?><dt><?= e(__('Sprache')) ?></dt><dd><?= e(Lang::all()[$i['lang']] ?? $i['lang']) ?></dd><?php endif; ?>
        <dt><?= e(__('Zuletzt geändert')) ?></dt><dd><?= e($when($i['updated_at'])) ?><?= $i['by'] !== '' ? ' · ' . e($i['by']) : '' ?></dd>
        <dt><?= e(__('Herkunft')) ?></dt><dd><?= e($i['origin_label'] !== '' ? $i['origin_label'] : Drafts::originLabel('')) ?></dd>
      </dl>
    </section>

    <?php if ($i['can_publish'] || $i['can_discard']): ?>
    <section class="adm-card rv-actions" aria-labelledby="dr-act-h">
      <h2 id="dr-act-h"><?= e(__('Entscheidung')) ?></h2>
      <?php if ($i['can_publish']): ?>
      <form method="post" action="<?= e(url('/admin/entwuerfe/veroeffentlichen')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="one" value="<?= e($i['key']) ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
        <button class="adm-btn adm-btn--primary adm-btn--block" type="submit"><?= e(__('Veröffentlichen')) ?></button></form>
      <?php endif; ?>
      <?php if ($i['can_discard']): ?>
      <form method="post" action="<?= e(url('/admin/entwuerfe/verwerfen')) ?>" data-confirm="<?= e($i['type'] === 'page' ? __('Entwurf von „{title}“ verwerfen? Die Seite geht zurück auf die veröffentlichte Fassung; der Entwurf bleibt unter „Versionen“ gesichert.', ['title' => $i['title']]) : __('„{title}“ verwerfen? Der Eintrag wird gelöscht und unter „Zuletzt verworfen“ gesichert.', ['title' => $i['title']])) ?>"><?= csrf_field() ?>
        <input type="hidden" name="one" value="<?= e($i['key']) ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
        <button class="adm-btn adm-btn--danger-text adm-btn--block" type="submit"><?= e(__('Verwerfen')) ?></button></form>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <form class="adm-card" method="post" action="<?= e(url('/admin/entwuerfe/notiz')) ?>" aria-labelledby="dr-note-h" id="<?= e(DraftController::anchor($i['key'])) ?>">
      <?= csrf_field() ?><input type="hidden" name="item" value="<?= e($i['key']) ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
      <h2 id="dr-note-h"><?= e(__('Notiz & Zuständigkeit')) ?></h2>
      <div class="f"><label for="dr-note"><?= e(__('Notiz')) ?></label>
        <textarea id="dr-note" name="note" rows="3" maxlength="500" placeholder="<?= e(__('z. B. wartet auf Freigabe durch …')) ?>"><?= e($i['note']) ?></textarea></div>
      <div class="f"><label for="dr-as"><?= e(__('Zuständig')) ?></label>
        <select id="dr-as" name="assignee"><option value=""><?= e(__('– niemand –')) ?></option>
          <?php foreach ($users as $uid => $uname): ?><option value="<?= (int) $uid ?>"<?= $i['assignee_id'] === $uid ? ' selected' : '' ?>><?= e($uname) ?></option><?php endforeach; ?></select></div>
      <?php if ($i['note_at'] !== ''): ?><p class="f-help"><?= e(__('Zuletzt: {when}', ['when' => $when($i['note_at'])])) ?><?= $i['note_by'] !== '' ? ' · ' . e($i['note_by']) : '' ?></p><?php endif; ?>
      <button class="adm-btn" type="submit"><?= e(__('Notiz speichern')) ?></button>
    </form>
  </aside>
</div>
