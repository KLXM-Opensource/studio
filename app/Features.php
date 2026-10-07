<?php
declare(strict_types=1);

namespace Core;

/**
 * Funktionsumfang je Website.
 *
 * Konfiguration in config/sites/{key}.php (bzw. config/config.local.php für die Hauptwebsite):
 *   'preset'      => 'full' | 'content' | 'minimal',        // Ausgangspunkt (Standard: full)
 *   'features'    => ['data.schema' => false, 'api' => false], // einzelne Funktionen an/aus
 *   'blocks'      => ['deny' => ['video'], 'allow' => null],  // Blocktypen für die Redaktion
 *   'integrators' => ['agentur@example.com'],                 // Konten, die immer alles sehen (z. B. Agentur)
 *
 * Zusätzlich schaltet die Verwaltung „Administration → Funktionen & Erweiterungen“ (FeaturesController) einzelne Funktionen
 * je Website (Einstellung sys.features_ui). Vorrang: Katalog-Standard → UI-Schalter → Preset → 'features' der Konfiguration.
 * Was die Konfiguration nennt (auch über das Preset), ist in der Verwaltung gesperrt („per Konfiguration festgelegt“);
 * `php bin/console features:release` überträgt solche Werte in die UI-Schalter und entfernt sie aus der Konfiguration.
 *
 * Wer schalten darf: Recht system.features („Haupt-Admin“, Administration hat es) – in Netzwerk-Installationen nur,
 * wenn die Netzwerk-Administration es für die Website freigibt (sys.features_delegate); Integratoren/Netzwerk-Konten immer.
 *
 * Abgeschaltete Funktionen sperren ihre Rechte für alle Rollen (auch „Administration“) –
 * Navigation, Seiten, API und MCP folgen dem automatisch.
 */
final class Features
{
    private static ?array $state = null;
    /** Zustand vor den Abhängigkeiten (Schalter „an“, aber Voraussetzung fehlt → „ruht“) */
    private static array $raw = [];
    private static array $extra = [];
    /** Funktionen von Erweiterungen, die ohne ausdrückliche Freigabe in der Konfiguration aus sind */
    private static array $extraOff = [];

