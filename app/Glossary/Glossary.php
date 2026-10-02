<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Glossary;

use Core\Data\Entries;
use Core\Data\Tables;
use Core\Features;
use Core\Lang;
use Core\PageCache;
use Core\Pages;

/**
 * Glossar (Funktion „glossary“, Standard aus): Fachbegriffe einer Website erklären.
 *
 *  - Daten: gewöhnliche Datentabelle „Glossar“ (Kurzname glossar) mit den Feldern begriff*, varianten (eine je Zeile bzw. durch
 *    Komma/Semikolon getrennt), kurz* (Klartext ≤ 240 Zeichen – Inhalt des Hinweisfensters), erklaerung (formatierter Text),
 *    kategorie, link (Mehr erfahren) + Status. Detailseiten unter /glossar/{slug} (Vorlage mit Block „Glossar“, Ansicht „Begriff“,
 *    DefinedTerm), Übersicht A–Z mit Buchstaben und Suchfilter (Block „Glossar“, DefinedTermSet). Anlegen: install() bzw.
 *    Verwaltung → Einstellungen → Glossar (bzw. Daten → Glossar → Prüfen & Einstellungen) → „Glossar einrichten“ oder php bin/console glossary:install.
 *  - Markieren: page() läuft auf dem fertigen HTML jeder Seite (SiteController::render vor dem Seiten-Cache, respond() für Seiten
 *    von Erweiterungen) – Regeln in Annotator. Nur veröffentlichte Begriffe; angemeldete Redaktion (Recht data.edit) sieht
 *    Entwürfe mit Hinweis „Entwurf“. Nie im Bearbeiten-Modus, nie auf der eigenen Detailseite des Begriffs.
 *  - Inhalte, die erst im Browser entstehen (z. B. Prüfergebnisse einer Erweiterung): Einstellung „Dynamische Bereiche“
 *    (CSS-Selektoren) bzw. data-glossary="live" – resources/js/glossary-live.mjs lädt /_glossary.json und markiert dort je Bereich.
 *  - Ausnehmen: data-glossary="off" an einem Element, Abschnitts-Option „Glossar-Begriffe hier nicht markieren“ (Tune noGlossary),
 *    Einstellung „Seiten ausnehmen“ (Pfade, * am Ende = Präfix).
 *  - Einstellungen (sys.glossary): mode (page|section|off), headings (h1…hN nicht markieren, Standard 3), live, exclude, page_id.
 *  - Mehrsprachig: Begriffe sind Einträge je Sprache (Übersetzung des Eintrags, Entries::translate). Eine Seite markiert nur die
 *    Begriffe ihrer Sprache (Wortendungen je Sprache, Annotator-Option lang); Detailseiten /en/glossar/{slug}; die Übersicht ist
 *    die Übersetzung der Glossar-Seite (z. B. /en/glossary) – ohne veröffentlichte Übersetzung kein Link auf die Übersicht.
 */
final class Glossary
{
    public const FEATURE = 'glossary';
    public const SET = 'sys.glossary';
    public const HANDLE = 'glossar';
    public const SHORT_MAX = 240;
    public const JSON_PATH = '/_glossary.json';
    public const MODES = ['page' => 'Erstes Vorkommen je Seite', 'section' => 'Erstes Vorkommen je Abschnitt', 'off' => 'Nicht automatisch markieren'];
    public const DEFAULTS = ['mode' => 'page', 'headings' => 3, 'live' => '', 'exclude' => '', 'page_id' => null];

    /** Wurde die Seite dieser Anfrage schon bearbeitet (bzw. aus dem Seiten-Cache geliefert)? – SiteController::respond() */
    public static bool $done = false;
    private static array $cache = [];

    // ================================================================= Zustand & Einstellungen

    public static function enabled(): bool
    {
        try {
            return Features::on(self::FEATURE, false);
        } catch (\Throwable) {
            return false;
        }
    }

    public static function settings(): array
    {
        $s = (array) app()->settings->get(self::SET, []);
        return array_replace(self::DEFAULTS, array_intersect_key($s, self::DEFAULTS));
    }

