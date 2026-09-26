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
 * erst --publish veröffentlicht. Zuordnung über Pfad + Sprache; neue Seiten und neue Medien überträgt der Abgleich nicht.
 */
final class ContentSync
{
    /** Seitenfelder, die neben den Blöcken mitgehen */
    private const FIELDS = ['title', 'meta_title', 'meta_description', 'nav_title', 'noindex'];

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
                if (!isset($local[$k])) continue;
                $work = self::fingerprint($local[$k], true);
                $base['pages'][$k] = ['pub' => $published ? $work : $base['pages'][$k]['pub'], 'work' => $work];
            }
            self::saveBase($base['pages'], $base['created']);
            echo 'Stand nachgeführt: ' . count($data['pages']) . " Seite(n).\n";
            return 0;
        }
        $pages = [];
        foreach (Pages::all() as $p) $pages[self::key($p)] = ['pub' => self::fingerprint($p), 'work' => self::fingerprint($p, true)];
        self::saveBase($pages, now());
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

    private static function saveBase(array $pages, string $created): void
    {
        @mkdir(dirname(self::baseFile()), 0775, true);
        file_put_contents(self::baseFile(), json_encode(['site' => site()->key, 'created' => $created, 'updated' => now(), 'pages' => $pages],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private static function export(?string $out, bool $all): int
    {
        $base = self::base();
        if (!$base) { fwrite(STDERR, "Kein gemerkter Stand – zuerst holen (deploy/content-pull.sh) bzw. content:snapshot.\n"); return 1; }
        $pages = [];
        foreach (Pages::all() as $p) {
            $k = self::key($p);
            if (!isset($base['pages'][$k])) { echo "  übersprungen (neu, nicht live): $k\n"; continue; }
            if (!$all && self::fingerprint($p, true) === $base['pages'][$k]['work']) continue;
            $json = $p['content_draft'] ?? $p['content_published'];
            $pages[] = ['key' => $k, 'base' => $base['pages'][$k], 'blocks' => json_decode((string) $json, true)['blocks'] ?? []]
                + array_intersect_key($p, array_flip(self::FIELDS));
            echo "  geändert: $k – {$p['title']}\n";
        }
        if (!$pages) { echo "Keine Änderungen seit dem Holen ({$base['created']}).\n"; return 0; }
        $out ??= site()->storage('content-sync-export.json');
        file_put_contents($out, json_encode(['site' => site()->key, 'base_created' => $base['created'], 'created' => now(), 'pages' => $pages], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo count($pages) . " Seite(n) exportiert: $out\n";
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
        $plan = [];
        $blocked = 0;
        foreach ($data['pages'] as $in) {
            $k = (string) ($in['key'] ?? '');
            $p = $live[$k] ?? null;
            if (!$p) { echo "  ✗ $k – gibt es live nicht (neue Seiten überträgt der Abgleich nicht)\n"; $blocked++; continue; }
            $problems = [];
            // Live muss noch so aussehen wie beim Holen – veröffentlichte Fassung UND Entwurf (auf dem lokal weitergearbeitet wurde)
            $b = is_array($in['base'] ?? null) ? $in['base'] : ['pub' => (string) ($in['base'] ?? ''), 'work' => (string) ($in['base'] ?? '')];
            if (self::fingerprint($p) !== $b['pub']) $problems[] = 'live seit dem Holen veröffentlicht';
            elseif (self::fingerprint($p, true) !== $b['work']) $problems[] = 'live seit dem Holen bearbeitet (Entwurf)';
            if ($missing = array_diff(self::mediaRefs($in['blocks'] ?? []), array_keys($mediaIds))) $problems[] = 'Medien fehlen live: #' . implode(', #', array_slice($missing, 0, 5));
            if ($problems && !$force) { echo "  ✗ $k – {$p['title']}: " . implode('; ', $problems) . "\n"; $blocked++; continue; }
            echo '  ' . ($problems ? '! ' : '✓ ') . "$k – {$p['title']}" . ($problems ? ' (übergangen: ' . implode('; ', $problems) . ')' : '') . "\n";
            $plan[] = [$p, $in];
        }
        if ($blocked && !$force) {
            fwrite(STDERR, "Nichts übernommen: $blocked Seite(n) mit Konflikt. Neu holen und Änderung wiederholen – oder bewusst --force.\n");
            return 2;
        }
        if ($dry) { echo count($plan) . " Seite(n) würden übernommen" . ($publish ? ' und veröffentlicht' : ' (als Entwurf)') . ". Probelauf – nichts geändert.\n"; return 0; }
        foreach ($plan as [$p, $in]) {
            $blocks = Pages::sanitizeBlocks((array) $in['blocks']);
            $fields = array_intersect_key($in, array_flip(self::FIELDS));
            if ($fields) Pages::db()->update('pages', $fields, 'id = :id', ['id' => (int) $p['id']]);
            Pages::saveDraft((int) $p['id'], $blocks, null, 'Content-Sync (' . ($data['created'] ?? now()) . ')');
            if ($publish) Pages::publish((int) $p['id'], null);
        }
        PageCache::clear();
        echo count($plan) . ' Seite(n) übernommen' . ($publish ? ' und veröffentlicht.' : ' – als Entwurf; in der Verwaltung prüfen und veröffentlichen.') . "\n";
        return 0;
    }

    /** Medien-IDs, auf die Blöcke verweisen (Felder wie image, media, poster, logo, file – auch in Listen) */
    private static function mediaRefs(array $blocks): array
    {
        $ids = [];
        $walk = function ($v, string $key = '') use (&$walk, &$ids) {
            if (is_array($v)) { foreach ($v as $k => $x) $walk($x, is_string($k) ? $k : $key); return; }
            if (preg_match('~(image|media|poster|logo|file|photo|video|bgImage)s?$~i', $key) && (is_int($v) || ctype_digit((string) $v)) && (int) $v > 0) $ids[] = (int) $v;
        };
        $walk($blocks);
        return array_values(array_unique($ids));
    }
}
