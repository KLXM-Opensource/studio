<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Api\ApiError;
use Core\Api\CmsService;
use Core\Api\OpenApi;
use Core\Api\Tokens;
use Core\Http\Request;
use Core\Http\Response;

/**
 * REST-API v1 (JSON). Authentifizierung: Authorization: Bearer cms_… (oder X-Api-Key).
 * Antworten: {"data": …} bzw. {"error": {"status", "message", …}}
 */
final class ApiController
{
    /** Führt eine Aktion mit Token-Prüfung aus und verpackt Ergebnis/Fehler einheitlich. */
    private function run(Request $r, bool $write, callable $fn, int $okStatus = 200): Response
    {
        try {
            $token = Tokens::authenticate($r);
            if ($write) {
                Tokens::requireWrite($token);
            }
            $svc = (new CmsService($token))->origin('api');
            // Sprachkontext: ?lang=en (Seiten, Einträge, Einstellungen, Medientexte in dieser Sprache)
            $svc->useLang(isset($r->query['lang']) ? (string) $r->query['lang'] : null);
            return self::ok($fn($svc, $r), $okStatus);
        } catch (\Core\Review\Pending $e) {
            // Token im Modus „zur Freigabe“: nichts ausgeführt, Einreichung angelegt (Core\Review\Queue)
            // (kein Location-Header: PHP machte daraus einen 302) – status_url steht im Body
            return self::ok($e->data, 202)->header('Content-Location', $e->data['status_url']);
        } catch (ApiError $e) {
            return self::fail($e);
        } catch (\Throwable $e) {
            error_log('[API] ' . $e);
            return self::fail(new ApiError(500, app()->config->get('debug') ? $e->getMessage() : 'Interner Fehler.'));
        }
    }

    public static function ok(mixed $data, int $status = 200): Response
    {
        return self::headers(Response::json(['data' => $data], $status));
    }

    public static function fail(ApiError $e): Response
    {
        $res = Response::json(['error' => ['status' => $e->status, 'message' => $e->getMessage()] + $e->details], $e->status);
        if ($e->status === 401) {
            $res->header('WWW-Authenticate', 'Bearer realm="' . CMS_SLUG . '-api"');
        }
        return self::headers($res);
    }

    private static function headers(Response $r): Response
    {
        return $r->header('X-Robots-Tag', 'noindex')->header('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    private function body(Request $r): array
    {
        return is_array($r->post) ? $r->post : [];
    }

    private static function bool(mixed $v): bool
    {
        return filter_var($v, FILTER_VALIDATE_BOOL);
    }

    // ------------------------------------------------------------------ Meta

    public function index(Request $r): Response
    {
        return self::ok([
            'name' => CMS_NAME . ' API', 'version' => '1',
            'docs' => absolute_url('/api/v1/openapi.json'),
            'mcp' => absolute_url('/mcp'),
            'auth' => 'Authorization: Bearer <token> (Admin → API & MCP)',
        ]);
    }

    public function openapi(Request $r): Response
    {
        return self::headers(Response::json(OpenApi::spec()));
    }

    public function publicInfo(Request $r): Response
    {
        return self::ok(CmsService::publicInfo())->header('Cache-Control', 'public, max-age=300');
    }

    public function me(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->siteInfo());
    }

    // ------------------------------------------------------------------ Einstellungen

    public function settingsSchema(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->settingsSchema());
    }

