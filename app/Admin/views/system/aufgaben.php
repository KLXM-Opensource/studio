<?php
/**
 * Grundeinstellungen → Geplante Aufgaben (Core\Cron, CronController): Zustand des einen Cronjobs, Aufgaben je Gruppe (Kern,
 * Erweiterungen) mit Zeitplan, nächstem/letztem Lauf, an/aus, Jetzt ausführen; Warteschlange und Protokoll der letzten Läufe.
 * @var array $tasks  @var array $sites  @var ?int $heartbeat  @var bool $running  @var string $line  @var array $queue  @var array $runs  @var string $show
 */
use Core\Cron;

$f = \Core\Format::admin();
$when = fn(?int $ts) => $ts ? $f->datetime($ts) : '–';
$status = function (?string $s): string {
    [$cls, $label] = match ($s) {
        'ok' => ['', __('erfolgreich')], 'error' => [' adm-badge--warn', __('Fehler')], 'timeout' => [' adm-badge--warn', __('Zeitlimit')],
        'running' => [' adm-badge--muted', __('läuft')], 'waiting' => [' adm-badge--muted', __('wartet')], 'done' => ['', __('erledigt')],
        'failed' => [' adm-badge--warn', __('fehlgeschlagen')], default => [' adm-badge--muted', __('noch nie')],
    };
    return '<span class="adm-badge' . $cls . '">' . e($label) . '</span>';
};
$dur = fn(int $sec) => $sec < 60 ? __('{n} s', ['n' => $sec]) : $f->duration($sec);
$groups = [];
foreach ($tasks as $k => $t) $groups[$t['group']][$k] = $t;
$presets = Cron::presets();
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/system')) ?>"><?= e(__('Grundeinstellungen')) ?></a></p><h1><?= e(__('Geplante Aufgaben')) ?></h1>
    <p class="adm-muted"><?= e(__('Wiederkehrende Arbeiten der Installation – Suchindex, Sicherungen, Push, Aufräumen und die Aufgaben der Erweiterungen. Ein einziger Cronjob startet sie; Zeitplan, An/Aus und „Jetzt ausführen“ regeln Sie hier.')) ?></p></div>
</header>

<div class="adm-tabs-form--side us-shell" data-secnav>
<?php $current = 'aufgaben'; include __DIR__ . '/_sidenav.php'; ?>
  <div class="us-main">

<section class="adm-card cr-state<?= $running ? '' : ' cr-state--off' ?>" aria-labelledby="cr-state-h">
  <h2 id="cr-state-h"><?= icon('clock-countdown') ?> <?= e($running ? __('Cronjob läuft') : ($heartbeat ? __('Cronjob läuft nicht mehr') : __('Cronjob ist noch nicht eingerichtet'))) ?></h2>
  <p><?= $heartbeat ? e(__('Letzter Aufruf: {time}', ['time' => $when($heartbeat)])) : e(__('Ohne Cronjob laufen geplante Aufgaben nicht – einige Funktionen holen Arbeiten zwar nebenbei in der Verwaltung nach, aber nicht zuverlässig.')) ?></p>
  <p class="f-help"><?= e(__('Einmal im Hosting (Plesk: Geplante Aufgaben, sonst crontab -e) anlegen, jede Minute:')) ?></p>
  <pre class="cr-line" id="cr-line"><?= e($line) ?></pre>
  <div class="adm-row"><button class="adm-btn adm-btn--small" type="button" data-copy="#cr-line"><?= e(__('Kopieren')) ?></button>
    <small class="adm-muted"><?= e(__('Bisherige einzelne Cronjobs (search:index, push:send, Sicherungen …) danach entfernen – sie laufen jetzt hierüber.')) ?></small></div>
</section>

