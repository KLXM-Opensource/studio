<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * Tote Rechte in Rollen (roles.permissions_json): Rechte, die weder der Kern noch eine aktive Erweiterung anbietet und die auch
 * im Code keiner installierten (abgeschalteten) Erweiterung vorkommen – z. B. von einer entfernten Erweiterung.
 *
 *   php bin/console roles:clean [--dry-run] [--all]   Probelauf bzw. entfernen, Protokoll je Rolle
 *   Health (Einrichtung): Hinweis „Rollen mit unbekannten Rechten“ → roles:clean
 *
 * Bewusst nie automatisch (migrate): Rechte abgeschalteter Erweiterungen sollen beim Wiedereinschalten wieder gelten.
 * Beim Speichern einer Rolle bleiben Rechte, die die Oberfläche nicht zeigt, erhalten (Permissions::keepHidden).
 */
final class RoleCleanup
{
    /** Ordner, die beim Durchsuchen einer Erweiterung übersprungen werden */
    private const SKIP_DIRS = ['vendor', 'node_modules', '.git', 'assets', 'public', 'docs', 'lang'];

    /**
     * Unbekannte Rechte der Liste: nicht in Permissions::all() und nicht als Zeichenkette ('recht' oder "recht") im PHP-Code
     * einer installierten Erweiterung (auch abgeschaltet – deren Rechte kennt der Kern erst nach dem Start).
     * @param list<string> $perms
     * @return list<string>
     */
    public static function unknown(array $perms, ?array $extensions = null): array
    {
        $known = Permissions::all();
        $left = array_values(array_unique(array_filter(array_map('strval', $perms), fn($p) => $p !== '*' && !in_array($p, $known, true))));
        if (!$left) return [];
        foreach ($extensions ?? self::extensionDirs() as $dir) {
            foreach (self::phpFiles((string) $dir) as $file) {
                $code = (string) @file_get_contents($file);
                $left = array_values(array_filter($left, fn($p) => !str_contains($code, "'$p'") && !str_contains($code, "\"$p\"")));
                if (!$left) return [];
            }
        }
        return $left;
    }

    /** Je Rolle die unbekannten Rechte: [rkey => ['name' => …, 'unknown' => [...]]] (ohne Rollen mit allen Rechten) */
    public static function report(?array $extensions = null): array
    {
        $out = [];
        foreach (Permissions::roles() as $key => $r) {
            if (in_array('*', $r['permissions'], true)) continue;
            $u = self::unknown($r['permissions'], $extensions);
            if ($u) $out[$key] = ['name' => $r['name'], 'unknown' => $u];
        }
        return $out;
    }

    /** Unbekannte Rechte entfernen ($dry = nur melden). Rückgabe wie report() */
    public static function run(bool $dry = false, ?array $extensions = null): array
    {
        $rep = self::report($extensions);
        if ($dry) return $rep;
        foreach ($rep as $key => $x) {
            $r = Permissions::role((string) $key);
            if (!$r) continue;
            $keep = array_values(array_diff($r['permissions'], $x['unknown']));
            app()->db->update('roles', ['permissions_json' => json_encode($keep)], 'rkey = :k', ['k' => (string) $key]);
        }
        return $rep;
    }

    /** Health (Einrichtung): Hinweis, wenn Rollen unbekannte Rechte haben – entfernt wird nur mit roles:clean */
    public static function health(): array
    {
        try {
            $rep = self::report();
        } catch (\Throwable) {
            return [];
        }
        if (!$rep) return [];
        $n = array_sum(array_map(fn($x) => count($x['unknown']), $rep));
        return ['Rollen mit unbekannten Rechten: ' . $n . ' in ' . count($rep) . ' Rolle(n) – php bin/console roles:clean --dry-run' => null];
    }

