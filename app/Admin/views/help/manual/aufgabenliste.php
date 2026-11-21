<?php /** Handbuch · Kapitel „Aufgaben für die Redaktion“ (Verwaltung → Aufgaben, Core\Tasks) · @var callable $anchor */ ?>
  <p class="lead">Offene Fragen und To-dos gehören nicht in die Mail oder auf einen Zettel: Unter <a href="<?= e(url('/admin/aufgaben')) ?>">Aufgaben</a> halten Sie fest, was zu tun ist, wer sich darum kümmert und bis wann. Jede offene Aufgabe steht in <b>Was ist zu tun?</b> auf der <a href="#<?= e($anchor('uebersicht')) ?>">Übersicht</a> – so lange, bis jemand sie abhakt.</p>
  <h3>Aufgabe anlegen</h3>
  <ol class="doc-steps">
    <li>Auf der Übersicht in „Was ist zu tun?“ <b>Aufgabe anlegen</b> wählen – oder unter <b>Aufgaben</b> → <b>Neue Aufgabe</b>, oder direkt an einer Seite (Seiteneinstellungen) bzw. einem Eintrag im Abschnitt <b>Aufgaben</b>. Dort ist der Bezug schon eingetragen.</li>
    <li><b>Titel</b> eintragen, kurz und konkret – z. B. „Öffnungszeiten im Sommer prüfen“. Unter <b>Beschreibung</b> steht, was genau offen ist (möglich: <code>**fett**</code>, Listen mit „- “, Links).</li>
    <li>Optional einen <b>Bezug</b> wählen: „Auswählen …“ öffnet den Struktur-Browser – eine Seite, ein Abschnitt einer Seite (Anker), ein Eintrag oder eine Datei. Die Aufgabe verlinkt dann genau dorthin, auch wenn sich die Adresse später ändert.</li>
    <li><b>Zuständig</b>: eine Person, eine Rolle (z. B. alle aus der Redaktion) oder alle in der Verwaltung. Dazu optional <b>Fällig am</b> und die <b>Priorität</b> → <b>Aufgabe anlegen</b>.</li>
  </ol>
  <p>Wer zuständig ist, bekommt eine E-Mail mit Link. Ohne das Recht „Aufgaben für andere anlegen“ legen Sie Aufgaben nur für sich selbst an – als persönliche Merkliste.</p>
  <h3>Abhaken, kommentieren, wieder öffnen</h3>
  <table class="doc-table">
    <tr><th>Aktion</th><th>So geht’s</th></tr>
    <tr><td>Erledigt</td><td>In der Liste <b>Erledigt</b> → optional ein Kommentar („Nummer aus dem Handelsregister eingetragen“) → <b>Als erledigt markieren</b>. Auf der Übersicht hakt „Erledigt“ ohne Kommentar ab. Abhaken darf, wer zuständig ist (auch über die Rolle oder „alle“) oder die Aufgabe angelegt hat.</td></tr>
    <tr><td>Wieder öffnen</td><td>Unter <b>Erledigt</b> bzw. in der Aufgabe <b>Wieder öffnen</b> – sie steht dann wieder in „Was ist zu tun?“.</td></tr>
    <tr><td>Verlauf</td><td>Ein Klick auf den Titel zeigt die Aufgabe mit allen Angaben und dem Verlauf: wer sie wann angelegt, neu zugewiesen, erledigt oder wieder geöffnet hat – mit Kommentar.</td></tr>
    <tr><td>Bearbeiten, löschen</td><td>Für Personen mit dem Recht „Aufgaben für andere anlegen“ sowie für eigene Aufgaben. Besser abhaken statt löschen – dann bleibt der Verlauf.</td></tr>
  </table>
  <p>Die Reiter oben filtern: <b>Offen</b>, <b>Meine</b> (Ihnen oder Ihrer Rolle zugewiesen), <b>Erledigt</b> (das Protokoll: wer hat wann was mit welchem Kommentar erledigt) und <b>Alle</b>. Hohe Priorität und Überfälliges sind markiert und stehen oben.</p>
  <h3>Entwürfe mit zuständiger Person</h3>
  <p>Hat jemand unter <a href="#<?= e($anchor('entwuerfe')) ?>">Entwürfe</a> eine Notiz mit zuständiger Person hinterlegt, erscheint der Entwurf für diese Person ebenfalls als Aufgabe („Entwurf prüfen: …“). Erledigt ist er, sobald er veröffentlicht oder verworfen wird.</p>
  <h3>Benachrichtigungen und Erinnerung</h3>
  <ul>
    <li><b>E-Mail</b> bei neuen Aufgaben für Sie oder Ihre Rolle – abschaltbar unter <a href="<?= e(url('/admin/account#aufgaben')) ?>">Konto → Mitteilungen → Aufgaben</a>.</li>
    <li><b>Push-Mitteilung</b> „Aufgaben für Sie“ – sofern Mitteilungen auf dem Gerät eingeschaltet sind (Konto → Benachrichtigungen).</li>
    <li><b>Erinnerung an fällige Aufgaben</b> per E-Mail – schaltet die Administration unter Aufgaben ein (Standard: aus). Dann gibt es eine Erinnerung am Tag vor und am Tag der Fälligkeit, überfällige höchstens einmal pro Woche.</li>
  </ul>
  <div class="doc-note doc-note--info"><strong>Für Agenturen und KI-Assistenten</strong><p>Beim Umzug einer Website lassen sich offene Fragen gesammelt hinterlegen – über die Kommandozeile (<code>tasks:add</code>), die REST-API oder einen KI-Assistenten über MCP (<code>task_create</code>, mit Bezug zur Seite). Die Redaktion beantwortet sie im Kommentar und hakt sie ab; die Agentur sieht das Ergebnis mit <code>task_list</code>. Einzelheiten im Entwicklerhandbuch.</p></div>
