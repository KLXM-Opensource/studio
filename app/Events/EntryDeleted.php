<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/**
 * entry.deleted: Eintrag gelöscht (Entries::delete). $entry = Stand vor dem Löschen (nur geladen, wenn jemand zuhört), $id immer.
 * Altform: fn(array $table, int $id)
 */
final class EntryDeleted extends EntryEvent
{
    public const NAME = 'entry.deleted';
    protected const STATE = 'live';

    public function __construct(array $definition, int $id, ?array $entry = null, ?int $userId = null)
    {
        parent::__construct($definition, $entry, $id, $userId);
    }

    public function legacyArgs(): array
    {
        return [$this->definition, $this->id];
    }
}
