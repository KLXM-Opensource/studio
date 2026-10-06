<?php
/*
 * Helfer des Kits „galerie“ (abgeleitet von „fluid“). Werden von Templates und Block-Renderern genutzt.
 * Präfix galerie_ – kit:create benennt ihn für abgeleitete Kits automatisch um.
 * Galerie-Funktionen (Tabellen, Status der Ausstellungen, Werkangaben, Preise, Anfragen, Besuch) stehen am Ende der Datei.
 * Alle sichtbaren festen Texte laufen über lt() (Übersetzungen: lang/site/{sprache}.php).
 */

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Pages;

/**
 * Klassen für <html>: Design-Werte (hdr-inline, btn-pill, cards-glass, has-dark …) + no-js.
 * is-darkbase: Die Vorlage ist schon im hellen Schema dunkel (z. B. „Tech-Startup“) → color-scheme: dark für Formularelemente.
 * CSP: keine Inline-Styles – alle Gestaltung über Variablen der Design-Datei und diese Klassen.
 */
function galerie_html_class(): string
{
    $bg = (string) design('background');
    $dark = $bg !== '' && galerie_luminance($bg) < 0.18;
    $serif = in_array((string) design('font_head'), ['fraunces', 'newsreader', 'instrument-serif', 'eb-garamond'], true);
    return trim('no-js ' . design_classes() . ($dark ? ' is-darkbase' : '') . ($serif ? ' head-serif' : ''));
}

function galerie_luminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return 1.0;
    $c = array_map(fn($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
    $c = array_map(fn($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/** Dateiname der variablen Schrift (latin) je Schlüssel – muss exakt der URL in css/font-*.css entsprechen (build.mjs) */
function galerie_font_files(): array
{
    return [
        'inter' => 'inter-latin-wght-normal.woff2',
        'manrope' => 'manrope-latin-wght-normal.woff2',
        'instrument-sans' => 'instrument-sans-latin-wght-normal.woff2',
        'hanken' => 'hanken-grotesk-latin-wght-normal.woff2',
        'space-grotesk' => 'space-grotesk-latin-wght-normal.woff2',
        'atkinson' => 'atkinson-hyperlegible-next-latin-wght-normal.woff2',
        'newsreader' => 'newsreader-latin-wght-normal.woff2',
        'fraunces' => 'fraunces-latin-opsz-normal.woff2',
        'instrument-serif' => 'instrument-serif-latin-400-normal.woff2',
        'eb-garamond' => 'eb-garamond-latin-wght-normal.woff2',
    ];
}

/** <link rel="preload"> für Fließtext- und Überschriften-Schrift (je eine variable Datei, latin) */
function galerie_font_preloads(): string
{
    $files = galerie_font_files();
    $h = '';
    foreach (array_unique([(string) design('font_body'), (string) design('font_head')]) as $font) {
        if (!isset($files[$font])) continue;
        $h .= '<link rel="preload" href="' . e(app()->theme->fontUrl('fonts/' . $files[$font])) . '" as="font" type="font/woff2" crossorigin>' . "\n";
    }
    return $h;
}

/** Name der Website (Kurzname für die Wortmarke) */
function galerie_name(bool $short = false): string
{
    $name = trim((string) setting('org_name'));
    $shortName = trim((string) setting('short_name'));
    if ($short && $shortName !== '') return $shortName;
    return $name !== '' ? $name : ($shortName !== '' ? $shortName : site_name());
}

// ------------------------------------------------------------------ Kontakt

/** Telefonnummer für die Anzeige – in weiteren Sprachen automatisch international (+49 …) */
function galerie_phone(): string
{
    return phone_display(trim((string) setting('phone')));
}

function galerie_phone_href(): ?string
{
    return tel_href(galerie_phone());
}

function galerie_email(): string
{
    $m = trim((string) setting('email'));
    return filled($m) ? $m : '';
}

/** Adresse als Zeilen: [Straße, PLZ Ort, Land] – nur befüllte Teile */
function galerie_address_lines(): array
{
    $street = trim((string) setting('street'));
    $city = trim(trim((string) setting('zip')) . ' ' . trim((string) setting('city')));
    $country = trim((string) setting('country'));
    return array_values(array_filter([$street, $city, $country], 'filled'));
}

/** Link mit Sonderwerten „phone“ und „email“ (Website) */
function galerie_link(?string $link): string
{
    return match (trim((string) $link)) {
        'phone' => galerie_phone_href() ?? link_href('#kontakt'),
        'email' => galerie_email() !== '' ? 'mailto:' . galerie_email() : link_href('#kontakt'),
        default => link_href($link),
    };
}

function galerie_link_attrs(?string $link): string
{
    $href = galerie_link($link);
    return 'href="' . e($href) . '"' . ext_attrs($href);
}

/** Hinweis für Screenreader bei Links in neuem Tab */
function galerie_ext_note(string $href): string
{
    return is_external($href) ? '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '';
}

/**
 * Button-Paar aus button_label/button_link und button2_label/button2_link. Nur Buttons mit Beschriftung und Link erscheinen.
 * $prefix: Pfad-Präfix für die Bearbeitung in Listen (z. B. 'items.0.').
 */
function galerie_buttons(\Core\Block $b, string $class = '', array $data = [], string $prefix = ''): string
{
    $d = $data ?: $b->data;
    $h = '';
    foreach (['button' => 'btn--primary', 'button2' => 'btn--secondary'] as $k => $style) {
        $label = trim((string) ($d[$k . '_label'] ?? ''));
        $link = trim((string) ($d[$k . '_link'] ?? ''));
        if ($label === '' || $link === '') continue;
        $href = galerie_link($link);
        $h .= '<a class="btn ' . $style . '" ' . galerie_link_attrs($link) . '><span' . $b->edit($prefix . $k . '_label') . '>' . e($label) . '</span>' . galerie_ext_note($href) . '</a>';
    }
    return $h !== '' ? '<div class="btn-row' . ($class !== '' ? ' ' . e($class) : '') . '">' . $h . '</div>' : '';
}

/** Mehrzeiliger Text → nicht leere Zeilen */
function galerie_lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('~\R~', (string) $text)), fn($l) => $l !== ''));
}

/** Häkchen-Liste */
function galerie_checks(array $lines, string $class = ''): string
{
    if (!$lines) return '';
    $h = '<ul class="checks' . ($class !== '' ? ' ' . e($class) : '') . '" role="list">';
    foreach ($lines as $l) $h .= '<li>' . icon('check-circle', ['class' => 'checks__ico']) . '<span>' . e($l) . '</span></li>';
    return $h . '</ul>';
}

// ------------------------------------------------------------------ Öffnungszeiten

function galerie_clock(?string $t): string
{
    return ltrim((string) $t, '0') ?: '0:00';
}

/** Zeitfenster eines Eintrags, z. B. „9:00–12:30, 13:30–17:00 Uhr“ (für API und Anzeige) */
function galerie_time_range(array $r): string
{
    $seg = [];
    if (!empty($r['von']) && !empty($r['bis'])) {
        if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
            $seg[] = galerie_clock($r['von']) . '–' . galerie_clock($r['pause_von']);
            $seg[] = galerie_clock($r['pause_bis']) . '–' . galerie_clock($r['bis']);
        } else {
            $seg[] = galerie_clock($r['von']) . '–' . galerie_clock($r['bis']);
        }
    }
    $time = $seg ? lt('{zeit} Uhr', ['zeit' => implode(', ', $seg)]) : '';
    return implode(' · ', array_filter([$time, trim((string) ($r['notiz'] ?? ''))]));
}

