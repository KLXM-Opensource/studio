<?php
/**
 * Datenliste: Einträge einer Datentabelle ausgeben (Kern-Block, vom Theme überschreibbar).
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Entries;
use Core\Data\Tables;

$t = Tables::findContent((string) ($d['table'] ?? ''));
$wrap = app()->theme->def['container_class'] ?? 'wrap';
$btn = app()->theme->def['button_class'] ?? 'btn btn--primary';
if (!$t) {
    if (is_editing()) echo '<div class="' . e($wrap) . '"><p class="dl-empty">Bitte in der Seitenleiste eine Tabelle wählen.</p></div>';
    return;
}
// Gewählte Felder dieser Tabelle (ohne Präfix); Standard: Titel, Bild, Beschreibung
$fields = [];
foreach ((array) ($d['fields'] ?? []) as $v) {
    if (str_starts_with((string) $v, $t['handle'] . '.')) $fields[] = substr((string) $v, strlen($t['handle']) + 1);
}
if (!$fields) {
    $fields = array_values(array_filter(['_title', Tables::imageField($t), $t['settings']['description_field']]));
}
$imageField = null;
foreach ($fields as $f) {
    if ((Tables::field($t, $f)['type'] ?? '') === 'media') { $imageField = $f; break; }
}
$other = array_values(array_filter($fields, fn($f) => $f !== $imageField && $f !== '_title'));
$showTitle = in_array('_title', $fields, true);

$limit = max(0, (int) ($d['limit'] ?? 0));
$page = !empty($d['paginate']) && $limit ? max(1, (int) (app()->request?->query['seite'] ?? 1)) : 1;
$o = ['status' => 'published'];
if ($limit) { $o['limit'] = $limit; $o['offset'] = ($page - 1) * $limit; }
$pfx = $t['handle'] . '.';
$ctx = app()->entry;
$op = (string) ($d['filter_op'] ?? '=');
if (str_starts_with((string) ($d['filter_field'] ?? ''), $pfx)) {
    $ff = substr($d['filter_field'], strlen($pfx));
    if ($op === 'current' || $op === 'same') {
        // Kontext-Filter auf Detailseiten: verknüpft mit / gleich wie der aufgerufene Eintrag
        $val = !$ctx ? null : ($op === 'current' ? $ctx['entry']['id'] : ($ctx['entry'][$ff] ?? null));
        if (is_array($val)) $val = $val[0] ?? null;
        $o['where'] = [[$ff, '=', $val === null || $val === '' ? '-1' : (string) $val]];
    } elseif (trim((string) ($d['filter_value'] ?? '')) !== '') {
        $o['where'] = [[$ff, $op, trim((string) $d['filter_value'])]];
    }
}
if (!empty($d['exclude_current']) && $ctx && $ctx['table']['handle'] === $t['handle']) $o['exclude'] = $ctx['entry']['id'];
if (str_starts_with((string) ($d['sort_field'] ?? ''), $pfx)) $o['sort'] = substr($d['sort_field'], strlen($pfx));
if (!empty($d['sort_dir'])) $o['dir'] = $d['sort_dir'];
// Geteilte Tabellen: Quelle (leer = wie für die Website eingestellt), hervorgehobene Einträge zuerst
if (!empty($d['source'])) $o['source'] = (string) $d['source'];
if (!empty($d['featured_first'])) $o['featured_first'] = true;
$rows = Entries::query($t, $o);
if (!empty($d['link_detail'])) \Core\StructuredData::itemList($t, $rows, strip_emphasis((string) ($d['title'] ?? '')));   // schema.org ItemList
$pages = !empty($d['paginate']) && $limit ? (int) ceil(Entries::count($t, $o) / $limit) : 1;
$layout = in_array($d['layout'] ?? '', ['cards', 'list', 'compact', 'table'], true) ? $d['layout'] : 'cards';
$link = fn(array $e) => !empty($d['link_detail']) ? Entries::href($t, $e) : null;
$hTag = !empty($d['title']) ? 'h3' : 'h2';
// Spaltenköpfe der Tabelle: Titel-Feld, Sonderfelder „_when“ (Termin) und „published_at“ wie im Block „Datensatz-Felder“
$colLabel = fn(string $f) => match (true) {
    $f === '_title' => ($tf = Tables::field($t, $t['settings']['title_field'])) ? Tables::label($tf) : lt('Titel'),
    ($ff = Tables::field($t, $f)) !== null => Tables::label($ff),
    $f === '_when' => lt('Termin'),
    $f === 'published_at' => lt('Datum'),
    default => $f,
};
?>
<div class="<?= e($wrap) ?> dl dl--<?= e($layout) ?>">
  <?php if (!empty($d['eyebrow']) || !empty($d['title']) || !empty($d['intro'])): ?>
  <header class="dl-head">
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2 h2--m dl-title"><span<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></span></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="muted dl-intro"<?= $b->edit('intro') ?>><?= e($d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>

  <?php if (!$rows): ?>
  <p class="dl-empty"><?= e($d['empty_text'] ?: lt('Zurzeit gibt es hier keine Einträge.')) ?><?= is_editing() ? ' <small>(nur veröffentlichte Einträge erscheinen)</small>' : '' ?></p>
  <?php elseif ($layout === 'table'): ?>
  <div class="dl-tablewrap"><table class="dl-table">
    <thead><tr><?php foreach ($fields as $f): if ($f === $imageField) continue; ?><th scope="col"><?= e($colLabel($f)) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach ($rows as $e): $url = $link($e); ?>
      <tr><?php foreach ($fields as $f): if ($f === $imageField) continue; ?>
        <td><?php if ($f === '_title'): ?><?= $url ? '<a href="' . e($url) . '">' . e(Entries::title($t, $e)) . '</a>' : e(Entries::title($t, $e)) ?><?= \Core\Data\EntryEdit::button($t, $e) ?><?php else: ?><?= Entries::html($t, $e, $f) ?><?php endif; ?></td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
  <ul class="dl-items<?= $layout === 'cards' ? ' dl-cols-' . e((string) ($d['columns'] ?: 3)) : '' ?>" role="list">
    <?php foreach ($rows as $e): $url = $link($e); $title = Entries::title($t, $e); ?>
    <li class="dl-item<?= ($e['_pick'] ?? null) === 'featured' ? ' dl-item--featured' : '' ?>" data-reveal="up"><?= \Core\Data\EntryEdit::button($t, $e, $title) ?>
      <?php if ($imageField && $layout !== 'compact'): $pic = Entries::html($t, $e, $imageField, ['ratio' => $d['ratio'] ?? '16:10', 'sizes' => $layout === 'cards' ? '(min-width: 1080px) 400px, 100vw' : '(min-width: 1080px) 320px, 40vw']); ?>
      <div class="dl-img ratio-<?= e(str_replace(':', '-', (string) ($d['ratio'] ?? '16:10'))) ?>"><?= $pic ?: '<span class="dl-ph" aria-hidden="true"></span>' ?></div>
      <?php endif; ?>
      <div class="dl-body">
        <?php $meta = []; foreach ($other as $f) { if (in_array(Tables::field($t, $f)['type'] ?? 'date', ['date', 'datetime', 'select', 'time'], true) || $f === 'published_at') { $h = Entries::html($t, $e, $f); if ($h !== '') $meta[] = $h; } } ?>
        <?php if ($meta): ?><p class="dl-meta"><?= implode('<span aria-hidden="true"> · </span>', $meta) ?></p><?php endif; ?>
        <?php if ($showTitle): ?><<?= $hTag ?> class="dl-name"><?= $url ? '<a href="' . e($url) . '" class="dl-link">' . e($title) . '</a>' : e($title) ?></<?= $hTag ?>><?php endif; ?>
        <?php foreach ($other as $f): $type = Tables::field($t, $f)['type'] ?? 'date'; if (in_array($type, ['date', 'datetime', 'select', 'time'], true)) continue; $html = Entries::html($t, $e, $f, ['link' => false]); if ($html === '') continue; ?>
        <div class="dl-f dl-f--<?= e($type) ?>"><?= $html ?></div>
        <?php endforeach; ?>
        <?php if ($url && !$showTitle): ?><a href="<?= e($url) ?>" class="dl-link dl-more-link"><?= e(lt('Mehr')) ?><span class="sr-only"> <?= e(lt('zu {title}', ['title' => $title])) ?></span></a><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if ($pages > 1): $base = app()->currentPage ? \Core\Pages::url(app()->currentPage) : url('/'); ?>
  <nav class="dl-pager" aria-label="<?= e(lt('Seiten')) ?>">
    <?php if ($page > 1): ?><a href="<?= e($base . ($page > 2 ? '?seite=' . ($page - 1) : '')) ?>" rel="prev">← <?= e(lt('Neuere')) ?></a><?php endif; ?>
    <span><?= e(lt('Seite {page} von {pages}', ['page' => $page, 'pages' => $pages])) ?></span>
    <?php if ($page < $pages): ?><a href="<?= e($base . '?seite=' . ($page + 1)) ?>" rel="next"><?= e(lt('Ältere')) ?> →</a><?php endif; ?>
  </nav>
  <?php endif; ?>

  <?php if (!empty($d['more_label']) && !empty($d['more_link'])): ?>
  <p class="dl-more"><a class="<?= e($btn) ?>" href="<?= e(link_href((string) $d['more_link'])) ?>"<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></a></p>
  <?php endif; ?>
<?= \Core\Data\EntryEdit::newButton($t) ?></div>
