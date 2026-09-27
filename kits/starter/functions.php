<?php
// SPDX-License-Identifier: MIT
/**
 * Helfer des Start-Kits. Alle Funktionen tragen das Präfix des Kits (starter_*) – mehrere Kits können so nebeneinander
 * installiert sein, und kit:create benennt das Präfix beim Kopieren um (starter_ → meinkit_).
 * Nur kleine, reine Funktionen: Ausgabe gehört in templates/ und blocks/.
 */
declare(strict_types=1);

use Core\Block;
use Core\Pages;

// ------------------------------------------------------------------ Stammdaten (aus „Website“, theme.php § 7)

/** Name der Organisation – $short: Kurzname/Wortmarke, falls gepflegt */
function starter_name(bool $short = false): string
{
    $name = trim((string) setting('org_name'));
    $shortName = trim((string) setting('short_name'));
    if ($short && filled($shortName)) return $shortName;
    return filled($name) ? $name : (filled($shortName) ? $shortName : site_name());
}

/** E-Mail-Adresse ('' = nicht gepflegt oder [Platzhalter]) */
function starter_email(): string
{
    $m = trim((string) setting('email'));
    return filled($m) ? $m : '';
}

/** Telefon zur Anzeige – in weiteren Sprachen automatisch international (+49 …) */
function starter_phone(): string
{
    return phone_display(trim((string) setting('phone')));
}

/** Adresse als Zeilen [Straße, PLZ Ort] – nur befüllte Teile */
function starter_address_lines(): array
{
    $city = trim(trim((string) setting('zip')) . ' ' . trim((string) setting('city')));
    return array_values(array_filter([trim((string) setting('street')), $city], 'filled'));
}

/** Social-Media-Profile [['label' => …, 'url' => …], …] */
function starter_social(): array
{
    return array_values(array_filter((array) setting('social', []), fn($s) => is_array($s) && filled($s['label'] ?? '') && filled($s['url'] ?? '')));
}

// ------------------------------------------------------------------ Links

/** Link-Feld → href. Sonderwerte aus theme.php → 'link_keywords': „phone“, „email“ */
function starter_link(?string $link): string
{
    return match (trim((string) $link)) {
        'phone' => tel_href(starter_phone()) ?? link_href('#kontakt'),
        'email' => starter_email() !== '' ? 'mailto:' . starter_email() : link_href('#kontakt'),
        default => link_href($link),
    };
}

/** href-Attribut + target/rel für externe Links */
function starter_link_attrs(?string $link): string
{
    $href = starter_link($link);
    return 'href="' . e($href) . '"' . ext_attrs($href);
}

/** Rechtliche Seiten (Website → Recht) als [['key', 'label', 'href'], …] – in der Sprache der Seite */
function starter_legal_links(): array
{
    $out = [];
    foreach (['imprint_page' => lt('Impressum'), 'privacy_page' => lt('Datenschutz'), 'accessibility_page' => lt('Barrierefreiheit')] as $k => $label) {
        $p = ($id = (int) setting($k)) ? Pages::find($id) : null;
        if ($p && \Core\Lang::multi()) $p = Pages::translations($p)[\Core\Lang::current()] ?? $p;
        if ($p && ($p['status'] === 'published' || app()->auth->check())) $out[] = ['key' => $k, 'label' => $label, 'href' => Pages::url($p)];
    }
    return $out;
}

/**
 * Hauptmenü: Seiten mit „Im Menü“ (Pages::menu, Baum mit 'children') + Abschnitte der Startseite mit
 * „In Navigation zeigen“ (Theme::navigation, Sprunganker). Einträge: id, label, href, active, children.
 */
function starter_menu(): array
{
    static $menu = null;
    if ($menu !== null) return $menu;
    $menu = Pages::menu(app()->auth->check());
    foreach (app()->theme->navigation() as $n) {
        $menu[] = ['id' => 'a-' . $n['id'], 'label' => $n['label'], 'href' => $n['href'], 'active' => false, 'children' => []];
    }
    return $menu;
}

/** Ist der Menüpunkt die aufgerufene Seite? (für aria-current="page") */
function starter_is_current(array $m): bool
{
    return is_int($m['id']) && $m['id'] === (int) (app()->currentPage['id'] ?? 0);
}

// ------------------------------------------------------------------ Bausteine für Blöcke

/**
 * Kopf eines Abschnitts: Dachzeile, Überschrift (mit id für aria-labelledby), Einleitung.
 * $b->edit('feld') macht den Text im Editor direkt bearbeitbar (leer für Besucher).
 */
