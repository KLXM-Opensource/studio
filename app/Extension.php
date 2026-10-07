<?php
declare(strict_types=1);

namespace Core;

/**
 * Schnittstelle für eine aktive Erweiterung (wird an 'boot' übergeben).
 *
 *   $x->blocks(['termine' => [...Blockdefinition...]])    Blöcke (Renderer: {dir}/blocks/{typ}.php)
 *   $x->routes(fn(Router $r) => $r->get('/kalender.ics', …))  eigene Routen
 *   $x->adminPage(['href' => '/admin/kalender', 'label' => 'Kalender', 'kind' => 'content', 'icon' => 'calendar', 'perm' => 'calendar.edit'])
 *                                                                      Seite der Verwaltung; kind content|tool → Menü, settings → Sammelseite
 *                                                                      „Einstellungen“ (+ 'table' => 'handle' an der Datentabelle), stats → „Statistiken“
 *   $x->nav('/admin/kalender', 'Kalender', 'calendar', 'calendar.edit')   Kurzform (ohne Art veraltet: 'main' = content, 'admin' = tool)
 *   $x->permissions('Kalender', ['calendar.edit' => 'Termine pflegen'])   Rechte für Rollen
 *   $x->feature('calendar', 'Kalender', ['calendar.edit'])             je Website abschaltbar
 *   $x->proxy('wetter', [...])                                         externe Quelle über Core\Proxy
 *   $x->table('kalender_termine', fn(Core\Db\Table $t) => $t->id()->column('titel', 'string')->index('titel'))
 *                                                                      Tabelle deklarativ (angeglichen beim Start nach Änderung und mit migrate)
 *   $x->migration(1, fn(Database $db) => …)                            Datenbank-Schritte (einmalig, versioniert – z. B. Daten umstellen)
 *   $x->command('kalender:import', 'Beschreibung', fn(array $args) => …)  CLI
 *   $x->asset('css/kalender.css')                                      URL zu {dir}/public/… (per extensions:publish veröffentlicht)
 *   $x->htmlFilter(fn(string $html, array $ctx) => $html)             HTML-Ausgabe der Website nachbearbeiten (vor dem Seiten-Cache)
 *   $x->csp(fn() => ['script-src' => ['https://…']])                   Quellen je Anfrage zur Website-CSP ergänzen (nie 'unsafe-inline')
 *   $x->footerLinks(fn() => [['label' => …, 'href' => '#…']])          Links in der Rechtliches-Zeile der Theme-Fußbereiche (footer_links())
 *   $x->pageAccess(['restricted' => fn(array $page): bool, 'allow' => fn(array $page, Request $r, ?array $table): bool|Response, 'table' => fn(array $t): bool, 'entry' => fn(array $t, array $e, string $audience): ?array])
 *                                                                      geschützte Seiten (Core\PageAccess: kein Cache, nicht in Menü/Sitemap/Suche)
 * Verwaltung, Mediathek & Betrieb (siehe Technik → „Funktionsumfang & Erweiterungen“):
 *   $x->feature('kalender', 'Kalender', [...], false)                  Funktion Standard AUS (erst 'features' => ['kalender' => true])
 *   $x->adminAssets(fn(string $view) => $view === 'media' ? ['css/x.css', 'js/x.js'] : [])   Dateien aus {dir}/assets in der Verwaltung laden
 *   $x->health(fn() => ['ffmpeg gefunden' => true])                    Zeilen für `php bin/console health` (true/false/null = Warnung)
 *   $x->afterAdminResponse(fn() => $queued ? fn() => work() : null)    billige Prüfung je Verwaltungsaufruf; Rückgabe läuft NACH der Antwort
 *   $x->on('media.deleted', fn(array $m) => …)                         Ereignisse des Cores (media.imported, media.replaced, media.deleted)
 *   $x->mediaChecks(fn() => ['key' => ['label' => …, 'icon' => …, 'where' => "SQL auf m.*", 'kind' => 'video']])   Filter unter „Prüfen“
 *   $x->mediaJson(fn(array $m) => [...] | null)                        Zusatzangaben je Datei in der Mediathek-API (Feld ext.{name})
 *   $x->mediaTypes(['video/webm' => ['ext' => 'webm', 'magic' => "\x1A\x45\xDF\xA3", 'label' => 'WebM']])   weitere Dateitypen der Mediathek
 *   $x->mediaAccess(fn(array $ctx, Request $r): bool|Response => …)   Zugriff auf Dateien geschützter Pools ($ctx: pool, path)
 *   $x->mediaPoster(fn(array $m): ?int => …)                           Vorschaubild (Bild-ID) für Videos ohne eigenes Poster (Themes, Player)
 *   $x->docs('manual'|'technical', ['key' => ['title' => …, 'file' => …, 'after' => 'medien']])   Kapitel im Handbuch/Entwicklerhandbuch
 *   $x->dashboard(fn(array $user) => ['tiles' => [...], 'cards' => [...]])   Kennzahlen-Kacheln und Karten der Übersicht (/admin)
 *   $x->pushEvent('kalender.neu', 'Neue Termine', 'calendar.edit', ['help' => …])   Ereignis unter Konto → Benachrichtigungen;
 *                                                                      senden mit Core\Push\Push::notifyUsers($wer, $inhalt, 'kalender.neu')
 * Slots der Verwaltung (Core\Slots – Daten statt HTML, der Core escaped; optional zweites Argument = Recht):
 *   $x->pageList(fn(array $page) => ['badges' => [['label' => …]], 'actions' => [['label' => …, 'href' => …]]])   Seitenbaum: Hinweis + Kontextmenü
 *   $x->pagePanel(fn(array $page) => ['title' => …, 'text' => …, 'lines' => [...], 'actions' => [...]])   Karte in den Seiteneinstellungen
 *   $x->tableActions(fn(array $t) => ['actions' => [...], 'row' => [['label' => …, 'href' => '/admin/x/{table}/{id}']]])   Datentabelle
 *   $x->mediaPanel(fn(array $m) => ['title' => …, 'lines' => [...]], 'media.upload')   Abschnitt in „Informationen“ der Mediathek
 *   $x->account(fn(array $user) => ['title' => …, 'actions' => [...]])                Abschnitt auf „Konto“
 *   $x->toolbar(fn(array $bar) => ['items' => [...], 'scripts' => ['js/x.js'], 'publishNote' => '…'])   Menü „⋯“ der Werkzeugleiste
 *   $x->frontendTool(['id' => 'notiz', 'label' => 'Notiz', 'icon' => 'note', 'module' => 'js/notiz.mjs', 'shortcut' => 'Alt+N', 'perm' => 'pages.edit'])
 *                                                                      Werkzeug beim Bearbeiten auf der Website (Core\FrontendTools, CMSAdmin.tools)
 * Ereignisse (on): typisiert on(Core\Events\PageSaved::class, fn(PageSaved $e) => …) – Seiten und Einträge (Core\Events\*);
 *   Namen wie 'page.saved' bleiben als Alias (Altform mit Array-Argumenten), media.*, inbox.* – siehe Technik → Erweiterungen
 * Eingangs-Tabellen (Anfragen, Core\Data\Inbox):
 *   $x->inbox(fn(array $t) => $t['handle'] === 'buchungen' ? ['statuses' => [...], 'info' => fn(array $row) => '…'] : null)   eigene Status, Zusatzzeile, Prüfung
 *   $x->on('inbox.status', fn(array $t, array $ids, string $status, array $old) => …)   Ereignisse inbox.status / inbox.deleted
 * Übersetzungen: {dir}/lang/{locale}.php (Verwaltung, __()) und {dir}/lang/site/{lang}.php (Website, lt()) werden automatisch geladen.
 */
