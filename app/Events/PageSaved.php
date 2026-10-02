<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** page.saved: Entwurf gespeichert (Pages::saveDraft). Altform: fn(array $page, ?int $userId) */
final class PageSaved extends PageEvent
{
    public const NAME = 'page.saved';
    protected const STATE = 'draft';

    public function legacyArgs(): array
    {
        return [$this->page, $this->userId];
    }
}
