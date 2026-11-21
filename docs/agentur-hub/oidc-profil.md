# OIDC-Profil des Agentur Hubs

Welches OpenID-Connect-Profil der Agentur Hub als Anbieter (OpenID Provider) liefert und was eine angebundene Instanz
(Client, Relying Party) prüfen muss. Es ist ein schmales, strenges Profil von
[OpenID Connect Core 1.0](https://openid.net/specs/openid-connect-core-1_0.html) – jeder Standard-Client, der es erfüllt,
kann sich anbinden. Umgekehrt kann eine KLXM-Studio-Instanz jeden Anbieter nutzen, der dieses Profil liefert.

Umsetzung des Clients in KLXM Studio: `Core\Oidc` (Entwicklerhandbuch → Sicherheit → Anmeldung per OpenID Connect).
Übersicht: [README.md](README.md). Die Schlüsselwörter MUSS, DARF NICHT, SOLL und KANN sind wie in RFC 2119 zu lesen.

## 1. Überblick

| Punkt | Profil |
|---|---|
| Ablauf | nur **Authorization Code Flow** (`response_type=code`, `response_mode=query`) |
| PKCE | **Pflicht**, nur `S256` (RFC 7636) |
| `state`, `nonce` | Client MUSS beide senden und prüfen |
| `iss` in der Antwort | Anbieter sendet ihn (RFC 9207), Client prüft ihn |
| Redirect-URI | **exakter** Vergleich, je Instanz registriert |
| Client-Authentifizierung | `client_secret_basic` (empfohlen), `client_secret_post`; der Hub kann zusätzlich `private_key_jwt` |
| ID-Token | JWS **RS256** (der Hub signiert nur RS256; KLXM Studio prüft auch EdDSA) |
| Zweiter Faktor | belegt über `amr` (RFC 8176) |
| Konten | Zuordnung über `iss` + `sub`, nie über die E-Mail-Adresse |
| Abmelden | RP-initiated Logout 1.0 (optional) |
| nicht unterstützt | Implicit/Hybrid Flow, `request`/`request_uri`, `claims`-Parameter, Refresh-Token, dynamische Registrierung |

## 2. Discovery

`GET {issuer}/.well-known/openid-configuration`. Der Issuer des Agentur Hubs hat die Form `https://hub.example.org/hub` und
DARF sich nach der Einrichtung nicht mehr ändern.

```json
{
  "issuer": "https://hub.example.org/hub",
  "authorization_endpoint": "https://hub.example.org/hub/authorize",
  "token_endpoint": "https://hub.example.org/hub/token",
  "userinfo_endpoint": "https://hub.example.org/hub/userinfo",
  "jwks_uri": "https://hub.example.org/hub/jwks",
  "end_session_endpoint": "https://hub.example.org/hub/logout",
  "logo_uri": "https://hub.example.org/assets/hub-logo.svg",
  "response_types_supported": ["code"],
  "response_modes_supported": ["query"],
  "grant_types_supported": ["authorization_code"],
  "subject_types_supported": ["public"],
  "id_token_signing_alg_values_supported": ["RS256"],
  "token_endpoint_auth_methods_supported": ["client_secret_basic", "client_secret_post", "private_key_jwt"],
  "token_endpoint_auth_signing_alg_values_supported": ["RS256", "EdDSA"],
  "code_challenge_methods_supported": ["S256"],
  "scopes_supported": ["openid", "email", "profile", "roles"],
  "claims_supported": ["sub", "iss", "aud", "exp", "iat", "auth_time", "nonce", "amr", "azp", "at_hash",
                       "email", "email_verified", "name", "roles"],
  "authorization_response_iss_parameter_supported": true,
  "request_parameter_supported": false,
  "request_uri_parameter_supported": false,
  "claims_parameter_supported": false
}
```

Der Client MUSS prüfen:

- `issuer` stimmt **exakt** mit dem eingestellten Issuer überein (ohne abschließenden Schrägstrich).
- `authorization_endpoint`, `token_endpoint` und `jwks_uri` sind vorhanden und beginnen mit `https://` (`http://` nur in
  einer Entwicklungsumgebung).
- Nennt `code_challenge_methods_supported` Verfahren, MUSS `S256` darunter sein.

Discovery und JWKS SOLLEN zwischengespeichert werden (KLXM Studio: 1 Stunde; der Hub sendet `Cache-Control: public,
max-age=300` und `Access-Control-Allow-Origin: *`). Abrufe SOLLEN gegen SSRF geschützt sein (keine internen Adressen,
begrenzte Größe und Zeit).

### Optionales Feld `logo_uri`

`logo_uri` ist **nicht** Teil des OIDC-Standards. Der Agentur Hub nennt darin ein Logo (SVG, mit `currentColor` gezeichnet)
für den Anmelde-Link der Instanzen. Ein Client KANN es nutzen, MUSS es dann aber selbst ablegen und lokal ausliefern – nie
direkt vom Anbieter einbinden (Datenschutz, CSP). KLXM Studio lädt es nur über https, SVG oder PNG bis 64 KB, bereinigt SVG,
prüft PNG (Signatur, höchstens 512 px) und legt es in `storage/oidc-logos/` ab. Fehler beim Logo DÜRFEN die Anmeldung nie
stören.

## 3. Anmeldung beginnen

Der Client erzeugt je Anmeldung:

- `state`: mindestens 32 Byte Zufall (base64url), an die Sitzung gebunden, **einmal** verwendbar, höchstens 10 Minuten gültig;
- `nonce`: mindestens 32 Byte Zufall;
- `code_verifier`: 43–128 Zeichen aus `[A-Za-z0-9._~-]`; `code_challenge = base64url(SHA-256(code_verifier))`.

```
GET https://hub.example.org/hub/authorize
  ?response_type=code
  &client_id=…
  &redirect_uri=https%3A%2F%2Fwebsite.example.org%2Fadmin%2Flogin%2Foidc%2Fcallback
  &scope=openid%20email%20profile
  &state=…&nonce=…
  &code_challenge=…&code_challenge_method=S256
  [&login_hint=person%40example.org]
```

Der Hub antwortet bei unbekanntem `client_id` oder nicht registrierter `redirect_uri` mit einer Fehlerseite und leitet **nie**
weiter. Weitere Fehler gehen als `error` an die Redirect-URI: `unsupported_response_type`, `request_not_supported`,
`invalid_scope` (ohne `openid`), `invalid_request` (PKCE fehlt, anderes `response_mode`), `login_required` (`prompt=none`
ohne Hub-Sitzung), `access_denied` (keine Freigabe für diese Instanz oder kein Recht, den Hub zu nutzen). Ohne gültige
Hub-Sitzung mit zweitem Faktor meldet sich die Person zuerst am Hub an; danach geht die Anfrage weiter (10 Minuten).

**Vom Anbieter ausgelöste Anmeldung** („Öffnen“ im Hub, `initiate_login_uri`, OIDC Core Abschnitt 4): Der Hub ruft den
Einstieg der Instanz mit `?iss=<issuer>` und optional `login_hint` auf. Der Client MUSS `iss` exakt mit dem eingestellten
Issuer vergleichen und sonst abbrechen; danach beginnt er eine normale Anmeldung wie oben. KLXM Studio wertet `target_link_uri`
nicht aus.

## 4. Rückkehr (Callback)

```
GET {redirect_uri}?code=…&state=…&iss=https%3A%2F%2Fhub.example.org%2Fhub
```

Der Client MUSS in dieser Reihenfolge prüfen:

1. `state` gehört zu einer offenen Anmeldung dieser Sitzung – danach sofort verbrauchen.
2. `iss` (RFC 9207) stimmt exakt mit dem Issuer überein. Kündigt die Discovery
   `authorization_response_iss_parameter_supported: true` an, SOLL der Client einen fehlenden `iss` als Fehler werten.
3. `error` vorhanden → abbrechen (nur den Fehlercode anzeigen, nie `error_description` ungeprüft ausgeben).
4. Code am Token-Endpunkt einlösen.

## 5. Token-Endpunkt

```
POST https://hub.example.org/hub/token
Authorization: Basic base64(urlencode(client_id) ":" urlencode(client_secret))
Content-Type: application/x-www-form-urlencoded

grant_type=authorization_code&code=…&redirect_uri=…&code_verifier=…&client_id=…
```

- `redirect_uri` MUSS dieselbe sein wie beim Beginn.
- Codes sind 60 Sekunden gültig und **einmal** verwendbar. Ein zweites Einlösen entwertet die damit ausgestellten Tokens.
- Antwort: `{"access_token", "token_type": "Bearer", "expires_in": 300, "scope", "id_token"}` – kein Refresh-Token.
- Fehler als JSON mit `error`: `invalid_client` (HTTP 401), `invalid_grant`, `unsupported_grant_type`, `slow_down` (429).

## 6. ID-Token

### Signatur

- Der Hub signiert mit **RS256** (RSA 3072 Bit), Kopf mit `kid`. Bei einem Schlüsselwechsel bleibt der alte Schlüssel 2 Tage
  im JWKS.
- Der Client MUSS abweisen: `alg: none`, alle HS*-Verfahren (ein öffentlicher Schlüssel würde sonst als HMAC-Geheimnis
  missbraucht), Köpfe mit `crit`, RSA-Schlüssel unter 2048 Bit. Er DARF NICHT `jku`, `x5u` oder `jwk` aus dem Kopf verwenden.
- Unbekannte `kid` → JWKS **einmal** neu laden (höchstens alle 60 Sekunden), sonst abweisen. Ohne `kid` nur, wenn genau ein
  passender Schlüssel existiert.

### Claims

| Claim | Inhalt beim Agentur Hub | Prüfung durch den Client |
|---|---|---|
| `iss` | Issuer | MUSS exakt passen |
| `aud` | `client_id` der Instanz | MUSS die eigene `client_id` enthalten |
| `azp` | `client_id` | bei mehreren `aud` oder vorhandenem `azp` MUSS er der eigenen `client_id` entsprechen |
| `exp` | `iat` + 300 s | nicht abgelaufen (Toleranz 60 s) |
| `iat` | Ausstellung | nicht in der Zukunft (60 s), nicht älter als 10 Minuten |
| `nbf` | – | falls vorhanden: nicht in der Zukunft (60 s) |
| `nonce` | aus der Anfrage | MUSS exakt der gesendeten `nonce` entsprechen |
| `sub` | dauerhafte, undurchsichtige Kennung der Person beim Hub (z. B. `h3QeR…`) | MUSS vorhanden sein (höchstens 255 Zeichen) |
| `at_hash` | linke Hälfte von SHA-256 des Access-Tokens | falls vorhanden: MUSS passen |
| `auth_time` | Zeitpunkt der Anmeldung am Hub | KANN ausgewertet werden |
| `amr` | Verfahren der Anmeldung am Hub, z. B. `["pwd","otp"]` oder `["hwk"]` | siehe Abschnitt 7 |
| `email` | E-Mail-Adresse (Kleinbuchstaben), mit Scope `email` | Pflicht für KLXM Studio |
| `email_verified` | `true` | falls vorhanden und nicht `true`: Adresse nicht verwenden bzw. abweisen |
| `name` | Anzeigename, mit Scope `profile` | KANN übernommen werden |
| `roles` | `["admin"]` oder `["redaktion"]` je Freigabe | siehe Abschnitt 7 |

Der Hub liefert `roles` **immer** mit, auch ohne Scope `roles`. Andere Anbieter liefern einen Rollen-Claim oft nur mit
eigenem Scope oder Mapper – dann dort entsprechend einrichten.

### Userinfo

`GET|POST {userinfo_endpoint}` mit `Authorization: Bearer <access_token>` liefert `sub`, `email`, `email_verified`, `name`,
`roles` nach Scope. KLXM Studio braucht ihn nicht – alles steht im ID-Token. Nach Entzug der Freigabe weist der Hub
Userinfo-Anfragen ab.

## 7. Sicherheitsanforderungen an einen Client

- **Konto-Zuordnung nur über `iss` + `sub`** (eigene Tabelle, eindeutiger Index). Die E-Mail-Adresse ist veränderlich und
  DARF NICHT allein zur Zuordnung dienen.
- **Bestehende lokale Konten nie übernehmen.** Gibt es schon ein Konto mit derselben E-Mail-Adresse und eigenem Passwort,
  wird die Anmeldung abgelehnt. Verknüpfen per E-Mail nur bei einem vorab ausdrücklich freigegebenen Platzhalter-Konto
  desselben Anbieters und nur mit `email_verified: true` (KLXM Studio: `php bin/console oidc:allow <email>`).
- **Zweiter Faktor über `amr`:** `amr` MUSS mindestens einen dieser Werte enthalten: `mfa`, `otp`, `hwk`, `swk`, `sc`, `fpt`,
  `face`, `iris`, `retina`, `vbm`. `pwd` allein reicht nicht. Nur wenn der Anbieter den zweiten Faktor nachweislich selbst
  erzwingt, darf der Client darauf verzichten (KLXM Studio: Einstellung „der Anbieter erzwingt ihn selbst“). Der Agentur Hub lässt
  ohnehin nur Sitzungen mit zweitem Faktor oder Passkey zu.
- **Rollen:** Wertet der Client `roles` aus, dann nur bekannte Werte (`admin`, `redaktion`) auf eigene Rollen abbilden; ohne
  passende Rolle keine Anmeldung. Rechte bei jeder Anmeldung neu abgleichen. Ein Client, der `roles` nicht auswertet, gibt jeder
  Freigabe – auch `redaktion` – dieselbe Rolle; das ist nur vertretbar, wenn er ausschließlich Administration vergibt und der
  Hub nur `admin`-Freigaben erteilt. KLXM Studio wertet `roles` aus (Einstellung „Rolle aus dem Claim“, `Accounts::roleFor`):
  `admin` → eingestellte Rolle, `redaktion` → Redaktionsrolle, sonst abgewiesen.
- **Exakte `redirect_uri`:** Die Adresse, die der Client sendet, MUSS Zeichen für Zeichen der beim Hub registrierten
  entsprechen (Schema, Host, Port, Pfad, Query). Sie SOLL aus einer fest eingestellten Basis-Adresse entstehen, nicht aus dem
  `Host`-Kopf der Anfrage.
- Konten des Anbieters haben **kein lokales Passwort** (kein „Passwort vergessen“), sind als Agentur-Konto gekennzeichnet und
  lokal sperrbar; Sperren beendet laufende Sitzungen.
- **Notfall-Zugang:** mindestens ein lokales Administrationskonto mit Passwort und Passkey behalten.
- Ziel nach der Anmeldung nur relative Pfade der eigenen Website (keine offene Weiterleitung).
- Rate-Limit je IP (KLXM Studio: 20 Anmeldungen in 10 Minuten), fehlgeschlagene Anmeldungen protokollieren (z. B. für fail2ban),
  Antworten mit `Cache-Control: no-store` und `Referrer-Policy: no-referrer`.
- Client-Geheimnis nur verschlüsselt speichern oder aus einer Umgebungsvariable lesen; nie ausgeben.
- Standard **aus**: Die Anmeldung über den Anbieter erscheint erst, wenn sie bewusst eingeschaltet wurde.

## 8. Abmelden (optional)

RP-initiated Logout 1.0:

```
GET https://hub.example.org/hub/logout
  ?id_token_hint=<ID-Token>&post_logout_redirect_uri=https%3A%2F%2Fwebsite.example.org%2Fadmin%2Flogin&client_id=…
```

- `post_logout_redirect_uri` MUSS beim Hub für die Instanz registriert sein, sonst bleibt der Hub auf seiner Seite.
- Mit gültigem `id_token_hint` (Signatur des Hubs, Ablauf egal) meldet der Hub sofort ab, sonst fragt er nach. `state` wird
  zurückgegeben, falls gesendet.
- Back-Channel- und Front-Channel-Logout gibt es nicht: Entzieht der Hub eine Freigabe, entstehen keine neuen Anmeldungen;
  laufende Sitzungen in der Instanz enden erst mit deren Ablauf oder einer Sperre dort.

## 9. Was im Hub je Instanz registriert wird

| Angabe | Beispiel (KLXM Studio) |
|---|---|
| Redirect-URI(s) | `https://website.example.org/admin/login/oidc/callback` |
| Einstieg (`initiate_login_uri`) | `https://website.example.org/admin/login/oidc` |
| Nach dem Abmelden | `https://website.example.org/admin/login` |
| Client-Authentifizierung | `client_secret_basic` |
| Freigaben | Personen mit Rolle `admin` oder `redaktion` |

Client-ID und Client-Geheimnis zeigt der Hub beim Registrieren **einmal** an; er speichert das Geheimnis nur als HMAC.
Die Adressen kann der Hub aus der [Selbstauskunft](instance-v1.md) übernehmen.

---

## English summary

The Agentur Hub is a strict OpenID Connect provider: authorization code flow only, PKCE `S256` mandatory, exact redirect URIs per
instance, `iss` in the authorization response (RFC 9207), client authentication `client_secret_basic`/`client_secret_post`
(`private_key_jwt` also accepted), ID tokens signed with RS256 (5 minutes, codes 60 seconds and single-use, no refresh tokens).
Claims: `sub` (opaque, stable), `email`, `email_verified`, `name`, `amr`, `auth_time`, `at_hash` and `roles` (`admin` or `redaktion`,
always included). The discovery document may contain the non-standard `logo_uri`; clients must store and serve it locally.
A client must validate `state`, `nonce`, `iss`, `aud`/`azp`, `exp`/`iat` (60 s leeway, `iat` at most 10 minutes old), `at_hash`,
reject `none`, HS* and `crit`, reload the JWKS once on an unknown `kid`, map accounts only by `iss` + `sub` (never by e-mail alone),
never take over existing local accounts, require a second factor via `amr` (`mfa`, `otp`, `hwk`, `swk`, …), keep a local emergency
admin and keep the feature off by default. Optional RP-initiated logout with `id_token_hint` and a registered
`post_logout_redirect_uri`.
