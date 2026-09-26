<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Landingpages mit eigenen Domains innerhalb einer Website (Funktion „landings“).
 *
 * Eine weitere Domain (z. B. reisemedizin-moers.de) zeigt eine Seite der Website – auf Wunsch mit ihren Unterseiten –
 * ohne eigene Website, Benutzer oder Datenbank. Die Zuordnung Domain → Seite liegt in der Tabelle „landings“ der Website;
 * die Domain selbst steht in config/sites/{key}.php → 'landing_hosts' (oder 'hosts'; site:hosts … --landing, Netzwerk-Übersicht)
 * und im Hosting (Plesk: Alias bzw. zusätzliche Domain) auf dieselbe Installation zeigen.
 *
 * Regeln (Anfrage auf einer Landing-Domain):
 *  - „/“ zeigt die Einstiegsseite, „/unterseite“ Unterseiten (falls eingeschaltet), „/en/“ die Übersetzung.
 *  - Andere Pfade: Weiterleitung (302) zur Hauptdomain oder 404. Dateien, Medien, Formulare, Suche, sitemap.xml, robots.txt bleiben.
 *  - Links auf Seiten der Landingpage bleiben auf der Landing-Domain, alle anderen zeigen absolut auf die Hauptdomain.
 *  - /admin leitet zur Hauptdomain – auf Landing-Domains entstehen nie Sitzungen oder Cookies.
 *  - Canonical: „own“ = Landing-Domain (Hauptdomain-Adresse zeigt ebenfalls dorthin, optional 301), „mirror“ = Hauptdomain.
 */
final class Landings
{
    public const MODES = ['own' => 'Eigene Domain ist maßgeblich (Canonical auf die Landing-Domain)', 'mirror' => 'Spiegel (Canonical auf die Hauptdomain)'];
    public const SEARCH = ['subtree' => 'Nur Seiten der Landingpage', 'all' => 'Ganze Website', 'off' => 'Aus'];
    public const LAYOUTS = ['full' => 'Normales Layout (Menü auf die Landingpage begrenzt)', 'landing' => 'Reduziertes Landing-Layout (wenn das Kit eines anbietet)'];

    /** @var list<Landing>|null */
    private static ?array $all = null;
    private static Landing|false|null $matched = null;
    private static int $suspended = 0;

    public static function enabled(): bool
    {
        return Features::on('landings', false);
    }

    /** @return list<Landing> */
    public static function all(): array
    {
        if (self::$all !== null) return self::$all;
        try {
            $rows = app()->db->fetchAll('SELECT * FROM landings ORDER BY id');
        } catch (\Throwable) {
            $rows = [];
        }
        return self::$all = array_map(fn($r) => Landing::fromRow($r), $rows);
    }

    public static function flush(): void
    {
        self::$all = null;
        self::$matched = null;
    }

    public static function find(int $id): ?Landing
    {
        foreach (self::all() as $l) if ($l->id === $id) return $l;
        return null;
    }

    /** Aktive Landingpage zur Domain (exakt mit Port, dann ohne Port) */
    public static function forHost(string $host): ?Landing
    {
        $host = strtolower(trim($host));
        $bare = (string) preg_replace('~:\d+$~', '', $host);
        foreach ([$host, $bare] as $h) {
            foreach (self::all() as $l) {
                if ($l->active && in_array($h, $l->hosts, true)) return $l;
            }
        }
        return null;
    }

    /** Landingpage der aktuellen Anfrage (unabhängig von suspend) */
    public static function matched(): ?Landing
    {
        if (self::$matched === null) {
            $r = app()->request;
            if (!$r || PHP_SAPI === 'cli') return null;
            self::$matched = self::enabled() ? (self::forHost($r->host()) ?? false) : false;
        }
        return self::$matched ?: null;
    }

    /** Landingpage, deren Regeln gerade gelten (null auf der Hauptdomain oder innerhalb von suspend) */
    public static function current(): ?Landing
    {
        return self::$suspended ? null : self::matched();
    }

    /** Code „wie auf der Hauptdomain“ ausführen (z. B. Canonical und JSON-LD im Spiegel-Modus) */
    public static function suspend(callable $fn): mixed
    {
        self::$suspended++;
        try {
            return $fn();
        } finally {
            self::$suspended--;
        }
    }

