<?php
declare(strict_types=1);

namespace Core;

/**
 * Inhalte zwischen Live und einer lokalen Kopie abgleichen – Seiten hin und zurück, ohne fremde Änderungen zu überschreiben.
 *
 *   1. holen:        deploy/content-pull.sh <ziel>   (site:backup live → site:restore lokal → content:snapshot)
 *   2. bearbeiten:   lokal in der Verwaltung (Entwurf oder veröffentlicht – beides zählt)
 *   3. zurück:       deploy/content-push.sh <ziel>   (content:export lokal → content:import live)
 *
 * content:snapshot merkt sich je Seite den Fingerabdruck des Live-Stands (storage/…/content-sync.json).
 * content:export nimmt nur Seiten, die sich lokal seitdem geändert haben, und legt den gemerkten Fingerabdruck bei.
 * content:import prüft live je Seite, ob sie noch diesem Fingerabdruck entspricht – sonst hat dort inzwischen jemand
 * gearbeitet, und die Seite wird nicht angefasst (Konflikt). Übernommen wird als Entwurf mit Version („Content-Sync“);
 * erst --publish veröffentlicht. Zuordnung über Pfad + Sprache.
 * Neue Seiten (mit Eltern, Übersetzungsgruppe, Menü) und neue Medien, auf die sie verweisen, gehen mit – live entstehen
 * dabei neue IDs; Verweise (Medien-IDs, page:ID) werden umgeschrieben. Danach ist der gemerkte Stand veraltet: vor der
 * nächsten Änderung neu holen (content:export verweigert sonst).
 */
final class ContentSync
{
    /** Seitenfelder, die neben den Blöcken mitgehen */
    private const FIELDS = ['title', 'meta_title', 'meta_description', 'nav_title', 'noindex', 'menu'];

