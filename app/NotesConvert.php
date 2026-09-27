<?php
declare(strict_types=1);

namespace Core;

/**
 * Alte Marker in Redaktionsnotizen „[# … #]“ umstellen (Core\EditorNotes) – php bin/console notes:convert [--site=…] [--dry-run].
 *
 * Umgestellt werden
 *   - „[bitte ergänzen: …]“, „[bitte prüfen: …]“, „[bitte rechtlich prüfen: …]“, „[bitte ergänzen nach Prüfung: …]“, „[bitte ergänzen]“
 *     (auch groß geschrieben, auch mit Formatierung darin),
 *   - „[Platzhalter]“ bzw. „[Platzhalter: …]“ (z. B. in Titeln von Einträgen),
 *   - die Kennzeichnung „NEU (bitte prüfen)“ (Dachzeilen, Beschriftungen).
 * Nicht angefasst: Text in <code>/<pre> und in `Backticks` (Anleitungen), andere Klammern („[Musik]“), schon umgestellte Notizen.
 *
 * Wo: Seiten (veröffentlichte Fassung UND Entwurf, Titel, Navigationstitel, SEO-Beschreibung) – je Fassung an Ort und Stelle,
 * der Veröffentlichungsstatus bleibt, nichts wird veröffentlicht; je geänderter Seite eine Version „Notizen umgestellt“ –
 * sowie Einträge aller Inhalts-Tabellen (nicht Eingänge: verschlüsselt, Angaben von Besuchern). updated_at bleibt unverändert.
 *
 * Hinweis: Marker aus dem KI-Seiten-Generator („[bitte ergänzen: …]“ in NEUEN Entwürfen) sperren weiterhin das Veröffentlichen
 * (Pages::openMarkers) – umgestellte Notizen nicht, sie sind öffentlich unsichtbar.
 */
final class NotesConvert
{
    private const RX_BITTE = '~(?<!`)\[\s*((?:bitte|Bitte)\s+(?:ergänzen\s+nach\s+Prüfung|rechtlich\s+prüfen|ergänzen|prüfen)(?:\s*[:–-][^\[\]]{0,1200}?)?)\s*\](?!`|\()~u';
    private const RX_PH = '~(?<!`)\[\s*((?:Platzhalter|PLATZHALTER|platzhalter)(?:\s*:[^\[\]]{0,600}?)?)\s*\](?!`|\()~u';
    private const RX_NEU = '~(?<![\[\w`])(?<!\[#\s)(NEU \(bitte prüfen\))(?![\w`])~u';
    private const KEEP = '~(<(code|pre)\b[^>]*>.*?</\2\s*>)~is';
    private const SKIP_KEYS = ['_fx', '_fit', '_bind'];

    /** Einen Text umstellen (Klartext oder HTML) */
    public static function convert(string $s): string
    {
        if (!preg_match('~bitte|Bitte|Platzhalter|PLATZHALTER|platzhalter~', $s)) return $s;
        $parts = preg_split(self::KEEP, $s, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$s];
        $out = '';
        foreach ($parts as $i => $p) {
            if ($i % 3 === 2) continue;
            if ($i % 3 === 1) { $out .= $p; continue; }
            $p = (string) preg_replace_callback(self::RX_BITTE, fn($m) => '[# ' . trim($m[1]) . ' #]', $p);
            $p = (string) preg_replace_callback(self::RX_PH, fn($m) => '[# ' . trim($m[1]) . ' #]', $p);
            $p = (string) preg_replace(self::RX_NEU, '[# $1 #]', $p);
            $out .= $p;
        }
        return $out;
    }

    /** Verschachtelte Daten umstellen; $n zählt geänderte Texte */
    public static function convertData(mixed $v, int &$n): mixed
    {
        if (is_string($v)) {
            $c = self::convert($v);
            if ($c !== $v) $n++;
            return $c;
        }
        if (!is_array($v)) return $v;
        foreach ($v as $k => $x) {
            if (is_string($k) && in_array($k, self::SKIP_KEYS, true)) continue;
            $v[$k] = self::convertData($x, $n);
        }
        return $v;
    }

