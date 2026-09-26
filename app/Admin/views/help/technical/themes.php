<?php /** Entwicklerhandbuch · Kits & Design */ ?>
  <p class="lead">Ein neues Projekt ist ein neues Kit – der Core bleibt unverändert und updatefähig. Mitgeliefert: <b><code>basis</code></b> (neutrales Business-Kit und Baukasten, Startpunkt für neue Projekte: 16 eigene Blöcke, vier Navigationen, sieben Design-Vorlagen, Dunkelmodus, mehrsprachig – siehe <code>themes/basis/README.md</code>) und <b><code>praxis</code></b> (Arztpraxis: 22 Blöcke, Praxisdaten mit Sprechzeiten, Rezept-/Überweisungs-Eingänge, Style-Editor mit drei Vorlagen ohne Dunkelmodus), außerdem u. a. <b><code>nature</code></b> (organisch und erdig für Höfe, Gärtnereien, Naturschutz: Vorlagen für die vier Jahreszeiten, Waldnacht als Dunkelmodus, Saisonzeiten – siehe <code>themes/nature/README.md</code>) und <b><code>modern</code></b> (zeitgemäß und klar: große Grotesk-Typografie, Blockfarbe, Bento-Raster, vier Navigationen, Einstieg als Mosaik oder große Aussage – siehe <code>themes/modern/README.md</code>) und <b><code>glas</code></b> (Glassmorphism mit Lesbarkeit zuerst: Mattglas über Farbfeldern, Aurora im Einstieg, deckend bei „Transparenz reduzieren“ – siehe <code>themes/glas/README.md</code>). Ein Kit ist ein eigenständiges Paket (eigener Ordner, eigene Version, eigene Build-Abhängigkeiten) und kann von mehreren Websites gleichzeitig genutzt werden. Der Core enthält keine Kit-Namen.</p>
  <p id="kits">Ein Kit bündelt Design-Tokens, Blöcke, das Formular der zentralen Angaben, Datenlisten, JSON-LD, Startinhalte, Sprachdateien und Logik – es ist die funktionale Grundlage eines Projekts. Der gestalterische Teil darin heißt weiterhin <b>Design</b> (Menü „Design“, Style-Editor).</p>
  <div class="doc-note doc-note--info"><strong>Name im Code: theme</strong><p>Kits liegen technisch unter <code>themes/{name}/</code> (Datei <code>theme.php</code>, Konfiguration <code>'theme' =&gt; …</code>) – der Name bleibt aus Kompatibilitätsgründen. Ebenso bleiben <code>app()-&gt;theme</code>, die Klasse <code>Core\Theme</code>, die Einstellung <code>sys.theme</code>, API-/MCP-Felder <code>theme</code> und die Befehle <code>theme:*</code> unverändert. Als Alias gehen <code>'kit' =&gt; …</code> in <code>config/sites/{key}.php</code> bzw. <code>config.local.php</code> sowie <code>kit:list</code> und <code>kit:create</code>.</p></div>
  <pre><code>themes/{name}/
