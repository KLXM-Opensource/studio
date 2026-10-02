<?php
/**
 * Geteilte Tabelle: Einträge anderer Websites (nur lesen) mit Auswahl dieser Website.
 * @var array $t  @var array $rows  @var string $src owner|members|suggestions  @var string $filter  @var string $q  @var array $shown  @var string $origin
 */
use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;

$base = '/admin/data/' . $t['handle'];
$key = $t['shared']['key'];
$isOwner = Shared::isOwner($t);
$canPick = can('data.publish', $t['handle']);
$ownerName = Shared::siteInfo($t['shared']['owner'], $key)['name'];
$heading = match ($src) {
    'owner' => term('shared_from_owner'),
    'members' => term('shared_from_members'),
    default => __('Vorschläge'),
};
$here = $base . '/shared?' . http_build_query(array_filter(['src' => $src, 'pick' => $filter, 'q' => $q, 'origin' => $origin]));
$filters = $src === 'suggestions'
    ? ['' => __('Offen'), 'visible' => __('Übernommen'), 'featured' => __('Hervorgehoben'), 'rejected' => __('Abgelehnt')]
    : ['' => __('Alle'), 'none' => __('Ohne Auswahl'), 'featured' => __('Hervorgehoben'), 'hidden' => __('Ausgeblendet')] + ($isOwner ? ['visible' => __('Übernommen'), 'rejected' => __('Abgelehnt')] : []);
$stateLabel = ['visible' => __('übernommen'), 'featured' => __('hervorgehoben'), 'hidden' => __('ausgeblendet'), 'rejected' => __('abgelehnt')];
$cols = array_slice(array_values(array_filter($t['fields'], fn($f) => !empty($f['in_list']) && $f['name'] !== $t['settings']['title_field'] && !in_array($f['type'], ['media', 'file', 'group'], true))), 0, 2);
$members = $t['shared']['members'];
?>
<header class="adm-head dt-head">
  <div><p class="adm-eyebrow"><a href="<?= e(url($base)) ?>"><span aria-hidden="true"><?= icon($t['icon']) ?></span> <?= e($t['name']) ?></a> · <span class="dt-nav__shared"><?= e(__('geteilt')) ?></span></p>
    <h1><?= e($heading) ?></h1>
    <p class="adm-muted"><?= e(match ($src) {
        'owner' => __('Einträge von „{site}“ – nur lesbar. Sie entscheiden, ob sie auf dieser Website erscheinen (Anzeige auf dieser Website) und können einzelne ausblenden oder hervorheben.', ['site' => $ownerName]),
        'members' => $isOwner ? __('Veröffentlichte Einträge der übrigen Websites – nur lesbar. Übernehmen zeigt einen Eintrag auf dieser Website.') : __('Veröffentlichte Einträge der übrigen Websites – nur lesbar.'),
        default => __('Einträge, die andere Websites Ihnen vorschlagen. Übernehmen zeigt sie auf dieser Website, Hervorheben zusätzlich betont.'),
    }) ?></p></div>
</header>

