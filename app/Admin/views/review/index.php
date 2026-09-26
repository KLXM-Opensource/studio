<?php
/**
 * Prüf-Ebene „Eingereicht“ (Core\Review\Queue): Einreichungen und Verlauf automatischer Änderungen über API, MCP und KI.
 * @var array $f  @var array $list  @var array $counts  @var array $sources
 */
use Core\Review\Queue;
use Core\Review\Snapshot;

$qs = fn(array $p) => url('/admin/ai/eingereicht') . '?' . http_build_query(array_filter($p + ['status' => $f['status'], 'channel' => $f['channel'],
    'token' => $f['token'], 'type' => $f['type'], 'q' => $f['q']], fn($v) => $v !== null && $v !== '' && $v !== 1));
$tabs = ['pending' => __('Offen'), 'applied' => __('Übernommen'), 'rejected' => __('Abgelehnt'), 'all' => __('Alle')];
$pending = $f['status'] === 'pending';
$filtered = $f['channel'] !== '' || $f['token'] !== '' || $f['type'] !== '' || $f['q'] !== '';
$when = fn(?string $t) => $t ? date('d.m.Y H:i', (int) strtotime($t)) : '–';
$source = function (array $r): string {
    $out = '<span class="rv-ch rv-ch--' . e($r['channel']) . '">' . e(Queue::channelLabel((string) $r['channel'])) . '</span> ';
    $name = $r['channel'] === 'ai' ? Queue::featureLabel($r['feature']) : (string) $r['token_name'];
    $out .= '<span class="rv-src__name">' . e($name !== '' ? $name : '–') . '</span>';
    if (!empty($r['client'])) $out .= '<span class="rv-src__client">' . e($r['client']) . '</span>';
    return $out;
};
$settings = app()->settings;
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Prüf-Ebene')) ?></p><h1><?= e(__('Eingereicht')) ?></h1>
    <p class="adm-muted"><?= e(__('Änderungen, die über die REST-API, den MCP-Server (KI-Assistenten) oder die KI-Funktionen entstehen. Offene Einreichungen gehen erst nach Ihrer Freigabe auf die Website; alle übrigen stehen hier als Verlauf automatischer Änderungen.')) ?></p></div>
</header>

<nav class="adm-filter rv-tabs" aria-label="<?= e(__('Status')) ?>">
  <?php foreach ($tabs as $k => $l): ?>
  <a href="<?= e($qs(['status' => $k, 'page' => null])) ?>"<?= $f['status'] === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?> <small class="rv-tabs__n"><?= (int) ($counts[$k] ?? 0) ?></small></a>
  <?php endforeach; ?>
</nav>

<form class="rv-toolbar" method="get" action="<?= e(url('/admin/ai/eingereicht')) ?>" role="search" aria-label="<?= e(__('Einreichungen filtern')) ?>">
  <input type="hidden" name="status" value="<?= e($f['status']) ?>">
  <div class="f rv-toolbar__q"><label for="rv-q"><?= e(__('Suche')) ?></label><input id="rv-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('Titel, Token, Client …')) ?>"></div>
  <div class="f"><label for="rv-ch"><?= e(__('Kanal')) ?></label><select id="rv-ch" name="channel"><option value=""><?= e(__('Alle')) ?></option>
    <?php foreach (Queue::CHANNELS as $c): ?><option value="<?= e($c) ?>"<?= $f['channel'] === $c ? ' selected' : '' ?>><?= e(Queue::channelLabel($c)) ?></option><?php endforeach; ?></select></div>
  <?php if ($sources): ?>
  <div class="f"><label for="rv-tok"><?= e(__('Token')) ?></label><select id="rv-tok" name="token"><option value=""><?= e(__('Alle')) ?></option>
    <?php foreach ($sources as $s): ?><option value="<?= (int) $s['token_id'] ?>"<?= $f['token'] === (string) $s['token_id'] ? ' selected' : '' ?>><?= e($s['name'] ?: '#' . $s['token_id']) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <div class="f"><label for="rv-type"><?= e(__('Art')) ?></label><select id="rv-type" name="type"><option value=""><?= e(__('Alle')) ?></option>
    <?php foreach (Snapshot::TYPES as $t): ?><option value="<?= e($t) ?>"<?= $f['type'] === $t ? ' selected' : '' ?>><?= e(Queue::typeLabel($t)) ?></option><?php endforeach; ?></select></div>
  <button class="adm-btn" type="submit"><?= e(__('Filtern')) ?></button>
  <?php if ($filtered): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/ai/eingereicht') . '?status=' . $f['status']) ?>"><?= e(__('Zurücksetzen')) ?></a><?php endif; ?>
