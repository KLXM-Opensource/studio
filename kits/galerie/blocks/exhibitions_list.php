<?php
/**
 * Ausstellungen: laufende / kommende / vergangene – Status je Tag aus Beginn und Ende berechnet (galerie_exhibitions()).
 * Anzeige: Reiter (js/tabs.js, ohne JavaScript untereinander) oder Abschnitte; vergangene nach Jahren gruppiert.
 * Varianten: list (Zeilen mit kleinem Bild) · cards (auto-fit-Raster) · timeline (Jahre links, Container-Query) · fairs (kompakt, Messen).
 * Auf der Detailseite eines Künstlers: nur dessen Ausstellungen; auf der Detailseite einer Ausstellung: ohne die aufgerufene.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;

$v = $b->variant() ?: 'list';
$t = galerie_table('exhibitions');
if (!$t) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Die Tabelle „Ausstellungen“ fehlt (Website → Galerie → Datentabellen).</p></div>';
    return;
}
$show = (string) ($d['show'] ?? 'tabs');
$venue = (string) ($d['venue'] ?? '');
$ctx = app()->entry;
$artistT = galerie_table('artists');
$o = [];
if ($venue !== '' && $venue !== 'no_fairs') $o['venue'] = [$venue];
if (!empty($d['auto_artist']) && $ctx && $artistT && $ctx['table']['handle'] === $artistT['handle']) $o['artist'] = (int) $ctx['entry']['id'];
if (!empty($d['exclude_current']) && $ctx && $ctx['table']['handle'] === $t['handle']) $o['exclude'] = (int) $ctx['entry']['id'];
$all = galerie_exhibitions($o);
if ($venue === 'no_fairs') $all = array_values(array_filter($all, fn($x) => ($x['ort'] ?? '') !== 'messe'));

$labels = ['current' => lt('Aktuell'), 'upcoming' => lt('Demnächst'), 'past' => lt('Archiv')];
$want = match ($show) {
    'current' => ['current'], 'upcoming' => ['upcoming'], 'past' => ['past'], 'current_upcoming' => ['current', 'upcoming'],
    default => ['current', 'upcoming', 'past'],
};
$limit = max(0, (int) ($d['limit'] ?? 0));
$groups = [];
foreach ($want as $st) {
    $rows = array_values(array_filter($all, fn($x) => $x['_status'] === $st));
    if ($limit) $rows = array_slice($rows, 0, $limit);
    if ($rows) $groups[$st] = $rows;
}
$grouped = in_array($show, ['tabs', 'sections'], true);
// Überschriften-Ebenen: Abschnitt (h2) → Gruppe → Jahr → Eintrag
$lv = trim((string) ($d['title'] ?? '')) !== '' ? 3 : 2;
$gl = $lv;
$yl = $grouped ? $lv + 1 : $lv;
$il = min(6, $yl + 1);
$showImg = !empty($d['show_image']);
$ratio = (string) ($d['ratio'] ?? '') ?: '4:3';

/** Ein Eintrag je Variante */
$item = function (array $x) use ($t, $v, $il, $showImg, $ratio): string {
    $href = Entries::href($t, $x);
    $title = e(Entries::title($t, $x));
    $titleHtml = $href && !is_editing() ? '<a class="cover-link" href="' . e($href) . '">' . $title . '</a>' : $title;
    $artists = galerie_artist_names((array) ($x['kuenstler'] ?? []), false);
    $sub = trim((string) ($x['untertitel'] ?? ''));
    $dates = galerie_dates($x);
    $place = galerie_venue($x);
    $badge = $x['_status'] !== 'past' ? galerie_status_badge($x, 'xl__badge') : '';
    $edit = edit_link('entry:' . $t['handle'] . ':' . $x['id'], Entries::title($t, $x));
    if ($v === 'fairs') {
        return '<li class="xl-fair">' . $edit . '<p class="xl-fair__dates">' . e($dates) . '</p>'
            . '<h' . $il . ' class="xl-fair__title">' . $titleHtml . '</h' . $il . '>'
            . '<p class="xl-fair__place">' . e(trim((string) ($x['ort_text'] ?? '')) ?: $place) . '</p>'
            . ($artists !== '' ? '<p class="xl-fair__artists">' . $artists . '</p>' : '') . '</li>';
    }
    $body = '<div class="xl__body">' . $badge
        . ($artists !== '' && $v === 'cards' ? '<p class="xl__artists">' . $artists . '</p>' : '')
        . '<h' . $il . ' class="xl__title">' . $titleHtml . '</h' . $il . '>'
        . ($sub !== '' ? '<p class="xl__sub">' . e($sub) . '</p>' : '')
        . ($artists !== '' && $v !== 'cards' ? '<p class="xl__artists">' . $artists . '</p>' : '')
        . '<p class="xl__meta">' . e($dates) . ($place !== '' ? '<span class="xl__sep" aria-hidden="true"> · </span>' . e($place) : '') . '</p>'
        . (($op = galerie_opening($x)) !== '' ? '<p class="xl__opening">' . e($op) . '</p>' : '')
        . '</div>';
    $imgId = !empty($x['bild']) ? (int) $x['bild'] : null;
    $pic = '';
    if ($v === 'cards') $pic = galerie_image($imgId, '(min-width: 1080px) 440px, (min-width: 640px) 50vw, 100vw', $ratio, 'xl__img');
    elseif ($showImg && $imgId) $pic = galerie_image($imgId, '(min-width: 640px) 220px, 40vw', '4:3', 'xl__img');
    return '<li class="xl__item' . ($pic !== '' ? ' xl__item--img' : '') . '" data-reveal>' . $edit . $pic . $body . '</li>';
};

