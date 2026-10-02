<?php
declare(strict_types=1);

namespace Core;

/**
 * Seiten mit Editor.js-JSON als Inhaltsquelle.
 * content_draft = Arbeitsstand, content_published = öffentlich sichtbarer Stand.
 */
final class Pages
{
    public static function db(): Database
    {
        return app()->db;
    }

    public static function find(int $id): ?array
    {
        return self::db()->fetch('SELECT * FROM pages WHERE id = ?', [$id]);
    }

    /** Erste Seite mit diesem Slug (bevorzugt oberste Ebene) – für Altverweise; eindeutig ist der Pfad */
    public static function bySlug(string $slug): ?array
    {
        return self::db()->fetch('SELECT * FROM pages WHERE slug = ? ORDER BY (parent_id IS NULL) DESC, id LIMIT 1', [$slug]);
    }

    /** Seite anhand des vollständigen Pfads, z. B. „leistungen/vorsorge“ */
    public static function byPath(string $path, ?string $lang = null): ?array
    {
        $path = trim($path, '/');
        $lang = Lang::norm($lang ?? Lang::current());
        return self::db()->fetch("SELECT * FROM pages WHERE path = ? AND type = 'page' AND " . Lang::sql(), [$path, $lang])
            ?? self::db()->fetch("SELECT * FROM pages WHERE path IS NULL AND slug = ? AND parent_id IS NULL AND type = 'page' AND " . Lang::sql(), [$path, $lang]);
    }

    /** Übersetzungen einer Seite: [sprache => seite] (inkl. der Seite selbst) */
    public static function translations(array $page): array
    {
        $group = (int) ($page['translation_group'] ?: $page['id']);
        $out = [];
        foreach (self::db()->fetchAll('SELECT * FROM pages WHERE id = ? OR translation_group = ?', [$group, $group]) as $p) {
            $out[Lang::norm($p['lang'])] = $p;
        }
        return $out;
    }

    /**
     * Übersetzung anlegen: Kopie der Seite in einer anderen Sprache (Entwurf), in der Übersetzungsgruppe verknüpft.
     * Die übergeordnete Seite wird – wenn vorhanden – durch ihre Übersetzung ersetzt.
     */
    public static function translate(int $id, string $lang): array
    {
        $p = self::find($id) ?? throw new \RuntimeException('Seite nicht gefunden.');
        if (!Lang::valid($lang)) throw new \RuntimeException('Unbekannte Sprache.');
        $existing = self::translations($p)[$lang] ?? null;
        if ($existing) return $existing;
        $group = (int) ($p['translation_group'] ?: $p['id']);
        if (!$p['translation_group']) self::db()->update('pages', ['translation_group' => $group], 'id = :id', ['id' => $group]);
        $parent = null;
        if ($p['parent_id'] && ($pp = self::find((int) $p['parent_id']))) {
            $parent = isset(self::translations($pp)[$lang]) ? (int) self::translations($pp)[$lang]['id'] : null;
        }
        $slug = $p['slug'];
        for ($n = 2; self::slugTaken($slug, $parent, null, $lang); $n++) $slug = $p['slug'] . '-' . $n;
        $newId = self::create(['slug' => $slug, 'title' => $p['title'], 'status' => 'draft', 'is_home' => $p['is_home'],
            'parent_id' => $parent, 'menu' => $p['menu'], 'nav_title' => $p['nav_title'], 'meta_description' => $p['meta_description'], 'meta_title' => $p['meta_title'] ?? null,
            'noindex' => $p['noindex'], 'lang' => $lang === Lang::default() ? null : $lang, 'translation_group' => $group, 'sort' => $p['sort'],
            // Vorlagen (Detailseiten, Seite „Nicht gefunden“ – Core\NotFound) bleiben Vorlagen
            'type' => $p['type'] ?? 'page', 'template_for' => $p['template_for'] ?? null],
            self::blocks($p, true));
        return self::find($newId);
    }

