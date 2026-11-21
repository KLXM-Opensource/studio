<?php
/**
 * Verwaltung → Aufgaben (Core\Tasks): Reiter Offen, Meine, Erledigt (Protokoll mit wer/wann/Kommentar), Alle; „Neue Aufgabe“
 * (Dialog, ohne JavaScript eigene Seite), Erinnerung an fällige Aufgaben (tasks.manage, Standard aus).
 * @var string $f  @var array $items  @var array $counts  @var array $me  @var array $form
 */
use Core\Tasks\Tasks;

$tabs = ['open' => __('Offen'), 'mine' => __('Meine'), 'done' => __('Erledigt'), 'all' => __('Alle')];
$back = '/admin/aufgaben' . ($f !== 'open' ? '?filter=' . $f : '');
$remind = (bool) app()->settings->get(Tasks::REMIND_SETTING, false);
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Inhalte')) ?></p><h1><?= e(__('Aufgaben')) ?></h1>
    <p class="adm-muted"><?= e(__('Offene Fragen und To-dos für die Redaktion – mit Zuständigkeit, Fälligkeit und Bezug zu Seite, Eintrag oder Datei. Aufgaben stehen in „Was ist zu tun?“ auf der Übersicht, bis jemand sie abhakt.')) ?></p></div>
  <?php if ($form['can']): ?><div class="adm-head__actions">
    <a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/aufgaben/neu?back=' . rawurlencode($back))) ?>" data-task-open="tk-new-dlg" aria-haspopup="dialog"><?= icon('plus') ?> <?= e(__('Neue Aufgabe')) ?></a>
  </div><?php endif; ?>
</header>

<nav class="adm-filter rv-tabs tk-tabs" aria-label="<?= e(__('Aufgaben filtern')) ?>">
  <?php foreach ($tabs as $k => $l): ?>
  <a href="<?= e(url('/admin/aufgaben' . ($k !== 'open' ? '?filter=' . $k : ''))) ?>"<?= $f === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> <small class="rv-tabs__n"><?= (int) ($counts[$k] ?? 0) ?></small></a>
  <?php endforeach; ?>
</nav>

<?php if (!$items): ?>
<div class="adm-card rv-empty">
  <p><b><?= e(match ($f) { 'done' => __('Noch nichts erledigt.'), 'mine' => __('Keine offenen Aufgaben für Sie.'), default => __('Keine offenen Aufgaben.') }) ?></b></p>
  <p class="adm-muted"><?= e($f === 'done' ? __('Abgehakte Aufgaben erscheinen hier mit Datum, Person und Kommentar.') : __('Neue Aufgaben legen Sie hier, auf der Übersicht oder direkt an einer Seite bzw. einem Eintrag an – auch über die Konsole, die API oder einen KI-Assistenten (MCP).')) ?></p>
</div>
<?php else: ?>
<section class="adm-card tk-card" aria-labelledby="tk-list-h">
  <h2 class="sr-only" id="tk-list-h"><?= e(__('Aufgaben') . ' · ' . $tabs[$f]) ?></h2>
  <ol class="tk-list" role="list">
    <?php foreach ($items as $n => $t): ?>
    <?= \Core\Theme::capture(__DIR__ . '/_item.php', ['t' => $t, 'me' => $me, 'back' => $back, 'n' => $n]) ?>
    <?php endforeach; ?>
  </ol>
</section>
<?php endif; ?>

<?php if (Tasks::manager($me)): ?>
<section class="adm-card tk-settings" aria-labelledby="tk-set-h">
  <h2 id="tk-set-h"><?= e(__('Erinnerung an fällige Aufgaben')) ?></h2>
  <form method="post" action="<?= e(url('/admin/aufgaben/einstellungen')) ?>" class="tk-settings__form">
    <?= csrf_field() ?>
    <input type="hidden" name="remind" value="0">
    <label class="tk-switch"><input type="checkbox" role="switch" name="remind" value="1"<?= $remind ? ' checked' : '' ?> aria-describedby="tk-set-help" data-autosubmit>
      <span><?= e(__('Zuständige per E-Mail erinnern')) ?></span></label>
    <button type="submit" class="adm-btn adm-btn--small"><?= e(__('Speichern')) ?></button>
  </form>
  <p class="f-help" id="tk-set-help"><?= e(__('Täglich um 7 Uhr (Geplante Aufgaben → „Aufgaben: Erinnerung an fällige“): Aufgaben, die morgen oder heute fällig sind, überfällige höchstens einmal pro Woche. Jede Person kann die E-Mails unter Konto → Mitteilungen abschalten.')) ?></p>
</section>
<?php endif; ?>

<?php if ($form['can']): ?>
<?= \Core\Theme::capture(__DIR__ . '/_dialog.php', ['id' => 'tk-new-dlg', 'back' => $back, 'form' => $form]) ?>
<?php endif; ?>
