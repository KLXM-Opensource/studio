<?php /** Handbuch · Kapitel „zentrale Angaben“ (Titel aus dem Kit) · @var string $settingsTitle  @var callable $has */ ?>
  <p class="lead">Hier stehen alle Angaben, die an mehreren Stellen der Website erscheinen. Einmal ändern – überall aktuell. Änderungen gelten sofort nach <b>Speichern</b>.</p>
  <table class="doc-table">
    <tr><th>Reiter</th><th>Enthält</th></tr>
    <?php foreach (app()->theme->settingsGroups() as $__g): $__labels = array_filter(array_column($__g['fields'], 'label')); ?>
    <tr><td><?= e($__g['label']) ?></td><td><?= e(implode(', ', array_slice($__labels, 0, 6))) ?><?= count($__labels) > 6 ? ' …' : '' ?></td></tr>
    <?php endforeach; ?>
  </table>
  <ul>
    <li><b>Vorschau:</b> Rechts zeigt die Seite auf Wunsch die echte Startseite mit Ihren <b>noch nicht gespeicherten</b> Werten – auf dem Computer und dem Handy.</li>
    <li><b>Mehrere Sprachen:</b> Über dem Formular wählen Sie die Sprache. In einer weiteren Sprache erscheinen nur Felder, die übersetzt werden können (Texte); leere Felder zeigen den Text der Standardsprache. Telefon, E-Mail und Adresse gelten für alle Sprachen.</li>
    <li>Mit dem <b>Stern</b> merken Sie sich einen Reiter als Favorit.</li>
  </ul>
  <?php include __DIR__ . '/_design.php'; ?>
