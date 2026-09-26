<?php
declare(strict_types=1);

namespace Core\Api;

/** Fachlicher Fehler der API (HTTP-Status + Details, z. B. Feldfehler). */
final class ApiError extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly array $details = [])
    {
        parent::__construct($message, $status);
    }
}
