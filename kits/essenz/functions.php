<?php
/*
 * Theme-Helfer „essenz“. Werden von Templates und Block-Renderern genutzt.
 * Präfix essenz_ – theme:create benennt ihn für abgeleitete Themes automatisch um.
 * Alle sichtbaren festen Texte laufen über lt() (Übersetzungen: lang/site/{sprache}.php).
 */

use Core\Pages;

/**
 * Klassen für <html>: Design-Werte (hdr-bar, btn-key, depth-relief, mk-dot, has-motifs, has-dark …) + no-js.
 * is-darkbase: Die Vorlage ist schon im hellen Schema dunkel („Nachtpaneel“) → color-scheme: dark für Formularelemente.
 * CSP: keine Inline-Styles – alle Gestaltung über Variablen der Design-Datei und diese Klassen.
 */
function essenz_html_class(): string
{
    $bg = (string) design('background');
    $dark = $bg !== '' && essenz_luminance($bg) < 0.18;
    return trim('no-js ' . design_classes() . ($dark ? ' is-darkbase' : ''));
}

function essenz_luminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return 1.0;
    $c = array_map(fn($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
    $c = array_map(fn($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/** <link rel="preload"> für die Hauptschnitte der gewählten Schriften (Kit-Schriften vom Schriften-Manager, Core\Design::preloads) */
function essenz_font_preloads(): string
{
    return \Core\Design::preloads(['font_body', 'font_head']);
}

/** Name der Website (Kurzname für die Wortmarke) */
function essenz_name(bool $short = false): string
{
    $name = trim((string) setting('org_name'));
    $shortName = trim((string) setting('short_name'));
    if ($short && $shortName !== '') return $shortName;
    return $name !== '' ? $name : ($shortName !== '' ? $shortName : site_name());
}

// ------------------------------------------------------------------ Kontakt

/** Telefonnummer für die Anzeige – in weiteren Sprachen automatisch international (+49 …) */
function essenz_phone(): string
{
    return phone_display(trim((string) setting('phone')));
}

function essenz_phone_href(): ?string
{
    return tel_href(essenz_phone());
}

function essenz_email(): string
{
    $m = trim((string) setting('email'));
    return filled($m) ? $m : '';
}

/** Adresse als Zeilen: [Straße, PLZ Ort, Land] – nur befüllte Teile */
function essenz_address_lines(): array
{
    $street = trim((string) setting('street'));
    $city = trim(trim((string) setting('zip')) . ' ' . trim((string) setting('city')));
    $country = trim((string) setting('country'));
    return array_values(array_filter([$street, $city, $country], 'filled'));
}

/** Link mit Sonderwerten „phone“ und „email“ (Website) */
function essenz_link(?string $link): string
{
    return match (trim((string) $link)) {
        'phone' => essenz_phone_href() ?? link_href('#kontakt'),
        'email' => essenz_email() !== '' ? 'mailto:' . essenz_email() : link_href('#kontakt'),
        default => link_href($link),
    };
}

function essenz_link_attrs(?string $link): string
{
    $href = essenz_link($link);
    return 'href="' . e($href) . '"' . ext_attrs($href);
}

/** Hinweis für Screenreader bei Links in neuem Tab */
function essenz_ext_note(string $href): string
{
    return is_external($href) ? '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '';
}

/**
 * Button-Paar aus button_label/button_link und button2_label/button2_link. Nur Buttons mit Beschriftung und Link erscheinen.
 * $prefix: Pfad-Präfix für die Bearbeitung in Listen (z. B. 'items.0.').
 */
function essenz_buttons(\Core\Block $b, string $class = '', array $data = [], string $prefix = ''): string
{
    $d = $data ?: $b->data;
    $h = '';
    foreach (['button' => 'btn--primary', 'button2' => 'btn--secondary'] as $k => $style) {
        $label = trim((string) ($d[$k . '_label'] ?? ''));
        $link = trim((string) ($d[$k . '_link'] ?? ''));
        if ($label === '' || $link === '') continue;
        $href = essenz_link($link);
        $h .= '<a class="btn ' . $style . '" ' . essenz_link_attrs($link) . '><span' . $b->edit($prefix . $k . '_label') . '>' . e($label) . '</span>' . essenz_ext_note($href) . '</a>';
    }
    return $h !== '' ? '<div class="btn-row' . ($class !== '' ? ' ' . e($class) : '') . '">' . $h . '</div>' : '';
}

/** Mehrzeiliger Text → nicht leere Zeilen */
function essenz_lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('~\R~', (string) $text)), fn($l) => $l !== ''));
}

