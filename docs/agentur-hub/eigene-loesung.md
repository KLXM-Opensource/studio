# Eigene Lösung an den Agentur Hub anbinden

Schritt für Schritt für ein eigenes System – etwa eine Laravel-, Symfony- oder WordPress-Website –, das sich wie eine
KLXM-Studio-Instanz an einen Agentur Hub anbindet: Anmeldung der Agentur per OpenID Connect und signierte Status-Abfrage.
Beides ist unabhängig voneinander; Sie können auch nur eines umsetzen.

Grundlagen: [OIDC-Profil](oidc-profil.md), [`status-v1`](monitoring-status-v1.md), [Selbstauskunft](instance-v1.md).
Die Code-Beispiele auf dieser Seite stehen unter der MIT-Lizenz und dürfen frei übernommen werden.

## 1. Instanz im Hub registrieren

1. Im Hub: **Verwalten → Instanz registrieren**, Adresse der Website eintragen. Der Agentur Hub kennt derzeit die Systeme
   „KLXM Studio“ und „REDAXO 6“ und belegt die Adressen danach vor; für eine eigene Lösung wählen Sie eines davon und ersetzen
   die vorbelegten Adressen.
2. Adressen Ihres Systems eintragen – exakt so, wie Ihr System sie später verwendet:

   | Angabe | Beispiel |
   |---|---|
   | Redirect-URI | `https://website.example.org/auth/hub/callback` |
   | Einstieg (`initiate_login_uri`) | `https://website.example.org/auth/hub/start` |
   | Nach dem Abmelden | `https://website.example.org/login` |
   | Status-Adresse | `https://website.example.org/.well-known/status` |

   Liefert Ihr System die [Selbstauskunft](instance-v1.md) (`/.well-known/klxm-studio`), übernimmt der Hub die Adressen mit
   „Adressen ermitteln“.
3. Client-ID und Client-Geheimnis notieren – der Hub zeigt das Geheimnis **nur einmal**.
4. **Freigaben:** Personen der Agentur mit Rolle `admin` oder `redaktion` für diese Instanz freigeben.
5. Öffentlichen **Überwachungsschlüssel** des Hubs kopieren (`ed25519:…`, im Hub unter Einstellungen → Agentur Hub).

## 2. Einstellungen in Ihrem System

Halten Sie fest (Konfiguration oder Umgebungsvariablen; das Geheimnis nie im Repository):

```
HUB_ISSUER=https://hub.example.org/hub
HUB_CLIENT_ID=…
HUB_CLIENT_SECRET=…
HUB_REDIRECT_URI=https://website.example.org/auth/hub/callback
HUB_STATUS_KEYS=ed25519:…           # mehrere durch Leerzeichen
```

Die Redirect-URI aus einer festen Einstellung bilden – nicht aus dem `Host`-Kopf der Anfrage.

## 3. Login-Knopf und Einstieg

Am einfachsten mit einer gepflegten OpenID-Connect-Bibliothek Ihres Frameworks – sie MUSS die Prüfliste in Abschnitt 7 erfüllen
(vor allem PKCE S256, `nonce`, `iss` in der Antwort und `amr`). Ohne Bibliothek:

```php
// GET /auth/hub/start – Knopf „Mit Agentur Hub anmelden“ und vom Hub ausgelöster Einstieg (?iss=…&login_hint=…)
function hub_start(array $cfg, array $query): never
{
    if (isset($query['iss']) && !hash_equals($cfg['issuer'], rtrim((string) $query['iss'], '/'))) {
        http_response_code(400); exit('Unbekannter Anbieter');
    }
    $disc = hub_discovery($cfg['issuer']);               // geprüft und zwischengespeichert, siehe OIDC-Profil Abschnitt 2
    $b64u = fn(string $b) => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
    $state = $b64u(random_bytes(32));
    $verifier = $b64u(random_bytes(48));
    $_SESSION['hub'][$state] = ['nonce' => $b64u(random_bytes(32)), 'verifier' => $verifier, 'at' => time()];
    $q = [
        'response_type' => 'code', 'client_id' => $cfg['client_id'], 'redirect_uri' => $cfg['redirect_uri'],
        'scope' => 'openid email profile roles', 'state' => $state, 'nonce' => $_SESSION['hub'][$state]['nonce'],
        'code_challenge' => $b64u(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256',
    ];
    if (!empty($query['login_hint'])) $q['login_hint'] = mb_substr((string) $query['login_hint'], 0, 191);
    header('Cache-Control: no-store');
    header('Location: ' . $disc['authorization_endpoint'] . '?' . http_build_query($q, '', '&', PHP_QUERY_RFC3986));
    exit;
}
```

