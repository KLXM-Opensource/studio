<?php
declare(strict_types=1);

/*
 * Globale Template-Helfer. In Theme-Templates frei verwendbar.
 */

use Core\App;
use Core\Media;
use Core\Pages;
use Core\Sanitizer;

function app(): App
{
    return App::get();
}

/** HTML-Escaping – IMMER für Ausgaben verwenden. */
function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** Wert aus den zentralen Einstellungen (z. B. setting('telefon')) */
function setting(string $key, mixed $default = null): mixed
{
    // Übersetzbare Einstellungen: „feld@en“ hat in der Sprache Vorrang (Grundlage für mehrsprachige Themes)
    if (app()->lang !== null && ($lv = app()->settings->get($key . '@' . app()->lang)) !== null && $lv !== '') {
        return $lv;
    }
    $v = app()->settings->get($key, $default);
    return $v ?? $default;
}

/** true, wenn ein Wert befüllt und kein [Platzhalter] ist */
function filled(mixed $v): bool
{
    if (is_array($v)) {
        return $v !== [];
    }
    $s = trim((string) $v);
    return $s !== '' && !str_starts_with($s, '[');
}

/** Basis-Pfad (bei Installation in Unterordner) */
/** Umgebung dieser Installation: production (Standard), staging oder development (config 'environment') */
function environment(): string
{
    $env = (string) app()->config->get('environment', 'production');
    return in_array($env, ['production', 'staging', 'development'], true) ? $env : 'production';
}

/** Suchmaschinen aussperren? (Grundeinstellung oder jede Umgebung außer production) */
function noindex_site(): bool
{
    return (bool) app()->settings->get('sys.noindex') || environment() !== 'production';
}

/** Vorschau eines einzelnen Listeneintrags (z. B. Hero-Thema): Index im Listenfeld oder null – für Themes */
function preview_focus(string $field): ?int
{
    return app()->previewFocus[$field] ?? null;
}

/** Design-Wert des Style-Editors (theme.php → 'design'), z. B. design('nav') */
function design(string $name): mixed
{
    return \Core\Design::get($name);
}

/** Klassen für <html> aus den Design-Werten (z. B. „nav-modern btn-pill has-dark“) */
function design_classes(): string
{
    return \Core\Design::classes();
}

/** <link>-Tags für gewählte Schriften und die Design-Variablen – im Layout nach dem Theme-CSS */
function design_head(): string
{
    return \Core\Design::head();
}

/**
 * Kopfbereich-Aktionen (Core\HeaderActions, Design → „Kopfbereich: Suche & Aktionen“):
 * header_actions('bar' | 'center' | 'below', ['compact' => true, 'wrap' => 'wrap', 'lang' => $langs, 'lang_class' => '…', 'search' => false, 'cta' => false])
 */
function header_actions(string $slot = 'bar', array $opt = []): string
{
    return \Core\HeaderActions::render($slot, $opt);
}

/** CSS (+ JavaScript bei interaktiven Optionen) der Kopfbereich-Aktionen für <head>, vor dem Kit-CSS; $opt['media'] optional */
function header_actions_head(array $opt = []): string
{
    return \Core\HeaderActions::head($opt);
}

/** Späte Stile der Kopfbereich-Aktionen (Paneel des Kontakt-Menüs) – im Kit-Layout vor </body>; theme.php 'header_actions' => ['late' => true] */
function header_actions_late(): string
{
    return \Core\HeaderActions::late();
}

/** Sprachumschalter im Stil aus Design → „Sprachumschalter“ (Kürzel, Aufklappliste, Sprachnamen – ohne Flaggen) */
function header_actions_lang(array $langs, string $class = ''): string
{
    return \Core\HeaderActions::lang($langs, $class);
}

/** Handlungsaufruf des Kopfbereichs ['label', 'link', 'href', …] oder null (Stil „Kein“ bzw. unvollständig) – z. B. fürs Menü-Blatt */
function header_cta(): ?array
{
    return \Core\HeaderActions::model()['cta'];
}

/** Aktuelle Website (Multi-Site) */
function site(): \Core\Site
{
    return app()->site;
}

function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = PHP_SAPI === 'cli' || $dir === '/' || $dir === '.' ? '' : rtrim($dir, '/');
    }
    return $base;
}