/** Aufzählung mit Signalmarke (kleines Quadrat wie eine Kontrollleuchte) */
function essenz_checks(array $lines, string $class = ''): string
{
    if (!$lines) return '';
    $h = '<ul class="checks' . ($class !== '' ? ' ' . e($class) : '') . '" role="list">';
    foreach ($lines as $l) $h .= '<li><span>' . essenz_title($l) . '</span></li>';   // *Wort* → Hervorhebung
    return $h . '</ul>';
}

/** Zweistellige Nummer (01, 02 …) für Index, Thesen, Schritte */
function essenz_num(int $n): string
{
    return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
}

// ------------------------------------------------------------------ Öffnungszeiten

function essenz_clock(?string $t): string
{
    return ltrim((string) $t, '0') ?: '0:00';
}

/** Zeitfenster eines Eintrags, z. B. „9:00–12:30, 13:30–17:00 Uhr“ (für API und Anzeige) */
function essenz_time_range(array $r): string
{
    $seg = [];
    if (!empty($r['von']) && !empty($r['bis'])) {
        if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
            $seg[] = essenz_clock($r['von']) . '–' . essenz_clock($r['pause_von']);
            $seg[] = essenz_clock($r['pause_bis']) . '–' . essenz_clock($r['bis']);
        } else {
            $seg[] = essenz_clock($r['von']) . '–' . essenz_clock($r['bis']);
        }
    }
    $time = $seg ? lt('{zeit} Uhr', ['zeit' => implode(', ', $seg)]) : '';
    return implode(' · ', array_filter([$time, trim((string) ($r['notiz'] ?? ''))]));
}

function essenz_weekday(int $dow): string
{
    return date_local(strtotime('2024-01-0' . ($dow === 0 ? 7 : $dow)), 'weekday');   // 01.01.2024 war ein Montag
}

/**
 * Öffnungszeiten, Mo–So sortiert; aufeinanderfolgende Tage mit gleichen Zeiten zusammengefasst.
 * 'dows' (Wochentage 0–6) und 'slots' (HH:MM-HH:MM) nutzt site.js für die Anzeige „Jetzt geöffnet“ – im Browser
 * berechnet, damit der Seiten-Cache keinen veralteten Zustand ausliefert.
 * @return list<array{days: string, time: string, dows: string, slots: string}>
 */
function essenz_hours(): array
{
    $byDay = $slots = [];
    foreach ((array) setting('hours', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if ($dow < 0 || $dow > 6) continue;
        $t = essenz_time_range($r);
        if ($t !== '') $byDay[$dow][] = $t;
        if (!empty($r['von']) && !empty($r['bis'])) {
            if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
                $slots[$dow][] = $r['von'] . '-' . $r['pause_von'];
                $slots[$dow][] = $r['pause_bis'] . '-' . $r['bis'];
            } else {
                $slots[$dow][] = $r['von'] . '-' . $r['bis'];
            }
        }
    }
    $groups = [];
    foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
        if (!isset($byDay[$dow])) { $groups[] = null; continue; }
        $time = implode(' · ', $byDay[$dow]);
        $last = $groups ? $groups[array_key_last($groups)] : null;
        if ($last && $last['time'] === $time) {
            $groups[array_key_last($groups)]['to'] = $dow;
        } else {
            $groups[] = ['from' => $dow, 'to' => $dow, 'time' => $time];
        }
    }
    $out = [];
    $order = [1, 2, 3, 4, 5, 6, 0];
    foreach (array_filter($groups) as $g) {
        $days = essenz_weekday($g['from']) . ($g['to'] !== $g['from'] ? '–' . essenz_weekday($g['to']) : '');
        $range = array_slice($order, array_search($g['from'], $order, true), array_search($g['to'], $order, true) - array_search($g['from'], $order, true) + 1);
        $out[] = ['days' => $days, 'time' => $g['time'], 'dows' => implode(',', $range), 'slots' => implode(',', $slots[$g['from']] ?? [])];
    }
    return $out;
}

