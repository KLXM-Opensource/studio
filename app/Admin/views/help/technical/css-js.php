<?php
/**
 * Entwicklerhandbuch · CSS & JS (Anker #css-js) – Kern-Referenz für alle Websites: Ebenen, Laden, CSP, Namen, Variablen,
 * data-*-Attribute, globale Objekte, Ereignisse, Bewegung, Regeln für Kits. Die Tabellen „Generiert“ kommen aus
 * Core\AssetDocs (gecacht in storage/cache/asset-docs-core.json, neu bei jeder Änderung der Quellen) – dieselben Daten
 * schreibt php bin/console docs:assets als Markdown.
 */
$__ad = \Core\AssetDocs::core();
try { $__adKit = \Core\AssetDocs::kit(app()->theme->path); } catch (\Throwable) { $__adKit = null; }
$__adKitDoc = is_file(app()->theme->path . '/docs/css-js.md');
$__v = fn(?string $s) => $s === null || $s === '' ? '–' : '<code>' . e($s) . '</code>';
?>
  <p class="lead">Wie CSS und JavaScript im Kern geschichtet sind, wie sie laden und was ein Kit davon benutzen, überschreiben oder in Ruhe lassen muss. Gilt für alle Websites und Kits; die Tabellen mit „generiert“ entstehen aus den Quellen (<code>Core\AssetDocs</code>) und sind damit immer auf dem Stand dieses Builds. Frameworks wie Tailwind, UIkit oder Bootstrap in einem Kit: <a href="#frameworks">Frameworks (Tailwind, UIkit, Bootstrap)</a>.</p>

  <h3 id="css-js-ebenen">Vier Ebenen</h3>
  <table class="doc-table">
    <tr><th>Ebene</th><th>Dateien</th><th>Wo, für wen</th><th>Isolation</th></tr>
    <tr><td><b>Verwaltung</b></td><td><code>admin.css</code> (+ <code>_*.css</code> per <code>@import</code>), <code>admin.js</code> (+ <code>_*.js</code>), Seiten-Skripte wie <code>dashboard.js</code></td><td><code>/admin/…</code> (<code>app/Admin/views/layout.php</code>), <code>&lt;html class="adm-ui"&gt;</code></td><td>Eigenes Dokument ohne Kit-CSS. Tokens <code>--adm-*</code> an <code>:root</code>, Hell/Dunkel über <code>html.adm-ui[data-theme]</code>.</td></tr>
    <tr><td><b>Editor auf der Website</b></td><td>In der Seite: <code>editor.css</code> (Rahmen, <code>[data-edit]</code>, Editor.js, Platz für Leisten, Z-Skala). Im Schatten: <code>editor.shadow.css</code> + <code>admin.shadow.css</code> (Build: <code>admin.css</code> mit <code>:root</code> → <code>:host</code>). Skripte <code>admin.js</code>, <code>editor.js</code> (Bündel mit <code>_shadow.js</code>, <code>_bar.js</code>, <code>_tools.js</code>, <code>_entry_edit.js</code> …)</td><td>Nur angemeldet mit Recht: Kit-Layout gibt <code>$toolbar</code>/<code>$editor</code> aus; <code>&lt;html class="is-editing"&gt;</code> im Seiten-Editor, <code>cms-has-bar</code> mit Werkzeugleiste</td><td>Werkzeugleiste (<code>.cms-bar-host</code>, Declarative Shadow DOM), gemeinsame Ebene <code>#cms-layer-host</code> (Seitenleiste „Block“, Dialoge, Formatierungsleiste, Meldungen), <code>#cms-epanel-host</code> (Eintrag bearbeiten). Kit-CSS erreicht sie nicht, sie erreichen das Kit nicht. Details: <a href="#shadow-dom">Isolation per Shadow DOM</a>.</td></tr>
    <tr><td><b>Öffentliche Kern-Bausteine</b></td><td><?php foreach ($__ad['css'] as $__f => $__c): ?><code><?= e($__f) ?></code> <?php endforeach; ?>– Skripte <code>map.mjs</code>, <code>media.mjs</code>, <code>hero.mjs</code>, <code>dials.mjs</code>, <code>partners.mjs</code>, <code>glossary.js</code>/<code>glossary-live.mjs</code>, <code>dataform.js</code>, <code>search.js</code>, <code>embed.js</code>, <code>visitor-chat-start.js</code> → <code>visitor-chat.mjs</code>; Einwilligung aus der Erweiterung Consent-Kit</td><td>Jede Website, in jedem Kit gleich; laden nur, wo der Baustein steht</td><td>Keine – sie liegen im Kit-DOM und erben Schrift und Farbe. Gestaltung über die Variablen unten oder eine Kit-Datei gleichen Namens. Ausnahme: Besucher-Chat (Shadow DOM, nur Akzent und <code>--cms-chat-lift</code>).</td></tr>
    <tr><td><b>Kit</b></td><td><code>kits/{name}/assets/css|js</code> → <code>/assets/kits/{name}/…</code>: <code>site.css</code>/<code>site.js</code> immer, Block-Dateien per <code>conditional_css</code></td><td>Je Website (<a href="#kits">Kits &amp; Design</a>)</td><td>Darf die Website frei gestalten – aber nie die Ebenen darüber (siehe <a href="#css-js-regeln">Regeln</a>).</td></tr>
  </table>

  <h3 id="css-js-laden">Laden: immer, bedingt, nachgeladen</h3>
  <ul>
    <li><b>Immer:</b> was das Kit-Layout (<code>templates/layout.php</code>) mit <code>theme_asset('css/site.css')</code> einbindet – Budgets: <a href="#build">Build &amp; Entwicklung</a>.</li>
    <li><b>Je Block:</b> <code>theme.php → 'conditional_css' =&gt; ['css/b-faq.css' =&gt; ['faq'], 'js/tabs.js' =&gt; ['tabs'], 'css/hero-x.css' =&gt; ['hero:search'], 'css/rich.css' =&gt; ['@rich']]</code> – gilt für <code>.css</code> <i>und</i> <code>.js</code> (Name der Datei entscheidet), Schlüssel „typ“, „typ:variante“ oder <code>@rich</code> (Rich-Text-Formate auf der Seite). Ausgewertet werden alle Blöcke einschließlich der Spalten im Layout (<code>SiteController::types()</code>); <code>'uses'</code> in der Blockdefinition zieht die Dateien eines anderen Blocks mit. Das Layout gibt sie als <code>$extraCss</code>/<code>$extraJs</code> aus (Skripte mit <code>defer</code>).</li>
    <li><b>Kern-Stylesheets je Block</b> lädt der Kern selbst (<code>Theme::conditionalCss</code>) – eine Datei gleichen Namens unter <code>kits/{name}/assets/css/</code> <b>ersetzt</b> sie (nicht ergänzt):
      <table class="doc-table">
        <tr><th>Kern-Datei</th><th>Blöcke</th></tr>
        <?php foreach (\Core\AssetDocs::CORE_CONDITIONAL as $__f => $__t): ?><tr><td><code>css/<?= e($__f) ?></code></td><td><?= e(implode(', ', $__t)) ?></td></tr><?php endforeach; ?>
        <tr><td><code>css/sections.css</code>, <code>css/rows.css</code></td><td>Abschnitte mit Vollbild/Hintergrundbild bzw. alte Reihen</td></tr>
      </table>
      Andere Bausteine binden ihre Datei an Ort und Stelle ein, einmal je Seite (<code>Core\Maps</code> → <code>map.css</code> + <code>map.mjs</code>, <code>Core\Glossary\Glossary</code> → <code>glossary.css</code>, ergänzt um <code>css/glossary.css</code> des Kits, <code>Core\Embeds</code> → <code>embed.js</code> …). Liste aller Lader: generierte Tabelle unten.</li>
    <li><b>Nachladen:</b> <code>*.mjs</code> baut esbuild als ES-Modul (sonst IIFE), eingebunden als <code>&lt;script type="module"&gt;</code>; große Teile kommen erst bei Bedarf per <code>import()</code> (MapLibre erst in Sichtweite bzw. nach Klick, PDF.js, Chat-Fenster erst nach dem Klick auf den Starter ≤ 1 KB). Pfade <code>/assets/*</code> und <code>../vendor/*</code> bleiben beim Bündeln extern.</li>
    <li><b>Versionierung:</b> <code>asset('css/x.css')</code> (Kern), <code>theme_asset()</code> bzw. <code>app()-&gt;theme-&gt;asset()</code> (Kit) und <code>$ext-&gt;asset()</code> (Erweiterung) hängen <code>?v=</code> + 8 Zeichen aus dem MD5 der Änderungszeit an – ändert sich die Datei, ändert sich die Adresse; lange Cache-Zeiten sind damit sicher. Adressen nie fest eintragen: Kern <code>/assets/css|js/…</code>, Kits <code>/assets/kits/{name}/…</code>, Erweiterungen <code>/assets/ext/{name}/…</code>; in CSS relative Pfade (<code>url(../fonts/x.woff2)</code>).</li>
  </ul>
  <h4>Content-Security-Policy</h4>
  <p>Website (<code>SiteController::csp()</code>): <code>default-src 'self'</code>, <code>script-src 'self'</code>, <code>style-src 'self'</code> – für Besucher <b>ohne</b> <code>'unsafe-inline'</code>, keine Nonces, keine Hashes. Angemeldete bekommen <code>style-src 'unsafe-inline'</code> nur, weil Editor.js Stile einfügt; <code>worker-src blob:</code> für MapLibre; <code>frame-src</code> YouTube (nocookie)/Vimeo + <code>theme.php → 'frame_hosts'</code>; Erweiterungen ergänzen Quellen je Anfrage mit <code>$x-&gt;csp()</code> (nie <code>'unsafe-inline'</code>). Daraus folgt für Kits:</p>
  <ul>
    <li>Kein <code>&lt;script&gt;…&lt;/script&gt;</code>, kein <code>onclick=</code>, kein <code>javascript:</code>, kein <code>&lt;style&gt;</code>, kein <code>style="…"</code> im Markup. Ausnahmen, die die CSP nicht betrifft: <code>&lt;script type="application/json"&gt;</code> bzw. <code>application/ld+json</code> (Daten, kein Code) und das CSSOM aus eigenen Skripten (<code>el.style.setProperty('--x', …)</code>).</li>
    <li>Werte je Element als Klasse aus einer festen Skala (Kern-Muster: <code>.ar-5</code>, <code>.lay-col--w2</code>, <code>.pl-w-6</code>, <code>.ifx-b5</code>) oder als <code>data-*</code>, das ein Skript ausliest. Bilder je Einbindung (Ausschnitt, Filter) erzeugen eigene kleine Stylesheets (<code>Core\ImageFit</code>, <code>Core\ImageFx</code>).</li>
    <li>Design-Tokens aus dem Style-Editor kommen als erzeugte Datei (<code>design_head()</code> → <code>&lt;link&gt;</code>), nur die Vorschau im Editor nutzt <code>&lt;style data-design-preview&gt;</code>.</li>
  </ul>

  <h3 id="css-js-namen">Namen und Präfixe</h3>
  <table class="doc-table">
    <tr><th>Präfix</th><th>Gehört zu</th><th>Status</th></tr>
    <tr><td><code>adm-</code>, <code>--adm-*</code>, <code>html.adm-ui</code></td><td>Verwaltung (auch im Schatten auf der Website)</td><td>intern – Kits fassen es nie an</td></tr>
    <tr><td><code>cms-</code></td><td>Editor und Werkzeugleiste (<code>cms-bar</code>, <code>cms-block</code>, <code>cms-drawer</code>, <code>cms-note</code> …) und öffentliche Kern-Bausteine (<code>cms-map</code>, <code>cms-gallery</code>, <code>cms-slider</code>, <code>cms-stack</code>, <code>cms-lb</code>, <code>cms-dials</code>, <code>cms-partners</code>, <code>cms-404</code>, <code>cms-chat</code>)</td><td>Editor: intern. Bausteine: Klassen sind lesbar/gestaltbar, Markup nicht ersetzbar</td></tr>
    <?php foreach ($__ad['css'] as $__f => $__c): if (str_starts_with($__c['prefix'], 'cms-') || $__f === 'editor.css') continue; ?><tr><td><code><?= e($__c['prefix']) ?></code></td><td><?= e($__c['label']) ?> (<code><?= e($__f) ?></code>)</td><td>öffentlich: Klassen + Variablen <?= $__c['vars'] ? implode(', ', array_map(fn($v) => '<code>' . e($v) . '*</code>', $__c['vars'])) : '' ?></td></tr><?php endforeach; ?>
    <tr><td><code>vembed</code></td><td>2-Klick-Video (Kern-Fragment <code>video-embed</code>)</td><td>öffentlich: Klassen (Liste unter <a href="#fragmente">Kern-Fragmente</a>)</td></tr>
    <tr><td><code>t-lead</code>, <code>t-small</code>, <code>t-note</code>, <code>c-*</code>, <code>--rt-*</code></td><td>Rich-Text-Formate</td><td>öffentlich – Kit gestaltet sie (<a href="#rich-text-stile">Klassenvertrag</a>)</td></tr>
    <tr><td><code>sec</code>, <code>sec--{typ}</code>, <code>bg-{name}</code>, <code>v-{variante}</code>, <code>lay-item</code></td><td>Abschnitts-Hülle aus <code>$b-&gt;sectionClass()</code></td><td>öffentlich – Kit gestaltet sie</td></tr>
    <tr><td><code>is-*</code>, <code>has-*</code></td><td>Zustände (<code>is-editing</code>, <code>is-open</code>, <code>is-loaded</code>, <code>is-paused</code> …)</td><td>vom Kern gesetzte lesen; eigene am eigenen Block</td></tr>
    <tr><td>ein bis drei Buchstaben je Kit: <code>--b-*</code> basis, <code>--m-*</code> modern, <code>--e-*</code> essenz/editorial, <code>--n-*</code> nature, <code>--f-*</code> fluid, <code>--g-*</code> glas, <code>--s-*</code> starter, <code>--c-*</code> praxis, <code>--k-*</code> KLXM-Kits</td><td>Tokens des Kits</td><td>gehört dem Kit; der Kern liest einige als Rückfall-Kette (z. B. <code>--gl-accent</code> ← <code>--b-link</code> ← <code>--m-link</code> …), setzt sie aber nie</td></tr>
  </table>
  <ul>
    <li><b>BEM</b> für Komponenten: <code>block__element--modifier</code> (<code>.cms-map__canvas</code>, <code>.dl-item--featured</code>, <code>.ldlg__close</code>); kurze Präfixe statt langer Namen, weil die Klassen im HTML jeder Seite stehen.</li>
    <li><b>Öffentlich</b> sind die Klassen und Variablen mit dem Präfix einer Komponente aus der Tabelle oben, die Zustandsklassen und die <code>data-*</code>-Attribute unter <a href="#css-js-data">data-*</a>. <b>Intern</b> sind Variablen mit <code>--_</code> (z. B. <code>--_acc</code> im Glossar), alles in Schatten-Wurzeln, Hilfsvariablen ohne Präfix (<code>--cols</code>, <code>--ar</code>, <code>--i</code>), <code>data-bar-*</code>, <code>data-editor-*</code>, <code>data-drawer-*</code>, <code>data-ff-*</code> und alles mit <code>adm-</code>. Interna können sich ohne Ankündigung ändern.</li>
    <li>Kit-eigene Klassen und Variablen mit dem Kit-Präfix (siehe <code>--b-*</code> in basis), Block-Dateien als <code>b-{block}.css</code>/<code>b-{block}.js</code> – dann ist auch ohne Tabelle klar, was wohin gehört.</li>
  </ul>

  <h3 id="kit-vertrag">Kit-Vertrag: Grundwerte <code>--kit-*</code> für Kern und Erweiterungen</h3>
  <p>Jedes Kit setzt einmal einen festen Satz Variablen. Kern-Bausteine (Suche, Glossar, Kopfbereich-Aktionen, Besucher-Chat, Formulare, Partner, Kennzahlen) und Erweiterungen (Buchung, Check, Mitgliederbereich …) lesen <b>nur diese Namen</b> – nie die Tokens eines bestimmten Kits. So passt jede Erweiterung zu jedem Kit, auch zu künftigen, ohne dass sie Kit-Präfixe kennen muss. <code>php bin/console kit:check</code> meldet fehlende Pflicht-Rollen.</p>
  <table class="doc-table">
    <tr><th>Variable</th><th>Rolle</th><th></th></tr>
    <tr><td><code>--kit-accent</code></td><td>Akzent: Auswahl, Fokus, Knöpfe, Markierungen</td><td>Pflicht</td></tr>
    <tr><td><code>--kit-on-accent</code></td><td>Schrift auf dem Akzent (mind. 4,5:1)</td><td>Pflicht</td></tr>
    <tr><td><code>--kit-link</code></td><td>Linkfarbe (oft gleich dem Akzent)</td><td>Pflicht</td></tr>
    <tr><td><code>--kit-muted</code></td><td>Nebentext (mind. 4,5:1 auf dem Hintergrund)</td><td>Pflicht</td></tr>
    <tr><td><code>--kit-radius</code></td><td>Rundung von Feldern, Karten, Knöpfen</td><td>Pflicht</td></tr>
    <tr><td><code>--kit-ink</code>, <code>--kit-text</code></td><td>Überschriften, Fließtext</td><td>empfohlen</td></tr>
    <tr><td><code>--kit-bg</code>, <code>--kit-surface</code>, <code>--kit-line</code></td><td>Hintergrund, getönte Fläche, Linie</td><td>empfohlen</td></tr>
    <tr><td><code>--kit-font</code>, <code>--kit-font-head</code></td><td>Schrift für Text bzw. Überschriften</td><td>empfohlen</td></tr>
  </table>
  <p><b>Wo setzen:</b> Stellt das Kit seine Farben in Bändern, Kopf oder Fuß um (dunkle Abschnitte, Karten auf Bildern), leitet jedes Element den Vertrag aus seinen eigenen Tokens ab – mit <code>:where(*)</code> (ohne Spezifität). An <code>:root</code> allein würden Bausteine in einem dunklen Band die hellen Farben der Seite erben. Kits ohne solche Umstellungen genügt <code>:root</code> plus feste Werte je Band (Beispiel: praxis).</p>
  <pre><code>/* kits/{name}/assets/css/_tokens.css */
