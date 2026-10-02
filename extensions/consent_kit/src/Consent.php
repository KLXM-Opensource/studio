<?php
// SPDX-License-Identifier: MIT
// Portions ported from FriendsOfREDAXO/consent_kit (MIT, © KLXM Crossmedia GmbH): Consent, Frontend
declare(strict_types=1);

namespace MyCms\Consent;

use Core\Extensions;
use Core\Features;
use Core\Links;

/**
 * Einstieg der Erweiterung für Website, CSP und Kits.
 *
 * Grundsätze (wie der Kern): Besucher erhalten keine Cookies und keine Tracker, solange sie nicht zugestimmt haben;
 * ohne aktiven einwilligungspflichtigen Dienst gibt es weder Hinweis noch Skript. Der Cookie „cms_consent“ entsteht
 * erst durch eine Entscheidung (JavaScript + Bestätigung per Set-Cookie vom Server). Kein Inline-Script: Konfiguration
 * als JSON-Datenblock, Code der Dienste als Dateien von der eigenen Domain, fremde Hosts erst nach Einwilligung in der CSP.
 */
final class Consent
{
    public const COOKIE = 'cms_consent';
    public const DISMISSED = 'cms_consent_dismissed';

    private static ?array $state = null;
    private static bool $stateRead = false;

    public static function enabled(): bool
    {
        return Extensions::isActive('consent_kit') && Features::on('consent', false) && Repository::ready();
    }

    public static function flush(): void
    {
        Compiler::flush();
        self::$stateRead = false;
    }

    /** Gibt es auf der Domain dieser Anfrage mindestens einen aktiven, vollständigen, einwilligungspflichtigen Dienst? */
    public static function needed(?array $build = null): bool
    {
        if (!self::enabled()) return false;
        $build ??= Compiler::build();
        foreach ($build['services'] as $c) if ($c['grp'] !== 'necessary') return true;
        return false;
    }

    // ------------------------------------------------------------------ Website

    /** HTML-Filter (vor dem Seiten-Cache): Konfiguration als JSON + ein Skript – nur wenn ein Dienst Einwilligung braucht */
    public static function filterHtml(string $html, array $ctx): string
    {
        if (!self::enabled() || !empty($ctx['editing']) || !str_contains($html, '</head>')) return $html;
        $b = Compiler::build();
        if (!self::needed($b)) return $html;
        $cfg = self::config($b, $ctx['page'] ?? null);
        // Nur für Angemeldete (deren Seiten nie im Cache landen): Platzhalter nennen Dienste, die fehlen oder inaktiv sind
        if (!empty($ctx['loggedIn']) && can('consent.manage')) {
            $cfg['editorHint'] = Compiler::t('Nur für Redaktion sichtbar: Der Dienst „{0}“ ist nicht angelegt oder auf dieser Domain inaktiv. Anlegen bzw. aktivieren unter Verwaltung → Cookie-Einwilligung.', $b['lang']);
        }
        $tags = '<script type="application/json" id="cms-consent-config">'
            . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n"
            . '<script src="' . e(self::asset('js/consent.js')) . '" defer></script>' . "\n";
        // Vor dem ersten Skript bzw. Stylesheet im <head>: läuft damit vor den (ebenfalls verzögerten) Kit-Skripten
        $head = strpos($html, '</head>');
        $first = null;
        foreach (['<script', '<link rel="stylesheet"'] as $needle) {
            $p = strpos($html, $needle);
            if ($p !== false && $p < $head && ($first === null || $p < $first)) $first = $p;
        }
        $at = $first ?? $head;
        return substr($html, 0, $at) . $tags . substr($html, $at);
    }