## 4. Callback

```php
// GET /auth/hub/callback?code=…&state=…&iss=…
function hub_callback(array $cfg, array $query): array
{
    $state = (string) ($query['state'] ?? '');
    $pend = $_SESSION['hub'][$state] ?? null;
    unset($_SESSION['hub'][$state]);                                   // einmal verwendbar
    if (strlen($state) < 32 || !$pend || $pend['at'] < time() - 600) throw new RuntimeException('state');
    if (!isset($query['iss']) || !hash_equals($cfg['issuer'], rtrim((string) $query['iss'], '/'))) throw new RuntimeException('iss');
    if (isset($query['error'])) throw new RuntimeException('error');
    $disc = hub_discovery($cfg['issuer']);
    $tok = hub_post($disc['token_endpoint'], [
        'grant_type' => 'authorization_code', 'code' => (string) ($query['code'] ?? ''),
        'redirect_uri' => $cfg['redirect_uri'], 'code_verifier' => $pend['verifier'], 'client_id' => $cfg['client_id'],
    ], 'Basic ' . base64_encode(rawurlencode($cfg['client_id']) . ':' . rawurlencode($cfg['client_secret'])));
    $claims = hub_verify_id_token($cfg, $disc, (string) ($tok['id_token'] ?? ''), $pend['nonce'], (string) ($tok['access_token'] ?? ''));
    // Zweiter Faktor (RFC 8176) – „pwd“ allein reicht nicht
    $strong = ['mfa', 'otp', 'hwk', 'swk', 'sc', 'fpt', 'face', 'iris', 'retina', 'vbm'];
    if (!array_intersect(array_map('strval', (array) ($claims['amr'] ?? [])), $strong)) throw new RuntimeException('amr');
    return $claims;   // weiter mit Abschnitt 5
}
```

