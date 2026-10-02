<?php /** Entwicklerhandbuch · Erweiterungen: Verwaltungsseiten nach Art, Werkzeuge der Website, Ereignisse (Core\AdminPages, Core\FrontendTools) */ ?>
  <p class="lead">Erweiterungen (und Funktionen des Cores) erweitern die Verwaltung und das Bearbeiten auf der Website über drei stabile Schnittstellen: <b>Verwaltungsseiten mit Art</b> (wo eine Seite erscheint), <b>Werkzeuge der Website</b> (Knopf in der Werkzeugleiste, Seitenleiste in der Shadow-DOM-Ebene) und <b>Ereignisse</b> in PHP und im Browser. Referenzbeispiel ist das <a href="#glossar">Quick-Glossar</a> – der Core meldet es genau so an wie eine Erweiterung.</p>

  <h3 id="erweiterungen-regeln">Integrationspunkte und Regeln</h3>
  <p>Erweiterungen dürfen <b>nur über die folgenden Stellen</b> eingreifen. Jede Stelle hat eine Methode im Manifest (<code>Core\Extension</code>), einen festen Datenvertrag, eine Rechteprüfung und einen Selbsttest (<code>extensions:selftest</code>). Was hier nicht steht, gibt es nicht – auch nicht „vorübergehend“. Neue Stellen entstehen nur im Core, mit Doku und Selbsttest, nie als zweiter Weg für etwas, das es schon gibt.</p>
  <table class="doc-table">
    <tr><th>Bereich</th><th>Stellen (Manifest)</th><th>Vertrag</th></tr>
    <tr><td>Lebenszyklus</td><td><code>boot</code>, <code>install</code>, <code>deactivate</code>, <code>requirements</code>, <code>usage</code>, <code>migration()</code>, <code>schema()</code></td><td>Tabellen deklarativ mit <a href="#erweiterungen-schema"><code>Core\Db\Table</code></a> (idempotent), einmalige Datenschritte mit <code>migration()</code>. Eigene Tabellen tragen den Namen der Erweiterung als Präfix.</td></tr>
    <tr><td>Routen</td><td><code>routes()</code></td><td>Routen unter <code>/admin</code> sind <a href="#erweiterungen-routen">geschützt</a>: Anmeldung, CSRF bei Nicht-GET und ein <b>Recht pro Route</b>. Ausnahmen nur benannt (<code>'csrf' =&gt; false</code>, <code>'public' =&gt; true</code>), sichtbar in <code>extensions:list</code>.</td></tr>
    <tr><td>Verwaltungsseiten</td><td><code>adminPage()</code> / <code>nav()</code>, <code>permissions()</code>, <code>feature()</code>, <code>adminAssets()</code></td><td>Art <code>content|tool|settings|stats</code> bestimmt den Ort (siehe unten).</td></tr>
    <tr><td>Slots der Verwaltung</td><td><code>pageList()</code>, <code>pagePanel()</code>, <code>tableActions()</code>, <code>mediaPanel()</code>, <code>dashboard()</code>, <code>account()</code></td><td>Liefern <b>Daten</b>, der Core rendert und escaped (<a href="#erweiterungen-slots">Slots</a>).</td></tr>
    <tr><td>Website</td><td><code>blocks()</code>, <code>htmlFilter()</code>, <code>csp()</code>, <code>footerLinks()</code>, <code>frontendTool()</code>, <code>toolbar()</code></td><td>CSP nur Hosts (kein <code>'unsafe-inline'</code>), Werkzeuge nur angemeldet.</td></tr>
    <tr><td>Mediathek</td><td><code>mediaChecks()</code>, <code>mediaJson()</code>, <code>mediaTypes()</code>, <code>mediaPoster()</code> (+ im Browser <code>CMSMedia.extend()</code>)</td><td>siehe Kapitel <a href="#medien">Medien</a>.</td></tr>
    <tr><td>Ereignisse</td><td><code>on(PageSaved::class, …)</code></td><td><a href="#erweiterungen-hooks">Typisierte Ereignisse</a> (<code>Core\Events\*</code>); die Namen <code>'page.saved'</code> … bleiben als Alias.</td></tr>
    <tr><td>Betrieb</td><td><code>command()</code>, <code>health()</code>, <code>afterAdminResponse()</code>, <code>proxy()</code>, <code>docs()</code>, <code>inbox()</code></td><td>Fehler einer Erweiterung werden protokolliert und brechen die Anfrage nicht ab.</td></tr>
  </table>
  <p><b>Regeln:</b></p>
  <ul>
    <li><b>Keine Eingriffe am Core vorbei:</b> keine Core-Dateien ändern oder überschreiben, keine Ausgabe-Puffer auf Verwaltungsseiten, keine eigenen <code>&lt;script&gt;</code> außer über <code>adminAssets()</code>/<code>toolbar()</code>, kein direktes Schreiben in Core-Tabellen (<code>pages</code>, <code>media</code>, <code>settings</code>, Datentabellen) – dafür gibt es <code>Pages</code>, <code>Entries</code>, <code>Media</code>, <code>app()-&gt;settings</code>.</li>
    <li><b>Escapen macht der Core:</b> Slots geben Beschriftungen, Werte und Pfade zurück, kein HTML. Alte Rückgaben als HTML (<code>pagePanel()</code>, Karten der Übersicht mit <code>render</code>) laufen weiter, gelten aber als Altform.</li>
    <li><b>Rechte doppelt:</b> Die Angabe <code>perm</code> steuert Sichtbarkeit und Route; Endpunkte, die Daten ändern, prüfen zusätzlich fachlich (z. B. Recht je Tabelle).</li>
    <li><b>Formatieren mit <code>Core\Format</code></b> (Datum, Zahl, Größe, Dauer …) statt eigener Helfer – siehe <a href="#format">Format</a>.</li>
    <li><b>Eigene Pakete:</b> Jede Erweiterung lebt in einem eigenen Repository und wird per Composer installiert (Typ <code>klxm-studio-extension</code>); interne Erweiterungen gehören nicht ins öffentliche Core-Repository.</li>
  </ul>

  <h3 id="erweiterungen-seiten">Verwaltungsseiten: Art und Ort (<code>Core\AdminPages</code>)</h3>
  <p>Jede Seite, die eine Funktion oder Erweiterung in der Verwaltung anmeldet, hat eine <b>Art</b>. Die Art bestimmt den Ort – das Backend bleibt aufgeräumt:</p>
  <table class="doc-table">
    <tr><th>Art (<code>kind</code>)</th><th>Wofür</th><th>Wo sie erscheint</th></tr>
    <tr><td><code>content</code></td><td>arbeitet mit Inhalten (Einträge, Buchungen, Termine …)</td><td>Hauptmenü (<code>place =&gt; 'main'</code>) bzw. Administration (<code>'admin'</code>)</td></tr>
    <tr><td><code>tool</code></td><td>Werkzeug, Arbeitsablauf (prüfen, importieren, Video bearbeiten …)</td><td>wie <code>content</code>, meist <code>place =&gt; 'admin'</code></td></tr>
    <tr><td><code>settings</code></td><td>reine Konfiguration</td><td><b>nicht im Menü</b>: Sammelseite <b>Administration → Einstellungen</b> (<code>/admin/einstellungen</code>, eine Karte je Seite, gruppiert nach Funktion/Erweiterung). Mit <code>'table' =&gt; 'handle'</code> zusätzlich an der Datentabelle (Knopf im Kopf der Liste, Unterpunkt in der Daten-Navigation)</td></tr>
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

  <h3 id="erweiterungen-routen">Routen: Verwaltung geschützt ab Werk (<code>Core\Http\Router</code>)</h3>
  <p>Routen, die eine Erweiterung mit <code>routes()</code> unter <code>/admin</code> anmeldet, schützt der Core, <b>bevor</b> der Handler läuft: Anmeldung (sonst Weiterleitung zur Anmeldung), Zwei-Faktor-Einrichtung, das angegebene <b>Recht</b> (sonst 403) und bei allen Methoden außer GET/HEAD/OPTIONS das <b>CSRF-Token</b> (Feld <code>_csrf</code> oder Kopfzeile <code>X-CSRF-Token</code>, sonst 419). Jede Verwaltungsroute braucht ein Recht.</p>
  <pre><code>$x-&gt;routes(function (Core\Http\Router $r): void {
    $c = KalenderController::class;
    $r-&gt;get('/admin/kalender', [$c, 'index'], 'calendar.edit');                      // Recht als dritte Angabe
    $r-&gt;post('/admin/kalender/{id}', [$c, 'save'], ['perm' =&gt; 'calendar.edit']);      // + CSRF
    $r-&gt;post('/admin/kalender/webhook', [$c, 'hook'], ['perm' =&gt; 'calendar.edit', 'csrf' =&gt; false]);   // Ausnahme, benannt
    $r-&gt;get('/admin/kalender/status.json', [$c, 'status'], ['public' =&gt; true]);       // Ausnahme: ohne Anmeldung
    $r-&gt;get('/kalender.ics', [$c, 'feed']);                                           // Website: Sache der Erweiterung
});</code></pre>
  <table class="doc-table">
    <tr><th>Fall</th><th>Verhalten</th></tr>
    <tr><td>mit Recht</td><td>Anmeldung, Recht, CSRF (Nicht-GET) – der Handler kann sich auf alles verlassen. Fachliche Prüfungen (Recht je Tabelle, eigene Einträge) bleiben Sache des Handlers.</td></tr>
    <tr><td><code>'csrf' =&gt; false</code>, <code>'public' =&gt; true</code></td><td>Benannte Ausnahmen – <code>php bin/console extensions:list</code> listet sie je Erweiterung. <code>public</code> verzichtet auf Anmeldung und Recht, CSRF gilt weiter (außer zusätzlich <code>'csrf' =&gt; false</code>).</td></tr>
    <tr><td>ohne Recht, Handler <code>[Controller, 'methode']</code> auf Basis von <code>AdminController</code></td><td><b>Altform</b> (alle Erweiterungen vor dieser Regel): läuft weiter – der Controller prüft das Recht wie bisher mit <code>$this-&gt;auth($r, 'recht')</code>, der Core prüft zusätzlich Anmeldung und CSRF. <code>extensions:list</code> meldet diese Routen; bitte das Recht an der Route angeben.</td></tr>
    <tr><td>ohne Recht, anderer Handler (Closure …)</td><td>Entwicklung (<code>'environment' =&gt; 'development'</code> oder <code>debug</code>): <code>LogicException</code> beim Anmelden. Produktion: Route antwortet mit 403 und schreibt ins Fehlerprotokoll.</td></tr>
  </table>
  <p>Routen des Cores (<code>app/routes.php</code>) sind davon nicht betroffen – ihre Controller prüfen mit <code>auth()</code>. <code>AdminController::routeGuard($r, $perm, $csrf)</code> ist dieselbe Prüfung ohne Nebenarbeiten. Selbsttest: <code>extensions:selftest</code> (Abschnitt Verwaltungsrouten).</p>

  <h3 id="erweiterungen-werkzeuge">Werkzeuge beim Bearbeiten auf der Website (<code>Core\FrontendTools</code>)</h3>
  <p>Ein Werkzeug ist ein Knopf in der Werkzeugleiste (<code>placement =&gt; 'main'</code>, auf Telefonen im Menü „⋯“) oder ein Eintrag im Menü „⋯“ (<code>'more'</code>), optional mit Tastenkürzel. Es erscheint <b>nur angemeldet</b> und nur mit Recht – standardmäßig <b>nur im Bearbeiten-Modus</b> (Seiten-Editor inkl. Vorlage: <code>page</code>; Eintrag direkt im Text: <code>entry</code>, dort erst nach „Bearbeiten“). Mit <code>'view' =&gt; true</code> (bzw. <code>'view'</code> in <code>modes</code>) erscheint es zusätzlich beim <b>Ansehen</b> (Seite ohne <code>?edit=1</code>, auch Live-Fassung; Detailseite eines Eintrags vor „Bearbeiten“) – dort ist jeder Text der Seite markierbar. Das ES-Modul lädt der Browser <b>erst beim ersten Öffnen</b> – Besucher laden nie etwas.</p>
  <pre><code>$x-&gt;frontendTool([
    'id' =&gt; 'notiz', 'label' =&gt; 'Notiz', 'icon' =&gt; 'note-pencil', 'placement' =&gt; 'main', 'shortcut' =&gt; 'Alt+N',
    'hint' =&gt; 'Kurze Notiz an der Schreibmarke',             // kleine Zeile im Menü „⋯“
    'module' =&gt; 'js/notiz.mjs',                              // {dir}/assets/js/notiz.mjs → /assets/ext/{name}/js/notiz.mjs
    'perm' =&gt; 'pages.edit', 'feature' =&gt; 'notiz',             // optional 'table' (Recht je Tabelle), 'visible' =&gt; fn(array $bar): bool
    'modes' =&gt; ['page', 'entry'],                      // Standard; 'view' =&gt; true = zusätzlich beim Ansehen
    'view' =&gt; true, 'chip' =&gt; 'Als Notiz',             // optional: schwebender Knopf neben markiertem Text (nur Ansehen)
    'panel' =&gt; ['title' =&gt; 'Notizen', 'size' =&gt; 'narrow'],    // narrow | wide
    'endpoints' =&gt; ['list' =&gt; '/admin/api/notiz'],          // eigene Routen – prüfen Recht + CSRF bei JEDEM Aufruf
    'data' =&gt; fn(array $bar) =&gt; ['max' =&gt; 200],              // je Werkzeugleiste, frei für das Modul
    'texts' =&gt; ['insert' =&gt; __('Einfügen')],                 // übersetzte Texte (auf der Website gibt es kein Wörterbuch)
]);</code></pre>
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
    <tr><td><code>focusText()</code>, <code>toast(msg, kind)</code>, <code>announce(msg)</code>, <code>on(event, fn)</code>, <code>close()</code></td><td>Zurück an die Schreibmarke, kurze Meldung (<code>ok|error</code>), Meldung für Screenreader (Live-Region der Werkzeugleiste), Ereignis am <code>document</code> abonnieren (bei <code>unmount</code> automatisch abgemeldet), schließen.</td></tr>
  </table>
  <p><b>Tastatur und Fokus:</b> Öffnen per Knopf oder Kürzel; der Fokus geht in die Seitenleiste (<code>[autofocus]</code>, sonst erstes Feld). <kbd>Esc</kbd> schließt und bringt den Fokus zurück an die Schreibmarke bzw. zum Knopf. Das Kürzel springt zwischen Text und offener Seitenleiste. Tasten in der Seitenleiste bleiben dort: Der Core hält sie von Editor.js und den Kürzeln der Seite fern (das echte Ereignis endet am <code>window</code>, eine nicht „composed“ Kopie erreicht das Ziel; Standardaktionen wie Tippen und Tab bleiben).</p>

  <h3 id="erweiterungen-ereignisse">Ereignisse im Browser</h3>
  <p><code>CustomEvent</code> am <code>document</code> – für Werkzeuge, Erweiterungs-Skripte (<code>toolbar()</code>-Skripte) und Kits. Senden: <code>CMSAdmin.events.emit(name, detail)</code>.</p>
  <table class="doc-table">
    <tr><th>Ereignis</th><th><code>detail</code></th><th>Wann</th></tr>
    <tr><td><code>cms:editor-ready</code></td><td><code>{ kind: 'page', page, template, entry }</code> bzw. <code>{ kind: 'entry', entry: {table, id}, fields: [...] }</code></td><td>Seiten-Editor bereit bzw. Eintrag in den Modus „Bearbeiten“ gewechselt</td></tr>
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
  </table>
  <pre><code>use Core\Events\{EntryPublished, PageSaved};