├── theme.php         label, version, requires (Core-Version, z. B. '>=1.0.0'), backgrounds, blocks, settings, forms (Vorlage für Eingangs-Tabellen), seo, jsonld, conditional_css, frame_hosts,
│                     link_keywords, image_ratios, app, core_blocks, container_class, button_class
├── functions.php     Kit-Helfer (Präfix = Kit-Name)
├── seed.php          Startinhalte: settings, pages[blocks], page_refs, after (callable für weitere Inhalte)
├── design.php        optional: Design-Tokens (oder inline unter theme.php → design)
├── docs/manual.php   optional: Kapitel für das Handbuch der Redaktion (siehe unten)
├── lang/             {locale}.php (Verwaltung) · site/{lang}.php (feste Website-Texte, lt())
├── templates/        layout.php · error.php · maintenance.php · offline.php (PWA) · form-page.php (optional, /anfrage/{form})
│                     search.php (optional) · partials/ (toolbar, editor, section, video-embed, search-form …)
├── blocks/{type}.php Renderer: $b (Core\Block), $d (Daten inkl. Defaults)
├── assets/           css/ js/ img/  →  pnpm build  →  public/themes/{name}/
├── build.mjs         optional: vendors(ctx) kopiert Kit-Vendoren (z. B. Schriften) beim pnpm build
├── package.json      optional: npm-Pakete nur für dieses Kit (installiert tools/build.mjs automatisch)
└── fonts/            serverseitige Dateien aus build.mjs (z. B. TTF für den Icon-Generator, nicht öffentlich)</code></pre>
  <h3>Weitere Kit-Optionen</h3>
  <table class="doc-table">
    <tr><th>Schlüssel</th><th>Wirkung</th></tr>
    <tr><td><code>image_ratios</code></td><td>Bildformate, für die in der Mediathek eigene Zuschnitte möglich sind, z. B. <code>['16:9' =&gt; 'Querformat', '1:1' =&gt; 'Quadrat']</code>. Blöcke übergeben das Format: <code>img($id, $sizes, ['ratio' =&gt; '4:3'])</code>.</td></tr>
    <tr><td><code>project</code></td><td>Branchenspezifisches, damit der Core neutral bleibt: <code>terms</code> (sichtbare Begriffe wie „Praxisschlüssel“ statt „Schlüssel“, „Praxis“ statt „Organisation“), <code>name_setting</code>, <code>setup_checks</code>, <code>dashboard_hint</code>, <code>public_info</code> (Funktion), <code>hours</code> (aktiviert Öffnungszeiten in API/MCP), <code>notice</code> (Hinweisbalken), <code>link_labels</code>, <code>search_keywords</code>, <code>mcp.instructions</code>/<code>mcp.prompts</code>. Helfer: <code>project('hours.label')</code>, <code>term('key')</code>, <code>site_name()</code>.</td></tr>
    <tr><td><code>jsonld</code> / strukturierte Daten</td><td>Der Core gibt je Seite einen schema.org-<code>@graph</code> aus: Organisation (Funktion aus <code>theme.php → 'jsonld'</code>, vollständig auf der Startseite, sonst als Verweis <code>#org</code>, mit Geo-Koordinaten der Karte), <code>WebSite</code>, <code>WebPage</code>, <code>BreadcrumbList</code>. Blöcke deklarieren ihre Daten in der Blockdefinition, z. B. <code>'jsonld' =&gt; ['type' =&gt; 'faq', 'items' =&gt; 'items', 'question' =&gt; 'q', 'answer' =&gt; 'a']</code> (FAQPage) oder <code>['type' =&gt; 'video', 'url' =&gt; 'video_url', 'poster' =&gt; 'poster', 'name' =&gt; 'title']</code> (VideoObject, nur mit Vorschaubild) – oder eine Funktion <code>fn(Block $b): ?array</code>. Kern-Blöcke: Datenliste → <code>ItemList</code>, Kalender/Nächste Termine → <code>Event</code>. Detailseiten: Typ je Datentabelle („Strukturierte Daten“: NewsArticle, Article, BlogPosting, Event, Person, Product, Service, Place, Organization) – Felder werden nach Typ/Namen zugeordnet (Datum, Autor über Verknüpfung, Schlagworte, Funktion, E-Mail/Telefon, Preis/Verfügbarkeit, Adresse/Geo). Platzhalter in [Klammern] werden nicht ausgegeben. Code: <code>Core\StructuredData</code>.</td></tr>
    <tr><td>Vorschau in den Einstellungen</td><td>Das Einstellungsformular zeigt auf Wunsch rechts die echte Startseite mit den <b>ungespeicherten</b> Werten (<code>POST /admin/api/settings-preview</code>, Werte nur im Speicher über <code>Settings::override()</code>; Desktop/Mobil; je Sprache). Listenfelder mit <code>'preview' =&gt; true</code> erhalten je Eintrag „Vorschau dieses Eintrags“; das Kit liest den gewünschten Eintrag mit <code>preview_focus('feldname')</code> (Index) und zeigt ihn zuerst – Beispiel: praxis <code>blocks/hero.php</code>. Listeneinträge sind einklappbar und zeigen eine Kurzinfo (Auswahl, Aktiv/Inaktiv, Zeitraum, Bild).</td></tr>
    <tr><td><code>docs/manual.php</code></td><td>Kapitel für das Handbuch der Redaktion. Die Datei gibt ein Array zurück: <code>hero</code> (Kopf), <code>chapters</code> (Kern-Kapitel ersetzen, mit <code>mode</code> <code>before</code>/<code>after</code> ergänzen, mit <code>false</code> entfernen oder neue Kapitel mit <code>after</code> einfügen – jeweils <code>file</code> = PHP-Teilansicht in <code>docs/manual/</code>), <code>blocks</code> (Texte für „Alle Blöcke“), <code>vars</code> (z. B. <code>blocks_page</code>, <code>image_sizes</code>, <code>mcp_examples</code>) und <code>figures =&gt; false</code>. Die Kern-Kapitel liegen in <code>app/Admin/views/help/manual/</code>; Nummern und Inhaltsverzeichnis entstehen automatisch. Beispiele: praxis und basis. Eine ältere, vollständige Ansicht (kein Array) wird weiterhin statt des Kern-Handbuchs gezeigt.</td></tr>
    <tr><td><code>app</code></td><td>Vorgaben für den Icon-Generator (<code>defaults</code>: icon_text, icon_bg, icon_fg, icon_dot) und <code>info</code> = Name einer Funktion, die Name, Kurzname, Beschreibung und Kurzbefehle fürs Manifest liefert.</td></tr>
    <tr><td><code>fonts</code></td><td>Hausschrift des Kits: <code>preload</code> (Pfade relativ zu <code>public/themes/{name}</code>; auch für den Offline-Speicher der App), <code>icon</code> (TTF relativ zu <code>themes/{name}</code> für den Icon-Generator; ohne Angabe: fette Systemschrift). Die Verwaltung nutzt die Schrift Lato aus dem Core.</td></tr>
    <tr><td><code>proxy</code></td><td>Weitere externe Quellen für den zentralen Proxy, z. B. <code>'wetter' =&gt; ['upstream' =&gt; 'https://api.example.org', 'allow' =&gt; '^v1/', 'ttl' =&gt; 600]</code> – siehe Kapitel „Karten &amp; Proxy“.</td></tr>
    <tr><td><code>project.map</code></td><td>Welche Einstellungen die Karte speisen: <code>location</code> (Feld vom Typ <code>geo</code>), <code>label</code>, <code>address</code> (Liste), <code>route</code> (eigener Routenlink).</td></tr>
    <tr><td><code>backgrounds</code>, <code>dark_backgrounds</code></td><td>Hintergründe der Abschnitte (Schlüssel → Bezeichnung) und welche davon dunkel sind (helle Schrift, <code>Block</code> setzt die passende Klasse).</td></tr>
    <tr><td><code>project.brand</code></td><td>Zwei Einstellungsfelder, deren Werte oben in der Seitenleiste der Verwaltung als Name der Website stehen (z. B. Wortmarke Zeile 1/2); ohne Angabe der Website-Name.</td></tr>
    <tr><td><code>core_blocks</code></td><td><code>false</code> schaltet alle zehn Kern-Blöcke ab (<code>data_list</code>, <code>data_fields</code>, <code>data_form</code>, <code>calendar</code>, <code>upcoming</code>, <code>map</code>, <code>gallery</code>, <code>slideshow</code>, <code>stack_cards</code>, <code>dials</code>). Einen einzelnen Kern-Block überschreibt ein Kit, indem es denselben Typ in <code>blocks</code> definiert bzw. <code>blocks/{typ}.php</code> mitbringt.</td></tr>
    <tr><td><code>container_class</code>, <code>button_class</code></td><td>CSS-Klassen, die Kern-Blöcke für Inhaltsbreite und Buttons verwenden (Standard: <code>wrap</code>, <code>btn btn--primary</code>).</td></tr>
    <tr><td><code>assets/css/data.css</code></td><td>Überschreibt die neutrale Kern-CSS der Datenblöcke (per <code>@import "../../../../resources/css/data.css";</code> erweiterbar).</td></tr>
    <tr><td><code>Pages::menu()</code></td><td>Menübaum aus Seiten mit „Im Menü“ – das Kit entscheidet über Darstellung (z. B. Aufklappmenü, <code>css/nav.css</code> nur bei Unterseiten).</td></tr>
  </table>
  <h3 id="design">Design (Style-Editor) – <code>theme.php → 'design'</code></h3>
  <p>Unter <b>Verwaltung → Design</b> ändern Admins Farben, Formen und Schriften, ohne das Kit anzufassen. Das Kit beschreibt, <em>was</em> einstellbar ist; jeder Wert ist eine CSS-Variable (oder eine Klasse am <code>&lt;html&gt;</code>). Werte gelten je Website <b>und</b> je Kit (Einstellung <code>design.{theme}</code>, Verlauf der letzten 10 Stände in <code>design.{theme}.history</code> mit Zeitpunkt und Benutzer). Recht <code>design.edit</code> (Standard: nur Administration), Funktion <code>design</code> im Funktionsumfang.</p>
  <pre><code>'design' => [
    'groups' => [
        ['id' => 'farben', 'label' => 'Farben', 'tokens' => [
            ['name' => 'accent', 'label' => 'Akzent', 'help' => 'Buttons, Links', 'type' => 'color', 'var' => '--accent',
             'default' => '#0F766E', 'dark' => '#2DD4BF',                    // optional eigener Dunkel-Wert (Eingabe „Dunkel“)
             'contrast' => ['with' => 'bg', 'min' => 4.5]],                  // with: Farbe oder Token-Name; optional dark_with
            ['name' => 'bg', 'label' => 'Hintergrund', 'type' => 'color', 'var' => '--bg', 'default' => '#FFFFFF', 'dark' => '#111418'],
        ]],
        ['id' => 'formen', 'label' => 'Formen', 'tokens' => [
            ['name' => 'radius', 'label' => 'Eckenradius', 'type' => 'range', 'var' => '--radius', 'default' => 12, 'min' => 0, 'max' => 32, 'step' => 2, 'unit' => 'px'],
            ['name' => 'buttons', 'label' => 'Buttons', 'type' => 'choice', 'var' => '--r-btn', 'preview' => 'radius', 'default' => 'pill',
             'options' => ['pill' => 'Rund', 'square' => 'Eckig'], 'values' => ['pill' => '999px', 'square' => '4px']],
            ['name' => 'nav', 'label' => 'Navigation', 'type' => 'choice', 'class' => 'nav-{value}', 'preview' => 'nav', 'default' => 'classic',
             'options' => ['classic' => 'Klassisch', 'centered' => 'Zentriert', 'burger' => 'Kompakt'], 'thumbs' => ['classic' => 'left']],
            ['name' => 'sticky', 'label' => 'Kopf mitscrollen', 'type' => 'bool', 'class' => 'has-sticky', 'default' => true],
        ]],
        ['id' => 'schrift', 'label' => 'Schrift', 'tokens' => [
            ['name' => 'font', 'label' => 'Schrift', 'type' => 'font', 'var' => '--font', 'default' => 'inter'],
        ]],
    ],
    'fonts'   => ['inter' => ['label' => 'Inter', 'stack' => 'Inter, system-ui, sans-serif', 'css' => 'css/font-inter.css'], …],
    'presets' => ['petrol' => ['label' => 'Petrol', 'values' => ['accent' => '#0F766E', 'accent@dark' => '#2DD4BF']], …],
    'dark'    => ['media' => '(prefers-color-scheme: dark)', 'scope' => ':root:not(.light)', 'force' => 'data-theme=dark'],
    'sample'  => 'muster',                                                   // optional: Musterseite (Pfad) für die Vorschau
],</code></pre>
  <table class="doc-table">
    <tr><th>Typ</th><th>Editor</th><th>Ausgabe</th></tr>
    <tr><td><code>color</code></td><td>Farbwähler + Hex, optional „Dunkel“; Kontrast-Badge (WCAG AA/AAA) bei <code>contrast</code></td><td><code>--var:#RRGGBB</code>; Dunkel-Wert in <code>dark.scope</code> (innerhalb <code>dark.media</code>)</td></tr>
    <tr><td><code>range</code></td><td>Schieberegler + Zahl + Einheit</td><td><code>--var:12px</code> (min/max werden erzwungen)</td></tr>
    <tr><td><code>choice</code></td><td>Segmente; mit <code>preview</code> Karten: <code>radius</code> (Wert als Ecke), <code>color</code>, <code>nav</code> (CSS-Schema: left, center, split, burger, sidebar, floating, transparent, bottom – Zuordnung über <code>thumbs</code> oder gleichnamige Optionen)</td><td><code>values</code> → Variable und/oder <code>class</code> (<code>{value}</code>) am <code>&lt;html&gt;</code></td></tr>
    <tr><td><code>bool</code></td><td>Schalter</td><td><code>class</code> bei an, bzw. Variable 1/0</td></tr>
    <tr><td><code>font</code></td><td>Auswahl mit Schriftprobe (Schrift-CSS wird dafür in der Verwaltung geladen)</td><td>Schrift-Stack als Variable; <code>css</code> der gewählten Schrift wird automatisch eingebunden</td></tr>
  </table>
  <p><b>Im Layout:</b> <code>design_head()</code> nach dem Kit-CSS (bindet die gewählten Schriftdateien und – nur wenn Werte vom Standard abweichen – die erzeugte Datei <code>design/design-{theme}-{hash}.css</code> ein) und <code>design_classes()</code> im <code>class</code>-Attribut von <code>&lt;html&gt;</code>. Einzelwerte liest <code>design('nav')</code>. Die Datei liegt im Medienordner der Website (<code>{media}/design/</code>, Hash = 10 Zeichen SHA-1). Die Standardwerte in <code>theme.php</code> müssen den Werten im Kit-CSS entsprechen – das Kit funktioniert damit auch ohne gespeicherte Werte.</p>
  <p><b>Editor:</b> Reiter je Gruppe, Vorlagen als Karten (füllen nur das Formular), Verwerfen, Kit-Standard, Verlauf (Wiederherstellen lädt einen früheren Stand ins Formular), Export/Import als JSON (<code>{"theme": …, "values": …}</code>, serverseitig über <code>Design::normalize()</code> geprüft; unbekannte Schlüssel werden gemeldet). Live-Vorschau (<code>POST /admin/api/design-preview</code>) rendert Startseite, eine gewählte Seite oder die Musterseite mit <code>Design::override()</code> – <code>design_head()</code> gibt die Variablen dann inline aus. „Dunkel“ in der Vorschau erzwingt die Dunkel-Werte ohne Media-Query und setzt optional <code>dark.force</code> (Klasse bzw. <code>attr=wert</code>) am <code>&lt;html&gt;</code>, damit auch themeeigene Dunkel-Regeln greifen. Speichern leert den Seiten-Cache; die CSS-Datei entsteht beim nächsten Aufruf der Website.</p>
  <p><b>API &amp; MCP:</b> <code>GET /api/v1/design</code> (Definition, Werte, Standardwerte, Kontrastprüfung, Verlauf) und <code>PATCH /api/v1/design</code> mit <code>{"values": {…}}</code>, <code>{"preset": "petrol"}</code> oder <code>{"reset": true}</code> – nur mit write-Token, dessen Benutzer-Rolle <code>design.edit</code> hat (sonst 403); ungültige Werte → 422. MCP: <code>get_design</code>, <code>set_design</code>.</p>
  <p><b>Referenz „basis“:</b> <code>themes/basis/design.php</code> definiert Farben (je hell + dunkel, Kontrastpartner als Token-Namen), die Gruppen Farben, Typografie (Fließtext- und Überschriftenschrift aus sechs selbst gehosteten Familien plus Systemschrift, Grundgröße, Skala, Stärke, Laufweite; <code>build.mjs</code> erzeugt je Familie <code>css/font-{key}.css</code>), Form &amp; Abstände (Radius, <code>btn-*</code>, <code>cards-*</code>, Abschnittsabstand), Navigation (<code>nav-{modern|classic|minimal|extended}</code> wählt Kopf-Partial und <code>css/nav-*.css</code>; Sticky, transparent, Infoleiste, Fußbereich) und Farbschema (<code>has-dark</code>) sowie sieben Voreinstellungen; <code>dark.force</code> = <code>is-dark</code> (<code>css/preview.css</code> wird nur in der Vorschau geladen). Standardwerte stehen identisch in <code>site.css</code> (auch der Dunkel-Block), weil ohne Abweichung keine Design-Datei ausgeliefert wird. Prüfung aller Vorlagen: <code>php themes/basis/tools/contrast.php</code>; Musterseite (<code>'sample' =&gt; 'baukasten'</code>): <code>CMS_SITE=… php themes/basis/tools/demo.php</code>. Kits können über <code>seed.php → 'after' =&gt; callable</code> nach den Startseiten weitere Inhalte anlegen (Seitenbaum, Datentabellen, Medien).</p>
  <h3 id="kopfbereich-aktionen">Kopfbereich-Aktionen – <code>header_actions()</code> gestalten und überschreiben</h3>
  <p><code>Core\HeaderActions</code> liefert Handlungsaufruf (gefüllt, Kontur, Textlink →, Symbol + Text, geteilt, Chip mit Statuspunkt, <b>Kontakt-Menü</b> – Symbol mit <code>&lt;details&gt;</code>-Liste aus Telefon, E-Mail, Kontaktseite, Anfahrt über <code>Core\Maps</code>, Öffnungszeiten, WhatsApp/Signal nur bei vorhandenem Feld –, <b>Menüpunkt</b> – abgesetzt nach der Navigation; <code>HeaderActions::menu($menu)</code> nimmt das gleiche Ziel aus dem Menü –, keiner), zweite Aktion, Suche (Lupe/Popover, Feld mit Vorschlägen, Aufziehen, Befehlsfeld „Suchen … ⌘K“, Suchleiste), Anordnung (rechts, Suche mittig, Suche darunter), Kontakt-Chip mit Öffnungsstatus, Sprachumschalter (Kürzel, Aufklappliste, Namen – ohne Flaggen), Social-Symbole und „Anmelden“. Einrichten im Kit:</p>
  <ul>
    <li><b>design.php:</b> <code>\Core\HeaderActions::designGroup(['ha_cta_style' =&gt; 'link', 'ha_search' =&gt; 'inline'], ['exclude' =&gt; ['ha_status']])</code> als Gruppe – Tokens <code>ha_cta_style</code>, <code>ha_menu_icon</code>, <code>ha_cta</code>, <code>ha_cta2</code>, <code>ha_search</code>, <code>ha_layout</code>, <code>ha_contact</code>, <code>ha_status</code>, <code>ha_lang</code>, <code>ha_social</code>, <code>ha_account</code>; das Kit nennt nur seine Standards (so sieht jedes Kit anders aus).</li>
    <li><b>theme.php:</b> <code>...\Core\HeaderActions::settingsFields()</code> in Website › Darstellung (Felder <code>header_cta_label/link</code>, <code>header_cta2_label/link</code>, <code>header_status_label</code>, <code>header_account_label/link</code>) und <code>'header_actions' =&gt; ['classes' =&gt; ['solid' =&gt; 'btn btn--primary btn--small', 'outline' =&gt; …, 'split' =&gt; …]]</code> – Kit-Klassen je Stil (Markierung <code>.ha-cta--kit</code>); optional <code>'kit_css' =&gt; 'css/header-actions-kit.css'</code> (Kit-Regeln zu den Optionen, laden nur zusammen mit dem Kern-Bündel – hält <code>site.css</code> klein) und <code>'base_css' =&gt; false</code> (schlichte Auswahl „Kit-Button + Lupe“ ohne Kern-Datei; das Kit-CSS enthält dann <code>.ha{display:flex…}</code> und die 8-px-Ecken).</li>
    <li><b>layout.php:</b> <code>&lt;?= header_actions_head() ?&gt;</code> <em>vor</em> dem Kit-CSS. Es bündelt nur die benötigten Teile zu je einer Datei <code>/assets/ha/{hash}.css|js</code> (entsteht beim ersten Aufruf, gleich für alle Websites mit derselben Auswahl; sonst einzelne Dateien): Grundlage <code>header-actions.css</code> (≈ 1,8 KB) plus <code>-link</code>, <code>-chip</code>, <code>-dot</code>, <code>-split</code>, <code>-menu</code>, <code>-navitem</code>, <code>-cta2</code>, <code>-field</code>, <code>-expand</code>, <code>-command</code>, <code>-below</code>, <code>-contact</code>, <code>-extra</code> (je 0,2–1,9 KB); JavaScript <code>header-actions.js</code> (≈ 1 KB, Tastenkürzel – nur mit Suche) plus <code>-status.js</code> (Öffnungsstatus) und <code>-menu.js</code> (Aufklappliste); nie im Bearbeitungsmodus. Typisch 3–5 KB CSS je Kit. <code>header_actions_head(['media' =&gt; '(min-width: 48em)'])</code> hält die Dateien auf Telefonen aus dem kritischen Pfad (Glas-Dock).</li>
    <li><b>Kopf-Template:</b> <code>header_actions('bar', ['compact' =&gt; true, 'lang' =&gt; $langs])</code> in der Leiste, <code>header_actions('center')</code> nach der Marke, <code>header_actions('below', ['wrap' =&gt; 'wrap'])</code> am Ende von <code>&lt;header&gt;</code>, <code>header_actions_lang($langs, 'klasse')</code> für den Umschalter, <code>header_cta()</code> für Menü-Blatt und Fußbereich. <code>compact</code>: unter 30 em wandern Text-Aktionen ins Menü, Symbol-Aktionen bleiben 44 px groß; unter 48 em werden Feld/Aufziehen zur Lupe.</li>
    <li><b>Gestalten:</b> Die Kern-Regeln sind einfache Klassen (<code>.ha</code>, <code>.ha-cta--{stil}</code>, <code>.ha-split</code>, <code>.ha-chip</code>, <code>.ha-dot</code>, <code>.ha-field</code>, <code>.ha-cmd</code>, <code>.ha-below</code>, <code>.ha-center</code>, <code>.ha-lang--dropdown</code>); das Kit setzt die Variablen <code>--ha-accent, --ha-on, --ha-ink, --ha-muted, --ha-line, --ha-bg, --ha-surface, --ha-open, --ha-h, --ha-gap, --ha-field-bg, --ha-anchor</code> und überschreibt mit gleicher Spezifität (lädt danach). Nur die Ecken (8 px, keine Pillen) setzt der Kern fest.</li>
    <li><b>Eigenes Markup:</b> <code>templates/partials/header-actions.php</code> ersetzt <code>app/Views/header-actions.php</code> und bekommt <code>$ha</code> (<code>HeaderActions::model()</code>: <code>cfg</code>, <code>cta</code>, <code>cta2</code>, <code>mini</code>, <code>contact</code>, <code>status</code>, <code>search</code>, <code>social</code>, <code>account</code>), <code>$slot</code> und <code>$opt</code>. <code>HeaderActions::widthRem()</code> schätzt die Breite für Container-Queries (<code>{kit}_nav_fit</code>).</li>
    <li><b>Barrierefreiheit:</b> Suchfelder in <code>&lt;form role="search" aria-label&gt;</code> mit Beschriftung, Vorschläge als ARIA-Combobox mit <code>aria-live</code> (<code>search.js</code>), <kbd>/</kbd> und <kbd>⌘/Strg</kbd>+<kbd>K</kbd> (<code>aria-keyshortcuts</code>; nicht in Eingabefeldern oder Editoren), <kbd>Esc</kbd> schließt Popover bzw. Feld, Statuspunkt mit Textalternative, <code>forced-colors</code> und „Bewegung reduzieren“ berücksichtigt; kein Inline-Skript/-Stil, keine externen Anfragen.</li>
  </ul>
  <h3 id="rich-text-stile">Rich-Text-Stile (Klassenvertrag)</h3>
  <p>Die Formatierungsleiste erzeugt nur diese Klassen und Elemente – <code>Core\Sanitizer</code> lässt genau diese Werte zu und entfernt alles andere (auch <code>style</code>, <code>on…</code>, fremde Klassen). Jedes Kit gestaltet sie selbst:</p>
  <table class="doc-table">
    <tr><th>Markup</th><th>Bedeutung</th><th>Empfehlung</th></tr>
    <tr><td><code>&lt;p class="t-lead"&gt;</code></td><td>Hervorgehoben: Einleitung/Kernaussage, keine Überschrift</td><td>ca. 1,2–1,3 em, Farbe wie Überschriften; zählt in der Suche wie normaler Text</td></tr>
    <tr><td><code>&lt;p class="t-small"&gt;</code></td><td>Klein, Anmerkung</td><td>ca. 0,875 em – nicht kleiner, Lesbarkeit</td></tr>
    <tr><td><code>&lt;p class="t-note"&gt;</code></td><td>Hinweis-Box</td><td>Fläche + Linie links, Kontrast des Textes ≥ 4,5:1 auf der Fläche</td></tr>
    <tr><td><code>&lt;span class="c-accent|c-muted|c-success|c-warning|c-danger"&gt;</code></td><td>Textfarben (max. fünf, keine freien Farben)</td><td>Farbe über <code>--rt-accent</code>, <code>--rt-muted</code>, <code>--rt-success</code>, <code>--rt-warning</code>, <code>--rt-danger</code> setzen – die Leiste liest diese Variablen am bearbeiteten Text und prüft den Kontrast gegen den echten Hintergrund</td></tr>
    <tr><td><code>&lt;mark&gt;</code>, <code>&lt;sup&gt;</code>, <code>&lt;sub&gt;</code></td><td>Textmarker, hoch-/tiefgestellt</td><td>Marker mit dunkler Schrift auf heller Fläche (in allen Farbschemata)</td></tr>
    <tr><td><code>&lt;a href data-link title target="_blank" rel="noopener"&gt;</code></td><td>Link; <code>data-link</code> = stabiler Verweis (<code>page:12</code>, <code>page:12#anker</code>, <code>entry:news:5</code>, <code>media:9[:viewer]</code>)</td><td><code>href</code> wird bei jeder Ausgabe aus <code>data-link</code> neu berechnet (<code>Core\Links</code>)</td></tr>
  </table>
  <ul>
    <li><b>Hell/dunkel und farbige Abschnitte:</b> Die <code>--rt-*</code>-Werte je Kontext setzen – im Dunkelschema und auf dunklen Flächen hellere Varianten (Kern-Vorschlag: <code>#6FD69A</code>, <code>#F2C063</code>, <code>#FF9A8F</code>), hell <code>#1A7240</code>, <code>#8A4B00</code>, <code>#B42318</code> (≥ 4,5:1 auf Weiß und typischen Flächen); auf Akzentflächen blasse Töne oder <code>currentColor</code>. Da <code>var()</code> in Custom Properties am deklarierenden Element aufgelöst wird, die Variablen an jeder Abschnittsklasse neu setzen (z. B. <code>:root,.bg-white,.bg-dark{--rt-accent:var(--link)}</code>).</li>
    <li><b>Laden:</b> am besten in einer bedingten Datei mit dem Pseudo-Typ <code>'@rich'</code> in <code>conditional_css</code> – sie lädt nur, wenn die Seite solche Formatierungen (auch H2–H4 oder Zitat) tatsächlich ausgibt (<code>Core\Sanitizer::styled()</code>), so bleibt das Budget der Startseite unberührt. Beispiele: praxis <code>css/prose.css</code>, basis/editorial <code>css/rich.css</code>, fluid <code>css/prose.css</code>, essenz <code>css/b-prose.css</code>.</li>
    <li><b>Palette in der Verwaltung:</b> <code>Core\RichText::palette()</code> (Design-Token <code>accent_ink</code>/<code>accent</code>, <code>muted</code>, <code>background</code>, sonst Kern-Werte); überschreiben mit <code>theme.php → 'rich_text' =&gt; ['colors' =&gt; ['success' =&gt; ['#hell', '#dunkel']], 'background' =&gt; ['#FFF', '#111'], 'dark' =&gt; true]</code>.</li>
    </ul>
  <h3 id="varianten">Block-Varianten: bedingt laden, Felder je Variante, Einstieg mit Werkzeug</h3>
  <ul>
    <li><b><code>conditional_css</code> mit „Typ:Variante“</b> gilt für Stylesheets <i>und</i> Skripte, z. B. <code>'css/hero-x.css' =&gt; ['hero:search', 'hero:form']</code> – die Datei lädt nur auf Seiten mit dieser Variante (<code>SiteController::types()</code>).</li>
    <li><b><code>'uses'</code></b> in der Blockdefinition: <code>'uses' =&gt; ['figures' =&gt; ['dials'], 'dates' =&gt; ['upcoming'], 'form' =&gt; ['data_form'], 'map' =&gt; ['map']]</code> – eine Variante bringt die Stylesheets eines anderen (Kern-)Blocks mit (<code>Core\Theme::withUses()</code>).</li>
    <li><b>Felder je Variante:</b> <code>'variants' =&gt; ['search']</code> am Feld (Kapitel Feldtypen). <b><code>'variant_help'</code></b> <code>=&gt; ['variante' =&gt; 'Wann verwenden …']</code> erscheint im Handbuch der Redaktion (Alle Blöcke → Einstieg).</li>
    <li><b><code>Core\Blocks\Hero</code></b> (<code>app/Blocks/Hero.php</code>): Feld-Voreinstellungen (<code>Hero::fields('search'|'video'|'figures'|'dates'|'form'|'map'|'compare'|'gallery'|'quote'|'cards'|'marquee', [varianten])</code>) und Ausgabe-Teile, die in allen Kits gleich funktionieren müssen: Suchfeld mit Vorschlägen (<code>Search::form('hero', '', ['label', 'placeholder'])</code>), Vorschlags-Chips, Hintergrundvideo (eigene Datei, stumm, Schleife, Pause-Schaltfläche, bei „Bewegung reduzieren“ Standbild, spielt nur sichtbar), Vorher/Nachher (natives <code>input type=range</code>, ohne Skript Bilder nebeneinander), Laufzeile (läuft nur mit Skript, Pause), Kern-Kennzahlen ohne Container (<code>dials.php</code> mit <code>_bare</code>), nächste Termine bzw. neueste Einträge, öffentliches Formular, Karte. Markup-Rahmen und Aussehen liefert das Kit. Skript <code>public/assets/js/hero.mjs</code> (≈ 1,3 KB) bindet der Helfer nur bei Bedarf ein.</li>
</ul>
  <h3>Neues Projekt anlegen</h3>
  <pre><code>php bin/console kit:create kanzlei                  # Kopie des Start-Kits, Präfix starter_* → kanzlei_*
php bin/console kit:create kanzlei --from=basis     # oder eine andere Vorlage (alt: theme:create kanzlei basis)
cd tools && pnpm build                              # Assets + Kit-Vendoren bauen
php bin/console site:create kanzlei www.kanzlei.de kanzlei   # als eigene Website mit eigener Domain
# oder bestehende Website umstellen: Admin → Grundeinstellungen → Aktives Kit</code></pre>
  <h3 id="start-kit">Eigenes Kit entwickeln – mit dem Start-Kit</h3>
  <p>Das Kit <b><code>starter</code></b> ist das kleinste vollständige Kit: sechs kurze Beispielblöcke (<code>hero</code>, <code>text</code>, <code>text_image</code>, <code>cards</code>, <code>cta</code>, <code>faq</code>) plus <code>video</code> und <code>downloads</code> als Hüllen um Kern-Helfer, Design-Tokens mit hell/dunkel und zwei Navigationsvarianten, Systemschrift, eine Akzentfarbe. Jede Möglichkeit eines Kits ist einmal vorhanden und im Code erklärt – <code>theme.php</code> ist in Abschnitte <b>§ 1–11</b> gegliedert. Es ist <code>kit:create</code>s Standardvorlage und steht unter MIT; Ihr eigenes Kit darf eine beliebige Lizenz haben.</p>
  <ol>
    <li><b>Kopie:</b> <code>php bin/console kit:create meinkit</code> – kopiert <code>themes/starter</code> und <code>public/themes/starter</code>, benennt Präfix (<code>starter_</code> → <code>meinkit_</code>), <code>label</code>, <code>description</code>, Paketnamen und Pfade um, setzt <code>version</code> auf 0.1.0 und zeigt die nächsten Schritte.</li>
    <li><b>Bauen:</b> <code>cd tools &amp;&amp; pnpm run build</code>.</li>
    <li><b>Testwebsite:</b> <code>php bin/console site:create meinkit meinkit.localhost meinkit</code> und <code>user:create … admin --site=meinkit</code>; beim ersten Aufruf spielt der Core <code>seed.php</code> ein.</li>
    <li><b>Anpassen:</b> Meta (§ 1), Farben in § 6 <em>und</em> <code>assets/css/_tokens.css</code> (Standardwerte müssen übereinstimmen), Kopf/Fuß (<code>templates/partials/</code>), Blöcke (§ 8 + <code>blocks/</code>), Formular „Website“ (§ 7), Startinhalte (<code>tools/demo-content.php</code>).</li>
    <li><b>Prüfen</b> (Liste unten) und <code>CHANGELOG.md</code>, <code>README.md</code>, <code>LICENSE</code> anpassen.</li>
  </ol>
  <pre><code>themes/starter/
├── theme.php            § 1 Meta · § 2 Abschnitte · § 3 SEO &amp; JSON-LD · § 4 Projekt · § 5 Assets (conditional_css)
│                        § 6 Design-Tokens, Voreinstellungen, Navigationsvarianten · § 7 Website · § 8 Blöcke
│                        § 9 Daten (Listen/Detailseiten) · § 10 Startinhalte · § 11 Sprachen
├── functions.php        Helfer starter_* (Stammdaten, Links, Menü, Abschnittskopf, Buttons, JSON-LD, Manifest)
├── seed.php             settings, pages (→ tools/demo-content.php), page_refs, after
├── blocks/              hero, text, text_image, cards, cta, faq, video, downloads
├── templates/           layout, error, maintenance, offline
│   └── partials/        header (Navigation als popover, Suche, Sprachen), footer (+ footer_links()), section,
│                        video-embed (Zwei-Klick, Media::posterFor()), editor
├── assets/css/          _tokens.css (Tokens + Rollen, hell/dunkel) · _base.css · site.css (immer)
│                        blocks.css (Beispielblöcke) · core.css (nur Variablen für Kern-Blöcke)
├── assets/js/           site.js (≈ 0,4 KB) · video.js (nur mit Block „Video“)
├── lang/                en.php (Verwaltung) · site/en.php (Website, lt())
├── tools/               demo-content.php · contrast.php (WCAG aller Voreinstellungen)
├── build.mjs            optionaler Build-Hook (leer, Beispiel für Schriften)
└── composer.json        Stub, Typ „klxm-studio-kit“ – Paket-Installation geplant</code></pre>
  <p><b>Farben über Rollen:</b> Komponenten nutzen nur Rollen (<code>--s-fg</code>, <code>--s-head</code>, <code>--s-link</code>, <code>--s-card</code>, <code>--s-btn-*</code>), die Abschnitte „Akzentfarbe“ und „Dunkel“ stellen sie um. <b>Kern-Blöcke</b> behalten die neutralen Kern-Stylesheets; <code>core.css</code> setzt nur deren Variablen (<code>--dl-*</code>, <code>--dff-*</code>, <code>--cal-*</code>, <code>--dial-*</code>, <code>--cms-map-*</code>, <code>--cms-*</code>). <b>Schrift:</b> Systemschrift; eigene per <code>php bin/console fonts:install Inter</code> (danach im Style-Editor wählbar) oder mit <code>build.mjs</code> im Kit. <b>Paket-Installation</b> (Composer, wie Erweiterungen vom Typ <code>klxm-studio-extension</code>) ist für Kits geplant; bis dahin liegen Kits unter <code>themes/{name}/</code>.</p>
  <p><b>Prüfliste</b></p>
  <ul>
    <li><code>php bin/console health --site=meinkit</code> – u. a. „Kit kompatibel“ (<code>requires</code>)</li>
    <li>Budgets: Ausgabe von <code>pnpm run build</code> – CSS der Startseite &lt; 30 KB, JS &lt; 8 KB (Start-Kit: ≈ 15,7 KB / 0,4 KB)</li>
    <li>Kontrast: <code>php themes/meinkit/tools/contrast.php</code> (alle Voreinstellungen, hell + dunkel) und die Abzeichen im Style-Editor</li>
    <li>Übersetzungen: <code>php bin/console i18n:missing en --site-texts --site=meinkit</code> sowie ohne <code>--site-texts</code></li>
    <li>Lizenzen: <code>node tools/licenses.mjs</code> (z. B. nach Hinzufügen von Schriftpaketen)</li>
    <li>Im Browser: keine Konsolenfehler, keine externen Anfragen, keine Cookies; Tastatur, 200 % Zoom, Kontrastmodus, hell/dunkel, 320 px Breite; eine Testseite mit Datenliste + Detailseite, Formular, Kennzahlen, Karte, Video, Downloads und Suche</li>
  </ul>
  <h3>Block hinzufügen</h3>
  <pre><code>// theme.php → 'blocks'
'faq_short' => [
    'label' => 'Kurz-FAQ', 'icon' => '?', 'group' => 'Inhalt',
    'variants' => ['plain' => 'Schlicht', 'boxed' => 'Mit Rahmen'],
    'fields' => [
        ['name' => 'title_strong', 'label' => 'Überschrift', 'type' => 'text', 'required' => true],
        ['name' => 'items', 'label' => 'Fragen', 'type' => 'repeater', 'fields' => [
            ['name' => 'q', 'label' => 'Frage', 'type' => 'text'],
            ['name' => 'a', 'label' => 'Antwort', 'type' => 'richtext'],
        ]],
    ],
],

// blocks/faq_short.php
&lt;div class="wrap"&gt;
  &lt;h2 id="&lt;?= e($b-&gt;titleId()) ?&gt;"&lt;?= $b-&gt;edit('title_strong') ?&gt;&gt;&lt;?= e($d['title_strong']) ?&gt;&lt;/h2&gt;
  &lt;?php foreach ($d['items'] as $i =&gt; $it): ?&gt;
    &lt;details&gt;&lt;summary&gt;&lt;?= e($it['q']) ?&gt;&lt;/summary&gt;&lt;?= rich($it['a']) ?&gt;&lt;/details&gt;
  &lt;?php endforeach; ?&gt;
&lt;/div&gt;</code></pre>
  <p>Der Block erscheint automatisch im Editor (Feldformular, Live-Vorschau, Abschnitts-Optionen, Drag &amp; Drop), in der REST-API und im MCP-Server.</p>
  <h3>Template-Helfer</h3>
  <table class="doc-table">
    <tr><th>Helfer</th><th>Zweck</th></tr>
    <tr><td><code>e($v)</code></td><td>HTML-Escaping – für jede Ausgabe</td></tr>
    <tr><td><code>rich($html)</code>, <code>inline($html)</code></td><td>Rich-Text über Whitelist ausgeben</td></tr>
    <tr><td><code>setting('key')</code>, <code>filled($v)</code></td><td>Einstellung lesen; „befüllt und kein [Platzhalter]“</td></tr>
    <tr><td><code>url('/pfad')</code>, <code>link_href($link)</code>, <code>tel_href($nr)</code></td><td>URLs (Rewrite-sicher), Link-Felder, Telefon-Links</td></tr>
    <tr><td><code>img($id, $sizes, $opt)</code></td><td><code>&lt;picture&gt;</code> mit AVIF/WebP-srcset, width/height, lazy</td></tr>
    <tr><td><code>theme_asset()</code>, <code>asset()</code></td><td>Asset-URL mit Cache-Busting</td></tr>
    <tr><td><code>$b-&gt;edit('pfad')</code>, <code>$b-&gt;central()</code></td><td>Inline-Editing bzw. Markierung „zentral gepflegt“ (nur im Editor); an den Datensatz gebundene Felder werden als <code>data-bound</code> gekennzeichnet und sind nicht inline bearbeitbar</td></tr>
    <tr><td><code>app()-&gt;entry</code></td><td>Auf Detailseiten: <code>['table' =&gt; …, 'entry' =&gt; …]</code> des aufgerufenen Datensatzes</td></tr>
    <tr><td><code>$b-&gt;tune('anchor')</code>, <code>$b-&gt;variant()</code>, <code>$b-&gt;titleId()</code></td><td>Abschnitts-Optionen, Variante, ID für aria-labelledby</td></tr>
  </table>
  <h3>Aktuelle Blöcke im Kit „<?= e(app()->theme->label()) ?>“</h3>
  <p><?php foreach ($blocks as $type => $b): ?><code><?= e($type) ?></code> <?php endforeach; ?></p>
