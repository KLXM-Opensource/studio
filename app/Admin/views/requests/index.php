<?php
/**
 * Anfragen (Eingangs-Tabellen, Ende-zu-Ende verschlüsselt) – Aufbau wie ein Mailprogramm bzw. Feedback:
 *   links   Postfächer (Seitenleiste, requests/_nav.php: Eingänge, Status, Einrichtung)
 *   Mitte   Liste der Anfragen mit Suche und „Entschlüsseln“ (Metadaten; nach dem Entschlüsseln Name und Auszug)
 *   rechts  gewählte Anfrage mit Aktionen (Status, Zuweisen, Drucken, Kopieren, Löschen)
 * Inhalte gibt es nur in der Antwort auf „Entschlüsseln“ (POST, Schlüssel weder gespeichert noch protokolliert): Alle Anfragen der
 * Seite stehen dann im HTML, resources/js/_requests.js wechselt zwischen ihnen ohne Neuladen und schickt Aktionen per fetch –
 * so bleibt die Ansicht entsperrt. Ohne JavaScript: Links (?id=) und normale Formulare. Schmal: Liste → Anfrage mit „‹ Zurück“.
 * @var array $tables  @var ?array $t  @var array $rows  @var array $decrypted  @var ?string $keyError  @var bool $unlocked
 * @var string $status  @var int $page  @var int $pages  @var array $counts  @var array $newCounts  @var array $users  @var bool $canManage
 * @var bool $canLog  @var int $selId  @var bool $hasSel
 */
use Core\Data\Inbox;

$title = __('Anfragen');
$qs = fn(array $p) => url('/admin/requests') . '?' . http_build_query(array_filter($p + ['table' => $t['handle'] ?? null, 'status' => $status ?? null], fn($v) => $v !== null && $v !== ''));
$userNames = array_column($users ?? [], null, 'id');
$fmt = \Core\Format::admin();
?>
<?php if (!$tables): ?>
<header class="adm-head"><div><p class="adm-eyebrow"><?= e(__('Online-Services')) ?></p><h1><?= e(__('Anfragen')) ?></h1></div></header>
<div class="adm-card">
  <p><?= e(__('Es gibt noch keine Eingangs-Tabelle, die Sie lesen dürfen.')) ?></p>
  <?php if (can('data.schema')): ?><p><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/data/new')) ?>"><?= e(__('Eingang anlegen')) ?></a></p><?php endif; ?>
</div>
<?php if ($canLog): ?><p><a href="<?= e(url('/admin/requests/log')) ?>"><?= e(__('Protokoll')) ?></a></p><?php endif; ?>
<?php return; endif;

$statuses = Inbox::statuses($t);
$tableNames = array_column($tables, 'name', 'handle');
$n = count($rows);
// Name und Auszug einer entschlüsselten Anfrage für die Liste (erste kurze Felder = Name, der Rest als Auszug)
$summary = function (?array $data) use ($t): array {
    if (!$data) return ['', ''];
    $vals = array_values(array_filter($data['fields'], fn($f) => empty($f['file']) && empty($f['table']) && trim((string) $f['value']) !== ''));
    $name = [];
    foreach (array_slice($vals, 0, 2) as $f) {
        $v = trim((string) $f['value']);
        if (mb_strlen($v) > 40 || str_contains($v, "\n") || str_contains($v, '@')) break;
        $name[] = $v;
    }
    $rest = array_slice($vals, count($name));
    $ex = implode(' · ', array_map(fn($f) => preg_replace('~\s+~u', ' ', trim((string) $f['value'])), $rest));
    return [implode(' ', $name), mb_strimwidth($ex, 0, 180, '…')];
};
$pill = fn(string $st) => '<span class="rq-pill rq-pill--' . e((string) ($statuses[$st]['tone'] ?? '')) . '" data-rq-pill>' . e(Inbox::statusLabel($st, $t)) . '</span>';
$boxTitle = $status === 'alle' ? __('Alle') : Inbox::statusLabel($status, $t);
?>
<h1 class="adm-sr"><?= e(__('Anfragen')) ?> – <?= e($t['name']) ?></h1>

