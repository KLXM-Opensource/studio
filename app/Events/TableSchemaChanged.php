<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/**
 * table.schema_changed: Datentabelle angelegt, Felder geändert oder gelöscht (Core\Data\Tables). $action: 'created' | 'updated'
 * | 'deleted'; $added/$dropped = Feldnamen (bei updated).
 */
final class TableSchemaChanged extends InfoEvent
{
    public const NAME = 'table.schema_changed';

    public function __construct(public readonly array $definition, public readonly string $action, public readonly array $added = [], public readonly array $dropped = [])
    {
        parent::__construct(null);
    }

    public string $table { get => (string) ($this->definition['handle'] ?? ''); }
}
