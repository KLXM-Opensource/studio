<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Graphics;

use Core\Media;
use Core\Svg;

/**
 * Bildkompositionen des Grafik-Editors (Persona „Bild“, Ausbaustufe 6): Fotos der Mediathek, Text und Formen gemischt wie in
 * Fireworks. Das Ergebnis ist immer ein normales Pixelbild der Mediathek (alle Größen, WebP/AVIF, Alt-Text) – gerendert im
 * Browser; nur die Arbeitsfassung bleibt ein bearbeitbares SVG.
 *
 * Ablage: Tabelle graphics (kind „photo“, nicht in der Mediathek, nicht in /admin/grafik), Spalte svg = Arbeitsfassung. Fotos sind
 * darin Platzhalter-Rechtecke mit data-ge-photo (Base64-JSON: m = Mediathek-ID, z = Zoom, fx/fy = Ausschnitt, b/c/s/bl = Korrektur) –
 * nie eingebettete Pixel, nie ein <image>: Core\Svg::clean bleibt für Website-SVGs bei „nur Vektor“.
 * graphic_media verbindet Komposition und Mediathek: role „out“ = Ausgabe je Zeichenfläche (board „main“ bzw. id des Bereichs),
 * role „src“ = verwendete Fotos (Media::usages zeigt die Komposition unter „Verwendet auf“). Speichern ersetzt vorhandene Ausgaben
 * per Media::replace – ID und Verwendungen bleiben.
 *
 * Unabhängig von Request/Controller (Host-neutral): Dateien kommen als Pfade, Rechte prüft der Aufrufer.
 */
final class Compose
{
    public const KIND = 'photo';
    /** Größte Ausgabe (Seitenlänge in Pixeln, Faktor 2 eingerechnet) – Media verkleinert Originale ohnehin auf 3200 */
    public const MAX_PX = 4000;

    /** @var array<int, int>|null Medium (Ausgabe) → Komposition, je Anfrage einmal gelesen */
    private static ?array $outMap = null;

    public static function find(int $id): ?array
    {
        $g = app()->db->fetch('SELECT * FROM graphics WHERE id = ? AND kind = ?', [$id, self::KIND]);
        return $g ?: null;
    }

    /** Mediathek-IDs der Fotos einer Arbeitsfassung (data-ge-photo → m), in Dokumentreihenfolge, ohne Doppelte */
    public static function photoIds(string $svg): array
    {
        $ids = [];
        if (!preg_match_all('~data-ge-photo="([A-Za-z0-9+/=]{1,4000})"~', $svg, $m)) return [];
        foreach ($m[1] as $b64) {
            $d = json_decode((string) base64_decode($b64, true), true);
            $id = is_array($d) ? (int) ($d['m'] ?? 0) : 0;
            if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
        }
        return $ids;
    }

    /** Ist die Arbeitsfassung eine Bildkomposition (Fotos oder ausdrücklich Pixel-Ausgabe)? */
    public static function isRaster(string $svg): bool
    {
        return str_contains($svg, 'data-ge-photo=') || (bool) preg_match('~<svg[^>]*\sdata-ge-out="(jpeg|png)"~', $svg);
    }

    /**
     * Angaben zu Fotos für den Editor: Adresse zum Anzeigen (WebP 1600), Original (zum Rendern in voller Auflösung), Maße, Fokuspunkt,
     * Alt-Text, Name. Nur Pixelbilder der Website (keine SVGs, nichts aus dem Papierkorb).
     * @param list<int> $ids
     * @return array<int, array{id: int, url: string, full: string, w: int, h: int, focus: array{x: int, y: int}, alt: string, name: string}>
     */
    public static function photoInfo(array $ids): array
    {
        $out = [];
        foreach (array_slice(array_unique(array_map('intval', $ids)), 0, 200) as $id) {
            $m = $id > 0 ? Media::find($id) : null;
            if (!$m || !empty($m['deleted_at']) || !str_starts_with((string) $m['mime'], 'image/') || $m['mime'] === Svg::MIME) continue;
            $out[$id] = ['id' => $id, 'url' => Media::url($m, 1600), 'full' => Media::originalUrl($m), 'w' => (int) $m['width'], 'h' => (int) $m['height'],
                'focus' => ['x' => (int) ($m['focus_x'] ?? 50), 'y' => (int) ($m['focus_y'] ?? 50)], 'alt' => (string) ($m['alt'] ?? ''), 'name' => Media::displayName($m)];
        }
        return $out;
    }

    /** @return array<string, int> Zeichenfläche → Medium der Ausgabe */
    public static function outputs(int $gid): array
    {
        $out = [];
        foreach (app()->db->fetchAll("SELECT board, media_id FROM graphic_media WHERE graphic_id = ? AND role = 'out'", [$gid]) as $r) $out[(string) $r['board']] = (int) $r['media_id'];
        return $out;
    }