    /** Frontend-Konfiguration (steht im gecachten HTML – nichts Besucherspezifisches) */
    public static function config(array $b, ?array $page = null): array
    {
        $s = Repository::settings();
        $lang = $b['lang'];
        $t = fn(string $x, array $p = []) => Compiler::t($x, $lang, $p);
        $links = [];
        $quiet = false;
        foreach (['privacy' => 'Datenschutzerklärung', 'imprint' => 'Impressum'] as $k => $label) {
            [$href, $pageId] = self::legal($k);
            if ($href) $links[] = ['key' => $k, 'label' => $t($label), 'url' => $href];
            if ($pageId && $page && (int) ($page['id'] ?? 0) === $pageId) $quiet = true;
        }
        $groups = array_map(function ($g) {
            $g['services'] = array_map(fn($c) => array_diff_key($c, array_flip(['_chunks', '_accept', '_revoke', 'default', 'grp'])), $g['services']);
            return $g;
        }, $b['groups']);
        $dark = (array) (\Core\Design::def()['dark'] ?? []);
        return [
            'v' => substr($b['hash'], 0, 10), 'rev' => Compiler::revision($b), 'epoch' => Repository::epoch(),
            'cookie' => self::COOKIE, 'dismissKey' => self::DISMISSED, 'days' => max(1, min(400, (int) $s['days'])), 'lang' => $lang,
            'endpoint' => url('/consent/save'), 'code' => url('/consent/js/'),
            'ui' => self::asset('js/consent-ui.mjs'), 'css' => url('/consent/style.css') . '?v=' . Design::version(),
            'layout' => (string) $s['layout'], 'position' => (string) $s['position'],
            'theme' => (string) $s['theme'], 'darkScope' => $s['theme'] === 'site' ? (string) ($dark['scope'] ?? '') : '',
            'dismiss' => (bool) $s['dismiss'], 'trigger' => (bool) $s['trigger'], 'reload' => (bool) $s['reload'],
            'bannerGroups' => (bool) $s['banner_groups'] && in_array($s['layout'], ['modal', 'offcanvas'], true),
            'gpc' => (string) $s['gpc'], 'quiet' => $quiet,
            'gcm' => $b['gcm'] ? ['redaction' => (bool) $s['gcm_redaction'], 'passthrough' => (bool) $s['gcm_passthrough'], 'wait' => max(0, (int) $s['gcm_wait'])] : null,
            'def' => $b['default'] !== '',
            'links' => $links, 'embeds' => (object) $b['embeds'],
            'texts' => self::texts($lang), 'groups' => $groups,
        ];
    }

    /** Datenschutz-/Impressum-Link: Einstellung (Linkauswahl) → Seite aus den Website-Angaben des Kits. @return array{0: ?string, 1: ?int} */
    public static function legal(string $which): array
    {
        $v = trim((string) (Repository::settings()[$which] ?? ''));
        if ($v === '') {
            $keys = $which === 'privacy' ? ['privacy_page', 'datenschutz_seite'] : ['imprint_page', 'impressum_seite'];
            foreach ($keys as $k) if ((int) setting($k)) { $v = 'page:' . (int) setting($k); break; }
        }
        if ($v === '') return [null, null];
        if (preg_match('~^page:(\d+)$~', $v, $m) && ($p = \Core\Pages::find((int) $m[1]))) {
            // Übersetzung der Seite in der Sprache der Anfrage (wie die Rechtliches-Links der Kits)
            if (\Core\Lang::multi()) $p = \Core\Pages::translations($p)[\Core\Lang::current()] ?? $p;
            return [\Core\Pages::url($p), (int) $p['id']];
        }
        if (Links::isRef($v)) {
            $page = preg_match('~^page:(\d+)~', $v, $m) ? (int) $m[1] : null;
            return [Links::href($v), $page];
        }
        return [\Core\Sanitizer::safeHref($v), null];
    }

