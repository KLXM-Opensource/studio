# Selbstauskunft einer Instanz – `klxm-instance-v1`

Öffentliche, unkritische Auskunft einer Installation, damit ein Agentur Hub (OpenID-Connect-Anbieter und Überwachung) die
richtigen Adressen selbst ermitteln kann, statt sie abzutippen. Umsetzung in KLXM Studio: `Core\InstanceInfo`.
Andere Systeme (REDAXO-AddOn, eigene Lösungen) dürfen dieselbe Auskunft unter derselben Adresse liefern.

Die Auskunft ist **optional**: Ohne sie trägt man die Adressen im Hub von Hand ein. Übersicht aller Protokolle:
[README.md](README.md).

Die Schlüsselwörter MUSS, DARF NICHT, SOLL und KANN sind wie in RFC 2119 zu lesen.

## Abruf

```
GET /.well-known/klxm-studio
Accept: application/json
```

- Liegt an der Wurzel des Hosts (nicht unter einem Unterpfad). In KLXM Studio auf **jedem Host** der Installation abrufbar,
  das Ergebnis ist überall gleich.
- **404**, solange nichts eingerichtet ist. KLXM Studio: weder OpenID Connect (auf der Netzwerk-Website Issuer oder Client-ID
  eingetragen) noch die Überwachung (auf irgendeiner Website eingeschaltet, mit Schlüssel).
- Keine Anmeldung, kein Staging-Passwort. KLXM Studio sendet `Access-Control-Allow-Origin: *` und
  `Cache-Control: public, max-age=300` und hält das Ergebnis serverseitig 60 Sekunden vor (verworfen beim Speichern von
  OIDC, Überwachung und Netzwerk-Domain).
- Die Auskunft DARF NICHT enthalten: Versionen, Personendaten, Inhalte, Schlüssel oder Geheimnisse, Pfade des Servers.

## Antwort

```json
{
  "spec": "klxm-instance-v1",
  "system": "studio",
  "name": "Lernen Musterstadt",
  "network": true,
  "login": {
    "redirect_uri": "https://website.example.org/admin/login/oidc/callback",
    "initiate_login_uri": "https://website.example.org/admin/login/oidc",
    "post_logout_redirect_uri": "https://website.example.org/admin/login",
    "configured": true
  },
  "monitoring": {
    "sites": [
      { "key": "default", "name": "Lernen Musterstadt", "url": "https://www.website.example.org",
        "status_url": "https://www.website.example.org/api/monitoring/status", "network": true }
    ]
  }
}
```

| Feld | Pflicht | Bedeutung |
|---|---|---|
| `spec` | ja | immer `klxm-instance-v1` – andere Werte nicht auswerten |
| `system` | ja | Kennung des Systems: `studio` (KLXM Studio), `redaxo6`, `redaxo5`; eigene Systeme eine eigene Kennung in Kleinbuchstaben (z. B. `laravel`) |
| `name` | nein | Anzeigename der Instanz (KLXM Studio: Name der Netzwerk-Website bzw. der einzigen Website) |
| `network` | nein | `true`, wenn die Installation mehrere Websites hat |
| `login.redirect_uri` | * | Rücksprung-Adresse für den Code-Flow – genau so beim Anbieter zu registrieren |
| `login.initiate_login_uri` | nein | Einstieg für eine vom Anbieter ausgelöste Anmeldung (OIDC Core, Abschnitt 4) |
| `login.post_logout_redirect_uri` | nein | Ziel nach dem Abmelden beim Anbieter |
| `login.configured` | nein | KLXM Studio: OIDC ist eingetragen |
| `monitoring.status_url` | * | Status-Adresse (Protokoll [`status-v1`](monitoring-status-v1.md)) – bei einer Installation mit einer Website |
| `monitoring.sites[]` | * | Websites mit eingeschalteter Überwachung: `key`, `name`, `url` (Hauptadresse), `status_url`, `network` (Netzwerk-Website, steht zuerst) |
| `icon`, `preview` | nein | vollständige Adressen eines Favicons bzw. Vorschaubilds für die Karte im Hub |

\* Mindestens `login.redirect_uri` oder eine Status-Adresse MUSS enthalten sein, sonst verwirft der Hub die Auskunft.
`monitoring` darf ein Objekt mit `status_url`, ein Objekt mit `sites` oder direkt eine Liste solcher Einträge sein.

KLXM Studio liefert bei einer Installation mit einer Website `status_url` **und** `sites`, im Netzwerk nur `sites`. Die
`login.*`-Adressen gehören zur Netzwerk-Website – mit der **Netzwerk-Domain** (`network_host`), falls gesetzt, und mit
eigener Verwaltungsadresse (`admin_path`). Die Status-Adressen liegen immer auf der **Hauptadresse** der jeweiligen Website
(die Signatur von `status-v1` enthält den Host), nie auf der Netzwerk-Domain.

Alle Adressen MÜSSEN vollständig sein und mit `https://` beginnen (der Hub übernimmt `http://` nur in einer
Entwicklungsumgebung); Adressen mit `#` werden ignoriert.

## Für den Hub

- Basis-Adresse der Instanz + `/.well-known/klxm-studio` abrufen (höchstens 64 KB, 8 Sekunden); 404 = nichts eingerichtet →
  Adressen von Hand.
- Adressen erst nach Bestätigung übernehmen; die `redirect_uri` muss danach exakt übereinstimmen.
- Mehrere Websites: der Eintrag, dessen Host zur eingetragenen Basis-Adresse passt, sonst der erste.
- Netzwerk: die Instanz meldet sich über die Netzwerk-Website an (eine `redirect_uri` je Installation); die übrigen Websites
  erreicht man per Einmal-Anmeldung.
- Der Agentur Hub sieht höchstens alle 6 Stunden nach und zeigt Abweichungen an – er ändert gespeicherte Adressen nie von selbst.
- `icon`/`preview` lädt der Hub nur vom Host der Website (oder seiner www-Variante): PNG, JPEG oder WebP bis 512 KB, SVG wird
  bereinigt.
- Neue Felder können hinzukommen; unbekannte Felder ignorieren.

---

## English summary

`GET /.well-known/klxm-studio` returns public, non-sensitive JSON (`spec: "klxm-instance-v1"`) so that a hub can discover an
instance's addresses: `system` (`studio`, `redaxo6`, `redaxo5` or an own lowercase id), `name`, `network`, `login`
(`redirect_uri`, `initiate_login_uri`, `post_logout_redirect_uri`, `configured`) and `monitoring` (`status_url` and/or
`sites[]` with `key`, `name`, `url`, `status_url`, `network`), optionally `icon`/`preview`. At least a `redirect_uri` or a status
URL is required. 404 while nothing is set up. No versions, personal data, content, keys or server paths. KLXM Studio answers on
every host of the installation with `Access-Control-Allow-Origin: *` and `Cache-Control: public, max-age=300`. The hub only
shows discovered addresses; a person confirms and saves them, after which the `redirect_uri` must match exactly. Ignore unknown
fields.
