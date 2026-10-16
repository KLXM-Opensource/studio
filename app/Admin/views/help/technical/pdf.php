<?php /** Entwicklerhandbuch · PDF erzeugen (Core\Pdf) – HTML → PDF mit austauschbarer Engine, dompdf als eigenes Paket */ ?>
  <p class="lead">PDF-Dateien (Report, AVV, Rechnungen …) entstehen über <code>Core\Pdf</code> aus HTML. Die Engine ist austauschbar; Standard ist dompdf. dompdf steht unter LGPL-2.1 und gehört deshalb nicht zum MIT-Kern, sondern kommt mit dem Paket <code>klxm/studio-pdf</code> (Erweiterung „pdf“). <b>Installiert = verfügbar</b> – ein Einschalten je Website ist nicht nötig.</p>
  <pre><code>if (Core\Pdf::available()) {
    $bytes = Core\Pdf::render($html, ['paper' =&gt; 'A4', 'font' =&gt; 'lato', 'title' =&gt; 'Report', 'pages' =&gt; true, 'footer' =&gt; 'Beispiel GmbH']);
    return Core\Pdf::response($bytes, 'Report-2026-10.pdf');          // inline: true = im Browser öffnen
}
// ohne Engine: z. B. „Drucken“ anbieten</code></pre>
  <table class="doc-table">
    <tr><th>Option</th><th>Bedeutung</th></tr>
    <tr><td><code>paper</code>, <code>orientation</code></td><td>Papierformat (Standard <code>A4</code>) und <code>portrait</code> | <code>landscape</code></td></tr>
    <tr><td><code>font</code></td><td>Standard <b>Lato</b> – im Kern mitgeliefert (<code>resources/fonts/pdf</code>, OFL) in Light, Regular, Bold und Black, je kursiv; ohne Netz. Andere freie Schriften über ihre Fontsource-Kennung (z. B. <code>inter</code>): einmal je Installation als TTF nach <code>storage/pdf-fonts/</code> geladen, danach ohne Netz; <code>weights</code> lädt weitere Stärken (z. B. <code>[300, 900]</code>). Im CSS immer als Familie <code>PdfFont</code> mit <code>font-weight</code> 300–900. <code>''</code>: DejaVu Sans. <code>display</code>: zweite Schrift für Überschriften (Familie <code>PdfDisplay</code>), z. B. <code>source-serif-4</code>.</td></tr>
    <tr><td><code>title</code></td><td>Titel in den Dokumenteigenschaften</td></tr>
    <tr><td><code>pages</code>, <code>footer</code></td><td>„Seite x von y“ unten rechts bzw. Text unten links auf jeder Seite</td></tr>
    <tr><td><code>dir</code></td><td>Ordner für Hilfsdateien (Standard <code>{storage}/pdf</code>); nur dieser und der Schriftordner sind lesbar (chroot)</td></tr>
  </table>
  <ul>
    <li><b>Sicherheit:</b> keine Dateien aus dem Netz (<code>isRemoteEnabled</code> aus), kein PHP im HTML. Bilder und Diagramme als <code>data:</code>-URL einbetten (Diagramme: PNG aus <code>cmsCharts</code> bzw. <code>image()</code> der Engine).</li>
    <li><b>Installation:</b> <code>composer require klxm/studio-pdf</code> im Kern oder den Ordner nach <code>extensions/pdf</code> und dort <code>composer install --no-dev</code>. Prüfen: Erweiterung einschalten und <code>php bin/console pdf:selftest</code>.</li>
    <li><b>Eigene Engine</b> (z. B. ein Druckdienst oder Chromium): <code>Pdf::registerEngine('name', fn(string $html, array $opt): string =&gt; …)</code> und Konfiguration <code>'pdf' =&gt; ['engine' =&gt; 'name']</code>. Aufrufer ändern sich nicht.</li>
    <li><b>Zeilenhöhe:</b> Mit einer Fontsource-Schrift gilt die Zeilenhöhe aus dem CSS (Grundwert <code>1.3</code>; eigene Werte am besten in <code>pt</code>). Hintergrund: Die Schriftdateien tragen eine große Bounding Box, die dompdf sonst als Mindesthöhe nimmt – der Kern setzt deshalb <code>fontHeightRatio</code> 0,85 (Option <code>font_height_ratio</code>).</li>
    <li>dompdf versteht CSS 2.1 mit Teilen von CSS 3 – kein Flexbox/Grid. Für Spalten Tabellen oder <code>float</code> verwenden; Seitenumbrüche mit <code>page-break-before</code>/<code>-inside</code>.</li>
  </ul>
