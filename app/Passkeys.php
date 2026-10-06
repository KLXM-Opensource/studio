<?php
declare(strict_types=1);

namespace Core;

use lbuchs\WebAuthn\Attestation\AttestationObject;
use lbuchs\WebAuthn\Attestation\AuthenticatorData;

/**
 * Passkeys (WebAuthn Level 2): Registrierung und Anmeldung mit Touch ID, Face ID, Windows Hello, Android-Bildschirmsperre,
 * Passwortmanagern (iCloud-Schlüsselbund, Google, 1Password, Bitwarden …) oder Sicherheitsschlüsseln.
 *
 * - Auswertung von CBOR/COSE und Attestierung: lbuchs/webauthn (MIT, ohne weitere Abhängigkeiten). Die Zeremonie selbst
 *   (Challenge, Typ, exakte Herkunft, RP-ID-Hash, Flags, Signatur, Zähler) prüft diese Klasse – strenger als die Bibliothek
 *   (Herkunft muss exakt der aufgerufenen Adresse entsprechen) und auch für http://*.localhost (lokale Tests, sicherer Kontext).
 * - RP-ID = Host der aktuellen Anfrage (ohne Port). Jede Website hat ihre eigene Domain → Passkeys gelten je Domain.
 * - Challenge: 32 Byte, in der Sitzung, 5 Minuten, einmal verwendbar (je Zweck: reg, login, 2fa).
 * - Speicherung: Tabelle user_passkeys der Datenbank, zu der das Konto gehört (Netzwerk-Konten: Netzwerk-Website, eine Zeile
 *   je Domain). Nur öffentlicher Schlüssel (PEM), Kennung als base64url + SHA-256 (eindeutig).
 * - Verlangt werden auffindbare Anmeldedaten (resident keys) – nötig für die Anmeldung ohne Passwort.
 */
final class Passkeys
{
    public const TTL = 300;
    public const MAX_PER_USER = 20;
    public const NAME_MAX = 60;
    /** Tabelle der Konten der Verwaltung; Erweiterungen (z. B. Mitglieder) übergeben ihre eigene – nie dieselbe */
    public const TABLE = 'user_passkeys';

    /** Bekannte Anbieter (AAGUID → Name) – nur als Namensvorschlag */
    private const AAGUIDS = [
        'fbfc3007-154e-4ecc-8c0b-6e020557d7bd' => 'iCloud-Schlüsselbund',
        'dd4ec289-e01d-41c9-bb89-70fa845d4bf2' => 'iCloud-Schlüsselbund',
        'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4' => 'Google Passwortmanager',
        'adce0002-35bc-c60a-648b-0b25f1f05503' => 'Chrome (Mac)',
        '08987058-cadc-4b81-b6e1-30de50dcbe96' => 'Windows Hello',
        '9ddd1817-af5a-4672-a2b9-3e3dd95000a9' => 'Windows Hello',
        '6028b017-b1d4-4c02-b4b3-afcdafc96bb2' => 'Windows Hello',
        'bada5566-a7aa-401f-bd96-45619a55120d' => '1Password',
        'd548826e-79b4-db40-a3d8-11116f7e8349' => 'Bitwarden',
        '531126d6-e717-415c-9320-3d9aa6981239' => 'Dashlane',
        'fdb141b2-5d84-443e-8a35-4698c205a502' => 'KeePassXC',
        'cb69481e-8ff7-4039-93ec-0a2729a154a8' => 'YubiKey',
        'ee882879-721c-4913-9775-3dfcce97072a' => 'YubiKey',
    ];

    // ================================================================= Tabelle

    /** Tabelle anlegen (Database::migrate für jede Website, Network::ensureSchema für die Netzwerk-Datenbank) – idempotent */
    public static function ensureTable(Database $db, string $table = self::TABLE): void
    {
        $table = self::tbl($table);
        $my = $db->driver === 'mysql';
        $pk = $my ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $tail = $my ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
        $db->pdo->exec("CREATE TABLE IF NOT EXISTS $table (id $pk, user_id INT NOT NULL, rp_id VARCHAR(191) NOT NULL,
            cred_hash VARCHAR(64) NOT NULL UNIQUE, credential_id TEXT NOT NULL, public_key TEXT NOT NULL, sign_count INT NOT NULL DEFAULT 0,
            transports VARCHAR(120) NULL, aaguid VARCHAR(36) NULL, name VARCHAR(191) NOT NULL, uv INT NOT NULL DEFAULT 0,
            backup INT NOT NULL DEFAULT 0, created_at VARCHAR(25) NULL, last_used_at VARCHAR(25) NULL)$tail");
        if (!$my) $db->pdo->exec("CREATE INDEX IF NOT EXISTS {$table}_user ON $table (user_id, rp_id)");
    }

