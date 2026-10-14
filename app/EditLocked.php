<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/** Speichern abgelehnt – jemand anderes bearbeitet das Objekt gerade (Core\EditLock). HTTP 423 Locked. */
final class EditLocked extends Http\HttpException
{
    public function __construct(string $message, public readonly array $holder = [])
    {
        parent::__construct(423, $message);
    }
}