    /** Komposition, aus der ein Medium entstanden ist (null = keine) */
    public static function forMedia(int $mediaId): ?int
    {
        if (self::$outMap === null) {
            self::$outMap = [];
            try {
                foreach (app()->db->fetchAll("SELECT media_id, graphic_id FROM graphic_media WHERE role = 'out'") as $r) self::$outMap[(int) $r['media_id']] = (int) $r['graphic_id'];
            } catch (\Throwable) {
                // Tabelle fehlt (vor der Migration)
            }
        }
        return self::$outMap[$mediaId] ?? null;
    }

    /** Vorlage, aus der ein Bild ausgefüllt wurde (site:{id} | net:{id}) – null = frei gestaltet */
    public static function templateOf(int $gid): ?string
    {
        $v = app()->db->fetchValue('SELECT tpl FROM graphics WHERE id = ? AND kind = ?', [$gid, self::KIND]);
        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * Speichern: Arbeitsfassung (bereinigt) + Ausgaben je Zeichenfläche. $outputs: [{board, path, name}] – path = fertiges Pixelbild
     * (JPEG/PNG aus dem Browser). Vorhandene Ausgabe derselben Zeichenfläche wird ersetzt (Media::replace), sonst neu importiert.
     * $meta: name, title, alt, decorative. $tpl/$vals: aus einer Vorlage (Templates) ausgefüllt.
     * @param list<array{board: string, path: string, name?: string}> $outputs
     * @return array{id: int, outputs: array<string, array<string, mixed>>, errors: list<string>}
     * @throws \RuntimeException verständliche Meldung
     */
    public static function save(string $work, array $meta, ?int $id, array $outputs, ?string $tpl = null, ?array $vals = null): array
    {
        $r = Svg::clean($work, ['editor' => true]);
        $svg = $r['svg'];
        $old = $id ? self::find($id) : null;
        if ($id && !$old) throw new \RuntimeException(__('Grafik nicht gefunden.'));
        if (!$outputs) throw new \RuntimeException(__('Kein Bild übermittelt.'));
        foreach ($outputs as $o) {
            $info = @getimagesize((string) ($o['path'] ?? ''));
            if (!$info || !in_array($info['mime'], ['image/jpeg', 'image/png'], true)) throw new \RuntimeException(__('Erwartet wird ein JPEG- oder PNG-Bild.'));
            if ($info[0] > self::MAX_PX || $info[1] > self::MAX_PX) throw new \RuntimeException(__('Das Bild ist zu groß (höchstens {n} Pixel je Seite).', ['n' => self::MAX_PX]));
        }
        $name = Graphics::cleanName((string) ($meta['name'] ?? '')) ?: __('Bild');
        $db = app()->db;
        $row = ['kind' => self::KIND, 'name' => $name, 'svg' => $svg, 'width' => $r['width'], 'height' => $r['height'], 'updated_at' => now(),
            'tpl' => $tpl !== null && preg_match('~^(site|net):\d+$~', $tpl) ? $tpl : ($old['tpl'] ?? null),
            'vals' => $vals !== null ? (string) json_encode($vals, JSON_UNESCAPED_UNICODE) : ($old['vals'] ?? null)];
        if ($old) {
            if ((string) $old['svg'] !== $svg) Graphics::addVersion('graphic:' . (int) $old['id'], (string) $old['svg']);
            $db->update('graphics', $row, 'id = :id', ['id' => (int) $old['id']]);
            $gid = (int) $old['id'];
        } else {
            $gid = $db->insert('graphics', $row + ['created_at' => now(), 'user_id' => (int) (app()->auth->user()['id'] ?? 0) ?: null]);
        }
        $have = self::outputs($gid);
        $done = [];
        $errors = [];
        $alt = trim(strip_tags((string) ($meta['alt'] ?? '')));
        foreach ($outputs as $o) {
            $board = preg_match('~^[a-z0-9_-]{1,40}$~i', (string) $o['board']) ? (string) $o['board'] : 'main';
            $ext = (getimagesize($o['path'])['mime'] ?? '') === 'image/png' ? 'png' : 'jpg';
            $file = Media::fileSlug(trim((string) ($o['name'] ?? '')) ?: $name) . '.' . $ext;
            $mid = $have[$board] ?? null;
            $m = $mid ? Media::find($mid) : null;
            if ($m && empty($m['deleted_at'])) {
                $opt = ['alt' => $alt];
                [$m, $err] = Media::replace((int) $mid, $o['path'], $file, $opt);
            } else {
                [$m, $err] = Media::import($o['path'], $file, $alt, ['title' => mb_substr(strip_tags((string) ($meta['title'] ?? $name)), 0, 180), 'decorative' => !empty($meta['decorative']),
                    'tags' => (string) ($meta['tags'] ?? '')]);
            }
            if (!$m) { $errors[] = (string) $err; continue; }
            if (!empty($meta['title'])) { $db->update('media', ['title' => mb_substr(strip_tags((string) $meta['title']), 0, 180)], 'id = :id', ['id' => (int) $m['id']]); Media::forget((int) $m['id']); $m = Media::find((int) $m['id']) ?? $m; }
            $db->query("DELETE FROM graphic_media WHERE graphic_id = ? AND role = 'out' AND board = ?", [$gid, $board]);
            $db->insert('graphic_media', ['graphic_id' => $gid, 'media_id' => (int) $m['id'], 'role' => 'out', 'board' => $board, 'hash' => (string) ($m['hash'] ?? '')]);
            $done[$board] = Media::toJson($m);
        }
        // Zeichenflächen, die es nicht mehr gibt: Verbindung lösen (das Bild bleibt in der Mediathek)
        $boards = array_map(fn($o) => (string) $o['board'], $outputs);
        foreach (array_keys($have) as $b) if (!in_array($b, $boards, true)) $db->query("DELETE FROM graphic_media WHERE graphic_id = ? AND role = 'out' AND board = ?", [$gid, $b]);
        // verwendete Fotos (Verwendet auf)
        $db->query("DELETE FROM graphic_media WHERE graphic_id = ? AND role = 'src'", [$gid]);
        foreach (self::photoIds($svg) as $pid) $db->insert('graphic_media', ['graphic_id' => $gid, 'media_id' => $pid, 'role' => 'src', 'board' => null, 'hash' => null]);
        self::$outMap = null;
        \Core\PageCache::clear();
        return ['id' => $gid, 'outputs' => $done, 'errors' => $errors];
    }

    /**
     * Wurde eine Ausgabe seit dem letzten Speichern anderswo ersetzt oder bearbeitet (Mediathek „Datei ersetzen“, Bild bearbeiten)?
     * @return list<string> Zeichenflächen mit geänderter Datei
     */
    public static function changedOutputs(int $gid): array
    {
        $out = [];
        foreach (app()->db->fetchAll("SELECT board, media_id, hash FROM graphic_media WHERE graphic_id = ? AND role = 'out'", [$gid]) as $r) {
            $m = Media::find((int) $r['media_id']);
            if ($m && (string) ($r['hash'] ?? '') !== '' && (string) $m['hash'] !== (string) $r['hash']) $out[] = (string) $r['board'];
        }
        return $out;
    }

    /**
     * „Verwendet auf“ eines Fotos: Kompositionen, die es verwenden (mit Adresse zum Bearbeiten).
     * @return list<array{label: string, url: string}>
     */
    public static function usagesOf(int $mediaId): array
    {
        $out = [];
        try {
            $rows = app()->db->fetchAll("SELECT g.id, g.name FROM graphic_media gm JOIN graphics g ON g.id = gm.graphic_id WHERE gm.media_id = ? AND gm.role = 'src' AND g.kind = ? ORDER BY g.name",
                [$mediaId, self::KIND]);
        } catch (\Throwable) {
            return [];
        }
        foreach ($rows as $r) $out[] = ['label' => __('Bild aus dem Grafik-Editor') . ' → ' . (string) $r['name'], 'url' => url('/admin/grafik/editor?id=' . (int) $r['id'])];
        return $out;
    }

    /** @return array<int, true> Fotos, die in Kompositionen stecken (Filter „Nicht verwendet“) */
    public static function usedIds(): array
    {
        $out = [];
        try {
            foreach (app()->db->fetchAll("SELECT DISTINCT media_id FROM graphic_media WHERE role = 'src'") as $r) $out[(int) $r['media_id']] = true;
        } catch (\Throwable) {
            // Tabelle fehlt
        }
        return $out;
    }

    /** Komposition löschen (nur die Arbeitsfassung; die Bilder bleiben in der Mediathek) */
    public static function delete(int $gid): bool
    {
        if (!self::find($gid)) return false;
        app()->db->query('DELETE FROM graphic_media WHERE graphic_id = ?', [$gid]);
        app()->db->query('DELETE FROM graphic_versions WHERE ref = ?', ['graphic:' . $gid]);
        app()->db->query('DELETE FROM graphics WHERE id = ?', [$gid]);
        self::$outMap = null;
        return true;
    }

    /**
     * Alt-Text-Vorschlag aus den Textebenen (Lesereihenfolge = Dokumentreihenfolge), höchstens 250 Zeichen. Für Fälle ohne Browser
     * (Vorlagen per API); der Editor schlägt dasselbe im Speichern-Dialog vor.
     */
    public static function suggestAlt(string $svg): string
    {
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($svg, LIBXML_NONET)) return '';
        $parts = [];
        foreach ($doc->getElementsByTagName('text') as $t) {
            /** @var \DOMElement $t */
            if ($t->getAttribute('display') === 'none') continue;
            $s = trim((string) preg_replace('~\s+~u', ' ', $t->textContent));
            if ($s !== '' && !in_array($s, $parts, true)) $parts[] = $s;
        }
        return mb_substr(implode(' – ', $parts), 0, 250);
    }
}