// ------------------------------------------------------------------ Navigation, Recht, Social

/** Links Impressum / Datenschutz / Barrierefreiheit (in der Sprache der Seite, falls übersetzt) */
function essenz_legal_links(): array
{
    $out = [];
    foreach (['imprint_page' => lt('Impressum'), 'privacy_page' => lt('Datenschutz'), 'accessibility_page' => lt('Barrierefreiheit')] as $k => $label) {
        $id = (int) setting($k);
        $p = $id ? Pages::find($id) : null;
        if ($p && \Core\Lang::multi()) $p = Pages::translations($p)[\Core\Lang::current()] ?? $p;
        if ($p && ($p['status'] === 'published' || app()->auth->check())) {
            $out[] = ['key' => $k, 'label' => $label, 'href' => Pages::url($p)];
        }
    }
    return $out;
}

/** Social-Media-Profile mit gültiger Adresse */
function essenz_social(): array
{
    return array_values(array_filter((array) setting('social', []), fn($s) => trim((string) ($s['label'] ?? '')) !== '' && is_external($s['url'] ?? '')));
}

/** Hauptmenü: Seiten mit „Im Menü“ (Unterseiten) + Anker der Startseite mit „In Navigation“ */
function essenz_menu(): array
{
    static $menu = null;
    if ($menu !== null) return $menu;
    $menu = Pages::menu(app()->auth->check());
    foreach (app()->theme->navigation() as $n) {
        $menu[] = ['id' => 'a-' . $n['id'], 'label' => $n['label'], 'href' => $n['href'], 'active' => false, 'children' => []];
    }
    return $menu;
}

/** Handlungsaufruf des Kopfbereichs [label, link] oder null (Stil „Kein“) – für Menü-Blatt und Fußbereich; im Kopf: header_actions() */
function essenz_header_cta(): ?array
{
    // Design → „Kopfbereich: Suche & Aktionen“ (Core\HeaderActions): Vorgabe (Kontakt, Termin …) oder Website → Darstellung
    $a = header_cta();
    return $a ? ['label' => $a['label'], 'link' => $a['link']] : null;
}

/**
 * Wie breit muss der Kopfbereich sein, damit das Menü in einer Zeile passt? (in rem, auf 6 rem gerundet)
 * Grundlage der Container-Query im Kopf (Klasse fit-NN): bis dahin zeigt die Leiste die Menü-Schaltfläche.
 * Die Schätzung ist bewusst großzügig; site.js korrigiert, falls das Menü trotzdem nicht passt (is-overflow).
 * Alles in rem – unabhängig von der Grundschriftgröße, weil die Navigation eine feste rem-Größe nutzt.
 */
function essenz_nav_fit(array $menu, ?array $cta, array $langs, bool $search, string $variant): string
{
    if (!$menu) return 'fit-0';
    if ($variant === 'index') return 'fit-no';                                // Minimal: Menü immer im Seitenblatt
    $em = fn(string $s, float $size) => mb_strlen($s) * 0.52 * $size;          // mittlere Zeichenbreite (Inter, 500)
    $nav = 0.0;
    $split = essenz_nav_parent() !== 'overview';
    foreach ($menu as $m) $nav += $em((string) $m['label'], .9375) + 1.5 + ($m['children'] ? ($split ? 1.75 : 1.05) : 0) + .25;
    $logo = (int) setting('logo');
    $brand = $logo ? 11.5 : 2.5 + .7 + $em(essenz_name(true), 1.125);
    // Aktionen rechts (Suche, Handlungsaufruf, Kontakt-Chip …) schätzt Core\HeaderActions je nach Einstellung
    $tools = \Core\HeaderActions::widthRem() + ($langs ? count($langs) * 2.4 + .6 : 0);
    $gut = 2 * 2.4;                                                           // Seitenabstand bei ~60–80 rem
    $total = match ($variant) {
        'centered' => max($brand, $nav + $tools + 1.5) + $gut,
        'split' => max($brand + 4, 2 * max($nav, $tools + 2)) + $brand + $gut,
        default => $brand + $nav + $tools + 3 + $gut,
    };
    $step = (int) (ceil($total * 1.05 / 6) * 6);
    return $step > 102 ? 'fit-no' : 'fit-' . max(30, $step);
}

