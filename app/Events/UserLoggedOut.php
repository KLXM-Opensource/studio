<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** user.logout: Abmeldung (Auth::logout), vor dem Beenden der Sitzung. */
final class UserLoggedOut extends InfoEvent
{
    public const NAME = 'user.logout';

    public function __construct(public readonly int $id)
    {
        parent::__construct($id);
    }
}
