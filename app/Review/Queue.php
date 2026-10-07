<?php
declare(strict_types=1);

namespace Core\Review;

use Core\Api\ApiError;
use Core\Api\CmsService;
use Core\Features;
use Core\PageCache;

/**
 * Prüf-Ebene „Eingereicht“: Herkunft aller Änderungen über REST-API, MCP und KI (Tabelle change_log je Website)
 * und Freigabe von Änderungen, die nicht direkt übernommen werden sollen.
 *
 * - Jede Schreibaktion von CmsService läuft durch run(): direkt → ausführen + protokollieren (status applied);
 *   Token im Modus „zur Freigabe“ (bzw. KI mit sys.review_ai) → Probelauf in einer Transaktion (Prüfung + Diff),
 *   zurückrollen und als Einreichung speichern (status pending) → Pending (REST 202, MCP pending_review).
 * - approve() spielt die Einreichung über denselben Weg (CmsService) ein – mit Token-Benutzer als Urheber und
 *   Prüfer im Protokoll. Konflikt: Gegenstand seit der Einreichung geändert (Prüfsumme) → erst neu prüfen.
 * - KI-Übernahmen aus der Verwaltung (AiController) protokolliert track().
 */
final class Queue
{
    public const STATUSES = ['pending', 'applied', 'rejected'];
    public const CHANNELS = ['api', 'mcp', 'ai'];
    public const PER_PAGE = 50;

    /** Schreibaktionen, die eine Einreichung wieder einspielen darf (öffentliche Methoden von CmsService) */
    public const METHODS = ['settingsUpdate', 'designUpdate', 'pageCreate', 'pageUpdate', 'pageDelete', 'pageTranslate', 'publish', 'discard', 'restore',
        'blocksReplace', 'blockAdd', 'blockUpdate', 'blockRemove', 'blockMove', 'mediaUpdate', 'mediaCrop', 'mediaDelete',
        'dataSave', 'dataDelete', 'dataTranslate', 'dataPick'];

    /** Position des Arguments „publish“ (Blöcke) – „Bearbeiten & übernehmen“ übernimmt nur als Entwurf */
    private const PUBLISH_ARG = ['blocksReplace' => 2, 'blockAdd' => 6, 'blockUpdate' => 5, 'blockRemove' => 2, 'blockMove' => 3];
    /** Position der Feldwerte, die sich vor dem Übernehmen bearbeiten lassen */
    public const EDIT_ARG = ['settingsUpdate' => 0, 'dataSave' => 2, 'mediaUpdate' => 1, 'pageUpdate' => 1, 'pageCreate' => 0];

    /** Stand vor/nach der zuletzt direkt ausgeführten Aktion (für approve) */
    private static ?array $last = null;
    private static ?int $pending = null;

    public static function enabled(): bool
    {
        return Features::on('review', false);
    }

    /** Darf die angemeldete Person Einreichungen sehen und freigeben? */
    public static function canReview(): bool
    {
        return self::enabled() && isset(app()->auth) && app()->auth->user() !== null && can('review.manage');
    }