    /**
     * Ursprung der Hauptdomain (ohne Pfad): Grundeinstellung „Kanonische Adresse“ → config base_url → erste Domain der
     * Website, die keine Landing-Domain ist → zuletzt in der Verwaltung benutzte Adresse → aktuelle Anfrage.
     */
    public static function mainOrigin(): string
    {
        $u = rtrim((string) app()->settings->get('sys.site_url', ''), '/');
        if ($u === '') $u = rtrim((string) app()->config->get('base_url', ''), '/');
        if ($u !== '') return $u;
        $r = app()->request;
        $scheme = !$r || $r->isSecure() ? 'https://' : 'http://';
        $landingHosts = array_merge(...array_map(fn(Landing $l) => $l->hosts, self::all()) ?: [[]]);
        foreach (site()->hosts() as $h) {
            if (!in_array($h, $landingHosts, true) && !in_array((string) preg_replace('~:\d+$~', '', $h), $landingHosts, true)) return $scheme . $h;
        }
        $saved = rtrim((string) app()->settings->get('landings.main_origin', ''), '/');
        if ($saved !== '') return $saved;
        return $r ? $scheme . $r->host() : '';
    }

    /** Adresse der Verwaltung merken (Rückfall für mainOrigin, wenn weder kanonische Adresse noch Domains gesetzt sind) */
    public static function rememberOrigin(): void
    {
        $r = app()->request;
        if (!$r || self::matched() || self::isLandingHost($r->host())) return;
        $o = ($r->isSecure() ? 'https://' : 'http://') . $r->host();
        if (app()->settings->get('landings.main_origin') !== $o) app()->settings->set('landings.main_origin', $o);
    }

    /**
     * Domains, die für Landingpages infrage kommen: nur die Landing-Domains der Website ('landing_hosts') – die Domains unter
     * 'hosts' (Hauptadresse und ihre Aliasse wie www/ohne www) bleiben der Website vorbehalten.
     */
    public static function siteHosts(): array
    {
        return site()->landingHosts();
    }

    public static function isLandingHost(string $host): bool
    {
        $host = strtolower($host);
        $bare = (string) preg_replace('~:\d+$~', '', $host);
        foreach (self::all() as $l) if (in_array($host, $l->hosts, true) || in_array($bare, $l->hosts, true)) return true;
        return false;
    }

    /** Landingpage im Modus „own“, zu der eine Seite gehört (tiefste Einstiegsseite gewinnt) */
    public static function owner(array $page): ?Landing
    {
        if (!self::enabled() || empty($page['id'])) return null;
        $best = null;
        $depth = -1;
        foreach (self::all() as $l) {
            if (!$l->active || $l->mode !== 'own' || !$l->contains($page)) continue;
            $d = substr_count((string) ($l->rootPage()['path'] ?? ''), '/');
            if ($d > $depth) { $best = $l; $depth = $d; }
        }
        return $best;
    }

    /** Alle Landingpages, zu denen eine Seite gehört (Seiteneinstellungen) */
    public static function forPage(array $page): array
    {
        return array_values(array_filter(self::all(), fn(Landing $l) => $l->contains($page)));
    }

    /**
     * Canonical einer Seite, wenn eine Landingpage ihn bestimmt (sonst null → normale Adresse):
     * auf der Landing-Domain (own) die Landing-Adresse, auf der Hauptdomain die der besitzenden Landingpage.
     */
    public static function canonical(array $page): ?string
    {
        if ($l = self::current()) {
            return $l->mode === 'own' ? $l->absUrl($page) : null;
        }
        return self::owner($page)?->absUrl($page);
    }

    /** Hauptdomain → Landing-Domain (301) für Besucher, wenn die Landingpage das verlangt */
    public static function redirectFor(array $page, Http\Request $r): ?string
    {
        if (self::matched() || $r->method !== 'GET' || isset($r->query['edit']) || isset($r->query['live']) || !empty($page['is_home'])) return null;
        $o = self::owner($page);
        if (!$o || !$o->redirectMain || $page['status'] !== 'published') return null;
        $to = $o->absUrl($page);
        return $to !== null ? $to . ($r->query ? '?' . http_build_query($r->query) : '') : null;
    }

