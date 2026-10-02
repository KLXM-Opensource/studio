<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/** Ereignis an einer Seite: $page = Datensatz (Stand nach der Änderung, bei PageDeleted vor dem Löschen) */
abstract class PageEvent extends Event
{
    /** 'draft' (Arbeitsstand) oder 'live' (veröffentlichte Fassung) – je Ereignis fest */
    protected const STATE = 'draft';

    public readonly string $table;
    public readonly int $id;
    public readonly string $lang;
    public readonly string $state;

    public function __construct(public readonly array $page, ?int $userId = null)
    {
        parent::__construct($userId);
        $this->table = 'pages';
        $this->id = (int) ($page['id'] ?? 0);
        $this->lang = self::langOf($page['lang'] ?? null);
        $this->state = static::STATE;
    }

    public function legacyArgs(): array
    {
        return [$this->page];
    }

    protected function record(): array
    {
        return $this->page;
    }

    public static function langOf(?string $code): string
    {
        try {
            return \Core\Lang::norm($code);
        } catch (\Throwable) {
            return (string) ($code ?: 'de');
        }
    }
}