</form>

<?php if (!$list['rows']): ?>
<div class="adm-card rv-empty">
  <p><b><?= e($pending ? __('Nichts zu prüfen.') : __('Keine Einträge in dieser Ansicht.')) ?></b></p>
  <p class="adm-muted"><?= e($pending ? __('Sobald ein Token im Modus „Zur Freigabe“ oder eine KI-Übernahme etwas ändern möchte, erscheint es hier.') : __('Hier stehen alle Änderungen über API, MCP und KI – mit Herkunft, Zeitpunkt und Unterschieden.')) ?></p>
  <?php if ($pending && can('api.manage')): ?><p><a href="<?= e(url('/admin/api-tokens')) ?>"><?= e(__('Tokens und ihren Modus verwalten →')) ?></a></p><?php endif; ?>
</div>
<?php else: ?>
<form method="post" action="<?= e(url('/admin/ai/eingereicht')) ?>" class="rv-listform" data-rv-bulk>
  <?= csrf_field() ?>
  <?php if ($pending): ?>
  <div class="rv-bulk" role="group" aria-label="<?= e(__('Sammelaktion')) ?>">
    <label class="rv-check"><input type="checkbox" data-rv-all> <span><?= e(__('Alle auswählen')) ?></span></label>
    <span class="adm-muted" data-rv-count aria-live="polite"></span>
    <button class="adm-btn adm-btn--small adm-btn--primary" type="submit" name="op" value="approve" data-rv-need data-confirm="<?= e(__('Ausgewählte Änderungen übernehmen? Einreichungen mit Konflikt werden übersprungen.')) ?>"><?= e(__('Ausgewählte übernehmen')) ?></button>
    <span class="rv-bulk__reject">
      <label class="sr-only" for="rv-reason"><?= e(__('Begründung (optional)')) ?></label>
      <input id="rv-reason" name="reason" maxlength="500" placeholder="<?= e(__('Begründung (optional)')) ?>">
      <button class="adm-btn adm-btn--small adm-btn--danger-text" type="submit" name="op" value="reject" data-rv-need><?= e(__('Ausgewählte ablehnen')) ?></button>
    </span>
  </div>
  <?php endif; ?>
  <div class="adm-card adm-card--flush rv-tablewrap">
  <table class="adm-table rv-table">
    <caption class="sr-only"><?= e($tabs[$f['status']] . ' · ' . __('Eingereicht')) ?></caption>
    <thead><tr>
      <?php if ($pending): ?><th scope="col" class="rv-table__sel"><span class="sr-only"><?= e(__('Auswahl')) ?></span></th><?php endif; ?>
      <th scope="col"><?= e(__('Änderung')) ?></th><th scope="col"><?= e(__('Quelle')) ?></th><th scope="col"><?= e(__('Im Namen von')) ?></th>
      <th scope="col"><?= e(__('Zeit')) ?></th><th scope="col"><?= e(__('Status')) ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($list['rows'] as $r): $rid = (int) $r['id']; ?>
      <tr class="rv-row rv-row--<?= e($r['status']) ?>">
        <?php if ($pending): ?><td class="rv-table__sel"><input type="checkbox" name="ids[]" value="<?= $rid ?>" id="rv-sel-<?= $rid ?>" aria-label="<?= e(__('#{id} auswählen', ['id' => $rid])) ?>" data-rv-item></td><?php endif; ?>
        <td><a class="rv-row__title" href="<?= e(url('/admin/ai/eingereicht/' . $rid)) ?>"><?php if (!in_array($r['entity_type'], ['settings', 'design'], true)): ?><span class="rv-row__type"><?= e(Queue::typeLabel((string) $r['entity_type'])) ?></span> <?php endif; ?><?= e($r['entity_label'] ?: '–') ?></a>
          <span class="rv-row__sum"><span class="rv-row__no">#<?= $rid ?></span> <?= e((string) $r['summary']) ?></span>
          <?php if (!empty($r['error']) && $r['status'] === 'pending'): ?><span class="rv-row__err"><?= e(__('Hinweis: {msg}', ['msg' => $r['error']])) ?></span><?php endif; ?></td>
        <td class="rv-src"><?= $source($r) ?></td>
        <td><?= e($r['user_name'] ?: ($r['user_email'] ?: '–')) ?></td>
        <td class="rv-when"><time datetime="<?= e(date('c', (int) strtotime((string) $r['created_at']))) ?>"><?= e($when($r['created_at'])) ?></time></td>
        <td><span class="rv-st rv-st--<?= e($r['status']) ?>"><?= e(Queue::statusLabel((string) $r['status'])) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</form>