final class Extension
{
    public array $routeCallbacks = [];
    /** Schlüssel der Funktionen, die diese Erweiterung angemeldet hat (feature()) */
    public array $featureKeys = [];
    public array $nav = [];
    /** @var list<array> Seiten der Verwaltung (Core\AdminPages::normalize) */
    public array $pages = [];
    public array $perms = [];
    public array $commands = [];
    /** @var list<callable(string, array): string> */
    public array $htmlFilters = [];
    /** @var list<callable(): array<string, list<string>>> */
    public array $cspProviders = [];
    /** @var list<callable(): list<array{label: string, href: string}>> */
    public array $footerLinkProviders = [];
    /** @var list<array{restricted: callable(array): bool, allow: callable(array, Http\Request): (bool|Http\Response)}> Zugriffsschutz (pageAccess) */
    public array $pageGuards = [];
    /** @var list<callable(string): list<string>> */
    public array $adminAssetProviders = [];
    /** @var list<callable(): array<string, ?bool>> */
    public array $healthProviders = [];
    /** @var list<callable(): ?callable> */
    public array $afterAdmin = [];
    /** @var array<string, list<callable>> */
    public array $listeners = [];
    /** @var list<callable(): array> */
    public array $mediaCheckProviders = [];
    /** @var list<callable(array): ?array> */
    public array $mediaJsonProviders = [];
    /** @var array<string, array{ext: string, magic?: string, label?: string}> */
    public array $mediaTypeDefs = [];
    /** @var list<callable(array): ?int> */
    public array $posterProviders = [];
    /** @var list<callable(array, Http\Request): (bool|Http\Response)> Zugriff auf geschützte Pools (mediaAccess) */
    public array $mediaAccessProviders = [];
    /** @var array<string, array<string, array>> */
    public array $docChapters = [];
    /** @var list<callable(array): array> */
    public array $dashboardProviders = [];
    /** @var list<callable(array): array> */
    public array $pageListProviders = [];
    /** @var list<callable(array): string> */
    public array $pagePanelProviders = [];
    /** @var list<callable(array): array> */
    public array $toolbarProviders = [];
    /** @var list<callable(array): ?array> Datentabelle (tableActions) */
    public array $tableActionProviders = [];
    /** @var list<callable(array): ?array> Mediathek, eine Datei (mediaPanel) */
    public array $mediaPanelProviders = [];
    /** @var list<callable(array): ?array> Konto (account) */
    public array $accountProviders = [];
    /** @var list<callable(array): ?array> */
    public array $inboxProviders = [];
    /** @var list<array> Werkzeuge für das Bearbeiten auf der Website (Core\FrontendTools::normalize) */
    public array $frontendTools = [];
    private array $migrations = [];
    /** @var array<string, callable(Db\Table): mixed> Tabellen (table()) */
    private array $tables = [];

