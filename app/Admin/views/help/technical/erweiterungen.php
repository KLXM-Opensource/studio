<?php /** Entwicklerhandbuch · Erweiterungen: Verwaltungsseiten nach Art, Werkzeuge der Website, Ereignisse (Core\AdminPages, Core\FrontendTools) */ ?>
  <p class="lead">Erweiterungen (und Funktionen des Cores) erweitern die Verwaltung und das Bearbeiten auf der Website über drei stabile Schnittstellen: <b>Verwaltungsseiten mit Art</b> (wo eine Seite erscheint), <b>Werkzeuge der Website</b> (Knopf in der Werkzeugleiste, Seitenleiste in der Shadow-DOM-Ebene) und <b>Ereignisse</b> in PHP und im Browser. Referenzbeispiel ist das <a href="#glossar">Quick-Glossar</a> – der Core meldet es genau so an wie eine Erweiterung.</p>

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
    <tr><td><code>href</code>*, <code>label</code>*</td><td>Adresse in dieser Installation (beginnt mit <code>/</code>) und Beschriftung (übersetzbar über <code>lang/{locale}.php</code> der Erweiterung). Die Route meldet die Erweiterung mit <code>routes()</code> an – sie prüft Rechte und CSRF selbst.</td></tr>
    <tr><td><code>kind</code></td><td><code>content</code> | <code>tool</code> | <code>settings</code> | <code>stats</code> (siehe oben).</td></tr>
    <tr><td><code>place</code></td><td><code>main</code> (Hauptmenü, Standard) oder <code>admin</code> (Abschnitt Administration) – nur für <code>content</code>/<code>tool</code>.</td></tr>
    <tr><td><code>icon</code>, <code>description</code></td><td>Symbolname bzw. Menü-Schlüssel (<code>Core\Icons</code>) und eine Zeile für die Karte auf der Sammelseite.</td></tr>
    <tr><td><code>perm</code>, <code>feature</code>, <code>visible</code></td><td>Sichtbar nur mit Recht (<code>can()</code>), wenn die Funktion an ist (unbekannte Funktion = aus) und <code>visible</code> (<code>fn(): bool</code>) <code>true</code> liefert. Gilt für Menü, Karten, Knöpfe an Tabellen und die Suche (<kbd>⌘K</kbd>).</td></tr>
    <tr><td><code>table</code>, <code>tableLabel</code></td><td>Nur <code>settings</code>: Kurzname einer Datentabelle – die Seite erscheint zusätzlich dort (Beschriftung <code>tableLabel</code>, sonst <code>label</code>).</td></tr>
  </table>
  <div class="doc-note doc-note--warn"><strong>Veraltet: <code>nav()</code> ohne Art</strong><p>Ältere Aufrufe <code>$x-&gt;nav($href, $label, $icon, $perm)</code> bzw. <code>…, 'admin')</code> funktionieren weiter und bleiben an ihrem Platz (<code>main</code> → <code>content</code>, <code>admin</code> → <code>tool</code>). Bitte die Art angeben – reine Einstellungsseiten gehören nach <code>settings</code>, Berichte nach <code>stats</code>. <code>Extensions::adminNav()</code> ist veraltet; das Layout nutzt <code>AdminPages::nav()</code>.</p></div>
  <p><b>Einordnung im Core:</b> Hauptmenü = Übersicht, Seiten, Entwürfe, Website-Angaben des Kits, Medien, Daten, Anfragen, Chat, KI (Inhalte/Werkzeuge); Administration = Grundeinstellungen, Funktionen &amp; Erweiterungen, <b>Einstellungen</b> (Sammelseite), <b>Statistiken</b> (nur wenn vorhanden), Design, Blöcke, Landingpages, Weiterleitungen, Benutzer &amp; Rollen. Als <code>settings</code> gesammelt: <b>Glossar</b> (<code>/admin/glossar</code>, an der Tabelle <code>glossar</code> als „Prüfen &amp; Einstellungen“), <b>Chat</b> (<code>/admin/chat/einstellungen</code>), <b>API &amp; MCP</b> (<code>/admin/api-tokens</code>). Externe Quellen sind ein Werkzeug der Daten-Navigation. Adressen und Rechte sind unverändert.</p>
  <p>API: <code>AdminPages::register([...])</code> (Core), <code>all()</code>, <code>visible($p)</code>, <code>ofKind('settings', …)</code>, <code>nav('main'|'admin')</code>, <code>settings()</code> / <code>stats()</code> (gruppiert), <code>forTable($handle)</code>, <code>match($path)</code>. Selbsttest: <code>php bin/console extensions:selftest</code>.</p>

  <h3 id="erweiterungen-werkzeuge">Werkzeuge beim Bearbeiten auf der Website (<code>Core\FrontendTools</code>)</h3>
  <p>Ein Werkzeug ist ein Knopf in der Werkzeugleiste (<code>placement =&gt; 'main'</code>, auf Telefonen im Menü „⋯“) oder ein Eintrag im Menü „⋯“ (<code>'more'</code>), optional mit Tastenkürzel. Es erscheint <b>nur angemeldet, nur im Bearbeiten-Modus</b> (Seiten-Editor inkl. Vorlage: <code>page</code>; Eintrag direkt im Text: <code>entry</code>, dort erst nach „Bearbeiten“) und nur mit Recht. Das ES-Modul lädt der Browser <b>erst beim ersten Öffnen</b> – Besucher laden nichts, die Redaktion beim Ansehen auch nicht.</p>
  <pre><code>$x-&gt;frontendTool([
    'id' =&gt; 'notiz', 'label' =&gt; 'Notiz', 'icon' =&gt; 'note-pencil', 'placement' =&gt; 'main', 'shortcut' =&gt; 'Alt+N',
    'hint' =&gt; 'Kurze Notiz an der Schreibmarke',             // kleine Zeile im Menü „⋯“
    'module' =&gt; 'js/notiz.mjs',                              // {dir}/assets/js/notiz.mjs → /assets/ext/{name}/js/notiz.mjs
    'perm' =&gt; 'pages.edit', 'feature' =&gt; 'notiz',             // optional 'table' (Recht je Tabelle), 'visible' =&gt; fn(array $bar): bool
    'modes' =&gt; ['page', 'entry'],
    'panel' =&gt; ['title' =&gt; 'Notizen', 'size' =&gt; 'narrow'],    // narrow | wide
    'endpoints' =&gt; ['list' =&gt; '/admin/api/notiz'],          // eigene Routen – prüfen Recht + CSRF bei JEDEM Aufruf
    'data' =&gt; fn(array $bar) =&gt; ['max' =&gt; 200],              // je Werkzeugleiste, frei für das Modul
    'texts' =&gt; ['insert' =&gt; __('Einfügen')],                 // übersetzte Texte (auf der Website gibt es kein Wörterbuch)
]);</code></pre>
  <p><b>Quick-Glossar (Core, Referenz):</b> <code>Core\Glossary\QuickTool::definition()</code> liefert genau diese Angaben – <code>id</code> <code>glossary</code>, Kürzel <code>Alt+G</code>, <code>feature</code> <code>glossary</code>, <code>visible</code> = Tabelle eingerichtet und <code>data.edit</code> auf <code>glossar</code>, drei Endpunkte (<code>GlossaryController::api*</code>), Modul <code>resources/js/quick-glossary.mjs</code>.</p>
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
    <tr><td><code>page</code>, <code>entry</code>, <code>kind</code>, <code>mode</code>, <code>lang</code>, <code>csrf</code></td><td>Seite <code>{id, title, lang}</code> bzw. Eintrag <code>{table, id}</code>, Art der Werkzeugleiste (<code>page|entry|template</code>), Bearbeiten-Art (<code>page|entry</code>), Sprache, CSRF-Token.</td></tr>
    <tr><td><code>panel</code></td><td><code>{ el, body, setTitle(t), close(), focus() }</code> – Seitenleiste in der Shadow-DOM-Ebene (<code>#cms-layer-host</code>, <code>role="dialog"</code>, nicht modal). Stile: <code>admin.shadow.css</code> + <code>editor.shadow.css</code> (Klassen <code>adm-btn</code>, <code>f</code>, <code>f-help</code> …); Kit-CSS wirkt nicht hinein.</td></tr>
    <tr><td><code>t(key, params)</code></td><td>Text aus <code>tool.texts</code> (sonst gemeinsame Texte), Platzhalter <code>{name}</code>.</td></tr>
    <tr><td><code>fetch(url, { method, json, query })</code></td><td>JSON-Anfrage mit <code>X-CSRF-Token</code> und Sitzung; wirft bei Fehler (<code>error.status</code>, <code>error.data</code>).</td></tr>
    <tr><td><code>selection()</code></td><td>Gemerkte Stelle im zuletzt bearbeiteten Text: <code>{ editable, range, text, rich }</code> oder <code>null</code> – bleibt erhalten, während die Seitenleiste den Fokus hat.</td></tr>
    <tr><td><code>insertText(text)</code>, <code>insertLink({ href, ref, label, title, newTab })</code></td><td>An der gemerkten Schreibmarke einfügen bzw. den markierten Text verlinken (gleiche Logik wie die Linkauswahl, <code>Rich.insertLink</code>; stabile Verweise <code>page:ID</code>, <code>entry:{tabelle}:{id}</code>, <code>media:{id}</code> in <code>data-link</code>). Felder ohne Links bekommen nur den Text. Ergebnis <code>'link'|'text'|false</code> (kein Text aktiv). Löst <code>input</code> aus – der Editor merkt die Änderung.</td></tr>
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
  </table>
  <pre><code>// Beispiel: Veröffentlichen nur mit Alt-Text an allen Bildern
document.addEventListener('cms:before-save', e =&gt; {
  if (!e.detail.publish) return;
  e.detail.waitUntil(fetch('/admin/api/alt-pruefen').then(r =&gt; r.json()).then(r =&gt; {
    if (!r.ok) e.detail.reason = 'Bitte zuerst Alt-Texte ergänzen.';
    return r.ok;
  }));
});</code></pre>

  <h3 id="erweiterungen-hooks">Ereignisse auf dem Server (<code>$x-&gt;on()</code>)</h3>
  <p>Gemeldet über <code>Extensions::emit()</code> nach der Änderung, unabhängig vom Weg (Verwaltung, Website, REST-API, MCP, KI, Kommandozeile). Fehler einer Erweiterung werden protokolliert und brechen nichts ab. Teure Angaben berechnet der Core nur, wenn jemand zuhört (<code>Extensions::listens()</code>).</p>
  <table class="doc-table">
    <tr><th>Ereignis</th><th>Argumente</th><th>Auslöser</th></tr>
    <tr><td><code>page.saved</code></td><td><code>array $page, ?int $userId</code></td><td>Entwurf gespeichert (<code>Pages::saveDraft</code>)</td></tr>
    <tr><td><code>page.published</code></td><td><code>array $page</code></td><td>veröffentlicht bzw. wieder online (<code>Pages::publish</code>)</td></tr>
    <tr><td><code>page.unpublished</code></td><td><code>array $page</code></td><td>offline genommen (nur bei echter Änderung)</td></tr>
    <tr><td><code>page.discarded</code></td><td><code>array $page</code></td><td>Entwurf verworfen</td></tr>
    <tr><td><code>page.deleted</code></td><td><code>array $page</code> (Stand vor dem Löschen)</td><td>Seite gelöscht (Verwaltung, API/MCP)</td></tr>
    <tr><td><code>entry.saved</code></td><td><code>array $table, array $entry, bool $created, ?array $old</code></td><td>Eintrag angelegt/geändert (<code>Entries::save</code>, auch Quellen-Abgleich und Formulare)</td></tr>
    <tr><td><code>entry.published</code>, <code>entry.unpublished</code></td><td><code>array $table, array $entry</code></td><td>Status wechselt auf veröffentlicht bzw. Entwurf (<code>save</code>, <code>setStatus</code> – nur bei echter Änderung)</td></tr>
    <tr><td><code>entry.deleted</code></td><td><code>array $table, int $id</code></td><td>Eintrag gelöscht</td></tr>
    <tr><td><code>media.imported</code>, <code>media.replaced</code>, <code>media.edited</code>, <code>media.deleted</code></td><td><code>$m</code> (bei replaced/edited zusätzlich die alte Fassung)</td><td>Mediathek</td></tr>
    <tr><td><code>inbox.status</code>, <code>inbox.deleted</code></td><td>siehe <a href="#inbox-hooks">Eingangs-Tabellen</a></td><td>Anfragen</td></tr>
  </table>
  <pre><code>$x-&gt;on('entry.published', function (array $t, array $e): void {
    if ($t['handle'] === 'news') Newsletter::queue((int) $e['id']);
});</code></pre>

  <h3 id="erweiterungen-sicherheit">Sicherheit</h3>
  <ul>
    <li><b>Server prüft immer:</b> Jeder Endpunkt eines Werkzeugs ruft <code>AdminController::auth()</code> (Anmeldung, bei POST CSRF über <code>X-CSRF-Token</code>) und prüft das Recht selbst – die Angaben <code>perm</code>/<code>visible</code> steuern nur die Anzeige.</li>
    <li><b>Keine Besucher:</b> Werkzeugleiste, Konfiguration (<code>#cms-tools</code>) und Module gibt es nur angemeldet und nur im Bearbeiten-Modus; Seiten im Seiten-Cache enthalten sie nie.</li>
    <li><b>Nur eigene Dateien:</b> Module von Erweiterungen kommen aus ihrem <code>assets</code>-Ordner (kein <code>..</code>, keine fremde Domain, CSP <code>'self'</code>); Endpunkte nur als Pfade dieser Installation.</li>
    <li><b>Isoliert:</b> Oberflächen in der Shadow-DOM-Ebene – kein Kit-CSS hinein, kein Werkzeug-CSS hinaus. Eigene Stile: Klassen der Verwaltung nutzen oder ein eigenes Stylesheet per <code>&lt;link&gt;</code> in <code>ctx.panel.el</code> anhängen (keine Inline-Styles, CSP).</li>
  </ul>
