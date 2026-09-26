<?php
/*
 * Theme-Helfer „basis“. Werden von Templates und Block-Renderern genutzt.
 * Präfix basis_ – theme:create benennt ihn für abgeleitete Themes automatisch um.
 * Alle sichtbaren festen Texte laufen über lt() (Übersetzungen: lang/site/{sprache}.php).
 */

use Core\Pages;

/**
 * Klassen für <html>: Design-Werte (nav-modern, btn-pill, cards-elevated, has-dark …) + no-js.
 * CSP: keine Inline-Styles – alle Gestaltung über Variablen der Design-Datei und diese Klassen.
 */
function basis_html_class(): string
{
    basis_design_migrate();
    return trim('no-js ' . design_classes());
}

/**
 * Einmalige Übernahme der früheren Einstellungen „Akzentfarbe“ und „Dunkles Farbschema“ (Website → Darstellung)
 * in die Design-Werte, damit bestehende Websites nach dem Update gleich aussehen. Neue Websites: nichts zu tun.
 */
function basis_design_migrate(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    $key = 'design.' . app()->theme->name;
    $accent = app()->settings->get('accent');
    if (app()->settings->get($key) !== null || $accent === null) return;
    // Frühere Akzentfarben: [hell: Akzent, kräftig, Schrift] [dunkel: Akzent, kräftig, Schrift]
    $legacy = [
        'petrol' => ['#0F6E68', '#0A5752', '#FFFFFF', '#5DCABE', '#8ADBD2', '#031F1C'],
        'blau' => ['#1D4ED8', '#1E3FAE', '#FFFFFF', '#8DB1FF', '#B3CBFF', '#08142E'],
        'gruen' => ['#137138', '#0E5A2C', '#FFFFFF', '#6FD39B', '#99E2B9', '#05200F'],
        'bordeaux' => ['#9F1239', '#80102F', '#FFFFFF', '#FF94AE', '#FFB8C9', '#2A0610'],
        'graphit' => ['#374151', '#1F2937', '#FFFFFF', '#C3CBD5', '#E1E6EC', '#101419'],
    ][(string) $accent] ?? null;
    $v = [];
    if ($legacy) {
        [$v['accent'], $v['accent_strong'], $v['on_accent'], $v['accent@dark'], $v['accent_strong@dark'], $v['on_accent@dark']] = $legacy;
    }
    $v['dark'] = (bool) app()->settings->get('dark_mode', true);
    app()->settings->set($key, \Core\Design::normalize($v));
}

/** <link rel="preload"> für die gewählte Fließtext-Schrift (400 + 600, latin) – muss exakt der URL in css/font-*.css entsprechen */
function basis_font_preloads(): string
{
    $pkg = ['inter' => 'inter', 'manrope' => 'manrope', 'plex' => 'ibm-plex-sans', 'source-serif' => 'source-serif-4', 'lora' => 'lora', 'fraunces' => 'fraunces'];
    $h = '';
    foreach (array_unique([(string) design('font_body'), (string) design('font_head')]) as $i => $font) {
        if (!isset($pkg[$font])) continue;
        foreach ($i === 0 ? [400, 600] : [(int) design('heading_weight') ?: 600] as $w) {
            $h .= '<link rel="preload" href="' . e(app()->theme->fontUrl("fonts/{$pkg[$font]}-latin-$w-normal.woff2")) . '" as="font" type="font/woff2" crossorigin>' . "\n";
        }
    }
    return $h;
}

/** Name der Website (Kurzname für die Wortmarke) */
function basis_name(bool $short = false): string
{
    $name = trim((string) setting('org_name'));
    $shortName = trim((string) setting('short_name'));
    if ($short && $shortName !== '') {
        return $shortName;
    }
    return $name !== '' ? $name : ($shortName !== '' ? $shortName : site_name());
}

// ------------------------------------------------------------------ Kontakt

/** Telefonnummer für die Anzeige – in weiteren Sprachen automatisch international (+49 …) */
function basis_phone(): string
{
    return phone_display(trim((string) setting('phone')));
}