function galerie_weekday(int $dow): string
{
    return date_local(strtotime('2024-01-0' . ($dow === 0 ? 7 : $dow)), 'weekday');   // 01.01.2024 war ein Montag
}

/**
 * Öffnungszeiten, Mo–So sortiert; aufeinanderfolgende Tage mit gleichen Zeiten zusammengefasst.
 * @return list<array{days: string, time: string}>
 */
function galerie_hours(): array
{
    $byDay = [];
    foreach ((array) setting('hours', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if ($dow < 0 || $dow > 6) continue;
        $t = galerie_time_range($r);
        if ($t !== '') $byDay[$dow][] = $t;
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
    foreach (array_filter($groups) as $g) {
        $days = galerie_weekday($g['from']) . ($g['to'] !== $g['from'] ? '–' . galerie_weekday($g['to']) : '');
        $out[] = ['days' => $days, 'time' => $g['time']];
    }
    return $out;
}

// ------------------------------------------------------------------ Navigation, Recht, Social

/** Links Impressum / Datenschutz / Barrierefreiheit (in der Sprache der Seite, falls übersetzt) */
function galerie_legal_links(): array
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
function galerie_social(): array
{
    return array_values(array_filter((array) setting('social', []), fn($s) => trim((string) ($s['label'] ?? '')) !== '' && is_external($s['url'] ?? '')));
}

/** Hauptmenü: Seiten mit „Im Menü“ (Unterseiten) + Anker der Startseite mit „In Navigation“ */
function galerie_menu(): array
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
function galerie_header_cta(): ?array
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
function galerie_nav_fit(array $menu, ?array $cta, array $langs, bool $search, string $variant): string
{
    if (!$menu) return 'fit-0';
    $em = fn(string $s, float $size) => mb_strlen($s) * 0.57 * $size;          // mittlere Zeichenbreite
    $nav = 0.0;
    foreach ($menu as $m) $nav += $em((string) $m['label'], .9375) + 1.5 + ($m['children'] ? 1.05 : 0) + .25;
    $logo = (int) setting('logo');
    $brand = $logo ? 11.5 : 2.5 + .7 + $em(galerie_name(true), 1.125);
    // Aktionen rechts (Suche, Handlungsaufruf, Kontakt-Chip …) schätzt Core\HeaderActions je nach Einstellung
    $tools = \Core\HeaderActions::widthRem() + ($langs ? count($langs) * 2.4 + .6 : 0);
    $gut = 2 * 2.4;                                                           // Seitenabstand bei ~60–80 rem
    $total = match ($variant) {
        'centered' => max($brand, $nav + $tools + 1.5) + $gut,
        'floating' => $brand + $nav + $tools + 3 + $gut + 2,
        default => $brand + $nav + $tools + 3 + $gut,
    };
    $step = (int) (ceil($total * 1.05 / 6) * 6);
    return $step > 102 ? 'fit-no' : 'fit-' . max(30, $step);
}

/** Seiten-ID für Menü-Vergleiche */
function galerie_is_current(array $m): bool
{
    return is_int($m['id']) && $m['id'] === (int) (app()->currentPage['id'] ?? 0);
}

/**
 * Hauptmenü in der Leiste: Unterseiten als Aufklappmenü (<details> – ohne JavaScript bedienbar; site.js ergänzt
 * Pfeiltasten, Escape, Klick daneben). $rail: Seitenleiste (Unterseiten als Akkordeon untereinander).
 */
function galerie_nav_inline(array $menu): string
{
    $cur = fn(array $m) => galerie_is_current($m) ? ' aria-current="page"' : '';
    $chev = icon('caret-down', ['class' => 'hnav__chev']);
    $nested = function (array $items) use (&$nested, $cur): string {
        if (!$items) return '';
        $h = '<ul class="hnav__nested" role="list">';
        foreach ($items as $c) $h .= '<li><a class="hnav__sublink" href="' . e($c['href']) . '"' . $cur($c) . '>' . e($c['label']) . '</a>' . $nested($c['children']) . '</li>';
        return $h . '</ul>';
    };
    $h = '<ul class="hnav__list" role="list">';
    foreach ($menu as $m) {
        $active = $m['active'] ? ' data-active' : '';
        if (!$m['children']) {
            $h .= '<li class="hnav__item"><a class="hnav__link" href="' . e($m['href']) . '"' . $cur($m) . $active . '>' . e($m['label']) . '</a></li>';
            continue;
        }
        $h .= '<li class="hnav__item"><details class="hnav__sub"' . $active . '><summary class="hnav__link"><span>' . e($m['label']) . '</span>' . $chev . '</summary>'
            . '<ul class="hnav__panel" role="list"><li><a class="hnav__sublink hnav__sublink--parent" href="' . e($m['href']) . '"' . $cur($m) . '>'
            . e(lt('Übersicht: {name}', ['name' => $m['label']])) . '</a></li>';
        foreach ($m['children'] as $c) {
            $h .= '<li><a class="hnav__sublink" href="' . e($c['href']) . '"' . $cur($c) . '>' . e($c['label']) . '</a>' . $nested($c['children']) . '</li>';
        }
        $h .= '</ul></details></li>';
    }
    return $h . '</ul>';
}

/**
 * Hauptmenü im Seitenblatt (Menü-Schaltfläche): Unterseiten als Akkordeon (<details name> – nur eins offen, ohne JavaScript).
 * Der aktive Zweig ist geöffnet.
 */
function galerie_nav_sheet(array $menu): string
{
    $cur = fn(array $m) => galerie_is_current($m) ? ' aria-current="page"' : '';
    $chev = icon('caret-down', ['class' => 'mnav__chev']);
    $level = function (array $items, int $depth) use (&$level, $cur, $chev): string {
        $h = '<ul class="mnav__list mnav__list--' . $depth . '" role="list">';
        foreach ($items as $m) {
            if (!$m['children']) {
                $h .= '<li><a class="mnav__link" href="' . e($m['href']) . '"' . $cur($m) . '>' . e($m['label']) . '</a></li>';
                continue;
            }
            $h .= '<li><details class="mnav__acc" name="mnav-' . $depth . '"' . ($m['active'] ? ' open' : '') . '>'
                . '<summary class="mnav__link"><span>' . e($m['label']) . '</span>' . $chev . '</summary>'
                . '<div class="mnav__sub"><a class="mnav__link mnav__link--parent" href="' . e($m['href']) . '"' . $cur($m) . '>' . e(lt('Übersicht: {name}', ['name' => $m['label']])) . '</a>'
                . $level($m['children'], $depth + 1) . '</div></details></li>';
        }
        return $h . '</ul>';
    };
    return $level($menu, 1);
}

/** Direktkontakt (Telefon, E-Mail) – im Seitenblatt und im Fußbereich */
function galerie_quick_contact(string $class = 'quick'): string
{
    $phone = galerie_phone();
    $tel = galerie_phone_href();
    $email = galerie_email();
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
function galerie_head(\Core\Block $b, string $class = '', string $tag = 'h2'): string
{
    $d = $b->data;
    $eyebrow = trim((string) ($d['eyebrow'] ?? ''));
    $title = trim((string) ($d['title'] ?? ''));
    $intro = trim((string) ($d['intro'] ?? ''));
    if ($eyebrow === '' && $title === '' && $intro === '' && !is_editing()) return '';
    $h = '<header class="sec-head' . ($class !== '' ? ' ' . e($class) : '') . '">';
    if ($eyebrow !== '') $h .= '<p class="eyebrow"' . $b->edit('eyebrow') . '>' . e($eyebrow) . '</p>';
    if ($title !== '' || is_editing()) $h .= '<' . $tag . ' id="' . e($b->titleId()) . '" class="h2"' . $b->edit('title') . '>' . e($title) . '</' . $tag . '>';
    if ($intro !== '') $h .= '<p class="lead"' . $b->edit('intro') . '>' . nl2br(e($intro), false) . '</p>';
    return $h . '</header>';
}

/** Überschriften-Ebene für Einträge: h3 unter einer Abschnittsüberschrift, sonst h2 */
function galerie_htag(array $d): string
{
    return trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
}

/**
 * Bild im festen Format (Rahmen mit aspect-ratio). Ohne Bild: für Besucher nichts, im Editor ein Platzhalter.
 * @param string $ratio z. B. '4:3' oder '' (Originalformat)
 */
function galerie_image(?int $id, string $sizes, string $ratio, string $class = '', array $opt = []): string
{
    $pic = img($id, $sizes, ($ratio !== '' ? ['ratio' => $ratio] : []) + $opt);
    $cls = 'frame' . ($ratio !== '' ? ' r-' . str_replace(':', '-', $ratio) : '') . ($class !== '' ? ' ' . $class : '');
    if ($pic !== '') return '<div class="' . e($cls) . '">' . $pic . '</div>';
    return is_editing() ? '<div class="' . e($cls) . ' frame--empty"><span>' . e('Bild' . ($ratio !== '' ? ' · ' . $ratio : '')) . '</span></div>' : '';
}

/** Mindestbreite je Eintrag (Raster ohne feste Spaltenzahl): Klasse min-s | min-m | min-l */
function galerie_min(array $d, string $key = 'size'): string
{
    return 'min-' . (in_array($d[$key] ?? '', ['s', 'm', 'l'], true) ? $d[$key] : 'm');
}

/**
 * Inhaltsverzeichnis für lange Texte: vergibt IDs an <h3> (und <h4>) – die Zwischenüberschriften des Editors und liefert [html, einträge].
 * @return array{0: string, 1: list<array{id: string, label: string, level: int}>}
 */
function galerie_toc(string $html, string $prefix): array
{
    $toc = [];
    $used = [];
    $html = preg_replace_callback('~<(h[34])([^>]*)>(.*?)</\1>~is', function ($m) use (&$toc, &$used, $prefix) {
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
        $toc[] = ['id' => $id, 'label' => $label, 'level' => strtolower($m[1]) === 'h3' ? 2 : 3];
        return '<' . $m[1] . $attrs . '>' . $m[3] . '</' . $m[1] . '>';
    }, $html) ?? $html;
    return [$html, $toc];
}

/** Geschätzte Lesezeit in Minuten */
function galerie_reading_time(string $html): int
{
    return max(1, (int) round(str_word_count(strip_tags($html), 0, 'äöüÄÖÜß') / 220));
}

// ------------------------------------------------------------------ SEO, App, API

/** Schema.org Organization / LocalBusiness – nur befüllte Felder, nur auf der Startseite */
function galerie_jsonld(array $page): ?array
{
    if (empty($page['is_home'])) return null;
    $type = (string) setting('schema_type', 'Organization');
    $d = ['@context' => 'https://schema.org', '@type' => in_array($type, ['Organization', 'LocalBusiness', 'ProfessionalService', 'NGO', 'Restaurant', 'Museum', 'ArtGallery'], true) ? $type : 'Organization'];
    if (filled(setting('org_name'))) $d['name'] = (string) setting('org_name');
    if (filled(setting('tagline'))) $d['slogan'] = (string) setting('tagline');
    $d['url'] = absolute_url('/');
    if ($tel = galerie_phone_href()) $d['telephone'] = substr($tel, 4);
    if (galerie_email() !== '') $d['email'] = galerie_email();
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
    if ($social = galerie_social()) $d['sameAs'] = array_column($social, 'url');
    return isset($d['name']) ? $d : null;
}

/** Name, Kurzname und Kurzbefehle für die installierbare Web-App (Manifest) */
function galerie_app_info(): array
{
    $short = galerie_name(true);
    return [
        'name' => galerie_name(),
        'short_name' => mb_strlen($short) <= 14 ? $short : mb_substr($short, 0, 12) . '…',
        'description' => (string) (setting('tagline') ?: setting('default_meta_description')),
        'shortcuts' => [['name' => lt('Kontakt'), 'short_name' => lt('Kontakt'), 'url' => url('/') . '#kontakt']],
    ];
}

/** Öffentliche Basisdaten (GET /api/v1/public, ohne Token) */
function galerie_public_info(): array
{
    return [
        'name' => galerie_name(),
        'tagline' => filled(setting('tagline')) ? setting('tagline') : null,
        'address' => array_filter([
            'street' => filled(setting('street')) ? setting('street') : null,
            'zip' => filled(setting('zip')) ? setting('zip') : null,
            'city' => filled(setting('city')) ? setting('city') : null,
            'country' => filled(setting('country')) ? setting('country') : null,
        ]),
        'phone' => ($t = galerie_phone_href()) ? substr($t, 4) : null,
        'phone_display' => filled(galerie_phone()) ? galerie_phone() : null,
        'email' => galerie_email() ?: null,
        'social' => galerie_social(),
    ];
}

/**
 * Überschrift mit Hervorhebung: *Wort* → <em class="hl">Wort</em> (Akzentfarbe bzw. kursiv bei Serifen).
 * Im Editor bleibt der Rohtext stehen, damit die Sternchen beim direkten Bearbeiten erhalten bleiben.
 */
function galerie_title(string $text): string
{
    if (is_editing()) return e($text);
    return preg_replace('~\*([^*]+)\*~u', '<em class="hl">$1</em>', e($text)) ?? e($text);
}

// ================================================================== Galerie
//
// Drei Datentabellen (Kurznamen wählbar unter Website → Galerie, Standard: kuenstler, ausstellungen, werke) mit festen
// Feldnamen (README → „Datenmodell“). Der Status einer Ausstellung (jetzt / demnächst / vergangen) wird immer aus den Daten
// berechnet – es gibt kein Statusfeld. Der Seiten-Cache gilt je Kalendertag (Core\PageCache), so stimmt der Status jeden Tag.

/** Standard-Kurznamen der Galerie-Tabellen je Rolle */
function galerie_handles(): array
{
    return ['artists' => 'kuenstler', 'exhibitions' => 'ausstellungen', 'works' => 'werke'];
}

/** Tabelle einer Rolle (artists | exhibitions | works) – Einstellung „Website → Galerie“, sonst Standard-Kurzname */
function galerie_table(string $role): ?array
{
    $h = trim((string) setting('gallery_' . $role . '_table'));
    if ($h === '') $h = galerie_handles()[$role] ?? '';
    return $h !== '' ? Tables::findContent($h) : null;
}

/** Feld vorhanden? (Tabellen dürfen Felder weglassen – die Blöcke zeigen dann einfach weniger) */
function galerie_has(?array $t, string $field): bool
{
    return $t !== null && Tables::field($t, $field) !== null;
}

/** Heutiges Datum (Y-m-d) – zentral, damit Tests und Vorschau einheitlich rechnen */
function galerie_today(): string
{
    return date('Y-m-d');
}

/** Eintrag aus einem Link-Wert (entry:handle:ID oder /route/slug) bzw. dem aufgerufenen Eintrag der Detailseite */
function galerie_entry_from(string $role, ?string $link = null): ?array
{
    $t = galerie_table($role);
    if (!$t) return null;
    $link = trim((string) $link);
    if ($link === '') {
        $ctx = app()->entry;
        return $ctx && $ctx['table']['handle'] === $t['handle'] ? $ctx['entry'] : null;
    }
    if (preg_match('~^entry:([a-z][a-z0-9_]*):(\d+)$~', $link, $m)) {
        return $m[1] === $t['handle'] ? Entries::query($t, ['ids' => [(int) $m[2]]])[0] ?? null : null;
    }
    $route = trim((string) $t['settings']['route'], '/');
    if ($route !== '' && preg_match('~/' . preg_quote($route, '~') . '/([^/?#]+)~', $link, $m)) {
        return Entries::bySlug($t, $m[1]);
    }
    return null;
}

/** Einträge einer Rolle nach IDs (Reihenfolge der IDs), je Anfrage zwischengespeichert */
function galerie_by_ids(string $role, array $ids): array
{
    static $cache = [];
    $t = galerie_table($role);
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$t || !$ids) return [];
    $key = $t['handle'];
    $missing = array_values(array_diff($ids, array_keys($cache[$key] ?? [])));
    if ($missing) {
        foreach ($missing as $id) $cache[$key][$id] = null;
        foreach (Entries::query($t, ['ids' => $missing]) as $r) $cache[$key][(int) $r['id']] = $r;
    }
    $out = [];
    foreach ($ids as $id) if (!empty($cache[$key][$id])) $out[$id] = $cache[$key][$id];
    return $out;
}

// ------------------------------------------------------------------ Ausstellungen: Status und Daten

/** Status aus Beginn/Ende: current (läuft), upcoming (beginnt später), past (vorbei) */
function galerie_status(array $e, ?string $today = null): string
{
    $today ??= galerie_today();
    $start = substr((string) ($e['beginn'] ?? ''), 0, 10);
    $end = substr((string) ($e['ende'] ?? ''), 0, 10);
    if ($start !== '' && $start > $today) return 'upcoming';
    if ($end !== '' && $end < $today) return 'past';
    // Ohne Ende: läuft ab Beginn bis auf Weiteres (z. B. Sammlungspräsentation)
    return 'current';
}

/** Etikett je Status: „Jetzt“, „Demnächst“, „Archiv“ */
function galerie_status_label(string $status): string
{
    return match ($status) {
        'current' => lt('Jetzt'),
        'upcoming' => lt('Demnächst'),
        default => lt('Archiv'),
    };
}

/** Etikett als HTML (Gestaltung: Design → Galerie → „Etikett“); Hinweis „Letzte Tage“ in der letzten Woche */
function galerie_status_badge(array $e, string $class = ''): string
{
    $st = galerie_status($e);
    $label = galerie_status_label($st);
    $end = substr((string) ($e['ende'] ?? ''), 0, 10);
    if ($st === 'current' && $end !== '' && (strtotime($end) - strtotime(galerie_today())) <= 6 * 86400) $label = lt('Letzte Tage');
    return '<span class="xlabel xlabel--' . e($st) . ($class !== '' ? ' ' . e($class) : '') . '">' . e($label) . '</span>';
}

/**
 * Zeitraum einer Ausstellung je Design → Galerie → „Datumsangaben“:
 * long „12. März – 30. April 2026“ · numeric „12.03. – 30.04.2026“ · relative „Bis 30. April“ / „Ab 12. März“ (sonst long)
 */
function galerie_dates(array $e, ?string $style = null): string
{
    $style ??= (string) (design('date_style') ?: 'long');
    $s = !empty($e['beginn']) ? strtotime(substr((string) $e['beginn'], 0, 10)) : false;
    $en = !empty($e['ende']) ? strtotime(substr((string) $e['ende'], 0, 10)) : false;
    if (!$s) return '';
    $f = fmt();
    $de = \Core\Lang::current() === 'de';
    $thisYear = date('Y');
    if ($style === 'relative') {
        $st = galerie_status($e);
        if ($st === 'current' && $en) return lt('Bis {datum}', ['datum' => $f->date($en, date('Y', $en) === $thisYear ? 'day_month' : 'long')]);
        if ($st === 'current') return lt('Seit {datum}', ['datum' => $f->date($s, date('Y', $s) === $thisYear ? 'day_month' : 'long')]);
        if ($st === 'upcoming') return lt('Ab {datum}', ['datum' => $f->date($s, date('Y', $s) === $thisYear ? 'day_month' : 'long')]);
        $style = 'long';
    }
    if (!$en || date('Y-m-d', $en) === date('Y-m-d', $s)) return $f->date($s, $style === 'numeric' ? 'short' : 'long');
    if ($style === 'numeric') {
        $first = date('Y', $s) === date('Y', $en) && $de ? date('d.m.', $s) : $f->date($s);
        return $first . ' – ' . $f->date($en);
    }
    if (date('Y-m', $s) === date('Y-m', $en)) return date('j', $s) . ($de ? '.' : '') . ' – ' . $f->date($en, 'long');
    $first = date('Y', $s) === date('Y', $en) ? $f->date($s, 'day_month') : $f->date($s, 'long');
    return $first . ' – ' . $f->date($en, 'long');
}

/** Eröffnung (Vernissage): „Eröffnung: Freitag, 12. März, 19 Uhr“ – nur solange sie nicht vorbei ist ($always: immer) */
function galerie_opening(array $e, bool $always = false): string
{
    $v = trim((string) ($e['eroeffnung'] ?? ''));
    $ts = $v !== '' ? strtotime($v) : false;
    if (!$ts || (!$always && date('Y-m-d', $ts) < galerie_today())) return '';
    $f = fmt();
    $day = $f->date($ts, 'weekday') . ', ' . $f->date($ts, date('Y', $ts) === date('Y') ? 'day_month' : 'long');
    $time = date('i', $ts) === '00' ? date('G', $ts) : date('G:i', $ts);
    return lt('Eröffnung: {tag}, {zeit} Uhr', ['tag' => $day, 'zeit' => $time]);
}

/** Ort einer Ausstellung: Auswahl (Galerie, Showroom, Projektraum, Messe …) + freie Angabe */
function galerie_venue(array $e): string
{
    $t = galerie_table('exhibitions');
    $parts = [];
    if ($t && ($f = Tables::field($t, 'ort')) && trim((string) ($e['ort'] ?? '')) !== '') $parts[] = Tables::optionLabel($f, (string) $e['ort']);
    if (trim((string) ($e['ort_text'] ?? '')) !== '') $parts[] = trim((string) $e['ort_text']);
    return implode(' · ', array_unique($parts));
}

/**
 * Ausstellungen mit berechnetem Status (Schlüssel _status).
 * $o: status (Liste: current, upcoming, past), venue (Liste von ort-Werten; leer = alle), artist (ID), exclude (ID), limit
 * Sortierung: laufende nach Ende, kommende nach Beginn, vergangene neueste zuerst.
 */
function galerie_exhibitions(array $o = []): array
{
    $t = galerie_table('exhibitions');
    if (!$t) return [];
    $rows = Entries::query($t, ['limit' => 1000]);
    $today = galerie_today();
    $want = array_values(array_filter((array) ($o['status'] ?? [])));
    $venues = array_values(array_filter((array) ($o['venue'] ?? [])));
    $artist = (int) ($o['artist'] ?? 0);
    $out = [];
    foreach ($rows as $r) {
        $r['_status'] = galerie_status($r, $today);
        if ($want && !in_array($r['_status'], $want, true)) continue;
        if ($venues && !in_array((string) ($r['ort'] ?? ''), $venues, true)) continue;
        if ($artist && !in_array($artist, array_map('intval', (array) ($r['kuenstler'] ?? [])), true)) continue;
        if (!empty($o['exclude']) && (int) $r['id'] === (int) $o['exclude']) continue;
        $out[] = $r;
    }
    $rank = ['current' => 0, 'upcoming' => 1, 'past' => 2];
    usort($out, function ($a, $b) use ($rank) {
        if ($a['_status'] !== $b['_status']) return $rank[$a['_status']] <=> $rank[$b['_status']];
        return match ($a['_status']) {
            'current' => [(string) ($a['ende'] ?: '9999'), (string) $a['beginn']] <=> [(string) ($b['ende'] ?: '9999'), (string) $b['beginn']],
            'upcoming' => (string) $a['beginn'] <=> (string) $b['beginn'],
            default => (string) $b['beginn'] <=> (string) $a['beginn'],
        };
    });
    return !empty($o['limit']) ? array_slice($out, 0, (int) $o['limit']) : $out;
}

/** Die Ausstellung für „Aktuelle Ausstellung“: laufende (bevorzugt am gewünschten Ort), sonst die nächste kommende */
function galerie_featured_exhibition(array $venues = []): ?array
{
    foreach ([['current'], ['upcoming']] as $st) {
        $rows = galerie_exhibitions(['status' => $st, 'venue' => $venues]);
        if ($rows) return $rows[0];
    }
    return null;
}

// ------------------------------------------------------------------ Künstlerinnen und Künstler

/** Sortierschlüssel: Feld „Sortiername“, sonst letztes Wort des Namens („Lene Beispiel“ → „beispiel“) */
function galerie_sortkey(array $a): string
{
    $s = trim((string) ($a['sortname'] ?? ''));
    if ($s === '') {
        $parts = preg_split('~\s+~u', trim((string) ($a['name'] ?? '')));
        $s = (string) end($parts);
    }
    $s = mb_strtolower($s);
    return strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'á' => 'a', 'à' => 'a', 'ø' => 'o', 'å' => 'a', 'ç' => 'c', 'ñ' => 'n']);
}

