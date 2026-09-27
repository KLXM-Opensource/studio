<?php
declare(strict_types=1);

namespace Core\AI;

use Core\Api\ApiError;
use Core\Api\CmsService;
use Core\Data\Entries;
use Core\Data\Tables;
use Core\Features;
use Core\Lang;
use Core\Media;
use Core\Pages;
use Core\Sanitizer;

/**
 * Assistent der Redaktion (Funktion „chat.assistant“ + „ai“, Recht „ai.use“, Schalter sys.ai_assistant):
 * Chat-Fenster überall in der Verwaltung (resources/js/_assistant.js → assistant.mjs).
 *
 *  - „Wie mache ich …?“: Antworten aus Handbuch, Technik und Tutorials (Core\AI\HelpIndex) und der Wissensdatenbank
 *    (Core\Support\Knowledge), Belege [n] als Links.
 *  - Kontext: der aktuelle Bildschirm wird ausdrücklich mitgeschickt (Adresse, Seite, Eintrag, Bild) – nur Dinge, die
 *    die Person selbst bearbeiten darf.
 *  - Aktionen: die KI schlägt anbieterunabhängig JSON vor ({"action", "args"}), der Server prüft Rechte und Ziel und
 *    zeigt eine Karte mit Vorschau (vorher/nachher). Ausgeführt wird erst nach „Ausführen“ – über dieselbe Werkzeug-
 *    Schicht wie MCP (Core\Api\CmsService, Kanal „ai“, Funktion „chat“). Mit „KI-Übernahmen ebenfalls zur Freigabe“
 *    (sys.review_ai) landen Aktionen unter „Eingereicht“. Nie löschen, nie veröffentlichen.
 *  - Verlauf je Person in {storage}/ai/chat.sqlite: standardmäßig nur Metadaten (Zeit, Anzahl, Bildschirm); Inhalte nur,
 *    wenn die Person den Chat speichert (jederzeit löschbar). Tageslimit und Zähler wie der KI-Bereich (Core\AI\Assist).
 */
final class Assistant
{
    public const MAX_QUESTION = 2000;
    public const MAX_HISTORY = 8;
    /** Metadaten ungespeicherter Unterhaltungen: Tage */
    public const KEEP_DAYS = 90;

    /**
     * Sichere Aktionen: Name => [Recht, Bezeichnung, Beschreibung für die KI]. Kein Löschen, kein Veröffentlichen.
     * Weitere Bedingungen (Mehrsprachigkeit, Datentabellen, Hinweisbalken) prüft actions().
     */
    private const ACTIONS = [
        'search_content' => [null, 'Inhalte durchsuchen', '{"q": "Suchbegriff"} – veröffentlichte Seiten und Einträge der Website durchsuchen, um IDs oder Fakten zu finden (läuft sofort, du bekommst die Treffer)'],
        'create_page' => ['pages.manage', 'Seite anlegen (Entwurf)', '{"title": "Seitentitel", "text": "<p>…</p>", "parent": Seiten-ID oder null} – neue, unveröffentlichte Seite mit einem Textabschnitt'],
        'update_page' => ['pages.manage', 'Seiteneinstellungen ändern', '{"page": Seiten-ID, "title": "…", "meta_description": "…"} – Titel und/oder Beschreibung für Suchmaschinen (nur angegebene Felder)'],
        'add_text' => ['pages.edit', 'Textabschnitt einfügen (Entwurf)', '{"page": Seiten-ID, "heading": "Überschrift", "text": "<p>…</p>"} – Textabschnitt am Ende der Seite, als Entwurf'],
        'update_block' => ['pages.edit', 'Text eines Blocks ändern (Entwurf)', '{"page": Seiten-ID, "block": "Block-ID", "field": "Feldname", "value": "neuer Text"} – ein Textfeld eines vorhandenen Blocks, als Entwurf'],
        'create_entry' => ['data.edit', 'Eintrag anlegen (Entwurf)', '{"table": "kurzname", "fields": {"feldname": "Wert"}} – neuer Eintrag in einer Datentabelle, als Entwurf'],
        'update_entry' => ['data.edit', 'Eintrag ändern', '{"table": "kurzname", "id": Eintrags-ID, "fields": {"feldname": "Wert"}} – nur die angegebenen Felder'],
        'translate_page' => ['pages.edit', 'Übersetzung der Seite anlegen', '{"page": Seiten-ID, "lang": "en"} – Kopie in der Zielsprache als Entwurf (danach „✦ Aus … übersetzen“)'],
        'translate_entry' => ['data.edit', 'Übersetzung des Eintrags anlegen', '{"table": "kurzname", "id": Eintrags-ID, "lang": "en"} – Kopie in der Zielsprache als Entwurf'],
        'set_alt_text' => ['media.upload', 'Alt-Text setzen', '{"media": Bild-ID, "alt": "Beschreibung, höchstens 125 Zeichen"} – Alternativtext eines Bildes'],
        'set_notice' => ['settings.edit', 'Hinweisbalken setzen', '{"text": "kurzer Hinweis", "active": true} – Hinweisbalken oben auf der Website (active false = ausblenden)'],
    ];

    // ================================================================== Verfügbarkeit, Oberfläche