<?php if ($list['pages'] > 1): ?>
<nav class="dt-pager" aria-label="<?= e(__('Seiten')) ?>">
  <?php if ($list['page'] > 1): ?><a href="<?= e($qs(['page' => $list['page'] - 1])) ?>">← <?= e(__('Zurück')) ?></a><?php endif; ?>
  <?= e(__('Seite {page} / {pages}', ['page' => $list['page'], 'pages' => $list['pages']])) ?>
  <?php if ($list['page'] < $list['pages']): ?><a href="<?= e($qs(['page' => $list['page'] + 1])) ?>"><?= e(__('Weiter')) ?> →</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>

<form class="adm-card rv-settings" id="rv-settings" method="post" action="<?= e(url('/admin/ai/eingereicht/einstellungen')) ?>">
  <?= csrf_field() ?>
  <h2><?= e(__('Einstellungen der Freigabe')) ?></h2>
  <div class="adm-checks">
    <label class="rv-check"><input type="checkbox" name="notify" value="1"<?= $settings->get('sys.review_notify', false) ? ' checked' : '' ?> aria-describedby="rv-notify-help"> <span><?= e(__('E-Mail bei neuen Einreichungen')) ?></span></label>
    <p class="f-help" id="rv-notify-help"><?= e(__('An alle Konten mit dem Recht „Eingereichte Änderungen prüfen“ – höchstens alle 15 Minuten.')) ?></p>
    <label class="rv-check"><input type="checkbox" name="ai" value="1"<?= $settings->get('sys.review_ai', false) ? ' checked' : '' ?> aria-describedby="rv-ai-help"> <span><?= e(__('KI-Übernahmen ebenfalls zur Freigabe')) ?></span></label>
    <p class="f-help" id="rv-ai-help"><?= e(__('Aus: Übernimmt jemand einen KI-Vorschlag, wird er gespeichert und hier protokolliert (ein Mensch hat bereits geprüft). An: Texte, Übersetzungen, SEO-Beschreibungen und Alt-Texte aus dem KI-Assistenten warten hier auf eine zweite Freigabe. Seiten- und Tabellen-Generator legen immer unveröffentlichte Entwürfe an.')) ?></p>
  </div>
  <p class="adm-muted"><?= e(__('Ob API- und MCP-Zugänge direkt ändern oder einreichen, legen Sie je Token unter „API & MCP“ fest.')) ?><?php if (can('api.manage')): ?> <a href="<?= e(url('/admin/api-tokens')) ?>"><?= e(__('Zu API & MCP →')) ?></a><?php endif; ?></p>
  <button class="adm-btn" type="submit"><?= e(__('Speichern')) ?></button>
</form>