function starter_head(Block $b, string $tag = 'h2'): string
{
    $d = $b->data;
    $h = '';
    if (filled($d['eyebrow'] ?? '')) $h .= '<p class="eyebrow"' . $b->edit('eyebrow') . '>' . e($d['eyebrow']) . '</p>';
    if (filled($d['title'] ?? '') || is_editing()) {
        $h .= '<' . $tag . ' id="' . e($b->titleId()) . '"' . $b->edit('title') . '>' . e((string) ($d['title'] ?? '')) . '</' . $tag . '>';
    }
    if (filled($d['intro'] ?? '')) $h .= '<p class="lead"' . $b->edit('intro') . '>' . nl2br(e($d['intro']), false) . '</p>';
    return $h === '' ? '' : '<header class="sec-head">' . $h . '</header>';
}

/** Button-Paar aus button_label/button_link und button2_label/button2_link – nur vollständige Buttons erscheinen */
function starter_buttons(Block $b): string
{
    $d = $b->data;
    $h = '';
    foreach (['button' => 'btn--primary', 'button2' => 'btn--secondary'] as $k => $class) {
        if (!filled($d[$k . '_label'] ?? '') || trim((string) ($d[$k . '_link'] ?? '')) === '') continue;
        $h .= '<a class="btn ' . $class . '" ' . starter_link_attrs($d[$k . '_link']) . '><span' . $b->edit($k . '_label') . '>' . e($d[$k . '_label']) . '</span></a>';
    }
    return $h === '' ? '' : '<div class="btn-row">' . $h . '</div>';
}

/** Bild im festen Format (Zuschnitt aus der Mediathek) – im Editor ein Platzhalter, sonst nichts */
function starter_image(mixed $id, string $sizes, string $ratio = '', string $class = ''): string
{
    $pic = img((int) $id ?: null, $sizes, $ratio !== '' ? ['ratio' => $ratio] : []);
    $cls = trim('media ' . ($ratio !== '' ? 'r-' . str_replace(':', '-', $ratio) : '') . ' ' . $class);
    if ($pic !== '') return '<div class="' . e($cls) . '">' . $pic . '</div>';
    return is_editing() ? '<div class="' . e($cls) . ' media--empty"><span>Bild wählen</span></div>' : '';
}

// ------------------------------------------------------------------ Hooks für den Core (theme.php)

/** Klassen für <html>: Design-Tokens (nav-bar, has-dark …) + no-js (site.js ersetzt es durch js) */
function starter_html_class(): string
{
    return trim('no-js ' . design_classes());
}

/** schema.org-Organisation (theme.php → 'jsonld'); der Core ergänzt @id, Geo-Koordinaten und den Rest des @graph */
function starter_jsonld(array $page): ?array
{
    if (empty($page['is_home'])) return null;
    $type = (string) setting('schema_type', 'Organization');
    $d = ['@context' => 'https://schema.org', '@type' => in_array($type, ['Organization', 'LocalBusiness', 'NGO'], true) ? $type : 'Organization',
        'name' => starter_name(), 'url' => absolute_url('/')];
    if (filled(setting('tagline'))) $d['slogan'] = (string) setting('tagline');
    if ($tel = tel_href(starter_phone())) $d['telephone'] = substr($tel, 4);
    if (starter_email() !== '') $d['email'] = starter_email();
    if ($logo = media((int) setting('logo') ?: null)) $d['logo'] = site_url() . \Core\Media::url($logo);
    $addr = array_filter(['streetAddress' => setting('street'), 'postalCode' => setting('zip'), 'addressLocality' => setting('city')], 'filled');
    if ($addr) $d['address'] = ['@type' => 'PostalAddress'] + $addr;
    if ($same = array_column(starter_social(), 'url')) $d['sameAs'] = $same;
    return $d;
}

/** Öffentliche Angaben für REST-API und MCP (theme.php → project.public_info) */
function starter_public_info(): array
{
    return [
        'name' => starter_name(),
        'tagline' => filled(setting('tagline')) ? setting('tagline') : null,
        'address' => starter_address_lines(),
        'phone' => filled(starter_phone()) ? starter_phone() : null,
        'email' => starter_email() ?: null,
        'social' => starter_social(),
    ];
}

/** Web-App-Manifest (theme.php → app.info) */
function starter_app_info(): array
{
    $short = starter_name(true);
    return [
        'name' => starter_name(),
        'short_name' => mb_strlen($short) <= 14 ? $short : mb_substr($short, 0, 12) . '…',
        'description' => (string) (setting('tagline') ?: setting('default_meta_description')),
    ];
}
