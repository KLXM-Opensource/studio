<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** user.deleted: Konto gelöscht (Verwaltung → Benutzer) – Erweiterungen entfernen ihre Zugänge des Kontos (z. B. App-Passwörter). */
final class UserDeleted extends InfoEvent
{
    public const NAME = 'user.deleted';

    public function __construct(public readonly int $id)
    {
        parent::__construct(null);
    }
}
