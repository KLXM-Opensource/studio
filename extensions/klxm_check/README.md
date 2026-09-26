# KLXM Check für KLXM Studio (`klxm_check`)

Domain-, Mail- und TLS-Analyse als **Block für alle Kits** – Neubau von [klxm.de/check](https://klxm.de/check/).
Besucher prüfen eine Domain und bekommen verständliche Befunde mit Empfehlungen; dazu gibt es einen SPF- und einen
DMARC-Generator mit Schritt-für-Schritt-Anleitung. Lizenz: MIT (`LICENSE`). Paket: `klxm/studio-check`.

## Funktionen

- **Domain-Analyse** (ein Eingabefeld, teilbare Adresse `?domain=…`, „Link kopieren“, <kbd>⌘</kbd>/<kbd>Strg</kbd>+<kbd>K</kbd>,
  Zusammenfassung mit Gesamtbewertung und Sprungmarken):
  - **SPF**: Eintrag holen und zerlegen, `include`/`redirect` rekursiv auflösen, **DNS-Lookups zählen** (Grenze 10, RFC 7208),
    leere Abfragen (void lookups, Grenze 2), mehrere Einträge, `+all`/`?all`, veraltetes `ptr`, Makros, Erklärung je Ausdruck,
    Optimierungsvorschlag (Dubletten, `a:domain` → `a`, von `ip4`-Netzen abgedeckte Einträge, Reihenfolge).
  - **DMARC**: `_dmarc`-Eintrag (sonst Organisations-Domain), Tags `p`, `sp`, `np`, `pct`, `rua`/`ruf`, `adkim`/`aspf`, `fo`, `ri`, `t`;
    **Freigabe externer Berichts-Empfänger** (`<domain>._report._dmarc.<empfänger>`), Empfehlungen.
  - **DKIM**: rund 50 gängige Selektoren plus eigener Selektor; Schlüsseltyp, **Länge in Bit**, Testmodus (`t=y`), widerrufen (leeres `p=`).
  - **Mailserver**: MX, Adressen, Reverse DNS, **FCrDNS**, CNAME-/Null-MX-Prüfung, **STARTTLS auf Port 25** (Hinweis statt Fehler,
    wenn der Hoster Port 25 sperrt).
  - **Hosting & DNS**: A/AAAA (auch `www.`), NS mit Anbieter-Hinweis, SOA, CAA, Reverse DNS, **DNSSEC** (DS-Eintrag, AD-Bit), IPv6-Bereitschaft.
  - **Website**: HTTPS, HTTP → HTTPS, Weiterleitungskette (max. 5), **HSTS**, CSP, X-Content-Type-Options, Referrer-Policy,
    Permissions-Policy, X-Frame-Options/frame-ancestors, COOP/CORP, Cookie-Attribute (nur Namen), Server-/X-Powered-By-Angaben,
    HTTP-Version, Antwortzeit, Kompression.
- **SSL/TLS-Checker** für HTTPS (443), SMTPS (465), SMTP mit STARTTLS (587/25), IMAPS (993), POP3S (995): Inhaber, SANs, Aussteller,
  Gültigkeit und Resttage, Schlüssel, Signatur, geschickte Kette, Namensprüfung, Vertrauen, Protokoll und Cipher, TLS 1.0/1.1 noch aktiv?
- **SPF-Generator** (eigene Server, häufige Dienste wie Google Workspace/Microsoft 365/IONOS …, IPv4/IPv6, include, a:, mx:) und
  **Analyse/Optimierung** eines eingefügten Eintrags; **DMARC-Generator** (p, sp, pct, rua, ruf, aspf, adkim) und **Analyse**.
- **Einsteiger-Guides** zu SPF, DMARC und dem Vorgehen als aufklappbare Abschnitte.

Barrierefrei (WCAG 2.2 AA): Reiter nach WAI-ARIA „Tabs“, Live-Regionen, sichtbarer Fokus, Status nie nur über Farbe (Symbol + Text),
`prefers-reduced-motion`, `forced-colors`. Das Aussehen erbt Schrift und Farben des Kits (currentColor, `color-mix`, Kit-Klassen
`.btn--primary`/`.btn--secondary`), hell/dunkel, ab 360 px Breite. Keine externen Anfragen des Browsers, kein CDN.
Stil ≈ 3 KB, Skript ≈ 6 KB (gzip), nur auf Seiten mit dem Werkzeug.

## Installation

**Als Composer-Paket** (eigenes Repository):

```bash
composer require klxm/studio-check
php bin/console extensions:publish        # Stil/Skript nach public/extensions/klxm_check
```

Paket-Typ `klxm-studio-extension`, Einstieg `extension.php` (`extra.klxm-studio.entry`). Stil und Skript in `assets/` laufen
ohne Bündeln – `extensions:publish` kopiert sie, wenn kein Build vorhanden ist.

**Im Projekt**: Ordner nach `extensions/klxm_check/` kopieren und `cd tools && pnpm run build` (oder `php bin/console extensions:publish`).

Voraussetzungen: PHP ≥ 8.4.1 mit `openssl`; empfohlen `curl` (Website-Prüfung) und `intl` (internationale Domains – sonst
eingebauter Punycode-Umwandler). Ausgehende Verbindungen (DNS/UDP 53 zum Resolver des Servers, TCP 80/443/25/465/587/993/995).

## Aktivieren und einsetzen

1. **Administration → Funktionen & Erweiterungen** → „KLXM Check“ aktivieren (Sicherheitshinweis bestätigen, Passwort).
   Alternativ per Konfiguration `config/sites/{key}.php`: `'extensions' => ['klxm_check']`. Die Funktion `check` lässt sich je
   Website wieder abschalten, ohne die Erweiterung zu deaktivieren.
2. **Block** einfügen: Seite bearbeiten → Block hinzufügen → Gruppe **Werkzeuge** → **KLXM Check**. Einstellbar: Überschrift,
   Einleitung, Start-Reiter, angebotene Werkzeuge (alle / nur Analyse / nur SSL/TLS / nur Generatoren), Einsteiger-Guides.
3. Optional **eigenständige Seite** `/check` ohne Block: **Administration → KLXM Check** → „Seite /check anzeigen“
   (bzw. `'klxm_check' => ['page' => true]`). Eine CMS-Seite mit derselben Adresse hat Vorrang.

Links wie beim alten Werkzeug: `?domain=beispiel.de` startet die Analyse, `?tab=checker|ssl-checker|spf-generator|dmarc-generator`
öffnet den Reiter (`?tab=ssl-checker&domain=host&service=imaps` prüft direkt).

## Konfiguration (optional)

```php
'klxm_check' => [
    'page' => false,                 // eigenständige Seite (sonst Schalter in der Verwaltung)
    'path' => '/check',              // Adresse der eigenständigen Seite
    'limits' => ['minute' => 40, 'day' => 400, 'site_day' => 5000],   // je Besucher/Minute, je Besucher/Tag, je Website/Tag
    'cache_ttl' => 300,              // Sekunden je Domain-Ergebnis (0 = aus)
    'smtp' => true,                  // STARTTLS auf Port 25 versuchen
    'resolver' => '',                // Resolver für den DNS-Client (Standard: /etc/resolv.conf)
],
```

## Sicherheit

Das Werkzeug lässt den Server im Auftrag von Besuchern fremde Server abfragen. Schutzmaßnahmen:

- **Eingaben**: Domain wird normalisiert (Schema/Pfad/E-Mail entfernt, IDN → Punycode), max. 253 Zeichen, gültige Labels,
  **keine IP-Adressen und keine Ports** (auch nicht im SSL/TLS-Checker – Zertifikate gehören zu Namen), keine internen/reservierten
  Namen (`localhost`, `.local`, `.internal`, `.test`, `.invalid`, `.arpa` …).
- **SSRF**: Namen werden selbst aufgelöst, **alle** Adressen müssen öffentlich sein (gesperrt u. a. 0/8, 10/8, 100.64/10, 127/8,
  169.254/16, 172.16/12, 192.168/16, 198.18/15, Dokumentationsnetze, Multicast, 240/4; IPv6 nur 2000::/3 ohne 2001::/23,
  2001:db8::/32, 3fff::/20, 6to4 mit interner IPv4 – damit auch ohne ::1, fc00::/7, fe80::/10, IPv4-gemappt, NAT64).
  Verbunden wird **fest mit der geprüften IP** (curl `CURLOPT_RESOLVE`, Sockets mit SNI/peer_name) – kein DNS-Rebinding.
  Jede Weiterleitung (max. 5) wird neu geprüft; Weiterleitungen auf IP-Adressen oder andere Ports werden nicht verfolgt.
  Feste Ports 80/443/25/465/587/993/995, Verbindungsaufbau 5 s, gesamt 10 s, max. 256 KB Antwort, kein Proxy aus der Umgebung,
  keine Cookies oder Zugangsdaten, User-Agent `KLXM-Check/1.0 (+https://klxm.de/check/)`.
- **DNS** über einen eigenen Client mit 2,5 s Zeitlimit (Resolver des Servers), sonst `dns_get_record`. Nicht öffentliche
  Adressen aus DNS-Antworten werden nie angezeigt (keine internen Netze preisgeben).
- **Missbrauch**: Ratenbegrenzung je Besucher über `Core\RateLimiter` mit **HMAC der IP** (keine IP gespeichert) und je Website,
  Ergebnis-Zwischenspeicher je Domain (5 min, Dateiname = HMAC), Aufrufe von fremden Websites (`Sec-Fetch-Site: cross-site`,
  fremder Origin) abgelehnt. Antworten mit `Cache-Control: no-store`, `X-Robots-Tag: noindex`, CSP `default-src 'none'`.
- **Datenschutz**: keine Protokollierung abgefragter Domains, nur anonyme Zähler je Tag und Prüfart; keine Cookies, keine Sitzung.
- Standardmäßig **aus**; Einschalten verlangt die Bestätigung des Sicherheitshinweises.

Selbsttest: `php bin/console check:selftest --site={key} [--online]` (IP-Filter, Domain-Prüfung, Parser, Lookup-Zählung,
SSRF-Sperren). Prüfung auf der Kommandozeile: `php bin/console check:run spf beispiel.de [--json]`.

## Grenzen

- Port 25 ist bei vielen Hostern ausgehend gesperrt – die Mailserver-Prüfung meldet das als Hinweis.
- DNSSEC: angezeigt wird, ob ein DS-Eintrag existiert und ob der Resolver die Antwort validiert (AD-Bit); keine eigene Kettenprüfung.
- Organisations-Domain ohne Public Suffix List (Näherung für `co.uk`, `com.au` …).
- TLS 1.0/1.1 lassen sich nur prüfen, wenn das OpenSSL des Servers sie noch anbietet (sonst „nicht prüfbar“).
- Die Vertrauensprüfung nutzt die Zertifizierungsstellen des Servers; der Grund eines Fehlers wird aus der geschickten Kette abgeleitet.

## English

**KLXM Check** is a KLXM Studio extension (MIT) that adds a block for every kit: domain analysis (SPF with DNS-lookup counting,
DMARC with external report authorisation, DKIM, MX/PTR/FCrDNS/STARTTLS, hosting/DNS/DNSSEC/IPv6, website security headers),
an SSL/TLS checker (HTTPS, SMTPS, SMTP STARTTLS, IMAPS, POP3S) and SPF/DMARC generators. Install with
`composer require klxm/studio-check && php bin/console extensions:publish` (or copy to `extensions/klxm_check`), enable it under
*Administration → Features & extensions*, then add the block “KLXM Check” (group “Tools”) to a page. All outbound connections are
SSRF-protected (public IPs only, pinned connections, fixed ports, timeouts, size caps) and rate-limited per visitor (HMAC of the IP)
and per site; queried domains are never logged. Texts are German with English translations (`lang/`).
