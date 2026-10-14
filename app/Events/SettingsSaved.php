<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/**
 * settings.saved: Einstellungen über die Verwaltung oder API/MCP gespeichert. $area: 'settings' (Website), 'system'
 * (Grundeinstellungen), 'design'. $keys = geänderte Schlüssel (ohne Werte – Geheimnisse bleiben in den Einstellungen).
 */
final class SettingsSaved extends InfoEvent
{
    public const NAME = 'settings.saved';

    public function __construct(public readonly string $area, public readonly array $keys)
    {
        parent::__construct(null);
    }
}
