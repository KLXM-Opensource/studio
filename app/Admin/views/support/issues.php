<?php
/**
 * Meldungen: eigene (Redaktion) bzw. alle der Website (Website-Administration) bzw. aller Websites (Support-Team).
 * @var bool $mine  @var array $f  @var array $list  @var array $counts  @var array $staff  @var array $sites  @var string $title
 */
use Core\Support\Support;
use Core\Support\Ui;

$isStaff = Support::isStaff();
$base = $mine ? '/admin/support' : '/admin/support/alle';
$qs = fn(array $p) => url($base) . '?' . http_build_query(array_filter($p + ['status' => $f['status'], 'kategorie' => $f['category'], 'prioritaet' => $f['priority'],
    'website' => $f['site'], 'zustaendig' => $f['assignee'], 'q' => $f['q']], fn($v) => $v !== null && $v !== ''));
$me = Support::me();
$myStaff = $isStaff ? (array_column($staff, null, 'user_key')[$me] ?? null) : null;
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('Support')) ?></p><h1><?= e($title) ?></h1>
    <p class="adm-muted"><?= e($mine ? __('Ihre Meldungen an das Support-Team – mit allen Antworten. Neue Antworten sind markiert.')
      : ($isStaff ? __('Meldungen aller Websites dieser Installation. Offene zuerst, dringende oben.') : __('Alle Meldungen, die von dieser Website aus gesendet wurden.'))) ?></p></div>
  <?php if (Support::canReport()): ?><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/support/neu')) ?>" data-support-report>+ <?= e(__('Problem melden')) ?></a><?php endif; ?>
</header>

<nav class="adm-filter sp-filter" aria-label="<?= e(__('Status')) ?>">
  <?php foreach (['offen', ...Support::STATUSES, 'alle'] as $k): $cur = $f['status'] === $k || ($k === 'alle' && $f['status'] === ''); ?>
  <a href="<?= e($qs(['status' => $k, 'seite' => null])) ?>"<?= $cur ? ' aria-current="true"' : '' ?>><?= e(match ($k) { 'offen' => __('Offen'), 'alle' => __('Alle'), default => Support::statusLabel($k) }) ?> <small><?= (int) ($counts[$k] ?? 0) ?></small></a>
  <?php endforeach; ?>
</nav>

