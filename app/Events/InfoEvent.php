<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Events;

/**
 * Ereignis ohne Seite/Eintrag als Gegenstand (Konten, Einstellungen, Formulare, Tabellen): Angaben als readonly-Eigenschaften
 * der Unterklasse. Altform (Listener mit dem Namen ohne Typ) bekommt das Ereignis-Objekt selbst.
 */
abstract class InfoEvent extends Event
{
    public function legacyArgs(): array
    {
        return [$this];
    }

    protected function record(): array
    {
        return get_object_vars($this);
    }
}
