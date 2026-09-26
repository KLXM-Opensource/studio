<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

use Core\MediaTracks;

/** Untertitel/Kapitel (Core\MediaTracks) auf neue Dateien übertragen: 1:1 (gleiche Zeitachse) oder zugeschnitten + verschoben (Ausschnitt). */
final class Vtt
{
    /**
     * Cues auf das Fenster [$fromMs, $toMs) zuschneiden und um $fromMs nach vorn schieben. $offsetMs verschiebt zusätzlich
     * (verlustfreier Schnitt beginnt am Keyframe davor: Video startet früher → Untertitel entsprechend später).
     */
    public static function cut(array $cues, int $fromMs, int $toMs, int $offsetMs = 0): array
    {
        $out = [];
        foreach ($cues as $c) {
            $s = (int) $c['start'];
            $e = (int) $c['end'];
            if ($e <= $fromMs || $s >= $toMs) continue;
            $ns = max($s, $fromMs) - $fromMs + $offsetMs;
            $ne = min($e, $toMs) - $fromMs + $offsetMs;
            if ($ne - $ns < 200) continue;   // Reste unter 0,2 s sind nicht lesbar
            $out[] = ['start' => max(0, $ns), 'end' => $ne, 'text' => $c['text'], 'settings' => $c['settings'] ?? ''];
        }
        return $out;
    }

    /**
     * Alle Spuren (und Transkripte) von $src auf $dst übertragen. $window = [from, to, offset] für Ausschnitte, null = unverändert.
     * @return int Anzahl übertragener Spuren
     */
    public static function copy(array $src, array $dst, ?array $window = null, ?int $userId = null): int
    {
        if (!MediaTracks::supports($src) || !MediaTracks::supports($dst)) return 0;
        $n = 0;
        foreach (MediaTracks::forMedia($src, false, true) as $t) {
            $cues = MediaTracks::cues($t);
            if ($window) $cues = self::cut($cues, $window[0], $window[1], $window[2] ?? 0);
            if (!$cues) continue;
            try {
                MediaTracks::save($dst, ['kind' => $t['kind'], 'lang' => $t['lang'], 'label' => (string) $t['label'], 'cues' => $cues,
                    'status' => $t['status'], 'source' => $t['source'] === 'upload' ? 'editor' : $t['source'],
                    'note' => $window ? __('Aus „{name}“ übernommen und zugeschnitten', ['name' => \Core\Media::displayName($src)]) : (string) ($t['note'] ?? '')], null, $userId);
                $n++;
            } catch (\InvalidArgumentException $e) {
                error_log('[video_tools] Untertitel: ' . $e->getMessage());
            }
        }
        // Transkripte: nur bei unveränderter Zeitachse vollständig übernehmen; bei Ausschnitten aus den zugeschnittenen Untertiteln
        foreach (MediaTracks::transcripts($src) as $lang => $tr) {
            if (!$window) {
                MediaTracks::setTranscript($dst, (string) $lang, (string) $tr['text'], (string) ($tr['status'] ?? 'draft'), (string) ($tr['source'] ?? 'editor'));
                continue;
            }
            foreach (MediaTracks::forMedia($dst, false, true) as $t) {
                if ($t['lang'] === $lang && in_array($t['kind'], ['subtitles', 'captions'], true)) {
                    MediaTracks::setTranscript($dst, (string) $lang, MediaTracks::transcriptFromCues(MediaTracks::cues($t)), 'draft', 'editor');
                    break;
                }
            }
        }
        return $n;
    }
}
