<?php
// SPDX-License-Identifier: MIT
// Portions ported from FriendsOfREDAXO/consent_kit (MIT, © KLXM Crossmedia GmbH): Cache::build/applyVariants/resolveParams/serviceHash, Events::build
declare(strict_types=1);

namespace MyCms\Consent;

use Core\Landings;
use Core\Lang;

/**
 * Baut aus den Diensten die Auslieferung für eine Domain und Sprache:
 *  - Varianten (Domain und/oder Sprache, die genaueste gewinnt) und Platzhalter {{param}}, {{lang}}, {{domain}}
 *  - Code-Felder → CSP-taugliche Schritte: externe <script src> bleiben extern, Inline-Code wird als Datei von der
 *    eigenen Domain ausgeliefert (/consent/js/{dienst}.{teil}.js) – nie als Inline-Script
 *  - CSP-Hosts je Dienst (aus Code, URL-Angaben, Vorlage und „Erweitert“), Fingerabdruck, Stand (Revision)
 * Domains: 'main' (Hauptdomain der Website) und 'landing:{id}' (Landingpages mit eigener Domain, Core\Landings).
 */
final class Compiler
{
    private static array $memo = [];

    public static function flush(): void
    {
        self::$memo = [];
    }

    /** Domain der aktuellen Anfrage */
    public static function domainId(): string
    {
        $l = Landings::current();
        return $l ? 'landing:' . $l->id : 'main';
    }

    /** Domains der Website für die Matrix: [id => Bezeichnung] */
    public static function domains(): array
    {
        $out = ['main' => __('Hauptdomain') . ' (' . (parse_url(Landings::mainOrigin(), PHP_URL_HOST) ?: site()->key) . ')'];
        if (Landings::enabled()) {
            foreach (Landings::all() as $l) {
                if ($l->active && $l->hosts) $out['landing:' . $l->id] = $l->label . ' (' . $l->hosts[0] . ')';
            }
        }
        return $out;
    }

    /** Host der Domain (für {{domain}}, ohne Port) */
    public static function hostOf(string $domain): string
    {
        if (str_starts_with($domain, 'landing:') && ($l = Landings::find((int) substr($domain, 8))) && $l->hosts) {
            return (string) preg_replace('~:\d+$~', '', $l->hosts[0]);
        }
        $h = parse_url(Landings::mainOrigin(), PHP_URL_HOST) ?: (app()->request?->host() ?? '');
        return (string) preg_replace(['~:\d+$~', '~^www\.~'], '', (string) $h);
    }

    /** Sprachen der Website (Codes) */
    public static function languages(): array
    {
        $all = array_keys((array) Lang::all());
        return $all ?: [Lang::default()];
    }

    /** Dienst gilt auf der Domain? (leer = alle) */
    public static function onDomain(array $s, string $domain): bool
    {
        return !$s['hosts'] || in_array($domain, $s['hosts'], true);
    }

    /**
     * Auslieferung für Domain + Sprache.
     * @return array{services: array<string, array>, groups: list<array>, hash: string, gcm: list<string>, default: string, embeds: array<string,string>}
     */
    public static function build(?string $domain = null, ?string $lang = null): array
    {
        $domain ??= self::domainId();
        $lang = Lang::norm($lang ?? Lang::current()) ?: Lang::default();
        $mk = "$domain|$lang";
        if (isset(self::$memo[$mk])) return self::$memo[$mk];

        $services = [];
        $defaults = [];
        $gcm = [];
        foreach (Repository::services(true) as $s) {
            if (!self::onDomain($s, $domain)) continue;
            $c = self::compile($s, $domain, $lang);
            if ($c === null) continue;   // unvollständig: nie ausliefern
            if ($c['default'] !== '') $defaults[] = "/* {$s['skey']} */\n" . $c['default'];
            $gcm = array_merge($gcm, $c['gcm']);
            $services[$s['skey']] = $c;
        }
        // Gruppen (nur mit Diensten); „Notwendig“ enthält immer den eigenen Eintrag (Cookie/Speicher des Hinweises)
        $groups = [];
        foreach (Repository::GROUPS as $gk => [$gname, $gdesc, $required]) {
            $list = array_values(array_filter($services, fn($c) => $c['grp'] === $gk));
            if ($gk === 'necessary') array_unshift($list, self::own($lang));
            if (!$list) continue;
            $groups[] = ['key' => $gk, 'name' => self::t($gname, $lang), 'description' => self::t($gdesc, $lang), 'required' => $required, 'services' => $list];
        }
        $hashes = [];
        foreach ($services as $k => $c) $hashes[$k] = $c['h'];
        ksort($hashes);
        $hash = sha1((string) json_encode([$hashes, Repository::epoch()]));

        $embeds = [];
        foreach ($services as $k => $c) {
            foreach (['youtube' => ['youtube.com', 'youtube-nocookie.com'], 'vimeo' => ['vimeo.com', 'player.vimeo.com']] as $prov => $hosts) {
                if ($k === $prov || array_intersect($hosts, $c['embed'])) $embeds[$prov] ??= $k;
            }
        }
        return self::$memo[$mk] = ['services' => $services, 'groups' => $groups, 'hash' => $hash,
            'gcm' => array_values(array_unique($gcm)), 'default' => implode("\n", $defaults), 'embeds' => $embeds, 'domain' => $domain, 'lang' => $lang];
    }