/** Seiten-ID für Menü-Vergleiche */
function essenz_is_current(array $m): bool
{
    return is_int($m['id']) && $m['id'] === (int) (app()->currentPage['id'] ?? 0);
}

/** Menüpunkte mit Unterseiten: „split“ (Link + Pfeil, Standard), „hover“ (dazu Öffnen beim Überfahren), „overview“ (Klick öffnet, mit „Übersicht“) */
function essenz_nav_parent(): string
{
    $v = (string) design('nav_parent');
    return in_array($v, ['split', 'hover', 'overview'], true) ? $v : 'split';
}

/**
 * Hauptmenü in der Leiste: Unterseiten als Aufklappmenü (<details> – ohne JavaScript bedienbar; site.js ergänzt
 * Pfeiltasten, Escape, Klick daneben, Öffnen beim Überfahren). Design „Menüpunkte mit Unterseiten“:
 *  - split/hover: der Menüpunkt bleibt ein Link, daneben öffnet ein Pfeil (<summary>) die Unterseiten – kein „Übersicht“-Eintrag
 *  - overview: der Menüpunkt öffnet das Menü, erster Eintrag „Übersicht: …“ führt zur Seite (bisheriges Verhalten)
 * Dritte Ebene: .hnav__nested (Design „Dritte Menüebene“: eingerückt bzw. gruppiert – nur CSS, Klassen nv-*).
 */
function essenz_nav_inline(array $menu): string
{
    $cur = fn(array $m) => essenz_is_current($m) ? ' aria-current="page"' : '';
    $chev = icon('caret-down', ['class' => 'hnav__chev']);
    $split = essenz_nav_parent() !== 'overview';
    $nested = function (array $items) use (&$nested, $cur): string {
        if (!$items) return '';
        $h = '<ul class="hnav__nested" role="list">';
        foreach ($items as $c) $h .= '<li><a class="hnav__sublink" href="' . e($c['href']) . '"' . $cur($c) . '>' . e($c['label']) . '</a>' . $nested($c['children']) . '</li>';
        return $h . '</ul>';
    };
    $children = function (array $m) use ($nested, $cur): string {
        $h = '';
        foreach ($m['children'] as $c) {
            $h .= '<li' . ($c['children'] ? ' class="has-nested"' : '') . '><a class="hnav__sublink" href="' . e($c['href']) . '"' . $cur($c) . '>' . e($c['label']) . '</a>' . $nested($c['children']) . '</li>';
        }
        return $h;
    };
    $h = '<ul class="hnav__list" role="list">';
    foreach ($menu as $m) {
        $active = $m['active'] ? ' data-active' : '';
        if (!$m['children']) {
            $h .= '<li class="hnav__item"><a class="hnav__link" href="' . e($m['href']) . '"' . $cur($m) . $active . '>' . e($m['label']) . '</a></li>';
            continue;
        }
        if ($split) {
            $h .= '<li class="hnav__item hnav__item--split"><a class="hnav__link hnav__link--top" href="' . e($m['href']) . '"' . $cur($m) . $active . '>' . e($m['label']) . '</a>'
                . '<details class="hnav__sub"' . $active . '><summary class="hnav__link hnav__toggle" aria-label="' . e(lt('Unterseiten von {name}', ['name' => $m['label']])) . '">' . $chev . '</summary>'
                . '<ul class="hnav__panel" role="list">' . $children($m) . '</ul></details></li>';
            continue;
        }
        $h .= '<li class="hnav__item"><details class="hnav__sub"' . $active . '><summary class="hnav__link"><span>' . e($m['label']) . '</span>' . $chev . '</summary>'
            . '<ul class="hnav__panel" role="list"><li><a class="hnav__sublink hnav__sublink--parent" href="' . e($m['href']) . '"' . $cur($m) . '>'
            . e(lt('Übersicht: {name}', ['name' => $m['label']])) . '</a></li>' . $children($m) . '</ul></details></li>';
    }
    return $h . '</ul>';
}

