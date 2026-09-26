<?php
declare(strict_types=1);

namespace Core\Blocks;

/** Übersetzungsfehler der Vorlagensprache eigener Blöcke – mit Zeilennummer der Vorlage (1-basiert, 0 = unbekannt). */
final class TemplateError extends \RuntimeException
{
    public function __construct(string $message, public readonly int $templateLine = 0)
    {
        parent::__construct($message);
    }

    /** Meldung für die Oberfläche: „Zeile 4: …“ */
    public function display(): string
    {
        return $this->templateLine > 0 ? __('Zeile {n}: {msg}', ['n' => $this->templateLine, 'msg' => $this->getMessage()]) : $this->getMessage();
    }
}
