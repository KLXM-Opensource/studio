<?php
/**
 * Kopfbereich-Aktionen (Core\HeaderActions::render) – Kits ersetzen das Markup mit templates/partials/header-actions.php.
 * Stellen ($slot):
 *   bar     Leiste: [Menüpunkt], Kontakt-Chip, Suche (Lupe, Feld, Aufziehen, Befehlsfeld), Social, Anmelden, zweite Aktion,
 *           Handlungsaufruf bzw. Kontakt-Menü (Symbol + Aufklappliste)
 *   center  nur die Suche, wenn „Suche mittig“ gewählt ist
 *   below   Suchleiste unter dem Kopf („Suchleiste über die ganze Breite“ bzw. „Suchfeld unter der Navigation“)
 * Klassen: .ha (Container, data-ha), .ha-cta--{solid|outline|link|icon|split|chip}, .ha-search--{popover|inline|expand|command},
 * .ha-chip (Kontakt), .ha-dot (Statuspunkt), .ha-social, .ha-acct, .ha--compact (mobil verkleinern). Stil: assets/css/header-actions.css
 * + Variablen des Kits (--ha-accent, --ha-on, --ha-ink, --ha-muted, --ha-line, --ha-bg, --ha-surface, --ha-r, --ha-h).
 * Barrierefreiheit: Suchfelder in <form role="search"> mit Beschriftung, Vorschläge als Combobox mit aria-live (search.js),
 * Symbole mit Textalternative, alle Ziele mind. 44 px hoch (mobil) bzw. 40 px (Leiste), kein Inline-Skript/-Stil.
 * @var array $ha  Core\HeaderActions::model()  @var string $slot  @var array $opt
 */
$c = $ha['cfg'];
$pageUrl = !empty(app()->currentPage['id']) ? \Core\Pages::url(app()->currentPage) : '';
$s = $ha['search'];
$compact = !empty($opt['compact']);
$ext = fn(array $a) => $a['ext'] ? ' target="_blank" rel="noopener"' : '';
$extNote = fn(array $a) => $a['ext'] ? '<span class="sf-sr"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '';
$status = $ha['status'];
$statusAttrs = fn() => $status ? ' data-ha-hours="' . e(json_encode($status['ranges'])) . '" data-ha-tz="' . e($status['tz']) . '" data-ha-open="' . e($status['open']) . '" data-ha-closed="' . e($status['closed']) . '"' : '';

/** Suchfeld (Leiste, mittig, darunter): Formular mit Beschriftung, Vorschläge über search.js (data-suggest) */
$field = function (string $variant, string $id) use ($s): string {
    $kbd = $variant !== 'expand' ? '<kbd class="ha-kbd" aria-hidden="true">/</kbd>' : '';
    return '<form class="ha-field ha-field--' . e($variant) . '" role="search" aria-label="' . e(lt('Website-Suche')) . '" action="' . e($s['action']) . '" method="get">'
        . '<label class="sf-sr" for="' . e($id) . '">' . e(lt('Website durchsuchen')) . '</label>'
        . '<input class="ha-field__in" id="' . e($id) . '" type="search" name="q" maxlength="200" autocomplete="off" spellcheck="false" enterkeyhint="search"'
        . ' placeholder="' . e(lt('Suchen …')) . '" aria-keyshortcuts="/" data-ha-field data-suggest="' . e($s['suggest']) . '" data-suggest-js="' . e($s['js']) . '"'
        . ' data-l10n="' . json_attr($s['l10n']) . '">'
        . $kbd
        . '<button class="ha-field__go" type="submit">' . icon('magnifying-glass', ['class' => 'ha-ico']) . '<span class="sf-sr">' . e(lt('Suchen')) . '</span></button>'
        . '</form>';
};

/** Suche als Lupe mit Popover (Kern-Suchformular) – auch mobiler Ersatz für Feld und Aufziehen */
$popover = fn(string $extra = '') => '<div class="ha-search ha-search--popover' . $extra . '" data-ha-search>' . \Core\Search\Search::form('header') . '</div>';

$searchHtml = function (string $where) use ($s, $field, $popover): string {
    if (!$s || $s['slot'] !== $where) return '';
    return match ($s['mode']) {
        'inline' => '<div class="ha-search ha-search--inline" data-ha-search>' . $field('inline', 'ha-q-' . $where) . '</div>'
            . ($where !== 'below' ? $popover(' ha-search--fallback') : ''),   // Lupe, wenn der Platz nicht reicht; darunter: Feld bleibt
        'expand' => '<div class="ha-search ha-search--expand" data-ha-search>' . $field('expand', 'ha-q-x') . '</div>' . $popover(' ha-search--fallback'),
        'command' => '<div class="ha-search ha-search--command" data-ha-search>'
            . '<button type="button" class="ha-cmd" popovertarget="' . e(\Core\Search\Search::panelId('header')) . '" aria-keyshortcuts="/ Control+K Meta+K">'
            . icon('magnifying-glass', ['class' => 'ha-ico']) . '<span class="ha-cmd__txt">' . e(lt('Suchen …')) . '</span>'
            . '<kbd class="ha-kbd" data-ha-kbd data-ctrl="' . e(lt('Strg')) . '" aria-hidden="true">⌘K</kbd><span class="sf-sr">' . e(lt('Suche öffnen')) . '</span></button>'
            . \Core\Search\Search::form('header', '', ['panelOnly' => true]) . '</div>',
        default => $popover(),
    };
};