/** Interne URL. Ohne Rewrite: /index.php/pfad */
function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    [$p, $frag] = array_pad(explode('#', $path, 2), 2, null);
    $rewrite = (bool) app()->config->get('url_rewrite', true);
    $out = base_path() . ($rewrite || $p === '/' ? $p : '/index.php' . $p);
    return $out . ($frag !== null ? '#' . $frag : '');
}

function site_url(): string
{
    // Landing-Domain (Core\Landings): eigener Ursprung; innerhalb von Landings::suspend() der der Hauptdomain
    if ($l = \Core\Landings::matched()) {
        return \Core\Landings::current() ? $l->origin() : \Core\Landings::mainOrigin();
    }
    $u = rtrim((string) app()->settings->get('sys.site_url', ''), '/');
    if ($u === '') {
        $u = rtrim((string) app()->config->get('base_url', ''), '/');
    }
    if ($u === '' && ($r = app()->request)) {
        $u = ($r->isSecure() ? 'https://' : 'http://') . $r->host();
    }
    return $u;
}

function absolute_url(string $path = '/'): string
{
    return site_url() . url($path);
}

/** Relative Adresse absolut machen (bereits absolute Adressen – z. B. Links einer Landing-Domain zur Hauptdomain – bleiben) */
function abs_url(string $href): string
{
    return preg_match('~^https?://~i', $href) ? $href : site_url() . $href;
}

/** Ursprung der Hauptdomain – auf Landing-Domains die Hauptadresse der Website, sonst wie site_url() */
function main_url(): string
{
    return \Core\Landings::matched() ? \Core\Landings::mainOrigin() : site_url();
}

/**
 * Landingpage der aktuellen Anfrage (Core\Landing) oder null auf der Hauptdomain – für Themes:
 * landing()?->name, landing()?->tagline, landing()?->logo (Medien-ID), landing()?->layout ('full' | 'landing').
 */
function landing(): ?\Core\Landing
{
    return \Core\Landings::current();
}

