<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

use Core\AI\AiException;
use Core\AI\Assist;
use Core\AI\Assistant;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Http\Sse;

/**
 * Assistent der Redaktion (Core\AI\Assistant): Frage (Server-Sent Events bzw. JSON), Aktion ausführen, Verlauf
 * speichern/löschen und die Seite „KLXM AI → Assistent“. Jeder Aufruf: Anmeldung, CSRF (POST), Assistant::available().
 */
final class AssistantController extends AdminController
{
    private function guard(Request $r): array
    {
        $u = $this->auth($r);
        if (!Assistant::available()) throw new HttpException(403, __('Der KI-Assistent ist für Sie auf dieser Website nicht verfügbar.'));
        return $u;
    }

    private static function out(array $data, int $status = 200): Response
    {
        return self::secure(Response::json($data, $status));
    }

    /** Seite im KI-Bereich: Assistent im Hauptbereich + gespeicherte Unterhaltungen */
    public function page(Request $r): Response
    {
        $this->auth($r);
        if (!\Core\Features::on('ai', false) || !\Core\Features::on('chat.assistant', false)) throw new HttpException(404);
        if (!can('ai.use')) throw new HttpException(403, __('Für diese Aktion fehlt Ihrer Rolle die Berechtigung.'));
        $brand = Assist::brand();
        return $this->view('ai/assistant', [
            'title' => $brand . ' · ' . __('Assistent'), 'available' => Assistant::available(), 'q' => mb_substr($r->str('q'), 0, 500),
            'list' => Assistant::available() ? Assistant::list() : ['saved' => [], 'recent' => []],
            'drill' => \Core\Theme::capture(ROOT . '/app/Admin/views/ai/_nav.php', ['cur' => 'assistant']), 'drillTitle' => $brand,
        ]);
    }

    public function ask(Request $r): Response
    {
        try {
            $this->guard($r);
        } catch (HttpException $e) {
            return self::out(['ok' => false, 'error' => $e->getMessage() ?: __('Keine Berechtigung.')], $e->getCode() ?: 403);
        }
        $q = trim(mb_substr((string) preg_replace('~[\x00-\x08\x0B-\x1F\x7F]~u', ' ', (string) ($r->post['q'] ?? '')), 0, Assistant::MAX_QUESTION));
        if (mb_strlen($q) < 2) return self::out(['ok' => false, 'error' => __('Bitte eine Frage oder einen Auftrag eingeben.')], 422);
        $history = [];
        foreach (array_slice((array) ($r->post['history'] ?? []), -Assistant::MAX_HISTORY) as $m) {
            if (is_array($m) && in_array($m['role'] ?? '', ['user', 'assistant'], true) && trim((string) ($m['content'] ?? '')) !== '') {
                $history[] = ['role' => $m['role'], 'content' => mb_substr(strip_tags((string) $m['content']), 0, 3000)];
            }
        }
        $ctx = is_array($r->post['context'] ?? null) && !empty($r->post['use_context']) ? (array) $r->post['context'] : ['route' => (string) (($r->post['context']['route'] ?? '') ?: '')];
        $conv = (string) ($r->post['conv'] ?? '');
        if (!Sse::wanted($r)) {
            try {
                return self::out(['ok' => true] + Assistant::ask($q, $history, $ctx, $conv, fn() => true));
            } catch (AiException $e) {
                return self::out(['ok' => false, 'error' => $e->getMessage(), 'quota' => Assist::quota()['left']], 422);
            }
        }
        return Sse::stream(function (Sse $sse) use ($q, $history, $ctx, $conv) {
            try {
                $out = Assistant::ask($q, $history, $ctx, $conv, fn(string $ev, array $d) => $sse->send($ev, $d));
                $sse->send('done', ['ok' => true] + $out);
            } catch (AiException $e) {
                Assist::log('assistant', 'ask', '', 'failed');
                $sse->send('error', ['error' => $e->getMessage(), 'quota' => Assist::quota()['left']]);
            }
        }, ['Cache-Control' => 'no-cache, no-store, no-transform, private']);
    }

    public function execute(Request $r): Response
    {
        try {
            $this->guard($r);
            $res = Assistant::execute($r->str('action'), (array) ($r->post['args'] ?? []), $r->str('sig'));
            return self::out(['ok' => true, 'quota' => Assist::quota()['left']] + $res);
        } catch (AiException $e) {
            Assist::log('assistant', mb_substr($r->str('action'), 0, 30), '', 'failed');
            return self::out(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (HttpException $e) {
            return self::out(['ok' => false, 'error' => $e->getMessage() ?: __('Keine Berechtigung.')], $e->getCode() ?: 403);
        }
    }

    public function history(Request $r): Response
    {
        try {
            $this->guard($r);
            return self::out(['ok' => true] + Assistant::list());
        } catch (HttpException $e) {
            return self::out(['ok' => false, 'error' => $e->getMessage()], $e->getCode() ?: 403);
        }
    }

    public function show(Request $r, string $id): Response
    {
        try {
            $this->guard($r);
            $c = Assistant::get($id) ?? throw new HttpException(404, __('Unterhaltung nicht gefunden.'));
            return self::out(['ok' => true, 'conversation' => ['id' => $c['id'], 'title' => $c['title'], 'pinned' => (bool) $c['pinned'], 'messages' => $c['messages']]]);
        } catch (HttpException $e) {
            return self::out(['ok' => false, 'error' => $e->getMessage()], $e->getCode() ?: 403);
        }
    }

    public function save(Request $r): Response
    {
        try {
            $this->guard($r);
            $pinned = array_key_exists('pinned', $r->post) ? !empty($r->post['pinned']) : null;
            return self::out(['ok' => true] + Assistant::save($r->str('id'), (array) ($r->post['messages'] ?? []), $r->str('title') ?: null, $pinned));
        } catch (AiException $e) {
            return self::out(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (HttpException $e) {
            return self::out(['ok' => false, 'error' => $e->getMessage()], $e->getCode() ?: 403);
        }
    }

    public function delete(Request $r, string $id): Response
    {
        try {
            $this->guard($r);
            return self::out(['ok' => Assistant::delete($id)]);
        } catch (HttpException $e) {
            return self::out(['ok' => false, 'error' => $e->getMessage()], $e->getCode() ?: 403);
        }
    }
}
