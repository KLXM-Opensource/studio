<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Network;

use Core\Mailer;
use Core\Sites;

/**
 * „Systemmeldungen an“: Empfänger für Meldungen der ganzen Installation – Integrität der Dateien, fehlgeschlagene Sicherung außer
 * Haus, Fehler in Geplanten Aufgaben. Eingestellt unter Netzwerk → E-Mail (Netzwerk-Datenbank, Tabelle network_mail, Schlüssel
 * system_to). Leer: alle aktiven Netzwerk-Konten; ohne Netzwerk (Einzelinstallation) die bisherigen Empfänger des Aufrufers.
 * Versand über Core\Mailer mit dem Netzwerk-Konto (Option network) – Absender und Impressum der Netzwerk-Website.
 */
final class SystemMail
{
    private const TABLE = 'network_mail';
    public const KEY = 'system_to';
    public const MAX = 10;

    private static function db(): ?\Core\Database
    {
        try {
            $db = Network::db();
            $db->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (k VARCHAR(40) NOT NULL PRIMARY KEY, v TEXT)');
            return $db;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Eingetragene Adressen (leer = nicht eingestellt) @return list<string> */
    public static function configured(): array
    {
        $raw = self::db()?->fetchValue('SELECT v FROM ' . self::TABLE . ' WHERE k = ?', [self::KEY]);
        return self::parse((string) ($raw ?? ''))[0];
    }

    /** Adressen aus Freitext (Komma, Semikolon, Zeilen) – [gültige, ungültige] @return array{0: list<string>, 1: list<string>} */
    public static function parse(string $raw): array
    {
        $ok = $bad = [];
        foreach (preg_split('~[\s,;]+~', strtolower(trim($raw))) ?: [] as $a) {
            if ($a === '') continue;
            if (filter_var($a, FILTER_VALIDATE_EMAIL)) $ok[$a] = $a; else $bad[] = $a;
        }
        return [array_values($ok), $bad];
    }

    /** Speichern; Rückgabe: Fehlermeldung oder null */
    public static function save(string $raw): ?string
    {
        [$ok, $bad] = self::parse($raw);
        if ($bad) return __('Keine gültige E-Mail-Adresse: {list}', ['list' => implode(', ', $bad)]);
        if (count($ok) > self::MAX) return __('Höchstens {n} Adressen.', ['n' => self::MAX]);
        $db = self::db() ?? throw new \RuntimeException(__('Keine Netzwerk-Datenbank.'));
        $db->query('DELETE FROM ' . self::TABLE . ' WHERE k = ?', [self::KEY]);
        if ($ok) $db->insert(self::TABLE, ['k' => self::KEY, 'v' => implode(', ', $ok)]);
        return null;
    }

    /** Aktive Netzwerk-Konten (Rückfall, wenn nichts eingetragen ist) @return list<string> */
    public static function networkAdmins(): array
    {
        try {
            return array_values(array_map(fn($a) => strtolower((string) $a['email']), array_filter(Network::accounts(), fn($a) => !(int) $a['disabled'])));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Wirksame Empfänger: eingetragen → sonst (mehrere Websites) Netzwerk-Konten → sonst $fallback (bisherige Empfänger).
     * @param list<string> $fallback  @return list<string>
     */
    public static function recipients(array $fallback = []): array
    {
        if ($c = self::configured()) return $c;
        if (Sites::multi() && ($n = self::networkAdmins())) return $n;
        return array_values(array_unique(array_filter($fallback, fn($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
    }

    /** Woher die Empfänger kommen: configured | network | fallback */
    public static function source(): string
    {
        if (self::configured()) return 'configured';
        return Sites::multi() && self::networkAdmins() ? 'network' : 'fallback';
    }

    /**
     * Meldung senden. Mit mehreren Websites über das Netzwerk-Konto (Absender/Impressum der Netzwerk-Website), sonst wie jede
     * Benachrichtigung der Website. Rückgabe: Anzahl Empfänger (0 = niemand bzw. nicht gesendet).
     * @param list<string> $fallback
     */
    public static function send(string $subject, string $text, array $fallback = []): int
    {
        $to = self::recipients($fallback);
        if (!$to) return 0;
        $err = Mailer::send($subject, $text, $to, ['purpose' => 'notify', 'network' => Sites::multi()]);
        return $err === null ? count($to) : 0;
    }

    /**
     * Wie send(), aber je Schlüssel nur einmal, bis clear() ihn zurücksetzt (z. B. „Sicherung fehlgeschlagen“ bis zur nächsten
     * erfolgreichen) – Merkliste in der Netzwerk-Datenbank bzw. der eigenen. Rückgabe wie send().
     * @param list<string> $fallback
     */
    public static function once(string $key, string $subject, string $text, array $fallback = []): int
    {
        $k = 'notice:' . substr(hash('sha256', $key), 0, 32);
        $db = self::db();
        if ($db && $db->fetchValue('SELECT 1 FROM ' . self::TABLE . ' WHERE k = ?', [$k])) return 0;
        $n = self::send($subject, $text, $fallback);
        if ($db && $n > 0) $db->insert(self::TABLE, ['k' => $k, 'v' => (string) time()]);
        return $n;
    }

    /** Merker von once() zurücksetzen (Fehler behoben) */
    public static function clear(string $key): void
    {
        self::db()?->query('DELETE FROM ' . self::TABLE . ' WHERE k = ?', ['notice:' . substr(hash('sha256', $key), 0, 32)]);
    }
}
