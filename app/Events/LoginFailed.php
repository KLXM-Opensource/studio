<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/**
 * user.login_failed: Anmeldung abgelehnt (Auth::attempt). $reason: 'password' | 'disabled' | 'limited' (zu viele Versuche).
 * $email so eingegeben (kann es nicht geben) – nur für Sicherheits-Auswertung, nicht anzeigen.
 */
final class LoginFailed extends InfoEvent
{
    public const NAME = 'user.login_failed';

    public function __construct(public readonly string $email, public readonly string $ip, public readonly string $reason)
    {
        parent::__construct(null);
    }
}
