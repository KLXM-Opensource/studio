<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** page.translated: Übersetzung angelegt (Pages::translate). $page = neue Sprachfassung, $sourceId = Ausgangsseite. */
final class PageTranslated extends PageEvent
{
    public const NAME = 'page.translated';

    public function __construct(array $page, public readonly int $sourceId, ?int $userId = null)
    {
        parent::__construct($page, $userId);
    }
}
