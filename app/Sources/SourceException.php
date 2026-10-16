<?php
declare(strict_types=1);

namespace Core\Sources;

/** Fehler beim Entpacken oder Lesen einer externen Quelle (Abruf: Core\Http\FetchException) – Meldung ist für die Oberfläche geeignet (übersetzt). */
class SourceException extends \Core\Http\FetchException
{
}