    /** Stand (Revision) der Auslieferung – legt bei geänderten einwilligungsrelevanten Angaben einen neuen Schnappschuss an */
    public static function revision(array $b): int
    {
        // Schnappschuss ohne Code: was stand zur Auswahl (Nachweis im Protokoll)
        $snapshot = array_map(fn($g) => ['key' => $g['key'], 'name' => $g['name'], 'required' => $g['required'],
            'services' => array_map(fn($c) => array_intersect_key($c, array_flip(['key', 'name', 'provider', 'privacyUrl', 'description', 'items', 'h'])), $g['services'])], $b['groups']);
        return Repository::revision($b['domain'], $b['hash'], ['lang' => $b['lang'], 'epoch' => Repository::epoch(), 'groups' => $snapshot]);
    }

    /** Eigener Eintrag in „Notwendig“: Cookie und Sitzungsspeicher des Hinweises */
    private static function own(string $lang): array
    {
        $days = (int) Repository::settings()['days'];
        return ['key' => '_consent', 'grp' => 'necessary', 'h' => '', 'name' => self::t('Cookie-Einwilligung', $lang), 'provider' => '', 'privacyUrl' => '',
            'description' => self::t('Speichert Ihre Auswahl zu Diensten und Cookies – erst nachdem Sie eine Auswahl getroffen haben.', $lang),
            'items' => [
                ['type' => 'cookie', 'typeLabel' => 'Cookie', 'name' => Consent::COOKIE, 'host' => '', 'duration' => self::duration($days, 'days', $lang),
                    'purpose' => self::t('Enthält die Einwilligungs-ID, den Stand der Konfiguration und die gewählten Dienste.', $lang)],
                ['type' => 'session_storage', 'typeLabel' => 'Session Storage', 'name' => Consent::DISMISSED, 'host' => '', 'duration' => self::duration(0, 'session', $lang),
                    'purpose' => self::t('Merkt sich bis zum Schließen des Browsers, dass der Hinweis ohne Entscheidung geschlossen wurde.', $lang)],
            ], 'gcm' => [], 'embed' => [], 'x' => false, 'head' => [], 'body' => [], 'acc' => false, 'rev' => false, 'events' => false, 'v' => '', 'hosts' => []];
    }

    /** Dienst für Domain + Sprache auflösen; null = unvollständig (offener Platzhalter) */
    public static function compile(array $s, string $domain, string $lang): ?array
    {
        $s = self::applyVariants($s, $domain, $lang);
        $events = Events::build($s['events'], Presets::events($s['preset'] ?: null));
        $code = self::resolveParams($s, $events);
        if ($code === null) return null;

        $head = self::steps($code['html_head']);
        $body = self::steps($code['html_body']);
        $chunks = array_merge($head['chunks'], $body['chunks']);
        // Nummerierung der Code-Teile über head und body hinweg
        $n = count($head['chunks']);
        $body['steps'] = array_map(fn($st) => isset($st['c']) ? ['c' => $st['c'] + $n] : $st, $body['steps']);
        $accept = trim($code['js_accept'] . ($code['js_events'] !== '' ? "\n" . $code['js_events'] : ''));

        // CSP-Hosts: aus dem Code und aus URL-Angaben, dazu Vorlage/„Erweitert“ – eigene Domain zählt nicht
        $found = self::hostsIn(implode("\n", array_merge(array_values($code), array_values(array_filter($s['params'], 'is_string')))));
        $csp = ['script' => [], 'connect' => [], 'img' => [], 'frame' => []];
        foreach ($found as $h) { $csp['script'][] = $h; $csp['connect'][] = $h; $csp['img'][] = $h; }
        foreach ($csp as $k => $_) $csp[$k] = array_values(array_unique(array_merge($csp[$k], array_map('strval', (array) ($s['csp'][$k] ?? [])))));
        $external = (bool) ($csp['script'] || $csp['connect'] || $csp['img']);

        $items = [];
        foreach ($s['items'] as $i) {
            $items[] = ['type' => $i['type'], 'typeLabel' => self::t(Repository::ITEM_TYPES[$i['type']] ?? $i['type'], $lang), 'name' => $i['name'], 'host' => $i['host'],
                'duration' => self::duration((int) $i['value'], (string) $i['unit'], $lang), 'purpose' => self::pick(['de' => $i['purpose_de'] ?? '', 'en' => $i['purpose_en'] ?? ''], $lang)];
        }
        $h = substr(sha1((string) json_encode([$s['grp'], Repository::GROUPS[$s['grp']][2] ?? false, $s['skey'], $s['provider'],
            array_map(fn($i) => [$i['type'], $i['name'], $i['host']], $s['items'])])), 0, 6);
        $v = substr(sha1((string) json_encode([$code, $chunks, $head['steps'], $body['steps']])), 0, 10);

        return [
            'key' => $s['skey'], 'grp' => $s['grp'], 'h' => $h, 'v' => $v,
            'name' => $s['name'], 'provider' => $s['provider'], 'privacyUrl' => $s['privacy_url'],
            'description' => self::pick($s['description'], $lang), 'items' => $items,
            'gcm' => array_values($s['gcm']), 'embed' => array_values($s['embed_hosts']),
            'head' => $head['steps'], 'body' => $body['steps'], 'acc' => $accept !== '', 'rev' => trim($code['js_revoke']) !== '',
            'x' => $external, 'hosts' => $csp,
            // nur serverseitig (für /consent/js/…, nicht in der Konfiguration im HTML)
            '_chunks' => $chunks, '_accept' => $accept, '_revoke' => trim($code['js_revoke']), 'default' => trim($code['js_default']),
        ];
    }

