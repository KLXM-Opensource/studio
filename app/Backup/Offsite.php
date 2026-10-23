<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Backup;

/**
 * Sicherung außer Haus: die neuesten Sicherungen (site:backup, pool:backup, shared:backup) und eine frische Sicherung der
 * Installation (Core\Backup\Installation) verschlüsselt (Core\Backup\Vault) zu einem oder mehreren Zielen übertragen
 * (Core\Backup\Transport: WebDAV, SFTP, FTPS/FTP, Ordner). Am Ziel bleiben je Website/Pool/Tabelle/Installation die neuesten
 * „keep“ Dateien; andere Dateien im Zielordner werden nie angefasst.
 *
 * Optional und standardmäßig aus: ohne eingerichtetes Ziel tut backup:offsite nichts. Einrichten unter Grundeinstellungen →
 * Sicherung außer Haus (nur wer die Installation verwaltet) oder in storage/backup-offsite/targets.json.
 * Geplante Aufgabe „backup.offsite“ täglich 03:30 (nach den lokalen Sicherungen um 02:00–02:40).
 */
final class Offsite
{
    private const FILE = 'targets.json';
    private const STATE = 'state.json';
    /** Muster der lokalen Sicherungen: {präfix}-JJJJMMTT-HHMMSS.tar.gz */
    private const PATTERN = '~^(?<prefix>[a-z0-9][a-z0-9_.-]*?)-(?<stamp>\d{8}-\d{6})\.tar(?:\.gz)?$~';

    // ------------------------------------------------------------------ Ziele

    /** @return array<string, array> [id => Ziel] – Passwort verschlüsselt */
    public static function targets(): array
    {
        $raw = json_decode((string) @file_get_contents(Vault::dir() . '/' . self::FILE), true);
        return is_array($raw) ? $raw : [];
    }

    public static function enabled(): array
    {
        return array_filter(self::targets(), fn($t) => !empty($t['enabled']));
    }

    /**
     * Ziel anlegen/ändern. $in: label, url, user, pass (leer = unverändert), key_file, fingerprint, insecure, keep, enabled.
     * @return array{0: ?string, 1: array<string, string>} [id, Fehler je Feld]
     */
    public static function save(?string $id, array $in): array
    {
        $all = self::targets();
        $old = $id !== null ? ($all[$id] ?? null) : null;
        $url = trim((string) ($in['url'] ?? ''));
        $err = [];
        if (Transport::kind($url) === '') $err['url'] = __('Adresse mit https:// (WebDAV), sftp://, ftps://, ftp:// oder ein Ordner (/…) angeben.');
        if (Transport::kind($url) === 'dir' && (str_starts_with(realpath(dirname($url)) ?: $url, ROOT) || str_contains($url, '..'))) $err['url'] = __('Der Ordner darf nicht in der Installation liegen.');
        $label = trim((string) ($in['label'] ?? '')) ?: (string) (parse_url($url, PHP_URL_HOST) ?: $url);
        if ($err) return [null, $err];
        $id ??= substr(bin2hex(random_bytes(6)), 0, 10);
        $pass = (string) ($in['pass'] ?? '');
        $all[$id] = [
            'label' => mb_substr($label, 0, 80), 'url' => $url, 'user' => trim((string) ($in['user'] ?? '')),
            'pass' => $pass !== '' ? self::seal($pass) : (string) ($old['pass'] ?? ''),
            'key_file' => trim((string) ($in['key_file'] ?? '')), 'fingerprint' => trim((string) ($in['fingerprint'] ?? '')),
            'insecure' => !empty($in['insecure']), 'keep' => max(1, min(365, (int) ($in['keep'] ?? 14))), 'enabled' => !empty($in['enabled']),
            'code' => !array_key_exists('code', $in) || !empty($in['code']),
        ];
        self::write(self::FILE, $all);
        return [$id, []];
    }

    public static function remove(string $id): void
    {
        $all = self::targets();
        unset($all[$id]);
        self::write(self::FILE, $all);
    }

    public static function transport(array $t): Transport
    {
        return new Transport(['pass' => self::open((string) ($t['pass'] ?? ''))] + $t);
    }

