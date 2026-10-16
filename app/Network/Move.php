<?php
// SPDX-License-Identifier: MIT
// KLXM Studio. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Core\Network;

use Core\Database;
use Core\Passkeys;
use Core\Sites;
use Core\Totp;

/**
 * Netzwerk-Website wechseln: php bin/console network:move <website> [--dry-run]
 *
 * Die Netzwerk-Konten (Rolle network) liegen in der Datenbank der Netzwerk-Website, ihre Geheimnisse sind mit deren app_key
 * verschlüsselt. Der Umzug
 *   1. sichert beide Datenbanken und config/config.local.php (storage/backups/),
 *   2. macht auf der neuen Website aus den Schatten-Konten echte Netzwerk-Konten (bzw. legt sie an) – Passwort, 2FA
 *      (neu verschlüsselt), Wiederherstellungscodes, Sperre,
 *   3. nimmt die Passkeys mit; sie behalten ihre Benutzerkennung (Spalte user_handle) und gelten weiter auf ihrer Domain,
 *   4. macht auf der alten Website Schatten-Konten daraus und stellt die Schatten der übrigen Websites auf die neuen IDs um,
 *   5. übernimmt Anmelde-Richtlinie (sys.net_auth), Netzwerk-E-Mail-Konto (neu verschlüsselt) und Protokoll,
 *   6. setzt 'network_site' in config/config.local.php.
 * Alle Sitzungen der Netzwerk-Konten enden (auth_ver). Offene Einladungen zur Netzwerk-Administration verfallen.
 * Mit --dry-run läuft alles in Transaktionen, die zurückgerollt werden – keine Sicherung, keine Konfiguration.
 */
final class Move
{
    /** @var list<string> */
    private array $out = [];

    public function __construct(private string $to, private bool $dry) {}

    public function run(): int
    {
        $from = Network::siteKey();
        $sites = Sites::all();
        if (!isset($sites[$this->to])) return $this->fail("Website „{$this->to}“ gibt es nicht (site:list).");
        if ($this->to === $from) return $this->fail("„{$this->to}“ ist bereits die Netzwerk-Website.");
        $local = ROOT . '/config/config.local.php';
        if (!$this->dry && !is_writable($local)) return $this->fail('config/config.local.php fehlt oder ist nicht beschreibbar.');

        $oldCfg = Network::config($from);
        $newCfg = Network::config($this->to);
        $oldKey = (string) $oldCfg->get('app_key');
        $newKey = (string) $newCfg->get('app_key');
        if (strlen($oldKey) < 16 || strlen($newKey) < 16) return $this->fail('app_key einer der beiden Websites fehlt.');
        $old = new Database((array) $oldCfg->get('db'));
        $new = new Database((array) $newCfg->get('db'));
        foreach ([$old, $new] as $db) if ($db->driver !== 'sqlite') return $this->fail('Nur für SQLite-Websites – bei MySQL bitte von Hand umziehen.');
        $new->migrate();
        Network::ensureSchema($new);
        Network::ensureSchema($old);

        $accounts = $old->fetchAll("SELECT * FROM users WHERE role = 'network' ORDER BY id");
        if (!$accounts) return $this->fail("Auf „{$from}“ gibt es keine Netzwerk-Konten.");
        $this->say(($this->dry ? 'Probelauf' : 'Umzug') . ": Netzwerk-Website {$from} → {$this->to}, " . count($accounts) . ' Konten');

        // Andere Websites mit Schatten-Konten (ohne alte und neue)
        $others = [];
        foreach (array_keys($sites) as $k) {
            if ($k === $from || $k === $this->to) continue;
            $cfg = (array) Network::config($k)->get('db');
            if (($cfg['driver'] ?? 'sqlite') === 'sqlite' && !is_file((string) ($cfg['path'] ?? ''))) continue;
            $others[$k] = new Database($cfg);
        }

        if (!$this->dry) {
            $dir = ROOT . '/storage/backups';
            if (!is_dir($dir)) @mkdir($dir, 0770, true);
            $stamp = date('Ymd-His');
            foreach ([$from => $old, $this->to => $new] + $others as $k => $db) {
                $file = "$dir/network-move-$stamp-$k.sqlite";
                $db->pdo->exec('VACUUM INTO ' . $db->pdo->quote($file));
                $this->say("  Sicherung: storage/backups/" . basename($file));
            }
            copy($local, "$dir/network-move-$stamp-config.local.php");
            $this->say("  Sicherung: storage/backups/network-move-$stamp-config.local.php");
        }

        $all = [$old, $new, ...array_values($others)];
        foreach ($all as $db) $db->pdo->beginTransaction();
        try {
            $map = $this->accounts($old, $new, $accounts, $oldKey, $newKey);
            $this->passkeys($old, $new, $map, $oldKey);
            $this->shadows($old, $map);
            foreach ($others as $k => $db) {
                $n = $this->remap($db, $map);
                if ($n) $this->say("  $k: $n Schatten-Konten auf die neuen IDs umgestellt");
            }
            $this->extras($old, $new, $oldKey, $newKey, $from);
        } catch (\Throwable $e) {
            foreach ($all as $db) $db->pdo->rollBack();
            return $this->fail('Abgebrochen, nichts geändert: ' . $e->getMessage());
        }
        if ($this->dry) {
            foreach ($all as $db) $db->pdo->rollBack();
            $this->say('Probelauf – nichts geändert. Ohne --dry-run ausführen, um umzuziehen.');
            return $this->flush(0);
        }
        // Konfiguration zuerst prüfen (wie Network::ensureKey); scheitert sie, wird nichts übernommen
        if (!self::writeSite($local, $this->to)) {
            foreach ($all as $db) $db->pdo->rollBack();
            return $this->fail('config/config.local.php konnte nicht geändert werden – nichts geändert.');
        }
        foreach ($all as $db) $db->pdo->commit();
        Network::reset();
        $this->say("Fertig. Netzwerk-Website ist jetzt „{$this->to}“ (" . Network::siteUrl($this->to) . '/admin).');
        $this->say('Alle Netzwerk-Sitzungen sind beendet. Passkeys gelten weiter auf ihrer bisherigen Domain;');
        $this->say('auf der neuen Domain unter Konto → Passkeys einen weiteren anlegen (z. B. nach Anmeldung über die alte Domain und Wechsel per Netzwerk).');
        return $this->flush(0);
    }

