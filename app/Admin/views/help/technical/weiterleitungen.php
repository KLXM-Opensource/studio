<?php /** Entwicklerhandbuch · Weiterleitungen und 404-Protokoll (Core\Redirects) */ ?>
  <p class="lead">Alte Adressen – nach einem Umzug, einer neuen Seitenstruktur oder einer umbenannten Seite – führen per 301/302 zu Seiten, Pfaden oder anderen Websites oder melden 410 („dauerhaft entfernt“). Je Website, Funktion <code>redirects</code> (Standard an), Recht <code>redirects.manage</code>, Verwaltung → Administration → Werkzeuge → <b>Weiterleitungen</b>.</p>
  <table class="doc-table">
    <tr><th>Baustein</th><th>Ort</th></tr>
    <tr><td>Regeln</td><td>Tabelle <code>redirects</code> der Website: <code>source</code> (Pfad ohne Domain, optional <code>?query</code>, optional <code>*</code> am Ende), <code>source_hash</code> (sha1 des Vergleichsschlüssels), <code>wildcard</code>, <code>target</code>, <code>code</code> (301|302|410), <code>note</code>, <code>origin</code> (manual|auto|import), <code>hits</code>, <code>last_hit</code>, <code>active</code>, Zeitstempel</td></tr>
    <tr><td>404-Protokoll</td><td>Tabelle <code>redirect_404</code>: die letzten 200 verschiedenen Pfade mit Anzahl, erstem/letztem Aufruf und „interner Link“ (Referrer = eigene Domain, nur Ja/Nein). Keine IP, kein User-Agent, kein Referrer. Angriffs-Scans (<code>/wp-…</code>, <code>*.php</code>, <code>.env</code> …) und Kit-/System-Dateien werden nicht protokolliert.</td></tr>
    <tr><td>Code</td><td><code>Core\Redirects\Redirects</code> (Normalisieren, Auflösen, Import/Export, automatische Regeln), <code>…\Console</code>, <code>…\SelfTest</code>, <code>Admin\RedirectController</code></td></tr>
    <tr><td>Einstellungen</td><td><code>sys.redirects_auto</code> (automatisch beim Umbenennen, Standard an), <code>sys.redirects_log404</code> (404-Protokoll, Standard an)</td></tr>
  </table>

  <h3>Auflösung</h3>
  <ul>
    <li><b>Nur im 404-Weg:</b> <code>App::handle</code> fängt die <code>HttpException(404)</code> ab und fragt <code>Redirects::handle404()</code> – nur GET/HEAD, nie <code>/admin</code>, <code>/api</code>, <code>/mcp</code>. Seiten, Detailseiten und alle Routen gehen damit immer vor; eine Regel auf einer vorhandenen Seite ist wirkungslos (Hinweis „Seite vorhanden“).</li>
    <li><b>Vergleich:</b> Domain und <code>#anker</code> fallen weg, Prozent-Kodierung wird aufgelöst (<code>/agentur/m%C3%BCntel/</code> = <code>/agentur/müntel</code>, Unicode NFC), Schrägstrich am Ende egal, ASCII-Groß/Kleinschreibung egal. Die Quelle wird wie eingegeben angezeigt.</li>
    <li><b>Reihenfolge:</b> exakte Quelle mit Query → exakte Quelle → Platzhalter (<code>/blog/*</code>, längster Präfix gewinnt). Ein <code>*</code> im Ziel übernimmt den Rest (<code>/blog/*</code> → <code>/news/*</code>).</li>
    <li><b>Ziele:</b> <code>page:ID[#anker]</code> (über <code>Pages::url</code> – folgt Umbenennen und Verschieben; unveröffentlicht/gelöscht → 404), <code>entry:tabelle:id</code>, <code>media:id</code>, <code>/pfad</code> oder <code>https://…</code>. Die Query der Anfrage wird angehängt (außer die Quelle nennt selbst eine Query). Location mit prozent-kodierten Umlauten.</li>
    <li><b>Keine Ketten, keine Schleifen:</b> höchstens ein Sprung; Ziel = Quelle wird beim Speichern abgelehnt und zur Laufzeit ignoriert, ebenso Platzhalter, deren Ziel wieder zur Quelle passt.</li>
    <li><b>410</b> zeigt die 404-Seite (gepflegte Seite „Nicht gefunden“, sonst die des Kits) mit Status 410. Treffer: ein <code>UPDATE hits = hits + 1</code> je Weiterleitung; 301 mit <code>Cache-Control: public, max-age=3600</code>, 302 mit <code>no-cache</code>.</li>
    <li><b>Mehrsprachig &amp; Landingpages:</b> Quellen mit Sprachpräfix angeben (<code>/en/old-page</code>); Seitenziele liefern die Adresse passend zur Domain (Landing-Domains über <code>Pages::url</code>).</li>
  </ul>

  <h3>Automatisch beim Umbenennen und Verschieben</h3>
  <p><code>Pages::rebuildPaths()</code> meldet geänderte Pfade an <code>Redirects::pathsChanged()</code>. Für veröffentlichte (oder schon einmal veröffentlichte) Seiten entsteht „alter Pfad → <code>page:ID</code>“ (301, Herkunft „auto“) – auch für alle Unterseiten eines umbenannten Zweigs. Gibt es für die alte Adresse schon eine Regel, bekommt sie das neue Ziel (keine Duplikate, keine Ketten). Wird eine Adresse wieder zur Adresse einer veröffentlichten Seite, entfällt die exakte Regel dafür. Wirkt für Verwaltung, API, MCP und Content-Sync; Content-Sync überträgt die Regeln selbst nicht (sie werden live gepflegt).</p>

  <h3>Import &amp; Export</h3>
  <p>CSV (<code>quelle;ziel;code;notiz</code>, Trenner <code>;</code>, <code>,</code> oder Tab, Kopfzeile optional, <code>#</code> = Kommentar) oder JSON: <code>[{"from": "/alt/", "to": "/neu/"}]</code> (auch <code>source/target</code>, <code>code</code>, <code>note</code>, ein Objekt <code>{"/alt": "/neu"}</code> oder <code>{"redirects": […]}</code>). Interne Pfad-Ziele mit veröffentlichter Seite werden standardmäßig zu <code>page:ID</code> („Ziele mit Seiten verknüpfen“, Konsole: <code>--keep-paths</code> schaltet ab). Vorhandene Quellen werden übersprungen, außer „überschreiben“ (<code>--overwrite</code>). Der Import läuft in einer Transaktion; „Nur prüfen“ (<code>--dry-run</code>) speichert nichts.</p>
  <pre><code>php bin/console redirects:import umzug.json --site=kunde --dry-run
php bin/console redirects:import umzug.json --site=kunde
php bin/console redirects:list --site=kunde [--q=agentur]
php bin/console redirects:test /agentur/referenzen/foo-12/ --site=kunde
php bin/console redirects:selftest</code></pre>
  <p><b>API/MCP:</b> <code>GET /api/v1/redirects</code> (<code>?q=</code>, <code>?test=/pfad</code>) bzw. MCP-Werkzeug <code>list_redirects</code> – nur lesen. Anlegen und Ändern bleiben der Verwaltung und der Konsole vorbehalten (keine Einreichung über die Prüf-Ebene).</p>
  <div class="doc-note"><strong>Grenzen</strong><p>Adressen, die der Webserver selbst beantwortet (vorhandene Dateien in <code>public/</code>), und <code>/index.php?…</code> (landet auf der Startseite) erreichen die Weiterleitungen nicht – solche Altadressen im Webserver umleiten. Kein Regex, nur ein <code>*</code> am Ende.</p></div>