<form class="sp-toolbar" method="get" action="<?= e(url($base)) ?>" role="search">
  <?php if ($f['status'] !== ''): ?><input type="hidden" name="status" value="<?= e($f['status']) ?>"><?php endif; ?>
  <div class="f sp-toolbar__q"><label for="sp-q"><?= e(__('Suche')) ?></label><input id="sp-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('Titel, Text oder #Nummer')) ?>"></div>
  <?php if (!$mine && $isStaff && count($sites) > 1): ?>
  <div class="f"><label for="sp-site"><?= e(__('Website')) ?></label><select id="sp-site" name="website"><option value=""><?= e(__('Alle Websites')) ?></option>
    <?php foreach ($sites as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['site'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <div class="f"><label for="sp-cat"><?= e(__('Kategorie')) ?></label><select id="sp-cat" name="kategorie"><option value=""><?= e(__('Alle')) ?></option>
    <?php foreach (Support::CATEGORIES as $c): ?><option value="<?= e($c) ?>"<?= $f['category'] === $c ? ' selected' : '' ?>><?= e(Support::categoryLabel($c)) ?></option><?php endforeach; ?></select></div>
  <div class="f"><label for="sp-prio"><?= e(__('Priorität')) ?></label><select id="sp-prio" name="prioritaet"><option value=""><?= e(__('Alle')) ?></option>
    <option value="dringend"<?= $f['priority'] === 'dringend' ? ' selected' : '' ?>><?= e(__('Dringend')) ?></option></select></div>
  <?php if (!$mine && $isStaff): ?>
  <div class="f"><label for="sp-as"><?= e(__('Zuständig')) ?></label><select id="sp-as" name="zustaendig"><option value=""><?= e(__('Alle')) ?></option>
    <option value="ich"<?= $f['assignee'] === 'ich' ? ' selected' : '' ?>><?= e(__('Ich')) ?></option>
    <option value="niemand"<?= $f['assignee'] === 'niemand' ? ' selected' : '' ?>><?= e(__('Niemand')) ?></option>
    <?php foreach ($staff as $s): if ($s['user_key'] === $me) continue; ?><option value="<?= e($s['user_key']) ?>"<?= $f['assignee'] === $s['user_key'] ? ' selected' : '' ?>><?= e($s['name'] ?: $s['email']) ?></option><?php endforeach; ?></select></div>
  <?php endif; ?>
  <button class="adm-btn" type="submit"><?= e(__('Filtern')) ?></button>
  <?php if ($f['q'] !== '' || $f['category'] !== '' || $f['priority'] !== '' || $f['site'] !== '' || $f['assignee'] !== ''): ?><a class="adm-btn adm-btn--ghost" href="<?= e(url($base)) ?>"><?= e(__('Zurücksetzen')) ?></a><?php endif; ?>
</form>

<?php if (!$list['rows']): ?>
<div class="adm-card sp-empty">
  <p><b><?= e($f['q'] !== '' ? __('Keine Meldung gefunden.') : ($mine ? __('Sie haben noch nichts gemeldet.') : __('Keine Meldungen in dieser Ansicht.'))) ?></b></p>
  <?php if ($mine): ?><p class="adm-muted"><?= e(__('Wenn etwas nicht funktioniert oder Sie Hilfe brauchen: „Problem melden“. Vorher lohnt ein Blick in die Wissensdatenbank – vielleicht gibt es die Lösung schon.')) ?></p>
  <p class="adm-row"><a class="adm-btn adm-btn--primary" href="<?= e(url('/admin/support/neu')) ?>" data-support-report><?= e(__('Problem melden')) ?></a> <a class="adm-btn adm-btn--ghost" href="<?= e(url('/admin/support/wissen')) ?>"><?= e(__('Wissensdatenbank')) ?></a></p><?php endif; ?>
</div>
<?php else: ?>
<ul class="sp-list" aria-label="<?= e($title) ?>">
  <?php foreach ($list['rows'] as $i): ?>
  <li class="sp-item<?= $i['unread'] ? ' is-unread' : '' ?><?= in_array($i['status'], Support::OPEN, true) ? '' : ' is-done' ?>">
    <div class="sp-item__main">
      <a class="sp-item__title" href="<?= e(url('/admin/support/meldung/' . $i['id'])) ?>"><?php if ($i['unread']): ?><span class="sp-dot" aria-hidden="true"></span><span class="sr-only"><?= e(__('Neu:')) ?> </span><?php endif; ?><span class="sp-item__no">#<?= (int) $i['id'] ?></span> <?= e($i['title']) ?></a>
      <p class="sp-item__meta">
        <?= Ui::category($i['category']) ?>
        <?php if (!$mine): ?><span><?= e($isStaff ? Support::siteLabel($i['site']) . ' · ' . $i['reporter_name'] : $i['reporter_name']) ?></span><?php endif; ?>
        <span><?= e(__('Aktualisiert')) ?> <?= Ui::when($i['updated_at']) ?></span>
        <?php if ((int) $i['replies']): ?><span><?= e(Ui::n((int) $i['replies'], 'messages')) ?></span><?php endif; ?>
        <?php if ((int) $i['nfiles']): ?><span>▣ <?= (int) $i['nfiles'] ?><span class="sr-only"> <?= e(__('Bilder')) ?></span></span><?php endif; ?>
        <?php if ($isStaff && $i['assignee_name']): ?><span>→ <?= e($i['assignee_name']) ?></span><?php endif; ?>
      </p>
    </div>
    <div class="sp-item__badges"><?= Ui::priority($i['priority']) ?><?= Ui::status($i['status']) ?></div>
  </li>
  <?php endforeach; ?>
</ul>
<?= Ui::pager((int) $f['page'] ?: 1, $list['pages'], $qs) ?>
<?php endif; ?>

<?php if (!$mine && $isStaff && $myStaff): ?>
<form class="sp-prefs" method="post" action="<?= e(url('/admin/support/einstellungen')) ?>"><?= csrf_field() ?>
  <input type="hidden" name="notify" value="<?= (int) $myStaff['notify'] ? '0' : '1' ?>">
  <p class="adm-muted"><?= e((int) $myStaff['notify'] ? __('Sie bekommen eine E-Mail bei neuen Meldungen ({email}).', ['email' => $myStaff['email']]) : __('E-Mails bei neuen Meldungen sind für Sie ausgeschaltet.')) ?>
    <button class="adm-link" type="submit"><?= e((int) $myStaff['notify'] ? __('Ausschalten') : __('Einschalten')) ?></button></p>
</form>
<?php endif; ?>