/** Buchstabe für das A–Z-Register */
function galerie_initial(array $a): string
{
    $k = galerie_sortkey($a);
    $c = mb_strtoupper(mb_substr($k, 0, 1));
    return preg_match('~^[A-Z]$~', $c) ? $c : '#';
}

/** Künstlerinnen und Künstler: alle (nur hervorgehobene mit $featured), alphabetisch nach Sortiername oder nach Reihenfolge der Tabelle */
function galerie_artists(bool $featured = false, string $order = 'alpha'): array
{
    $t = galerie_table('artists');
    if (!$t) return [];
    $rows = Entries::query($t, ['limit' => 1000] + ($featured && galerie_has($t, 'featured') ? ['where' => [['featured', '=', '1']]] : []));
    if ($order === 'alpha') usort($rows, fn($a, $b) => strcmp(galerie_sortkey($a), galerie_sortkey($b)));
    return $rows;
}

/** Namen verknüpfter Künstler als Text bzw. HTML mit Links („A, B und C“) */
function galerie_artist_names(array $ids, bool $link = true): string
{
    $t = galerie_table('artists');
    $items = [];
    foreach (galerie_by_ids('artists', (array) $ids) as $a) {
        $href = $link && $t ? Entries::href($t, $a) : null;
        $name = e(Entries::title($t, $a));
        $items[] = $href ? '<a href="' . e($href) . '">' . $name . '</a>' : $name;
    }
    if (count($items) <= 1) return implode('', $items);
    $last = array_pop($items);
    return implode(', ', $items) . ' ' . e(lt('und')) . ' ' . $last;
}