    /** Einstellungen aus dem Formular prüfen und speichern */
    public static function saveSettings(array $in): array
    {
        $s = self::settings();
        $s['mode'] = isset(self::MODES[$in['mode'] ?? '']) ? (string) $in['mode'] : 'page';
        $s['headings'] = max(0, min(6, (int) ($in['headings'] ?? 3)));
        $clean = fn(string $v, string $rx) => implode("\n", array_slice(array_values(array_filter(array_map('trim', preg_split('~[\r\n,]+~', $v) ?: []),
            fn($l) => $l !== '' && preg_match($rx, $l))), 0, 30));
        // Dynamische Bereiche: einfache Selektoren (.klasse, #id, [attribut], element)
        $s['live'] = $clean(mb_substr((string) ($in['live'] ?? ''), 0, 1000), '~^(?:[a-z][a-z0-9-]*)?(?:[.#][A-Za-z_][\w-]*|\[[a-z][\w-]*(?:=["\']?[\w -]+["\']?)?\])*$~');
        $s['exclude'] = $clean(mb_substr((string) ($in['exclude'] ?? ''), 0, 2000), '~^/[^\s<>"]*$~');
        app()->settings->set(self::SET, $s);
        PageCache::clear();
        return $s;
    }

    /** Glossar-Tabelle dieser Website (null = noch nicht eingerichtet oder ohne die nötigen Felder) */
    public static function table(): ?array
    {
        if (array_key_exists('table', self::$cache)) return self::$cache['table'];
        try {
            $t = Tables::findContent(self::HANDLE);
        } catch (\Throwable) {
            $t = null;
        }
        return self::$cache['table'] = $t && self::fieldsOk($t) ? $t : null;
    }