    public static function available(): bool
    {
        try {
            return app()->auth->check() && Features::on('chat.assistant', false) && Features::on('ai', false) && can('ai.use')
                && Ai::enabled('text') && (bool) app()->settings->get('sys.ai_assistant', true);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Gehen Aktionen zur Freigabe („Eingereicht“)? */
    public static function reviewMode(): bool
    {
        return \Core\Review\Queue::enabled() && (bool) app()->settings->get('sys.review_ai', false);
    }

    /** Konfiguration für resources/js/_assistant.js (null = kein Assistent) */
    public static function client(): ?array
    {
        if (!self::available()) return null;
        $q = Assist::quota();
        return [
            'base' => url('/admin/api/assistant'), 'module' => asset('js/assistant.mjs'), 'css' => asset('css/assistant.css'),
            'page' => url('/admin/ai/assistent'), 'brand' => Assist::brand(), 'review' => self::reviewMode(),
            'quota' => $q['left'], 'user' => (int) (app()->auth->user()['id'] ?? 0), 'site' => site()->key,
            'external' => Ai::capability('text')['external'],
        ];
    }

    public static function clientScript(): string
    {
        $c = self::client();
        return $c ? '<script type="application/json" id="cms-assistant">' . json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' : '';
    }

    /** Erlaubte Aktionen dieser Person: Name => Beschreibung für die KI */
    public static function actions(): array
    {
        $out = [];
        foreach (self::ACTIONS as $name => [$perm, , $desc]) {
            if (self::allowedAction($name)) $out[$name] = $desc;
        }
        return $out;
    }

    private static function allowedAction(string $name, ?string $table = null): bool
    {
        if (!isset(self::ACTIONS[$name])) return false;
        $perm = self::ACTIONS[$name][0];
        if (in_array($name, ['translate_page', 'translate_entry'], true) && !Lang::multi()) return false;
        if (in_array($name, ['create_entry', 'update_entry', 'translate_entry'], true) && (!Features::on('data', false) || !Tables::content())) return false;
        if ($name === 'set_notice' && !CmsService::hasNotice()) return false;
        if ($name === 'search_content' && !\Core\Search\Search::enabled()) return false;
        if ($name === 'create_page' && !self::textBlock()) return false;
        if ($name === 'add_text' && !self::textBlock()) return false;
        return $perm === null || can($perm, $table);
    }

    public static function actionLabel(string $name): string
    {
        return __(self::ACTIONS[$name][1] ?? $name);
    }

    /** Blocktyp für Textabschnitte: bevorzugt „richtext“, sonst der erste Block mit einem richtext-Feld → [Typ, Textfeld, Überschriftfeld] */
    public static function textBlock(): ?array
    {
        $blocks = app()->theme->blocks();
        $order = array_keys($blocks);
        usort($order, fn($a, $b) => ($b === 'richtext') <=> ($a === 'richtext'));
        foreach ($order as $type) {
            if (str_starts_with($type, 'cblk_') || !Features::allowsBlock($type)) continue;
            $text = $head = null;
            foreach ((array) ($blocks[$type]['fields'] ?? []) as $f) {
                if (!$text && ($f['type'] ?? '') === 'richtext') $text = $f['name'];
                if (!$head && ($f['type'] ?? '') === 'text' && preg_match('~^(title|title_strong|heading|headline|titel|ueberschrift)$~', (string) $f['name'])) $head = $f['name'];
            }
            if ($text) return [$type, $text, $head];
        }
        return null;
    }

    // ================================================================== Kontext (aktueller Bildschirm)

    /**
     * $in: route (Pfad + Abfrage), title (Fenstertitel), page, entry {table, id}, media – alles optional.
     * @return array{text: string, route: string, page: ?array, entry: ?array, table: ?array, media: ?array}
     */
    public static function context(array $in): array
    {
        $route = mb_substr(preg_replace('~[\x00-\x1F]~', '', (string) ($in['route'] ?? '')), 0, 300);
        $path = (string) parse_url($route, PHP_URL_PATH);
        parse_str((string) parse_url($route, PHP_URL_QUERY), $query);
        $base = rtrim(base_path(), '/');
        if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
        $pageId = (int) ($in['page'] ?? 0);
        $table = null; $entryId = 0;
        if (preg_match('~^/admin/pages/(\d+)~', $path, $m)) $pageId = (int) $m[1];
        if (preg_match('~^/admin/data/([a-z0-9_]+)(?:/(\d+))?~', $path, $m)) { $table = $m[1]; $entryId = (int) ($m[2] ?? 0); }
        if (is_array($in['entry'] ?? null)) { $table = (string) ($in['entry']['table'] ?? $table); $entryId = (int) ($in['entry']['id'] ?? $entryId); }
        $mediaId = (int) ($in['media'] ?? $query['media'] ?? $query['m'] ?? 0);
        $out = ['text' => '', 'route' => $path, 'page' => null, 'entry' => null, 'table' => null, 'media' => null];
        $lines = ['Adresse in der Verwaltung: ' . ($path ?: '/admin') . (($t = trim(mb_substr((string) ($in['title'] ?? ''), 0, 120))) !== '' ? ' („' . $t . '“)' : '')];
        // Kontext für die KI ohne Redaktionsnotizen [# … #] (Core\EditorNotes)
        $clip = fn($v, int $n = 160) => mb_strimwidth(trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(\Core\EditorNotes::strip((string) $v)), ENT_QUOTES | ENT_HTML5))), 0, $n, '…');
        if ($pageId && can('pages.edit') && ($p = Pages::find($pageId))) {
            $out['page'] = $p;
            $lines[] = 'Seite #' . $p['id'] . ' „' . $p['title'] . '“ (Status: ' . $p['status'] . ', Sprache: ' . Lang::norm($p['lang'] ?? null) . ', Adresse: ' . Pages::url($p) . ')';
            $lines[] = 'Beschreibung für Suchmaschinen: ' . ($clip($p['meta_description']) ?: '(leer)');
            $lines[] = 'Blöcke (Entwurf) – ID, Typ, Textfelder:';
            foreach (array_slice(Pages::blocks($p, true), 0, 14) as $b) {
                $def = app()->theme->block((string) $b['type']);
                $fields = [];
                foreach ((array) ($def['fields'] ?? []) as $f) {
                    if (!in_array($f['type'] ?? '', ['text', 'textarea', 'richtext', 'inline'], true)) continue;
                    $v = $clip($b['data'][$f['name']] ?? '', 140);
                    if ($v !== '') $fields[] = $f['name'] . ' = ' . $v;
                }
                $lines[] = '- [' . $b['id'] . '] ' . $b['type'] . ($fields ? ': ' . implode(' | ', $fields) : '');
            }
        }
        if ($table && ($t = Tables::findContent($table)) && can('data.edit', $t['handle'])) {
            $out['table'] = $t;
            $fl = [];
            foreach ($t['fields'] as $f) $fl[] = $f['name'] . ' (' . $f['type'] . (!empty($f['required']) ? ', Pflicht' : '') . (!empty($f['options']) && is_array($f['options']) ? ': ' . implode('/', array_slice(array_keys($f['options']), 0, 8)) : '') . ')';
            $lines[] = 'Datentabelle „' . $t['name'] . '“ (Kurzname ' . $t['handle'] . ', Entwürfe: ' . (!empty($t['settings']['workflow']) ? 'ja' : 'nein – Einträge sind sofort sichtbar') . '). Felder: ' . implode(', ', array_slice($fl, 0, 20));
            if ($entryId && ($e = Entries::find($t, $entryId))) {
                $out['entry'] = ['table' => $t, 'entry' => $e];
                $vals = [];
                foreach ($t['fields'] as $f) {
                    if (in_array($f['type'], ['text', 'textarea', 'richtext', 'inline', 'date', 'datetime', 'select', 'number', 'email', 'url', 'tel'], true) && ($v = $clip($e[$f['name']] ?? '', 120)) !== '') $vals[] = $f['name'] . ' = ' . $v;
                }
                $lines[] = 'Eintrag #' . $e['id'] . ' „' . Entries::title($t, $e) . '“ (Status: ' . ($e['status'] ?? '') . '): ' . implode(' | ', array_slice($vals, 0, 14));
            }
        }
        if ($mediaId && can('media.upload') && ($m = Media::find($mediaId))) {
            $out['media'] = $m;
            $lines[] = 'Datei #' . $m['id'] . ' „' . ($m['title'] ?: $m['original_name']) . '“ (' . $m['mime'] . '), Alt-Text: ' . ($clip($m['alt']) ?: '(leer)');
        }
        if (Features::on('data', false)) {
            $tl = array_map(fn($t) => $t['handle'] . ' (' . $t['name'] . ')', array_filter(Tables::content(), fn($t) => can('data.edit', $t['handle'])));
            if ($tl) $lines[] = 'Datentabellen: ' . implode(', ', array_slice($tl, 0, 20));
        }
        if (Lang::multi()) $lines[] = 'Sprachen: ' . implode(', ', array_keys(Lang::all())) . ' (Standard ' . Lang::default() . ')';
        $lines[] = 'Rolle: ' . (app()->auth->role()['name'] ?? '') . ($out['page'] || $out['entry'] ? '' : '');
        $out['text'] = implode("\n", $lines);
        return $out;
    }

    // ================================================================== Frage beantworten

    /**
     * Antwort erzeugen. $emit(event, data): „sources“ (Quellen vorab), „delta“ (Textstück). Rückgabe für „done“.
     * @return array{text: string, sources: list<array>, actions: list<array>, conv: string, model: string, ms: int}
     */
    public static function ask(string $q, array $history, array $ctxIn, string $conv, callable $emit): array
    {
        if (!self::available()) throw new AiException(__('Der KI-Assistent ist für Sie auf dieser Website nicht verfügbar.'));
        $quota = Assist::quota();
        if ($quota['left'] === 0) {
            throw new AiException(__('Das Tageslimit für KI-Aufrufe dieser Website ist erreicht ({n}). Morgen geht es weiter – oder die Administration erhöht das Limit unter Grundeinstellungen → KI.', ['n' => $quota['cap']]));
        }
        $ctx = self::context($ctxIn);
        $sources = self::sources($q);
        $emit('sources', ['items' => array_map(fn($s) => ['n' => $s['n'], 'title' => $s['title'], 'url' => $s['url'], 'kind' => $s['kind']], $sources)]);
        $actions = self::actions();
        $pack = Prompts::assistant($q, $sources, $history, $actions, $ctx['text'], [
            'language' => \Core\I18n::locale(), 'brand' => Assist::brand(), 'notes' => Assist::ctx()['notes'],
        ]);
        $r = self::streamRound($pack, $emit, self::fakeAnswer($q, $sources, $ctx));
        [$text, $proposals] = self::split($r['text']);
        $ms = $r['ms'];
        // Suche als Werkzeug: sofort ausführen und die KI mit den Treffern ein zweites Mal fragen
        $search = array_values(array_filter($proposals, fn($p) => $p['action'] === 'search_content'));
        if ($search && isset($actions['search_content'])) {
            $hits = self::searchContent((string) ($search[0]['args']['q'] ?? $q));
            $emit('status', ['text' => __('Suche in den Inhalten: „{q}“', ['q' => (string) ($search[0]['args']['q'] ?? $q)])]);
            $pack2 = Prompts::assistant($q, $sources, $history, array_diff_key($actions, ['search_content' => 1]),
                $ctx['text'] . "\n\nSuchergebnisse für „" . ($search[0]['args']['q'] ?? $q) . "“:\n" . ($hits ?: '(keine Treffer)'), [
                'language' => \Core\I18n::locale(), 'brand' => Assist::brand(), 'notes' => Assist::ctx()['notes'],
            ]);
            if (trim($text) !== '') $emit('delta', ['t' => "\n\n"]);
            $r2 = self::streamRound($pack2, $emit, self::fakeAnswer($q . ' #2', $sources, $ctx));
            [$text2, $proposals] = self::split($r2['text']);
            $text = trim($text . "\n\n" . $text2);
            $ms += $r2['ms'];
        }
        $proposals = array_values(array_filter($proposals, fn($p) => $p['action'] !== 'search_content'));
        [$text, $cited] = VisitorChat::cite($text, $sources);
        $cards = [];
        foreach (array_slice($proposals, 0, 3) as $p) $cards[] = self::prepare($p['action'], $p['args']);
        $conv = self::track($conv, $ctx['route'], count($cards));
        Assist::log('assistant', 'ask', mb_substr($ctx['route'], 0, 120), 'suggested', $r['model'], $ms);
        return ['text' => $text, 'sources' => $cited, 'actions' => $cards, 'conv' => $conv, 'model' => $r['model'], 'ms' => $ms, 'quota' => Assist::quota()['left']];
    }

    /** Ein Aufruf mit Streaming; Text ab der Aktions-Markierung wird zurückgehalten */
    private static function streamRound(array $pack, callable $emit, string $fake): array
    {
        $full = '';
        $sent = 0;
        $hold = mb_strlen(Prompts::ACTIONS_MARK) + 2;
        $push = function (bool $final) use (&$full, &$sent, $emit, $hold): bool {
            $pos = mb_strpos($full, '@@');
            $fence = mb_strpos($full, '```');
            $cut = min($pos === false ? PHP_INT_MAX : $pos, $fence === false ? PHP_INT_MAX : $fence);
            $visible = $cut === PHP_INT_MAX ? ($final ? $full : mb_substr($full, 0, max(0, mb_strlen($full) - $hold))) : mb_substr($full, 0, $cut);
            if (mb_strlen($visible) > $sent) {
                $piece = mb_substr($visible, $sent);
                $sent = mb_strlen($visible);
                return $emit('delta', ['t' => $piece]);
            }
            return true;
        };
        try {
            $r = Ai::stream($pack['messages'], ['system' => $pack['system'], 'max_tokens' => $pack['max_tokens'], 'temperature' => $pack['temperature'], 'fake' => $fake],
                function (string $piece) use (&$full, $push) { $full .= $piece; return $push(false); });
        } catch (AiException $e) {
            throw new AiException(Assist::friendly($e->getMessage()), 0, $e);
        }
        $push(true);
        return $r;
    }

    /** Antwort in Text und Aktionen teilen (Markierung @@AKTIONEN@@ oder – Rückfall – ein JSON-Codeblock am Ende) */
    public static function split(string $raw): array
    {
        $text = $raw;
        $json = '';
        if (($p = mb_strpos($raw, Prompts::ACTIONS_MARK)) !== false) {
            $text = mb_substr($raw, 0, $p);
            $json = mb_substr($raw, $p + mb_strlen(Prompts::ACTIONS_MARK));
        } elseif (preg_match('~```(?:json)?\s*(\[.*?\]|\{.*?\})\s*```~s', $raw, $m, PREG_OFFSET_CAPTURE) && str_contains($m[1][0], '"action"')) {
            $text = substr($raw, 0, $m[0][1]) . substr($raw, $m[0][1] + strlen($m[0][0]));
            $json = $m[1][0];
        }
        $json = trim((string) preg_replace('~^```(?:json)?|```$~m', '', trim($json)));
        $list = [];
        if ($json !== '') {
            $start = strcspn($json, '[{');
            $d = json_decode(substr($json, $start), true);
            if (is_array($d)) {
                if (isset($d['action'])) $d = [$d];
                if (isset($d['actions']) && is_array($d['actions'])) $d = $d['actions'];
                foreach ($d as $a) {
                    if (is_array($a) && is_string($a['action'] ?? null) && isset(self::ACTIONS[$a['action']])) {
                        $list[] = ['action' => $a['action'], 'args' => is_array($a['args'] ?? null) ? $a['args'] : array_diff_key($a, ['action' => 1])];
                    }
                }
            }
        }
        $text = trim(str_replace(Prompts::ACTIONS_MARK, '', $text));
        return [$text, $list];
    }

    /** Quellen: Hilfe-Abschnitte + passende Artikel der Wissensdatenbank */
    public static function sources(string $q): array
    {
        $kinds = ['manual' => __('Handbuch'), 'tech' => __('Technische Dokumentation'), 'tutorial' => __('Tutorial'), 'kb' => __('Wissensdatenbank')];
        $out = [];
        foreach (HelpIndex::query($q, 5, can('system.manage') || Features::integrator()) as $s) {
            $out[] = ['n' => count($out) + 1, 'title' => $s['title'], 'kind' => $kinds[$s['kind']] ?? $s['kind'], 'url' => $s['url'], 'text' => $s['text']];
        }
        try {
            if (Features::on('support', false) && \Core\Support\Support::canRead()) {
                foreach (\Core\Support\Knowledge::search($q, 2, true) as $a) {
                    if (!\Core\Support\Knowledge::canSee($a)) continue;
                    $out[] = ['n' => count($out) + 1, 'title' => (string) $a['title'], 'kind' => $kinds['kb'], 'url' => url('/admin/support/wissen/' . (int) $a['id']),
                        'text' => mb_strimwidth(trim((string) preg_replace('~\s+~u', ' ', strip_tags((string) $a['body']))), 0, 1200, '…')];
                }
            }
        } catch (\Throwable $e) {
            error_log('[ai] assistant kb: ' . $e->getMessage());
        }
        return $out;
    }

    /** Inhalte suchen (veröffentlicht, wie Besucher) + Seiten nach Titel (auch Entwürfe) → Text für die KI */
    private static function searchContent(string $q): string
    {
        $lines = [];
        try {
            $r = \Core\Search\Search::query(\Core\Search\Search::clean($q), ['per' => 6, 'count_miss' => false]);
            foreach ($r['items'] as $it) {
                $ref = match (true) {
                    str_starts_with($it['id'], 'p-') => 'Seite #' . substr($it['id'], 2),
                    str_starts_with($it['id'], 'e-') && preg_match('~^e-([a-z0-9_]+)-(\d+)$~', $it['id'], $m) => 'Eintrag #' . $m[2] . ' in Tabelle ' . $m[1],
                    default => 'Datei ' . $it['id'],
                };
                $lines[] = '- ' . $ref . ': „' . $it['title_plain'] . '“ – ' . mb_strimwidth(trim(strip_tags((string) $it['snippet'])), 0, 200, '…');
            }
        } catch (\Throwable) {
            // Suche nicht verfügbar
        }
        $words = array_filter(array_map(fn($w) => mb_strtolower($w), preg_split('~\s+~u', $q) ?: []), fn($w) => mb_strlen($w) >= 3);
        if (can('pages.edit') && $words) {
            foreach (Pages::flat() as $p) {
                $t = mb_strtolower((string) $p['title']);
                foreach ($words as $w) if (str_contains($t, $w)) { $lines[] = '- Seite #' . $p['id'] . ': „' . $p['title'] . '“ (' . $p['status'] . ')'; break; }
                if (count($lines) >= 12) break;
            }
        }
        return implode("\n", array_unique($lines));
    }

    /** Test-Anbieter „fake“: nachvollziehbare Antworten und Aktionen für Oberflächentests */
    private static function fakeAnswer(string $q, array $sources, array $ctx): string
    {
        $l = mb_strtolower($q);
        $j = fn(array $a) => "\n" . Prompts::ACTIONS_MARK . "\n" . json_encode([$a], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (str_contains($l, 'seite') && preg_match('~anleg|erstell|neue~u', $l)) {
            $title = preg_match('~[„"“]([^“"”]+)[“"”]~u', $q, $m) ? $m[1] : 'Neue Seite';
            return '[Test-KI] Ich schlage vor, die Seite als Entwurf anzulegen' . ($sources ? ' [1]' : '') . '.' . $j(['action' => 'create_page', 'args' => ['title' => $title, 'text' => '<p>[bitte ergänzen: Inhalt der Seite]</p>']]);
        }
        if (str_contains($l, 'hinweis')) return '[Test-KI] Hier ist ein Vorschlag für den Hinweisbalken.' . $j(['action' => 'set_notice', 'args' => ['text' => 'Test-Hinweis: [bitte ergänzen]', 'active' => true]]);
        if (str_contains($l, 'alt') && $ctx['media']) return '[Test-KI] Vorschlag für den Alt-Text.' . $j(['action' => 'set_alt_text', 'args' => ['media' => (int) $ctx['media']['id'], 'alt' => 'Testbild mit Beschreibung']]);
        if (str_contains($l, 'beschreibung') && $ctx['page']) return '[Test-KI] Neue Beschreibung für Suchmaschinen.' . $j(['action' => 'update_page', 'args' => ['page' => (int) $ctx['page']['id'], 'meta_description' => 'Test-Beschreibung der Seite [bitte ergänzen]']]);
        if (str_contains($l, 'lösch')) return '[Test-KI] Löschen kann ich nicht – das machen Sie bitte selbst.' . $j(['action' => 'delete_page', 'args' => ['page' => 1]]);
        if ($sources) return '[Test-KI] ' . mb_strimwidth($sources[0]['text'], 0, 220, '…') . ' [1]';
        return '[Test-KI] Dazu habe ich in der Hilfe nichts gefunden.';
    }

    // ================================================================== Aktionen: Vorschau (Karte) und Ausführen

    /**
     * Vorschlag prüfen und als Karte aufbereiten: Bezeichnung, Ziel, Felder vorher/nachher, erlaubt?, Signatur.
     * @return array{id: string, action: string, label: string, target: string, fields: list<array>, notes: list<string>, allowed: bool, reason: ?string, args: array, sig: string, review: bool}
     */
    public static function prepare(string $action, array $args): array
    {
        $card = ['id' => bin2hex(random_bytes(5)), 'action' => $action, 'label' => self::actionLabel($action), 'target' => '', 'fields' => [], 'notes' => [],
            'allowed' => false, 'reason' => null, 'args' => [], 'sig' => '', 'review' => self::reviewMode()];
        try {
            [$card['target'], $card['fields'], $card['args'], $card['notes']] = self::normalize($action, $args);
            $card['allowed'] = true;
        } catch (AiException $e) {
            $card['reason'] = $e->getMessage();
        }
        if ($card['allowed']) $card['sig'] = self::sign($action, $card['args']);
        return $card;
    }

    private static function sign(string $action, array $args): string
    {
        return hash_hmac('sha256', json_encode([(int) (app()->auth->user()['id'] ?? 0), site()->key, $action, $args], JSON_UNESCAPED_UNICODE), (string) app()->config->get('app_key', CMS_NAME));
    }

    /** Argumente prüfen und vereinheitlichen → [Ziel, Felder, Argumente, Hinweise]; wirft AiException mit Grund */
    private static function normalize(string $action, array $a): array
    {
        $no = fn(string $m) => throw new AiException($m);
        if (!isset(self::ACTIONS[$action])) $no(__('Diese Aktion kann der Assistent nicht ausführen (nur Entwürfe, nichts löschen oder veröffentlichen).'));
        $str = fn(string $k, int $max = 300) => trim(mb_substr(strip_tags((string) ($a[$k] ?? '')), 0, $max));
        $rich = fn(string $k) => Sanitizer::block(mb_substr((string) ($a[$k] ?? ''), 0, 20000));
        $page = function () use ($a, $no) {
            $p = Pages::find((int) ($a['page'] ?? 0));
            if (!$p || ($p['type'] ?? 'page') !== 'page') $no(__('Seite nicht gefunden.'));
            return $p;
        };
        $f = fn(string $label, $before, $after, bool $html = false) => ['label' => $label, 'before' => $before, 'after' => $after, 'html' => $html];
        $notes = [];
        switch ($action) {
            case 'create_page':
                if (!self::allowedAction($action)) $no(self::denied());
                $title = $str('title', 120);
                if ($title === '') $no(__('Es fehlt ein Seitentitel.'));
                $parent = isset($a['parent']) && (int) $a['parent'] > 0 ? (int) $a['parent'] : null;
                if ($parent !== null && !Pages::find($parent)) $parent = null;
                $text = $rich('text') ?: '<p>' . e(Prompts::PLACEHOLDER) . '</p>';
                $notes[] = __('Die Seite wird als unveröffentlichter Entwurf angelegt.');
                if (Assist::placeholders($text)) $notes[] = __('Der Text enthält Platzhalter [bitte ergänzen: …] – vor dem Veröffentlichen ausfüllen.');
                return [__('Neue Seite „{t}“', ['t' => $title]) . ($parent ? ' · ' . __('unter „{p}“', ['p' => Pages::find($parent)['title']]) : ''),
                    [$f(__('Titel'), null, $title), $f(__('Text'), null, $text, true)], ['title' => $title, 'text' => $text, 'parent' => $parent], $notes];
            case 'update_page':
                if (!self::allowedAction($action)) $no(self::denied());
                $p = $page();
                $out = [];
                $fields = [];
                if (($t = $str('title', 120)) !== '' && $t !== $p['title']) { $out['title'] = $t; $fields[] = $f(__('Titel'), $p['title'], $t); }
                if (array_key_exists('meta_description', $a) && ($d = $str('meta_description', 300)) !== (string) $p['meta_description']) { $out['meta_description'] = $d; $fields[] = $f(__('Beschreibung für Suchmaschinen'), (string) $p['meta_description'], $d); }
                if (!$out) $no(__('Keine Änderung gegenüber dem aktuellen Stand.'));
                if ($p['status'] === 'published') $notes[] = __('Titel und Beschreibung gelten sofort (Seiteneinstellungen haben keinen Entwurf).');
                return [__('Seite „{t}“ (#{id})', ['t' => $p['title'], 'id' => $p['id']]), $fields, ['page' => (int) $p['id']] + $out, $notes];
            case 'add_text':
                if (!self::allowedAction($action)) $no(self::denied());
                $p = $page();
                $text = $rich('text');
                if (trim(strip_tags($text)) === '') $no(__('Es fehlt der Text.'));
                $head = $str('heading', 60);
                $notes[] = __('Wird am Ende der Seite als Entwurf eingefügt – sichtbar erst nach „Veröffentlichen“.');
                return [__('Seite „{t}“ (#{id})', ['t' => $p['title'], 'id' => $p['id']]),
                    array_values(array_filter([$head !== '' ? $f(__('Überschrift'), null, $head) : null, $f(__('Text'), null, $text, true)])),
                    ['page' => (int) $p['id'], 'heading' => $head, 'text' => $text], $notes];
            case 'update_block':
                if (!self::allowedAction($action)) $no(self::denied());
                $p = $page();
                $bid = (string) ($a['block'] ?? '');
                $block = null;
                foreach (Pages::blocks($p, true) as $b) if ((string) $b['id'] === $bid) $block = $b;
                if (!$block) $no(__('Block nicht gefunden.'));
                $field = (string) ($a['field'] ?? '');
                $def = null;
                foreach ((array) (app()->theme->block((string) $block['type'])['fields'] ?? []) as $fd) if ($fd['name'] === $field) $def = $fd;
                if (!$def || !in_array($def['type'] ?? '', ['text', 'textarea', 'richtext', 'inline'], true)) $no(__('Dieses Feld kann der Assistent nicht ändern (nur Textfelder).'));
                $html = in_array($def['type'], ['richtext', 'inline'], true);
                $value = match ($def['type']) { 'richtext' => $rich('value'), 'inline' => Sanitizer::inline(mb_substr((string) ($a['value'] ?? ''), 0, 4000)), default => $str('value', 4000) };
                $before = (string) ($block['data'][$field] ?? '');
                if ($value === $before) $no(__('Keine Änderung gegenüber dem aktuellen Stand.'));
                $notes[] = __('Änderung als Entwurf – sichtbar erst nach „Veröffentlichen“.');
                return [__('Seite „{t}“ (#{id}) · Block {type}', ['t' => $p['title'], 'id' => $p['id'], 'type' => app()->theme->block((string) $block['type'])['label'] ?? $block['type']]),
                    [$f(\Core\Data\Tables::label($def) ?: $field, $before, $value, $html)], ['page' => (int) $p['id'], 'block' => $bid, 'field' => $field, 'value' => $value], $notes];
            case 'create_entry':
            case 'update_entry':
                $t = Tables::findContent((string) ($a['table'] ?? ''));
                if (!$t || Tables::isInbox($t)) $no(__('Datentabelle nicht gefunden.'));
                if (!self::allowedAction($action, $t['handle'])) $no(self::denied());
                $in = is_array($a['fields'] ?? null) ? $a['fields'] : [];
                $e = null;
                if ($action === 'update_entry') {
                    $e = Entries::find($t, (int) ($a['id'] ?? 0));
                    if (!$e) $no(__('Eintrag nicht gefunden.'));
                }
                $vals = [];
                $fields = [];
                foreach ($t['fields'] as $fd) {
                    if (!array_key_exists($fd['name'], $in)) continue;
                    if (!in_array($fd['type'], ['text', 'textarea', 'richtext', 'inline', 'date', 'datetime', 'time', 'select', 'number', 'email', 'url', 'tel', 'bool', 'link'], true)) continue;
                    $v = $in[$fd['name']];
                    $v = match ($fd['type']) { 'richtext' => Sanitizer::block((string) $v), 'inline' => Sanitizer::inline((string) $v), 'bool' => (bool) $v, default => is_scalar($v) ? trim(mb_substr(strip_tags((string) $v), 0, 4000)) : '' };
                    if ($e && (string) ($e[$fd['name']] ?? '') === (string) $v) continue;
                    $vals[$fd['name']] = $v;
                    $fields[] = $f(Tables::label($fd), $e ? (string) ($e[$fd['name']] ?? '') : null, is_bool($v) ? ($v ? __('Ja') : __('Nein')) : (string) $v, in_array($fd['type'], ['richtext', 'inline'], true));
                }
                if (!$vals) $no(__('Keine gültigen Felder angegeben.'));
                $workflow = !empty($t['settings']['workflow']);
                $live = !$workflow || ($e && ($e['status'] ?? '') === 'published');
                if ($live && !can('data.publish', $t['handle'])) $no(__('Diese Änderung wäre sofort öffentlich – dafür fehlt Ihrer Rolle das Recht „Einträge veröffentlichen“.'));
                $notes[] = $live ? __('Achtung: Diese Änderung ist sofort auf der Website sichtbar.') : __('Wird als Entwurf gespeichert.');
                $target = $e ? __('Eintrag „{t}“ in {table}', ['t' => Entries::title($t, $e), 'table' => $t['name']]) : __('Neuer Eintrag in {table}', ['table' => $t['name']]);
                return [$target, $fields, ['table' => $t['handle'], 'id' => $e ? (int) $e['id'] : null, 'fields' => $vals], $notes];
            case 'translate_page':
                if (!self::allowedAction($action)) $no(self::denied());
                $p = $page();
                $lang = strtolower($str('lang', 10));
                if (!Lang::valid($lang) || $lang === Lang::norm($p['lang'] ?? null)) $no(__('Unbekannte oder gleiche Sprache.'));
                $notes[] = __('Legt eine Kopie in der Zielsprache als Entwurf an; übersetzen Sie sie dann mit „✦ Aus … übersetzen“ im Editor.');
                return [__('Seite „{t}“ (#{id}) → {lang}', ['t' => $p['title'], 'id' => $p['id'], 'lang' => Lang::all()[$lang] ?? $lang]), [], ['page' => (int) $p['id'], 'lang' => $lang], $notes];
            case 'translate_entry':
                $t = Tables::findContent((string) ($a['table'] ?? ''));
                if (!$t || !self::allowedAction($action, $t['handle'])) $no(self::denied());
                $e = Entries::find($t, (int) ($a['id'] ?? 0));
                if (!$e) $no(__('Eintrag nicht gefunden.'));
                $lang = strtolower($str('lang', 10));
                if (!Lang::valid($lang)) $no(__('Unbekannte Sprache.'));
                return [__('Eintrag „{t}“ → {lang}', ['t' => Entries::title($t, $e), 'lang' => Lang::all()[$lang] ?? $lang]), [], ['table' => $t['handle'], 'id' => (int) $e['id'], 'lang' => $lang],
                    [__('Legt eine Kopie in der Zielsprache als Entwurf an.')]];
            case 'set_alt_text':
                if (!self::allowedAction($action)) $no(self::denied());
                $m = Media::find((int) ($a['media'] ?? 0));
                if (!$m || !str_starts_with((string) $m['mime'], 'image/')) $no(__('Bild nicht gefunden.'));
                $alt = $str('alt', 250);
                if (mb_strlen($alt) < 3) $no(__('Alt-Text zu kurz.'));
                return [__('Bild „{t}“ (#{id})', ['t' => $m['title'] ?: $m['original_name'], 'id' => $m['id']]), [$f(__('Alt-Text'), (string) $m['alt'], $alt)], ['media' => (int) $m['id'], 'alt' => $alt], []];
            case 'set_notice':
                if (!self::allowedAction($action)) $no(self::denied());
                $text = $str('text', 300);
                $active = !array_key_exists('active', $a) || !in_array($a['active'], [false, 0, '0', 'false'], true);
                $cur = (string) setting((string) project('notice.text'), '');
                $curOn = (bool) setting((string) project('notice.active', ''), false);
                $notes[] = __('Der Hinweisbalken ist sofort auf der Website sichtbar.');
                return [__('Hinweisbalken der Website'), [$f(__('Text'), strip_tags($cur), $text), $f(__('Sichtbar'), $curOn ? __('Ja') : __('Nein'), $active ? __('Ja') : __('Nein'))],
                    ['text' => $text, 'active' => $active], $notes];
        }
        $no(__('Diese Aktion kann der Assistent nicht ausführen (nur Entwürfe, nichts löschen oder veröffentlichen).'));
        return [];
    }

    private static function denied(): string
    {
        return __('Dafür fehlt Ihrer Rolle die Berechtigung.');
    }

    /**
     * Aktion nach „Ausführen“: Signatur, Rechte und Ziel erneut prüfen, dann über CmsService (Kanal ai, Funktion chat).
     * @return array{ok: bool, message: string, url: ?string, review: ?array}
     */
    public static function execute(string $action, array $args, string $sig): array
    {
        if (!self::available()) throw new AiException(__('Der KI-Assistent ist für Sie auf dieser Website nicht verfügbar.'));
        if ($sig === '' || !hash_equals(self::sign($action, $args), $sig)) throw new AiException(__('Der Vorschlag ist abgelaufen oder wurde verändert. Bitte erneut fragen.'));
        // Erneut prüfen (Stand kann sich seit dem Vorschlag geändert haben) – gleiche Argumente, gleiche Regeln
        [$target, , $norm] = self::normalize($action, $args);
        $u = app()->auth->user();
        $review = self::reviewMode();
        $svc = $review ? CmsService::forAi('chat')
            : (new CmsService(['id' => null, 'name' => Assist::brand(), 'scope' => 'write', 'user_id' => $u['id'] ?? null, 'review_mode' => 'direct']))->origin('ai', null, 'chat');
        try {
            $res = match ($action) {
                'create_page' => $svc->pageCreate(['title' => $norm['title'], 'parent' => $norm['parent'], 'status' => 'draft',
                    'blocks' => [self::textBlockData('', $norm['text'])]]),
                'update_page' => $svc->pageUpdate((int) $norm['page'], array_intersect_key($norm, ['title' => 1, 'meta_description' => 1])),
                'add_text' => (function () use ($svc, $norm) {
                    $b = self::textBlockData($norm['heading'], $norm['text']);
                    return $svc->blockAdd((int) $norm['page'], $b['type'], $b['data'], [], null, null, false);
                })(),
                'update_block' => $svc->blockUpdate((int) $norm['page'], (string) $norm['block'], [$norm['field'] => $norm['value']], null, false, false),
                'create_entry' => $svc->dataSave($norm['table'], null, $norm['fields'] + ['status' => 'draft']),
                'update_entry' => $svc->dataSave($norm['table'], (int) $norm['id'], $norm['fields']),
                'translate_page' => $svc->pageTranslate((int) $norm['page'], $norm['lang']),
                'translate_entry' => $svc->dataTranslate($norm['table'], (int) $norm['id'], $norm['lang']),
                'set_alt_text' => $svc->mediaUpdate((int) $norm['media'], ['alt' => $norm['alt'], 'decorative' => false]),
                'set_notice' => $svc->noticeSet($norm['text'], (bool) $norm['active']),
                default => throw new AiException(__('Diese Aktion kann der Assistent nicht ausführen (nur Entwürfe, nichts löschen oder veröffentlichen).')),
            };
        } catch (\Core\Review\Pending $p) {
            $id = (int) ($p->data['id'] ?? 0);
            Assist::log('assistant', $action, mb_substr($target, 0, 120), 'applied');
            return ['ok' => true, 'message' => __('Zur Freigabe eingereicht – gespeichert wird nach der Prüfung unter „Eingereicht“.'), 'url' => url('/admin/ai/eingereicht' . ($id ? '/' . $id : '')),
                'review' => ['id' => $id]];
        } catch (ApiError $e) {
            throw new AiException($e->getMessage());
        }
        Assist::log('assistant', $action, mb_substr($target, 0, 120), 'applied');
        \Core\PageCache::clear();
        return ['ok' => true, 'message' => __('Erledigt: {what}', ['what' => $target]), 'url' => self::resultUrl($action, $norm, is_array($res) ? $res : []), 'review' => null];
    }

    /** Daten für einen Textabschnitt im Blocktyp des Themes */
    private static function textBlockData(string $heading, string $html): array
    {
        [$type, $textField, $headField] = self::textBlock() ?? throw new AiException(__('Dieses Kit hat keinen Textblock.'));
        $data = [$textField => $html];
        if ($heading !== '') {
            if ($headField) $data[$headField] = $heading;
            else $data[$textField] = '<h2>' . e($heading) . '</h2>' . $html;
        }
        return ['type' => $type, 'data' => $data];
    }

    private static function resultUrl(string $action, array $norm, array $res): ?string
    {
        return match ($action) {
            'create_page', 'translate_page' => isset($res['id']) ? url('/admin/pages/' . (int) $res['id']) : null,
            'update_page', 'add_text', 'update_block' => url('/admin/pages/' . (int) $norm['page']),
            'create_entry', 'update_entry', 'translate_entry' => isset($res['id']) ? url('/admin/data/' . $norm['table'] . '/' . (int) $res['id']) : url('/admin/data/' . $norm['table']),
            'set_alt_text' => url('/admin/media') . '?media=' . (int) $norm['media'],
            'set_notice' => url('/'),
            default => null,
        };
    }

    // ================================================================== Verlauf ({storage}/ai/chat.sqlite)

    public static function db(): \Core\Database
    {
        static $db = [];
        $k = site()->key;
        if (isset($db[$k])) return $db[$k];
        $dir = site()->storage('ai');
        if (!is_dir($dir)) @mkdir($dir, 0770, true);
        $d = new \Core\Database(['driver' => 'sqlite', 'path' => $dir . '/chat.sqlite']);
        $d->pdo->exec("CREATE TABLE IF NOT EXISTS conversations (id TEXT PRIMARY KEY, user_id INT NOT NULL, created TEXT NOT NULL, updated TEXT NOT NULL,
            turns INT NOT NULL DEFAULT 0, actions INT NOT NULL DEFAULT 0, route TEXT NOT NULL DEFAULT '', saved INT NOT NULL DEFAULT 0, pinned INT NOT NULL DEFAULT 0,
            title TEXT NULL, messages TEXT NULL)");
        $d->pdo->exec('CREATE INDEX IF NOT EXISTS conversations_user ON conversations (user_id, updated)');
        return $db[$k] = $d;
    }

    private static function uid(): int
    {
        return (int) (app()->auth->user()['id'] ?? 0);
    }

    /** Frage zählen (nur Metadaten) → ID der Unterhaltung */
    private static function track(string $conv, string $route, int $actions): string
    {
        try {
            $conv = preg_match('~^[a-f0-9]{16}$~', $conv) ? $conv : bin2hex(random_bytes(8));
            $db = self::db();
            $row = $db->fetch('SELECT user_id FROM conversations WHERE id = ?', [$conv]);
            if ($row && (int) $row['user_id'] !== self::uid()) $conv = bin2hex(random_bytes(8));
            $db->query('INSERT INTO conversations (id, user_id, created, updated, turns, actions, route) VALUES (?, ?, ?, ?, 1, ?, ?)
                ON CONFLICT(id) DO UPDATE SET updated = excluded.updated, turns = turns + 1, actions = actions + excluded.actions',
                [$conv, self::uid(), date('Y-m-d H:i:s'), date('Y-m-d H:i:s'), $actions, mb_substr($route, 0, 200)]);
            if (random_int(1, 50) === 1) $db->query('DELETE FROM conversations WHERE saved = 0 AND updated < ?', [date('Y-m-d H:i:s', strtotime('-' . self::KEEP_DAYS . ' days'))]);
        } catch (\Throwable $e) {
            error_log('[ai] assistant history: ' . $e->getMessage());
        }
        return $conv;
    }

    /** Unterhaltung speichern (Inhalte) bzw. anheften – nur eigene */
    public static function save(string $id, array $messages, ?string $title = null, ?bool $pinned = null): array
    {
        if (!preg_match('~^[a-f0-9]{16}$~', $id)) $id = bin2hex(random_bytes(8));
        $db = self::db();
        $row = $db->fetch('SELECT * FROM conversations WHERE id = ?', [$id]);
        if ($row && (int) $row['user_id'] !== self::uid()) throw new AiException(__('Unterhaltung nicht gefunden.'));
        $clean = [];
        foreach (array_slice($messages, -100) as $m) {
            if (!is_array($m) || !in_array($m['role'] ?? '', ['user', 'assistant'], true)) continue;
            $clean[] = ['role' => $m['role'], 'content' => mb_substr(strip_tags((string) ($m['content'] ?? '')), 0, 8000),
                'sources' => array_values(array_map(fn($s) => ['n' => (int) ($s['n'] ?? 0), 'title' => mb_substr((string) ($s['title'] ?? ''), 0, 200), 'url' => self::localUrl((string) ($s['url'] ?? ''))],
                    array_filter(array_slice((array) ($m['sources'] ?? []), 0, 10), 'is_array')))];
        }
        if (!$clean) throw new AiException(__('Nichts zu speichern.'));
        $first = '';
        foreach ($clean as $m) if ($m['role'] === 'user') { $first = $m['content']; break; }
        $title = trim(mb_substr(strip_tags((string) ($title ?? '')), 0, 120)) ?: mb_strimwidth(trim((string) preg_replace('~\s+~u', ' ', $first)), 0, 80, '…');
        $now = date('Y-m-d H:i:s');
        $db->query('INSERT INTO conversations (id, user_id, created, updated, turns, route, saved, pinned, title, messages) VALUES (?, ?, ?, ?, ?, \'\', 1, ?, ?, ?)
            ON CONFLICT(id) DO UPDATE SET updated = excluded.updated, saved = 1, pinned = excluded.pinned, title = excluded.title, messages = excluded.messages',
            [$id, self::uid(), $now, $now, count(array_filter($clean, fn($m) => $m['role'] === 'user')), $pinned ?? (bool) ($row['pinned'] ?? false) ? 1 : 0, $title, json_encode($clean, JSON_UNESCAPED_UNICODE)]);
        return ['id' => $id, 'title' => $title];
    }

    private static function localUrl(string $u): string
    {
        return preg_match('~^/(?!/)~', $u) ? mb_substr($u, 0, 300) : '';
    }

    /** Gespeicherte Unterhaltungen (angeheftete zuerst) + Metadaten der letzten ungespeicherten */
    public static function list(int $limit = 50): array
    {
        $db = self::db();
        return [
            'saved' => $db->fetchAll('SELECT id, title, created, updated, turns, pinned FROM conversations WHERE user_id = ? AND saved = 1 ORDER BY pinned DESC, updated DESC LIMIT ' . max(1, min(200, $limit)), [self::uid()]),
            'recent' => $db->fetchAll('SELECT id, created, updated, turns, actions, route FROM conversations WHERE user_id = ? AND saved = 0 ORDER BY updated DESC LIMIT 20', [self::uid()]),
        ];
    }

    public static function get(string $id): ?array
    {
        $r = self::db()->fetch('SELECT * FROM conversations WHERE id = ? AND user_id = ? AND saved = 1', [$id, self::uid()]);
        if (!$r) return null;
        $r['messages'] = json_decode((string) $r['messages'], true) ?: [];
        return $r;
    }

    /** Löschen: gespeicherte Inhalte und Metadaten */
    public static function delete(string $id): bool
    {
        return (bool) self::db()->query('DELETE FROM conversations WHERE id = ? AND user_id = ?', [$id, self::uid()])->rowCount();
    }

    /** Alle eigenen Unterhaltungen löschen (Konto → Datenschutz, Verlauf) */
    public static function deleteAll(): int
    {
        return (int) self::db()->query('DELETE FROM conversations WHERE user_id = ?', [self::uid()])->rowCount();
    }
}