    /** Seite zu einem Pfad der Website („/leistungen/x“, „/en/…“) – für Links in Inhalten */
    public static function pageForPath(string $path): ?array
    {
        $path = trim((string) preg_split('~[?#]~', $path)[0], '/');
        $lang = null;
        $first = explode('/', $path)[0];
        if (Lang::multi() && $first !== Lang::default() && Lang::valid($first)) {
            $lang = $first;
            $path = trim(substr($path, strlen($first)), '/');
        }
        if ($path === '') return Pages::home($lang);
        return Pages::byPath($path, $lang);
    }

    /**
     * Links in HTML (Rich-Text) für die Landing-Domain umschreiben: Seitenpfade der Website („/leistungen/x“) zeigen
     * innerhalb der Landingpage relativ, sonst absolut auf die Hauptdomain. Auf der Hauptdomain unverändert.
     */
    public static function rewriteLinks(string $html): string
    {
        if (!self::current() || !str_contains($html, 'href="/')) return $html;
        return (string) preg_replace_callback('~href="(/(?!/)[^"]*)"~', function ($m) {
            $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            $p = self::pageForPath($raw);
            if (!$p) return $m[0];
            return 'href="' . e(Pages::url($p) . (preg_match('~[?#].*$~', $raw, $x) ? $x[0] : '')) . '"';
        }, $html);
    }

    /** Template für die Seite: „landing“, wenn gewählt und vom Theme angeboten, sonst „layout“ */
    public static function template(): string
    {
        $l = self::current();
        return $l && $l->layout === 'landing' && is_file(app()->theme->path . '/templates/landing.php') ? 'landing' : 'layout';
    }

    // ------------------------------------------------------------------ Verwaltung

    /** Formularfelder (Core\Fields) für Anlegen/Bearbeiten */
    public static function fields(?int $id = null): array
    {
        // Domains der Website ohne die Verwaltungs-Domain und ohne Domains anderer Landingpages
        $r = app()->request;
        $taken = array_merge(...array_map(fn(Landing $l) => $l->id === $id ? [] : $l->hosts, self::all()) ?: [[]]);
        $hostOpts = [];
        foreach (self::siteHosts() as $h) {
            if (in_array($h, $taken, true) || ($r && strtolower($r->host()) === $h)) continue;
            $hostOpts[$h] = $h;
        }
        $out = [
            ['type' => 'heading', 'label' => __('Domain & Inhalt')],
            ['name' => 'label', 'label' => __('Bezeichnung (intern)'), 'type' => 'text', 'placeholder' => __('z. B. Kampagne Reisemedizin')],
            ['name' => 'hosts', 'label' => __('Domains'), 'type' => 'multiselect', 'required' => true, 'options' => $hostOpts,
                'help' => $hostOpts ? __('Landing-Domains dieser Website. Die erste gewählte Domain ist die Hauptadresse der Landingpage.')
                    : __('Keine freie Domain: Die Agentur trägt neue Domains in der Netzwerk-Übersicht (Domains) oder per site:hosts ein.')],
            ['name' => 'page_id', 'label' => __('Seite'), 'type' => 'page', 'required' => true, 'help' => __('Diese Seite erscheint unter „/“ der Landing-Domain.')],
            ['name' => 'include_subpages', 'label' => __('Unterseiten einbeziehen (z. B. /impfungen)'), 'type' => 'bool', 'default' => true],
            ['name' => 'active', 'label' => __('Aktiv'), 'type' => 'bool', 'default' => true],
            ['type' => 'heading', 'label' => __('Suchmaschinen & Weiterleitungen')],
            ['name' => 'mode', 'label' => __('Maßgebliche Adresse (Canonical)'), 'type' => 'select', 'required' => true, 'default' => 'own',
                'options' => array_map('__', self::MODES)],
            ['name' => 'redirect_main', 'label' => __('Besucher der Hauptdomain-Adresse dauerhaft (301) zur Landing-Domain weiterleiten'), 'type' => 'bool', 'default' => false,
                'help' => __('Nur im Modus „Eigene Domain“. Angemeldete Redakteure bleiben auf der Hauptdomain.')],
            ['name' => 'redirect_other', 'label' => __('Andere Adressen der Landing-Domain zur Hauptdomain weiterleiten (sonst „nicht gefunden“)'), 'type' => 'bool', 'default' => true],
            ['name' => 'noindex', 'label' => __('Landing-Domain nicht in Suchmaschinen aufnehmen'), 'type' => 'bool', 'default' => false],
            ['name' => 'search', 'label' => __('Website-Suche auf der Landing-Domain'), 'type' => 'select', 'required' => true, 'default' => 'subtree',
                'options' => array_map('__', self::SEARCH)],
            ['type' => 'heading', 'label' => __('Marke & Darstellung'), 'help' => __('Leer lassen = wie die Website.')],
            ['name' => 'layout', 'label' => __('Layout'), 'type' => 'select', 'required' => true, 'default' => 'full', 'options' => array_map('__', self::LAYOUTS)],
            ['name' => 'name', 'label' => __('Name der Landingpage'), 'type' => 'text', 'width' => 'half', 'placeholder' => site_name()],
            ['name' => 'tagline', 'label' => __('Unterzeile'), 'type' => 'text', 'width' => 'half'],
            ['name' => 'logo', 'label' => __('Logo'), 'type' => 'media'],
            ['name' => 'favicon', 'label' => __('Favicon / App-Icon (quadratisch, PNG oder SVG)'), 'type' => 'media'],
            ['name' => 'og_image', 'label' => __('Vorschaubild für soziale Netzwerke'), 'type' => 'media'],
        ];
        // Design-Überschreibungen: Farben und Schriften des Themes
        $fonts = Design::fonts();   // inkl. installierter Schriften (Core\Fonts)
        $tokens = array_filter(Design::tokens(), fn($t) => in_array($t['type'] ?? 'color', ['color', 'font'], true) && !empty($t['var']));
        if ($tokens && Features::on('design', false)) {
            $out[] = ['type' => 'heading', 'label' => __('Farben & Schriften'), 'help' => __('Überschreibt einzelne Werte aus Verwaltung → Design nur auf der Landing-Domain. Leer = Wert der Website.')];
            foreach ($tokens as $n => $t) {
                $label = __((string) ($t['label'] ?? $n));
                if (($t['type'] ?? 'color') === 'font') {
                    $out[] = ['name' => 'd_' . $n, 'label' => $label, 'type' => 'select', 'width' => 'half',
                        'options' => array_map(fn($f) => (string) ($f['label'] ?? ''), $fonts)];
                } else {
                    $out[] = ['name' => 'd_' . $n, 'label' => $label, 'type' => 'color', 'width' => 'half', 'default' => '', 'placeholder' => (string) Design::get($n)];
                    if (isset($t['dark'])) $out[] = ['name' => 'd_' . $n . '__dark', 'label' => __('{label} (dunkel)', ['label' => $label]), 'type' => 'color', 'width' => 'half', 'default' => ''];
                }
            }
        }
        return $out;
    }

