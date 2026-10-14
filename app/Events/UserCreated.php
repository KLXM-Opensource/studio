<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** user.created: Konto der Website angelegt (Verwaltung, Einrichtung, Einladung angenommen). $via: 'admin' | 'setup' | 'invite'. */
final class UserCreated extends InfoEvent
{
    public const NAME = 'user.created';

    public function __construct(public readonly int $id, public readonly string $email, public readonly string $role, public readonly string $via = 'admin')
    {
        parent::__construct(null);
    }
}
