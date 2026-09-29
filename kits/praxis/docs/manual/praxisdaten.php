<?php /** Handbuch „praxis“ · Kapitel „Praxisdaten“ (ersetzt die zentralen Angaben des Kerns) · @var string $settingsTitle  @var callable $img */ ?>
  <p class="lead">Hier stehen alle Angaben, die an mehreren Stellen erscheinen. Einmal ändern – überall aktuell: Kopfzeile, Kontaktkarte, Kontaktbereich, Fußzeile und die Daten für Google. Änderungen gelten sofort nach <b>Speichern</b>.</p>
  <?= $img('website-daten.webp', 'Praxisdaten mit Reiter Erreichbarkeit', '<b>' . e($settingsTitle) . ':</b> nach Reitern gegliedert, Änderungen sind nach „Speichern“ sofort sichtbar.') ?>
  <table class="doc-table">
    <tr><th>Reiter</th><th>Was Sie hier pflegen</th></tr>
    <tr><td>Stammdaten</td><td>Praxisname, Wortmarke im Kopf (zwei Zeilen), Adresse, Telefon (für den Wähl-Link und zur Anzeige), Fax, E-Mail.</td></tr>
    <tr><td>Erreichbarkeit</td><td><b>Sprechzeiten</b> je Wochentag, telefonische Erreichbarkeit, Hinweis unter den Sprechzeiten.</td></tr>
    <tr><td>Termine</td><td>Online-Terminbuchung (Doctolib) an/aus, Link, Hinweistext, Beschriftung des Termin-Buttons.</td></tr>
    <tr><td>Online-Services</td><td>Rezept und Überweisung an/aus; internes verschlüsseltes Formular oder Link zu einem externen Dienst; Bearbeitungsfrist, Hinweis unter dem Formular. Unter „Formularfelder“ führen Links zu den Feldern der beiden Eingänge und zu den Anfragen.</td></tr>
    <tr><td>Hinweise</td><td><b>Aktueller Hinweis</b> (farbiger Balken ganz oben, z. B. Urlaub), Status-Badge „Jetzt geöffnet“ an/aus, <b>Praxis geschlossen von/bis</b>, Notfall-Texte.</td></tr>
    <tr><td>Hero-Themen</td><td>Die wechselnden Themen im Kopfbereich – mit „Sichtbar ab/bis“ für saisonale Themen.</td></tr>
    <tr><td>Anfahrt</td><td>Bus und Bahn, Parken, Barrierefreiheit vor Ort, Routenplaner-Link, Karte im Kontaktbereich und Standort.</td></tr>
    <tr><td>Recht</td><td>Welche Seiten als Impressum, Datenschutz und Barrierefreiheit im Fuß verlinkt sind.</td></tr>
    <tr><td>SEO</td><td>Seitentitel der Startseite, Titel-Zusatz, Standard-Beschreibung, Vorschaubild für soziale Netzwerke.</td></tr>
  </table>
  <p>Rechts zeigt auf Wunsch eine <b>Vorschau</b> die Startseite mit Ihren noch nicht gespeicherten Werten; bei Hero-Themen zeigt „Vorschau dieses Eintrags“ genau das gewählte Thema. Auf mehrsprachigen Websites wählen Sie über dem Formular die Sprache – übersetzbar sind die Texte, Telefon und Adresse gelten für alle Sprachen.</p>

  <h3>Sprechzeiten eintragen</h3>
  <p>Pro Wochentag ein Eintrag. Eine <b>Mittagspause</b> teilt den Tag in Vor- und Nachmittag. Beispiel für die aktuellen Zeiten:</p>
  <div class="doc-scroll"><table class="doc-table">
    <tr><th>Wochentag</th><th>Von</th><th>Bis</th><th>Pause von</th><th>Pause bis</th><th>Notiz</th><th>Ergebnis auf der Website</th></tr>
    <?php foreach (praxis_hours() as $__h): $__row = null; foreach ((array) setting('oeffnungszeiten', []) as $__r) { if ((int) $__r['tag'] === $__h['dow']) { $__row = $__r; } } ?>
    <tr><td><?= e($__h['day']) ?></td><td><?= e($__row['von'] ?? '') ?></td><td><?= e($__row['bis'] ?? '') ?></td><td><?= e($__row['pause_von'] ?? '') ?></td><td><?= e($__row['pause_bis'] ?? '') ?></td><td><?= e($__row['notiz'] ?? '') ?></td><td><b><?= e($__h['time']) ?></b></td></tr>
    <?php endforeach; ?>
  </table></div>
  <div class="doc-note doc-note--tip"><strong>Automatisch richtig</strong><p>Die Kontaktkarte zeigt immer die Zeiten des heutigen Tages; Google erhält die Öffnungszeiten als strukturierte Daten. Tage ohne Eintrag gelten als geschlossen.</p></div>
  <h3>Status-Badge „Jetzt geöffnet“</h3>
  <p>Auf der Kontaktkarte und im Kontaktbereich zeigt ein Badge live den aktuellen Stand – immer nach der Ortszeit der Praxis (Zeitzone der Website), auch für Besucher in anderen Zeitzonen:</p>
  <table class="doc-table">
    <tr><th>Anzeige</th><th>Wann</th></tr>
    <tr><td><span class="tag tag--read">● Jetzt geöffnet · bis 11:00 Uhr</span></td><td>während der Sprechzeiten</td></tr>
    <tr><td><span class="tag tag--write">● Schließt bald · um 11:00 Uhr</span></td><td>in den letzten 30 Minuten</td></tr>
    <tr><td><span class="tag">● Geschlossen · öffnet um 15:30 Uhr</span></td><td>Mittagspause, abends, am Wochenende (mit nächster Öffnung)</td></tr>
    <tr><td><span class="tag tag--danger">● Praxis geschlossen bis 25.10.</span></td><td>im eingetragenen Zeitraum „Praxis geschlossen“ (<?= e($settingsTitle) ?> → Hinweise)</td></tr>
  </table>
  <p>Das Badge lässt sich unter <?= e($settingsTitle) ?> → Hinweise ausschalten. An Feiertagen bitte den Zeitraum „Praxis geschlossen“ nutzen.</p>
  <p><b>Geschlossen:</b> Statt der heutigen Zeiten zeigt die Kontaktkarte dann „Wir öffnen wieder um 15:30 Uhr“ (bzw. „morgen um …“, „am Montag um …“) und darunter groß „In dringenden Notfällen“: <b>116 117</b> (ärztlicher Bereitschaftsdienst) und <b>112</b> (Notruf) – beide Nummern zum Antippen. Der Chip sagt dann nur „Geschlossen“. Sätze mit 116 117 oder 112 in der <b>Notfall – Kurzform</b> (Hinweiszeile unten auf der Karte) blendet die Karte in dieser Zeit aus, damit nichts doppelt steht; der Rest (z. B. „Die Praxis ist nicht barrierefrei erreichbar“) bleibt. Überzählige Trennzeichen wie „! ·.“ räumt die Karte selbst auf.</p>
  <p><b>Termin (Doctolib) und externe Dienste:</b> Die Kachel dreht die Karte zu einem kurzen Hinweis „Sie verlassen unsere Website und wechseln zu Doctolib (doctolib.de) …“ mit <b>Weiter zu Doctolib</b> und <b>Abbrechen</b>.</p>

  <h3>Hero-Themen (Kopfbereich)</h3>
  <ul>
    <li><b>Hauptthema</b> ist die Hauptüberschrift der Seite und immer vorhanden.</li>
    <li><b>Begrüßung</b> zeigt je nach Uhrzeit „Guten Morgen / Tag / Abend“ – Titel leer lassen.</li>
    <li><b>Aktuelles Thema</b> für Saisonales (Grippeimpfung …). Mit <b>Sichtbar ab/bis</b> erscheint und verschwindet es automatisch.</li>
    <li>Die Themen wechseln alle 7 Sekunden; Besucher können anhalten. Empfohlen: max. 60 Zeichen Titel, 300 Zeichen Text.</li>
  </ul>
  <?php include ROOT . '/app/Admin/views/help/manual/_design.php'; ?>
