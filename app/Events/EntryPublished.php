<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** entry.published: Status wechselt auf veröffentlicht (Entries::save, setStatus – nur bei echter Änderung). Altform: fn(array $table, array $entry) */
final class EntryPublished extends EntryEvent
{
    public const NAME = 'entry.published';
    protected const STATE = 'live';

    public function __construct(array $definition, array $entry, ?int $userId = null)
    {
        parent::__construct($definition, $entry, null, $userId);
    }
}