    /** bin/console roles:clean [--dry-run] | roles:selftest */
    public static function console(string $cmd, array $args): int
    {
        if ($cmd === 'roles:selftest') {
            $res = self::selfTest();
            foreach ($res['fails'] as $f) echo "  FEHLER: $f\n";
            echo "  {$res['ok']} Prüfungen bestanden, " . count($res['fails']) . " fehlgeschlagen\n";
            return $res['fails'] ? 1 : 0;
        }
        $dry = Cli::flag($args, 'dry-run');
        $rep = self::run($dry);
        if (!$rep) {
            echo site()->key . ": keine unbekannten Rechte.\n";
            return 0;
        }
        foreach ($rep as $key => $x) {
            echo site()->key . ': Rolle „' . $x['name'] . '“ (' . $key . '): ' . ($dry ? 'würde entfernen' : 'entfernt') . ' ' . implode(', ', $x['unknown']) . "\n";
        }
        if ($dry) echo "Probelauf – nichts geändert. Ohne --dry-run entfernen.\n";
        return 0;
    }

    /** Ordner der installierten Erweiterungen (Core\Extensions::available, auch abgeschaltete) */
    private static function extensionDirs(): array
    {
        return array_values(array_filter(array_map(fn($m) => (string) ($m['dir'] ?? ''), Extensions::available()), 'is_dir'));
    }

    /** PHP-Dateien eines Ordners (rekursiv, ohne Abhängigkeiten und Assets) */
    private static function phpFiles(string $dir): \Generator
    {
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                fn(\SplFileInfo $f) => $f->isDir() ? !in_array($f->getFilename(), self::SKIP_DIRS, true) : $f->getExtension() === 'php'));
            foreach ($it as $f) yield $f->getPathname();
        } catch (\Throwable) {
            return;
        }
    }

    /** Selbsttest (roles:selftest): unbekannte Rechte erkennen, Probelauf ändert nichts, Entfernen, Erhalt beim Speichern */
    public static function selfTest(): array
    {
        $ok = 0;
        $fails = [];
        $eq = \Core\Testing\Check::eq($ok, $fails, 'Rollen – ');
        $key = 'selbsttest_rollen_' . bin2hex(random_bytes(3));
        $tmp = sys_get_temp_dir() . '/klxm-roles-' . bin2hex(random_bytes(4));
        try {
            // Abgeschaltete Erweiterung (nur Code): ihr Recht gilt als bekannt
            @mkdir($tmp, 0775, true);
            file_put_contents($tmp . '/extension.php', "<?php return ['boot' => fn(\$x) => \$x->permissions('Probe', ['probe_x.edit' => 'Probe'])];\n");
            $ext = [$tmp];
            $eq('Bekannt: Kern-Recht', self::unknown(['pages.edit', '*'], $ext), []);
            $eq('Bekannt: Recht im Code einer Erweiterung', self::unknown(['probe_x.edit'], $ext), []);
            $eq('Unbekannt', self::unknown(['pages.edit', 'probe_weg.recht', 'probe_x.edit'], $ext), ['probe_weg.recht']);
            app()->db->insert('roles', ['rkey' => $key, 'name' => 'Selbsttest', 'description' => '', 'builtin' => 0, 'tables_json' => null,
                'permissions_json' => json_encode(['pages.edit', 'probe_weg.recht', 'probe_x.edit'])]);
            $eq('Bericht', self::report($ext)[$key]['unknown'] ?? null, ['probe_weg.recht']);
            self::run(true, $ext);
            $eq('Probelauf ändert nichts', Permissions::role($key)['permissions'] ?? null, ['pages.edit', 'probe_weg.recht', 'probe_x.edit']);
            self::run(false, $ext);
            $eq('Entfernt nur Unbekanntes', Permissions::role($key)['permissions'] ?? null, ['pages.edit', 'probe_x.edit']);
            // Speichern in der Oberfläche: angehakte bekannte Rechte + nicht angezeigte (unbekannte) Rechte der Rolle bleiben
            $eq('Speichern erhält Rechte abgeschalteter Erweiterungen', Permissions::keepHidden(['data.edit', 'probe_neu.recht'], ['pages.edit', 'probe_x.edit']),
                ['data.edit', 'probe_x.edit']);
        } catch (\Throwable $e) {
            $fails[] = 'Rollen – Ausnahme: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
        } finally {
            try { app()->db->query('DELETE FROM roles WHERE rkey = ?', [$key]); } catch (\Throwable) {}
            @unlink($tmp . '/extension.php');
            @rmdir($tmp);
        }
        return ['ok' => $ok, 'fails' => $fails];
    }
}