// ------------------------------------------------------------------ Suche mittig / darunter
if ($slot === 'center') {
    $h = $searchHtml('center');
    if ($h !== '') echo '<div class="ha ha-center' . (!empty($opt['class']) ? ' ' . e($opt['class']) : '') . '" data-ha>' . $h . '</div>';
    return;
}
if ($slot === 'below') {
    $h = $searchHtml('below');
    if ($h === '') return;
    echo '<div class="ha-below' . ($s['band'] ? ' ha-below--band' : '') . (!empty($opt['class']) ? ' ' . e($opt['class']) : '') . '" data-ha>'
        . '<div class="ha-below__in' . (!empty($opt['wrap']) ? ' ' . e($opt['wrap']) : '') . '">' . $h . '</div></div>';
    return;
}

// ------------------------------------------------------------------ Leiste
$parts = [];

// Kontakt-Chip (erster Chip trägt den Öffnungsstatus)
if (($opt['contact'] ?? true) && $ha['contact']) {
    $chips = '';
    foreach ($ha['contact'] as $i => $x) {
        $withStatus = $i === 0 && $status;
        $chips .= '<a class="ha-chip ha-chip--' . e($x['kind']) . '" href="' . e($x['href']) . '"' . ($withStatus ? $statusAttrs() : '') . '>'
            . ($withStatus ? '<span class="ha-dot" aria-hidden="true"></span><span class="ha-chip__state" data-ha-state hidden></span>' : '')
            . icon($x['icon'], ['class' => 'ha-ico']) . '<span class="ha-chip__txt">' . e($x['text']) . '</span>'
            . '<span class="sf-sr">' . e($x['sr']) . '</span></a>';
    }
    $parts[] = '<div class="ha-contact">' . $chips . '</div>';
}

if ($opt['search'] ?? true) $parts[] = $searchHtml('bar');

if (!empty($opt['lang']) && is_array($opt['lang'])) $parts[] = \Core\HeaderActions::lang($opt['lang'], trim('ha-langs ' . ($opt['lang_class'] ?? '')));

if ($ha['social']) {
    $li = '';
    foreach ($ha['social'] as $x) {
        $li .= '<li><a href="' . e($x['href']) . '" target="_blank" rel="noopener">' . icon($x['icon'], ['class' => 'ha-ico'])
            . '<span class="sf-sr">' . e($x['label']) . ' ' . e(lt('(öffnet in neuem Tab)')) . '</span></a></li>';
    }
    $parts[] = '<ul class="ha-social" role="list" aria-label="' . e(lt('Social Media')) . '">' . $li . '</ul>';
}

if ($a = $ha['account']) {
    $parts[] = '<a class="ha-acct" href="' . e($a['href']) . '"' . $ext($a) . '>' . icon('user-circle', ['class' => 'ha-ico']) . '<span class="ha-acct__txt">' . e($a['label']) . '</span>' . $extNote($a) . '</a>';
}

// Zweite Aktion (nicht bei „Geteilt“ als kleine Schaltfläche)
if (($opt['cta'] ?? true) && ($a = $ha['cta2'])) {
    $parts[] = '<a class="ha-cta2 ha-cta2--' . e($a['kind']) . '" href="' . e($a['href']) . '"' . $ext($a) . '>' . icon($a['icon'], ['class' => 'ha-ico'])
        . '<span class="ha-cta2__txt">' . e($a['label']) . '</span>' . ($a['sr'] !== $a['label'] ? '<span class="sf-sr"> – ' . e($a['sr']) . '</span>' : '') . $extNote($a) . '</a>';
}

