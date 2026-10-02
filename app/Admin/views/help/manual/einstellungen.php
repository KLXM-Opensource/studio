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
    <li><b>Visitenkarte:</b> Aus Name, Adresse, Telefon, E-Mail, Logo, Standort und Öffnungszeiten entsteht automatisch eine Visitenkarte zum Speichern im Adressbuch: <a href="<?= e(url('/vcard.vcf')) ?>"><code>/vcard.vcf</code></a>. Sie ist da, sobald Telefon, E-Mail oder Adresse eingetragen sind, und immer aktuell. Verlinken Sie sie z. B. als „Kontakt speichern“ (Link-Feld: Adresse <code>/vcard.vcf</code>). Personen einer Tabelle mit „Strukturierte Daten: Person“ (z. B. Team) haben eigene Karten unter <code>/vcard/{tabelle}/{adresse-des-eintrags}.vcf</code>.</li>
    <?php if (\Core\Notice::has()): ?><li><b>Hinweisbalken:</b> Mit <b>Anzeigen ab</b> und <b>Anzeigen bis</b> planen Sie einen Hinweis im Voraus (z. B. Betriebsferien) – er erscheint und verschwindet von selbst, der Haken muss dafür gesetzt sein. Unter <b>Darstellung</b> wählen Sie den Balken oben (wie im Design, linksbündig oder zentriert) oder eine <b>schwebende Bubble</b> unten links, unten mittig oder mitten im Bildschirm, die Besucher schließen können. Optional machen <b>Farbe</b> (z. B. Signalgelb, Rot) und <b>Hervorheben</b> (Pulsieren, kurz wackeln, Leuchten) den Hinweis auffälliger – die Bewegung läuft nur wenige Sekunden.</li><?php endif; ?>
    <li>Mit dem <b>Stern</b> merken Sie sich einen Reiter als Favorit.</li>
  </ul>
  <?php include __DIR__ . '/_design.php'; ?>
