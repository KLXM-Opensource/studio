<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** page.discarded: Entwurf verworfen, Arbeitsstand = veröffentlichte Fassung (Pages::discardDraft). Altform: fn(array $page) */
final class PageDiscarded extends PageEvent
{
    public const NAME = 'page.discarded';
    protected const STATE = 'draft';
}
