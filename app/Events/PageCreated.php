<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** page.created: Seite angelegt (Pages::create – Verwaltung, API/MCP, KI, Vorlagen, Import, Übersetzung). Stand: Entwurf. */
final class PageCreated extends PageEvent
{
    public const NAME = 'page.created';
}