    public static function console(string $cmd, array $args): int
    {
        $opt = fn(string $n) => in_array('--' . $n, $args, true);
        $val = function (string $n) use ($args): ?string {
            foreach ($args as $a) if (str_starts_with($a, "--$n=")) return substr($a, strlen($n) + 3);
            return null;
        };
        $pos = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));
        return match ($cmd) {
            'content:snapshot' => self::snapshot($val('pushed'), $opt('publish')),
            'content:export' => self::export($val('out'), $opt('all')),
            'content:import' => self::import($pos[0] ?? '', $opt('publish'), $opt('dry-run'), $opt('force')),
            default => 1,
        };
    }

    /** Fingerabdruck einer Seite: Blöcke (ohne Zeitstempel) + Seitenfelder */
    public static function fingerprint(array $page, bool $draft = false): string
    {
        $json = $draft ? ($page['content_draft'] ?? $page['content_published']) : $page['content_published'];
        $blocks = json_decode((string) $json, true)['blocks'] ?? [];
        $fields = array_map(fn($f) => (string) ($page[$f] ?? ''), array_combine(self::FIELDS, self::FIELDS));
        return sha1(json_encode([$blocks, $fields], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function key(array $page): string
    {
        return ($page['lang'] ?: '-') . ':' . ($page['is_home'] ? '/' : (string) $page['path']);
    }

    private static function baseFile(): string
    {
        return site()->storage('content-sync.json');
    }

    /**
     * Stand merken: je Seite Fingerabdruck der veröffentlichten Fassung ('pub') und des Arbeitsstands ('work', Entwurf).
     * $pushed (nach dem Zurückspielen, deploy/content-push.sh): nur die übertragenen Seiten nachführen – live ist ihr
     * Arbeitsstand jetzt der lokale, veröffentlicht nur mit $published.
     */
    private static function snapshot(?string $pushed = null, bool $published = false): int
    {
        if ($pushed !== null) {
            $base = self::base();
            $data = json_decode((string) @file_get_contents($pushed), true);
            if (!$base || !is_array($data['pages'] ?? null)) { fwrite(STDERR, "content:snapshot --pushed: Stand oder Exportdatei fehlt.\n"); return 1; }
            $local = [];
            foreach (Pages::all() as $p) $local[self::key($p)] = $p;
            foreach ($data['pages'] as $in) {
                $k = (string) $in['key'];
                if (!isset($local[$k]) || !empty($in['new'])) continue;
                $work = self::fingerprint($local[$k], true);
                $base['pages'][$k] = ['pub' => $published ? $work : $base['pages'][$k]['pub'], 'work' => $work];
            }
            // Neue Seiten/Medien haben live andere IDs – lokal weiterarbeiten würde falsche Verweise erzeugen
            $stale = !empty($data['media']) || (bool) array_filter($data['pages'], fn($x) => !empty($x['new']));
            self::saveBase($base['pages'], $base['created'], (int) ($base['media_max'] ?? 0), $stale);
            echo 'Stand nachgeführt: ' . count($data['pages']) . " Seite(n).\n";
            return 0;
        }
        $pages = [];
        foreach (Pages::all() as $p) $pages[self::key($p)] = ['pub' => self::fingerprint($p), 'work' => self::fingerprint($p, true)];
        self::saveBase($pages, now(), (int) Pages::db()->fetchValue('SELECT MAX(id) FROM media'));
        echo 'Stand gemerkt: ' . count($pages) . ' Seiten (' . site()->key . ") – Änderungen ab jetzt gehen mit content:export zurück.\n";
        return 0;
    }

    /** Gemerkter Stand ['created', 'pages' => [key => ['pub', 'work']]] oder null */
    private static function base(): ?array
    {
        $b = json_decode((string) @file_get_contents(self::baseFile()), true);
        if (!is_array($b) || !is_array($b['pages'] ?? null)) return null;
        foreach ($b['pages'] as $k => $v) if (is_string($v)) $b['pages'][$k] = ['pub' => $v, 'work' => $v];
        return $b;
    }

    private static function saveBase(array $pages, string $created, int $mediaMax = 0, bool $stale = false): void
    {
        @mkdir(dirname(self::baseFile()), 0775, true);
        file_put_contents(self::baseFile(), json_encode(['site' => site()->key, 'created' => $created, 'updated' => now(), 'media_max' => $mediaMax,
            'stale' => $stale, 'pages' => $pages],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function export(?string $out, bool $all): int
    {
        $base = self::base();
        if (!$base) { fwrite(STDERR, "Kein gemerkter Stand – zuerst holen (deploy/content-pull.sh) bzw. content:snapshot.\n"); return 1; }
        if (!empty($base['stale'])) { fwrite(STDERR, "Seit dem letzten Zurückspielen haben neue Seiten/Medien live andere IDs – bitte zuerst neu holen (deploy/content-pull.sh).\n"); return 1; }
        $all_ = Pages::all();
        $byId = array_column($all_, null, 'id');
        $pages = [];
        foreach ($all_ as $p) {
            $k = self::key($p);
            $json = $p['content_draft'] ?? $p['content_published'];
            $row = ['key' => $k, 'blocks' => json_decode((string) $json, true)['blocks'] ?? []] + array_intersect_key($p, array_flip(self::FIELDS));
            if (!isset($base['pages'][$k])) {
                // Neue Seite: Ort im Baum und Übersetzungsgruppe über Schlüssel (live andere IDs)
                $parent = $p['parent_id'] ? ($byId[$p['parent_id']] ?? null) : null;
                $head = $byId[$p['translation_group']] ?? null;
                $pages[] = $row + ['new' => true, 'local_id' => (int) $p['id'], 'slug' => $p['slug'], 'lang' => $p['lang'],
                    'parent' => $parent ? self::key($parent) : null, 'group' => $head && (int) $head['id'] !== (int) $p['id'] ? self::key($head) : null,
                    'sort' => (int) $p['sort'], 'type' => $p['type'], 'template_for' => $p['template_for']];
                echo "  neu: $k – {$p['title']}\n";
                continue;
            }
            if (!$all && self::fingerprint($p, true) === $base['pages'][$k]['work']) continue;
            $pages[] = $row + ['base' => $base['pages'][$k]];
            echo "  geändert: $k – {$p['title']}\n";
        }
        if (!$pages) { echo "Keine Änderungen seit dem Holen ({$base['created']}).\n"; return 0; }
        // Neue Medien (nach dem Holen hochgeladen), auf die die Seiten verweisen – Datei mitgeben
        $media = [];
        $max = (int) ($base['media_max'] ?? PHP_INT_MAX);
        foreach (array_unique(array_merge(...array_map(fn($x) => self::mediaRefs($x['blocks']), $pages))) as $mid) {
            if ($mid <= $max || !($m = Media::find($mid))) continue;
            $path = Media::dir() . '/' . $m['file'];
            if (!is_file($path)) { fwrite(STDERR, "Datei zu Medium #$mid fehlt: $path\n"); return 1; }
            $media[] = ['id' => $mid, 'name' => $m['original_name'] ?: $m['file'], 'data' => base64_encode((string) file_get_contents($path))]
                + array_intersect_key($m, array_flip(['alt', 'title', 'credit', 'tags', 'decorative', 'focus_x', 'focus_y', 'i18n', 'crops']));
            echo "  neues Medium: #$mid " . ($m['original_name'] ?: $m['file']) . "\n";
        }
        $out ??= site()->storage('content-sync-export.json');
        file_put_contents($out, json_encode(['site' => site()->key, 'base_created' => $base['created'], 'created' => now(), 'pages' => $pages, 'media' => $media],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo count($pages) . ' Seite(n)' . ($media ? ', ' . count($media) . ' Medien' : '') . " exportiert: $out\n";
        return 0;
    }

    private static function import(string $file, bool $publish, bool $dry, bool $force): int
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($data) || !is_array($data['pages'] ?? null)) {
            fwrite(STDERR, "Aufruf: content:import <export.json> [--dry-run] [--publish] [--force]  (--force übergeht Konflikte)\n");
            return 1;
        }
        $live = [];
        foreach (Pages::all() as $p) $live[self::key($p)] = $p;
        $mediaIds = array_flip(array_map('intval', array_column(Pages::db()->fetchAll('SELECT id FROM media'), 'id')));
        $newMedia = array_flip(array_map(fn($m) => (int) $m['id'], (array) ($data['media'] ?? [])));
        $newKeys = array_flip(array_map(fn($x) => $x['key'], array_filter($data['pages'], fn($x) => !empty($x['new']))));
        $plan = [];
        $blocked = 0;
        foreach ($data['pages'] as $in) {
            $k = (string) ($in['key'] ?? '');
            $p = $live[$k] ?? null;
            $problems = [];
            if (!empty($in['new'])) {
                if ($p) $problems[] = 'gibt es live schon';
                foreach (['parent' => 'übergeordnete Seite', 'group' => 'Übersetzungsgruppe'] as $f => $label) {
                    if (!empty($in[$f]) && !isset($live[$in[$f]]) && !isset($newKeys[$in[$f]])) $problems[] = "$label {$in[$f]} fehlt live";
                }
            } else {
                if (!$p) { echo "  ✗ $k – gibt es live nicht mehr\n"; $blocked++; continue; }
                // Live muss noch so aussehen wie beim Holen – veröffentlichte Fassung UND Entwurf (auf dem lokal weitergearbeitet wurde)
                $b = is_array($in['base'] ?? null) ? $in['base'] : ['pub' => (string) ($in['base'] ?? ''), 'work' => (string) ($in['base'] ?? '')];
                if (self::fingerprint($p) !== $b['pub']) $problems[] = 'live seit dem Holen veröffentlicht';
                elseif (self::fingerprint($p, true) !== $b['work']) $problems[] = 'live seit dem Holen bearbeitet (Entwurf)';
            }
            $missing = array_filter(self::mediaRefs($in['blocks'] ?? []), fn($id) => !isset($newMedia[$id]) && !isset($mediaIds[$id]));
            if ($missing) $problems[] = 'Medien fehlen live: #' . implode(', #', array_slice($missing, 0, 5));
            $title = $in['title'] ?? $p['title'] ?? '';
            if ($problems && !$force) { echo "  ✗ $k – $title: " . implode('; ', $problems) . "\n"; $blocked++; continue; }
            echo '  ' . ($problems ? '! ' : '✓ ') . (!empty($in['new']) ? 'neu ' : '') . "$k – $title" . ($problems ? ' (übergangen: ' . implode('; ', $problems) . ')' : '') . "\n";
            $plan[] = [$p, $in];
        }
        foreach ((array) ($data['media'] ?? []) as $m) echo "  ✓ neues Medium: {$m['name']}\n";
        if ($blocked && !$force) {
            fwrite(STDERR, "Nichts übernommen: $blocked Seite(n) mit Konflikt. Neu holen und Änderung wiederholen – oder bewusst --force.\n");
            return 2;
        }
        if ($dry) { echo count($plan) . " Seite(n) würden übernommen" . ($publish ? ' und veröffentlicht' : ' (als Entwurf)') . ". Probelauf – nichts geändert.\n"; return 0; }

        // 1. Neue Medien anlegen → ID-Zuordnung lokal → live
        $mediaMap = [];
        foreach ((array) ($data['media'] ?? []) as $m) {
            $tmp = tempnam(sys_get_temp_dir(), 'csm');
            file_put_contents($tmp, base64_decode((string) $m['data']));
            [$row, $err] = Media::import($tmp, (string) $m['name'], (string) ($m['alt'] ?? ''), ['title' => $m['title'] ?? '', 'credit' => $m['credit'] ?? '', 'tags' => $m['tags'] ?? '', 'decorative' => !empty($m['decorative'])]);
            @unlink($tmp);
            if (!$row) { fwrite(STDERR, "Medium {$m['name']}: $err – abgebrochen.\n"); return 1; }
            Pages::db()->update('media', array_intersect_key($m, array_flip(['focus_x', 'focus_y', 'i18n', 'crops'])), 'id = :id', ['id' => (int) $row['id']]);
            $mediaMap[(int) $m['id']] = (int) $row['id'];
        }
        // 2. Neue Seiten: Eltern zuerst, je Gruppe die Hauptsprache zuerst
        $pageMap = [];
        $keyToId = array_map(fn($p) => (int) $p['id'], $live);
        usort($plan, fn($a, $b) => [empty($a[1]['new']), substr_count($a[1]['key'], '/'), !empty($a[1]['group'])] <=> [empty($b[1]['new']), substr_count($b[1]['key'], '/'), !empty($b[1]['group'])]);
        $note = 'Content-Sync (' . ($data['created'] ?? now()) . ')';
        foreach ($plan as [$p, $in]) {
            $blocks = Pages::sanitizeBlocks(self::remap((array) $in['blocks'], $mediaMap, $pageMap));
            $fields = array_intersect_key($in, array_flip(self::FIELDS));
            if (!empty($in['new'])) {
                $id = Pages::create($fields + ['slug' => (string) $in['slug'], 'lang' => $in['lang'] ?: null, 'sort' => (int) $in['sort'], 'type' => $in['type'] ?: 'page',
                    'template_for' => $in['template_for'] ?: null, 'parent_id' => $in['parent'] ? ($keyToId[$in['parent']] ?? null) : null,
                    'status' => $publish ? 'published' : 'draft'], $blocks);
                Pages::db()->update('pages', ['translation_group' => $in['group'] ? ($keyToId[$in['group']] ?? $id) : $id], 'id = :id', ['id' => $id]);
                Pages::addRevision($id, (string) Pages::find($id)['content_draft'], null, $note);
                $pageMap[(int) $in['local_id']] = $id;
                $keyToId[$in['key']] = $id;
                continue;
            }
            if ($fields) Pages::db()->update('pages', $fields, 'id = :id', ['id' => (int) $p['id']]);
            Pages::saveDraft((int) $p['id'], $blocks, null, $note);
            if ($publish) Pages::publish((int) $p['id'], null);
        }
        PageCache::clear();
        echo count($plan) . ' Seite(n)' . ($mediaMap ? ', ' . count($mediaMap) . ' Medien' : '') . ' übernommen'
            . ($publish ? ' und veröffentlicht.' : ' – als Entwurf; in der Verwaltung prüfen und veröffentlichen.') . "\n";
        return 0;
    }

    /** Medien-IDs und page:ID-Verweise auf neu angelegte Einträge umschreiben (lokale → live-IDs) */
    private static function remap(array $blocks, array $media, array $pages): array
    {
        if (!$media && !$pages) return $blocks;
        $walk = function ($v, string $key = '') use (&$walk, $media, $pages) {
            if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = $walk($x, is_string($k) ? $k : $key); return $v; }
            if ($media && self::isMediaKey($key) && (is_int($v) || (is_string($v) && ctype_digit($v))) && isset($media[(int) $v])) return is_int($v) ? $media[$v] : (string) $media[(int) $v];
            if ($pages && is_string($v) && str_contains($v, 'page:')) return preg_replace_callback('~\bpage:(\d+)~', fn($m) => 'page:' . ($pages[(int) $m[1]] ?? $m[1]), $v);
            return $v;
        };
        return $walk($blocks);
    }

    private static function isMediaKey(string $key): bool
    {
        return (bool) preg_match('~(image|media|poster|logo|file|photo|video|bgImage)\d*s?$~i', $key);
    }

    /** Medien-IDs, auf die Blöcke verweisen (Felder wie image, media, poster, logo, file – auch in Listen) */
    private static function mediaRefs(array $blocks): array
    {
        $ids = [];
        $walk = function ($v, string $key = '') use (&$walk, &$ids) {
            if (is_array($v)) { foreach ($v as $k => $x) $walk($x, is_string($k) ? $k : $key); return; }
            if (self::isMediaKey($key) && (is_int($v) || ctype_digit((string) $v)) && (int) $v > 0) $ids[] = (int) $v;
        };
        $walk($blocks);
        return array_values(array_unique($ids));
    }
}