/**
 * Hauptmenü im Seitenblatt (Menü-Schaltfläche). „overview“: Unterseiten als Akkordeon (<details name> – nur eins offen,
 * ohne JavaScript), erster Eintrag „Übersicht: …“. „split“/„hover“: jede Seite bleibt ein Link, die Unterseiten öffnet
 * eine eigene Schaltfläche daneben (aria-expanded; ohne JavaScript ist alles sichtbar, site.js klappt zu). Aktiver Zweig offen.
 */
function essenz_nav_sheet(array $menu): string
{
    $cur = fn(array $m) => essenz_is_current($m) ? ' aria-current="page"' : '';
    $chev = icon('caret-down', ['class' => 'mnav__chev']);
    $split = essenz_nav_parent() !== 'overview';
    $n = 0;
    $level = function (array $items, int $depth) use (&$level, &$n, $cur, $chev, $split): string {
        $h = '<ul class="mnav__list mnav__list--' . $depth . '" role="list">';
        foreach ($items as $i => $m) {
            // Hauptebene nummeriert wie ein Index (01, 02 …) – die Nummer ist Gestaltung, nicht Inhalt
            $num = $depth === 1 ? '<span class="mnav__num" aria-hidden="true">' . essenz_num($i + 1) . '</span>' : '';
            if (!$m['children']) {
                $h .= '<li><a class="mnav__link" href="' . e($m['href']) . '"' . $cur($m) . '>' . $num . '<span class="mnav__txt">' . e($m['label']) . '</span></a></li>';
                continue;
            }
            if ($split) {
                $id = 'mnav-sub-' . (++$n);
                $h .= '<li><div class="mnav__row"><a class="mnav__link" href="' . e($m['href']) . '"' . $cur($m) . '>' . $num . '<span class="mnav__txt">' . e($m['label']) . '</span></a>'
                    . '<button type="button" class="mnav__toggle" aria-expanded="' . ($m['active'] ? 'true' : 'false') . '" aria-controls="' . $id . '">'
                    . $chev . '<span class="sr-only">' . e(lt('Unterseiten von {name}', ['name' => $m['label']])) . '</span></button></div>'
                    . '<div class="mnav__sub" id="' . $id . '">' . $level($m['children'], $depth + 1) . '</div></li>';
                continue;
            }
            $h .= '<li><details class="mnav__acc" name="mnav-' . $depth . '"' . ($m['active'] ? ' open' : '') . '>'
                . '<summary class="mnav__link">' . $num . '<span class="mnav__txt">' . e($m['label']) . '</span>' . $chev . '</summary>'
                . '<div class="mnav__sub"><a class="mnav__link mnav__link--parent" href="' . e($m['href']) . '"' . $cur($m) . '>' . e(lt('Übersicht: {name}', ['name' => $m['label']])) . '</a>'
                . $level($m['children'], $depth + 1) . '</div></details></li>';
        }
        return $h . '</ul>';
    };
    return $level($menu, 1);
}

/** Direktkontakt (Telefon, E-Mail) – im Seitenblatt und im Fußbereich */
function essenz_quick_contact(string $class = 'quick'): string
{
    $phone = essenz_phone();
    $tel = essenz_phone_href();
    $email = essenz_email();
    if (!(filled($phone) && $tel) && $email === '') return '';
    $h = '<ul class="' . e($class) . '" role="list">';
    if (filled($phone) && $tel) $h .= '<li><a href="' . e($tel) . '">' . icon('phone') . '<span>' . e($phone) . '</span></a></li>';
    if ($email !== '') $h .= '<li><a href="mailto:' . e($email) . '">' . icon('envelope-simple') . '<span>' . e($email) . '</span></a></li>';
    return $h . '</ul>';
}

// ------------------------------------------------------------------ Ausgabe-Bausteine

/**
 * Kopf eines Abschnitts: Dachzeile, Überschrift (H2 mit ID für aria-labelledby), Einleitung.
 * Leere optionale Teile erscheinen nicht; im Editor ist die Überschrift immer direkt bearbeitbar.
 */
