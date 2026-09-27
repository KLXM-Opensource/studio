<?php
/**
 * Anfragen: Eingangs-Tabellen (Ende-zu-Ende verschlüsselt). Liste = Metadaten; Inhalte nur nach „Entschlüsseln“ für diese Ansicht.
 * @var array $tables  @var ?array $t  @var array $rows  @var array $decrypted  @var ?string $keyError  @var bool $unlocked
 * @var string $status  @var int $page  @var int $pages  @var array $counts  @var array $newCounts  @var array $users  @var bool $canManage  @var bool $canLog
 */
use Core\Data\Inbox;

$title = __('Anfragen');
$qs = fn(array $p) => url('/admin/requests') . '?' . http_build_query(array_filter($p + ['table' => $t['handle'] ?? null, 'status' => $status ?? null], fn($v) => $v !== null && $v !== ''));
$userNames = array_column($users ?? [], null, 'id');
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Online-Services')) ?></p><h1><?= e(__('Anfragen')) ?></h1>
    <p class="adm-muted"><?= e(__('Inhalte sind Ende-zu-Ende verschlüsselt gespeichert. Zum Lesen den geheimen {key} eingeben – er wird weder gespeichert noch protokolliert.', ['key' => term('key')])) ?></p></div>
  <?php if ($t && can('data.schema')): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/data/' . $t['handle'] . '/schema')) ?>"><?= e(__('Felder & Einstellungen')) ?></a><?php endif; ?>
</header>

<?php if (!$tables): ?>
<div class="adm-card">
  <p><?= e(__('Es gibt noch keine Eingangs-Tabelle, die Sie lesen dürfen.')) ?></p>
  <?php if (can('data.schema')): ?><p><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/data/new?preset=inbox')) ?>"><?= e(__('Eingang anlegen')) ?></a></p><?php endif; ?>
</div>
<?php if ($canLog): ?><p><a href="<?= e(url('/admin/requests/log')) ?>"><?= e(__('Protokoll')) ?></a></p><?php endif; ?>
<?php return; endif; ?>

<nav class="adm-filter adm-inbox-tabs" aria-label="<?= e(__('Eingänge')) ?>">
  <?php foreach ($tables as $x): ?>
  <a href="<?= e(url('/admin/requests?table=' . rawurlencode($x['handle']))) ?>"<?= $x['handle'] === $t['handle'] ? ' aria-current="page"' : '' ?>><span aria-hidden="true"><?= icon($x['icon']) ?></span> <?= e($x['name']) ?><?php if ($newCounts[$x['handle']] ?? 0): ?> <span class="adm-count" title="<?= e(__('neu')) ?>"><?= (int) $newCounts[$x['handle']] ?></span><?php endif; ?></a>
  <?php endforeach; ?>
  <?php if ($canLog): ?><a class="adm-inbox-tabs__log" href="<?= e(url('/admin/requests/log')) ?>"><?= e(__('Protokoll')) ?></a><?php endif; ?>
</nav>

<?php if (!$keyReady): ?>
<p class="adm-flash adm-flash--error" role="note"><?= e(__('Es ist kein öffentlicher Schlüssel hinterlegt – die Formulare nehmen keine Anfragen an.')) ?> <a href="<?= e(url('/admin/system#keys')) ?>"><?= e(__('Schlüssel erzeugen')) ?></a></p>
<?php endif; ?>

<?php $tableNames = array_column($tables, 'name', 'handle'); if (!empty($deliveryAlerts)): ?>
<div class="adm-flash adm-flash--error" role="alert">
  <p><b><?= e(__('Zustellung fehlgeschlagen – Anfrage verschlüsselt gesichert')) ?></b></p>
  <ul><?php foreach (array_slice($deliveryAlerts, 0, 5) as $a): ?>
    <li><?= e(date('d.m.Y H:i', strtotime((string) $a['at']))) ?> · <?= e($tableNames[$a['table']] ?? $a['table']) ?> · <span class="adm-mono"><?= e((string) $a['ref']) ?></span> – <?= e((string) $a['reason']) ?><?= empty($a['stored']) ? ' – <b>' . e(__('NICHT gesichert')) . '</b>' : '' ?></li>
  <?php endforeach; ?></ul>
  <?php if ($canManage): ?><form method="post" action="<?= e(url('/admin/requests/alerts/clear')) ?>"><?= csrf_field() ?><input type="hidden" name="table" value="<?= e($t['handle']) ?>">
    <button class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Gelesen – Hinweise ausblenden')) ?></button></form><?php endif; ?>
