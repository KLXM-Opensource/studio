<?php
declare(strict_types=1);

namespace Core;

/**
 * Untertitel, Kapitel und Transkripte für Videos und Audio der Mediathek (Barrierefreiheit, WCAG 1.2.2/1.2.3).
 *
 *  - Textspuren (Tabelle media_tracks in der Datenbank der Datei – Website oder geteilter Pool): Art (subtitles | captions |
 *    descriptions | chapters), Sprache, Bezeichnung, Status (draft | published), Herkunft (upload | editor | ai | translate).
 *    Der WebVTT-Inhalt liegt in der Datenbank; veröffentlichte Spuren werden zusätzlich als Datei neben die Medien geschrieben:
 *    {Medienordner}/tracks/{id}-{sprache}-{art}.vtt – Entwürfe (z. B. KI-Transkripte) sind so nie öffentlich abrufbar.
 *  - Transkripte (Spalte media.transcripts, JSON je Sprache {text, status, source, updated_at}): Klartext für Besucher
 *    („Transkript anzeigen“) und die Website-Suche. Entwürfe erscheinen nicht auf der Website.
 *  - Hochgeladene .vtt/.srt werden geprüft und gesäubert: nur erlaubte WebVTT-Tags (b, i, u, c, v, lang, ruby, rt, Zeitmarken),
 *    Cue-Einstellungen nur line/position/size/align, keine STYLE-/REGION-Blöcke.
 */
final class MediaTracks
{
    public const KINDS = ['subtitles' => 'Untertitel', 'captions' => 'Untertitel für Gehörlose (mit Geräuschen)', 'descriptions' => 'Audiodeskription (Text)', 'chapters' => 'Kapitel'];
    public const MAX_BYTES = 2 * 1024 * 1024;
    public const MAX_CUES = 5000;
    /** Eigenbezeichnungen häufiger Sprachen (für die Auswahl im Player) */
    private const NAMES = ['de' => 'Deutsch', 'en' => 'English', 'fr' => 'Français', 'es' => 'Español', 'it' => 'Italiano', 'nl' => 'Nederlands',
        'pl' => 'Polski', 'tr' => 'Türkçe', 'ar' => 'العربية', 'ru' => 'Русский', 'uk' => 'Українська', 'pt' => 'Português', 'el' => 'Ελληνικά',
        'sv' => 'Svenska', 'da' => 'Dansk', 'fi' => 'Suomi', 'no' => 'Norsk', 'cs' => 'Čeština', 'hu' => 'Magyar', 'ro' => 'Română', 'bg' => 'Български',
        'hr' => 'Hrvatski', 'sr' => 'Srpski', 'fa' => 'فارسی', 'zh' => '中文', 'ja' => '日本語', 'ko' => '한국어', 'ku' => 'Kurdî', 'sq' => 'Shqip', 'sl' => 'Slovenščina', 'sk' => 'Slovenčina'];

    public static function enabled(): bool
    {
        return Features::on('media.captions', true);
    }

    /** Hat die Datei Ton (Video/Audio)? */
    public static function supports(array $m): bool
    {
        $mime = (string) ($m['mime'] ?? '');
        return str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/');
    }

    public static function validLang(string $code): bool
    {
        return (bool) preg_match('~^[a-z]{2,3}(-[A-Za-z]{2,4})?$~', $code);
    }

    /** Bezeichnung einer Sprache: Sprachen der Website, sonst Eigenbezeichnung, sonst Kürzel */
    public static function langLabel(string $code): string
    {
        $site = Lang::all();
        return (string) ($site[$code] ?? self::NAMES[strtolower(substr($code, 0, 2))] ?? strtoupper($code));
    }

    /** Standardbezeichnung einer Spur im Player, z. B. „Deutsch“, „English (CC)“, „Deutsch – Kapitel“ */
    public static function defaultLabel(string $kind, string $lang): string
    {
        $l = self::langLabel($lang);
        return match ($kind) {
            'captions' => $l . ' (CC)',
            'descriptions' => $l . ' – ' . lt('Audiodeskription'),
            'chapters' => $l . ' – ' . lt('Kapitel'),
            default => $l,
        };
    }

