<?php
/*
 * Theme-Helfer „editorial“. Werden von Templates und Block-Renderern genutzt.
 * Präfix editorial_ – theme:create benennt ihn für abgeleitete Themes automatisch um.
 * Alle sichtbaren festen Texte laufen über lt() (Übersetzungen: lang/site/{sprache}.php).
 */

use Core\Pages;

/**
 * Klassen für <html>: Design-Werte (head-masthead, grid-asym, img-duotone, kick-mono, rules-double, has-dark …) + no-js.
 * CSP: keine Inline-Styles – alle Gestaltung über Variablen der Design-Datei und diese Klassen.
 */
function editorial_html_class(): string
{
    return trim('no-js ' . design_classes());
}

/** Kopfbereich-Variante (Design → Kopf & Navigation) */
function editorial_header(): string
{
    $v = preg_replace('~[^a-z]~', '', (string) design('header'));
    return in_array($v, ['masthead', 'compact', 'split', 'ressorts'], true) ? $v : 'masthead';
}

/** <link rel="preload"> für die Hauptschnitte der gewählten Schriften (Kit-Schriften vom Schriften-Manager, Core\Design::preloads) */
function editorial_font_preloads(): string
{
    return \Core\Design::preloads(['font_body', 'font_display']);
}

/** Name der Website (Kurzname für die Wortmarke) */
function editorial_name(bool $short = false): string
{
    $name = trim((string) setting('org_name'));
    $shortName = trim((string) setting('short_name'));
    if ($short && $shortName !== '') return $shortName;
    return $name !== '' ? $name : ($shortName !== '' ? $shortName : site_name());
}

// ------------------------------------------------------------------ Kontakt

function editorial_phone(): string
{
    return phone_display(trim((string) setting('phone')));
}

function editorial_phone_href(): ?string
{
    return tel_href(editorial_phone());
}

function editorial_email(): string
{
    $m = trim((string) setting('email'));
    return filled($m) ? $m : '';
}

/** Adresse als Zeilen: [Straße, PLZ Ort, Land] – nur befüllte Teile */
function editorial_address_lines(): array
{
    $street = trim((string) setting('street'));
    $city = trim(trim((string) setting('zip')) . ' ' . trim((string) setting('city')));
    $country = trim((string) setting('country'));
    return array_values(array_filter([$street, $city, $country], 'filled'));
}

/** Link mit Sonderwerten „phone“ und „email“ (Website) */
function editorial_link(?string $link): string
{
    return match (trim((string) $link)) {
        'phone' => editorial_phone_href() ?? link_href('#kontakt'),
        'email' => editorial_email() !== '' ? 'mailto:' . editorial_email() : link_href('#kontakt'),
        default => link_href($link),
    };
}

function editorial_link_attrs(?string $link): string
{
    $href = editorial_link($link);
    return 'href="' . e($href) . '"' . ext_attrs($href);
}

/** Hinweis für Screenreader bei externen Links */
function editorial_ext_note(string $href): string
{
    return is_external($href) ? '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '';
}

/** Button-Paar aus button_label/button_link und button2_label/button2_link (nur mit Beschriftung und Link) */
function editorial_buttons(\Core\Block $b, string $class = ''): string
{
    $d = $b->data;
    $h = '';
    foreach (['button' => 'btn--primary', 'button2' => 'btn--ghost'] as $k => $style) {
        $label = trim((string) ($d[$k . '_label'] ?? ''));
        $link = trim((string) ($d[$k . '_link'] ?? ''));
        if ($label === '' || $link === '') continue;
        $h .= '<a class="btn ' . $style . '" ' . editorial_link_attrs($link) . '><span' . $b->edit($k . '_label') . '>' . e($label) . '</span>'
            . editorial_ext_note(editorial_link($link)) . '</a>';
    }
    return $h !== '' ? '<div class="btn-row' . ($class !== '' ? ' ' . e($class) : '') . '">' . $h . '</div>' : '';
}

// ------------------------------------------------------------------ Geschäftszeiten (optional, Kontakt-Block)

function editorial_clock(?string $t): string
{
    return ltrim((string) $t, '0') ?: '0:00';
}

