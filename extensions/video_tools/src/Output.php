<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

use Core\Media;

/**
 * Ergebnis eines Auftrags in die Mediathek übernehmen:
 *   optimize  neue Datei („Version von …“) mit allen Angaben des Originals – oder Datei des Originals ersetzen (ID bleibt)
 *   trim      neue Datei „… (Ausschnitt 00:12–00:34)“, Untertitel zugeschnitten und verschoben
 *   poster    Bild (dekorativ) + Verknüpfung als Vorschaubild des Videos
 *   preview   stumme Schleife für das Raster der Mediathek (keine eigene Mediendatei)
 */
final class Output
{
    /** @return array{0: ?int, 1: string, 2: ?int} [Ergebnis-ID, Meldung, Bytes] */
    public static function store(array $j, array $m, string $file, array $info, array $p): array
    {
        $uid = $j['user_id'] ? (int) $j['user_id'] : null;
        return match ($j['type']) {
            'optimize' => self::optimized($j, $m, $file, $p, $uid),
            'trim' => self::clip($m, $file, $p, $uid),
            'poster' => self::poster($m, $file, (int) ($p['at'] ?? 0)),
            'preview' => self::preview($m, $file),
        };
    }

    private static function check(string $file): void
    {
        $info = Ffmpeg::probe($file);
        if (!$info || !Analyzer::video($info)) throw new JobException(__('Das Ergebnis ist kein gültiges Video.'));
    }

    private static function baseName(array $m): string
    {
        return preg_replace('~\.[a-z0-9]{2,4}$~i', '', (string) $m['original_name']) ?: 'video';
    }

    private static function optimized(array $j, array $m, string $file, array $p, ?int $uid): array
    {
        self::check($file);
        $preset = (string) $j['preset'];
        [$ext] = Presets::output($preset);
        $bytes = (int) filesize($file);
        $before = (int) $m['size'];
        if ($j['mode'] === 'replace') {
            [$r, $err] = Media::replace((int) $m['id'], $file, self::baseName($m) . '.' . $ext);
            if (!$r) throw new JobException((string) $err);
            Repo::link((int) $m['id'], (int) $m['id'], 'optimized', $preset, Presets::label($preset));
            return [(int) $r['id'], __('Ersetzt ({preset}): {saved}.', ['preset' => Presets::label($preset), 'saved' => self::saved($before, $bytes)]), $bytes];
        }
        $label = Presets::label($preset);
        $new = self::import($m, $file, self::baseName($m) . '-' . $preset . '.' . $ext, trim(Media::displayName($m) . ' (' . $label . ')'));
        Vtt::copy($m, $new, null, $uid);
        Repo::link((int) $new['id'], (int) $m['id'], 'version', $preset, $label);
        $msg = __('Neue Version „{name}“: {saved}.', ['name' => Media::displayName($new), 'saved' => self::saved($before, $bytes)]);
        if (!empty($p['delete_original'])) {
            if (Media::usages((int) $m['id'])) {
                $msg .= ' ' . __('Original bleibt – es wird noch verwendet.');
            } else {
                Media::delete((int) $m['id']);
                $msg .= ' ' . __('Original gelöscht.');
            }
        }
        return [(int) $new['id'], $msg, $bytes];
    }

    private static function clip(array $m, string $file, array $p, ?int $uid): array
    {
        self::check($file);
        $from = (int) $p['from'];
        $to = (int) $p['to'];
        $offset = 0;
        $winFrom = $from;
        if (empty($p['precise'])) {
            // Verlustfrei beginnt der Ausschnitt am Keyframe K vor dem Anfang: der gewünschte Anfang liegt in der neuen Datei bei
            // (Anfang − K) + Startzeit der Videospur → Untertitel entsprechend später. Rückfall: Differenz der Dauer.
            $out = Ffmpeg::probe($file);
            $k = Ffmpeg::keyframeBefore(Repo::path($m), $from);
            $vs = (float) (Analyzer::video($out ?? [])['start'] ?? 0);
            if ($k !== null) {
                $offset = ($from - $k) + (int) round($vs * 1000);
                // Die Datei enthält schon ab K Bild und Ton – Untertitel ab K übernehmen (nur um die Startzeit der Videospur verschoben)
                $winFrom = $k;
            } elseif (($d = (float) ($out['duration'] ?? 0)) > 0) {
                $offset = (int) round($d * 1000) - ($to - $from);
            }
            $offset = max(0, min(15000, $offset));
        }
        $title = __('{name} (Ausschnitt {from}–{to})', ['name' => Media::displayName($m), 'from' => Jobs::clock($from), 'to' => Jobs::clock($to)]);
        $new = self::import($m, $file, self::baseName($m) . '-' . sprintf('%06d-%06d', intdiv($from, 100), intdiv($to, 100)) . '.mp4', $title);
        $n = Vtt::copy($m, $new, [$winFrom, $to, $offset - ($from - $winFrom)], $uid);
        Repo::link((int) $new['id'], (int) $m['id'], 'clip', null, Jobs::clock($from) . '–' . Jobs::clock($to));
        $msg = __('Ausschnitt „{name}“ angelegt ({size}).', ['name' => Media::displayName($new), 'size' => Media::humanSize((int) $new['size'])]);
        if ($n) $msg .= ' ' . __('{n} Untertitel-Spur(en) zugeschnitten.', ['n' => $n]);
        if ($offset > 50) $msg .= ' ' . __('Beginnt {s} s früher (Keyframe).', ['s' => \Core\Format::admin()->number($offset / 1000, 1)]);
        return [(int) $new['id'], $msg, (int) $new['size']];
    }

