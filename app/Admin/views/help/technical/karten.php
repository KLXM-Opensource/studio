<?php /** Entwicklerhandbuch · Karten & Proxy */ ?>
  <p class="lead">Externe Inhalte laufen über den eigenen Server. Besucher haben keinen Kontakt zu Dritten – keine IP-Übertragung, keine Cookies, keine Zwei-Klick-Einwilligung.</p>
  <h3>Zentraler Proxy (<code>Core\Proxy</code>)</h3>
  <p>Route <code>GET /proxy/{quelle}/{pfad}</code>. Nur registrierte Quellen, nur erlaubte Pfade und Content-Types, https, keine Weiterleitungen, Größenlimit. An den Anbieter gehen weder IP, Cookies noch Referer der Besucher. Antworten liegen in <code>storage/cache/proxy</code> (außerhalb von <code>public</code>, gzip-komprimiert, ETag, Größenlimit mit Aufräumen der ältesten Dateien). Nachladen beim Anbieter ist je Besucher begrenzt (2000 / 10 min).</p>
  <pre><code>// theme.php
'proxy' =&gt; [
    'wetter' =&gt; ['label' =&gt; 'Wetterdaten', 'upstream' =&gt; 'https://api.example.org',
                 'allow' =&gt; '^v1/forecast', 'types' =&gt; ['application/json'], 'ttl' =&gt; 600],
],
// oder im Code (functions.php, Erweiterungen)
\Core\Proxy::register('name', [...]);
\Core\Proxy::url('wetter', 'v1/forecast');        // öffentliche Adresse
\Core\Proxy::http('https://api.example.org/…');   // serverseitig, nur Hosts registrierter Quellen</code></pre>
  <table class="doc-table">
    <tr><th>Option</th><th>Bedeutung</th></tr>
    <tr><td><code>upstream</code></td><td>https-Basisadresse des Anbieters</td></tr>
    <tr><td><code>public</code></td><td><code>false</code> = nur serverseitig über <code>Proxy::http()</code> (z. B. Geokodierung, oEmbed)</td></tr>
    <tr><td><code>allow</code>, <code>types</code>, <code>max</code></td><td>Pfad-Regex, erlaubte Content-Types, maximale Größe (Standard 5 MB)</td></tr>
    <tr><td><code>ttl</code></td><td>Cache-Dauer in Sekunden (Standard 1 Tag) oder <code>fn(string $pfad): int</code></td></tr>
    <tr><td><code>rewrite</code></td><td>Upstream-Adressen in JSON/CSS auf den Proxy umschreiben (z. B. Kartenstile, TileJSON)</td></tr>
    <tr><td><code>guard</code></td><td><code>fn(string $pfad): bool</code> – zusätzliche Prüfung; angemeldete Nutzer sind davon ausgenommen</td></tr>
    <tr><td><code>hosts</code></td><td>weitere Hosts für <code>Proxy::http()</code> (z. B. Bild-CDN des Anbieters)</td></tr>
    <tr><td><code>label</code>, <code>headers</code></td><td>Bezeichnung in der Übersicht; zusätzliche Header an den Anbieter (z. B. API-Schlüssel – bleiben serverseitig)</td></tr>
  </table>
  <p>Kern-Quellen: <code>ofm</code> (OpenFreeMap: Stile, Kacheln, Schriften, Symbole), <code>nominatim</code> (Adresssuche, nur Verwaltung), <code>youtube</code>/<code>vimeo</code> (Vorschaubilder, nur Server). Übersicht, Cache-Größe (Obergrenze <code>sys.proxy_cache_mb</code>, Standard 500 MB) und „Zwischenspeicher leeren“: Grundeinstellungen → Karten &amp; externe Quellen. Quellen registrieren Kits (<code>theme.php → proxy</code>), Erweiterungen (<code>$x-&gt;proxy()</code>) oder Code (<code>Proxy::register()</code>).</p>
  <h3>Karten (<code>Core\Maps</code>)</h3>
  <ul>
    <li>MapLibre GL (<code>public/assets/vendor/maplibre</code>, ~800 KB) lädt erst, wenn die Karte in Sichtweite kommt; bei „Datensparen“ erst per Klick. Stil: Grundeinstellungen (Liberty, Bright, Positron, Dark, Fiord) oder je Block.</li>
    <li>Kern-Block <code>map</code> (vom Kit überschreibbar), Feldtyp <code>geo</code> (auch in Datentabellen; auf Detailseiten per <?= icon('link', ['label' => 'Kette']) ?> an den Block koppelbar), Ausgabe im Kit: <code>\Core\Maps::render(['point' =&gt; '51.16, 10.45', 'label' =&gt; '…'])</code> oder <code>Maps::renderBlock($d)</code>, Felder für eigene Blöcke: <code>Maps::blockFields()</code>.</li>
    <li><b>Kartengebiet:</b> Kacheln bis Zoom 8 sind frei; Detailkacheln (zwischengespeichert 30 Tage, Stile 1 Tag) liefert der Proxy nur im Umkreis (Standard 25 km, einstellbar 2–200 km) um Orte, die auf der Website als Karte ausgegeben wurden (<code>sys.map_areas</code>, wird beim Rendern gepflegt). So dient der Server nicht als freier Kachelserver.</li>
    <li>Dunkelmodus: Erklärt das Kit <code>&lt;meta name="color-scheme" content="light dark"&gt;</code>, nutzt die Karte bei dunkler Geräteeinstellung automatisch <code>sys.map_style_dark</code> (Standard „Dark“) und wechselt live mit.</li>
    <li>CSP: <code>worker-src 'self' blob:</code> (MapLibre-Worker); alle Daten kommen von <code>connect-src 'self'</code>.</li>
    <li>Pflicht-Quellenangabe (OpenFreeMap, OpenMapTiles, OpenStreetMap) zeigt die Karte automatisch.</li>
  </ul>