    /** Katalog: Schlüssel => [Bezeichnung, zugehörige Rechte] */
    public static function catalog(): array
    {
        return array_merge([
            'pages.structure' => ['Seiten anlegen, verschieben und löschen', ['pages.manage']],
            'settings' => ['Zentrale Angaben des Kits', ['settings.edit']],
            'media' => ['Mediathek', ['media.upload', 'media.delete']],
            // Untertitel, Kapitel und Transkripte für Video/Audio (Core\MediaTracks); KI-Transkription zusätzlich über „ai“
            'media.captions' => ['Untertitel & Transkripte für Videos und Audio', []],
            // SVG-Grafiken hochladen – nur bereinigt und optimiert (Core\Svg); abschaltbar, falls eine Website keine SVG möchte
            'media.svg' => ['SVG-Grafiken hochladen (bereinigt)', []],
            'data' => ['Datentabellen (Collections)', ['data.edit', 'data.publish', 'data.delete', 'data.schema']],
            'data.schema' => ['Tabellen und Felder selbst anlegen (Tabellen-Designer)', ['data.schema']],
            'data.shared' => ['Geteilte Daten verwalten (Tabellen mehrerer Websites)', ['data.shared.manage']],
            'requests' => ['Anfragen: verschlüsselte Eingangs-Tabellen und ihre Formulare', ['requests.read', 'requests.manage']],
            // Anfragen je Eingang auch per E-Mail zustellen – mit vollem Inhalt, optional S/MIME (Core\Data\Delivery); Standard je Tabelle: nur im System
            'requests.mail' => ['Anfragen per E-Mail zustellen (mit Inhalt, optional S/MIME-verschlüsselt)', []],
            'forms.data' => ['Formulare für Datentabellen (Inhaltstabellen)', []],
            'users' => ['Benutzer und Rollen verwalten', ['users.manage']],
            'system' => ['Grundeinstellungen', ['system.manage']],
            'api' => ['REST-API', []],
            'mcp' => ['MCP-Server für KI-Assistenten', []],
            'languages' => ['Mehrsprachigkeit', []],
            'maps' => ['Karten', []],
            'calendar' => ['Kalender (Termine, Wiederholungen, iCal)', []],
            'pwa' => ['App-Icon & installierbare Web-App', []],
            'theme_switch' => ['Kit in den Grundeinstellungen wechseln', []],
            'design' => ['Design anpassen (Style-Editor)', ['design.edit']],
            // Schriften aus dem Google-Fonts-Katalog laden und selbst ausliefern (Core\Fonts, Grundeinstellungen → Schriften)
            'fonts' => ['Schriften aus Google Fonts installieren (selbst gehostet)', []],
            // Website-Suche für Besucher (/suche), optional semantisch über einen KI-Anbieter (config 'ai', Core\AI\Ai)
            'search' => ['Website-Suche für Besucher (optional semantisch mit KI)', []],
            // Landingpages mit eigenen Domains (Core\Landings): weitere Domains zeigen eine Seite bzw. einen Seitenzweig
            'landings' => ['Landingpages mit eigenen Domains', []],
            // Weiterleitungen (Core\Redirects): alte Adressen → Seiten/Pfade/URLs, 410, automatisch beim Umbenennen, 404-Protokoll
            'redirects' => ['Weiterleitungen und 404-Protokoll', ['redirects.manage']],
            // KI-Funktionen (Symfony AI, Core\AI\Ai): Texte, Übersetzung, SEO, Alt-Texte, semantische Suche – je Website zusätzlich einzuschalten
            'ai' => ['KI-Funktionen (Symfony AI): Texte, Übersetzung, SEO, Alt-Texte, semantische Suche', ['ai.use']],
            // KI-Chats (Core\AI\VisitorChat, Core\AI\Assistant): Besucher-Chat auf der Website (Standard AUS – erst die Agentur
            // gibt ihn frei, dann schaltet die Website ihn unter Grundeinstellungen → KI ein) und Assistent der Redaktion
            'chat.visitor' => ['KI-Chat für Besucher (antwortet nur aus den Inhalten der Website)', []],
            'chat.assistant' => ['KI-Assistent der Redaktion (Hilfe, Kontext, Aktionen mit Bestätigung)', []],
            // Prüf-Ebene: Änderungen über API, MCP und KI protokollieren und zur Freigabe vorlegen (Core\Review\Queue)
            'review' => ['Eingereicht: Änderungen von API, MCP und KI prüfen und freigeben', ['review.manage']],
            // Presets lassen „support“ an: auch mit eingeschränktem Umfang kann die Redaktion Probleme melden
            'support' => ['Support & Wissensdatenbank (Probleme melden, Fragen & Antworten)', ['support.report', 'support.answer', 'support.manage']],
            // Block-Designer (Core\Blocks\Custom): eigene Blöcke aus Feldern, sicherer Vorlage und begrenztem CSS
            'blocks.custom' => ['Eigene Blöcke bauen (Block-Designer)', ['blocks.build']],
            // Chat zwischen Benutzern der Verwaltung (Core\Chat) – Standard aus; einschalten per config oder Chat-Einstellungen (Netzwerk/Integratoren)
            'chat' => ['Chat zwischen Benutzern der Verwaltung (Direktnachrichten, Kanäle)', ['chat.use', 'chat.manage']],
            // Externe Quellen (Core\Sources): Feeds, JSON-APIs, XML und OpenImmo in Datentabellen übernehmen – Standard aus;
            // einschalten per config 'features' => ['sources' => true] oder unter Daten → Externe Quellen (Netzwerk/Integratoren)
            'sources' => ['Externe Quellen: Feeds, APIs und OpenImmo in Datentabellen übernehmen', ['sources.manage']],
            // Glossar (Core\Glossary): Fachbegriffe als Datentabelle, erstes Vorkommen im Text mit Erklärung, Übersicht A–Z – Standard aus
            'glossary' => ['Glossar: Fachbegriffe auf der Website erklären (Hinweis im Text, Übersicht A–Z)', []],
            // Push-Benachrichtigungen (Core\Push): Redaktion (Konto → Benachrichtigungen) und Besucher (Abo neuer Einträge je Datentabelle) –
            // Standard aus; VAPID-Schlüssel entstehen beim Einschalten (config.local.php)
            'push' => ['Push-Benachrichtigungen (Redaktion und Abos neuer Einträge für Besucher)', ['push.view', 'push.send']],
        ], self::$extra);
    }