    /** Slug auf gleicher Ebene schon vergeben? */
    public static function slugTaken(string $slug, ?int $parentId, ?int $exceptId = null, ?string $lang = null): bool
    {
        // Oberste Ebene: Namen von Ordnern/Dateien in public/ (assets, media, pools, sites – vor assets:migrate auch kits, themes,
        // extensions, fonts) liefert der Webserver selbst aus – eine Seite dort wäre nie erreichbar (Apache/nginx antworten mit
        // 301/403). Solche Adressen gelten als vergeben. Ausnahme: eine bestehende Seite behält ihre Adresse.
        // Siehe Core\PublicPaths und Entwicklerhandbuch → Staging & Deploy → Umstellung public/ → /assets/
        if (!$parentId && $slug !== '' && file_exists(ROOT . '/public/' . $slug)
            && !($exceptId && ($own = self::find($exceptId)) && $own['slug'] === $slug && !$own['parent_id'])) return true;
        $sql = 'SELECT id FROM pages WHERE slug = ? AND ' . ($parentId ? 'parent_id = ?' : 'parent_id IS NULL') . ' AND ' . Lang::sql();
        $params = $parentId ? [$slug, $parentId, Lang::norm($lang)] : [$slug, Lang::norm($lang)];
        if ($lang === null && $exceptId && ($own = self::find($exceptId))) {
            $params[count($params) - 1] = Lang::norm($own['lang']);
        }
        foreach (self::db()->fetchAll($sql, $params) as $r) {
            if ((int) $r['id'] !== (int) $exceptId) return true;
        }
        return false;
    }

    // ================================================================= Seitenbaum

    /** Alle Seiten als Baum: [['page' => …, 'children' => [...]], …] */
    /** Seitenbaum; mit $lang nur Seiten dieser Sprache (bei nur einer Sprache: alle) */
    public static function tree(bool $withTemplates = true, ?string $lang = null): array
    {
        $rows = self::db()->fetchAll('SELECT * FROM pages ORDER BY is_home DESC, sort, id');
        $by = [];
        foreach ($rows as $r) {
            if (!$withTemplates && $r['type'] !== 'page') continue;
            if ($lang !== null && Lang::multi() && $r['type'] === 'page' && Lang::norm($r['lang']) !== $lang) continue;
            $by[(int) ($r['parent_id'] ?? 0)][] = $r;
        }
        $build = function (int $pid, int $depth) use (&$build, $by): array {
            $out = [];
            foreach ($by[$pid] ?? [] as $r) {
                $out[] = ['page' => $r, 'depth' => $depth, 'children' => $depth < 8 ? $build((int) $r['id'], $depth + 1) : []];
            }
            return $out;
        };
        return $build(0, 0);
    }

    /** Flache Liste in Baumreihenfolge mit Tiefe (für Auswahllisten) */
    public static function flat(bool $withTemplates = false): array
    {
        $out = [];
        $walk = function (array $nodes) use (&$walk, &$out) {
            foreach ($nodes as $n) {
                $out[] = $n['page'] + ['depth' => $n['depth']];
                $walk($n['children']);
            }
        };
        $walk(self::tree($withTemplates));
        return $out;
    }

    /** Vorfahren (von oben nach unten), z. B. für Brotkrumen */
    public static function ancestors(array $page): array
    {
        $out = [];
        $seen = [];
        while (!empty($page['parent_id']) && !isset($seen[$page['parent_id']])) {
            $seen[$page['parent_id']] = true;
            $page = self::find((int) $page['parent_id']);
            if (!$page) break;
            array_unshift($out, $page);
        }
        return $out;
    }

    /** IDs aller Nachfahren (verhindert Zyklen beim Verschieben) */
    public static function descendantIds(int $id): array
    {
        $ids = [];
        $queue = [$id];
        while ($queue) {
            $pid = array_shift($queue);
            foreach (self::db()->fetchAll('SELECT id FROM pages WHERE parent_id = ?', [$pid]) as $r) {
                $ids[] = (int) $r['id'];
                $queue[] = (int) $r['id'];
            }
        }
        return $ids;
    }