    public function __construct(public readonly string $name, public readonly string $dir, public readonly array $manifest) {}

    public function blocks(array $defs, ?string $dir = null): self
    {
        app()->theme->addBlocks($defs, $dir ?? $this->dir . '/blocks', $this->name);
        return $this;
    }

    public function routes(callable $cb): self
    {
        $this->routeCallbacks[] = $cb;
        return $this;
    }

    /**
     * Kurzform für adminPage(): Eintrag in der Verwaltung. $place: 'main' = Hauptmenü, 'admin' = Abschnitt „Administration“ –
     * oder gleich die Art (Core\AdminPages): 'content' | 'tool' (Menü), 'settings' (Sammelseite „Einstellungen“), 'stats'
     * („Statistiken“). $opts: weitere Angaben wie bei adminPage() (table, description, kind, place, visible …).
     * Ohne Art (nur 'main'/'admin') ist der Aufruf veraltet: er bleibt an seinem Platz (main → content, admin → tool).
     */
    public function nav(string $href, string $label, string $icon = 'ext', ?string $perm = null, string $place = 'main', array $opts = []): self
    {
        $def = ['href' => $href, 'label' => $label, 'icon' => $icon, 'perm' => $perm] + $opts;
        if (in_array($place, AdminPages::KINDS, true)) $def['kind'] ??= $place;
        else $def['place'] ??= $place;
        return $this->adminPage($def);
    }