<?php foreach ($groups as $g => $list): ?>
<section class="adm-card adm-card--flush" aria-labelledby="cr-g-<?= e(md5($g)) ?>">
  <h2 class="cr-group" id="cr-g-<?= e(md5($g)) ?>"><?= e($g) ?></h2>
  <ul class="cr-list" role="list">
  <?php foreach ($list as $k => $t):
      $custom = !isset($presets[$t['schedule']]); $siteList = $sites[$k] ?? null; $idle = $siteList !== null && !$siteList; ?>
    <li class="cr-task<?= $t['enabled'] && !$idle ? '' : ' is-off' ?>" id="t-<?= e($k) ?>">
      <div class="cr-task__main">
        <strong><?= e($t['label']) ?></strong>
        <?php if ($t['help'] !== ''): ?><small class="adm-muted"><?= e($t['help']) ?></small><?php endif; ?>
        <code class="cr-cmd"><?= e($t['command']) ?></code>
        <?php if ($siteList !== null): ?><small class="adm-muted"><?= $idle ? e(__('Erweiterung auf keiner Website aktiv – läuft nicht.'))
            : e(($t['scope'] === 'site' ? __('Läuft für: {sites}', ['sites' => implode(', ', $siteList)]) : __('Einmal für die Installation'))) ?></small><?php endif; ?>
      </div>
      <dl class="cr-task__meta">
        <div><dt><?= e(__('Zeitplan')) ?></dt><dd><?= e(Cron::describe($t['schedule'])) ?><?= $t['custom'] ? ' <span class="adm-badge adm-badge--muted">' . e(__('angepasst')) . '</span>' : '' ?></dd></div>
        <div><dt><?= e(__('Nächster Lauf')) ?></dt><dd><?= e($t['enabled'] && !$idle ? $when($t['next']) : __('aus')) ?></dd></div>
        <div><dt><?= e(__('Letzter Lauf')) ?></dt><dd><?= $status($t['last_status']) ?> <?= e($when($t['last_start'] ? (int) $t['last_start'] : null)) ?>
          <?php if ($t['last_start'] && $t['last_end']): ?><small class="adm-muted">(<?= e($dur(max(0, (int) $t['last_end'] - (int) $t['last_start']))) ?>)</small><?php endif; ?></dd></div>
      </dl>
      <?php if (trim((string) $t['last_output']) !== ''): ?>
      <details class="cr-out"<?= $show === $k ? ' open' : '' ?>><summary><?= e(__('Ausgabe des letzten Laufs')) ?></summary><pre><?= e((string) $t['last_output']) ?></pre></details>
      <?php endif; ?>
      <div class="cr-task__actions">
        <form method="post" action="<?= e(url('/admin/system/aufgaben/' . $k)) ?>" class="cr-form"><?= csrf_field() ?>
          <label class="f-check"><input type="checkbox" name="enabled" value="1"<?= $t['enabled'] ? ' checked' : '' ?>><span><?= e(__('aktiv')) ?></span></label>
          <label class="adm-sr" for="cr-s-<?= e($k) ?>"><?= e(__('Zeitplan')) ?></label>
          <select id="cr-s-<?= e($k) ?>" name="schedule" data-cr-sched>
            <?php foreach ($presets as $expr => $l): ?><option value="<?= e($expr) ?>"<?= $t['schedule'] === $expr ? ' selected' : '' ?>><?= e($l) ?><?= $expr === $t['default'] ? ' · ' . e(__('Standard')) : '' ?></option><?php endforeach; ?>
            <?php if (!isset($presets[$t['default']])): ?><option value="<?= e($t['default']) ?>"<?= $t['schedule'] === $t['default'] ? ' selected' : '' ?>><?= e(Cron::describe($t['default'])) ?> · <?= e(__('Standard')) ?></option><?php endif; ?>
            <option value="custom"<?= $custom && $t['schedule'] !== $t['default'] ? ' selected' : '' ?>><?= e(__('Eigener Ausdruck …')) ?></option>
          </select>
          <label class="adm-sr" for="cr-c-<?= e($k) ?>"><?= e(__('Eigener Ausdruck (Minute Stunde Tag Monat Wochentag)')) ?></label>
          <input id="cr-c-<?= e($k) ?>" class="cr-custom" name="custom" value="<?= e($t['schedule']) ?>" placeholder="30 3 * * *" spellcheck="false" autocomplete="off">
          <button class="adm-btn adm-btn--small" type="submit"><?= e(__('Speichern')) ?></button>
        </form>
        <form method="post" action="<?= e(url('/admin/system/aufgaben/' . $k . '/run')) ?>"><?= csrf_field() ?>
          <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"<?= $idle ? ' disabled' : '' ?>><?= e(__('Jetzt ausführen')) ?></button></form>
      </div>
    </li>
  <?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>

<?php if ($queue): ?>
<section class="adm-card adm-card--flush" aria-labelledby="cr-q-h">
  <h2 class="cr-group" id="cr-q-h"><?= e(__('Warteschlange')) ?> <small class="adm-muted"><?= e(__('letzte 24 Stunden')) ?></small></h2>
  <div class="cr-scroll"><table class="adm-table">
    <thead><tr><th scope="col"><?= e(__('Aufgabe')) ?></th><th scope="col"><?= e(__('Zustand')) ?></th><th scope="col"><?= e(__('Eingereiht')) ?></th><th scope="col"><?= e(__('Von')) ?></th><th scope="col"><?= e(__('Versuche')) ?></th></tr></thead>
    <tbody><?php foreach ($queue as $q): ?>
      <tr><td><?= e($tasks[$q['k']]['label'] ?? $q['k']) ?></td><td><?= $status($q['status']) ?></td><td><?= e($when((int) $q['created'])) ?></td><td><?= e((string) $q['by']) ?></td><td><?= (int) $q['attempts'] ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if ($runs): ?>
<section class="adm-card adm-card--flush" aria-labelledby="cr-r-h">
  <h2 class="cr-group" id="cr-r-h"><?= e(__('Letzte Läufe')) ?></h2>
  <div class="cr-scroll"><table class="adm-table">
    <thead><tr><th scope="col"><?= e(__('Aufgabe')) ?></th><th scope="col"><?= e(__('Ergebnis')) ?></th><th scope="col"><?= e(__('Start')) ?></th><th scope="col"><?= e(__('Dauer')) ?></th><th scope="col"><?= e(__('Anlass')) ?></th></tr></thead>
    <tbody><?php foreach ($runs as $run): ?>
      <tr><td><a href="<?= e(url('/admin/system/aufgaben') . '?protokoll=' . rawurlencode($run['k']) . '#t-' . $run['k']) ?>"><?= e($tasks[$run['k']]['label'] ?? $run['k']) ?></a></td><td><?= $status($run['status']) ?></td>
        <td><?= e($when((int) $run['started'])) ?></td><td><?= e($dur(max(0, (int) $run['ended'] - (int) $run['started']))) ?></td>
        <td><?= e(match ($run['trigger']) { 'schedule' => __('Zeitplan'), 'queue' => __('Jetzt ausführen'), default => __('Kommandozeile') }) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>

<p class="f-help"><?= e(__('Erweiterungen melden ihre Aufgaben selbst an; sie laufen auf jeder Website, auf der die Erweiterung aktiv ist. Jede Aufgabe läuft als eigener Prozess mit Sperre (nie doppelt) und Zeitlimit. Kommandozeile: php bin/console cron:list, cron:exec <aufgabe>.')) ?></p>
  </div>
</div>