// ------------------------------------------------------------------ Werke: Angaben, Verfügbarkeit, Preis, Anfrage

/**
 * Werke. $o: artist (ID), ids (Liste, Reihenfolge bleibt), availability (Liste: verfuegbar, reserviert, verkauft), exclude (ID), limit
 */
function galerie_works(array $o = []): array
{
    $t = galerie_table('works');
    if (!$t) return [];
    $q = ['limit' => 1000];
    $where = [];
    if (!empty($o['artist']) && galerie_has($t, 'kuenstler')) $where[] = ['kuenstler', '=', (string) (int) $o['artist']];
    if ($where) $q['where'] = $where;
    if (!empty($o['ids'])) $q['ids'] = array_map('intval', (array) $o['ids']);
    $rows = Entries::query($t, $q);
    if (!empty($o['ids'])) {
        $pos = array_flip(array_map('intval', (array) $o['ids']));
        usort($rows, fn($a, $b) => ($pos[$a['id']] ?? 0) <=> ($pos[$b['id']] ?? 0));
    }
    $avail = array_values(array_filter((array) ($o['availability'] ?? [])));
    $rows = array_values(array_filter($rows, fn($w) => (!$avail || in_array((string) ($w['verfuegbarkeit'] ?? ''), $avail, true))
        && (empty($o['exclude']) || (int) $w['id'] !== (int) $o['exclude'])));
    return !empty($o['limit']) ? array_slice($rows, 0, (int) $o['limit']) : $rows;
}

