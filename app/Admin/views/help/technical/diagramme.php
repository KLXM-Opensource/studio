<?php /** Entwicklerhandbuch · Diagramme (Core\Charts) – eigenes Format, austauschbare Engine (Standard: Apache ECharts) */ ?>
  <p class="lead">Diagramme in Verwaltung und Website laufen über <code>Core\Charts</code>. Aufrufer beschreiben ein Diagramm in einem eigenen, schlichten Format – nie im Format einer Bibliothek. Die Engine, die zeichnet, ist austauschbar; Standard ist Apache ECharts (Apache-2.0), gebündelt und selbst gehostet. Musterseite mit allen Arten: <a href="<?= e(url('/admin/hilfe/ui?teil=diagramme')) ?>">Oberfläche → Diagramme</a>.</p>
  <table class="doc-table">
    <tr><th>Schlüssel</th><th>Bedeutung</th></tr>
    <tr><td><code>type</code></td><td><code>line</code>, <code>area</code>, <code>bar</code>, <code>hbar</code>, <code>pie</code>, <code>donut</code> (Torte/Ring: eine Reihe)</td></tr>
    <tr><td><code>labels</code>, <code>series</code></td><td>Kategorien und Reihen <code>[['name' =&gt; …, 'data' =&gt; [1, 2, null]], …]</code> – bis 12 Reihen, 1000 Werte; Nicht-Zahlen werden <code>null</code></td></tr>
    <tr><td><code>title</code>, <code>description</code></td><td>sichtbarer Titel (optional) und Satz für Screenreader (<code>role="img"</code>, <code>aria-label</code>)</td></tr>
    <tr><td><code>format</code>, <code>unit</code></td><td><code>number</code>, <code>percent</code>, <code>bytes</code>, <code>duration</code> (Sekunden); Einheit hinter dem Wert</td></tr>
    <tr><td><code>stacked</code>, <code>height</code></td><td>Reihen stapeln (Balken, Fläche); Höhe 160–720 px in 40er-Schritten (Klassen, keine Inline-Stile)</td></tr>
  </table>
  <pre><code>echo \Core\Charts::render(['type' =&gt; 'bar', 'labels' =&gt; ['Jan', 'Feb'], 'series' =&gt; [['name' =&gt; __('Anfragen'), 'data' =&gt; [12, 19]]],
    'description' =&gt; __('Anfragen je Monat')], ['caption' =&gt; false, 'class' =&gt; 'meine-klasse', 'table' =&gt; false]);</code></pre>
  <h3 id="diagramme-ablauf">Ausgabe und Laden</h3>
  <ul>
    <li><code>render()</code> gibt <code>&lt;figure data-chart data-chart-engine="…"&gt;</code> aus: Fläche, Daten als <code>&lt;script type="application/json"&gt;</code> (nicht ausführbar, CSP-verträglich), Tabelle in <code>&lt;details&gt;</code> („Als Tabelle anzeigen“). Ohne JavaScript oder bei einem Fehler der Engine bleibt nur die Tabelle.</li>
    <li>Lader <code>resources/js/charts.mjs</code> (1,6 KB): auf der Website einmal je Seite von <code>render()</code> ausgegeben (dazu <code>css/charts.css</code>), in der Verwaltung fest im Layout. Er lädt die Engine erst in Sichtweite, erkennt nachgeladene Inhalte (MutationObserver) und zeichnet bei Wechsel von Hell/Dunkel oder Akzent neu. Ohne Diagramm lädt nichts.</li>
    <li>Farben: Reihen aus <code>--chart-1</code> … <code>--chart-8</code> (hell) bzw. <code>--chart-d1</code> … <code>--chart-d8</code> (dunkel) – Standard ist eine geprüfte Palette in fester Reihenfolge. Text, Achsen, Raster und Fläche leitet der Lader aus der Schriftfarbe und dem Hintergrund an der Stelle des Diagramms ab; helle Schrift bedeutet dunklen Grund und damit die dunklen Stufen. So passt ein Diagramm in jedes Kit und jeden Abschnitt, ohne dessen Dunkelmodus zu kennen. Kits können alles festlegen: <code>--chart-ink</code>, <code>--chart-muted</code>, <code>--chart-grid</code>, <code>--chart-bg</code>, <code>--chart-dark</code> (1/0). Die Verwaltung setzt sie aus ihren eigenen Farben.</li>
    <li>Tooltip nur mit Klassen (<code>.cms-chart-tip</code>, Farbpunkt <code>.cms-chart-c0</code> … <code>c7</code>) – keine <code>style</code>-Attribute, die die CSP der Website blockieren würde.</li>
  </ul>
  <h3 id="diagramme-engine">Engine tauschen</h3>
  <p>Eine Engine ist ein ES-Modul mit genau einer Funktion: <code>export function render(el, spec, theme)</code> → <code>{ update(theme), resize(), dispose(), image() }</code> – <code>image()</code> ist optional und liefert eine Bild-URL, die der Lader vor dem Drucken anstelle der Fläche einsetzt (Canvas passt sich der Papierbreite sonst nicht an). <code>spec</code> ist das Ergebnis von <code>Charts::normalize()</code> (dazu <code>locale</code>), <code>theme</code> = <code>{colors[], ink, muted, grid, bg, font, dark}</code>. Vorlage: <code>resources/js/charts-echarts.mjs</code> – die einzige Datei, die ECharts kennt (tree-shaken: Linie, Balken, Torte, Canvas; ≈ 550 KB, gzip ≈ 180 KB, lädt nur bei Bedarf). Anmelden und wählen:</p>
  <pre><code>\Core\Charts::registerEngine('meine', asset('ext/meine/js/charts-meine.mjs'));   // Erweiterung oder Kit
// Wahl: config/config.local.php bzw. config/sites/{key}.php 'charts' =&gt; ['engine' =&gt; 'meine'], im Kit theme.php 'chart_engine' =&gt; 'meine' (nur Website)</code></pre>
  <p>Aufrufer, Blöcke und Erweiterungen ändern sich beim Wechsel nicht. Neue Diagrammarten kommen über <code>Charts::TYPES</code> und alle Engines gemeinsam dazu.</p>
  <h3 id="diagramme-block">Block „Diagramm“ und Datenquellen</h3>
  <p>Kern-Block <code>chart</code> (<code>app/Blocks/chart.php</code>, Felder <code>Charts::blockFields()</code>, im Kit überschreibbar): Werte von Hand (Beschriftungen je Zeile, Werte je Reihe mit <code>;</code> oder Zeilenumbruch; <code>Charts::number()</code> liest „1.250“, „12,5“, „3.5“) oder aus einer angemeldeten Quelle. Erweiterungen melden Quellen an, deren Werte laufend aktuell bleiben:</p>
  <pre><code>\Core\Charts::registerSource('stats.visitors', __('Besucher je Tag (30 Tage)'), fn(array $blockData) =&gt; [
    'labels' =&gt; [...], 'series' =&gt; [['name' =&gt; __('Besucher'), 'data' =&gt; [...]]],
]);</code></pre>
  <p>Selbsttest: <code>php bin/console charts:selftest</code> (Beschreibung, Zahlen, Ausgabe ohne <code>style</code>-Attribute, Maskierung, Quellen, Engine-Dateien).</p>
