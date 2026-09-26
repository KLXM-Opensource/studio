<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/** Fehler mit Text für Besucher (bereits übersetzt, ohne technische Details wie IP-Adressen des Servers) */
final class CheckException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}
