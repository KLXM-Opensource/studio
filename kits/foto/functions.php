<?php
/*
 * Kit-Helfer „foto“ (Grundlage: Kit „fluid“). Werden von Templates und Block-Renderern genutzt.
 * Präfix foto_ – kit:create benennt ihn für abgeleitete Kits automatisch um.
 * Bild-Helfer (Bühne, Fotostrecke, Serien, Lightbox): Abschnitt „Fotografie“ am Ende der Datei.
 * Alle sichtbaren festen Texte laufen über lt() (Übersetzungen: lang/site/{sprache}.php).
 */

use Core\Pages;

/**
 * Klassen für <html>: Design-Werte (hdr-inline, btn-pill, cards-glass, has-dark …) + no-js.
 * is-darkbase: Die Vorlage ist schon im hellen Schema dunkel (z. B. „Tech-Startup“) → color-scheme: dark für Formularelemente.
 * CSP: keine Inline-Styles – alle Gestaltung über Variablen der Design-Datei und diese Klassen.
 */
function foto_html_class(): string
{
    $bg = (string) design('background');
    $dark = $bg !== '' && foto_luminance($bg) < 0.18;
    $serif = in_array((string) design('font_head'), ['fraunces', 'newsreader', 'instrument-serif'], true);
    return trim('no-js ' . design_classes() . ($dark ? ' is-darkbase' : '') . ($serif ? ' head-serif' : ''));
}

function foto_luminance(string $hex): float
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) return 1.0;
    $c = array_map(fn($i) => hexdec(substr($hex, $i, 2)) / 255, [0, 2, 4]);
    $c = array_map(fn($x) => $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/** <link rel="preload"> für die Hauptschnitte der gewählten Schriften (Kit-Schriften vom Schriften-Manager, Core\Design::preloads) */
function foto_font_preloads(): string
{
    return \Core\Design::preloads(['font_body', 'font_head']);
}

/** Name der Website (Kurzname für die Wortmarke) */
function foto_name(bool $short = false): string
{
    $name = trim((string) setting('org_name'));
    $shortName = trim((string) setting('short_name'));
    if ($short && $shortName !== '') return $shortName;
    return $name !== '' ? $name : ($shortName !== '' ? $shortName : site_name());
}

// ------------------------------------------------------------------ Kontakt

/** Telefonnummer für die Anzeige – in weiteren Sprachen automatisch international (+49 …) */
function foto_phone(): string
{
    return phone_display(trim((string) setting('phone')));
}

function foto_phone_href(): ?string
{
    return tel_href(foto_phone());
}

function foto_email(): string
{
    $m = trim((string) setting('email'));
    return filled($m) ? $m : '';
}

/** Adresse als Zeilen: [Straße, PLZ Ort, Land] – nur befüllte Teile */
function foto_address_lines(): array
{
    $street = trim((string) setting('street'));
    $city = trim(trim((string) setting('zip')) . ' ' . trim((string) setting('city')));
    $country = trim((string) setting('country'));
    return array_values(array_filter([$street, $city, $country], 'filled'));
}

/** Link mit Sonderwerten „phone“ und „email“ (Website) */
function foto_link(?string $link): string
{
    return match (trim((string) $link)) {
        'phone' => foto_phone_href() ?? link_href('#kontakt'),
        'email' => foto_email() !== '' ? 'mailto:' . foto_email() : link_href('#kontakt'),
        default => link_href($link),
    };
}

function foto_link_attrs(?string $link): string
{
    $href = foto_link($link);
    return 'href="' . e($href) . '"' . ext_attrs($href);
}

/** Hinweis für Screenreader bei Links in neuem Tab */
function foto_ext_note(string $href): string
{
    return is_external($href) ? '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '';
}

/**
 * Button-Paar aus button_label/button_link und button2_label/button2_link. Nur Buttons mit Beschriftung und Link erscheinen.
 * $prefix: Pfad-Präfix für die Bearbeitung in Listen (z. B. 'items.0.').
 */
function foto_buttons(\Core\Block $b, string $class = '', array $data = [], string $prefix = ''): string
{
    $d = $data ?: $b->data;
    $h = '';
    foreach (['button' => 'btn--primary', 'button2' => 'btn--secondary'] as $k => $style) {
        $label = trim((string) ($d[$k . '_label'] ?? ''));
        $link = trim((string) ($d[$k . '_link'] ?? ''));
        if ($label === '' || $link === '') continue;
        $href = foto_link($link);
        $h .= '<a class="btn ' . $style . '" ' . foto_link_attrs($link) . '><span' . $b->edit($prefix . $k . '_label') . '>' . e($label) . '</span>' . foto_ext_note($href) . '</a>';
    }
    return $h !== '' ? '<div class="btn-row' . ($class !== '' ? ' ' . e($class) : '') . '">' . $h . '</div>' : '';
}

/** Mehrzeiliger Text → nicht leere Zeilen */
function foto_lines(?string $text): array
{
    return array_values(array_filter(array_map('trim', preg_split('~\R~', (string) $text)), fn($l) => $l !== ''));
}

/** Häkchen-Liste */
function foto_checks(array $lines, string $class = ''): string
{
    if (!$lines) return '';
    $h = '<ul class="checks' . ($class !== '' ? ' ' . e($class) : '') . '" role="list">';
    foreach ($lines as $l) $h .= '<li>' . icon('check-circle', ['class' => 'checks__ico']) . '<span>' . foto_title($l) . '</span></li>';
    return $h . '</ul>';
}

// ------------------------------------------------------------------ Öffnungszeiten

function foto_clock(?string $t): string
{
    return ltrim((string) $t, '0') ?: '0:00';
}

/** Zeitfenster eines Eintrags, z. B. „9:00–12:30, 13:30–17:00 Uhr“ (für API und Anzeige) */
function foto_time_range(array $r): string
{
    $seg = [];
    if (!empty($r['von']) && !empty($r['bis'])) {
        if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
            $seg[] = foto_clock($r['von']) . '–' . foto_clock($r['pause_von']);
            $seg[] = foto_clock($r['pause_bis']) . '–' . foto_clock($r['bis']);
        } else {
            $seg[] = foto_clock($r['von']) . '–' . foto_clock($r['bis']);
        }
    }
    $time = $seg ? lt('{zeit} Uhr', ['zeit' => implode(', ', $seg)]) : '';
    return implode(' · ', array_filter([$time, trim((string) ($r['notiz'] ?? ''))]));
}

