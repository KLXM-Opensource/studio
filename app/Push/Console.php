<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Push;

use Core\Sites;

/**
 * Kommandozeile der Push-Benachrichtigungen:
 *   push:send [--all] [--limit=N]          fällige Zustellungen senden (Cron: jede Minute bis alle 5 min, --all = alle Websites)
 *   push:status [--all]                    Funktion, Schlüssel, Abos, Warteschlange, letzter Versand
 *   push:keys [--generate | --regenerate --force]
 *                                          öffentlichen VAPID-Schlüssel zeigen bzw. anlegen; --regenerate: neues Paar (alle Abos ungültig!)
 *   push:test <email>                      Testnachricht an alle Geräte eines Kontos (sofort)
 *   push:selftest                          Verschlüsselung (Rundlauf, RFC-8291-Testvektor), JWT, Endpunkte, Abos und Warteschlange
 */
final class Console
{
    public static function run(string $cmd, array $args, string $console): int
    {
        $all = in_array('--all', $args, true);
        if ($all && in_array($cmd, ['push:send', 'push:status'], true)) {
            $fail = 0;
            $rest = array_values(array_filter($args, fn($a) => $a !== '--all' && !str_starts_with($a, '--site=')));
            foreach (array_keys(Sites::all()) as $k) {
                echo "── $k\n";
                passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($console) . ' ' . $cmd . ' ' . implode(' ', array_map('escapeshellarg', $rest)) . ' --site=' . escapeshellarg($k), $code);
                $fail = $fail ?: $code;
            }
            return $fail;
        }
        return match ($cmd) {
            'push:send' => self::send($args),
            'push:status' => self::status(),
            'push:keys' => self::keys($args),
            'push:test' => self::test($args),
            'push:selftest' => self::selftest(),
            default => 1,
        };
    }

    private static function send(array $args): int
    {
        if (!Push::on()) {
            echo 'Push-Benachrichtigungen sind auf ' . site()->key . " nicht eingeschaltet – nichts zu tun.\n";
            return 0;
        }
        if (!Keys::ready()) {
            fwrite(STDERR, "Keine VAPID-Schlüssel – php bin/console push:keys --generate\n");
            return 1;
        }
        $limit = 2000;
        foreach ($args as $a) if (preg_match('~^--limit=(\d+)$~', $a, $m)) $limit = max(1, (int) $m[1]);
        $st = Push::process($limit, 50.0);
        if (!empty($st['locked'])) {
            echo "Versand läuft bereits (Sperre) – übersprungen.\n";
            return 0;
        }
        echo sprintf("Push (%s): %d gesendet, %d später erneut, %d fehlgeschlagen, %d Abos abgelaufen, %d zu alt.\n",
            site()->key, $st['sent'], $st['retry'], $st['failed'], $st['gone'], $st['expired']);
        return 0;
    }

    private static function status(): int
    {
        $s = Push::status();
        $rows = [
            'Funktion' => $s['feature'] ? 'an' : 'aus',
            'Schlüssel' => $s['keys'] ? 'vorhanden (' . $s['fingerprint'] . ($s['created'] ? ', ' . $s['created'] : '') . ')' : 'fehlen',
            'Umgebung' => $s['environment'] . ($s['visitors_allowed'] ? '' : ' (keine Nachrichten an Besucher)'),
            'Geräte Redaktion' => $s['devices'] . ' von ' . $s['people'] . ' Personen',
            'Besucher-Abos' => (string) $s['visitors'],
            'Warteschlange' => (string) $s['queued'],
            '7 Tage' => $s['sent7'] . ' gesendet, ' . $s['failed7'] . ' fehlgeschlagen, ' . $s['gone7'] . ' abgelaufen',
            'Letzter Lauf' => (string) ($s['last_run']['at'] ?? 'nie'),
        ];
        foreach ($s['topics'] as $t => $n) $rows['Thema ' . $t] = (string) $n;
        if ($s['stale']) $rows['Veraltet'] = (string) $s['stale'];
        foreach ($rows as $k => $v) echo mb_str_pad($k, 20) . $v . "\n";
        return 0;
    }

    private static function keys(array $args): int
    {
        if (in_array('--regenerate', $args, true)) {
            if (!in_array('--force', $args, true)) {
                fwrite(STDERR, "ACHTUNG: Neue Schlüssel machen ALLE bestehenden Abos aller Websites dieser Installation ungültig.\n"
                    . "Personen der Verwaltung abonnieren beim nächsten Besuch still neu; Besucher, die die Website länger nicht aufrufen,\n"
                    . "erhalten bis dahin keine Mitteilungen mehr. Fortfahren mit: push:keys --regenerate --force\n");
                return 1;
            }
            if (!Keys::regenerate()) {
                fwrite(STDERR, "Schlüssel konnten nicht geschrieben werden (config/config.local.php beschreibbar?).\n");
                return 1;
            }
            // Gespeicherte Seiten tragen den alten öffentlichen Schlüssel (Block „Benachrichtigungen abonnieren“) – Seiten-Caches leeren
            foreach (array_keys(Sites::all()) as $k) {
                passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(ROOT . '/bin/console') . ' cache:clear --site=' . escapeshellarg($k) . ' > /dev/null');
            }
            echo "Neue VAPID-Schlüssel gespeichert (config/config.local.php), Seiten-Caches geleert.\n";
        } elseif (in_array('--generate', $args, true)) {
            if (Keys::ready()) echo "Schlüssel sind schon vorhanden (neu nur mit --regenerate --force).\n";
            elseif (!Keys::ensure()) {
                fwrite(STDERR, "Schlüssel konnten nicht geschrieben werden (config/config.local.php beschreibbar?).\n");
                return 1;
            } else echo "VAPID-Schlüssel angelegt (config/config.local.php).\n";
        }
        $k = Keys::get();
        if (!$k) {
            echo "Keine VAPID-Schlüssel. Anlegen: php bin/console push:keys --generate (oder Funktion „push“ einschalten).\n";
            return 1;
        }
        echo "Öffentlicher Schlüssel (applicationServerKey):\n  " . Keys::publicB64() . "\nFingerabdruck: " . Keys::fingerprint()
            . ($k['created'] ? "\nErzeugt: " . $k['created'] : '') . "\nKontakt (VAPID sub): " . Keys::subject()
            . "\nGeheimer Schlüssel: in config/config.local.php (" . Keys::PRIVATE . ") – nie weitergeben.\n";
        return 0;
    }

    private static function test(array $args): int
    {
        $email = (string) (array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')))[0] ?? '');
        $u = $email !== '' ? app()->db->fetch('SELECT id FROM users WHERE LOWER(email) = LOWER(?)', [$email]) : null;
        if (!$u) {
            fwrite(STDERR, "Aufruf: push:test <email> [--site=key] – Konto nicht gefunden.\n");
            return 1;
        }
        $r = Push::test((int) $u['id']);
        echo "Geräte: {$r['devices']}, gesendet: {$r['sent']}, fehlgeschlagen: {$r['failed']}" . ($r['error'] ? " – {$r['error']}" : '') . "\n";
        return $r['sent'] > 0 ? 0 : 1;
    }

    private static function selftest(): int
    {
        $res = SelfTest::run();
        foreach ($res['fails'] as $f) echo "  FEHLER: $f\n";
        echo "  {$res['ok']} Prüfungen bestanden, " . count($res['fails']) . " fehlgeschlagen\n";
        return $res['fails'] ? 1 : 0;
    }
}
