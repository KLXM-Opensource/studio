<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\VideoTools;

use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Media;
use Core\Theme;

/**
 * Verwaltung → Medien → Video-Werkzeuge: Statusseite + JSON-API für die Mediathek (resources: assets/js/video-tools.js).
 * Recht video.tools; Original ersetzen/löschen zusätzlich media.delete; Befehlsvorschau und Protokoll nur Administration.
 */
final class AdminController extends \Core\Http\Controllers\Admin\AdminController
{
    private function guard(Request $r): array
    {
        if (!\Core\Extensions::isActive('video_tools') || !\Core\Features::on(VideoTools::FEATURE)) throw new HttpException(404);
        $u = $this->auth($r, VideoTools::PERM);
        if (!Repo::ready()) throw new HttpException(503, __('Die Tabellen der Erweiterung werden noch angelegt. Bitte neu laden.'));
        Media::usePool(null);
        return $u;
    }

    private function video(string $id): array
    {
        return Repo::video((int) $id) ?? throw new HttpException(404, __('Video nicht gefunden (nur Videos der Mediathek dieser Website).'));
    }

    private static function fail(\Throwable $e, int $status = 422): Response
    {
        return Response::json(['ok' => false, 'error' => $e->getMessage()], $status);
    }

    // ------------------------------------------------------------------ Statusseite