<?php if (!$keyReady): ?>
<p class="adm-flash adm-flash--error" role="note"><?= e(__('Es ist kein öffentlicher Schlüssel hinterlegt – die Formulare nehmen keine Anfragen an.')) ?> <a href="<?= e(url('/admin/system#keys')) ?>"><?= e(__('Schlüssel erzeugen')) ?></a></p>
<?php endif; ?>
<?php if (!empty($deliveryAlerts)): ?>
<div class="adm-flash adm-flash--error" role="alert">
  <p><b><?= e(__('Zustellung fehlgeschlagen – Anfrage verschlüsselt gesichert')) ?></b></p>
  <ul><?php foreach (array_slice($deliveryAlerts, 0, 5) as $a): ?>
    <li><?= e(date('d.m.Y H:i', strtotime((string) $a['at']))) ?> · <?= e($tableNames[$a['table']] ?? $a['table']) ?> · <span class="adm-mono"><?= e((string) $a['ref']) ?></span> – <?= e((string) $a['reason']) ?><?= empty($a['stored']) ? ' – <b>' . e(__('NICHT gesichert')) . '</b>' : '' ?></li>
  <?php endforeach; ?></ul>
  <?php if ($canManage): ?><form method="post" action="<?= e(url('/admin/requests/alerts/clear')) ?>"><?= csrf_field() ?><input type="hidden" name="table" value="<?= e($t['handle']) ?>">
    <button class="adm-btn adm-btn--small adm-btn--ghost"><?= e(__('Gelesen – Hinweise ausblenden')) ?></button></form><?php endif; ?>
</div>
<?php endif; ?>

