<?php /** Entwicklerhandbuch · PHP-API-Referenz: Schnittstellen für Erweiterungen und Kits, live aus dem Code (Core\ApiReference) */
$__ref = \Core\ApiReference::build(false);
$__n = count(\Core\ApiReference::classes());
?>
  <p class="lead">Alle Klassen, Methoden und Signaturen entstehen beim Aufruf aus dem Code – die Referenz kann nicht veralten. Hier die <b>Schnittstellen für Erweiterungen und Kits</b> (stabil, mit <span class="tag tag--read">API</span> markiert); alle <?= (int) $__n ?> Klassen des Kerns zeigt die <a href="<?= e(url('/admin/hilfe/php-api')) ?>">vollständige PHP-API-Referenz</a>. Für GitHub: <code>php bin/console docs:api</code> schreibt sie als Markdown nach <code>docs/php-api.md</code>.</p>
  <ul>
    <li><b>Stabil</b> sind die markierten Klassen und alle Ereignisse unter <code>Core\Events</code>. Erweiterungen greifen nur über sie ein (<a href="#erweiterungen">Integrationspunkte und Regeln</a>); alle anderen Klassen sind intern und können sich ändern.</li>
    <li>Weitere Schnittstellen: <a href="#api">REST-API</a> (OpenAPI 3.1), <a href="#mcp">MCP-Server</a>, <a href="#cli">Kommandozeile</a>, Hilfsfunktionen in <code>app/helpers.php</code> (<code>e()</code>, <code>url()</code>, <code>fmt()</code>, <code>__()</code>, <code>lt()</code>, <code>can()</code>, <code>site()</code>, <code>app()</code>, <code>icon()</code>, <code>csrf_field()</code>).</li>
  </ul>
  <?php foreach ($__ref as $__ns => $__list): ?>
  <h4><code><?= e($__ns) ?></code></h4>
  <?php foreach ($__list as $cls => $c): include __DIR__ . '/../_php-api-class.php'; endforeach; ?>
  <?php endforeach; ?>
