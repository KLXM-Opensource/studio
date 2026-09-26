<?php
declare(strict_types=1);

namespace Core\Http\Controllers\Admin;

/** Wird geworfen, um aus einem Controller heraus umzuleiten (z. B. zum Login). */
final class RedirectException extends \RuntimeException
{
    public function __construct(public readonly string $to)
    {
        parent::__construct('redirect');
    }
}