<div class="dt-list">
  <div class="dt-bar">
    <nav class="fx-seg dt-seg" aria-label="<?= e(__('Auswahl')) ?>">
      <?php foreach ($filters as $k => $l): ?><a href="<?= e(url($base . '/shared') . '?' . http_build_query(array_filter(['src' => $src, 'pick' => $k, 'q' => $q]))) ?>"<?= $filter === $k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
    </nav>
    <form class="fx-search dt-search" method="get" action="<?= e(url($base . '/shared')) ?>" role="search">
      <input type="hidden" name="src" value="<?= e($src) ?>">
      <?php if ($src !== 'owner' && count($members) > 1): ?>
      <select name="origin" aria-label="<?= e(__('Website')) ?>"><option value=""><?= e(__('Alle Websites')) ?></option>
        <?php foreach ($members as $m): if ($m === site()->key) continue; ?><option value="<?= e($m) ?>"<?= $origin === $m ? ' selected' : '' ?>><?= e(Shared::siteInfo($m, $key)['name']) ?></option><?php endforeach; ?>
      </select>
      <?php endif; ?>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(__('Suchen')) ?>" aria-label="<?= e(__('Einträge durchsuchen')) ?>">
      <button class="adm-btn adm-btn--small adm-btn--ghost" type="submit"><?= e(__('Filtern')) ?></button>
    </form>
  </div>

  <?php if (!$rows): ?>
  <div class="dt-empty"><span class="dt-icon" aria-hidden="true"><?= icon($t['icon']) ?></span>
    <p><?= e($src === 'suggestions' && $filter === '' ? __('Keine offenen Vorschläge.') : __('Keine Einträge.')) ?></p></div>
  <?php else: ?>
  <form method="post" action="<?= e(url($base . '/pick')) ?>">
    <?= csrf_field() ?><input type="hidden" name="back" value="<?= e($here) ?>">
    <table class="dt-table sh-table">
      <thead><tr>
        <?php if ($canPick): ?><th class="dt-c-check"><input type="checkbox" data-checkall aria-label="<?= e(__('Alle auswählen')) ?>"></th><?php endif; ?>
        <th scope="col"><?= e(Tables::field($t, $t['settings']['title_field'])['label'] ?? __('Titel')) ?></th>
        <?php foreach ($cols as $c): ?><th scope="col"><?= e($c['label']) ?></th><?php endforeach; ?>
        <th scope="col"><?= e(__('Website')) ?></th>
        <th scope="col"><?= e(__('Auf dieser Website')) ?></th>
        <?php if ($canPick): ?><th scope="col"><span class="sr-only"><?= e(__('Aktionen')) ?></span></th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($rows as $e): $pick = $e['_pick']; $on = isset($shown[$e['id']]); $origin = Shared::originUrl($t, $e); ?>
        <tr>
          <?php if ($canPick): ?><td class="dt-c-check"><input type="checkbox" name="ids[]" data-check value="<?= (int) $e['id'] ?>" aria-label="<?= e(__('{title} auswählen', ['title' => Entries::title($t, $e)])) ?>"></td><?php endif; ?>
          <td class="dt-c-title"><div class="dt-c-title__in"><a href="<?= e(url($base . '/' . $e['id'])) ?>"><?= e(Entries::title($t, $e)) ?></a>
            <?php if ($origin): ?><a class="dt-view" href="<?= e($origin) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('Auf Ursprungs-Website öffnen')) ?>">↗</a><?php endif; ?></div></td>
          <?php foreach ($cols as $c): ?><td><?= strip_tags(Entries::html($t, $e, $c['name'], ['link' => false]), '<br>') ?></td><?php endforeach; ?>
          <td><?= e(Shared::siteInfo((string) $e['origin_site'], $key)['name']) ?></td>
          <td><span class="dt-status dt-status--<?= $on ? 'published' : 'draft' ?>"><?= e($on ? __('sichtbar') : __('nicht sichtbar')) ?></span>
            <?php if ($pick): ?> <small class="sh-pick sh-pick--<?= e($pick) ?>"><?= e($stateLabel[$pick] ?? $pick) ?></small><?php endif; ?></td>
          <?php if ($canPick): ?>
          <td class="sh-actions">
            <?php if ($isOwner && !in_array($pick, ['visible', 'featured'], true)): ?><button class="adm-btn adm-btn--small" name="one" value="<?= (int) $e['id'] ?>:visible"><?= e(__('Übernehmen')) ?></button><?php endif; ?>
            <?php if ($pick !== 'featured'): ?><button class="adm-btn adm-btn--small adm-btn--ghost" name="one" value="<?= (int) $e['id'] ?>:featured"><?= e(__('Hervorheben')) ?></button><?php endif; ?>
            <?php if ($isOwner && $src === 'suggestions' && $pick === null): ?><button class="adm-btn adm-btn--small adm-btn--ghost adm-btn--danger-text" name="one" value="<?= (int) $e['id'] ?>:rejected"><?= e(__('Ablehnen')) ?></button><?php endif; ?>
            <?php if ($pick !== 'hidden' && ($on || !$isOwner)): ?><button class="adm-btn adm-btn--small adm-btn--ghost" name="one" value="<?= (int) $e['id'] ?>:hidden"><?= e(__('Ausblenden')) ?></button><?php endif; ?>
            <?php if ($pick !== null): ?><button class="adm-btn adm-btn--small adm-btn--ghost" name="one" value="<?= (int) $e['id'] ?>:reset"><?= e(__('Zurücksetzen')) ?></button><?php endif; ?>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($canPick): ?>
    <footer class="dt-foot sh-bulk">
      <span><?= e(count($rows) === 1 ? __('1 Eintrag') : __('{n} Einträge', ['n' => count($rows)])) ?> · <?= e(__('Ausgewählte:')) ?></span>
      <span class="adm-row">
        <?php if ($isOwner): ?><button class="adm-btn adm-btn--small" name="state" value="visible"><?= e(__('Übernehmen')) ?></button><?php endif; ?>
        <button class="adm-btn adm-btn--small adm-btn--ghost" name="state" value="featured"><?= e(__('Hervorheben')) ?></button>
        <?php if ($isOwner && $src === 'suggestions'): ?><button class="adm-btn adm-btn--small adm-btn--ghost" name="state" value="rejected"><?= e(__('Ablehnen')) ?></button><?php endif; ?>
        <button class="adm-btn adm-btn--small adm-btn--ghost" name="state" value="hidden"><?= e(__('Ausblenden')) ?></button>
        <button class="adm-btn adm-btn--small adm-btn--ghost" name="state" value="reset"><?= e(__('Zurücksetzen')) ?></button>
      </span>
    </footer>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>
