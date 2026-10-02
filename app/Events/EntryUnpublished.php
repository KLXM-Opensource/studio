<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** entry.unpublished: Status wechselt auf Entwurf (nur bei echter Änderung). Altform: fn(array $table, array $entry) */
final class EntryUnpublished extends EntryEvent
{
    public const NAME = 'entry.unpublished';
    protected const STATE = 'draft';

    public function __construct(array $definition, array $entry, ?int $userId = null)
    {
        parent::__construct($definition, $entry, null, $userId);
    }
}