    public static function texts(string $lang): array
    {
        $t = fn(string $x) => Compiler::t($x, $lang);
        return [
            'title' => $t('Datenschutz-Einstellungen'),
            'intro' => $t('Wir verwenden Cookies und ähnliche Technologien. Einige sind für den Betrieb der Website notwendig, andere werden nur mit Ihrer Einwilligung eingesetzt. Sie können Ihre Auswahl jederzeit über „Cookie-Einstellungen“ ändern oder widerrufen.'),
            'accept_all' => $t('Alle akzeptieren'), 'reject_all' => $t('Alle ablehnen'), 'settings' => $t('Einstellungen'), 'save' => $t('Auswahl speichern'),
            'close' => $t('Schließen, ohne zu entscheiden'), 'settings_title' => $t('Dienste auswählen'),
            'settings_intro' => $t('Hier können Sie jeden Dienst einzeln zulassen oder ablehnen. Notwendige Dienste sind immer aktiv.'),
            'always_active' => $t('Immer aktiv'), 'group_toggle' => $t('Alle Dienste der Gruppe „{name}“'),
            'services_count' => $t('{n} Dienste'), 'services_count_one' => $t('1 Dienst'),
            'show_details' => $t('Details anzeigen'), 'hide_details' => $t('Details ausblenden'),
            'provider' => $t('Anbieter'), 'privacy_policy' => $t('Datenschutzerklärung'), 'privacy_policy_of' => $t('Datenschutzerklärung von {name}'),
            'storage' => $t('Cookies und Speichereinträge'), 'no_items' => $t('Für diesen Dienst sind keine Einträge hinterlegt.'),
            'col_name' => $t('Name'), 'col_type' => $t('Art'), 'col_host' => $t('Domain'), 'col_duration' => $t('Laufzeit'), 'col_purpose' => $t('Zweck'),
            'trigger' => $t('Cookie-Einstellungen'), 'consent_info' => $t('Einwilligungs-ID: {id} · gespeichert am {date}'),
            'withdraw' => $t('Einwilligung widerrufen'),
            'gpc_notice' => $t('Ihr Browser sendet das Signal „Global Privacy Control“. Optionale Dienste wurden deshalb nicht aktiviert.'),
            'embed_title' => $t('Externer Inhalt von {name}'),
            'embed_text' => $t('Zum Anzeigen dieses Inhalts werden Daten an {name} übertragen. Details finden Sie in den Cookie-Einstellungen.'),
            'embed_once' => $t('Inhalt einmal laden'), 'embed_always' => $t('{name} immer erlauben'), 'embed_settings' => $t('Cookie-Einstellungen öffnen'),
            'embed_unavailable' => $t('Dieser Inhalt ist derzeit nicht verfügbar.'), 'imprint' => $t('Impressum'),
            'new_tab' => $t('(öffnet in neuem Tab)'),
        ];
    }

    /** Öffentliche Adresse einer Datei der Erweiterung (public/assets/ext/consent_kit, nach pnpm build; ältere Kerne: /extensions/…) */
    public static function asset(string $path): string
    {
        $x = Extensions::active()['consent_kit'] ?? null;
        if ($x) return $x->asset($path);
        return class_exists(\Core\PublicPaths::class) ? \Core\PublicPaths::url('ext', 'consent_kit', $path) : base_path() . '/extensions/consent_kit/' . $path;
    }

    // ------------------------------------------------------------------ Einwilligung (Cookie dieser Anfrage)

    /** Inhalt des Cookies (nur gültige Epoche): id, e, rev, ts, a (akzeptiert: key => Fingerabdruck), r (abgelehnt) */
    public static function state(): ?array
    {
        if (self::$stateRead) return self::$state;
        self::$stateRead = true;
        self::$state = null;
        $raw = $_COOKIE[self::COOKIE] ?? null;
        if (!is_string($raw) || strlen($raw) > 4000) return null;
        $d = json_decode($raw, true);
        if (!is_array($d) || !is_string($d['id'] ?? null) || (int) ($d['e'] ?? -1) !== Repository::epoch()) return null;
        return self::$state = ['id' => $d['id'], 'e' => (int) $d['e'], 'rev' => (int) ($d['rev'] ?? 0), 'ts' => (int) ($d['ts'] ?? 0),
            'a' => array_map('strval', (array) ($d['a'] ?? [])), 'r' => array_map('strval', (array) ($d['r'] ?? []))];
    }