/** Verfügbarkeit [schlüssel, text] – verfuegbar | reserviert | verkauft, sonst ['', ''] */
function galerie_availability(array $w): array
{
    return match ((string) ($w['verfuegbarkeit'] ?? '')) {
        'verfuegbar' => ['available', lt('Verfügbar')],
        'reserviert' => ['reserved', lt('Reserviert')],
        'verkauft' => ['sold', lt('Verkauft')],
        default => ['', ''],
    };
}

/** Verfügbarkeit als Punkt + Wort (der Punkt ist Zierde – das Wort trägt die Information; Punkt aus: Design → Galerie) */
function galerie_avail_html(array $w): string
{
    [$k, $label] = galerie_availability($w);
    if ($k === '') return '';
    return '<span class="avail avail--' . e($k) . '"><span class="avail__dot" aria-hidden="true"></span>' . e($label) . '</span>';
}

/** Preis als Text: Betrag, „Preis auf Anfrage“ oder leer (verkauft, ausgeblendet, Design → Galerie → „Preise“: nie) */
function galerie_price(array $w): string
{
    if ((string) ($w['verfuegbarkeit'] ?? '') === 'verkauft') return '';
    $mode = (string) (design('price_mode') ?: 'per_work');
    if ($mode === 'never') return '';
    if ($mode === 'request') return lt('Preis auf Anfrage');
    $show = (string) ($w['preis_anzeige'] ?? 'anfrage');
    if ($show === 'aus') return '';
    if ($show === 'zeigen' && is_numeric($w['preis'] ?? null) && (float) $w['preis'] > 0) return fmt()->currency((float) $w['preis'], 'EUR', null);
    return lt('Preis auf Anfrage');
}

