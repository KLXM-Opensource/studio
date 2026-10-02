<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** page.published: veröffentlicht bzw. wieder online (Pages::publish). Altform: fn(array $page) */
final class PagePublished extends PageEvent
{
    public const NAME = 'page.published';
    protected const STATE = 'live';
}
