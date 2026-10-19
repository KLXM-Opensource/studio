<?php /** Entwicklerhandbuch · Erweiterungen: Verwaltungsseiten nach Art, Werkzeuge der Website, Ereignisse (Core\AdminPages, Core\FrontendTools) */ ?>
  <p class="lead">Erweiterungen (und Funktionen des Cores) erweitern die Verwaltung und das Bearbeiten auf der Website über drei stabile Schnittstellen: <b>Verwaltungsseiten mit Art</b> (wo eine Seite erscheint), <b>Werkzeuge der Website</b> (Knopf in der Werkzeugleiste, Seitenleiste in der Shadow-DOM-Ebene) und <b>Ereignisse</b> in PHP und im Browser. Referenzbeispiel ist das <a href="#glossar">Quick-Glossar</a> – der Core meldet es genau so an wie eine Erweiterung.</p>

  <h3 id="erweiterungen-regeln">Integrationspunkte und Regeln</h3>
  <p>Erweiterungen dürfen <b>nur über die folgenden Stellen</b> eingreifen. Jede Stelle hat eine Methode im Manifest (<code>Core\Extension</code>), einen festen Datenvertrag, eine Rechteprüfung und einen Selbsttest (<code>extensions:selftest</code>). Was hier nicht steht, gibt es nicht – auch nicht „vorübergehend“. Neue Stellen entstehen nur im Core, mit Doku und Selbsttest, nie als zweiter Weg für etwas, das es schon gibt.</p>
  <table class="doc-table">
    <tr><th>Bereich</th><th>Stellen (Manifest)</th><th>Vertrag</th></tr>
    <tr><td>Lebenszyklus</td><td><code>boot</code>, <code>install</code>, <code>deactivate</code>, <code>requirements</code>, <code>usage</code>, <code>table()</code>, <code>migration()</code></td><td>Tabellen deklarativ mit <a href="#erweiterungen-schema"><code>Core\Db\Table</code></a> (idempotent), einmalige Datenschritte mit <code>migration()</code>. Eigene Tabellen tragen den Namen der Erweiterung als Präfix.</td></tr>
    <tr><td>Routen</td><td><code>routes()</code></td><td>Routen unter <code>/admin</code> sind <a href="#erweiterungen-routen">geschützt</a>: Anmeldung, CSRF bei Nicht-GET und ein <b>Recht pro Route</b>. Ausnahmen nur benannt (<code>'csrf' =&gt; false</code>, <code>'public' =&gt; true</code>), sichtbar in <code>extensions:list</code>.</td></tr>
    <tr><td>Verwaltungsseiten</td><td><code>adminPage()</code> / <code>nav()</code>, <code>permissions()</code>, <code>feature()</code>, <code>adminAssets()</code></td><td>Art <code>content|tool|settings|stats</code> bestimmt den Ort (siehe unten).</td></tr>
    <tr><td>Slots der Verwaltung</td><td><code>pageList()</code>, <code>pagePanel()</code>, <code>tableActions()</code>, <code>mediaPanel()</code>, <code>dashboard()</code>, <code>account()</code></td><td>Liefern <b>Daten</b>, der Core rendert und escaped (<a href="#erweiterungen-slots">Slots</a>).</td></tr>
    <tr><td>Website</td><td><code>blocks()</code>, <code>htmlFilter()</code>, <code>csp()</code>, <code>footerLinks()</code>, <code>frontendTool()</code>, <code>toolbar()</code></td><td>CSP nur Hosts (kein <code>'unsafe-inline'</code>), Werkzeuge nur angemeldet.</td></tr>
    <tr><td>Blöcke &amp; Editor</td><td><code>blocksResolve()</code>, <code>blocksBeforeSave()</code>, <code>blockPresets()</code>, im Browser <code>CMSAdmin.editorExt</code></td><td><b>blocksResolve</b> <code>fn(array $blocks, array $ctx): array</code> – Blöcke auflösen – <code>$ctx['purpose']</code>: <code>render</code> (Ausgabe der Seite, <code>Theme::renderBlocks</code>, nicht im Bearbeiten-Modus), <code>preview</code> (Vorschau eines Blocks im Editor; ein aufgelöster Block anderen Typs ist dort nicht direkt bearbeitbar), <code>search</code>/<code>api</code>/<code>export</code> (Leser über <code>Pages::readBlocks($page, $draft, $purpose)</code>). Der Editor bekommt immer die rohen Blöcke. <b>blocksBeforeSave</b> – vor jedem <code>Pages::saveDraft</code> (Editor, API/MCP, KI, Import), <code>$ctx</code>: <code>page_id</code>, <code>user_id</code>. <b>blockPresets</b> <code>fn(): list&lt;{type, label, data, group?, hint?, icon?}&gt;</code> – vorbelegte Blöcke in der Blockauswahl, nach Gruppe. Fehler einer Erweiterung lassen die Blöcke unverändert (Protokoll). Browser: <code>CMSAdmin.editorExt.addBlockAction({id, label, icon?, when?(b), run(b)})</code> (Block-Menü ⋯), <code>addBadge(b =&gt; text|null)</code> (Kennzeichen in der Block-Leiste), <code>blocks()</code> (Seitenreihenfolge), <code>refreshBadges()</code>, <code>api(url, body)</code> (POST mit CSRF), <code>readForm(form)</code> (Felder <code>f[…]</code> → Daten), <code>pageId</code>, <code>onEdit(type, b =&gt; …)</code> („Bearbeiten“ eines eigenen Blocktyps übernehmen, z. B. eigener Dialog statt Seitenleiste); <code>b</code> = <code>{id, type, index, data, label, update(data), replace(type, data)}</code> – <code>replace</code> behält die Abschnitts-Einstellungen. Skript über <code>toolbar()</code> laden; bereit nach <code>cms:editor-ready</code>. Blockdefinition (<code>blocks()</code>): <code>'sanitize' =&gt; fn(array $data): array</code> prüft die Daten selbst statt nach dem Feldschema (Ausnahme = Block entfällt), <code>'listed' =&gt; false</code> zeigt den Typ nur über Vorlagen (<code>blockPresets</code>). Vorschau im Editor (Seitenstart und je Block): <code>Theme::previewBlock()</code>.</td></tr>
    <tr><td>Mediathek</td><td><code>mediaChecks()</code>, <code>mediaJson()</code>, <code>mediaTypes()</code>, <code>mediaPoster()</code> (+ im Browser <code>CMSMedia.extend()</code>)</td><td>siehe Kapitel <a href="#medien">Medien</a>.</td></tr>
    <tr><td>Ereignisse</td><td><code>on(PageSaved::class, …)</code></td><td><a href="#erweiterungen-hooks">Typisierte Ereignisse</a> (<code>Core\Events\*</code>) für Seiten, Einträge, Formulare, Konten, Einstellungen und Tabellen; die Namen <code>'page.saved'</code> … bleiben als Alias. Nur Mitteilung <b>danach</b>.</td></tr>
    <tr><td>E-Mail</td><td><code>mailPurpose()</code>, <code>mailTransport()</code></td><td><a href="#erweiterungen-mail">Eigene Zwecke und Versandwege</a> für E-Mail-Konten (<code>Core\Mail\Accounts</code>) – z. B. eine Newsletter-Erweiterung mit eigenem Konto oder der API eines Dienstes.</td></tr>
    <tr><td>Filter</td><td><code>filter('seo.meta', …)</code></td><td><a href="#erweiterungen-filter">Werte an festen Stellen ändern</a> (<code>Core\Filters::ALL</code>): Suche, Sitemap, Menü, SEO, Formular-Prüfung, E-Mails, Datenliste – Ergebnis wirkt.</td></tr>
    <tr><td>Betrieb</td><td><code>command()</code>, Manifest <code>'schedule'</code> (geplante Aufgaben), <code>health()</code>, <code>afterAdminResponse()</code>, <code>proxy()</code>, <code>docs()</code>, <code>inbox()</code>, <code>pushEvent()</code> (+ <code>Core\Push\Push::notifyUsers()</code>, siehe <a href="#push">Push-Benachrichtigungen</a>)</td><td>Fehler einer Erweiterung werden protokolliert und brechen die Anfrage nicht ab. Wiederkehrende Arbeiten nie als eigenen Cronjob dokumentieren, sondern im Manifest anmelden: <code>'schedule' =&gt; ['purge' =&gt; ['label' =&gt; 'Alte Einträge löschen', 'command' =&gt; 'xyz:purge', 'cron' =&gt; '10 4 * * *', 'scope' =&gt; 'site'|'installation', 'timeout' =&gt; 600]]</code> – der Befehl läuft über den einen Cronjob der Installation mit <code>--site=…</code> je Website, auf der die Erweiterung aktiv ist (<code>scope</code> <code>installation</code>: einmal). Sichtbar und schaltbar unter Grundeinstellungen → Geplante Aufgaben (<a href="#cron">Betrieb</a>).</td></tr>
  </table>
  <p><b>Regeln:</b></p>
  <ul>
    <li><b>Keine Eingriffe am Core vorbei:</b> keine Core-Dateien ändern oder überschreiben, keine Ausgabe-Puffer auf Verwaltungsseiten, keine eigenen <code>&lt;script&gt;</code> außer über <code>adminAssets()</code>/<code>toolbar()</code>, kein direktes Schreiben in Core-Tabellen (<code>pages</code>, <code>media</code>, <code>settings</code>, Datentabellen) – dafür gibt es <code>Pages</code>, <code>Entries</code>, <code>Media</code>, <code>app()-&gt;settings</code>.</li>
    <li><b>Escapen macht der Core:</b> Slots geben Beschriftungen, Werte und Pfade zurück, kein HTML. Alte Rückgaben als HTML (<code>pagePanel()</code>, Karten der Übersicht mit <code>render</code>) laufen weiter, gelten aber als Altform.</li>
    <li><b>Rechte doppelt:</b> Die Angabe <code>perm</code> steuert Sichtbarkeit und Route; Endpunkte, die Daten ändern, prüfen zusätzlich fachlich (z. B. Recht je Tabelle).</li>
    <li><b>Formatieren mit <code>Core\Format</code></b> (Datum, Zahl, Größe, Dauer …) statt eigener Helfer – siehe <a href="#format">Format</a>.</li>
    <li><b>Eigene Pakete:</b> Jede Erweiterung lebt in einem eigenen Repository und wird per Composer installiert (Typ <code>klxm-studio-extension</code>); interne Erweiterungen gehören nicht ins öffentliche Core-Repository.</li>
  </ul>

  <div class="doc-note doc-note--info"><strong>Farben und Formen der Website: nur <code>--kit-*</code></strong><p>Blöcke und Seiten einer Erweiterung lesen die Grundwerte des Kits ausschließlich über den <a href="#kit-vertrag">Kit-Vertrag</a> (<code>--kit-accent</code>, <code>--kit-muted</code>, <code>--kit-line</code>, <code>--kit-radius</code> …), immer mit Rückfall (<code>var(--kit-accent,currentColor)</code>) – keine Ketten aus Kit-Präfixen wie <code>--b-a</code> oder <code>--f-a</code>. Eigene Variablen mit dem Präfix der Erweiterung (z. B. <code>--bk-*</code>) leiten sich davon ab, damit Kits gezielt nachsteuern können.</p></div>
  <h3 id="erweiterungen-seiten">Verwaltungsseiten: Art und Ort (<code>Core\AdminPages</code>)</h3>
  <p>Jede Seite, die eine Funktion oder Erweiterung in der Verwaltung anmeldet, hat eine <b>Art</b>. Die Art bestimmt den Ort – das Backend bleibt aufgeräumt:</p>
  <table class="doc-table">
    <tr><th>Art (<code>kind</code>)</th><th>Wofür</th><th>Wo sie erscheint</th></tr>
    <tr><td><code>content</code></td><td>arbeitet mit Inhalten (Einträge, Buchungen, Termine …)</td><td>Hauptmenü (<code>place =&gt; 'main'</code>) bzw. Administration (<code>'admin'</code>)</td></tr>
    <tr><td><code>tool</code></td><td>Werkzeug, Arbeitsablauf (prüfen, importieren, Video bearbeiten …)</td><td>wie <code>content</code>, meist <code>place =&gt; 'admin'</code></td></tr>
    <tr><td><code>settings</code></td><td>reine Konfiguration</td><td><b>nicht im Menü</b>: Sammelseite <b>Einrichtung › System › Einstellungen der Funktionen</b> (<code>/admin/einstellungen</code>, eine Karte je Seite, gruppiert nach Funktion/Erweiterung). Mit <code>'table' =&gt; 'handle'</code> zusätzlich an der Datentabelle (Knopf im Kopf der Liste, Unterpunkt in der Daten-Navigation)</td></tr>
    <tr><td><code>stats</code></td><td>Berichte, Statistiken</td><td><b>nicht im Menü</b>: Sammelseite <b>Administration → Statistiken</b> (<code>/admin/statistiken</code>) – der Menüpunkt erscheint erst, wenn es mindestens eine sichtbare Seite gibt</td></tr>
  </table>
  <pre><code>// extension.php → 'boot' =&gt; function (Core\Extension $x) { … }