    // ================================================================= Beschriftungen

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'settingsUpdate' => __('Einstellungen ändern'), 'designUpdate' => __('Design ändern'),
            'pageCreate' => __('Seite anlegen'), 'pageUpdate' => __('Seiteneinstellungen ändern'), 'pageDelete' => __('Seite löschen'),
            'pageTranslate' => __('Seite übersetzen'), 'publish' => __('Seite veröffentlichen'), 'discard' => __('Entwurf verwerfen'),
            'restore' => __('Version wiederherstellen'), 'blocksReplace' => __('Alle Blöcke ersetzen'), 'blockAdd' => __('Block einfügen'),
            'blockUpdate' => __('Block ändern'), 'blockRemove' => __('Block entfernen'), 'blockMove' => __('Block verschieben'),
            'mediaUpdate' => __('Medien-Infos ändern'), 'mediaCrop' => __('Bild zuschneiden'), 'mediaDelete' => __('Datei löschen'),
            'mediaUpload' => __('Datei hochladen'), 'dataSave' => __('Eintrag speichern'), 'dataDelete' => __('Eintrag löschen'),
            'dataTranslate' => __('Eintrag übersetzen'), 'dataPick' => __('Auswahl setzen'), 'requestStatus' => __('Anfrage-Status setzen'),
            'aiPageCreate' => __('Seite generieren'), 'aiTableCreate' => __('Tabelle generieren'), 'aiPageText' => __('Text in Seite einfügen'),
            'aiPageTranslate' => __('Übersetzung übernehmen'), 'aiAlt' => __('Alt-Text übernehmen'), 'aiSeo' => __('SEO-Beschreibung übernehmen'),
            default => $action,
        };
    }

    public static function channelLabel(string $c): string
    {
        return match ($c) { 'api' => __('REST-API'), 'mcp' => __('MCP'), 'ai' => __('KI'), default => $c };
    }

    public static function statusLabel(string $s): string
    {
        return match ($s) { 'pending' => __('Offen'), 'applied' => __('Übernommen'), 'rejected' => __('Abgelehnt'), default => $s };
    }

    public static function featureLabel(?string $f): string
    {
        return match ((string) $f) {
            'text' => __('Texte'), 'translate' => __('Übersetzen'), 'seo' => __('SEO'), 'alt' => __('Alt-Texte'),
            'pagegen' => __('Seiten-Generator'), 'tablegen' => __('Tabellen-Generator'), 'chat' => __('Assistent'), default => (string) $f,
        };
    }

    public static function typeLabel(string $t): string
    {
        return match ($t) {
            'page' => __('Seite'), 'entry' => __('Eintrag'), 'media' => __('Medien'), 'settings' => app()->theme->settingsTitle(),
            'design' => __('Design'), 'pick' => __('Auswahl (geteilt)'), 'table' => __('Tabelle'), default => $t,
        };
    }

    // ================================================================= Schreiben (CmsService)

    /**
     * Schreibaktion ausführen, protokollieren oder einreichen.
     * $ctx: token (Datensatz), origin [channel, client, feature], lang, review (bool), change (?int: Einreichung wird gerade übernommen),
     *       recheck (?int: Einreichung neu prüfen)
     * $simulate: Folgezustand ohne Ausführung berechnen (für Aktionen mit Dateien, z. B. Löschen, Zuschnitt, Design)
     * $call: führt die eigentliche Aktion aus
     */
    public static function run(array $ctx, string $method, array $args, array $target, ?callable $simulate, callable $call): mixed
    {
        $before = Snapshot::take($target);
        if (!empty($ctx['review']) || !empty($ctx['recheck'])) {
            [$after, $target] = self::dryRun($target, $simulate, $call);
            if (!empty($ctx['recheck'])) {
                self::update((int) $ctx['recheck'], ['before_json' => self::json($before), 'after_json' => self::json($after),
                    'diff_json' => self::json(Diff::compute($target, $before, $after)), 'base_hash' => Snapshot::hash($before),
                    'checked_at' => now(), 'error' => null, 'target_json' => self::json($target)]);
                throw new Pending(self::pendingJson((int) $ctx['recheck']));
            }
            $id = self::insert($ctx, $method, $args, $target, $before, $after, 'pending');
            self::notify();
            // Push-Mitteilung an alle mit review.manage (Konto → Benachrichtigungen, Core\Push)
            if ($row = self::find($id)) \Core\Push\Hooks::review($id, (string) ($row['summary'] ?? ''), (string) ($row['entity_label'] ?? ''));
            throw new Pending(self::pendingJson($id));
        }
        $out = $call();
        $target = self::resolve($target, $out);
        $after = Snapshot::take($target);
        self::$last = ['target' => $target, 'before' => $before, 'after' => $after];
        if (empty($ctx['change'])) self::insert($ctx, $method, $args, $target, $before, $after, 'applied');
        return $out;
    }

    /** Probelauf: ausführen, Folgezustand lesen, alles zurückrollen */
    private static function dryRun(array $target, ?callable $simulate, callable $call): array
    {
        if ($simulate !== null) {
            return [$simulate(Snapshot::take($target)), $target];
        }
        $dbs = Snapshot::dbs($target);
        foreach ($dbs as $db) $db->pdo->beginTransaction();
        try {
            $out = $call();
            $target = self::resolve($target, $out);
            $after = Snapshot::take($target);
        } finally {
            foreach ($dbs as $db) if ($db->pdo->inTransaction()) $db->pdo->rollBack();
            Snapshot::reset($target);
            PageCache::clear();
        }
        return [$after, $target];
    }

    /** Neu angelegte Gegenstände: ID aus dem Ergebnis übernehmen */
    private static function resolve(array $target, mixed $out): array
    {
        if (($target['id'] ?? null) === null && is_array($out) && isset($out['id']) && in_array($target['type'], ['page', 'entry', 'media'], true)) {
            $target['id'] = (int) $out['id'];
        }
        return $target;
    }

    private static function json(mixed $v): ?string
    {
        return $v === null ? null : (json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: null);
    }

    private static function entityId(array $target): ?string
    {
        return match ($target['type']) {
            'entry' => ($target['table'] ?? '') . ':' . ($target['id'] ?? 'neu'),
            'pick' => (string) ($target['table'] ?? ''),
            'settings', 'design' => null,
            default => isset($target['id']) ? (string) $target['id'] : null,
        };
    }

    private static function publishFlag(string $method, array $args): bool
    {
        if (isset(self::PUBLISH_ARG[$method])) return !empty($args[self::PUBLISH_ARG[$method]]);
        if ($method === 'publish') return true;
        $in = $args[self::EDIT_ARG[$method] ?? -1] ?? null;
        return is_array($in) && ($in['status'] ?? '') === 'published' && in_array($method, ['pageCreate', 'pageUpdate'], true);
    }

    private static function summary(string $method, array $args, array $target, array $diff): string
    {
        $s = self::actionLabel($method);
        if ($method === 'dataSave') $s = ($target['id'] ?? null) !== null && ($args[1] ?? null) !== null ? __('Eintrag ändern') : __('Eintrag anlegen');
        $n = Diff::count($diff);
        $s .= ' · ' . ($n === 1 ? __('1 Änderung') : __('{n} Änderungen', ['n' => $n]));
        if (self::publishFlag($method, $args) && $method !== 'publish') $s .= ' · ' . __('sofort veröffentlichen');
        return $s;
    }

    private static function insert(array $ctx, string $method, array $args, array $target, ?array $before, ?array $after, string $status): int
    {
        $token = (array) ($ctx['token'] ?? []);
        $origin = (array) ($ctx['origin'] ?? []);
        $diff = Diff::compute($target, $before, $after);
        $pending = $status === 'pending';
        $id = app()->db->insert('change_log', [
            'created_at' => now(), 'channel' => in_array($origin['channel'] ?? '', self::CHANNELS, true) ? $origin['channel'] : 'api',
            'token_id' => isset($token['id']) ? (int) $token['id'] : null, 'token_name' => mb_substr((string) ($token['name'] ?? ''), 0, 120) ?: null,
            'client' => ($c = mb_substr(trim((string) ($origin['client'] ?? '')), 0, 120)) !== '' ? $c : null,
            'feature' => ($f = (string) ($origin['feature'] ?? '')) !== '' ? mb_substr($f, 0, 30) : null,
            'user_id' => isset($token['user_id']) && $token['user_id'] !== null ? (int) $token['user_id'] : null,
            'action' => $method, 'entity_type' => $target['type'], 'entity_id' => self::entityId($target),
            // Name wie er jetzt heißt (bei Einreichungen der aktuelle, bei Neuanlagen der vorgeschlagene)
            'entity_label' => mb_substr(Snapshot::label($target, $pending ? ($before ?? $after) : ($after ?? $before)), 0, 190),
            'summary' => self::summary($method, $args, $target, $diff), 'lang' => $ctx['lang'] ?? null,
            'payload_json' => self::json(['method' => $method, 'args' => $args]), 'target_json' => self::json($target),
            'before_json' => $pending ? self::json($before) : null, 'after_json' => $pending ? self::json($after) : null,
            'diff_json' => self::json($diff), 'base_hash' => Snapshot::hash($before), 'status' => $status,
        ]);
        self::$pending = null;
        if (random_int(1, 200) === 1) {
            app()->db->query("DELETE FROM change_log WHERE status != 'pending' AND created_at < ?", [date('Y-m-d H:i:s', strtotime('-365 days'))]);
        }
        return $id;
    }

    /** Direkt ausgeführte Aktion ohne Prüfung nachträglich protokollieren (z. B. Uploads) */
    public static function record(array $ctx, string $method, array $target, ?array $before = null): void
    {
        try {
            self::insert($ctx, $method, [], $target, $before, Snapshot::take($target), 'applied');
        } catch (\Throwable $e) {
            error_log('[review] record: ' . $e->getMessage());
        }
    }

    private static function update(int $id, array $data): void
    {
        app()->db->update('change_log', $data, 'id = :id', ['id' => $id]);
        self::$pending = null;
    }

    /**
     * KI-Übernahme aus der Verwaltung protokollieren (direkt ausgeführt): Stand vor/nach $fn, Herkunft KI + Funktion.
     * $target['id'] null → ID aus dem Ergebnis ($out['id'] bzw. $out['handle'] bei Tabellen).
     */
    public static function track(string $feature, string $action, array $target, callable $fn): mixed
    {
        $before = Snapshot::take($target);
        $out = $fn();
        try {
            if (($target['id'] ?? null) === null && is_array($out)) {
                $target['id'] = $target['type'] === 'table' ? ($out['handle'] ?? null) : ($out['id'] ?? null);
            }
            if (($target['id'] ?? null) === null && !in_array($target['type'], ['settings', 'design'], true)) return $out;   // nichts angelegt
            $after = Snapshot::take($target);
            if (json_encode($before) === json_encode($after)) return $out;
            $u = app()->auth->user();
            self::insert(['token' => ['id' => null, 'name' => \Core\AI\Assist::brand(), 'user_id' => $u['id'] ?? null],
                'origin' => ['channel' => 'ai', 'feature' => $feature], 'lang' => null], $action, [], $target, $before, $after, 'applied');
        } catch (\Throwable $e) {
            error_log('[review] track: ' . $e->getMessage());
        }
        return $out;
    }

    // ================================================================= Lesen

    public static function find(int $id): ?array
    {
        return app()->db->fetch('SELECT c.*, u.email AS user_email, u.name AS user_name, r.email AS reviewer_email, r.name AS reviewer_name
            FROM change_log c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN users r ON r.id = c.reviewer_id WHERE c.id = ?', [$id]);
    }

    /** $f: status (pending|applied|rejected|all), channel, token (ID), type, q, page */
    public static function list(array $f): array
    {
        [$where, $params] = self::where($f);
        $page = max(1, (int) ($f['page'] ?? 1));
        $total = (int) app()->db->fetchValue('SELECT COUNT(*) FROM change_log c' . $where, $params);
        $rows = app()->db->fetchAll('SELECT c.id, c.created_at, c.channel, c.token_id, c.token_name, c.client, c.feature, c.user_id, c.action, c.entity_type,
            c.entity_id, c.entity_label, c.summary, c.status, c.reviewed_at, c.reason, c.error, u.email AS user_email, u.name AS user_name
            FROM change_log c LEFT JOIN users u ON u.id = c.user_id' . $where . ' ORDER BY c.id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, (int) ceil($total / self::PER_PAGE)), 'page' => $page];
    }

    private static function where(array $f, bool $withStatus = true): array
    {
        $w = [];
        $p = [];
        if ($withStatus && in_array($f['status'] ?? '', self::STATUSES, true)) { $w[] = 'c.status = ?'; $p[] = $f['status']; }
        if (in_array($f['channel'] ?? '', self::CHANNELS, true)) { $w[] = 'c.channel = ?'; $p[] = $f['channel']; }
        if (($f['token'] ?? '') !== '' && ctype_digit((string) $f['token'])) { $w[] = 'c.token_id = ?'; $p[] = (int) $f['token']; }
        if (in_array($f['type'] ?? '', Snapshot::TYPES, true)) { $w[] = 'c.entity_type = ?'; $p[] = $f['type']; }
        if (($q = trim((string) ($f['q'] ?? ''))) !== '') {
            $w[] = '(c.entity_label LIKE ? OR c.summary LIKE ? OR c.token_name LIKE ? OR c.client LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%", "%$q%");
        }
        return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $p];
    }

    /** Anzahl je Status (mit den übrigen Filtern) */
    public static function counts(array $f = []): array
    {
        [$where, $params] = self::where($f, false);
        $out = array_fill_keys(self::STATUSES, 0);
        foreach (app()->db->fetchAll('SELECT c.status, COUNT(*) AS n FROM change_log c' . $where . ' GROUP BY c.status', $params) as $r) $out[$r['status']] = (int) $r['n'];
        $out['all'] = array_sum($out);
        return $out;
    }

    /** Offene Einreichungen (für Badge, Übersicht, Netzwerk) */
    public static function pendingCount(): int
    {
        if (self::$pending !== null) return self::$pending;
        try {
            return self::$pending = (int) app()->db->fetchValue("SELECT COUNT(*) FROM change_log WHERE status = 'pending'");
        } catch (\Throwable) {
            return self::$pending = 0;   // Tabelle fehlt noch (vor „migrate“)
        }
    }

    /** Tokens bzw. Quellen, die Änderungen eingereicht haben (Filter) */
    public static function sources(): array
    {
        return app()->db->fetchAll('SELECT token_id, MAX(token_name) AS name, MAX(channel) AS channel, COUNT(*) AS n FROM change_log WHERE token_id IS NOT NULL GROUP BY token_id ORDER BY name');
    }

    public static function decode(array $row): array
    {
        foreach (['payload', 'target', 'before', 'after', 'diff'] as $k) {
            $row[$k] = isset($row[$k . '_json']) && $row[$k . '_json'] !== null ? json_decode((string) $row[$k . '_json'], true) : null;
        }
        return $row;
    }

    public static function adminUrl(int $id): string
    {
        return absolute_url('/admin/ai/eingereicht/' . $id);
    }

    /** Antwort bei Einreichung (REST 202, MCP) */
    public static function pendingJson(int $id): array
    {
        $row = self::find($id);
        return [
            'status' => 'pending_review', 'id' => $id, 'url' => self::adminUrl($id), 'status_url' => absolute_url('/api/v1/changes/' . $id),
            'summary' => (string) ($row['summary'] ?? ''), 'entity' => ['type' => $row['entity_type'] ?? null, 'id' => $row['entity_id'] ?? null, 'label' => $row['entity_label'] ?? null],
            'message' => 'Die Änderung wurde NICHT ausgeführt, sondern zur Freigabe eingereicht. Ein Mensch prüft sie in der Verwaltung (Eingereicht). '
                . 'Status abfragen: GET /api/v1/changes/' . $id . ' bzw. MCP-Tool get_change_status.',
        ];
    }

    /** Stand einer Einreichung für API/MCP (Token sieht nur eigene) */
    public static function publicJson(array $row): array
    {
        $row = self::decode($row);
        $changes = [];
        foreach ((array) $row['diff'] as $d) {
            if ($d['type'] === 'blocks') {
                foreach ($d['items'] as $it) {
                    $changes[] = ['field' => $d['path'] . '.' . $it['id'], 'label' => $d['label'] . ' · ' . $it['label'], 'op' => $it['op'],
                        'fields' => array_map(fn($x) => ['field' => $x['path'], 'before' => self::short($x['before']), 'after' => self::short($x['after'])], $it['fields'])];
                }
            } else {
                $changes[] = ['field' => $d['path'], 'label' => $d['label'], 'before' => self::short($d['before']), 'after' => self::short($d['after'])];
            }
            if (count($changes) >= 50) break;
        }
        return [
            'id' => (int) $row['id'], 'status' => $row['status'] === 'pending' ? 'pending_review' : $row['status'],
            'action' => $row['action'], 'summary' => (string) $row['summary'], 'channel' => $row['channel'],
            'entity' => ['type' => $row['entity_type'], 'id' => $row['entity_id'], 'label' => $row['entity_label']],
            'submitted_at' => $row['created_at'], 'reviewed_at' => $row['reviewed_at'],
            'reason' => $row['status'] === 'rejected' ? (string) $row['reason'] : null,
            'note' => $row['status'] === 'applied' && (string) $row['reason'] !== '' ? (string) $row['reason'] : null,
            'url' => self::adminUrl((int) $row['id']), 'changes' => $changes,
        ];
    }

    private static function short(mixed $v): mixed
    {
        if (is_string($v) && mb_strlen($v) > 600) return mb_substr($v, 0, 600) . ' …';
        return $v;
    }

    // ================================================================= Freigabe

    /** CmsService im Namen der ursprünglichen Quelle (Token-Benutzer als Urheber) */
    private static function service(array $row): CmsService
    {
        $svc = new CmsService(['id' => $row['token_id'] !== null ? (int) $row['token_id'] : null, 'name' => (string) ($row['token_name'] ?: 'API'),
            'scope' => 'write', 'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null, 'review_mode' => 'direct']);
        $svc->origin((string) $row['channel'], $row['client'] ?: null, $row['feature'] ?: null);
        if ($row['lang']) $svc->useLang((string) $row['lang']);
        return $svc;
    }

    /** Hat sich der Gegenstand seit der Einreichung geändert? → aktueller Stand, sonst null */
    public static function conflict(array $row): ?array
    {
        $row = self::decode($row);
        $target = (array) $row['target'];
        if ($row['status'] !== 'pending' || $row['before'] === null) return null;   // Neuanlage: kein Konflikt möglich
        $current = Snapshot::take($target);
        if (Snapshot::hash($current) === $row['base_hash']) return null;
        $since = Diff::compute($target, $row['before'], $current);
        if ($current === null) return ['current' => null, 'diff' => $since];   // inzwischen gelöscht
        // Aktionen, die den ganzen Inhalt ersetzen, würden fremde Änderungen zurückdrehen → jede Änderung ist ein Konflikt
        // Ebenso Veröffentlichen: es würde auch fremde, hier nicht gezeigte Entwurfsänderungen online stellen
        if (in_array($row['action'], ['blocksReplace', 'restore', 'discard', 'designUpdate'], true)
            || self::publishFlag((string) $row['action'], array_values((array) ($row['payload']['args'] ?? [])))) return ['current' => $current, 'diff' => $since];
        // Nur Überschneidungen zählen: Felder bzw. Blöcke, die sowohl die Einreichung als auch jemand anderes geändert hat
        $overlap = array_intersect(self::touched($since), self::touched((array) $row['diff']));
        return $overlap ? ['current' => $current, 'diff' => array_values(array_filter($since, fn($d) => array_intersect(self::touched([$d]), $overlap)))] : null;
    }

    /** Geänderte Stellen eines Diffs: Feldpfade bzw. „blocks.{id}“ */
    private static function touched(array $diff): array
    {
        $out = [];
        foreach ($diff as $d) {
            if (($d['type'] ?? '') === 'blocks') foreach ((array) $d['items'] as $it) $out[] = $d['path'] . '.' . $it['id'];
            else $out[] = (string) ($d['path'] ?? '');
        }
        return $out;
    }

    /**
     * Einreichung übernehmen. $values: bearbeitete Feldwerte („Bearbeiten & übernehmen“), $draftOnly: Blöcke nur als Entwurf.
     * @return array{ok: bool, error?: string, conflict?: bool, target?: array}
     */
    public static function approve(int $id, int $reviewerId, array $values = [], bool $draftOnly = false, bool $force = false): array
    {
        $row = self::find($id);
        if (!$row || $row['status'] !== 'pending') return ['ok' => false, 'error' => __('Diese Einreichung ist nicht mehr offen.')];
        if (!$force && self::conflict($row)) return ['ok' => false, 'conflict' => true, 'error' => __('Der Inhalt wurde seit der Einreichung geändert. Bitte zuerst neu prüfen.')];
        $d = self::decode($row);
        $method = (string) ($d['payload']['method'] ?? '');
        $args = array_values((array) ($d['payload']['args'] ?? []));
        if (!in_array($method, self::METHODS, true)) return ['ok' => false, 'error' => __('Unbekannte Aktion.')];
        $edited = false;
        if ($values && isset(self::EDIT_ARG[$method]) && is_array($args[self::EDIT_ARG[$method]] ?? null)) {
            foreach ($values as $k => $v) {
                if (array_key_exists($k, $args[self::EDIT_ARG[$method]]) && is_string($args[self::EDIT_ARG[$method]][$k]) && $args[self::EDIT_ARG[$method]][$k] !== $v) {
                    $args[self::EDIT_ARG[$method]][$k] = (string) $v;
                    $edited = true;
                }
            }
        }
        if ($draftOnly && isset(self::PUBLISH_ARG[$method]) && !empty($args[self::PUBLISH_ARG[$method]])) {
            $args[self::PUBLISH_ARG[$method]] = false;
            $edited = true;
        }
        self::$last = null;
        try {
            $svc = self::service($row);
            $svc->applyingChange($id);
            $svc->{$method}(...$args);
        } catch (ApiError $e) {
            self::update($id, ['error' => mb_substr($e->getMessage(), 0, 1000)]);
            return ['ok' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            error_log('[review] approve #' . $id . ': ' . $e);
            self::update($id, ['error' => __('Interner Fehler beim Übernehmen.')]);
            return ['ok' => false, 'error' => __('Interner Fehler beim Übernehmen.')];
        }
        $last = self::$last ?? ['target' => $d['target'], 'before' => null, 'after' => null];
        self::update($id, [
            'status' => 'applied', 'reviewer_id' => $reviewerId, 'reviewed_at' => now(), 'error' => null,
            'reason' => $edited ? __('Vor dem Übernehmen bearbeitet.') : null,
            'payload_json' => self::json(['method' => $method, 'args' => $args]),
            'target_json' => self::json($last['target']), 'entity_id' => self::entityId($last['target']),
            'after_json' => self::json($last['after']), 'diff_json' => self::json(Diff::compute($last['target'], $last['before'], $last['after'])),
        ]);
        return ['ok' => true, 'target' => $last['target']];
    }

    public static function reject(int $id, int $reviewerId, string $reason): bool
    {
        $row = self::find($id);
        if (!$row || $row['status'] !== 'pending') return false;
        self::update($id, ['status' => 'rejected', 'reviewer_id' => $reviewerId, 'reviewed_at' => now(), 'reason' => mb_substr(trim(strip_tags($reason)), 0, 2000) ?: null]);
        return true;
    }

    /** Einreichung gegen den aktuellen Stand neu prüfen (Probelauf, neuer Diff, Konflikt aufgelöst). @return ?string Fehler */
    public static function recheck(int $id): ?string
    {
        $row = self::find($id);
        if (!$row || $row['status'] !== 'pending') return __('Diese Einreichung ist nicht mehr offen.');
        $d = self::decode($row);
        $method = (string) ($d['payload']['method'] ?? '');
        if (!in_array($method, self::METHODS, true)) return __('Unbekannte Aktion.');
        try {
            $svc = self::service($row);
            $svc->recheckChange($id);
            $svc->{$method}(...array_values((array) ($d['payload']['args'] ?? [])));
        } catch (Pending) {
            return null;
        } catch (ApiError $e) {
            self::update($id, ['error' => mb_substr($e->getMessage(), 0, 1000), 'checked_at' => now()]);
            return $e->getMessage();
        }
        return null;
    }

    // ================================================================= Benachrichtigung

    /** E-Mail an alle, die freigeben dürfen (Einstellung sys.review_notify) – höchstens alle 15 Minuten */
    private static function notify(): void
    {
        try {
            $s = app()->settings;
            if (!$s->get('sys.review_notify', false) || !self::enabled()) return;
            $last = (int) $s->get('sys.review_notified_at', 0);
            if ($last > time() - 900) return;
            $to = self::reviewerEmails();
            if (!$to) return;
            $s->set('sys.review_notified_at', time());
            $n = self::pendingCount();
            \Core\Mailer::send(__('{n} Änderungen warten auf Freigabe', ['n' => $n]) . ' · ' . site_name(),
                __('Über API, MCP oder KI wurden Änderungen eingereicht, die erst nach Ihrer Prüfung auf die Website gehen.') . "\n\n"
                . __('Offen: {n}', ['n' => $n]) . "\n" . absolute_url('/admin/ai/eingereicht') . "\n\n—\n"
                . __('Diese Nachricht kommt höchstens alle 15 Minuten. Abschalten: Verwaltung → Eingereicht → Einstellungen.'), $to);
        } catch (\Throwable $e) {
            error_log('[review] notify: ' . $e->getMessage());
        }
    }

    /** E-Mail-Adressen aller Konten dieser Website mit Recht review.manage (ohne Netzwerk-Konten) */
    public static function reviewerEmails(): array
    {
        $out = [];
        $roles = \Core\Permissions::roles();
        foreach (app()->db->fetchAll('SELECT * FROM users') as $u) {
            if (!empty($u['network_uid']) || ($u['role'] ?? '') === 'network' || !empty($u['locked_at'] ?? null)) continue;
            if (\Core\Permissions::allows($roles[$u['role']] ?? null, 'review.manage') && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) $out[] = $u['email'];
        }
        return array_values(array_unique($out));
    }
}