function basis_phone_href(): ?string
{
    return tel_href(basis_phone());
}

function basis_email(): string
{
    $m = trim((string) setting('email'));
    return filled($m) ? $m : '';
}

/** Adresse als Zeilen: [Straße, PLZ Ort, Land] – nur befüllte Teile */
function basis_address_lines(): array
{
    $street = trim((string) setting('street'));
    $city = trim(trim((string) setting('zip')) . ' ' . trim((string) setting('city')));
    $country = trim((string) setting('country'));
    return array_values(array_filter([$street, $city, $country], 'filled'));
}

/** Link mit Sonderwerten „phone“ und „email“ (Website) */
function basis_link(?string $link): string
{
    return match (trim((string) $link)) {
        'phone' => basis_phone_href() ?? link_href('#kontakt'),
        'email' => basis_email() !== '' ? 'mailto:' . basis_email() : link_href('#kontakt'),
        default => link_href($link),
    };
}

function basis_link_attrs(?string $link): string
{
    $href = basis_link($link);
    return 'href="' . e($href) . '"' . ext_attrs($href);
}

/**
 * Button-Paar aus den Feldern button_label/button_link und button2_label/button2_link.
 * Nur Buttons mit Beschriftung und Link erscheinen.
 */
function basis_buttons(\Core\Block $b, string $class = ''): string
{
    $d = $b->data;
    $h = '';
    foreach (['button' => 'btn--primary', 'button2' => 'btn--secondary'] as $k => $style) {
        $label = trim((string) ($d[$k . '_label'] ?? ''));
        $link = trim((string) ($d[$k . '_link'] ?? ''));
        if ($label === '' || $link === '') continue;
        $href = basis_link($link);
        $h .= '<a class="btn ' . $style . '" ' . basis_link_attrs($link) . '><span' . $b->edit($k . '_label') . '>' . e($label) . '</span>'
            . (is_external($href) ? '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '') . '</a>';
    }
    return $h !== '' ? '<div class="btn-row' . ($class !== '' ? ' ' . e($class) : '') . '">' . $h . '</div>' : '';
}

// ------------------------------------------------------------------ Öffnungszeiten

/** Uhrzeit „07:30“ → „7:30“ */
function basis_clock(?string $t): string
{
    return ltrim((string) $t, '0') ?: '0:00';
}

/** Zeitfenster eines Eintrags, z. B. „9:00–12:30, 13:30–17:00 Uhr“ (für API und Anzeige) */
function basis_time_range(array $r): string
{
    $seg = [];
    if (!empty($r['von']) && !empty($r['bis'])) {
        if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
            $seg[] = basis_clock($r['von']) . '–' . basis_clock($r['pause_von']);
            $seg[] = basis_clock($r['pause_bis']) . '–' . basis_clock($r['bis']);
        } else {
            $seg[] = basis_clock($r['von']) . '–' . basis_clock($r['bis']);
        }
    }
    $time = $seg ? lt('{zeit} Uhr', ['zeit' => implode(', ', $seg)]) : '';
    return implode(' · ', array_filter([$time, trim((string) ($r['notiz'] ?? ''))]));
}

/** Wochentag (0 = Sonntag … 6 = Samstag) in der Sprache der Seite */
function basis_weekday(int $dow): string
{
    return date_local(strtotime('2024-01-0' . ($dow === 0 ? 7 : $dow)), 'weekday');   // 01.01.2024 war ein Montag
}

/**
 * Öffnungszeiten, Mo–So sortiert; aufeinanderfolgende Tage mit gleichen Zeiten zusammengefasst.
 * @return list<array{days: string, time: string}>
 */
