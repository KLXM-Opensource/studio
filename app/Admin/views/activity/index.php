<?php
/**
 * System → Aktionslog (Core\Activity): wer hat wann was angelegt, geändert, veröffentlicht oder gelöscht – mit Link zum Objekt.
 * Filter per GET (ohne JavaScript nutzbar; admin.js sendet Auswahlfelder mit data-autosubmit sofort ab).
 * @var array $f  @var array $rows  @var int $total  @var int $page  @var int $pages  @var array $counts  @var array $users
 */
use Core\Activity;

// Adresse mit den aktuellen Filtern (Standardwerte weggelassen), $over ersetzt einzelne
$base = ['art' => $f['type'], 'aktion' => $f['group'], 'person' => $f['user'], 'zeitraum' => $f['days'] === 30 ? '' : ($f['days'] ?: 'alle'),
    'sortierung' => $f['sort'] === 'new' ? '' : $f['sort'], 'q' => $f['q']];
$q = fn(array $over = []) => url('/admin/aktionslog') . (($p = http_build_query(array_filter(array_merge($base, $over), fn($v) => $v !== '' && $v !== null))) !== '' ? '?' . $p : '');
// Personen (Avatar): einmal laden
$people = [];
foreach (app()->db->fetchAll('SELECT id, name, email, ui_prefs FROM users') as $u) $people[(int) $u['id']] = $u;
$typeIcon = ['page' => 'file-text', 'entry' => 'table', 'media' => 'image'];
$tone = fn(string $a) => match (true) {
    in_array($a, Activity::GROUPS['new'], true) => 'new',
    in_array($a, Activity::GROUPS['live'], true) => $a === 'published' ? 'live' : 'off',
    in_array($a, Activity::GROUPS['gone'], true) => 'gone',
    default => 'changed',
};
$tables = [];
$tableName = function (?string $h) use (&$tables): string {
    if (!$h) return '';
    return $tables[$h] ??= (string) ((($t = \Core\Data\Tables::find($h)) ? ($t['name'] ?? $h) : $h));
};
$day = function (string $t): string {
    $d = date('Y-m-d', (int) strtotime($t));
    return match ($d) {
        date('Y-m-d') => __('Heute'),
        date('Y-m-d', time() - 86400) => __('Gestern'),
        default => fmt()->date($t, 'long') ?: date('d.m.Y', (int) strtotime($t)),
    };
};
$byDay = in_array($f['sort'], ['new', 'old'], true);
$lastDay = null;
?>
<header class="adm-head">
  <div><p class="adm-eyebrow"><?= e(__('System')) ?></p><h1><?= e(__('Aktionslog')) ?></h1>
    <p class="adm-muted"><?= e(__('Wer hat wann Seiten, Datensätze und Medien angelegt, geändert, veröffentlicht oder gelöscht. Mehrfaches Speichern derselben Sache innerhalb von 15 Minuten erscheint als ein Eintrag. Einträge bleiben ein Jahr erhalten.')) ?></p></div>
</header>

<nav class="adm-filter rv-tabs" aria-label="<?= e(__('Nach Art filtern')) ?>">
  <?php foreach (['' => __('Alle')] + Activity::typeLabels() as $k => $l): ?>
  <a href="<?= e($q(['art' => $k, 'seite' => ''])) ?>"<?= $f['type'] === $k ? ' aria-current="page"' : '' ?>><?php if ($k !== ''): ?><?= icon($typeIcon[$k]) ?> <?php endif; ?><?= e($l) ?> <small class="rv-tabs__n"><?= (int) ($counts[$k] ?? 0) ?></small></a>
  <?php endforeach; ?>
</nav>

