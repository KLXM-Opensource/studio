<?php
/**
 * Eine Meldung: Verlauf (Antworten, interne Notizen, Statuswechsel), Angaben, Aktionen.
 * @var array $issue  @var array $posts  @var array $files  @var array $staff  @var ?array $article
 */
use Core\Support\Markdown;
use Core\Support\Support;
use Core\Support\Tickets;
use Core\Support\Ui;

$isStaff = Support::isStaff();
$isReporter = Tickets::isReporter($issue);
$ctx = json_decode((string) $issue['context_json'], true) ?: [];
$labels = Tickets::contextLabels();
$closed = $issue['status'] === 'geschlossen';
$shots = function (array $list) {
    if (!$list) return '';
    $out = '<ul class="sp-gallery">';
    foreach ($list as $f) {
        $src = url('/admin/support/datei/' . $f['id']);
        $out .= '<li><a href="' . e($src) . '" target="_blank" rel="noopener"><img src="' . e($src) . '" alt="' . e(__('Bildschirmfoto: {name}', ['name' => $f['name']])) . '" loading="lazy"'
            . ($f['width'] ? ' width="' . (int) $f['width'] . '" height="' . (int) $f['height'] . '"' : '') . '></a><small>' . e($f['name']) . '</small></li>';
    }
    return $out . '</ul>';
};
$who = fn(array $p) => Support::authorLabel((string) $p['author_key'], $p['author_name'], (bool) $p['author_staff']);
?>
<header class="adm-head sp-issue-head">
  <div>
    <p class="adm-eyebrow"><a href="<?= e(url($isReporter ? '/admin/support' : '/admin/support/alle')) ?>">← <?= e($isReporter ? __('Meine Meldungen') : ($isStaff ? __('Alle Meldungen') : __('Meldungen dieser Website'))) ?></a></p>
    <h1><span class="sp-item__no">#<?= (int) $issue['id'] ?></span> <?= e($issue['title']) ?></h1>
    <p class="sp-issue-head__badges"><?= Ui::status($issue['status']) ?><?= Ui::priority($issue['priority']) ?><?= Ui::category($issue['category']) ?></p>
  </div>
</header>

