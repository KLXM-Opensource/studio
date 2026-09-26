# Changelog – KLXM Check (`klxm_check`)

## 1.0.0 – 2026-09-26

Erste Version als KLXM-Studio-Erweiterung (Neubau von klxm.de/check).

- Domain-Analyse: SPF (rekursive Auflösung, Lookup- und Void-Zählung, Optimierung), DMARC (Tags, Organisations-Domain,
  Freigabe externer Berichts-Empfänger), DKIM (≈ 50 Selektoren + eigener, Schlüssellänge, Testmodus), Mailserver (MX, PTR,
  FCrDNS, STARTTLS auf Port 25), Hosting & DNS (NS, SOA, CAA, DNSSEC, IPv6), Website (Weiterleitungen, HSTS, Sicherheits-Header,
  Cookies, HTTP-Version, Antwortzeit, Kompression); Gesamtbewertung, teilbare Adresse, ⌘/Strg+K.
- SSL/TLS-Checker für HTTPS, SMTPS, SMTP STARTTLS (587/25), IMAPS, POP3S.
- SPF- und DMARC-Generator mit Analyse eingefügter Einträge, Einsteiger-Guides.
- Block für alle Kits, optionale eigenständige Seite `/check`, Verwaltungsseite, Kachel, `health`, Handbuch- und Technik-Kapitel.
- Sicherheit: strenge Eingabeprüfung, SSRF-Schutz mit festgehaltener IP, feste Ports, Zeit- und Größenlimits, eigener DNS-Client
  mit Zeitlimit, Ratenbegrenzung (HMAC der IP), kurzer Zwischenspeicher, keine Protokollierung von Domains.
- `check:selftest [--online]`, `check:run`.