/** Zeitfenster eines Eintrags, z. B. „9:00–12:30, 13:30–17:00 Uhr“ (für API und Anzeige) */
function editorial_time_range(array $r): string
{
    $seg = [];
    if (!empty($r['von']) && !empty($r['bis'])) {
        if (!empty($r['pause_von']) && !empty($r['pause_bis'])) {
            $seg[] = editorial_clock($r['von']) . '–' . editorial_clock($r['pause_von']);
            $seg[] = editorial_clock($r['pause_bis']) . '–' . editorial_clock($r['bis']);
        } else {
            $seg[] = editorial_clock($r['von']) . '–' . editorial_clock($r['bis']);
        }
    }
    $time = $seg ? lt('{zeit} Uhr', ['zeit' => implode(', ', $seg)]) : '';
    return implode(' · ', array_filter([$time, trim((string) ($r['notiz'] ?? ''))]));
}

/** Zeiten Mo–So; aufeinanderfolgende Tage mit gleichen Zeiten zusammengefasst. @return list<array{days: string, time: string}> */
function editorial_hours(): array
{
    $byDay = [];
    foreach ((array) setting('hours', []) as $r) {
        $dow = (int) ($r['tag'] ?? -1);
        if ($dow < 0 || $dow > 6) continue;
        $t = editorial_time_range($r);
        if ($t !== '') $byDay[$dow][] = $t;
    }
    $groups = [];
    foreach ([1, 2, 3, 4, 5, 6, 0] as $dow) {
        if (!isset($byDay[$dow])) { $groups[] = null; continue; }
        $time = implode(' · ', $byDay[$dow]);
        $last = $groups ? $groups[array_key_last($groups)] : null;
        if ($last && $last['time'] === $time) $groups[array_key_last($groups)]['to'] = $dow;
        else $groups[] = ['from' => $dow, 'to' => $dow, 'time' => $time];
    }
    $day = fn(int $dow) => date_local(strtotime('2024-01-0' . ($dow === 0 ? 7 : $dow)), 'weekday');   // 01.01.2024 war ein Montag
    $out = [];
    foreach (array_filter($groups) as $g) {
        $out[] = ['days' => $day($g['from']) . ($g['to'] !== $g['from'] ? '–' . $day($g['to']) : ''), 'time' => $g['time']];
    }
    return $out;
}

// ------------------------------------------------------------------ Navigation, Recht, Social

/** Links Impressum / Datenschutz / Barrierefreiheit (in der Sprache der Seite, falls übersetzt) */
function editorial_legal_links(): array
{
    $out = [];
    foreach (['imprint_page' => lt('Impressum'), 'privacy_page' => lt('Datenschutz'), 'accessibility_page' => lt('Barrierefreiheit')] as $k => $label) {
        $id = (int) setting($k);
        $p = $id ? Pages::find($id) : null;
        if ($p && \Core\Lang::multi()) $p = Pages::translations($p)[\Core\Lang::current()] ?? $p;
        if ($p && ($p['status'] === 'published' || app()->auth->check())) $out[] = ['label' => $label, 'href' => Pages::url($p)];
    }
    return $out;
}

/** Social-Media-Profile mit gültiger Adresse */
function editorial_social(): array
{
    return array_values(array_filter((array) setting('social', []), fn($s) => trim((string) ($s['label'] ?? '')) !== '' && is_external($s['url'] ?? '')));
}

/** Hauptmenü: Seiten mit „Im Menü“ (Unterseiten aufklappbar) + Anker der Startseite mit „In Navigation“ */
function editorial_menu(): array
{
    static $items = null;
    if ($items !== null) return $items;
    $items = Pages::menu(app()->auth->check());
    foreach (app()->theme->navigation() as $n) {
        $items[] = ['id' => 'a-' . $n['id'], 'label' => $n['label'], 'href' => $n['href'], 'active' => false, 'children' => []];
    }
    return $items;
}