    // ------------------------------------------------------------------ Ablauf

    /** Stand je Ziel: [id => ['ok' => Zeit, 'error' => …, 'at' => Zeit, 'uploaded' => n, 'bytes' => n, 'remote' => n]] */
    public static function state(): array
    {
        $raw = json_decode((string) @file_get_contents(Vault::dir() . '/' . self::STATE), true);
        return is_array($raw) ? $raw : [];
    }

    /** Neueste lokale Sicherung je Präfix (Website, pool-…, shared-…, install) in storage/backups: [präfix => Pfad] */
    public static function latestLocal(string $dir = ''): array
    {
        $dir = $dir ?: ROOT . '/storage/backups';
        $out = [];
        if (!is_dir($dir)) return $out;
        foreach (scandir($dir) ?: [] as $f) {
            if (!preg_match(self::PATTERN, $f, $m)) continue;
            if (!isset($out[$m['prefix']]) || strcmp(basename($out[$m['prefix']]), $f) < 0) $out[$m['prefix']] = "$dir/$f";
        }
        ksort($out);
        return $out;
    }

    /**
     * Alles übertragen. $only: nur dieses Ziel; $log: fn(string $zeile). Gibt je Ziel ['ok' => bool, 'uploaded' => n, 'error' => ?string] zurück.
     * Legt vorher eine frische Sicherung der Installation an (lokal rotiert wie die anderen).
     */
    public static function run(?string $only = null, ?callable $log = null): array
    {
        $log ??= fn(string $s) => null;
        $targets = $only !== null ? array_intersect_key(self::targets(), [$only => 1]) : self::enabled();
        if (!$targets) { $log('Kein Sicherungsziel eingerichtet bzw. eingeschaltet – nichts zu tun.'); return []; }
        $dir = ROOT . '/storage/backups';
        $code = (bool) array_filter($targets, fn($t) => $t['code'] ?? true);
        $install = Installation::create($dir, $code);
        $log('Installation gesichert: ' . basename($install) . ' (' . self::size((int) filesize($install)) . ')');
        self::rotateLocal($dir, 'install', (int) (\Core\Network\Network::install()['backup_keep'] ?? app()->config->get('backup_keep', 14)));
        $files = self::latestLocal($dir);
        $stale = array_filter($files, fn($f, $p) => $p !== 'install' && filemtime($f) < time() - 30 * 3600, ARRAY_FILTER_USE_BOTH);
        if ($stale) $log('Hinweis: älter als 30 Stunden – ' . implode(', ', array_keys($stale)) . ' (laufen die geplanten Sicherungen?)');
        $enc = [];   // einmal verschlüsseln, an alle Ziele
        $state = self::state();
        $report = [];
        try {
            foreach ($targets as $id => $t) {
                $tr = self::transport($t);
                $up = 0;
                $bytes = 0;
                try {
                    $remote = $tr->list();
                    foreach ($files as $prefix => $path) {
                        $name = basename($path) . Vault::EXT;
                        if (in_array($name, $remote, true)) continue;
                        if (!isset($enc[$name])) {
                            $enc[$name] = ROOT . '/storage/tmp/' . $name;
                            @mkdir(dirname($enc[$name]), 0700, true);
                            Vault::encryptFile($path, $enc[$name]);
                        }
                        $tr->put($enc[$name], $name);
                        $up++;
                        $bytes += (int) filesize($enc[$name]);
                        $remote[] = $name;
                        $log("  {$t['label']}: $name übertragen");
                    }
                    $deleted = self::rotateRemote($tr, $remote, (int) ($t['keep'] ?? 14));
                    if ($deleted) $log("  {$t['label']}: " . count($deleted) . ' ältere entfernt');
                    $state[$id] = ['ok' => time(), 'at' => time(), 'error' => null, 'uploaded' => $up, 'bytes' => $bytes,
                        'remote' => count(array_filter($remote, fn($f) => str_ends_with($f, Vault::EXT))) - count($deleted)];
                    $report[$id] = ['ok' => true, 'uploaded' => $up, 'error' => null];
                } catch (\Throwable $e) {
                    $state[$id] = ['at' => time(), 'error' => $e->getMessage()] + ($state[$id] ?? []);
                    $report[$id] = ['ok' => false, 'uploaded' => $up, 'error' => $e->getMessage()];
                    $log("  {$t['label']}: FEHLER – " . $e->getMessage());
                }
            }
        } finally {
            foreach ($enc as $f) @unlink($f);
            self::write(self::STATE, $state);
        }
        return $report;
    }

