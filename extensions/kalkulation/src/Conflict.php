<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Kalkulation;

/** Speichern abgelehnt: die Kalkulation wurde inzwischen von jemand anderem gespeichert ($current = Stand in der Datenbank). */
final class Conflict extends \RuntimeException
{
    public function __construct(public readonly array $current)
    {
        parent::__construct(__('{user} hat diese Kalkulation um {time} gespeichert. Bitte neu laden oder Ihren Stand trotzdem speichern.', [
            'user' => Kalkulation::userName($current['updated_by']), 'time' => Kalkulation::dateTime($current['updated_at'])]));
    }
}