/** Handlungsaufruf des Kopfbereichs [label, link] oder null (Stil „Kein“) – für Menü-Blatt und Fußbereich; im Kopf: header_actions() */
function editorial_header_cta(): ?array
{
    // Design → „Kopfbereich: Suche & Aktionen“ (Core\HeaderActions): Vorgabe (Kontakt, Termin …) oder Website → Darstellung
    $a = header_cta();
    return $a ? ['label' => $a['label'], 'link' => $a['link']] : null;
}

/** Beschreibung einer Seite (meta_description, gekürzt) – für das Ressort-Menü */
function editorial_page_desc(mixed $id, int $max = 110): string
{
    static $cache = [];
    if (!is_int($id)) return '';
    if (!isset($cache[$id])) {
        $p = Pages::find($id);
        $cache[$id] = trim((string) ($p['meta_description'] ?? ''));
    }
    $d = $cache[$id];
    return mb_strlen($d) > $max ? rtrim(mb_substr($d, 0, $max - 2), " ,.;:–-") . ' …' : $d;
}

function editorial_chev(): string
{
    return '<svg class="nav__chev" viewBox="0 0 12 12" aria-hidden="true" focusable="false"><path d="m3 4.5 3 3 3-3"/></svg>';
}

/**
 * Hauptmenü als Liste: Unterseiten aufklappbar über <details>/<summary> – funktioniert ohne JavaScript;
 * site.js ergänzt Pfeiltasten, Escape, „nur eins offen“ und schließt beim Klick daneben. Mobil = Akkordeon im Menü-Blatt.
 */
function editorial_nav_list(array $menu): string
{
    $currentId = (int) (app()->currentPage['id'] ?? 0);
    $cur = fn(array $m) => is_int($m['id']) && $m['id'] === $currentId ? ' aria-current="page"' : '';
    $nested = function (array $items, string $cls) use (&$nested, $cur): string {
        if (!$items) return '';
        $h = '<ul class="' . $cls . '">';
        foreach ($items as $c) {
            $h .= '<li><a class="subnav__link" href="' . e($c['href']) . '"' . $cur($c) . '>' . e($c['label']) . '</a>' . $nested($c['children'], 'subnav subnav--nested') . '</li>';
        }
        return $h . '</ul>';
    };
    $h = '<ul class="nav">';
    foreach ($menu as $m) {
        $active = $m['active'] ? ' data-active' : '';
        if (!$m['children']) {
            $h .= '<li class="nav__item"><a class="nav__link" href="' . e($m['href']) . '"' . $cur($m) . $active . '>' . e($m['label']) . '</a></li>';
            continue;
        }
        $h .= '<li class="nav__item has-sub"><details class="nav__sub"' . $active . '>'
            . '<summary class="nav__link"><span>' . e($m['label']) . '</span>' . editorial_chev() . '</summary>'
            . '<ul class="subnav"><li><a class="subnav__link subnav__link--all" href="' . e($m['href']) . '"' . $cur($m) . '>' . e(lt('Übersicht')) . '<span class="sr-only">: ' . e($m['label']) . '</span></a></li>';
        foreach ($m['children'] as $c) {
            $h .= '<li><a class="subnav__link" href="' . e($c['href']) . '"' . $cur($c) . '>' . e($c['label']) . '</a>' . $nested($c['children'], 'subnav subnav--nested') . '</li>';
        }
        $h .= '</ul></details></li>';
    }
    return $h . '</ul>';
}

/** Rubriken als einfache Links (mobile Reiterleiste der Variante „Geteilt“, Ressortkopf) */
function editorial_tab_links(array $items, string $class = 'strip__list'): string
{
    $currentId = (int) (app()->currentPage['id'] ?? 0);
    $h = '<ul class="' . e($class) . '">';
    foreach ($items as $m) {
        $on = (is_int($m['id']) && $m['id'] === $currentId) ? ' aria-current="page"' : (!empty($m['active']) ? ' data-active' : '');
        $h .= '<li><a href="' . e($m['href']) . '"' . $on . '>' . e($m['label']) . '</a></li>';
    }
    return $h . '</ul>';
}

/**
 * Unterseiten für den Ressortkopf: Kinder der Seite – oder, wenn sie keine hat, die Geschwister samt Elternseite.
 * @return list<array{id:int,label:string,href:string,active:bool}>
 */