    /**
     * Seite der Verwaltung anmelden (Core\AdminPages): ['href' => '/admin/…', 'label' => …, 'kind' => 'content'|'tool'|'settings'|'stats',
     * 'icon' => Symbol, 'perm' => Recht, 'place' => 'main'|'admin' (content/tool), 'table' => Datentabelle (settings: auch dort als Knopf),
     * 'description' => eine Zeile für die Karte, 'feature' => Funktion muss an sein, 'visible' => fn(): bool].
     * Die Route meldet die Erweiterung selbst mit routes() an – Adresse und Rechteprüfung der Route bleiben ihre Sache.
     */
    public function adminPage(array $def): self
    {
        $p = AdminPages::normalize($def, 'ext:' . $this->name, (string) ($this->manifest['label'] ?? $this->name));
        if (!$p) {
            error_log('[Erweiterung ' . $this->name . '] adminPage: ungültige Angaben (href/label)');
            return $this;
        }
        $this->pages[] = $p;
        // Rückwärtskompatibel: [href, label, icon, perm, place] (Extensions::contributions, ältere Aufrufer)
        $this->nav[] = [$p['href'], $p['label'], $p['icon'], $p['perm'], $p['place'], $p['kind']];
        return $this;
    }

    public function permissions(string $group, array $perms): self
    {
        $this->perms[$group] = array_merge($this->perms[$group] ?? [], $perms);
        return $this;
    }

    /**
     * Ereignis für Push-Benachrichtigungen der Verwaltung (Core\Push): erscheint unter Konto → Benachrichtigungen bei allen, die
     * $perm haben (null = alle). Senden: Core\Push\Push::notifyUsers($wer, ['title' => …, 'body' => …, 'url' => '/admin/…'], $key).
     * $o: help (Zeile unter dem Schalter), default (Schalter vorbelegt, Standard true), feature (nur mit dieser Funktion).
     */
    public function pushEvent(string $key, string $label, ?string $perm = null, array $o = []): self
    {
        \Core\Push\Push::registerEvent($key, $label, $perm, $o + ['owner' => $this->name]);
        return $this;
    }

    /** Funktion je Website; $default = false: aus, bis die Konfiguration sie mit 'features' => [$key => true] einschaltet */
    public function feature(string $key, string $label, array $permissions = [], bool $default = true): self
    {
        Features::register($key, $label, $permissions, $default);
        $this->featureKeys[] = $key;
        return $this;
    }

    public function proxy(string $key, array $def): self
    {
        Proxy::register($key, $def);
        return $this;
    }

    /**
     * Tabelle der Erweiterung deklarativ beschreiben (Core\Db\Table): fn(Table $t) => $t->id()->column(…)->index(…).
     * Angeglichen wird beim Start, wenn sich die Beschreibung geändert hat (Fingerabdruck je Website), und bei jedem `migrate` –
     * vor den migration()-Schritten. Additiv: Spalten entfernt nur dropColumn(). Name am besten mit dem Namen der Erweiterung als Präfix.
     */
    public function table(string $name, callable $define): self
    {
        $this->tables[$name] = $define;
        return $this;
    }

    /** Tabellen (table()) angleichen – nur bei geändertem Fingerabdruck bzw. $force (migrate). @return array<string, string> Name → created|altered|unchanged */
    public function ensureTables(bool $force = false): array
    {
        if (!$this->tables) return [];
        $defs = [];
        foreach ($this->tables as $name => $define) {
            $t = Db\Table::named($name);
            $define($t);
            $defs[$name] = $t;
        }
        $fp = md5(implode('|', array_map(fn(Db\Table $t) => $t->fingerprint(), $defs)));
        $key = 'ext.' . $this->name . '.tables';
        if (!$force && app()->settings->get($key) === $fp) return [];
        $out = [];
        foreach ($defs as $name => $t) $out[$name] = $t->ensure(app()->db);
        app()->settings->set($key, $fp);
        return $out;
    }

