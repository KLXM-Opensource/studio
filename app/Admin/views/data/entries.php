<?php
/**
 * Einträge einer Tabelle (Listenansicht wie im Finder).
 * @var array $t  @var array $rows  @var int $total  @var string $status  @var string $q  @var string $sort  @var string $dir
 * @var int $page  @var int $pages  @var array $counts  @var array $user
 */
use Core\Data\Entries;
use Core\Data\Tables;

$cols = array_values(array_filter($t['fields'], fn($f) => !empty($f['in_list']) && $f['name'] !== $t['settings']['title_field']));
$cols = array_slice($cols, 0, 4);
$listImg = $t['settings']['list_image'] ?? 'small';
$img = $listImg === 'none' ? '' : Tables::imageField($t);
// Kleines Vorschaubild für Bild-/Dateifelder in Spalten
$thumb = function (?int $id, string $cls = '') use ($listImg): string {
    if ($listImg === 'none' || !$id || !($m = \Core\Media::find($id))) return '';
    return str_starts_with((string) $m['mime'], 'image/')
        ? '<img src="' . e(\Core\Media::url($m, 480)) . '" alt="" class="dt-thumb' . ($listImg === 'large' ? ' dt-thumb--l' : '') . $cls . '" loading="lazy">'
        : '<span class="dt-file">' . e(\Core\Media::displayName($m)) . '</span>';
};
$base = '/admin/data/' . $t['handle'];
$multi = \Core\Lang::multi();
$qs = fn(array $o) => url($base) . '?' . http_build_query(array_filter(array_merge(['lang' => $multi && $lang !== \Core\Lang::default() ? $lang : '', 'status' => $status === 'all' ? '' : $status, 'q' => $q, 'sort' => $sort, 'dir' => $dir], $o), fn($v) => $v !== '' && $v !== null));
$sortLink = function (string $key, string $label) use ($sort, $dir, $qs) {
    $active = $sort === $key;
    return '<a href="' . e($qs(['sort' => $key, 'dir' => $active && $dir === 'asc' ? 'desc' : 'asc', 'seite' => null])) . '" class="' . ($active ? 'is-sorted' . ($dir === 'desc' ? ' is-desc' : '') : '') . '">' . e($label) . '</a>';
};
$manual = $sort === 'sort' && $q === '' && $status === 'all';
// Geteilte Tabellen (Mitglieder): Entscheidung der Eigentümer-Website über Vorschläge
$ownerPicks = \Core\Data\Tables::isShared($t) && !\Core\Data\Shared::isOwner($t) ? \Core\Data\Shared::picks($t, array_column($rows, 'id'), $t['shared']['owner']) : [];
// Externe Quellen (Core\Sources): Einträge aus einem Feed/einer API – nur lesbar, Badge mit Name der Quelle
$extOrigin = \Core\Sources\Sources::originMap($t, array_column($rows, 'id'));
?>
<?php // Ansicht (Liste/Kalender), Felder und „Detailseite gestalten“ stehen in der Daten-Navigation (data/_nav.php) ?>
<header class="adm-head dt-head">
  <h1><span aria-hidden="true" class="dt-h1icon"><?= icon($t['icon']) ?></span> <?= e($t['name']) ?><?php if ($shared = \Core\Data\Tables::isShared($t)): ?> <span class="dt-nav__shared"><?= e(__('geteilt')) ?></span><?php endif; ?></h1>
  <div class="adm-row">
    <a class="adm-btn adm-btn--primary" href="<?= e(url($base . '/new') . ($multi ? '?lang=' . $lang : '')) ?>">+ <?= e($t['singular']) ?></a>
  </div>
</header>