function editorial_subpages(?array $page): array
{
    if (empty($page['id'])) return [];
    // Pfad bis zur Seite im Menübaum
    $trail = function (array $nodes, array $path = []) use (&$trail, $page): ?array {
        foreach ($nodes as $n) {
            if ($n['id'] === (int) $page['id']) return [...$path, $n];
            if ($n['children'] && ($r = $trail($n['children'], [...$path, $n]))) return $r;
        }
        return null;
    };
    $path = $trail(Pages::menu(app()->auth->check()));
    if (!$path) return [];
    $self = $path[count($path) - 1];
    if ($self['children']) return [['id' => $self['id'], 'label' => lt('Alle'), 'href' => $self['href'], 'active' => true], ...$self['children']];
    $parent = $path[count($path) - 2] ?? null;
    return $parent ? [['id' => $parent['id'], 'label' => lt('Alle'), 'href' => $parent['href'], 'active' => false], ...$parent['children']] : [];
}

/** Datumszeile: Wochentag + Datum (site.js aktualisiert sie im Browser – der Seiten-Cache bleibt gültig) */
function editorial_dateline(): string
{
    $today = time();
    $edition = trim((string) setting('edition'));
    return '<p class="dateline__text"><time data-today datetime="' . e(date('Y-m-d', $today)) . '">' . e(date_local($today, 'weekday') . ', ' . date_local($today, 'long')) . '</time>'
        . ($edition !== '' ? '<span class="dateline__sep" aria-hidden="true"> · </span><span class="dateline__ed">' . e($edition) . '</span>' : '')
        . '<span class="dateline__night"><span class="dateline__sep" aria-hidden="true"> · </span>' . e(lt('Nachtausgabe')) . '</span></p>';
}

// ------------------------------------------------------------------ Ausgabe-Bausteine

/**
 * Kopf eines Abschnitts: Dachzeile, Überschrift (H2 mit ID für aria-labelledby), Einleitung – mit Linie (Design → Linien).
 * Leere optionale Teile erscheinen nicht; im Editor ist die Überschrift immer direkt bearbeitbar.
 */
function editorial_head(\Core\Block $b, string $class = '', string $tag = 'h2'): string
{
    $d = $b->data;
    $eyebrow = trim((string) ($d['eyebrow'] ?? ''));
    $title = trim((string) ($d['title'] ?? ''));
    $intro = trim((string) ($d['intro'] ?? ''));
    if ($eyebrow === '' && $title === '' && $intro === '' && !is_editing()) return '';
    $h = '<header class="sec-head' . ($class !== '' ? ' ' . e($class) : '') . '">';
    if ($eyebrow !== '') $h .= '<p class="kicker"' . $b->edit('eyebrow') . '>' . e($eyebrow) . '</p>';
    if ($title !== '' || is_editing()) $h .= '<' . $tag . ' id="' . e($b->titleId()) . '" class="h2"' . $b->edit('title') . '>' . e($title) . '</' . $tag . '>';
    if ($intro !== '') $h .= '<p class="lead"' . $b->edit('intro') . '>' . nl2br(e($intro), false) . '</p>';
    return $h . '</header>';
}

/**
 * Bild im festen Format (Klasse .media → Bildbehandlung aus dem Design). Ohne Bild: für Besucher nichts, im Editor ein Platzhalter.
 * @param string $ratio z. B. '4:3' oder '' (Originalformat)
 */
function editorial_image(?int $id, string $sizes, string $ratio, string $class = '', array $opt = []): string
{
    $pic = img($id, $sizes, ($ratio !== '' ? ['ratio' => $ratio] : []) + $opt);
    $cls = 'media' . ($ratio !== '' ? ' r-' . str_replace(':', '-', $ratio) : ' media--free') . ($class !== '' ? ' ' . $class : '');
    if ($pic !== '') return '<div class="' . e($cls) . '">' . $pic . '</div>';
    return is_editing() ? '<div class="' . e($cls) . ' media--empty"><span>' . e('Bild' . ($ratio !== '' ? ' · ' . $ratio : '')) . '</span></div>' : '';
}