    /**
     * Seite im Baum verschieben: neuer Elternteil ($parentId null = oberste Ebene) und Position.
     * Position am besten über eine Nachbarseite ($before/$after = ID einer Seite derselben Ebene): Der Seitenbaum der
     * Verwaltung zeigt nicht alle Geschwister (Detailseiten-Vorlagen, 404-Seiten und andere Sprachen liegen unsichtbar
     * auf derselben Ebene) – ein dort gezählter $index trifft in der Datenbank sonst die falsche Stelle.
     * Prüft Zyklen und Slug-Kollisionen, baut Pfade neu auf.
     */
    public static function move(int $id, ?int $parentId, int $index, ?int $before = null, ?int $after = null): ?string
    {
        $page = self::find($id);
        if (!$page) return 'Seite nicht gefunden.';
        if ($parentId && ($parentId === $id || in_array($parentId, self::descendantIds($id), true))) {
            return 'Eine Seite kann nicht in sich selbst verschoben werden.';
        }
        if ($parentId && !self::find($parentId)) return 'Übergeordnete Seite nicht gefunden.';
        if ($page['is_home'] && $parentId) return 'Die Startseite bleibt auf der obersten Ebene.';
        if (self::slugTaken($page['slug'], $parentId, $id)) {
            return 'Auf dieser Ebene gibt es schon eine Seite mit der Adresse „' . $page['slug'] . '“.';
        }
        $sql = 'SELECT id FROM pages WHERE id != ? AND ' . ($parentId ? 'parent_id = ?' : 'parent_id IS NULL') . ' ORDER BY is_home DESC, sort, id';
        $siblings = array_map('intval', array_column(self::db()->fetchAll($sql, $parentId ? [$id, $parentId] : [$id]), 'id'));
        $anchor = $before ?: $after;
        if ($anchor) {
            if ($anchor === $id) return null;   // auf sich selbst abgelegt: nichts zu tun
            $at = array_search($anchor, $siblings, true);
            if ($at === false) return 'Die Bezugsseite liegt nicht auf dieser Ebene.';
            $index = $at + ($before ? 0 : 1);
        }
        self::db()->transaction(function () use ($id, $parentId, $index, $siblings) {
            array_splice($siblings, max(0, min($index, count($siblings))), 0, [$id]);
            foreach ($siblings as $i => $sid) {
                self::db()->update('pages', ['sort' => $i * 10] + ($sid === $id ? ['parent_id' => $parentId] : []), 'id = :id', ['id' => $sid]);
            }
        });
        self::rebuildPaths();
        PageCache::clear();
        return null;
    }

    /**
     * Pfade aller Seiten aus Slugs und Eltern neu berechnen. Geänderte Pfade veröffentlichter Seiten legen eine
     * Weiterleitung alter Pfad → page:ID an (Core\Redirects\Redirects::pathsChanged, Funktion „redirects“).
     */
    public static function rebuildPaths(): void
    {
        $rows = self::db()->fetchAll('SELECT id, parent_id, slug, is_home, path, type, status, published_at, lang FROM pages');
        $by = array_column($rows, null, 'id');
        $changes = [];
        foreach ($rows as $r) {
            $parts = [];
            $cur = $r;
            $guard = 0;
            while ($cur && $guard++ < 20) {
                if (!$cur['is_home']) array_unshift($parts, $cur['slug']);
                $cur = $cur['parent_id'] ? ($by[$cur['parent_id']] ?? null) : null;
            }
            $path = $r['is_home'] ? '' : implode('/', $parts);
            if ($r['path'] === null || $path !== (string) $r['path']) {
                self::db()->update('pages', ['path' => $path], 'id = :id', ['id' => (int) $r['id']]);
                $changes[] = ['id' => (int) $r['id'], 'old' => $r['path'], 'new' => $r['is_home'] ? null : $path, 'lang' => $r['lang'],
                    'type' => $r['type'], 'published' => $r['status'] === 'published' || $r['published_at'] !== null];
            }
        }
        if ($changes) {
            try {
                Redirects\Redirects::pathsChanged($changes);
            } catch (\Throwable $e) {
                error_log('[redirects] ' . $e->getMessage());   // Speichern der Seite geht vor
            }
        }
    }