</div>
<?php endif; ?>
<?php if (($delivery ?? 'system') !== 'system'): ?>
<p class="adm-flash adm-flash--info" role="note"><?= e(\Core\Data\Delivery::modeLabel($delivery)) ?>: <?= e($delivery === 'mail'
    ? __('Anfragen dieses Eingangs gehen nur per E-Mail hinaus. Hier erscheinen nur Anfragen, deren Zustellung fehlgeschlagen ist (verschlüsselt gesichert).')
    : __('Anfragen werden hier gespeichert und zusätzlich mit vollem Inhalt per E-Mail zugestellt.')) ?>
  <?php foreach (array_filter($deliveryProblems ?? [], fn($p) => $p['level'] === 'error') as $p): ?><br><b><?= e($p['text']) ?></b><?php endforeach; ?></p>
<?php endif; ?>

<form class="adm-card adm-unlock" method="post" action="<?= e($qs(['seite' => $page > 1 ? $page : null])) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="table" value="<?= e($t['handle']) ?>">
  <div class="f<?= $keyError ? ' f--error' : '' ?>"><label for="secret"><?= e(__('Geheimer {key}', ['key' => term('key')])) ?></label>
    <input id="secret" name="secret" type="password" autocomplete="off" spellcheck="false"<?= $keyError ? ' aria-invalid="true" aria-describedby="secret-e"' : '' ?>>
    <?php if ($keyError): ?><p class="f-error" id="secret-e"><?= e($keyError) ?></p><?php endif; ?></div>
  <button class="adm-btn adm-btn--primary" type="submit"><?= e($unlocked ? __('Erneut entschlüsseln') : __('Entschlüsseln')) ?></button>
  <?php if ($unlocked): ?><span class="adm-badge"><?= e(__('Entsperrt für diese Ansicht')) ?></span><?php endif; ?>
</form>

<p class="adm-filter" aria-label="<?= e(__('Status')) ?>">
  <?php $statuses = Inbox::statuses($t); foreach ([...array_keys($statuses), 'alle'] as $k): ?>
  <a href="<?= e($qs(['status' => $k, 'seite' => null])) ?>"<?= $status === $k ? ' aria-current="true"' : '' ?>><?= e($k === 'alle' ? __('Alle') : Inbox::statusLabel($k, $t)) ?> <small><?= (int) ($counts[$k] ?? 0) ?></small></a>
  <?php endforeach; ?>
</p>