    public function migration(int $version, callable $step): self
    {
        $this->migrations[$version] = $step;
        return $this;
    }

    public function command(string $name, string $description, callable $run): self
    {
        $this->commands[$name] = [$description, $run];
        return $this;
    }

    /** HTML einer Website-Seite nachbearbeiten: fn(string $html, array $ctx): string – $ctx: page, editing, loggedIn, status */
    public function htmlFilter(callable $fn): self
    {
        $this->htmlFilters[] = $fn;
        return $this;
    }

    /** Quellen zur Website-CSP ergänzen (je Anfrage, auch bei Treffern im Seiten-Cache): fn(): ['script-src' => [...], 'frame-src' => [...]] */
    public function csp(callable $fn): self
    {
        $this->cspProviders[] = $fn;
        return $this;
    }

    /** Links für die Rechtliches-Zeile im Fußbereich der Themes (footer_links()): fn(): [['label' => …, 'href' => …], …] */
    public function footerLinks(callable $fn): self
    {
        $this->footerLinkProviders[] = $fn;
        return $this;
    }

    /**
     * Seiten schützen (Core\PageAccess): 'restricted' => fn(array $page): bool (billig, für alle Besucher gleich),
     * 'allow' => fn(array $page, Http\Request $r): bool|Http\Response (true = zeigen, false = 404, Response z. B. Anmeldung).
     */
    /**
     * Zugriff auf Dateien geschützter Medien-Pools (Core\MediaPools, Adresse /geschuetzt/…): fn(array $ctx, Http\Request $r): bool|Http\Response
     * – $ctx: pool, path. true = ausliefern, Response = z. B. Weiterleitung zur Anmeldung. Angemeldete Redaktion darf immer.
     */
    public function mediaAccess(callable $fn): self
    {
        $this->mediaAccessProviders[] = $fn;
        return $this;
    }

    public function pageAccess(array $def): self
    {
        if (!is_callable($def['restricted'] ?? null) || !is_callable($def['allow'] ?? null)) {
            throw new \InvalidArgumentException('pageAccess braucht restricted und allow');
        }
        $this->pageGuards[] = ['restricted' => $def['restricted'], 'allow' => $def['allow']] + array_filter(['table' => $def['table'] ?? null, 'entry' => $def['entry'] ?? null], 'is_callable');
        return $this;
    }

    /** CSS/JS aus {dir}/assets (gebaut nach public/assets/ext/{name}) in der Verwaltung laden: fn(string $view): ['css/x.css', 'js/x.js'] – $view z. B. 'media' */
    public function adminAssets(callable $fn): self
    {
        $this->adminAssetProviders[] = $fn;
        return $this;
    }

    /** Prüfungen für `php bin/console health`: fn(): ['Bezeichnung' => true|false|null] (null = Warnung, kein Deploy-Fehler) */
    public function health(callable $fn): self
    {
        $this->healthProviders[] = $fn;
        return $this;
    }

    /**
     * Arbeit nach der Antwort eines Verwaltungsaufrufs (z. B. wartende Hintergrund-Aufträge): fn(): ?callable.
     * Die Prüfung läuft bei JEDEM Aufruf und muss billig sein; der zurückgegebene Callable läuft nach fastcgi_finish_request().
     */
    public function afterAdminResponse(callable $fn): self
    {
        $this->afterAdmin[] = $fn;
        return $this;
    }