    /**
     * Menübaum aus Seiten mit „Im Menü“ (öffentlich: nur veröffentlichte).
     * @return array<array{id:int,label:string,href:string,active:bool,children:array}>
     */
    public static function menu(bool $includeDrafts = false): array
    {
        $cur = app()->currentPage;
        $activeIds = $cur ? array_merge(array_map(fn($p) => (int) $p['id'], self::ancestors($cur)), [(int) $cur['id']]) : [];
        $walk = function (array $nodes) use (&$walk, $includeDrafts, $activeIds): array {
            $out = [];
            foreach ($nodes as $n) {
                $p = $n['page'];
                if (!$p['menu'] || $p['type'] !== 'page' || (!$includeDrafts && $p['status'] !== 'published')) continue;
                // Angemeldet: neue, noch nie veröffentlichte Seiten erscheinen (Vorschau, 'draft' => true); offline gestellte
                // (schon einmal veröffentlicht) nicht – sie sollen aus dem Menü verschwinden, z. B. nach dem Zusammenlegen
                if ($p['status'] !== 'published' && $p['content_published'] !== null) continue;
                $out[] = ['id' => (int) $p['id'], 'label' => $p['nav_title'] ?: $p['title'], 'href' => self::url($p),
                    'active' => in_array((int) $p['id'], $activeIds, true), 'children' => $walk($n['children']),
                    'draft' => $p['status'] !== 'published'];
            }
            return $out;
        };
        $tree = self::tree(false, Lang::current());
        // Landing-Domain: Menü = Unterseiten der Landingpage
        if ($l = Landings::current()) $tree = $l->subtree($tree, Lang::current());
        return $walk($tree);
    }

    /** Startseite der Sprache (Standard: aktuelle Sprache; Rückfall auf die Startseite der Standardsprache) */
    public static function home(?string $lang = null): ?array
    {
        $lang = Lang::norm($lang ?? Lang::current());
        return self::db()->fetch('SELECT * FROM pages WHERE is_home = 1 AND ' . Lang::sql() . ' ORDER BY id LIMIT 1', [$lang])
            ?? self::db()->fetch('SELECT * FROM pages WHERE is_home = 1 AND ' . Lang::sql() . ' ORDER BY id LIMIT 1', [Lang::default()]);
    }

    public static function all(): array
    {
        return self::db()->fetchAll("SELECT * FROM pages WHERE type = 'page' ORDER BY is_home DESC, sort, title");
    }

    public static function published(): array
    {
        return self::db()->fetchAll("SELECT * FROM pages WHERE status = 'published' AND type = 'page' ORDER BY is_home DESC, sort, title");
    }

    /** Adresse einer Seite – auf Landing-Domains (Core\Landings) relativ zur Landingpage bzw. absolut zur Hauptdomain */
    public static function url(array $page): string
    {
        if ($l = Landings::current()) return $l->href($page);
        return self::plainUrl($page);
    }

    /** Adresse einer Seite auf der Hauptdomain (relativ, ohne Landing-Regeln) */
    public static function plainUrl(array $page): string
    {
        $prefix = Lang::prefix($page['lang'] ?? null);
        return $page['is_home'] ? url($prefix . '/') : url($prefix . '/' . (($page['path'] ?? '') !== '' ? $page['path'] : $page['slug']));
    }

    /** Blöcke (Editor.js-Format) einer Seite */
    public static function blocks(array $page, bool $draft = false): array
    {
        $json = $draft ? (($page['content_draft'] ?? null) ?: ($page['content_published'] ?? null)) : ($page['content_published'] ?? null);
        $data = json_decode((string) $json, true);
        return is_array($data['blocks'] ?? null) ? $data['blocks'] : [];
    }

    public static function hasUnpublished(array $page): bool
    {
        return ($page['content_draft'] ?? null) !== null && $page['content_draft'] !== ($page['content_published'] ?? null);
    }