    /** Netzwerk-Konten auf der neuen Website anlegen bzw. aus dem Schatten-Konto machen → [alte ID => neue ID] */
    private function accounts(Database $old, Database $new, array $accounts, string $oldKey, string $newKey): array
    {
        $cols = array_column($new->fetchAll('PRAGMA table_info(users)'), 'name');
        $map = [];
        foreach ($accounts as $a) {
            $email = strtolower((string) $a['email']);
            $row = $new->fetch('SELECT id, role, network_uid FROM users WHERE network_uid = ?', [(int) $a['id']])
                ?? $new->fetch('SELECT id, role, network_uid FROM users WHERE LOWER(email) = ?', [$email]);
            if ($row && empty($row['network_uid']) && $row['role'] !== 'network') {
                throw new \RuntimeException("$email hat auf „{$this->to}“ ein eigenes Konto (Rolle {$row['role']}) – erst klären.");
            }
            $data = [];
            foreach ($a as $k => $v) if ($k !== 'id' && in_array($k, $cols, true)) $data[$k] = $v;
            $data['email'] = $email;
            $data['role'] = 'network';
            $data['network_uid'] = null;
            $data['auth_ver'] = (int) ($a['auth_ver'] ?? 0) + 1;
            if (!empty($a['totp_secret'])) {
                $secret = Totp::open((string) $a['totp_secret'], $oldKey);
                if ($secret === null) throw new \RuntimeException("2FA-Geheimnis von $email nicht lesbar.");
                $data['totp_secret'] = Totp::seal($secret, $newKey);
            }
            if ($row) {
                // Schatten-Konto darf keine eigenen Passkeys haben – Reste entfernen, bevor es das echte Konto wird
                $new->query('DELETE FROM user_passkeys WHERE user_id = ?', [(int) $row['id']]);
                $new->update('users', $data, 'id = :id', ['id' => $row['id']]);
                $id = (int) $row['id'];
            } else {
                $id = $new->insert('users', $data);
            }
            $map[(int) $a['id']] = $id;
            $this->say("  $email: Konto {$a['id']} → {$this->to} #$id" . ($row ? ' (war Schatten-Konto)' : ' (neu)')
                . ((int) $a['totp_enabled'] ? ', 2FA-App' : ''));
        }
        return $map;
    }

    /** Passkeys der Netzwerk-Konten mitnehmen; die Benutzerkennung (Handle) bleibt die beim Anlegen verwendete */
    private function passkeys(Database $old, Database $new, array $map, string $oldKey): void
    {
        $n = 0;
        $have = array_column($old->fetchAll('PRAGMA table_info(user_passkeys)'), 'name');
        foreach ($map as $oid => $nid) {
            foreach ($old->fetchAll('SELECT * FROM user_passkeys WHERE user_id = ?', [$oid]) as $p) {
                if ($new->fetchValue('SELECT COUNT(*) FROM user_passkeys WHERE cred_hash = ?', [$p['cred_hash']])) continue;
                $handle = in_array('user_handle', $have, true) && ($p['user_handle'] ?? '') !== ''
                    ? (string) $p['user_handle'] : base64_encode(Passkeys::userHandle('net', $oid, $oldKey));
                unset($p['id']);
                $new->insert('user_passkeys', ['user_id' => $nid, 'user_handle' => $handle] + $p);
                $n++;
            }
            $old->query('DELETE FROM user_passkeys WHERE user_id = ?', [$oid]);
        }
        $this->say("  $n Passkeys übernommen");
    }

