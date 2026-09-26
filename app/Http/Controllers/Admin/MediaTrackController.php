<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\AI\AiException;
use Core\AI\Assist;
use Core\AI\MediaJobs;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Lang;
use Core\Media;
use Core\MediaPools;
use Core\MediaTracks;

/**
 * Untertitel, Kapitel und Transkripte für Videos/Audio der Mediathek (JSON für resources/js/_captions.js).
 * Lesen: angemeldet. Ändern: „media.upload“ (Website) bzw. MediaPools::canEdit (geteilte Pools).
 * KI-Transkription/-Übersetzung: zusätzlich „ai.use“ und die Fähigkeit transcribe bzw. text (Core\AI\MediaJobs).
 */
final class MediaTrackController extends AdminController
{
    /** Medium im richtigen Kontext (Parameter pool oder Verweis auf eine Pool-Datei) – mit Schreibrecht, falls $write */
    private function media(Request $r, string $id, bool $write): array
    {
        $this->auth($r, $write ? 'media.upload' : false);
        if (!MediaTracks::enabled()) throw new HttpException(404);
        $pool = $r->str('pool');
        if ($pool !== '') {
            if (!MediaPools::available($pool)) throw new HttpException(404, __('Diesen Medien-Pool nutzt die Website nicht.'));
            if ($write && !MediaPools::canEdit($pool)) throw new HttpException(403, __('Diesen geteilten Pool darf diese Website nur verwenden.'));
            Media::usePool($pool);
        }
        $m = Media::find((int) $id) ?? throw new HttpException(404, __('Datei nicht gefunden.'));
        if (!empty($m['_pool']) && $write && !MediaPools::canEdit((string) $m['_pool'])) {
            throw new HttpException(403, __('Diesen geteilten Pool darf diese Website nur verwenden.'));
        }
        if (!MediaTracks::supports($m)) throw new HttpException(422, __('Untertitel gibt es nur für Videos und Audio.'));
        return $m;
    }

    private function json(callable $fn): Response
    {
        try {
            return self::secure(Response::json(['ok' => true] + $fn()));
        } catch (\InvalidArgumentException | AiException $e) {
            return self::secure(Response::json(['ok' => false, 'error' => $e->getMessage()], 422));
        } catch (HttpException $e) {
            return self::secure(Response::json(['ok' => false, 'error' => $e->getMessage() ?: __('Keine Berechtigung.')], $e->getCode() ?: 400));
        }
    }

    private function state(array $m): array
    {
        $langs = Lang::all();
        return [
            'tracks' => array_map(fn($t) => MediaTracks::trackJson($m, $t), MediaTracks::forMedia($m)),
            'transcripts' => (object) array_map(fn($t) => ['text' => (string) $t['text'], 'status' => $t['status'], 'source' => $t['source'] ?? '', 'updated_at' => $t['updated_at'] ?? null],
                MediaTracks::transcripts($m)),
            'jobs' => MediaJobs::list($m, 5),
            'langs' => (object) $langs, 'default_lang' => Lang::default(),
            'kinds' => array_map(fn($l) => __($l), MediaTracks::KINDS),
            'can_edit' => empty($m['_pool']) ? can('media.upload') : MediaPools::canEdit((string) $m['_pool']),
            'ai' => ['transcribe' => MediaJobs::canTranscribe(), 'translate' => MediaJobs::canTranslate(),
                'language' => (string) (\Core\AI\Ai::config()['transcribe']['language'] ?? 'auto'), 'external' => \Core\AI\Ai::capability('transcribe')['external']],
        ];
    }

    public function index(Request $r, string $id): Response
    {
        return $this->json(fn() => $this->state($this->media($r, $id, false)));
    }

    public function show(Request $r, string $id, string $tid): Response
    {
        return $this->json(function () use ($r, $id, $tid) {
            $m = $this->media($r, $id, false);
            $t = MediaTracks::find($m, (int) $tid) ?? throw new HttpException(404, __('Untertitel nicht gefunden.'));
            return ['track' => MediaTracks::trackJson($m, $t, true)];
        });
    }

