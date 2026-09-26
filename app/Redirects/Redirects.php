<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Redirects;

use Core\Database;
use Core\Features;
use Core\Http\Request;
use Core\Http\Response;
use Core\Lang;
use Core\Links;
use Core\Pages;

/**
 * Weiterleitungen je Website (Funktion „redirects“, Recht redirects.manage).
 *
 * Tabelle „redirects“: Quelle (Pfad ohne Domain, optional mit ?query, optional mit * am Ende als Präfix) → Ziel
 * (page:ID[#anker], entry:tabelle:id, media:id, /pfad oder https://…), Code 301/302/410, Notiz, Treffer.
 * Tabelle „redirect_404“: die letzten MAX_404 verschiedenen Pfade, die mit 404 endeten (Pfad, Anzahl, Zeiten – keine IP).
 *
 * Regeln:
 *  - Aufgelöst wird nur, wenn keine Seite und keine Route passt (App::handle fängt die 404 ab) – echte Seiten gehen immer vor.
 *  - Vergleich: führender Schrägstrich, ohne Domain, Schrägstrich am Ende egal, ASCII-Groß/Kleinschreibung egal,
 *    Prozent-Kodierung egal (/agentur/m%C3%BCntel/ = /agentur/müntel).
 *  - Exakte Quelle vor Platzhalter; bei mehreren Platzhaltern gewinnt der längste Präfix. „*“ im Ziel übernimmt den Rest.
 *  - Die Query der Anfrage bleibt erhalten (außer die Quelle nennt selbst eine Query). Keine Ketten: höchstens ein Sprung.
 *  - page:ID folgt umbenannten und verschobenen Seiten automatisch (Pages::url).
 *  - Umbenennen/Verschieben veröffentlichter Seiten legt „alter Pfad → page:ID“ an (Pages::rebuildPaths → pathsChanged).
 */
final class Redirects
{
    public const CODES = [301, 302, 410];
    /** Anzahl verschiedener 404-Pfade im Protokoll */
    public const MAX_404 = 200;
    /** Einstellungen der Website */
    public const SET_AUTO = 'sys.redirects_auto';
    public const SET_LOG = 'sys.redirects_log404';
    /** Anfragen, die nie ins 404-Protokoll kommen (Angriffs-Scans, Dateien des Systems) */
    private const NO_LOG = '~^/(wp-|wordpress|xmlrpc|\.env|\.git|\.well-known|cgi-bin|phpmyadmin|pma|vendor/|assets/|themes/|apple-touch-icon|browserconfig\.xml)|\.(map|php\d?|asp|aspx|jsp|cgi|sql|bak|zip|gz|ini|log)$~i';

    public static function enabled(): bool
    {
        return Features::on('redirects', false);
    }

    public static function ensureTable(Database $db): void
    {
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->query("CREATE TABLE IF NOT EXISTS redirects (id $pk, source TEXT NOT NULL, source_hash VARCHAR(40) NOT NULL,
            wildcard INT NOT NULL DEFAULT 0, target TEXT NOT NULL, code INT NOT NULL DEFAULT 301, note VARCHAR(191) NULL,
            origin VARCHAR(10) NOT NULL DEFAULT 'manual', hits INT NOT NULL DEFAULT 0, last_hit VARCHAR(25) NULL,
            active INT NOT NULL DEFAULT 1, created_at VARCHAR(25), updated_at VARCHAR(25))$tail");
        $db->query("CREATE TABLE IF NOT EXISTS redirect_404 (id $pk, path TEXT NOT NULL, path_hash VARCHAR(40) NOT NULL,
            hits INT NOT NULL DEFAULT 1, internal INT NOT NULL DEFAULT 0, first_seen VARCHAR(25), last_seen VARCHAR(25))$tail");
        if ($my) {
            try { $db->query('CREATE INDEX redirects_hash ON redirects (source_hash)'); } catch (\Throwable) {}
            try { $db->query('CREATE INDEX redirects_wild ON redirects (wildcard, active)'); } catch (\Throwable) {}
            try { $db->query('CREATE UNIQUE INDEX redirect_404_hash ON redirect_404 (path_hash)'); } catch (\Throwable) {}
        } else {
            $db->query('CREATE INDEX IF NOT EXISTS redirects_hash ON redirects (source_hash)');
            $db->query('CREATE INDEX IF NOT EXISTS redirects_wild ON redirects (wildcard, active)');
            $db->query('CREATE UNIQUE INDEX IF NOT EXISTS redirect_404_hash ON redirect_404 (path_hash)');
        }
    }

    private static function db(): Database
    {
        return app()->db;
    }

    // ================================================================== Normalisieren & Vergleichen (ohne Datenbank)

