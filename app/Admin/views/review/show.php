<?php
/**
 * Prüf-Ebene: eine Einreichung bzw. protokollierte Änderung mit Herkunft, Unterschieden (Feld- und Blockebene), Konfliktprüfung und Aktionen.
 * @var array $row (dekodiert: payload, target, before, after, diff)  @var ?array $conflict  @var bool $edit
 */
use Core\Review\Diff;
use Core\Review\Queue;
use Core\Review\Snapshot;

$id = (int) $row['id'];
$pending = $row['status'] === 'pending';
$target = (array) $row['target'];
$method = (string) ($row['payload']['method'] ?? $row['action']);
$args = array_values((array) ($row['payload']['args'] ?? []));
$diff = (array) ($row['diff'] ?? []);
$when = fn(?string $t) => $t ? date('d.m.Y H:i', (int) strtotime($t)) : '–';
$isPageBlocks = ($target['type'] ?? '') === 'page' && is_array($row['after']['blocks'] ?? null);
$editArg = Queue::EDIT_ARG[$method] ?? null;
$editable = $pending && $editArg !== null && is_array($args[$editArg] ?? null) ? array_filter($args[$editArg], 'is_string') : [];
$labels = Snapshot::labels($target);
$newLabel = $pending ? __('Vorschlag') : __('Nachher');

// Link zum Gegenstand in der Verwaltung
$link = null;
$tid = $target['id'] ?? null;
if (($target['type'] ?? '') === 'page' && $tid && ($p = \Core\Pages::find((int) $tid))) $link = [url('/admin/pages/' . (int) $tid), __('Seiteneinstellungen')];
if (($target['type'] ?? '') === 'entry' && $tid && \Core\Data\Tables::find((string) $target['table'])) $link = [url('/admin/data/' . $target['table'] . '/' . (int) $tid), __('Eintrag öffnen')];
if (($target['type'] ?? '') === 'media') $link = [url('/admin/media'), __('Mediathek')];
if (($target['type'] ?? '') === 'settings') $link = [url('/admin/settings'), app()->theme->settingsTitle()];
if (($target['type'] ?? '') === 'design') $link = [url('/admin/design'), __('Design')];
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url('/admin/ai/eingereicht' . ($pending ? '' : '?status=all'))) ?>"><?= e(__('Eingereicht')) ?></a> · #<?= $id ?></p>
    <h1><?= e($row['entity_label'] ?: Queue::typeLabel((string) $row['entity_type'])) ?></h1>
    <p class="rv-head__sum"><span class="rv-st rv-st--<?= e($row['status']) ?>"><?= e(Queue::statusLabel((string) $row['status'])) ?></span> <?= e((string) $row['summary']) ?></p></div>
  <?php if ($link): ?><a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e($link[0]) ?>"><?= e($link[1]) ?></a><?php endif; ?>
</header>

<?php if ($pending && $conflict): ?>
<section class="adm-card rv-alert rv-alert--warn" role="alert" aria-labelledby="rv-conf-h">
  <h2 id="rv-conf-h"><?= e(__('Konflikt: seit der Einreichung geändert')) ?></h2>
  <p><?= e(__('Der Inhalt wurde nach der Einreichung von jemand anderem geändert. Vergleichen Sie die drei Stände und prüfen Sie die Einreichung neu – dann wird sie auf den aktuellen Stand angewendet und der Unterschied neu berechnet.')) ?></p>
  <?php
  $rows3 = [];
  foreach ((array) $conflict['diff'] as $c) $rows3[$c['path']] = ['label' => $c['label'], 'type' => $c['type'], 'current' => $c['type'] === 'blocks' ? count($c['items']) : $c['after']];
  ?>
  <div class="rv-tablewrap"><table class="adm-table rv-3way">
    <caption class="sr-only"><?= e(__('Drei Stände im Vergleich')) ?></caption>
    <thead><tr><th scope="col"><?= e(__('Feld')) ?></th><th scope="col"><?= e(__('Bei Einreichung')) ?></th><th scope="col"><?= e(__('Jetzt')) ?></th><th scope="col"><?= e(__('Vorschlag')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows3 as $path => $c): ?>
      <tr><th scope="row"><?= e($c['label']) ?></th>
        <?php if ($c['type'] === 'blocks'): ?>
        <td colspan="3"><?= e($c['current'] === 1 ? __('1 Block seit der Einreichung geändert – Einzelheiten nach „Neu prüfen“.') : __('{n} Blöcke seit der Einreichung geändert – Einzelheiten nach „Neu prüfen“.', ['n' => $c['current']])) ?></td>
        <?php else: ?>
        <td><?= e(mb_strimwidth(Diff::text($row['before'][$path] ?? null), 0, 300, ' …')) ?></td>
        <td class="rv-3way__now"><?= e(mb_strimwidth(Diff::text($c['current']), 0, 300, ' …')) ?></td>
        <td><?php $prop = $row['after'][$path] ?? null; ?><?= json_encode($prop) === json_encode($row['before'][$path] ?? null) ? '<span class="adm-muted">' . e(__('unverändert')) . '</span>' : e(mb_strimwidth(Diff::text($prop), 0, 300, ' …')) ?></td>
        <?php endif; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <form method="post" action="<?= e(url('/admin/ai/eingereicht/' . $id . '/pruefen')) ?>"><?= csrf_field() ?><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Neu prüfen')) ?></button></form>