/** Bildnachweis aus der Mediathek („Foto: …“) */
function editorial_credit(?int $id): string
{
    $m = $id ? media($id) : null;
    return trim((string) ($m['credit'] ?? ''));
}

/** Bildunterschrift + Nachweis als <figcaption> (leer → nichts) */
function editorial_caption(string $caption, string $credit = '', string $edit = ''): string
{
    if ($caption === '' && $credit === '' && $edit === '') return '';
    return '<figcaption class="cap">' . ($caption !== '' || $edit !== '' ? '<span class="cap__text"' . $edit . '>' . e($caption) . '</span>' : '')
        . ($credit !== '' ? ' <span class="cap__credit">' . e($credit) . '</span>' : '') . '</figcaption>';
}

/**
 * Symbol aus dem Kern-Sprite (Phosphor duotone, icon()). Frühere Symbolnamen des Themes „basis“ werden übersetzt,
 * damit Inhalte beim Theme-Wechsel ihre Symbole behalten.
 */
function editorial_icon(?string $name, string $class = ''): string
{
    $legacy = ['chat' => 'chat-circle-text', 'chart' => 'chart-line-up', 'shield' => 'shield-check', 'bolt' => 'lightning', 'bulb' => 'lightbulb',
        'tool' => 'hammer', 'pin' => 'map-pin', 'mail' => 'envelope-simple', 'box' => 'package', 'layers' => 'squares-four', 'home' => 'house', 'spark' => 'sparkle',
        'target' => 'flag', 'chart' => 'chart-line-up'];
    $name = trim((string) $name);
    $name = $legacy[$name] ?? $name;
    if ($name === '' || !\Core\Icons::exists($name)) return '';
    return icon($name, ['class' => $class]);
}

/** Lesezeit in Minuten (200 Wörter je Minute) */
function editorial_reading_time(string $html): int
{
    $words = str_word_count(strip_tags(str_replace('>', '> ', $html)), 0, 'ÄÖÜäöüß');
    return max(1, (int) round($words / 200));
}

/**
 * Inhaltsverzeichnis aus Zwischenüberschriften (h2, sonst h3) eines bereinigten Textes: IDs ergänzen und Einträge liefern.
 * @return array{0: string, 1: list<array{id: string, label: string}>}
 */
function editorial_toc(string $html, string $prefix = 'abschnitt'): array
{
    // Zwischenüberschriften: h2 (falls vorhanden), sonst h3 – der Kern-Editor erzeugt in Texten h3/h4
    $lvl = preg_match('~<h2[\s>]~i', $html) ? '2' : '3';
    $items = [];
    $html = (string) preg_replace_callback('~<h' . $lvl . '(\s[^>]*)?>(.*?)</h' . $lvl . '>~is', function ($m) use (&$items, $prefix, $lvl) {
        $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
        if ($label === '') return $m[0];
        $id = $prefix . '-' . (count($items) + 1) . '-' . substr(Pages::slugify($label), 0, 40);
        $items[] = ['id' => $id, 'label' => $label];
        $attrs = preg_replace('~\sid="[^"]*"~', '', (string) ($m[1] ?? ''));
        return '<h' . $lvl . ' id="' . e($id) . '"' . $attrs . '>' . $m[2] . '</h' . $lvl . '>';
    }, $html);
    return [$html, $items];
}

/** Inhaltsverzeichnis als Navigation */
function editorial_toc_nav(array $items, string $id): string
{
    if (count($items) < 2) return '';
    $h = '<nav class="toc" aria-labelledby="' . e($id) . '"><p class="toc__title" id="' . e($id) . '">' . e(lt('Inhalt')) . '</p><ol class="toc__list">';
    foreach ($items as $it) $h .= '<li><a href="#' . e($it['id']) . '">' . e($it['label']) . '</a></li>';
    return $h . '</ol></nav>';
}

/**
 * Teilen ohne Tracker: einfache Adressen der Dienste (kein Skript, keine Zählpixel) + „Link kopieren“ (js/blocks.js).
 */