function essenz_head(\Core\Block $b, string $class = '', string $tag = 'h2'): string
{
    $d = $b->data;
    $eyebrow = trim((string) ($d['eyebrow'] ?? ''));
    $title = trim((string) ($d['title'] ?? ''));
    $intro = trim((string) ($d['intro'] ?? ''));
    if ($eyebrow === '' && $title === '' && $intro === '' && !is_editing()) return '';
    $h = '<header class="sec-head' . ($class !== '' ? ' ' . e($class) : '') . '">';
    // Laufende Abschnittsnummer (Design → Abschnittsmarken „Laufende Nummer“): serverseitig gezählt, weil Container
    // (container-type) CSS-Zähler kapseln; bei anderen Marken blendet das CSS die Nummer aus
    static $mk = 0;
    if ($eyebrow !== '') $h .= '<p class="eyebrow"><span class="eyebrow__n" aria-hidden="true">' . essenz_num(++$mk) . '</span><span' . $b->edit('eyebrow') . '>' . essenz_title($eyebrow) . '</span></p>';
    if ($title !== '' || is_editing()) $h .= '<' . $tag . ' id="' . e($b->titleId()) . '" class="h2"' . $b->edit('title') . '>' . essenz_title($title) . '</' . $tag . '>';
    if ($intro !== '') $h .= '<p class="lead"' . $b->edit('intro') . '>' . nl2br(essenz_title($intro), false) . '</p>';
    return $h . '</header>';
}

/** Überschriften-Ebene für Einträge: h3 unter einer Abschnittsüberschrift, sonst h2 */
function essenz_htag(array $d): string
{
    return trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
}

/**
 * Bild im festen Format (Rahmen mit aspect-ratio). Ohne Bild: für Besucher nichts, im Editor ein Platzhalter.
 * @param string $ratio z. B. '4:3' oder '' (Originalformat)
 */
function essenz_image(?int $id, string $sizes, string $ratio, string $class = '', array $opt = []): string
{
    $pic = img($id, $sizes, ($ratio !== '' ? ['ratio' => $ratio] : []) + $opt);
    $cls = 'frame' . ($ratio !== '' ? ' r-' . str_replace(':', '-', $ratio) : '') . ($class !== '' ? ' ' . $class : '');
    if ($pic !== '') return '<div class="' . e($cls) . '">' . $pic . '</div>';
    return is_editing() ? '<div class="' . e($cls) . ' frame--empty"><span>' . e('Bild' . ($ratio !== '' ? ' · ' . $ratio : '')) . '</span></div>' : '';
}

/** Mindestbreite je Eintrag (Raster ohne feste Spaltenzahl): Klasse min-s | min-m | min-l */
function essenz_min(array $d, string $key = 'size'): string
{
    return 'min-' . (in_array($d[$key] ?? '', ['s', 'm', 'l'], true) ? $d[$key] : 'm');
}

/**
 * Inhaltsverzeichnis für lange Texte: vergibt IDs an <h2>/<h3> (und <h4>) – die Zwischenüberschriften des Editors und liefert [html, einträge].
 * @return array{0: string, 1: list<array{id: string, label: string, level: int}>}
 */
function essenz_toc(string $html, string $prefix): array
{
    $toc = [];
    $used = [];
    $html = preg_replace_callback('~<(h[234])([^>]*)>(.*?)</\1>~is', function ($m) use (&$toc, &$used, $prefix) {
        $label = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($label === '') return $m[0];
        if (preg_match('~\sid="([^"]+)"~', $m[2], $idm)) {
            $id = $idm[1];
            $attrs = $m[2];
        } else {
            $base = $prefix . '-' . (Pages::slugify($label) ?: 'abschnitt');
            $id = $base;
            for ($i = 2; isset($used[$id]); $i++) $id = $base . '-' . $i;
            $attrs = $m[2] . ' id="' . e($id) . '"';
        }
        $used[$id] = true;
        $toc[] = ['id' => $id, 'label' => $label, 'level' => strtolower($m[1]) === 'h4' ? 3 : 2];
        return '<' . $m[1] . $attrs . '>' . $m[3] . '</' . $m[1] . '>';
    }, $html) ?? $html;
    return [$html, $toc];
}

/** Geschätzte Lesezeit in Minuten */
function essenz_reading_time(string $html): int
{
    return max(1, (int) round(str_word_count(strip_tags($html), 0, 'äöüÄÖÜß') / 220));
}

// ------------------------------------------------------------------ SEO, App, API

