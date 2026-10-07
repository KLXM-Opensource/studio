<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core;

/**
 * php bin/console site:extract <key> [--out=ordner] [--archive] [--all-kits] – eine Website einer Multi-Site als eigenständige
 * Installation herauslösen. Die Quelle bleibt unverändert; es entsteht eine vollständige Kopie, in der die gewählte Website die
 * einzige ist (Website „default“):
 *
 *  - Code: app, bin, lang, lib, resources, deploy, vendor, Composer-Dateien, Doku – tools ohne node_modules und Arbeitsordner
 *  - Kits: das Kit der Website und die für sie erlaubten (mit --all-kits alle), jeweils mit public/assets/kits/{kit}
 *  - Erweiterungen: nur die aktiven der Website (Symlinks werden aufgelöst, .git/node_modules bleiben weg)
 *  - Daten: Datenbank (SQLite per VACUUM INTO – konsistent im laufenden Betrieb; MySQL als Dump zum Einspielen), Medien,
 *    Suchindex und übrige Website-Daten; Medien-Pools, die die Website nutzt (Datenbank, Dateien, auch geschützte)
 *  - Konfiguration: config/config.local.php = Installation + Website (eigener app_key bleibt – sonst wären verschlüsselte
 *    Einstellungen wie das SMTP-Passwort unlesbar), ohne Domains der Multi-Site; Erweiterungen der Website fest eingetragen
 *  - Bericht EIGENE-INSTANZ.md (Schritte, Hinweise) und storage/extract.json; Prüfung der Kopie mit health
 *
 * Nicht übernommen (gemeldet): geteilte Datentabellen (vorher data:unshare), Netzwerk-Konten, zentrale Support-Daten, Sitzungen,
 * Cache und Protokolle.
 */
final class SiteExtract
{
    /** Code-Ordner und Dateien der Installation, die in jede Kopie gehören */
    private const CODE = ['app', 'bin', 'lang', 'lib', 'resources', 'deploy', 'vendor', '.github',
        'composer.json', 'composer.lock', 'AGENTS.md', 'CHANGELOG.md', 'CONTRIBUTING.md', 'COPYRIGHT', 'LICENSE', 'README.md', 'SECURITY.md',
        'THIRD-PARTY-NOTICES.md', '.gitignore'];
    /** Nie kopieren (Entwicklung, Arbeitsdateien, Versionsverwaltung) */
    private const SKIP = ['.git', 'node_modules', '.DS_Store', '.work', '.narration', '__pycache__', '.venv', '.venv-chatterbox'];
    /** Daten einer Website, die nicht mitkommen (laufzeit- bzw. installationsbezogen) */
    private const SKIP_STORAGE = ['cache', 'sessions', 'logs', 'database', 'backups', 'exports', 'sites', 'pools', 'shared', 'support', 'tmp'];

    private string $out;
    private array $notes = [];
    private int $files = 0;
    private int $bytes = 0;

    /** @param callable(string):void $log */
    public function __construct(private $log) {}