    // ================================================================= Adresse, Kennungen

    /** RP-ID: Host der aktuellen Anfrage ohne Port (kleingeschrieben) */
    public static function rpId(): string
    {
        $host = strtolower(app()->request ? app()->request->host() : 'localhost');
        $host = preg_replace('~:\d+$~', '', $host);
        return rtrim(trim($host, '[]'), '.');
    }

    /** Erwartete Herkunft (Schema, Host, Port) – muss exakt passen */
    public static function origin(): string
    {
        $r = app()->request;
        return ($r && $r->isSecure() ? 'https://' : 'http://') . strtolower($r ? $r->host() : 'localhost');
    }

    /** Passkeys sind nur unter einem Domainnamen in einem sicheren Kontext möglich (HTTPS oder localhost) */
    public static function available(): bool
    {
        $rp = self::rpId();
        if ($rp === '' || filter_var($rp, FILTER_VALIDATE_IP)) return false;
        $local = $rp === 'localhost' || str_ends_with($rp, '.localhost');
        return $local || (app()->request && app()->request->isSecure());
    }

    /** Benutzerkennung für den Authenticator (keine E-Mail, keine fortlaufende ID) – 32 Byte */
    public static function userHandle(string $scope, int $uid, string $key): string
    {
        return hash_hmac('sha256', 'passkey-user|' . $scope . '|' . $uid, $key, true);
    }