/** Schema.org Organization / LocalBusiness – nur befüllte Felder, nur auf der Startseite */
function essenz_jsonld(array $page): ?array
{
    if (empty($page['is_home'])) return null;
    $type = (string) setting('schema_type', 'Organization');
    $d = ['@context' => 'https://schema.org', '@type' => in_array($type, ['Organization', 'LocalBusiness', 'ProfessionalService', 'NGO', 'Restaurant', 'Museum'], true) ? $type : 'Organization'];
    if (filled(setting('org_name'))) $d['name'] = (string) setting('org_name');
    if (filled(setting('tagline'))) $d['slogan'] = (string) setting('tagline');
    $d['url'] = absolute_url('/');
    if ($tel = essenz_phone_href()) $d['telephone'] = substr($tel, 4);
    if (essenz_email() !== '') $d['email'] = essenz_email();
    $logo = media((int) setting('logo') ?: null);
    if ($logo) $d['logo'] = site_url() . \Core\Media::url($logo);
    $addr = array_filter([
        'streetAddress' => filled(setting('street')) ? (string) setting('street') : null,
        'postalCode' => filled(setting('zip')) ? (string) setting('zip') : null,
        'addressLocality' => filled(setting('city')) ? (string) setting('city') : null,
        'addressCountry' => filled(setting('country')) ? (string) setting('country') : null,
    ]);
    if ($addr) $d['address'] = ['@type' => 'PostalAddress'] + $addr;
    if ($p = \Core\Maps::siteLocation()) $d['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $p[0], 'longitude' => $p[1]];
    if (!in_array($d['@type'], ['Organization', 'NGO'], true)) {
        $map = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];
        $spec = [];
        foreach ((array) setting('hours', []) as $r) {
            if (empty($r['von']) || empty($r['bis']) || !isset($map[(int) ($r['tag'] ?? -1)])) continue;
            $ranges = !empty($r['pause_von']) && !empty($r['pause_bis']) ? [[$r['von'], $r['pause_von']], [$r['pause_bis'], $r['bis']]] : [[$r['von'], $r['bis']]];
            foreach ($ranges as [$o, $c]) {
                $spec[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/' . $map[(int) $r['tag']], 'opens' => $o, 'closes' => $c];
            }
        }
        if ($spec) $d['openingHoursSpecification'] = $spec;
    }
    if ($social = essenz_social()) $d['sameAs'] = array_column($social, 'url');
    return isset($d['name']) ? $d : null;
}

/** Name, Kurzname und Kurzbefehle für die installierbare Web-App (Manifest) */
function essenz_app_info(): array
{
    $short = essenz_name(true);
    return [
        'name' => essenz_name(),
        'short_name' => mb_strlen($short) <= 14 ? $short : mb_substr($short, 0, 12) . '…',
        'description' => (string) (setting('tagline') ?: setting('default_meta_description')),
        'shortcuts' => [['name' => lt('Kontakt'), 'short_name' => lt('Kontakt'), 'url' => url('/') . '#kontakt']],
    ];
}

/** Öffentliche Basisdaten (GET /api/v1/public, ohne Token) */
function essenz_public_info(): array
{
    return [
        'name' => essenz_name(),
        'tagline' => filled(setting('tagline')) ? setting('tagline') : null,
        'address' => array_filter([
            'street' => filled(setting('street')) ? setting('street') : null,
            'zip' => filled(setting('zip')) ? setting('zip') : null,
            'city' => filled(setting('city')) ? setting('city') : null,
            'country' => filled(setting('country')) ? setting('country') : null,
        ]),
        'phone' => ($t = essenz_phone_href()) ? substr($t, 4) : null,
        'phone_display' => filled(essenz_phone()) ? essenz_phone() : null,
        'email' => essenz_email() ?: null,
        'social' => essenz_social(),
    ];
}

/**
 * Überschrift mit Hervorhebung: *Wort* → <em class="hl">Wort</em> (Akzentfarbe bzw. kursiv bei Serifen).
 * Im Editor ist die Hervorhebung sichtbar, die Sternchen bleiben als dezente Textzeichen stehen (span.hl-mark, Gestaltung im
 * Kern: resources/css/editor.css) – innerText liefert beim Speichern weiter „*Wort*“.
 */
function essenz_title(string $text): string
{
    // *Wort* → <em class="hl"> (Kern: emphasis_hl – ein Muster für alle Kits; Bearbeiten: Sternchen dezent sichtbar)
    return emphasis_hl($text);
}