    /**
     * @param array{out?: string, all_kits?: bool, archive?: bool} $o
     * @return array{out: string, archive: ?string, files: int, bytes: int, notes: list<string>, health: string}
     */
    public function run(array $o = []): array
    {
        $site = site();
        $key = $site->key;
        $this->out = rtrim((string) ($o['out'] ?? (ROOT . '/storage/exports/' . $key . '-' . date('Ymd-His'))), '/');
        if (!str_starts_with($this->out, '/')) $this->out = getcwd() . '/' . $this->out;
        if (is_dir($this->out) && (scandir($this->out) ?: []) !== ['.', '..']) throw new \RuntimeException("Zielordner ist nicht leer: {$this->out}");
        $realRoot = realpath(ROOT) ?: ROOT;
        foreach (['/app', '/public', '/kits', '/extensions', '/config', '/vendor'] as $d) {
            if (str_starts_with($this->out . '/', $realRoot . $d . '/')) throw new \RuntimeException("Zielordner liegt im Code der Installation ($d) – bitte außerhalb oder unter storage/exports wählen.");
        }
        $this->mkdir($this->out);

        // 1) Code
        $this->say('Code');
        foreach (self::CODE as $p) if (file_exists(ROOT . "/$p")) $this->copy(ROOT . "/$p", $this->out . "/$p");
        $this->copy(ROOT . '/tools', $this->out . '/tools', fn(string $rel) => !preg_match('~(^|/)voices/[^/]+\.(onnx|onnx\.json|MODEL_CARD)$~', $rel));
        $this->mkdir($this->out . '/config');
        copy(ROOT . '/config/config.php', $this->out . '/config/config.php');
        $this->mkdir($this->out . '/public');
        foreach (glob(ROOT . '/public/{,.}*', GLOB_BRACE) ?: [] as $f) if (is_file($f)) $this->copyFile($f, $this->out . '/public/' . basename($f));
        foreach (glob(ROOT . '/public/assets/*', GLOB_ONLYDIR) ?: [] as $d) {
            $n = basename($d);
            if (in_array($n, ['kits', 'ext'], true)) continue;
            // Symbol-Sprites anderer Websites (icons/sites/{key}.*.svg) nicht – die Kopie erzeugt ihre eigenen
            $this->copy($d, $this->out . "/public/assets/$n", fn(string $rel) => !str_starts_with($rel, 'sites/'));
        }

        // 2) Kits
        // Das Kit der Website; hat sie eine feste Auswahl erlaubter Kits ('kits' in config/sites), diese dazu. --all-kits: alle
        $restricted = (array) (($site->cfg['kits'] ?? []) ?: ($site->cfg['themes'] ?? []));
        $kits = !empty($o['all_kits']) ? array_keys(Theme::available())
            : array_values(array_unique(array_merge([app()->theme->name], array_keys(array_intersect_key(Theme::available(), array_flip($restricted))))));
        $this->say('Kits: ' . implode(', ', $kits));
        foreach ($kits as $k) {
            $dir = Kit::dir($k);
            if (!is_dir($dir)) continue;
            $this->copy($dir, $this->out . '/' . Kit::relative($dir));
            if (is_dir($pub = Kit::publicDir($k))) $this->copy($pub, $this->out . '/' . Kit::relative($pub));
        }

        // 3) Erweiterungen (nur aktive; Composer-Pakete stecken bereits in vendor/)
        $exts = array_keys(Extensions::active());
        $this->say('Erweiterungen: ' . ($exts ? implode(', ', $exts) : 'keine'));
        foreach (Extensions::available() as $name => $m) {
            if (!in_array($name, $exts, true)) continue;
            $dir = realpath((string) $m['dir']) ?: (string) $m['dir'];
            if (str_starts_with((string) $m['source'], 'lokal')) {
                $target = $this->out . '/extensions/' . basename((string) $m['dir']);
                $this->copy($dir, $target);
            }
            $pub = PublicPaths::dir(PublicPaths::EXT, $name);
            if (is_dir($pub)) $this->copy($pub, $this->out . '/public/assets/ext/' . $name);
        }

        // 4) Daten der Website
        $this->say('Datenbank');
        $cfg = app()->config->get('db');
        $this->mkdir($this->out . '/storage/database');
        $mysql = ($cfg['driver'] ?? 'sqlite') !== 'sqlite';
        if (!$mysql) {
            app()->db->pdo->exec('VACUUM INTO ' . app()->db->pdo->quote($this->out . '/storage/database/site.sqlite'));
            $this->count($this->out . '/storage/database/site.sqlite');
        } else {
            $dump = $this->out . '/storage/database/import.sql';
            putenv('MYSQL_PWD=' . $cfg['pass']);
            exec(sprintf('mysqldump --single-transaction --no-tablespaces -h %s -P %d -u %s %s > %s', escapeshellarg((string) $cfg['host']), (int) $cfg['port'],
                escapeshellarg((string) $cfg['user']), escapeshellarg((string) $cfg['name']), escapeshellarg($dump)), $x, $code);
            putenv('MYSQL_PWD');
            if ($code !== 0) throw new \RuntimeException('mysqldump fehlgeschlagen.');
            $this->notes[] = 'MySQL: Die Kopie zeigt noch auf DIESELBE Datenbank wie das Original. Vor dem Start eine neue Datenbank anlegen, '
                . 'storage/database/import.sql einspielen und die Zugangsdaten in config/config.local.php (db) ändern.';
        }
        $this->say('Medien und übrige Daten');
        if (is_dir($site->mediaDir())) $this->copy($site->mediaDir(), $this->out . '/public/media');
        foreach (glob($site->storage() . '/*') ?: [] as $p) {
            if (in_array(basename($p), self::SKIP_STORAGE, true)) continue;
            $this->copy($p, $this->out . '/storage/' . basename($p));
        }
        foreach (['cache', 'logs', 'sessions'] as $d) $this->mkdir($this->out . "/storage/$d");

        // 5) Medien-Pools, die die Website nutzt – in der Kopie gehören sie nur noch ihr
        $pools = array_keys(MediaPools::forSite());
        if ($pools) $this->say('Medien-Pools: ' . implode(', ', $pools));
        foreach ($pools as $pk) {
            $dst = $this->out . '/storage/pools/' . $pk;
            $this->copy(MediaPools::dir($pk), $dst, fn(string $rel) => !preg_match('~^pool\.sqlite(-wal|-shm)?$~', $rel));
            MediaPools::db($pk)->pdo->exec('VACUUM INTO ' . MediaPools::db($pk)->pdo->quote($dst . '/pool.sqlite'));
            if (!MediaPools::isProtected($pk) && is_dir(ROOT . '/public/pools/' . $pk)) $this->copy(ROOT . '/public/pools/' . $pk, $this->out . '/public/pools/' . $pk);
            $meta = json_decode((string) file_get_contents($dst . '/pool.json'), true) ?: [];
            $meta['sites'] = [Site::DEFAULT];
            $meta['editors'] = [];
            file_put_contents($dst . '/pool.json', json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        // 6) Was nicht mitkommt
        $shared = array_keys(Data\Shared::forSite());
        if ($shared) $this->notes[] = 'Geteilte Datentabellen nicht übernommen: ' . implode(', ', $shared) . ' – in der Kopie fehlen sie. Vorher im Original mit '
            . '„php bin/console data:unshare <handle> --site=…“ zu einer eigenen Tabelle machen (nur Eigentümer) und erneut herauslösen.';
        if (Sites::multi()) $this->notes[] = 'Netzwerk-Konten (Anmeldung über die Netzwerk-Administration) gelten in der Kopie nicht – Konten der Website selbst schon.';
        $this->notes[] = 'Angemeldete Sitzungen enden; Passkeys gelten weiter, solange die Domain gleich bleibt.';

        // 7) Konfiguration
        $this->say('Konfiguration');
        $this->writeConfig($site, $exts, $pools, $mysql);
        $report = $this->writeReport($site, $kits, $exts, $pools, $mysql);

        // 8) Kopie prüfen (eigener Prozess – ROOT ist dort der neue Ordner)
        $this->say('Prüfung der Kopie');
        $health = $this->health();

        $archive = null;
        if (!empty($o['archive'])) {
            $archive = $this->out . '.tar.gz';
            exec('COPYFILE_DISABLE=1 tar -czf ' . escapeshellarg($archive) . ' -C ' . escapeshellarg(dirname($this->out)) . ' ' . escapeshellarg(basename($this->out)), $x, $code);
            if ($code !== 0) { $this->notes[] = 'Archiv konnte nicht erstellt werden (tar).'; $archive = null; }
        }
        return ['out' => $this->out, 'archive' => $archive, 'files' => $this->files, 'bytes' => $this->bytes, 'notes' => $this->notes, 'health' => $health, 'report' => $report];
    }

    /** config/config.local.php der Kopie: Installation ← Website; Domains der Multi-Site fallen weg, SQLite am Standardort */
    private function writeConfig(Site $site, array $exts, array $pools, bool $mysql): void
    {
        $localFile = ROOT . '/config/config.local.php';
        $local = is_file($localFile) ? (array) (require $localFile) : [];
        $siteCfg = $site->isDefault() ? [] : (Sites::all()[$site->key] ?? []);
        $cfg = array_replace_recursive($local, $siteCfg);
        foreach (['hosts', 'fallback_site', 'themes', 'kits'] as $k) unset($cfg[$k]);
        if (!$mysql) $cfg['db'] = ['driver' => 'sqlite'];   // Pfad aus config.php (storage/database/site.sqlite der Kopie)
        else $cfg['db'] = (array) app()->config->get('db');
        $cfg['extensions'] = $exts;
        if ($pools) $cfg['media_pools'] = $pools; else unset($cfg['media_pools']);
        $cfg['app_key'] = (string) app()->config->get('app_key');   // verschlüsselte Einstellungen bleiben lesbar
        $cfg['setup_token'] = bin2hex(random_bytes(12));
        unset($cfg['session']['name']);
        if (isset($cfg['session']) && !$cfg['session']) unset($cfg['session']);
        $php = "<?php\n// Lokale Konfiguration – NICHT versionieren, NICHT weitergeben.\n"
            . "// Herausgelöst aus der Website „{$site->key}“ am " . date('Y-m-d H:i') . " (php bin/console site:extract)\nreturn " . var_export($cfg, true) . ";\n";
        file_put_contents($this->out . '/config/config.local.php', $php);
        @chmod($this->out . '/config/config.local.php', 0640);
    }

    private function writeReport(Site $site, array $kits, array $exts, array $pools, bool $mysql): string
    {
        $hosts = $site->hosts();
        $md = "# Eigene Instanz: Website „{$site->key}“\n\nHerausgelöst am " . date('d.m.Y H:i') . " aus " . (realpath(ROOT) ?: ROOT) . " (KLXM Studio " . CMS_VERSION . ").\n"
            . "Die Website ist hier die einzige (Website „default“); das Original blieb unverändert.\n\n"
            . "| | |\n|---|---|\n| Domains bisher | " . ($hosts ? implode(', ', $hosts) : '–') . " |\n| Kit | " . implode(', ', $kits) . " |\n"
            . "| Erweiterungen | " . ($exts ? implode(', ', $exts) : '–') . " |\n| Medien-Pools | " . ($pools ? implode(', ', $pools) : '–') . " |\n"
            . "| Datenbank | " . ($mysql ? 'MySQL (Dump: storage/database/import.sql)' : 'SQLite (storage/database/site.sqlite)') . " |\n\n"
            . "## Schritte\n\n1. Ordner auf den Zielserver kopieren (z. B. als httpdocs), Dokumentstamm = `public`.\n"
            . ($mysql ? "2. Neue MySQL-Datenbank anlegen, `storage/database/import.sql` einspielen, Zugang in `config/config.local.php` (db) eintragen.\n" : "2. Schreibrechte für `storage/` und `public/media` prüfen.\n")
            . "3. `php bin/console health` – alles grün?\n4. Zeitgesteuerte Aufgaben (Cron) wie bisher einrichten (`php bin/console jobs:run` o. Ä., siehe Entwicklerhandbuch → Betrieb).\n"
            . "5. Testen (z. B. über eine Testdomain), dann DNS der Domains auf den neuen Server umstellen und SSL einrichten.\n"
            . "6. Erst danach im Original die Website entfernen (config/sites/{$site->key}.php, storage/sites/{$site->key}, public/sites/{$site->key}).\n\n"
            . "## Hinweise\n\n" . implode("\n", array_map(fn($n) => "- $n", $this->notes)) . "\n";
        file_put_contents($this->out . '/EIGENE-INSTANZ.md', $md);
        file_put_contents($this->out . '/storage/extract.json', json_encode(['site' => $site->key, 'created' => date('c'), 'core' => CMS_VERSION,
            'kits' => $kits, 'extensions' => $exts, 'pools' => $pools, 'hosts' => $hosts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $this->out . '/EIGENE-INSTANZ.md';
    }

    private function health(): string
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->out . '/bin/console') . ' health 2>&1';
        exec($cmd, $lines, $code);
        $fail = array_values(array_filter($lines, fn($l) => str_contains($l, '✗')));
        return ($code === 0 ? 'OK' : 'FEHLER') . ($fail ? ' – ' . implode(' · ', array_map('trim', $fail)) : '') . ' (' . trim((string) end($lines)) . ')';
    }

    // ------------------------------------------------------------------ Dateien

    private function say(string $m): void
    {
        ($this->log)('▸ ' . $m);
    }

    private function mkdir(string $d): bool
    {
        return is_dir($d) || @mkdir($d, 0775, true);
    }

    private function count(string $f): void
    {
        $this->files++;
        $this->bytes += (int) @filesize($f);
    }

    private function copyFile(string $src, string $dst): void
    {
        $this->mkdir(dirname($dst));
        if (!@copy($src, $dst)) throw new \RuntimeException("Kopieren fehlgeschlagen: $src");
        @chmod($dst, fileperms($src) & 0777);
        $this->count($dst);
    }

    /** Rekursiv kopieren; Symlinks werden aufgelöst (Inhalt statt Verweis), SKIP-Namen ausgelassen. $keep(rel) = false → überspringen */
    private function copy(string $src, string $dst, ?callable $keep = null, string $rel = ''): void
    {
        if (in_array(basename($src), self::SKIP, true)) return;
        if ($rel !== '' && $keep && !$keep($rel)) return;
        if (is_file($src)) { $this->copyFile($src, $dst); return; }
        if (!is_dir($src)) return;
        $this->mkdir($dst);
        foreach (scandir($src) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            $this->copy("$src/$f", "$dst/$f", $keep, $rel === '' ? $f : "$rel/$f");
        }
    }
}