// Kontakt-Menü: Symbol öffnet eine Liste aller Kontaktwege (<details> – ohne JavaScript bedienbar; header-actions-menu.js:
// Escape/Klick daneben schließen, Fokus zurück, aria-expanded). Status der Öffnungszeiten über header-actions-status.js.
if (($opt['cta'] ?? true) && $c['ha_cta_style'] === 'menu' && ($mn = $ha['menu'])) {
    $kit = trim((string) ($ha['classes']['menu'] ?? ''));
    $li = '';
    foreach ($mn['items'] as $it) {
        $li .= '<li><a href="' . e($it['href']) . '"' . $ext($it) . '>' . icon($it['icon'], ['class' => 'ha-ico'])
            . '<span class="ha-menu__txt"><span class="ha-menu__label">' . e($it['label']) . '</span>'
            . ($it['sub'] !== '' ? '<span class="ha-menu__sub">' . e($it['sub']) . '</span>' : '') . '</span>' . $extNote($it) . '</a></li>';
    }
    $hours = '';
    if ($mn['hours']) {
        $hours = '<div class="ha-menu__hours"' . ($status ? $statusAttrs() : '') . '><p class="ha-menu__head">' . icon('clock', ['class' => 'ha-ico'])
            . '<span>' . e(lt('Öffnungszeiten')) . '</span>' . ($status ? '<span class="ha-dot" aria-hidden="true"></span><span class="ha-menu__state" data-ha-state hidden></span>' : '') . '</p><dl>';
        foreach ($mn['hours'] as $h) $hours .= '<div><dt>' . e($h['days']) . '</dt><dd>' . e($h['time']) . '</dd></div>';
        $hours .= '</dl></div>';
    }
    $parts[] = '<details class="ha-menu' . ($kit !== '' ? ' ' . e($kit) : '') . '" data-ha-menu>'
        . '<summary class="ha-menu__btn">' . icon($mn['icon'], ['class' => 'ha-ico']) . '<span class="sf-sr">' . e(lt('Kontaktmöglichkeiten')) . '</span></summary>'
        . '<div class="ha-menu__panel"><p class="ha-menu__title">' . e($mn['label']) . '</p><ul class="ha-menu__list" role="list">' . $li . '</ul>' . $hours . '</div></details>';
}

// Handlungsaufruf
if (($opt['cta'] ?? true) && ($a = $ha['cta']) && $c['ha_cta_style'] !== 'menu') {
    $style = (string) $c['ha_cta_style'];
    $kit = trim((string) ($ha['classes'][$style] ?? ($style === 'split' ? ($ha['classes']['solid'] ?? '') : '')));
    $cls = 'ha-cta ha-cta--' . e($style) . ($kit !== '' ? ' ha-cta--kit ' . e($kit) : '');
    $label = '<span class="ha-cta__txt">' . e($a['label']) . '</span>';
    $attrs = ' href="' . e($a['href']) . '"' . $ext($a);
    $html = match ($style) {
        'link' => '<a class="' . $cls . '"' . $attrs . '>' . $label . '<span class="ha-arrow" aria-hidden="true">→</span>' . $extNote($a) . '</a>',
        'icon' => '<a class="' . $cls . '"' . $attrs . '>' . icon($a['icon'], ['class' => 'ha-ico']) . $label . $extNote($a) . '</a>',
        'chip' => '<a class="' . $cls . '"' . $attrs . ($status ? $statusAttrs() : '') . '><span class="ha-dot' . ($status ? '' : ' ha-dot--static') . '" aria-hidden="true"></span>'
            . $label . ($status ? '<span class="sf-sr" data-ha-state></span>' : '') . $extNote($a) . '</a>',
        'split' => ($m = $ha['mini'])
            ? '<div class="ha-split" role="group" aria-label="' . e(lt('Kontakt aufnehmen')) . '"><a class="' . $cls . '"' . $attrs . '>' . $label . $extNote($a) . '</a>'
              . '<a class="ha-split__mini" href="' . e($m['href']) . '">' . icon($m['icon'], ['class' => 'ha-ico']) . '<span class="sf-sr">' . e($m['sr']) . '</span></a></div>'
            : '<a class="' . str_replace('ha-cta--split', 'ha-cta--solid', $cls) . '"' . $attrs . '>' . $label . $extNote($a) . '</a>',
        // Abgesetzter Menüpunkt: steht direkt nach der Navigation (vor Suche usw.), mit Trennlinie und Akzent (Kit)
        'navitem' => '<a class="' . $cls . '"' . $attrs . (($pageUrl ?? '') === $a['href'] ? ' aria-current="page"' : '') . '>' . $label . '<span class="ha-arrow" aria-hidden="true">→</span>' . $extNote($a) . '</a>',
        default => '<a class="' . $cls . '"' . $attrs . '>' . $label . $extNote($a) . '</a>',
    };
    if ($style === 'navitem') array_unshift($parts, $html); else $parts[] = $html;
}

$parts = array_filter($parts, fn($p) => $p !== '');
if (!$parts) return;
$classes = 'ha ha--layout-' . e((string) $c['ha_layout']) . ($compact ? ' ha--compact' : '') . (!empty($opt['class']) ? ' ' . e($opt['class']) : '');
?>
<div class="<?= $classes ?>" data-ha><?= implode('', $parts) ?></div>
