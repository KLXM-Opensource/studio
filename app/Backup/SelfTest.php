<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Backup;

/**
 * php bin/console backup:selftest – Verschlüsselung (Hin und zurück, falscher Schlüssel, veränderte Datei), Auswahl der Dateien der
 * Installations-Sicherung (keine Caches/Sitzungen/Schlüssel, Konfiguration dabei), Liste der Kern-Kits/-Erweiterungen gegen Git,
 * Erkennen der Protokolle, Ziel „Ordner“ (schreiben, auflisten, holen, löschen) und neueste lokale Sicherung je Präfix.
 */
final class SelfTest
{
    public static function run(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails);
        $tmp = sys_get_temp_dir() . '/kbk-selftest-' . bin2hex(random_bytes(4));
        @mkdir($tmp, 0700, true);
        try {
            // Verschlüsselung: mehrere Stücke (> 1 MB), Rundlauf, falscher Schlüssel, veränderte Datei
            $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
            $plain = random_bytes(2_500_000);
            file_put_contents("$tmp/a.bin", $plain);
            Vault::encryptFile("$tmp/a.bin", "$tmp/a.kbk", $key);
            Vault::decryptFile("$tmp/a.kbk", "$tmp/b.bin", $key);
            $eq('Verschlüsseln und Entschlüsseln (2,5 MB, mehrere Stücke)', hash_file('sha256', "$tmp/b.bin"), hash('sha256', $plain));
            $eq('Datei ist verschlüsselt (kein Klartext)', str_contains((string) file_get_contents("$tmp/a.kbk"), substr($plain, 1000, 64)), false);
            $wrong = false;
            try { Vault::decryptFile("$tmp/a.kbk", "$tmp/c.bin", sodium_crypto_secretstream_xchacha20poly1305_keygen()); } catch (\RuntimeException) { $wrong = true; }
            $eq('Falscher Schlüssel wird abgelehnt', $wrong, true);
            $raw = (string) file_get_contents("$tmp/a.kbk");
            $raw[200] = $raw[200] === 'x' ? 'y' : 'x';
            file_put_contents("$tmp/d.kbk", $raw);
            $tampered = false;
            try { Vault::decryptFile("$tmp/d.kbk", "$tmp/e.bin", $key); } catch (\RuntimeException) { $tampered = true; }
            $eq('Veränderte Datei wird abgelehnt', $tampered, true);
            file_put_contents("$tmp/empty.bin", '');
            Vault::encryptFile("$tmp/empty.bin", "$tmp/empty.kbk", $key);
            Vault::decryptFile("$tmp/empty.kbk", "$tmp/empty2.bin", $key);
            $eq('Leere Datei', filesize("$tmp/empty2.bin"), 0);
            $eq('Schlüssel als Text hin und zurück', Vault::import(Vault::export($key)), $key);
            $eq('Schlüssel: falsches Format', Vault::import('kbk1.abc'), null);

            // Auswahl der Dateien der Installations-Sicherung
            $files = array_keys(Installation::files(false));
            $bad = array_filter($files, fn($f) => preg_match('~^storage/(cache|sessions|logs|backups|backup-offsite|search|tmp|sites|database|pools)/~', $f) || str_contains($f, '/node_modules/'));
            $eq('Keine Caches, Sitzungen, Sicherungen, Schlüssel oder Website-Daten', array_values($bad), []);
            if (is_file(ROOT . '/config/config.local.php')) $eq('config.local.php gehört dazu', in_array('config/config.local.php', $files, true), true);
            $eq('Ohne Code: keine kits/ oder extensions/', array_values(array_filter($files, fn($f) => preg_match('~^(kits|extensions)/~', $f))), []);

            // Kern-Kits/-Erweiterungen wie im Repository (nur in einem Git-Stand prüfbar)
            if (is_dir(ROOT . '/.git') && function_exists('exec')) {
                exec('git -C ' . escapeshellarg(ROOT) . ' ls-files kits extensions 2>/dev/null', $ls);
                $kits = $exts = [];
                foreach ($ls as $l) {
                    $p = explode('/', $l);
                    if (count($p) > 2 && $p[0] === 'kits') $kits[$p[1]] = 1;
                    if (count($p) > 2 && $p[0] === 'extensions') $exts[$p[1]] = 1;
                }
                ksort($kits); ksort($exts);
                $b = Installation::BUNDLED_KITS; sort($b);
                $e = Installation::BUNDLED_EXTENSIONS; sort($e);
                $eq('BUNDLED_KITS wie im Repository', $b, array_keys($kits));
                $eq('BUNDLED_EXTENSIONS wie im Repository', $e, array_keys($exts));
            }

            // Protokolle
            $eq('Protokoll aus der Adresse', array_map([Transport::class, 'kind'], ['https://x.org/dav', 'sftp://h/p', 'ftps://h', 'ftp://h', '/mnt/nas', 'smb://h']),
                ['webdav', 'sftp', 'ftps', 'ftp', 'dir', '']);

            // Ziel „Ordner“
            $tr = new Transport(['url' => "$tmp/ziel"]);
            $eq('Ordner: Verbindungstest', $tr->test(), null);
            $tr->put("$tmp/a.kbk", 'site-20260101-020000.tar.gz.kbk');
            $tr->get('site-20260101-020000.tar.gz.kbk', "$tmp/back.kbk");
            $eq('Ordner: schreiben und holen', hash_file('sha256', "$tmp/back.kbk"), hash_file('sha256', "$tmp/a.kbk"));
            $tr->delete('site-20260101-020000.tar.gz.kbk');
            $eq('Ordner: löschen', $tr->list(), []);
            $badName = false;
            try { $tr->put("$tmp/a.kbk", '../x'); } catch (\InvalidArgumentException) { $badName = true; }
            $eq('Ungültiger Dateiname wird abgelehnt', $badName, true);

            // Neueste lokale Sicherung je Präfix
            @mkdir("$tmp/b", 0700);
            foreach (['default-20260101-020000.tar.gz', 'default-20260102-020000.tar.gz', 'pool-marke-20260101-022000.tar.gz', 'notiz.txt', 'install-20260102-033000.tar.gz'] as $f) touch("$tmp/b/$f");
            $eq('Neueste je Präfix', array_map('basename', Offsite::latestLocal("$tmp/b")),
                ['default' => 'default-20260102-020000.tar.gz', 'install' => 'install-20260102-033000.tar.gz', 'pool-marke' => 'pool-marke-20260101-022000.tar.gz']);

            // Ernstfall: Auswahl am Ziel (neueste je Präfix, bis zu einem Datum; fremde Dateien ignoriert)
            $remote = ['default-20261020-020000.tar.gz.kbk', 'default-20261022-020000.tar.gz.kbk', 'install-20261021-033000.tar.gz.kbk',
                'install-20261022-033000.tar.gz.kbk', 'pool-marke-20261022-022000.tar.gz.kbk', 'notizen.txt', 'default-20261022-020000.tar.gz'];
            $eq('Wiederherstellen: neueste je Präfix', Restore::pick($remote),
                ['default' => 'default-20261022-020000.tar.gz.kbk', 'install' => 'install-20261022-033000.tar.gz.kbk', 'pool-marke' => 'pool-marke-20261022-022000.tar.gz.kbk']);
            $eq('Wiederherstellen: Stand bis zu einem Datum', Restore::pick($remote, '20261021'),
                ['default' => 'default-20261020-020000.tar.gz.kbk', 'install' => 'install-20261021-033000.tar.gz.kbk']);
        } catch (\Throwable $e) {
            $fails[] = 'Ausnahme: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
        } finally {
            Archive::remove($tmp);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
