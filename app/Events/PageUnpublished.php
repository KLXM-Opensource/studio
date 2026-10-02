<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** page.unpublished: offline genommen – nur bei echter Änderung (Pages::unpublish). Altform: fn(array $page) */
final class PageUnpublished extends PageEvent
{
    public const NAME = 'page.unpublished';
    protected const STATE = 'live';
}