$x-&gt;adminPage(['href' =&gt; '/admin/buchungen', 'label' =&gt; 'Buchungen', 'kind' =&gt; 'content', 'icon' =&gt; 'calendar-check', 'perm' =&gt; 'booking.manage']);
$x-&gt;adminPage(['href' =&gt; '/admin/consent', 'label' =&gt; 'Cookie-Einwilligung', 'kind' =&gt; 'settings', 'icon' =&gt; 'cookie',
    'perm' =&gt; 'consent.manage', 'description' =&gt; 'Dienste, Texte und Darstellung des Cookie-Banners']);
$x-&gt;adminPage(['href' =&gt; '/admin/shop/einstellungen', 'label' =&gt; 'Shop', 'kind' =&gt; 'settings', 'table' =&gt; 'produkte',
    'tableLabel' =&gt; 'Shop-Einstellungen', 'perm' =&gt; 'shop.edit']);          // auch als Knopf an der Tabelle „produkte“
$x-&gt;adminPage(['href' =&gt; '/admin/matomo', 'label' =&gt; 'Besucher', 'kind' =&gt; 'stats', 'icon' =&gt; 'chart-line-up',
    'visible' =&gt; fn() =&gt; can('stats.view') &amp;&amp; Matomo::configured()]);
// Kurzform (gleiche Wirkung): nav($href, $label, $icon, $perm, $kindOderPlatz, $weitereAngaben)
$x-&gt;nav('/admin/consent', 'Cookie-Einwilligung', 'cookie', 'consent.manage', 'settings', ['description' =&gt; '…']);</code></pre>
  <table class="doc-table">
    <tr><th>Angabe</th><th>Bedeutung</th></tr>
    <tr><td><code>href</code>*, <code>label</code>*</td><td>Adresse in dieser Installation (beginnt mit <code>/</code>) und Beschriftung (übersetzbar über <code>lang/{locale}.php</code> der Erweiterung). Die Route meldet die Erweiterung mit <code>routes()</code> an – mit Recht (<a href="#erweiterungen-routen">geschützte Verwaltungsrouten</a>).</td></tr>
    <tr><td><code>kind</code></td><td><code>content</code> | <code>tool</code> | <code>settings</code> | <code>stats</code> (siehe oben).</td></tr>
    <tr><td><code>place</code></td><td><code>main</code> (Hauptmenü, Standard) oder <code>admin</code> (Abschnitt Administration) – nur für <code>content</code>/<code>tool</code>.</td></tr>
    <tr><td><code>icon</code>, <code>description</code></td><td>Symbolname bzw. Menü-Schlüssel (<code>Core\Icons</code>) und eine Zeile für die Karte auf der Sammelseite.</td></tr>
    <tr><td><code>perm</code>, <code>feature</code>, <code>visible</code></td><td>Sichtbar nur mit Recht (<code>can()</code>), wenn die Funktion an ist (unbekannte Funktion = aus) und <code>visible</code> (<code>fn(): bool</code>) <code>true</code> liefert. Gilt für Menü, Karten, Knöpfe an Tabellen und die Suche (<kbd>⌘K</kbd>).</td></tr>
    <tr><td><code>table</code>, <code>tableLabel</code></td><td>Nur <code>settings</code>: Kurzname einer Datentabelle – die Seite erscheint zusätzlich dort (Beschriftung <code>tableLabel</code>, sonst <code>label</code>).</td></tr>
  </table>
  <div class="doc-note doc-note--warn"><strong>Veraltet: <code>nav()</code> ohne Art</strong><p>Ältere Aufrufe <code>$x-&gt;nav($href, $label, $icon, $perm)</code> bzw. <code>…, 'admin')</code> funktionieren weiter und bleiben an ihrem Platz (<code>main</code> → <code>content</code>, <code>admin</code> → <code>tool</code>). Bitte die Art angeben – reine Einstellungsseiten gehören nach <code>settings</code>, Berichte nach <code>stats</code>. <code>Extensions::adminNav()</code> ist veraltet; das Layout nutzt <code>AdminPages::nav()</code>.</p></div>
  <p><b>Einordnung im Core:</b> Hauptmenü = Übersicht, Seiten, Entwürfe, Website-Angaben des Kits, Medien, Daten, Anfragen, Chat, KI (Inhalte/Werkzeuge); Administration = Grundeinstellungen, Funktionen &amp; Erweiterungen, <b>Einstellungen</b> (Sammelseite), <b>Statistiken</b> (nur wenn vorhanden), Design, Blöcke, Landingpages, Weiterleitungen, Benutzer &amp; Rollen. Als <code>settings</code> gesammelt: <b>Glossar</b> (<code>/admin/glossar</code>, an der Tabelle <code>glossar</code> als „Prüfen &amp; Einstellungen“), <b>Chat</b> (<code>/admin/chat/einstellungen</code>), <b>API &amp; MCP</b> (<code>/admin/api-tokens</code>). Externe Quellen sind ein Werkzeug der Daten-Navigation. Adressen und Rechte sind unverändert.</p>
  <p>API: <code>AdminPages::register([...])</code> (Core), <code>all()</code>, <code>visible($p)</code>, <code>ofKind('settings', …)</code>, <code>nav('main'|'admin')</code>, <code>settings()</code> / <code>stats()</code> (gruppiert), <code>forTable($handle)</code>, <code>match($path)</code>. Selbsttest: <code>php bin/console extensions:selftest</code>.</p>

  <p><b>Favoriten-Stern auf ganzseitigen Ansichten:</b> Der Stern (<code>resources/js/_favorites.js</code>) steht neben der ersten sichtbaren <code>&lt;h1&gt;</code>. App-Ansichten ohne Überschrift, die die volle Höhe nutzen (Mediathek, z. B. ein Feedback-Eingang), markieren ihre Werkzeugleiste mit <code>data-fav-slot</code> – der Stern sitzt dann dort statt schwebend über der oberen rechten Ecke. Wert = zusätzliche Klassen für den Knopf (z. B. <code>data-fav-slot="my-tbtn"</code>, damit er wie die übrigen Knöpfe der Leiste aussieht), ein Kind mit <code>data-fav-before</code> legt die Stelle fest (sonst am Ende). Baut ein Skript die Leiste erst nach dem Laden auf, wandert der Stern hinein, sobald sie erscheint.</p>
  <pre><code>&lt;header class="my-bar" data-fav-slot="my-tbtn"&gt;
  &lt;strong&gt;Eingang&lt;/strong&gt; … &lt;button class="my-tbtn" data-fav-before&gt;Filter&lt;/button&gt;