function editorial_share(string $url, string $title): string
{
    $u = rawurlencode($url);
    $t = rawurlencode($title);
    $links = [
        'E-Mail' => 'mailto:?subject=' . $t . '&body=' . $u,
        'WhatsApp' => 'https://wa.me/?text=' . $t . '%20' . $u,
        'Bluesky' => 'https://bsky.app/intent/compose?text=' . $t . '%20' . $u,
        'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $u,
    ];
    $h = '<div class="share"><p class="share__title">' . e(lt('Teilen')) . '</p><ul class="share__list">';
    foreach ($links as $label => $href) {
        $ext = str_starts_with($href, 'https://');
        $h .= '<li><a class="share__link" href="' . e($href) . '"' . ($ext ? ' target="_blank" rel="noopener noreferrer"' : '') . '>'
            . e($label === 'E-Mail' ? lt('E-Mail') : $label) . ($ext ? '<span class="sr-only"> ' . e(lt('(öffnet in neuem Tab)')) . '</span>' : '') . '</a></li>';
    }
    $h .= '<li><button type="button" class="share__link share__copy" data-copy="' . e($url) . '" data-done="' . e(lt('Link kopiert')) . '" hidden>' . e(lt('Link kopieren')) . '</button></li>';
    return $h . '</ul></div>';
}

// ------------------------------------------------------------------ SEO, App, API

/** Schema.org Organization / NGO – nur befüllte Felder (Kern: vollständig auf der Startseite, sonst als Verweis) */
function editorial_jsonld(array $page): ?array
{
    if (empty($page['is_home'])) return null;
    $type = (string) setting('schema_type', 'Organization');
    $d = ['@context' => 'https://schema.org', '@type' => in_array($type, ['Organization', 'NGO', 'NewsMediaOrganization', 'EducationalOrganization', 'PerformingArtsTheater', 'LocalBusiness'], true) ? $type : 'Organization'];
    if (filled(setting('org_name'))) $d['name'] = (string) setting('org_name');
    if (filled(setting('tagline'))) $d['slogan'] = (string) setting('tagline');
    $d['url'] = absolute_url('/');
    if ($tel = editorial_phone_href()) $d['telephone'] = substr($tel, 4);
    if (editorial_email() !== '') $d['email'] = editorial_email();
    $logo = media((int) setting('logo') ?: null);
    if ($logo) $d['logo'] = site_url() . \Core\Media::url($logo);
    $addr = array_filter([
        'streetAddress' => filled(setting('street')) ? (string) setting('street') : null,
        'postalCode' => filled(setting('zip')) ? (string) setting('zip') : null,
        'addressLocality' => filled(setting('city')) ? (string) setting('city') : null,
        'addressCountry' => filled(setting('country')) ? (string) setting('country') : null,
    ]);
    if ($addr) $d['address'] = ['@type' => 'PostalAddress'] + $addr;
    if ($social = editorial_social()) $d['sameAs'] = array_column($social, 'url');
    return isset($d['name']) ? $d : null;
}

/** Name, Kurzname und Kurzbefehle für die installierbare Web-App (Manifest) */
function editorial_app_info(): array
{
    $short = editorial_name(true);
    return [
        'name' => editorial_name(),
        'short_name' => mb_strlen($short) <= 14 ? $short : mb_substr($short, 0, 12) . '…',
        'description' => (string) (setting('tagline') ?: setting('default_meta_description')),
        'shortcuts' => [],
    ];
}

/** Öffentliche Basisdaten (GET /api/v1/public, ohne Token) */
function editorial_public_info(): array
{
    return [
        'name' => editorial_name(),
        'tagline' => filled(setting('tagline')) ? setting('tagline') : null,
        'edition' => filled(setting('edition')) ? setting('edition') : null,
        'address' => array_filter([
            'street' => filled(setting('street')) ? setting('street') : null,
            'zip' => filled(setting('zip')) ? setting('zip') : null,
            'city' => filled(setting('city')) ? setting('city') : null,
            'country' => filled(setting('country')) ? setting('country') : null,
        ]),
        'phone' => ($t = editorial_phone_href()) ? substr($t, 4) : null,
        'phone_display' => filled(editorial_phone()) ? editorial_phone() : null,
        'email' => editorial_email() ?: null,
        'social' => editorial_social(),
    ];
}
