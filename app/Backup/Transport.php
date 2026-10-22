<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Copyright (C) 2026 KLXM and contributors (see LICENSE, COPYRIGHT)
declare(strict_types=1);

namespace Core\Backup;

/**
 * Übertragung zu einem Sicherungsziel – das Protokoll ergibt sich aus der Adresse:
 *   https://cloud.example.org/remote.php/dav/files/name/Sicherungen   WebDAV (Nextcloud, ownCloud, Storage-Box, NAS)
 *   sftp://u123.example.org:23/backups/website                        SFTP (Passwort oder privater Schlüssel)
 *   ftps://host/backups · ftp://host/backups                            FTP mit TLS bzw. unverschlüsselt
 *   /mnt/nas/sicherungen                                                eingehängter Ordner (NFS, SMB …)
 * Alles über curl (WebDAV, SFTP, FTP/FTPS) bzw. Dateifunktionen – keine Zusatzpakete. Die Dateien sind schon verschlüsselt
 * (Core\Backup\Vault), die Übertragung trotzdem nach Möglichkeit ebenso (https, sftp, ftps).
 *
 * $t: url, user, pass (Klartext – Core\Backup\Offsite entschlüsselt), key_file (SFTP), fingerprint (SFTP, SHA256 Base64 des
 * Server-Schlüssels), insecure (TLS-Zertifikat nicht prüfen, z. B. NAS mit eigenem Zertifikat).
 */
final class Transport
{
    public function __construct(private readonly array $t) {}

    /** webdav | sftp | ftps | ftp | dir | '' (unbekannt) */
    public static function kind(string $url): string
    {
        return match (true) {
            str_starts_with($url, '/') => 'dir',
            (bool) preg_match('~^https?://~i', $url) => 'webdav',
            (bool) preg_match('~^sftp://~i', $url) => 'sftp',
            (bool) preg_match('~^ftps://~i', $url) => 'ftps',
            (bool) preg_match('~^ftp://~i', $url) => 'ftp',
            default => '',
        };
    }

    private function k(): string
    {
        return self::kind((string) $this->t['url']);
    }

    private function base(): string
    {
        return rtrim((string) $this->t['url'], '/');
    }

    /** Datei hochladen (Name ohne Pfad) */
    public function put(string $local, string $name): void
    {
        self::checkName($name);
        if ($this->k() === 'dir') {
            @mkdir($this->base(), 0770, true);
            if (!copy($local, $this->base() . "/$name.part") || !rename($this->base() . "/$name.part", $this->base() . "/$name")) throw new \RuntimeException('Kopieren in den Ordner fehlgeschlagen.');
            return;
        }
        if ($this->k() === 'webdav') $this->mkcol();
        $fh = fopen($local, 'rb');
        $this->curl($this->fileUrl($name), [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fh, CURLOPT_INFILESIZE => filesize($local),
            CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR_RETRY], [200, 201, 204]);
        fclose($fh);
    }

