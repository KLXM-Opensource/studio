# Agentur Hub – Anbindung und Protokolle

Der **Agentur Hub** ist eine Erweiterung für KLXM Studio (`agentur`), die auf einer Website einer Studio-Installation läuft.
Er ist zweierlei:

- **OpenID-Connect-Anbieter:** Mitarbeitende einer Agentur melden sich einmal am Hub an (Pflicht: zweiter Faktor oder Passkey)
  und von dort per Einmal-Anmeldung an allen Websites, für die sie freigegeben sind.
- **Überwachung:** Der Hub fragt die Websites alle 15 Minuten lesend und signiert ab und zeigt eine Ampel – Versionen,
  Prüfungen, Sicherung, Fehlerprotokoll (nur Zahlen). Keine Inhalte, keine Personendaten.

Die Websites sprechen mit dem Hub nur über offene Standards bzw. die hier beschriebenen Protokolle. Deshalb kann jedes System
teilnehmen – und eine KLXM-Studio-Instanz kann ebenso jeden anderen Anbieter nutzen, der das OIDC-Profil erfüllt.

| Dokument | Inhalt |
|---|---|
| [oidc-profil.md](oidc-profil.md) | OIDC-Profil: Discovery, Code-Flow mit PKCE, Claims, Rollen, Sicherheitsanforderungen an Clients, Abmelden |
| [monitoring-status-v1.md](monitoring-status-v1.md) | Status-Abfrage `status-v1`: Ed25519-Signatur, Zeitfenster, Nonce, Antwortformat, Fehlercodes |
| [instance-v1.md](instance-v1.md) | Selbstauskunft `/.well-known/klxm-studio`: Adressen automatisch ermitteln |
| [eigene-loesung.md](eigene-loesung.md) | Eigenes System anbinden – Schritt für Schritt, Beispielcode (MIT), Prüfliste |

## Welche Systeme wie teilnehmen

| System | Anmeldung über den Hub | Überwachung | Selbstauskunft |
|---|---|---|---|
| **KLXM Studio** | eingebaut (`Core\Oidc`): Benutzer & Rollen → Anmeldung & Sicherheit | eingebaut (`Core\Monitoring`): Grundeinstellungen → Überwachung | eingebaut |
| **REDAXO 6** | AddOn [`klxm_hub`](https://github.com/KLXM-Studio/redaxo-klxm-hub) | AddOn `klxm_hub` | noch nicht |
| **REDAXO 5** | AddOn in Arbeit | AddOn in Arbeit | – |
| **eigene Lösung** | jeder Standard-OIDC-Client nach [oidc-profil.md](oidc-profil.md) | nach [monitoring-status-v1.md](monitoring-status-v1.md) | optional nach [instance-v1.md](instance-v1.md) |

Alles ist in den Instanzen **standardmäßig aus** und ändert nichts an der Darstellung der Website: Die Anmeldung zeigt nur
auf der Anmeldeseite der Verwaltung einen dezenten Link, der Status-Endpunkt liefert nur JSON an signierte Anfragen.

## KLXM Studio als Instanz – Kurzfassung

1. **Im Hub:** Instanz registrieren (System „KLXM Studio“, Adresse der Website) → „Adressen ermitteln“ oder von Hand:
   Redirect-URI `https://website.example.org/admin/login/oidc/callback`, Einstieg `…/admin/login/oidc`, nach dem Abmelden
   `…/admin/login`, Status `…/api/monitoring/status` (mit eigener Verwaltungsadresse bzw. Netzwerk-Domain entsprechend).
   Client-ID und Client-Geheimnis notieren (einmal angezeigt), Personen freigeben.
2. **In der Instanz – Anmeldung:** Benutzer & Rollen → Anmeldung & Sicherheit → „Anmeldung über einen Identitätsanbieter“:
   Issuer (z. B. `https://hub.example.org/hub`), Client-ID, Client-Geheimnis; Rolle „automatisch“ (Administration bzw. im
   Netzwerk Netzwerk-Administration); optional Claim `roles` = `admin`; zweiter Faktor „muss im Token belegt sein“;
   einschalten und speichern (die Instanz prüft die Discovery).
3. **In der Instanz – Überwachung:** Grundeinstellungen → Überwachung: öffentlichen Schlüssel des Hubs (`ed25519:…`)
   eintragen, einschalten.
4. **Im Hub:** „Jetzt prüfen“ – die Ampel erscheint; „Öffnen“ meldet direkt an.
5. Lokalen Notfall-Admin mit Passwort und Passkey behalten.

Ausführlich: Handbuch → Anmelden & Konto (Identitätsanbieter, Überwachung) und Entwicklerhandbuch → Sicherheit.

## Eigener Hub statt des Agentur Hubs

Für die Instanzen ist der Hub austauschbar: Als Anbieter genügt jeder OpenID-Connect-Anbieter, der das
[OIDC-Profil](oidc-profil.md) erfüllt (Code-Flow mit PKCE S256, `amr` mit zweitem Faktor, E-Mail-Claim; für das REDAXO-AddOn
zusätzlich `roles`), als Überwachung jeder Dienst, der Anfragen nach [`status-v1`](monitoring-status-v1.md) signiert.

---

## English summary

The Agentur Hub is a KLXM Studio extension running on one website of a Studio installation. It is an OpenID Connect provider
(single sign-on with a mandatory second factor for agency staff) and a read-only, signed monitoring service. Instances talk to it
only through open protocols: [OIDC profile](oidc-profil.md), [status-v1](monitoring-status-v1.md) and the optional
[instance self-description](instance-v1.md); [eigene-loesung.md](eigene-loesung.md) shows how to connect your own system
(MIT sample code, security checklist). KLXM Studio supports all three out of the box (off by default), REDAXO 6 via the
`klxm_hub` addon, REDAXO 5 is in progress. Any provider or monitoring service implementing these specifications can replace the
hub.
