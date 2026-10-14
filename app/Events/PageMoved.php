<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** page.moved: übergeordnete Seite oder Reihenfolge geändert (Seitenbaum, Seiteneinstellungen, API/MCP). $page = neuer Stand. */
final class PageMoved extends PageEvent
{
    public const NAME = 'page.moved';
    protected const STATE = 'live';

    public function __construct(array $page, public readonly ?int $oldParentId, ?int $userId = null)
    {
        parent::__construct($page, $userId);
    }

    /** Hat sich die übergeordnete Seite geändert (sonst nur die Reihenfolge)? */
    public function reparented(): bool
    {
        return ($this->page['parent_id'] ?? null) !== null ? (int) $this->page['parent_id'] !== $this->oldParentId : $this->oldParentId !== null;
    }
}
