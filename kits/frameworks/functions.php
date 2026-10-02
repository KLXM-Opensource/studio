<?php
// SPDX-License-Identifier: MIT
/**
 * Helfer des Kits „frameworks“ (Präfix frameworks_*). Neu gegenüber dem Start-Kit:
 *   frameworks_fw()          aktives Framework: 'tailwind' | 'uikit' (Design → Framework, ?fw=… zum Vergleichen)
 *   frameworks_view($name)   Pfad der Vorlage views/{framework}/{name}.php – blocks/*.php und Partials binden sie ein
 *   frameworks_html_class()  Klassen am <html>: Design-Tokens + fw-{framework}
 *   frameworks_assets()      Stylesheets und Skripte des Frameworks für layout.php
 * Alles Framework-Spezifische (Klassen, Markup, JS-Attribute) steht in views/tailwind/ bzw. views/uikit/ – hier nur Logik.
 */
declare(strict_types=1);

use Core\Block;
use Core\Pages;

/** Unterstützte Frameworks (Funktion statt Konstante: kit:create benennt nur das Präfix frameworks_ um) */
function frameworks_all(): array
{
    return ['tailwind', 'uikit'];
}

// ------------------------------------------------------------------ Framework wählen

/**
 * Aktives Framework. Reihenfolge:
 *  1. ?fw=tailwind|uikit – nur diese Anfrage (Besucher bekommen kein Cookie; Seiten mit Query-String cached der Kern nicht).
 *     Angemeldete Redaktion: zusätzlich für die Sitzung gemerkt (Editor-Vorschauen laden ohne ?fw); ?fw=reset vergisst es.
 *  2. gemerkter Wert der Sitzung (nur mit bestehender Sitzung, nie für Besucher)
 *  3. Design → „Framework“ (gespeichert je Website)
 */
function frameworks_fw(): string
{
    static $fw = null;
    if ($fw !== null) return $fw;
    $q = strtolower(trim((string) (app()->request?->query['fw'] ?? '')));
    $session = app()->session->started() && app()->auth->check() ? app()->session : null;
    if (in_array($q, frameworks_all(), true)) {
        $session?->set('frameworks.fw', $q);
        return $fw = $q;
    }
    if ($q === 'reset') $session?->forget('frameworks.fw');
    $s = $session?->get('frameworks.fw');
    if (is_string($s) && in_array($s, frameworks_all(), true)) return $fw = $s;
    $d = (string) design('framework');
    return $fw = in_array($d, frameworks_all(), true) ? $d : 'tailwind';
}

function frameworks_is(string $name): bool
{
    return frameworks_fw() === $name;
}

/** Vorlage des aktiven Frameworks – include frameworks_view('hero') im Gültigkeitsbereich des Aufrufers ($b, $d …) */
function frameworks_view(string $name): string
{
    return __DIR__ . '/views/' . frameworks_fw() . '/' . basename($name) . '.php';
}

/** Klassen für <html>: Design-Tokens (has-dark, has-sticky, tw-preflight …) ohne die gespeicherte fw-*, dazu das aktive fw-* */
function frameworks_html_class(): string
{
    $cls = preg_replace('~(^|\s)fw-[a-z]+~', '', design_classes()) ?? '';
    return trim(preg_replace('~\s+~', ' ', 'no-js fw-' . frameworks_fw() . ' ' . $cls) ?? '');
}

/**
 * Stylesheets/Skripte des aktiven Frameworks: ['css' => [...], 'js' => [...]] (URLs mit Cache-Busting).
 * Reihenfolge der Stylesheets: Framework → css/site.css (Tokens, Kern-Variablen) → Brücke (nur UIkit).
 */
function frameworks_assets(): array
{
    $t = app()->theme;
    if (frameworks_is('uikit')) {
        return [
            'css' => [$t->asset('vendor/uikit/uikit.min.css'), $t->asset('css/site.css'), $t->asset('css/uikit.css')],
            'js' => [$t->asset('vendor/uikit/uikit.min.js'), $t->asset('vendor/uikit/uikit-icons.min.js'), $t->asset('js/site.js')],
        ];
    }
    $pf = (bool) design('tw_preflight');
    return [
        'css' => [$t->asset($pf ? 'css/tailwind.css' : 'css/tailwind-nopf.css'), $t->asset('css/site.css')],
        'js' => [$t->asset('js/site.js'), $t->asset('js/tailwind.js')],
    ];
}

// ------------------------------------------------------------------ Stammdaten (aus „Website“, theme.php § 7)

function frameworks_name(bool $short = false): string
{
    $name = trim((string) setting('org_name'));
    $shortName = trim((string) setting('short_name'));
    if ($short && filled($shortName)) return $shortName;
    return filled($name) ? $name : (filled($shortName) ? $shortName : site_name());
}

function frameworks_email(): string
{
    $m = trim((string) setting('email'));
    return filled($m) ? $m : '';
}

function frameworks_phone(): string
{
    return phone_display(trim((string) setting('phone')));
}

/** Adresse als Zeilen [Straße, PLZ Ort] – nur befüllte Teile */
function frameworks_address_lines(): array
{
    $city = trim(trim((string) setting('zip')) . ' ' . trim((string) setting('city')));
    return array_values(array_filter([trim((string) setting('street')), $city], 'filled'));
}

// ------------------------------------------------------------------ Links & Menü