&lt;/header&gt;</code></pre>
  <p><b>Große Formulare:</b> POST-Formulare der Verwaltung mit mehr als der halben Grenze <code>max_input_vars</code> (meist 1000) sendet <code>admin.js</code> gebündelt in einem Feld <code>_packed</code>; <code>Core\Http\Request</code> baut daraus wieder die übliche Struktur – Controller merken davon nichts. Kommen ohne JavaScript zu viele Felder an, lehnt der Core das Speichern mit einer Meldung ab (<code>Request::$truncated</code>), statt abgeschnittene Daten zu speichern. Ausnahme je Formular: <code>data-no-pack</code>.</p>

  <h3 id="erweiterungen-routen">Routen: Verwaltung geschützt ab Werk (<code>Core\Http\Router</code>)</h3>
  <p>Routen, die eine Erweiterung mit <code>routes()</code> unter <code>/admin</code> anmeldet, schützt der Core, <b>bevor</b> der Handler läuft: Anmeldung (sonst Weiterleitung zur Anmeldung), Zwei-Faktor-Einrichtung, das angegebene <b>Recht</b> (sonst 403) und bei allen Methoden außer GET/HEAD/OPTIONS das <b>CSRF-Token</b> (Feld <code>_csrf</code> oder Kopfzeile <code>X-CSRF-Token</code>, sonst 419). Jede Verwaltungsroute braucht ein Recht.</p>
  <pre><code>$x-&gt;routes(function (Core\Http\Router $r): void {
    $c = KalenderController::class;
    $r-&gt;get('/admin/kalender', [$c, 'index'], 'calendar.edit');                      // Recht als dritte Angabe
    $r-&gt;post('/admin/kalender/{id}', [$c, 'save'], ['perm' =&gt; 'calendar.edit']);      // + CSRF
    $r-&gt;get('/admin/kalender/notiz', [$c, 'note'], ['perm' =&gt; ['calendar.edit', 'calendar.note']]);   // eines der Rechte genügt
    $r-&gt;post('/admin/kalender/webhook', [$c, 'hook'], ['perm' =&gt; 'calendar.edit', 'csrf' =&gt; false]);   // Ausnahme, benannt
    $r-&gt;get('/admin/kalender/status.json', [$c, 'status'], ['public' =&gt; true]);       // Ausnahme: ohne Anmeldung
    $r-&gt;get('/kalender.ics', [$c, 'feed']);                                           // Website: Sache der Erweiterung
});</code></pre>
  <table class="doc-table">
    <tr><th>Fall</th><th>Verhalten</th></tr>
    <tr><td>mit Recht</td><td>Anmeldung, Recht, CSRF (Nicht-GET) – der Handler kann sich auf alles verlassen. Fachliche Prüfungen (Recht je Tabelle, eigene Einträge) bleiben Sache des Handlers.</td></tr>
    <tr><td>mehrere Rechte (Liste)</td><td><code>['perm' =&gt; ['a', 'b']]</code>: eines davon genügt – für Seiten, die zwei Gruppen nutzen (z. B. Redaktion mit <code>pages.edit</code> und Prüfende nur mit <code>feedback.write</code>). <code>extensions:list</code> zeigt <code>a|b</code>. Ältere Cores kennen die Liste nicht: Controller auf Basis von <code>AdminController</code> laufen dort als Altform (prüfen selbst), andere Handler werden abgelehnt.</td></tr>
    <tr><td><code>'csrf' =&gt; false</code>, <code>'public' =&gt; true</code></td><td>Benannte Ausnahmen – <code>php bin/console extensions:list</code> listet sie je Erweiterung. <code>public</code> verzichtet auf Anmeldung und Recht, CSRF gilt weiter (außer zusätzlich <code>'csrf' =&gt; false</code>).</td></tr>
    <tr><td>ohne Recht, Handler <code>[Controller, 'methode']</code> auf Basis von <code>AdminController</code></td><td><b>Altform</b> (alle Erweiterungen vor dieser Regel): läuft weiter – der Controller prüft das Recht wie bisher mit <code>$this-&gt;auth($r, 'recht')</code>, der Core prüft zusätzlich Anmeldung und CSRF. <code>extensions:list</code> meldet diese Routen; bitte das Recht an der Route angeben.</td></tr>
    <tr><td>ohne Recht, anderer Handler (Closure …)</td><td>Entwicklung (<code>'environment' =&gt; 'development'</code> oder <code>debug</code>): <code>LogicException</code> beim Anmelden. Produktion: Route antwortet mit 403 und schreibt ins Fehlerprotokoll.</td></tr>
  </table>
  <p>Routen des Cores (<code>app/routes.php</code>) sind davon nicht betroffen – ihre Controller prüfen mit <code>auth()</code>. <code>AdminController::routeGuard($r, $perm, $csrf)</code> ist dieselbe Prüfung ohne Nebenarbeiten. Selbsttest: <code>extensions:selftest</code> (Abschnitt Verwaltungsrouten).</p>

  <h3 id="erweiterungen-schema">Tabellen deklarativ (<code>Core\Db\Table</code>)</h3>
  <p>Nach dem Vorbild von <code>rex_sql_table</code>: eine Tabelle beschreiben statt <code>CREATE</code>/<code>ALTER</code> je Datenbank zu schreiben. <code>ensure()</code> legt an bzw. gleicht an – beliebig oft, gleiches Ergebnis (idempotent). Unter der Haube Doctrine DBAL (wie bei den Datentabellen) auf <b>derselben PDO-Verbindung</b> wie <code>app()-&gt;db</code> – also auch in einer offenen Transaktion, ohne zweite Sperre bei SQLite. SQLite und MySQL/MariaDB.</p>
  <pre><code>use Core\Db\Table;

// extension.php → boot: angeglichen beim Start (nur wenn sich die Beschreibung geändert hat) und bei jedem `migrate`
$x-&gt;table('kalender_termine', fn(Table $t) =&gt; $t
    -&gt;id()                                                         // INTEGER, Primärschlüssel, Autoincrement
    -&gt;column('titel', 'string', ['length' =&gt; 120, 'null' =&gt; false, 'default' =&gt; ''])
    -&gt;column('status', 'string', ['length' =&gt; 12, 'default' =&gt; 'neu'])
    -&gt;column('beginn', 'datetime')                                // VARCHAR(25) „Y-m-d H:i:s“ wie im Core
    -&gt;column('media_id', 'int')
    -&gt;index(['status', 'beginn'])                                 // Name: kalender_termine_status_beginn
    -&gt;unique('titel')
    -&gt;foreignKey('media_id', 'media', 'id', 'SET NULL')
    -&gt;renameColumn('title', 'titel'));                            // ausdrücklich, Daten bleiben

// direkt (Kommandozeile, neue Tabellen des Cores): Rückgabe 'created' | 'altered' | 'unchanged'
Table::named('kalender_log')-&gt;id()-&gt;column('text', 'text')-&gt;ensure();</code></pre>
  <table class="doc-table">
    <tr><th>Angabe</th><th>Bedeutung</th></tr>
    <tr><td>Typen</td><td><code>id</code>, <code>int</code>, <code>bigint</code>, <code>string</code> (Länge 191), <code>text</code>, <code>longtext</code>, <code>bool</code>, <code>float</code>, <code>decimal</code> (<code>precision</code>/<code>scale</code>), <code>datetime</code>, <code>date</code>, <code>json</code> (TEXT).</td></tr>
    <tr><td>Optionen</td><td><code>null</code> (Standard <code>true</code>), <code>default</code>, <code>length</code>, <code>precision</code>, <code>scale</code>, <code>unsigned</code>.</td></tr>
    <tr><td>Additiv</td><td>Nicht beschriebene Spalten und Indizes bleiben. Entfernt wird nur mit <code>dropColumn()</code> bzw. <code>dropIndex()</code>. Der Primärschlüssel wird nur beim Anlegen gesetzt.</td></tr>
    <tr><td>NOT NULL</td><td>Neue Spalte in einer gefüllten Tabelle wird ohne <code>default</code> nullable angelegt; eine bestehende nullable Spalte wird nur mit <code>default</code> zu NOT NULL (leere Werte werden damit gefüllt).</td></tr>
    <tr><td>Umbenennen</td><td><code>renameColumn('alt', 'neu')</code> – nur wenn <code>alt</code> da und <code>neu</code> noch nicht da ist; Indizes über die Spalte bitte mit neuem Namen beschreiben und den alten mit <code>dropIndex()</code> entfernen.</td></tr>
    <tr><td>Fremdschlüssel</td><td>Nur zwischen Tabellen derselben Datenbank (nicht zu geteilten Tabellen oder Pools). SQLite: <code>PRAGMA foreign_keys = ON</code> setzt <code>Core\Database</code>; das Ergänzen an einer bestehenden Tabelle baut sie neu auf (DBAL).</td></tr>
  </table>
  <p><code>table()</code> ersetzt handgeschriebenes SQL (<code>$pk</code>/<code>$long</code> je Datenbank) für <b>neue</b> Tabellen; einmalige Datenschritte (Werte umstellen, kopieren) bleiben <code>migration()</code>. Bestehende Migrationen müssen nicht umgeschrieben werden. Selbsttest: <code>php bin/console db:selftest</code> (Wegwerf-Datenbank: anlegen, idempotent, ergänzen, umbenennen, Indizes, Fremdschlüssel, Transaktion).</p>

  <h3 id="erweiterungen-slots">Slots der Verwaltung (<code>Core\Slots</code>)</h3>
  <p>Erweiterungen hängen sich nur an diesen sechs Stellen in Seiten des Cores ein. Jeder Slot hat genau eine Manifest-Methode, nimmt als zweites Argument optional ein <b>Recht</b> (ohne <code>can($perm)</code> erscheint nichts) und liefert <b>Daten</b> – der Core rendert und escaped. Fehler einer Erweiterung landen im Fehlerprotokoll, die Seite bleibt stehen. Reihenfolge: wie die Erweiterungen starten.</p>
  <table class="doc-table">
    <tr><th>Slot</th><th>Methode</th><th>Wo</th><th>Rückgabe</th></tr>
<?php foreach (\Core\Slots::ALL as $__slot => [$__m, $__where, $__ret]): ?>
    <tr><td><code><?= e($__slot) ?></code></td><td><code><?= e($__m) ?>(fn, ?perm)</code></td><td><?= e($__where) ?></td><td><?= $__ret === 'Karte' ? 'Karte (siehe unten)' : '<code>' . e($__ret) . '</code>' ?></td></tr>