    /** Einwilligung in den Dienst (in seiner aktuellen Fassung) – notwendige Dienste: immer */
    public static function has(string $key): bool
    {
        if (!self::enabled()) return false;
        $c = Compiler::build()['services'][$key] ?? null;
        if (!$c) return false;
        if ($c['grp'] === 'necessary') return true;
        $st = self::state();
        return $st !== null && ($st['a'][$key] ?? null) === $c['h'];
    }

    /**
     * CSP-Quellen dieser Anfrage: iframe-Hosts aktiver Dienste (für den 2-Klick-Platzhalter „einmal laden“),
     * alle übrigen Hosts eines Dienstes erst, wenn der Cookie die Einwilligung in seine aktuelle Fassung enthält.
     */
    public static function cspSources(): array
    {
        if (!self::enabled() || app()->request?->isAdminPath()) return [];
        $out = [];
        $st = self::state();
        foreach (Compiler::languages() as $lang) {
            $b = Compiler::build(null, $lang);
            foreach ($b['services'] as $key => $c) {
                foreach ($c['embed'] as $h) {
                    $h = strtolower(trim((string) $h));
                    if ($h === '') continue;
                    $out['frame-src'][] = 'https://' . $h;
                    if (substr_count($h, '.') === 1) $out['frame-src'][] = 'https://*.' . $h;
                }
                $consented = $c['grp'] === 'necessary' || ($st !== null && ($st['a'][$key] ?? null) === $c['h']);
                if (!$consented) continue;
                foreach (['script' => 'script-src', 'connect' => 'connect-src', 'img' => 'img-src', 'frame' => 'frame-src'] as $k => $dir) {
                    foreach ($c['hosts'][$k] ?? [] as $h) $out[$dir][] = $h;
                }
            }
        }
        return array_map(fn($l) => array_values(array_unique($l)), $out);
    }

    // ------------------------------------------------------------------ Kits

    public static function footerLinks(): array
    {
        if (!self::enabled() || !Repository::settings()['footer_link'] || !self::needed()) return [];
        return [['label' => lt('Cookie-Einstellungen'), 'href' => '#cookie-einstellungen']];
    }

    public static function settingsLink(string $label = '', string $class = ''): string
    {
        if (!self::needed()) return '';
        return '<a href="#cookie-einstellungen" data-consent-open' . ($class !== '' ? ' class="' . e($class) . '"' : '') . '>' . e($label !== '' ? $label : lt('Cookie-Einstellungen')) . '</a>';
    }

    /** Markup in <consent-embed> verpacken: erst nach Einwilligung (oder „einmal laden“) gelangt es in die Seite */
    public static function embed(string $service, string $html, array $o = []): string
    {
        if (!self::enabled()) return '';
        $b = Compiler::build();
        $c = $b['services'][$service] ?? null;
        // Nicht angelegt oder inaktiv: Platzhalter „nicht verfügbar“ ohne Inhalt – Name aus der gleichnamigen Vorlage
        if (!$c) return '<consent-embed service="' . e($service) . '"' . (($p = Presets::get($service)) ? ' name="' . e((string) $p['name']) . '"' : '') . '></consent-embed>';
        $title = trim((string) ($o['title'] ?? ''));
        $ratio = preg_match('~^\d{1,2}\s*/\s*\d{1,2}$~', (string) ($o['ratio'] ?? '')) ? (string) $o['ratio'] : '16/9';
        $link = trim((string) ($o['link'] ?? ''));
        return '<consent-embed service="' . e($service) . '"' . ($title !== '' ? ' label="' . e($title) . '"' : '') . ' ratio="' . e($ratio) . '">'
            . '<template>' . $html . '</template>'
            . ($link !== '' && ($safe = \Core\Sanitizer::safeHref($link)) ? '<noscript><a href="' . e($safe) . '" rel="noopener" target="_blank">' . e($title !== '' ? $title : $c['name']) . '</a></noscript>' : '')
            . '</consent-embed>';
    }