    /** Neue Mediendatei mit den Angaben des Originals (Beschreibung, Übersetzungen, Fotonachweis, Tags, Sammlungen, Transkripte) */
    private static function import(array $m, string $file, string $name, string $title): array
    {
        [$new, $err] = Media::import($file, $name, (string) $m['alt'], [
            'title' => mb_substr($title, 0, 180), 'credit' => (string) ($m['credit'] ?? ''), 'tags' => Media::tagList($m['tags'] ?? ''), 'require_alt' => false,
        ]);
        if (!$new) throw new JobException((string) $err);
        $id = (int) $new['id'];
        if (!empty($m['i18n'])) {
            $i18n = Media::translations($m);
            foreach ($i18n as $l => &$row) if (isset($row['title'])) $row['title'] = mb_substr($row['title'] . ' ' . mb_substr($title, mb_strlen(Media::displayName($m))), 0, 180);
            Media::db()->update('media', ['i18n' => json_encode($i18n, JSON_UNESCAPED_UNICODE)], 'id = :id', ['id' => $id]);
        }
        foreach (Media::collectionIds((int) $m['id']) as $cid) Media::addToCollection($cid, [$id]);
        Media::forget($id);
        return Media::find($id);
    }

    private static function poster(array $m, string $file, int $at): array
    {
        if (!@getimagesize($file)) throw new JobException(__('Das Standbild ist ungültig.'));
        return self::storePoster($m, $file, $at);
    }

    /** Poster-Bild übernehmen (auch für Standbilder aus dem Browser, wenn ffmpeg fehlt) */
    public static function storePoster(array $m, string $file, ?int $at): array
    {
        [$img, $err] = Media::import($file, self::baseName($m) . '-poster.jpg', '', [
            'decorative' => true, 'title' => mb_substr(__('Poster: {name}', ['name' => Media::displayName($m)]), 0, 180), 'tags' => ['poster'],
        ]);
        if (!$img) throw new JobException((string) $err);
        Repo::setPoster((int) $m['id'], (int) $img['id'], $at);
        return [(int) $img['id'], __('Poster gesetzt ({at}).', ['at' => $at !== null ? Jobs::clock($at) : __('Standbild')]), (int) $img['size']];
    }

    private static function preview(array $m, string $file): array
    {
        $rel = 'cache/vt-' . bin2hex(random_bytes(6)) . '-preview.mp4';
        $dir = rtrim(Media::dir(), '/');
        if (!is_dir("$dir/cache")) @mkdir("$dir/cache", 0775, true);
        if (!@rename($file, "$dir/$rel") && !@copy($file, "$dir/$rel")) throw new JobException(__('Vorschau konnte nicht gespeichert werden.'));
        Repo::meta($m);
        $old = app()->db->fetchValue('SELECT preview FROM video_meta WHERE media_id = ?', [(int) $m['id']]);
        if ($old) @unlink("$dir/$old");
        app()->db->update('video_meta', ['preview' => $rel], 'media_id = :id', ['id' => (int) $m['id']]);
        return [null, __('Animierte Vorschau erzeugt.'), (int) filesize("$dir/$rel")];
    }

    /** „12,3 MB gespart (−45 %)“ bzw. „0,4 MB größer“ */
    public static function saved(int $before, int $after): string
    {
        if ($before <= 0) return Media::humanSize($after);
        $d = $before - $after;
        $pct = (int) round(abs($d) / $before * 100);
        if (abs($d) < max(4096, $before * 0.005)) return __('Größe unverändert ({mb})', ['mb' => Media::humanSize($after)]);
        return $d >= 0 ? __('{mb} gespart (−{pct} %)', ['mb' => Media::humanSize($d), 'pct' => $pct])
            : __('{mb} größer (+{pct} %)', ['mb' => Media::humanSize(-$d), 'pct' => $pct]);
    }
}