    /** Funktionen, die ohne ausdrückliche Freigabe in der Konfiguration aus sind (Besucher-Texte gehen an einen KI-Anbieter) */
    public const OFF_BY_DEFAULT = ['chat.visitor', 'glossary', 'push'];

    /** UI-Schalter je Website (nur ausdrücklich gesetzte Werte): ['api' => false, 'mcp' => true] */
    public const UI_KEY = 'sys.features_ui';
    /** Netzwerk: Website-Administration darf selbst schalten (Recht system.features wirkt) – setzt nur die Netzwerk-Administration */
    public const DELEGATE_KEY = 'sys.features_delegate';
    /** Funktionen mit eigenem, älterem Schalter der Website (Chat-Einstellungen, Externe Quellen) – die UI schreibt dorthin */
    public const SWITCH_KEYS = ['chat' => 'sys.userchat_enabled', 'sources' => 'sys.sources_enabled'];
    /** Hart voneinander abhängige Funktionen: Funktion => Voraussetzung (ohne sie wirkungslos) */
    public const REQUIRES = [
        'data.schema' => 'data', 'data.shared' => 'data', 'calendar' => 'data', 'forms.data' => 'data', 'sources' => 'data',
        'chat.visitor' => 'ai', 'chat.assistant' => 'ai', 'media.svg' => 'media', 'requests.mail' => 'requests', 'glossary' => 'data',
    ];

    /** Voreinstellungen für typische Projekte */
    public const PRESETS = [
        'full' => [],
        // Redaktion pflegt Inhalte – Struktur, Technik und Schnittstellen bleiben bei der Agentur
        'content' => ['data.schema' => false, 'data.shared' => false, 'system' => false, 'api' => false, 'mcp' => false, 'theme_switch' => false, 'design' => false, 'fonts' => false, 'users' => false, 'landings' => false, 'blocks.custom' => false],
        // Nur Texte und Bilder bestehender Seiten
        'minimal' => ['pages.structure' => false, 'data' => false, 'data.schema' => false, 'data.shared' => false, 'requests' => false, 'users' => false,
            'system' => false, 'api' => false, 'mcp' => false, 'theme_switch' => false, 'design' => false, 'fonts' => false, 'languages' => false, 'search' => false, 'ai' => false, 'landings' => false, 'blocks.custom' => false],
    ];

    /** Erweiterungen können eigene Funktionen anmelden (Standard: an; $default = false → erst per 'features' => [$key => true]) */
    public static function register(string $key, string $label, array $permissions = [], bool $default = true): void
    {
        self::$extra[$key] = [$label, $permissions];
        if ($default) unset(self::$extraOff[$key]); else self::$extraOff[$key] = true;
        self::$state = null;
    }

    /** Wirksamer Zustand aller Funktionen */
    public static function all(): array
    {
        if (self::$state !== null) return self::$state;
        $cfg = app()->config;
        $preset = (string) $cfg->get('preset', 'full');
        $state = array_fill_keys(array_keys(self::catalog()), true);
        // Standard AUS (bis 'features' => ['…' => true] in der Konfiguration bzw. der Schalter in der Verwaltung sie einschaltet)
        foreach ([...self::OFF_BY_DEFAULT, ...array_keys(self::$extraOff)] as $k) if (isset($state[$k])) $state[$k] = false;
        // Schalter der Verwaltung (Funktionen & Erweiterungen) – Preset und Konfiguration gehen vor
        foreach (self::ui() as $k => $on) {
            if (isset($state[$k]) && !isset(self::SWITCH_KEYS[$k])) $state[$k] = (bool) $on;
        }
        foreach (array_merge(self::PRESETS[$preset] ?? [], (array) $cfg->get('features', [])) as $k => $on) {
            if (isset($state[$k])) $state[$k] = (bool) $on;
        }
        // Chat: ohne ausdrückliche Angabe in 'features' aus – außer die Netzwerk-Administration hat ihn für die Website eingeschaltet
        if (!array_key_exists('chat', (array) $cfg->get('features', []))) $state['chat'] = Chat\Chat::siteSwitch();
        // Externe Quellen: ebenso Standard aus, Schalter der Website (Netzwerk-Administration/Integratoren), sofern config nichts vorgibt
        if (!array_key_exists('sources', (array) $cfg->get('features', []))) $state['sources'] = Sources\Sources::siteSwitch();
        self::$raw = $state;
        // Ohne Datentabellen kein Tabellen-Designer
        if (!$state['data']) $state['data.schema'] = false;
        if (!$state['data']) $state['data.shared'] = false;
        // Kalender baut auf Datentabellen auf
        if (!$state['data']) $state['calendar'] = false;
        // Öffentliche Formulare legen Einträge in Datentabellen an
        if (!$state['data']) $state['forms.data'] = false;
        // Externe Quellen schreiben in Datentabellen
        if (!$state['data']) $state['sources'] = false;
        // Glossar: Begriffe stehen in einer Datentabelle
        if (!$state['data']) $state['glossary'] = false;
        return self::$state = $state;
    }