    /** Formularwerte einer Landingpage */
    public static function values(?Landing $l): array
    {
        if (!$l) {
            $v = ['hosts' => [], 'include_subpages' => true, 'active' => true, 'mode' => 'own', 'redirect_other' => true, 'search' => 'subtree', 'layout' => 'full'];
            return $v;
        }
        $v = ['label' => $l->label, 'hosts' => $l->hosts, 'page_id' => $l->pageId, 'include_subpages' => $l->includeSubpages, 'active' => $l->active,
            'mode' => $l->mode, 'redirect_main' => $l->redirectMain, 'redirect_other' => $l->redirectOther, 'noindex' => $l->noindex, 'search' => $l->search,
            'layout' => $l->layout, 'name' => $l->name ?? '', 'tagline' => $l->tagline ?? '', 'logo' => $l->logo, 'favicon' => $l->favicon, 'og_image' => $l->ogImage];
        foreach ($l->design as $k => $val) $v['d_' . str_replace('@dark', '__dark', (string) $k)] = $val;
        return $v;
    }

    /**
     * Prüfen und speichern. $input = Formularwerte (Core\Fields). @return array{0: ?int, 1: array} [id, Fehler]
     */
    public static function save(array $input, ?int $id = null): array
    {
        $fields = self::fields($id);
        [$v, $errors] = Fields::sanitize($fields, $input, []);
        $hosts = array_values(array_unique(array_map('strtolower', (array) ($v['hosts'] ?? []))));
        // Reihenfolge der Eingabe beibehalten (erste = Hauptadresse)
        $siteHosts = self::siteHosts();
        foreach ($hosts as $h) {
            if (!in_array($h, $siteHosts, true)) $errors['hosts'] = __('Die Domain {host} ist keine Landing-Domain dieser Website.', ['host' => $h]);
            foreach (self::all() as $o) {
                if ($o->id !== $id && in_array($h, $o->hosts, true)) $errors['hosts'] = __('Die Domain {host} gehört schon zur Landingpage „{name}“.', ['host' => $h, 'name' => $o->label ?: $o->host()]);
            }
            $r = app()->request;
            if ($r && (strtolower($r->host()) === $h)) $errors['hosts'] = __('Die Domain {host} ist die Adresse, unter der Sie gerade verwalten – sie kann keine Landing-Domain sein.', ['host' => $h]);
        }
        if (!$hosts) $errors['hosts'] ??= __('Bitte mindestens eine Domain wählen.');
        $page = !empty($v['page_id']) ? Pages::find((int) $v['page_id']) : null;
        if (!$page || $page['type'] !== 'page') {
            $errors['page_id'] ??= __('Bitte eine Seite wählen.');
        } elseif (!empty($page['is_home'])) {
            $errors['page_id'] = __('Die Startseite kann keine Landingpage sein – bitte eine andere Seite wählen.');
        }
        if ($errors) return [null, $errors];

        $design = [];
        foreach ($v as $k => $val) {
            if (!str_starts_with((string) $k, 'd_') || $val === '' || $val === null) continue;
            $design[str_replace('__dark', '@dark', substr((string) $k, 2))] = $val;
        }
        $opts = [
            'redirect_main' => !empty($v['redirect_main']), 'redirect_other' => !empty($v['redirect_other']), 'noindex' => !empty($v['noindex']),
            'search' => (string) ($v['search'] ?: 'subtree'), 'layout' => (string) ($v['layout'] ?: 'full'),
            'name' => trim((string) ($v['name'] ?? '')), 'tagline' => trim((string) ($v['tagline'] ?? '')),
            'logo' => $v['logo'] ?? null, 'favicon' => $v['favicon'] ?? null, 'og_image' => $v['og_image'] ?? null, 'design' => $design,
        ];
        $row = [
            'label' => mb_substr(trim((string) ($v['label'] ?? '')) ?: $hosts[0], 0, 120), 'hosts' => json_encode($hosts),
            'page_id' => (int) $page['id'], 'include_subpages' => !empty($v['include_subpages']) ? 1 : 0,
            'mode' => in_array($v['mode'] ?? '', ['own', 'mirror'], true) ? $v['mode'] : 'own',
            'options_json' => json_encode($opts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'active' => !empty($v['active']) ? 1 : 0, 'updated_at' => now(),
        ];
        $db = app()->db;
        if ($id) {
            $db->update('landings', $row, 'id = :id', ['id' => $id]);
        } else {
            $id = (int) $db->insert('landings', $row + ['created_at' => now()]);
        }
        self::flush();
        PageCache::clear();
        return [$id, []];
    }

    public static function delete(int $id): void
    {
        app()->db->pdo->prepare('DELETE FROM landings WHERE id = ?')->execute([$id]);
        foreach (glob(site()->mediaDir('design') . '/landing-' . $id . '-*.css') ?: [] as $f) @unlink($f);
        self::flush();
        PageCache::clear();
    }

    /** Prüf-Token für /health?landing=… (Erreichbarkeit der Domain) */
    public static function healthToken(Landing $l): string
    {
        return substr(hash_hmac('sha256', 'landing:' . $l->id . ':' . site()->key, app()->key()), 0, 24);
    }

    /**
     * Status einer Domain: DNS-Auflösung und Selbsttest (/health?landing=…) – zeigt, ob die Domain auf diese Installation zeigt.
     * @return array{dns: list<string>, reachable: bool, same: bool, message: string}
     */
    public static function check(Landing $l, string $host): array
    {
        $bare = (string) preg_replace('~:\d+$~', '', $host);
        $ips = @gethostbynamel($bare) ?: [];
        $token = self::healthToken($l);
        $out = ['dns' => $ips, 'reachable' => false, 'same' => false, 'message' => ''];
        foreach (['https://', 'http://'] as $scheme) {
            $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true, 'follow_location' => 0, 'header' => "Accept: application/json\r\n"],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
            $body = @file_get_contents($scheme . $host . base_path() . '/health?landing=' . $token, false, $ctx);
            if ($body === false) continue;
            $out['reachable'] = true;
            $j = json_decode($body, true);
            $out['same'] = is_array($j) && ($j['landing'] ?? null) === $l->id;
            $out['message'] = $scheme;
            break;
        }
        return $out;
    }
}
