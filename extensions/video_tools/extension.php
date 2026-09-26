<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Video-Werkzeuge. Copyright (C) 2026 KLXM and contributors (see LICENSE)
// Ideen aus FriendsOfREDAXO/ffmpeg (MIT) – eigene Umsetzung, kein übernommener Code.
/**
 * Erweiterung „video_tools“: Videos in der Mediathek analysieren, fürs Web optimieren, schneiden, Poster setzen –
 * als Hintergrund-Aufträge mit ffmpeg/ffprobe (ohne ffmpeg: nur Analyse aus PHP und Poster aus dem Browser).
 *
 * Aktivieren je Website:  Administration → Funktionen & Erweiterungen (Haupt-Admin) oder per Konfiguration
 *                         'extensions' => ['video_tools'], 'features' => ['video.tools' => true]
 *                         (Funktion ist Standard AUS). Optionen: 'video_tools' => ['ffmpeg' => '/usr/bin/ffmpeg', …] – siehe README.md.
 * Cron (empfohlen):        * * * * * php bin/console video:work --site={key} --all   (--site = eine Website mit video_tools)
 * Doku: extensions/video_tools/README.md, Verwaltung → Hilfe (Handbuch „Video-Werkzeuge“, Technik „Video-Werkzeuge“).
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Klxm\\VideoTools\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('Klxm\\VideoTools\\')) . '.php';
        if (is_file($file)) require $file;
    }
});

use Klxm\VideoTools\AdminController;
use Klxm\VideoTools\Console;
use Klxm\VideoTools\Ffmpeg;
use Klxm\VideoTools\Jobs;
use Klxm\VideoTools\Repo;
use Klxm\VideoTools\VideoTools;

return [
    'name' => 'video_tools',
    'label' => 'Video-Werkzeuge (ffmpeg)',
    'version' => '1.0.1',
    'requires' => '>=1.0.0',
    'description' => 'Videos in der Mediathek prüfen, fürs Web optimieren, schneiden und mit Poster versehen – im Hintergrund mit ffmpeg.',
    // Angaben für Administration → Funktionen & Erweiterungen (Autor/Lizenz zusätzlich aus composer.json)
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'risk' => 'Startet ffmpeg/ffprobe als Prozesse auf dem Server (proc_open) und verarbeitet hochgeladene Dateien. Nur aktivieren, wenn Videos wirklich hier bearbeitet werden; Speicherplatz und CPU-Last im Blick behalten.',
    'provides' => ['Menüpunkt „Video-Werkzeuge“ und Werkzeuge in der Mediathek', 'Recht „Videos optimieren, schneiden und Poster setzen“', 'Hintergrund-Aufträge (Cron video:work, jede Minute empfohlen)', 'Dateityp WebM in der Mediathek'],
    'commands' => ['video:work', 'video:jobs', 'video:info', 'video:optimize'],
    'docs' => ['Handbuch: Video-Werkzeuge' => '/admin/hilfe#video-tools', 'Technik: Video-Werkzeuge' => '/admin/hilfe/technik#video-tools'],
    // Voraussetzungen – auch ohne Aktivierung prüfbar (Administration → Funktionen & Erweiterungen)
    'requirements' => function (): array {
        $out = [__('Video-Werkzeuge: proc_open erlaubt') => Ffmpeg::canExec() ? true : false];
        foreach (['ffmpeg', 'ffprobe'] as $b) {
            $path = Ffmpeg::bin($b);
            $out[$path !== '' ? __('Video-Werkzeuge: {bin} gefunden', ['bin' => $b]) . ' (' . $path . ')'
                : __('Video-Werkzeuge: {bin} nicht gefunden – nur Analyse aus PHP (siehe Verwaltung → Video-Werkzeuge)', ['bin' => $b])] = $path !== '' ? true : null;
        }
        return $out;
    },
    'usage' => function (): ?string {
        if (!Repo::ready()) return null;
        $n = (int) app()->db->fetchValue('SELECT COUNT(*) FROM video_jobs');
        return $n ? __('{n} Video-Aufträge', ['n' => $n]) : null;
    },
    'boot' => function (Core\Extension $x): void {
        $x->feature(VideoTools::FEATURE, 'Video-Werkzeuge: Videos optimieren, schneiden, Poster (ffmpeg)', [VideoTools::PERM], false);
        $x->permissions('Medien', [VideoTools::PERM => 'Videos optimieren, schneiden und Poster setzen (Original ersetzen/löschen zusätzlich mit „Medien löschen“)']);
        $x->migration(1, fn(Core\Database $db) => Repo::migrate($db));
        $x->nav('/admin/video-tools', 'Video-Werkzeuge', 'video-camera', VideoTools::PERM, 'admin');

        // Mediathek: Oberfläche (Info-Panel, Trimmer, Aufträge) nur in der Medienverwaltung, nur mit Recht
        $x->adminAssets(fn(string $view) => str_starts_with($view, 'media') && VideoTools::allowed() ? ['css/video-tools.css', 'js/video-tools.js'] : []);
        $x->mediaJson(fn(array $m) => VideoTools::mediaJson($m));
        $x->mediaChecks(fn() => VideoTools::checks());
        $x->mediaPoster(fn(array $m) => VideoTools::poster($m));
        // WebM (VP9/Opus) als zusätzliche Version: Dateityp der Mediathek mit Prüfung des Dateianfangs (EBML)
        $x->mediaTypes(['video/webm' => ['ext' => 'webm', 'magic' => "\x1A\x45\xDF\xA3", 'label' => 'WebM']]);
        $x->on('media.deleted', fn(array $m) => Repo::forgetMedia($m));
        $x->on('media.replaced', fn(array $m) => Repo::replaced($m));

        // Hintergrund: wartende Aufträge ohne Arbeiter nach der Antwort starten; Betrieb
        $x->afterAdminResponse(fn() => VideoTools::on() ? Jobs::maybeRun() : null);
        $x->health(fn() => VideoTools::health());

        // Hilfe: Handbuch (nach „Bilder & Dateien“) und Entwicklerhandbuch
        $x->docs('manual', ['video-tools' => ['title' => 'Video-Werkzeuge: optimieren, schneiden, Poster', 'file' => __DIR__ . '/docs/manual.php', 'after' => 'medien']]);
        $x->docs('technical', ['video-tools' => ['title' => 'Video-Werkzeuge (Erweiterung, ffmpeg)', 'file' => __DIR__ . '/docs/technical.php', 'part' => 2, 'after' => 'medien']]);

        $x->routes(function (Core\Http\Router $r): void {
            $c = AdminController::class;
            $r->get('/admin/video-tools', [$c, 'index']);
            $r->get('/admin/api/video-tools/media/{id}', [$c, 'info']);
            $r->get('/admin/api/video-tools/media/{id}/stream', [$c, 'stream']);
            $r->post('/admin/api/video-tools/media/{id}/loudness', [$c, 'loudness']);
            $r->get('/admin/api/video-tools/media/{id}/keyframe', [$c, 'keyframe']);
            $r->post('/admin/api/video-tools/media/{id}/optimize', [$c, 'optimize']);
            $r->post('/admin/api/video-tools/media/{id}/trim', [$c, 'trim']);
            $r->post('/admin/api/video-tools/media/{id}/poster', [$c, 'poster']);
            $r->post('/admin/api/video-tools/media/{id}/poster/clear', [$c, 'posterClear']);
            $r->post('/admin/api/video-tools/previews', [$c, 'previews']);
            $r->post('/admin/api/video-tools/bulk', [$c, 'bulk']);
            $r->get('/admin/api/video-tools/jobs', [$c, 'jobs']);
            $r->post('/admin/api/video-tools/jobs/{jid}/cancel', [$c, 'cancel']);
            $r->post('/admin/api/video-tools/jobs/{jid}/retry', [$c, 'retry']);
            $r->get('/admin/api/video-tools/jobs/{jid}/log', [$c, 'log']);
        });

        $x->command('video:info', 'Video analysieren: video:info <id> [--loudness] [--json]', fn(array $a) => Console::info($a));
        $x->command('video:optimize', 'Video optimieren: video:optimize <id> --preset=web1080|web720|mobile540|archive|webm|faststart|mute|loudnorm [--replace] [--wait]', fn(array $a) => Console::optimize($a));
        $x->command('video:work', 'Wartende Video-Aufträge abarbeiten [--all] [--job=ID] (Cron: jede Minute)', fn(array $a) => Console::work($a));
        $x->command('video:jobs', 'Video-Aufträge: [--open] [--cancel=ID] [--retry=ID] [--log=ID]', fn(array $a) => Console::jobs($a));
    },
];
