<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/**
 * form.submitted: Besucher hat ein Formular abgeschickt, nach Prüfung, Spamschutz und Speichern (DataForms::submit).
 * $table = Kurzname, $values = geprüfte Werte (Klartext – auch bei verschlüsselten Anfragen; nicht protokollieren),
 * $entryId (Datentabelle) bzw. $ref (Anfrage-Eingang, verschlüsselt), $inbox = Eingangs-Tabelle.
 */
final class FormSubmitted extends InfoEvent
{
    public const NAME = 'form.submitted';

    public function __construct(public readonly array $definition, public readonly array $values, public readonly ?int $entryId = null,
        public readonly ?string $ref = null, public readonly bool $inbox = false)
    {
        parent::__construct(null);
    }

    public string $table { get => (string) ($this->definition['handle'] ?? ''); }
}
