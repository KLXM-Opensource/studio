<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

use Core\Extensions;
use Core\Features;
use Core\Media;

/** Einstieg der Erweiterung: Zustand, Rechte und die Haken für Mediathek, Themes und Betrieb. */
final class VideoTools
{
    public const FEATURE = 'video.tools';
    public const PERM = 'video.tools';

    /** Erweiterung aktiv und Funktion für diese Website eingeschaltet */
    public static function on(): bool
    {
        return Extensions::isActive('video_tools') && Features::on(self::FEATURE) && Repo::ready();
    }

    public static function allowed(): bool
    {
        return self::on() && can(self::PERM);
    }

    /** Originale ersetzen/löschen braucht zusätzlich „Medien löschen“ */
    public static function canReplace(): bool
    {
        return self::allowed() && can('media.delete');
    }

    /** Befehlsvorschau und Protokolle: nur Administration */
    public static function isAdmin(): bool
    {
        return can('system.manage');
    }

    public static function asset(string $path): string
    {
        $x = Extensions::active()['video_tools'] ?? null;
        return $x ? $x->asset($path) : base_path() . '/extensions/video_tools/' . $path;
    }

    /** Symbol aus dem eigenen Sprite (Phosphor duotone, MIT) für Symbole, die der Kern nicht mitbringt */
    public static function icon(string $name, string $class = ''): string
    {
        return '<svg class="ico' . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true" focusable="false" width="1em" height="1em" fill="currentColor"><use href="'
            . e(self::asset('img/icons.svg')) . '#i-' . e($name) . '"/></svg>';
    }

    // ------------------------------------------------------------------ Haken

    /** Extension::mediaJson – kurze Angaben je Video für die Liste (ohne Analyse-Lauf) */
    public static function mediaJson(array $m): ?array
    {
        if (!str_starts_with((string) $m['mime'], 'video/') || !empty($m['_pool']) || !empty($m['pool_ref']) || !self::on()) return null;
        $row = Repo::allMeta()[(int) $m['id']] ?? null;
        $poster = !empty($row['poster_id']) ? Media::find((int) $row['poster_id']) : null;
        return [
            'score' => $row && $row['score'] !== null ? (int) $row['score'] : null,
            'optimized' => $row ? (bool) $row['optimized'] : null,
            'faststart' => $row && $row['faststart'] !== null ? (bool) $row['faststart'] : null,
            'poster' => $poster ? Media::url($poster, 480) : null,
            'preview' => !empty($row['preview']) && Ffmpeg::config()['preview'] ? site()->mediaUrl((string) $row['preview']) : null,
            'job' => Jobs::openByMedia()[(int) $m['id']] ?? null,
        ];
    }

    /** Extension::mediaChecks – „Videos nicht optimiert“, „Videos ohne Poster“ (nur Mediathek der Website) */
    public static function checks(): array
    {
        if (!self::on() || Media::pool() !== null) return [];
        Repo::ensureAll(40);
        return [
            'video_unoptimized' => ['label' => __('Videos nicht optimiert'), 'icon' => 'gauge', 'kind' => 'video',
                'where' => "m.mime LIKE 'video/%' AND m.id NOT IN (SELECT media_id FROM video_meta WHERE optimized = 1)"],
            'video_noposter' => ['label' => __('Videos ohne Poster'), 'icon' => 'image', 'kind' => 'video',
                'where' => "m.mime LIKE 'video/%' AND m.id NOT IN (SELECT media_id FROM video_meta WHERE poster_id IS NOT NULL)"],
        ];
    }

    /** Extension::mediaPoster – Poster eines Videos für Themes und Player */
    public static function poster(array $m): ?int
    {
        if (!self::on() || !empty($m['_pool'])) return null;
        return Repo::posterId($m);
    }

    /** Extension::health */
    public static function health(): array
    {
        if (!Features::on(self::FEATURE, false)) return [];
        $out = [];
        $out[__('Video-Werkzeuge: proc_open erlaubt')] = Ffmpeg::canExec() ? true : null;
        foreach (['ffmpeg', 'ffprobe'] as $b) {
            $label = Ffmpeg::bin($b) !== '' ? __('Video-Werkzeuge: {bin} gefunden', ['bin' => $b]) . ' (' . Ffmpeg::bin($b) . ')'
                : (Ffmpeg::misconfigured($b) ? __('Video-Werkzeuge: {bin} – konfigurierter Pfad ungültig ({path})', ['bin' => $b, 'path' => Ffmpeg::configuredPath($b)])
                : __('Video-Werkzeuge: {bin} nicht gefunden – nur Analyse aus PHP (siehe Verwaltung → Video-Werkzeuge)', ['bin' => $b]));
            // Fehlt ffmpeg, arbeitet die Erweiterung eingeschränkt weiter (nur Analyse aus PHP) → Warnung, kein Deploy-Fehler.
            // Ein konfigurierter, aber falscher Pfad ist dagegen ein Fehler.
            $out[$label] = Ffmpeg::bin($b) !== '' ? true : (Ffmpeg::misconfigured($b) ? false : null);
        }
        if (Repo::ready()) {
            $stuck = (int) app()->db->fetchValue("SELECT COUNT(*) FROM video_jobs WHERE status = 'queued' AND created_at < ?", [date('Y-m-d H:i:s', time() - 3600)]);
            $out[$stuck ? __('Video-Werkzeuge: {n} Auftrag/Aufträge warten seit über einer Stunde (Cron video:work?)', ['n' => $stuck]) : __('Video-Werkzeuge: Warteschlange in Ordnung')] = $stuck ? null : true;
        }
        return $out;
    }

    /** Für die Statusseite: Programme, Versionen, Grenzen, Hinweise */
    public static function status(): array
    {
        $cfg = Ffmpeg::config();
        $free = @disk_free_space(Jobs::dir('tmp'));
        return [
            'available' => Ffmpeg::available(), 'exec' => Ffmpeg::canExec(),
            'ffmpeg' => Ffmpeg::bin('ffmpeg'), 'ffprobe' => Ffmpeg::bin('ffprobe'),
            'ffmpeg_bad' => Ffmpeg::misconfigured('ffmpeg'), 'ffprobe_bad' => Ffmpeg::misconfigured('ffprobe'),
            'ffmpeg_cfg' => Ffmpeg::configuredPath('ffmpeg'), 'ffprobe_cfg' => Ffmpeg::configuredPath('ffprobe'),
            'version' => Ffmpeg::version(), 'webm' => Presets::all()['webm']['available'], 'x264' => Ffmpeg::hasEncoder('libx264'),
            'config' => $cfg, 'free_mb' => $free !== false ? (int) ($free / 1048576) : null,
            'max_upload_mb' => (int) app()->config->get('media.max_upload_mb', 50),
            'nice' => \Core\Ffmpeg::hasNice(),
            'hint' => Ffmpeg::available() ? '' : \Core\Ffmpeg::hint(), 'open_basedir' => \Core\Ffmpeg::openBasedir(),
            'php' => \Core\Network\Stats::phpBinary(),
        ];
    }
}