    /** Block „Externer Inhalt (mit Einwilligung)“ */
    public static function blockDefinition(): array
    {
        $opts = [];
        try {
            foreach (Repository::services(true) as $s) {
                if ($s['embed_hosts'] && $s['grp'] !== 'necessary') $opts[$s['skey']] = $s['name'];
            }
        } catch (\Throwable) {
        }
        return [
            'label' => 'Externer Inhalt (mit Einwilligung)', 'icon' => 'cookie', 'group' => 'Medien',
            'help' => 'Karte, Beitrag, Podcast oder Terminbuchung eines Anbieters als iframe – erst nach Einwilligung bzw. Klick („2-Klick-Lösung“). Anbieter anlegen unter Verwaltung → Cookie-Einwilligung (Dienst mit „Domains eingebetteter Inhalte“). YouTube/Vimeo: Block „Video“.',
            'fields' => [
                ['name' => 'title', 'label' => 'Überschrift (optional)', 'type' => 'text', 'width' => 'half'],
                ['name' => 'service', 'label' => 'Dienst', 'type' => 'select', 'required' => true, 'width' => 'half', 'options' => $opts ?: ['' => '– zuerst einen Dienst anlegen –']],
                ['name' => 'url', 'label' => 'Adresse zum Einbetten (https://…)', 'type' => 'url',
                    'help' => 'Die Embed-Adresse des Anbieters (z. B. „Karte teilen → Karte einbetten“ bei Google Maps). Der Host muss beim Dienst unter „Domains eingebetteter Inhalte“ stehen.'],
                ['name' => 'label', 'label' => 'Beschreibung für Screenreader (Titel des iframes)', 'type' => 'text', 'required' => true, 'width' => 'half'],
                ['name' => 'ratio', 'label' => 'Format', 'type' => 'select', 'default' => '16/9', 'width' => 'half', 'required' => true,
                    'options' => ['16/9' => '16:9', '4/3' => '4:3', '1/1' => '1:1', '3/4' => '3:4 (hoch)', '9/16' => '9:16 (Kurzvideo)']],
                ['name' => 'caption', 'label' => 'Bildunterschrift (optional)', 'type' => 'text', 'max' => 200],
            ],
        ];
    }

    /** Passt der Host der URL zu den Einbettungs-Domains des Dienstes? (Subdomains zählen mit) */
    public static function urlFits(string $url, array $hosts): bool
    {
        $h = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($h === '' || !str_starts_with(strtolower($url), 'https://')) return false;
        foreach ($hosts as $x) {
            $x = strtolower(trim((string) $x));
            if ($x !== '' && ($h === $x || str_ends_with($h, '.' . $x))) return true;
        }
        return false;
    }

    // ------------------------------------------------------------------ Status (CLI, Verwaltung)

    public static function statusLines(): array
    {
        $out = ['Website: ' . site()->key . ' · Erweiterung ' . (Extensions::isActive('consent_kit') ? 'aktiv' : 'aus') . ' · Funktion „consent“ ' . (Features::on('consent', false) ? 'an' : 'aus')];
        if (!self::enabled()) return $out;
        foreach (Compiler::domains() as $d => $label) {
            $b = Compiler::build($d);
            $out[] = "Domain $label: " . count($b['services']) . ' aktive Dienste' . (self::needed($b) ? ' – Hinweis wird angezeigt' : ' – kein Hinweis (nichts einwilligungspflichtig)');
            foreach ($b['services'] as $k => $c) {
                $hosts = array_values(array_unique(array_merge(...array_values($c['hosts']))));
                $out[] = sprintf('  %-22s %-12s fp=%s  CSP nach Einwilligung: %s', $k, $c['grp'], $c['h'], $hosts ? implode(' ', $hosts) : "nur 'self'");
            }
        }
        $out[] = 'Protokoll: ' . Log::count() . ' Einträge, Aufbewahrung ' . ((int) Repository::settings()['retention'] ?: '∞') . ' Tage';
        return $out;
    }
}