function basis_hours(): array
{
    $byDay = [];
    foreach ((array) setting('hours', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if ($dow < 0 || $dow > 6) continue;
        $t = basis_time_range($r);
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
        $days = basis_weekday($g['from']) . ($g['to'] !== $g['from'] ? '–' . basis_weekday($g['to']) : '');
        $out[] = ['days' => $days, 'time' => $g['time']];
    }
    return $out;
}

// ------------------------------------------------------------------ Navigation, Recht, Social

/** Links Impressum / Datenschutz / Barrierefreiheit (in der Sprache der Seite, falls übersetzt) */
function basis_legal_links(): array
{
    $out = [];
    foreach (['imprint_page' => lt('Impressum'), 'privacy_page' => lt('Datenschutz'), 'accessibility_page' => lt('Barrierefreiheit')] as $k => $label) {
        $id = (int) setting($k);
        $p = $id ? Pages::find($id) : null;
        if ($p && \Core\Lang::multi()) {
            $p = Pages::translations($p)[\Core\Lang::current()] ?? $p;
        }
        if ($p && ($p['status'] === 'published' || app()->auth->check())) {
            $out[] = ['label' => $label, 'href' => Pages::url($p)];
        }
    }
    return $out;
}

/** Social-Media-Profile mit gültiger Adresse */
function basis_social(): array
{
    return array_values(array_filter((array) setting('social', []), fn($s) => trim((string) ($s['label'] ?? '')) !== '' && is_external($s['url'] ?? '')));
}

/** Hauptmenü: Seiten mit „Im Menü“ (Unterseiten als Aufklappmenü) + Anker der Startseite mit „In Navigation“ */
function basis_menu(): array
{
    $items = Pages::menu(app()->auth->check());
    foreach (app()->theme->navigation() as $n) {
        $items[] = ['id' => 'a-' . $n['id'], 'label' => $n['label'], 'href' => $n['href'], 'active' => false, 'children' => []];
    }
    return $items;
}

/** Handlungsaufruf des Kopfbereichs [label, link] oder null (Stil „Kein“) – für Menü-Blatt und Fußbereich; im Kopf: header_actions() */
function basis_header_cta(): ?array
{
    // Design → „Kopfbereich: Suche & Aktionen“ (Core\HeaderActions): Vorgabe (Kontakt, Termin …) oder Website → Darstellung
    $a = header_cta();
    return $a ? ['label' => $a['label'], 'link' => $a['link']] : null;
}

/** Seitenangaben für das Mega-Menü: Beschreibung (meta_description) und Vorschaubild (og_image) */
function basis_page_info(mixed $id): array
{
    static $cache = [];
    if (!is_int($id)) return ['desc' => '', 'image' => null];
    if (!isset($cache[$id])) {
        $p = Pages::find($id);
        $desc = trim((string) ($p['meta_description'] ?? ''));
        if (mb_strlen($desc) > 96) $desc = rtrim(mb_substr($desc, 0, 94), " ,.;:–-") . ' …';
        $cache[$id] = ['desc' => $desc, 'image' => (int) ($p['og_image'] ?? 0) ?: null];
    }
    return $cache[$id];
}

/**
 * Hauptmenü als Liste. $mode: 'dropdown' (Aufklappmenü), 'mega' (Mega-Menü mit Beschreibungen), 'overlay' (Vollbild, alles offen).
 * Aufklappen über <details>/<summary> – funktioniert ohne JavaScript; site.js ergänzt Pfeiltasten, Escape und Hover.
 */
function basis_nav_list(array $menu, string $mode = 'dropdown'): string
{
    $currentId = (int) (app()->currentPage['id'] ?? 0);
    $cur = fn(array $m) => is_int($m['id']) && $m['id'] === $currentId ? ' aria-current="page"' : '';
    $chev = '<svg class="nav__chev" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="m3 4.5 3 3 3-3"/></svg>';
    $nested = function (array $items, string $cls) use (&$nested, $cur): string {
        if (!$items) return '';
        $h = '<ul class="' . $cls . '">';
        foreach ($items as $c) {
            $h .= '<li><a class="subnav__link" href="' . e($c['href']) . '"' . $cur($c) . '>' . e($c['label']) . '</a>' . $nested($c['children'], 'subnav subnav--nested') . '</li>';
        }
        return $h . '</ul>';
    };
    $h = '<ul class="nav nav--' . e($mode) . '">';
    foreach ($menu as $m) {
        $active = $m['active'] ? ' data-active' : '';
        if (!$m['children'] || $mode === 'overlay') {
            // Vollbild-Menü: Unterseiten unter dem Link; mobil als Akkordeon (Schaltfläche mit Pfeil, site.js), sonst immer sichtbar
            $sub = $mode === 'overlay' ? $nested($m['children'], 'subnav') : '';
            $tog = '';
            if ($sub !== '') {
                $sid = 'subnav-' . substr(md5((string) $m['href']), 0, 8);
                $sub = preg_replace('~^<ul class="subnav"~', '<ul class="subnav" id="' . $sid . '"', $sub);
                $tog = '<button type="button" class="nav__toggle" aria-expanded="' . ($m['active'] ? 'true' : 'false') . '" aria-controls="' . $sid . '">'
                    . '<span class="sr-only">' . e(lt('Unterseiten von {name}', ['name' => $m['label']])) . '</span>' . $chev . '</button>';
            }
            $h .= '<li class="nav__item' . ($tog !== '' ? ' has-toggle' : '') . '"><a class="nav__link" href="' . e($m['href']) . '"' . $cur($m) . $active . '>' . e($m['label']) . '</a>'
                . $tog . $sub . '</li>';
            continue;
        }
        $h .= '<li class="nav__item has-sub"><details class="nav__sub' . ($mode === 'mega' ? ' nav__sub--mega' : '') . '"' . $active . '>'
            . '<summary class="nav__link"><span>' . e($m['label']) . '</span>' . $chev . '</summary>';
        if ($mode !== 'mega') {
            $h .= '<ul class="subnav"><li><a class="subnav__link" href="' . e($m['href']) . '"' . $cur($m) . '>' . e(lt('Übersicht')) . '</a></li>';
            foreach ($m['children'] as $c) {
                $h .= '<li><a class="subnav__link" href="' . e($c['href']) . '"' . $cur($c) . '>' . e($c['label']) . '</a>' . $nested($c['children'], 'subnav subnav--nested') . '</li>';
            }
            $h .= '</ul></details></li>';
            continue;
        }
        // Mega-Menü: Einleitung · Unterseiten mit Beschreibung · Bild der ersten Unterseite mit Vorschaubild · Direktkontakt
        $info = basis_page_info($m['id']);
        $h .= '<div class="mega"><div class="wrap mega__inner"><div class="mega__intro"><p class="mega__title">' . e($m['label']) . '</p>'
            . ($info['desc'] !== '' ? '<p class="mega__text">' . e($info['desc']) . '</p>' : '')
            . '<a class="more" href="' . e($m['href']) . '"' . $cur($m) . '>' . e(lt('Übersicht')) . basis_icon('arrow', 'more__icon') . '</a></div><ul class="mega__list">';
        $feature = null;
        foreach ($m['children'] as $c) {
            $ci = basis_page_info($c['id']);
            if (!$feature && $ci['image']) $feature = $c + $ci;
            $h .= '<li><a class="mega__link" href="' . e($c['href']) . '"' . $cur($c) . '><span class="mega__name">' . e($c['label']) . '</span>'
                . ($ci['desc'] !== '' ? '<span class="mega__desc">' . e($ci['desc']) . '</span>' : '') . '</a>'
                . $nested($c['children'], 'mega__sub') . '</li>';
        }
        $h .= '</ul>';
        if ($feature && ($pic = img($feature['image'], '320px', ['ratio' => '3:2', 'alt' => ''])) !== '') {
            $h .= '<a class="mega__feature" href="' . e($feature['href']) . '"><span class="mega__img">' . $pic . '</span>'
                . '<span class="mega__name">' . e($feature['label']) . '</span>' . ($feature['desc'] !== '' ? '<span class="mega__desc">' . e($feature['desc']) . '</span>' : '') . '</a>';
        } else {
            $h .= basis_quick_contact('mega__contact');
        }
        $h .= '</div></div></details></li>';
    }
    return $h . '</ul>';
}

/** Direktkontakt (Telefon, E-Mail, Button) – im Mega-Menü und im Vollbild-Menü */
function basis_quick_contact(string $class): string
{
    $phone = basis_phone();
    $tel = basis_phone_href();
    $email = basis_email();
    $cta = basis_header_cta();
    if (!(filled($phone) && $tel) && $email === '' && !$cta) return '';
    $h = '<div class="' . e($class) . '"><p class="quick__title">' . e(lt('Direkter Kontakt')) . '</p><ul class="quick__list">';
    if (filled($phone) && $tel) $h .= '<li><a href="' . e($tel) . '">' . basis_icon('phone') . '<span>' . e($phone) . '</span></a></li>';
    if ($email !== '') $h .= '<li><a href="mailto:' . e($email) . '">' . basis_icon('mail') . '<span>' . e($email) . '</span></a></li>';
    $h .= '</ul>';
    if ($cta) $h .= '<a class="btn btn--primary btn--small" ' . basis_link_attrs($cta['link']) . '>' . e($cta['label']) . '</a>';
    return $h . '</div>';
}

/**
 * Transparente Kopfleiste über dem ersten Abschnitt („Modern“ + Option): '' (nein), 'light' oder 'dark' (heller Text).
 * Serverseitig ermittelt – keine Inline-Styles, kein Flackern.
 */
function basis_over_hero(?array $page): string
{
    if (design('nav') !== 'modern' || !design('nav_transparent') || is_editing() || empty($page['id'])) return '';
    foreach (Pages::blocks($page, app()->auth->check() && !isset(app()->request->query['live'])) as $b) {
        $t = $b['tunes']['section'] ?? [];
        if (isset($t['visible']) && !$t['visible']) continue;
        $bg = (string) ($t['background'] ?? (app()->theme->block((string) ($b['type'] ?? ''))['background'] ?? 'white'));
        $dark = in_array($bg, (array) (app()->theme->def['dark_backgrounds'] ?? []), true) || (!empty($t['bgImage']) && ($t['overlay'] ?? '') === 'dark');
        return $dark ? 'dark' : 'light';
    }
    return '';
}

/** Öffnungszeiten als Zeitfenster [Wochentag 0–6, von, bis] für die Anzeige „jetzt geöffnet“ (berechnet im Browser – Seiten-Cache bleibt gültig) */
function basis_hours_ranges(): array
{
    $out = [];
    foreach ((array) setting('hours', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if ($dow < 0 || $dow > 6 || empty($r['von']) || empty($r['bis'])) continue;
        if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
            $out[] = [$dow, $r['von'], $r['pause_von']];
            $out[] = [$dow, $r['pause_bis'], $r['bis']];
        } else {
            $out[] = [$dow, $r['von'], $r['bis']];
        }
    }
    return $out;
}

/** Adresse der Datenschutzerklärung (für Hinweise bei eingebetteten Videos) */
function basis_privacy_url(): string
{
    foreach (basis_legal_links() as $l) {
        if ($l['label'] === lt('Datenschutz')) return $l['href'];
    }
    return url('/');
}

// ------------------------------------------------------------------ Ausgabe-Bausteine

/**
 * Kopf eines Abschnitts: Dachzeile, Überschrift (H2 mit ID für aria-labelledby), Einleitung.
 * Leere optionale Teile erscheinen nicht; im Editor ist die Überschrift immer direkt bearbeitbar.
 */
function basis_head(\Core\Block $b, string $class = '', string $tag = 'h2'): string
{
    $d = $b->data;
    $eyebrow = trim((string) ($d['eyebrow'] ?? ''));
    $title = trim((string) ($d['title'] ?? ''));
    $intro = trim((string) ($d['intro'] ?? ''));
    if ($eyebrow === '' && $title === '' && $intro === '' && !is_editing()) {
        return '';
    }
    $h = '<header class="sec-head' . ($class !== '' ? ' ' . e($class) : '') . '">';
    if ($eyebrow !== '') $h .= '<p class="eyebrow"' . $b->edit('eyebrow') . '>' . e($eyebrow) . '</p>';
    if ($title !== '' || is_editing()) {
        $h .= '<' . $tag . ' id="' . e($b->titleId()) . '" class="h2"' . $b->edit('title') . '>' . e($title) . '</' . $tag . '>';
    }
    if ($intro !== '') $h .= '<p class="lead"' . $b->edit('intro') . '>' . nl2br(e($intro), false) . '</p>';
    return $h . '</header>';
}

/**
 * Bild im festen Format. Ohne Bild: für Besucher nichts, im Editor ein dezenter Platzhalter.
 * @param string $ratio z. B. '4:3'
 */
function basis_image(?int $id, string $sizes, string $ratio, string $class = '', array $opt = []): string
{
    $pic = img($id, $sizes, ['ratio' => $ratio] + $opt);
    $cls = 'media r-' . str_replace(':', '-', $ratio) . ($class !== '' ? ' ' . $class : '');
    if ($pic !== '') {
        return '<div class="' . e($cls) . '">' . $pic . '</div>';
    }
    return is_editing()
        ? '<div class="' . e($cls) . ' media--empty"><span>' . e('Bild · ' . $ratio) . '</span></div>'
        : '';
}

/** Symbole (24 × 24, Linie) – Auswahl im Block „Merkmale“ */
function basis_icons(): array
{
    return [
        'check' => ['Häkchen', '<path d="M20 6 9 17l-5-5"/>'],
        'star' => ['Stern', '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>'],
        'shield' => ['Schild / Sicherheit', '<path d="M12 3 4 6v6c0 4.6 3.4 8 8 9 4.6-1 8-4.4 8-9V6z"/><path d="m9 12 2 2 4-4"/>'],
        'clock' => ['Uhr / Zeit', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'],
        'chat' => ['Sprechblase / Beratung', '<path d="M20 12a8 8 0 0 1-11.7 7.1L4 20l1-4A8 8 0 1 1 20 12z"/>'],
        'chart' => ['Diagramm / Wachstum', '<path d="M4 20V10m6 10V4m6 16v-7m4 7H3"/>'],
        'leaf' => ['Blatt / Nachhaltigkeit', '<path d="M5 20c0-8.5 5-14 15-15-.5 9.5-6.5 15-15 15z"/><path d="m5 20 7-7"/>'],
        'bolt' => ['Blitz / Schnell', '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>'],
        'users' => ['Personen / Team', '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.6a3.5 3.5 0 0 1 0 6.8M21.5 20a6.5 6.5 0 0 0-3.5-5.8"/>'],
        'globe' => ['Globus / International', '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18"/>'],
        'heart' => ['Herz', '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.2a4.3 4.3 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z"/>'],
        'bulb' => ['Glühbirne / Idee', '<path d="M9 18h6m-5 3h4M12 3a6 6 0 0 0-3.6 10.8c.7.6 1.1 1.3 1.1 2.2h5c0-.9.4-1.6 1.1-2.2A6 6 0 0 0 12 3z"/>'],
        'target' => ['Ziel', '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>'],
        'tool' => ['Werkzeug / Handwerk', '<path d="M14.5 6.5a4 4 0 0 0 5 5L21 13l-8 8-3-3 1.5-1.5-5-5L5 13l-2-2 6-6 2 2-1.5 1.5 5 5"/>'],
        'pin' => ['Ort', '<path d="M12 21s-7-5.8-7-11a7 7 0 0 1 14 0c0 5.2-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>'],
        'mail' => ['Brief / E-Mail', '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'],
        'phone' => ['Telefon', '<path d="M21 16.5v3a2 2 0 0 1-2.2 2A18 18 0 0 1 2.5 5.2 2 2 0 0 1 4.5 3h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.4 10.9a15 15 0 0 0 4.7 4.7l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>'],
        'calendar' => ['Kalender / Termin', '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4m8-4v4M3 10h18"/>'],
        'box' => ['Paket / Produkt', '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="m3 7.5 9 4.5 9-4.5M12 12v9"/>'],
        'layers' => ['Ebenen / Lösungen', '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>'],
        'home' => ['Haus', '<path d="M3 11 12 3l9 8"/><path d="M5 9.5V21h14V9.5M10 21v-6h4v6"/>'],
        'spark' => ['Funke / Qualität', '<path d="M12 3v4m0 10v4M3 12h4m10 0h4M5.6 5.6l2.8 2.8m7.2 7.2 2.8 2.8m0-12.8-2.8 2.8m-7.2 7.2-2.8 2.8"/>'],
    ];
}

function basis_icon_options(): array
{
    return array_map(fn($i) => $i[0], basis_icons());
}

/** Symbol als Inline-SVG (dekorativ). Zusätzlich zur Auswahl: Pfeil, Anführungszeichen, Download. */
function basis_icon(string $name, string $class = 'icon'): string
{
    $ui = [
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'quote' => '<path d="M10 6.5C6.8 7.4 5 9.8 5 13v4.5h4.5V13H6.2M19 6.5c-3.2.9-5 3.3-5 6.5v4.5h4.5V13h-3.3"/>',
        'download' => '<path d="M12 4v11m-5-5 5 5 5-5M5 20h14"/>',
    ];
    $svg = $ui[$name] ?? (basis_icons()[$name][1] ?? null);
    return $svg ? '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $svg . '</svg>' : '';
}

// ------------------------------------------------------------------ SEO, App, API

/** Schema.org Organization / LocalBusiness – nur befüllte Felder, nur auf der Startseite */
function basis_jsonld(array $page): ?array
{
    if (empty($page['is_home'])) {
        return null;
    }
    $type = (string) setting('schema_type', 'Organization');
    $d = ['@context' => 'https://schema.org', '@type' => in_array($type, ['Organization', 'LocalBusiness', 'ProfessionalService', 'NGO'], true) ? $type : 'Organization'];
    if (filled(setting('org_name'))) $d['name'] = (string) setting('org_name');
    if (filled(setting('tagline'))) $d['slogan'] = (string) setting('tagline');
    $d['url'] = absolute_url('/');
    if ($tel = basis_phone_href()) $d['telephone'] = substr($tel, 4);
    if (basis_email() !== '') $d['email'] = basis_email();
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
    if ($d['@type'] !== 'Organization' && $d['@type'] !== 'NGO') {
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
    if ($social = basis_social()) $d['sameAs'] = array_column($social, 'url');
    return isset($d['name']) ? $d : null;
}

/** Name, Kurzname und Kurzbefehle für die installierbare Web-App (Manifest) */
function basis_app_info(): array
{
    $name = basis_name();
    $short = basis_name(true);
    return [
        'name' => $name,
        'short_name' => mb_strlen($short) <= 14 ? $short : mb_substr($short, 0, 12) . '…',
        'description' => (string) (setting('tagline') ?: setting('default_meta_description')),
        'shortcuts' => [['name' => lt('Kontakt'), 'short_name' => lt('Kontakt'), 'url' => url('/') . '#kontakt']],
    ];
}

/** Öffentliche Basisdaten (GET /api/v1/public, ohne Token) */
function basis_public_info(): array
{
    return [
        'name' => basis_name(),
        'tagline' => filled(setting('tagline')) ? setting('tagline') : null,
        'address' => array_filter([
            'street' => filled(setting('street')) ? setting('street') : null,
            'zip' => filled(setting('zip')) ? setting('zip') : null,
            'city' => filled(setting('city')) ? setting('city') : null,
            'country' => filled(setting('country')) ? setting('country') : null,
        ]),
        'phone' => ($t = basis_phone_href()) ? substr($t, 4) : null,
        'phone_display' => filled(basis_phone()) ? basis_phone() : null,
        'email' => basis_email() ?: null,
        'social' => basis_social(),
    ];
}
