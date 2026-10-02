<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** page.deleted: Seite gelöscht (Verwaltung, API/MCP); $page = Stand vor dem Löschen. Altform: fn(array $page) */
final class PageDeleted extends PageEvent
{
    public const NAME = 'page.deleted';
    protected const STATE = 'live';
}