$x-&gt;on(EntryPublished::class, function (EntryPublished $e): void {
    if ($e-&gt;table === 'news') Newsletter::queue($e-&gt;id);
});
$x-&gt;on('page.saved', fn(PageSaved $e) =&gt; Log::draft($e-&gt;id, $e-&gt;userId));   // Name als Alias – am Typ erkannt
$x-&gt;on('page.saved', fn(array $page, ?int $uid) =&gt; …);                      // Altform: bisherige Argumente</code></pre>
  <p><b>Rückwärtskompatibel:</b> Listener, die mit dem Namen angemeldet sind und am ersten Parameter keinen Ereignis-Typ haben, bekommen weiter die bisherigen Argumente. Das Objekt erlaubt zusätzlich Array-Zugriff zum Lesen (<code>$e['id']</code>, <code>$e['title']</code> = Feld des Datensatzes); Schreiben wirft eine <code>LogicException</code>.</p>
  <p><b>Ohne Typ</b> (nur Name, Argumente wie bisher): <code>media.imported</code> (<code>array $m</code>), <code>media.replaced</code> und <code>media.edited</code> (<code>array $neu, array $alt</code>), <code>media.deleted</code> (<code>array $m</code>), <code>inbox.status</code> und <code>inbox.deleted</code> (siehe <a href="#inbox-hooks">Eingangs-Tabellen</a>). Weitere Ereignisse kommen nur dazu, wenn eine Erweiterung sie wirklich braucht.</p>

  <h3 id="erweiterungen-sicherheit">Sicherheit</h3>
  <ul>
    <li><b>Server prüft immer:</b> Endpunkte unter <code>/admin</code> meldet die Erweiterung mit Recht an – der Router prüft Anmeldung, Recht und CSRF (<code>X-CSRF-Token</code>), bevor der Handler läuft (<a href="#erweiterungen-routen">Routen</a>). Die Angaben <code>perm</code>/<code>visible</code> an Werkzeugen und Slots steuern nur die Anzeige.</li>
    <li><b>Keine Besucher:</b> Werkzeugleiste, Konfiguration (<code>#cms-tools</code>), schwebender Knopf und Module gibt es nur angemeldet (Bearbeiten bzw. mit <code>view</code> auch Ansehen); Seiten im Seiten-Cache enthalten sie nie.</li>
    <li><b>Nur eigene Dateien:</b> Module von Erweiterungen kommen aus ihrem <code>assets</code>-Ordner (kein <code>..</code>, keine fremde Domain, CSP <code>'self'</code>); Endpunkte nur als Pfade dieser Installation.</li>
    <li><b>Isoliert:</b> Oberflächen in der Shadow-DOM-Ebene – kein Kit-CSS hinein, kein Werkzeug-CSS hinaus. Eigene Stile: Klassen der Verwaltung nutzen oder ein eigenes Stylesheet per <code>&lt;link&gt;</code> in <code>ctx.panel.el</code> anhängen (keine Inline-Styles, CSP).</li>
  </ul>