    /** Am Ziel je Präfix die neuesten $keep verschlüsselten Sicherungen behalten → gelöschte Namen */
    private static function rotateRemote(Transport $tr, array $remote, int $keep): array
    {
        $groups = [];
        foreach ($remote as $f) {
            if (!str_ends_with($f, Vault::EXT) || !preg_match(self::PATTERN, substr($f, 0, -strlen(Vault::EXT)), $m)) continue;
            $groups[$m['prefix']][] = $f;
        }
        $deleted = [];
        foreach ($groups as $list) {
            rsort($list, SORT_STRING);
            foreach (array_slice($list, max(1, $keep)) as $old) { $tr->delete($old); $deleted[] = $old; }
        }
        return $deleted;
    }

    private static function rotateLocal(string $dir, string $prefix, int $keep): void
    {
        if ($keep < 1) return;
        $list = array_values(array_filter(scandir($dir) ?: [], fn($f) => preg_match(self::PATTERN, $f, $m) && $m['prefix'] === $prefix));
        rsort($list, SORT_STRING);
        foreach (array_slice($list, $keep) as $f) @unlink("$dir/$f");
    }

    /**
     * Für health und die Übersicht: je eingeschaltetem Ziel ein Eintrag – Fehler beim letzten Versuch oder seit über 3 Tagen nichts
     * übertragen = Warnung (null). Ohne Ziel: [] (die Sicherung außer Haus ist optional).
     */
    public static function health(): array
    {
        $out = [];
        $state = self::state();
        foreach (self::enabled() as $id => $t) {
            $s = $state[$id] ?? [];
            $ok = (int) ($s['ok'] ?? 0);
            if (!empty($s['error'])) $out[__('Sicherung außer Haus „{label}“: {msg}', ['label' => $t['label'], 'msg' => mb_strimwidth((string) $s['error'], 0, 120, '…')])] = null;
            elseif ($ok < time() - 3 * 86400) $out[__('Sicherung außer Haus „{label}“: seit über 3 Tagen nichts übertragen', ['label' => $t['label']])] = null;
            else $out[__('Sicherung außer Haus „{label}“ aktuell', ['label' => $t['label']])] = true;
        }
        return $out;
    }

    // ------------------------------------------------------------------ Hilfen

    public static function size(int $b): string
    {
        return $b >= 1073741824 ? round($b / 1073741824, 1) . ' GB' : ($b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int) round($b / 1024)) . ' KB');
    }

    private static function write(string $file, array $data): void
    {
        @mkdir(Vault::dir(), 0700, true);
        file_put_contents(Vault::dir() . "/$file", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
        @chmod(Vault::dir() . "/$file", 0600);
    }

    /** Passwörter der Ziele: verschlüsselt mit dem Schlüssel der Installation (gleich für alle Websites, auch über --all) */
    private static function boxKey(): string
    {
        $i = \Core\Network\Network::install();
        $k = (string) (($i['network_key'] ?? '') ?: ($i['app_key'] ?? '') ?: app()->config->get('app_key'));
        if (strlen($k) < 32) throw new \RuntimeException('Schlüssel der Installation fehlt.');
        return sodium_crypto_generichash('backup-target|' . $k, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function seal(string $v): string
    {
        $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'sb1:' . base64_encode($n . sodium_crypto_secretbox($v, $n, self::boxKey()));
    }

    private static function open(string $v): string
    {
        if (!str_starts_with($v, 'sb1:')) return $v;
        $raw = (string) base64_decode(substr($v, 4), true);
        $plain = strlen($raw) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
            ? sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::boxKey()) : false;
        return $plain === false ? '' : $plain;
    }
}