    public static function fieldsOk(array $t): bool
    {
        $names = array_column($t['fields'], 'type', 'name');
        return isset($names['begriff'], $names['kurz']);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    // ================================================================= Begriffe

    /**
     * Begriffe für Markierung, Übersicht und JSON: id, key (Slug), term, short, long (HTML), category, link, url (Detailseite),
     * variants, draft. $drafts: auch Entwürfe (Ansicht der Redaktion).
     */
    public static function terms(bool $drafts = false, ?string $lang = null): array
    {
        $t = self::table();
        if (!$t) return [];
        $key = 'terms:' . ($drafts ? 'd' : 'p') . ':' . ($lang ?? Lang::current());
        if (isset(self::$cache[$key])) return self::$cache[$key];
        $o = ['status' => $drafts ? 'all' : 'published', 'limit' => 3000, 'sort' => 'begriff', 'dir' => 'asc'];
        if ($lang !== null) $o['lang'] = $lang;
        try {
            $rows = Entries::query($t, $o);
        } catch (\Throwable $e) {
            error_log('[glossary] ' . $e->getMessage());
            $rows = [];
        }
        $out = [];
        foreach ($rows as $e) {
            $term = self::term($t, $e);
            if ($term['term'] !== '') $out[] = $term;
        }
        return self::$cache[$key] = $out;
    }

    /** Ein Eintrag als Begriff */
    public static function term(array $t, array $e): array
    {
        $has = array_column($t['fields'], 'type', 'name');
        return [
            'id' => (int) $e['id'], 'key' => (string) $e['slug'],
            'term' => trim(html_entity_decode(strip_tags((string) ($e['begriff'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'short' => self::short((string) ($e['kurz'] ?? '')),
            'long' => isset($has['erklaerung']) && trim(strip_tags((string) ($e['erklaerung'] ?? ''))) !== '' ? Entries::html($t, $e, 'erklaerung') : '',
            'category' => isset($has['kategorie']) ? trim(strip_tags((string) ($e['kategorie'] ?? ''))) : '',
            'link' => isset($has['link']) ? trim((string) ($e['link'] ?? '')) : '',
            'url' => Entries::href($t, $e),
            'variants' => self::splitVariants((string) ($e['varianten'] ?? '')),
            'draft' => ($e['status'] ?? 'published') !== 'published',
            'lang' => Lang::norm($e['lang'] ?? null),
        ];
    }

    /** Varianten: eine je Zeile, oder durch Komma bzw. Semikolon getrennt */
    public static function splitVariants(string $s): array
    {
        $out = [];
        foreach (preg_split('~[\r\n;,]+~u', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: [] as $v) {
            $v = trim((string) preg_replace('~\s+~u', ' ', $v));
            if ($v !== '' && mb_strlen($v) <= 80 && !in_array($v, $out, true)) $out[] = $v;
        }
        return array_slice($out, 0, 20);
    }

    /** Kurz-Erklärung als Klartext, höchstens 240 Zeichen (an einer Wortgrenze gekürzt) */
    public static function short(string $s): string
    {
        $s = trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if (mb_strlen($s) <= self::SHORT_MAX) return $s;
        $cut = mb_substr($s, 0, self::SHORT_MAX - 1);
        $sp = mb_strrpos($cut, ' ');
        return rtrim($sp > self::SHORT_MAX * .6 ? mb_substr($cut, 0, $sp) : $cut, ' ,;:–-') . '…';
    }

    /** Adresse der Übersicht (Seite mit dem Block „Glossar“) – sonst null */
    public static function overviewUrl(): ?string
    {
        $id = (int) (self::settings()['page_id'] ?? 0);
        $p = $id ? Pages::find($id) : null;
        // Andere Sprache: deren Übersetzung der Übersicht (ohne Übersetzung kein Link – die Übersicht zeigt Begriffe ihrer Sprache)
        if ($p && Lang::multi() && Lang::norm($p['lang'] ?? null) !== Lang::current()) $p = Pages::translations($p)[Lang::current()] ?? null;
        if (!$p || ($p['status'] !== 'published' && !app()->auth->check())) return null;
        return Pages::url($p);
    }

    // ================================================================= Ausgabe auf der Website

    /**
     * Ganze Seite: Begriffe markieren und – nur wenn etwas markiert wurde oder dynamische Bereiche da sind – Stylesheet und Skript
     * einbinden. Einmal je Anfrage (self::$done). $status ≠ 200 (Fehlerseiten) bleibt unberührt.
     */
    public static function page(string $html, int $status = 200): string
    {
        if (self::$done) return $html;
        self::$done = true;
        try {
            if ($status !== 200 || app()->editing || !str_contains($html, '</body>') || !self::enabled()) return $html;
            $s = self::settings();
            $path = (string) (app()->request?->path ?? '/');
            if ($s['mode'] === 'off' || self::excluded($path, $s['exclude'])) return $html;
            // Suchergebnisse (Auszüge) nicht markieren
            if (Features::on('search', false) && preg_match('~(^|/)' . preg_quote(\Core\Search\Search::slug(), '~') . '$~', trim($path, '/'))) return $html;
            $t = self::table();
            if (!$t) return $html;
            $drafts = app()->auth->check() && can('data.edit', $t['handle']);
            $terms = self::terms($drafts);
            if (!$terms) return $html;
            $exclude = [];
            $ctx = app()->entry;
            if ($ctx && ($ctx['table']['handle'] ?? '') === $t['handle']) $exclude[] = (int) $ctx['entry']['id'];
            $a = new Annotator($terms, ['mode' => $s['mode'], 'headings' => $s['headings'], 'exclude' => $exclude, 'labels' => self::labels(),
                'lang' => Lang::current()]);
            $out = $a->annotate($html);
            $live = self::liveIn($out, $s['live']);
            // Block „Glossar“ (Übersicht): Skript für Suchfilter und Buchstaben auch ohne Markierungen
            if ($a->count === 0 && !$live && !str_contains($out, 'data-glx-list')) return $html;
            return self::assets($out, $live ? self::selectors($s['live']) : []);
        } catch (\Throwable $e) {
            error_log('[glossary] ' . $e->getMessage());
            return $html;
        }
    }

    /** Feste Texte der Hinweisfenster (Sprache der Seite) */
    public static function labels(): array
    {
        return ['more' => lt('Mehr im Glossar'), 'close' => lt('Erklärung schließen'), 'draft' => lt('Entwurf')];
    }

    /** Pfad ausgenommen? Zeilen „/pfad“ (genau) oder „/pfad/*“ (alles darunter) */
    public static function excluded(string $path, string $list): bool
    {
        $path = '/' . trim($path, '/');
        foreach (preg_split('~\R~', $list) ?: [] as $p) {
            $p = trim($p);
            if ($p === '') continue;
            $base = '/' . trim(rtrim($p, '*'), '/');
            if (str_ends_with($p, '/*') ? ($path === $base || str_starts_with($path, rtrim($base, '/') . '/')) : (str_ends_with($p, '*') ? str_starts_with($path, $base) : $path === $base)) return true;
        }
        return false;
    }

    /** Selektoren der dynamischen Bereiche (immer auch [data-glossary="live"]) */
    public static function selectors(string $live): array
    {
        return array_values(array_unique(['[data-glossary="live"]', ...array_filter(array_map('trim', preg_split('~\R~', $live) ?: []))]));
    }

    /** Kommt ein dynamischer Bereich auf der Seite vor? (einfacher Abgleich von Klasse, ID, Attribut bzw. Element) */
    public static function liveIn(string $html, string $live): bool
    {
        if (preg_match('~\sdata-glossary\s*=\s*["\']?live\b~i', $html)) return true;
        foreach (preg_split('~\R~', $live) ?: [] as $sel) {
            $sel = trim($sel);
            if ($sel === '') continue;
            if (preg_match('~\.([\w-]+)~', $sel, $m) && preg_match('~\sclass\s*=\s*["\'][^"\']*(?<![\w-])' . preg_quote($m[1], '~') . '(?![\w-])~', $html)) return true;
            if (preg_match('~#([\w-]+)~', $sel, $m) && preg_match('~\sid\s*=\s*["\']' . preg_quote($m[1], '~') . '["\']~', $html)) return true;
            if (preg_match('~\[([\w-]+)~', $sel, $m) && preg_match('~\s' . preg_quote($m[1], '~') . '[\s=>]~', $html)) return true;
            if (preg_match('~^([a-z][a-z0-9-]*)$~', $sel, $m) && preg_match('~<' . preg_quote($m[1], '~') . '[\s>]~i', $html)) return true;
        }
        return false;
    }

    /** Stylesheet (Kern + optional css/glossary.css des Kits) und Skript einbinden */
    public static function assets(string $html, array $live = []): string
    {
        if (str_contains($html, 'assets/js/glossary.js')) return $html;
        // Stylesheet des Kits bringt eine Seite mit dem Block schon mit (Theme::conditionalCss) – nicht doppelt
        $css = implode('', array_map(fn($u) => '<link rel="stylesheet" href="' . e($u) . '">',
            array_filter(self::stylesheets(), fn($u) => !str_contains($html, (string) strtok($u, '?')))));
        $js = '<script src="' . e(asset('js/glossary.js')) . '" defer'
            . ($live ? ' data-live="' . e(implode(',', $live)) . '" data-src="' . e(url(self::JSON_PATH) . '?lang=' . rawurlencode(Lang::current())) . '"'
                . ' data-mod="' . e(asset('js/glossary-live.mjs')) . '"' : '') . '></script>';
        if ($css !== '') $html = ($p = stripos($html, '</head>')) !== false ? substr_replace($html, $css . "\n", $p, 0) : $css . $html;
        return ($p = strripos($html, '</body>')) !== false ? substr_replace($html, $js . "\n", $p, 0) : $html . $js;
    }

    /**
     * Stylesheets: Kern (resources/css/glossary.css – Begriffe im Text; mit $list glossary-list.css – Block „Glossar“), dazu
     * css/glossary.css des Kits, falls vorhanden (nur Ergänzungen nötig, gilt für beides)
     */
    public static function stylesheets(bool $list = false): array
    {
        $theme = app()->theme;
        return [asset($list ? 'css/glossary-list.css' : 'css/glossary.css'), ...($theme->hasAsset('css/glossary.css') ? [$theme->asset('css/glossary.css')] : [])];
    }

    /** Antwort für /_glossary.json (?lang=…) – 404 ohne Funktion bzw. Glossar */
    public static function jsonResponse(\Core\Http\Request $r): \Core\Http\Response
    {
        if (!self::enabled() || !self::table()) throw new \Core\Http\HttpException(404);
        $l = (string) ($r->query['lang'] ?? '');
        if ($l !== '' && $l !== Lang::default() && Lang::multi() && Lang::valid($l)) app()->lang = $l;
        $json = (string) json_encode(self::json(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return new \Core\Http\Response($json, 200, ['Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'public, max-age=300', 'X-Robots-Tag' => 'noindex', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** /_glossary.json: veröffentlichte Begriffe für dynamische Bereiche (ohne Entwürfe, öffentlich zwischenspeicherbar) */
    public static function json(): array
    {
        $s = self::settings();
        $terms = [];
        foreach (self::terms(false) as $t) {
            if ($t['short'] === '') continue;
            $terms[] = ['k' => $t['key'], 't' => $t['term'], 's' => $t['short'], 'u' => $t['url'],
                'v' => array_map(fn($v) => [$v, Annotator::caseSensitive($v) ? 1 : 0], Annotator::variants($t))];
        }
        return ['mode' => $s['mode'], 'headings' => (int) $s['headings'], 'lang' => Lang::current(), 'labels' => self::labels(), 'terms' => $terms];
    }

    // ================================================================= Einrichten

    /** Definition der Tabelle im Eingabeformat von Tables::validate() */
    public static function definition(): array
    {
        return [
            'name' => 'Glossar', 'singular' => 'Begriff', 'handle' => self::HANDLE, 'icon' => 'book-open-text',
            'description' => 'Fachbegriffe mit kurzer Erklärung – erscheinen als Hinweis an der ersten Stelle im Text (Funktion „Glossar“).',
            'fields' => [
                ['name' => 'begriff', 'label' => 'Begriff', 'type' => 'text', 'required' => true, 'in_list' => true, 'searchable' => true, 'width' => 'half',
                    'help' => 'So, wie er im Glossar steht – z. B. „SPF“ oder „Barrierefreiheit“.'],
                ['name' => 'kategorie', 'label' => 'Kategorie', 'type' => 'text', 'in_list' => true, 'width' => 'half', 'help' => 'Optional, z. B. „E-Mail“ oder „Sicherheit“.'],
                ['name' => 'varianten', 'label' => 'Varianten, Synonyme, Abkürzungen', 'type' => 'textarea', 'searchable' => true,
                    'help' => 'Eine je Zeile (oder mit Komma getrennt), z. B. „Sender Policy Framework“. Abkürzungen wie SPF zählen nur in genau dieser Schreibweise; "In Anführungszeichen" erzwingt die genaue Schreibweise auch für Wörter.'],
                ['name' => 'kurz', 'label' => 'Kurz-Erklärung', 'type' => 'textarea', 'required' => true, 'in_list' => true,
                    'help' => 'Klartext, höchstens 240 Zeichen – erscheint im Hinweisfenster auf der Website.'],
                ['name' => 'erklaerung', 'label' => 'Ausführliche Erklärung', 'type' => 'richtext', 'help' => 'Optional – erscheint auf der Detailseite des Begriffs.'],
                ['name' => 'link', 'label' => 'Mehr erfahren (Webadresse)', 'type' => 'url', 'help' => 'Optional: weiterführende Quelle, z. B. RFC, BSI, MDN oder Wikipedia.'],
            ],
            'settings' => [
                'route' => self::HANDLE, 'title_field' => 'begriff', 'description_field' => 'kurz', 'sort_field' => 'begriff', 'sort_dir' => 'asc',
                'schema_type' => 'none', 'workflow' => true, 'per_page' => 100, 'list_image' => 'none',
            ],
        ];
    }

    /**
     * Glossar einrichten (wiederholbar): Tabelle, Detailseiten-Vorlage (/glossar/{slug}) und Übersichtsseite /glossar.
     * $o: publish (Übersicht gleich veröffentlichen, Standard nein), dry (nur prüfen). Rückgabe: [Meldungen, Tabelle]
     */
    public static function install(array $o = []): array
    {
        $msgs = [];
        $dry = !empty($o['dry']);
        $existing = Tables::findContent(self::HANDLE);
        if ($existing && !self::fieldsOk($existing)) {
            throw new \RuntimeException(__('Es gibt schon eine Tabelle „glossar“ ohne die Felder „begriff“ und „kurz“ – bitte umbenennen oder die Felder ergänzen.'));
        }
        if (!$existing) {
            if (Tables::byRoute(self::HANDLE)) throw new \RuntimeException(__('Die Adresse /glossar nutzt schon eine andere Tabelle.'));
            [$def, $errors] = Tables::validate(self::definition());
            if ($errors) throw new \RuntimeException(implode(' ', $errors));
            if (!$dry) Tables::create($def);
            $msgs[] = $dry ? __('Tabelle „Glossar“ würde angelegt.') : __('Tabelle „Glossar“ angelegt.');
        } else {
            $msgs[] = __('Tabelle „Glossar“ ist schon da.');
        }
        Tables::flush();
        self::flush();
        $t = Tables::findContent(self::HANDLE);
        if ($dry) return [$msgs, $t];
        // Detailseiten-Vorlage: Block „Glossar“ in der Ansicht „Begriff“
        if (empty($t['settings']['detail_page_id']) || !Pages::find((int) $t['settings']['detail_page_id'])) {
            $id = Pages::create(['slug' => '_vorlage-' . self::HANDLE, 'title' => 'Glossar – Detailseite', 'type' => 'template', 'template_for' => self::HANDLE,
                'status' => 'published', 'noindex' => 0], Pages::sanitizeBlocks([['id' => bin2hex(random_bytes(5)), 'type' => 'glossary',
                'data' => ['view' => 'term'], 'tunes' => ['section' => []]]]));
            Tables::setDetailPage($t, $id);
            $msgs[] = __('Detailseiten unter /glossar/… eingerichtet.');
        }
        // Übersicht /glossar
        $s = self::settings();
        $page = !empty($s['page_id']) ? Pages::find((int) $s['page_id']) : null;
        $page ??= Pages::byPath(self::HANDLE, Lang::default());
        if (!$page) {
            $pid = Pages::create(['slug' => self::HANDLE, 'title' => 'Glossar', 'status' => !empty($o['publish']) ? 'published' : 'draft',
                'meta_description' => 'Fachbegriffe kurz erklärt – von A bis Z.'],
                Pages::sanitizeBlocks([['id' => bin2hex(random_bytes(5)), 'type' => 'glossary', 'data' => ['view' => 'list', 'title' => 'Glossar',
                    'intro' => 'Fachbegriffe kurz erklärt – von A bis Z.', 'search' => true, 'letters' => true], 'tunes' => ['section' => []]]]));
            $page = Pages::find($pid);
            $msgs[] = !empty($o['publish']) ? __('Übersichtsseite /glossar angelegt und veröffentlicht.') : __('Übersichtsseite /glossar als Entwurf angelegt.');
        }
        if ((int) ($s['page_id'] ?? 0) !== (int) $page['id']) {
            $s['page_id'] = (int) $page['id'];
            app()->settings->set(self::SET, $s);
        }
        Tables::flush();
        self::flush();
        PageCache::clear();
        return [$msgs, Tables::findContent(self::HANDLE)];
    }

    // ================================================================= Prüfen

    /**
     * Hinweise zur Pflege: doppelte Varianten (nur einer wird markiert), Überschneidungen (längere Variante gewinnt),
     * fehlende oder zu lange Kurz-Erklärungen. @return list<array{level: string, id: int, text: string}>
     */
    public static function checks(?array $terms = null): array
    {
        $terms ??= self::terms(true);
        $out = [];
        $owners = [];
        foreach ($terms as $t) {
            if ($t['short'] === '') $out[] = ['level' => 'warn', 'id' => $t['id'], 'text' => __('„{t}“ hat keine Kurz-Erklärung und wird nicht markiert.', ['t' => $t['term']])];
            foreach (Annotator::variants($t) as $v) {
                $plain = trim($v, '"„“”');
                if (mb_strlen($plain) < 2) $out[] = ['level' => 'warn', 'id' => $t['id'], 'text' => __('„{t}“: Variante „{v}“ ist zu kurz.', ['t' => $t['term'], 'v' => $v])];
                $norm = Annotator::caseSensitive($plain) ? $plain : mb_strtolower($plain);
                // Je Sprache: „API“ auf Deutsch und Englisch ist kein Doppel (eine Seite markiert nur Begriffe ihrer Sprache)
                $owners[($t['lang'] ?? '') . "\x1F" . $norm][$t['id']] = $t['term'];
            }
        }
        foreach ($owners as $v => $ids) {
            $v = substr((string) $v, (int) strpos((string) $v, "\x1F") + 1);
            if (count($ids) > 1) {
                $out[] = ['level' => 'warn', 'id' => (int) array_key_first($ids), 'text' => __('„{v}“ steht bei mehreren Begriffen ({list}) – markiert wird nur einer.', ['v' => $v, 'list' => implode(', ', $ids)])];
            }
        }
        // Überschneidungen: Variante steckt als ganzes Wort in einer Variante eines anderen Begriffs (TLS ⊂ TLS-RPT)
        $keys = array_map('strval', array_keys($owners));
        foreach ($keys as $ks) {
            [$ls, $short] = explode("\x1F", $ks, 2);
            foreach ($keys as $kl) {
                [$ll, $long] = explode("\x1F", $kl, 2);
                if ($ls !== $ll || $short === $long || mb_strlen($long) <= mb_strlen($short)) continue;
                if (!preg_match('~(?<![\p{L}\p{N}])' . preg_quote($short, '~') . '(?![\p{L}\p{N}])~u', $long)) continue;
                $a = array_values($owners[$ks])[0];
                $b = array_values($owners[$kl])[0];
                if ($a === $b) continue;
                $out[] = ['level' => 'info', 'id' => (int) array_key_first($owners[$kl]),
                    'text' => __('„{short}“ ({a}) steckt in „{long}“ ({b}) – dort gilt der längere Begriff.', ['short' => $short, 'a' => $a, 'long' => $long, 'b' => $b])];
            }
        }
        return $out;
    }

    /**
     * „Wo kommt der Begriff vor?“ – Texte des Suchindex (Seiten und Einträge anderer Tabellen, Core\Search\Documents) mit den
     * Regeln der Markierung durchsuchen. 10 Minuten zwischengespeichert. @return array<int, list<array{title: string, url: string}>>
     */
    public static function occurrences(bool $refresh = false): array
    {
        $file = site()->storage('cache') . '/glossary-occurrences.json';
        if (!$refresh && is_file($file) && filemtime($file) > time() - 600) {
            return json_decode((string) file_get_contents($file), true) ?: [];
        }
        $out = [];
        // Je Sprache: deren Begriffe in deren Seiten und Einträgen
        foreach (Lang::multi() ? array_keys(Lang::all()) : [Lang::default()] as $lang) {
            $rx = Annotator::pattern(self::terms(true, $lang), $lang);
            if ($rx === null) continue;
            foreach (\Core\Search\Documents::build($lang) as $doc) {
                if (($doc['table'] ?? '') === self::HANDLE || ($doc['url'] ?? '') === '') continue;
                $hit = [];
                preg_replace_callback($rx, function ($m) use (&$hit) {
                    if (!empty($m['MARK'])) $hit[(int) $m['MARK']] = true;
                    return $m[0];
                }, e(implode(' ', [$doc['title'] ?? '', $doc['headings'] ?? '', $doc['text'] ?? ''])));
                foreach (array_keys($hit) as $id) $out[$id][] = ['title' => (string) $doc['title'], 'url' => (string) $doc['url']];
            }
        }
        @mkdir(dirname($file), 0775, true);
        @file_put_contents($file, json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $out;
    }

    // ================================================================= Import & Export

    /** Spalte lang: Sprache des Begriffs (leer = Standardsprache) – vorhandene Begriffe werden je Sprache abgeglichen */
    public const CSV_COLUMNS = ['begriff', 'varianten', 'kurz', 'erklaerung', 'kategorie', 'link', 'status', 'lang'];

    public static function exportCsv(): string
    {
        $t = self::table();
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, self::CSV_COLUMNS, ';', '"', '');
        foreach ($t ? Entries::query($t, ['status' => 'all', 'limit' => 5000, 'lang' => 'all']) : [] as $e) {
            fputcsv($fh, [(string) $e['begriff'], implode(', ', self::splitVariants((string) ($e['varianten'] ?? ''))), (string) $e['kurz'],
                (string) ($e['erklaerung'] ?? ''), (string) ($e['kategorie'] ?? ''), (string) ($e['link'] ?? ''), (string) $e['status'], Lang::norm($e['lang'] ?? null)], ';', '"', '');
        }
        rewind($fh);
        return (string) stream_get_contents($fh);
    }

    /**
     * CSV übernehmen (Trennzeichen ; oder , – erste Zeile mit Spaltennamen wie im Export). Vorhandene Begriffe (gleicher
     * Begriff, ohne Groß-/Kleinschreibung) nur mit $overwrite. Neue Einträge als Entwurf, außer Spalte status = published.
     * @return array{total: int, created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public static function importCsv(string $text, bool $dry = false, bool $overwrite = false): array
    {
        $t = self::table() ?? throw new \RuntimeException(__('Bitte zuerst das Glossar einrichten.'));
        $text = (string) preg_replace('~^\xEF\xBB\xBF~', '', $text);
        $first = strtok($text, "\n") ?: '';
        $sep = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, $text);
        rewind($fh);
        $head = array_map(fn($h) => strtolower(trim((string) $h)), fgetcsv($fh, null, $sep, '"', '') ?: []);
        if (!in_array('begriff', $head, true)) throw new \RuntimeException(__('Die erste Zeile braucht die Spaltennamen, mindestens „begriff“ und „kurz“.'));
        $existing = [];
        $key = fn(string $lang, string $name) => $lang . "\x1F" . mb_strtolower(trim($name));
        foreach (Entries::query($t, ['status' => 'all', 'limit' => 5000, 'lang' => 'all']) as $e) $existing[$key(Lang::norm($e['lang'] ?? null), (string) $e['begriff'])] = $e;
        $res = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $line = 1;
        while (($row = fgetcsv($fh, null, $sep, '"', '')) !== false) {
            $line++;
            if ($row === [null] || !array_filter($row, fn($v) => trim((string) $v) !== '')) continue;
            $res['total']++;
            $r = [];
            foreach ($head as $i => $h) if (in_array($h, self::CSV_COLUMNS, true)) $r[$h] = trim((string) ($row[$i] ?? ''));
            $name = (string) ($r['begriff'] ?? '');
            if ($name === '' || ($r['kurz'] ?? '') === '') { $res['errors'][$line] = __('Begriff und Kurz-Erklärung sind Pflicht.'); continue; }
            $lang = strtolower((string) ($r['lang'] ?? ''));
            $lang = $lang !== '' && Lang::valid($lang) ? $lang : Lang::default();
            $cur = $existing[$key($lang, $name)] ?? null;
            if ($cur && !$overwrite) { $res['skipped']++; continue; }
            $in = ['begriff' => $name, 'kurz' => self::short($r['kurz']), 'varianten' => implode("\n", self::splitVariants((string) ($r['varianten'] ?? '')))];
            foreach (['erklaerung', 'kategorie', 'link'] as $k) if (array_key_exists($k, $r)) $in[$k] = $r[$k];
            $in['status'] = in_array(strtolower((string) ($r['status'] ?? '')), ['published', 'veröffentlicht', 'online'], true) ? 'published' : 'draft';
            if (!$cur) $in['lang'] = $lang;
            if ($dry) { $res[$cur ? 'updated' : 'created']++; continue; }
            [$id, $errors] = Entries::save($t, $cur ? (int) $cur['id'] : null, $in);
            if ($errors) { $res['errors'][$line] = implode(' ', $errors); continue; }
            $res[$cur ? 'updated' : 'created']++;
            $existing[$key($lang, $name)] = ['id' => $id, 'begriff' => $name];
        }
        self::flush();
        return $res;
    }

    // ================================================================= KI (optional)

    /** Kann die angemeldete Person Erklärungen von der KI vorschlagen lassen? (Funktion „ai“ + KI eingerichtet) */
    public static function aiAvailable(): bool
    {
        try {
            return Features::on('ai', false) && \Core\AI\Assist::available('text');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Kurz-Erklärung vorschlagen (Klartext ≤ 240 Zeichen, „Sie“-Form) – wird immer als Entwurf gespeichert und geprüft */
    public static function suggest(string $term, array $variants = [], string $context = ''): string
    {
        $site = site_name();
        $pack = [
            'system' => "Du schreibst Glossar-Erklärungen für die Website „{$site}“. Antworte nur mit der Erklärung: ein bis zwei Sätze, "
                . 'höchstens 220 Zeichen, sachlich korrekt, neutral, verständlich für Laien, auf Deutsch in der Sie-Form, ohne Werbung, ohne '
                . 'Einleitung wie „X ist …:“, ohne Markdown. Wenn der Begriff mehrdeutig ist, nimm die Bedeutung aus dem Zusammenhang.',
            'user' => 'Begriff: ' . $term . ($variants ? "\nAuch: " . implode(', ', $variants) : '') . ($context !== '' ? "\nZusammenhang: " . mb_substr($context, 0, 600) : ''),
            'max_tokens' => 300, 'temperature' => 0.2,
        ];
        $r = \Core\AI\Assist::call('text', $pack, '[Test-KI] ' . $term . ' kurz erklärt – bitte prüfen.');
        \Core\AI\Assist::log('glossary', 'suggest', $term, 'suggested', (string) ($r['model'] ?? ''), (int) ($r['ms'] ?? 0));
        return self::short(\Core\AI\Assist::clean((string) $r['text'], 'plain'));
    }
}