</section>
<?php endif; ?>
<?php if ($pending && !empty($row['error'])): ?>
<p class="adm-flash adm-flash--error" role="alert"><?= e(__('Letzter Versuch: {msg}', ['msg' => $row['error']])) ?></p>
<?php endif; ?>

<div class="rv-layout">
  <section class="adm-card rv-changes" aria-labelledby="rv-diff-h">
    <div class="rv-changes__head">
      <h2 id="rv-diff-h"><?= e($pending ? __('Vorgeschlagene Änderungen') : __('Änderungen')) ?></h2>
      <?php if ($isPageBlocks && $pending): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/ai/eingereicht/' . $id . '/vorschau')) ?>" target="_blank" rel="noopener"><?= e(__('Vorschau des Vorschlags')) ?> <span aria-hidden="true">↗</span><span class="sr-only"><?= e(__('(neues Fenster)')) ?></span></a><?php endif; ?>
    </div>
    <?php if (in_array($method, ['pageDelete', 'dataDelete', 'mediaDelete'], true)): ?>
    <p class="rv-alert rv-alert--danger"><?= e(__('Diese Aktion löscht den Gegenstand endgültig.')) ?></p>
    <?php endif; ?>
    <?php if (!$diff): ?>
    <p class="adm-muted"><?= e(__('Keine inhaltlichen Unterschiede (z. B. Veröffentlichen ohne Änderungen oder bereits gleicher Stand).')) ?></p>
    <?php endif; ?>
    <?= \Core\Theme::capture(__DIR__ . '/_diff.php', ['diff' => $diff, 'newLabel' => $newLabel]) ?>
  </section>

  <aside class="rv-side">
    <section class="adm-card" aria-labelledby="rv-meta-h">
      <h2 id="rv-meta-h"><?= e(__('Herkunft')) ?></h2>
      <dl class="adm-dl rv-dl">
        <dt><?= e(__('Aktion')) ?></dt><dd><?= e(Queue::actionLabel($method)) ?> <small class="adm-muted">(<?= e(Queue::typeLabel((string) $row['entity_type'])) ?><?= $row['entity_id'] ? ' ' . e((string) $row['entity_id']) : '' ?>)</small></dd>
        <dt><?= e(__('Kanal')) ?></dt><dd><span class="rv-ch rv-ch--<?= e($row['channel']) ?>"><?= e(Queue::channelLabel((string) $row['channel'])) ?></span></dd>
        <?php if ($row['channel'] === 'ai'): ?><dt><?= e(__('KI-Funktion')) ?></dt><dd><?= e(Queue::featureLabel($row['feature']) ?: '–') ?></dd>
        <?php else: ?><dt><?= e(__('Token')) ?></dt><dd><?= e($row['token_name'] ?: '–') ?><?= $row['token_id'] ? ' <small class="adm-muted">#' . (int) $row['token_id'] . '</small>' : '' ?></dd><?php endif; ?>
        <?php if (!empty($row['client'])): ?><dt><?= e(__('Client')) ?></dt><dd><?= e($row['client']) ?></dd><?php endif; ?>
        <dt><?= e(__('Im Namen von')) ?></dt><dd><?= e($row['user_name'] ?: ($row['user_email'] ?: '–')) ?></dd>
        <dt><?= e($pending ? __('Eingereicht') : __('Zeitpunkt')) ?></dt><dd><?= e($when($row['created_at'])) ?></dd>
        <?php if (!empty($row['lang'])): ?><dt><?= e(__('Sprache')) ?></dt><dd><?= e(\Core\Lang::all()[$row['lang']] ?? $row['lang']) ?></dd><?php endif; ?>
        <?php if (!empty($row['checked_at'])): ?><dt><?= e(__('Neu geprüft')) ?></dt><dd><?= e($when($row['checked_at'])) ?></dd><?php endif; ?>
        <?php if ($row['reviewed_at']): ?>
        <dt><?= e($row['status'] === 'rejected' ? __('Abgelehnt von') : __('Freigegeben von')) ?></dt><dd><?= e($row['reviewer_name'] ?: ($row['reviewer_email'] ?: '–')) ?> · <?= e($when($row['reviewed_at'])) ?></dd>
        <?php endif; ?>
        <?php if ((string) $row['reason'] !== ''): ?><dt><?= e($row['status'] === 'rejected' ? __('Begründung') : __('Hinweis')) ?></dt><dd><?= nl2br(e((string) $row['reason'])) ?></dd><?php endif; ?>
      </dl>
    </section>

    <?php if ($pending): ?>
    <section class="adm-card rv-actions" aria-labelledby="rv-act-h">
      <h2 id="rv-act-h"><?= e(__('Entscheidung')) ?></h2>
      <?php if ($edit && $editable): ?>
      <form method="post" action="<?= e(url('/admin/ai/eingereicht/' . $id . '/bearbeiten')) ?>" class="rv-edit">
        <?= csrf_field() ?>
        <p class="adm-muted"><?= e(__('Werte anpassen und übernehmen. Gespeichert wird über die normalen Prüfungen.')) ?></p>
        <?php foreach ($editable as $k => $v): $fid = 'rv-e-' . preg_replace('~[^a-z0-9_-]~i', '', (string) $k); $long = mb_strlen($v) > 80 || str_contains($v, "\n") || $v !== strip_tags($v); ?>
        <div class="f"><label for="<?= e($fid) ?>"><?= e($labels[$k] ?? (string) $k) ?></label>
          <?php if ($long): ?><textarea id="<?= e($fid) ?>" name="values[<?= e((string) $k) ?>]" rows="<?= min(12, max(3, (int) ceil(mb_strlen($v) / 70))) ?>"><?= e($v) ?></textarea>
          <?php else: ?><input id="<?= e($fid) ?>" name="values[<?= e((string) $k) ?>]" value="<?= e($v) ?>"><?php endif; ?></div>
        <?php endforeach; ?>
        <div class="rv-actions__row"><button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Bearbeitet übernehmen')) ?></button>
          <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/ai/eingereicht/' . $id)) ?>"><?= e(__('Abbrechen')) ?></a></div>
      </form>
      <?php else: ?>
      <form method="post" action="<?= e(url('/admin/ai/eingereicht/' . $id . '/uebernehmen')) ?>"><?= csrf_field() ?>
        <button class="adm-btn adm-btn--primary adm-btn--block" type="submit"<?= $conflict ? ' disabled aria-describedby="rv-conf-h"' : '' ?>><?= e(__('Übernehmen')) ?></button></form>
      <?php if ($editable): ?>
      <a class="adm-btn adm-btn--block" href="<?= e(url('/admin/ai/eingereicht/' . $id . '?bearbeiten=1')) ?>"><?= e(__('Bearbeiten & übernehmen')) ?></a>
      <?php elseif ($isPageBlocks): ?>
      <form method="post" action="<?= e(url('/admin/ai/eingereicht/' . $id . '/entwurf')) ?>"><?= csrf_field() ?>
        <button class="adm-btn adm-btn--block" type="submit"<?= $conflict ? ' disabled' : '' ?>><?= e(__('Als Entwurf übernehmen & im Editor bearbeiten')) ?></button></form>
      <?php endif; ?>
      <?php if (!$conflict): ?>
      <form method="post" action="<?= e(url('/admin/ai/eingereicht/' . $id . '/pruefen')) ?>"><?= csrf_field() ?>
        <button class="adm-btn adm-btn--ghost adm-btn--block adm-btn--small" type="submit"><?= e(__('Gegen aktuellen Stand neu prüfen')) ?></button></form>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/admin/ai/eingereicht/' . $id . '/ablehnen')) ?>" class="rv-reject">
        <?= csrf_field() ?>
        <div class="f"><label for="rv-reason"><?= e(__('Begründung für die Ablehnung')) ?></label>
          <textarea id="rv-reason" name="reason" rows="3" maxlength="2000" aria-describedby="rv-reason-help"></textarea>
          <p class="f-help" id="rv-reason-help"><?= e(__('Optional – die Quelle erhält sie über die Status-Abfrage (API/MCP).')) ?></p></div>
        <button class="adm-btn adm-btn--danger-text adm-btn--block" type="submit"><?= e(__('Ablehnen')) ?></button>
      </form>
      <?php endif; ?>
    </section>
    <?php endif; ?>
  </aside>
</div>