    /**
     * Quelle bzw. Pfad vereinheitlichen: Domain weg, Fragment weg, Prozent-Kodierung aufgelöst (UTF-8), führender
     * Schrägstrich, doppelte Schrägstriche zusammengefasst. Schrägstrich am Ende und Schreibweise bleiben (Anzeige).
     * Query bleibt erhalten (als „?…“). Leer bei unbrauchbarer Eingabe.
     */
    public static function normalize(string $in): string
    {
        $s = trim($in);
        if ($s === '') return '';
        if (preg_match('~^(https?:)?//~i', $s)) {
            $p = parse_url(preg_match('~^//~', $s) ? 'http:' . $s : $s);
            if (!$p) return '';
            $s = ($p['path'] ?? '/') . (isset($p['query']) && $p['query'] !== '' ? '?' . $p['query'] : '');
        }
        $s = explode('#', $s, 2)[0];
        [$path, $query] = array_pad(explode('?', $s, 2), 2, null);
        $dec = rawurldecode((string) $path);
        $path = mb_check_encoding($dec, 'UTF-8') ? $dec : (string) $path;
        $path = (string) preg_replace('~[\x00-\x1F\x7F]~', '', $path);
        $path = '/' . ltrim((string) preg_replace('~/{2,}~', '/', $path), '/');
        if (class_exists(\Normalizer::class)) $path = \Normalizer::normalize($path, \Normalizer::FORM_C) ?: $path;
        $query = $query !== null ? trim($query) : '';
        return $path . ($query !== '' ? '?' . $query : '');
    }

    /** Vergleichsschlüssel: ohne Schrägstrich am Ende (außer „/“), ASCII-Kleinbuchstaben (Umlaute bleiben) */
    public static function key(string $normalized): string
    {
        [$path, $query] = array_pad(explode('?', $normalized, 2), 2, '');
        if ($path !== '/') $path = rtrim($path, '/');
        return strtolower($path === '' ? '/' : $path) . ($query !== '' ? '?' . strtolower($query) : '');
    }

    public static function hash(string $key): string
    {
        return sha1($key);
    }

    /** Platzhalter-Quelle („/alt/*“)? */
    public static function isWildcard(string $source): bool
    {
        return !str_contains($source, '?') && str_ends_with($source, '*');
    }

    /**
     * Passende Regel aus Kandidaten wählen (reine Funktion – auch für den Selbsttest).
     * $rules: Zeilen mit source, wildcard, active. Rückgabe: [regel, rest] oder null. Rest = von „*“ erfasster Teil.
     * $path ist bereits normalisiert, $query ohne „?“.
     */
    public static function pick(array $rules, string $path, string $query = ''): ?array
    {
        $pathOnly = explode('?', $path, 2)[0];
        $trail = $pathOnly !== '/' && str_ends_with($pathOnly, '/');
        $base = $pathOnly === '/' ? '/' : rtrim($pathOnly, '/');
        $keys = $query !== '' ? [self::key($base . '?' . $query), self::key($base)] : [self::key($base)];
        foreach ($keys as $k) {
            foreach ($rules as $r) {
                if (!(int) ($r['active'] ?? 1) || (int) ($r['wildcard'] ?? 0)) continue;
                if (self::key(self::normalize((string) $r['source'])) === $k) return [$r, ''];
            }
        }
        // Platzhalter: längster Präfix gewinnt; Vergleich in Kleinbuchstaben (ASCII, gleiche Byte-Länge → Rest aus dem Original)
        $best = null;
        $bestLen = -1;
        $hay = strtolower($base) . '/';
        foreach ($rules as $r) {
            if (!(int) ($r['active'] ?? 1) || !(int) ($r['wildcard'] ?? 0)) continue;
            $prefix = strtolower(substr(self::normalize((string) $r['source']), 0, -1));
            if ($prefix === '' || !str_starts_with($hay, $prefix) || strlen($prefix) <= $bestLen) continue;
            $best = $r;
            $bestLen = strlen($prefix);
        }
        if ($best === null) return null;
        $rest = rtrim(substr($base . '/', $bestLen), '/');
        if ($rest !== '' && $trail) $rest .= '/';
        return [$best, $rest];
    }

    /** Query an eine Adresse hängen (vor einem #anker; vorhandene Query wird mit & ergänzt) */
    public static function appendQuery(string $url, string $query): string
    {
        if ($query === '') return $url;
        [$u, $frag] = array_pad(explode('#', $url, 2), 2, null);
        return $u . (str_contains($u, '?') ? '&' : '?') . $query . ($frag !== null ? '#' . $frag : '');
    }

    /** Location-Header: Zeichen außerhalb von ASCII (Umlaute, Leerzeichen) prozent-kodieren */
    public static function encodeLocation(string $url): string
    {
        return (string) preg_replace_callback('~[^\x21-\x7E]+~', fn($m) => rawurlencode($m[0]), $url);
    }

    /** Art eines Ziels: ref (page:/entry:/media:), url (https://…), path (/…) oder '' (ungültig/leer) */
    public static function targetKind(string $target): string
    {
        $t = trim($target);
        if ($t === '') return '';
        if (Links::isRef($t)) return 'ref';
        if (preg_match('~^https?://[^\s/$.?#][^\s]*$~i', $t)) return 'url';
        if (str_starts_with($t, '/') && !str_starts_with($t, '//')) return 'path';
        return '';
    }

    // ================================================================== Auflösen (Website)

