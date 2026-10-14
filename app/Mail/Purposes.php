<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Mail;

use Core\Extensions;

/**
 * Zwecke des E-Mail-Versands – je Zweck wählt die Website ein Konto (Core\Mail\Accounts::route). Mailer::send(…, ['purpose' => …]);
 * ohne Angabe: 'account' bei Mails zur Kontosicherheit, sonst 'notify'. Erweiterungen melden eigene Zwecke an
 * (Extension::mailPurpose, Schlüssel „ext:{erweiterung}.{name}“, z. B. ein Newsletter).
 */
final class Purposes
{
    /** Zwecke des Cores: Schlüssel → [Beschriftung, Erklärung] */
    public const CORE = [
        'account' => ['Konto & Sicherheit', 'Einladungen, Passwort vergessen, E-Mail-Änderung, Hinweise zur Anmeldung.'],
        'notify' => ['Benachrichtigungen an die Redaktion', 'Neue Anfragen und Einträge, Support, Chat, Freigaben, Testnachricht.'],
        'visitor' => ['Nachrichten an Besucher', 'Eingangsbestätigungen von Formularen und Zustellung von Anfragen per E-Mail.'],
        'newsletter' => ['Newsletter und Massenversand', 'Für Erweiterungen (z. B. Newsletter) – viele Empfänger; oft ein eigener Dienst über eine API.'],
    ];

    /** @return array<string, array{label: string, help: string, owner: string}> alle Zwecke (Core + Erweiterungen) */
    public static function all(): array
    {
        $out = [];
        foreach (self::CORE as $k => [$label, $help]) $out[$k] = ['label' => __($label), 'help' => __($help), 'owner' => 'core'];
        foreach (Extensions::mailPurposes() as $k => $p) $out[$k] ??= $p;
        return $out;
    }

    public static function valid(string $key): bool
    {
        return isset(self::all()[$key]);
    }
}