    public function index(Request $r): Response
    {
        $this->guard($r);
        if (random_int(1, 10) === 1) { Jobs::cleanTmp(); Jobs::purge(); }
        $vars = ['title' => __('Video-Werkzeuge'), 'status' => VideoTools::status(), 'jobs' => array_values(array_filter(Jobs::list([], 80), fn($j) => $j['type'] !== 'preview' || $j['status'] !== 'done')), 'presets' => Presets::all(),
            'admin' => VideoTools::isAdmin(), 'flash' => app()->session->takeFlash(), 'user' => app()->auth->user(), 'css' => []];
        $content = Theme::capture(dirname(__DIR__) . '/views/status.php', $vars);
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => 'media/video-tools']);
        return self::secure(new Response($html));
    }

    // ------------------------------------------------------------------ Datei

    /** Analyse, Empfehlungen, Presets, Herkunft/Versionen, Aufträge, Poster, Fähigkeiten des Servers */
    public function info(Request $r, string $id): Response
    {
        $this->guard($r);
        $m = $this->video($id);
        $meta = Repo::meta($m, $r->str('refresh') === '1');
        $posterId = Repo::posterId($m);
        $poster = $posterId ? Media::find($posterId) : null;
        $admin = VideoTools::isAdmin();
        $presets = Presets::all();
        if ($admin && Ffmpeg::available()) {
            foreach ($presets as $k => &$p) {
                try { $p['command'] = Runner::display(Presets::optimize($k, 'eingabe', 'ausgabe.' . Presets::output($k)[0], $meta['info'])); } catch (\InvalidArgumentException) { $p['available'] = false; $p['command'] = null; }
            }
            unset($p);
        }
        $row = $meta['row'];
        return Response::json([
            'ok' => true, 'id' => (int) $m['id'], 'display' => Media::displayName($m), 'url' => Media::url($m), 'bytes' => (int) $m['size'],
            'stream' => url('/admin/api/video-tools/media/' . (int) $m['id'] . '/stream') . '?v=' . substr(md5((string) $m['file'] . $m['size']), 0, 8),
            'info' => $meta['info'], 'loud' => $meta['loud'], 'assess' => $meta['assess'],
            'poster' => $poster ? ['id' => (int) $poster['id'], 'thumb' => Media::url($poster, 480), 'at' => $row['poster_at'] !== null ? (int) $row['poster_at'] : null] : null,
            'preview' => !empty($row['preview']) && Ffmpeg::config()['preview'] ? site()->mediaUrl((string) $row['preview']) : null,
            'presets' => array_values($presets), 'relations' => Repo::relations((int) $m['id']), 'jobs' => Jobs::list(['media' => (int) $m['id']], 10),
            'captions' => count(\Core\MediaTracks::forMedia($m)),
            'can' => ['process' => Ffmpeg::available(), 'replace' => VideoTools::canReplace(), 'admin' => $admin, 'loudness' => Ffmpeg::available(),
                'status' => url('/admin/video-tools')],
        ]);
    }

    /**
     * Video für Schneiden/Poster mit HTTP-Range ausliefern: exaktes Spulen auch auf Servern, die statische Dateien ohne
     * Range-Unterstützung ausliefern (z. B. PHP-Entwicklungsserver). Nur angemeldet mit Recht video.tools.
     */
    public function stream(Request $r, string $id): Response
    {
        $this->guard($r);
        $m = $this->video($id);
        $file = Repo::path($m);
        $size = (int) @filesize($file);
        if (!$size) throw new HttpException(404);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();   // lange Übertragung sperrt die Sitzung nicht
        $start = 0;
        $end = $size - 1;
        $status = 200;
        if (preg_match('~^bytes=(\d*)-(\d*)$~', (string) ($r->server['HTTP_RANGE'] ?? ''), $mm) && ($mm[1] !== '' || $mm[2] !== '')) {
            if ($mm[1] === '') { $start = max(0, $size - (int) $mm[2]); }
            else { $start = (int) $mm[1]; if ($mm[2] !== '') $end = min($end, (int) $mm[2]); }
            if ($start > $end || $start >= $size) {
                header('Content-Range: bytes */' . $size, true, 416);
                return Response::alreadySent();
            }
            $status = 206;
        }
        http_response_code($status);
        header('Content-Type: ' . $m['mime']);
        header('Accept-Ranges: bytes');
        header('Content-Length: ' . ($end - $start + 1));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        if ($status === 206) header("Content-Range: bytes $start-$end/$size");
        $fh = fopen($file, 'rb');
        fseek($fh, $start);
        $left = $end - $start + 1;
        while ($left > 0 && !feof($fh) && !connection_aborted()) {
            $chunk = fread($fh, (int) min(262144, $left));
            if ($chunk === false || $chunk === '') break;
            echo $chunk;
            $left -= strlen($chunk);
            flush();
        }
        fclose($fh);
        return Response::alreadySent();
    }

    public function loudness(Request $r, string $id): Response
    {
        $this->guard($r);
        $m = $this->video($id);
        if (!Ffmpeg::available()) return Response::json(['ok' => false, 'error' => __('ffmpeg ist auf diesem Server nicht verfügbar.')], 422);
        if (!Analyzer::audio(Repo::meta($m)['info'])) return Response::json(['ok' => false, 'error' => __('Das Video hat keine Tonspur.')], 422);
        @set_time_limit(360);
        $l = Ffmpeg::loudness(Repo::path($m), 300);
        if (!$l) return Response::json(['ok' => false, 'error' => __('Lautheit konnte nicht gemessen werden.')], 422);
        Repo::setLoudness($m, $l);
        return $this->info($r, $id);
    }

    public function keyframe(Request $r, string $id): Response
    {
        $this->guard($r);
        $m = $this->video($id);
        $at = max(0, (int) $r->str('at'));
        return Response::json(['ok' => true, 'at' => $at, 'keyframe' => Ffmpeg::keyframeBefore(Repo::path($m), $at)]);
    }

    public function optimize(Request $r, string $id): Response
    {
        $this->guard($r);
        $m = $this->video($id);
        $mode = (string) ($r->post['mode'] ?? 'new');
        $del = !empty($r->post['delete_original']);
        if (($mode === 'replace' || $del) && !VideoTools::canReplace()) {
            return Response::json(['ok' => false, 'error' => __('Original ersetzen oder löschen braucht das Recht „Medien löschen“.')], 403);
        }
        try {
            $j = Jobs::create('optimize', $m, ['delete_original' => $del], (string) ($r->post['preset'] ?? ''), $mode);
        } catch (JobException $e) {
            return self::fail($e);
        }
        return Response::json(['ok' => true, 'job' => $j]);
    }

    /** Mehrere Ausschnitte auf einmal: {clips: [{from, to}], precise} */
    public function trim(Request $r, string $id): Response
    {
        $this->guard($r);
        $m = $this->video($id);
        $clips = array_slice(array_values(array_filter((array) ($r->post['clips'] ?? []), 'is_array')), 0, 20);
        if (!$clips) return Response::json(['ok' => false, 'error' => __('Bitte mindestens einen Ausschnitt festlegen.')], 422);
        $batch = 'trim-' . bin2hex(random_bytes(4));
        $jobs = [];
        try {
            foreach ($clips as $i => $c) {
                $jobs[] = Jobs::create('trim', $m, ['from' => (int) ($c['from'] ?? 0), 'to' => (int) ($c['to'] ?? 0), 'precise' => !empty($r->post['precise'])],
                    null, 'new', $batch, $i === count($clips) - 1);
            }
        } catch (JobException $e) {
            if ($jobs) Jobs::spawn();
            return Response::json(['ok' => (bool) $jobs, 'error' => $e->getMessage(), 'jobs' => $jobs], $jobs ? 200 : 422);
        }
        return Response::json(['ok' => true, 'jobs' => $jobs]);
    }

    /** Poster: {at} (ffmpeg, Hintergrund) oder hochgeladenes Standbild aus dem Browser (image, ohne ffmpeg) */
    public function poster(Request $r, string $id): Response
    {
        $this->guard($r);
        $m = $this->video($id);
        $file = $r->files['image'] ?? null;
        if ($file) {
            if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name']) || (int) $file['size'] > 12 * 1048576) {
                return Response::json(['ok' => false, 'error' => __('Standbild konnte nicht übertragen werden.')], 422);
            }
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !@getimagesize((string) $file['tmp_name'])) {
                return Response::json(['ok' => false, 'error' => __('Das Standbild ist ungültig.')], 422);
            }
            try {
                [$pid, $msg] = Output::storePoster($m, (string) $file['tmp_name'], $r->str('at') !== '' ? max(0, (int) $r->str('at')) : null);
            } catch (JobException $e) {
                return self::fail($e);
            }
            $this->changed();
            return Response::json(['ok' => true, 'message' => $msg, 'poster' => $pid]);
        }
        try {
            $j = Jobs::create('poster', $m, ['at' => (int) ($r->post['at'] ?? 0)]);
        } catch (JobException $e) {
            return self::fail($e);
        }
        return Response::json(['ok' => true, 'job' => $j]);
    }

    public function posterClear(Request $r, string $id): Response
    {
        $this->guard($r);
        $m = $this->video($id);
        Repo::setPoster((int) $m['id'], null, null);
        return Response::json(['ok' => true]);
    }

    /** Animierte Vorschau für eine oder mehrere Dateien (für das Raster, erzeugt bei Bedarf) */
    public function previews(Request $r): Response
    {
        $this->guard($r);
        if (!Ffmpeg::available() || !Ffmpeg::config()['preview']) return Response::json(['ok' => true, 'queued' => 0]);
        $n = 0;
        foreach (array_slice(array_map('intval', (array) ($r->post['ids'] ?? [])), 0, 12) as $id) {
            $m = Repo::video($id);
            if (!$m) continue;
            $row = Repo::meta($m)['row'];
            if (!empty($row['preview'])) continue;
            $dur = (float) (Repo::meta($m)['info']['duration'] ?? 0);
            try {
                Jobs::create('preview', $m, ['at' => (int) round(min(max(0, $dur * 0.2), max(0, $dur - 3)) * 1000)], null, 'new', 'preview', false);
                $n++;
            } catch (JobException) {
            }
        }
        if ($n) Jobs::spawn();
        return Response::json(['ok' => true, 'queued' => $n]);
    }

    /** Sammelaktion „Alle optimieren“: {preset, ids[]} oder {preset, check: 'video_unoptimized'} */
    public function bulk(Request $r): Response
    {
        $this->guard($r);
        $preset = (string) ($r->post['preset'] ?? '');
        $ids = array_map('intval', (array) ($r->post['ids'] ?? []));
        if (!$ids && ($r->post['check'] ?? '') !== '') {
            $ids = array_map(fn($m) => (int) $m['id'], Media::all(['check' => (string) $r->post['check']]));
        }
        $ids = array_slice(array_values(array_unique(array_filter($ids))), 0, 200);
        if (!$ids) return Response::json(['ok' => false, 'error' => __('Keine Videos ausgewählt.')], 422);
        $batch = 'bulk-' . bin2hex(random_bytes(4));
        $jobs = [];
        $skipped = [];
        foreach ($ids as $id) {
            $m = Repo::video($id);
            if (!$m) continue;
            // „Nur faststart“ lohnt nur, wo es fehlt; bereits optimierte Videos nicht erneut kodieren
            $meta = Repo::meta($m);
            if ($preset === 'faststart' && ($meta['info']['faststart'] ?? null) !== false) { $skipped[] = Media::displayName($m); continue; }
            try {
                $jobs[] = Jobs::create('optimize', $m, [], $preset, 'new', $batch, false);
            } catch (JobException $e) {
                $skipped[] = Media::displayName($m) . ': ' . $e->getMessage();
            }
        }
        if ($jobs) Jobs::spawn();
        return Response::json(['ok' => (bool) $jobs, 'queued' => count($jobs), 'skipped' => $skipped, 'batch' => $batch,
            'error' => $jobs ? null : ($skipped[0] ?? __('Nichts zu tun.'))], $jobs ? 200 : 422);
    }

    // ------------------------------------------------------------------ Aufträge

    public function jobs(Request $r): Response
    {
        $this->guard($r);
        $ids = array_filter(explode(',', $r->str('ids')));
        $list = Jobs::list(['open' => $r->str('open') === '1', 'ids' => $ids, 'media' => (int) $r->str('media')], (int) ($r->str('limit') ?: 40));
        $open = (int) app()->db->fetchValue("SELECT COUNT(*) FROM video_jobs WHERE status IN ('queued', 'running')");
        return Response::json(['ok' => true, 'jobs' => $list, 'open' => $open, 'admin' => VideoTools::isAdmin()]);
    }

    public function cancel(Request $r, string $jid): Response
    {
        $this->guard($r);
        Jobs::cancel((int) $jid);
        return Response::json(['ok' => true, 'job' => Jobs::get((int) $jid)]);
    }

    public function retry(Request $r, string $jid): Response
    {
        $this->guard($r);
        $j = Jobs::raw((int) $jid);
        if ($j && ($j['mode'] === 'replace' || str_contains((string) $j['params'], '"delete_original":true')) && !VideoTools::canReplace()) {
            return Response::json(['ok' => false, 'error' => __('Original ersetzen oder löschen braucht das Recht „Medien löschen“.')], 403);
        }
        try {
            $j = Jobs::retry((int) $jid);
        } catch (JobException $e) {
            return self::fail($e);
        }
        return $j ? Response::json(['ok' => true, 'job' => $j]) : Response::json(['ok' => false, 'error' => __('Nur fehlgeschlagene oder abgebrochene Aufträge lassen sich wiederholen.')], 422);
    }

    /** Protokoll (Befehl mit Platzhaltern, Ausgabe von ffmpeg) – nur Administration */
    public function log(Request $r, string $jid): Response
    {
        $this->guard($r);
        if (!VideoTools::isAdmin()) throw new HttpException(403, __('Keine Berechtigung.'));
        $j = Jobs::raw((int) $jid) ?? throw new HttpException(404);
        $log = str_replace([ROOT . '/', ROOT], ['', ''], (string) $j['log']);
        return Response::json(['ok' => true, 'id' => (int) $j['id'], 'label' => Jobs::label($j), 'log' => $log !== '' ? $log : __('(kein Protokoll)')]);
    }
}