    // ================================================================= Ablage (Website oder Pool)

    /** Datenbank, ID, Medienordner und Pool einer Datei (Verweise auf Pool-Dateien → Pool) */
    public static function ctx(array $m): array
    {
        $pool = !empty($m['_pool']) ? (string) $m['_pool'] : null;
        return [
            'db' => $pool !== null ? MediaPools::db($pool) : app()->db,
            'id' => (int) ($pool !== null ? ($m['_pool_id'] ?? $m['id']) : $m['id']),
            'dir' => $pool !== null ? MediaPools::mediaDir($pool) : site()->mediaDir(),
            'pool' => $pool,
        ];
    }

    private static function publicUrl(array $ctx, string $rel): string
    {
        return $ctx['pool'] !== null ? MediaPools::url($ctx['pool'], $rel) : site()->mediaUrl($rel);
    }

    /** Spuren einer Datei (ohne VTT-Inhalt, außer $withVtt) – veröffentlichte zuerst */
    public static function forMedia(array $m, bool $publishedOnly = false, bool $withVtt = false): array
    {
        if (!self::supports($m)) return [];
        $c = self::ctx($m);
        try {
            $rows = $c['db']->fetchAll('SELECT * FROM media_tracks WHERE media_id = ?' . ($publishedOnly ? " AND status = 'published'" : '')
                . " ORDER BY CASE status WHEN 'published' THEN 0 ELSE 1 END, lang, kind, id", [$c['id']]);
        } catch (\Throwable) {
            return [];
        }
        if (!$withVtt) foreach ($rows as &$r) unset($r['vtt']);
        return $rows;
    }

    public static function find(array $m, int $trackId): ?array
    {
        $c = self::ctx($m);
        return $c['db']->fetch('SELECT * FROM media_tracks WHERE id = ? AND media_id = ?', [$trackId, $c['id']]) ?: null;
    }

    /** Öffentliche Adresse einer veröffentlichten Spur (mit Versionskennung gegen Browser-Cache) */
    public static function url(array $m, array $track): ?string
    {
        if (($track['status'] ?? '') !== 'published' || empty($track['file'])) return null;
        return self::publicUrl(self::ctx($m), (string) $track['file']) . '?v=' . substr(md5((string) $track['updated_at'] . '|' . $track['id']), 0, 8);
    }

    /**
     * Spur anlegen oder ändern. $in: kind, lang, label, cues (Liste [start, end, text, settings] in ms) ODER vtt/srt (Text),
     * status (draft|published), source. Liefert die gespeicherte Spur oder wirft \InvalidArgumentException (Meldung für die Oberfläche).
     */
    public static function save(array $m, array $in, ?int $trackId = null, ?int $userId = null): array
    {
        if (!self::supports($m)) throw new \InvalidArgumentException(__('Untertitel gibt es nur für Videos und Audio.'));
        $c = self::ctx($m);
        $old = $trackId ? self::find($m, $trackId) : null;
        if ($trackId && !$old) throw new \InvalidArgumentException(__('Untertitel nicht gefunden.'));
        $kind = (string) ($in['kind'] ?? $old['kind'] ?? 'subtitles');
        if (!isset(self::KINDS[$kind])) throw new \InvalidArgumentException(__('Unbekannte Art der Textspur.'));
        $lang = (string) ($in['lang'] ?? $old['lang'] ?? Lang::default());
        if (!self::validLang($lang)) throw new \InvalidArgumentException(__('Ungültige Sprache.'));
        if (isset($in['cues']) && is_array($in['cues'])) {
            $cues = self::cleanCues($in['cues']);
        } elseif (isset($in['vtt']) && is_string($in['vtt'])) {
            [$cues, $err] = self::parse($in['vtt']);
            if ($err !== null) throw new \InvalidArgumentException($err);
        } elseif ($old) {
            [$cues] = self::parse((string) $old['vtt']);
        } else {
            $cues = [];
        }
        if (count($cues) > self::MAX_CUES) throw new \InvalidArgumentException(__('Zu viele Einträge (max. {n}).', ['n' => self::MAX_CUES]));
        $status = in_array($in['status'] ?? '', ['draft', 'published'], true) ? $in['status'] : ($old['status'] ?? 'draft');
        if ($status === 'published' && !$cues) throw new \InvalidArgumentException(__('Leere Untertitel können nicht veröffentlicht werden.'));
        $label = trim(mb_substr(strip_tags((string) ($in['label'] ?? $old['label'] ?? '')), 0, 80));
        $row = [
            'kind' => $kind, 'lang' => $lang, 'label' => $label,
            'status' => $status, 'vtt' => self::toVtt($cues, $kind), 'cues' => count($cues),
            'source' => in_array($in['source'] ?? '', ['upload', 'editor', 'ai', 'translate'], true) ? $in['source'] : ($old['source'] ?? 'editor'),
            'updated_at' => now(),
        ];
        if (array_key_exists('note', $in)) $row['note'] = mb_substr(strip_tags((string) $in['note']), 0, 250);
        if ($status === 'published' && ($old['status'] ?? '') !== 'published') {
            $row['reviewed_by'] = $userId;
            $row['reviewed_at'] = now();
        }
        if ($old) {
            $c['db']->update('media_tracks', $row, 'id = :id', ['id' => (int) $old['id']]);
            $id = (int) $old['id'];
        } else {
            $id = (int) $c['db']->insert('media_tracks', $row + ['media_id' => $c['id'], 'created_by' => $userId, 'created_at' => now()]);
        }
        $t = self::find($m, $id);
        self::sync($m, $t, $old);
        PageCache::clear();
        return self::find($m, $id);
    }

