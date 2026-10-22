<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Backup;

/**
 * Alles von einem Sicherungsziel wiederherstellen – für den Ernstfall auf einem neuen Server (php bin/console backup:restore-all):
 *  1. am Ziel je Präfix (Website, pool-…, shared-…, install) die neueste Sicherung wählen (oder die neueste bis --date=JJJJMMTT)
 *  2. Installation holen, entschlüsseln, einspielen (Konfiguration, storage/, eigene Kits/Erweiterungen) – den Schlüssel als
 *     storage/backup-offsite/key ablegen, damit neue Sicherungen denselben verwenden
 *  3. je Website site:restore, je Pool pool:restore, je Tabelle shared:restore – als eigene Prozesse, weil die eingespielte
 *     Konfiguration (neue Websites) erst beim nächsten Start gilt
 *  4. migrate --all, kits:publish, extensions:publish, cache:clear --all, search:index --all --full, integrity:accept --all
 * Pakete aus Composer (composer install) und der Cronjob bleiben Handarbeit – der Befehl nennt sie am Ende.
 */
final class Restore
{
    private const PATTERN = '~^(?<prefix>[a-z0-9][a-z0-9_.-]*?)-(?<stamp>\d{8}-\d{6})\.tar(?:\.gz)?\.kbk$~';

    /**
     * Auswahl am Ziel: [präfix => dateiname], neueste je Präfix (bis einschließlich $until = JJJJMMTT)
     * @return array<string, string>
     */
    public static function pick(array $remote, string $until = ''): array
    {
        $out = [];
        foreach ($remote as $f) {
            if (!preg_match(self::PATTERN, $f, $m)) continue;
            if ($until !== '' && substr($m['stamp'], 0, 8) > $until) continue;
            if (!isset($out[$m['prefix']]) || strcmp($out[$m['prefix']], $f) < 0) $out[$m['prefix']] = $f;
        }
        ksort($out);
        return $out;
    }

    /**
     * Ablauf; $opt: until (JJJJMMTT), code (eigene Kits/Erweiterungen, Standard true), dry (nur anzeigen), index (Suchindex, Standard true).
     * Gibt 0 bei Erfolg zurück, sonst die Zahl der fehlgeschlagenen Schritte.
     */
    public static function run(Transport $tr, string $key, array $opt, callable $log): int
    {
        $files = self::pick($tr->list(), (string) ($opt['until'] ?? ''));
        if (!isset($files['install'])) { $log('Am Ziel liegt keine Sicherung der Installation (install-….kbk) – Abbruch.'); return 1; }
        $sites = $pools = $shared = [];
        foreach ($files as $prefix => $f) {
            if ($prefix === 'install') continue;
            if (str_starts_with($prefix, 'pool-')) $pools[$prefix] = $f;
            elseif (str_starts_with($prefix, 'shared-')) $shared[$prefix] = $f;
            else $sites[$prefix] = $f;
        }
        $log('Ausgewählt: ' . count($files) . ' Sicherungen');
        foreach ($files as $f) $log('  ' . $f);
        if (!empty($opt['dry'])) return 0;

        $dir = ROOT . '/storage/restore';
        @mkdir($dir, 0700, true);
        $get = function (string $name) use ($tr, $key, $dir): string {
            $enc = "$dir/$name";
            $tr->get($name, $enc);
            $plain = substr($enc, 0, -strlen(Vault::EXT));
            Vault::decryptFile($enc, $plain, $key);
            unlink($enc);
            return $plain;
        };
        $fail = 0;

        // 1. Installation
        $log('== Installation');
        $r = Installation::restore($get($files['install']), $opt['code'] ?? true);
        $log("  {$r['files']} Dateien eingespielt (Sicherung vom " . ($r['manifest']['created'] ?? '?') . ', Kern ' . ($r['manifest']['core'] ?? '?') . ', hier ' . CMS_VERSION . ')');
        if (!is_file(Vault::dir() . '/key')) {
            @mkdir(Vault::dir(), 0700, true);
            file_put_contents(Vault::dir() . '/key', Vault::export($key), LOCK_EX);
            @chmod(Vault::dir() . '/key', 0600);
            $log('  Schlüssel als storage/backup-offsite/key abgelegt – neue Sicherungen verwenden denselben.');
        }
        $known = array_keys((array) ($r['manifest']['sites'] ?? []));

        // 2. Websites, Pools, Tabellen – eigene Prozesse (neue Konfiguration)
        foreach ($sites as $site => $f) {
            $log("== Website $site");
            if ($known && !in_array($site, $known, true)) { $log("  übersprungen – „{$site}“ steht nicht in der Konfiguration der Sicherung"); continue; }
            $fail += self::console(['site:restore', $get($f), '--force', '--site=' . $site], $log);
        }
        foreach ($pools as $f) { $log('== ' . substr($f, 0, strpos($f, '-2') ?: null)); $fail += self::console(['pool:restore', $get($f), '--force'], $log); }
        foreach ($shared as $f) { $log('== ' . substr($f, 0, strpos($f, '-2') ?: null)); $fail += self::console(['shared:restore', $get($f), '--force'], $log); }

        // 3. Abschluss
        $log('== Abschluss');
        foreach ([['migrate', '--all'], ['kits:publish'], ['extensions:publish'], ['cache:clear', '--all']] as $c) $fail += self::console($c, $log, true);
        if ($opt['index'] ?? true) $fail += self::console(['search:index', '--all', '--full'], $log, true);
        $fail += self::console(['integrity:accept', '--all'], $log, true);
        Archive::remove($dir);
        $log($fail ? "Fertig mit $fail Fehlern – Ausgabe oben prüfen." : 'Fertig.');
        $log('Noch von Hand: composer install --no-dev (Pakete laut composer.json), Cronjob anlegen, Domains umstellen, Sicherung außer Haus neu einrichten, health --all.');
        return $fail;
    }

    /** bin/console in eigenem Prozess; Ausgabe eingerückt ins Protokoll; 1 bei Fehler */
    private static function console(array $args, callable $log, bool $quiet = false): int
    {
        // KLXM_NO_SEED: eine frisch angelegte Website soll keine Startinhalte (samt Bildern) bekommen, bevor die Sicherung sie ersetzt
        $p = proc_open(array_merge([\Core\Network\Stats::phpBinary(), ROOT . '/bin/console'], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, ROOT,
            array_merge(getenv(), ['KLXM_NO_SEED' => '1']));
        $out = trim((string) stream_get_contents($pipes[1]) . "\n" . (string) stream_get_contents($pipes[2]));
        fclose($pipes[1]); fclose($pipes[2]);
        $code = proc_close($p);
        $lines = array_values(array_filter(explode("\n", $out), fn($l) => trim($l) !== ''));
        if ($quiet && $code === 0) $lines = array_slice($lines, -1);
        $log('  ' . implode(' ', array_slice($args, 0, 1)) . ($code === 0 ? ' ✓' : " – Fehler (Code $code)"));
        foreach (array_slice($lines, 0, $code === 0 ? 3 : 12) as $l) $log('    ' . mb_strimwidth($l, 0, 200, '…'));
        return $code === 0 ? 0 : 1;
    }
}