function foto_weekday(int $dow): string
{
    return date_local(strtotime('2024-01-0' . ($dow === 0 ? 7 : $dow)), 'weekday');   // 01.01.2024 war ein Montag
}

/**
 * Öffnungszeiten, Mo–So sortiert; aufeinanderfolgende Tage mit gleichen Zeiten zusammengefasst.
 * @return list<array{days: string, time: string}>
 */
function foto_hours(): array
{
    $byDay = [];
    foreach ((array) setting('hours', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if ($dow < 0 || $dow > 6) continue;
        $t = foto_time_range($r);
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
        $days = foto_weekday($g['from']) . ($g['to'] !== $g['from'] ? '–' . foto_weekday($g['to']) : '');
        $out[] = ['days' => $days, 'time' => $g['time']];
    }
    return $out;
}

// ------------------------------------------------------------------ Navigation, Recht, Social

/** Links Impressum / Datenschutz / Barrierefreiheit (in der Sprache der Seite, falls übersetzt) */
function foto_legal_links(): array
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
function foto_social(): array
{
    return array_values(array_filter((array) setting('social', []), fn($s) => trim((string) ($s['label'] ?? '')) !== '' && is_external($s['url'] ?? '')));
}

/** Hauptmenü: Seiten mit „Im Menü“ (Unterseiten) + Anker der Startseite mit „In Navigation“ */
function foto_menu(): array
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
function foto_header_cta(): ?array
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
function foto_nav_fit(array $menu, ?array $cta, array $langs, bool $search, string $variant): string
{
    if (!$menu) return 'fit-0';
    $em = fn(string $s, float $size) => mb_strlen($s) * 0.57 * $size;          // mittlere Zeichenbreite
    $nav = 0.0;
    $split = foto_nav_parent() !== 'overview';
    foreach ($menu as $m) $nav += $em((string) $m['label'], .9375) + 1.5 + ($m['children'] ? ($split ? 1.75 : 1.05) : 0) + .25;
    $logo = (int) setting('logo');
    $brand = $logo ? 11.5 : 2.5 + .7 + $em(foto_name(true), 1.125);
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
function foto_is_current(array $m): bool
{
    return is_int($m['id']) && $m['id'] === (int) (app()->currentPage['id'] ?? 0);
}

/** Menüpunkte mit Unterseiten: „split“ (Link + Pfeil, Standard), „hover“ (dazu Öffnen beim Überfahren), „overview“ (Klick öffnet, mit „Übersicht“) */
function foto_nav_parent(): string
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
function foto_nav_inline(array $menu): string
{
    $cur = fn(array $m) => foto_is_current($m) ? ' aria-current="page"' : '';
    $chev = icon('caret-down', ['class' => 'hnav__chev']);
    $split = foto_nav_parent() !== 'overview';
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
function foto_nav_sheet(array $menu): string
{
    $cur = fn(array $m) => foto_is_current($m) ? ' aria-current="page"' : '';
    $chev = icon('caret-down', ['class' => 'mnav__chev']);
    $split = foto_nav_parent() !== 'overview';
    $n = 0;
    $level = function (array $items, int $depth) use (&$level, &$n, $cur, $chev, $split): string {
        $h = '<ul class="mnav__list mnav__list--' . $depth . '" role="list">';
        foreach ($items as $m) {
            if (!$m['children']) {
                $h .= '<li><a class="mnav__link" href="' . e($m['href']) . '"' . $cur($m) . '>' . e($m['label']) . '</a></li>';
                continue;
            }
            if ($split) {
                $id = 'mnav-sub-' . (++$n);
                $h .= '<li><div class="mnav__row"><a class="mnav__link" href="' . e($m['href']) . '"' . $cur($m) . '>' . e($m['label']) . '</a>'
                    . '<button type="button" class="mnav__toggle" aria-expanded="' . ($m['active'] ? 'true' : 'false') . '" aria-controls="' . $id . '">'
                    . $chev . '<span class="sr-only">' . e(lt('Unterseiten von {name}', ['name' => $m['label']])) . '</span></button></div>'
                    . '<div class="mnav__sub" id="' . $id . '">' . $level($m['children'], $depth + 1) . '</div></li>';
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
function foto_quick_contact(string $class = 'quick'): string
{
    $phone = foto_phone();
    $tel = foto_phone_href();
    $email = foto_email();
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
function foto_head(\Core\Block $b, string $class = '', string $tag = 'h2'): string
{
    $d = $b->data;
    $eyebrow = trim((string) ($d['eyebrow'] ?? ''));
    $title = trim((string) ($d['title'] ?? ''));
    $intro = trim((string) ($d['intro'] ?? ''));
    if ($eyebrow === '' && $title === '' && $intro === '' && !is_editing()) return '';
    $h = '<header class="sec-head' . ($class !== '' ? ' ' . e($class) : '') . '">';
    if ($eyebrow !== '') $h .= '<p class="eyebrow"' . $b->edit('eyebrow') . '>' . foto_title($eyebrow) . '</p>';
    if ($title !== '' || is_editing()) $h .= '<' . $tag . ' id="' . e($b->titleId()) . '" class="h2"' . $b->edit('title') . '>' . foto_title($title) . '</' . $tag . '>';
    if ($intro !== '') $h .= '<p class="lead"' . $b->edit('intro') . '>' . nl2br(foto_title($intro), false) . '</p>';
    return $h . '</header>';
}

/** Überschriften-Ebene für Einträge: h3 unter einer Abschnittsüberschrift, sonst h2 */
function foto_htag(array $d): string
{
    return trim((string) ($d['title'] ?? '')) !== '' ? 'h3' : 'h2';
}

/**
 * Bild im festen Format (Rahmen mit aspect-ratio). Ohne Bild: für Besucher nichts, im Editor ein Platzhalter.
 * @param string $ratio z. B. '4:3' oder '' (Originalformat)
 */
function foto_image(?int $id, string $sizes, string $ratio, string $class = '', array $opt = []): string
{
    $pic = img($id, $sizes, ($ratio !== '' ? ['ratio' => $ratio] : []) + $opt);
    $cls = 'frame' . ($ratio !== '' ? ' r-' . str_replace(':', '-', $ratio) : '') . ($class !== '' ? ' ' . $class : '');
    if ($pic !== '') return '<div class="' . e($cls) . '">' . $pic . '</div>';
    return is_editing() ? '<div class="' . e($cls) . ' frame--empty"><span>' . e('Bild' . ($ratio !== '' ? ' · ' . $ratio : '')) . '</span></div>' : '';
}

/** Mindestbreite je Eintrag (Raster ohne feste Spaltenzahl): Klasse min-s | min-m | min-l */
function foto_min(array $d, string $key = 'size'): string
{
    return 'min-' . (in_array($d[$key] ?? '', ['s', 'm', 'l'], true) ? $d[$key] : 'm');
}

/**
 * Inhaltsverzeichnis für lange Texte: vergibt IDs an <h3> (und <h4>) – die Zwischenüberschriften des Editors und liefert [html, einträge].
 * @return array{0: string, 1: list<array{id: string, label: string, level: int}>}
 */
function foto_toc(string $html, string $prefix): array
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
function foto_reading_time(string $html): int
{
    return max(1, (int) round(str_word_count(strip_tags($html), 0, 'äöüÄÖÜß') / 220));
}

// ------------------------------------------------------------------ SEO, App, API

/** Schema.org Organization / LocalBusiness – nur befüllte Felder, nur auf der Startseite */
function foto_jsonld(array $page): ?array
{
    if (empty($page['is_home'])) return null;
    $type = (string) setting('schema_type', 'ProfessionalService');
    $d = ['@context' => 'https://schema.org', '@type' => in_array($type, ['Person', 'Organization', 'LocalBusiness', 'ProfessionalService'], true) ? $type : 'ProfessionalService'];
    if (filled(setting('org_name'))) $d['name'] = (string) setting('org_name');
    if (filled(setting('tagline'))) $d['slogan'] = (string) setting('tagline');
    $d['url'] = absolute_url('/');
    if ($tel = foto_phone_href()) $d['telephone'] = substr($tel, 4);
    if (foto_email() !== '') $d['email'] = foto_email();
    $logo = media((int) setting('logo') ?: null);
    if ($logo && $d['@type'] !== 'Person') $d['logo'] = site_url() . \Core\Media::url($logo);
    $addr = array_filter([
        'streetAddress' => filled(setting('street')) ? (string) setting('street') : null,
        'postalCode' => filled(setting('zip')) ? (string) setting('zip') : null,
        'addressLocality' => filled(setting('city')) ? (string) setting('city') : null,
        'addressCountry' => filled(setting('country')) ? (string) setting('country') : null,
    ]);
    if ($addr) $d['address'] = ['@type' => 'PostalAddress'] + $addr;
    if ($p = \Core\Maps::siteLocation()) $d['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $p[0], 'longitude' => $p[1]];
    if (!in_array($d['@type'], ['Organization', 'Person'], true)) {
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
    if ($social = foto_social()) $d['sameAs'] = array_column($social, 'url');
    return isset($d['name']) ? $d : null;
}

/** Name, Kurzname und Kurzbefehle für die installierbare Web-App (Manifest) */
function foto_app_info(): array
{
    $short = foto_name(true);
    return [
        'name' => foto_name(),
        'short_name' => mb_strlen($short) <= 14 ? $short : mb_substr($short, 0, 12) . '…',
        'description' => (string) (setting('tagline') ?: setting('default_meta_description')),
        'shortcuts' => [['name' => lt('Kontakt'), 'short_name' => lt('Kontakt'), 'url' => url('/') . '#kontakt']],
    ];
}

/** Öffentliche Basisdaten (GET /api/v1/public, ohne Token) */
function foto_public_info(): array
{
    return [
        'name' => foto_name(),
        'tagline' => filled(setting('tagline')) ? setting('tagline') : null,
        'address' => array_filter([
            'street' => filled(setting('street')) ? setting('street') : null,
            'zip' => filled(setting('zip')) ? setting('zip') : null,
            'city' => filled(setting('city')) ? setting('city') : null,
            'country' => filled(setting('country')) ? setting('country') : null,
        ]),
        'phone' => ($t = foto_phone_href()) ? substr($t, 4) : null,
        'phone_display' => filled(foto_phone()) ? foto_phone() : null,
        'email' => foto_email() ?: null,
        'social' => foto_social(),
    ];
}

/**
 * Überschrift mit Hervorhebung: *Wort* → <em class="hl">Wort</em> (Akzentfarbe bzw. kursiv bei Serifen).
 * Im Editor ist die Hervorhebung sichtbar, die Sternchen bleiben als dezente Textzeichen stehen (span.hl-mark, Gestaltung im
 * Kern: resources/css/editor.css) – innerText liefert beim Speichern weiter „*Wort*“.
 */
function foto_title(string $text): string
{
    // *Wort* → <em class="hl"> (Kern: emphasis_hl – ein Muster für alle Kits; Bearbeiten: Sternchen dezent sichtbar)
    return emphasis_hl($text);
}


// ------------------------------------------------------------------ Fotografie (Bühne, Fotostrecke, Serien)

/**
 * Marke oben links: Wortmarke (Design → „Marke oben links“ = Wortmarke oder kein Logo hinterlegt) bzw. das Kern-Fragment
 * „brand“ mit Logo-Bild. Die Wortmarke nutzt den Kurznamen und die Schrift der Überschriften – ohne Monogramm.
 */
function foto_brand(string $href, string $class = ''): string
{
    if (design('logo_style') === 'image' && (int) setting('logo') && !landing()?->name) {
        return app()->theme->partial('brand', ['href' => $href, 'class' => $class]);
    }
    $name = landing()?->name ?? foto_name(true);
    return '<a class="brand brand--word' . ($class !== '' ? ' ' . e($class) : '') . '" href="' . e($href) . '">'
        . '<span class="brand__name">' . e($name) . '</span><span class="sr-only"> – ' . e(lt('Startseite')) . '</span></a>';
}

/** Breite einer Galerie: Block-Option „width“ (leer = Design → „Breite der Galerien“) → Klasse gwrap--{contained|wide|full} */
function foto_gw(array $d, string $key = 'width'): string
{
    $w = (string) ($d[$key] ?? '');
    if (!in_array($w, ['contained', 'wide', 'full'], true)) $w = (string) design('gallery_width');
    return 'gwrap gwrap--' . (in_array($w, ['contained', 'wide', 'full'], true) ? $w : 'wide');
}

/** Bildformat „3:2“ → Klasse r-3-2 ('' = Originalformat) */
function foto_ratio_class(string $ratio): string
{
    return $ratio !== '' ? 'r-' . str_replace(':', '-', $ratio) : '';
}

/**
 * Ein Foto als <figure class="ph">: Rahmen (.ph__box, Passepartout/Linie per Design), Bild (.ph__img, mit Format zugeschnitten
 * oder im Originalformat), optional Link zur Lightbox (js/lightbox.js; ohne JavaScript: Link zur großen Fassung) und
 * Bildunterschrift (Design → „Bildunterschriften“: darunter, beim Zeigen, auf dem Bild, ausgeblendet).
 *
 * @param array $o sizes, ratio ('' = Original), caption, capEdit (Attribut für die Direktbearbeitung), lightbox (bool),
 *                 n (Nummer für die Beschriftung „Bild n vergrößern“), class, eager, path (Datenpfad für „Anpassen“), alt
 */
function foto_photo(array $m, array $o = []): string
{
    if (empty($m['mime'])) return '';
    if (str_starts_with((string) $m['mime'], 'video/')) return foto_video($m, $o);
    $o += ['sizes' => '100vw', 'ratio' => '', 'caption' => '', 'capEdit' => '', 'lightbox' => false, 'n' => 1, 'class' => '', 'eager' => false, 'path' => null];
    $ratio = (string) $o['ratio'];
    $opt = ($ratio !== '' ? ['ratio' => $ratio] : []) + ($o['eager'] ? ['eager' => true] : []) + ($o['path'] ? ['path' => (string) $o['path']] : [])
        + (isset($o['alt']) ? ['alt' => (string) $o['alt']] : []);
    $pic = \Core\Media::pictureOf($m, (string) $o['sizes'], $opt);
    if ($pic === '') return '';
    $caption = trim((string) $o['caption']);
    $alt = isset($o['alt']) ? (string) $o['alt'] : \Core\Media::alt($m);
    $img = '<span class="ph__img' . ($ratio !== '' ? ' ' . foto_ratio_class($ratio) : ' ph__img--orig') . '">' . $pic . '</span>';
    $lb = $o['lightbox'] && !is_editing();
    if ($lb) {
        $label = $alt !== '' ? '<span class="sr-only"> ' . e(lt('(vergrößern)')) . '</span>' : '<span class="sr-only">' . e(lt('Bild {n} vergrößern', ['n' => (int) $o['n']])) . '</span>';
        $box = '<a class="ph__box"' . \Core\MediaBlocks::lightboxLink($m, strip_emphasis($caption)) . ' data-lb>' . $img . $label . '</a>';
    } else {
        $box = '<span class="ph__box">' . $img . ($o['extra'] ?? '') . '</span>';
    }
    $cap = $caption !== '' || ($o['capEdit'] !== '' && is_editing())
        ? '<figcaption class="ph__cap"' . $o['capEdit'] . '>' . foto_title($caption) . '</figcaption>' : '';
    return '<figure class="ph' . ($o['class'] !== '' ? ' ' . e((string) $o['class']) : '') . ($cap !== '' ? ' ph--cap' : '') . '">' . $box . $cap . '</figure>';
}

/** Platzhalter im Editor, wenn ein Bild fehlt */
function foto_photo_empty(string $ratio = '', string $label = 'Bild'): string
{
    if (!is_editing()) return '';
    return '<figure class="ph ph--empty"><span class="ph__box"><span class="ph__img frame--empty ' . e($ratio !== '' ? foto_ratio_class($ratio) : 'r-3-2') . '"><span>'
        . e($label . ($ratio !== '' ? ' · ' . $ratio : '')) . '</span></span></span></figure>';
}

/**
 * Lightbox-Dialog des Kits (einmal je Seite; js/lightbox.js befüllt ihn). Hintergrund nach Design → „Hintergrund der Lightbox“
 * (Klassen lb-dark | lb-light | lb-blur am <html>). Beschriftungen in der Sprache der Seite.
 */
function foto_lightbox(): string
{
    static $done = false;
    if ($done || is_editing()) return '';
    $done = true;
    return '<dialog class="flb" aria-label="' . e(lt('Bildansicht')) . '" data-count="' . e(lt('Bild {n} von {total}')) . '">'
        . '<figure class="flb__fig"><img class="flb__img" alt="" sizes="100vw"><figcaption class="flb__cap"><span class="flb__text"></span> <span class="flb__count" aria-live="polite"></span></figcaption></figure>'
        . '<button type="button" class="flb__btn flb__close" data-flb-close>' . icon('x') . '<span class="sr-only">' . e(lt('Schließen')) . '</span></button>'
        . '<button type="button" class="flb__btn flb__prev" data-flb-step="-1">' . icon('arrow-right', ['class' => 'flip-x']) . '<span class="sr-only">' . e(lt('Vorheriges Bild')) . '</span></button>'
        . '<button type="button" class="flb__btn flb__next" data-flb-step="1">' . icon('arrow-right') . '<span class="sr-only">' . e(lt('Nächstes Bild')) . '</span></button>'
        . '</dialog>';
}

/**
 * Bilder und Videos eines Foto-Blocks: Einzelauswahl (Liste „images“: Bild oder Video aus der Mediathek, optional
 * YouTube-/Vimeo-Link, Bildunterschrift) oder alle Bilder und Videos einer Sammlung (Bildunterschrift = Titel in der Mediathek).
 * @return list<array{m: ?array, url: string, caption: string, path: ?string, i: int, video: bool}>
 */
function foto_images(array $d, string $list = 'images'): array
{
    $out = [];
    $ok = fn(?array $m) => $m && (str_starts_with((string) $m['mime'], 'image/') || str_starts_with((string) $m['mime'], 'video/'));
    if (($d['source'] ?? 'manual') === 'collection') {
        if (!empty($d['collection'])) {
            foreach (\Core\Media::all(['collection' => (int) $d['collection'], 'kind' => 'visual']) as $m) {
                $out[] = ['m' => $m, 'url' => '', 'caption' => trim(\Core\Media::title($m)), 'path' => null, 'i' => count($out), 'video' => str_starts_with((string) $m['mime'], 'video/')];
            }
        }
        return $out;
    }
    foreach ((array) ($d[$list] ?? []) as $i => $it) {
        if (!is_array($it)) continue;
        $m = !empty($it['image']) ? \Core\Media::find((int) $it['image']) : null;
        $url = trim((string) ($it['video_url'] ?? ''));
        if ($url !== '' && !\Core\Embeds::parse($url)) $url = '';
        if (!$ok($m) && $url === '') continue;
        $out[] = ['m' => $ok($m) ? $m : null, 'url' => $url, 'caption' => trim((string) ($it['caption'] ?? '')), 'path' => "$list.$i", 'i' => (int) $i,
            'video' => $url !== '' || ($m && str_starts_with((string) $m['mime'], 'video/'))];
    }
    return $out;
}

/** Nächstliegendes Bildformat (Klasse r-*) für Breite × Höhe – auch Hochformat-Videos (9:16) */
function foto_nearest_ratio(int $w, int $h, string $fallback = '16:9'): string
{
    if ($w <= 0 || $h <= 0) return $fallback;
    $r = $w / $h;
    $best = $fallback;
    $diff = PHP_FLOAT_MAX;
    foreach (['16:9' => 16 / 9, '3:2' => 1.5, '4:3' => 4 / 3, '1:1' => 1.0, '4:5' => .8, '2:3' => 2 / 3, '9:16' => 9 / 16, '21:9' => 21 / 9] as $k => $x) {
        if (abs($r - $x) < $diff) { $diff = abs($r - $x); $best = $k; }
    }
    return $best;
}

/**
 * Ein Video (MP4 aus der Mediathek) als <figure class="ph ph--video">: Vorschaubild (eigenes Poster der Mediathek bzw.
 * automatisch erzeugt, Media::posterFor) mit Abspiel-Zeichen. Mit Lightbox: Link öffnet das Video im Dialog (js/lightbox.js
 * klont das <template> mit <video controls>, Untertiteln und Transkript – Core\MediaTracks); ohne Lightbox: <video controls>
 * direkt in der Kachel (preload="none"). Ohne JavaScript führt der Link zur Videodatei.
 */
function foto_video(array $m, array $o): string
{
    $o += ['sizes' => '100vw', 'ratio' => '', 'caption' => '', 'capEdit' => '', 'lightbox' => false, 'n' => 1, 'class' => '', 'eager' => false, 'path' => null];
    $poster = \Core\Media::posterFor($m);
    $ratio = (string) $o['ratio'] !== '' ? (string) $o['ratio'] : foto_nearest_ratio((int) ($m['width'] ?? 0), (int) ($m['height'] ?? 0));
    $posterUrl = $poster ? \Core\Media::url($poster, 1600) : '';
    $title = strip_emphasis(trim((string) ($o['caption'] ?? '')) ?: trim(\Core\Media::title($m)));
    $caption = trim((string) $o['caption']);
    $video = fn(string $cls, string $preload) => '<video class="' . $cls . '" controls playsinline preload="' . $preload . '"' . ($posterUrl !== '' ? ' poster="' . e($posterUrl) . '"' : '')
        . ($title !== '' ? ' aria-label="' . e($title) . '"' : '') . '><source src="' . e(\Core\Media::url($m)) . '" type="' . e((string) $m['mime']) . '">'
        . \Core\MediaTracks::trackTags($m) . '</video>';
    $cap = $caption !== '' || ($o['capEdit'] !== '' && is_editing()) ? '<figcaption class="ph__cap"' . $o['capEdit'] . '>' . foto_title($caption) . '</figcaption>' : '';
    $frame = 'ph__img ' . foto_ratio_class($ratio);
    if ($o['lightbox'] && !is_editing()) {
        static $n = 0;
        $tpl = 'flb-v' . (++$n);
        $pic = $poster ? \Core\Media::pictureOf($poster, (string) $o['sizes'], ['ratio' => $ratio, 'alt' => ''] + ($o['eager'] ? ['eager' => true] : [])) : '';
        $box = '<a class="ph__box ph__box--video" href="' . e(\Core\Media::url($m)) . '" data-lb data-lb-video="' . e($tpl) . '"'
            . ' data-w="' . (int) ($m['width'] ?? 0) . '" data-h="' . (int) ($m['height'] ?? 0) . '"' . ($caption !== '' ? ' data-caption="' . e(strip_emphasis($caption)) . '"' : '') . '>'
            . '<span class="' . e($frame) . '">' . $pic . '</span><span class="ph__play" aria-hidden="true"></span>'
            . '<span class="sr-only">' . e($title !== '' ? lt('Video abspielen: {title}', ['title' => $title]) : lt('Video abspielen')) . '</span></a>'
            . '<template id="' . e($tpl) . '">' . $video('flb__video', 'metadata') . \Core\MediaTracks::transcriptHtml($m) . '</template>';
    } else {
        $box = '<span class="ph__box"><span class="' . e($frame) . ' ph__img--video">' . $video('ph__video', 'none') . '</span>' . ($o['extra'] ?? '') . '</span>' . \Core\MediaTracks::transcriptHtml($m);
    }
    return '<figure class="ph ph--video' . ($o['class'] !== '' ? ' ' . e((string) $o['class']) : '') . ($cap !== '' ? ' ph--cap' : '') . '">' . $box . $cap . '</figure>';
}

/** YouTube/Vimeo in einer Kachel: Kern-Fragment „video-embed“ (Zwei-Klick-Lösung, Vorschaubild vom eigenen Server) */
function foto_embed(string $url, array $o): string
{
    $caption = trim((string) ($o['caption'] ?? ''));
    $embed = app()->theme->partial('video-embed', ['url' => $url, 'file' => null, 'poster' => $o['poster'] ?? null, 'ratio' => '16-9', 'label' => strip_emphasis($caption)]);
    $cap = $caption !== '' || (($o['capEdit'] ?? '') !== '' && is_editing()) ? '<figcaption class="ph__cap"' . ($o['capEdit'] ?? '') . '>' . foto_title($caption) . '</figcaption>' : '';
    return '<figure class="ph ph--embed' . (!empty($o['class']) ? ' ' . e((string) $o['class']) : '') . '"><span class="ph__box">' . $embed . '</span>' . $cap . '</figure>';
}

/**
 * Einträge der Serien-Übersicht.
 *  - Quelle „Unterseiten“: veröffentlichte Unterseiten einer Seite (Standard: die aktuelle Seite). Titel, Jahr, Ort, Kategorie
 *    und Titelbild kommen aus dem Block „Serie (Kopf)“ der Unterseite; ohne ihn aus dem Vorschaubild (SEO) bzw. dem ersten
 *    Bild einer Fotostrecke oder Bühne.
 *  - Quelle „Von Hand“: Liste im Block (Bild, Titel, Jahr, Kategorie, Link).
 * @return list<array{title: string, href: string, year: string, category: string, place: string, image: ?int, path: string}>
 */
function foto_series_items(array $d): array
{
    $out = [];
    if (($d['source'] ?? 'children') === 'manual') {
        foreach ((array) ($d['items'] ?? []) as $i => $it) {
            if (!is_array($it) || (trim((string) ($it['title'] ?? '')) === '' && !is_editing())) continue;
            $out[] = ['title' => trim((string) ($it['title'] ?? '')), 'href' => trim((string) ($it['link'] ?? '')) !== '' ? foto_link($it['link']) : '',
                'year' => trim((string) ($it['year'] ?? '')), 'category' => trim((string) ($it['category'] ?? '')), 'place' => '',
                'image' => !empty($it['image']) ? (int) $it['image'] : null, 'path' => "items.$i"];
        }
        return $out;
    }
    $parent = (int) ($d['parent'] ?? 0) ?: (int) (app()->currentPage['id'] ?? 0);
    if (!$parent) return [];
    $drafts = app()->auth->check();
    $rows = app()->db->fetchAll("SELECT * FROM pages WHERE parent_id = ? AND type = 'page'" . ($drafts ? '' : " AND status = 'published'") . ' ORDER BY sort, id', [$parent]);
    foreach ($rows as $p) {
        if (!$drafts && \Core\PageAccess::restricted($p)) continue;
        $meta = foto_series_meta($p);
        $out[] = ['title' => $meta['title'] !== '' ? $meta['title'] : (string) ($p['nav_title'] ?: $p['title']), 'href' => \Core\Pages::url($p),
            'year' => $meta['year'], 'category' => $meta['category'], 'place' => $meta['place'], 'image' => $meta['image'], 'path' => ''];
    }
    $limit = (int) ($d['limit'] ?? 0);
    return $limit > 0 ? array_slice($out, 0, $limit) : $out;
}

/**
 * Angaben einer Serien-Seite aus ihren Blöcken: „Serie (Kopf)“ (Titel, Jahr, Ort, Kategorie, Titelbild), sonst erstes Bild
 * aus Bühne, Fotostrecke, Bild & Text oder Galerie bzw. das Vorschaubild der Seite.
 * @return array{title: string, year: string, category: string, place: string, image: ?int}
 */
function foto_series_meta(array $page): array
{
    $meta = ['title' => '', 'year' => '', 'category' => '', 'place' => '', 'image' => null];
    $first = null;
    $walk = function (array $blocks) use (&$walk, &$meta, &$first): void {
        foreach ($blocks as $bl) {
            $type = (string) ($bl['type'] ?? '');
            $data = (array) ($bl['data'] ?? []);
            if ($type === 'series_head' && $meta['title'] === '') {
                $meta['title'] = trim((string) ($data['title'] ?? ''));
                $meta['year'] = trim((string) ($data['year'] ?? ''));
                $meta['category'] = trim((string) ($data['category'] ?? ''));
                $meta['place'] = trim((string) ($data['place'] ?? ''));
                if (!empty($data['cover'])) $meta['image'] = (int) $data['cover'];
            }
            if ($first === null) {
                foreach (['image', 'cover'] as $k) if (!empty($data[$k]) && in_array($type, ['photo_hero', 'photo_text', 'hero', 'media_text'], true)) { $first = (int) $data[$k]; break; }
                if ($first === null) foreach ((array) ($data['images'] ?? []) as $it) if (is_array($it) && !empty($it['image'])) { $first = (int) $it['image']; break; }
            }
            foreach ((array) ($data['columns'] ?? []) as $col) if (is_array($col['blocks'] ?? null)) $walk($col['blocks']);   // Block „Layout“
        }
    };
    $walk(\Core\Pages::blocks($page));
    $meta['image'] ??= !empty($page['og_image']) ? (int) $page['og_image'] : $first;
    // Titelbild muss ein Bild sein – bei einem Video dessen Vorschaubild
    if ($meta['image'] && ($m = media($meta['image'])) && str_starts_with((string) $m['mime'], 'video/')) {
        $p = \Core\Media::posterFor($m);
        $meta['image'] = $p && !empty($p['id']) ? (int) $p['id'] : null;
    }
    return $meta;
}

/** Zeile „2025 · Musterstadt · Porträt“ aus den befüllten Angaben */
function foto_meta_line(array $parts): string
{
    return implode(' · ', array_map('e', array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''))));
}

// ------------------------------------------------------------------ Bearbeiten-Modus: Fotos per Drag & Drop (js/editor-photos.js)

/**
 * Ablagefläche „Fotos hierher ziehen oder auswählen“ – nur im Bearbeiten-Modus (für Besucher leer). js/editor-photos.js
 * (theme.php → 'editor_js') lädt abgelegte bzw. ausgewählte Dateien über den Upload der Mediathek hoch (Alt-Text-Pflicht,
 * Fortschritt je Datei) und hängt sie an das Feld des Blocks an.
 * @param string $field Feld des Blocks (Liste „images“/„slides“ oder einzelnes Bildfeld „image“/„cover“)
 * @param string $mode  list (Liste mit Bild + Bildunterschrift) | single (ein Bild) | collection (Bilder kommen in die Sammlung)
 * @param array  $o     collection (ID der Sammlung), switch (Variante, auf die bei mehreren Dateien gewechselt wird, + Listenfeld),
 *                      accept ('image' | 'visual' = auch Videos), allowCollection (Option „Alle als Sammlung“)
 */
function foto_drop_zone(string $field, string $mode = 'list', array $o = []): string
{
    if (!is_editing()) return '';
    $attrs = ' data-foto-drop data-field="' . e($field) . '" data-mode="' . e($mode) . '"';
    if (!empty($o['collection'])) $attrs .= ' data-collection="' . (int) $o['collection'] . '"';
    if (!empty($o['switch'])) $attrs .= ' data-switch-variant="' . e((string) $o['switch'][0]) . '" data-switch-field="' . e((string) $o['switch'][1]) . '"';
    if (!empty($o['allowCollection'])) $attrs .= ' data-allow-collection';
    $video = ($o['accept'] ?? 'image') === 'visual';
    $attrs .= ' data-accept="' . ($video ? 'visual' : 'image') . '"';
    $label = $mode === 'single'
        ? ($video ? __('Foto oder Video hierher ziehen oder auswählen') : __('Foto hierher ziehen oder auswählen'))
        : ($video ? __('Fotos und Videos hierher ziehen oder auswählen') : __('Fotos hierher ziehen oder auswählen'));
    $hint = match (true) {
        $mode === 'collection' => __('Neue Dateien kommen in die Sammlung dieser Fotostrecke.'),
        $mode === 'single' => $video ? __('Ersetzt das Bild. Bilder (JPG, PNG, WebP) oder Video (MP4).') : __('Ersetzt das Bild. JPG, PNG oder WebP.'),
        default => $video ? __('Mehrere auf einmal · Bilder (JPG, PNG, WebP) und Videos (MP4) · Reihenfolge per Ziehen oder mit den Pfeilen ändern.')
            : __('Mehrere auf einmal · JPG, PNG oder WebP · Reihenfolge per Ziehen oder mit den Pfeilen ändern.'),
    };
    return '<div class="fdz"' . $attrs . '><button type="button" class="fdz__btn" data-foto-choose>' . icon('upload-simple') . '<span>' . e($label) . '</span></button>'
        . '<span class="fdz__hint">' . e($hint) . '</span><span class="fdz__status" role="status" aria-live="polite"></span></div>';
}

/** Werkzeuge an einem Bild einer Liste im Bearbeiten-Modus: nach links/rechts verschieben, entfernen ($i = Index im Feld) */
function foto_item_tools(string $field, int $i, int $pos, int $count): string
{
    if (!is_editing()) return '';
    $b = fn(string $act, string $ico, string $label, bool $off = false) => '<button type="button" class="fdz-tool" data-foto-act="' . $act . '"' . ($off ? ' disabled' : '') . ' title="' . e($label) . '">'
        . icon($ico) . '<span class="sr-only">' . e($label) . '</span></button>';
    return '<span class="fdz-tools" data-foto-item="' . $i . '" data-field="' . e($field) . '">'
        . $b('prev', 'arrow-left', __('Nach vorn'), $pos === 0)
        . $b('next', 'arrow-right', __('Nach hinten'), $pos === $count - 1)
        . $b('remove', 'trash', __('Aus der Fotostrecke entfernen'))
        . '</span>';
}

// ------------------------------------------------------------------ Bildstrom (Bento, Mixed Media) – blocks/moments.php, js/moments.js

/** Kachelgrößen des Bildstroms: Bento (Spalten × Zeilen im 12er-Raster) und Strom (Spalten) */
const FOTO_MO_SIZES = ['s', 'm', 'wide', 'tall', 'l', 'full'];

/**
 * Kacheln des Bildstroms, vereinheitlicht – aus der Liste „tiles“ (von Hand), den Unterseiten einer Seite oder einer Sammlung.
 * Hinter jeder Kachel kann etwas stecken: das Bild groß (zoom), eine ganze Galerie (Sammlung → Lightbox-Folge) oder eine Seite.
 * @return list<array{m: ?array, size: string, tone: string, place: string, title: string, text: string, open: string,
 *                    href: string, gallery: list<array>, path: ?string, i: int, video: bool}>
 */
function foto_moments(array $d): array
{
    $src = (string) ($d['source'] ?? 'manual');
    $visual = fn(?array $m) => $m && (str_starts_with((string) $m['mime'], 'image/') || str_starts_with((string) $m['mime'], 'video/'));
    $tile = fn(array $t) => $t + ['m' => null, 'size' => 'auto', 'tone' => 'none', 'place' => 'auto', 'title' => '', 'text' => '', 'open' => 'none',
        'href' => '', 'gallery' => [], 'path' => null, 'i' => 0];
    $out = [];
    if ($src === 'collection') {
        foreach (!empty($d['collection']) ? \Core\Media::all(['collection' => (int) $d['collection'], 'kind' => 'visual']) : [] as $m) {
            $out[] = $tile(['m' => $m, 'title' => '', 'text' => trim(\Core\Media::title($m)), 'open' => 'zoom', 'i' => count($out)]);
        }
    } elseif ($src === 'pages') {
        $parent = preg_match('~^page:(\d+)~', (string) ($d['parent'] ?? ''), $p) ? (int) $p[1] : (int) (app()->currentPage['id'] ?? 0);
        $drafts = app()->auth->check();
        $rows = $parent ? app()->db->fetchAll("SELECT * FROM pages WHERE parent_id = ? AND type = 'page'" . ($drafts ? '' : " AND status = 'published'") . ' ORDER BY sort, id', [$parent]) : [];
        foreach ($rows as $row) {
            if (!$drafts && \Core\PageAccess::restricted($row)) continue;
            $meta = foto_series_meta($row);
            $out[] = $tile(['m' => $meta['image'] ? media((int) $meta['image']) : null, 'title' => $meta['title'] !== '' ? $meta['title'] : (string) ($row['nav_title'] ?: $row['title']),
                'text' => foto_meta_line([$meta['year'], $meta['category']]), 'open' => 'page', 'href' => \Core\Pages::url($row), 'i' => count($out)]);
        }
    } else {
        foreach ((array) ($d['tiles'] ?? []) as $i => $it) {
            if (!is_array($it)) continue;
            $m = !empty($it['image']) ? \Core\Media::find((int) $it['image']) : null;
            $m = $visual($m) ? $m : null;
            $open = in_array($it['open'] ?? '', ['none', 'zoom', 'gallery', 'page'], true) ? (string) $it['open'] : 'zoom';
            $title = trim((string) ($it['title'] ?? ''));
            $text = trim((string) ($it['text'] ?? $it['caption'] ?? ''));
            $href = '';
            $gallery = [];
            if ($open === 'page') {
                $link = trim((string) ($it['link'] ?? ''));
                $href = $link !== '' ? foto_link($link) : '';
                // Seite ohne eigenes Bild: Titelbild und Titel der Zielseite (wie in der Serien-Übersicht)
                // Kachel ohne Bild und ohne Titel: Titelbild und Titel der Zielseite; mit Titel bleibt sie eine Textkachel
                if ($title === '' && preg_match('~^page:(\d+)$~', $link, $pm) && ($pg = app()->db->fetch('SELECT * FROM pages WHERE id = ?', [(int) $pm[1]]))) {
                    $meta = foto_series_meta($pg);
                    $m ??= $meta['image'] ? media((int) $meta['image']) : null;
                    $title = $meta['title'] !== '' ? $meta['title'] : (string) ($pg['nav_title'] ?: $pg['title']);
                }
                if ($href === '' || $href === '#') $open = 'none';
            } elseif ($open === 'gallery') {
                foreach (!empty($it['collection']) ? \Core\Media::all(['collection' => (int) $it['collection'], 'kind' => 'visual']) : [] as $g) {
                    if (!$m || (int) $g['id'] !== (int) $m['id']) $gallery[] = $g;
                }
                $m ??= array_shift($gallery);
                if (!$gallery) $open = $m ? 'zoom' : 'none';
            }
            if ($open === 'zoom' && !$m) $open = 'none';
            if (!$m && $title === '' && $text === '' && !is_editing()) continue;
            $out[] = $tile([
                'm' => $m, 'size' => in_array($it['size'] ?? '', FOTO_MO_SIZES, true) ? (string) $it['size'] : 'auto',
                'tone' => in_array($it['tone'] ?? '', ['muted', 'accent', 'secondary', 'dark'], true) ? (string) $it['tone'] : 'none',
                'place' => in_array($it['place'] ?? '', ['start', 'center', 'end'], true) ? (string) $it['place'] : 'auto',
                'title' => $title, 'text' => $text, 'open' => $open, 'href' => $href, 'gallery' => $gallery, 'path' => "tiles.$i", 'i' => (int) $i,
            ]);
        }
    }
    // „Automatisch“: Größe aus dem Seitenverhältnis – mit etwas Abwechslung, damit ein Bento entsteht und keine Liste
    $n = 0;
    foreach ($out as &$t) {
        $t['video'] = $t['m'] && str_starts_with((string) $t['m']['mime'], 'video/');
        if ($t['size'] !== 'auto') continue;
        if (!$t['m']) { $t['size'] = 's'; continue; }
        $w = (int) ($t['m']['width'] ?? 0); $h = (int) ($t['m']['height'] ?? 0);
        $r = $w > 0 && $h > 0 ? $w / $h : 1.5;
        $n++;
        $t['size'] = match (true) {
            $r < .9 => $n % 5 === 0 ? 'tall' : 'm',
            $r > 1.9 => 'wide',
            $n % 7 === 1 => 'l',
            $n % 3 === 0 => 's',
            default => $r > 1.25 ? 'wide' : 'm',
        };
    }
    return $out;
}

/**
 * Lightbox-Link einer Datei (Bild oder Video) – für die Kachel selbst ($inner = Bild) oder versteckt als weiteres Bild der Galerie.
 * Videos: <template> mit <video controls> + Untertitel/Transkript (wie foto_video); lightbox.js klont es.
 */
function foto_lb_link(array $m, string $caption, string $class, string $inner, bool $hidden = false): string
{
    $alt = \Core\Media::alt($m);
    $attrs = ' class="' . e($class) . '" data-lb' . ($hidden ? ' hidden tabindex="-1"' : '') . ($alt !== '' ? ' data-alt="' . e($alt) . '"' : '');
    if (str_starts_with((string) $m['mime'], 'video/')) {
        static $n = 0;
        $tpl = 'mo-v' . (++$n);
        $poster = \Core\Media::posterFor($m);
        $title = strip_emphasis($caption !== '' ? $caption : trim(\Core\Media::title($m)));
        $video = '<video class="flb__video" controls playsinline preload="metadata"' . ($poster ? ' poster="' . e(\Core\Media::url($poster, 1600)) . '"' : '')
            . ($title !== '' ? ' aria-label="' . e($title) . '"' : '') . '><source src="' . e(\Core\Media::url($m)) . '" type="' . e((string) $m['mime']) . '">'
            . \Core\MediaTracks::trackTags($m) . '</video>' . \Core\MediaTracks::transcriptHtml($m);
        return '<a href="' . e(\Core\Media::url($m)) . '"' . $attrs . ' data-lb-video="' . e($tpl) . '" data-w="' . (int) ($m['width'] ?? 0) . '" data-h="' . (int) ($m['height'] ?? 0) . '"'
            . ($caption !== '' ? ' data-caption="' . e(strip_emphasis($caption)) . '"' : '') . '>' . $inner . '</a><template id="' . e($tpl) . '">' . $video . '</template>';
    }
    return '<a' . \Core\MediaBlocks::lightboxLink($m, strip_emphasis($caption)) . $attrs . '>' . $inner . '</a>';
}

/**
 * Bild bzw. stummes Video einer Kachel. Videos laufen als Schleife, sobald sie sichtbar sind (js/moments.js; nie bei
 * „Bewegung reduzieren“ – dann bleibt das Vorschaubild stehen), ohne Ton; mit Pause-Schaltfläche neben dem Link.
 */
function foto_mo_media(array $m, string $sizes, string $ratio, bool $eager): string
{
    if (str_starts_with((string) $m['mime'], 'video/')) {
        $poster = \Core\Media::posterFor($m);
        $title = trim(\Core\Media::title($m));
        return '<video class="mo__vid" muted loop playsinline preload="none" data-mo-loop' . ($poster ? ' poster="' . e(\Core\Media::url($poster, 1600)) . '"' : '')
            . ($title !== '' ? ' aria-label="' . e($title) . '"' : ' aria-hidden="true"') . '><source src="' . e(\Core\Media::url($m)) . '" type="' . e((string) $m['mime']) . '"></video>';
    }
    return \Core\Media::pictureOf($m, $sizes, ($ratio !== '' ? ['ratio' => $ratio] : []) + ($eager ? ['eager' => true] : []));
}