/** Link-Feld → href. Sonderwerte aus theme.php → 'link_keywords': „phone“, „email“ */
function frameworks_link(?string $link): string
{
    return match (trim((string) $link)) {
        'phone' => tel_href(frameworks_phone()) ?? link_href('#kontakt'),
        'email' => frameworks_email() !== '' ? 'mailto:' . frameworks_email() : link_href('#kontakt'),
        default => link_href($link),
    };
}

function frameworks_link_attrs(?string $link): string
{
    $href = frameworks_link($link);
    return 'href="' . e($href) . '"' . ext_attrs($href);
}

/** Rechtliche Seiten (Website → Recht) + Links von Erweiterungen (footer_links) */
function frameworks_legal_links(): array
{
    $out = [];
    foreach (['imprint_page' => lt('Impressum'), 'privacy_page' => lt('Datenschutz')] as $k => $label) {
        $p = ($id = (int) setting($k)) ? Pages::find($id) : null;
        if ($p && \Core\Lang::multi()) $p = Pages::translations($p)[\Core\Lang::current()] ?? $p;
        if ($p && ($p['status'] === 'published' || app()->auth->check())) $out[] = ['label' => $label, 'href' => Pages::url($p)];
    }
    return array_merge($out, footer_links());
}

/** Hauptmenü: Seiten „Im Menü“ (Baum mit children) + Abschnitte der Startseite mit „In Navigation zeigen“ */
function frameworks_menu(): array
{
    static $menu = null;
    if ($menu !== null) return $menu;
    $menu = Pages::menu(app()->auth->check());
    foreach (app()->theme->navigation() as $n) {
        $menu[] = ['id' => 'a-' . $n['id'], 'label' => $n['label'], 'href' => $n['href'], 'active' => false, 'children' => []];
    }
    return $menu;
}

function frameworks_is_current(array $m): bool
{
    return is_int($m['id']) && $m['id'] === (int) (app()->currentPage['id'] ?? 0);
}

// ------------------------------------------------------------------ Bausteine für Vorlagen

/** Buttons aus button_label/button_link (+ button2_*) – Klassen je Framework: [primär, sekundär] */
function frameworks_buttons(Block $b, array $classes): string
{
    $d = $b->data;
    $h = '';
    foreach (['button' => $classes[0], 'button2' => $classes[1]] as $k => $class) {
        if (!filled($d[$k . '_label'] ?? '') || trim((string) ($d[$k . '_link'] ?? '')) === '') continue;
        $h .= '<a class="' . e($class) . '" ' . frameworks_link_attrs($d[$k . '_link']) . '><span' . $b->edit($k . '_label') . '>' . e($d[$k . '_label']) . '</span></a>';
    }
    return $h;
}

/** Bild im Format (Zuschnitt aus der Mediathek) – im Editor ein Platzhalter. $class = Hülle des Frameworks */
function frameworks_image(mixed $id, string $sizes, string $ratio, string $class, array $opt = []): string
{
    $pic = img((int) $id ?: null, $sizes, ($ratio !== '' ? ['ratio' => $ratio] : []) + $opt);
    $cls = trim('fw-media r-' . str_replace(':', '-', $ratio) . ' ' . $class);
    if ($pic !== '') return '<div class="' . e($cls) . '">' . $pic . '</div>';
    return is_editing() ? '<div class="' . e($cls) . ' fw-media--empty"><span>' . e(lt('Bild wählen')) . '</span></div>' : '';
}

/** Spalten eines Rasters als vollständige Klassennamen (Tailwind findet nur Klassen, die wörtlich im Quelltext stehen) */
function frameworks_cols(int $n, string $fw): string
{
    $n = max(1, min(4, $n));
    return $fw === 'uikit'
        ? [1 => 'uk-child-width-1-1', 2 => 'uk-child-width-1-2@s', 3 => 'uk-child-width-1-2@s uk-child-width-1-3@m', 4 => 'uk-child-width-1-2@s uk-child-width-1-4@m'][$n]
        : [1 => 'grid-cols-1', 2 => 'sm:grid-cols-2', 3 => 'sm:grid-cols-2 lg:grid-cols-3', 4 => 'sm:grid-cols-2 lg:grid-cols-4'][$n];
}

// ------------------------------------------------------------------ Hooks für den Core (theme.php)

function frameworks_jsonld(array $page): ?array
{
    if (empty($page['is_home'])) return null;
    $d = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => frameworks_name(), 'url' => absolute_url('/')];
    if (filled(setting('tagline'))) $d['slogan'] = (string) setting('tagline');
    if ($tel = tel_href(frameworks_phone())) $d['telephone'] = substr($tel, 4);
    if (frameworks_email() !== '') $d['email'] = frameworks_email();
    $addr = array_filter(['streetAddress' => setting('street'), 'postalCode' => setting('zip'), 'addressLocality' => setting('city')], 'filled');
    if ($addr) $d['address'] = ['@type' => 'PostalAddress'] + $addr;
    return $d;
}

function frameworks_public_info(): array
{
    return [
        'name' => frameworks_name(),
        'tagline' => filled(setting('tagline')) ? setting('tagline') : null,
        'address' => frameworks_address_lines(),
        'phone' => filled(frameworks_phone()) ? frameworks_phone() : null,
        'email' => frameworks_email() ?: null,
    ];
}

function frameworks_app_info(): array
{
    $short = frameworks_name(true);
    return [
        'name' => frameworks_name(),
        'short_name' => mb_strlen($short) <= 14 ? $short : mb_substr($short, 0, 12) . '…',
        'description' => (string) (setting('tagline') ?: setting('default_meta_description')),
    ];
}
