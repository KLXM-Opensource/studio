<?php /** Entwicklerhandbuch · „KLXM Check“ (Erweiterung klxm_check, eingebunden über Extension::docs('technical')) */ ?>
  <p class="lead">Erweiterung <code>klxm_check</code> (Paket <code>klxm/studio-check</code>, MIT): Domain-, Mail- und TLS-Analyse als Block für alle Kits – Neubau von klxm.de/check. Details: <code>README.md</code> im Erweiterungsordner.</p>

  <h3>Einbindung</h3>
  <ul>
    <li><b>Block</b> <code>klxm_check</code> über <code>Extension::blocks()</code> (Renderer <code>blocks/klxm_check.php</code>) – Theme-Blöcke gleichen Namens hätten Vorrang, daher gilt er in jedem Kit. Stil <code>/extensions/klxm_check/css/check.css</code> und Skript <code>js/check.mjs</code> (ES-Modul) werden nur auf Seiten mit dem Werkzeug geladen (je einmal).</li>
    <li><b>Eigenständige Seite</b> unter <code>/check</code> (Konfiguration <code>'klxm_check' =&gt; ['path' =&gt; '/check']</code>): Schalter unter Administration → KLXM Check (Einstellung <code>ext.klxm_check.page</code>, Standard aus) bzw. <code>'klxm_check' =&gt; ['page' =&gt; true]</code>. Eine CMS-Seite mit gleicher Adresse hat Vorrang.</li>
    <li><b>Funktion</b> <code>check</code> (je Website abschaltbar); Menüpunkt, Kachel der Übersicht (anonyme Zähler), <code>health</code>, Handbuch/Technik über <code>Extension::docs</code>.</li>
    <li>CLI: <code>check:selftest [--online]</code>, <code>check:run &lt;spf|dmarc|dkim|mail|hosting|web|tls&gt; &lt;domain&gt; [--service=…] [--selector=…] [--json]</code>.</li>
  </ul>

  <h3>Schnittstelle</h3>
  <table class="doc-table">
    <tr><th>Weg</th><th>Zweck</th></tr>
    <tr><td><code>GET /check/api/{spf|dmarc|dkim|mail|hosting|web}?domain=…</code></td><td>Abschnitt der Domain-Analyse (<code>selector</code> für DKIM, <code>smtp=0</code> ohne STARTTLS-Probe, <code>lang</code>)</td></tr>
    <tr><td><code>GET /check/api/tls?host=…&amp;service=https|smtps|submission|smtp|imaps|pop3s</code></td><td>SSL/TLS-Checker</td></tr>
    <tr><td><code>POST /check/api/spf-analyse</code> · <code>/dmarc-analyse</code></td><td>Eingefügten Eintrag prüfen (<code>record</code>, optional <code>domain</code>)</td></tr>
  </table>
  <p>Antwort: <code>{ok, section, title, status: ok|info|warn|error, score, summary, findings[{level, text}], blocks[kv|table|code|list]}</code> – Texte in der Sprache der Seite, das Skript setzt sie nur als Text ein.</p>

  <h3>Sicherheit</h3>
  <ul>
    <li><b>Eingaben:</b> Domain normalisiert (Schema/Pfad/E-Mail entfernt, IDN → Punycode mit intl oder eingebautem Umwandler), max. 253 Zeichen, gültige Labels, keine IP-Adressen, keine Ports, keine internen/reservierten Namen (localhost, .local, .internal, .test, .arpa …).</li>
    <li><b>SSRF:</b> Namen werden selbst aufgelöst; <em>alle</em> Adressen müssen öffentlich sein (gesperrt u. a. 0/8, 10/8, 100.64/10, 127/8, 169.254/16, 172.16/12, 192.168/16, 198.18/15, Dokunetze, Multicast, 240/4; IPv6 nur 2000::/3 ohne 2001::/23, 2001:db8::/32, 6to4 mit interner IPv4). Verbunden wird fest mit der geprüften IP (curl <code>CURLOPT_RESOLVE</code>, Sockets mit SNI/peer_name), jede Weiterleitung (max. 5) wird neu geprüft, keine Weiterleitung auf IP-Adressen. Feste Ports 80/443/25/465/587/993/995, Verbindungsaufbau 5 s, gesamt 10 s, höchstens 256 KB Antwort, kein Proxy, keine Cookies/Zugangsdaten, User-Agent <code>KLXM-Check/1.0</code>.</li>
    <li><b>DNS</b> über einen eigenen UDP/TCP-Client mit 2,5 s Zeitlimit (Resolver des Servers aus <code>/etc/resolv.conf</code> oder <code>'klxm_check' =&gt; ['resolver' =&gt; …]</code>), sonst <code>dns_get_record</code>. Nicht öffentliche Adressen aus DNS-Antworten werden nie angezeigt.</li>
    <li><b>Missbrauch:</b> Ratenbegrenzung je HMAC der IP (<code>Core\RateLimiter</code>, Standard 40/Minute, 400/Tag) und je Website (5000/Tag), Ergebnisse 5 Minuten je Domain zwischengespeichert (Dateiname = HMAC), Aufrufe mit <code>Sec-Fetch-Site: cross-site</code> bzw. fremdem Origin abgelehnt. Antworten mit <code>Cache-Control: no-store</code>, <code>X-Robots-Tag: noindex</code>, CSP <code>default-src 'none'</code>.</li>
    <li><b>Datenschutz:</b> keine Protokollierung abgefragter Domains; nur anonyme Zähler je Tag und Prüfart. Fehler landen ohne Domain im Fehlerprotokoll.</li>
  </ul>