    public static function b64(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function unb64(mixed $s): ?string
    {
        if (!is_string($s) || $s === '' || strlen($s) > 20000 || !preg_match('~^[A-Za-z0-9_\-+/=]+$~', $s)) return null;
        $bin = base64_decode(strtr($s, '-_', '+/'), true);
        return $bin === false ? null : $bin;
    }

    /** Tabellenname prüfen (nur Buchstaben, Ziffern, Unterstrich – er steht im SQL) */
    private static function tbl(string $table): string
    {
        if (!preg_match('~^[a-z][a-z0-9_]{0,62}$~', $table)) throw new \InvalidArgumentException('Passkeys: ungültige Tabelle');
        return $table;
    }

    /** Schlüssel der Challenge in der Sitzung (Zweck bzw. Registrierung) */
    private static function slot(string $slot): string
    {
        if (!preg_match('~^[a-z0-9_]{1,31}$~', $slot)) throw new \InvalidArgumentException('Passkeys: ungültiger Zweck');
        return $slot;
    }

    public static function credHash(string $rawId): string
    {
        return hash('sha256', $rawId);
    }

    // ================================================================= Verwaltung

    /** @return list<array> Passkeys eines Kontos (optional nur einer Domain), neueste zuerst */
    public static function list(Database $db, int $uid, ?string $rpId = null, string $table = self::TABLE): array
    {
        $sql = 'SELECT id, rp_id, name, aaguid, transports, backup, uv, created_at, last_used_at FROM ' . self::tbl($table) . ' WHERE user_id = ?';
        $args = [$uid];
        if ($rpId !== null) {
            $sql .= ' AND rp_id = ?';
            $args[] = $rpId;
        }
        try {
            return $db->fetchAll($sql . ' ORDER BY id DESC', $args);
        } catch (\Throwable) {
            return [];   // Tabelle fehlt (noch nicht migriert)
        }
    }

    public static function count(Database $db, int $uid, ?string $rpId = null, string $table = self::TABLE): int
    {
        $t = self::tbl($table);
        try {
            return $rpId === null
                ? (int) $db->fetchValue("SELECT COUNT(*) FROM $t WHERE user_id = ?", [$uid])
                : (int) $db->fetchValue("SELECT COUNT(*) FROM $t WHERE user_id = ? AND rp_id = ?", [$uid, $rpId]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<int,int> Anzahl je Konto (Benutzerliste) */
    public static function counts(Database $db, string $table = self::TABLE): array
    {
        try {
            $out = [];
            foreach ($db->fetchAll('SELECT user_id, COUNT(*) AS n FROM ' . self::tbl($table) . ' GROUP BY user_id') as $r) $out[(int) $r['user_id']] = (int) $r['n'];
            return $out;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function rename(Database $db, int $uid, int $id, string $name, string $table = self::TABLE): bool
    {
        $name = self::cleanName($name);
        if ($name === '') return false;
        return $db->query('UPDATE ' . self::tbl($table) . ' SET name = ? WHERE id = ? AND user_id = ?', [$name, $id, $uid])->rowCount() > 0;
    }

    public static function delete(Database $db, int $uid, int $id, string $table = self::TABLE): bool
    {
        return $db->query('DELETE FROM ' . self::tbl($table) . ' WHERE id = ? AND user_id = ?', [$id, $uid])->rowCount() > 0;
    }

    public static function deleteAll(Database $db, int $uid, string $table = self::TABLE): int
    {
        try {
            return $db->query('DELETE FROM ' . self::tbl($table) . ' WHERE user_id = ?', [$uid])->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function cleanName(string $name): string
    {
        $name = trim(preg_replace('~[\x00-\x1f\x7f]+~u', ' ', $name) ?? '');
        return mb_substr($name, 0, self::NAME_MAX);
    }

    /** Namensvorschlag aus dem Anbieter (AAGUID) */
    public static function providerName(?string $aaguid): ?string
    {
        return $aaguid ? (self::AAGUIDS[strtolower($aaguid)] ?? null) : null;
    }

    // ================================================================= Registrierung

    /**
     * Optionen für navigator.credentials.create() (JSON, Binärwerte base64url). Challenge in der Sitzung.
     * @param list<string> $exclude vorhandene Kennungen (base64url) – kein zweites Mal derselbe Authenticator
     */
    public static function creationOptions(string $handle, string $userName, string $displayName, array $exclude, array $pending = [], string $slot = 'reg'): array
    {
        $challenge = random_bytes(32);
        app()->session->set('_pk_' . self::slot($slot), ['c' => self::b64($challenge), 'at' => time(), 'rp' => self::rpId(), 'h' => self::b64($handle)] + $pending);
        $rpName = trim(site_name()) ?: CMS_NAME;
        return [
            'rp' => ['name' => mb_substr($rpName, 0, 64), 'id' => self::rpId()],
            'user' => ['id' => self::b64($handle), 'name' => $userName, 'displayName' => $displayName ?: $userName],
            'challenge' => self::b64($challenge),
            // EdDSA, ES256, RS256
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -8], ['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -257]],
            'timeout' => 180000,
            'attestation' => 'none',
            'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'preferred'],
            'excludeCredentials' => array_map(fn($id) => ['type' => 'public-key', 'id' => $id], $exclude),
            'extensions' => ['credProps' => true],
        ];
    }

    /** Offene Registrierung (Sitzung) – z. B. um den gewünschten Namen zu lesen */
    public static function pending(string $slot = 'reg'): ?array
    {
        $p = app()->session->get('_pk_' . self::slot($slot));
        return is_array($p) && time() - (int) ($p['at'] ?? 0) <= self::TTL ? $p : null;
    }

    /**
     * Antwort von navigator.credentials.create() prüfen und speichern.
     * @return array|string gespeicherte Zeile oder Fehlertext
     */
    public static function register(Database $db, int $uid, array $in, string $name, string $table = self::TABLE, string $slot = 'reg'): array|string
    {
        $t = self::tbl($table);
        $p = self::pending($slot);
        app()->session->forget('_pk_' . self::slot($slot));
        if (!$p || ($p['rp'] ?? '') !== self::rpId()) return __('Die Anfrage ist abgelaufen. Bitte erneut versuchen.');
        $resp = is_array($in['response'] ?? null) ? $in['response'] : [];
        $rawId = self::unb64($in['rawId'] ?? $in['id'] ?? null);
        $cdj = self::unb64($resp['clientDataJSON'] ?? null);
        $att = self::unb64($resp['attestationObject'] ?? null);
        if (($in['type'] ?? '') !== 'public-key' || $rawId === null || $cdj === null || $att === null || strlen($rawId) > 1023) {
            return __('Die Antwort des Geräts ist unvollständig.');
        }
        if ($err = self::checkClientData($cdj, 'webauthn.create', (string) $p['c'])) return $err;
        try {
            $obj = new AttestationObject($att, ['none', 'packed', 'fido-u2f', 'apple', 'android-key', 'android-safetynet', 'tpm']);
            $ad = $obj->getAuthenticatorData();
            if (!$obj->validateRpIdHash(hash('sha256', self::rpId(), true))) return __('Der Passkey gehört zu einer anderen Adresse.');
            if (!$obj->validateAttestation(hash('sha256', $cdj, true))) return __('Die Bestätigung des Geräts ist ungültig.');
            if (!$ad->getUserPresent()) return __('Der Passkey wurde nicht bestätigt.');
            $credId = (string) $ad->getCredentialId();
            $pem = (string) $ad->getPublicKeyPem();
            $aaguid = self::uuid((string) $ad->getAAGUID());
            $uv = (bool) $ad->getUserVerified();
            $backup = (bool) $ad->getIsBackupEligible();
            $count = (int) $ad->getSignCount();
        } catch (\Throwable $e) {
            error_log('[passkey] register: ' . $e->getMessage());
            return __('Die Antwort des Geräts konnte nicht gelesen werden.');
        }
        if (!hash_equals($credId, $rawId)) return __('Die Antwort des Geräts ist unvollständig.');
        if (self::count($db, $uid, null, $t) >= self::MAX_PER_USER) return __('Höchstens {n} Passkeys je Konto.', ['n' => self::MAX_PER_USER]);
        $hash = self::credHash($credId);
        if ($db->fetchValue("SELECT COUNT(*) FROM $t WHERE cred_hash = ?", [$hash])) return __('Dieser Passkey ist bereits eingerichtet.');
        $transports = array_values(array_intersect((array) ($resp['transports'] ?? []), ['usb', 'nfc', 'ble', 'hybrid', 'internal', 'smart-card']));
        $name = self::cleanName($name) ?: (self::providerName($aaguid) ?? __('Passkey'));
        $id = $db->insert($t, ['user_id' => $uid, 'rp_id' => self::rpId(), 'cred_hash' => $hash, 'credential_id' => self::b64($credId),
            'public_key' => $pem, 'sign_count' => $count, 'transports' => implode(',', $transports) ?: null, 'aaguid' => $aaguid,
            'name' => $name, 'uv' => $uv ? 1 : 0, 'backup' => $backup ? 1 : 0, 'created_at' => now()]);
        return ['id' => $id, 'name' => $name, 'aaguid' => $aaguid];
    }

    // ================================================================= Anmeldung

    /**
     * Optionen für navigator.credentials.get(). Ohne $creds: auffindbare Anmeldedaten (Anmeldung ohne Passwort, Autofill).
     * @param list<array{credential_id:string, transports:?string}> $creds
     */
    public static function requestOptions(string $purpose, array $creds = [], bool $uv = false): array
    {
        $challenge = random_bytes(32);
        app()->session->set('_pk_' . self::slot($purpose), ['c' => self::b64($challenge), 'at' => time(), 'rp' => self::rpId(), 'uv' => $uv]);
        $o = ['challenge' => self::b64($challenge), 'rpId' => self::rpId(), 'timeout' => 180000, 'userVerification' => $uv ? 'required' : 'preferred'];
        if ($creds) {
            $o['allowCredentials'] = array_map(fn($c) => array_filter(['type' => 'public-key', 'id' => $c['credential_id'],
                'transports' => $c['transports'] ? explode(',', (string) $c['transports']) : null]), $creds);
        }
        return $o;
    }

    /** Kennung (base64url) der Antwort – um das Konto zu finden (Anmeldung ohne Passwort) */
    public static function credentialHash(array $in): ?string
    {
        $raw = self::unb64($in['rawId'] ?? $in['id'] ?? null);
        return $raw === null ? null : self::credHash($raw);
    }

    /** Zeile zur Kennung dieser Domain (optional nur für ein Konto) */
    public static function find(Database $db, string $credHash, ?int $uid = null, string $table = self::TABLE): ?array
    {
        $t = self::tbl($table);
        try {
            return $uid === null
                ? $db->fetch("SELECT * FROM $t WHERE cred_hash = ? AND rp_id = ?", [$credHash, self::rpId()])
                : $db->fetch("SELECT * FROM $t WHERE cred_hash = ? AND rp_id = ? AND user_id = ?", [$credHash, self::rpId(), $uid]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Antwort von navigator.credentials.get() für eine gespeicherte Zeile prüfen; Zähler und „zuletzt verwendet“ aktualisieren.
     * @param string $handle erwartete Benutzerkennung (binär) – wird mit userHandle der Antwort verglichen, falls vorhanden
     * @return string|null Fehlertext oder null (gültig)
     */
    public static function verify(Database $db, string $purpose, array $in, ?array $row, string $handle, bool $requireHandle = false, string $table = self::TABLE): ?string
    {
        $p = app()->session->get('_pk_' . self::slot($purpose));
        app()->session->forget('_pk_' . self::slot($purpose));
        if (!is_array($p) || time() - (int) ($p['at'] ?? 0) > self::TTL || ($p['rp'] ?? '') !== self::rpId()) {
            return __('Die Anfrage ist abgelaufen. Bitte erneut versuchen.');
        }
        if (!$row) return __('Dieser Passkey ist hier nicht eingerichtet.');
        $resp = is_array($in['response'] ?? null) ? $in['response'] : [];
        $cdj = self::unb64($resp['clientDataJSON'] ?? null);
        $authData = self::unb64($resp['authenticatorData'] ?? null);
        $sig = self::unb64($resp['signature'] ?? null);
        if (($in['type'] ?? '') !== 'public-key' || $cdj === null || $authData === null || $sig === null) return __('Die Antwort des Geräts ist unvollständig.');
        $uh = ($resp['userHandle'] ?? null) ? self::unb64($resp['userHandle']) : null;
        if (($requireHandle && $uh === null) || ($uh !== null && !hash_equals($handle, $uh))) return __('Dieser Passkey gehört zu einem anderen Konto.');
        if ($err = self::checkClientData($cdj, 'webauthn.get', (string) $p['c'])) return $err;
        try {
            $ad = new AuthenticatorData($authData);
        } catch (\Throwable) {
            return __('Die Antwort des Geräts konnte nicht gelesen werden.');
        }
        if (!hash_equals(hash('sha256', self::rpId(), true), $ad->getRpIdHash())) return __('Der Passkey gehört zu einer anderen Adresse.');
        if (!$ad->getUserPresent()) return __('Der Passkey wurde nicht bestätigt.');
        if (!empty($p['uv']) && !$ad->getUserVerified()) return __('Das Gerät hat Sie nicht per Fingerabdruck, Gesicht oder PIN bestätigt.');
        if (!self::signatureValid($authData . hash('sha256', $cdj, true), $sig, (string) $row['public_key'])) {
            return __('Die Signatur des Passkeys ist ungültig.');
        }
        // Zähler: steigt bei Geräten mit Zähler – ein kleinerer/gleicher Wert deutet auf einen kopierten Schlüssel
        $new = (int) $ad->getSignCount();
        $old = (int) $row['sign_count'];
        if (($new !== 0 || $old !== 0) && $new <= $old) {
            error_log('[passkey] sign counter not increasing for passkey ' . (int) $row['id']);
            return __('Dieser Passkey wurde abgelehnt (Zähler ungültig). Bitte einen anderen Passkey verwenden oder die Administration informieren.');
        }
        $db->update(self::tbl($table), ['sign_count' => $new, 'last_used_at' => now(), 'backup' => $ad->getIsBackupEligible() ? 1 : (int) $row['backup']],
            'id = :id', ['id' => $row['id']]);
        return null;
    }

    /** clientDataJSON: Typ, Challenge (einmalig), exakte Herkunft, nicht eingebettet */
    private static function checkClientData(string $json, string $type, string $challenge): ?string
    {
        $c = json_decode($json, true);
        if (!is_array($c) || ($c['type'] ?? '') !== $type) return __('Die Antwort des Geräts ist ungültig.');
        $got = self::unb64($c['challenge'] ?? null);
        $want = self::unb64($challenge);
        if ($got === null || $want === null || !hash_equals($want, $got)) return __('Die Anfrage ist abgelaufen. Bitte erneut versuchen.');
        if (!hash_equals(self::origin(), strtolower((string) ($c['origin'] ?? ''))) || !empty($c['crossOrigin'])) {
            return __('Der Passkey wurde von einer anderen Adresse aus verwendet.');
        }
        return null;
    }

    /** Signatur prüfen: EdDSA (Ed25519) mit libsodium, ES256/RS256 mit OpenSSL (SHA-256) */
    private static function signatureValid(string $data, string $sig, string $pem): bool
    {
        if (preg_match('~BEGIN PUBLIC KEY-+\s+([^-]+?)\s*-+END PUBLIC KEY~', $pem, $m)) {
            $der = base64_decode(preg_replace('~\s+~', '', $m[1]), true);
            $prefix = "\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00";   // SubjectPublicKeyInfo Ed25519
            if (is_string($der) && strlen($der) === 44 && str_starts_with($der, $prefix)) {
                return strlen($sig) === SODIUM_CRYPTO_SIGN_BYTES && sodium_crypto_sign_verify_detached($sig, $data, substr($der, 12));
            }
        }
        $key = openssl_pkey_get_public($pem);
        return $key !== false && openssl_verify($data, $sig, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    private static function uuid(string $bin): ?string
    {
        if (strlen($bin) !== 16 || trim($bin, "\0") === '') return null;
        $h = bin2hex($bin);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
    }
}
