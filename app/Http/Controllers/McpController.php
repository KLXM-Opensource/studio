<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Api\ApiError;
use Core\Api\CmsService;
use Core\Api\Tokens;
use Core\Http\Request;
use Core\Http\Response;
use Core\Pages;

/**
 * MCP-Server (Model Context Protocol) über „Streamable HTTP“ – zustandslos, JSON-Antworten.
 * Endpunkt: POST /mcp · Auth: Authorization: Bearer cms_… (gleiche Tokens wie die REST-API)
 *
 * Bietet Tools (lesen/ändern), Resources (zentrale Einstellungen, Seiten) und Prompts (häufige Aufgaben).
 */
final class McpController
{
    private const VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];
    private const SERVER = ['name' => CMS_SLUG, 'title' => CMS_NAME, 'version' => CMS_VERSION];

    private array $token;
    private CmsService $svc;

    /** Katalog für die Dokumentation (Tools, Prompts, Resources) */
    public static function catalog(): array
    {
        $c = new self();
        $c->token = ['name' => 'doc', 'scope' => 'write', 'user_id' => null];
        $c->svc = new CmsService($c->token);
        return [
            'tools' => array_values(array_map(fn($t) => [
                'name' => $t['name'], 'title' => $t['title'], 'description' => $t['description'], 'write' => $t['write'],
                'destructive' => !empty($t['annotations']['destructiveHint']),
                'params' => array_keys((array) ($t['inputSchema']['properties'] ?? [])),
                'required' => $t['inputSchema']['required'] ?? [],
            ], $c->tools())),
            'prompts' => array_values(array_map(fn($p) => ['name' => $p['name'], 'title' => $p['title'], 'description' => $p['description'],
                'arguments' => array_column($p['arguments'], 'name')], $c->prompts())),
            'resources' => $c->resources(),
            'versions' => self::VERSIONS,
        ];
    }

    public function get(Request $r): Response
    {
        // Kein Server-Sent-Events-Stream: zustandsloser Server
        return (new Response('', 405, ['Allow' => 'POST', 'Content-Type' => 'text/plain']));
    }

    public function handle(Request $r): Response
    {
        // Schutz vor DNS-Rebinding: fremde Browser-Origins ablehnen
        $origin = (string) ($r->server['HTTP_ORIGIN'] ?? '');
        if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('//' . $r->host(), PHP_URL_HOST)) {
            return self::rpcHttpError(403, -32000, 'Origin nicht erlaubt.');
        }
        try {
            $this->token = Tokens::authenticate($r);
        } catch (ApiError $e) {
            return self::rpcHttpError($e->status, -32001, $e->getMessage())
                ->header('WWW-Authenticate', 'Bearer realm="' . CMS_SLUG . '-mcp"');
        }
        // Herkunft für die Prüf-Ebene (Core\Review): MCP + zuletzt gemeldeter Client dieses Tokens (initialize → clientInfo)
        $this->svc = (new CmsService($this->token))->origin('mcp', ($this->token['mcp_client'] ?? null) ?: null);

        $raw = json_decode((string) file_get_contents('php://input'), true);
        if ($raw === null) {
            $raw = $r->post ?: null;
        }
        if (!is_array($raw)) {
            return self::json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);
        }

        $batch = array_is_list($raw);
        $out = [];
        foreach ($batch ? $raw : [$raw] as $msg) {
            $res = $this->dispatch(is_array($msg) ? $msg : []);
            if ($res !== null) {
                $out[] = $res;
            }
        }
        if (!$out) {
            return new Response('', 202, ['Content-Type' => 'application/json']);
        }
        return self::json($batch ? $out : $out[0]);
    }

    private static function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status)->header('X-Robots-Tag', 'noindex');
    }

    private static function rpcHttpError(int $status, int $code, string $msg): Response
    {
        return self::json(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => $code, 'message' => $msg]], $status);
    }

    private function dispatch(array $m): ?array
    {
        $id = $m['id'] ?? null;
        $method = (string) ($m['method'] ?? '');
        $params = is_array($m['params'] ?? null) ? $m['params'] : [];
        $isNotification = !array_key_exists('id', $m);

        if (($m['jsonrpc'] ?? '') !== '2.0' || $method === '') {
            return $isNotification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32600, 'message' => 'Invalid Request']];
        }
        if (str_starts_with($method, 'notifications/')) {
            return null;
        }
        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'ping' => new \stdClass(),
                'tools/list' => ['tools' => array_values(array_map(fn($t) => array_diff_key($t, ['run' => 1, 'write' => 1]), $this->tools()))],
                'tools/call' => $this->callTool($params),
                'resources/list' => ['resources' => $this->resources()],
                'resources/templates/list' => ['resourceTemplates' => [[
                    'uriTemplate' => 'cms://pages/{slug}', 'name' => 'page', 'title' => 'Seite', 'mimeType' => 'application/json',
                    'description' => 'Seite mit allen Blöcken (Entwurf)']]],
                'resources/read' => $this->readResource((string) ($params['uri'] ?? '')),
                'prompts/list' => ['prompts' => array_values(array_map(fn($p) => array_diff_key($p, ['text' => 1]), $this->prompts()))],
                'prompts/get' => $this->getPrompt($params),
                'logging/setLevel' => new \stdClass(),
                default => throw new \DomainException("Methode „{$method}“ nicht gefunden", -32601),
            };
            return $isNotification ? null : ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
        } catch (\DomainException $e) {
            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $e->getCode() ?: -32602, 'message' => $e->getMessage()]];
        } catch (\Throwable $e) {
            error_log('[MCP] ' . $e);
            return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32603, 'message' => 'Interner Fehler']];
        }
    }

    private function initialize(array $p): array
    {
        $req = (string) ($p['protocolVersion'] ?? '');
        // Client merken (zustandsloser Server): erscheint in der Prüf-Ebene als Herkunft, z. B. „claude-code 2.1“
        $client = trim(mb_substr(strip_tags((string) ($p['clientInfo']['name'] ?? '') . ' ' . (string) ($p['clientInfo']['version'] ?? '')), 0, 120));
        if ($client !== '' && isset($this->token['id']) && $client !== ($this->token['mcp_client'] ?? '')) {
            try {
                app()->db->update('api_tokens', ['mcp_client' => $client], 'id = :id', ['id' => (int) $this->token['id']]);
            } catch (\Throwable) {
                // Spalte fehlt noch (vor „migrate“)
            }
            $this->token['mcp_client'] = $client;
            $this->svc->origin('mcp', $client);
        }
        $review = $this->token['scope'] === 'write' && $this->svc->reviewMode();
        return [
            'protocolVersion' => in_array($req, self::VERSIONS, true) ? $req : self::VERSIONS[1],
            'capabilities' => ['tools' => ['listChanged' => false], 'resources' => ['listChanged' => false, 'subscribe' => false], 'prompts' => ['listChanged' => false], 'logging' => new \stdClass()],
            'serverInfo' => self::SERVER + ['websiteUrl' => absolute_url('/')],
            'instructions' => "Du verwaltest die Website „" . site_name() . "“ (" . absolute_url('/') . ").\n"
                . "- Starte mit site_overview. " . app()->theme->settingsTitle() . " (zentrale Angaben wie Kontaktdaten) werden an einer Stelle gepflegt und erscheinen automatisch überall.\n"
                . "- Seiteninhalte bestehen aus Blöcken (list_block_types). Änderungen landen als ENTWURF – erst publish_page (oder publish: true) stellt sie online. Frage vor dem Veröffentlichen nach, wenn unklar.\n"
                . "- Erfinde keine Fakten; Platzhalter in [eckigen Klammern] nur mit echten Angaben ersetzen.\n"
                . "- Online-Anfragen sind verschlüsselt; über MCP sind nur Metadaten sichtbar.\n"
                . (\Core\Lang::multi() ? "- Mehrsprachig (" . implode(', ', array_keys(\Core\Lang::all())) . ", Standard " . \Core\Lang::default() . "): Parameter lang wählt die Sprache. Neue Sprachfassungen mit translate_page/translate_entry anlegen, dann die Texte übersetzen. Einstellungen mit lang ändern nur übersetzbare Felder (siehe get_settings_schema → translatable), Medien-Alt-Texte über update_media → i18n.\n" : '')
                . "- Kartenstandorte (Feldtyp geo) mit geocode_address ermitteln – nie Koordinaten schätzen.\n"
                . (project('mcp.instructions') ? '- ' . project('mcp.instructions') . "\n" : '')
                . ($review ? "- Dieses Token reicht Änderungen ZUR FREIGABE ein: Schreib-Tools liefern status \"pending_review\" mit id – nichts ist ausgeführt, bis ein Mensch in der Verwaltung zustimmt. Sage das dem Menschen so; Stand mit get_change_status abfragen. Folgeänderungen bauen nicht auf eingereichten auf.\n" : '')
                . 'Berechtigung dieses Tokens: ' . ($this->token['scope'] === 'write' ? 'lesen und ändern' . ($review ? ' (zur Freigabe)' : '') : 'nur lesen') . '.',
        ];
    }

    // ------------------------------------------------------------------ Tools

    private function tools(): array
    {
        $s = $this->svc;
        $str = fn(string $d, array $x = []) => ['type' => 'string', 'description' => $d] + $x;
        $bool = fn(string $d) => ['type' => 'boolean', 'description' => $d];
        $int = fn(string $d) => ['type' => 'integer', 'description' => $d];
        $obj = fn(string $d) => ['type' => 'object', 'description' => $d, 'additionalProperties' => true];
        $page = $str('Seiten-ID, Pfad (z. B. „leistungen/beratung“ oder „impressum“) oder „home“ für die Startseite');
        $publish = $bool('Nach dem Speichern sofort veröffentlichen (Standard: false = Entwurf)');
        // Hintergründe kommen aus dem aktiven Theme (theme.php → backgrounds)
        $section = $obj('Abschnitts-Optionen: background (' . implode('|', array_keys(app()->theme->backgrounds())) . '), anchor, visible, showInNav, navLabel, spaceTop/spaceBottom (normal|small|none), divider, height (auto|screen = Vollbild 100svh), bgImage (Medien-ID Hintergrundbild), overlay (none|light|dark), align (top|center|bottom, bei height=screen)');
        $schema = fn(array $props, array $req = []) => ['type' => 'object', 'properties' => $props ?: new \stdClass()] + ($req ? ['required' => $req] : []);
        $ro = ['readOnlyHint' => true, 'openWorldHint' => false];
        $rw = ['readOnlyHint' => false, 'destructiveHint' => false, 'openWorldHint' => false];
        $del = ['readOnlyHint' => false, 'destructiveHint' => true, 'openWorldHint' => false];

        $t = [];
        // Mehrsprachig: jedes Tool bekommt den optionalen Parameter lang (Sprache der Seiten, Einträge, Einstellungen, Medientexte)
        $langProp = \Core\Lang::multi() ? ['lang' => $str('Sprache für diese Aktion: ' . implode(', ', array_keys(\Core\Lang::all())) . ' (Standard: ' . \Core\Lang::default() . ')',
            ['enum' => array_keys(\Core\Lang::all())])] : [];
        $add = function (string $name, string $title, string $desc, array $input, array $ann, bool $write, callable $run) use (&$t, $langProp) {
            if ($langProp && !in_array($name, ['site_overview', 'geocode_address'], true)) {
                $input['properties'] = array_merge(is_array($input['properties']) ? $input['properties'] : [], $langProp);
            }
            $t[$name] = ['name' => $name, 'title' => $title, 'description' => $desc, 'inputSchema' => $input,
                'annotations' => $ann + ['title' => $title], 'write' => $write, 'run' => $run];
        };

        // Lesen
        $add('site_overview', 'Website-Überblick', 'Überblick: Name, URL, Seiten, Einstellungsgruppen, Blocktypen, Formulare, Token-Berechtigung. Guter Einstieg.',
            $schema([]), $ro, false, fn() => $s->siteInfo());
        $st = app()->theme->settingsTitle();
        $groups = implode(', ', array_column(app()->theme->settingsGroups(), 'id'));
        $add('get_settings', $st . ' lesen', 'Zentrale Einstellungen der Website („' . $st . '“) lesen. Optional nur eine Gruppe.',
            $schema(['group' => $str('Gruppen-ID' . ($groups !== '' ? ': ' . $groups : ''))]), $ro, false,
            fn($a) => $s->settingsGet($a['group'] ?? null));
        $add('get_settings_schema', 'Schema: ' . $st, 'Feld-Schema der zentralen Einstellungen: Namen, Typen, Pflichtfelder, erlaubte Werte. Vor update_settings nutzen.',
            $schema([]), $ro, false, fn() => ['groups' => $s->settingsSchema()]);
        $design = \Core\Features::on('design', false) && \Core\Design::enabled();
        if ($design) {
            $add('get_design', 'Design lesen', 'Design-Werte des Kits (Style-Editor): Gruppen und Tokens (Farben, Formen, Schriften) mit Typ, Standard, erlaubten Werten, aktuelle Werte, Vorlagen (presets) und Kontrastprüfung (WCAG). Vor set_design nutzen.',
                $schema([]), $ro, false, fn() => $s->designGet());
        }
        if (\Core\Landings::enabled()) {
            $add('list_landings', 'Landingpages lesen', 'Landingpages mit eigenen Domains: Domains, zugeordnete Seite (mit Unterseiten?), Canonical-Modus own (Landing-Domain maßgeblich) oder mirror (Hauptdomain maßgeblich), Weiterleitungen, Suche, Marke und die Seiten mit ihren Adressen. Nur lesen – Inhalte der Landingpages sind normale Seiten (get_page/update_page).',
                $schema([]), $ro, false, fn() => $s->landingsGet());
        }
        $hl = (string) project('hours.label', 'Öffnungszeiten');
        if (CmsService::hasHours()) {
            $add('get_opening_hours', $hl . ' lesen', 'Aktuelle ' . $hl . ' je Wochentag inkl. formatierter Anzeige.', $schema([]), $ro, false, fn() => ['hours' => $s->hoursGet()]);
        }
        $add('list_pages', 'Seiten auflisten', 'Alle Seiten mit Status, URL und ob unveröffentlichte Änderungen vorliegen.', $schema([]), $ro, false, fn() => ['pages' => $s->pagesList()]);
        $add('get_page', 'Seite lesen', 'Eine Seite mit allen Blöcken (IDs, Typ, Daten, Abschnitts-Optionen). Standard: Entwurf.',
            $schema(['page' => $page, 'version' => $str('draft (Standard) oder published', ['enum' => ['draft', 'published']])], ['page']), $ro, false,
            fn($a) => $s->pageGet($a['page'], ($a['version'] ?? '') === 'published'));
        $add('list_block_types', 'Blocktypen', 'Verfügbare Blocktypen mit Feld-Schema und Varianten. Optional einen Typ filtern.',
            $schema(['type' => $str('Nur diesen Blocktyp zeigen')]), $ro, false, function ($a) use ($s) {
                $all = $s->blockTypes();
                if (!empty($a['type'])) {
                    $all['types'] = array_intersect_key($all['types'], [$a['type'] => 1]) ?: throw new ApiError(404, 'Blocktyp unbekannt.');
                }
                return $all;
            });
        $add('list_revisions', 'Versionen einer Seite', 'Letzte gespeicherte Versionen einer Seite.', $schema(['page' => $page], ['page']), $ro, false, fn($a) => ['revisions' => $s->revisions($a['page'])]);
        $add('list_media', 'Mediathek', 'Bilder und Dateien der Mediathek (ID für Bild-/Datei-Felder in Blöcken) plus Sammlungen, Tags und Bildformate für Zuschnitte. Filter kombinierbar.',
            $schema(['kind' => $str('Dateiart', ['enum' => ['image', 'pdf', 'video', 'audio', 'all']]), 'q' => $str('Suchbegriff (Name, Titel, Alt-Text, Tag)'),
                'tag' => $str('nur mit diesem Tag'), 'collection' => $int('nur aus dieser Sammlung (ID)'), 'noalt' => ['type' => 'boolean', 'description' => 'nur Bilder ohne Alt-Text'],
                'missing_lang' => $str('nur Bilder ohne Alt-Text in dieser weiteren Sprache (Kürzel, z. B. en)'), 'notitle' => ['type' => 'boolean', 'description' => 'nur Dateien ohne Titel'],
                'nocaptions' => ['type' => 'boolean', 'description' => 'nur Videos ohne veröffentlichte Untertitel'], 'notranscript' => ['type' => 'boolean', 'description' => 'nur Audio ohne veröffentlichtes Transkript']]),
            $ro, false, fn($a) => ['media' => $s->mediaList($a)] + $s->mediaMeta());
        $add('list_data_tables', 'Datentabellen', 'Eigene Inhaltstypen (z. B. Aktuelles, Team, Produkte) mit Feldern, Typen und Auswahlwerten. Einträge erscheinen über den Block „data_list“ und auf Detailseiten. Tabellen mit kind = inbox sind Eingänge für verschlüsselte Anfragen: nur Metadaten (list_requests), keine Inhalte, kein Anlegen/Ändern. search = Such-Einstellungen der Tabelle (enabled, fields: Gewichtung je Feld high|normal|low|off, facets, title/summary/image/date, label, future, exclude). icon = Symbolname (Phosphor duotone, z. B. calendar-dots).',
            $schema([]), $ro, false, fn() => ['tables' => $s->dataTables()]);
        $add('list_entries', 'Einträge auflisten', 'Einträge einer Datentabelle, filterbar und sortierbar.',
            $schema(['table' => $str('Kurzname der Tabelle, z. B. aktuelles'), 'q' => $str('Suchbegriff'),
                'status' => $str('published, draft oder all (Standard)', ['enum' => ['published', 'draft', 'all']]),
                'filter' => $obj('Feld = Wert, z. B. {"kategorie": "neuigkeiten"}'), 'sort' => $str('Feldname'), 'dir' => $str('asc|desc', ['enum' => ['asc', 'desc']]),
                'limit' => $int('max. 200, Standard 50'), 'offset' => $int('Versatz'),
                'source' => $str('Nur geteilte Tabellen (list_data_tables → shared): site (Standard, wie auf der Website), own, owner, members, own_owner, all, featured', ['enum' => \Core\Data\Shared::SOURCES])], ['table']), $ro, false,
            fn($a) => $s->dataEntries((string) $a['table'], $a));
        $add('get_entry', 'Eintrag lesen', 'Einen Eintrag per ID oder Slug lesen.',
            $schema(['table' => $str('Kurzname der Tabelle'), 'entry' => $str('ID oder Slug')], ['table', 'entry']), $ro, false,
            fn($a) => $s->dataEntry((string) $a['table'], (string) $a['entry']));
        if (\Core\Features::on('calendar', false)) {
            $add('list_occurrences', 'Termine im Zeitraum', 'Termine einer Kalender-Tabelle (list_data_tables → calendar) im Zeitraum – Wiederholungen einzeln, Ausnahmen berücksichtigt, sortiert nach Beginn.',
                $schema(['table' => $str('Kurzname der Kalender-Tabelle'), 'from' => $str('Beginn JJJJ-MM-TT (Standard: heute)'), 'to' => $str('Ende JJJJ-MM-TT einschließlich (Standard: +1 Monat, max. 366 Tage)'),
                    'status' => $str('published (Standard), draft oder all', ['enum' => ['published', 'draft', 'all']]), 'filter' => $obj('Feld = Wert, z. B. {"kategorie": "workshop"}'),
                    'limit' => $int('max. 1000, Standard 500'),
                    'source' => $str('Nur geteilte Tabellen: site (Standard), own, owner, members, own_owner, all, featured', ['enum' => \Core\Data\Shared::SOURCES])], ['table']), $ro, false,
                fn($a) => $s->dataOccurrences((string) $a['table'], $a));
        }
        $add('save_entry', 'Eintrag anlegen/ändern', 'Eintrag anlegen (ohne id) oder ändern (mit id; nur übergebene Felder). Feldnamen siehe list_data_tables. Bild-/Dateifelder erwarten Medien-IDs, Datum JJJJ-MM-TT, Datum & Uhrzeit „JJJJ-MM-TT HH:MM“ (Ortszeit), Wiederholung als RRULE „FREQ=WEEKLY;BYDAY=MO;UNTIL=20261231“ (Ausnahmen: zweite Zeile „EXDATE:2026-10-12,…“), Rich-Text einfaches HTML, IBAN ohne oder mit Leerzeichen (wird geprüft), wiederholbare Gruppe (Typ group) als Array von Objekten [{"unterfeld": "wert"}, …] (Unterfelder, min/max unter group in list_data_tables). Bedingungen der Felder (visible_if/required_if/compare aus list_data_tables) werden geprüft; ausgeblendete Felder werden geleert. status: published oder draft.',
            $schema(['table' => $str('Kurzname der Tabelle'), 'id' => $int('Eintrags-ID zum Ändern'), 'fields' => $obj('Feldwerte, z. B. {"titel": "…", "datum": "2026-10-01"}'),
                'slug' => $str('Adresse (optional)'), 'status' => $str('published|draft', ['enum' => ['published', 'draft']])], ['table', 'fields']), $rw, true,
            fn($a) => $s->dataSave((string) $a['table'], isset($a['id']) ? (int) $a['id'] : null, (array) $a['fields'] + array_intersect_key($a, ['slug' => 1, 'status' => 1])));
        // Geteilte Tabellen (mehrere Websites): Vorschläge der übrigen Websites und Auswahl dieser Website
        if (array_filter(\Core\Data\Tables::all(), [\Core\Data\Tables::class, 'isShared'])) {
            $add('list_suggestions', 'Vorschläge (geteilte Tabelle)', 'Nur für die Website, der eine geteilte Tabelle gehört: Einträge, die andere Websites vorschlagen. state: pending (offen, Standard), visible (übernommen), featured, rejected, all.',
                $schema(['table' => $str('Kurzname der geteilten Tabelle'), 'state' => $str('pending|visible|featured|rejected|all', ['enum' => ['pending', 'visible', 'featured', 'rejected', 'all']])], ['table']), $ro, false,
                fn($a) => $s->dataSuggestions((string) $a['table'], (string) ($a['state'] ?? 'pending')));
            $add('set_pick', 'Auswahl setzen (geteilte Tabelle)', 'Entscheidet, wie Einträge einer geteilten Tabelle auf DIESER Website erscheinen: visible = übernehmen, rejected = Vorschlag ablehnen (beides nur die Eigentümer-Website, für Einträge anderer Websites), featured = hervorheben, hidden = ausblenden (nur fremde Einträge), reset = zurücksetzen. Einträge anderer Websites sind sonst nur lesbar.',
                $schema(['table' => $str('Kurzname der geteilten Tabelle'), 'ids' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Eintrags-IDs'],
                    'state' => $str('visible|featured|hidden|rejected|reset', ['enum' => ['visible', 'featured', 'hidden', 'rejected', 'reset']])], ['table', 'ids', 'state']), $rw + ['idempotentHint' => true], true,
                fn($a) => $s->dataPick((string) $a['table'], (array) $a['ids'], (string) $a['state']));
        }
        $add('delete_entry', 'Eintrag löschen', 'Einen Eintrag endgültig löschen.',
            $schema(['table' => $str('Kurzname der Tabelle'), 'id' => $int('Eintrags-ID')], ['table', 'id']), $del, true,
            fn($a) => $s->dataDelete((string) $a['table'], (int) $a['id']));
        if (\Core\Search\Search::enabled()) {
            $add('search_site', 'Website durchsuchen', 'Sucht wie Besucher auf der Website (tippfehlertolerant, bei eingeschalteter KI auch semantisch): veröffentlichte Seiten, Einträge mit Detailseite (inkl. geteilter), ggf. PDF-Dokumente. Liefert Titel, Art, URL, Datum und Auszug – gut, um vor dem Schreiben vorhandene Inhalte zu finden.',
                $schema(['q' => $str('Suchbegriff(e), z. B. „Öffnungszeiten“'), 'type' => $str('Nur diese Art: page, file oder Kurzname einer Datentabelle'),
                    'limit' => $int('max. 50, Standard 10'), 'page' => $int('Seite der Ergebnisse (Standard 1)')], ['q']), $ro, false,
                fn($a) => $s->search((string) $a['q'], $a));
        }
        $add('geocode_address', 'Adresse → Koordinaten', 'Findet Koordinaten zu einer Adresse (OpenStreetMap). Ergebnis „value“ direkt für Felder vom Typ geo verwenden (z. B. Standort der Karte in update_settings oder Block „map“ → point).',
            $schema(['address' => $str('Adresse, z. B. „Hauptstraße 1, 12345 Musterstadt“')], ['address']), $ro + ['openWorldHint' => true], false,
            fn($a) => ['results' => $s->geocode((string) $a['address'])]);
        $add('list_requests', 'Anfragen (Eingang)', 'Eingegangene Anfragen aller Eingangs-Tabellen (z. B. ' . term('requests') . ') – nur Metadaten: Tabelle, Vorgangsnummer, Status, Zeitpunkt. Inhalte bleiben Ende-zu-Ende verschlüsselt und sind nur in der Verwaltung mit dem Schlüssel lesbar.',
            $schema(['status' => $str('neu (Standard), in_bearbeitung, erledigt oder alle', ['enum' => ['neu', 'in_bearbeitung', 'erledigt', 'alle']]),
                'table' => $str('Nur diese Eingangs-Tabelle (Kurzname, siehe list_data_tables mit kind = inbox)')]), $ro, false,
            fn($a) => ['requests' => $s->requestsList($a['status'] ?? 'neu', false, isset($a['table']) ? (string) $a['table'] : null)]);
        // Prüf-Ebene (Core\Review): Stand eingereichter Änderungen dieses Tokens
        if (\Core\Review\Queue::enabled()) {
            $add('get_change_status', 'Stand eingereichter Änderungen', 'Stand einer zur Freigabe eingereichten Änderung (id aus dem Ergebnis „pending_review“): pending_review (wartet), applied (übernommen, ggf. bearbeitet) oder rejected (abgelehnt, mit reason). Ohne id: die letzten Einreichungen und Änderungen dieses Tokens.',
                $schema(['id' => $int('ID der Einreichung'), 'status' => $str('Nur ohne id: pending_review|applied|rejected', ['enum' => ['pending_review', 'applied', 'rejected']])]), $ro, false,
                fn($a) => isset($a['id']) ? $s->changeStatus((int) $a['id']) : $s->changesList(isset($a['status']) ? (string) $a['status'] : null));
        }

        // Ändern
        $add('update_settings', $st . ' ändern', 'Zentrale Einstellungen teilweise ändern – nur übergebene Felder. Wird sofort auf der Website wirksam. Schema: get_settings_schema.',
            $schema(['values' => $obj('Feldname → Wert, z. B. {"telefon": "01234 123456", "doctolib_url": "https://…"}')], ['values']), $rw, true,
            fn($a) => ['saved' => $s->settingsUpdate((array) $a['values'])]);
        if ($design) $add('set_design', 'Design ändern', 'Design-Werte ändern und sofort veröffentlichen (mit Verlauf, in der Verwaltung wiederherstellbar). values = nur zu ändernde Werte (Farben #RRGGBB, dunkle Werte „name@dark“), preset = Vorlage als Grundlage, reset = Kit-Standard als Grundlage. Kontrastwerte im Ergebnis prüfen. Braucht einen Token-Benutzer mit Recht „Design“.',
            $schema(['values' => $obj('Token-Name → Wert, z. B. {"accent": "#0F5E63", "buttons": "soft"}'), 'preset' => $str('Schlüssel einer Vorlage aus get_design → presets'),
                'reset' => $bool('Vorher auf den Kit-Standard zurücksetzen')]), $rw + ['idempotentHint' => true], true,
            fn($a) => $s->designUpdate(array_intersect_key($a, ['values' => 1, 'preset' => 1, 'reset' => 1])));
        if (CmsService::hasHours()) $add('set_opening_hours', $hl . ' setzen', 'Ersetzt ALLE ' . $hl . '. Pro Wochentag ein Eintrag; Mittagspause über pause_von/pause_bis. Wirkt sofort.',
            $schema(['hours' => ['type' => 'array', 'description' => 'Einträge', 'items' => ['type' => 'object', 'required' => ['tag'], 'properties' => [
                'tag' => $str('Wochentag: Mo, Di, Mi, Do, Fr, Sa, So (oder 1–6, 0 = Sonntag)'),
                'von' => $str('HH:MM'), 'bis' => $str('HH:MM'), 'pause_von' => $str('HH:MM'), 'pause_bis' => $str('HH:MM'),
                'notiz' => $str('z. B. „nachmittags geschlossen“')]]]], ['hours']), $rw + ['idempotentHint' => true], true,
            fn($a) => ['hours' => $s->hoursSet((array) $a['hours'])]);
        if (CmsService::hasNotice()) $add('set_notice', 'Aktuellen Hinweis setzen', 'Hinweisbalken oben auf der Website (' . project('notice.example', 'z. B. Betriebsferien') . '). active=false blendet ihn aus. Wirkt sofort.',
            $schema(['text' => $str('Text; erlaubt: <b>, <i>, <a href>'), 'active' => $bool('Anzeigen (Standard: true)')], ['text']), $rw + ['idempotentHint' => true], true,
            fn($a) => ['saved' => $s->noticeSet((string) $a['text'], (bool) ($a['active'] ?? true))]);
        $add('create_page', 'Seite anlegen', 'Neue Seite anlegen (Standard: Entwurf). Optional direkt mit Blöcken [{type, data, section}].',
            $schema(['title' => $str('Titel'), 'slug' => $str('URL-Pfad, z. B. „leistungen“'), 'status' => $str('draft|published', ['enum' => ['draft', 'published']]),
                'meta_description' => $str('Beschreibung für Suchmaschinen (≤160 Zeichen)'), 'noindex' => $bool('Nicht indexieren'),
                'parent' => $str('Übergeordnete Seite (ID oder Pfad) – für Unterseiten'), 'menu' => $bool('Im Hauptmenü zeigen'), 'nav_title' => $str('Beschriftung im Menü'),
                'blocks' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => 'Blöcke [{type, data, section}]']], ['title']), $rw, true,
            fn($a) => $s->pageCreate($a));
        $add('update_page', 'Seiteneinstellungen ändern', 'Titel, Slug, Status, SEO-Beschreibung, noindex, Menü oder Position im Seitenbaum ändern.',
            $schema(['page' => $page, 'title' => $str('Titel'), 'slug' => $str('Slug'), 'status' => $str('draft|published', ['enum' => ['draft', 'published']]),
                'meta_description' => $str('SEO-Beschreibung'), 'noindex' => $bool('noindex'), 'menu' => $bool('Im Hauptmenü zeigen'), 'nav_title' => $str('Beschriftung im Menü'),
                'parent' => $str('Neue übergeordnete Seite (ID/Pfad, leer = oberste Ebene)'), 'position' => $int('Position unter den Geschwistern (0 = erste)')], ['page']), $rw, true,
            fn($a) => $s->pageUpdate($a['page'], array_diff_key($a, ['page' => 1])));
        if (\Core\Lang::multi()) {
            $add('translate_page', 'Seite übersetzen', 'Legt die Übersetzung einer Seite in einer weiteren Sprache an (Kopie als Entwurf, verknüpft). Danach Texte mit update_block übersetzen (mit lang) und publish_page. Gibt es sie schon, wird sie zurückgegeben.',
                $schema(['page' => $page, 'to' => $str('Zielsprache', ['enum' => array_keys(\Core\Lang::all())])], ['page', 'to']), $rw, true,
                fn($a) => $s->pageTranslate($a['page'], (string) $a['to']));
            $add('translate_entry', 'Eintrag übersetzen', 'Legt die Übersetzung eines Eintrags an (Kopie als Entwurf in der Zielsprache, verknüpft). Danach mit save_entry (id der Übersetzung) die Texte übersetzen.',
                $schema(['table' => $str('Kurzname der Tabelle'), 'id' => $int('Eintrags-ID (Original)'), 'to' => $str('Zielsprache', ['enum' => array_keys(\Core\Lang::all())])], ['table', 'id', 'to']), $rw, true,
                fn($a) => $s->dataTranslate((string) $a['table'], (int) $a['id'], (string) $a['to']));
        }
        $add('delete_page', 'Seite löschen', 'Seite endgültig löschen (nicht die Startseite). Nur nach ausdrücklicher Bestätigung durch den Menschen verwenden.',
            $schema(['page' => $page, 'confirm' => $bool('Muss true sein')], ['page', 'confirm']), $del, true, function ($a) use ($s) {
                if (($a['confirm'] ?? false) !== true) {
                    throw new ApiError(422, 'confirm muss true sein.');
                }
                return $s->pageDelete($a['page']);
            });
        $add('add_block', 'Block einfügen', 'Neuen Block in eine Seite einfügen (Entwurf). Felder laut list_block_types. Position 0-basiert oder after = Block-ID.',
            $schema(['page' => $page, 'type' => $str('Blocktyp'), 'data' => $obj('Blockfelder'), 'section' => $section,
                'position' => $int('Position (0 = oben); ohne Angabe ans Ende'), 'after' => $str('Nach dieser Block-ID einfügen'), 'publish' => $publish], ['page', 'type']), $rw, true,
            fn($a) => $s->blockAdd($a['page'], $a['type'], (array) ($a['data'] ?? []), (array) ($a['section'] ?? []),
                isset($a['position']) ? (int) $a['position'] : null, $a['after'] ?? null, (bool) ($a['publish'] ?? false)));
        $add('update_block', 'Block ändern', 'Felder eines Blocks ändern. data wird zusammengeführt (nur übergebene Felder ändern sich), replace=true ersetzt data komplett.',
            $schema(['page' => $page, 'block_id' => $str('Block-ID aus get_page'), 'data' => $obj('Zu ändernde Felder'), 'section' => $section,
                'replace' => $bool('data vollständig ersetzen'), 'publish' => $publish], ['page', 'block_id']), $rw, true,
            fn($a) => $s->blockUpdate($a['page'], $a['block_id'], isset($a['data']) ? (array) $a['data'] : null,
                isset($a['section']) ? (array) $a['section'] : null, (bool) ($a['replace'] ?? false), (bool) ($a['publish'] ?? false)));
        $add('move_block', 'Block verschieben', 'Block an eine neue Position verschieben (0 = oben).',
            $schema(['page' => $page, 'block_id' => $str('Block-ID'), 'position' => $int('Zielposition'), 'publish' => $publish], ['page', 'block_id', 'position']),
            $rw, true, fn($a) => $s->blockMove($a['page'], $a['block_id'], (int) $a['position'], (bool) ($a['publish'] ?? false)));
        $add('remove_block', 'Block entfernen', 'Block aus einer Seite entfernen (Entwurf; über Versionen wiederherstellbar).',
            $schema(['page' => $page, 'block_id' => $str('Block-ID'), 'publish' => $publish], ['page', 'block_id']), $del, true,
            fn($a) => $s->blockRemove($a['page'], $a['block_id'], (bool) ($a['publish'] ?? false)));
        $add('replace_blocks', 'Alle Blöcke ersetzen', 'Ersetzt alle Blöcke einer Seite (Reihenfolge = Array). Vorsicht bei der Startseite.',
            $schema(['page' => $page, 'blocks' => ['type' => 'array', 'items' => ['type' => 'object'], 'description' => '[{id?, type, data, section}]'], 'publish' => $publish], ['page', 'blocks']),
            $del, true, fn($a) => $s->blocksReplace($a['page'], (array) $a['blocks'], (bool) ($a['publish'] ?? false)));
        $add('publish_page', 'Seite veröffentlichen', 'Den Entwurf einer Seite veröffentlichen (online stellen).', $schema(['page' => $page], ['page']),
            $rw + ['idempotentHint' => true], true, fn($a) => $s->publish($a['page']));
        $add('discard_draft', 'Entwurf verwerfen', 'Unveröffentlichte Änderungen einer Seite verwerfen – zurück zur veröffentlichten Fassung. Der Entwurf bleibt als Version gesichert.',
            $schema(['page' => $page], ['page']), $del, true, fn($a) => $s->discard($a['page']));
        $add('restore_revision', 'Version wiederherstellen', 'Eine frühere Version als Entwurf wiederherstellen.',
            $schema(['page' => $page, 'revision_id' => $int('Versions-ID aus list_revisions')], ['page', 'revision_id']), $rw, true,
            fn($a) => $s->restore($a['page'], (int) $a['revision_id']));
        $add('upload_media', 'Datei hochladen', 'Bild (JPG/PNG/WebP/GIF), PDF, Video (MP4) oder Audio (MP3/M4A) per Base64 hochladen. Bilder brauchen einen aussagekräftigen Alt-Text (Pflicht, mind. 3 Zeichen) – nur rein schmückende Bilder mit decorative = true.',
            $schema(['filename' => $str('Dateiname mit Endung'), 'base64' => $str('Dateiinhalt Base64'), 'alt' => $str('Alt-Text (Bildbeschreibung) – Pflicht bei Bildern'),
                'decorative' => ['type' => 'boolean', 'description' => 'Bild ist rein dekorativ (dann ohne Alt-Text)'], 'title' => $str('Anzeigename, z. B. für Downloads'),
                'tags' => $str('Tags, mit Komma getrennt'), 'collection' => $int('in diese Sammlung legen (ID)')], ['filename', 'base64']),
            $rw, true, fn($a) => $s->mediaUploadBase64($a['filename'], $a['base64'], (string) ($a['alt'] ?? ''), $a));
        $add('update_media', 'Medien-Infos ändern', 'Alt-Text, Titel, Fotonachweis, Tags, Sammlungen, Fokuspunkt oder Bildanpassung einer Datei ändern.',
            $schema(['id' => $int('Medien-ID'), 'alt' => $str('Alt-Text'), 'decorative' => ['type' => 'boolean'], 'title' => $str('Anzeigename'), 'credit' => $str('Fotonachweis'),
                'tags' => $str('Tags, mit Komma getrennt (ersetzt alle)'), 'collections' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Sammlungs-IDs (ersetzt alle)'],
                'focus' => ['type' => 'object', 'properties' => ['x' => ['type' => 'integer'], 'y' => ['type' => 'integer']], 'description' => 'Fokuspunkt in Prozent (0–100), z. B. Gesicht'],
                'i18n' => $obj('Übersetzungen von Alt-Text/Titel je Sprache, z. B. {"en": {"alt": "…", "title": "…"}} – leer = Standardsprache'),
                'adjust' => $str('Bild anpassen, zerstörungsfrei (überall, wo das Bild erscheint): Effekt gray|sepia|warm|cool|muted|vivid|contrast und s/b/c in Prozent (Sättigung 0–200, Helligkeit/Kontrast 50–150, 10er-Schritte), z. B. "sepia s120 c110"; leer = Original')], ['id']),
            $rw + ['idempotentHint' => true], true,
            fn($a) => $s->mediaUpdate((int) $a['id'], array_intersect_key($a, ['alt' => 1, 'decorative' => 1, 'title' => 1, 'credit' => 1, 'tags' => 1, 'collections' => 1, 'focus' => 1, 'i18n' => 1, 'adjust' => 1])));
        $add('crop_media', 'Bild zuschneiden', 'Eigenen Bildausschnitt für ein Format festlegen (Formate: siehe list_media → ratios). rect in Anteilen des Originals (0–1), das Seitenverhältnis wird erzwungen. Ohne rect wird der Zuschnitt entfernt (dann gilt der Fokuspunkt).',
            $schema(['id' => $int('Medien-ID'), 'ratio' => $str('z. B. 16:9'), 'rect' => ['type' => 'object', 'properties' => ['x' => ['type' => 'number'], 'y' => ['type' => 'number'], 'w' => ['type' => 'number'], 'h' => ['type' => 'number']]]], ['id', 'ratio']),
            $rw + ['idempotentHint' => true], true, fn($a) => $s->mediaCrop((int) $a['id'], (string) $a['ratio'], is_array($a['rect'] ?? null) ? $a['rect'] : null));
        $add('set_request_status', 'Anfrage-Status setzen', 'Anfrage als „neu“, „in_bearbeitung“ oder „erledigt“ markieren (wird protokolliert). table angeben, wenn die ID in mehreren Eingangs-Tabellen vorkommt.',
            $schema(['id' => $int('Anfrage-ID'), 'status' => $str('neu|in_bearbeitung|erledigt', ['enum' => ['neu', 'in_bearbeitung', 'erledigt']]),
                'table' => $str('Kurzname der Eingangs-Tabelle (aus list_requests)')], ['id', 'status']), $rw + ['idempotentHint' => true], true,
            fn($a) => $s->requestStatus((int) $a['id'], (string) $a['status'], isset($a['table']) ? (string) $a['table'] : null));

        // Support & Wissensdatenbank (Core\Support\Api)
        if (\Core\Features::on('support', false)) {
            $add('search_knowledge', 'Wissensdatenbank durchsuchen', 'Anleitungen und gesammelte Lösungen des Supports (für diese Website sichtbare, veröffentlichte Artikel). Ohne q: neueste Artikel. Vor report_issue nutzen – vielleicht gibt es die Lösung schon.',
                $schema(['q' => $str('Suchbegriffe, z. B. „Alt-Text“'), 'limit' => $int('Anzahl (1–50, Standard 10)')]), $ro, false,
                fn($a) => \Core\Support\Api::knowledge((string) ($a['q'] ?? ''), (int) ($a['limit'] ?? 10)));
            $token = $this->token;
            $add('report_issue', 'Problem an den Support melden', 'Meldung an das Support-Team im Namen des Token-Benutzers (Frage, Fehler oder Wunsch). Antworten erscheinen in der Verwaltung unter Support.',
                $schema(['title' => $str('Kurzer Titel'), 'description' => $str('Was ist passiert, was wurde erwartet, auf welcher Seite? Markdown-lite erlaubt.'),
                    'category' => $str('frage|fehler|wunsch', ['enum' => ['frage', 'fehler', 'wunsch']]), 'priority' => $str('normal|dringend', ['enum' => ['normal', 'dringend']])], ['title', 'description']),
                $rw, true, fn($a) => \Core\Support\Api::report($token, $a));
        }

        // Nur-Lese-Tokens sehen keine Schreib-Tools
        if ($this->token['scope'] !== 'write') {
            $t = array_filter($t, fn($x) => !$x['write']);
        }
        return $t;
    }

    private function callTool(array $p): array
    {
        $name = (string) ($p['name'] ?? '');
        $tools = $this->tools();
        if (!isset($tools[$name])) {
            throw new \DomainException("Unbekanntes Tool „{$name}“" . ($this->token['scope'] !== 'write' ? ' (Token darf nur lesen)' : ''), -32602);
        }
        $args = is_array($p['arguments'] ?? null) ? $p['arguments'] : [];
        foreach ($tools[$name]['inputSchema']['required'] ?? [] as $req) {
            if (!array_key_exists($req, $args)) {
                return self::toolError("Parameter „{$req}“ fehlt.");
            }
        }
        try {
            if (isset($args['lang']) && $name !== 'translate_page' && $name !== 'translate_entry') {
                $this->svc->useLang((string) $args['lang']);
            }
            $data = ($tools[$name]['run'])($args);
        } catch (\Core\Review\Pending $e) {
            // Token im Modus „zur Freigabe“: nichts ausgeführt – Einreichung mit id und Link (Core\Review\Queue)
            return [
                'content' => [['type' => 'text', 'text' => json_encode($e->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
                'structuredContent' => $e->data,
                'isError' => false,
            ];
        } catch (ApiError $e) {
            return self::toolError($e->getMessage() . ($e->details ? "\n" . json_encode($e->details, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : ''));
        }
        $structured = is_array($data) && !array_is_list($data) ? $data : ['items' => $data];
        return [
            'content' => [['type' => 'text', 'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]],
            'structuredContent' => $structured ?: new \stdClass(),
            'isError' => false,
        ];
    }

    private static function toolError(string $msg): array
    {
        return ['content' => [['type' => 'text', 'text' => 'Fehler: ' . $msg]], 'isError' => true];
    }

    // ------------------------------------------------------------------ Resources

    private function resources(): array
    {
        $r = [
            ['uri' => 'cms://site', 'name' => 'site', 'title' => 'Website-Überblick', 'mimeType' => 'application/json'],
            ['uri' => 'cms://settings', 'name' => 'settings', 'title' => app()->theme->settingsTitle(), 'mimeType' => 'application/json'],
            ['uri' => 'cms://block-types', 'name' => 'block-types', 'title' => 'Blocktypen', 'mimeType' => 'application/json'],
        ];
        if (CmsService::hasHours()) {
            $r[] = ['uri' => 'cms://hours', 'name' => 'hours', 'title' => (string) project('hours.label', 'Öffnungszeiten'), 'mimeType' => 'application/json'];
        }
        foreach (Pages::all() as $p) {
            $r[] = ['uri' => 'cms://pages/' . $p['slug'], 'name' => 'page-' . $p['slug'], 'title' => 'Seite: ' . $p['title'], 'mimeType' => 'application/json'];
        }
        return $r;
    }

    private function readResource(string $uri): array
    {
        try {
            $data = match (true) {
                $uri === 'cms://site' => $this->svc->siteInfo(),
                $uri === 'cms://settings' => $this->svc->settingsGet(),
                $uri === 'cms://hours' => $this->svc->hoursGet(),
                $uri === 'cms://block-types' => $this->svc->blockTypes(),
                str_starts_with($uri, 'cms://pages/') => $this->svc->pageGet(substr($uri, 12)),
                default => throw new \DomainException('Resource nicht gefunden', -32002),
            };
        } catch (ApiError $e) {
            throw new \DomainException($e->getMessage(), -32002);
        }
        return ['contents' => [['uri' => $uri, 'mimeType' => 'application/json',
            'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)]]];
    }

    // ------------------------------------------------------------------ Prompts

    private function prompts(): array
    {
        // Allgemeine Vorlagen; branchenspezifische bringt das Theme mit (theme.php → project.mcp.prompts)
        $out = [
            'seite_pruefen' => ['name' => 'seite_pruefen', 'title' => 'Seite auf Platzhalter prüfen',
                'description' => 'Findet offene [Platzhalter] und fehlende Alt-Texte.',
                'arguments' => [['name' => 'seite', 'description' => 'Pfad oder „home“', 'required' => false]],
                'text' => "Prüfe die Seite {seite} (get_page) sowie get_settings und list_media: Liste alle Texte in [eckigen Klammern], leere Pflichtfelder und Bilder ohne Alt-Text als übersichtliche Checkliste auf. Ändere nichts."],
            'beitrag_anlegen' => ['name' => 'beitrag_anlegen', 'title' => 'Eintrag in einer Datentabelle anlegen',
                'description' => 'Legt z. B. einen Beitrag, eine Person oder ein Produkt als Entwurf an.',
                'arguments' => [['name' => 'tabelle', 'description' => 'z. B. aktuelles', 'required' => true], ['name' => 'inhalt', 'description' => 'Stichpunkte oder Text', 'required' => true]],
                'text' => "Lies list_data_tables für die Felder der Tabelle {tabelle}. Lege mit save_entry einen Eintrag als Entwurf (status: draft) an, basierend auf: {inhalt}. Erfinde keine Fakten und zeige mir die Feldwerte vor dem Speichern."],
        ];
        foreach ((array) project('mcp.prompts', []) as $name => $pr) {
            if (str_contains((string) ($pr['text'] ?? ''), 'set_notice') && !CmsService::hasNotice()) continue;
            if (str_contains((string) ($pr['text'] ?? ''), 'opening_hours') && !CmsService::hasHours()) continue;
            $out[$name] = ['name' => $name] + $pr + ['arguments' => [], 'description' => ''];
        }
        return $out;
    }

    private function getPrompt(array $p): array
    {
        $all = $this->prompts();
        $name = (string) ($p['name'] ?? '');
        if (!isset($all[$name])) {
            throw new \DomainException('Prompt nicht gefunden', -32602);
        }
        $args = is_array($p['arguments'] ?? null) ? $p['arguments'] : [];
        $text = preg_replace_callback('~\{(\w+)\}~', fn($m) => (string) ($args[$m[1]] ?? ($m[1] === 'seite' ? 'home' : '(offen)')), $all[$name]['text']);
        return ['description' => $all[$name]['description'], 'messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => $text]]]];
    }
}