<div class="dt-list" data-entries="<?= e(url($base . '/bulk')) ?>">
  <div class="dt-bar">
    <?php if ($multi): ?><nav class="fx-seg dt-seg" aria-label="Sprache"><?php foreach (\Core\Lang::all() as $code => $label): ?><a href="<?= e(url($base) . '?lang=' . $code) ?>"<?= $code === $lang ? ' aria-current="true"' : '' ?>><?= e($label) ?></a><?php endforeach; ?></nav><?php endif; ?>
    <nav class="fx-seg dt-seg" aria-label="Status">
      <?php foreach (['all' => 'Alle', 'published' => 'Online', 'draft' => 'Entwurf'] as $k => $l): if ($k !== 'all' && !$t['settings']['workflow']) continue; ?>
      <a href="<?= e($qs(['status' => $k === 'all' ? '' : $k, 'seite' => null])) ?>"<?= $status === $k ? ' aria-current="true"' : '' ?>><?= $l ?> <small><?= $counts[$k] ?></small></a>
      <?php endforeach; ?>
    </nav>
    <form class="fx-search dt-search" method="get" action="<?= e(url($base)) ?>" role="search">
      <svg viewBox="0 0 16 16" aria-hidden="true"><path d="M7 2a5 5 0 1 0 3 9l3.3 3.3 1-1L11 10A5 5 0 0 0 7 2zm0 1.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7z"/></svg>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Suchen" aria-label="Einträge durchsuchen">
      <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    </form>
    <span class="dt-bulk" hidden data-bulk>
      <strong data-selcount></strong>
      <?php if ($t['settings']['workflow'] && can('data.publish', $t['handle'])): ?><button type="button" class="adm-btn adm-btn--small" data-bulk-action="publish">Online stellen</button>
      <button type="button" class="adm-btn adm-btn--small" data-bulk-action="draft">Auf Entwurf</button><?php endif; ?>
      <?php if (can('data.delete', $t['handle'])): ?><button type="button" class="adm-btn adm-btn--small adm-btn--danger-text adm-btn--ghost" data-bulk-action="delete">Löschen</button><?php endif; ?>
    </span>
  </div>

  <?php if (!empty($cal)): ?>
  <?= \Core\Theme::capture(__DIR__ . '/_calendar.php', ['t' => $t, 'cal' => $cal, 'base' => $base, 'lang' => $lang, 'multi' => $multi, 'status' => $status, 'q' => $q]) ?>
  <?php elseif (!$rows): ?>
  <div class="dt-empty">
    <span class="dt-icon" aria-hidden="true"><?= icon($t['icon']) ?></span>
    <p><?= $q !== '' ? 'Keine Treffer für „' . e($q) . '“.' : 'Noch keine Einträge.' ?></p>
    <a class="adm-btn adm-btn--primary" href="<?= e(url($base . '/new')) ?>">+ <?= e($t['singular']) ?> anlegen</a>
  </div>
  <?php else: ?>
  <table class="dt-table<?= $manual ? ' is-sortable' : '' ?>">
    <thead><tr>
      <th class="dt-c-check"><input type="checkbox" data-checkall aria-label="Alle auswählen"></th>
      <?php if ($manual): ?><th class="dt-c-grip"><span class="sr-only">Reihenfolge</span></th><?php endif; ?>
      <th scope="col"><?= $sortLink($t['settings']['title_field'] ?: 'id', Tables::field($t, $t['settings']['title_field'])['label'] ?? 'Titel') ?></th>
      <?php foreach ($cols as $c): ?><th scope="col"><?= $sortLink($c['name'], $c['label']) ?></th><?php endforeach; ?>
      <?php if ($t['settings']['workflow']): ?><th scope="col"><?= $sortLink('status', 'Status') ?></th><?php endif; ?>
      <th scope="col"><?= $sortLink('updated_at', 'Geändert') ?></th>
    </tr></thead>
    <tbody data-rows>
    <?php foreach ($rows as $e): $url = Entries::url($t, $e); ?>
      <tr data-id="<?= (int) $e['id'] ?>"<?= $manual ? ' draggable="true"' : '' ?>>
        <td class="dt-c-check"><input type="checkbox" data-check value="<?= (int) $e['id'] ?>" aria-label="<?= e(Entries::title($t, $e)) ?> auswählen"></td>
        <?php if ($manual): ?><td class="dt-c-grip" aria-hidden="true">⋮⋮</td><?php endif; ?>
        <td class="dt-c-title">
          <?php if ($img && !empty($e[$img]) && ($m = \Core\Media::find((int) $e[$img]))): ?><img src="<?= e(\Core\Media::url($m, 480)) ?>" alt="" class="dt-thumb<?= $listImg === 'large' ? ' dt-thumb--l' : '' ?>" loading="lazy"><?php elseif ($listImg !== 'none'): ?><span class="dt-thumb dt-thumb--ph<?= $listImg === 'large' ? ' dt-thumb--l' : '' ?>" aria-hidden="true"><?= icon($t['icon']) ?></span><?php endif; ?>
          <a href="<?= e(url($base . '/' . $e['id'])) ?>"><?= e(Entries::title($t, $e)) ?></a>
          <?php if (!empty($shared) && !empty($e['suggest'])): $sp = $ownerPicks[$e['id']] ?? null; ?><small class="sh-pick sh-pick--<?= e($sp ?? 'suggested') ?>"><?= e(match ($sp) { 'visible' => __('übernommen'), 'featured' => __('hervorgehoben'), 'rejected' => __('abgelehnt'), 'hidden' => __('ausgeblendet'), default => __('vorgeschlagen') }) ?></small><?php endif; ?>
          <?php if (isset($extOrigin[$e['id']])): ?><small class="src-badge" title="<?= e(__('Aus der externen Quelle „{name}“ – nur lesbar', ['name' => $extOrigin[$e['id']]['name']])) ?>"><?= icon('plugs-connected') ?> <?= e(__('aus Quelle')) ?></small><?php endif; ?>
          <?php if ($url): ?><a class="dt-view" href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="Auf der Website ansehen">↗</a><?php endif; ?>
        </td>
        <?php foreach ($cols as $c): ?><td><?= $c['type'] === 'group' ? e(Entries::groupSummary($c, $e[$c['name']] ?? [])) : (in_array($c['type'], ['media', 'file'], true) ?$thumb(isset($e[$c['name']]) && $e[$c['name']] !== '' ? (int) $e[$c['name']] : null) : strip_tags(Entries::html($t, $e, $c['name'], ['link' => false]), '<br>')) ?></td><?php endforeach; ?>
        <?php if ($t['settings']['workflow']): ?><td><span class="dt-status dt-status--<?= e($e['status']) ?>"><?= $e['status'] === 'published' ? 'Online' : 'Entwurf' ?></span></td><?php endif; ?>
        <td class="adm-muted"><?= e(date('d.m.Y', strtotime((string) ($e['updated_at'] ?: $e['created_at'])))) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <footer class="dt-foot">
    <span><?= $total ?> <?= $total === 1 ? 'Eintrag' : 'Einträge' ?><?= $manual ? ' · Reihenfolge per Ziehen ändern' : '' ?></span>
    <?php if ($pages > 1): ?><span class="dt-pager">
      <?php if ($page > 1): ?><a href="<?= e($qs(['seite' => $page - 1])) ?>">← Zurück</a><?php endif; ?>
      Seite <?= $page ?> / <?= $pages ?>
      <?php if ($page < $pages): ?><a href="<?= e($qs(['seite' => $page + 1])) ?>">Weiter →</a><?php endif; ?>
    </span><?php endif; ?>
  </footer>
  <?php endif; ?>
</div>
