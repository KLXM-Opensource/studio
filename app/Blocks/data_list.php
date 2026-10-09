<?php
/**
 * Datenliste: Einträge einer Datentabelle ausgeben (Kern-Block, vom Theme überschreibbar).
 *
 * Darstellung „Verzeichnis“ (directory): Karten mit eingepasstem Bild auf einer Kachel (Logos), Auswahl-/Mehrfachauswahl-
 * Felder als Etiketten (dl-chip dl-tone-1…5 nach Reihenfolge der Option). Besucher-Filter (alle Darstellungen): bis zu zwei
 * Auswahlfelder als Filter-Schaltflächen (?{feldname}={option}), Suchfeld (?q=) und A–Z-Sprungleiste – serverseitig per GET,
 * ohne JavaScript; Aufrufe mit Parametern umgehen den Seiten-Cache. Ohne diese Optionen ist die Ausgabe unverändert.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Clamp;
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
// Besucher-Filter: Schaltflächen je Auswahlfeld, Suche, A–Z (Verzeichnis, Mitglieder, Partner …)
$query = (array) (app()->request?->query ?? []);
$vfilters = [];   // [name => ['field' => …, 'options' => [key => label], 'sel' => ?string]]
foreach (['visitor_filter', 'visitor_filter2'] as $k) {
    if (!str_starts_with((string) ($d[$k] ?? ''), $pfx)) continue;
    $vn = substr($d[$k], strlen($pfx));
    $vfd = Tables::field($t, $vn);
    if (!$vfd || !in_array($vfd['type'], ['select', 'multiselect'], true) || isset($vfilters[$vn]) || in_array($vn, ['q', 'seite'], true)) continue;
    $vopts = (array) ($vfd['options'] ?? []);
    $sel = is_string($query[$vn] ?? null) && array_key_exists($query[$vn], $vopts) ? $query[$vn] : null;
    $vfilters[$vn] = ['field' => $vfd, 'options' => $vopts, 'sel' => $sel];
    if ($sel !== null) $o['where'] = array_merge((array) ($o['where'] ?? []), [[$vn, '=', $sel]]);
}
$vq = !empty($d['visitor_search']) && is_string($query['q'] ?? null) ? trim(mb_substr($query['q'], 0, 80)) : '';
if ($vq !== '') $o['q'] = $vq;
$filtering = $vq !== '' || array_filter(array_column($vfilters, 'sel'), fn($v) => $v !== null);
$layout = in_array($d['layout'] ?? '', ['cards', 'list', 'compact', 'table', 'directory'], true) ? $d['layout'] : 'cards';
// A–Z: nur ohne Blättern (Sprungmarken auf dieser Seite) und nicht als Tabelle; sortiert nach dem Titel
$az = !empty($d['az_index']) && empty($d['paginate']) && $layout !== 'table';
if ($az && ($tf = (string) ($t['settings']['title_field'] ?? '')) !== '') { $o['sort'] = $tf; $o['dir'] = 'asc'; }
$rows = Entries::query($t, $o);   // Stellenangebote: abgelaufene fehlen schon hier (Core\Data\Jobs, Entries::where)
// „Abschnitt ausblenden, wenn nichts da ist“: für Besucher keine Ausgabe – die Hülle (partials/section) entfällt dann
if (!$rows && !empty($d['hide_empty']) && !is_editing() && !$filtering) return;
$jobs = \Core\Data\Jobs::is($t);
if (!empty($d['link_detail'])) \Core\StructuredData::itemList($t, $rows, strip_emphasis((string) ($d['title'] ?? '')));   // schema.org ItemList
$pages = !empty($d['paginate']) && $limit ? (int) ceil(Entries::count($t, $o) / $limit) : 1;
$link = fn(array $e) => !empty($d['link_detail']) ? Entries::href($t, $e) : null;
$hTag = !empty($d['title']) ? 'h3' : 'h2';
$azTag = $hTag;   // Buchstaben-Überschriften der A–Z-Gruppen; Namen der Einträge eine Stufe darunter
if ($az) $hTag = !empty($d['title']) ? 'h4' : 'h3';
// Bilder einpassen (Logos auf einer Kachel) statt füllen: Standard im Verzeichnis
$fit = (string) ($d['image_fit'] ?? '');
$contain = $fit === 'contain' || ($fit === '' && $layout === 'directory');
// Etiketten (Verzeichnis): Farbton nach Position der Option (dl-tone-1 … 5)
$tone = fn(array $f, string $k) => 'dl-tone-' . ((int) array_search($k, array_keys((array) ($f['options'] ?? [])), true) % 5 + 1);
$chips = function (array $e, string $f) use ($t, $tone): string {
    $fd = Tables::field($t, $f);
    $vals = array_values(array_filter((array) ($e[$f] ?? []), fn($v) => $v !== '' && $v !== null));
    if (!$fd || !$vals) return '';
    $h = '';
    foreach ($vals as $k) $h .= '<span class="dl-chip ' . $tone($fd, (string) $k) . '">' . e(Tables::optionLabel($fd, (string) $k)) . '</span> ';
    return '<span class="dl-chips">' . trim($h) . '</span>';
};
// Adresse mit den aktiven Besucher-Filtern (Schaltflächen, Blättern); $set überschreibt, null entfernt
$keep = array_filter(array_map(fn($v) => $v['sel'], $vfilters), fn($v) => $v !== null) + ($vq !== '' ? ['q' => $vq] : []);
$here = app()->currentPage ? \Core\Pages::url(app()->currentPage) : url('/');
$qs = function (array $set) use ($keep, $here, $b): string {
    $p = array_filter($set + $keep, fn($v) => $v !== null && $v !== '');
    return $here . ($p ? '?' . http_build_query($p) : '') . '#' . $b->domId();
};
// Gruppen für A–Z (Umlaute zum Grundbuchstaben, Ziffern/Sonstiges unter „#“)
$letterOf = function (string $s): string {
    $c = strtr(mb_strtoupper(mb_substr(trim($s), 0, 1)), ['Ä' => 'A', 'Ö' => 'O', 'Ü' => 'U', 'À' => 'A', 'Á' => 'A', 'É' => 'E', 'È' => 'E']);
    return preg_match('~^[A-Z]$~', $c) ? $c : '#';
};
$groups = ['' => $rows];
if ($az && $rows) {
    $groups = [];
    foreach ($rows as $e) $groups[$letterOf(Entries::title($t, $e))][] = $e;
    uksort($groups, fn($a, $b2) => $a === '#' ? 1 : ($b2 === '#' ? -1 : strcmp($a, $b2)));
}
$titleClamp = Clamp::titleClass($d);   // Textlänge/Titel kürzen (Karten und Listen; Texte über Clamp::text)
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
    <?php if (!empty($d['eyebrow'])): ?><p class="eyebrow eyebrow--accent"<?= $b->edit('eyebrow') ?>><?= emphasis((string) $d['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($d['title'])): ?><h2 id="<?= e($b->titleId()) ?>" class="h2 h2--m dl-title"><span<?= $b->edit('title') ?>><?= emphasis((string) $d['title']) ?></span></h2><?php endif; ?>
    <?php if (!empty($d['intro'])): ?><p class="muted dl-intro"<?= $b->edit('intro') ?>><?= emphasis((string) $d['intro']) ?></p><?php endif; ?>
  </header>
  <?php endif; ?>

  <?php if ($vfilters || !empty($d['visitor_search'])): $qid = $b->domId() . '-q'; ?>
  <div class="dl-tools">
    <?php if (!empty($d['visitor_search'])): ?>
    <form class="dl-search" role="search" method="get" action="<?= e($here . '#' . $b->domId()) ?>">
      <label class="dl-search__label" for="<?= e($qid) ?>"><?= e(lt('In der Liste suchen')) ?></label>
      <span class="dl-search__row"><input class="dl-search__input" id="<?= e($qid) ?>" type="search" name="q" value="<?= e($vq) ?>" maxlength="80" autocomplete="off" enterkeyhint="search"><?php foreach ($vfilters as $vn => $vf): if ($vf['sel'] === null) continue; ?><input type="hidden" name="<?= e($vn) ?>" value="<?= e($vf['sel']) ?>"><?php endforeach; ?><button class="dl-search__btn" type="submit"><?= e(lt('Suchen')) ?></button></span>
    </form>
    <?php endif; ?>
    <?php foreach ($vfilters as $vn => $vf): $vl = Tables::label($vf['field']); ?>
    <nav class="dl-filter" aria-label="<?= e(lt('Filtern nach {label}', ['label' => $vl])) ?>">
      <span class="dl-filter__label" aria-hidden="true"><?= e($vl) ?></span>
      <ul class="dl-filter__list" role="list">
        <li><a class="dl-fchip" href="<?= e($qs([$vn => null])) ?>" rel="nofollow"<?= $vf['sel'] === null ? ' aria-current="true"' : '' ?>><?= e(lt('Alle')) ?></a></li>
        <?php foreach ($vf['options'] as $k => $l): ?><li><a class="dl-fchip <?= e($tone($vf['field'], (string) $k)) ?>" href="<?= e($qs([$vn => (string) $k])) ?>" rel="nofollow"<?= $vf['sel'] === (string) $k ? ' aria-current="true"' : '' ?>><?= e(Tables::optionLabel($vf['field'], (string) $k)) ?></a></li><?php endforeach; ?>
      </ul>
    </nav>
    <?php endforeach; ?>
    <?php if ($filtering): $total = $limit ? Entries::count($t, $o) : count($rows); ?>
    <p class="dl-count" role="status"><?= e($total === 1 ? lt('1 Treffer') : lt('{n} Treffer', ['n' => $total])) ?> · <a href="<?= e($here . '#' . $b->domId()) ?>"><?= e(lt('Filter zurücksetzen')) ?></a></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if (!$rows && $filtering): ?>
  <p class="dl-empty"><?= e(lt('Keine Einträge für diese Auswahl.')) ?> <a href="<?= e($here . '#' . $b->domId()) ?>"><?= e(lt('Alle anzeigen')) ?></a></p>
  <?php elseif (!$rows): ?>
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
  <?php if ($az && count($rows) > 1): $azLetters = array_merge(range('A', 'Z'), isset($groups['#']) ? ['#'] : []); ?>
  <nav class="dl-az" aria-label="<?= e(lt('Nach Anfangsbuchstaben springen')) ?>"><ul class="dl-az__list" role="list">
    <?php foreach ($azLetters as $L): ?><li><?php if (isset($groups[$L])): ?><a href="#<?= e($b->domId() . '-az-' . ($L === '#' ? 'num' : strtolower($L))) ?>"><?= e($L) ?></a><?php else: ?><span aria-hidden="true"><?= e($L) ?></span><?php endif; ?></li><?php endforeach; ?>
  </ul></nav>
  <?php endif; ?>
  <?php foreach ($groups as $L => $grows): $chipField = fn(string $f) => $layout === 'directory' && in_array(Tables::field($t, $f)['type'] ?? '', ['select', 'multiselect'], true); ?>
  <?php if ($L !== ''): ?><<?= $azTag ?> class="dl-az__head" id="<?= e($b->domId() . '-az-' . ($L === '#' ? 'num' : strtolower($L))) ?>"><?= e($L) ?></<?= $azTag ?>><?php endif; ?>
  <ul class="dl-items<?= in_array($layout, ['cards', 'directory'], true) ? ' dl-cols-' . e((string) ($d['columns'] ?: 3)) : '' ?>" role="list">
    <?php foreach ($grows as $e): $url = $link($e); $title = Entries::title($t, $e); ?>
    <li class="dl-item<?= ($e['_pick'] ?? null) === 'featured' ? ' dl-item--featured' : '' ?>" data-reveal="up"><?= \Core\Data\EntryEdit::button($t, $e, $title) ?>
      <?php if ($imageField && $layout !== 'compact'): $pic = Entries::html($t, $e, $imageField, ['ratio' => $d['ratio'] ?? '16:10', 'sizes' => in_array($layout, ['cards', 'directory'], true) ? '(min-width: 1080px) 400px, 100vw' : '(min-width: 1080px) 320px, 40vw']); ?>
      <div class="dl-img ratio-<?= e(str_replace(':', '-', (string) ($d['ratio'] ?? '16:10'))) ?><?= $contain ? ' dl-img--contain' : '' ?>"><?= $pic ?: '<span class="dl-ph" aria-hidden="true"></span>' ?></div>
      <?php endif; ?>
      <div class="dl-body">
        <?php $meta = $jobs && ($js = \Core\Data\Jobs::summary($t, $e)) !== '' ? [e($js)] : []; foreach ($other as $f) { if ($chipField($f)) continue; if (in_array(Tables::field($t, $f)['type'] ?? 'date', ['date', 'datetime', 'select', 'time'], true) || $f === 'published_at') { $h = Entries::html($t, $e, $f); if ($h !== '') $meta[] = $h; } } ?>
        <?php if ($meta): ?><p class="dl-meta"><?= implode('<span aria-hidden="true"> · </span>', $meta) ?></p><?php endif; ?>
        <?php if ($showTitle): ?><<?= $hTag ?> class="dl-name<?= $titleClamp ?>"><?= $url ? '<a href="' . e($url) . '" class="dl-link">' . e($title) . '</a>' : e($title) ?></<?= $hTag ?>><?php endif; ?>
        <?php foreach ($other as $f): $type = Tables::field($t, $f)['type'] ?? 'date'; if (in_array($type, ['date', 'datetime', 'select', 'time'], true) || $chipField($f)) continue; [$clamp, $html] = Clamp::text($t, $e, $f, $d); if ($html === '') continue; ?>
        <div class="dl-f dl-f--<?= e($type) ?><?= $clamp ?>"><?= $html ?></div>
        <?php endforeach; ?>
        <?php foreach ($other as $f): if (!$chipField($f) || ($ch = $chips($e, $f)) === '') continue; ?>
        <div class="dl-f dl-f--chips"><?= $ch ?></div>
        <?php endforeach; ?>
        <?php if ($url && !$showTitle): ?><a href="<?= e($url) ?>" class="dl-link dl-more-link"><?= e(lt('Mehr')) ?><span class="sr-only"> <?= e(lt('zu {title}', ['title' => $title])) ?></span></a><?php endif; ?>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($pages > 1): $base = app()->currentPage ? \Core\Pages::url(app()->currentPage) : url('/'); ?>
  <nav class="dl-pager" aria-label="<?= e(lt('Seiten')) ?>">
    <?php if ($page > 1): ?><a href="<?= e($keep ? $qs(['seite' => $page > 2 ? $page - 1 : null]) : $base . ($page > 2 ? '?seite=' . ($page - 1) : '')) ?>" rel="prev">← <?= e(lt('Neuere')) ?></a><?php endif; ?>
    <span><?= e(lt('Seite {page} von {pages}', ['page' => $page, 'pages' => $pages])) ?></span>
    <?php if ($page < $pages): ?><a href="<?= e($keep ? $qs(['seite' => $page + 1]) : $base . '?seite=' . ($page + 1)) ?>" rel="next"><?= e(lt('Ältere')) ?> →</a><?php endif; ?>
  </nav>
  <?php endif; ?>

  <?php if (!empty($d['more_label']) && !empty($d['more_link'])): ?>
  <p class="dl-more"><a class="<?= e($btn) ?>" href="<?= e(link_href((string) $d['more_link'])) ?>"<?= $b->edit('more_label') ?>><?= e($d['more_label']) ?></a></p>
  <?php endif; ?>
<?= \Core\Data\EntryEdit::newButton($t) ?></div>