<div class="adm-requests">
  <?php foreach ($rows as $row): $data = $decrypted[$row['id']] ?? null; $rid = 'req-' . (int) $row['id']; ?>
  <article class="adm-card adm-request<?= $row['status'] === 'neu' ? ' is-new' : '' ?>" id="<?= $rid ?>">
    <header>
      <strong><?= e($t['singular']) ?> <span class="adm-mono"><?= e((string) $row['ref']) ?></span></strong>
      <span class="adm-muted"><?= e(date('d.m.Y H:i', strtotime((string) $row['created_at']))) ?> · #<?= (int) $row['id'] ?></span>
      <?php $tone = $statuses[$row['status']]['tone'] ?? ''; ?><span class="adm-badge<?= $tone === 'warn' ? ' adm-badge--adm-warn' : ($tone === 'muted' ? ' adm-badge--muted' : '') ?>"><?= e(Inbox::statusLabel($row['status'], $t)) ?></span>
      <?php if ($row['legacy']): ?><span class="adm-badge adm-badge--draft" title="<?= e(__('Aus den früheren Online-Anfragen übernommen')) ?>"><?= e(__('übernommen')) ?></span><?php endif; ?>
      <?php if (!empty($row['lang'])): ?><span class="adm-badge adm-badge--draft"><?= e(strtoupper((string) $row['lang'])) ?></span><?php endif; ?>
      <?php if ($row['assignee']): $au = $userNames[$row['assignee']] ?? null; ?><span class="adm-muted">→ <?= e($au ? ($au['name'] ?: $au['email']) : '#' . $row['assignee']) ?></span><?php endif; ?>
    </header>
    <?php if (($info = Inbox::info($t, $row)) !== ''): /* Zusatzzeile der Erweiterung (z. B. Buchung: Objekt, Zeitraum) */ ?><p class="adm-muted adm-request__info"><?= e($info) ?></p><?php endif; ?>
    <?php if ($data): ?>
      <?php if (($data['delivery'] ?? '') === 'fallback'): ?><p class="adm-badge adm-badge--adm-warn"><?= e(__('Zustellung per E-Mail fehlgeschlagen – hier gesichert')) ?></p><?php endif; ?>
      <dl class="adm-dl adm-dl--request">
        <?php foreach ($data['fields'] as $f): ?><dt><?= e($f['label']) ?></dt><dd><?php if (!empty($f['file'])): /* Datei (Zustellung per E-Mail, versiegelt) */ ?>
          <a href="data:<?= e(preg_match('~^[a-z]+/[a-z0-9.+-]+$~', (string) $f['file']['type']) ? (string) $f['file']['type'] : 'application/octet-stream') ?>;base64,<?= e((string) $f['file']['data']) ?>" download="<?= e((string) $f['file']['file']) ?>"><?= e($f['value']) ?></a>
        <?php elseif (!empty($f['table'])): /* Wiederholbare Gruppe */ ?>
          <table class="adm-inbox-group"><thead><tr><th scope="col"><span class="sr-only"><?= e(__('Nr.')) ?></span></th><?php foreach ($f['table']['cols'] as $c): ?><th scope="col"><?= e($c) ?></th><?php endforeach; ?></tr></thead>
            <tbody><?php foreach ($f['table']['rows'] as $ri => $cells): ?><tr><th scope="row"><?= $ri + 1 ?></th><?php foreach ($cells as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
        <?php else: ?><?= nl2br(e($f['value']), false) ?><?php endif; ?></dd><?php endforeach; ?>
      </dl>
      <pre id="<?= $rid ?>-copy" hidden><?= e($t['singular'] . ' ' . $row['ref'] . ' · ' . date('d.m.Y H:i', strtotime((string) $row['created_at'])) . "\n" . implode("\n", array_map(fn($f) => $f['label'] . ':' . (!empty($f['table']) ? "\n" : ' ') . $f['value'], $data['fields']))) ?></pre>
      <p class="adm-row adm-inbox-export">
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-print="#<?= $rid ?>"><?= e(__('Drucken')) ?></button>
        <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-copy="#<?= $rid ?>-copy"><?= e(__('Kopieren')) ?></button>
      </p>
    <?php elseif ($unlocked): ?>
      <p class="adm-flash adm-flash--error"><?= e(__('Diese Anfrage konnte mit dem Schlüssel nicht entschlüsselt werden (evtl. mit einem älteren Schlüssel verschlüsselt).')) ?></p>
    <?php else: ?>
      <p class="adm-muted"><?= icon('lock') ?> <?= e(__('Verschlüsselt')) ?></p>
    <?php endif; ?>
    <?php if ($canManage): ?>
    <div class="adm-row adm-inbox-actions">
      <?php foreach ($statuses as $st => $sd): if ($st === $row['status'] || !$sd['manual']) continue; ?>
      <form method="post" action="<?= e(url('/admin/requests/' . $t['handle'] . '/' . $row['id'] . '/status')) ?>"><?= csrf_field() ?>
        <input type="hidden" name="status" value="<?= e($st) ?>"><input type="hidden" name="back" value="<?= e($status) ?>">
        <button class="adm-btn adm-btn--small"><?= e($sd['action']) ?></button></form>
      <?php endforeach; ?>
      <?php if (count($users) > 0): ?>
      <form method="post" action="<?= e(url('/admin/requests/' . $t['handle'] . '/' . $row['id'] . '/assign')) ?>" class="adm-inbox-assign"><?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e($status) ?>">
        <label class="sr-only" for="<?= $rid ?>-as"><?= e(__('Zuweisen an')) ?></label>
        <select id="<?= $rid ?>-as" name="user"><option value=""><?= e(__('– niemand –')) ?></option>
          <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= (int) $u['id'] === (int) $row['assignee'] ? ' selected' : '' ?>><?= e($u['name'] ?: $u['email']) ?></option><?php endforeach; ?>
        </select>
        <button class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Zuweisen')) ?></button></form>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/admin/requests/' . $t['handle'] . '/' . $row['id'] . '/delete')) ?>" data-confirm="<?= e(__('Anfrage endgültig löschen?')) ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($status) ?>">
        <button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Löschen')) ?></button></form>
    </div>
    <?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php if (!$rows): ?><p class="adm-card adm-muted"><?= e(__('Keine Anfragen in dieser Ansicht.')) ?></p><?php endif; ?>
</div>
<?php if ($pages > 1): ?>
<p class="dt-pager">
  <?php if ($page > 1): ?><a href="<?= e($qs(['seite' => $page - 1])) ?>">← <?= e(__('Zurück')) ?></a><?php endif; ?>
  <?= e(__('Seite {page} / {pages}', ['page' => $page, 'pages' => $pages])) ?>
  <?php if ($page < $pages): ?><a href="<?= e($qs(['seite' => $page + 1])) ?>"><?= e(__('Weiter')) ?> →</a><?php endif; ?>
</p>
<?php endif; ?>
<?php $days = (int) Inbox::config($t)['retention_days']; ?>
<p class="adm-muted"><?= e($days > 0 ? __('Datenschutz: Erledigte Anfragen werden nach {days} Tagen automatisch gelöscht.', ['days' => $days]) : __('Datenschutz: Erledigte Anfragen bitte regelmäßig löschen (Datensparsamkeit).')) ?>
  <?= e(__('Übernehmen Sie Inhalte bei Bedarf in {system}. Drucken und Kopieren geschieht nur im Browser – auf dem Server entsteht keine Klartext-Datei.', ['system' => term('records_system')])) ?></p>
