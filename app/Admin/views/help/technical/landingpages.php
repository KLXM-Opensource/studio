<?php /** Entwicklerhandbuch · Landingpages mit eigenen Domains (Core\Landings, Core\Landing) */ ?>
  <p class="lead">Eine weitere Domain zeigt eine Seite der Website – auf Wunsch mit ihren Unterseiten – ohne eigene Website, Benutzer oder Datenbank. Beispiel: <code>reisemedizin-moers.de</code> zeigt den Zweig „Reisemedizin“ der Praxis-Website. Inhalte, Medien, Formulare und Anfragen bleiben im Projekt; Besucher erhalten auch hier keine Cookies und keine Analyse.</p>
  <table class="doc-table">
    <tr><th>Baustein</th><th>Ort</th></tr>
    <tr><td>Domain gehört zur Website</td><td><code>config/sites/{key}.php</code> → <code>'landing_hosts' =&gt; ['reisemedizin-moers.de', 'www.reisemedizin-moers.de']</code> (Agentur; <code>Sites::resolve</code> prüft <code>hosts</code> und <code>landing_hosts</code>). Hauptadresse der Website bleibt <code>hosts[0]</code>.</td></tr>
    <tr><td>Zuordnung Domain → Seite</td><td>Tabelle <code>landings</code> der Website (id, label, hosts JSON, page_id, include_subpages, mode, options_json, active) – Verwaltung → Administration → <b>Landingpages</b> (Recht <code>system.manage</code>, Funktion <code>landings</code>)</td></tr>
    <tr><td>Funktion</td><td><code>landings</code> (Core\Features) – an in <code>full</code>, aus in den Presets <code>content</code>/<code>minimal</code>; Integratoren und Netzwerk-Konten sehen sie immer. Ohne Funktion werden Landing-Domains wie normale Domains der Website behandelt.</td></tr>
    <tr><td>Kits</td><td><code>landing()</code> → <code>?Core\Landing</code> (null auf der Hauptdomain): <code>-&gt;name</code>, <code>-&gt;tagline</code>, <code>-&gt;logo</code> (Medien-ID), <code>-&gt;layout</code>; Klassen <code>is-landing</code> / <code>is-landing--reduced</code> am <code>&lt;html&gt;</code> (über <code>design_classes()</code>); optionales Template <code>templates/landing.php</code> für das reduzierte Layout</td></tr>
  </table>

  <h3>Einrichten (Agentur)</h3>
  <ol class="doc-steps">
    <li><b>DNS:</b> A/AAAA (oder CNAME) der neuen Domain auf den Server.</li>
    <li><b>Plesk:</b> die Domain als <b>Alias</b> der Hauptdomain anlegen (Websites &amp; Domains → Alias hinzufügen; „Umleitung mit HTTP 301“ und „Mail-Dienst“ <b>aus</b>, damit der Alias die Inhalte selbst ausliefert) – oder als zusätzliche Domain im selben Abonnement mit <b>Dokumentstamm <code>httpdocs/public</code></b> und denselben Apache-/nginx-Anweisungen. SSL-Zertifikat (Let’s Encrypt) für die Domain ausstellen bzw. den Alias ins Zertifikat aufnehmen.</li>
    <li><b>Domain der Website zuordnen:</b> <code>php bin/console site:hosts &lt;key&gt; add reisemedizin-moers.de --landing</code> – oder in der Netzwerk-Übersicht bei der Website „Domains bearbeiten“. Der Befehl ändert nur den Eintrag <code>landing_hosts</code> (Kommentare und übrige Werte bleiben; Prüfung vor dem Ersetzen, Sicherung <code>{key}.php.bak</code>). <code>site:hosts &lt;key&gt;</code> zeigt die Domains, <code>remove</code> entfernt eine.</li>
    <li><b>Verwaltung → Landingpages:</b> Domain und Seite wählen, Modus festlegen, „Status prüfen“ (DNS + Anfrage an <code>https://domain/health?landing=…</code> mit Prüf-Token – bestätigt, dass die Domain auf <em>diese</em> Installation und Landingpage zeigt).</li>
  </ol>
  <pre><code>php bin/console site:hosts default add reisemedizin-moers.de --landing
php bin/console site:hosts default add www.reisemedizin-moers.de --landing
php bin/console site:hosts default                # Domains + Landing-Domains anzeigen</code></pre>

  <h3>Routing auf einer Landing-Domain</h3>
  <table class="doc-table">
    <tr><th>Anfrage</th><th>Ergebnis</th></tr>
    <tr><td><code>/</code></td><td>Einstiegsseite (die gewählte Seite)</td></tr>
    <tr><td><code>/impfungen</code></td><td>Unterseite <code>reisemedizin/impfungen</code> – nur mit „Unterseiten einbeziehen“</td></tr>
    <tr><td><code>/en/</code>, <code>/en/…</code></td><td>Übersetzung der Einstiegsseite bzw. ihrer Unterseiten</td></tr>
    <tr><td>andere Pfade</td><td>302 zur gleichen Adresse auf der Hauptdomain („Andere Adressen weiterleiten“, Standard) – sonst 404</td></tr>
    <tr><td><code>/admin…</code></td><td>302 zur Hauptdomain. Auf Landing-Domains entstehen nie Sitzungen oder Cookies; Anmeldung, Passkeys (RP-ID = Domain) und Bearbeiten bleiben auf der Hauptdomain.</td></tr>
    <tr><td>Medien, Kit-Dateien, <code>/formular/…</code>, <code>/anfrage/…</code>, <code>/suche</code>, <code>/health</code>, <code>/pdf</code>, <code>/kalender/…ics</code></td><td>unverändert auf der Landing-Domain (gleicher Webroot). Formulare senden an die Landing-Domain, Einträge landen in den Eingängen der Website.</td></tr>
    <tr><td><code>/sitemap.xml</code>, <code>/robots.txt</code></td><td>je Domain: die Landing-Domain listet nur ihre Seiten (Modus „own“, nicht „noindex“); die Sitemap der Hauptdomain lässt Seiten weg, deren maßgebliche Adresse eine Landing-Domain ist.</td></tr>
    <tr><td><code>/favicon.ico</code></td><td>eigenes Favicon (Mediathek) per 302, sonst das der Website</td></tr>
  </table>
  <p><b>Links:</b> <code>Pages::url()</code> liefert auf der Landing-Domain für Seiten der Landingpage relative Adressen, für alle anderen absolute Adressen der Hauptdomain (Impressum, Datenschutz …). <code>link_href()</code> und Rich-Text (<code>rich()</code> → <code>Landings::rewriteLinks</code>) bilden Seitenpfade wie <code>/reisemedizin/impfungen</code> ebenso ab; Anker, die es auf der Einstiegsseite nicht gibt, führen zur Startseite der Hauptdomain. Das Menü (<code>Pages::menu()</code>) zeigt die Unterseiten der Einstiegsseite, <code>Theme::navigation()</code> deren Abschnitte. <code>Pages::plainUrl()</code> gibt immer die Adresse der Hauptdomain; <code>Landings::suspend(fn)</code> führt Code „wie auf der Hauptdomain“ aus (Suchindex, Spiegel-Modus). Hauptdomain = Grundeinstellung „Kanonische Adresse“ → <code>base_url</code> → <code>hosts[0]</code> → zuletzt benutzte Verwaltungsadresse.</p>

  <h3>Suchmaschinen</h3>
  <table class="doc-table">
    <tr><th>Modus</th><th>Canonical / hreflang</th><th>JSON-LD</th><th>Sitemap</th></tr>
    <tr><td><code>own</code> – eigene Domain maßgeblich</td><td>Landing-Domain – auch auf der Hauptdomain-Adresse der Seite (optional <b>301</b> dorthin für Besucher; angemeldete Redakteure und <code>?edit</code> bleiben)</td><td><code>WebSite</code> der Landing-Domain mit ihrem Namen, <code>Organization</code> bleibt die der Website (<code>@id</code> auf der Hauptdomain), Brotkrumen ab der Einstiegsseite</td><td>Landing-Domain</td></tr>
    <tr><td><code>mirror</code> – Spiegel</td><td>Hauptdomain</td><td>wie auf der Hauptdomain</td><td>Hauptdomain (Landing-Sitemap leer)</td></tr>
  </table>
  <p>„Nicht in Suchmaschinen“ je Landingpage setzt <code>noindex</code>, <code>X-Robots-Tag</code> und <code>Disallow: /</code> für die Landing-Domain. Titel-Zusatz und <code>og:image</code> folgen der Marke der Landingpage. Der Seiten-Cache (<code>PageCache</code>) ist je Domain getrennt.</p>

  <h3>Marke &amp; Layout</h3>
  <ul>
    <li><b>Design:</b> Farben und Schriften des Kits lassen sich je Landingpage überschreiben; daraus entsteht eine kleine Datei <code>media/design/landing-{id}-{theme}-{hash}.css</code>, die <code>design_head()</code> nach den Design-Variablen der Website einbindet (CSP-konform, kein Inline-CSS). Die Verwaltung prüft den Kontrast (WCAG 2.2 AA) der überschriebenen Farben.</li>
    <li><b>Logo, Name, Unterzeile:</b> die Kern-Kits zeigen sie im Kopf (<code>partials/brand.php</code> bzw. praxis <code>partials/header.php</code>); <code>site_name()</code> liefert auf der Landing-Domain den Namen der Landingpage.</li>
    <li><b>Favicon/App-Icon</b> und <b>Vorschaubild</b> aus der Mediathek; die installierbare Web-App (Manifest) bleibt die der Website.</li>
    <li><b>Layout:</b> „full“ = normales Layout mit Menü der Landingpage; „landing“ rendert <code>templates/landing.php</code>, wenn das Kit eines mitbringt (Variablen wie <code>layout.php</code>), sonst das normale Layout mit der Klasse <code>is-landing--reduced</code>.</li>
  </ul>

  <h3>Suche, API &amp; MCP</h3>
  <p>Suche je Landingpage: „Nur Seiten der Landingpage“ (Standard), „Ganze Website“ (Treffer außerhalb absolut zur Hauptdomain) oder „Aus“ (Lupe verschwindet, <code>/suche</code> → Hauptdomain bzw. 404). Lesen: <code>GET /api/v1/landings</code> und MCP-Tool <code>list_landings</code> (Domains, Seite, Modus, Marke, Seiten mit Adressen). Inhalte der Landingpages sind normale Seiten (<code>get_page</code>, <code>update_page</code>).</p>
  <div class="doc-note doc-note--warn"><strong>Grenzen</strong><p>Detailseiten von Datentabellen und Kalender-Feeds bleiben auf der Hauptdomain (Links aus Listen führen per Weiterleitung dorthin). Eine Landing-Domain kann nicht die Startseite zeigen, und jede Domain gehört zu höchstens einer Landingpage.</p></div>