    /** Dateinamen im Zielordner (ohne Unterordner); fehlt der Ordner: [] */
    public function list(): array
    {
        if ($this->k() === 'dir') return is_dir($this->base()) ? array_values(array_filter(scandir($this->base()) ?: [], fn($f) => is_file($this->base() . "/$f"))) : [];
        if ($this->k() === 'webdav') {
            try {
                $xml = $this->curl($this->base() . '/', [CURLOPT_CUSTOMREQUEST => 'PROPFIND', CURLOPT_HTTPHEADER => ['Depth: 1', 'Content-Type: application/xml'],
                    CURLOPT_POSTFIELDS => '<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>'], [207]);
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'HTTP 404')) return [];
                throw $e;
            }
            $out = [];
            if (preg_match_all('~<(?:[a-z0-9]+:)?response\b.*?</(?:[a-z0-9]+:)?response>~is', $xml, $rs)) {
                foreach ($rs[0] as $r) {
                    if (preg_match('~<(?:[a-z0-9]+:)?collection\b~i', $r) || !preg_match('~<(?:[a-z0-9]+:)?href>([^<]+)</~i', $r, $m)) continue;
                    $out[] = rawurldecode(basename(rtrim(html_entity_decode($m[1]), '/')));
                }
            }
            return $out;
        }
        try {
            $raw = $this->curl($this->base() . '/', [CURLOPT_DIRLISTONLY => true]);
        } catch (\RuntimeException $e) {
            // Ordner gibt es (noch) nicht
            if (preg_match('~(could not|failed to|Access denied|No such|not found|RETR|CWD|550|78)~i', $e->getMessage())) return [];
            throw $e;
        }
        return array_values(array_filter(array_map('trim', explode("\n", $raw)), fn($f) => $f !== '' && $f !== '.' && $f !== '..'));
    }

    public function delete(string $name): void
    {
        self::checkName($name);
        switch ($this->k()) {
            case 'dir':
                @unlink($this->base() . "/$name");
                return;
            case 'webdav':
                $this->curl($this->fileUrl($name), [CURLOPT_CUSTOMREQUEST => 'DELETE'], [200, 204, 404]);
                return;
            case 'sftp':
                $this->curl($this->base() . '/', [CURLOPT_NOBODY => true, CURLOPT_POSTQUOTE => ['rm "' . $this->sftpPath() . '/' . $name . '"']]);
                return;
            default:
                $this->curl($this->base() . '/', [CURLOPT_NOBODY => true, CURLOPT_POSTQUOTE => ['DELE ' . $name]]);
        }
    }

    public function get(string $name, string $local): void
    {
        self::checkName($name);
        if ($this->k() === 'dir') {
            if (!copy($this->base() . "/$name", $local)) throw new \RuntimeException('Datei nicht gefunden.');
            return;
        }
        $fh = fopen($local, 'wb');
        try {
            $this->curl($this->fileUrl($name), [CURLOPT_FILE => $fh], [200]);
        } finally {
            fclose($fh);
        }
    }

    /** Verbindung prüfen: kleine Datei schreiben, auflisten, löschen → null = in Ordnung, sonst Fehlermeldung */
    public function test(): ?string
    {
        if ($this->k() === '') return __('Unbekannte Adresse – erlaubt sind https:// (WebDAV), sftp://, ftps://, ftp:// oder ein Ordner (/…).');
        $tmp = tempnam(sys_get_temp_dir(), 'kbt');
        file_put_contents($tmp, 'KLXM Studio – Verbindungstest ' . date('c'));
        $name = 'klxm-verbindungstest-' . bin2hex(random_bytes(4)) . '.txt';   // ohne Punkt am Anfang: manche FTP-Server listen solche Dateien nicht
        try {
            $this->put($tmp, $name);
            if (!in_array($name, $this->list(), true)) return __('Datei wurde geschrieben, erscheint aber nicht in der Liste des Zielordners.');
            $this->delete($name);
            if (in_array($name, $this->list(), true)) return __('Testdatei ließ sich nicht löschen – alte Sicherungen könnten nicht aufgeräumt werden.');
            return null;
        } catch (\Throwable $e) {
            return $e->getMessage();
        } finally {
            @unlink($tmp);
        }
    }

    private function fileUrl(string $name): string
    {
        return $this->base() . '/' . rawurlencode($name);
    }

    /** WebDAV: Zielordner samt fehlenden Elternordnern anlegen (MKCOL; vorhandene → 405, wird ignoriert) */
    private function mkcol(): void
    {
        $p = parse_url($this->base());
        $root = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $path = '';
        foreach (array_filter(explode('/', (string) ($p['path'] ?? ''))) as $seg) {
            $path .= '/' . $seg;
            try { $this->curl($root . $path . '/', [CURLOPT_CUSTOMREQUEST => 'MKCOL'], [201, 301, 405, 403, 409]); } catch (\RuntimeException) { /* Elternordner ohne Rechte – weiter */ }
        }
    }

    /** SFTP: Pfad des Zielordners für Befehle (/~/x → ~/x) */
    private function sftpPath(): string
    {
        $path = rawurldecode((string) (parse_url($this->base(), PHP_URL_PATH) ?? ''));
        return str_starts_with($path, '/~/') ? substr($path, 1) : $path;
    }

    private static function checkName(string $name): void
    {
        if (!preg_match('~^\.?[A-Za-z0-9][A-Za-z0-9._-]{0,180}$~', $name)) throw new \InvalidArgumentException('Ungültiger Dateiname für das Sicherungsziel.');
    }

    /** Anfrage ausführen → Antwort; Ausnahme mit lesbarer Meldung bei Fehler bzw. unerwartetem HTTP-Status */
    private function curl(string $url, array $opt = [], array $okHttp = []): string
    {
        $ch = curl_init($url);
        $user = (string) ($this->t['user'] ?? '');
        $base = [CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 0, CURLOPT_LOW_SPEED_LIMIT => 1024,
            CURLOPT_LOW_SPEED_TIME => 120, CURLOPT_FOLLOWLOCATION => false, CURLOPT_USERAGENT => 'KLXM-Studio-Backup/' . CMS_VERSION];
        if ($user !== '') $base[CURLOPT_USERPWD] = $user . ':' . (string) ($this->t['pass'] ?? '');
        $k = $this->k();
        if ($k === 'ftps') $base[CURLOPT_USE_SSL] = CURLUSESSL_ALL;
        if (in_array($k, ['webdav', 'ftps'], true) && !empty($this->t['insecure'])) { $base[CURLOPT_SSL_VERIFYPEER] = false; $base[CURLOPT_SSL_VERIFYHOST] = 0; }
        if ($k === 'sftp') {
            if (!empty($this->t['key_file'])) { $base[CURLOPT_SSH_PRIVATE_KEYFILE] = (string) $this->t['key_file']; $base[CURLOPT_SSH_AUTH_TYPES] = CURLSSH_AUTH_PUBLICKEY; }
            if (!empty($this->t['fingerprint']) && defined('CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256')) $base[CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256] = (string) $this->t['fingerprint'];
        }
        // Reihenfolge zählt: RETURNTRANSFER (auch false) nach CURLOPT_FILE würde die Ausgabe-Datei wieder auf STDOUT umstellen
        if (!isset($opt[CURLOPT_FILE])) $base[CURLOPT_RETURNTRANSFER] = true;
        curl_setopt_array($ch, $base);
        curl_setopt_array($ch, $opt);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        $no = curl_errno($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($res === false || $no !== 0) throw new \RuntimeException(trim("curl $no: $err"));
        if ($k === 'webdav' && $okHttp && !in_array($code, $okHttp, true)) {
            throw new \RuntimeException(match (true) {
                $code === 401 => __('Anmeldung abgelehnt (HTTP 401) – Benutzername oder Passwort (bei Nextcloud: App-Passwort) prüfen.'),
                $code === 507 => __('Speicher am Ziel voll (HTTP 507).'),
                default => "HTTP $code",
            });
        }
        return is_string($res) ? $res : '';
    }
}