    /** Entwurf prüfen und veröffentlichen (ersetzt eine veröffentlichte Spur gleicher Art und Sprache) */
    public static function publish(array $m, int $trackId, ?int $userId = null): array
    {
        $t = self::find($m, $trackId) ?? throw new \InvalidArgumentException(__('Untertitel nicht gefunden.'));
        return self::save($m, ['status' => 'published', 'note' => ''], (int) $t['id'], $userId);
    }

    public static function delete(array $m, int $trackId): void
    {
        $t = self::find($m, $trackId);
        if (!$t) return;
        $c = self::ctx($m);
        if (!empty($t['file'])) @unlink($c['dir'] . '/' . $t['file']);
        $c['db']->query('DELETE FROM media_tracks WHERE id = ?', [(int) $t['id']]);
        PageCache::clear();
    }

    /** Alle Spuren einer Datei entfernen (beim Löschen des Mediums) */
    public static function deleteAll(array $m): void
    {
        $c = self::ctx($m);
        try {
            foreach ($c['db']->fetchAll('SELECT id, file FROM media_tracks WHERE media_id = ?', [$c['id']]) as $t) {
                if (!empty($t['file'])) @unlink($c['dir'] . '/' . $t['file']);
            }
            $c['db']->query('DELETE FROM media_tracks WHERE media_id = ?', [$c['id']]);
        } catch (\Throwable) {
        }
    }

    /** Spuren beim Verschieben in einen Pool mitnehmen (Media::shareToPool) */
    public static function moveToPool(int $siteId, string $pool, int $poolId): void
    {
        try {
            $rows = app()->db->fetchAll('SELECT * FROM media_tracks WHERE media_id = ?', [$siteId]);
        } catch (\Throwable) {
            return;
        }
        $pdb = MediaPools::db($pool);
        foreach ($rows as $t) {
            $old = $t['file'] ?? null;
            unset($t['id']);
            $t['media_id'] = $poolId;
            $t['file'] = null;
            $nid = (int) $pdb->insert('media_tracks', $t);
            if ($old) @unlink(site()->mediaDir() . '/' . $old);
            if (($t['status'] ?? '') === 'published') {
                $row = $pdb->fetch('SELECT * FROM media_tracks WHERE id = ?', [$nid]);
                self::sync(['_pool' => $pool, '_pool_id' => $poolId, 'id' => $poolId, 'mime' => 'video/mp4'], $row, null);
            }
        }
        app()->db->query('DELETE FROM media_tracks WHERE media_id = ?', [$siteId]);
    }