<?php endforeach; ?>
  </table>
  <p><b>Karte</b> (gleicher Vertrag für <code>pagePanel</code>, <code>mediaPanel</code>, <code>account</code> und <code>'body'</code> einer Karte der Übersicht): <code>['title' =&gt; …, 'text' =&gt; …, 'tone' =&gt; 'ok'|'warn'|'info'|'muted', 'lines' =&gt; ['Bezeichnung' =&gt; 'Wert'], 'actions' =&gt; [['label' =&gt; …, 'href' =&gt; '/admin/…', 'primary' =&gt; true]]]</code> – alles Klartext. Aktionen nur als Pfad dieser Installation (beginnt mit <code>/</code>, kein <code>//</code>, kein <code>javascript:</code>); sie öffnen Seiten der Erweiterung (deren Routen sind <a href="#erweiterungen-routen">geschützt</a>).</p>
  <pre><code>$x-&gt;pagePanel(fn(array $page) =&gt; [
    'title' =&gt; 'Feedback &amp; Freigabe', 'text' =&gt; Feedback::sentence($page['id']), 'tone' =&gt; 'ok',
    'lines' =&gt; ['Offene Rückmeldungen' =&gt; (string) Feedback::open($page['id'])],
    'actions' =&gt; [['label' =&gt; 'Feedback ansehen', 'href' =&gt; '/admin/feedback/' . $page['id'], 'primary' =&gt; true]],
], 'feedback.view');
$x-&gt;tableActions(fn(array $t) =&gt; $t['handle'] !== 'news' ? null : [
    'actions' =&gt; [['label' =&gt; 'Newsletter', 'href' =&gt; '/admin/newsletter/{table}', 'icon' =&gt; 'envelope']],   // Kopf der Liste
    'row'     =&gt; [['label' =&gt; 'Versenden', 'href' =&gt; '/admin/newsletter/{table}/{id}']],                       // je Eintrag
], 'newsletter.send');
$x-&gt;mediaPanel(fn(array $m) =&gt; ($o = Origin::of($m)) ? ['title' =&gt; 'Herkunft', 'lines' =&gt; ['Quelle' =&gt; $o['label']]] : null, 'media.upload');
$x-&gt;account(fn(array $user) =&gt; ['title' =&gt; 'Kalender-Abo', 'text' =&gt; 'Persönlicher Link für Ihre Kalender-App.',
    'actions' =&gt; [['label' =&gt; 'Link anzeigen', 'href' =&gt; '/admin/kalender/abo']]]);
$x-&gt;dashboard(fn(array $user) =&gt; ['cards' =&gt; ['offen' =&gt; ['title' =&gt; 'Buchungen', 'icon' =&gt; 'calendar-check',
    'body' =&gt; ['lines' =&gt; ['Heute' =&gt; '4', 'Offen' =&gt; '2'], 'actions' =&gt; [['label' =&gt; 'Öffnen', 'href' =&gt; '/admin/buchungen']]]]]]);</code></pre>
  <ul>
    <li><b>Seitenbaum</b> (<code>pageList</code>): <code>badges</code> = Symbole in der Spalte „Hinweise“ (<code>label</code> + <code>title</code> als Tooltip, <code>tone</code> ok|warn|info|muted bestimmt Farbe und Standardsymbol, optional <code>icon</code> – Name eines Kern-Symbols, z. B. <code>lock</code>), <code>actions</code> = Einträge im Kontextmenü. Läuft für jede Seite – Daten einmal vorab laden.</li>
    <li><b>Datentabelle:</b> eigene <i>Seiten</i> an einer Tabelle sind <code>adminPage(['kind' =&gt; 'settings', 'table' =&gt; …])</code> (Knopf im Kopf und Unterpunkt der Daten-Navigation); <code>tableActions()</code> ergänzt Knöpfe und eine Aktion je Zeile (Platzhalter <code>{table}</code>, <code>{id}</code>).</li>
    <li><b>Mediathek:</b> <code>mediaPanel()</code> für einen Abschnitt aus Daten; für Bedienelemente mit eigenem Skript bleibt <code>CMSMedia.extend({ panel, menu, … })</code> (siehe <a href="#medien">Medien</a>) und <code>mediaJson()</code> für Zusatzangaben.</li>
    <li><b>Altform:</b> <code>pagePanel()</code> darf weiter fertiges, selbst escaptes HTML als Zeichenkette liefern, Karten der Übersicht weiter <code>'render' =&gt; fn(): string</code>. Neue Slots (<code>tableActions</code>, <code>mediaPanel</code>, <code>account</code>) nehmen nur Daten.</li>
    <li><b>Nicht vorgesehen</b> (bewusst): eigene Spalten in fremden Tabellen, eigene Felder im Seiten-Editor außerhalb von Blöcken, Eingriffe ins Menü außer <code>adminPage()</code>, Ersetzen von Core-Ansichten. Bedarf → Vorschlag für einen neuen Slot im Core.</li>
  </ul>
  <p>Selbsttest: <code>extensions:selftest</code> (Abschnitt Slots: Methoden, Escapen, Pfade, Platzhalter, Rechte, Altform, Fehler).</p>

  <h3 id="erweiterungen-werkzeuge">Werkzeuge beim Bearbeiten auf der Website (<code>Core\FrontendTools</code>)</h3>
  <p>Ein Werkzeug ist ein Knopf in der Werkzeugleiste (<code>placement =&gt; 'main'</code>, auf Telefonen im Menü „⋯“) oder ein Eintrag im Menü „⋯“ (<code>'more'</code>), optional mit Tastenkürzel. Das Menü hat ab 1024 px zwei Spalten: „Diese Seite“ (Standard) und allgemein – mit <code>'scope' =&gt; 'site'</code> steht das Werkzeug in der allgemeinen Spalte (gilt für die ganze Website, z. B. Design). Es erscheint <b>nur angemeldet</b> und nur mit Recht – standardmäßig <b>nur im Bearbeiten-Modus</b> (Seiten-Editor inkl. Vorlage: <code>page</code>; Eintrag direkt im Text: <code>entry</code>, dort erst nach „Bearbeiten“). Mit <code>'view' =&gt; true</code> (bzw. <code>'view'</code> in <code>modes</code>) erscheint es zusätzlich beim <b>Ansehen</b> (Seite ohne <code>?edit=1</code>, auch Live-Fassung; Detailseite eines Eintrags vor „Bearbeiten“) – dort ist jeder Text der Seite markierbar. Das ES-Modul lädt der Browser <b>erst beim ersten Öffnen</b> – Besucher laden nie etwas.</p>
  <pre><code>$x-&gt;frontendTool([
    'id' =&gt; 'notiz', 'label' =&gt; 'Notiz', 'icon' =&gt; 'note-pencil', 'placement' =&gt; 'main', 'shortcut' =&gt; 'Alt+N',
    'hint' =&gt; 'Kurze Notiz an der Schreibmarke',             // kleine Zeile im Menü „⋯“
    'module' =&gt; 'js/notiz.mjs',                              // {dir}/assets/js/notiz.mjs → /assets/ext/{name}/js/notiz.mjs
    'perm' =&gt; 'pages.edit', 'feature' =&gt; 'notiz',             // optional 'table' (Recht je Tabelle), 'visible' =&gt; fn(array $bar): bool
    'modes' =&gt; ['page', 'entry'],                      // Standard; 'view' =&gt; true = zusätzlich beim Ansehen
    'view' =&gt; true, 'chip' =&gt; 'Als Notiz',             // optional: schwebender Knopf neben markiertem Text (nur Ansehen)
    'panel' =&gt; ['title' =&gt; 'Notizen', 'size' =&gt; 'narrow'],    // narrow | wide | modal | drawer
    'endpoints' =&gt; ['list' =&gt; '/admin/api/notiz'],          // eigene Routen – prüfen Recht + CSRF bei JEDEM Aufruf
    'data' =&gt; fn(array $bar) =&gt; ['max' =&gt; 200],              // je Werkzeugleiste, frei für das Modul
    'texts' =&gt; ['insert' =&gt; __('Einfügen')],                 // übersetzte Texte (auf der Website gibt es kein Wörterbuch)
]);</code></pre>
  <p><b>Neue Seite (Core):</b> <code>Core\PageTool::definition()</code> – Knopf in der Werkzeugleiste (<code>Alt+N</code>, <code>perm</code> <code>pages.manage</code>, auch beim Ansehen), <code>'panel' =&gt; ['size' =&gt; 'modal']</code> (Dialog in der Mitte, abgedunkelter Hintergrund, Tab bleibt im Dialog), Modul <code>resources/js/new-page.mjs</code>, Endpunkte <code>GET /admin/api/pages/tree</code> und <code>POST /admin/api/pages/create</code> (<code>PageController::apiTree/apiCreate</code>, gleiche Prüfungen wie das Formular).</p>
  <p><b>Design (Core):</b> <code>Core\DesignTool::definition()</code> – Eintrag „Design“ im Menü „⋯“, allgemeine Spalte (<code>placement</code> <code>more</code>, <code>scope</code> <code>site</code>; <code>perm</code> <code>design.edit</code>, <code>feature</code> <code>design</code>, beim Ansehen und Bearbeiten, nur wenn das Kit Design-Tokens hat), <code>'panel' =&gt; ['size' =&gt; 'drawer']</code>: Seitenleiste in voller Höhe rechts, die Website rückt per <code>body.has-tdrawer</code> (editor.css, Breite <code>--cms-tdrawer-w</code>) nach links und ihre Haltepunkte folgen der neuen Breite (<code>resources/js/_cq.js</code>, auch beim Ansehen); Telefone: Blatt unten. Modul <code>resources/js/design-live.mjs</code>, Endpunkte <code>GET /admin/api/design-live</code> (Schema übersetzt, Werte, Standardwerte, Markup-Tokens, Live-Stand der gespeicherten Werte) und <code>POST /admin/api/design-live</code> mit <code>{"v": {…}}</code> bzw. <code>{"v": {…}, "save": 1}</code> (<code>DesignController::live/liveApply</code>, Recht <code>design.edit</code> + CSRF wie Verwaltung → Design; Speichern über <code>Design::save()</code> mit Verlauf, Schriften, Seiten-Cache). Antwort = <code>Design::live()</code>: <code>css</code> (Variablen hell + dunkel, davor <code>@font-face</code> noch nicht installierter Kit-Schriften), <code>classes</code> (<code>&lt;html&gt;</code>-Klassen aus <code>{kit}_html_class()</code>, sonst <code>design_classes()</code>), <code>fonts</code>, <code>opt</code> (<code>Design::optionCss()</code>), <code>checks</code> (nicht bestandene Kontrastprüfungen). Im Browser: Klassen tauschen (gemerkt in <code>html[data-design-classes]</code>), Variablen per <code>document.adoptedStyleSheets</code> (CSSOM, CSP-konform), Schrift-<code>&lt;link&gt;</code> ergänzen, <code>link[data-design-opt]</code> austauschen (im Bearbeiten-Modus über <code>convertSheet()</code>/<code>dropSheet()</code> aus <code>_cq.js</code>). 120 ms gebündelt, die jüngste Antwort gewinnt. Für Kits: siehe <a href="#design">Design → Live auf der Website</a>.</p>
  <p><b>Seiteneinstellungen (Core):</b> <code>Core\PageSettingsTool::definition()</code> – Eintrag im Menü „⋯“ (<code>placement</code> <code>more</code>,
    <code>perm</code> <code>pages.manage</code>, auch beim Ansehen, nur auf Seiten – <code>visible</code> über <code>PageSettingsTool::applies()</code>; ersetzt dort den
    Link in die Verwaltung), Modal wie „Neue Seite“, Modul <code>resources/js/page-settings.mjs</code>. Endpunkte <code>GET</code> und <code>POST /admin/api/pages/{id}/settings</code>
    (<code>PageController::apiSettings/apiSettingsSave</code>, JSON: <code>title</code>, <code>slug</code>, <code>meta_title</code>, <code>meta_description</code>,
    <code>og_image</code>, <code>status</code>, <code>menu</code>, <code>nav_title</code>, <code>noindex</code> – nur übergebene Felder ändern sich). Gespeichert wird über
    <code>PageController::saveSettings()</code>, denselben Weg wie das Formular der Verwaltung (Prüfungen, Startseite/404-Sonderfälle, Platzhalter-Sperre,
    Status nur mit <code>pages.publish</code>, automatische Weiterleitung bei neuer Adresse, Seiten-Cache). Die Antwort liefert die neuen Werte, die Adresse und
    <code>seo</code> (Titel, Beschreibung, Vorschaubild, Canonical) – das Modul führt damit <code>&lt;title&gt;</code> und Meta-Angaben ohne Neuladen nach.
    Im Editor fragt es dafür <code>window.CMSEditor.isDirty()</code>.</p>
  <p><b>Quick-Glossar (Core, Referenz):</b> <code>Core\Glossary\QuickTool::definition()</code> liefert genau diese Angaben – <code>id</code> <code>glossary</code>, Kürzel <code>Alt+G</code>, <code>feature</code> <code>glossary</code>, <code>visible</code> = Tabelle eingerichtet und <code>data.edit</code> auf <code>glossar</code>, <code>'view' =&gt; true</code> mit <code>chip</code> „Als Glossar-Begriff“, drei Endpunkte (<code>GlossaryController::api*</code>), Modul <code>resources/js/quick-glossary.mjs</code>.</p>
  <table class="doc-table">
    <tr><th>Modi</th><th>Wann</th><th>Hinweise</th></tr>
    <tr><td><code>page</code></td><td>Seiten-Editor (<code>?edit=1</code>) und Vorlagen-Editor</td><td>Standard</td></tr>
    <tr><td><code>entry</code></td><td>Detailseite eines Eintrags im Modus „Bearbeiten“ (Wechsel ohne Neuladen)</td><td>Standard</td></tr>
    <tr><td><code>view</code></td><td>Ansehen: Seite ohne <code>?edit=1</code> (auch <code>?live=1</code>), Detailseite vor „Bearbeiten“</td><td>nur mit <code>'view' =&gt; true</code>; <code>ctx.mode</code> = <code>'view'</code>, <code>insertText</code>/<code>insertLink</code> ohne Wirkung (<code>false</code>), <code>selection()</code> = markierter Text der Seite. Auf Detailseiten bekommt ein Werkzeug nur eines Modus <code>data-bar-when</code> (die Werkzeugleiste blendet es beim Wechsel aus); offene Werkzeuge des anderen Modus schließen.</td></tr>
  </table>
  <h4>Modul (JavaScript)</h4>
  <pre><code>// assets/js/notiz.mjs – ES-Modul, wird mit pnpm build gebaut
export default {
  async mount(ctx) {                     // beim ersten Öffnen; ctx.panel.body ist leer
    ctx.panel.body.innerHTML = `&lt;button type="button" class="adm-btn adm-btn--small" data-go&gt;${ctx.t("insert")}&lt;/button&gt;`;
    ctx.panel.body.querySelector('[data-go]').addEventListener('click', () =&gt; {
      if (!ctx.insertText('Notiz: ')) ctx.toast(ctx.t("noText"), 'error');
    });
    const res = await ctx.fetch(ctx.tool.endpoints.list, { query: { page: ctx.page?.id } });
    ctx.on('cms:saved', e =&gt; ctx.announce('Gespeichert: ' + e.detail.kind));
  },
  show(ctx) {},                          // optional: erneut geöffnet
  hide(ctx) {},                          // optional: geschlossen
  unmount(ctx) {},                       // optional: abgemeldet (Ereignisse aus ctx.on() meldet der Core ab)
};
// Alternative ohne default-Export: CMSAdmin.tools.register('notiz', { mount, unmount })</code></pre>
  <table class="doc-table">
    <tr><th><code>ctx</code></th><th>Inhalt</th></tr>
    <tr><td><code>id</code>, <code>tool</code></td><td>Kennung und Angaben vom Server (<code>tool.data</code>, <code>tool.texts</code>, <code>tool.endpoints</code> als fertige Adressen, <code>tool.shortcut</code>).</td></tr>
    <tr><td><code>page</code>, <code>entry</code>, <code>kind</code>, <code>mode</code>, <code>editKind</code>, <code>lang</code>, <code>csrf</code></td><td>Seite <code>{id, title, lang}</code> bzw. Eintrag <code>{table, id}</code>, Art der Werkzeugleiste (<code>page|entry|template</code>), <b>aktueller</b> Modus <code>'view'|'edit'</code> (Getter – auf Detailseiten wechselt er ohne Neuladen, Ereignis <code>cms:mode-change</code>), Bearbeiten-Art (<code>page|entry|null</code>), Sprache, CSRF-Token.</td></tr>
    <tr><td><code>panel</code></td><td><code>{ el, body, setTitle(t), close(), focus() }</code> – Seitenleiste in der Shadow-DOM-Ebene (<code>#cms-layer-host</code>, <code>role="dialog"</code>, nicht modal). Stile: <code>admin.shadow.css</code> + <code>editor.shadow.css</code> (Klassen <code>adm-btn</code>, <code>f</code>, <code>f-help</code> …); Kit-CSS wirkt nicht hinein.</td></tr>
    <tr><td><code>t(key, params)</code></td><td>Text aus <code>tool.texts</code> (sonst gemeinsame Texte), Platzhalter <code>{name}</code>.</td></tr>
    <tr><td><code>fetch(url, { method, json, query })</code></td><td>JSON-Anfrage mit <code>X-CSRF-Token</code> und Sitzung; wirft bei Fehler (<code>error.status</code>, <code>error.data</code>).</td></tr>
    <tr><td><code>selection()</code></td><td>Bearbeiten: gemerkte Stelle im zuletzt bearbeiteten Text <code>{ editable, range, text, rich }</code>. Ansehen: markierter Text irgendwo auf der Seite (außerhalb der CMS-Oberfläche) <code>{ editable: null, range, text, rich: false, view: true }</code> – gemerkt, <b>bevor</b> der Fokus in die Seitenleiste wechselt (Knopf, Kürzel, <code>chip</code>); ein Klick in die Seite beginnt neu. Sonst <code>null</code>.</td></tr>
    <tr><td><code>insertText(text)</code>, <code>insertLink({ href, ref, label, title, newTab })</code></td><td>Nur beim Bearbeiten (beim Ansehen ohne Wirkung, Ergebnis <code>false</code>). An der gemerkten Schreibmarke einfügen bzw. den markierten Text verlinken (gleiche Logik wie die Linkauswahl, <code>Rich.insertLink</code>; stabile Verweise <code>page:ID</code>, <code>entry:{tabelle}:{id}</code>, <code>media:{id}</code> in <code>data-link</code>). Felder ohne Links bekommen nur den Text. Ergebnis <code>'link'|'text'|false</code> (kein Text aktiv). Löst <code>input</code> aus – der Editor merkt die Änderung.</td></tr>
    <tr><td><code>focusText()</code>, <code>toast(msg, kind, ms, {title, action: {label, fn}, sticky})</code> (Fehler: rote Karte oben, mind. 12 s bzw. bis zum Schließen; ok/info unten – auch global als <code>CMSAdmin.toast</code>), <code>announce(msg)</code>, <code>on(event, fn)</code>, <code>close()</code></td><td>Zurück an die Schreibmarke, kurze Meldung (<code>ok|error</code>), Meldung für Screenreader (Live-Region der Werkzeugleiste), Ereignis am <code>document</code> abonnieren (bei <code>unmount</code> automatisch abgemeldet), schließen.</td></tr>
  </table>
  <p><b>Tastatur und Fokus:</b> Öffnen per Knopf oder Kürzel; der Fokus geht in die Seitenleiste (<code>[autofocus]</code>, sonst erstes Feld). <kbd>Esc</kbd> schließt und bringt den Fokus zurück an die Schreibmarke bzw. zum Knopf. Das Kürzel springt zwischen Text und offener Seitenleiste. Tasten in der Seitenleiste bleiben dort: Der Core hält sie von Editor.js und den Kürzeln der Seite fern (das echte Ereignis endet am <code>window</code>, eine nicht „composed“ Kopie erreicht das Ziel; Standardaktionen wie Tippen und Tab bleiben).</p>

  <h3 id="erweiterungen-ereignisse">Ereignisse im Browser</h3>
  <p><code>CustomEvent</code> am <code>document</code> – für Werkzeuge, Erweiterungs-Skripte (<code>toolbar()</code>-Skripte) und Kits. Senden: <code>CMSAdmin.events.emit(name, detail)</code>.</p>
  <table class="doc-table">
    <tr><th>Ereignis</th><th><code>detail</code></th><th>Wann</th></tr>
    <tr><td><code>cms:editor-ready</code></td><td><code>{ kind: 'page', page, template, entry }</code> bzw. <code>{ kind: 'entry', entry: {table, id}, fields: [...] }</code></td><td>Seiten-Editor bereit bzw. Eintrag in den Modus „Bearbeiten“ gewechselt</td></tr>
    <tr><td><code>cms:block-preview</code></td><td><code>{ type, id }</code> – Ziel ist die Vorschau des Blocks (<code>e.target</code>), Ereignis steigt bis <code>document</code> auf</td><td>Vorschau eines Blocks im Seiten-Editor ist (neu) gezeichnet – Kit-Skripte richten Videos, Animationen usw. darin ein (sie laufen sonst nur beim Laden der Seite)</td></tr>
    <tr><td><code>cms:block-select</code></td><td><code>{ kind: 'page', id, type, label, el }</code></td><td>ein anderer Block wird ausgewählt (Klick, Fokus)</td></tr>
    <tr><td><code>cms:before-save</code></td><td><code>{ kind, publish, page, blocks }</code> bzw. <code>{ kind: 'entry', status, entry, fields }</code> (<code>panel: true</code> aus der Seitenleiste „Alle Felder“) + <code>reason</code>, <code>waitUntil(promise)</code></td><td>vor dem Speichern/Veröffentlichen; <b>abbrechbar</b>: <code>event.preventDefault()</code> (optional <code>detail.reason = 'Text'</code>) oder <code>detail.waitUntil(promise)</code> mit Ergebnis <code>false</code> bzw. Fehler. Dann wird nicht gespeichert, die Änderungen bleiben.</td></tr>
    <tr><td><code>cms:saved</code></td><td><code>{ kind, page|entry, publish, status, savedAt }</code></td><td>nach erfolgreichem Speichern (auch beim Veröffentlichen)</td></tr>
    <tr><td><code>cms:published</code></td><td>wie <code>cms:saved</code></td><td>nach dem Veröffentlichen</td></tr>
    <tr><td><code>cms:status-changed</code></td><td><code>{ kind, status: 'published'|'offline'|'draft', via?, id?, page?, entry? }</code></td><td>Online/Offline über den Status-Chip, Veröffentlichen, Eintrag als Entwurf</td></tr>
    <tr><td><code>cms:tool-open</code>, <code>cms:tool-close</code></td><td><code>{ id }</code></td><td>Werkzeug geöffnet/geschlossen</td></tr>
    <tr><td><code>cms:mode-change</code></td><td><code>{ mode: 'view'|'edit'|'template' }</code></td><td>Modus der Werkzeugleiste gewechselt (Detailseite: Ansehen ↔ Bearbeiten ohne Neuladen)</td></tr>
  </table>
  <pre><code>// Beispiel: Veröffentlichen nur mit Alt-Text an allen Bildern
document.addEventListener('cms:before-save', e =&gt; {
  if (!e.detail.publish) return;
  e.detail.waitUntil(fetch('/admin/api/alt-pruefen').then(r =&gt; r.json()).then(r =&gt; {
    if (!r.ok) e.detail.reason = 'Bitte zuerst Alt-Texte ergänzen.';
    return r.ok;
  }));
});</code></pre>

  <h3 id="erweiterungen-hooks">Ereignisse auf dem Server (<code>$x-&gt;on()</code>, <code>Core\Events</code>)</h3>
  <p>Gemeldet über <code>Extensions::emit()</code> nach der Änderung, unabhängig vom Weg (Verwaltung, Website, REST-API, MCP, KI, Kommandozeile). Fehler einer Erweiterung werden protokolliert und brechen nichts ab. Teure Angaben berechnet der Core nur, wenn jemand zuhört (<code>Extensions::listens()</code> – Name und Klasse zählen gleich).</p>
  <p><b>Typisiert (empfohlen):</b> Seiten und Einträge melden unveränderliche Ereignis-Objekte. Gemeinsame Angaben (<code>readonly</code>): <code>table</code> (<code>'pages'</code> bzw. Kurzname der Datentabelle), <code>id</code>, <code>lang</code> (nie <code>null</code>), <code>userId</code> (wer – <code>null</code> = System/Kommandozeile), <code>state</code> (<code>'draft'</code> = Arbeitsstand, <code>'live'</code> = veröffentlichte Fassung).</p>
  <table class="doc-table">
    <tr><th>Klasse (<code>Core\Events\…</code>)</th><th>Name (Alias)</th><th>Zusätzlich</th><th><code>state</code></th><th>Altform (Name, ohne Typ)</th><th>Auslöser</th></tr>
    <tr><td><code>PageSaved</code></td><td><code>page.saved</code></td><td><code>page</code></td><td>draft</td><td><code>array $page, ?int $userId</code></td><td>Entwurf gespeichert (<code>Pages::saveDraft</code>)</td></tr>
    <tr><td><code>PagePublished</code></td><td><code>page.published</code></td><td><code>page</code></td><td>live</td><td><code>array $page</code></td><td>veröffentlicht bzw. wieder online (<code>Pages::publish</code>)</td></tr>
    <tr><td><code>PageUnpublished</code></td><td><code>page.unpublished</code></td><td><code>page</code></td><td>live</td><td><code>array $page</code></td><td>offline genommen (nur bei echter Änderung)</td></tr>
    <tr><td><code>PageDiscarded</code></td><td><code>page.discarded</code></td><td><code>page</code></td><td>draft</td><td><code>array $page</code></td><td>Entwurf verworfen</td></tr>
    <tr><td><code>PageDeleted</code></td><td><code>page.deleted</code></td><td><code>page</code> (Stand vor dem Löschen)</td><td>live</td><td><code>array $page</code></td><td>Seite gelöscht (Verwaltung, API/MCP)</td></tr>
    <tr><td><code>EntrySaved</code></td><td><code>entry.saved</code></td><td><code>definition</code>, <code>entry</code>, <code>created</code>, <code>old</code></td><td>nach Status</td><td><code>array $table, array $entry, bool $created, ?array $old</code></td><td>Eintrag angelegt/geändert (<code>Entries::save</code>, auch Quellen-Abgleich und Formulare)</td></tr>
    <tr><td><code>EntryPublished</code>, <code>EntryUnpublished</code></td><td><code>entry.published</code>, <code>entry.unpublished</code></td><td><code>definition</code>, <code>entry</code></td><td>live / draft</td><td><code>array $table, array $entry</code></td><td>Status wechselt (<code>save</code>, <code>setStatus</code> – nur bei echter Änderung)</td></tr>
    <tr><td><code>EntryDeleted</code></td><td><code>entry.deleted</code></td><td><code>definition</code>, <code>entry</code> (Stand vor dem Löschen, sonst <code>null</code>)</td><td>live</td><td><code>array $table, int $id</code></td><td>Eintrag gelöscht</td></tr>
    <tr><td><code>PageCreated</code></td><td><code>page.created</code></td><td><code>page</code></td><td>draft</td><td><code>array $page</code></td><td>Seite angelegt (<code>Pages::create</code> – Verwaltung, API/MCP, KI, Vorlagen, Import, Übersetzung)</td></tr>
    <tr><td><code>PageMoved</code></td><td><code>page.moved</code></td><td><code>page</code> (neuer Stand), <code>oldParentId</code>, <code>reparented()</code></td><td>live</td><td><code>array $page</code></td><td>Seitenbaum, Seiteneinstellungen („Übergeordnete Seite“), API/MCP</td></tr>
    <tr><td><code>PageTranslated</code></td><td><code>page.translated</code></td><td><code>page</code> (neue Sprachfassung), <code>sourceId</code></td><td>draft</td><td><code>array $page</code></td><td>Übersetzung angelegt (<code>Pages::translate</code>); davor kommt <code>page.created</code></td></tr>
    <tr><td><code>PageRestored</code></td><td><code>page.restored</code></td><td><code>page</code>, <code>revisionId</code></td><td>draft</td><td><code>array $page</code></td><td>Version als Entwurf wiederhergestellt (Verwaltung, API/MCP); davor kommt <code>page.saved</code></td></tr>
  </table>
  <p><b>Weitere typisierte Ereignisse</b> (Basis <code>Core\Events\InfoEvent</code> – kein Datensatz als Gegenstand; Listener mit dem Namen ohne Typ bekommen das Objekt selbst):</p>
  <table class="doc-table">
    <tr><th>Klasse</th><th>Name</th><th>Angaben (<code>readonly</code>)</th><th>Auslöser</th></tr>
    <tr><td><code>FormSubmitted</code></td><td><code>form.submitted</code></td><td><code>definition</code>, <code>table</code>, <code>values</code> (geprüft, <b>Klartext</b> – auch bei verschlüsselten Anfragen; nie protokollieren), <code>entryId</code> bzw. <code>ref</code>, <code>inbox</code></td><td>Formular der Website nach Prüfung, Spamschutz und Speichern (<code>DataForms::submit</code>)</td></tr>
    <tr><td><code>UserLoggedIn</code></td><td><code>user.login</code></td><td><code>id</code>, <code>email</code>, <code>role</code>, <code>ip</code></td><td>jede erfolgreiche Anmeldung (<code>Auth::login</code> – Passwort, Zwei-Faktor, Passkey, Einladung, Netzwerk)</td></tr>
    <tr><td><code>UserLoggedOut</code></td><td><code>user.logout</code></td><td><code>id</code></td><td>Abmeldung, Sitzung noch offen</td></tr>
    <tr><td><code>LoginFailed</code></td><td><code>user.login_failed</code></td><td><code>email</code> (wie eingegeben), <code>ip</code>, <code>reason</code>: <code>password</code> | <code>disabled</code> | <code>limited</code></td><td>abgelehnte Anmeldung (<code>Auth::attempt</code>)</td></tr>
    <tr><td><code>UserCreated</code></td><td><code>user.created</code></td><td><code>id</code>, <code>email</code>, <code>role</code>, <code>via</code>: <code>admin</code> | <code>setup</code> | <code>invite</code></td><td>Konto der Website angelegt</td></tr>
    <tr><td><code>UserRoleChanged</code></td><td><code>user.role_changed</code></td><td><code>id</code>, <code>role</code>, <code>oldRole</code></td><td>Verwaltung → Rollen</td></tr>
    <tr><td><code>UserDeleted</code></td><td><code>user.deleted</code></td><td><code>id</code></td><td>Konto gelöscht – eigene Zugänge des Kontos entfernen (z. B. App-Passwörter, Tokens)</td></tr>
    <tr><td><code>SettingsSaved</code></td><td><code>settings.saved</code></td><td><code>area</code>: <code>settings</code> | <code>system</code> | <code>design</code>; <code>keys</code> (nur geänderte, ohne Werte; Sprachfassungen als <code>schlüssel@en</code>)</td><td>Website-Einstellungen, Grundeinstellungen, Design (Verwaltung, Live-Design, API/MCP)</td></tr>
    <tr><td><code>TableSchemaChanged</code></td><td><code>table.schema_changed</code></td><td><code>definition</code>, <code>table</code>, <code>action</code>: <code>created</code> | <code>updated</code> | <code>deleted</code>; <code>added</code>, <code>dropped</code> (Feldnamen; umbenannt = beides)</td><td>Datentabelle angelegt, Felder geändert, gelöscht (<code>Core\Data\Tables</code>)</td></tr>
  </table>
  <pre><code>use Core\Events\{EntryPublished, PageSaved};

$x-&gt;on(EntryPublished::class, function (EntryPublished $e): void {
    if ($e-&gt;table === 'news') Newsletter::queue($e-&gt;id);
});
$x-&gt;on('page.saved', fn(PageSaved $e) =&gt; Log::draft($e-&gt;id, $e-&gt;userId));   // Name als Alias – am Typ erkannt
$x-&gt;on('page.saved', fn(array $page, ?int $uid) =&gt; …);                      // Altform: bisherige Argumente</code></pre>
  <p><b>Rückwärtskompatibel:</b> Listener, die mit dem Namen angemeldet sind und am ersten Parameter keinen Ereignis-Typ haben, bekommen weiter die bisherigen Argumente. Das Objekt erlaubt zusätzlich Array-Zugriff zum Lesen (<code>$e['id']</code>, <code>$e['title']</code> = Feld des Datensatzes); Schreiben wirft eine <code>LogicException</code>.</p>
  <p><b>Ohne Typ</b> (nur Name, Argumente wie bisher): <code>media.imported</code> (<code>array $m</code>), <code>media.replaced</code> und <code>media.edited</code> (<code>array $neu, array $alt</code>), <code>media.deleted</code> (<code>array $m</code>), <code>inbox.status</code> und <code>inbox.deleted</code> (siehe <a href="#inbox-hooks">Eingangs-Tabellen</a>). </p>

  <p><b>Beispiele</b> (alle in <code>boot</code> einer Erweiterung, <code>$x</code> = <code>Core\Extension</code>):</p>
  <pre><code>use Core\Events\{FormSubmitted, LoginFailed, PageMoved, SettingsSaved, TableSchemaChanged, UserLoggedIn};

// Neue Anmeldung zu einem Termin → eigene Warteliste pflegen
$x-&gt;on(FormSubmitted::class, function (FormSubmitted $e): void {
    if ($e-&gt;table === 'anmeldungen' &amp;&amp; $e-&gt;entryId) Warteliste::pruefen($e-&gt;entryId);
});

// Sicherheit: nach fünf Fehlversuchen derselben Adresse an die Administration melden
$x-&gt;on(LoginFailed::class, fn(LoginFailed $e) =&gt; $e-&gt;reason === 'password' ? Wache::zaehlen($e-&gt;ip) : null);
$x-&gt;on(UserLoggedIn::class, fn(UserLoggedIn $e) =&gt; Wache::zuruecksetzen($e-&gt;ip));

// Seite verschoben → externe Verlinkung (z. B. Kurz-URL-Dienst) nachziehen
$x-&gt;on(PageMoved::class, function (PageMoved $e): void {
    if ($e-&gt;reparented()) KurzUrl::aktualisieren($e-&gt;id, \Core\Pages::url($e-&gt;page));
});

// Design geändert → eigenes, vorab erzeugtes CSS neu bauen
$x-&gt;on(SettingsSaved::class, fn(SettingsSaved $e) =&gt; $e-&gt;area === 'design' ? MeinCss::neu() : null);

// Feld einer Tabelle gelöscht → eigene Zuordnung aufräumen
$x-&gt;on(TableSchemaChanged::class, fn(TableSchemaChanged $e) =&gt; Zuordnung::entfernen($e-&gt;table, $e-&gt;dropped));</code></pre>
  <p><b>Faustregeln:</b> Ereignisse sind Mitteilungen <b>nach</b> der Änderung – sie können nichts verhindern (dafür gibt es Filter wie <code>form.validate</code> bzw. <code>blocksBeforeSave</code>). Listener kurz halten; Langes (Mails an viele, fremde Dienste) über <code>afterAdminResponse()</code> oder einen eigenen Auftrag nach der Antwort. Nie Werte aus <code>FormSubmitted::$values</code> ins Protokoll schreiben (Datenschutz, verschlüsselte Anfragen).</p>

  <h3 id="erweiterungen-filter">Filter (<code>$x-&gt;filter()</code>, <code>Core\Filters</code>)</h3>
  <p>Wo Ereignisse nur mitteilen, ändern Filter ein <b>Ergebnis</b>: Der Core reicht einen Wert durch alle Erweiterungen (<code>Extensions::filter($name, $wert, $ctx)</code>). Regeln: <code>fn($wert, array $ctx)</code> gibt den neuen Wert zurück; <code>null</code> = unverändert (ein vergessenes <code>return</code> schadet nicht); <code>false</code> = verwerfen, nur wo die Tabelle es erlaubt; Fehler einer Erweiterung lassen den Wert unverändert (Protokoll). Unbekannte Namen werden nicht angemeldet (Protokoll). Eigene Filter einer Erweiterung heißen <code>ext:{erweiterung}.{name}</code> – andere Erweiterungen können sie dann genauso nutzen.</p>
  <ul class="doc-list">
    <li><b><code>search.document</code></b> – Dokument ändern oder mit <code>false</code> aus der Website-Suche (und damit dem Besucher-Chat) nehmen – unabhängig von „noindex“. Wirkt beim nächsten Abgleich (<code>search:index</code>).<br><small>Wert: Dokument (<code>id</code>, <code>type</code>, <code>title</code>, <code>text</code>, <code>url</code> …) · <code>$ctx</code>: <code>type</code> (<code>page</code>|<code>entry</code>|<code>file</code>), <code>lang</code>, <code>page_id</code> bzw. <code>table</code> + <code>entry_id</code></small></li>
    <li><b><code>sitemap.urls</code></b> – Adressen der <code>sitemap.xml</code> ergänzen (eigene Routen einer Erweiterung), ändern, entfernen.<br><small>Wert: <code>list&lt;{loc, lastmod?, images?}&gt;</code> (<code>lastmod</code> W3C-Datum, <code>images</code> absolute Bild-Adressen → <code>image:image</code>) · <code>$ctx</code>: <code>host</code>. Die Sitemap verweist auf <code>/sitemap.xsl</code> (lesbare Ansicht im Browser; Bilder je Seite: Vorschaubild, dann Medienfelder der Blöcke, je Eintrag die Medienfelder – höchstens 5, nie geschützte Medien).</small></li>
    <li><b><code>menu.items</code></b> – Navigation aller Kits (<code>Pages::menu</code>) – Einträge ergänzen, umbenennen, ausblenden. Steht im Seiten-Cache: nur Angaben, die für alle Besucher gleich sind.<br><small>Wert: Baum <code>{id, label, href, active, children, draft}</code> · <code>$ctx</code>: <code>drafts</code></small></li>
    <li><b><code>seo.meta</code></b> – Angaben für den Kopf der Seite. Nur ein Array mit <code>title</code> wird übernommen.<br><small>Wert: <code>{title, description, canonical, og_image, noindex, alternates, jsonld}</code> · <code>$ctx</code>: <code>page</code>; Detailseiten zusätzlich <code>table</code>, <code>entry</code></small></li>
    <li><b><code>form.validate</code></b> – Eigene Prüfung vor dem Speichern (nach Feldern und Bedingungen, vor dem Spamschutz). Fehler je Feld oder <code>'_form'</code> als Meldung über dem Formular brechen das Absenden ab.<br><small>Wert: <code>array $errors</code> (Feld → Meldung) · <code>$ctx</code>: <code>table</code>, <code>values</code>, <code>ip</code></small></li>
    <li><b><code>mail.message</code></b> – Ausgehende E-Mail ändern oder mit <code>false</code> unterdrücken. Mails zur Kontosicherheit (Passwort, Einladung, E-Mail-Änderung) laufen nie durch den Filter; Test-Umleitung und Staging-Schutz gelten danach weiter. Ungültige Empfänger werden verworfen.<br><small>Wert: <code>{subject, text, to, options}</code> · <code>$ctx</code>: <code>account</code> (immer <code>false</code>)</small></li>
    <li><b><code>data_list.query</code></b> – Abfrage des Blocks „Datenliste“ – z. B. zusätzliche Bedingung. Gilt auch für die Seitenzahl beim Blättern.<br><small>Wert: Optionen für <code>Entries::query</code> (<code>where</code>, <code>sort</code>, <code>limit</code>, <code>q</code> …) · <code>$ctx</code>: <code>table</code>, <code>block</code> (Daten des Blocks)</small></li>
  </ul>
  <p><b>Beispiele:</b></p>
  <pre><code>// Interne Notizen-Tabelle nie in der Website-Suche, auch wenn ihre Detailseiten öffentlich sind
$x-&gt;filter('search.document', fn(array $doc, array $c) =&gt; ($c['table'] ?? '') === 'notizen' ? false : $doc);

// Eigene Seiten einer Erweiterung (Route /termine/{id}) in die Sitemap
$x-&gt;filter('sitemap.urls', function (array $urls): array {
    foreach (Termine::oeffentlich() as $t) $urls[] = ['loc' =&gt; abs_url('/termine/' . $t['id']), 'lastmod' =&gt; substr($t['updated_at'], 0, 10)];
    return $urls;
});

// Menüpunkt „Mitglieder“ nur, solange es Mitglieder gibt
$x-&gt;filter('menu.items', fn(array $items) =&gt; Mitglieder::anzahl() ? $items : array_values(array_filter($items, fn($i) =&gt; $i['href'] !== url('/mitglieder'))));

// Titel von Veranstaltungs-Detailseiten mit Datum
$x-&gt;filter('seo.meta', function (array $m, array $c): ?array {
    if (($c['table']['handle'] ?? '') !== 'termine') return null;   // null = unverändert
    return ['title' =&gt; fmt()-&gt;date($c['entry']['datum']) . ' · ' . $m['title']] + $m;
});

// Anmeldung nur mit Mitgliedsnummer aus der eigenen Liste
$x-&gt;filter('form.validate', function (array $errors, array $c): array {
    if ($c['table']['handle'] === 'anmeldungen' &amp;&amp; !Mitglieder::nummerGueltig($c['values']['mitgliedsnummer'] ?? '')) {
        $errors['mitgliedsnummer'] = lt('Diese Mitgliedsnummer kennen wir nicht.');
    }
    return $errors;
});

// Fußzeile mit Abmeldehinweis unter alle Benachrichtigungen
$x-&gt;filter('mail.message', fn(array $m) =&gt; ['text' =&gt; $m['text'] . "\n\n-- \nVerein Musterstadt e. V."] + $m);

// Datenliste: abgesagte Termine ausblenden
$x-&gt;filter('data_list.query', fn(array $o, array $c) =&gt; $c['table']['handle'] === 'termine'
    ? ['where' =&gt; [...($o['where'] ?? []), ['abgesagt', '=', 0]]] + $o : $o);

// Eigener Filter einer Erweiterung, den andere nutzen können
$preis = \Core\Extensions::filter('ext:buchung.preis', $grundpreis, ['termin' =&gt; $t]);</code></pre>
  <p><b>Wann was?</b> Etwas soll <i>danach</i> passieren (Mail, Abgleich, Protokoll) → Ereignis. Ein Ergebnis soll anders aussehen oder etwas soll verhindert werden → Filter. Blöcke haben eigene Haken (<code>blocksResolve</code>, <code>blocksBeforeSave</code>, <code>blockPresets</code>, siehe oben). Fehlt eine Stelle: Vorschlag für einen neuen Filter im Core (Name in <code>Core\Filters::ALL</code>, Aufruf, Selbsttest, diese Tabelle).</p>
  <p>Selbsttest: <code>php bin/console extensions:selftest</code> (Filterkette, <code>null</code>/<code>false</code>/Fehler, Menü, SEO, Suche, E-Mail, Ereignisse beim Anlegen/Verschieben/Fehlanmelden, Namen ↔ Klassen).</p>

  <h3 id="erweiterungen-mail">E-Mail-Konten, Zwecke und Versandwege (<code>Core\Mail</code>)</h3>
  <p>Eine Website hat das Konto „Standard“ (Grundeinstellungen → E-Mail-Versand), beliebig viele weitere Konten (<code>sys.mail_accounts</code>, Geheimnisse verschlüsselt) und – in Installationen mit Netzwerk – das <b>Netzwerk-Konto</b> (verwaltet im Netzwerk-Dashboard, <code>POST /admin/network/mail</code>; Netzwerk-Datenbank, Tabelle <code>network_mail</code>, verschlüsselt mit einem Schlüssel aus dem <code>app_key</code> der Netzwerk-Website). Je <b>Zweck</b> wählt die Website ein Konto (<code>sys.mail_routes</code>). <code>Mailer::send($betreff, $text, $an, ['purpose' =&gt; …])</code> versendet über das gewählte Konto: Absender, Absender-Name, Antwortadresse, Message-ID-Domain und Versandweg kommen vom Konto.</p>
  <ul>
    <li><b>Zwecke des Cores:</b> <code>account</code> (Konto &amp; Sicherheit; Standard bei <code>'account' =&gt; true</code>), <code>notify</code> (Redaktion; Standard), <code>visitor</code> (Besucher), <code>newsletter</code> (für Erweiterungen).</li>
    <li><b>Netzwerk:</b> <code>'network' =&gt; true</code> (Mails an Netzwerk-Konten) versendet nur über das Netzwerk-Konto. Websites ohne versandbereites Konto nutzen das Netzwerk-Konto; nie das Konto einer anderen Website.</li>
    <li><b>Versandarten:</b> <code>smtp</code>, <code>dsn</code> (Symfony-DSN – Dienste mit Bridge-Paket, z. B. <code>mailjet+api://…</code> mit <code>symfony/mailjet-mailer</code>, <code>mandrill+api://…</code> mit <code>symfony/mailchimp-mailer</code>; fehlt das Paket, meldet <code>Accounts::transport()</code> den Paketnamen), <code>null</code> und Versandwege von Erweiterungen (<code>ext:{schema}</code>). sendmail und PHP-<code>mail()</code> sind bewusst nicht dabei.</li>
    <li><b>Kopfzeilen:</b> <code>'headers' =&gt; ['List-Unsubscribe' =&gt; '&lt;https://…&gt;']</code> – erlaubt sind <code>List-*</code>, <code>X-*</code> und <code>Precedence</code>; Adressfelder nie.</li>
    <li><b>Rücklaufadresse und Stapelversand:</b> <code>'return_path' =&gt; 'bounce+…@news.example.org'</code> setzt Return-Path/Envelope-From (VERP – Rückläufer landen im eigenen Bounce-Postfach). <code>'keep_alive' =&gt; true</code> hält die Verbindung des Kontos für weitere Mails offen (Cron-Stapel); am Ende <code>Mailer::closeTransports()</code>. <code>'via' =&gt; 'konto-id'</code> versendet über ein bestimmtes Konto statt über den Zweck (z. B. eigener Absender je Newsletter-Verteiler; nicht versandbereit → Zweck).</li>
    <li><b>Hinweise bei Fehlern:</b> <code>Accounts::hint($fehler)</code> übersetzt typische SMTP-Fehler (Anmeldung, Microsoft&nbsp;365 ohne SMTP-Anmeldung, STARTTLS/SSL, Zertifikat, nicht erreichbar, Relay, Absender) in einen Satz mit Abhilfe; <code>Mailer::send</code> hängt ihn an die Fehlermeldung an (auch im Testversand der Konten).</li>
    <li>Test-Umleitung, Staging-Schutz und der Filter <code>mail.message</code> (Kontext jetzt mit <code>purpose</code> und <code>mail_account</code>) gelten für alle Konten.</li>
  </ul>
  <pre><code>// Newsletter-Erweiterung: eigener Zweck – die Website wählt dafür ein Konto (z. B. „Mailjet Newsletter“)
$x-&gt;mailPurpose('kampagne', 'Newsletter-Kampagnen', 'Versand an alle Abonnenten');
foreach ($abonnenten as $a) {
    Mailer::send($betreff, $text, [$a['email']], ['purpose' =&gt; 'ext:newsletter.kampagne', 'html' =&gt; $html,
        'headers' =&gt; ['List-Unsubscribe' =&gt; '&lt;' . $abmeldeLink . '&gt;']]);
}

// Dienst ohne Symfony-Bridge (z. B. die API eines Newsletter-Anbieters): eigener Versandweg für Konten
$x-&gt;mailTransport('meinapi', 'Mein Dienst (API)', fn(array $konto) =&gt; new MeinApiTransport($konto['pass'], $konto['host']));</code></pre>
  <p>Listen-Dienste wie <b>CleverReach</b> oder <b>Mailchimp-Kampagnen</b> verschicken nicht einzelne Mails, sondern Mailings an Listen – eine Newsletter-Erweiterung spricht dafür deren API direkt an (Empfänger, Mailings, Abmeldungen) und nutzt die Konten nur für Einzel-Mails wie Bestätigungen. Selbsttest: <code>php bin/console mail:selftest</code>; Health meldet ein fehlendes Netzwerk-Konto und Zwecke mit fehlendem Konto.</p>

  <h3 id="erweiterungen-zugriff">Geschützte Bereiche (<code>Core\PageAccess</code>, <code>$x-&gt;pageAccess()</code>, <code>$x-&gt;mediaAccess()</code>)</h3>
  <p>Für Mitgliederbereiche u. Ä. – die Erweiterung entscheidet, der Core setzt durch:</p>
  <ul>
    <li><code>'restricted' =&gt; fn(array $page): bool</code> – Seite gehört zu einem geschützten Bereich (billig, für alle gleich). Folgen im Core: Prüfung in <code>SiteController::render</code> <b>vor</b> dem Seiten-Cache, nie gecacht, <code>Cache-Control: private, no-store</code>, nicht im Menü (<code>Pages::menu</code>), in Sitemap, <code>llms.txt</code>, Suchindex (also auch nicht im Besucher-Chat) und nicht über <code>/api/live/block</code>. Angemeldete Redaktion sieht alles.</li>
    <li><code>'allow' =&gt; fn(array $page, Request $r, ?array $table): bool|Response</code> – darf diese Anfrage? <code>true</code> zeigen, <code>false</code> 404, <code>Response</code> z. B. Weiterleitung zur Anmeldung. <code>$table</code> bei Detailseiten von Einträgen.</li>
    <li><code>'table' =&gt; fn(array $table): bool</code> – ganze Datentabelle geschützt: Detailseiten verlangen Zugriff; <code>Entries::query</code>/<code>count</code> liefern Besuchern öffentlicher Seiten nichts (Listen, Kalender, Feeds). Auf geschützten Seiten (nie gecacht) volle Daten. <code>PageAccess::audience($table)</code>: <code>all</code> (Verwaltung, API, MCP, Konsole) | <code>members</code> | <code>public</code>.</li>
    <li><code>'entry' =&gt; fn(array $table, array $entry, string $audience): ?array</code> – einzelne Einträge/Felder doch zeigen (<code>public</code>) bzw. kürzen (<code>members</code>), z. B. Profile, deren Inhaber Felder veröffentlichen. <code>null</code> blendet aus.</li>
    <li><code>$x-&gt;mediaAccess(fn(array $ctx, Request $r): bool|Response)</code> – Dateien geschützter Medien-Pools (<code>$ctx</code>: <code>pool</code>, <code>path</code>), siehe Medien → Geschützte Pools.</li>
    <li>Passkeys für eigene Konten: <code>Core\Passkeys</code> mit eigener Tabelle (<code>ensureTable($db, 'x_passkeys')</code>, Parameter <code>$table</code> an allen Methoden) und eigenem Sitzungsplatz (<code>creationOptions(…, $slot)</code>, <code>requestOptions('x_login')</code>) – nie <code>user_passkeys</code>, nie Zeilen in <code>users</code> (jede Zeile dort darf in die Verwaltung).</li>
  </ul>
  <h3 id="erweiterungen-sicherheit">Sicherheit</h3>
  <ul>
    <li><b>Server prüft immer:</b> Endpunkte unter <code>/admin</code> meldet die Erweiterung mit Recht an – der Router prüft Anmeldung, Recht und CSRF (<code>X-CSRF-Token</code>), bevor der Handler läuft (<a href="#erweiterungen-routen">Routen</a>). Die Angaben <code>perm</code>/<code>visible</code> an Werkzeugen und Slots steuern nur die Anzeige.</li>
    <li><b>Keine Besucher:</b> Werkzeugleiste, Konfiguration (<code>#cms-tools</code>), schwebender Knopf und Module gibt es nur angemeldet (Bearbeiten bzw. mit <code>view</code> auch Ansehen); Seiten im Seiten-Cache enthalten sie nie.</li>
    <li><b>Nur eigene Dateien:</b> Module von Erweiterungen kommen aus ihrem <code>assets</code>-Ordner (kein <code>..</code>, keine fremde Domain, CSP <code>'self'</code>); Endpunkte nur als Pfade dieser Installation.</li>
    <li><b>Isoliert:</b> Oberflächen in der Shadow-DOM-Ebene – kein Kit-CSS hinein, kein Werkzeug-CSS hinaus. Eigene Stile: Klassen der Verwaltung nutzen oder ein eigenes Stylesheet per <code>&lt;link&gt;</code> in <code>ctx.panel.el</code> anhängen (keine Inline-Styles, CSP).</li>
  </ul>