:where(*){--kit-accent:var(--b-a);--kit-on-accent:var(--b-a-on);--kit-link:var(--b-link);--kit-ink:var(--b-ink);
  --kit-text:var(--b-text);--kit-muted:var(--b-muted);--kit-bg:var(--b-bg);--kit-surface:var(--b-surface);
  --kit-line:var(--b-line);--kit-radius:var(--b-radius);--kit-font:var(--b-font);--kit-font-head:var(--b-font-head)}</code></pre>
  <ul>
    <li><b>Nur lesen:</b> Farben ändert das Kit über seine eigenen Tokens; ein an einem Element gesetztes <code>--kit-*</code> erben dessen Kinder nicht (dort leitet <code>:where(*)</code> neu ab).</li>
    <li><b>Lesen mit Rückfall:</b> <code>var(--kit-accent,currentColor)</code> bzw. die bisherige Kette dahinter – Kits ohne Vertrag (ältere, eigene) bleiben so lesbar.</li>
    <li>Die Variablen der einzelnen Bausteine (<code>--se-*</code>, <code>--gl-*</code>, <code>--dff-*</code> …) gibt es weiter – für Feinheiten setzt das Kit sie zusätzlich.</li>
  </ul>

  <h3 id="css-js-variablen">Anpassen: Variablen der Kern-Bausteine</h3>
  <p>Jeder öffentliche Baustein liest Variablen mit seinem Präfix; fehlen sie, gilt die Vorgabe (meist aus <code>currentColor</code>/<code>Canvas</code> abgeleitet, damit der Baustein ohne Kit-Anpassung hell und dunkel lesbar bleibt). Ein Kit setzt sie an der Komponente oder an <code>:root</code> – und im Dunkelmodus erneut:</p>
  <pre><code>/* kits/{name}/assets/css/site.css */