<div class="sp-issue">
  <div class="sp-thread">
    <ol class="sp-posts" aria-label="<?= e(__('Verlauf')) ?>">
      <li class="sp-post sp-post--origin">
        <header class="sp-post__head"><b><?= e(Support::authorLabel((string) $issue['reporter_key'], $issue['reporter_name'])) ?></b> <span class="adm-muted"><?= e(__('hat gemeldet')) ?> · <?= Ui::when($issue['created_at']) ?></span></header>
        <div class="sp-md"><?= Markdown::render($issue['body']) ?></div>
        <?= $shots($files[0] ?? []) ?>
      </li>
      <?php foreach ($posts as $p): $meta = json_decode((string) $p['meta'], true) ?: []; ?>
        <?php if ($p['kind'] === 'status'): ?>
        <li class="sp-post sp-post--event"><span aria-hidden="true">●</span> <?= e(__('{name} hat den Status auf „{status}“ gesetzt', ['name' => $who($p), 'status' => Support::statusLabel((string) ($meta['to'] ?? ''))])) ?> · <?= Ui::when($p['created_at']) ?></li>
        <?php elseif ($p['kind'] === 'assign'): ?>
        <li class="sp-post sp-post--event sp-post--internal-event"><span aria-hidden="true">→</span> <?= e($meta['to'] ? __('{name} hat die Meldung {to} zugewiesen', ['name' => $p['author_name'], 'to' => $meta['to']]) : __('{name} hat die Zuweisung aufgehoben', ['name' => $p['author_name']])) ?> · <?= Ui::when($p['created_at']) ?> <span class="sp-internal-tag"><?= e(__('intern')) ?></span></li>
        <?php elseif ($p['kind'] === 'kb'): ?>
        <li class="sp-post sp-post--event sp-post--internal-event"><span aria-hidden="true">❖</span> <?= e(__('{name} hat daraus einen Wissensartikel gemacht', ['name' => $p['author_name']])) ?> · <a href="<?= e(url('/admin/support/wissen/' . (int) ($meta['article'] ?? 0))) ?>"><?= e(__('Artikel ansehen')) ?></a> <span class="sp-internal-tag"><?= e(__('intern')) ?></span></li>
        <?php else: $internal = (int) $p['internal'] === 1; ?>
        <li class="sp-post<?= $internal ? ' sp-post--note' : ((int) $p['author_staff'] ? ' sp-post--staff' : '') ?>" id="p<?= (int) $p['id'] ?>">
          <header class="sp-post__head"><b><?= e($who($p)) ?></b> <span class="adm-muted"><?= Ui::when($p['created_at']) ?></span>
            <?php if ($internal): ?><span class="sp-internal-tag"><?= e(__('Interne Notiz – nur Support-Team')) ?></span><?php endif; ?></header>
          <div class="sp-md"><?= Markdown::render($p['body']) ?></div>
          <?= $shots($files[(int) $p['id']] ?? []) ?>
        </li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ol>
    <span id="verlauf-ende"></span>

    <?php if (!$closed || $isStaff): ?>
    <form class="adm-card sp-form sp-reply" id="antworten" method="post" action="<?= e(url('/admin/support/meldung/' . $issue['id'])) ?>" enctype="multipart/form-data" data-support-form>
      <?= csrf_field() ?>
      <h2><?= e($isStaff ? __('Antworten oder Notiz') : __('Antworten')) ?></h2>
      <?php if ($isStaff): ?>
      <fieldset class="sp-mode">
        <legend class="sr-only"><?= e(__('Art der Nachricht')) ?></legend>
        <label><input type="radio" name="internal" value="0" checked> <?= e(__('Antwort an {name}', ['name' => $issue['reporter_name'] ?: __('meldende Person')])) ?></label>
        <label><input type="radio" name="internal" value="1"> <?= e(__('Interne Notiz (nur Support-Team)')) ?></label>
      </fieldset>
      <?php endif; ?>
      <div class="f">
        <label for="sp-reply-body"><?= e(__('Nachricht')) ?></label>
        <textarea id="sp-reply-body" name="body" rows="5" data-support-paste aria-describedby="sp-reply-h"></textarea>
        <?= Ui::mdHint('sp-reply-h') ?>
      </div>
      <div class="f sp-shots" data-support-shots>
        <label for="sp-reply-shots"><?= e(__('Bildschirmfotos (optional)')) ?></label>
        <input id="sp-reply-shots" name="shots[]" type="file" accept="image/png,image/jpeg,image/webp,image/gif" multiple>
        <ul class="sp-thumbs" data-support-thumbs aria-live="polite"></ul>
      </div>
      <div class="adm-row sp-actions">
        <?php if ($isStaff): ?>
        <label class="sp-inline-select"><?= e(__('Status danach')) ?>
          <select name="status">
            <?php foreach (Support::STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $s === ($issue['status'] === 'neu' ? 'in_arbeit' : $issue['status']) ? ' selected' : '' ?>><?= e(Support::statusLabel($s)) ?></option><?php endforeach; ?>
          </select></label>
        <?php endif; ?>
        <button class="adm-btn adm-btn--primary" type="submit"><?= e(__('Senden')) ?></button>
      </div>
      <?php if (!$isStaff && $issue['status'] === 'rueckfrage'): ?><p class="adm-muted"><?= e(__('Das Support-Team wartet auf Ihre Rückmeldung.')) ?></p><?php endif; ?>
    </form>
    <?php else: ?>
    <p class="adm-card adm-muted"><?= e(__('Diese Meldung ist geschlossen. Bei einem neuen Problem legen Sie bitte eine neue Meldung an.')) ?> <a href="<?= e(url('/admin/support/neu')) ?>"><?= e(__('Problem melden')) ?></a></p>
    <?php endif; ?>
  </div>

  <aside class="sp-side" aria-label="<?= e(__('Angaben zur Meldung')) ?>">
    <section class="adm-card">
      <h2><?= e(__('Angaben')) ?></h2>
      <dl class="sp-dl">
        <dt><?= e(__('Status')) ?></dt><dd><?= Ui::status($issue['status']) ?></dd>
        <dt><?= e(__('Kategorie')) ?></dt><dd><?= e(Support::categoryLabel($issue['category'])) ?></dd>
        <dt><?= e(__('Priorität')) ?></dt><dd><?= e(Support::priorityLabel($issue['priority'])) ?></dd>
        <dt><?= e(__('Website')) ?></dt><dd><?= e(Support::siteLabel($issue['site'])) ?></dd>
        <?php if (!$isReporter): ?><dt><?= e(__('Gemeldet von')) ?></dt><dd><?= e($issue['reporter_name']) ?><?php if ($issue['reporter_email']): ?><br><a href="mailto:<?= e($issue['reporter_email']) ?>"><?= e($issue['reporter_email']) ?></a><?php endif; ?></dd><?php endif; ?>
        <dt><?= e(__('Gemeldet')) ?></dt><dd><?= e(date('d.m.Y H:i', strtotime((string) $issue['created_at']))) ?></dd>
        <?php if ($isStaff || $issue['assignee_name']): ?><dt><?= e(__('Zuständig')) ?></dt><dd><?= e($issue['assignee_name'] ?: __('noch niemand')) ?></dd><?php endif; ?>
        <?php if ($article): ?><dt><?= e(__('Wissensartikel')) ?></dt><dd><a href="<?= e(url('/admin/support/wissen/' . $article['id'])) ?>"><?= e($article['title']) ?></a></dd><?php endif; ?>
      </dl>
    </section>

    <?php if ($isStaff): ?>
    <section class="adm-card sp-staff">
      <h2><?= e(__('Bearbeiten')) ?></h2>
      <form method="post" action="<?= e(url('/admin/support/meldung/' . $issue['id'] . '/status')) ?>" class="sp-inline-form"><?= csrf_field() ?>
        <label for="sp-st"><?= e(__('Status')) ?></label>
        <select id="sp-st" name="status"><?php foreach (Support::STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $s === $issue['status'] ? ' selected' : '' ?>><?= e(Support::statusLabel($s)) ?></option><?php endforeach; ?></select>
        <button class="adm-btn adm-btn--small"><?= e(__('Setzen')) ?></button>
      </form>
      <form method="post" action="<?= e(url('/admin/support/meldung/' . $issue['id'] . '/zuweisen')) ?>" class="sp-inline-form"><?= csrf_field() ?>
        <label for="sp-as"><?= e(__('Zuständig')) ?></label>
        <select id="sp-as" name="assignee"><option value=""><?= e(__('– niemand –')) ?></option>
          <?php foreach ($staff as $s): ?><option value="<?= e($s['user_key']) ?>"<?= $s['user_key'] === $issue['assignee_key'] ? ' selected' : '' ?>><?= e(($s['name'] ?: $s['email']) . ($s['user_key'] === Support::me() ? ' (' . __('ich') . ')' : '')) ?></option><?php endforeach; ?>
        </select>
        <button class="adm-btn adm-btn--small"><?= e(__('Zuweisen')) ?></button>
      </form>
      <?php if (in_array($issue['status'], ['geloest', 'geschlossen'], true)): ?>
      <a class="adm-btn adm-btn--block" href="<?= e(url('/admin/support/meldung/' . $issue['id'] . '/wissen')) ?>">❖ <?= e($article ? __('Weiteren Wissensartikel anlegen') : __('Als Wissensartikel veröffentlichen')) ?></a>
      <?php else: ?>
      <p class="adm-muted sp-small"><?= e(__('Gelöste Meldungen lassen sich als Wissensartikel veröffentlichen.')) ?></p>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/admin/support/meldung/' . $issue['id'] . '/loeschen')) ?>" data-confirm="<?= e(__('Meldung #{id} mit allen Nachrichten und Bildern endgültig löschen?', ['id' => $issue['id']])) ?>"><?= csrf_field() ?>
        <button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text"><?= e(__('Meldung löschen')) ?></button></form>
    </section>
    <?php elseif ($isReporter): ?>
    <section class="adm-card">
      <?php if (in_array($issue['status'], Support::OPEN, true)): ?>
      <form method="post" action="<?= e(url('/admin/support/meldung/' . $issue['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="geloest">
        <p class="adm-muted sp-small"><?= e(__('Hat sich das erledigt?')) ?></p>
        <button class="adm-btn adm-btn--block">✓ <?= e(__('Problem ist gelöst')) ?></button></form>
      <?php elseif ($issue['status'] === 'geloest'): ?>
      <form method="post" action="<?= e(url('/admin/support/meldung/' . $issue['id'] . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="status" value="in_arbeit">
        <p class="adm-muted sp-small"><?= e(__('Doch noch nicht gelöst?')) ?></p>
        <button class="adm-btn adm-btn--block"><?= e(__('Wieder öffnen')) ?></button></form>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($ctx): ?>
    <details class="adm-card sp-ctx"<?= $isStaff ? ' open' : '' ?>>
      <summary><?= e(__('Technische Angaben')) ?></summary>
      <dl class="sp-dl sp-dl--small"><?php foreach ($ctx as $k => $v): ?><dt><?= e($labels[$k] ?? $k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?></dl>
    </details>
    <?php endif; ?>
  </aside>
</div>
