<?php
// SPDX-License-Identifier: MIT
declare(strict_types=1);

namespace Klxm\Check;

/**
 * DKIM (RFC 6376, 8463): öffentliche Schlüssel unter {selektor}._domainkey.{domain} suchen – gängige Selektoren plus
 * ein vom Besucher angegebener – und prüfen: Schlüsseltyp, Länge (Bits), Testmodus (t=y), widerrufen (leeres p=).
 * Den Selektor einer Mail verrät die Kopfzeile „DKIM-Signature: … s=selektor“.
 */
final class Dkim
{
    /** Häufige Selektoren (Hoster, Microsoft 365, Google Workspace, Versanddienste) */
    public const SELECTORS = [
        'default', 'dkim', 'mail', 'selector1', 'selector2', 'google', 'k1', 'k2', 'k3', 's1', 's2', 'smtp', 'mandrill', 'mxvault',
        'zoho', 'zmail', 'mailjet', 'mta', 'email', 'key1', 'key2', 'dkim1', 'dkim2', 'sig1', 'fm1', 'fm2', 'fm3', 'protonmail',
        'protonmail2', 'protonmail3', 'hs1', 'hs2', 'cm', 'strato-dkim-0001', 'strato-dkim-0002', 'everlytickey1', 'mailo', 'x',
        '20230601', '20221208', '20210112', '20161025', 'amazonses', 'scph0122', 'sendinblue', 'brevo', 'mailchimp', 'mte1', 'kl', 'kl2',
    ];

    public static function validSelector(string $s): bool
    {
        return (bool) preg_match('~^[a-z0-9]([a-z0-9_-]{0,62})(\.[a-z0-9]([a-z0-9_-]{0,62}))*$~i', $s) && strlen($s) <= 100;
    }

    /** Schlüssel-Eintrag zerlegen und bewerten. @return array{tags: array, type: string, bits: ?int, revoked: bool, testing: bool, error: ?string} */
    public static function parseKey(string $txt): array
    {
        $tags = [];
        foreach (explode(';', $txt) as $p) {
            if (!str_contains($p, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $p, 2));
            $tags[strtolower($k)] ??= $v;
        }
        $type = strtolower($tags['k'] ?? 'rsa');
        $pb = preg_replace('~\s+~', '', (string) ($tags['p'] ?? '')) ?? '';
        $out = ['tags' => $tags, 'type' => $type, 'bits' => null, 'revoked' => $pb === '', 'testing' => in_array('y', array_map('trim', explode(':', strtolower($tags['t'] ?? ''))), true), 'error' => null];
        if (!array_key_exists('p', $tags)) {
            $out['error'] = lt('Das Tag „p=“ (öffentlicher Schlüssel) fehlt.');
            $out['revoked'] = false;
            return $out;
        }
        if ($pb === '') return $out;
        $der = base64_decode($pb, true);
        if ($der === false) {
            $out['error'] = lt('Der Schlüssel ist kein gültiges Base64.');
            return $out;
        }
        if ($type === 'ed25519') {
            $out['bits'] = strlen($der) === 32 ? 256 : null;
            if (strlen($der) !== 32) $out['error'] = lt('Ed25519-Schlüssel müssen 32 Byte lang sein.');
            return $out;
        }
        if ($type !== 'rsa') {
            $out['error'] = lt('Unbekannter Schlüsseltyp „{type}“.', ['type' => $type]);
            return $out;
        }
        $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($pb, 64, "\n") . "-----END PUBLIC KEY-----\n";
        $key = @openssl_pkey_get_public($pem);
        if (!$key) {
            // Manche Anbieter veröffentlichen den nackten RSAPublicKey (PKCS#1)
            $key = @openssl_pkey_get_public("-----BEGIN RSA PUBLIC KEY-----\n" . chunk_split($pb, 64, "\n") . "-----END RSA PUBLIC KEY-----\n");
        }
        if ($key) {
            $d = openssl_pkey_get_details($key);
            $out['bits'] = isset($d['bits']) ? (int) $d['bits'] : null;
        } else {
            // Näherung aus der Länge (DER-Overhead ca. 38 Byte)
            $out['bits'] = (int) round((strlen($der) - 38) * 8 / 256) * 256 ?: null;
            $out['error'] = lt('Der Schlüssel ließ sich nicht lesen – Länge nur geschätzt.');
        }
        return $out;
    }