    /**
     * 404-Weg der Website: passende Weiterleitung als Antwort, sonst null (dann 404 wie bisher; der Pfad kommt ins Protokoll).
     * Nur GET/HEAD, nie für /admin, /api, /mcp.
     */
    public static function handle404(Request $r): ?Response
    {
        if (!in_array($r->method, ['GET', 'HEAD'], true) || $r->isAdminPath() || str_starts_with($r->path, '/api/') || $r->path === '/mcp') return null;
        try {
            if (!self::enabled()) return null;
            $uri = (string) ($r->server['REQUEST_URI'] ?? $r->path);
            $query = (string) parse_url($uri, PHP_URL_QUERY);
            $path = self::normalize($r->path);
            // Schrägstrich am Ende der Anfrage kennt $r->path nicht mehr – aus der Original-Adresse übernehmen
            $rawPath = (string) parse_url($uri, PHP_URL_PATH);
            if ($path !== '/' && str_ends_with($rawPath, '/')) $path .= '/';
            $hit = self::match($path, $query);
            if ($hit) {
                self::countHit((int) $hit['rule']['id']);
                if ($hit['code'] === 410) return self::gone();
                return Response::redirect(self::encodeLocation($hit['location']), $hit['code'])
                    ->header('Cache-Control', $hit['code'] === 301 ? 'public, max-age=3600' : 'no-cache')->header('X-Redirect-By', CMS_NAME);
            }
            self::log404($r, $path);
        } catch (\Throwable $e) {
            error_log('[redirects] ' . $e->getMessage());
        }
        return null;
    }

    private static function gone(): Response
    {
        return (new \Core\Http\Controllers\SiteController())->error(410);
    }

    /**
     * Regel und Ziel zu einem Pfad: ['rule' => …, 'code' => 301|302|410, 'location' => …, 'rest' => …] oder null
     * (keine Regel, Ziel fehlt/unveröffentlicht oder Schleife).
     */
    public static function match(string $path, string $query = ''): ?array
    {
        $path = self::normalize($path);
        if ($path === '') return null;
        $base = explode('?', $path, 2)[0];
        $trimmed = $base === '/' ? '/' : rtrim($base, '/');
        $hashes = [self::hash(self::key($trimmed))];
        if ($query !== '') array_unshift($hashes, self::hash(self::key($trimmed . '?' . $query)));
        $in = implode(',', array_fill(0, count($hashes), '?'));
        $rules = self::db()->fetchAll("SELECT * FROM redirects WHERE active = 1 AND (source_hash IN ($in) OR wildcard = 1) ORDER BY id", $hashes);
        $picked = self::pick($rules, $base, $query);
        if (!$picked) return null;
        [$rule, $rest] = $picked;
        $code = in_array((int) $rule['code'], self::CODES, true) ? (int) $rule['code'] : 301;
        if ($code === 410) return ['rule' => $rule, 'code' => 410, 'location' => '', 'rest' => $rest];
        $loc = self::targetUrl((string) $rule['target'], $rest);
        if ($loc === null) return null;
        // Quelle mit eigener Query: die Query ist „verbraucht“; sonst übernehmen
        if (!str_contains((string) $rule['source'], '?')) $loc = self::appendQuery($loc, $query);
        // Schleife: Ziel = Quelle (gleiche Website, gleicher Pfad) → keine Weiterleitung
        $locPath = preg_match('~^https?://~i', $loc) ? (self::sameSite($loc) ? (string) parse_url($loc, PHP_URL_PATH) : '') : explode('?', explode('#', $loc, 2)[0], 2)[0];
        if ($locPath !== '' && self::key(self::stripBase(self::normalize($locPath))) === self::key($trimmed)) return null;
        return ['rule' => $rule, 'code' => $code, 'location' => $loc, 'rest' => $rest];
    }

    /** Zieladresse (relativ oder absolut) oder null, wenn das Ziel fehlt bzw. unveröffentlicht ist */
    public static function targetUrl(string $target, string $rest = ''): ?string
    {
        $t = trim($target);
        switch (self::targetKind($t)) {
            case 'ref':
                if (preg_match('~^page:(\d+)~', $t, $m)) {
                    $p = Pages::find((int) $m[1]);
                    if (!$p || $p['status'] !== 'published' || $p['type'] !== 'page') return null;
                }
                return Links::href($t);
            case 'url':
                return str_replace('*', $rest, $t);
            case 'path':
                return url(str_replace('*', $rest, $t));
        }
        return null;
    }

    private static function sameSite(string $abs): bool
    {
        $h = strtolower((string) parse_url($abs, PHP_URL_HOST));
        return $h !== '' && ($h === strtolower((string) parse_url(site_url(), PHP_URL_HOST)) || (app()->request && $h === strtolower((string) preg_replace('~:\d+$~', '', app()->request->host()))));
    }

    /** Installations-Unterordner (base_path) vorne entfernen */
    private static function stripBase(string $path): string
    {
        $b = base_path();
        return $b !== '' && str_starts_with($path, $b . '/') ? substr($path, strlen($b)) : $path;
    }