    /** Alte Netzwerk-Website: aus den Konten werden Schatten-Konten (kein eigenes Passwort, keine eigene 2FA) */
    private function shadows(Database $old, array $map): void
    {
        foreach ($map as $oid => $nid) {
            $old->query("UPDATE users SET network_uid = ?, password_hash = '!network', totp_secret = NULL, totp_recovery = NULL,
                totp_last = NULL, totp_enabled = 0, auth_ver = auth_ver + 1 WHERE id = ?", [$nid, $oid]);
        }
        $this->say('  ' . count($map) . ' Konten auf der alten Netzwerk-Website zu Schatten-Konten gemacht');
    }

    /** Schatten-Konten einer anderen Website auf die neuen IDs umstellen (über negative Zwischenwerte – IDs können sich überschneiden) */
    private function remap(Database $db, array $map): int
    {
        $n = 0;
        foreach ($map as $oid => $nid) $n += $db->query('UPDATE users SET network_uid = ? WHERE network_uid = ?', [-$nid, $oid])->rowCount();
        $db->query('UPDATE users SET network_uid = -network_uid WHERE network_uid < 0');
        return $n;
    }

    /** Anmelde-Richtlinie, Netzwerk-E-Mail-Konto und Protokoll */
    private function extras(Database $old, Database $new, string $oldKey, string $newKey, string $from): void
    {
        $pol = $old->fetch('SELECT * FROM settings WHERE skey = ?', [\Core\Mfa::NET_KEY]);
        if ($pol) {
            $new->query('DELETE FROM settings WHERE skey = ?', [\Core\Mfa::NET_KEY]);
            $new->insert('settings', $pol);
            $this->say('  Anmelde-Richtlinie der Netzwerk-Konten übernommen');
        }
        $old->pdo->exec('CREATE TABLE IF NOT EXISTS network_mail (k VARCHAR(40) NOT NULL PRIMARY KEY, v TEXT)');
        $new->pdo->exec('CREATE TABLE IF NOT EXISTS network_mail (k VARCHAR(40) NOT NULL PRIMARY KEY, v TEXT)');
        $mail = $old->fetchValue("SELECT v FROM network_mail WHERE k = 'account'");
        if ($mail) {
            $json = \Core\Mail\Accounts::resealNetwork((string) $mail, $oldKey, $newKey);
            if ($json === null) throw new \RuntimeException('Netzwerk-E-Mail-Konto nicht lesbar.');
            $new->query("DELETE FROM network_mail WHERE k = 'account'");
            $new->insert('network_mail', ['k' => 'account', 'v' => $json]);
            $this->say('  Netzwerk-E-Mail-Konto übernommen');
        }
        $log = $old->fetchAll('SELECT created_at, user_email, site, action, detail, ip_hash FROM network_log ORDER BY id');
        foreach ($log as $l) $new->insert('network_log', $l);
        $new->insert('network_log', ['created_at' => now(), 'user_email' => 'cli', 'site' => $this->to, 'action' => 'network.move', 'detail' => "$from → {$this->to}"]);
        $this->say('  ' . count($log) . ' Protokolleinträge übernommen');
        $inv = 0;
        try {
            $inv = (int) $old->fetchValue("SELECT COUNT(*) FROM user_invites WHERE role = 'network' AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at > ?", [time()]);
        } catch (\Throwable) {
        }
        if ($inv) $this->say("  Hinweis: $inv offene Einladungen zur Netzwerk-Administration verfallen – neu einladen.");
    }

    /** 'network_site' in config/config.local.php setzen bzw. ergänzen (temporäre Datei, erst nach erfolgreichem Einlesen ersetzen) */
    private static function writeSite(string $file, string $key): bool
    {
        $src = (string) file_get_contents($file);
        $val = var_export($key, true);
        $new = preg_replace("~(['\"]network_site['\"]\s*=>\s*)(['\"])[^'\"]*\\2~", '${1}' . $val, $src, 1, $n);
        if (!$n) {
            $line = "  'network_site' => $val,   // Netzwerk-Website (Netzwerk-Konten, Netzwerk-Verwaltung) – Wechsel nur per network:move\n";
            $new = preg_replace('~(\n\s*)(\)|\]);\s*$~', "\n" . $line . '$2;' . "\n", $src, 1, $n);
        }
        if (!$n || $new === null) return false;
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $new, LOCK_EX);
        try {
            $check = require $tmp;
        } catch (\Throwable) {
            $check = null;
        }
        if (!is_array($check) || ($check['network_site'] ?? null) !== $key) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, fileperms($file) & 0777);
        rename($tmp, $file);
        if (function_exists('opcache_invalidate')) @opcache_invalidate($file, true);
        return true;
    }

    private function say(string $s): void
    {
        $this->out[] = $s;
    }

    private function fail(string $s): int
    {
        $this->out[] = 'Fehler: ' . $s;
        return $this->flush(1);
    }

    private function flush(int $code): int
    {
        echo implode("\n", $this->out) . "\n";
        $this->out = [];
        return $code;
    }
}