/** Kurzbezeichnung eines Werks für Anfragen: „Lene Beispiel: Ohne Titel (Blau), 2025“ */
function galerie_work_label(array $w): string
{
    $artist = trim(strip_tags(galerie_artist_names(array_filter([(int) ($w['kuenstler'] ?? 0)]), false)));
    $title = trim((string) ($w['titel'] ?? ''));
    $year = trim((string) ($w['jahr'] ?? ''));
    return trim(($artist !== '' ? $artist . ': ' : '') . $title . ($year !== '' ? ', ' . $year : ''));
}

/**
 * Link „Anfrage zu diesem Werk“ (Website → Galerie → „Anfragen“): Seite mit Formular (?werk=slug#anfrage – der Block „Kontakt“
 * füllt das Feld „werk“ vor), E-Mail mit Betreff, oder aus. Ohne $w: allgemeine Anfrage.
 */
function galerie_enquiry_href(?array $w = null): ?string
{
    $mode = (string) (setting('enquiry_mode') ?: 'page');
    if ($mode === 'off') return null;
    if ($mode === 'page' && ($pid = (int) setting('enquiry_page')) && ($p = Pages::find($pid)) && ($p['status'] === 'published' || app()->auth->check())) {
        return Pages::url($p) . ($w && !empty($w['slug']) ? '?werk=' . rawurlencode((string) $w['slug']) : '') . '#anfrage';
    }
    $mail = trim((string) setting('enquiry_email')) ?: galerie_email();
    if ($mail === '') return null;
    return 'mailto:' . $mail . '?subject=' . rawurlencode($w ? lt('Anfrage: {werk}', ['werk' => galerie_work_label($w)]) : lt('Anfrage an die Galerie'));
}

/** Beschriftung des Anfrage-Buttons */
function galerie_enquiry_label(): string
{
    $l = trim((string) setting('enquiry_label'));
    return $l !== '' ? $l : lt('Anfrage zu diesem Werk');
}

/** Wert für das Formularfeld „werk“ aus ?werk=slug (Block „Kontakt“ auf der Anfrageseite) */
function galerie_enquiry_prefill(): string
{
    $slug = trim((string) (app()->request?->query['werk'] ?? ''));
    if ($slug === '' || !preg_match('~^[a-z0-9-]{1,120}$~', $slug) || !($t = galerie_table('works'))) return '';
    $w = Entries::bySlug($t, $slug);
    return $w ? galerie_work_label($w) : '';
}

/**
 * Werkangaben (Museumsschild): Künstler, Titel (kursiv, <cite>), Jahr, Technik, Maße, Auflage, darunter Verfügbarkeit und Preis.
 * Die Gestaltung wählt Design → Galerie → „Werkangaben“ (cap-museum | cap-minimal | cap-line); $full: alles zeigen (Detailseite).
 * $o: href (Titel verlinken), artist (Künstler zeigen, Standard ja), meta (Verfügbarkeit/Preis, Standard ja), tag, class, htag (Überschrift für den Titel)
 */
function galerie_caption(array $w, array $o = []): string
{
    $tag = $o['tag'] ?? 'figcaption';
    $h = '<' . $tag . ' class="cap' . (!empty($o['full']) ? ' cap--full' : '') . (!empty($o['class']) ? ' ' . e($o['class']) : '') . '">';
    if (($o['artist'] ?? true) && !empty($w['kuenstler'])) {
        $h .= '<span class="cap__artist">' . galerie_artist_names([(int) $w['kuenstler']], false) . '</span>';
    }
    $title = trim((string) ($w['titel'] ?? ''));
    $cite = '<cite class="cap__title">' . (!empty($o['href']) && !is_editing() ? '<a class="cover-link" href="' . e($o['href']) . '">' . e($title) . '</a>' : e($title)) . '</cite>';
    $year = trim((string) ($w['jahr'] ?? ''));
    $h .= '<span class="cap__work">' . (!empty($o['htag']) ? '<' . $o['htag'] . ' class="cap__h">' . $cite . '</' . $o['htag'] . '>' : $cite)
        . ($year !== '' ? '<span class="cap__year">, ' . e($year) . '</span>' : '') . '</span>';
    foreach (['technik' => 'medium', 'masse' => 'size', 'auflage' => 'ed'] as $f => $cls) {
        $v = trim((string) ($w[$f] ?? ''));
        if ($v !== '') $h .= '<span class="cap__' . $cls . '">' . e($v) . '</span>';
    }
    if ($o['meta'] ?? true) {
        $meta = array_filter([galerie_avail_html($w), ($p = galerie_price($w)) !== '' ? '<span class="cap__price">' . e($p) . '</span>' : '']);
        if ($meta) $h .= '<span class="cap__meta">' . implode('', $meta) . '</span>';
    }
    return $h . '</' . $tag . '>';
}

/**
 * Werkabbildung im Originalformat (nie beschnitten) – Präsentation über Design → Galerie (af-flat | af-wall | af-mat).
 * $opt: wie img() (eager, alt …), class (zusätzliche Klasse)
 */
function galerie_art(?int $id, string $sizes, array $opt = []): string
{
    $class = (string) ($opt['class'] ?? '');
    unset($opt['class']);
    $pic = $id ? img($id, $sizes, $opt) : '';
    if ($pic === '') return is_editing() ? '<div class="art art--empty' . ($class !== '' ? ' ' . e($class) : '') . '"><span>' . e(__('Werkabbildung')) . '</span></div>' : '';
    return '<div class="art' . ($class !== '' ? ' ' . e($class) : '') . '"><div class="art__frame">' . $pic . '</div></div>';
}

// ------------------------------------------------------------------ Besuch: Orte, Ausnahmen, heute

/**
 * Orte der Galerie: Hauptort aus Website → Stammdaten (Adresse, Öffnungszeiten) + weitere Orte (Website → Galerie → „Weitere Orte“).
 * @return list<array{name: string, lines: list<string>, hours: list<array{days: string, time: string}>, hours_text: list<string>, note: string, map: string}>
 */
function galerie_venues(): array
{
    $out = [];
    $main = galerie_address_lines();
    $mainName = trim((string) setting('venue_name')) ?: galerie_name();
    if ($main || galerie_hours()) {
        $q = rawurlencode(implode(', ', array_merge([galerie_name()], $main)));
        $out[] = ['name' => $mainName, 'lines' => $main, 'hours' => galerie_hours(), 'hours_text' => [], 'note' => trim((string) setting('hours_note')),
            'map' => $main ? 'https://www.openstreetmap.org/search?query=' . $q : ''];
    }
    foreach ((array) setting('venues', []) as $v) {
        $name = trim((string) ($v['name'] ?? ''));
        if ($name === '') continue;
        $lines = galerie_lines($v['adresse'] ?? '');
        $map = trim((string) ($v['karte'] ?? ''));
        if ($map === '' && $lines) $map = 'https://www.openstreetmap.org/search?query=' . rawurlencode(implode(', ', $lines));
        $out[] = ['name' => $name, 'lines' => $lines, 'hours' => [], 'hours_text' => galerie_lines($v['zeiten'] ?? ''), 'note' => trim((string) ($v['hinweis'] ?? '')),
            'map' => is_external($map) ? $map : ''];
    }
    return $out;
}

/**
 * Abweichende Öffnungszeiten (Feiertage, Aufbau, Sommerpause) aus Website → Galerie, die heute gelten oder in den
 * nächsten $days Tagen beginnen. @return list<array{dates: string, text: string, closed: bool, time: string, today: bool}>
 */