    /**
     * Umstellung der aktuellen Website. @return array{pages: int, versions: int, texts: int, entries: int, entry_texts: int, notes: int, log: list<string>}
     */
    public static function run(bool $dry): array
    {
        $db = app()->db;
        $r = ['pages' => 0, 'versions' => 0, 'texts' => 0, 'entries' => 0, 'entry_texts' => 0, 'notes' => 0, 'log' => []];
        foreach ($db->fetchAll('SELECT * FROM pages ORDER BY id') as $p) {
            $upd = [];
            $n = 0;
            foreach (['content_published', 'content_draft'] as $col) {
                if (($p[$col] ?? null) === null || $p[$col] === '') continue;
                $json = json_decode((string) $p[$col], true);
                if (!is_array($json) || !is_array($json['blocks'] ?? null)) continue;
                $k = 0;
                $json['blocks'] = self::convertData($json['blocks'], $k);
                if ($k) {
                    $upd[$col] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $r['versions']++;
                    $n += $k;
                }
            }
            // Gleiche Fassungen bleiben gleich (sonst gälte die Seite als „mit unveröffentlichten Änderungen“)
            if (isset($upd['content_published']) && $p['content_draft'] === $p['content_published']) $upd['content_draft'] = $upd['content_published'];
            foreach (['title', 'nav_title', 'meta_description', 'meta_title'] as $f) {
                if (!array_key_exists($f, $p) || !is_string($p[$f])) continue;
                $c = self::convert($p[$f]);
                if ($c !== $p[$f]) { $upd[$f] = $c; $n++; }
            }
            if (!$upd) continue;
            $r['pages']++;
            $r['texts'] += $n;
            $r['notes'] += count(EditorNotes::find(implode(' ', array_map('strval', $upd))));
            $r['log'][] = sprintf('  Seite #%d %s – %d Text(e)%s', $p['id'], $p['title'], $n, $p['status'] === 'draft' ? ' (Entwurf)' : (Pages::hasUnpublished($p) ? ' (veröffentlicht + Entwurf)' : ''));
            if ($dry) continue;
            $db->update('pages', $upd, 'id = :id', ['id' => (int) $p['id']]);
            $rev = $upd['content_draft'] ?? $p['content_draft'] ?? $upd['content_published'] ?? $p['content_published'];
            if ($rev !== null) Pages::addRevision((int) $p['id'], (string) $rev, null, 'Notizen umgestellt');
        }
        foreach ($db->fetchAll('SELECT handle, settings_json FROM data_tables ORDER BY id') as $t) {
            $s = json_decode((string) $t['settings_json'], true) ?: [];
            if (($s['kind'] ?? 'content') === 'inbox' || !preg_match('~^[a-z0-9_]+$~', (string) $t['handle'])) continue;
            try {
                $rows = $db->fetchAll("SELECT * FROM data_{$t['handle']}");
            } catch (\Throwable) {
                continue;   // geteilte Tabelle einer anderen Website
            }
            foreach ($rows as $e) {
                $upd = [];
                foreach ($e as $col => $v) {
                    if (!is_string($v) || in_array($col, \Core\Data\Tables::SYSTEM, true)) continue;
                    $j = ($v !== '' && ($v[0] === '{' || $v[0] === '[')) ? json_decode($v, true) : null;
                    if (is_array($j)) {
                        $k = 0;
                        $c = self::convertData($j, $k);
                        if ($k) $upd[$col] = json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    } elseif (($c = self::convert($v)) !== $v) {
                        $upd[$col] = $c;
                    }
                }
                if (!$upd) continue;
                $r['entries']++;
                $r['entry_texts'] += count($upd);
                $r['notes'] += count(EditorNotes::find(implode(' ', $upd)));
                $r['log'][] = sprintf('  %s #%d – %s', $t['handle'], $e['id'], implode(', ', array_keys($upd)));
                if (!$dry) $db->update("data_{$t['handle']}", $upd, 'id = :id', ['id' => (int) $e['id']]);
            }
        }
        if (!$dry && ($r['pages'] || $r['entries'])) PageCache::clear();
        return $r;
    }
}
