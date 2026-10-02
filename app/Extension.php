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
 *   $x->migration(1, fn(Database $db) => …)                            Datenbank-Schritte (einmalig, versioniert)
 *   $x->command('kalender:import', 'Beschreibung', fn(array $args) => …)  CLI
 *   $x->asset('css/kalender.css')                                      URL zu {dir}/public/… (per extensions:publish veröffentlicht)
 *   $x->htmlFilter(fn(string $html, array $ctx) => $html)             HTML-Ausgabe der Website nachbearbeiten (vor dem Seiten-Cache)
 *   $x->csp(fn() => ['script-src' => ['https://…']])                   Quellen je Anfrage zur Website-CSP ergänzen (nie 'unsafe-inline')
 *   $x->footerLinks(fn() => [['label' => …, 'href' => '#…']])          Links in der Rechtliches-Zeile der Theme-Fußbereiche (footer_links())
 * Verwaltung, Mediathek & Betrieb (siehe Technik → „Funktionsumfang & Erweiterungen“):
 *   $x->feature('kalender', 'Kalender', [...], false)                  Funktion Standard AUS (erst 'features' => ['kalender' => true])
 *   $x->adminAssets(fn(string $view) => $view === 'media' ? ['css/x.css', 'js/x.js'] : [])   Dateien aus {dir}/assets in der Verwaltung laden
 *   $x->health(fn() => ['ffmpeg gefunden' => true])                    Zeilen für `php bin/console health` (true/false/null = Warnung)
 *   $x->afterAdminResponse(fn() => $queued ? fn() => work() : null)    billige Prüfung je Verwaltungsaufruf; Rückgabe läuft NACH der Antwort
 *   $x->on('media.deleted', fn(array $m) => …)                         Ereignisse des Cores (media.imported, media.replaced, media.deleted)
 *   $x->mediaChecks(fn() => ['key' => ['label' => …, 'icon' => …, 'where' => "SQL auf m.*", 'kind' => 'video']])   Filter unter „Prüfen“
 *   $x->mediaJson(fn(array $m) => [...] | null)                        Zusatzangaben je Datei in der Mediathek-API (Feld ext.{name})
 *   $x->mediaTypes(['video/webm' => ['ext' => 'webm', 'magic' => "\x1A\x45\xDF\xA3", 'label' => 'WebM']])   weitere Dateitypen der Mediathek
 *   $x->mediaPoster(fn(array $m): ?int => …)                           Vorschaubild (Bild-ID) für Videos ohne eigenes Poster (Themes, Player)
 *   $x->docs('manual'|'technical', ['key' => ['title' => …, 'file' => …, 'after' => 'medien']])   Kapitel im Handbuch/Entwicklerhandbuch
 *   $x->dashboard(fn(array $user) => ['tiles' => [...], 'cards' => [...]])   Kennzahlen-Kacheln und Karten der Übersicht (/admin)
 * Seiten (Verwaltung und Werkzeugleiste der Website):
 *   $x->pageList(fn(array $page) => ['badges' => [['label' => …]], 'actions' => [['label' => …, 'href' => …]]])   Seitenbaum: Hinweis + Kontextmenü
 *   $x->pagePanel(fn(array $page) => '<section class="adm-card">…</section>')   Karte in der Seitenleiste der Seiteneinstellungen
 *   $x->toolbar(fn(array $bar) => ['items' => [...], 'scripts' => ['js/x.js'], 'publishNote' => '…'])   Menü „⋯“ der Werkzeugleiste
 *   $x->frontendTool(['id' => 'notiz', 'label' => 'Notiz', 'icon' => 'note', 'module' => 'js/notiz.mjs', 'shortcut' => 'Alt+N', 'perm' => 'pages.edit'])
 *                                                                      Werkzeug beim Bearbeiten auf der Website (Core\FrontendTools, CMSAdmin.tools)
 * Ereignisse (on): page.saved, page.published, page.unpublished, page.discarded, page.deleted, entry.saved, entry.published,
 *   entry.unpublished, entry.deleted, media.*, inbox.* – siehe Technik → Erweiterungen
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
    /** @var list<callable(array): ?array> */
    public array $inboxProviders = [];
    /** @var list<array> Werkzeuge für das Bearbeiten auf der Website (Core\FrontendTools::normalize) */
    public array $frontendTools = [];
    private array $migrations = [];

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
     * Ereignis des Cores abonnieren: media.imported (array $m), media.replaced (array $neu, array $alt), media.deleted (array $m),
     * page.saved (array $page, ?int $userId), page.published (array $page), page.unpublished (array $page), page.discarded (array $page),
     * page.deleted (array $page), entry.saved (array $table, array $entry, bool $created, ?array $old), entry.published (array $table,
     * array $entry), entry.unpublished (array $table, array $entry), entry.deleted (array $table, int $id), inbox.status, inbox.deleted
     */
    public function on(string $event, callable $fn): self
    {
        $this->listeners[$event][] = $fn;
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
     *               'render' => fn(): string (HTML, selbst escapen), 'lazy' => true (nachladen, 'ttl' Sekunden Zwischenspeicher je Website)]],
     * ]. Rechte prüft die Erweiterung selbst (can()); leeres Array = nichts anzeigen. Fehler werden protokolliert, die Übersicht bleibt stehen.
     */
    public function dashboard(callable $fn): self
    {
        $this->dashboardProviders[] = $fn;
        return $this;
    }

    /**
     * Seitenbaum (Verwaltung → Seiten) je Seite: fn(array $page): [
     *   'badges'  => [['label' => 'Freigegeben', 'title' => 'Tooltip (optional)', 'tone' => 'ok'|'warn'|'info'|'muted']],   Hinweis in der Spalte „Status“
     *   'actions' => [['label' => 'Entwurf teilen …', 'href' => '/admin/…']],                                         Einträge im Kontextmenü (Link)
     * ]. Läuft für JEDE Seite des Baums – Daten einmal vorab laden (statischer Zwischenspeicher), Rechte selbst prüfen (can()).
     */
    public function pageList(callable $fn): self
    {
        $this->pageListProviders[] = $fn;
        return $this;
    }

    /** Seiteneinstellungen (Verwaltung → Seiten → Seite): fn(array $page): string – fertiges, selbst escaptes HTML (z. B. <section class="adm-card">) in der Seitenleiste */
    public function pagePanel(callable $fn): self
    {
        $this->pagePanelProviders[] = $fn;
        return $this;
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

    /** Öffentlicher Ordner der Erweiterung: public/assets/ext/{name} (Rückfall public/extensions/{name}, siehe Core\PublicPaths) */
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