function galerie_exceptions(int $days = 60): array
{
    $today = galerie_today();
    $until = date('Y-m-d', strtotime("+$days days"));
    $out = [];
    foreach ((array) setting('hours_exceptions', []) as $x) {
        $from = substr(trim((string) ($x['von'] ?? '')), 0, 10);
        if ($from === '') continue;
        $to = substr(trim((string) ($x['bis'] ?? '')), 0, 10) ?: $from;
        if ($to < $today || $from > $until) continue;
        $out[] = [
            'from' => $from,
            'dates' => galerie_dates(['beginn' => $from, 'ende' => $to], 'long'),
            'text' => trim((string) ($x['text'] ?? '')),
            'closed' => !array_key_exists('geschlossen', $x) || !empty($x['geschlossen']),
            'time' => trim((string) ($x['zeiten'] ?? '')),
            'today' => $from <= $today && $to >= $today,
        ];
    }
    usort($out, fn($a, $b) => strcmp($a['from'], $b['from']));
    return $out;
}

/** Heute: [zustand, text] – zustand open | closed | special (Ausnahme mit eigenen Zeiten); null ohne Öffnungszeiten */
function galerie_today_status(): ?array
{
    foreach (galerie_exceptions(0) as $x) {
        if (!$x['today']) continue;
        if ($x['closed']) return ['closed', $x['text'] !== '' ? lt('Heute geschlossen ({grund})', ['grund' => $x['text']]) : lt('Heute geschlossen')];
        return ['special', lt('Heute geöffnet: {zeit}', ['zeit' => $x['time'] !== '' ? $x['time'] : $x['text']])];
    }
    $rows = (array) setting('hours', []);
    if (!$rows) return null;
    $dow = (int) date('w');
    $times = [];
    foreach ($rows as $r) {
        if ((int) ($r['tag'] ?? -1) === $dow && ($t = galerie_time_range($r)) !== '') $times[] = $t;
    }
    return $times ? ['open', lt('Heute geöffnet: {zeit}', ['zeit' => implode(' · ', $times)])] : ['closed', lt('Heute geschlossen')];
}

/**
 * Ein Werk als Listeneintrag (Raster, Mauerwerk, Salon, „Weitere Werke“): Abbildung, Werkangaben, Anfrage.
 * $o: sizes, artist (Künstler zeigen), link (Detailseite verlinken), enquiry (Anfrage-Button), class, attrs (zusätzliche Attribute), htag
 */
function galerie_work_card(array $w, array $o = []): string
{
    $t = galerie_table('works');
    if (!$t) return '';
    $href = ($o['link'] ?? true) ? Entries::href($t, $w) : null;
    $pic = galerie_art(!empty($w['bild']) ? (int) $w['bild'] : null, (string) ($o['sizes'] ?? '(min-width: 1080px) 420px, (min-width: 640px) 50vw, 100vw'));
    $enq = ($o['enquiry'] ?? false) && ($w['verfuegbarkeit'] ?? '') !== 'verkauft' ? galerie_enquiry_href($w) : null;
    $h = '<li class="aw' . (!empty($o['class']) ? ' ' . e($o['class']) : '') . '"' . ($o['attrs'] ?? '') . ' data-reveal>'
        . edit_link('entry:' . $t['handle'] . ':' . $w['id'], Entries::title($t, $w))
        . '<figure class="aw__fig">' . $pic
        . galerie_caption($w, ['href' => $href, 'artist' => $o['artist'] ?? true, 'htag' => $o['htag'] ?? 'h3']) . '</figure>';
    if ($enq) {
        $h .= '<p class="aw__enq"><a class="aw__enq-link" href="' . e($enq) . '">' . e(galerie_enquiry_label())
            . '<span class="sr-only">: ' . e(galerie_work_label($w)) . '</span></a></p>';
    }
    return $h . '</li>';
}

/** Bilder als vergrößerbare Galerie (Lightbox des Kerns, js/media.mjs) – $class am Container, $sizes je Bild */
function galerie_lightbox(array $ids, string $class, string $sizes, string $label = ''): string
{
    $items = '';
    foreach ($ids as $n => $id) {
        $m = media($id);
        if (!$m || !str_starts_with((string) $m['mime'], 'image/')) continue;
        $cap = trim((string) ($m['title'] ?? ''));
        $credit = trim((string) ($m['credit'] ?? ''));
        $caption = trim($cap . ($credit !== '' ? ' · ' . $credit : ''));
        $pic = img($id, $sizes);
        if (is_editing()) { $items .= '<li class="lb__item">' . $pic . '</li>'; continue; }
        $items .= '<li class="lb__item"><a class="lb__link"' . \Core\MediaBlocks::lightboxLink($m, $caption) . '>' . $pic
            . '<span class="lb__zoom" aria-hidden="true">' . icon('plus') . '</span><span class="sr-only">' . e(lt('Bild {n} vergrößern', ['n' => $n + 1])) . '</span></a></li>';
    }
    if ($items === '') return '';
    return '<ul class="' . e($class) . '" role="list"' . (!is_editing() ? ' data-cms-lightbox' : '') . ($label !== '' ? ' aria-label="' . e($label) . '"' : '') . '>' . $items . '</ul>'
        . (!is_editing() ? \Core\MediaBlocks::lightbox() . \Core\MediaBlocks::script() : '');
}

// ------------------------------------------------------------------ Medien: Bilder und Videos je Eintrag
//
// Ein Eintrag hat ein Hauptbild (Feld „bild“ bzw. „portraet“, nur Bilder) und weitere Plätze für Bilder ODER Videos
// (Felder vom Typ „Datei“: ansicht_1 … bei Werken und Ausstellungen, medien_1 … bei Künstlern) – dazu optional einen
// YouTube-/Vimeo-Link (video_url). Der Kern kennt (noch) kein Listenfeld für Medien; die nummerierten Felder bilden die
// Liste nach (Vorschlag für den Kern: Feldtyp „gallery“, README → „Vorschläge für den Kern“).

/** Medien-Plätze einer Rolle in Reihenfolge: [feld => 'image'|'visual'] – nur Felder, die die Tabelle wirklich hat */
function galerie_media_slots(string $role): array
{
    $t = galerie_table($role);
    if (!$t) return [];
    $out = [];
    $main = $role === 'artists' ? 'portraet' : 'bild';
    if (($f = Tables::field($t, $main)) && $f['type'] === 'media') $out[$main] = 'image';
    foreach ($t['fields'] as $f) {
        if (preg_match('~^(ansicht|medien|bild)_\d+$~', $f['name']) && in_array($f['type'], ['file', 'media'], true)) {
            $out[$f['name']] = $f['type'] === 'media' ? 'image' : 'visual';
        }
    }
    return $out;
}

/** Medien-IDs eines Eintrags in Reihenfolge der Plätze ($main: Hauptbild mitzählen) */
function galerie_entry_media(string $role, array $e, bool $main = true): array
{
    $ids = [];
    foreach (galerie_media_slots($role) as $f => $kind) {
        if (!$main && $kind === 'image' && !preg_match('~_\d+$~', $f)) continue;
        if (!empty($e[$f])) $ids[] = (int) $e[$f];
    }
    return array_values(array_unique($ids));
}

/** Ist das Medium ein Video (MP4 aus der Mediathek)? */
function galerie_is_video(?array $m): bool
{
    return $m !== null && str_starts_with((string) ($m['mime'] ?? ''), 'video/');
}