    /** Eingestellt „an“, wirkt aber nicht, weil die Voraussetzung (REQUIRES) aus ist */
    public static function dormant(string $key): bool
    {
        $all = self::all();
        $dep = self::REQUIRES[$key] ?? null;
        return $dep !== null && !($all[$dep] ?? true) && (self::$raw[$key] ?? false) && !($all[$key] ?? false);
    }

    /** Zwischenspeicher verwerfen (nach dem Umschalten in der Verwaltung, Erweiterungen) */
    public static function flush(): void
    {
        self::$state = null;
    }

    /** Schalter der Verwaltung (sys.features_ui) – ohne Datenbank/Einstellungen: [] */
    public static function ui(): array
    {
        try {
            return isset(app()->settings) ? array_map('boolval', (array) app()->settings->get(self::UI_KEY, [])) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** UI-Schalter einer Funktion setzen (Chat/Externe Quellen: deren eigener Schalter) */
    public static function setUi(string $key, bool $on): void
    {
        if (isset(self::SWITCH_KEYS[$key])) {
            app()->settings->set(self::SWITCH_KEYS[$key], $on ? 1 : 0);
        } else {
            $ui = self::ui();
            $ui[$key] = $on;
            ksort($ui);
            app()->settings->set(self::UI_KEY, $ui);
        }
        self::flush();
    }

    /**
     * Legt die Konfiguration den Wert fest? ['via' => 'features'|'preset', 'file' => 'config/sites/kunde.php', 'value' => bool] oder null.
     * Gesperrte Schalter zeigt die Verwaltung als „per Konfiguration festgelegt“.
     */
    public static function lock(string $key): ?array
    {
        $cfg = app()->config;
        $f = (array) $cfg->get('features', []);
        if (array_key_exists($key, $f)) return ['via' => 'features', 'file' => self::configFile('features'), 'value' => (bool) $f[$key]];
        $preset = (string) $cfg->get('preset', 'full');
        if (array_key_exists($key, self::PRESETS[$preset] ?? [])) {
            return ['via' => 'preset', 'preset' => $preset, 'file' => self::configFile('preset'), 'value' => (bool) self::PRESETS[$preset][$key]];
        }
        return null;
    }

    /** Datei, die einen Schlüssel der obersten Ebene für diese Website setzt (relativ zu ROOT) */
    public static function configFile(string $top, ?string $site = null): string
    {
        $site ??= site()->key;
        if (array_key_exists($top, Sites::all()[$site] ?? [])) return 'config/sites/' . $site . '.php';
        $local = ROOT . '/config/config.local.php';
        if (is_file($local)) {
            $l = (static fn(string $__f) => require $__f)($local);
            if (is_array($l) && array_key_exists($top, $l)) return 'config/config.local.php';
        }
        return 'config/config.php';
    }

    /** Netzwerk-Installation (mehrere Websites): Schalten nur mit Freigabe der Netzwerk-Administration */
    public static function networked(): bool
    {
        return Sites::multi();
    }

    /** Hat die Netzwerk-Administration der Website-Administration das Schalten freigegeben? */
    public static function delegated(): bool
    {
        try {
            return (bool) app()->settings->get(self::DELEGATE_KEY, false);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Darf das angemeldete Konto Funktionen und Erweiterungen dieser Website schalten? */
    public static function canManage(): bool
    {
        if (!isset(app()->auth) || !app()->auth->user()) return false;
        return self::integrator() || can('system.features');
    }

    /** Seite „Funktionen & Erweiterungen“ sichtbar (im Netzwerk: Website-Administration liest mit) */
    public static function canView(): bool
    {
        if (!isset(app()->auth) || !app()->auth->user()) return false;
        return self::canManage() || (self::networked() && can('system.manage'));
    }

    /** Ist die Funktion auf dieser Website verfügbar? (Integratoren: immer) */
    public static function on(string $key, bool $respectIntegrator = true): bool
    {
        if ($respectIntegrator && self::integrator()) return true;
        return self::all()[$key] ?? true;
    }

    /** Angemeldetes Konto steht in 'integrators' (z. B. die Agentur) */
    public static function integrator(): bool
    {
        // Netzwerk-Administration (Core\Network) hat auf jeder Website die Rechte der Agentur
        if (isset(app()->auth) && Network\Network::isNetworkUser()) return true;
        $list = array_map('strtolower', (array) app()->config->get('integrators', []));
        if (!$list || !isset(app()->auth)) return false;
        $u = app()->auth->user();
        return $u && in_array(strtolower((string) $u['email']), $list, true);
    }

    /** Darf ein Recht auf dieser Website überhaupt vergeben werden? */
    public static function allowsPermission(string $perm): bool
    {
        if (self::integrator()) return true;
        // Funktionen schalten: Einzel-Installation = Haupt-Admin; Netzwerk nur mit Freigabe der Netzwerk-Administration
        if ($perm === 'system.features') return !self::networked() || self::delegated();
        // Token-Verwaltung braucht REST-API oder MCP
        if ($perm === 'api.manage') return (self::all()['api'] ?? true) || (self::all()['mcp'] ?? true);
        foreach (self::catalog() as $key => [, $perms]) {
            if (in_array($perm, $perms, true) && !(self::all()[$key] ?? true)) return false;
        }
        return true;
    }

    /** Blocktyp für die Redaktion freigegeben? */
    public static function allowsBlock(string $type): bool
    {
        $b = (array) app()->config->get('blocks', []);
        if (!empty($b['allow']) && !in_array($type, (array) $b['allow'], true)) return false;
        if (in_array($type, (array) ($b['deny'] ?? []), true)) return false;
        // Eigene Blöcke (Block-Designer): ohne Funktion „blocks.custom“ bleiben bestehende sichtbar, neue lassen sich nicht einfügen
        if (str_starts_with($type, 'cblk_') && !self::on('blocks.custom', false)) return false;
        if (in_array($type, ['data_list', 'data_fields'], true) && !self::on('data', false)) return false;
        if ($type === 'map' && !self::on('maps', false)) return false;
        if (in_array($type, ['calendar', 'upcoming'], true) && !self::on('calendar', false)) return false;
        if ($type === 'glossary' && !self::on('glossary', false)) return false;
        if ($type === 'push_subscribe' && !self::on('push', false)) return false;
        // Formular-Block: für Inhaltstabellen (forms.data) oder Eingangs-Tabellen (requests)
        if ($type === 'data_form' && !self::on('forms.data', false) && !self::on('requests', false)) return false;
        return true;
    }

    /** Pfade, die eine Funktion voraussetzen (Website-Routen ohne Rechteprüfung) */
    public static function allowsPath(string $path): bool
    {
        if (str_starts_with($path, '/api/v1') && !in_array($path, ['/api/v1/public', '/api/v1', '/api/v1/openapi.json'], true)) return self::on('api', false);
        if ($path === '/mcp') return self::on('mcp', false);
        if (in_array($path, ['/manifest.webmanifest', '/sw.js'], true)) return self::on('pwa', false);
        if ($path === '/push-sw.js' || str_starts_with($path, '/api/push/')) return self::on('push', false);
        if (str_starts_with($path, '/proxy/ofm/')) return self::on('maps', false);
        if (str_starts_with($path, '/kalender/') && str_ends_with($path, '.ics')) return self::on('calendar', false);
        if (str_starts_with($path, '/formular/')) return self::on('forms.data', false) || self::on('requests', false);
        if (str_starts_with($path, '/anfrage/') || str_starts_with($path, '/api/form/')) return self::on('requests', false);
        return true;
    }

    /** Übersicht für die Systeminfo: [key => [label, on]] */
    public static function overview(): array
    {
        $out = [];
        foreach (self::catalog() as $k => [$label]) {
            $out[$k] = [$label, self::all()[$k] ?? true];
        }
        return $out;
    }
}
