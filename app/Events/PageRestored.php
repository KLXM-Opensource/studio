<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** page.restored: ältere Version als Entwurf wiederhergestellt (Verwaltung, API/MCP). Danach folgt wie immer page.saved. */
final class PageRestored extends PageEvent
{
    public const NAME = 'page.restored';

    public function __construct(array $page, public readonly int $revisionId, ?int $userId = null)
    {
        parent::__construct($page, $userId);
    }
}
