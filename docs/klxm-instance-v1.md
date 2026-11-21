# Selbstauskunft einer Instanz – `klxm-instance-v1`

Öffentliche, unkritische Auskunft einer KLXM-Studio-Installation, damit ein Agentur-Hub (OpenID-Connect-Anbieter und
Überwachung) die richtigen Adressen selbst ermitteln kann, statt sie abzutippen. Umsetzung: `Core\InstanceInfo`.

## Abruf

```
GET /.well-known/klxm-studio
Accept: application/json
```

- Auf **jedem Host** der Installation abrufbar, das Ergebnis ist überall gleich.
- **404**, solange weder OpenID Connect (auf der Netzwerk-Website: Issuer oder Client-ID eingetragen) noch die Überwachung
  (auf irgendeiner Website eingeschaltet, mit Schlüssel) eingerichtet ist.
- Keine Anmeldung, kein Staging-Passwort, `Access-Control-Allow-Origin: *`, `Cache-Control: public, max-age=300`
  (serverseitig 60 s zwischengespeichert).
- Enthält **keine** Versionen, Personendaten, Inhalte oder Schlüssel.

## Antwort

```json
{
  "spec": "klxm-instance-v1",
  "system": "studio",
  "name": "Lernen Musterstadt",
  "network": true,
  "login": {
    "redirect_uri": "https://kunde.agentur.example/admin/login/oidc/callback",
    "initiate_login_uri": "https://kunde.agentur.example/admin/login/oidc",
    "post_logout_redirect_uri": "https://kunde.agentur.example/admin/login",
    "configured": true
  },
  "monitoring": {
    "sites": [
      { "key": "default", "name": "Lernen Musterstadt", "url": "https://www.kunde.example",
        "status_url": "https://www.kunde.example/api/monitoring/status", "network": true }
    ]
  }
}
```

| Feld | Bedeutung |
|---|---|
| `spec` | immer `klxm-instance-v1` – andere Werte nicht auswerten |
| `system` | `studio` |
| `name` | Name der Netzwerk-Website (bzw. der einzigen Website) |
| `network` | `true`, wenn die Installation mehrere Websites hat |
| `login.*` | Adressen der Netzwerk-Website für den Anbieter – mit der **Netzwerk-Domain** (`network_host`), falls gesetzt, und mit eigener Verwaltungsadresse (`admin_path`). `configured`: OIDC ist eingetragen. |
| `monitoring.status_url` | nur Einzel-Installation: Status-Adresse (Protokoll `status-v1`) |
| `monitoring.sites[]` | Websites mit eingeschalteter Überwachung: `key`, `name`, `url` (Hauptadresse), `status_url`, `network` (Netzwerk-Website, steht zuerst) |

Die Status-Adressen liegen immer auf der **Hauptadresse** der jeweiligen Website (die Signatur von `status-v1` enthält den
Host), nie auf der Netzwerk-Domain.

## Für den Hub

- Basis-Adresse der Instanz + `/.well-known/klxm-studio` abrufen; 404 = nichts eingerichtet → Adressen von Hand.
- Adressen erst nach Bestätigung übernehmen; die `redirect_uri` muss danach exakt übereinstimmen.
- Netzwerk: die Instanz meldet sich über die Netzwerk-Website an (eine `redirect_uri` je Installation); die übrigen Websites
  erreicht man per Einmal-Anmeldung.
- Neue Felder können hinzukommen; unbekannte Felder ignorieren.
