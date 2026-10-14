<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** user.role_changed: Rolle eines Kontos geändert (Verwaltung → Rollen). */
final class UserRoleChanged extends InfoEvent
{
    public const NAME = 'user.role_changed';

    public function __construct(public readonly int $id, public readonly string $role, public readonly string $oldRole)
    {
        parent::__construct(null);
    }
}
