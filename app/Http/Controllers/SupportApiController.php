<?php
declare(strict_types=1);

namespace Core\Http\Controllers;

use Core\Api\ApiError;
use Core\Api\Tokens;
use Core\Http\Request;
use Core\Http\Response;

/** REST-API: Wissensdatenbank lesen – GET /api/v1/kb?q=…&limit=10 (Core\Support\Api) */
final class SupportApiController
{
    public function kb(Request $r): Response
    {
        try {
            Tokens::authenticate($r);
            return ApiController::ok(\Core\Support\Api::knowledge((string) ($r->query['q'] ?? ''), (int) ($r->query['limit'] ?? 10)));
        } catch (ApiError $e) {
            return ApiController::fail($e);
        } catch (\Throwable $e) {
            error_log('[API] kb: ' . $e);
            return ApiController::fail(new ApiError(500, 'Interner Fehler.'));
        }
    }
}