    /** Varianten: erst allgemein, dann nur Sprache, dann nur Domain, zuletzt Domain + Sprache – die spezifischste gewinnt */
    public static function applyVariants(array $s, string $domain, string $lang): array
    {
        $matches = [];
        foreach ($s['variants'] as $v) {
            $d = (string) ($v['domain'] ?? '');
            $l = (string) ($v['lang'] ?? '');
            if (($d === '' || $d === $domain) && ($l === '' || $l === $lang || $l === substr($lang, 0, 2))) {
                $matches[] = [($d === '' ? 0 : 2) + ($l === '' ? 0 : 1), $v];
            }
        }
        usort($matches, fn($a, $b) => $a[0] <=> $b[0]);
        foreach ($matches as [, $v]) {
            $s['params'] = array_merge($s['params'], array_filter((array) ($v['params'] ?? []), fn($x) => trim((string) $x) !== ''));
            foreach (Repository::CODE_FIELDS as $f) {
                if (trim((string) ($v[$f] ?? '')) !== '') $s[$f] = (string) $v[$f];
            }
        }
        $s['params'] += ['lang' => substr($lang, 0, 2), 'domain' => self::hostOf($domain)];
        return $s;
    }

    /** {{param}} ersetzen; bleibt ein Platzhalter offen, ist der Dienst unvollständig → null */
    public static function resolveParams(array $s, string $events = ''): ?array
    {
        $replace = [];
        foreach ($s['params'] as $k => $v) {
            if (is_scalar($v) && trim((string) $v) !== '') $replace['{{' . $k . '}}'] = trim((string) $v);
        }
        $out = [];
        foreach ([...Repository::CODE_FIELDS, 'js_events'] as $f) {
            $val = strtr($f === 'js_events' ? $events : (string) ($s[$f] ?? ''), $replace);
            if (preg_match('~\{\{[a-z0-9_]+\}\}~i', $val)) return null;
            $out[$f] = $val;
        }
        return $out;
    }

    /** Fehlt dem Dienst auf einer seiner Domains/Sprachen eine Angabe? (Marke „Unvollständig“) */
    public static function isComplete(array $s): bool
    {
        foreach (array_keys(self::domains()) as $d) {
            if (!self::onDomain($s, $d)) continue;
            foreach (self::languages() as $l) {
                $v = self::applyVariants($s, $d, $l);
                if (self::resolveParams($v, Events::build($v['events'], Presets::events($v['preset'] ?: null))) === null) return false;
            }
        }
        return true;
    }

