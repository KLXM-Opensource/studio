<?php
declare(strict_types=1);

namespace Core\Review;

/**
 * Änderung wurde nicht ausgeführt, sondern zur Freigabe eingereicht (Core\Review\Queue).
 * REST-API: HTTP 202 mit $data; MCP: Tool-Ergebnis {status: "pending_review", id, url}.
 */
final class Pending extends \RuntimeException
{
    public function __construct(public readonly array $data)
    {
        parent::__construct((string) ($data['message'] ?? 'pending_review'));
    }
}