/** Core-Asset aus /public/assets mit Cache-Busting */
function asset(string $path): string
{
    $file = ROOT . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : CMS_VERSION;
    return base_path() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function theme_asset(string $path): string
{
    return app()->theme->asset($path);
}

/**
 * Link-Feld in eine href auflösen:
 *  "#anker"  → auf anderen Seiten als der Startseite: "/#anker" (falls Anker dort nicht existiert)
 *  "/seite"  → url('/seite')
 *  "page:ID" → URL der Seite (auch page:ID#anker, entry:{tabelle}:{id}, media:{id}[:viewer] – Core\Links)
 */
function link_href(?string $link): string
{
    $link = trim((string) $link);
    if ($link === '') {
        return '#';
    }
    // Stabile Verweise (Core\Links): page:12, page:12#anker, entry:news:5, media:9, media:9:viewer
    if (\Core\Links::isRef($link)) {
        return \Core\Links::href($link) ?? '#';
    }
    if ($link[0] === '#') {
        $cur = app()->currentPage;
        if ($cur && !$cur['is_home']) {
            $anchors = Pages::anchors($cur, app()->editing);
            if (!isset($anchors[substr($link, 1)])) {
                // Landing-Domain: Anker der Einstiegsseite bleiben dort, sonst Startseite der Hauptdomain
                if (($l = \Core\Landings::current()) && ($root = $l->root())) {
                    if ((int) $root['id'] !== (int) ($cur['id'] ?? 0) && isset(Pages::anchors($root)[substr($link, 1)])) return url('/') . $link;
                    return ($home = Pages::home()) ? Pages::url($home) . $link : $link;
                }
                return url('/') . $link;
            }
        }
        return $link;
    }
    if ($link[0] === '/' && !str_starts_with($link, '//')) {
        // Landing-Domain: Seitenpfade außerhalb der Landingpage zeigen auf die Hauptdomain
        if (\Core\Landings::current() && ($p = \Core\Landings::pageForPath($link))) {
            return Pages::url($p) . (preg_match('~[?#].*$~', $link, $m) ? $m[0] : '');
        }
        return url($link);
    }
    return Sanitizer::safeHref($link) ?? '#';
}

function is_external(?string $link): bool
{
    return (bool) preg_match('~^https?://~i', (string) $link);
}

/** Attribute für externe Links */
function ext_attrs(?string $link): string
{
    return is_external($link) ? ' target="_blank" rel="noopener"' : '';
}

/** Landesvorwahl der Website, z. B. „+49“ (Grundeinstellungen → Website) */
function country_code(): string
{
    $c = preg_replace('~[^\d]~', '', (string) setting('sys.phone_country', '+49'));
    return '+' . ($c !== '' ? $c : '49');
}

/**
 * Telefonnummer für die Anzeige: In der Standardsprache wie eingegeben (z. B. „01234 56 78 90“),
 * in allen anderen Sprachen automatisch international („+49 1234 56 78 90“) – die Gliederung bleibt erhalten.
 */
function phone_display(?string $number, ?string $lang = null): string
{
    $n = trim((string) $number);
    if ($n === '' || str_starts_with($n, '[')) return $n;
    $lang ??= \Core\Lang::current();
    if ($lang === \Core\Lang::default() || str_starts_with($n, '+')) return $n;
    $n = trim((string) preg_replace(['~\s*\(0\)\s*~', '~\s*/\s*~'], ' ', $n));
    if (str_starts_with($n, '00')) return '+' . ltrim(substr($n, 2));
    if (str_starts_with($n, '0')) return country_code() . ' ' . ltrim(substr($n, 1), ' -');
    return country_code() . ' ' . $n;
}

/** Telefonnummer → tel:+49… (null, wenn keine Nummer) */
function tel_href(?string $number): ?string
{
    $n = trim((string) $number);
    if ($n === '' || str_starts_with($n, '[')) {
        return null;
    }
    $digits = preg_replace('~[^\d+]~', '', (string) preg_replace('~\(0\)~', '', $n));
    if (str_starts_with($digits, '00')) {
        $digits = '+' . substr($digits, 2);
    } elseif (str_starts_with($digits, '0')) {
        $digits = country_code() . substr($digits, 1);
    } elseif (!str_starts_with($digits, '+')) {
        $digits = country_code() . $digits;
    }
    return strlen($digits) > 5 ? 'tel:' . $digits : null;
}

/** Rich-Text (Absätze, Listen) sicher ausgeben */
function rich(?string $html): string
{
    return \Core\Landings::rewriteLinks(Sanitizer::block($html));
}

/** Inline-Rich-Text (b, i, a, br) sicher ausgeben */
function inline(?string $html): string
{
    return Sanitizer::inline($html);
}

/** Mehrzeiliger Text → Absätze */
function paragraphs(?string $text, string $attrs = ''): string
{
    $out = '';
    foreach (preg_split('~\n\s*\n~', trim((string) $text)) as $p) {
        if (trim($p) !== '') {
            $out .= "<p$attrs>" . nl2br(e(trim($p)), false) . '</p>';
        }
    }
    return $out;
}

/** Responsives Bild (<picture> mit AVIF/WebP, srcset, width/height) */
function img(?int $mediaId, string $sizes = '100vw', array $opt = []): string
{
    return Media::picture($mediaId, $sizes, $opt);
}

function media(?int $id): ?array
{
    return $id ? Media::find($id) : null;
}

function is_editing(): bool
{
    return app()->editing;
}

/**
 * *Betonung* in kurzen Texten (Überschriften der Kern-Blöcke) wie in den Kits: Bringt das Kit eine Funktion {kit}_title()
 * mit (klxm_title, klxm_agentur_title, fluid_title … – Bindestrich im Namen → „_“), wird sie benutzt, damit Kern-Blöcke
 * aussehen wie die Blöcke des Kits (<strong> bzw. <em class="hl">). Sonst *…* → <em>…</em>. Ergebnis ist HTML, der Text
 * wird immer escaped; im Bearbeiten-Modus Rohtext (die Sternchen bleiben sichtbar und editierbar).
 */
function emphasis(string $text): string
{
    if (is_editing() || !str_contains($text, '*')) return e($text);
    static $fn = [];
    $kit = app()->theme->name;
    $fn[$kit] ??= function_exists($f = str_replace('-', '_', $kit) . '_title') ? $f : '';
    if ($fn[$kit] !== '') return (string) ($fn[$kit])($text);
    return preg_replace('~\*([^*]+)\*~u', '<em>$1</em>', e($text)) ?? e($text);
}

/** Text ohne *Betonung*-Sternchen (für aria-label, title, Meta-Angaben) */
function strip_emphasis(string $text): string
{
    return str_contains($text, '*') ? (string) preg_replace('~\*([^*]+)\*~u', '$1', $text) : $text;
}

/**
 * Eigene Detailvorlagen: macht das Element mit dem Wert eines Feldes für die angemeldete Redaktion direkt editierbar.
 * <h1<?= entry_edit_attr($table, $entry, 'titel') ?>><?= e(…) ?></h1> – für Besucher leer (keine Markup-Änderung).
 * $mode: null = nach Feldtyp (Text → plain, Mehrzeilig → lines, Formatierter Text → rich)
 */
function entry_edit_attr(array $table, array $entry, string $field, ?string $mode = null): string
{
    return \Core\Data\EntryEdit::attr($table, $entry, $field, $mode);
}

/**
 * Karten/Kacheln: kleine Aktion „Bearbeiten“ für das Ziel (Seite → Seiten-Editor, Eintrag → Seitenleiste) – nur für die
 * angemeldete Redaktion mit Recht (pages.edit bzw. data.edit der Tabelle), sonst ''. $ref: page:ID, entry:{tabelle}:{id}
 * oder eine Adresse dieser Website (Core\TargetEdit). <li class="card"><?= edit_link($it['link'], $it['title']) ?>…
 */
function edit_link(?string $ref, ?string $label = null): string
{
    return \Core\TargetEdit::html($ref, $label);
}

/**
 * Redaktions-Werkzeugleiste der Website (Core\Toolbar, alle Modi). Kits rufen $theme->partial('toolbar', $toolbar) auf –
 * das Kern-Fragment rendert sie (nur Kern); cms_toolbar() bleibt für ältere Kit-Dateien und eigene Aufrufe.
 */
function cms_toolbar(array $vars): string
{
    return \Core\Toolbar::render($vars);
}

/**
 * Nur Detailseiten (ältere Theme-Partials mit eigener Seiten-Leiste): if (($bar = entry_toolbar(get_defined_vars())) !== '') { echo $bar; return; }
 * Liefert außerhalb des Vorlagen-Editors die Leiste des Kerns, sonst ''.
 */
function entry_toolbar(array $vars): string
{
    return \Core\Data\EntryEdit::toolbar($vars);
}

/**
 * Besucher-Chat (Core\AI\VisitorChat): Starter-Knopf unten rechts/links – im Theme-Layout vor </body>:
 * <?= cms_chat_launcher($page, (bool) $editor) ?>. Liefert '' solange der Chat nicht eingeschaltet ist; das Chat-Fenster
 * (JavaScript/CSS) lädt erst nach dem ersten Klick, site.js des Themes bleibt unverändert.
 */
function cms_chat_launcher(?array $page = null, bool $editing = false): string
{
    return \Core\AI\VisitorChat::launcher($page, $editing);
}

/**
 * Zusätzliche Links für die Rechtliches-Zeile im Fußbereich (z. B. „Cookie-Einstellungen“ der Erweiterung consent_kit):
 * [['label' => …, 'href' => …], …]. Im Theme: array_merge(theme_legal_links(), footer_links()). Leer ohne Erweiterungen.
 */
function footer_links(): array
{
    $links = \Core\Extensions::footerLinks();
    // Glossar (Core\Glossary): Übersicht verlinken, sobald die Funktion an und die Seite veröffentlicht ist
    if (\Core\Features::on('glossary', false) && ($u = \Core\Glossary\Glossary::overviewUrl())) {
        array_unshift($links, ['label' => lt('Glossar'), 'href' => $u]);
    }
    return $links;
}

/** Adresse der Datenschutzerklärung (Core\Legal) – für Kern-Fragmente und Kits statt kit-eigener Helfer */
function privacy_url(): string
{
    return \Core\Legal::privacyUrl();
}

/** Impressum / Datenschutz / Barrierefreiheit in der Sprache der Seite: [['key', 'type', 'label', 'href'], …] (Core\Legal) */
function legal_links(): array
{
    return \Core\Legal::links();
}

/** Name der Organisation aus den zentralen Angaben (org_name, mit $short: short_name), sonst Name der Website */
function org_name(bool $short = false): string
{
    return \Core\Legal::orgName($short);
}

/**
 * Visitenkarte der Organisation (Core\VCard, vCard 3.0) – Adresse von /vcard.vcf oder null ohne Telefon, E-Mail und Adresse.
 * Im Kit: <?php if ($v = vcard_url()): ?><a href="<?= e($v) ?>" download>Kontakt speichern</a><?php endif; ?>
 */
function vcard_url(): ?string
{
    if (\Core\VCard::orgData() === null) return null;
    $lang = \Core\Lang::current();
    return url(\Core\VCard::ORG_PATH) . ($lang !== \Core\Lang::default() ? '?lang=' . rawurlencode($lang) : '');
}

/** Visitenkarte einer Person (Tabelle mit Schema-Typ „Person“, veröffentlichter Eintrag) – /vcard/{tabelle}/{slug}.vcf oder null */
function vcard_entry_url(array $table, array $entry): ?string
{
    return \Core\VCard::personUrl($table, $entry);
}

/**
 * Kern-Fragment bzw. die Fassung von Projekt/Kit (Core\Fragments, Suchreihenfolge project/overrides → Kit → Kern):
 * <?= fragment('langswitch', ['langs' => language_links()]) ?> – gleichbedeutend mit app()->theme->partial(…).
 */
function fragment(string $name, array $vars = []): string
{
    return app()->theme->partial($name, $vars);
}

/** Logo von KLXM Studio (Kreis mit KLXM-Kreuz, übernimmt die Textfarbe) */
function cms_logo(): string
{
    return '<svg class="cms-mark__logo" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10.002,18.997C5.031,18.998 1.001,14.971 1,10.002C0.999,5.033 5.028,1.003 9.998,1.003C14.969,1.001 18.999,5.029 19,9.998C19.001,14.967 14.972,18.996 10.002,18.997ZM10.005,7.175L7.183,4.353L4.361,7.175L7.183,9.997L4.361,12.819L7.183,15.641L10.005,12.819L12.827,15.641L15.649,12.819L12.827,9.997L15.649,7.175L12.827,4.353L10.005,7.175Z"/></svg>';
}

/** Produktmarke „KLXM Studio“ als HTML */
function cms_mark(): string
{
    return '<span class="cms-mark">' . cms_logo() . '<b>KLXM</b> <em>Studio</em></span>';
}

/**
 * Symbol aus dem Sprite (Phosphor duotone, Core\Icons): icon('calendar'), icon($t['icon'], ['class' => 'x', 'label' => 'Termine']).
 * Nimmt Symbolnamen, Menü-Schlüssel und alte Unicode-Zeichen (◷ ✎ ▦ …); Unbekanntes erscheint als Zeichen.
 * Ohne 'label' dekorativ (aria-hidden), mit 'label' role="img" + <title>. Farbe = currentColor.
 */
function icon(?string $name, array $opt = []): string
{
    return \Core\Icons::render($name, $opt);
}

function csrf_field(): string
{
    return \Core\Csrf::field();
}

function json_attr(mixed $v): string
{
    return e(json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * Projektangaben des Themes (theme.php → 'project'). Der Core bleibt neutral;
 * branchenspezifische Begriffe und Funktionen (z. B. Öffnungszeiten) bringt das Theme mit.
 */
function project(string $key, mixed $default = null): mixed
{
    $p = app()->theme->def['project'] ?? [];
    foreach (explode('.', $key) as $k) {
        if (!is_array($p) || !array_key_exists($k, $p)) return $default;
        $p = $p[$k];
    }
    return $p;
}

/** Sichtbarer Begriff – vom Theme überschreibbar, sonst neutral. */
function term(string $key): string
{
    static $neutral = [
        'org' => 'Organisation',                         // „Passwortmanager der Organisation“
        'key' => 'Schlüssel',                            // geheimer Schlüssel für verschlüsselte Formulare
        'requests' => 'Formular-Anfragen',
        'requests_data' => 'personenbezogene Daten',     // was die Anfragen enthalten
        'records_system' => 'Ihr Fachsystem',            // wohin Anfragen übernommen werden
        'site_name' => 'Name der Website',
        'shared_owner' => 'Verband',                     // Eigentümer-Website geteilter Datentabellen
        'shared_members' => 'Vereine',                   // übrige beteiligte Websites
        'shared_from_owner' => 'Vom Verband',            // Daten-Navigation: Einträge der Eigentümer-Website
        'shared_from_members' => 'Von Vereinen',         // Daten-Navigation: Einträge der übrigen Websites
    ];
    return __((string) project('terms.' . $key, $neutral[$key] ?? $key));
}

/** Name der Website/Organisation aus dem vom Theme benannten Einstellungsfeld */
function site_name(): string
{
    if (($l = \Core\Landings::current()) && $l->name !== null) return $l->name;   // Marke der Landing-Domain
    $v = trim(strip_tags((string) setting((string) project('name_setting', 'site_name'), '')));
    return filled($v) ? $v : (string) (app()->request?->server['HTTP_HOST'] ?? CMS_NAME);
}

/** Oberflächentext übersetzen (Quelle Deutsch). __('Speichern'), __('{n} Einträge', ['n' => 3]) */
/** Fester Text der Website in der Sprache der aufgerufenen Seite (Templates, Blöcke): lt('Anfahrt') */
function lt(string $text, array $params = []): string
{
    return \Core\I18n::site($text, $params);
}

/**
 * Datum in der Sprache der aufgerufenen Seite. $style: short (24.09.2026 / 24/09/2026), long (24. September 2026 / 24 September 2026),
 * weekday (Donnerstag / Thursday), day_month (24. September / 24 September).
 */
function date_local(int|string|null $when, string $style = 'short'): string
{
    $ts = is_int($when) ? $when : (($when === null || $when === '') ? time() : strtotime((string) $when));
    if ($ts === false) return '';
    $lang = \Core\Lang::current();
    if (class_exists(\IntlDateFormatter::class)) {
        $pattern = match ($style) {
            'long' => $lang === 'de' ? 'd. MMMM y' : 'd MMMM y',
            'weekday' => 'EEEE',
            'day_month' => $lang === 'de' ? 'd. MMMM' : 'd MMMM',
            default => $lang === 'de' ? 'dd.MM.y' : 'dd/MM/y',
        };
        $f = new \IntlDateFormatter($lang, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, date_default_timezone_get(), null, $pattern);
        $out = $f->format($ts);
        if (is_string($out)) return $out;
    }
    $months = ['de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember']];
    $days = ['de' => ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag']];
    $m = isset($months[$lang]) ? $months[$lang][(int) date('n', $ts) - 1] : date('F', $ts);
    return match ($style) {
        'long' => date('j', $ts) . ($lang === 'de' ? '. ' : ' ') . $m . ' ' . date('Y', $ts),
        'weekday' => isset($days[$lang]) ? $days[$lang][(int) date('w', $ts)] : date('l', $ts),
        'day_month' => date('j', $ts) . ($lang === 'de' ? '. ' : ' ') . $m,
        default => date($lang === 'de' ? 'd.m.Y' : 'd/m/Y', $ts),
    };
}

function __(string $text, array $params = []): string
{
    return \Core\I18n::translate($text, $params);
}

/** Darf der angemeldete Benutzer das? can('pages.publish'), can('data.edit', 'aktuelles') */
function can(string $perm, ?string $table = null): bool
{
    return app()->auth->can($perm, $table);
}

/** Marke der Verwaltung: [Zeile 1, Zeile 2] aus project.brand (Einstellungsfelder) oder Website-Name */
function admin_brand(): array
{
    $b = (array) project('brand', []);
    $one = isset($b[0]) ? trim((string) setting($b[0])) : '';
    $two = isset($b[1]) ? trim((string) setting($b[1])) : '';
    return [$one !== '' ? $one : site_name(), $two];
}

/**
 * Sprachumschalter für Themes: [['code' => 'de', 'label' => 'Deutsch', 'url' => …, 'active' => bool, 'exists' => bool], …]
 * Gibt es die aktuelle Seite in einer Sprache nicht, führt der Link zur Startseite dieser Sprache.
 */
function language_links(): array
{
    if (!\Core\Lang::multi()) return [];
    $cur = app()->currentPage;
    $ctx = app()->entry;
    $trans = $cur && !empty($cur['id']) && ($cur['type'] ?? 'page') === 'page' ? \Core\Pages::translations($cur) : [];
    $out = [];
    foreach (\Core\Lang::all() as $code => $label) {
        $p = $trans[$code] ?? null;
        $exists = $p && $p['status'] === 'published';
        $url = $exists ? \Core\Pages::url($p) : url(\Core\Lang::prefix($code) . '/');
        // Landing-Domain ohne Übersetzung: Startseite dieser Sprache auf der Hauptdomain
        if (!$exists && \Core\Landings::current() && !\Core\Landings::current()->root($code) && ($h = \Core\Pages::home($code))) $url = \Core\Pages::url($h);
        if ($ctx) {
            $tr = \Core\Data\Entries::translations($ctx['table'], $ctx['entry'])[$code] ?? null;
            $exists = (bool) $tr;
            $url = $tr ? (string) \Core\Data\Entries::url($ctx['table'], $tr) : url(\Core\Lang::prefix($code) . '/');
        }
        $out[] = ['code' => $code, 'label' => $label, 'url' => $url, 'active' => $code === \Core\Lang::current(), 'exists' => $exists];
    }
    return $out;
}