    /**
     * HTML eines Code-Felds → Schritte in Reihenfolge:
     *   ['s' => src, 'a' => [attr => wert]]  externes Script (Reihenfolge bleibt, außer async)
     *   ['c' => n]                            Inline-Code als eigene Datei (aufeinanderfolgende Inline-Scripts zusammengefasst)
     *   ['h' => html]                         sonstiges HTML (ohne Scripts, <noscript> und Kommentare entfallen)
     * @return array{steps: list<array>, chunks: list<string>}
     */
    public static function steps(string $html): array
    {
        $steps = [];
        $chunks = [];
        $html = (string) preg_replace(['~<!--.*?-->~s', '~<noscript\b.*?</noscript\s*>~is'], '', $html);
        $pos = 0;
        $pending = null;
        $flush = function () use (&$pending, &$steps, &$chunks) {
            if ($pending !== null && trim($pending) !== '') { $chunks[] = trim($pending); $steps[] = ['c' => count($chunks) - 1]; }
            $pending = null;
        };
        if (preg_match_all('~<script\b([^>]*)>(.*?)</script\s*>~is', $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $match) {
                $between = trim(substr($html, $pos, $match[0][1] - $pos));
                $pos = $match[0][1] + strlen($match[0][0]);
                if ($between !== '') { $flush(); $steps[] = ['h' => $between]; }
                $attrs = self::attrs($match[1][0]);
                $type = strtolower($attrs['type'] ?? '');
                if ($type !== '' && !in_array($type, ['text/javascript', 'application/javascript', 'module'], true)) continue;   // z. B. JSON-LD
                if (isset($attrs['src'])) {
                    $flush();
                    $src = html_entity_decode($attrs['src'], ENT_QUOTES);
                    if (str_starts_with($src, '//')) $src = 'https:' . $src;
                    $a = array_intersect_key($attrs, array_flip(['async', 'defer', 'id', 'crossorigin', 'charset', 'referrerpolicy', 'type', 'nomodule']))
                        + array_filter($attrs, fn($k) => str_starts_with($k, 'data-'), ARRAY_FILTER_USE_KEY);
                    unset($a['type']);
                    if ($type === 'module') $a['type'] = 'module';
                    $steps[] = ['s' => $src] + ($a ? ['a' => $a] : []);
                } else {
                    $pending = ($pending === null ? '' : $pending . "\n;\n") . $match[2][0];
                }
            }
        }
        $flush();
        $rest = trim(substr($html, $pos));
        if ($rest !== '') $steps[] = ['h' => $rest];
        return ['steps' => $steps, 'chunks' => $chunks];
    }

    private static function attrs(string $s): array
    {
        $out = [];
        preg_match_all('~([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?~', $s, $m, PREG_SET_ORDER);
        foreach ($m as $a) $out[strtolower($a[1])] = $a[2] !== '' ? $a[2] : (($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? ''));
        return $out;
    }

    /** Fremde Hosts im Code (https://host, //host) als CSP-Quellen; die eigene Domain und Landing-Domains zählen nicht */
    public static function hostsIn(string $code): array
    {
        preg_match_all('~(?:https?:)?//([a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+)(:\d+)?~i', $code, $m, PREG_SET_ORDER);
        $own = array_map(fn($h) => strtolower((string) preg_replace('~:\d+$~', '', $h)), array_merge(site()->hosts(), site()->landingHosts()));
        $out = [];
        foreach ($m as $x) {
            $h = strtolower($x[1]);
            if (in_array($h, $own, true) || str_ends_with($h, '.localhost') || $h === 'localhost' || !preg_match('~\.[a-z]{2,}$~', $h)) continue;
            $out[] = 'https://' . $h;
        }
        return array_values(array_unique($out));
    }

    // ------------------------------------------------------------------ Texte

    /** Fester Text in der Sprache $lang (lt() liest die Sprache der Anfrage) */
    public static function t(string $text, string $lang, array $params = []): string
    {
        $prev = app()->lang;
        app()->lang = $lang === Lang::default() ? null : $lang;
        try {
            return lt($text, $params);
        } finally {
            app()->lang = $prev;
        }
    }

    /** Übersetzbares Feld {de, en, …}: Sprache → Sprachanteil → Deutsch → Englisch → erster Wert */
    public static function pick(array|string $v, string $lang): string
    {
        if (is_string($v)) return $v;
        foreach ([$lang, substr($lang, 0, 2), 'de', 'en'] as $k) {
            if (trim((string) ($v[$k] ?? '')) !== '') return (string) $v[$k];
        }
        foreach ($v as $x) if (is_string($x) && trim($x) !== '') return $x;
        return '';
    }

    public static function duration(int $n, string $unit, string $lang): string
    {
        return match ($unit) {
            'session' => self::t('Sitzung', $lang),
            'persistent' => self::t('Unbegrenzt', $lang),
            'minutes' => $n === 1 ? self::t('1 Minute', $lang) : self::t('{n} Minuten', $lang, ['n' => $n]),
            'hours' => $n === 1 ? self::t('1 Stunde', $lang) : self::t('{n} Stunden', $lang, ['n' => $n]),
            'days' => $n === 1 ? self::t('1 Tag', $lang) : self::t('{n} Tage', $lang, ['n' => $n]),
            'months' => $n === 1 ? self::t('1 Monat', $lang) : self::t('{n} Monate', $lang, ['n' => $n]),
            'years' => $n === 1 ? self::t('1 Jahr', $lang) : self::t('{n} Jahre', $lang, ['n' => $n]),
            default => (string) $n,
        };
    }
}