/** Liste einer Gruppe – vergangene (und Zeitleiste) nach Jahren */
$list = function (string $st, array $rows) use ($v, $item, $yl, $b): string {
    $cls = match ($v) { 'cards' => 'grid ' . galerie_min($b->data) . ' xl xl--cards', 'fairs' => 'xl-fairs', default => 'xl xl--' . $v };
    $byYear = $v === 'timeline' || ($st === 'past' && $v !== 'fairs');
    if (!$byYear) return '<ul class="' . e($cls) . '" role="list">' . implode('', array_map($item, $rows)) . '</ul>';
    $years = [];
    foreach ($rows as $x) $years[substr((string) $x['beginn'], 0, 4) ?: '—'][] = $x;
    $h = '<div class="xl-years' . ($v === 'timeline' ? ' xl-years--timeline' : '') . '">';
    foreach ($years as $y => $ys) {
        $h .= '<section class="xl-year"><h' . $yl . ' class="xl-year__h">' . e((string) $y) . '</h' . $yl . '><ul class="' . e($cls) . '" role="list">' . implode('', array_map($item, $ys)) . '</ul></section>';
    }
    return $h . '</div>';
};
$id = $b->domId();
$empty = trim((string) ($d['empty_text'] ?? ''));
?>
<div class="wrap">
  <?= galerie_head($b) ?>
  <?php if (!$groups): ?>
    <?php if ($empty !== '' || is_editing()): ?><p class="xl-empty"<?= $b->edit('empty_text') ?>><?= galerie_title($empty !== '' ? $empty : 'Keine passenden Ausstellungen.') ?></p><?php endif; ?>
  <?php elseif ($show === 'tabs' && count($groups) > 1): ?>
  <div class="xl-tabs" data-tabs>
    <div class="xl-tabs__list" role="tablist"<?= trim((string) $d['title']) !== '' ? ' aria-labelledby="' . e($b->titleId()) . '"' : ' aria-label="' . e(lt('Ausstellungen')) . '"' ?> hidden>
      <?php $i = 0; foreach ($groups as $st => $rows): ?><button type="button" class="xl-tabs__tab" role="tab" id="<?= e("$id-tab-$st") ?>" aria-controls="<?= e("$id-$st") ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"<?= $i++ ? ' tabindex="-1"' : '' ?>><?= e($labels[$st]) ?> <span class="xl-tabs__n"><?= count($rows) ?></span></button><?php endforeach; ?>
    </div>
    <?php foreach ($groups as $st => $rows): ?>
    <section class="xl-group" id="<?= e("$id-$st") ?>" aria-labelledby="<?= e("$id-tab-$st") ?>">
      <h<?= $gl ?> class="xl-group__h"><?= e($labels[$st]) ?></h<?= $gl ?>>
      <?= $list($st, $rows) ?>
    </section>
    <?php endforeach; ?>
  </div>
  <?php elseif ($grouped): ?>
    <?php foreach ($groups as $st => $rows): ?>
    <section class="xl-group xl-group--open">
      <h<?= $gl ?> class="xl-group__h"><?= e($labels[$st]) ?></h<?= $gl ?>>
      <?= $list($st, $rows) ?>
    </section>
    <?php endforeach; ?>
  <?php else: ?>
    <?php /* ohne Gruppen: eine gemeinsame Liste (laufende vor kommenden) – Archiv bleibt nach Jahren gegliedert */ ?>
    <?= $list(count($groups) === 1 ? (string) array_key_first($groups) : 'current', array_merge(...array_values($groups))) ?>
  <?php endif; ?>
  <?php if (trim((string) ($d['more_label'] ?? '')) !== '' && trim((string) ($d['more_link'] ?? '')) !== ''): ?>
  <p class="more-row"><a class="btn btn--secondary" <?= galerie_link_attrs($d['more_link']) ?>><span<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></span></a></p>
  <?php endif; ?>
</div>
