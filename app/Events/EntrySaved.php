<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/**
 * entry.saved: Eintrag angelegt oder geändert (Entries::save – Verwaltung, Website, API/MCP, Quellen-Abgleich, Formulare).
 * $created = neu angelegt, $old = Stand davor (null bei neuen). $state: 'live', wenn der Eintrag veröffentlicht ist.
 * Altform: fn(array $table, array $entry, bool $created, ?array $old)
 */
final class EntrySaved extends EntryEvent
{
    public const NAME = 'entry.saved';

    public readonly bool $created;

    public function __construct(array $definition, array $entry, public readonly ?array $old = null, ?int $userId = null)
    {
        parent::__construct($definition, $entry, null, $userId);
        $this->created = $old === null;
    }

    protected function stateOf(?array $entry): string
    {
        return ($entry['status'] ?? '') === 'published' ? 'live' : 'draft';
    }

    public function legacyArgs(): array
    {
        return [$this->definition, $this->entry, $this->created, $this->old];
    }
}