<div class="rq-inbox<?= $hasSel && $selId ? ' is-reading' : '' ?>" data-rq data-rq-base="<?= e(url('/admin/requests')) ?>"
  data-rq-t-count="<?= e(__('{n} Anfragen')) ?>" data-rq-t-count1="<?= e(__('1 Anfrage')) ?>" data-rq-t-nomatch="<?= e(__('Keine Anfrage passt zu „{q}“.')) ?>"
  data-rq-t-error="<?= e(__('Das hat nicht geklappt. Bitte erneut versuchen.')) ?>" data-rq-t-nobody="<?= e(__('– niemand –')) ?>">
  <section class="rq-col rq-col--list" aria-labelledby="rq-list-h">
    <div class="rq-bar" data-fav-slot="adm-btn adm-btn--small adm-btn--ghost rq-favbtn">
      <div class="rq-bar__title"><h2 id="rq-list-h"><?= e($t['name']) ?> · <?= e($boxTitle) ?></h2>
        <small data-rq-count aria-live="polite"><?= e($n === 1 ? __('1 Anfrage') : __('{n} Anfragen', ['n' => $n])) ?><?= $pages > 1 ? ' · ' . e(__('Seite {page} / {pages}', ['page' => $page, 'pages' => $pages])) : '' ?></small></div>
      <?php if ($unlocked): ?><span class="rq-lockstate is-open" title="<?= e(__('Entsperrt für diese Ansicht – nach dem Neuladen wieder verschlossen')) ?>"><?= icon('key') ?> <span><?= e(__('Entsperrt')) ?></span></span>
      <?php else: ?><span class="rq-lockstate" title="<?= e(__('Verschlüsselt')) ?>"><?= icon('lock') ?> <span><?= e(__('Verschlüsselt')) ?></span></span><?php endif; ?>
    </div>

    <?php if (!empty($envKey)): ?>
    <p class="rq-note"><?= e(__('Automatisch entschlüsselt: Der geheime {key} ist in der Hosting-Umgebung hinterlegt.', ['key' => term('key')])) ?></p>
    <?php elseif (!$unlocked): ?>
    <form class="rq-unlock" method="post" action="<?= e($qs(['seite' => $page > 1 ? $page : null])) ?>" autocomplete="off" data-rq-unlock>
      <?= csrf_field() ?>
      <input type="hidden" name="table" value="<?= e($t['handle']) ?>">
      <input type="hidden" name="id" value="<?= (int) $selId ?>" data-rq-selid>
      <label for="secret" class="adm-sr"><?= e(__('Geheimer {key}', ['key' => term('key')])) ?></label>
      <span class="rq-unlock__field<?= $keyError ? ' is-error' : '' ?>"><?= icon('key') ?>
        <input id="secret" class="is-bare" name="secret" type="password" autocomplete="off" spellcheck="false" placeholder="<?= e(__('Geheimer {key}', ['key' => term('key')])) ?>"<?= $keyError ? ' aria-invalid="true" aria-describedby="secret-e"' : '' ?>></span>
      <button class="adm-btn adm-btn--small adm-btn--primary" type="submit"><?= e(__('Entschlüsseln')) ?></button>
      <?php if ($keyError): ?><p class="f-error" id="secret-e"><?= e($keyError) ?></p><?php endif; ?>
    </form>
    <?php endif; ?>

    <div class="rq-tools">
      <div class="rq-search" role="search"><?= icon('magnifying-glass') ?>
        <label for="rq-q" class="adm-sr"><?= e(__('Anfragen durchsuchen')) ?></label>
        <input id="rq-q" class="is-bare" type="search" placeholder="<?= e($unlocked ? __('Suchen') : __('Nummer, Datum, zugewiesen …')) ?>" autocomplete="off" aria-keyshortcuts="/" aria-controls="rq-list" data-rq-q></div>
    </div>

    <?php if (($delivery ?? 'system') !== 'system'): ?>
    <p class="rq-note"><?= e(\Core\Data\Delivery::modeLabel($delivery)) ?>: <?= e($delivery === 'mail'
        ? __('Anfragen dieses Eingangs gehen nur per E-Mail hinaus. Hier erscheinen nur Anfragen, deren Zustellung fehlgeschlagen ist (verschlüsselt gesichert).')
        : __('Anfragen werden hier gespeichert und zusätzlich mit vollem Inhalt per E-Mail zugestellt.')) ?>
      <?php foreach (array_filter($deliveryProblems ?? [], fn($p) => $p['level'] === 'error') as $p): ?><br><b><?= e($p['text']) ?></b><?php endforeach; ?></p>
    <?php endif; ?>

    <div class="rq-list" id="rq-list">
      <?php if (!$rows): ?>
      <p class="rq-none"><?= e(__('Keine Anfragen in dieser Ansicht.')) ?></p>
      <?php else: ?>
      <ul class="rq-rows" data-rq-list aria-label="<?= e(__('Anfragen')) ?>">
        <?php foreach ($rows as $row): $id = (int) $row['id']; $data = $decrypted[$id] ?? null; [$name, $ex] = $summary($data); $on = $id === $selId;
          $au = $row['assignee'] ? ($userNames[$row['assignee']] ?? null) : null; $auName = $au ? ($au['name'] ?: $au['email']) : ($row['assignee'] ? '#' . $row['assignee'] : '');
          $search = mb_strtolower(implode(' ', [$row['ref'], date('d.m.Y', strtotime((string) $row['created_at'])), $auName, Inbox::statusLabel($row['status'], $t), $data ? implode(' ', array_column($data['fields'], 'value')) : ''])); ?>
        <li class="rq-row<?= $row['status'] === 'neu' ? ' is-unread' : '' ?><?= $on ? ' is-sel' : '' ?>" id="rq-row-<?= $id ?>" data-id="<?= $id ?>" data-search="<?= e($search) ?>">
          <a class="rq-row__a" href="<?= e($qs(['id' => $id, 'seite' => $page > 1 ? $page : null])) ?>"<?= $on ? ' aria-current="true"' : '' ?>>
            <span class="rq-row__dot" aria-hidden="true"></span>
            <span class="rq-row__top">
              <strong class="rq-row__title"><?php if ($row['status'] === 'neu'): ?><span class="adm-sr"><?= e(__('Neu:')) ?> </span><?php endif; ?><?= e($name !== '' ? $name : $t['singular'] . ' ' . $row['ref']) ?></strong>
              <time class="rq-row__time" datetime="<?= e(date('c', strtotime((string) $row['created_at']))) ?>" title="<?= e($fmt->datetime((string) $row['created_at'])) ?>"><?= e($fmt->relative((string) $row['created_at'])) ?></time>
            </span>
            <span class="rq-row__meta"><span class="adm-mono"><?= e((string) $row['ref']) ?></span><span class="rq-row__who" data-rq-whowrap<?= $auName === '' ? ' hidden' : '' ?>> · → <span data-rq-who><?= e($auName) ?></span></span></span>
            <?php if ($data): ?><span class="rq-row__ex"><?= e($ex) ?></span>
            <?php elseif (($info = Inbox::info($t, $row)) !== ''): ?><span class="rq-row__ex"><?= e($info) ?></span>
            <?php else: ?><span class="rq-row__ex rq-row__ex--muted"><?= icon('lock') ?> <?= e(__('Verschlüsselt')) ?></span><?php endif; ?>
            <span class="rq-row__tags"><?= $pill((string) $row['status']) ?>
              <?php if ($row['legacy']): ?><span class="rq-pill"><?= e(__('übernommen')) ?></span><?php endif; ?>
              <?php if (!empty($row['lang'])): ?><span class="rq-pill"><?= e(strtoupper((string) $row['lang'])) ?></span><?php endif; ?>
              <?php if ($data && ($data['delivery'] ?? '') === 'fallback'): ?><span class="rq-pill rq-pill--warn"><?= e(__('Zustellung fehlgeschlagen')) ?></span><?php endif; ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="rq-none" data-rq-nomatch hidden></p>
      <?php endif; ?>
    </div>
    <?php if ($pages > 1): ?>
    <nav class="rq-pager" aria-label="<?= e(__('Seiten')) ?>">
      <?php if ($page > 1): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($qs(['seite' => $page - 1])) ?>">← <?= e(__('Zurück')) ?></a><?php else: ?><span></span><?php endif; ?>
      <span><?= e(__('Seite {page} / {pages}', ['page' => $page, 'pages' => $pages])) ?></span>
      <?php if ($page < $pages): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($qs(['seite' => $page + 1])) ?>"><?= e(__('Weiter')) ?> →</a><?php else: ?><span></span><?php endif; ?>
    </nav>
    <?php endif; ?>
  </section>

  <div class="rq-col rq-col--pane" data-rq-pane>
    <?php if (!$rows): ?>
    <div class="rq-empty"><span class="rq-empty__ico" aria-hidden="true"><?= icon('tray') ?></span>
      <h2><?= e(__('Keine Anfragen')) ?></h2><p><?= e(__('In „{box}“ liegt gerade nichts.', ['box' => $boxTitle])) ?></p></div>
    <?php endif; ?>
    <?php foreach ($rows as $row): $id = (int) $row['id']; $data = $decrypted[$id] ?? null; [$name] = $summary($data); $rid = 'req-' . $id;
      $au = $row['assignee'] ? ($userNames[$row['assignee']] ?? null) : null; ?>
    <article class="rq-read" id="<?= $rid ?>" data-rq-read="<?= $id ?>" aria-labelledby="<?= $rid ?>-h"<?= $id === $selId ? '' : ' hidden' ?>>
      <header class="rq-head">
        <a class="rq-back" href="<?= e($qs(['seite' => $page > 1 ? $page : null])) ?>" data-rq-back>‹ <?= e($boxTitle) ?></a>
        <div class="rq-head__main">
          <p class="rq-eyebrow"><?= e($t['singular']) ?> · <span class="adm-mono"><?= e((string) $row['ref']) ?></span></p>
          <h2 id="<?= $rid ?>-h" tabindex="-1"><?= e($name !== '' ? $name : $t['singular'] . ' ' . $row['ref']) ?> <?= $pill((string) $row['status']) ?></h2>
          <p class="rq-head__meta"><time datetime="<?= e(date('c', strtotime((string) $row['created_at']))) ?>"><?= e($fmt->datetime((string) $row['created_at'])) ?></time> · #<?= $id ?>
            · <?= icon('user') ?> <span data-rq-who><?= e($au ? ($au['name'] ?: $au['email']) : ($row['assignee'] ? '#' . $row['assignee'] : __('niemandem zugewiesen'))) ?></span></p>
        </div>
        <div class="rq-head__act">
          <?php if ($canManage): foreach ($statuses as $st => $sd): if (!$sd['manual']) continue; ?>
          <form method="post" action="<?= e(url('/admin/requests/' . $t['handle'] . '/' . $id . '/status')) ?>" data-rq-act="status"<?= $st === $row['status'] ? ' hidden' : '' ?> data-rq-status="<?= e($st) ?>"><?= csrf_field() ?>
            <input type="hidden" name="status" value="<?= e($st) ?>"><input type="hidden" name="back" value="<?= e($status) ?>"><input type="hidden" name="id" value="<?= $id ?>">
            <button class="adm-btn adm-btn--small<?= $sd['done'] ? '' : ' adm-btn--ghost' ?>"><?= $sd['done'] ? icon('check-circle') . ' ' : '' ?><?= e($sd['action']) ?></button></form>
          <?php endforeach; endif; ?>
          <?php if ($data): ?>
          <span class="rq-head__sep" aria-hidden="true"></span>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-print="#<?= $rid ?>" title="<?= e(__('Drucken')) ?>"><?= icon('printer') ?><span class="adm-sr"><?= e(__('Drucken')) ?></span></button>
          <button type="button" class="adm-btn adm-btn--small adm-btn--ghost" data-copy="#<?= $rid ?>-copy" title="<?= e(__('Kopieren')) ?>"><?= icon('copy') ?><span class="adm-sr"><?= e(__('Kopieren')) ?></span></button>
          <?php endif; ?>
          <?php if ($canManage): ?>
          <form method="post" action="<?= e(url('/admin/requests/' . $t['handle'] . '/' . $id . '/delete')) ?>" data-rq-act="delete" data-confirm="<?= e(__('Anfrage endgültig löschen?')) ?>"><?= csrf_field() ?>
            <input type="hidden" name="back" value="<?= e($status) ?>">
            <button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" title="<?= e(__('Löschen')) ?>"><?= icon('trash') ?><span class="adm-sr"><?= e(__('Löschen')) ?></span></button></form>
          <?php endif; ?>
        </div>
      </header>

      <div class="rq-body">
        <?php if (($info = Inbox::info($t, $row)) !== ''): ?><p class="rq-info"><?= e($info) ?></p><?php endif; ?>
        <?php if ($data): ?>
          <?php if (($data['delivery'] ?? '') === 'fallback'): ?><p class="rq-warn"><?= e(__('Zustellung per E-Mail fehlgeschlagen – hier gesichert')) ?></p><?php endif; ?>
          <dl class="rq-dl">
            <?php foreach ($data['fields'] as $f): ?><dt><?= e($f['label']) ?></dt><dd><?php if (!empty($f['file'])): /* Datei (Zustellung per E-Mail, versiegelt) */ ?>
              <a href="data:<?= e(preg_match('~^[a-z]+/[a-z0-9.+-]+$~', (string) $f['file']['type']) ? (string) $f['file']['type'] : 'application/octet-stream') ?>;base64,<?= e((string) $f['file']['data']) ?>" download="<?= e((string) $f['file']['file']) ?>"><?= e($f['value']) ?></a>
            <?php elseif (!empty($f['table'])): /* Wiederholbare Gruppe */ ?>
              <table class="adm-inbox-group"><thead><tr><th scope="col"><span class="sr-only"><?= e(__('Nr.')) ?></span></th><?php foreach ($f['table']['cols'] as $c): ?><th scope="col"><?= e($c) ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach ($f['table']['rows'] as $ri => $cells): ?><tr><th scope="row"><?= $ri + 1 ?></th><?php foreach ($cells as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
            <?php elseif (filter_var(trim((string) $f['value']), FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?= e(trim((string) $f['value'])) ?>"><?= e($f['value']) ?></a>
            <?php else: ?><?= nl2br(e($f['value']), false) ?><?php endif; ?></dd><?php endforeach; ?>
          </dl>
          <pre id="<?= $rid ?>-copy" hidden><?= e($t['singular'] . ' ' . $row['ref'] . ' · ' . date('d.m.Y H:i', strtotime((string) $row['created_at'])) . "\n" . implode("\n", array_map(fn($f) => $f['label'] . ':' . (!empty($f['table']) ? "\n" : ' ') . $f['value'], $data['fields']))) ?></pre>
        <?php elseif ($unlocked): ?>
          <p class="adm-flash adm-flash--error"><?= e(__('Diese Anfrage konnte mit dem Schlüssel nicht entschlüsselt werden (evtl. mit einem älteren Schlüssel verschlüsselt).')) ?></p>
        <?php else: ?>
          <div class="rq-locked"><span class="rq-empty__ico" aria-hidden="true"><?= icon('lock') ?></span>
            <h3><?= e(__('Verschlüsselt')) ?></h3>
            <p><?= e(__('Zum Lesen über der Liste den geheimen {key} eingeben und „Entschlüsseln“ wählen. Er wird weder gespeichert noch protokolliert; nach dem Neuladen ist wieder alles verschlossen.', ['key' => term('key')])) ?></p>
            <?php if (empty($envKey)): ?><a class="adm-btn adm-btn--small" href="#secret" data-rq-back data-rq-key><?= icon('key') ?> <?= e(__('Schlüssel eingeben')) ?></a><?php endif; ?></div>
        <?php endif; ?>

        <?php if ($canManage && count($users) > 0): ?>
        <form method="post" action="<?= e(url('/admin/requests/' . $t['handle'] . '/' . $id . '/assign')) ?>" class="rq-assign" data-rq-act="assign"><?= csrf_field() ?>
          <input type="hidden" name="back" value="<?= e($status) ?>"><input type="hidden" name="id" value="<?= $id ?>">
          <label for="<?= $rid ?>-as"><?= e(__('Zuweisen an')) ?></label>
          <select id="<?= $rid ?>-as" name="user"><option value=""><?= e(__('– niemand –')) ?></option>
            <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"<?= (int) $u['id'] === (int) $row['assignee'] ? ' selected' : '' ?>><?= e($u['name'] ?: $u['email']) ?></option><?php endforeach; ?>
          </select>
          <button class="adm-btn adm-btn--small adm-btn--ghost" data-rq-nojs><?= e(__('Zuweisen')) ?></button></form>
        <?php endif; ?>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <p class="adm-sr" aria-live="polite" data-rq-live></p>
</div>

<?php $days = (int) Inbox::config($t)['retention_days']; ?>
<p class="rq-foot adm-muted"><?= e($days > 0 ? __('Datenschutz: Erledigte Anfragen werden nach {days} Tagen automatisch gelöscht.', ['days' => $days]) : __('Datenschutz: Erledigte Anfragen bitte regelmäßig löschen (Datensparsamkeit).')) ?>
  <?= e(__('Übernehmen Sie Inhalte bei Bedarf in {system}. Drucken und Kopieren geschieht nur im Browser – auf dem Server entsteht keine Klartext-Datei.', ['system' => term('records_system')])) ?></p>
