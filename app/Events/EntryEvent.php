<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/**
 * Ereignis an einem Eintrag einer Datentabelle: $table = Kurzname (handle), $definition = Tabellen-Definition
 * (Core\Data\Tables), $entry = Datensatz nach der Änderung (EntryDeleted: Stand vor dem Löschen, falls bekannt).
 */
abstract class EntryEvent extends Event
{
    protected const STATE = 'draft';

    public readonly string $table;
    public readonly int $id;
    public readonly string $lang;
    public readonly string $state;

    public function __construct(public readonly array $definition, public readonly ?array $entry, ?int $id = null, ?int $userId = null)
    {
        parent::__construct($userId);
        $this->table = (string) ($definition['handle'] ?? '');
        $this->id = (int) ($id ?? $entry['id'] ?? 0);
        $this->lang = PageEvent::langOf($entry['lang'] ?? null);
        $this->state = $this->stateOf($entry);
    }

    /** 'draft' oder 'live' – je Ereignis fest (STATE), bei EntrySaved nach dem Status des Eintrags */
    protected function stateOf(?array $entry): string
    {
        return static::STATE;
    }

    public function legacyArgs(): array
    {
        return [$this->definition, $this->entry];
    }

    protected function record(): array
    {
        return $this->entry ?? ['id' => $this->id];
    }
}
