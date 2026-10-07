<?php
/**
 * Mitteilungen → Kanäle: Tabellen-Kanäle (automatisch aus Datentabellen mit Abo) und freie Kanäle (Name, Beschreibung, öffentlich,
 * Reihenfolge, archivieren). Rechts: gewählter Kanal bzw. neuer Kanal.
 * @var ?array $sel  @var array $errors  @var array $old  @var bool $canSend
 */
use Core\Push\Channels;

$b = \Core\Http\Controllers\Admin\MessagesController::BASE;
$all = Channels::all();
$tables = array_filter($all, fn($c) => $c['kind'] === 'table');
$free = Channels::free();
$err = fn(string $k) => isset($errors[$k]) ? '<p class="f-error" id="pc-err-' . e($k) . '">' . e($errors[$k]) . '</p>' : '';
$isNew = isset($sel['new']);
$val = fn(string $k, mixed $d = '') => $old[$k] ?? ($sel[$k] ?? $d);
?>
<h1 class="adm-sr"><?= e(__('Kanäle')) ?></h1>
<div class="pm pm--split<?= $sel ? ' is-reading' : '' ?>">
  <section class="pm-col pm-col--list" aria-labelledby="pc-h">
    <div class="pm-bar"><div class="pm-bar__title"><h2 id="pc-h"><?= e(__('Kanäle')) ?></h2><small><?= e(__('Was Besucher abonnieren können')) ?></small></div>
      <?php if ($canSend): ?><a class="adm-btn adm-btn--small adm-btn--primary" href="<?= e(url($b . '/kanaele?neu=1')) ?>"><?= icon('plus') ?> <span><?= e(__('Kanal')) ?></span></a><?php endif; ?></div>
    <div class="pm-scroll">
      <h3 class="pm-listhead"><?= e(__('Freie Kanäle')) ?></h3>
      <ul class="pm-list" role="list">
        <?php if (!$free): ?><li class="pm-empty"><?= e(__('Noch keine – z. B. „Allgemeine News“ oder „Notdienst“. Mitteilungen dafür schreiben Sie von Hand.')) ?></li><?php endif; ?>
        <?php foreach ($free as $c): $t = Channels::PREFIX . $c['handle']; $on = $sel && (int) ($sel['id'] ?? 0) === (int) $c['id']; ?>
        <li><a class="pm-row<?= $on ? ' is-active' : '' ?><?= $c['archived_at'] ? ' is-archived' : '' ?>" href="<?= e(url($b . '/kanaele?id=' . (int) $c['id'])) ?>"<?= $on ? ' aria-current="true"' : '' ?>>
          <span class="pm-row__ico" aria-hidden="true"><?= icon('broadcast') ?></span>
          <span class="pm-row__main"><span class="pm-row__title"><?= e($c['name']) ?></span><span class="pm-row__sub"><?= e((string) ($c['description'] ?? '')) ?: '<code>' . e($t) . '</code>' ?></span></span>
          <span class="pm-row__meta"><span class="pm-row__n"><?= e(fmt()->number(Channels::subscribers($t))) ?></span>
            <?php if ($c['archived_at']): ?><span class="adm-badge adm-badge--muted"><?= e(__('archiviert')) ?></span><?php elseif (!(int) $c['public']): ?><span class="adm-badge adm-badge--muted"><?= e(__('nicht öffentlich')) ?></span><?php endif; ?></span></a></li>
        <?php endforeach; ?>
      </ul>
      <h3 class="pm-listhead"><?= e(__('Aus Datentabellen')) ?></h3>
      <ul class="pm-list" role="list">
        <?php if (!$tables): ?><li class="pm-empty"><?= e(__('Keine Tabelle bietet ein Abo an – einschalten unter Daten → Tabelle → Felder & Einstellungen → „Benachrichtigungen (Push)“.')) ?></li><?php endif; ?>
        <?php foreach ($tables as $t => $c): ?>
        <li><a class="pm-row" href="<?= e(url('/admin/data/' . $c['handle'] . '/schema#table-push')) ?>">
          <span class="pm-row__ico pm-row__ico--auto" aria-hidden="true"><?= icon('table') ?></span>
          <span class="pm-row__main"><span class="pm-row__title"><?= e($c['name']) ?></span><span class="pm-row__sub"><?= e($c['description'] ?: __('Neue Einträge lösen automatisch eine Mitteilung aus')) ?></span></span>
          <span class="pm-row__meta"><span class="pm-row__n"><?= e(fmt()->number(Channels::subscribers($t))) ?></span><span class="pm-row__sub"><?= e(__('Einstellungen ↗')) ?></span></span></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <section class="pm-col pm-col--pane" aria-labelledby="pc-pane-h">
    <?php if (!$sel): ?>
    <div class="pm-blank"><?= icon('broadcast') ?><p id="pc-pane-h"><?= e(__('Freie Kanäle sammeln Abos für Mitteilungen, die Sie von Hand schreiben – etwa „Allgemeine News“ oder „Notdienst“. Kanäle aus Datentabellen entstehen automatisch.')) ?></p></div>
    <?php else: $t = $isNew ? '' : Channels::PREFIX . $sel['handle']; ?>
    <div class="pm-bar"><a class="pm-back" href="<?= e(url($b . '/kanaele')) ?>"><span aria-hidden="true">‹</span> <?= e(__('Zurück')) ?></a>
      <div class="pm-bar__title"><h2 id="pc-pane-h"><?= e($isNew ? __('Neuer Kanal') : (string) $sel['name']) ?></h2><?php if (!$isNew): ?><small><code><?= e($t) ?></code></small><?php endif; ?></div>
      <?php if (!$isNew && $canSend && !$sel['archived_at']): ?><a class="adm-btn adm-btn--small" href="<?= e(url($b . '/neu?kanal=' . rawurlencode($t))) ?>"><?= icon('paper-plane-tilt') ?> <span><?= e(__('Mitteilung schreiben')) ?></span></a><?php endif; ?></div>
    <div class="pm-scroll pm-detail">
      <form method="post" action="<?= e(url($b . '/kanaele' . ($isNew ? '' : '/' . (int) $sel['id']))) ?>" novalidate>
        <?= csrf_field() ?>
        <fieldset class="set-group"<?= $canSend ? '' : ' disabled' ?>>
          <legend class="set-group__title"><?= e(__('Kanal')) ?></legend>
          <div class="set-list">
            <div class="f<?= isset($errors['name']) ? ' f--error' : '' ?>"><label for="pc-name"><?= e(__('Name')) ?></label>
              <input id="pc-name" name="name" maxlength="80" required value="<?= e((string) $val('name')) ?>"<?= isset($errors['name']) ? ' aria-invalid="true" aria-describedby="pc-err-name"' : '' ?>><?= $err('name') ?></div>
            <?php if ($isNew): ?><div class="f<?= isset($errors['handle']) ? ' f--error' : '' ?>"><label for="pc-handle"><?= e(__('Kurzname (optional)')) ?></label>
              <input id="pc-handle" name="handle" maxlength="40" pattern="[a-z0-9][a-z0-9_\-]*" value="<?= e((string) $val('handle')) ?>" aria-describedby="pc-handle-h">
              <p class="f-help" id="pc-handle-h"><?= e(__('Leer = aus dem Namen. Lässt sich später nicht ändern (Abos hängen daran).')) ?></p><?= $err('handle') ?></div><?php endif; ?>
            <div class="f"><label for="pc-desc"><?= e(__('Beschreibung für Besucher')) ?></label>
              <input id="pc-desc" name="description" maxlength="240" value="<?= e((string) $val('description')) ?>" placeholder="<?= e(__('z. B. Wichtige Hinweise, höchstens einmal pro Woche')) ?>"></div>
            <div class="f f--bool"><input type="hidden" name="public" value="0"><label class="f-check"><input type="checkbox" name="public" value="1"<?= (int) $val('public', 1) ? ' checked' : '' ?>> <span><?= e(__('Öffentlich: Besucher können ihn in Block, Banner und Glocke wählen')) ?></span></label></div>
            <div class="f f--inline"><label for="pc-sort"><?= e(__('Reihenfolge')) ?></label><input type="number" id="pc-sort" name="sort" min="0" max="9999" value="<?= (int) $val('sort', 0) ?>" class="f-in--short"></div>
          </div>
        </fieldset>
        <?php if ($canSend): ?><div class="set-actions"><button class="adm-btn adm-btn--primary" type="submit"><?= e($isNew ? __('Kanal anlegen') : __('Speichern')) ?></button></div><?php endif; ?>
      </form>
      <?php if (!$isNew): ?>
      <section class="set-group">
        <h3 class="set-group__title"><?= e(__('Abos')) ?></h3>
        <div class="set-list">
          <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Aktuell')) ?></span></div><div class="set-row__ctl"><strong><?= e(fmt()->number(Channels::subscribers($t))) ?></strong></div></div>
          <div class="set-row"><div class="set-row__main"><span class="set-row__label"><?= e(__('Angelegt')) ?></span></div><div class="set-row__ctl"><?= e($sel['created_at'] ? fmt()->datetime((string) $sel['created_at']) : '–') ?></div></div>
        </div>
        <p class="set-group__note"><a href="<?= e(url($b . '/statistik?kanal=' . rawurlencode($t))) ?>"><?= e(__('Verlauf der Abos ansehen')) ?></a></p>
      </section>
      <?php if ($canSend): ?>
      <form class="set-actions" method="post" action="<?= e(url($b . '/kanaele/' . (int) $sel['id'] . '/archiv')) ?>"<?= $sel['archived_at'] ? '' : ' data-confirm="' . e(__('Kanal archivieren? Er nimmt keine neuen Abos mehr an und verschwindet aus der Auswahl; bestehende Abos bleiben.')) . '"' ?>><?= csrf_field() ?>
        <button class="adm-btn<?= $sel['archived_at'] ? '' : ' adm-btn--danger-text' ?>" type="submit"><?= e($sel['archived_at'] ? __('Wieder aktivieren') : __('Archivieren')) ?></button></form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>
</div>