    public static function check(string $domain, Dns $dns, string $custom = ''): Result
    {
        $r = new Result('dkim', lt('DKIM'));
        $selectors = self::SELECTORS;
        $custom = strtolower(trim($custom));
        if ($custom !== '') {
            if (!self::validSelector($custom)) throw new CheckException(lt('Der Selektor enthält ungültige Zeichen.'));
            array_unshift($selectors, $custom);
        }
        $selectors = array_values(array_unique($selectors));
        $found = [];
        foreach ($selectors as $sel) {
            if ($dns->expired()) break;
            $name = $sel . '._domainkey.' . $domain;
            $txt = $dns->txt($name) ?? [];
            // Manche Einträge ohne „v=DKIM1“ – dann zählt ein p=
            $keys = array_values(array_filter($txt, fn($x) => preg_match('~(^|;)\s*v\s*=\s*DKIM1~i', $x) || preg_match('~(^|;)\s*p\s*=~i', $x)));
            if ($keys) $found[$sel] = $keys;
        }
        $rows = [];
        foreach ($found as $sel => $keys) {
            foreach ($keys as $k) {
                $info = self::parseKey($k);
                $status = 'ok';
                $notes = [];
                if ($info['error']) {
                    $status = 'error';
                    $notes[] = $info['error'];
                }
                if ($info['revoked']) {
                    $status = 'info';
                    $notes[] = lt('widerrufen (leeres p=)');
                } elseif ($info['type'] === 'rsa' && $info['bits'] !== null) {
                    if ($info['bits'] < 1024) {
                        $status = 'error';
                        $r->error(lt('Selektor „{sel}“: RSA-Schlüssel mit nur {bits} Bit – unsicher und wird von Empfängern abgelehnt. Neuen Schlüssel mit 2048 Bit erzeugen.', ['sel' => $sel, 'bits' => $info['bits']]));
                    } elseif ($info['bits'] < 2048) {
                        $status = $status === 'ok' ? 'warn' : $status;
                        $r->warn(lt('Selektor „{sel}“: RSA-Schlüssel mit {bits} Bit – empfohlen sind 2048 Bit.', ['sel' => $sel, 'bits' => $info['bits']]));
                    }
                }
                if ($info['testing']) {
                    $notes[] = lt('Testmodus (t=y)');
                    $r->info(lt('Selektor „{sel}“ steht im Testmodus (t=y) – Empfänger behandeln Signaturen nachsichtig. Nach dem Test entfernen.', ['sel' => $sel]));
                }
                $rows[] = [$sel, strtoupper($info['type']), $info['bits'] ? $info['bits'] . ' Bit' : '–', [$notes ? implode(', ', $notes) : lt('in Ordnung'), $status]];
            }
        }
        if (!$found) {
            $r->info(lt('Unter {n} gängigen Selektoren wurde kein DKIM-Schlüssel gefunden. Das heißt nicht, dass DKIM fehlt: Den Selektor findest du im Kopf einer versendeten Mail („DKIM-Signature: … s=…“) – trage ihn unter „Optionen“ ein.', ['n' => count($selectors)]));
            $r->summary = lt('Kein DKIM-Schlüssel unter gängigen Selektoren gefunden.');
            $r->status = 'info';
        } else {
            $active = array_filter($rows, fn($row) => ($row[3][1] ?? '') !== 'info');
            if ($active) {
                $r->ok(lt('DKIM-Schlüssel gefunden: {list}.', ['list' => implode(', ', array_keys($found))]));
                $r->summary = lt('{n} DKIM-Selektor(en) gefunden.', ['n' => count($found)]);
            } else {
                $r->info(lt('Nur widerrufene Schlüssel (leeres p=) gefunden – der aktuelle Selektor ist nicht in der Liste. Trage ihn unter „Optionen“ ein.'));
                $r->summary = lt('Nur widerrufene DKIM-Schlüssel gefunden.');
                $r->status = 'info';
            }
            $r->table(lt('Gefundene Schlüssel'), [lt('Selektor'), lt('Typ'), lt('Länge'), lt('Status')], $rows);
        }
        $r->data = ['found' => array_keys($found), 'tested' => count($selectors)];
        return $r;
    }
}