    /** Anlegen: Datei (multipart „file“: .vtt/.srt) oder Cues (JSON) */
    public function store(Request $r, string $id): Response
    {
        return $this->json(function () use ($r, $id) {
            $m = $this->media($r, $id, true);
            $in = ['kind' => $r->str('kind', 'subtitles'), 'lang' => $r->str('lang', Lang::default()), 'label' => $r->str('label'),
                // Upload-Formular: publish=1 · Untertitel-Editor (JSON): status=published („Geprüft – veröffentlichen“ bei neuen Spuren)
                'status' => $r->str('publish') === '1' || $r->str('status') === 'published' ? 'published' : 'draft'];
            $file = $r->files['file'] ?? null;
            if ($file) {
                if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) throw new \InvalidArgumentException(__('Upload fehlgeschlagen.'));
                if ((int) $file['size'] > MediaTracks::MAX_BYTES) throw new \InvalidArgumentException(__('Datei ist zu groß (max. 2 MB).'));
                if (!preg_match('~\.(vtt|srt)$~i', (string) $file['name'])) throw new \InvalidArgumentException(__('Bitte eine WebVTT- (.vtt) oder SubRip-Datei (.srt) wählen.'));
                $in['vtt'] = (string) file_get_contents((string) $file['tmp_name']);
                $in['source'] = 'upload';
            } else {
                $in['cues'] = (array) ($r->post['cues'] ?? []);
                $in['source'] = 'editor';
            }
            $t = MediaTracks::save($m, $in, null, (int) app()->auth->user()['id']);
            $this->changed();
            return ['track' => MediaTracks::trackJson($m, $t)] + $this->state(Media::find((int) $id) ?? $m);
        });
    }

    /** Editor speichert (Cues, Art, Sprache, Bezeichnung; status published = geprüft und veröffentlicht) */
    public function update(Request $r, string $id, string $tid): Response
    {
        return $this->json(function () use ($r, $id, $tid) {
            $m = $this->media($r, $id, true);
            $p = $r->post;
            $in = array_intersect_key($p, ['kind' => 1, 'lang' => 1, 'label' => 1, 'cues' => 1, 'status' => 1]);
            if (($in['status'] ?? '') === 'published') $in['note'] = '';
            $t = MediaTracks::save($m, $in, (int) $tid, (int) app()->auth->user()['id']);
            // Mit der Prüfung eines KI-Entwurfs wird auch der Transkript-Entwurf dieser Sprache veröffentlicht
            if ($t['status'] === 'published' && in_array($t['kind'], ['subtitles', 'captions'], true)) {
                $tr = MediaTracks::transcripts(Media::find((int) $m['id']) ?? $m)[$t['lang']] ?? null;
                if ($tr && ($tr['status'] ?? '') === 'draft') {
                    // KI-Entwurf: aus den geprüften Untertiteln neu bilden; von Hand geschriebene Entwürfe bleiben, wie sie sind
                    $text = in_array($tr['source'] ?? '', ['ai', 'translate'], true) ? MediaTracks::transcriptFromCues(MediaTracks::cues($t)) : (string) $tr['text'];
                    MediaTracks::setTranscript($m, $t['lang'], $text, 'published', (string) ($tr['source'] ?? 'ai'));
                }
            }
            $this->changed();
            return ['track' => MediaTracks::trackJson($m, $t, true)] + $this->state(Media::find((int) $m['id']) ?? $m);
        });
    }

    public function delete(Request $r, string $id, string $tid): Response
    {
        return $this->json(function () use ($r, $id, $tid) {
            $m = $this->media($r, $id, true);
            MediaTracks::delete($m, (int) $tid);
            $this->changed();
            return $this->state($m);
        });
    }

    /** WebVTT herunterladen (auch Entwürfe – nur angemeldet) */
    public function download(Request $r, string $id, string $tid): Response
    {
        $m = $this->media($r, $id, false);
        $t = MediaTracks::find($m, (int) $tid) ?? throw new HttpException(404);
        $name = preg_replace('~[^\w\-]+~', '-', pathinfo((string) $m['original_name'], PATHINFO_FILENAME)) . '.' . $t['lang'] . '.' . $t['kind'] . '.vtt';
        return self::secure(new Response((string) $t['vtt'], 200, ['Content-Type' => 'text/vtt; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $name . '"', 'X-Content-Type-Options' => 'nosniff']));
    }

    /** Transkript einer Sprache speichern (leer = entfernen); publish=1 → auf der Website zeigen */
    public function transcript(Request $r, string $id): Response
    {
        return $this->json(function () use ($r, $id) {
            $m = $this->media($r, $id, true);
            $lang = $r->str('lang');
            $text = (string) ($r->post['text'] ?? '');
            if (!empty($r->post['from_track'])) {
                $t = MediaTracks::find($m, (int) $r->post['from_track']) ?? throw new \InvalidArgumentException(__('Untertitel nicht gefunden.'));
                $text = MediaTracks::transcriptFromCues(MediaTracks::cues($t));
                $lang = $t['lang'];
            }
            MediaTracks::setTranscript($m, $lang, $text, $r->str('publish') === '1' ? 'published' : 'draft', 'editor');
            $this->changed();
            return $this->state(Media::find((int) $m['id']) ?? $m);
        });
    }

    /** KI-Transkription starten (Hintergrund-Auftrag) */
    public function transcribe(Request $r, string $id): Response
    {
        return $this->json(function () use ($r, $id) {
            $m = $this->media($r, $id, true);
            if (!MediaJobs::canTranscribe()) throw new HttpException(403, __('Die KI-Transkription ist für Sie auf dieser Website nicht verfügbar.'));
            $lang = $r->str('lang', 'auto');
            if ($lang !== 'auto' && !MediaTracks::validLang($lang)) throw new \InvalidArgumentException(__('Ungültige Sprache.'));
            $job = MediaJobs::create('transcribe', $m, ['lang' => $lang]);
            Assist::log('transcribe', '', __('Medien') . ' #' . (int) $id, 'suggested', \Core\AI\Ai::capability('transcribe')['model']);
            return ['job' => $job] + $this->state($m);
        });
    }

    /** Untertitel mit der Text-KI übersetzen (Hintergrund-Auftrag) */
    public function translate(Request $r, string $id, string $tid): Response
    {
        return $this->json(function () use ($r, $id, $tid) {
            $m = $this->media($r, $id, true);
            if (!MediaJobs::canTranslate()) throw new HttpException(403, __('Der KI-Assistent ist für Sie auf dieser Website nicht verfügbar.'));
            $t = MediaTracks::find($m, (int) $tid) ?? throw new \InvalidArgumentException(__('Untertitel nicht gefunden.'));
            $to = $r->str('target');
            if (!MediaTracks::validLang($to) || $to === $t['lang']) throw new \InvalidArgumentException(__('Bitte eine andere Zielsprache wählen.'));
            $job = MediaJobs::create('translate', $m, ['track' => (int) $t['id'], 'lang' => $t['lang'], 'target' => $to]);
            Assist::log('translate', 'captions', __('Medien') . ' #' . (int) $id, 'suggested');
            return ['job' => $job] + $this->state($m);
        });
    }

    /** Stand von Aufträgen (Polling): ids=1,2,3 */
    public function jobs(Request $r): Response
    {
        return $this->json(function () use ($r) {
            $this->auth($r);
            $ids = array_filter(array_map('intval', explode(',', $r->str('ids'))));
            return ['jobs' => array_values(array_filter(array_map(fn($i) => MediaJobs::get($i), array_slice($ids, 0, 50))))];
        });
    }

    public function cancel(Request $r, string $jid): Response
    {
        return $this->json(function () use ($r, $jid) {
            $u = $this->auth($r, 'media.upload');
            $j = MediaJobs::get((int) $jid) ?? throw new HttpException(404);
            MediaJobs::cancel((int) $jid);
            return ['job' => MediaJobs::get((int) $jid)];
        });
    }
}