    /**
     * Ereignis des Cores abonnieren. Typisiert (empfohlen): on(Core\Events\PageSaved::class, fn(PageSaved $e) => …) – Seiten:
     * PageSaved, PagePublished, PageUnpublished, PageDiscarded, PageDeleted; Einträge: EntrySaved, EntryPublished, EntryUnpublished,
     * EntryDeleted (readonly: table, id, lang, userId, state 'draft'|'live' + Datensatz). Der Name ('page.saved') ist ein Alias:
     * Listener mit Ereignis-Typ am ersten Parameter bekommen das Objekt, alle anderen die bisherigen Argumente (Altform):
     * page.saved (array $page, ?int $userId), page.published|unpublished|discarded|deleted (array $page), entry.saved (array $table,
     * array $entry, bool $created, ?array $old), entry.published|unpublished (array $table, array $entry), entry.deleted (array $table,
     * int $id). Ohne Typ (nur Name): media.imported (array $m), media.replaced (array $neu, array $alt), media.edited, media.deleted
     * (array $m), inbox.status, inbox.deleted.
     */
    public function on(string $event, callable $fn): self
    {
        $this->listeners[ltrim($event, '\\')][] = $fn;
        return $this;
    }

    /**
     * Filter unter „Prüfen“ in der Mediathek: fn(): ['schlüssel' => ['label' => …, 'icon' => Symbolname, 'where' => SQL-Bedingung auf
     * Alias m (Tabelle media, feste Werte – keine Eingaben), 'params' => [...], 'kind' => 'video'|null]]. Parameter der API: check=schlüssel
     */
    public function mediaChecks(callable $fn): self
    {
        $this->mediaCheckProviders[] = $fn;
        return $this;
    }

    /** Zusatzangaben je Datei in der Mediathek-API (Media::toJson → ext.{name}): fn(array $m): ?array */
    public function mediaJson(callable $fn): self
    {
        $this->mediaJsonProviders[] = $fn;
        return $this;
    }

    /** Weitere Dateitypen der Mediathek: ['video/webm' => ['ext' => 'webm', 'magic' => Dateianfang (optional), 'label' => 'WebM']] */
    public function mediaTypes(array $defs): self
    {
        foreach ($defs as $mime => $d) {
            if (preg_match('~^(video|audio)/[a-z0-9.+-]{2,40}$~', (string) $mime) && preg_match('~^[a-z0-9]{2,5}$~', (string) ($d['ext'] ?? ''))) {
                $this->mediaTypeDefs[(string) $mime] = $d;
            }
        }
        return $this;
    }

    /** Vorschaubild für ein Video ohne eigenes Poster (Bild-ID aus der Mediathek): fn(array $m): ?int */
    public function mediaPoster(callable $fn): self
    {
        $this->posterProviders[] = $fn;
        return $this;
    }

    /**
     * Kapitel im Handbuch ('manual', Format wie kits/{name}/docs/manual.php → 'chapters') bzw. im Entwicklerhandbuch
     * ('technical': ['key' => ['title' => …, 'file' => …, 'part' => 0–4, 'after' => 'medien']])
     */
    public function docs(string $book, array $chapters): self
    {
        $this->docChapters[$book] = array_merge($this->docChapters[$book] ?? [], $chapters);
        return $this;
    }

    /**
     * Übersicht der Verwaltung (/admin, Core\Dashboard\Dashboard): fn(array $user): [
     *   'tiles' => [['key' => 'besuche', 'label' => 'Besuche', 'value' => '1.234', 'text' => '30 Tage', 'level' => 0–100 (Füllstand, optional),
     *               'trend' => ['now' => 1234, 'prev' => 1100, 'label' => 'Besuche'] (optional), 'href' => '/admin/…' (optional)]],
     *   'cards' => ['besucher' => ['title' => 'Besucher', 'icon' => 'chart-line', 'size' => 'third'|'half'|'two-thirds'|'full',
     *               'body' => ['text' => …, 'lines' => [...], 'actions' => [...]] (Core\Slots::card, escaped vom Core) ODER
     *               'render' => fn(): string (Altform: HTML, selbst escapen), 'lazy' => true (nachladen, 'ttl' Sekunden Zwischenspeicher je Website)]],
     * ]. Recht: zweites Argument (Slot nur mit can($perm)) bzw. in der Funktion; leeres Array = nichts anzeigen. Fehler werden protokolliert.
     */
    public function dashboard(callable $fn, ?string $perm = null): self
    {
        $this->dashboardProviders[] = self::gated($fn, $perm, []);
        return $this;
    }

