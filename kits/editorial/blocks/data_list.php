<?php
/**
 * Datenliste im Redaktionsstil (überschreibt den Kern-Block; Felder und Filter wie im Kern, zusätzliche Darstellungen):
 *   lead     Aufmacher + Raster – erster Eintrag groß, weitere in Spalten mit Linien
 *   river    Nachrichtenstrom – Datum, Dachzeile, Titel, Vorspann, kleines Bild
 *   brief    Kurz notiert – kompakt, ohne Bilder (Kasten)
 *   archive  Archiv – nach Monaten gegliedert
 *   agenda   Termine – großes Datum (Datumsfeld der Tabelle)
 *   people   Personen – Porträts mit Name und Funktion
 *   cards, list, compact – Karten, Liste mit Bild, kompakte Liste; table = Tabelle (Ausgabe des Kerns)
 * Rubrik-Leiste (filter_nav): Links je Auswahlwert (?rubrik=…) – ohne JavaScript, Seitenzahlen bleiben erhalten.
 * Geteilte Tabellen: Einträge anderer Websites tragen „von …“ (show_origin); „Hervorgehobene zuerst“ und „Quelle“ wie im Kern.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Calendar;
use Core\Data\Clamp;
use Core\Data\Entries;
use Core\Data\Shared;
use Core\Data\Tables;

$layout = in_array($d['layout'] ?? '', ['lead', 'river', 'brief', 'archive', 'agenda', 'people', 'cards', 'list', 'compact', 'table'], true) ? $d['layout'] : 'lead';
$t = Tables::findContent((string) ($d['table'] ?? ''));
if ($layout === 'table' && $t && empty($d['filter_nav'])) {
    include ROOT . '/app/Blocks/data_list.php';   // Tabelle: Ausgabe des Kerns (Stil: css/data.css)
    return;
}
if (!$t) {
    if (is_editing()) echo '<div class="wrap"><p class="empty-hint">Bitte in der Seitenleiste eine Tabelle wählen.</p></div>';
    return;
}
$pfx = $t['handle'] . '.';
$fields = [];
foreach ((array) ($d['fields'] ?? []) as $v) {
    if (str_starts_with((string) $v, $pfx)) $fields[] = substr((string) $v, strlen($pfx));
}
if (!$fields) {
    $fields = array_values(array_filter(['_title', Tables::imageField($t), 'published_at', $t['settings']['description_field']]));
    foreach ($t['fields'] as $f) if ($f['type'] === 'select') { array_splice($fields, 1, 0, [$f['name']]); break; }
}
$type = fn(string $f) => $f === 'published_at' ? 'date' : ($f === '_origin' ? 'origin' : (Tables::field($t, $f)['type'] ?? ''));
$pick = function (array $types) use ($fields, $type): ?string {
    foreach ($fields as $f) if (in_array($type($f), $types, true)) return $f;
    return null;
};
$imageField = $pick(['media']);
$kickerField = $pick(['select', 'multiselect']);
$dateField = $pick(['date', 'datetime']);
$textField = $pick(['textarea', 'richtext']);
$showTitle = in_array('_title', $fields, true) || !$fields;
$rest = array_values(array_filter($fields, fn($f) => !in_array($f, ['_title', $imageField, $kickerField, $dateField, $textField, '_origin'], true)));

// ------------------------------------------------------------------ Abfrage (wie im Kern) + Rubrik-Leiste
$query = (array) (app()->request?->query ?? []);
$limit = max(0, (int) ($d['limit'] ?? 0));
$page = !empty($d['paginate']) && $limit ? max(1, (int) ($query['seite'] ?? 1)) : 1;
$skip = max(0, (int) ($d['skip'] ?? 0));
$o = ['status' => 'published', 'where' => []];
if ($limit) { $o['limit'] = $limit; $o['offset'] = $skip + ($page - 1) * $limit; }
elseif ($skip) { $o['limit'] = 500; $o['offset'] = $skip; }
$ctx = app()->entry;
$op = (string) ($d['filter_op'] ?? '=');
if (str_starts_with((string) ($d['filter_field'] ?? ''), $pfx)) {
    $ff = substr($d['filter_field'], strlen($pfx));
    if ($op === 'current' || $op === 'same') {
        $val = !$ctx ? null : ($op === 'current' ? $ctx['entry']['id'] : ($ctx['entry'][$ff] ?? null));
        if (is_array($val)) $val = $val[0] ?? null;
        $o['where'][] = [$ff, '=', $val === null || $val === '' ? '-1' : (string) $val];
    } elseif (trim((string) ($d['filter_value'] ?? '')) !== '') {
        $o['where'][] = [$ff, $op, trim((string) $d['filter_value'])];
    }
}
$navField = str_starts_with((string) ($d['filter_nav'] ?? ''), $pfx) ? Tables::field($t, substr($d['filter_nav'], strlen($pfx))) : null;
$navField = $navField && in_array($navField['type'], ['select', 'multiselect'], true) ? $navField : null;
$active = null;
if ($navField && is_string($query['rubrik'] ?? null) && array_key_exists($query['rubrik'], (array) ($navField['options'] ?? []))) {
    $active = $query['rubrik'];
    $o['where'][] = [$navField['name'], $navField['type'] === 'multiselect' ? 'contains' : '=', $active];
}
if (!$o['where']) unset($o['where']);
if (!empty($d['exclude_current']) && $ctx && $ctx['table']['handle'] === $t['handle']) $o['exclude'] = $ctx['entry']['id'];
if (str_starts_with((string) ($d['sort_field'] ?? ''), $pfx)) $o['sort'] = substr($d['sort_field'], strlen($pfx));
if (!empty($d['sort_dir'])) $o['dir'] = $d['sort_dir'];
if (!empty($d['source'])) $o['source'] = (string) $d['source'];
if (!empty($d['featured_first'])) $o['featured_first'] = true;
$rows = Entries::query($t, $o);
if (!empty($d['link_detail'])) \Core\StructuredData::itemList($t, $rows, (string) ($d['title'] ?? ''));   // schema.org ItemList
$pages = !empty($d['paginate']) && $limit ? (int) ceil(max(0, Entries::count($t, $o) - $skip) / $limit) : 1;

$base = app()->currentPage && !$ctx ? \Core\Pages::url(app()->currentPage) : '';
$qs = fn(array $p) => $base . (($q = http_build_query(array_filter($p, fn($v) => $v !== null && $v !== ''))) !== '' ? '?' . $q : '') . '#' . $b->domId();
$hTag = !empty($d['title']) ? 'h3' : 'h2';
$ratio = (string) ($d['ratio'] ?? '3:2');
$cols = in_array((string) ($d['columns'] ?? ''), ['2', '3', '4'], true) ? (string) $d['columns'] : '3';
$showOrigin = ($d['show_origin'] ?? true) && Tables::isShared($t);

// ------------------------------------------------------------------ Bausteine je Eintrag
$ts = function (array $e) use ($dateField): ?int {
    if (!$dateField) return null;
    $v = $e[$dateField] ?? null;
    return $v ? (strtotime((string) $v) ?: null) : null;
};
$dateHtml = function (array $e, string $style = 'long') use ($ts, $dateField, $type): string {
    if (!($s = $ts($e))) return '';
    $label = date_local($s, $style) . ($type($dateField) === 'datetime' ? ', ' . lt('{time} Uhr', ['time' => date('H:i', $s)]) : '');
    return '<time datetime="' . e(date($type($dateField) === 'datetime' ? 'Y-m-d\TH:i' : 'Y-m-d', $s)) . '">' . e($label) . '</time>';
};
$origin = fn(array $e) => $showOrigin && Shared::isForeign($t, $e) && ($n = Entries::html($t, $e, '_origin')) !== ''
    ? '<span class="origin">' . e(lt('von')) . ' ' . $n . '</span>' : '';
$dek = function (array $e, int $max = 220) use ($t, $textField): string {
    if (!$textField) return '';
    $s = trim(preg_replace('~\s+~u', ' ', Entries::text($t, $e, $textField)));
    return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max - 1), " ,.;:–-") . ' …' : $s;
};
$extra = function (array $e) use ($t, $rest, $d): string {
    $h = '';
    foreach ($rest as $f) {
        [$cl, $v] = Clamp::text($t, $e, $f, $d);   // Textlänge: gekürzt als reiner Text
        if ($v !== '') $h .= '<p class="tz__f tz__f--' . e(Tables::field($t, $f)['type'] ?? 'x') . $cl . '">' . $v . '</p>';
    }
    return $h;
};
$teaser = function (array $e, int $i, string $variant, ?string $tag = null) use ($t, $d, $hTag, $ratio, $imageField, $kickerField, $showTitle, $dateHtml, $origin, $dek, $extra, $b): string {
    $title = Entries::title($t, $e);
    $url = !empty($d['link_detail']) ? Entries::href($t, $e) : null;
    $lead = $variant === 'lead';
    $tag ??= $hTag;
    $pic = '';
    if ($imageField && in_array($variant, ['lead', 'grid', 'row', 'river', 'people'], true)) {
        $r = $variant === 'people' ? ($ratio === '16:10' ? '4:5' : $ratio) : ($variant === 'river' ? '3:2' : $ratio);
        $sizes = $lead ? '(min-width: 1360px) 760px, (min-width: 960px) 58vw, 100vw' : ($variant === 'river' ? '(min-width: 960px) 240px, 34vw' : '(min-width: 1080px) 400px, 50vw');
        $img = Entries::html($t, $e, $imageField, ['ratio' => $r, 'sizes' => $sizes]);
        $pic = '<div class="media tz__media r-' . e(str_replace(':', '-', $r)) . ($img === '' ? ' media--ph' : '') . '">' . ($img ?: '<span class="media__ph" aria-hidden="true">' . e(mb_substr($title, 0, 1)) . '</span>') . '</div>';
    }
    $kicker = $kickerField ? Entries::html($t, $e, $kickerField) : '';
    $meta = array_filter([$dateHtml($e, $variant === 'brief' ? 'short' : 'long'), $origin($e)]);
    $h = '<li class="tz dl-item' . ($lead ? ' tz--lead' : '') . (($e['_pick'] ?? null) === 'featured' ? ' tz--featured' : '') . '">' . \Core\Data\EntryEdit::button($t, $e, $title);
    if ($variant === 'river') $h .= '<p class="tz__date">' . ($dateHtml($e, 'short') ?: '&nbsp;') . '</p>';
    $h .= $pic . '<div class="tz__body">';
    if ($kicker !== '') $h .= '<p class="kicker tz__kicker">' . $kicker . '</p>';
    if ($showTitle) {
        $h .= '<' . $tag . ' class="tz__title' . Clamp::titleClass($d) . '">' . ($url ? '<a class="tz__link" href="' . e($url) . '"' . ext_attrs($url) . '>' . e($title) . editorial_ext_note($url) . '</a>' : e($title)) . '</' . $tag . '>';
    }
    // Vorspann: fester Auszug; mit „Textlänge“ zusätzlich auf n Zeilen (Aufmacher bleibt beim festen Auszug)
    $lines = $lead ? 0 : Clamp::lines($d);
    if ($variant !== 'brief' && ($s = $dek($e, $lines ? min($lines * Clamp::CHARS_PER_LINE, 320) : ($lead ? 320 : ($variant === 'people' ? 140 : 200)))) !== '') $h .= '<p class="tz__dek' . Clamp::cls($lines) . '">' . e($s) . '</p>';
    $h .= $extra($e);
    if ($variant !== 'river' && $meta) $h .= '<p class="tz__meta">' . implode('<span aria-hidden="true"> · </span>', $meta) . '</p>';
    elseif ($variant === 'river' && ($o = $origin($e)) !== '') $h .= '<p class="tz__meta">' . $o . '</p>';
    if ($url && !$showTitle) $h .= '<a href="' . e($url) . '" class="tz__link tz__more">' . e(lt('Mehr')) . '<span class="sr-only"> ' . e(lt('zu {title}', ['title' => $title])) . '</span></a>';
    return $h . '</div></li>';
};
?>
<div class="wrap dl dlx dlx--<?= e($layout) ?>">
  <?php if (!empty($d['eyebrow']) || !empty($d['title']) || !empty($d['intro'])): ?>
  <header class="sec-head">
    <?php if (!empty($d['eyebrow'])): ?><p class="kicker"<?= $b->edit('eyebrow') ?>><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2"><span<?= $b->edit('title') ?>><?= e($d['title']) ?></span></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="lead"<?= $b->edit('intro') ?>><?= e($d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>

  <?php if ($navField): ?>
  <nav class="chips" aria-label="<?= e(lt('Nach {name} filtern', ['name' => Tables::label($navField)])) ?>">
    <ul class="chips__list">
      <li><a class="chip" href="<?= e($qs([])) ?>" rel="nofollow"<?= $active === null ? ' aria-current="true"' : '' ?>><?= e(lt('Alle')) ?></a></li>
      <?php foreach ((array) ($navField['options'] ?? []) as $k => $l): ?>
      <li><a class="chip" href="<?= e($qs(['rubrik' => (string) $k])) ?>" rel="nofollow"<?= $active === (string) $k ? ' aria-current="true"' : '' ?>><?= e(Tables::optionLabel($navField, (string) $k)) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>
  <?php endif; ?>

  <?php if (!$rows): ?>
  <p class="empty-hint"><?= e($d['empty_text'] ?: lt('Zurzeit gibt es hier keine Einträge.')) ?><?= is_editing() ? ' <small>(nur veröffentlichte Einträge erscheinen)</small>' : '' ?></p>

  <?php elseif ($layout === 'table'): ?>
  <?php $fieldsTable = array_values(array_filter($fields, fn($f) => $f !== $imageField)); ?>
  <div class="dl-tablewrap"><table class="dl-table">
    <thead><tr><?php foreach ($fieldsTable as $f): ?><th scope="col"><?= e($f === '_title' ? lt('Titel') : ($f === 'published_at' ? lt('Datum') : (($ff = Tables::field($t, $f)) ? Tables::label($ff) : $f))) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($rows as $e): $url = !empty($d['link_detail']) ? Entries::href($t, $e) : null; ?><tr>
      <?php foreach ($fieldsTable as $f): ?><td><?= $f === '_title' ? ($url ? '<a href="' . e($url) . '">' . e(Entries::title($t, $e)) . '</a>' : e(Entries::title($t, $e))) : Entries::html($t, $e, $f) ?></td><?php endforeach; ?>
    </tr><?php endforeach; ?></tbody>
  </table></div>

  <?php elseif ($layout === 'agenda'): ?>
  <ol class="agenda" role="list">
    <?php foreach ($rows as $i => $e): $s = $ts($e); $title = Entries::title($t, $e); $url = !empty($d['link_detail']) ? Entries::href($t, $e) : null; ?>
    <li class="agenda__item dl-item"><?= \Core\Data\EntryEdit::button($t, $e, $title) ?>
      <p class="agenda__date"<?= $s ? '' : ' aria-hidden="true"' ?>><?php if ($s): $dt = (new DateTimeImmutable('@' . $s))->setTimezone(Calendar::tz()); ?><span class="agenda__day"><?= e(date('j', $s)) ?></span><span class="agenda__mon"><?= e(Calendar::fmt($dt, 'LLL')) ?></span><span class="agenda__wd"><?= e(Calendar::fmt($dt, 'EEE')) ?></span><?php endif; ?></p>
      <div class="agenda__body">
        <?php if ($kickerField && ($k = Entries::html($t, $e, $kickerField)) !== ''): ?><p class="kicker"><?= $k ?></p><?php endif; ?>
        <<?= $hTag ?> class="agenda__title"><?= $url ? '<a class="tz__link" href="' . e($url) . '">' . e($title) . '</a>' : e($title) ?></<?= $hTag ?>>
        <p class="tz__meta"><?= implode('<span aria-hidden="true"> · </span>', array_filter([$dateHtml($e), strip_tags($extra($e), '<a><time>'), $origin($e)])) ?></p>
      </div>
    </li>
    <?php endforeach; ?>
  </ol>

  <?php elseif ($layout === 'archive'): $month = null; ?>
  <div class="archive">
    <?php foreach ($rows as $i => $e): $s = $ts($e); $m = $s ? date('Y-m', $s) : ''; ?>
    <?php if ($m !== $month): if ($month !== null) echo '</ol>'; $month = $m; ?>
    <<?= $hTag ?> class="archive__month"><?= $s ? e(Calendar::fmt((new DateTimeImmutable('@' . $s))->setTimezone(Calendar::tz()), 'LLLL y')) : e(lt('Ohne Datum')) ?></<?= $hTag ?>>
    <ol class="tz-grid tz-grid--archive" role="list">
    <?php endif; ?>
    <?= $teaser($e, $i, 'brief', $hTag === 'h3' ? 'h4' : 'h3') ?>
    <?php endforeach; ?>
    </ol>
  </div>

  <?php else:
    $variant = ['lead' => 'grid', 'cards' => 'grid', 'list' => 'row', 'compact' => 'brief', 'brief' => 'brief', 'river' => 'river', 'people' => 'people'][$layout]; ?>
  <ol class="tz-grid tz-grid--<?= e($layout) ?> cols-<?= e($cols) ?>" role="list">
    <?php foreach ($rows as $i => $e): ?><?= $teaser($e, $i, $layout === 'lead' && $i === 0 && $page === 1 ? 'lead' : $variant) ?><?php endforeach; ?>
  </ol>
  <?php endif; ?>

  <?php if ($pages > 1): ?>
  <nav class="pager" aria-label="<?= e(lt('Seiten')) ?>">
    <?php if ($page > 1): ?><a class="pager__step" href="<?= e($qs(['rubrik' => $active, 'seite' => $page > 2 ? $page - 1 : null])) ?>" rel="prev"><span aria-hidden="true">←</span> <?= e(lt('Neuere')) ?></a><?php endif; ?>
    <span class="pager__info"><?= e(lt('Seite {page} von {pages}', ['page' => $page, 'pages' => $pages])) ?></span>
    <?php if ($page < $pages): ?><a class="pager__step" href="<?= e($qs(['rubrik' => $active, 'seite' => $page + 1])) ?>" rel="next"><?= e(lt('Ältere')) ?> <span aria-hidden="true">→</span></a><?php endif; ?>
  </nav>
  <?php endif; ?>

  <?php if (!empty($d['more_label']) && !empty($d['more_link'])): ?>
  <p class="dlx__more"><a class="more" href="<?= e(link_href((string) $d['more_link'])) ?>"><span<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></span> <span aria-hidden="true">→</span></a></p>
  <?php endif; ?>
<?= \Core\Data\EntryEdit::newButton($t) ?></div>