    public function settings(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->settingsGet($r->query['group'] ?? null));
    }

    public function settingsUpdate(Request $r): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->settingsUpdate($this->body($r)));
    }

    // ------------------------------------------------------------------ Design (Style-Editor)

    public function design(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->designGet());
    }

    public function designUpdate(Request $r): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->designUpdate($this->body($r)));
    }

    /** Landingpages mit eigenen Domains (Core\Landings) – nur lesen */
    public function landings(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->landingsGet());
    }

    /** Weiterleitungen (Core\Redirects) – nur lesen, ?q=…, ?test=/pfad */
    public function redirects(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->redirectsGet(isset($r->query['q']) ? (string) $r->query['q'] : null, isset($r->query['test']) ? (string) $r->query['test'] : null));
    }

    public function hours(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->hoursGet());
    }

    public function hoursUpdate(Request $r): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->hoursSet(array_is_list($this->body($r)) ? $this->body($r) : (array) ($this->body($r)['hours'] ?? [])));
    }

    public function notice(Request $r): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->noticeSet((string) ($this->body($r)['text'] ?? ''), self::bool($this->body($r)['active'] ?? true)));
    }

    // ------------------------------------------------------------------ Seiten & Blöcke

    public function blockTypes(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->blockTypes());
    }

    public function pages(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->pagesList());
    }

    public function pageCreate(Request $r): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->pageCreate($this->body($r)), 201);
    }

    public function page(Request $r, string $page): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->pageGet($page, ($r->query['version'] ?? '') === 'published'));
    }

    public function pageUpdate(Request $r, string $page): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->pageUpdate($page, $this->body($r)));
    }

    public function pageDelete(Request $r, string $page): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->pageDelete($page));
    }

    public function pageTranslate(Request $r, string $page): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->pageTranslate($page, (string) ($this->body($r)['lang'] ?? '')), 201);
    }

    public function publish(Request $r, string $page): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->publish($page));
    }

    public function discard(Request $r, string $page): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->discard($page));
    }

    public function revisions(Request $r, string $page): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->revisions($page));
    }

    public function restore(Request $r, string $page, string $rev): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->restore($page, (int) $rev));
    }

    public function blocks(Request $r, string $page): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->pageGet($page, ($r->query['version'] ?? '') === 'published')['blocks']);
    }

    public function blocksReplace(Request $r, string $page): Response
    {
        $b = $this->body($r);
        return $this->run($r, true, fn(CmsService $s) => $s->blocksReplace($page, array_is_list($b) ? $b : (array) ($b['blocks'] ?? []), self::bool($r->query['publish'] ?? $b['publish'] ?? false)));
    }

    public function blockAdd(Request $r, string $page): Response
    {
        $b = $this->body($r);
        return $this->run($r, true, fn(CmsService $s) => $s->blockAdd($page, (string) ($b['type'] ?? ''), (array) ($b['data'] ?? []), (array) ($b['section'] ?? []),
            isset($b['position']) ? (int) $b['position'] : null, isset($b['after']) ? (string) $b['after'] : null, self::bool($b['publish'] ?? false)), 201);
    }

    public function blockUpdate(Request $r, string $page, string $block): Response
    {
        $b = $this->body($r);
        return $this->run($r, true, fn(CmsService $s) => $s->blockUpdate($page, $block, isset($b['data']) ? (array) $b['data'] : null,
            isset($b['section']) ? (array) $b['section'] : null, self::bool($b['replace'] ?? false), self::bool($b['publish'] ?? false)));
    }

    public function blockDelete(Request $r, string $page, string $block): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->blockRemove($page, $block, self::bool($r->query['publish'] ?? false)));
    }

    public function blockMove(Request $r, string $page, string $block): Response
    {
        $b = $this->body($r);
        return $this->run($r, true, fn(CmsService $s) => $s->blockMove($page, $block, (int) ($b['position'] ?? 0), self::bool($b['publish'] ?? false)));
    }

    // ------------------------------------------------------------------ Medien

    public function media(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->mediaList($r->query));
    }

    public function mediaMeta(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->mediaMeta());
    }

    public function mediaCrop(Request $r, string $id): Response
    {
        return $this->run($r, true, function (CmsService $s) use ($r, $id) {
            $b = $this->body($r);
            return $s->mediaCrop((int) $id, (string) ($b['ratio'] ?? ''), is_array($b['rect'] ?? null) ? $b['rect'] : null);
        });
    }

    public function mediaUpload(Request $r): Response
    {
        return $this->run($r, true, function (CmsService $s) use ($r) {
            $alt = (string) ($r->post['alt'] ?? '');
            $opt = array_intersect_key($r->post, ['decorative' => 1, 'title' => 1, 'tags' => 1, 'collection' => 1]);
            if (isset($r->files['file'])) {
                return $s->mediaUploadFile($r->files['file'], $alt, $opt);
            }
            if (!empty($r->post['base64'])) {
                return $s->mediaUploadBase64((string) ($r->post['filename'] ?? 'datei'), (string) $r->post['base64'], $alt, $opt);
            }
            throw new ApiError(422, 'Datei fehlt: multipart-Feld „file“ oder JSON {filename, base64}.');
        }, 201);
    }

    public function mediaUpdate(Request $r, string $id): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->mediaUpdate((int) $id, $this->body($r)));
    }

    public function mediaDelete(Request $r, string $id): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->mediaDelete((int) $id));
    }

    // ------------------------------------------------------------------ Anfragen

    public function requests(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->requestsList((string) ($r->query['status'] ?? 'neu'), self::bool($r->query['ciphertext'] ?? false),
            isset($r->query['table']) ? (string) $r->query['table'] : null));
    }

    public function requestUpdate(Request $r, string $id): Response
    {
        $b = $this->body($r);
        $table = (string) ($b['table'] ?? $r->query['table'] ?? '');
        return $this->run($r, true, fn(CmsService $s) => $s->requestStatus((int) $id, (string) ($b['status'] ?? ''), $table !== '' ? $table : null));
    }

    // ------------------------------------------------------------------ Datentabellen

    public function dataTables(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->dataTables());
    }

    public function dataEntries(Request $r, string $table): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->dataEntries($table, ['ciphertext' => self::bool($r->query['ciphertext'] ?? false)] + $r->query));
    }

    public function dataOccurrences(Request $r, string $table): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->dataOccurrences($table, $r->query));
    }

    public function dataSuggestions(Request $r, string $table): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->dataSuggestions($table, (string) ($r->query['state'] ?? 'pending')));
    }

    public function dataPick(Request $r, string $table): Response
    {
        $b = $this->body($r);
        $ids = isset($b['ids']) ? (array) $b['ids'] : (isset($b['entry_id']) ? [$b['entry_id']] : []);
        return $this->run($r, true, fn(CmsService $s) => $s->dataPick($table, $ids, isset($b['state']) ? (string) $b['state'] : ''));
    }

    public function dataEntry(Request $r, string $table, string $id): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->dataEntry($table, $id, self::bool($r->query['ciphertext'] ?? false)));
    }

    public function dataCreate(Request $r, string $table): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->dataSave($table, null, $this->body($r)), 201);
    }

    public function dataUpdate(Request $r, string $table, string $id): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->dataSave($table, (int) $id, $this->body($r)));
    }

    public function dataTranslate(Request $r, string $table, string $id): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->dataTranslate($table, (int) $id, (string) ($this->body($r)['lang'] ?? '')), 201);
    }

    /** GET /api/v1/search?q=…&lang=&page=&limit=&type= – Website-Suche wie für Besucher (Core\Search) */
    public function search(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->search((string) ($r->query['q'] ?? ''), $r->query));
    }

    public function geocode(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->geocode((string) ($r->query['q'] ?? '')));
    }

    public function dataDelete(Request $r, string $table, string $id): Response
    {
        return $this->run($r, true, fn(CmsService $s) => $s->dataDelete($table, (int) $id));
    }

    // ------------------------------------------------------------------ Eingereichte Änderungen (Prüf-Ebene)

    /** GET /api/v1/changes?status=pending|applied|rejected – eigene Einreichungen und Protokoll dieses Tokens */
    public function changes(Request $r): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->changesList(isset($r->query['status']) ? (string) $r->query['status'] : null));
    }

    /** GET /api/v1/changes/{id} – Stand einer Einreichung (pending_review | applied | rejected, bei Ablehnung mit reason) */
    public function change(Request $r, string $id): Response
    {
        return $this->run($r, false, fn(CmsService $s) => $s->changeStatus((int) $id));
    }

    public function notFound(Request $r): Response
    {
        return self::fail(new ApiError(404, 'Unbekannter API-Endpunkt. Übersicht: ' . absolute_url('/api/v1/openapi.json')));
    }
}