    /**
     * Seitenbaum (Verwaltung → Seiten) je Seite: fn(array $page): [
     *   'badges'  => [['label' => 'Freigegeben', 'title' => 'Tooltip (optional)', 'tone' => 'ok'|'warn'|'info'|'muted']],   Hinweis in der Spalte „Status“
     *   'actions' => [['label' => 'Entwurf teilen …', 'href' => '/admin/…']],                                         Einträge im Kontextmenü (Link)
     * ]. Läuft für JEDE Seite des Baums – Daten einmal vorab laden (statischer Zwischenspeicher), Rechte selbst prüfen (can()).
     */
    public function pageList(callable $fn, ?string $perm = null): self
    {
        $this->pageListProviders[] = self::gated($fn, $perm, []);
        return $this;
    }

    /**
     * Seiteneinstellungen (Verwaltung → Seiten → Seite): fn(array $page): ?array – Karte in der Seitenleiste (Core\Slots::card:
     * title, text, tone, lines, actions; escaped vom Core). Altform: fertiges, selbst escaptes HTML als Zeichenkette.
     */
    public function pagePanel(callable $fn, ?string $perm = null): self
    {
        $this->pagePanelProviders[] = self::gated($fn, $perm, null);
        return $this;
    }

    /**
     * Datentabelle (Verwaltung → Daten → Tabelle): fn(array $table): ?array – [
     *   'actions' => [['label' => 'Exportieren', 'href' => '/admin/x/{table}/export', 'icon' => 'download']],   Knöpfe im Kopf der Liste
     *   'row'     => [['label' => 'Senden', 'href' => '/admin/x/{table}/{id}']],                                  Aktion je Zeile
     * ]. Platzhalter {table} (Kurzname) und {id} ersetzt der Core. Eigene Seiten an der Tabelle: adminPage(['kind' => 'settings', 'table' => …]).
     */
    public function tableActions(callable $fn, ?string $perm = null): self
    {
        $this->tableActionProviders[] = self::gated($fn, $perm, null);
        return $this;
    }

    /** Mediathek (eine Datei, „Informationen“): fn(array $m): ?array – Karte (Core\Slots::card) als eigener Abschnitt */
    public function mediaPanel(callable $fn, ?string $perm = null): self
    {
        $this->mediaPanelProviders[] = self::gated($fn, $perm, null);
        return $this;
    }

    /** Konto (/admin/account): fn(array $user): ?array – Karte (Core\Slots::card) als eigener Abschnitt, z. B. persönliche Einstellungen der Erweiterung */
    public function account(callable $fn, ?string $perm = null): self
    {
        $this->accountProviders[] = self::gated($fn, $perm, null);
        return $this;
    }

    /** Slot nur mit Recht: ohne $perm unverändert, sonst $empty, wenn can($perm) fehlt */
    private static function gated(callable $fn, ?string $perm, mixed $empty): callable
    {
        if ($perm === null || $perm === '') return $fn;
        return static fn(mixed ...$a) => can($perm) ? $fn(...$a) : $empty;
    }

    /**
     * Redaktions-Werkzeugleiste auf der Website (Core\Toolbar, nur angemeldet): fn(array $bar): [
     *   'items'       => [['label' => …, 'hint' => 'kleine Zeile (optional)', 'icon' => Symbolname, 'href' => Adresse ODER 'data' => ['fg-open' => '1'] (→ data-…-Attribute an einem <button>)]],
     *   'scripts'     => ['js/panel.js'],     Skripte aus {dir}/assets (gebaut nach /assets/ext/{name}/), im Dokument nach der Leiste – nur 'self'
     *   'publishNote' => 'Freigegeben von …', Zusatz im Dialog „Änderungen jetzt veröffentlichen?“
     * ]. $bar = Core\Toolbar::context() (kind, mode, page, editing, canEditPages …). Rechte prüft die Erweiterung (can()).
     */
    public function toolbar(callable $fn): self
    {
        $this->toolbarProviders[] = $fn;
        return $this;
    }