    /**
     * Datei einer Spur schreiben bzw. entfernen: nur veröffentlichte Spuren liegen öffentlich im Medienordner.
     * Eine andere veröffentlichte Spur gleicher Art und Sprache wird dabei ersetzt.
     */
    private static function sync(array $m, array $t, ?array $old): void
    {
        $c = self::ctx($m);
        $rel = 'tracks/' . $c['id'] . '-' . $t['lang'] . '-' . $t['kind'] . '.vtt';
        if (!empty($old['file']) && ($t['status'] !== 'published' || $old['file'] !== $rel)) @unlink($c['dir'] . '/' . $old['file']);
        if ($t['status'] !== 'published') {
            if (!empty($t['file'])) $c['db']->update('media_tracks', ['file' => null], 'id = :id', ['id' => (int) $t['id']]);
            return;
        }
        foreach ($c['db']->fetchAll("SELECT id, file FROM media_tracks WHERE media_id = ? AND kind = ? AND lang = ? AND status = 'published' AND id != ?",
            [$c['id'], $t['kind'], $t['lang'], (int) $t['id']]) as $other) {
            if (!empty($other['file']) && $other['file'] !== $rel) @unlink($c['dir'] . '/' . $other['file']);
            $c['db']->query('DELETE FROM media_tracks WHERE id = ?', [(int) $other['id']]);
        }
        if (!is_dir($c['dir'] . '/tracks')) @mkdir($c['dir'] . '/tracks', 0775, true);
        file_put_contents($c['dir'] . '/' . $rel, (string) $t['vtt'], LOCK_EX);
        $c['db']->update('media_tracks', ['file' => $rel], 'id = :id', ['id' => (int) $t['id']]);
    }

    // ================================================================= WebVTT/SRT lesen, prüfen, schreiben