    /** Alle Anker einer Seite (für Link-Autocomplete & Link-Auflösung) */
    public static function anchors(array $page, bool $draft = false): array
    {
        $out = [];
        foreach (Layout::flatten(self::blocks($page, $draft)) as $b) {   // auch Sprungmarken von Blöcken in Spalten
            $t = $b['tunes']['section'] ?? [];
            if (!empty($t['anchor']) && ($t['visible'] ?? true)) {
                $out[$t['anchor']] = $t['navLabel'] ?? ($b['data']['title_strong'] ?? $b['data']['title'] ?? $t['anchor']);
            }
        }
        return $out;
    }

    public static function slugify(string $s): string
    {
        $s = strtr(mb_strtolower(trim($s)), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', '’' => '', "'" => '', '´' => '', '`' => '']);
        $s = preg_replace('~[^a-z0-9]+~', '-', $s);
        return trim((string) $s, '-') ?: 'seite';
    }

    /**
     * Abgelehnte Kinder des letzten sanitizeBlocks() (Block „Layout“: nicht verschachtelbare Blöcke) – je „Typ“ als Text,
     * z. B. für eine Fehlermeldung der API.
     * @var list<string>
     */
    public static array $rejected = [];

    /**
     * Bereinigt eingehendes Editor.js-JSON anhand der Block-Schemata. Block „Layout“ (Core\Layout): Spalten passend zum Raster,
     * darin nur verschachtelbare Blöcke (Theme::nestable – andere werden verworfen und in self::$rejected gemeldet); Block-IDs
     * der ganzen Seite eindeutig (auch in Spalten).
     */
    public static function sanitizeBlocks(array $blocks, ?array &$seen = null, bool $nested = false): array
    {
        $theme = app()->theme;
        $out = [];
        if ($seen === null) { $seen = []; self::$rejected = []; }
        foreach ($blocks as $b) {
            if (!is_array($b)) continue;
            $type = (string) ($b['type'] ?? '');
            $def = $theme->block($type);
            if (!$def) {
                continue;
            }
            if ($nested && !$theme->nestable($type)) {
                self::$rejected[] = $type;
                continue;
            }
            [$data] = Fields::sanitize($def['fields'] ?? [], is_array($b['data'] ?? null) ? $b['data'] : []);
            // Feldbindungen für Detailseiten-Vorlagen: {blockfeld: datensatzfeld}
            $bind = [];
            $names = array_column($def['fields'] ?? [], 'name');
            foreach ((array) ($b['data']['_bind'] ?? []) as $k => $v) {
                if (in_array($k, $names, true) && is_string($v) && preg_match('~^[a-z_][a-z0-9_]{0,40}$~', $v)) $bind[$k] = $v;
            }
            if ($bind) $data['_bind'] = $bind;
            // Bild anpassen je Einbindung: {feldpfad: anpassung} – nur Bild-Felder des Schemas (auch in Listen), Core\ImageFx
            if (!empty($b['data']['_fx']) && ($fx = ImageFx::sanitize($b['data']['_fx'], $def['fields'] ?? [], $data))) $data['_fx'] = $fx;
            // Bild im Rahmen je Einbindung (füllen/einpassen/Originalformat): {feldpfad: einstellung}, Core\ImageFit
            if (!empty($b['data']['_fit']) && ($fit = ImageFit::sanitize($b['data']['_fit'], $def['fields'] ?? [], $data))) $data['_fit'] = $fit;
            if (!empty($def['variants'])) {
                $variant = (string) ($b['data']['variant'] ?? '');
                // In einer Spalte nur die erlaubten Varianten (z. B. Handlungsaufruf nur als Box)
                $allowed = $nested && is_array($nv = $theme->nestableVariants($type)) ? array_flip($nv) : $def['variants'];
                $data['variant'] = array_key_exists($variant, $allowed) ? $variant : (string) array_key_first($allowed);
            }
            if ($type === Layout::TYPE) {
                $preset = Layout::preset($data);
                $cols = Layout::fitColumns(is_array($b['data']['columns'] ?? null) ? $b['data']['columns'] : [], Layout::count($preset));
                $data['columns'] = array_map(fn($c) => ['blocks' => self::sanitizeBlocks($c['blocks'], $seen, true)], $cols);
            }
            $id = preg_replace('~[^\w\-]~', '', (string) ($b['id'] ?? '')) ?: substr(bin2hex(random_bytes(6)), 0, 10);
            while (isset($seen[$id])) $id = substr(bin2hex(random_bytes(6)), 0, 10);
            $seen[$id] = true;
            $out[] = [
                'id' => $id,
                'type' => $type,
                'data' => $data,
                'tunes' => ['section' => $nested ? Layout::childTunes((array) ($b['tunes']['section'] ?? $b['section'] ?? []), $theme) : $theme->sanitizeTunes($b['tunes']['section'] ?? [], $def)],
            ];
        }
        return $out;
    }

    public static function saveDraft(int $id, array $blocks, ?int $userId, string $note = 'Gespeichert'): void
    {
        $json = json_encode(['time' => (int) (microtime(true) * 1000), 'blocks' => $blocks, 'version' => '2.31'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        self::db()->update('pages', ['content_draft' => $json, 'updated_at' => now()], 'id = :id', ['id' => $id]);
        self::addRevision($id, $json, $userId, $note);
        // Ereignis für Erweiterungen (Extension::on): Entwurf gespeichert
        if (Extensions::listens('page.saved') && ($p = self::find($id))) Extensions::emit(new Events\PageSaved($p, $userId));
    }

    /** Offene Platzhalter „[bitte ergänzen: …]“ (KI-Assistent, Core\AI) im JSON bzw. Text einer Seite */
    public static function openMarkers(?string $json): array
    {
        $s = str_replace(['\\u00e4', '\\u00c4'], ['ä', 'Ä'], (string) $json);
        // Beispiele in Code (<code>…</code>, `Backticks`) sind keine offenen Platzhalter – z. B. in Anleitungen
        $s = preg_replace(['~<code\b[^>]*>.*?<\\\\?/code>~is', '~`[^`\r\n]*`~u'], '', $s) ?? $s;
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        preg_match_all('~\[\s*bitte erg(?:ä|ae)nzen[^\]]{0,160}\]~iu', $s, $m);
        return array_values(array_unique($m[0]));
    }

    /**
     * Veröffentlichen. Enthält der Entwurf noch Platzhalter „[bitte ergänzen: …]“ (z. B. aus dem Seiten-Generator), wird nicht
     * veröffentlicht: \RuntimeException mit Liste – der Entwurf bleibt gespeichert.
     */
    public static function publish(int $id, ?int $userId): void
    {
        $p = self::find($id);
        if (!$p) {
            return;
        }
        if ($open = self::openMarkers(($p['content_draft'] ?? $p['content_published']) . ' ' . $p['title'] . ' ' . $p['meta_description'])) {
            throw new \RuntimeException(__('Nicht veröffentlicht: Die Seite enthält noch {n} Platzhalter, z. B. {list}. Bitte ergänzen oder entfernen.', ['n' => count($open), 'list' => implode(' · ', array_slice($open, 0, 3))]));
        }
        self::db()->update('pages', [
            'content_published' => $p['content_draft'] ?? $p['content_published'],
            'status' => 'published', 'published_at' => now(), 'updated_at' => now(),
        ], 'id = :id', ['id' => $id]);
        PageCache::clear();
        if (Extensions::listens('page.published') && ($p = self::find($id))) Extensions::emit(new Events\PagePublished($p, $userId));
    }

    /**
     * Offline nehmen: Status „Entwurf“, die veröffentlichte Fassung (content_published) bleibt erhalten – „Online stellen“
     * (publish) bringt sie zurück. Besucher erhalten danach „Nicht gefunden“, Menü, Sitemap und Suche lassen die Seite aus.
     * @return bool false für die Startseite (immer online) und unbekannte Seiten
     */
    public static function unpublish(int $id): bool
    {
        $p = self::find($id);
        if (!$p || $p['is_home']) {
            return false;
        }
        if ($p['status'] === 'published') {
            self::db()->update('pages', ['status' => 'draft', 'updated_at' => now()], 'id = :id', ['id' => $id]);
            PageCache::clear();   // Seiten-Cache + Suchindex (Core\Search::changed)
            if (Extensions::listens('page.unpublished') && ($q = self::find($id))) Extensions::emit(new Events\PageUnpublished($q));
        }
        return true;
    }

    /**
     * Veröffentlichungsstand für Listen und die Werkzeugleiste: online | offline (war schon online, Status „Entwurf“) |
     * draft (noch nie veröffentlicht)
     */
    public static function state(array $p): string
    {
        return $p['status'] === 'published' ? 'online' : ($p['content_published'] !== null ? 'offline' : 'draft');
    }

    /**
     * Entwurf verwerfen: Arbeitsstand = veröffentlichte Fassung.
     * Der verworfene Entwurf bleibt als Version erhalten (wiederherstellbar).
     * @return bool false, wenn die Seite noch nie veröffentlicht wurde
     */
    public static function discardDraft(int $id, ?int $userId, string $note = 'Entwurf verworfen'): bool
    {
        $p = self::find($id);
        if (!$p || $p['content_published'] === null) {
            return false;
        }
        if ($p['content_draft'] !== null && $p['content_draft'] !== $p['content_published']) {
            self::addRevision($id, (string) $p['content_draft'], $userId, 'Verworfener Entwurf (Sicherung)');
        }
        self::db()->update('pages', ['content_draft' => $p['content_published'], 'updated_at' => now()], 'id = :id', ['id' => $id]);
        self::addRevision($id, (string) $p['content_published'], $userId, $note);
        if (Extensions::listens('page.discarded') && ($q = self::find($id))) Extensions::emit(new Events\PageDiscarded($q, $userId));
        return true;
    }

    public static function addRevision(int $pageId, string $json, ?int $userId, string $note): void
    {
        $db = self::db();
        $last = $db->fetchValue('SELECT blocks_json FROM revisions WHERE page_id = ? ORDER BY id DESC LIMIT 1', [$pageId]);
        if ($last !== null && self::stripTime($last) === self::stripTime($json)) {
            return;
        }
        $db->insert('revisions', ['page_id' => $pageId, 'blocks_json' => $json, 'created_at' => now(), 'user_id' => $userId, 'note' => $note]);
        $keep = (int) app()->config->get('revisions', 20);
        $ids = $db->fetchAll('SELECT id FROM revisions WHERE page_id = ? ORDER BY id DESC', [$pageId]);
        foreach (array_slice($ids, $keep) as $r) {
            $db->query('DELETE FROM revisions WHERE id = ?', [$r['id']]);
        }
    }

    private static function stripTime(string $json): string
    {
        $d = json_decode($json, true);
        return json_encode($d['blocks'] ?? []);
    }

    public static function revisions(int $pageId): array
    {
        return self::db()->fetchAll('SELECT r.id, r.created_at, r.note, u.email FROM revisions r
            LEFT JOIN users u ON u.id = r.user_id WHERE r.page_id = ? ORDER BY r.id DESC', [$pageId]);
    }

    public static function create(array $fields, array $blocks = []): int
    {
        $json = json_encode(['time' => (int) (microtime(true) * 1000), 'blocks' => $blocks, 'version' => '2.31'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $status = $fields['status'] ?? 'draft';
        $id = self::db()->insert('pages', array_replace([
            'slug' => '', 'title' => '', 'meta_description' => '', 'og_image' => null, 'status' => $status,
            'is_home' => 0, 'sort' => 0, 'noindex' => 0,
            'content_draft' => $json, 'content_published' => $status === 'published' ? $json : null,
            'updated_at' => now(), 'published_at' => $status === 'published' ? now() : null,
        ], $fields));
        self::rebuildPaths();
        return $id;
    }
}
