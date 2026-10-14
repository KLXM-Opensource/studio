<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** user.login: Anmeldung in der Verwaltung erfolgreich (Auth::login – Passwort, Zwei-Faktor, Passkey, Einladung, Netzwerk). */
final class UserLoggedIn extends InfoEvent
{
    public const NAME = 'user.login';

    public function __construct(public readonly int $id, public readonly string $email, public readonly string $role, public readonly string $ip = '')
    {
        parent::__construct($id);
    }
}