:root{--cms-map-accent:var(--b-a);--gl-line:color-mix(in srgb,var(--b-a) 60%,transparent);--lay-stack:1000px}
.dl,.df{--dl-radius:var(--b-radius);--dl-surface:var(--b-surface)}
@media (prefers-color-scheme:dark){:root{--cms-map-bg:#1b1d22}}</code></pre>
  <p>Variablen, die an einer Klasse <i>gesetzt</i> werden (Art „gesetzt an …“), muss das Kit mindestens mit derselben Spezifität setzen (dieselbe Klasse in <code>site.css</code> nach dem Kern-Stylesheet bzw. mit höherer Spezifität, weil Block-Stylesheets später laden). Variablen der Art „Rückfall“ liest der Baustein nur und setzt sie nie – hier genügt jede Ebene darüber. Reicht das nicht, ersetzt eine Kit-Datei gleichen Namens das Kern-Stylesheet ganz (dann gehört die Pflege dem Kit).</p>
  <div class="doc-faq">
  <?php foreach ($__ad['css'] as $__f => $__c): ?>
    <details><summary><code><?= e($__f) ?></code> – <?= e($__c['label']) ?> (<?= count($__c['props']) ?> Variablen, generiert)</summary><div>
      <p>Klassen-Präfix <code><?= e($__c['prefix']) ?></code> · geladen: <?= e($__c['loader']) ?></p>
      <?php if ($__c['props']): ?>
      <table class="doc-table"><tr><th>Variable</th><th>Vorgabe</th><th>Art</th></tr>
        <?php foreach ($__c['props'] as $__n => $__p): ?><tr><td><code><?= e($__n) ?></code></td><td><?= $__v($__p['default']) ?></td><td><?= e($__p['kind']) ?><?= $__p['where'] !== '' ? ' an ' . $__v($__p['where']) : '' ?></td></tr><?php endforeach; ?>
      </table>
      <?php else: ?><p>Keine öffentlichen Variablen.</p><?php endif; ?>
      <?php if ($__c['chain']): ?><p>Intern bzw. gelesene Kit-Tokens (keine Schnittstelle): <?= implode(', ', array_map(fn($n) => '<code>' . e($n) . '</code>', $__c['chain'])) ?></p><?php endif; ?>
    </div></details>
  <?php endforeach; ?>
  </div>

  <h3 id="css-js-data">data-*-Attribute</h3>
  <table class="doc-table">
    <tr><th>Attribut</th><th>Gesetzt von</th><th>Bedeutung, Regel für Kits</th></tr>
    <tr><td><code>data-edit="pfad"</code>, <code>data-edit-mode="rich|inline"</code></td><td><code>$b-&gt;edit('title')</code>, <code>$b-&gt;edit('text', 'rich')</code></td><td>Nur im Seiten-Editor: Feld direkt im Text bearbeitbar; Pfad mit Punkten für Listen (<code>items.0.title</code>), im Layout mit Präfix <code>columns.{s}.blocks.{n}.data.</code>. Kit gibt das Attribut an das Element, das genau den Text enthält – nie an einen Link mit <code>::after</code> über die ganze Karte.</td></tr>
    <tr><td><code>data-bound</code></td><td><code>$b-&gt;edit()</code> in Detailseiten-Vorlagen</td><td>Feld kommt aus dem Eintrag, im Vorlagen-Editor nicht bearbeitbar.</td></tr>
    <tr><td><code>data-entry-field</code>, <code>data-entry-edit</code>, <code>data-entry-mode</code></td><td><code>Core\Data\EntryEdit</code></td><td>Eintrag direkt auf der Detailseite bearbeiten bzw. Stift in Datenlisten.</td></tr>
    <tr><td><code>data-central="Bezeichnung"</code></td><td><code>$b-&gt;central()</code></td><td>Nur im Editor: Inhalt kommt aus den zentralen Angaben (Adresse, Zeiten …) – Rahmen und Hinweis „Zentral gepflegt“.</td></tr>
    <tr><td><code>data-media-id</code></td><td><code>img()</code> im Editor</td><td>Bild-Werkzeuge (Zuschnitt, Anpassen, Rahmen) am Bild.</td></tr>
    <tr><td><code>data-cms-header</code>, <code>data-cms-sticky</code>, <code>data-cms-pinned</code></td><td>Kit (<code>data-cms-sticky</code> am klebenden Kopf) bzw. <code>_shadow.js</code></td><td>Kopf der Website: liegt im Bearbeiten über den Block-Leisten und rückt unter die Werkzeugleiste (<code>top: var(--cms-bar-offset)</code>), solange er klebt. Kits mit klebendem Kopf setzen <code>data-cms-sticky</code> oder <code>top: var(--cms-toolbar-h, 0)</code>.</td></tr>
    <tr><td><code>data-link="page:12#anker"</code></td><td>Rich Text (<code>Core\Sanitizer</code>)</td><td>Stabiler Verweis, <code>href</code> wird bei jeder Ausgabe neu berechnet.</td></tr>
    <tr><td><code>data-glossary="off|live"</code></td><td>Kit oder Abschnitts-Option</td><td><code>off</code>: hier keine Glossar-Begriffe markieren (z. B. Navigation, Logos); <code>live</code>: Bereich, den ein Skript später füllt – <code>glossary-live.mjs</code> markiert nach.</td></tr>
    <tr><td><code>data-cms-map</code>, <code>data-click</code>, <code>data-cms-map-js</code></td><td><code>Core\Maps</code></td><td>Karte (Konfiguration als JSON); Zwei-Klick; Adresse von <code>map.mjs</code> für Kits mit eigenem Lader (<code>'map_loader' =&gt; 'kit'</code>).</td></tr>
    <tr><td><code>data-embed</code>, <code>data-embed-play</code>, <code>data-embed-remember</code></td><td>Fragment <code>video-embed</code></td><td>2-Klick-Video; lädt nach Klick oder Einwilligung (<code>cms:consent</code>).</td></tr>
    <tr><td><code>data-cms-lightbox</code>, <code>data-hero-video</code>, <code>data-hero-marquee</code>, <code>data-l-pause</code>/<code>data-l-play</code></td><td>Kern-Blöcke Galerie, Einstieg</td><td>Lightbox bzw. Bewegung mit Pausenknopf; <code>data-l-*</code> = übersetzte Beschriftungen für Skripte (Muster auch für Kits).</td></tr>
    <tr><td><code>data-cms-chat</code></td><td><code>Core\AI\VisitorChat</code></td><td>Starter des Besucher-Chats (lädt Fenster und CSS erst nach dem Klick).</td></tr>
    <tr><td><code>data-reveal</code></td><td>Kern-Datenliste</td><td>Einblenden beim Scrollen – das Kit gestaltet es (essenz, fluid, glas: <code>site.js</code> + <code>_base.css</code>); <code>editor.css</code> hebt es im Editor auf. Endbild ohne JavaScript und bei „Bewegung reduzieren“ sichtbar.</td></tr>
  </table>
  <p><b>Eigene Attribute im Kit</b> mit sprechendem Namen je Funktion, kein Präfix nötig, aber nie <code>data-cms-*</code>, <code>data-edit*</code>, <code>data-entry-*</code> oder Namen aus dieser Tabelle mit anderer Bedeutung. Muster aus den KLXM-Kits: <code>data-inview</code> (ein gemeinsamer IntersectionObserver setzt es, solange ein Element sichtbar ist; CSS hält Animationen ohne das Attribut an), <code>data-loop-box</code>/<code>data-loop-toggle</code> (stummes Video mit Pausenknopf), <code>data-sheet</code>/<code>data-sheet-head</code> (Inhalt als Karte über der Seite), <code>data-office</code>/<code>data-zeit</code> (Zustand am <code>&lt;html&gt;</code>, vor dem ersten Zeichnen gesetzt). Solche Attribute gehören in die Kit-Seite „CSS &amp; JS“ (<a href="#kit-css-js">Standard</a>).</p>
  <div class="doc-faq"><details><summary>Alle <code>data-*</code>-Attribute in Kern-Vorlagen (<?= count($__ad['data']) ?>, generiert – öffentlich nur die oben erklärten)</summary><div>
    <table class="doc-table"><tr><th>Attribut</th><th>Fundstellen</th></tr>
      <?php foreach ($__ad['data'] as $__a => $__files): ?><tr><td><code><?= e($__a) ?></code></td><td><?= e(implode(', ', array_slice(array_unique($__files), 0, 4))) ?><?= count(array_unique($__files)) > 4 ? ' …' : '' ?></td></tr><?php endforeach; ?>
    </table>
  </div></details></div>

  <h3 id="css-js-js">JavaScript: globale Objekte und Ereignisse</h3>
  <table class="doc-table">
    <tr><th>Objekt</th><th>Wo</th><th>Inhalt</th></tr>
    <tr><td><code>CMSAdmin</code></td><td>Verwaltung und Bearbeiten auf der Website (<code>admin.js</code>)</td><td>Stabil: <code>tools.register(id, {mount, unmount})</code>, <code>events.emit(name, detail)</code>, <code>events.beforeSave</code>, <code>openMediaPicker</code>, <code>pickLink</code>/<code>openLinkPicker</code>, <code>Rich</code> (Formatierungsleiste), <code>Markdown</code>, <code>t()</code> (Übersetzung), <code>ico()</code>, <code>esc()</code>. Intern: <code>shadow</code> (Helfer der Schatten-Ebene), <code>bar</code>, <code>formFields</code>.</td></tr>
    <tr><td><code>CMSEditor</code></td><td>Seiten-Editor (<code>editor.js</code>)</td><td>Brücke für Bild-Werkzeuge (<code>fx.target(img)</code> …) – intern.</td></tr>
    <tr><td><code>CMSMedia</code></td><td>Mediathek (<code>_media.js</code>)</td><td><code>pick()</code>, <code>crop()</code>, <code>Finder</code>, <code>Uploader</code>, <code>extend()</code> (eigene Quellen) – für Erweiterungen.</td></tr>
    <tr><td><code>CMSAi</code>, <code>cmsAssistant</code></td><td>KI-Funktionen, Assistent</td><td>intern</td></tr>
    <tr><td><code>cmsConsent</code></td><td>Erweiterung Consent-Kit</td><td><code>embed(anbieter)</code> → true/false/null (nicht verwaltet); Ereignis <code>cms:consent</code></td></tr>
    <tr><td><code>cmsSearch</code>, <code>__klxmLegal</code>, <code>__klxmPasskeys</code></td><td>Suche, Rechtstext-Dialog, Passkeys</td><td>Merker gegen doppeltes Laden – keine Schnittstelle</td></tr>
  </table>
  <p>Kits legen höchstens ein Objekt mit Kit-Präfix an (<code>window.praxisForm</code>), besser gar keins – Skripte als IIFE bzw. Modul, Kommunikation über Ereignisse.</p>
  <p><b>Ereignisse</b> sind <code>CustomEvent</code> am <code>document</code> (Ausnahmen genannt). Payloads und Beispiele für Werkzeuge: <a href="#erweiterungen-ereignisse">Erweiterungen › Ereignisse im Browser</a>, Werkzeug-API: <a href="#erweiterungen-werkzeuge">Werkzeuge beim Bearbeiten</a> – hier die Übersicht für Kits:</p>
  <table class="doc-table">
    <tr><th>Ereignis</th><th>Payload (<code>e.detail</code>)</th><th>Für Kits</th></tr>
    <tr><td><code>cms:editor-ready</code></td><td><code>{kind: 'page', page, template, entry}</code> | <code>{kind: 'entry', entry, fields}</code></td><td>Editor bereit – z. B. Animationen anhalten, Karussell auf Folie 1</td></tr>
    <tr><td><code>cms:block-select</code></td><td><code>{kind, id, type, label, el}</code></td><td>gewählten Block sichtbar machen (Reiter, Akkordeon öffnen)</td></tr>
    <tr><td><code>cms:before-save</code></td><td><code>{kind, publish, page, blocks | entry, fields, status}</code>, <code>waitUntil(promise)</code>, abbrechbar</td><td>nur Werkzeuge/Erweiterungen</td></tr>
    <tr><td><code>cms:saved</code>, <code>cms:published</code></td><td><code>{kind, page | entry, publish, status, savedAt}</code></td><td>Kit-Skripte, die Inhalte zwischenspeichern, neu aufbauen</td></tr>
    <tr><td><code>cms:status-changed</code></td><td><code>{kind, status: 'published'|'offline'|'draft', via?, id?}</code></td><td>–</td></tr>
    <tr><td><code>cms:mode-change</code></td><td><code>{mode: 'view'|'edit'|'template'}</code></td><td>Detailseite wechselt ohne Neuladen zwischen Ansehen und Bearbeiten: Bewegung an/aus</td></tr>
    <tr><td><code>cms:tool-open</code>, <code>cms:tool-close</code></td><td><code>{id}</code></td><td>–</td></tr>
    <tr><td><code>cms:consent</code></td><td>Stand der Einwilligung</td><td>gesperrte Inhalte nach Einwilligung laden (wie <code>embed.js</code>)</td></tr>
    <tr><td><code>dff:sent</code> (am Formular, <code>bubbles</code>)</td><td>Antwort des Servers</td><td>nach dem Absenden eines Datentabellen-Formulars (z. B. Verfügbarkeit neu laden)</td></tr>
    <tr><td><code>cms:entry-status</code>, <code>adm:drawer</code>, <code>adm:location</code></td><td>–</td><td>intern</td></tr>
  </table>
  <ul>
    <li><b><code>cms:*</code> sendet nur der Kern.</b> Kits und Erweiterungen hören zu und senden unter eigenem Präfix (Kit-Name, z. B. <code>praxis:</code>, Erweiterung wie <code>consentkit:change</code>).</li>
    <li><b>Allgemeines Muster für Kit-Ereignisse</b> (so in den KLXM-Kits): <code>{kit}:inview</code> am Element mit <code>bubbles</code>, <code>detail</code> = sichtbar ja/nein (ein gemeinsamer Observer für alle Animationen, statt einem je Block); <code>{kit}:content</code> am <code>document</code>, <code>detail</code> = Wurzel-Element nachgeladener Inhalte (Overlay, Karte, „Mehr laden“) – jedes Block-Skript initialisiert sich darin erneut, statt nur beim Laden der Seite; <code>{kit}:slide</code> beim Wechsel einer Folie. Wer Inhalte nachlädt, ergänzt fehlende Stylesheets/Skripte der geladenen Seite (deren <code>&lt;link&gt;</code>/<code>&lt;script&gt;</code> aus dem <code>&lt;head&gt;</code>) und sendet danach <code>{kit}:content</code>.</li>
  </ul>

  <h3 id="css-js-bewegung">Barrierefreiheit und Bewegung – Pflicht für jedes Kit</h3>
  <ul>
    <li><b><code>prefers-reduced-motion: reduce</code></b>: alle Animationen, Übergänge, View Transitions, Parallaxe, Autoplay aus – und zwar mit <b>Endbild</b>: der Ausgangszustand im CSS ist der fertige Zustand, die Bewegung kommt nur unter <code>@media (prefers-reduced-motion: no-preference)</code> bzw. für <code>.js</code> dazu. Eingeblendetes darf ohne JavaScript, bei reduzierter Bewegung und im Bearbeiten-Modus nie unsichtbar bleiben (<code>opacity:0</code> nur, solange ein Skript es sicher wieder aufhebt). Skripte fragen <code>matchMedia('(prefers-reduced-motion: reduce)')</code> und reagieren auf Wechsel zur Laufzeit.</li>
    <li><b>Pausieren (WCAG 2.2.2)</b>: Was sich länger als 5 Sekunden selbst bewegt (Video-Schleife, Laufzeile, Karussell, Himmel), hat einen sichtbaren Pausenknopf (<code>&lt;button aria-pressed&gt;</code> mit übersetzter Beschriftung über <code>data-l-pause</code>/<code>data-l-play</code>); Autoplay nur sichtbar (IntersectionObserver), nicht im verborgenen Tab, nicht bei „Datensparmodus“; die Wahl des Besuchers gilt mindestens für die Sitzung. Vorbilder: <code>hero.mjs</code>, <code>media.mjs</code> (Slider stoppt bei Maus/Fokus).</li>
    <li><b>Fokus</b>: sichtbarer <code>:focus-visible</code>-Ring mit ≥ 3:1 Kontrast auf jedem Hintergrund (auch Karten mit Bild, dunkle Abschnitte); nie <code>outline:none</code> ohne Ersatz. Dialoge/Overlays: Fokus hinein, <kbd>Esc</kbd> schließt, Fokus zurück zum Auslöser; nicht-modal mit <code>inert</code> auf dem Rest.</li>
    <li><b><code>forced-colors: active</code></b> (Windows-Kontrastmodus): Hintergrundbilder und Verläufe verschwinden – Rahmen statt Flächen, <code>CanvasText</code>/<code>Canvas</code>/<code>LinkText</code>/<code>Highlight</code>, Symbole mit <code>currentColor</code>, Zustände nicht nur über Farbe.</li>
    <li><b>Bearbeiten-Modus</b> (<code>html.is-editing</code>): Bewegung, Autoplay, klebende/fixierte Overlays, Karten-Links mit <code>::after</code> über der ganzen Karte und Hover-Effekte (Anheben, Neigen) aus, damit Texte direkt bearbeitbar sind; alle Varianten/Folien/Reiter erreichbar (<code>cms:block-select</code>).</li>
  </ul>
  <p>Prüfhilfe: <code>php bin/console docs:assets --kit=… --kit-only</code> zeigt je Datei Keyframes, <code>prefers-reduced-motion</code>, <code>forced-colors</code> und <code>:focus-visible</code> und markiert Animationen ohne eigene Abfrage mit ⚠ (prüfen – eine globale Regel in <code>site.css</code> kann genügen).</p>

  <h3 id="css-js-regeln">Regeln für Kits: Dos &amp; Don'ts</h3>
  <table class="doc-table">
    <tr><th>Nicht</th><th>Stattdessen</th></tr>
    <tr><td>Globale Resets/Normalize mit Elementselektoren, die alles treffen (<code>*{all:unset}</code>, <code>button{…}</code> ohne Kontext, <code>[hidden]{display:block}</code>, <code>dialog{…}</code>, <code>body &gt; *</code>)</td><td>Reset auf das Nötige (<code>box-sizing</code>, <code>margin</code>), Elementregeln unter einer Kit-Hülle (<code>.k-prose button</code>) oder mit <code>:where()</code> niedrig halten. Die CMS-Teile in der Seite (Stift, „+ Neuer Eintrag“, Hinweiszeile) schützen sich mit <code>all:unset</code> + doppelter Klasse – das ist Notwehr, keine Einladung.</td></tr>
    <tr><td><code>.cms-*</code>, <code>.adm-*</code>, <code>.ce-*</code> (Editor.js), <code>#cms-*</code>, <code>[data-edit]</code> gestalten oder per Skript verändern</td><td>Nur die öffentlichen Bausteine (<code>cms-map</code>, <code>cms-gallery</code> …) und nur über ihre Variablen bzw. Klassen. Editierbare Bereiche: eigenes Element mit Kit-Klasse und <code>&lt;?= $b-&gt;edit('feld') ?&gt;</code> daran – das Kit gestaltet sein Element, der Editor den Rahmen.</td></tr>
    <tr><td><code>z-index: 9999</code> und höher, <code>position:fixed</code> über allem</td><td>Kit-Werte unter 1000 (Kopf 30–100, Overlays/Sheets 20–60, Elemente in Karten 1–5); Modales mit <code>&lt;dialog&gt;.showModal()</code> (Top Layer, kein z-index).</td></tr>
    <tr><td>Fremde Variablen-Namen ohne Präfix an <code>:root</code> (<code>--primary</code>, <code>--radius</code>)</td><td>Kit-Präfix (<code>--b-*</code>); Kern-Variablen nur mit ihrem Präfix setzen (<code>--dl-*</code>, <code>--gl-*</code> …).</td></tr>
    <tr><td>Skripte, die beim Bearbeiten DOM umbauen (Klonen für Endlos-Karussell, Text in Spans zerlegen) und so <code>data-edit</code>-Elemente verdoppeln</td><td>Im Editor (<code>html.is-editing</code>) nichts umbauen – basis lädt <code>site.js</code> im Seiten-Editor gar nicht (<code>&lt;?php if (!$editor): ?&gt;</code> im Layout); zerlegte Texte nur für Besucher.</td></tr>
    <tr><td><code>style="…"</code>, Inline-Skripte, <code>eval</code>, Fremd-CDNs</td><td>Klassen/<code>data-*</code>, Dateien unter <code>assets/</code>, Vendoren per <code>build.mjs</code> selbst hosten.</td></tr>
  </table>
  <p><b>Z-Skala</b> (Kern, <code>editor.css</code> – oberhalb üblicher Framework-Werte: Bootstrap 1000–1090, UIkit 980–1040, Tailwind <code>z-50</code>):</p>
  <table class="doc-table">
    <tr><th>z-index</th><th>Ebene</th></tr>
    <tr><td>&lt; 1000</td><td><b>Kit</b>: Inhalte, klebender Kopf (z. B. 30), Overlays/Sheets des Kits (z. B. 25/26), Hinweise (60). Kern-Bausteine im Inhalt: Glossar-Begriff <code>.gl-term</code> <b>2</b> (liegt über Karten-Links mit <code>::after{inset:0}</code>, sonst öffnet der Begriff nie), Slider-Pause 3, Suchvorschläge <code>.ssug</code> 60, Menüs der Kopfbereich-Aktionen 60, <code>.sec__bg</code> −1</td></tr>
    <tr><td>2147482990</td><td>Markierungen in der Seite (Stift je Eintrag, „Ziel bearbeiten“, „Zentral gepflegt“)</td></tr>
    <tr><td>2147483000</td><td>Bedienelemente in der Seite (Block-Leisten, „+“, Editor.js-Werkzeugleiste); öffentlich: Glossar-Hinweisfenster <code>.gl-pop</code>, Besucher-Chat</td></tr>
    <tr><td>2147483050</td><td>Kopf des Kits im Bearbeiten (<code>[data-cms-header]</code>, <code>[data-cms-sticky]</code>) mit seinen Menüs – über den Block-Leisten</td></tr>
    <tr><td>2147483060</td><td>Geöffnete Editor.js-Menüs (über dem Kopf, solange offen)</td></tr>
    <tr><td>2147483100</td><td>Seitenleiste „Eintrag bearbeiten“ (<code>#cms-epanel-host</code>)</td></tr>
    <tr><td>2147483200</td><td>Gemeinsame Ebene <code>#cms-layer-host</code> (Seitenleiste „Block“, Formatierungsleiste, Meldungen)</td></tr>
    <tr><td>2147483300</td><td>Werkzeugleiste <code>.cms-bar-host</code></td></tr>
    <tr><td>Top Layer</td><td>Modale Dialoge (<code>showModal()</code>: Lightbox, Mediathek, Rückfragen) – immer ganz oben</td></tr>
  </table>
  <p>Klebender Kit-Kopf mit Werkzeugleiste: <code>top: var(--cms-toolbar-h, 0)</code> (bzw. <code>--cms-bar-offset</code> – sichtbare Unterkante der Leiste, auf Telefonen schrumpfend); feste Leisten unten machen dem Chat-Starter mit <code>--cms-chat-lift</code> Platz.</p>

  <h3 id="css-js-generator">Referenz erzeugen – <code>docs:assets</code></h3>
  <pre><code>php bin/console docs:assets                              # Kern (Markdown auf stdout)
php bin/console docs:assets --kit=basis --out            # Kern + Kit → storage/docs/css-js-basis.md
php bin/console docs:assets --kit=basis --kit-only --out=/tmp/basis.md
php bin/console docs:assets --kit-dir=../andere-installation/kits/meinkit --kit-only   # Kit aus beliebigem Ordner
php bin/console docs:assets --kit=basis --update         # Anhang in kits/basis/docs/css-js.md neu schreiben (legt die Seite an)
php bin/console docs:assets --json                       # Rohdaten
php bin/console docs:selftest                            # Parser gegen Beispiele und gegen den Kern</code></pre>
  <p>Gelesen wird nur statisch (<code>Core\AssetDocs</code>): CSS-Quellen samt lokaler <code>@import</code> (Custom Properties mit Selektor und <code>@media</code>-Kontext, <code>var(--x, Rückfall)</code>, Keyframes), <code>theme.php</code> (<code>conditional_css</code>, Blöcke, Varianten, Design-Tokens), <code>theme_asset()</code> in <code>templates/</code>, Ereignisse (<code>new CustomEvent</code>, <code>emit()</code>, <code>addEventListener</code>, <code>ctx.on</code> – nur Namen mit Doppelpunkt), <code>window.*</code>, <code>data-*</code> in Vorlagen und Skripten. Diese Seite zeigt den Kern-Teil zur Laufzeit (Cache <code>storage/cache/asset-docs-core.json</code>, erneuert sich bei jeder Änderung unter <code>resources/</code>, <code>app/Blocks</code>, <code>app/Views</code>).</p>
  <div class="doc-faq">
    <details><summary>Ereignisse im Code (<?= count($__ad['events']) ?>, generiert)</summary><div>
      <table class="doc-table"><tr><th>Ereignis</th><th>Payload (Quelltext)</th><th>gesendet</th><th>empfangen</th></tr>
        <?php foreach ($__ad['events'] as $__n => $__e): ?><tr><td><code><?= e($__n) ?></code></td><td><?= $__v($__e['detail'] ?? null) ?></td><td><?= e(implode(', ', array_unique($__e['dispatch'] ?? []))) ?></td><td><?= e(implode(', ', array_unique($__e['listen'] ?? []))) ?></td></tr><?php endforeach; ?>
      </table>
    </div></details>
    <details><summary>Lader der Kern-Dateien (<?= count($__ad['loaders']) ?>, generiert)</summary><div>
      <table class="doc-table"><tr><th>Datei</th><th>eingebunden von</th></tr>
        <?php foreach ($__ad['loaders'] as $__a => $__by): ?><tr><td><code><?= e($__a) ?></code></td><td><?= e(implode(', ', $__by)) ?></td></tr><?php endforeach; ?>
      </table>
    </div></details>
    <?php if ($__adKit): ?>
    <details><summary>Aktives Kit „<?= e($__adKit['label']) ?>“: Blöcke → Dateien (generiert)</summary><div>
      <p>Immer: <?= $__adKit['base'] ? implode(', ', array_map(fn($f) => '<code>' . e($f) . '</code>', array_keys($__adKit['base']))) : '–' ?>. Kit-Seite <code>kits/<?= e($__adKit['name']) ?>/docs/css-js.md</code>: <?= $__adKitDoc ? 'vorhanden' : 'fehlt noch – <code>php bin/console docs:assets --kit=' . e($__adKit['name']) . ' --update</code>' ?>.</p>
      <table class="doc-table"><tr><th>Block</th><th>Renderer</th><th>CSS/JS (Variante)</th></tr>
        <?php foreach ($__adKit['blocks'] as $__t => $__b): ?><tr><td><code><?= e($__t) ?></code> <?= e($__b['label']) ?></td><td><?= $__v($__b['renderer']) ?></td><td><?php $__o = []; foreach ($__b['files'] as $__f => $__vars) $__o[] = '<code>' . e($__f) . '</code>' . ($__vars ? ' (' . e(implode(', ', $__vars)) . ')' : ''); echo $__o ? implode(', ', $__o) : '–'; ?></td></tr><?php endforeach; ?>
      </table>
    </div></details>
    <?php endif; ?>
  </div>
  <p>Jedes Kit beschreibt seine eigene Schicht auf einer Seite nach festem Muster: <a href="#kit-css-js">Kits &amp; Design › Die Kit-Seite „CSS &amp; JS“</a>.</p>