<form method="get" action="<?= e(url('/admin/aktionslog')) ?>" class="al-filter">
  <?php if ($f['type'] !== ''): ?><input type="hidden" name="art" value="<?= e($f['type']) ?>"><?php endif; ?>
  <label class="fx-search dt-search al-search"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="M7 2a5 5 0 1 0 3 9l3.3 3.3 1-1L11 10A5 5 0 0 0 7 2zm0 1.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z"/></svg><input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="<?= e(__('Name suchen')) ?>" aria-label="<?= e(__('Name suchen')) ?>"></label>
  <label class="al-sel"><span><?= e(__('Aktion')) ?></span>
    <select name="aktion" data-autosubmit><option value=""><?= e(__('Alle')) ?></option>
      <?php foreach (Activity::groupLabels() as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['group'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></label>
  <label class="al-sel"><span><?= e(__('Person')) ?></span>
    <select name="person" data-autosubmit><option value=""><?= e(__('Alle')) ?></option>
      <?php foreach ($users as $id => $n): ?><option value="<?= (int) $id ?>"<?= $f['user'] === (string) $id ? ' selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
    </select></label>
  <label class="al-sel"><span><?= e(__('Zeitraum')) ?></span>
    <select name="zeitraum" data-autosubmit>
      <?php foreach ([1 => __('Heute und gestern'), 7 => __('7 Tage'), 30 => __('30 Tage'), 90 => __('90 Tage'), 365 => __('1 Jahr'), 0 => __('Alles')] as $k => $l): ?><option value="<?= $k ?: 'alle' ?>"<?= $f['days'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></label>
  <label class="al-sel"><span><?= e(__('Sortierung')) ?></span>
    <select name="sortierung" data-autosubmit>
      <?php foreach (['new' => __('Neueste zuerst'), 'old' => __('Älteste zuerst'), 'type' => __('Nach Art'), 'user' => __('Nach Person')] as $k => $l): ?><option value="<?= e($k) ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
    </select></label>
  <button type="submit" class="adm-btn adm-btn--small"><?= e(__('Anwenden')) ?></button>
  <?php if ($f['group'] !== '' || $f['user'] !== '' || $f['q'] !== '' || $f['days'] !== 30 || $f['sort'] !== 'new' || $f['type'] !== ''): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e(url('/admin/aktionslog')) ?>"><?= e(__('Zurücksetzen')) ?></a><?php endif; ?>
</form>

<?php if (!$rows): ?>
<div class="adm-card rv-empty"><p><b><?= e(__('Keine Einträge für diese Auswahl.')) ?></b></p>
  <p class="adm-muted"><?= e(__('Zeitraum vergrößern oder Filter zurücksetzen.')) ?></p></div>
<?php else: ?>
<div class="adm-card al-card">
  <table class="adm-table al-table">
    <thead><tr><th scope="col"><?= e(__('Zeit')) ?></th><th scope="col"><?= e(__('Person')) ?></th><th scope="col"><?= e(__('Aktion')) ?></th><th scope="col"><?= e(__('Art')) ?></th><th scope="col"><?= e(__('Objekt')) ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $d = date('Y-m-d', (int) strtotime($r['created_at']));
        if ($byDay && $d !== $lastDay): $lastDay = $d; ?>
      <tr class="al-day"><th colspan="5" scope="rowgroup"><?= e($day($r['created_at'])) ?></th></tr>
    <?php endif;
        $uid = $r['user_id'] !== null ? (int) $r['user_id'] : null;
        $u = $uid !== null ? ($people[$uid] ?? null) : null;
        $link = Activity::link($r);
        $view = Activity::viewUrl($r);
        $kind = $r['type'] === 'entry' ? $tableName($r['tbl']) : Activity::typeLabel($r['type']); ?>
      <tr>
        <td class="al-time"><time datetime="<?= e(date('c', (int) strtotime($r['created_at']))) ?>" title="<?= e(date('d.m.Y H:i', (int) strtotime($r['created_at']))) ?>"><?= e($byDay ? date('H:i', (int) strtotime($r['created_at'])) : date('d.m.Y H:i', (int) strtotime($r['created_at']))) ?></time></td>
        <td class="al-user"><?php if ($u): ?><?= \Core\Avatar::html($u, 'adm-ava al-ava') ?> <span><?= e((string) ($r['user_name'] ?: $u['name'] ?: $u['email'])) ?></span><?php else: ?><span class="adm-muted"><?= e((string) ($r['user_name'] ?: __('System'))) ?></span><?php endif; ?></td>
        <td><span class="al-act al-act--<?= e($tone($r['action'])) ?>"><?= e(Activity::actionLabel($r['action'])) ?></span><?php if ((int) $r['n'] > 1): ?> <small class="adm-muted" title="<?= e(__('Mehrfach gespeichert innerhalb von 15 Minuten')) ?>">×<?= (int) $r['n'] ?></small><?php endif; ?></td>
        <td class="al-kind"><?= icon($typeIcon[$r['type']] ?? 'file') ?> <span><?= e($kind) ?></span></td>
        <td class="al-obj">
          <?php if ($link): ?><a href="<?= e($link) ?>"><?= e((string) $r['label']) ?></a><?php else: ?><span class="al-gone" title="<?= e(__('Nicht mehr vorhanden')) ?>"><?= e((string) $r['label']) ?></span><?php endif; ?>
          <?php if ($view): ?> <a class="al-view" href="<?= e($view) ?>" target="_blank" rel="noopener" title="<?= e(__('Auf der Website ansehen')) ?>" aria-label="<?= e(__('Auf der Website ansehen')) ?>"><?= icon('arrow-square-out') ?></a><?php endif; ?>
          <?php if (!empty($r['lang']) && \Core\Lang::multi()): ?> <small class="al-lang"><?= e(strtoupper((string) $r['lang'])) ?></small><?php endif; ?>
          <?php if (!empty($r['detail'])): ?><small class="al-detail"><?= e((string) $r['detail']) ?></small><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<nav class="al-pager" aria-label="<?= e(__('Seiten')) ?>">
  <span class="adm-muted"><?= e(__('{n} Einträge', ['n' => fmt()->number($total)])) ?></span>
  <?php if ($pages > 1): ?>
    <?php if ($page > 1): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($q(['seite' => $page - 1 > 1 ? $page - 1 : ''])) ?>">← <?= e(__('Neuere')) ?></a><?php endif; ?>
    <span><?= e(__('Seite {a} von {b}', ['a' => $page, 'b' => $pages])) ?></span>
    <?php if ($page < $pages): ?><a class="adm-btn adm-btn--small adm-btn--ghost" href="<?= e($q(['seite' => $page + 1])) ?>"><?= e(__('Ältere')) ?> →</a><?php endif; ?>
  <?php endif; ?>
</nav>
<?php endif; ?>