    /**
     * Werkzeug für das Bearbeiten auf der Website (Core\FrontendTools): Knopf in der Werkzeugleiste ('placement' => 'main') bzw.
     * Eintrag im Menü „⋯“ ('more'), optional Tastenkürzel ('shortcut' => 'Alt+G'). 'module' => 'js/x.mjs' aus {dir}/assets wird
     * erst beim ersten Öffnen geladen (ES-Modul: export default { mount(ctx), unmount(ctx) }). Nur angemeldet, nur beim Bearbeiten,
     * nur mit Recht ('perm', 'table', 'feature', 'visible' => fn(array $bar): bool). Endpunkte prüfen Rechte und CSRF selbst.
     */
    public function frontendTool(array $def): self
    {
        $t = FrontendTools::normalize($def, 'ext:' . $this->name, $this);
        if ($t) $this->frontendTools[] = $t;
        else error_log('[Erweiterung ' . $this->name . '] frontendTool: ungültige Angaben (id/label/module)');
        return $this;
    }

    /**
     * Eingangs-Tabellen, die die Erweiterung verantwortet (z. B. Buchungen): fn(array $t): ?array – null = nicht zuständig, sonst [
     *   'statuses' => ['neu' => ['label' => 'Anfrage', 'action' => 'Wieder öffnen', 'tone' => 'warn'|'ok'|'muted'|'info', 'done' => false, 'manual' => true], …]
     *                 eigener Status-Satz (Schlüssel a–z/0–9/_, max. 20 Zeichen; „neu“ muss vorkommen = offen/Badge; done = true → wird nach
     *                 retention_days gelöscht wie „erledigt“),
     *   'info'     => fn(array $row): string   Zusatzzeile je Anfrage in der Liste (Klartext, nie personenbezogene Inhalte),
     *   'guard'    => fn(array $ids, string $status): ?string   vor jeder Statusänderung (Verwaltung, API, MCP) – Text = Abbruch mit dieser Meldung,
     *   'direct_form' => false   Formular nur über die Erweiterung (kein /formular/{handle}, nicht im Block „data_form“).
     * ]. Ereignisse danach: on('inbox.status', fn($t, $ids, $status, $old)) und on('inbox.deleted', fn($t, $ids)) – auch beim automatischen Löschen.
     */
    public function inbox(callable $fn): self
    {
        $this->inboxProviders[] = $fn;
        return $this;
    }

    /** Öffentliche Adresse einer Datei aus {dir}/public bzw. {dir}/assets (nach extensions:publish bzw. pnpm build): /assets/ext/{name}/… */
    public function asset(string $path): string
    {
        $file = $this->publicDir() . '/' . ltrim($path, '/');
        $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : (string) ($this->manifest['version'] ?? '1');
        return PublicPaths::url(PublicPaths::EXT, $this->name, $path) . '?v=' . $v;
    }

    /** Öffentlicher Ordner der Erweiterung: public/assets/ext/{name} (Core\PublicPaths) */
    public function publicDir(): string
    {
        return PublicPaths::dir(PublicPaths::EXT, $this->name);
    }

    /** Offene Datenbank-Schritte ausführen (Stand je Website in den Einstellungen) */
    public function runMigrations(): void
    {
        if (!$this->migrations) return;
        ksort($this->migrations);
        $key = 'ext.' . $this->name . '.schema';
        $done = (int) app()->settings->get($key, 0);
        foreach ($this->migrations as $v => $step) {
            if ($v <= $done) continue;
            app()->db->transaction(fn() => $step(app()->db));
            app()->settings->set($key, $v);
            $done = $v;
        }
    }
}