`hub_verify_id_token()` prüft Signatur (RS256 über das JWKS, unbekannte `kid` → einmal neu laden), `iss`, `aud`/`azp`,
`exp`/`iat` (60 s Toleranz, `iat` höchstens 10 Minuten alt), `nonce`, `sub` und `at_hash` – Einzelheiten im
[OIDC-Profil](oidc-profil.md#6-id-token). Für die Signaturprüfung genügt `openssl_verify(..., OPENSSL_ALGO_SHA256)` mit dem
aus `n`/`e` gebildeten öffentlichen Schlüssel; eine JWT-Bibliothek ist nur dann geeignet, wenn sie das Verfahren fest auf
`RS256` setzt (nie aus dem Token übernimmt).

## 5. Konto zuordnen

```text
identität = SELECT … FROM hub_identities WHERE issuer = :iss AND subject = :sub     -- eindeutiger Index (issuer, subject)
wenn identität:
    konto = Konto der Identität; gesperrt → ablehnen
sonst:
    wenn ein Konto mit derselben E-Mail existiert → ablehnen (nie übernehmen)
    wenn Anlage bei erster Anmeldung aus → ablehnen
    konto = neues Konto ohne lokales Passwort, gekennzeichnet als Agentur-Konto
    INSERT hub_identities (konto, issuer, subject)
rolle = roles enthält "admin" → Administration; "redaktion" → Redaktionsrolle; sonst ablehnen
Rechte des Kontos auf die Rolle setzen (bei jeder Anmeldung), Name/E-Mail aktualisieren (E-Mail nur, wenn email_verified)
Sitzung neu erzeugen (session_regenerate_id), Ziel nur ein relativer Pfad der eigenen Website
```

## 6. Status-Endpunkt (`status-v1`)

Vollständige Prüfung mit libsodium – ohne weitere Abhängigkeiten. `$seen` merkt Nonces (Datenbank, Cache, Datei).

```php
<?php
// SPDX-License-Identifier: MIT
// status-v1: signierte Status-Abfrage prüfen (Spezifikation docs/agentur-hub/monitoring-status-v1.md)

function status_b64u_decode(string $s): string|false
{
    if ($s === '' || !preg_match('~^[A-Za-z0-9_-]+$~', $s)) return false;
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
}

function status_key_id(string $public32): string
{
    return substr(rtrim(strtr(base64_encode(hash('sha256', $public32, true)), '+/', '-_'), '='), 0, 16);
}

/** „ed25519:…“ → 32 Byte oder null */
function status_parse_key(string $text): ?string
{
    if (!preg_match('~(?:^|\s)ed25519:([A-Za-z0-9_-]{43})$~', trim($text), $m)) return null;
    $raw = status_b64u_decode($m[1]);
    return $raw !== false && strlen($raw) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ? $raw : null;
}

/**
 * @param array<string,string> $server  $_SERVER
 * @param list<string> $keys            öffentliche Schlüssel als „ed25519:…“
 * @param callable(string $kid, string $nonce): bool $seen  true = schon verwendet (merkt sich den Nonce sonst, ≥ 660 s)
 * @return string|null  Fehlergrund (nur fürs eigene Protokoll) oder null = gültig
 */
function status_verify(array $server, array $keys, callable $seen, ?int $now = null): ?string
{
    $now ??= time();
    $kid = (string) ($server['HTTP_X_STATUS_KEY'] ?? '');
    $ts = (string) ($server['HTTP_X_STATUS_TIMESTAMP'] ?? '');
    $nonce = (string) ($server['HTTP_X_STATUS_NONCE'] ?? '');
    $sig = status_b64u_decode((string) ($server['HTTP_X_STATUS_SIGNATURE'] ?? ''));
    if ($kid === '' || !preg_match('~^\d{9,11}$~', $ts) || !preg_match('~^[A-Za-z0-9_-]{16,64}$~', $nonce)
        || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) return 'format';
    if (abs($now - (int) $ts) > 300) return 'time';
    $pub = null;
    foreach ($keys as $k) {
        $raw = status_parse_key($k);
        if ($raw !== null && hash_equals(status_key_id($raw), $kid)) { $pub = $raw; break; }
    }
    if ($pub === null) return 'key';
    // Host wie vom Client gesendet: Kleinbuchstaben, Standard-Port entfernen
    $https = ($server['HTTPS'] ?? '') !== '' && ($server['HTTPS'] ?? '') !== 'off';
    $host = strtolower((string) ($server['HTTP_HOST'] ?? ''));
    $host = preg_replace($https ? '~:443$~' : '~:80$~', '', $host);
    $base = implode("\n", ['status-v1', strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET')), $host,
        (string) ($server['REQUEST_URI'] ?? '/'), $ts, $nonce]);
    if (!sodium_crypto_sign_verify_detached($sig, $base, $pub)) return 'signature';
    if ($seen($kid, $nonce)) return 'replay';   // erst nach gültiger Signatur merken
    return null;
}

// --- Endpunkt ---------------------------------------------------------------------------------------------------------
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$keys = array_filter(explode(' ', (string) getenv('HUB_STATUS_KEYS')));
if (!$keys) { http_response_code(404); exit('{"error":"not_found"}'); }
// Rate-Limit je IP hier einfügen (z. B. 30 Anfragen in 10 Minuten) → 429 {"error":"rate_limited"}
$err = status_verify($_SERVER, $keys, fn(string $kid, string $nonce): bool => my_nonce_seen_or_store("$kid:$nonce", 660));
if ($err !== null) {
    error_log("status-v1 denied: $err");
    http_response_code(401);
    exit('{"error":"unauthorized"}');
}
echo json_encode([
    'protocol' => 'status-v1',
    'system' => 'laravel',
    'system_version' => '11.0.0',
    'php' => PHP_VERSION,
    'generated_at' => date('c'),
    'health' => ['status' => 'green', 'errors' => 0, 'warnings' => 0, 'notices' => 0, 'messages' => []],
    'backup' => ['last_at' => my_last_backup_iso(), 'offsite_at' => null],
    'nonce' => (string) $_SERVER['HTTP_X_STATUS_NONCE'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
```

`my_nonce_seen_or_store()` und `my_last_backup_iso()` sind Platzhalter für Ihre Ablage. Hinter einem Proxy setzen Sie
`HTTP_HOST` und `HTTPS` vorher aus den Kopfzeilen vertrauenswürdiger Proxys (z. B. `X-Forwarded-Host`, `X-Forwarded-Proto`).

### Selbst testen

Ein Testschlüsselpaar erzeugen und eine Anfrage signieren (nur zum Testen – im Betrieb signiert der Hub):

```php
<?php
// SPDX-License-Identifier: MIT
$pair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($pair);
$public = sodium_crypto_sign_publickey($pair);
$b64u = fn(string $b) => rtrim(strtr(base64_encode($b), '+/', '-_'), '=');
echo 'Öffentlicher Schlüssel: ed25519:' . $b64u($public), "\n";

$url = 'https://website.example.org/.well-known/status';
$p = parse_url($url);
$ts = time();
$nonce = $b64u(random_bytes(18));
$base = implode("\n", ['status-v1', 'GET', strtolower($p['host']), $p['path'] . (isset($p['query']) ? '?' . $p['query'] : ''), $ts, $nonce]);
$kid = substr($b64u(hash('sha256', $public, true)), 0, 16);
printf("curl -s %s -H 'X-Status-Key: %s' -H 'X-Status-Timestamp: %d' -H 'X-Status-Nonce: %s' -H 'X-Status-Signature: %s'\n",
    $url, $kid, $ts, $nonce, $b64u(sodium_crypto_sign_detached($base, $secret)));
```

Erwartet: beim ersten Aufruf `200` mit `nonce`-Echo, beim zweiten Aufruf desselben Befehls `401` (Wiederholung).

## 7. Prüfliste Sicherheit

Anmeldung:

- [ ] Standard **aus**; erst nach bewusstem Einschalten erscheint der Knopf.
- [ ] Discovery: `issuer` exakt, Endpunkte nur `https://`, `S256` angeboten; Abrufe mit Zeit- und Größenlimit, keine internen Adressen (SSRF).
- [ ] PKCE S256, `state` (≥ 32 Byte, Sitzung, einmal, 10 Minuten), `nonce`.
- [ ] `iss` in der Rückkehr geprüft (RFC 9207); vom Hub ausgelöster Einstieg nur mit passendem `iss`.
- [ ] ID-Token: nur RS256 (bzw. fest gewählte asymmetrische Verfahren), `none`/HS*/`crit` abgewiesen, `kid`-Wechsel mit Begrenzung, `iss`, `aud`/`azp`, `exp`/`iat`, `nonce`, `sub`, `at_hash`.
- [ ] Zweiter Faktor über `amr`.
- [ ] Zuordnung nur über `iss` + `sub`; vorhandene Konten nie übernehmen; keine lokalen Passwörter für Agentur-Konten.
- [ ] Rollen nur aus bekannten Werten von `roles`, bei jeder Anmeldung abgeglichen; ohne Rolle keine Anmeldung.
- [ ] Redirect-URI aus fester Einstellung, exakt wie im Hub registriert; Ziel nach der Anmeldung nur relative Pfade.
- [ ] Sitzungs-ID nach der Anmeldung neu, Antworten `Cache-Control: no-store`, `Referrer-Policy: no-referrer`.
- [ ] Client-Geheimnis verschlüsselt oder aus der Umgebung; nie im Repository, nie in Protokollen.
- [ ] Rate-Limit und Protokoll fehlgeschlagener Anmeldungen.
- [ ] Lokaler Notfall-Admin mit Passwort und zweitem Faktor bleibt bestehen.

Überwachung:

- [ ] Standard **aus** (`404`), Schlüssel nur öffentlich gespeichert, mehrere möglich.
- [ ] Zeitfenster ±300 s, Signatur über Host + Pfad mit Query, Nonce erst nach gültiger Signatur gemerkt (≥ 600 s, empfohlen 660 s).
- [ ] Fehlerantworten ohne Grund; Grund nur im eigenen Protokoll; Rate-Limit je IP.
- [ ] Antwort ohne Inhalte, Konten, Personendaten, Geheimnisse, Pfade; Fehlerprotokoll nur als Zahlen.
- [ ] `nonce`-Echo in jeder Antwort, `Cache-Control: no-store`, `X-Robots-Tag: noindex`.

---

## English summary

How to connect your own system (Laravel, Symfony, WordPress …) to an Agentur Hub: register the instance in the hub (redirect
URI, initiate-login URI, post-logout URI, status URL; the client secret is shown once), grant people the role `admin` or
`redaktion`, copy the hub's monitoring key `ed25519:…`. Implement the login with a maintained OIDC library or the sketched code:
PKCE S256, `state`, `nonce`, check `iss` on return (RFC 9207), validate the RS256 ID token, require a second factor via `amr`, map
accounts only by `iss` + `sub`, never take over existing accounts, map `roles` on every login, keep a local emergency admin. The
MIT-licensed `status_verify()` function implements the `status-v1` signature check with libsodium (format, ±300 s window, key id,
Ed25519 signature over host + path with query, nonce stored only after a valid signature). A test script creates a key pair and
prints a signed `curl` call. Finish with the security checklist.