    private static function countHit(int $id): void
    {
        try {
            self::db()->query('UPDATE redirects SET hits = hits + 1, last_hit = ? WHERE id = ?', [now(), $id]);
        } catch (\Throwable) {
            // Zählen ist nie wichtiger als die Weiterleitung selbst (z. B. Datenbank kurz gesperrt)
        }
    }

    // ================================================================== 404-Protokoll (ohne IP, ohne Referrer-Adresse)

    public static function logEnabled(): bool
    {
        return (bool) app()->settings->get(self::SET_LOG, true);
    }

    private static function log404(Request $r, string $path): void
    {
        if (!self::logEnabled() || strlen($path) > 600 || preg_match(self::NO_LOG, explode('?', $path, 2)[0])) return;
        $base = explode('?', $path, 2)[0];
        $h = self::hash(self::key($base));
        // Interner Link: Verweis kam von dieser Website (nur Ja/Nein wird gespeichert)
        $ref = strtolower((string) parse_url((string) ($r->server['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
        $internal = $ref !== '' && $ref === strtolower((string) preg_replace('~:\d+$~', '', $r->host())) ? 1 : 0;
        $db = self::db();
        $db->query('UPDATE redirect_404 SET hits = hits + 1, last_seen = ?' . ($internal ? ', internal = 1' : '') . ' WHERE path_hash = ?', [now(), $h]);
        if ((int) $db->fetchValue('SELECT COUNT(*) FROM redirect_404 WHERE path_hash = ?', [$h]) > 0) return;
        try {
            $db->insert('redirect_404', ['path' => $base, 'path_hash' => $h, 'hits' => 1, 'internal' => $internal, 'first_seen' => now(), 'last_seen' => now()]);
        } catch (\Throwable) {
            return;   // gleichzeitig eingetragen (UNIQUE)
        }
        $n = (int) $db->fetchValue('SELECT COUNT(*) FROM redirect_404');
        if ($n > self::MAX_404 + 20) {
            $keep = $db->fetchAll('SELECT id FROM redirect_404 ORDER BY last_seen DESC, id DESC LIMIT ' . self::MAX_404);
            $ids = array_map('intval', array_column($keep, 'id'));
            if ($ids) $db->query('DELETE FROM redirect_404 WHERE id NOT IN (' . implode(',', $ids) . ')');
        }
    }

    /** 404-Protokoll: häufigste zuerst, optional Suche */
    public static function notFound(string $q = '', int $limit = self::MAX_404): array
    {
        $sql = 'SELECT * FROM redirect_404';
        $p = [];
        if ($q !== '') { $sql .= ' WHERE path LIKE ?'; $p[] = '%' . $q . '%'; }
        return self::db()->fetchAll($sql . ' ORDER BY hits DESC, last_seen DESC LIMIT ' . max(1, $limit), $p);
    }

    public static function notFoundCount(): int
    {
        try {
            return (int) self::db()->fetchValue('SELECT COUNT(*) FROM redirect_404');
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function notFoundDelete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        self::db()->query('DELETE FROM redirect_404 WHERE id IN (' . implode(',', $ids) . ')');
        return count($ids);
    }

    public static function notFoundClear(): void
    {
        self::db()->query('DELETE FROM redirect_404');
    }

    /** Vorschlag für einen 404-Pfad: Seite mit gleichem letzten Adressteil (Slug) – ['target' => 'page:ID', 'page' => …] oder null */
    public static function suggest(string $path): ?array
    {
        $segs = array_values(array_filter(explode('/', explode('?', $path, 2)[0]), fn($s) => $s !== ''));
        for ($i = count($segs) - 1; $i >= 0 && $i >= count($segs) - 2; $i--) {
            $slug = Pages::slugify(preg_replace('~\.(html?|php)$~i', '', $segs[$i]) ?? '');
            if ($slug === 'seite' || strlen($slug) < 3) continue;
            $p = self::db()->fetch("SELECT * FROM pages WHERE slug = ? AND type = 'page' AND status = 'published' ORDER BY (parent_id IS NULL) DESC, id LIMIT 1", [$slug]);
            if ($p) return ['target' => 'page:' . $p['id'], 'page' => $p];
        }
        return null;
    }

    // ================================================================== Verwaltung

    public static function find(int $id): ?array
    {
        return self::db()->fetch('SELECT * FROM redirects WHERE id = ?', [$id]);
    }

    /** Regel mit genau dieser Quelle (Vergleichsschlüssel) */
    public static function bySource(string $source): ?array
    {
        $n = self::normalize($source);
        if ($n === '') return null;
        return self::db()->fetch('SELECT * FROM redirects WHERE source_hash = ? ORDER BY id LIMIT 1', [self::hash(self::key($n))]);
    }

    /** Liste mit Suche und Filter: ['rows', 'total', 'page', 'pages'] */
    public static function list(array $f = []): array
    {
        $where = [];
        $p = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(source LIKE ? OR target LIKE ? OR note LIKE ?)';
            array_push($p, "%$q%", "%$q%", "%$q%");
        }
        if (in_array((int) ($f['code'] ?? 0), self::CODES, true)) { $where[] = 'code = ?'; $p[] = (int) $f['code']; }
        if (in_array($f['origin'] ?? '', ['manual', 'auto', 'import'], true)) { $where[] = 'origin = ?'; $p[] = $f['origin']; }
        $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $total = (int) self::db()->fetchValue('SELECT COUNT(*) FROM redirects' . $sql, $p);
        $per = max(10, min(500, (int) ($f['per'] ?? 50)));
        $pages = max(1, (int) ceil($total / $per));
        $page = max(1, min($pages, (int) ($f['page'] ?? 1)));
        $order = match ($f['sort'] ?? '') { 'hits' => 'hits DESC, id DESC', 'source' => 'source', 'last' => 'last_hit DESC, id DESC', default => 'id DESC' };
        $rows = self::db()->fetchAll('SELECT * FROM redirects' . $sql . " ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public static function all(): array
    {
        return self::db()->fetchAll('SELECT * FROM redirects ORDER BY id');
    }

    public static function count(): int
    {
        try {
            return (int) self::db()->fetchValue('SELECT COUNT(*) FROM redirects');
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Eingaben prüfen und vereinheitlichen: [werte, fehler, hinweise].
     * Fehler verhindern das Speichern; Hinweise (Seite vorhanden, Kette) nicht.
     */
    public static function validate(array $in, ?int $ownId = null): array
    {
        $errors = [];
        $notes = [];
        $source = self::normalize((string) ($in['source'] ?? ''));
        $code = (int) ($in['code'] ?? 301);
        if (!in_array($code, self::CODES, true)) $code = 301;
        $target = trim((string) ($in['target'] ?? ''));
        if ($source === '' || $source === '/' || $source === '/*') {
            $errors['source'] = __('Bitte eine alte Adresse angeben, z. B. /alte-seite/ – die Startseite selbst kann nicht weitergeleitet werden.');
        } elseif (str_contains(substr($source, 0, -1), '*')) {
            $errors['source'] = __('Ein * ist nur am Ende der Adresse erlaubt (alles, was so beginnt).');
        } elseif (preg_match('~^/(admin|api)(/|$)|^/mcp$~i', $source)) {
            $errors['source'] = __('Adressen der Verwaltung und der Schnittstellen können nicht weitergeleitet werden.');
        } elseif (($dupe = self::bySource($source)) && (int) $dupe['id'] !== (int) $ownId) {
            $errors['source'] = __('Für diese Adresse gibt es schon eine Weiterleitung (#{id}).', ['id' => $dupe['id']]);
        }
        if ($code === 410) {
            $target = '';
        } elseif ($target === '') {
            $errors['target'] = __('Bitte ein Ziel wählen: Seite, Pfad wie /neue-seite/ oder vollständige Adresse (https://…).');
        } else {
            if (!preg_match('~^[a-z]+:|^/~i', $target) && !str_contains($target, ' ')) $target = '/' . $target;
            $kind = self::targetKind($target);
            if ($kind === '') {
                $errors['target'] = __('Ungültiges Ziel. Erlaubt: Seite (page:ID), Pfad wie /neue-seite/ oder https://…');
            } elseif ($kind === 'ref' && Links::href($target) === null) {
                $errors['target'] = __('Das gewählte Ziel gibt es nicht mehr.');
            } elseif (str_contains($target, '*') && !self::isWildcard($source)) {
                $errors['target'] = __('Ein * im Ziel ist nur zusammen mit einem * am Ende der alten Adresse möglich.');
            } elseif ($kind === 'path' && self::isWildcard($source) && str_starts_with(strtolower(self::normalize($target)) . '/', strtolower(substr($source, 0, -1)))) {
                $errors['target'] = __('Das Ziel beginnt wie die alte Adresse – die Weiterleitung würde sich immer wieder selbst treffen.');
            } elseif ($kind === 'path' && !str_contains($target, '*') && self::key(explode('?', self::normalize($target), 2)[0]) === self::key(explode('?', $source, 2)[0])) {
                $errors['target'] = __('Ziel und alte Adresse sind gleich – das wäre eine Endlosschleife.');
            } elseif ($kind === 'ref' && preg_match('~^page:(\d+)~', $target, $m) && ($pp = Pages::find((int) $m[1])) && !self::isWildcard($source)
                && self::key(explode('?', self::stripBase(Pages::plainUrl($pp)), 2)[0]) === self::key(explode('?', $source, 2)[0])) {
                $errors['target'] = __('Ziel und alte Adresse sind gleich – das wäre eine Endlosschleife.');
            }
        }
        if (!$errors) {
            if (!self::isWildcard($source) && ($p = self::pageAt($source))) {
                $notes[] = __('Unter „{path}“ gibt es die Seite „{title}“. Die Weiterleitung greift erst, wenn die Seite dort nicht mehr erreichbar ist.', ['path' => $source, 'title' => $p['title']]);
            }
            if ($code !== 410 && self::targetKind($target) === 'path' && !self::pageAt($target) && ($chain = self::bySource($target)) && (int) $chain['id'] !== (int) $ownId && (int) $chain['active']) {
                $notes[] = __('Das Ziel wird selbst weitergeleitet (#{id}). Besucher werden nicht zweimal weitergeleitet – besser gleich das endgültige Ziel eintragen.', ['id' => $chain['id']]);
            }
        }
        $values = ['source' => $source, 'target' => $target, 'code' => $code, 'note' => mb_substr(trim(strip_tags((string) ($in['note'] ?? ''))), 0, 190),
            'active' => array_key_exists('active', $in) ? (!empty($in['active']) ? 1 : 0) : 1];
        return [$values, $errors, $notes];
    }

    /** Speichern (neu oder ändern): [id, fehler, hinweise] */
    public static function save(array $in, ?int $id = null, string $origin = 'manual'): array
    {
        [$v, $errors, $notes] = self::validate($in, $id);
        if ($errors) return [0, $errors, $notes];
        $row = ['source' => $v['source'], 'source_hash' => self::hash(self::key($v['source'])), 'wildcard' => self::isWildcard($v['source']) ? 1 : 0,
            'target' => $v['target'], 'code' => $v['code'], 'note' => $v['note'] !== '' ? $v['note'] : null, 'active' => $v['active'], 'updated_at' => now()];
        if ($id) {
            self::db()->update('redirects', $row, 'id = :id', ['id' => $id]);
        } else {
            $id = self::db()->insert('redirects', $row + ['origin' => $origin, 'hits' => 0, 'created_at' => now()]);
        }
        // Aus dem 404-Protokoll entfernen – dafür gibt es jetzt eine Regel
        if (!$row['wildcard']) self::db()->query('DELETE FROM redirect_404 WHERE path_hash = ?', [self::hash(self::key(explode('?', $v['source'], 2)[0]))]);
        return [$id, [], $notes];
    }

    public static function delete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        self::db()->query('DELETE FROM redirects WHERE id IN (' . implode(',', $ids) . ')');
        return count($ids);
    }

    public static function setActive(array $ids, bool $on): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) return 0;
        self::db()->query('UPDATE redirects SET active = ?, updated_at = ? WHERE id IN (' . implode(',', $ids) . ')', [$on ? 1 : 0, now()]);
        return count($ids);
    }

    /** Veröffentlichte Seite unter einem Pfad (mit Sprachpräfix; nur Seiten, keine Detailseiten) */
    public static function pageAt(string $path): ?array
    {
        $path = trim(self::stripBase(explode('?', self::normalize($path), 2)[0]), '/');
        $lang = null;
        $first = explode('/', $path)[0];
        if (Lang::multi() && $first !== Lang::default() && Lang::valid($first)) {
            $lang = $first;
            $path = trim(substr($path, strlen($first)), '/');
        }
        if ($path === '') return Pages::home($lang);
        $p = self::db()->fetch("SELECT * FROM pages WHERE LOWER(path) = ? AND type = 'page' AND status = 'published' AND " . Lang::sql(), [strtolower($path), Lang::norm($lang)]);
        return $p ?: null;
    }

    /**
     * Wohin führt eine Adresse? Für „Adresse testen“ und redirects:test:
     * ['path', 'status' => 'page'|'redirect'|'gone'|'broken'|'none', 'page', 'rule', 'code', 'location', 'text'].
     */
    public static function explain(string $input): array
    {
        $n = self::normalize($input);
        $out = ['path' => $n, 'status' => 'none', 'page' => null, 'rule' => null, 'code' => 404, 'location' => null];
        if ($n === '') return $out + ['text' => __('Bitte eine Adresse eingeben.')];
        [$path, $query] = array_pad(explode('?', $n, 2), 2, '');
        if ($p = self::pageAt($path)) {
            return ['status' => 'page', 'page' => $p, 'code' => 200, 'location' => Pages::plainUrl($p),
                'text' => __('Die Adresse gehört zur Seite „{title}“ – Weiterleitungen greifen hier nicht.', ['title' => $p['title']])] + $out;
        }
        $m = self::match($path, $query);
        if ($m) {
            if ($m['code'] === 410) {
                return ['status' => 'gone', 'rule' => $m['rule'], 'code' => 410, 'text' => __('410 – als dauerhaft entfernt gemeldet (Regel #{id}).', ['id' => $m['rule']['id']])] + $out;
            }
            return ['status' => 'redirect', 'rule' => $m['rule'], 'code' => $m['code'], 'location' => $m['location'],
                'text' => __('{code} → {to} (Regel #{id})', ['code' => $m['code'], 'to' => $m['location'], 'id' => $m['rule']['id']])] + $out;
        }
        // Regel vorhanden, aber Ziel fehlt/unveröffentlicht oder Schleife
        $rules = self::db()->fetchAll('SELECT * FROM redirects WHERE active = 1 AND (source_hash = ? OR wildcard = 1)', [self::hash(self::key($path === '/' ? '/' : rtrim($path, '/')))]);
        if ($picked = self::pick($rules, $path, $query)) {
            return ['status' => 'broken', 'rule' => $picked[0], 'text' => __('Regel #{id} passt, ihr Ziel ist aber nicht erreichbar (gelöscht, unveröffentlicht oder gleich der alten Adresse) – Besucher sehen 404.', ['id' => $picked[0]['id']])] + $out;
        }
        return $out + ['text' => __('Keine Seite und keine Weiterleitung – Besucher sehen „Seite nicht gefunden“ (404).')];
    }

    /** Anzeige eines Ziels: ['label', 'href', 'missing'] */
    public static function describeTarget(array $row): array
    {
        if ((int) $row['code'] === 410) return ['label' => __('410 – entfernt'), 'href' => '', 'missing' => false];
        $t = (string) $row['target'];
        if (self::targetKind($t) === 'ref') {
            try {
                $d = Links::describe($t);
            } catch (\Throwable) {
                $d = ['label' => $t, 'href' => '', 'missing' => true];
            }
            if (preg_match('~^page:(\d+)~', $t, $m) && ($p = Pages::find((int) $m[1])) && $p['status'] !== 'published') {
                return ['label' => (string) $d['label'], 'href' => (string) $d['href'], 'missing' => true];
            }
            return ['label' => (string) $d['label'], 'href' => (string) $d['href'], 'missing' => (bool) $d['missing']];
        }
        return ['label' => $t, 'href' => $t, 'missing' => false];
    }

    // ================================================================== Automatisch: Seite umbenannt/verschoben

    /**
     * Von Pages::rebuildPaths(): $changes = [['id', 'old', 'new', 'lang', 'type', 'published'], …] (Pfade ohne Sprachpräfix).
     * Alte Adresse veröffentlichter Seiten → page:ID; Weiterleitungen, deren Quelle wieder eine veröffentlichte Seite ist, entfallen.
     */
    public static function pathsChanged(array $changes): void
    {
        if (!$changes || !self::enabled()) return;
        $db = self::db();
        $auto = (bool) app()->settings->get(self::SET_AUTO, true);
        foreach ($changes as $c) {
            if (($c['type'] ?? 'page') !== 'page') continue;
            $prefix = Lang::prefix($c['lang'] ?? null);
            // Neue Adresse ist eine echte Seite → Weiterleitung von dort entfernen (nur exakte Quellen)
            if ($c['new'] !== null && !empty($c['published'])) {
                $new = self::normalize($prefix . '/' . $c['new']);
                $db->query('DELETE FROM redirects WHERE source_hash = ? AND wildcard = 0', [self::hash(self::key($new))]);
            }
            if (!$auto || empty($c['published']) || $c['old'] === null || $c['old'] === '' || $c['new'] === null || $c['old'] === $c['new']) continue;
            $old = self::normalize($prefix . '/' . $c['old']);
            $row = self::bySource($old);
            $target = 'page:' . (int) $c['id'];
            if ($row) {
                // Vorhandene Regel dieser Adresse übernimmt das neue Ziel (auch nach mehrfachem Umbenennen keine Kette)
                if ($row['target'] !== $target || (int) $row['code'] !== 301 || !(int) $row['active']) {
                    $db->update('redirects', ['target' => $target, 'code' => 301, 'active' => 1, 'updated_at' => now()], 'id = :id', ['id' => (int) $row['id']]);
                }
                continue;
            }
            $db->insert('redirects', ['source' => $old, 'source_hash' => self::hash(self::key($old)), 'wildcard' => 0, 'target' => $target,
                'code' => 301, 'note' => mb_substr(__('Automatisch: Adresse geändert zu {path}', ['path' => $prefix . '/' . $c['new']]), 0, 190),
                'origin' => 'auto', 'hits' => 0, 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    // ================================================================== Import & Export

    /**
     * Zeilen aus CSV (quelle;ziel;code;notiz – Trenner ; , oder Tab, Kopfzeile optional) oder JSON
     * ([{"from","to"}], [{"source","target","code","note"}], {"/alt": "/neu"} oder {"redirects": […]}).
     * @return list<array{source:string,target:string,code:int|string,note:string,line:int}>
     */
    public static function parse(string $text, string $name = ''): array
    {
        $text = (string) preg_replace('~^\xEF\xBB\xBF~', '', $text);
        $trim = ltrim($text);
        if (str_ends_with(strtolower($name), '.json') || str_starts_with($trim, '[') || str_starts_with($trim, '{')) {
            $d = json_decode($text, true);
            if (!is_array($d)) throw new \RuntimeException(__('Die JSON-Datei ist ungültig: {msg}', ['msg' => json_last_error_msg()]));
            if (isset($d['redirects']) && is_array($d['redirects'])) $d = $d['redirects'];
            $out = [];
            $i = 0;
            foreach ($d as $k => $v) {
                $i++;
                if (is_string($v) && is_string($k)) { $out[] = ['source' => $k, 'target' => $v, 'code' => 301, 'note' => '', 'line' => $i]; continue; }
                if (!is_array($v)) continue;
                $out[] = ['source' => (string) ($v['from'] ?? $v['source'] ?? $v['old'] ?? ''), 'target' => (string) ($v['to'] ?? $v['target'] ?? $v['new'] ?? ''),
                    'code' => $v['code'] ?? $v['status'] ?? 301, 'note' => (string) ($v['note'] ?? ''), 'line' => $i];
            }
            return $out;
        }
        $lines = preg_split('~\r\n|\r|\n~', $text) ?: [];
        $first = '';
        foreach ($lines as $l) if (trim($l) !== '') { $first = $l; break; }
        $sep = substr_count($first, ';') ? ';' : (substr_count($first, "\t") ? "\t" : ',');
        $out = [];
        $head = true;
        foreach ($lines as $n => $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) continue;
            $c = str_getcsv($line, $sep, '"', '');
            $src = trim((string) ($c[0] ?? ''));
            // Kopfzeile (optional): erste Zeile mit „source“, „quelle“, „from“ …
            if ($head && ($head = false) === false && preg_match('~^(source|quelle|from|von|alt|old)$~i', $src)) continue;
            $out[] = ['source' => $src, 'target' => trim((string) ($c[1] ?? '')), 'code' => trim((string) ($c[2] ?? '')) ?: 301, 'note' => trim((string) ($c[3] ?? '')), 'line' => $n + 1];
        }
        return $out;
    }

    /**
     * Importieren: ['created', 'updated', 'skipped', 'errors' => [zeile => text], 'linked'].
     * $o: dry (nur prüfen), overwrite (vorhandene Quelle: Ziel ersetzen), link (interne Pfade mit Seite → page:ID).
     */
    public static function import(array $rows, array $o = []): array
    {
        $dry = !empty($o['dry']);
        $overwrite = !empty($o['overwrite']);
        $link = $o['link'] ?? true;
        $res = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'linked' => 0, 'errors' => [], 'total' => count($rows)];
        $seen = [];
        $run = function () use ($rows, $dry, $overwrite, $link, &$res, &$seen) {
            foreach ($rows as $r) {
                $line = (int) ($r['line'] ?? 0);
                $code = (int) $r['code'];
                $in = ['source' => $r['source'], 'target' => $r['target'], 'code' => $code ?: 301, 'note' => $r['note'] ?? ''];
                if ($link && $in['target'] !== '' && ($ref = self::pageRef($in['target']))) {
                    $in['target'] = $ref;
                    $res['linked']++;
                }
                $n = self::normalize((string) $in['source']);
                $k = $n !== '' ? self::key($n) : '';
                if ($k !== '' && isset($seen[$k])) { $res['skipped']++; $res['errors'][$line] = __('Doppelt in der Datei (Zeile {n}).', ['n' => $seen[$k]]); continue; }
                if ($k !== '') $seen[$k] = $line;
                $existing = $n !== '' ? self::bySource($n) : null;
                if ($existing && !$overwrite) { $res['skipped']++; continue; }
                [$v, $errors] = self::validate($in, $existing ? (int) $existing['id'] : null);
                if ($errors) { $res['errors'][$line] = implode(' ', $errors); $res['skipped']++; continue; }
                if ($dry) { $existing ? $res['updated']++ : $res['created']++; continue; }
                [, $err] = self::save($v + ['active' => 1], $existing ? (int) $existing['id'] : null, 'import');
                if ($err) { $res['errors'][$line] = implode(' ', $err); $res['skipped']++; continue; }
                $existing ? $res['updated']++ : $res['created']++;
            }
        };
        $dry ? $run() : self::db()->transaction(fn() => $run());
        return $res;
    }

    /** Interner Pfad (ohne Query) einer veröffentlichten Seite → page:ID[#anker], sonst null */
    public static function pageRef(string $target): ?string
    {
        if (self::targetKind($target) !== 'path' || str_contains($target, '?') || str_contains($target, '*')) return null;
        [$path, $anchor] = array_pad(explode('#', $target, 2), 2, '');
        $p = self::pageAt($path);
        return $p ? 'page:' . $p['id'] . ($anchor !== '' ? '#' . $anchor : '') : null;
    }

    public static function exportCsv(): string
    {
        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, ['source', 'target', 'code', 'note', 'active', 'hits', 'last_hit'], ';', '"', '');
        foreach (self::all() as $r) {
            fputcsv($fh, [$r['source'], $r['target'], $r['code'], (string) $r['note'], $r['active'], $r['hits'], (string) $r['last_hit']], ';', '"', '');
        }
        rewind($fh);
        return "\xEF\xBB\xBF" . stream_get_contents($fh);
    }

    public static function exportJson(): string
    {
        $out = array_map(fn($r) => ['from' => $r['source'], 'to' => $r['target'], 'code' => (int) $r['code']]
            + ($r['note'] !== null && $r['note'] !== '' ? ['note' => $r['note']] : []) + (!(int) $r['active'] ? ['active' => false] : []), self::all());
        return json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /** Für API und Konsole */
    public static function toArray(array $r): array
    {
        return ['id' => (int) $r['id'], 'source' => $r['source'], 'target' => $r['target'], 'code' => (int) $r['code'], 'note' => (string) $r['note'],
            'origin' => $r['origin'], 'active' => (bool) $r['active'], 'hits' => (int) $r['hits'], 'last_hit' => $r['last_hit'],
            'created_at' => $r['created_at'], 'updated_at' => $r['updated_at']];
    }
}