/**
 * Gemischte Galerie: Bilder vergrößerbar (Lightbox des Kerns), Videos als Player mit Vorschaubild, Untertiteln und Transkript
 * (Kern-Fragment „video-embed“ – startet nie von selbst), dazu ein YouTube-/Vimeo-Link mit Zwei-Klick-Lösung.
 * Videos stehen in einer eigenen Liste, damit die Lightbox nur Bilder durchblättert.
 */
function galerie_media_gallery(array $ids, string $videoUrl = '', string $label = '', string $sizes = '(min-width: 1080px) 640px, 100vw'): string
{
    $images = $videos = [];
    foreach ($ids as $id) {
        $m = media((int) $id);
        if (!$m) continue;
        if (galerie_is_video($m)) $videos[] = (int) $id;
        elseif (str_starts_with((string) $m['mime'], 'image/')) $images[] = (int) $id;
    }
    $h = $images ? galerie_lightbox($images, 'lb lb--views', $sizes, $label) : '';
    $vh = '';
    foreach ($videos as $vid) {
        $m = media($vid);
        $vh .= '<li class="mg__video"><figure class="video">' . app()->theme->partial('video-embed', ['url' => '', 'file' => $vid, 'poster' => null, 'ratio' => '16-9',
            'label' => trim((string) ($m['title'] ?? '')) ?: lt('Video')]) . (trim((string) ($m['title'] ?? '')) !== '' ? '<figcaption class="video__cap">' . e((string) $m['title']) . '</figcaption>' : '') . '</figure></li>';
    }
    $videoUrl = trim($videoUrl);
    if ($videoUrl !== '' && \Core\Embeds::parse($videoUrl)) {
        $vh .= '<li class="mg__video"><figure class="video">' . app()->theme->partial('video-embed', ['url' => $videoUrl, 'file' => null, 'poster' => null, 'ratio' => '16-9', 'label' => $label ?: lt('Video')]) . '</figure></li>';
    }
    if ($vh !== '') $h .= '<ul class="mg__videos" role="list">' . $vh . '</ul>';
    return $h !== '' ? '<div class="mg">' . $h . '</div>' : '';
}

/**
 * Medien verwalten (nur angemeldete Redaktion mit Recht auf den Eintrag, auf der Detailseite außerhalb des Seiten-Editors):
 * eine Ablagefläche „Bilder und Videos hierher ziehen“ (auch als Knopf), Hochladen mit Fortschritt über den Uploader des
 * Kerns (window.CMSMedia.Uploader – Alt-Text wird aus den Angaben vorausgefüllt), Reihenfolge ändern, entfernen, aus der
 * Mediathek wählen. Speichert sofort über die Eintrags-Schnittstelle des Kerns (POST /admin/api/entries/{tabelle}/{id}).
 * Verhalten: js/gallery-edit.js · Aussehen: css/gallery-edit.css (beides nur für die Redaktion).
 */
function galerie_media_manager(string $role, array $e, string $alt = ''): string
{
    $t = galerie_table($role);
    if (!$t || is_editing() || !app()->auth->check()) return '';
    $ctx = \Core\Data\EntryEdit::current();
    if (!$ctx || $ctx['table']['handle'] !== $t['handle'] || (int) $ctx['entry']['id'] !== (int) $e['id']) return '';
    $slots = galerie_media_slots($role);
    if (!$slots) return '';
    $items = [];
    foreach ($slots as $f => $kind) {
        $m = !empty($e[$f]) ? media((int) $e[$f]) : null;
        if ($m) $items[] = ['f' => $f] + array_intersect_key(\Core\Media::toJson($m), array_flip(['id', 'thumb', 'display', 'kind', 'mime', 'alt']));
    }
    $cfg = ['endpoint' => \Core\Data\EntryEdit::endpoint($t, $e), 'csrf' => \Core\Csrf::token(), 'alt' => $alt,
        'slots' => array_map(fn($f, $k) => ['f' => $f, 'k' => $k, 'label' => (string) (Tables::field($t, $f)['label'] ?? $f)], array_keys($slots), $slots), 'items' => $items];
    $id = 'gmm-' . $t['handle'] . '-' . (int) $e['id'];
    return galerie_edit_assets()
        . '<section class="gmm" id="' . e($id) . '" data-gmm="' . e(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '" aria-labelledby="' . e($id) . '-h">'
        . '<div class="gmm__head"><h2 class="gmm__h" id="' . e($id) . '-h">' . e(__('Bilder und Videos')) . '</h2>'
        . '<p class="gmm__note">' . e(__('Nur für die Redaktion sichtbar · wird sofort gespeichert')) . '</p></div>'
        . '<div class="gmm__drop" data-gmm-drop><p class="gmm__droptext">' . e(__('Bilder und Videos hierher ziehen')) . '</p>'
        . '<p class="gmm__actions"><button type="button" class="gmm__btn gmm__btn--primary" data-gmm-choose>' . e(__('Dateien auswählen …')) . '</button>'
        . '<button type="button" class="gmm__btn" data-gmm-library>' . e(__('Aus der Mediathek …')) . '</button></p>'
        . '<p class="gmm__hint">' . e(__('JPG, PNG, WebP oder MP4. Das erste Bild ist das Hauptbild. Alt-Text wird aus den Angaben vorgeschlagen.')) . '</p></div>'
        . '<ol class="gmm__list" data-gmm-list></ol><div class="gmm__queue" data-gmm-queue></div>'
        . '<p class="gmm__status" data-gmm-status role="status" aria-live="polite"></p>'
        . '<p class="gmm__reload" data-gmm-reload hidden><button type="button" class="gmm__btn" data-gmm-refresh>' . e(__('Seite aktualisieren')) . '</button></p>'
        . '</section>';
}

/** Ablagefläche „Neues Werk aus Foto“ im Block „Werke“ (nur im Seiten-Editor, mit Recht auf die Tabelle „Werke“) */
function galerie_new_work_drop(): string
{
    $t = galerie_table('works');
    if (!$t || !is_editing() || !\Core\Data\EntryEdit::canTable($t)) return '';
    $cfg = ['endpoint' => \Core\Data\EntryEdit::endpoint($t), 'csrf' => \Core\Csrf::token(), 'base' => url('/admin/api/entries/' . $t['handle'] . '/')];
    return galerie_edit_assets() . '<div class="gnew" data-gnew="' . e(json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '">'
        . '<p class="gnew__text"><strong>' . e(__('Neues Werk aus Foto')) . '</strong> ' . e(__('Foto hierher ziehen – das Werk wird als Entwurf angelegt (Titel aus dem Dateinamen) und öffnet sich zum Ergänzen.')) . '</p>'
        . '<button type="button" class="gmm__btn gmm__btn--primary" data-gnew-choose>' . e(__('Foto auswählen …')) . '</button>'
        . '<div class="gmm__queue" data-gnew-queue></div><p class="gmm__status" data-gnew-status role="status" aria-live="polite"></p></div>';
}

/** Skript und Stylesheet der Medienverwaltung – einmal je Seite, nur für die Redaktion (im Seiten-Editor lädt 'editor_js') */
function galerie_edit_assets(): string
{
    static $done = false;
    if ($done) return '';
    $done = true;
    $h = '<link rel="stylesheet" href="' . e(theme_asset('css/gallery-edit.css')) . '">';
    if (!is_editing()) $h .= '<script src="' . e(theme_asset('js/gallery-edit.js')) . '" defer></script>';
    return $h;
}
