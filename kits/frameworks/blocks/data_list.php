<?php
/**
 * Datenliste – eigene Ausgabe des Kits für die Layouts „Karten“ und „Liste“ mit Klassen des Frameworks
 * (views/tailwind/data_list.php bzw. views/uikit/data_list.php). „Kompakt“ und „Tabelle“ übernimmt der Kern-Block
 * unverändert (neutrale Kern-CSS data.css, Variablen --dl-* in css/site.css) – so muss ein Kit nur nachbauen, was es
 * anders aussehen lassen will.
 *
 * Abfrage wie im Kern (app/Blocks/data_list.php): Felder, Filter (auch Kontext-Filter auf Detailseiten), Sortierung,
 * Seiten, geteilte Tabellen, „Abschnitt ausblenden, wenn leer“, ItemList (JSON-LD), „Bearbeiten“-Stift je Eintrag
 * (EntryEdit::button) und „+ Neuer Eintrag“ (EntryEdit::newButton) für die Redaktion.
 * @var \Core\Block $b  @var array $d
 */
use Core\Data\Clamp;
use Core\Data\EntryEdit;
use Core\Data\Entries;
use Core\Data\Tables;

$layout = in_array($d['layout'] ?? '', ['cards', 'list', 'compact', 'table'], true) ? $d['layout'] : 'cards';
if (!in_array($layout, ['cards', 'list'], true)) {
    include ROOT . '/app/Blocks/data_list.php';
    return;
}
$t = Tables::findContent((string) ($d['table'] ?? ''));
if (!$t) {
    if (is_editing()) echo '<div class="wrap uk-container"><p class="empty-hint">' . e(__('Bitte in der Seitenleiste eine Tabelle wählen.')) . '</p></div>';
    return;
}
$fields = [];
foreach ((array) ($d['fields'] ?? []) as $v) {
    if (str_starts_with((string) $v, $t['handle'] . '.')) $fields[] = substr((string) $v, strlen($t['handle']) + 1);
}
if (!$fields) $fields = array_values(array_filter(['_title', Tables::imageField($t), $t['settings']['description_field']]));
$imageField = null;
foreach ($fields as $f) {
    if ((Tables::field($t, $f)['type'] ?? '') === 'media') { $imageField = $f; break; }
}
$other = array_values(array_filter($fields, fn($f) => $f !== $imageField && $f !== '_title'));
$showTitle = in_array('_title', $fields, true);

$limit = max(0, (int) ($d['limit'] ?? 0));
$pageNo = !empty($d['paginate']) && $limit ? max(1, (int) (app()->request?->query['seite'] ?? 1)) : 1;
$o = ['status' => 'published'];
if ($limit) { $o['limit'] = $limit; $o['offset'] = ($pageNo - 1) * $limit; }
$pfx = $t['handle'] . '.';
$ctx = app()->entry;
$op = (string) ($d['filter_op'] ?? '=');
if (str_starts_with((string) ($d['filter_field'] ?? ''), $pfx)) {
    $ff = substr($d['filter_field'], strlen($pfx));
    if ($op === 'current' || $op === 'same') {
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
if (!empty($d['source'])) $o['source'] = (string) $d['source'];
if (!empty($d['featured_first'])) $o['featured_first'] = true;
$rows = Entries::query($t, $o);
if (!$rows && !empty($d['hide_empty']) && !is_editing()) return;
if (!empty($d['link_detail'])) \Core\StructuredData::itemList($t, $rows, strip_emphasis((string) ($d['title'] ?? '')));
$pages = !empty($d['paginate']) && $limit ? (int) ceil(Entries::count($t, $o) / $limit) : 1;

// Für die Vorlagen aufbereitet: je Eintrag Titel, Link, Bild, Meta-Zeile (Datum/Auswahl), Textfelder, Stift
$hTag = !empty($d['title']) ? 'h3' : 'h2';
$ratio = (string) ($d['ratio'] ?? '16:10');
$cols = (int) ($d['columns'] ?: 3);
$titleClamp = Clamp::titleClass($d);
$items = [];
foreach ($rows as $e) {
    $url = !empty($d['link_detail']) ? Entries::href($t, $e) : null;
    $title = Entries::title($t, $e);
    $meta = [];
    $texts = [];
    foreach ($other as $f) {
        $type = Tables::field($t, $f)['type'] ?? 'date';
        if (in_array($type, ['date', 'datetime', 'select', 'time'], true) || $f === 'published_at') {
            if (($h = Entries::html($t, $e, $f)) !== '') $meta[] = $h;
            continue;
        }
        [$clamp, $html] = Clamp::text($t, $e, $f, $d);
        if ($html !== '') $texts[] = ['type' => $type, 'clamp' => $clamp, 'html' => $html];
    }
    $items[] = [
        'title' => $title, 'url' => $url, 'meta' => $meta, 'texts' => $texts,
        'featured' => ($e['_pick'] ?? null) === 'featured',
        'image' => $imageField ? Entries::html($t, $e, $imageField, ['ratio' => $ratio, 'sizes' => $layout === 'cards' ? '(min-width: 1080px) 400px, 100vw' : '(min-width: 1080px) 320px, 40vw']) : null,
        'pencil' => EntryEdit::button($t, $e, $title),
    ];
}
$pager = null;
if ($pages > 1) {
    $base = app()->currentPage ? \Core\Pages::url(app()->currentPage) : url('/');
    $pager = ['page' => $pageNo, 'pages' => $pages,
        'prev' => $pageNo > 1 ? $base . ($pageNo > 2 ? '?seite=' . ($pageNo - 1) : '') : null,
        'next' => $pageNo < $pages ? $base . '?seite=' . ($pageNo + 1) : null];
}
$newButton = EntryEdit::newButton($t);
include frameworks_view('data_list');