    /**
     * WebVTT oder SRT einlesen. Liefert [$cues, $fehler]; Cue = ['start' => ms, 'end' => ms, 'text' => '…', 'settings' => '…'].
     * Unbekannte Tags, STYLE/REGION/NOTE-Blöcke und Formatierungen aus SRT ({\an8}, <font>) werden entfernt.
     */
    public static function parse(string $raw): array
    {
        if (strlen($raw) > self::MAX_BYTES) return [[], __('Datei ist zu groß (max. 2 MB).')];
        if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = (string) @mb_convert_encoding(substr($raw, 2), 'UTF-8', str_starts_with($raw, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        }
        if (!mb_check_encoding($raw, 'UTF-8')) $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        if (str_contains($raw, "\0")) return [[], __('Keine gültige Untertitel-Datei.')];
        $blocks = preg_split("~\n[ \t]*\n~", trim($raw)) ?: [];
        $cues = [];
        $time = '(?:(\d{1,3}):)?(\d{1,2}):(\d{1,2})[.,](\d{1,3})';
        foreach ($blocks as $i => $b) {
            $lines = explode("\n", trim($b, "\n"));
            if ($i === 0 && preg_match('~^WEBVTT\b~', $lines[0])) {
                array_shift($lines);
                // Kopfzeilen des Dateikopfs (z. B. „Kind: captions“) überspringen, der erste Cue kann direkt folgen
                while ($lines && !str_contains($lines[0], '-->')) array_shift($lines);
                if (!$lines) continue;
            }
            if (preg_match('~^(NOTE|STYLE|REGION)\b~', $lines[0])) continue;
            // Kennung (VTT) bzw. Nummer (SRT) vor der Zeitzeile
            if (!str_contains($lines[0], '-->') && isset($lines[1]) && str_contains($lines[1], '-->')) array_shift($lines);
            if (!preg_match('~^' . $time . '\s*-->\s*' . $time . '(.*)$~', trim($lines[0]), $mm)) continue;
            $start = self::ms($mm[1], $mm[2], $mm[3], $mm[4]);
            $end = self::ms($mm[5], $mm[6], $mm[7], $mm[8]);
            $text = implode("\n", array_slice($lines, 1));
            $cues[] = ['start' => $start, 'end' => $end, 'text' => $text, 'settings' => $mm[9]];
            if (count($cues) > self::MAX_CUES) return [[], __('Zu viele Einträge (max. {n}).', ['n' => self::MAX_CUES])];
        }
        if (!$cues) return [[], __('Keine Untertitel gefunden – bitte eine WebVTT- (.vtt) oder SubRip-Datei (.srt) wählen.')];
        return [self::cleanCues($cues), null];
    }

    private static function ms(string $h, string $m, string $s, string $f): int
    {
        return (((int) $h * 60 + (int) $m) * 60 + (int) $s) * 1000 + (int) str_pad($f, 3, '0');
    }

    /** Cues prüfen: Zeiten (ms, Ende > Anfang), Text säubern, sortieren; leere Cues entfallen */
    public static function cleanCues(array $in): array
    {
        $out = [];
        foreach (array_slice($in, 0, self::MAX_CUES + 1) as $c) {
            if (!is_array($c)) continue;
            $start = max(0, (int) round((float) ($c['start'] ?? $c[0] ?? 0)));
            $end = (int) round((float) ($c['end'] ?? $c[1] ?? 0));
            $text = self::cleanText((string) ($c['text'] ?? $c[2] ?? ''));
            if ($text === '') continue;
            if ($end <= $start) $end = $start + 1000;
            $out[] = ['start' => min($start, 359999999), 'end' => min($end, 359999999), 'text' => $text, 'settings' => self::cleanSettings((string) ($c['settings'] ?? $c[3] ?? ''))];
        }
        usort($out, fn($a, $b) => $a['start'] <=> $b['start'] ?: $a['end'] <=> $b['end']);
        return $out;
    }

    /** Cue-Text: nur erlaubte WebVTT-Tags, Entities normalisiert, höchstens 500 Zeichen und 4 Zeilen */
    public static function cleanText(string $t): string
    {
        $t = str_replace(["\r\n", "\r"], "\n", $t);
        $t = (string) preg_replace('~\{\\\\[^}]*\}~', '', $t);          // SRT/ASS-Anweisungen wie {\an8}
        $t = str_replace('-->', '→', $t);
        $out = '';
        $open = [];
        foreach (preg_split('~(<[^>\n]{0,120}>)~', $t, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            if ($part[0] === '<' && str_ends_with($part, '>')) {
                if (preg_match('~^<(\d{1,3}:)?\d{1,2}:\d{2}\.\d{3}>$~', $part)) { $out .= $part; continue; }   // Zeitmarke (Karaoke)
                if (preg_match('~^</(b|i|u|c|v|lang|ruby|rt)>$~i', $part, $mm)) {
                    $tag = strtolower($mm[1]);
                    $k = array_search($tag, array_reverse($open, true), true);
                    if ($k !== false) { unset($open[$k]); $open = array_values($open); $out .= '</' . $tag . '>'; }
                    continue;
                }
                if (preg_match('~^<(b|i|u|ruby|rt)>$~i', $part, $mm)) { $open[] = strtolower($mm[1]); $out .= '<' . strtolower($mm[1]) . '>'; continue; }
                if (preg_match('~^<c((?:\.[A-Za-z0-9_-]{1,30}){0,4})>$~', $part, $mm)) { $open[] = 'c'; $out .= '<c' . $mm[1] . '>'; continue; }
                if (preg_match('~^<v(?:\.[A-Za-z0-9_-]{1,30})*\s+([^<>&]{1,60})>$~u', $part, $mm)) { $open[] = 'v'; $out .= '<v ' . trim($mm[1]) . '>'; continue; }
                if (preg_match('~^<lang\s+([a-zA-Z]{2,3}(?:-[A-Za-z0-9]{2,8})?)>$~', $part, $mm)) { $open[] = 'lang'; $out .= '<lang ' . $mm[1] . '>'; continue; }
                continue;   // alles andere (HTML, <font>, <script> …) entfällt
            }
            $plain = html_entity_decode($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $out .= str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $plain);
        }
        foreach (array_reverse($open) as $tag) $out .= '</' . $tag . '>';
        $lines = array_values(array_filter(array_map(fn($l) => trim(preg_replace('~[ \t]+~u', ' ', $l)), explode("\n", $out)), fn($l) => $l !== ''));
        return mb_substr(implode("\n", array_slice($lines, 0, 4)), 0, 500);
    }

    private static function cleanSettings(string $s): string
    {
        $out = [];
        foreach (preg_split('~\s+~', trim($s)) ?: [] as $p) {
            if (preg_match('~^(line|position|size):-?\d{1,3}(\.\d+)?%?(,(start|center|end|line-left|line-right|auto))?$~', $p)
                || preg_match('~^align:(start|center|end|left|right)$~', $p)) $out[] = $p;
        }
        return implode(' ', array_slice($out, 0, 4));
    }

    public static function stamp(int $ms): string
    {
        $h = intdiv($ms, 3600000);
        $m = intdiv($ms % 3600000, 60000);
        $s = intdiv($ms % 60000, 1000);
        return sprintf('%02d:%02d:%02d.%03d', $h, $m, $s, $ms % 1000);
    }

    public static function toVtt(array $cues, string $kind = 'subtitles'): string
    {
        $out = "WEBVTT\n";
        foreach ($cues as $i => $c) {
            $text = $kind === 'chapters' ? self::plain($c['text']) : $c['text'];
            $out .= "\n" . ($i + 1) . "\n" . self::stamp($c['start']) . ' --> ' . self::stamp($c['end']) . ($c['settings'] !== '' ? ' ' . $c['settings'] : '') . "\n" . $text . "\n";
        }
        return $out;
    }

    /** Cues einer Spur für den Editor */
    public static function cues(array $track): array
    {
        return self::parse((string) ($track['vtt'] ?? ''))[0];
    }

    /** Sichtbarer Text eines Cues (ohne Tags) */
    public static function plain(string $t): string
    {
        $t = (string) preg_replace('~<v\s+([^>]+)>~u', '$1: ', $t);
        return trim(html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // ================================================================= Transkripte

    /** ['de' => ['text' => …, 'status' => 'draft'|'published', 'source' => …, 'updated_at' => …], …] */
    public static function transcripts(array $m): array
    {
        $t = json_decode((string) ($m['transcripts'] ?? ''), true);
        return is_array($t) ? $t : [];
    }

    public static function setTranscript(array $m, string $lang, string $text, string $status = 'published', string $source = 'editor'): array
    {
        if (!self::validLang($lang)) throw new \InvalidArgumentException(__('Ungültige Sprache.'));
        $c = self::ctx($m);
        $row = $c['db']->fetch('SELECT transcripts FROM media WHERE id = ?', [$c['id']]);
        $all = self::transcripts($row ?: []);
        $text = trim(mb_substr(str_replace(["\r\n", "\r"], "\n", strip_tags($text)), 0, 200000));
        if ($text === '') {
            unset($all[$lang]);
        } else {
            $all[$lang] = ['text' => $text, 'status' => $status === 'draft' ? 'draft' : 'published', 'source' => $source, 'updated_at' => now()];
        }
        $c['db']->update('media', ['transcripts' => $all ? json_encode($all, JSON_UNESCAPED_UNICODE) : null, 'updated_at' => now()], 'id = :id', ['id' => $c['id']]);
        Media::forget((int) $m['id']);
        MediaPools::forget();
        PageCache::clear();
        return $all;
    }

    /** Fließtext aus Cues: Sätze zusammenfügen, nach längeren Pausen (≥ 2 s) neuer Absatz */
    public static function transcriptFromCues(array $cues): string
    {
        $paras = [];
        $cur = '';
        $last = null;
        foreach ($cues as $c) {
            $t = preg_replace('~\s+~u', ' ', self::plain($c['text']));
            if ($t === '') continue;
            if ($last !== null && $c['start'] - $last >= 2000 && $cur !== '') { $paras[] = trim($cur); $cur = ''; }
            $cur .= ' ' . $t;
            $last = $c['end'];
        }
        if (trim($cur) !== '') $paras[] = trim($cur);
        return implode("\n\n", $paras);
    }

    /** Veröffentlichter Transkript-Text in einer Sprache (für Suche und Ausgabe), Rückfall Standardsprache */
    public static function transcriptText(array $m, ?string $lang = null, bool $fallback = true): string
    {
        $all = array_filter(self::transcripts($m), fn($t) => ($t['status'] ?? '') === 'published' && trim((string) ($t['text'] ?? '')) !== '');
        $lang ??= Lang::current();
        $t = $all[$lang] ?? ($fallback ? ($all[Lang::default()] ?? (reset($all) ?: null)) : null);
        return is_array($t) ? (string) $t['text'] : '';
    }

    // ================================================================= Ausgabe auf der Website

    /** <track>-Elemente für ein <video>/<audio>: nur veröffentlichte Spuren; Standard = Sprache der Seite */
    public static function trackTags(?array $m): string
    {
        if (!$m || !self::enabled() || !self::supports($m)) return '';
        $cur = Lang::current();
        $out = '';
        $default = false;
        foreach (self::forMedia($m, true) as $t) {
            $url = self::url($m, $t);
            if (!$url) continue;
            $isDef = !$default && $t['lang'] === $cur && in_array($t['kind'], ['subtitles', 'captions'], true);
            $default = $default || $isDef;
            $out .= '<track kind="' . e($t['kind']) . '" src="' . e($url) . '" srclang="' . e($t['lang']) . '" label="'
                . e($t['label'] !== '' ? $t['label'] : self::defaultLabel($t['kind'], $t['lang'])) . '"' . ($isDef ? ' default' : '') . '>';
        }
        return $out;
    }

    /** „Transkript anzeigen“ (<details>) – nur mit veröffentlichtem Transkript; Sprache der Seite, sonst Standardsprache */
    public static function transcriptHtml(?array $m, string $class = 'media-transcript'): string
    {
        if (!$m || !self::enabled() || !self::supports($m)) return '';
        $all = array_filter(self::transcripts($m), fn($t) => ($t['status'] ?? '') === 'published' && trim((string) ($t['text'] ?? '')) !== '');
        if (!$all) return '';
        $cur = Lang::current();
        $lang = isset($all[$cur]) ? $cur : (isset($all[Lang::default()]) ? Lang::default() : (string) array_key_first($all));
        $paras = '';
        foreach (preg_split("~\n\s*\n~", (string) $all[$lang]['text']) ?: [] as $p) {
            if (trim($p) !== '') $paras .= '<p>' . nl2br(e(trim($p)), false) . '</p>';
        }
        $title = Media::title($m);
        return '<details class="' . e($class) . '"><summary>' . e(lt('Transkript anzeigen'))
            . ($title !== '' ? '<span class="' . e($class) . '__for">: ' . e($title) . '</span>' : '') . '</summary>'
            . '<div class="' . e($class) . '__text"' . ($lang !== $cur ? ' lang="' . e($lang) . '"' : '') . '>' . $paras . '</div></details>';
    }

    /**
     * Video bzw. Audio mit Textspuren und Transkript (z. B. Datei-Felder von Einträgen).
     * Dekorative Videos (media.decorative) laufen ohne Bedienelemente als stumme Schleife – siehe decorativeVideo();
     * $opt['controls'] = true erzwingt trotzdem den normalen Player.
     */
    public static function player(array $m, array $opt = []): string
    {
        if (self::isDecorative($m) && empty($opt['controls'])) return self::decorativeVideo($m, $opt);
        $isAudio = str_starts_with((string) $m['mime'], 'audio/');
        $tag = $isAudio ? 'audio' : 'video';
        $title = Media::title($m);
        return '<figure class="' . e($opt['class'] ?? 'media-player') . '"><' . $tag . ' controls preload="' . ($isAudio ? 'metadata' : 'none') . '"'
            . ($isAudio ? '' : ' playsinline') . ($title !== '' ? ' aria-label="' . e($title) . '"' : '')
            . (!$isAudio && ($poster = Media::posterFor($m)) ? ' poster="' . e(Media::url($poster, 1200)) . '"' : '') . '>'
            . '<source src="' . e(Media::url($m)) . '" type="' . e((string) $m['mime']) . '">' . self::trackTags($m) . '</' . $tag . '>'
            . ($title !== '' && !empty($opt['caption']) ? '<figcaption>' . e($title) . '</figcaption>' : '')
            . self::transcriptHtml($m) . '</figure>';
    }

    /** Dekoratives Video (Hintergrund-/Stimmungsvideo ohne Informationsgehalt): keine Untertitel nötig, für Screenreader ausgeblendet */
    public static function isDecorative(?array $m): bool
    {
        return $m && !empty($m['decorative']) && str_starts_with((string) ($m['mime'] ?? ''), 'video/');
    }

    /**
     * Dekoratives Video: <video muted loop playsinline aria-hidden="true" tabindex="-1"> ohne Bedienelemente, ohne Textspuren.
     * Abgespielt wird nur mit Skript, sichtbar und ohne „Bewegung reduzieren“ (public/assets/js/hero.mjs); dann erscheint eine
     * beschriftete Pause-Schaltfläche (WCAG 2.2.2). Ohne Skript bleibt das Standbild (Poster) stehen.
     * $opt: class (Rahmen, Standard „media-player“; dazu immer „media-player--decorative“)
     */
    public static function decorativeVideo(array $m, array $opt = []): string
    {
        $poster = Media::posterFor($m);
        return '<figure class="' . e($opt['class'] ?? 'media-player') . ' media-player--decorative" data-hero-video-box>'
            . '<video data-hero-video muted loop playsinline preload="none" disablepictureinpicture aria-hidden="true" tabindex="-1"'
            . ($poster ? ' poster="' . e(Media::url($poster, 1200)) . '"' : '') . '>'
            . '<source src="' . e(Media::url($m)) . '" type="' . e((string) $m['mime']) . '"></video>'
            . '<button type="button" class="hx-toggle" data-hero-video-toggle hidden>'
            . '<span data-l-pause="' . e(lt('Hintergrundvideo anhalten')) . '" data-l-play="' . e(lt('Hintergrundvideo abspielen')) . '">'
            . e(lt('Hintergrundvideo anhalten')) . '</span></button></figure>' . \Core\Blocks\Hero::script();
    }

    // ================================================================= Verwaltung (JSON)

    public static function trackJson(array $m, array $t, bool $cues = false): array
    {
        $out = [
            'id' => (int) $t['id'], 'kind' => $t['kind'], 'lang' => $t['lang'], 'label' => (string) $t['label'],
            'display' => (string) $t['label'] !== '' ? (string) $t['label'] : self::defaultLabel($t['kind'], $t['lang']),
            'status' => $t['status'], 'source' => $t['source'], 'note' => (string) ($t['note'] ?? ''), 'cue_count' => (int) $t['cues'],
            'url' => self::url($m, $t), 'updated_at' => $t['updated_at'], 'reviewed_at' => $t['reviewed_at'] ?? null,
        ];
        // Editor: „&“ und geschützte Leerzeichen lesbar; Tags bleiben (werden beim Speichern erneut geprüft)
        if ($cues) $out['cues'] = array_map(fn($c) => ['start' => $c['start'], 'end' => $c['end'], 'settings' => $c['settings'],
            'text' => str_replace(['&amp;', '&nbsp;'], ['&', "\u{00A0}"], $c['text'])], self::cues($t));
        return $out;
    }

    /** Kurzfassung für Media::toJson (Liste): Anzahl veröffentlichter/Entwurfs-Spuren und Transkript-Sprachen */
    public static function summary(array $m): array
    {
        $pub = $draft = 0;
        $langs = [];
        foreach (self::forMedia($m) as $t) {
            $t['status'] === 'published' ? $pub++ : $draft++;
            if ($t['status'] === 'published' && in_array($t['kind'], ['subtitles', 'captions'], true)) $langs[] = $t['lang'];
        }
        $tr = self::transcripts($m);
        return ['published' => $pub, 'drafts' => $draft, 'caption_langs' => array_values(array_unique($langs)),
            'transcripts' => array_keys(array_filter($tr, fn($x) => ($x['status'] ?? '') === 'published')),
            'transcript_drafts' => array_keys(array_filter($tr, fn($x) => ($x['status'] ?? '') === 'draft'))];
    }
}
